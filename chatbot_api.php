<?php
/**
 * Customer support chatbot (name and avatar are configurable in Admin > Chatbot Settings).
 * Answers courier-related questions only, uses live pricing / tracking data
 * and records every conversation for the admin panel.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/chatbot_ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function chatbot_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chatbot_rs(float $amount): string
{
    return 'Rs ' . number_format(round($amount), 0);
}

function chatbot_pct(float $value): string
{
    return rtrim(rtrim(number_format($value, 2), '0'), '.') . '%';
}

function chatbot_action(string $label, string $url): array
{
    return ['label' => $label, 'url' => $url];
}

function chatbot_score(array $tokens, string $normalized, array $keywords): int
{
    $score = 0;
    foreach ($keywords as $keyword) {
        if (str_contains($keyword, ' ')) {
            if (str_contains($normalized, $keyword)) {
                $score += 2;
            }
        } elseif (in_array($keyword, $tokens, true)) {
            $score += 1;
        }
    }
    return $score;
}

function chatbot_extract_tracking(string $message): ?string
{
    if (preg_match('/\bFC[\s-]?(\d{4})[\s-]?([A-Z0-9]{6})\b/i', $message, $match)) {
        return 'FC-' . $match[1] . '-' . strtoupper($match[2]);
    }
    return null;
}

function chatbot_lookup_shipment(PDO $pdo, string $trackingId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT sh.shipment_id, sh.tracking_id, sh.current_status, sh.updated_at, b.destination
         FROM Shipments sh
         JOIN Bookings b ON b.booking_id = sh.booking_id
         WHERE sh.tracking_id = ?
         LIMIT 1'
    );
    $stmt->execute([$trackingId]);
    $shipment = $stmt->fetch();
    if (!$shipment) {
        return null;
    }

    $history = $pdo->prepare(
        'SELECT status, location, remarks, updated_at
         FROM ShipmentHistory
         WHERE shipment_id = ?
         ORDER BY updated_at DESC, history_id DESC
         LIMIT 1'
    );
    $history->execute([$shipment['shipment_id']]);
    $shipment['last_update'] = $history->fetch() ?: null;
    return $shipment;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatbot_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if (!$originHost || strcasecmp($originHost, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
        chatbot_json(['ok' => false, 'error' => 'Invalid request origin.'], 403);
    }
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    chatbot_json(['ok' => false, 'error' => 'Invalid request body.'], 400);
}

$message = is_string($payload['message'] ?? null) ? trim($payload['message']) : '';
$message = (string) preg_replace('/\s+/u', ' ', $message);
$sessionId = is_string($payload['session'] ?? null) ? trim($payload['session']) : '';
$pageUrl = is_string($payload['page'] ?? null) ? mb_substr(trim($payload['page']), 0, 190) : '';

// Short conversation context for ChatGPT follow-up questions.
$history = [];
if (isset($payload['history']) && is_array($payload['history'])) {
    foreach (array_slice($payload['history'], -6) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $role = (string) ($item['role'] ?? '');
        $role = $role === 'bot' ? 'bot' : ($role === 'user' ? 'user' : '');
        $content = is_string($item['content'] ?? null) ? trim($item['content']) : '';
        if ($role !== '' && $content !== '') {
            $history[] = ['role' => $role, 'content' => mb_substr($content, 0, 500)];
        }
    }
}

if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $sessionId)) {
    $sessionId = 'guest-' . bin2hex(random_bytes(8));
}

if (mb_strlen($message) < 1) {
    chatbot_json(['ok' => false, 'error' => 'Please type a question.'], 422);
}
if (mb_strlen($message) > 500) {
    chatbot_json(['ok' => false, 'error' => 'Please keep your question under 500 characters.'], 422);
}

// Simple flood protection: max 20 questions per minute per browser session.
$now = time();
$recent = array_values(array_filter(
    (array) ($_SESSION['chatbot_times'] ?? []),
    static fn($time): bool => (int) $time > $now - 60
));
if (count($recent) >= 20) {
    chatbot_json([
        'ok' => true,
        'reply' => 'You are sending messages very quickly. Please wait a minute and try again, or contact us on WhatsApp for urgent help.',
        'intent' => 'rate_limited',
        'matched' => true,
        'actions' => [chatbot_action('WhatsApp Support', 'https://wa.me/' . whatsapp_digits())],
    ], 429);
}
$recent[] = $now;
$_SESSION['chatbot_times'] = $recent;

// ---- Language detection (English vs Roman Urdu) ----
$lower = mb_strtolower($message);
$normalized = ' ' . trim((string) preg_replace('/[^a-z0-9]+/', ' ', $lower)) . ' ';
$tokens = array_values(array_filter(explode(' ', trim($normalized))));
$urduScript = (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $message);
$romanMarkers = [
    'aap', 'ap', 'kya', 'kia', 'kaise', 'kesay', 'kab', 'kitna', 'kitne', 'kitni', 'kahan', 'kaha',
    'mujhe', 'mera', 'meri', 'hai', 'hain', 'nahi', 'nahin', 'karna', 'karni', 'chahiye',
    'bhejna', 'bhej', 'paisa', 'paise', 'shukriya', 'salam', 'assalam', 'bhai', 'yaar',
    'karo', 'kro', 'kare', 'karu', 'karon', 'kese', 'kaisay', 'hoga', 'hogi', 'mein', 'aur', 'koi', 'kuch', 'acha', 'theek', 'batao',
    'batayein', 'bata', 'batana', 'madad', 'sawal', 'jawab', 'lagana', 'pahunchna', 'pooch',
    'raha', 'rahi', 'milega', 'milegi', 'mile', 'gaya', 'gayi',
];
$romanHits = count(array_intersect($tokens, $romanMarkers));
$isUrdu = $urduScript || $romanHits >= 2;

// ---- Intent scoring ----
$intentKeywords = [
    'tracking' => [
        'track', 'tracking', 'trace', 'tracking id', 'shipment status', 'parcel status',
        'order status', 'where is my parcel', 'where is my shipment', 'where is my order',
        'kahan pohncha', 'parcel kahan', 'parcel kahan hai', 'shipment kahan',
        'shipment kahan hai', 'tracking kahan', 'tracking number', 'tracking id kya',
        'mera order', 'mera parcel', 'parcel kab ayega', 'parcel kab aayega',
        'kab milega', 'kab ayega', 'kab aayega', 'delivery kab',
    ],
    'pricing' => [
        'price', 'pricing', 'rate', 'rates', 'charge', 'charges', 'delivery charge',
        'delivery charges', 'cost', 'fee', 'fees', 'how much', 'kitna', 'kitne ka',
        'quote', 'estimate', 'paisa', 'paise',
    ],
    'delivery_time' => [
        'delivery time', 'how long', 'how many days', 'kitne din', 'kab tak', 'kab pohnche',
        'same day', 'express', 'urgent', 'jaldi', 'fast delivery', 'speed', 'time lagta',
    ],
    'booking' => [
        'book', 'booking', 'book a parcel', 'place order', 'place an order', 'send parcel',
        'send a parcel', 'parcel bhejna', 'bhejna', 'courier karna', 'new shipment',
        'order parcel', 'parcel send', 'booking kese', 'booking kaise', 'book kese',
        'book kaise', 'parcel kese bhejein', 'parcel kaise bhejein', 'booking process',
        'how to book', 'parcel book karna', 'order kese', 'order kaise', 'order karna',
        'delivery kese', 'delivery kaise',
    ],
    'coverage' => [
        'coverage', 'cities', 'city', 'deliver', 'delivery', 'deliver to', 'deliver in',
        'delivery in', 'delivery area', 'delivery areas', 'cover', 'covered',
        'kahan kahan', 'which cities', 'nationwide', 'all pakistan', 'pakistan',
        'international', 'international delivery', 'international shipping',
        'abroad', 'overseas',
    ],
    'payment' => [
        'payment', 'pay', 'cash on delivery', 'cod', 'easypaisa', 'jazzcash', 'bank',
        'iban', 'transaction id', 'online payment', 'advance payment', 'payment method',
        'account number', 'payment account', 'bank account',
    ],
    'refund' => [
        'refund', 'money back', 'return my money', 'paisa wapas', 'payment wapas',
    ],
    'pickup' => [
        'pickup', 'pick up', 'pick-up', 'collect my parcel', 'doorstep', 'door step',
        'ghar se', 'pickup service',
    ],
    'weight_cargo' => [
        'weight', 'kg', 'kilo', 'kilos', 'heavy', 'bulk', 'cargo', 'pallet', 'overweight',
        'oversize', 'dimension', 'dimensions', 'limit',
    ],
    'cancel' => [
        'cancel', 'modify', 'change address', 'reschedule', 'edit booking', 'booking change',
        'cancel karna', 'address change', 'change delivery',
    ],
    'complaint' => [
        'damage', 'damaged', 'broken', 'lost', 'missing', 'stolen', 'tampered', 'complaint',
        'shikayat', 'delayed', 'delay', 'late delivery', 'parcel nahi mila', 'wrong item',
    ],
    'contact' => [
        'contact', 'human', 'agent', 'support', 'helpline', 'call', 'phone', 'whatsapp',
        'email', 'talk to', 'baat karni', 'customer care', 'customer service',
        'representative', 'address', 'office address', 'office', 'location',
        'contact number', 'phone number', 'whatsapp number', 'rider', 'delivery boy',
    ],
    'packing' => [
        'packing', 'pack', 'packaging', 'wrap', 'wrapping', 'pack kese', 'packing kese',
        'parcel pack', 'parcel kese pack', 'how to pack', 'safe packing',
    ],
    'items' => [
        'laptop', 'mobile', 'mobile phone', 'cell phone', 'electronics', 'documents',
        'clothes', 'clothing', 'food', 'medicine', 'perfume', 'battery', 'power bank',
        'fragile', 'glass', 'gift', 'books', 'shoes', 'cake', 'bakery', 'allowed',
        'restricted', 'prohibited', 'illegal',
    ],
    'hours' => [
        'timing', 'timings', 'hours', 'working hours', 'office hours', 'office timing',
        'office timings', 'kab tak khula', 'sunday', '24/7', 'support hours',
    ],
    'account' => [
        'login', 'log in', 'sign in', 'signin', 'register', 'registration', 'signup', 'sign up',
        'password', 'forgot password', 'change password', 'reset password', 'my account',
        'profile', 'account', 'dashboard', 'create account', 'new account', 'mera account',
        'account banaye', 'account kese', 'login kese', 'login kaise', 'register kese',
        'register kaise', 'registration kese', 'registration kaise',
    ],
    'website' => [
        'website', 'web site', 'site', 'how to use', 'use kese', 'kese use', 'kaise use',
        'use kaise', 'how does it work', 'what can i do', 'features', 'guide', 'help',
        'madad', 'sahulat', 'website kese', 'website kaise', 'website help',
    ],
    'about' => [
        'about', 'company', 'fasted courier', 'about company', 'about fasted courier',
        'company kya', 'courier kya hai', 'founder', 'owner', 'history', 'kab bani', 'who owns',
    ],
    'barcode' => [
        'barcode', 'slip', 'print', 'receipt', 'label', 'shipping label',
    ],
    'greeting' => [
        'hello', 'hi', 'hey', 'salam', 'assalam', 'assalam o alaikum', 'good morning',
        'good afternoon', 'good evening', 'aoa',
    ],
    'thanks' => [
        'thanks', 'thank you', 'shukriya', 'jazakallah', 'great', 'awesome', 'perfect',
        'ok thanks',
    ],
    'bye' => [
        'bye', 'goodbye', 'good bye', 'see you', 'khuda hafiz', 'allah hafiz',
    ],
    'identity' => [
        'who are you', 'your name', 'what is your name', 'tum kon', 'aap kon', 'naam kya',
        'are you human', 'human ho', 'chatbot', 'bot',
    ],
];

$scores = [];
foreach ($intentKeywords as $intent => $keywords) {
    $scores[$intent] = chatbot_score($tokens, $normalized, $keywords);
}

$trackingId = chatbot_extract_tracking($message);
if ($trackingId !== null) {
    $scores['tracking'] += 20;
}

$intent = 'fallback';
$bestScore = 0;
foreach ($scores as $candidate => $score) {
    if ($score > $bestScore) {
        $bestScore = $score;
        $intent = $candidate;
    }
}
if ($bestScore === 0) {
    $intent = 'fallback';
}

// ---- Live data used by several answers ----
$services = $pdo->query(
    "SELECT s.service_name, p.base_price, p.per_kg, p.discount_percent, p.estimated_days
     FROM Services s JOIN Pricing p ON p.service_id = s.service_id
     WHERE s.status = 'Active'
     ORDER BY p.base_price"
)->fetchAll();

$paymentAccounts = $pdo->query(
    "SELECT account_type, account_title, account_number, bank_name
     FROM PaymentAccounts WHERE status = 'Active'
     ORDER BY sort_order, account_id"
)->fetchAll();

$supportHours = setting('support_hours', '24/7 Support - Monday to Sunday');
$contactPhone = setting('contact_phone', '+92 300 123 4567');
$contactEmail = setting('contact_email', 'info@fastedcourier.com');
$contactAddress = setting('contact_address', 'Head Office: 24-C, Shahrah-e-Faisal, Karachi, Pakistan');
$whatsappLink = 'https://wa.me/' . whatsapp_digits();
$botName = chatbot_bot_name();

$actions = [];
$reply = '';
$courierRelated = $intent !== 'fallback';

switch ($intent) {
    case 'tracking':
        if ($trackingId !== null) {
            $shipment = chatbot_lookup_shipment($pdo, $trackingId);
            if ($shipment === null) {
                $reply = $isUrdu
                    ? "Tracking ID {$trackingId} se koi shipment nahi mila. Baraye meharbani booking confirmation se ID dobara check karein. Agar phir bhi masla ho to hamari team se rabta karein."
                    : "I couldn't find a shipment with Tracking ID {$trackingId}. Please double-check the ID from your booking confirmation. If it still doesn't work, our team can help.";
                $actions = [
                    chatbot_action($isUrdu ? 'Tracking Page' : 'Track Shipment', url('track.php')),
                    chatbot_action($isUrdu ? 'Support se baat karein' : 'Contact Support', url('contact.php')),
                ];
            } else {
                $last = $shipment['last_update'];
                $lastLine = '';
                if ($last !== null) {
                    $when = date('d M Y, g:i A', strtotime((string) $last['updated_at']));
                    $place = trim((string) ($last['location'] ?? ''));
                    $remark = trim((string) ($last['remarks'] ?? ''));
                    $detail = $place !== '' ? ' at ' . $place : '';
                    if ($remark !== '') {
                        $detail .= ' - ' . $remark;
                    }
                    $lastLine = $isUrdu
                        ? "Aakhri update: {$last['status']}{$detail} ({$when})"
                        : "Last update: {$last['status']}{$detail} ({$when})";
                }
                $reply = $isUrdu
                    ? "Shipment {$shipment['tracking_id']}\nCurrent status: {$shipment['current_status']}\nDestination: {$shipment['destination']}"
                        . ($lastLine !== '' ? "\n{$lastLine}" : '')
                        . "\n\nPura timeline Track Shipment page par dekh sakte hain."
                    : "Shipment {$shipment['tracking_id']}\nCurrent status: {$shipment['current_status']}\nDestination: {$shipment['destination']}"
                        . ($lastLine !== '' ? "\n{$lastLine}" : '')
                        . "\n\nYou can see the full timeline on the Track Shipment page.";
                $actions = [
                    chatbot_action($isUrdu ? 'Pura timeline dekhein' : 'View Full Timeline', url('track.php?tid=' . urlencode((string) $shipment['tracking_id']))),
                ];
            }
        } else {
            $asksWhere = (bool) preg_match('/where (can|do) i (get|find)|kaha(n)? se milega|kahan milegi|kaha milega|tracking id kahan|tracking number kahan/i', $message);
            if ($asksWhere) {
                $reply = $isUrdu
                    ? "Tracking ID booking approve hone ke baad milti hai. Aap usay yahan dekh sakte hain:\n1. Customer Dashboard -> My Bookings\n2. Booking details page (barcode slip ke saath)\n3. Booking confirmation / approval ki notification\n\nID format FC-2026-7K2M9Q jaisa hota hai. Mil jaye to mujhe bhej dein, main status check kar dunga."
                    : "Your Tracking ID is issued after the booking is approved. You can find it in:\n1. Customer Dashboard -> My Bookings\n2. The booking details page (along with the barcode slip)\n3. Your booking confirmation / approval notification\n\nThe ID looks like FC-2026-7K2M9Q. Send it here and I'll check the status.";
                $actions = [
                    chatbot_action($isUrdu ? 'Meri Bookings' : 'My Bookings', url('customer/bookings.php')),
                    chatbot_action($isUrdu ? 'Tracking Page' : 'Track Shipment', url('track.php')),
                ];
            } else {
                $reply = $isUrdu
                    ? "Zaroor. Apna Tracking ID bhejein (format: FC-2026-7K2M9Q) aur main latest status check kar deta hoon. Aap Track Shipment page par bhi ID daal sakte hain."
                    : "Sure. Please send your Tracking ID (format: FC-2026-7K2M9Q) and I'll check the latest status. You can also enter it on the Track Shipment page.";
                $actions = [chatbot_action($isUrdu ? 'Track Shipment' : 'Track Shipment', url('track.php'))];
            }
        }
        break;

    case 'pricing':
        $lines = [];
        foreach ($services as $service) {
            $base = (float) $service['base_price'];
            $perKg = (float) $service['per_kg'];
            $discount = (float) $service['discount_percent'];
            $line = '- ' . $service['service_name'] . ': ' . chatbot_rs($base) . ' base + '
                . chatbot_rs($perKg) . '/kg - ' . $service['estimated_days'];
            if ($discount > 0) {
                $line .= ' - ' . chatbot_pct($discount) . ' off';
            }
            $lines[] = $line;
        }

        $weight = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:kg|kgs|kilo|kilos)\b/i', $message, $weightMatch)) {
            $candidate = (float) $weightMatch[1];
            if ($candidate > 0 && $candidate <= 500) {
                $weight = $candidate;
            }
        }

        $example = '';
        if ($weight !== null && $services) {
            $exampleLines = [];
            foreach ($services as $service) {
                $subtotal = (float) $service['base_price'] + (float) $service['per_kg'] * $weight;
                $final = $subtotal * (1 - ((float) $service['discount_percent'] / 100));
                $exampleLines[] = '- ' . $service['service_name'] . ': about ' . chatbot_rs($final);
            }
            $example = $isUrdu
                ? "\n\n{$weight} kg parcel ka andaza:\n" . implode("\n", $exampleLines)
                : "\n\nEstimate for a {$weight} kg parcel:\n" . implode("\n", $exampleLines);
        }

        if (!$lines) {
            $reply = $isUrdu
                ? 'Filhal online rates available nahi hain. Baraye meharbani hamari team se quote lein.'
                : 'Online rates are not available right now. Please contact our team for a quote.';
        } else {
            $reply = ($isUrdu
                    ? "Yeh hamari current delivery charges hain (charge = base price + per kg rate):\n"
                    : "Here are our current delivery charges (charge = base price + per-kg rate):\n")
                . implode("\n", $lines)
                . $example
                . ($isUrdu
                    ? "\n\nRates poore Pakistan mein same hain. Pricing page par full details hain."
                    : "\n\nRates are the same nationwide. The Pricing page has the full details.");
        }
        $actions = [
            chatbot_action($isUrdu ? 'Pricing Page' : 'View Pricing', url('pricing.php')),
            chatbot_action($isUrdu ? 'Parcel Book karein' : 'Book a Parcel', url('book.php')),
        ];
        break;

    case 'delivery_time':
        $lines = [];
        foreach ($services as $service) {
            $lines[] = '- ' . $service['service_name'] . ': ' . $service['estimated_days'];
        }
        $body = $lines ? implode("\n", $lines) : ($isUrdu ? '- Standard: 3 - 7 days' : '- Standard: 3 - 7 days');
        $reply = ($isUrdu
                ? "Hamari current estimated delivery times:\n"
                : "Our current estimated delivery times:\n")
            . $body
            . ($isUrdu
                ? "\n\nSame-Day Delivery ke liye same city mein dopahar 12 baje se pehle booking karein. Door daraz northern areas mein kabhi kabhi ek din zyada lag sakta hai."
                : "\n\nSame-Day Delivery requires booking before 12 PM within the same city. Remote northern areas may take one extra day.");
        $actions = [
            chatbot_action($isUrdu ? 'Services dekhein' : 'View Services', url('services.php')),
            chatbot_action($isUrdu ? 'Ab book karein' : 'Book Now', url('book.php')),
        ];
        break;

    case 'booking':
        $reply = $isUrdu
            ? "Parcel book karna asaan hai:\n1. Account banayein ya log in karein.\n2. Book a Parcel page kholein.\n3. Sender, receiver, city aur parcel ki details bharein.\n4. Service aur payment method chunein.\n5. Booking submit karein. Approval ke baad Tracking ID mil jayegi.\n\nAgar aap ko booking form mein madad chahiye to bata dein."
            : "Booking a parcel is easy:\n1. Create an account or log in.\n2. Open the Book a Parcel page.\n3. Enter sender, receiver, destination and parcel details.\n4. Choose a service and payment method.\n5. Submit the booking. After approval you receive a Tracking ID.\n\nTell me if you need help with any field in the booking form.";
        $actions = [
            chatbot_action($isUrdu ? 'Parcel Book karein' : 'Book a Parcel', url('book.php')),
            chatbot_action($isUrdu ? 'Account banayein' : 'Create Account', url('register.php')),
        ];
        break;

    case 'coverage':
        $reply = $isUrdu
            ? "Filhal hum poore Pakistan mein delivery karte hain - Karachi, Lahore, Islamabad, Rawalpindi, Faisalabad, Multan, Peshawar, Quetta, Hyderabad, Sialkot, Gilgit aur baqi shehron tak. International delivery abhi available nahi hai; yeh hamare roadmap par hai."
            : "We currently deliver across Pakistan - including Karachi, Lahore, Islamabad, Rawalpindi, Faisalabad, Multan, Peshawar, Quetta, Hyderabad, Sialkot, Gilgit and more. International delivery is not available yet; it is on our roadmap.";
        $actions = [
            chatbot_action($isUrdu ? 'Coverage dekhein' : 'View Services', url('services.php')),
        ];
        break;

    case 'packing':
        $reply = $isUrdu
            ? "Parcel safely pack karne ka tareeqa:\n1. Parcel ke size ka strong box chunein.\n2. Electronics aur fragile items ko bubble wrap ya foam se lapetein.\n3. Box ke andar khali jagah paper ya newspaper se bharein taake item hilay na.\n4. Box ko strong tape se upar, neeche aur sides se band karein.\n5. Receiver ka address aur phone number box par clearly likhein.\n6. Qeemti saman ho to booking se pehle support team ko bata dein.\n\nLiquids aur glass items ko alag leak-proof packing mein rakhein."
            : "How to pack your parcel safely:\n1. Choose a strong box that fits the item.\n2. Wrap electronics and fragile items in bubble wrap or foam.\n3. Fill empty space with paper so the item cannot move.\n4. Seal the box with strong tape on all sides.\n5. Write the receiver's address and phone number clearly on the box.\n6. For valuable items, inform our support team before booking.\n\nPack liquids and glass items separately in leak-proof packaging.";
        $actions = [
            chatbot_action($isUrdu ? 'Parcel Book karein' : 'Book a Parcel', url('book.php')),
            chatbot_action($isUrdu ? 'Support se poochein' : 'Ask Support', url('contact.php')),
        ];
        break;

    case 'items':
        $reply = $isUrdu
            ? "Aap documents, kapre, books, electronics, gifts, cosmetics aur spare parts jaise everyday items bhej sakte hain. Fragile items ko achi tarah pack karein. Weapons, drugs, cash, hazardous chemicals aur illegal items allowed nahi hain. Kisi khaas item ke baare mein confirm karna ho to booking se pehle team se poochein."
            : "You can send everyday items such as documents, clothing, books, electronics, gifts, cosmetics and spare parts. Please pack fragile items carefully. Weapons, drugs, cash, hazardous chemicals and illegal items are not allowed. If you are unsure about a specific item, contact our team before booking.";
        $actions = [
            chatbot_action($isUrdu ? 'Parcel Book karein' : 'Book a Parcel', url('book.php')),
            chatbot_action($isUrdu ? 'Item confirm karein' : 'Ask Support', url('contact.php')),
        ];
        break;

    case 'payment':
        $lines = [];
        foreach ($paymentAccounts as $account) {
            $line = '- ' . $account['account_type'] . ' - ' . $account['account_title']
                . ' - ' . $account['account_number'];
            if (($account['bank_name'] ?? '') !== '' && $account['bank_name'] !== null) {
                $line .= ' (' . $account['bank_name'] . ')';
            }
            $lines[] = $line;
        }
        $reply = ($isUrdu
                ? "Aap Cash on Delivery ya Online Payment (Easypaisa, JazzCash ya bank transfer) mein se choose kar sakte hain."
                : "You can pay Cash on Delivery or Online Payment (Easypaisa, JazzCash or bank transfer).")
            . ($lines ? "\n\n" . implode("\n", $lines) : '')
            . ($isUrdu
                ? "\n\nOnline payment ke liye booking ke waqt Transaction ID zaroor likhein taake team jaldi verify kar sake."
                : "\n\nFor online payments, add the Transaction ID while booking so our team can verify it faster.");
        $actions = [
            chatbot_action($isUrdu ? 'Payment ke saath book karein' : 'Book with Payment', url('book.php')),
            chatbot_action($isUrdu ? 'Support se poochein' : 'Ask Support', url('contact.php')),
        ];
        break;

    case 'refund':
        $reply = $isUrdu
            ? "Refund requests har case mein alag hoti hain aur hamari team review karti hai. Apni Tracking ID, payment method aur payment ki tafseel bhejein taake team jaldi respond kar sake."
            : "Refund requests are reviewed case by case by our team. Please share your Tracking ID, payment method and payment details so the team can respond quickly.";
        $actions = [
            chatbot_action($isUrdu ? 'Support se rabta' : 'Contact Support', url('contact.php')),
            chatbot_action('WhatsApp', $whatsappLink),
        ];
        break;

    case 'pickup':
        $reply = $isUrdu
            ? "Haan, door-to-door pickup hamari service ka hissa hai. Booking ke waqt sender address dein; approved booking ke baad rider parcel collect karta hai. Aap ghar se ya office se parcel bhej sakte hain."
            : "Yes, door-to-door pickup is part of the service. Enter the sender address while booking; after approval a rider collects the parcel. You can send from home or office.";
        $actions = [
            chatbot_action($isUrdu ? 'Pickup ke saath book karein' : 'Book with Pickup', url('book.php')),
        ];
        break;

    case 'weight_cargo':
        $reply = $isUrdu
            ? "Standard, Express aur Same-Day Delivery everyday parcels ke liye hain. Heavy parcels aur bulk shipments ke liye Nationwide Cargo service hai, jismein tracking pickup se doorstep tak hoti hai. Bohat bade load ya pallet ke liye hamari team se custom quote lein."
            : "Standard, Express and Same-Day Delivery are for everyday parcels. For heavy parcels and bulk shipments, Nationwide Cargo includes tracking from pickup to doorstep. For very large loads or pallets, contact our team for a custom quote.";
        $actions = [
            chatbot_action($isUrdu ? 'Cargo service dekhein' : 'View Cargo Service', url('services.php?id=4')),
            chatbot_action($isUrdu ? 'Custom quote' : 'Get a Quote', url('contact.php')),
        ];
        break;

    case 'cancel':
        $reply = $isUrdu
            ? "Booking mein tabdeeli ya cancellation pickup se pehle mumkin hai. Apni Tracking ID aur nayi details hamari team ko bhejein. Agar parcel already transit mein ho to changes network par depend karte hain."
            : "A booking can be changed or cancelled before pickup. Send your Tracking ID and the new details to our team. If the parcel is already in transit, changes depend on the network.";
        $actions = [
            chatbot_action($isUrdu ? 'Support se rabta' : 'Contact Support', url('contact.php')),
            chatbot_action('WhatsApp', $whatsappLink),
        ];
        break;

    case 'complaint':
        $reply = $isUrdu
            ? "Afsoos hai ke aap ko masla hua. Baraye meharbani Tracking ID aur masla bhejein (late delivery, damage, lost parcel waghera). Agar parcel damaged mile to parcel aur packaging ki photos bhi rakh lein. Hamari team is ko urgently investigate karegi."
            : "I'm sorry you had this experience. Please share your Tracking ID and what happened (late delivery, damage, lost parcel, etc.). If the parcel arrived damaged, keep photos of the parcel and packaging. Our team will investigate urgently.";
        $actions = [
            chatbot_action($isUrdu ? 'Complaint darj karein' : 'Contact Support', url('contact.php')),
            chatbot_action('WhatsApp', $whatsappLink),
        ];
        break;

    case 'contact':
        $reply = ($isUrdu ? "Hamari support team se yahan rabta karein:\n" : "You can reach our support team here:\n")
            . "- Phone: {$contactPhone}\n- Email: {$contactEmail}\n- WhatsApp: " . whatsapp_display() . "\n- Address: {$contactAddress}\n- Hours: {$supportHours}";
        $actions = [
            chatbot_action($isUrdu ? 'Contact page' : 'Contact Page', url('contact.php')),
            chatbot_action('WhatsApp', $whatsappLink),
        ];
        break;

    case 'hours':
        $reply = $isUrdu
            ? "Hamari support timing: {$supportHours}. Aap contact page ya WhatsApp par bhi message kar sakte hain."
            : "Our support hours are: {$supportHours}. You can also message us through the contact page or WhatsApp.";
        $actions = [
            chatbot_action($isUrdu ? 'Contact page' : 'Contact Page', url('contact.php')),
        ];
        break;

    case 'account':
        $wantsRegister = (bool) preg_match(
            '/register|registration|sign ?up|account bana|account create|create account|new account|naya account|account kese|account kaise/i',
            $message
        );
        $wantsLogin = (bool) preg_match('/login|log ?in|sign ?in/i', $message);
        $wantsPassword = (bool) preg_match('/password|forgot|reset|bhool/i', $message);

        if ($wantsPassword) {
            $reply = $isUrdu
                ? "Password change karne ke liye:\n1. Login karein.\n2. Dashboard mein Profile page kholein.\n3. 'Change Password' section mein current password aur naya password (8+ characters, letters + numbers) likhein.\n4. Save karein.\n\nAgar password bhool gaye hain to website par self-service reset nahi hai. Apna registered email support team ko bhejein, wo verify karke access recover karne mein madad karegi."
                : "To change your password:\n1. Log in.\n2. Open the Profile page in your dashboard.\n3. In the Change Password section, enter your current password and a new password (8+ characters with letters and numbers).\n4. Save the changes.\n\nIf you forgot your password, there is no self-service reset on the website. Send your registered email to our support team and they will verify you and help recover access.";
            $actions = [
                chatbot_action($isUrdu ? 'Profile' : 'My Profile', url('customer/profile.php')),
                chatbot_action($isUrdu ? 'Support se rabta' : 'Contact Support', url('contact.php')),
            ];
        } elseif ($wantsRegister && !$wantsLogin) {
            $reply = $isUrdu
                ? "Account banane ka tareeqa:\n1. Website ke top-right 'Register' button par click karein.\n2. Full Name, Email, Phone, City, Address aur Password (8+ characters) bharein.\n3. Confirm Password dobara likhein aur Create Account dabayein.\n4. Account ban jane ke baad Email aur Password se login karein.\n\nAap 'Continue with Google' se bhi account bana sakte hain."
                : "How to create an account:\n1. Click the Register button at the top-right of the website.\n2. Enter your Full Name, Email, Phone, City, Address and a Password (8+ characters).\n3. Re-enter the Confirm Password and click Create Account.\n4. After registration, log in with your Email and Password.\n\nYou can also use 'Continue with Google' to create an account.";
            $actions = [
                chatbot_action($isUrdu ? 'Account banayein' : 'Register', url('register.php')),
                chatbot_action($isUrdu ? 'Login' : 'Login', url('login.php')),
            ];
        } elseif ($wantsLogin) {
            $reply = $isUrdu
                ? "Login karne ka tareeqa:\n1. Website ke top-right 'Login' button par click karein.\n2. Apna registered Email aur Password likhein.\n3. Login dabayein - aap customer dashboard par pahunch jayenge.\n\nGoogle account use kiya hai to 'Continue with Google' par click karein. Password bhool gaye hain to support team se rabta karein."
                : "How to log in:\n1. Click the Login button at the top-right of the website.\n2. Enter your registered Email and Password.\n3. Click Login - you will reach your customer dashboard.\n\nIf you registered with Google, use 'Continue with Google'. If you forgot your password, contact our support team.";
            $actions = [
                chatbot_action($isUrdu ? 'Login' : 'Login', url('login.php')),
                chatbot_action($isUrdu ? 'Account banayein' : 'Register', url('register.php')),
            ];
        } else {
            $reply = $isUrdu
                ? "FASTED COURIER par account free hai. Account se aap parcel book kar sakte hain, apni bookings manage kar sakte hain, barcode slip print kar sakte hain aur payments dekh sakte hain.\n\n- Naya account: top-right 'Register' button.\n- Pehle se account hai: top-right 'Login' button.\n- Tracking ke liye account zaroori nahi - Tracking ID se Track page par status dekh sakte hain."
                : "A FASTED COURIER account is free. With an account you can book parcels, manage your bookings, print barcode slips and view payments.\n\n- New here? Use the Register button at the top-right.\n- Already registered? Use the Login button at the top-right.\n- Tracking does not require an account - enter your Tracking ID on the Track page.";
            $actions = [
                chatbot_action($isUrdu ? 'Account banayein' : 'Register', url('register.php')),
                chatbot_action($isUrdu ? 'Login' : 'Login', url('login.php')),
                chatbot_action($isUrdu ? 'Tracking' : 'Track a Parcel', url('track.php')),
            ];
        }
        break;

    case 'website':
        $reply = $isUrdu
            ? "FASTED COURIER website par aap ye sab kar sakte hain:\n- Naya account banana aur login karna\n- Parcel book karna\n- Tracking ID se shipment track karna\n- Apni bookings, barcode slip aur payments dekhna\n- Services, rates aur coverage dekhna\n- Contact form ya WhatsApp se support lena\n\nBata dein kis ka step-by-step tareeqa chahiye: registration, login, booking, tracking ya payment."
            : "On the FASTED COURIER website you can:\n- Create an account and log in\n- Book a parcel\n- Track a shipment with your Tracking ID\n- View your bookings, barcode slip and payments\n- Browse services, rates and coverage\n- Get support through the contact form or WhatsApp\n\nTell me which step-by-step guide you need: registration, login, booking, tracking or payment.";
        $actions = [
            chatbot_action($isUrdu ? 'Account banayein' : 'Register', url('register.php')),
            chatbot_action($isUrdu ? 'Parcel book karein' : 'Book a Parcel', url('book.php')),
            chatbot_action($isUrdu ? 'Tracking' : 'Track a Parcel', url('track.php')),
            chatbot_action($isUrdu ? 'Support' : 'Contact Support', url('contact.php')),
        ];
        break;

    case 'about':
        if ($isUrdu) {
            $aboutIntro = 'FASTED COURIER ek modern courier aur logistics company hai jo poore Pakistan mein parcels safely aur waqt par deliver karti hai. Chhotay documents se le kar full cargo tak, hamari service reliable aur trackable hai.';
        } else {
            $aboutIntro = trim(setting('about_intro'));
            if ($aboutIntro === '') {
                $aboutIntro = 'FASTED COURIER is a modern courier and logistics company delivering parcels safely and on time across Pakistan.';
            }
            $aboutIntro = mb_strlen($aboutIntro) > 420
                ? mb_substr($aboutIntro, 0, 420) . '...'
                : $aboutIntro;
        }
        $founder = '';
        foreach (array_filter(array_map('trim', explode('|', setting('about_team')))) as $teamLine) {
            if (preg_match('/founder|ceo/i', $teamLine)) {
                $founder = trim(explode(' - ', $teamLine)[0]);
                break;
            }
        }
        $reply = $aboutIntro
            . ($founder !== '' ? "\n\nFounder & CEO: " . $founder : '')
            . ($isUrdu
                ? "\n\nHamari services: Standard Delivery, Express Delivery, Same-Day Delivery aur Nationwide Cargo. Poore Pakistan mein door-to-door delivery hoti hai."
                : "\n\nOur services: Standard Delivery, Express Delivery, Same-Day Delivery and Nationwide Cargo, with door-to-door delivery across Pakistan.");
        $actions = [
            chatbot_action($isUrdu ? 'About Us' : 'About Us', url('about.php')),
            chatbot_action($isUrdu ? 'Services' : 'Services', url('services.php')),
        ];
        break;

    case 'barcode':
        $reply = $isUrdu
            ? "Booking approve hone ke baad Tracking ID aur barcode slip aap ke customer dashboard mein 'My Bookings' ke andar milti hai. Wahan se booking kholein aur slip print karein."
            : "After your booking is approved, the Tracking ID and barcode slip appear in your customer dashboard under My Bookings. Open the booking and print the slip.";
        $actions = [
            chatbot_action($isUrdu ? 'Meri Bookings' : 'My Bookings', url('customer/bookings.php')),
        ];
        break;

    case 'greeting':
        $reply = $isUrdu
            ? "Assalam-o-Alaikum! Main {$botName} hoon, FASTED COURIER ka support assistant. Registration, login, booking, tracking, delivery charges, payment ya complaint - kis cheez mein madad chahiye?"
            : "Hello! I'm {$botName}, FASTED COURIER's support assistant. I can help with registration, login, booking, tracking, delivery charges, payments or complaints. What do you need help with?";
        $actions = [
            chatbot_action($isUrdu ? 'Tracking' : 'Track a Parcel', url('track.php')),
            chatbot_action($isUrdu ? 'Rates' : 'Delivery Rates', url('pricing.php')),
            chatbot_action($isUrdu ? 'Booking' : 'Book a Parcel', url('book.php')),
        ];
        break;

    case 'thanks':
        $reply = $isUrdu
            ? "Shukriya! Koi aur courier-related sawal ho to main yahin hoon."
            : "You're welcome! If you have another courier-related question, I'm here to help.";
        break;

    case 'bye':
        $reply = $isUrdu
            ? "Allah Hafiz! FASTED COURIER ka safar aap ke saath hamesha asaan rahe."
            : "Goodbye! Thanks for choosing FASTED COURIER.";
        break;

    case 'identity':
        $reply = $isUrdu
            ? "Main {$botName} hoon, FASTED COURIER ka virtual customer support assistant. Main courier aur is website se related sawalon ka jawab deta hoon - registration, login, booking, tracking, rates, delivery time, payment aur complaints. Main human agent nahi hoon, lekin aap ko hamari team se connect kar sakta hoon."
            : "I'm {$botName}, FASTED COURIER's virtual customer support assistant. I answer courier and website questions - registration, login, booking, tracking, rates, delivery times, payments and complaints. I'm not a human agent, but I can connect you to our team.";
        $actions = [
            chatbot_action($isUrdu ? 'Support team' : 'Contact Support', url('contact.php')),
            chatbot_action('WhatsApp', $whatsappLink),
        ];
        break;

    default:
        $intent = 'fallback';
        $courierRelated = (bool) preg_match(
            '/courier|fasted|parcel|shipment|delivery|deliver|booking|track|price|rate|charge|'
            . 'payment|account|login|register|website|service|cargo|pickup|refund|complaint|'
            . 'order|insurance|franchise|invoice|receipt|return|bulk|corporate/i',
            $message
        );
        if ($courierRelated) {
            $reply = $isUrdu
                ? "Yeh sawal thora specific hai. Main is ka exact jawab confirm nahi kar sakta, lekin hamari support team aap ki poori madad kar sakti hai. Apna sawal contact page ya WhatsApp par bhej dein - team jaldi jawab degi."
                : "This question is quite specific. I cannot confirm the exact answer, but our support team can help you fully. Please send your question through the contact page or WhatsApp - the team will reply soon.";
            $actions = [
                chatbot_action($isUrdu ? 'Contact page' : 'Contact Support', url('contact.php')),
                chatbot_action('WhatsApp', $whatsappLink),
            ];
        } else {
            $reply = $isUrdu
                ? "Main {$botName} hoon, FASTED COURIER ka support assistant. Main courier aur is website se related sawalon mein madad karta hoon - registration, login, booking, tracking, rates, delivery time, coverage, payment, packing aur complaints. Baraye meharbani courier ya website se related sawal poochein."
                : "I'm {$botName}, FASTED COURIER's support assistant. I help with courier and website questions - registration, login, booking, tracking, rates, delivery times, coverage, payments, packing and complaints. Please ask a courier or website-related question.";
            $actions = [
                chatbot_action($isUrdu ? 'Tracking' : 'Track a Parcel', url('track.php')),
                chatbot_action($isUrdu ? 'Rates' : 'Delivery Rates', url('pricing.php')),
                chatbot_action($isUrdu ? 'Booking' : 'Book a Parcel', url('book.php')),
                chatbot_action($isUrdu ? 'Support' : 'Contact Support', url('contact.php')),
            ];
        }
        break;
}

$matched = $intent !== 'fallback';
$aiUsed = false;

// Optional ChatGPT answer. Live tracking with a Tracking ID always keeps the
// exact database answer, and off-topic questions are still refused locally.
if (chatbot_ai_enabled()) {
    $aiEligible = $courierRelated
        && !in_array($intent, ['greeting', 'thanks', 'bye', 'identity'], true)
        && !($intent === 'tracking' && $trackingId !== null);

    if ($aiEligible) {
        $ai = chatbot_ai_request(chatbot_ai_messages($pdo, $message, $isUrdu, $reply, $history), 380, 16);
        if ($ai['ok'] && $ai['reply'] !== '') {
            $reply = $ai['reply'];
            $aiUsed = true;
        }
    }
}

// Keep a copy of every exchange for the admin panel.
try {
    $pdo->prepare(
        'INSERT INTO ChatbotLogs
            (session_id, user_message, bot_reply, intent, matched, is_ai, page_url, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $sessionId,
        mb_substr($message, 0, 1000),
        $reply,
        $intent,
        $matched ? 1 : 0,
        $aiUsed ? 1 : 0,
        $pageUrl !== '' ? $pageUrl : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
} catch (Throwable $error) {
    // A logging failure must never break the customer's chat.
}

chatbot_json([
    'ok' => true,
    'reply' => $reply,
    'intent' => $intent,
    'matched' => $matched,
    'ai' => $aiUsed,
    'actions' => $actions,
]);

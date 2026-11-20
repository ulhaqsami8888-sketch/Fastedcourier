<?php
/**
 * XML sitemap generated for the current host - submit this URL
 * (e.g. https://yourdomain.com/sitemap.php) in Google Search Console.
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/xml; charset=utf-8');

$pages = [
    ['loc' => 'index.php',   'priority' => '1.0', 'freq' => 'weekly'],
    ['loc' => 'services.php', 'priority' => '0.9', 'freq' => 'weekly'],
    ['loc' => 'pricing.php',  'priority' => '0.9', 'freq' => 'weekly'],
    ['loc' => 'track.php',    'priority' => '0.9', 'freq' => 'weekly'],
    ['loc' => 'book.php',     'priority' => '0.8', 'freq' => 'monthly'],
    ['loc' => 'about.php',    'priority' => '0.7', 'freq' => 'monthly'],
    ['loc' => 'contact.php',  'priority' => '0.7', 'freq' => 'monthly'],
];

foreach ($pdo->query("SELECT service_id FROM Services WHERE status = 'Active' ORDER BY service_id") as $row) {
    $pages[] = [
        'loc'      => 'services.php?id=' . (int) $row['service_id'],
        'priority' => '0.6',
        'freq'     => 'monthly',
    ];
}

$lastmod = date('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $page) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars(absolute_url($page['loc']), ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>{$page['freq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';

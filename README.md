# FASTED COURIER — Courier Management & Tracking System

A complete courier management and parcel tracking web application built with **PHP 8 + MySQL (MariaDB)**, **Bootstrap 5**, **HTML5**, **CSS3** and **JavaScript**. The project covers the public website, a customer portal and a protected admin panel.

---

## Features

### Public website
- Hero slider, tracking widget, service highlights and company statistics
- About Us (introduction, mission, vision, team, achievements, gallery with zoom lightbox)
- Our Services and service-wise Pricing
- Public shipment tracking by Tracking ID (phone numbers masked for privacy)
- Brief loading animation featuring the site logo
- Floating WhatsApp support button on every public page — shows the support number with one-click copy and a direct chat link; the number is editable from **Appearance & Branding**
- Subtle site-wide motion: floating logo, hero pan and entrance, scroll-reveal cards, hover lifts and a pulsing WhatsApp button (honours reduced-motion settings)
- Contact form that stores customer queries for the admin — messages are linked to the customer's profile (photo and account link in the inbox) and replies are visible to the customer on the **My Messages** page
- Customer registration and login, plus **Continue with Google** (Google OAuth 2.0) so customers can sign in with their own Gmail account

### Customer portal
- Dashboard with booking, shipment and payment summaries
- Book a Parcel with live delivery-charge estimate
- Booking history, status filters and search
- Booking detail page with progress bar and full shipment history
- Profile with photo upload, plus password change
- Payment records (Cash on Delivery, Cash Payment, simulated Online Payment)
- My Messages — every Contact Us query with its status and the support team's reply

### Admin panel
1. Dashboard — totals, recent bookings, courier activity, pending actions, revenue
2. Manage Parcel Bookings — approve (auto Tracking ID), reject or delete requests (with confirmation)
3. Shipment Management — status updates, search, complete history and confirmed delete (returns the booking to Pending)
4. Manage Customers — search, view details, block/unblock accounts, and delete a customer with their related bookings
5. Manage Delivery Services — add, edit, activate/deactivate, delete
6. Manage Pricing — service-wise rates with live example estimates
7. Manage About Us — introduction, mission, vision, team, achievements
8. Manage Contact Us — inbox, replies, contact details and social links
9. Manage Website Gallery — image upload (JPG/PNG/WEBP), rename, delete
10. Appearance & Branding — change the site logo, favicon, ten homepage slider images or videos, hero text, the WhatsApp support number and the Google sign-in credentials from the panel
11. Manage Reports — daily/monthly bookings, delivered, pending, revenue, CSV export and print
12. Payments — mark Paid/Refunded, outstanding totals
13. Printable Booking Slip — A4 consignment note with Code 39 barcode, shipper/consignee details, charges and signatures
14. Administrator Accounts — create, edit and delete admin logins from inside the panel, plus a protected sign-up page on the admin login screen that requires an administrator access code
15. Panel Settings — save the administrator access code, update your own name, email and password, and replace the built-in themed wallpaper behind the admin panel

---

## Requirements

| Component | Version |
|-----------|---------|
| XAMPP | 8.x (PHP 8.0+ and MariaDB 10.4+) |
| PHP extensions | PDO MySQL, mbstring, fileinfo, session, cURL (needed only for Google sign-in) |
| Browser | Chrome, Edge or Firefox (modern) |

No Composer, Node.js or internet connection is required at runtime — Bootstrap, Bootstrap Icons and fonts are bundled locally. An internet connection is only used when a customer chooses **Continue with Google**.

---

## Installation

1. **Start XAMPP** — open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. **Copy the project** into `C:\xampp\htdocs\` so the folder is:
   `C:\xampp\htdocs\fasted-courier`
3. **Create the database**
   - Open <http://localhost/phpmyadmin>
   - Choose *Import* → select `database/fasted_courier.sql` → *Go*
   - The script creates the `fasted_courier` database, all 11 tables and demo data.
4. **Check the configuration** in `config/config.php`
   - `BASE_URL` is `/fasted-courier`. Change it if your folder name is different.
   - Database credentials default to XAMPP (`root` / empty password).
5. **Open the website**: <http://localhost/fasted-courier/>
6. **Admin panel**: <http://localhost/fasted-courier/admin/login.php> (not linked publicly; login credentials are provided separately)
   - New administrators can be created from **Administration → Administrator Accounts** after login, or from the **Create new account** link on the admin login page.
   - The sign-up form requires the access code saved under **Administration → Panel Settings** (it starts as `ADMIN_SIGNUP_CODE` from `config/config.php`). Change it from the panel before deploying.

> Demo seed accounts are created by the SQL file. Administrator and customer credentials are shared privately for evaluation.

### Google sign-in (optional)

Customers can sign in with their own Gmail account through **Continue with Google**. The button stays available but shows a friendly message until the site owner saves Google OAuth credentials. One-time setup:

1. Open <https://console.cloud.google.com/> and create (or select) a project.
2. Go to **APIs & Services → OAuth consent screen**, choose **External**, fill in the app name and support/developer emails, then save.
3. Go to **APIs & Services → Credentials → Create credentials → OAuth client ID**.
4. Choose **Web application**, give it a name and, under **Authorized redirect URIs**, paste exactly the value shown in the panel:
   `http://localhost/fasted-courier/google-callback.php`
5. Copy the **Client ID** and **Client secret**.
6. In the admin panel open **Appearance & Branding → Google Sign-In for Customers**, paste both values and click **Save Branding**. The card switches to **Active**.
7. While the consent screen is in **Testing** mode, add the Gmail addresses you want to test under **Test users**.

On first sign-in the system creates a customer account from the verified Google email; the new customer can add a phone number and address from **My Profile**.

### Folder permissions (Windows)
The web server must be able to write to
`assets/uploads/gallery/` (gallery images), `assets/uploads/site/` (logo, favicon and slider images/videos) and `assets/uploads/customers/` (customer profile photos).
On XAMPP this works by default.

---

## Project structure

```
fasted-courier/
├── admin/                  # Protected admin panel (no public links)
│   ├── index.php           # Dashboard
│   ├── bookings.php        # Approve / reject bookings
│   ├── shipments.php       # Shipment list
│   ├── shipment-view.php   # Status update + history
│   ├── customers.php       # Customer management
│   ├── payments.php        # Payment records
│   ├── services.php        # Delivery services
│   ├── pricing.php         # Service pricing
│   ├── about.php           # About Us content
│   ├── contact.php         # Messages + contact settings
│   ├── gallery.php         # Gallery uploads
│   ├── appearance.php      # Logo, favicon, slider (images/videos), hero text, WhatsApp and Google keys
│   ├── reports.php         # Business reports
│   ├── admins.php          # Administrator accounts
│   ├── slip.php            # Printable A4 booking slip
│   ├── login.php / logout.php
│   ├── register.php        # Admin sign-up (access-code protected)
├── assets/
│   ├── css/                # style.css (public), admin.css (panel)
│   ├── js/                 # main.js, admin.js
│   ├── img/                # Brand and hero images
│   ├── uploads/gallery/    # Admin-uploaded gallery images
│   ├── uploads/site/       # Admin-uploaded logo, favicon and slides
│   ├── uploads/customers/  # Customer profile photos
│   └── vendor/             # Bootstrap + Bootstrap Icons (local)
├── config/
│   ├── config.php          # Constants, session hardening
│   └── database.php        # PDO connection
├── customer/               # Customer portal (dashboard, bookings, profile, payments, messages)
├── database/
│   └── fasted_courier.sql  # Schema + demo data
├── docs/                   # Synopsis, documentation, ER diagram, reports
│   └── screenshots/        # 32 screenshots of the running system
├── includes/
│   ├── functions.php       # Helpers, auth guards, validation, CSRF
│   ├── header.php / footer.php
│   ├── admin_header.php / admin_footer.php
├── index.php, about.php, services.php, pricing.php
├── track.php, contact.php, book.php
├── login.php, register.php, logout.php
├── google-login.php        # Starts Google OAuth 2.0 sign-in
├── google-callback.php     # Verifies Google account, links or creates the customer
└── README.md
```

---

## Database

The schema uses 11 InnoDB tables with foreign keys:

`Users`, `Admins`, `Services`, `Pricing`, `Bookings`, `Shipments`, `ShipmentHistory`, `Payments`, `ContactMessages`, `SiteSettings`, `Gallery`.

See `docs/03-ER-Diagram.md` for the complete entity-relationship diagram.

### Business rules
- Delivery charge = **base price + (per-kg rate × weight)**, recalculated server-side.
- Tracking IDs use the format **FC-YYYY-XXXXXX** and are generated only when an admin approves a booking.
- Shipment statuses: Approved → Picked Up → In Transit → Delivered (synchronised with the booking).
- Rejected bookings keep an admin note and never receive a tracking ID.
- Online payments are **simulated**; no card or bank data is stored.

---

## Security

- Passwords hashed with `password_hash()` (bcrypt) and verified with `password_verify()`.
- Separate session guards for customers (`$_SESSION['user_id']`) and admins (`$_SESSION['admin_id']`).
- All SQL uses PDO prepared statements; exceptions enabled; emulated prepares disabled.
- CSRF tokens on every state-changing form.
- Output escaped with a central `e()` helper to prevent XSS.
- HTTP-only, SameSite session cookies; session ID regenerated at login.
- Public tracking masks phone numbers and hides payment method/reference.
- `database/.htaccess` blocks direct download of the SQL dump.
- File uploads limited to JPG/PNG/WEBP, max 2 MB, verified with `getimagesize()`.
- Google sign-in uses the OAuth 2.0 Authorization Code flow with a single-use `state` value checked against the session, and accepts only a verified Google email whose token audience matches the stored Client ID.
- The Google Client Secret is stored server-side and never printed back to the browser; admin pages are session-guarded.

---

## Screenshots

Screenshots of every public, customer and admin screen are in
[`docs/screenshots/`](docs/screenshots/).

| # | Screen |
|---|--------|
| 01 | Home page |
| 02 | About Us |
| 03 | Services |
| 04 | Pricing |
| 05 | Public tracking result |
| 06 | Contact |
| 07–08 | Login / Register |
| 09 | Admin login |
| 10–21 | Admin panel (dashboard, bookings, shipments, customers, payments, services, pricing, about, contact, gallery, reports) |
| 22–27 | Customer portal (dashboard, book parcel, bookings, booking details, payments, profile) |
| 28 | Admin — administrator accounts |
| 29–31 | Admin sign-up, Appearance & Branding, printable booking slip |
| 32 | Customer — My Messages (query and admin reply) |

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Database connection error | Start MySQL in XAMPP and confirm the database name in `config/database.php`. |
| CSS/images missing | Keep the folder name `fasted-courier` or update `BASE_URL` in `config/config.php`. |
| Gallery upload fails | Ensure `assets/uploads/gallery` exists and is writable. |
| Port 80 busy | Change Apache to port 8080 and open `http://localhost:8080/fasted-courier/`. |
| Blank page | Enable `display_errors` in `php.ini` temporarily and check the Apache error log. |
| Google sign-in fails | The redirect URI in Google Cloud must match `http://localhost/fasted-courier/google-callback.php` exactly. Also confirm the cURL extension is enabled and the Gmail address is listed as a **Test user** while the consent screen is in Testing mode. |

---

© 2026 FASTED COURIER — Academic project. Fast Delivery. Nationwide.

# MedDrop — Online Pharmacy & Medicine Delivery System

![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8%2B-4479A1?logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-CDN-38B2AC?logo=tailwindcss&logoColor=white)
![Vanilla JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?logo=javascript&logoColor=222)

MedDrop is a responsive online pharmacy demo built with PHP 8, MySQL, HTML, Tailwind CSS, and vanilla JavaScript. Customers can browse a realistic medicine catalogue, submit prescription and Senior Citizen / PWD documents, and track deliveries. Pharmacists review regulated orders and manage batches. Riders pick up approved orders and update delivery progress.

## Features

- **Role based workspaces:** customer, pharmacist, and rider accounts with server side access checks.
- **Medicine catalogue:** OTC and prescription only labels, category and type filters, search, dosage, prices, and availability.
- **FEFO stock:** medicines keep dated batches; checkout deducts unexpired stock from the earliest expiry batch first.
- **Inventory alerts:** low stock is below 15 units; near expiry is within 60 days.
- **Prescription checks:** an RX basket cannot be submitted without a JPG, PNG, or PDF prescription (5 MB maximum).
- **Senior Citizen / PWD requests:** upload an ID to request 20% off the subtotal while pharmacist review is pending.
- **Order tracking:** visual status path, delivery details, payment information, and pharmacist rejection reasons.
- **Pharmacist queue:** private inline image/PDF previews, approve/reject actions, and a required rejection explanation.
- **Inventory management:** create or update medicine details, add batches, expiry dates, and stock; archive catalogue entries.
- **Rider delivery board:** customer contact and address, map link, payment method, order summary, and delivery status actions.
- **Map based delivery tracking:** Leaflet.js with OpenStreetMap tiles, checkout map pinning and browser geolocation, customer live rider polling, rider GPS sharing, and a simulated route for demos. No map API key is needed.
- **OSRM road routing:** The customer and rider maps draw public OSRM driving routes with a straight-line fallback and distance and ETA estimates.
- **Delivery operations:** pharmacists have approval and searchable verification history; riders claim unassigned orders from a first-come pool and can review completed deliveries.
- **Profile photos:** customers, pharmacists, and riders can upload JPG, PNG, or WebP avatars, with demo SVG portraits and an initials fallback.
- **Security basics:** PDO prepared statements, password hashing, CSRF checks, session ID rotation, server-side file MIME checks, size limits, and access-controlled document delivery.

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8+ with modular MVC-style app, PDO, and MySQL |
| Database | MySQL 8+ / MariaDB 10.5+ |
| Frontend | HTML5, Tailwind CSS CDN, custom responsive CSS, vanilla JavaScript |
| Local runtime | XAMPP, WAMP, MAMP, or PHP's built-in web server |

## Local installation (XAMPP)

1. Install XAMPP with PHP 8+ and MySQL/MariaDB. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Copy this project folder into `C:\xampp\htdocs\meddrop` (or your web root).
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Select **Import**, choose the project's `database.sql`, then choose **Import**. The script creates the `meddrop` database, schema, sample accounts, medicines, and batch stock.
5. Check the database settings in `config/config.php`. The defaults are host `127.0.0.1`, database `meddrop`, user `root`, and an empty password, matching a common local XAMPP install. Change them if your MySQL credentials differ.
6. Ensure PHP extensions `pdo_mysql`, `fileinfo`, and `mbstring` are enabled. Prescriptions are stored in `storage/uploads`; avatar uploads go into `uploads/avatars`. Both directories have Apache rules to prevent script execution.
7. Visit `http://localhost/meddrop/` and sign in with a demo account below.

Tailwind CSS and the display fonts load from CDNs, so the UI needs internet access in a standard browser. PHP application routes do not require URL rewriting.

Leaflet.js, OpenStreetMap tiles, and public OSRM road routing load over the internet and require no API keys. Browser GPS sharing works on `localhost` or a secure HTTPS deployment and requires rider location permission.

### Updating an existing installation

If you already imported an earlier MedDrop schema, run [`database/migrations/2026_11_22_add_order_tracking_coordinates.sql`](database/migrations/2026_11_22_add_order_tracking_coordinates.sql) only if the coordinate columns are not already present, then run [`database/migrations/2026_11_22_profile_pictures_and_delivery_history.sql`](database/migrations/2026_11_22_profile_pictures_and_delivery_history.sql). The `reviewed_at` column already exists in the earlier schema. Fresh `database.sql` imports include all fields. The seed includes an in-transit order and an unclaimed order for the demo accounts.

## Demo accounts

All three seeded passwords are `password123`.

| Role | Email | Password |
| --- | --- | --- |
| Customer | `customer@meddrop.test` | `password123` |
| Pharmacist | `pharmacist@meddrop.test` | `password123` |
| Rider | `rider@meddrop.test` | `password123` |

The customer account can place OTC orders without a review step or submit an RX order for pharmacist review. Seeded in-transit and unclaimed sample orders let you preview live tracking and rider job selection immediately. The pharmacist account manages review requests and the stockroom. The rider account can claim available jobs, share GPS or simulate a route, and review completed deliveries.

## Screenshots

Add portfolio screenshots to `docs/screenshots/` and replace these placeholders when captured:

| View | Screenshot |
| --- | --- |
| Customer catalogue | ![Customer catalogue](docs/screenshots/customer-catalogue.png) |
| Checkout and tracking | ![Checkout and order tracking](docs/screenshots/checkout-tracking.png) |
| Pharmacist workspace | ![Pharmacist review and inventory](docs/screenshots/pharmacist-workspace.png) |
| Rider delivery panel | ![Rider delivery panel](docs/screenshots/rider-delivery.png) |

## Project structure

```text
meddrop/
├── app/
│   ├── actions.php       # Validated POST actions and business logic
│   └── bootstrap.php     # Sessions, PDO, CSRF, auth, uploads, helpers
├── assets/
│   ├── app.js            # Responsive navigation, checkout, document previews
│   └── style.css         # Tailwind-supported MedDrop visual system
├── config/config.php     # Local database and upload settings
├── api/                  # Customer tracking poll and rider GPS updates
├── database/migrations/  # Existing-database coordinate migration
├── uploads/avatars/      # Demo SVG portraits and uploaded avatars
├── storage/uploads/      # Private prescription and ID uploads
├── views/                # Auth, customer, pharmacist, rider, and layout views
├── database.sql          # Schema plus demo accounts, medicine and batch seed
├── document.php          # Authenticated document streaming
└── index.php             # Front controller and read routes
```

## Operational notes

- Prescription and ID files are stored with random server generated names. Uploads accept JPG, PNG, or PDF only, up to 5 MB. Apache denies direct access to the upload directory; document reads verify the signed-in role and order ownership.
- A prescription review or discount request begins in **Pending Pharmacist Review**. Orders without either request go directly to **Approved & Preparing**.
- Rejecting an order records the pharmacist's reason and returns its units to inventory.
- This is a portfolio application. Before handling real prescriptions or payments, add deployment-specific privacy, compliance, payment provider, rate limiting, backup, and operational controls.
## Screenshots

### Customer Medicine Catalog (OTC & Rx Filtering)
![Customer Medicine Catalog](docs/screenshots/customer-catalog.png)

### Cart & Interactive Delivery Pin Map
![Customer Checkout](docs/screenshots/customer-checkout.png)

### Real-Time Rider Tracking (OSRM Road Routing & Live ETA)
![Live Rider Tracking](docs/screenshots/live-tracking.png)

### Pharmacist Verification Queue & Order History
![Pharmacist Dashboard](docs/screenshots/pharmacist-dashboard.png)

### Rider Delivery Pool & Active Route Simulation

![Rider Dashboard](docs/screenshots/rider-dashboard.png)
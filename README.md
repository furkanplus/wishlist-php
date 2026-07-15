# 🎁 Wishlist PHP — Self-Hosted Personal Wishlist

A lightweight, self-hosted, mobile-first wishlist application written in native PHP, modern CSS, and vanilla JavaScript. Avoid duplicate gifts on birthdays, holidays, or special events with a secure verification system, dynamic metadata scraping, and built-in multi-language management.

---

## ✨ Key Features

- **📱 Responsive Mobile-First Design:** A sleek, modern card grid powered by CSS Variables, Grid, and Flexbox. Looks and feels premium on any device (iOS, Android, and Desktop).
- **🔒 Anti-Duplicate Gift Verification:** When a friend marks an item as bought, they must provide verification proof (Tracking Link or Order ID).
- **💬 Buyer Messages:** Friends can leave a personal note when marking an item as bought, with options to make the message public on the wishlist or keep it private for the owner only.
- **📦 Archiving System:** Hide items from the public wishlist while keeping them visible in the admin panel for future reference.
- **✨ Metadata Auto-Scraper:** Inputting a product URL automatically scrapes the page title and product image via cURL.
- **🗺️ Interactive Language Engine:** Dynamic translation dashboard to manage UI strings stored in `lang/*.php` without touching code.
- **🎛️ Dynamic Admin Settings:** Drag-and-drop reordering, owner announcements, and shipping addresses with privacy-focused expiration dates.
- **🛡️ Security Hardened:** Built-in CSRF protection, security headers (CSP, HSTS), rate limiting, session hardening, and SSRF protection for metadata scraping and webhooks.
- **🚀 Web Setup Wizard:** Interactive `install.php` script to configure the database, seed settings, and register the admin user.

---

## 🛠️ Technology Stack

- **Backend:** Native PHP 7.4+ or 8.x
  - Requires: `PDO` (with MySQL driver), `cURL`, and `DOM`/`libxml` extensions.
- **Frontend:** Vanilla HTML5, CSS3 (Modern custom properties), and Vanilla JavaScript (AJAX Fetch API).
- **Database:** MySQL / MariaDB

---

## 📂 Project Structure

```text
wishlist-php/
├── api/
│   ├── fetch-metadata.php  # Metadata scraping with SSRF protection
│   ├── mark-bought.php     # Purchase API with rate limiting and atomic updates
│   └── update-order.php    # Admin API for drag-and-drop sorting with row locking
├── assets/
│   ├── css/
│   │   └── style.css       # Core stylesheet (dark mode, grids, UI components)
│   └── js/
│       ├── admin.js        # Admin interaction and dashboard scripts
│       └── app.js          # Public scripts for purchase workflows
├── lang/
│   └── tr.php              # Translation matrices
├── admin.php               # Secure Admin Dashboard
├── config.template.php     # Template for auto-generating config.php
├── index.php               # Main public wishlist catalog
├── install.php             # Step-by-step Web Installation Wizard
├── login.php / logout.php  # Authorization gateway with rate limiting
└── schema.sql              # Database structure template
```

---

## 🚀 Installation & Setup

### Prerequisites
- A web server running Apache/Nginx with PHP 7.4+.
- A MySQL or MariaDB database server.
- Write permissions enabled on the project folder (for generating `config.php` and `install.lock`).

### Step 1: Upload Files
Upload all files in this repository to your server's public directory.

### Step 2: Run the Web Installer
1. Navigate to your website: `http://yourdomain.com/wishlist/install.php`
2. Enter your MySQL database credentials.
3. Create your admin login credentials (minimum 12 characters required).
4. Click **Install & Run Setup**.

The installer will execute the schema, generate `config.php`, and create an `install.lock` file to secure the installation.

> [!IMPORTANT]
> `config.php` is untracked by git for security. Ensure it is backed up or managed via your deployment process.

---

## 🔒 Security Features

- **CSRF Protection:** All state-changing actions are protected by secure tokens.
- **SSRF Protection:** Metadata scraping and webhooks use robust IP and URL validation.
- **Rate Limiting:** Protects against login brute-force and API abuse.
- **Security Headers:** Implements Content Security Policy (CSP), HSTS, and X-Frame-Options.
- **Session Hardening:** Uses SameSite=Strict, Secure, and HttpOnly cookie attributes.

---

## 🌐 Localization (Translations)

Manage translations directly from the **Admin Dashboard**:
1. Navigate to the **Translations** tab.
2. Add a new ISO language code (e.g., `de`, `fr`) or edit existing ones.
3. Translate UI labels on-the-fly. Fallback to English is automatic for untranslated strings.

---

## 🚀 Deployment

Use the included `deploy.sh` script for easy deployment:
1. Configure your FTP credentials in a `.env` file.
2. Run `./deploy.sh` to sync your local files with the remote server.

---

## License

This project is licensed under the **GNU AGPLv3 with the Commons Clause**.

### What this means:
* **Source-Available & Open:** Anyone can view, copy, fork, and edit the code.
* **Viral Open-Source:** Any changes or additions you make must also be made publicly available under these exact terms.
* **Strictly Non-Commercial:** You are **not** permitted to sell this software, bundle it into a commercial product, or charge users a subscription fee to access a hosted version of it.

For more details, see the [LICENSE] file.

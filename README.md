# LouVentory

Multi-tenant inventory management SaaS. Each customer gets their own subdomain at `{tenant}.louventory.uk`. Built in vanilla PHP 8 + MySQL, deployable via FTP to Hostinger shared hosting.

---

## 1. Database Setup

1. Log into **Hostinger hPanel → Databases → MySQL Databases**
2. Create the database and user (already done — see credentials in `config/db.php`)
3. Go to **phpMyAdmin**, select `u463907152_louventory`, click **Import**
4. Import `schema.sql` — this creates all tables

---

## 2. Fill in `config/db.php`

The database credentials are already set. Before going live you must:

### Set the admin password

Run this on any PHP installation (e.g. your local machine or a test file):

```php
echo password_hash('your_chosen_password', PASSWORD_BCRYPT);
```

Copy the output and paste it as the value of `ADMIN_PASSWORD` in `config/db.php`.

### Set the cron token

Replace `change_me_to_a_long_random_secret` with a random string (e.g. generate one at https://www.random.org/strings/).

---

## 3. Deploy via FTP

Upload **all files** from this folder (the root) to your Hostinger `public_html` directory (or subdomain document root).

The structure in `public_html` should look like:

```
public_html/
├── .htaccess
├── index.php
├── config/
├── core/
├── pages/
├── admin/
├── assets/
├── templates/
├── uploads/
└── cron/
```

Make sure `.htaccess` is uploaded — FTP clients sometimes skip dotfiles. Verify mod_rewrite is enabled in Hostinger (it is by default on shared hosting).

---

## 4. Subdomain DNS Setup

In Hostinger hPanel → **Domains → Subdomains**, create:

| Subdomain | Points to |
|-----------|-----------|
| `admin` | `/public_html` (or wherever you deployed) |
| `*` (wildcard) | same directory |

A wildcard subdomain (`*.louventory.uk`) lets any tenant subdomain route to the same document root, where `index.php` reads the subdomain and loads the correct tenant.

If your hosting doesn't support wildcard subdomains, you must create each tenant subdomain manually and point it to the same directory.

---

## 5. Add Your First Tenant (via Admin Panel)

1. Go to `https://admin.louventory.uk`
2. Log in with `ADMIN_EMAIL` / the password you set in step 2
3. Fill in the **Create Tenant** form:
   - **Company / Name** — e.g. "Acme Ltd"
   - **Subdomain** — e.g. `acme` (will be `acme.louventory.uk`)
   - **Plan** — Free (100 items) or Pro
   - **Item Limit** — number of items this tenant can create
   - **First Admin User** — name, email, and password for their login
4. Click **Create Tenant**

The tenant can now log in at `acme.louventory.uk` with the credentials you set.

---

## 6. Cron Job (Overdue Alerts)

Set up a URL-based cron in **Hostinger hPanel → Advanced → Cron Jobs**:

- **Command / URL:** `https://admin.louventory.uk/cron/overdue_check.php?token=YOUR_CRON_TOKEN`
- **Schedule:** Daily — e.g. `0 8 * * *` (08:00 every day)

Replace `YOUR_CRON_TOKEN` with the value of `CRON_TOKEN` in `config/db.php`.

The cron script checks all tenants for overdue loans where no alert was sent today, emails the borrower using `mail()`, and logs the action.

---

## 7. File Uploads

Photos are stored in `uploads/{tenant_id}/{item_id}_{random}.{ext}`.

The `uploads/.htaccess` blocks PHP execution inside that directory so uploaded files cannot be executed as scripts.

Hostinger shared hosting typically has `file_uploads = On` by default. If uploads fail, check **hPanel → PHP Configuration** and ensure `file_uploads` is enabled and `upload_max_filesize` is at least `5M`.

---

## 8. Security Notes

- All DB queries use PDO prepared statements
- All output is escaped with `htmlspecialchars()`
- Every form has a CSRF token validated on POST
- Tenant isolation is enforced on every query via `tenant_id`
- The admin panel uses a separate session namespace (`lv_admin`) isolated from tenant sessions
- The `uploads/` directory blocks PHP execution via `.htaccess`

---

## File Structure

```
├── .htaccess               Routes all requests to index.php
├── index.php               Main router — detects tenant, dispatches pages
├── schema.sql              Full database schema — import this first
├── config/
│   └── db.php              DB credentials, admin credentials, constants
├── core/
│   ├── tenant.php          Subdomain detection, PDO singleton, tenant lookup
│   ├── auth.php            Login/logout/session, admin auth
│   └── functions.php       Helpers: h(), csrf, flash, log_activity, paginate…
├── pages/                  One file per page, included by index.php
├── templates/
│   ├── header.php          HTML <head> + app-layout wrapper
│   ├── sidebar.php         Dark navigation sidebar
│   └── footer.php          Closing tags + confirm modal + app.js
├── admin/
│   ├── index.php           Admin auth + routing
│   └── dashboard.php       Tenant management UI
├── assets/
│   ├── css/app.css         Full stylesheet
│   └── js/app.js           Confirm modals, clipboard copy, barcode scanner
├── uploads/                Item photos (auto-created per tenant)
│   └── .htaccess           Blocks PHP execution in uploads/
└── cron/
    └── overdue_check.php   Overdue loan email alerts (URL-triggered)
```

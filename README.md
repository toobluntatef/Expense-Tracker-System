# Spendly – A Simple Expense Tracker for Everyday Life

Stack: HTML5 + CSS + vanilla JavaScript (front-end) · PHP 8 + PDO (backend, JSON API) · MySQL (database).
No frameworks, no Composer, no npm and no CDN are needed: charts are drawn with built-in SVG code.

## How to run (XAMPP – Windows / macOS / Linux)

1. **Install XAMPP** (PHP 8.0 or newer) and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. **Copy the `spendly` folder** into XAMPP's web root:
   - Windows: `C:\xampp\htdocs\spendly`
   - macOS/Linux: `/opt/lampp/htdocs/spendly`
3. **Check the database settings** in `api/config.php`. XAMPP defaults (user `root`, empty password) already work.
4. **Create the database and accounts.** Choose one:
   - *Easy:* open **http://localhost/spendly/setup.php** – it creates the `spendly` database, all tables, default categories and the admin account.
     To also get a ready-made demo user with sample data, open **http://localhost/spendly/setup.php?demo=1**.
   - *Manual:* open phpMyAdmin → Import → choose `database/spendly.sql`, then still open `setup.php` once to create the admin account.
5. **Delete `setup.php`** once it has run.
6. Open **http://localhost/spendly/** in your browser.

### Logins
| Role  | Email               | Password   |
|-------|---------------------|------------|
| Admin | admin@spendly.test  | Admin@123  |
| Demo user (only if you used `?demo=1`) | demo@spendly.test | Demo@1234 |
| Regular user | register your own on the Register page | |

Change the admin password after the first login (Settings page).

### Without XAMPP (PHP built-in server)
Requires PHP with the `pdo_mysql` extension and a running MySQL server.
```
cd spendly
php -S localhost:8000
```
then open http://localhost:8000/setup.php and http://localhost:8000/.

## Project structure
```
spendly/
├── index.html            Landing page            ├── api/            PHP backend (JSON endpoints)
├── login.html            Login                   │   ├── config.php        DB credentials
├── register.html         Register                │   ├── bootstrap.php     PDO, sessions, CSRF, helpers
├── forgot.html           Forgot password info    │   ├── auth.php          register / login / logout / me
├── dashboard.html        Dashboard               │   ├── transactions.php  CRUD + search/filter/sort/paging
├── transactions.html     Transactions            │   ├── categories.php    user categories
├── budgets.html          Monthly budgets         │   ├── budgets.php       budgets + progress
├── goals.html            Savings goals           │   ├── goals.php         savings goals
├── reports.html          Reports + CSV/PDF       │   ├── dashboard.php     dashboard data
├── settings.html         Profile/password/cats   │   ├── reports.php       monthly report + CSV export
├── admin.html            Admin panel             │   ├── profile.php       profile + password
├── setup.php             One-time setup          │   └── admin.php         users + default categories
├── assets/css/style.css  Responsive dark theme
├── assets/js/            common.js, charts.js and one JS file per page
└── database/spendly.sql  MySQL schema + default categories
```

## Requirements coverage (SRS)
| ID | Feature | Where |
|----|---------|-------|
| FR-01/02 | Register (unique email), login, logout | register.html, login.html, api/auth.php |
| FR-03 | Add / edit / delete transactions (incl. "repeat monthly") | transactions.html, api/transactions.php |
| FR-04 | Default + custom categories | settings.html, api/categories.php |
| FR-05/06 | Monthly budgets with progress, warnings at 80% and 100% | budgets.html, dashboard banner, toast after saving an expense |
| FR-07 | Savings goals, add/withdraw money | goals.html, api/goals.php |
| FR-08 | Dashboard: balance, income vs expenses, trend line, category donut | dashboard.html |
| FR-09 | Search & filter by keyword, date range, category, type, amount; sort; pagination | transactions.html |
| FR-10 | Monthly report, 6-month chart, CSV export, print/Save-as-PDF | reports.html |
| FR-11 | Edit profile, change password | settings.html |
| FR-12 | Admin: activate/deactivate users, manage default categories, usage stats | admin.html |

Security (SRS §4): `password_hash`/`password_verify`; PDO prepared statements everywhere; input stripped of tags and
length-limited on the server and HTML-escaped when rendered; every query is scoped to the logged-in user's id;
session-cookie `HttpOnly` + `SameSite`, session id regenerated at login; CSRF token required on all write requests;
CSV export neutralises spreadsheet-formula injection. The admin API has no endpoint that exposes any user's
transactions, budgets or goals (only account details and aggregate counts).

## Notes / design decisions
- "Forgot password": the SRS has no email feature, so the admin can reset a user's password (Admin Panel → Reset password).
- "Repeat monthly" entries are created automatically for each month that has passed, the next time the user opens the app.
- Currency is shown as `Tk`. Change `CUR` in `assets/js/common.js` to use another symbol.
- If you rename the database, update `DB_NAME` in `api/config.php` **and** the two `spendly` lines at the top of `database/spendly.sql`.

## Troubleshooting
- *"Database connection failed"* – MySQL is not running or `api/config.php` credentials are wrong.
- *Blank page / "Unexpected server response"* – PHP errors; make sure you open the site through `http://localhost/...`, not by double-clicking the HTML files.
- *Port 3306 busy* – change `DB_PORT` in `api/config.php` to match your MySQL port.

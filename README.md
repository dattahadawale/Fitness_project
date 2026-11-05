# Apex Fitness Club — Gym Management System

A complete, modern, responsive, full-stack **Gym Management System** engineered using **Core PHP, MySQL, HTML5, CSS3, and Vanilla JavaScript**.

Designed with a high-energy athletic dark theme featuring deep obsidian, charcoal, crisp white, and radiant athletic orange (`#ff6b00`). No external frameworks (no React, Node, Laravel, Bootstrap, or Tailwind) are used.

---

## 🏋️ Features Overview

### 1. Public Landing Page
- High-impact athletic hero section with Call-To-Action buttons.
- Gym overview, facilities, and key performance statistics.
- Dynamic **Membership Plans** display with pricing and privileges.
- Certified **Coaching Staff** profiles with specializations.
- Operational hours, gym address, and contact details.

### 2. Administrator Portal
- **Dashboard**: 6 real-time KPI metric cards (Total Members, Active Members, Trainers, Monthly Revenue, Today's Check-Ins, Expired Subscriptions), membership expiry alerts banner, recent registrations, and transaction log.
- **Member Management**: Complete CRUD, search by name/code/email/phone, filter by plan and status, pagination, and view modal.
- **Trainer Management**: Manage fitness coaches, certifications, specializations, experience, and monthly salary.
- **Membership Plan Management**: Configure monthly, quarterly, semi-annual, and yearly packages with durations and pricing.
- **Subscription & Renewal Tracking**: Assign or renew memberships with automated expiry date calculation (`start_date + duration_days`).
- **Attendance Management**: Fast check-in interface with **database-level duplicate check-in prevention** on the same day, daily attendance table, and checkout timer.
- **Payment & Financial Records**: Record payments across **Cash, UPI, Card, and Bank Transfer**, scan the supplied PhonePe QR when selecting UPI, automatic reference generation, search filters, and **printable digital payment receipts**. Admins must confirm QR payments arrived before saving; scanning the static QR does not verify payment.
- **Workout Plan Builder**: Dynamic routine builder supporting custom exercises, day-of-week grouping, sets, repetitions, rest periods, and assignment to members.
- **Reports & Analytics**: Date-range filtered metrics, plan popularity analysis, payment method volume breakdown, daily attendance trends, and **one-click CSV downloads** for Members, Payments, and Attendance.
- **PhonePe Checkout**: Member-only PhonePe Standard Checkout v2, UPI-capable hosted checkout, verified server-side payment status, idempotent membership activation, and transaction history.
- **Email Verification**: PHPMailer SMTP delivery, hashed six-digit OTP, five-minute expiry, one-minute resend cooldown, and bounded verification/login attempts.

### 3. Member Portal
- **Digital Athlete ID Card**: Membership code, plan tier, status badge, and **days remaining countdown**.
- **Personal Profile**: View and edit contact details, phone, address, emergency contact, and secure password updates.
- **Attendance History**: Complete logs of gym floor sessions, timestamps, and visit streaks.
- **Invoices & Receipts**: Complete billing ledger with one-click printable official digital receipts.
- **Assigned Workout Regimens**: Structured day-by-day routines (Monday–Saturday) showing exercise movements, target sets, rep ranges, rest intervals, and trainer cues.

### 4. Security & Standards
- PHP sessions with session fixation prevention (`session_regenerate_id`).
- Cryptographic password hashing (`password_hash` with `PASSWORD_DEFAULT` / BCRYPT).
- PDO prepared statements across every query to prevent SQL injection.
- CSRF token generation and validation on all form actions.
- HTML output escaping with `htmlspecialchars()`.
- Server-side role-based authorization guards (`admin_auth.php`, `member_auth.php`).
- Existing accounts remain verified during migration; new members must verify before signing in or paying.

---

## 💻 System Prerequisites

- **XAMPP** (or any LAMP/WAMP/MAMP stack with PHP 8.0+)
- **Apache Web Server**
- **MySQL / MariaDB** (Port 3306)
- PHP extensions: PDO MySQL, cURL, and OpenSSL
- Composer 2
- **Web Browser** (Chrome, Firefox, Edge, Safari)

---

## 🚀 Installation & Setup Guide

### Step 1: Place Files in XAMPP `htdocs`
1. Copy the project folder into your XAMPP `htdocs` directory:
   ```text
   C:\xampp\htdocs\gym-management-system\
   ```
   *(Or if running directly in this workspace, you can symlink or use the PHP built-in server as shown below).*

### Step 2: Start Apache and MySQL
1. Launch the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 3: Create Database & Import Schema
There are two easy methods:

#### Method A: Using phpMyAdmin
1. Open your browser and navigate to: `http://localhost/phpmyadmin/`
2. Click **New** in the left sidebar.
3. Enter database name: `gym_management` and select collation `utf8mb4_unicode_ci`, then click **Create**.
4. Select the `gym_management` database, click on the **Import** tab.
5. Click **Choose File**, select `database/gym_management.sql`, and click **Go**.

#### Method B: Using Automated Setup Wizard
1. Open `http://localhost/gym-management-system/setup.php` in your browser.
2. Click **Import gym_management.sql** to create the existing tables, load sample data, and apply the new migration.

### Step 4: Install PHP dependencies and migrate an existing database
From the project directory, install PHPMailer:
```powershell
composer install
```
If the database already exists, back it up and apply the SQL files in `database/migrations/` in filename order using phpMyAdmin or the MySQL client. They add email verification, OTP, login rate-limit, PhonePe order tables, and the UPI payment-proof workflow. The latest UPI workflow migration also reconciles older proof-table layouts and backfills payment history. Do not run the base `database/gym_management.sql` against a database with data: it drops and recreates the existing tables.

In Command Prompt, from the project directory, the migrations can be applied in order:
```text
C:\xampp\mysql\bin\mysql.exe -u root -p gym_management < database\migrations\20261025_01_secure_member_payments.sql
C:\xampp\mysql\bin\mysql.exe -u root -p gym_management < database\migrations\20261025_02_phonepe_provider_transaction_id.sql
C:\xampp\mysql\bin\mysql.exe -u root -p gym_management < database\migrations\20261025_03_preserve_phonepe_records.sql
C:\xampp\mysql\bin\mysql.exe -u root -p gym_management < database\migrations\20261026_04_upi_payment_workflow.sql
```

---

## ⚙️ Database Configuration

`config/database.php` reads settings from the process environment and then from a project-root `.env` file. Process/server environment variables take precedence. Copy `.env.example` to `.env` and set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match your local MySQL installation. The `.env` file is git-ignored; never commit or share it.

```powershell
# Create the local, ignored configuration file if it does not exist.
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
# Edit .env and replace the DB_PASS placeholder with your local MySQL password.
# Keep DB_PASS empty only if your MySQL account has no password.

# Or set database values in the shell that starts PHP:
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "3306"
$env:DB_NAME = "gym_management"
$env:DB_USER = "root"
$env:DB_PASS = "<your local MySQL password>"
$env:APP_BASE_URL = "http://127.0.0.1:8000"

# Generate once and keep the same key for later server runs. Changing it
# invalidates pending email-verification codes.
if (-not [Environment]::GetEnvironmentVariable("APP_KEY", "User")) {
    $key = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($key)
    [Environment]::SetEnvironmentVariable("APP_KEY", [Convert]::ToBase64String($key), "User")
}
$env:APP_KEY = [Environment]::GetEnvironmentVariable("APP_KEY", "User")

# Start the app from the project directory.
php -S 127.0.0.1:8000 -t .
```

For XAMPP Apache, set the same values in Apache's environment configuration or its service environment, then restart Apache. Never put real secrets in a public source file or commit them. `APP_KEY` must be configured for member registration and email verification.

### PhonePe and email configuration
Start in sandbox mode and request credentials from your PhonePe merchant account. This integration uses the official PhonePe Standard Checkout API v2; the UPI app/payment options are provided by PhonePe's hosted checkout when available for the user's device and account. See PhonePe's [Standard Checkout API integration guide](https://developer.phonepe.com/payment-gateway/website-integration/standard-checkout/api-integration/api-integration-website) for current API and account-specific setup requirements.

Set these environment variables on the PHP process:

```text
PHONEPE_ENV=sandbox
PHONEPE_CLIENT_ID=<PhonePe sandbox client id>
PHONEPE_CLIENT_SECRET=<PhonePe sandbox client secret>
PHONEPE_CLIENT_VERSION=<PhonePe-issued client version>
PHONEPE_WEBHOOK_USERNAME=<webhook username configured in PhonePe>
PHONEPE_WEBHOOK_PASSWORD=<webhook password configured in PhonePe>

SMTP_HOST=<authenticated SMTP host>
SMTP_PORT=587
SMTP_USERNAME=<SMTP username>
SMTP_PASSWORD=<SMTP password>
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=<verified sender email>
SMTP_FROM_NAME=Apex Fitness Club
```

For local XAMPP development, open PowerShell in the project directory, set the existing database and `APP_KEY` values shown above, then add your SMTP provider's authenticated settings before starting PHP:

```powershell
$env:SMTP_HOST = "<your SMTP server hostname>"
$env:SMTP_PORT = "587"
$env:SMTP_USERNAME = "<your SMTP login>"
$env:SMTP_PASSWORD = "<your SMTP password or app password>"
$env:SMTP_ENCRYPTION = "tls"
$env:SMTP_FROM_EMAIL = "<verified sender address>"
$env:SMTP_FROM_NAME = "Apex Fitness Club"
C:\xampp\php\php.exe -S 127.0.0.1:8000
```

Use port `587` with STARTTLS (`SMTP_ENCRYPTION=tls`) or port `465` with implicit TLS (`SMTP_ENCRYPTION=ssl`), according to your email provider. For Gmail, use an app password when required by the account; do not use or commit your regular account password. The sender address must be authorized by the SMTP provider. Set environment variables in the same PowerShell session that starts PHP; setting them in a separate shell or editing `.env.example` does not configure the running server. After an SMTP failure, check the PHP/Apache error log for the `Email OTP delivery failed` diagnostic; logs exclude OTP contents and SMTP passwords.

`PHONEPE_ENV` accepts `sandbox` or `production`; production uses PhonePe's production endpoints and should only be enabled after sandbox verification. Configure the PhonePe webhook URL as `https://<your-domain>/actions/phonepe-webhook.php` and configure the same webhook username/password there. The webhook is authenticated and still verifies each order through PhonePe's server-side status API. A public HTTPS URL is required for production callbacks. Localhost can render the return page during sandbox development but cannot receive public webhooks.

`APP_KEY` is used as a server-only pepper when hashing email OTPs. Generate a unique value with a cryptographic random generator; do not use the example value above. PHPMailer is installed by Composer and sends through authenticated SMTP. Email and live PhonePe checkout are not available until valid credentials are configured.

**Currency:** Existing plan prices are now interpreted as INR without conversion. The numeric plan amounts remain unchanged; update them in the administrator portal if they are not your intended rupee prices.

---

## 🔒 Creating the Initial Administrator Account Securely

To comply with security guidelines, you can securely register or reset the administrator account using either of two methods:

### Method 1: Using the Interactive Setup Wizard
1. Visit `http://localhost/gym-management-system/setup.php`
2. Scroll to section **2. Create / Update Administrator Account**.
3. Fill in your Administrator Name, Email Address, and secure Password.
4. Click **Save Administrator Account**.
5. The password will be hashed using PHP BCRYPT before saving to the database.

### Method 2: Testing with Default Seeded Accounts
If you imported `database/gym_management.sql`, the following pre-seeded test accounts are ready for testing:

| Role | Email Address | Password |
|---|---|---|
| **Administrator** | `admin@gym.com` | `Admin@123` |
| **Member 1** | `alex@example.com` | `Member@123` |
| **Member 2** | `sophia@example.com` | `Member@123` |
| **Member 3** | `david@example.com` | `Member@123` |

*Note: On the login page (`login.php`), a convenient "Quick Test Credentials" filler is provided for instant testing.*

---

## 🌐 Running the Application

### Option 1: Via XAMPP Apache
Navigate to:
```text
http://localhost/gym-management-system/
```

### Option 2: Via PHP Built-in Server
Open PowerShell / Command Prompt inside the project directory and run:
```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000
```
Then visit:
```text
http://127.0.0.1:8000/
```

---

## 🧪 Testing Features Walkthrough

1. **Public Landing Page**:
   - Visit `index.php`. Review hero banner, stats, services, plans, and coaches.
2. **Admin Authentication**:
   - Go to `login.php`, click **Fill Admin** (or enter `admin@gym.com` / `Admin@123`), and sign in.
   - You will be redirected to `admin/dashboard.php`.
3. **Members Module**:
   - Navigate to `admin/members.php`. Click `+ Add New Member`.
   - Enter member details and pick a plan (e.g. Yearly Champion). Notice how the expiry date auto-calculates.
   - Submit and verify the member appears in the table with active status.
4. **Attendance Check-In**:
   - Go to `admin/attendance.php`. Select a member and click **Record Check-In**.
   - Attempt to check in the **same member again** on the same day: notice the warning banner preventing duplicate entries!
5. **Payment Recording & Receipts**:
   - Go to `admin/payments.php`. Click `+ Record Payment`.
   - Select member, enter amount `₹129.99`, select method **UPI** or **Card**, and save.
   - Click `🧾 Receipt` next to the transaction to preview and print the official receipt.
6. **Workout Builder**:
   - Go to `admin/workouts.php`. Click `+ Build New Workout Plan`.
   - Enter plan name, difficulty, and click `+ Add Exercise Row` to dynamically add exercises with sets, reps, and cues. Save the plan.
   - Click `👤 Assign to Member` to assign this regimen to a member.
7. **Reports & CSV Export**:
   - Go to `admin/reports.php`. Filter by "This Month" or "This Year".
   - Click `📥 Members CSV`, `📥 Payments CSV`, or `📥 Attendance CSV` to download formatted spreadsheets.
8. **Member Portal**:
   - Log out, then log in as `alex@example.com` / `Member@123`.
   - View your digital athlete card with **days remaining countdown**.
   - Check `member/workouts.php` to see your assigned workout routine broken down by day of the week.
   - Check `member/payments.php` to view invoices and print receipts.
9. **PhonePe member payment**:
   - Register with an active plan, verify the email OTP, and sign in.
   - Use **Pay Now with PhonePe** on the public plan cards or member dashboard.
   - Check transaction history and the payment status page; membership activation is based on server-side PhonePe status, not the browser redirect.
   - Test with PhonePe-issued sandbox credentials and sandbox payment methods. A browser redirect alone is not proof of payment.

---

## 🛠️ Common Errors & Troubleshooting

| Issue | Cause | Solution |
|---|---|---|
| **"Database Connection Error"** | MySQL service is not running or password mismatch | 1. Ensure MySQL is started in XAMPP.<br>2. Check password in `config/database.php`.<br>3. Open `setup.php` to verify status. |
| **"Table 'gym_management.users' doesn't exist"** | Database schema was not imported | Open `setup.php` and click **Import gym_management.sql** or import the file in phpMyAdmin. |
| **"Security token mismatch"** | CSRF token expired or cookies disabled | Refresh the page and resubmit the form. Ensure browser cookies are enabled. |
| **"Duplicate check-in prevented"** | Member already logged attendance today | Working as intended: system prevents duplicate check-in entries for the same member on the same day. |
| **"Access Denied on Admin Pages"** | Logged in as a member or not logged in | Admin pages are strictly protected. Log in using an administrator account. |
| **OTP email not received** | Missing SMTP settings, sender restrictions, or SMTP connectivity | Check the PHP/Apache error log, configure authenticated SMTP environment values, and use the resend button. After a failed resend, the prior code remains valid if it has not expired; the resend cooldown still applies. |
| **PhonePe checkout unavailable** | Missing sandbox credentials, incorrect callback URL, or API connectivity | Check server logs and PhonePe sandbox credentials; do not switch to production to bypass a sandbox configuration error. |

---

## 📁 Project Structure

```text
gym-management-system/
├── index.php                      # Public homepage & landing section
├── login.php                      # Unified authentication portal
├── register.php                   # Member self-registration
├── verify-email.php               # OTP entry and resend
├── logout.php                     # Secure session termination
├── setup.php                      # System installer & admin configuration
├── composer.json                  # PHPMailer dependency
├── .env.example                   # Environment-variable reference only
├── config/
│   └── database.php               # PDO connection singleton & credentials
├── includes/
│   ├── auth.php                   # Session security & helpers
│   ├── admin_auth.php             # Admin role gatekeeper
│   ├── member_auth.php            # Member role gatekeeper
│   ├── functions.php              # Global helpers, sanitization, CSRF, KPIs
│   ├── email.php                  # PHPMailer authenticated SMTP
│   ├── email-verification.php     # OTP creation, hashing, resend limits
│   ├── phonepe.php                # Standard Checkout v2 and payment settlement
│   └── security.php               # Database-backed login throttling
│   ├── header.php                 # Shared HTML head & inline SVG icons
│   ├── sidebar.php                # Responsive sidebar navigation
│   ├── navbar.php                 # Top navigation bar with live clock
│   └── footer.php                 # Shared footer & script inclusions
├── admin/
│   ├── dashboard.php              # 6 KPI cards, alerts, recent activity
│   ├── members.php                # Member CRUD, search, filter, pagination
│   ├── trainers.php               # Trainer & coach management
│   ├── membership-plans.php       # Plan packages CRUD & subscriber stats
│   ├── memberships.php            # Subscription assignment & renewals
│   ├── attendance.php             # Fast check-in & duplicate prevention
│   ├── payments.php               # Payment ledger & printable receipts
│   ├── workouts.php               # Dynamic routine builder & assigner
│   └── reports.php                # Financial analytics & CSV exporters
├── member/
│   ├── dashboard.php              # Digital athlete pass & today's workout
│   ├── profile.php                # Member profile & password management
│   ├── attendance.php             # Member check-in history & stats
│   ├── payments.php               # Personal billing ledger & receipts
│   └── workouts.php               # Day-by-day assigned workout routines
├── payments/
│   ├── return.php                 # Server-verified PhonePe return
│   └── result.php                 # Success, failure, and pending status view
├── actions/
│   ├── auth-actions.php           # Authentication & password processor
│   ├── verify-email-actions.php   # OTP verification and resend endpoints
│   ├── phonepe-actions.php        # Authenticated checkout initiation
│   └── phonepe-webhook.php        # Authenticated PhonePe callback endpoint
│   ├── member-actions.php         # Member CRUD backend
│   ├── trainer-actions.php        # Trainer CRUD backend
│   ├── membership-actions.php     # Plan & subscription backend
│   ├── attendance-actions.php     # Attendance check-in backend
│   ├── payment-actions.php        # Payment recording backend
│   ├── workout-actions.php        # Workout plan & exercise backend
│   └── export-reports.php         # Streaming CSV report generator
├── assets/
│   ├── css/
│   │   └── style.css              # Custom responsive dark & orange styles
│   └── js/
│       └── script.js              # Vanilla JS modals, search, dynamic rows
├── database/
│   ├── gym_management.sql         # Existing tables and sample data
│   └── migrations/                # Additive account, payment, and integrity changes
└── README.md                      # Documentation & setup manual
```

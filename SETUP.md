# Alliah Dental — Setup Guide for New Team Members

> Read this if you just cloned this project and need to get it running.

---

## What This Project Is

A dental clinic appointment system with:
- **Public booking** via SMS OTP (no passwords for patients)
- **Admin dashboard** for staff to manage appointments
- **Supabase PostgreSQL** database (cloud-hosted)
- **UniSMS** for sending OTP text messages

---

## Step 1: Prerequisites

You need **XAMPP** (or any PHP server) with:

| Requirement | How to Check |
|-------------|-------------|
| PHP 8.1+ | Run `php -v` in terminal |
| `pdo_pgsql` extension | Run `php -m \| findstr pgsql` |
| Internet connection | Required — Supabase is cloud-hosted |

### If `pdo_pgsql` is missing:
1. Open `C:\xampp\php\php.ini`
2. Find `;extension=pdo_pgsql`
3. Remove the `;` at the start → `extension=pdo_pgsql`
4. Save and **restart Apache**
5. Run `php -m \| findstr pgsql` again — you should see `pdo_pgsql`

---

## Step 2: Clone and Place Files

1. Clone the repo into your XAMPP `htdocs` folder:
   ```
   C:\xampp\htdocs\alliah\
   ```

2. Make sure the `logs/` folder exists and is writable:
   ```
   C:\xampp\htdocs\alliah\logs\
   ```
   (It should already exist. If not, create it.)

---

## Step 3: Database Connection

The project connects to **Supabase** (cloud PostgreSQL). The credentials are already in the code as fallbacks, so it should work immediately if:
- You have internet
- The Supabase project is active (not paused)
- Your IP is allowlisted in Supabase

### If you see "Database Connection Failed":

Check these in order:

1. **No internet?** Supabase is cloud-hosted. You need an active connection.

2. **Supabase project paused?** Free-tier Supabase projects auto-pause after 7 days of inactivity. Ask the project owner to log into Supabase and click **Resume**.

3. **IP blocked?** Supabase may only allow certain IPs. Ask the project owner to add your IP in:
   > Supabase Dashboard → Project Settings → Database → IPv4 Allow List

4. **Check the exact error:**
   ```
   C:\xampp\htdocs\alliah\logs\database.log
   ```

---

## Step 4: Test the Booking Flow (OTP)

### Option A: Mock Mode (Recommended for Local Dev)

By default, the app tries to send real SMS via UniSMS. For local testing, use **mock mode** so no real texts are sent.

1. Open `includes/functions-fixed.php`
2. Find line ~13:
   ```php
   define('SMS_MOCK_MODE', false);
   ```
3. Change to:
   ```php
   define('SMS_MOCK_MODE', true);
   ```

Now when you book:
- The OTP appears on the page itself
- The OTP is also written to `logs/sms_mock.log`
- No real SMS is sent = no credits used

### Option B: Live SMS (Requires UniSMS Account)

If you want real SMS delivery:
1. Make sure `SMS_MOCK_MODE` is `false`
2. The UniSMS API key is already in the code as a fallback
3. Ensure your server can reach `https://unismsapi.com`
4. Check `logs/sms_errors.log` if messages fail

---

## Step 5: Access the App

| Page | URL |
|------|-----|
| Public booking | `http://localhost/alliah/public/client/book.php` |
| View my bookings | `http://localhost/alliah/public/my-bookings.php` |
| Admin login | `http://localhost/alliah/public/login.php` |

---

## File Structure (What You Need to Know)

```
alliah/
├── includes/
│   ├── config.php          ← Database credentials, env vars
│   ├── functions-fixed.php ← Main helpers, OTP logic, SMS sending
│   └── bootstrap.php       ← Loads config + functions + auth
├── public/
│   ├── client/book.php     ← Patient booking page (OTP flow)
│   ├── my-bookings.php     ← View history with OTP
│   ├── login.php           ← Staff/admin login
│   └── admin/              ← Admin dashboard pages
├── logs/
│   ├── sms_mock.log        ← OTP codes in mock mode
│   ├── sms_errors.log      ← SMS API errors
│   └── database.log        ← Database connection errors
└── SETUP.md                ← This file
```

---

## Common Issues & Fixes

### "Missing PHP Extension: pdo_pgsql"
→ See Step 1. Enable `extension=pdo_pgsql` in `php.ini` and restart Apache.

### "Database Connection Failed"
→ See Step 3. Check internet, Supabase status, and `logs/database.log`.

### "Failed to send OTP. Please try again."
→ Check `logs/sms_errors.log` for the exact API error. Most likely:
- No internet
- Wrong API endpoint (already fixed in latest code)
- UniSMS account has no credits

### Form looks broken / squished
→ Already fixed in latest code. If you still see it, make sure you pulled the latest version.

---

## For Production Deployment

When deploying to a live server:

1. **Set environment variables** instead of relying on hardcoded fallbacks:
   ```
   DB_HOST=your-db-host
   DB_PASS=your-db-password
   UNISMS_ACCESS_KEY=your-api-key
   ```

2. **Whitelist the server IP** in Supabase dashboard.

3. **Ensure the host supports PostgreSQL** (`pdo_pgsql` extension).

4. **Set `SMS_MOCK_MODE` to `false`** for real SMS delivery.

---

## Need Help?

If something isn't working:
1. Check the relevant log file in `logs/`
2. Share the exact error message from the log
3. Mention what you already tried from this guide


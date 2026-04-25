# OTP API & Security Fix TODO

## Problem 1: "Failed to send OTP. Please try again."
- ✅ Fixed UniSMS endpoint in `includes/functions-fixed.php` to `https://unismsapi.com/api/sms`
- ✅ Added dedicated OTP endpoints (`/api/otp` and `/api/otp/verify`)
- ✅ Added `requestOtpViaSms()` and `verifyOtpSubmission()` helpers
- ✅ Updated `public/client/book.php` and `public/my-bookings.php` to use new OTP flow
- ✅ Added error logging to `logs/sms_errors.log`
- ✅ Fixed HTML structure (missing `</div>` tags) in `public/client/book.php`

## Problem 2: Security — Hardcoded Credentials
- ✅ Moved all sensitive credentials to environment variables with fallbacks in `includes/config.php`
- Database: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- UniSMS: `UNISMS_ACCESS_KEY`, `UNISMS_SENDER`

## Problem 3: Teammate's Database Error
- ✅ Added `extension_loaded('pdo_pgsql')` check with helpful HTML error message
- ✅ Improved PDOException error message with specific causes and fixes

## Steps for Teammates
1. Enable `extension=pdo_pgsql` in `php.ini` and restart Apache
2. Ensure internet connection is active (Supabase is cloud-hosted)
3. If still failing, check `logs/database.log` for exact error

## Steps for Production Deployment
1. Set environment variables on the hosting server (do not commit `.env` file)
2. Whitelist the production server's IP in Supabase dashboard
3. Ensure hosting provider supports PostgreSQL (`pdo_pgsql`)
4. Switch `SMS_MOCK_MODE` to `false` for real SMS delivery


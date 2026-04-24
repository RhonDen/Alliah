# OTP API Fix TODO

## Problem
"Failed to send OTP. Please try again."
Root cause: UniSMS API configuration issues.

## Changes Made
- [x] Fixed UniSMS endpoint to `https://unismsapi.com/api/sms` in `includes/functions-fixed.php`
- [x] Added error logging to `includes/functions-fixed.php` (writes to `logs/sms_errors.log`)
- [x] Added dedicated OTP endpoint (`/api/otp`) and verify endpoint (`/api/otp/verify`) for UniSMS
- [x] Added `requestOtpViaSms()` and `verifyOtpSubmission()` helpers in `includes/functions-fixed.php`
- [x] Updated `public/client/book.php` to use `requestOtpViaSms()` and `verifyOtpSubmission()`
- [x] Updated `public/my-bookings.php` to use `requestOtpViaSms()` and `verifyOtpSubmission()`
- [x] Fixed `normalizeMobile` in `functions-ultimate.php` to return international format
- [x] Added missing helpers (`formatMobileForDisplay`, `findClientByMobile`) in `functions-ultimate.php`
- [x] Updated `functions-ultimate.php` with same OTP endpoint fixes and new helpers

## Follow-up
- Test OTP send on the booking page
- If it still fails, check `logs/sms_errors.log` for HTTP code and API response
- Verify your UniSMS API key has sufficient credits and OTP permissions

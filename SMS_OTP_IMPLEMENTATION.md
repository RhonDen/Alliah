# Replace Patient Passwords With SMS OTP

## Why We Are Removing Patient Passwords

Passwords are a poor fit for most dental patients:

- Many patients, especially elderly patients, forget passwords and give up on booking.
- Registration adds friction that does not help the clinic confirm the mobile number is real.
- Fake bookings are easy when the phone number is never verified.
- "Forgot password" support steals time from clinic staff.

SMS OTP solves the real problem more directly:

- Patients only enter their name, mobile number, age, and schedule preference.
- A 6-digit code is sent to the phone number they entered.
- The booking is only created after that code is verified.
- The verified mobile number becomes the patient identity, so future bookings on the same number stay attached to the same history.
- Admin/staff login still uses email plus password.

## What Changed In This Repo

- `public/client/book.php` is now a public 2-step OTP booking page.
- `public/my-bookings.php` lets patients view booking history with the same OTP pattern.
- `public/login.php` is now staff-only.
- `public/register.php`, `public/set-password.php`, and `public/walkin-complete.php` now point patients to OTP flows instead of password setup.
- `includes/functions-fixed.php` now contains:
  - international mobile normalization (`639123456789`)
  - display formatting for local UI (`0912-345-6789`)
  - OTP generation
  - mock SMS logging
  - mobile-based patient lookup/creation
  - appointment status SMS helpers
- `public/admin/appointments.php` now sends SMS on approval/rejection.
- `public/api/availability.php` is public, so booking can load time slots before login.

## Step-By-Step Rollout

1. Run `sms_otp_migration.sql` in phpMyAdmin or your MySQL client.
2. Keep `SMS_MOCK_MODE` on while testing. The app writes SMS messages to `logs/sms_mock.log`.
3. Open `public/client/book.php`, submit a booking request, and read the OTP from the log.
4. Verify the OTP and confirm the appointment is created.
5. Open `public/my-bookings.php`, enter the same mobile number, and confirm the appointment history appears after OTP verification.
6. Log in as admin at `public/login.php`, approve or reject the appointment, and confirm the follow-up SMS is appended to `logs/sms_mock.log`.
7. When you are ready for production, replace the mock sender inside `includes/functions-fixed.php` with the real UniSMS call and hide the development OTP block from the booking/history pages.

## UniSMS Switch Point

The app is intentionally wired so the rest of the code only calls:

- `sendOtpViaSms()`
- `sendAppointmentStatusSms()`

To go live with UniSMS, keep those function names and swap the internals of `sendSmsMock()`/the SMS transport layer so nothing else in the booking flow needs to change.

## Checklist

- `users.mobile` is unique.
- `users.password` allows `NULL`.
- `users.otp_code` and `users.otp_expires_at` exist.
- Patient booking works without registration or login.
- OTP expires after 10 minutes.
- Patient history is preserved by mobile number.
- Admin approval/rejection sends SMS.
- `logs/` exists and is writable for mock mode.

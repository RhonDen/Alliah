# Implementation Plan

## Overview
The current login system in `public/login.php` supports both email and mobile login via separate input fields, with backend logic querying the database for a user matching either the provided email OR mobile identifier. However, users report \"invalid credentials\" when attempting login specifically with mobile number + password (testing intentionally without email). This suggests a backend authentication issue where the mobile-based lookup or password verification fails unexpectedly, possibly due to data inconsistencies (e.g., mobile format mismatches like country code handling, duplicate records, or unhashed legacy passwords), validation gaps, or session issues. The goal is to diagnose and fix the mobile login flow to ensure reliable authentication using mobile number alone, while preserving email support and UX for older users who prefer phone numbers.

This fits into the existing PHP/MySQL-based clinic management system, where user auth relies on `includes/auth.php` for session management and `public/login.php` for form handling. No major refactoring needed; focus on robust mobile handling, debugging aids, and edge-case fixes without altering the dual-field UI (already separate email/mobile inputs).

## Types
No new type system changes required (plain PHP without TypeScript/strict typing).

## Files
Minimal modifications to existing files:
- **public/login.php** (modify): Add mobile-specific normalization/formatting, enhanced error logging/debug output, improved identifier handling to prioritize mobile when provided.
- **includes/auth.php** (no change): Existing `loginUser()` and session functions are sufficient.
- **includes/functions.php** (modify): Add new helper functions for mobile normalization/validation and user lookup by mobile.
- No new files.
- No deletions.

## Functions
New helper functions for better mobile handling:
- `normalizeMobile(string $mobile): string` in `includes/functions.php` – Strips +63 prefix, validates PH format (9-11 digits), normalizes to consistent storage format.
- `findUserByMobile(PDO $pdo, string $mobile): ?array` in `includes/functions.php` – Dedicated query for mobile lookup to isolate issues.
- `isValidMobile(string $mobile): bool` in `includes/functions.php` – Regex validation for PH numbers.

Modified functions:
- Login handler in `public/login.php` (anonymous block): Enhance `$identifier` logic to use mobile-first if provided, add logging with `error_log()`, fallback to dedicated mobile query if general query fails.

No removals.

## Classes
No classes involved (procedural PHP codebase).

## Dependencies
No new packages or version changes (pure PHP/MySQL).

## Testing
- **Unit-like tests via CLI/DB**: Insert test users with mobile-only data, run manual login simulations.
- **Manual browser tests**: 
  1. Login with mobile only (valid/invalid password).
  2. Login with email only.
  3. Login with both (prioritize mobile).
  4. Edge cases: Invalid formats (+639123456789, 09123456789, 123), short/long numbers.
- **DB checks**: Verify `users` table schema (`mobile` column nullable? unique?), sample data consistency.
- Success criteria: Mobile login succeeds for existing valid users; specific error for non-existent mobile; no regressions on email login.
- Add temporary debug logging, remove post-fix.

## Implementation Order
1. Add mobile helper functions to `includes/functions.php`.
2. Update `public/login.php` login handler with normalization, logging, and fallback logic.
3. Test login flows in browser.
4. Review logs/DB for issues, iterate if needed.
5. Clean up debug code, confirm fix.

# Profile Avatar & Google OAuth Audit

## Summary

The project already contains a robust Google OAuth flow and user avatar fallback system. The user record keeps a `google_id`, `avatar`, and email verification state. The existing architecture supports Google login safely and already resolves the user avatar URL before rendering in the header and account pages.

No duplicate Google avatar column is required. The project already reuses the existing `users.avatar` field as the active image source in the customer account. This matches the requirement to avoid unnecessary schema bloat.

## Existing Google OAuth architecture

- `public/includes/google_auth.php` creates the Google authorization URL and validates OAuth state.
- `exchange_google_code_for_user()` exchanges the code and fetches Google profile data.
- `sync_google_customer()` binds the Google `sub` value to the local user record using `google_id`.
- The Google profile `picture` is saved to the existing `avatar` column when present.

## Avatar priority model

The existing project already follows the intended logic:

- user custom avatar (if present in the app)
- Google avatar URL if saved in `avatar` via OAuth
- initials SVG fallback if avatar is empty

The key requirement is to keep Google profile images as a source of truth but allow a later custom upload to replace the active avatar without erasing the Google image record.

## Why the current system is safe

- OAuth state is validated against a server-side session value.
- Google credentials remain server-side and are not exposed in the frontend.
- Email verification is required before a Google record is accepted.
- Duplicate email/google account linking is blocked.
- Existing admin accounts are explicitly excluded from Google customer provisioning.

## Current rendering points

The user avatar is used in:

- `public/header.php`
- `public/account.php`
- `public/profile.php`
- any user-facing customer cards that call `user_avatar_url()`

The shared helper `user_avatar_url()` in `public/includes/helpers.php` correctly handles:

- absolute URL input,
- local upload paths,
- generated initials fallback.

## Recommendation

No database migration is needed unless the team wants a distinct `google_avatar_url` field for explicit provenance tracking. The current `users.avatar` plus `users.google_id` is already sufficient and safer than duplicating another column.

## Files reviewed

- `public/includes/google_auth.php`
- `public/includes/helpers.php`
- `public/header.php`
- `public/account.php`
- `public/profile.php`

## Conclusion

The existing Google login architecture is already production-oriented and aligned with the requested behavior. The real issue in the user image experience was the inconsistent product image handling, not the Google avatar flow itself.

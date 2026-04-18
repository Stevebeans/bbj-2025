# Design: Login & Registration Modals (bbj-v2-theme)

**Date:** 2026-04-18
**Status:** Draft — pending user review
**Feature area:** `wp-content/themes/bbj-v2-theme/` + `wp-content/plugins/bigbrotherjunkies-data/`

## Goal

Build a reusable, modal-based authentication UI for the bbj-v2 theme with four
views — Log In, Create Account, Link Account, Forgot Password, Reset Password —
that supports Google Sign-In alongside traditional username/password, and stays
reliable for users stuck in Facebook/Instagram in-app browsers.

## Design principles

1. **Native WordPress session.** Use `wp_set_auth_cookie()` instead of JWT so
   `is_user_logged_in()` works server-side, the admin bar appears, role-based
   ad hiding applies, comment authorship works — no two-worlds problem.
2. **One backend.** The existing `bigbrotherjunkies-data` plugin endpoints stay
   the single source of truth for auth logic; the Next.js app continues to use
   them unchanged. The PHP theme requests the same endpoints with an opt-in
   `wp_session=1` flag that adds a WP cookie alongside the JWT response.
3. **Fallback-first.** Username/password always works. Google is additive.
   Users in WebViews (which block third-party cookies) are not locked out.
4. **Reusable triggers.** `bbj_v2_auth_trigger()` is a stateless helper that
   any template can emit (logo bar, comment forms, sidebar widgets, walled
   pages). All triggers open the same global modal.
5. **No JS state after auth.** Successful auth reloads the page; the server
   becomes the source of truth for "am I logged in."

## Architecture

```
┌──────────────────── Theme (presentation) ────────────────────┐
│                                                              │
│  footer.php loads template-parts/auth/modal.php (once)       │
│                                                              │
│  Modal markup (hidden by default)                            │
│    id="bbj-auth-modal" aria-hidden="true"                    │
│    Contains 5 <section>s, one per view; JS toggles visible.  │
│                                                              │
│  Trigger helper                                              │
│    bbj_v2_auth_trigger($label, $view='login')                │
│    → <button data-bbj-auth-open="login">Label</button>       │
│    Returns empty when is_user_logged_in() is true.           │
│                                                              │
│  JS controller (src/js/auth-modal.js)                        │
│    • open/close, switch view, focus trap, Esc                │
│    • Detects FB/IG WebView → hides Google button + CTA       │
│    • POSTs forms to plugin endpoints (same-origin, nonce)    │
│    • On success → window.location.reload()                   │
│                                                              │
└──────────────────────────────────────────────────────────────┘
                  │ fetch() same-origin, body includes wp_session=1
                  ▼
┌─── bigbrotherjunkies-data plugin (auth logic, unchanged API) ┐
│                                                              │
│  Existing endpoints extended with optional wp_session flag:  │
│   POST /bbjd/v1/auth/google                                  │
│   POST /bbjd/v1/auth/link-google                             │
│   POST /bbjd/v1/auth/create-from-google                      │
│   POST /bbjd/v1/auth/register                                │
│   POST /bbjd/v1/auth/forgot-password   (no change)           │
│   POST /bbjd/v1/auth/reset-password    (wp_session accepted) │
│   POST /bbjd/v1/auth/check-username    (no change)           │
│   POST /bbjd/v1/auth/check-email       (no change)           │
│                                                              │
│  New endpoint for user/password login:                       │
│   POST /bbjd/v1/auth/login                                   │
│     Thin wrapper around wp_signon(); sets auth cookie;       │
│     returns {success, user}. Avoids theme-side JWT plugin    │
│     coupling.                                                │
│                                                              │
│  WpSessionBridge::maybeSetAuthCookie($userId, $remember, $r) │
│   When request has wp_session=1:                             │
│    wp_set_auth_cookie($userId, $remember, is_ssl(), 'logged_in') │
│    set_auth_cookie filter enforces SameSite=Lax              │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

## Components

### Theme: new files

```
wp-content/themes/bbj-v2-theme/
├── template-parts/auth/
│   ├── modal.php             Modal chrome + slots for 5 views
│   ├── view-login.php        Log In view (Google + form + forgot link)
│   ├── view-register.php     Create Account view
│   ├── view-link.php         Link-Account view (Google profile + 2 paths)
│   ├── view-forgot.php       Forgot Password view
│   └── view-reset.php        Reset Password view (pre-filled from URL)
├── src/js/
│   ├── auth-modal.js         Open/close, view switch, focus trap, Esc
│   ├── auth-google.js        GIS init, WebView detect, credential → POST
│   └── auth-forms.js         Form submit, error display, debounced checks
├── inc/auth.php              Trigger helper, modal loader, ?bbj_rp handler
```

### Theme: modifications

| File | Change |
|---|---|
| `footer.php` | Add `get_template_part('template-parts/auth/modal')` before `wp_footer()` |
| `template-parts/header/logo-bar.php` | Replace Log In anchor with `bbj_v2_auth_trigger('Log In', 'login')` |
| `inc/enqueue.php` | Enqueue auth JS + GSI script for anonymous users only |
| `src/css/style.css` | Modal component classes: `.bbj-modal`, `.bbj-modal-dialog`, `.bbj-modal-view` |

### Plugin: new files

```
wp-content/plugins/bigbrotherjunkies-data/src/Auth/
└── WpSessionBridge.php       One class, one method: maybeSetAuthCookie()
```

### Plugin: modifications

- `src/Api/AuthRoutes.php` — each of the 5 auth-issuing endpoints
  (`google`, `link-google`, `create-from-google`, `register`,
  `reset-password`) calls `WpSessionBridge::maybeSetAuthCookie()` when the
  request contains `wp_session=1`. Existing JWT return shape is preserved.
- `src/Api/AuthRoutes.php` — add a new `POST /bbjd/v1/auth/login` endpoint
  that calls `wp_signon()` and returns `{success, user}` (no JWT).
- `src/Plugin.php` — register the new `WpSessionBridge` class.

### Component responsibilities

| Component | Responsibility |
|---|---|
| `modal.php` | Backdrop, dialog, close button; slot the 5 view partials |
| `view-*.php` | Static markup for one flow; server-rendered labels & nonce |
| `auth-modal.js` | Modal state machine (open/close/switch); focus trap; Esc |
| `auth-google.js` | GIS script load, button render, WebView detection, callback |
| `auth-forms.js` | Form serialization, fetch, error display, success reload |
| `inc/auth.php` | Trigger helper; modal include; reset-link `?bbj_rp=…` handler |
| `WpSessionBridge.php` | Single decision point for "also set WP auth cookie" |

## Data flow

### Flow 1 — Username/password login

1. User clicks a trigger → `auth-modal.js` opens modal, shows `view-login`.
2. User fills username + password, toggles "Keep me logged in", submits.
3. `auth-forms.js` POSTs `/bbjd/v1/auth/login` with
   `{username, password, remember_me, wp_session: 1, _wpnonce}`.
4. Plugin calls `wp_signon()`; on success `WpSessionBridge` sets the auth
   cookie; response is `{success: true, user}`.
5. JS reloads the page. Server re-renders with `is_user_logged_in() === true`.

### Flow 2 — Registration

1. User clicks "Sign up" in modal → `view-register`.
2. As user types username: debounced 500ms POST to `/auth/check-username`.
3. As user types email: debounced 500ms POST to `/auth/check-email`.
4. On submit, JS fetches reCAPTCHA v3 token (action: `register`).
5. POST `/bbjd/v1/auth/register` with
   `{username, email, password, display_name, subscribe_newsletter, recaptcha_token, wp_session: 1}`.
6. Plugin validates, creates the WP user, sets auth cookie, fires existing
   hooks (MailPoet subscribe). Response `{success: true, user}`.
7. JS shows success state briefly, reloads.

### Flow 3 — Google sign-in (account exists)

1. User clicks Google button in `view-login`.
2. GIS returns a credential (ID token).
3. `auth-google.js` POSTs `/bbjd/v1/auth/google` with
   `{credential, remember_me, wp_session: 1}`.
4. Plugin verifies the credential against Google tokeninfo, finds the WP user
   by `google_id` user meta or by email match, sets auth cookie.
5. JS reloads.

### Flow 4 — Google sign-in (no match → link flow)

1. Google credential returned, POST `/auth/google`.
2. Plugin finds no `google_id` and no email match; responds
   `{success: false, needs_linking: true, google_user: {email, name, picture}}`.
3. JS switches modal to `view-link`, caches the credential in modal state.
4. The Link-Account view presents two paths:

   **Path A — link to existing BBJ account:**
   User enters BBJ username + password → POST `/bbjd/v1/auth/link-google`
   with `{credential, username, password, wp_session: 1}`. Plugin validates,
   attaches `google_id` meta to the matched user, sets auth cookie, reload.

   **Path B — create new BBJ account from Google:**
   User clicks "Continue as New User" → POST `/bbjd/v1/auth/create-from-google`
   with `{credential, wp_session: 1}`. Plugin creates WP user from Google
   profile (random password), sets auth cookie, reload.

### Flow 5 — Forgot password

1. User clicks "Forgot password?" in `view-login` → `view-forgot`.
2. Submit email → POST `/bbjd/v1/auth/forgot-password` (unchanged).
3. Plugin sends a reset email with link `https://site/?bbj_rp=1&key=…&login=…`.
4. JS shows "Check your email" success state.

### Flow 6 — Reset password (from email link)

1. User clicks email link; lands on the site with `?bbj_rp=1&key=…&login=…`.
2. `inc/auth.php` sees the query args and injects a small inline script that
   auto-opens the modal to `view-reset`, passing `key` and `login`.
3. User enters new password + confirm, submits.
4. POST `/bbjd/v1/auth/reset-password` with
   `{key, login, password, wp_session: 1}`.
5. Plugin validates the reset key, updates the password, sets auth cookie.
6. JS reloads to `/`.

### Cross-cutting — WebView detection

On modal open, `auth-google.js` checks:

```js
const isWebView =
  /FBAN|FBAV|Instagram|Line\//.test(navigator.userAgent)
  || (new URLSearchParams(location.search)).has('bbj_force_webview');
```

When true: hide the Google button, render instead a short message —
"Google sign-in doesn't work inside the Facebook/Instagram browser. Tap the
••• menu and choose 'Open in Chrome' / 'Open in Safari.'" Username/password
form remains the primary path and works normally.

When `WP_DEBUG` is on, visiting any URL with `?bbj_force_webview=1` sets a
session cookie that keeps the override active until the browser closes, so
developers can navigate and see the fallback UI on every page. In production
(`!WP_DEBUG`) the query-param path is disabled — only real UA matches trigger
the fallback.

### Cross-cutting — nonces & CSRF

- Every POST body includes a `_wpnonce` generated per page load via
  `wp_create_nonce('bbj_auth')`.
- Plugin endpoints verify with `check_ajax_referer('bbj_auth', '_wpnonce', false)`.
- `fetch()` calls use `credentials: 'same-origin'`; endpoints reject
  cross-origin requests when `wp_session=1` is present.

### Cross-cutting — remember-me and cookie hardening

- `wp_set_auth_cookie($user_id, $remember, is_ssl(), 'logged_in')`.
- `$remember=true` → 14-day cookie; `false` → session cookie.
- Default ON for Google sign-in, matches the checkbox state for form login.
- `set_auth_cookie` filter appends `SameSite=Lax` to the cookie header.
- `HttpOnly` and `Secure` (in prod via `is_ssl()`) are the WP defaults.

## Error handling

### Server response contract

| Scenario | HTTP | Body |
|---|---|---|
| Success | 200 | `{success: true, user}` |
| Invalid credentials | 401 | `{success: false, error}` |
| Needs linking (Google no match) | 200 | `{success: false, needs_linking: true, google_user}` |
| Validation error | 422 | `{success: false, error, field?}` |
| reCAPTCHA failed | 400 | `{success: false, error}` |
| Rate limited | 429 | `{success: false, error}` |
| Bad Google credential | 400 | `{success: false, error}` |
| Nonce invalid | 403 | `{success: false, error: 'Session expired. Please refresh the page.'}` |
| Server error | 500 | `{success: false, error}` |

### Client display rules

- Field-level error (has `field`) → red text under the named input; other
  inputs stay normal.
- Form-level error → red banner above the form.
- On submit, disable the submit button and show a spinner in its label;
  re-enable on error.
- On reload-expected success, keep the button disabled with "Logging you in…";
  if reload doesn't happen within 3s, re-enable with a retry message.

### Google-specific failures

- GIS script fails to load (ad blocker, offline) → silently drop the Google
  button; render a small note "Google sign-in unavailable. Please use the
  form below."
- GIS `callback` never fires → no-op; user can retry or use the form.

### Rate limiting (defense-in-depth)

Stored as WP transients keyed by `remote_addr` (or email, for forgot-password):

- Login: 10 failed attempts / IP / 15 min → 429.
- Register: 5 registrations / IP / hour.
- Forgot-password: 3 requests / email / hour.

### Accessibility on error

- Invalid-field focus moves to the first invalid input.
- Error containers use `role="alert"` so screen readers announce them.
- Modal itself is `role="dialog" aria-modal="true"` with `aria-labelledby`.

### Server logging

- Successful login → WP's `wp_login` action fires (existing audit plugin).
- Failed login → WP's `wp_login_failed` action fires.
- Google verification failures → `error_log()` with reason only (never the
  credential itself).

## Testing

No PHPUnit or Jest setup exists in the theme, and wiring one up only for this
feature is overkill. Testing is manual with a scripted matrix.

### Manual test matrix

Run in order:

1. Username/password login, happy path — reload, avatar/name appears in
   logo bar, `is_user_logged_in()` true server-side.
2. Wrong password — red banner, form re-enabled, no reload.
3. Remember me — checked survives browser restart; unchecked expires on quit.
4. Registration, happy path — user created, auto-logged-in, WP user row
   correct, newsletter checkbox triggers MailPoet subscribe.
5. Username taken — debounced check surfaces error without submit.
6. Password mismatch — inline error on confirm field.
7. reCAPTCHA blocked (browser plugin) — clean failure message.
8. Google sign-in, existing account — reload, logged in.
9. Google sign-in, no match → link — profile card renders, link with existing
   creds succeeds, reload.
10. Google sign-in, no match → create new — "Continue as New User" creates
    account with Google profile, reload.
11. Forgot password — success message, real email arrives with working link.
12. Reset password — follow email link, modal opens to reset view pre-filled,
    new password works.
13. Logout — admin bar logout, page reloads, triggers return.

### Edge cases

- **WebView detection (dev):** visit `/?bbj_force_webview=1`, click Log In,
  confirm Google button replaced with "open in Chrome/Safari" message; form
  still works.
- **WebView detection (real):** when ready, capture the UA string from an
  actual FB/IG in-app browser and add to the regex if it doesn't match.
- **Nonce expiry:** leave modal open 24h, submit, confirm clear "refresh the
  page" error instead of silent failure.
- **Dark mode:** all five views render correctly.
- **Keyboard only:** Tab through modal, Esc closes, Shift+Tab wraps, focus
  doesn't escape the dialog.
- **Screen reader:** NVDA or VoiceOver announces view title on open, errors
  on submit failure.
- **Cookie flags:** DevTools → Application → Cookies confirms `SameSite=Lax`,
  `HttpOnly`, `Secure` in prod.
- **Logged-in user sees no triggers:** `bbj_v2_auth_trigger()` returns empty
  when `is_user_logged_in()`.

### Regression checks

- **Next.js app still works:** login via React UI at `../bbj-app/` continues
  to issue a JWT. Since `wp_session` is opt-in and the React app doesn't
  send it, behavior is unchanged.
- **WP admin login (`/wp-login.php`):** unaffected.

### Smoke test before shipping

Run flows 1, 4, 8, 9, 11 — ~90% of surface area. If any regress, don't merge.

## Non-goals (YAGNI)

- Social logins beyond Google (Facebook, Twitter, Apple). Can be added later.
- SSO / enterprise SAML.
- Passwordless magic-link login.
- Two-factor authentication UI.
- Account settings page (change password, manage linked accounts, delete
  account). A profile page already exists at `/wp-admin/profile.php` and can
  be styled separately.
- Rate-limit admin UI. Transient keys are readable via the DB if we need to
  clear them during testing.

## Open questions

None currently — all clarifications resolved in brainstorming session.

## Implementation checkpoints

Suggested order for the implementation plan (writing-plans skill will refine):

1. Plugin-side: `WpSessionBridge` class + `wp_session=1` handling on 4 existing endpoints + new `/auth/login` endpoint.
2. Theme-side: modal shell + CSS; `view-login` + trigger helper; Log In button wiring on logo bar.
3. `auth-forms.js` + `auth-modal.js`: modal state machine and form POST plumbing.
4. Username/password login end-to-end — test.
5. `view-register` + `auth-forms.js` registration submit + debounced checks.
6. Registration end-to-end — test.
7. `auth-google.js`: GSI init, button render, WebView detection.
8. Google sign-in (account exists) end-to-end — test.
9. `view-link`: link-to-existing + create-from-Google paths.
10. Link flow end-to-end — test.
11. `view-forgot` + `view-reset` + `?bbj_rp=…` handler.
12. Forgot / reset end-to-end — test.
13. Accessibility pass: focus trap, `role=dialog`, error announcements.
14. Production cookie audit: SameSite, Secure, HttpOnly on staging.

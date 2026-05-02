# Login/Register Modals — pre-ship checklist

Run this before merging `feature/bbj-v2-theme` → staging, then again on
staging before prod. Tick off in a copy or PR description.

## Local dev checks (bbj.localhost)

- [x] Flow 1 — username/password login reloads, logo bar shows avatar/name.
- [x] Flow 2 — wrong password shows "Incorrect username or password." and
      re-enables the submit button.
- [x] Flow 4 — register creates a new WP user and auto-logs in. Inline
      username/email availability checks fire after 500ms.
- [ ] Flow 3 — "Keep me logged in" checked → cookie survives browser quit;
      unchecked → cookie is a session cookie.
- [x] Flow 9 — Google sign-in for an unlinked account routes to view-link
      (verified on staging).
- [ ] Flow 10 — "Continue as New User" on view-link creates a WP user.
- [x] Flow 11 — forgot-password returns success. (Cannot verify mail
      delivery on `bbj.localhost` without SMTP; check staging instead.)
- [ ] Flow 12 — reset link on `?bbj_rp=1&key=…&login=…` auto-opens the
      reset view and completes a password change.
- [x] WebView dev flag — `?bbj_force_webview=1` hides Google button, shows
      the "open in Chrome" notice; form still works.

## Staging checks (stg-wp.bigbrotherjunkies.com)

- [x] Theme activated.
- [x] `bbjd_google_client_id` and `bbj_recaptcha_site_key` options present.
- [x] Google sign-in (existing account) — page reloads logged in.
- [ ] Google sign-in (new email that has no BBJ match) → view-link renders
      the Google profile card, link-to-existing succeeds.
- [ ] Forgot-password email actually arrives.
- [ ] Reset link from that email completes the flow.

## Cookie audit (DevTools → Application → Cookies)

- [ ] `wordpress_logged_in_…` — `HttpOnly` ☑, `Secure` ☑ (prod/staging),
      `SameSite=Lax` ☑.
- [ ] `wordpress_sec_…` — `HttpOnly` ☑, `Secure` ☑, `SameSite=Lax` ☑.
- [ ] No residual JWT cookies (`bbj_token`) from Next.js experiments
      coexisting with a WP session.
- [ ] `bbj_force_webview` (dev only, via `?bbj_force_webview=1`) — has
      `SameSite=Lax`, `path=/`, expires on browser close.

## Next.js app regression (bbj-app)

- [ ] Login via the React UI at `../bbj-app/` still issues a JWT (no WP
      cookie) and returns the same response shape as before.
- [ ] `/jwt-auth/v1/token` and `/bbjd/v1/auth/me` still work for the React
      UI when no `wp_session` flag is sent.
- [ ] Verify by logging into Next.js and confirming the existing user
      interface (avatar, display name) still shows as expected.

## Real-world WebView capture (do before trusting the regex)

- [ ] On an iPhone, open a Facebook post linking to the site. Open the
      link inside FB.
- [ ] Capture the full User-Agent string (easiest: visit a UA-echo URL).
- [ ] Confirm the UA matches `/FBAN|FBAV|Instagram|Line\//` in
      `auth-google.js`.
- [ ] If it doesn't, add the missing pattern to the regex AND update
      `.claude/projects/bbj/memory/project_login_reliability.md` with the
      new token.
- [ ] Verify the fallback notice appears inside the FB browser and that
      username/password login still works.

## Accessibility

- [ ] Screen reader (NVDA, VoiceOver, or Narrator) announces dialog title
      on open and on view switch (login → register → link → forgot).
- [ ] Form errors announced via `role="alert"` on submit failure.
- [ ] Forgot-password success announced via `role="status"`/`aria-live`.
- [ ] Focus returns to the Log In button when the modal closes.
- [ ] Tab cycle stays inside the dialog; Shift+Tab wraps backwards.
- [ ] Esc closes. Backdrop click closes. Dragging text out of a field
      does NOT close (regression test for the `mousedown`-tracking fix).

## Security

- [ ] reCAPTCHA v3 token present on every register submit (DevTools →
      Network → `/auth/register` payload has `recaptcha_token`).
- [ ] Rate limit — fail a login 10 times from the same IP → 11th attempt
      returns `429 rate_limited`. Transient `bbj_login_fails_<md5 ip>`
      appears in `wp_options`. Successful login clears the transient.
- [ ] Nonce expiry — with modal open 24h+, submit a form → "Session
      expired. Please refresh the page." (from `WpSessionBridge::verifyNonce`).
- [ ] All 5 auth-issuing endpoints enforce nonce when `wp_session=1` is
      present: `/auth/login`, `/auth/google`, `/auth/link-google`,
      `/auth/create-from-google`, `/auth/register`, `/auth/reset-password`.

## Performance / regression

- [ ] First paint unaffected for logged-in users (modal + auth scripts are
      only enqueued when `!is_user_logged_in()`).
- [ ] Logged-out HTML weight increase: modal ~2-3KB gzipped, scripts
      ~4-6KB gzipped, GIS script loaded on demand (first click of a view
      that shows it).
- [ ] Ads still render / hide per role.
- [ ] AIOSEO canonical, JSON-LD, and meta tags unchanged on any template
      the theme renders.
- [ ] Breeze page cache invalidates after first login (or force-purge via
      Cloudways dashboard after deploy).

## Smoke before merging prod

Run flows 1, 4, 8, 9, 11 on staging. If any regress, DO NOT merge to master.

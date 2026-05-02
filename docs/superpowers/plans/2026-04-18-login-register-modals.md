# Login & Registration Modals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a four-view auth modal (login, register, link-account, forgot/reset password) for the bbj-v2 theme that uses WordPress-native cookies, supports Google Sign-In, and stays reliable inside Facebook/Instagram in-app browsers.

**Architecture:** Extend the existing `bigbrotherjunkies-data` plugin endpoints with an opt-in `wp_session=1` flag that calls `wp_set_auth_cookie()` alongside the JWT response, then build a global modal in the theme that POSTs to those endpoints and reloads the page on success. The Next.js app (which doesn't send `wp_session`) continues to work unchanged.

**Tech Stack:** PHP 8+ (WordPress theme + plugin), vanilla JS (ES modules), Tailwind (compiled to `build/style.css`), Google Identity Services (GIS), reCAPTCHA v3.

**Design spec:** `docs/superpowers/specs/2026-04-18-login-register-modals-design.md`

**Testing note:** This theme has no PHPUnit or Jest setup. Each task's "test" is either a `curl` command (for endpoints) or a browser verification step. Don't skip them — they're how we catch regressions before shipping.

---

## Phase 1 — Plugin-side session bridge

### Task 1: Create `WpSessionBridge` class

Creates the one-liner class that every auth-issuing endpoint will call to optionally set a WordPress auth cookie.

**Files:**
- Create: `wp-content/plugins/bigbrotherjunkies-data/src/Auth/WpSessionBridge.php`

- [ ] **Step 1: Write the class**

```php
<?php

namespace BigBrotherJunkies\Data\Auth;

/**
 * Bridges the plugin's JWT-based auth endpoints with WordPress-native
 * session cookies. When a request carries wp_session=1, we call
 * wp_set_auth_cookie() so is_user_logged_in() works server-side for
 * the PHP theme. The Next.js app doesn't send the flag, so its flow
 * is unchanged.
 */
class WpSessionBridge
{
    /**
     * If the request opts in, set the WordPress auth cookie for $userId.
     *
     * @param int              $userId    User ID to log in.
     * @param bool             $remember  14-day cookie vs session cookie.
     * @param \WP_REST_Request $request   Incoming REST request.
     * @return bool  true if the cookie was set, false otherwise.
     */
    public static function maybeSetAuthCookie(int $userId, bool $remember, \WP_REST_Request $request): bool
    {
        if ((int) $request->get_param('wp_session') !== 1) {
            return false;
        }
        if ($userId <= 0 || !get_userdata($userId)) {
            return false;
        }
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, $remember, is_ssl());
        return true;
    }

    /**
     * Verify the theme-scoped auth nonce when wp_session=1 is requested.
     * Returns null on success, WP_Error on failure. Endpoint callers should
     * call this first and return the error if non-null.
     */
    public static function verifyNonce(\WP_REST_Request $request): ?\WP_Error
    {
        if ((int) $request->get_param('wp_session') !== 1) {
            return null; // Only enforce for theme consumers opting into WP session.
        }
        $nonce = $request->get_header('X-WP-Nonce') ?: (string) $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'bbj_auth')) {
            return new \WP_Error(
                'invalid_nonce',
                __('Session expired. Please refresh the page.', 'bigbrotherjunkies-data'),
                ['status' => 403]
            );
        }
        return null;
    }

    /**
     * Register the SameSite=Lax enforcement filter once.
     * Call this from Plugin::initAuth() during bootstrap.
     */
    public static function init(): void
    {
        add_filter('set_auth_cookie', [self::class, 'addSameSite'], 10, 6);
        add_filter('set_logged_in_cookie', [self::class, 'addSameSite'], 10, 6);
    }

    /**
     * Append SameSite=Lax to auth cookie headers. WordPress core does not
     * set SameSite by default, and some WebViews require Lax for survival.
     *
     * @param string $cookie  Serialized cookie string.
     */
    public static function addSameSite($cookie): string
    {
        if (stripos((string) $cookie, 'samesite=') !== false) {
            return (string) $cookie;
        }
        return rtrim((string) $cookie, ';') . '; SameSite=Lax';
    }
}
```

- [ ] **Step 2: Verify file parses**

Run: `php -l wp-content/plugins/bigbrotherjunkies-data/src/Auth/WpSessionBridge.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Auth/WpSessionBridge.php
git commit -m "feat(plugin): add WpSessionBridge for opt-in wp_set_auth_cookie on REST auth"
```

---

### Task 2: Register `WpSessionBridge::init()` in `Plugin.php`

Wires the SameSite filter on boot.

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php`

- [ ] **Step 1: Add import at top**

Find the existing `use BigBrotherJunkies\Data\Auth\AuthManager;` line (around line 44) and add below it:

```php
use BigBrotherJunkies\Data\Auth\WpSessionBridge;
```

- [ ] **Step 2: Call `init()` in `initAuth()`**

Find `private function initAuth(): void` (around line 334). Change body from:

```php
private function initAuth(): void
{
    AuthManager::getInstance()->init();
}
```

to:

```php
private function initAuth(): void
{
    AuthManager::getInstance()->init();
    WpSessionBridge::init();
}
```

- [ ] **Step 3: Verify hook is registered**

Browse `http://bbj.localhost/wp-admin/` once to force plugin bootstrap, then:

```bash
curl -s -I http://bbj.localhost/wp-login.php | grep -i samesite
```

Expected: cookie headers include `SameSite=Lax`. (Login cookies only appear after a real login; for now we're confirming the filter runs without fatal error.)

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php
git commit -m "feat(plugin): bootstrap WpSessionBridge filters in initAuth"
```

---

### Task 3: Add `wp_session=1` bridge to `/auth/google`

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php`

- [ ] **Step 1: Add import for the bridge (top of file, near line 8)**

Add after `use BigBrotherJunkies\Data\Auth\Integrations\MailPoetSubscriber;`:

```php
use BigBrotherJunkies\Data\Auth\WpSessionBridge;
```

- [ ] **Step 2: Add nonce verification at the top of `handleGoogleAuth()`**

Find `handleGoogleAuth()` (around line 245). At the very top of the method body (before any existing logic), add:

```php
        if ($err = WpSessionBridge::verifyNonce($request)) {
            return $err;
        }
```

This rejects `wp_session=1` requests that lack a valid `bbj_auth` nonce. When `wp_session` is absent (Next.js app), it returns null and the existing flow continues unchanged.

- [ ] **Step 3: Wire the bridge into `handleGoogleAuth()`**

Find the block where `$token` is generated and the success response is built (around line 290). Just before `return new \WP_REST_Response([...`, insert:

```php
        // Opt-in WP-native session for PHP theme consumers.
        WpSessionBridge::maybeSetAuthCookie((int) $user->ID, (bool) $rememberMe, $request);
```

- [ ] **Step 4: Add `wp_session` arg to the route registration**

Find the `register_rest_route(self::NAMESPACE, '/auth/google', ...)` block (around line 68). Inside the `'args'` array, after the `remember_me` arg, add:

```php
                'wp_session' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ],
```

- [ ] **Step 5: Manual test — valid credential without the flag still works**

Since we don't have a real Google credential handy, confirm the endpoint still parses by checking the route registration:

```bash
curl -s http://bbj.localhost/wp-json/bbjd/v1/ | python -m json.tool | grep -A1 '/auth/google'
```

Expected: `/auth/google` appears with POST method in the output.

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php
git commit -m "feat(plugin): bridge /auth/google to WP session when wp_session=1"
```

---

### Task 4: Add `wp_session=1` bridge to `/auth/link-google`

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php`

- [ ] **Step 1: Add nonce verification at the top of `handleLinkGoogle()`**

Find `handleLinkGoogle()` (around line 771). At the very top of the method body, add:

```php
        if ($err = WpSessionBridge::verifyNonce($request)) {
            return $err;
        }
```

- [ ] **Step 2: Wire the bridge into `handleLinkGoogle()`**

Locate the section where the token is generated (around line 818-820). Just before `return new \WP_REST_Response([...` for the success case, insert:

```php
        WpSessionBridge::maybeSetAuthCookie((int) $user->ID, (bool) $rememberMe, $request);
```

- [ ] **Step 3: Add `wp_session` arg to the route**

Find `register_rest_route(self::NAMESPACE, '/auth/link-google', ...)` (around line 173). In the `'args'` array, add:

```php
                'wp_session' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ],
```

- [ ] **Step 4: Confirm route parses**

```bash
curl -s http://bbj.localhost/wp-json/bbjd/v1/ | python -m json.tool | grep -A1 '/auth/link-google'
```

Expected: appears in output without errors.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php
git commit -m "feat(plugin): bridge /auth/link-google to WP session when wp_session=1"
```

---

### Task 5: Add `wp_session=1` bridge to `/auth/create-from-google`

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php`

- [ ] **Step 1: Add nonce verification at the top of `handleCreateFromGoogle()`**

Find `handleCreateFromGoogle()` (around line 844). At the very top of the method body, add:

```php
        if ($err = WpSessionBridge::verifyNonce($request)) {
            return $err;
        }
```

- [ ] **Step 2: Wire the bridge into `handleCreateFromGoogle()`**

Locate the section where the token is generated (around line 897-900). Just before the success `return new \WP_REST_Response([...`, insert:

```php
        WpSessionBridge::maybeSetAuthCookie((int) $user->ID, (bool) $rememberMe, $request);
```

- [ ] **Step 3: Add `wp_session` arg to the route**

Find `register_rest_route(self::NAMESPACE, '/auth/create-from-google', ...)` (around line 200). In the `'args'` array, add:

```php
                'wp_session' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ],
```

- [ ] **Step 4: Confirm route parses**

```bash
curl -s http://bbj.localhost/wp-json/bbjd/v1/ | python -m json.tool | grep -A1 '/auth/create-from-google'
```

Expected: appears without errors.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php
git commit -m "feat(plugin): bridge /auth/create-from-google to WP session when wp_session=1"
```

---

### Task 6: Add `wp_session=1` bridge to `/auth/register`

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php`

- [ ] **Step 1: Add nonce verification at the top of `handleRegister()`**

Find `handleRegister()` (around line 323). At the very top of the method body (even before reCAPTCHA verification), add:

```php
        if ($err = WpSessionBridge::verifyNonce($request)) {
            return $err;
        }
```

- [ ] **Step 2: Wire the bridge into `handleRegister()`**

Find the success return block (around line 470) where `'token' => $token` is included. Just before that `return new \WP_REST_Response([...` line, insert:

```php
        WpSessionBridge::maybeSetAuthCookie((int) $userId, true, $request);
```

(Registration always implies "remember me" — user just created the account.)

- [ ] **Step 3: Add `wp_session` arg to the route**

Find `register_rest_route(self::NAMESPACE, '/auth/register', ...)` (around line 87). In the `'args'` array, add:

```php
                'wp_session' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ],
```

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php
git commit -m "feat(plugin): bridge /auth/register to WP session when wp_session=1"
```

---

### Task 7: Add `wp_session=1` bridge to `/auth/reset-password`

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php`

- [ ] **Step 1: Add nonce verification at the top of `handleResetPassword()`**

Find `handleResetPassword()` (around line 530). At the very top of the method body, add:

```php
        if ($err = WpSessionBridge::verifyNonce($request)) {
            return $err;
        }
```

- [ ] **Step 2: Wire the bridge into `handleResetPassword()`**

Locate the success response. Before the return, insert:

```php
        WpSessionBridge::maybeSetAuthCookie((int) $user->ID, true, $request);
```

If the existing handler doesn't already have a `$user` variable on the success path, find the `check_password_reset_key()` call — its return value IS the user. Capture it as `$user` before calling `reset_password()` if needed. Show the full resulting block in your commit.

- [ ] **Step 3: Add `wp_session` arg to the route**

Find `register_rest_route(self::NAMESPACE, '/auth/reset-password', ...)` (around line 123). In the `'args'` array, add:

```php
                'wp_session' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ],
```

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php
git commit -m "feat(plugin): bridge /auth/reset-password to WP session when wp_session=1"
```

---

### Task 8: Add new `/auth/login` endpoint (username/password → WP cookie)

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php`

- [ ] **Step 1: Add the route registration**

Inside `registerRoutes()`, after the existing `/auth/check-username` block (around line 172), add:

```php
        // Username/password login → WordPress native session cookie.
        // Intended for the bbj-v2 PHP theme. Next.js continues to use /jwt-auth/v1/token.
        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'handleLogin'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'remember_me' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
                ],
                'wp_session' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                ],
            ],
        ]);
```

- [ ] **Step 2: Add the handler method**

Below the existing `handleCheckUsername()` method (search for `public function handleCheckUsername`), add:

```php
    /**
     * Handle username/password login — WP-native session, no JWT.
     * Rate-limited to 10 failed attempts per IP per 15 minutes.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleLogin(\WP_REST_Request $request)
    {
        if ($err = WpSessionBridge::verifyNonce($request)) {
            return $err;
        }

        // Rate limit by IP: 10 failed attempts per 15 minutes.
        $ip = $this->clientIp();
        $rlKey = 'bbj_login_fails_' . md5($ip);
        $failCount = (int) get_transient($rlKey);
        if ($failCount >= 10) {
            return new \WP_Error(
                'rate_limited',
                __('Too many failed attempts. Please wait a few minutes and try again.', 'bigbrotherjunkies-data'),
                ['status' => 429]
            );
        }

        $username = $request->get_param('username');
        $password = $request->get_param('password');
        $remember = (bool) $request->get_param('remember_me');

        if (!$username || !$password) {
            return new \WP_Error('missing_credentials', __('Username and password are required.', 'bigbrotherjunkies-data'), ['status' => 400]);
        }

        $creds = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ];
        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            set_transient($rlKey, $failCount + 1, 15 * MINUTE_IN_SECONDS);
            return new \WP_Error(
                'invalid_credentials',
                __('Incorrect username or password.', 'bigbrotherjunkies-data'),
                ['status' => 401]
            );
        }

        // Clear the rate-limit counter on success.
        delete_transient($rlKey);

        WpSessionBridge::maybeSetAuthCookie((int) $user->ID, $remember, $request);

        return new \WP_REST_Response([
            'success' => true,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'avatar' => AvatarUploader::getAvatarUrl($user->ID),
                'roles' => array_values((array) $user->roles),
            ],
        ], 200);
    }

    /**
     * Best-effort client IP detection. Respects Cloudflare and common
     * proxy headers, falls back to REMOTE_ADDR.
     */
    private function clientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', (string) $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
```

- [ ] **Step 3: Manual test — wrong credentials fail cleanly**

```bash
curl -s -X POST http://bbj.localhost/wp-json/bbjd/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"nobody","password":"wrong"}'
```

Expected: JSON response with `{"code":"invalid_credentials", ...}` and no fatal error.

- [ ] **Step 4: Manual test — correct credentials return success and set cookie**

Replace `YOUR_USERNAME` and `YOUR_PASSWORD` with real credentials for a test user in your local DB:

```bash
curl -s -X POST http://bbj.localhost/wp-json/bbjd/v1/auth/login \
  -H "Content-Type: application/json" \
  -c /tmp/bbj-cookies.txt \
  -d '{"username":"YOUR_USERNAME","password":"YOUR_PASSWORD","remember_me":true}'

cat /tmp/bbj-cookies.txt | grep wordpress_logged_in
```

Expected: response has `"success":true`, cookie jar contains a `wordpress_logged_in_…` entry.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/AuthRoutes.php
git commit -m "feat(plugin): add /auth/login endpoint (WP-native session, no JWT)"
```

---

## Phase 2 — Theme modal foundation

### Task 9: Add modal CSS to `src/css/style.css`

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/css/style.css`

- [ ] **Step 1: Append modal classes inside `@layer components`**

Find the closing `}` of `@layer components` (near the spoiler-bar image classes). Just before the closing brace, add:

```css
  /* Auth modal — shared chrome for login/register/link/forgot/reset views. */
  .bbj-modal {
    @apply fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4;
  }
  .bbj-modal.is-open {
    @apply flex;
  }
  .bbj-modal-dialog {
    @apply bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto;
  }
  .bbj-modal-header {
    @apply flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700;
  }
  .bbj-modal-title {
    @apply text-xl font-display text-primary-500 dark:text-primary-400;
  }
  .bbj-modal-close {
    @apply p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded;
  }
  .bbj-modal-body {
    @apply p-4;
  }
  .bbj-modal-view {
    @apply space-y-4 hidden;
  }
  .bbj-modal-view.is-active {
    @apply block;
  }

  /* Field-level error text shown beneath inputs. */
  .bbj-field-error {
    @apply mt-1 text-sm text-red-500;
  }
  /* Form-level error banner above a form. */
  .bbj-form-error {
    @apply p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-600 dark:text-red-400 text-sm;
  }
  /* Neutral divider "or" between auth methods. */
  .bbj-divider {
    @apply relative text-center;
  }
  .bbj-divider::before {
    content: "";
    @apply absolute left-0 right-0 top-1/2 border-t border-gray-200 dark:border-gray-700;
  }
  .bbj-divider > span {
    @apply relative inline-block px-2 bg-white dark:bg-gray-800 text-sm text-gray-500;
  }
```

- [ ] **Step 2: Rebuild Tailwind**

```bash
cd wp-content/themes/bbj-v2-theme && npm run build
```

Expected: `Done in XXXms.` output, no errors.

- [ ] **Step 3: Confirm compiled CSS contains the new classes**

```bash
grep -c "bbj-modal" wp-content/themes/bbj-v2-theme/build/style.css
```

Expected: non-zero count (at least 8+ matches).

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/css/style.css wp-content/themes/bbj-v2-theme/build/style.css
git commit -m "feat(theme): add auth modal component classes"
```

---

### Task 10: Create `inc/auth.php` with trigger helper and modal loader

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/inc/auth.php`
- Modify: `wp-content/themes/bbj-v2-theme/functions.php`

- [ ] **Step 1: Create the helper file**

```php
<?php
/**
 * Auth modal: trigger helper, modal loader, reset-link handler.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emit a button that opens the auth modal.
 * Returns empty output when the user is already logged in.
 *
 * @param string $label  Visible label ("Log In", "Sign up", "Log in to comment", …).
 * @param string $view   Initial view: login | register | forgot.
 * @param string $class  Extra CSS classes to apply to the button.
 */
function bbj_v2_auth_trigger(string $label, string $view = 'login', string $class = ''): void
{
    if (is_user_logged_in()) {
        return;
    }
    $allowed = ['login', 'register', 'forgot'];
    if (!in_array($view, $allowed, true)) {
        $view = 'login';
    }
    printf(
        '<button type="button" class="%s" data-bbj-auth-open="%s">%s</button>',
        esc_attr(trim($class)),
        esc_attr($view),
        esc_html($label)
    );
}

/**
 * Detect a password-reset landing URL and stash its key/login on the body
 * as data attributes so the modal JS can auto-open to view-reset.
 *
 * Reset emails link to: https://site.com/?bbj_rp=1&key=...&login=...
 */
add_filter('body_class', function (array $classes): array {
    if (!empty($_GET['bbj_rp']) && !empty($_GET['key']) && !empty($_GET['login'])) {
        $classes[] = 'bbj-reset-password-landing';
    }
    return $classes;
});

/**
 * Pass the reset-password key/login into the page as JSON so the modal JS
 * can read them. Output right after <body> so the markup is visible to JS
 * before wp_footer() fires.
 */
add_action('wp_body_open', function (): void {
    if (empty($_GET['bbj_rp']) || empty($_GET['key']) || empty($_GET['login'])) {
        return;
    }
    $payload = wp_json_encode([
        'key'   => sanitize_text_field(wp_unslash($_GET['key'])),
        'login' => sanitize_text_field(wp_unslash($_GET['login'])),
    ]);
    echo '<script id="bbj-reset-payload" type="application/json">' . $payload . '</script>';
});
```

- [ ] **Step 2: Require `inc/auth.php` from `functions.php`**

Find the existing `require_once` lines for other `inc/*.php` files (likely loading `setup.php`, `enqueue.php`, `template-functions.php`, `dark-mode.php`). Add below them:

```php
require_once BBJ_V2_THEME_PATH . '/inc/auth.php';
```

- [ ] **Step 3: Manual test — helper renders while logged out**

In any template (temporarily add to `index.php` before `get_header()` is NOT right — instead add to a scratch location or just call in a dev console). For a quick check, add this temporary line to `footer.php` inside the `<footer>` element, save, then reload:

```php
<?php bbj_v2_auth_trigger('TEST', 'login', 'bg-red-500 text-white p-2'); ?>
```

Load `http://bbj.localhost/` as a logged-out user → button appears.
Log in via `wp-admin` → reload → button disappears.
**Remove the test line before committing.**

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/auth.php wp-content/themes/bbj-v2-theme/functions.php
git commit -m "feat(theme): add auth trigger helper and reset-link body attribute"
```

---

### Task 11: Create modal shell `template-parts/auth/modal.php` and load it from `footer.php`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/auth/modal.php`
- Modify: `wp-content/themes/bbj-v2-theme/footer.php`

- [ ] **Step 1: Create the modal shell**

```php
<?php
/**
 * Auth modal — shared chrome + slots for each view.
 * Hidden by default; JS adds .is-open on the .bbj-modal root.
 * Only rendered for logged-out users (saves ~2KB for authed users).
 */

if (!defined('ABSPATH')) {
    exit;
}
if (is_user_logged_in()) {
    return;
}
?>
<div id="bbj-auth-modal"
     class="bbj-modal"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     aria-labelledby="bbj-auth-modal-title">
    <div class="bbj-modal-dialog" role="document">
        <div class="bbj-modal-header">
            <h2 id="bbj-auth-modal-title" class="bbj-modal-title" data-bbj-auth-title>
                <?php esc_html_e('Log In', 'bbj-v2-theme'); ?>
            </h2>
            <button type="button" class="bbj-modal-close" data-bbj-auth-close aria-label="<?php esc_attr_e('Close', 'bbj-v2-theme'); ?>">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="bbj-modal-body">
            <?php get_template_part('template-parts/auth/view-login'); ?>
            <?php get_template_part('template-parts/auth/view-register'); ?>
            <?php get_template_part('template-parts/auth/view-link'); ?>
            <?php get_template_part('template-parts/auth/view-forgot'); ?>
            <?php get_template_part('template-parts/auth/view-reset'); ?>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Stub the five view partials so `get_template_part` doesn't silently fail**

Create each of these as an empty file with just the ABSPATH guard:

`view-login.php`:

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view is-active" data-bbj-auth-view="login">
    <p class="text-gray-500 dark:text-gray-400">Login view coming soon.</p>
</section>
```

`view-register.php`:

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="register">
    <p class="text-gray-500 dark:text-gray-400">Register view coming soon.</p>
</section>
```

`view-link.php`:

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="link">
    <p class="text-gray-500 dark:text-gray-400">Link-account view coming soon.</p>
</section>
```

`view-forgot.php`:

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="forgot">
    <p class="text-gray-500 dark:text-gray-400">Forgot-password view coming soon.</p>
</section>
```

`view-reset.php`:

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="reset">
    <p class="text-gray-500 dark:text-gray-400">Reset-password view coming soon.</p>
</section>
```

- [ ] **Step 3: Load the modal from `footer.php`**

Open `wp-content/themes/bbj-v2-theme/footer.php`. Find the line that calls `wp_footer()` (near the end). Immediately **before** `<?php wp_footer(); ?>`, insert:

```php
<?php get_template_part('template-parts/auth/modal'); ?>
```

- [ ] **Step 4: Manual test — modal markup appears on every page while logged out**

```bash
curl -s http://bbj.localhost/ | grep -c 'id="bbj-auth-modal"'
```

Expected: `1`.

Logged-in check (log in via `wp-admin`, then):

```bash
curl -s -b /tmp/bbj-cookies.txt http://bbj.localhost/ | grep -c 'id="bbj-auth-modal"'
```

Expected: `0` (no modal for authed users).

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/auth/ wp-content/themes/bbj-v2-theme/footer.php
git commit -m "feat(theme): scaffold auth modal shell with view stubs"
```

---

### Task 12: Update logo bar "Log In" button to use the trigger helper

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/header/logo-bar.php`

- [ ] **Step 1: Replace the existing anchor with the helper call**

Find the `<?php else : ?>` block (the logged-out branch) inside the `is_user_logged_in()` check. Replace the entire anchor:

```php
<a href="<?php echo esc_url(wp_login_url(home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? '/')))); ?>"
   class="flex items-center gap-1 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition">
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span class="text-sm font-osw uppercase tracking-wider"><?php esc_html_e('Log In', 'bbj-v2-theme'); ?></span>
</a>
```

with:

```php
<button type="button"
        data-bbj-auth-open="login"
        class="flex items-center gap-1 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition">
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span class="text-sm font-osw uppercase tracking-wider"><?php esc_html_e('Log In', 'bbj-v2-theme'); ?></span>
</button>
```

- [ ] **Step 2: Confirm output**

```bash
curl -s http://bbj.localhost/ | grep 'data-bbj-auth-open="login"' | head -1
```

Expected: the button line is present.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/header/logo-bar.php
git commit -m "feat(theme): wire logo bar Log In to data-bbj-auth-open trigger"
```

---

### Task 13: Write `auth-modal.js` — open/close, view switch, focus trap

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/js/auth-modal.js`

- [ ] **Step 1: Write the module**

```js
/**
 * Auth modal controller.
 *
 * Responsibilities:
 *  - open/close the modal (toggles .is-open + aria-hidden)
 *  - switch between views (login/register/link/forgot/reset)
 *  - trap focus within the dialog while open
 *  - close on Esc / backdrop click / [data-bbj-auth-close]
 *
 * Consumers dispatch events rather than importing — any [data-bbj-auth-open]
 * button opens the modal; JS modules can call window.BBJAuthModal.open(view).
 */
(function () {
    'use strict';

    const modal = document.getElementById('bbj-auth-modal');
    if (!modal) return; // Logged-in users have no modal.

    const titleEl = modal.querySelector('[data-bbj-auth-title]');
    const views = modal.querySelectorAll('[data-bbj-auth-view]');
    const focusableSel = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const titles = {
        login:    'Log In',
        register: 'Create Account',
        link:     'Link Your Account',
        forgot:   'Reset Password',
        reset:    'Set a New Password',
    };

    let lastFocus = null;

    function showView(name) {
        let found = false;
        views.forEach(v => {
            const match = v.getAttribute('data-bbj-auth-view') === name;
            v.classList.toggle('is-active', match);
            if (match) found = true;
        });
        if (!found) {
            showView('login');
            return;
        }
        if (titleEl && titles[name]) {
            titleEl.textContent = titles[name];
        }
        const firstInput = modal.querySelector('.bbj-modal-view.is-active input:not([type="hidden"]), .bbj-modal-view.is-active button');
        if (firstInput) firstInput.focus();
        modal.dispatchEvent(new CustomEvent('bbj-auth:view', { detail: { view: name } }));
    }

    function open(view) {
        lastFocus = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        showView(view || 'login');
    }

    function close() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    // Delegated handlers.
    document.addEventListener('click', function (e) {
        const opener = e.target.closest('[data-bbj-auth-open]');
        if (opener) {
            e.preventDefault();
            open(opener.getAttribute('data-bbj-auth-open'));
            return;
        }
        const switcher = e.target.closest('[data-bbj-auth-switch]');
        if (switcher && modal.contains(switcher)) {
            e.preventDefault();
            showView(switcher.getAttribute('data-bbj-auth-switch'));
            return;
        }
        if (e.target.closest('[data-bbj-auth-close]')) {
            e.preventDefault();
            close();
            return;
        }
        // Backdrop click: target is the modal root itself.
        if (e.target === modal) close();
    });

    // Esc closes.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            e.preventDefault();
            close();
            return;
        }
        // Focus trap: Tab cycling within dialog.
        if (e.key === 'Tab' && modal.classList.contains('is-open')) {
            const focusables = Array.from(modal.querySelectorAll(focusableSel)).filter(el => !el.hasAttribute('aria-hidden'));
            if (focusables.length === 0) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    // Auto-open on password-reset landing.
    const resetPayload = document.getElementById('bbj-reset-payload');
    if (resetPayload) {
        try {
            const data = JSON.parse(resetPayload.textContent);
            modal.dataset.resetKey = data.key;
            modal.dataset.resetLogin = data.login;
            open('reset');
        } catch (_) { /* ignore bad payload */ }
    }

    // Expose a minimal API for other modules.
    window.BBJAuthModal = { open, close, showView };
})();
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/auth-modal.js
git commit -m "feat(theme): add auth-modal.js controller with focus trap"
```

---

### Task 14: Enqueue auth JS and localize a nonce + API base

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/enqueue.php`

- [ ] **Step 1: Add an enqueue block for auth assets**

Open `inc/enqueue.php`. Inside the main enqueue function (likely `bbj_v2_enqueue_assets` on `wp_enqueue_scripts`), after the existing theme JS enqueues, add:

```php
    // Auth assets — anonymous users only.
    if (!is_user_logged_in()) {
        wp_enqueue_script(
            'bbj-v2-auth-modal',
            get_template_directory_uri() . '/src/js/auth-modal.js',
            [],
            filemtime(BBJ_V2_THEME_PATH . '/src/js/auth-modal.js'),
            true
        );
        wp_localize_script('bbj-v2-auth-modal', 'BBJAuth', [
            'api'     => esc_url_raw(rest_url('bbjd/v1/')),
            'nonce'   => wp_create_nonce('bbj_auth'),
            'debug'   => defined('WP_DEBUG') && WP_DEBUG,
            'homeUrl' => esc_url_raw(home_url('/')),
        ]);
    }
```

- [ ] **Step 2: Manual test — JS loads and `BBJAuth` is on window**

Load `http://bbj.localhost/` as logged-out user. Open DevTools Console:

```js
typeof BBJAuth
```

Expected: `"object"`

```js
typeof BBJAuthModal.open
```

Expected: `"function"`

Click the Log In button in the header. Expected: modal appears showing the stub "Login view coming soon." Click the `×` button, press Esc, and click the backdrop — each should close.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/enqueue.php
git commit -m "feat(theme): enqueue auth-modal.js and localize nonce for logged-out users"
```

---

## Phase 3 — Login view and form submission

### Task 15: Build `view-login.php` markup

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/auth/view-login.php`

- [ ] **Step 1: Replace the stub with the real login view**

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view is-active" data-bbj-auth-view="login" aria-labelledby="bbj-auth-modal-title">

    <div data-bbj-auth-webview-notice class="hidden bbj-form-error">
        <?php esc_html_e(
            "Google sign-in doesn't work inside the Facebook or Instagram browser. Tap the ••• menu and choose \"Open in Chrome\" or \"Open in Safari.\"",
            'bbj-v2-theme'
        ); ?>
    </div>

    <div data-bbj-google-login-container></div>

    <div class="bbj-divider"><span><?php esc_html_e('or', 'bbj-v2-theme'); ?></span></div>

    <form data-bbj-auth-form="login" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

        <div>
            <label for="bbj-login-username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Username or Email', 'bbj-v2-theme'); ?>
            </label>
            <input type="text" id="bbj-login-username" name="username" required autocomplete="username"
                   class="input">
        </div>

        <div>
            <label for="bbj-login-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Password', 'bbj-v2-theme'); ?>
            </label>
            <input type="password" id="bbj-login-password" name="password" required autocomplete="current-password"
                   class="input">
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember_me" checked
                       class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-500 focus:ring-primary-500">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    <?php esc_html_e('Keep me logged in', 'bbj-v2-theme'); ?>
                </span>
            </label>
            <button type="button" data-bbj-auth-switch="forgot" class="text-sm text-primary-500 hover:underline">
                <?php esc_html_e('Forgot password?', 'bbj-v2-theme'); ?>
            </button>
        </div>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Log In', 'bbj-v2-theme'); ?>
        </button>
    </form>

    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        <?php esc_html_e("Don't have an account?", 'bbj-v2-theme'); ?>
        <button type="button" data-bbj-auth-switch="register" class="text-primary-500 hover:underline font-medium">
            <?php esc_html_e('Sign up', 'bbj-v2-theme'); ?>
        </button>
    </p>
</section>
```

- [ ] **Step 2: Manual test — markup renders**

Reload the homepage, click Log In → view-login appears with form inputs styled. "Sign up" link in footer clicking switches to the register stub. "Forgot password?" switches to forgot stub.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/auth/view-login.php
git commit -m "feat(theme): build view-login.php markup"
```

---

### Task 16: Write `auth-forms.js` with login submit handler

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/js/auth-forms.js`
- Modify: `wp-content/themes/bbj-v2-theme/inc/enqueue.php`

- [ ] **Step 1: Create the forms module**

```js
/**
 * Auth form submission handlers.
 *
 * Delegated listener on [data-bbj-auth-form]; routes by the form's data
 * attribute value. On successful auth we reload — server is the source of
 * truth for "logged in" state.
 */
(function () {
    'use strict';

    if (typeof BBJAuth === 'undefined') return; // Config missing — theme enqueue issue.

    const modal = document.getElementById('bbj-auth-modal');
    if (!modal) return;

    /**
     * POST JSON to an endpoint with the page-scoped nonce attached.
     * Returns { ok, status, data }.
     */
    async function postJSON(path, body) {
        const url = BBJAuth.api + path;
        const payload = Object.assign({ wp_session: 1 }, body || {});
        let res, data;
        try {
            res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': BBJAuth.nonce,
                },
                body: JSON.stringify(payload),
            });
        } catch (err) {
            return { ok: false, status: 0, data: { error: 'Network error. Please check your connection and try again.' } };
        }
        try {
            data = await res.json();
        } catch (_) {
            data = { error: 'Unexpected response from the server.' };
        }
        return { ok: res.ok, status: res.status, data };
    }

    /**
     * Show / hide the form-level error banner in a view.
     */
    function setFormError(viewEl, message) {
        const banner = viewEl.querySelector('[data-bbj-form-error]');
        if (!banner) return;
        if (!message) {
            banner.classList.add('hidden');
            banner.textContent = '';
            return;
        }
        banner.classList.remove('hidden');
        banner.textContent = message;
    }

    /**
     * Disable submit button and replace its label. Returns a restore fn.
     */
    function busy(btn, loadingLabel) {
        if (!btn) return () => {};
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = loadingLabel;
        return () => { btn.disabled = false; btn.textContent = original; };
    }

    /**
     * Full page reload after successful auth.
     */
    function reloadOnSuccess() {
        const target = new URL(window.location.href);
        target.searchParams.delete('bbj_rp');
        target.searchParams.delete('key');
        target.searchParams.delete('login');
        window.location.href = target.toString();
    }

    async function handleLogin(form) {
        const view = form.closest('.bbj-modal-view');
        setFormError(view, '');
        const fd = new FormData(form);
        const payload = {
            username: (fd.get('username') || '').trim(),
            password: fd.get('password') || '',
            remember_me: !!fd.get('remember_me'),
        };
        if (!payload.username || !payload.password) {
            setFormError(view, 'Username and password are required.');
            return;
        }
        const restore = busy(form.querySelector('[data-bbj-submit]'), 'Logging you in…');
        const { ok, data } = await postJSON('auth/login', payload);
        if (ok && data && data.success) {
            reloadOnSuccess();
            // Fallback — if reload stalls, re-enable the button after 3s.
            setTimeout(restore, 3000);
            return;
        }
        restore();
        setFormError(view, (data && (data.error || data.message)) || 'Login failed.');
    }

    // Delegated submit listener.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('[data-bbj-auth-form]');
        if (!form || !modal.contains(form)) return;
        e.preventDefault();
        const kind = form.getAttribute('data-bbj-auth-form');
        if (kind === 'login') return handleLogin(form);
        // Other handlers attached in later tasks.
    });
})();
```

- [ ] **Step 2: Enqueue `auth-forms.js` after `auth-modal.js`**

Back in `inc/enqueue.php`, inside the same `!is_user_logged_in()` block you edited in Task 14, after the `wp_localize_script('bbj-v2-auth-modal', ...)` call, add:

```php
        wp_enqueue_script(
            'bbj-v2-auth-forms',
            get_template_directory_uri() . '/src/js/auth-forms.js',
            ['bbj-v2-auth-modal'],
            filemtime(BBJ_V2_THEME_PATH . '/src/js/auth-forms.js'),
            true
        );
```

- [ ] **Step 3: Manual test — end-to-end login**

Pre-reqs: you have a WP test user with known credentials.

1. Visit `http://bbj.localhost/` logged out.
2. Click Log In → modal opens to view-login.
3. Enter wrong password → submit → red banner "Incorrect username or password." Button re-enables.
4. Enter correct username + password → submit → page reloads, logo bar shows avatar/name, WP admin bar appears, modal markup is absent on the new page (because `is_user_logged_in()` is now true).

DevTools check: Application → Cookies → `wordpress_logged_in_*` cookie has `SameSite=Lax`, `HttpOnly`, and (on prod) `Secure`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/auth-forms.js wp-content/themes/bbj-v2-theme/inc/enqueue.php
git commit -m "feat(theme): wire login form submit to /auth/login, reload on success"
```

---

## Phase 4 — Registration

### Task 17: Build `view-register.php` markup

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/auth/view-register.php`

- [ ] **Step 1: Replace the stub**

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="register" aria-labelledby="bbj-auth-modal-title">

    <div data-bbj-auth-webview-notice class="hidden bbj-form-error">
        <?php esc_html_e(
            "Google sign-up doesn't work inside the Facebook or Instagram browser. Tap the ••• menu and choose \"Open in Chrome\" or \"Open in Safari.\"",
            'bbj-v2-theme'
        ); ?>
    </div>

    <div data-bbj-google-register-container></div>

    <div class="bbj-divider"><span><?php esc_html_e('or', 'bbj-v2-theme'); ?></span></div>

    <form data-bbj-auth-form="register" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

        <div>
            <label for="bbj-reg-username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Username', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="text" id="bbj-reg-username" name="username" required autocomplete="username"
                   class="input" placeholder="<?php esc_attr_e('lowercase letters and numbers only', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="username"></p>
        </div>

        <div>
            <label for="bbj-reg-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Email', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="email" id="bbj-reg-email" name="email" required autocomplete="email"
                   class="input" placeholder="you@example.com">
            <p class="bbj-field-error hidden" data-bbj-field-error="email"></p>
        </div>

        <div>
            <label for="bbj-reg-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Password', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="password" id="bbj-reg-password" name="password" required autocomplete="new-password"
                   class="input" placeholder="<?php esc_attr_e('At least 8 characters', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="password"></p>
        </div>

        <div>
            <label for="bbj-reg-password2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Confirm Password', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="password" id="bbj-reg-password2" name="confirm_password" required autocomplete="new-password"
                   class="input" placeholder="<?php esc_attr_e('Re-enter your password', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="confirm_password"></p>
        </div>

        <div>
            <label for="bbj-reg-display" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Display Name', 'bbj-v2-theme'); ?>
                <span class="text-gray-400">(<?php esc_html_e('optional', 'bbj-v2-theme'); ?>)</span>
            </label>
            <input type="text" id="bbj-reg-display" name="display_name" autocomplete="name"
                   class="input" placeholder="<?php esc_attr_e('How you want to be known', 'bbj-v2-theme'); ?>">
        </div>

        <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" name="subscribe_newsletter" checked
                   class="mt-1 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-500 focus:ring-primary-500">
            <span class="text-sm text-gray-600 dark:text-gray-400">
                <?php esc_html_e('Email me when new posts are published', 'bbj-v2-theme'); ?>
            </span>
        </label>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Create Account', 'bbj-v2-theme'); ?>
        </button>
    </form>

    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        <?php esc_html_e('Already have an account?', 'bbj-v2-theme'); ?>
        <button type="button" data-bbj-auth-switch="login" class="text-primary-500 hover:underline font-medium">
            <?php esc_html_e('Log in', 'bbj-v2-theme'); ?>
        </button>
    </p>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/auth/view-register.php
git commit -m "feat(theme): build view-register.php markup"
```

---

### Task 18: Add registration submit + debounced username/email checks to `auth-forms.js`

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/js/auth-forms.js`

- [ ] **Step 1: Add helpers and the register handler**

Inside the existing IIFE in `auth-forms.js`, just below `handleLogin` (before the delegated submit listener), add:

```js
    function setFieldError(form, fieldName, message) {
        const el = form.querySelector('[data-bbj-field-error="' + fieldName + '"]');
        if (!el) return;
        if (!message) {
            el.classList.add('hidden');
            el.textContent = '';
            return;
        }
        el.classList.remove('hidden');
        el.textContent = message;
    }

    function clearAllFieldErrors(form) {
        form.querySelectorAll('[data-bbj-field-error]').forEach(el => {
            el.classList.add('hidden');
            el.textContent = '';
        });
    }

    function debounce(fn, ms) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments, self = this;
            t = setTimeout(() => fn.apply(self, args), ms);
        };
    }

    async function handleRegister(form) {
        const view = form.closest('.bbj-modal-view');
        setFormError(view, '');
        clearAllFieldErrors(form);

        const fd = new FormData(form);
        const username = (fd.get('username') || '').toString().toLowerCase().trim();
        const email = (fd.get('email') || '').toString().trim();
        const password = (fd.get('password') || '').toString();
        const confirm = (fd.get('confirm_password') || '').toString();
        const displayName = (fd.get('display_name') || '').toString().trim();
        const subscribe = !!fd.get('subscribe_newsletter');

        // Client-side validation mirrors server rules.
        let failed = false;
        if (!/^[a-z0-9]{3,}$/.test(username)) {
            setFieldError(form, 'username', 'Username must be 3+ lowercase letters and numbers only.');
            failed = true;
        }
        if (!/\S+@\S+\.\S+/.test(email)) {
            setFieldError(form, 'email', 'Please enter a valid email.');
            failed = true;
        }
        if (password.length < 8) {
            setFieldError(form, 'password', 'Password must be at least 8 characters.');
            failed = true;
        }
        if (password !== confirm) {
            setFieldError(form, 'confirm_password', 'Passwords do not match.');
            failed = true;
        }
        if (failed) return;

        const restore = busy(form.querySelector('[data-bbj-submit]'), 'Creating account…');
        const { ok, data } = await postJSON('auth/register', {
            username,
            email,
            password,
            display_name: displayName,
            subscribe_newsletter: subscribe,
        });
        if (ok && data && data.success) {
            reloadOnSuccess();
            setTimeout(restore, 3000);
            return;
        }
        restore();
        if (data && data.field) {
            setFieldError(form, data.field, data.error || data.message || 'Invalid.');
        } else {
            setFormError(view, (data && (data.error || data.message)) || 'Registration failed.');
        }
    }

    // Debounced username availability check.
    const checkUsername = debounce(async function (input) {
        const value = (input.value || '').toLowerCase().trim();
        const form = input.closest('form');
        if (value.length < 3) { setFieldError(form, 'username', ''); return; }
        const { ok, data } = await postJSON('auth/check-username', { username: value });
        if (!ok) return;
        if (data && data.valid === false) {
            setFieldError(form, 'username', data.message || 'Username not available.');
        } else {
            setFieldError(form, 'username', '');
        }
    }, 500);

    // Debounced email availability check.
    const checkEmail = debounce(async function (input) {
        const value = (input.value || '').trim();
        const form = input.closest('form');
        if (!value.includes('@')) { setFieldError(form, 'email', ''); return; }
        const { ok, data } = await postJSON('auth/check-email', { email: value });
        if (!ok) return;
        if (data && data.exists === true) {
            setFieldError(form, 'email', 'An account with this email already exists.');
        } else {
            setFieldError(form, 'email', '');
        }
    }, 500);

    document.addEventListener('input', function (e) {
        if (!modal.contains(e.target)) return;
        if (e.target.matches('form[data-bbj-auth-form="register"] input[name="username"]')) checkUsername(e.target);
        if (e.target.matches('form[data-bbj-auth-form="register"] input[name="email"]'))    checkEmail(e.target);
    });
```

- [ ] **Step 2: Extend the submit listener to dispatch register**

Find this in `auth-forms.js`:

```js
        if (kind === 'login') return handleLogin(form);
        // Other handlers attached in later tasks.
```

Change to:

```js
        if (kind === 'login')    return handleLogin(form);
        if (kind === 'register') return handleRegister(form);
        // Other handlers attached in later tasks.
```

- [ ] **Step 3: Manual test — registration end-to-end**

1. Click Log In → "Sign up" → view-register appears.
2. Type `STEVE` in username → on blur/500ms → field error "Username must be 3+ lowercase letters and numbers only." After fixing to `stevetest1`, error clears (or becomes "taken" if that username exists).
3. Type your own email → if account exists, see "An account with this email already exists."
4. Submit with password "abc" → inline error "Password must be at least 8 characters."
5. Submit with mismatched passwords → error under confirm field.
6. Submit valid data → page reloads, logged in as new user.
7. Open `wp-admin/users.php` in another tab → confirm the new user appears with the correct email.
8. If MailPoet is enabled on this install, confirm the user appears in MailPoet subscribers.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/auth-forms.js
git commit -m "feat(theme): register form submit with debounced availability checks"
```

---

## Phase 5 — Google Sign-In & WebView detection

### Task 19: Write `auth-google.js` — GIS init, WebView detection, credential callback

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/js/auth-google.js`
- Modify: `wp-content/themes/bbj-v2-theme/inc/enqueue.php`

- [ ] **Step 1: Create the module**

```js
/**
 * Google Sign-In integration for auth modal.
 *
 * - Loads Google Identity Services once on demand.
 * - Renders the Google button into the active view's container.
 * - Detects FB/IG/Line WebViews and swaps the button for a CTA telling
 *   the user to open the page in Chrome/Safari — GIS needs third-party
 *   cookies, which WebViews sandbox or block.
 * - On credential: POST /auth/google. If needs_linking, switch to the
 *   link view and stash the credential + google_user on the modal.
 */
(function () {
    'use strict';

    if (typeof BBJAuth === 'undefined') return;

    const modal = document.getElementById('bbj-auth-modal');
    if (!modal) return;

    // Client ID is exposed via a data attribute set in PHP (see enqueue).
    const clientId = (document.documentElement.getAttribute('data-bbj-google-client') || '').trim();

    const WEBVIEW_REGEX = /FBAN|FBAV|Instagram|Line\//i;
    function isWebView() {
        if (WEBVIEW_REGEX.test(navigator.userAgent)) return true;
        if (!BBJAuth.debug) return false;
        const p = new URLSearchParams(location.search);
        if (p.has('bbj_force_webview')) {
            document.cookie = 'bbj_force_webview=1; path=/; SameSite=Lax';
        }
        return document.cookie.includes('bbj_force_webview=1');
    }

    let gisPromise = null;
    function loadGIS() {
        if (gisPromise) return gisPromise;
        gisPromise = new Promise(function (resolve, reject) {
            if (window.google && window.google.accounts && window.google.accounts.id) {
                resolve();
                return;
            }
            const existing = document.querySelector('script[src="https://accounts.google.com/gsi/client"]');
            if (existing) {
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', () => reject(new Error('GIS failed to load')));
                return;
            }
            const s = document.createElement('script');
            s.src = 'https://accounts.google.com/gsi/client';
            s.async = true;
            s.defer = true;
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('GIS failed to load'));
            document.body.appendChild(s);
        });
        return gisPromise;
    }

    async function handleCredential(response) {
        if (!response || !response.credential) return;
        const activeView = modal.querySelector('.bbj-modal-view.is-active');
        const errorEl = activeView && activeView.querySelector('[data-bbj-form-error]');
        if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }

        try {
            const res = await fetch(BBJAuth.api + 'auth/google', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': BBJAuth.nonce,
                },
                body: JSON.stringify({
                    credential: response.credential,
                    remember_me: true,
                    wp_session: 1,
                }),
            });
            const data = await res.json();
            if (data && data.needs_linking) {
                modal.dataset.googleCredential = response.credential;
                modal.dataset.googleUser = JSON.stringify(data.google_user || {});
                window.BBJAuthModal.showView('link');
                return;
            }
            if (res.ok && data && data.success) {
                window.location.reload();
                return;
            }
            if (errorEl) {
                errorEl.textContent = (data && (data.error || data.message)) || 'Google sign-in failed.';
                errorEl.classList.remove('hidden');
            }
        } catch (err) {
            if (errorEl) {
                errorEl.textContent = 'Network error during Google sign-in.';
                errorEl.classList.remove('hidden');
            }
        }
    }

    function renderButtonInto(containerEl, textMode) {
        if (!containerEl || !window.google || !window.google.accounts || !window.google.accounts.id) return;
        window.google.accounts.id.initialize({
            client_id: clientId,
            callback: handleCredential,
            auto_select: false,
        });
        containerEl.innerHTML = '';
        window.google.accounts.id.renderButton(containerEl, {
            theme: 'outline',
            size: 'large',
            width: 320,
            text: textMode, // 'continue_with' or 'signup_with'
        });
    }

    function showWebViewNotice(view) {
        const notice = view.querySelector('[data-bbj-auth-webview-notice]');
        if (notice) notice.classList.remove('hidden');
    }

    async function setupForView(viewName) {
        if (!clientId) return; // No client configured — silently skip.
        const view = modal.querySelector('[data-bbj-auth-view="' + viewName + '"]');
        if (!view) return;
        const container = view.querySelector(viewName === 'register' ? '[data-bbj-google-register-container]' : '[data-bbj-google-login-container]');
        if (!container) return;

        if (isWebView()) {
            container.classList.add('hidden');
            showWebViewNotice(view);
            return;
        }

        try {
            await loadGIS();
        } catch (_) {
            return; // Silent drop if GIS won't load.
        }
        renderButtonInto(container, viewName === 'register' ? 'signup_with' : 'continue_with');
    }

    // Render on view switch.
    modal.addEventListener('bbj-auth:view', function (e) {
        const name = e.detail && e.detail.view;
        if (name === 'login' || name === 'register') setupForView(name);
    });

    // Render on first open (if opened before any explicit switch event).
    modal.addEventListener('click', function () {
        const active = modal.querySelector('.bbj-modal-view.is-active');
        if (!active) return;
        const name = active.getAttribute('data-bbj-auth-view');
        if ((name === 'login' || name === 'register') && !active.querySelector('[data-bbj-google-login-container] iframe, [data-bbj-google-register-container] iframe')) {
            setupForView(name);
        }
    }, { once: true });
})();
```

- [ ] **Step 2: Expose the Google client ID via `<html>` attribute**

Open `wp-content/themes/bbj-v2-theme/inc/auth.php`. At the bottom of the file, add:

```php
/**
 * Expose the bbjd Google OAuth client ID as a data attribute on <html>
 * so auth-google.js can read it without another network request.
 *
 * Filter registered at file load so it's present before header.php renders
 * (language_attributes() on <html> fires before wp_head / wp_enqueue_scripts).
 */
add_filter('language_attributes', function (string $output): string {
    if (is_user_logged_in()) {
        return $output;
    }
    $client_id = defined('BBJD_GOOGLE_CLIENT_ID') ? (string) BBJD_GOOGLE_CLIENT_ID : (string) get_option('bbjd_google_client_id', '');
    if ($client_id === '') {
        return $output;
    }
    return $output . ' data-bbj-google-client="' . esc_attr($client_id) . '"';
});
```

- [ ] **Step 3: Enqueue `auth-google.js`**

Inside the same `!is_user_logged_in()` block in `inc/enqueue.php` where you enqueue `auth-forms.js`, add:

```php
        wp_enqueue_script(
            'bbj-v2-auth-google',
            get_template_directory_uri() . '/src/js/auth-google.js',
            ['bbj-v2-auth-modal'],
            filemtime(BBJ_V2_THEME_PATH . '/src/js/auth-google.js'),
            true
        );
```

- [ ] **Step 4: Manual test — WebView fallback (dev flag)**

1. Visit `http://bbj.localhost/?bbj_force_webview=1` logged out.
2. Click Log In → modal opens → **Google button is NOT shown**; the notice "Google sign-in doesn't work inside…" is visible. Username/password form still works.
3. Open a new tab to `http://bbj.localhost/` without the flag (the cookie persists until browser close) → verify behavior still shows the notice on any page until the browser is closed.
4. Close the browser, reopen, visit `http://bbj.localhost/` → Google button reappears in the modal.

- [ ] **Step 5: Manual test — Google Sign-In (existing account)**

Pre-req: Your BBJ test user's Google account is already linked via user meta `google_id` (or matches by email on first sign-in). If you have a test Google account configured on your local dev, use that.

1. Click Log In, click the Google button.
2. Pick your Google account in the GIS popup.
3. Page reloads → you're logged in as that BBJ user.
4. DevTools → Application → Cookies → `wordpress_logged_in_…` cookie is set, `SameSite=Lax`.

If your dev environment has no Google client ID configured, skip this test for now; it'll be covered again on staging.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/auth-google.js wp-content/themes/bbj-v2-theme/inc/enqueue.php wp-content/themes/bbj-v2-theme/inc/auth.php
git commit -m "feat(theme): Google Sign-In integration with WebView detection"
```

---

## Phase 6 — Link Account flow

### Task 20: Build `view-link.php` markup

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/auth/view-link.php`

- [ ] **Step 1: Replace the stub**

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="link" aria-labelledby="bbj-auth-modal-title">

    <div data-bbj-link-profile class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
        <div data-bbj-link-avatar class="w-16 h-16 rounded-full mx-auto mb-2 bg-gray-200 dark:bg-gray-600"></div>
        <p class="text-sm text-gray-600 dark:text-gray-300"><?php esc_html_e('Signing in as', 'bbj-v2-theme'); ?></p>
        <p class="font-medium text-gray-800 dark:text-white" data-bbj-link-name></p>
        <p class="text-sm text-gray-500 dark:text-gray-400" data-bbj-link-email></p>
    </div>

    <p class="text-center text-sm text-gray-600 dark:text-gray-300">
        <?php esc_html_e('No BBJ account found with this email.', 'bbj-v2-theme'); ?>
    </p>

    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
        <h3 class="font-medium text-gray-800 dark:text-white mb-3">
            <?php esc_html_e('Have an existing BBJ account?', 'bbj-v2-theme'); ?>
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            <?php esc_html_e('Enter your BBJ credentials to link your Google account:', 'bbj-v2-theme'); ?>
        </p>

        <form data-bbj-auth-form="link" class="space-y-3" novalidate>
            <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

            <input type="text" name="username" placeholder="<?php esc_attr_e('Username or Email', 'bbj-v2-theme'); ?>"
                   autocomplete="username" required class="input text-sm">
            <input type="password" name="password" placeholder="<?php esc_attr_e('Password', 'bbj-v2-theme'); ?>"
                   autocomplete="current-password" required class="input text-sm">

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember_me" checked
                       class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-500 focus:ring-primary-500">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    <?php esc_html_e('Keep me logged in', 'bbj-v2-theme'); ?>
                </span>
            </label>

            <button type="submit" class="btn-primary w-full" data-bbj-submit>
                <?php esc_html_e('Link Account', 'bbj-v2-theme'); ?>
            </button>
        </form>
    </div>

    <div class="bbj-divider"><span><?php esc_html_e('or', 'bbj-v2-theme'); ?></span></div>

    <button type="button" data-bbj-auth-create-from-google
            class="w-full py-2.5 border-2 border-primary-500 text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg font-medium transition-colors">
        <?php esc_html_e('Continue as New User', 'bbj-v2-theme'); ?>
    </button>

    <p class="text-xs text-center text-gray-400 dark:text-gray-500">
        <?php esc_html_e('You can also link accounts later in your profile settings.', 'bbj-v2-theme'); ?>
    </p>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/auth/view-link.php
git commit -m "feat(theme): build view-link.php markup"
```

---

### Task 21: Link-Account handlers (link existing + create-from-google) in `auth-forms.js`

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/js/auth-forms.js`

- [ ] **Step 1: Add a view-entered handler to populate profile card on link view**

Inside the IIFE in `auth-forms.js`, just above the `document.addEventListener('submit', ...)` block, add:

```js
    function populateLinkCard() {
        const view = modal.querySelector('[data-bbj-auth-view="link"]');
        if (!view) return;
        let gu = {};
        try { gu = JSON.parse(modal.dataset.googleUser || '{}'); } catch (_) {}
        const nameEl = view.querySelector('[data-bbj-link-name]');
        const emailEl = view.querySelector('[data-bbj-link-email]');
        const avatarEl = view.querySelector('[data-bbj-link-avatar]');
        if (nameEl) nameEl.textContent = gu.name || '';
        if (emailEl) emailEl.textContent = gu.email || '';
        if (avatarEl) {
            if (gu.picture) {
                avatarEl.outerHTML = '<img src="' + gu.picture.replace(/"/g, '&quot;') + '" alt="" referrerpolicy="no-referrer" class="w-16 h-16 rounded-full mx-auto mb-2" data-bbj-link-avatar>';
            }
        }
    }

    modal.addEventListener('bbj-auth:view', function (e) {
        if (e.detail && e.detail.view === 'link') populateLinkCard();
    });

    async function handleLink(form) {
        const view = form.closest('.bbj-modal-view');
        setFormError(view, '');
        const credential = modal.dataset.googleCredential;
        if (!credential) { setFormError(view, 'Google sign-in expired. Please try again.'); return; }

        const fd = new FormData(form);
        const payload = {
            credential,
            username: (fd.get('username') || '').toString().trim(),
            password: (fd.get('password') || '').toString(),
            remember_me: !!fd.get('remember_me'),
        };
        if (!payload.username || !payload.password) {
            setFormError(view, 'Username and password are required.');
            return;
        }
        const restore = busy(form.querySelector('[data-bbj-submit]'), 'Linking…');
        const { ok, data } = await postJSON('auth/link-google', payload);
        if (ok && data && data.success) {
            reloadOnSuccess();
            setTimeout(restore, 3000);
            return;
        }
        restore();
        setFormError(view, (data && (data.error || data.message)) || 'Failed to link account.');
    }

    async function handleCreateFromGoogle(view, btn) {
        setFormError(view, '');
        const credential = modal.dataset.googleCredential;
        if (!credential) { setFormError(view, 'Google sign-in expired. Please try again.'); return; }
        const restore = busy(btn, 'Creating…');
        const { ok, data } = await postJSON('auth/create-from-google', { credential, remember_me: true });
        if (ok && data && data.success) {
            reloadOnSuccess();
            setTimeout(restore, 3000);
            return;
        }
        restore();
        setFormError(view, (data && (data.error || data.message)) || 'Failed to create account.');
    }

    // Delegated click listener for the "Continue as New User" button.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-bbj-auth-create-from-google]');
        if (!btn || !modal.contains(btn)) return;
        e.preventDefault();
        const view = btn.closest('.bbj-modal-view');
        handleCreateFromGoogle(view, btn);
    });
```

- [ ] **Step 2: Extend the submit dispatcher**

Update the existing submit block from:

```js
        if (kind === 'login')    return handleLogin(form);
        if (kind === 'register') return handleRegister(form);
        // Other handlers attached in later tasks.
```

to:

```js
        if (kind === 'login')    return handleLogin(form);
        if (kind === 'register') return handleRegister(form);
        if (kind === 'link')     return handleLink(form);
        // Other handlers attached in later tasks.
```

- [ ] **Step 3: Manual test — Link flow (link to existing)**

1. Sign in with a Google account whose email **does not** match any BBJ user → modal switches to view-link → profile card shows Google name/email/avatar.
2. Enter existing BBJ user's username + password → Link Account → reloads → now logged in as that BBJ user, and `google_id` user meta is set.

Verify in DB:

```bash
"/c/xampp/mysql/bin/mysql" -uroot bbj_db -e "SELECT user_id, meta_value FROM wp_usermeta WHERE meta_key='google_id' ORDER BY umeta_id DESC LIMIT 3;"
```

Expected: new row with the matched BBJ user ID.

- [ ] **Step 4: Manual test — Link flow (create new from Google)**

1. Sign in with a different Google account that has no BBJ match → view-link.
2. Click "Continue as New User" → reloads → new WP user row created with email from Google.
3. Confirm in `wp-admin/users.php`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/auth-forms.js
git commit -m "feat(theme): link-account form and create-from-google button handlers"
```

---

## Phase 7 — Forgot & reset password

### Task 22: Build `view-forgot.php` and submit handler

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/auth/view-forgot.php`
- Modify: `wp-content/themes/bbj-v2-theme/src/js/auth-forms.js`

- [ ] **Step 1: Replace the stub**

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="forgot" aria-labelledby="bbj-auth-modal-title">

    <p class="text-sm text-gray-600 dark:text-gray-300">
        <?php esc_html_e("Enter the email associated with your BBJ account and we'll send a reset link.", 'bbj-v2-theme'); ?>
    </p>

    <form data-bbj-auth-form="forgot" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>
        <div data-bbj-form-success class="hidden p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300 text-sm">
            <?php esc_html_e("Check your email for a reset link. The link expires in 24 hours.", 'bbj-v2-theme'); ?>
        </div>

        <div>
            <label for="bbj-forgot-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Email', 'bbj-v2-theme'); ?>
            </label>
            <input type="email" id="bbj-forgot-email" name="email" required autocomplete="email" class="input">
        </div>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Send Reset Link', 'bbj-v2-theme'); ?>
        </button>
    </form>

    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        <button type="button" data-bbj-auth-switch="login" class="text-primary-500 hover:underline font-medium">
            <?php esc_html_e('Back to Log In', 'bbj-v2-theme'); ?>
        </button>
    </p>
</section>
```

- [ ] **Step 2: Add the `handleForgot` function in `auth-forms.js`**

Below `handleCreateFromGoogle`, add:

```js
    async function handleForgot(form) {
        const view = form.closest('.bbj-modal-view');
        setFormError(view, '');
        const success = view.querySelector('[data-bbj-form-success]');
        if (success) success.classList.add('hidden');

        const fd = new FormData(form);
        const email = (fd.get('email') || '').toString().trim();
        if (!/\S+@\S+\.\S+/.test(email)) {
            setFormError(view, 'Please enter a valid email.');
            return;
        }
        const restore = busy(form.querySelector('[data-bbj-submit]'), 'Sending…');
        const { ok, data } = await postJSON('auth/forgot-password', { email });
        restore();
        if (ok && data && data.success) {
            if (success) success.classList.remove('hidden');
            form.reset();
            return;
        }
        setFormError(view, (data && (data.error || data.message)) || 'Failed to send reset link.');
    }
```

- [ ] **Step 3: Extend the submit dispatcher**

Change:

```js
        if (kind === 'link')     return handleLink(form);
        // Other handlers attached in later tasks.
```

to:

```js
        if (kind === 'link')     return handleLink(form);
        if (kind === 'forgot')   return handleForgot(form);
        // Other handlers attached in later tasks.
```

- [ ] **Step 4: Manual test**

1. Click Log In → "Forgot password?" → view-forgot.
2. Submit non-existent email → plugin still responds success (for enumeration safety); verify green success box appears. (This matches WordPress default behavior.)
3. Submit a real test user's email → check the mailtrap / local mail sink / `wp-content/debug.log` — a message with a reset link containing `bbj_rp=1&key=…&login=…` should be generated. If local email isn't configured, the plugin's email hook should still fire without a PHP error.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/auth/view-forgot.php wp-content/themes/bbj-v2-theme/src/js/auth-forms.js
git commit -m "feat(theme): forgot-password view and submit handler"
```

---

### Task 23: Build `view-reset.php` and handler; wire `?bbj_rp=…` landing

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/auth/view-reset.php`
- Modify: `wp-content/themes/bbj-v2-theme/src/js/auth-forms.js`

- [ ] **Step 1: Replace the stub**

```php
<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="reset" aria-labelledby="bbj-auth-modal-title">

    <p class="text-sm text-gray-600 dark:text-gray-300">
        <?php esc_html_e('Enter a new password for your BBJ account.', 'bbj-v2-theme'); ?>
    </p>

    <form data-bbj-auth-form="reset" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

        <div>
            <label for="bbj-reset-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('New Password', 'bbj-v2-theme'); ?>
            </label>
            <input type="password" id="bbj-reset-password" name="password" required autocomplete="new-password"
                   class="input" placeholder="<?php esc_attr_e('At least 8 characters', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="password"></p>
        </div>

        <div>
            <label for="bbj-reset-password2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Confirm Password', 'bbj-v2-theme'); ?>
            </label>
            <input type="password" id="bbj-reset-password2" name="confirm_password" required autocomplete="new-password"
                   class="input">
            <p class="bbj-field-error hidden" data-bbj-field-error="confirm_password"></p>
        </div>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Set New Password', 'bbj-v2-theme'); ?>
        </button>
    </form>
</section>
```

- [ ] **Step 2: Add `handleReset` function in `auth-forms.js`**

Below `handleForgot`, add:

```js
    async function handleReset(form) {
        const view = form.closest('.bbj-modal-view');
        setFormError(view, '');
        clearAllFieldErrors(form);

        const key = modal.dataset.resetKey || '';
        const login = modal.dataset.resetLogin || '';
        if (!key || !login) {
            setFormError(view, 'Reset link expired or invalid. Please request a new one.');
            return;
        }

        const fd = new FormData(form);
        const password = (fd.get('password') || '').toString();
        const confirm = (fd.get('confirm_password') || '').toString();

        let failed = false;
        if (password.length < 8) {
            setFieldError(form, 'password', 'Password must be at least 8 characters.');
            failed = true;
        }
        if (password !== confirm) {
            setFieldError(form, 'confirm_password', 'Passwords do not match.');
            failed = true;
        }
        if (failed) return;

        const restore = busy(form.querySelector('[data-bbj-submit]'), 'Saving…');
        const { ok, data } = await postJSON('auth/reset-password', { key, login, password });
        if (ok && data && data.success) {
            reloadOnSuccess();
            setTimeout(restore, 3000);
            return;
        }
        restore();
        setFormError(view, (data && (data.error || data.message)) || 'Failed to reset password.');
    }
```

- [ ] **Step 3: Extend the submit dispatcher**

Change:

```js
        if (kind === 'forgot')   return handleForgot(form);
        // Other handlers attached in later tasks.
```

to:

```js
        if (kind === 'forgot')   return handleForgot(form);
        if (kind === 'reset')    return handleReset(form);
```

- [ ] **Step 4: Manual test — reset via email link**

1. Trigger a forgot-password request for a test user (Task 22).
2. Locate the reset email (debug log, local mail sink, or manually construct the URL from a `get_password_reset_key()` in PHP for testing).
3. Visit the URL: `http://bbj.localhost/?bbj_rp=1&key=THEKEY&login=LOGIN`.
4. The modal auto-opens on the reset view.
5. Enter a new password (≥8 chars) + matching confirm → submit.
6. Page reloads → logged in as that user with the new password.
7. Verify by logging out and logging back in with the new password.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/auth/view-reset.php wp-content/themes/bbj-v2-theme/src/js/auth-forms.js
git commit -m "feat(theme): reset-password view, handler, and landing auto-open"
```

---

## Phase 8 — Polish

### Task 24: Accessibility pass

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/js/auth-modal.js`
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/auth/modal.php`

- [ ] **Step 1: Ensure the `<dialog>` announces on open**

Open `auth-modal.js`. In `open(view)`, after `modal.setAttribute('aria-hidden', 'false');`, add:

```js
        modal.dispatchEvent(new CustomEvent('bbj-auth:opened', { detail: { view: view || 'login' } }));
```

(Gives downstream code a hook to announce view changes to screen readers — the view change event was already wired in `showView`.)

- [ ] **Step 2: Manual test — screen reader announces title change**

1. Open a screen reader (Narrator on Windows, VoiceOver on macOS).
2. Click Log In → "Log In" is announced as the dialog title.
3. Click "Sign up" → "Create Account" is announced.
4. Trigger a form error (submit empty form) → error banner is announced because `role="alert"` is on the container.

- [ ] **Step 3: Manual test — keyboard only**

1. Load page, press Tab until Log In button is focused.
2. Press Enter → modal opens, focus lands on the username field.
3. Tab through the form → Tab past the last element (Sign up link) wraps back to the `×` close button.
4. Shift+Tab from the username field wraps to the close button (or last focusable element).
5. Press Esc → modal closes, focus returns to the Log In button.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/auth-modal.js
git commit -m "feat(theme): add bbj-auth:opened event for a11y announcements"
```

---

### Task 25: Production cookie audit and WebView verification checklist

**Files:**
- Create: `docs/superpowers/plans/2026-04-18-login-register-modals-checklist.md`

- [ ] **Step 1: Write the pre-ship checklist**

```markdown
# Login/Register Modals — pre-ship checklist

Run this before merging `feature/bbj-v2-theme` → staging, then again on staging before prod.

## Local dev checks

- [ ] Flow 1 — username/password login reloads, logo bar shows avatar/name.
- [ ] Flow 4 — register new user, newsletter subscribe triggers (if MailPoet enabled).
- [ ] Flow 8 — Google sign-in for a linked account reloads.
- [ ] Flow 9 — Google sign-in for an unlinked account routes to view-link; link with existing password succeeds.
- [ ] Flow 10 — "Continue as New User" on view-link creates a new WP user.
- [ ] Flow 11 — forgot-password produces an email (check `wp-content/debug.log` or local mail sink).
- [ ] Flow 12 — reset link on ?bbj_rp=1 auto-opens the reset view and completes.
- [ ] WebView dev flag — `?bbj_force_webview=1` hides Google button, shows "open in Chrome" notice; form still works.

## Cookie audit (DevTools → Application → Cookies)

- [ ] `wordpress_logged_in_…` — HttpOnly ☑, Secure ☑ (prod only), SameSite=Lax ☑.
- [ ] `wordpress_sec_…` — HttpOnly ☑, Secure ☑ (prod only), SameSite=Lax ☑.
- [ ] `bbj_force_webview` (dev only) — SameSite=Lax, path=/.
- [ ] No residual JWT cookies from earlier experiments.

## Next.js app regression

- [ ] `../bbj-app/` login still issues a JWT (no WP cookie).
- [ ] `/jwt-auth/v1/token` returns the same shape as before.
- [ ] `/bbjd/v1/auth/me` (with Bearer token) still works for the React UI.

## Real-world WebView capture (do this before shipping)

- [ ] On an iPhone, open a Facebook post linking to the site. Open the link inside FB.
- [ ] Copy the User-Agent string (long-press an input, paste it into a note — or use a UA-echo page).
- [ ] Confirm the UA matches `/FBAN|FBAV|Instagram|Line\//`. If it doesn't, add it to the regex in `auth-google.js`.
- [ ] Verify the fallback notice appears and the form works inside the FB browser.

## Accessibility

- [ ] Screen reader announces dialog title on open and on view switch.
- [ ] Form errors announced via `role="alert"`.
- [ ] Focus returns to opener on close.
- [ ] Tab cycle stays inside the dialog.

## Performance / regression

- [ ] First paint unaffected for logged-in users (modal & scripts skipped).
- [ ] Logged-out HTML weight added: modal is ~2-3KB gzipped; scripts are ~4-6KB gzipped + GIS on demand.
- [ ] Ads / AIOSEO / Breeze cache behavior unchanged.
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/plans/2026-04-18-login-register-modals-checklist.md
git commit -m "docs(plan): add login/register pre-ship checklist"
```

---

## Implementation complete — final verification

- [ ] Run the full Manual test matrix from the design spec (flows 1-13).
- [ ] Run every item in the pre-ship checklist above.
- [ ] Skim the commit log — `git log --oneline master..HEAD` should show ~25 commits, each on a single concern (class, endpoint, view, handler, etc.).
- [ ] Push the branch and open a PR titled `feat: login & registration modals (bbj-v2 theme)`.

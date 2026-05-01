# Comments Rebuild Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the bbj-app React comment UI into bbj-v2-theme as a lazy-hydrated React island consuming the existing `bbjd/v1` REST API. Cookie+nonce auth primary, JWT preserved.

**Architecture:** PHP filter swaps `comments_template()` for an SSR placeholder + bootstrap loader. IntersectionObserver triggers dynamic import of `main.js` (list + voting + reactions). First write-intent triggers dynamic import of `composer.js` (CommentForm + media/Giphy/emoji/mention pickers). All `bbjd/v1` reads/writes go through a single `bbjAuthFetch` helper that adds the custom `X-BBJ-Nonce` header.

**Tech Stack:** PHP 7.4+ (WordPress), bigbrotherjunkies-data plugin, React 18 (forked from Next.js bbj-app), Tailwind CSS 3.4, wp-scripts (webpack), PHPUnit (Brain Monkey + WP_Mock for unit tests).

**Spec:** `docs/superpowers/specs/2026-05-01-comments-rebuild-design.md` — read first.

---

## File structure

### New PHP files

| Path | Responsibility |
|---|---|
| `wp-content/themes/bbj-v2-theme/inc/comments-island.php` | `comments_template` filter, enqueue + localize, `?bbjcomments=plain` short-circuit |
| `wp-content/themes/bbj-v2-theme/template-parts/comments/island-placeholder.php` | SSR placeholder div + skeleton + `<noscript>` fallback |
| `wp-content/themes/bbj-v2-theme/template-parts/comments/plain-fallback.php` | Server-side comment list for `?bbjcomments=plain` |

### New PHP backend (in `bigbrotherjunkies-data` plugin)

| Path | Responsibility |
|---|---|
| `wp-content/plugins/bigbrotherjunkies-data/src/Auth/CookieOrJwtAuth.php` | `bbjd_cookie_or_jwt_permission()` helper |
| `wp-content/plugins/bigbrotherjunkies-data/src/Routes/AuthRoutes.php` (modify) | Add `GET /auth/refresh-nonce` |
| `wp-content/plugins/bigbrotherjunkies-data/src/Cache/CommentsReadCache.php` | Object cache wrapper for `GET /comments/{post_id}` |
| `wp-content/plugins/bigbrotherjunkies-data/src/Routes/CommentRoutes.php` (modify) | Swap `permission_callback`s to `bbjd_cookie_or_jwt_permission` |

### New React files (in `bbj-v2-theme/src/comments/`)

| Path | Chunk |
|---|---|
| `bootstrap.js` | bootstrap |
| `main.js` | main |
| `composer.js` | composer |
| `components/CommentSection.jsx` | main |
| `components/CommentCard.jsx` | main |
| `components/VoteButtons.jsx` | main |
| `components/ReactionButtons.jsx` | main |
| `components/RankBadge.jsx` | main |
| `components/StaffPickBadge.jsx` | main |
| `components/OnlineIndicator.jsx` | main |
| `components/AuthorModal.jsx` | main |
| `components/ReportModal.jsx` | main |
| `components/CommentForm.jsx` | composer |
| `components/MediaUploader.jsx` | composer |
| `components/GiphyPicker.jsx` | composer |
| `components/EmojiPicker.jsx` | composer |
| `components/MentionAutocomplete.jsx` | composer |
| `hooks/useBbjUser.js` | main |
| `hooks/useToast.js` | main |
| `lib/bbjAuthFetch.js` | main |
| `lib/api.js` | main |
| `lib/rankConfig.js` | main |

### PHPUnit test files

| Path | Covers |
|---|---|
| `wp-content/plugins/bigbrotherjunkies-data/tests/Auth/CookieOrJwtAuthTest.php` | All branches of `bbjd_cookie_or_jwt_permission()` |
| `wp-content/plugins/bigbrotherjunkies-data/tests/Routes/AuthRoutesTest.php` | `/auth/refresh-nonce` endpoint |

### Modified files

| Path | What changes |
|---|---|
| `wp-content/themes/bbj-v2-theme/inc/setup.php` | `require_once 'comments-island.php'` |
| `wp-content/themes/bbj-v2-theme/comments.php` | Reduce to a one-line note (island handles this now) |
| `wp-content/themes/bbj-v2-theme/inc/enqueue.php` | Register new comment script handles + ensure webpack output is loaded |
| `wp-content/themes/bbj-v2-theme/package.json` | Add wp-scripts entry config for the three chunks |
| `wp-content/themes/bbj-v2-theme/webpack.config.js` (NEW or modify) | Three entry points: bootstrap, main, composer |

---

## Phase 1 — Backend: auth helper, refresh endpoint, read cache

### Task 1: Cookie+JWT permission helper with PHPUnit tests

**Files:**
- Create: `wp-content/plugins/bigbrotherjunkies-data/src/Auth/CookieOrJwtAuth.php`
- Create: `wp-content/plugins/bigbrotherjunkies-data/tests/Auth/CookieOrJwtAuthTest.php`
- Modify: `wp-content/plugins/bigbrotherjunkies-data/composer.json` (add `phpunit/phpunit`, `brain/monkey`, `10up/wp_mock` if not present)

- [ ] **Step 1: Write the failing test file**

```php
<?php
namespace BigBrotherJunkies\Data\Tests\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class CookieOrJwtAuthTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_returns_true_when_logged_in_with_valid_nonce(): void {
        $_SERVER['HTTP_X_BBJ_NONCE'] = 'valid-nonce';
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->alias(
            fn($n, $action) => $n === 'valid-nonce' && $action === 'bbj_comments' ? 1 : false
        );
        $this->assertTrue(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_false_when_logged_in_without_nonce(): void {
        unset($_SERVER['HTTP_X_BBJ_NONCE']);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(false);
        $this->assertFalse(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_false_when_logged_in_with_bad_nonce(): void {
        $_SERVER['HTTP_X_BBJ_NONCE'] = 'wrong';
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(false);
        $this->assertFalse(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_true_with_valid_jwt_only(): void {
        unset($_SERVER['HTTP_X_BBJ_NONCE']);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(true);
        $this->assertTrue(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_false_with_invalid_jwt_no_cookie(): void {
        unset($_SERVER['HTTP_X_BBJ_NONCE']);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(false);
        $this->assertFalse(\bbjd_cookie_or_jwt_permission());
    }

    public function test_prefers_cookie_when_both_present(): void {
        $_SERVER['HTTP_X_BBJ_NONCE'] = 'valid-nonce';
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(true);
        $this->assertTrue(\bbjd_cookie_or_jwt_permission());
        $this->assertSame('cookie', \bbjd_last_auth_path());
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run from plugin dir: `vendor/bin/phpunit tests/Auth/CookieOrJwtAuthTest.php`
Expected: 6 errors / failures — function `bbjd_cookie_or_jwt_permission` doesn't exist.

- [ ] **Step 3: Implement the helper**

```php
<?php
namespace BigBrotherJunkies\Data\Auth;

if (!defined('ABSPATH')) { exit; }

class CookieOrJwtAuth {
    private static string $lastPath = 'none';

    public static function check(): bool {
        $nonce = $_SERVER['HTTP_X_BBJ_NONCE'] ?? '';
        if (is_user_logged_in() && $nonce !== '' && wp_verify_nonce($nonce, 'bbj_comments')) {
            self::$lastPath = 'cookie';
            return true;
        }
        if (function_exists('bbjd_jwt_present_and_valid') && bbjd_jwt_present_and_valid()) {
            self::$lastPath = 'jwt';
            return true;
        }
        self::$lastPath = 'none';
        return false;
    }

    public static function lastPath(): string { return self::$lastPath; }
}

if (!function_exists('bbjd_cookie_or_jwt_permission')) {
    function bbjd_cookie_or_jwt_permission(): bool {
        return \BigBrotherJunkies\Data\Auth\CookieOrJwtAuth::check();
    }
}
if (!function_exists('bbjd_last_auth_path')) {
    function bbjd_last_auth_path(): string {
        return \BigBrotherJunkies\Data\Auth\CookieOrJwtAuth::lastPath();
    }
}
```

- [ ] **Step 4: Wire the file into the plugin bootstrap**

Modify `wp-content/plugins/bigbrotherjunkies-data/Plugin.php` to autoload `src/Auth/`. If PSR-4 autoload is already configured, no change. Otherwise add: `require_once __DIR__ . '/src/Auth/CookieOrJwtAuth.php';` in the bootstrap.

- [ ] **Step 5: Run the test, verify it passes**

Run: `vendor/bin/phpunit tests/Auth/CookieOrJwtAuthTest.php`
Expected: PASS — 6/6 tests, 6 assertions.

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Auth/CookieOrJwtAuth.php \
         wp-content/plugins/bigbrotherjunkies-data/tests/Auth/CookieOrJwtAuthTest.php \
         wp-content/plugins/bigbrotherjunkies-data/Plugin.php
git commit -m "feat(comments): cookie+jwt permission helper for bbjd/v1"
```

---

### Task 2: Apply helper across all `bbjd/v1/comments/*` routes

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Routes/CommentRoutes.php`

- [ ] **Step 1: List every route's current `permission_callback`**

Run: `grep -n "permission_callback" wp-content/plugins/bigbrotherjunkies-data/src/Routes/CommentRoutes.php`
Expected: a list of route registrations. Note which ones currently use a JWT-only check vs. read-only.

- [ ] **Step 2: Replace JWT-only callbacks with the new helper**

For each write route (POST/PUT/DELETE/PATCH on `/comments/*`), change:
```php
'permission_callback' => [$this, 'checkUserLoggedIn'],
```
to:
```php
'permission_callback' => 'bbjd_cookie_or_jwt_permission',
```

Leave read-only routes (`GET /comments/{post_id}`) with their existing `permission_callback => '__return_true'` (public read).

- [ ] **Step 3: Manual smoke — hit a write endpoint with cookie + nonce**

Start watch mode in another terminal: `npm run dev` in theme dir.

Use the WP REST API browser (or `curl`) logged in as an editor:
```bash
curl -X POST 'http://bbj.local/wp-json/bbjd/v1/comments' \
  -H 'X-BBJ-Nonce: <nonce-from-localized-data>' \
  -H 'Cookie: <wp-session-cookie>' \
  -H 'Content-Type: application/json' \
  --data '{"post_id":1,"content":"smoke test"}'
```
Expected: 200 with the new comment object, OR 422 if fields invalid (still proves auth passed).

- [ ] **Step 4: Manual smoke — hit a write endpoint with JWT only (regression)**

```bash
curl -X POST 'http://bbj.local/wp-json/bbjd/v1/comments' \
  -H 'Authorization: Bearer <existing-jwt>' \
  -H 'Content-Type: application/json' \
  --data '{"post_id":1,"content":"jwt smoke test"}'
```
Expected: 200 — the JWT path still works for external consumers.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Routes/CommentRoutes.php
git commit -m "feat(comments): cookie+nonce auth path on bbjd/v1/comments writes"
```

---

### Task 3: `GET /auth/refresh-nonce` endpoint with PHPUnit test

**Files:**
- Create: `wp-content/plugins/bigbrotherjunkies-data/tests/Routes/AuthRoutesTest.php`
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Routes/AuthRoutes.php` (or create if missing)

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace BigBrotherJunkies\Data\Tests\Routes;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class AuthRoutesTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_refresh_nonce_returns_fresh_nonce_for_logged_in_user(): void {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_create_nonce')->alias(fn($a) => "nonce-for-{$a}");
        $route = new \BigBrotherJunkies\Data\Routes\AuthRoutes();
        $response = $route->refreshNonce();
        $this->assertSame(['nonce' => 'nonce-for-bbj_comments'], $response->get_data());
        $this->assertSame(200, $response->get_status());
    }

    public function test_refresh_nonce_rejects_anonymous(): void {
        Functions\when('is_user_logged_in')->justReturn(false);
        $route = new \BigBrotherJunkies\Data\Routes\AuthRoutes();
        $this->assertFalse($route->checkLoggedIn());
    }
}
```

- [ ] **Step 2: Run, verify fail**

Run: `vendor/bin/phpunit tests/Routes/AuthRoutesTest.php`
Expected: FAIL — `AuthRoutes` class missing or method missing.

- [ ] **Step 3: Implement the route**

```php
<?php
namespace BigBrotherJunkies\Data\Routes;

if (!defined('ABSPATH')) { exit; }

class AuthRoutes {
    public function registerRoutes(): void {
        register_rest_route('bbjd/v1', '/auth/refresh-nonce', [
            'methods'             => 'GET',
            'callback'            => [$this, 'refreshNonce'],
            'permission_callback' => [$this, 'checkLoggedIn'],
        ]);
    }
    public function checkLoggedIn(): bool {
        return is_user_logged_in();
    }
    public function refreshNonce(): \WP_REST_Response {
        return new \WP_REST_Response(['nonce' => wp_create_nonce('bbj_comments')], 200);
    }
}
```

- [ ] **Step 4: Wire the route into `rest_api_init`**

Modify `Plugin.php` (or wherever routes are registered):
```php
add_action('rest_api_init', [new \BigBrotherJunkies\Data\Routes\AuthRoutes(), 'registerRoutes']);
```

- [ ] **Step 5: Run, verify pass**

Run: `vendor/bin/phpunit tests/Routes/AuthRoutesTest.php`
Expected: PASS — 2/2.

- [ ] **Step 6: Manual smoke**

Logged in browser tab, console:
```js
fetch('/wp-json/bbjd/v1/auth/refresh-nonce', {credentials: 'include'}).then(r=>r.json()).then(console.log)
```
Expected: `{ nonce: "<32-char string>" }`

- [ ] **Step 7: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Routes/AuthRoutes.php \
         wp-content/plugins/bigbrotherjunkies-data/tests/Routes/AuthRoutesTest.php \
         wp-content/plugins/bigbrotherjunkies-data/Plugin.php
git commit -m "feat(comments): /auth/refresh-nonce endpoint"
```

---

### Task 4: Object cache wrapper for `GET /comments/{post_id}`

**Files:**
- Create: `wp-content/plugins/bigbrotherjunkies-data/src/Cache/CommentsReadCache.php`
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Routes/CommentRoutes.php` — wrap the read handler

- [ ] **Step 1: Implement the cache wrapper**

```php
<?php
namespace BigBrotherJunkies\Data\Cache;

if (!defined('ABSPATH')) { exit; }

class CommentsReadCache {
    private const GROUP = 'bbj_v2';
    private const TTL = 60;

    public static function key(int $postId, int $page, string $sort): string {
        return sprintf('bbj_comments_%d_p%d_s%s', $postId, $page, $sort);
    }

    public static function get(int $postId, int $page, string $sort) {
        return wp_cache_get(self::key($postId, $page, $sort), self::GROUP);
    }

    public static function set(int $postId, int $page, string $sort, $payload): bool {
        return wp_cache_set(self::key($postId, $page, $sort), $payload, self::GROUP, self::TTL);
    }

    public static function bust(int $postId): void {
        // Bust all paged/sorted variants by deleting the post-scoped namespace.
        // Object cache APIs lack pattern-delete, so we bump a version counter.
        $vKey = "bbj_comments_v_{$postId}";
        $cur = (int) wp_cache_get($vKey, self::GROUP) ?: 1;
        wp_cache_set($vKey, $cur + 1, self::GROUP, 0);
    }

    public static function versionedKey(int $postId, int $page, string $sort): string {
        $vKey = "bbj_comments_v_{$postId}";
        $v = (int) wp_cache_get($vKey, self::GROUP) ?: 1;
        return sprintf('bbj_comments_%d_v%d_p%d_s%s', $postId, $v, $page, $sort);
    }

    public static function registerInvalidationHooks(): void {
        $bust = function ($commentIdOrObj) {
            $postId = is_object($commentIdOrObj)
                ? (int) $commentIdOrObj->comment_post_ID
                : (int) get_comment($commentIdOrObj)->comment_post_ID;
            if ($postId > 0) self::bust($postId);
        };
        add_action('wp_insert_comment', $bust, 10, 2);
        add_action('edit_comment', $bust, 10, 2);
        add_action('deleted_comment', $bust, 10, 2);
        add_action('wp_set_comment_status', $bust, 10, 2);
        // Custom bbjd writes
        add_action('bbjd_comment_voted',   fn($postId) => self::bust((int) $postId), 10, 1);
        add_action('bbjd_comment_pinned',  fn($postId) => self::bust((int) $postId), 10, 1);
        add_action('bbjd_comment_reacted', fn($postId) => self::bust((int) $postId), 10, 1);
        add_action('bbjd_comment_reported', fn($postId) => self::bust((int) $postId), 10, 1);
    }
}
```

- [ ] **Step 2: Wire registration in `Plugin.php` bootstrap**

```php
add_action('init', [\BigBrotherJunkies\Data\Cache\CommentsReadCache::class, 'registerInvalidationHooks']);
```

- [ ] **Step 3: Wrap the read handler in `CommentRoutes.php`**

Find the `GET /comments/{post_id}` handler. Before the existing query work, add:
```php
$postId = (int) $request['post_id'];
$page   = max(1, (int) $request->get_param('page'));
$sort   = in_array($request->get_param('sort'), ['newest','oldest','popular'], true)
    ? $request->get_param('sort') : 'newest';

$cacheKey = \BigBrotherJunkies\Data\Cache\CommentsReadCache::versionedKey($postId, $page, $sort);
$cached = wp_cache_get($cacheKey, 'bbj_v2');
if ($cached !== false) {
    return new \WP_REST_Response($cached, 200);
}
```
After computing the response payload (BEFORE returning), add:
```php
wp_cache_set($cacheKey, $payload, 'bbj_v2', 60);
```
Replace `$payload` with the actual variable name in the existing handler.

- [ ] **Step 4: Manual smoke — verify cache hit**

In a logged-in browser console:
```js
const t0 = performance.now();
await fetch('/wp-json/bbjd/v1/comments/1?page=1&sort=newest').then(r=>r.json());
const t1 = performance.now();
await fetch('/wp-json/bbjd/v1/comments/1?page=1&sort=newest').then(r=>r.json());
const t2 = performance.now();
console.log('cold:', t1-t0, 'warm:', t2-t1);
```
Expected: warm hit < 50% of cold hit time.

- [ ] **Step 5: Manual smoke — verify invalidation**

Post a new comment to post 1 via REST. Re-run the warm fetch — verify the new comment appears (cache busted by `wp_insert_comment` hook).

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Cache/CommentsReadCache.php \
         wp-content/plugins/bigbrotherjunkies-data/Plugin.php \
         wp-content/plugins/bigbrotherjunkies-data/src/Routes/CommentRoutes.php
git commit -m "feat(comments): object cache for /comments/{post_id} reads"
```

---

## Phase 2 — PHP island

### Task 5: `inc/comments-island.php` — filter, enqueue, localize

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/inc/comments-island.php`
- Modify: `wp-content/themes/bbj-v2-theme/inc/setup.php` (require_once the new file)

- [ ] **Step 1: Implement the file**

```php
<?php
/**
 * Comments island — replaces comments_template() with the React island placeholder.
 */

if (!defined('ABSPATH')) { exit; }

add_filter('comments_template', 'bbj_v2_comments_island_template', 20);
function bbj_v2_comments_island_template(string $original): string {
    if (!comments_open()) {
        return $original;
    }
    if (!empty($_GET['bbjcomments']) && $_GET['bbjcomments'] === 'plain') {
        return get_template_directory() . '/template-parts/comments/plain-fallback.php';
    }
    return get_template_directory() . '/template-parts/comments/island-placeholder.php';
}

add_action('wp_enqueue_scripts', 'bbj_v2_comments_island_enqueue');
function bbj_v2_comments_island_enqueue(): void {
    if (!is_singular() || !comments_open()) return;
    if (!empty($_GET['bbjcomments']) && $_GET['bbjcomments'] === 'plain') return;

    $themeUri  = get_template_directory_uri();
    $buildPath = get_template_directory() . '/build/comments/bootstrap.js';
    $version   = file_exists($buildPath) ? (string) filemtime($buildPath) : '1';

    wp_enqueue_script(
        'bbj-comments-bootstrap',
        $themeUri . '/build/comments/bootstrap.js',
        [],
        $version,
        true
    );

    $user = wp_get_current_user();
    wp_localize_script('bbj-comments-bootstrap', 'bbjComments', [
        'user' => $user && $user->ID > 0 ? [
            'id'           => (int) $user->ID,
            'display_name' => $user->display_name,
            'avatar_url'   => get_avatar_url($user->ID, ['size' => 80]),
            'rank'         => function_exists('bbjd_get_user_rank') ? bbjd_get_user_rank($user->ID) : null,
            'can_moderate' => current_user_can('moderate_comments'),
        ] : null,
        'nonce'           => wp_create_nonce('bbj_comments'),
        'nonceRefreshUrl' => esc_url_raw(rest_url('bbjd/v1/auth/refresh-nonce')),
        'endpoints'       => ['base' => esc_url_raw(rest_url('bbjd/v1'))],
        'config'          => ['perPage' => 20, 'maxDepth' => 3, 'sortDefault' => 'newest'],
        'postId'          => (int) get_queried_object_id(),
    ]);
}
```

- [ ] **Step 2: Wire into `setup.php`**

Open `wp-content/themes/bbj-v2-theme/inc/setup.php`, add near the other `require_once` lines:
```php
require_once get_template_directory() . '/inc/comments-island.php';
```

- [ ] **Step 3: Smoke — confirm filter is taking effect**

Reload any single post in browser. View source. Look for the comments area — it should be empty (placeholder doesn't exist yet → file_get_contents fail). Console shouldn't show JS errors.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/comments-island.php \
         wp-content/themes/bbj-v2-theme/inc/setup.php
git commit -m "feat(comments): wp_localize bbjComments + comments_template filter"
```

---

### Task 6: SSR placeholder partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/comments/island-placeholder.php`

- [ ] **Step 1: Implement the placeholder**

```php
<?php
/**
 * Comment island placeholder — server-rendered shell. The React island
 * mounts into #bbj-comments-root once the bundle hydrates.
 */

if (!defined('ABSPATH')) { exit; }

$post_id      = (int) get_queried_object_id();
$count        = (int) get_comments_number($post_id);
$can_comment  = is_user_logged_in() ? 1 : 0;
$comment_q    = isset($_GET['comment']) ? (int) $_GET['comment'] : 0;
?>
<section
    id="bbj-comments-root"
    class="bbj-card bbj-comments-island"
    data-post-id="<?php echo esc_attr($post_id); ?>"
    data-comment-count="<?php echo esc_attr($count); ?>"
    data-can-comment="<?php echo esc_attr($can_comment); ?>"
    data-comments-open="1"
    data-permalink-comment="<?php echo esc_attr($comment_q); ?>"
    aria-label="<?php esc_attr_e('Comments', 'bbj-v2-theme'); ?>"
>
    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <?php
            printf(
                esc_html(_n('%d Comment', '%d Comments', $count, 'bbj-v2-theme')),
                $count
            );
            ?>
        </h2>
    </div>
    <div class="bbj-comments-skeleton text-sm text-gray-500 dark:text-gray-400" aria-hidden="true">
        <?php esc_html_e('Loading comments…', 'bbj-v2-theme'); ?>
    </div>
    <noscript>
        <p class="mt-4">
            <a href="<?php echo esc_url(add_query_arg('bbjcomments', 'plain')); ?>" class="text-primary-500 underline">
                <?php esc_html_e('View comments →', 'bbj-v2-theme'); ?>
            </a>
        </p>
    </noscript>
</section>
```

- [ ] **Step 2: Smoke — verify the placeholder renders**

Reload a post page. Inspect — should see `<section id="bbj-comments-root">` with the count + skeleton. View source: no `<script>` for `bbj-comments-bootstrap` yet (no build output exists).

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/comments/island-placeholder.php
git commit -m "feat(comments): SSR island placeholder"
```

---

### Task 7: `?bbjcomments=plain` fallback partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/comments/plain-fallback.php`

- [ ] **Step 1: Implement the fallback**

```php
<?php
/**
 * Plain-server fallback for the comment island.
 * Triggered by ?bbjcomments=plain — used by:
 *   • no-JS readers
 *   • bundle-load failure recovery (3 retries)
 *   • moderator quick-scan view
 */

if (!defined('ABSPATH')) { exit; }
?>
<section id="comments" class="bbj-card bbj-comments-plain">
    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <?php
            $cnt = (int) get_comments_number();
            printf(esc_html(_n('%d Comment', '%d Comments', $cnt, 'bbj-v2-theme')), $cnt);
            ?>
            <span class="ml-2 text-xs uppercase tracking-wider text-gray-400">(<?php esc_html_e('Plain View', 'bbj-v2-theme'); ?>)</span>
        </h2>
    </div>

    <?php if (have_comments()) : ?>
        <ol class="space-y-4">
            <?php wp_list_comments(['style' => 'ol', 'avatar_size' => 40, 'short_ping' => true]); ?>
        </ol>
        <?php the_comments_pagination(['mid_size' => 1]); ?>
    <?php else : ?>
        <p class="text-sm text-gray-500"><?php esc_html_e('No comments yet.', 'bbj-v2-theme'); ?></p>
    <?php endif; ?>

    <?php if (is_user_logged_in()) : ?>
        <?php comment_form(['class_form' => 'space-y-4 mt-6', 'class_submit' => 'btn-primary']); ?>
    <?php else : ?>
        <p class="mt-4 text-sm">
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="text-primary-500 underline">
                <?php esc_html_e('Log in to comment →', 'bbj-v2-theme'); ?>
            </a>
        </p>
    <?php endif; ?>
</section>
```

- [ ] **Step 2: Smoke — verify the fallback renders**

Visit any single post with `?bbjcomments=plain` appended. Should see the plain comment list + form (or empty state).

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/comments/plain-fallback.php
git commit -m "feat(comments): ?bbjcomments=plain fallback partial"
```

---

## Phase 3 — React infrastructure

### Task 8: Folder scaffold + webpack entry config

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/` (directory)
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/` (directory)
- Create: `wp-content/themes/bbj-v2-theme/src/comments/hooks/` (directory)
- Create: `wp-content/themes/bbj-v2-theme/src/comments/lib/` (directory)
- Modify: `wp-content/themes/bbj-v2-theme/package.json` — add npm scripts for comments build
- Create: `wp-content/themes/bbj-v2-theme/webpack.comments.config.js`

- [ ] **Step 1: Create folders + placeholder index files**

```bash
mkdir -p wp-content/themes/bbj-v2-theme/src/comments/components
mkdir -p wp-content/themes/bbj-v2-theme/src/comments/hooks
mkdir -p wp-content/themes/bbj-v2-theme/src/comments/lib
mkdir -p wp-content/themes/bbj-v2-theme/build/comments
```

- [ ] **Step 2: Add webpack config for the three chunks**

Create `wp-content/themes/bbj-v2-theme/webpack.comments.config.js`:
```js
const defaults = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
  ...defaults,
  entry: {
    bootstrap: path.resolve(__dirname, 'src/comments/bootstrap.js'),
    main:      path.resolve(__dirname, 'src/comments/main.js'),
    composer:  path.resolve(__dirname, 'src/comments/composer.js'),
  },
  output: {
    ...defaults.output,
    path: path.resolve(__dirname, 'build/comments'),
    filename: '[name].js',
    chunkFilename: 'chunk.[name].[contenthash:8].js',
    publicPath: '/wp-content/themes/bbj-v2-theme/build/comments/',
  },
};
```

- [ ] **Step 3: Add npm scripts**

Modify `wp-content/themes/bbj-v2-theme/package.json`. Add to `scripts`:
```json
"comments:build": "wp-scripts build --config webpack.comments.config.js",
"comments:dev":   "wp-scripts start --config webpack.comments.config.js"
```

- [ ] **Step 4: Verify scripts are recognized**

Run from theme dir: `npm run comments:build`
Expected: error from webpack about missing entry files (we'll create them next). The script invocation itself should be recognized.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/webpack.comments.config.js \
         wp-content/themes/bbj-v2-theme/package.json
git commit -m "build(comments): wp-scripts entry config for bootstrap/main/composer chunks"
```

---

### Task 9: `bootstrap.js` — IO observer + dynamic import

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/bootstrap.js`

- [ ] **Step 1: Implement bootstrap**

```js
/**
 * Comments island bootstrap.
 * - Watches #bbj-comments-root with IntersectionObserver
 * - Lazy-imports main.js when within 500px of viewport
 * - Retries up to 3 times on import failure, then falls back to ?bbjcomments=plain
 */

const ROOT_ID = 'bbj-comments-root';
const MAX_RETRIES = 3;

function fallbackToPlain(root) {
  const fallbackHref = new URL(window.location.href);
  fallbackHref.searchParams.set('bbjcomments', 'plain');
  root.innerHTML =
    '<div class="text-sm text-gray-600">' +
      'Comments couldn\'t load. ' +
      '<a class="text-primary-500 underline" href="' + fallbackHref.toString() + '">View on full page →</a>' +
    '</div>';
}

function showRetry(root, onRetry) {
  root.innerHTML =
    '<div class="text-sm text-gray-600">' +
      'Comments couldn\'t load. ' +
      '<button type="button" class="text-primary-500 underline" id="bbj-comments-retry">Retry</button>' +
    '</div>';
  document.getElementById('bbj-comments-retry').addEventListener('click', onRetry, { once: true });
}

function loadMain(root, attempt = 1) {
  import(/* webpackChunkName: "main" */ './main.js')
    .then(({ default: mount }) => mount(root, window.bbjComments))
    .catch((err) => {
      console.error('[bbj-comments] main chunk load failed', { attempt, err });
      if (attempt >= MAX_RETRIES) {
        fallbackToPlain(root);
      } else {
        showRetry(root, () => loadMain(root, attempt + 1));
      }
    });
}

function init() {
  const root = document.getElementById(ROOT_ID);
  if (!root || !window.bbjComments) return;
  if (!('IntersectionObserver' in window)) {
    loadMain(root);
    return;
  }
  const io = new IntersectionObserver((entries) => {
    if (entries.some((e) => e.isIntersecting)) {
      io.disconnect();
      loadMain(root);
    }
  }, { rootMargin: '500px 0px' });
  io.observe(root);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}
```

- [ ] **Step 2: Implement a temporary `main.js` shell so the build succeeds**

Create `wp-content/themes/bbj-v2-theme/src/comments/main.js`:
```js
export default function mount(root /* , data */) {
  root.innerHTML = '<div class="text-sm text-gray-500">[bbj-comments] island booted</div>';
}
```

- [ ] **Step 3: Implement an empty `composer.js` placeholder**

Create `wp-content/themes/bbj-v2-theme/src/comments/composer.js`:
```js
export default {};
```

- [ ] **Step 4: Build and smoke**

Run from theme dir: `npm run comments:build`
Expected: build succeeds, three files in `build/comments/`: `bootstrap.js`, `main.js`, `composer.js`.

Reload a single post in browser, scroll to comments. Should see "[bbj-comments] island booted" replacing the skeleton.

- [ ] **Step 5: Verify lazy-load in DevTools**

In DevTools → Network, filter by `comments/`. Reload the post but DON'T scroll. Verify `bootstrap.js` loaded but `main.js` did NOT. Now scroll near comments — verify `main.js` request fires.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/bootstrap.js \
         wp-content/themes/bbj-v2-theme/src/comments/main.js \
         wp-content/themes/bbj-v2-theme/src/comments/composer.js \
         wp-content/themes/bbj-v2-theme/build/comments/
git commit -m "feat(comments): bootstrap.js with IO + lazy import + retry"
```

---

### Task 10: `useBbjUser` hook

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/hooks/useBbjUser.js`

- [ ] **Step 1: Implement the hook**

```js
import { useEffect, useState, useCallback } from 'react';

const AUTH_EVENT = 'bbj:auth:changed';
const ME_PATH = '/auth/me';

export function useBbjUser() {
  const initial = (typeof window !== 'undefined' && window.bbjComments && window.bbjComments.user) || null;
  const [user, setUser] = useState(initial);

  const refresh = useCallback(async () => {
    if (!window.bbjComments) return;
    try {
      const res = await fetch(window.bbjComments.endpoints.base + ME_PATH, {
        credentials: 'include',
        headers: { 'X-BBJ-Nonce': window.bbjComments.nonce },
      });
      if (res.ok) {
        const data = await res.json();
        setUser(data.user || null);
        window.bbjComments.user = data.user || null;
      } else if (res.status === 401) {
        setUser(null);
        window.bbjComments.user = null;
      }
    } catch (err) {
      console.error('[bbj-comments] auth refresh failed', err);
    }
  }, []);

  useEffect(() => {
    const handler = () => refresh();
    window.addEventListener(AUTH_EVENT, handler);
    return () => window.removeEventListener(AUTH_EVENT, handler);
  }, [refresh]);

  return { user, isAuthenticated: !!user, refresh };
}
```

- [ ] **Step 2: Smoke — temporary use in main.js**

Modify `src/comments/main.js`:
```js
import React from 'react';
import { createRoot } from 'react-dom/client';
import { useBbjUser } from './hooks/useBbjUser.js';

function Probe() {
  const { user, isAuthenticated } = useBbjUser();
  return React.createElement('div', { className: 'text-sm' },
    isAuthenticated ? `Hi, ${user.display_name}` : 'Anonymous reader'
  );
}

export default function mount(root) {
  createRoot(root.querySelector('.bbj-comments-skeleton') || root).render(React.createElement(Probe));
}
```

Build: `npm run comments:build`. Reload post. Should see "Hi, Steve" if logged in or "Anonymous reader" otherwise.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/hooks/useBbjUser.js \
         wp-content/themes/bbj-v2-theme/src/comments/main.js
git commit -m "feat(comments): useBbjUser hook reading wp_localize data"
```

---

### Task 11: `bbjAuthFetch` helper

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/lib/bbjAuthFetch.js`

- [ ] **Step 1: Implement the helper**

```js
/**
 * Authenticated fetch wrapper for bbjd/v1.
 * - Always sends X-BBJ-Nonce.
 * - Optionally sends Authorization: Bearer if window.bbjComments.jwt is set.
 * - Auto-refreshes nonce once on 401 + rest_cookie_invalid_nonce, retries.
 * - Throws structured { code, message, status } on error.
 */

let nonceRefreshInflight = null;

async function refreshNonce() {
  if (nonceRefreshInflight) return nonceRefreshInflight;
  nonceRefreshInflight = (async () => {
    const res = await fetch(window.bbjComments.nonceRefreshUrl, {
      credentials: 'include',
    });
    if (!res.ok) throw new Error('nonce refresh failed: ' + res.status);
    const data = await res.json();
    window.bbjComments.nonce = data.nonce;
    return data.nonce;
  })()
    .finally(() => { nonceRefreshInflight = null; });
  return nonceRefreshInflight;
}

function authHeaders() {
  const headers = { 'X-BBJ-Nonce': window.bbjComments.nonce };
  if (window.bbjComments.jwt) headers['Authorization'] = 'Bearer ' + window.bbjComments.jwt;
  return headers;
}

export async function bbjAuthFetch(path, options = {}) {
  const url = window.bbjComments.endpoints.base + path;
  const init = {
    credentials: 'include',
    ...options,
    headers: { ...authHeaders(), ...(options.headers || {}) },
  };
  if (init.body && typeof init.body === 'object' && !(init.body instanceof FormData)) {
    init.headers['Content-Type'] = init.headers['Content-Type'] || 'application/json';
    init.body = JSON.stringify(init.body);
  }

  let res = await fetch(url, init);

  if (res.status === 401) {
    const cloned = res.clone();
    let body;
    try { body = await cloned.json(); } catch { body = null; }
    if (body && body.code === 'rest_cookie_invalid_nonce') {
      try {
        await refreshNonce();
        init.headers = { ...authHeaders(), ...(options.headers || {}) };
        res = await fetch(url, init);
      } catch (err) {
        const e = new Error('Authentication required'); e.status = 401; e.code = 'auth_required';
        window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'nonce_refresh_failed' } }));
        throw e;
      }
    }
  }

  if (!res.ok) {
    let body;
    try { body = await res.json(); } catch { body = null; }
    const err = new Error((body && body.message) || ('Request failed: ' + res.status));
    err.status = res.status;
    err.code = (body && body.code) || 'http_error';
    err.data = body && body.data;
    throw err;
  }

  if (res.status === 204) return null;
  return res.json();
}
```

- [ ] **Step 2: Smoke — wire into the probe in main.js**

Update `src/comments/main.js` to fetch the comment list:
```js
import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { bbjAuthFetch } from './lib/bbjAuthFetch.js';

function Probe() {
  const [count, setCount] = useState(null);
  const [err, setErr] = useState(null);
  useEffect(() => {
    bbjAuthFetch(`/comments/${window.bbjComments.postId}?page=1&per_page=20&sort=newest`)
      .then((data) => setCount(Array.isArray(data) ? data.length : (data.comments || []).length))
      .catch((e) => setErr(e.message));
  }, []);
  if (err) return React.createElement('div', { className: 'text-sm text-red-600' }, 'Error: ' + err);
  if (count === null) return React.createElement('div', { className: 'text-sm' }, 'Loading…');
  return React.createElement('div', { className: 'text-sm' }, `Fetched ${count} comments`);
}

export default function mount(root) {
  createRoot(root.querySelector('.bbj-comments-skeleton') || root).render(React.createElement(Probe));
}
```

Build + reload. Should show "Fetched N comments" on a post that has comments.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/lib/bbjAuthFetch.js \
         wp-content/themes/bbj-v2-theme/src/comments/main.js
git commit -m "feat(comments): bbjAuthFetch with nonce refresh + structured errors"
```

---

### Task 12: `api.js` — endpoint wrappers

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/lib/api.js`

- [ ] **Step 1: Implement endpoint wrappers**

```js
import { bbjAuthFetch } from './bbjAuthFetch.js';

export const list = (postId, { page = 1, perPage = 20, sort = 'newest' } = {}) =>
  bbjAuthFetch(`/comments/${postId}?page=${page}&per_page=${perPage}&sort=${sort}`);

export const create = ({ postId, content, parentId = 0, mediaId = null }) =>
  bbjAuthFetch('/comments', {
    method: 'POST',
    body: { post_id: postId, content, parent_id: parentId, media_id: mediaId },
  });

export const update = (commentId, { content }) =>
  bbjAuthFetch(`/comments/${commentId}`, { method: 'PUT', body: { content } });

export const remove = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}`, { method: 'DELETE' });

export const vote = (commentId, voteType) =>
  bbjAuthFetch(`/comments/${commentId}/vote`, { method: 'POST', body: { vote_type: voteType } });

export const myVote = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}/my-vote`);

export const report = (commentId, { reason, details = '' }) =>
  bbjAuthFetch(`/comments/${commentId}/report`, { method: 'POST', body: { reason, details } });

export const reactAdd = (commentId, reactionType) =>
  bbjAuthFetch(`/comments/${commentId}/reactions`, { method: 'POST', body: { reaction_type: reactionType } });

export const reactRemove = (commentId, reactionType) =>
  bbjAuthFetch(`/comments/${commentId}/reactions`, { method: 'DELETE', body: { reaction_type: reactionType } });

export const pin = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}/pin`, { method: 'POST' });

export const unpin = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}/pin`, { method: 'DELETE' });

export const uploadMedia = (file) => {
  const fd = new FormData();
  fd.append('file', file);
  return bbjAuthFetch('/comments/media', { method: 'POST', body: fd });
};

export const giphySearch = (q, limit = 20, offset = 0) =>
  bbjAuthFetch(`/comments/media/giphy/search?q=${encodeURIComponent(q)}&limit=${limit}&offset=${offset}`);

export const userSearch = (q, limit = 10) =>
  bbjAuthFetch(`/users/search?q=${encodeURIComponent(q)}&limit=${limit}`);

export const userRank = (userId) =>
  bbjAuthFetch(`/users/${userId}/rank`);

export const userProfile = (userId) =>
  bbjAuthFetch(`/users/${userId}/profile`);
```

- [ ] **Step 2: Smoke — switch the probe to use `list`**

In `src/comments/main.js`, replace `bbjAuthFetch(...)` with:
```js
import * as commentApi from './lib/api.js';
// ...
commentApi.list(window.bbjComments.postId).then(...)
```

Build + reload. Same behavior as before.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/lib/api.js \
         wp-content/themes/bbj-v2-theme/src/comments/main.js
git commit -m "feat(comments): api.js endpoint wrappers"
```

---

### Task 13: `rankConfig.js` — static rank icon/color map

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/lib/rankConfig.js`

- [ ] **Step 1: Implement**

Source: `C:\xampp\htdocs\bbj-app\src\components\comments\RankBadge.jsx` (the static color/icon map at top of file). Copy the constant maps verbatim into a standalone file:

```js
// Mirror of bbj-app's RankBadge static config. Update both if ranks change.
export const RANK_ICONS = {
  crown:  '👑',
  medal:  '🥇',
  gem:    '💎',
  shield: '🛡️',
  star:   '⭐',
  trophy: '🏆',
};

export const RANK_COLORS = {
  orange: 'bg-orange-100 text-orange-900',
  cyan:   'bg-cyan-100 text-cyan-900',
  yellow: 'bg-yellow-100 text-yellow-900',
  purple: 'bg-purple-100 text-purple-900',
  teal:   'bg-teal-100 text-teal-900',
  red:    'bg-red-100 text-red-900',
  blue:   'bg-blue-100 text-blue-900',
  amber:  'bg-amber-100 text-amber-900',
  pink:   'bg-pink-100 text-pink-900',
};

export const RANK_SIZES = {
  sm: 'text-[10px] px-1.5 py-0.5',
  md: 'text-xs px-2 py-0.5',
  lg: 'text-sm px-2.5 py-1',
};
```

Open `bbj-app/src/components/comments/RankBadge.jsx` and verify the keys match. If any drift, prefer the bbj-app source values (it's the live config).

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/lib/rankConfig.js
git commit -m "feat(comments): rankConfig.js static badge config"
```

---

### Task 14: `useToast` hook

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/hooks/useToast.js`

- [ ] **Step 1: Implement minimal toast hook + portal**

```js
import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

let pushExternal = null;

export function useToast() {
  return {
    push: (msg, opts = {}) => pushExternal && pushExternal(msg, opts),
  };
}

export function ToastHost() {
  const [items, setItems] = useState([]);
  const [host] = useState(() => {
    if (typeof document === 'undefined') return null;
    let el = document.getElementById('bbj-comments-toasts');
    if (!el) {
      el = document.createElement('div');
      el.id = 'bbj-comments-toasts';
      el.className = 'fixed bottom-4 right-4 z-50 space-y-2 pointer-events-none';
      document.body.appendChild(el);
    }
    return el;
  });

  const push = useCallback((msg, { kind = 'info', ttl = 4000 } = {}) => {
    const id = Math.random().toString(36).slice(2);
    setItems((prev) => [...prev, { id, msg, kind }]);
    setTimeout(() => setItems((prev) => prev.filter((t) => t.id !== id)), ttl);
  }, []);

  useEffect(() => { pushExternal = push; return () => { pushExternal = null; }; }, [push]);

  if (!host) return null;
  return createPortal(
    items.map((t) => React.createElement('div', {
      key: t.id,
      className: 'pointer-events-auto rounded-md px-3 py-2 shadow-md text-sm ' + (
        t.kind === 'error' ? 'bg-red-600 text-white' :
        t.kind === 'success' ? 'bg-emerald-600 text-white' :
        'bg-gray-900 text-white'
      ),
    }, t.msg)),
    host
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/hooks/useToast.js
git commit -m "feat(comments): useToast hook + ToastHost portal"
```

---

## Phase 4 — Port read-side components

For every port task in this phase, the workflow is:

1. Read the source component from `C:\xampp\htdocs\bbj-app\src\components\comments\<Name>.jsx`
2. Apply the standardized port transformations:
   - Remove `'use client';` directive
   - Remove `import Image from 'next/image';` and replace `<Image>` with `<img width={…} height={…}>`
   - Remove `import Link from 'next/link';` and replace `<Link href={...}>` with `<a href={...}>`
   - Remove `import { useRouter, usePathname } from 'next/navigation';` — replace router calls with `window.location` reads
   - Remove `import { useSession } from 'next-auth/react';` — replace with `import { useBbjUser } from '../hooks/useBbjUser.js';` and `const { user, isAuthenticated } = useBbjUser();`
   - Replace any direct `fetch(...)` to bbjd endpoints with calls to the `commentApi` wrappers (`import * as commentApi from '../lib/api.js';`)
   - Tailwind classes: keep as-is; bbj-v2-theme uses dashed names (e.g. `primary-500`) which match. If a class collides with theme globals (e.g. `.card`), prefix with `bbj-c-` (per `feedback_design_class_collisions` memory).
   - `dark:` classes preserved
3. Save to `wp-content/themes/bbj-v2-theme/src/comments/components/<Name>.jsx`
4. Build + smoke
5. Commit

---

### Task 15: Port `RankBadge`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/RankBadge.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\RankBadge.jsx` (~102 lines).

- [ ] **Step 2: Apply port transformations + import from `rankConfig.js`**

Save the transformed file to the destination. Replace any inline `RANK_ICONS` / `RANK_COLORS` / `RANK_SIZES` definitions with `import { RANK_ICONS, RANK_COLORS, RANK_SIZES } from '../lib/rankConfig.js';`.

- [ ] **Step 3: Smoke render in `main.js`**

Temporarily import + render: `<RankBadge rank={{ slug: 'expert', icon: 'crown', color: 'yellow', label: 'Expert' }} size="md" />`. Build + reload. Verify badge renders with the right color/icon.

- [ ] **Step 4: Revert the smoke change in main.js (we'll wire it properly in CommentSection)**

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/RankBadge.jsx
git commit -m "feat(comments): port RankBadge"
```

---

### Task 16: Port `StaffPickBadge`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/StaffPickBadge.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\StaffPickBadge.jsx` (~15 lines — small).

- [ ] **Step 2: Apply port transformations**

Save to destination. Likely just removes `'use client';` and is otherwise unchanged.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/StaffPickBadge.jsx
git commit -m "feat(comments): port StaffPickBadge"
```

---

### Task 17: Port `OnlineIndicator`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/OnlineIndicator.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\OnlineIndicator.jsx`.

- [ ] **Step 2: Apply port transformations + save**

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/OnlineIndicator.jsx
git commit -m "feat(comments): port OnlineIndicator"
```

---

### Task 18: Port `AuthorModal`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/AuthorModal.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\AuthorModal.jsx`.

- [ ] **Step 2: Apply port transformations**

In addition to the standard port: any direct calls to `/users/{id}/profile` get replaced with `commentApi.userProfile(id)`.

- [ ] **Step 3: Save + commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/AuthorModal.jsx
git commit -m "feat(comments): port AuthorModal"
```

---

### Task 19: Port `CommentSection`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/CommentSection.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\CommentSection.jsx` (~296 lines).

- [ ] **Step 2: Apply port transformations**

In addition to the standard port:
- Replace `useRouter().push(...)` permalink scroll logic with `history.replaceState` and `document.getElementById('comment-' + id).scrollIntoView({ behavior: 'smooth' })`.
- Read `postId` from props (passed by `mount(root, data)` — see Task 21).
- Read sort/page/perPage defaults from `window.bbjComments.config`.

- [ ] **Step 3: Save**

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/CommentSection.jsx
git commit -m "feat(comments): port CommentSection (orchestrator)"
```

---

### Task 20: Port `CommentCard`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/CommentCard.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\CommentCard.jsx` (~469 lines).

- [ ] **Step 2: Apply port transformations**

In addition to the standard port:
- The composer mount inside CommentCard (when user clicks Reply) MUST be a lazy import. Replace `import CommentForm from './CommentForm';` with:
  ```js
  const CommentForm = React.lazy(() => import(/* webpackChunkName: "composer" */ '../composer.js').then(m => ({ default: m.CommentForm })));
  ```
  And wrap usages with `<React.Suspense fallback={<div className="text-xs text-gray-400">Loading editor…</div>}>`.
- Same lazy treatment for `EmojiPicker` if used inline (e.g. via React quick-react).
- `react-icons` imports: keep as-is (we'll add `react-icons` to theme deps in Task 33).

- [ ] **Step 3: Save**

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/CommentCard.jsx
git commit -m "feat(comments): port CommentCard with lazy composer chunk"
```

---

### Task 21: Wire `mount(root, data)` to render `CommentSection` + ToastHost

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/comments/main.js`

- [ ] **Step 1: Replace the probe with the real mount**

```js
import React from 'react';
import { createRoot } from 'react-dom/client';
import CommentSection from './components/CommentSection.jsx';
import { ToastHost } from './hooks/useToast.js';

export default function mount(root, data) {
  const postId  = parseInt(root.dataset.postId, 10);
  const skeleton = root.querySelector('.bbj-comments-skeleton');
  if (skeleton) skeleton.remove();

  const target = document.createElement('div');
  root.appendChild(target);
  createRoot(target).render(
    React.createElement(React.Fragment, null,
      React.createElement(CommentSection, { postId, config: data.config }),
      React.createElement(ToastHost)
    )
  );
}
```

- [ ] **Step 2: Build + smoke**

Run: `npm run comments:build`. Reload a post with comments. Verify the comment list renders (no replies/composer yet). Open DevTools console, no errors.

- [ ] **Step 3: Smoke — punch list item 1 (logged-out read)**

Log out. Reload post. Verify list renders, sort/pagination work. Click upvote → should trigger auth modal (the modal won't open yet — verify `bbj:auth:open` event in DevTools console: `window.addEventListener('bbj:auth:open', e => console.log('would open:', e.detail))`).

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/main.js
git commit -m "feat(comments): mount CommentSection + ToastHost from main.js"
```

---

## Phase 5 — Port interaction components

### Task 22: Port `VoteButtons`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/VoteButtons.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\VoteButtons.jsx` (~80 lines).

- [ ] **Step 2: Apply port transformations**

In addition to the standard port:
- Replace direct `/comments/{id}/vote` calls with `commentApi.vote(id, type)`.
- On 401 caught error: `window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'vote' } }))`.
- On other errors: `useToast().push(err.message, { kind: 'error' })`.

- [ ] **Step 3: Save + smoke**

Build + reload. Click upvote on a comment as a logged-in user. Verify optimistic UI flip + persistence (reload page, vote should still be there). Verify `bbj_comment_votes` row in DB.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/VoteButtons.jsx
git commit -m "feat(comments): port VoteButtons w/ optimistic UI"
```

---

### Task 23: Port `ReactionButtons`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/ReactionButtons.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\ReactionButtons.jsx`.

- [ ] **Step 2: Apply port transformations**

Same standard port. Wire to `commentApi.reactAdd` / `commentApi.reactRemove`. Same auth-event + toast pattern as VoteButtons.

- [ ] **Step 3: Save + smoke**

Build + reload. Add a 👍 reaction on a comment. Verify `bbj_comment_reactions` row.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/ReactionButtons.jsx
git commit -m "feat(comments): port ReactionButtons"
```

---

### Task 24: Port `ReportModal`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/ReportModal.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\ReportModal.jsx` (~169 lines).

- [ ] **Step 2: Apply port transformations**

Standard port. Wire `submit` to `commentApi.report(id, { reason, details })`.

- [ ] **Step 3: Save + smoke**

Build + reload. Open report flow on a comment. Submit. Verify success toast + `bbj_comment_reports` row.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/ReportModal.jsx
git commit -m "feat(comments): port ReportModal"
```

---

## Phase 6 — Composer chunk

### Task 25: `composer.js` entry + lazy load wiring

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/comments/composer.js`

- [ ] **Step 1: Define the chunk's exports**

Replace the placeholder:
```js
export { default as CommentForm } from './components/CommentForm.jsx';
export { default as MediaUploader } from './components/MediaUploader.jsx';
export { default as GiphyPicker } from './components/GiphyPicker.jsx';
export { default as EmojiPicker } from './components/EmojiPicker.jsx';
export { default as MentionAutocomplete } from './components/MentionAutocomplete.jsx';
```

(The lazy-import in CommentCard already references these via `import('../composer.js').then(m => ({ default: m.CommentForm }))`.)

- [ ] **Step 2: Skip build/smoke until at least CommentForm exists**

We'll build after Task 26.

- [ ] **Step 3: No commit yet** — paired with Task 26.

---

### Task 26: Port `CommentForm`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/CommentForm.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\CommentForm.jsx` (~200 lines).

- [ ] **Step 2: Apply port transformations**

Standard port. Wire submit to `commentApi.create({ postId, content, parentId, mediaId })`. Picker components (Media/Giphy/Emoji/Mention) are sibling imports in the same chunk, so direct `import` is fine — no lazy-load inside this chunk.

- [ ] **Step 3: Save**

- [ ] **Step 4: Build + smoke**

Run: `npm run comments:build`. Reload post. Click "Reply" on a comment. Verify `composer.js` chunk loads (DevTools network tab). Type + submit a reply. Verify it appears in the thread + lands in `wp_comments`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/CommentForm.jsx \
         wp-content/themes/bbj-v2-theme/src/comments/composer.js
git commit -m "feat(comments): port CommentForm + composer chunk wiring"
```

---

### Task 27: Port `MentionAutocomplete`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/MentionAutocomplete.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\MentionAutocomplete.jsx`.

- [ ] **Step 2: Apply port transformations**

Standard port. Wire user search to `commentApi.userSearch(q, limit)`.

- [ ] **Step 3: Save + smoke**

Build + reload. In a reply textarea, type `@`. Verify dropdown appears with users. Select one — verify @username inserted. Submit — verify target user gets `bbj_notifications` row.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/MentionAutocomplete.jsx
git commit -m "feat(comments): port MentionAutocomplete"
```

---

### Task 28: Port `EmojiPicker`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/EmojiPicker.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\EmojiPicker.jsx`. Note any 3rd-party emoji libs (e.g. `emoji-mart`).

- [ ] **Step 2: If a 3rd-party lib is used, install it**

```bash
cd wp-content/themes/bbj-v2-theme
npm install <emoji-lib-from-step-1>
```

- [ ] **Step 3: Apply port transformations + save**

- [ ] **Step 4: Build + smoke**

Build + reload. Open emoji picker in composer. Insert. Verify works.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/EmojiPicker.jsx \
         wp-content/themes/bbj-v2-theme/package.json \
         wp-content/themes/bbj-v2-theme/package-lock.json
git commit -m "feat(comments): port EmojiPicker"
```

---

### Task 29: Port `MediaUploader`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/MediaUploader.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\MediaUploader.jsx`.

- [ ] **Step 2: Apply port transformations**

Standard port. Wire upload to `commentApi.uploadMedia(file)`.

- [ ] **Step 3: Save + smoke**

Build + reload. Open composer. Upload an image. Verify it attaches + post comment shows the image. Verify `bbj_comment_media` row.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/MediaUploader.jsx
git commit -m "feat(comments): port MediaUploader"
```

---

### Task 30: Port `GiphyPicker`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/comments/components/GiphyPicker.jsx`

- [ ] **Step 1: Read source**

Open `C:\xampp\htdocs\bbj-app\src\components\comments\GiphyPicker.jsx`.

- [ ] **Step 2: Apply port transformations**

Standard port. Wire search to `commentApi.giphySearch(q, limit, offset)`.

- [ ] **Step 3: Save + smoke**

Build + reload. Open composer → Giphy. Search "cat". Pick a GIF. Submit. Verify GIF renders in the thread.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/comments/components/GiphyPicker.jsx
git commit -m "feat(comments): port GiphyPicker"
```

---

## Phase 7 — Cross-template smoke + polish

### Task 31: Cross-template smoke

**Files:** None (manual test only)

- [ ] **Step 1: Smoke `single.php`** — load a regular post, scroll, hydrate, post a top-level comment, vote, react.

- [ ] **Step 2: Smoke `single-live-feed-updates.php`** — load a feed update post, same flow.

- [ ] **Step 3: Smoke `single-bigbrother-players.php`** — load a player profile, same flow.

- [ ] **Step 4: Smoke `single-bigbrother-seasons.php`** — load a season profile, same flow.

- [ ] **Step 5: Smoke `page.php`** — load any page with comments enabled, same flow.

- [ ] **Step 6: Smoke "comments closed"** — edit a post, uncheck "Allow comments", save, reload — verify the island doesn't render at all (filter falls through, no skeleton appears).

- [ ] **Step 7: Smoke `?bbjcomments=plain`** — append `?bbjcomments=plain` to any post URL, verify plain fallback renders + accepts comments via the WP form.

- [ ] **Step 8: Commit checklist + memory updates**

If smoke surfaces any breakage, file & fix per Task 32. Otherwise:

```bash
git commit --allow-empty -m "test(comments): cross-template smoke pass complete"
```

---

### Task 32: Bundle audit + perf budget verification

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/enqueue.php` (any tweaks discovered)

- [ ] **Step 1: Build production bundles**

Run: `npm run comments:build`

- [ ] **Step 2: Measure gzipped sizes**

```bash
gzip -k -c build/comments/bootstrap.js | wc -c
gzip -k -c build/comments/main.js      | wc -c
gzip -k -c build/comments/composer.js  | wc -c
```
Expected (from spec):
- bootstrap < 6 KB
- main < 60 KB
- composer < 30 KB

- [ ] **Step 3: If main > 60 KB, audit**

Run: `npx webpack-bundle-analyzer build/comments/main.stats.json` (after building with `--stats verbose`). Identify the largest deps. Common culprits: full lodash, moment.js, react-icons (use individual icon imports). Fix and rebuild.

- [ ] **Step 4: Lighthouse audit on staging**

Run a Lighthouse Performance audit on a single post (logged out) in incognito. Verify:
- LCP unaffected by comments (placeholder is HTML-only).
- CLS = 0 (placeholder reserves space).

- [ ] **Step 5: INP check on hot interactions**

In Chrome DevTools → Performance, record 5s while clicking upvote on multiple comments. Verify INP < 200ms.

- [ ] **Step 6: Commit any tuning**

```bash
git add wp-content/themes/bbj-v2-theme/
git commit -m "perf(comments): bundle audit + tune to perf budget"
```

---

### Task 33: Memory updates + spec close-out

**Files:**
- Create: `C:\Users\sbeli\.claude\projects\C--xampp-htdocs-bbj\memory\project_comments_rebuild_state.md`
- Create: `C:\Users\sbeli\.claude\projects\C--xampp-htdocs-bbj\memory\project_comments_rebuild_testing.md`
- Modify: `C:\Users\sbeli\.claude\projects\C--xampp-htdocs-bbj\memory\MEMORY.md` (add two index lines)

- [ ] **Step 1: Write `project_comments_rebuild_state.md`**

Capture: shipped date, branch, file inventory, the 3-chunk split, where wp_localize lives, the cookie+nonce header convention, Tailwind class collision exceptions if any, deferred items (notifications bell, real-time, edit history UI, admin moderation pane).

- [ ] **Step 2: Write `project_comments_rebuild_testing.md`**

Copy the 15-item punch list from the spec (`docs/superpowers/specs/2026-05-01-comments-rebuild-design.md` → "Manual test punch list"). Add a "How to clean up tasks at end of testing" section like `project_weekly_tracker_testing.md`.

- [ ] **Step 3: Add MEMORY.md index lines**

```
- [Comments Rebuild State](project_comments_rebuild_state.md) — Shipped <DATE>. React island, lazy hydrate, cookie+nonce auth, three-chunk bundle. Backend reuses bbjd/v1.
- [Comments Rebuild Testing](project_comments_rebuild_testing.md) — 15-item punch list for owner verification. Includes Tailwind class trap reminder + Akismet smoke.
```

- [ ] **Step 4: Commit (memory files are outside the repo — no git commit needed for those)**

For the spec close-out, no code commit needed. The implementation work itself is committed task-by-task above.

---

## Done

After Task 33: announce shipped, push to staging via `/push-staging`, and prompt owner to walk the punch list. Then move to the deferred admin-moderation-pane sprint.

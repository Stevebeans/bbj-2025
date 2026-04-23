# Feed Updates Admin Pane Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

## ⚠ Plan amendment (2026-04-23, mid-execution)

Discovered during Task 2 review: the BBJ plugin already has a **production-wide permissions system** — `BigBrotherJunkies\Data\Permissions\PermissionChecker` — with a `feed_updates` feature already defined in `DEFAULT_PERMISSIONS`. The native permissions UI in the admin already writes to `bbj_admin_permissions` option, which `PermissionChecker::getPermissionConfig()` merges over the defaults. This is the platform pattern used by AdRoutes, AdSettingsRoutes, AIRoutes, AnalyticsRoutes, ContentEngineRoutes, EditorRoutes, FacebookRoutes, NewsAggregatorRoutes, and AdminRoutes.

**Changes to this plan:**

1. **Task 1 (capability class) is SUPERSEDED** — the `FeedUpdatesCapability` class and the `bbj_v2_edit_feed_updates` WP core capability are not needed. Plug into `PermissionChecker` instead. Task 1's commits (`d1d5106`, `2a88379`) are reverted by the plan-amendment commit.
2. **Task 3 now uses `PermissionChecker::userCan('feed_updates')`** as the permission callback in `FeedUpdateRoutes`. No new capability.
3. **Task 4's helper is renamed** from `bbj_v2_require_capability(string $cap)` to `bbj_v2_require_permission(string $feature)` — delegates to `PermissionChecker::userCan()`.
4. Spec file `docs/superpowers/specs/2026-04-23-feed-updates-admin-pane-design.md` still refers to the cap; it is outdated on this single detail and will be updated after the build lands. The up-to-date gating approach is this amendment.

---

**Goal:** Build `/admin?tab=feed-updates` — a single-screen admin pane with a quick-post form at the top and a scannable list of the 50 most recent updates below, supporting inline edit + delete via new `/bbjd/v1/feed-updates/{id}` REST routes, gated by the existing `PermissionChecker::userCan('feed_updates')` feature permission.

**Architecture:** New pane template + vanilla JS file in the theme, new REST handlers (PUT/DELETE) in the plugin, and a refactor of `FeedUpdateRoutes::checkUpdaterPermission` to delegate to `PermissionChecker`. The existing `POST /bbjd/v1/feed-updates/create` endpoint is extended to accept user-written `title` / `details` / `update_type` while keeping existing `content`/`mode`-only callers (Next.js) working unchanged. The pane is server-rendered for the initial 50 rows; all mutations after that are JS fetch calls.

**Tech Stack:** WordPress 6.x, PHP 8, Tailwind CSS 3.4, vanilla JS (no build step, no jQuery).

**Testing:** Manual smoke-testing on `http://bbj.localhost/`. No automated tests this sprint — no test infra exists on theme/plugin side.

---

## Spec reference

`docs/superpowers/specs/2026-04-23-feed-updates-admin-pane-design.md`

---

## File structure

**Create:**
- `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-feed-updates.php` — the pane (form + list, server-rendered)
- `wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js` — vanilla JS client (fetch, DOM updates, toasts)

**Modify:**
- `wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php` — extend `create`, add PUT + DELETE, refactor permission check to `PermissionChecker::userCan('feed_updates')`
- `wp-content/themes/bbj-v2-theme/inc/admin-shell.php` — add `bbj_v2_require_permission(string $feature)` helper
- `wp-content/themes/bbj-v2-theme/page-admin.php` — dispatcher picks up `tab=feed-updates`
- `wp-content/themes/bbj-v2-theme/inc/enqueue.php` — conditional enqueue for `admin-feed-updates.js`

**Removed / no longer needed (per amendment):**
- ~~`wp-content/plugins/bigbrotherjunkies-data/src/Capabilities/FeedUpdatesCapability.php`~~ — reverted
- ~~`wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php`~~ wire-in of `FeedUpdatesCapability` — reverted

**Convention note:** The spec says `assets/js/`; the theme actually uses `/src/js/` for all vanilla JS (see `enqueue.php`). The plan follows existing convention — `/src/js/admin-feed-updates.js`.

---

### Task 1: ~~Capability class + bootstrap~~ — SUPERSEDED (see amendment)

**Status:** This task has been reverted. The BBJ platform already has a permissions system (`PermissionChecker`) with a `feed_updates` feature defined; a new WP core capability is redundant. The Task 3 permission refactor uses `PermissionChecker::userCan('feed_updates')` instead.

The original task body is retained below for historical record only — **do not re-implement.**

---

**Files:**
- ~~Create: `wp-content/plugins/bigbrotherjunkies-data/src/Capabilities/FeedUpdatesCapability.php`~~
- ~~Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php`~~

**Context:** A single custom WP cap (`bbj_v2_edit_feed_updates`) governs both the admin pane guard and the mutating REST endpoints. Seeded to `administrator` via a versioned one-shot so it runs on normal page loads too — not just `register_activation_hook` (which only fires when the plugin is clicked Activate in wp-admin, never on rsync deploys).

- [ ] **Step 1: Create the capability class**

Create `wp-content/plugins/bigbrotherjunkies-data/src/Capabilities/FeedUpdatesCapability.php` with this exact content:

```php
<?php

namespace BigBrotherJunkies\Data\Capabilities;

/**
 * Registers the `bbj_v2_edit_feed_updates` capability and seeds it on
 * the administrator role. The future native permissions grid in
 * Settings will grant/revoke this cap to other roles via
 * $role->add_cap() / $role->remove_cap(); this class does not need
 * to change when that lands.
 *
 * Uses a versioned one-shot (bbj_v2_caps_version option) so the
 * seeder runs on normal page loads after a deploy, not just on
 * plugin-activation click.
 */
class FeedUpdatesCapability
{
    public const CAP = 'bbj_v2_edit_feed_updates';
    public const VERSION_OPTION = 'bbj_v2_caps_version';
    public const CURRENT_VERSION = 1;

    /**
     * Hook into WordPress.
     */
    public function init(): void
    {
        add_action('init', [$this, 'maybeSeed'], 20);
    }

    /**
     * Seed the cap if not already seeded at the current version.
     * Idempotent — safe to run on every request.
     */
    public function maybeSeed(): void
    {
        $seeded = (int) get_option(self::VERSION_OPTION, 0);
        if ($seeded >= self::CURRENT_VERSION) {
            return;
        }

        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(self::CAP)) {
            $admin->add_cap(self::CAP);
        }

        update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
    }
}
```

- [ ] **Step 2: Wire into Plugin.php**

Open `wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php`.

At the top of the file, after the existing `use BigBrotherJunkies\Data\Taxonomies\UpdateLocationTaxonomy;` line (around line 64), add this use statement:

```php
use BigBrotherJunkies\Data\Capabilities\FeedUpdatesCapability;
```

Then in the `init()` method, find this line:

```php
        // Register custom taxonomies (update_type, etc.)
        $this->initTaxonomies();
```

Insert this block immediately BEFORE it:

```php
        // Register + seed custom capabilities (bbj_v2_edit_feed_updates, etc.)
        (new FeedUpdatesCapability())->init();

```

- [ ] **Step 3: Syntax check**

Run:
```bash
php -l wp-content/plugins/bigbrotherjunkies-data/src/Capabilities/FeedUpdatesCapability.php
php -l wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php
```

Both must report `No syntax errors detected`.

- [ ] **Step 4: Verify cap seeded**

Load any page on the site (e.g. `http://bbj.localhost/`) to trigger `init`. Then run:

```bash
php -r "define('WP_USE_THEMES', false); require 'wp-load.php'; \$r=get_role('administrator'); echo \$r->has_cap('bbj_v2_edit_feed_updates')?\"OK\n\":\"MISSING\n\"; echo 'version=' . get_option('bbj_v2_caps_version', 0) . \"\n\";" 2>&1 | tail -5
```

Expected:
```
OK
version=1
```

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Capabilities/FeedUpdatesCapability.php wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php
git commit -m "feat(plugin): add bbj_v2_edit_feed_updates capability"
```

---

### Task 2: Extend POST /create to accept title, details, update_type

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php`

**Context:** The existing create endpoint only accepts `content` + `mode` + social flags, and auto-generates titles like `"BB27 Feed Update - Jan 30, 3:45 PM PT"`. The admin pane needs to send a user-written headline (becomes `post_title`) + optional details (becomes `post_content`) + optional taxonomy term. Next.js integration must keep working — new params are optional, old behavior is the fallback.

- [ ] **Step 1: Extend createFeedUpdate method**

Open `wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php`.

Find the `createFeedUpdate` method (starts around line 110). Replace the block from the method signature down through the `wp_insert_post([...]);` call (inclusive) with this exact content:

```php
    public function createFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        // New admin-pane params (all optional for backward compat with Next.js)
        $adminTitle   = $request->get_param('title');
        $adminDetails = $request->get_param('details');
        $adminType    = $request->get_param('update_type'); // term_id (int) or slug (string)

        // Legacy param (Next.js sends this as post_content source)
        $legacyContent = $request->get_param('content');

        $mode = in_array($request->get_param('mode'), ['feed', 'show'])
            ? $request->get_param('mode')
            : 'feed';
        $postToBluesky = (bool) $request->get_param('post_to_bluesky');
        $postToFacebook = (bool) $request->get_param('post_to_facebook');

        // At minimum we need a title OR some content/details; otherwise nothing to save
        $hasTitle   = !empty($adminTitle);
        $hasContent = !empty($adminDetails) || !empty($legacyContent);
        if (!$hasTitle && !$hasContent) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Either a headline or content is required',
            ], 400);
        }

        // Resolve title: admin-written headline wins, else auto-generate from mode
        $postTitle = $hasTitle
            ? sanitize_text_field($adminTitle)
            : $this->generateTitle($mode);

        // Resolve content: admin's details field wins, else legacy `content` param
        $postContent = !empty($adminDetails)
            ? wp_kses_post($adminDetails)
            : wp_kses_post($legacyContent);

        // Create the post
        $postId = wp_insert_post([
            'post_title' => $postTitle,
            'post_content' => $postContent,
            'post_status' => 'publish',
            'post_type' => 'live-feed-updates',
            'meta_input' => [
                '_feed_update_mode' => $mode,
            ],
        ]);
```

Also find the line where the old method read `$content`:

```php
        $content = wp_kses_post($request->get_param('content'));
```

Delete that line (it's now redundant — all content handling is at the top of the method).

And find this line (still inside createFeedUpdate, later):

```php
        // Validate required fields
        if (empty($content)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Content is required',
            ], 400);
        }
```

Delete that entire `if` block (validation is now done up-front with the `$hasTitle || $hasContent` check).

And find this line (still inside createFeedUpdate):

```php
        // Generate SEO-friendly title: "BB27 Feed Update - Jan 30, 3:45 PM PT"
        $title = $this->generateTitle($mode);
```

Delete those two lines.

- [ ] **Step 2: Apply the update_type taxonomy term**

In `createFeedUpdate`, find this block (after the image upload block, before social posting):

```php
        // Get hashtag for social posts
        $hashtag = $this->getSeasonHashtag();
```

Insert this BEFORE it:

```php
        // Apply update_type taxonomy term if provided
        if (!empty($adminType)) {
            if (is_numeric($adminType)) {
                wp_set_object_terms($postId, (int) $adminType, 'update_type', false);
            } else {
                wp_set_object_terms($postId, sanitize_title($adminType), 'update_type', false);
            }
        }

```

- [ ] **Step 3: Syntax check**

Run:
```bash
php -l wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Backward-compat smoke test**

Post to the endpoint as Next.js would (content + mode only, no title):

```bash
curl -sS -X POST http://bbj.localhost/wp-json/bbjd/v1/feed-updates/create \
  -H "Content-Type: application/json" \
  -u "admin:PASSWORD_HERE_APP_PASSWORD" \
  -d '{"content":"Backward compat test","mode":"feed"}' | head -c 400
```

Expected: JSON response with `"success":true` and a title like `"BB27 Feed Update - ..."`. (You'll need an application password; create one at `http://bbj.localhost/wp-admin/users.php` if needed, or use curl cookie jar from an authenticated session. Alternatively, skip this curl and just verify the code path by calling from the admin pane in Task 6.)

Also verify the new params take effect:

```bash
curl -sS -X POST http://bbj.localhost/wp-json/bbjd/v1/feed-updates/create \
  -H "Content-Type: application/json" \
  -u "admin:PASSWORD_HERE_APP_PASSWORD" \
  -d '{"title":"Jag wins HOH","details":"Quick first-night HOH","mode":"feed"}' | head -c 400
```

Expected: response `update.title` is `"Jag wins HOH"`, slug is `"jag-wins-hoh"`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php
git commit -m "feat(api): /feed-updates/create accepts title + details + update_type"
```

---

### Task 3: Add PUT + DELETE endpoints, refactor permission check

**Files:**
- Modify: `wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php`

**Context:** Inline edit/delete in the admin pane needs dedicated endpoints. The PUT handler MUST NOT trigger social cross-posting — typo fixes shouldn't re-post to Bluesky/Facebook. The permission check flips from hardcoded role array to the platform-standard `PermissionChecker::userCan('feed_updates')` so the existing permissions UI (at `/admin/settings`, matrix of features × roles) drives access with zero code changes when checkboxes are ticked.

- [ ] **Step 1: Refactor checkUpdaterPermission to use PermissionChecker**

Open `wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php`.

At the top of the file, add this `use` statement alongside the other `use` lines (near `use BigBrotherJunkies\Data\Comments\AvatarUploader;`):

```php
use BigBrotherJunkies\Data\Permissions\PermissionChecker;
```

Find this method (around line 97):

```php
    public function checkUpdaterPermission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return !empty(array_intersect(self::ALLOWED_ROLES, $user->roles));
    }
```

Replace it entirely with:

```php
    public function checkUpdaterPermission(): bool
    {
        return PermissionChecker::userCan('feed_updates');
    }
```

Also remove the now-unused `ALLOWED_ROLES` constant at the top of the class (around line 20):

```php
    private const ALLOWED_ROLES = ['administrator', 'editor', 'updater', 'second_in_command'];
```

Delete that entire line.

**Why this is safe:** `PermissionChecker::userCan('feed_updates')` handles the `is_user_logged_in()` check internally (returns `false` if not logged in) and resolves roles from `getPermissionConfig()`, which merges the `bbj_admin_permissions` option over `DEFAULT_PERMISSIONS`. The default for `feed_updates` is `['administrator', 'updater']` — so this is a NARROWING from the old hardcoded `['administrator', 'editor', 'updater', 'second_in_command']`. If editors or second_in_commands need access, that's now configured in the permissions UI, not in code — which is the whole point of moving to this system.

- [ ] **Step 2: Register PUT + DELETE routes**

Still in `FeedUpdateRoutes.php`, find the `registerRoutes` method. After this block (around line 85-92):

```php
        // Get social API settings (for frontend to know what's configured)
        register_rest_route($namespace, '/feed-updates/social-config', [
            'methods' => 'GET',
            'callback' => [$this, 'getSocialConfig'],
            'permission_callback' => [$this, 'checkUpdaterPermission'],
        ]);
```

Insert these two new route registrations BEFORE the closing `}` of `registerRoutes`:

```php

        // Update a feed update (admin pane inline edit — no social re-post)
        register_rest_route($namespace, '/feed-updates/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateFeedUpdate'],
            'permission_callback' => [$this, 'checkUpdaterPermission'],
        ]);

        // Delete a feed update (force delete, no trash)
        register_rest_route($namespace, '/feed-updates/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteFeedUpdate'],
            'permission_callback' => [$this, 'checkUpdaterPermission'],
        ]);
```

- [ ] **Step 3: Add updateFeedUpdate handler**

Still in `FeedUpdateRoutes.php`, find the `deleteFeedUpdate` insertion point — there isn't one yet. Find the `getSingleFeedUpdate` method closing `}` (search for `public function voteFeedUpdate`; the line BEFORE is the closing `}` of `getSingleFeedUpdate`).

Insert the following two methods BEFORE `voteFeedUpdate`:

```php

    /**
     * Update an existing feed update (admin pane inline edit).
     * Does NOT trigger social cross-posting — typo fixes shouldn't re-post.
     */
    public function updateFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);
        if (!$post || $post->post_type !== 'live-feed-updates') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Feed update not found',
            ], 404);
        }

        $title   = $request->get_param('title');
        $details = $request->get_param('details');
        $type    = $request->get_param('update_type');

        $update = ['ID' => $id];
        if ($title !== null) {
            $update['post_title'] = sanitize_text_field((string) $title);
        }
        if ($details !== null) {
            $update['post_content'] = wp_kses_post((string) $details);
        }

        if (count($update) > 1) {
            $result = wp_update_post($update, true);
            if (is_wp_error($result)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message(),
                ], 500);
            }
        }

        if ($type !== null) {
            if (is_numeric($type)) {
                wp_set_object_terms($id, (int) $type, 'update_type', false);
            } elseif ($type === '') {
                wp_set_object_terms($id, [], 'update_type', false);
            } else {
                wp_set_object_terms($id, sanitize_title($type), 'update_type', false);
            }
        }

        // Clear caches so the archive + single-post pages reflect the edit
        do_action('breeze_clear_all_cache');

        $updated = get_post($id);
        return new \WP_REST_Response([
            'success' => true,
            'update' => $this->formatFeedUpdate($updated),
        ]);
    }

    /**
     * Force-delete a feed update (no trash).
     */
    public function deleteFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);
        if (!$post || $post->post_type !== 'live-feed-updates') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Feed update not found',
            ], 404);
        }

        $result = wp_delete_post($id, true);
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Delete failed',
            ], 500);
        }

        do_action('breeze_clear_all_cache');

        return new \WP_REST_Response(null, 204);
    }

```

- [ ] **Step 4: Syntax check**

```bash
php -l wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php
git commit -m "feat(api): add PUT + DELETE feed-updates routes; switch to PermissionChecker"
```

---

### Task 4: Admin shell — permission helper + dispatcher branch + conditional JS enqueue

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/admin-shell.php`
- Modify: `wp-content/themes/bbj-v2-theme/page-admin.php`
- Modify: `wp-content/themes/bbj-v2-theme/inc/enqueue.php`

**Context:** Three small wire-ins. The helper lets the pane line-1 guard delegate to `PermissionChecker`. The dispatcher branch routes `tab=feed-updates` to the new pane. The conditional enqueue loads `admin-feed-updates.js` only on this tab (avoids bloating other admin pages).

- [ ] **Step 1: Add the permission helper**

Open `wp-content/themes/bbj-v2-theme/inc/admin-shell.php`.

Find this closing brace of `bbj_v2_require_logged_in()`:

```php
function bbj_v2_require_logged_in(): void
{
    add_filter('wp_robots', 'wp_robots_no_robots');

    if (!is_user_logged_in()) {
        $current = home_url(add_query_arg([]));
        wp_safe_redirect(wp_login_url($current));
        exit;
    }
}
```

Insert this function immediately AFTER it:

```php

/**
 * Require a PermissionChecker feature permission. Redirects to login
 * if logged-out, 403 if logged-in but lacks the permission.
 * Delegates to BigBrotherJunkies\Data\Permissions\PermissionChecker
 * so the platform's permissions UI drives access.
 */
function bbj_v2_require_permission(string $feature): void
{
    add_filter('wp_robots', 'wp_robots_no_robots');

    if (!is_user_logged_in()) {
        $current = home_url(add_query_arg([]));
        wp_safe_redirect(wp_login_url($current));
        exit;
    }

    if (!\BigBrotherJunkies\Data\Permissions\PermissionChecker::userCan($feature)) {
        status_header(403);
        wp_die(
            esc_html__('You do not have permission to access this page.', 'bbj-v2-theme'),
            esc_html__('Access Denied', 'bbj-v2-theme'),
            ['response' => 403]
        );
    }
}
```

The fully-qualified `\BigBrotherJunkies\Data\Permissions\PermissionChecker::userCan()` call avoids needing a `use` statement in a procedural file. The class is autoloaded by the plugin's Composer autoloader, which is loaded before the theme.

- [ ] **Step 2: Wire dispatcher branch**

Open `wp-content/themes/bbj-v2-theme/page-admin.php`.

Find this block:

```php
            <?php elseif ($active_tab === 'settings'): ?>
                <?php get_template_part('template-parts/admin/pane-settings'); ?>
```

Insert this branch IMMEDIATELY BEFORE it:

```php
            <?php elseif ($active_tab === 'feed-updates'): ?>
                <?php
                bbj_v2_require_permission('feed_updates');
                get_template_part('template-parts/admin/pane-feed-updates');
                ?>
```

Note: `page-admin.php` already calls `bbj_v2_require_admin()` on line 1, so today this per-tab cap guard is defense-in-depth. It becomes the primary gate when the overall page-level guard is later relaxed (to let non-admins with the cap reach the shell). Out of scope for today.

- [ ] **Step 3: Add conditional JS enqueue**

Open `wp-content/themes/bbj-v2-theme/inc/enqueue.php`.

Find the end of the `bbj_v2_enqueue_assets` function (the closing `}` before the `// Preload Google Fonts` comment).

Insert this block INSIDE the function, just before its closing `}`:

```php

    // Admin Feed Updates pane — load JS only on that tab.
    if (is_page('admin') && get_query_var('tab') === 'feed-updates') {
        wp_enqueue_script(
            'bbj-v2-admin-feed-updates',
            BBJ_V2_THEME_URL . '/src/js/admin-feed-updates.js',
            [],
            bbj_v2_asset_ver('/src/js/admin-feed-updates.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }
```

- [ ] **Step 4: Syntax check**

```bash
php -l wp-content/themes/bbj-v2-theme/inc/admin-shell.php
php -l wp-content/themes/bbj-v2-theme/page-admin.php
php -l wp-content/themes/bbj-v2-theme/inc/enqueue.php
```

All three must report `No syntax errors detected`.

- [ ] **Step 5: Smoke test — stub renders**

Visit `http://bbj.localhost/admin/?tab=feed-updates` in a browser. Expected: currently falls through to the `pane-feed-updates.php` template part which doesn't exist yet, so you'll see a WP notice / empty content. **That's fine** — the dispatcher is wired; Task 5 creates the pane.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/admin-shell.php wp-content/themes/bbj-v2-theme/page-admin.php wp-content/themes/bbj-v2-theme/inc/enqueue.php
git commit -m "feat(theme): admin shell wires feed-updates tab + permission helper"
```

---

### Task 5: Pane template — form + list markup + localized config

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-feed-updates.php`

**Context:** The biggest single file. Renders the quick-post form at the top and the server-rendered list of 50 most-recent updates below. Emits a `<script>` tag with `window.BBJ_FEED` config (REST root, nonce, taxonomy terms, social config). Each `<li>` row contains both a display subtree and a hidden edit subtree so the JS can toggle between them with a CSS class change.

- [ ] **Step 1: Create the pane template**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-feed-updates.php` with this exact content:

```php
<?php
/**
 * Admin shell — Feed Updates pane.
 *
 * Single-screen UI for live-feed shifts: quick-post form at top,
 * scannable list of the 50 most recent updates below. Hydrates the
 * list server-side so the page is readable even if JS fails to load.
 * All mutations after initial render happen via fetch in admin-feed-updates.js.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 50 most recent feed updates
$recent = get_posts([
    'post_type'   => 'live-feed-updates',
    'post_status' => 'publish',
    'numberposts' => 50,
    'orderby'     => 'date',
    'order'       => 'DESC',
]);

// Update type taxonomy terms for the category select
$terms = get_terms([
    'taxonomy'   => 'update_type',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);
if (is_wp_error($terms)) {
    $terms = [];
}

// Social config — so we can disable checkboxes when not configured
$social_options = get_option('bbjd_social_settings', []);
$bluesky_configured = !empty($social_options['bluesky_handle']) && !empty($social_options['bluesky_app_password']);
$facebook_configured = !empty($social_options['facebook_page_token']);

// Preload per-row data the JS will need when editing (term id, raw values)
function bbj_v2_feed_pane_row_data(\WP_Post $post): array
{
    $term_ids = wp_get_object_terms($post->ID, 'update_type', ['fields' => 'ids']);
    if (is_wp_error($term_ids)) {
        $term_ids = [];
    }
    $first_term_id = !empty($term_ids) ? (int) $term_ids[0] : 0;

    $term_name = '';
    if ($first_term_id > 0) {
        $t = get_term($first_term_id, 'update_type');
        if ($t && !is_wp_error($t)) {
            $term_name = (string) $t->name;
        }
    }

    global $wpdb;
    $vote_table = $wpdb->prefix . 'bbj_feed_ratings';
    $total_votes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(rating), 0) FROM {$vote_table} WHERE update_id = %d",
        $post->ID
    ));

    $social_results = get_post_meta($post->ID, '_social_posting_results', true);
    $thumb = get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: '';

    return [
        'title'       => get_the_title($post),
        'content'     => $post->post_content,
        'term_id'     => $first_term_id,
        'term_name'   => $term_name,
        'time_ago'    => human_time_diff(get_post_timestamp($post), current_time('timestamp')) . ' ago',
        'votes'       => $total_votes,
        'social'      => is_array($social_results) ? $social_results : null,
        'thumb'       => $thumb,
    ];
}
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        Feed Updates
    </h1>
    <p class="text-sm text-stone-600 dark:text-slate-400 mb-6">
        Post and manage live-feed updates. New updates appear at the top of the list instantly.
    </p>

    <!-- Quick-post form -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Quick Post
    </h2>
    <form id="bbj-feed-form" class="mb-8 space-y-3" enctype="multipart/form-data" novalidate>
        <div>
            <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-headline">
                Headline <span class="text-accent-red">*</span>
            </label>
            <input type="text" id="bbj-feed-headline" name="title" required
                   class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                   placeholder="e.g. Jag wins HOH">
            <p class="hidden text-xs text-accent-red mt-1" data-headline-error>Headline required.</p>
        </div>

        <div>
            <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-details">
                Details <span class="text-stone-400 text-xs font-normal">(optional)</span>
            </label>
            <textarea id="bbj-feed-details" name="details" rows="3"
                      class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                      placeholder="Extra context — who, where, what was said"></textarea>
        </div>

        <div>
            <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-image">
                Image <span class="text-stone-400 text-xs font-normal">(optional)</span>
            </label>
            <input type="file" id="bbj-feed-image" name="image" accept="image/*"
                   class="block w-full text-sm text-stone-700 dark:text-slate-300">
            <div class="hidden mt-2" data-image-preview>
                <img alt="" class="h-20 w-auto border border-stone-200 dark:border-slate-700">
                <button type="button" data-image-clear
                        class="ml-2 text-xs text-accent-red hover:underline">Remove</button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-category">
                    Category
                </label>
                <select id="bbj-feed-category" name="update_type"
                        class="px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm min-w-[180px]">
                    <option value="">(none)</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html($t->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <span class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1">Mode</span>
                <label class="inline-flex items-center gap-1.5 mr-3 text-sm">
                    <input type="radio" name="mode" value="feed" checked> Feed
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm">
                    <input type="radio" name="mode" value="show"> Show
                </label>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 pt-1">
            <label class="inline-flex items-center gap-1.5 text-sm <?php echo $bluesky_configured ? '' : 'opacity-50'; ?>">
                <input type="checkbox" name="post_to_bluesky" value="1"
                       <?php disabled(!$bluesky_configured); ?>>
                Bluesky<?php if (!$bluesky_configured): ?> <span class="text-xs text-stone-400">(not configured)</span><?php endif; ?>
            </label>
            <label class="inline-flex items-center gap-1.5 text-sm <?php echo $facebook_configured ? '' : 'opacity-50'; ?>">
                <input type="checkbox" name="post_to_facebook" value="1"
                       <?php disabled(!$facebook_configured); ?>>
                Facebook<?php if (!$facebook_configured): ?> <span class="text-xs text-stone-400">(not configured)</span><?php endif; ?>
            </label>
            <button type="submit"
                    class="ml-auto px-5 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors">
                Post Update
            </button>
        </div>
    </form>

    <!-- Recent Updates list -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Recent Updates (<?php echo count($recent); ?>)
    </h2>
    <ul id="bbj-feed-list" class="divide-y divide-stone-200 dark:divide-slate-800 border border-stone-200 dark:border-slate-800">
        <?php foreach ($recent as $post):
            $row = bbj_v2_feed_pane_row_data($post);
        ?>
            <li class="p-3" data-id="<?php echo (int) $post->ID; ?>"
                data-title="<?php echo esc_attr($row['title']); ?>"
                data-content="<?php echo esc_attr($row['content']); ?>"
                data-term-id="<?php echo (int) $row['term_id']; ?>"
                data-term-name="<?php echo esc_attr($row['term_name']); ?>"
                data-mode="display">

                <!-- Display subtree -->
                <div class="flex items-center gap-3" data-row-display>
                    <?php if ($row['thumb']): ?>
                        <img src="<?php echo esc_url($row['thumb']); ?>" alt=""
                             class="h-8 w-8 object-cover border border-stone-200 dark:border-slate-700 shrink-0">
                    <?php else: ?>
                        <div class="h-8 w-8 shrink-0"></div>
                    <?php endif; ?>

                    <span class="flex-1 truncate text-sm text-stone-800 dark:text-slate-200 font-medium"
                          title="<?php echo esc_attr($row['title']); ?>">
                        <?php echo esc_html($row['title']); ?>
                    </span>

                    <?php if ($row['term_name'] !== ''): ?>
                        <span class="px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400">
                            <?php echo esc_html($row['term_name']); ?>
                        </span>
                    <?php endif; ?>

                    <span class="text-xs text-stone-500 dark:text-slate-500 shrink-0" data-nosnippet>
                        <?php echo esc_html($row['time_ago']); ?>
                    </span>

                    <span class="text-xs text-stone-600 dark:text-slate-400 shrink-0" title="Votes">
                        ▲<?php echo (int) $row['votes']; ?>
                    </span>

                    <span class="text-xs shrink-0" data-social>
                        <?php if (is_array($row['social'])):
                            $bs = $row['social']['bluesky'] ?? null;
                            $fb = $row['social']['facebook'] ?? null;
                            if (is_array($bs) && !empty($bs['posted'])) echo '<span class="text-green-600" title="Posted to Bluesky">✓BS</span> ';
                            elseif (is_array($bs) && !empty($bs['error'])) echo '<span class="text-accent-red" title="' . esc_attr($bs['error']) . '">✗BS</span> ';
                            if (is_array($fb) && !empty($fb['posted'])) echo '<span class="text-green-600" title="Posted to Facebook">✓FB</span>';
                            elseif (is_array($fb) && !empty($fb['error'])) echo '<span class="text-accent-red" title="' . esc_attr($fb['error']) . '">✗FB</span>';
                        endif; ?>
                    </span>

                    <span class="flex items-center gap-2 shrink-0" data-row-actions>
                        <button type="button" data-action="edit"
                                class="text-xs text-primary-500 hover:underline">Edit</button>
                        <button type="button" data-action="delete"
                                class="text-xs text-accent-red hover:underline">Delete</button>
                    </span>
                </div>

                <!-- Edit subtree (hidden until Edit clicked) -->
                <div class="hidden space-y-2" data-row-edit>
                    <div class="flex gap-2">
                        <input type="text" data-edit-title value="<?php echo esc_attr($row['title']); ?>"
                               class="flex-1 px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                        <select data-edit-category
                                class="px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                            <option value="">(none)</option>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?php echo (int) $t->term_id; ?>"
                                    <?php selected((int) $t->term_id === (int) $row['term_id']); ?>>
                                    <?php echo esc_html($t->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" data-action="save"
                                class="px-3 py-1 bg-primary-500 hover:bg-primary-600 text-white text-xs font-semibold">Save</button>
                        <button type="button" data-action="cancel"
                                class="px-2 py-1 text-xs text-stone-500 hover:underline">Cancel</button>
                    </div>
                    <textarea data-edit-details rows="3"
                              class="w-full px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"><?php echo esc_textarea($row['content']); ?></textarea>
                </div>

                <!-- Delete confirm (hidden until Delete clicked) -->
                <div class="hidden mt-2 text-sm" data-row-confirm>
                    <span class="text-stone-700 dark:text-slate-300 mr-2">Delete this update?</span>
                    <button type="button" data-action="confirm-delete"
                            class="px-2 py-0.5 bg-accent-red hover:opacity-90 text-white text-xs font-semibold">Confirm</button>
                    <button type="button" data-action="cancel-delete"
                            class="px-2 py-0.5 text-xs text-stone-500 hover:underline">Cancel</button>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Toast container (right-aligned, fixed) -->
    <div id="bbj-feed-toasts" class="fixed top-4 right-4 flex flex-col gap-2 z-50 pointer-events-none"></div>

</section>

<script>
    window.BBJ_FEED = {
        restRoot: <?php echo wp_json_encode(esc_url_raw(rest_url('bbjd/v1/feed-updates'))); ?>,
        nonce:    <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>,
        social: {
            bluesky:  <?php echo $bluesky_configured ? 'true' : 'false'; ?>,
            facebook: <?php echo $facebook_configured ? 'true' : 'false'; ?>
        }
    };
</script>
```

- [ ] **Step 2: Syntax check**

```bash
php -l wp-content/themes/bbj-v2-theme/template-parts/admin/pane-feed-updates.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Smoke test**

Visit `http://bbj.localhost/admin/?tab=feed-updates` in a browser.

Expected: form renders at top; list of existing feed updates (or an empty `<ul>` if the DB has none yet) renders below. The form does not yet submit — `admin-feed-updates.js` doesn't exist. Buttons are inert.

Check browser devtools → Network → confirm the page responded 200. Check View Source and confirm the `<script>` tag at the bottom sets `window.BBJ_FEED = { restRoot: "...", nonce: "...", social: { ... } }`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-feed-updates.php
git commit -m "feat(admin): feed-updates pane template (form + hydrated list)"
```

---

### Task 6: JS — bootstrap + toast helper + create flow

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js`

**Context:** Single JS file handles all three flows. This task covers the skeleton + create. Edit + Delete come in Tasks 7 + 8 so each is individually testable.

- [ ] **Step 1: Create the JS file with bootstrap + toasts + create**

Create `wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js` with this exact content:

```javascript
/**
 * Admin Feed Updates pane — client-side logic.
 *
 * Three flows: create (form submit), edit (inline row), delete (inline confirm).
 * All fetch against /wp-json/bbjd/v1/feed-updates/* with the X-WP-Nonce header.
 * Keeps a pure-DOM approach — no jQuery, no framework.
 */
(function () {
    'use strict';

    var cfg = window.BBJ_FEED;
    if (!cfg || !cfg.restRoot) return;

    var form = document.getElementById('bbj-feed-form');
    var list = document.getElementById('bbj-feed-list');
    var toastContainer = document.getElementById('bbj-feed-toasts');
    if (!form || !list || !toastContainer) return;

    // ---- Toast helper -------------------------------------------------------

    function toast(message, tone) {
        var el = document.createElement('div');
        el.className =
            'px-3 py-2 text-sm text-white shadow pointer-events-auto ' +
            (tone === 'error'
                ? 'bg-accent-red'
                : tone === 'warn'
                ? 'bg-secondary-500 text-primary-500'
                : 'bg-primary-500');
        el.textContent = message;
        toastContainer.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity 0.3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 3000);
    }

    // ---- REST wrapper -------------------------------------------------------

    function restFetch(path, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.headers['X-WP-Nonce'] = cfg.nonce;
        return fetch(cfg.restRoot + path, options);
    }

    // ---- Image preview ------------------------------------------------------

    var imageInput = form.querySelector('input[name="image"]');
    var imagePreview = form.querySelector('[data-image-preview]');
    var imagePreviewImg = imagePreview ? imagePreview.querySelector('img') : null;
    var imageClear = form.querySelector('[data-image-clear]');

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            if (!imageInput.files || !imageInput.files[0]) {
                imagePreview.classList.add('hidden');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                imagePreviewImg.src = e.target.result;
                imagePreview.classList.remove('hidden');
            };
            reader.readAsDataURL(imageInput.files[0]);
        });
    }
    if (imageClear) {
        imageClear.addEventListener('click', function () {
            imageInput.value = '';
            imagePreview.classList.add('hidden');
        });
    }

    // ---- Create flow --------------------------------------------------------

    var headlineInput = form.querySelector('input[name="title"]');
    var headlineError = form.querySelector('[data-headline-error]');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var headline = headlineInput.value.trim();
        if (!headline) {
            headlineError.classList.remove('hidden');
            headlineInput.focus();
            return;
        }
        headlineError.classList.add('hidden');

        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Posting…';

        var fd = new FormData(form);
        // FormData from the form already carries title/details/image/update_type/mode.
        // Social checkboxes only serialize when checked, matching the endpoint's
        // post_to_* bool params.

        restFetch('/create', { method: 'POST', body: fd })
            .then(function (res) {
                return res.json().then(function (json) { return { ok: res.ok, json: json }; });
            })
            .then(function (result) {
                if (!result.ok || !result.json.success) {
                    throw new Error(result.json.message || 'Post failed');
                }
                prependRow(result.json.update, result.json.social_results || null);
                form.reset();
                if (imagePreview) imagePreview.classList.add('hidden');
                headlineInput.focus();

                var msg = 'Posted ✓';
                var social = result.json.social_results || {};
                if (social.bluesky && social.bluesky.error) msg += ' — Bluesky failed';
                if (social.facebook && social.facebook.error) msg += ' — Facebook failed';
                toast(msg);
            })
            .catch(function (err) {
                toast(err.message || 'Request failed — check connection', 'error');
            })
            .finally(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Update';
            });
    });

    // ---- Row rendering ------------------------------------------------------

    function prependRow(update, socialResults) {
        var li = document.createElement('li');
        li.className = 'p-3';
        li.setAttribute('data-id', String(update.id));
        li.setAttribute('data-title', update.title || '');
        li.setAttribute('data-content', ''); // server didn't return raw content; fetch-on-edit below handles this
        li.setAttribute('data-term-id', '0');
        li.setAttribute('data-term-name', '');
        li.setAttribute('data-mode', 'display');

        // Display subtree — mirrors the PHP-rendered markup in the pane template.
        var social = '';
        if (socialResults) {
            if (socialResults.bluesky && socialResults.bluesky.posted) social += '<span class="text-green-600" title="Posted to Bluesky">✓BS</span> ';
            else if (socialResults.bluesky && socialResults.bluesky.error) social += '<span class="text-accent-red" title="' + escAttr(socialResults.bluesky.error) + '">✗BS</span> ';
            if (socialResults.facebook && socialResults.facebook.posted) social += '<span class="text-green-600" title="Posted to Facebook">✓FB</span>';
            else if (socialResults.facebook && socialResults.facebook.error) social += '<span class="text-accent-red" title="' + escAttr(socialResults.facebook.error) + '">✗FB</span>';
        }

        var thumb = update.thumbnail
            ? '<img src="' + escAttr(update.thumbnail) + '" alt="" class="h-8 w-8 object-cover border border-stone-200 dark:border-slate-700 shrink-0">'
            : '<div class="h-8 w-8 shrink-0"></div>';

        li.innerHTML =
            '<div class="flex items-center gap-3" data-row-display>' +
                thumb +
                '<span class="flex-1 truncate text-sm text-stone-800 dark:text-slate-200 font-medium" title="' + escAttr(update.title) + '">' + escHtml(update.title) + '</span>' +
                '<span class="text-xs text-stone-500 dark:text-slate-500 shrink-0" data-nosnippet>' + escHtml(update.time_ago || 'just now') + '</span>' +
                '<span class="text-xs text-stone-600 dark:text-slate-400 shrink-0" title="Votes">▲0</span>' +
                '<span class="text-xs shrink-0" data-social>' + social + '</span>' +
                '<span class="flex items-center gap-2 shrink-0" data-row-actions>' +
                    '<button type="button" data-action="edit" class="text-xs text-primary-500 hover:underline">Edit</button>' +
                    '<button type="button" data-action="delete" class="text-xs text-accent-red hover:underline">Delete</button>' +
                '</span>' +
            '</div>' +
            '<div class="hidden space-y-2" data-row-edit></div>' +
            '<div class="hidden mt-2 text-sm" data-row-confirm></div>';

        list.insertBefore(li, list.firstChild);
    }

    // ---- HTML-escape helpers (minimal) --------------------------------------

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    function escAttr(s) {
        return escHtml(s).replace(/"/g, '&quot;');
    }

    // ---- Edit + delete flows (wired in subsequent tasks) --------------------
    // window.BBJ_FEED_DEBUG gives a console handle for manual probing.
    window.BBJ_FEED_DEBUG = { toast: toast, prependRow: prependRow };

})();
```

- [ ] **Step 2: Smoke test — create flow**

Visit `http://bbj.localhost/admin/?tab=feed-updates`. Open devtools → Console.

Fill the form:
- Headline: `Smoke test one-liner`
- Leave details empty
- Leave image empty
- Category: (pick any)
- Mode: Feed
- Social checkboxes: unchecked (avoid real social posts)

Click **Post Update**. Expected:
- Network tab shows `POST /wp-json/bbjd/v1/feed-updates/create` → 201
- Toast `Posted ✓` appears top-right
- New row appears at the top of the list with your headline
- Form clears; headline input is focused

Verify the post in wp-admin: `http://bbj.localhost/wp-admin/edit.php?post_type=live-feed-updates` → your headline is there, published.

Test the validation: submit with an empty headline. Expected: inline red `Headline required.` appears, no fetch fires.

Test error path: in devtools Console run `window.BBJ_FEED.nonce = 'badnonce'`, then try to post. Expected: toast `Request failed...` or the server's error message; no row added.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js
git commit -m "feat(admin): feed-updates JS — bootstrap + create flow"
```

---

### Task 7: JS — inline edit flow

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js`

**Context:** Click Edit → row swaps to edit subtree (inputs pre-filled from `data-*` attrs). Save → PUT → toggle back with updated values. Cancel → just toggle back.

- [ ] **Step 1: Add edit flow**

Open `wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js`.

Find the comment block:

```javascript
    // ---- Edit + delete flows (wired in subsequent tasks) --------------------
    // window.BBJ_FEED_DEBUG gives a console handle for manual probing.
    window.BBJ_FEED_DEBUG = { toast: toast, prependRow: prependRow };
```

REPLACE it with:

```javascript
    // ---- Edit flow ----------------------------------------------------------

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-action]');
        if (!btn) return;
        var li = btn.closest('li[data-id]');
        if (!li) return;

        var action = btn.getAttribute('data-action');
        if (action === 'edit')           openEdit(li);
        else if (action === 'save')      saveEdit(li, btn);
        else if (action === 'cancel')    closeEdit(li);
        else if (action === 'delete')    openConfirmDelete(li);
        else if (action === 'confirm-delete') confirmDelete(li, btn);
        else if (action === 'cancel-delete')  closeConfirmDelete(li);
    });

    function openEdit(li) {
        // If the row didn't come with hydrated edit markup (e.g. a row prepended
        // by prependRow after create), build it from data-* attrs on the fly.
        var editEl = li.querySelector('[data-row-edit]');
        if (editEl && !editEl.querySelector('[data-edit-title]')) {
            buildEditSubtree(li, editEl);
        }
        li.querySelector('[data-row-display]').classList.add('hidden');
        editEl.classList.remove('hidden');
        li.setAttribute('data-mode', 'edit');
        var titleInput = editEl.querySelector('[data-edit-title]');
        if (titleInput) titleInput.focus();
    }

    function closeEdit(li) {
        li.querySelector('[data-row-edit]').classList.add('hidden');
        li.querySelector('[data-row-display]').classList.remove('hidden');
        li.setAttribute('data-mode', 'display');
    }

    function saveEdit(li, btn) {
        var editEl = li.querySelector('[data-row-edit]');
        var title   = editEl.querySelector('[data-edit-title]').value.trim();
        var details = editEl.querySelector('[data-edit-details]').value;
        var termId  = editEl.querySelector('[data-edit-category]').value;
        var id      = li.getAttribute('data-id');

        if (!title) {
            toast('Headline required', 'warn');
            return;
        }

        btn.disabled = true;
        var originalLabel = btn.textContent;
        btn.textContent = 'Saving…';

        restFetch('/' + encodeURIComponent(id), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: title,
                details: details,
                update_type: termId
            })
        })
            .then(function (res) {
                if (res.status === 404) throw new Error('Update disappeared — refresh');
                return res.json().then(function (json) { return { ok: res.ok, json: json }; });
            })
            .then(function (result) {
                if (!result.ok || !result.json.success) {
                    throw new Error(result.json.message || 'Save failed');
                }
                applyRowUpdate(li, result.json.update, termId, editEl);
                closeEdit(li);
                toast('Updated ✓');
            })
            .catch(function (err) {
                toast(err.message, 'error');
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = originalLabel;
            });
    }

    function buildEditSubtree(li, editEl) {
        // For rows prepended client-side (after a create), the edit subtree
        // is empty. Populate it from data-* attrs so the edit form works.
        var title   = li.getAttribute('data-title') || '';
        var content = li.getAttribute('data-content') || '';
        var termId  = li.getAttribute('data-term-id') || '0';

        // Build category options — clone from the main form's select so the
        // taxonomy list stays in sync with the page.
        var mainSelect = document.getElementById('bbj-feed-category');
        var optsHtml = '<option value="">(none)</option>';
        if (mainSelect) {
            Array.prototype.forEach.call(mainSelect.querySelectorAll('option'), function (opt) {
                if (opt.value === '') return;
                var selected = (opt.value === termId) ? ' selected' : '';
                optsHtml += '<option value="' + escAttr(opt.value) + '"' + selected + '>' + escHtml(opt.textContent) + '</option>';
            });
        }

        editEl.innerHTML =
            '<div class="flex gap-2">' +
                '<input type="text" data-edit-title value="' + escAttr(title) + '" class="flex-1 px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">' +
                '<select data-edit-category class="px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">' + optsHtml + '</select>' +
                '<button type="button" data-action="save" class="px-3 py-1 bg-primary-500 hover:bg-primary-600 text-white text-xs font-semibold">Save</button>' +
                '<button type="button" data-action="cancel" class="px-2 py-1 text-xs text-stone-500 hover:underline">Cancel</button>' +
            '</div>' +
            '<textarea data-edit-details rows="3" class="w-full px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">' + escHtml(content) + '</textarea>';
    }

    function applyRowUpdate(li, update, submittedTermId, editEl) {
        // Update data-* attrs so subsequent edits start from fresh values.
        li.setAttribute('data-title', update.title || '');
        li.setAttribute('data-content', editEl.querySelector('[data-edit-details]').value);
        li.setAttribute('data-term-id', submittedTermId || '0');

        // Refresh display subtree text. Keep markup structure stable;
        // only touch the nodes that changed.
        var display = li.querySelector('[data-row-display]');
        if (display) {
            var titleSpan = display.querySelector('.flex-1.truncate');
            if (titleSpan) {
                titleSpan.textContent = update.title;
                titleSpan.setAttribute('title', update.title);
            }
            // Category pill: find the one fixed-width font-mono span, or
            // insert one if the update didn't previously have a category.
            var pill = display.querySelector('.font-mono');
            var termName = '';
            if (submittedTermId) {
                var mainOpt = document.querySelector('#bbj-feed-category option[value="' + CSS.escape(submittedTermId) + '"]');
                if (mainOpt) termName = mainOpt.textContent;
            }
            if (termName) {
                if (pill) {
                    pill.textContent = termName;
                } else {
                    var newPill = document.createElement('span');
                    newPill.className = 'px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400';
                    newPill.textContent = termName;
                    // Insert after title span (second child after thumb)
                    titleSpan.parentNode.insertBefore(newPill, titleSpan.nextSibling);
                }
                li.setAttribute('data-term-name', termName);
            } else if (pill) {
                pill.remove();
                li.setAttribute('data-term-name', '');
            }
        }
    }

    // ---- Delete flow (next task) --------------------------------------------

    function openConfirmDelete(li) {
        var confirmEl = li.querySelector('[data-row-confirm]');
        if (!confirmEl.innerHTML.trim()) {
            confirmEl.innerHTML =
                '<span class="text-stone-700 dark:text-slate-300 mr-2">Delete this update?</span>' +
                '<button type="button" data-action="confirm-delete" class="px-2 py-0.5 bg-accent-red hover:opacity-90 text-white text-xs font-semibold">Confirm</button>' +
                '<button type="button" data-action="cancel-delete" class="px-2 py-0.5 text-xs text-stone-500 hover:underline">Cancel</button>';
        }
        confirmEl.classList.remove('hidden');
    }
    function closeConfirmDelete(li) {
        li.querySelector('[data-row-confirm]').classList.add('hidden');
    }
    function confirmDelete(/*li, btn*/) { /* implemented in Task 8 */ }

    // Debug handle
    window.BBJ_FEED_DEBUG = { toast: toast, prependRow: prependRow };
```

- [ ] **Step 2: Smoke test — edit flow**

Visit `/admin/?tab=feed-updates`. On any existing row:

1. Click **Edit**. Expected: row replaces display subtree with editable fields; title input focused.
2. Change the headline to `Edited: (original text)`.
3. Click **Save**. Expected:
   - Network tab shows `PUT /wp-json/bbjd/v1/feed-updates/{id}` → 200.
   - Toast `Updated ✓`.
   - Row collapses back to display mode with the new headline.
   - **Verify no social re-post:** open wp-admin → edit the post → `_social_posting_results` meta should still show the original posted_at timestamps (or the same errors), NOT a new posting attempt.
4. Check the public single page for the post — new title appears there too.
5. Cancel test: click Edit, change a field, click **Cancel**. Expected: row closes with no changes persisted.

Test the Clicked-Edit-on-just-created row path: create a new update via the form, then immediately click Edit on the new row at top. Expected: edit subtree builds on the fly (from data-* attrs), save works.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js
git commit -m "feat(admin): feed-updates JS — inline edit flow"
```

---

### Task 8: JS — delete flow

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js`

- [ ] **Step 1: Implement confirmDelete**

Open `wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js`.

Find this stub:

```javascript
    function confirmDelete(/*li, btn*/) { /* implemented in Task 8 */ }
```

Replace it with:

```javascript
    function confirmDelete(li, btn) {
        var id = li.getAttribute('data-id');
        btn.disabled = true;
        var originalLabel = btn.textContent;
        btn.textContent = 'Deleting…';

        restFetch('/' + encodeURIComponent(id), { method: 'DELETE' })
            .then(function (res) {
                if (res.status === 404) {
                    // Treat as already-deleted — remove the row anyway
                    toast('Already deleted', 'warn');
                    li.remove();
                    return;
                }
                if (!res.ok) {
                    return res.json().then(function (json) {
                        throw new Error(json.message || 'Delete failed');
                    });
                }
                li.remove();
                toast('Deleted');
            })
            .catch(function (err) {
                toast(err.message, 'error');
                btn.disabled = false;
                btn.textContent = originalLabel;
            });
    }
```

- [ ] **Step 2: Smoke test — delete flow**

Visit `/admin/?tab=feed-updates`. Create a throwaway test update via the form first (so you have something deletable).

1. Click **Delete** on the test row. Expected: row reveals an inline `Delete this update? [Confirm] [Cancel]`.
2. Click **Cancel**. Expected: confirm panel hides; row intact.
3. Click **Delete** again, then **Confirm**. Expected:
   - Network tab shows `DELETE /wp-json/bbjd/v1/feed-updates/{id}` → 204.
   - Toast `Deleted`.
   - Row disappears from the DOM.
4. Check wp-admin → `Posts → Feed Updates` → Trash is empty; the post is force-deleted, not trashed. Confirm with:
   ```bash
   php -r "define('WP_USE_THEMES', false); require 'wp-load.php'; \$p = get_post(DELETED_ID_HERE); var_dump(\$p);" 2>&1 | tail -5
   ```
   Expected: `NULL`.

Test the 404 path: click Delete on a row, then in devtools manually change its `data-id` to a bogus number like `99999999`, click Confirm. Expected: toast `Already deleted`, row still removed locally.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/src/js/admin-feed-updates.js
git commit -m "feat(admin): feed-updates JS — delete flow"
```

---

### Task 9: Final smoke-test pass + roadmap update

**Files:**
- Modify: `.claude/project/roadmap.md` (if exists)

**Context:** Run through the spec's testing checklist end-to-end as one continuous session. Surface any regressions before handoff.

- [ ] **Step 1: End-to-end smoke test**

Fresh incognito browser at `http://bbj.localhost/admin/?tab=feed-updates`. Log in as admin. Run through this checklist in one session; every row must pass:

| # | Scenario | Pass criteria |
|---|----------|---------------|
| 1 | Post one-liner, Gossip category, no socials | New row appears at top; post appears in `/feed-updates/` archive; single page has human-readable slug (e.g. `jag-wins-hoh`), not `bb27-feed-update-jan-30-345-pm-pt` |
| 2 | Post headline + details + image + Bluesky + Facebook (if configured) | Image attached, social posts fire, new row shows `✓BS` `✓FB` or appropriate `✗` with tooltip on hover |
| 3 | Edit headline on existing row | Row updates; `_social_posting_results` meta unchanged (no re-post); single-page title reflects edit |
| 4 | Edit details + category on existing row | Both persist; category pill updates in the row |
| 5 | Delete | Row gone from DOM; post force-deleted (not in Trash) |
| 6 | Logout, visit `/admin?tab=feed-updates` | Redirects to login with `?redirect_to=` return URL |
| 7 | Log in as non-admin user | 403 (create a subscriber test user if needed) |
| 8 | Flip administrator off `feed_updates` in `bbj_admin_permissions` option (e.g. `update_option('bbj_admin_permissions', ['feed_updates' => ['roles' => []]])`), reload the pane | 403. Clear the override to restore access: `delete_option('bbj_admin_permissions')`. |
| 9 | Backward-compat: `curl` to `/create` with `content` + `mode` only (simulating Next.js) | Endpoint still responds 201 with auto-generated title. (If you skipped this in Task 2 Step 4, do it now.) |

If any scenario fails, diagnose + fix + re-run the full checklist before moving on.

- [ ] **Step 2: Update the project roadmap**

Check whether `.claude/project/roadmap.md` exists:

```bash
test -f .claude/project/roadmap.md && echo EXISTS || echo MISSING
```

If `EXISTS`: open it, find the Sprint B section (or the next unshipped sprint section), and mark the Feed Updates admin pane as shipped. Match the tone and format of prior shipped entries in the same file. Commit the update.

If `MISSING`: skip this step.

- [ ] **Step 3: Verify no stray files / clean status**

```bash
git status
```

Expected: clean (or only the roadmap change if you updated it in Step 2). No untracked debug files, console-logs, or scratch commits.

- [ ] **Step 4: Final commit (if roadmap was updated)**

```bash
git add .claude/project/roadmap.md
git commit -m "docs(roadmap): Feed Updates admin pane shipped"
```

---

## Deploy checklist (after all tasks green, before pushing to staging)

- [ ] All 8 prior tasks committed (Task 1 reverted per plan amendment)
- [ ] `git log --oneline -15` shows the expected chain of commits (API extend → PUT/DELETE + PermissionChecker → shell wire-in → pane template → JS bootstrap → JS edit → JS delete → roadmap)
- [ ] `php -l` clean on every PHP file touched
- [ ] No `console.log` / `var_dump` / `error_log` debug residue
- [ ] End-to-end smoke test from Task 9 passed
- [ ] Ready to push staging via your usual flow (`/push-staging` or equivalent)

Staging verification once deployed:

```
wp shell
> \BigBrotherJunkies\Data\Permissions\PermissionChecker::userCan('feed_updates');
=> true (when run as an administrator)
```

`/admin?tab=feed-updates` loads, 50 rows visible, posting works on first real test.

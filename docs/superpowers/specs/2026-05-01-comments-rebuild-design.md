# Comments Rebuild — Lazy-Loaded React Island Design

**Date:** 2026-05-01
**Status:** Spec ready for implementation plan
**Owner:** Steve (BBJ)

## Problem

The current bbj-v2-theme `comments.php` is a minimal WP-default fallback rendering `wp_list_comments()` with light Tailwind styling. Meanwhile, the `bigbrotherjunkies-data` plugin already ships a complete comment backend (REST API at `bbjd/v1`, custom tables for votes / reactions / pins / reports / blacklist / media / mentions, rank tie-ins, notification scaffolding) and the Next.js `bbj-app` repo already has a fully built React comment UI consuming that API. Today, none of that frontend reaches the WP shell — readers on bigbrotherjunkies.com get a flat WP comment thread with no voting, ranks, reactions, pinning, mentions, or media.

Per `project_php_pivot`, bbj-app is being phased out in favor of the WP theme. We need to bring the React comment system *into* the theme, not maintain it externally.

## Goals

1. Replace the WP-default comment thread with the bbj-app React experience on every WP surface that renders `comments_template()` (single posts, feed updates, player profiles, season profiles, pages).
2. Ship as a **lazy-loaded React island** — bundle hydrates only when the comment section enters the viewport, with a server-rendered placeholder + count for instant first paint.
3. Keep `wp_comments` as the canonical store so Akismet, exports, and core moderation continue to work.
4. Reuse the existing `bbjd/v1` REST API; add a **cookie + custom-nonce auth path** alongside the existing JWT path so the WP shell authenticates without minting tokens.
5. **Fork** the React components from bbj-app into `bbj-v2-theme/src/comments/` so the theme owns them going forward and bbj-app can be retired.
6. Code-split the bundle so the composer + emoji/Giphy/mention pickers (heavy) only load on first write-intent.
7. Preserve every per-post off-switch the WP shell already provides (`comments_open()`, `supports => 'comments'`, "Allow comments" checkbox).

## Non-goals (explicit)

- **Real-time push** — websockets / polling. V2 if engagement metrics warrant. Current behavior: data is fresh on page load + after each user action; no background updates.
- **Notification bell wiring** — Sprint E candidate. The `bbj_notifications` table already gets written to on @mention and reply; surfacing it in the header is a separate rebuild.
- **Edit history / soft delete UI** — backend supports the underlying ops, but no UI for diff or restore this round.
- **Admin moderation UI** — separate rebuild as the second half of this sprint per owner ("then work on the admin side of handling comments"). This spec covers the front-of-house only.
- **TypeScript migration** — port stays JSX; type-safety is runtime-validated at API boundaries.
- **Test suite scaffolding for the React layer** — components are forked from a system already shipped on bbj-app; we don't add Jest/Vitest this round.
- **Bbj-app maintenance after fork** — bbj-app remains as-is for any external consumers, but is not part of this sprint's CI/release path. The original component files in `bbj-app/src/components/comments/` are NOT deleted as part of this work; they freeze in place. If/when bbj-app is fully retired, that's a separate cleanup PR.

## Architecture overview

```
[ WP single template ]
         │
         ▼
comments_template()  ←  filter intercepts
         │
         ▼
[ SSR placeholder ]                              ← bbj-v2-theme/template-parts/comments/island-placeholder.php
  • <div id="bbj-comments-root" data-* …></div>
  • wp_localize_script('bbj-comments-data', { user, nonce, endpoints, config })
  • bootstrap.js enqueued (defer)
         │  (when root within viewport ±500px)
         ▼
[ import('./main.js') ]                          ← code-split chunk #1
  • mounts <CommentSection postId={…}>
  • bbjAuthFetch(GET /bbjd/v1/comments/{postId})
  • renders list, voting, reactions, badges, modals
         │
         ▼
[ import('./composer.js') on first Reply/Compose click ]   ← code-split chunk #2
  • CommentForm + MediaUploader + GiphyPicker + EmojiPicker + MentionAutocomplete
```

Three layers, three responsibilities:

- **PHP** owns: detecting where comments should appear, rendering the placeholder + skeleton, localizing user/nonce/config, enqueuing the bootstrap.
- **REST** owns: data + business rules. Already exists in `bigbrotherjunkies-data`. Gets one new helper for cookie+nonce auth.
- **React** owns: list rendering, interaction, optimistic UI, lazy chunk loading, error UX.

## PHP layer

### `bbj-v2-theme/inc/comments-island.php` (new)

- Hooks `comments_template` filter at priority 20. Returns the island placeholder partial path. Skips (returns the default template) when `! comments_open()` — that way, "Allow comments" off on a post means the island doesn't render at all, falling through to nothing.
- Enqueues `bbj-comments-bootstrap` (built from `src/comments/bootstrap.js`) with `defer`.
- Calls `wp_localize_script('bbj-comments-bootstrap', 'bbjComments', [...])` with:
  - `user`: `null` if logged out, else `{ id, display_name, avatar_url, rank, can_moderate }`
  - `nonce`: `wp_create_nonce('bbj_comments')` — used as the `X-BBJ-Nonce` header value
  - `nonceRefreshUrl`: `rest_url('bbjd/v1/auth/refresh-nonce')`
  - `endpoints.base`: `rest_url('bbjd/v1')`
  - `config`: `{ perPage: 20, maxDepth: 3, sortDefault: 'newest' }`
- Loaded via `inc/setup.php` (or wherever theme bootstraps `inc/` files).

### `bbj-v2-theme/template-parts/comments/island-placeholder.php` (new)

- Renders one `<section id="bbj-comments-root" …>` with `data-post-id`, `data-comment-count`, `data-can-comment`, `data-comments-open`, `data-permalink-comment` (parsed from `?comment=` query if present).
- Skeleton inside: comment count, "loading…" affordance. Visible without JS.
- A `<noscript>` fallback link: `<a href="?bbjcomments=plain">View comments →</a>` — server-side full list, primarily a graceful no-JS path and an Akismet review aid (handled in `inc/comments-island.php` via `template_redirect` short-circuit).

### `?bbjcomments=plain` fallback

When the URL has `?bbjcomments=plain`, `inc/comments-island.php` short-circuits in `template_redirect` and renders a stripped-down server-side comment list using `wp_list_comments()` with a minimal Tailwind walker (read-only, no voting / reactions / composer beyond the WP-default form). Two consumers:

- No-JS readers (rare, but accessible).
- Bundle-load-failure recovery (after 3 retries).
- Manual moderator review aid when scanning a thread without React in the way.

Bundle / island assets are not enqueued on this query path.

### Comment count badge

The post hero / card already shows comment counts in several places via `get_comments_number()`. No changes — those keep using core data unchanged.

## React layer

### File layout

```
bbj-v2-theme/src/comments/
  bootstrap.js          ← always loaded (≈5KB target)
  main.js               ← lazy on intersection (≈60KB gz target)
  composer.js           ← lazy on first write-intent (≈30KB gz target)
  components/
    CommentSection.jsx
    CommentCard.jsx
    VoteButtons.jsx
    ReactionButtons.jsx
    RankBadge.jsx
    StaffPickBadge.jsx
    OnlineIndicator.jsx
    AuthorModal.jsx
    ReportModal.jsx
    CommentForm.jsx           ← composer chunk
    MediaUploader.jsx         ← composer chunk
    GiphyPicker.jsx           ← composer chunk
    EmojiPicker.jsx           ← composer chunk
    MentionAutocomplete.jsx   ← composer chunk
  hooks/
    useBbjUser.js
    useToast.js
  lib/
    bbjAuthFetch.js
    api.js              ← endpoint wrappers (GET/POST/PUT/DELETE)
    rankConfig.js       ← static rank icon / color map
```

### Per-component port changes (applied uniformly during fork)

- Drop `"use client"` directives.
- `next/image` → `<img>` with explicit `width` + `height` attrs to preserve no-CLS.
- `next/link` → `<a href>`.
- `useRouter` / `usePathname` → `window.location` reads.
- `useSession` (NextAuth) → `useBbjUser`.
- All `fetch` calls routed through `bbjAuthFetch`.
- Tailwind: bbj-v2-theme already uses dashed names (`primary-500` etc.); port should be ~drop-in. Any class collisions caught at build get a `bbj-c-` prefix per `feedback_design_class_collisions`.
- `dark:` variants preserved; theme has dark-mode scaffolding via `inc/dark-mode.php`.

### `useBbjUser`

- Reads `window.bbjComments.user` on mount.
- Listens for `bbj:auth:changed` window event (fired by `template-parts/auth/modal.php`).
- On auth change: refreshes via `GET /bbjd/v1/auth/me` (existing) and updates state.
- Returns `{ user, isAuthenticated, refresh, signOut }`.

### `bbjAuthFetch`

- Always sends `X-BBJ-Nonce: <window.bbjComments.nonce>` header. NOT `X-WP-Nonce` per `feedback_rest_nonce_collision`.
- If `window.bbjComments.jwt` is present (set by an external integration), also sends `Authorization: Bearer ...`.
- On 401 with `code: 'rest_cookie_invalid_nonce'`: hits `nonceRefreshUrl`, retries once with the fresh nonce. If still 401: opens auth modal via `bbj:auth:open` event.
- On 5xx: rejects with structured error `{ code, message, status }` for the toast layer.

### Bundle split detail

- **bootstrap.js**: IntersectionObserver setup, dynamic `import('./main.js')`, error UX for failed import (3 retries → fallback link).
- **main.js**: everything in the file layout above EXCEPT the composer-chunk components and EmojiPicker/MentionAutocomplete.
- **composer.js**: composer + pickers. `import('./composer.js')` triggered on the FIRST click of any Reply / Compose / React-with-emoji / @-trigger button per page. The result is cached in module scope so subsequent clicks are instant.

## Backend additions

### Cookie + nonce auth helper (`bigbrotherjunkies-data` plugin)

Single helper `bbjd_cookie_or_jwt_permission()` added once, applied across every existing `bbjd/v1/comments/*` route's `permission_callback`:

- Cookie path: WP user is logged in (`is_user_logged_in()`) AND the request carries a valid `X-BBJ-Nonce` matching `wp_verify_nonce($_SERVER['HTTP_X_BBJ_NONCE'], 'bbj_comments')`.
- JWT path: existing JWT validation logic preserved.
- If both succeed, prefers the cookie identity (deterministic for the WP-shell case).
- Routes that previously required no auth (e.g. read-only `GET /comments/{post_id}`) keep their existing permission callback unchanged.

### New tiny endpoint: `GET /bbjd/v1/auth/refresh-nonce`

- Returns `{ nonce: '<new value>' }` for the current logged-in user.
- Permission: `is_user_logged_in()`.
- Used by `bbjAuthFetch` to recover from a stale-nonce 401 in long-open tabs.

### Object cache for the read endpoint

- `GET /bbjd/v1/comments/{post_id}?page=N&sort=S` cached in `bbj_v2` group, key `bbj_comments_{post_id}_p{N}_s{S}`, TTL 60s.
- Bust on: `wp_insert_comment`, `wp_update_comment`, `wp_set_comment_status`, plus the bbjd vote / pin / react / report write hooks.
- Per-user state (`user_vote`, `user_reaction`, `can_edit`, `can_delete`, `can_pin`) stays OUT of the cache and is layered on after the fetch — keeps cache user-agnostic and cheap.

## Data flow

### Initial pageload (zero JS executed)

1. WP renders single template.
2. `comments_template()` filter returns island placeholder.
3. PHP renders `<div id="bbj-comments-root" data-* …>` + skeleton + `<noscript>` fallback link.
4. PHP enqueues `bbj-comments-bootstrap` (defer) + localizes `window.bbjComments`.

### Hydration (root enters viewport ±500px)

1. `bootstrap.js`'s IntersectionObserver fires.
2. Dynamic `import('./main.js')`.
3. `<CommentSection postId={...}>` mounts.
4. `bbjAuthFetch(GET /bbjd/v1/comments/{postId}?page=1&per_page=20&sort=newest)`.
5. Server returns list + count + per-comment user state. React renders.

### Read interactions

- Sort change → re-fetch with new `sort` param.
- "Load more" → `?page=N+1`, append.
- Permalink (`?comment=99#comment-99`) → after data loads, `useEffect` scrolls + flashes the row once.

### Write interactions (vote / react / report)

1. Optimistic UI updates immediately.
2. `bbjAuthFetch(POST/DELETE …)`.
3. On 200 → confirm with server payload (idempotent merge).
4. On 401 → nonce refresh (bbjAuthFetch handles), then retry once. Still 401 → auth modal.
5. On 403 (blacklist) → toast, revert optimistic.
6. On 5xx → toast, revert.

### Compose interaction (composer chunk lazy-loads)

1. User clicks Reply / Compose.
2. If composer chunk not yet loaded → `import('./composer.js')` (one network spend per page).
3. Composer renders inline.
4. Submit → `bbjAuthFetch(POST /bbjd/v1/comments, { post_id, parent_id, content, media_id? })`.
5. Server returns the new comment object → prepended optimistically to the list.
6. On rejection → keep draft in textarea, show error toast.

### Auth-state mid-session

- Theme auth modal fires `bbj:auth:changed` window event on login/logout.
- `useBbjUser` listens, refreshes user state.
- Composer / vote / react buttons swap from "Log in to comment" CTA → fully interactive.

## Error handling

| Scenario | Behavior |
|---|---|
| Bundle load fails (network drop, ad-blocker) | Skeleton replaced with "Comments couldn't load. [Retry]". Retry triggers another `import()`. After 3 fails → fallback link to `?bbjcomments=plain` server-rendered list. |
| GET comments 5xx | Inline error block in island: "Couldn't load comments. [Retry]" — count badge preserved. |
| GET comments 200 + empty | "Be the first to comment." Composer mounts directly if logged in; anon sees login CTA. |
| POST comment 401 | Auto-refresh nonce + retry once; if still 401, pop auth modal preserving the draft. |
| POST comment 403 (blacklist) | Toast: "Your account isn't permitted to comment." Draft cleared, no retry. |
| POST 422 (validation) | Inline error under offending field; draft preserved. |
| Vote / react timeout | Optimistic UI rolled back; toast "Vote didn't register, try again". |
| Comments closed mid-session | Composer hides on next interaction; existing comments stay visible. |
| Nonce expired (>12h tab open) | One-shot refresh via `auth/refresh-nonce`; if THAT fails, prompt re-login. |
| Mixed auth | bbjAuthFetch always sends nonce; if a JWT is present, also sends Authorization. Backend prefers nonce when both arrive. |

Logging: errors that are not user-fault (5xx, bundle fail) get one `console.error` line tagged `[bbj-comments] <op> <status>`. No Sentry / external logger this round.

## Testing

### Backend (PHPUnit, in `bigbrotherjunkies-data`)

One new test file covering `bbjd_cookie_or_jwt_permission()`:

- Returns true when WP user is logged in + valid nonce header present.
- Returns false when nonce is missing, even if logged in.
- Returns false when nonce is invalid / expired.
- Returns true when valid JWT bearer is present (no cookie / no nonce).
- Returns false when JWT is malformed / expired.
- Prefers cookie identity when both are present.

Plus a regression smoke: every existing `bbjd/v1/comments/*` endpoint still returns 200 with a valid JWT (catches accidental break of external consumers).

### Frontend

No new framework. Components are forked from a system already shipped on bbj-app — they're not new logic. Manual punch list (lives in this spec, copied to `project_comments_rebuild_testing.md` memory after ship).

### Manual test punch list

1. Logged-out: load post, scroll, see comments hydrate, can read + sort + paginate, can NOT vote / post (sees login prompt).
2. Logged-in: scroll, hydrate, post a top-level comment, see it appear, see it land in `wp_comments`.
3. Reply to a comment, verify nesting + depth-3 cap.
4. Vote up / down / remove, verify `bbj_comment_votes` rows.
5. React (emoji), verify `bbj_comment_reactions` rows.
6. Report a comment, verify `bbj_comment_reports` row.
7. Mod user pins a comment, verify `bbj_pinned_comments` + StaffPickBadge appears.
8. @mention autocomplete works, target user gets `bbj_notifications` row.
9. Composer chunk loads only on first Reply click (verify in DevTools network tab — `composer.js` should be absent on initial scroll).
10. Permalink `?comment=X#comment-X` scrolls + flashes the target row.
11. Bundle load failure simulation (block `comments/main.js` in DevTools): retry path works → fallback link works.
12. Akismet still flags spam: post a comment with a known spam trigger, verify it lands in moderation.
13. Comments closed: edit post, uncheck "Allow comments", reload — placeholder doesn't render at all (filter falls through).
14. Cross-template smoke: same flow on `single.php`, `single-live-feed-updates.php`, `single-bigbrother-players.php`, `single-bigbrother-seasons.php`, `page.php`.
15. Cache invalidation: post a comment, reload — new comment appears without manual cache clear.

## Performance budget

Verify with WebPageTest + Lighthouse before `/push-live`:

- Initial pageload bundle delta: < 6KB gzipped (bootstrap only)
- Main chunk: < 60KB gzipped (target — verify post-build; if over, audit dependencies)
- Composer chunk: < 30KB gzipped
- LCP: unaffected (placeholder is HTML-only, no JS executed)
- INP: < 200ms on vote / react interactions
- CLS: 0 — placeholder reserves space matching final render
- Object cache hit ratio for read endpoint: > 80% in steady state (BB-active periods)

## Shipping order

1. Backend: cookie + nonce auth helper, `auth/refresh-nonce` endpoint, object cache wrapper on the read endpoint, PHPUnit tests for the helper.
2. PHP: `inc/comments-island.php`, `template-parts/comments/island-placeholder.php`, `?bbjcomments=plain` fallback handler.
3. React scaffold: `bootstrap.js` + IntersectionObserver, `main.js` empty mount, `useBbjUser`, `bbjAuthFetch`, smoke render of comment count.
4. Port `CommentSection` + `CommentCard` (read-only): list + sort + pagination + permalink scroll. Manual smoke on `single.php`.
5. Port `VoteButtons` + `ReactionButtons` + RankBadge + StaffPickBadge + OnlineIndicator + AuthorModal + ReportModal.
6. Composer chunk: `CommentForm`, `MediaUploader`, `GiphyPicker`, `EmojiPicker`, `MentionAutocomplete`. Lazy-load wiring.
7. Cross-template smoke (test punch list 1-7, 14).
8. Polish: error toasts, loading skeletons, optimistic-UI rollback animations, bundle audit vs perf budget.
9. Push to staging Cloudways via `/push-staging`. Run punch list end-to-end.
10. Update memory: write `project_comments_rebuild_state.md` (shipped state) and `project_comments_rebuild_testing.md` (punch list for owner).

## Memory / reference touch points

- `feedback_rest_nonce_collision.md` — custom nonce header, NOT `X-WP-Nonce`. Hard-learned.
- `feedback_rest_permission_callback_notices.md` — every route gets a permission_callback (no nulls). The cookie+nonce helper is one such callback.
- `feedback_design_class_collisions.md` — prefix any colliding class with `bbj-c-`.
- `project_admin_stack_hybrid.md` — confirms React-island pattern is consistent with house style ("React sprinkles for interactive surfaces").
- `project_php_pivot.md` — confirms bbj-app is being phased out, so forking is the right call.
- `feedback_no_worktrees.md` — branches only.
- `feedback_basic_build_then_simmer.md` — ship the core punch-list path first, polish after smoke passes.

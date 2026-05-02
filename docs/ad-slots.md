# Ad Slots — bbj-v2-theme

Slots added as **placeholders** (via `template-parts/components/ad-placeholder.php`)
are tracked here so we can wire them to real ads later via `bbjd_ad()` from the
`bigbrotherjunkies-data` plugin.

## How to swap placeholder → real ad

When a slot is ready to go live:

1. Register the slot in the WP admin (bigbrotherjunkies-data → Ad Slots).
2. In the template that emits the placeholder, swap
   ```php
   get_template_part('template-parts/components/ad-placeholder', null, [
       'slot' => 'leaderboard_top', 'size' => '970x90', 'note' => '…',
   ]);
   ```
   for
   ```php
   get_template_part('template-parts/components/ad-slot', null, [
       'slot' => 'leaderboard_top', 'wrapper' => 'mx-auto max-w-screen-xl px-2 py-4',
   ]);
   ```
3. Leave the slot name identical so analytics continuity is preserved.

## Current placeholder slots

| Slot name         | Size    | Location                       | Behaviour                     | Status      |
|-------------------|---------|--------------------------------|-------------------------------|-------------|
| `leaderboard_top` | 970×90  | `header.php` — below nav strip | Eager-load, above-the-fold    | Placeholder |

## Slot naming convention

- `snake_case`, short and descriptive.
- Prefer position + role over hostname/vendor: `leaderboard_top`, not `dfp_home_970x90_a`.
- Top-of-page: `*_top`. Mid-content: `in_content_<n>`. Sidebar: `sidebar_top`, `sidebar_mid`. End of article: `below_post`.

## Removed / retired slots

*(none yet — add here if we drop a slot and want to document why.)*

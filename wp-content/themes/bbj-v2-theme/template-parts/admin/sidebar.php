<?php
/**
 * Admin shell sidebar. Rendered by page-admin.php.
 * Receives $args['active'] — the current tab slug (defaults to 'overview').
 *
 * Visual direction: branded dark-navy sidebar with yellow active state,
 * matching the user dashboard sidebar.
 */

if (!defined('ABSPATH')) {
    exit;
}

$active = isset($args['active']) ? (string) $args['active'] : 'overview';

/*
 * Grouped into sections for readability (13+ items otherwise become a wall).
 * Per-item `badge` is optional — same shape as dashboard badges:
 *   - ['type' => 'count', 'text' => '14']
 *   - ['type' => 'alert', 'text' => 'LIVE']
 *   - ['type' => 'state', 'text' => 'OK']
 * Admin panes are all stubs in v1 so no badges are wired yet; leaving the
 * scaffolding so real counts slot in when each pane is built.
 */
$sections = [
    [
        'label' => 'Content',
        'items' => [
            ['slug' => 'overview',       'label' => 'Overview',      'icon' => 'home'],
            ['slug' => 'posts',          'label' => 'Posts',         'icon' => 'document-text'],
            ['slug' => 'feed-updates',   'label' => 'Feed Updates',  'icon' => 'rss'],
            ['slug' => 'comments',       'label' => 'Comments',      'icon' => 'chat'],
        ],
    ],
    [
        'label' => 'Game',
        'items' => [
            ['slug' => 'players',        'label' => 'Players',       'icon' => 'users'],
            ['slug' => 'seasons',        'label' => 'Seasons',       'icon' => 'calendar'],
            ['slug' => 'spoiler-bar',    'label' => 'Spoiler Bar',   'icon' => 'shield-check'],
        ],
    ],
    [
        'label' => 'Community',
        'items' => [
            ['slug' => 'announcements',  'label' => 'Announcements', 'icon' => 'megaphone'],
            ['slug' => 'content-engine', 'label' => 'Content',       'icon' => 'pencil-square'],
            ['slug' => 'users',          'label' => 'Users',         'icon' => 'users'],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['slug' => 'stats',          'label' => 'Stats',         'icon' => 'chart-bar'],
            ['slug' => 'settings',       'label' => 'Settings',      'icon' => 'cog'],
        ],
    ],
];

$current_user = wp_get_current_user();

/**
 * Helper: print an inline SVG by icon name. Kept local to avoid polluting
 * global scope. Paths come from heroicon set (stroke, 24x24, stroke 1.5).
 * Declared here (above the markup that calls it) because PHP does NOT
 * hoist conditionally-declared functions — a bare `function foo(){}` hoists,
 * but `if (!function_exists(...)) { function foo(){} }` does not.
 */
if (!function_exists('bbj_v2_admin_icon')) {
    function bbj_v2_admin_icon(string $name): void
    {
        $paths = [
            'home'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
            'document-text'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
            'rss'            => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"/>',
            'chat'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
            'users'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
            'calendar'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
            'megaphone'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>',
            'pencil-square'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>',
            'chart-bar'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            'cog'            => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'shield-check'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
        ];
        $svg = $paths[$name] ?? '';
        echo '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' . $svg . '</svg>';
    }
}

/**
 * Helper: render the right-side badge for an admin sidebar item.
 * Mirrors bbj_v2_dashboard_badge so both shells feel the same.
 */
if (!function_exists('bbj_v2_admin_badge')) {
    function bbj_v2_admin_badge(array $badge, bool $is_active): void
    {
        $type = $badge['type'] ?? 'count';
        $text = $badge['text'] ?? '';
        if ($text === '') {
            return;
        }

        if ($type === 'alert') {
            $classes = 'bg-accent-red text-white';
        } elseif ($type === 'state') {
            $classes = 'bg-secondary-500 text-primary-500';
        } else {
            $classes = $is_active
                ? 'bg-primary-500/20 text-primary-500'
                : 'bg-white/10 text-slate-200';
        }

        printf(
            '<span class="ml-auto inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-[11px] font-semibold rounded-full %s">%s</span>',
            esc_attr($classes),
            esc_html($text)
        );
    }
}
?>

<aside class="w-56 shrink-0 self-start sticky top-4 bg-primary-500 text-slate-100 min-h-[85vh]">
    <nav class="pt-4 pb-3 px-3 space-y-1" aria-label="<?php esc_attr_e('Admin navigation', 'bbj-v2-theme'); ?>">
        <?php foreach ($sections as $section_index => $section): ?>
            <div class="px-2 <?php echo $section_index === 0 ? 'pt-1' : 'pt-4'; ?> pb-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                <?php echo esc_html($section['label']); ?>
            </div>
            <?php foreach ($section['items'] as $item):
                $is_active = ($item['slug'] === $active);
                $url = $item['slug'] === 'overview'
                    ? home_url('/admin/')
                    : add_query_arg('tab', $item['slug'], home_url('/admin/'));
                $classes = $is_active
                    ? 'bg-secondary-500 text-primary-500 font-bold shadow-sm'
                    : 'text-slate-200 hover:bg-white/10 font-medium';
            ?>
                <a href="<?php echo esc_url($url); ?>"
                   class="flex items-center gap-3 px-3 py-2 text-sm rounded-md transition-colors <?php echo esc_attr($classes); ?>"
                   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                    <?php bbj_v2_admin_icon($item['icon']); ?>
                    <span class="truncate"><?php echo esc_html($item['label']); ?></span>
                    <?php if (!empty($item['badge'])): bbj_v2_admin_badge($item['badge'], $is_active); endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="px-3 pt-4 pb-3 mt-4 border-t border-white/10">
        <a href="<?php echo esc_url(home_url('/')); ?>"
           class="flex items-center gap-2 px-3 py-2 text-sm text-slate-400 hover:text-white hover:bg-white/10 rounded-md transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            <span><?php esc_html_e('Back to Site', 'bbj-v2-theme'); ?></span>
        </a>
    </div>
</aside>

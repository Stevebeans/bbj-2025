<?php
/**
 * User dashboard shell sidebar. Rendered by page-dashboard.php.
 * Receives $args['active'] — the current tab slug (defaults to 'overview').
 */

if (!defined('ABSPATH')) {
    exit;
}

$active = isset($args['active']) ? (string) $args['active'] : 'overview';

$sections = [
    [
        'label' => 'My BBJ',
        'items' => [
            ['slug' => 'overview',      'label' => 'Overview',      'icon' => 'home'],
            ['slug' => 'activity',      'label' => 'Activity',      'icon' => 'lightning'],
            ['slug' => 'saved',         'label' => 'Saved',         'icon' => 'bookmark'],
            ['slug' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell'],
        ],
    ],
    [
        'label' => 'Account',
        'items' => [
            ['slug' => 'profile',  'label' => 'Profile',  'icon' => 'user-circle'],
            ['slug' => 'premium',  'label' => 'Premium',  'icon' => 'star'],
            ['slug' => 'settings', 'label' => 'Settings', 'icon' => 'cog'],
        ],
    ],
    [
        'label' => 'Explore',
        'items' => [
            ['slug' => 'feeds-blog',     'label' => 'Feeds Blog',     'icon' => 'rss'],
            ['slug' => 'power-rankings', 'label' => 'Power Rankings', 'icon' => 'chart-bar'],
            ['slug' => 'leaderboard',    'label' => 'Leaderboard',    'icon' => 'trophy'],
        ],
    ],
];

$current_user = wp_get_current_user();

/**
 * Helper: print an inline SVG by icon name for the dashboard sidebar.
 * Kept separate from bbj_v2_admin_icon because the icon sets only partly overlap.
 * Declared above the markup that calls it — conditionally-declared functions
 * are NOT hoisted by PHP, so the definition must precede the first call site.
 */
if (!function_exists('bbj_v2_dashboard_icon')) {
    function bbj_v2_dashboard_icon(string $name): void
    {
        $paths = [
            'home'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
            'lightning'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
            'bookmark'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>',
            'bell'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
            'user-circle'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
            'star'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
            'cog'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'rss'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"/>',
            'chart-bar'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            'trophy'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4a5 5 0 0010 0V3M7 21h10M12 17v4M8 3h8M6 7H4a1 1 0 01-1-1V5a2 2 0 012-2h2m12 4h2a1 1 0 001-1V5a2 2 0 00-2-2h-2"/>',
        ];
        $svg = $paths[$name] ?? '';
        echo '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' . $svg . '</svg>';
    }
}
?>

<aside class="w-52 shrink-0 self-start sticky top-4 bg-white dark:bg-gray-900 border border-stone-200 dark:border-slate-800">
    <div class="px-4 py-3 border-b border-stone-200 dark:border-slate-700">
        <div class="text-xs uppercase tracking-wider text-stone-500">My BBJ</div>
        <div class="text-sm text-stone-800 dark:text-slate-200 mt-0.5 truncate">
            <?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?>
        </div>
    </div>

    <nav class="py-2 px-2" aria-label="<?php esc_attr_e('User dashboard navigation', 'bbj-v2-theme'); ?>">
        <?php foreach ($sections as $section): ?>
            <div class="px-3 pt-3 pb-1 text-xs uppercase tracking-wider text-stone-500">
                <?php echo esc_html($section['label']); ?>
            </div>
            <?php foreach ($section['items'] as $item):
                $is_active = ($item['slug'] === $active);
                $url = $item['slug'] === 'overview'
                    ? esc_url(home_url('/dashboard/'))
                    : esc_url(add_query_arg('tab', $item['slug'], home_url('/dashboard/')));
                $classes = $is_active
                    ? 'bg-primary-500 text-white'
                    : 'text-stone-700 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800';
            ?>
                <a href="<?php echo $url; ?>"
                   class="flex items-center gap-3 px-3 py-2 text-sm font-medium transition-colors <?php echo $classes; ?>"
                   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                    <?php bbj_v2_dashboard_icon($item['icon']); ?>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="px-2 py-2 border-t border-stone-200 dark:border-slate-700">
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"
           class="flex items-center gap-2 px-3 py-2 text-sm text-stone-600 hover:bg-stone-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span><?php esc_html_e('Log out', 'bbj-v2-theme'); ?></span>
        </a>
    </div>
</aside>

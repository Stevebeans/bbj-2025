<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<nav class="hidden md:block bg-primary-500 text-white" aria-label="<?php esc_attr_e('Primary', 'bbj-v2-theme'); ?>">
    <div class="mx-auto max-w-screen-xl px-0 flex items-stretch">
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'bbj-nav flex items-stretch',
            'depth'          => 1,
            'fallback_cb'    => 'bbj_v2_fallback_menu',
            'link_class'     => 'bbj-nav-link',
        ]);
        ?>
        <a href="<?php echo esc_url(home_url('/watch-feeds/')); ?>" class="bbj-nav-watch">
            <?php esc_html_e('Watch Live Feeds', 'bbj-v2-theme'); ?>
        </a>
    </div>
</nav>

<?php
// Fallbacks fire only until the user assigns a menu in Appearance → Menus.
function bbj_v2_fallback_menu(): void
{
    $items = bbj_v2_primary_menu_items();
    echo '<ul class="bbj-nav flex items-stretch">';
    foreach ($items as $item) {
        $active = !empty($item['match']);
        printf(
            '<li class="%s"><a href="%s" class="bbj-nav-link%s">%s</a></li>',
            $active ? 'current-menu-item' : '',
            esc_url($item['url']),
            $active ? ' is-active' : '',
            esc_html($item['label'])
        );
    }
    echo '</ul>';
}

function bbj_v2_fallback_mobile_menu(): void
{
    $items = bbj_v2_primary_menu_items();
    echo '<ul class="bbj-mnav flex flex-col">';
    foreach ($items as $item) {
        $active = !empty($item['match']);
        printf(
            '<li class="%s"><a href="%s" class="bbj-mnav-link%s">%s</a></li>',
            $active ? 'current-menu-item' : '',
            esc_url($item['url']),
            $active ? ' is-active' : '',
            esc_html($item['label'])
        );
    }
    echo '</ul>';
}

function bbj_v2_primary_menu_items(): array
{
    return [
        ['label' => 'Home',           'url' => home_url('/'),               'match' => is_front_page()],
        ['label' => 'Feed Updates',   'url' => home_url('/feed-updates/'),  'match' => is_post_type_archive('live-feed-updates') || is_singular('live-feed-updates') || is_page('feed-updates')],
        ['label' => 'Player Directory', 'url' => home_url('/bigbrother-players/'), 'match' => is_post_type_archive('bigbrother-players') || is_singular('bigbrother-players')],
        ['label' => 'Seasons',        'url' => home_url('/bigbrother-seasons/'), 'match' => is_post_type_archive('bigbrother-seasons') || is_singular('bigbrother-seasons')],
        ['label' => 'Power Rankings', 'url' => home_url('/power-rankings/'),'match' => is_page('power-rankings')],
        ['label' => 'Forums',         'url' => home_url('/forums/'),        'match' => is_page('forums')],
    ];
}

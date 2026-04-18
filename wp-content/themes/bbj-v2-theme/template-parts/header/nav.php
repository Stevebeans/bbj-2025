<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<nav class="bg-primary-500 text-white relative" aria-label="<?php esc_attr_e('Primary', 'bbj-v2-theme'); ?>">
    <div class="mx-auto max-w-screen-xl px-0 flex items-stretch">

        <button type="button"
                class="bbj-mnav-toggle md:hidden"
                data-bbj-mnav-toggle
                aria-expanded="false"
                aria-controls="bbj-mnav-panel"
                aria-label="<?php esc_attr_e('Open menu', 'bbj-v2-theme'); ?>">
            <span class="bbj-mnav-burger" aria-hidden="true">
                <span></span><span></span><span></span>
            </span>
            <span class="font-osw uppercase tracking-wider text-sm"><?php esc_html_e('Menu', 'bbj-v2-theme'); ?></span>
        </button>

        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'bbj-nav hidden md:flex items-stretch',
            'depth'          => 1,
            'fallback_cb'    => 'bbj_v2_fallback_menu',
            'link_class'     => 'bbj-nav-link',
        ]);
        ?>

        <a href="<?php echo esc_url(home_url('/watch-feeds/')); ?>" class="bbj-nav-watch hidden md:inline-flex">
            <?php esc_html_e('Watch Live Feeds', 'bbj-v2-theme'); ?>
        </a>
    </div>

    <div id="bbj-mnav-panel"
         class="bbj-mnav-panel md:hidden"
         data-bbj-mnav-panel
         hidden>
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'bbj-mnav flex flex-col',
            'depth'          => 1,
            'fallback_cb'    => 'bbj_v2_fallback_mobile_menu',
            'link_class'     => 'bbj-mnav-link',
        ]);
        ?>
        <a href="<?php echo esc_url(home_url('/watch-feeds/')); ?>" class="bbj-nav-watch justify-center">
            <?php esc_html_e('Watch Live Feeds', 'bbj-v2-theme'); ?>
        </a>
    </div>
</nav>

<?php
// Fallbacks fire only until the user assigns a menu in Appearance → Menus.
function bbj_v2_fallback_menu(): void
{
    $items = bbj_v2_primary_menu_items();
    echo '<ul class="bbj-nav hidden md:flex items-stretch">';
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
        ['label' => 'Feed Updates',   'url' => home_url('/feed-updates/'),  'match' => is_page('feed-updates') || is_post_type_archive('live-feed-updates') || is_singular('live-feed-updates')],
        ['label' => 'Houseguests',    'url' => home_url('/houseguests/'),   'match' => is_post_type_archive('bigbrother-players') || is_singular('bigbrother-players')],
        ['label' => 'Seasons',        'url' => home_url('/seasons/'),       'match' => is_post_type_archive('bigbrother-seasons') || is_singular('bigbrother-seasons')],
        ['label' => 'Power Rankings', 'url' => home_url('/power-rankings/'),'match' => is_page('power-rankings')],
        ['label' => 'Forums',         'url' => home_url('/forums/'),        'match' => is_page('forums')],
    ];
}

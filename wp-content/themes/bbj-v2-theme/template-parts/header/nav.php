<?php
/**
 * Primary navigation — dark blue bar under logo row.
 * Left:  Watch Feeds • LIVE (yellow)
 * Center: wp_nav_menu (Home, Contact, Feed Updates, Directory, Log In, Register)
 * Right: Go Ad Free (yellow)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<nav class="bg-primary-500 text-white" aria-label="<?php esc_attr_e('Primary', 'bbj-v2-theme'); ?>">
    <div class="mx-auto max-w-screen-xl px-4 py-2 flex items-center justify-between gap-4">

        <a href="<?php echo esc_url(home_url('/watch-feeds/')); ?>"
           class="flex items-center gap-2 font-osw uppercase tracking-wider text-secondary-500 hover:text-white transition">
            <span><?php esc_html_e('Watch Feeds', 'bbj-v2-theme'); ?></span>
            <span class="inline-flex items-center gap-1 bg-accent-red text-white text-xs font-semibold px-2 py-0.5 rounded-full uppercase">
                <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" aria-hidden="true"></span>
                <?php esc_html_e('LIVE', 'bbj-v2-theme'); ?>
            </span>
        </a>

        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'hidden md:flex items-center gap-6 font-osw uppercase tracking-wider text-sm',
            'depth'          => 1,
            'fallback_cb'    => 'bbj_v2_fallback_menu',
            'link_before'    => '<span class="hover:text-secondary-500 transition">',
            'link_after'     => '</span>',
        ]);
        ?>

        <a href="<?php echo esc_url(home_url('/go-ad-free/')); ?>"
           class="font-osw uppercase tracking-wider text-secondary-500 hover:text-white transition whitespace-nowrap">
            <?php esc_html_e('Go Ad Free', 'bbj-v2-theme'); ?>
        </a>
    </div>
</nav>

<?php
/**
 * Fallback primary menu (used until user assigns one in Appearance → Menus).
 */
function bbj_v2_fallback_menu(): void
{
    $items = [
        ['label' => 'Home',         'url' => home_url('/')],
        ['label' => 'Contact',      'url' => home_url('/contact/')],
        ['label' => 'Feed Updates', 'url' => home_url('/feed-updates/')],
        ['label' => 'Directory',    'url' => home_url('/directory/')],
    ];
    if (is_user_logged_in()) {
        $items[] = ['label' => 'Log Out', 'url' => wp_logout_url(home_url('/'))];
    } else {
        $items[] = ['label' => 'Log In',   'url' => wp_login_url()];
        $items[] = ['label' => 'Register', 'url' => wp_registration_url()];
    }
    echo '<ul class="hidden md:flex items-center gap-6 font-osw uppercase tracking-wider text-sm">';
    foreach ($items as $item) {
        printf(
            '<li><a href="%s" class="hover:text-secondary-500 transition">%s</a></li>',
            esc_url($item['url']),
            esc_html($item['label'])
        );
    }
    echo '</ul>';
}

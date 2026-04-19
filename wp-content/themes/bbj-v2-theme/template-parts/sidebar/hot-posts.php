<?php
/**
 * Hot Posts sidebar card — top 5 most-commented posts in the last 30 days.
 */

if (!defined('ABSPATH')) {
    exit;
}

$posts = bbj_v2_hot_posts(5, 30);
if (empty($posts)) {
    return;
}
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('Hot Posts', 'bbj-v2-theme'); ?></h2>
    <ol class="space-y-3">
        <?php foreach ($posts as $i => $p) :
            $comments = (int) get_comments_number($p->ID);
        ?>
            <li class="flex gap-3 items-start">
                <span class="font-osw text-primary-500 dark:text-secondary-500 text-lg w-6 shrink-0" aria-hidden="true">
                    <?php echo (int) ($i + 1); ?>
                </span>
                <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="group min-w-0 flex-1">
                    <span class="block text-sm font-semibold leading-snug text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors">
                        <?php echo esc_html($p->post_title); ?>
                    </span>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider">
                        <?php printf(esc_html(_n('%d comment', '%d comments', $comments, 'bbj-v2-theme')), $comments); ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

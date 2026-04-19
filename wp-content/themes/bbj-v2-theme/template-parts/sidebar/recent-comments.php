<?php
/**
 * Recent Comments sidebar card — last 5 approved comments site-wide.
 */

if (!defined('ABSPATH')) {
    exit;
}

$comments = bbj_v2_homepage_recent_comments(5);
if (empty($comments)) {
    return;
}
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('Recent Comments', 'bbj-v2-theme'); ?></h2>
    <ul class="space-y-3">
        <?php foreach ($comments as $c) :
            $content = wp_trim_words(wp_strip_all_tags($c->comment_content), 15, '…');
            $link    = get_comment_link($c);
            $author  = $c->comment_author ?: __('Anonymous', 'bbj-v2-theme');
        ?>
            <li class="text-sm">
                <a href="<?php echo esc_url($link); ?>" class="block group">
                    <div class="font-osw uppercase tracking-wide text-xs text-gray-600 dark:text-gray-400">
                        <?php echo esc_html($author); ?>
                        <span class="mx-1">·</span>
                        <span data-nosnippet><?php echo esc_html(human_time_diff(strtotime($c->comment_date_gmt), time())); ?> ago</span>
                    </div>
                    <div class="text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors">
                        <?php echo esc_html($content); ?>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

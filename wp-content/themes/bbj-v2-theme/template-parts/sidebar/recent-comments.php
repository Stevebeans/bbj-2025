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
<section class="bbj-card bbj-recent-comments">
    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <?php esc_html_e('Recent Comments', 'bbj-v2-theme'); ?>
        </h2>
    </div>
    <ul>
        <?php foreach ($comments as $c) :
            $content = wp_trim_words(wp_strip_all_tags($c->comment_content), 18, '…');
            $link    = get_comment_link($c);
            $author  = $c->comment_author ?: __('Anonymous', 'bbj-v2-theme');
            $avatar  = get_avatar($c, 40, '', $author, [
                'class'      => 'w-10 h-10 rounded-full block',
                'extra_attr' => 'loading="lazy"',
            ]);
            $when    = human_time_diff(strtotime($c->comment_date_gmt), time()) . ' ago';
        ?>
            <li class="py-3 border-b border-dashed border-gray-300 dark:border-gray-700 first:pt-0 last:border-b-0 last:pb-0">
                <a href="<?php echo esc_url($link); ?>" class="flex gap-3 group -mx-2 px-2 py-1 rounded hover:bg-stone-50 dark:hover:bg-gray-800/50 transition-colors">
                    <div class="shrink-0 w-10 h-10 rounded-full overflow-hidden bg-stone-200 dark:bg-gray-700 ring-1 ring-stone-200 dark:ring-gray-700">
                        <?php echo $avatar; // WP-escaped already ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-1.5 mb-0.5">
                            <span class="font-osw uppercase tracking-wide text-xs font-semibold text-primary-500 dark:text-secondary-500 truncate">
                                <?php echo esc_html($author); ?>
                            </span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0" data-nosnippet>· <?php echo esc_html($when); ?></span>
                        </div>
                        <div class="text-sm leading-snug text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100 transition-colors">
                            <?php echo esc_html($content); ?>
                        </div>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php
/**
 * Plain-server fallback for the comment island.
 * Triggered by ?bbjcomments=plain — used by:
 *   • no-JS readers
 *   • bundle-load failure recovery (3 retries)
 *   • moderator quick-scan view
 */

if (!defined('ABSPATH')) { exit; }
?>
<section id="comments" class="bbj-card bbj-comments-plain">
    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <?php
            $cnt = (int) get_comments_number();
            printf(esc_html(_n('%d Comment', '%d Comments', $cnt, 'bbj-v2-theme')), $cnt);
            ?>
            <span class="ml-2 text-xs uppercase tracking-wider text-gray-400">(<?php esc_html_e('Plain View', 'bbj-v2-theme'); ?>)</span>
        </h2>
    </div>

    <?php if (have_comments()) : ?>
        <ol class="space-y-4">
            <?php wp_list_comments(['style' => 'ol', 'avatar_size' => 40, 'short_ping' => true]); ?>
        </ol>
        <?php the_comments_pagination(['mid_size' => 1]); ?>
    <?php else : ?>
        <p class="text-sm text-gray-500"><?php esc_html_e('No comments yet.', 'bbj-v2-theme'); ?></p>
    <?php endif; ?>

    <?php if (is_user_logged_in()) : ?>
        <?php comment_form(['class_form' => 'space-y-4 mt-6', 'class_submit' => 'btn-primary']); ?>
    <?php else : ?>
        <p class="mt-4 text-sm">
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="text-primary-500 underline">
                <?php esc_html_e('Log in to comment →', 'bbj-v2-theme'); ?>
            </a>
        </p>
    <?php endif; ?>
</section>

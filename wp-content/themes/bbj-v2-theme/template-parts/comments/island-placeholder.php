<?php
/**
 * Comment island placeholder — server-rendered shell. The React island
 * mounts into #bbj-comments-root once the bundle hydrates.
 */

if (!defined('ABSPATH')) { exit; }

$post_id      = (int) get_queried_object_id();
$count        = (int) get_comments_number($post_id);
$can_comment  = is_user_logged_in() ? 1 : 0;
$comment_q    = isset($_GET['comment']) ? (int) $_GET['comment'] : 0;
?>
<section
    id="bbj-comments-root"
    class="bbj-card bbj-comments-island"
    data-post-id="<?php echo esc_attr($post_id); ?>"
    data-comment-count="<?php echo esc_attr($count); ?>"
    data-can-comment="<?php echo esc_attr($can_comment); ?>"
    data-comments-open="1"
    data-permalink-comment="<?php echo esc_attr($comment_q); ?>"
    aria-label="<?php esc_attr_e('Comments', 'bbj-v2-theme'); ?>"
>
    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <?php
            printf(
                esc_html(_n('%d Comment', '%d Comments', $count, 'bbj-v2-theme')),
                $count
            );
            ?>
        </h2>
    </div>
    <div class="bbj-comments-skeleton text-sm text-gray-500 dark:text-gray-400" aria-hidden="true">
        <?php esc_html_e('Loading comments…', 'bbj-v2-theme'); ?>
    </div>
    <noscript>
        <p class="mt-4">
            <a href="<?php echo esc_url(add_query_arg('bbjcomments', 'plain')); ?>" class="text-primary-500 underline">
                <?php esc_html_e('View comments →', 'bbj-v2-theme'); ?>
            </a>
        </p>
    </noscript>
</section>

<?php
/**
 * Comments Template
 *
 * Note: The bigbrotherjunkies-data plugin has a full custom comment system.
 * This is the basic WP fallback for development/testing.
 */

if (post_password_required()) return;
?>

<div id="comments" class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
    <h2 class="section-header mb-4">
        <?php
        $count = get_comments_number();
        if ($count > 0) {
            printf(_n('%d Comment', '%d Comments', $count, 'bbj-v2-theme'), $count);
        } else {
            echo 'Leave a Comment';
        }
        ?>
    </h2>

    <?php if (have_comments()) : ?>
        <ol class="space-y-4 mb-6">
            <?php
            wp_list_comments([
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 48,
            ]);
            ?>
        </ol>

        <?php the_comments_navigation(); ?>
    <?php endif; ?>

    <?php
    comment_form([
        'class_form'    => 'space-y-4',
        'class_submit'  => 'btn-primary',
        'title_reply'   => '<span class="font-osw text-xl text-primary-500 dark:text-secondary-500">Leave a Reply</span>',
    ]);
    ?>
</div>

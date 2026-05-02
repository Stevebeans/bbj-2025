<?php
/**
 * Latest from the Feeds — 5 rich cards, an inline ad, then 5 more rich cards.
 */

if (!defined('ABSPATH')) {
    exit;
}

$items = bbj_v2_homepage_latest_feeds(10);
if (empty($items)) {
    return;
}
$first_batch  = array_slice($items, 0, 5);
$second_batch = array_slice($items, 5, 5);
?>
<section id="latest-feeds" class="bbj-card bbj-latest-feeds">

    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <a href="<?php echo esc_url(home_url('/feed-updates/')); ?>" class="no-underline hover:text-secondary-500 dark:hover:text-secondary-400">
                <?php esc_html_e('Latest from the Feeds', 'bbj-v2-theme'); ?>
            </a>
        </h2>
    </div>

    <div class="relative border-l-2 border-gray-200 dark:border-gray-700 pl-1 sm:pl-0">
        <?php foreach ($first_batch as $item) :
            get_template_part('template-parts/content/feed-update-card', null, [
                'post'     => $item['post'],
                'type'     => $item['type'],
                'location' => $item['location'],
                'variant'  => 'rich',
            ]);
        endforeach; ?>
    </div>

    <div class="my-6">
        <?php get_template_part('template-parts/components/ad-placeholder', null, [
            'slot'        => 'homepage_feeds_inline',
            'size'        => '300x250',
            'mobile_size' => '300x250',
            'note'        => __('Homepage · mid feeds break', 'bbj-v2-theme'),
        ]); ?>
    </div>

    <?php if (!empty($second_batch)) : ?>
        <div class="relative border-l-2 border-gray-200 dark:border-gray-700 pl-1 sm:pl-0">
            <?php foreach ($second_batch as $item) :
                get_template_part('template-parts/content/feed-update-card', null, [
                    'post'     => $item['post'],
                    'type'     => $item['type'],
                    'location' => $item['location'],
                    'variant'  => 'rich',
                ]);
            endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="mt-4 text-right">
        <a href="<?php echo esc_url(home_url('/feed-updates/')); ?>" class="font-osw uppercase tracking-wider text-sm text-primary-500 dark:text-secondary-500 hover:underline">
            <?php esc_html_e('Click here to see more updates →', 'bbj-v2-theme'); ?>
        </a>
    </p>
</section>

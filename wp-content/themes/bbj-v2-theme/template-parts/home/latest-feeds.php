<?php
/**
 * Latest from the Feeds — 5 rich cards + 10 compact rows.
 */

if (!defined('ABSPATH')) {
    exit;
}

$items = bbj_v2_homepage_latest_feeds(15);
if (empty($items)) {
    return;
}
$rich_items    = array_slice($items, 0, 5);
$compact_items = array_slice($items, 5, 10);
?>
<section id="latest-feeds" class="bbj-latest-feeds">
    <div class="flex items-baseline justify-between mb-2">
        <h2 class="section-header">
            <a href="<?php echo esc_url(home_url('/feed-updates/')); ?>" class="no-underline hover:text-secondary-500">
                <?php esc_html_e('Latest from the Feeds', 'bbj-v2-theme'); ?>
            </a>
        </h2>
    </div>

    <div class="relative border-l-2 border-gray-200 dark:border-gray-700 pl-1 sm:pl-0">
        <?php foreach ($rich_items as $item) :
            get_template_part('template-parts/content/feed-update-card', null, [
                'post'     => $item['post'],
                'type'     => $item['type'],
                'location' => $item['location'],
                'variant'  => 'rich',
            ]);
        endforeach; ?>
    </div>

    <?php if (!empty($compact_items)) : ?>
        <div class="mt-6">
            <h3 class="font-osw uppercase tracking-wider text-sm text-gray-700 dark:text-gray-300 mb-2">
                <?php esc_html_e('Quick hits', 'bbj-v2-theme'); ?>
            </h3>
            <ul class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 px-4">
                <?php foreach ($compact_items as $item) :
                    get_template_part('template-parts/content/feed-update-card', null, [
                        'post'     => $item['post'],
                        'type'     => $item['type'],
                        'location' => $item['location'],
                        'variant'  => 'compact',
                    ]);
                endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p class="mt-4 text-right">
        <a href="<?php echo esc_url(home_url('/feed-updates/')); ?>" class="font-osw uppercase tracking-wider text-sm text-primary-500 dark:text-secondary-500 hover:underline">
            <?php esc_html_e('See all feed updates →', 'bbj-v2-theme'); ?>
        </a>
    </p>
</section>

<?php
/**
 * Spoiler bar — horizontal scroll of current-season players with status pills.
 * Data: bbj_v2_get_spoiler_bar() (cached 300s)
 */

if (!defined('ABSPATH')) {
    exit;
}

$players = bbj_v2_get_spoiler_bar();
if (empty($players)) {
    return;
}
?>
<div class="bg-slate-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
    <div class="mx-auto max-w-screen-xl px-2">
        <ul class="flex overflow-x-auto gap-2 py-2 snap-x snap-mandatory scroll-smooth"
            role="list" aria-label="<?php esc_attr_e('Current season players', 'bbj-v2-theme'); ?>">
            <?php foreach ($players as $p) :
                $name   = isset($p['name'])   ? (string) $p['name']   : '';
                $status = isset($p['status']) ? (string) $p['status'] : 'active';
                $label  = isset($p['status_label']) ? (string) $p['status_label'] : ucfirst($status);
                $image  = isset($p['image'])  ? (string) $p['image']  : (isset($p['profile_picture']) ? (string) $p['profile_picture'] : '');
                $link   = isset($p['url'])    ? (string) $p['url']    : (isset($p['link']) ? (string) $p['link'] : '#');
                $pill   = bbj_v2_status_class($status);
                $imgFx  = bbj_v2_status_img_class($status);
            ?>
                <li class="flex-none snap-start w-24 text-center">
                    <a href="<?php echo esc_url($link); ?>" class="block group" aria-label="<?php echo esc_attr($name . ' — ' . $label); ?>">
                        <span class="block text-xs font-osw uppercase tracking-wide rounded-t-md px-2 py-0.5 border-b-2 <?php echo esc_attr($pill); ?>">
                            <?php echo esc_html($label); ?>
                        </span>
                        <?php if ($image !== '') : ?>
                            <img src="<?php echo esc_url($image); ?>"
                                 alt="<?php echo esc_attr($name); ?>"
                                 class="w-24 h-24 object-cover <?php echo esc_attr($imgFx); ?>"
                                 loading="lazy" decoding="async" width="100" height="100">
                        <?php else : ?>
                            <div class="w-24 h-24 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></div>
                        <?php endif; ?>
                        <span class="block mt-1 text-xs text-gray-700 dark:text-gray-200 truncate">
                            <?php echo esc_html($name); ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

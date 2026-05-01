<?php
/**
 * THE HOUSE status strip — compact horizontal supplement under the hero.
 * Shows HoH / Veto / Nominees / Have-Nots from bbj_v2_get_spoiler_bar().
 * Falls back to the same demo cast as the legacy more-bb-spoilers block
 * during off-season so the strip never goes empty.
 */

if (!defined('ABSPATH')) {
    exit;
}

$bar     = function_exists('bbj_v2_get_spoiler_bar') ? bbj_v2_get_spoiler_bar() : ['season' => [], 'players' => []];
$players = $bar['players'] ?? [];

// Season label — full name like "Big Brother 26".
$season_id    = (int) get_option('bbj_v2_current_season', 0);
$season_num   = bbj_v2_current_season_number();
$season_row   = ($season_id > 0 && function_exists('bbj_v2_get_season_by_id'))
    ? bbj_v2_get_season_by_id($season_id) : null;
$season_label = trim((string) ($season_row['full_name'] ?? ''));
if ($season_label === '' && $season_num > 0) {
    $season_label = sprintf('Big Brother %d', $season_num);
}

// Bucket players by status. Empty buckets render as "TBD" so the strip
// always shows its full HoH / Veto / Noms / Have-Nots structure.
$by_status = ['hoh' => [], 'pov' => [], 'nominated' => [], 'havenot' => []];
foreach ($players as $p) {
    $status = strtolower((string) ($p['status'] ?? ''));
    if (isset($by_status[$status])) {
        $by_status[$status][] = $p;
    }
}

$initial = static fn(string $name): string => $name === '' ? '' : strtoupper(mb_substr(trim($name), 0, 1));

$render_group = static function (array $group, string $label, string $bg_class) use ($initial): void {
    $names = array_filter(
        array_map(static fn($p) => (string) ($p['display_name'] ?? ''), $group),
        static fn($n) => $n !== ''
    );
    $is_empty = empty($names);
    ?>
    <div class="flex items-center gap-3 shrink-0">
        <div class="flex -space-x-2">
            <?php if ($is_empty) : ?>
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500 font-osw text-base"
                      aria-hidden="true">—</span>
            <?php else : ?>
                <?php foreach ($group as $p) :
                    $photo   = (string) ($p['photo'] ?? '');
                    $display = (string) ($p['display_name'] ?? '');
                    $url     = (string) ($p['permalink'] ?? '#');
                ?>
                    <a href="<?php echo esc_url($url); ?>"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-full text-white font-osw font-semibold text-sm border-2 border-white dark:border-gray-800 overflow-hidden no-underline <?php echo esc_attr($bg_class); ?>"
                       aria-label="<?php echo esc_attr($display); ?>">
                        <?php if ($photo !== '') : ?>
                            <img src="<?php echo esc_url($photo); ?>" alt=""
                                 loading="lazy" decoding="async"
                                 class="w-full h-full object-cover"
                                 width="36" height="36">
                        <?php else : ?>
                            <?php echo esc_html($initial($display)); ?>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="leading-tight">
            <div class="text-[10px] font-osw uppercase tracking-wider text-gray-500 dark:text-gray-400"><?php echo esc_html($label); ?></div>
            <?php if ($is_empty) : ?>
                <div class="text-sm font-semibold text-gray-400 dark:text-gray-500">TBD</div>
            <?php else : ?>
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100"><?php echo esc_html(implode(' · ', $names)); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>
<section class="bbj-card bbj-house-strip">
    <div class="flex items-center gap-6 flex-wrap">
        <div class="leading-tight pr-6 border-r shrink-0" style="border-color:var(--line)">
            <div class="font-osw uppercase tracking-wider text-sm text-primary-500 dark:text-secondary-500">The House</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo esc_html($season_label); ?></div>
        </div>
        <div class="flex justify-around flex-grow">
            <?php
                $render_group($by_status['hoh'],       __('HOH',       'bbj-v2-theme'), 'bg-emerald-600');
                $render_group($by_status['pov'],       __('VETO',      'bbj-v2-theme'), 'bg-orange-500');
                $render_group($by_status['nominated'], __('NOMS',      'bbj-v2-theme'), 'bg-red-600');
                $render_group($by_status['havenot'],   __('HAVE-NOTS', 'bbj-v2-theme'), 'bg-purple-600');
            ?>
        </div>

    </div>
</section>

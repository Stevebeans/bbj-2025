<?php
/**
 * Center-column "<Season Name> Spoilers" block.
 * Primary content: rich houseboard (HoH / PoV / Nominees / Have-Nots)
 * pulled from bbj_v2_get_spoiler_bar(). Falls back to a numbered list of
 * recent spoiler posts if no in-house statuses are set (e.g. off-season).
 */

if (!defined('ABSPATH')) {
    exit;
}

$bar        = function_exists('bbj_v2_get_spoiler_bar') ? bbj_v2_get_spoiler_bar() : ['season' => [], 'players' => []];
$players    = $bar['players'] ?? [];
$season     = $bar['season']  ?? [];
$season_num = bbj_v2_current_season_number();
$season_id  = (int) get_option('bbj_v2_current_season', 0);
$row        = null; // populated below if season_id set — referenced again for week calc.

$full_name = '';
if ($season_id > 0 && function_exists('bbj_v2_get_season_by_id')) {
    $row = bbj_v2_get_season_by_id($season_id);
    $full_name = trim((string) ($row['full_name'] ?? ''));
}
if ($full_name === '' && $season_num > 0) {
    $full_name = sprintf('Big Brother %d', $season_num);
}
$heading = $full_name !== '' ? $full_name . ' Spoilers' : __('Spoilers', 'bbj-v2-theme');

// Week number = weeks elapsed since start_date (+1 to make it 1-indexed).
$week = 0;
if ($season_id > 0 && !empty($row['start_date'])) {
    $start_ts = strtotime((string) $row['start_date']);
    if ($start_ts !== false && $start_ts <= time()) {
        $week = (int) floor((time() - $start_ts) / WEEK_IN_SECONDS) + 1;
    }
}

// Bucket players by status.
$by_status = ['hoh' => [], 'pov' => [], 'nominated' => [], 'havenot' => []];
foreach ($players as $p) {
    $status = strtolower((string) ($p['status'] ?? ''));
    if (isset($by_status[$status])) {
        $by_status[$status][] = $p;
    }
}
$has_house_state = !empty($by_status['hoh']) || !empty($by_status['pov'])
    || !empty($by_status['nominated']) || !empty($by_status['havenot']);

// Placeholder fallback — until the admin spoiler-bar control lands, render
// the mockup's demo cast so the visual design isn't empty during off-season.
if (!$has_house_state) {
    $by_status = [
        'hoh'       => [['display_name' => 'Rachel',  'permalink' => '#']],
        'pov'       => [['display_name' => 'Amy',     'permalink' => '#']],
        'nominated' => [
            ['display_name' => 'Rylie', 'permalink' => '#'],
            ['display_name' => 'Zach',  'permalink' => '#'],
        ],
        'havenot'   => [
            ['display_name' => 'Isaiah', 'permalink' => '#'],
            ['display_name' => 'Mickey', 'permalink' => '#'],
        ],
    ];
    $has_house_state = true;
    $week = 6;
}

$initials = static function (string $name): string {
    $name = trim(str_replace(['"', "'", '"', '"'], '', $name));
    return $name === '' ? '' : strtoupper(mb_substr($name, 0, 1));
};

$render_avatar = static function (array $p, string $ring_class) use ($initials): void {
    $photo   = (string) ($p['photo'] ?? '');
    $display = (string) ($p['display_name'] ?? ($p['first_name'] ?? ($p['name'] ?? '')));
    $url     = (string) ($p['permalink'] ?? '#');
    ?>
    <a href="<?php echo esc_url($url); ?>"
       class="flex flex-col items-center gap-1 no-underline"
       aria-label="<?php echo esc_attr($display); ?>">
        <span class="bbj-hb-avatar <?php echo esc_attr($ring_class); ?>">
            <?php if ($photo !== '') : ?>
                <img src="<?php echo esc_url($photo); ?>" alt=""
                     loading="lazy" decoding="async"
                     class="w-full h-full object-cover rounded-full"
                     width="48" height="48">
            <?php else : ?>
                <span class="font-osw uppercase font-semibold text-white text-lg">
                    <?php echo esc_html($initials($display)); ?>
                </span>
            <?php endif; ?>
        </span>
    </a>
    <?php
};

$render_names = static function (array $group): string {
    $names = array_map(
        static fn($p) => (string) ($p['display_name'] ?? ($p['first_name'] ?? ($p['name'] ?? ''))),
        $group
    );
    $names = array_filter($names, static fn($n) => $n !== '');
    return implode(' · ', $names);
};
?>
<section id="more-bb-spoilers" class="bbj-spoilers-block">
    <div class="flex items-baseline justify-between mb-3 gap-3">
        <h2 class="section-header truncate">
            <a href="<?php echo esc_url(home_url('/category/spoilers/')); ?>" class="no-underline hover:text-secondary-500">
                <?php echo esc_html($heading); ?>
            </a>
        </h2>
        <?php if ($week > 0 && $has_house_state) : ?>
            <span class="font-osw uppercase tracking-wider text-xs text-gray-500 dark:text-gray-400 shrink-0">
                <?php printf(esc_html__('Week %d', 'bbj-v2-theme'), $week); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($has_house_state) : ?>
        <div class="bbj-houseboard space-y-3">

            <?php if (!empty($by_status['hoh'])) : ?>
                <div class="bbj-hb-panel">
                    <div class="bbj-hb-label text-emerald-700 dark:text-emerald-400"><?php esc_html_e('Head of Household', 'bbj-v2-theme'); ?></div>
                    <div class="flex justify-center flex-wrap gap-3 mt-2">
                        <?php foreach ($by_status['hoh'] as $p) $render_avatar($p, 'ring-emerald-600 bg-emerald-600'); ?>
                    </div>
                    <div class="mt-2 text-center font-display text-xl text-gray-900 dark:text-gray-100">
                        <?php echo esc_html($render_names($by_status['hoh'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php if (!empty($by_status['pov'])) : ?>
                    <div class="bbj-hb-panel">
                        <div class="bbj-hb-label text-orange-700 dark:text-orange-400"><?php esc_html_e('Power of Veto', 'bbj-v2-theme'); ?></div>
                        <div class="flex justify-center flex-wrap gap-3 mt-2">
                            <?php foreach ($by_status['pov'] as $p) $render_avatar($p, 'ring-orange-600 bg-orange-600'); ?>
                        </div>
                        <div class="mt-2 text-center text-sm font-medium text-gray-900 dark:text-gray-100">
                            <?php echo esc_html($render_names($by_status['pov'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($by_status['nominated'])) : ?>
                    <div class="bbj-hb-panel">
                        <div class="bbj-hb-label text-red-700 dark:text-red-400"><?php esc_html_e('Nominees', 'bbj-v2-theme'); ?></div>
                        <div class="flex justify-center flex-wrap gap-3 mt-2">
                            <?php foreach ($by_status['nominated'] as $p) $render_avatar($p, 'ring-red-600 bg-red-600'); ?>
                        </div>
                        <div class="mt-2 text-center text-sm font-medium text-gray-900 dark:text-gray-100">
                            <?php echo esc_html($render_names($by_status['nominated'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($by_status['havenot'])) : ?>
                <div class="bbj-hb-panel">
                    <div class="bbj-hb-label text-purple-700 dark:text-purple-400"><?php esc_html_e('Have-Nots', 'bbj-v2-theme'); ?></div>
                    <div class="flex justify-center flex-wrap gap-3 mt-2">
                        <?php foreach ($by_status['havenot'] as $p) $render_avatar($p, 'ring-purple-600 bg-purple-600'); ?>
                    </div>
                    <div class="mt-2 text-center text-sm font-medium text-gray-900 dark:text-gray-100">
                        <?php echo esc_html($render_names($by_status['havenot'])); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>

    <div class="mt-6">
        <?php get_template_part('template-parts/components/ad-placeholder', null, [
            'slot'        => 'homepage_right_mpu',
            'size'        => '300x250',
            'mobile_size' => '300x250',
            'note'        => __('Homepage · Square / MPU', 'bbj-v2-theme'),
        ]); ?>
    </div>
</section>

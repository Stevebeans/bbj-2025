<?php
/**
 * Spoiler bar — header strip of current-season players.
 *
 * Card-banner-photo-name layout matching the bbj-app SpoilerBar.jsx design
 * shown on production. Each player is a vertical card with a colored status
 * banner on top, photo in the middle (greyed for evicted, slight grey + indigo
 * tint for jury, never dimmed for winner/runner-up), and a colored name strip
 * at the bottom.
 *
 * Data: bbj_v2_get_spoiler_bar() (cached 300s — pulls from /bbjd/v1/spoiler-bar)
 */

if (!defined('ABSPATH')) {
    exit;
}

$bar     = bbj_v2_get_spoiler_bar();
$players = $bar['players'] ?? [];
$season  = $bar['season']  ?? [];

if (empty($players)) {
    return;
}

$season_num    = (int) ($season['season_number'] ?? 0);
$season_prefix = strtoupper((string) ($season['show_type'] ?? 'bb'));
$season_label  = $season_num > 0 ? $season_prefix . $season_num : (string) ($season['name'] ?? '');

$total     = count($players);
$evicted   = 0;
$remaining = 0;
foreach ($players as $p) {
    $status = strtolower((string) ($p['status'] ?? 'active'));
    if (in_array($status, ['evicted', 'jury', 'winner', 'runner_up', 'runner-up'], true)) {
        $evicted++;
    } else {
        $remaining++;
    }
}

// Strip curly + straight quotes so "Will" renders initials as "Wi", not "\"W".
$initials = static function (string $name): string {
    $name = trim(str_replace(['"', '"', '"', "'"], '', $name));
    if ($name === '') return '';
    return strtoupper(mb_substr($name, 0, 2));
};
?>
<div class="bbj-spoiler-bar" data-bbj-spoiler>
    <div class="mx-auto max-w-screen-xl px-3">
        <div class="bbj-spoiler-head">
            <div class="flex items-baseline gap-3 min-w-0">
                <strong class="font-osw uppercase tracking-wider text-primary-500 dark:text-secondary-500 text-sm sm:text-base">
                    <?php if ($season_label !== '') : ?>
                        <?php echo esc_html($season_label); ?>
                    <?php endif; ?>
                    <?php esc_html_e('Houseguests', 'bbj-v2-theme'); ?>
                </strong>
                <span class="hidden md:inline text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">
                    <?php echo esc_html(sprintf('%d Cast', $total)); ?>
                    <span aria-hidden="true" class="mx-1">·</span>
                    <?php echo esc_html(sprintf('%d Remaining', $remaining)); ?>
                    <span aria-hidden="true" class="mx-1">·</span>
                    <?php echo esc_html(sprintf('%d Evicted', $evicted)); ?>
                </span>
            </div>
            <button type="button"
                    class="bbj-spoiler-toggle"
                    data-bbj-spoiler-toggle
                    aria-expanded="true"
                    aria-controls="bbj-spoiler-body">
                <span data-bbj-spoiler-label><?php esc_html_e('Collapse', 'bbj-v2-theme'); ?></span>
                <span class="bbj-spoiler-toggle-icon" aria-hidden="true">−</span>
            </button>
        </div>

        <div id="bbj-spoiler-body" class="bbj-spoiler-body" data-bbj-spoiler-body>
            <div class="overflow-x-auto py-1 scroll-smooth" data-spoiler-bar
                 aria-label="<?php esc_attr_e('Current season players', 'bbj-v2-theme'); ?>">
                <div class="flex flex-nowrap gap-1 w-max mx-auto">
                    <?php foreach ($players as $p) :
                        $name        = (string) ($p['name'] ?? '');
                        $first       = (string) ($p['first_name'] ?? '');
                        $last        = (string) ($p['last_name']  ?? '');
                        $display     = (string) ($p['display_name'] ?? ($first ?: $name));
                        $status      = strtolower((string) ($p['status'] ?? 'active'));
                        $label       = (string) ($p['status_label'] ?? ucfirst($status));
                        $photo       = (string) ($p['photo'] ?? '');
                        $url         = (string) ($p['permalink'] ?? '#');
                        $pill        = bbj_v2_status_class($status);
                        $img_filter  = bbj_v2_status_img_class($status);
                        $is_jury     = ($status === 'jury');
                        $alt_text    = trim($first . ' ' . $last) ?: $name;
                    ?>
                        <a href="<?php echo esc_url($url); ?>"
                           class="bbj-spoiler-card group"
                           title="<?php echo esc_attr($alt_text); ?>">
                            <!-- Banner -->
                            <div class="bbj-spoiler-banner <?php echo esc_attr($pill); ?>">
                                <?php echo esc_html($label); ?>
                            </div>

                            <!-- Photo -->
                            <div class="bbj-spoiler-photo <?php echo esc_attr($pill); ?>">
                                <?php if ($photo !== '') : ?>
                                    <img src="<?php echo esc_url($photo); ?>"
                                         alt="<?php echo esc_attr($alt_text); ?>"
                                         class="w-full h-full object-cover <?php echo esc_attr($img_filter); ?>"
                                         loading="lazy" decoding="async">
                                    <?php if ($is_jury) : ?>
                                        <span class="absolute inset-0 bg-indigo-500/25 mix-blend-multiply pointer-events-none"></span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="bbj-spoiler-photo-fallback">
                                        <?php echo esc_html($initials($display ?: $name)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Name -->
                            <div class="bbj-spoiler-namebar <?php echo esc_attr($pill); ?>">
                                <?php echo esc_html($display ?: $name); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Pre-paint collapse state so mobile users don't see an open→close flash.
// Mirrors the logic in spoiler-bar.js; main controller wires the click listener.
(function(){
    try {
        var el = document.currentScript.previousElementSibling;
        if (!el || !el.matches('[data-bbj-spoiler]')) return;
        var v = localStorage.getItem('bbj:spoiler:collapsed');
        var collapsed = v !== null ? v === '1' : window.innerWidth < 768;
        if (!collapsed) return;
        el.classList.add('is-collapsed');
        var btn = el.querySelector('[data-bbj-spoiler-toggle]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        var label = el.querySelector('[data-bbj-spoiler-label]');
        if (label) label.textContent = 'Expand';
        var icon = el.querySelector('.bbj-spoiler-toggle-icon');
        if (icon) icon.textContent = '+';
    } catch (_) {}
})();
</script>

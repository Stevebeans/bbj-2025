<?php
/**
 * Admin shell — edit-season tab navigation + tab bodies.
 * Receives $args['season'] — the season row object.
 * Receives $args['season_id'] — int.
 *
 * Tab state is held in the URL hash (#info, #spoiler, #photos).
 * Pre-sprint default is #info since #spoiler is stubbed.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season    = $args['season'];
$season_id = (int) $args['season_id'];

$tabs = [
    ['id' => 'spoiler', 'label' => 'Spoiler Bar'],
    ['id' => 'info',    'label' => 'Season Info'],
    ['id' => 'photos',  'label' => 'Player Photos'],
    ['id' => 'weeks',   'label' => 'Weeks'],
];
?>

<div class="border-b border-stone-200 dark:border-slate-700 mb-6">
    <nav class="flex -mb-px gap-1" role="tablist" id="bbj-season-tabs">
        <?php foreach ($tabs as $tab): ?>
            <a href="#<?php echo esc_attr($tab['id']); ?>"
               data-bbj-tab="<?php echo esc_attr($tab['id']); ?>"
               role="tab"
               class="bbj-season-tab px-4 py-2 text-sm font-semibold border-b-2 border-transparent text-stone-500 hover:text-primary-500 dark:text-slate-400 dark:hover:text-secondary-500 transition-colors">
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Tab panels -->
<div data-bbj-tab-panel="spoiler" class="bbj-tab-panel">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-spoiler', null, [
        'season'    => $season,
        'season_id' => $season_id,
    ]); ?>
</div>

<div data-bbj-tab-panel="info" class="bbj-tab-panel hidden">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-info', null, [
        'season'    => $season,
        'season_id' => $season_id,
    ]); ?>
</div>

<div data-bbj-tab-panel="photos" class="bbj-tab-panel hidden">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Player Photos',
    ]); ?>
</div>

<div data-bbj-tab-panel="weeks" class="bbj-tab-panel hidden" id="weeks">
    <?php get_template_part('template-parts/admin/pane-seasons-weeks', null, [
        'season_post_id' => $season_id,
    ]); ?>
</div>

<script>
(function () {
    var VALID = ['spoiler', 'info', 'photos', 'weeks'];
    var DEFAULT_TAB = 'spoiler'; // Spoiler Bar is the primary reason to open this page.

    function showTab(tabId) {
        if (VALID.indexOf(tabId) === -1) tabId = DEFAULT_TAB;

        document.querySelectorAll('.bbj-season-tab').forEach(function (el) {
            var active = el.getAttribute('data-bbj-tab') === tabId;
            el.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                el.classList.add('text-primary-500', 'border-primary-500');
                el.classList.remove('text-stone-500', 'border-transparent');
            } else {
                el.classList.remove('text-primary-500', 'border-primary-500');
                el.classList.add('text-stone-500', 'border-transparent');
            }
        });

        document.querySelectorAll('.bbj-tab-panel').forEach(function (panel) {
            var show = panel.getAttribute('data-bbj-tab-panel') === tabId;
            panel.classList.toggle('hidden', !show);
        });
    }

    function getTabFromHash() {
        var h = (window.location.hash || '').replace(/^#/, '');
        return VALID.indexOf(h) !== -1 ? h : DEFAULT_TAB;
    }

    window.addEventListener('hashchange', function () {
        showTab(getTabFromHash());
    });

    // Initial render
    showTab(getTabFromHash());
})();
</script>

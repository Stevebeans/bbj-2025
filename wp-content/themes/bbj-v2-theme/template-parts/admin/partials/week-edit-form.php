<?php
/**
 * Per-week edit grid form. Submits to admin-post.php?action=bbj_v2_save_week.
 *
 * Expected args:
 *   - season_post_id (int)
 *   - week (array — wp_bbj_weeks row + comp_count + evicted_count)
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = $args ?? [];
$season_post_id = (int) ($args['season_post_id'] ?? 0);
$week = $args['week'] ?? null;
$season_start = (string) ($args['season_start'] ?? '');
$season_end   = (string) ($args['season_end']   ?? '');
if ($season_post_id <= 0 || !is_array($week)) {
    return;
}

$week_id    = (int) $week['id'];
$players    = bbj_v2_active_players_for_week($season_post_id, $week_id);
$comp_types = bbj_v2_comp_types_active();

// Bucket comp types so the form can render fixed HOH / POV / Misc pills with
// the rest as a "specify subtype" dropdown under Misc.
$hoh_type      = null;
$pov_type      = null;
$misc_type     = null;
$misc_subtypes = [];
foreach ($comp_types as $ct) {
    switch ($ct['slug']) {
        case 'hoh':  $hoh_type  = $ct; break;
        case 'pov':  $pov_type  = $ct; break;
        case 'misc': $misc_type = $ct; break;
        default:     $misc_subtypes[] = $ct;
    }
}
$misc_family_ids = [];
if ($misc_type)        { $misc_family_ids[] = (int) $misc_type['id']; }
foreach ($misc_subtypes as $st) { $misc_family_ids[] = (int) $st['id']; }
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
    <?php wp_nonce_field('bbj_v2_save_week'); ?>
    <input type="hidden" name="action" value="bbj_v2_save_week">
    <input type="hidden" name="season_post_id" value="<?php echo (int) $season_post_id; ?>">
    <input type="hidden" name="week_id" value="<?php echo (int) $week_id; ?>">

    <!-- Week meta -->
    <div class="grid grid-cols-3 gap-3">
        <label class="text-sm">
            <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 dark:text-slate-400 block mb-1">Week #</span>
            <input type="number" name="week_num" value="<?php echo (int) $week['week_num']; ?>"
                   class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm">
        </label>
        <label class="text-sm">
            <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 dark:text-slate-400 block mb-1">Start date</span>
            <input type="date" name="start_date" value="<?php echo esc_attr($week['start_date'] ?? ''); ?>"
                   <?php if ($season_start): ?>min="<?php echo esc_attr($season_start); ?>"<?php endif; ?>
                   <?php if ($season_end):   ?>max="<?php echo esc_attr($season_end);   ?>"<?php endif; ?>
                   class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm">
        </label>
        <label class="text-sm">
            <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 dark:text-slate-400 block mb-1">End date</span>
            <input type="date" name="end_date" value="<?php echo esc_attr($week['end_date'] ?? ''); ?>"
                   <?php if ($season_start): ?>min="<?php echo esc_attr($season_start); ?>"<?php endif; ?>
                   <?php if ($season_end):   ?>max="<?php echo esc_attr($season_end);   ?>"<?php endif; ?>
                   class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm">
        </label>
    </div>

    <label class="text-sm block">
        <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 dark:text-slate-400 block mb-1">Week summary (editorial recap)</span>
        <textarea name="summary" rows="3"
                  class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm"><?php echo esc_textarea($week['summary'] ?? ''); ?></textarea>
    </label>

    <!-- Players grid -->
    <?php if (empty($players)): ?>
        <p class="text-stone-500 italic text-sm">No players found for this season. Make sure players are linked via the Player-Season junction.</p>
    <?php else: ?>
        <div class="rounded-md bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 dark:bg-slate-800 border-b border-stone-200 dark:border-slate-700">
                    <tr class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-600 dark:text-slate-400">
                        <th class="text-left px-3 py-2">Player</th>
                        <th class="text-left px-3 py-2">Comps won</th>
                        <th class="text-left px-3 py-2 w-[60px]">Nom</th>
                        <th class="text-left px-3 py-2 w-[200px]">Saved by</th>
                        <th class="text-left px-3 py-2 w-[60px]">Evict</th>
                        <th class="text-left px-3 py-2 w-[200px]">Voted to evict</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $i => $p):
                        $pid = (int) $p['player_id'];
                        $existing_comp_ids = array_map('intval', array_column($p['comps'] ?? [], 'comp_type_id'));
                    ?>
                        <tr class="border-t border-stone-100 dark:border-slate-800 align-top">
                            <input type="hidden" name="rows[<?php echo $i; ?>][player_id]" value="<?php echo $pid; ?>">
                            <td class="px-3 py-2 text-stone-900 dark:text-slate-200 whitespace-nowrap"><?php echo esc_html($p['player_name']); ?></td>
                            <td class="px-3 py-2">
                                <?php if (empty($comp_types)): ?>
                                    <span class="text-stone-400 italic text-xs">No comp types</span>
                                <?php else:
                                    $hoh_checked  = $hoh_type && in_array((int) $hoh_type['id'], $existing_comp_ids, true);
                                    $pov_checked  = $pov_type && in_array((int) $pov_type['id'], $existing_comp_ids, true);

                                    $misc_selected_id = 0;
                                    foreach ($existing_comp_ids as $eid) {
                                        if (in_array($eid, $misc_family_ids, true)) {
                                            $misc_selected_id = $eid;
                                            break;
                                        }
                                    }
                                    $misc_checked = $misc_selected_id > 0;
                                    $misc_subtype_id = ($misc_type && $misc_selected_id === (int) $misc_type['id'])
                                        ? 0
                                        : $misc_selected_id;
                                ?>
                                    <div class="flex flex-wrap items-center gap-1">
                                        <?php if ($hoh_type): ?>
                                            <label class="bbj-comp-pill inline-flex items-center gap-1 text-[12px] px-2 py-0.5 border border-stone-300 rounded cursor-pointer <?php echo $hoh_checked ? 'bg-amber-100 border-amber-300' : 'bg-white dark:bg-slate-800 dark:border-slate-600'; ?>">
                                                <input type="checkbox"
                                                       name="rows[<?php echo $i; ?>][comps][]"
                                                       value="<?php echo (int) $hoh_type['id']; ?>"
                                                       <?php checked($hoh_checked); ?>
                                                       class="hidden">
                                                <span><?php echo esc_html($hoh_type['name']); ?></span>
                                            </label>
                                        <?php endif; ?>

                                        <?php if ($pov_type): ?>
                                            <label class="bbj-comp-pill inline-flex items-center gap-1 text-[12px] px-2 py-0.5 border border-stone-300 rounded cursor-pointer <?php echo $pov_checked ? 'bg-amber-100 border-amber-300' : 'bg-white dark:bg-slate-800 dark:border-slate-600'; ?>">
                                                <input type="checkbox"
                                                       name="rows[<?php echo $i; ?>][comps][]"
                                                       value="<?php echo (int) $pov_type['id']; ?>"
                                                       <?php checked($pov_checked); ?>
                                                       class="hidden">
                                                <span><?php echo esc_html($pov_type['name']); ?></span>
                                            </label>
                                        <?php endif; ?>

                                        <?php if ($misc_type): ?>
                                            <label class="bbj-comp-misc-pill inline-flex items-center gap-1 text-[12px] px-2 py-0.5 border border-stone-300 rounded cursor-pointer <?php echo $misc_checked ? 'bg-amber-100 border-amber-300' : 'bg-white dark:bg-slate-800 dark:border-slate-600'; ?>">
                                                <input type="checkbox"
                                                       name="rows[<?php echo $i; ?>][misc]"
                                                       value="1"
                                                       <?php checked($misc_checked); ?>
                                                       class="hidden bbj-misc-toggle">
                                                <span>Misc</span>
                                            </label>
                                            <select name="rows[<?php echo $i; ?>][misc_subtype]"
                                                    class="bbj-misc-subtype text-[12px] px-1 py-0.5 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded <?php echo $misc_checked ? '' : 'opacity-50'; ?>"
                                                    <?php disabled(!$misc_checked); ?>>
                                                <option value="">— specify type —</option>
                                                <?php foreach ($misc_subtypes as $st): ?>
                                                    <option value="<?php echo (int) $st['id']; ?>"
                                                            <?php selected((int) $st['id'], $misc_subtype_id); ?>>
                                                        <?php echo esc_html($st['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <input type="checkbox" name="rows[<?php echo $i; ?>][nom]" value="1" <?php checked((int) ($p['nom'] ?? 0)); ?>>
                            </td>
                            <td class="px-3 py-2">
                                <select name="rows[<?php echo $i; ?>][saved_by_player_id]"
                                        class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm">
                                    <option value="">— not saved —</option>
                                    <option value="<?php echo $pid; ?>"
                                            <?php selected((int) ($p['saved_by_player_id'] ?? 0), $pid); ?>>
                                        Self / Twist
                                    </option>
                                    <?php foreach ($players as $other):
                                        $oid = (int) $other['player_id'];
                                        if ($oid === $pid) continue;
                                    ?>
                                        <option value="<?php echo $oid; ?>"
                                                <?php selected((int) ($p['saved_by_player_id'] ?? 0), $oid); ?>>
                                            <?php echo esc_html($other['player_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="checkbox" name="rows[<?php echo $i; ?>][evicted]" value="1" <?php checked((int) ($p['evicted'] ?? 0)); ?>>
                            </td>
                            <td class="px-3 py-2">
                                <?php $row_is_nom = ((int) ($p['nom'] ?? 0) === 1); ?>
                                <select name="rows[<?php echo $i; ?>][voted_for]"
                                        <?php disabled($row_is_nom); ?>
                                        title="<?php echo $row_is_nom ? esc_attr__('Nominees do not vote', 'bbj-v2-theme') : ''; ?>"
                                        class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm <?php echo $row_is_nom ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                    <option value="0">—</option>
                                    <?php foreach ($players as $target):
                                        $tid = (int) $target['player_id'];
                                        if ($tid === $pid) continue;
                                        if ((int) ($target['nom'] ?? 0) !== 1) continue;
                                    ?>
                                        <option value="<?php echo $tid; ?>"
                                                <?php selected((int) ($p['voted_for'] ?? 0), $tid); ?>>
                                            <?php echo esc_html($target['player_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="flex justify-end">
        <button type="submit"
                class="px-5 py-2 bg-primary-500 text-white text-sm font-osw uppercase tracking-wider rounded hover:bg-primary-600 transition-colors">
            Save Week
        </button>
    </div>
</form>

<script>
// Toggle visual state on comp-pill labels (their checkbox is hidden).
document.querySelectorAll('.bbj-comp-pill input[type="checkbox"]').forEach(function (cb) {
    cb.addEventListener('change', function () {
        var label = cb.closest('.bbj-comp-pill');
        if (cb.checked) {
            label.classList.add('bg-amber-100', 'border-amber-300');
            label.classList.remove('bg-white');
        } else {
            label.classList.remove('bg-amber-100', 'border-amber-300');
            label.classList.add('bg-white');
        }
    });
});

// Misc pill: same visual toggle, plus enable/disable the subtype dropdown.
document.querySelectorAll('.bbj-misc-toggle').forEach(function (cb) {
    var label  = cb.closest('.bbj-comp-misc-pill');
    var select = label && label.parentNode
        ? label.parentNode.querySelector('.bbj-misc-subtype')
        : null;
    cb.addEventListener('change', function () {
        if (cb.checked) {
            label.classList.add('bg-amber-100', 'border-amber-300');
            label.classList.remove('bg-white');
            if (select) { select.disabled = false; select.classList.remove('opacity-50'); }
        } else {
            label.classList.remove('bg-amber-100', 'border-amber-300');
            label.classList.add('bg-white');
            if (select) {
                select.disabled = true;
                select.value = '';
                select.classList.add('opacity-50');
            }
        }
    });
});
</script>

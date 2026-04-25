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
if ($season_post_id <= 0 || !is_array($week)) {
    return;
}

$week_id    = (int) $week['id'];
$players    = bbj_v2_active_players_for_week($season_post_id, $week_id);
$comp_types = bbj_v2_comp_types_active();
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
    <?php wp_nonce_field('bbj_v2_save_week'); ?>
    <input type="hidden" name="action" value="bbj_v2_save_week">
    <input type="hidden" name="season_post_id" value="<?php echo (int) $season_post_id; ?>">
    <input type="hidden" name="week_id" value="<?php echo (int) $week_id; ?>">

    <!-- Week meta -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <label class="text-sm">
            <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 dark:text-slate-400 block mb-1">Week #</span>
            <input type="number" name="week_num" value="<?php echo (int) $week['week_num']; ?>"
                   class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm">
        </label>
        <label class="text-sm">
            <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 dark:text-slate-400 block mb-1">Start date</span>
            <input type="date" name="start_date" value="<?php echo esc_attr($week['start_date'] ?? ''); ?>"
                   class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm">
        </label>
        <label class="text-sm">
            <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 dark:text-slate-400 block mb-1">End date</span>
            <input type="date" name="end_date" value="<?php echo esc_attr($week['end_date'] ?? ''); ?>"
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
                                <?php else: ?>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($comp_types as $ct):
                                            $checked = in_array((int) $ct['id'], $existing_comp_ids, true);
                                        ?>
                                            <label class="bbj-comp-pill inline-flex items-center gap-1 text-[12px] px-2 py-0.5 border border-stone-300 rounded cursor-pointer <?php echo $checked ? 'bg-amber-100 border-amber-300' : 'bg-white dark:bg-slate-800 dark:border-slate-600'; ?>">
                                                <input type="checkbox"
                                                       name="rows[<?php echo $i; ?>][comps][]"
                                                       value="<?php echo (int) $ct['id']; ?>"
                                                       <?php checked($checked); ?>
                                                       class="hidden">
                                                <span><?php echo esc_html($ct['name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
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
                                <select name="rows[<?php echo $i; ?>][voted_for]"
                                        class="w-full px-2 py-1 border border-stone-300 dark:border-slate-600 bg-white dark:bg-slate-800 rounded text-sm">
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
                class="px-5 py-2 bg-primary500 text-white text-sm font-osw uppercase tracking-wider rounded hover:bg-primaryHard transition-colors">
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
</script>

<?php
/**
 * Admin shell — single player card inside the Spoiler Bar tab.
 *
 * Receives $args['player'] — a row from bbj_v2_get_season_players() (ARRAY_A).
 * Receives $args['season'] — the wp_bbj_seasons row object (for winner/runner-up/AFP comparisons).
 */

if (!defined('ABSPATH')) {
    exit;
}

$player = $args['player'];
$season = $args['season'];

$player_id   = (int) ($player['bbj_player'] ?? 0);
$first_name  = (string) ($player['first_name'] ?? '');
$last_name   = (string) ($player['last_name'] ?? '');
$nickname    = (string) ($player['official_nickname'] ?? '');
$avatar_url  = (string) ($player['profile_picture_url'] ?? '');
$display_full = trim($first_name . ' ' . $last_name);
$display_card = $nickname !== '' ? '"' . $nickname . '"' : $first_name;

// Current-state reads from the row (INTs from DB, treat as bool)
$current_hoh     = !empty($player['current_hoh']);
$current_pov     = !empty($player['current_pov']);
$current_nom     = !empty($player['current_nom']);
$current_havenot = !empty($player['current_havenot']);
$current_evicted = !empty($player['current_evicted']);
$current_misc    = !empty($player['current_misc']);
$current_jury    = !empty($player['current_jury']);
$current_safe    = !empty($player['current_safe']);

$evicted_date   = (string) ($player['bbj_evicted_date'] ?? '');
$misc_notes     = (string) ($player['misc_notes'] ?? '');
$finish_place   = $player['finish_place'] ?? '';

// Determine primary status for the left-border accent.
// Winner / runner-up / AFP live on the seasons row; the rest come from the player's flags.
$winner_id    = (int) ($season->season_winner ?? 0);
$runner_up_id = (int) ($season->runner_up ?? 0);
$afp_id       = (int) ($season->afp ?? 0);

$primary = 'stone';
if     ($player_id === $winner_id && $winner_id > 0)       $primary = 'yellow';
elseif ($player_id === $runner_up_id && $runner_up_id > 0) $primary = 'sky';
elseif ($player_id === $afp_id && $afp_id > 0)             $primary = 'pink';
elseif ($current_hoh)                                      $primary = 'emerald';
elseif ($current_pov)                                      $primary = 'yellow'; // PoV = yellow (was purple) to match bbj-app
elseif ($current_nom)                                      $primary = 'red';
elseif ($current_safe)                                     $primary = 'green';
elseif ($current_havenot)                                  $primary = 'amber';
elseif ($current_jury)                                     $primary = 'indigo';
elseif ($current_evicted)                                  $primary = 'gray';

// Explicit class names so Tailwind JIT picks them up from source.
$border_class = match ($primary) {
    'yellow'  => 'border-l-yellow-500',
    'sky'     => 'border-l-sky-500',
    'pink'    => 'border-l-pink-500',
    'emerald' => 'border-l-emerald-500',
    'red'     => 'border-l-red-500',
    'green'   => 'border-l-green-500',
    'amber'   => 'border-l-amber-700',
    'indigo'  => 'border-l-indigo-500',
    'gray'    => 'border-l-gray-500',
    default   => 'border-l-stone-500',
};

// Avatar greyscale/opacity treatment matches bbj-app PlayerStatusCard.jsx:
//   - Evicted (NOT a finalist) → grayscale 80% + opacity 75%
//   - Jury                    → grayscale 40% + opacity 85% + indigo overlay
//   - Winner / Runner-up      → never dimmed even if evicted=1
$finish_int       = is_numeric($finish_place) ? (int) $finish_place : 0;
$is_finalist      = ($finish_int === 1 || $finish_int === 2);
$avatar_img_class = '';
$avatar_overlay   = false;
if ($current_jury) {
    $avatar_img_class = 'spoilerbar-jury-img';
    $avatar_overlay   = true;
} elseif ($current_evicted && !$is_finalist) {
    $avatar_img_class = 'spoilerbar-evicted-img';
}

// Toggle-chip helper classes
$chip_base = 'cursor-pointer select-none inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold border text-stone-500 border-stone-300 bg-white dark:bg-slate-900 dark:text-slate-400 dark:border-slate-700 transition-colors';
$chip_checked_on = ' has-[:checked]:bg-primary-500 has-[:checked]:text-white has-[:checked]:border-primary-500';
?>

<div class="border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 border-l-4 <?php echo esc_attr($border_class); ?> p-3 mb-2 text-sm">

    <!-- Row 1: avatar, name, finish -->
    <div class="flex items-center gap-3 mb-2">
        <?php if ($avatar_url): ?>
            <span class="relative inline-block w-10 h-10 rounded-full overflow-hidden border border-stone-200 dark:border-slate-700 shrink-0">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_full); ?>"
                     class="w-full h-full object-cover <?php echo esc_attr($avatar_img_class); ?>">
                <?php if ($avatar_overlay): ?>
                    <span class="absolute inset-0 bg-indigo-500/25 mix-blend-multiply pointer-events-none"></span>
                <?php endif; ?>
            </span>
        <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-stone-100 dark:bg-slate-800 flex items-center justify-center text-stone-500 dark:text-slate-400 font-bold shrink-0">
                <?php echo esc_html(strtoupper(substr($first_name, 0, 1))); ?>
            </div>
        <?php endif; ?>

        <div class="min-w-0">
            <div class="font-semibold text-stone-800 dark:text-slate-200 truncate">
                <?php echo esc_html($display_full !== '' ? $display_full : '(Unnamed player)'); ?>
            </div>
            <div class="text-xs text-stone-500 dark:text-slate-500 truncate">
                Card name: <?php echo esc_html($display_card); ?>
            </div>
        </div>

        <!-- Status toggle chips moved into the header row to condense the card -->
        <div class="flex flex-wrap gap-1 text-xs flex-1 justify-center">
            <?php
            $toggles = [
                ['label' => 'HoH',  'name' => "current_hoh[{$player_id}]",     'checked' => $current_hoh],
                ['label' => 'PoV',  'name' => "current_pov[{$player_id}]",     'checked' => $current_pov],
                ['label' => 'Nom',  'name' => "current_nom[{$player_id}]",     'checked' => $current_nom],
                ['label' => 'Safe', 'name' => "current_safe[{$player_id}]",    'checked' => $current_safe],
                ['label' => 'HN',   'name' => "current_havenot[{$player_id}]", 'checked' => $current_havenot],
                ['label' => 'Misc', 'name' => "current_misc[{$player_id}]",    'checked' => $current_misc],
            ];
            foreach ($toggles as $t):
            ?>
                <label class="<?php echo esc_attr($chip_base . $chip_checked_on); ?>">
                    <input type="checkbox" name="<?php echo esc_attr($t['name']); ?>" value="1"
                           class="sr-only"
                           <?php checked($t['checked']); ?>>
                    <span><?php echo esc_html($t['label']); ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <label class="text-stone-600 dark:text-slate-400" for="bbj-fin-<?php echo $player_id; ?>">Fin#</label>
            <input type="number" min="1" max="99" id="bbj-fin-<?php echo $player_id; ?>"
                   name="finish_place[<?php echo $player_id; ?>]"
                   value="<?php echo esc_attr($finish_place !== null ? $finish_place : ''); ?>"
                   class="w-14 px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        </div>
    </div>

    <!-- Row 3: elimination controls + misc notes -->
    <div class="flex flex-wrap items-center gap-3 mb-2 text-xs">
        <span class="text-stone-500 dark:text-slate-500">Elim?</span>
        <label class="inline-flex items-center gap-1">
            <input type="radio" name="current_evicted[<?php echo $player_id; ?>]" value="0"
                   <?php checked(!$current_evicted); ?>>
            <span>N</span>
        </label>
        <label class="inline-flex items-center gap-1">
            <input type="radio" name="current_evicted[<?php echo $player_id; ?>]" value="1"
                   <?php checked($current_evicted); ?>>
            <span>Y</span>
        </label>

        <label class="inline-flex items-center gap-1">
            <input type="checkbox" name="current_jury[<?php echo $player_id; ?>]" value="1"
                   <?php checked($current_jury); ?>>
            <span>Jury</span>
        </label>

        <label class="inline-flex items-center gap-1">
            <span class="text-stone-500 dark:text-slate-500">Evicted:</span>
            <input type="date" name="evicted_date[<?php echo $player_id; ?>]"
                   value="<?php echo esc_attr($evicted_date); ?>"
                   class="px-2 py-0.5 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        </label>

        <label class="inline-flex items-center gap-1 flex-1 min-w-[140px]">
            <span class="text-stone-500 dark:text-slate-500">Misc:</span>
            <input type="text" name="misc_notes[<?php echo $player_id; ?>]"
                   value="<?php echo esc_attr($misc_notes); ?>"
                   placeholder="Custom status label"
                   class="w-full px-2 py-0.5 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        </label>
    </div>

</div>

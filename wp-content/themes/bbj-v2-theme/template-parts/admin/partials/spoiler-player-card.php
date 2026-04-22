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
$finish_place   = $player['bbj_finish_place'] ?? '';

// Stat counts
$hoh_count      = (int) ($player['bbj_total_hoh'] ?? 0);
$veto_count     = (int) ($player['bbj_total_pov'] ?? 0);
$nom_count      = (int) ($player['bbj_total_nom'] ?? 0);
$veto_played    = (int) ($player['bbj_veto_played'] ?? 0);
$misc_count     = (int) ($player['bbj_total_misc'] ?? 0);
$saved_count    = (int) ($player['bbj_total_saved'] ?? 0);
$havenot_count  = (int) ($player['bbj_total_havenot'] ?? 0);
$votes_received = (int) ($player['bbj_votes_received'] ?? 0);

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
elseif ($current_pov)                                      $primary = 'purple';
elseif ($current_nom)                                      $primary = 'red';
elseif ($current_safe)                                     $primary = 'green';
elseif ($current_havenot)                                  $primary = 'slate';
elseif ($current_jury)                                     $primary = 'indigo';
elseif ($current_evicted)                                  $primary = 'gray';

// Explicit class names so Tailwind JIT picks them up from source.
$border_class = match ($primary) {
    'yellow'  => 'border-l-yellow-500',
    'sky'     => 'border-l-sky-500',
    'pink'    => 'border-l-pink-500',
    'emerald' => 'border-l-emerald-500',
    'purple'  => 'border-l-purple-500',
    'red'     => 'border-l-red-500',
    'green'   => 'border-l-green-500',
    'slate'   => 'border-l-slate-500',
    'indigo'  => 'border-l-indigo-500',
    'gray'    => 'border-l-gray-500',
    default   => 'border-l-stone-500',
};

// Toggle-chip helper classes
$chip_base = 'cursor-pointer select-none inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold border text-stone-500 border-stone-300 bg-white dark:bg-slate-900 dark:text-slate-400 dark:border-slate-700 transition-colors';
$chip_checked_on = ' has-[:checked]:bg-primary-500 has-[:checked]:text-white has-[:checked]:border-primary-500';
?>

<div class="border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 border-l-4 <?php echo esc_attr($border_class); ?> p-3 mb-2 text-sm">

    <!-- Row 1: avatar, name, finish -->
    <div class="flex items-center gap-3 mb-2">
        <?php if ($avatar_url): ?>
            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_full); ?>"
                 class="w-10 h-10 object-cover rounded-full border border-stone-200 dark:border-slate-700">
        <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-stone-100 dark:bg-slate-800 flex items-center justify-center text-stone-500 dark:text-slate-400 font-bold">
                <?php echo esc_html(strtoupper(substr($first_name, 0, 1))); ?>
            </div>
        <?php endif; ?>

        <div class="flex-1">
            <div class="font-semibold text-stone-800 dark:text-slate-200">
                <?php echo esc_html($display_full !== '' ? $display_full : '(Unnamed player)'); ?>
            </div>
            <div class="text-xs text-stone-500 dark:text-slate-500">
                Card name: <?php echo esc_html($display_card); ?>
            </div>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <label class="text-stone-600 dark:text-slate-400" for="bbj-fin-<?php echo $player_id; ?>">Fin#</label>
            <input type="number" min="1" max="99" id="bbj-fin-<?php echo $player_id; ?>"
                   name="finish_place[<?php echo $player_id; ?>]"
                   value="<?php echo esc_attr($finish_place !== null ? $finish_place : ''); ?>"
                   class="w-14 px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        </div>
    </div>

    <!-- Row 2: 8 stat counts -->
    <div class="grid grid-cols-8 gap-2 mb-2 text-xs">
        <?php
        $stats = [
            ['label' => 'HoH',   'name' => "hoh_count[{$player_id}]",      'value' => $hoh_count],
            ['label' => 'PoV',   'name' => "veto_count[{$player_id}]",     'value' => $veto_count],
            ['label' => 'Nom',   'name' => "nom_count[{$player_id}]",      'value' => $nom_count],
            ['label' => 'Veto',  'name' => "veto_played[{$player_id}]",    'value' => $veto_played],
            ['label' => 'Misc',  'name' => "misc_count[{$player_id}]",     'value' => $misc_count],
            ['label' => 'Saved', 'name' => "saved_count[{$player_id}]",    'value' => $saved_count],
            ['label' => 'H/N',   'name' => "havenot_count[{$player_id}]",  'value' => $havenot_count],
            ['label' => 'Votes', 'name' => "votes_received[{$player_id}]", 'value' => $votes_received],
        ];
        foreach ($stats as $s):
        ?>
            <label class="flex flex-col items-center">
                <span class="text-stone-500 dark:text-slate-500"><?php echo esc_html($s['label']); ?></span>
                <input type="number" min="0" name="<?php echo esc_attr($s['name']); ?>"
                       value="<?php echo esc_attr((string) $s['value']); ?>"
                       class="w-full px-2 py-0.5 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-center">
            </label>
        <?php endforeach; ?>
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

    <!-- Row 4: status toggle chips -->
    <div class="flex flex-wrap gap-1 text-xs">
        <span class="text-stone-500 dark:text-slate-500 mr-1">Status:</span>
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

</div>

<?php

namespace BigBrotherJunkies\Data\Weekly;

/**
 * Admin write path for the weekly tables (bbj_weeks / bbj_weeks_players /
 * bbj_week_comps). The pure planner mirrors WeeklyImporter's row semantics:
 * a veto-saved nominee keeps nom=1, voted_for = who they voted to EVICT,
 * saved_by_player_id = the veto winner.
 */
class WeeklyAdmin
{
    /**
     * Pure: convert an admin week-editor payload into normalized row arrays.
     *
     * Payload (all player refs are WP post IDs):
     *   active: int[]                  players in the house this week
     *   hoh: ?int, pov: ?int
     *   noms: int[]                    ALL nominees this week (incl. replacement)
     *   veto_used_on: ?int             nominee saved by the veto (must be in noms)
     *   evicted: int[]                 evictees (supports double evictions/expulsions)
     *   votes: array<int|string,int>   voter_id => voted-to-evict id
     *   misc_comps: array<array{player_id:int, comp_type_id:int, notes?:string}>
     *
     * @return array{players: array<int,array>, comps: array<int,array>, errors: string[]}
     */
    public static function planWeekRows(array $payload, array $rosterIds): array
    {
        $roster = array_flip(array_map('intval', $rosterIds));
        $inRoster = static fn ($id): bool => isset($roster[(int) $id]);
        $errors = [];

        $hoh        = (int) ($payload['hoh'] ?? 0) ?: null;
        $pov        = (int) ($payload['pov'] ?? 0) ?: null;
        $vetoUsedOn = (int) ($payload['veto_used_on'] ?? 0) ?: null;
        $noms    = array_values(array_unique(array_map('intval', (array) ($payload['noms'] ?? []))));
        $evicted = array_values(array_unique(array_map('intval', (array) ($payload['evicted'] ?? []))));
        $active  = array_values(array_unique(array_map('intval', (array) ($payload['active'] ?? []))));

        $votes = [];
        foreach ((array) ($payload['votes'] ?? []) as $voter => $for) {
            if ((int) $voter && (int) $for) {
                $votes[(int) $voter] = (int) $for;
            }
        }

        foreach ([['hoh', $hoh], ['pov', $pov], ['veto_used_on', $vetoUsedOn]] as [$label, $id]) {
            if ($id !== null && !$inRoster($id)) {
                $errors[] = "{$label}: player {$id} is not on this season's roster";
            }
        }
        foreach (['noms' => $noms, 'evicted' => $evicted, 'active' => $active] as $label => $ids) {
            foreach ($ids as $id) {
                if (!$inRoster($id)) {
                    $errors[] = "{$label}: player {$id} is not on this season's roster";
                }
            }
        }
        foreach ($votes as $voter => $for) {
            if (!$inRoster($voter)) $errors[] = "votes: voter {$voter} is not on this season's roster";
            if (!$inRoster($for))   $errors[] = "votes: target {$for} is not on this season's roster";
        }

        if ($vetoUsedOn !== null && $pov === null) {
            $errors[] = 'veto_used_on requires a pov winner';
        }
        if ($vetoUsedOn !== null && !in_array($vetoUsedOn, $noms, true)) {
            $errors[] = 'veto_used_on must be one of the nominees';
        }

        $comps = [];
        if ($hoh !== null) $comps[] = ['player_id' => $hoh, 'slug' => 'hoh', 'comp_type_id' => null, 'notes' => null];
        if ($pov !== null) $comps[] = ['player_id' => $pov, 'slug' => 'pov', 'comp_type_id' => null, 'notes' => null];
        foreach ((array) ($payload['misc_comps'] ?? []) as $mc) {
            $pid  = (int) ($mc['player_id'] ?? 0);
            $ctid = (int) ($mc['comp_type_id'] ?? 0);
            if (!$pid || !$ctid) {
                continue; // blank UI row
            }
            if (!$inRoster($pid)) {
                $errors[] = "misc_comps: player {$pid} is not on this season's roster";
                continue;
            }
            $notes = trim((string) ($mc['notes'] ?? ''));
            $comps[] = [
                'player_id' => $pid,
                'slug' => null,
                'comp_type_id' => $ctid,
                'notes' => $notes === '' ? null : substr($notes, 0, 140),
            ];
        }

        if ($errors) {
            return ['players' => [], 'comps' => [], 'errors' => array_values(array_unique($errors))];
        }

        // Row set: every active player, plus anyone referenced (safety net for a
        // short active list — an evictee must always land a row).
        $rowIds = $active;
        $referenced = array_merge([$hoh, $pov, $vetoUsedOn], $noms, $evicted, array_keys($votes));
        foreach ($referenced as $id) {
            if ($id !== null && $id !== 0 && !in_array($id, $rowIds, true)) {
                $rowIds[] = $id;
            }
        }

        $players = [];
        foreach ($rowIds as $pid) {
            $players[] = [
                'player_id'   => $pid,
                'nom'         => in_array($pid, $noms, true) ? 1 : 0,
                'evicted'     => in_array($pid, $evicted, true) ? 1 : 0,
                'veto_played' => 0,
                'voted_for'   => $votes[$pid] ?? 0,
                'saved_by'    => ($vetoUsedOn !== null && $pid === $vetoUsedOn) ? $pov : null,
                'active'      => 1,
            ];
        }

        return ['players' => $players, 'comps' => $comps, 'errors' => []];
    }
}

<?php

namespace BigBrotherJunkies\Data\Tests\Weekly;

use BigBrotherJunkies\Data\Weekly\WeeklyAdmin;
use PHPUnit\Framework\TestCase;

class WeeklyAdminPlannerTest extends TestCase
{
    private const ROSTER = [101, 102, 103, 104, 105, 106];

    private function basePayload(): array
    {
        return [
            'active' => [101, 102, 103, 104, 105, 106],
            'hoh' => 101,
            'pov' => 102,
            'noms' => [103, 104, 105],          // 104 saved by veto, 105 = replacement
            'veto_used_on' => 104,
            'evicted' => [103],
            'havenot' => [105, 106],
            'veto_played' => [101, 103, 104, 105, 106], // hoh + noms + drawn
            'hoh_played' => [102, 103, 104, 105, 106],  // everyone but the outgoing HoH
            'votes' => ['102' => 103, '106' => 103], // JSON object keys arrive as strings
            'misc_comps' => [
                ['player_id' => 106, 'comp_type_id' => 9, 'notes' => 'AI Arena'],
            ],
        ];
    }

    private function playerRow(array $result, int $pid): array
    {
        foreach ($result['players'] as $row) {
            if ($row['player_id'] === $pid) {
                return $row;
            }
        }
        $this->fail("No players row for {$pid}");
    }

    public function test_full_week_produces_correct_rows(): void
    {
        $result = WeeklyAdmin::planWeekRows($this->basePayload(), self::ROSTER);

        $this->assertSame([], $result['errors']);
        $this->assertCount(6, $result['players']); // one row per active player

        $saved = $this->playerRow($result, 104);
        $this->assertSame(1, $saved['nom']);          // veto-saved keeps nom=1
        $this->assertSame(102, $saved['saved_by']);   // saved by the pov winner
        $this->assertSame(0, $saved['evicted']);

        $evicted = $this->playerRow($result, 103);
        $this->assertSame(1, $evicted['evicted']);
        $this->assertSame(1, $evicted['nom']);

        $voter = $this->playerRow($result, 106);
        $this->assertSame(103, $voter['voted_for']);
        $this->assertSame(1, $voter['active']);
        $this->assertSame(1, $voter['havenot']);
        $this->assertSame(1, $voter['veto_played']);
        $this->assertNull($voter['saved_by']);

        $hohRow = $this->playerRow($result, 101);
        $this->assertSame(0, $hohRow['voted_for']); // HoH cast no vote
        $this->assertSame(0, $hohRow['havenot']);
        $this->assertSame(1, $hohRow['veto_played']);

        $this->assertSame(0, $this->playerRow($result, 102)['veto_played']); // pov WINNER not in played list here — planner trusts payload

        $this->assertSame(1, $this->playerRow($result, 102)['hoh_played']);
        $this->assertSame(0, $this->playerRow($result, 101)['hoh_played']); // outgoing HoH sat out

        $comps = $result['comps'];
        $this->assertContains(['player_id' => 101, 'slug' => 'hoh', 'comp_type_id' => null, 'notes' => null], $comps);
        $this->assertContains(['player_id' => 102, 'slug' => 'pov', 'comp_type_id' => null, 'notes' => null], $comps);
        $this->assertContains(['player_id' => 106, 'slug' => null, 'comp_type_id' => 9, 'notes' => 'AI Arena'], $comps);
    }

    public function test_havenot_off_roster_is_rejected(): void
    {
        $payload = $this->basePayload();
        $payload['havenot'] = [999];
        $result = WeeklyAdmin::planWeekRows($payload, self::ROSTER);
        $this->assertNotSame([], $result['errors']);
        $this->assertStringContainsString('havenot', implode(' ', $result['errors']));
    }

    public function test_veto_played_off_roster_is_rejected(): void
    {
        $payload = $this->basePayload();
        $payload['veto_played'] = [999];
        $result = WeeklyAdmin::planWeekRows($payload, self::ROSTER);
        $this->assertNotSame([], $result['errors']);
        $this->assertStringContainsString('veto_played', implode(' ', $result['errors']));
    }

    public function test_hoh_played_off_roster_is_rejected(): void
    {
        $payload = $this->basePayload();
        $payload['hoh_played'] = [999];
        $result = WeeklyAdmin::planWeekRows($payload, self::ROSTER);
        $this->assertNotSame([], $result['errors']);
        $this->assertStringContainsString('hoh_played', implode(' ', $result['errors']));
    }

    public function test_rejects_players_not_on_roster(): void
    {
        $payload = $this->basePayload();
        $payload['hoh'] = 999;
        $result = WeeklyAdmin::planWeekRows($payload, self::ROSTER);
        $this->assertNotSame([], $result['errors']);
        $this->assertSame([], $result['players']);
        $this->assertStringContainsString('999', implode(' ', $result['errors']));
    }

    public function test_veto_used_on_requires_pov_winner_and_nominee(): void
    {
        $noPov = $this->basePayload();
        $noPov['pov'] = null;
        $this->assertNotSame([], WeeklyAdmin::planWeekRows($noPov, self::ROSTER)['errors']);

        $notNominee = $this->basePayload();
        $notNominee['veto_used_on'] = 106; // not in noms
        $this->assertNotSame([], WeeklyAdmin::planWeekRows($notNominee, self::ROSTER)['errors']);
    }

    public function test_supports_double_eviction_and_no_veto(): void
    {
        $payload = [
            'active' => [101, 102, 103, 104],
            'hoh' => 101,
            'pov' => null,
            'noms' => [103, 104],
            'veto_used_on' => null,
            'evicted' => [103, 104],
            'votes' => [],
            'misc_comps' => [],
        ];
        $result = WeeklyAdmin::planWeekRows($payload, self::ROSTER);
        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $this->playerRow($result, 103)['evicted']);
        $this->assertSame(1, $this->playerRow($result, 104)['evicted']);
        $this->assertCount(1, $result['comps']); // hoh only
    }

    public function test_empty_week_is_valid_and_empty(): void
    {
        $result = WeeklyAdmin::planWeekRows(['active' => [101, 102]], self::ROSTER);
        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['players']);
        $this->assertSame([], $result['comps']);
    }

    public function test_referenced_player_missing_from_active_still_gets_row(): void
    {
        $payload = $this->basePayload();
        $payload['active'] = [101, 102]; // UI sent short list; evicted 103 etc. still referenced
        $result = WeeklyAdmin::planWeekRows($payload, self::ROSTER);
        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $this->playerRow($result, 103)['evicted']);
    }

    public function test_misc_comp_off_roster_is_error_and_zero_ids_skipped(): void
    {
        $payload = $this->basePayload();
        $payload['misc_comps'] = [
            ['player_id' => 999, 'comp_type_id' => 9],
            ['player_id' => 0, 'comp_type_id' => 9],   // blank UI row — silently skipped
            ['player_id' => 105, 'comp_type_id' => 0], // blank UI row — silently skipped
        ];
        $result = WeeklyAdmin::planWeekRows($payload, self::ROSTER);
        $this->assertNotSame([], $result['errors']);
    }
}

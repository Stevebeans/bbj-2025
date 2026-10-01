<?php

namespace BigBrotherJunkies\Tests\Conduct;

use BigBrotherJunkies\Data\Conduct\ConductLogic;
use PHPUnit\Framework\TestCase;

class ConductLogicTest extends TestCase
{
    public function test_scope_covers(): void
    {
        $this->assertTrue(ConductLogic::scopeCovers('both', 'commenting'));
        $this->assertTrue(ConductLogic::scopeCovers('both', 'dm_init'));
        $this->assertTrue(ConductLogic::scopeCovers('commenting', 'commenting'));
        $this->assertFalse(ConductLogic::scopeCovers('commenting', 'dm_init'));
        $this->assertFalse(ConductLogic::scopeCovers('dm_init', 'commenting'));
    }

    public function test_is_active_row_timeout_unexpired(): void
    {
        $row = ['action' => 'timeout', 'revoked_at' => null, 'expires_at' => '2026-08-23 00:00:00'];
        $this->assertTrue(ConductLogic::isActiveRow($row, '2026-08-22 12:00:00'));
        $this->assertFalse(ConductLogic::isActiveRow($row, '2026-08-23 00:00:01'));
    }

    public function test_is_active_row_revoked_is_inactive(): void
    {
        $row = ['action' => 'timeout', 'revoked_at' => '2026-08-22 01:00:00', 'expires_at' => '2026-08-23 00:00:00'];
        $this->assertFalse(ConductLogic::isActiveRow($row, '2026-08-22 12:00:00'));
    }

    public function test_is_active_row_permanent_ban(): void
    {
        $row = ['action' => 'ban', 'revoked_at' => null, 'expires_at' => null];
        $this->assertTrue(ConductLogic::isActiveRow($row, '2030-01-01 00:00:00'));
    }

    public function test_is_active_row_warning_never_active(): void
    {
        $row = ['action' => 'warning', 'revoked_at' => null, 'expires_at' => null];
        $this->assertFalse(ConductLogic::isActiveRow($row, '2026-08-22 12:00:00'));
    }

    public function test_valid_action(): void
    {
        $this->assertTrue(ConductLogic::validAction('warning', '', '')['ok']);
        $this->assertTrue(ConductLogic::validAction('timeout', 'dm_init', '72h')['ok']);
        $this->assertTrue(ConductLogic::validAction('ban', '', '')['ok']);
        $this->assertTrue(ConductLogic::validAction('lift', '', '')['ok']);
        $this->assertSame('bad_action', ConductLogic::validAction('nuke', '', '')['reason']);
        $this->assertSame('bad_scope', ConductLogic::validAction('timeout', 'everything', '24h')['reason']);
        $this->assertSame('bad_duration', ConductLogic::validAction('timeout', 'both', '5m')['reason']);
    }

    public function test_durations_map(): void
    {
        $this->assertSame(['24h' => 24, '72h' => 72, '7d' => 168], ConductLogic::DURATIONS);
    }

    public function test_season_is_a_valid_timeout_duration(): void
    {
        $this->assertTrue(ConductLogic::validAction('timeout', 'both', 'season')['ok']);
        $this->assertSame('bad_duration', ConductLogic::validAction('timeout', 'both', 'offseason')['reason']);
    }

    public function test_expires_at_for_hour_durations_is_relative_to_now(): void
    {
        $now = gmmktime(12, 0, 0, 9, 18, 2026);
        $this->assertSame('2026-09-19 12:00:00', ConductLogic::expiresAtFor('24h', $now));
        $this->assertSame('2026-09-25 12:00:00', ConductLogic::expiresAtFor('7d', $now));
    }

    public function test_expires_at_for_season_is_end_of_finale_day_eastern(): void
    {
        // Oct 5, 2026 23:59:59 America/New_York (EDT, UTC-4) = Oct 6 03:59:59 UTC,
        // regardless of when the timeout is applied.
        $this->assertSame('2026-10-06 03:59:59', ConductLogic::expiresAtFor('season', gmmktime(12, 0, 0, 9, 18, 2026)));
        $this->assertSame('2026-10-06 03:59:59', ConductLogic::expiresAtFor('season', gmmktime(0, 0, 0, 10, 4, 2026)));
        $this->assertSame(ConductLogic::SEASON_END_UTC, ConductLogic::expiresAtFor('season', time()));
    }

    public function test_timeout_body_reads_naturally_for_each_duration(): void
    {
        $season = ConductLogic::timeoutBody('both', 'season', '');
        $this->assertStringContainsString('until the end of the season (October 5)', $season);
        $this->assertStringNotContainsString('for season', $season);
        $this->assertStringContainsString('for 72h', ConductLogic::timeoutBody('both', '72h', ''));
    }

    public function test_bodies_include_note_and_avoid_emdash(): void
    {
        foreach ([
            ConductLogic::warningBody('Watch the name-calling.'),
            ConductLogic::timeoutBody('both', '72h', 'Repeated abuse reports.'),
            ConductLogic::banBody(''),
        ] as $body) {
            $this->assertStringNotContainsString("\u{2014}", $body);
            $this->assertNotSame('', trim($body));
        }
        $this->assertStringContainsString('Watch the name-calling.', ConductLogic::warningBody('Watch the name-calling.'));
        $this->assertStringNotContainsString('Moderator note', ConductLogic::warningBody(''));
        $this->assertStringContainsString('72h', ConductLogic::timeoutBody('both', '72h', ''));
    }

    public function test_gate_refusal_copy(): void
    {
        $this->assertStringContainsString('suspended', ConductLogic::gateRefusal('commenting', '2026-08-23 00:00:00'));
        $this->assertStringContainsString('not able', ConductLogic::gateRefusal('commenting', null));
    }
}

<?php

namespace BigBrotherJunkies\Data\Conduct;

/**
 * Pure decisions + member-facing copy for the conduct system. No WordPress
 * calls so everything is unit-testable (tests/Conduct/ConductLogicTest.php).
 * All datetimes are UTC MySQL strings; string comparison is safe because the
 * format sorts lexicographically.
 */
class ConductLogic
{
    public const ACTIONS   = ['warning', 'timeout', 'ban', 'lift'];
    public const SCOPES    = ['commenting', 'dm_init', 'both'];
    public const DURATIONS = ['24h' => 24, '72h' => 72, '7d' => 168];
    public const REASONS   = ['spam', 'abuse', 'off_topic', 'misinformation', 'other'];

    /**
     * "Sit out the rest of the season": a fixed-date timeout that lifts at the
     * end of finale day (Oct 5, 2026, America/New_York = EDT) no matter when
     * it is applied. Bump this each season.
     */
    public const SEASON         = 'season';
    public const SEASON_END_UTC = '2026-10-06 03:59:59';

    public static function expiresAtFor(string $duration, int $nowTs): string
    {
        if ($duration === self::SEASON) {
            return self::SEASON_END_UTC;
        }
        return gmdate('Y-m-d H:i:s', $nowTs + self::DURATIONS[$duration] * 3600);
    }

    public static function scopeCovers(string $scope, string $gate): bool
    {
        return $scope === 'both' || $scope === $gate;
    }

    public static function isActiveRow(array $row, string $nowUtc): bool
    {
        if (!in_array($row['action'] ?? '', ['timeout', 'ban'], true)) {
            return false;
        }
        if (!empty($row['revoked_at'])) {
            return false;
        }
        if (empty($row['expires_at'])) {
            return true; // permanent (ban)
        }
        return $row['expires_at'] > $nowUtc;
    }

    /** @return array{ok:bool, reason?:string} */
    public static function validAction(string $action, string $scope, string $duration): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            return ['ok' => false, 'reason' => 'bad_action'];
        }
        if ($action === 'timeout') {
            if (!in_array($scope, self::SCOPES, true)) {
                return ['ok' => false, 'reason' => 'bad_scope'];
            }
            if (!isset(self::DURATIONS[$duration]) && $duration !== self::SEASON) {
                return ['ok' => false, 'reason' => 'bad_duration'];
            }
        }
        return ['ok' => true];
    }

    public static function warningBody(string $note): string
    {
        $body = "This is a formal warning from the Big Brother Junkies moderation team. "
            . "Behavior reported by other members crossed our community guidelines. "
            . "Please keep it respectful, in comments and in private messages. "
            . "Further reports can lead to a temporary suspension or a ban.";
        return self::withNote($body, $note);
    }

    public static function timeoutBody(string $scope, string $durationLabel, string $note): string
    {
        $what = [
            'commenting' => 'posting comments',
            'dm_init'    => 'starting new private message conversations',
            'both'       => 'posting comments and starting new private message conversations',
        ][$scope] ?? 'posting';
        $howLong = $durationLabel === self::SEASON
            ? 'until the end of the season (October 5)'
            : "for {$durationLabel}";
        $body = "Following reports from other members, your access to {$what} has been "
            . "suspended {$howLong}. The suspension lifts automatically. "
            . "Continued behavior like this after it lifts can lead to a longer suspension or a ban.";
        return self::withNote($body, $note);
    }

    public static function banBody(string $note): string
    {
        $body = "Following repeated reports from other members, your posting privileges on "
            . "Big Brother Junkies have been removed. If you believe this is a mistake, "
            . "you can reach the team through the site contact form.";
        return self::withNote($body, $note);
    }

    /** Member-facing 403 copy at an enforcement gate. Null expiry = permanent. */
    public static function gateRefusal(string $gate, ?string $expiresAtUtc): string
    {
        $what = $gate === 'dm_init' ? 'start new conversations' : 'post comments';
        if ($expiresAtUtc === null) {
            return "You are not able to {$what}.";
        }
        return "Your account is temporarily suspended from being able to {$what}. "
            . "The suspension lifts automatically.";
    }

    private static function withNote(string $body, string $note): string
    {
        if (trim($note) !== '') {
            $body .= "\n\nModerator note: " . trim($note);
        }
        return $body;
    }
}

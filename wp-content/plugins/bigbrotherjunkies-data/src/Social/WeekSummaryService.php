<?php

namespace BigBrotherJunkies\Data\Social;

use BigBrotherJunkies\Data\Weekly\WeeklySchema;

/**
 * Drafts the 2-4 sentence week summary shown on a season page, from the
 * weekly editor's CURRENT form payload (unsaved edits included) plus any
 * stored Social Intel digests / blog recaps that overlap the week's dates.
 * Reuses the DraftService plumbing (AnthropicClient + Social settings), so
 * there is no new configuration. Returns text only; nothing is persisted —
 * the draft lands in the editor's textarea and ships with the normal save.
 *
 * Old seasons have no social rows for their windows, so the same endpoint
 * doubles as a facts-only backfill generator for BB2-27.
 */
class WeekSummaryService
{
    /** Rows of flavor context; a week of digests can be large. */
    private const MAX_FLAVOR_ROWS  = 10;
    private const MAX_FLAVOR_CHARS = 4000;

    public const SUMMARY_PROMPT = <<<'PROMPT'
You write the short summary shown next to one week of a Big Brother season on
bigbrotherjunkies.com. You receive that week's confirmed results as structured facts,
and sometimes feed recaps from the same week for background.

Write 2 to 4 sentences in a casual, knowledgeable fan voice with light snark where the
material earns it. Tell the week's arc: who won HoH and who they targeted, how the veto
changed the plan, and who went home and how the vote landed. The structured facts are
the authority on outcomes; never contradict them. Use the feed recaps only to explain
WHY things happened (a blowup, a flipped vote, someone torching their own game), never
to add events or results the facts don't show. When a fact is missing (no eviction or
votes recorded yet), leave it out rather than guessing. Third person, past tense.
No em-dashes, no hashtags, no emojis, no handles. Never invent events or quotes.
Respond with the summary text only.
PROMPT;

    /**
     * @param array<string,mixed> $payload the weekly editor's formToPayload() body
     * @return array{success:true,summary:string}|array{success:false,message:string}
     */
    public static function generate(int $weekId, array $payload): array
    {
        global $wpdb;

        $weeksTable = WeeklySchema::table(WeeklySchema::TABLE_WEEKS);
        $week       = $wpdb->get_row($wpdb->prepare(
            "SELECT id, season_id, week_num, start_date, end_date FROM {$weeksTable} WHERE id = %d",
            $weekId
        ), ARRAY_A);
        if (!$week) {
            return ['success' => false, 'message' => 'Week not found.'];
        }

        // Editor dates (possibly unsaved) win over the stored row's.
        $startDate = (string) ($payload['start_date'] ?? '') ?: (string) ($week['start_date'] ?? '');
        $endDate   = (string) ($payload['end_date'] ?? '') ?: (string) ($week['end_date'] ?? '');

        $facts = self::buildFacts(
            get_the_title((int) $week['season_id']) ?: 'Big Brother',
            (int) $week['week_num'],
            $startDate,
            $endDate,
            $payload,
            self::nameMap($payload),
            self::compTypeNames($payload)
        );

        $flavor = self::flavorLines($startDate, $endDate);

        $settings = SocialStore::settings();
        $client   = new AnthropicClient();
        $result   = $client->complete(
            (string) apply_filters('bbjd_week_summary_prompt', self::SUMMARY_PROMPT),
            $facts . ($flavor === '' ? '' : "\n\n" . $flavor),
            (string) $settings['model'],
            (string) $settings['anthropic_api_key']
        );

        if (isset($result['error'])) {
            return ['success' => false, 'message' => (string) $result['error']];
        }

        $text = trim((string) $result['text']);
        if ($text === '') {
            return ['success' => false, 'message' => 'Model returned an empty summary.'];
        }

        return ['success' => true, 'summary' => $text];
    }

    /**
     * Pure facts-block builder (unit-verifiable): renders the payload's IDs to
     * names and states only what the editor actually contains — absent facts
     * produce no line at all, per the prompt's "leave it out" contract.
     *
     * @param array<string,mixed> $payload
     * @param array<int,string>   $names         player id => display name
     * @param array<int,string>   $compTypeNames comp_type id => display name
     */
    public static function buildFacts(
        string $seasonName,
        int $weekNum,
        string $startDate,
        string $endDate,
        array $payload,
        array $names,
        array $compTypeNames
    ): string {
        $name = static fn ($id) => $names[(int) $id] ?? ('Player #' . (int) $id);
        $list = static fn (array $ids) => implode(', ', array_map($name, $ids));

        $dates = trim($startDate . ($endDate !== '' ? ' to ' . $endDate : ''));
        $lines = [sprintf(
            'Season: %s, Week %d%s.',
            $seasonName,
            $weekNum,
            $dates !== '' ? ' (' . $dates . ')' : ''
        )];

        $hoh = (int) ($payload['hoh'] ?? 0);
        if ($hoh) {
            $lines[] = 'HoH winner: ' . $name($hoh);
        }

        $noms = array_map('intval', (array) ($payload['noms'] ?? []));
        if ($noms) {
            $lines[] = 'Nominees (everyone who sat on the block, incl. any replacement): ' . $list($noms);
        }

        $pov = (int) ($payload['pov'] ?? 0);
        if ($pov) {
            $lines[] = 'Veto winner: ' . $name($pov);
        }

        $savedId = (int) ($payload['veto_used_on'] ?? 0);
        if ($savedId) {
            $lines[] = 'Veto used to save: ' . $name($savedId);
        } elseif ($pov && $noms) {
            $lines[] = 'Veto not used.';
        }

        // "Eviction safe" comp wins (Block Buster, AI Arena…) pulled the
        // nominee off the block at the live show — mirrors the editor's flag.
        $compSaved = [];
        foreach ((array) ($payload['misc_comps'] ?? []) as $mc) {
            $winner = (int) ($mc['player_id'] ?? 0);
            if ($winner && !empty($mc['saved']) && in_array($winner, $noms, true)) {
                $compSaved[] = $winner;
            }
        }

        $finalNoms = array_values(array_filter(
            $noms,
            static fn ($id) => $id !== $savedId && !in_array($id, $compSaved, true)
        ));
        if ($finalNoms && ($savedId || $compSaved)) {
            $lines[] = 'Final nominees at the vote: ' . $list($finalNoms);
        }

        $evicted = array_map('intval', (array) ($payload['evicted'] ?? []));
        $tally   = self::voteTally((array) ($payload['votes'] ?? []));
        if ($evicted) {
            $lines[] = 'Evicted: ' . $list($evicted) . self::tallyText($tally, $names);
        }

        $havenots = array_map('intval', (array) ($payload['havenot'] ?? []));
        if ($havenots) {
            $lines[] = 'Have-Nots: ' . $list($havenots);
        }

        foreach ((array) ($payload['misc_comps'] ?? []) as $mc) {
            $ctName = $compTypeNames[(int) ($mc['comp_type_id'] ?? 0)] ?? '';
            $winner = (int) ($mc['player_id'] ?? 0);
            if ($ctName === '' || !$winner) {
                continue;
            }
            $notes   = trim((string) ($mc['notes'] ?? ''));
            $lines[] = sprintf(
                '%s won by %s%s%s',
                $ctName,
                $name($winner),
                in_array($winner, $compSaved, true) ? ', saving them from the block before the vote' : '',
                $notes !== '' ? ' (' . $notes . ')' : ''
            );
        }

        return "=== CONFIRMED WEEK FACTS (authoritative) ===\n" . implode("\n", $lines);
    }

    /** @param array<int|string,int|string> $votes voter id => voted-to-evict id */
    private static function voteTally(array $votes): array
    {
        $tally = [];
        foreach ($votes as $votedFor) {
            $votedFor = (int) $votedFor;
            if ($votedFor > 0) {
                $tally[$votedFor] = ($tally[$votedFor] ?? 0) + 1;
            }
        }
        arsort($tally);
        return $tally;
    }

    /** @param array<int,int> $tally @param array<int,string> $names */
    private static function tallyText(array $tally, array $names): string
    {
        if (!$tally) {
            return '';
        }
        $parts = [];
        foreach ($tally as $id => $count) {
            $parts[] = sprintf('%d for %s', $count, $names[$id] ?? ('Player #' . $id));
        }
        return ' (vote: ' . implode(', ', $parts) . ')';
    }

    /**
     * Stored digests + blog recaps overlapping the week's PT dates, as one
     * flavor block. Empty string when dates are missing or nothing overlaps
     * (facts-only generation — the normal case for pre-BB28 backfill).
     */
    private static function flavorLines(string $startDate, string $endDate): string
    {
        if ($startDate === '' || $endDate === '') {
            return '';
        }
        try {
            $pt   = new \DateTimeZone('America/Los_Angeles');
            $utc  = new \DateTimeZone('UTC');
            $from = (new \DateTimeImmutable($startDate . ' 00:00:00', $pt))->setTimezone($utc);
            $to   = (new \DateTimeImmutable($endDate . ' 00:00:00', $pt))->modify('+1 day')->setTimezone($utc);
        } catch (\Exception $e) {
            return '';
        }

        $rows = SocialStore::summariesInWindow(
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
            ['digest', 'blog'],
            self::MAX_FLAVOR_ROWS
        );
        if (!$rows) {
            return '';
        }

        $lines = ['=== FEED RECAPS FROM THIS WEEK (background color only; facts above win) ==='];
        foreach ($rows as $row) {
            $content = html_entity_decode((string) ($row['content'] ?? ''), ENT_QUOTES, 'UTF-8');
            if (strlen($content) > self::MAX_FLAVOR_CHARS) {
                $content = substr($content, 0, self::MAX_FLAVOR_CHARS) . ' [...]';
            }
            $lines[] = sprintf(
                '--- %s (%s PT) ---',
                (string) $row['kind'] === 'blog' ? 'Blog recap' : 'Feed digest',
                BeanBotLogic::toHouseTime((string) $row['window_from'])
            );
            $lines[] = $content;
        }
        return implode("\n", $lines);
    }

    /**
     * Every player ID the payload references => display name, resolved once.
     *
     * @param array<string,mixed> $payload
     * @return array<int,string>
     */
    private static function nameMap(array $payload): array
    {
        $ids = array_merge(
            [(int) ($payload['hoh'] ?? 0), (int) ($payload['pov'] ?? 0), (int) ($payload['veto_used_on'] ?? 0)],
            array_map('intval', (array) ($payload['noms'] ?? [])),
            array_map('intval', (array) ($payload['evicted'] ?? [])),
            array_map('intval', (array) ($payload['havenot'] ?? [])),
            array_map('intval', array_values((array) ($payload['votes'] ?? []))),
            array_map(static fn ($mc) => (int) ($mc['player_id'] ?? 0), (array) ($payload['misc_comps'] ?? []))
        );

        $names = [];
        foreach (array_unique(array_filter($ids)) as $id) {
            $title = get_the_title($id);
            if ($title !== '') {
                $names[$id] = $title;
            }
        }
        return $names;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,string> comp_type id => name
     */
    private static function compTypeNames(array $payload): array
    {
        global $wpdb;
        $ids = array_unique(array_filter(array_map(
            static fn ($mc) => (int) ($mc['comp_type_id'] ?? 0),
            (array) ($payload['misc_comps'] ?? [])
        )));
        if (!$ids) {
            return [];
        }
        $table = WeeklySchema::table(WeeklySchema::TABLE_COMP_TYPES);
        $in    = implode(',', array_map('intval', $ids));
        $rows  = $wpdb->get_results("SELECT id, name FROM {$table} WHERE id IN ({$in})", ARRAY_A) ?: [];

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }
        return $names;
    }
}

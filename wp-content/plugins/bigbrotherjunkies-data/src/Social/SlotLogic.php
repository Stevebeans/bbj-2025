<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Pure 30-minute scheduling grid (no WordPress) for the Content Engine slot
 * picker. Taken = an already-scheduled queue row within the radius, labeled
 * by source so the dropdown doubles as an interleave view.
 */
class SlotLogic
{
    public const SLOT_MINUTES = 30;
    public const DEFAULT_COUNT = 48;
    public const TAKEN_RADIUS_MIN = 15;
    private const BUFFER_MIN = 5;

    private const LABELS = [
        'social_quickie'    => 'quickie card',
        'feed_update_share' => 'feed share',
        'batch'             => 'batch post',
        'manual_photo'      => 'photo post',
    ];

    public static function labelFor(string $source): string
    {
        return self::LABELS[$source] ?? 'manual post';
    }

    /**
     * @param array<int,array{at?:string,source?:string}> $scheduled
     * @return array<int,array{at:string,taken:bool,label:?string}>
     */
    public static function grid(
        \DateTimeImmutable $nowUtc,
        array $scheduled,
        int $count = self::DEFAULT_COUNT,
        int $radiusMin = self::TAKEN_RADIUS_MIN
    ): array {
        // Parse scheduled rows once; drop malformed ones.
        $rows = [];
        foreach ($scheduled as $r) {
            $raw = trim((string) ($r['at'] ?? ''));
            if ($raw === '') {
                // strtotime(' UTC') resolves to the CURRENT time, not false -
                // an empty at must be dropped explicitly or it would falsely
                // mark the nearest slot taken.
                continue;
            }
            $ts = strtotime($raw . ' UTC');
            if ($ts !== false && $ts > 0) {
                $rows[] = ['ts' => $ts, 'source' => (string) ($r['source'] ?? '')];
            }
        }

        // First slot: next :00/:30 boundary strictly after now + buffer.
        $base = $nowUtc->modify('+' . self::BUFFER_MIN . ' minutes');
        $min  = (int) $base->format('i');
        $add  = $min < 30 ? (30 - $min) : (60 - $min);
        $slot = $base->modify("+{$add} minutes")->setTime(
            (int) $base->modify("+{$add} minutes")->format('H'),
            (int) $base->modify("+{$add} minutes")->format('i'),
            0
        );

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $slotTs = $slot->getTimestamp();
            $taken  = false;
            $label  = null;
            foreach ($rows as $r) {
                if (abs($r['ts'] - $slotTs) <= $radiusMin * 60) {
                    $taken = true;
                    $label = self::labelFor($r['source']);
                    break;
                }
            }
            $out[] = [
                'at'    => $slot->format('Y-m-d H:i:s'),
                'taken' => $taken,
                'label' => $label,
            ];
            $slot = $slot->modify('+' . self::SLOT_MINUTES . ' minutes');
        }
        return $out;
    }
}

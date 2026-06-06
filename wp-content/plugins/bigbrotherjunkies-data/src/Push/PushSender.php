<?php

declare(strict_types=1);

namespace BigBrotherJunkies\Data\Push;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushSender
{
    public static function buildPayload(string $title, string $body, string $url): string
    {
        return wp_json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url !== '' ? $url : '/',
        ]);
    }

    /**
     * Send to every subscription. Prunes endpoints the push service reports gone.
     *
     * @return array{sent:int, failed:int, pruned:int}
     */
    public static function broadcast(string $title, string $body, string $url): array
    {
        if (!VapidKeys::isConfigured()) {
            return ['sent' => 0, 'failed' => 0, 'pruned' => 0, 'error' => 'VAPID not configured'];
        }

        $payload = self::buildPayload($title, $body, $url);
        $webPush = new WebPush(VapidKeys::authArray());
        $rows    = PushSubscriptionRepository::all();

        foreach ($rows as $row) {
            $webPush->queueNotification(
                Subscription::create(PushSubscriptionRepository::toWebPushArray($row)),
                $payload
            );
        }

        $sent   = 0;
        $failed = 0;
        $pruned = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                if ($report->isSubscriptionExpired()) {
                    PushSubscriptionRepository::deleteByEndpoint($report->getEndpoint());
                    $pruned++;
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'pruned' => $pruned];
    }
}

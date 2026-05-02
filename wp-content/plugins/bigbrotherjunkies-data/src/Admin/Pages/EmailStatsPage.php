<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Email\EmailService;

class EmailStatsPage
{
    public const MENU_SLUG = 'bbjd-mailing-stats';

    public function render(): void
    {
        $service = new EmailService();
        $stats = $service->getStats();
        $engagement = $service->getEngagementScoring();
        $recentSends = $service->getRecentSends(15);

        $sends = $stats['sends_90d'];
        $totalEngaged = array_sum($engagement);
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-6xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Email Stats
                </h1>

                <!-- Stat Cards -->
                <div class="bbjd-grid bbjd-grid-cols-2 md:bbjd-grid-cols-4 bbjd-gap-4 bbjd-mb-6">
                    <?php
                    $totalSubscribed = $stats['subscribers']['subscribed'] ?? 0;
                    $cards = [
                        ['label' => 'Total Subscribed', 'value' => number_format($totalSubscribed), 'color' => 'bbjd-text-primary500'],
                        ['label' => 'Open Rate (90d)', 'value' => $sends['open_rate'] . '%', 'color' => 'bbjd-text-green-600'],
                        ['label' => 'Click Rate (90d)', 'value' => $sends['click_rate'] . '%', 'color' => 'bbjd-text-blue-600'],
                        ['label' => 'Bounce Rate (90d)', 'value' => $sends['bounce_rate'] . '%', 'color' => 'bbjd-text-red-600'],
                    ];
                    foreach ($cards as $card):
                    ?>
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-4">
                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-uppercase bbjd-tracking-wide"><?php echo esc_html($card['label']); ?></p>
                        <p class="bbjd-text-2xl bbjd-font-bold <?php echo $card['color']; ?> bbjd-mt-1"><?php echo esc_html($card['value']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="bbjd-grid bbjd-grid-cols-1 lg:bbjd-grid-cols-3 bbjd-gap-6 bbjd-mb-6">
                    <!-- Engagement Groups -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
                        <h2 class="bbjd-text-lg bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">Engagement</h2>
                        <div class="bbjd-space-y-3">
                            <?php
                            $groups = [
                                ['label' => 'Active (30d)', 'key' => 'active', 'color' => 'bbjd-bg-green-500'],
                                ['label' => 'Inactive (30-90d)', 'key' => 'inactive', 'color' => 'bbjd-bg-yellow-500'],
                                ['label' => 'Dormant (90d+)', 'key' => 'dormant', 'color' => 'bbjd-bg-red-500'],
                                ['label' => 'Never Opened', 'key' => 'never_opened', 'color' => 'bbjd-bg-gray-400'],
                            ];
                            foreach ($groups as $group):
                                $count = $engagement[$group['key']];
                                $pct = $totalEngaged > 0 ? round(($count / $totalEngaged) * 100) : 0;
                            ?>
                            <div>
                                <div class="bbjd-flex bbjd-justify-between bbjd-text-sm bbjd-mb-1">
                                    <span class="bbjd-text-gray-700"><?php echo $group['label']; ?></span>
                                    <span class="bbjd-font-medium"><?php echo $count; ?> (<?php echo $pct; ?>%)</span>
                                </div>
                                <div class="bbjd-w-full bbjd-bg-gray-200 bbjd-rounded-full bbjd-h-2">
                                    <div class="<?php echo $group['color']; ?> bbjd-h-2 bbjd-rounded-full" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 lg:bbjd-col-span-2">
                        <h2 class="bbjd-text-lg bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">Recommendations</h2>
                        <div class="bbjd-space-y-3">
                            <?php if ($engagement['dormant'] > 0): ?>
                            <div class="bbjd-p-3 bbjd-bg-amber-50 bbjd-border bbjd-border-amber-200 bbjd-rounded">
                                <p class="bbjd-text-sm bbjd-text-amber-800">
                                    <strong><?php echo $engagement['dormant']; ?> dormant subscribers</strong> haven't opened an email in 90+ days.
                                    Consider running a re-confirmation campaign from the Emails page.
                                </p>
                            </div>
                            <?php endif; ?>

                            <?php if ($engagement['never_opened'] > 0): ?>
                            <div class="bbjd-p-3 bbjd-bg-gray-50 bbjd-border bbjd-border-gray-200 bbjd-rounded">
                                <p class="bbjd-text-sm bbjd-text-gray-700">
                                    <strong><?php echo $engagement['never_opened']; ?> subscribers</strong> have never opened an email.
                                    They may be new or have email tracking blocked.
                                </p>
                            </div>
                            <?php endif; ?>

                            <?php if ($sends['bounce_rate'] > 5): ?>
                            <div class="bbjd-p-3 bbjd-bg-red-50 bbjd-border bbjd-border-red-200 bbjd-rounded">
                                <p class="bbjd-text-sm bbjd-text-red-800">
                                    Bounce rate is <strong><?php echo $sends['bounce_rate']; ?>%</strong> — above 5% threshold.
                                    Hard bounces are auto-removed. Check for invalid emails.
                                </p>
                            </div>
                            <?php endif; ?>

                            <?php if ($engagement['dormant'] === 0 && $sends['bounce_rate'] <= 5): ?>
                            <div class="bbjd-p-3 bbjd-bg-green-50 bbjd-border bbjd-border-green-200 bbjd-rounded">
                                <p class="bbjd-text-sm bbjd-text-green-800">
                                    List health looks good! No immediate actions needed.
                                </p>
                            </div>
                            <?php endif; ?>

                            <!-- Subscriber breakdown by status -->
                            <div class="bbjd-pt-3 bbjd-border-t bbjd-mt-3">
                                <h3 class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">Subscriber Breakdown</h3>
                                <div class="bbjd-grid bbjd-grid-cols-2 bbjd-gap-2 bbjd-text-sm">
                                    <?php foreach ($stats['subscribers'] as $status => $count): ?>
                                    <div class="bbjd-flex bbjd-justify-between">
                                        <span class="bbjd-text-gray-600"><?php echo esc_html(ucfirst($status)); ?></span>
                                        <span class="bbjd-font-medium"><?php echo number_format($count); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sends -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-overflow-hidden">
                    <div class="bbjd-px-6 bbjd-py-4 bbjd-border-b">
                        <h2 class="bbjd-text-lg bbjd-font-semibold bbjd-text-gray-800">Recent Sends</h2>
                    </div>
                    <table class="bbjd-w-full bbjd-text-sm">
                        <thead>
                            <tr class="bbjd-bg-gray-50 bbjd-border-b">
                                <th class="bbjd-text-left bbjd-px-6 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Subject</th>
                                <th class="bbjd-text-center bbjd-px-4 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Sent</th>
                                <th class="bbjd-text-center bbjd-px-4 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Opens</th>
                                <th class="bbjd-text-center bbjd-px-4 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Clicks</th>
                                <th class="bbjd-text-center bbjd-px-4 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Bounces</th>
                                <th class="bbjd-text-right bbjd-px-6 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSends)): ?>
                                <tr><td colspan="6" class="bbjd-px-6 bbjd-py-8 bbjd-text-center bbjd-text-gray-500">No emails sent yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSends as $send): ?>
                                <tr class="bbjd-border-b">
                                    <td class="bbjd-px-6 bbjd-py-3"><?php echo esc_html($send['subject']); ?></td>
                                    <td class="bbjd-px-4 bbjd-py-3 bbjd-text-center"><?php echo intval($send['total']); ?></td>
                                    <td class="bbjd-px-4 bbjd-py-3 bbjd-text-center">
                                        <?php
                                        $total = intval($send['total']);
                                        $opens = intval($send['opens']);
                                        $openPct = $total > 0 ? round(($opens / $total) * 100, 1) : 0;
                                        echo $opens . ' <span class="bbjd-text-gray-400">(' . $openPct . '%)</span>';
                                        ?>
                                    </td>
                                    <td class="bbjd-px-4 bbjd-py-3 bbjd-text-center">
                                        <?php
                                        $clicks = intval($send['clicks']);
                                        $clickPct = $total > 0 ? round(($clicks / $total) * 100, 1) : 0;
                                        echo $clicks . ' <span class="bbjd-text-gray-400">(' . $clickPct . '%)</span>';
                                        ?>
                                    </td>
                                    <td class="bbjd-px-4 bbjd-py-3 bbjd-text-center"><?php echo intval($send['bounces']); ?></td>
                                    <td class="bbjd-px-6 bbjd-py-3 bbjd-text-right bbjd-text-gray-600 bbjd-text-xs">
                                        <?php echo esc_html(date('M j, Y', strtotime($send['sent_at']))); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

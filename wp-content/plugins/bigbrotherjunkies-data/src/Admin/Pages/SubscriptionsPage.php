<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Billing\SubscriptionManager;

/**
 * Subscriptions admin page for managing user subscriptions
 */
class SubscriptionsPage
{
    public const MENU_SLUG = 'bbjd-subscriptions';

    /**
     * Handle admin actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_cancel_subscription', [$this, 'handleCancelEndOfCycle']);
        add_action('admin_post_bbjd_cancel_subscription_immediate', [$this, 'handleCancelImmediate']);
        add_action('admin_post_bbjd_update_subscription_status', [$this, 'handleUpdateStatus']);
    }

    /**
     * Cancel subscription at end of billing cycle
     */
    public function handleCancelEndOfCycle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $subscriptionId = intval($_GET['subscription_id'] ?? 0);
        check_admin_referer('bbjd_cancel_sub_' . $subscriptionId);

        $manager = SubscriptionManager::getInstance();
        $subscription = $manager->findById($subscriptionId);

        if (!$subscription) {
            $this->redirectWithMessage('subscription_not_found');
            return;
        }

        $result = $manager->cancelSubscription((int) $subscription['user_id'], immediate: false);

        $message = isset($result['error']) ? 'cancel_error' : 'cancel_end_of_cycle';
        $this->redirectWithMessage($message);
    }

    /**
     * Cancel subscription immediately
     */
    public function handleCancelImmediate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $subscriptionId = intval($_GET['subscription_id'] ?? 0);
        check_admin_referer('bbjd_cancel_sub_immediate_' . $subscriptionId);

        $manager = SubscriptionManager::getInstance();
        $subscription = $manager->findById($subscriptionId);

        if (!$subscription) {
            $this->redirectWithMessage('subscription_not_found');
            return;
        }

        $result = $manager->cancelSubscription((int) $subscription['user_id'], immediate: true);

        $message = isset($result['error']) ? 'cancel_error' : 'cancel_immediate';
        $this->redirectWithMessage($message);
    }

    /**
     * Manually update subscription status
     */
    public function handleUpdateStatus(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_update_sub_status');

        $subscriptionId = intval($_POST['subscription_id'] ?? 0);
        $newStatus = sanitize_text_field($_POST['new_status'] ?? '');

        $validStatuses = ['active', 'canceled', 'expired', 'lifetime'];
        if (!$subscriptionId || !in_array($newStatus, $validStatuses, true)) {
            $this->redirectWithMessage('invalid_status');
            return;
        }

        $manager = SubscriptionManager::getInstance();
        $subscription = $manager->findById($subscriptionId);

        if (!$subscription) {
            $this->redirectWithMessage('subscription_not_found');
            return;
        }

        // Update DB status — this also handles role sync internally
        $manager->updateSubscriptionStatus($subscriptionId, $newStatus);

        $this->redirectWithMessage('status_updated');
    }

    /**
     * Redirect back to page with message
     */
    private function redirectWithMessage(string $message): void
    {
        $filter = sanitize_text_field($_GET['filter'] ?? $_POST['filter'] ?? '');
        $validFilters = ['active', 'lifetime', 'canceled', 'expired'];
        if ($filter && !in_array($filter, $validFilters, true)) {
            $filter = '';
        }

        $args = [
            'page' => self::MENU_SLUG,
            'message' => $message,
        ];
        if ($filter) {
            $args['filter'] = $filter;
        }

        wp_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $filter = sanitize_text_field($_GET['filter'] ?? '');
        $page = max(1, intval($_GET['paged'] ?? 1));
        $message = sanitize_text_field($_GET['message'] ?? '');

        $manager = SubscriptionManager::getInstance();
        $stats = $manager->getStats();
        $statusFilter = in_array($filter, ['active', 'lifetime', 'canceled', 'expired'], true) ? $filter : null;
        $data = $manager->getAllSubscriptions($statusFilter, $page, 25);
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-7xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Subscriptions
                </h1>

                <?php $this->renderMessages($message); ?>

                <?php $this->renderStatsCards($stats); ?>

                <?php $this->renderFilterTabs($filter); ?>

                <?php $this->renderTable($data, $filter, $page); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render stats cards
     */
    private function renderStatsCards(array $stats): void
    {
        $cards = [
            [
                'label' => 'Active',
                'value' => (int) $stats['active'],
                'bg' => 'bbjd-bg-green-50',
                'border' => 'bbjd-border-green-200',
                'text' => 'bbjd-text-green-700',
                'valueText' => 'bbjd-text-green-900',
            ],
            [
                'label' => 'Lifetime',
                'value' => (int) $stats['lifetime'],
                'bg' => 'bbjd-bg-purple-50',
                'border' => 'bbjd-border-purple-200',
                'text' => 'bbjd-text-purple-700',
                'valueText' => 'bbjd-text-purple-900',
            ],
            [
                'label' => 'Cancelled',
                'value' => (int) $stats['canceled'],
                'bg' => 'bbjd-bg-gray-50',
                'border' => 'bbjd-border-gray-200',
                'text' => 'bbjd-text-gray-600',
                'valueText' => 'bbjd-text-gray-900',
            ],
            [
                'label' => 'Total All-Time',
                'value' => (int) $stats['total'],
                'bg' => 'bbjd-bg-blue-50',
                'border' => 'bbjd-border-blue-200',
                'text' => 'bbjd-text-blue-700',
                'valueText' => 'bbjd-text-blue-900',
            ],
        ];
        ?>
        <div class="bbjd-grid bbjd-grid-cols-2 lg:bbjd-grid-cols-4 bbjd-gap-4 bbjd-mb-6">
            <?php foreach ($cards as $card): ?>
                <div class="<?php echo "{$card['bg']} {$card['border']}"; ?> bbjd-border bbjd-rounded-lg bbjd-p-4">
                    <div class="<?php echo $card['text']; ?> bbjd-text-sm bbjd-font-medium"><?php echo esc_html($card['label']); ?></div>
                    <div class="<?php echo $card['valueText']; ?> bbjd-text-2xl bbjd-font-bold bbjd-mt-1"><?php echo esc_html($card['value']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render filter tabs
     */
    private function renderFilterTabs(string $activeFilter): void
    {
        $tabs = [
            '' => 'All',
            'active' => 'Active',
            'lifetime' => 'Lifetime',
            'canceled' => 'Cancelled',
            'expired' => 'Expired',
        ];
        ?>
        <div class="bbjd-mb-6 bbjd-border-b bbjd-border-gray-200">
            <nav class="bbjd-flex bbjd-gap-4">
                <?php foreach ($tabs as $value => $label): ?>
                    <a href="<?php echo add_query_arg(['filter' => $value], admin_url('admin.php?page=' . self::MENU_SLUG)); ?>"
                       class="bbjd-px-4 bbjd-py-2 bbjd-font-medium <?php echo $activeFilter === $value ? 'bbjd-text-primary500 bbjd-border-b-2 bbjd-border-primary500' : 'bbjd-text-gray-500 hover:bbjd-text-gray-700'; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php
    }

    /**
     * Render subscriptions table
     */
    private function renderTable(array $data, string $filter, int $currentPage): void
    {
        $items = $data['items'];
        $totalPages = $data['pages'];
        $total = $data['total'];
        ?>
        <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
            <div class="bbjd-flex bbjd-justify-between bbjd-items-center bbjd-mb-4">
                <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800">
                    <?php echo esc_html($total); ?> subscription<?php echo $total !== 1 ? 's' : ''; ?>
                </h2>
            </div>

            <?php if (empty($items)): ?>
                <p class="bbjd-text-gray-500">No subscriptions found.</p>
            <?php else: ?>
                <div class="bbjd-overflow-x-auto">
                    <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200 bbjd-text-sm">
                        <thead class="bbjd-bg-gray-50">
                            <tr>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">User</th>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Plan</th>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Status</th>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Processor</th>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Amount</th>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Period End</th>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">WP Role</th>
                                <th class="bbjd-px-3 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                            <?php foreach ($items as $sub): ?>
                                <?php $this->renderRow($sub, $filter); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php $this->renderPagination($currentPage, $totalPages, $filter); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a single table row
     */
    private function renderRow(array $sub, string $filter): void
    {
        $userId = (int) $sub['user_id'];
        $user = get_user_by('ID', $userId);
        $roles = $user ? implode(', ', $user->roles) : 'N/A';
        $isActive = in_array($sub['status'], ['active', 'lifetime'], true);
        $isCancelPending = $isActive && (bool) ($sub['cancel_at_period_end'] ?? false);
        ?>
        <tr>
            <!-- User -->
            <td class="bbjd-px-3 bbjd-py-3">
                <div class="bbjd-font-medium bbjd-text-gray-900"><?php echo esc_html($sub['display_name'] ?? 'Unknown'); ?></div>
                <div class="bbjd-text-gray-500 bbjd-text-xs"><?php echo esc_html($sub['user_email'] ?? ''); ?></div>
                <div class="bbjd-text-gray-400 bbjd-text-xs">@<?php echo esc_html($sub['user_login'] ?? '?'); ?> (ID: <?php echo $userId; ?>)</div>
            </td>

            <!-- Plan -->
            <td class="bbjd-px-3 bbjd-py-3">
                <?php echo $this->planBadge($sub['plan_type']); ?>
            </td>

            <!-- Status -->
            <td class="bbjd-px-3 bbjd-py-3">
                <?php echo $this->statusBadge($sub['status']); ?>
                <?php if ($isCancelPending): ?>
                    <div class="bbjd-text-xs bbjd-text-orange-600 bbjd-mt-1">Cancels at period end</div>
                <?php endif; ?>
            </td>

            <!-- Processor -->
            <td class="bbjd-px-3 bbjd-py-3">
                <?php echo $this->processorBadge($sub['processor']); ?>
            </td>

            <!-- Amount -->
            <td class="bbjd-px-3 bbjd-py-3 bbjd-text-gray-900">
                <?php
                $cents = (int) ($sub['amount_cents'] ?? 0);
                echo $cents > 0 ? '$' . number_format($cents / 100, 2) : '—';
                ?>
            </td>

            <!-- Period End -->
            <td class="bbjd-px-3 bbjd-py-3 bbjd-text-gray-500">
                <?php
                if ($sub['status'] === 'lifetime') {
                    echo '<span class="bbjd-text-purple-600 bbjd-font-medium">Never</span>';
                } elseif (!empty($sub['current_period_end'])) {
                    echo esc_html(date('M j, Y', strtotime($sub['current_period_end'])));
                } else {
                    echo '—';
                }
                ?>
            </td>

            <!-- WP Role -->
            <td class="bbjd-px-3 bbjd-py-3 bbjd-text-gray-600 bbjd-text-xs">
                <?php echo esc_html($roles); ?>
            </td>

            <!-- Actions -->
            <td class="bbjd-px-3 bbjd-py-3">
                <div class="bbjd-flex bbjd-flex-col bbjd-gap-2">
                    <?php if ($isActive && $sub['status'] !== 'lifetime'): ?>
                        <?php if (!$isCancelPending && $sub['processor'] === 'stripe'): ?>
                            <!-- Cancel at end of cycle (Stripe only) -->
                            <a href="<?php echo wp_nonce_url(
                                add_query_arg([
                                    'action' => 'bbjd_cancel_subscription',
                                    'subscription_id' => $sub['id'],
                                    'filter' => $filter,
                                ], admin_url('admin-post.php')),
                                'bbjd_cancel_sub_' . $sub['id']
                            ); ?>"
                               class="bbjd-text-orange-600 hover:bbjd-text-orange-800 bbjd-text-xs"
                               onclick="return confirm('Cancel at end of billing cycle? User keeps access until period ends.');">
                                Cancel (End of Cycle)
                            </a>
                        <?php endif; ?>

                        <!-- Cancel immediately -->
                        <a href="<?php echo wp_nonce_url(
                            add_query_arg([
                                'action' => 'bbjd_cancel_subscription_immediate',
                                'subscription_id' => $sub['id'],
                                'filter' => $filter,
                            ], admin_url('admin-post.php')),
                            'bbjd_cancel_sub_immediate_' . $sub['id']
                        ); ?>"
                           class="bbjd-text-red-600 hover:bbjd-text-red-800 bbjd-text-xs"
                           onclick="return confirm('Cancel IMMEDIATELY? User loses access right now.');">
                            Cancel (Immediate)
                        </a>
                    <?php endif; ?>

                    <!-- Manual status change -->
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="bbjd-flex bbjd-gap-1 bbjd-items-center"
                          onsubmit="return confirm('Manually change subscription status? This will also update the user\'s WP role.');">
                        <?php wp_nonce_field('bbjd_update_sub_status'); ?>
                        <input type="hidden" name="action" value="bbjd_update_subscription_status">
                        <input type="hidden" name="subscription_id" value="<?php echo esc_attr($sub['id']); ?>">
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                        <select name="new_status" class="bbjd-text-xs bbjd-border bbjd-border-gray-300 bbjd-rounded bbjd-px-1 bbjd-py-0.5">
                            <option value="active" <?php selected($sub['status'], 'active'); ?>>Active</option>
                            <option value="lifetime" <?php selected($sub['status'], 'lifetime'); ?>>Lifetime</option>
                            <option value="canceled" <?php selected($sub['status'], 'canceled'); ?>>Cancelled</option>
                            <option value="expired" <?php selected($sub['status'], 'expired'); ?>>Expired</option>
                        </select>
                        <button type="submit" class="bbjd-text-xs bbjd-text-primary500 hover:bbjd-text-primaryHard bbjd-font-medium">
                            Set
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Render pagination
     */
    private function renderPagination(int $currentPage, int $totalPages, string $filter): void
    {
        if ($totalPages <= 1) {
            return;
        }
        ?>
        <div class="bbjd-flex bbjd-justify-center bbjd-gap-2 bbjd-mt-4">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?php echo add_query_arg(['paged' => $i, 'filter' => $filter], admin_url('admin.php?page=' . self::MENU_SLUG)); ?>"
                   class="bbjd-px-3 bbjd-py-1 bbjd-rounded bbjd-text-sm <?php echo $i === $currentPage ? 'bbjd-bg-primary500 bbjd-text-white' : 'bbjd-bg-gray-100 bbjd-text-gray-700 hover:bbjd-bg-gray-200'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php
    }

    /**
     * Plan badge HTML
     */
    private function planBadge(string $plan): string
    {
        $config = [
            'monthly' => ['label' => 'Monthly', 'class' => 'bbjd-bg-blue-100 bbjd-text-blue-800'],
            'annual'  => ['label' => 'Annual',  'class' => 'bbjd-bg-indigo-100 bbjd-text-indigo-800'],
            'lifetime' => ['label' => 'Lifetime', 'class' => 'bbjd-bg-purple-100 bbjd-text-purple-800'],
        ];

        $c = $config[$plan] ?? ['label' => $plan, 'class' => 'bbjd-bg-gray-100 bbjd-text-gray-800'];
        return '<span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium ' . $c['class'] . '">' . esc_html($c['label']) . '</span>';
    }

    /**
     * Status badge HTML
     */
    private function statusBadge(string $status): string
    {
        $config = [
            'active'   => ['label' => 'Active',    'class' => 'bbjd-bg-green-100 bbjd-text-green-800'],
            'lifetime' => ['label' => 'Lifetime',   'class' => 'bbjd-bg-purple-100 bbjd-text-purple-800'],
            'canceled' => ['label' => 'Cancelled',  'class' => 'bbjd-bg-red-100 bbjd-text-red-800'],
            'expired'  => ['label' => 'Expired',    'class' => 'bbjd-bg-gray-100 bbjd-text-gray-800'],
            'past_due' => ['label' => 'Past Due',   'class' => 'bbjd-bg-yellow-100 bbjd-text-yellow-800'],
        ];

        $c = $config[$status] ?? ['label' => $status, 'class' => 'bbjd-bg-gray-100 bbjd-text-gray-800'];
        return '<span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium ' . $c['class'] . '">' . esc_html($c['label']) . '</span>';
    }

    /**
     * Processor badge HTML
     */
    private function processorBadge(string $processor): string
    {
        $config = [
            'stripe' => ['label' => 'Stripe', 'class' => 'bbjd-bg-violet-100 bbjd-text-violet-800'],
            'paypal' => ['label' => 'PayPal', 'class' => 'bbjd-bg-sky-100 bbjd-text-sky-800'],
        ];

        $c = $config[$processor] ?? ['label' => $processor, 'class' => 'bbjd-bg-gray-100 bbjd-text-gray-800'];
        return '<span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium ' . $c['class'] . '">' . esc_html($c['label']) . '</span>';
    }

    /**
     * Render status messages
     */
    private function renderMessages(string $message): void
    {
        if (empty($message)) {
            return;
        }

        $messages = [
            'cancel_end_of_cycle'    => ['success', 'Subscription set to cancel at end of billing cycle.'],
            'cancel_immediate'       => ['success', 'Subscription cancelled immediately. User access revoked.'],
            'status_updated'         => ['success', 'Subscription status and user role updated.'],
            'cancel_error'           => ['error', 'Failed to cancel subscription. Check error logs.'],
            'subscription_not_found' => ['error', 'Subscription not found.'],
            'invalid_status'         => ['error', 'Invalid status provided.'],
        ];

        if (!isset($messages[$message])) {
            return;
        }

        [$type, $text] = $messages[$message];
        $bgColor = $type === 'success'
            ? 'bbjd-bg-green-100 bbjd-border-green-500 bbjd-text-green-700'
            : 'bbjd-bg-red-100 bbjd-border-red-500 bbjd-text-red-700';
        ?>
        <div class="<?php echo $bgColor; ?> bbjd-border-l-4 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
            <p><?php echo esc_html($text); ?></p>
        </div>
        <?php
    }
}

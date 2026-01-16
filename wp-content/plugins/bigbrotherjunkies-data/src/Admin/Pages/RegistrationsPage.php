<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Api\AuthRoutes;

/**
 * Registrations admin page for managing user registrations
 */
class RegistrationsPage
{
    public const MENU_SLUG = 'bbjd-registrations';

    /**
     * Handle admin actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_delete_user', [$this, 'handleDeleteUser']);
        add_action('admin_post_bbjd_resend_verification', [$this, 'handleResendVerification']);
        add_action('admin_post_bbjd_clear_registration_logs', [$this, 'handleClearLogs']);
    }

    /**
     * Handle delete user action
     */
    public function handleDeleteUser(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $userId = intval($_GET['user_id'] ?? 0);
        check_admin_referer('bbjd_delete_user_' . $userId);

        if ($userId && $userId !== get_current_user_id()) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user($userId);
            $message = 'user_deleted';
        } else {
            $message = 'delete_error';
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle resend verification email
     */
    public function handleResendVerification(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $userId = intval($_GET['user_id'] ?? 0);
        check_admin_referer('bbjd_resend_verification_' . $userId);

        $user = get_user_by('ID', $userId);
        if ($user) {
            // Generate new verification token
            $verificationToken = wp_generate_password(32, false);
            update_user_meta($userId, 'bbjd_verification_token', $verificationToken);
            update_user_meta($userId, 'bbjd_verification_expires', time() + (24 * HOUR_IN_SECONDS));

            // Send verification email
            $this->sendVerificationEmail($user->user_email, $user->user_login, $verificationToken);
            $message = 'verification_sent';
        } else {
            $message = 'user_not_found';
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle clear logs action
     */
    public function handleClearLogs(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_clear_registration_logs');

        AuthRoutes::clearRegistrationLogs();

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'logs_cleared',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $tab = $_GET['tab'] ?? 'pending';
        $message = $_GET['message'] ?? '';
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-6xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Registrations
                </h1>

                <?php $this->renderMessages($message); ?>

                <!-- Tabs -->
                <div class="bbjd-mb-6 bbjd-border-b bbjd-border-gray-200">
                    <nav class="bbjd-flex bbjd-gap-4">
                        <a href="<?php echo add_query_arg('tab', 'pending', admin_url('admin.php?page=' . self::MENU_SLUG)); ?>"
                           class="bbjd-px-4 bbjd-py-2 bbjd-font-medium <?php echo $tab === 'pending' ? 'bbjd-text-primary500 bbjd-border-b-2 bbjd-border-primary500' : 'bbjd-text-gray-500 hover:bbjd-text-gray-700'; ?>">
                            Pending Verification
                        </a>
                        <a href="<?php echo add_query_arg('tab', 'logs', admin_url('admin.php?page=' . self::MENU_SLUG)); ?>"
                           class="bbjd-px-4 bbjd-py-2 bbjd-font-medium <?php echo $tab === 'logs' ? 'bbjd-text-primary500 bbjd-border-b-2 bbjd-border-primary500' : 'bbjd-text-gray-500 hover:bbjd-text-gray-700'; ?>">
                            Registration Logs
                        </a>
                    </nav>
                </div>

                <?php
                if ($tab === 'logs') {
                    $this->renderLogsTab();
                } else {
                    $this->renderPendingTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render pending verifications tab
     */
    private function renderPendingTab(): void
    {
        $pendingUsers = $this->getPendingVerifications();
        ?>
        <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
            <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                Users Awaiting Email Verification
            </h2>

            <?php if (empty($pendingUsers)): ?>
                <p class="bbjd-text-gray-500">No users pending email verification.</p>
            <?php else: ?>
                <div class="bbjd-overflow-x-auto">
                    <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200 bbjd-text-sm">
                        <thead class="bbjd-bg-gray-50">
                            <tr>
                                <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">User</th>
                                <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Email</th>
                                <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Registered</th>
                                <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Method</th>
                                <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Expires</th>
                                <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                            <?php foreach ($pendingUsers as $user): ?>
                            <tr>
                                <td class="bbjd-px-4 bbjd-py-3">
                                    <div class="bbjd-font-medium bbjd-text-gray-900"><?php echo esc_html($user->display_name); ?></div>
                                    <div class="bbjd-text-gray-500 bbjd-text-xs">@<?php echo esc_html($user->user_login); ?> (ID: <?php echo $user->ID; ?>)</div>
                                </td>
                                <td class="bbjd-px-4 bbjd-py-3 bbjd-text-gray-900">
                                    <?php echo esc_html($user->user_email); ?>
                                </td>
                                <td class="bbjd-px-4 bbjd-py-3 bbjd-text-gray-500">
                                    <?php echo esc_html($user->user_registered); ?>
                                </td>
                                <td class="bbjd-px-4 bbjd-py-3">
                                    <?php
                                    $method = get_user_meta($user->ID, 'bbjd_registered_via', true) ?: 'email';
                                    $methodClass = $method === 'google' ? 'bbjd-bg-blue-100 bbjd-text-blue-800' : 'bbjd-bg-gray-100 bbjd-text-gray-800';
                                    ?>
                                    <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium <?php echo $methodClass; ?>">
                                        <?php echo esc_html($method); ?>
                                    </span>
                                </td>
                                <td class="bbjd-px-4 bbjd-py-3 bbjd-text-gray-500">
                                    <?php
                                    $expires = get_user_meta($user->ID, 'bbjd_verification_expires', true);
                                    if ($expires) {
                                        $isExpired = $expires < time();
                                        $expireText = $isExpired ? 'Expired' : human_time_diff($expires) . ' left';
                                        $expireClass = $isExpired ? 'bbjd-text-red-600' : 'bbjd-text-gray-500';
                                        echo '<span class="' . $expireClass . '">' . esc_html($expireText) . '</span>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="bbjd-px-4 bbjd-py-3">
                                    <div class="bbjd-flex bbjd-gap-3">
                                        <a href="<?php echo wp_nonce_url(
                                            add_query_arg([
                                                'action' => 'bbjd_resend_verification',
                                                'user_id' => $user->ID,
                                            ], admin_url('admin-post.php')),
                                            'bbjd_resend_verification_' . $user->ID
                                        ); ?>" class="bbjd-text-primary500 hover:bbjd-text-primaryHard bbjd-text-sm">
                                            Resend
                                        </a>
                                        <a href="<?php echo wp_nonce_url(
                                            add_query_arg([
                                                'action' => 'bbjd_delete_user',
                                                'user_id' => $user->ID,
                                            ], admin_url('admin-post.php')),
                                            'bbjd_delete_user_' . $user->ID
                                        ); ?>"
                                           class="bbjd-text-red-600 hover:bbjd-text-red-800 bbjd-text-sm"
                                           onclick="return confirm('Are you sure you want to delete this user?');">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render registration logs tab
     */
    private function renderLogsTab(): void
    {
        $logs = AuthRoutes::getRegistrationLogs(100);
        ?>
        <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
            <div class="bbjd-flex bbjd-justify-between bbjd-items-center bbjd-mb-4">
                <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800">
                    Registration Logs
                </h2>
                <?php if (!empty($logs)): ?>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
                      onsubmit="return confirm('Are you sure you want to clear all registration logs?');">
                    <?php wp_nonce_field('bbjd_clear_registration_logs'); ?>
                    <input type="hidden" name="action" value="bbjd_clear_registration_logs">
                    <button type="submit" class="bbjd-text-sm bbjd-text-red-600 hover:bbjd-text-red-800">
                        Clear Logs
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (empty($logs)): ?>
                <p class="bbjd-text-gray-500 bbjd-text-sm">No registration attempts logged yet.</p>
            <?php else: ?>
                <div class="bbjd-overflow-x-auto">
                    <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200 bbjd-text-sm">
                        <thead class="bbjd-bg-gray-50">
                            <tr>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Time</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Username</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Email</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Method</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">reCAPTCHA</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Status</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">IP</th>
                            </tr>
                        </thead>
                        <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-500 bbjd-whitespace-nowrap">
                                    <?php echo esc_html($log['timestamp'] ?? '-'); ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-900">
                                    <?php echo esc_html($log['username'] ?? '-'); ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-900">
                                    <?php echo esc_html($log['email'] ?? '-'); ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2">
                                    <?php
                                    $method = $log['method'] ?? 'email';
                                    $methodClass = $method === 'google' ? 'bbjd-bg-blue-100 bbjd-text-blue-800' : 'bbjd-bg-gray-100 bbjd-text-gray-800';
                                    ?>
                                    <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium <?php echo $methodClass; ?>">
                                        <?php echo esc_html($method); ?>
                                    </span>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2">
                                    <?php
                                    $score = $log['recaptcha_score'] ?? null;
                                    if ($score === null) {
                                        echo '<span class="bbjd-text-gray-400">N/A</span>';
                                    } else {
                                        $scoreColor = $score >= 0.7 ? 'bbjd-text-green-600' : ($score >= 0.5 ? 'bbjd-text-yellow-600' : 'bbjd-text-red-600');
                                        echo '<span class="' . $scoreColor . ' bbjd-font-mono">' . number_format($score, 2) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2">
                                    <?php
                                    $status = $log['status'] ?? 'unknown';
                                    $statusClass = $status === 'success'
                                        ? 'bbjd-bg-green-100 bbjd-text-green-800'
                                        : 'bbjd-bg-red-100 bbjd-text-red-800';
                                    ?>
                                    <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium <?php echo $statusClass; ?>">
                                        <?php echo esc_html($status); ?>
                                    </span>
                                    <?php if (!empty($log['reason'])): ?>
                                        <span class="bbjd-text-xs bbjd-text-gray-400 bbjd-ml-1">(<?php echo esc_html($log['reason']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-500 bbjd-font-mono bbjd-text-xs">
                                    <?php echo esc_html($log['ip'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get users pending email verification
     */
    private function getPendingVerifications(): array
    {
        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'bbjd_email_verified',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ],
            'orderby' => 'registered',
            'order' => 'DESC',
        ]);

        return $users;
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail(string $email, string $username, string $token): void
    {
        $verifyUrl = add_query_arg([
            'action' => 'bbjd_verify_email',
            'token' => $token,
            'email' => urlencode($email),
        ], home_url('/'));

        $subject = sprintf(__('Verify your email - %s', 'bigbrotherjunkies-data'), get_bloginfo('name'));

        $message = sprintf(
            __("Hi %s,\n\nPlease click the link below to verify your email address:\n\n%s\n\nThis link will expire in 24 hours.\n\nIf you didn't create an account, you can safely ignore this email.\n\nThanks,\n%s", 'bigbrotherjunkies-data'),
            $username,
            $verifyUrl,
            get_bloginfo('name')
        );

        wp_mail($email, $subject, $message);
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
            'user_deleted' => ['success', 'User deleted successfully.'],
            'delete_error' => ['error', 'Could not delete user.'],
            'verification_sent' => ['success', 'Verification email sent successfully.'],
            'user_not_found' => ['error', 'User not found.'],
            'logs_cleared' => ['success', 'Registration logs cleared.'],
        ];

        if (!isset($messages[$message])) {
            return;
        }

        [$type, $text] = $messages[$message];
        $bgColor = $type === 'success' ? 'bbjd-bg-green-100 bbjd-border-green-500 bbjd-text-green-700' : 'bbjd-bg-red-100 bbjd-border-red-500 bbjd-text-red-700';
        ?>
        <div class="<?php echo $bgColor; ?> bbjd-border-l-4 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
            <p><?php echo esc_html($text); ?></p>
        </div>
        <?php
    }
}

<?php

namespace BigBrotherJunkies\Data\Auth\Integrations;

/**
 * MailPoet integration for newsletter subscriptions
 */
class MailPoetSubscriber
{
    /**
     * Default list name for Big Brother Daily Digest
     */
    private const DEFAULT_LIST = 'Big Brother Daily Digest';

    /**
     * Subscribe a user to the newsletter
     */
    public function subscribe(string $email, string $firstName = '', string $lastName = ''): bool
    {
        // Check if MailPoet is active
        if (!$this->isMailPoetActive()) {
            error_log('BBJD: MailPoet is not active, cannot subscribe user.');
            return false;
        }

        try {
            $mailpoetApi = \MailPoet\API\API::MP('v1');

            // Get or find the list ID
            $listId = $this->getListId($mailpoetApi);

            if (!$listId) {
                error_log('BBJD: Could not find newsletter list.');
                return false;
            }

            // Prepare subscriber data
            $subscriberData = [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];

            // Check if subscriber already exists
            try {
                $existingSubscriber = $mailpoetApi->getSubscriber($email);

                // Subscriber exists, add to list if not already subscribed
                $mailpoetApi->subscribeToList($existingSubscriber['id'], $listId, [
                    'send_confirmation_email' => false,
                ]);

                return true;
            } catch (\Exception $e) {
                // Subscriber doesn't exist, create new one
            }

            // Add new subscriber
            $subscriber = $mailpoetApi->addSubscriber($subscriberData, [$listId], [
                'send_confirmation_email' => true,
                'skip_subscriber_notification' => false,
            ]);

            return !empty($subscriber['id']);

        } catch (\Exception $e) {
            error_log('BBJD: MailPoet subscription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if MailPoet plugin is active
     */
    private function isMailPoetActive(): bool
    {
        return class_exists(\MailPoet\API\API::class);
    }

    /**
     * Get the list ID for the newsletter
     */
    private function getListId($mailpoetApi): ?int
    {
        try {
            $lists = $mailpoetApi->getLists();

            // First, try to find by exact name
            foreach ($lists as $list) {
                if ($list['name'] === self::DEFAULT_LIST) {
                    return (int) $list['id'];
                }
            }

            // If not found, try partial match
            foreach ($lists as $list) {
                if (stripos($list['name'], 'daily digest') !== false) {
                    return (int) $list['id'];
                }
            }

            // If still not found, try any list with "newsletter" in the name
            foreach ($lists as $list) {
                if (stripos($list['name'], 'newsletter') !== false) {
                    return (int) $list['id'];
                }
            }

            // Fall back to first list if any exist
            if (!empty($lists)) {
                return (int) $lists[0]['id'];
            }

            return null;

        } catch (\Exception $e) {
            error_log('BBJD: Error getting MailPoet lists: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all available lists (for admin settings)
     */
    public function getAvailableLists(): array
    {
        if (!$this->isMailPoetActive()) {
            return [];
        }

        try {
            $mailpoetApi = \MailPoet\API\API::MP('v1');
            return $mailpoetApi->getLists();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Unsubscribe a user from the newsletter
     */
    public function unsubscribe(string $email): bool
    {
        if (!$this->isMailPoetActive()) {
            return false;
        }

        try {
            $mailpoetApi = \MailPoet\API\API::MP('v1');
            $listId = $this->getListId($mailpoetApi);

            if (!$listId) {
                return false;
            }

            $subscriber = $mailpoetApi->getSubscriber($email);
            $mailpoetApi->unsubscribeFromList($subscriber['id'], $listId);

            return true;

        } catch (\Exception $e) {
            error_log('BBJD: MailPoet unsubscribe error: ' . $e->getMessage());
            return false;
        }
    }
}

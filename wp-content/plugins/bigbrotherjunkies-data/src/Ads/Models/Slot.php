<?php

namespace BigBrotherJunkies\Data\Ads\Models;

/**
 * Slot entity model
 */
class Slot
{
    public int $id = 0;
    public string $name = '';
    public string $slug = '';
    public string $type = 'manual';
    public string $description = '';
    public array $settings = [];
    public string $status = 'active';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    /**
     * Type constants
     */
    public const TYPE_MANUAL = 'manual';
    public const TYPE_AUTO_CONTENT = 'auto_content';
    public const TYPE_AUTO_COMMENTS = 'auto_comments';
    public const TYPE_AUTO_FEED = 'auto_feed';

    /**
     * Create from database row
     */
    public static function fromRow(object $row): self
    {
        $slot = new self();
        $slot->id = (int) $row->id;
        $slot->name = $row->name;
        $slot->slug = $row->slug;
        $slot->type = $row->type;
        $slot->description = $row->description ?? '';
        $slot->settings = !empty($row->settings) ? json_decode($row->settings, true) : [];
        $slot->status = $row->status;
        $slot->createdAt = $row->created_at;
        $slot->updatedAt = $row->updated_at;

        return $slot;
    }

    /**
     * Convert to array for database insert/update
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'description' => $this->description,
            'settings' => json_encode($this->settings),
            'status' => $this->status,
        ];
    }

    /**
     * Check if slot is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if this is an auto-insertion slot
     */
    public function isAutoInsert(): bool
    {
        return in_array($this->type, [
            self::TYPE_AUTO_CONTENT,
            self::TYPE_AUTO_COMMENTS,
            self::TYPE_AUTO_FEED,
        ], true);
    }

    /**
     * Get a setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value
     */
    public function setSetting(string $key, $value): void
    {
        $this->settings[$key] = $value;
    }

    /**
     * Get roles that should not see this slot
     */
    public function getHiddenRoles(): array
    {
        return $this->settings['hidden_roles'] ?? [];
    }

    /**
     * Set roles that should not see this slot
     */
    public function setHiddenRoles(array $roles): void
    {
        $this->settings['hidden_roles'] = $roles;
    }

    /**
     * Check if a specific role should be hidden for this slot
     */
    public function isHiddenForRole(string $role): bool
    {
        return in_array($role, $this->getHiddenRoles(), true);
    }

    /**
     * Get default settings for auto-content insertion
     */
    public static function getAutoContentDefaults(): array
    {
        return [
            'paragraph_interval' => 4,
            'max_insertions' => 3,
            'skip_first_paragraphs' => 2,
            'avoid_consecutive' => true,
            'post_types' => ['post'],
            'min_paragraphs' => 6,
        ];
    }

    /**
     * Get default settings for auto-comment insertion
     */
    public static function getAutoCommentDefaults(): array
    {
        return [
            'comment_interval' => 5,
            'max_insertions' => 2,
            'skip_first_comments' => 3,
        ];
    }

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_MANUAL => __('Manual (Theme Function)', 'bigbrotherjunkies-data'),
            self::TYPE_AUTO_CONTENT => __('Auto Insert (Content)', 'bigbrotherjunkies-data'),
            self::TYPE_AUTO_COMMENTS => __('Auto Insert (Comments)', 'bigbrotherjunkies-data'),
            self::TYPE_AUTO_FEED => __('Auto Insert (Feed Updates)', 'bigbrotherjunkies-data'),
        ];
    }

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('Active', 'bigbrotherjunkies-data'),
            self::STATUS_DISABLED => __('Disabled', 'bigbrotherjunkies-data'),
        ];
    }
}

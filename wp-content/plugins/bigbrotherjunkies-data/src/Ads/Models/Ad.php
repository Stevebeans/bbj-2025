<?php

namespace BigBrotherJunkies\Data\Ads\Models;

/**
 * Ad entity model
 */
class Ad
{
    public int $id = 0;
    public string $name = '';
    public string $slug = '';
    public string $type = 'html';
    public string $contentDesktop = '';
    public string $contentMobile = '';
    public string $status = 'active';
    public int $priority = 0;
    public array $settings = [];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_DRAFT = 'draft';

    /**
     * Type constants
     */
    public const TYPE_HTML = 'html';
    public const TYPE_IMAGE = 'image';
    public const TYPE_ADSENSE = 'adsense';
    public const TYPE_AMAZON = 'amazon';

    /**
     * Create from database row
     */
    public static function fromRow(object $row): self
    {
        $ad = new self();
        $ad->id = (int) $row->id;
        $ad->name = $row->name;
        $ad->slug = $row->slug;
        $ad->type = $row->type;
        $ad->contentDesktop = $row->content_desktop ?? '';
        $ad->contentMobile = $row->content_mobile ?? '';
        $ad->status = $row->status;
        $ad->priority = (int) $row->priority;
        $ad->settings = !empty($row->settings) ? json_decode($row->settings, true) : [];
        $ad->createdAt = $row->created_at;
        $ad->updatedAt = $row->updated_at;

        return $ad;
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
            'content_desktop' => $this->contentDesktop,
            'content_mobile' => $this->contentMobile,
            'status' => $this->status,
            'priority' => $this->priority,
            'settings' => json_encode($this->settings),
        ];
    }

    /**
     * Check if ad is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get content for the current device
     */
    public function getContent(bool $isMobile = false): string
    {
        if ($isMobile && !empty($this->contentMobile)) {
            return $this->contentMobile;
        }
        return $this->contentDesktop;
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
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('Active', 'bigbrotherjunkies-data'),
            self::STATUS_PAUSED => __('Paused', 'bigbrotherjunkies-data'),
            self::STATUS_DRAFT => __('Draft', 'bigbrotherjunkies-data'),
        ];
    }

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_HTML => __('HTML/JavaScript', 'bigbrotherjunkies-data'),
            self::TYPE_IMAGE => __('Image', 'bigbrotherjunkies-data'),
            self::TYPE_ADSENSE => __('Google AdSense', 'bigbrotherjunkies-data'),
            self::TYPE_AMAZON => __('Amazon Associates', 'bigbrotherjunkies-data'),
        ];
    }
}

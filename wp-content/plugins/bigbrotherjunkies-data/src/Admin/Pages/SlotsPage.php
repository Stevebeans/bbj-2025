<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Ads\Models\Slot;
use BigBrotherJunkies\Data\Ads\Repositories\SlotRepository;

/**
 * Slots management admin page
 */
class SlotsPage
{
    public const MENU_SLUG = 'bbjd-slots';

    private SlotRepository $slotRepository;

    public function __construct()
    {
        $this->slotRepository = new SlotRepository();
    }

    /**
     * Handle actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_save_slot', [$this, 'handleSaveSlot']);
        add_action('admin_post_bbjd_delete_slot', [$this, 'handleDeleteSlot']);
    }

    /**
     * Handle save slot action
     */
    public function handleSaveSlot(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_save_slot');

        $slotId = intval($_POST['slot_id'] ?? 0);
        $isNew = $slotId === 0;

        $slot = $isNew ? new Slot() : $this->slotRepository->find($slotId);
        if (!$slot) {
            $slot = new Slot();
        }

        $slot->name = sanitize_text_field($_POST['slot_name'] ?? '');
        $slot->slug = sanitize_title($_POST['slot_slug'] ?? $slot->name);
        $slot->type = sanitize_text_field($_POST['slot_type'] ?? 'manual');
        $slot->description = sanitize_textarea_field($_POST['slot_description'] ?? '');
        $slot->status = sanitize_text_field($_POST['slot_status'] ?? 'active');

        // Handle settings based on type
        $settings = [];
        if ($slot->type === Slot::TYPE_AUTO_CONTENT) {
            $settings = [
                'paragraph_interval' => intval($_POST['paragraph_interval'] ?? 4),
                'max_insertions' => intval($_POST['max_insertions'] ?? 3),
                'skip_first_paragraphs' => intval($_POST['skip_first_paragraphs'] ?? 2),
                'min_paragraphs' => intval($_POST['min_paragraphs'] ?? 6),
            ];
        }

        // Per-slot role hiding (applies to all slot types)
        $hiddenRoles = isset($_POST['slot_hidden_roles'])
            ? array_map('sanitize_text_field', (array) $_POST['slot_hidden_roles'])
            : [];
        $settings['hidden_roles'] = $hiddenRoles;

        // Branded display setting
        $settings['show_branding'] = !empty($_POST['slot_show_branding']);

        $slot->settings = $settings;

        if ($isNew) {
            $this->slotRepository->create($slot);
        } else {
            $this->slotRepository->update($slot);
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'saved',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle delete slot action
     */
    public function handleDeleteSlot(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $slotId = intval($_GET['slot_id'] ?? 0);
        check_admin_referer('bbjd_delete_slot_' . $slotId);

        if ($slotId) {
            $this->slotRepository->delete($slotId);
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'deleted',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $slots = $this->slotRepository->findAll();
        $message = $_GET['message'] ?? '';
        $editSlotId = intval($_GET['edit'] ?? 0);
        $editSlot = $editSlotId ? $this->slotRepository->find($editSlotId) : null;
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Ad Slots
                </h1>

                <?php $this->renderMessages($message); ?>

                <div class="bbjd-grid bbjd-grid-cols-1 lg:bbjd-grid-cols-3 bbjd-gap-6">
                    <!-- Slot Form -->
                    <div class="lg:bbjd-col-span-1">
                        <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
                            <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                                <?php echo $editSlot ? 'Edit Slot' : 'Add New Slot'; ?>
                            </h2>

                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <?php wp_nonce_field('bbjd_save_slot'); ?>
                                <input type="hidden" name="action" value="bbjd_save_slot">
                                <input type="hidden" name="slot_id" value="<?php echo esc_attr($editSlot->id ?? 0); ?>">

                                <div class="bbjd-space-y-4">
                                    <div>
                                        <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">Name *</label>
                                        <input type="text" name="slot_name" value="<?php echo esc_attr($editSlot->name ?? ''); ?>" required
                                               class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                    </div>

                                    <div>
                                        <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">Slug</label>
                                        <input type="text" name="slot_slug" value="<?php echo esc_attr($editSlot->slug ?? ''); ?>"
                                               class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm"
                                               placeholder="auto-generated">
                                    </div>

                                    <div>
                                        <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">Type</label>
                                        <select name="slot_type" id="slot_type"
                                                class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                            <?php foreach (Slot::getTypes() as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($editSlot->type ?? '', $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">Status</label>
                                        <select name="slot_status"
                                                class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                            <?php foreach (Slot::getStatuses() as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($editSlot->status ?? 'active', $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">Description</label>
                                        <textarea name="slot_description" rows="2"
                                                  class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm"><?php echo esc_textarea($editSlot->description ?? ''); ?></textarea>
                                    </div>

                                    <!-- Branded display -->
                                    <div class="bbjd-flex bbjd-items-start bbjd-space-x-3 bbjd-pt-3 bbjd-border-t">
                                        <input type="checkbox"
                                               name="slot_show_branding"
                                               id="slot_show_branding"
                                               value="1"
                                               <?php checked($editSlot ? $editSlot->getSetting('show_branding', false) : false); ?>
                                               class="bbjd-rounded bbjd-border-gray-300 bbjd-text-primary500 bbjd-mt-1">
                                        <div>
                                            <label for="slot_show_branding" class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700">
                                                Branded Display
                                            </label>
                                            <p class="bbjd-text-xs bbjd-text-gray-500">
                                                Shows "Advertisement" header and "Go Ad-Free" footer around the ad slot.
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Per-slot role hiding -->
                                    <div class="bbjd-pt-3 bbjd-border-t">
                                        <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                            Hide This Slot for Roles
                                        </label>
                                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mb-2">
                                            Users with checked roles won't see this specific slot (in addition to global settings).
                                        </p>
                                        <div class="bbjd-grid bbjd-grid-cols-2 bbjd-gap-1 bbjd-max-h-32 bbjd-overflow-y-auto bbjd-bg-gray-50 bbjd-p-2 bbjd-rounded">
                                            <?php
                                            $wpRoles = wp_roles();
                                            $allRoles = $wpRoles->get_names();
                                            $slotHiddenRoles = $editSlot ? $editSlot->getHiddenRoles() : [];
                                            foreach ($allRoles as $roleSlug => $roleName):
                                            ?>
                                            <label class="bbjd-flex bbjd-items-center bbjd-space-x-1">
                                                <input type="checkbox"
                                                       name="slot_hidden_roles[]"
                                                       value="<?php echo esc_attr($roleSlug); ?>"
                                                       <?php checked(in_array($roleSlug, $slotHiddenRoles)); ?>
                                                       class="bbjd-rounded bbjd-border-gray-300 bbjd-text-primary500 bbjd-h-3 bbjd-w-3">
                                                <span class="bbjd-text-xs bbjd-text-gray-700"><?php echo esc_html($roleName); ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Auto-content settings -->
                                    <div id="auto_content_settings" class="bbjd-hidden bbjd-space-y-3 bbjd-pt-3 bbjd-border-t">
                                        <h4 class="bbjd-font-medium bbjd-text-gray-700">Auto-Insert Settings</h4>
                                        <?php $settings = $editSlot->settings ?? Slot::getAutoContentDefaults(); ?>
                                        <div class="bbjd-grid bbjd-grid-cols-2 bbjd-gap-3">
                                            <div>
                                                <label class="bbjd-block bbjd-text-xs bbjd-text-gray-600 bbjd-mb-1">Every X paragraphs</label>
                                                <input type="number" name="paragraph_interval" value="<?php echo esc_attr($settings['paragraph_interval'] ?? 4); ?>"
                                                       class="bbjd-w-full bbjd-px-2 bbjd-py-1 bbjd-border bbjd-border-gray-300 bbjd-rounded bbjd-text-sm">
                                            </div>
                                            <div>
                                                <label class="bbjd-block bbjd-text-xs bbjd-text-gray-600 bbjd-mb-1">Max insertions</label>
                                                <input type="number" name="max_insertions" value="<?php echo esc_attr($settings['max_insertions'] ?? 3); ?>"
                                                       class="bbjd-w-full bbjd-px-2 bbjd-py-1 bbjd-border bbjd-border-gray-300 bbjd-rounded bbjd-text-sm">
                                            </div>
                                            <div>
                                                <label class="bbjd-block bbjd-text-xs bbjd-text-gray-600 bbjd-mb-1">Skip first X paragraphs</label>
                                                <input type="number" name="skip_first_paragraphs" value="<?php echo esc_attr($settings['skip_first_paragraphs'] ?? 2); ?>"
                                                       class="bbjd-w-full bbjd-px-2 bbjd-py-1 bbjd-border bbjd-border-gray-300 bbjd-rounded bbjd-text-sm">
                                            </div>
                                            <div>
                                                <label class="bbjd-block bbjd-text-xs bbjd-text-gray-600 bbjd-mb-1">Min paragraphs required</label>
                                                <input type="number" name="min_paragraphs" value="<?php echo esc_attr($settings['min_paragraphs'] ?? 6); ?>"
                                                       class="bbjd-w-full bbjd-px-2 bbjd-py-1 bbjd-border bbjd-border-gray-300 bbjd-rounded bbjd-text-sm">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit"
                                            class="bbjd-w-full bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                                        <?php echo $editSlot ? 'Update Slot' : 'Create Slot'; ?>
                                    </button>

                                    <?php if ($editSlot): ?>
                                    <a href="<?php echo admin_url('admin.php?page=' . self::MENU_SLUG); ?>"
                                       class="bbjd-block bbjd-text-center bbjd-text-gray-500 hover:bbjd-text-gray-700 bbjd-text-sm">
                                        Cancel
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Slots List -->
                    <div class="lg:bbjd-col-span-2">
                        <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-overflow-hidden">
                            <?php if (empty($slots)): ?>
                                <div class="bbjd-p-8 bbjd-text-center">
                                    <p class="bbjd-text-gray-500">No slots found. Create your first slot to get started.</p>
                                </div>
                            <?php else: ?>
                                <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200">
                                    <thead class="bbjd-bg-gray-50">
                                        <tr>
                                            <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Name</th>
                                            <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Type</th>
                                            <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Ads</th>
                                            <th class="bbjd-px-4 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                                        <?php foreach ($slots as $slot): ?>
                                        <tr>
                                            <td class="bbjd-px-4 bbjd-py-3">
                                                <div class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-900"><?php echo esc_html($slot->name); ?></div>
                                                <div class="bbjd-text-xs bbjd-text-gray-500 bbjd-font-mono"><?php echo esc_html($slot->slug); ?></div>
                                            </td>
                                            <td class="bbjd-px-4 bbjd-py-3 bbjd-text-sm bbjd-text-gray-500">
                                                <?php echo esc_html(Slot::getTypes()[$slot->type] ?? $slot->type); ?>
                                            </td>
                                            <td class="bbjd-px-4 bbjd-py-3 bbjd-text-sm bbjd-text-gray-500">
                                                <?php echo $this->slotRepository->getAdCount($slot->id); ?>
                                            </td>
                                            <td class="bbjd-px-4 bbjd-py-3 bbjd-text-sm">
                                                <a href="<?php echo admin_url('admin.php?page=' . self::MENU_SLUG . '&edit=' . $slot->id); ?>"
                                                   class="bbjd-text-primary500 hover:bbjd-text-primaryHard bbjd-mr-3">Edit</a>
                                                <a href="<?php echo wp_nonce_url(
                                                    admin_url('admin-post.php?action=bbjd_delete_slot&slot_id=' . $slot->id),
                                                    'bbjd_delete_slot_' . $slot->id
                                                ); ?>"
                                                   class="bbjd-text-thirdColor hover:bbjd-text-red-700"
                                                   onclick="return confirm('Delete this slot?');">Delete</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- Usage Info -->
                        <div class="bbjd-mt-6 bbjd-bg-blue-50 bbjd-rounded-lg bbjd-p-4">
                            <h3 class="bbjd-font-medium bbjd-text-blue-800 bbjd-mb-2">Theme Usage</h3>
                            <p class="bbjd-text-sm bbjd-text-blue-700 bbjd-mb-2">Use these functions in your theme to display ads:</p>
                            <code class="bbjd-block bbjd-bg-blue-100 bbjd-px-3 bbjd-py-2 bbjd-rounded bbjd-text-sm bbjd-font-mono bbjd-text-blue-900">
                                &lt;?php bbjd_ad('slot_slug'); ?&gt;
                            </code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('slot_type');
            const autoSettings = document.getElementById('auto_content_settings');

            function toggleAutoSettings() {
                if (typeSelect.value === 'auto_content') {
                    autoSettings.classList.remove('bbjd-hidden');
                } else {
                    autoSettings.classList.add('bbjd-hidden');
                }
            }

            typeSelect.addEventListener('change', toggleAutoSettings);
            toggleAutoSettings();
        });
        </script>
        <?php
    }

    /**
     * Render messages
     */
    private function renderMessages(string $message): void
    {
        if ($message === 'saved') {
            ?>
            <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                <p>Slot saved successfully.</p>
            </div>
            <?php
        } elseif ($message === 'deleted') {
            ?>
            <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                <p>Slot deleted successfully.</p>
            </div>
            <?php
        }
    }
}

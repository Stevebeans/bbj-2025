<?php
/**
 * Admin pane: Comp Types CRUD.
 *
 * Lists all comp types, plus an Add form and per-row edit/archive form.
 * Posts to admin-post.php handlers in inc/template-functions.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_require_admin();

$types = bbj_v2_comp_types_all();
$msg   = isset($_GET['bbj_msg']) ? sanitize_key($_GET['bbj_msg']) : '';
$msg_text = [
    'saved'          => 'Comp type saved.',
    'toggled'        => 'Archive status updated.',
    'name_required'  => 'Name and slug are required.',
][$msg] ?? '';
?>

<div class="space-y-6">
  <header class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-stone-900 pb-4">
    <div>
      <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-stone-500">Game data</p>
      <h1 class="font-osw text-3xl text-primary-500">Comp Types</h1>
      <p class="mt-1 text-sm text-stone-600">Categories used when logging weekly comp wins. Add seasonal regulars as they appear.</p>
    </div>
  </header>

  <?php if ($msg_text): ?>
    <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
      <?php echo esc_html($msg_text); ?>
    </div>
  <?php endif; ?>

  <!-- Add new -->
  <section class="rounded-md bg-white border border-stone-200 p-5">
    <h2 class="font-osw text-lg text-primary-500 mb-3">Add new comp type</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="grid grid-cols-1 sm:grid-cols-[1fr_180px_120px_auto] gap-3 items-end">
      <?php wp_nonce_field('bbj_v2_save_comp_type'); ?>
      <input type="hidden" name="action" value="bbj_v2_save_comp_type">
      <label class="text-sm">
        <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Name</span>
        <input type="text" name="name" required class="w-full px-3 py-2 border border-stone-300 rounded text-sm" placeholder="e.g. Battle Back">
      </label>
      <label class="text-sm">
        <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Slug (auto if blank)</span>
        <input type="text" name="slug" class="w-full px-3 py-2 border border-stone-300 rounded text-sm font-mono" placeholder="auto-generated">
      </label>
      <label class="text-sm">
        <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Sort</span>
        <input type="number" name="sort_order" value="50" class="w-full px-3 py-2 border border-stone-300 rounded text-sm">
      </label>
      <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-osw uppercase tracking-wider rounded">Add</button>
    </form>
  </section>

  <!-- List -->
  <section class="rounded-md bg-white border border-stone-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-stone-50 border-b border-stone-200">
        <tr class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-600">
          <th class="text-left font-semibold px-5 py-2 w-[220px]">Name</th>
          <th class="text-left font-semibold px-5 py-2 w-[140px]">Slug</th>
          <th class="text-left font-semibold px-5 py-2 w-[100px]">Sort</th>
          <th class="text-left font-semibold px-5 py-2 w-[100px]">Status</th>
          <th class="text-right font-semibold px-5 py-2 w-[200px]">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($types)): ?>
          <tr><td colspan="5" class="px-5 py-6 text-center text-stone-500 italic">No comp types yet.</td></tr>
        <?php else: foreach ($types as $t): ?>
          <tr class="border-t border-stone-100 align-top">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="contents">
              <?php wp_nonce_field('bbj_v2_save_comp_type'); ?>
              <input type="hidden" name="action" value="bbj_v2_save_comp_type">
              <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
              <input type="hidden" name="slug" value="<?php echo esc_attr($t['slug']); ?>">
              <td class="px-5 py-2"><input type="text" name="name" value="<?php echo esc_attr($t['name']); ?>" class="w-full px-2 py-1 border border-stone-300 rounded text-sm"></td>
              <td class="px-5 py-2 font-mono text-[12px] text-stone-700"><?php echo esc_html($t['slug']); ?></td>
              <td class="px-5 py-2"><input type="number" name="sort_order" value="<?php echo (int) $t['sort_order']; ?>" class="w-20 px-2 py-1 border border-stone-300 rounded text-sm"></td>
              <td class="px-5 py-2">
                <?php if ((int) $t['is_archived'] === 1): ?>
                  <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-stone-100 text-stone-700">Archived</span>
                <?php else: ?>
                  <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-green-100 text-green-900">Active</span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-2 text-right">
                <button type="submit" class="px-3 py-1 bg-primary-500 text-white text-xs font-osw uppercase tracking-wider rounded">Save</button>
            </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
                  <?php wp_nonce_field('bbj_v2_toggle_comp_type'); ?>
                  <input type="hidden" name="action" value="bbj_v2_toggle_comp_type">
                  <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                  <button type="submit" class="px-3 py-1 bg-stone-100 text-stone-700 text-xs font-osw uppercase tracking-wider rounded">
                    <?php echo (int) $t['is_archived'] === 1 ? 'Unarchive' : 'Archive'; ?>
                  </button>
                </form>
              </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </section>
</div>

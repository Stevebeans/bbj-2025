<?php 

$can_edit = current_user_can('edit-seasons-players') ?>

<!-- admin-sidebar.php -->
<div class="admin-sidebar">
  <div class="admin-link"><a href="?page=bbj-tools">Home</a></div>
  <?= $isAdmin ? '<div class="admin-link"><a href="?page=bbj-tools-seasons">Seasons</a></div>' : '' ?>
</div>

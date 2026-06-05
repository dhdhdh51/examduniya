<?php
$current_uri = $_SERVER['REQUEST_URI'] ?? '/';
$site_name   = get_setting('site_name') ?: 'GovExam Portal';
$admin_name  = $_SESSION['name'] ?? 'Admin';
$admin_avatar= $_SESSION['avatar'] ?? '';

function admin_nav_active($path, $current)
{
    return (strpos($current, $path) !== false) ? 'active' : '';
}
?>
<!-- Mobile open button -->
<button id="sidebarToggle" class="admin-sidebar-toggle btn btn-dark d-lg-none" type="button" aria-label="Toggle menu">
  <i class="fa-solid fa-bars"></i>
</button>
<!-- Desktop reopen button (shown only when sidebar is collapsed) -->
<button id="sidebarOpenDesktop" class="admin-sidebar-open-desktop btn btn-dark d-none d-lg-flex" type="button" aria-label="Open menu" title="Open menu">
  <i class="fa-solid fa-bars"></i>
</button>
<nav id="adminSidebar" class="admin-sidebar d-flex flex-column">
  <!-- Brand -->
  <div class="admin-sidebar-brand px-3 py-3 border-bottom border-secondary d-flex align-items-center justify-content-between">
    <a href="/admin/" class="text-white text-decoration-none d-flex align-items-center gap-2">
      <i class="fa-solid fa-graduation-cap text-primary fs-5"></i>
      <span class="fw-bold"><?= htmlspecialchars($site_name) ?></span>
    </a>
    <!-- Desktop collapse button -->
    <button id="sidebarCollapseDesktop" class="btn btn-sm btn-outline-light border-0 d-none d-lg-inline-flex p-1" type="button" aria-label="Collapse menu" title="Hide menu">
      <i class="fa-solid fa-angles-left"></i>
    </button>
  </div>

  <!-- Admin profile -->
  <div class="px-3 py-3 border-bottom border-secondary">
    <div class="d-flex align-items-center gap-2">
      <?php if ($admin_avatar): ?>
        <img src="/uploads/avatars/<?= htmlspecialchars($admin_avatar) ?>"
             class="rounded-circle" width="36" height="36" alt="avatar">
      <?php else: ?>
        <div class="admin-avatar-placeholder rounded-circle bg-primary d-flex align-items-center justify-content-center"
             style="width:36px;height:36px;">
          <i class="fa-solid fa-user text-white small"></i>
        </div>
      <?php endif; ?>
      <div>
        <div class="text-white fw-semibold small"><?= htmlspecialchars($admin_name) ?></div>
        <span class="badge bg-warning text-dark" style="font-size:10px;">Admin</span>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <div class="admin-nav flex-fill overflow-auto py-2">
    <ul class="nav flex-column px-2">

      <!-- Dashboard -->
      <li class="nav-item">
        <a class="nav-link <?= admin_nav_active('/admin/index', $current_uri) ?>"
           href="/admin/">
          <i class="fa-solid fa-gauge me-2"></i>Dashboard
        </a>
      </li>

      <!-- Notifications section -->
      <li class="nav-item mt-2">
        <button class="btn nav-link text-start w-100 d-flex align-items-center justify-content-between"
                type="button" data-bs-toggle="collapse" data-bs-target="#collapseNotif"
                aria-expanded="<?= (strpos($current_uri, '/admin/notifications') !== false) ? 'true' : 'false' ?>">
          <span><i class="fa-solid fa-bell me-2"></i>Notifications</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </button>
        <div class="collapse <?= (strpos($current_uri, '/admin/notifications') !== false) ? 'show' : '' ?>"
             id="collapseNotif">
          <ul class="nav flex-column ps-3">
            <li class="nav-item">
              <a class="nav-link <?= admin_nav_active('/admin/notifications/index', $current_uri) ?>"
                 href="/admin/notifications/">
                <i class="fa-regular fa-list-alt me-2"></i>All Notifications
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= admin_nav_active('/admin/notifications/add', $current_uri) ?>"
                 href="/admin/notifications/add.php">
                <i class="fa-solid fa-plus me-2"></i>Add New
              </a>
            </li>
          </ul>
        </div>
      </li>

      <!-- Mock Tests section -->
      <li class="nav-item mt-1">
        <button class="btn nav-link text-start w-100 d-flex align-items-center justify-content-between"
                type="button" data-bs-toggle="collapse" data-bs-target="#collapseTests"
                aria-expanded="<?= (strpos($current_uri, '/admin/tests') !== false) ? 'true' : 'false' ?>">
          <span><i class="fa-solid fa-file-pen me-2"></i>Mock Tests</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </button>
        <div class="collapse <?= (strpos($current_uri, '/admin/tests') !== false) ? 'show' : '' ?>"
             id="collapseTests">
          <ul class="nav flex-column ps-3">
            <li class="nav-item">
              <a class="nav-link <?= admin_nav_active('/admin/tests/index', $current_uri) ?>"
                 href="/admin/tests/">
                <i class="fa-regular fa-list-alt me-2"></i>All Tests
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= admin_nav_active('/admin/tests/add', $current_uri) ?>"
                 href="/admin/tests/add.php">
                <i class="fa-solid fa-plus me-2"></i>Add Test
              </a>
            </li>
          </ul>
        </div>
      </li>

      <!-- Blog section -->
      <li class="nav-item mt-1">
        <button class="btn nav-link text-start w-100 d-flex align-items-center justify-content-between"
                type="button" data-bs-toggle="collapse" data-bs-target="#collapseBlogs"
                aria-expanded="<?= (strpos($current_uri, '/admin/blogs') !== false) ? 'true' : 'false' ?>">
          <span><i class="fa-solid fa-newspaper me-2"></i>Blogs</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </button>
        <div class="collapse <?= (strpos($current_uri, '/admin/blogs') !== false) ? 'show' : '' ?>"
             id="collapseBlogs">
          <ul class="nav flex-column ps-3">
            <li class="nav-item">
              <a class="nav-link <?= admin_nav_active('/admin/blogs/index', $current_uri) ?>"
                 href="/admin/blogs/">
                <i class="fa-regular fa-list-alt me-2"></i>All Posts
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= admin_nav_active('/admin/blogs/add', $current_uri) ?>"
                 href="/admin/blogs/add.php">
                <i class="fa-solid fa-plus me-2"></i>New Post
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= admin_nav_active('/admin/comments', $current_uri) ?>"
                 href="/admin/comments/">
                <i class="fa-regular fa-comments me-2"></i>Comments
              </a>
            </li>
          </ul>
        </div>
      </li>

      <!-- Users -->
      <li class="nav-item mt-1">
        <a class="nav-link <?= admin_nav_active('/admin/users', $current_uri) ?>"
           href="/admin/users/">
          <i class="fa-solid fa-users me-2"></i>Users
        </a>
      </li>

      <!-- Payments -->
      <li class="nav-item mt-1">
        <a class="nav-link <?= admin_nav_active('/admin/payments', $current_uri) ?>"
           href="/admin/payments/">
          <i class="fa-solid fa-credit-card me-2"></i>Payments
        </a>
      </li>

      <!-- Settings -->
      <li class="nav-item mt-1">
        <a class="nav-link <?= admin_nav_active('/admin/ai', $current_uri) ?>"
           href="/admin/ai/">
          <i class="fa-solid fa-robot me-2"></i>AI Providers
        </a>
      </li>

      <!-- Settings -->
      <li class="nav-item mt-1">
        <a class="nav-link <?= admin_nav_active('/admin/settings', $current_uri) ?>"
           href="/admin/settings.php">
          <i class="fa-solid fa-gear me-2"></i>Settings
        </a>
      </li>

    </ul>
  </div>

  <!-- Sidebar footer -->
  <div class="px-3 py-3 border-top border-secondary">
    <a href="/" class="btn btn-sm btn-outline-secondary w-100 mb-2" target="_blank">
      <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Site
    </a>
    <a href="/auth/logout.php" class="btn btn-sm btn-outline-danger w-100">
      <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
    </a>
  </div>
</nav>

<!-- Sidebar toggle overlay for mobile -->
<div id="sidebarOverlay" class="sidebar-overlay d-lg-none"></div>

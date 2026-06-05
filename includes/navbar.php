<?php
$site_name  = get_setting('site_name') ?: 'GovExam Portal';
$current    = $_SERVER['REQUEST_URI'] ?? '/';
$is_logged  = !empty($_SESSION['user_id']);
$user_name  = $is_logged ? ($_SESSION['name'] ?? 'User') : '';
$user_role  = $is_logged ? ($_SESSION['role'] ?? 'free') : '';
$user_avatar= $is_logged ? ($_SESSION['avatar'] ?? '') : '';
?>
<nav class="navbar navbar-expand-lg sticky-top navbar-light bg-white border-bottom shadow-sm">
  <div class="container">
    <!-- Brand / Logo -->
    <a class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" href="/">
      <i class="fa-solid fa-graduation-cap fs-4"></i>
      <span><?= htmlspecialchars($site_name) ?></span>
    </a>

    <!-- Mobile toggle -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
            aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">
      <!-- Main navigation links -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link<?= ($current === '/') ? ' active fw-semibold' : '' ?>" href="/">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= (strpos($current, '/pages/exams') === 0) ? ' active fw-semibold' : '' ?>"
             href="/pages/exams/">Exams</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle<?= (strpos($current, '/pages/exams') === 0) ? ' active' : '' ?>"
             href="#" data-bs-toggle="dropdown">Categories</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/pages/exams/?cat=SSC"><i class="fa-solid fa-briefcase me-2 text-primary"></i>SSC</a></li>
            <li><a class="dropdown-item" href="/pages/exams/?cat=UPSC"><i class="fa-solid fa-landmark me-2 text-primary"></i>UPSC</a></li>
            <li><a class="dropdown-item" href="/pages/exams/?cat=Railway"><i class="fa-solid fa-train me-2 text-primary"></i>Railway</a></li>
            <li><a class="dropdown-item" href="/pages/exams/?cat=Banking"><i class="fa-solid fa-building-columns me-2 text-primary"></i>Banking</a></li>
            <li><a class="dropdown-item" href="/pages/exams/?cat=StatePSC"><i class="fa-solid fa-flag me-2 text-primary"></i>State PSC</a></li>
            <li><a class="dropdown-item" href="/pages/exams/?cat=Defence"><i class="fa-solid fa-shield me-2 text-primary"></i>Defence</a></li>
            <li><a class="dropdown-item" href="/pages/exams/?cat=Other"><i class="fa-solid fa-ellipsis me-2 text-primary"></i>Other</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= (strpos($current, '/pages/tests') === 0) ? ' active fw-semibold' : '' ?>"
             href="/pages/tests/">Mock Tests</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= (strpos($current, '/pages/blog') === 0) ? ' active fw-semibold' : '' ?>"
             href="/pages/blog/">Blog</a>
        </li>
      </ul>

      <!-- Right side -->
      <div class="d-flex align-items-center gap-2">
        <!-- Search button -->
        <button class="btn btn-sm btn-outline-secondary" type="button"
                data-bs-toggle="modal" data-bs-target="#searchModal" aria-label="Search">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>

        <?php if ($is_logged): ?>
          <!-- User avatar dropdown -->
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle d-flex align-items-center gap-2"
                    data-bs-toggle="dropdown">
              <?php if ($user_avatar): ?>
                <img src="/uploads/avatars/<?= htmlspecialchars($user_avatar) ?>"
                     class="rounded-circle" width="24" height="24" alt="avatar">
              <?php else: ?>
                <i class="fa-solid fa-circle-user"></i>
              <?php endif; ?>
              <span class="d-none d-md-inline"><?= htmlspecialchars($user_name) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/pages/dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="/pages/profile.php"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
              <?php if ($user_role === 'admin'): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-warning" href="/admin/"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Admin Panel</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="/auth/login.php" class="btn btn-sm btn-outline-primary">Login</a>
          <a href="/auth/register.php" class="btn btn-sm btn-primary">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-label="Search">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Exams</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="search" id="globalSearch" class="form-control form-control-lg"
               placeholder="Type to search exams, tests, blogs..." autocomplete="off">
        <div id="searchResults" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>

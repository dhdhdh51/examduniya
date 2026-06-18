<?php
$site_name = get_setting('site_name') ?: 'Exam Duniya';
$site_url  = get_setting('site_url') ?: '#';
$footer_disclaimer = get_setting('footer_disclaimer')
    ?: 'Exam Duniya is an independent education and exam information platform. We are not affiliated with any government recruitment board. Candidates must verify all important details from the official website before applying.';
?>
</div><!-- /.main-content -->

<footer class="bg-dark text-light py-5 mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4 col-md-6">
        <h5 class="fw-bold mb-3">
          <i class="fa-solid fa-graduation-cap text-primary me-2"></i>
          <?= htmlspecialchars($site_name) ?>
        </h5>
        <p class="text-secondary small">
          Your trusted destination for Government exam notifications, free mock tests,
          and comprehensive study material.
        </p>
        <div class="d-flex gap-3 mt-3">
          <a href="#" class="text-secondary fs-5" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
          <a href="#" class="text-secondary fs-5" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" class="text-secondary fs-5" aria-label="Telegram"><i class="fab fa-telegram"></i></a>
          <a href="#" class="text-secondary fs-5" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
      </div>
      <div class="col-lg-2 col-md-3 col-6">
        <h6 class="fw-semibold mb-3 text-light">Exams</h6>
        <ul class="list-unstyled small">
          <li class="mb-1"><a href="/pages/exams/?cat=SSC" class="text-secondary text-decoration-none">SSC</a></li>
          <li class="mb-1"><a href="/pages/exams/?cat=UPSC" class="text-secondary text-decoration-none">UPSC</a></li>
          <li class="mb-1"><a href="/pages/exams/?cat=Railway" class="text-secondary text-decoration-none">Railway</a></li>
          <li class="mb-1"><a href="/pages/exams/?cat=Banking" class="text-secondary text-decoration-none">Banking</a></li>
          <li class="mb-1"><a href="/pages/exams/?cat=Defence" class="text-secondary text-decoration-none">Defence</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-md-3 col-6">
        <h6 class="fw-semibold mb-3 text-light">Quick Links</h6>
        <ul class="list-unstyled small">
          <li class="mb-1"><a href="/" class="text-secondary text-decoration-none">Home</a></li>
          <li class="mb-1"><a href="/pages/tests/" class="text-secondary text-decoration-none">Mock Tests</a></li>
          <li class="mb-1"><a href="/pages/blog/" class="text-secondary text-decoration-none">Blog</a></li>
          <li class="mb-1"><a href="/auth/login.php" class="text-secondary text-decoration-none">Login</a></li>
          <li class="mb-1"><a href="/auth/signup.php" class="text-secondary text-decoration-none">Register</a></li>
        </ul>
      </div>
      <div class="col-lg-4 col-md-6">
        <h6 class="fw-semibold mb-3 text-light">Stay Updated</h6>
        <p class="text-secondary small mb-2">Get the latest exam notifications directly.</p>
        <a href="https://t.me/" class="btn btn-sm btn-outline-light">
          <i class="fab fa-telegram me-1"></i> Join Telegram
        </a>
        <hr class="border-secondary mt-4">
        <ul class="list-inline small text-secondary mb-0">
          <li class="list-inline-item"><a href="/pages/about.php" class="text-secondary text-decoration-none">About</a></li>
          <li class="list-inline-item"><a href="/pages/contact.php" class="text-secondary text-decoration-none">Contact</a></li>
          <li class="list-inline-item"><a href="/pages/privacy.php" class="text-secondary text-decoration-none">Privacy</a></li>
          <li class="list-inline-item"><a href="/pages/terms.php" class="text-secondary text-decoration-none">Terms</a></li>
          <li class="list-inline-item"><a href="/pages/refund.php" class="text-secondary text-decoration-none">Refund Policy</a></li>
          <li class="list-inline-item"><a href="/pages/shipping.php" class="text-secondary text-decoration-none">Shipping &amp; Delivery</a></li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary mt-4">
    <p class="text-center text-secondary small mb-2" style="max-width:900px;margin-inline:auto;">
      <?= htmlspecialchars($footer_disclaimer) ?>
    </p>
    <ul class="list-inline small text-center text-secondary mb-2">
      <li class="list-inline-item"><a href="/about-exam-duniya/" class="text-secondary text-decoration-none">About Exam Duniya</a></li>
      <li class="list-inline-item"><a href="/editorial-policy/" class="text-secondary text-decoration-none">Editorial Policy</a></li>
      <li class="list-inline-item"><a href="/fact-check-policy/" class="text-secondary text-decoration-none">Fact-Check Policy</a></li>
      <li class="list-inline-item"><a href="/correction-policy/" class="text-secondary text-decoration-none">Correction Policy</a></li>
      <li class="list-inline-item"><a href="/contact/" class="text-secondary text-decoration-none">Contact</a></li>
    </ul>
    <p class="text-center text-secondary small mb-0">
      &copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. All rights reserved.
    </p>
  </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Custom JS -->
<script src="<?= asset('/assets/js/main.js') ?>"></script>

</body>
</html>

<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();
maintenance_mode_check();

$test_id = (int)($_GET['test_id'] ?? 0);
if (!$test_id) {
    header('Location: /pages/tests/listing.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM mock_tests WHERE id = ? AND is_active = 1");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    http_response_code(404);
    include ROOT . '/404.php';
    exit;
}

// Free tests go directly to attempt
if ($test['access_type'] === 'free') {
    header('Location: /pages/tests/attempt.php?test_id=' . $test_id);
    exit;
}

// Check if user has an active premium plan
$stmt = $pdo->prepare("SELECT id FROM payments WHERE user_id = ? AND status = 'success' LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetch()) {
    header('Location: /pages/tests/attempt.php?test_id=' . $test_id);
    exit;
}

$monthly_price = (float)(get_setting('plan_monthly_price') ?: 99);
$yearly_price  = (float)(get_setting('plan_yearly_price') ?: 799);
$site_name     = get_setting('site_name') ?: 'GovExam Portal';

$page_title = 'Get Premium Access — ' . htmlspecialchars($test['title']);
require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>

<div class="container my-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/tests/listing.php">Mock Tests</a></li>
            <li class="breadcrumb-item active">Get Access</li>
        </ol>
    </nav>

    <?php if (!empty($_SESSION['payment_error'])): ?>
        <div class="alert alert-danger alert-dismissible auto-dismiss" role="alert">
            <i class="fa-solid fa-circle-xmark me-2"></i><?= htmlspecialchars($_SESSION['payment_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['payment_error']); ?>
    <?php endif; ?>

    <div class="row justify-content-center g-4">

        <!-- Order Summary -->
        <div class="col-lg-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-clipboard-list me-2"></i>Test Access Required</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-semibold"><?= htmlspecialchars($test['title']) ?></h6>
                    <p class="text-muted small mb-3"><?= htmlspecialchars(excerpt($test['description'] ?? '', 120)) ?></p>
                    <div class="d-flex flex-wrap gap-3 text-muted small mb-4">
                        <span><i class="fas fa-question-circle me-1"></i><?= (int)$test['total_questions'] ?> Questions</span>
                        <span><i class="fas fa-clock me-1"></i><?= (int)$test['duration_minutes'] ?> minutes</span>
                        <span><i class="fas fa-tag me-1"></i><?= htmlspecialchars($test['category'] ?? '') ?></span>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-lock me-2"></i>
                        This is a <strong>Premium</strong> test. Subscribe to get unlimited access to all premium tests.
                    </div>
                </div>
            </div>

            <!-- Subscription Plans -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-crown text-warning me-2"></i>Choose Your Plan</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">

                        <!-- Monthly Plan -->
                        <div class="col-6">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <h6 class="fw-semibold">Monthly</h6>
                                    <div class="display-6 text-primary fw-bold">&#8377;<?= number_format($monthly_price, 0) ?></div>
                                    <div class="text-muted small">per month</div>
                                    <ul class="list-unstyled small text-start mt-3">
                                        <li><i class="fa-solid fa-check text-success me-2"></i>All premium tests</li>
                                        <li><i class="fa-solid fa-check text-success me-2"></i>Detailed analytics</li>
                                        <li><i class="fa-solid fa-check text-success me-2"></i>PDF reports</li>
                                    </ul>
                                    <form method="POST" action="/api/payu-initiate.php" class="mt-3">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="test_id" value="<?= $test_id ?>">
                                        <input type="hidden" name="plan" value="monthly">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                            <i class="fa-solid fa-credit-card me-1"></i>Buy Monthly
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Yearly Plan -->
                        <div class="col-6">
                            <div class="card border-success h-100 position-relative">
                                <span class="position-absolute top-0 start-50 translate-middle badge bg-success" style="font-size:0.65rem;">BEST VALUE</span>
                                <div class="card-body text-center">
                                    <h6 class="fw-semibold">Yearly</h6>
                                    <div class="display-6 text-success fw-bold">&#8377;<?= number_format($yearly_price, 0) ?></div>
                                    <div class="text-muted small">per year</div>
                                    <ul class="list-unstyled small text-start mt-3">
                                        <li><i class="fa-solid fa-check text-success me-2"></i>All premium tests</li>
                                        <li><i class="fa-solid fa-check text-success me-2"></i>Detailed analytics</li>
                                        <li><i class="fa-solid fa-check text-success me-2"></i>PDF reports</li>
                                    </ul>
                                    <form method="POST" action="/api/payu-initiate.php" class="mt-3">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="test_id" value="<?= $test_id ?>">
                                        <input type="hidden" name="plan" value="yearly">
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fa-solid fa-crown me-1"></i>Buy Yearly
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- User Details -->
                    <div class="border rounded p-3 bg-light mb-3">
                        <h6 class="fw-semibold small mb-2"><i class="fa-solid fa-user me-2 text-primary"></i>Your Account</h6>
                        <div class="row small">
                            <div class="col-4 text-muted">Name:</div>
                            <div class="col-8"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></div>
                            <div class="col-4 text-muted">Email:</div>
                            <div class="col-8"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                        </div>
                    </div>

                    <!-- Security Badges -->
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <span class="badge bg-secondary"><i class="fa-solid fa-shield-halved me-1"></i>Secure Payment via PayU</span>
                        <span class="badge bg-info text-dark"><i class="fa-solid fa-bolt me-1"></i>Instant Access</span>
                        <span class="badge bg-success"><i class="fa-solid fa-rotate-left me-1"></i>Money Back Guarantee</span>
                    </div>
                    <p class="text-center text-muted small mt-3 mb-0">
                        Powered by <strong>PayU</strong> &mdash; India's trusted payment gateway
                    </p>
                </div>
            </div>

        </div>

    </div>
</div>

<div class="main-content" style="display:none;"></div>

<?php require_once ROOT . '/includes/footer.php'; ?>

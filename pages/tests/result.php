<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();
maintenance_mode_check();

$attempt_id = (int)($_GET['attempt_id'] ?? 0);
if (!$attempt_id) {
    header('Location: /pages/tests/listing.php');
    exit;
}

// Fetch attempt — must belong to current user
$stmt = $pdo->prepare("SELECT * FROM user_attempts WHERE id = ? AND user_id = ?");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$attempt) {
    http_response_code(404);
    include ROOT . '/404.php';
    exit;
}

// Fetch test
$stmt = $pdo->prepare("SELECT * FROM mock_tests WHERE id = ?");
$stmt->execute([$attempt['test_id']]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    http_response_code(404);
    include ROOT . '/404.php';
    exit;
}

// Parse
$questions   = [];
if (!empty($test['questions_json'])) {
    $questions = json_decode($test['questions_json'], true) ?: [];
}
$answers_given = [];
if (!empty($attempt['answers_json'])) {
    $answers_given = json_decode($attempt['answers_json'], true) ?: [];
}

$score       = (float)($attempt['score'] ?? 0);
$total_marks = (float)($attempt['total_marks'] ?? count($questions) * (float)($test['marks_per_question'] ?? 1));
$correct     = (int)($attempt['correct_count'] ?? 0);
$wrong       = (int)($attempt['wrong_count'] ?? 0);
$unattempted = (int)($attempt['unanswered_count'] ?? 0);
$percentage  = $total_marks > 0 ? round(($score / $total_marks) * 100, 2) : 0;
$time_taken  = (int)($attempt['time_taken_seconds'] ?? 0);

// Recalculate rank and percentile live
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE test_id = ? AND score > ? AND completed_at IS NOT NULL");
$stmt->execute([$attempt['test_id'], $score]);
$rank = (int)$stmt->fetchColumn() + 1;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE test_id = ? AND completed_at IS NOT NULL");
$stmt->execute([$attempt['test_id']]);
$total_attempts = (int)$stmt->fetchColumn();
$percentile = $total_attempts > 0
    ? round((($total_attempts - $rank + 1) / $total_attempts) * 100, 2)
    : 100.0;

// Format time taken
$tt_hours = intdiv($time_taken, 3600);
$tt_mins  = intdiv($time_taken % 3600, 60);
$tt_secs  = $time_taken % 60;
$time_str = ($tt_hours > 0 ? $tt_hours . 'h ' : '') . $tt_mins . 'm ' . $tt_secs . 's';

$completed_at = !empty($attempt['completed_at']) ? format_date($attempt['completed_at'], 'd M Y, h:i A') : 'N/A';

$page_title = 'Test Result — ' . htmlspecialchars($test['title']);
require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';
?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item"><a href="/pages/tests/listing.php">Mock Tests</a></li>
            <li class="breadcrumb-item active">Result</li>
        </ol>
    </nav>

    <?php if (!empty($_SESSION['payment_success'])): ?>
        <div class="alert alert-success alert-dismissible auto-dismiss">
            <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['payment_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['payment_success']); ?>
    <?php endif; ?>

    <!-- Test title -->
    <h4 class="fw-bold mb-1"><?= htmlspecialchars($test['title']) ?></h4>
    <p class="text-muted small mb-4">Completed on <?= $completed_at ?></p>

    <div class="row g-4">

        <!-- Score Card -->
        <div class="col-lg-4">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <div style="width:140px;height:140px;position:relative;margin:0 auto 1rem;">
                        <canvas id="scoreChart"></canvas>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <div class="fw-bold" style="font-size:1.5rem;line-height:1;"><?= number_format($score, 1) ?></div>
                            <div class="text-muted" style="font-size:.75rem;">/ <?= number_format($total_marks, 1) ?></div>
                        </div>
                    </div>
                    <div class="display-6 fw-bold <?= $percentage >= 60 ? 'text-success' : ($percentage >= 35 ? 'text-warning' : 'text-danger') ?>">
                        <?= $percentage ?>%
                    </div>
                    <div class="text-muted small mt-1">Overall Score</div>
                    <span class="badge mt-2 <?= $percentage >= 60 ? 'bg-success' : ($percentage >= 35 ? 'bg-warning text-dark' : 'bg-danger') ?> fs-6">
                        <?= $percentage >= 60 ? 'PASS' : 'NEEDS IMPROVEMENT' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="col-lg-5">
            <div class="row g-3 h-100">
                <div class="col-6">
                    <div class="card text-center border-success h-100">
                        <div class="card-body">
                            <div class="display-5 fw-bold text-success"><?= $correct ?></div>
                            <div class="text-muted small mt-1"><i class="fa-solid fa-check-circle text-success me-1"></i>Correct</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center border-danger h-100">
                        <div class="card-body">
                            <div class="display-5 fw-bold text-danger"><?= $wrong ?></div>
                            <div class="text-muted small mt-1"><i class="fa-solid fa-times-circle text-danger me-1"></i>Wrong</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center border-secondary h-100">
                        <div class="card-body">
                            <div class="display-5 fw-bold text-secondary"><?= $unattempted ?></div>
                            <div class="text-muted small mt-1"><i class="fa-solid fa-minus-circle text-secondary me-1"></i>Unattempted</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center border-info h-100">
                        <div class="card-body">
                            <div class="display-5 fw-bold text-info"><?= $time_str ?></div>
                            <div class="text-muted small mt-1"><i class="fa-solid fa-clock text-info me-1"></i>Time Taken</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rank Card -->
        <div class="col-lg-3">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    <i class="fa-solid fa-trophy fa-3x text-warning mb-3"></i>
                    <div class="display-4 fw-bold">#<?= $rank ?></div>
                    <div class="text-muted small mb-3">Your Rank</div>
                    <div class="badge bg-primary fs-6">
                        <?= $percentile ?>th Percentile
                    </div>
                    <div class="text-muted small mt-2">
                        Among <?= number_format($total_attempts) ?> attempts
                    </div>
                    <a href="/pages/tests/result-pdf.php?attempt_id=<?= $attempt_id ?>"
                       class="btn btn-outline-danger btn-sm mt-3 w-100">
                        <i class="fa-solid fa-file-pdf me-1"></i>Download PDF
                    </a>
                </div>
            </div>
        </div>

    </div><!-- /row -->

    <!-- Charts row -->
    <div class="row g-4 mt-2">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Answer Distribution</h6></div>
                <div class="card-body" style="height:260px;">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="mb-0"><i class="fa-solid fa-chart-bar me-2 text-primary"></i>Score Breakdown</h6></div>
                <div class="card-body" style="height:260px;">
                    <canvas id="breakdownChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Answer Key Table -->
    <div class="card shadow-sm mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fa-solid fa-key me-2 text-primary"></i>Answer Key &amp; Explanations</h6>
            <span class="badge bg-secondary"><?= count($questions) ?> Questions</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Question</th>
                        <th style="width:90px;">Your Answer</th>
                        <th style="width:90px;">Correct</th>
                        <th style="width:90px;">Status</th>
                        <th>Explanation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $i => $q):
                        $user_ans    = isset($answers_given[$i]) ? strtoupper(trim((string)$answers_given[$i])) : '';
                        $correct_ans = isset($q['correct']) ? strtoupper(trim((string)$q['correct'])) : '';
                        $is_correct  = ($user_ans !== '' && $user_ans === $correct_ans);
                        $is_wrong    = ($user_ans !== '' && $user_ans !== $correct_ans);
                        $row_class   = $is_correct ? 'table-success' : ($is_wrong ? 'table-danger' : '');
                        $q_text      = strip_tags($q['question'] ?? $q['text'] ?? '');
                    ?>
                        <tr class="<?= $row_class ?>">
                            <td class="fw-semibold"><?= $i + 1 ?></td>
                            <td>
                                <span title="<?= htmlspecialchars($q_text) ?>">
                                    <?= htmlspecialchars(mb_substr($q_text, 0, 80)) ?><?= mb_strlen($q_text) > 80 ? '...' : '' ?>
                                </span>
                            </td>
                            <td class="text-center fw-bold">
                                <?= $user_ans !== '' ? htmlspecialchars($user_ans) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-center fw-bold text-success">
                                <?= htmlspecialchars($correct_ans) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($is_correct): ?>
                                    <span class="badge bg-success">Correct</span>
                                <?php elseif ($is_wrong): ?>
                                    <span class="badge bg-danger">Wrong</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Skipped</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?= htmlspecialchars(excerpt($q['explanation'] ?? '', 120)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Action buttons -->
    <div class="d-flex gap-3 justify-content-center mt-4 mb-5">
        <a href="/pages/tests/listing.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-list me-1"></i>More Tests
        </a>
        <a href="/pages/user/dashboard.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-gauge me-1"></i>Dashboard
        </a>
        <a href="/pages/tests/result-pdf.php?attempt_id=<?= $attempt_id ?>" class="btn btn-danger">
            <i class="fa-solid fa-file-pdf me-1"></i>Download PDF Report
        </a>
    </div>

</div><!-- /container -->

<div class="main-content" style="display:none;"></div>

<?php require_once ROOT . '/includes/footer.php'; ?>

<script>
'use strict';
// Chart data from PHP
var chartData = {
    correct:     <?= (int)$correct ?>,
    wrong:       <?= (int)$wrong ?>,
    unattempted: <?= (int)$unattempted ?>,
    score:       <?= (float)$score ?>,
    totalMarks:  <?= (float)$total_marks ?>
};

document.addEventListener('DOMContentLoaded', function() {
    // Donut / Score chart (in the score card — small)
    var scoreCanvas = document.getElementById('scoreChart');
    if (scoreCanvas && typeof Chart !== 'undefined') {
        new Chart(scoreCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [chartData.score, Math.max(0, chartData.totalMarks - chartData.score)],
                    backgroundColor: [
                        <?= $percentage >= 60 ? "'#059669'" : ($percentage >= 35 ? "'#D97706'" : "'#DC2626'") ?>,
                        '#e2e8f0'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '75%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });
    }

    // Doughnut distribution chart
    var distCanvas = document.getElementById('distributionChart');
    if (distCanvas && typeof Chart !== 'undefined') {
        new Chart(distCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Correct', 'Wrong', 'Unattempted'],
                datasets: [{
                    data: [chartData.correct, chartData.wrong, chartData.unattempted],
                    backgroundColor: ['#059669', '#DC2626', '#94a3b8'],
                    borderWidth: 2
                }]
            },
            options: {
                cutout: '60%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 } } }
                }
            }
        });
    }

    // Bar chart: score breakdown
    var barCanvas = document.getElementById('breakdownChart');
    if (barCanvas && typeof Chart !== 'undefined') {
        new Chart(barCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Correct', 'Wrong', 'Unattempted', 'Total'],
                datasets: [{
                    label: 'Questions',
                    data: [chartData.correct, chartData.wrong, chartData.unattempted,
                           chartData.correct + chartData.wrong + chartData.unattempted],
                    backgroundColor: ['#059669', '#DC2626', '#94a3b8', '#2563EB'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>

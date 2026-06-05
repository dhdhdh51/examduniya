<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/SimplePDF.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

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

// Parse questions & answers
$questions = [];
if (!empty($test['questions_json'])) {
    $questions = json_decode($test['questions_json'], true) ?: [];
}
$answers_given = [];
if (!empty($attempt['answers_json'])) {
    $answers_given = json_decode($attempt['answers_json'], true) ?: [];
}

// Scoring values
$score       = (float)($attempt['score'] ?? 0);
$total_marks = (float)($attempt['total_marks'] ?? count($questions) * (float)($test['marks_per_question'] ?? 1));
$correct     = (int)($attempt['correct_count'] ?? 0);
$wrong       = (int)($attempt['wrong_count'] ?? 0);
$unattempted = (int)($attempt['unanswered_count'] ?? 0);
$percentage  = $total_marks > 0 ? round(($score / $total_marks) * 100, 2) : 0;
$time_taken  = (int)($attempt['time_taken_seconds'] ?? 0);

$tt_mins = intdiv($time_taken % 3600, 60);
$tt_secs = $time_taken % 60;
$time_str = intdiv($time_taken, 3600) > 0
    ? intdiv($time_taken, 3600) . 'h ' . $tt_mins . 'm'
    : $tt_mins . 'm ' . $tt_secs . 's';

$completed_at = !empty($attempt['completed_at']) ? format_date($attempt['completed_at'], 'd M Y h:i A') : date('d M Y');

$site_name  = get_setting('site_name') ?: 'GovExam Portal';
$user_name  = $_SESSION['name'] ?? 'Candidate';
$user_email = $_SESSION['email'] ?? '';

// Rank
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE test_id = ? AND score > ? AND completed_at IS NOT NULL");
$stmt->execute([$attempt['test_id'], $score]);
$rank = (int)$stmt->fetchColumn() + 1;

// Build PDF
$pdf = new SimplePDF();
$pdf->setTitle('Result — ' . $test['title']);

// ---- PAGE 1: Result Summary ----
$pdf->addPage();
$pdf->addText($site_name, 18, true, 'C', 'blue');
$pdf->addSpace(4);
$pdf->addText('Mock Test Result Card', 13, false, 'C', 'gray');
$pdf->addSpace(6);
$pdf->addLine(1, 'gray');
$pdf->addSpace(4);

$pdf->addText('Test Details', 12, true, 'L', 'blue');
$pdf->addSpace(4);
$pdf->addRow('Test Name:',  $test['title']);
$pdf->addRow('Category:',   $test['category'] ?? '');
$pdf->addRow('Total Questions:', (string)$test['total_questions']);
$pdf->addRow('Duration:',   $test['duration_minutes'] . ' minutes');
$pdf->addRow('Date Completed:', $completed_at);
$pdf->addSpace(8);

$pdf->addText('Candidate Details', 12, true, 'L', 'blue');
$pdf->addSpace(4);
$pdf->addRow('Name:',   $user_name);
$pdf->addRow('Email:',  $user_email);
$pdf->addSpace(8);

$pdf->addLine(0.5, 'light');
$pdf->addSpace(4);
$pdf->addText('Score Summary', 12, true, 'L', 'blue');
$pdf->addSpace(4);
$pdf->addRow('Your Score:',     number_format($score, 2) . ' / ' . number_format($total_marks, 2));
$pdf->addRow('Percentage:',     $percentage . '%', $percentage >= 60 ? 'green' : 'red');
$pdf->addRow('Correct:',        (string)$correct, 'green');
$pdf->addRow('Wrong:',          (string)$wrong, 'red');
$pdf->addRow('Unattempted:',    (string)$unattempted, 'gray');
$pdf->addRow('Time Taken:',     $time_str);
$pdf->addRow('Your Rank:',      '#' . $rank, 'blue');
$pdf->addSpace(8);

$pdf->addLine(0.5, 'light');
$pdf->addSpace(4);

$result_label = $percentage >= 60 ? 'PASS' : 'NEEDS IMPROVEMENT';
$result_color = $percentage >= 60 ? 'green' : 'red';
$pdf->addText('Result: ' . $result_label, 14, true, 'C', $result_color);
$pdf->addSpace(12);

// ---- PAGE 2: Answer Key ----
$pdf->addPage();
$pdf->addText('Answer Key', 14, true, 'C', 'blue');
$pdf->addSpace(6);
$pdf->addLine(0.5, 'gray');
$pdf->addSpace(4);

foreach ($questions as $i => $q) {
    $user_ans    = isset($answers_given[$i]) ? strtoupper(trim((string)$answers_given[$i])) : '-';
    $correct_ans = isset($q['correct']) ? strtoupper(trim((string)$q['correct'])) : '-';
    $is_correct  = ($user_ans !== '-' && $user_ans === $correct_ans);
    $is_wrong    = ($user_ans !== '-' && !$is_correct);

    $q_text = strip_tags($q['question'] ?? $q['text'] ?? '');
    $q_short = mb_substr($q_text, 0, 70) . (mb_strlen($q_text) > 70 ? '...' : '');

    $status      = $is_correct ? 'Correct' : ($is_wrong ? 'Wrong' : 'Skipped');
    $status_col  = $is_correct ? 'green' : ($is_wrong ? 'red' : 'gray');

    $pdf->addText('Q' . ($i + 1) . '. ' . $q_short, 9, false, 'L', 'black');
    $line = 'Your: ' . $user_ans . '  |  Correct: ' . $correct_ans . '  |  ' . $status;
    $pdf->addText($line, 9, true, 'L', $status_col);
    $pdf->addSpace(3);
}

$pdf->addLine(0.5, 'light');
$pdf->addSpace(6);
$pdf->addText('Generated by ' . $site_name . ' on ' . date('d M Y'), 8, false, 'C', 'gray');

// Output
$filename = 'result_' . $attempt_id . '_' . date('Ymd') . '.pdf';
$pdf->output($filename);
exit;

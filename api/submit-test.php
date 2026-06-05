<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$test_id   = (int)($input['test_id'] ?? 0);
$answers   = $input['answers'] ?? [];
$time_taken = (int)($input['time_taken'] ?? 0);

if (!$test_id) {
    echo json_encode(['error' => 'Invalid test']);
    exit;
}

// Fetch test
$stmt = $pdo->prepare("SELECT * FROM mock_tests WHERE id = ?");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    echo json_encode(['error' => 'Test not found']);
    exit;
}

$questions = [];
if (!empty($test['questions_json'])) {
    $questions = json_decode($test['questions_json'], true) ?: [];
}

// Scoring
$marks_correct   = (float)($test['marks_per_question'] ?? 1);
$marks_negative  = (float)($test['negative_marking'] ?? 0);
$correct         = 0;
$wrong           = 0;
$unattempted     = 0;

foreach ($questions as $i => $q) {
    $user_answer = isset($answers[$i]) ? strtoupper(trim((string)$answers[$i])) : '';
    $correct_ans = isset($q['correct']) ? strtoupper(trim((string)$q['correct'])) : '';

    if ($user_answer !== '') {
        if ($user_answer === $correct_ans) {
            $correct++;
        } else {
            $wrong++;
        }
    } else {
        $unattempted++;
    }
}

$score       = ($correct * $marks_correct) - ($wrong * $marks_negative);
$total_marks = count($questions) * $marks_correct;
$percentage  = $total_marks > 0 ? round(($score / $total_marks) * 100, 2) : 0;

// Calculate rank among completed attempts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE test_id = ? AND score > ? AND completed_at IS NOT NULL");
$stmt->execute([$test_id, $score]);
$rank = (int)$stmt->fetchColumn() + 1;

// Total completed attempts for percentile
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE test_id = ? AND completed_at IS NOT NULL");
$stmt->execute([$test_id]);
$total_attempts = (int)$stmt->fetchColumn();
$percentile = $total_attempts > 0
    ? round((($total_attempts - $rank + 1) / $total_attempts) * 100, 2)
    : 100.0;

// Upsert attempt
$stmt = $pdo->prepare("SELECT id FROM user_attempts WHERE user_id = ? AND test_id = ? AND completed_at IS NULL LIMIT 1");
$stmt->execute([$_SESSION['user_id'], $test_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $stmt = $pdo->prepare("UPDATE user_attempts SET answers_json = ?, score = ?, total_marks = ?, correct_count = ?, wrong_count = ?, unanswered_count = ?, time_taken_seconds = ?, completed_at = NOW() WHERE id = ?");
    $stmt->execute([
        json_encode($answers),
        $score,
        $total_marks,
        $correct,
        $wrong,
        $unattempted,
        $time_taken,
        $existing['id']
    ]);
    $attempt_id = $existing['id'];
} else {
    $stmt = $pdo->prepare("INSERT INTO user_attempts (user_id, test_id, answers_json, score, total_marks, correct_count, wrong_count, unanswered_count, time_taken_seconds, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $_SESSION['user_id'],
        $test_id,
        json_encode($answers),
        $score,
        $total_marks,
        $correct,
        $wrong,
        $unattempted,
        $time_taken
    ]);
    $attempt_id = (int)$pdo->lastInsertId();
}

// Increment attempts count on test
$stmt = $pdo->prepare("UPDATE mock_tests SET attempts_count = attempts_count + 1 WHERE id = ?");
$stmt->execute([$test_id]);

echo json_encode([
    'success'     => true,
    'attempt_id'  => $attempt_id,
    'score'       => $score,
    'total_marks' => $total_marks,
    'percentage'  => $percentage,
    'correct'     => $correct,
    'wrong'       => $wrong,
    'unattempted' => $unattempted,
    'rank'        => $rank,
    'percentile'  => $percentile,
    'redirect'    => '/pages/tests/result.php?attempt_id=' . $attempt_id,
]);

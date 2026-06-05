<?php
/**
 * Admin: download a sample/demo CSV showing how to format questions & answers
 * for bulk import via /admin/tests/import.php
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

// Force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sample-questions.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Header row (must match the importer's expected columns)
fputcsv($out, ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct', 'explanation']);

// Example rows. "correct" can be a letter (A-D), a number (1-4) or the exact option text.
$rows = [
    [
        'What is the capital of India?',
        'Mumbai', 'New Delhi', 'Kolkata', 'Chennai',
        'B',
        'New Delhi is the capital of India.',
    ],
    [
        'Who is known as the Father of the Indian Constitution?',
        'Mahatma Gandhi', 'Jawaharlal Nehru', 'B. R. Ambedkar', 'Sardar Patel',
        'C',
        'Dr. B. R. Ambedkar chaired the drafting committee of the Constitution.',
    ],
    [
        '5 + 7 x 2 = ?',
        '24', '19', '17', '14',
        '2',
        'Order of operations: 7 x 2 = 14, then 5 + 14 = 19. (correct given as number 2 = option B)',
    ],
    [
        'The Tropic of Cancer does NOT pass through which Indian state?',
        'Gujarat', 'Rajasthan', 'Bihar', 'Kerala',
        'Kerala',
        'Kerala lies south of the Tropic of Cancer. (correct given as option text)',
    ],
];

foreach ($rows as $row) {
    fputcsv($out, $row);
}

fclose($out);
exit;

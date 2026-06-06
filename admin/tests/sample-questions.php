<?php
/**
 * Admin: download a sample/demo CSV showing how to format questions & answers
 * for bulk import via /admin/tests/import.php
 *
 * Columns: question, option_a, option_b, option_c, option_d, correct, explanation, subject
 * - subject is optional; it groups questions into section tabs during the exam.
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
fputcsv($out, ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct', 'explanation', 'subject']);

// Example rows showing different subjects (sections)
$rows = [
    [
        'What is the capital of India?',
        'Mumbai', 'New Delhi', 'Kolkata', 'Chennai',
        'B',
        'New Delhi is the capital of India.',
        'General Awareness',
    ],
    [
        'Who is known as the Father of the Indian Constitution?',
        'Mahatma Gandhi', 'Jawaharlal Nehru', 'B. R. Ambedkar', 'Sardar Patel',
        'C',
        'Dr. B. R. Ambedkar chaired the drafting committee of the Constitution.',
        'General Awareness',
    ],
    [
        '5 + 7 x 2 = ?',
        '24', '19', '17', '14',
        'B',
        'Order of operations: 7 x 2 = 14, then 5 + 14 = 19.',
        'Quantitative Aptitude',
    ],
    [
        'If APPLE is coded as 50, then MANGO is coded as?',
        '55', '60', '65', '70',
        'A',
        'Sum of letter positions: M(13)+A(1)+N(14)+G(7)+O(15) = 50. Wait — let us use A=1 scheme for APPLE: A(1)+P(16)+P(16)+L(12)+E(5)=50. MANGO: 13+1+14+7+15=50. So 50.',
        'Reasoning',
    ],
    [
        'Choose the correct spelling:',
        'Accomodation', 'Accommodation', 'Acomodation', 'Acommodation',
        'B',
        'Accommodation has two c\'s and two m\'s.',
        'English',
    ],
    [
        'The Tropic of Cancer does NOT pass through which Indian state?',
        'Gujarat', 'Rajasthan', 'Bihar', 'Kerala',
        'Kerala',
        'Kerala lies south of the Tropic of Cancer.',
        'General Awareness',
    ],
];

foreach ($rows as $row) {
    fputcsv($out, $row);
}

fclose($out);
exit;

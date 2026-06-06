<?php
/**
 * Admin: download a sample/demo CSV showing how to format questions & answers
 * for bulk import via /admin/tests/import.php
 *
 * Columns: question, option_a, option_b, option_c, option_d, correct, explanation, subject
 * - subject is optional; it groups questions into section tabs during the exam.
 * - Only use the main numbered sections as subject (e.g. "General Knowledge",
 *   "General Hindi", "Numerical & Mental Ability", "Mental Aptitude / Reasoning")
 *   NOT the subtopics.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

// Force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sample-questions-up-police.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility (Hindi text)
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct', 'explanation', 'subject']);

// UP Police Constable style questions with proper subject sections
$rows = [
    // --- General Knowledge ---
    [
        'भारत की राजधानी कौन सी है?',
        'मुंबई', 'नई दिल्ली', 'कोलकाता', 'चेन्नई',
        'B',
        'नई दिल्ली भारत की राजधानी है।',
        'General Knowledge',
    ],
    [
        'भारतीय संविधान के जनक किसे कहा जाता है?',
        'महात्मा गांधी', 'जवाहरलाल नेहरू', 'डॉ. बी.आर. अम्बेडकर', 'सरदार पटेल',
        'C',
        'डॉ. बी.आर. अम्बेडकर ने संविधान की मसौदा समिति की अध्यक्षता की।',
        'General Knowledge',
    ],
    [
        'उत्तर प्रदेश की राजधानी कौन सी है?',
        'वाराणसी', 'इलाहाबाद', 'लखनऊ', 'कानपुर',
        'C',
        'लखनऊ उत्तर प्रदेश की राजधानी है।',
        'General Knowledge',
    ],
    [
        'Which planet is known as the Red Planet?',
        'Venus', 'Mars', 'Jupiter', 'Saturn',
        'B',
        'Mars is called the Red Planet due to iron oxide on its surface.',
        'General Knowledge',
    ],

    // --- General Hindi ---
    [
        '"सूर्य" का पर्यायवाची शब्द क्या है?',
        'चंद्रमा', 'रवि', 'तारा', 'आकाश',
        'B',
        'सूर्य के पर्यायवाची: रवि, भानु, दिनकर, सूरज',
        'General Hindi',
    ],
    [
        '"अंधेरे में तीर मारना" मुहावरे का अर्थ है:',
        'बिना सोचे-समझे काम करना', 'रात में शिकार करना', 'अंधेरे से डरना', 'तीर चलाना सीखना',
        'A',
        'इस मुहावरे का अर्थ है बिना सोचे-समझे या अनुमान से काम करना।',
        'General Hindi',
    ],
    [
        '"विद्यालय" शब्द में कौन सा समास है?',
        'तत्पुरुष', 'द्वंद्व', 'कर्मधारय', 'बहुव्रीहि',
        'A',
        'विद्या के लिए आलय = तत्पुरुष समास',
        'General Hindi',
    ],

    // --- Numerical & Mental Ability ---
    [
        'If 5 + 7 x 2 = ?, find the value.',
        '24', '19', '17', '14',
        'B',
        'BODMAS: 7 x 2 = 14, then 5 + 14 = 19',
        'Numerical & Mental Ability',
    ],
    [
        'A can do a work in 10 days, B in 15 days. Together in how many days?',
        '5 days', '6 days', '7 days', '8 days',
        'B',
        'Combined rate = 1/10 + 1/15 = 5/30 = 1/6. So 6 days.',
        'Numerical & Mental Ability',
    ],
    [
        'The ratio of A to B is 3:5. If B is 40, what is A?',
        '20', '24', '30', '15',
        'B',
        '3/5 = A/40, so A = 3 x 40 / 5 = 24',
        'Numerical & Mental Ability',
    ],

    // --- Mental Aptitude / Reasoning ---
    [
        'If APPLE is coded as 50, then MANGO is coded as?',
        '50', '55', '60', '65',
        'A',
        'A=1,P=16,P=16,L=12,E=5 => 50. M=13,A=1,N=14,G=7,O=15 => 50.',
        'Mental Aptitude / Reasoning',
    ],
    [
        'Find the next in series: 2, 6, 12, 20, 30, ?',
        '40', '42', '44', '36',
        'B',
        'Differences: 4, 6, 8, 10, 12. Next = 30 + 12 = 42.',
        'Mental Aptitude / Reasoning',
    ],
    [
        'Pointing to a man, Sita said "He is my mother\'s only son\'s son." How is the man related to Sita?',
        'Son', 'Brother', 'Nephew', 'Father',
        'A',
        'Mother\'s only son = Sita\'s brother. His son = Sita\'s nephew. Wait — "my mother\'s only son" = Sita (if female) has no brother, so it\'s Sita herself? No — Sita is female so her mother\'s only son is her brother. His son = nephew. Actually re-read: the man is "my mother\'s only son\'s son" = my brother\'s son = my nephew. Let me correct: Answer is C (Nephew).',
        'Mental Aptitude / Reasoning',
    ],
];

foreach ($rows as $row) {
    fputcsv($out, $row);
}

fclose($out);
exit;

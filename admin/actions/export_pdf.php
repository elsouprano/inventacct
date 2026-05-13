<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

$student_id = $_GET['id'] ?? null;
if (!$student_id) {
    die("Invalid student ID.");
}

// Fetch student
$stmt = $pdo->prepare("SELECT u.*, s.submitted_at, s.is_complete, s.id as submission_id 
    FROM users u 
    LEFT JOIN inventory_submissions s ON u.id = s.user_id 
    WHERE u.id = ? AND u.role = 'student'");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found.");
}

$answers = [];
if ($student['submission_id']) {
    $stmt_ans = $pdo->prepare("SELECT section, question_key, answer_value FROM inventory_answers WHERE submission_id = ?");
    $stmt_ans->execute([$student['submission_id']]);
    $ans_data = $stmt_ans->fetchAll();
    foreach ($ans_data as $row) {
        $answers[$row['section']][] = $row;
    }
}

$sections = [
    'personal_info' => 'Personal Info',
    'learning_style' => 'Learning Style',
    'erq' => 'ERQ',
    'cat' => 'CAT',
    'dass21' => 'DASS-21',
    'ars30' => 'ARS-30',
    'ffmq' => 'FFMQ'
];

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('CCT Inventory System');
$pdf->SetTitle('Student Inventory Export - ' . $student['last_name']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$html = '<h2>Student Profile</h2>';
$html .= '<table cellpadding="5">';
$html .= '<tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($student['first_name'] . ' ' . $student['middle_initial'] . ' ' . $student['last_name']) . '</td></tr>';
$html .= '<tr><td><strong>Student ID:</strong></td><td>' . htmlspecialchars($student['student_id']) . '</td></tr>';
$html .= '<tr><td><strong>Program:</strong></td><td>' . htmlspecialchars($student['program']) . '</td></tr>';
$html .= '<tr><td><strong>Year & Section:</strong></td><td>' . htmlspecialchars($student['year_level'] . ' - ' . $student['section']) . '</td></tr>';
$html .= '<tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($student['email']) . '</td></tr>';
$html .= '<tr><td><strong>Date Submitted:</strong></td><td>' . ($student['submitted_at'] ? date('M d, Y h:i A', strtotime($student['submitted_at'])) : 'Not submitted') . '</td></tr>';
$html .= '</table><hr>';

foreach ($sections as $key => $label) {
    $html .= '<h3>' . $label . '</h3>';
    if (!isset($answers[$key]) || empty($answers[$key])) {
        $html .= '<p><em>No submission yet.</em></p>';
    } else {
        $html .= '<table cellpadding="3" border="1">';
        foreach ($answers[$key] as $ans) {
            $html .= '<tr><td width="30%"><strong>' . htmlspecialchars($ans['question_key']) . '</strong></td><td width="70%">' . htmlspecialchars($ans['answer_value']) . '</td></tr>';
        }
        $html .= '</table>';
    }
    $html .= '<br>';
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('student_' . $student['id'] . '_inventory.pdf', 'D');

<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action   = trim($_POST['action'] ?? '');
$user_ids = $_POST['user_ids'] ?? [];

// CSRF check (not needed for CSV export which is GET-compatible, but enforce for mutations)
if ($action !== 'export_csv') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// Sanitize user_ids
if (!is_array($user_ids)) $user_ids = [];
$user_ids = array_filter(array_map('intval', $user_ids));

if (empty($user_ids) && $action !== 'export_csv') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No students selected.']);
    exit;
}

// ── Mark Complete ──────────────────────────────────────────────────────────────
if ($action === 'mark_complete') {
    header('Content-Type: application/json');
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    try {
        // Insert submission row if missing, then set is_complete = 1
        foreach ($user_ids as $uid) {
            $existing = $pdo->prepare("SELECT id FROM inventory_submissions WHERE user_id = ?");
            $existing->execute([$uid]);
            $sub = $existing->fetch();
            if ($sub) {
                $pdo->prepare("UPDATE inventory_submissions SET is_complete = 1, submitted_at = NOW() WHERE user_id = ?")->execute([$uid]);
            } else {
                $pdo->prepare("INSERT INTO inventory_submissions (user_id, is_complete, submitted_at, validity_status) VALUES (?, 1, NOW(), 'valid')")->execute([$uid]);
            }
        }
        echo json_encode(['success' => true, 'updated' => count($user_ids)]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error.']);
    }
    exit;
}

// ── Delete ─────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    header('Content-Type: application/json');
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    try {
        $pdo->beginTransaction();
        // Delete answers
        $pdo->prepare("DELETE ia FROM inventory_answers ia JOIN inventory_submissions s ON ia.submission_id = s.id WHERE s.user_id IN ($placeholders)")->execute($user_ids);
        // Delete scores
        $pdo->prepare("DELETE sc FROM inventory_scores sc JOIN inventory_submissions s ON sc.submission_id = s.id WHERE s.user_id IN ($placeholders)")->execute($user_ids);
        // Delete submissions
        $pdo->prepare("DELETE FROM inventory_submissions WHERE user_id IN ($placeholders)")->execute($user_ids);
        // Delete drafts
        $pdo->prepare("DELETE FROM inventory_drafts WHERE user_id IN ($placeholders)")->execute($user_ids);
        // Delete users
        $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)")->execute($user_ids);
        $pdo->commit();
        echo json_encode(['success' => true, 'deleted' => count($user_ids)]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ── Export CSV ─────────────────────────────────────────────────────────────────
if ($action === 'export_csv') {
    // For CSV export, user_ids may come from POST (selected) or be empty (export all filtered)
    $placeholders = !empty($user_ids)
        ? 'u.id IN (' . implode(',', array_fill(0, count($user_ids), '?')) . ')'
        : "u.role = 'student'";

    $stmt = $pdo->prepare("
        SELECT u.student_id, u.first_name, u.middle_initial, u.last_name,
               u.program, u.section, u.email,
               s.is_complete, s.risk_level, s.validity_status,
               s.time_elapsed_seconds, s.submitted_at
        FROM users u
        LEFT JOIN inventory_submissions s ON s.user_id = u.id AND s.id = (
            SELECT MAX(id) FROM inventory_submissions WHERE user_id = u.id
        )
        WHERE $placeholders
        ORDER BY u.last_name ASC
    ");
    $stmt->execute(!empty($user_ids) ? $user_ids : []);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'students_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Full Name', 'Program', 'Section', 'Email',
                   'Submission Status', 'Risk Level', 'Validity Status',
                   'Time Taken', 'Submitted At']);

    foreach ($rows as $r) {
        $time_fmt = $r['time_elapsed_seconds'] !== null
            ? floor($r['time_elapsed_seconds'] / 60) . 'm ' . ($r['time_elapsed_seconds'] % 60) . 's'
            : '—';
        fputcsv($out, [
            $r['student_id'],
            trim($r['first_name'] . ' ' . ($r['middle_initial'] ? $r['middle_initial'] . '. ' : '') . $r['last_name']),
            $r['program'] ?? '',
            $r['section'] ?? '',
            $r['email'],
            $r['is_complete'] ? 'Submitted' : 'Pending',
            $r['risk_level'] ?? 'none',
            $r['validity_status'] ?? '',
            $time_fmt,
            $r['submitted_at'] ? date('M d, Y h:i A', strtotime($r['submitted_at'])) : '—',
        ]);
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Unknown action.']);

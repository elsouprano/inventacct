<?php
session_start();
require_once '../config/db.php';
require_once '../config/question_labels.php';

// Session check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}
$submission_id = (int)$_GET['id'];

// Get submission & student
$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.middle_initial, u.last_name, u.student_id, u.program, u.year_level, u.section 
    FROM inventory_submissions s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    die("Submission not found.");
}

// Format time
$time_formatted = floor($submission['time_elapsed_seconds'] / 60) . 'm ' . ($submission['time_elapsed_seconds'] % 60) . 's';

// Fetch answers
$answers = [];
$scores = [];
$stmt_ans = $pdo->prepare("SELECT section, question_key, answer_value FROM inventory_answers WHERE submission_id = ?");
$stmt_ans->execute([$submission_id]);
$ans_data = $stmt_ans->fetchAll();
foreach ($ans_data as $row) {
    $answers[$row['section']][] = $row;
}

$stmt_scores = $pdo->prepare("SELECT scale, raw_score, interpretation, needs_counseling FROM inventory_scores WHERE submission_id = ?");
$stmt_scores->execute([$submission_id]);
$scores = $stmt_scores->fetchAll(PDO::FETCH_ASSOC);

$flags = json_decode($submission['validity_flags'] ?: '[]', true);

$sections = [
    'scores' => 'Scoring Results',
    'personal_info' => 'Personal Info',
    'learning_style' => 'Learning Style',
    'erq' => 'ERQ',
    'cat' => 'CAT',
    'dass21' => 'DASS-21',
    'ars30' => 'ARS-30',
    'ffmq' => 'FFMQ'
];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submission - CCT Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_layout.css">
    <style>
        .profile-card {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            position: relative;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .profile-item label {
            font-size: 0.8em;
            color: #666;
            display: block;
        }
        .profile-item div {
            font-weight: bold;
        }
        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1em;
            color: #666;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: bold;
        }
        .tab-content {
            display: none;
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .tab-content.active {
            display: block;
        }
        .answer-list {
            list-style: none;
            padding: 0;
        }
        .answer-list li {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: block;
        }
        .answer-list li strong {
            color: #555;
            margin-bottom: 5px;
            display: inline-block;
        }
        .answer-list li span {
            display: inline-block;
            margin-left: 10px;
        }
        .score-table {
            width: 100%;
            border-collapse: collapse;
        }
        .score-table th, .score-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .score-table th {
            background: #f8f9fa;
        }
        .badge-normal { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .placeholder {
            color: #888;
            font-style: italic;
        }
        .actions-bar {
            background: var(--white);
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
    </style>
</head>
<body>
<?php $pageTitle = 'Review Submission'; ?>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include 'includes/admin_header.php'; ?>
        <div class="admin-content">

        <h1 style="margin-bottom:20px;">Review Submission</h1>
        
        <div class="actions-bar">
            <strong style="margin-right: auto;">Review Actions:</strong>
            <form method="POST" action="actions/review_action.php" style="display: flex; gap: 10px; margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">
                
                <button type="submit" name="action" value="accept" class="btn" style="width: auto; background: #28a745;" onclick="return confirm('Accept this submission as valid?');">Accept as Valid</button>
                <button type="submit" name="action" value="resubmit" class="btn" style="width: auto; background: #ffc107; color: #333;" onclick="return confirm('Request the student to resubmit their inventory?');">Request Resubmit</button>
                <button type="submit" name="action" value="reject" class="btn btn-danger" style="width: auto; background: var(--error-color);" onclick="return confirm('Reject this submission? This cannot be undone easily.');">Reject Submission</button>
            </form>
        </div>

        <div class="profile-card">
            <h2><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['middle_initial'] . ' ' . $submission['last_name']); ?></h2>
            <div class="profile-grid">
                <div class="profile-item">
                    <label>Student ID</label>
                    <div><?php echo htmlspecialchars($submission['student_id'] ?: 'N/A'); ?></div>
                </div>
                <div class="profile-item">
                    <label>Program / Year / Sec</label>
                    <div><?php echo htmlspecialchars($submission['program'] . ' - ' . $submission['year_level'] . $submission['section']); ?></div>
                </div>
                <div class="profile-item">
                    <label>Time Taken</label>
                    <div style="<?php echo $submission['time_elapsed_seconds'] < 300 ? 'color: var(--error-color); font-weight: bold;' : ''; ?>">
                        <?php echo $time_formatted; ?>
                        <?php if($submission['time_elapsed_seconds'] < 300) echo ' (Suspiciously fast)'; ?>
                    </div>
                </div>
                <div class="profile-item">
                    <label>Date Submitted</label>
                    <div><?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></div>
                </div>
            </div>
            
            <?php if (!empty($flags)): ?>
                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <strong style="color: #856404; display: block; margin-bottom: 10px;">⚠️ Validity Flags Detected:</strong>
                    <ul style="margin: 0; padding-left: 20px; color: #856404;">
                        <?php foreach($flags as $flag): ?>
                            <li><?php echo htmlspecialchars($flag); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div class="tabs">
            <?php $first = true; foreach($sections as $key => $label): ?>
                <button class="tab <?php echo $first ? 'active' : ''; ?>" onclick="openTab(event, '<?php echo $key; ?>')"><?php echo $label; ?></button>
            <?php $first = false; endforeach; ?>
        </div>

        <?php $first = true; foreach($sections as $key => $label): ?>
            <div id="<?php echo $key; ?>" class="tab-content <?php echo $first ? 'active' : ''; ?>">
                <h3><?php echo $label; ?></h3>
                
                <?php if ($key === 'scores'): ?>
                    <?php if (empty($scores)): ?>
                        <p class="placeholder">No scores calculated yet.</p>
                    <?php else: ?>
                        <table class="score-table">
                            <thead>
                                <tr>
                                    <th>Scale</th>
                                    <th>Raw Score</th>
                                    <th>Interpretation</th>
                                    <th>Needs Counseling</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $scale_names = [
                                    'learning_style' => 'Learning Style',
                                    'erq_cr' => 'Cognitive Reappraisal (ERQ)',
                                    'erq_es' => 'Expressive Suppression (ERQ)',
                                    'cat_negative' => 'Negative Affect (CAT)',
                                    'cat_positive' => 'Positive Affect (CAT)',
                                    'dass21_depression' => 'Depression (DASS-21)',
                                    'dass21_anxiety' => 'Anxiety (DASS-21)',
                                    'dass21_stress' => 'Stress (DASS-21)',
                                    'ars30' => 'Academic Resilience (ARS-30)',
                                    'ffmq_observing' => 'Observing (FFMQ)',
                                    'ffmq_describing' => 'Describing (FFMQ)',
                                    'ffmq_awareness' => 'Acting with Awareness (FFMQ)',
                                    'ffmq_nonjudging' => 'Non-judging (FFMQ)',
                                    'ffmq_nonreactivity' => 'Non-reactivity (FFMQ)'
                                ];
                                foreach($scores as $score): 
                                    $interp = strtolower($score['interpretation']);
                                    $badge_class = 'badge-warning'; // Default
                                    if (str_contains($interp, 'normal') || str_contains($interp, 'high') || str_contains($interp, 'well adjusted')) {
                                        $badge_class = 'badge-normal';
                                    } elseif (str_contains($interp, 'severe') || str_contains($interp, 'low') || str_contains($interp, 'poor')) {
                                        $badge_class = 'badge-danger';
                                    }
                                    if ($score['scale'] === 'learning_style') $badge_class = 'badge-normal'; 
                                ?>
                                <tr>
                                    <td><strong><?php echo $scale_names[$score['scale']] ?? $score['scale']; ?></strong></td>
                                    <td><?php echo $score['raw_score']; ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($score['interpretation']); ?></span></td>
                                    <td>
                                        <?php if ($score['needs_counseling']): ?>
                                            <span class="badge badge-danger">Yes</span>
                                        <?php else: ?>
                                            <span class="badge badge-normal">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <?php if (!isset($answers[$key]) || empty($answers[$key])): ?>
                        <p class="placeholder">No submission yet.</p>
                    <?php else: ?>
                        <ul class="answer-list">
                            <?php foreach($answers[$key] as $ans): 
                                $q_key = $ans['question_key'];
                                $q_text = $question_labels[$q_key] ?? $q_key;
                                $ans_text = get_formatted_answer($q_key, $ans['answer_value']);
                            ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($q_text); ?></strong><br>
                                    <span style="color: #333;">&mdash; <?php echo htmlspecialchars($ans_text); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php $first = false; endforeach; ?>

    </main>

    <script>
    function openTab(evt, sectionName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(sectionName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }
    </script>
        </div><!-- admin-content -->
    </div><!-- admin-main -->
</div><!-- admin-wrapper -->
</body>
</html>

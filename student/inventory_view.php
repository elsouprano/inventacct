<?php
session_start();
require_once '../config/db.php';
require_once '../config/question_labels.php';

// Session check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, s.submitted_at, s.is_complete, s.id as submission_id 
    FROM users u 
    LEFT JOIN inventory_submissions s ON u.id = s.user_id 
    WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student || !$student['is_complete']) {
    header("Location: dashboard.php");
    exit;
}

// Fetch answers
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inventory Submission - CCT</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student_mobile.css">
    <style>
        .profile-card {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="logo">CCT Inventory</div>
        <a href="dashboard.php" class="logout-link">&larr; Back to Dashboard</a>
    </header>

    <main class="dashboard-content" style="max-width: 900px;">
        <h1 style="margin-bottom: 20px;">My Submission</h1>

        <div class="profile-card">
            <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_initial'] . ' ' . $student['last_name']); ?></h2>
            <div class="profile-grid">
                <div class="profile-item">
                    <label>Student ID</label>
                    <div><?php echo htmlspecialchars($student['student_id'] ?: 'N/A'); ?></div>
                </div>
                <div class="profile-item">
                    <label>Program</label>
                    <div><?php echo htmlspecialchars($student['program']); ?></div>
                </div>
                <div class="profile-item">
                    <label>Date Submitted</label>
                    <div><?php echo date('M d, Y h:i A', strtotime($student['submitted_at'])); ?></div>
                </div>
            </div>
        </div>

        <div class="tabs">
            <?php $first = true; foreach($sections as $key => $label): ?>
                <button class="tab <?php echo $first ? 'active' : ''; ?>" onclick="openTab(event, '<?php echo $key; ?>')"><?php echo $label; ?></button>
            <?php $first = false; endforeach; ?>
        </div>

        <?php $first = true; foreach($sections as $key => $label): ?>
            <div id="<?php echo $key; ?>" class="tab-content <?php echo $first ? 'active' : ''; ?>">
                <h3><?php echo $label; ?></h3>
                <?php if (!isset($answers[$key]) || empty($answers[$key])): ?>
                    <p style="color: #888; font-style: italic;">No data found.</p>
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
</body>
</html>

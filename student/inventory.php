<?php
session_start();
require_once '../config/db.php';

// Session check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

// Check submission status
$stmt = $pdo->prepare("SELECT is_complete FROM inventory_submissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$submission = $stmt->fetch();
if ($submission && $submission['is_complete'] == 1) {
    header("Location: dashboard.php");
    exit;
}

// --- Submission Window Check ---
require_once '../config/app_config.php';
$active_period = $pdo->query("SELECT * FROM inventory_periods WHERE is_active = 1 LIMIT 1")->fetch();
$now = time();
$period_open   = $active_period ? strtotime($active_period['open_date'])  : null;
$period_close  = $active_period ? strtotime($active_period['close_date']) : null;
$within_window = $active_period && $now >= $period_open && $now <= $period_close;

if (!$within_window) {
    // Determine message
    if (!$active_period) {
        $window_msg = "No inventory period is currently scheduled. Please check back later.";
    } elseif ($now < $period_open) {
        $window_msg = "The inventory period has not started yet. It will open on " . date('F d, Y \a\t h:i A', $period_open) . ".";
    } else {
        $window_msg = "The inventory submission period has closed. Please contact the Guidance Office if you have concerns.";
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Closed - CCT</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="dashboard-header">
        <div class="logo">CCT Inventory</div>
        <a href="dashboard.php" class="logout-link">Back to Dashboard</a>
    </header>
    <main class="dashboard-content">
        <div style="max-width:600px;margin:60px auto;background:var(--white);padding:40px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.07);text-align:center;">
            <div style="font-size:3em;margin-bottom:15px;">📋</div>
            <h2 style="color:var(--primary-color);margin-bottom:15px;">Inventory Unavailable</h2>
            <p style="color:#555;margin-bottom:30px;line-height:1.6;"><?php echo htmlspecialchars($window_msg); ?></p>
            <div style="background:#f8f9fa;border-radius:6px;padding:20px;text-align:left;font-size:0.9em;color:#555;">
                <strong><?php echo GUIDANCE_OFFICE_NAME; ?></strong><br>
                📍 <?php echo GUIDANCE_OFFICE_LOCATION; ?><br>
                ✉️ <?php echo GUIDANCE_OFFICE_EMAIL; ?><br>
                📞 <?php echo GUIDANCE_OFFICE_PHONE; ?>
            </div>
            <a href="dashboard.php" class="btn" style="display:inline-block;width:auto;padding:10px 25px;margin-top:25px;">Return to Dashboard</a>
        </div>
    </main>
</body>
</html>
<?php
    exit;
}

// --- Draft Loading ---
$draft_notice = '';
$stmt_draft = $pdo->prepare("SELECT * FROM inventory_drafts WHERE user_id = ?");
$stmt_draft->execute([$_SESSION['user_id']]);
$draft = $stmt_draft->fetch();

if ($draft && !isset($_SESSION['inventory_answers'])) {
    $draft_answers = json_decode($draft['answers'], true);
    if (is_array($draft_answers)) {
        $_SESSION['inventory_answers'] = $draft_answers;
    }
    if (!isset($_SESSION['inventory_start'])) {
        $_SESSION['inventory_start'] = strtotime($draft['started_at']);
    }
    $draft_step = (int)$draft['current_step'];
    $saved_ago  = human_time_diff(strtotime($draft['last_saved']));
    $draft_notice = "Welcome back! We found your saved progress from {$saved_ago}. Continuing from Step {$draft_step}.";
    // Redirect to the saved step if arriving fresh
    if (!isset($_GET['step'])) {
        header("Location: inventory.php?step={$draft_step}");
        exit;
    }
}

// Human-readable time difference helper
function human_time_diff($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60)  return 'just now';
    if ($diff < 3600) return floor($diff/60) . ' minute' . (floor($diff/60)!==1?'s':'') . ' ago';
    if ($diff < 86400) return floor($diff/3600) . ' hour' . (floor($diff/3600)!==1?'s':'') . ' ago';
    return date('M d, Y', $timestamp);
}

// Timer
if (!isset($_SESSION['inventory_start'])) {
    $_SESSION['inventory_start'] = time();
}

// Initialize answers session
if (!isset($_SESSION['inventory_answers'])) {
    $_SESSION['inventory_answers'] = [];
}

// Step logic
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1) $step = 1;
if ($step > 7) $step = 7;

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    
    // Save answers
    $current_step = (int)$_POST['current_step'];
    foreach ($_POST as $key => $value) {
        if ($key !== 'csrf_token' && $key !== 'current_step' && $key !== 'action') {
            $_SESSION['inventory_answers'][$key] = $value;
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'submit') {
        // Final submit
        try {
            $pdo->beginTransaction();
            
            // Update address if provided
            if (isset($_SESSION['inventory_answers']['address'])) {
                $stmt_addr = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
                $stmt_addr->execute([$_SESSION['inventory_answers']['address'], $_SESSION['user_id']]);
            }
            
            // Insert or Update submission
            $elapsed = time() - $_SESSION['inventory_start'];
            
            $stmt_check = $pdo->prepare("SELECT id FROM inventory_submissions WHERE user_id = ?");
            $stmt_check->execute([$_SESSION['user_id']]);
            $existing = $stmt_check->fetch();
            
            if ($existing) {
                $submission_id = $existing['id'];
                $pdo->prepare("DELETE FROM inventory_answers WHERE submission_id = ?")->execute([$submission_id]);
                $pdo->prepare("DELETE FROM inventory_scores WHERE submission_id = ?")->execute([$submission_id]);
                
                $stmt_sub = $pdo->prepare("UPDATE inventory_submissions SET time_elapsed_seconds = ?, is_complete = 1, validity_status = 'valid', validity_flags = NULL, reviewed_by = NULL, reviewed_at = NULL WHERE id = ?");
                $stmt_sub->execute([$elapsed, $submission_id]);
            } else {
                $stmt_sub = $pdo->prepare("INSERT INTO inventory_submissions (user_id, time_elapsed_seconds, is_complete, validity_status) VALUES (?, ?, 1, 'valid')");
                $stmt_sub->execute([$_SESSION['user_id'], $elapsed]);
                $submission_id = $pdo->lastInsertId();
            }
            
            // Insert answers
            $stmt_ans = $pdo->prepare("INSERT INTO inventory_answers (submission_id, section, question_key, answer_value) VALUES (?, ?, ?, ?)");
            
            $sections_map = [
                'learning_' => 'learning_style',
                'erq_' => 'erq',
                'cat_' => 'cat',
                'dass_' => 'dass21',
                'ars_' => 'ars30',
                'ffmq_' => 'ffmq'
            ];
            
            foreach ($_SESSION['inventory_answers'] as $key => $val) {
                if ($key === 'address' || $key === 'consent') continue;
                
                $section = 'personal_info'; // default
                foreach ($sections_map as $prefix => $sec) {
                    if (str_starts_with($key, $prefix)) {
                        $section = $sec;
                        break;
                    }
                }
                $stmt_ans->execute([$submission_id, $section, $key, $val]);
            }
            
            require_once '../config/scoring.php';
            computeAndSaveScores($pdo, $submission_id);
            
            require_once '../config/validity.php';
            checkValidity($pdo, $submission_id);
            
            require_once '../config/risk.php';
            assessRisk($pdo, $submission_id);
            
            // Delete draft on successful submission
            $pdo->prepare("DELETE FROM inventory_drafts WHERE user_id = ?")->execute([$_SESSION['user_id']]);
            
            $pdo->commit();
            
            unset($_SESSION['inventory_answers']);
            unset($_SESSION['inventory_start']);
            $_SESSION['show_risk_message'] = true;
            header("Location: thankyou.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Submission failed: " . $e->getMessage());
        }
    }
    
    // If not final submit, go to next or prev step
    if (isset($_POST['action']) && $_POST['action'] === 'prev') {
        header("Location: inventory.php?step=" . ($current_step - 1));
        exit;
    } else {
        header("Location: inventory.php?step=" . ($current_step + 1));
        exit;
    }
}

// Titles
$step_titles = [
    1 => "Personal Info",
    2 => "Learning Style Inventory",
    3 => "Emotion Regulation Questionnaire (ERQ)",
    4 => "College Adjustment Test (CAT)",
    5 => "DASS-21",
    6 => "Academic Resilience Scale (ARS-30)",
    7 => "Five Facet Mindfulness Questionnaire (FFMQ)"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - CCT</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student_mobile.css">
    <style>
        .progress-bar {
            background: #eee;
            border-radius: 8px;
            height: 10px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .progress-fill {
            background: var(--primary-color);
            height: 100%;
            transition: width 0.3s ease;
        }
        .form-container {
            background: var(--white);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
        }
        .question-block {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .question-text {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .options.horizontal {
            flex-direction: row;
            flex-wrap: wrap;
            gap: 15px;
        }
        .radio-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: all 0.2s;
            flex: 1;
        }
        .radio-label:hover {
            background: #f8f9fa;
        }
        input[type="radio"]:checked + .radio-label {
            border-color: var(--primary-color);
            background: #f0f4f8;
        }
        input[type="radio"] {
            margin: 0;
            cursor: pointer;
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        .btn-outline:hover {
            background: #f0f4f8;
        }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="logo">CCT Inventory</div>
        <a href="dashboard.php" class="logout-link">Exit to Dashboard</a>
    </header>
    
    <main class="dashboard-content" style="max-width: 900px;">

        <div class="progress-sticky">
            <?php if ($draft_notice): ?>
            <div style="background:#cce5ff;color:#004085;padding:8px 14px;border-radius:6px;margin-bottom:8px;font-size:0.88em;">
                📂 <?php echo htmlspecialchars($draft_notice); ?>
            </div>
            <?php endif; ?>
            <div style="background:#d4edda;color:#155724;padding:6px 12px;border-radius:6px;margin-bottom:8px;font-size:0.82em;">
                🕐 Closes <strong><?php echo date('M d, Y h:i A', $period_close); ?></strong> · Auto-saved
            </div>
            <div class="progress-info" style="text-align:center;margin-bottom:4px;font-size:0.88em;position:relative;">
                Step <?php echo $step; ?> of 7 — <strong><?php echo $step_titles[$step]; ?></strong>
                <span id="draft-saved-notice" style="font-size:0.8em;color:#28a745;opacity:0;transition:opacity 0.5s;">✓ Draft saved</span>
            </div>
            <div class="progress-bar" style="margin-bottom:2px;">
                <div class="progress-fill" style="width:<?php echo ($step/7)*100; ?>%;"></div>
            </div>
            <?php
            $q_counts = [1=>0,2=>14,3=>10,4=>19,5=>21,6=>30,7=>27];
            $remaining_q = 0;
            for ($s=$step; $s<=7; $s++) $remaining_q += $q_counts[$s];
            $mins = (int)ceil($remaining_q * 30 / 60);
            ?>
            <div class="time-remaining">About <?php echo $mins; ?> minute<?php echo $mins!==1?'s':''; ?> remaining</div>
            <div id="validation-msg">Please answer all questions before continuing.</div>
        </div>
        
        <div class="form-container">
            <h2 style="margin-bottom: 20px; color: var(--primary-color);"><?php echo $step_titles[$step]; ?></h2>
            
            <form method="POST" id="inventory-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="current_step" value="<?php echo $step; ?>">
                
                <?php 
                $step_files = [
                    1 => 'step1_personal.php',
                    2 => 'step2_learning.php',
                    3 => 'step3_erq.php',
                    4 => 'step4_cat.php',
                    5 => 'step5_dass21.php',
                    6 => 'step6_ars30.php',
                    7 => 'step7_ffmq.php'
                ];
                include "steps/" . $step_files[$step]; 
                ?>
                
                <div class="nav-buttons">
                    <?php if ($step > 1): ?>
                        <button type="submit" name="action" value="prev" class="btn btn-outline" style="width:auto;padding:10px 30px;" formnovalidate id="back-btn">Back</button>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    <?php if ($step < 7): ?>
                        <button type="button" class="btn" style="width:auto;padding:10px 30px;" id="next-btn">Next</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-accent" style="width:auto;padding:10px 30px;" id="submit-btn">Submit Inventory</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>

    <!-- Submission Confirm Modal -->
    <div class="submit-modal-overlay" id="submit-modal">
        <div class="submit-modal">
            <div class="submit-modal-header">Ready to Submit?</div>
            <div class="submit-modal-body">
                <p>You've completed all 7 sections.<br>Once submitted, <strong>you cannot make changes.</strong></p>
                <p class="check-item">✓ All questions answered</p>
                <p class="check-item">✓ Draft will be cleared</p>
            </div>
            <div class="submit-modal-footer">
                <button class="btn btn-outline" style="flex:1;" onclick="closeModal()">Review My Answers</button>
                <button class="btn btn-accent" style="flex:1;" id="confirm-submit-btn">Submit Now</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('inventory-form');
            const draftNotice = document.getElementById('draft-saved-notice');
            const currentStep = <?php echo $step; ?>;
            const nextBtn  = document.getElementById('next-btn');
            const submitBtn = document.getElementById('submit-btn');
            const backBtn  = document.getElementById('back-btn');
            const validationMsg = document.getElementById('validation-msg');
            const isLastStep = currentStep === 7;

            // ── Validity check ──────────────────────────────────
            function getUnanswered() {
                const unanswered = [];
                const requiredInputs = form.querySelectorAll('input[required]');
                requiredInputs.forEach(inp => {
                    if (inp.type === 'checkbox' && !inp.checked) unanswered.push(inp);
                    if (inp.type === 'text' && inp.value.trim() === '') unanswered.push(inp);
                });
                const radioGroups = new Map();
                form.querySelectorAll('input[type="radio"]').forEach(r => {
                    if (!radioGroups.has(r.name)) radioGroups.set(r.name, []);
                    radioGroups.get(r.name).push(r);
                });
                radioGroups.forEach((radios, name) => {
                    if (!form.querySelector(`input[name="${name}"]:checked`)) {
                        unanswered.push(radios[0]);
                    }
                });
                return unanswered;
            }

            function checkValidity() {
                const valid = getUnanswered().length === 0;
                if (nextBtn)   { nextBtn.disabled = !valid; nextBtn.style.opacity = valid ? '1' : '0.5'; }
                if (submitBtn) { submitBtn.disabled = !valid; submitBtn.style.opacity = valid ? '1' : '0.5'; }
            }

            // ── Draft save ──────────────────────────────────────
            function collectAnswers() {
                const data = {};
                form.querySelectorAll('input, select, textarea').forEach(el => {
                    if (!el.name || ['csrf_token','current_step','action'].includes(el.name)) return;
                    if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return;
                    data[el.name] = el.value;
                });
                return data;
            }

            function saveDraft() {
                const fd = new FormData();
                fd.append('step', currentStep);
                fd.append('answers', JSON.stringify(collectAnswers()));
                fetch('actions/save_draft.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success && draftNotice) {
                            draftNotice.style.opacity = '1';
                            setTimeout(() => { draftNotice.style.opacity = '0'; }, 2000);
                        }
                    }).catch(() => {});
            }

            // ── Next button click ───────────────────────────────
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    const unanswered = getUnanswered();
                    if (unanswered.length > 0) {
                        // Highlight unanswered
                        form.querySelectorAll('.question-block').forEach(b => b.classList.remove('unanswered-error'));
                        unanswered.forEach(inp => {
                            const block = inp.closest('.question-block');
                            if (block) block.classList.add('unanswered-error');
                        });
                        if (validationMsg) validationMsg.classList.add('visible');
                        // Scroll to first unanswered
                        const firstBlock = unanswered[0].closest('.question-block');
                        if (firstBlock) firstBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                    if (validationMsg) validationMsg.classList.remove('visible');
                    form.querySelectorAll('.question-block').forEach(b => b.classList.remove('unanswered-error'));
                    saveDraft();
                    // Submit form with next action
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden'; hidden.name = 'action'; hidden.value = 'next';
                    form.appendChild(hidden);
                    form.submit();
                });
            }

            // ── Submit button → modal ───────────────────────────
            if (submitBtn) {
                submitBtn.addEventListener('click', function() {
                    const unanswered = getUnanswered();
                    if (unanswered.length > 0) {
                        form.querySelectorAll('.question-block').forEach(b => b.classList.remove('unanswered-error'));
                        unanswered.forEach(inp => {
                            const block = inp.closest('.question-block');
                            if (block) block.classList.add('unanswered-error');
                        });
                        if (validationMsg) validationMsg.classList.add('visible');
                        const firstBlock = unanswered[0].closest('.question-block');
                        if (firstBlock) firstBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                    document.getElementById('submit-modal').classList.add('active');
                });
            }

            // Confirm submit
            const confirmBtn = document.getElementById('confirm-submit-btn');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden'; hidden.name = 'action'; hidden.value = 'submit';
                    form.appendChild(hidden);
                    form.submit();
                });
            }

            // Input change → recheck validity + clear errors
            const inputs = form.querySelectorAll('input[type="radio"], input[type="checkbox"][required], input[type="text"][required]');
            inputs.forEach(inp => {
                inp.addEventListener('change', () => { checkValidity(); });
                inp.addEventListener('input', () => { checkValidity(); });
            });
            checkValidity();
        });

        function closeModal() {
            document.getElementById('submit-modal').classList.remove('active');
        }
    </script>
</body>
</html>

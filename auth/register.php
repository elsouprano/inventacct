<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../student/dashboard.php");
    }
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $middleInitial = trim($_POST['middle_initial'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $program = trim($_POST['program'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : 0;

    if (empty($firstName)) $errors['first_name'] = 'First name is required.';
    if (empty($lastName)) $errors['last_name'] = 'Last name is required.';
    if (empty($studentId)) $errors['student_id'] = 'Student ID is required.';
    
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!str_ends_with($email, '@citycollegeoftagaytay.edu.ph')) {
        $errors['email'] = 'Email must end in @citycollegeoftagaytay.edu.ph';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if (empty($program) || $program_id <= 0) $errors['program'] = 'Program is required.';
    if (empty($section)) $errors['section'] = 'Section is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email is already registered.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'student';

            $stmt = $pdo->prepare("INSERT INTO users (student_id, email, password_hash, role, first_name, middle_initial, last_name, program, program_id, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([
                    $studentId, $email, $passwordHash, $role, $firstName, 
                    empty($middleInitial) ? null : $middleInitial, 
                    $lastName, $program, $program_id, $section
                ]);
                $success = true;
                
                // Auto login
                $userId = $pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['role'] = $role;
                
                header("Location: ../student/dashboard.php");
                exit;
            } catch (PDOException $e) {
                $errors['general'] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CCT Individual Inventory System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student_mobile.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card register-card">
            <h2>Student Registration</h2>
            <p class="subtitle">Create your CCT Inventory account</p>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($errors['general']); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        <?php if (isset($errors['first_name'])): ?><span class="inline-error"><?php echo htmlspecialchars($errors['first_name']); ?></span><?php endif; ?>
                    </div>
                    <div class="form-group" style="flex: 0.3;">
                        <label for="middle_initial">M.I.</label>
                        <input type="text" id="middle_initial" name="middle_initial" maxlength="10" value="<?php echo htmlspecialchars($_POST['middle_initial'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        <?php if (isset($errors['last_name'])): ?><span class="inline-error"><?php echo htmlspecialchars($errors['last_name']); ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
                        <?php if (isset($errors['student_id'])): ?><span class="inline-error"><?php echo htmlspecialchars($errors['student_id']); ?></span><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="email">Institutional Email</label>
                        <input type="email" id="email" name="email" placeholder="@citycollegeoftagaytay.edu.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <?php if (isset($errors['email'])): ?><span class="inline-error"><?php echo htmlspecialchars($errors['email']); ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password">
                    <?php if (isset($errors['password'])): ?><span class="inline-error"><?php echo htmlspecialchars($errors['password']); ?></span><?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="program">Program</label>
                        <select id="program" name="program" onchange="loadSections(this)" required>
                            <option value="">Select Program</option>
                            <?php 
                            $db_programs = $pdo->query("SELECT id, code, name FROM programs WHERE is_active = 1 ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
                            $selectedProgram = $_POST['program'] ?? '';
                            foreach ($db_programs as $prog) {
                                $selected = $selectedProgram === $prog['code'] ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($prog['code']) . "\" data-id=\"" . $prog['id'] . "\" $selected>" . htmlspecialchars($prog['code']) . "</option>";
                            }
                            ?>
                        </select>
                        <?php if (isset($errors['program'])): ?><span class="inline-error"><?php echo htmlspecialchars($errors['program']); ?></span><?php endif; ?>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="section">Year Level &amp; Section</label>
                        <select id="section" name="section" required disabled>
                            <option value="">Select program first</option>
                        </select>
                        <input type="hidden" id="program_id" name="program_id" value="">
                        <?php if (isset($errors['section'])): ?><span class="inline-error"><?php echo htmlspecialchars($errors['section']); ?></span><?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn">Register</button>
            </form>
            <div class="text-center">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
    function loadSections(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const programId = opt ? opt.dataset.id : '';
        const secSelect = document.getElementById('section');
        const pidInput  = document.getElementById('program_id');

        secSelect.innerHTML = '<option value="">Loading...</option>';
        secSelect.disabled = true;
        pidInput.value = '';

        if (!programId) {
            secSelect.innerHTML = '<option value="">Select program first</option>';
            return;
        }

        pidInput.value = programId;

        fetch('../student/actions/get_sections.php?program_id=' + programId)
            .then(r => r.json())
            .then(sections => {
                if (!sections.length) {
                    secSelect.innerHTML = '<option value="">No sections available for this program</option>';
                    return;
                }
                const prev = <?php echo json_encode($_POST['section'] ?? ''); ?>;
                secSelect.innerHTML = '<option value="">Select Section</option>' +
                    sections.map(s =>
                        `<option value="${s.section_code}" ${s.section_code === prev ? 'selected' : ''}>${s.section_code}</option>`
                    ).join('');
                secSelect.disabled = false;
            })
            .catch(() => {
                secSelect.innerHTML = '<option value="">Error loading sections</option>';
            });
    }

    // On page load, if program was pre-selected (e.g. after validation error), reload sections
    window.addEventListener('DOMContentLoaded', () => {
        const prog = document.getElementById('program');
        if (prog.value) loadSections(prog);
    });
    </script>
</body>
</html>

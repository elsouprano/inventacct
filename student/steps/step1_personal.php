<?php
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$u = $stmt_user->fetch();

$address = $_SESSION['inventory_answers']['address'] ?? $u['address'] ?? '';
?>
<div class="question-block">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
        <div><strong>Full Name:</strong> <br><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['middle_initial'] . ' ' . $u['last_name']); ?></div>
        <div><strong>Student ID:</strong> <br><?php echo htmlspecialchars($u['student_id'] ?: 'N/A'); ?></div>
        <div><strong>Program:</strong> <br><?php echo htmlspecialchars($u['program']); ?></div>
        <div><strong>Section:</strong> <br><?php echo htmlspecialchars($u['section']); ?></div>
        <div><strong>Email:</strong> <br><?php echo htmlspecialchars($u['email']); ?></div>
        <div><strong>Is Paying Student:</strong> <br><?php echo $u['is_paying_student'] ? 'Yes' : 'No'; ?></div>
    </div>
    
    <div class="form-group" style="margin-top: 20px;">
        <label for="address" style="display: block; margin-bottom: 5px; font-weight: bold;">Current Address (Required):</label>
        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px;">
    </div>
</div>

<div class="question-block" style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid var(--accent-color);">
    <label style="display: flex; gap: 10px; align-items: flex-start; cursor: pointer;">
        <input type="checkbox" name="consent" required <?php echo isset($_SESSION['inventory_answers']['consent']) ? 'checked' : ''; ?> style="margin-top: 5px;">
        <span><strong>Data Privacy Consent:</strong> I hereby consent to the collection and processing of my personal data by the City College of Tagaytay Guidance and Counseling Services for the purpose of the Individual Inventory System. I understand that my data will be kept strictly confidential.</span>
    </label>
</div>

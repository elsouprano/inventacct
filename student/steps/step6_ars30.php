<?php
$questions = [
    "I would not accept the tutors' feedback",
    "I would use the feedback to improve my work",
    "I would just give up",
    "I would use the situation to motivate myself",
    "I would change my career plans",
    "I would probably get annoyed",
    "I would begin to think my chances of success at university were poor",
    "I would see the situation as a challenge",
    "I would do my best to stop thinking negative thoughts",
    "I would see the situation as temporary",
    "I would work harder",
    "I would probably get depressed",
    "I would try to think of new solutions",
    "I would be very disappointed",
    "I would blame the tutor",
    "I would keep trying",
    "I would not change my long-term goals and ambitions",
    "I would use my past successes to help motivate myself",
    "I would begin to think my chances of getting the job I want were poor",
    "I would start to monitor and evaluate my achievements and effort",
    "I would seek help from my tutors",
    "I would give myself encouragement",
    "I would stop myself from panicking",
    "I would try different ways to study",
    "I would set my own goals for achievement",
    "I would seek encouragement from my family and friends",
    "I would try to think more about my strengths and weaknesses",
    "I would feel like everything was ruined and was going wrong",
    "I would start to self-impose rewards and punishments depending on my performance",
    "I would look forward to showing that I can improve my grades"
];
?>
<div style="background: #f0f4f8; padding: 15px; border-radius: 4px; margin-bottom: 25px; font-style: italic;">
    <strong>Scenario:</strong> You have received your mark for a recent assignment and it is a fail. The marks for two other recent assignments were also poorer than you would want as you are aiming to get as good a degree as you can because you have clear career goals in mind and don't want to disappoint your family. The feedback from the tutor for the assignment is quite critical, including reference to lack of understanding and poor writing and expression, but it also includes ways that the work could be improved. Similar comments were made by the tutors who marked your other two assignments.
</div>
<p style="margin-bottom: 20px;">Scale: 1 (Strongly Agree) to 5 (Strongly Disagree)</p>

<?php foreach ($questions as $index => $qText): 
    $num = $index + 1;
    $key = "ars_q$num";
    $val = $_SESSION['inventory_answers'][$key] ?? '';
?>
    <div class="question-block">
        <div class="question-text"><?php echo $num . '. ' . htmlspecialchars($qText); ?></div>
        <div class="options horizontal">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="radio-wrapper">
                    <input type="radio" name="<?php echo $key; ?>" id="<?php echo $key.'_'.$i; ?>" value="<?php echo $i; ?>" <?php if($val == $i) echo 'checked'; ?>>
                    <label class="radio-label" for="<?php echo $key.'_'.$i; ?>" style="padding: 8px 15px;"><?php echo $i; ?></label>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php endforeach; ?>

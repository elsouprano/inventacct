<?php
$questions = [
    "Missed your friends from high school",
    "Missed your home",
    "Missed your parents and other family members",
    "Worried about how you will perform academically at college",
    "Worried about love or intimate relationships with others",
    "Worried about the way you look",
    "Worried about the impression you make on others",
    "Worried about being in college in general",
    "Liked your classes",
    "Liked your roommate(s)",
    "Liked being away from your parents",
    "Liked your social life",
    "Liked college in general",
    "Felt angry",
    "Felt lonely",
    "Felt anxious or nervous",
    "Felt depressed",
    "Felt optimistic about your future at college",
    "Felt good about yourself"
];
?>
<p style="margin-bottom: 20px;"><strong>Prompt:</strong> Within the LAST WEEK, to what degree have you: <br>
Scale: 1 (Not at all) - 4 (Somewhat) - 7 (A great deal)</p>

<?php foreach ($questions as $index => $qText): 
    $num = $index + 1;
    $key = "cat_q$num";
    $val = $_SESSION['inventory_answers'][$key] ?? '';
?>
    <div class="question-block">
        <div class="question-text"><?php echo $num . '. ' . htmlspecialchars($qText); ?></div>
        <div class="options horizontal">
            <?php for ($i = 1; $i <= 7; $i++): ?>
                <div class="radio-wrapper">
                    <input type="radio" name="<?php echo $key; ?>" id="<?php echo $key.'_'.$i; ?>" value="<?php echo $i; ?>" <?php if($val == $i) echo 'checked'; ?>>
                    <label class="radio-label" for="<?php echo $key.'_'.$i; ?>" style="padding: 8px 15px;"><?php echo $i; ?></label>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php endforeach; ?>

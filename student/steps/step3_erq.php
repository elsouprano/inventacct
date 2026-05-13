<?php
$questions = [
    "When I want to feel more positive emotion, I change what I'm thinking about.",
    "I keep my emotions to myself.",
    "When I want to feel less negative emotion, I change what I'm thinking about.",
    "When I am feeling positive emotions, I am careful not to express them.",
    "When I'm faced with a stressful situation, I make myself think about it in a way that helps me stay calm.",
    "I control my emotions by not expressing them.",
    "When I want to feel more positive emotion, I change the way I'm thinking about the situation.",
    "I control my emotions by changing the way I think about the situation I'm in.",
    "When I am feeling negative emotions, I make sure not to express them.",
    "When I want to feel less negative emotion, I change the way I'm thinking about the situation."
];
?>
<p style="margin-bottom: 20px;">Scale: 1 (Strongly Disagree) to 7 (Strongly Agree)</p>

<?php foreach ($questions as $index => $qText): 
    $num = $index + 1;
    $key = "erq_q$num";
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

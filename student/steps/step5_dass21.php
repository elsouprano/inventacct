<?php
$questions = [
    "I found it hard to wind down",
    "I was aware of dryness of my mouth",
    "I couldn't seem to experience any positive feeling at all",
    "I experienced breathing difficulty",
    "I found it difficult to work up the initiative to do things",
    "I tended to over-react to situations",
    "I experienced trembling (e.g. in the hands)",
    "I felt that I was using a lot of nervous energy",
    "I was worried about situations in which I might panic and make a fool of myself",
    "I felt that I had nothing to look forward to",
    "I found myself getting agitated",
    "I found it difficult to relax",
    "I felt down-hearted and blue",
    "I was intolerant of anything that kept me from getting on with what I was doing",
    "I felt I was close to panic",
    "I was unable to become enthusiastic about anything",
    "I felt I wasn't worth much as a person",
    "I felt that I was rather touchy",
    "I was aware of the action of my heart in the absence of physical exertion",
    "I felt scared without any good reason",
    "I felt that life was meaningless"
];
?>
<p style="margin-bottom: 20px;">Scale:<br>
0 = Did not apply to me at all<br>
1 = Applied to me to some degree, or some of the time<br>
2 = Applied to me to a considerable degree or a good part of time<br>
3 = Applied to me very much or most of the time</p>

<?php foreach ($questions as $index => $qText): 
    $num = $index + 1;
    $key = "dass_q$num";
    $val = $_SESSION['inventory_answers'][$key] ?? '';
?>
    <div class="question-block">
        <div class="question-text"><?php echo $num . '. ' . htmlspecialchars($qText); ?></div>
        <div class="options horizontal">
            <?php for ($i = 0; $i <= 3; $i++): ?>
                <div class="radio-wrapper">
                    <input type="radio" name="<?php echo $key; ?>" id="<?php echo $key.'_'.$i; ?>" value="<?php echo $i; ?>" <?php if((string)$val === (string)$i) echo 'checked'; ?>>
                    <label class="radio-label" for="<?php echo $key.'_'.$i; ?>" style="padding: 8px 15px;"><?php echo $i; ?></label>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php endforeach; ?>

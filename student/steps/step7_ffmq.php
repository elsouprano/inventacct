<?php
$questions = [
    "When I'm walking, I deliberately notice the sensations of my body moving.",
    "I'm good at finding words to describe my feelings.",
    "I criticize myself for having irrational or inappropriate emotions.",
    "I perceive my feelings and emotions without having to react to them.",
    "When I do things, my mind wanders off and I'm easily distracted.",
    "When I take a shower or bath, I stay alert to the sensations of water on my body.",
    "I can easily put my beliefs, opinions, and expectations into words.",
    "I don't pay attention to what I'm doing because I'm daydreaming, worrying, or otherwise distracted.",
    "I watch my feelings without getting lost in them.",
    "I tell myself I shouldn't be feeling the way I'm feeling.",
    "I notice how foods and drinks affect my thoughts, bodily sensations, and emotions.",
    "It's hard for me to find the words to describe what I'm thinking.",
    "I am easily distracted.",
    "I believe some of my thoughts are abnormal or bad and I shouldn't think that way.",
    "I pay attention to sensations, such as the wind in my hair or sun on my face.",
    "I have trouble thinking of the right words to express how I feel about things.",
    "I make judgments about whether my thoughts are good or bad.",
    "I find it difficult to stay focused on what's happening in the present.",
    "When I have distressing thoughts or images, I step back and am aware of the thought without getting taken over by it.",
    "I pay attention to sounds, such as clocks ticking, birds chirping, or cars passing.",
    "In difficult situations, I can pause without immediately reacting.",
    "When I have a sensation in my body, it's difficult for me to describe it because I can't find the right words.",
    "It seems I am running on automatic without much awareness of what I'm doing.",
    "When I have distressing thoughts or images, I feel calm soon after.",
    "I tell myself that I shouldn't be thinking the way I'm thinking.",
    "I notice the smells and aromas of things.",
    "Even when I'm feeling terribly upset, I can find a way to put it into words."
];
?>
<p style="margin-bottom: 20px;">Scale:<br>
1 = Never or very rarely true<br>
2 = Rarely true<br>
3 = Sometimes true<br>
4 = Often true<br>
5 = Very often or always true</p>

<?php foreach ($questions as $index => $qText): 
    $num = $index + 1;
    $key = "ffmq_q$num";
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

<?php
$questions = [
    1 => ['text' => "If I have to learn how to do something, I learn best when I:", 'a' => "Watch someone show me how", 'b' => "Hear someone tell me how", 'c' => "Try to do it myself"],
    2 => ['text' => "When I read, I often find that I:", 'a' => "Visualize what I am reading in my mind's eye", 'b' => "Read out loud or hear the words inside my head", 'c' => "Fidget and try to \"feel\" the content"],
    3 => ['text' => "When asked to give directions, I:", 'a' => "See the actual places in my mind as I say them or prefer to draw them", 'b' => "Have no difficulty giving them verbally", 'c' => "Have to point or move my body as I give them"],
    4 => ['text' => "If I am unsure how to spell a word, I tend to:", 'a' => "Write it to determine if it looks right", 'b' => "Spell it out loud to determine if it sounds right", 'c' => "Write it to determine if it feels right"],
    5 => ['text' => "When I write, I:", 'a' => "Am concerned with how neat and well-spaced my letters appear", 'b' => "Often say the letters and words to myself", 'c' => "Push hard on my pen and can feel the flow of the words"],
    6 => ['text' => "If I had to remember a list of items, I would remember best if I:", 'a' => "Wrote them down", 'b' => "Said them over and over to myself", 'c' => "Moved around and used my fingers to name each item"],
    7 => ['text' => "I prefer teachers who:", 'a' => "Use a board or overhead projector while they lecture", 'b' => "Talk with lots of expression", 'c' => "Use hands-on activities"],
    8 => ['text' => "When trying to concentrate, I have difficulty when:", 'a' => "There is a lot of clutter or movement in the room", 'b' => "There is a lot of noise in the room", 'c' => "I have to sit still for any length of time"],
    9 => ['text' => "When solving a problem, I:", 'a' => "Write or draw diagrams to see it", 'b' => "Talk myself through it", 'c' => "Use my entire body or move objects to help me think"],
    10 => ['text' => "When given written instructions on how to build something, I:", 'a' => "Read them silently and try to visualize how parts fit together", 'b' => "Read them out loud and talk to myself as I put parts together", 'c' => "Try to put parts together first and read later"],
    11 => ['text' => "To keep occupied while waiting, I:", 'a' => "Look around, stare, or read", 'b' => "Talk or listen to others", 'c' => "Walk around or manipulate things with my hands"],
    12 => ['text' => "If I had to verbally describe something to another person, I would:", 'a' => "Be brief because I do not like to talk at length", 'b' => "Go into great detail because I like to talk", 'c' => "Gesture and move around while talking"],
    13 => ['text' => "If someone were verbally describing something, I would:", 'a' => "Try to visualize what he/she was saying", 'b' => "Enjoy listening but want to interrupt and talk myself", 'c' => "Become bored if the description got too long"],
    14 => ['text' => "When trying to recall names, I remember:", 'a' => "Faces but forget names", 'b' => "Names but forget faces", 'c' => "The situation where I met the person"]
];
?>
<p style="margin-bottom: 20px;">Please select the option that best describes you.</p>

<?php foreach ($questions as $num => $q): 
    $key = "learning_q$num";
    $val = $_SESSION['inventory_answers'][$key] ?? '';
?>
    <div class="question-block">
        <div class="question-text"><?php echo $num . '. ' . htmlspecialchars($q['text']); ?></div>
        <div class="options">
            <?php foreach (['a' => 'V', 'b' => 'A', 'c' => 'K'] as $opt => $val_type): ?>
                <div class="radio-wrapper">
                    <input type="radio" name="<?php echo $key; ?>" id="<?php echo $key.'_'.$opt; ?>" value="<?php echo $val_type; ?>" <?php if($val === $val_type) echo 'checked'; ?>>
                    <label class="radio-label" for="<?php echo $key.'_'.$opt; ?>"><?php echo htmlspecialchars($q[$opt]); ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

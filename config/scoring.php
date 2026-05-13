<?php
// config/scoring.php

function computeAndSaveScores($pdo, $submission_id) {
    $stmt = $pdo->prepare("SELECT question_key, answer_value FROM inventory_answers WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $answers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $answers[$row['question_key']] = $row['answer_value'];
    }
    
    $scores = [];
    
    // Helper to get value
    $val = function($key) use ($answers) {
        return isset($answers[$key]) ? (int)$answers[$key] : 0;
    };
    $raw_val = function($key) use ($answers) {
        return $answers[$key] ?? '';
    };
    
    // 1. Learning Style
    $counts = ['V' => 0, 'A' => 0, 'K' => 0];
    for ($i = 1; $i <= 14; $i++) {
        $ans = $raw_val("learning_q$i");
        if (isset($counts[$ans])) $counts[$ans]++;
    }
    $max = max($counts);
    $dominants = [];
    foreach ($counts as $k => $v) {
        if ($v === $max) $dominants[] = $k;
    }
    if (count($dominants) > 1) {
        $interp = 'Mixed';
    } else {
        $map = ['V' => 'Visual', 'A' => 'Auditory', 'K' => 'Kinesthetic'];
        $interp = $map[$dominants[0]] ?? 'Mixed';
    }
    $scores[] = ['scale' => 'learning_style', 'raw_score' => $max, 'interpretation' => $interp, 'needs_counseling' => 0];

    // 2. ERQ
    $erq_cr = $val('erq_q1') + $val('erq_q3') + $val('erq_q5') + $val('erq_q7') + $val('erq_q8') + $val('erq_q10');
    $cr_interp = $erq_cr < 24 ? 'Low Cognitive Reappraisal' : ($erq_cr <= 36 ? 'Moderate Cognitive Reappraisal' : 'High Cognitive Reappraisal');
    $scores[] = ['scale' => 'erq_cr', 'raw_score' => $erq_cr, 'interpretation' => $cr_interp, 'needs_counseling' => $erq_cr < 24 ? 1 : 0];
    
    $erq_es = $val('erq_q2') + $val('erq_q4') + $val('erq_q6') + $val('erq_q9');
    $es_interp = $erq_es < 12 ? 'Low Expressive Suppression' : ($erq_es <= 20 ? 'Moderate Expressive Suppression' : 'High Expressive Suppression');
    $scores[] = ['scale' => 'erq_es', 'raw_score' => $erq_es, 'interpretation' => $es_interp, 'needs_counseling' => $erq_es > 20 ? 1 : 0];

    // 3. CAT
    $cat_neg = $val('cat_q1') + $val('cat_q2') + $val('cat_q3') + $val('cat_q4') + $val('cat_q5') + $val('cat_q6') + $val('cat_q7') + $val('cat_q8') + $val('cat_q14') + $val('cat_q15') + $val('cat_q16') + $val('cat_q17');
    $neg_interp = $cat_neg <= 36 ? 'Well Adjusted' : ($cat_neg <= 60 ? 'Moderately Adjusted' : 'Poorly Adjusted');
    $scores[] = ['scale' => 'cat_negative', 'raw_score' => $cat_neg, 'interpretation' => $neg_interp, 'needs_counseling' => $cat_neg > 60 ? 1 : 0];

    $cat_pos = $val('cat_q9') + $val('cat_q10') + $val('cat_q11') + $val('cat_q12') + $val('cat_q13') + $val('cat_q18') + $val('cat_q19');
    $pos_interp = $cat_pos <= 21 ? 'Low Positive Adjustment' : ($cat_pos <= 35 ? 'Moderate Positive Adjustment' : 'High Positive Adjustment');
    $scores[] = ['scale' => 'cat_positive', 'raw_score' => $cat_pos, 'interpretation' => $pos_interp, 'needs_counseling' => $cat_pos <= 21 ? 1 : 0];

    // 4. DASS-21
    $dass_dep = ($val('dass_q3') + $val('dass_q5') + $val('dass_q10') + $val('dass_q13') + $val('dass_q16') + $val('dass_q17') + $val('dass_q21')) * 2;
    if ($dass_dep <= 9) $dep_interp = 'Normal';
    elseif ($dass_dep <= 13) $dep_interp = 'Mild';
    elseif ($dass_dep <= 20) $dep_interp = 'Moderate';
    elseif ($dass_dep <= 27) $dep_interp = 'Severe';
    else $dep_interp = 'Extremely Severe';
    $scores[] = ['scale' => 'dass21_depression', 'raw_score' => $dass_dep, 'interpretation' => $dep_interp, 'needs_counseling' => $dass_dep >= 14 ? 1 : 0];

    $dass_anx = ($val('dass_q2') + $val('dass_q4') + $val('dass_q7') + $val('dass_q9') + $val('dass_q15') + $val('dass_q19') + $val('dass_q20')) * 2;
    if ($dass_anx <= 7) $anx_interp = 'Normal';
    elseif ($dass_anx <= 9) $anx_interp = 'Mild';
    elseif ($dass_anx <= 14) $anx_interp = 'Moderate';
    elseif ($dass_anx <= 19) $anx_interp = 'Severe';
    else $anx_interp = 'Extremely Severe';
    $scores[] = ['scale' => 'dass21_anxiety', 'raw_score' => $dass_anx, 'interpretation' => $anx_interp, 'needs_counseling' => $dass_anx >= 10 ? 1 : 0];

    $dass_str = ($val('dass_q1') + $val('dass_q6') + $val('dass_q8') + $val('dass_q11') + $val('dass_q12') + $val('dass_q14') + $val('dass_q18')) * 2;
    if ($dass_str <= 14) $str_interp = 'Normal';
    elseif ($dass_str <= 18) $str_interp = 'Mild';
    elseif ($dass_str <= 25) $str_interp = 'Moderate';
    elseif ($dass_str <= 33) $str_interp = 'Severe';
    else $str_interp = 'Extremely Severe';
    $scores[] = ['scale' => 'dass21_stress', 'raw_score' => $dass_str, 'interpretation' => $str_interp, 'needs_counseling' => $dass_str >= 19 ? 1 : 0];

    // 5. ARS-30
    $ars_pos_keys = [2,4,8,9,10,11,13,16,17,18,20,21,22,23,24,25,26,27,29,30];
    $ars_neg_keys = [1,3,5,6,7,12,14,15,19,28];
    $ars_score = 0;
    foreach ($ars_pos_keys as $k) $ars_score += $val("ars_q$k");
    foreach ($ars_neg_keys as $k) $ars_score += (6 - $val("ars_q$k"));
    if ($ars_score <= 89) $ars_interp = 'Low Resilience';
    elseif ($ars_score <= 119) $ars_interp = 'Moderate Resilience';
    else $ars_interp = 'High Resilience';
    $scores[] = ['scale' => 'ars30', 'raw_score' => $ars_score, 'interpretation' => $ars_interp, 'needs_counseling' => $ars_score <= 89 ? 1 : 0];

    // 6. FFMQ
    $ffmq_calc = function($keys, $rev_keys) use ($val) {
        $sum = 0;
        foreach ($keys as $k) {
            $sum += in_array($k, $rev_keys) ? (6 - $val($k)) : $val($k);
        }
        return $sum;
    };
    $ffmq_facets = [
        'ffmq_observing' => ['keys' => ['ffmq_q1', 'ffmq_q6', 'ffmq_q11', 'ffmq_q15', 'ffmq_q20', 'ffmq_q26'], 'rev' => [], 'max' => 30],
        'ffmq_describing' => ['keys' => ['ffmq_q2', 'ffmq_q7', 'ffmq_q12', 'ffmq_q16', 'ffmq_q22', 'ffmq_q27'], 'rev' => ['ffmq_q12', 'ffmq_q16', 'ffmq_q22'], 'max' => 30],
        'ffmq_awareness' => ['keys' => ['ffmq_q5', 'ffmq_q8', 'ffmq_q13', 'ffmq_q18', 'ffmq_q23'], 'rev' => ['ffmq_q5', 'ffmq_q8', 'ffmq_q13', 'ffmq_q18', 'ffmq_q23'], 'max' => 25],
        'ffmq_nonjudging' => ['keys' => ['ffmq_q3', 'ffmq_q10', 'ffmq_q14', 'ffmq_q17', 'ffmq_q25'], 'rev' => ['ffmq_q3', 'ffmq_q10', 'ffmq_q14', 'ffmq_q17', 'ffmq_q25'], 'max' => 25],
        'ffmq_nonreactivity' => ['keys' => ['ffmq_q4', 'ffmq_q9', 'ffmq_q19', 'ffmq_q21', 'ffmq_q24'], 'rev' => [], 'max' => 25],
    ];

    $low_ffmq_count = 0;
    $ffmq_scores = [];
    foreach ($ffmq_facets as $scale => $data) {
        $score = $ffmq_calc($data['keys'], $data['rev']);
        $pct = ($score / $data['max']) * 100;
        if ($pct < 40) {
            $interp = 'Low';
            $low_ffmq_count++;
        } elseif ($pct <= 70) {
            $interp = 'Moderate';
        } else {
            $interp = 'High';
        }
        $ffmq_scores[] = ['scale' => $scale, 'raw_score' => $score, 'interpretation' => $interp, 'needs_counseling' => 0];
    }
    
    // Update needs_counseling for the last FFMQ facet if 3 or more are Low
    if ($low_ffmq_count >= 3) {
        $ffmq_scores[count($ffmq_scores)-1]['needs_counseling'] = 1;
    }
    foreach ($ffmq_scores as $fs) {
        $scores[] = $fs;
    }

    $stmt_insert = $pdo->prepare("INSERT INTO inventory_scores (submission_id, scale, raw_score, interpretation, needs_counseling) VALUES (?, ?, ?, ?, ?)");
    foreach ($scores as $s) {
        $stmt_insert->execute([$submission_id, $s['scale'], $s['raw_score'], $s['interpretation'], $s['needs_counseling']]);
    }
}

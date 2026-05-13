<?php
// config/validity.php

function checkValidity($pdo, $submission_id) {
    // Fetch time elapsed and user
    $stmt_sub = $pdo->prepare("SELECT time_elapsed_seconds FROM inventory_submissions WHERE id = ?");
    $stmt_sub->execute([$submission_id]);
    $sub = $stmt_sub->fetch(PDO::FETCH_ASSOC);
    $time_elapsed = $sub['time_elapsed_seconds'];

    // Fetch answers
    $stmt_ans = $pdo->prepare("SELECT section, question_key, answer_value FROM inventory_answers WHERE submission_id = ?");
    $stmt_ans->execute([$submission_id]);
    $answers_by_section = [];
    $all_answers = [];
    while ($row = $stmt_ans->fetch(PDO::FETCH_ASSOC)) {
        $answers_by_section[$row['section']][$row['question_key']] = $row['answer_value'];
        $all_answers[$row['question_key']] = (int)$row['answer_value'];
    }

    $flags = [];

    // Check 1: Speed Flag
    if ($time_elapsed < 300) {
        $flags[] = "Completed in suspiciously short time ({$time_elapsed} seconds)";
    }

    // Check 2: Straight-lining Detection
    $scales_to_check = ['erq', 'cat', 'dass21', 'ars30', 'ffmq'];
    foreach ($scales_to_check as $scale) {
        if (isset($answers_by_section[$scale]) && count($answers_by_section[$scale]) > 0) {
            $vals = array_values($answers_by_section[$scale]);
            if (count(array_unique($vals)) === 1) {
                $flags[] = "Straight-line response detected in {$scale}";
            }
        }
    }

    // Check 3: Inconsistency Detection
    $val = function($key) use ($all_answers) {
        return $all_answers[$key] ?? 0;
    };

    // DASS-21
    if (abs($val('dass_q3') - $val('dass_q16')) > 2) {
        $flags[] = "Inconsistent responses: DASS-21 Depression items";
    }
    if (abs($val('dass_q1') - $val('dass_q12')) > 2) {
        $flags[] = "Inconsistent responses: DASS-21 Stress items";
    }

    // ERQ
    if (abs($val('erq_q2') - $val('erq_q6')) > 2) {
        $flags[] = "Inconsistent responses: ERQ Suppression items";
    }
    if (abs($val('erq_q1') - $val('erq_q7')) > 2) {
        $flags[] = "Inconsistent responses: ERQ Reappraisal items";
    }

    // ARS-30
    $rev_q3 = 6 - $val('ars_q3');
    if (abs($rev_q3 - $val('ars_q16')) > 2) {
        $flags[] = "Inconsistent responses: ARS-30 Perseverance items";
    }
    
    $rev_q12 = 6 - $val('ars_q12');
    if (abs($val('ars_q4') - $rev_q12) > 2) {
        $flags[] = "Inconsistent responses: ARS-30 Motivation items";
    }

    $status = 'valid';
    $flags_json = null;
    if (count($flags) > 0) {
        $status = 'requires_review';
        $flags_json = json_encode($flags);
    }

    // Update submission
    $stmt_upd = $pdo->prepare("UPDATE inventory_submissions SET validity_status = ?, validity_flags = ? WHERE id = ?");
    $stmt_upd->execute([$status, $flags_json, $submission_id]);
}

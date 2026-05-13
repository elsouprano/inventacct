<?php
// config/risk.php

function assessRisk($pdo, $submission_id) {
    // Get scores
    $stmt_scores = $pdo->prepare("SELECT scale, raw_score, interpretation FROM inventory_scores WHERE submission_id = ?");
    $stmt_scores->execute([$submission_id]);
    $scores = [];
    while ($row = $stmt_scores->fetch(PDO::FETCH_ASSOC)) {
        $scores[$row['scale']] = $row;
    }
    
    // Get FFMQ low count
    $stmt_ffmq = $pdo->prepare("SELECT COUNT(*) FROM inventory_scores WHERE submission_id = ? AND scale LIKE 'ffmq_%' AND interpretation = 'Low'");
    $stmt_ffmq->execute([$submission_id]);
    $ffmq_low_count = $stmt_ffmq->fetchColumn();
    
    // Get validity status
    $stmt_sub = $pdo->prepare("SELECT validity_status FROM inventory_submissions WHERE id = ?");
    $stmt_sub->execute([$submission_id]);
    $validity_status = $stmt_sub->fetchColumn();

    $risk_level = 'none';
    $flags = [];
    
    $score = function($scale) use ($scores) { return isset($scores[$scale]) ? $scores[$scale]['raw_score'] : null; };

    $d_dep = $score('dass21_depression');
    $d_anx = $score('dass21_anxiety');
    $d_str = $score('dass21_stress');
    $ars = $score('ars30');
    $es = $score('erq_es');
    $c_neg = $score('cat_negative');
    $c_pos = $score('cat_positive');

    $is_urgent = false;
    if ($d_dep !== null && $d_dep >= 28) { $is_urgent = true; $flags[] = "Extremely Severe Depression detected"; }
    if ($d_anx !== null && $d_anx >= 20) { $is_urgent = true; $flags[] = "Extremely Severe Anxiety detected"; }
    if ($d_str !== null && $d_str >= 34) { $is_urgent = true; $flags[] = "Extremely Severe Stress detected"; }
    if ($d_dep !== null && $d_anx !== null && $d_str !== null && $d_dep >= 14 && $d_anx >= 10 && $d_str >= 19) {
        $is_urgent = true; 
        $flags[] = "All three DASS-21 subscales scored Severe or above simultaneously";
    }

    $is_high = false;
    if ($d_dep !== null && $d_dep >= 21 && $d_dep <= 27) { $is_high = true; $flags[] = "Severe Depression detected"; }
    if ($d_anx !== null && $d_anx >= 15 && $d_anx <= 19) { $is_high = true; $flags[] = "Severe Anxiety detected"; }
    if ($d_str !== null && $d_str >= 26 && $d_str <= 33) { $is_high = true; $flags[] = "Severe Stress detected"; }
    if ($ars !== null && $ars <= 59) { $is_high = true; $flags[] = "Very Low Resilience"; }
    if ($es !== null && $es > 24) { $is_high = true; $flags[] = "Very High Expressive Suppression"; }

    $is_mod = false;
    if ($d_dep !== null && $d_dep >= 14 && $d_dep <= 20) { $is_mod = true; $flags[] = "Moderate Depression detected"; }
    if ($d_anx !== null && $d_anx >= 10 && $d_anx <= 14) { $is_mod = true; $flags[] = "Moderate Anxiety detected"; }
    if ($d_str !== null && $d_str >= 19 && $d_str <= 25) { $is_mod = true; $flags[] = "Moderate Stress detected"; }
    if ($ars !== null && $ars >= 60 && $ars <= 89) { $is_mod = true; $flags[] = "Low Resilience"; }
    if ($c_neg !== null && $c_neg > 60 && $c_pos !== null && $c_pos <= 21) { $is_mod = true; $flags[] = "Poorly Adjusted with Low Positive Affect"; }
    if ($ffmq_low_count >= 3) { $is_mod = true; $flags[] = "3 or more FFMQ facets scored 'Low'"; }

    $is_low = false;
    if ($d_dep !== null && $d_dep >= 10 && $d_dep <= 13) { $is_low = true; $flags[] = "Mild Depression indicators present"; }
    if ($d_anx !== null && $d_anx >= 8 && $d_anx <= 9) { $is_low = true; $flags[] = "Mild Anxiety indicators present"; }
    if ($d_str !== null && $d_str >= 15 && $d_str <= 18) { $is_low = true; $flags[] = "Mild Stress indicators present"; }
    if ($c_neg !== null && $c_neg >= 37 && $c_neg <= 60 && $c_pos !== null && $c_pos <= 21) { $is_low = true; $flags[] = "Moderately Adjusted with Low Positive Affect"; }

    if ($is_urgent) {
        $risk_level = 'urgent';
    } elseif ($is_high) {
        $risk_level = 'high';
    } elseif ($is_mod) {
        $risk_level = 'moderate';
    } elseif ($is_low) {
        $risk_level = 'low';
    }

    if ($is_urgent && $validity_status === 'requires_review') {
        $flags[] = "PRIORITY: Urgent risk + validity concern";
    }

    $flags_json = count($flags) > 0 ? json_encode(array_values(array_unique($flags))) : null;

    $stmt_upd = $pdo->prepare("UPDATE inventory_submissions SET risk_level = ?, risk_flags = ? WHERE id = ?");
    $stmt_upd->execute([$risk_level, $flags_json, $submission_id]);
}

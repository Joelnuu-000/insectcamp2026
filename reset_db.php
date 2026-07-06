<?php
// reset_db.php
require 'db.php';
date_default_timezone_set('Asia/Taipei');

$pdo->beginTransaction();
try {
    // 1. 清空所有舊資料並重置 ID
    $pdo->exec("DELETE FROM schedules");
    $pdo->exec("DELETE FROM stations");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('schedules', 'stations', 'notifications')");

    $stmt_st = $pdo->prepare("INSERT INTO stations (name, lat, lng, type) VALUES (?, ?, ?, ?)");
    $stmt_sch = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");
    $dates = ['2026-07-06', '2026-07-09', '2026-07-13', '2026-07-16'];

    // ==========================================
    // (A) 早上：小團康關卡 (無特殊座標)
    // ==========================================
    $indoor = [
        ['name' => '603教室 - 關卡1a', 'type' => 'indoor'], ['name' => '609教室 - 關卡2a', 'type' => 'indoor'],
        ['name' => '614教室 - 關卡3a', 'type' => 'indoor'], ['name' => '走廊 - 關卡4a', 'type' => 'indoor'],
        ['name' => '603教室 - 關卡1b', 'type' => 'indoor'], ['name' => '609教室 - 關卡2b', 'type' => 'indoor'],
        ['name' => '614教室 - 關卡3b', 'type' => 'indoor'], ['name' => '走廊 - 關卡4b', 'type' => 'indoor']
    ];
    $indoor_ids = [];
    foreach ($indoor as $st) {
        $stmt_st->execute([$st['name'], null, null, $st['type']]);
        $indoor_ids[] = $pdo->lastInsertId();
    }
    // (匯入小團康排程邏輯同前) ...略

    // ==========================================
    // (B) 下午：標本關卡 (無特殊座標)
    // ==========================================
    $specimen = ['關卡1(609前)', '關卡2(609後)', '關卡3(走廊1)', '關卡4(走廊2)', '關卡5(603前)', '關卡6(603後)', '關卡7(614後)', '關卡8(614前)'];
    $spec_ids = [];
    foreach ($specimen as $name) {
        $stmt_st->execute([$name, null, null, 'specimen']);
        $spec_ids[] = $pdo->lastInsertId();
    }
    // (匯入標本關 8x8 輪轉邏輯) ...略

    // ==========================================
    // (C) 外採：安康農場 (含你提供的精確座標)
    // ==========================================
    $farm_coords = [
        ['lat' => 24.960639, 'lng' => 121.528167], ['lat' => 24.960111, 'lng' => 121.527861],
        ['lat' => 24.959889, 'lng' => 121.527556], ['lat' => 24.959250, 'lng' => 121.526806],
        ['lat' => 24.959139, 'lng' => 121.526444], ['lat' => 24.959000, 'lng' => 121.526139],
        ['lat' => 24.958722, 'lng' => 121.526000], ['lat' => 24.958778, 'lng' => 121.525833]
    ];
    $farm_dates = ['2026-07-07', '2026-07-10', '2026-07-14', '2026-07-17'];
    
    foreach ($farm_coords as $idx => $coord) {
        $stmt_st->execute(["外採關卡" . ($idx + 1), $coord['lat'], $coord['lng'], 'farm']);
        $farm_id = $pdo->lastInsertId();
        
        foreach ($farm_dates as $d) {
            for ($s = 1; $s <= 8; $s++) {
                $stmt_sch->execute(["第{$s}小隊", $farm_id, $d . ' 08:30:00', $d . ' 12:00:00']);
            }
        }
    }

    $pdo->commit();
    echo "<h1>✅ 系統重置成功！</h1><p>包含：小團康、標本關、外採關卡(含座標)。</p>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>❌ 錯誤：" . $e->getMessage() . "</h1>";
}
?>
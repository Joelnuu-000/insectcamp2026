<?php
// reset_db.php
require 'db.php';
echo "<h2>開始重置營隊系統資料庫...</h2>";

try {
    $pdo->beginTransaction();

    // 1. 暴力清空舊資料與重置 ID
    $pdo->exec("DELETE FROM schedules");
    $pdo->exec("DELETE FROM stations");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('schedules', 'stations', 'notifications')");

    $stmt_st = $pdo->prepare("INSERT INTO stations (name, type) VALUES (?, ?)");
    $stmt_sch = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");
    $dates = ['2026-07-06', '2026-07-09', '2026-07-13', '2026-07-16'];

    // ==========================================
    // (A) 匯入早上：小團康關卡
    // ==========================================
    $indoor_stations = [
        ['name' => '603教室 - 關卡1a(巧拼前行)', 'type' => 'indoor'], ['name' => '609教室 - 關卡2a(蟲蟲在哪兒)', 'type' => 'indoor'],
        ['name' => '614教室 - 關卡3a(123偽裝蟲)', 'type' => 'indoor'], ['name' => '走廊 - 關卡4a(蟲蟲擬在哪)', 'type' => 'indoor'],
        ['name' => '603教室 - 關卡1b(昆蟲OX賽)', 'type' => 'indoor'], ['name' => '609教室 - 關卡2b(蟲蟲吃點心)', 'type' => 'indoor'],
        ['name' => '614教室 - 關卡3b(蟲蟲爭奪戰)', 'type' => 'indoor'], ['name' => '走廊 - 關卡4b(拼圖)', 'type' => 'indoor']
    ];
    $indoor_ids = [];
    foreach ($indoor_stations as $st) {
        $stmt_st->execute([$st['name'], $st['type']]);
        $indoor_ids[] = $pdo->lastInsertId(); // 動態抓取正確 ID
    }

    $morning_slots = [
        ['start' => '10:15:00', 'end' => '10:23:00', 'phase' => 'a', 'games' => [[1,2],[3,4],[5,6],[7,8]]],
        ['start' => '10:25:00', 'end' => '10:33:00', 'phase' => 'a', 'games' => [[3,5],[1,7],[2,8],[4,6]]],
        ['start' => '10:35:00', 'end' => '10:43:00', 'phase' => 'a', 'games' => [[4,8],[2,6],[3,7],[1,5]]],
        ['start' => '10:45:00', 'end' => '10:53:00', 'phase' => 'a', 'games' => [[6,7],[5,8],[1,4],[2,3]]],
        ['start' => '10:55:00', 'end' => '11:03:00', 'phase' => 'b', 'games' => [[1,2],[3,4],[5,6],[7,8]]],
        ['start' => '11:05:00', 'end' => '11:13:00', 'phase' => 'b', 'games' => [[3,5],[1,7],[2,8],[4,6]]],
        ['start' => '11:15:00', 'end' => '11:23:00', 'phase' => 'b', 'games' => [[4,8],[2,6],[3,7],[1,5]]],
        ['start' => '11:25:00', 'end' => '11:33:00', 'phase' => 'b', 'games' => [[6,7],[5,8],[1,4],[2,3]]]
    ];

    foreach ($dates as $date) {
        foreach ($morning_slots as $slot) {
            $start_dt = $date . ' ' . $slot['start'];
            $end_dt   = $date . ' ' . $slot['end'];
            $offset = ($slot['phase'] === 'a') ? 0 : 4; 
            foreach ($slot['games'] as $idx => $matchup) {
                $st_id = $indoor_ids[$offset + $idx];
                $stmt_sch->execute(["第{$matchup[0]}小隊", $st_id, $start_dt, $end_dt]);
                $stmt_sch->execute(["第{$matchup[1]}小隊", $st_id, $start_dt, $end_dt]);
            }
        }
    }

    // ==========================================
    // (B) 匯入下午：標本講解關
    // ==========================================
    $specimen_stations = [
        '關卡1(609前)', '關卡2(609後)', '關卡3(走廊1)', '關卡4(走廊2)', 
        '關卡5(603前)', '關卡6(603後)', '關卡7(614後)', '關卡8(614前)'
    ];
    $specimen_ids = [];
    foreach ($specimen_stations as $name) {
        $stmt_st->execute([$name, 'specimen']);
        $specimen_ids[] = $pdo->lastInsertId();
    }

    $afternoon_slots = [
        ['start' => '14:05:00', 'end' => '14:12:00'], ['start' => '14:12:00', 'end' => '14:19:00'],
        ['start' => '14:19:00', 'end' => '14:26:00'], ['start' => '14:26:00', 'end' => '14:33:00'],
        ['start' => '14:33:00', 'end' => '14:40:00'], ['start' => '14:40:00', 'end' => '14:47:00'],
        ['start' => '14:47:00', 'end' => '14:54:00'], ['start' => '14:54:00', 'end' => '15:01:00']
    ];
    $squad_matrix = [
        [1, 2, 3, 4, 5, 6, 7, 8], [8, 1, 2, 3, 4, 5, 6, 7], [7, 8, 1, 2, 3, 4, 5, 6], [6, 7, 8, 1, 2, 3, 4, 5],
        [5, 6, 7, 8, 1, 2, 3, 4], [4, 5, 6, 7, 8, 1, 2, 3], [3, 4, 5, 6, 7, 8, 1, 2], [2, 3, 4, 5, 6, 7, 8, 1]
    ];

    foreach ($dates as $date) {
        foreach ($afternoon_slots as $row_index => $slot) {
            $start_dt = $date . ' ' . $slot['start'];
            $end_dt   = $date . ' ' . $slot['end'];
            foreach ($specimen_ids as $col_index => $st_id) {
                $squad_num = $squad_matrix[$row_index][$col_index];
                $stmt_sch->execute(["第{$squad_num}小隊", $st_id, $start_dt, $end_dt]);
            }
        }
    }

    $pdo->commit();
    echo "<h3 style='color:green;'>✅ 所有關卡與賽程已完美重置並匯入！</h3>";
    echo "<p>請關閉此頁面，回到 <a href='/'>營隊首頁</a> 進行時光機測試！</p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h3 style='color:red;'>❌ 匯入失敗：" . $e->getMessage() . "</h3>";
}
?>
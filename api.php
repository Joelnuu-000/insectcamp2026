<?php
require 'db.php';

$action = $_GET['action'] ?? '';

// --- 1. 處理前端請求：獲取小隊當前或下一關的任務 ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_schedule') {
    $squad_id = $_GET['squad_id'] ?? '';
    // 如果有傳入模擬時間就使用模擬時間，否則使用伺服器當前時間
    $current_time = $_GET['time'] ?? date('Y-m-d H:i:s'); 
    
    // 找出該小隊「結束時間大於當前時間」的第一筆任務
    $stmt = $pdo->prepare("
        SELECT s.start_time, s.end_time, st.name, st.lat, st.lng 
        FROM schedules s
        JOIN stations st ON s.station_id = st.id
        WHERE s.squad_id = ? AND s.end_time >= ?
        ORDER BY s.start_time ASC
        LIMIT 1
    ");
    // 注意：這裡的 "第X小隊" 必須與你 seed_games.php 寫入的格式完全一致
    $stmt->execute(["第" . $squad_id . "小隊", $current_time]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo json_encode(['status' => 'success', 'data' => $task]);
    } else {
        echo json_encode(['status' => 'empty', 'message' => '目前無任務或營隊已結束']);
    }
    exit;
}

// --- 2. 處理關主發送的 Delay 通知 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'notify') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO notifications (sender, target_squad, message) VALUES (?, ?, ?)");
    $stmt->execute([$data['sender'], $data['target_squad'], $data['message']]);
    echo json_encode(['status' => 'success']);
    exit;
}

// --- 3. SSE 端點：持續推播最新通知 ---
if ($action === 'sse') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    // 獲取最後發送的通知 ID
    $last_id = isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? intval($_SERVER["HTTP_LAST_EVENT_ID"]) : 0;

    while (true) {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id > ? ORDER BY id ASC");
        $stmt->execute([$last_id]);
        $new_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($new_notifications) {
            foreach ($new_notifications as $note) {
                echo "id: " . $note['id'] . "\n";
                echo "data: " . json_encode($note) . "\n\n";
                $last_id = $note['id'];
            }
            ob_flush();
            flush();
        }
        
        sleep(2); // 降低 CPU 負載
        if (connection_aborted()) break;
    }
    exit;
}
?>
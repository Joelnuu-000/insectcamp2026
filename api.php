<?php
require 'db.php';

$action = $_GET['action'] ?? '';

// ==========================================
// 1. 獲取「小隊」當前或下一關的任務
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_schedule') {
    $squad_id = $_GET['squad_id'] ?? '';
    // 若有傳入模擬時間則使用，否則抓伺服器當前時間
    $current_time = $_GET['time'] ?? date('Y-m-d H:i:s'); 
    
    $stmt = $pdo->prepare("
        SELECT s.start_time, s.end_time, st.name, st.lat, st.lng 
        FROM schedules s
        JOIN stations st ON s.station_id = st.id
        WHERE s.squad_id = ? AND s.end_time >= ?
        ORDER BY s.start_time ASC
        LIMIT 1
    ");
    $stmt->execute(["第" . $squad_id . "小隊", $current_time]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo json_encode(['status' => 'success', 'data' => $task]);
    } else {
        echo json_encode(['status' => 'empty', 'message' => '目前無任務或營隊已結束']);
    }
    exit;
}

// ==========================================
// 2. 獲取「關主」目前應接待的小隊
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_station_schedule') {
    $station_id = $_GET['station_id'] ?? '';
    $current_time = $_GET['time'] ?? date('Y-m-d H:i:s'); 
    
    $stmt = $pdo->prepare("
        SELECT squad_id, start_time, end_time
        FROM schedules
        WHERE station_id = ? AND end_time >= ?
        ORDER BY start_time ASC
        LIMIT 1
    ");
    $stmt->execute([$station_id, $current_time]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo json_encode(['status' => 'success', 'data' => $task]);
    } else {
        echo json_encode(['status' => 'empty', 'message' => '目前無接待任務']);
    }
    exit;
}

// ==========================================
// 3. 處理關主發送的 Delay 通知 (寫入資料庫)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'notify') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO notifications (sender, target_squad, message) VALUES (?, ?, ?)");
    $stmt->execute([$data['sender'], $data['target_squad'], $data['message']]);
    echo json_encode(['status' => 'success']);
    exit;
}

// ==========================================
// 4. SSE 端點：持續推播最新通知給前端
// ==========================================
if ($action === 'sse') {
    // 解除 PHP 預設的 30 秒執行限制，防止長連線被強制中斷
    set_time_limit(0);
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    // 獲取最後發送的通知 ID，避免重複推播
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
        
        sleep(2); // 暫停 2 秒降低伺服器 CPU 負載
        if (connection_aborted()) break; // 若前端關閉網頁則自動結束迴圈
    }
    exit;
}
?>
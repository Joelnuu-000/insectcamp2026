<?php
// db.php

// 使用相對路徑 __DIR__，並指定一個專屬的 data 資料夾
// 在 Railway 預設環境中，__DIR__ 通常會是 /app，所以這會對應到 /app/data
$db_dir = __DIR__ . '/data';
$db_file = $db_dir . '/camp_database.sqlite';

// 【關鍵修正】檢查資料夾是否存在，若無則以最高權限建立
if (!is_dir($db_dir)) {
    mkdir($db_dir, 0777, true);
}

$is_new = !file_exists($db_file);

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($is_new) {
        // 建立資料表：站點(關卡)、時間表、即時通知
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT, lat REAL, lng REAL, type TEXT
            );
            CREATE TABLE IF NOT EXISTS schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                squad_id TEXT, station_id INTEGER, start_time TEXT, end_time TEXT
            );
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender TEXT, target_squad TEXT, message TEXT, 
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>
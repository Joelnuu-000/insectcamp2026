<?php
// db.php
$db_file = __DIR__ . '/camp_database.sqlite';
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
                squad_id TEXT, station_id INTEGER, start_time TEXT
            );
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender TEXT, target_squad TEXT, message TEXT, 
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        
        // 寫入安康農場與標本關測試資料
        $pdo->exec("
            INSERT INTO stations (name, lat, lng, type) VALUES 
            ('標本講解關', 25.017, 121.539, 'indoor'),
            ('安康農場野外採集', 24.954, 121.514, 'outdoor');
        ");
    }
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>
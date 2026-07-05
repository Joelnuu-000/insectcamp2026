<?php 
require 'db.php'; 

// 從資料庫撈取所有關卡站點
$stmt = $pdo->query("SELECT id, name, type FROM stations ORDER BY id ASC");
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 營隊預設為 8 個小隊
$squads = range(1, 8); 
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>營隊即時管理系統</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gray-100 p-4">

    <div id="app" class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-6">
        <h1 class="text-2xl font-bold mb-4 text-center text-indigo-600">營隊即時連線系統</h1>
        
        <select id="role-selector" class="w-full p-2 border rounded mb-4" onchange="switchView(this.value, this.options[this.selectedIndex].text)">
            <option value="">請選擇身份...</option>
            
            <optgroup label="🏕️ 小隊輔">
                <?php foreach($squads as $s): ?>
                    <option value="squad_<?= $s ?>">第 <?= $s ?> 小隊</option>
                <?php endforeach; ?>
            </optgroup>
            
            <optgroup label="🎯 關主">
                <?php foreach($stations as $st): ?>
                    <option value="station_<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                <?php endforeach; ?>
            </optgroup>
            
            <optgroup label="📡 場控">
                <option value="coordinator_0">場控總部</option>
            </optgroup>
        </select>

        <div id="view-squad" class="hidden">
            <h2 class="text-xl font-bold text-blue-700" id="squad-title">小隊任務</h2>
            <p class="text-sm text-gray-500 mb-2" id="squad-next-station">資料載入中...</p>
            <div id="map" class="h-64 w-full bg-gray-200 rounded mb-4"></div>
            <div id="squad-notifications" class="p-3 bg-red-100 text-red-700 rounded text-sm hidden"></div>
        </div>

        <div id="view-station" class="hidden">
            <h2 class="text-xl font-bold text-green-700 mb-2" id="station-title">關主控制台</h2>
            <p class="mb-4 text-gray-600" id="station-incoming">預計接待：載入中...</p>
            <button onclick="notifyDelay()" class="w-full bg-yellow-500 text-white p-3 rounded-lg font-bold shadow hover:bg-yellow-600 transition">
                ⚠️ 通知：本關卡 Delay 5 分鐘
            </button>
        </div>

        <div id="view-coordinator" class="hidden">
            <h2 class="text-xl font-bold text-purple-700 mb-2">場控全局監控</h2>
            <ul id="global-logs" class="text-sm space-y-2 h-64 overflow-y-auto border p-2 rounded bg-gray-50"></ul>
        </div>
    </div>

    <script>
        let map = null;
        let currentIdentity = { type: '', id: '', name: '' };

        // 視角切換邏輯 (支援動態 ID)
        function switchView(roleValue, roleText) {
            document.querySelectorAll('#app > div[id^="view-"]').forEach(el => el.classList.add('hidden'));
            if (!roleValue) return;

            // 解析選項 (例如 "squad_3" 拆成 type="squad", id="3")
            const [type, id] = roleValue.split('_');
            currentIdentity = { type, id, name: roleText };

            document.getElementById(`view-${type}`).classList.remove('hidden');

            // 更新 UI 標題
            if (type === 'squad') {
                document.getElementById('squad-title').innerText = roleText + " 任務";
                // 這裡後續會加上 API 請求，去撈取該小隊現在該去哪一關
            } else if (type === 'station') {
                document.getElementById('station-title').innerText = roleText;
                // 這裡後續會加上 API 請求，去撈取即將到來的小隊
            }

            // 初始化地圖 (防呆避免重複渲染)
            if (type === 'squad' && !map) {
                setTimeout(() => {
                    map = L.map('map').setView([24.954, 121.514], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                }, 100);
            }
        }

        // 推播相關邏輯保留...
        async function notifyDelay() {
            if(currentIdentity.type !== 'station') return;
            await fetch('api.php?action=notify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sender: currentIdentity.name,
                    target_squad: '全體', // 暫時寫死，後續改為動態
                    message: `${currentIdentity.name} 延遲 5 分鐘，請稍候。`
                })
            });
            alert('已發送 Delay 通知！');
        }

        const sse = new EventSource('api.php?action=sse');
        sse.onmessage = function(event) {
            const data = JSON.parse(event.data);
            if (currentIdentity.type === 'coordinator') {
                const logList = document.getElementById('global-logs');
                logList.innerHTML = `<li class="p-2 bg-white border-l-4 border-red-500 shadow-sm">[${data.created_at}] <b>${data.sender}</b>: ${data.message}</li>` + logList.innerHTML;
            }
            if (currentIdentity.type === 'squad') {
                const alertBox = document.getElementById('squad-notifications');
                alertBox.innerText = `🚨 總部/關主通知：${data.message}`;
                alertBox.classList.remove('hidden');
            }
        };
    </script>
</body>
</html>
<?php require 'db.php'; ?>
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
        
        <select id="role-selector" class="w-full p-2 border rounded mb-4" onchange="switchView(this.value)">
            <option value="">請選擇身份...</option>
            <option value="squad">小隊輔 (第1小隊)</option>
            <option value="station">關主 (標本講解關)</option>
            <option value="coordinator">場控總部</option>
        </select>

        <div id="view-squad" class="hidden">
            <h2 class="text-xl font-bold">小隊任務</h2>
            <p class="text-sm text-gray-500 mb-2">下一關：安康農場野外採集</p>
            <div id="map" class="h-64 w-full bg-gray-200 rounded mb-4"></div>
            <div id="squad-notifications" class="p-3 bg-red-100 text-red-700 rounded text-sm hidden"></div>
        </div>

        <div id="view-station" class="hidden">
            <h2 class="text-xl font-bold mb-2">關主控制台</h2>
            <p class="mb-4">預計接待：第1小隊</p>
            <button onclick="notifyDelay()" class="w-full bg-yellow-500 text-white p-2 rounded hover:bg-yellow-600">
                ⚠️ 通知：本關卡 Delay 5 分鐘
            </button>
        </div>

        <div id="view-coordinator" class="hidden">
            <h2 class="text-xl font-bold mb-2">場控全局監控</h2>
            <ul id="global-logs" class="text-sm space-y-2"></ul>
        </div>
    </div>

    <script>
        let map = null;

        // 視角切換邏輯
        function switchView(role) {
            document.querySelectorAll('#app > div[id^="view-"]').forEach(el => el.classList.add('hidden'));
            if (!role) return;
            document.getElementById(`view-${role}`).classList.remove('hidden');

            // 若切換為小隊輔，初始化地圖 (野外採集座標範例)
            if (role === 'squad' && !map) {
                setTimeout(() => {
                    map = L.map('map').setView([24.954, 121.514], 15); // 安康農場預設座標
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                    L.marker([24.954, 121.514]).addTo(map)
                        .bindPopup('目的地：安康農場').openPopup();
                }, 100);
            }
        }

        // 關主發送 Delay 通知
        async function notifyDelay() {
            await fetch('api.php?action=notify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sender: '標本講解關',
                    target_squad: '第1小隊',
                    message: '標本關延遲 5 分鐘，請稍候前往。'
                })
            });
            alert('已發送 Delay 通知給下一隊與場控！');
        }

        // 建立 SSE 連線接收即時推播
        const sse = new EventSource('api.php?action=sse');
        sse.onmessage = function(event) {
            const data = JSON.parse(event.data);
            const currentRole = document.getElementById('role-selector').value;

            // 場控接收所有廣播
            if (currentRole === 'coordinator') {
                const logList = document.getElementById('global-logs');
                logList.innerHTML += `<li class="p-2 bg-gray-100 rounded">[${data.created_at}] ${data.sender} -> ${data.target_squad}: ${data.message}</li>`;
            }
            
            // 目標小隊接收專屬通知
            if (currentRole === 'squad' && data.target_squad === '第1小隊') {
                const alertBox = document.getElementById('squad-notifications');
                alertBox.innerText = `🚨 最新消息：${data.message}`;
                alertBox.classList.remove('hidden');
            }
        };
    </script>
</body>
</html>
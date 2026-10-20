<?php
// ==========================================
// 1. เชื่อมต่อฐานข้อมูล MySQL
// ==========================================
$servername = "10.26.1.95";
$username_db = "admin_user";
$password_db = "123456";
$dbname = "my_database";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$selected_rfid = isset($_GET['rfid']) ? $_GET['rfid'] : '';
$users_data = [];
$profile_name = "Unknown Employee";

// ตัวแปรสำหรับหน้า Weekly
$w_total = 0; $w_burnout = 0; $w_neutral = 0;
$w_high = 0; $w_mod = 0; $w_low = 0;
$w_labels = []; $w_hrv_data = []; $w_cam_data = [];
$w_burnout_pct = 0; $w_neutral_pct = 0;
$w_final_score = 0; // คะแนนรวมหน้า 5 วัน

// ตัวแปรสำหรับหน้า Monthly
$m_total = 0; $m_burnout = 0; $m_neutral = 0;
$m_high = 0; $m_mod = 0; $m_low = 0;
$m_labels = []; $m_hrv_data = []; $m_cam_data = [];
$m_burnout_pct = 0; $m_neutral_pct = 0;
$m_final_score = 0; // คะแนนรวมหน้า 30 วัน

if ($selected_rfid == '') {
    // หน้าหลัก: ดึงรายชื่อพนักงาน
    $sql_users = "SELECT r.rfid_uid, r.username, 
            (SELECT camera_state FROM user_logs WHERE rfid_uid = r.rfid_uid ORDER BY recorded_time DESC LIMIT 1) as latest_state,
            (SELECT recorded_time FROM user_logs WHERE rfid_uid = r.rfid_uid ORDER BY recorded_time DESC LIMIT 1) as latest_time,
            (SELECT final_score FROM summary_results WHERE rfid_uid = r.rfid_uid ORDER BY created_at DESC LIMIT 1) as latest_score
            FROM registered_users r";
    $result_users = $conn->query($sql_users);
    if ($result_users && $result_users->num_rows > 0) {
        while($row = $result_users->fetch_assoc()) { 
            $users_data[] = $row; 
        }
    }
} else {
    // หน้า Dashboard ของแต่ละคน
    $sql_profile = "SELECT username FROM registered_users WHERE rfid_uid = '$selected_rfid' LIMIT 1";
    $res = $conn->query($sql_profile);
    if($res && $res->num_rows > 0) { $profile_name = $res->fetch_assoc()['username']; }

    // ==========================================
    // 2. ดึงข้อมูล 5 ครั้งล่าสุด (WEEKLY)
    // ==========================================
    // ดึงค่าเฉลี่ย Overall Risk (5 ครั้งล่าสุด)
    $res_w_score = $conn->query("SELECT AVG(final_score) as avg_score FROM (SELECT final_score FROM summary_results WHERE rfid_uid = '$selected_rfid' ORDER BY created_at DESC LIMIT 5) AS sub");
    if($res_w_score && $res_w_score->num_rows > 0) {
        $w_final_score = round(floatval($res_w_score->fetch_assoc()['avg_score']), 2);
    }

    $res_w = $conn->query("SELECT camera_state, survey_answer, hrv, recorded_time FROM user_logs WHERE rfid_uid = '$selected_rfid' ORDER BY recorded_time DESC LIMIT 5");
    $temp_w = [];
    if($res_w) { while($r = $res_w->fetch_assoc()) { $temp_w[] = $r; } }
    $temp_w = array_reverse($temp_w); 
    foreach($temp_w as $row) {
        $w_total++;
        if($row['camera_state'] >= 1) $w_burnout++; else $w_neutral++;
        if($row['survey_answer'] >= 50) $w_high++;
        elseif($row['survey_answer'] > 16.7) $w_mod++;
        else $w_low++;
        
        $w_labels[] = date('D H:i', strtotime($row['recorded_time']));
        $w_hrv_data[] = floatval($row['hrv']);
        $w_cam_data[] = ($row['camera_state'] >= 1) ? 100 : 0;
    }
    if($w_total > 0) {
        $w_burnout_pct = round(($w_burnout / $w_total) * 100);$w_neutral_pct = round(($w_neutral / $w_total) * 100);
    }

    // ==========================================
    // 3. ดึงข้อมูล 30 ครั้งล่าสุด (MONTHLY)
    // ==========================================
    // ดึงค่าเฉลี่ย Overall Risk (30 ครั้งล่าสุด)
    $res_m_score =$conn->query("SELECT AVG(final_score) as avg_score FROM (SELECT final_score FROM summary_results WHERE rfid_uid = '$selected_rfid' ORDER BY created_at DESC LIMIT 30) AS sub");
    if($res_m_score &&$res_m_score->num_rows > 0) {
        $m_final_score = round(floatval($res_m_score->fetch_assoc()['avg_score']), 2);
    }

    $res_m =$conn->query("SELECT camera_state, survey_answer, hrv, recorded_time FROM user_logs WHERE rfid_uid = '$selected_rfid' ORDER BY recorded_time DESC LIMIT 30");
    $temp_m = [];
    if($res_m) { while($r =$res_m->fetch_assoc()) { $temp_m[] =$r; } }
    $temp_m = array_reverse($temp_m);
    foreach($temp_m as $row) {$m_total++;
        if($row['camera_state'] >= 1) $m_burnout++; else$m_neutral++;
        if($row['survey_answer'] >= 50)$m_high++;
        elseif($row['survey_answer'] > 16.7)$m_mod++;
        else $m_low++;
        
        $m_labels[] = date('d M', strtotime($row['recorded_time']));
        $m_hrv_data[] = floatval($row['hrv']);
        $m_cam_data[] = ($row['camera_state'] >= 1) ? 100 : 0;
    }
    if($m_total > 0) {$m_burnout_pct = round(($m_burnout / $m_total) * 100);
        $m_neutral_pct = round(($m_neutral / $m_total) * 100);
    }
}$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Burnout Risk Report Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <link rel="stylesheet" href="db.css">
</head>
<body>

    <?php if ($selected_rfid == ''): ?>
    <!-- VIEW 1: หน้า MAIN -->
    <div id="view-main" class="view active">
        <div class="main-container">
            <div class="header-top">
                <div>Date: <span id="currentDate"></span><br>Time: <span id="currentTime"></span></div>
            </div>
            <div class="main-title">Burnout Risk Report for<br>Employee Attendance</div>

            <div class="emp-grid">
                <?php if (count($users_data) > 0): ?>
                    <?php foreach ($users_data as$user): ?>
                        <?php 
                            $latest_time =$user['latest_time'] ? date("h:i A", strtotime($user['latest_time'])) : "--:-- AM";
                        ?>
                        <div class="emp-card" onclick="window.location.href='dbb.php?rfid=<?php echo $user['rfid_uid']; ?>'">
                            <img src="https://via.placeholder.com/100" alt="Profile">
                            <div class="emp-info">
                                <p class="emp-name">Name: <?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="emp-dept">RFID : <?php echo htmlspecialchars($user['rfid_uid']); ?></p>
                                <div class="arrival-tag">Arrival Time: <?php echo $latest_time; ?></div>
                            </div>
                            <button class="arrow-btn">></button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center;">ยังไม่มีผู้ลงทะเบียนในระบบ</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- VIEW 2: หน้า DASHBOARD WEEKLY -->
    <div id="view-weekly" class="view active">
        <div class="dashboard-layout">
            <div class="sidebar">
                <svg class="home-icon" onclick="window.location.href='dbb.php'" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </div>
            <div class="dashboard-content">
                <div class="dash-header">
                    <div class="dash-profile">
                        <img src="https://via.placeholder.com/50" alt="Profile">
                        <h2><?php echo htmlspecialchars($profile_name); ?>'s Report</h2>
                    </div>
                </div>

                <!-- เมนูแท็บชิดซ้าย (หน้า Weekly) -->
                <div class="dash-subheader" style="display: flex; justify-content: flex-start; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <div class="active-badge">Weekly (Last 5 Scans)</div>
                    <div class="switch-badge" onclick="navigateTo('view-monthly')">Monthly</div>
                </div>

                <div class="risk-banner <?php echo ($w_final_score >= 50) ? 'risk-red' : 'risk-green'; ?>">
                    Overall Burnout Risk: <?php echo $w_final_score; ?> %
                </div>

                <div class="grid-dashboard">
                    <!-- คอลัมน์ที่ 1: ตัวเลขสถิติ Weekly -->
                    <div class="stat-group">
                        <div class="dash-card stat-block">
                            <h3>Total of inspections</h3>
                            <div class="stat-value"><?php echo $w_total; ?> Time</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Neutral <?php echo $w_neutral; ?> Time</h3>
                            <div class="stat-value"><?php echo $w_neutral_pct; ?>%</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Burnout <?php echo $w_burnout; ?> Time</h3>
                            <div class="stat-value"><?php echo $w_burnout_pct; ?>%</div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 2: Camera Burnout Detection Weekly -->
                    <div class="chart-group">
                        <div class="dash-card" style="height: 100%; display: flex; flex-direction: column; justify-content: space-evenly;">
                            <h3 style="text-align: center; margin-bottom: 5px;">Camera Burnout Detection</h3>
                            <div class="heatmap-legend" style="margin-top: 0; margin-bottom: 15px;">
                                <div class="legend-item"><div class="legend-color box-red"></div> Burnout</div>
                                <div class="legend-item"><div class="legend-color box-green"></div> Neutral</div>
                            </div>
                            <div class="donut-container" style="margin-bottom: 20px;"><canvas id="weeklyDonut1"></canvas></div>
                            
                            <hr style="border: none; border-top: 1px solid #eee; width: 100%; margin: 10px 0;">
                            
                            <h3 style="text-align: center; font-size: 14px; margin-bottom: 10px;">History (Last 5 Scans)</h3>
                            <div id="weeklyHeatmap" class="heatmap-weekly"></div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 3: Questionnaire และ HRV Weekly -->
                    <div class="chart-group">
                        <div class="dash-card">
                            <h3 style="text-align: center; margin-bottom: 5px;">Questionnaire Report</h3>
                            <div class="heatmap-legend" style="margin-top: 0; margin-bottom: 15px;">
                                <div class="legend-item"><div class="legend-color box-red"></div> High</div>
                                <div class="legend-item"><div class="legend-color" style="background-color: #fdd835;"></div> Moderate</div>
                                <div class="legend-item"><div class="legend-color box-green"></div> Low</div>
                            </div>
                            <div class="donut-container" style="height: 180px;"><canvas id="weeklyDonut2"></canvas></div>
                        </div>
                        <div class="dash-card">
                            <h3>HRV History (Last 5 Scans)</h3>
                            <div class="chart-container" style="height: 180px;"><canvas id="weeklyBar1"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW 3: หน้า DASHBOARD MONTHLY -->
    <div id="view-monthly" class="view">
        <div class="dashboard-layout">
            <div class="sidebar">
                <svg class="home-icon" onclick="window.location.href='dbb.php'" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </div>
            <div class="dashboard-content">
                <div class="dash-header">
                    <div class="dash-profile">
                        <img src="https://via.placeholder.com/50" alt="Profile">
                        <h2><?php echo htmlspecialchars($profile_name); ?>'s Report</h2>
                    </div>
                </div>

                <!-- เมนูแท็บชิดซ้าย (หน้า Monthly) จัดลำดับ Weekly-Monthly เหมือนเดิม -->
                <div class="dash-subheader" style="display: flex; justify-content: flex-start; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <div class="switch-badge" onclick="navigateTo('view-weekly')">Weekly</div>
                    <div class="active-badge">Monthly (Last 30 Scans)</div>
                </div>

                <div class="risk-banner <?php echo ($m_final_score >= 50) ? 'risk-red' : 'risk-green'; ?>">
                    Overall Burnout Risk: <?php echo $m_final_score; ?> %
                </div>

                <div class="grid-dashboard">
                    <!-- คอลัมน์ที่ 1: ตัวเลขสถิติ Monthly -->
                    <div class="stat-group">
                        <div class="dash-card stat-block">
                            <h3>Total of inspections</h3>
                            <div class="stat-value"><?php echo $m_total; ?> Time</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Neutral <?php echo $m_neutral; ?> Time</h3>
                            <div class="stat-value"><?php echo $m_neutral_pct; ?>%</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Burnout <?php echo $m_burnout; ?> Time</h3>
                            <div class="stat-value"><?php echo $m_burnout_pct; ?>%</div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 2: Camera Burnout Detection Monthly -->
                    <div class="chart-group">
                        <div class="dash-card" style="height: 100%; display: flex; flex-direction: column; justify-content: space-evenly;">
                            <h3 style="text-align: center; margin-bottom: 5px;">Camera Burnout Detection</h3>
                            <div class="heatmap-legend" style="margin-top: 0; margin-bottom: 15px;">
                                <div class="legend-item"><div class="legend-color box-red"></div> Burnout</div>
                                <div class="legend-item"><div class="legend-color box-green"></div> Neutral</div>
                            </div>
                            <div class="donut-container" style="margin-bottom: 20px;"><canvas id="monthlyDonut1"></canvas></div>
                            
                            <hr style="border: none; border-top: 1px solid #eee; width: 100%; margin: 10px 0;">
                            
                            <h3 style="text-align: center; font-size: 14px; margin-bottom: 10px;">History (Last 30 Scans)</h3>
                            <div id="monthlyHeatmap" class="heatmap-monthly"></div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 3: Questionnaire และ HRV Monthly -->
                    <div class="chart-group">
                        <div class="dash-card">
                            <h3 style="text-align: center; margin-bottom: 5px;">Questionnaire Report</h3>
                            <div class="heatmap-legend" style="margin-top: 0; margin-bottom: 15px;">
                                <div class="legend-item"><div class="legend-color box-red"></div> High</div>
                                <div class="legend-item"><div class="legend-color" style="background-color: #fdd835;"></div> Moderate</div>
                                <div class="legend-item"><div class="legend-color box-green"></div> Low</div>
                            </div>
                            <div class="donut-container" style="height: 180px;"><canvas id="monthlyDonut2"></canvas></div>
                        </div>
                        <div class="dash-card">
                            <h3>HRV History (Last 30 Scans)</h3>
                            <div class="chart-container" style="height: 180px;"><canvas id="monthlyLine1"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function updateDateTime() {
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if(dateEl && timeEl) {
                const now = new Date();
                dateEl.innerText = now.toLocaleDateString('en-GB');
                timeEl.innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }
        updateDateTime(); setInterval(updateDateTime, 1000);

        let chartsInitialized = false;
        function navigateTo(viewId) {
            document.querySelectorAll('.view').forEach(view => view.classList.remove('active'));
            document.getElementById(viewId).classList.add('active');
            window.scrollTo(0, 0);

            if (!chartsInitialized && (viewId === 'view-weekly' || viewId === 'view-monthly')) {
                initCharts();
                chartsInitialized = true;
            }
            setTimeout(() => { if (chartsInitialized) { for (let id in Chart.instances) Chart.instances[id].resize(); } }, 50);
        }

        <?php if ($selected_rfid != ''): ?>
            window.onload = function() {
                initCharts();
                chartsInitialized = true;
            };

            function initCharts() {
                Chart.defaults.font.family = "'Kanit', sans-serif";
                Chart.register(ChartDataLabels);
                Chart.defaults.set('plugins.datalabels', { display: false });

                // ปรับแต่ง Formatter ให้แสดงเฉพาะค่าที่มากกว่า 0
                const donutOptionsNoPercent = {
                    responsive: true, maintainAspectRatio: false, layout: { padding: 5 }, 
                    plugins: { 
                        legend: { display: false }, 
                        datalabels: { 
                            display: true, 
                            color: '#ffffff', 
                            font: { weight: 'bold', size: 10 }, 
                            formatter: (val) => val > 0 ? val : '' // ซ่อนตัวเลข 0
                        } 
                    }
                };

                // =============== WEEKLY CHARTS (5 Scans) ===============
                new Chart(document.getElementById('weeklyDonut1'), {
                    type: 'doughnut',
                    data: { labels: ['Burnout', 'Neutral'], datasets: [{ data: [<?php echo $w_burnout_pct; ?>, <?php echo$w_neutral_pct; ?>], backgroundColor: ['#cc0000', '#4caf50'], borderWidth: 0 }] },
                    options: { 
                        responsive: true, maintainAspectRatio: false, 
                        plugins: { 
                            legend: { display: false }, 
                            datalabels: { 
                                display: true, color: '#ffffff', font: { weight: 'bold', size: 10 }, 
                                formatter: (val) => val > 0 ? val + '%' : '' // ซ่อน 0%
                            } 
                        } 
                    }
                });

                new Chart(document.getElementById('weeklyDonut2'), {
                    type: 'doughnut',
                    data: { labels: ['High', 'Moderate', 'Low'], datasets: [{ data: [<?php echo $w_high; ?>, <?php echo $w_mod; ?>, <?php echo$w_low; ?>], backgroundColor: ['#cc0000', '#fdd835', '#4caf50'], borderWidth: 0 }] },
                    options: donutOptionsNoPercent
                });

                const wLabels = <?php echo json_encode($w_labels); ?>;
                const wHrvData = <?php echo json_encode($w_hrv_data); ?>;
                const wCamData = <?php echo json_encode($w_cam_data); ?>;
                
                new Chart(document.getElementById('weeklyBar1'), {
                    type: 'bar',
                    data: { labels: wLabels, datasets: [{ label: 'HRV', data: wHrvData, backgroundColor: ['#a984ed', '#7e5fcc', '#4c4298', '#262873', '#00134d'], borderRadius: 5 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
                });

                // =============== MONTHLY CHARTS (30 Scans) ===============
                new Chart(document.getElementById('monthlyDonut1'), {
                    type: 'doughnut',
                    data: { labels: ['Burnout', 'Neutral'], datasets: [{ data: [<?php echo $m_burnout_pct; ?>, <?php echo$m_neutral_pct; ?>], backgroundColor: ['#cc0000', '#4caf50'], borderWidth: 0 }] },
                    options: { 
                        responsive: true, maintainAspectRatio: false, 
                        plugins: { 
                            legend: { display: false }, 
                            datalabels: { 
                                display: true, color: '#ffffff', font: { weight: 'bold', size: 10 }, 
                                formatter: (val) => val > 0 ? val + '%' : '' // ซ่อน 0%
                            } 
                        } 
                    }
                });

                new Chart(document.getElementById('monthlyDonut2'), {
                    type: 'doughnut',
                    data: { labels: ['High', 'Moderate', 'Low'], datasets: [{ data: [<?php echo $m_high; ?>, <?php echo $m_mod; ?>, <?php echo$m_low; ?>], backgroundColor: ['#cc0000', '#fdd835', '#4caf50'], borderWidth: 0 }] },
                    options: donutOptionsNoPercent
                });

                const mLabels = <?php echo json_encode($m_labels); ?>;
                const mHrvData = <?php echo json_encode($m_hrv_data); ?>;
                const mCamData = <?php echo json_encode($m_cam_data); ?>;

                new Chart(document.getElementById('monthlyLine1'), {
                    type: 'line',
                    data: { labels: mLabels, datasets: [{ label: 'HRV', data: mHrvData, borderColor: '#0d47a1', backgroundColor: '#0d47a1', tension: 0.3, pointRadius: 3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { autoSkip: true, maxTicksLimit: 10, font: { size: 10 } } }, y: { beginAtZero: true, max: 100 } } }
                });

                // =============== HEATMAP ===============
                function renderHeatmap(containerId, dataArray, labelsArray, maxBoxes) {
                    const container = document.getElementById(containerId);
                    if (!container) return;
                    container.innerHTML = ''; 

                    // 1. วาดกล่องที่มีข้อมูลจริง
                    dataArray.forEach((val, index) => {
                        const box = document.createElement('div');
                        box.className = 'heatmap-box';
                        if (val === 100) {
                            box.classList.add('box-red');
                            box.title = `${labelsArray[index]} : Burnout`; 
                        } else {
                            box.classList.add('box-green');
                            box.title = `${labelsArray[index]} : Neutral`; 
                        }
                        container.appendChild(box);
                    });

                    // 2. เติมกล่องสีเทาสำรองกรณีข้อมูลไม่ครบโควตา
                    const currentCount = dataArray.length;
                    if (currentCount < maxBoxes) {
                        const emptyCount = maxBoxes - currentCount;
                        for (let i = 0; i < emptyCount; i++) {
                            const emptyBox = document.createElement('div');
                            emptyBox.className = 'heatmap-box';
                            emptyBox.title = 'Waiting for scan data...';
                            emptyBox.style.opacity = '0.5';
                            container.appendChild(emptyBox);
                        }
                    }
                }
                
                renderHeatmap('weeklyHeatmap', wCamData, wLabels, 5);
                renderHeatmap('monthlyHeatmap', mCamData, mLabels, 30);
            }
        <?php endif; ?>
    </script>
</body>
</html>
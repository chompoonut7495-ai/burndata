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

// ==========================================
// 2. เช็คว่ามีการคลิกเลือกใครมาหรือยัง (ผ่าน ?rfid=...)
// ==========================================
$selected_rfid = isset($_GET['rfid']) ? $_GET['rfid'] : '';

$users_data = [];
$profile_name = "";
$final_score = 0;

if ($selected_rfid == '') {
    // ----------------------------------------------------
    // หน้า MAIN: ถ้าไม่มี ?rfid ห้อยมา ให้ดึงรายชื่อพนักงานทุกคน
    // ----------------------------------------------------
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
    // ----------------------------------------------------
    // หน้า DASHBOARD: ถ้ามี ?rfid ห้อยมา ให้ดึงข้อมูลเฉพาะคนนั้น!
    // ----------------------------------------------------
    // 1. ดึงชื่อคนนั้น
    $sql_profile = "SELECT username FROM registered_users WHERE rfid_uid = '$selected_rfid' LIMIT 1";
    $res_profile = $conn->query($sql_profile);
    $profile_name = ($res_profile && $res_profile->num_rows > 0) ? $res_profile->fetch_assoc()['username'] : 'Unknown Employee';

    // 2. ดึงสถิติรวม 7 วัน (ใส่ WHERE rfid_uid เพื่อกรองเฉพาะคนนี้)
    $sql_weekly = "SELECT 
                    COUNT(*) as total, 
                    SUM(CASE WHEN camera_state >= 1 THEN 1 ELSE 0 END) as burnout_count,
                    SUM(CASE WHEN camera_state = 0 THEN 1 ELSE 0 END) as neutral_count
                  FROM user_logs 
                  WHERE rfid_uid = '$selected_rfid' AND recorded_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $res_weekly = $conn->query($sql_weekly);
    $weekly = $res_weekly->fetch_assoc();

    $total_weekly = $weekly['total'] > 0 ? $weekly['total'] : 1;
    $burnout_count = $weekly['burnout_count'] ?: 0;
    $neutral_count = $weekly['neutral_count'] ?: 0;
    
    $burnout_percent = round(($burnout_count / $total_weekly) * 100);
    $neutral_percent = round(($neutral_count / $total_weekly) * 100);

    // 3. ดึงคะแนน Final Score ล่าสุดของคนนี้
    $sql_score = "SELECT final_score FROM summary_results WHERE rfid_uid = '$selected_rfid' ORDER BY created_at DESC LIMIT 1";
    $res_score = $conn->query($sql_score);
    $final_score = ($res_score && $res_score->num_rows > 0) ? floatval($res_score->fetch_assoc()['final_score']) : 0;
}
$conn->close();
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
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php if ($selected_rfid == ''): ?>
    <!-- ========================================== -->
    <!-- VIEW 1: หน้า MAIN (แสดงเมื่อไม่มี ?rfid) -->
    <!-- ========================================== -->
    <div id="view-main" class="view active">
        <div class="main-container">
            <div class="header-top">
                <div>Date: <span id="currentDate"></span><br>Time: <span id="currentTime"></span></div>
            </div>
            <div class="main-title">Burnout Risk Report for<br>Employee Attendance</div>

            <div class="emp-grid">
                <?php if (count($users_data) > 0): ?>
                    <?php foreach ($users_data as $user): ?>
                        <?php 
                            $latest_time = $user['latest_time'] ? date("h:i A", strtotime($user['latest_time'])) : "--:-- AM";
                            $user_final_score = $user['latest_score'] ? floatval($user['latest_score']) : 0;
                            $is_burnout = ($user_final_score >= 50);
                        ?>
                        <!-- โค้ดพระเอก: กดแล้วส่งค่า rfid ไปที่ URL -->
                        <div class="emp-card" onclick="window.location.href='index.php?rfid=<?php echo $user['rfid_uid']; ?>'">
                            <img src="https://via.placeholder.com/100" alt="Profile">
                            <div class="emp-info">
                                <?php if($is_burnout): ?>
                                    <div class="high-burnout-tag">High Risk: <?php echo $user_final_score; ?>%</div>
                                <?php else: ?>
                                    <div class="normal-tag">Normal: <?php echo $user_final_score; ?>%</div>
                                <?php endif; ?>
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
    <!-- ========================================== -->
    <!-- VIEW 2: หน้า DASHBOARD (แสดงเมื่อคลิกพนักงานมาแล้ว) -->
    <!-- ========================================== -->
    <div id="view-weekly" class="view active">
        <div class="dashboard-layout">
            <div class="sidebar">
                <!-- กดปุ่ม Home เพื่อเคลียร์ rfid กลับหน้าแรก -->
                <svg class="home-icon" onclick="window.location.href='index.php'" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </div>
            <div class="dashboard-content">
                <div class="dash-header">
                    <div class="dash-profile">
                        <img src="https://via.placeholder.com/50" alt="Profile">
                        <!-- โชว์ชื่อเจ้าของข้อมูล -->
                        <h2><?php echo htmlspecialchars($profile_name); ?>'s Report</h2>
                    </div>
                </div>

                <div class="dash-subheader">
                    <div class="time-badge">Weekly (Last 7 Days)</div>
                </div>

                <div class="risk-banner <?php echo ($final_score >= 50) ? 'risk-red' : 'risk-green'; ?>">
                    Overall Burnout Risk: <?php echo $final_score; ?> %
                </div>

                <div class="grid-dashboard">
                    <!-- กล่องตัวเลข 3 กล่อง -->
                    <div class="stat-group">
                        <div class="dash-card stat-block">
                            <h3>Total of inspections</h3>
                            <div class="stat-value"><?php echo ($weekly['total'] > 0) ? $weekly['total'] : 0; ?> Time</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Neutral <?php echo $neutral_count; ?> Time</h3>
                            <div class="stat-value"><?php echo $neutral_percent; ?>%</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Burnout <?php echo $burnout_count; ?> Time</h3>
                            <div class="stat-value"><?php echo $burnout_percent; ?>%</div>
                        </div>
                    </div>

                    <div class="chart-group donut-group">
                        <div class="dash-card">
                            <h3>Burnout risk (Camera)</h3>
                            <div class="donut-container"><canvas id="weeklyDonut1"></canvas></div>
                        </div>
                        <div class="dash-card">
                            <h3>Questionnaire (Static)</h3>
                            <div class="donut-container"><canvas id="weeklyDonut2"></canvas></div>
                        </div>
                    </div>

                    <div class="chart-group">
                        <div class="dash-card">
                            <h3>Heart rate (BPM) (Static)</h3>
                            <div class="chart-container"><canvas id="weeklyBar1"></canvas></div>
                        </div>
                        <div class="dash-card">
                            <h3>Facial Burnout Detection (Static)</h3>
                            <div class="chart-container"><canvas id="weeklyBar2"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // ส่วนนาฬิกา
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

        // ส่วนวาดกราฟ จะทำงานก็ต่อเมื่อมีการเลือก rfid (อยู่หน้า Dashboard)
        <?php if ($selected_rfid != ''): ?>
            Chart.defaults.font.family = "'Kanit', sans-serif";
            Chart.register(ChartDataLabels);
            Chart.defaults.set('plugins.datalabels', { display: false });

            const donutOptions = {
                responsive: true, maintainAspectRatio: false, layout: { padding: 5 }, 
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } },
                    datalabels: { display: true, color: '#ffffff', font: { weight: 'bold', size: 10 }, formatter: (val) => val + '%' }
                }
            };

            // ดึงค่า % การตรวจจับใบหน้าจาก PHP เฉพาะของคนนี้ มาวาดลงกราฟวงกลม
            const burnoutPercent = <?php echo $burnout_percent; ?>;
            const neutralPercent = <?php echo $neutral_percent; ?>;

            new Chart(document.getElementById('weeklyDonut1'), {
                type: 'doughnut',
                data: { labels: ['Burnout', 'Neutral'], datasets: [{ data: [burnoutPercent, neutralPercent], backgroundColor: ['#cc0000', '#4caf50'], borderWidth: 0 }] },
                options: donutOptions
            });

            // --- กราฟสมมติอื่นๆ ด้านล่าง ---
            new Chart(document.getElementById('weeklyDonut2'), {
                type: 'doughnut',
                data: { labels: ['High', 'Moderate', 'Low'], datasets: [{ data: [50, 33.3, 16.7], backgroundColor: ['#cc0000', '#fdd835', '#4caf50'], borderWidth: 0 }] },
                options: donutOptions
            });
            new Chart(document.getElementById('weeklyBar1'), {
                type: 'bar',
                data: { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], datasets: [{ label: 'BPM', data: [150, 80, 95, 85, 95], backgroundColor: ['#a984ed', '#7e5fcc', '#4c4298', '#262873', '#00134d'], borderRadius: 5 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { size: 11 } } }, y: { beginAtZero: true, max: 200 } } }
            });
            new Chart(document.getElementById('weeklyBar2'), {
                type: 'bar',
                data: { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], datasets: [{ label: 'Score', data: [100, 80, 95, 85, 95], backgroundColor: ['#a984ed', '#7e5fcc', '#4c4298', '#262873', '#00134d'], borderRadius: 5 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { size: 11 } } }, y: { beginAtZero: true, max: 100 } } }
            });
        <?php endif; ?>
    </script>
</body>
</html>
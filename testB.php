<?php
// ==========================================
// สคริปต์จำลองข้อมูลสำหรับ Database B (st67010999_burnout)
// ==========================================
$servername = "10.26.1.95";
$username_db = "admin_user";
$password_db = "123456";
$dbname = "st67010999_burnout";[cite: 7]

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. เพิ่มข้อมูลพนักงานทดสอบลงในตาราง registered_users
$test_rfid = "RFID_TEST_01";
$test_username = "พนักงานทดสอบ (Demo)";

$check_user = $conn->query("SELECT * FROM registered_users WHERE rfid_uid = '$test_rfid'");[cite: 7]
if ($check_user->num_rows == 0) {
    $conn->query("INSERT INTO registered_users (rfid_uid, username) VALUES ('$test_rfid', '$test_username')");[cite: 7]
    echo "สร้างผู้ใช้จำลองใน Database B สำเร็จ!<br>";
}

// 2. สุ่มข้อมูลลงในตาราง user_logs (จำลอง 35 ครั้งย้อนหลัง)
echo "กำลังส่งข้อมูลจำลองเข้า user_logs...<br>";
for ($i = 35; $i >= 1; $i--) {
    $recorded_time = date('Y-m-d H:i:s', strtotime("-$i hours"));
    $camera_state = (rand(1, 10) > 7) ? 1 : 0; // โอกาสเป็น Burnout (1) หรือ Neutral (0)
    $survey_answer = rand(10, 85);
    $hrv = rand(55, 95);

    $conn->query("INSERT INTO user_logs (rfid_uid, camera_state, survey_answer, hrv, recorded_time) 
                  VALUES ('$test_rfid', '$camera_state', '$survey_answer', '$hrv', '$recorded_time')");[cite: 7]
}

// 3. สุ่มข้อมูลลงในตาราง summary_results (สำหรับคำนวณ Overall Risk)
echo "กำลังส่งข้อมูลจำลองเข้า summary_results...<br>";
for ($i = 35; $i >= 1; $i--) {
    $created_at = date('Y-m-d H:i:s', strtotime("-$i hours"));
    $final_score = rand(20, 90);

    $conn->query("INSERT INTO summary_results (rfid_uid, final_score, created_at) 
                  VALUES ('$test_rfid', '$final_score', '$created_at')");[cite: 7]
}

echo "<h3 style='color: green;'>✅ ส่งค่าจำลองเข้า Database B (st67010999_burnout) สำเร็จเรียบร้อย!</h3>";[cite: 7]
echo "<a href='index.php?rfid=$test_rfid'>คลิกที่นี่เพื่อไปดูหน้า Dashboard</a>";

$conn->close();
?>
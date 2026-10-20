<?php
// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("localhost", "root", "", "my_database");

// ดึงรหัส RFID ล่าสุดที่ Python ส่งมาพักไว้
$result = $conn->query("SELECT rfid_uid FROM temp_rfid WHERE id = 1");
if ($row = $result->fetch_assoc()) {
    echo $row['rfid_uid']; 
}
$conn->close();
?>
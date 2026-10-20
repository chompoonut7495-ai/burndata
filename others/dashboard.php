<?php
// เชื่อมต่อฐานข้อมูล (ใช้ localhost เพราะไฟล์นี้รันบนเซิร์ฟเวอร์เดียวกับฐานข้อมูล)
$conn = new mysqli("localhost", "root", "", "my_database");

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ดึงข้อมูลคะแนนล่าสุด และเชื่อมกับชื่อผู้ใช้ด้วย rfid_uid
$sql = "SELECT s.id, s.rfid_uid, s.final_score, s.created_at, r.username 
        FROM summary_results s
        LEFT JOIN registered_users r ON s.rfid_uid = r.rfid_uid
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- รีเฟรชหน้าเว็บอัตโนมัติทุก 5 วินาที เพื่อดูคะแนนเรียลไทม์ -->
    <meta http-equiv="refresh" content="5">
    <title>Dashboard - สรุปคะแนนการประเมิน</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --bg-color: #f4f7f6;
            --text-color: #333;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        h2 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .score-high {
            color: #27ae60;
            font-weight: bold;
        }
        .score-med {
            color: #f39c12;
            font-weight: bold;
        }
        .score-low {
            color: #e74c3c;
            font-weight: bold;
        }
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>📊 Dashboard สรุปผลคะแนนล่าสุด</h2>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ชื่อผู้ใช้งาน</th>
                    <th>รหัส RFID</th>
                    <th>Final Score</th>
                    <th>เวลาที่บันทึก</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    $rank = 1;
                    while($row = $result->fetch_assoc()) {
                        // จัดการกรณีที่ RFID ยังไม่ได้ลงทะเบียนชื่อ
                        $username = !empty($row["username"]) ? $row["username"] : "<i style='color:#95a5a6;'>ไม่ได้ลงทะเบียนชื่อ</i>";
                        
                        // แต่งสีให้คะแนน
                        $score = $row["final_score"];
                        $scoreClass = "";
                        if($score >= 80) { $scoreClass = "score-high"; }
                        elseif($score >= 60) { $scoreClass = "score-med"; }
                        else { $scoreClass = "score-low"; }

                        // จัดรูปแบบเวลา
                        $time = date("d/m/Y H:i:s", strtotime($row["created_at"]));

                        echo "<tr>
                                <td>{$rank}</td>
                                <td>{$username}</td>
                                <td>{$row["rfid_uid"]}</td>
                                <td class='{$scoreClass}'>{$score}</td>
                                <td>{$time}</td>
                              </tr>";
                        $rank++;
                    }
                } else {
                    echo "<tr><td colspan='5' class='empty-state'>ยังไม่มีข้อมูลการสแกนในระบบ</td></tr>";
                }
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
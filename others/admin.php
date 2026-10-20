<?php
$conn = new mysqli("localhost", "root", "", "my_database");
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rfid_uid = $_POST['rfid_uid'];
    $username = $_POST['username'];
    
    // เช็คว่ามีในระบบหรือยัง
    $check_sql = "SELECT id FROM registered_users WHERE rfid_uid = '$rfid_uid'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        $message = "<p style='color:red;'>❌ RFID นี้มีในระบบแล้ว!</p>";
    } else {
        // บันทึกคนใหม่
        $sql = "INSERT INTO registered_users (rfid_uid, username) VALUES ('$rfid_uid', '$username')";
        if ($conn->query($sql) === TRUE) {
            $message = "<p style='color:green;'>✅ เพิ่มผู้ใช้ $username สำเร็จ!</p>";
            // ล้างค่ากระดานให้ว่าง เพื่อรอบัตรใบต่อไป
            $conn->query("UPDATE temp_rfid SET rfid_uid = '' WHERE id = 1");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบลงทะเบียนผู้ใช้งาน</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px;}
        .box { background: white; padding: 20px; border-radius: 8px; width: 350px; margin: 0 auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1);}
        input { width: 90%; padding: 8px; margin-bottom: 15px; }
        button { width: 95%; padding: 10px; background: #2c3e50; color: white; border: none; cursor: pointer; border-radius: 4px;}
        button:hover { background: #1a252f; }
    </style>
</head>
<body>
    <div class="box">
        <h3>➕ ลงทะเบียน RFID ใหม่</h3>
        <?php echo $message; ?>
        <form method="POST" action="">
            <label>1. แตะบัตรที่เครื่องอ่าน (รหัสจะขึ้นอัตโนมัติ)</label><br>
            <!-- ช่องนี้จะรอรับข้อมูลจาก JavaScript -->
            <input type="text" name="rfid_uid" id="rfid_input" required readonly placeholder="...รอรับข้อมูล..."><br>
            
            <label>2. ชื่อผู้ใช้งาน (Username)</label><br>
            <input type="text" name="username" id="username_input" required placeholder="กรอกชื่อผู้ใช้"><br>
            
            <button type="submit">บันทึกผู้ใช้</button>
        </form>
    </div>

    <script>
        // โค้ดสำหรับเช็ครหัสใหม่ทุกๆ 1 วินาที
        setInterval(function() {
            let currentRfid = document.getElementById('rfid_input').value;
            if (currentRfid === "") {
                fetch('get_latest_rfid.php')
                    .then(response => response.text())
                    .then(data => {
                        let rfid = data.trim();
                        if(rfid !== "") {
                            document.getElementById('rfid_input').value = rfid; // ใส่รหัส
                            document.getElementById('username_input').focus();  // เด้งไปช่องพิมพ์ชื่อ
                        }
                    });
            }
        }, 1000);
    </script>
</body>
</html>
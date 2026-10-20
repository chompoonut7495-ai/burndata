import mysql.connector
from datetime import datetime  # 1. เพิ่มการ import datetime

# ======================================
# ค่าจากแต่ละ Module
# ======================================

camera_state = 0         # int: เช่น 0 = Normal, 1 = Abnormal
hrv = 52.4               # float: ค่า HRV
survey_answer = 8.0      # float: คะแนนรวมหรือค่าเฉลี่ยแบบสอบถาม
current_time = datetime.now()  # 2. ดึงเวลาปัจจุบันของระบบ

db = None
cursor = None

try:
    # 1. เชื่อมต่อ Database
    db = mysql.connector.connect(
        host="10.26.1.95",
        user="admin_user",
        password="123456",
        database="my_database",
        port=3306,
    )

    cursor = db.cursor()

    # 2. ข้อมูล RFID
    new_rfid = "9A2C79A1"
    new_username = "พี่บี"

    # 3. ตรวจสอบว่ามีพนักงานแล้วหรือยัง
    cursor.execute(
        """
        SELECT rfid_uid
        FROM registered_users
        WHERE rfid_uid = %s
        LIMIT 1
        """,
        (new_rfid,)
    )

    if cursor.fetchone() is None:
        cursor.execute(
            """
            INSERT INTO registered_users
                (rfid_uid, username)
            VALUES (%s, %s)
            """,
            (new_rfid, new_username)
        )
        print("เพิ่มข้อมูล RFID สำเร็จ")

    # 4. บันทึกข้อมูลเข้าตาราง user_logs (เพิ่มคอลัมน์ recorded_time)
    sql_module = """
        INSERT INTO user_logs
            (recorded_time, rfid_uid, camera_state, hrv, survey_answer)
        VALUES (%s, %s, %s, %s, %s)
    """

    module_values = (
        current_time,  # ใส่ค่าเวลาปัจจุบัน
        new_rfid,
        camera_state,
        hrv,
        survey_answer
    )

    cursor.execute(sql_module, module_values)

    # 5. ยืนยันการบันทึก
    db.commit()

    print("บันทึกข้อมูลทุก Module สำเร็จ")
    print("Recorded Time:", current_time)
    print("RFID:", new_rfid)
    print("Camera State:", camera_state)
    print("HRV:", hrv)
    print("Survey Answer:", survey_answer)
    print("Record ID:", cursor.lastrowid)

except mysql.connector.Error as err:
    if db is not None and db.is_connected():
        db.rollback()

    print(f"เกิดข้อผิดพลาด: {err}")

finally:
    if cursor is not None:
        cursor.close()

    if db is not None and db.is_connected():
        db.close()
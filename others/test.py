import mysql.connector
from datetime import datetime
import random
import time

# --- แก้เลข IP ตรงนี้ให้เป็นของเครื่อง A (เซิร์ฟเวอร์) ---
server_ip = "10.26.1.95" 
# -----------------------------------------------

try:
    print(f"⏳ กำลังจำลองการสแกน 5 รอบ เพื่อคำนวณผลเข้าตาราง summary_results...")
    
    conn = mysql.connector.connect(
        host=server_ip,
        user="admin_user",
        password="123456",
        database="my_database"
    )
    # เพิ่ม buffered=True เพื่อป้องกันปัญหา Unread result
    cursor = conn.cursor(dictionary=True, buffered=True)

    test_rfid = "3AB9224A"  # รหัสที่ลงทะเบียนชื่อไว้
    
    # 1. วนลูปจำลองสแกนและบันทึกเข้าตาราง user_logs 5 ครั้ง
    for i in range(1, 6):
        test_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        cam_state = random.choice([0, 1]) # 0 = Neutral, 1 = Burnout
        hrv = 16.7                        # ค่า HRV อิงตามเกณฑ์น้อย/ผิดปกติ
        survey_answer = 50.0              # คะแนนแบบสอบถามที่แปลงแล้ว

        cursor.execute("""
            INSERT INTO user_logs (recorded_time, rfid_uid, camera_state, hrv, survey_answer) 
            VALUES (%s, %s, %s, %s, %s)
        """, (test_time, test_rfid, cam_state, hrv, survey_answer))
        conn.commit()
        print(f"   📥 รอบที่ {i}/5: ส่งค่าเข้า user_logs สำเร็จ")
        time.sleep(0.5)

    # 2. ดึงข้อมูล 5 รอบล่าสุดมาคำนวณตามสูตรใหม่
    print(f"\n⚙️ ครบ 5 รอบ! กำลังคำนวณคะแนนตามสูตรน้ำหนักใหม่ (กล้อง 50%, คำถาม 30%, HRV 20%)...")
    
    # ลบ ออกแล้ว
    cursor.execute("SELECT * FROM user_logs WHERE rfid_uid = %s ORDER BY id DESC LIMIT 5", (test_rfid,))
    logs = cursor.fetchall()
    
    sum_daily_scores = 0
    
    for row in logs:
        # แปลงค่าตามเกณฑ์น้ำหนัก
        cam_score = 100.0 if row['camera_state'] >= 1 else 0.0
        survey_score = float(row['survey_answer'])
        hrv_score = float(row['hrv'])
        
        # สูตรคำนวณคะแนนรายวัน (Daily Score)
        daily_score = (cam_score * 0.50) + (survey_score * 0.30) + (hrv_score * 0.20)
        sum_daily_scores += daily_score
        
    # สูตรคำนวณค่าเฉลี่ย 5 วัน (5-Day Moving Average)
    final_score = sum_daily_scores / 5.0
    
    # 3. บันทึกผลลัพธ์ลง summary_results
    recorded_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("""
        INSERT INTO summary_results (rfid_uid, final_score, created_at) 
        VALUES (%s, %s, %s)
    """, (test_rfid, round(final_score, 2), recorded_time))
    conn.commit()

    print(f"✅ คำนวณสำเร็จ! บันทึกคะแนนรวม ({final_score:.2f}%) ลงตาราง summary_results เรียบร้อย!")
    print("👉 เปิดหน้าเว็บ dashboard.php ดูได้เลยครับ คะแนนจะขึ้นโชว์ในตารางทันที")

except mysql.connector.Error as err:
    print(f"❌ Error: {err}")
finally:
    if 'conn' in locals() and conn.is_connected():
        cursor.close()
        conn.close()
import mysql.connector
from datetime import datetime
import random
import time

# ==========================================
# 1. ตั้งค่า Database
# ==========================================
server_ip = "10.26.1.95"
user_db = "admin_user"
pass_db = "123456"
db_name = "my_database"

# รายชื่อ RFID ที่ต้องการทดสอบ
rfid_list = ["3AB9224A", "9A2C79A1"]

try:
    conn = mysql.connector.connect(
        host=server_ip,
        user=user_db,
        password=pass_db,
        database=db_name
    )
    cursor = conn.cursor(dictionary=True)

    # วนลูปทำให้ทีละ RFID
    for rfid in rfid_list:
        print(f"--- 👤 เริ่มจำลองข้อมูลสำหรับ RFID: {rfid} ---")
        
        # 2. จำลองการสแกนและบันทึกเข้า user_logs 5 ครั้ง
        for i in range(1, 6):
            test_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            # ==========================================
            # กำหนดค่าให้ต่างกันชัดเจนตาม RFID
            # ==========================================
            if rfid == "3AB9224A":
                # คนที่ 1: เสี่ยงสูงมาก (High Burnout)
                cam_state = 1                                     # กล้องตรวจจับเจอ Burnout ตลอด
                survey_ans = random.choice([50.0, 33.3])          # ตอบแบบสอบถามไปทางเครียดมาก
                hrv = round(random.uniform(70.0, 95.0), 2)        # ดันคะแนน HRV ให้สูงเพื่อให้ผลรวมพุ่ง
            else:
                # คนที่ 2: ปกติ (Normal / Neutral)
                cam_state = 0                                     # กล้องตรวจจับเจอ Neutral ตลอด
                survey_ans = random.choice([16.7, 33.3])          # ตอบแบบสอบถามไปทางเครียดน้อย
                hrv = round(random.uniform(10.0, 30.0), 2)        # ดันคะแนน HRV ให้ต่ำเพื่อให้ผลรวมน้อย

            cursor.execute("""
                INSERT INTO user_logs (recorded_time, rfid_uid, camera_state, hrv, survey_answer) 
                VALUES (%s, %s, %s, %s, %s)
            """, (test_time, rfid, cam_state, hrv, survey_ans))
            conn.commit()
            
            print(f"  📥 รอบที่ {i}/5: ส่งค่าเข้า user_logs สำเร็จ (Cam: {cam_state}, Survey: {survey_ans}, HRV: {hrv})")
            time.sleep(0.5) # หน่วงเวลาเล็กน้อย

        # 3. ดึงข้อมูล 5 รอบล่าสุดมาคำนวณตามสูตร
        print("  ⚙️ ครบ 5 รอบ! กำลังคำนวณคะแนน Final Score...")
        cursor.execute("SELECT * FROM user_logs WHERE rfid_uid = %s ORDER BY id DESC LIMIT 5", (rfid,))
        logs = cursor.fetchall()
        
        sum_daily_scores = 0
        
        for row in logs:
            # ก. กล้อง (Camera): Neutral (0) = 0 คะแนน, Burnout (1+) = 100 คะแนน
            cam_score = 100.0 if row['camera_state'] >= 1 else 0.0
            
            # ข. แบบสอบถาม (Questionnaire): ดึงค่าที่เก็บไว้ (50, 33.3 หรือ 16.7)
            survey_score = float(row['survey_answer'])
            
            # ค. HRV (สรีรวิทยา): ดึงค่าที่เก็บไว้
            hrv_score = float(row['hrv'])
            
            # คำนวณ Daily Score ของแต่ละวัน
            # Daily Score = (กล้อง * 0.50) + (คำถาม * 0.30) + (HRV * 0.20)
            daily_score = (cam_score * 0.50) + (survey_score * 0.30) + (hrv_score * 0.20)
            sum_daily_scores += daily_score

        # คำนวณค่าเฉลี่ย 5 วัน (5-Day Moving Average)
        # สูตร = ผลรวม Daily Score ย้อนหลัง 5 วัน / 5
        final_5_day_score = sum_daily_scores / 5.0
        
        # 4. บันทึกผลลัพธ์ลง summary_results
        created_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        cursor.execute("""
            INSERT INTO summary_results (rfid_uid, final_score, created_at) 
            VALUES (%s, %s, %s)
        """, (rfid, round(final_5_day_score, 2), created_at))
        conn.commit()

        print(f"  ✅ คำนวณสำเร็จ! บันทึกคะแนนรวม ({final_5_day_score:.2f}%) ลงตาราง summary_results เรียบร้อย!\n")

    print("🎉 จำลองข้อมูลเสร็จสิ้นทั้ง 2 RFID! เปิดดูที่หน้า Dashboard ได้เลยครับ")

except mysql.connector.Error as err:
    print(f"❌ Error Database: {err}")
finally:
    if 'conn' in locals() and conn.is_connected():
        cursor.close()
        conn.close()
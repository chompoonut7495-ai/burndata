import mysql.connector
from datetime import datetime
import random

# --- แก้เลข IP ตรงนี้ให้เป็นของเครื่องเซิร์ฟเวอร์ ---
server_ip = "10.26.1.95" 
# ----------------------------------------

def connect_db():
    return mysql.connector.connect(
        host=server_ip,
        user="admin_user",
        password="123456",
        database="my_database"
    )

print("▶️ เริ่มระบบอ่าน RFID พร้อมสูตรคำนวณใหม่ (พิมพ์รหัส หรือจำลองการสแกน)")

while True:
    scanned_rfid = input("\n[รอรับบัตร] กรุณาแตะบัตร (หรือพิมพ์รหัส): ").strip()
    if not scanned_rfid:
        continue
    
    try:
        conn = connect_db()
        cursor = conn.cursor(dictionary=True)

        # 1. เช็คว่ามีบัตรนี้ลงทะเบียนในระบบหรือยัง
        cursor.execute("SELECT username FROM registered_users WHERE rfid_uid = %s", (scanned_rfid,))
        user = cursor.fetchone()

        if not user:
            print(f"⚠️ บัตรใหม่! ส่งรหัส {scanned_rfid} ไปที่หน้า Admin เพื่อลงทะเบียน...")
            cursor.execute("UPDATE temp_rfid SET rfid_uid = %s WHERE id = 1", (scanned_rfid,))
            conn.commit()
            continue

        username = user['username']
        print(f"👤 ผู้ใช้: {username} (กำลังบันทึกข้อมูลเซ็นเซอร์...)")
        
        # 2. จำลองค่าดิบที่ได้จากเซ็นเซอร์ในรอบนี้
        # - camera_state: 0 = Neutral (0 คะแนน), 1 หรือ 2 = Burnout (100 คะแนน)
        # - survey_answer: สมมติค่าเฉลี่ยแบบสอบถามรายวัน (แปลงเป็นคะแนนดิบตามเกณฑ์: มาก=50, กลาง=33.3, น้อย=16.7)
        # - hrv_value: สมมติค่า HRV (ถ้าผิดปกติ/น้อย จะได้ 16.7)
        raw_camera = random.choice([0, 1]) # 0=Neutral, 1=Burnout
        raw_survey = random.choice([50.0, 33.3, 16.7]) 
        raw_hrv = 16.7 # อิงตามเกณฑ์น้อย/ผิดปกติ
        
        recorded_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        # บันทึกข้อมูลดิบลงตาราง user_logs 
        # (หมายเหตุ: ในตาราง user_logs เดิมเราเก็บ heart_rate กับ survey_answer สามารถประยุกต์ช่องเก็บค่าได้ตามความเหมาะสม)
        cursor.execute("""
            INSERT INTO user_logs (recorded_time, rfid_uid, camera_state, heart_rate, survey_answer) 
            VALUES (%s, %s, %s, %s, %s)
        """, (recorded_time, scanned_rfid, raw_camera, raw_hrv, raw_survey))
        conn.commit()
        
        # 3. ตรวจสอบจำนวนรอบสะสมของ RFID นี้
        cursor.execute("SELECT COUNT(*) AS total FROM user_logs WHERE rfid_uid = %s", (scanned_rfid,))
        total_scan = cursor.fetchone()['total']
        print(f"✅ บันทึกข้อมูลรอบที่ {total_scan} สำเร็จ!")
        
        # 4. เมื่อครบทุกๆ 5 รอบ นำมาคำนวณ 5-Day Moving Average ตามสูตรใหม่
        if total_scan > 0 and total_scan % 5 == 0:
            print(f"🎉 ครบ 5 รอบ! กำลังคำนวณคะแนนตามสูตรน้ำหนักใหม่ (กล้อง 50%, คำถาม 30%, HRV 20%)...")
            
            # ดึงข้อมูล 5 รอบล่าสุด
            cursor.execute("SELECT * FROM user_logs WHERE rfid_uid = %s ORDER BY id DESC LIMIT 5", (scanned_rfid,))
            logs = cursor.fetchall()
            
            sum_daily_scores = 0
            
            for row in logs:
                # แปลงค่าตามเกณฑ์
                # ก. กล้อง (Camera): Neutral (0) = 0 คะแนน, Burnout (1+) = 100 คะแนน
                cam_score = 100.0 if row['camera_state'] >= 1 else 0.0
                
                # ข. แบบสอบถาม (Questionnaire): ดึงค่าที่เก็บไว้ (50, 33.3 หรือ 16.7)
                survey_score = float(row['survey_answer'])
                
                # ค. HRV (สรีรวิทยา): ดึงค่าที่เก็บไว้
                hrv_score = float(row['heart_rate'])
                
                # คำนวณ Daily Score ของแต่ละวัน
                # Daily Score = (กล้อง * 0.50) + (คำถาม * 0.30) + (HRV * 0.20)
                daily_score = (cam_score * 0.50) + (survey_score * 0.30) + (hrv_score * 0.20)
                sum_daily_scores += daily_score

            # คำนวณค่าเฉลี่ย 5 วัน (5-Day Moving Average)
            # สูตร = ผลรวม Daily Score ย้อนหลัง 4 วัน + วันปัจจุบัน / 5
            final_5_day_score = sum_daily_scores / 5.0
            
            # บันทึกผลลัพธ์ลงตาราง summary_results
            cursor.execute("""
                INSERT INTO summary_results (rfid_uid, final_score, created_at) 
                VALUES (%s, %s, %s)
            """, (scanned_rfid, round(final_5_day_score, 2), recorded_time))
            conn.commit()
            
            print(f"💾 คำนวณ 5-Day Average เรียบร้อย! คะแนนสะสม = {final_5_day_score:.2f}% (ส่งเข้า Dashboard แล้ว)")
            
    except mysql.connector.Error as err:
        print(f"❌ Error: {err}")
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()
# Prompt สำหรับ Codex / Cursor — TECHED SPORT Scoring Refactor V2

> วิธีใช้: เปิด Coding Agent ที่ Root ของโปรเจค และแนบ/วางไฟล์ `docs/scoring-architecture-v2.md` ไว้ในโปรเจคก่อนส่ง Prompt นี้

คุณเป็น **Senior PHP Software Architect + Full-Stack Developer** ที่ต้องช่วยปรับโปรเจค **TECHED SPORT** ซึ่งใช้ PHP, JavaScript, SQL Database และ **Tailwind CSS**

## งานของคุณ

อ่าน `docs/scoring-architecture-v2.md` ให้เข้าใจ โดยเฉพาะ Catalog ประเภทการบันทึกผล 21 ประเภท, การเลือก UI จากฐานข้อมูล, เงื่อนไขการบังคับประเภทคะแนน และแผนแยกโค้ด แล้วตรวจสอบ Codebase จริงก่อนแก้ไข

**เป้าหมายระยะยาว**
- เมื่อสร้าง/ตั้งค่ารายการกีฬาใน Tournament ให้ผู้จัดกำหนด `scoring_type` และ `scoring_config` บันทึกลงฐานข้อมูล
- เมื่อ Match Official เลือกแมตช์ Backend ต้องอ่านค่าที่ผูกกับแมตช์แล้วเปิด Recorder UI ที่ตรงกับ Type **โดยอัตโนมัติและบังคับ**
- UI, Rule Validator และการบันทึกต้องใช้ Type เดียวกัน; Browser เปลี่ยน Type เองไม่ได้
- แยกโค้ด Tournament / Matches / Scoring ออกจาก `index.php` และ `AppService.php` ตามความรับผิดชอบ เพื่อให้แก้โค้ดแต่ละส่วนง่ายขึ้น
- รองรับกีฬาอีกหลายประเภทผ่าน UI Template + Rule Config ไม่ใช่ 1 Page ต่อ 1 กีฬา

**งานที่ให้ทำรอบนี้ (สำคัญ):** เริ่มจาก **Phase 0: Audit + Phase 1: Extract Views ของโมดูล Scoring เท่านั้น** เมื่อสำเร็จจึงรายงานผล ไม่ทำ Migration, เปลี่ยน Logic การคิดคะแนน หรือสร้างระบบ 21 ประเภทพร้อมกันโดยพลการ

## ขั้นตอนทำงาน

1. ตรวจ `git status`, เส้นทาง Routing, `index.php`, `api.php`, `AppService.php`, ส่วน Auth/DB, หน้า Match Recording และไฟล์ Tailwind/JS ที่เกี่ยวข้อง
2. สรุป **Dependency Map** ว่าหน้าบันทึกอยู่ที่ไหน เรียก API/Function อะไร ใช้ข้อมูลใด มีประเภทคะแนนใดรองรับอยู่แล้ว
3. วางโครงสร้างไฟล์ที่เหมาะกับโค้ดเดิม ไม่จำเป็นต้องสร้างทุกไฟล์ในตัวอย่างของ `.md` และต้องไม่สร้าง Component/Service ซ้ำ
4. **Extract Scoring Views/Pages** ออกจากไฟล์ใหญ่ทีละส่วน โดยรักษา DOM IDs, Form Fields, Event Listeners, Routing, API Contract และการทำงานเดิม
5. ใช้ Tailwind CSS เดิม ห้ามย้อนกลับไปสร้าง CSS ปกติหรือเปลี่ยน Framework
6. ทดสอบหน้า Match Selector, หน้าบันทึกแต่ละ Type ที่มีอยู่จริง, เพิ่ม/แก้/โหลดคะแนน, Permission, สถานะการแข่งขัน และหน้าอื่นที่อาจได้รับผลกระทบ
7. สรุปไฟล์ที่เปลี่ยน Test ที่รันจริง สิ่งที่ยังไม่ได้ทดสอบ และแผน **Phase ถัดไป** ก่อนลงมือแก้ Scoring Logic/Schema

## ข้อบังคับด้านความปลอดภัยและความถูกต้อง

- **ห้าม Rewrite ทั้งระบบ**; ห้ามแก้ News/User Management หรือหน้าที่ไม่เกี่ยวข้อง
- **ห้ามเปลี่ยน Business Logic, Scoring Rules, Database Schema หรือ API Contract ใน Phase 0–1**
- **ห้ามลบข้อมูลการแข่งขันเดิม** หรือเดา Scoring Type ของผลเก่า
- ห้ามทำลาย Git Working Tree, Reset, Clean หรือ Force Checkout โดยไม่รับอนุญาต
- ห้ามส่งคะแนนสำเร็จปลอมหรือทดสอบด้วย Mock Data แล้วอ้างว่าระบบจริงผ่าน
- ใน Phase ถัดไป การเลือก UI ต้องอิงจากฐานข้อมูลฝั่ง Server และต้องไม่ fallback เป็น `SCORE`
- ห้ามให้ผู้บันทึกเปลี่ยนประเภทคะแนนด้วย Query String, Hidden Input หรือ API Request
- หากพบ Dependency ที่ทำให้แยก View ไม่ปลอดภัย ให้รายงานจุดเสี่ยงและเลือกวิธีเปลี่ยนแปลงที่เล็กที่สุด

## รูปแบบรายงานผล

ตอบเป็นภาษาไทยแบบกระชับ โดยแสดง:
- Current architecture & จุดเสี่ยง
- Proposed folder structure ตามไฟล์ที่มีอยู่จริง
- รายชื่อไฟล์ที่แก้และเหตุผล
- Test commands / Results ตามจริง
- สิ่งที่รักษาไว้ไม่เปลี่ยน
- งานถัดไปที่จำเป็นสำหรับ Config-Driven Scoring พร้อมข้อควรระวัง

**เริ่มงาน Phase 0 ก่อน ตรวจสอบ Source Code จริงทุกครั้ง แล้วทำ Phase 1 เฉพาะส่วนที่ปลอดภัยและมีวิธีทดสอบ หากไม่แน่ใจว่า Business Logic เปลี่ยนหรือไม่ ให้คงของเดิมและรายงานแทนการคาดเดา**

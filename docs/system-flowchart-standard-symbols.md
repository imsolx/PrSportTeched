# Flowchart ระบบ TECHED SPORT ตามสัญลักษณ์มาตรฐาน

แผนภาพนี้ใช้สัญลักษณ์ตามหลักการเขียน Flowchart และรวมขั้นตอนที่ใช้ร่วมกันไว้เพียงจุดเดียว เพื่อลดทั้งความซ้ำซ้อนของแผนภาพและความซ้ำซ้อนของข้อมูลในระบบ

```mermaid
flowchart TD
    START([เริ่มต้น]) --> USER_INPUT[/ผู้ใช้เลือกเข้าเว็บไซต์สาธารณะ<br/>หรือเข้าสู่ระบบเจ้าหน้าที่/]
    USER_INPUT --> USER_TYPE{เป็นเจ้าหน้าที่หรือไม่}

    USER_TYPE -->|ไม่ใช่| READ_PUBLIC[อ่านข้อมูลที่เผยแพร่แล้ว]
    READ_PUBLIC --> CENTRAL_DB
    CENTRAL_DB --> PUBLIC_OUTPUT[/แสดงกีฬา ตารางแข่งขัน ผลการแข่งขัน<br/>ตารางคะแนน ข่าว และรายชื่อนักกีฬา/]
    PUBLIC_OUTPUT --> PUBLIC_MORE{ต้องการดูข้อมูลอื่นหรือไม่}
    PUBLIC_MORE -->|ใช่| READ_PUBLIC
    PUBLIC_MORE -->|ไม่| END_PUBLIC([สิ้นสุด])

    USER_TYPE -->|ใช่| LOGIN_INPUT[/กรอกอีเมลและรหัสผ่าน/]
    LOGIN_INPUT --> AUTH_PROCESS[ตรวจสอบบัญชี รหัสผ่าน<br/>สถานะบัญชี และบทบาท]
    AUTH_PROCESS --> AUTH_OK{ข้อมูลเข้าสู่ระบบถูกต้องหรือไม่}
    AUTH_OK -->|ไม่ถูกต้อง| LOGIN_ERROR[/แสดงข้อความผิดพลาด/]
    LOGIN_ERROR --> LOGIN_INPUT
    AUTH_OK -->|ถูกต้อง| SESSION_PROCESS[สร้าง Session และ CSRF token]
    SESSION_PROCESS --> ROLE_PROCESS[ตรวจ Role กีฬา สังกัด<br/>และแมตช์ที่รับผิดชอบ]
    ROLE_PROCESS --> MENU_INPUT[/เลือกงานที่ได้รับสิทธิ์/]
    MENU_INPUT --> MENU{ประเภทงาน}

    MENU -->|ข้อมูลหลัก| MASTER_PROCESS[จัดการหน่วยงาน กีฬา<br/>กติกา และบัญชีผู้ใช้]

    MENU -->|เตรียมการแข่งขัน| TOUR_PROCESS[สร้างทัวร์นาเมนต์ เลือกกีฬา<br/>และกำหนดกติกา]
    TOUR_PROCESS --> STUDENT_INPUT[/เพิ่มหรือนำเข้านักศึกษา/]
    STUDENT_INPUT --> TEAM_PROCESS[สร้างทีมและเลือกสมาชิก<br/>จากทะเบียนนักศึกษากลาง]
    TEAM_PROCESS --> TEAM_READY{กติกา ทีม และสมาชิกพร้อมหรือไม่}
    TEAM_READY -->|ไม่พร้อม| TOUR_PROCESS
    TEAM_READY -->|พร้อม| FIXTURE_PROCESS[สร้างตารางแข่งขัน<br/>และมอบหมายเจ้าหน้าที่]

    MENU -->|บันทึกผล| MATCH_INPUT[/เลือกแมตช์/]
    MATCH_INPUT --> MATCH_SCOPE{ได้รับมอบหมายและ<br/>อยู่ในขอบเขตหรือไม่}
    MATCH_SCOPE -->|ไม่ผ่าน| ACCESS_ERROR[/แสดงข้อความปฏิเสธการเข้าถึง/]
    ACCESS_ERROR --> MENU_INPUT
    MATCH_SCOPE -->|ผ่าน| MATCH_PROCESS[โหลดแมตช์ กติกา ช่วงเวลา<br/>รายชื่อ และผลเดิม]
    MATCH_PROCESS --> SCORE_TYPE{ชนิดการคิดคะแนน}
    SCORE_TYPE -->|SCORE| SCORE_INPUT[/บันทึกคะแนนและเหตุการณ์/]
    SCORE_TYPE -->|SET| SET_INPUT[/บันทึกคะแนนรายเซต/]
    SCORE_TYPE -->|TIME หรือ RACE_POSITION| RANK_INPUT[/บันทึกเวลาและอันดับ/]
    SCORE_INPUT --> CONFIRM_RESULT{ยืนยันผลสุดท้ายหรือไม่}
    SET_INPUT --> CONFIRM_RESULT
    RANK_INPUT --> CONFIRM_RESULT
    CONFIRM_RESULT -->|ยังไม่ยืนยัน| MATCH_PROCESS
    CONFIRM_RESULT -->|ยืนยัน| RESULT_PROCESS[ตรวจผู้ชนะและคำนวณตารางคะแนนใหม่]

    MENU -->|ข่าวประชาสัมพันธ์| NEWS_INPUT[/เลือกแมตช์หรือทัวร์นาเมนต์<br/>และกรอกข้อมูลข่าว/]
    NEWS_INPUT --> NEWS_PROCESS[สร้างหรือแก้ไขข่าว<br/>ด้วยตนเองหรือ AI]
    NEWS_PROCESS --> PUBLISH{เผยแพร่ข่าวหรือไม่}
    PUBLISH -->|ไม่| DRAFT_PROCESS[บันทึกเป็นฉบับร่าง]
    PUBLISH -->|ใช่| PUBLISH_PROCESS[กำหนดสถานะ Published]

    MASTER_PROCESS --> WRITE_SUBROUTINE
    FIXTURE_PROCESS --> WRITE_SUBROUTINE
    RESULT_PROCESS --> WRITE_SUBROUTINE
    DRAFT_PROCESS --> WRITE_SUBROUTINE
    PUBLISH_PROCESS --> WRITE_SUBROUTINE

    WRITE_SUBROUTINE[[กระบวนการตรวจสอบและบันทึกข้อมูลร่วม]] --> WRITE_CHECK
    WRITE_CHECK{CSRF สิทธิ์ Validation<br/>Revision และ Request ID ผ่านหรือไม่}
    WRITE_CHECK -->|ไม่ผ่าน| WRITE_ERROR[/แจ้งข้อผิดพลาด<br/>และไม่เปลี่ยนข้อมูล/]
    WRITE_ERROR --> MENU_INPUT
    WRITE_CHECK -->|ผ่าน| TRANSACTION_PROCESS[บันทึกแบบ Transaction<br/>พร้อม Audit log และ Revision]
    TRANSACTION_PROCESS --> CENTRAL_DB[(ฐานข้อมูลกลางแบบ Single Source of Truth<br/>ข้อมูลหลัก - การแข่งขัน - ผลลัพธ์ - ข่าว)]
    TRANSACTION_PROCESS --> CONTINUE{ทำรายการอื่นต่อหรือไม่}
    CONTINUE -->|ใช่| MENU_INPUT
    CONTINUE -->|ไม่| LOGOUT_PROCESS[ออกจากระบบและทำลาย Session]
    LOGOUT_PROCESS --> END_STAFF([สิ้นสุด])
```

## ความหมายของสัญลักษณ์

| สัญลักษณ์ | ชื่อ | การใช้งานในแผนภาพ |
|---|---|---|
| วงรี | Terminator | จุดเริ่มต้นและสิ้นสุด |
| สี่เหลี่ยมด้านขนาน | Input / Output | การกรอก เลือก รับ หรือแสดงข้อมูล |
| สี่เหลี่ยมผืนผ้า | Process | ขั้นตอนประมวลผลหรือการทำงาน |
| สี่เหลี่ยมผืนผ้าขอบคู่ | Predefined process | กระบวนการย่อยที่เรียกใช้ร่วมกันหลายจุด |
| สี่เหลี่ยมข้าวหลามตัด | Decision | ตรวจเงื่อนไขและแยกเส้นทาง ใช่/ไม่ใช่ |
| ทรงกระบอก | Database | ฐานข้อมูลกลางของระบบ |
| ลูกศรทึบ | Flow line | ลำดับการทำงาน |

## หลักการลดความซ้ำซ้อนของข้อมูล

1. เก็บนักศึกษาหนึ่งครั้งใน `students` และใช้ `team_members` เชื่อมเข้าทีม
2. เก็บกีฬาและกติกาใน `sports` และ `sport_rule_versions` แล้วอ้างอิงด้วย ID
3. เก็บทีมหนึ่งครั้ง แล้วให้แมตช์อ้างอิงผ่าน `team1_id` และ `team2_id`
4. เก็บผลรวมหนึ่งระเบียนต่อแมตช์ใน `match_results`; ประวัติการแก้ไขแยกเก็บในตาราง revision
5. ใช้ `match_events` เป็นแหล่งเหตุการณ์จริง และคำนวณคะแนนหรือตารางอันดับจากผลที่ยืนยันแล้ว
6. ข่าวอ้างอิงรายการแข่งขันหรือแมตช์ผ่าน `news_links` แทนการคัดลอกข้อมูลต้นทาง
7. ทุกการเขียนข้อมูลผ่านจุดตรวจสอบและ Transaction กลางชุดเดียวกัน

> หมายเหตุ: หน้าบันทึกผลเฉพาะทางในระบบปัจจุบันมีสำหรับ `SCORE` และ `SET`; ชนิด `TIME` และ `RACE_POSITION` ใช้กระบวนการรายการผู้เข้าแข่งขันและ API ที่เกี่ยวข้อง

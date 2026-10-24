# Flowchart ระบบ TECHED SPORT แบบลดความซ้ำซ้อนของข้อมูล

เอกสารนี้สรุปกระบวนการหลักของระบบเป็นแผนภาพเดียว โดยรวมขั้นตอนตรวจสอบและการบันทึกที่ใช้ร่วมกันไว้จุดเดียว และกำหนดให้ข้อมูลแต่ละประเภทมีแหล่งอ้างอิงหลักเพียงแห่งเดียว (Single Source of Truth)

```mermaid
flowchart TD
    START([เริ่มใช้งาน]) --> USER_TYPE{ประเภทผู้ใช้}

    USER_TYPE -->|ผู้เยี่ยมชม| PUBLIC[เว็บไซต์สาธารณะ]
    USER_TYPE -->|เจ้าหน้าที่| LOGIN[เข้าสู่ระบบ]
    LOGIN --> AUTH{บัญชีและรหัสผ่านถูกต้องหรือไม่}
    AUTH -->|ไม่ถูกต้อง| LOGIN_ERROR[แจ้งข้อผิดพลาดและบันทึก Audit log]
    LOGIN_ERROR --> LOGIN
    AUTH -->|ถูกต้อง| SESSION[สร้าง Session และ CSRF token]
    SESSION --> SCOPE[ตรวจ Role, กีฬา, สังกัด และแมตช์ที่รับผิดชอบ]
    SCOPE --> WORKSPACE{เลือกงานตามสิทธิ์}

    subgraph PREPARE[เตรียมการแข่งขัน]
        direction TD
        MASTER[จัดการหน่วยงาน กีฬา และกติกา]
        TOUR[สร้างทัวร์นาเมนต์และเลือกกีฬา]
        REGISTER[เพิ่มหรือนำเข้านักศึกษา]
        TEAM[สร้างทีมและเพิ่มสมาชิกจากทะเบียนกลาง]
        READY{กติกา ทีม และสมาชิกพร้อมหรือไม่}
        FIXTURE[สร้างตารางแข่งขันและมอบหมายเจ้าหน้าที่]

        MASTER --> TOUR
        TOUR --> REGISTER
        REGISTER --> TEAM
        TEAM --> READY
        READY -->|ไม่พร้อม| TOUR
        READY -->|พร้อม| FIXTURE
    end

    subgraph COMPETE[ดำเนินการแข่งขัน]
        direction TD
        MATCH_ACCESS{ได้รับมอบหมายและอยู่ในขอบเขตหรือไม่}
        MATCH_CENTER[เปิด Match Center และโหลดกติกาของแมตช์]
        SCORE_TYPE{ชนิดการบันทึกผล}
        SCORE[คะแนนและเหตุการณ์]
        SET[คะแนนรายเซต]
        RANK[เวลาและอันดับ]
        CONFIRM[ยืนยันผลการแข่งขัน]
        RECALC[คำนวณตารางคะแนนใหม่]

        MATCH_ACCESS -->|ไม่ผ่าน| DENIED[ปฏิเสธการเข้าถึง]
        MATCH_ACCESS -->|ผ่าน| MATCH_CENTER
        MATCH_CENTER --> SCORE_TYPE
        SCORE_TYPE -->|SCORE| SCORE
        SCORE_TYPE -->|SET| SET
        SCORE_TYPE -->|TIME หรือ RACE_POSITION| RANK
        SCORE --> CONFIRM
        SET --> CONFIRM
        RANK --> CONFIRM
        CONFIRM --> RECALC
    end

    subgraph SUPPORT[งานสนับสนุน]
        direction TD
        NEWS[ร่างข่าวด้วยตนเองหรือ AI ตรวจ และเผยแพร่]
        ADMIN[จัดการบัญชี คำเชิญ และ Audit log]
    end

    WORKSPACE -->|ตั้งค่าระบบหรือทัวร์นาเมนต์| MASTER
    WORKSPACE -->|บันทึกผล| MATCH_ACCESS
    WORKSPACE -->|ประชาสัมพันธ์| NEWS
    WORKSPACE -->|ดูแลระบบ| ADMIN
    FIXTURE --> MATCH_ACCESS
    CONFIRM --> NEWS

    MASTER --> WRITE_GATE
    TOUR --> WRITE_GATE
    REGISTER --> WRITE_GATE
    TEAM --> WRITE_GATE
    FIXTURE --> WRITE_GATE
    SCORE --> WRITE_GATE
    SET --> WRITE_GATE
    RANK --> WRITE_GATE
    CONFIRM --> WRITE_GATE
    NEWS --> WRITE_GATE
    ADMIN --> WRITE_GATE

    subgraph SHARED[กระบวนการเขียนข้อมูลร่วม]
        direction TD
        WRITE_GATE{CSRF, สิทธิ์, Validation, Revision และ Request ID ผ่านหรือไม่}
        WRITE_GATE -->|ไม่ผ่าน| WRITE_ERROR[คืนข้อผิดพลาดโดยไม่เปลี่ยนข้อมูล]
        WRITE_GATE -->|ผ่าน| TRANSACTION[บันทึกแบบ Transaction]
        TRANSACTION --> AUDIT[บันทึก Audit log และประวัติ Revision]
    end

    subgraph DATA[ฐานข้อมูลกลาง: เก็บข้อมูลแต่ละเรื่องเพียงจุดเดียว]
        direction LR
        MASTER_DATA[(ข้อมูลหลัก<br/>มหาวิทยาลัย คณะ สาขา<br/>นักศึกษา กีฬา กติกา ผู้ใช้)]
        EVENT_DATA[(ข้อมูลดำเนินงาน<br/>ทัวร์นาเมนต์ ทีม สมาชิก<br/>แมตช์ ช่วงเวลา การมอบหมาย)]
        RESULT_DATA[(ข้อมูลผลลัพธ์<br/>เหตุการณ์ ผลการแข่งขัน อันดับ<br/>ข่าว และประวัติการเปลี่ยนแปลง)]

        MASTER_DATA -. อ้างอิงด้วย ID .-> EVENT_DATA
        EVENT_DATA -. อ้างอิงด้วย ID .-> RESULT_DATA
    end

    AUDIT --> MASTER_DATA
    AUDIT --> EVENT_DATA
    AUDIT --> RESULT_DATA
    WRITE_ERROR --> WORKSPACE

    MASTER_DATA -. อ่านข้อมูลร่วม .-> PREPARE
    EVENT_DATA -. อ่านข้อมูลร่วม .-> COMPETE
    RESULT_DATA -. เฉพาะข้อมูลที่เผยแพร่ .-> PUBLIC
    PUBLIC --> PUBLIC_MENU{เลือกดูข้อมูล}
    PUBLIC_MENU --> VIEW[กีฬา ตารางแข่ง ผล สายการแข่งขัน<br/>ตารางคะแนน ข่าว Hall of Fame และนักกีฬา]
    VIEW --> END_PUBLIC([จบการดูข้อมูล])

    WORKSPACE --> LOGOUT[ออกจากระบบ]
    LOGOUT --> END_STAFF([สิ้นสุด Session])
```

## หลักการลดความซ้ำซ้อนที่ใช้

1. เก็บนักศึกษาเพียงครั้งเดียวใน `students` แล้วเชื่อมทีมผ่าน `team_members` แทนการคัดลอกชื่อและสังกัดลงทุกทีม
2. เก็บกีฬาและเวอร์ชันกติกาใน `sports` และ `sport_rule_versions`; ทัวร์นาเมนต์เลือกใช้งานผ่านตารางเชื่อม และแมตช์ตรึง `rule_version_id`
3. เก็บทีมครั้งเดียวต่อทัวร์นาเมนต์ กีฬา และสังกัด แล้วให้แมตช์อ้างอิง `team1_id` และ `team2_id`
4. เก็บผลรวมของแมตช์หนึ่งรายการใน `match_results` เพียงระเบียนเดียว; การแก้ไขเก็บในตาราง revision โดยไม่สร้างผลซ้ำ
5. เก็บเหตุการณ์จริงใน `match_events`; คะแนนและตารางอันดับคำนวณจากข้อมูลที่ยืนยันแล้ว เพื่อลดค่าที่ค้างหรือขัดแย้งกัน
6. ข่าวเชื่อมแหล่งข้อมูลผ่าน `news_links` แทนการทำสำเนาข้อมูลแมตช์หรือทัวร์นาเมนต์ทั้งหมด
7. รวมการตรวจ CSRF, สิทธิ์, validation, revision และ request ID เป็น Write Gateway เดียวก่อนทุกการเปลี่ยนแปลงข้อมูล
8. หน้าเว็บไซต์สาธารณะอ่านจากข้อมูลกลางชุดเดียวกัน แต่แสดงเฉพาะข้อมูลที่มีสถานะเผยแพร่แล้ว

## สัญลักษณ์

- เส้นทึบ: ลำดับการทำงาน
- เส้นประ: การอ่านหรืออ้างอิงข้อมูล
- สี่เหลี่ยมข้าวหลามตัด: จุดตัดสินใจ
- ทรงกระบอก: กลุ่มข้อมูลในฐานข้อมูลกลาง

> หมายเหตุ: หน้าบันทึกผลเฉพาะทางในระบบปัจจุบันมีสำหรับ `SCORE` และ `SET`; ชนิด `TIME` และ `RACE_POSITION` ใช้กระบวนการรายการผู้เข้าแข่งขันและ API ที่เกี่ยวข้อง

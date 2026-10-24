# ข้อกำหนด Flowchart ระบบ TECHED SPORT

> เอกสารนี้ถอดจากโค้ดปัจจุบันในโปรเจกต์ `prsport` ณ วันที่ 18 ตุลาคม 2026 เพื่อใช้เป็น source of truth สำหรับให้ AI หรือเครื่องมือวาดแผนภาพสร้าง Flowchart โดยไม่ต้องเดากระบวนการเพิ่มเอง

## 1. คำสั่งสำหรับ AI ที่นำเอกสารนี้ไปสร้างภาพ

สร้าง Flowchart ภาษาไทยของระบบ **TECHED SPORT** โดยยึด node, decision, actor, สถานะ และเส้นเชื่อมในเอกสารนี้อย่างเคร่งครัด

- ใช้ Swimlane แยกผู้ใช้งาน: ผู้เยี่ยมชม, System Admin, Operation Admin, Sport Manager, Match Official และฝ่ายประชาสัมพันธ์
- ใช้สัญลักษณ์วงรีสำหรับ Start/End, สี่เหลี่ยมสำหรับ Process, ข้าวหลามตัดสำหรับ Decision และทรงกระบอกสำหรับ Database
- เส้นทึบหมายถึงลำดับงาน เส้นประหมายถึงการอ่านข้อมูลหรือผลที่ถูกส่งไปแสดงต่อ
- แสดงเส้นทางผิดพลาดของ Login, CSRF, สิทธิ์, ข้อมูลไม่ครบ, revision conflict และ validation ไม่ผ่าน
- ห้ามเพิ่มขั้นตอนสมัครนักกีฬาด้วยตนเองหรือสมัครทีมจากหน้าสาธารณะ เพราะระบบไม่มีขั้นตอนดังกล่าว
- ห้ามวาดว่าทุกชนิดคะแนนมีหน้าบันทึกเฉพาะพร้อมใช้งาน ปัจจุบันหน้าเฉพาะมีเฉพาะ `SCORE` และ `SET`
- ถ้าสร้างภาพเดียว ให้ใช้หัวข้อ 3 เป็นแกนหลัก และยุบรายละเอียดจากหัวข้อ 4-8 เป็น subprocess

## 2. ขอบเขตและผู้มีส่วนร่วม

### 2.1 Actor และสิทธิ์หลัก

| Actor | รหัสบทบาท | ขอบเขตงานจริง |
|---|---|---|
| ผู้เยี่ยมชม | `guest` | ดูหน้าแรก กีฬา ตารางแข่งขัน ผลการแข่งขัน สายการแข่งขัน ตารางคะแนน ข่าว Hall of Fame และรายชื่อนักกีฬา/ทีมที่เผยแพร่ |
| System Admin | `super_admin` | จัดการได้ทุกส่วน รวมผู้ใช้ คำเชิญ และ Audit log |
| Operation Admin | `admin` | ข้อมูลกลาง กีฬา/กติกา ทัวร์นาเมนต์ นักกีฬา ทีม แมตช์ การมอบหมายผู้ตัดสิน ผล ข่าว และ AI แต่ไม่จัดการบัญชีผู้ใช้ |
| Sport Manager | `sport_head` | จัดการเฉพาะกีฬาและสังกัดในขอบเขตของตน จัดการทีม/สมาชิก และบันทึกผลเฉพาะแมตช์ที่ได้รับมอบหมาย |
| Match Official | `result_officer` | ดูและบันทึกผลเฉพาะแมตช์ที่ได้รับมอบหมาย รวมเวลา เหตุการณ์ คะแนน และผลจัดอันดับ |
| ฝ่ายประชาสัมพันธ์ | `public_relations` | สร้าง/แก้ไข/เผยแพร่ข่าว อัปโหลดภาพ และใช้ AI ช่วยร่างข่าวจากข้อมูลการแข่งขัน |

### 2.2 เงื่อนไขสิทธิ์ของการบันทึกผลแมตช์

`super_admin` และ `admin` จัดการผลได้ทุกแมตช์ ส่วน `result_officer` และ `sport_head` ต้องผ่านทุกเงื่อนไขที่เกี่ยวข้องดังนี้:

1. มีรายการมอบหมายใน `match_official_assignments`
2. กีฬาอยู่ใน `sport_ids` ของผู้ใช้ เมื่อบัญชีถูกจำกัดกีฬาหรือเป็น `sport_head`
3. ถ้ามอบหมายเจาะจงทีม ทีมดังกล่าวต้องเป็นหนึ่งในสองทีมของแมตช์
4. ถ้าบัญชีถูกจำกัด `department_code` ต้องตรงกับทีมที่ตนรับผิดชอบ

## 3. Master Flowchart ของระบบ

```mermaid
flowchart TD
    START([เริ่มใช้งานระบบ]) --> ACTOR{ผู้ใช้ประเภทใด}

    ACTOR -->|ผู้เยี่ยมชม| PUBLIC[เปิดหน้าเว็บไซต์สาธารณะ]
    PUBLIC --> PUBLIC_MENU{เลือกข้อมูลที่ต้องการดู}
    PUBLIC_MENU --> PHOME[หน้าแรกและรายการสด]
    PUBLIC_MENU --> PSPORT[กีฬา]
    PUBLIC_MENU --> PSCHEDULE[ตารางแข่งขัน]
    PUBLIC_MENU --> PRESULT[ผลการแข่งขัน]
    PUBLIC_MENU --> PBRACKET[สายการแข่งขัน]
    PUBLIC_MENU --> PSTANDING[ตารางคะแนน]
    PUBLIC_MENU --> PNEWS[ข่าวและ Hall of Fame]
    PUBLIC_MENU --> PATHLETE[นักกีฬาและทีม]
    PHOME --> END_PUBLIC([จบการดูข้อมูล])
    PSPORT --> END_PUBLIC
    PSCHEDULE --> END_PUBLIC
    PRESULT --> END_PUBLIC
    PBRACKET --> END_PUBLIC
    PSTANDING --> END_PUBLIC
    PNEWS --> END_PUBLIC
    PATHLETE --> END_PUBLIC

    ACTOR -->|เจ้าหน้าที่| LOGIN[กรอกอีเมลและรหัสผ่าน]
    LOGIN --> AUTH{บัญชีมีอยู่ เปิดใช้งาน รองรับบทบาท และรหัสผ่านถูกต้องหรือไม่}
    AUTH -->|ไม่ผ่าน| LOGIN_ERROR[แจ้งสาเหตุและบันทึก login_failed ใน Audit log]
    LOGIN_ERROR --> LOGIN
    AUTH -->|ผ่าน| SESSION[Regenerate session และออก CSRF token]
    SESSION --> ROLE{ตรวจบทบาทและขอบเขตข้อมูล}

    ROLE -->|System Admin| USERS[จัดการผู้ใช้ คำเชิญ และ Audit log]
    ROLE -->|System Admin หรือ Operation Admin| MASTER[จัดการข้อมูลองค์กร กีฬา ชุดกติกา และทัวร์นาเมนต์]
    ROLE -->|Operation Admin| REGISTRATION[เพิ่ม แก้ไข หรือนำเข้านักกีฬา]
    ROLE -->|Admin หรือ Sport Manager| TEAMWORK[สร้างทีมและจัดสมาชิกเข้าทีม]
    ROLE -->|Admin| ASSIGN[สร้างแมตช์และมอบหมาย Match Official หรือ Sport Manager]
    ROLE -->|ผู้ได้รับสิทธิ์บันทึกผล| OFFICIAL[เปิดศูนย์บันทึกผลของแมตช์ที่ได้รับมอบหมาย]
    ROLE -->|ฝ่ายประชาสัมพันธ์| PR[จัดทำและเผยแพร่ข่าว]

    MASTER --> TOURNAMENT_FLOW[[Workflow เตรียมทัวร์นาเมนต์ 11 ขั้น]]
    REGISTRATION --> TOURNAMENT_FLOW
    TEAMWORK --> TOURNAMENT_FLOW
    ASSIGN --> MATCH_FLOW[[Workflow การแข่งขันและบันทึกผล]]
    OFFICIAL --> MATCH_FLOW
    MATCH_FLOW --> CONFIRMED[(ผลการแข่งขันที่ยืนยันแล้ว)]
    CONFIRMED --> STANDINGS[คำนวณตารางคะแนนใหม่ เมื่อกติกากำหนดให้นับคะแนน]
    CONFIRMED --> PR
    PR --> NEWS_STATUS{สถานะข่าว}
    NEWS_STATUS -->|draft| EDIT_NEWS[แก้ไขข่าวหรือใช้ AI ช่วยร่าง]
    EDIT_NEWS --> NEWS_STATUS
    NEWS_STATUS -->|published| PUBLIC_DB[(ข้อมูลสาธารณะ)]
    STANDINGS --> PUBLIC_DB
    CONFIRMED --> PUBLIC_DB
    PUBLIC_DB -.แสดงผล.-> PUBLIC
    USERS --> LOGOUT[ออกจากระบบ]
    TOURNAMENT_FLOW --> LOGOUT
    MATCH_FLOW --> LOGOUT
    NEWS_STATUS --> LOGOUT
    LOGOUT --> END([สิ้นสุด Session ผู้ใช้])
```

## 4. Flowchart การเตรียมทัวร์นาเมนต์ 11 ขั้น

```mermaid
flowchart TD
    T0([เริ่มจัดการแข่งขัน]) --> T1[1. สร้างข้อมูลทัวร์นาเมนต์]
    T1 --> T2[2. เลือกกีฬาอย่างน้อย 1 รายการ]
    T2 --> T3[3. เลือกเวอร์ชันกติกาของกีฬาแต่ละรายการ]
    T3 --> RULE_READY{ทุกกีฬามี rule_version และสถานะ ready หรือไม่}
    RULE_READY -->|ไม่| FIX_RULE[สร้างหรือแก้ชุดกติกา แล้วตั้งสถานะ pending ready หรือ locked]
    FIX_RULE --> T3
    RULE_READY -->|ใช่| T4[4. กำหนดสังกัดที่เข้าร่วม ถ้าต้องใช้]
    T4 --> T5[5. เพิ่มนักกีฬาแบบกรอกข้อมูลหรือ CSV]
    T5 --> T6[6. สร้างทีม แยกตามทัวร์นาเมนต์ กีฬา และสังกัด]
    T6 --> TEAM_STATUS{สถานะทีมพร้อมใช้หรือไม่}
    TEAM_STATUS -->|draft rejected withdrawn| FIX_TEAM[แก้ไขและส่งทีมใหม่]
    FIX_TEAM --> TEAM_STATUS
    TEAM_STATUS -->|submitted approved หรือ locked| T7[7. เพิ่มสมาชิกจากทะเบียนนักกีฬาเข้าแต่ละทีม]
    T7 --> T8[8. ตรวจความพร้อมรายกีฬา]
    T8 --> READY{กติกาพร้อม และมีอย่างน้อย 1 ทีมกับอย่างน้อย 1 สมาชิกในทุกกีฬาหรือไม่}
    READY -->|ไม่| FIX_DATA[ย้อนแก้กติกา ทีม หรือสมาชิก]
    FIX_DATA --> T8
    READY -->|ใช่| T9[9. สร้างตารางแข่งขัน]
    T9 --> EXISTING{กีฬานี้มีแมตช์อยู่แล้วหรือไม่}
    EXISTING -->|ใช่| STOP_FIXTURE[ไม่สร้างซ้ำ ต้องจัดการแมตช์เดิมก่อน]
    STOP_FIXTURE --> T9
    EXISTING -->|ไม่| TEAM_COUNT{มีทีมที่ไม่ใช่ draft rejected withdrawn อย่างน้อย 2 ทีมหรือไม่}
    TEAM_COUNT -->|ไม่| T6
    TEAM_COUNT -->|ใช่| FORMAT{รูปแบบทัวร์นาเมนต์}
    FORMAT -->|knockout| KNOCKOUT[สร้างสายแพ้คัดออกและ Bye ตามจำนวนทีม]
    FORMAT -->|round_robin| ROUNDROBIN[สร้างคู่พบกันหมด]
    FORMAT -->|group หรือ group_knockout| GROUP[แบ่งกลุ่มและสร้างคู่ในกลุ่ม]
    GROUP --> GROUP_MIN{มีอย่างน้อย 4 ทีมหรือไม่}
    GROUP_MIN -->|ไม่| T6
    GROUP_MIN -->|ใช่| CREATE_MATCH
    KNOCKOUT --> CREATE_MATCH[สร้าง matches และ match_periods พร้อมตรึง rule_version]
    ROUNDROBIN --> CREATE_MATCH
    CREATE_MATCH --> ASSIGN_OFFICIAL[กำหนดวัน เวลา สนาม และมอบหมายเจ้าหน้าที่]
    ASSIGN_OFFICIAL --> T10[10. แข่งขันและบันทึกผล]
    T10 --> COMPLETED{มีแมตช์สถานะ completed หรือไม่}
    COMPLETED -->|ไม่| T10
    COMPLETED -->|ใช่| T11[11. จัดทำและเผยแพร่ข่าวประชาสัมพันธ์]
    T11 --> TEND([จบกระบวนการหลัก])
```

### เกณฑ์ Progress ที่ระบบใช้จริง

| ขั้น | ถือว่าเสร็จเมื่อ |
|---|---|
| 1 | มีทัวร์นาเมนต์ที่เลือกอยู่ |
| 2 | ทัวร์นาเมนต์มีกีฬาอย่างน้อย 1 รายการ |
| 3 | ทุกกีฬามีชุดกติกาและ `setting_status = ready` |
| 4 | ถือว่าเสร็จเสมอ เพราะเป็นขั้นไม่บังคับ |
| 5 | มีนักกีฬาในระบบอย่างน้อย 1 คน ไม่ได้จำกัดเฉพาะทัวร์นาเมนต์ |
| 6 | มีทีมในทัวร์นาเมนต์อย่างน้อย 1 ทีม |
| 7 | มีสมาชิกทีมในทัวร์นาเมนต์อย่างน้อย 1 ราย |
| 8 | ทุกกีฬามีกติกาพร้อม และแต่ละกีฬามีทีมกับสมาชิกอย่างน้อย 1 ราย |
| 9 | มีแมตช์ในทัวร์นาเมนต์อย่างน้อย 1 แมตช์ |
| 10 | มีแมตช์สถานะ `completed` อย่างน้อย 1 แมตช์ |
| 11 | ในโค้ดปัจจุบันไม่ถูกตั้งเป็น complete อัตโนมัติ แม้จะมีข่าวแล้ว |

## 5. Flowchart การบันทึกผลการแข่งขัน

```mermaid
flowchart TD
    M0([เลือกแมตช์]) --> ACCESS{มีสิทธิ์และอยู่ใน scope ของแมตช์หรือไม่}
    ACCESS -->|ไม่| DENIED[ปฏิเสธการเข้าถึง]
    DENIED --> MEND([จบ])
    ACCESS -->|ใช่| LOAD[โหลดแมตช์ เวอร์ชันกติกา ช่วงเวลา เหตุการณ์ สมาชิก และผลเดิม]
    LOAD --> TYPE{ชนิดการคิดคะแนนที่ตรวจพบ}

    TYPE -->|SCORE| SCORE_PAGE[เปิด score.php]
    TYPE -->|SET| SET_PAGE[เปิด set.php]
    TYPE -->|TIME หรือ RACE_POSITION| RANKING_API[ใช้กระบวนการผู้เข้าแข่งขันและผลเวลา]
    TYPE -->|ชนิดอื่น| NO_UI[Backend รองรับ แต่หน้าบันทึกเฉพาะยังไม่พร้อม]
    NO_UI --> MEND

    SCORE_PAGE --> START_CLOCK[เริ่มนาฬิกา: scheduled เป็น live]
    START_CLOCK --> LIVE_ACTION{การทำงานระหว่างแข่ง}
    LIVE_ACTION -->|เพิ่มเหตุการณ์หรือคะแนน| EVENT[บันทึก match_event]
    LIVE_ACTION -->|แก้เหตุการณ์| UPDATE_EVENT[แก้ event พร้อม expected_revision]
    LIVE_ACTION -->|ยกเลิกเหตุการณ์| VOID_EVENT[ตั้ง is_voided พร้อม expected_revision]
    LIVE_ACTION -->|แก้คะแนนทีมโดยตรง| CORRECT_SCORE[ระบุเหตุผลและ expected_revision]
    LIVE_ACTION -->|ควบคุมเวลา| CLOCK[start pause reset next_period หรือ overtime]
    EVENT --> IDEMPOTENT{request_id ซ้ำหรือไม่}
    IDEMPOTENT -->|ซ้ำ| RETURN_EXISTING[คืนเหตุการณ์เดิม ไม่บันทึกซ้ำ]
    IDEMPOTENT -->|ไม่ซ้ำ| REVISION{action_revision ตรงหรือไม่}
    UPDATE_EVENT --> REVISION
    VOID_EVENT --> REVISION
    CORRECT_SCORE --> REVISION
    REVISION -->|ไม่ตรง| REFRESH[แจ้งว่าข้อมูลถูกแก้โดยผู้ใช้อื่น ให้รีเฟรช]
    REFRESH --> LOAD
    REVISION -->|ตรง| UPDATE_SCORE[บันทึก Transaction เพิ่ม revision และคำนวณคะแนนจาก event]
    CLOCK --> LIVE_ACTION
    UPDATE_SCORE --> LIVE_ACTION
    RETURN_EXISTING --> LIVE_ACTION

    SET_PAGE --> START_SET[เริ่มแมตช์]
    START_SET --> EDIT_SET[เพิ่ม แก้ หรือลบคะแนนแต่ละเซต]
    EDIT_SET --> SAVE_DRAFT[บันทึก recording_draft พร้อม action_revision]
    SAVE_DRAFT --> SET_WINNER{ฝ่ายใดชนะครบจำนวนเซตหรือยัง}
    SET_WINNER -->|ยัง| EDIT_SET
    SET_WINNER -->|แล้ว| FINALIZE

    SCORE_PAGE --> FINISH{กดยืนยันผลสุดท้ายหรือไม่}
    LIVE_ACTION --> FINISH
    FINISH -->|ยัง| LIVE_ACTION
    FINISH -->|ใช่| FINALIZE[ส่งชนิดคะแนน config และ result_data เข้า ScoringEngine]

    RANKING_API --> ENTRIES[เพิ่มผู้เข้าแข่งขันแบบ athlete team หรือ affiliation]
    ENTRIES --> AT_LEAST_TWO{มีผู้เข้าแข่งขันอย่างน้อย 2 รายหรือไม่}
    AT_LEAST_TWO -->|ไม่| ENTRIES
    AT_LEAST_TWO -->|ใช่| RACE_START[เริ่มแมตช์ให้เป็น live]
    RACE_START --> RECORD_TIME[บันทึก FINISHED DNS DNF หรือ DQ]
    RECORD_TIME --> ENTRY_REV{revision_number ตรงหรือไม่}
    ENTRY_REV -->|ไม่ตรง| REFRESH_ENTRY[แจ้งให้รีเฟรช]
    REFRESH_ENTRY --> ENTRIES
    ENTRY_REV -->|ตรง| RANK[เรียงเวลาที่ verified จากน้อยไปมากและให้คะแนนตามอันดับ]
    RANK --> ALL_CHECKED{ทุกรายตรวจผลแล้วหรือไม่}
    ALL_CHECKED -->|ไม่| RECORD_TIME
    ALL_CHECKED -->|ใช่| CONFIRM_RANK[ยืนยันผลรวมและเปลี่ยนแมตช์เป็น completed]
    CONFIRM_RANK --> AFTER_RESULT

    FINALIZE --> VALID{ScoringEngine ตรวจผลผ่านหรือไม่}
    VALID -->|ไม่ผ่าน| FIX_RESULT[แจ้ง validation error และกลับไปแก้ผล]
    FIX_RESULT --> TYPE
    VALID -->|ผ่าน| OLD_RESULT{มีผลเดิมหรือไม่}
    OLD_RESULT -->|มี| REASON{ระบุเหตุผลและ expected revision ตรงหรือไม่}
    REASON -->|ไม่| FIX_RESULT
    REASON -->|ใช่| SAVE_REV[เก็บ match_result_revision แล้วเพิ่ม revision]
    OLD_RESULT -->|ไม่มี| SAVE_RESULT[สร้าง match_result revision 1]
    SAVE_REV --> COMPLETE[ตั้ง match เป็น completed หยุดเวลา บันทึกคะแนน ผู้ชนะ ผู้ยืนยัน และเวลา]
    SAVE_RESULT --> COMPLETE
    COMPLETE --> AFTER_RESULT[คำนวณ standings ใหม่และบันทึก Audit log]
    AFTER_RESULT --> PUBLIC_RESULT[(ผล ตารางอันดับ และข้อมูลสำหรับข่าว)]
    PUBLIC_RESULT --> MEND
```

### ชนิดคะแนนที่ Backend รองรับ

| กลุ่ม | `scoring_type` | หลักตัดสินย่อ |
|---|---|---|
| คะแนนสองฝั่ง | `SCORE` | คะแนนรวมสูงกว่า; รองรับเสมอ ต่อเวลา และจุดโทษตาม config |
| คะแนนรายช่วง | `PERIOD_SCORE` | รวมคะแนนทุก period และเพิ่ม overtime เมื่อกติกาอนุญาต |
| แข่งเป็นเซต | `SET` | ชนะครบจำนวนเซตตาม `best_of` และตรวจ target/win-by |
| คะแนนรายรอบ/End | `ROUND`, `END_SCORE` | รวมคะแนนรายรอบ; END_SCORE ต้องถึงเป้าหมาย |
| เวลา/อันดับ | `TIME`, `RACE_POSITION` | ค่าน้อยกว่าชนะ; กระบวนการหลายผู้เข้าแข่งขันใช้ `match_entries` |
| ระยะ/ความสูง | `DISTANCE`, `HEIGHT` | ผลงานที่ดีที่สุด; ความสูงใช้จำนวนครั้งไม่ผ่านช่วยตัดสิน |
| กรรมการ/เป้า | `JUDGED`, `POINT_RANKING`, `TARGET` | รวม/เฉลี่ย/ตัดค่าสูงต่ำตาม config แล้วคะแนนสูงกว่าชนะ |
| จำนวน/ค่าน้อย | `COUNT`, `LOWEST` | สูงหรือต่ำกว่าชนะตามชนิดและ config |
| วิธีตัดสิน | `DECISION`, `COMBAT` | ผู้ชนะจากคะแนนหรือวิธี KO, TKO, Submission, DQ, Walkover เป็นต้น |
| หลายรายการ | `COMBINED` | รวมคะแนนถ่วงน้ำหนัก สูงหรือต่ำกว่าชนะตาม config |
| กอล์ฟ | `STROKE` | จำนวนสโตรกน้อยกว่าชนะ |
| อีสปอร์ต | `MAP` | ชนะจำนวน Map ถึงเกณฑ์ `best_of` |

> ข้อจำกัด UI ปัจจุบัน: `public/assets/app.js` ส่งผู้ใช้ไปหน้าบันทึกเฉพาะ `public/scoring/score.php` สำหรับ `SCORE` และ `public/scoring/set.php` สำหรับ `SET` เท่านั้น ชนิดอื่นมี service/engine รองรับแต่หน้า UI หลักแสดงข้อความว่ายังอยู่ระหว่างจัดทำ

## 6. Flowchart ข่าวและการเผยแพร่

```mermaid
flowchart TD
    N0([มีผลการแข่งขันหรือข้อมูลกิจกรรม]) --> AUTHOR[ฝ่ายประชาสัมพันธ์เปิดหน้าจัดการข่าว]
    AUTHOR --> METHOD{วิธีสร้างเนื้อหา}
    METHOD -->|เขียนเอง| FORM[กรอกหัวข้อ คำโปรย เนื้อหา ประเภทข่าว และลิงก์ข้อมูล]
    METHOD -->|ใช้ AI| SELECT_SOURCE[เลือกกีฬา ทัวร์นาเมนต์ แมตช์ รูปแบบเรื่อง โทน และคำสั่ง]
    SELECT_SOURCE --> AI{Gemini พร้อมใช้งานหรือไม่}
    AI -->|ใช่| GEMINI[Gemini สร้างร่างข่าว]
    AI -->|ไม่| LOCAL[local-summary สร้างร่างสำรอง]
    GEMINI --> FORM
    LOCAL --> FORM
    FORM --> IMAGE{มีภาพข่าวหรือไม่}
    IMAGE -->|มี| UPLOAD[ตรวจชนิด JPEG PNG WebP GIF และขนาดไม่เกิน 5 MB แล้วอัปโหลด]
    IMAGE -->|ไม่มี| DRAFT
    UPLOAD --> DRAFT[บันทึกข่าวสถานะ draft]
    DRAFT --> REVIEW{พร้อมเผยแพร่หรือไม่}
    REVIEW -->|ไม่| FORM
    REVIEW -->|ใช่| PUBLISH[ตั้งสถานะ published และ published_at]
    PUBLISH --> PUBLIC_NEWS[(แสดงหน้าเว็บไซต์สาธารณะ)]
    PUBLIC_NEWS --> NEND([จบ])
```

## 7. Flowchart การรับคำขอของระบบ

```mermaid
flowchart LR
    BROWSER[Browser: PHP HTML CSS Vanilla JS] -->|POST JSON และ X-CSRF-Token| API[public/api.php]
    API --> BOOT[app/bootstrap.php]
    BOOT --> ENV[โหลด .env ตั้ง Asia/Bangkok เริ่ม PHP session และ Database boot]
    ENV --> CSRF{เป็น GET, login, invite preview หรือ invite accept หรือไม่}
    CSRF -->|ใช่| ROUTE[AppService::handle]
    CSRF -->|ไม่| TOKEN{CSRF token ตรงหรือไม่}
    TOKEN -->|ไม่| E400[ตอบ error]
    TOKEN -->|ใช่| ROUTE
    ROUTE --> AUTHZ{ผ่าน role และ scope หรือไม่}
    AUTHZ -->|ไม่| E403[ตอบ 403 หรือ business error]
    AUTHZ -->|ใช่| SERVICE[ทำ Business logic และ Transaction]
    SERVICE --> DB[(MySQL PostgreSQL หรือ SQLite ผ่าน PDO)]
    SERVICE --> FILES[(public/uploads)]
    SERVICE --> AI[Gemini API หรือ local-summary]
    DB --> AUDIT[(audit_logs)]
    SERVICE --> RESPONSE[JSON: ok true และ data]
    RESPONSE --> BROWSER
```

### Error mapping ของ API

- `InvalidArgumentException` → HTTP 422
- `RuntimeException` ที่มีข้อความ “ไม่มีสิทธิ์” → HTTP 403
- `RuntimeException` อื่น → HTTP 400
- `Throwable` อื่น → HTTP 500 และไม่ส่งรายละเอียดภายในออกไป

## 8. สถานะสำคัญที่ควรแสดงในภาพ

```mermaid
stateDiagram-v2
    state "Match" as Match {
        [*] --> scheduled
        scheduled --> live: เริ่มนาฬิกาหรือเริ่มแข่งขัน
        live --> completed: ยืนยันผล
        scheduled --> postponed
        scheduled --> cancelled
        live --> live: แก้เหตุการณ์ คะแนน เวลา หรือ draft
        completed --> live: ลบผลพร้อมเหตุผลเพื่อเปิดแก้ใหม่
    }

    state "News" as News {
        [*] --> draft
        draft --> draft: แก้ไข
        draft --> published: เผยแพร่
    }

    state "Tournament sport setting" as SportSetting {
        [*] --> pending
        pending --> ready
        ready --> locked
        locked --> ready
    }

    state "Team" as Team {
        [*] --> submitted: การสร้างทีมตั้งค่านี้อัตโนมัติ
        submitted --> approved
        submitted --> rejected
        approved --> locked
        submitted --> withdrawn
        rejected --> submitted
    }
```

## 9. Data flow และตารางฐานข้อมูลหลัก

```mermaid
flowchart LR
    ORG[(universities faculties departments)] --> STUDENT[(students)]
    ORG --> TEAM[(teams)]
    USER[(users user_invites)] --> ASSIGNMENT[(match_official_assignments)]
    SPORT[(sports sport_rule_versions)] --> TS[(tournaments tournament_sports tournament_sport_settings)]
    TS --> TEAM
    STUDENT --> MEMBER[(team_members)]
    TEAM --> MEMBER
    TS --> MATCH[(matches match_periods)]
    TEAM --> MATCH
    USER --> ASSIGNMENT
    MATCH --> ASSIGNMENT
    MATCH --> EVENT[(match_events match_event_requests)]
    MATCH --> RESULT[(match_results match_result_revisions)]
    MATCH --> ENTRY[(match_entries match_entry_revisions)]
    RESULT --> STANDING[(standings)]
    MATCH --> STANDING
    MATCH --> NEWS[(news news_links)]
    USER -.ทุกการเปลี่ยนแปลงสำคัญ.-> AUDIT[(audit_logs)]
```

## 10. กฎความถูกต้องที่ห้ามตัดออกจาก Flowchart

1. ทุกคำขอเขียนข้อมูลต้องมี CSRF token ยกเว้น Login และกระบวนการคำเชิญที่ระบุไว้
2. การสร้างแมตช์ตรึง `rule_version_id` เพื่อรักษากติกาประวัติของแมตช์นั้น
3. เมื่อเปลี่ยนกติกาประจำทัวร์นาเมนต์ ระบบย้ายกติกาให้เฉพาะแมตช์ `scheduled` ที่ยังไม่มี event และ result; แมตช์ live/completed ไม่ถูกเปลี่ยน
4. การสร้างสายอัตโนมัติไม่ทำงานถ้ากีฬานั้นมีแมตช์อยู่แล้ว
5. การบันทึกผลทั่วไปผ่าน `ScoringEngine` และสำเร็จแบบ transaction เท่านั้น
6. การแก้ผลที่มีอยู่ต้องระบุเหตุผลและ revision ต้องตรง เพื่อป้องกันเขียนทับกัน
7. การบันทึก action/event ใช้ `action_revision`; การสร้าง event ใช้ `request_id` ป้องกันคำขอซ้ำ
8. Event ที่ยกเลิกไม่ถูกลบทิ้ง แต่ตั้ง `is_voided` เพื่อเก็บประวัติ
9. การยืนยันผลทำให้แมตช์เป็น `completed`, หยุดนาฬิกา, บันทึกผู้ยืนยัน/เวลา และคำนวณ standings ใหม่
10. การลบผลเดิมต้องมีเหตุผล เก็บประวัติใน Audit log และเปิดแมตช์กลับเป็น `live`
11. ผลแบบเวลาใช้ `verified_time_ms` จัดอันดับจากน้อยไปมาก; DNS, DNF และ DQ ไม่มีอันดับ
12. ข่าวจะปรากฏต่อสาธารณะเมื่อสถานะเป็น `published` เท่านั้น

## 11. หมายเหตุความคลาดเคลื่อนระหว่าง UI และ Backend

- Backend อนุญาต `sport_head` ที่ได้รับมอบหมายให้บันทึกผล แต่ปุ่มลัดขั้นที่ 10 ใน Tournament Workflow ฝั่ง UI ระบุเฉพาะ `super_admin`, `admin` และ `result_officer`; Sport Manager ยังเข้าศูนย์ผลได้จากหน้าที่บทบาทตนมีสิทธิ์
- Backend รองรับชนิดคะแนนทั้งหมดในหัวข้อ 5 แต่ routing หน้าเว็บปัจจุบันมี dedicated recording page เฉพาะ `SCORE` และ `SET`
- ขั้นที่ 4 “สังกัดที่เข้าร่วม” ถูกอธิบายว่าไม่บังคับและ Progress ถือว่าเสร็จเสมอ แม้ API บันทึกสังกัดจะบังคับให้เลือกอย่างน้อย 1 สังกัดเมื่อผู้ใช้กดบันทึก
- Progress ขั้นที่ 11 ยังไม่มีเงื่อนไขตรวจข่าวที่เผยแพร่ จึงแสดงเป็นยังไม่เสร็จเสมอในข้อมูล workspace ปัจจุบัน

## 12. ไฟล์อ้างอิงจากโค้ด

- `public/index.php` — โครงหน้าเว็บสาธารณะและหลังบ้าน
- `public/assets/app.js` — navigation, role view, Tournament Workflow 11 ขั้น และ routing หน้าบันทึกผล
- `public/api.php` — API entry point และ error mapping
- `app/bootstrap.php` — environment, session, CSRF support และ database boot
- `app/Auth.php` — บทบาท Login และ permission helper
- `app/AppService.php` — use case, validation, transaction, scope และ Audit log
- `app/ScoringTypeDetector.php` — การเลือกชนิดคะแนนจากกติกาหรือชนิดกีฬา
- `app/ScoringEngine.php` — การตรวจและสรุปผลแต่ละชนิดคะแนน
- `public/scoring/score.php` และ `public/assets/scoring/score.js` — หน้าบันทึกคะแนน `SCORE`
- `public/scoring/set.php` และ `public/assets/scoring/set.js` — หน้าบันทึกคะแนน `SET`
- `database/schema.sql`, `database/schema.mysql.sql`, `database/schema.pgsql.sql` — schema ฐานข้อมูล

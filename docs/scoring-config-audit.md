# Scoring Phase 4 — Config/Data Audit และแผนเตรียม Server Resolver

วันที่ตรวจ: 26 ตุลาคม 2026 (Asia/Bangkok)

เอกสารนี้บันทึกสถานะก่อน Phase 5; การแก้ไข Resolver/guards ภายหลังอธิบายใน
[scoring-server-resolver.md](scoring-server-resolver.md) โดย production inventory ยังเป็น release gate

## ขอบเขตและสถานะหลักฐาน

ตรวจ source, schema ทั้ง SQLite/MySQL/PostgreSQL และพฤติกรรมผ่าน API จริงบน
SQLite แยก (`sqlite::memory:`) เท่านั้น ไม่แก้ runtime code, schema หรือข้อมูลใช้งานจริง
งาน Phase 3 ที่ยังไม่ commit คงไว้ทั้งหมด

ไม่พบไฟล์สำเนาฐานข้อมูล SQLite ใน workspace จึง **ยังไม่มี inventory ของข้อมูลจริง**
ผล query/probe ที่รันในรอบนี้เป็นข้อมูลทดสอบ ไม่ใช่หลักฐานว่า production พร้อม migrate
ไม่ได้อ่าน `.env` หรือเชื่อมฐานข้อมูล deployment และไม่ได้รัน bootstrap กับฐานข้อมูลจริง
เพราะ `Database::migrate()` เรียก seed/backfill และอาจเปลี่ยนข้อมูลก่อนตรวจ

อ้างอิงข้อกำหนด: [scoring-architecture-v2.md](scoring-architecture-v2.md)

## Data Map ปัจจุบัน

| ข้อมูล | แหล่งเก็บ/ความสัมพันธ์จริง | พฤติกรรมและข้อจำกัด |
| --- | --- | --- |
| กีฬาที่ร่วมรายการ | `tournament_sports` | หนึ่งคู่ tournament/sport ต่อแถว ไม่ใช่ division |
| กติกามี version | `sport_rule_versions.scoring_type`, `scoring_config` และ JSON กติกาอื่น | มี `UNIQUE(sport_id, version_number)`; ห้ามแก้ผ่าน `saveSportRule()` เมื่อมี setting/match อ้างแล้ว |
| การเลือกกติกาของรายการ | `tournament_sport_settings.rule_version_id` | มี `UNIQUE(tournament_id, sport_id)` และ FK ไป rule; จึงยังเลือกหลาย division ของกีฬาเดียวกันอย่างอิสระไม่ได้ |
| กติกาของแมตช์ | `matches.rule_version_id` | pin version ตอนสร้างแมตช์; nullable และไม่มี FK ไป rule ใน schema ทั้งสามชุด |
| คะแนน/เวลาในแมตช์ | `matches.team*_score`, clock/period/status, `action_revision` | ไม่ใช่ snapshot ของ config; `current_period`/clock ไม่ใช่ version ของ config |
| เหตุการณ์และแบบร่าง | `match_events`, `metadata_json` ของ `recording_draft` | draft มี type/result data และประวัติ แต่ไม่มี full config snapshot |
| ผลสองฝ่าย | `match_results.scoring_type`, `config_snapshot`, `result_data`, `summary_json` | snapshot ของ scoring config เกิดตอนบันทึกผล; ไม่ได้ล็อกตั้งแต่เริ่มแข่ง และไม่ได้เก็บ JSON กติกาประกอบทั้งหมด |
| ประวัติแก้ผล | `match_result_revisions.old_result/new_result` | เก็บ result data แต่ไม่ได้เก็บ type/config snapshot เต็มของแต่ละ revision |
| ฮีตหลายผู้เข้าแข่งขัน | `match_entries`, `match_entry_revisions` | ยืนยันผลผ่าน `confirmMatchEntries()` แล้วจบแมตช์โดยไม่สร้าง `match_results`; ต้องตรวจแยกจาก workflow สองทีม |
| Audit | `audit_logs` | ยกเลิกผลแล้วเก็บผล/ประวัติใน audit; ไม่ควรลบหรือสร้างผลใหม่อัตโนมัติเพื่อให้ inventory ดูครบ |

Schema ที่เทียบ: `database/schema.sql`, `schema.mysql.sql`, `schema.pgsql.sql`.
ยังไม่มี competition event/division entity, `matches.event_id` หรือ immutable snapshot
ก่อนการแข่งขันในทั้งสามชุด (`event_id` ของ request ledger เป็นเหตุการณ์ในสนาม ไม่ใช่ division)
ไม่ควรสร้างตารางผู้เข้าแข่งขันใหม่ซ้ำ `match_entries` โดยไม่ประเมินข้อมูลเดิมก่อน

## เส้นทางอ่านและเขียนที่ต้องใช้แหล่งข้อมูลเดียวกัน

1. `TournamentModule::tournamentSportRule()` อ่าน setting หรือ fallback ไป active sport rule;
   `AppService::createMatch()` บันทึก rule version ลงแมตช์
2. `ScoringSupport::resolvedMatchScoringType()` ให้ saved result type มาก่อน
   แล้วใช้ `ScoringTypeDetector::detect()` ซึ่งยังเดาจากชื่อกีฬาและ fallback ได้
3. `MatchRecordingOperations::saveMatchRecordingDraft()` ตรวจ type กับค่าที่ resolve
   แต่ `MatchResultOperations::saveMatchResult()` ยอมใช้ type ที่รู้จักจาก request
4. event/correction อ่าน rule type/config ของแมตช์ ส่วน entry ranking อ่าน `rank_points`
   จาก rule; ดังนั้นการแก้เฉพาะ result endpoint ไม่ทำให้ทุก write ใช้กติกาตรงกัน
5. Selector ใช้ client inference; SCORE/SET match picker กรองจาก rule type แต่หน้า
   recorder ตรวจ `resolved_scoring_type` อีกครั้ง ทั้งสามจุดอาจไม่เห็น type เดียวกัน

รหัสจริง: `ScoringEngine::TYPES` มี 19 ค่า; ผู้จัดเลือก 12 ค่าใหม่ได้ และมี 7 ค่า legacy
หน้าบันทึกเฉพาะมี SCORE/SET เท่านั้น **ห้ามเปลี่ยนชื่อ SET → SET_SCORE หรือ TIME → RACE_TIME
เพียงเพราะชื่อใน catalog V2 ต่างกัน** และห้ามทำ backfill จากชื่อกีฬา

## ข้อค้นพบที่ต้องจัดการก่อนใช้งาน Resolver จริง

| รหัส | ข้อค้นพบและหลักฐาน | งานที่ต้องทำภายหลัง |
| --- | --- | --- |
| F1 | ส่ง SET result ให้แมตช์ที่ pin SCORE rule ได้ ผ่าน `matches.result` ใน probe | server เลือก type/config; reject request type ที่ขัดกัน |
| F2 | `AppService::updateMatch()` รับ rule version ใหม่ได้แม้แมตช์ live; ไม่ตรวจว่ากติกาเป็นของ sport เดียวกันในเส้นทางเลือก rule โดยตรง | ป้องกันทุกเส้นทางเปลี่ยน rule/sport และตรวจขอบเขตอ้างอิงก่อนเขียน |
| F3 | `saveTournamentSportSetting()` รับ `locked` เป็นสถานะ แต่ไม่บล็อกการเปลี่ยน setting ที่ locked; probe เปลี่ยน locked กลับเป็น ready พร้อม rule ใหม่ได้ | กำหนด transition และสิทธิ์ unlock ที่ตรวจสอบย้อนหลังได้ |
| F4 | การย้าย rule อัตโนมัติเลือก scheduled + ไม่มี event/result แต่ไม่ได้ตรวจ clock, period ที่เริ่มแล้ว หรือผล entry | นิยาม untouched จากหลักฐานทั้งหมด; อย่าใช้ status อย่างเดียว หรือถือว่าแค่ลงทะเบียน entrant คือเริ่มแข่งแล้ว |
| F5 | snapshot เกิดตอนบันทึกผล; entry/draft ไม่มี full frozen rule bundle | กำหนดจุดล็อกและ provenance ของ snapshot; ไม่อ้าง reconstructed config ว่าเป็น original snapshot |
| F6 | rule JSON decode คืน `[]` เมื่อ JSON เสีย และ validation config ยังไม่แยกตาม type ครบ | strict decoding/schema validation และ fail closed พร้อม error ที่ใช้ได้กับ UI |
| F7 | `Database::backfillCompetitionModel()` เดา type, เติม config และ pin null rule อัตโนมัติ | ไม่ใช้ app bootstrap เป็นเครื่องมือ audit/backfill ข้อมูลเก่า; แยก migration ที่มีรายการตรวจสอบและแผนย้อนกลับ |

F1, การเปลี่ยน live rule ใน F2 และการเปลี่ยน locked setting ใน F3 ยืนยันด้วย API จริง
บนฐานข้อมูลทดสอบแล้ว ประเด็นอื่นเป็นข้อค้นพบจาก source/schema ไม่ใช่จำนวนปัญหาใน production
Referenced rule edit guard ยังมีอยู่และ regression เดิมผ่าน แต่ guard นี้ไม่ครอบคลุมการเปลี่ยน pointer ของแมตช์

## แผน inventory บนสำเนาข้อมูล

ใช้ [scoring-config-audit.sql](../tools/scoring-config-audit.sql) ซึ่งมี 14 คำสั่ง SELECT
บน snapshot ที่ระบุชัดเจนและ connection อ่านอย่างเดียว ไม่โหลด `app/bootstrap.php`
ให้บันทึก hash/เวลาของสำเนาและ schema version เพื่อเทียบก่อน/หลังได้

Query ครอบคลุมจำนวนแถว/ประเภท, rule ที่หายหรืออ้างข้ามกีฬา, setting ที่หาย,
rule ที่ pin ต่างจาก setting ปัจจุบัน, result/rule mismatch, config ว่าง,
legacy inference candidates, completed entry workflow และ scheduled ที่มีหลักฐานดำเนินงาน
ผล query เป็น review queue: pinned rule ที่ต่างจาก setting ปัจจุบันอาจถูกต้องตามประวัติ
และ completed match ที่ไม่มี `match_results` อาจเป็น entry workflow ปกติ

ต้องตรวจเพิ่มก่อนลงนามอนุมัติข้อมูล:

- Decode JSON ทุก config/snapshot/draft/revision อย่าง strict; ตรวจ object/list และกติกา
  แยกตาม type ไม่ถือว่า JSON parse ผ่านแปลว่ากติกาถูกต้อง หรือ `{}` แปลว่าใช้ไม่ได้เสมอ
- เทียบ type ทุกแถวกับ registry จริง รวม null, whitespace, case และรหัสที่ไม่รองรับ
- เทียบ sport ของแมตช์/ทีม/rule/setting และสำรวจ FK/index ของฐานข้อมูลที่ deploy จริง
- แยก old two-team result, entry result, result ที่ยกเลิก/reopen และผลที่มีเพียง audit
- จัด evidence map ต่อ match: pinned rule, saved result snapshot, draft/event history,
  entry revisions, timestamps และรายการที่ต้องให้เจ้าของข้อมูลตัดสิน
- ข้อมูลขัดแย้งหรือไม่มีหลักฐาน type/config ต้องกักเป็น unresolved; ไม่แก้อัตโนมัติ

## แผนข้อมูลเป้าหมาย — ยังไม่ใช่ migration ที่อนุมัติให้รัน

### A. Harden ระบบปัจจุบันก่อนขยาย division

ใช้ `matches.rule_version_id` ที่ผ่านการตรวจอ้างอิงเป็นฐานสำหรับแมตช์ที่ยังไม่มีผล
รักษา rule versions ที่ถูกใช้งานแล้วให้ immutable และบล็อก pointer changes เมื่อเริ่มงาน
ผลเก่าต้องตรวจ result type/snapshot เทียบ pinned rule ก่อนเลือก policy; mismatch ไม่อาจ
ตัดสินด้วยลำดับ fallback อัตโนมัติหรือเชื่อ snapshot ที่มาจาก request เดิมโดยไม่ review

Phase 5 ทำ Resolver/validation และ guard ของ F1–F4 โดยคง API action/response หลักเดิม
แยกการเปลี่ยน behavior ที่ reject request ขัด type เป็น regression cases ชัดเจน
ไม่จำเป็นต้องรอสร้าง UI 21 ประเภท และไม่ต้องทำ migration ของ division พร้อมกัน

### B. Immutable snapshot ก่อนการแข่งขัน (ข้อเสนอ additive)

ประเมินตาราง `match_scoring_snapshots` หนึ่ง snapshot ต่อแมตช์ หรือคอลัมน์เทียบเท่า
โดยมี match/rule reference, type, config schema version, canonical rule bundle,
hash, locked timestamp และ provenance (`locked_at_start`, `reviewed_reconstruction` เป็นต้น)
rule bundle ต้องรวม scoring config และ JSON ประกอบที่ event/clock/ranking ใช้ด้วย
ไม่เก็บเพียง scoring_config แล้วปล่อยให้ event/period ไปอ่านกติกาอีก version

ล็อกแบบ atomic ที่ first start/first scoring write ตาม workflow จริง พร้อม transaction
เดียวกับ write นั้น ไม่ล็อกเพราะเปิดหน้า Match Center อย่างเดียว และไม่อ่าน event setting
ปัจจุบันทับ snapshot ระหว่างบันทึก ผล/entry revision ใหม่ต้องอ้าง config version ที่ใช้จริง
Snapshot เดิมของผลที่ reopen ต้องไม่ถูกลบทิ้งหรือแทนด้วย active rule ล่าสุด

### C. Event/Division แยกเป็น migration อีกชุด

ถ้าต้องรองรับหลายรายการของกีฬาเดียวกันใน tournament ให้เพิ่ม event/division entity
และ nullable `event_id` ก่อน โดยคง links เดิมไว้ระหว่างเปลี่ยนผ่าน ไม่เปลี่ยน unique key
ของ `tournament_sport_settings` ทันที เพราะ Tournament/teams/standings/public pages
ยังใช้ tournament+sport ต้องทำ impact map ของ consumers เหล่านี้ก่อน
ไม่สร้าง participant table ซ้ำหาก `match_entries` ขยายรองรับได้

## Backfill, rollout และ rollback ที่ต้องเตรียม

1. สำรองและ restore rehearsal บนสำเนา; เก็บ inventory/evidence ที่เจ้าของข้อมูล review แล้ว
2. แยก clean/ambiguous/orphan/cross-sport/legacy-entry/reopened cohorts และผลต่างที่คาดหวัง
3. อนุมัติ policy สำหรับข้อมูลแต่ละกลุ่มก่อนสร้าง migration; ไม่ rename/infer type จากกีฬา
4. ถ้าอนุมัติ schema ใหม่ ให้ทำ additive ก่อน, เขียน backfill ที่ deterministic/idempotent
   เฉพาะแถวที่มีหลักฐานและมี expected counts; ไม่แก้ result เดิมหรือประวัติ audit
5. เพิ่ม FK/index หลังแก้ orphan และตรวจ query plan; FK rule-id อย่างเดียวไม่ตรวจ cross-sport
   ควรประเมิน index `matches(rule_version_id)` ซึ่งยังไม่ประกาศใน schema ที่ตรวจ
6. ทดสอบ SQLite/MySQL/PostgreSQL บนสำเนาจริง ไม่เหมารวมว่า DDL rollback ได้เหมือนกัน
   โดยเฉพาะ MySQL ที่ DDL หลายชนิด implicit commit; เตรียม restore/reverse script แยก
7. เปิด Resolver ผ่าน rollout ที่ควบคุมได้ พร้อมตรวจ mismatch/error และผลรวม standings
8. Rollback runtime ให้กลับ version เดิมโดยคง additive data/snapshots ไว้ก่อน
   ห้าม drop snapshot หรือ overwrite history เพื่อย้อนระบบ; คืนข้อมูลเฉพาะ migration batch
   ที่มี evidence และ restore plan ผ่านการทดสอบแล้ว

## ผลตรวจในรอบนี้และข้อจำกัด

- Read-only SQL 14 คำสั่งรันผ่านบน SQLite แยกที่ตั้ง `PRAGMA query_only=ON`
- Probe ยืนยัน F1/F2/F3 ด้วยข้อมูลที่สร้างผ่าน API ของระบบจริงใน test DB
- รัน regression ของ version pinning/scheduled migration, rollback Service,
  multi-entrant confirmation และ multi-sport revisions เพื่อยืนยัน baseline เดิม
- ไม่มีการรัน migration, เปลี่ยน source ฝั่งแอป หรือแก้ข้อมูลใช้งานจริงใน Phase 4
- ยังไม่ได้รัน SQL บน MySQL/PostgreSQL หรือสำเนา production และยังไม่รับรองความพร้อมข้อมูลจริง

ส่งมอบ Data Map และ Migration Plan แล้ว ส่วน production inventory และการอนุมัติ backfill
ยังเป็น release gate ก่อนเปลี่ยนข้อมูลจริง ไม่ใช่งานที่ audit รอบนี้ได้ดำเนินการแล้ว

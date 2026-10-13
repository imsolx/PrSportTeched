INSERT IGNORE INTO settings (`key`, value, note) VALUES
('app_name', 'Sport Tournament', 'ชื่อระบบ'),
('faculty_code', '102', 'รหัสคณะ'),
('current_academic_year', '69', 'ปีการศึกษาปัจจุบัน'),
('max_sports_per_athlete', '2', 'จำกัดจำนวนกีฬาต่อนักกีฬา');

INSERT IGNORE INTO departments (id, code, name, is_active) VALUES
(1, '130', 'สาขาวิชาวิศวกรรมเครื่องกล', 1),
(2, '140', 'สาขาวิชาวิศวกรรมอุตสาหการ', 1),
(3, '120', 'สาขาวิชาวิศวกรรมไฟฟ้า', 1),
(4, '170', 'สาขาวิชาวิศวกรรมอิเล็กทรอนิกส์และระบบอัตโนมัติ', 1),
(5, '160', 'สาขาวิชาวิศวกรรมคอมพิวเตอร์', 1),
(6, '180', 'สาขาวิชาวิศวกรรมโยธา', 1),
(7, '190', 'สาขาวิชาอิเล็กทรอนิกส์อัจฉริยะ', 1),
(8, '200', 'สาขาวิชาเทคโนโลยีการผลิต', 1),
(9, '010', 'สาขาวิชาเทคโนโลยีและสื่อสารการศึกษา', 1),
(10, '020', 'สาขาวิชาเทคโนโลยีดิจิทัลเพื่อการศึกษา', 1),
(11, '060', 'สาขาวิชานวัตกรรมการเรียนรู้และเทคโนโลยีสารสนเทศ', 1);

INSERT IGNORE INTO sports
(id, name, type, gender, min_players, max_players, reserve_players, rules, icon, is_active, created_at, updated_at)
VALUES
(1, 'ฟุตบอล', 'team', 'male', 7, 11, 7, '', 'football', 1, NOW(), NOW()),
(2, 'วอลเลย์บอล', 'team', 'mixed', 6, 6, 6, '', 'volleyball', 1, NOW(), NOW()),
(3, 'วิ่ง 100 เมตร', 'individual', 'open', 1, 1, 0, '', 'running', 1, NOW(), NOW());

INSERT IGNORE INTO news
(id, title, slug, excerpt, body, cover_image_url, source_url, news_type, department_code, status, is_pinned, published_at, author_email, created_at, updated_at)
VALUES
(1, 'เปิดระบบ Sport Tournament', 'welcome', 'ระบบจัดการกีฬาเวอร์ชันทดลองพร้อมใช้งานแล้ว', 'ทีมงานสามารถเริ่มเพิ่มกีฬา ตารางแข่งขัน ผลการแข่งขัน และประกาศได้จากหน้า Dashboard', '', 'https://www.rmutt.ac.th/', 'general', NULL, 'published', 1, NOW(), '', NOW(), NOW());

INSERT IGNORE INTO report_categories (id, code, name, description, icon, display_order, is_active) VALUES
(1, 'competition', 'รายงานประชาสัมพันธ์ผลการแข่งขันกีฬา', 'เรียบเรียงผลการแข่งขัน รางวัล และประเด็นเผยแพร่ข่าวกีฬา', 'trophy', 10, 1),
(2, 'project', 'รายงานประชาสัมพันธ์โครงการ/กิจกรรมกีฬา', 'สรุปการจัดโครงการ กิจกรรมกีฬา และสาระสำหรับเผยแพร่', 'clipboard-check', 20, 1),
(3, 'performance', 'รายงานภาพรวมงานประชาสัมพันธ์กีฬา', 'สรุปภาพรวมการสื่อสาร ข่าวสาร และการดำเนินงานประชาสัมพันธ์กีฬา', 'file-chart-column', 30, 1),
(4, 'kpi', 'รายงานตัวชี้วัดงานประชาสัมพันธ์กีฬา', 'เปรียบเทียบเป้าหมาย ผลจริง และความสำเร็จของงานสื่อสารกีฬา', 'gauge', 40, 1),
(5, 'executive', 'รายงานสรุปประชาสัมพันธ์กีฬาสำหรับผู้บริหาร', 'สรุปสาระสำคัญด้านประชาสัมพันธ์กีฬาเพื่อประกอบการตัดสินใจ', 'briefcase-business', 50, 1),
(6, 'budget', 'รายงานงบประมาณงานประชาสัมพันธ์กีฬา', 'สรุปการใช้งบประมาณด้านสื่อสาร เผยแพร่ และกิจกรรมประชาสัมพันธ์กีฬา', 'banknote', 60, 1),
(7, 'participation', 'รายงานการมีส่วนร่วมและการรับรู้ด้านกีฬา', 'สรุปจำนวนผู้เข้าร่วม การรับรู้ และหน่วยงานที่เกี่ยวข้องกับข่าวสารกีฬา', 'chart-no-axes-combined', 70, 1),
(8, 'issues', 'รายงานประเด็นสื่อสาร ปัญหา และข้อเสนอแนะด้านกีฬา', 'รวบรวมประเด็นปัญหา การสื่อสาร การแก้ไข และแนวทางพัฒนาประชาสัมพันธ์กีฬา', 'message-square-warning', 80, 1);

INSERT IGNORE INTO report_templates (id, category_id, code, name, description, sections_json, display_order, is_active) VALUES
(1, 1, 'competition-summary', 'ร่างข่าวสรุปผลการแข่งขันกีฬา', 'สรุปผลการแข่งขันสำหรับใช้ประชาสัมพันธ์อย่างเป็นทางการ', '["general","performance","sports_results","outcomes","problems","attachments"]', 10, 1),
(2, 1, 'medal-summary', 'รายงานรางวัลและผลงานเด่นนักกีฬา', 'เรียบเรียงรางวัล อันดับ และผลงานเด่นสำหรับเผยแพร่', '["general","sports_results","outcomes","attachments"]', 20, 1),
(3, 1, 'sport-breakdown', 'รายงานผลการแข่งขันแยกตามชนิดกีฬา', 'จัดทำข้อมูลประชาสัมพันธ์จำแนกตามชนิดกีฬา', '["general","performance","sports_results","outcomes"]', 30, 1),
(4, 1, 'department-breakdown', 'รายงานผลงานกีฬาแยกตามหน่วยงาน', 'สรุปผลงานเพื่อสื่อสารภาพรวมรายหน่วยงานหรือสาขา', '["general","sports_results","outcomes"]', 40, 1),
(5, 2, 'project-performance', 'รายงานประชาสัมพันธ์ผลโครงการกีฬา', 'รายงานการดำเนินโครงการกีฬาเพื่อเผยแพร่และเสนอผู้บริหาร', '["general","principles","performance","sports_results","kpi","budget","outcomes","problems","attachments"]', 10, 1),
(6, 2, 'activity-performance', 'รายงานประชาสัมพันธ์กิจกรรมกีฬา', 'เรียบเรียงผลกิจกรรมกีฬา บรรยากาศ และประโยชน์ที่เกิดขึ้น', '["general","principles","performance","outcomes","problems","attachments"]', 20, 1),
(7, 2, 'project-plan', 'รายงานติดตามแผนประชาสัมพันธ์กีฬา', 'เปรียบเทียบแผนงานประชาสัมพันธ์กีฬากับผลการดำเนินงาน', '["general","principles","performance","kpi","budget","outcomes","problems"]', 30, 1),
(8, 3, 'annual-performance', 'รายงานภาพรวมการประชาสัมพันธ์กีฬา', 'สรุปภาพรวมงานสื่อสาร ข่าวสาร และผลดำเนินงานด้านกีฬา', '["general","performance","sports_results","kpi","budget","outcomes","problems","attachments"]', 10, 1),
(9, 4, 'kpi-report', 'รายงาน KPI ประชาสัมพันธ์กีฬา', 'รายงานผลตัวชี้วัดด้านข่าวสารและการเผยแพร่กีฬา', '["general","performance","kpi","outcomes","problems"]', 10, 1),
(10, 4, 'target-actual', 'รายงานเป้าหมายเทียบผลจริงงานสื่อสารกีฬา', 'เปรียบเทียบเป้าหมายกับผลจริงของงานประชาสัมพันธ์กีฬา', '["general","kpi","outcomes"]', 20, 1),
(11, 4, 'achievement-report', 'รายงานความสำเร็จงานประชาสัมพันธ์กีฬา', 'สรุปร้อยละความสำเร็จและผลสัมฤทธิ์ของงานสื่อสารกีฬา', '["general","kpi","outcomes"]', 30, 1),
(12, 5, 'executive-summary', 'สรุปผู้บริหารด้านประชาสัมพันธ์กีฬา', 'สรุปสาระสำคัญด้านประชาสัมพันธ์กีฬาอย่างเป็นทางการ', '["general","executive","performance","sports_results","kpi","budget","outcomes","problems"]', 10, 1),
(13, 5, 'annual-overview', 'รายงานประชาสัมพันธ์กีฬาประจำปี', 'สรุปภาพรวมข่าวสาร กิจกรรม และผลการแข่งขันประจำปี', '["general","executive","performance","sports_results","kpi","budget","outcomes","problems"]', 20, 1),
(14, 5, 'semester-overview', 'รายงานประชาสัมพันธ์กีฬาประจำภาคการศึกษา', 'สรุปงานประชาสัมพันธ์กีฬาในรอบภาคการศึกษา', '["general","executive","performance","sports_results","kpi","budget","outcomes","problems"]', 30, 1),
(15, 6, 'budget-summary', 'รายงานงบประมาณประชาสัมพันธ์กีฬา', 'สรุปการใช้งบประมาณเพื่อสื่อสารและเผยแพร่กิจกรรมกีฬา', '["general","budget","outcomes","problems","attachments"]', 10, 1),
(16, 7, 'participation-summary', 'รายงานการมีส่วนร่วมจากข่าวสารกีฬา', 'สรุปการมีส่วนร่วม การรับรู้ และกลุ่มเป้าหมายของประชาสัมพันธ์กีฬา', '["general","performance","outcomes"]', 10, 1),
(17, 8, 'issues-summary', 'รายงานประเด็นสื่อสารและข้อเสนอแนะกีฬา', 'สรุปปัญหาการสื่อสารกีฬาและแนวทางปรับปรุง', '["general","problems","attachments"]', 10, 1);

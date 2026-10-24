INSERT OR IGNORE INTO settings (key, value, note) VALUES
('app_name', 'Sport Tournament', 'ชื่อระบบ'),
('faculty_code', '102', 'รหัสคณะ'),
('current_academic_year', '69', 'ปีการศึกษาปัจจุบัน'),
('staff_invites_enabled', '1', 'เปิดใช้ลิงก์คำเชิญทีมงาน'),
('staff_invite_expiry_minutes', '45', 'อายุคำเชิญใหม่ หน่วยนาที'),
('max_sports_per_athlete', '2', 'จำกัดจำนวนกีฬาต่อนักกีฬา');

INSERT OR IGNORE INTO departments (id, code, name, is_active) VALUES
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

INSERT OR IGNORE INTO sports
(id, name, type, gender, min_players, max_players, reserve_players, rules, icon, is_active, created_at, updated_at)
VALUES
(1, 'ฟุตบอล', 'team', 'male', 7, 11, 7, '', 'football', 1, datetime('now'), datetime('now')),
(2, 'วอลเลย์บอล', 'team', 'mixed', 6, 6, 6, '', 'volleyball', 1, datetime('now'), datetime('now')),
(3, 'วิ่ง 100 เมตร', 'individual', 'open', 1, 1, 0, '', 'running', 1, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO news
(id, title, slug, excerpt, body, cover_image_url, source_url, news_type, department_code, status, is_pinned, published_at, author_email, created_at, updated_at)
VALUES
(1, 'เปิดระบบ Sport Tournament', 'welcome', 'ระบบจัดการกีฬาเวอร์ชันทดลองพร้อมใช้งานแล้ว', 'ทีมงานสามารถเริ่มเพิ่มกีฬา ตารางแข่งขัน ผลการแข่งขัน และประกาศได้จากหน้า Dashboard', '', 'https://www.rmutt.ac.th/', 'general', NULL, 'published', 1, datetime('now'), '', datetime('now'), datetime('now'));

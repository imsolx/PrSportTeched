# ตำแหน่งไฟล์หน้าจอ

`public/index.php` ประกอบหน้าเว็บจากไฟล์ในโฟลเดอร์นี้ โดยใช้ URL และ JavaScript เดิม

## frontend — หน้าผู้ชมทั่วไป

| ไฟล์ | หน้าที่ |
| --- | --- |
| `frontend/home.php` | หน้าแรก สถิติ ตารางใกล้ถึง ผลล่าสุด ข่าวล่าสุด และ Hall of Fame |
| `frontend/sports.php` | รายการกีฬาและตัวกรองประเภทกีฬา |
| `frontend/athletes.php` | ค้นหาและแสดงรายชื่อนักกีฬา |
| `frontend/schedule.php` | ตารางการแข่งขัน |
| `frontend/results.php` | ผลการแข่งขันและตารางคะแนนย่อ |
| `frontend/standings.php` | ตารางอันดับและคะแนนทีม |
| `frontend/bracket.php` | สายการแข่งขัน |

## staff — หน้าทีมงาน

| ไฟล์ | หน้าที่ |
| --- | --- |
| `staff/dashboard.php` | ภาพรวม สถิติ ทางลัด และกิจกรรมล่าสุด |
| `staff/sports.php` | จัดการกีฬา ชุดกติกา รูปแบบคะแนน และโครงสร้างหน่วยงาน |
| `staff/athletes.php` | จัดการนักกีฬา รูปภาพ ตัวกรองสังกัด และนำเข้า CSV; ใช้ DOM ID เดิม `view-students` |
| `staff/teams.php` | จัดการทีม สมาชิกทีม และนำเข้า CSV |

## ส่วนที่ยังอยู่ตำแหน่งเดิม

- ข่าวผู้ชม: `public/pages/news/PublicNewsList.php` และ `PublicNewsDetail.php`
- ข่าวทีมงาน: `public/pages/news/NewsManagement.php`, `NewsEditor.php` และ `NewsPreview.php`
- ทัวร์นาเมนต์ทีมงาน: `public/pages/tournaments/`
- เลือกแมตช์และบันทึกคะแนนทีมงาน: `public/pages/scoring/`; จุดเข้าเว็บอยู่ที่ `public/scoring/`
- จัดการผู้ใช้และคำเชิญทีมงาน: `public/pages/admin-users/`
- ส่วนร่วม เช่น header, navigation, login และ profile: ยังอยู่ใน `public/index.php`

## แก้หน้าตาและพฤติกรรมที่ไหน

- แก้ HTML และข้อความของ 11 หน้าข้างต้นในไฟล์ view ที่ตรงหน้าที่
- แก้พฤติกรรมและการเรียก API ที่ `public/assets/app.js` และ scripts เฉพาะงานใน `public/assets/modules/`
- แก้ CSS ต้นฉบับใน `resources/css/` แล้วรัน `npm.cmd run build`
- Tailwind สแกน `app/views/**/*.php` ด้วย จึงใช้ utility classes ใน views ได้
- ระบบสิทธิ์และการประมวลผลยังอยู่ใน `app/Auth.php`, `app/AppService.php` และไฟล์ระบบอื่นตามเดิม การแยกโฟลเดอร์เป็นการจัดกลุ่มหน้าจอ ไม่ได้เปลี่ยนสิทธิ์การเข้าถึง

รักษา DOM IDs, ชื่อ form fields และลำดับการประกอบหน้าเดิมเมื่อแก้ HTML เพราะ JavaScript ใช้อ้างอิงอยู่

---
name: evidence-based-uxui-color-design
description: >
  ใช้กำหนด ตรวจสอบ และปรับปรุงระบบสีของ Web Application โดยอ้างอิงงานวิจัย
  Human–Computer Interaction, จิตวิทยาการรับรู้สี, มาตรฐาน WCAG 2.2 และการออกแบบที่ยึดผู้ใช้เป็นศูนย์กลาง
  สำหรับ AI Coding Agent, UX/UI Designer และ Frontend Developer
version: "1.0.0"
language: th
last_verified: "2026-10-23"
---

# Evidence-Based UX/UI Color Design for Web Applications

> **วัตถุประสงค์:** คู่มือปฏิบัติสำหรับ AI / นักออกแบบ / นักพัฒนา เพื่อออกแบบสีของระบบเว็บแอปพลิเคชันอย่างมีเหตุผล ตรวจสอบได้ และใช้ซ้ำได้ โดย **ไม่อ้างความหมายของสีแบบเหมารวม** และ **ไม่แสร้งว่าค่า HEX ตัวอย่างเป็นผลจากงานวิจัย**

## 0. วิธีเรียกใช้ Skill นี้

ใช้ Skill เมื่อมีคำสั่งให้:
- ออกแบบชุดสี (color palette), UX/UI, design system, dashboard, web app, admin panel หรือแบบฟอร์ม
- ปรับปรุง visual hierarchy, contrast, สถานะ success/warning/error, สีปุ่ม และสีกราฟ
- ทำ light/dark theme, design tokens, CSS variables, Tailwind หรือ component variants
- ตรวจสอบความเข้าถึงได้ของสี และออกแบบสำหรับผู้มีความบกพร่องในการรับรู้สี

**ข้อความสั่งงานตัวอย่าง:**

> ใช้ skill `evidence-based-uxui-color-design` ตรวจระบบสีของ Web App นี้ ตั้งเป้า WCAG 2.2 ระดับ AA ส่งออกหลักฐานการเลือกสี, semantic color tokens, ตารางคู่สี/contrast, ข้อบกพร่องพร้อมวิธีแก้, ตัวอย่าง implementation และรายการตรวจ QA ห้ามอ้างว่าสีใดเพิ่ม conversion หรือ trust โดยไม่มีการทดสอบตรงกับกลุ่มผู้ใช้

**ค่าเริ่มต้นเมื่อไม่ทราบบริบท:** ตั้งเป้า WCAG 2.2 AA; UI ที่อ่านง่ายและสงบ; ใช้พื้นผิว neutral เป็นหลัก; จัดกลุ่มสีตาม *หน้าที่* ก่อนความสวย; ให้สถานะมีป้ายข้อความหรือรูปร่างประกอบเสมอ; ถือพาเลตตัวอย่างเป็นเพียงต้นแบบที่ต้องทดสอบกับระบบจริง

---

## 1. ลำดับความน่าเชื่อถือของหลักฐาน (Evidence hierarchy)

AI ต้องระบุชนิดของเหตุผลทุกครั้งที่ตัดสินใจเรื่องสี:

| รหัส | ประเภท | ความหมาย | ตัวอย่าง |
|---|---|---|---|
| `STD` | มาตรฐานทางการ | ข้อกำหนดที่ตรวจสอบได้ในเกณฑ์ที่อ้างถึง | WCAG 2.2 SC 1.4.3 [S1] |
| `RES` | งานวิจัยวิชาการ | ผลการศึกษาในบริบทและประชากรที่กำหนด | Hall & Hanna (2004) [R1] |
| `PRO` | แนวปฏิบัติจากการสังเคราะห์ | การตัดสินใจด้านออกแบบที่อนุมานจากมาตรฐานและวิจัย | ใช้ semantic tokens เพื่อคงความสม่ำเสมอ |
| `HYP` | สมมติฐานสำหรับการทดสอบ | แนวทางที่ยังไม่ยืนยันสำหรับระบบและผู้ใช้เป้าหมาย | เปลี่ยน accent เพื่อเพิ่มความชัดเจนของ CTA แล้วทดสอบ |

### ห้ามเด็ดขาด

1. ห้ามแต่งชื่อวารสาร, DOI, ผู้แต่ง, ปี, ขนาดตัวอย่าง หรือผลเชิงสถิติขึ้นเอง
2. ห้ามกล่าวว่า **“สีฟ้าทำให้เชื่อถือเสมอ”**, **“สีแดงเพิ่มอัตราคลิก X%”** หรือ **“โทน 60–30–10 เป็นมาตรฐาน UX ทางวิชาการ”**
3. ห้ามกล่าวว่า WCAG บังคับให้ใช้สีใดสีหนึ่งหรือจำเป็นต้องใช้ primary/secondary แบบตายตัว
4. ห้ามใช้การจับคู่สีที่ “ดูชัด” ด้วยสายตาแทนการคำนวณ WCAG contrast
5. ห้ามแปลว่า WCAG AA ผ่านทั้งหน้า จากการตรวจเฉพาะปุ่มหรือเฉพาะคู่สีใน token
6. ห้ามแสดง `PASS` สำหรับคู่สีที่ยังไม่คำนวณ รวมถึงสีที่มี transparency, gradient, overlay หรือภาพพื้นหลัง
7. เมื่อหลักฐานไม่เพียงพอ ให้บอกว่าเป็น `HYP` หรือ `PRO` และเสนอวิธีทดสอบ

**ข้อควรทราบ:** WCAG เป็นมาตรฐานเชิงบรรทัดฐานที่ทดสอบได้ ส่วนข้อค้นพบงานวิจัยเป็นข้อมูลเชิงประจักษ์ในบริบทการศึกษา ซึ่งไม่รับประกันว่าจะเกิดผลแบบเดียวกันในทุก Web App.

---

## 2. พื้นฐานวิทยาศาสตร์และทฤษฎีสีที่ใช้ได้จริง

### 2.1 Hue, saturation, brightness/lightness

- **Hue (เฉดสี):** กลุ่มสี เช่น น้ำเงิน แดง เขียว ใช้ช่วยแยกหมวดหมู่และสร้างเอกลักษณ์ แต่การตีความหมายต่างกันตามประสบการณ์และวัฒนธรรม [R3, R5, R6]
- **Saturation (ความอิ่มตัว):** ระดับความเข้มข้นของสี งานทดลองของ Valdez & Mehrabian ศึกษาความสัมพันธ์กับการตอบสนองเชิงอารมณ์ แต่ **ไม่ใช่การทดลอง Web App** และไม่ควรนำผลมาใช้ทำนาย Conversion โดยตรง [R7]
- **Brightness / Lightness (ความสว่าง):** ส่งผลต่อการรับรู้และลำดับความสำคัญเชิงภาพ แต่ **ตัวเลข HSV brightness หรือ HSL lightness ไม่ใช่ WCAG contrast** ต้องคำนวณจาก relative luminance ของสี sRGB ตามมาตรฐาน [S1]
- **Color harmony** เช่น complementary/analogous อาจใช้เป็นเครื่องมือสร้างแนวสี (`PRO`) ไม่ใช่กฎทางวิทยาศาสตร์ว่ารูปแบบหนึ่งใช้ได้ดีกว่าในทุกสถานการณ์

### 2.2 ความสามารถในการอ่าน (readability)

- Hall & Hanna (2004) ทดลองกับผู้เข้าร่วม 136 คน ใน 4 เงื่อนไขคู่สีของเว็บเพจ พบว่าความต่างเชิงแสงที่มากขึ้นโดยทั่วไปสัมพันธ์กับการอ่านง่ายขึ้น ขณะที่ **ไม่พบผลของคู่สีต่อการจำข้อมูลอย่างมีนัยสำคัญ** [R1]
- **นำไปใช้:** ให้ความสำคัญกับ contrast ของข้อความ/พื้นหลัง, ความหนาฟอนต์, การวางข้อความบนรูป, และการทดสอบสภาพการใช้งานจริง ก่อนเลือกสีตามอารมณ์
- **ข้อจำกัด:** เป็นการศึกษาปี 2004 บนเว็บและเงื่อนไขเฉพาะ ไม่ใช่หลักฐานว่า contrast ยิ่งสูงจะดีกว่าเสมอสำหรับผู้ใช้ทุกกลุ่มและทุกสภาพแสง

### 2.3 สี ความประทับใจ และความน่าเชื่อถือ

- Cyr, Head & Larios (2010) ทดลองสามชุดสีของเว็บไซต์กับผู้ใช้ต่างวัฒนธรรมโดยใช้หลายวิธี และพบความเกี่ยวข้องระหว่างความดึงดูดใจของสีกับ trust/satisfaction รวมถึงความต่างข้ามวัฒนธรรม [R2]
- Reinecke และคณะ (2013) ศึกษาการประเมินเว็บ 450 เว็บไซต์กับผู้ร่วมศึกษา 548 คน พบว่า visual complexity และ colorfulness อธิบายความประทับใจเชิงความงามเบื้องต้นได้ส่วนหนึ่ง [R4]
- Reinecke & Gajos (2014) วิเคราะห์การให้คะแนนประมาณ 2.4 ล้านรายการจากผู้เข้าร่วมเกือบ 40,000 คน พบว่าระดับความสดสีและความซับซ้อนที่ผู้ใช้ชอบแตกต่างตามกลุ่มผู้ใช้ [R5]
- **นำไปใช้:** ใช้สีจำกัดหน้าที่และสร้างลำดับสายตาที่ชัด จากนั้นทดสอบกับกลุ่มเป้าหมาย ห้ามตีความว่าชุดสีหนึ่งเป็นสูตรสำเร็จสำหรับทุกชาติ ทุกวัย หรือทุกแอป

### 2.4 จิตวิทยาสีต้องพิจารณาบริบท

- Palmer & Schloss (2010) เสนอและทดสอบแนวคิด Ecological Valence Theory ว่าความชอบสีเกี่ยวข้องกับความรู้สึกต่อสิ่งของหรือประสบการณ์ที่คนเชื่อมโยงกับสีนั้น [R6]
- Elliot & Maier (2014) ทบทวนวรรณกรรมและเตือนว่าการสรุปผลสีต่อพฤติกรรมของมนุษย์ยังต้องศึกษาขอบเขต เงื่อนไข และการนำไปใช้ในโลกจริงเพิ่มเติม [R3]
- **นำไปใช้:** เลือกสีจากงานของผู้ใช้ ความหมายในโดเมน และ brand requirement; อย่าถือว่า “แดง = ผิด” “เขียว = ถูก” ได้ในทุกวัฒนธรรมโดยไม่ใช้ข้อความกำกับ

---

## 3. ข้อกำหนดด้าน Accessibility: WCAG 2.2

**Target ขั้นต่ำที่ Skill ตั้งไว้:** WCAG 2.2 ระดับ **AA** สำหรับหน้าจอและสถานะการใช้งานที่เกี่ยวข้องทั้งหมด [S1]

| เกณฑ์ | ระดับ | สิ่งที่ต้องทำ |
|---|---|---|
| SC **1.4.1 Use of Color** | A | ห้ามใช้สีเป็นวิธี *เดียว* ในการสื่อความหมาย/สถานะ/การกระทำ ให้เพิ่มข้อความ ไอคอน รูปร่าง เส้น หรือลวดลาย [S2] |
| SC **1.4.3 Contrast (Minimum)** | AA | ข้อความทั่วไป/ข้อความในรูปภาพ **อย่างน้อย 4.5:1**; ข้อความขนาดใหญ่ **อย่างน้อย 3:1** [S3] |
| SC **1.4.11 Non-text Contrast** | AA | ส่วนมองเห็นที่จำเป็นสำหรับระบุ UI controls/states และกราฟิกสำคัญต้องต่างจากสีข้างเคียง **อย่างน้อย 3:1** ภายใต้ข้อยกเว้นในมาตรฐาน [S4] |
| SC **2.4.7 Focus Visible** | AA | องค์ประกอบที่ควบคุมด้วยคีย์บอร์ดต้องมีตัวบอก focus ที่มองเห็นได้ [S5] |
| SC **1.4.6 Contrast (Enhanced)** | AAA (ทางเลือก) | ข้อความทั่วไป **7:1**; ข้อความขนาดใหญ่ **4.5:1** [S6] |

**นิยาม large-scale text ตาม WCAG:** ประมาณ **24 CSS px ขึ้นไป** (18 pt) หรือประมาณ **18.67 CSS px ขึ้นไปเมื่อหนา/bold** (14 pt bold) ตามความหมายของมาตรฐานและตัวอักษรที่ใช้ [S3]. หากไม่แน่ใจ ให้ตรวจด้วยเกณฑ์ 4.5:1.

**ข้อยกเว้น:** มีข้อยกเว้นสำหรับข้อความประกอบภาพที่ incidental, ข้อความตกแต่ง, โลโก้ หรือส่วน inactive ตามเงื่อนไข [S3, S4]; แต่ *อย่าใช้ข้อยกเว้นเพื่อทำให้ UI หลักอ่านยาก*. Placeholder ที่แสดงเป็นข้อความไม่ได้รับการยกเว้นโดยอัตโนมัติ [S3].

**สำคัญ:** การมี focus ring ที่มองเห็นได้อย่างเดียวไม่ยืนยันว่าได้ AA ทั้งหมด; ถ้าใช้สี/เส้นเป็นสัญญาณ focus ที่จำเป็น ให้ประเมินข้อกำหนด non-text contrast ที่เกี่ยวข้องด้วย [S4, S5]. `SC 2.4.13 Focus Appearance` เป็นระดับ AAA ไม่ควรอ้างว่าเป็นข้อบังคับระดับ AA.

### 3.1 วิธีคำนวณ Contrast Ratio อย่างถูกต้อง

สำหรับสองสีที่คำนวณ relative luminance ได้:

`Contrast ratio = (Lighter luminance + 0.05) / (Darker luminance + 0.05)`

สำหรับ sRGB แต่ละช่อง 0–255 ให้แปลงเป็น 0–1 จากนั้น linearize:

- ถ้า `c <= 0.04045` ให้ `linear = c / 12.92`
- ไม่เช่นนั้น `linear = ((c + 0.055) / 1.055) ^ 2.4`
- `L = 0.2126 * R + 0.7152 * G + 0.0722 * B` (ค่าที่ linearized แล้ว)

**ห้ามปัดค่าเพื่อให้ผ่าน:** 4.499:1 ไม่เท่ากับ 4.5:1 [S3]. หากมี `rgba`, opacity, gradient หรือ backdrop ให้คำนวณ **สีที่ถูก composite/ปรากฏจริง** ไม่ใช่แค่โค้ดสีต้นทาง

### 3.2 จุดตรวจที่มักถูกมองข้าม

- ข้อความในปุ่มหลัก/รอง, navigation, breadcrumb, dropdown, tooltip, placeholder, toast และ disabled text (ตรวจข้อยกเว้นให้ตรง)
- border ที่จำเป็นต่อการมองเห็น input, checkbox, radio, focus indicator, selected state, icon-only buttons
- link ในเนื้อความไม่ควรอาศัยสีที่ต่างอย่างเดียว: ใช้ underline หรือสัญญาณอื่นที่เพียงพอ
- กราฟแท่ง/เส้น/แผนภาพ: ใช้ labels, direct values, line styles หรือ patterns ประกอบ เพื่อไม่ต้องแยกชุดข้อมูลจาก hue อย่างเดียว
- ตาราง/ฟอร์ม: ห้ามอาศัยแถวแดง/เขียวล้วนในการบอกข้อมูลผิดหรือผ่าน; มีคำว่า “ผิดพลาด”, “ผ่าน”, “รอตรวจ” ประกอบ
- ทดสอบสถานะ default, hover, focus, active, selected, validation-error, success, loading รวมทั้ง light/dark theme

---

## 4. หลักการเลือกสีสำหรับระบบ Web App

### 4.1 เลือกตามบทบาท ไม่ใช่ตามจำนวนสี

กำหนด semantic roles อย่างน้อย:

1. `canvas`, `surface`, `surface-raised`: พื้นหลังและชั้นของข้อมูล
2. `text-primary`, `text-secondary`: ลำดับชั้นข้อความ
3. `border`, `divider`, `focus`: การแบ่งส่วนและการบอกสถานะโฟกัส
4. `action-primary`, `action-secondary`, `action-danger`: การกระทำสำคัญและการกระทำเสี่ยง
5. `info`, `success`, `warning`, `danger`: ข้อความสถานะ พร้อม **ข้อความ/ไอคอน**
6. `chart-category-n`: แยกข้อมูลด้วยสีร่วมกับ labels, รูปแบบเส้น, markers หรือ pattern

**หมายเหตุ:** การแยก semantic tokens เป็น `PRO` (แนวปฏิบัติการพัฒนา) ไม่ใช่กฎที่มีตัวเลขยืนยันจากงานวิจัยข้างต้น

### 4.2 ลำดับการตัดสินใจ

1. อ่านวัตถุประสงค์ของ Web App, กลุ่มผู้ใช้, brand constraints, อุปกรณ์ และงานที่ต้องทำบ่อย
2. ระบุ critical workflows เช่น เข้าสู่ระบบ, ค้นหา, บันทึกข้อมูล, อนุมัติ, ส่งฟอร์ม และลบข้อมูล
3. ระบุข้อมูลที่ต้องแยกทันที เช่น “บันทึกสำเร็จ/ไม่สำเร็จ”, “ผลแพ้/ชนะ”, “รออนุมัติ”
4. เลือกพื้นผิว neutral และสี text ที่ผ่านขั้นต่ำก่อน
5. กำหนด accent สำหรับทางเดินสายตาและ action สำคัญ **โดยไม่ทำให้ทุกอย่างเด่นเท่ากัน**
6. กำหนด semantic colors และอย่าใช้สีสถานะเดียวกันเพื่อสื่อความหมายขัดกันภายในระบบ
7. คำนวณ contrast ของทุกคู่ที่ใช้งานจริง
8. ตรวจ keyboard, focus, color-vision-deficiency simulation และความสามารถเข้าใจได้โดยไม่ใช้สี
9. ทำ usability testing ก่อนอ้างว่าชุดสีใหม่ “ทำงานดีกว่า”

### 4.3 สิ่งที่เป็น heuristic ไม่ใช่ผลวิจัยตายตัว

- ใช้ neutral surfaces มากกว่าสีจัดทั้งหน้า เพื่อแยกเนื้อหาและ interactive states (`PRO`)
- ไม่ใช้ accent หลายสีจนทุกปุ่มแข่งขันแย่งความสนใจ (`PRO`; ศึกษาความซับซ้อนของภาพประกอบ [R4, R5])
- ใช้สีอ่อนสำหรับ status background และสีข้อความที่เข้มพอ โดยต้องตรวจคู่สีจริง (`PRO`, `STD`)
- Light mode และ dark mode ไม่มีโหมดที่เหมาะกับทุกคนเสมอไป; ทดสอบแต่ละโหมดแยกกัน (`HYP` หากจะอ้างว่าผู้ใช้ของแอปชอบแบบใด)
- **ห้าม** นำกฎ 60/30/10, “ปุ่มสีส้มขายดีที่สุด”, หรือความหมายจิตวิทยา 1 สี = 1 ผลลัพธ์ มาเป็นหลักฐานพิสูจน์ประสิทธิภาพ

---

## 5. พาเลตตัวอย่างเพื่อเริ่มพัฒนา (ไม่ใช่พาเลตจากงานวิจัย)

พาเลตนี้เป็น **ตัวอย่างเชิงวิศวกรรม (`PRO`)** คัดเลือกให้คู่ข้อความหลักมี contrast ตามเกณฑ์; สีหลักไม่ใช่ข้อบังคับของ WCAG หรือผลทดลองโดยตรง ค่า ratio คำนวณจากสูตรใน §3.1 และปัดเพื่อ *แสดงผลเท่านั้น*.

### 5.1 Light theme

| Role | FG | BG | Contrast โดยประมาณ | การใช้ |
|---|---|---|---:|---|
| ข้อความหลัก | `#111827` | `#FFFFFF` | 17.74:1 | ตัวหนังสือทั่วไป |
| ข้อความรอง | `#475569` | `#FFFFFF` | 7.58:1 | คำอธิบาย |
| ปุ่ม primary | `#FFFFFF` | `#1D4ED8` | 6.70:1 | ปุ่มหลัก |
| ข้อความ error | `#B91C1C` | `#FFFFFF` | 6.47:1 | ข้อผิดพลาดพร้อม icon/ข้อความ |
| ข้อความ success | `#166534` | `#F0FDF4` | 6.81:1 | สถานะสำเร็จพร้อม icon/ข้อความ |
| เส้นขอบ input (เมื่อจำเป็น) | `#64748B` | `#FFFFFF` | 4.76:1 | ขอบช่องกรอก |

### 5.2 Dark theme

| Role | FG | BG | Contrast โดยประมาณ | การใช้ |
|---|---|---|---:|---|
| ข้อความหลัก | `#F8FAFC` | `#111827` | 16.96:1 | ตัวหนังสือทั่วไป |
| ข้อความรอง | `#CBD5E1` | `#111827` | 11.95:1 | คำอธิบาย |
| ปุ่ม primary | `#0B1220` | `#60A5FA` | 7.36:1 | ปุ่มหลัก |
| เส้นขอบ input | `#94A3B8` | `#111827` | 6.92:1 | ขอบช่องกรอก |

> สีที่ใช้งานจริงอาจมีพื้นหลังอื่น, overlay, โปร่งแสง, เส้นบาง, hover/disabled, การเปลี่ยน theme และอัตราส่วนอื่น ต้อง **ตรวจใหม่ตาม rendered UI** ไม่ควรถือว่าตารางตัวอย่างรับประกันทั้งระบบผ่าน WCAG.

### 5.3 ตัวอย่าง CSS semantic design tokens

```css
:root {
  color-scheme: light;
  --color-canvas: #ffffff;
  --color-surface: #f8fafc;
  --color-text: #111827;
  --color-text-muted: #475569;
  --color-border-control: #64748b;
  --color-action-primary: #1d4ed8;
  --color-on-action-primary: #ffffff;
  --color-focus: #7c3aed;
  --color-success-bg: #f0fdf4;
  --color-success-fg: #166534;
  --color-error-bg: #fef2f2;
  --color-error-fg: #b91c1c;
}

[data-theme="dark"] {
  color-scheme: dark;
  --color-canvas: #0b1220;
  --color-surface: #111827;
  --color-text: #f8fafc;
  --color-text-muted: #cbd5e1;
  --color-border-control: #94a3b8;
  --color-action-primary: #60a5fa;
  --color-on-action-primary: #0b1220;
  --color-focus: #93c5fd;
  --color-success-bg: #052e16;
  --color-success-fg: #bbf7d0;
  --color-error-bg: #450a0a;
  --color-error-fg: #fecaca;
}

body {
  color: var(--color-text);
  background: var(--color-canvas);
}

button.primary {
  color: var(--color-on-action-primary);
  background: var(--color-action-primary);
}

input, select, textarea {
  color: var(--color-text);
  background: var(--color-canvas);
  border: 1px solid var(--color-border-control);
}

:where(button, a, input, select, textarea):focus-visible {
  outline: 3px solid var(--color-focus);
  outline-offset: 3px;
}

.status--success {
  color: var(--color-success-fg);
  background: var(--color-success-bg);
}

.status--error {
  color: var(--color-error-fg);
  background: var(--color-error-bg);
}
```

**ข้อจำกัดของตัวอย่าง:** `focus` ต้องตรวจ contrast กับสีที่อยู่ติดกันจริง; แถบ status ต้องมีเนื้อความ/สัญลักษณ์กำกับ; ถ้าต้องรองรับ CSS alpha/gradient ให้คำนวณหลัง compositing. CSS นี้ยังไม่ใช่ component library ที่ผ่าน audit แล้ว

### 5.4 โค้ดช่วยคำนวณ WCAG contrast สำหรับ HEX (sRGB แบบทึบ)

```js
function hexToRgb(hex) {
  const value = hex.replace(/^#/, "");
  if (!/^[0-9a-fA-F]{6}$/.test(value)) {
    throw new Error(`Expected 6-digit HEX: ${hex}`);
  }
  return [0, 2, 4].map(i => parseInt(value.slice(i, i + 2), 16) / 255);
}

function relativeLuminance(hex) {
  const [r, g, b] = hexToRgb(hex).map(c =>
    c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  );
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrastRatio(foreground, background) {
  const a = relativeLuminance(foreground);
  const b = relativeLuminance(background);
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

const pairs = [
  { use: "body text", fg: "#111827", bg: "#FFFFFF", min: 4.5 },
  { use: "primary button", fg: "#FFFFFF", bg: "#1D4ED8", min: 4.5 },
  { use: "input border", fg: "#64748B", bg: "#FFFFFF", min: 3.0 },
];

for (const pair of pairs) {
  const ratio = contrastRatio(pair.fg, pair.bg);
  console.log(`${pair.use}: ${ratio.toFixed(2)}:1 | ${ratio >= pair.min ? "PASS" : "FAIL"}`);
}
```

**โค้ดนี้ไม่รองรับ alpha, gradients, images, blend modes, anti-aliasing หรือ dynamic backgrounds**; สำหรับกรณีดังกล่าวต้องตรวจสีที่แสดงผลจริงและใช้เครื่องมือตรวจเว็บร่วมด้วย

---

## 6. ตัวอย่างการนำไปใช้กับ Web App ประกาศผลการแข่งขัน

กรณีระบบบันทึกผลกีฬา / ประชาสัมพันธ์การแข่งขัน (ใช้เป็น *case study* ไม่ใช่ข้อกำหนดเฉพาะของทุกระบบ):

| องค์ประกอบ | กฎที่ออกแบบ | เหตุผล |
|---|---|---|
| ปุ่ม “บันทึกผลการแข่งขัน” | ใช้ primary action เด่นตามลำดับงาน; ข้อความอ่านได้ 4.5:1 | `STD` 1.4.3; `PRO` hierarchy |
| “รอแข่งขัน / กำลังแข่งขัน / จบการแข่งขัน” | แสดงชื่อสถานะ + badge/shape และสีประกอบ | `STD` 1.4.1 |
| “ชนะ / แพ้ / เสมอ” | แสดงผลเป็นข้อความและคะแนนจริง ไม่ใช้สีเซลล์อย่างเดียว | `STD` 1.4.1 |
| แบบฟอร์มบันทึกคะแนนผิด | แสดงข้อความผิดที่ช่องกรอก + ขอบ/สัญลักษณ์ที่เห็นชัด | `STD` 1.4.1, 1.4.11 |
| กราฟผลคะแนน/อันดับ | ชื่อทีม, ตัวเลข, legend, pattern/markers | `STD` 1.4.1, 1.4.11 |
| ปุ่มลบผลการแข่งขัน | แยกเป็น destructive action พร้อมข้อความยืนยัน ไม่พึ่งสีแดงอย่างเดียว | `PRO` usability, `STD` 1.4.1 |
| ตารางคะแนนหลายทีม | รักษาคอนทราสต์ของทุกแถวและข้อความ; ใช้ grouping/spacing ช่วย | `STD` 1.4.3, `PRO` hierarchy |

**ห้ามใส่เหตุผลเช่น “สีเขียวช่วยให้นักกีฬามั่นใจเพิ่มขึ้น 30%” โดยไม่มีการศึกษาเฉพาะเรื่อง**.

---

## 7. Workflow สำหรับ AI Coding Agent (ต้องปฏิบัติตามลำดับ)

### STEP 1 — เข้าใจบริบท

อ่าน UI เดิม, component list และ brand constraints. ระบุ user roles, หน้างานหลัก, สถานะสำคัญ, mobile/desktop และความต้องการ high-contrast/light/dark. ถ้าข้อมูลไม่ครบ ให้บันทึกสมมติฐาน ไม่แต่งเป็นความจริง

### STEP 2 — Audit ก่อนแก้

รวบรวมสีเดิมและการใช้งานจริง พร้อมแยก token กับ hardcoded colors. ตรวจหน้า: login, dashboard, form, table, modal, alert, chart และหน้าบันทึกข้อมูล รวมถึงทุก state ที่เข้าถึงได้

### STEP 3 — สรุปหลักฐานที่ใช้

ทำตาราง `Design decision | STD/RES/PRO/HYP | Source | Limitation`. กฎ WCAG ต้องอ้างเลข SC; ถ้าอ้างวิจัยต้องโยงให้ถูกขอบเขต ไม่ขยายผลเกินการศึกษา

### STEP 4 — ออกแบบระบบสี

กำหนด primitive colors และ semantic roles จากนั้นออกแบบ light/dark themes ด้วย token ชื่อเดียวกัน หลีกเลี่ยงชื่อ token ที่ผูกสีถาวร เช่น `blueButton` เมื่อความหมายที่แท้จริงคือ `actionPrimary`

### STEP 5 — Validate ความต่างสี

ตรวจคู่ foreground/background **จริง** ในแต่ละ state. สำหรับ text ตาม SC 1.4.3 และสำหรับรูปสัญลักษณ์/ขอบที่จำเป็นตาม SC 1.4.11; ไม่ใช้การปัดเศษเพื่อให้ผ่าน

### STEP 6 — ตรวจความหมายเมื่อไม่มีสี

ลองขาวดำ/จำลอง color-vision deficiency และซ่อนสีเชิงความหมายบางส่วน: ถ้าผู้ใช้แยกสถานะ คะแนน ความผิดพลาด หรือ action ไม่ได้ ให้ปรับองค์ประกอบที่ไม่ใช่สีทันที

### STEP 7 — ทดสอบกับผู้ใช้

ให้ผู้ใช้จริงทำ task ที่สำคัญ; เก็บ task success rate, error rate, completion time, confidence/ความเข้าใจ (กำหนดวิธีวัดให้ชัด) และความเห็นเชิงคุณภาพ. หากจะอ้างว่าชุดสี A ดีกว่า B ให้ควบคุมปัจจัยอื่นและออกแบบการเปรียบเทียบที่เหมาะสม; ไม่อ้าง causality จาก preference test อย่างเดียว

### STEP 8 — ส่งมอบผลลัพธ์

ต้องมี:

1. **Color rationale:** ปัญหา + หลักฐาน + ข้อจำกัด
2. **Token sheet:** HEX และ semantic roles (light/dark)
3. **Contrast audit:** คู่สี, บริบท, ratio, threshold, PASS/FAIL
4. **UI state specification:** default/hover/focus/selected/error/success/disabled
5. **Accessibility defects:** ข้อผิดพลาดพร้อมแนวทางแก้และ WCAG SC ที่เกี่ยวข้อง
6. **Component implementation:** CSS variables หรือ framework tokens
7. **Test plan/results:** ระบุชัดว่าอะไรทดสอบแล้ว/ยังไม่ได้ทดสอบ
8. **References:** ลิงก์มาตรฐาน/DOI ตรงกับข้ออ้าง

**ถ้าไม่มีโอกาสรันหรือดูเว็บจริง:** ให้ส่งเป็น *proposed design / not verified in rendered UI* ห้ามอ้างว่าผ่านการตรวจ production แล้ว

---

## 8. Checklist ก่อน Merge / Deploy

### Accessibility

- [ ] ข้อความทั่วไปมี contrast อย่างน้อย 4.5:1 กับพื้นหลังที่ปรากฏจริง
- [ ] ข้อความใหญ่ที่เข้าข่าย large-scale มี contrast อย่างน้อย 3:1
- [ ] UI controls / important graphics ที่อยู่ในขอบเขต SC 1.4.11 มี contrast อย่างน้อย 3:1 กับสีติดกัน
- [ ] ไม่มีสถานะหรือการกระทำสำคัญที่ใช้สีเป็นวิธีสื่อความหมายเพียงวิธีเดียว
- [ ] Focus indicator มองเห็นได้ด้วย keyboard navigation
- [ ] ตรวจจริงทั้ง light/dark, default/hover/focus/selected, error/success, enabled/disabled ตามเกณฑ์ที่ใช้บังคับ
- [ ] ตรวจกรณี alpha, gradient, overlay, images และข้อความบนภาพ
- [ ] สีกราฟสำคัญมี labels/markers/patterns หรือทางเลือกที่เทียบเท่า

### การทำงานและหลักฐาน

- [ ] Semantic tokens สอดคล้องและไม่มีสี hardcoded ที่ไม่มีเหตุผล
- [ ] CTA สำคัญมองเห็นได้ โดยไม่ทำให้ปุ่มอื่นดูสำคัญเท่ากันทั้งหมด
- [ ] ชุดสีไม่ขัด brand constraints และบริบทกลุ่มผู้ใช้
- [ ] ระบุการตัดสินใจเป็น `STD`, `RES`, `PRO` หรือ `HYP` ตามจริง
- [ ] ทุกคำกล่าวจากงานวิจัยมีแหล่ง DOI และไม่อ้างเกินบริบท
- [ ] บันทึกเครื่องมือและขอบเขตการทดสอบ ระบุ defect ที่ยังเปิดอยู่
- [ ] ไม่อ้างว่า “WCAG AA compliant” ทั้งระบบโดยอาศัยการตรวจสีเพียงอย่างเดียว

---

## 9. แหล่งอ้างอิงที่ตรวจสอบได้ (References)

> **หลักฐานคนละประเภท:** `[S]` เป็นมาตรฐาน/คำอธิบายอย่างเป็นทางการ; `[R]` เป็นงานวิจัยที่ผ่านการเผยแพร่ในแหล่งวิชาการ ไม่ใช่ทุกงานจะศึกษา Web App โดยตรง. หมายเลขในเนื้อหาชี้กลับมายังบรรณานุกรมนี้.

### A. มาตรฐานและแหล่งทางการ

- **[S1] W3C (2024).** *Web Content Accessibility Guidelines (WCAG) 2.2*, Recommendation (ฉบับ 12 ธันวาคม 2024). https://www.w3.org/TR/WCAG22/
- **[S2] W3C WAI.** *Understanding SC 1.4.1: Use of Color (Level A).* https://www.w3.org/WAI/WCAG22/Understanding/use-of-color
- **[S3] W3C WAI.** *Understanding SC 1.4.3: Contrast (Minimum) (Level AA).* https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum
- **[S4] W3C WAI.** *Understanding SC 1.4.11: Non-text Contrast (Level AA).* https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast
- **[S5] W3C WAI.** *Understanding SC 2.4.7: Focus Visible (Level AA).* https://www.w3.org/WAI/WCAG22/Understanding/focus-visible
- **[S6] W3C WAI.** *Understanding SC 1.4.6: Contrast (Enhanced) (Level AAA).* https://www.w3.org/WAI/WCAG22/Understanding/contrast-enhanced
- **[S7] ISO (2019).** *ISO 9241-210:2019 — Ergonomics of human-system interaction—Human-centred design for interactive systems.* https://www.iso.org/standard/77520.html — ใช้อ้างกระบวนการออกแบบโดยยึดผู้ใช้เป็นศูนย์กลาง; **ไม่ใช่มาตรฐานค่าสี/contrast และเอกสารฉบับเต็มอาจต้องซื้อ**
- **[S8] W3C WAI.** *Web Accessibility Evaluation Tools List.* https://www.w3.org/WAI/test-evaluate/tools/list/ — เป็นรายชื่อเครื่องมือที่ W3C รวบรวม ไม่ใช่การรับรองเครื่องมือทุกตัว

### B. งานวิจัยที่เผยแพร่ในวารสารและเวที HCI

- **[R1] Hall, R. H., & Hanna, P. (2004).** The impact of web page text-background colour combinations on readability, retention, aesthetics and behavioural intention. *Behaviour & Information Technology, 23*(3), 183–195. https://doi.org/10.1080/01449290410001669932 — ทดลองความแตกต่างของคู่สีเว็บเพจ, ขนาดตัวอย่าง 136 คน
- **[R2] Cyr, D., Head, M., & Larios, H. (2010).** Colour appeal in website design within and across cultures: A multi-method evaluation. *International Journal of Human-Computer Studies, 68*(1–2), 1–21. https://doi.org/10.1016/j.ijhcs.2009.08.005 — สีเว็บไซต์กับความเชื่อถือ/ความพึงพอใจและความต่างข้ามวัฒนธรรม
- **[R3] Elliot, A. J., & Maier, M. A. (2014).** Color psychology: Effects of perceiving color on psychological functioning in humans. *Annual Review of Psychology, 65*, 95–120. https://doi.org/10.1146/annurev-psych-010213-115035 — บทความทบทวนงานวิจัยและข้อจำกัดในการขยายผล
- **[R4] Reinecke, K., Yeh, T., Miratrix, L., Mardiko, R., Zhao, Y., Liu, J., & Gajos, K. Z. (2013).** Predicting users' first impressions of website aesthetics with a quantification of perceived visual complexity and colorfulness. *Proceedings of CHI '13*, 2049–2058. https://doi.org/10.1145/2470654.2481281 — ความซับซ้อนภาพและความหลากสีต่อ first impressions
- **[R5] Reinecke, K., & Gajos, K. Z. (2014).** Quantifying visual preferences around the world. *Proceedings of CHI '14*, 11–20. https://doi.org/10.1145/2556288.2557052 — ข้อมูลการให้คะแนนจำนวนมากและความแตกต่างของกลุ่มผู้ใช้
- **[R6] Palmer, S. E., & Schloss, K. B. (2010).** An ecological valence theory of human color preference. *Proceedings of the National Academy of Sciences, 107*(19), 8877–8882. https://doi.org/10.1073/pnas.0906172107 — ความชอบสีสัมพันธ์กับประสบการณ์/ความชอบต่อสิ่งที่เชื่อมโยงกับสี
- **[R7] Valdez, P., & Mehrabian, A. (1994).** Effects of color on emotions. *Journal of Experimental Psychology: General, 123*(4), 394–409. https://doi.org/10.1037/0096-3445.123.4.394 — ผล hue, saturation และ brightness ต่อการตอบสนองทางอารมณ์ในบริบทการทดลอง ไม่ใช่การวัด UX ของ Web App

### แนวทางตรวจสอบแหล่งอ้างอิง

1. เปิด URL ต้นทางของผู้จัดพิมพ์/หน่วยงานมาตรฐาน หรือ DOI ก่อนใช้อ้าง
2. เช็กชื่อบทความ/ผู้แต่ง/ปี/ประเภทงานวิจัย/วิธีวิจัย/กลุ่มผู้เข้าร่วม
3. อ้าง **ข้อสรุปเท่าที่ abstract/full text รองรับ**; ถ้าอ่านเฉพาะ abstract ห้ามอ้างรายละเอียดวิธีทดลองที่ไม่มีในนั้น
4. แยกข้อกำหนดของ WCAG ที่เป็น normative ออกจากหน้า *Understanding* ที่เป็นคำอธิบาย informative
5. ตรวจการอัปเดตมาตรฐานอีกครั้งก่อนอ้างในเอกสารรับรอง compliance ระดับองค์กร

---

## 10. คำตอบที่ AI ต้องส่งคืนเป็นค่าเริ่มต้น

เมื่อถูกสั่งให้ออกแบบสีหรือ audit ให้ตอบตามโครงนี้โดยย่อก่อนเริ่มแก้โค้ด:

```md
# UX/UI Color Decision Record

## Context
- ระบบ/ผู้ใช้เป้าหมาย:
- งานสำคัญ:
- Accessibility target: WCAG 2.2 AA

## Evidence-based decisions
| Decision | Evidence (STD/RES/PRO/HYP) | Source | Limitation |
|---|---|---|---|

## Semantic color tokens
| Role | Light HEX | Dark HEX | Usage |
|---|---|---|---|

## Contrast audit
| Element/state | Foreground | Background | Ratio | Threshold | Result |
|---|---|---|---:|---:|---|

## Non-color cues
- สถานะ/ป้ายข้อความ/ไอคอน/เส้น/ลวดลาย:

## Implementation
- ไฟล์ที่แก้/โค้ดตัวอย่าง:

## Tests, limitations, unresolved defects
- ผ่านการตรวจจริง:
- ยังไม่ตรวจ:
- สิ่งที่ต้องให้ผู้ใช้ทดลอง:

## References
- ลิงก์ WCAG SC และ DOI ตรงกับข้ออ้าง:
```

**เกณฑ์ความสำเร็จ:** ผู้ใช้ทำงานสำคัญได้โดยไม่ต้องเดาความหมายของสี ข้อความ/องค์ประกอบสำคัญมี contrast ตามเกณฑ์ที่เกี่ยวข้อง ระบบใช้โทเคนสีสม่ำเสมอ และทุกข้ออ้างเรื่องผลเชิงจิตวิทยาหรือประสิทธิภาพมีหลักฐานตรงบริบทหรือถูกระบุว่าเป็นสมมติฐานที่ยังไม่ทดสอบ

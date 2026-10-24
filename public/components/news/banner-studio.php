<section class="view app-panel news-banner-page" id="view-news-banner" hidden>
  <header class="section-head news-page-heading">
    <div><button class="btn ghost news-back" id="banner-close-button" type="button"><i data-lucide="arrow-left"></i><span>กลับไปแก้ไขข่าว</span></button><h2 id="banner-title">ออกแบบรูปปกข่าว</h2><p>ขนาด 1200 × 675 พิกเซล · อัตราส่วน 16:9</p></div>
    <div class="actions"><button class="btn" id="banner-download-button" type="button"><i data-lucide="download"></i><span>ดาวน์โหลด PNG</span></button><button class="btn primary" id="banner-use-button" type="button"><i data-lucide="image-check"></i><span>ใช้เป็นรูปปกข่าว</span></button></div>
  </header>
  <p class="news-review-note" id="banner-flat-cover-note" hidden>รูปปกเดิมเป็น PNG สำเร็จ ไม่ได้เก็บเลเยอร์ข้อความ จึงเริ่มจากเทมเพลตใหม่เพื่อไม่ให้ข้อความซ้อน รูปปกเดิมจะยังอยู่จนกด “ใช้เป็นรูปปกข่าว”</p>
  <div id="banner-dialog" class="news-banner-editor">
    <div class="banner-studio">
      <aside class="banner-controls" aria-label="เครื่องมือออกแบบรูปปก">
        <section class="news-banner-control-section"><h3>เทมเพลต</h3><label>เลือกเทมเพลต<select id="banner-template"><option value="matchday">Match Day</option><option value="result">Match Result</option><option value="spotlight">Sport Spotlight</option></select></label></section>
        <section class="news-banner-control-section"><h3>รูปภาพ</h3><label>รูปการแข่งขัน<input id="banner-image-file" type="file" accept="image/jpeg,image/png,image/webp"></label><p class="news-field-help">เลือกไฟล์ JPG, PNG หรือ WebP จากเครื่อง</p></section>
        <section class="news-banner-control-section"><h3>ข้อความ</h3>
          <label>ชื่อกีฬา<input id="banner-sport" maxlength="80" placeholder="ชื่อกีฬา"></label>
          <label>หัวข้อข่าว<textarea id="banner-headline" maxlength="180" rows="2"></textarea></label>
          <label>คำอธิบาย<textarea id="banner-subtitle" maxlength="220" rows="2"></textarea></label>
          <div class="form-row compact-row"><label>สีกิจกรรม<input id="banner-accent" type="color" value="#f4c400"></label><label>การจัดข้อความ<select id="banner-align"><option value="left">ชิดซ้าย</option><option value="center">กึ่งกลาง</option><option value="right">ชิดขวา</option></select></label></div>
        </section>
        <details class="news-banner-control-section news-disclosure"><summary><span>จัดวางรูปภาพ</span><i data-lucide="chevron-down"></i></summary><div class="news-disclosure-body">
          <label>ขนาดภาพ<input id="banner-image-scale" type="range" min="100" max="220" value="100"></label>
          <div class="form-row compact-row"><label>แนวนอน<input id="banner-image-x" type="range" min="0" max="100" value="50"></label><label>แนวตั้ง<input id="banner-image-y" type="range" min="0" max="100" value="50"></label></div>
        </div></details>
        <details class="news-banner-control-section news-disclosure" open><summary><span>จัดวางข้อความ</span><i data-lucide="chevron-down"></i></summary><div class="news-disclosure-body">
          <label>องค์ประกอบ<select id="banner-active-element"><option value="sport">ชื่อกีฬา</option><option value="headline">หัวข้อข่าว</option><option value="subtitle">คำอธิบาย</option></select></label>
          <div class="form-row compact-row"><label>ตำแหน่ง X<input id="banner-element-x" type="range" min="3" max="97" value="8"></label><label>ตำแหน่ง Y<input id="banner-element-y" type="range" min="5" max="95" value="34"></label></div>
          <label>ขนาดตัวอักษร <output id="banner-element-scale-value">100%</output><input id="banner-element-scale" type="range" min="50" max="160" value="100"></label>
        </div></details>
      </aside>
      <section class="banner-workspace" aria-label="ตัวอย่างรูปปกข่าว">
        <h3>ตัวอย่างรูปปกข่าว <small>(1200 × 675 พิกเซล)</small></h3>
        <div class="canvas-shell"><canvas id="banner-canvas" width="1200" height="675" tabindex="0" aria-label="ตัวอย่างแบนเนอร์ ลากข้อความหรือใช้ปุ่มลูกศรเพื่อจัดตำแหน่ง"></canvas></div>
        <p class="canvas-hint"><i data-lucide="move"></i>ลากข้อความบนภาพเพื่อจัดตำแหน่ง หรือใช้ตัวเลื่อนด้านซ้าย</p>
      </section>
    </div>
  </div>
</section>

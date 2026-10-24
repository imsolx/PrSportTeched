<details class="news-editor-section news-disclosure news-writing-assistant" id="news-ai-panel">
  <summary><i data-lucide="sparkles"></i><span id="news-ai-heading">AI ช่วยร่างข่าว</span><i data-lucide="chevron-down"></i></summary>
  <div class="news-disclosure-body">
    <button type="button" class="btn news-ai-mobile-close" id="news-ai-close"><i data-lucide="x"></i><span>กลับไปเขียนข่าว</span></button>
    <p class="news-ai-intro"><i data-lucide="lightbulb"></i><span>ให้ AI ช่วยร่างข่าวจากข้อมูลการแข่งขัน หรือหัวข้อที่คุณต้องการ คุณแก้ไขเพิ่มเติมได้ก่อนเผยแพร่</span></p>
    <label>แหล่งข้อมูลในการร่างข่าว<select id="news-ai-mode"><option value="match">เลือกจากข้อมูลการแข่งขัน</option><option value="topic">ร่างจากหัวข้อทั่วไป</option></select></label>
    <div id="news-ai-match-fields">
      <div class="news-ai-field-pair">
        <label>ทัวร์นาเมนต์<select data-news-reference="tournament_id" aria-label="ทัวร์นาเมนต์สำหรับ AI"></select></label>
        <label>กีฬา<select data-news-reference="sport_id" aria-label="กีฬาสำหรับ AI"></select></label>
      </div>
      <label>แมตช์<select data-news-reference="match_id" aria-label="แมตช์สำหรับ AI"></select></label>
      <p class="news-field-help" id="news-ai-source">เลือกแมตช์เพื่ออ้างข้อมูลจริงจากระบบ</p>
    </div>
    <label id="news-ai-topic-field" hidden>หัวข้อหรือข้อมูลที่ต้องการประชาสัมพันธ์<textarea id="news-ai-topic" maxlength="500" rows="3" placeholder="เช่น เชิญชวนร่วมกิจกรรมกีฬา พร้อมระบุวัน เวลา และรายละเอียดที่ทราบ"></textarea></label>
    <div class="news-ai-field-pair">
      <label>โทนการเขียน<select id="news-ai-tone"><option value="official">เป็นทางการ</option><option value="energetic">สนุก เร้าใจ</option><option value="inspiring">สร้างแรงบันดาลใจ</option></select></label>
      <label>ความยาว<select id="news-ai-length"><option value="medium">ปานกลาง</option><option value="short">สั้น กระชับ</option><option value="long">รายละเอียดครบ</option></select></label>
    </div>
    <details class="news-ai-options" id="news-ai-options">
      <summary>ปรับรายละเอียดการร่างข่าว <small>(ไม่บังคับ)</small><i data-lucide="chevron-down"></i></summary>
      <label id="news-ai-story-field">ประเภทข่าว<select id="news-ai-story-type"><option value="result">สรุปผลการแข่งขัน</option><option value="preview">ประชาสัมพันธ์ก่อนแข่ง</option><option value="highlight">ไฮไลต์และความประทับใจ</option></select></label>
      <label>ประเด็นที่ต้องการเน้น<textarea id="news-ai-instruction" maxlength="200" rows="2" placeholder="เช่น ชื่นชมนักกีฬา หรือเชิญชวนติดตามรอบถัดไป"></textarea></label>
    </details>
    <div class="news-ai-actions"><button class="btn primary" id="news-ai-button" type="button"><i data-lucide="sparkles"></i><span>สร้างร่างข่าวด้วย AI</span></button><span id="news-ai-status" role="status">พร้อมสร้างร่าง</span></div>
    <p class="news-ai-error" id="news-ai-error" role="alert" hidden></p>
    <?php require __DIR__ . '/news-draft-review.php'; ?>
  </div>
</details>

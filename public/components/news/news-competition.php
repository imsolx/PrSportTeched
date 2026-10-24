<details class="news-editor-section news-disclosure news-competition" id="news-competition-panel">
  <summary><i data-lucide="database"></i><span>เชื่อมโยงข้อมูลการแข่งขัน <small>(ไม่บังคับ)</small><small class="news-disclosure-description">เชื่อมโยงทัวร์นาเมนต์ กีฬา หรือแมตช์ เพื่อดึงข้อมูลมาใช้ประกอบข่าว</small></span><i data-lucide="chevron-down"></i></summary>
  <div class="news-disclosure-body">
    <p class="news-field-help">เลือกแหล่งข้อมูลครั้งเดียว ใช้ร่วมกับร่างข่าวและ AI โดยไม่ต้องกรอกคะแนนซ้ำ</p>
    <div class="form-row"><label>ทัวร์นาเมนต์<select name="tournament_id" class="tournament-select optional"></select></label><label>กีฬา<select name="sport_id" class="sport-select optional"></select></label></div>
    <div class="form-row"><label>ทีม<select name="team_id" id="news-team-link"></select></label><label>แมตช์<select name="match_id" id="news-match-link"></select></label></div>
    <div id="news-match-summary" class="news-match-summary" aria-live="polite"><p>เลือกแมตช์เพื่อดูทีม วันแข่งขัน และผลจากระบบ</p></div>
    <button class="btn" id="news-from-match-button" type="button"><i data-lucide="file-text"></i><span>สร้างร่างจากแมตช์</span></button>
  </div>
</details>

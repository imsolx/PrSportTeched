    <section class="view app-panel" id="view-sports" hidden>
      <div class="grid">
        <form class="card form-card" id="sport-form">
          <h2>ข้อมูลกีฬา</h2>
          <input type="hidden" name="id">
          <label>ชื่อกีฬา <input name="name" required></label>
          <div class="form-row tournament-meta-row">
            <label>ประเภท <select name="type"><option value="team">ทีม</option><option value="individual">รายบุคคล</option></select></label>
            <label>เพศ <select name="gender"><option value="open">ทั่วไป</option><option value="male">ชาย</option><option value="female">หญิง</option><option value="mixed">ผสม</option></select></label>
          </div>
          <label>รูปแบบการบันทึกผล <select name="scoring_type" id="sport-scoring-type" required>
            <option value="" selected disabled>เลือกวิธีบันทึกและตัดสินผล</option>
            <option value="SCORE">คะแนนสะสม (SCORE)</option><option value="SET">คะแนนเป็นเซต (SET)</option><option value="ROUND">คะแนนเป็นรอบ (ROUND)</option>
            <option value="TIME">จับเวลา (TIME)</option><option value="DISTANCE">วัดระยะ (DISTANCE)</option><option value="HEIGHT">วัดความสูง (HEIGHT)</option>
            <option value="JUDGED">คะแนนกรรมการ (JUDGED)</option><option value="TARGET">คะแนนเป้าหมาย (TARGET)</option><option value="COUNT">นับจำนวนครั้ง (COUNT)</option>
            <option value="LOWEST">ตัวเลขต่ำสุด (LOWEST)</option><option value="DECISION">ชนะด้วยเงื่อนไข (DECISION)</option><option value="COMBINED">ผลรวมหลายรายการ (COMBINED)</option>
          </select><small class="field-hint" id="sport-scoring-help">รูปแบบที่เลือกจะสร้างเป็นกติกาหลัก และแมตช์ใหม่จะใช้แบบบันทึกตามกติกานี้</small></label>
          <div class="form-row">
            <label>ผู้เล่นต่ำสุด <input type="number" name="min_players" min="1" value="1"></label>
            <label>ผู้เล่นสูงสุด <input type="number" name="max_players" min="1" value="1"></label>
            <label>ตัวสำรอง <input type="number" name="reserve_players" min="0" value="0"></label>
          </div>
          <fieldset class="sport-icon-field">
            <legend>ไอคอนกีฬา</legend>
            <input type="hidden" name="icon" value="general">
            <div class="sport-icon-picker" id="sport-icon-picker" role="group" aria-label="เลือกไอคอนกีฬา"></div>
          </fieldset>
          <div class="form-row">
            <label>โลโก้กีฬา <input type="file" id="sport-logo-file" accept="image/jpeg,image/png,image/webp"></label>
            <label>ลิงก์โลโก้ <input name="logo_url"></label>
          </div>
          <label>คำอธิบายกีฬาโดยย่อ <textarea name="rules" placeholder="ข้อมูลสำหรับแสดงผลทั่วไป ไม่ใช่ชุดกติกาที่ใช้คำนวณผลการแข่งขัน"></textarea></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกกีฬา</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>รายการกีฬา</h2><div class="table-wrap" id="sports-table"></div></div>
        <form class="card form-card" id="sport-rule-form">
          <h2>ชุดกติกากีฬา</h2>
          <p class="muted">สร้างไว้เป็นข้อมูลกลาง แล้วเลือกใช้ซ้ำในแต่ละทัวร์นาเมนต์ เมื่อกติกาที่ใช้งานอยู่ต้องเปลี่ยน ให้สร้างเป็นเวอร์ชันใหม่เพื่อรักษาประวัติเดิม</p>
          <input type="hidden" name="id">
          <label>กีฬา <select name="sport_id" class="sport-select" required></select></label>
          <label>ชื่อชุดกติกา <input name="name" value="กติกามาตรฐาน" required></label>
          <div class="form-row">
            <label>หมวดกีฬา <input name="category" placeholder="กีฬาทีม / กีฬาต่อสู้"></label>
            <label>ประเภทผู้เล่น <select name="player_type"><option value="team">ทีม</option><option value="individual">บุคคล</option><option value="pair">คู่</option><option value="relay_team">ทีมผลัด</option></select></label>
            <label>จำนวนผู้เล่น <input type="number" name="team_size" min="1" value="1"></label>
          </div>
          <div class="form-row">
            <label>ประเภทการคิดผล <select name="scoring_type" id="scoring-type"><option value="SCORE">คะแนนสะสม (SCORE)</option><option value="SET">คะแนนเป็นเซต (SET)</option><option value="ROUND">คะแนนเป็นรอบ (ROUND)</option><option value="TIME">จับเวลา (TIME)</option><option value="DISTANCE">วัดระยะ (DISTANCE)</option><option value="HEIGHT">วัดความสูง (HEIGHT)</option><option value="JUDGED">คะแนนกรรมการ (JUDGED)</option><option value="TARGET">คะแนนเป้าหมาย (TARGET)</option><option value="COUNT">นับจำนวนครั้ง (COUNT)</option><option value="LOWEST">ตัวเลขต่ำสุด (LOWEST)</option><option value="DECISION">ชนะด้วยเงื่อนไข (DECISION)</option><option value="COMBINED">ผลรวมหลายรายการ (COMBINED)</option><optgroup data-legacy-modes hidden label="รูปแบบเดิมของแมตช์ที่มีอยู่"><option value="PERIOD_SCORE">คะแนนแยกช่วงเดิม</option><option value="END_SCORE">คะแนนแต่ละ End เดิม</option><option value="POINT_RANKING">คะแนนกรรมการเดิม</option><option value="COMBAT">กีฬาต่อสู้เดิม</option><option value="RACE_POSITION">อันดับเข้าเส้นชัยเดิม</option><option value="STROKE">Stroke เดิม</option><option value="MAP">Game / Map เดิม</option></optgroup></select></label>
            <label class="checkbox-line"><input type="checkbox" name="include_in_standings" checked> นำผลไปรวมตารางคะแนนทีม</label>
          </div>
          <div class="form-row">
            <label>ระบบคะแนน <select name="scoring_mode"><option value="points">คะแนนรวม</option><option value="sets">เซต</option><option value="time">เวลา</option><option value="rank">อันดับ</option></select></label>
            <label>ตัดสินผู้ชนะ <select name="win_by"><option value="highest">คะแนนสูงสุด</option><option value="lowest">คะแนน/เวลาต่ำสุด</option><option value="sets">ชนะเซต</option></select></label>
            <label>แต้มต่อเหตุการณ์ <input type="number" name="event_points" min="0" value="1"></label>
          </div>
          <div class="form-row">
            <label>จำนวนช่วง/ครึ่ง/เซต <input type="number" name="period_count" min="1" value="2"></label>
            <label>นาทีต่อช่วง <input type="number" name="period_minutes" min="0" value="45"></label>
            <label class="checkbox-line"><input type="checkbox" name="allow_draw"> อนุญาตผลเสมอ</label>
          </div>
          <fieldset id="scoring-config-fields">
            <legend>ค่ากติกาสำหรับการคำนวณผล</legend>
            <div class="form-row">
              <label data-config-for="SCORE PERIOD_SCORE">ปุ่มเพิ่มแต้ม <input name="quick_points" value="1" placeholder="1,2,3"><small class="field-hint">คั่นด้วยจุลภาค เช่น 1,2,3</small></label>
              <label data-config-for="SET MAP">Best of <input type="number" name="best_of" min="1" value="3"></label>
              <label data-config-for="SET">คะแนนชนะเซต <input type="number" name="points_to_win" min="0" value="21"></label>
              <label data-config-for="SET">ชนะห่าง <input type="number" name="set_win_by" min="1" value="2"></label>
              <label data-config-for="SET">คะแนนสูงสุด <input type="number" name="max_points" min="0" value="30"></label>
            </div>
            <div class="form-row">
              <label data-config-for="SET">เซตปกติ <input type="number" name="normal_set_points" min="0" value="0"></label>
              <label data-config-for="SET">เซตสุดท้าย <input type="number" name="final_set_points" min="0" value="0"></label>
              <label data-config-for="ROUND END_SCORE">คะแนนเป้าหมาย (0 = ไม่กำหนด) <input type="number" name="target_score" min="0" value="0"></label>
              <label data-config-for="JUDGED TARGET POINT_RANKING">รวมคะแนน <select name="aggregation"><option value="SUM">ผลรวม</option><option value="AVERAGE">ค่าเฉลี่ย</option><option value="DROP_HIGHEST_LOWEST">ตัดสูงสุดและต่ำสุด</option></select></label>
              <label data-config-for="COUNT COMBINED">ผู้ชนะ <select name="numeric_win_by"><option value="HIGHEST">ค่ามากกว่า</option><option value="LOWEST">ค่าน้อยกว่า</option></select></label>
            </div>
          </fieldset>
          <label>Scoring Config ขั้นสูง <textarea name="scoring_config" id="scoring-config-json" rows="5" placeholder='{"tie_break":["best_result","second_best_result"]}'></textarea><small class="field-hint">ใช้เพิ่มค่าเฉพาะทางในรูปแบบ JSON โดยค่าจากช่องด้านบนจะมีผลเหนือกว่า</small></label>
          <div class="form-row">
            <label class="checkbox-line"><input type="checkbox" name="overtime_enabled"> มีการต่อเวลา</label>
            <label>จำนวนช่วงต่อเวลา <input type="number" name="overtime_periods" min="1" value="2"></label>
            <label>นาทีต่อช่วง <input type="number" name="overtime_minutes" min="1" value="15"></label>
          </div>
          <div class="form-row">
            <label>ตัดสินเสมอ <select name="tie_break_method"><option value="draw">เสมอ</option><option value="penalty">ยิงจุดโทษ</option><option value="golden_score">Golden score</option><option value="judge">กรรมการตัดสิน</option></select></label>
            <label>แต้มชนะ <input type="number" name="win_points" value="3"></label>
            <label>แต้มเสมอ <input type="number" name="draw_points" value="1"></label>
            <label>แต้มแพ้ <input type="number" name="loss_points" value="0"></label>
          </div>
          <label>ประเภทเหตุการณ์ <textarea name="event_types_text" placeholder="score|ทำคะแนน|1|athlete&#10;foul|ฟาวล์|0|athlete&#10;timeout|ขอเวลานอก|0|team"></textarea><small class="field-hint">รูปแบบ: รหัส | ชื่อ | แต้ม | ผู้เกี่ยวข้อง (athlete, team หรือ optional)</small></label>
          <label>กติกาเพิ่มเติม <textarea name="custom_rules"></textarea></label>
          <label class="checkbox-line"><input type="checkbox" name="is_active" checked> ตั้งเป็นชุดกติกาหลักสำหรับทัวร์นาเมนต์ใหม่</label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกชุดกติกา</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>ชุดกติกาทั้งหมด</h2><div class="table-wrap" id="sport-rules-table"></div></div>
        <form class="card form-card" id="organization-form">
          <h2>มหาวิทยาลัย คณะ และสาขา</h2>
          <input type="hidden" name="id">
          <label>ระดับ <select name="entity_type" id="organization-type"><option value="university">มหาวิทยาลัย</option><option value="faculty">คณะ</option><option value="department">สาขา</option></select></label>
          <label>มหาวิทยาลัยต้นสังกัด <select name="university_id" id="organization-university"></select></label>
          <label>คณะต้นสังกัด <select name="faculty_id" id="organization-faculty"></select></label>
          <div class="form-row"><label>รหัส <input name="code" required></label><label>ชื่อ <input name="name" required></label></div>
          <div class="form-row"><label>โลโก้ <input type="file" id="organization-logo-file" accept="image/jpeg,image/png,image/webp"></label><label>ลิงก์โลโก้ <input name="logo_url"></label></div>
          <label class="checkbox-line"><input type="checkbox" name="is_active" checked> เปิดใช้งาน</label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกหน่วยงาน</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card wide"><h2>โครงสร้างหน่วยงาน</h2><div class="table-wrap" id="organizations-table"></div></div>
      </div>
    </section>

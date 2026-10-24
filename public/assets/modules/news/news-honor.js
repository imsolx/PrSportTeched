// Optional honor-story helper. Read only the existing public roster: no new access to private student APIs.
function bindNewsHonor() {
  bindTaskButton('#news-honor-load', async () => {
    const token = newsUi.loadGeneration;
    $('#news-honor-data-state').textContent = 'กำลังโหลดรายชื่อ…';
    try {
      const form = $('#news-form');
      const data = await request('public.athletes', { tournament_id: form.elements.tournament_id.value, sport_id: form.elements.sport_id.value, team_id: form.elements.team_id.value });
      if (!newsUi.editing || token !== newsUi.loadGeneration) return;
      const rows = (data.teams || []).flatMap(team => (team.players || [])
        .filter(player => player.full_name && String(player.role || '').toLowerCase() !== 'coach')
        .map(player => ({ id: `${team.team_id}:${player.student_id || player.id}`, name: player.full_name, label: `${player.full_name} · ${team.team_name}` })));
      newsUi.honorPlayers = [...new Map(rows.map(row => [row.id, row])).values()];
      setOptions($('#news-honor-athlete'), newsUi.honorPlayers, row => row.id, row => row.label, 'เลือกนักกีฬา (ถ้ามี)');
      $('#news-honor-data-state').textContent = rows.length ? 'ใช้เฉพาะรายชื่อที่เผยแพร่ในระบบ ไม่เปิดข้อมูลนักกีฬาส่วนตัวเพิ่มเติม' : 'ยังไม่มีรายชื่อที่เผยแพร่ตามตัวเลือกนี้ กรอกชื่อหรือทีมที่ตรวจสอบแล้วได้';
    } catch (error) {
      $('#news-honor-data-state').textContent = 'โหลดรายชื่อไม่สำเร็จ ลองใหม่หรือกรอกข้อมูลที่ตรวจสอบแล้ว';
      throw error;
    }
  });
  $('#news-honor-athlete').addEventListener('change', event => {
    const player = (newsUi.honorPlayers || []).find(row => row.id === event.target.value);
    if (player) $('#news-honor-name').value = player.name;
    markNewsDirty();
  });
  bindTaskButton('#news-honor-apply', async () => {
    const name = $('#news-honor-name').value.trim();
    const achievement = $('#news-honor-achievement').value.trim();
    if (!name || !achievement) throw new Error('กรุณาระบุผู้ได้รับรางวัลและผลงานที่ตรวจสอบแล้ว');
    const text = `ผู้ได้รับรางวัล / ทีม: ${name}\nผลงาน / รางวัล: ${achievement}`;
    const body = $('#news-form').elements.body;
    if (!body.value.includes(text)) body.value = [body.value.trim(), text].filter(Boolean).join('\n\n');
    $('#news-honor-name').value = '';
    $('#news-honor-achievement').value = '';
    $('#news-honor-athlete').value = '';
    markNewsDirty();
    toast('เพิ่มข้อมูลลงเนื้อหาแล้ว ตรวจทานก่อนเผยแพร่');
  });
}

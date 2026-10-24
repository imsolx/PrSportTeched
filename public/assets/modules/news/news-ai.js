// Optional AI newsroom. Source selectors live only in news-competition.php; the API contract is unchanged.
function syncNewsAiOptions() {
  syncNewsCompetitionOptions();
}

function validateNewsAiFacts(draft, match) {
  const text = [draft.title, draft.excerpt, draft.body].join(' ');
  const facts = JSON.stringify({ code: match.match_code, tournament: match.tournament_name, sport: match.sport_name, team1: match.team1_id ? match.team1_name : '', team2: match.team2_id ? match.team2_name : '', score: newsMatchIsOfficial(match) ? match.score_label : '', date: match.scheduled_at, venue: match.venue, result: newsMatchIsOfficial(match) ? match.result_summary : {} });
  const known = new Set((facts.match(/\d+/g) || []).map(value => String(Number(value))));
  const year = Number(String(match.scheduled_at || '').slice(0, 4));
  if (year > 1900) known.add(String(year + 543));
  const unknown = (text.match(/\d+/g) || []).find(value => !known.has(String(Number(value))));
  if (unknown) throw new Error('ร่าง AI มีตัวเลขที่ตรวจสอบกับแมตช์ไม่ได้ กรุณาใช้ร่างจากข้อมูลการแข่งขัน หรือสร้างร่างใหม่');
  // This numeric check is only a safety net. Named entities/prose still require explicit human review.
}

function bindNewsAi() {
  bindTaskButton('#news-ai-button', async () => {
    const id = $('#news-match-link').value;
    if (!id) {
      $('#news-competition-panel').open = true;
      $('#news-match-link').focus();
      throw new Error('กรุณาเลือกแมตช์อ้างอิงก่อนใช้ AI');
    }
    const token = ++newsUi.generation;
    const match = await refreshNewsMatch(id);
    const story = $('#news-ai-story-type').value;
    if (match.status === 'completed' && !newsMatchIsOfficial(match)) throw new Error('แมตช์จบแล้วแต่ผลยังไม่ยืนยัน กรุณาใช้ร่างข้อมูลจากระบบก่อนยืนยันผล');
    if (story === 'result' && !newsMatchIsOfficial(match)) throw new Error('ผลยังไม่ยืนยัน จึงสร้างข่าวสรุปผลทางการไม่ได้ กรุณาเลือกรูปแบบก่อนแข่งหรือไฮไลต์');
    if (!match.team1_id || !match.team2_id) throw new Error('AI เดิมไม่มีข้อมูลทีมครบสำหรับแมตช์นี้ กรุณาใช้ “สร้างร่างจากแมตช์” เพื่ออ้างข้อมูลจริงโดยไม่สร้างรายชื่อขึ้นเอง');
    const fingerprint = newsMatchFingerprint(match);
    $('#news-ai-status').textContent = 'กำลังสร้างร่าง…';
    try {
      const result = await request('ai.news', {
        sport_id: match.sport_id || '',
        tournament_id: match.tournament_id || '',
        match_id: id,
        story_type: story,
        tone: $('#news-ai-tone').value,
        instruction: $('#news-ai-instruction').value,
      });
      if (!newsUi.editing || token !== newsUi.generation || $('#news-match-link').value !== id) {
        toast('แหล่งข้อมูลหรือหน้าที่ใช้งานเปลี่ยนแล้ว จึงไม่นำร่างเก่ามาแสดง', true);
        return;
      }
      const fresh = await refreshNewsMatch(id);
      if (newsMatchFingerprint(fresh) !== fingerprint) throw new Error('ผลการแข่งขันเปลี่ยนระหว่างสร้างร่าง กรุณาสร้างใหม่');
      validateNewsAiFacts(result, fresh);
      $('#news-ai-status').textContent = result.provider === 'google-gemini' ? `Gemini: ${result.model}` : 'ร่างอัตโนมัติในระบบ';
      stageNewsDraft({ ...result, match_id: id, sourceFingerprint: fingerprint }, `AI · ${result.model || 'local-summary'} · ${newsMatchPair(match)}`, true);
    } catch (error) {
      $('#news-ai-status').textContent = 'สร้างร่างไม่สำเร็จ — ลองใหม่หรือใช้ข้อมูลจากระบบ';
      throw error;
    }
  });
}

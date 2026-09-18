# Gemini AI Integration

The PHP system now supports Google Gemini for AI-assisted dashboard briefs,
system progress summaries, formal reports, and automatic news drafts.

## Configuration

Set these environment variables before starting PHP/XAMPP:

```bash
GEMINI_API_KEY=your_google_ai_studio_key
GEMINI_MODEL=gemini-3.5-flash
```

Optional:

```bash
GEMINI_API_ENDPOINT=https://generativelanguage.googleapis.com/v1beta
GEMINI_TIMEOUT=25
# Use this only when the machine must access the internet through a proxy.
GEMINI_PROXY=http://proxy.example.ac.th:8080
```

If `GEMINI_API_KEY` is not set, the app keeps working with the built-in
`local-summary` fallback.

If the AI test shows `TCP 443: ไม่ผ่าน` with Windows socket error `10013`,
allow `C:\xampp\php\php.exe` in Windows Firewall/Antivirus or configure
`GEMINI_PROXY` for the organization's proxy.

## AI Actions

- `ai.models`: returns available models and current provider status.
- `ai.dashboard`: summarizes dashboard counts, risks, and next actions.
- `ai.system`: summarizes overall system progress and suggested direction.
- `ai.news`: drafts news from a selected sport, tournament, or match. It accepts
  `story_type`, `tone`, and an optional editorial `instruction`, and also returns
  short title/subtitle text for the banner editor.
- `reports.generate`: creates JSON and CSV report files using Gemini when
  configured, otherwise local-summary.

The dataset sent to Gemini is assembled from the SQLite database with limits
and excludes sensitive values such as password hashes and raw card data.

The REST implementation follows Google's Gemini `models.generateContent`
endpoint with an `x-goog-api-key` header.


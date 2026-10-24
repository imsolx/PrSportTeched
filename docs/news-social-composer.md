# Social news composer

The existing `?view=news-editor`, `news-preview`, and `news-banner` routes
remain compatible with creating and editing existing articles. No schema,
authentication, tournament, scoring, or stored news-category changes were made.

## Components and integration

- `public/pages/news/NewsEditor.php`: action bar, return navigation and real save feedback.
- `public/components/news/news-form.php`: post identity, writing intents, title/body
  counters, working media toolbar and optional excerpt.
- Existing cover, competition, AI, draft-review and publishing components are
  reused in the new layout. There is only one AI panel and one staged draft.
- `public/assets/modules/news/news-composer.js`: presentation and synchronization,
  mirrored competition selectors, cover drag/drop/remove, responsive AI sheet,
  keyboard/focus handling and preview-size switching.
- Existing editor, AI, competition and draft modules retain their named controls,
  payloads and integrations. Responses do not overwrite typed text automatically;
  replacement requires confirmation. Appending preserves the title and excerpt.
- `resources/css/modules/news/news.css`: Tailwind source; compiled output remains
  `public/assets/modules/news/news.css`. Rebuild with `npm run build:css`.

## Data and capability boundaries

- The five top buttons are **writing intents**, not new database categories.
  Competition/result/announcement writing intents use the existing general or
  department publishing category; honor selects `hall_of_fame`. The three actual
  persisted categories remain selectable in the identity and publishing panels.
  Additional writing-intent selection lasts for the editing session; it is not a
  new stored field and resets to the stored category when an article is reopened.
- Title, excerpt and body are never interchanged. The optional excerpt retains
  its original value, and long existing articles are never truncated. The 150 /
  5,000 character counters are writing recommendations, not new database limits.
- Existing `ai.news` request fields and response keys are retained. An explicit
  `[GENERAL_TOPIC]` prefix in `instruction`, with blank match/sport/tournament
  request fields, selects topic-only generation. Existing callers without this
  prefix keep their original match selection behavior. General-topic generation
  uses the existing Gemini client and does not borrow an unrelated match.
- Match facts, result-confirmation checks, fingerprints and human review are
  retained. A pending draft cannot be applied while another draft is generating.
  References selected in the AI panel synchronize with the original form controls.
- No automatic publication, simulated scheduling, videos, attachments, polls,
  rich-text formatting, social reactions, or multi-image galleries were added.
  Cover editing uses the existing single-image `news.upload` contract. The link
  button opens the existing source-URL field; preview renders a safe external link.
- Banner Studio retains editing, PNG download, upload and return-to-composer
  behavior without saving or publishing an article automatically.
- Saved publication status/time comes from the server. Typing during a delayed
  save stays dirty and is not falsely acknowledged as saved.

## Visual verification

Reference: the user's supplied social composer screenshot, supplemented by the
ImageGen concept at
`C:/Users/tanat/.codex/generated_images/01a16ddd-a1c7-77e0-986e-39b8242ae245/exec-1d24d4a4-ee18-4704-ada6-21bd4081cff0.png`.

The interface was rendered with local PHP and Playwright Chrome because the
Browser plugin is not available. Screenshots were inspected with `view_image`,
including the concept and the implementation at the native 1536×1024 viewport.

| Comparison | Implementation and intentional differences |
| --- | --- |
| Layout | Composer left, AI/publishing right; measured 780px / 434px with 22px gap at 1536px. Existing 228px sidebar and top header remain, so editor content starts lower than the reference. |
| Palette | Existing navy/white/yellow/light-gray tokens; blue AI guidance and restrained lavender draft review. Red numbered reference annotations are omitted. |
| Type | Existing Prompt/Sarabun stack, 36px desktop title, 20px title field, readable body and compact inspector labels; inherited application chrome remains. |
| Controls and icons | Existing Lucide icons, six-to-eight-pixel radii, navy primary actions, yellow active intent and functional media actions. Unsupported reference buttons are omitted. |
| Content hierarchy | Immediate title/body entry; optional excerpt and competition links; advanced AI details disclosed on demand. Draft review is a short text preview with an expandable editor. |
| Responsive | One column, persistent bottom action bar, and the same AI panel presented as a keyboard-accessible bottom sheet at 900px and below. |
| Copy | Main creation title, toolbar/action labels and reference hierarchy retained. Edit/published states use accurate contextual labels. Additional copy explains actual cover limits, local fallback, optional fields and unsaved status. No fabricated team/score/media content is seeded into the application. |

## Validation

- `npm run build:css`: pass.
- PHP lint, Node syntax checks and `git diff --check`: pass.
- `PRSPORT_DB_DSN=sqlite::memory:` with `php tools/news-module-test.php`: pass,
  including legacy aliases, response shape, permissions and topic-only fallback.
- `npm run test:ui`: pass, including the expanded `news-ux-ui-test.cjs` flow.
  Covers manual/match/topic drafting, human review, append/replace confirmation,
  source synchronization, save response races, XSS-safe preview, real image upload,
  Banner Studio/export/return, draft/edit/publish/unpublish, pin/archive/delete,
  permission rejection and responsive widths 320/390/768/1440 plus native 1536.
- Additional rendered checks cover edited-draft preview synchronization, the
  Desktop/Mobile preview toggle, drawer close/reopen/Escape and no horizontal
  document overflow. No relevant JavaScript or PHP errors were observed.

Tests use isolated SQLite databases and remove their own uploaded files; they do
not replace live data or write fixture records into the configured database.
Live Gemini responses were **not** exercised: automated runs deliberately clear
credentials and verify the honestly labelled local fallback. Other browsers,
real-provider output quality and a live MySQL deployment remain untested.

# Scoring JavaScript

The main application uses classic scripts, as do the existing Tournament and News
modules. No bundler or ES module loader is required.

## Main entry page

`public/index.php` loads `scoring-config.js`, then `match-selector.js`, then
`app.js`. Module files declare functions without accessing DOM or application
state at load time. `app.js` calls `bindScoringConfig()` and
`bindScoringSelector()` during `DOMContentLoaded`. Both binders are idempotent.

| File | Responsibility | Runtime dependencies |
| --- | --- | --- |
| `scoring-config.js` | Rule form fields, legacy options, existing type inference and defaults | `$`, `$$` from `app.js` |
| `match-selector.js` | Official match lists, refresh/back controls, navigation to recorder pages | `app`, `request`, `loadAdminRefs`, `showView`, `workspace.labels`, `matchStatusClass`, `escapeHtml`, `escapeAttr`, `toast`, `detectScoringType` |

The selector reads the live `app.matches` and `app.sportRules` arrays. It does not
create another state store or API client. `loadAdminRefs()` still loads the shared
reference data; rule requests retain their original action, payload and error
handling. Match cards are replaced on render, then each new card gets one click
listener. Refresh/back listeners are bound once per document.

Public function names such as `openMatchCenter`, `loadOfficialCenter`,
`mountOfficialMatchCenter` and `syncScoringConfigFields` stay available for the
existing application and Tournament module. These scripts depend on `app.js`
helpers at invocation time, so do not invoke their functions during script loading.

`PRSPORT.assetBase` and `PRSPORT.scoringBaseUrl` come from the existing PHP
`app_url()` helper. Use these for sport icons and recorder navigation, respectively,
so URLs work at the deployment root, in a subdirectory and at `/public/index.php`.

## Dedicated recorder pages

The SCORE and SET PHP entry points load their own View and only their respective
`score.js` / `set.js` handlers. Each handler already owns its private state, API
client, validation and DOM bindings inside an IIFE. They do not load `app.js` or
the selector modules. Race result formatting in `app.js` belongs to public result
presentation; it is not an active recording handler.

## Server resolver prerequisite

Before production use of config-driven scoring, address these existing issues in
a separate backend phase:

- `AppService::saveMatchResult()` can accept `scoring_type` from the request.
- Client/server legacy type inference can guess from sport names or fall back to
  `SCORE`. Preserve this compatibility behavior during JavaScript extraction;
  replace it through a server-authoritative resolver with regression coverage.
- Existing UI routes cover SCORE and SET only. Other engine types retain their
  current unsupported-page message; adding recorder pages is a separate phase.

Run `node tools/scoring-modules-ui-test.cjs` for real PHP/API/SQLite checks of
route bases, asset loading, listener ownership, requests and persisted results.
The existing SCORE, SET, recording-mode and full UI smoke tests remain applicable.

# UI Update

The PHP application keeps the legacy navy (#061f42) and gold (#f4c400) colors.
The public pages retain the sports identity; authenticated users have a sidebar
and searchable, paginated data tables. On smaller screens the sidebar becomes
a collapsible menu.

Create/edit forms open in native HTML dialogs. Dialogs support keyboard focus,
Escape, backdrop dismissal, and a confirmation before discarding changes.
Save errors retain entered values, and pending submissions disable form buttons.
Deletion uses a separate confirmation dialog. News cards open full articles.

## Files

- `public/assets/workspace.js`: dialogs, data tables, navigation, shared UI state.
- `public/assets/workspace.css`: theme and responsive layout, after the legacy CSS.
- `public/assets/app.js`: existing API workflows integrated with the new UI.
- `public/index.php`: page structure and local asset loading.

## Verification

Run `npm.cmd run test:ui` on Windows with Chrome and XAMPP installed.
Set `PHP_BINARY` to override the default `C:/xampp/php/php.exe` executable.
The test starts a temporary PHP server on an available port and uses a separate
SQLite file, removed after the run. It does not write to `storage/prsport.sqlite`.
Screenshots are saved in `storage/ui-checks/`.

## Assets

Lucide 0.468.0 and Sarabun fonts are bundled locally; their licenses are beside
the assets. The basketball background is the existing Unsplash photo
`https://images.unsplash.com/photo-1546519638-68e109498ffc`, now stored as
`public/assets/court.jpg` to avoid relying on external image requests at runtime.

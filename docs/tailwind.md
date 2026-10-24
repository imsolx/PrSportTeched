# Tailwind CSS

The app uses Tailwind CSS 4 through the local CLI. PHP serves compiled CSS;
there is no browser CDN compiler and Node is not required by the PHP server.

## Development

```sh
npm ci
npm run build:css
# Rebuild automatically while editing CSS, PHP, or JavaScript:
npm run dev:css
```

Edit **resources/css/**, not the generated files in **public/assets/**.
The build preserves existing output URLs, font/image paths, stylesheet order,
and cache-busting. Commit the generated CSS with source changes so XAMPP and
the existing PHP-only Docker deployment work without a Node runtime.

## Styling

- `resources/css/tailwind.css` defines the theme and scans PHP templates and
  JavaScript renderers. Write complete, literal utility classes so scanning
  can detect them; do not construct class names from string fragments.
- Utilities use the `tw:` prefix, for example
  `class="tw:flex tw:items-center tw:gap-[12px] tw:text-brand"`.
- Existing semantic component classes and JS hooks are retained. Layout,
  spacing, sizing, alignment and other shared declarations now use Tailwind
  `@apply`, for example `.actions { @apply tw:flex tw:items-center; }`.
- Complex grids, sports-specific styles, media queries, accessibility rules,
  and semantic color variables remain custom CSS alongside Tailwind.
- Fixed pixel sizes use arbitrary values to preserve the existing appearance
  at the app's 14px root size. Default Tailwind spacing uses rem units.
- Preflight is omitted because the application already supplies its resets.
  Unlayered component styles take precedence over layered utilities. If a new
  utility needs to override a component declaration, remove that declaration
  from the component or intentionally use an important utility (`tw:flex!`).

Run `npm run build:css` before committing or deploying style/template changes.
Run `npm run test:ui` for the existing end-to-end UI smoke suite.

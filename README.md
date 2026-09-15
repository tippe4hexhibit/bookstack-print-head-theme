# BookStack Print-Ready Export Head Theme

A [BookStack](https://www.bookstackapp.com/) theme override that makes the
self-contained **HTML export** carry its own print stylesheet, print-pagination
JavaScript ([Paged.js](https://pagedjs.org/)), and web fonts — all inlined
directly into the exported file, so it prints correctly when opened straight
from disk, with zero network requests. **PDF export** is left untouched.

Built for the Tippecanoe County 4-H Handbook, but the mechanism (and most of
the theme file) is generic — swap in your own stylesheet/script/fonts under
`assets/` and it'll inline those instead. See "Adapting this for your own
document" below.

## Why this exists

Two separate BookStack limitations led here:

1. **Custom Head Content scripts are stripped from every export format.**
   BookStack's `CustomHtmlHeadContentProvider::forExport()` strips every
   `<script>` tag from Settings → Customization → Custom HTML Head Content,
   for every export format, regardless of the `APP_CONTENT_FILTERING` /
   `ALLOW_CONTENT_SCRIPTS` settings. Reasonable default, but it blocks
   client-side print tooling like Paged.js that only ever needs to run in a
   browser, not on the server.

2. **Loading anything cross-origin from the downloaded file doesn't work.**
   Once scripts can run, the obvious fix looks like: have a small script in
   Custom Head Content dynamically insert `<link>`/`<script src>` tags
   pointing at the real assets on your BookStack instance. That works when
   *viewing* the export, but a downloaded, self-contained HTML file opened
   via `file://` has every local file treated as a unique security origin —
   including relative to itself — so the browser blocks the load with
   `Unsafe attempt to load URL file:///... 'file:' URLs are treated as
   unique security origins.`

The fix for both is the same one BookStack's own exporter already uses for
images: `ExportFormatter::containHtml()` base64-encodes every `<img>` into the
HTML so exports never depend on a live server to render correctly. This theme
does the equivalent for the print stack — CSS, JS, and fonts all get read from
disk and inlined as literal `<style>`/`<script>` blocks and `data:` URIs at
export time, so there's nothing left to fetch.

This is layered on top of BookStack's [theme system](https://www.bookstackapp.com/docs/admin/development/theme-system/),
overriding a single core view — `exports/parts/custom-head.blade.php` — so it
requires **no changes to BookStack core** and survives BookStack upgrades
untouched.

## What it does

- **HTML export**: inlines `assets/handbook-print.css`, `assets/fix-toc-and-links.js`,
  and `assets/pagedjs/paged.polyfill.js` directly into the export's `<head>`,
  in that order (order matters — see the comment block at the top of
  `custom-head.blade.php`). Every `@font-face` `url('*.woff2')` reference in
  the stylesheet gets rewritten to a base64 `data:` URI, reading the matching
  file from `assets/fonts/`, at render time. Whatever is in Settings →
  Customization → Custom HTML Head Content is *also* still echoed as-is, for
  anything unrelated to print you might want there.
- **PDF export**: unchanged, falls through to BookStack's normal
  `CustomHtmlHeadContentProvider::forExport()`, which strips scripts. PDF
  generation happens server-side (dompdf/wkhtmltopdf/a shell command) and
  already does its own CSS paged-media layout, so there's no reason to ship a
  client-side pagination polyfill into that pipeline — and executing arbitrary
  custom JS server-side during PDF generation is a meaningfully different risk
  (SSRF, resource exhaustion) than a script that only ever runs in the browser
  of whoever opens a downloaded HTML file, so that path is deliberately left
  alone.

Since this now handles the print stack unconditionally for every HTML export,
you can delete any script from Custom Head Content that was only there to
load these resources — it's fully superseded.

- **Comment visibility**: the page comments section (`comments/comments.blade.php`)
  is hidden entirely from guests — anyone browsing without logging in, i.e.
  BookStack's built-in "Public" role. Signed-in users still see and interact
  with comments exactly as core BookStack behaves, gated by whatever the
  site's existing per-role permissions (`comment-create-all`, etc.) already
  allow — this override doesn't touch that logic, it only adds an
  `@auth`/`@endauth` wrapper around the section so it never renders for
  anonymous visitors in the first place.

## What's vendored, and licensing

| Path | What | License |
|---|---|---|
| `assets/handbook-print.css` | Print/pagination stylesheet written for this handbook's BookStack markup | Project-specific, no separate license |
| `assets/fix-toc-and-links.js` | Fixes BookStack's page/chapter anchor IDs colliding with Paged.js's own, and annotates the table of contents with real printed page numbers | Project-specific, no separate license |
| `assets/pagedjs/paged.polyfill.js` | [Paged.js](https://gitlab.coko.foundation/pagedjs/pagedjs) v0.4.3, vendored unmodified from unpkg | MIT (license header retained inline in the file) |
| `assets/fonts/*.woff2` | SAP's ["72" typeface](https://github.com/SAP/theming-base-content), the `-full` variant of 7 weights/styles, vendored unmodified from `SAP/theming-base-content` | Apache-2.0 (`assets/fonts/LICENSE.txt`) |

Only the 7 font files actually used by `handbook-print.css` are included (out
of the 26 the upstream `72` family ships across its Condensed/Mono/SemiboldDuplex
variants and base+`-full` pairs) — see the comment at the top of
`handbook-print.css` for why the rest were left out.

## Installation

BookStack looks for an active theme in a `themes/<name>/` folder at the root of
the install, activated by setting the `APP_THEME` environment variable.

1. Copy the entire `themes/print-export-scripts/` folder from this repo into
   your BookStack install's `themes/` directory, preserving its structure
   (subfolders and all — this is more than one file). You can rename
   `print-export-scripts` to whatever you like, just make sure the folder name
   matches `APP_THEME` below. Result should look like:

   ```
   <bookstack-root>/themes/print-export-scripts/
     exports/parts/custom-head.blade.php
     comments/comments.blade.php
     assets/
       handbook-print.css
       fix-toc-and-links.js
       pagedjs/paged.polyfill.js
       fonts/*.woff2, LICENSE.txt
   ```

2. Set the environment variable in your BookStack `.env` file:

   ```
   APP_THEME=print-export-scripts
   ```

3. Clear BookStack's view cache and restart PHP-FPM (or the container) so the
   new theme path and any cached compiled views are picked up:

   ```
   php artisan view:clear
   ```

4. Export any page/chapter/book as HTML, download it, and open it directly
   from disk (not via a URL) to confirm it paginates for print with no
   console errors. Export as PDF and confirm the print stack isn't present
   there (expected — see above).

## Installing on a Pikapods-hosted instance (or other linuxserver.io-based BookStack containers)

Pikapods (and other hosts using the [linuxserver.io BookStack image](https://docs.linuxserver.io/images/docker-bookstack/))
run BookStack from `/app/www`, with a few paths symlinked out to the
persistent `/config` volume so they survive container updates:

```
/app/www/themes -> /config/www/themes
/app/www/.env   -> /config/www/.env
```

So on these hosts:

1. SFTP/SSH into the container.
2. Upload the entire `themes/print-export-scripts/` folder from this repo
   into `/config/www/themes/`, preserving its subfolder structure, so you end
   up with `/config/www/themes/print-export-scripts/exports/parts/custom-head.blade.php`
   and everything under `assets/` alongside it.
   - **Make sure your SFTP client uploads in binary mode**, not text/ASCII
     mode. The `.woff2` font files and `paged.polyfill.js` are binary/large
     text; a client that "helpfully" rewrites line endings in ASCII mode will
     silently corrupt the fonts (they'll fail to render, usually falling back
     to a system font with no error) and can break the JS file outright. Most
     modern SFTP clients default to binary/auto-detect, but it's worth
     confirming if fonts don't show up correctly after upload.
3. Add `APP_THEME=print-export-scripts` to `/config/www/.env`.
   - **Check your host's dashboard first.** Some managed hosts (Pikapods
     included, depending on plan/setup) expose environment variables through
     their own UI and may regenerate `.env` from that on redeploy, which would
     silently overwrite a hand-edited line. If your host offers an environment
     variable panel, set `APP_THEME` there instead of (or in addition to)
     editing the file directly.
   - Don't leave `APP_DEBUG=true` set if you turned it on to troubleshoot —
     BookStack's debug error page (which reports the active theme name,
     handy for confirming `APP_THEME` actually took effect) leaks stack
     traces to anyone who hits a broken URL while it's on.
4. Restart the container/app process so the new `.env` value and theme path
   are picked up. A plain view-cache clear may not be reachable via SFTP-only
   access, so a full container restart is the safe option here.

## Adapting this for your own document

The mechanism in `custom-head.blade.php` is generic — it inlines whatever is
at `assets/handbook-print.css`, `assets/fix-toc-and-links.js`, and
`assets/pagedjs/paged.polyfill.js`, and base64-embeds any `.woff2` a stylesheet
references via `assets/fonts/`. To reuse this for a different document:

- Replace `assets/handbook-print.css` with your own print stylesheet. Keep
  font references as bare filenames in `url('...')` (no path), matching a
  file under `assets/fonts/`.
- Replace `assets/fix-toc-and-links.js` with your own script, or delete the
  file and its `<script>` line in `custom-head.blade.php` if you don't need
  one.
- Swap the fonts in `assets/fonts/` for whatever you're licensed to
  redistribute, and update `assets/fonts/LICENSE.txt` accordingly.
- Paged.js itself (`assets/pagedjs/paged.polyfill.js`) can stay as-is; it's
  not specific to this handbook.

## A note on export file size

Inlining Paged.js plus 7 embedded fonts adds roughly 1.4MB to every HTML
export (unminified Paged.js is the bulk of that, ~900KB). That's the tradeoff
for a print-ready file with zero external dependencies. Worth knowing if
you're exporting very large books repeatedly, though for a one-off handbook
download it's a non-issue.

## Uninstalling

Remove (or blank out) `APP_THEME` from `.env`, then restart. The `themes/`
folder can stay in place unused — BookStack only reads it when `APP_THEME`
points at it.

## Compatibility note

This overrides core BookStack view files, including
`resources/views/exports/parts/custom-head.blade.php` and
`resources/views/comments/comments.blade.php`. If a future BookStack release
restructures one of these views (renames a variable it's passed, changes what
calls it, or changes its markup), the matching override here may silently
stop matching and fall out of sync — the `comments.blade.php` override in
particular is a full copy of core's markup plus an `@auth` wrapper, so it
needs to be re-diffed against core whenever core's comments UI changes.
Check this repo's overrides against the current core files after major
BookStack upgrades.

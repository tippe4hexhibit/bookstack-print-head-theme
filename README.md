# BookStack Print-Ready Export Head Theme

A small [BookStack](https://www.bookstackapp.com/) theme override that lets custom
`<script>` tags in your **Settings → Customization → Custom HTML Head Content**
survive in the self-contained **HTML export**, while still stripping them from
**PDF export**.

## Why this exists

BookStack's built-in HTML head content export logic
(`CustomHtmlHeadContentProvider::forExport()`) strips every `<script>` tag from
your custom head content for **every** export format, regardless of the
`APP_CONTENT_FILTERING` / `ALLOW_CONTENT_SCRIPTS` settings. That's a reasonable
default, but it means you can't ship JavaScript that only needs to run in a
browser — for example, a script that fixes up print/page-break formatting in a
downloaded HTML export.

This override only changes the **exported HTML file**, not the live web pages
(which already respect your content-filtering settings) and not **PDF export**,
which still strips scripts. PDF generation happens server-side (via dompdf,
wkhtmltopdf, or a shell command), so executing arbitrary custom JavaScript there
is a meaningfully different risk (SSRF, resource exhaustion) than a script that
only ever runs in the browser of whoever opens the downloaded HTML file — so
that path is deliberately left alone.

It works by using BookStack's own [theme system](https://www.bookstackapp.com/docs/admin/development/theme-system/)
to override a single view file — `exports/parts/custom-head.blade.php` — so it
requires **no changes to BookStack core**, and survives BookStack upgrades
untouched.

## What it does

- **HTML export**: outputs your full "Custom Head Content" setting as-is,
  scripts included.
- **PDF export**: unchanged, falls through to BookStack's normal
  `CustomHtmlHeadContentProvider::forExport()`, which strips scripts.

## Installation

BookStack looks for an active theme in a `themes/<name>/` folder at the root of
the install, activated by setting the `APP_THEME` environment variable.

1. Copy the `themes/print-export-scripts/` folder from this repo into your
   BookStack install's `themes/` directory. (You can rename
   `print-export-scripts` to whatever theme name you like — just make sure the
   folder name matches `APP_THEME` below.)

   Result should look like:

   ```
   <bookstack-root>/themes/print-export-scripts/exports/parts/custom-head.blade.php
   ```

2. Set the environment variable in your BookStack `.env` file:

   ```
   APP_THEME=print-export-scripts
   ```

3. Make sure `ALLOW_CONTENT_SCRIPTS=true` (or an `APP_CONTENT_FILTERING` value
   without `j`) is set if you want scripts to work on regular web pages too —
   that setting is unrelated to this override, but if you're adding a
   `<script>` tag to Custom Head Content, you likely want it enabled there too.
   See BookStack's `.env.example.complete` for details.

4. Clear BookStack's view cache and restart PHP-FPM (or the container) so the
   new theme path and any cached compiled views are picked up:

   ```
   php artisan view:clear
   ```

5. Add your script to **Settings → Customization → Custom HTML Head Content**,
   then export any page/chapter/book as HTML and confirm the script is
   present. Export as PDF and confirm it isn't.

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
2. Copy `themes/print-export-scripts/` from this repo into
   `/config/www/themes/`, so you end up with:

   ```
   /config/www/themes/print-export-scripts/exports/parts/custom-head.blade.php
   ```

3. Add `APP_THEME=print-export-scripts` to `/config/www/.env`.
   - **Check your host's dashboard first.** Some managed hosts (Pikapods
     included, depending on plan/setup) expose environment variables through
     their own UI and may regenerate `.env` from that on redeploy, which would
     silently overwrite a hand-edited line. If your host offers an environment
     variable panel, set `APP_THEME` there instead of (or in addition to)
     editing the file directly.
4. Restart the container/app process so the new `.env` value and theme path
   are picked up. A plain view-cache clear may not be reachable via SFTP-only
   access, so a full container restart is the safe option here.

## Uninstalling

Remove (or blank out) `APP_THEME` from `.env`, then restart. The `themes/`
folder can stay in place unused — BookStack only reads it when `APP_THEME`
points at it.

## Compatibility note

This overrides one core BookStack view file
(`resources/views/exports/parts/custom-head.blade.php`). If a future BookStack
release restructures that view (renames the `$format` variable it's passed, or
changes what calls it), this override may silently stop matching and fall out
of sync. Check this repo's override against the current core file after major
BookStack upgrades.

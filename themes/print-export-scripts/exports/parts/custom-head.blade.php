@inject('headContent', 'BookStack\Theming\CustomHtmlHeadContentProvider')

{{--
    Override of resources/views/exports/parts/custom-head.blade.php.

    Two independent things happen here, both scoped to HTML export only:

    1. Whatever is in Settings -> Customization -> Custom HTML Head Content
       is still echoed raw and unfiltered, same as core would for HTML
       export, in case it's ever used for something unrelated to print.

    2. The whole print stack (handbook-print.css, the TOC/anchor fix
       script, and Paged.js itself, all vendored under assets/) is read
       from disk and inlined directly into the export: styles and scripts
       as literal <style>/<script> blocks, and every font the stylesheet
       references as a base64 data: URI. Nothing points at an external
       URL. BookStack already does the equivalent for exported images
       (base64-encoding them into the HTML in
       ExportFormatter::containHtml()), for the same underlying reason: a
       downloaded, self-contained HTML file opened via file:// treats
       every origin as unique and can't load anything cross-origin, not
       even a second copy of itself. That's the direct cause of the
       "'file:' URLs are treated as unique security origins" error seen
       when this print stack was previously loaded via external
       <script src>/<link href> tags instead of inlined like this.

    PDF export is untouched here (falls straight through to forExport()):
    dompdf/wkhtmltopdf already do CSS paged-media layout server-side, so
    there's no reason to ship a client-side pagination polyfill into that
    pipeline, and PDF export stays scripts-off per
    CustomHtmlHeadContentProvider::forExport()'s own handling.

    Script/style order below matters and is deliberate: handbook-print.css
    first (after BookStack's own exported <style>, so it wins the
    cascade), then fix-toc-and-links.js (which must run and set
    window.PagedConfig before Paged.js itself does anything with it), then
    paged.polyfill.js last. This mirrors the exact order the original
    dynamic-<script>-injection version used and was tested against.
--}}
@php
    $printAssets = null;
    if (($format ?? '') === 'html') {
        $printCssPath = theme_path('assets/handbook-print.css');
        $fixScriptPath = theme_path('assets/fix-toc-and-links.js');
        $pagedJsPath = theme_path('assets/pagedjs/paged.polyfill.js');

        if ($printCssPath && file_exists($printCssPath) && file_exists($fixScriptPath) && file_exists($pagedJsPath)) {
            $css = preg_replace_callback(
                "/url\('([^'\/]+\.woff2)'\)/",
                function ($matches) {
                    $fontPath = theme_path('assets/fonts/' . $matches[1]);
                    if (!$fontPath || !file_exists($fontPath)) {
                        return $matches[0];
                    }
                    $data = base64_encode(file_get_contents($fontPath));
                    return "url('data:font/woff2;base64,{$data}')";
                },
                file_get_contents($printCssPath)
            );

            // Defends against a future update to either vendored script ever
            // containing a literal "</script" inside a string/regex, which
            // would otherwise prematurely close the <script> tag it's inlined
            // into below. Neither currently does (checked against the pinned
            // Paged.js 0.4.3 build this theme ships).
            $inlineSafeJs = fn (string $js) => str_replace('</script', '<\/script', $js);

            $printAssets = [
                'css'       => $css,
                'fixScript' => $inlineSafeJs(file_get_contents($fixScriptPath)),
                'pagedJs'   => $inlineSafeJs(file_get_contents($pagedJsPath)),
            ];
        }
    }
@endphp
@if(setting('app-custom-head'))
<!-- Custom user content -->
@if(($format ?? '') === 'pdf')
    {!! $headContent->forExport() !!}
@else
    {!! setting('app-custom-head') !!}
@endif
<!-- End custom user content -->
@endif
@if($printAssets)
<!-- Inlined print stack: handbook-print.css, fix-toc-and-links.js, Paged.js 0.4.3 (MIT) -->
<style>{!! $printAssets['css'] !!}</style>
<script>{!! $printAssets['fixScript'] !!}</script>
<script>{!! $printAssets['pagedJs'] !!}</script>
<!-- End inlined print stack -->
@endif

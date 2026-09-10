@inject('headContent', 'BookStack\Theming\CustomHtmlHeadContentProvider')

{{--
    Override of resources/views/exports/parts/custom-head.blade.php.

    Core's CustomHtmlHeadContentProvider::forExport() always strips <script> tags
    from the "Custom Head Content" setting for every export format. We want our
    print-formatting script to survive in the self-contained HTML export, while
    PDF export (rendered server-side via dompdf/wkhtmltopdf/a shell command) stays
    script-free, since executing arbitrary custom JS server-side during PDF
    generation is a real risk (SSRF, resource exhaustion) that doesn't apply to a
    script that only ever runs in the browser of whoever opens the exported file.
--}}
@if(setting('app-custom-head'))
<!-- Custom user content -->
@if(($format ?? '') === 'pdf')
    {!! $headContent->forExport() !!}
@else
    {!! setting('app-custom-head') !!}
@endif
<!-- End custom user content -->
@endif

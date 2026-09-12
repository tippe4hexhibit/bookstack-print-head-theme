@extends('layouts.export')

@section('title', $book->name)

@section('content')

    {{--
        Override of resources/views/exports/book.blade.php.

        4-H front-matter convention: if this book has top-level pages named
        exactly "Title Page" and/or "Blank Page" (case-insensitive; not
        inside a chapter), they're pulled out of normal book order and
        rendered first, in that fixed order -- ahead of BookStack's own
        generated title/TOC block -- instead of wherever they'd naturally
        sort. This is the confirmed-working structure for the handbook's
        cover image (Title Page) and its blank backside (Blank Page): live
        browser testing this session showed every auto-generated substitute
        for an authored Blank Page (two bare page-breaks, an empty
        placeholder div, a div with only a non-breaking space) is handled
        inconsistently by this Paged.js build -- sometimes a real blank
        page, sometimes silently collapsed away, with no template change
        between a working export and a failing one. An authored page with
        real (if visually blank) editor content is what actually worked
        reliably, so that's what this uses. Don't try to replace Blank Page
        with generated markup again without re-verifying against a real
        export -- this has already regressed twice.

        Both are excluded from $remainingChildren, which does two things:
        keeps them out of the table of contents (built from
        $remainingChildren below), and -- paired with the "no running
        header/footer" @page rules and the counter-reset on the relocated
        book <h1> in handbook-print.css -- keeps them out of the printed
        page-number sequence too. In practice the counter-reset reliably
        makes the relocated book title print as "1", but @page :blank has
        not reliably suppressed the Blank Page's own number -- a visible
        "2" there has been accepted rather than chased further, since a
        fix would mean CSS named pages, which this file has already found
        to cause a stray blank page elsewhere (see the chapter-hint note
        further down in handbook-print.css).

        Each is wrapped in .front-matter-page so handbook-print.css can
        constrain any image inside to one page's height. Without that, a
        large cover image (inserted at its natural size, no height
        constraint) can be taller than a printed page and overflow onto the
        next one -- which visually looks like the pages came out in the
        wrong order even though the underlying HTML order here is correct.

        Books with no page named either of these render identically to
        core: $frontMatterItems stays empty, $remainingChildren is just
        $bookChildren unchanged, and the front-matter block below is
        skipped entirely.

        Deliberately no <div class="page-break"> before the first
        front-matter item: every other page-break in this document follows
        real preceding content, so break-after:page just cleanly ends the
        page already in progress. Verified live in a browser that a
        page-break with nothing before it -- as the very first element in
        the whole document -- gets its own blank page before the content
        after it starts, which produced a genuine extra blank page ahead of
        the cover. Breaks are still placed between the two front-matter
        items and before the relocated book title, since those always have
        real content immediately before them.
    --}}
    @php
        $frontMatterKeys = ['title page', 'blank page'];
        $frontMatter = [];
        $remainingChildren = [];
        foreach ($bookChildren as $bookChild) {
            $key = strtolower(trim($bookChild->name));
            if (!$bookChild->isA('chapter') && in_array($key, $frontMatterKeys, true) && !isset($frontMatter[$key])) {
                $frontMatter[$key] = $bookChild;
            } else {
                $remainingChildren[] = $bookChild;
            }
        }
        $frontMatterItems = array_values(array_filter(array_map(
            fn ($key) => $frontMatter[$key] ?? null,
            $frontMatterKeys
        )));
    @endphp

    @foreach($frontMatterItems as $index => $frontMatterItem)
        @if($index > 0)
            <div class="page-break"></div>
        @endif
        <div class="front-matter-page">
            {!! $frontMatterItem->html !!}
        </div>
    @endforeach

    @if(!empty($frontMatterItems))
        <div class="page-break"></div>
    @endif
    <h1 style="font-size: 4.8em">{{$book->name}}</h1>
    <div>{!! $book->descriptionInfo()->getHtml() !!}</div>

    @include('exports.parts.book-contents-menu', ['children' => $remainingChildren])

    @foreach($remainingChildren as $bookChild)
        @if($bookChild->isA('chapter'))
            @include('exports.parts.chapter-item', ['chapter' => $bookChild])
        @else
            @include('exports.parts.page-item', ['page' => $bookChild, 'chapter' => null])
        @endif
    @endforeach

@endsection

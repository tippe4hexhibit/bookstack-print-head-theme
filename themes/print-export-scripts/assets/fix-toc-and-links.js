/**
 * Gate: only do anything on BookStack's own HTML export output, never on
 * a live wiki page.
 *
 * This whole file is meant to be pasted into BookStack's Custom HTML
 * Head Content setting, which applies to every page on the site, live
 * wiki pages included, not just the exported HTML file this is actually
 * for. Loading paged.polyfill.js and running it against a live page
 * would be wrong (there's nothing to paginate, and it would just waste
 * work at best).
 *
 * BookStack itself marks its HTML export output with
 * <body class="export export-format-html ...">, a class no live page
 * carries. That's not a guess, it's checked directly against a real
 * export's markup. Everything below is gated on it.
 *
 * paged.polyfill.js still needs to be present as a <script> tag (it has
 * to be, to be available at all), but simply including the tag doesn't
 * make it act: the polyfill's own bootstrap only auto-renders when
 * config.auto isn't explicitly false, and this sets that to false the
 * moment it determines the current document isn't an export, before
 * the polyfill's bootstrap ever checks it.
 */
window.PagedConfig = {
  before: function () {
    if (!document.body.classList.contains("export")) {
      window.PagedConfig.auto = false;
      return;
    }

    var elements = document.querySelectorAll(
      '[id^="page-"], [id^="chapter-"]'
    );
    var idMap = {};
    elements.forEach(function (el) {
      var oldId = el.id;
      var newId = oldId + "-bk";
      idMap[oldId] = newId;
      el.id = newId;
    });

    var links = document.querySelectorAll(
      'a[href^="#page-"], a[href^="#chapter-"]'
    );
    links.forEach(function (a) {
      var oldHref = a.getAttribute("href").slice(1);
      if (idMap[oldHref]) {
        a.setAttribute("href", "#" + idMap[oldHref]);
      }
    });

    /**
     * Paged.js pulls the whole document into an inert <template> the
     * moment it starts, then rebuilds it piece by piece into
     * .pagedjs_pages as it paginates. For a document this size that can
     * take well over a minute. Until a given target page has actually
     * been built, its id doesn't exist anywhere in the live DOM yet (it's
     * still inert inside that <template>), so a TOC link clicked early
     * has nothing to scroll to -- verified live that the resulting
     * position reads as landing on whatever page happened to be last
     * rendered, not on the intended header. The TOC itself sits near the
     * front of the book and so becomes clickable long before later
     * chapters are paginated, making this easy to hit in practice.
     *
     * Lock the TOC's links here, before pagination starts, and release
     * them in after() once pagination has actually finished and every
     * target id is live. This has to be an inline style on each link
     * rather than a <style> rule: Paged.js's preview() strips every
     * <style>/<link> out of the document right after before() returns
     * (to feed them into its own stylesheet pipeline instead), which
     * drops the id off any <style> tag added here -- confirmed live that
     * a lock rule added that way survives, but after() can no longer find
     * it to remove it, so the TOC would stay locked forever. Inline
     * styles are plain element attributes, so they get carried along by
     * the clone Paged.js makes of each link when it builds the final
     * rendered page, and after() can find and clear them on those clones
     * directly.
     */
    var tocLinks = document.querySelectorAll(".contents a");
    tocLinks.forEach(function (a) {
      a.style.pointerEvents = "none";
      a.style.opacity = "0.5";
      a.style.cursor = "default";
    });

    /**
     * Marks the book-title h1 (the big "Tippecanoe County 4-H Handbook
     * 2026" page, immediately before the table of contents -- not to be
     * confused with the "Title Page" BookStack entity, which is the cover
     * image and a separate, earlier physical page) so after() can find it
     * again once it's been cloned into its rendered page. It has no id of
     * its own to select by (see handbook-print.css's title-page comment),
     * and this has to happen before Paged.js clones it, for the same
     * reason the TOC lock above uses an inline style rather than
     * anything added afterward.
     */
    var titleH1 = document.querySelector(".page-content > h1:first-of-type");
    if (titleH1) {
      titleH1.setAttribute("data-title-page", "");
    }
  },

  after: function (flow) {
    if (!document.body.classList.contains("export")) {
      return;
    }

    var tocLinks = document.querySelectorAll(".contents a");
    tocLinks.forEach(function (a) {
      a.style.pointerEvents = "";
      a.style.opacity = "";
      a.style.cursor = "";
    });

    /**
     * Hides the running header/footer on the cover, the blank backside
     * (when present), and the book-title/TOC-lead-in page, so none of
     * them show a page number -- while leaving the underlying page count
     * completely alone, so every page after them still prints its true
     * physical position (see handbook-print.css's front-matter comment
     * for why nothing here resets or offsets that count). This runs
     * against the already-rendered page rather than through CSS, because
     * the two native CSS ways to target one specific physical page both
     * failed here: @page :blank only matches a page with no generated
     * content at all, and the blank backside, precisely because it's a
     * real authored page (see book.blade.php's comment on why), always
     * has some; and CSS named pages, confirmed live, reproduce the stray
     * blank page bug already documented elsewhere in handbook-print.css
     * for this polyfill build. Neither problem exists for a plain DOM
     * search after layout is done.
     */
    document.querySelectorAll(".pagedjs_page").forEach(function (pg) {
      var isFrontMatter = pg.querySelector(".front-matter-page");
      var isTitlePage = pg.querySelector("[data-title-page]");
      if (!isFrontMatter && !isTitlePage) {
        return;
      }
      pg.querySelectorAll(
        [
          ".pagedjs_margin-top",
          ".pagedjs_margin-bottom",
          ".pagedjs_margin-top-left-corner-holder",
          ".pagedjs_margin-top-right-corner-holder",
          ".pagedjs_margin-bottom-left-corner-holder",
          ".pagedjs_margin-bottom-right-corner-holder",
        ].join(", ")
      ).forEach(function (marginBox) {
        marginBox.style.visibility = "hidden";
      });
    });

    var pageEls = Array.prototype.slice.call(
      document.querySelectorAll(".pagedjs_page")
    );
    var links = document.querySelectorAll('.contents li > a[href^="#"]');

    links.forEach(function (a) {
      var id = a.getAttribute("href").slice(1);
      for (var i = 0; i < pageEls.length; i++) {
        var pg = pageEls[i];
        var el = pg.querySelector("#" + CSS.escape(id));
        if (el) {
          var realPage = pg.getAttribute("data-page-number");
          if (realPage) {
            a.setAttribute("data-real-page", realPage);
          }
          break;
        }
      }
    });

    var style = document.createElement("style");
    style.textContent =
      ".contents li > a::after { content: attr(data-real-page) !important; }";
    document.head.appendChild(style);
  },
};

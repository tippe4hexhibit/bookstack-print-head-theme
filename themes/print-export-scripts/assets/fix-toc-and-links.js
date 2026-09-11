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
  },

  after: function (flow) {
    if (!document.body.classList.contains("export")) {
      return;
    }

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

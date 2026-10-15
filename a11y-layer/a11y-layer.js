/* Accessibility Layer: phone heading fit. */
(function () {
  'use strict';

  /* Phones: a heading word too long for the screen ("ENCLOSURES" at 320px) would split mid-word.
     Only those headings step down in size until every word fits whole. */
  var fitted = [];

  function wordSplits(heading) {
    var walker = document.createTreeWalker(heading, NodeFilter.SHOW_TEXT);
    var node, match, range = document.createRange(), re = /[^\s\-\/\u2014\u2013]+/g;
    while ((node = walker.nextNode())) {
      re.lastIndex = 0;
      while ((match = re.exec(node.data))) {
        range.setStart(node, match.index);
        range.setEnd(node, match.index + match[0].length);
        var tops = {};
        Array.prototype.forEach.call(range.getClientRects(), function (r) { if (r.width) tops[Math.round(r.top)] = 1; });
        if (Object.keys(tops).length > 1) return true;
      }
    }
    return false;
  }

  function fitHeadings() {
    fitted.forEach(function (pair) { pair[0].style.fontSize = pair[1]; });
    fitted = [];
    if (window.innerWidth > 480) return;
    document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(function (h) {
      if (!h.offsetParent || !wordSplits(h)) return;
      var start = parseFloat(getComputedStyle(h).fontSize), size = start;
      fitted.push([h, h.style.fontSize]);
      while (wordSplits(h) && size > start * 0.6) {
        size = size * 0.95;
        h.style.fontSize = size + 'px';
      }
    });
  }

  function start() {
    (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(fitHeadings);
    var timer;
    window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(fitHeadings, 200); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

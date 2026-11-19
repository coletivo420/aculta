return (function () {
  function parse(c) {
    var m = c.match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    var p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number);
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  }
  function lum(c) {
    var f = [c.r, c.g, c.b].map(function (v) {
      v = v / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * f[0] + 0.7152 * f[1] + 0.0722 * f[2];
  }
  function ratio(a, b) {
    var x = lum(a), y = lum(b);
    var hi = Math.max(x, y), lo = Math.min(x, y);
    return (hi + 0.05) / (lo + 0.05);
  }
  function bgOf(el) {
    var n = el;
    while (n && n.nodeType === 1) {
      var c = parse(getComputedStyle(n).backgroundColor);
      if (c && c.a > 0.95) return c;
      n = n.parentElement;
    }
    return { r: 23, g: 21, b: 19, a: 1 };
  }
  var page = document.querySelector('.layout-page__content') || document.querySelector('main');
  var out = [];
  var origem = null;
  var walker = document.createTreeWalker(page, NodeFilter.SHOW_TEXT);
  var node;
  while ((node = walker.nextNode())) {
    var t = node.textContent.trim();
    if (!t) continue;
    var el = node.parentElement;
    if (!el) continue;
    var cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    var fg = parse(cs.color);
    if (!fg) continue;
    var bg = bgOf(el);
    var r = ratio(fg, bg);
    if (r < 4.5) {
      var story = el.closest('.ui_patterns_story');
      var name = story
        ? (story.querySelector('.ui_patterns_story__name') || {}).textContent
        : (el.closest('.ui_patterns_component')
          ? (el.closest('.ui_patterns_component').querySelector('.ui_patterns_component__title') || {}).textContent
          : '(página)');
      if (origem === null) {
        var regras = [];
        var anc = el;
        var alvos = [];
        while (anc && anc.nodeType === 1) { alvos.push(anc); anc = anc.parentElement; }
        Array.prototype.forEach.call(document.styleSheets, function (ss) {
          var rs;
          try { rs = ss.cssRules; } catch (e) { return; }
          Array.prototype.forEach.call(rs, function (rule) {
            if (rule.selectorText && rule.style && rule.style.color) {
              try {
                alvos.forEach(function (a, idx) {
                  if (a.matches(rule.selectorText)) {
                    regras.push({ nivel: idx, elemento: a.tagName + '.' + String(a.className).slice(0, 50), sel: rule.selectorText.slice(0, 120), cor: rule.style.color, folha: (ss.href || 'inline').slice(-60) });
                  }
                });
              } catch (e) { /* seletor inválido para o elemento */ }
            }
          });
        });
        origem = {
          texto: t.slice(0, 40),
          elemento: el.tagName + '.' + String(el.className).slice(0, 80),
          corComputada: cs.color,
          regras: regras.slice(0, 15),
          tokenTextoNoElemento: getComputedStyle(el).getPropertyValue('--color-text-primary'),
          tokenTextoNaPagina: getComputedStyle(page).getPropertyValue('--color-text-primary'),
          modoDaPagina: page.getAttribute('data-theme')
        };
      }
      out.push({
        componente: (name || '').trim().slice(0, 60),
        texto: t.slice(0, 50),
        cor: cs.color,
        fundo: 'rgb(' + bg.r + ',' + bg.g + ',' + bg.b + ')',
        razao: Math.round(r * 100) / 100
      });
    }
  }
  return { falhas: out.slice(0, 400), origem: origem };
})();

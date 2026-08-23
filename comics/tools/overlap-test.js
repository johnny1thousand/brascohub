// Hunts for elements that visually collide, and for text whose own line boxes
// overlap, across every width a phone or tablet is likely to be.
const { chromium } = require('playwright');
const WIDTHS = [320, 360, 375, 390, 393, 402, 412, 430, 480, 540, 620, 680, 768, 820, 900, 980, 1024, 1180, 1340];

const probe = () => {
  const out = { collisions: [], lineOverlap: [], overflow: [], hscroll: 0 };
  const R = el => el.getBoundingClientRect();
  const inter = (a, b) => {
    const x = Math.min(a.right, b.right) - Math.max(a.left, b.left);
    const y = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
    return (x > 1 && y > 1) ? Math.round(Math.min(x, y)) : 0;
  };
  // things that must never sit on top of each other
  const pairs = [
    ['.hero > div:first-child', '.shelf'],
    ['.hero h1', '.hero .lede'],
    ['.hero .lede', '.cta-row'],
    ['.cta-row', '.cta-note'],
    ['.topbar a:first-child', '.topbar nav'],
    ['.hero', '.band'],
    ['.band .steps', '.band h2'],
    ['.split .panel:first-child', '.split .panel:last-child'],
    ['.shelf', '.band']
  ];
  for (const [aSel, bSel] of pairs) {
    const a = document.querySelector(aSel), b = document.querySelector(bSel);
    if (!a || !b) continue;
    const px = inter(R(a), R(b));
    if (px) out.collisions.push(aSel + ' x ' + bSel + ' = ' + px + 'px');
  }
  // line boxes inside the big headings: do any two lines actually overlap?
  for (const sel of ['.hero h1', '.band h2', '.section > h2', '.panel h2']) {
    document.querySelectorAll(sel).forEach((h, hi) => {
      const rects = [];
      const walk = n => {
        if (n.nodeType === 3) {
          const r = document.createRange(); r.selectNodeContents(n);
          for (const rect of r.getClientRects()) rects.push(rect);
        } else n.childNodes.forEach(walk);
      };
      walk(h);
      rects.sort((a, b) => a.top - b.top);
      for (let i = 1; i < rects.length; i++) {
        const prev = rects[i - 1], cur = rects[i];
        // same line? (tops within 2px) then it is not a wrap, skip
        if (Math.abs(cur.top - prev.top) < 2) continue;
        const ov = prev.bottom - cur.top;
        if (ov > 2) out.lineOverlap.push(sel + '#' + hi + ' lines ' + (i - 1) + '/' + i + ' overlap ' + Math.round(ov) + 'px');
      }
    });
  }
  // anything whose content is wider than its box
  document.querySelectorAll('.hero h1, .hero .lede, .btn, .mini p, .step, .feat, .panel, .shelf-head').forEach(el => {
    if (el.scrollWidth - el.clientWidth > 1) out.overflow.push((el.className || el.tagName) + ' overflows by ' + (el.scrollWidth - el.clientWidth) + 'px');
  });
  out.hscroll = document.documentElement.scrollWidth - document.documentElement.clientWidth;
  return out;
};

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  let bad = 0;
  for (const w of WIDTHS) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 2, isMobile: w <= 480, hasTouch: w <= 480 });
    const p = await ctx.newPage();
    await p.goto('http://127.0.0.1:8899/', { waitUntil: 'networkidle' });
    await p.waitForTimeout(250);
    const r = await p.evaluate(probe);
    const issues = [...r.collisions, ...r.lineOverlap, ...r.overflow, ...(r.hscroll > 0 ? ['page scrolls sideways by ' + r.hscroll + 'px'] : [])];
    if (issues.length) { bad++; console.log('FAIL ' + w + 'px'); issues.forEach(i => console.log('       ' + i)); await p.screenshot({ path: 'ov-' + w + '.png', fullPage: false }); }
    else console.log('  ok ' + w + 'px');
    await ctx.close();
  }
  console.log(bad ? '\n' + bad + ' width(s) with problems' : '\nno overlaps, no overflow, no sideways scroll at any width');
  await b.close();
  process.exit(bad ? 1 : 0);
})();

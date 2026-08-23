// The app draws the donut in JS, the public page in PHP. If they ever disagree,
// one of the two pages is lying about the same shelf — so compare the actual
// SVG they produce, path by path, over a set of awkward distributions.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const CASES = {
  'two characters':        { 'Spider-Man': 3, 'Batman': 1 },
  'exactly four':          { 'A': 4, 'B': 3, 'C': 2, 'D': 1 },
  'five, so one Other':    { 'A': 5, 'B': 4, 'C': 3, 'D': 2, 'E': 1 },
  'a long tail':           { 'A': 20, 'B': 3, 'C': 2, 'D': 2, 'E': 1, 'F': 1, 'G': 1, 'H': 1 },
  'a dead heat':           { 'A': 2, 'B': 2, 'C': 2, 'D': 2, 'E': 2 },
  'one huge, one tiny':    { 'A': 99, 'B': 1 },
  'ties broken by name':   { 'Zorro': 2, 'Ant-Man': 2, 'Mister X': 2 },
  'unfiled books too':     { 'A': 3, 'B': 2, '': 4 }
};
const books = counts => Object.entries(counts).flatMap(([c, n]) => Array.from({ length: n }, () => c));
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage();
  await p.goto('http://127.0.0.1:8899/app/', { waitUntil: 'networkidle' });
  await p.fill('#loginUser','Thanos'); await p.fill('#loginPass','Cra3653793!@#'); await p.click('#loginBtn');
  await p.waitForFunction(()=>!document.getElementById('loginScreen').classList.contains('open'));
  await p.waitForTimeout(600);
  let bad = 0;
  for (const [name, counts] of Object.entries(CASES)) {
    const list = books(counts);
    const js = await p.evaluate(chars => {
      const t = window.__ctChart;
      const data = t.characterSlices(chars.map(c => ({ character: c })));
      return t.donutSvg(data);
    }, list);
    const php = execFileSync('php', ['/home/user/brascohub/comics/tools/chart-parity.php', JSON.stringify(list)]).toString().trim();
    // the app tags each slice with data-char so a click can filter the shelf;
    // the public page has nothing to filter. That attribute is the one licensed
    // difference — everything else must match byte for byte.
    const strip = t => t.replace(/ data-char="[^"]*"/g, '').trim();
    const same = strip(js) === strip(php);
    if (!same) {
      bad++;
      console.log('MISMATCH ' + name);
      console.log('   js : ' + strip(js).slice(0, 240));
      console.log('   php: ' + strip(php).slice(0, 240));
    } else {
      const slices = (js.match(/<path/g) || []).length;
      console.log('  identical  ' + name.padEnd(22) + slices + ' slices, ' + js.length + ' bytes');
    }
  }
  console.log(bad ? '\n' + bad + ' case(s) where the two pages would disagree' : '\nthe app and the public page draw byte-identical charts');
  await b.close();
  process.exit(bad ? 1 : 0);
})();

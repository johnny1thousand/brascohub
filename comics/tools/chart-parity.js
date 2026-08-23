// The app builds the bars in JS, the public page in PHP. Render the same
// distributions through both and diff the markup, so the two pages can never
// tell different stories about one shelf.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const CASES = {
  'two characters':      { 'Spider-Man': 3, 'Batman': 1 },
  'exactly five':        { 'A': 5, 'B': 4, 'C': 3, 'D': 2, 'E': 1 },
  'a long tail':         { 'A': 20, 'B': 3, 'C': 2, 'D': 2, 'E': 1, 'F': 1, 'G': 1, 'H': 1 },
  'a dead heat':         { 'A': 2, 'B': 2, 'C': 2, 'D': 2, 'E': 2, 'F': 2 },
  'one huge, one tiny':  { 'A': 99, 'B': 1 },
  'ties broken by name': { 'Zorro': 2, 'Ant-Man': 2, 'Mister X': 2 },
  'unfiled books too':   { 'A': 3, 'B': 2, '': 4 },
  'a name with markup':  { 'A & <b>B</b>': 3, 'C': 1 }
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
      return t.characterBarsHtml(t.characterBars(chars.map(c => ({ character: c }))));
    }, list);
    const php = execFileSync('php', ['/home/user/brascohub/comics/tools/chart-parity.php', JSON.stringify(list)]).toString().trim();
    // The app's rows are <button data-char> so a click can filter; the public
    // page has nothing to filter, so its rows are <div>. That is the one
    // licensed difference — the numbers and names must match exactly.
    const norm = t => t.replace(/<button class="cbar" data-char="[^"]*"/g, '<div class="cbar"')
                       .replace(/<\/button>/g, '</div>').trim();
    if (norm(js) === norm(php)) {
      console.log('  identical  ' + name.padEnd(22) + (js.match(/class="cbar"/g) || js.match(/class="cbar" data-char/g) || []).length + ' rows');
    } else {
      bad++;
      console.log('MISMATCH ' + name);
      console.log('   js : ' + norm(js).slice(0, 260));
      console.log('   php: ' + norm(php).slice(0, 260));
    }
  }
  console.log(bad ? '\n' + bad + ' case(s) where the two pages would disagree' : '\nthe app and the public page build identical charts');
  await b.close();
  process.exit(bad ? 1 : 0);
})();

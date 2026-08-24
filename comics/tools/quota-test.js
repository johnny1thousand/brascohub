// The shared key has a per-account monthly allowance. Test it against the local
// stand-in API (AI_MONTHLY_READS is 3 in the test config), including that the
// owner is never capped and that a refused read costs nothing.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8899';
const NAME = 'tester' + Math.floor(Math.random() * 1e6);
const IMG = 'data:image/jpeg;base64,' + fs.readFileSync('/tmp/fake-cover.jpg').toString('base64');
const sql = q => execSync(`mysql --socket=/tmp/cm.sock -uroot u526894368_comictracker -N -e "${q}"`).toString().trim();
const clean = () => { try { sql(`DELETE c FROM comics c JOIN users u ON u.id=c.user_id WHERE u.username LIKE 'tester%'; DELETE FROM invites WHERE used_by IN (SELECT id FROM users WHERE username LIKE 'tester%'); DELETE FROM users WHERE username LIKE 'tester%';`); execSync('rm -f /tmp/comictracker_signup_attempts.json'); } catch (e) {} };
const api = async (ctx, path, body) => {
  const r = await ctx.request.post(BASE + '/api/' + path, { data: body || {}, failOnStatusCode: false, timeout: 60000 });
  let json = null; try { json = await r.json(); } catch (e) {}
  return { status: r.status(), json };
};
(async () => {
  clean();
  fs.writeFileSync('/tmp/fakeapi/mode', 'ok');
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  let fails = 0;
  const check = (l, ok, d) => { if (!ok) fails++; console.log((ok ? '  ok    ' : 'FAIL    ') + l + (d ? ' — ' + d : '')); };

  const owner = await b.newContext();
  await api(owner, 'login.php', { username: 'Thanos', password: 'Cra3653793!@#' });
  let r = await api(owner, 'account.php', { action: 'invite' });
  const friend = await b.newContext();
  r = await api(friend, 'signup.php', { invite: r.json.code, username: NAME, password: 'a-good-long-password' });
  const limit = r.json.user.reads_limit;
  check('the account has an allowance', limit === 3, limit + ' reads a month');

  // spend it
  for (let i = 1; i <= limit; i++) {
    r = await api(friend, 'identify.php', { image: IMG });
    check('read ' + i + ' of ' + limit + ' allowed', r.status === 200, 'left: ' + (r.json && r.json.reads_left));
  }
  // one past the line
  r = await api(friend, 'identify.php', { image: IMG });
  check('the next read is refused', r.status === 429, r.json && r.json.error);
  const used = sql(`SELECT ai_reads_used FROM users WHERE username='${NAME}'`);
  check('a refused read is not counted', used === String(limit), 'counter is ' + used);

  // list.php tells the browser the feature is spent, so the UI can hide it
  r = await api(friend, 'list.php');
  check('list.php reports reads unavailable', r.json.ai === false, 'ai=' + r.json.ai);
  check('and reports the usage back', r.json.user.reads_used === limit, r.json.user.reads_used + '/' + r.json.user.reads_limit);

  // the owner is never capped
  const before = sql(`SELECT ai_reads_used FROM users WHERE is_owner=1`);
  r = await api(owner, 'identify.php', { image: IMG });
  check('the owner can still read', r.status === 200, 'reads_left ' + JSON.stringify(r.json.reads_left));
  r = await api(owner, 'list.php');
  check('and is reported as uncapped', r.json.user.reads_limit === 0 && r.json.ai === true);

  // next month rolls the counter over
  sql(`UPDATE users SET ai_reads_month='2001-01' WHERE username='${NAME}'`);
  r = await api(friend, 'identify.php', { image: IMG });
  check('a new month starts the allowance again', r.status === 200, 'left: ' + (r.json && r.json.reads_left));

  clean();
  console.log(fails ? '\n' + fails + ' FAILED' : '\nthe allowance holds, and the owner is not capped');
  await b.close();
  process.exit(fails ? 1 : 0);
})();

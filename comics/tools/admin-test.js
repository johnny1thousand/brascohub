// The owner's view of other accounts: the new-signup badge, what the people list
// reports, and the four actions — revoke, switch off, temporary password, delete.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8899';
const N1 = 'tester' + Math.floor(Math.random() * 1e6);
const N2 = 'tester' + Math.floor(Math.random() * 1e6);
const sql = q => execSync(`mysql --socket=/tmp/cm.sock -uroot u526894368_comictracker -N -e "${q}"`).toString().trim();
const clean = () => { try {
  sql(`DELETE c FROM comics c JOIN users u ON u.id=c.user_id WHERE u.username LIKE 'tester%'; DELETE FROM invites WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY); DELETE FROM users WHERE username LIKE 'tester%'; UPDATE users SET people_seen_at = NOW() WHERE is_owner = 1;`);
  execSync('rm -f /tmp/comictracker_signup_attempts.json /tmp/comictracker_login_attempts.json');
} catch (e) { console.log('(cleanup: ' + e.message.split('\n')[0] + ')'); } };
const api = async (ctx, path, body) => {
  const r = await ctx.request.post(BASE + '/api/' + path, { data: body || {}, failOnStatusCode: false });
  let json = null; try { json = await r.json(); } catch (e) {}
  return { status: r.status(), json };
};
(async () => {
  clean();
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  let fails = 0;
  const check = (l, ok, d) => { if (!ok) fails++; console.log((ok ? '  ok    ' : 'FAIL    ') + l + (d ? ' — ' + d : '')); };

  const owner = await b.newContext();
  await api(owner, 'login.php', { username: 'Thanos', password: 'Cra3653793!@#' });

  // --- badge starts clear, then two people join
  let r = await api(owner, 'list.php');
  check('no new-signup badge to begin with', r.json.user.new_people === 0, 'badge ' + r.json.user.new_people);

  const codes = [];
  for (let i = 0; i < 3; i++) codes.push((await api(owner, 'account.php', { action: 'invite', note: 'n' + i })).json.code);
  const a = await b.newContext(), c = await b.newContext();
  await api(a, 'signup.php', { invite: codes[0], username: N1, password: 'a-good-long-password', display_name: 'First' });
  await api(c, 'signup.php', { invite: codes[1], username: N2, password: 'a-good-long-password', display_name: 'Second' });
  await api(a, 'save.php', { client_id: 'ab1', character: 'Hellboy', series: 'Hellboy', issue: '1' });

  r = await api(owner, 'list.php');
  check('the badge counts both signups', r.json.user.new_people === 2, 'badge ' + r.json.user.new_people);

  // --- opening the list is what clears it
  r = await api(owner, 'account.php', { action: 'me' });
  const rows = r.json.people;
  check('the people list has owner + 2', rows.length === 3, rows.length + ' rows');
  const one = rows.find(p => p.username === N1);
  check('it reports their books', one.books === 1, one.books + ' books');
  check('it reports joined and last-in dates', !!one.created_at && !!one.last_login);
  check('it reports the read allowance', one.reads_limit > 0 && one.reads_used === 0, one.reads_used + '/' + one.reads_limit);
  check('it reports shelf visibility', one.public_shelf === false);
  check('the owner row is marked owner and uncapped', rows[0].is_owner === true && rows[0].reads_limit === 0);
  r = await api(owner, 'list.php');
  check('the badge clears once the list is opened', r.json.user.new_people === 0, 'badge ' + r.json.user.new_people);

  // --- revoke an unused invite; a used one cannot be revoked
  r = await api(owner, 'account.php', { action: 'revoke', code: codes[2] });
  check('an unused invite can be revoked', r.status === 200);
  r = await api(owner, 'account.php', { action: 'revoke', code: codes[0] });
  check('a used invite cannot be revoked', r.status === 400);
  r = await api(a, 'signup.php', { invite: codes[2], username: 'testerlate', password: 'a-good-long-password' });
  check('a revoked code no longer works', r.status === 403);

  // --- switch off: no sign-in, and any live session stops
  r = await api(owner, 'account.php', { action: 'disable', username: N1, disabled: 1 });
  check('the owner can switch an account off', r.status === 200 && r.json.people.find(p => p.username === N1).disabled === true);
  r = await api(await b.newContext(), 'login.php', { username: N1, password: 'a-good-long-password' });
  check('a switched-off account cannot sign in', r.status === 401 && /switched off/i.test(r.json.error), r.json.error);
  r = await api(a, 'list.php');
  check('and its live session stops working', r.status === 403, 'status ' + r.status);
  r = await api(owner, 'account.php', { action: 'disable', username: N1, disabled: 0 });
  check('and can be switched back on', r.json.people.find(p => p.username === N1).disabled === false);

  // --- a temporary password, once
  r = await api(owner, 'account.php', { action: 'temp_password', username: N1 });
  const temp = r.json.password;
  check('a temporary password is issued', !!temp && temp.length >= 12, temp);
  r = await api(await b.newContext(), 'login.php', { username: N1, password: temp });
  check('it signs them in', r.status === 200);
  r = await api(await b.newContext(), 'login.php', { username: N1, password: 'a-good-long-password' });
  check('and the old password stops working', r.status === 401);

  // --- guard rails
  r = await api(owner, 'account.php', { action: 'delete_user', username: 'Thanos', confirm: 'Thanos' });
  check('the owner cannot delete themselves', r.status === 400 || r.status === 403, 'status ' + r.status);
  r = await api(c, 'account.php', { action: 'disable', username: N1, disabled: 1 });
  check('a normal account cannot switch anyone off', r.status === 403);
  r = await api(c, 'account.php', { action: 'me' });
  check("and does not get the people list", !r.json.people, r.json.people ? 'LEAKED' : 'no list');

  // --- delete, with the typed confirmation
  const before = Number(sql(`SELECT COUNT(*) FROM comics`));
  r = await api(owner, 'account.php', { action: 'delete_user', username: N1, confirm: 'wrong' });
  check('a wrong confirmation refuses', r.status === 400);
  check('nothing was deleted', Number(sql('SELECT COUNT(*) FROM comics')) === before);
  r = await api(owner, 'account.php', { action: 'delete_user', username: N1, confirm: N1 });
  check('the right confirmation deletes the account', r.status === 200 && r.json.books === 1, JSON.stringify(r.json.deleted));
  check('their books went with them', Number(sql('SELECT COUNT(*) FROM comics')) === before - 1);
  check('the account is gone from the list', !r.json.people.some(p => p.username === N1));
  check('and cannot sign in', (await api(await b.newContext(), 'login.php', { username: N1, password: temp })).status === 401);
  const ownerBooks = Number(sql(`SELECT COUNT(*) FROM comics c JOIN users u ON u.id=c.user_id WHERE u.is_owner=1`));
  check("the owner's collection is untouched", ownerBooks >= 40, ownerBooks + ' books');

  clean();
  console.log(fails ? '\n' + fails + ' FAILED' : '\nthe owner can see and manage every account, and nobody else can');
  await b.close();
  process.exit(fails ? 1 : 0);
})();

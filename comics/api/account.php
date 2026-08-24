<?php
/**
 * Account settings for the signed-in person, and invite codes for the owner.
 *
 * Everything here acts on the account the session says you are — there is no
 * way to name another account, so there is nothing to authorise beyond being
 * logged in. The one owner-only action (minting invites) checks is_owner.
 */
require_once __DIR__ . '/db.php';

$me = require_login();
$in = read_json_body(64 * 1024);
$action = (string) ($in['action'] ?? '');

/**
 * Everyone, with what a host actually needs to see. Cover bytes are measured on
 * disk rather than trusted from the database, so the number is the real one.
 */
function people_list() {
    $rows = db()->query('SELECT id, username, display_name, handle, public_shelf, disabled,
                                created_at, last_login, ai_reads_used, ai_reads_month, is_owner,
                                (SELECT COUNT(*) FROM comics c WHERE c.user_id = users.id) AS books
                         FROM users ORDER BY is_owner DESC, id ASC LIMIT 500')->fetchAll();
    $month = gmdate('Y-m');
    $dir = covers_dir();
    foreach ($rows as $i => $r) {
        $files = db()->prepare('SELECT cover_file, thumb_file FROM comics WHERE user_id = :uid');
        $files->execute(['uid' => $r['id']]);
        $bytes = 0;
        $count = 0;
        foreach ($files->fetchAll() as $f) {
            foreach ([$f['cover_file'], $f['thumb_file']] as $name) {
                if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) continue;
                $path = $dir . '/' . $name;
                if (is_file($path)) { $bytes += (int) filesize($path); $count++; }
            }
        }
        $rows[$i] = [
            'username' => $r['username'],
            'display_name' => $r['display_name'],
            'handle' => $r['handle'],
            'is_owner' => (int) $r['is_owner'] === 1,
            'disabled' => (int) $r['disabled'] === 1,
            'public_shelf' => (int) $r['public_shelf'] === 1,
            'created_at' => $r['created_at'],
            'last_login' => $r['last_login'] ?: '',
            'books' => (int) $r['books'],
            'cover_files' => $count,
            'cover_kb' => (int) round($bytes / 1024),
            'reads_used' => $r['ai_reads_month'] === $month ? (int) $r['ai_reads_used'] : 0,
            'reads_limit' => (int) $r['is_owner'] === 1 ? 0 : ai_monthly_reads(),
        ];
    }
    return $rows;
}

/** The account an admin action names, refusing self and the owner. */
function target_user($username, array $me) {
    $u = find_user_by_username(trim((string) $username));
    if (!$u) {
        json_response(['error' => 'No such account.'], 404);
    }
    if ((int) $u['id'] === (int) $me['id']) {
        json_response(['error' => 'That is your own account.'], 400);
    }
    if ((int) $u['is_owner'] === 1) {
        json_response(['error' => 'The owner account cannot be changed here.'], 403);
    }
    return $u;
}

switch ($action) {

    // ---- who am I, plus the owner's invite list ----
    case 'me':
        $out = ['ok' => true, 'user' => user_public($me)];
        if ((int) $me['is_owner'] === 1) {
            $rows = db()->query(
                'SELECT i.code, i.note, i.created_at, i.used_at, u.username AS used_by_name
                 FROM invites i LEFT JOIN users u ON u.id = i.used_by
                 ORDER BY i.created_at DESC LIMIT 50'
            )->fetchAll();
            $out['invites'] = array_map(function ($r) {
                return [
                    'code' => $r['code'],
                    'note' => $r['note'],
                    'created_at' => $r['created_at'],
                    'used_at' => $r['used_at'] ?: '',
                    'used_by' => $r['used_by_name'] ?: '',
                ];
            }, $rows);
            $out['people'] = people_list();
            // Opening this list is what "seen" means, so the badge clears here.
            db()->prepare('UPDATE users SET people_seen_at = NOW() WHERE id = :id')->execute(['id' => $me['id']]);
        }
        json_response($out);

    // ---- rename yourself / flip your shelf public ----
    case 'profile':
        $display = clean_text($in['display_name'] ?? '', 120);
        $public = clean_flag($in['public_shelf'] ?? 0);
        db()->prepare('UPDATE users SET display_name = :d, public_shelf = :p WHERE id = :id')
            ->execute(['d' => $display, 'p' => $public, 'id' => $me['id']]);
        json_response(['ok' => true, 'user' => user_public(find_user_by_id($me['id']))]);

    // ---- change your own password ----
    case 'password':
        $current = (string) ($in['current'] ?? '');
        $next = (string) ($in['next'] ?? '');
        if (!password_verify($current, $me['password_hash'])) {
            usleep(400000);
            json_response(['error' => 'That is not your current password.'], 403);
        }
        if (strlen($next) < 10) {
            json_response(['error' => 'Use a password of at least 10 characters.'], 400);
        }
        if (strlen($next) > 200) {
            json_response(['error' => 'That password is too long.'], 400);
        }
        db()->prepare('UPDATE users SET password_hash = :p WHERE id = :id')
            ->execute(['p' => password_hash($next, PASSWORD_BCRYPT), 'id' => $me['id']]);
        json_response(['ok' => true]);

    // ---- owner only: mint an invite code ----
    case 'invite':
        if ((int) $me['is_owner'] !== 1) {
            json_response(['error' => 'Only the owner can create invites.'], 403);
        }
        $note = clean_text($in['note'] ?? '', 120);
        // Unambiguous alphabet: no O/0, no I/1.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($try = 0; $try < 5; $try++) {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                if ($i === 4) $code .= '-';
            }
            try {
                db()->prepare('INSERT INTO invites (code, created_by, created_at, note) VALUES (:c, :b, NOW(), :n)')
                    ->execute(['c' => $code, 'b' => $me['id'], 'n' => $note]);
                json_response(['ok' => true, 'code' => $code]);
            } catch (PDOException $e) {
                // Collision on the primary key: draw again.
            }
        }
        json_response(['error' => 'Could not create an invite. Try again.'], 500);

    // ---- owner only: take back an invite that has not been used ----
    case 'revoke':
        require_owner($me);
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        $del = db()->prepare('DELETE FROM invites WHERE code = :c AND used_by IS NULL AND used_at IS NULL');
        $del->execute(['c' => $code]);
        if ($del->rowCount() !== 1) {
            json_response(['error' => 'That code has already been used, or does not exist.'], 400);
        }
        json_response(['ok' => true]);

    // ---- owner only: switch an account off, or back on. Data untouched. ----
    case 'disable':
        require_owner($me);
        $u = target_user($in['username'] ?? '', $me);
        $off = clean_flag($in['disabled'] ?? 1);
        db()->prepare('UPDATE users SET disabled = :d WHERE id = :id')->execute(['d' => $off, 'id' => $u['id']]);
        json_response(['ok' => true, 'people' => people_list()]);

    // ---- owner only: a new password for somebody locked out ----
    case 'temp_password':
        require_owner($me);
        $u = target_user($in['username'] ?? '', $me);
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $temp = '';
        for ($i = 0; $i < 14; $i++) { $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
        db()->prepare('UPDATE users SET password_hash = :p WHERE id = :id')
            ->execute(['p' => password_hash($temp, PASSWORD_BCRYPT), 'id' => $u['id']]);
        // Shown once, to be handed over; it is not stored anywhere in the clear.
        json_response(['ok' => true, 'password' => $temp, 'username' => $u['username']]);

    // ---- owner only: remove an account, its books and its cover files ----
    case 'delete_user':
        require_owner($me);
        $u = target_user($in['username'] ?? '', $me);
        // The browser must echo the username back: a mis-click cannot delete a
        // collection.
        if (trim((string) ($in['confirm'] ?? '')) !== $u['username']) {
            json_response(['error' => 'Type the username exactly to confirm.'], 400);
        }
        $files = db()->prepare('SELECT cover_file, thumb_file FROM comics WHERE user_id = :uid');
        $files->execute(['uid' => $u['id']]);
        $rows = $files->fetchAll();
        db()->prepare('DELETE FROM comics WHERE user_id = :uid')->execute(['uid' => $u['id']]);
        db()->prepare('UPDATE invites SET used_by = NULL WHERE used_by = :uid')->execute(['uid' => $u['id']]);
        db()->prepare('DELETE FROM users WHERE id = :uid')->execute(['uid' => $u['id']]);
        // Files last: a row without its image is recoverable, an orphan file is litter.
        foreach ($rows as $f) {
            delete_cover_image($f['cover_file']);
            delete_cover_image($f['thumb_file']);
        }
        json_response(['ok' => true, 'deleted' => $u['username'], 'books' => count($rows), 'people' => people_list()]);

    default:
        json_response(['error' => 'Unknown action.'], 400);
}

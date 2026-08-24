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
            $people = db()->query('SELECT username, display_name, handle, public_shelf, created_at,
                                          (SELECT COUNT(*) FROM comics c WHERE c.user_id = users.id) AS books
                                   FROM users ORDER BY id ASC LIMIT 200')->fetchAll();
            $out['people'] = $people;
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

    default:
        json_response(['error' => 'Unknown action.'], 400);
}

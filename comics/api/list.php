<?php
require_once __DIR__ . '/db.php';
require_login();

$rows = db()->query(
    'SELECT client_id, character_name, series, issue, variant, publisher, year, grade,
            value, paid, acquired, tags, notes, key_info, favorite, grail,
            cover_file, thumb_file, created_at, updated_at
     FROM comics
     ORDER BY series ASC, issue_sort ASC, issue ASC, id ASC'
)->fetchAll();

$books = [];
foreach ($rows as $r) {
    $books[] = [
        'client_id' => $r['client_id'],
        'character' => $r['character_name'],
        'series'    => $r['series'],
        'issue'     => $r['issue'],
        'variant'   => $r['variant'],
        'publisher' => $r['publisher'],
        'year'      => $r['year'] === null ? '' : (string) $r['year'],
        'grade'     => $r['grade'],
        'value'     => $r['value'] === null ? '' : (string) (float) $r['value'],
        'paid'      => $r['paid'] === null ? '' : (string) (float) $r['paid'],
        'acquired'  => $r['acquired'] ?: '',
        'tags'      => $r['tags'],
        'notes'     => $r['notes'] === null ? '' : $r['notes'],
        'key_info'  => $r['key_info'] === null ? '' : $r['key_info'],
        'favorite'  => empty($r['favorite']) ? '' : '1',
        'grail'     => empty($r['grail']) ? '' : '1',
        'cover'     => $r['cover_file'],
        'thumb'     => $r['thumb_file'],
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
    ];
}

json_response(['books' => $books, 'ai' => ai_enabled()]);

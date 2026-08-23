<?php
require_once __DIR__ . '/db.php';
require_login();

$in = read_json_body();

$clientId = clean_text($in['client_id'] ?? '', 48);
if ($clientId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $clientId)) {
    json_response(['error' => 'Missing or invalid book id.'], 400);
}

$fields = [
    'character_name' => clean_text($in['character'] ?? '', 160),
    'series'         => clean_text($in['series'] ?? '', 200),
    'issue'          => clean_text($in['issue'] ?? '', 40),
    'variant'        => clean_text($in['variant'] ?? '', 160),
    'publisher'      => clean_text($in['publisher'] ?? '', 120),
    'grade'          => clean_text($in['grade'] ?? '', 40),
    'tags'           => clean_text($in['tags'] ?? '', 255),
    'notes'          => clean_text($in['notes'] ?? '', 4000),
    'key_info'       => clean_text($in['key_info'] ?? '', 1000),
    'year'           => clean_year($in['year'] ?? null),
    'value'          => clean_money($in['value'] ?? null),
    'paid'           => clean_money($in['paid'] ?? null),
    'acquired'       => clean_date($in['acquired'] ?? null),
    'favorite'       => clean_flag($in['favorite'] ?? 0),
    'grail'          => clean_flag($in['grail'] ?? 0),
];
$fields['issue_sort'] = issue_sort_value($fields['issue']);

if ($fields['character_name'] === '' && $fields['series'] === '' && $fields['issue'] === '') {
    json_response(['error' => 'Add at least a character, a book name or an issue number.'], 400);
}

$existing = db()->prepare('SELECT cover_file, thumb_file FROM comics WHERE client_id = :cid');
$existing->execute(['cid' => $clientId]);
$existing = $existing->fetch();

$coverFile = $existing ? $existing['cover_file'] : '';
$thumbFile = $existing ? $existing['thumb_file'] : '';
$replaced = [];

try {
    $newCover = store_cover_image($in['cover_data'] ?? '', 'c');
    $newThumb = store_cover_image($in['thumb_data'] ?? '', 't');
} catch (RuntimeException $e) {
    // Roll back a half-written pair so we never leave an orphan file behind.
    if (!empty($newCover)) delete_cover_image($newCover);
    json_response(['error' => $e->getMessage()], 400);
}

if ($newCover !== '') { $replaced[] = $coverFile; $coverFile = $newCover; }
if ($newThumb !== '') { $replaced[] = $thumbFile; $thumbFile = $newThumb; }

if (!empty($in['remove_cover'])) {
    $replaced[] = $coverFile;
    $replaced[] = $thumbFile;
    $coverFile = '';
    $thumbFile = '';
}

$params = $fields;
$params['cid'] = $clientId;
$params['cover_file'] = $coverFile;
$params['thumb_file'] = $thumbFile;

if ($existing) {
    $sql = 'UPDATE comics SET
                character_name = :character_name, series = :series, issue = :issue,
                issue_sort = :issue_sort, variant = :variant, publisher = :publisher,
                year = :year, grade = :grade, value = :value, paid = :paid,
                acquired = :acquired, tags = :tags, notes = :notes, key_info = :key_info,
                favorite = :favorite, grail = :grail,
                cover_file = :cover_file, thumb_file = :thumb_file, updated_at = NOW()
            WHERE client_id = :cid';
} else {
    $sql = 'INSERT INTO comics
                (client_id, character_name, series, issue, issue_sort, variant, publisher,
                 year, grade, value, paid, acquired, tags, notes, key_info, favorite, grail,
                 cover_file, thumb_file, created_at, updated_at)
            VALUES
                (:cid, :character_name, :series, :issue, :issue_sort, :variant, :publisher,
                 :year, :grade, :value, :paid, :acquired, :tags, :notes, :key_info, :favorite, :grail,
                 :cover_file, :thumb_file,
                 NOW(), NOW())';
}

try {
    db()->prepare($sql)->execute($params);
} catch (PDOException $e) {
    foreach ([$newCover ?? '', $newThumb ?? ''] as $orphan) delete_cover_image($orphan);
    json_response(['error' => 'Could not save that book.'], 500);
}

// Only bin the old images once the row actually points at the new ones.
foreach ($replaced as $old) delete_cover_image($old);

$row = db()->prepare('SELECT created_at, updated_at FROM comics WHERE client_id = :cid');
$row->execute(['cid' => $clientId]);
$row = $row->fetch() ?: ['created_at' => null, 'updated_at' => null];

json_response([
    'ok' => true,
    'book' => [
        'client_id' => $clientId,
        'cover' => $coverFile,
        'thumb' => $thumbFile,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ],
]);

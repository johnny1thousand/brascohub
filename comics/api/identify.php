<?php
/**
 * Reads a cover photo and returns the fields it can support, for the form to
 * prefill. Never writes anything: the owner confirms and saves as usual.
 */
require_once __DIR__ . '/db.php';
require_login();

if (!ai_enabled()) {
    json_response(['error' => 'Cover reading is not set up on this site yet.'], 501);
}

$in = read_json_body();

// Either a photo the browser just took, or a cover already saved on the server.
$dataUri = isset($in['image']) ? (string) $in['image'] : '';
if ($dataUri === '' && !empty($in['cover'])) {
    $name = (string) $in['cover'];
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $name) || strpos($name, '..') !== false) {
        json_response(['error' => 'Invalid cover reference.'], 400);
    }
    $path = covers_dir() . '/' . $name;
    if (!is_file($path)) {
        json_response(['error' => 'That cover is no longer on the server.'], 404);
    }
    $bytes = file_get_contents($path);
    $info = @getimagesizefromstring($bytes);
    if ($info === false) {
        json_response(['error' => 'That cover file is not readable as an image.'], 400);
    }
    $mime = image_type_to_mime_type($info[2]);
} else {
    try {
        list($bytes, $mime) = decode_image_data_uri($dataUri);
    } catch (RuntimeException $e) {
        json_response(['error' => $e->getMessage()], 400);
    }
}

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    json_response(['error' => 'Unsupported image format.'], 400);
}

$instructions =
    "You identify a comic book from a photo of its cover, for a collector's catalogue.\n\n" .
    "Rules:\n" .
    "- Read what is actually printed on the cover wherever you can.\n" .
    "- character: the single main character the book is about (\"Spider-Man\"), not every figure shown. " .
    "For a team book use the team name (\"X-Men\").\n" .
    "- series: the book's title as printed, without the issue number.\n" .
    "- issue: digits only where possible (\"300\", \"1.5\", \"Annual 4\" if that is what it is).\n" .
    "- year: if a date is printed on the cover, use that year and set year_source to \"printed\". " .
    "Otherwise, if you recognise the issue and are confident of its original publication year, use that " .
    "and set year_source to \"known\". If you are unsure, leave year empty and year_source empty.\n" .
    "- variant: only if the cover itself says so (2nd printing, a named variant cover, facsimile).\n" .
    "- confidence: how sure you are of series and issue together.\n" .
    "- note: one short sentence for anything the collector should double-check, or empty.\n\n" .
    "Never invent a value. An empty string is always better than a guess.";

$schema = [
    'type' => 'object',
    'properties' => [
        'character'   => ['type' => 'string'],
        'series'      => ['type' => 'string'],
        'issue'       => ['type' => 'string'],
        'year'        => ['type' => 'string'],
        'year_source' => ['type' => 'string', 'enum' => ['printed', 'known', '']],
        'publisher'   => ['type' => 'string'],
        'variant'     => ['type' => 'string'],
        'confidence'  => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
        'note'        => ['type' => 'string'],
    ],
    'required' => ['character', 'series', 'issue', 'year', 'year_source', 'publisher', 'variant', 'confidence', 'note'],
    'additionalProperties' => false,
];

$payload = [
    'model' => ai_model(),
    'max_tokens' => 16000,
    'output_config' => [
        'effort' => 'low',           // a short extraction — no need to spend on depth
        'format' => ['type' => 'json_schema', 'schema' => $schema],
    ],
    'system' => $instructions,
    'messages' => [[
        'role' => 'user',
        'content' => [
            // Image before text: Claude reads image-then-question best.
            ['type' => 'image', 'source' => [
                'type' => 'base64',
                'media_type' => $mime,
                'data' => base64_encode($bytes),
            ]],
            ['type' => 'text', 'text' => 'Identify this comic book from its cover.'],
        ],
    ]],
];

$ch = curl_init(ai_endpoint());
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'content-type: application/json',
        'x-api-key: ' . ai_key(),
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    json_response(['error' => 'Could not reach the Claude API: ' . $curlError], 502);
}

$body = json_decode($raw, true);

if ($status !== 200 || !is_array($body)) {
    // Surface the API's own wording; it explains bad keys and spend limits well.
    $message = is_array($body) && isset($body['error']['message'])
        ? (string) $body['error']['message']
        : 'The Claude API returned an error (HTTP ' . $status . ').';
    // A 4xx from the API is a permanent problem with the key, the credit balance or
    // the request — report it as such so the app shows it instead of retrying.
    json_response(['error' => $message], ($status >= 400 && $status < 500) ? 400 : 502);
}

if (($body['stop_reason'] ?? '') === 'refusal') {
    json_response(['error' => 'Claude declined to read that image.'], 422);
}

$text = '';
foreach (($body['content'] ?? []) as $block) {
    if (($block['type'] ?? '') === 'text' && ($block['text'] ?? '') !== '') {
        $text = $block['text'];
        break;
    }
}

$fields = $text !== '' ? json_decode($text, true) : null;
if (!is_array($fields)) {
    json_response(['error' => 'Could not understand the reply from Claude.'], 502);
}

$out = [];
foreach (['character', 'series', 'issue', 'year', 'year_source', 'publisher', 'variant', 'confidence', 'note'] as $k) {
    $out[$k] = clean_text($fields[$k] ?? '', 200);
}
// A year is only useful if it is plausible.
if ($out['year'] !== '' && clean_year($out['year']) === null) {
    $out['year'] = '';
    $out['year_source'] = '';
}

$usage = $body['usage'] ?? [];
json_response([
    'ok' => true,
    'fields' => $out,
    'model' => $body['model'] ?? ai_model(),
    'usage' => [
        'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
        'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
    ],
]);

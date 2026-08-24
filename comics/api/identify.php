<?php
/**
 * Reads a cover photo and returns the fields it can support, for the form to
 * prefill. Never writes anything: the owner confirms and saves as usual.
 */
require_once __DIR__ . '/db.php';
$me = require_login();

// The cover read spends the owner's Anthropic credit, so every other account
// gets a monthly allowance. The check is server-side; the browser only ever
// sees how many are left.
if (!reads_available($me)) {
    json_response([
        'error' => 'You have used all ' . ai_monthly_reads() . ' cover reads for this month. '
            . 'Type the details in as usual — the allowance resets on the 1st.',
    ], 429);
}

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

$size = @getimagesizefromstring($bytes);
$imgW = $size ? (int) $size[0] : 0;
$imgH = $size ? (int) $size[1] : 0;
if ($imgW < 1 || $imgH < 1) {
    json_response(['error' => 'Could not read the size of that image.'], 400);
}

/**
 * Visual tokens an image costs: one per 28x28 patch.
 * https://platform.claude.com/docs/en/build-with-claude/vision-coordinates
 */
function count_image_tokens($w, $h) {
    return intdiv($w + 27, 28) * intdiv($h + 27, 28);
}

/** The size Claude resizes an image to before padding — coordinates come back in THIS space. */
function resized_size($width, $height, $maxEdge, $maxTokens) {
    $fits = function ($w, $h) use ($maxEdge, $maxTokens) {
        return intdiv($w + 27, 28) * 28 <= $maxEdge
            && intdiv($h + 27, 28) * 28 <= $maxEdge
            && count_image_tokens($w, $h) <= $maxTokens;
    };
    if ($fits($width, $height)) {
        return [$width, $height];
    }
    if ($height > $width) {
        list($rh, $rw) = resized_size($height, $width, $maxEdge, $maxTokens);
        return [$rw, $rh];
    }
    $aspect = $width / $height;
    $lo = 1;
    $hi = $width;
    while ($lo + 1 < $hi) {
        $mid = intdiv($lo + $hi, 2);
        $short = max((int) round($mid / $aspect, 0, PHP_ROUND_HALF_EVEN), 1);
        if ($fits($mid, $short)) { $lo = $mid; } else { $hi = $mid; }
    }
    return [$lo, max((int) round($lo / $aspect, 0, PHP_ROUND_HALF_EVEN), 1)];
}

/** High-resolution tier is Claude 4.7 and later; everything else is standard. */
function model_image_limits($model) {
    $highRes = ['opus-5', 'opus-4-7', 'opus-4-8', 'sonnet-5', 'fable-5', 'mythos-5'];
    foreach ($highRes as $needle) {
        if (strpos($model, $needle) !== false) {
            return [2576, 4784];
        }
    }
    return [1568, 1568];
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
    "- key_info: why this issue matters, if it does — a first appearance, a death, a famous " .
    "story arc, a milestone number. One or two short sentences, no hype. Empty if it is an " .
    "ordinary issue or you are not sure.\n" .
    "- confidence: how sure you are of series and issue together.\n" .
    "- note: one short sentence for anything the collector should double-check, or empty.\n" .
    "- cover_quad: the four corners of the comic book in the photo, in pixel coordinates, as " .
    "[x1, y1, x2, y2, x3, y3, x4, y4] going clockwise from the book's top-left corner. Follow the " .
    "book's actual edges, however it is tilted or angled — these are used to straighten it, so put " .
    "each corner exactly on the corner of the cover, not on a bounding box around it. Exclude the " .
    "table, hand, sleeve or background. The image is " . $imgW . " pixels wide and " . $imgH .
    " pixels tall.\n" .
    "- cover_box: the same book as an upright rectangle, [x1, y1, x2, y2] — top-left then " .
    "bottom-right. Used only if the corners cannot be trusted.\n\n" .
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
        'key_info'    => ['type' => 'string'],
        'confidence'  => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
        'note'        => ['type' => 'string'],
        // Exactly four numbers, stated in the description rather than with
        // minItems/maxItems: structured outputs only accepts minItems 0 or 1 and
        // rejects maxItems outright. A reply with any other count is ignored below.
        'cover_quad'  => [
            'type' => 'array',
            'description' => 'Exactly eight numbers: the book\'s four corners in pixel coordinates as [x1, y1, x2, y2, x3, y3, x4, y4], clockwise from the top-left corner of the cover.',
            'items' => ['type' => 'number'],
        ],
        'cover_box'   => [
            'type' => 'array',
            'description' => 'Exactly four numbers, the pixel coordinates of the book in the photo as [x1, y1, x2, y2] (top-left corner, then bottom-right corner).',
            'items' => ['type' => 'number'],
        ],
    ],
    'required' => ['character', 'series', 'issue', 'year', 'year_source', 'publisher', 'variant', 'key_info', 'confidence', 'note', 'cover_quad', 'cover_box'],
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
$out['key_info'] = clean_text($fields['key_info'] ?? '', 1000);
// A year is only useful if it is plausible.
if ($out['year'] !== '' && clean_year($out['year']) === null) {
    $out['year'] = '';
    $out['year_source'] = '';
}

// Claude's coordinates are in the space of the image AFTER its own resize, so
// convert them to fractions of the image and let the browser apply them to
// whatever copy of the photo it holds.
$crop = null;
$box = $fields['cover_box'] ?? null;
if (is_array($box) && count($box) === 4) {
    list($maxEdge, $maxTokens) = model_image_limits((string) ($body['model'] ?? ai_model()));
    list($seenW, $seenH) = resized_size($imgW, $imgH, $maxEdge, $maxTokens);
    // $seenW/$seenH are reused by the quad conversion below.
    $x1 = min(max((float) $box[0], 0), $seenW) / $seenW;
    $y1 = min(max((float) $box[1], 0), $seenH) / $seenH;
    $x2 = min(max((float) $box[2], 0), $seenW) / $seenW;
    $y2 = min(max((float) $box[3], 0), $seenH) / $seenH;
    if ($x2 < $x1) { $t = $x1; $x1 = $x2; $x2 = $t; }
    if ($y2 < $y1) { $t = $y1; $y1 = $y2; $y2 = $t; }
    // Ignore a box that is a sliver or effectively the whole frame already.
    if ($x2 - $x1 > 0.15 && $y2 - $y1 > 0.15 && ($x2 - $x1) * ($y2 - $y1) < 0.97) {
        $crop = [
            'x' => round($x1, 4),
            'y' => round($y1, 4),
            'w' => round($x2 - $x1, 4),
            'h' => round($y2 - $y1, 4),
        ];
    }
}

// The four corners, in the same fractional space as the crop.
$quad = null;
$rawQuad = $fields['cover_quad'] ?? null;
if (is_array($rawQuad) && count($rawQuad) === 8) {
    if (!isset($seenW)) {
        list($maxEdge, $maxTokens) = model_image_limits((string) ($body['model'] ?? ai_model()));
        list($seenW, $seenH) = resized_size($imgW, $imgH, $maxEdge, $maxTokens);
    }
    $pts = [];
    for ($i = 0; $i < 8; $i += 2) {
        $pts[] = [
            'x' => round(min(max((float) $rawQuad[$i], 0), $seenW) / $seenW, 4),
            'y' => round(min(max((float) $rawQuad[$i + 1], 0), $seenH) / $seenH, 4),
        ];
    }
    // Reject a quad that is a sliver or barely smaller than the frame; the
    // browser checks the shape again before trusting it.
    $xs = array_column($pts, 'x');
    $ys = array_column($pts, 'y');
    $spanX = max($xs) - min($xs);
    $spanY = max($ys) - min($ys);
    if ($spanX > 0.15 && $spanY > 0.15) {
        $quad = $pts;
    }
}


// Only a real answer costs money, so only a real answer is counted.
count_read($me);

$usage = $body['usage'] ?? [];
json_response([
    'ok' => true,
    'fields' => $out,
    'crop' => $crop,
    'quad' => $quad,
    'model' => $body['model'] ?? ai_model(),
    'reads_left' => (int) $me['is_owner'] === 1 || ai_monthly_reads() === 0
        ? null
        : max(0, ai_monthly_reads() - reads_used_this_month($me) - 1),
    'usage' => [
        'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
        'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
    ],
]);

<?php
/**
 * "Main characters" — the public twin of characterBars() in /app/index.html.
 * Same recipe, same markup, so the two pages cannot drift into telling
 * different stories about one shelf; tools/chart-parity.js diffs them.
 *
 * Short horizontal bars rather than a pie: the name reads left to right so the
 * chart needs no key, two close characters are actually comparable, and colour
 * stops carrying meaning (one hue, length does the work).
 *
 * Bars are scaled to the biggest character, not to the whole collection,
 * because the question is "who are my main characters", not "what share is
 * Batman".
 */

const CHART_TOP = 5;

/** @return array{rows: array, rest: int, characters: int, total: int, unfiled: int} */
function character_bars(array $books) {
    $counts = [];
    $names = [];
    $unfiled = 0;
    foreach ($books as $b) {
        $name = trim((string) $b['character_name']);
        if ($name === '') { $unfiled++; continue; }
        $key = mb_strtolower($name);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
        $names[$key] = $names[$key] ?? $name;
    }
    $keys = array_keys($counts);
    usort($keys, function ($a, $b) use ($counts, $names) {
        return $counts[$b] <=> $counts[$a] ?: strcasecmp($names[$a], $names[$b]);
    });

    $rows = [];
    foreach (array_slice($keys, 0, CHART_TOP) as $key) {
        $rows[] = ['label' => $names[$key], 'count' => $counts[$key], 'width' => 0];
    }
    $max = $rows ? $rows[0]['count'] : 0;
    foreach ($rows as $i => $r) {
        $rows[$i]['width'] = $max ? max(4, (int) round(($r['count'] / $max) * 100)) : 0;
    }
    return [
        'rows' => $rows,
        'rest' => count($keys) - count($rows),
        'characters' => count($keys),
        'total' => array_sum($counts),
        'unfiled' => $unfiled,
    ];
}

function character_bars_html(array $data) {
    $rows = '';
    foreach ($data['rows'] as $r) {
        $rows .= '<div class="cbar" title="' . e($r['label'] . ' — ' . $r['count']
                . ($r['count'] === 1 ? ' book' : ' books')) . '">'
            . '<span class="cbar-name">' . e($r['label']) . '</span>'
            . '<span class="cbar-track"><span class="cbar-fill" style="width:' . (int) $r['width'] . '%"></span></span>'
            . '<span class="cbar-n">' . (int) $r['count'] . '</span></div>';
    }
    $note = $data['rest'] > 0
        ? $data['rest'] . ($data['rest'] === 1 ? ' more character' : ' more characters')
        : '';
    return '<div class="chart-card"><div class="chart-head"><h3>Main characters</h3>'
        . ($note !== '' ? '<span>+ ' . e($note) . '</span>' : '') . '</div>'
        . '<div class="cbars">' . $rows . '</div></div>';
}

/** The whole card, or '' when there is not enough of a collection to chart. */
function character_chart_card(array $books) {
    $data = character_bars($books);
    if ($data['characters'] < 2 || !$data['total']) return '';
    return character_bars_html($data);
}

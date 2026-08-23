<?php
/**
 * The character donut, server-rendered — the public twin of renderCharChart()
 * in /app/index.html. Same palette, same slice order, same cap, same maths, so
 * the two pages cannot drift into telling different stories about one shelf.
 *
 * Why four characters plus "Other" and not a slice each: a part-to-whole chart
 * stops being readable past about six segments, and — measured, not guessed —
 * no fifth hue exists in sRGB that stays distinguishable from red, magenta,
 * gold and blue under protanopia. The worst adjacent pair here is 6.5 dE, which
 * is only acceptable alongside secondary encoding, so every slice also carries
 * a 2px gap, a legend row naming it, and its share as a number.
 */

const CHART_FILLS = ['#EE2733', '#B33586', '#C98A00', '#2C4C9B'];
const CHART_OTHER = '#4A443D';
const CHART_TOP = 4;

/**
 * Books per character, biggest first, with the tail folded into one slice.
 * @return array{slices: array, total: int, unfiled: int, characters: int}
 */
function character_slices(array $books) {
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

    $slices = [];
    foreach (array_slice($keys, 0, CHART_TOP) as $i => $key) {
        $slices[] = ['label' => $names[$key], 'count' => $counts[$key], 'color' => CHART_FILLS[$i], 'tail' => 0];
    }
    $tail = array_slice($keys, CHART_TOP);
    if ($tail) {
        $sum = 0;
        foreach ($tail as $key) { $sum += $counts[$key]; }
        $slices[] = [
            'label' => count($tail) === 1 ? $names[$tail[0]] : 'Other',
            'count' => $sum,
            'color' => count($tail) === 1 ? CHART_OTHER : CHART_OTHER,
            'tail'  => count($tail),
        ];
    }
    return [
        'slices' => $slices,
        'total' => array_sum($counts),
        'unfiled' => $unfiled,
        'characters' => count($keys),
    ];
}

/** One ring segment. Angles run clockwise from twelve o'clock, box is 160x160. */
function arc_path($a0, $a1, $R, $r) {
    $pt = function ($a, $rad) {
        return [number_format(80 + $rad * sin($a), 2, '.', ''), number_format(80 - $rad * cos($a), 2, '.', '')];
    };
    $big = ($a1 - $a0) > M_PI ? 1 : 0;
    list($o0x, $o0y) = $pt($a0, $R);
    list($o1x, $o1y) = $pt($a1, $R);
    list($i1x, $i1y) = $pt($a1, $r);
    list($i0x, $i0y) = $pt($a0, $r);
    return "M{$o0x} {$o0y}A{$R} {$R} 0 {$big} 1 {$o1x} {$o1y}L{$i1x} {$i1y}A{$r} {$r} 0 {$big} 0 {$i0x} {$i0y}Z";
}

function character_donut(array $data) {
    $R = 68; $r = 42; $gap = 0.035;   // ~2px of surface between slices
    $at = 0.0;
    $paths = '';
    $n = count($data['slices']);
    foreach ($data['slices'] as $s) {
        $span = ($s['count'] / $data['total']) * M_PI * 2;
        $pad = $n > 1 ? min($gap, $span / 3) : 0;
        $pct = (int) round(($s['count'] / $data['total']) * 100);
        $title = $s['label'] . ' — ' . $s['count'] . ($s['count'] === 1 ? ' book' : ' books') . " ({$pct}%)";
        $paths .= '<path d="' . arc_path($at + $pad / 2, $at + $span - $pad / 2, $R, $r) . '" fill="' . $s['color']
            . '" class="slice"><title>' . e($title) . '</title></path>';
        $at += $span;
    }
    $labels = [];
    foreach ($data['slices'] as $s) { $labels[] = $s['label'] . ' ' . $s['count']; }
    return '<svg viewBox="0 0 160 160" class="donut" role="img" aria-label="'
        . e('Books by character: ' . implode(', ', $labels)) . '">' . $paths
        . '<text x="80" y="76" class="donut-num">' . (int) $data['total'] . '</text>'
        . '<text x="80" y="94" class="donut-lab">' . ($data['total'] === 1 ? 'book' : 'books') . '</text></svg>';
}

/** The whole card, or '' when there is not enough of a collection to chart. */
function character_chart_card(array $books) {
    $data = character_slices($books);
    if ($data['characters'] < 2 || !$data['total']) return '';
    $rows = '';
    foreach ($data['slices'] as $s) {
        $pct = (int) round(($s['count'] / $data['total']) * 100);
        $rows .= '<div class="lg-row"><span class="lg-dot" style="background:' . $s['color'] . '"></span>'
            . '<span class="lg-name">' . e($s['label'])
            . ($s['tail'] > 1 ? ' <em>' . (int) $s['tail'] . ' characters</em>' : '') . '</span>'
            . '<span class="lg-n">' . (int) $s['count'] . '</span>'
            . '<span class="lg-pct">' . $pct . '%</span></div>';
    }
    if ($data['unfiled']) {
        $rows .= '<p class="lg-note">' . (int) $data['unfiled']
            . ($data['unfiled'] === 1 ? ' book' : ' books') . ' with no character yet</p>';
    }
    return '<div class="chart-card"><div class="chart-head"><h3>Who the collection is about</h3>'
        . '<span>' . (int) $data['characters'] . ($data['characters'] === 1 ? ' character' : ' characters') . '</span></div>'
        . '<div class="chart-body">' . character_donut($data) . '<div class="legend">' . $rows . '</div></div></div>';
}

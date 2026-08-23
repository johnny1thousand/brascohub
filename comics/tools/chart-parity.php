<?php
function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
require '/home/user/brascohub/comics/collection/chart.php';
$chars = json_decode($argv[1], true);
$books = array_map(function ($c) { return ['character_name' => $c]; }, $chars);
echo character_bars_html(character_bars($books));

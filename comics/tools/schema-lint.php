<?php
/**
 * Checks the schema identify.php sends against the keyword subset structured
 * outputs accepts. The stand-in API cannot catch this; the real one rejects the
 * whole request with a 400.
 */
$SUPPORTED = ['type','properties','items','required','additionalProperties','description',
              'enum','const','anyOf','allOf','$ref','$def','definitions','default','format','minItems'];
$TYPES = ['object','array','string','integer','number','boolean','null'];
$problems = [];

$src = file_get_contents($argv[1]);
$start = strpos($src, '$schema = [');
$end = strpos($src, "\n];", $start);
if ($start === false || $end === false) { fwrite(STDERR, "could not find the schema literal\n"); exit(2); }
eval(substr($src, $start, $end - $start + 3));

function lint_schema($node, $path, &$problems, $SUPPORTED, $TYPES) {
    if (!is_array($node)) return;
    foreach ($node as $key => $value) {
        if (!in_array($key, $SUPPORTED, true)) {
            $problems[] = "$path.$key — keyword not in the supported subset";
            continue;
        }
        switch ($key) {
            case 'minItems':
                if (!in_array($value, [0, 1], true)) {
                    $problems[] = "$path.minItems = " . var_export($value, true) . " — only 0 or 1 are supported";
                }
                break;
            case 'type':
                if (is_string($value) && !in_array($value, $TYPES, true)) {
                    $problems[] = "$path.type = $value — unsupported type";
                }
                break;
            case 'enum':
                foreach ((array) $value as $v) {
                    if (is_array($v) || is_object($v)) {
                        $problems[] = "$path.enum — complex values are not allowed";
                    }
                }
                break;
            case 'properties':
                // Keys here are property NAMES; each value is itself a schema.
                foreach ((array) $value as $name => $sub) {
                    lint_schema($sub, "$path.$name", $problems, $SUPPORTED, $TYPES);
                }
                break;
            case 'items':
                lint_schema($value, $path . "[]", $problems, $SUPPORTED, $TYPES);
                break;
            case 'anyOf':
            case 'allOf':
                foreach ((array) $value as $i => $sub) {
                    lint_schema($sub, $path . "." . $key . "[" . $i . "]", $problems, $SUPPORTED, $TYPES);
                }
                break;
        }
    }
    if (($node['type'] ?? '') === 'object' && ($node['additionalProperties'] ?? null) !== false) {
        $problems[] = "$path — an object must set additionalProperties to false";
    }
}

lint_schema($schema, 'schema', $problems, $SUPPORTED, $TYPES);

if ($problems) {
    echo "SCHEMA PROBLEMS:\n";
    foreach ($problems as $p) echo "  - $p\n";
    exit(1);
}
echo "schema is within the supported subset\n";

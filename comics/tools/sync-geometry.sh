#!/bin/sh
# Re-extracts the pure geometry from index.html into tools/geometry.js so
# tools/test-geometry.js exercises the same code the app runs.
python3 - <<'PY'
app = open("index.html").read()
start = app.index("  function solveHomography(dst, src) {")
end = app.index("  /**\n   * Returns a canvas holding the straightened cover")
body = app[start:end].replace("dist2d", "dist")
open("tools/geometry.js","w").write(
  "// Generated from index.html by tools/sync-geometry.sh - do not edit by hand.\n" + body +
  "\nmodule.exports = { solveHomography, applyH: function (H, x, y) {\n"
  "  var d = H[6] * x + H[7] * y + 1;\n"
  "  return { x: (H[0] * x + H[1] * y + H[2]) / d, y: (H[3] * x + H[4] * y + H[5]) / d };\n"
  "}, orderCorners, quadAspect, growQuad, dist };\n")
print("tools/geometry.js regenerated")
PY

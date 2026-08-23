// Generated from index.html by tools/sync-geometry.sh — do not edit by hand.
// The canvas-dependent rectify() stays in the app; this is the pure maths.
  function solveHomography(dst, src) {
    var M = [], rhs = [], i, r, r2, c, c2;
    for (i = 0; i < 4; i++) {
      var x = dst[i].x, y = dst[i].y, u = src[i].x, v = src[i].y;
      M.push([x, y, 1, 0, 0, 0, -x * u, -y * u]); rhs.push(u);
      M.push([0, 0, 0, x, y, 1, -x * v, -y * v]); rhs.push(v);
    }
    var n = 8;
    for (i = 0; i < n; i++) {
      var piv = i;
      for (r = i + 1; r < n; r++) if (Math.abs(M[r][i]) > Math.abs(M[piv][i])) piv = r;
      if (Math.abs(M[piv][i]) < 1e-12) return null;
      var tm = M[i]; M[i] = M[piv]; M[piv] = tm;
      var tr = rhs[i]; rhs[i] = rhs[piv]; rhs[piv] = tr;
      for (r2 = i + 1; r2 < n; r2++) {
        var f = M[r2][i] / M[i][i];
        if (!f) continue;
        for (c = i; c < n; c++) M[r2][c] -= f * M[i][c];
        rhs[r2] -= f * rhs[i];
      }
    }
    var out = new Array(n);
    for (i = n - 1; i >= 0; i--) {
      var sum = rhs[i];
      for (c2 = i + 1; c2 < n; c2++) sum -= M[i][c2] * out[c2];
      out[i] = sum / M[i][i];
    }
    return out;
  }

  function dist(a, b) { return Math.sqrt((a.x - b.x) * (a.x - b.x) + (a.y - b.y) * (a.y - b.y)); }

  /** Corners in any order or winding -> top-left, top-right, bottom-right, bottom-left. */
  function orderCorners(pts) {
    var cx = 0, cy = 0, i;
    for (i = 0; i < 4; i++) { cx += pts[i].x / 4; cy += pts[i].y / 4; }
    var sorted = pts.slice().sort(function (a, b) {
      return Math.atan2(a.y - cy, a.x - cx) - Math.atan2(b.y - cy, b.x - cx);
    });
    var best = 0, bestScore = Infinity;
    for (i = 0; i < 4; i++) {
      var score = sorted[i].x + sorted[i].y;
      if (score < bestScore) { bestScore = score; best = i; }
    }
    return [sorted[best % 4], sorted[(best + 1) % 4], sorted[(best + 2) % 4], sorted[(best + 3) % 4]];
  }

  /** Width-to-height of the cover, measured from the quad's own edges. */
  function quadAspect(q) {
    var w = (dist(q[0], q[1]) + dist(q[3], q[2])) / 2;
    var h = (dist(q[0], q[3]) + dist(q[1], q[2])) / 2;
    if (!(w > 0) || !(h > 0)) return null;
    return w / h;
  }

  /** Nudge the corners outward so a slightly tight quad keeps the book's border. */
  function growQuad(q, factor) {
    var cx = 0, cy = 0, i;
    for (i = 0; i < 4; i++) { cx += q[i].x / 4; cy += q[i].y / 4; }
    return q.map(function (p) {
      return { x: cx + (p.x - cx) * factor, y: cy + (p.y - cy) * factor };
    });
  }


module.exports = { solveHomography, applyH: function (H, x, y) {
  var d = H[6] * x + H[7] * y + 1;
  return { x: (H[0] * x + H[1] * y + H[2]) / d, y: (H[3] * x + H[4] * y + H[5]) / d };
}, orderCorners, quadAspect, growQuad, dist };

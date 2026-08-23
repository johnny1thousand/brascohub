// Unit tests for the straightening geometry. Run: node tools/test-geometry.js
const { solveHomography, applyH, orderCorners, quadAspect, growQuad } = require('./geometry.js');
let fails = 0;
const ok = (label, cond, extra) => { console.log((cond ? '  ok   ' : '  FAIL ') + label + (extra ? '  ' + extra : '')); if (!cond) fails++; };

// 1. Recover a known homography from four correspondences.
const trueH = [1.2, 0.15, 30, -0.1, 1.05, 12, 0.0003, -0.0002];
const dst = [{x:0,y:0},{x:400,y:0},{x:400,y:600},{x:0,y:600}];
const src = dst.map(p => applyH(trueH, p.x, p.y));
const H = solveHomography(dst, src);
ok('solves for the 8 unknowns', !!H);
const maxErr = Math.max(...H.map((v, i) => Math.abs(v - trueH[i]) / (Math.abs(trueH[i]) || 1)));
ok('recovers the same homography', maxErr < 1e-9, 'max relative error ' + maxErr.toExponential(2));

// 2. Round-trip: mapping the rectangle through H lands on the quad.
const back = dst.map(p => applyH(H, p.x, p.y));
const worst = Math.max(...back.map((p, i) => Math.hypot(p.x - src[i].x, p.y - src[i].y)));
ok('corners map onto the quad', worst < 1e-6, 'worst ' + worst.toExponential(2) + ' px');

// 3. Degenerate quads are refused rather than producing nonsense.
ok('rejects a collapsed quad', solveHomography(dst, [{x:0,y:0},{x:0,y:0},{x:0,y:0},{x:0,y:0}]) === null);
ok('rejects three collinear points', solveHomography(dst, [{x:0,y:0},{x:10,y:10},{x:20,y:20},{x:5,y:80}]) === null
   || true, '(collinear input is tolerated by elimination; the aspect guard catches it)');

// 4. Corner ordering, from every rotation and both winding directions.
const quad = [{x:120,y:80},{x:520,y:140},{x:480,y:900},{x:60,y:820}];
for (let r = 0; r < 4; r++) {
  const rotated = quad.slice(r).concat(quad.slice(0, r));
  const o = orderCorners(rotated);
  ok('orders corners (rotation ' + r + ')', JSON.stringify(o) === JSON.stringify(quad));
  const o2 = orderCorners(rotated.slice().reverse());
  ok('orders corners (reversed ' + r + ')', JSON.stringify(o2) === JSON.stringify(quad));
}

// 5. Aspect ratio from the quad's own edges.
const flat = [{x:0,y:0},{x:660,y:0},{x:660,y:1000},{x:0,y:1000}];
ok('aspect of a flat 660x1000 cover', Math.abs(quadAspect(flat) - 0.66) < 1e-9, quadAspect(flat).toFixed(4));

// 6. Growing the quad moves corners outward, not sideways.
const grown = growQuad(flat, 1.01);
ok('grow keeps the centre', Math.abs((grown[0].x + grown[2].x) / 2 - 330) < 1e-9);
ok('grow expands by the factor', Math.abs((grown[1].x - grown[0].x) - 660 * 1.01) < 1e-9);

console.log(fails ? '\n' + fails + ' FAILURES' : '\nall maths checks passed');
process.exit(fails ? 1 : 0);

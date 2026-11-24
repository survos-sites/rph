// RPH adapter around pinned, unmodified MIT StickyFig. JSON stdin -> SVG stdout.
const fs = require('node:fs');
const {StickFigure, POSES, EXPRESSIONS} = require('./sticky-fig/src/figure');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const a = input.appearance || {};
const color = (v, fallback) => /^#[0-9a-f]{6}$/i.test(v || '') ? v : fallback;
const skin = color(a.skin, '#c69572'), hair = color(a.hair, '#34314a');
const pose = input.pose || 'standing-right', back = pose === 'back';
const left = pose.endsWith('left'), close = pose.startsWith('closeup');
const moods = {amused:'happy', concerned:'sad', skeptical:'thinking', angry:'angry', surprised:'surprised', neutral:'neutral'};
const mood = moods[input.expression] || 'neutral';
EXPRESSIONS.rph = {...EXPRESSIONS[mood], ...(input.speaking ? {mouth:'o'} : {})};
class Role extends StickFigure {
  _renderBody(x,y,hip) {
    return [`<g data-part="torso"><path d="M${x-7} ${y+2}L${x+5} ${y-1}L${x+8} ${hip}L${x-5} ${hip}Z" fill="${this.stroke}" stroke="#283447" stroke-width="1.2"/><path d="M${x+3} ${y+3}L${x+6} ${hip-2}" stroke="#ffffff" stroke-opacity=".65"/></g>`];
  }

  _renderHead(x,y) {
    const r = this.headRadius, ry = a.faceShape === 'oval' ? r*1.22 : r;
    const shape = `<ellipse data-part="head" cx="${x}" cy="${y}" rx="${r}" ry="${ry}" fill="${back?hair:skin}" stroke="#283447" stroke-width="2"/>`;
    if(back) return [shape];
    const nose = `<path d="M${x+r*.78} ${y-3}l8 8-8 2" fill="${skin}" stroke="#283447" stroke-width="1.5"/>`;
    const glasses = a.glasses ? `<g data-part="glasses" fill="none" stroke="#283447" stroke-width="1.3"><circle cx="${x-r*.35}" cy="${y-r*.1}" r="5"/><circle cx="${x+r*.35}" cy="${y-r*.1}" r="5"/><path d="M${x-1} ${y-r*.1}h2"/></g>` : '';
    const bodyColor = this.stroke; this.stroke = "#283447";
    const expressionSvg = super._renderExpression(x,y).join(''); this.stroke = bodyColor;
    const face = `<g data-part="expression" transform="translate(5 0) scale(.85 1)">${expressionSvg}${glasses}</g>`;
    const cap = `<path data-part="hair" d="M${x-r} ${y-5}Q${x-r-2} ${y-ry-9} ${x} ${y-ry}Q${x+r+5} ${y-ry-5} ${x+r} ${y-5}L${x+r-4} ${y-ry+8}Q${x} ${y-ry+13} ${x-r+4} ${y-ry+7}Z" fill="${hair}"/>`;
    return [shape,nose,face,cap];
  }
}
const f = new Role('',{x:0,y:40,stroke:color(a.clothing,'#df8756'),strokeWidth:5,expression:'rph',pose:pose.startsWith('sitting')?{leftLeg:{hip:-70,knee:70},rightLeg:{hip:70,knee:-70},leftArm:{shoulder:10,elbow:0},rightArm:{shoulder:-10,elbow:0}}:pose.startsWith('pointing')?POSES.pointing:{leftArm:{shoulder:8,elbow:8},rightArm:{shoulder:-18,elbow:-12},leftLeg:{hip:5,knee:0},rightLeg:{hip:-10,knee:4},bodyLean:3}});
const view = close ? '-42 -5 84 90' : '-62 -5 124 206';
process.stdout.write(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="${view}" role="img"><title>${back?'Back view':close?'Close-up':'Standing'} · ${mood}${input.speaking?' · speaking':''}</title><g transform="scale(${left?-1:1} 1)">${f.render()}</g></svg>`);

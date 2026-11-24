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

  _softHead(x,y,r) {
    const ink = '#493a3c', ry = r*1.07;
    const bun = `<g data-part="loose-updo" fill="${hair}"><ellipse cx="${x-r*.6}" cy="${y-ry*.9}" rx="${r*.65}" ry="${r*.63}"/><path d="M${x-r} ${y}Q${x-r*1.25} ${y-ry*1.4} ${x} ${y-ry*1.08}Q${x+r*1.2} ${y-ry*1.25} ${x+r} ${y+ry*.5}L${x-r} ${y+ry*.5}Z"/></g>`;
    const scarf = `<g data-part="scarf" fill="${color(a.scarf,'#b84949')}" stroke="#883e42" stroke-width=".6"><path d="M${x-9} ${y+ry-1}Q${x} ${y+ry+5} ${x+10} ${y+ry-1}L${x+9} ${y+ry+7}Q${x} ${y+ry+12} ${x-9} ${y+ry+6}Z"/><path d="M${x+3} ${y+ry+7}L${x+10} ${y+ry+5}L${x+13} ${y+ry+28}L${x+6} ${y+ry+30}Z"/></g>`;
    if(back) return [bun, `<ellipse cx="${x}" cy="${y}" rx="${r}" ry="${ry}" fill="${hair}"/>`, scarf];
    const face = `<ellipse cx="${x}" cy="${y}" rx="${r*.91}" ry="${ry}" fill="${skin}" stroke="${ink}" stroke-width="1.1"/>`;
    const worried = ['sad','angry','thinking'].includes(mood), surprised = mood==='surprised';
    const eyes = [-1,1].map(side => {
      const ex=x+side*r*.35;
      return `<ellipse cx="${ex}" cy="${y-1}" rx="2.6" ry="3.3" fill="#fff5e8"/><ellipse cx="${ex+.5}" cy="${y-1}" rx="1.8" ry="2.6" fill="#654735"/><circle cx="${ex+1}" cy="${y-2}" r=".7" fill="white"/><path d="M${ex-2.8} ${y-3}Q${ex} ${y-5} ${ex+2.8} ${y-3}" fill="none" stroke="${ink}" stroke-width=".8"/><path d="M${ex-2.8} ${y-7}Q${ex} ${y-(worried?10:9)} ${ex+2.8} ${y-7}" fill="none" stroke="${hair}" stroke-linecap="round" stroke-width="1"/>`;
    }).join('');
    const cheeks = `<g fill="#d98779" opacity=".4"><ellipse cx="${x-r*.55}" cy="${y+5}" rx="3.3" ry="1.6"/><ellipse cx="${x+r*.55}" cy="${y+5}" rx="3.3" ry="1.6"/></g>`;
    const mouth = input.speaking || surprised ? `<ellipse cx="${x+1}" cy="${y+10}" rx="2.1" ry="${surprised?2.7:1.8}" fill="#9f5961"/>` : `<path d="M${x-4} ${y+9}Q${x+1} ${y+(worried?8:14)} ${x+6} ${y+8}" fill="none" stroke="#a65d63" stroke-width="1.3" stroke-linecap="round"/>`;
    const fringe = `<path data-part="face-framing-hair" d="M${x-r*.95} ${y+4}Q${x-r*1.1} ${y-ry*1.15} ${x} ${y-ry}Q${x+r*1.15} ${y-ry*1.2} ${x+r*.93} ${y+7}Q${x+r*.55} ${y-3} ${x+r*.65} ${y-ry*.65}Q${x} ${y-ry*.25} ${x-r*.65} ${y-ry*.65}Q${x-r*.65} ${y-2} ${x-r*.95} ${y+4}Z" fill="${hair}"/>`;
    return [bun,scarf,face,cheeks,`<g data-part="expression">${eyes}<path d="M${x+2} ${y+1}l1 4-2 1" fill="none" stroke="#bb8b75" stroke-width=".8"/>${mouth}</g>`,fringe];
  }

  _renderHead(x,y) {
    const r = this.headRadius, ry = a.faceShape === 'oval' ? r*1.22 : r;
    if (a.faceStyle === 'soft') return this._softHead(x, y, r);
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
const view = a.faceStyle === 'soft' ? (close ? '-42 -18 84 103' : '-62 -18 124 219') : (close ? '-42 -5 84 90' : '-62 -5 124 206');
process.stdout.write(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="${view}" role="img"><title>${back?'Back view':close?'Close-up':'Standing'} · ${mood}${input.speaking?' · speaking':''}</title><g transform="scale(${left?-1:1} 1)">${f.render()}</g></svg>`);

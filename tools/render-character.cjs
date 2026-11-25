// RPH adapter around pinned, unmodified MIT StickyFig. JSON stdin -> SVG stdout.
const fs = require('node:fs');
const {StickFigure, POSES, EXPRESSIONS} = require('./sticky-fig/src/figure');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const a = input.appearance || {};
const color = (v, fallback) => /^#[0-9a-f]{6}$/i.test(v || '') ? v : fallback;
const skin = color(a.skin, '#c69572'), hair = color(a.hair, '#34314a');
const pose = input.pose || 'standing-right', back = pose === 'back';
const left = pose.endsWith('left'), close = pose.startsWith('closeup');
// Reusable side-profile gait, eight phases; phase 8 is the planted arrival pose.
if (/^walk-[0-8]-(left|right)$/.test(pose)) {
  const phase=Number(pose.split('-')[1]), swing=phase===8?0:Math.sin(phase*Math.PI/4), lift=phase===8?0:Math.abs(Math.cos(phase*Math.PI/4))*2;
  const coat=color(a.clothing,'#bd956f'), scarf=color(a.scarf,'#b84949');
  const leg=(sign,shade)=>{const dx=swing*sign*20;return `<path d="M50 143L${50+dx*.45} ${171-Math.max(0,-sign*swing)*7}L${50+dx} ${205-Math.max(0,-sign*swing)*12}" stroke="${shade}" stroke-width="8" fill="none" stroke-linecap="round"/><path d="M${48+dx} ${207-Math.max(0,-sign*swing)*12}h12" stroke="#37404b" stroke-width="7" stroke-linecap="round"/>`;};
  process.stdout.write(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 220"><title>Side-profile walking pose</title><g ${left?'transform="translate(100 0) scale(-1 1)"':''}>${leg(-1,'#796a60')}<g transform="translate(0 ${-lift})"><path d="M39 80L59 80L67 148H34Z" fill="${coat}" stroke="#534944" stroke-width="1.5"/><path d="M48 89L${49-swing*13} 114L${51-swing*22} 137" stroke="${coat}" stroke-width="8" fill="none" stroke-linecap="round"/><ellipse cx="46" cy="49" rx="22" ry="29" fill="${hair}"/><circle cx="29" cy="25" r="12" fill="${hair}"/><path d="M45 26Q68 27 67 47L72 54L66 57Q67 75 51 77Q38 75 36 60L36 40Z" fill="${skin}"/><path d="M34 28Q48 20 65 30L57 42L43 36L35 58Z" fill="${hair}"/><ellipse cx="61" cy="46" rx="2" ry="3" fill="#493a3c"/><path d="M56 39Q60 36 64 39M60 63h6" stroke="#654735" fill="none" stroke-width="1.5"/><path d="M35 76Q49 82 62 76L62 86L46 88L40 119L32 117L36 86Z" fill="${scarf}"/></g>${leg(1,'#a18b76')}</g></svg>`);
  process.exit(0);
}
// Two compatible rear poses share a hand anchor at the touching edge of their boxes.
if (pose.startsWith('holding-hands-back-')) {
  const leftPerson=pose.endsWith('left');
  const coat=color(a.clothing,'#bd956f');
  const reach=leftPerson?'M64 88 L82 111 L100 132':'M36 88 L18 111 L0 132';
  const outer=leftPerson?'M36 88 L27 139':'M64 88 L73 139';
  const bun=a.hairStyle==='loose-updo'?`<circle cx="35" cy="21" r="12" fill="${hair}"/>`:'';
  process.stdout.write(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 220"><title>Holding hands, rear view</title>${bun}<ellipse cx="50" cy="47" rx="24" ry="29" fill="${hair}"/><path d="M36 82 L64 82 L70 151 L30 151Z" fill="${coat}" stroke="#283447" stroke-width="2"/><path d="M40 151 L36 207 M60 151 L66 207" stroke="#3b4551" stroke-width="8" stroke-linecap="round"/><path d="${reach} ${outer}" fill="none" stroke="${coat}" stroke-width="8" stroke-linecap="round"/><circle cx="${leftPerson?100:0}" cy="132" r="4" fill="${skin}"/></svg>`);
  process.exit(0);
}
const moods = {amused:'happy', concerned:'sad', skeptical:'thinking', angry:'angry', surprised:'surprised', neutral:'neutral'};
const mood = moods[input.expression] || 'neutral';
EXPRESSIONS.rph = {...EXPRESSIONS[mood], ...(input.speaking ? {mouth:'o'} : {})};
class Role extends StickFigure {
  _renderBody(x,y,hip) {
    if(a.uniform==='mechanic') {
      const name=String(a.namePatch||'').replace(/[^a-zA-Z -]/g,'').slice(0,14);
      const patch=back?'':`<g ${left?`transform="translate(${2*x} 0) scale(-1 1)"`:''}><rect x="${x-11}" y="${y+12}" width="22" height="9" rx="2" fill="#eee8d5"/><text x="${x}" y="${y+18.4}" font-family="Arial" font-size="5.7" font-weight="bold" fill="#21303a" text-anchor="middle">${name}</text></g>`;
      return [`<g data-part="mechanic-shirt"><path d="M${x-14} ${y}L${x+14} ${y}L${x+12} ${hip}L${x-12} ${hip}Z" fill="${this.stroke}" stroke="#283447" stroke-width="1.2"/><path d="M${x} ${y}V${hip}" stroke="#c0d0d8"/>${patch}</g>`];
    }
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

  _naturalHead(x,y,r) {
    const worried=['sad','angry','thinking'].includes(mood);
    const mouth=input.speaking||mood==='surprised'?'<ellipse cx="2" cy="12" rx="2.5" ry="3" fill="#865950"/>':`<path d="M-5 11Q2 ${worried?10:16} 9 10" fill="none" stroke="#85564c" stroke-width="1.4" stroke-linecap="round"/>`;
    const curls=`<path d="M-19 -5Q-24 -23 -10 -24Q-5 -32 5 -26Q18 -30 21 -17L18 -3L14 -14Q4 -10 -2 -16Q-10 -9 -15 -15Z" fill="${hair}"/><g fill="${hair}"><ellipse cx="-11" cy="-22" rx="9" ry="6"/><ellipse cx="3" cy="-25" rx="8" ry="6"/><ellipse cx="14" cy="-20" rx="7" ry="6"/></g>`;
    if(back)return [`<g transform="translate(${x} ${y}) scale(${r/20})"><ellipse cy="-1" rx="19" ry="23" fill="${hair}"/>${curls}</g>`];
    const eyes=[-7,8].map(ex=>`<ellipse cx="${ex}" cy="-2" rx="3.3" ry="3.6" fill="#fff8ef"/><ellipse cx="${ex+.6}" cy="-1.5" rx="1.8" ry="2.5" fill="#6d694c"/><circle cx="${ex+1}" cy="-2.5" r=".7" fill="#fff"/><path d="M${ex-4} -8Q${ex} ${worried?-9:-11} ${ex+4} ${worried?-11:-8}" fill="none" stroke="${hair}" stroke-width="1.8" stroke-linecap="round"/>`).join('');
    return [`<g transform="translate(${x} ${y}) scale(${r/20})"><path d="M-18 -14Q0 -27 18 -14L19 7Q16 20 7 23H-5Q-17 18 -19 4Z" fill="${skin}" stroke="#574943" stroke-width="1.4"/><path d="M-16 9Q-10 23 5 21Q16 18 17 8Q9 15 2 16Q-7 16 -16 9Z" fill="${hair}" opacity=".17"/>${eyes}<path d="M3 -1L5 5Q3 7 1 5" fill="none" stroke="#9f715b" stroke-width="1.2" stroke-linecap="round"/>${mouth}${curls}</g>`];
  }

  _renderHead(x,y) {
    const r = this.headRadius, ry = a.faceShape === 'oval' ? r*1.22 : r;
    if (a.faceStyle === 'natural') return this._naturalHead(x,y,r);
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

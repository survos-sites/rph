const svg = require('./svg')

const EXPRESSIONS = {
  neutral: { eyes: 'normal', mouth: 'line' },
  happy: { eyes: 'normal', mouth: 'smile' },
  sad: { eyes: 'sad', mouth: 'frown' },
  angry: { eyes: 'angry', mouth: 'frown', brows: 'angry' },
  surprised: { eyes: 'wide', mouth: 'o' },
  scared: { eyes: 'wide', mouth: 'wavy', brows: 'worried' },
  laughing: { eyes: 'closed', mouth: 'big-smile' },
  wink: { eyes: 'wink', mouth: 'smile' },
  confused: { eyes: 'normal', mouth: 'wavy', brows: 'confused' },
  thinking: { eyes: 'look-up', mouth: 'line', brows: 'one-up' },
  dead: { eyes: 'x-eyes', mouth: 'wavy' },
  dizzy: { eyes: 'spiral', mouth: 'wavy' },
}

const { Animation, animation: mkAnimation } = require('./animation')

const ANIM_ID_PREFIX = 'sffig'
let _figIdCounter = 0

function clamp(val, min, max) {
  return Math.max(min, Math.min(max, val))
}

function rad(deg) {
  return deg * Math.PI / 180
}

class Pose {
  constructor(opts = {}) {
    this.headTilt = opts.headTilt || 0
    this.leftArm = opts.leftArm || { shoulder: 0, elbow: 0 }
    this.rightArm = opts.rightArm || { shoulder: 0, elbow: 0 }
    this.leftLeg = opts.leftLeg || { hip: 5, knee: 0 }
    this.rightLeg = opts.rightLeg || { hip: -5, knee: 0 }
    this.bodyLean = opts.bodyLean || 0
  }
}

class StickFigure {
  constructor(name, opts = {}) {
    this.name = name
    this.x = opts.x || 0
    this.y = opts.y || 0
    this.scale = opts.scale || 1
    this.stroke = opts.stroke || 'black'
    this.strokeWidth = opts.strokeWidth || 2.5
    this.headRadius = (opts.headRadius || 18) * this.scale
    this.bodyLength = (opts.bodyLength || 55) * this.scale
    this.armLength = (opts.armLength || 35) * this.scale
    this.legLength = (opts.legLength || 45) * this.scale
    this.armLineWidth = (opts.armLineWidth || 2) * this.scale
    this.expression = opts.expression || 'neutral'
    this.pose = opts.pose instanceof Pose ? opts.pose : new Pose(opts.pose || {})
    this.hair = opts.hair || null
    this.hat = opts.hat || null
    this.accessories = opts.accessories || []
    this.animation = opts.animation || null
    if (this.animation && !(this.animation instanceof Animation)) {
      this.animation = mkAnimation(this.animation)
    }
    this._animId = ANIM_ID_PREFIX + (_figIdCounter++)
  }

  neckX() {
    return 0 + this.pose.bodyLean * this.scale * 0.3
  }
  neckY() {
    return -this.legLength - this.bodyLength
  }

  headX() {
    return this.neckX() + Math.sin(rad(this.pose.headTilt)) * this.headRadius * 0.8
  }
  headY() {
    return this.neckY() - this.headRadius * 1.1
  }

  hipY() {
    return -this.legLength
  }
  shoulderY() {
    return this.neckY()
  }

  render() {
    const els = []

    const tx = this.x
    const ty = this.y + this.legLength + this.bodyLength

    const headX = this.headX()
    const headY = this.headY()
    const neckX = this.neckX()
    const neckY = this.neckY()
    const hipY = this.hipY()
    const shoulderY = this.shoulderY()

    const shoulderOffsetX = this.headRadius * 0.6
    const hipOffsetX = this.headRadius * 0.25

    els.push(...this._renderBody(neckX, neckY, hipY))

    const isWalk = this.animation && (this.animation.type === 'walk-cycle' || this.animation.type === 'run-cycle')

    if (isWalk) {
      els.push(svg.line(neckX - hipOffsetX, hipY, neckX + hipOffsetX, hipY, { stroke: this.stroke, width: this.strokeWidth, cap: 'round' }))
      els.push(...this._renderAnimatedArms(neckX, shoulderY, shoulderOffsetX))
      els.push(...this._renderAnimatedLegs(neckX, hipY, hipOffsetX))
    } else {
      els.push(...this._renderArms(neckX, shoulderY, shoulderOffsetX))
      els.push(...this._renderLegs(neckX, hipY, hipOffsetX))
    }
    els.push(...this._renderHead(headX, headY))

    if (this.hair) els.push(...this._renderHair(headX, headY))
    if (this.hat) els.push(...this._renderHat(headX, headY))

    const groupOpts = { transform: svg.translate(tx, ty) }

    if (this.animation) {
      return svg.group(
        [svg.group(els, {
          className: this._animId,
          style: this.animation.getInlineStyle(),
        })],
        groupOpts
      )
    }

    return svg.group(els, groupOpts)
  }

  _renderHead(hx, hy) {
    const els = []
    els.push(svg.circle(hx, hy, this.headRadius, {
      stroke: this.stroke,
      width: this.strokeWidth,
      fill: 'white'
    }))
    els.push(...this._renderExpression(hx, hy))
    return els
  }

  _renderExpression(hx, hy) {
    const els = []
    const expr = EXPRESSIONS[this.expression] || EXPRESSIONS.neutral
    const r = this.headRadius
    const s = this.scale
    const eyeY = hy - r * 0.1
    const eyeSpacing = r * 0.35
    const mouthY = hy + r * 0.35

    const opts = { stroke: this.stroke, width: Math.max(1.5, 1.5 * s), fill: 'none' }

    if (expr.brows === 'angry') {
      els.push(svg.line(hx - eyeSpacing - r * 0.2, eyeY - r * 0.35, hx - eyeSpacing + r * 0.1, eyeY - r * 0.25, opts))
      els.push(svg.line(hx + eyeSpacing + r * 0.2, eyeY - r * 0.35, hx + eyeSpacing - r * 0.1, eyeY - r * 0.25, opts))
    } else if (expr.brows === 'worried') {
      els.push(svg.line(hx - eyeSpacing - r * 0.15, eyeY - r * 0.3, hx - eyeSpacing + r * 0.15, eyeY - r * 0.38, opts))
      els.push(svg.line(hx + eyeSpacing - r * 0.15, eyeY - r * 0.38, hx + eyeSpacing + r * 0.15, eyeY - r * 0.3, opts))
    } else if (expr.brows === 'confused') {
      els.push(svg.line(hx - eyeSpacing - r * 0.15, eyeY - r * 0.32, hx - eyeSpacing + r * 0.15, eyeY - r * 0.32, opts))
      els.push(svg.line(hx + eyeSpacing - r * 0.15, eyeY - r * 0.38, hx + eyeSpacing + r * 0.15, eyeY - r * 0.28, opts))
    } else if (expr.brows === 'one-up') {
      els.push(svg.line(hx - eyeSpacing - r * 0.15, eyeY - r * 0.38, hx - eyeSpacing + r * 0.15, eyeY - r * 0.3, opts))
    }

    switch (expr.eyes) {
      case 'normal':
        els.push(svg.filledCircle(hx - eyeSpacing, eyeY, r * 0.08, this.stroke))
        els.push(svg.filledCircle(hx + eyeSpacing, eyeY, r * 0.08, this.stroke))
        break
      case 'wide':
        els.push(svg.circle(hx - eyeSpacing, eyeY, r * 0.13, { fill: 'white', stroke: this.stroke, width: 1.5 * s }))
        els.push(svg.filledCircle(hx - eyeSpacing, eyeY, r * 0.07, this.stroke))
        els.push(svg.circle(hx + eyeSpacing, eyeY, r * 0.13, { fill: 'white', stroke: this.stroke, width: 1.5 * s }))
        els.push(svg.filledCircle(hx + eyeSpacing, eyeY, r * 0.07, this.stroke))
        break
      case 'sad':
        els.push(svg.filledCircle(hx - eyeSpacing, eyeY, r * 0.08, this.stroke))
        els.push(svg.filledCircle(hx + eyeSpacing, eyeY, r * 0.08, this.stroke))
        els.push(svg.line(hx - eyeSpacing - r * 0.15, eyeY - r * 0.18, hx - eyeSpacing + r * 0.08, eyeY - r * 0.1, { stroke: this.stroke, width: 1.5 * s }))
        els.push(svg.line(hx + eyeSpacing + r * 0.15, eyeY - r * 0.18, hx + eyeSpacing - r * 0.08, eyeY - r * 0.1, { stroke: this.stroke, width: 1.5 * s }))
        break
      case 'angry':
        els.push(svg.filledCircle(hx - eyeSpacing, eyeY, r * 0.08, this.stroke))
        els.push(svg.filledCircle(hx + eyeSpacing, eyeY, r * 0.08, this.stroke))
        break
      case 'closed':
        els.push(svg.line(hx - eyeSpacing - r * 0.1, eyeY, hx - eyeSpacing + r * 0.1, eyeY, { stroke: this.stroke, width: 1.5 * s }))
        els.push(svg.line(hx + eyeSpacing - r * 0.1, eyeY, hx + eyeSpacing + r * 0.1, eyeY, { stroke: this.stroke, width: 1.5 * s }))
        break
      case 'wink':
        els.push(svg.filledCircle(hx - eyeSpacing, eyeY, r * 0.08, this.stroke))
        els.push(svg.line(hx + eyeSpacing - r * 0.1, eyeY, hx + eyeSpacing + r * 0.1, eyeY, { stroke: this.stroke, width: 1.5 * s }))
        break
      case 'look-up':
        els.push(svg.filledCircle(hx - eyeSpacing, eyeY - r * 0.05, r * 0.08, this.stroke))
        els.push(svg.filledCircle(hx + eyeSpacing, eyeY - r * 0.05, r * 0.08, this.stroke))
        break
      case 'x-eyes':
        const es = r * 0.1
        els.push(svg.line(hx - eyeSpacing - es, eyeY - es, hx - eyeSpacing + es, eyeY + es, { stroke: this.stroke, width: 1.5 * s }))
        els.push(svg.line(hx - eyeSpacing + es, eyeY - es, hx - eyeSpacing - es, eyeY + es, { stroke: this.stroke, width: 1.5 * s }))
        els.push(svg.line(hx + eyeSpacing - es, eyeY - es, hx + eyeSpacing + es, eyeY + es, { stroke: this.stroke, width: 1.5 * s }))
        els.push(svg.line(hx + eyeSpacing + es, eyeY - es, hx + eyeSpacing - es, eyeY + es, { stroke: this.stroke, width: 1.5 * s }))
        break
      case 'spiral':
        els.push(svg.path(`M ${hx - eyeSpacing} ${eyeY} a ${r * 0.05} ${r * 0.05} 0 0 1 ${r * 0.1} 0 a ${r * 0.1} ${r * 0.1} 0 0 1 -${r * 0.2} 0`, { stroke: this.stroke, width: 1.2 * s, fill: 'none' }))
        els.push(svg.path(`M ${hx + eyeSpacing} ${eyeY} a ${r * 0.05} ${r * 0.05} 0 0 1 ${r * 0.1} 0 a ${r * 0.1} ${r * 0.1} 0 0 1 -${r * 0.2} 0`, { stroke: this.stroke, width: 1.2 * s, fill: 'none' }))
        break
    }

    const mouthW = r * 0.35
    switch (expr.mouth) {
      case 'line':
        els.push(svg.line(hx - mouthW, mouthY, hx + mouthW, mouthY, { stroke: this.stroke, width: Math.max(1.5, 1.5 * s), cap: 'round' }))
        break
      case 'smile':
        els.push(svg.path(`M ${hx - mouthW} ${mouthY - r * 0.05} Q ${hx} ${mouthY + r * 0.25} ${hx + mouthW} ${mouthY - r * 0.05}`, { stroke: this.stroke, width: Math.max(1.5, 1.5 * s), fill: 'none' }))
        break
      case 'frown':
        els.push(svg.path(`M ${hx - mouthW} ${mouthY + r * 0.1} Q ${hx} ${mouthY - r * 0.2} ${hx + mouthW} ${mouthY + r * 0.1}`, { stroke: this.stroke, width: Math.max(1.5, 1.5 * s), fill: 'none' }))
        break
      case 'o':
        els.push(svg.ellipse(hx, mouthY + r * 0.05, r * 0.12, r * 0.15, { stroke: this.stroke, width: Math.max(1.5, 1.5 * s), fill: 'white' }))
        break
      case 'big-smile':
        els.push(svg.path(`M ${hx - mouthW * 1.2} ${mouthY - r * 0.1} Q ${hx} ${mouthY + r * 0.45} ${hx + mouthW * 1.2} ${mouthY - r * 0.1}`, { stroke: this.stroke, width: Math.max(1.5, 1.5 * s), fill: 'none' }))
        break
      case 'wavy':
        els.push(svg.path(`M ${hx - mouthW} ${mouthY} Q ${hx - mouthW * 0.5} ${mouthY + r * 0.12} ${hx} ${mouthY} Q ${hx + mouthW * 0.5} ${mouthY - r * 0.12} ${hx + mouthW} ${mouthY}`, { stroke: this.stroke, width: Math.max(1.5, 1.5 * s), fill: 'none' }))
        break
    }

    return els
  }

  _renderBody(neckX, neckY, hipY) {
    const shoulderW = this.headRadius * 0.6
    return [
      svg.line(neckX, neckY, neckX, hipY, { stroke: this.stroke, width: this.strokeWidth, cap: 'round' }),
      svg.line(neckX - shoulderW, neckY, neckX + shoulderW, neckY, { stroke: this.stroke, width: this.armLineWidth, cap: 'round' }),
    ]
  }

  _renderArms(neckX, shoulderY, shoulderOffset) {
    const els = []
    const armLen = this.armLength
    const halfArm = armLen * 0.5

    for (const side of ['left', 'right']) {
      const arm = side === 'left' ? this.pose.leftArm : this.pose.rightArm
      const dir = side === 'left' ? -1 : 1

      const shoulderAngle = rad(arm.shoulder)
      const shoulderX = neckX + shoulderOffset * dir
      const shoulderPt = [shoulderX, shoulderY]

      const elbowAngle = shoulderAngle + rad(arm.elbow)

      const elbowX = shoulderX + Math.sin(shoulderAngle) * halfArm * dir
      const elbowY = shoulderY + Math.cos(shoulderAngle) * halfArm

      const handX = elbowX + Math.sin(elbowAngle) * halfArm * dir
      const handY = elbowY + Math.cos(elbowAngle) * halfArm

      els.push(svg.line(shoulderX, shoulderY, elbowX, elbowY, {
        stroke: this.stroke, width: this.armLineWidth, cap: 'round'
      }))
      els.push(svg.line(elbowX, elbowY, handX, handY, {
        stroke: this.stroke, width: this.armLineWidth, cap: 'round'
      }))
    }

    return els
  }

  _renderLegs(neckX, hipY, hipOffset) {
    const els = []
    const legLen = this.legLength
    const halfLeg = legLen * 0.5

    for (const side of ['left', 'right']) {
      const leg = side === 'left' ? this.pose.leftLeg : this.pose.rightLeg
      const dir = side === 'left' ? -1 : 1

      const hipAngle = rad(leg.hip)
      const hipX = neckX + hipOffset * dir

      const kneeX = hipX + Math.sin(hipAngle) * halfLeg * dir
      const kneeY = hipY + Math.cos(hipAngle) * halfLeg

      const kneeAngle = hipAngle + rad(leg.knee)
      const footX = kneeX + Math.sin(kneeAngle) * halfLeg * dir
      const footY = kneeY + Math.cos(kneeAngle) * halfLeg

      els.push(svg.line(hipX, hipY, kneeX, kneeY, {
        stroke: this.stroke, width: this.strokeWidth, cap: 'round'
      }))
      els.push(svg.line(kneeX, kneeY, footX, footY, {
        stroke: this.stroke, width: this.strokeWidth, cap: 'round'
      }))
    }

    return els
  }

  _makeAnimateTransform(values, dur) {
    const kt = '0;0.25;0.5;0.75;1'
    const splines = '0.42 0 0.58 1;0.42 0 0.58 1;0.42 0 0.58 1;0.42 0 0.58 1'
    return `<animateTransform attributeName="transform" type="rotate" values="${values}" keyTimes="${kt}" dur="${dur}s" calcMode="spline" keySplines="${splines}" repeatCount="indefinite"/>`
  }

  _renderAnimatedArms(neckX, shoulderY, shoulderOffset) {
    const els = []
    const halfArm = this.armLength * 0.5
    const isRun = this.animation && this.animation.type === 'run-cycle'
    const dur = this.animation ? this.animation.getDuration() : (isRun ? 0.6 : 1)

    const lUp = isRun ? '-50;-12;50;12;-50' : '-32;-8;32;8;-32'
    const rUp = isRun ? '50;12;-50;-12;50' : '32;8;-32;-8;32'
    const fore = isRun ? '-12;-25;-12;-25;-12' : '-8;-16;-8;-16;-8'

    for (const side of ['left', 'right']) {
      const dir = side === 'left' ? -1 : 1
      const sx = neckX + shoulderOffset * dir
      const upVals = side === 'left' ? lUp : rUp
      const anim = (vals) => this._makeAnimateTransform(vals, dur)
      const opts = { stroke: this.stroke, width: this.armLineWidth, cap: 'round' }

      els.push(svg.group([
        svg.group([
          anim(upVals),
          svg.line(0, 0, 0, halfArm, opts),
          svg.group([
            svg.group([
              anim(fore),
              svg.line(0, 0, 0, halfArm, opts),
            ]),
          ], { transform: svg.translate(0, halfArm) }),
        ]),
      ], { transform: svg.translate(sx, shoulderY) }))
    }

    return els
  }

  _renderAnimatedLegs(neckX, hipY, hipOffset) {
    const els = []
    const halfLeg = this.legLength * 0.5
    const isRun = this.animation && this.animation.type === 'run-cycle'
    const dur = this.animation ? this.animation.getDuration() : (isRun ? 0.6 : 1)

    const lUp = isRun ? '-45;-10;45;10;-45' : '-28;-7;28;7;-28'
    const rUp = isRun ? '45;10;-45;-10;45' : '28;7;-28;-7;28'
    const lLow = isRun ? '0;8;32;8;0' : '0;5;22;5;0'
    const rLow = isRun ? '32;8;0;8;32' : '22;5;0;5;22'

    for (const side of ['left', 'right']) {
      const dir = side === 'left' ? -1 : 1
      const hx = neckX + hipOffset * dir
      const upVals = side === 'left' ? lUp : rUp
      const lowVals = side === 'left' ? lLow : rLow
      const anim = (vals) => this._makeAnimateTransform(vals, dur)
      const opts = { stroke: this.stroke, width: this.strokeWidth, cap: 'round' }

      els.push(svg.group([
        svg.group([
          anim(upVals),
          svg.line(0, 0, 0, halfLeg, opts),
          svg.group([
            svg.group([
              anim(lowVals),
              svg.line(0, 0, 0, halfLeg, opts),
            ]),
          ], { transform: svg.translate(0, halfLeg) }),
        ]),
      ], { transform: svg.translate(hx, hipY) }))
    }

    return els
  }

  _renderHair(hx, hy) {
    const els = []
    const r = this.headRadius
    const opts = { stroke: this.stroke, width: Math.max(1.5, 1.5 * this.scale), fill: 'none' }

    switch (this.hair) {
      case 'spiky':
        for (let i = -2; i <= 2; i++) {
          const angle = -90 + i * 25
          const startX = hx + Math.cos(rad(angle)) * r
          const startY = hy + Math.sin(rad(angle)) * r
          const endX = hx + Math.cos(rad(angle)) * (r + r * 0.4)
          const endY = hy + Math.sin(rad(angle)) * (r + r * 0.4)
          els.push(svg.line(startX, startY, endX, endY, opts))
        }
        break
      case 'long':
        els.push(svg.path(`M ${hx - r} ${hy} C ${hx - r - r * 0.3} ${hy + r * 0.8} ${hx - r * 0.5} ${hy + r * 1.5} ${hx - r * 0.3} ${hy + r * 1.8}`, opts))
        els.push(svg.path(`M ${hx + r} ${hy} C ${hx + r + r * 0.3} ${hy + r * 0.8} ${hx + r * 0.5} ${hy + r * 1.5} ${hx + r * 0.3} ${hy + r * 1.8}`, opts))
        break
      case 'curly':
        for (let i = -3; i <= 3; i++) {
          const angle = -90 + i * 20
          const cx1 = hx + Math.cos(rad(angle)) * (r + r * 0.2)
          const cy1 = hy + Math.sin(rad(angle)) * (r + r * 0.2)
          els.push(svg.circle(cx1, cy1, r * 0.12, { ...opts, fill: this.stroke }))
        }
        break
      case 'mohawk':
        els.push(svg.path(`M ${hx - r * 0.15} ${hy - r} L ${hx} ${hy - r * 1.7} L ${hx + r * 0.15} ${hy - r}`, { ...opts, fill: this.stroke }))
        break
    }

    return els
  }

  _renderHat(hx, hy) {
    const els = []
    const r = this.headRadius
    const opts = { stroke: this.stroke, width: Math.max(1.5, 1.5 * this.scale), fill: 'none' }

    switch (this.hat) {
      case 'tophat':
        els.push(svg.rect(hx - r * 0.5, hy - r * 2, r, r * 0.9, { ...opts, fill: this.stroke }))
        els.push(svg.line(hx - r * 0.8, hy - r * 1.1, hx + r * 0.8, hy - r * 1.1, { ...opts, stroke: this.stroke }))
        break
      case 'cap':
        els.push(svg.path(`M ${hx - r * 0.8} ${hy - r * 0.5} A ${r * 0.8} ${r * 0.5} 0 0 1 ${hx + r * 0.8} ${hy - r * 0.5}`, { ...opts, fill: this.stroke }))
        els.push(svg.line(hx, hy - r * 0.5, hx + r * 0.9, hy - r * 0.2, { ...opts, stroke: this.stroke }))
        break
      case 'cowboy':
        els.push(svg.path(`M ${hx - r * 1.4} ${hy - r * 0.6} L ${hx - r * 0.5} ${hy - r * 0.8} L ${hx - r * 0.5} ${hy - r * 1.6} L ${hx + r * 0.5} ${hy - r * 1.6} L ${hx + r * 0.5} ${hy - r * 0.8} L ${hx + r * 1.4} ${hy - r * 0.6}`, { ...opts, fill: this.stroke }))
        break
    }

    return els
  }
}

function figure(name, opts = {}) {
  return new StickFigure(name, opts)
}

function pose(opts = {}) {
  return new Pose(opts)
}

const POSES = {
  standing: new Pose({ leftArm: { shoulder: 15, elbow: 0 }, rightArm: { shoulder: -15, elbow: 0 }, leftLeg: { hip: 5, knee: 0 }, rightLeg: { hip: -5, knee: 0 } }),
  walking: new Pose({ leftArm: { shoulder: 30, elbow: -20 }, rightArm: { shoulder: -30, elbow: -20 }, leftLeg: { hip: 25, knee: 0 }, rightLeg: { hip: -25, knee: 10 } }),
  running: new Pose({ leftArm: { shoulder: 50, elbow: -30 }, rightArm: { shoulder: -50, elbow: -30 }, leftLeg: { hip: 40, knee: 20 }, rightLeg: { hip: -40, knee: 25 } }),
  waving: new Pose({ leftArm: { shoulder: -150, elbow: -30 }, rightArm: { shoulder: -10, elbow: 0 }, leftLeg: { hip: 5, knee: 0 }, rightLeg: { hip: -5, knee: 0 } }),
  pointing: new Pose({ leftArm: { shoulder: -80, elbow: 0 }, rightArm: { shoulder: 10, elbow: 0 }, leftLeg: { hip: 5, knee: 0 }, rightLeg: { hip: -5, knee: 0 } }),
  shrugging: new Pose({ leftArm: { shoulder: 70, elbow: -80 }, rightArm: { shoulder: -70, elbow: 80 }, bodyLean: 0 }),
  sitting: new Pose({ leftLeg: { hip: 70, knee: -70 }, rightLeg: { hip: -70, knee: 70 }, leftArm: { shoulder: 10, elbow: 0 }, rightArm: { shoulder: -10, elbow: 0 } }),
  kicking: new Pose({ leftLeg: { hip: 5, knee: 0 }, rightLeg: { hip: -80, knee: 10 }, leftArm: { shoulder: 30, elbow: 0 }, rightArm: { shoulder: -50, elbow: 0 } }),
  defeated: new Pose({ leftArm: { shoulder: 40, elbow: -40 }, rightArm: { shoulder: -40, elbow: 40 }, leftLeg: { hip: 15, knee: 15 }, rightLeg: { hip: -15, knee: 10 }, bodyLean: 10, headTilt: 20 }),
  armsUp: new Pose({ leftArm: { shoulder: 150, elbow: 0 }, rightArm: { shoulder: -150, elbow: 0 }, leftLeg: { hip: 5, knee: 0 }, rightLeg: { hip: -5, knee: 0 }, headTilt: -5 }),
  thinking2: new Pose({ leftArm: { shoulder: 70, elbow: -90 }, rightArm: { shoulder: -10, elbow: 0 }, leftLeg: { hip: 5, knee: 0 }, rightLeg: { hip: -5, knee: 0 }, headTilt: -10 }),
  thumbsUp: new Pose({ leftArm: { shoulder: 10, elbow: 0 }, rightArm: { shoulder: -160, elbow: 0 }, leftLeg: { hip: 5, knee: 0 }, rightLeg: { hip: -5, knee: 0 } }),
}

module.exports = {
  StickFigure,
  Pose,
  figure,
  pose,
  POSES,
  EXPRESSIONS,
}
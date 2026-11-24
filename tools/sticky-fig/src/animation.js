let _idCounter = 0

const PRESETS = {
  bounce: {
    keyframes: `@keyframes sf-bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-12px); }
}`,
    defaultDuration: 1.5,
  },
  wave: {
    keyframes: `@keyframes sf-wave {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(8deg); }
  75% { transform: rotate(-8deg); }
}`,
    defaultDuration: 1,
  },
  shake: {
    keyframes: `@keyframes sf-shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-5px); }
  75% { transform: translateX(5px); }
}`,
    defaultDuration: 0.5,
  },
  'fade-in': {
    keyframes: `@keyframes sf-fade-in {
  0% { opacity: 0; }
  100% { opacity: 1; }
}`,
    defaultDuration: 0.8,
    fill: 'forwards',
  },
  pop: {
    keyframes: `@keyframes sf-pop {
  0% { transform: scale(0); opacity: 0; }
  70% { transform: scale(1.15); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}`,
    defaultDuration: 0.6,
    fill: 'forwards',
  },
  float: {
    keyframes: `@keyframes sf-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}`,
    defaultDuration: 3,
  },
  pulse: {
    keyframes: `@keyframes sf-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.06); }
}`,
    defaultDuration: 2,
  },
  jitter: {
    keyframes: `@keyframes sf-jitter {
  0%, 100% { transform: translate(0, 0); }
  25% { transform: translate(-2px, 1px); }
  50% { transform: translate(2px, -1px); }
  75% { transform: translate(-1px, -2px); }
}`,
    defaultDuration: 0.3,
  },
  'slide-left': {
    keyframes: `@keyframes sf-slide-left {
  0% { transform: translateX(40px); opacity: 0; }
  100% { transform: translateX(0); opacity: 1; }
}`,
    defaultDuration: 0.7,
    fill: 'forwards',
  },
  'slide-right': {
    keyframes: `@keyframes sf-slide-right {
  0% { transform: translateX(-40px); opacity: 0; }
  100% { transform: translateX(0); opacity: 1; }
}`,
    defaultDuration: 0.7,
    fill: 'forwards',
  },
  'slide-up': {
    keyframes: `@keyframes sf-slide-up {
  0% { transform: translateY(30px); opacity: 0; }
  100% { transform: translateY(0); opacity: 1; }
}`,
    defaultDuration: 0.7,
    fill: 'forwards',
  },
  'slide-down': {
    keyframes: `@keyframes sf-slide-down {
  0% { transform: translateY(-30px); opacity: 0; }
  100% { transform: translateY(0); opacity: 1; }
}`,
    defaultDuration: 0.7,
    fill: 'forwards',
  },
  spin: {
    keyframes: `@keyframes sf-spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}`,
    defaultDuration: 2,
  },
  'pop-in': {
    keyframes: `@keyframes sf-pop-in {
  0% { transform: scale(0.3); opacity: 0; }
  50% { transform: scale(1.05); }
  70% { transform: scale(0.9); }
  100% { transform: scale(1); opacity: 1; }
}`,
    defaultDuration: 0.5,
    fill: 'forwards',
  },
  'walk-cycle': {
    keyframes: `@keyframes sf-walk-cycle {
  0%, 100% { transform: translateY(0) rotate(1deg); }
  25% { transform: translateY(-8px) rotate(2deg); }
  50% { transform: translateY(0) rotate(1deg); }
  75% { transform: translateY(-8px) rotate(2deg); }
}`,
    defaultDuration: 1,
  },
  'run-cycle': {
    keyframes: `@keyframes sf-run-cycle {
  0%, 100% { transform: translateY(0) rotate(3deg); }
  25% { transform: translateY(-14px) rotate(5deg); }
  50% { transform: translateY(0) rotate(3deg); }
  75% { transform: translateY(-14px) rotate(5deg); }
}`,
    defaultDuration: 0.6,
  },
}

class Animation {
  constructor(type, opts = {}) {
    this.id = _idCounter++
    this.type = typeof type === 'string' ? type : null
    this.duration = opts.duration !== undefined ? opts.duration : null
    this.delay = opts.delay || 0
    this.repeat = opts.repeat !== undefined ? opts.repeat : true
    this.direction = opts.direction || null
    this.easing = opts.easing || 'ease-in-out'
    this.customKeyframes = opts.keyframes || null
    this.fill = opts.fill || null
  }

  getDuration() {
    if (this.duration !== null) return this.duration
    const preset = PRESETS[this.type]
    return preset ? preset.defaultDuration : 1
  }

  getFill() {
    if (this.fill) return this.fill
    const preset = PRESETS[this.type]
    return preset ? (preset.fill || 'none') : 'none'
  }

  getRepeatCount() {
    if (this.repeat === true || this.repeat === 'indefinite' || this.repeat === Infinity) return 'indefinite'
    if (typeof this.repeat === 'number') return this.repeat
    return 1
  }

  getDirection() {
    if (this.direction) return this.direction
    if (this.repeat === true || this.repeat === 'indefinite') return 'alternate'
    return 'normal'
  }

  getAnimationName() {
    if (this.customKeyframes) return `sf-custom-${this.id}`
    return `sf-${this.type}`
  }

  getInlineStyle() {
    const name = this.getAnimationName()
    const duration = this.getDuration()
    const delay = this.delay
    const repeat = this.getRepeatCount()
    const direction = this.getDirection()
    const easing = this.easing
    const fill = this.getFill()
    const repeatStr = repeat === 'indefinite' ? 'infinite' : repeat
    let style = `animation-name:${name};animation-duration:${duration}s;animation-timing-function:${easing};animation-delay:${delay}s;animation-iteration-count:${repeatStr};animation-direction:${direction}`
    if (fill && fill !== 'none') {
      style += `;animation-fill-mode:${fill}`
    }
    return style
  }

  getKeyframesCSS() {
    if (this.customKeyframes) {
      return `@keyframes sf-custom-${this.id} { ${this.customKeyframes} }`
    }
    const preset = PRESETS[this.type]
    if (!preset) return ''
    return preset.keyframes
  }

  isPreset() {
    return !this.customKeyframes && PRESETS[this.type]
  }
}

function animation(type, opts) {
  if (typeof type === 'string') {
    return new Animation(type, opts || {})
  }
  if (typeof type === 'object' && type !== null) {
    return new Animation(type.type || 'custom', type)
  }
  return new Animation('bounce', {})
}

function collectAnimationStyles(animations) {
  const keyframeDefs = new Map()
  const styles = []

  for (const anim of animations) {
    if (!anim) continue
    const name = anim.getAnimationName()
    if (!keyframeDefs.has(name)) {
      const css = anim.getKeyframesCSS()
      if (css) keyframeDefs.set(name, css)
    }
  }

  if (keyframeDefs.size > 0) {
    styles.push(Array.from(keyframeDefs.values()).join('\n\n'))
  }

  return styles.join('\n\n')
}

const ANIMATION_PRESETS = Object.keys(PRESETS)

module.exports = {
  Animation,
  animation,
  PRESETS,
  ANIMATION_PRESETS,
  collectAnimationStyles,
}
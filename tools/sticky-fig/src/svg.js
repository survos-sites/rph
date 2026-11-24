class SvgCanvas {
  constructor(width, height) {
    this.width = width
    this.height = height
    this.elements = []
    this.defs = []
    this.styles = []
  }

  addDef(def) {
    this.defs.push(def)
  }

  add(el) {
    this.elements.push(el)
  }

  addStyle(css) {
    this.styles.push(css)
  }

  render() {
    const defsXml = this.defs.length > 0
      ? `\n  <defs>\n    ${this.defs.join('\n    ')}\n  </defs>`
      : ''
    const styleXml = this.styles.length > 0
      ? `\n  <style>\n    ${this.styles.join('\n    ')}\n  </style>`
      : ''

    return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${this.width}" height="${this.height}" viewBox="0 0 ${this.width} ${this.height}">
${defsXml}${styleXml}
  <rect width="100%" height="100%" fill="white"/>
  ${this.elements.join('\n  ')}
</svg>`
  }
}

function line(x1, y1, x2, y2, opts = {}) {
  const stroke = opts.stroke || 'black'
  const width = opts.width || 2
  const dasharray = opts.dash ? ` stroke-dasharray="${opts.dash}"` : ''
  const cap = opts.cap ? ` stroke-linecap="${opts.cap}"` : ''
  return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${stroke}" stroke-width="${width}"${dasharray}${cap}/>`
}

function circle(cx, cy, r, opts = {}) {
  const fill = opts.fill || 'none'
  const stroke = opts.stroke || 'black'
  const width = opts.width || 2
  return `<circle cx="${cx}" cy="${cy}" r="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${width}"/>`
}

function filledCircle(cx, cy, r, fill, opts = {}) {
  const stroke = opts.stroke || fill
  return `<circle cx="${cx}" cy="${cy}" r="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${opts.width || 1}"/>`
}

function ellipse(cx, cy, rx, ry, opts = {}) {
  const fill = opts.fill || 'none'
  const stroke = opts.stroke || 'black'
  const width = opts.width || 2
  return `<ellipse cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}" fill="${fill}" stroke="${stroke}" stroke-width="${width}"/>`
}

function rect(x, y, w, h, opts = {}) {
  const fill = opts.fill || 'none'
  const stroke = opts.stroke || 'black'
  const width = opts.width || 2
  const rx = opts.rx || 0
  const ry = opts.ry || rx
  return `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${fill}" stroke="${stroke}" stroke-width="${width}" rx="${rx}" ry="${ry}"/>`
}

function polygon(points, opts = {}) {
  const fill = opts.fill || 'none'
  const stroke = opts.stroke || 'black'
  const width = opts.width || 2
  const pts = points.map(p => p.join(',')).join(' ')
  return `<polygon points="${pts}" fill="${fill}" stroke="${stroke}" stroke-width="${width}"/>`
}

function polyline(points, opts = {}) {
  const stroke = opts.stroke || 'black'
  const width = opts.width || 2
  const fill = opts.fill || 'none'
  const cap = opts.cap ? ` stroke-linecap="${opts.cap}"` : ''
  const join = opts.join ? ` stroke-linejoin="${opts.join}"` : ''
  const pts = points.map(p => p.join(',')).join(' ')
  return `<polyline points="${pts}" fill="${fill}" stroke="${stroke}" stroke-width="${width}"${cap}${join}/>`
}

function path(d, opts = {}) {
  const fill = opts.fill || 'none'
  const stroke = opts.stroke || 'black'
  const width = opts.width || 2
  return `<path d="${d}" fill="${fill}" stroke="${stroke}" stroke-width="${width}"/>`
}

function text(x, y, content, opts = {}) {
  const size = opts.size || 14
  const family = opts.family || 'Comic Sans MS, cursive'
  const fill = opts.fill || 'black'
  const anchor = opts.anchor || 'middle'
  const weight = opts.bold ? ' font-weight="bold"' : ''
  const style = opts.italic ? ' font-style="italic"' : ''
  const transform = opts.rotate ? ` transform="rotate(${opts.rotate}, ${x}, ${y})"` : ''
  return `<text x="${x}" y="${y}" font-size="${size}" font-family="${family}" fill="${fill}" text-anchor="${anchor}"${weight}${style}${transform}>${content}</text>`
}

function group(elements, opts = {}) {
  const transform = opts.transform || ''
  const tfAttr = transform ? ` transform="${transform}"` : ''
  const clipAttr = opts.clipPath ? ` clip-path="url(#${opts.clipPath})"` : ''
  const classAttr = opts.className ? ` class="${opts.className}"` : ''
  const styleAttr = opts.style ? ` style="${opts.style}"` : ''
  const idAttr = opts.id ? ` id="${opts.id}"` : ''
  const inner = elements.join('\n    ')
  return `<g${tfAttr}${clipAttr}${classAttr}${styleAttr}${idAttr}>\n    ${inner}\n  </g>`
}

function clipPath(id, width, height, opts = {}) {
  const x = opts.x || 0
  const y = opts.y || 0
  return `<clipPath id="${id}"><rect x="${x}" y="${y}" width="${width}" height="${height}"/></clipPath>`
}

function translate(tx, ty) {
  return `translate(${tx}, ${ty})`
}

function scale(sx, sy) {
  return `scale(${sx}, ${sy || sx})`
}

function rotate(angle, cx, cy) {
  if (cx !== undefined && cy !== undefined) {
    return `rotate(${angle}, ${cx}, ${cy})`
  }
  return `rotate(${angle})`
}

module.exports = {
  SvgCanvas,
  line,
  circle,
  filledCircle,
  ellipse,
  rect,
  polygon,
  polyline,
  path,
  text,
  group,
  translate,
  scale,
  rotate,
  clipPath,
}
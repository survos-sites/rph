/** Pause only when ordinary playback crosses into another scene. Seeking must stay free. */
export function sceneBoundary(previous, frame, labels, enabled, fps) {
 if (!enabled || previous === null || frame <= previous || frame - previous > fps) return null;
 const before=labels.find(s=>s.start<=previous && previous<s.end);
 const after=labels.find(s=>s.start<=frame && frame<s.end);
 if (!before || (after && before.scene===after.scene)) return null;
 return before.end;
}

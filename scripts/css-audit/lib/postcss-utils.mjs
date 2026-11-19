/** Returns whether a PostCSS rule is a frame selector inside @keyframes. */
export function isKeyframeStep(rule) {
  return rule.parent?.type === 'atrule' && /keyframes$/i.test(rule.parent.name);
}

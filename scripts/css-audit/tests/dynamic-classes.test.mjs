import { test } from 'node:test';
import assert from 'node:assert/strict';
import { collectDynamicClasses, parseComponentEnums } from '../collect-dynamic-classes.mjs';
import { SAFELIST_PATTERNS, STATIC_SAFELIST, buildSafelist } from '../safelist.mjs';
import { sourceHash } from '../lib/theme-css.mjs';

test('parseComponentEnums lê enum inline e em bloco com YAML estruturado', () => {
  const yamlSource = `
props:
  properties:
    tone:
      type: string
      enum: [canvas, raised, green-soft]
    level:
      type: integer
      enum: [1, 2, 3]
    size:
      type: string
      enum:
        - sm
        - md
    nested:
      type: object
      properties:
        ignored:
          type: string
          enum: [x]
`;
  const enums = parseComponentEnums(yamlSource);
  assert.deepEqual(enums.tone, ['canvas', 'raised', 'green-soft']);
  assert.deepEqual(enums.level, ['1', '2', '3']);
  assert.deepEqual(enums.size, ['sm', 'md']);
  assert.equal(enums.nested, undefined);
});

test('parseComponentEnums recusa YAML inválido', () => {
  assert.throws(() => parseComponentEnums('props: [\n  bad'), /Metadata SDC inválido/);
});

test('collectDynamicClasses resolve concatenação, interpolação, alias e UI Styles', () => {
  const { classes, components } = collectDynamicClasses({ root: process.cwd() });
  const set = new Set(classes);
  assert.ok(components >= 80, `componentes: ${components}`);

  // Concatenação com enum direto.
  assert.ok(set.has('aculta-surface--tone-canvas'));
  assert.ok(set.has('aculta-surface--tone-green-soft'));
  assert.ok(set.has('aculta-surface-panel--elevation-floating'));
  assert.ok(set.has('aculta-surface-panel--header-institutional'));

  // Interpolação Twig e alias `set size = ...`.
  assert.ok(set.has('aculta-icon--duotone'));
  assert.ok(set.has('aculta-icon--xl'));
  assert.ok(set.has('data-table--cards'));

  // Concatenação com filtro/default.
  assert.ok(set.has('aculta-auth-dialog--neutral'));
  assert.ok(set.has('aculta-auth-dialog--institutional'));

  // Opções literais de UI Styles.
  assert.ok(set.has('aculta-heading--accent-green'));

  for (const className of classes) {
    assert.match(className, /^[a-zA-Z][\w-]*$/, `classe inválida: ${className}`);
  }
});

test('safelist não usa padrão geral aculta- e cada padrão tem justificativa', () => {
  assert.ok(!SAFELIST_PATTERNS.some((entry) => entry.pattern.source === '^aculta-'));
  assert.ok(!SAFELIST_PATTERNS.some((entry) => entry.pattern.source.includes('aculta')));
  for (const entry of SAFELIST_PATTERNS) {
    assert.ok(entry.justification && entry.justification.length > 10, `sem justificativa: ${entry.pattern}`);
  }
  assert.ok(STATIC_SAFELIST.includes('messages'));
  assert.ok(!STATIC_SAFELIST.includes('aculta-surface'));
  const built = buildSafelist(['aculta-surface--tone-canvas']);
  assert.ok(built.standard.includes('aculta-surface--tone-canvas'));
});

test('sourceHash muda quando o CSS muda e é estável na mesma entrada', () => {
  const a = [{ name: 'a.css', raw: '.a { color: red; }' }];
  const b = [{ name: 'a.css', raw: '.a { color: blue; }' }];
  assert.equal(sourceHash(a), sourceHash([...a]));
  assert.notEqual(sourceHash(a), sourceHash(b));
});

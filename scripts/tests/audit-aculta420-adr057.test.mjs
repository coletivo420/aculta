import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, unlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { auditAdr057 } from '../audit-aculta420-adr057.mjs';

const script = fileURLToPath(new URL('../audit-aculta420-adr057.mjs', import.meta.url));
function fixture() {
  const root = mkdtempSync(join(tmpdir(), 'adr057-'));
  const put = (p, content) => {
    const full = join(root, p);
    mkdirSync(join(full, '..'), { recursive: true });
    writeFileSync(full, content);
    return full;
  };
  put('web/themes/custom/aculta420_v2/aculta420_v2.info.yml', 'version: 1.5.0\nregions:\n  institution: I\n  header: H\n  content: C\n  sidebar_first: S\n  footer: F\n');
  put('config/sync/core.extension.yml', 'module:\n  ui_patterns: 0\n  ui_patterns_library: 0\n  ui_skins: 0\n  ui_styles: 0\n  ui_icons: 0\ntheme:\n  aculta420_v2: 0\n');
  put('web/themes/custom/aculta420_v2/aculta420_v2.libraries.yml', 'global-styling:\n  css: {}\n');
  put('web/themes/custom/aculta420_v2/src/styles/base.css', 'body { margin: 0; }\n');
  put('web/themes/custom/aculta420_v2/components/a/a.component.yml', 'name: A\n');
  const story = put('web/themes/custom/aculta420_v2/components/a/a.default.story.yml', 'name: Default\n');
  put('web/themes/custom/aculta420_v2/templates/page.html.twig', '{{ page.content }}');
  put('config/sync/views.view.example.yml', 'id: example\n');
  put('config/sync/core.entity_view_display.node.page.full.yml', 'id: full\n');
  put('tests/browser/a/routes.json', '{"front":"main"}');
  return { root, put, story };
}
test('inventário determinístico separa PASS_STATIC do Runtime', t => {
  const f = fixture();
  t.after(() => rmSync(f.root, { recursive: true, force: true }));
  const result = auditAdr057(f.root);
  assert.equal(result.static_status, 'PASS_STATIC');
  assert.equal(result.runtime_status, 'DEFERRED');
  assert.equal(result.version, '1.5.0');
  assert.equal(result.inventory.manifests, 1);
  assert.equal(result.inventory.stories, 1);
  assert.equal(result.inventory.twig_templates, 1);
  assert.equal(result.inventory.views_sync, 1);
  assert.equal(result.inventory.displays_sync, 1);
  assert.equal(result.inventory.browser_routes, 1);
});
test('detecta story ausente e CKEditor vedado e falha com --check', t => {
  const f = fixture();
  t.after(() => rmSync(f.root, { recursive: true, force: true }));
  unlinkSync(f.story);
  f.put('config/sync/core.extension.yml', 'module:\n  ui_patterns: 0\n  ui_patterns_library: 0\n  ui_skins: 0\n  ui_styles: 0\n  ui_icons: 0\n  ui_patterns_ckeditor5: 0\n');
  const result = auditAdr057(f.root);
  assert.equal(result.static_status, 'FAIL');
  assert.equal(result.violations.length, 2);
  const cli = spawnSync(process.execPath, [script, '--root', f.root, '--json', '--check'], { encoding: 'utf8' });
  assert.equal(cli.status, 1);
  assert.equal(JSON.parse(cli.stdout).runtime_status, 'DEFERRED');
});
test('argumento --root sem destino falha explicitamente', () => {
  const cli = spawnSync(process.execPath, [script, '--root'], { encoding: 'utf8' });
  assert.equal(cli.status, 2);
  assert.match(cli.stderr, /requer caminho/);
});

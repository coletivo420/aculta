import { test, expect, request } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';

const file = process.env.ACULTA_WIKI_MODERATION_QA_FIXTURE || '';
const fixture = file && existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : null;
const ready = fixture?.isolated === true && typeof fixture?.wikiOrigin === 'string';
const rejected = [301, 302, 303, 307, 308, 401, 403, 404];

function hasState(role) {
  return ready && typeof fixture.states?.[role] === 'string'
    && existsSync(fixture.states[role]);
}
async function get(role, origin, pathname) {
  const context = await request.newContext({
    ...(role ? { storageState: fixture.states[role] } : {}),
  });
  try {
    const response = await context.get(new URL(pathname, origin).toString(), { maxRedirects: 0 });
    return { status: response.status(), body: await response.text() };
  } finally {
    await context.dispose();
  }
}

test.describe('WE Sativa - moderação Wiki isolada do Forum', () => {
  const pages = [
    '/we-sativa/moderacao',
    '/we-sativa/moderacao/propostas',
    '/we-sativa/moderacao/verbetes',
    '/we-sativa/moderacao/denuncias',
  ];

  test('anônimo não acessa fila de moderação', async () => {
    test.skip(!ready, 'Requer ambiente descartável de QA.');
    for (const path of pages) {
      expect(rejected).toContain((await get(null, fixture.wikiOrigin, path)).status);
    }
  });

  test('moderador exclusivamente do Fórum não herda acesso Wiki', async () => {
    test.skip(!hasState('forumModerator'), 'Requer state de papel Forum sem grants Wiki.');
    for (const path of pages) {
      const result = await get('forumModerator', fixture.wikiOrigin, path);
      expect(rejected).toContain(result.status);
      if (fixture.markers?.privateWikiTitle) {
        expect(result.body).not.toContain(fixture.markers.privateWikiTitle);
      }
    }
  });

  test('revisor de propostas vê apenas fila de propostas Wiki', async () => {
    test.skip(!hasState('wikiProposalModerator'), 'Requer revisor Wiki sem grants de Node/CGU.');
    expect((await get('wikiProposalModerator', fixture.wikiOrigin, pages[0])).status).toBe(200);
    expect((await get('wikiProposalModerator', fixture.wikiOrigin, pages[1])).status).toBe(200);
    expect(rejected).toContain((await get('wikiProposalModerator', fixture.wikiOrigin, pages[2])).status);
    expect(rejected).toContain((await get('wikiProposalModerator', fixture.wikiOrigin, pages[3])).status);
  });

  test('editor de verbetes não herda denúncia CGU', async () => {
    test.skip(!hasState('wikiEditor'), 'Requer editor Wiki sem moderação CGU.');
    expect((await get('wikiEditor', fixture.wikiOrigin, pages[2])).status).toBe(200);
    expect(rejected).toContain((await get('wikiEditor', fixture.wikiOrigin, pages[3])).status);
  });

  test('moderador CGU Wiki não herda outras filas', async () => {
    test.skip(!hasState('wikiCguModerator'), 'Requer papel com apenas CGU wiki.');
    expect((await get('wikiCguModerator', fixture.wikiOrigin, pages[3])).status).toBe(200);
    expect(rejected).toContain((await get('wikiCguModerator', fixture.wikiOrigin, pages[1])).status);
    expect(rejected).toContain((await get('wikiCguModerator', fixture.wikiOrigin, pages[2])).status);
  });

  test('parecer de denúncia Forum não é acessível pela Wiki, mesmo com papel combinado', async () => {
    test.skip(!hasState('mixedModerator') || !fixture.ids?.forumReportId, 'Requer denúncia Forum e role combinada.');
    const url = '/we-sativa/moderacao/denuncias/' + encodeURIComponent(fixture.ids.forumReportId) + '/decidir';
    const result = await get('mixedModerator', fixture.wikiOrigin, url);
    expect(rejected).toContain(result.status);
    if (fixture.markers?.forumCguNotes) {
      expect(result.body).not.toContain(fixture.markers.forumCguNotes);
    }
  });

  test('alvo Wiki restrito exige permissão de leitura mesmo para moderador CGU Wiki', async () => {
    test.skip(!hasState('wikiCguModerator') || !fixture.ids?.privateWikiReportId, 'Requer alvo restrito.');
    const url = '/we-sativa/moderacao/denuncias/' + encodeURIComponent(fixture.ids.privateWikiReportId) + '/decidir';
    const result = await get('wikiCguModerator', fixture.wikiOrigin, url);
    expect(rejected).toContain(result.status);
    if (fixture.markers?.privateWikiTitle) {
      expect(result.body).not.toContain(fixture.markers.privateWikiTitle);
    }
  });

  test('domínio Fórum não hospeda UI de moderação Wiki', async () => {
    test.skip(!hasState('wikiCguModerator') || !fixture.forumOrigin, 'Requer domains Wiki e Forum.');
    expect(rejected).toContain((await get('wikiCguModerator', fixture.forumOrigin, pages[3])).status);
  });

  test('CGU Wiki mostra formulário apenas com alvo acessível', async () => {
    test.skip(!hasState('wikiCguModerator') || !fixture.ids?.visibleWikiReportId, 'Requer caso CGU sintético acessível.');
    const path = '/we-sativa/moderacao/denuncias/' + encodeURIComponent(fixture.ids.visibleWikiReportId) + '/decidir';
    const result = await get('wikiCguModerator', fixture.wikiOrigin, path);
    expect(result.status).toBe(200);
    expect(result.body).toContain('aculta_cgu_report_decision_form');
  });
});

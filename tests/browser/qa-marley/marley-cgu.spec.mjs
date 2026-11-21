/**
 * Studio Marley / CGU browser acceptance on a dedicated, seeded QA site.
 *
 * Authentication uses pre-established Playwright storageState files. No
 * passwords, credentials, fixture creation, Drupal mutation or site URLs are
 * stored in the repository.
 *
 * Non-mutating checks require ACULTA_QA_MARLEY_FIXTURE pointing to a local
 * JSON manifest. Write checks additionally require the explicit two-key guard:
 * manifest.isolated=true and ACULTA_QA_MARLEY_ALLOW_MUTATION=yes.
 */
import { expect, request, test } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';

const fixturePath = process.env.ACULTA_QA_MARLEY_FIXTURE || '';
const fixture = fixturePath && existsSync(fixturePath)
  ? JSON.parse(readFileSync(fixturePath, 'utf8'))
  : null;

const origin = fixture?.accountOrigin || '';
const fixturesReady = Boolean(fixture?.isolated === true && origin);
const mutationsEnabled = fixturesReady && process.env.ACULTA_QA_MARLEY_ALLOW_MUTATION === 'yes';
const forbidden = [301, 302, 303, 307, 308, 401, 403, 404];

function accountReady(name) {
  const path = fixture?.states?.[name];
  return fixturesReady && typeof path === 'string' && existsSync(path);
}

function url(path) {
  return new URL(path, origin).toString();
}

function reportPath(id) {
  return '/marley/forum/moderacao/denuncias/' + encodeURIComponent(String(id)) + '/decidir';
}

async function withAccount(name, callback) {
  const ctx = await request.newContext({ storageState: fixture.states[name] });
  try {
    return await callback(ctx);
  } finally {
    await ctx.dispose();
  }
}

async function status(name, path) {
  return withAccount(name, async (client) => {
    const response = await client.get(url(path), { maxRedirects: 0 });
    return response.status();
  });
}

test.describe('Marley / CGU - acesso por escopo e domínio da Conta', () => {
  test('anônimo não recebe a fila nem um relatório direto', async () => {
    test.skip(!fixturesReady || !fixture.reports?.publicForum, 'Exige ambiente isolado com relatório Fórum.');
    const ctx = await request.newContext();
    try {
      for (const path of ['/marley/forum/moderacao/denuncias', reportPath(fixture.reports.publicForum)]) {
        const response = await ctx.get(url(path), { maxRedirects: 0 });
        expect(forbidden, 'anônimo não deve ler ' + path).toContain(response.status());
      }
    } finally {
      await ctx.dispose();
    }
  });

  for (const name of ['common', 'revista_only', 'wiki_only', 'revista_wiki']) {
    test(name + ' não acessa a fila Fórum, mesmo com papéis combinados', async () => {
      test.skip(!accountReady(name), 'Estado de autenticação de QA indisponível: ' + name);
      expect(forbidden).toContain(await status(name, '/marley/forum/moderacao/denuncias'));
    });
  }

  for (const name of ['forum_only', 'forum_wiki', 'forum_revista']) {
    test(name + ' acessa fila Fórum sem obter backend administrativo', async () => {
      test.skip(!accountReady(name), 'Estado de autenticação de QA indisponível: ' + name);
      expect(await status(name, '/marley/forum/moderacao/denuncias')).toBe(200);
      for (const path of ['/painel-administrativo/', '/admin/']) {
        expect(forbidden, 'moderador jamais recebe backend em ' + path).toContain(await status(name, path));
      }
    });
  }

  test('sessão com Fórum+Wiki não consegue decidir denúncia Revista ou Wiki pelo Marley', async () => {
    test.skip(!accountReady('forum_wiki') || !fixture.reports?.revista || !fixture.reports?.wiki,
      'Exige estados Fórum+Wiki e denúncias de outros purposes.');
    for (const id of [fixture.reports.revista, fixture.reports.wiki]) {
      expect(forbidden).toContain(await status('forum_wiki', reportPath(id)));
    }
  });

  test('Fórum+Revista não herda acesso a casos Wiki por essa rota', async () => {
    test.skip(!accountReady('forum_revista') || !fixture.reports?.wiki, 'Exige perfil composto e denúncia Wiki.');
    expect(forbidden).toContain(await status('forum_revista', reportPath(fixture.reports.wiki)));
  });

  test('moderador Fórum não lê relatório cujo alvo está oculto por Entity Access', async () => {
    test.skip(!accountReady('forum_only') || !fixture.reports?.privateForum,
      'Exige relatório de tópico Fórum privado em fixture isolada.');
    expect(forbidden).toContain(await status('forum_only', reportPath(fixture.reports.privateForum)));
  });

  test('a fila não expõe título privado nem identidade do denunciante', async () => {
    test.skip(!accountReady('forum_wiki') || !fixture.markers?.privateTitle ||
      !fixture.markers?.reporterIdentity, 'Exige marcadores sintéticos de privacidade.');
    await withAccount('forum_wiki', async (client) => {
      const response = await client.get(url('/marley/forum/moderacao/denuncias'));
      expect(response.status()).toBe(200);
      const html = await response.text();
      expect(html).not.toContain(fixture.markers.privateTitle);
      expect(html).not.toContain(fixture.markers.reporterIdentity);
      expect(html).not.toContain('/painel-administrativo/moderacao/denuncias');
    });
  });

  test('o link de um tópico autorizado aponta ao domínio real do Fórum', async () => {
    test.skip(!accountReady('forum_only') || !fixture.forumOrigin ||
      !fixture.markers?.publicTitle, 'Exige domínio Fórum e tópico público sinalizado.');
    await withAccount('forum_only', async (client) => {
      const response = await client.get(url('/marley/forum/moderacao/denuncias'));
      expect(response.status()).toBe(200);
      const html = await response.text();
      expect(html).toContain(fixture.markers.publicTitle);
      expect(html).toContain(new URL(fixture.forumOrigin).host);
    });
  });
});

test.describe('Marley / CGU - Form API e decisões auditáveis', () => {
  test('formulário de decisão exige moderador Fórum e nunca exibe UID do denunciante', async ({ browser }) => {
    test.skip(!accountReady('forum_only') || !fixture.reports?.publicForum,
      'Exige conta moderadora e denúncia pública do Fórum.');
    const context = await browser.newContext({ storageState: fixture.states.forum_only });
    try {
      const page = await context.newPage();
      const response = await page.goto(url(reportPath(fixture.reports.publicForum)), { waitUntil: 'domcontentloaded' });
      expect(response?.status()).toBe(200);
      await expect(page.locator('form input[name="form_id"][value="aculta_cgu_report_decision_form"]')).toHaveCount(1);
      await expect(page.getByText('Parecer da moderação')).toBeVisible();
      if (fixture.markers?.reporterIdentity) {
        await expect(page.locator('body')).not.toContainText(fixture.markers.reporterIdentity);
      }
      expect(page.url()).not.toContain('/painel-administrativo/');
    } finally {
      await context.close();
    }
  });

  test('POST inválido sem token não registra decisão', async () => {
    test.skip(!mutationsEnabled || !accountReady('forum_only') || !fixture.reports?.pendingWrite,
      'Somente fixture isolada com ACULTA_QA_MARLEY_ALLOW_MUTATION=yes.');
    const path = reportPath(fixture.reports.pendingWrite);
    await withAccount('forum_only', async (client) => {
      const response = await client.post(url(path), {
        form: {
          form_id: 'aculta_cgu_report_decision_form',
          form_build_id: 'invalid-or-expired',
          form_token: 'invalid-csrf-token',
          decision: 'procedente',
          decision_notes: 'Tentativa inválida - teste controlado.',
          op: 'Registrar decisão',
        },
        maxRedirects: 0,
      });
      // Drupal Form API may return HTTP 200 with an error message after a
      // malformed POST. The authoritative assertion is that status remains
      // pending on a fresh GET (not merely the HTTP response status).
      expect(response.status()).toBeLessThan(500);
      const fresh = await client.get(url(path));
      expect(fresh.status()).toBe(200);
      const html = await fresh.text();
      expect(html).toContain('aculta_cgu_report_decision_form');
      expect(html).toContain('decision_notes');
      expect(html).not.toContain('Decisão proferida');
    });
  });

  test('decisão válida via Browser grava parecer e não despublica tópico', async ({ browser }) => {
    test.skip(!mutationsEnabled || !accountReady('forum_only') ||
      !fixture.reports?.pendingWrite || !fixture.targets?.pendingWriteUrl,
      'Exige ambiente exclusivamente de teste e relatório descartável para escrita.');
    const context = await browser.newContext({ storageState: fixture.states.forum_only });
    try {
      const page = await context.newPage();
      const target = fixture.targets.pendingWriteUrl;
      const before = await page.goto(target, { waitUntil: 'domcontentloaded' });
      expect(before?.status()).toBe(200);

      await page.goto(url(reportPath(fixture.reports.pendingWrite)), { waitUntil: 'domcontentloaded' });
      await page.locator('input[name="decision"][value="improcedente"]').check();
      const marker = 'QA Marley ' + fixture.reports.pendingWrite;
      await page.locator('textarea[name="decision_notes"]').fill(marker);
      await page.getByRole('button', { name: 'Registrar decisão' }).click();
      await expect(page).toHaveURL(/\/marley\/forum\/moderacao\/denuncias(?:\?|$)/);

      // The CGU decision must never take a publication action.
      const after = await page.goto(target, { waitUntil: 'domcontentloaded' });
      expect(after?.status()).toBe(200);
      await page.goto(url(reportPath(fixture.reports.pendingWrite)), { waitUntil: 'domcontentloaded' });
      await expect(page.getByText('Decisão proferida')).toBeVisible();
      await expect(page.getByText(marker)).toBeVisible();
      await expect(page.locator('textarea[name="decision_notes"]')).toHaveCount(0);
    } finally {
      await context.close();
    }
  });
});

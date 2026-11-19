// Testes funcionais de controle de acesso (frente de Fase 9). Anônimo, sem contas novas.
// Contrato: rotas administrativas são restritas ao domínio MAIN. Anônimo não pode
// receber a página (200). Aceito: redirecionamento para login ou negação (401/403/404).
// Não transforma 403/404 esperado em sucesso genérico: a asserção é o contrato acima.
import { expect, test } from '@playwright/test';

const origin = process.env.ACULTA_SITE_ORIGIN || '';
const ADMIN_PATHS = ['/painel-administrativo', '/painel-administrativo/'];
const DENIED_OR_REDIRECT = [301, 302, 303, 307, 308, 401, 403, 404];

test.describe('acesso anônimo a rotas administrativas (MAIN)', () => {
  for (const path of ADMIN_PATHS) {
    test(`anônimo não recebe 200 em ${path}`, async ({ request }) => {
      test.skip(!origin, 'defina ACULTA_SITE_ORIGIN para o teste funcional de acesso');
      const response = await request.get(new URL(path, origin).toString(), { maxRedirects: 0 });
      expect(DENIED_OR_REDIRECT, `status inesperado ${response.status()} em ${path}`).toContain(response.status());
    });
  }
});

// Sessão autenticada com contas de teste (secrets/ via auth-login.mjs). Nunca imprime credenciais.
// Sem a conta ou sem origem, o teste fica skipped (não é PASS).
import { login } from './auth-login.mjs';

test.describe('sessão com contas de teste', () => {
  test('conta comum entra e não acessa área administrativa', async ({ page }) => {
    test.skip(!origin, 'defina ACULTA_SITE_ORIGIN para o login de teste');
    await login(page, origin, 'teste_comum');
    // Prova de sessão: anônimo é redirecionado para login em /user; a conta comum não termina lá.
    await page.goto(new URL('/user', origin).toString(), { waitUntil: 'domcontentloaded' });
    expect(new URL(page.url()).pathname, 'sessão comum não deve terminar na página de login').not.toMatch(/entrar|user\/login/);
    const response = await page.goto(new URL('/painel-administrativo', origin).toString(), { waitUntil: 'domcontentloaded' });
    expect([301, 302, 303, 307, 308, 401, 403, 404], `status ${response?.status()} para conta comum`).toContain(response?.status() ?? 0);
  });

  test('conta administrativa acessa área administrativa no domínio MAIN', async ({ page }) => {
    test.skip(!origin, 'defina ACULTA_SITE_ORIGIN para o login de teste');
    await login(page, origin, 'teste_administrativo');
    const response = await page.goto(new URL('/painel-administrativo', origin).toString(), { waitUntil: 'domcontentloaded' });
    expect(response?.status() ?? 0, 'conta administrativa deve receber 200').toBe(200);
  });
});

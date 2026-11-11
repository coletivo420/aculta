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

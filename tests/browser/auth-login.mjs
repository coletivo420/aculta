// Login de teste compartilhado pelas frentes. Lê as credenciais de secrets/ na
// raiz do repositório, em tempo de execução. Nunca imprime valores.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';

// secrets/ fica na raiz do checkout principal, ignorada pelo Git. Em worktree,
// o diretório comum do Git aponta para o checkout principal; o caminho é calculado
// em tempo de execução, sem caminho absoluto no código.
function resolveSecretsDir() {
  if (process.env.ACULTA_SECRETS_DIR) return process.env.ACULTA_SECRETS_DIR;
  const here = dirname(fileURLToPath(import.meta.url));
  const commonDir = execFileSync('git', ['rev-parse', '--path-format=absolute', '--git-common-dir'], { cwd: here, encoding: 'utf8' }).trim();
  return join(dirname(commonDir), 'secrets');
}

const secretsDir = resolveSecretsDir();

// Contas conhecidas. Chaves são os nomes de variável definidos nos arquivos de secrets/.
export const ACCOUNTS = {
  teste_comum: { file: 'teste_comum.env', user: 'TESTE_COMUM_USER', pass: 'TESTE_COMUM_PASSWORD' },
  teste_administrativo: { file: 'teste_administrativo.env', user: 'username', pass: 'password' },
};

export function loadAccount(name) {
  const account = ACCOUNTS[name];
  if (!account) throw new Error(`conta de teste desconhecida: ${name}`);
  const values = {};
  for (const raw of readFileSync(join(secretsDir, account.file), 'utf8').split('\n')) {
    const line = raw.trim();
    if (!line || line.startsWith('#')) continue;
    const eq = line.indexOf('=');
    if (eq < 0) continue;
    values[line.slice(0, eq).trim()] = line.slice(eq + 1).trim().replace(/^['"]|['"]$/g, '');
  }
  const user = values[account.user];
  const pass = values[account.pass];
  if (!user || !pass) throw new Error(`${account.file}: chaves ${account.user}/${account.pass} ausentes`);
  return { user, pass };
}

// Entra pela janela de login do portal (/entrar/janela, formulário aculta-login-popup).
// /entrar responde 404 neste portal. O envio é AJAX: a URL não muda. Por isso o helper só
// confirma que o envio não gerou erro visível; o efeito da sessão é verificado pelos testes
// funcionais (conta administrativa recebe 200 em /painel-administrativo; conta comum não recebe).
export async function login(page, host, accountName) {
  const { user, pass } = loadAccount(accountName);
  await page.goto(new URL('/entrar/janela', host).toString(), { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="name"]', user);
  await page.fill('input[name="pass"]', pass);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.locator('input[type="submit"], button[type="submit"]').first().click(),
  ]);
  const errors = await page.locator('[role="alert"], .messages--error, .form-item--error-message').count();
  expect(errors, `envio do login de ${accountName} mostrou erro visível`).toBe(0);
}

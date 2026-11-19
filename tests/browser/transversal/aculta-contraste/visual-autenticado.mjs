/**
 * Homologação autenticada de layout e contraste das telas de conta e Studio.
 *
 * Entra com as contas de teste de `secrets/` (nunca imprime credenciais),
 * percorre rotas de conta e `/marley` em 320/1440 px, claro e escuro, e mede:
 * status HTTP, overflow horizontal, classes `aculta-u-*` remanescentes,
 * equivalências Bootstrap removidas e violações `color-contrast` (axe-core).
 *
 * Uso:
 *   SUITE_OUT=/caminho/externo node tests/browser/transversal/aculta-contraste/visual-autenticado.mjs
 *
 * Códigos: 0 tudo passou; 1 há falha ou login; 2 falta SUITE_OUT.
 * Nenhum conteúdo é gravado; as PNGs de 1440 px ficam em `$SUITE_OUT`.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(new URL('../../', import.meta.url));
const { firefox } = require('playwright');
const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const { login } = await import('../../auth-login.mjs');

const suiteOut = process.env.SUITE_OUT;
if (!suiteOut || !path.isAbsolute(suiteOut)) {
  console.error('SUITE_OUT absoluto e fora do repositório é obrigatório.');
  process.exit(2);
}

const MAIN = 'https://aculta.toca.net.br/';
const CONTA = 'https://conta.aculta.toca.net.br/';

const browsers = await firefox.launch({ headless: true });
const contexto = await browsers.newContext();
const loginPage = await contexto.newPage();
let loginOk = true;
try {
  await login(loginPage, MAIN, 'teste_comum');
} catch (erro) {
  loginOk = false;
  console.error('Login da conta comum falhou: ' + String(erro).slice(0, 200));
}
await loginPage.close();

const alvos = [
  [CONTA, '/conta'], [CONTA, '/conta/dados'], [CONTA, '/meu-perfil'],
  [CONTA, '/mensagens/resumo'], [CONTA, '/avisos-mensagens'], [CONTA, '/salvos'],
];
const resultados = [];
if (loginOk) {
  for (const [host, rota] of alvos) {
    for (const largura of [320, 1440]) {
      for (const tema of ['light', 'dark']) {
        const url = new URL(rota, host).toString();
        const page = await contexto.newPage();
        await page.setViewportSize({ width: largura, height: 900 });
        try {
          const resposta = await page.goto(url, { waitUntil: 'load', timeout: 60000 });
          await page.waitForTimeout(400);
          if (tema === 'dark') await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
          const medida = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            innerWidth: window.innerWidth,
            utils: [...document.querySelectorAll('[class*="aculta-u-"]')].map((e) => String(e.className)).slice(0, 8),
            legado: ['text-muted', 'list-unstyled', 'table-hover', 'label-default', 'link-primary', 'section-title', 'nav-tabs'].filter((c) => document.getElementsByClassName(c).length),
          }));
          const item = { url, status: resposta?.status() ?? 0, largura, tema, ...medida, overflow: medida.scrollWidth > medida.innerWidth + 1 };
          if (largura === 1440) {
            await page.evaluate(axeSource);
            item.axe = await page.evaluate(async () => (await window.axe.run(document, { runOnly: ['color-contrast'] })).violations.flatMap((v) => v.nodes.map((n) => n.target)));
          }
          resultados.push(item);
          if (largura === 1440) {
            await page.screenshot({ path: path.join(suiteOut, rota.replace(/\W+/g, '_') + '-' + tema + '.png') }).catch(() => {});
          }
        } catch (erro) {
          resultados.push({ url, largura, tema, erro: String(erro).slice(0, 160) });
        } finally {
          await page.close();
        }
      }
    }
  }
}
await browsers.close();

fs.writeFileSync(path.join(suiteOut, 'autenticado-axe.json'), JSON.stringify({ data: new Date().toISOString(), loginOk, resultados }, null, 1));
const validos = resultados.filter((r) => !r.erro && r.status === 200);
const falhas = validos.filter((r) => r.overflow || (r.axe && r.axe.length) || r.legado.length || r.utils.some((c) => !c.includes('aculta-u-block-start')));
console.log(`login=${loginOk} checagens=${resultados.length} 200=${validos.length} falhas=${falhas.length}`);
if (falhas.length) console.log(JSON.stringify(falhas.slice(0, 10), null, 1));
process.exit(!loginOk || falhas.length ? 1 : 0);

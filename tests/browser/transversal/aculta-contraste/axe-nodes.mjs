/**
 * Auditoria de contraste (WCAG 2.2 AA) dos domínios ACULTA.
 *
 * Mede `color-contrast` com axe-core no Firefox do Playwright, nos modos
 * claro e escuro, e devolve o seletor, o HTML e o par de cores de cada nó
 * reprovado. Serve para antes/depois de correções de UI Skins e tokens.
 *
 * Uso:
 *   SUITE_OUT=/caminho/externo node tests/browser/transversal/aculta-contraste/axe-nodes.mjs
 *   SUITE_OUT=... node ... https://aculta.toca.net.br/ https://growroom420.aculta.toca.net.br/categorias
 *
 * Sem argumentos, cobre a matriz padrão abaixo. `SUITE_OUT` é obrigatório e
 * deve ficar fora do repositório; o JSON de resultado é gravado nele.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(new URL('../../', import.meta.url));
const { firefox } = require('playwright');
const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

const matrizPadrao = [
  'https://aculta.toca.net.br/',
  'https://conta.aculta.toca.net.br/',
  'https://revista.aculta.toca.net.br/',
  'https://wiki420.aculta.toca.net.br/',
  'https://growroom420.aculta.toca.net.br/',
  'https://growroom420.aculta.toca.net.br/categorias',
  'https://loja.aculta.toca.net.br/',
  'https://cursos.aculta.toca.net.br/',
  'https://links.aculta.toca.net.br/',
  'https://apoio.aculta.toca.net.br/',
  'https://eventos.aculta.toca.net.br/',
];

const suiteOut = process.env.SUITE_OUT;
if (!suiteOut || !path.isAbsolute(suiteOut)) {
  console.error('SUITE_OUT absoluto e fora do repositório é obrigatório.');
  process.exit(2);
}

const urls = process.argv.slice(2);
const alvos = urls.length ? urls : matrizPadrao;
const resultados = [];

const browser = await firefox.launch({ headless: true });
for (const url of alvos) {
  for (const tema of ['light', 'dark']) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    try {
      await page.goto(url, { waitUntil: 'load', timeout: 60000 });
      if (tema === 'dark') {
        await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
      }
      await page.evaluate(axeSource);
      const violacoes = await page.evaluate(async () => {
        const r = await window.axe.run(document, { runOnly: ['color-contrast'] });
        return r.violations.flatMap((v) => v.nodes.map((n) => ({
          regra: v.id,
          impacto: v.impact,
          alvo: n.target,
          html: n.html.slice(0, 220),
          detalhe: n.failureSummary,
        })));
      });
      resultados.push({ url, tema, violacoes });
      console.log(`${violacoes.length === 0 ? 'PASS' : 'FAIL'} ${url} [${tema}] ${violacoes.length}`);
    } catch (erro) {
      resultados.push({ url, tema, erro: String(erro).slice(0, 400) });
      console.error(`ERRO ${url} [${tema}] ${erro}`);
    } finally {
      await page.close();
    }
  }
}
await browser.close();

const saida = path.join(suiteOut, 'contraste-axe.json');
fs.writeFileSync(saida, JSON.stringify({ data: new Date().toISOString(), resultados }, null, 1));
console.log(`Resultado: ${saida}`);
process.exit(resultados.some((r) => r.erro || (r.violacoes && r.violacoes.length)) ? 1 : 0);

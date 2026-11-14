/**
 * Rotas por papel: contribuidor Wiki e educador LMS.
 *
 * Entra com as contas de teste `teste_wiki` (content_editor) e
 * `teste_educador` (lms_teacher), de `secrets/`, e mede as rotas restritas
 * em claro/escuro e 320/1440 CSS px: status, overflow, classes
 * `aculta-u-*` remanescentes, equivalências Bootstrap e axe color-contrast.
 *
 * Uso:
 *   SUITE_OUT=/caminho/externo node tests/browser/transversal/aculta-papeis/papeis.mjs
 *
 * Códigos: 0 passou; 1 falhou; 2 pré-requisito ausente.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { login } from '../../auth-login.mjs';

const require = createRequire(new URL('../../', import.meta.url));
const { firefox } = require('playwright');
const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

const suiteOut = process.env.SUITE_OUT;
if (!suiteOut || !path.isAbsolute(suiteOut)) {
  console.error('SUITE_OUT absoluto e fora do repositório é obrigatório.');
  process.exit(2);
}
fs.mkdirSync(suiteOut, { recursive: true, mode: 0o700 });

const LARGURAS = (process.env.PAPEIS_WIDTHS || '320,1440').split(',').map(Number);
const LEGADO = ['text-muted', 'text-center', 'small', 'lead', 'list-unstyled', 'list-group', 'table-hover', 'label-default', 'link-primary', 'section-title', 'nav-tabs', 'pager-next', 'pager-prev', 'container-inline'];
const casos = [
  {
    conta: 'teste_wiki',
    host: 'https://wiki420.aculta.toca.net.br',
    rotas: ['/we-sativa', '/we-sativa/verbetes/novo'],
  },
  {
    conta: 'teste_educador',
    host: 'https://cursos.aculta.toca.net.br',
    rotas: ['/ganjalab', '/ganjalab/educador'],
  },
];

const resultados = [];
const browser = await firefox.launch({ headless: true });
try {
  for (const caso of casos) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    try {
      await login(page, caso.host, caso.conta);
    } catch (erro) {
      resultados.push({ conta: caso.conta, erro: `login: ${String(erro).split('\n')[0].slice(0, 200)}` });
      console.error(`ERRO login ${caso.conta}`);
      await page.close();
      continue;
    }
    for (const rota of caso.rotas) {
      for (const tema of ['light', 'dark']) {
        for (const largura of LARGURAS) {
          const falhas = [];
          try {
            await page.setViewportSize({ width: largura, height: 900 });
            const resp = await page.goto(new URL(rota, caso.host).toString(), { waitUntil: 'load', timeout: 60000 });
            if (tema === 'dark') {
              await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
            }
            const estado = await page.evaluate((legado) => ({
              overflow: document.documentElement.scrollWidth > innerWidth + 1,
              utils: [...document.querySelectorAll('[class*="aculta-u-"]')]
                .map((e) => e.className).filter((c) => !c.includes('aculta-u-block-start')).slice(0, 5),
              legado: legado.filter((c) => document.getElementsByClassName(c).length),
            }), LEGADO);
            if (resp?.status() !== 200) falhas.push(`status ${resp?.status()}`);
            if (estado.overflow) falhas.push('overflow horizontal');
            if (estado.utils.length) falhas.push(`aculta-u-*: ${estado.utils.join(' | ')}`);
            if (estado.legado.length) falhas.push(`classes legadas: ${estado.legado.join(', ')}`);
            await page.evaluate(axeSource);
            const violacoes = await page.evaluate(async () => {
              const r = await window.axe.run(document, { runOnly: ['color-contrast'] });
              return r.violations.flatMap((v) => v.nodes.map((n) => n.target.join(' ')));
            });
            if (violacoes.length) falhas.push(`axe: ${violacoes.slice(0, 3).join(' | ')}`);
            resultados.push({ conta: caso.conta, rota, tema, largura, status: resp?.status() ?? null, falhas });
            console.log(`${falhas.length === 0 ? 'PASS' : 'FAIL'} ${caso.conta} ${rota} [${tema} ${largura}px]${falhas.length ? ' ' + falhas.join('; ') : ''}`);
          } catch (erro) {
            resultados.push({ conta: caso.conta, rota, tema, largura, erro: String(erro).split('\n')[0].slice(0, 200) });
            console.error(`ERRO ${caso.conta} ${rota} [${tema} ${largura}px] ${erro}`);
          }
        }
      }
    }
    await page.close();
  }
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(suiteOut, 'papeis.json'), JSON.stringify({ data: new Date().toISOString(), resultados }, null, 1));
const falhou = resultados.some((r) => r.erro || (r.falhas && r.falhas.length));
console.log(`Resultado: ${path.join(suiteOut, 'papeis.json')}`);
process.exit(falhou ? 1 : 0);

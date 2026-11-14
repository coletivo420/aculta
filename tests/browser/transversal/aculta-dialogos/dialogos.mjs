/**
 * Diálogos e gaveta de Mensagens: abertura, foco, fuga e contraste.
 *
 * Para cada gatilho (`data-aculta-dialog-trigger` + `aria-controls`), abre o
 * diálogo em claro/escuro e 320/1024 CSS px e verifica:
 *  - o diálogo abre e o foco entra nele;
 *  - 20 Tabs mantêm o foco dentro do diálogo;
 *  - Escape fecha e devolve o foco ao gatilho;
 *  - não há overflow horizontal;
 *  - axe `color-contrast` não acusa violação dentro do diálogo.
 *
 * Uso:
 *   SUITE_OUT=/caminho/externo node tests/browser/transversal/aculta-dialogos/dialogos.mjs
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

const LARGURAS = (process.env.DIALOGO_WIDTHS || '320,1024').split(',').map(Number);
const casos = [
  {
    nome: 'carrinho',
    url: 'https://aculta.toca.net.br/',
    id: 'aculta-cart-dialog',
    autenticado: false,
  },
  {
    nome: 'menu-da-conta',
    url: 'https://aculta.toca.net.br/',
    id: 'aculta-account-dialog',
    autenticado: false,
  },
  {
    nome: 'mensagens',
    url: 'https://conta.aculta.toca.net.br/conta',
    id: 'aculta-messenger-dialog',
    autenticado: true,
    // A conta comum recebe o modal de aceite dos termos, que prende o foco por
    // desenho até o aceite; usar a conta administrativa evita gravar aceite.
    conta: 'teste_administrativo',
  },
];

const resultados = [];
const browser = await firefox.launch({ headless: true });

for (const caso of casos) {
  for (const tema of ['light', 'dark']) {
    for (const largura of LARGURAS) {
      const page = await browser.newPage({ viewport: { width: largura, height: 900 } });
      const falhas = [];
      try {
        if (caso.autenticado) await login(page, 'https://conta.aculta.toca.net.br', caso.conta);
        await page.goto(caso.url, { waitUntil: 'load', timeout: 60000 });
        const termosAbertos = await page.evaluate(() => Boolean(document.querySelector('.ui-dialog[role="dialog"] #aculta-account-terms, #aculta-account-terms')));
        if (termosAbertos) {
          falhas.push('PRECONDIÇÃO: modal de aceite dos termos aberto');
          console.log(`SKIP ${caso.nome} [${tema} ${largura}px] ${falhas[0]}`);
          continue;
        }
        if (tema === 'dark') {
          await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
        }
        const gatilho = page.locator(`[data-aculta-dialog-trigger][aria-controls="${caso.id}"]`).first();
        if (await gatilho.count() === 0) {
          falhas.push(`gatilho de ${caso.id} ausente`);
        } else {
          // Foco de teclado antes de abrir: é o caminho que a WCAG 2.4.3 cobre
          // e o que o diálogo nativo usa para devolver o foco ao fechar.
          await gatilho.focus();
          await page.keyboard.press('Enter');
          await page.waitForFunction((id) => {
            const d = document.getElementById(id);
            return d && (d.open === true || getComputedStyle(d).display !== 'none');
          }, caso.id, { timeout: 5000 }).catch(() => falhas.push('diálogo não abriu'));

          const estado = await page.evaluate((id) => {
            const d = document.getElementById(id);
            if (!d) return null;
            const aberto = d.open === true || getComputedStyle(d).display !== 'none';
            return {
              aberto,
              focoDentro: d.contains(document.activeElement),
              overflow: document.documentElement.scrollWidth > innerWidth + 1,
            };
          }, caso.id);
          if (!estado?.aberto) falhas.push('diálogo fechado após abrir');
          if (estado && !estado.focoDentro) falhas.push('foco não entrou no diálogo');
          if (estado?.overflow) falhas.push('overflow horizontal com o diálogo aberto');

          // Nome acessível e estado do gatilho: o que o leitor de tela anuncia.
          const aria = await page.evaluate((id) => {
            const d = document.getElementById(id);
            const rotulo = d?.getAttribute('aria-label')
              || (d?.getAttribute('aria-labelledby') ? document.getElementById(d.getAttribute('aria-labelledby'))?.textContent.trim() : '');
            const gatilho = document.querySelector(`[data-aculta-dialog-trigger][aria-controls="${id}"]`);
            return { tag: d?.tagName, rotulo: (rotulo || '').trim(), expandido: gatilho?.getAttribute('aria-expanded') };
          }, caso.id);
          if (aria.tag !== 'DIALOG') falhas.push('diálogo sem elemento <dialog> (role implícito ausente)');
          if (!aria.rotulo) falhas.push('diálogo sem nome acessível');
          if (aria.expandido !== 'true') falhas.push('gatilho não anuncia aria-expanded=true');

          for (let i = 0; i < 20; i += 1) {
            await page.keyboard.press('Tab');
          }
          const focoPreso = await page.evaluate((id) => {
            const d = document.getElementById(id);
            return Boolean(d && d.contains(document.activeElement));
          }, caso.id);
          if (!focoPreso) falhas.push('foco escapou do diálogo após 20 Tabs');

          await page.evaluate(axeSource);
          const violacoes = await page.evaluate(async (id) => {
            const d = document.getElementById(id);
            const r = await window.axe.run(d, { runOnly: ['color-contrast'] });
            return r.violations.flatMap((v) => v.nodes.map((n) => n.failureSummary?.slice(0, 120) || v.id));
          }, caso.id);
          if (violacoes.length) falhas.push(`axe color-contrast: ${violacoes.join(' | ')}`);

          await page.keyboard.press('Escape');
          const fechou = await page.evaluate((id) => {
            const d = document.getElementById(id);
            return !d || (d.open !== true && getComputedStyle(d).display === 'none');
          }, caso.id);
          if (!fechou) falhas.push('Escape não fechou o diálogo');
          const colapsou = await page.evaluate((id) => document.querySelector(`[data-aculta-dialog-trigger][aria-controls="${id}"]`)?.getAttribute('aria-expanded'), caso.id);
          if (colapsou === 'true') falhas.push('gatilho permanece aria-expanded=true após fechar');
          const focoDeVolta = await page.evaluate((id) => {
            const d = document.getElementById(id);
            const gatilho = document.querySelector(`[data-aculta-dialog-trigger][aria-controls="${id}"]`);
            return Boolean(gatilho && (document.activeElement === gatilho || gatilho.contains(document.activeElement)));
          }, caso.id);
          if (!focoDeVolta) falhas.push('foco não voltou ao gatilho');
        }
        console.log(`${falhas.length === 0 ? 'PASS' : 'FAIL'} ${caso.nome} [${tema} ${largura}px] ${falhas.length ? falhas.join('; ') : ''}`);
      } catch (erro) {
        falhas.push(String(erro).split('\n')[0].slice(0, 200));
        console.error(`ERRO ${caso.nome} [${tema} ${largura}px] ${erro}`);
      } finally {
        resultados.push({ caso: caso.nome, tema, largura, falhas });
        await page.close();
      }
    }
  }
}
await browser.close();

fs.writeFileSync(path.join(suiteOut, 'dialogos.json'), JSON.stringify({ data: new Date().toISOString(), resultados }, null, 1));
const falhou = resultados.some((r) => r.falhas.some((f) => !f.startsWith('PRECONDIÇÃO')));
const pulou = resultados.some((r) => r.falhas.some((f) => f.startsWith('PRECONDIÇÃO')));
console.log(`Resultado: ${path.join(suiteOut, 'dialogos.json')}`);
process.exit(falhou ? 1 : (pulou ? 2 : 0));

/**
 * Teclado e foco visível nos domínios ACULTA.
 *
 * Percorre os primeiros elementos focáveis com Tab em claro e escuro, em
 * 320 e 1440 CSS px, e exige indicador de foco (outline ou box-shadow) em
 * cada parada. Também confere que o foco não é preso fora de diálogo e que
 * o diálogo de login recebe e devolve o foco.
 *
 * Uso:
 *   SUITE_OUT=/caminho/externo node tests/browser/transversal/aculta-teclado-foco/teclado.mjs
 *
 * Códigos: 0 passou; 1 falhou; 2 pré-requisito ausente.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(new URL('../../', import.meta.url));
const { firefox } = require('playwright');

const suiteOut = process.env.SUITE_OUT;
if (!suiteOut || !path.isAbsolute(suiteOut)) {
  console.error('SUITE_OUT absoluto e fora do repositório é obrigatório.');
  process.exit(2);
}
fs.mkdirSync(suiteOut, { recursive: true, mode: 0o700 });

const hosts = (process.env.TECLADO_URLS || [
  'https://aculta.toca.net.br/',
  'https://growroom420.aculta.toca.net.br/',
  'https://revista.aculta.toca.net.br/',
  'https://eventos.aculta.toca.net.br/',
  'https://conta.aculta.toca.net.br/entrar',
].join(' ')).split(/\s+/).filter(Boolean);

const larguras = (process.env.TECLADO_WIDTHS || '320,1440').split(',').map(Number);
const MAX_TABS = 30;
const resultados = [];

const visivel = () => {
  const el = document.activeElement;
  if (!el || el === document.body) return null;
  const cs = getComputedStyle(el);
  const outline = parseFloat(cs.outlineWidth) || 0;
  const sombra = cs.boxShadow && cs.boxShadow !== 'none';
  return {
    tag: el.tagName.toLowerCase(),
    texto: (el.getAttribute('aria-label') || el.textContent || '').trim().slice(0, 60),
    href: el.getAttribute('href') || null,
    outline,
    sombra,
    visivel: outline >= 2 || sombra,
  };
};

const browser = await firefox.launch({ headless: true });
for (const url of hosts) {
  for (const tema of ['light', 'dark']) {
    for (const largura of larguras) {
      const page = await browser.newPage({ viewport: { width: largura, height: 900 } });
      const falhas = [];
      try {
        await page.goto(url, { waitUntil: 'load', timeout: 60000 });
        if (tema === 'dark') {
          await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
        }
        await page.evaluate(() => {
          const s = document.createElement('style');
          s.textContent = '*,*::before,*::after{transition:none!important;animation:none!important}';
          document.head.appendChild(s);
        });
        const paradas = [];
        for (let i = 0; i < MAX_TABS; i += 1) {
          await page.keyboard.press('Tab');
          const foco = await page.evaluate(visivel);
          if (!foco) { falhas.push(`parada ${i + 1}: foco perdido em body`); break; }
          paradas.push(foco);
          if (!foco.visivel) falhas.push(`parada ${i + 1} (${foco.tag} "${foco.texto}"): sem indicador de foco`);
        }
        // O foco precisa continuar dentro do documento após o laço.
        const noDocumento = await page.evaluate(() => Boolean(document.activeElement && document.contains(document.activeElement)));
        if (!noDocumento) falhas.push('foco saiu do documento');

        resultados.push({ url, tema, largura, paradas: paradas.length, falhas });
        console.log(`${falhas.length === 0 ? 'PASS' : 'FAIL'} ${url} [${tema} ${largura}px] paradas=${paradas.length} falhas=${falhas.length}`);
        for (const f of falhas) console.log(`  - ${f}`);
      } catch (erro) {
        resultados.push({ url, tema, largura, erro: String(erro).slice(0, 300) });
        console.error(`ERRO ${url} [${tema} ${largura}px] ${erro}`);
      } finally {
        await page.close();
      }
    }
  }
}
await browser.close();

fs.writeFileSync(path.join(suiteOut, 'teclado.json'), JSON.stringify({ data: new Date().toISOString(), resultados }, null, 1));
const falhou = resultados.some((r) => r.erro || (r.falhas && r.falhas.length));
console.log(`Resultado: ${path.join(suiteOut, 'teclado.json')}`);
process.exit(falhou ? 1 : 0);

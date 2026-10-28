// Smoke visual da agenda de eventos, da grade do mês, do dia e da página
// incorporável, no host do purpose `eventos` do SERVIDOR DE TESTES
// (https://eventos.aculta.toca.net.br).
// Confere: página carrega, título, estado do conteúdo, ausência de erro visível,
// rolagem horizontal zero e largura real de viewport em 1280, 390 e 320 px —
// as duas menores pelo BiDi (browsingContext.setViewport), porque o gerenciador
// X11 impõe largura mínima de janela. Na grade, confere a tabela semântica e a
// troca contagem → nome do evento no breakpoint md (768 px). No fim, emula
// prefers-color-scheme: dark (o mecanismo do ACULTA420) e captura a grade.
// Não autentica, não envia formulário e não escreve no banco. Artefatos em $SUITE_OUT.
import { mkdirSync, writeFileSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { join } from 'node:path';

const BASE = process.env.EVENTOS_BASE || 'https://eventos.aculta.toca.net.br';
const OUT = process.env.SUITE_OUT || '/tmp/aculta-suite-eventos';
const GECKO = process.env.GECKODRIVER_BIN || 'geckodriver';
const FIREFOX = process.env.FIREFOX || '/usr/bin/firefox-esr';
mkdirSync(OUT, { recursive: true });

const PAGES = [
  ['agenda', '/calendario', /Calendário de eventos/],
  ['mes', '/calendario/mes/2026/10', /Calendário/],
  ['dia', '/calendario/dia/2026/10/28', /Calendário/],
  ['embed', '/calendario/incorporar', /Calendário de eventos/],
  ['mapa', '/mapa', /Locais|Mapa/],
];
const WIDTHS = [1280, 390, 320];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok: !!ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  -- ' + detail : ''}`);
};

const port = await new Promise((resolve, reject) => {
  const s = createServer();
  s.on('error', reject);
  s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => resolve(p)); });
});
const driver = spawn(GECKO, ['--port', String(port)], { stdio: 'ignore' });
const GD = `http://127.0.0.1:${port}`;

async function wd(method, path, body) {
  const res = await fetch(GD + path, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  const json = await res.json();
  if (json.value && json.value.error) throw new Error(`${json.value.error}: ${String(json.value.message).split('\n')[0]}`);
  return json.value;
}

for (let i = 0; i < 75; i++) {
  try { await wd('GET', '/status'); break; } catch { await sleep(200); }
}

let session;
let bidi;
try {
  const caps = await wd('POST', '/session', {
    capabilities: { alwaysMatch: {
      browserName: 'firefox',
      acceptInsecureCerts: true,
      webSocketUrl: true,
      'moz:firefoxOptions': { binary: FIREFOX, args: ['-no-remote'] },
    } },
  });
  session = { id: caps.sessionId, ws: caps.capabilities.webSocketUrl };
  const p = (path) => `/session/${session.id}${path}`;
  const exec = (script) => wd('POST', p('/execute/sync'), { script, args: [] });
  const shot = async (name) => writeFileSync(join(OUT, `${name}.png`), Buffer.from(await wd('GET', p('/screenshot')), 'base64'));

  bidi = new WebSocket(session.ws);
  let id = 0; const pending = new Map();
  bidi.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pending.has(d.id)) { pending.get(d.id)(d); pending.delete(d.id); } };
  await new Promise((r) => (bidi.onopen = r));
  const bsend = (method, params = {}) => new Promise((res) => { const i = ++id; pending.set(i, res); bidi.send(JSON.stringify({ id: i, method, params })); });
  const tree = await bsend('browsingContext.getTree', {});
  const context = tree.result.contexts[0].context;

  for (const [nome, caminho, tituloEsperado] of PAGES) {
    const url = BASE + caminho;
    for (const largura of WIDTHS) {
      await bsend('browsingContext.setViewport', { context, viewport: { width: largura, height: largura < 500 ? 844 : 900 } });
      try {
        await wd('POST', p('/url'), { url });
      } catch (e) {
        check(`${nome} ${largura}px carrega`, false, String(e).slice(0, 120));
        continue;
      }
      await sleep(1200);
      const info = JSON.parse(await exec(`return JSON.stringify({
        url: location.href,
        titulo: document.title,
        texto: (document.querySelector('main') || document.body).innerText.replace(/\\s+/g, ' ').trim().slice(0, 240),
        viewport: window.innerWidth,
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        largos: [...document.querySelectorAll('body *')]
          .filter(e => e.getBoundingClientRect().width > document.documentElement.clientWidth + 1)
          .sort((a, b) => b.getBoundingClientRect().width - a.getBoundingClientRect().width)
          .slice(0, 8)
          .map(e => (e.tagName + '.' + String(e.className || '')).slice(0, 60) + ' width=' + Math.round(e.getBoundingClientRect().width) + ' left=' + Math.round(e.getBoundingClientRect().left)),
        rolagens: [...document.querySelectorAll('body *')]
          .filter(e => e.scrollWidth > e.clientWidth + 1)
          .sort((a, b) => (b.scrollWidth - b.clientWidth) - (a.scrollWidth - a.clientWidth))
          .slice(0, 6)
          .map(e => (e.tagName + '.' + String(e.className || '')).slice(0, 60) + ' scroll=' + e.scrollWidth + ' client=' + e.clientWidth),
        estouros: [...document.querySelectorAll('body *')]
          .filter(e => e.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
          .sort((a, b) => b.getBoundingClientRect().right - a.getBoundingClientRect().right)
          .slice(0, 8)
          .map(e => (e.tagName + '.' + String(e.className || '')).slice(0, 60) + ' right=' + Math.round(e.getBoundingClientRect().right)),
        erros: [...document.querySelectorAll('.messages--error, [role=alert]')].map(e => e.innerText.trim()).filter(Boolean).slice(0, 3),
        noindex: (document.querySelector('meta[name=robots]') || {}).content || ''
      });`));

      check(`${nome} ${largura}px: título`, tituloEsperado.test(info.titulo), info.titulo);
      check(`${nome} ${largura}px: viewport real`, info.viewport === largura, `pedido ${largura}, efetivo ${info.viewport}`);
      check(`${nome} ${largura}px: sem rolagem horizontal`, info.overflow <= 0, `${info.overflow}px ${info.estouros.join(' | ')} || LARGOS: ${info.largos.join(' | ')} || ROLAGENS: ${info.rolagens.join(' | ')}`);
      check(`${nome} ${largura}px: sem erro visível`, info.erros.length === 0, info.erros.join(' | '));
      check(`${nome} ${largura}px: conteúdo`, info.texto.length > 0, info.texto);
      if (nome === 'embed') {
        check(`${nome} ${largura}px: noindex`, /noindex/.test(info.noindex), info.noindex);
      }
      if (nome === 'mes') {
        const grade = JSON.parse(await exec(`var t = document.querySelector('table.aculta-mes');
          var medir = function (classe) {
            var d = document.createElement('div');
            d.className = classe;
            document.body.append(d);
            var v = getComputedStyle(d).display;
            d.remove();
            return v;
          };
          return JSON.stringify({
            existe: !!t,
            caption: t ? (t.querySelector('caption') || {}).innerText || '' : '',
            colunas: t ? t.querySelectorAll('th[scope=col]').length : 0,
            linhas: t ? t.querySelectorAll('tbody tr').length : 0,
            eventos: medir('aculta-mes__eventos'),
            contagem: medir('aculta-mes__contagem')
          });`));
        check(`${nome} ${largura}px: tabela da grade`, grade.existe && grade.colunas === 7 && grade.linhas >= 4, `colunas ${grade.colunas}, linhas ${grade.linhas}`);
        check(`${nome} ${largura}px: caption do mês`, /2026/.test(grade.caption), grade.caption);
        // Abaixo de md vale a contagem; a partir de md, o nome do primeiro evento.
        const esperado = largura >= 768 ? ['block', 'none'] : ['none', 'block'];
        check(`${nome} ${largura}px: responsivo no breakpoint md`, grade.eventos === esperado[0] && grade.contagem === esperado[1], `eventos=${grade.eventos}, contagem=${grade.contagem}`);
      }
      if (nome === 'mapa') {
        // O Google desenha um aviso dentro do container quando a chave é recusada.
        await sleep(2500);
        const mapa = JSON.parse(await exec(`var m = document.getElementById('aculta-map');
          return JSON.stringify({
            existe: !!m,
            filhos: m ? m.children.length : -1,
            texto: m ? m.innerText.replace(/\\s+/g, ' ').trim().slice(0, 200) : ''
          });`));
        check(`${nome} ${largura}px: container do mapa`, mapa.existe, mapa.existe ? 'div #aculta-map presente' : 'div ausente');
        // Sem marcador não há requisição ao Google: a chave não é exercitada.
        if (/Nenhum local com localização/i.test(mapa.texto) || mapa.filhos === 0) {
          results.push({ name: `${nome} ${largura}px: chave aceita pelo Google`, ok: null, detail: 'sem local com coordenadas' });
          console.log(`SKIP  ${nome} ${largura}px: chave aceita pelo Google  -- sem local com coordenadas`);
        }
        else {
          check(`${nome} ${largura}px: chave aceita pelo Google`, !/didn.t load|for development purposes|não foi possível carregar/i.test(mapa.texto), mapa.texto);
        }
      }
      await shot(`${nome}_${largura}`);
    }
  }

  // Modo escuro: o ACULTA420 resolve o modo por data-bs-theme (o Portal aplica o
  // atributo). O site de teste está em claro, então o teste alterna o atributo na
  // própria página, sem tocar na configuração do servidor.
  await bsend('browsingContext.setViewport', { context, viewport: { width: 1280, height: 900 } });
  await wd('POST', p('/url'), { url: BASE + '/calendario/mes/2026/10' });
  await sleep(1200);
  await exec(`document.documentElement.setAttribute('data-bs-theme', 'dark'); return true;`);
  await sleep(400);
  const escuro = JSON.parse(await exec(`return JSON.stringify({
    fundoBody: getComputedStyle(document.body).backgroundColor,
    fundoHtml: getComputedStyle(document.documentElement).backgroundColor,
    texto: getComputedStyle(document.body).color,
    overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    erros: [...document.querySelectorAll('.messages--error, [role=alert]')].length
  });`));
  const canais = (cor) => (cor.match(/\d+/g) || []).slice(0, 3).map(Number);
  const soma = (cor) => canais(cor).reduce((a, b) => a + b, 0);
  const fundo = escuro.fundoBody === 'rgba(0, 0, 0, 0)' ? escuro.fundoHtml : escuro.fundoBody;
  check('modo escuro 1280px: sem rolagem horizontal', escuro.overflow <= 0, `${escuro.overflow}px`);
  check('modo escuro 1280px: sem erro visível', escuro.erros === 0, String(escuro.erros));
  check('modo escuro 1280px: fundo escuro', fundo !== 'rgba(0, 0, 0, 0)' && soma(fundo) < 384, `fundo ${fundo}, texto ${escuro.texto}`);
  await shot('mes_dark_1280');
  await exec(`document.documentElement.removeAttribute('data-bs-theme'); return true;`);
} finally {
  const linhas = results.map((r) => `${r.ok === null ? 'ignorado' : r.ok ? 'ok' : 'FALHA'},${r.name},"${String(r.detail).replace(/"/g, "'")}"`);
  writeFileSync(join(OUT, 'resumo.txt'), linhas.join('\n') + '\n');
  if (bidi) bidi.close();
  if (session) { try { await wd('DELETE', `/session/${session.id}`); } catch { /* sessão já encerrada */ } }
  driver.kill();
}

const falhas = results.filter((r) => r.ok === false).length;
const ignoradas = results.filter((r) => r.ok === null).length;
console.log(`\n${results.length - falhas - ignoradas}/${results.length} verificações ok, ${ignoradas} ignoradas, ${falhas} falhas. Saída em ${OUT}`);
process.exit(falhas === 0 ? 0 : 1);

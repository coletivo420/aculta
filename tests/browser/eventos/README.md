# Navegador — eventos (agenda e incorporação)

Smoke visual das páginas públicas do purpose `eventos`, no **servidor de testes**
`https://eventos.aculta.toca.net.br`. Não autentica, não envia formulário e não
escreve no banco: só carrega as páginas, mede e captura.

## Pré-requisitos

- Firefox ESR em `/usr/bin/firefox-esr` (ou `FIREFOX`) e `geckodriver` no PATH
  (ou `GECKODRIVER_BIN`);
- sessão X11 local em `DISPLAY=:0` — Firefox visível, nunca headless nem Chromium;
- Node 22+ (o script usa `fetch` e `WebSocket` nativos).

O script sobe o próprio geckodriver em uma porta livre e o encerra no fim.

## Como executar

```bash
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/tmp/aculta-suite-eventos DISPLAY=:0 node tests/browser/eventos/agenda-embed.mjs
```

| Variável | Padrão | Função |
| --- | --- | --- |
| `EVENTOS_BASE` | `https://eventos.aculta.toca.net.br` | host do purpose `eventos` |
| `SUITE_OUT` | `/tmp/aculta-suite-eventos` | PNGs e `resumo.txt` (fora do repositório) |
| `GECKODRIVER_BIN` | `geckodriver` | binário do driver |
| `FIREFOX` | `/usr/bin/firefox-esr` | binário do navegador |

Saída: um PNG por página e largura, mais `resumo.txt`; código de saída `1` se
qualquer verificação falhar.

## O que cobre

| Página | Caminho |
| --- | --- |
| Agenda pública por dia | `/calendario` |
| Página feita para iframe | `/calendario/incorporar` |

Larguras: **1280** (desktop), **390** (celular) e **320** (menor suportada). As
duas menores passam por `browsingContext.setViewport` (WebDriver BiDi), porque o
gerenciador X11 impõe largura mínima de janela em torno de 500 px: sem BiDi, o
teste diria "320 px" medindo 500 px.

Por página e largura: título, largura efetiva do viewport, rolagem horizontal
(`scrollWidth - clientWidth`), erro visível (`.messages--error`, `[role=alert]`),
texto principal e, na incorporação, `noindex`.

Última execução (2026-10-28): **33/33 verificações ok**.

## Limites conhecidos

- O Runtime de teste está com **0 eventos e 0 datas**: o teste cobre carregamento,
  layout e estado vazio, não a renderização de ocorrências.
- Não verifica o cabeçalho `Content-Security-Policy: frame-ancestors` da rota de
  incorporação nem o iframe real em outro host; isso se confere com `curl -I`.
- Não mede contraste, foco por teclado nem leitor de tela (ver `VISUAL.md` do
  módulo e `docs/operations/NAVEGADOR-TESTES.md`).
- Roda só no servidor de testes; produção fica fora.

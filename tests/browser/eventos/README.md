# Navegador — eventos (agenda e incorporação)

Smoke visual das páginas públicas do purpose `eventos`, no **servidor de desenvolvimento**
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
| Lista de eventos (home do purpose) | `/` |
| Página de evento (primeiro link da lista) | `/eventos/{slug}` |
| Agenda pública por dia | `/calendario` |
| Grade do mês | `/calendario/mes/2026/10` |
| Agenda de um dia | `/calendario/dia/2026/10/28` |
| Página feita para iframe | `/calendario/incorporar` |
| Mapa de locais | `/mapa` |

Larguras: **1280** (desktop), **390** (celular) e **320** (menor suportada). As
duas menores passam por `browsingContext.setViewport` (WebDriver BiDi), porque o
gerenciador X11 impõe largura mínima de janela em torno de 500 px: sem BiDi, o
teste diria "320 px" medindo 500 px.

Por página e largura: título, largura efetiva do viewport, rolagem horizontal
(`scrollWidth - clientWidth`), erro visível (`.messages--error`, `[role=alert]`),
texto principal e, na incorporação, `noindex`. No mapa, confere o container
`#aculta-map` e, quando há local com coordenadas, se o Google recusou a chave.

Quando nenhum local tem coordenadas, o mapa não é desenhado e não há requisição
ao Google: a verificação da chave aparece como **SKIP** em vez de passar por
omissão.

No fim, o script alterna o atributo `data-bs-theme="dark"` na própria página (o mecanismo do
ACULTA420; o site de teste está em claro) e captura a grade em modo escuro, sem mudar a
configuração do servidor.

Última execução (2026-10-28): **126 ok, 3 ignoradas, 0 falhas**.

## Limites conhecidos

- O Runtime de teste está com **0 eventos e 0 datas**: o teste cobre carregamento,
  layout e estado vazio, não a renderização de ocorrências.
- O mapa só desenha com local que tenha coordenadas. O local de teste tem
  endereço, mas estava sem `field_coordenadas`, então a chave do Maps não é
  exercitada (SKIP).
- A chave de teste provisionada tem **restrição por referenciador** e o Google
  a recusa na Geocoding API (`REQUEST_DENIED`: "API keys with referer
  restrictions cannot be used with this API"). Geocodificação no servidor exige
  chave restrita por IP, separada da chave do navegador.
- Não verifica o cabeçalho `Content-Security-Policy: frame-ancestors` da rota de
  incorporação nem o iframe real em outro host; isso se confere com `curl -I`.
- Não mede contraste, foco por teclado nem leitor de tela (ver `VISUAL.md` do
  módulo e `docs/operations/NAVEGADOR-TESTES.md`).
- Roda só no servidor de desenvolvimento; produção fica fora.

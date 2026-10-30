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

## `domain-boundary.py` — fronteira de domínio e Datas no Evento

Homologa, autenticado como administrador no host de Eventos, que a área
editorial vive somente em `eventos.aculta` e que Datas/Locais são geridos
dentro do Evento. Não envia formulário nem grava conteúdo.

Cobre:

- visão geral administrativa sem “Adicionar data” e sem link para
  `/painel-administrativo/eventos/datas/adicionar`;
- criação de Evento com a seção de Datas/Locais e “Adicionar outra data”;
- edição de Evento existente listando Datas e com link escopado
  `/painel-administrativo/eventos/{id}/datas/adicionar`;
- negação (404) das rotas editoriais de Eventos e do OSM no domínio MAIN;
- disponibilidade da configuração OSM em Eventos;
- 404 da rota antiga global de Data.

### Execução

A URL de login de uso único é gerada com Drush **no host de Conta** (o cookie de
sessão é compartilhado em `.aculta.toca.net.br`) e passada por arquivo, nunca por
argumento ou saída de terminal:

```bash
cd /home/piradopirata/projetos/aculta
OUT=/home/piradopirata/aculta-suite-out/events-domain
mkdir -p "$OUT"
umask 077
vendor/bin/drush user:login --uri=https://conta.aculta.toca.net.br --no-browser \
  | tail -1 > "$OUT/login-url.txt"
chmod 600 "$OUT/login-url.txt"
DISPLAY=:0 SUITE_OUT="$OUT" ACULTA_LOGIN_URL_FILE="$OUT/login-url.txt" \
  python3 tests/browser/eventos/domain-boundary.py
rm -f "$OUT/login-url.txt"
```

| Variável | Padrão | Função |
| --- | --- | --- |
| `EVENTOS_BASE` | `https://eventos.aculta.toca.net.br` | host do purpose `eventos` |
| `MAIN_BASE` | `https://aculta.toca.net.br` | host MAIN, onde o editorial deve ser negado |
| `ACCOUNT_BASE` | `https://conta.aculta.toca.net.br` | host de Conta; o login de uso único é aberto nele (cookie `.aculta.toca.net.br`) |
| `SUITE_OUT` | — (obrigatório) | PNGs e `resultado.json` fora do repositório |
| `ACULTA_LOGIN_URL_FILE` | — (obrigatório) | arquivo com a URL de login de uso único |

### Limites conhecidos

- O link de login expira/é de uso único; gere um novo por execução e apague o
  arquivo no fim.
- O script depende de existir ao menos um Evento público no catálogo para a
  verificação da edição; sem Evento, essa verificação falha explicitamente.
- Não cobre o salvamento real de uma Data (evita gravar conteúdo no Runtime) nem
  o popup de Local além da presença do acionador.

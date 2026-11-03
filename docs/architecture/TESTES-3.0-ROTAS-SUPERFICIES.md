# Testes 3.0: congelar o comportamento atual das rotas por superfície

Status: **roteiro**, sem execução. **RUNTIME STATUS: DEFERRED** (sem acesso ao Runtime nem ao `vendor/` nesta sessão).

Base: `dev` @ 93b638a. Inventário de rotas: `rotas-por-purpose-dev-93b638a.csv` (161 declarações em YAML, mais atribuições em código por `RoutingEvents::ALTER`, que não entram no CSV).

## Objetivo

Antes de qualquer mudança na resolução de superfícies (Fase 3.1 a 3.4 do plano), registrar o resultado observável de cada combinação relevante. Depois de cada etapa, o mesmo roteiro deve dar o mesmo resultado, salvo mudança declarada.

## Matriz

Para cada linha: `host` × `método` × `caminho` → resultado esperado (código HTTP, redirect ou corpo).

Os hosts são os de produção (host de produção) e os de teste (host de teste do Homelab), para cada um dos 10 purposes.

### Casos obrigatórios

| Grupo | Caso | Resultado atual esperado |
|---|---|---|
| Raiz | `GET /` em cada purpose | serve a superfície do purpose (ver `FRONT_PATHS`) |
| Administração | `GET /painel-administrativo` fora de `main` | 404 |
| Administração | `POST` em rota administrativa fora de `main` | 404, sem redirect |
| Mutação | `POST` de pagamento em host errado | 404, sem redirect |
| Carrinho e checkout | `GET /carrinho` e `/checkout` fora de `main` | canonicalização ou 404, conforme `DomainRoutePolicy` |
| Conta | `GET /conta` sem login | redirect para login da Conta |
| Conta | `GET /conta-legada` | conforme `aculta_portal.dashboard` |
| Reset de senha | `entity.user.edit_form` no purpose `account` | exceção permitida |
| Integrações | `GET /integracoes/mcp` em `main` | serve o endpoint MCP |
| Integrações | `GET /integracoes/mcp` fora de `main` | 404 |
| Legado | `GET /forum` no purpose `forum` | 404 (`CAMINHOS_LEGADOS`) |
| Loja | `GET /node/372` na raiz de `shop` | 404 (`NO_DA_RAIZ`) |
| Raiz | `GET /` em `wiki` (wiki420) | serve a Wiki (raiz reescrita para `/wiki`) |
| Raiz | `GET /` em `shop` (loja420) | serve a loja (raiz reescrita para `/loja`) |
| Interno | `GET /wiki` e `GET /loja` diretos | 404 (`FRONT_PATHS`) |
| WE SATIVA | `GET /we-sativa` em `wiki` | rota da Suite (purpose `wiki`) |
| WE SATIVA | `GET /we-sativa/inicio` em `wiki` | rota da Suite |
| GREEN STASH | `GET /green-stash` em `shop` | rota da Suite (purpose `shop`) |
| Wiki | `GET /busca-wiki`, `/verbetes`, `/propostas/{id}` em `wiki` | rotas da Wiki na raiz |
| Caminhos antigos | `GET /wesativa`, `/greenstash`, `/wiki/busca`, `/wiki/verbetes` | 404 sem redirect |
| Studio | `GET /studio` e `GET /studio/*` em todos os purposes | 404 |
| Eventos | rota compartilhada `['eventos','main']` | serve nos dois purposes |

### Casos de flag

| Caso | Esperado |
|---|---|
| Rota com `_aculta_domain_purpose_authoritative` | o purpose declarado não é sobrescrito por subscriber posterior |
| Rota sem a flag | comportamento atual, sem mudança |

## Como executar (quando houver Runtime)

1. Subir o banco de teste do Runtime (ver docs/operations/AGENT-OPERATIONS.md), com a configuração de `config/sync` do commit do teste.
2. Para cada linha da matriz, requisitar o host com `curl -sS -o /dev/null -w '%{http_code} %{redirect_url}\n'` (sem credencial; rotas autenticadas ficam fora do escopo 3.0).
3. Registrar a saída em uma tabela com data, commit e host.
4. Comparar com a tabela de referência. Qualquer diferença é regressão, a não ser que esteja declarada no plano.

## Limites

- Não cobre rotas que dependem de login ou de pedido real.
- Não cobre a ordem de subscribers além do caso MCP e do caso de flag.
- Não substitui os testes unitários e de Kernel do Drupal (`scripts/validate-*`), que dependem de `vendor/`.

## Registro de execução

| Data | Commit | Host | Resultado | Status |
|---|---|---|---|---|
| — | — | — | não executado | DEFERRED |

### Runtime Homelab — 2026-11-03

Execução com `curl` contra `https://127.0.0.1`, usando `Host` dos aliases
documentados em `docs/architecture/multidomain.md`; os resultados são do
Runtime Homelab. A coluna `Location` ficou ausente em todos os casos.

| Caso | leitura_ativa=FALSE | leitura_ativa=TRUE |
|---|---:|---:|
| Raízes MAIN, Wiki e Loja | 200 | 200 |
| `/we-sativa` no Wiki | 403 | 403 |
| `/green-stash` na Loja | 403 | 403 |
| `/wiki` e `/loja` internos | 404 | 404 |
| Painel MAIN sem sessão | 404 (módulo desabilitado no primeiro levantamento) | 403 |
| Painel em subdomínio | 404 | 404 |
| `/studio` e `/studio/x` | 404 | 404 |
| `/wesativa`, `/greenstash`, `/wiki/busca` | 404 | 404 |
| `/integracoes/mcp` fora do MAIN | 404 | 404 |
| `/integracoes/mcp` no MAIN sem autenticação | 401 | 401 |

As respostas Drupal comuns usaram `Cache-Control: must-revalidate, no-cache,
private`; `/studio*` e `/integracoes/mcp` fora do MAIN usaram `no-store,
private`. Os 403 das Suites são controle de acesso para anônimo, sem redirect.
A validação autenticada da página administrativa e das Suites permanece
DEFERRED; o painel MAIN foi confirmado como rota existente por 403 de
permissão, e o mesmo caminho no Wiki permanece 404 sem redirect.

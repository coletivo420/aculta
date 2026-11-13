# ACULTA420 V2 — auditoria, PurgeCSS e limpeza de CSS legado (2026-11-12)

Snapshot inicial: `e37f3d8d45ecba9719d4b97dde67effc2236b13f` (branch `dev`).
Tema: `web/themes/custom/aculta420_v2`.

## 1. Escopo

Refatoração do CSS do tema com três objetivos:

1. inventário reproduzível de todo o CSS e das classes que o consomem;
2. integração do PurgeCSS como ferramenta de desenvolvimento, em modos
   `audit` e `apply` (staging), com safelist específica e justificada;
3. eliminação de CSS legado comprovadamente sem consumidor, preservando SDC,
   UI Suite, UI Skins, tokens semânticos, modo claro/escuro, acessibilidade e
   o contrato de cinco regiões.

O trabalho segue a ADR-054 (SDC First, Layout Builder) e a ADR-055 (UI Suite
integral). Nenhum componente SDC foi removido; o catálogo permanece íntegro.

## 2. Infraestrutura entregue

| Arquivo | Papel |
| --- | --- |
| `scripts/css-audit/report.mjs` | inventário: arquivo, biblioteca, regras, seletores, declarações, bytes, duplicações, `!important`, `:has()`/`:is()`/`:where()`, variáveis, media queries e referências textuais por classe |
| `scripts/css-audit/purge.mjs` | PurgeCSS 9.1.0; `--mode=audit` gera relatório e CSS podado em `reports/`; `--mode=apply` grava em staging; `--write` altera a fonte somente após revisão |
| `scripts/css-audit/collect-dynamic-classes.mjs` | resolve classes montadas a partir de `enum` dos metadados SDC, interpolações Twig, aliases `set` e opções de UI Styles |
| `scripts/css-audit/extractors/index.mjs` | extratores por extensão (Twig, PHP, JS, YAML) |
| `scripts/css-audit/safelist.mjs` | lista preservada com justificativa por padrão; proibido `^aculta-` |
| `scripts/css-audit/verify.mjs` | gate de integridade (`npm run css:verify`) |

Scripts npm: `css:audit`, `css:purge`, `css:verify`. Dependências de
desenvolvimento fixadas no `package-lock.json`: `purgecss@9.1.0` e
`postcss@8.5.32`. Nenhum Node, Vite ou Webpack é dependência do Runtime
Drupal; a infraestrutura só roda no repositório.

### Classes dinâmicas preservadas

O coletor resolve 220 classes em 86 componentes, incluindo:

- `aculta-surface--tone-{canvas,raised,subtle,institution,green-soft}`;
- `aculta-surface--radius-*`, `--spacing-*`, `--elevation-*`, `--border-*`;
- `aculta-surface-panel--header-*`, `--tone-*`, `--elevation-*`;
- `aculta-heading--level-*`, `--style-*`, `--accent-*`, `--surface-*`,
  `--align-*`;
- `aculta-icon--{duotone,regular}` e `--{sm,md,lg,xl,bar}`;
- `data-table--{cards,scroll}`;
- `aculta-auth-dialog--{neutral,institutional}`;
- classes literais das opções de `aculta420_v2.ui_styles.yml`.

A safelist também cobre, com justificativa individual, classes geradas em
tempo de execução por Drupal (rotas, nós, views, campos, toolbar, estados,
mensagens), UI Suite, HTMX e Leaflet. Não existe padrão geral `^aculta-`.

## 3. Métricas antes e depois

| Métrica | Antes | Depois | Δ |
| --- | ---: | ---: | ---: |
| Arquivos CSS-fonte | 107 | 106 | −1 |
| Bytes-fonte | 496.431 | 451.112 | −45.319 (−9,1%) |
| Regras | 2.691 | 2.499 | −192 |
| Seletores | 3.396 | 3.098 | −298 |
| Declarações `!important` | 17 | 14 | −3 |
| Seletores com `:has()` | 178 | 73 | −105 |
| Seletores com `:is()` | 355 | 277 | −78 |
| Media queries | 119 | 105 | −14 |
| Classes sem referência textual | 322 | 227 | −95 |
| Seletores rejeitados pelo PurgeCSS | 540 | 238 | −302 |

A redução não teve meta percentual: cada regra foi removida porque não tinha
consumidor textual, classe emitida por metadado ou seletor alcançável.

### Arquivos e blocos removidos

| Arquivo | Antes → depois | Substituição / motivo |
| --- | ---: | --- |
| `src/styles/studio-identity.css` | 5.168 → 0 | Nenhum código do V2 aplica `html.studio-identity`; as regras eram inalcançáveis. A biblioteca `studio` carrega somente `studio.css` |
| `src/styles/studio.css` | 80.133 → 56.741 | 642 linhas presas a `.aculta-main` (o shell usa `.page__main`) e regras de `.aculta-card*`, `.aculta-context-header*`, `.studio__tab*`, `.aculta-empty-state`, `.aculta-container` e `alert-*` |
| `src/styles/messenger-drawer.css` | 19.538 → 17.214 | classes de gaveta/aviso sem emissão (`aculta-messenger-offcanvas`, `aculta-read-label*`, `aculta-notice-type--*`), preservando as do Private Message |
| `src/styles/portal-account.css` | 17.680 → 14.951 | classes de conta sem emissão (`portal-account__status`, `__chevron`, `__icon`, `__label`, `social__*`, `courses*`, `photo__edit-icon`) |
| `src/styles/component-library-mode.css` | 12.236 → 8.396 | `aculta-component-library__component/description/index` e `ui_patterns_quicklinks__link(s)` sem emissão |
| `src/styles/content.css` | 6.184 → 3.952 | `.aculta-page-heading`, `.aculta-highlighted*`, `.aculta-content-surface`, `.aculta-metadata`, `.aculta-card*`, `.view-aculta-albuns` e a regra legada de `.aculta-surface`, que conflitava com o SDC `surface` |
| `src/styles/forum.css` | 37.928 → 35.072 | discussão de Revista/Fórum sem template (`aculta-revista-publication__discussion`, `aculta-forum-topic__discussion`), list-row e botão de novo tópico legados |
| `src/styles/utilities.css` | 2.474 → 2.328 | `.aculta-card*`, `.aculta-main-canvas`, `.aculta-panel__heading`, `aculta-u-mb-0` e `aculta-u-mb-4` |
| `src/styles/base.css` | 2.902 → 2.741 | `.flex`, `.justify-center` e `.align-center` sem emissão; `.flex-column` permanece (visualizador de PDF) |
| `src/styles/portal-account.css` | 17.680 → 14.807 | `portal-account__logout` (o template emite `__logout-row`) além das classes de conta sem emissão |
| `components/multidomain-bar/multidomain-bar.css` | 21.360 → 20.162 | `.aculta-purpose-showcase*` (o SDC emite `purpose-showcase`) e `.aculta-login-dialog__surface` (o template usa `aculta-search-dialog__surface`) |
| `src/styles/wiki.css` | 5.276 → 4.924 | `.aculta-wiki-collaboration` e `.aculta-wiki-search` (o form usa `aculta-wiki-search-results`) |
| `src/styles/layout.css` | 5.554 → 4.810 | `.page__footer` e `.page__footer-inner` (o rodapé é o SDC `site-footer`, que define `grid-area`) |
| `src/styles/portal-ui-map.css` | 11.794 → 11.926 | `aculta-location-actions/admin-actions` removidos; `.nav` passou a ter estilo próprio e `.messages--info` foi criado (+132 bytes) |
| `src/styles/portal-ui-extras.css` | 11.171 → 11.143 | `.aculta-social-link-dialog` saiu do seletor `:has()` |

### Tokens migrados

- `donation-account-invite`: `alert alert-info` (nomes Bootstrap) →
  `messages messages--info`. O token mantém `aculta-u-mt-3`.
- `we-sativa-intent`, `we-sativa-start`, `we-sativa-workspace`: removida a
  classe `we-sativa__workspace`, que não tinha CSS nem consumidor; os tokens
  mantêm `aculta-u-grid aculta-u-gap-4`.

## 4. Bibliotecas

`aculta420_v2.libraries.yml`:

- `studio` deixa de declarar `src/styles/studio-identity.css`;
- as demais bibliotecas contextuais (`statistics`, `portal-account`,
  `public-profile`, `event-organization`, `events`, `wiki`, `forum`,
  `shell-v2`) permanecem como estavam;
- `global-styling` mantém as 16 folhas locais + fonte externa (108.703 bytes
  locais). A auditoria
  confirmou que elas são transversais: fundações, utilitários emitidos por
  `PortalUiHooks`, conteúdo, busca (popup em toda página), botões de
  formulário, autoria de artigo, gaveta de mensagens e mapas `portal-ui-*`.
  Os CSS de `button`, `surface`, `surface-accent`, `surface-panel` e `heading`
  continuam globais porque as classes também são aplicadas por UI Styles fora
  dos SDCs.

`validate-aculta420-css-scope.php` passou a exigir que `studio-identity.css`
não volte nem ao global nem à biblioteca `studio`.

## 5. Endurecimento da infraestrutura (2026-11-12)

- **Parser YAML estruturado:** `js-yaml@4.3.2` substituiu o parsing por
  regex nos metadados SDC, em `aculta420_v2.libraries.yml` e no gate. Enum
  inteiro vira string de sufixo; `$ref` e props sem `enum` são ignorados.
- **Relatório obrigatório e fresco:** `purge.mjs` grava `manifest.json` com
  `sourceHash` (SHA-256 de nome + conteúdo de todas as folhas) e
  `toolVersions`. `css:verify` recalcula o hash e falha se o relatório
  estiver ausente, incompleto (contagem de arquivos divergente) ou
  desatualizado. Testado inserindo uma folha temporária: o gate falhou como
  esperado.
- **Métricas corrigidas:** retido e removido são calculados por diferença
  real entre o CSS de origem e o podado, com conjuntos de seletores de topo
  (respeitando `:is()`/`:has()`/`:where()`). `rejected` do PurgeCSS passou a
  ser apenas informativo.
- **Testes:** `npm run css:test` cobre parsing YAML, enum inline/em bloco,
  concatenação Twig, interpolação, alias `set`, UI Styles, safelist e hash.
- **`--write` seguro:** exige `--mode=apply --confirm-write`; faz backup com
  timestamp em `reports/css-audit/backup/`; recusa o corte se qualquer CSS
  podado não fizer parse. Sem confirmação, nada é escrito.
- **Safelist revisada:** removido o padrão amplo `\.is-`; os estados
  conhecidos (`is-open`, `is-collapsed`, `is-loading`, `is-invalid` etc.)
  ficaram explícitos. Os demais padrões continuam restritos a famílias
  geradas pelo Drupal/UI Suite, cada um com justificativa.

## 6. Gates executados

| Gate | Resultado |
| --- | --- |
| `npm run lint:css` | PASS (0 erros) |
| `npm run css:audit` | PASS (inventário + relatório PurgeCSS) |
| `npm run css:test` | PASS (5 testes: YAML estruturado, classes dinâmicas, safelist, hash) |
| `npm run css:verify` | PASS (8 verificações, incluindo frescor do relatório) |
| `php scripts/validate-aculta420-mapped-classes.php` | PASS (480 classes, 0 exceções, 0 nomes Bootstrap) |
| `php scripts/validate-aculta420-css-scope.php` | PASS (50 checks) |
| `git diff --check` | PASS |

Não executados nesta sessão:

- **Captura visual e Playwright**: DEFERRED. O ambiente X11 não foi acionado
  nesta sessão. O CSS removido não tinha consumidor nem seletor alcançável,
  mas claro/escuro e 320–1440 px continuam exigindo aceite.
- `validate-aculta420-v2-sdc.php` e `validate-aculta420-layout-builder-batch.php`:
  N/A — nenhum schema, render array ou configuração de display foi alterado.
- `validate-aculta420-design-foundations.php`: DEFERRED por problema
  estrutural conhecido (lê `css/tokens.css`, enquanto o V2 publica
  `src/styles/tokens.css`).

## 7. Pendências reais (com consumidores identificados)

1. **Utilitários `aculta-u-*` — RESOLVIDO em 2026-11-12.** Nenhum token de
   `PortalUiHooks::TOKENS` usa mais utilitário. Os 39 tokens receberam classes
   semânticas por domínio, definidas em `portal-ui-map.css` e nos CSS dos
   módulos; `utilities.css` saiu de `global-styling` e do repositório.
   Permanece apenas `.aculta-u-block-start`, que é configuração de UI Styles
   em `block.block.*` (não vem do mapa) e agora vive em `layout.css`.
2. **`portal-ui-map.css` e `portal-ui-extras.css`**: continuam sendo a ponte
   sancionada token → classe (`data-portal-ui`), não uma camada de
   compatibilidade: as equivalências Bootstrap foram removidas e os tokens
   passaram a apontar para classes semânticas. A substituição integral por
   composição SDC do markup de módulo é incremental e não bloqueia a
   eliminação do legado de utilitários.
3. **`studio.css`**: restam regras de `.studio`, `.aculta-profile-editor`,
   formulários de perfil e seletores de estado. A migração para SDC/UI Suite
   exige revisão visual autenticada; preservadas.
4. **PurgeCSS `apply`**: o staging é evidência, não corte automático. Muitos
   seletores restantes dependem de marcação renderizada em tempo de execução
   (IDs de Views, classes de comentários/aninhamento). Aplicar `--write`
   exige comparação no Runtime.

## 8. Rollback

- CSS: `git revert` do commit desta entrega restaura os arquivos-fonte,
  incluindo `studio-identity.css` e a declaração na biblioteca `studio`.
- Ferramentas: remover `scripts/css-audit/`, as entradas `css:*` de
  `package.json` e `purgecss`/`postcss` do lockfile. Nenhum artefato é
  servido pelo Drupal.
- Não há alteração de configuração Drupal, conteúdo, banco ou Runtime.

## 9. Reprodução

```bash
npm ci
npm run css:audit   # inventário + relatório do PurgeCSS
npm run css:verify  # gate de integridade
npm run lint:css
php scripts/validate-aculta420-mapped-classes.php
php scripts/validate-aculta420-css-scope.php
```

Saídas geradas em `reports/css-audit/` (fora do Git).

## 10. Runtime e homologação (2026-11-12)

### Sincronização

`bash scripts/runtime-sync.sh doctor` retornou **BLOCKED**: worktree com
alterações concorrentes e delta cumulativo **RX**. O próprio diagnóstico
mostrou `updatedb_pending_count: 0`, `config_drift_count: 0` e
`deploy_hook_pending_count: 0`, com Drush e banco operacionais. Nenhum
`sync-code`, `drush cr`, `updatedb` ou import de configuração foi executado
por esta frente.

Ainda assim, o Runtime já servia o CSS novo: o agregado
`css_zbZdWZT4qiGV0GSo8xtAoIdjOlECdvIYrVBNjTNQJ-c.css` contém
`messages--info` (regra criada nesta entrega) e não contém
`aculta-wiki-collaboration` (seletor removido). A conclusão é que a
regeneração de agregados acompanhou a mudança de mtime das folhas; isso não
substitui a validação de cache do sincronizador, que segue DEFERRED.

### Matriz visual

Método: Firefox ESR visível via X11 (`DISPLAY=:0`, geckodriver/wdlib),
ambiente de desenvolvimento. Dez hosts (main, conta, revista, wiki420,
growroom420, loja, cursos, links, apoio, eventos), larguras 320, 375, 768,
1024 e 1440 CSS px, modos claro e escuro.

Resultado: **100/100 PASS** — HTTP 200, `readyState=complete` e ausência de
overflow horizontal em todas as combinações. 40 PNGs (320 e 1440 px nos dois
modos) em `$SUITE_OUT`, manifesto versionado em
`tests/browser/capturas/css-audit-20261112/manifest.json`.

### Acessibilidade

`playwright.a11y.spec.mjs` (axe-core, WCAG 2.0/2.1/2.2 A/AA): 16
verificações, 7 falhas de `color-contrast` **pré-existentes** — `.site-footer`
(`#0c3c29` sobre `#689427`, 3,45:1) e breadcrumb do Fórum (`#689427` sobre
branco). Os arquivos desses componentes e os tokens não foram alterados desde
`e37f3d8`. Reflow a 320 px passou. Achado registrado em
`docs/operations/testing/CONTRASTE-FOOTER-2026-11-12.md`.

Não cobertos: sessão autenticada (Studio, conta, moderação), modais,
gavetas, teclado manual e leitor de tela. Permanecem DEFERRED.

## 11. Purge final com evidência do Runtime (2026-11-12)

`npm run css:classify` usa o HTML renderizado de dez hosts (capturado em
`reports/css-audit/runtime/html/`) como fonte complementar. Dos 252 seletores
que o PurgeCSS removeria:

| Classificação | Seletores | Decisão |
| --- | ---: | --- |
| Dinâmicos (enum SDC/UI Styles) | 7 | preservar |
| Presentes no HTML do Runtime | 69 | preservar |
| Padrão de runtime (Drupal/UI Suite/Commons) | 125 | preservar até prova em contrário |
| Referência textual no repositório | 31 | preservar |
| Sem evidência | 20 | manter: todos pertencem ao SDC `studio-connections-page`, que permanece no catálogo universal e só carrega CSS quando renderizado |

Nenhum corte adicional foi aplicado a partir dessa classificação. O
`--write` continua bloqueado por confirmação explícita e não foi usado.
As exclusões aplicadas nesta rodada foram as comprovadas por referência
textual e seletor alcançável: `.flex`, `.justify-center`, `.align-center`
(sem emissão; `.flex-column` fica para o visualizador de PDF),
`portal-account__logout` (o template emite `__logout-row`),
`page__footer`/`page__footer-inner` (o rodapé é o SDC `site-footer`),
`aculta-forum-category-list` e `aculta-wiki-search`.

O HTML do Runtime é uma amostra das homes; estados autenticados e telas
internas não estão nela. Por isso "sem evidência" não autoriza remoção.

## 12. Auditoria de consumidores do mapa de tokens (2026-11-12)

`PortalUiHooks::TOKENS` tem 529 entradas, 45 delas com lista de classes
vazia. O cruzamento com templates, PHP, JS, YAML e Config Sync encontrou
apenas 10 tokens sem referência textual externa:

`button-ghost`, `button-outline`, `card-cultural-accent-{green,yellow,red}`,
`revista-teaser-accent-{green,yellow,red}`, `domain-brand-image` e
`event-date-cancel`.

Nenhum foi removido: os tokens de botão são exigidos pelo UI Styles
(`button_outline`/`button_ghost`), `event-date-cancel` atende envios de
formulário com `data-portal-ui`, e os demais pertencem a variantes editoriais
aplicadas por configuração. O mapa permanece com 529 entradas e 480 classes
cobertas pelo gate.

Conclusão da Fase 4 nesta rodada: não há entrada de token comprovadamente
morta. A migração de `aculta-u-*` e dos mapas `portal-ui-*` para composição
SDC continua dependente dos render arrays dos módulos do Portal, fora do
escopo do tema e sem autorização para alterar módulos com trabalho
concorrente.

## 13. Segunda rodada com autorização do responsável (2026-11-12)

O responsável autorizou alterar render arrays dos módulos do Portal e corrigir
o contraste em frente de UI Skins. Resultado:

- **Utilitários eliminados.** `src/styles/utilities.css` saiu do repositório e
  de `global-styling`. Os 39 tokens que usavam `aculta-u-*` passaram a classes
  semânticas (`aculta-donation__*`, `aculta-checkout__*`, `aculta-shop-*`,
  `aculta-location-*`, `aculta-lms-*`, `aculta-messenger-*`,
  `aculta-we-sativa__*`, `studio__actions`, `aculta-hint*`,
  `aculta-form--inline`, `aculta-section-title`).
- **Equivalências Bootstrap removidas** do mapa: `.text-muted`, `.text-center`,
  `.small`, `.lead`, `.clearfix`, `.list-unstyled`, `.list-group`, `.table`,
  `.table-hover`, `.label-default`, `.link-primary`, `.section-title`, `.nav`,
  `.nav-tabs`, `.pager-next` e `.pager-prev`. Restam `.inline` e
  `.container-inline`, emitidas pelo Core.
- **`.aculta-panel--default` removida** por repetir `.aculta-panel`.
- **Dois emissores diretos migrados**: o `<small class="text-muted">` do painel
  administrativo de organizações virou o token
  `events-organization-admin-table`; o `link-primary` do aviso de pagamento
  virou `aculta-payment-notice__link` no template do tema.
- **Gate de documentação de tokens corrigido**: não aborta mais quando o tema
  V1 `aculta420` não existe. `docs/portal/TOKENS-E-ATRIBUTOS.md` sincronizado
  (532 tokens, 451 usados, 160 atributos).
- **Contraste**: link do rodapé (3,45:1 → 5,08:1) e nível atual da trilha
  (3,58:1 → 12,4:1); categoria do teaser da Revista idem. Frente nova
  `tests/browser/transversal/aculta-contraste/` (axe): 22/22 PASS.
- **Homologação**: `page-matrix.py` 100/100 nos dez hosts, 320–1440 px, claro e
  escuro, sem overflow; `css:verify` PASS; mapped classes PASS (470); UI Styles
  PASS (54); Stylelint e PHPCS sem erros novos.
- **Sessão autenticada**: com as contas de teste de `secrets/`, 28/28
  verificações PASS — conta comum em `/conta`, `/conta/dados`, `/meu-perfil`,
  `/mensagens/resumo`, `/avisos-mensagens` e `/salvos`; conta administrativa em
  `/marley`. Em 320 e 1440 px, claro e escuro: HTTP 200, sem overflow, sem
  `aculta-u-*` (fora de `.aculta-u-block-start`), sem equivalências Bootstrap e
  axe `color-contrast` zerado. Evidência em
  `/home/piradopirata/aculta-suite-out/autenticado-20261112`.
- **Métricas**: 105 arquivos, 452.559 bytes, 2.488 regras, 3.087 seletores;
  `global-styling` 108.777 bytes.
- **Continua pendente**: `wiki420/we-sativa` e `cursos/ganjalab` (403 para as
  contas de teste: exigem papéis de contribuidor Wiki e educador), modais,
  gavetas, teclado e leitor de tela; composição SDC do markup de módulo
  (incremental); comparação pixel a pixel sem baseline anterior.

## 14. Teclado, diálogos e determinismo da matriz (2026-11-12)

- **Harness corrigido.** `page-matrix.py` não redimensionava a janela: todas as
  capturas saíam com ~1024 px e a matriz passava sem testar 320–1440. Passa a
  aplicar viewport real por WebDriver BiDi (`browsingContext.setViewport`),
  registra a largura medida e falha quando ela diverge. Transições e animações
  são congeladas antes do screenshot: duas execuções seguidas produziram
  **40/40 PNGs idênticos**. Nova execução com larguras reais: **100/100 PASS**
  (dez hosts × 320/375/768/1024/1440 × claro/escuro).
- **Teclado.** Frente `tests/browser/transversal/aculta-teclado-foco/`: 30
  paradas de Tab em cinco URLs, claro/escuro, 320/1440, com outline ou
  box-shadow em cada parada. **20/20 PASS**.
- **Diálogos.** Frente `tests/browser/transversal/aculta-dialogos/`: carrinho,
  menu da conta e gaveta de Mensagens abertos por teclado; foco entra, 20 Tabs
  não escapam, Escape fecha e devolve o foco, sem overflow, axe
  `color-contrast` zerado, `<dialog>` com nome acessível e `aria-expanded`
  correto. **12/12 PASS**.
- **Modal de aceite dos termos** não é automatizado porque exige gravar o
  aceite; a conta comum o mantém aberto e prendendo o foco por desenho.
- **Leitor de tela** segue manual: Orca está instalado, mas o
  `speech-dispatcher` está inativo no ambiente.
- **Pendências restantes**: papéis de contribuidor Wiki (`/we-sativa`) e
  educador (`/ganjalab`), que recebem 403 com as contas disponíveis;
  verificação manual com Orca; composição SDC incremental; comparação pixel a
  pixel histórica (a partir de agora há baseline determinístico).

## 15. Revisão da classificação e cortes (2026-11-13)

A classificação foi inspecionada por categoria. Os 22 seletores marcados sem
evidência foram pesquisados em todas as fontes Twig, PHP, YAML e JavaScript do
tema e módulos customizados; nenhum emissor foi encontrado. Removidos 24 nós de
regra (22 seletores únicos) em `donation-presentation.css`, `sales-panel.css` e
`studio-connections-page.css`. O diff remove 3.295 bytes, incluindo espaço em
branco associado. Os 144 seletores rejeitados restantes foram mantidos: 2
dinâmicos, 46 encontrados no HTML de Runtime, 68 cobertos por padrões
Drupal/UI e 28 com referências no repositório.

| Métrica | Antes | Depois |
| --- | ---: | ---: |
| CSS-fonte inventariado | 415.291 bytes | 411.996 bytes |
| Regras / seletores no inventário | 2.381 / 2.835 | 2.357 / 2.811 |
| CSS retido pelo PurgeCSS | 390.345 bytes | 390.250 bytes |
| CSS removível pelo PurgeCSS | 24.946 bytes | 21.746 bytes |

`npm run css:audit`, `npm run css:verify`, `npm run css:test`,
`npm run lint:css` e `git diff --check`: PASS. A busca por consumidores é
estática; o aceite visual depois do corte continua pendente, pois o
sincronizador Runtime está bloqueado por RX e drift de configuração.

## 16. Integridade do relatório e escrita controlada (2026-11-13)

- UI Styles agora é lido com `js-yaml`; o coletor compartilha a expansão de
  enums SDC e de classes PHP construídas por allowlists `safeValue()`.
- `css:verify` compara versões instaladas, conteúdo/globs, safelist, classes
  dinâmicas e hash CSS com o relatório. Exige a lista completa de fontes e de
  CSS podado, valida sintaxe, bytes, seletores removidos/reescritos e totais
  por arquivo contra o conteúdo real em staging.
- Métricas de seletores obedecem `originais + reescritos − removidos = retidos`;
  bytes retidos/removidos são medidos no CSS efetivo após normalização Stylelint.
- `css:purge --mode=apply` só cria staging. `--write` exige confirmação e hash
  de revisão sobre CSS, ferramentas, safelist e classificação completa;
  safelist dinâmica preservada e todos os seletores removidos classificados
  como sem evidência. Neste estado, nenhum dos seletores restantes ficou sem
  evidência, então a tentativa com hash válido foi recusada antes de gravar.
  Backups e gates pré/pós-gravação ficam obrigatórios quando houver corte
  elegível; folhas que ficariam vazias são preservadas.
- `npm run css:audit`, `css:verify`, `css:classify`, `css:test`, Stylelint no
  staging e no tema e `git diff --check`: PASS.
- **Runtime e navegador:** DEFERRED. O OpenSSH recusa a configuração de proxy
  por proprietário `nobody`; sem essa configuração, DNS de `github.com` falha.
  Os hosts ACULTA resolvem para loopback, mas a conexão HTTPS é recusada. O
  estado persistido registra drift em `user.role.administrator`, cache validado
  somente até `e37f3d8d` e módulos ausentes de schema. Não foram executados
  `sync-code`, `drush cr` nem Playwright contra Runtime.

## 17. Migração contextual de consumidores do mapa (2026-11-13)

- Oito regras e dez identidades de seletor saíram de
  `src/styles/portal-ui-map.css`: hosts de calendário, DNS de superfícies,
  filtros/fila/pager de moderação, Loja, filtro de organizações de Eventos e
  links de nova conversa. O mapa passou de 4.187 para 3.451 bytes (-736 bytes,
  -17,6%).
- As regras foram para bibliotecas de propriedade dos módulos:
  `aculta_calendar/embed-hosts`, `aculta_paths/surfaces`,
  `aculta_cgu/moderation-queue`, `aculta_user/report-queue` e
  `aculta_portal/shop-presentation`; `aculta-form--inline` foi para o asset de
  Eventos existente e os links do Mensageiro ganharam uma biblioteca anexada
  ao formulário apenas no fluxo de grupo. As seis folhas novas somam 673 bytes;
  todas são anexadas no render array que consome cada regra.
- O gate `validate-aculta420-mapped-classes.php` agora inspeciona CSS da raiz
  de `aculta_portal/css` além dos submódulos. A migração da Loja revelou que o
  gate anterior não considerava folhas CSS da raiz do módulo.
- Relatório recalculado após as oito regras: 144 folhas de tema, 413.273 bytes,
  2.368 regras e 2.824 seletores. O PurgeCSS retém 391.903 bytes e identifica
  21.370 bytes / 142
  seletores removíveis; todos permanecem classificados com evidência (2
  dinâmicos, 43 no HTML Runtime, 68 por padrão Drupal e 29 por fonte). Não se
  aplicou purge destrutivo.
- A verificação de escopo informa 61.190 bytes de CSS local em
  `global-styling`; a remoção isolada do mapa global é 736 bytes. `css:audit`,
  `css:classify`, `css:verify`, `css:test`, `lint:css`, Stylelint das folhas
  novas, YAML/PHP lint, PHPUnit unitário (16 testes/96 assertions em fila e
  Messenger), `validate-aculta420-mapped-classes.php` e
  `validate-aculta420-css-scope.php`: PASS. Uma depreciação preexistente do
  `private_message` foi reportada. Os Kernel tests de Calendar/CGU foram
  tentados, mas o sandbox não permitiu conexão ao socket MySQL; não são
  evidência de Runtime.
- `runtime-sync.sh doctor --format=json` não conseguiu confirmar `origin/dev`;
  sincronização, `drush cr` e matriz visual seguem pendentes. Não declarar
  homologação até executar essas verificações no Runtime atualizado.

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
| Bytes-fonte | 496.431 | 451.485 | −44.946 (−9,1%) |
| Regras | 2.691 | 2.504 | −187 |
| Seletores | 3.396 | 3.103 | −293 |
| Declarações `!important` | 17 | 14 | −3 |
| Seletores com `:has()` | 178 | 73 | −105 |
| Seletores com `:is()` | 355 | 278 | −77 |
| Media queries | 119 | 105 | −14 |
| Classes sem referência textual | 322 | 231 | −91 |
| Seletores rejeitados pelo PurgeCSS | 540 | 243 | −297 |

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
- `global-styling` mantém as 16 folhas locais + fonte externa (108.769 bytes
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

1. **Utilitários `aculta-u-*`**: continuam emitidos por `PortalUiHooks::TOKENS`
   (ex.: `account-entry-links`, `studio-actions`, `new-conversation-links`).
   Removê-los exige migrar os consumidores para composição SDC. Não há
   `legacy.css`/`compat.css`: a regra viva permanece em `utilities.css`.
2. **`portal-ui-map.css` e `portal-ui-extras.css`**: são o mapa ativo das
   classes emitidas pelos tokens do Portal (cerca de 480 classes). A
   substituição integral por SDC depende de migrar os render arrays dos
   módulos do Portal, fora do escopo do tema.
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

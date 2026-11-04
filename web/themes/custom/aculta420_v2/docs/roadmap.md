# ACULTA420 V2 roadmap

- `1.2.0-dev.13`: breadcrumb migrado da V1 para SDC e integrado ao template
  Drupal `navigation/breadcrumb`. Contrato visual preserva links editoriais,
  separador `/`, título atual e foco. Baseline: V2 não possuía override nem
  componente; referência V1 em `templates/navigation/breadcrumb.html.twig` e
  `css/components/breadcrumb.css`. Firefox ESR visível, purpose `main`, URL
  `/node/572`, anônimo: 320px claro, 390px escuro, 1440px claro/escuro. Uma
  navegação, rótulo traduzido “Trilha de navegação”, título atual preservado,
  link com contraste 5,70:1 no canvas claro e 6,79:1 na superfície escura;
  documento sem overflow. Zoom nativo 200%, viewport CSS 320px: documento 320px,
  breadcrumb 288px. Tab atingiu o link com foco visível de 3px. Capturas em
  `$SUITE_OUT/v1-v2-breadcrumb-20261104/`. Gate SDC: 19/19 componentes renderizados;
  schemas 55/764; foundations 51 pares. Cobertura focal: outras rotas e purposes
  seguem pendentes.

- `1.2.0-dev.11`: modal de entrada portado da identidade V1 para `<dialog>` e
  Fetch, mantendo o botão Google quando a integração Portal estiver configurada.
  fornecido por `aculta_user`; não há apelido na barra. Evidência visual e
  runtime autenticado ainda pendentes.
- `1.2.0-dev.12`: superfície do modal alinhada ao verde institucional da V1;
  Google, campos, ação amarela e hierarquia de links visualmente conferidos.
  Firefox ESR visível no purpose `main`, rota `/`, estado anônimo: 320 px
  claro, 390 px escuro e 1440 px em ambos os modos; modal 288/358/440 px,
  sem overflow e com foco inicial no campo. Zoom real de 200% com viewport de
  320 CSS px não alargou o documento; Tab avança para senha e Escape fecha o
  modal. Capturas e métricas em `$SUITE_OUT/login-modal-v2-20261104/`.
  Autenticação completa e demais purposes ficaram DEFERRED nesta verificação
  focal.

## Fase 1 — Tokens e fundações

**Status: subfase de tokens/fundações entregue em `1.2.0-dev.1`; a Fase 1 do ADR-046 permanece aberta para shell, purpose e UI Styles.**

- Tokens semânticos mantêm a identidade visual V1 e os modos explícitos do
  Portal. Não há detecção `prefers-color-scheme`.
- Inter e Oswald, escala de espaçamento, foco visível, movimento reduzido,
  dimensões fluidas e prevenção de overflow em conteúdo longo são fundações da
  V2. O tema continua sem Bootstrap, Vite ou dependência de Node.
- O gate `php scripts/validate-aculta420-v2-foundations.php` valida 33 pares
  texto/superfície de tema escuro acima de 4,5:1 e indicadores/bordas acima de
  3:1. O menor contraste de texto é 6,07:1.
- Auditoria antes/depois: baseline visual/código `676ff17449`; depois
  `1.2.0-dev.1`. Dez purposes públicos; URL raiz `/` conforme a matriz de
  `docs/architecture/multidomain.md` (Conta termina em `/entrar`). Viewports
  320/360/390/599/600/768/1024/1280/1440 CSS px; claro e escuro; sessão
  anônima. Reflow: 180/180 combinações sem overflow e HTTP 200 nos dez
  purposes. Firefox ESR visível: Tab e Shift+Tab 10/10, foco de 3 px; zoom
  nativo 200% e 400% 10/10, viewport efetiva 320 px; sem JavaScript 90/90
  combinações largura/purpose; `prefers-reduced-motion: reduce` 180/180
  combinações largura/modo, transição 0,01 ms. Capturas e resultados por URL
  requisitada/final em `$SUITE_OUT/aculta420-v2-foundations-full/`, fora do
  repositório.
- Limitadores: a auditoria foi anônima; estados autenticados e leitor de tela
  permanecem DEFERRED. O comando legado `validate-aculta420-foundation.php`
  falha porque exige V1 como tema público e Bootstrap5 como base; o gate
  específico `validate-aculta420-v2-foundations.php` e os gates SDC V2 passam.

## Fase 2 — Shell

**Status: em andamento; navegação SDC integrada. Revisões de Institution Bar, Domain Header, rodapé e demais regiões seguem pendentes.**

- Referência visual: V1 `templates/page.html.twig` e `docs/shell.md`, somente
  leitura. Institution Bar e Domain Header foram preservados como contratos.
- Baseline antes da navegação: commit `33e4dddbf5`; Firefox ESR visível,
  anônimo, nas raízes dos dez purposes em claro/escuro e 320–1440 CSS px,
  180/180 sem overflow. Tab/Shift+Tab, zoom 200/400, sem JS e movimento
  reduzido passaram. Capturas: `$SUITE_OUT/aculta420-v2-shell-baseline/`.
- Antes: o bloco de menu principal estava dentro de `page.header`, sem o
  comportamento do SDC `navigation-bar`.
- Depois: o preprocess substitui o bloco pela instância do SDC no mesmo peso e
  posição do cabeçalho; o render array original continua como slot de menu, com
  acesso e metadados de cache preservados. O SDC não repete a marca do Domain
  Header. Menu é visível sem JavaScript; com JS, Enter abre, Escape fecha e o
  foco acompanha a interação.
- Firefox ESR visível, anônimo: 70/70 checks nos dez purposes, em 320/767/768/
  1440 CSS px; Enter, Escape, foco, landmarks e fallback sem JS em 320 px.
  Evidência: `$SUITE_OUT/aculta420-v2-navigation-final3/`.
- A faixa institucional fica acima da sidebar administrativa Gin e do toolbar
  Core; Gin inicia após a altura medida da faixa, e a bandeja Core após a faixa
  e sua própria barra de controles. Firefox ESR visível passou 10/10 checks em
  320/390/768/1024/1280 px, sem overflow: barra 0–73 px, Gin inicia em 73 px,
  controles Core iniciam em 73 px e bandeja Core em 112 px. A fixture usa as
  folhas locais de Gin e Core, mas não uma sessão administrativa real: isso
  permanece DEFERRED.
  Evidência: `$SUITE_OUT/aculta420-v2-admin-toolbar-layer-accept/`.
- Baseline: commit `33e4dddbf5`; screenshots atuais comparam as raízes de Main,
  Conta, Apoio, Revista, Wiki, Loja, Cursos, Fórum, Links e Eventos, URLs
  requisitadas e finais vindas do `deploy.json`, estado anônimo, claro/escuro,
  320–1440 CSS px, zoom 200/400, teclado e movimento reduzido. Resultados em
  `$SUITE_OUT/aculta420-v2-shell-after-verified/`; leitores de tela e estados
  autenticados permanecem DEFERRED. O runner aplica o CSS baseline ao DOM atual;
  essa comparação não recupera a marcação Twig histórica da baseline.

### Fase 2 — menu de conta (ADR-047)

**Status: versionado em `1.2.0-dev.3` a `1.2.0-dev.11` (ver [CHANGELOG.md](../CHANGELOG.md)). Validação visual autenticada e gates de renderização pendentes.**

- O SDC `account-menu` está na barra institucional. O PHP (`PagePresentationHooks::buildAccountMenuComponent`) monta o componente: visitante recebe "Entrar" apontando para `/entrar/janela`; autenticado recebe o avatar do Portal e o menu Drupal de conta.
- O avatar vem de `aculta_user` como render array (`aculta_account_avatar`). Sem avatar, o componente mostra o ícone neutro.
- A barra não mostra apelido nem nome. O rótulo "Minha conta" é texto oculto, para o nome acessível.
- Pendentes para fechar a fase:
  1. Verificação visual em 320, 390 e 1440 CSS px, claro e escuro, zoom de 200%, teclado, com três cenários autenticados: foto, acervo e sem foto.
  2. Leitor de tela: o botão deve anunciar "Minha conta".
  3. Validação de renderização do `account-menu`. O changelog do `1.2.0-dev.5` registra que o `drush cr` revelou um problema de descoberta e que a validação seria repetida; essa repetição não aparece no changelog.
  4. Gates do tema (ver [gates.md](gates.md)) executados no Homelab, com a saída colada na entrega.
  5. Versão: `1.2.0-dev.11` está em `info.yml`. Revisar com o responsável se a entrega fecha em `dev.11` ou sobe para a próxima versão, após a validação.

## Fase 3 — Purpose neutro no Portal

**Status: pendente; contrato de apresentação será alterado em commit separado no `aculta_portal`.**

## Fase 4 — UI Styles

**Status: pendente; verificar compatibilidade da versão com Drupal 11.5 e 12 antes da instalação.**

## Fase 5 — Componentes e templates

**Status: catálogo atual pendente de auditoria integral; componentes faltantes e templates de páginas seguem após shell e fundações.**

- Há quinze componentes SDC com schema estrito e um gate Drush de descoberta,
  assets e renderização isolada.
- ACULTA Maps fornece render arrays neutros; Leaflet e Google reutilizam
  templates SDC inertes. O JavaScript de mapa usa `core/once`.
- Phosphor Icons Core 2.1.1 (MIT) está vendorizado; seus SVGs são injetados
  inline pelo SDC `icon`.
- A barra institucional usa dados neutros do Portal; conta, login, busca,
  carrinho, mensagens e alertas permanecem no shell.

### Pendências antes de promover a RC

- Revisão visual Firefox em larguras móveis e amplas, nos modos claro e escuro,
  com zoom e teclado.
- Revisão assistiva das páginas reais que usam pager, mensagens e teasers.
- Não trocar tiles OSM por CartoDB nem introduzir endpoint de pontos sem decisão
  e revisão dos contratos de privacidade, acesso, cache e purpose.

## Decisões pendentes do responsável

- Chave neutra de purpose no `domain_presentation` (Fase 3): aprovar a PR no Portal.
- Dropdown do menu de conta: lista final de itens além de Minha conta e Sair, e texto do contador do sino (ADR-047, pendências).
- Verificação de `core/misc/ajax.js` no Drupal 11.5 e definição dos exportables e do formulário do wizard do LMS (ADR-044).
- Migração para Drupal 12: data, sem definição (ADR-045).

## Ordem sugerida a partir daqui

1. Fechar a Fase 2 (avatar do Portal e validação do menu de conta).
2. Fase 3: chave neutra de purpose, em PR própria no Portal.
3. Fase 2 (restante): Institution Bar e Domain Header revisados, rodapé e regiões do `page.html.twig`.
4. Fase 4: UI Styles, após checar compatibilidade com 11.5 e 12.
5. Fase 5: componentes faltantes na ordem de uso (`avatar`, `author-box`, depois `breadcrumb`, `tabs`, `accordion`, `dialog`, `form-field`, `progress`, `data-table`, `empty-state`, `price`), e templates por tipo de conteúdo.

## Documentação deste tema

- [README.md](../README.md): visão geral, estrutura e como adicionar componente.
- [AGENTS.md](../AGENTS.md): regras para agentes.
- [architecture.md](architecture.md): camadas, contrato com o Portal, JavaScript e tokens.
- [components.md](components.md): catálogo com props, slots e estado.
- [gates.md](gates.md): validadores e como rodar.

## Compatibilidade e limites

O tema é desenvolvido no Drupal 11.5 instalado e declara `^11.5 || ^12`. A
execução no Drupal 12 ainda requer validação em Core 12. O Runtime de
desenvolvimento não equivale a validação Alpha ou produção.

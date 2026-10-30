# Inventário visual OpenCulturas Next 3.1.5

**OCS-F0 — fonte upstream verificada em 2026-10-29; ACULTA route notes históricas.** Auditoria estática e baseline inicial. Este documento documenta a fonte visual e capturas históricas; não certifica paridade das telas autenticadas. Para rotas, owners e estado de captura atuais, consulte a [matriz de paridade reconciliada com ADR-038](visual-parity-matrix.md). Todas as referências `/studio*` abaixo são anteriores à migração e não são instruções de roteamento vigentes.

## Proveniência e verificação

- Pacote oficial Drupal.org: `https://ftp.drupal.org/files/projects/openculturas-3.1.5.tar.gz`.
- Em 2026-10-30, o tarball oficial foi baixado e validado localmente:
  SHA-256 `2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`.
  O inventário de nomes confirma `openculturas/profile/themes/opcult/`; a cópia
  de trabalho e a extração ficam fora do repositório em
  `/home/piradopirata/aculta-suite-out/openculturas-3.1.5-source/`.
- Raiz do tema moderno: `openculturas/profile/themes/opcult/`.
- `profile/openculturas.info.yml` declara `version: '3.1.5'`; `composer.json` declara GPL-2.0-or-later e requer Drupal Core `~11.4.11`.
- Verificação da referência pública em `https://www.openculturas.org/en`: HTML carrega o tema `opcult`; o `css/opcult.css` servido tem SHA-256 `1441f91a50e1461825c676e9fd1f33b08a176b0c7c897033a1e1432c5b3315b9`, idêntico ao CSS compilado no pacote 3.1.5. Isso confirma a identidade dessa folha servida, mas não prova que todos os módulos ou configurações do site estejam no pacote.
- A verificação local repetiu o SHA-256 de `profile/themes/opcult/css/opcult.css` e obteve o mesmo valor da referência pública acima; versão e path foram conferidos diretamente nos arquivos do tarball. CSS, Twig, SVGs e ícones upstream não foram copiados. Os dois arquivos WOFF2 de Montserrat foram copiados sem alteração somente depois da verificação independente da licença OFL, registrada abaixo e no README do tema.
- Capturas Firefox ESR visível: `$SUITE_OUT/openculturas-1440.png` e `$SUITE_OUT/openculturas-390.png`. O perfil temporário fica em `$SUITE_OUT/profiles/` e é removido ao encerrar a sessão. Capturas autenticadas do Studio ficam em `$SUITE_OUT/aculta-studio-*.png`.
- O manifesto do pacote declara GPL-2.0-or-later; `sprite/svg/LICENSE_phosphor-icons.md` registra a licença do sprite Phosphor. O tarball, por si, não esclarece a licença dos arquivos Montserrat/Open Sans. Antes de incluir Montserrat, a família exata foi verificada no metadata oficial Google Fonts (`OFL`) e o texto SIL OFL 1.1 foi arquivado junto dos WOFF2 locais. Open Sans, logos, imagens, SVGs, CSS e Twig upstream não foram copiados. Nenhum CSS de terceiros foi derivado literalmente.

## Arquivos concretos inspecionados

| Área | Fonte 3.1.5 | O que demonstra | Implicação no ACULTA |
|---|---|---|---|
| Estrutura da página | `templates/layout/page.html.twig`, `templates/layout/layout.scss`, `templates/layout/layout-regions.scss` | Header, menu off-canvas, breadcrumbs, intro, título, conteúdo, rodapé e regiões | Inspiração restrita ao conteúdo interno do Studio; Institution Bar e Domain Header não mudam |
| Header e navegação | `templates/layout/header.scss`, `templates/navigation/menu.html.twig`, `templates/navigation/menu.scss`, `templates/navigation/header-toggles.scss`, `templates/navigation/tabs.scss`, `templates/block/block--system-menu-block.html.twig` | Agrupamento das utilities, highlights, menu mobile, tabs e estados de foco | Reutilizar `patterns/context-tabs` e navegação real do Studio; sem copiar Twig upstream |
| Dashboard | `templates/views/user-dashboard-views.scss` | Profile finder, “My profiles”, bookmarks, “My content” e pager/carregar mais | Referência de composição. O CSS não prova que cada View esteja habilitada/visível; no ACULTA mostrar apenas tarefas e conteúdo reais |
| Views e listagens | `templates/views/views-exposed-forms.scss`, `templates/views/view-search.scss`, `templates/views/view-related-*`, `templates/node/node--compact.html.twig`, `templates/node/node--date--compact.html.twig`, `templates/node/node--teaser-unified.html.twig` | Filtros, cards compactos, teasers e resultados | Reutilizar `patterns/card`, `content-grid`, `content-section` e consumers existentes; checar cada consumer antes da mudança |
| Forms e feedback | `sass/base/_forms.scss`, `sass/base/_buttons.scss`, `sass/base/_messages.scss`, `templates/contrib/password.scss`, `templates/components/status-messages.html.twig` | Controles, botões, mensagens e feedback do Core | Preservar Form API e markup do owner; estilos apenas dentro do Studio |
| Tipografia e cores | `sass/abstracts/_colors.scss`, `sass/abstracts/_typography.scss`, `sass/base/_custom-properties.scss`, `sass/fonts.scss`, `css/fonts.css` | Roxo `#9d1980`, verde-interação `#16635e`, cores funcionais, tokens, Montserrat/Open Sans | Valores são evidência para F1. Montserrat foi verificada sob OFL e incluída localmente; Open Sans não foi copiada |
| Dark mode | `sass/dark-mode/_index.scss`, `sass/dark-mode/_misc.scss`, `sass/abstracts/_colors.scss` | Overrides de cores para modo escuro | Derivar escopo com os tokens ACULTA existentes; testar os dois temas sem alterar preferência Runtime |
| Componentes e ícones | `templates/block/`, `templates/media/`, `templates/paragraph/`, `templates/node/`, `sprite/README.md`, `sprite/svg/LICENSE_phosphor-icons.md` | Templates de block, media, parágrafo, node, SVG sprite e atribuição Phosphor | Não copiar markup, ícones ou assets; preferência por SDC ACULTA atual e seus slots |
| Bibliotecas e JS | `opcult.libraries.yml`, `js/opcult.js`, `opcult.breakpoints.yml`, `package.json`, `gulpfile.mjs` | CSS compilado, biblioteca de fonte, dependências Drupal, behavior de UI e breakpoints | Não ativar o tema nem criar pipeline Sass paralelo; tamanhos de referência: 480, 768, 992, 1100 e 1600 px |

### OCS-F1 — tokens de fidelidade iniciais

Tokens com prefixo `--aculta-studio-fidelity-*` registram cores, tipografia,
espaçamentos e superfícies observados no pacote 3.1.5. Os tokens e a família
Montserrat ficam limitados aos wrappers `.studio` e `.aculta-profile-editor`;
Institution Bar, Domain Header e outros purposes não recebem esse acabamento.
Os estilos são implementados no tema ACULTA, sem copiar CSS, Twig ou markup
upstream. A declaração local `@font-face` usa apenas os WOFF2 Montserrat
licenciados e documentados nesta auditoria.

Os pares aplicados ao layout e às abas têm contraste calculado acima de 7:1 no
claro e acima de 12:1 no escuro. A cor muted clara upstream `#7b7d85` tem
4,11:1 sobre branco; foi registrada como referência, mas não é usada como texto
normal. A suíte Firefox no Marley passou contraste e navegação; a validação de
todos os componentes e áreas permanece pendente.

Em 2026-10-30, OCS-F1 passou a usar Montserrat variável em WOFF2 local, com os subconjuntos
Latin/Latin Extended do pacote 3.1.5. A licença foi verificada no
[metadata oficial do Google Fonts](https://github.com/google/fonts/blob/main/ofl/montserrat/METADATA.pb)
(`OFL` para Montserrat); o [texto da licença SIL OFL 1.1](https://raw.githubusercontent.com/google/fonts/main/ofl/montserrat/OFL.txt) acompanha os arquivos
em `web/themes/custom/aculta420/assets/fonts/montserrat/`. Os bins permanecem
inalterados e não há chamada a CDN. A família está aplicada somente aos
wrappers `.studio` e `.aculta-profile-editor`; tokens de fidelidade também foram
movidos para esses escopos. O token de tamanho/cor muted `#7b7d85` segue sem uso
como texto normal por não atingir AA sobre branco. Open Sans não foi copiada,
pois não é necessária para a família padrão/head identificada no tema Next.

F1 continua parcial: cartões, formulários e demais componentes precisam de
acabamento e comparação real. Em Firefox ESR visível, a folha candidata de
tokens Studio e `studio.css` foi injetada no consumer Marley do Runtime. Passou
63/63 checks executáveis; uma verificação de edição foi `SKIP` sem fixture de
publicação própria. Foram confirmados Montserrat carregada, gutter global
inalterado, contraste mínimo medido de 6,49:1 claro e 11,51:1 escuro, geometria
igual nos modos e zero overflow nas larguras 320/360/390/599/600/767/768/1024/
1280/1440. As capturas 1280/390 foram inspecionadas em
`/home/piradopirata/aculta-suite-out/ocs-f1-candidate-20261030-after-500-fix/`.
É validação da folha candidata sobre markup servido, não da implantação desta
branch; não compara um dashboard autenticado equivalente do upstream e não
cobre todos os consumers.

### OCS-F3 — variante do SDC de cartão para o Hub (2026-10-30)

`templates/node/teasers.scss` e `sass/abstracts/_teasers.scss` do mesmo tarball
3.1.5 definem cartões com margem vertical de metade do row-gap, padding de
1rem/2rem, borda transparente de 1px, raio de 1.5rem e sombra centrada. Esses
valores foram reexpressos no SDC existente `patterns/card` como variante
`studio_fidelity`; o consumidor é o adaptador visual da entrada `account`, que
mantém links, access/cache e slot existentes. A story não adiciona links reais.
Stylelint, schema (55 componentes/756 checks), Unit (20 testes/121 assertions)
e fixture Firefox (45/45 em nove larguras) passaram. Captura Drupal/Runtime
permanece pendente. Nenhum CSS ou Twig upstream foi copiado.

Na fixture Firefox 320px, o H1 de 3rem herdado da escala Next excedeu a largura
do viewport. O tema agora reduz o H1 progressivamente com `clamp(2rem, 7vw,
3rem)` até a escala desktop, preservando a hierarquia e sem mudar o texto ou a
ordem DOM. A correção surgiu do consumer responsivo; será retestada nas nove
larguras da matriz antes do registro do resultado.

Também passaram lint CSS/JS, SDC schema/discovery (55 componentes, 756 checks),
Design Foundations (2.418 checks e 159 fixtures), browser validators (21), mapa
módulo/tema (211), boundary módulo/tema (3.442), Runtime read-only Foundation
(351) e Shell (11). Unit PortalUiHooks: 17/110; dois Kernel de formulários de
Eventos: 9/141 com 24 depreciações de Core/contrib. PHP lint passou. PHPCS
Drupal/DrupalPractice dos dois scripts do Design Foundations passou após
formatação e documentação das funções. CSS/JS lint e schema permanecem verdes.
Zoom 200/400, no-JS, reduced motion, A/B, revisão de todos os consumers e o gate
Fidelity F7 continuam pendentes.

### OCS-F2 — navegação interna do Studio (implementação parcial)

`templates/navigation/tabs.scss` do pacote 3.1.5 (tarball oficial conferido pelo
SHA-256 `2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`)
diferencia `.tabs` primárias de `.tabs.secondary`: as primeiras usam indicador
inferior discreto por link; as secundárias usam cor de link e indicador de
página ativa mais espesso. O tema adapta o padrão nos seletores existentes
`.studio__tabs` e `.studio__subtabs`, remove as molduras Bootstrap dos links e
dos `<li class="list-group-item">`, e mantém flex-wrap como base mobile. A
interação secundária usa os tokens de superfície/interação em claro e escuro.
Não foi copiado CSS upstream. A navegação permanece composta por links comuns
autorizados; nenhum destino, rota, registry ou SDC foi criado.

No Firefox ESR visível, Marley e a navegação contextual de Conta passaram
57/57 verificações executáveis com CSS candidato reinjetado em cada navegação:
estrutura e links dos três níveis, estilo computado sem moldura list-group/nav,
AJAX/URL/histórico, foco visível após Tab, teclado, nomes acessíveis, contraste,
claro/escuro com geometria igual e overflow em Marley (320, 390, 767, 768, 1024,
1280 e 1440 px) e Conta (320, 390, 768 e 1280 px). A Conta manteve seus 9 links
e exatamente um estado atual; nenhum screenshot de dados privados foi salvo.
Uma verificação de edição foi ignorada por não haver publicação própria na
conta. Resultados estão em
`/home/piradopirata/aculta-suite-out/ocs-f2-marley-account-border-final-20261030/`.
A comparação pareada com dashboard autenticado OpenCulturas e cobertura de
GanjaLab, WE Sativa, Green Stash e demais consumers Studio continuam pendentes;
F2 permanece parcial.

## Referências visuais e limites

O site público `www.openculturas.org/en` usa a folha de estilo exata que acompanha o pacote e serve como referência verificável da linguagem visual geral. A captura do site não equivale a uma instalação limpa, porque há CSS agrupado e asset injector próprio. A página autenticada do dashboard OpenCulturas não foi disponibilizada para esta auditoria; sua estrutura foi mapeada pelo `user-dashboard-views.scss`, sem afirmar equivalência pixel-perfect.

O Runtime ACULTA confirmado para esta sessão é `toca`, URI `http://default`, Drupal 11.4.10, banco `aculta_runtime`, no checkout `/home/piradopirata/projetos/aculta`. `/studio/marley` e `/studio/conta` retornam 403 para sessão anônima, conforme verificação anterior. A captura autenticada usa somente a conta sintética `teste.comum` e não envia formulários. O gate visual completo, A/B, acessibilidade e demais sessões/estados continua pendente.

**Execução OCS-F0 nesta rodada:** após ajustar o script com WebDriver BiDi, Firefox ESR visível (`153.5.1esr`, `DISPLAY=:0`) capturou a página pública OpenCulturas e `/studio/marley` autenticado em viewport CSS exato de 1440×1000 e 390×844. As quatro imagens e `baseline.json` estão em `/home/piradopirata/suite-out/aculta-studio-ocs-f0/` (fora do Git). A conta `teste.comum` não enviou formulários. A comparação visual foi inspecionada manualmente; a página OpenCulturas é a homepage pública e **não** corresponde a dashboard autenticado, portanto não permite declarar paridade de tela. No screenshot Studio aparecem elementos do toolbar de administração da conta usada; isso deve ser considerado ao selecionar fixture/estado nas capturas de aceite. Baseline F0 executado para duas larguras, não para todos os breakpoints. Nenhum `updatedb` ou `config:import` foi executado.

O JSON registrou `documentScrollWidth` e `bodyScrollWidth` iguais ao viewport
nas duas larguras e nas duas páginas. Isso só demonstra ausência de overflow
horizontal nas rotas/estados capturados, não valida a responsividade global,
WCAG, zoom ou qualquer outra tela.

Uma tentativa expandida anterior foi bloqueada antes da autenticação porque
Drush `uli` não descobriu `aculta_document_viewer_group:course_document`; essa
tentativa está preservada em `baseline-deferred.json`. Após a mudança do
ambiente, a execução seguinte capturou as oito rotas em duas larguras
(`/studio`, Marley, GanjaLab, WE Sativa, Conta, Perfil, Green Stash e Planos
Mercado Pago). O Hub retornou documento vazio, GanjaLab exibiu Minha Conta em
vez do painel LMS e Green Stash negou acesso à conta sintética sem permissão.
As outras rotas renderizaram; os scroll widths coincidiram com a viewport a
390/1440 px. Isso é evidência parcial de captura, não aprovação de F0. Não foi
executado `updatedb` ou `config:import`.

## Reuso existente no ACULTA420

- `patterns/card`: `default`, `compact`, `horizontal`, `overlay`; SDC com story por variante.
- `patterns/context-header`, `patterns/context-tabs`, `patterns/content-section`, `patterns/content-grid` e `feedback/empty-state`.
- `patterns/profile-editor`; `content/profile-identity`, `content/profile-bio` e `content/profile-social`.
- `feedback/stat-counter`, `content/accessibility-badges` e `content/sponsor-logos` existem, mas status experimental/consumer devem ser conferidos antes de uso.

O componente `bookmark-toggle` tem consumer no Fórum; sua presença não autoriza exibir uma lista “Meus salvos” no Studio. Conta privada, Perfil público e organização continuam experiências distintas.

## Cobertura Studio e Green Stash (reescaneamento histórico pré-ADR-038)

O inventário de providers e rotas desta seção registra o código inspecionado
em `3c1691fc5`; a migração posterior ADR-038 distribuiu as Suites entre seus
purposes donos e aposentou `/studio*`. Não use as URLs abaixo como mapa atual;
a matriz corrente vinculada no cabeçalho registra rotas, evidências e
pendências verificadas na árvore posterior.

No HEAD `3c1691fc5`, o `aculta_studio.services.yml` registra sete providers: Conta, Perfil, Marley, GanjaLab, WE Sativa, Planos Mercado Pago e Meus Salvos. O `aculta_commerce` registra dinamicamente um oitavo provider, Green Stash, quando Studio e Commerce estão disponíveis. O Hub tem controller canônico `/studio`; essa lista é inventário de código, não prova de disponibilidade por usuário no Runtime. O LMS é owner da rota `/studio/ganjalab`, que usa
o compositor de aprendizagem no Studio. Foram encontrados controladores de Hub, Marley, GanjaLab, WE Sativa, Conta, Perfil e Salvos, além dos plugins Marley de Revista, Fórum, Galerias, Cursos e Wiki. GanjaLab editorial (MarleyTab/Presenter) e
GanjaLab do estudante/LMS (`/studio/ganjalab`) são experiências distintas.
O inventário visual e os estados por tela estão em
[`visual-parity-matrix.md`](visual-parity-matrix.md); rotas, consumers,
visibilidade por conta e aparência ainda não foram homologados em Runtime.



### Registro dinâmico Studio confirmado em 2026-10-30

| Provider | ID / rota canônica | Disponibilidade e acesso constatados no código | Estado visual nesta auditoria |
|---|---|---|---|
| Conta | `conta` / `/studio/conta` | Sempre disponível; autenticação e `access aculta portal` | Conta privada; S-R5 e visual A/B pendentes |
| Perfil | `perfil` / `/studio/perfil` | `aculta_user` ativo; autenticação e `access aculta portal` | Editor separado da Conta; S-R4/S-R5 e visual A/B pendentes |
| Marley | `marley` / `/studio/marley` | Provider sempre disponível; acesso exige login, permissão do Portal e pelo menos uma aba acessível | Editor/listas reais; Runtime anteriormente capturado; equivalência Next pendente |
| GanjaLab estudante | `ganjalab` / `aculta_lms.student_dashboard` | Provider disponível quando `LearnerDashboardComposer` opcional está injetado; rota e ACL final pertencem ao LMS | Não homologado; baseline anterior mostrou conteúdo de Conta no lugar do painel LMS, Pede-A em aberto |
| WE Sativa | `we_sativa` / `/studio/we-sativa` | Disponível com `aculta_wiki.contributor_workspace`; acesso delegado ao workspace Wiki | Workspace real do owner; captura/ACL e autoria versus moderação pendentes |
| Planos Mercado Pago | `mercado_pago_plans` / `aculta_pagamentos_mercadopago.studio_plans` | Módulo opcional ativo; login, Portal e `administrar doacoes` | Condicional; visual e cenário de módulo ausente pendentes |
| Meus Salvos | `salvos` / `/studio/salvos` | Provider no registry; login e `access aculta portal`; controller usa `SavedContentQueryInterface` | UI de salvos/seguindo/recomendações existe no código; testes de isolamento/cache registrados em ARQ-07B, captura visual e A/B no Runtime pendentes |
| ACULTA STUDIO GREEN STASH | `green_stash` / `/studio/green-stash` | Registro condicional via `AcultaCommerceServiceProvider`; ACL de entrada é `GreenStashAccessPolicy`, além dos grants por loja/operação | Área comercial integrada ao Studio. Provider ainda mostra mensagem de preparação; produtos/envios têm fluxos em código, mas acesso autorizado e operação não foram homologados. Não criar shell nem menu global paralelo. |

`aculta_studio.services.yml` registra os sete primeiros providers; Green Stash
entra dinamicamente pelo service provider de Commerce. Todos usam purpose
`account`. O módulo e as rotas proprietárias determinam disponibilidade e
acesso efetivos. A matriz visual permanece parcial: este inventário não altera
rotas/serviços nem transforma estados de preparação em recursos operacionais.

### Green Stash integrado — capacidades observadas no código

As rotas canônicas em `aculta_commerce.routing.yml` ficam em
`/studio/green-stash` no purpose `account`, protegidas pela permissão comercial.
O nome público é **ACULTA STUDIO GREEN STASH**. Os antigos paths
`/estudio/loja/*` foram aposentados sem alias/redirect em 2026-10-30; os IDs
internos de rota e contratos Commerce foram preservados. O módulo Commerce registra
`GreenStashExperienceProvider` no registry compartilhado do Studio. Esse uso
técnico não é evidência de plataforma independente: a apresentação deverá
permanecer contextual ao shell/navegação ACULTA Studio e Commerce continuará
dono de dados, acesso e operações.

- **Produtos:** lista, detalhe e formulário de criação de produto estão no
  código; o reader filtra pela loja resolvida e ACL de entidade. O teste de
  submissão, variações e estado resultante não foi executado nesta auditoria.
- **Envios:** reader, paginação, detalhes de remessa e links de transição estão
  no controller; a transição usa Form API de confirmação/CSRF e permissões
  comerciais específicas. Runtime e ACL loja A/B permanecem pendentes.
- **Pedidos e estoque:** existem slugs de navegação, mas nenhum conteúdo
  operacional correspondente foi encontrado no controller nesta inspeção.
- **Promoções, clientes, relatórios e configurações:** slugs de navegação
  existem; o conteúdo é estado preparatório. Não apresentar como recurso ativo.
- A navegação e os estados vazios ainda estão em markup próprio do módulo; não
  foi confirmado consumer de SDC para a UI Green Stash.

### Pede-A — documentação/contrato visual do Commerce

Sem editar arquivos de ownership Commerce, pedir ao owner que harmonize a
descrição do produto como área comercial integrada ao Studio e deixe explícita
a diferença entre slugs planejados e operações disponíveis. Referências que
hoje conflitam com a decisão atual ou entre si:

| Caminho | Contrato esperado | Teste/evidência do owner |
|---|---|---|
| `aculta_commerce/src/Experience/GreenStashExperienceProvider.php` | Comentário/entrypoint descrevendo Green Stash como área comercial integrada do Studio; provider técnico não é declaração de produto independente. | Unit de registry/visibilidade e consumer do Hub/Studio; sem duplicar navegação global. |
| `aculta_commerce/src/AcultaCommerceServiceProvider.php` | Registro dinâmico apresentado como contribuição de uma área comercial integrada, sem semântica de plataforma paralela. | Teste de registry com módulos Commerce/Studio opcionais, acesso e rota canônica intactos. |
| `aculta_commerce/src/Navigation/GreenStashNavigation.php` | Navegação contextual de seção deve ficar visualmente dentro do contexto Studio, não virar menu global. | Teste de links/aria-current e autorização por seção; confirmar shell comum, sem destino Drupal admin. |
| `aculta_commerce/src/Controller/GreenStashController.php` | Documentar estado real por seção: produtos, envios e transições disponíveis; slugs restantes preparatórios. | Unit/Kernel para seção por seção, acesso por loja e não exposição de ação ausente. |
| `aculta_commerce/README.md` | Distinguir catálogo/criação de produto e envios do restante em preparação; corrigir narrativa histórica de “experiência independente” para o modelo atual integrado. | README alinhado ao código e ao Operations Contract, sem declarar pedidos/estoque etc. operacionais. |
| `aculta_commerce/docs/GREEN-STASH-UX-MOBILE-FIRST.md` | Acrescentar retificação datada da decisão: área integrada sob shell Studio, sem menu/shell/autenticação/checkout independente; atualizar linguagem sem apagar histórico. | Auditoria de links e estados contra rotas/permissões atuais. |
| `aculta_commerce/docs/ROADMAP.md` | Retificar as descrições de “experiência independente” para área comercial integrada e alinhar GS-0.2 UX ao contexto Studio; preservar atribuição de gestão funcional ao Commerce. | Revisão cruzada do road map, Operations Contract e etapas do produto. |

### Pede-A — estabilização do Runtime para baseline Studio

Na tentativa expandida, `drush uli` interrompeu antes da sessão porque o
container falhou ao descobrir `aculta_document_viewer_group:course_document`;
o manager listou `aculta_document_viewer:course_document` entre os IDs
disponíveis. O worktree da frente de código contém mudanças não commitadas no
submódulo/plugin de documentos; a Frente B não alterou nem reconciliou esses
arquivos. Contrato solicitado ao owner: manter a relação/configuração esperada
resolvível ou fornecer a migração aprovada, sem quebrar a ACL dos documentos.
Aceite para retomar baseline: Drush `uli` e route discovery no checkout de
teste concluírem sem erro, mais Kernel de integração do acesso à relação de
documento. Qualquer `updatedb`/`config:import` no Runtime depende do procedimento
e autorização próprios.

### Pede-A — reconciliação de `config/sync` e FAQ do Runtime

A decisão do responsável é que `aculta_runtime` corresponda ao `config/sync` do
head, preservando `aculta_faq` e sua configuração no Sync. A inspeção anterior
encontrou seis nós FAQ ativos (NIDs 567–572), porém o Sync não contém a
extensão nem a configuração do bundle; também há três extensões listadas como
ativas no banco sem código no checkout: `aculta_document_viewer_group`,
`aculta_pdf_viewer` e `aculta_pdf_viewer_group`. A Frente B não exportou nem
importou configuração e não alterou os dados.

| Caminho/contrato | Entrega necessária do owner | Teste/evidência de aceite |
|---|---|---|
| `config/sync/core.extension.yml`, configs `node.type.aculta_faq` e `field.*` relacionados, módulo `aculta_faq` | Reconciliar a configuração canônica para manter a extensão e o bundle FAQ no Sync e preservar os seis nós existentes; registrar export/diff revisado no head. | `config:status` sem diff inesperado; teste Kernel/Functional do bundle e acesso aos seis nós após import controlado. |
| Código e configuração das três extensões sem código no checkout | Restaurar e versionar as extensões com seus owners, ou aprovar/documentar reconciliação compatível antes de retirar sua ativação/configuração do estado de destino. | Discovery Drupal sem avisos de extensão ausente; testes de integração dos consumidores e `updatedb:status` limpo. |
| `aculta_runtime` | Após reconciliação versionada e revisão do diff, seguir `AGENTES-PROCEDIMENTO.md`: confirmar banco/tabelas, snapshot com hash, `updatedb` se necessário e import somente do Sync do head. | Snapshot identificado; `config:status` e `updatedb:status` limpos; smoke HTTP da home e loja; validar os seis FAQs e os módulos preservados. |

Até o owner concluir a reconciliação, não importar o Sync corrente: ele não
atende à decisão de preservar FAQ e a ausência de código das três extensões
impede confirmar um destino coerente. Isso mantém a etapa de runtime deste
baseline como **DEFERRED**, sem bloquear o trabalho visual estático que não
precise do Runtime.

### Pede-A — consumidores/fixtures necessários ao gate visual

| Caminho/contrato | Evidência observada | Entrega do owner e teste de aceite |
|---|---|---|
| `aculta_studio/src/Controller/StudioHubController.php` e rota `aculta_studio.hub` | GET autenticado de `/studio` carregou documento HTML de apenas 26 caracteres, sem título/corpo nem links; Firefox registrou 2 elementos. O controle não foi contornado na Frente B. | Diagnosticar a resposta vazia; Functional/Kernel deve comprovar render do hub com links às experiências existentes, filtradas por provider/ACL/rota, e vazio explícito quando nenhuma estiver disponível. Repetir smoke HTTP autenticado sem mudança de rota/ACL. |
| `aculta_lms.routing.yml` e consumer de `/studio/ganjalab` | Captura autenticada mostrou título/conteúdo de Minha Conta, não painel de estudante; rota não tinha elemento `[data-aculta-studio]`. | Confirmar rota canônica e encaminhamento do dashboard LMS sem alterar ACL/contratos visuais; Functional para aluno matriculado e usuário sem matrícula, mais browser smoke autenticado. |
| `aculta_commerce/src/Access/GreenStashAccessPolicy.php` e fixture de loja | Captura histórica de `/estudio/loja` respondeu “Acesso negado” para a conta sintética `teste.comum`; esta conta não é evidência de operador autorizado. A rota oficial atual é `/studio/green-stash`. | Disponibilizar no ambiente de teste um operador sintético com loja autorizada, sem dados pessoais/loja real; testar leitura positiva e negativa entre duas lojas, incluindo listagem e operações de produtos/envios, sem ampliar permissão em Runtime pela Frente B. |
| Contrato Domain Header / apresentação ACCOUNT | Captura autenticada histórica em `/studio/marley`, GanjaLab, WE Sativa, Conta, Perfil, Planos e `/estudio/loja` mostrou Institution Bar (`.aculta-institution-bar`) presente, mas `.aculta-header` ausente em todas as oito rotas. A rota comercial oficial atual é `/studio/green-stash`. | Owner de Domain/Portal deve confirmar se a ausência do Domain Header é intencional no purpose `account` ou corrigir a composição para preservá-lo conforme a decisão visual. Teste browser autenticado por purpose que confira Institution Bar e Domain Header sem duplicá-los; não introduzir workaround visual da Frente B. |

As evidências detalhadas e 18 screenshots estão em `baseline.json` e
`$SUITE_OUT`; a tentativa anterior permanece em `baseline-deferred.json`.
Não declarar o Hub, a rota LMS de GanjaLab nem Green Stash homologados até
resolver os pedidos acima e repetir as capturas com fixtures/rotas corretas.

## THEME-MF0 e OCS-F0 — limites do que foi verificado

A varredura registrou os 92 arquivos de estilo, 11 scripts e 98 templates do
tema no inventário tabular. As contagens de regras não validam cascata,
renderização ou ausência de overflow. Ainda estão pendentes: resolução completa
de bibliotecas anexadas por Views/forms e módulos; associação de cada rota de
purpose aos estilos efetivamente carregados; tabela de breakpoints e larguras
fixas que considere CSS inline/HTML/JS; baseline visual global por purpose; e
captura Studio autenticada pareada. Portanto MF0/F0 continuam parciais e não
liberam implementação além de decisões estéticas já documentadas.

## OCS-F0 / THEME-MF0 — captura responsiva expandida (2026-10-29)

Execução Firefox ESR visível com `OCS_WIDTHS=320,360,390,599,600,768,1024,1280,1440`,
`ACULTA_ROOT=/home/piradopirata/projetos/aculta` e URI do servidor de
desenvolvimento. O checkout servido estava no commit `f2708e60f`; no momento
da captura, a branch PR estava em `5734bed87`. Os arquivos versionados do tema,
Studio e Commerce desses dois heads eram idênticos; o checkout servidor também
tinha alterações locais não commitadas somente no módulo Eventos. Portanto,
esta é evidência responsiva dos arquivos visuais compartilhados, mas não é uma
homologação do head completo nem de Eventos.

O JSON contém 81 registros e 81 PNGs fora do repositório em
`$SUITE_OUT/aculta-ocs-f0-responsive-20261029/`: nove larguras da homepage
pública OpenCulturas e nove para cada uma das oito URLs Studio. Todas as páginas
Studio renderizadas tiveram `documentScrollWidth == documentWidth` e
`bodyScrollWidth == width` nas nove larguras (nenhum overflow horizontal
medido). `/studio` permaneceu documento vazio nas nove larguras;
`/estudio/loja` mostrou acesso negado para a conta sintética nas nove;
`/studio/ganjalab` continuou renderizando Minha Conta em vez do painel LMS. As
outras cinco rotas — Marley, WE Sativa, Conta, Perfil e Planos Mercado Pago —
renderizaram nas nove larguras. A homepage pública upstream também renderizou
nas nove e não é equivalente ao dashboard autenticado OpenCulturas.

A Institution Bar estava presente nas sete URLs que renderizaram conteúdo; o
Domain Header `.aculta-header` esteve ausente em todas as oito URLs, inclusive
o hub vazio. Os marcadores `[data-aculta-studio]` apareceram somente em Marley.
Screenshots de Marley em 320 e 1440 px foram inspecionados manualmente; outros
screenshots foram guardados para revisão posterior. A conta sintética não
submeteu formulários. Isso prova apenas medidas de largura/scroll e estados
capturados; não prova fidelidade comparativa, acessibilidade, zoom, teclado,
modos claro/escuro, reduced motion, AJAX/no-JS, outros purposes ou conteúdo LMS/
Commerce autorizado. O status permanece **PARCIAL**, não PASS.

### OCS-F0 — probe de teclado e foco visível (2026-10-29)

`tests/browser/aculta-studio/keyboard-focus.py` usou Firefox ESR visível no
servidor de desenvolvimento, com 12 Tabs por rota em Marley, Conta e Perfil,
nas larguras 320/1440. Foram 72 registros; todos tiveram `:focus-visible`
verdadeiro e outline calculado diferente de `none`. As seis capturas ficaram
em `$SUITE_OUT/aculta-ocs-f0-keyboard-20261029/`; a tela Marley/320 foi
inspecionada e mostra outline no link de skip. Nenhum formulário foi enviado.
O probe cobre somente os primeiros 12 Tabs por três rotas/duas larguras, não
representa análise WCAG, teclado completo/Shift+Tab, modais, widgets ou outros
purposes. O servidor rodava o código visual de tema/Studio/Commerce comum aos
heads `f2708e60f` e `5734bed87`, com mudanças locais restritas a Eventos; essa
evidência não homologa o head completo da PR.

**Atualização do probe:** uma segunda execução percorreu a grade completa
(320/360/390/599/600/768/1024/1280/1440) com Tab e Shift+Tab em Marley, Conta
e Perfil. Foram 648 observações: 621 alvos focáveis, todos com
`:focus-visible` e outline calculado presente; as 27 restantes retornaram ao
`body` após completar o percurso reverso. As 27 capturas estão em
`$SUITE_OUT/aculta-ocs-f0-keyboard-full-20261029/`; Marley/320 foi inspecionada.
Continua sendo amostra de 12 passos, não auditoria WCAG nem percurso completo.

### OCS-F0 — modos de cor e movimento (2026-10-29)

`theme-modes.py` rodou em Firefox ESR visível para Marley, Conta e Perfil,
nas larguras 320/1440, em claro explícito, escuro explícito + reduced motion e
auto. As 18 capturas não apresentaram overflow medido. `matchMedia` confirmou
`prefers-color-scheme: dark` em 6/6 páginas dark e
`prefers-reduced-motion: reduce` em 6/6 da execução combinada; ambos falsos
em light/auto no perfil padrão. Tokens de superfície/texto primário: claro
`#f2f7f0`/`#0c3c29`, escuro `#171513`/`#f4efe8`. O screenshot escuro Marley/320
foi inspecionado. O tema explícito foi aplicado apenas no atributo DOM dentro
do browser temporário, sem salvar configuração/preferência Drupal. A evidência
não substitui avaliação de contraste AA, demais states/components/purposes ou
o gate visual completo.

**Grade completa:** em 2026-10-29, as mesmas três rotas foram capturadas em
nove viewports nos modos light, dark+reduced e auto: 81 PNG/JSON records.
`matchMedia` confirmou os modos escuro/reduzido em 27/27 capturas; light e auto
(perfil padrão do Firefox claro) em 0/27 para cada preferência. Os tokens
continuaram coerentes por modo e não houve overflow medido em nenhuma captura.
Artefatos ficam em `$SUITE_OUT/aculta-ocs-f0-mode-{light-full,dark-reduced-full,auto-full}-20261029/`.
A cobertura permanece restrita a Marley, Conta e Perfil.

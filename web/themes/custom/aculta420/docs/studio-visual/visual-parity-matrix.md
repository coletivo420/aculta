# Matriz de paridade visual do OpenCulturas Next → ACULTA Studio

**OCS-F0 — estado corrente reconciliado com ADR-038 (2026-10-30).** A fidelidade visual OpenCulturas permanece restrita às experiências ACULTA Studio. As Suites usam o purpose/domain de seus owners; o framework `aculta_studio` e o registry continuam compartilhados, sem Hub público em `/studio`.

### Matriz corrente de rotas e evidências

As rotas abaixo seguem o mapa canônico ADR-038, os providers/owners atuais e os baselines Firefox `aculta-studio-adr038-20261030` e `ocs-f0-rerun-20261030`. A retificação vinculante de 2026-10-30 mantém `/studio/we-sativa/**` no purpose `wiki` e `/studio/green-stash/**` no purpose `shop`; os demais caminhos `/studio*` respondem 404 sem redirect. As capturas anteriores em `/wesativa` e `/greenstash` registram baseline histórico e não homologam os novos paths. A revalidação autenticada cobriu nove destinos ACULTA em 390/1440px; não equivale a um dashboard autenticado upstream.

| Área/estado | Purpose e path canônicos | Provider/owner funcional | Evidência atual | Pendência visual/aceite |
|---|---|---|---|---|
| Entrada da plataforma Studio e links para Suites | `account:/` e `account:/conta` | `AccountExperienceProvider`; links absolutos resolvidos por `DomainPurposeManager` | Labels da Conta agora coincidem com providers: ACULTA STUDIO WE SATIVA e ACULTA STUDIO GREEN STASH. Fixture alinhada ao consumer passou 250/250 em 27 larguras (320–1920px), incluindo limites upstream, container, escala tipográfica, Montserrat, alvos 44×44px, foco visível por Tab, zero overflow e zoom real 200/400%; capturas em `/home/piradopirata/aculta-suite-out/studio-card-zoom-official-names-20261031/` | Não criar Hub `/studio` nem menu global paralelo; fixture não prova o HTML/CSS integrado no Runtime; conferir Suite opcional ausente |
| Marley e navegação editorial | `account:/marley`, `/marley/{aba}[/{secao}[/{sub}]]` | `MarleyExperienceProvider`; `aculta_studio` compõe, plugins/owners mantêm ações e conteúdo | Captura candidata autenticada em 320/390/1440px: regra escopada ao marcador root/heading oculta o H1 genérico da rota somente quando existe H1 do workspace; 5/5 heading checks passaram em cada largura, breadcrumb e shell preservados e sem overflow. Navegação das cinco áreas passou wrapping, alvos ≥44×44px e zero overflow interno em 320/390/1440px claro e 320/390px escuro, sem alteração de CSS. Evidências em `/home/piradopirata/aculta-suite-out/marley-single-heading-final-20261031/`, `/home/piradopirata/aculta-suite-out/marley-navigation-targets-20261031/` e `/home/piradopirata/aculta-suite-out/marley-navigation-dark-20261031/`. Formulário `node-revista-form` recebe Fidelity somente em `body.path-marley`; Firefox fixture passou 54/54, incluindo Magazine sem token/vazamento | CSS candidata do título ainda não servida no Runtime; baseline não é comparação pareada com dashboard autenticado Next. Cobrir formulários, filtros, edição, estados, teclado/no-JS e conteúdo de dois titulares |
| Ganjalab — jornada LMS | `courses:/ganjalab` | `GanjalabExperienceProvider` → `aculta_lms.student_dashboard`; LMS mantém dados, matrícula e ACL | Firefox ESR autenticado no Runtime HEAD `2493d7535`: dashboard LMS real em 320–1440px, com `Aprendizagem`, `Meus cursos` e estados vazios de cursos/certificados; sem overflow. No reteste candidato com CSS Fidelity da branch injetada no Firefox, passou 11/11 invariantes em 320/390/768/1024/1440px; duas empty states, fontes corretas, shell presente e tokens sem vazamento. Capturas em `/home/piradopirata/aculta-suite-out/ganjalab-card-candidate-correct-domain-20261031/`. | Runtime ainda não serve o token raiz `studio-fidelity-scope` da source branch; a folha candidata foi injetada. Cobrir usuário matriculado, grants/ACL, estados de matrícula/turma/cursos, no-JS, CSS Fidelity servida, zoom/teclado e comparação pareada por consumer |
| ACULTA STUDIO WE SATIVA — workspace Wiki | `wiki:/studio/we-sativa`, `/studio/we-sativa/inicio`; ações Wiki em suas rotas próprias | `WeSativaExperienceProvider`; `aculta_wiki` mantém autoria, revisão, moderação e acesso | `/wesativa` (legado), Firefox ESR autenticado e CSS Fidelity candidata: 279/279 invariantes em 320–1440px, claro/escuro; links da Institution Bar 11,34:1/12,95:1; capturas 320/1440px inspecionadas. Ajuste candidato de ritmo vertical passou medições em 320/1440px, claro/escuro: 48px/64px entre seções, margens zeradas e sem overflow; CSS só foi injetada no navegador. | `/studio/we-sativa` não é servido neste Runtime; conteúdo estava vazio. Validar caminho oficial e autoria/revisão/moderação com fixtures autorizadas; A/B de grants, no-JS e teclado; implantar e revisar estilos servidos |
| ACULTA STUDIO GREEN STASH — área comercial integrada | `shop:/studio/green-stash` e `/studio/green-stash/*` | `GreenStashExperienceProvider`; `aculta_commerce` mantém loja, produtos, pedidos, estoque e envios | Fixture Firefox ESR atual passou 114/114 em nove larguras, claro/escuro e zoom 200/400%; verifica estado Visão geral em preparação, aviso de escopo de Produtos, apenas três links operacionais, controles tocáveis, contraste e zero overflow. Após o ajuste mobile do menu integrado, fixture passou 132/132, incluindo três áreas na mesma linha em nove larguras, foco e zoom real 200/400%; captura 320px revisada em `/home/piradopirata/aculta-suite-out/green-stash-navigation-final-20261031/`. A expansão para detalhe de produto e formulário de rascunho passou 186/186 checks, incluindo zoom real 200/400%, com capturas em `/home/piradopirata/aculta-suite-out/green-stash-detail-create-homologation-20261031/`. | CSS e conteúdo funcional do Runtime não foram homologados nesta branch; exige operador autorizado e teste A/B entre lojas. Não mostrar operações não implementadas nem encaminhar a admin Commerce |
| ACULTA Studio Culturas | `eventos:/culturas` e rotas `/culturas/eventos/*`, `/culturas/locais/*` | `aculta_events` mantém Eventos, Datas, Locais, grants e operações | Fixture Firefox ESR: 110/110 em nove viewports, claro/escuro, foco, ações, tabela e shell; na rota autenticada com CSS candidata injetada, 20/20 verificações (18 combinações de nove viewports e modos mais foco por teclado em ambos), um dashboard e headers únicos, zero overflow do documento; capturas 320/1440 revisadas | CSS ainda não servida no Runtime; markup real foi observado por injeção, mas formulários/estados, grants A/B, AJAX/no-JS, cache, ACL, zoom, WCAG e comparação pareada OpenCulturas pendentes; não aprova F10/F11 nem MF3 |
| Catálogo público de Eventos | `eventos:/` | `event_card` e catálogo proprietário de `aculta_events` | Consumer Runtime recebeu CSS candidata por injeção no Firefox; 20/20 checks em dez larguras, três cartões e sem overflow | Focal apenas: validar detalhe, shell completo, formulários, teclado, zoom e WCAG |
| Conta privada | `account:/conta` e `/conta/{dados,endereco,documentos,seguranca,seguranca/senha,conexoes,pedidos,apoio,preferencias}`; `/conta-legada` permanece | Portal/Commerce/User preservam os formulários e dados donos; `aculta_studio` compõe | Hub `/conta` capturado em 390/1440px; CSS candidata de formulários coberta por fixture 42/42 em claro/escuro; formulário canônico `/dados/endereco` no shell legado passou 50/50 checks Firefox no Runtime, incluindo JS/no-JS, nove/quatro viewports e zoom real 200/400% sem overflow; rotas antigas `/studio/conta*` foram aposentadas | S-R5 por formulário, envio Form API, estados AJAX/no-JS, CSRF, foco, lookup CEP ao vivo, A/B e comparação completa com `/conta-legada` pendentes |
| Perfil privado e identidade pública | `account:/perfil` e `/perfil/{apelido,privacidade,bio,redes/*}`; público em `main:/u/{apelido}` conforme `aculta_user` routing | `ProfileExperienceProvider`; `aculta_user` e `aculta_image`; Conta e Perfil são distintos | Fixture `profile-identity` passou 56/56 em 14 larguras; pública 22/22 em 11. Fixture Firefox de formulários passou 66/66 em claro/escuro, 320/390/768/1440px; modal privado de bio reusa Fidelity e Magazine continua isolada. `/perfil` candidata mediu sete ações em 44px ou mais, cinco larguras; zoom real 200/400% passou 7/7 e Tab alcançou os sete links a 320px com foco visível. No Runtime anônimo, `/u/pessoa-5` passou 11/11 verificações em 320/390/768/1024/1440px: SDC público presente, um H1, headers únicos, sem overflow e nenhum token ou fonte Fidelity nem marcador Studio mesmo com CSS candidata carregada. Evidências em `/home/piradopirata/aculta-suite-out/profile-action-zoom-gate-20261031/`, `/home/piradopirata/aculta-suite-out/profile-action-keyboard-20261031/` e `/home/piradopirata/aculta-suite-out/profile-public-touch-targets-20261031/`; 4/4 links passaram por Tab e zoom real 200/400%, com evidência em `/home/piradopirata/aculta-suite-out/profile-public-accessibility-final-20261031/`; nenhum form foi enviado | A CSS privada candidata não é servida no Runtime; S-R4/S-R5: grants A/B, privacidade, avatar/foto, crop, fallback, cache, leitor de tela, AJAX/no-JS, percurso completo por teclado e gate WCAG/F11 pendentes |
| Salvos, Seguindo e Recomendações | `account:/salvos` | `SavedExperienceProvider` e `SavedContentController`; consulta/cache continuam nos owners Portal/Studio | Firefox ESR fixture local: 131/131 em 320–1440px, claro/escuro e estado vazio/preenchido. Captura autenticada Runtime com CSS Fidelity candidata passou heading checks em 320/390/768/1024/1440px: um H1 de conteúdo visível, breadcrumb preservado, sem overflow e headers únicos. Evidência em `/home/piradopirata/aculta-suite-out/saved-content-heading-candidate-20261031/` | CSS ainda não servida; homologar cache privado A/B, ACL por entidade, estado de `aculta_index`/módulos opcionais, zoom/teclado/no-JS e validar apenas recomendações entregues pelo serviço |
| Planos Mercado Pago | `account:/conta/mercado-pago/planos` | Provider condicional `MercadoPagoPlansExperienceProvider`; owner `aculta_pagamentos_mercadopago` | Capturado em 390/1440px com a conta sintética; shell presente | Módulo/permissão ausente, operação real, estados e comparação detalhada pendentes |
| Revista — capa e listagem pública | `magazine:/` e `magazine:/node/{revista}` | View `revista_sessoes`; tema só apresenta os teasers e o shell | Runtime anônimo em `2493d7535`: View/hero, quatro capas e shell únicos; CSS servida passou 5/28 e falhou 23/28, inclusive CTAs de 19px com JavaScript desativado. Com `revista-listing.css` do worktree injetada somente no Firefox, passou 28/28 em 320–1440px, teclado, claro/escuro, zoom real 200/400% e nove larguras sem scripts do site; grade 1/2/3 colunas, sem overflow, títulos/CTAs ≥44px, foco 3px, links trazidos ao viewport e contraste de 12,40:1/14,50:1. Repetição atual, também 28/28, em `/home/piradopirata/aculta-suite-out/revista-cover-recheck-20261031/`; capturas e relatórios anteriores em `/home/piradopirata/aculta-suite-out/revista-cover-nojs-candidate-20261031/` e `/home/piradopirata/aculta-suite-out/revista-cover-nojs-baseline-20261031/` | Folha candidata ainda não está servida no Runtime. Estados de erro/vazio, reduced motion quando aplicável e WCAG integral pendentes; não aprova THEME-MF3 |
| Outras áreas e regressão do shell | `main`, `account`, `support`, `magazine`, `wiki`, `shop`, `courses`, `forum`, `links`, `eventos` | Tema/Portal e módulos proprietários por purpose | Reteste Firefox ESR pós-migration no HEAD `4eb116482`: 90/90 raízes anônimas × nove larguras; Institution Bar e Domain Header uma vez cada em 90/90, zero overflow e zero erro 500 | Páginas internas, contas autenticadas, zoom, teclado, claro/escuro, reduced motion e WCAG global continuam fora desse baseline |

Evidências de ACULTA Studio Culturas:
`/home/piradopirata/aculta-suite-out/culturas-dashboard-20261030/` (fixture,
110/110) e `/home/piradopirata/aculta-suite-out/culturas-runtime-dashboard-20261030/`
(rota autenticada, CSS candidata apenas no Firefox, 20/20), incluindo capturas
Firefox ESR 320/1440px em claro/escuro e `results.json`.

### Revalidação OCS-F0 — rotas canônicas, Firefox ESR visível (2026-10-30)

No checkout servido `/home/piradopirata/projetos/aculta` em HEAD `4399627c4`,
`ocs-baseline.py` capturou 20 páginas em 390×844 e 1440×1000: a homepage
pública OpenCulturas mais nove destinos ACULTA (`account:/`, Marley, Ganjalab,
WE SATIVA, Conta, Perfil, Green Stash, Culturas e Planos). Os 18 screenshots
ACULTA não tiveram overflow horizontal; cada um apresentou exatamente uma
Institution Bar e um Domain Header. Marley emitiu 23 marcadores
`data-aculta-studio`; os demais destinos não emitiram esse marcador no baseline.

Green Stash retornou acesso negado para `teste.comum` nas duas larguras. Isso
registra a falta de grant dessa conta de teste; não é inspeção visual da área
comercial e não demonstra disponibilidade das operações. As capturas móveis de
Marley, Ganjalab, WE SATIVA, Conta e Perfil foram inspecionadas; em WE SATIVA os
quatro filtros de estado quebram em linhas separadas a 390px, e sua equivalência
com o padrão Next ainda precisa de revisão. Capturas e `baseline.json`:
`/home/piradopirata/aculta-suite-out/ocs-f0-rerun-20261030/`.

A referência pública OpenCulturas em 390/1440px carrega `opcult.css` com o hash
do pacote 3.1.5 validado em [`upstream-inventory.md`](upstream-inventory.md),
mas é homepage pública, não dashboard autenticado. A comparação pareada de
dashboard, grants/estados de loja, demais breakpoints e gates de acessibilidade
continuam pendentes. O Runtime não estava no HEAD remoto da PR `88063e76b` durante
esta captura; portanto, estes resultados são baseline do checkout servido, não
homologação da cabeça candidata nem aceite OCS-F0/F11.

**Reteste de rotas oficiais no Runtime HEAD `2493d7535` (2026-10-31):** com
conta sintética autorizada, Firefox ESR capturou GanjaLab, WE SATIVA e Green
Stash em nove larguras (27 registros). Correção após inspeção dos artefatos:
`/ganjalab` mostrou a navegação compartilhada de Conta no shell e, no conteúdo
principal, o dashboard LMS real (`Aprendizagem`, `Meus cursos` e estados vazios
de cursos/certificados). A conta de teste não tinha matrícula nem certificado;
isso é o estado vazio real, não conteúdo de Conta no lugar do LMS. A captura
1440px confirma a composição. O Runtime ainda não continha o token raiz
`studio-fidelity-scope` da branch; a CSS candidata injetada não homologa a
integração nova. `/studio/we-sativa` e `/studio/green-stash` produziram
documentos vazios nas nove capturas. GET anônimo retornou 404 nos dois paths; Drush
confirmou rota Wiki ausente e Green Stash ainda em `/greenstash`. A source branch
local define os paths oficiais; o PHPUnit de navegação/provider Commerce passou
11 testes/145 assertions. `ocs-baseline.py` sinalizou `CAPTURED`, mas essa
etiqueta agregada não detecta os documentos vazios e não deve ser tratada como
aceite. Evidência em
`/home/piradopirata/aculta-suite-out/ocs-studio-routes-resume-20261031/`.
O snapshot Runtime pré-login é externo ao Git, modo 600, SHA-256
`0b00e27c827397cdd24019c26c6522f511b89987cc5f8c1387f0d9a6cdd57ec5`; nenhum
formulário foi enviado ou conteúdo/configuração alterado. A divergência bloqueia
a homologação visual de F6/F7/F8 até o Runtime servir a source branch e repetir
as capturas; THEME-MF3/OCS-F11 permanecem pendentes.

**Composição LMS candidata:** `LearnerDashboardComposer` agora emite o token
semântico `studio-fidelity-scope` na raiz do conteúdo de GanjaLab. O tema o
traduz para `.studio`, habilitando a fundação Fidelity sem alcançar Institution
Bar ou Domain Header. Testes Unit verificam emissão e tradução; ainda falta
repetir a captura no Runtime alinhado para avaliar a aparência renderizada.

O pacote oficial do OpenCulturas 3.1.5 permanece a fonte visual: o hash e os
arquivos concretos inspecionados estão em [`upstream-inventory.md`](upstream-inventory.md).
Reuso SDC deve ser confirmado por consumer real e story; esta matriz não trata
classes Bootstrap, markup isolado ou providers como evidência de consumer SDC.
Organização é um conceito separado de Conta e Perfil; não foi confirmado um
consumer organizacional operacional no registro atual do Studio. Não exibir
área de organização, recomendações ou ações ausentes como funcionalidades.

### Matriz inicial histórica — anterior ao ADR-038 (2026-10-29)

Os caminhos desta tabela preservam o baseline da época; não são rotas atuais.
`Pede-A` indica dependência de código/rota/serviço fora do ownership visual.

| Tela/estado | URL/rota ACULTA | Evidência upstream concreta | Referência/viewport | SDC atual | Alteração candidata | Owner | Fidelidade inicial | Risco/consumer | Teste | Status comprovado |
|---|---|---|---|---|---|---|---|---|---|---|
| Fundamento F1 — tipografia, superfícies e geometria | `.studio` nas rotas `/studio/*`; editor privado `.aculta-profile-editor` | `sass/abstracts/_colors.scss`, `_typography.scss`, `_layout.scss`, `_effects.scss`, `templates/navigation/tabs.scss` do pacote 3.1.5 verificado | Capturas equivalentes 320/390/768/1440; requer baseline autenticado pareado | SDCs existentes herdam valores do wrapper; sem mudança de contrato | Tokens escopados, Montserrat OFL local e escala tipográfica do Next | Tema/Frente B | Parcial; folha candidata aplicada em Marley pelo Firefox ESR visível | Não tocar Institution Bar/Domain Header nem outros purposes; fonte só baixa quando usada | lint CSS/JS, schema SDC, Design Foundations/fixtures, Runtime Foundation/shell, Firefox claro/escuro, 320–1440, foco, zoom e comparação visual | 63/63 checks executáveis; Montserrat carregada, contraste 6,49:1/11,51:1 e zero overflow na grade com 767 extra; uma edição SKIP sem fixture; PHPCS dos scripts do Design Foundations PASS; sem comparação pareada upstream; F1/F7 não aprovados integralmente |
| Entrada/Hub Studio | `account:/` e `/conta` · `AccountExperienceProvider` | `templates/views/user-dashboard-views.scss` (listas de perfis/salvos, não Hub autenticado); `templates/layout/page.html.twig` | Capturas Runtime ACULTA anteriores 390/1440; fixture CSS candidata Firefox 45/45 em 320–1440; referência upstream autenticada indisponível | `patterns/card` com variante `studio_fidelity` via `PortalUiHooks` | Grade e cartões dos links reais registrados | B tema/SDC; A para disponibilidade/provider | Geometria de teaser aplicada; paridade do conteúdo do Hub upstream não mensurável | Só experiências autorizadas/registradas; sem cards fictícios | Unit do adaptador, A/B, módulos opcionais, teclado, viewport | Integração PHP estática testada; a fixture não comprova HTML/CSS do Runtime neste HEAD |
| Meus Salvos / Seguindo / Recomendações | `/studio/salvos` · `aculta_studio.salvos` | `templates/views/user-dashboard-views.scss`; padrões de lista e paginação | Captura autenticada equivalente não disponível; requer 320/390/1440 | `studio-*` via `PortalUiHooks`; SDCs `card`, `context-tabs` e `empty-state` são candidatos, ainda sem prova de consumer SDC nesta tela | Harmonizar lista real e estados vazios sem anunciar operação não disponível | Tema B; Portal (consulta de salvos) e Studio (apresentação) | Código de lista/paginação/recommendation existente; paridade visual não medida | Conteúdo privado, cache por UID, ACL de cada entity e módulo `aculta_index` opcional | Kernel de isolamento A/B, cache quente, teclado/no-JS, light/dark | Provider e consumer no registry; testes ARQ-07B documentados; Runtime A/B e capturas pendentes |
| Dashboard Marley | `/studio/marley` · `aculta_studio.home` | `templates/views/user-dashboard-views.scss`; `templates/layout/page.html.twig` | Home pública upstream + rota ACULTA não equivalente; 1440/390 | `context-header`, `card`, `content-section`, `content-grid`, `empty-state` | tokens e composição interna após fechar escopo mobile-first | B tema/SDC; A se exigir provider | Baseline ACULTA capturado; upstream dashboard autenticado não disponível | Não inventar salvos/recomendações/organizações; cache UID | Firefox visível, anônimo/A/B, cache, zoom, teclado | PNGs inspecionados; sem overflow horizontal nas larguras 390/1440 desta rota; gate de paridade pendente |
| GanjaLab editorial | `/studio/ganjalab` e abas reais | Dashboard/listagem OpenCulturas; `node--compact.html.twig` | Captura ACULTA 1440/390; requer 320 | `context-header`, `card`, `content-grid`, `empty-state` | Reuso do layout de experiência e estados reais | B visual; A para providers/ACL | Estrutural | Não confundir com experiência LMS GanjaLab420; plugin opcional | Autoria A/B, módulos disponíveis/ausentes, no-JS | Captura renderiza Minha Conta; rota esperada não foi identificada, Pede-A |
| GanjaLab — painel LMS do estudante | `/studio/ganjalab` · `aculta_lms.student_dashboard` | Dashboard/listagens e cards upstream | Captura ACULTA 1440/390, conteúdo inesperado; requer 320 | `card`, `content-grid`, `context-header`, `empty-state` | Responsividade de curso/progresso/certificado | B visual; LMS/Studio A para dados/acesso | Estrutural | Estado acadêmico, matrícula e ACL permanecem no LMS; rota em `aculta_lms.routing.yml` | Matrícula, turma, certificado, A/B, cache | Conteúdo capturado é Minha Conta; rota LMS não homologada, Pede-A |
| WE Sativa / Wiki | `/studio/we-sativa` e subrotas reais | Views/listagens, `node--compact.html.twig`, templates de revisão | Capturas autorizadas equivalentes; 1440/768/390/320 | `wiki-entry`, `card`, `content-grid`, estados existentes | Autoria, revisão e moderação visualmente distintas | B visual; Wiki A para filas/ACL | Estrutural | Nunca exibir revisão/proposta sem grants; provider pode faltar | dois autores, grants, módulo ausente, foco/no-JS | Consumer descrito no roadmap; captura pendente |
| ACULTA STUDIO GREEN STASH — entrada integrada | `/studio/green-stash` (rota de Commerce) | Dashboard OpenCulturas como inspiração de hierarquia | Capturas Studio/Commerce pareadas; 1440/768/390/320 | SDCs compartilhados de `card`, `content-section`, `context-tabs` | Integrar navegação comercial ao contexto Studio | Tema; Commerce para rotas/recursos | Não há tela autenticada upstream equivalente confirmada | Provider é integração técnica; a área continua dentro do Studio | ACL loja, purpose account, navegação Studio, sem admin Drupal | Path canônico atualizado; fixture de operador autorizada e visual Runtime pendentes |
| ACULTA STUDIO GREEN STASH — produtos/variações | `/studio/green-stash/produtos`, `/studio/green-stash/produtos/novo`, `/studio/green-stash/produtos/{produto}` | `templates/node/node--compact.html.twig`, cartões de listagem | Catálogo real; 1440/390/320 | `card`, `content-grid`, componentes product atuais | Apresentar catálogo, detalhe e rascunho atualmente implementados | Tema; Commerce para operação/dados | Inspiracional apenas | Rotas existem; contrato de variação e status do rascunho precisam validação do owner | loja autorizada, outra loja negada, submit/no-JS | Código presente; runtime e fluxo completo pendentes |
| ACULTA STUDIO GREEN STASH — pedidos/estoque/envios | `/studio/green-stash/{secao}` e rotas de remessa | Padrões de lista/filtros/formulários | Somente telas/ações existentes; 1440/390/320 | `card`, `content-grid`, tabela responsiva existente | Envios apresenta remessas e transições; pedidos/estoque aparecem na navegação preparatória | Tema; Commerce/Shipping para operações | Sem equivalência direta confirmada | Não encaminhar a admin nativo Drupal; ACL e transições CSRF permanecem no Commerce | loja A/B, transições, Commerce Stock/Shipping, cache | Código confirma leitura/transições de envios; listas de pedidos/estoque não encontradas nesta varredura |
| ACULTA STUDIO GREEN STASH — promoções/clientes/relatórios/configurações | `/studio/green-stash/{secao}` | Não há tela upstream de Studio autenticado equivalente confirmada | Sem captura | SDC compartilhado apenas se existir consumer real | Manter estado preparatório atual; projetar UI operacional só após recurso/consumer real | Commerce; tema após contrato/consumer | Não mapeado | Path genérico não torna cada seção operacional; não anunciar funções preparatórias | Registro da operação real, permissão, consumer e teste do owner | Capacidade operacional não verificada/não deve ser presumida |
| Planos Mercado Pago | `/studio/conta/mercado-pago/planos` · `aculta_pagamentos_mercadopago.studio_plans` | Views/filtros/forms upstream como inspiração, sem equivalente de domínio confirmado | Captura autenticada real; 1440/390/320 | SDCs existentes a identificar no consumer | Apenas acabamento da superfície de planos presente | B visual; integração/pagamentos A | Não comparável por domínio | Provider condicional pelo módulo; acesso exige permissão `administrar doacoes` | módulo ausente/presente, permissão, anônimo, foco, cache | Provider e routeName no registry; rota real no owner, visual pendente |
| Navegação principal do Studio | `/studio/marley`, `/studio/marley/{aba}` | `templates/layout/header.scss`, `templates/navigation/menu.scss`, `templates/layout/page.html.twig` | Capturas 1440/390 | `context-tabs`; markup `data-aculta-studio` | menu e abas no conteúdo do Studio | B visual; A para URLs/ações | Sem medida | Institution Bar e Domain Header intactos; só URLs verdadeiras | teclado, URL/back-forward, no-JS/AJAX | Mapeado; gate browser pendente |
| Navegação de seção/subseção | `/studio/marley/{aba}/{secao}[/{sub}]` | `templates/navigation/tabs.scss` | 1440/768/390 | `context-tabs` | variantes/slots ou CSS Studio escopado | B | Sem medida | Não duplicar tabs do shell | teclado, `aria-current`, sem JS | Mapeado; gate browser pendente |
| Lista Meu Trabalho | `/studio/marley/{aba}/{secao}/{sub}` | `templates/views/user-dashboard-views.scss`, `templates/node/node--compact.html.twig` | 1440/390 | `card`, `content-grid`, `empty-state` | variantes para item real/estado vazio | B; provider/cache A | Estrutural | Apenas conteúdo autorizado; revisão e cache por usuário | 2 autores, status, cache quente, pager | Mapeado; provider não reavaliado nesta fase |
| Criar/editar conteúdo (Marley) | rotas existentes sob `/studio/marley/.../criar` e edição por consumer | `sass/base/_forms.scss`, `sass/base/_buttons.scss`, `templates/views/views-exposed-forms.scss` | Captura real do formulário; 1440/390 | `content-section`, `context-header`, SDC de feedback existente | acabamento de controles sem alterar Form API | B visual; A para form/rotas | Sem captura ainda | AJAX, CSRF, estados de erro; Pede-A se comportamento mudar | submit positivo/negativo, AJAX/no-JS, foco | Inventário de arquivos; formulário não homologado |
| Filtros/listas editoriais | views de Studio existentes; URL fornecida pelo consumer | `templates/views/views-exposed-forms.scss`, `templates/views/user-dashboard-views.scss` | Desktop/mobile | `content-grid`, `card`, `empty-state` | filtros responsivos e estado vazio | B; A se provider | Sem medida | Paginação e ACL reais | teclado, filtros, vazio/erro | Mapeado; execução pendente |
| Mídia/galeria | rota real do consumer Studio | `templates/media/media.scss`, `templates/paragraph/paragraph--gallery.scss` | 1440/768/390 | SDCs de mídia existentes; verificar consumer | apenas apresentação das entidades existentes | B; A para upload/storage | Sem medida | Não alterar media storage/upload | preview, modal/AJAX, limites e erro | Consumer/rota precisa de validação autenticada |
| Estados vazio/erro/sucesso | rotas de listas/formulários acima | `templates/components/status-messages.html.twig`, `sass/base/_messages.scss`, `user-dashboard-views.scss` | Stories + capturas de fluxo | `feedback/empty-state` e feedback existentes | variantes e stories reais | B; A para estado funcional | Conceitual | Mensagem não substitui confirmação do owner | contraste, foco, leitores de tela | SDC disponível; consumer a conferir |
| Diálogos e ações | onde os owners já expõem a ação | `sass/base/_modal-dialog.scss`, `templates/block/block--search-toggle.html.twig`, `js/opcult.js` | viewport 390/1440 | componentes de diálogo existentes; inventariar consumer | estilos em escopo Studio | B; A para ações/JS | Sem medida | Não adicionar função falsa; garantir fechamento/teclado | teclado, Escape, foco, sem JS/AJAX | Mapeamento upstream feito; consumer pendente |
| Conta — hub | `/studio/conta` · `aculta_studio.account` | `templates/layout/layout.scss`, `templates/navigation/tabs.scss` | Captura autenticada; 1440/390 | `context-header`, `context-tabs`, `content-grid` | composição fiel com links já autorizados | B; A para provider/route | Sem medida; captura autenticada pendente | Conta privada ≠ Perfil; `/conta-legada` permanente | S-R5, duas contas, anônimo, no-JS | URLs no inventário; S-R5 não homologada |
| Conta — formulários e pedidos | `/studio/conta/dados`, `/endereco`, `/documentos`, `/seguranca`, `/senha`, `/conexoes`, `/pedidos`, `/apoio`, `/preferencias` | `sass/base/_forms.scss`, `sass/base/_buttons.scss`, `templates/contrib/password.scss` | Capturas por formulário, viewport 390/1440 | `content-section`, formulários Drupal, `context-tabs` | estilos base escopados | B; owners Portal/User/Commerce A | Sem medida | Form API, CSRF, dono único; pedidos usam owner Commerce | matriz S-R5 e estados negativos/positivos | URLs mapeadas; forms não homologados |
| Perfil — edição privada | `/studio/perfil`, `/apelido`, `/privacidade`, `/bio`, `/redes/{rede}` | `templates/node/node--profile/*`, `templates/field/field--extra-field-social-media-buttons.html.twig`, `sass/base/_forms.scss` | Capturas do titular, viewport 390/1440 | `profile-editor`, `profile-identity`, `profile-bio`, `profile-social` | acabamento das composições existentes | B; política/Form API A | Sem medida | Não vazar dados de conta nem tornar perfil público | S-R4/S-R5, A/B, grants/cache | SDCs e URLs mapeados; gates pendentes |
| Perfil — público | `/u/{apelido}` (MAIN) | `templates/node/node--profile/node--profile--full.html.twig` | Página pública real, viewport 390/1440 | `profile-identity`, `profile-bio`, `profile-social` quando autorizado | identidade visual só se consumer público já usa SDC | B; policy A | Sem medida | Exibir só campos opt-in; perfil privado não vira Conta | anônimo, visibilidade, e-mail opt-in, cache | Matriz S-R5 define contrato; execução pendente |
| Foto/avatar | `/studio/perfil` e URL pública autorizada | `templates/media/media--image.html.twig`, `templates/field/field--node--field-portrait--profile.html.twig` | Modal/estado sem imagem | `profile-identity` slot `avatar` | estilo de slot/estado sem foto | B; A para upload/crop | Sem medida | Apenas `aculta_image` persiste/processa | upload/crop, visibilidade, fallback, cache | Consumer e estado real pendentes |
| Preferências claro/escuro | `/studio/conta/preferencias` | `sass/dark-mode/_index.scss`, `sass/dark-mode/_misc.scss` | 1440/390 em ambos os modos | SDCs reutilizados sem novo componente | tokens escopados por modo | B; Portal A | Sem medida | Não salvar preferência para fazer captura sem restore | contraste AA, preferência, screenshot lado a lado | Upstream mapeado; runtime gate pendente |
| Shell multidomínio no purpose ACCOUNT | `/studio/*`, incluindo `/studio/green-stash` | A barra institucional é ACULTA, distinta do shell OpenCulturas | Captura autenticada 1440/390 | `institution-bar`, header de domínio do tema | Preservar ambos no shell compartilhado | Tema; Domain/Portal para composição/availability | Parcial: Institution Bar presente, `.aculta-header` ausente nas oito rotas capturadas | Não duplicar shell, não retirar institution bar; ausência do Domain Header requer decisão do owner | Firefox DOM + screenshot, keyboard, purpose matrix | Marcadores DOM confirmam Institution Bar em todas as rotas válidas; Domain Header ausente, Pede-A |
| Shells de outros purposes | Fórum, Wiki, Revista, Loja, Eventos, GanjaLab | CSS upstream não se aplica fora do Studio | Raízes reais, 320–1440px, 2026-10-30 | SDCs existentes | testar vazamento dos seletores/tokens | B; Portal para composição do shell | Baseline remoto: 90/90 sem overflow horizontal medido; Institution Bar 90/90; Domain Header 18/90 (somente MAIN/Wiki) | Revista: Firefox ESR Runtime 10/10 checks; 4 teasers/imagens reais, hero e shell único em nove viewports, sem overflow; capturas 320/1440 inspecionadas | Ampliar zoom, teclado, modos e acessibilidade; comparar tela editorial equivalente com upstream quando disponível | Evidência focal; não aprova MF3/F11 |

## Escalas de aceite

- **Estrutural:** presença e ordem dos blocos conferem com a fonte de código; não é paridade visual.
- **Parcial:** contraste, tipografia, geometria e estados medidos em capturas equivalentes; limitações registradas.
- **Fiel:** comparação de tela/estado/viewport equivalente revisada, sem regressões de acessibilidade ou shell.
- **Aprovado:** gate F7 completo, evidências visíveis e validação do responsável. Nenhum status inicial desta matriz significa aprovado.

## Atualização de captura Firefox — nove larguras (2026-10-29)

A captura expandida `$SUITE_OUT/aculta-ocs-f0-responsive-20261029/baseline.json`
contempla 320/360/390/599/600/768/1024/1280/1440 nas oito URLs Studio e na
referência pública OpenCulturas. No conjunto Studio: zero overflow medido nas
sete páginas que renderizaram; o hub está vazio nas nove larguras; Green Stash
retorna acesso negado nas nove para a conta sintética; GanjaLab ainda apresenta
Minha Conta, sem o painel LMS. Institution Bar presente nas sete rotas
renderizadas, Domain Header ausente nas oito URLs, marcadores `[data-aculta-studio]`
somente em Marley. A execução foi contra o checkout servidor `f2708e60f`, cuja
árvore versionada de tema/Studio/Commerce coincidia com a PR `5734bed87`; havia
alterações locais de Eventos, então esta evidência não aprova o head completo
nem a regressão global. Os screenshots Marley 320/1440 foram inspecionados; as
capturas não equivalem a referência autenticada upstream nem aprovam a
fidelidade ou os gates de acessibilidade. Status geral continua **PARCIAL**.

## Foco por teclado — evidência parcial (2026-10-29)

O probe em Marley, Conta e Perfil a 320/1440 px registrou 12 Tabs por tela,
com 72 de 72 alvos correspondendo a `:focus-visible` e outline calculado.
Marley/320 foi inspecionada e exibe o foco do link de skip. Cobertura não inclui
Shift+Tab, percurso inteiro, widgets/modais, fluxos de formulário ou os demais
purposes. Não é aceite WCAG 2.2 AA.

### Repetição ampliada — teclado na grade completa

A repetição em nove larguras e duas direções produziu 648 observações nas três
rotas: 621 elementos focáveis com `:focus-visible` e outline calculado, mais
27 retornos ao `body` no final do Shift+Tab. As capturas estão fora do Git em
`$SUITE_OUT/aculta-ocs-f0-keyboard-full-20261029/`; Marley/320 foi inspecionada.
Amostra de 12 passos não equivale à análise de todos os focáveis nem ao aceite
WCAG 2.2 AA.

## Modos claro/escuro e reduced motion — evidência parcial

O probe `theme-modes.py` capturou Marley, Conta e Perfil em 320/1440 px sob
`data-bs-theme=light`, `data-bs-theme=dark` com `prefers-reduced-motion: reduce`
e modo auto sem atributo (Firefox padrão claro). O browser confirmou dark 6/6
e reduced motion 6/6 na combinação escuro/reduzido; o modo claro/auto confirmou
ambos falsos. Tokens `--aculta-surface-page`/`--aculta-text-primary` foram
`#f2f7f0`/`#0c3c29` no claro e `#171513`/`#f4efe8` no escuro. Scroll width não
excedeu o viewport nas 18 capturas; Marley/320 escuro foi inspecionada. Os
atributos foram aplicados somente no DOM temporário; nenhuma preferência
Drupal foi salva. Isso não aprova contraste WCAG nem os demais componentes/
purposes.

### Atualização: modes na grade completa do viewport

A execução expandida cobriu Marley, Conta e Perfil a 320/360/390/599/600/768/
1024/1280/1440 em modo claro, escuro + reduced motion e automático (81
capturas). Light e auto/default Firefox: `prefers-color-scheme: dark` e
`prefers-reduced-motion: reduce` falsos em 27/27; modo dark/reduced: ambos
verdadeiros em 27/27. Tokens claro/escuro permaneceram `#f2f7f0`/`#0c3c29` e
`#171513`/`#f4efe8`, respectivamente. Nenhum overflow foi medido. Isto não
substitui contraste AA, nem cobre outras rotas/purposes.


### OCS-F3/F4 — reteste local dos cartões Fidelity (2026-10-30)

`studio-card-fidelity.py` foi reexecutado em Firefox ESR visível no HEAD local
`c00666e87`. Passaram 45/45 verificações nas larguras 320/360/390/599/600/768/
1024/1280/1440px: uma coluna até 600px, duas a partir de 768px, dois cartões,
geometria Fidelity e zero overflow. A captura de 320px foi inspecionada.
Evidência: `/home/piradopirata/aculta-suite-out/studio-card-fidelity-current-20261030/`.
A fixture incorpora a CSS local e markup equivalente; não comprova render Drupal,
ACL, cache, estados de módulo opcional ou comparação pareada com Hub OpenCulturas
autenticado. OCS-F3/F4 seguem parciais e OCS-F11 não está aprovado.


### OCS-F1/F3 — breakpoints do container Fidelity (2026-10-30)

A fixture `studio-card-fidelity.py` agora cobre os limites do layout no pacote
OpenCulturas 3.1.5: 48em/768px, 70em/1120px, 85em/1360px e 120em/1920px,
incluindo um viewport de cada lado das mudanças. Firefox ESR visível passou
216/216 verificações em 27 larguras; mediu sem overflow, grade, geometria do
SDC, largura/padding do container e tipografia. Montserrat carregou nos pesos
400/700; body ficou 16/24px e H3 em 24px/700. O H1 preserva o clamp mobile de
32–48px, com 48px no desktop. Capturas 320/1440 foram inspecionadas. Evidência
em `/home/piradopirata/aculta-suite-out/studio-card-typography-rerun-20261030/`.
A fonte é `openculturas/profile/themes/opcult/sass/abstracts/_mixins.scss`,
`_layout.scss` e `_typography.scss` no tarball verificado. Esta fixture não
valida o consumer Drupal, modo escuro nem comparação pareada com dashboard
autenticado upstream.
OCS-F1/F3 seguem parciais.


### OCS-F1/F5–F7/F9 — campos e botões de formulário

Os estilos de formulário do pacote 3.1.5 foram aplicados aos formulários dentro
de `.studio__main` e `.aculta-profile-editor`, sem alterar markup, Form API ou
regras dos owners. A fixture local passou 42/42 verificações em quatro larguras
e dois modos de cor, com foco visível e ausência de vazamento para formulário
externo. Evidência e capturas em
`/home/piradopirata/aculta-suite-out/studio-form-fidelity-final2-20261030/`.
Ainda não houve comparação pareada com os formulários reais de Marley, LMS, Wiki
ou Conta no Runtime; essas fases seguem parciais.

### OCS-F7 — consumer Wiki WE SATIVA (2026-10-30)

A folha Fidelity candidata foi aplicada ao wrapper real `.we-sativa__workspace`
sem mudar domínio, rotas, presenters, ACL ou markup. Firefox ESR visível sobre o
Runtime em `/wesativa` passou 14/14 invariantes em 320 e 1440px, incluindo
Montserrat carregada, 2 seções, 2 empty states, 4 filtros com um `aria-current`,
links com 46px de altura, wrapping e shell único sem tokens Fidelity. Capturas
revisadas em `/home/piradopirata/aculta-suite-out/wesativa-legacy-route-final-20261030/`.
Isso testa o consumer servido pelo caminho antigo; o caminho oficial
`/studio/we-sativa` permanece vazio no Runtime `2493d7535` e não foi homologado.
Amostra não cobre dados Wiki preenchidos, revisão/moderação, grants A/B,
no-JS/AJAX, zoom ou WCAG; F7 parcial, OCS-F11 não aprovado.

#### OCS-F7 — modo escuro do consumer legado (2026-10-30)

No documento de teste com `data-bs-theme=dark`, Firefox confirmou texto Fidelity
`rgb(239, 239, 239)`, Montserrat, superfícies escuras, quatro filtros com link
mínimo de 44px, shell isolado e zero overflow; 15/15 invariantes passaram em
320/1440px. Capturas revisadas em
`/home/piradopirata/aculta-suite-out/wesativa-dark-legacy-20261030/`. O snapshot
pré-ULI foi `/home/piradopirata/aculta-suite-out/runtime-before-wesativa-dark-20261030.sql`,
SHA-256 `635b80adb3aaff58be98a539c52aeddaf9f133a14bf3336f4a7bd6e5b707ca33`.
A preferência da conta não foi salva; a captura continua no path legado, sem
validar a rota canônica ou contraste WCAG integral.

### OCS-F3 — revalidação atual da fixture de card e navegação Fidelity (2026-10-31)

No HEAD local, `studio-card-fidelity.py` passou 243/243 em 27 larguras, cobrindo
reflow do Hub, SDC card, tipografia Montserrat, container e alvos das abas. As
capturas 320/1440 foram inspecionadas. As evidências estão em
`/home/piradopirata/aculta-suite-out/ocs-studio-card-current-20261031/`.
Resultado é fixture local; Hub Drupal autenticado, autorização, cache e
comparação pareada com OpenCulturas autenticado seguem sem prova. OCS-F3/F4
parciais; F11 pendente.

### OCS-F8 / THEME-MF2 — detalhe e novo rascunho Green Stash (2026-10-31)

A fixture ampliada de `green-stash-layout.py` passou **186/186** checks em
Firefox ESR visível, com detalhe de produto, formulário de criação, menu
contextual, nove larguras, claro/escuro, foco e zoom real 200/400%. Capturas em
`/home/piradopirata/aculta-suite-out/green-stash-detail-create-homologation-20261031/`.
O formulário de criação não foi submetido e a fixture não cobre grants de lojas
ou a rota servida; OCS-F8 é parcial e OCS-F11/THEME-MF3 seguem pendentes.

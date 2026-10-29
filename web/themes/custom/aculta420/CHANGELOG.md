# 0.7.0-dev.17 — migrar carrossel editorial para mobile-first (2026-10-29)

- O carrossel da View editorial na home MAIN usa limite compacto (390px) e item fluido como base; `min-width: 48.0625rem` restaura os limites amplos anteriores, mantendo a transição original após 768 CSS px.
- Harness Firefox candidato/baseline em 12 viewports criado, execução DEFERRED por sessões concorrentes visíveis. Sem homologação Runtime ou THEME-MF3.

# 0.7.0-dev.16 — regenerar inventário responsivo mobile-first (2026-10-29)

- O TSV de MF0 é gerado por `scripts/audit-aculta420-responsive-inventory.py` e agora inclui o script `navigation-top-bar-displace.js` e as media queries mobile-first recentes.
- Recontagem: 541 arquivos no tema; 92 estilos, 12 JS e 98 Twig/HTML; 77 blocos `@media`, zero `@container`, 599 expressões dimensionais e 54 declarações de overflow. É inventário lexical, não validação visual.

# 0.7.0-dev.15 — migrar formulários públicos de autenticação para mobile-first (2026-10-29)

- `/entrar`, registro e recuperação passam a usar espaçamento compacto como base; `min-width: 36rem` restaura o painel amplo anterior.
- Firefox ESR candidato/baseline está DEFERRED enquanto sessões visíveis da Revista consomem os recursos locais. Lint CSS e validações estáticas são executados; sem homologação Runtime ou THEME-MF3.

# 0.7.0-dev.14 — migrar o Perfil público para mobile-first (2026-10-29)

- Perfil público usa composição empilhada e espaçamento móvel como base; `min-width: 36rem` restaura a apresentação ampla anterior.
- O harness Firefox ESR usa fixture sintética em 11 larguras. A primeira execução fez 21/22 checks: uma diferença do fixture em 320 px foi corrigida; a repetição ficou DEFERRED porque as sessões visíveis da Revista saturaram memória e geckodriver. Não representa render do Runtime nem aprova THEME-MF3.

# 0.7.0-dev.13 — reflow mobile-first da composição institucional (2026-10-29)

- CTAs de hero, grade de áreas institucionais e pares de detalhes da instituição passam a usar layout móvel como base; as colunas largas são aprimoradas em `min-width`.
- Firefox ESR visível comparou baseline e candidato em 12 larguras: 24/24 verificações passaram, capturas idênticas e sem overflow no fixture; ver `docs/mobile-first-audit.md`.
- `npm run lint:css`, SDC schema (734), Design Foundations (2434), browser validators (21) e Foundation via Drush/Runtime (348) PASS. A validação visual das páginas servidas após sincronizar o HEAD continua pendente.

# 0.7.0-dev.12 — corrigir deslocamento da barra multidomínio no mobile (2026-10-29)

- A barra top-bar do Drupal Navigation em fluxo normal já não desloca a Institution Bar sticky no mobile; em desktop fixo, mantém o deslocamento real.
- Firefox ESR visível com candidato CSS/JS: admin 38/38, fallback sem JavaScript 38/38; homes anônimas dos dez purposes 99/99. Evidências e limites em `docs/mobile-first-audit.md`.
- Lint CSS/JS e validadores de design foundations, SDC e fronteira módulo-tema executados; gate transversal THEME-MF3 continua pendente.

# 0.7.0-dev.11 — tokens de Eventos, Calendar e Maps (2026-10-29)

- PortalUiHooks mapeia cartões de evento, calendário interativo e mapa. O
  módulo entrega apenas estrutura Drupal e tokens semânticos.
- Gate módulo–tema: 3397 verificações PASS, zero classes de design no código de
  módulos. Revisão visual dos três consumidores fica pendente após servir o
  candidato.

## 0.7.0-dev.10 — empilhar a top-bar Navigation no mobile

- Refluxo mobile da integração Drupal/Bootstrap agora empilha contexto e ações em uma coluna e alinha as ações ao início; Editar e Mais ações permanecem disponíveis.
- Firefox ESR visível com CSS local injetado: álbuns autenticados do host principal e do Fórum passaram 8/8 verificações em 1280/390/320 px, sem overflow e com ações dentro da viewport. O CSS ainda não foi servido pelo Runtime; repetir após cache rebuild.
- Design Foundations, SDC schemas, gate global e diff check ainda pendentes nesta entrega.

## 0.7.0-dev.9 — reflow mobile da barra administrativa Drupal

- A barra top-bar do módulo Navigation reordena contexto e ações em duas
  colunas a 767.98px; títulos podem quebrar linha e as ações Editar/Mais ações
  permanecem visíveis. Override no arquivo de integração Drupal/Bootstrap.
- Design Foundations 2379 checks e SDC schemas 54 componentes/734 checks PASS.
  Firefox ESR com SUITE_THEME_CSS injetado no documento atual passou 15/15
  verificações em álbuns/diálogo, 1280/390/320px; o CSS ainda não está servido
  pelo Runtime. Repetir após cache rebuild. Gate módulo-tema continua FAIL por
  aculta_events e aculta_calendar em arquivos de outra frente.

## 0.7.0-dev.8 — mapear tokens semânticos do checkout

- O tema traduz `checkout-guest-registration` e `checkout-google` em classes Bootstrap para a identificação e o cadastro durante o checkout.
- `PortalUiHooksTest`: 9 testes / 52 assertions PASS; validador de fronteira: 3100 verificações PASS. PHPCS Drupal/DrupalPractice: zero erros; seis avisos preexistentes de linhas longas neste arquivo de teste.

## Não validado — calculadora de frete (ME-0.1-C) — 2026-10-27

- Novo componente `shipping-calculator` (campo de CEP, botão "Calcular", "Não sei meu CEP" e resultado com aria-live). Estados idle, available, unavailable e empty; o tema só apresenta o estado devolvido pelo Commerce.
- Novo componente `shipping-summary`: resumo do último cálculo para o carrinho lateral (sem campo de CEP; a gaveta não calcula). `cart-offcanvas` recebe o resumo na prop `shipping`.
- Encaixes: página do produto (abaixo da compra), carrinho lateral (gaveta e painel AJAX) e carrinho completo.
- Stories: aguardando CEP, disponível, indisponível e sem opções (shipping-calculator); resumo com e sem cálculo (shipping-summary).
- Validação: `validate-aculta420-sdc-schemas.php` PASS (661 checagens); `validate-aculta420-design-foundations.php` PASS; `validate-browser-validators.php` PASS (21 checks); `validate-aculta420-foundation.php` PASS (345 checks, versão asserida 0.7.0-dev.8).
- Não validado no navegador (Firefox ESR, ACULTA420 e Olivero) e no servidor de testes com origem, credencial e pacote reais. RUNTIME STATUS: DEFERRED.

## 0.7.0-dev.6 — author-card com tamanhos e repasse ao SDC (Fases 2 e 3 do avatar padrão)

- `author-card` ganha a prop `size` (`sm`, `md`, `lg`; padrão `md`) para listas densas e destaques. O tamanho é contrato do SDC, não estilo de display: UI Styles continua restrito às famílias semânticas (superfície, texto, borda, elevação).
- Stories novas: tamanho `sm` e tamanho `lg`.
- O cartão sem imagem deixou de existir: a inicial do nome não é usada em nenhum momento e o acervo é o fallback obrigatório. A story `author-card.initial` foi removida.
- `aculta-user-author-card` repassa `size` ao SDC `author-card`; o padrão continua `md`.
- Marcação visual dos cartões existentes não muda: `md` mantém as dimensões atuais.
- Validação: `validate-aculta420-design-foundations.php` PASS (2233 checks); `validate-browser-validators.php` PASS (21 checks). `validate-aculta420-sdc-schemas.php` não executado nesta sessão (sem `vendor/`). RUNTIME STATUS: DEFERRED.
## Não validado — alinhamento da barra multidomínio — 2026-10-27

- Unifica a área de toque, o ícone e o posicionamento do contador de busca, carrinho, mensagens e avisos; os contadores usam tokens semânticos existentes.
- Mantém as gavetas e os atributos AJAX existentes e alinha os rótulos de mensagens e avisos, inclusive depois da atualização da contagem.
- Unit do tema: 16 testes / 213 assertions; seis cenários de atualização JavaScript; fronteira, schemas SDC e Design Foundations: PASS.
- Validação visual em Firefox ESR e marcação de versão pendentes; versão pretendida para a correção: `0.7.1-dev.1`.

## 0.7.0-dev.5 — CSS administrativo de segredos movido ao submódulo — 2026-10-26

- Remove de `portal-requirements-report.css` apenas as regras `.aculta-secret-value*`; o submódulo as fornece para Claro e ACULTA420.
- A exceção para UI administrativa no Claro está registrada na fronteira módulo–tema. Nenhum outro estilo desta folha foi alterado.
- Fronteira (3034), SDC (628) e Design Foundations (2227): PASS; PHP lint e `git diff --check`: PASS. PHPCS do script Foundation mantém violações preexistentes (3 erros / 16 avisos), fora da linha alterada.
- Firefox visual Claro: DEFERRED ao Local 1 (1280/390/320 px; foco por Tab).

## 0.7.0-dev.4 — tokens de Links e Secrets Manager — 2026-10-26

- Mapeia tokens semânticos de links públicos e abas de credenciais às mesmas classes visuais atuais.
- Validação: fronteira 3034, SDC 628 e Foundations 2227 checks PASS; lint PASS; PHPCS sem erros (29 avisos preexistentes de linhas longas); Unit 20/106 e Kernel 8/231 PASS, com 64 depreciações upstream. `git diff --check` PASS.
- Foundation via Drush: DEFERRED (docroot isolado sem `settings.php`; Runtime fora do escopo). Firefox ESR: DEFERRED (`DISPLAY` indisponível; worktree não servido).

## 0.7.0-dev.3 — PHPCS do tema e tokens de Portal (2026-10-25)

- PHPCS Drupal e DrupalPractice limpo em `src` e `tests` do tema. Blocos de documentação malformados foram corrigidos e os métodos sem descrição receberam texto real.
- Novos tokens `fiscal-identity-other-data` (checkout Commerce) e `wiki-proposal-contribute` (botão da Wiki) mapeiam para as mesmas classes que já eram emitidas. A marcação visual não muda.
- Avisos do Messenger passam a ser estilizados por `data-portal-ui` (`notice-origin-*`, `notice-related-group*`), e não mais por classes de design no módulo.
- Gates executados: PHPUnit Unit do tema 12/12 OK; Kernel do tema 10 testes OK, incluindo `ProfileSdcRenderKernelTest` no banco de teste dedicado; PHPCS sem erros.
- **Pendente:** o validador de fronteira aponta `Hook/ThemeHooks.php` registrando `page_attachments` para anexar a biblioteca de avisos. A regra permite apenas `preprocess_*`. A correção depende de decisão sobre onde a biblioteca deve ser carregada.

## 0.7.0-dev.2 — teste Kernel dos quatro SDCs de Meu Perfil (2026-10-25)

- Criado `ProfileSdcRenderKernelTest`, com descoberta de `profile-editor`, `profile-identity`, `profile-bio`, `profile-social` via Core SDC. Renderiza componentes aninhados com props/slots e entradas demonstrativas, verifica escape, ausência de links públicos inferidos, estados vazios e metadata de cache.
- A fixture de link foi mantida independente do roteamento para isolar o contrato de apresentação. Contratos SDC (schemas, Twig, CSS) **não foram alterados**; continuam experimentais.
- **O teste foi escrito, mas não executado**, pois não há ambiente Drupal Kernel instalado/Runtime disponível nesta sessão; não afirmar sucesso em Core/UI Patterns ou Firefox.

## Nota documental — S-R4: consumo dos SDCs (2026-10-25)

O Studio dev.21 passou a consumir os quatro SDCs experimentais
`profile-*` por `#type: component`, conservando props, slots, CSS,
Twig e regras de origem inalterados. Sem nova versão do tema
(persiste `0.7.0-dev.1`). Render real em UI Patterns, Kernel,
Firefox ESR/X11 e Runtime ainda não homologados.

## 0.7.0-dev.1 — SDCs preparatórios do Meu Perfil (2026-10-25)

Classificação: MINOR pré-1.0; novos componentes experimentais, sem quebra dos componentes estáveis.

- `aculta420:profile-editor`: composição visual com slots para introdução, identidade, biografia, redes e ações.
- `aculta420:profile-identity`: identidade privada do titular, distinta do `author-card` público.
- `aculta420:profile-bio` e `aculta420:profile-social`: painéis visuais com conteúdo e ações enviados por render arrays dos módulos proprietários.
- Quatro contratos SDC com Twig, CSS mobile-first/tokens semânticos, duas stories estáticas por SDC e um teste Unit de metadados (escrito).
- Não integrados ao StudioProfileController nesta etapa; validação pendente.

## 0.6.1-dev.13 — separar visualmente avisos por origem — 2026-10-25

O tema apresenta a divisão semântica das listas de avisos da central e da gaveta global por origem com separadores, preservando reflow e as listas internas de cada fonte. A biblioteca visual é anexada globalmente para cobrir o cabeçalho compartilhado; a aplicação do agrupamento permanece no contrato de renderização do Messenger.

Validação: Unit completo Messenger **221/1.582 PASS**, `ThemeHooksTest` **1/1 PASS**, Kernel MariaDB `ForumNoticeIdempotencyKernelTest` **38/891 PASS**, Foundation **345 checks PASS** e Design Foundations **2.170 checks PASS**. `ThemeHooks` mantém o perfil PHPCS preexistente, com um erro a menos (9/11 contra 10/11 no HEAD). Smoke read-only do Messenger passou **8/8** no Firefox ESR visível no Runtime de teste, em 1270 px; Alpha e demais viewports pendentes.

## 0.6.1-dev.10 — estados de carregamento e erro na gaveta — 2026-10-24

A gaveta agora apresenta o SDC `messenger-state` durante carregamentos AJAX e anuncia falhas de navegação como alerta.
A biblioteca `messenger-drawers` carrega o CSS do componente para garantir a apresentação também nas respostas de
fragmentos buscadas por `fetch`.

Validação: suíte de navegador do Messenger, Firefox ESR visível no Runtime de teste: **76/76 verificações aprovadas**.

## 0.6.1-dev.9 — não fechar a gaveta ao dispensar confirmação com Escape — 2026-10-24

Escape fecha a confirmação de amizade/bloqueio sem propagar ao listener do Bootstrap Offcanvas, mantendo a conversa aberta. O contrato atualizado está em `docs/messenger.md`.

Validação: suíte visível de navegador do Messenger em Firefox ESR (`DISPLAY=:0`), 75/75 verificações PASS; Escape fechou o modal e manteve gaveta e ação de bloqueio disponíveis. Sem mudança de autorização ou estado no módulo.

## 0.6.1-dev.8 — ações de leitura na central de avisos — 2026-10-24

Classificação: PATCH de integração visual.

- Adiciona estilos de layout, foco e hover para os controles de leitura da
  central do Messenger e mapeia os tokens `notice-read-state-form` e
  `notice-read-state-action`. Revisão visual no Firefox ESR/Runtime pendente.

## 0.6.1-dev.7 — schema das configurações do tema e render da home Wiki — 2026-10-24

Classificação: PATCH de compatibilidade do tema.

- `aculta420.settings` tipa as nove opções `b5_*` já fornecidas nos defaults do
  tema. A instalação do ACULTA420 falhava no Kernel porque essas chaves não
  tinham schema; não houve alteração de valores ou do contrato de apresentação.
- Kernel da Wiki instala o tema e renderiza a home com os SDCs `context-tabs` e
  `wiki-home` (focal 1/96). HTTP/Runtime e revisão visual continuam pendentes.

## 0.6.1-dev.6 — apresentar ações do workspace WE SATIVA

Classificação: PATCH de integração visual.

- `PortalUiHooks` mapeia `we-sativa-workspace`, `we-sativa-create` e
  `wiki-workspace-return` para utilitários Bootstrap; links agora recebem a
  tradução de token no preprocess do tema.
- Teste Unit cobre classes responsivas do workspace e ações. Firefox ESR/X11
  ainda não foi executado para WE SATIVA; Runtime e tela em 320 px pendentes.

## 0.6.1-dev.5 — ajustar busca da Wiki para não causar overflow

Classificação: PATCH visual responsivo.

- O submit do formulário de busca no cabeçalho Wiki deixa de herdar a largura
  `100%` do `.form-control` dentro do item flex e passa a ocupar só seu conteúdo.
- Firefox ESR visível no Runtime de teste, com CSS atual carregado diretamente
  por GET no browser: 46/46 verificações, sem overflow em 320, 390 ou 1280 px.
  O agregado/cache do Runtime não foi reconstruído; Olivero, contraste,
  teclado/leitor de tela e demais larguras permanecem sem validação.

## 0.6.1-dev.4 — removidos overrides de bundles editoriais aposentados

- Removidos `node--article--full.html.twig` e `node--blog--full.html.twig`; `revista` é o único bundle editorial ativo.

## 0.6.1-dev.3 — banner de consentimento sem sobreposição (2026-10-22)

Classificação: PATCH de acessibilidade e reflow.

- O banner passa a ocupar o fluxo após o rodapé, sem `position: fixed` cobrindo
  conteúdo ou foco até a decisão. Modos claro/escuro mantêm a mesma geometria.
- Firefox ESR no X11 do Runtime de teste: 15/15 verificações; capturas em
  390, 768 e 1024 CSS px, claro/escuro, sem overflow horizontal. Inspeção das
  capturas confirmou cartões livres da sobreposição em 390 px (claro/escuro) e
  1024 px (escuro); foco do link de salto verificado. Sem alteração de conteúdo.

## 0.6.1-dev.2 — hierarquia semântica dos cartões de categoria (2026-10-22)

Classificação: PATCH de acessibilidade e tipografia na linha aberta.

- Os títulos dos grupos da View `/categorias` usam H2; os links dos termos
  usam H3, preservando o estilo tipográfico do cartão.
- Firefox ESR/X11 no Runtime de teste: capturas em 320, 375, 390, 768, 1024 e
  1440 CSS px; document scrollWidth igual a clientWidth em todos. Os grupos e
  12 cartões renderizam. O banner global de consentimento se sobrepõe ao
  conteúdo até a escolha da pessoa; dark mode, zoom e dispositivo real não
  foram verificados. Nenhum teste automatizado executado.
- Foundation ajustada para a versão atual do tema `0.6.1-dev.2`.

## 0.6.1-dev.1 — remoção do tipo de conteúdo projeto (2026-10-21)

Classificação: PATCH na linha aberta, decidida pelo responsável.

- Removido o tipo `project` do código e da configuração: campos, displays, view `aculta_projects`, blocos de cabeçalho, pathauto, metatag, captcha, permissão de criação e bundle de busca.
- Removidos os campos `field_project` de activity, revista e media_gallery, e as views `aculta_related_activities` e `aculta_related_news`.
- Runtime de teste: 5 nós de projeto excluídos depois de backup do banco; a configuração foi removida pela API do Drupal.
- Versão `0.6.1-dev.1` registrada no `aculta420.info.yml`.

Documentação complementar, sem mudança de código ou versão:

- Atualizada a divisão de responsabilidade da categoria: Views agrupa a página
  `/categorias` por Parent; o SDC apresenta os termos e descrições.

## 0.6.0-dev.147 — auditoria Mobile First (documental) — 2026-10-21

Classificação: PATCH documental; sem alteração de código ou incremento de versão.

- Criados ADR-012 e inventário MF-0.1 com evidências do CSS, shell, assets e
  implementação/runtime atual do Studio. Roadmap Mobile First incluído como
  projeto identificado por fases, sem substituir o SemVer do tema.
- Preferência registrada para seletor nativo compacto de área no Studio mobile;
  implementação, fallback e validação permanecem na MF-0.4.
- Não executada homologação visual nesta fase. PHP CLI do checkout é 8.4.26;
  compatibilidade com o baseline PHP 8.5 continua pendente.

## 0.6.0-dev.143 — define tamanho dos modais de anexo e galeria do Fórum — 2026-10-21

## 0.6.0-dev.146 — grade e abas das planilhas com tokens (homologação RC1) — 2026-10-21

Classificação: PATCH (correção de apresentação com tokens semânticos).

- A grade (`.tabulator`, cabeçalho, linhas, células, rodapé, filtros e paginação) e as abas da planilha usam tokens de superfície, texto, borda e foco. Antes, a grade ficava branca no modo escuro.
- A aba ativa usa a cadeia completa de seletores do leitor, porque a regra do Tabulator é mais específica.
- Medido no Runtime (Chromium X11): modo escuro, grade e abas com contraste; modo claro e modo escuro em 390 px sem rolagem horizontal; alvos de modo com 44 px.

## 0.6.0-dev.145 — apresentação dos leitores DOCX e planilhas — 2026-10-21

Classificação: MINOR (componente de apresentação novo, com contrato neutro).

- Novo `css/components/document-viewer.css`, carregado pela biblioteca global do tema. Estiliza `[data-aculta-document-viewer]` e as partes `[data-aculta-document-part]` (cabeçalho, tipo, título, barra, zoom, status, quadro, modos e seletor de aba) com tokens semânticos (superfície, interação, foco, borda e tipografia). Sem cor fixa.
- Tela cheia usa o mesmo contêiner, com fundo de página no modo `:fullscreen`.
- Largura e altura do iframe do DOCX e do quadro da planilha respeitam o celular (16 px de gutter herdado do layout).
- Testado no Runtime (Chromium X11) com DOCX e XLSX públicos: título com HTML aparece como texto; zoom 100% → 115%; tela cheia ativada por clique real; status oculto ao concluir.
- Pendente: revisão visual em Firefox e em 390/768/1024 px; o PDF permanece com a folha `pdf-viewer.css`, sem alteração.

Classificação: PATCH (apresentação responsiva com tokens semânticos e diálogo Core existente).

- `PortalUiHooks` traduz `forum-attachment-dialog` e `forum-gallery-dialog` para classes do ACULTA420.
- `dialog.css` define largura responsiva e rolagem para os modais de upload contextual; nenhum modal ou CSS paralelo é criado pelo Fórum.
- `PortalUiHooksTest`: 6 testes/29 asserções PASS; Foundation: 368 checks PASS. Inspeção visual pré-RC pendente. Design Foundations continua com três falhas no contrato do envelope Mailer, fora desta mudança.

## 0.6.0-dev.142 — modo de cor do envelope segue o controle do site — 2026-10-22

Classificação: PATCH (apresentação do envelope; sem componente novo, sem cor nova).

- `MailEnvelopeHooks` resolve o modo `light`, `dark` ou `auto` recebido do aculta_mailer. Em `light` não há bloco escuro; em `dark` as cores escuras valem; em `auto` vale `prefers-color-scheme`.
- O template declara `color-scheme` conforme o modo e emite o bloco escuro só em `auto`.

## 0.6.0-dev.141 — rodapé do envelope de e-mail segue o layout de marca — 2026-10-22

Classificação: PATCH (apresentação do envelope do aculta_mailer; sem componente novo, sem cor nova).

- `MailEnvelopeHooks`: `show_footer` é falso quando o e-mail tem layout de marca (o rodapé já vem do corpo). Sem layout, o rodapé fixo da associação continua.
- `aculta-mailer-wrap.html.twig`: o rodapé fixo só é desenhado quando `show_footer`. Tokens claro e escuro inalterados.

## 0.6.0-dev.140 — espaçamento para documentos H5P — 2026-10-21

Classificação: PATCH (mapeia um token semântico do Portal para uma primitive Bootstrap existente).

- `PortalUiHooks` mapeia `lms-h5p-course-documents` para `mt-3`, separando visualmente o PDF privado complementar do player.
- Firefox no X11 (framebuffer 1024×768) confirmou H5P, PDF privado renderizado e Fórum contextual; o wrapper PDF recebeu `mt-3`. A fixture temporária foi removida.

## 0.6.0-dev.139 — estilos do conteúdo no CKEditor 5 — 2026-10-21

Classificação: PATCH (apresentação do conteúdo dentro do editor; sem componente novo, sem cor nova).

- `css/ckeditor5.css`, registrado por `ckeditor5-stylesheets` em `aculta420.info.yml`: títulos, citação, link, destaque,
  tabela, separador e quebra de página dentro de `.ck-content`, com tokens `--aculta-*` nos modos claro e escuro.
- Afeta a área de edição de todos os formatos CKEditor 5 do site, não só os do `aculta_editor`. Conteúdo salvo não muda.
- Revisão visual no editor pendente: a validação no navegador fica para o roteiro do `aculta_editor`.

## 0.6.0-dev.138 — tokens semânticos para discussões do LMS — 2026-10-21

Classificação: PATCH (mapeia a apresentação dos blocos existentes, sem criar novo componente).

- `PortalUiHooks` traduz `lms-activity-discussions`, `lms-h5p-activity` e
  `lms-h5p-discussions` para primitives Bootstrap. O adapter LMS emite somente
  tokens semânticos, conforme a fronteira Portal–ACULTA420.
- `PortalUiHooksTest` valida as classes geradas e a remoção dos tokens do HTML.
- O player com tópico real ainda não está disponível no Runtime de teste; a
  revisão visual permanece pendente.


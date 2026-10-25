# Roadmap — ACULTA420

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../docs/operations/RELEASE-LIFECYCLE.md). Todo requisito de código, testes, gates e revisão visual aplicável deve estar pronto antes de RC; Alpha é validação pós-deploy no servidor de desenvolvimento. Após Alpha aprovado, preparar a migração para produção e realizar checks específicos pós-deploy.

| Versão | Estado | Marcação | Conteúdo |
| --- | --- | --- | --- |
| 0.6.1-dev.13 | Separadores visuais entre grupos de avisos por origem | Messenger Unit 221/1.582, ThemeHooks Unit 1/1, Kernel 38/891, Foundation 345, Design Foundations 2170 e smoke Firefox ESR 8/8 PASS no Runtime de teste (1270 px); Alpha e demais viewports pendentes | Tema apresenta cabeçalhos/separadores do contrato de origem sem alterar autorização, ordem ou persistência |
| 0.6.1-dev.7 | Schema de settings e renderização SDC da home Wiki no Kernel | Kernel Wiki 1/96; Runtime e browser pendentes | `aculta420.settings` reconhece as nove opções `b5_*` dos defaults; instalação e render pelo tema passam |
| 0.6.1-dev.6 | Tokens visuais do workspace WE SATIVA e retorno aos trabalhos | PHPUnit Unit de PortalUiHooks; browser WE SATIVA pendente | Layout usa `d-grid/gap-4`, ação de criação `btn-primary`, retorno `btn-outline-primary`; não cria SDC paralelo |
| 0.6.1-dev.5 | Corrigida largura do submit da busca no cabeçalho Wiki | Firefox ESR/X11, CSS candidato direto, 46/46; sem overflow em 320/390/1280 px | Largura intrínseca evita que `.form-control` ultrapasse o formulário flex; agregado Runtime e Olivero pendentes |
| 0.6.1-dev.4 | Removidos overrides de Article e Blog aposentados | Changelog registra alteração compatível no tema | `revista` permanece com override próprio |
| 0.6.1-dev.3 | Banner de consentimento no fluxo normal para não cobrir conteúdo | Firefox ESR/X11: 15/15; capturas 390/768/1024 px em claro/escuro; sem overflow; foco do link de salto verificado | Sai da posição fixa e permanece após o rodapé; cartões sem sobreposição nas capturas inspecionadas |
| 0.6.1-dev.2 | Hierarquia semântica de grupos e cartões de categoria | Firefox ESR/X11, seis viewports, sem overflow; modo escuro/zoom/dispositivo real pendentes | Grupo H2; categoria H3 com tipografia existente; Foundation espera `0.6.1-dev.2` |
| 0.6.0-dev.147 | Template da publicação `revista` conectado ao SDC existente | Código criado; revisão visual no Runtime pendente | `node--revista--full.html.twig` reutiliza `revista-publication` sem duplicar CSS ou lógica editorial |
| 0.6.0-dev.146 | Grade e abas das planilhas com tokens em claro e escuro | Medido no Runtime (Chromium X11) em 390 e 768 px, claro e escuro; revisão em Firefox pendente | `document-viewer.css` com a cadeia completa de seletores |
| 0.6.0-dev.145 | Apresentação compartilhada dos leitores DOCX e planilhas (`aculta_document_viewer`): cabeçalho, barra de zoom e tela cheia, status acessível, abas e modo Tabela | Runtime testado no Chromium X11 com DOCX e XLSX públicos (zoom, tela cheia por clique real, status oculto ao concluir, título escapado); captura confirma tokens; revisão em Firefox e viewports móveis pendente | `css/components/document-viewer.css` com seletores `data-aculta-document-part`; sem classe de design no módulo |
| 0.6.0-dev.144 | Padrão visual comum para modais de upload do Portal | Fixture X11/Firefox com CSS real passou em 390/768/1024 px; inspeção do modal servido por Drupal no Runtime pendente | Token `user-upload-dialog` com rolagem responsiva; variantes do Fórum continuam reconhecidas |
| 0.6.0-dev.143 | Tokens e largura responsiva para modais de anexo/galeria do Fórum | Unit 6/29 e Foundation 368 PASS; visual pré-RC pendente; Design Foundations falha em três checks do envelope Mailer fora desta mudança | Dialog Core reutilizado; tema define dimensões e rolagem |
| 0.6.0-dev.142 | Modo de cor do envelope de e-mail segue o controle de aparência do site | Gate de fundação PASS; render dos três modos PASS | light, dark ou auto, pelo mesmo controle do site.
| 0.6.0-dev.141 | Rodapé do envelope de e-mail segue o layout de marca do aculta_mailer | Gate de fundação e render local com e sem layout PASS; revisão visual no cliente de e-mail pendente | Sem layout, o rodapé fixo é mantido. Com layout, o rodapé vem da marca. Tokens claro e escuro inalterados.
| 0.6.0-dev.140 | Token semântico para documentos PDF em atividade H5P | Runtime Firefox verificou PDF privado e conversas sob atividade; Gate de tokens atualizado | ACULTA420 aplica espaçamento entre player e documentos existentes por `lms-h5p-course-documents`. |
| 0.6.0-dev.139 | Estilos do conteúdo no CKEditor 5 (`ckeditor5-stylesheets`) | Foundation e revisão visual no editor pendentes; validação do fluxo completo no navegador pendente | Usa só tokens `--aculta-*`; afeta todos os formatos CKEditor 5 do site, não apenas o `aculta_editor`. |
| 0.6.0-dev.138 | Tokens de apresentação para discussões gerais e H5P do LMS | `PortalUiHooksTest` 5/26; Foundation 368 e design foundations 2132 PASS. Visual de tópico sob player depende de conteúdo H5P no Runtime | O Portal entrega tokens neutros; ACULTA420 mapeia-os para Bootstrap. |
| 0.6.0-dev.133 | Contraste do banner corrigido (superfície e texto de tokens, 12,4:1 no claro e 14,5:1 no escuro); revisão visual no claro feita, escuro e clique pendentes | Foundation, design foundations, SDC schema e shell contract PASS | Ajuste do `consent-banner` sem mudança de comportamento. |
| 0.6.0-dev.132 | Banner de consentimento para visitante anônimo (`consent-banner`), oculto até a decisão | Foundation, SDC schema e fronteira PASS; revisão visual responsiva/acessível e teste com a opção ligada pendentes; estado Dev | Comportamento no módulo `aculta_statistics` (`consent_banner.enabled`, desligado por padrão); mapa em `MAPA-MODULO-TEMA.md`. |
| 0.6.0-dev.131 | Componentes experimentais do QR Code dinâmico (`qr-preview`, `qr-download`) | SDC schema e Foundation PASS; revisão visual responsiva/acessível pendente; estado Dev | Consumidor público ainda não ligado; o Portal prepara URL e texto |
| 0.6.0-dev.130 | Identidade pública opcional do proprietário em micropáginas Links | SDC schema e revisão visual responsiva/acessível pendentes; estado Dev | Slot neutro renderizado pelo tema; privacidade e dados preparados pelo Portal |
| 0.6.0-dev.129 | Micropáginas separam identidade autoral da marca ACULTA | Validações do SDC, Foundation e visual pendentes; estado Dev | Título público torna-se H1 visível e a landing preserva a marca institucional |
| 0.6.0-dev.128 | Template da landing institucional restaurada conectado ao SDC de links | Runtime 200/404 e Firefox 390 × 844 claro conferidos; desktop, dark, teclado e leitor de tela pendentes | Apresenta conteúdo legado/fallback sem adicionar lógica de domínio ao tema |
| 0.6.0-dev.127 | Presets governados `classic`, `soft` e `outline` no SDC de links | Revisão visual responsiva/acessível ainda pendente | A micropágina escolhe uma opção permitida; o SDC usa tokens semânticos; a landing antiga conserva `classic` |
| 0.6.0-dev.126 | Template público do ACULTA Links conectado ao SDC existente; visual ainda pendente | Revisar micropágina em larguras móveis/desktop, claro/escuro, foco/teclado e leitor de tela |
| 0.6.0-dev.125 | Miniatura PDF responsiva | Alpha Firefox 390 × 844 identificou e corrigiu o overflow; render, controles e canvas verificados no Firefox WebDriver. Foundation 366, Design Foundations 2103 e fronteira módulo–tema 1608 PASS | Restringir a largura da miniatura ao viewer sem alterar o canvas do leitor |
| 0.6.0-dev.124 | Tokens do LMS no mapa; instalação alinhada ao Runtime | Foundation e design foundations PASS. Sem mudança visual | Tokens de moderação do Fórum no LMS mapeados; exceção restrita do e-mail no gate de modo |
| 0.6.0-dev.123 | Token de extensões PHP do painel de requisitos | Gate de fronteira Portal–tema PASS. Sem mudança visual | Token mapeado para a tabela de extensões PHP; documentado em TOKENS-E-ATRIBUTOS |
| 0.6.0-dev.122 | Cartão editorial integra autoria compartilhada | Firefox ESR/X11 em 1270/500 px, claro/escuro; fallback de inicial PASS. Foundation/design gates ainda apontam a integração paralela de e-mail. Runtime PARTIAL | SDC `author-card` recebe dados editoriais autorizados; Magazine só fornece Autor/Coautor |
| 0.6.0-dev.119 | Estado vazio da busca do Fórum | Runtime importou a View; Firefox/X11 1024 px confirmou painel, mensagem, link e formulário. Mobile/AT pendente. SDC, Design Foundations e Shell Contract PASS; Foundation falha na allowlist de Twig do template Mailer `email-wrap.html.twig`. Runtime PASS no estado vazio | Reutiliza o painel semântico do índice público em `.view-aculta-forum-search .view-empty` |
| 0.6.0-dev.118 | Formulário da busca pública do Fórum | Implementado; X11 a 1024 px confirma correção da descrição que colidia com o botão; Responsive Design Mode 320×480 parcial, viewport maior pendente. SDC, Design Foundations e Shell Contract PASS; Foundation bloqueado pela allowlist incompleta do template paralelo Mailer `email-wrap.html.twig`. Runtime PARTIAL | Colunas responsivas e textos auxiliares contidos em `forum-index.css` |
| 0.6.0-dev.116 | Ação de primeira não lida apresentada | Firefox ESR/X11/BiDi em 320/360/390/500/1250 CSS px: controle aparece com marcador `.new`, mantém 44 px no mobile, cabe em uma linha no desktop e some quando marcador não existe. Claro/escuro e ausência de overflow confirmados. Foundation Runtime e gates direcionados PASS. History autenticado real e leitor de tela/AT pendentes. RUNTIME PARTIAL | Estilizar a ação neutra criada pelo Portal com `data-aculta-discussion-unread` sem consultar History |
| 0.6.0-dev.115 | Ajuste de usabilidade mobile implementado | Em 320/360/390/500 px o slider ocupa largura própria e o índice fica visível; rótulos de botões cabem sem rolagem horizontal de texto. 320 px usa botões em duas colunas, demais larguras em uma linha. Claro/escuro e ações verificadas em X11/BiDi; sem overflow. Foundation Runtime e gates direcionados PASS. Leitor de tela/AT pendente. RUNTIME PASS no escopo visual | Expor a posição ao leitor visual sem comprimir o slider e preservar a leitura em telas estreitas |
| 0.6.0-dev.114 | Ajuste visual responsivo implementado | Firefox ESR/X11 e BiDi em 320/360/390/500/1250 CSS px: barra e controles dentro do container; em 320 px usa duas linhas, em 390 px mantém a linha compacta. Claro/escuro e navegação por botão/slider verificados; documento/corpo sem overflow. Foundation Runtime e gates direcionados PASS. Leitor de tela/AT pendente. RUNTIME PASS no escopo visual | Corrigir avanço lateral de controles em telas estreitas sem reduzir os alvos de toque de 44 px |
| 0.6.0-dev.113 | Implementado; validação parcial | Firefox ESR Runtime confirmou rolagem sem anúncio, botão anuncia, slider expõe valor; Foundation 366 PASS. Leitor de tela real e inspeção X11 deste ajuste pendentes | Separar posição visual da live region e manter `aria-valuetext` sincronizado |
| 0.6.0-dev.112 | Corrigido; mobile validado | Foundation 365 PASS; Firefox ESR/X11 BiDi a 390 × 844 CSS px confirmou barra `static`, sem cruzar a mensagem ativa; status 5/6, documento/corpo em 390 px. Leitor de tela/AT pendente. RUNTIME PARTIAL | Em larguras até 575 px a navegação está no fluxo, evitando sobrepor texto durante a leitura |
| 0.6.0-dev.111 | Corrigido; inspeção visual parcial | Foundation 365 PASS; Firefox/X11 escuro a 500 px confirmou a barra toda abaixo do cabeçalho (topo 56 px), progresso 4/6 durante rolagem e documento/corpo sem overflow. 390/AT pendentes. RUNTIME PARTIAL | `discussion.css` usa `--drupal-displace-offset-top` e os 3 rem de scroll-padding do cabeçalho |
| 0.6.0-dev.110 | Implementado; inspeção visual parcial | Foundation 365, SDC 535, Design 2097, fronteira 1419, mapa 152 e Shell 11 PASS; Firefox ESR/X11 verificou campo real com DOM sintético a 1250/500, navegação por botão/teclado, claro/escuro e sem overflow. Fórum vazio e sem conteúdo salvo. 390/AT pendentes. RUNTIME PARTIAL | Portal anexa controles; `discussion.css` apresenta progresso, foco, alvos e scroll offset; fallback linear sem JavaScript |
| 0.6.0-dev.109 | Implementado; visual parcial | Foundation 365, SDC 535, Design 2097, fronteira 1415, mapa 152, Portal 975 PASS. Firefox/X11 anônimo: lista 1024/500 e detalhe 500; duas linhas aparecem a 1024 e a coluna empilha a 500 sem corte aparente. Três tópicos/cinco Comments removidos; zero conteúdo QA, History baseline e flags preservadas. Dark/390/a11y pendentes. RUNTIME PARTIAL | Reduzir densidade desktop escondendo resumo/data redundante só na lista, compactar cartão de autor e preservar metadados/coluna responsiva |
| 0.6.0-dev.108 | Implementado; visual autenticado parcial | Foundation 364, SDC 535, Design 2097, fronteira 1415, mapa 152 PASS; Firefox/X11 autenticado em 1024 e Responsive Design Mode 390 × 670 px confirmou links, toggles e ausência de overflow visível. Modo escuro forçado localmente no DOM inspecionado em 1024 px; preferência Runtime não alterada. RUNTIME PARTIAL | Core/contrib geram ações e verificam access; ACULTA420 apresenta os links com tokens |
| 0.6.0-dev.107 | Implementado; visual claro validado | Firefox/X11 Runtime: lista 1024/768/390 e tópico 390 sem overflow observado; Foundation 364, SDC 535, Design 2097, fronteira 1415, mapa 152 PASS. Fixtures removidas; Runtime confirma zero tópicos/Comments e fila Messenger vazia. Modo escuro/autenticado pendentes. RUNTIME PARTIAL | Cartão unificado, metadados limitados e aba intermediária compacta; sem alterar o contrato Views/Portal |
| 0.6.0-dev.106 | Implementado; visual parcial | Foundation 365 via Drush, SDC 535, Design Foundations 2097; Firefox/X11 1024/768/390 sem overflow observado; light PASS, dark e tópico com conteúdo pendentes | Entrada Growroom420 combina categorias principais (View/CTools na sidebar) e lista recente; layout responsivo usa o SDC existente |
| 0.6.0-dev.105 | Remover templates da integração com Forum contrib | Foundation 362 via Drush, SDC 531, Design Foundations 2094, Portal 970, fronteira 1386, mapa 151 PASS; cache rebuild e raízes Fórum/Revista HTTP 200; sem tópico persistido para página individual. RUNTIME PARTIAL | Removidos presenters específicos de `node.forum`, `comment_forum` e `forum-list`; publicação deixa de excluir campo legado |
| 0.6.0-dev.104 | Cartões responsivos para categorias públicas do Fórum | SDC 531, design 2103, Foundation 362, Portal 970, boundary 1388, mapa 154 PASS; `/categorias` 200 e 12 cartões com links TID. Browser visual DEFERRED | SDC recebe os campos já renderizados pela View Core e apresenta hierarquia/descrição sem consultar entidades |
| 0.6.0-dev.103 | Data pública da conta em cartões de autoria do Fórum | Foundation 360, SDC 518, design 2089, Portal 970, boundary 1384, mapa 154 PASS; visual com tópico persistido pendente | SDC recebe apenas o ano; perfil privado e autoria Growroom histórica ficam sem data |
| 0.6.0-dev.102 | Comentários diretos da Revista aparecem no slot de discussão | Foundation 360, SDC 516, design 2089, fixtures 157, fronteira 1382, mapa 154, Drupal 11+ 970 PASS; Firefox confirmou slot/formulário; Views e browser admin confirmaram filas separadas e anônimos não veem pendentes. RUNTIME PARTIAL: submissão bloqueada pela falta da marca canônica de confirmação nos usuários QA; fixtures removidas e conteúdo novo limpo | `field_aculta_discussion` é passado como render array Core aos templates `article`/`blog`; tema admin é validado pelo gate Foundation conforme a rota |
| Não marcada | Cartão do último participante na lista Growroom420 | SDC 516 e design 2089 PASS; Runtime aplica o campo Core, a View responde 200 e a chamada sintética constrói o cartão. Foundation bloqueada por placement anterior da Revista; inspeção visual desktop/mobile/claro/escuro pendente | Reutiliza `author_forum`; não expõe login nem cria consulta no tema |
| Não marcada | SDC de autoria diferencia Fórum, Revista, LMS e Wiki420 | SDC 516, design 2089, YAML/Twig e render Runtime dos rótulos PASS; Foundation bloqueada por placement anterior da Revista; browser pendente | Rótulos contextuais neutros derivados das variantes `aculta_user`; sem dados de papel ou matrícula |
| Não marcada | Contador e última atividade na lista Growroom420 | Runtime HTTP 200 com oito linhas `0 respostas` e data; sort SQL descendente executado; SDC/design PASS; Foundation bloqueada por drift do bloco administrativo da Revista; navegador DEFERRED | Template reapresenta teaser, contagem Core e data |
| Não marcada | Navegação e linhas responsivas das listas ACULTA Forum | SDC/design/map/boundary PASS; Foundation bloqueada por placement Magazine prévio; HTTP 200 e CSS cacheado presentes; inspeção de navegador pendente | Links usam chips focáveis; contagem e atividade empilham no mobile |
| 0.6.0-dev.101 | Corrige classes dos cards do ACULTA Forum com base no render Firefox | Foundation, SDC, design e fixtures; Firefox desktop e viewport compacto de 500 px inspecionados. Viewport de 390 px e modo escuro pendentes. | Twig declara variante teaser/full, alinhada aos seletores; categoria vira etiqueta semântica |
| 0.6.0-dev.100 | Ajuste responsivo dos controles Comment | Foundation 354, SDC 516, design 2078 e fixtures 157 PASS; visual no navegador DEFERRED | Largura total exclui controles binários e de arquivo |
| 0.6.0-dev.99 | Formulário de resposta Comment compartilhado | Foundation 354, SDC 516, design 2078, fixtures 157, fronteira 1331 e mapa 154 PASS; Runtime raiz 200 e CSS agregado verificado; visual no navegador/envio DEFERRED | Regras responsivas e foco em `discussion.css` |
| 0.6.0-dev.98 | Estado vazio do índice Growroom420 | Gates PASS; raiz HTTP 200 mostra a mensagem | `.view-aculta-forum-public-topics .view-empty` em `forum-index.css`; inspeção visual pendente |
| 0.6.0-dev.97 | Apresentação de tópicos ACULTA Forum | Gates PASS; render Runtime não salvo PASS; inspeção visual no navegador pendente | `node--aculta-forum-topic.html.twig`; identidade via `aculta_user`; tokens em `forum-index.css` |

## Projeto ACULTA420 Mobile First — MF

Iniciativa autorizada pelo responsável em 2026-10-21. Os identificadores MF
organizam esta evolução e não são versões SemVer do tema. O Studio é o primeiro
consumidor de referência; todos os subsistemas continuam donos de seus dados,
permissões e operações. A decisão arquitetural está em
[`ADR-012`](../../../../../docs/decisions/ADR-012-aculta420-mobile-first.md),
com evidências da MF-0.1 na
[`auditoria responsiva`](mobile-first-audit.md).

| Fase | Estado | Escopo |
| --- | --- | --- |
| MF-0.1 | Concluída — 2026-10-21 | Inventário de CSS, breakpoints, shell, carregamento de assets, Studio, Runtime e cobertura existente; sem refatoração visual. |
| MF-0.2 | Planejada | Fundamentos responsivos compartilhados sobre tokens, grids, containers, tipografia, formulários, imagens e controles. |
| MF-0.3 | Planejada | Shell multidomínio, navegação, Conta, Messenger, Carrinho, alertas, diálogos e rodapé. |
| MF-0.4 | Planejada | Estrutura visual responsiva do Studio em três níveis. Preferência registrada: seletor nativo compacto para área no mobile; validar URL real, fallback sem JavaScript, teclado e leitores de tela. |
| MF-0.5 | Planejada | AJAX progressivo do Studio: histórico, concorrência, foco, carregamento, erros e formulários com alterações. |
| MF-0.6 | Planejada | Autoria editorial da Revista, CKEditor 5, Media, validação, workflow e ações de publicação. |
| MF-0.7 | Planejada | Listagens e moderação dos subsistemas reais, preservando acesso, tabelas e operações em massa. |
| MF-0.8 | Planejada | SDCs e padrões compartilhados: cards, filtros, comentários, galeria, documento, formulários e notificações. |
| MF-0.9 | Planejada | Aplicar padrões aprovados ao conteúdo público e aos demais domínios. |
| MF-0.10 | Planejada | Performance mobile, assets e bibliotecas contextuais; medição antes/depois quando aplicável. |
| MF-0.11 | Planejada | Acessibilidade WCAG 2.2 AA, teclado, zoom/reflow, foco, contraste e movimento reduzido. |
| MF-0.12 | Planejada | Homologação em viewports, navegadores e dispositivos disponíveis, com evidências por ambiente. |
| MF-1.0 | Planejada | Estabilização, documentação, gates existentes e liberação controlada. |

Atualizado em 2026-10-20 (`0.6.0-dev.104`; gates e HTTP de cartões de categoria PASS; inspeção visual DEFERRED). Dívidas e pendências, com evidência,
estão em [`docs/operations/DEBT-REGISTER.md`](../../../../../docs/operations/DEBT-REGISTER.md).
Este roadmap planeja o saneamento primeiro e as features de produto depois. Não planeja 1.0.

## Releases e marcações

| Versão | Estado | Marcação | Conteúdo |
| --- | --- | --- | --- |
| 0.6.0-dev.96 | Template e estilos de Comment compartilhado ACULTA | Gates estáticos pendentes; Runtime e inspeção visual DEFERRED | `comment--aculta-discussion-post.html.twig`, `discussion.css` |
| 0.6.0-dev.95 | Testes automatizados de InputHooks e PortalUiHooks; bibliotecas `web/libraries` versionadas | Unit 5/5 e Kernel 7/7 OK no banco de teste; validação visual pendente | `tests/src/Unit/PortalUiHooksTest.php`, `tests/src/Kernel/InputHooksKernelTest.php` |
| 0.6.0-dev.94 | Correção de arquitetura do preprocess de input; ícones oficiais do Bootstrap Icons 1.11.0 | Gates estáticos PASS; render no Runtime de teste verificado; validação visual pendente | `InputHooks` (único `preprocess_input`); `assets/icons/bootstrap-icons/icons/`; `docs/verificacao-boas-praticas.md` |
| 0.6.0-dev.93 | Botões de imagem e webform_multiple do Webform no Bootstrap 5 | Gates estáticos PASS; render do `image_button` verificado no Runtime de teste; validação visual pendente | `PortalUiHooks` troca o `src` dos ícones de adicionar e remover; `input[type=image]`; `css/components/webform.css` (`webform_multiple`) |
| 0.6.0-dev.92 | Webform no Bootstrap 5: regras portadas do webform_bootstrap 3.x | Gates estáticos PASS; validação visual dos formulários `/contato` e participação pendente | `css/components/webform.css`; itens cobertos pelo Bootstrap 5 não portados |
| 0.6.0-dev.91 | Cartão de autoria SDC experimental | Gates estáticos de SDC e design PASS; Foundation/Runtime visual DEFERRED porque Drush aponta `aculta_runtime` com URI `http://default` | `content/author-card`, variantes inline/empilhada e `author_growroom_historico` |
| 0.6.0-dev.78 | Imagens das duas galerias VVJS carregadas progressivamente | Runtime de teste: validar primeira imagem, avanço e clique no último slide do produto; álbum mantém evidência da dev.77 | `ThemeHooks` aplica adiamento também às linhas Media da `galeria_produto`; biblioteca VVJS compartilhada restaura imagem atual e próxima |
| 0.6.0-dev.77 | Imagens dos slides inativos são carregadas progressivamente | Firefox no Runtime: palco caiu de 63 requisições (8,8 MB) para 4 (572 KB); miniaturas visíveis/adjacentes: 20 (62 KB); clique na miniatura 60 carregou o slide 60 | Slide atual e próximo usam API/evento público VVJS; sequência e miniaturas preservadas |
| 0.6.0-dev.76 | Auditoria de consumidores VVJ e smoke test de slideshow | Runtime de teste: 7 mídias no produto e 63 no álbum; inspeção visual/teclado/toque DEFERRED | Sem consumidor semântico para VVJA/VVJF/VVJH/VVJR/VVJT identificado no inventário |
| 0.6.0-dev.75 | Gate Foundation alinhado à base Drupal Bootstrap 5.0.2, ao framework 5.3.8 e aos templates atuais | Gate PASS; mapa de tema do Runtime alinhado ao sync; DT-O14 parcial por configurações órfãs restantes | Revisão dos dois scaffolds 5.0.2; nenhum código novo necessário |
| 0.6.0-dev.74 | Galeria de produto migrada para VVJS, sem autoplay | Runtime de teste: 7 linhas VVJS renderizadas; inspeção visual DEFERRED | Preserva modo `produto_galeria` e apresenta imagens contidas |
| 0.6.0-dev.73 | VVJ patterns aprovados instalados; palco de álbum portado de VVJB para VVJS | RUNTIME STATUS: VVJ habilitados e View atualizada; validação visual DEFERRED | Mantém VVJB na Home/Projetos e PhotoSwipe na galeria |
| 0.6.0-dev.72 | LMS em pt-BR; “Lições” passa a “Aulas” em toda a interface traduzida | Catálogos `lms.pt-br.po` e `lms-terms.pt-br.po` importados; exportações do Runtime sem “lição/lições” | Catálogo LMS |
| 0.6.0-dev.69 | F5 Catálogo: SDC ACULTA420 substitui o cartão LMS na View existente | SDC schema, View access/cache, Firefox keyboard e host de testes verificados; RUNTIME STATUS: ACTIVE | `components/content/course_card/`, catálogo `courses_catalog` |
| 0.6.0-dev.68 | Viewer PDF: página em área rolável preserva o zoom escolhido em telas menores | Firefox 153.4 ESR no Runtime: PDF de 14 páginas, zoom 125%, controles e foco visível; viewport 1440 px e viewport estreito 500 px sem overflow horizontal da página. Smoke visual/acessível PASS em PV6; matriz de navegadores e CSP segue na PV7. | `css/components/pdf-viewer.css`, marcação do `aculta_pdf_viewer` |
| 0.6.0-dev.66 | Barra e cabeçalho do shell decididos pelo Portal (`domain_presentation.layout`); shell-contract PASS | Gates de shell, fundação, design e SDC PASS; capturas em main, wiki, cursos, apoio e conta; `links` não verificado (404 no teste) | `templates/page.html.twig` |
| 0.6.0-dev.67 | Painéis de vendas e doações: tokens `sales-*` (tabela de pedidos, estados e ações de pagamento) e `css/components/sales-panel.css` | Gates de fundação, design, shell, SDC, fronteira módulo-tema, mapa e docs de tokens PASS |
| 0.6.0-dev.65 | Tokens faltantes do módulo mapeados (avatar do perfil, avisos de conquista, relatório de imagens) | Gates de fundação, design e SDC PASS; shell-contract FAIL pré-existente (`page.html.twig`) registrado | `PortalUiHooks.php`, `messenger-drawer.css`, `portal-requirements-report.css` |
| 0.6.0-dev.61 | Apresentação do leitor PDF compartilhado | Chromium headless no Runtime: 14 páginas; viewport 1440 px e 390 px sem overflow mobile; RUNTIME STATUS: ACTIVE no Homelab | `css/components/pdf-viewer.css` |
| 0.6.0-dev.54 | Conquistas no perfil público | Firefox desktop/mobile e contrato módulo/tema verificados | `public-profile.css`, `PortalUiHooks.php` |
| 0.6.0-dev.53 | Tokens do Messenger, CSS e JS separados, janelas e acordeão | Suíte de navegador: 70 de 70 | `messenger-drawer.css`, `alerts-dismiss.js`, `PortalUiHooks.php` |
| 0.6.0-dev.52 | Confirmação para bloquear e desfazer amizade; botões Cancelar/Voltar da nova conversa | Capturas de tela e fluxo no runtime de teste | `messenger-drawers.js`, `messenger-item.css`, `PortalUiHooks.php` |
| 0.6.0-dev.51 | Menu "Opções" nas conversas e no perfil; largura e fonte | Capturas de tela no runtime de teste | `messenger-item.css`, `messenger-drawers.js` |
| 0.6.0-dev.50 | Resposta enviada pela gaveta sem navegação | Suíte de navegador: 52 de 52 | `messenger-drawers.js` (envio da resposta) |
| 0.6.0-dev.49 | Aviso de conversa restrita e de bloqueio no lugar da caixa de resposta | Testado no runtime de teste por POST e captura de tela | `messenger-item.css` (aviso), `PortalUiHooks.php` (token da ação do aviso) |
| 0.6.0-dev.48 | "Opções" em menu sanfona na mesma linha de "← Todas as conversas"; ações de amizade e bloqueio | Testado no Firefox headless em 1280×800 e 390×844 (alinhamento e ordem do menu medidos); ações conferidas por POST | `messenger-item.css` (grid da barra e menu), `messenger-drawers.js` (envio do formulário de Opções) |
| 0.6.0-dev.47 | Gaveta de mensagens com rolagem no desktop e no celular; link "← Todas as conversas" no topo, à direita; textos do Private Message em português | Testado no Firefox headless em 1280×800 e 390×844 (rolagem medida, capturas revisadas); RUNTIME STATUS: ACTIVE no teste | `messenger-item.css` (rolagem do corpo da gaveta), `messenger-drawers.js` (envio da nova conversa por fetch) |
| 0.6.0-dev.46 | Espaçamento superior compacto onde o cabeçalho de domínio é omitido | Visualmente verificado no Runtime em 1440×1000 e 390×844; home principal e Wiki preservadas; RUNTIME STATUS: ACTIVE | `aculta-main--compact-top` reduz o espaço para 0.5rem–1.25rem sem afetar a barra multidomínio |
| 0.6.0-dev.45 | Cabeçalho principal restrito à home do domínio principal; Wiki420 preservada | Verificado por HTTP no Runtime de testes; RUNTIME STATUS: ACTIVE | Cabeçalho com marca e menu oculto nas demais páginas e purposes; barra multidomínio preservada |
| 0.6.0-dev.44 | "Iniciar nova conversa" na primeira janela da barra de mensagens, com busca de membros a partir de 3 caracteres | Rotas verificadas por HTTP no runtime de testes (busca, compor); interação no navegador pendente | Etapa de busca e compor; envio pelo formulário nativo do Private Message |
| 0.6.0-dev.43 | Sanfona da conta sem texto de carregamento; mensagens só na barra | Verificado no runtime com membro comum em 360 px | Sem 'Carregando…'; sem seção de Mensagens na conta |
| 0.6.0-dev.42 | 99+ em todos os contadores, inclusive atualização ao vivo | Testado no runtime com valores máximos (1250, 120, 100) em 320 px e desktop | Script global corrige a atualização do carrinho |
| 0.6.0-dev.41 | Limite de exibição 99+ nos contadores | Simulado no runtime com 100 conversas não lidas; 99+ em 320 px e desktop | Exibição 99+ acima de 99; número exato em data-count |
| 0.6.0-dev.40 | Contadores de dois dígitos na barra mobile | Simulado no runtime (carrinho 12, sino 12) em 320, 360 e 390 px; mensagens de dois dígitos pendentes | Barra em uma linha; logo da barra fora da área visível até 374 px |
| 0.6.0-dev.39 | Barra multidomínio em uma linha no mobile; destaque de Minha conta por propósito | Verificado no runtime em 360, 390 e 414 px e nos domínios principal e conta; RUNTIME STATUS: ACTIVE | Logo e links compactados no mobile; classe da barra de conta vem do propósito do domínio |
| 0.6.0-dev.38 | Link Minha conta identificado por token | Verificado no runtime (aculta, conta, loja); RUNTIME STATUS: ACTIVE | Cor do link vem da classe `aculta-account-home`, não da posição no menu |
| 0.6.0-dev.37 | Rota de conversas e token de ação no tema | Verificação estática PASS (Foundation 341); RUNTIME STATUS: DEFERRED | Lista de conversas mapeada à rota `conversations`; token `messenger-preview-inbox-link` documentado |
| 0.6.0-dev.36 | Conversa no drawer via navegação AJAX | Verificação estática PASS; runtime a validar (RUNTIME STATUS: DEFERRED) | Abre conversa e volta para lista no próprio painel lateral; link da caixa ocupa toda a área clicável |
| 0.6.0-dev.35 | Data visível na gaveta lateral e apresentação compacta | Validação visual pendente (RUNTIME STATUS: DEFERRED) | Data em elemento explícito acima do nome; fonte secundária pequena |
| 0.6.0-dev.34 | Data da última mensagem nos contatos | Validação visual pendente (RUNTIME STATUS: DEFERRED) | Data/hora pequena acima do nome, sem ampliar intencionalmente o botão |
| 0.6.0-dev.33 | Caixa compacta de remetente e ação de amizade no perfil público | Capturas locais desktop/mobile da lista revisadas; fluxo de amizade DEFERRED | Mensagens exibem avatar circular + nome; ação do perfil usa a forma de botão “Ver Perfil” |
| 0.6.0-dev.32 | Agrupamento de conversas, menu contextual e badge contextual | Implementado; apresentação substituída por dev.33; RUNTIME STATUS: DEFERRED | Menu aciona formulário nativo de histórico pessoal; badge vermelho só no shell de conta |
| 0.6.0-dev.30 | Primeiro item de Minha Conta usa destaque vermelho semântico na barra | Implementado; validação visual pendente; RUNTIME STATUS: DEFERRED | Hover e foco usam superfície vermelha; demais itens do menu preservados |
| 0.6.0-dev.29 | Contadores vermelhos ao lado dos ícones da barra multidomínio | Implementado; validação visual pendente; RUNTIME STATUS: DEFERRED | Badge compartilhado pelo carrinho, mensagens e avisos |
| 0.6.0-dev.28 | Caixa em balões com avatar e modal de resposta; avisos vermelhos dispensáveis por pessoa | Implementado; validação interativa pendente; RUNTIME STATUS: DEFERRED | Private Message continua dono da thread/formulário; SDC só exibe render arrays autorizados |
| 0.6.0-dev.27 | Badges atualizados na abertura das gavetas e remoção do throbber da busca no desktop | Código local; visual desktop/mobile pendente; RUNTIME STATUS: DEFERRED | Resposta AJAX já transporta o contador; a busca continua AJAX e o throbber permanece em telas menores |
| 0.6.0-dev.26 | Gavetas Bootstrap de mensagens e avisos no padrão visual do carrinho, com AJAX ao abrir | Código local; validação de gates e runtime pendente; RUNTIME STATUS: DEFERRED | Mesmo shell Bootstrap, largura 420 px e tokens do carrinho; atualização apenas ao abrir, sem polling |
| 0.6.0-dev.25 | Palco do álbum com fronteira documentada: moldura neutra por token (claro e escuro), miniaturas e slideshow no palco; contrato módulo/tema verificado por gate (`validate-album-stage-contract.php`) | Gate de contrato PASS; validar palco no servidor de testes (desktop e celular, claro e escuro) | Token `--aculta-media-stage-bg`; biblioteca `aculta420/album-slideshow` global |
| 0.6.0-dev.24 | Gatilho modal de avisos e apresentação de leitura por conversa | Gates pendentes; RUNTIME STATUS: DEFERRED | Visualização consome estado nativo provido pelo Private Message/Portal |
| 0.6.0-dev.23 | Componentes SDC experimentais de conversas, mensagens, composição, Site Alert, notificações, não lidas e estados | Gates de código pendentes; Runtime visual DEFERRED | Slots preservam render arrays e formulários nativos; sem lógica funcional no ACULTA420 |
| 0.6.0-dev.22 | Durações de movimento centralizadas em tokens (DT-T19): gate de Foundation volta a passar | Gate de Foundation PASS (341 checks, verificado em 2026-10-15 via Drush no servidor de testes) | Tokens `--aculta-motion-fast` e `--aculta-motion-quick` |
| 0.6.0-dev.21 | Modelo padrão de publicação da Revista (`revista-publication`) | Entregue; os bundles antigos foram aposentados e seus overrides removidos em 0.6.1-dev.4 | `node--revista--full` usa o componente SDC; verificar homologação visual somente no ciclo de release |
| 0.6.0-dev.20 | Revista: imagens do corpo (notícia e blog) centralizadas na coluna de leitura e intertítulos alinhados à esquerda | Em desenvolvimento; gates de código PASS; validar no servidor de testes | Imagens do corpo chegam como mídia embutida (`media--type-image`), sem regra de módulo |
| 0.6.0-dev.19 | Sugestões interativas na busca Wiki420, com o comportamento de prévia compartilhado | Gates de código e HTTP local PASS; validar interação por teclado e visual no servidor de testes | O Portal consulta apenas verbetes acessíveis do Domain Wiki; tema estiliza o dropdown |
| 0.6.0-dev.18 | Apresentação Wiki420 inspirada na Wikipédia: cabeçalho compacto, busca junto da marca, categorias compactas e tipografia de leitura | HTTP e gates de contrato locais PASS; screenshot local indisponível; validar em desktop/mobile, claro/escuro no servidor de testes. Foundation bloqueado por DT-T19 | Busca recebida pelo contrato neutro; somente o Domain Header Wiki varia; nids de conteúdo de teste 72/73 somente em `aculta_runtime` |
| 0.6.0-dev.17 | Marca de domínio como contrato neutro: o Portal envia a chave, o tema entrega o asset (fronteira módulo x tema) | Em desenvolvimento; gate de fronteira PASS sem exceção do builder, teste com tema Claro PASS; inspeção visual DEFERRED | Compatibilidade de tema do Portal e da foto de perfil |
| 0.6.0-dev.16 | Apresentação enciclopédica Wiki420: pattern artigo/teaser, autoria omitida da leitura pública, home e busca revisadas | Em desenvolvimento; SDC/tokens/shell/fronteira PASS, Foundation bloqueado por DT-T19 preexistente; HTTP local PASS, inspeção visual DEFERRED | Primeiro layout enciclopédico com ownership Drupal → Portal → ACULTA420 |
| 0.6.0-dev.15 | Somente a Institution Bar multidomínio permanece no topo; Domain Header rola normalmente | RUNTIME STATUS: PASS; Chrome desktop/mobile em `/dados/outros-dados` (2026-10-13) | Sticky aplicado diretamente à barra institucional |
| 0.6.0-dev.14 | Institution Bar e Domain Header agrupados no topo durante o scroll | Comportamento substituído por 0.6.0-dev.15 após feedback do responsável | O wrapper sticky fazia o cabeçalho permanecer fixo também |
| 0.6.0-dev.13 | Botões adicionais da página LINKS recebidos do Portal e exibidos em sequência vertical | RUNTIME STATUS: validação pendente | Layout reutiliza a apresentação do botão fixo |
| 0.6.0-dev.12 | CTA “Visite nosso Site” na página LINKS, com destino à raiz MAIN resolvido pelo Portal | RUNTIME STATUS: pendente nesta alteração | Os canais sociais configurados permanecem no rodapé |
| 0.6.0-dev.11 | Página LINKS com canais administráveis, cards de projeto em carrossel VVJB e rodapé dedicado | RUNTIME STATUS: HTTP e visual mobile (claro/escuro) PASS; Foundation gate bloqueado por durações literais preexistentes em `portal-account.css` | Reusa View `aculta_projects`, SDC `carousel` e ícones Bootstrap `currentColor` |
| 0.6.0-dev.10 | Pattern mobile de links sociais, barra institucional e referências aos projetos publicados | RUNTIME STATUS: estrutura HTTP verificada no servidor de testes; inspeção visual pendente | Instagram institucional cadastrado; canais adicionais aguardam confirmação |
| 0.6.0-dev.7 | página do produto da loja em SDC: galeria em carrossel, compra com preço, detalhes e abas (layout da loja original) | marcada no código; gates PASS; captura de tela DEFERRED |
| 0.6.0-dev.6 | marca da barra institucional: logo ACULTA no lugar do nome, 78×26 (1x) e 156×52 (2x) em WebP; nome completo só no `alt` | marcada no código; validada visualmente (captura de tela) |
| 0.6.0-dev.5 | F3 (busca): apresentação da página `/busca`, do popup e dos resultados com tokens do tema | marcada no código; `/busca` verificada no servidor de testes; popup e teclado DEFERRED |
| 0.6.0-dev.2 | validador de navegador: regras de barra e sigla ajustadas ao shell; apoio indisponível aceito; PASS no site público | marcada no código |
| 0.6.0-dev.1 | F4 (UI Patterns): biblioteca de componentes com 9 stories; ui_patterns ^2.0 | marcada no código |
| 0.1.0 | concluída (2026-10-07) | marcada no código | Foundation: provider, namespace, tokens, fronteiras |
| 0.2.0 | concluída (2026-10-09) | marcada no código | Shell multidomínio: Domain Presentation, Institution Bar, Domain Header, sticky, QA |
| 0.3.0 | concluída (2026-10-09) | marcada no código | Card System v1: `editorial-card` stable, `project-card` experimental, skin do curso |
| 0.3.1 | concluída (2026-10-09) | marcada no código | Padrões SDC: seções, hero, grade, carrossel e cabeçalho por purpose |
| 0.4.0 | linha 0.4.x fechada em 0.4.5 (2026-10-09), sem versão 0.4.0 retroativa (decisão do responsável) | sem tag | Patterns v1; T1 a T6 com decisão ou conclusão registradas (T5 com reconstrução em ambiente novo DEFERRED) |
| 0.5.1 | F2 (ícones) concluída: Bootstrap Icons, sem dependência nova; gate contra SVG inline no tema | marcada no código (release) |
| 0.5.0 | F1 (modo de cor) concluída: claro, escuro e automático; validada no Runtime oficial em 2026-10-09 | marcada no código (release) |
| 0.5.0-dev.3 | F1 (modo de cor): atributo aplicado via `b5_theme_mode` (o Bootstrap 5 sobrescrevia `html_attributes`); verificado por requisição HTTP em cópia isolada do Runtime | marcada no código; RUNTIME STATUS DEFERRED |
| 0.5.0-dev.2 | F1 (modo de cor): modo claro, escuro e automático com contrato do Portal; gate ajustado para o bloco automático e o ramo de contrato | marcada no código; RUNTIME STATUS DEFERRED |
| 0.5.0-dev.1 | F1 (modo de cor): medição do modo escuro em navegador, pré-requisito atendido; tokens da barra sem alteração | marcada no código |
| 0.4.5 | fechamento da linha 0.4.x (2026-10-09): 0.4.4 (PR #122), 0.4.5-dev.1 a dev.5 (PRs #124, #129, #130 e o dev.5); DT-T18 pendente, independente do tema | marcada no código (release) |
| 0.4.5-dev.5 | sitemap do validador de navegador delegado ao `aculta_deployer sitemap`; DT-T18 pendente (independente do tema) | marcada no código |
| 0.4.5-dev.4 | validador de navegador: página de apoio no host SUPPORT (DT-T18) | marcada no código |
| 0.4.5-dev.3 | cards de projeto: imagem 256 px com 2x e conteúdo centralizado | marcada no código |
| 0.4.5-dev.2 | imagem dos cards de projeto e grade responsiva (CSS entregue pela biblioteca global) | marcada no código |
| 0.4.5-dev.1 | kit de artes Bloco Sativa 420 (sem consumidor de template; autoria e licença pendentes) | marcada no código | Kit validado offline; QA em navegador DEFERRED |
| 0.4.4-dev.1 | gates executados no runtime e correção do gate institucional | marcada no código |
| 0.4.3 | PR #115 mesclada (kit Baque Sativa; sem consumidor de template) | marcada no código | Kit validado offline; QA visual e runtime DEFERRED |
| 0.4.2 | PR #114 mesclada (assets Podplant420; handoff fora do tema, sem consumidor de template) | marcada no código | Assets de identidade Podplant420 validados offline; QA visual e runtime DEFERRED |

Regras de versão em [`docs/versioning.md`](versioning.md). A versão atual fica marcada em
`aculta420.info.yml`. Tags Git só são criadas sob pedido do responsável.

## Saneamento

### T0 — Higiene documental (DT-T01, DT-T02, DT-T14) — concluída em 0.4.0-dev.2

- Remover "0.1.0" como versão atual em `README.md` e `docs/README.md` (`docs/features.md` já foi removido),
  `docs/design-system.md` e `docs/development.md`. Manter menção histórica quando for histórica.
- Descrever o estado atual do shell (0.2.0 em diante), e não a Foundation.
- Critério: nenhum documento afirma "versão atual 0.1.0"; o gate de documentação passa.

### T1 — Fechar a linha 0.4.0 (DT-T03) — decisão registrada em 0.4.0-dev.2

Estruturas internas permanecem como rich text (ver `components.md`, decisão T1). T2 e T3 foram concluídas em 0.4.0-dev.4 e 0.4.0-dev.5, então a condição de fechamento da linha está cumprida. Como o tema já está em 0.4.3, fechar a linha com a versão `0.4.0` seria um retrocesso de versão; o fechamento sem versão retroativa depende de decisão do responsável.

- Decidir, para cada estrutura interna, entre migrar para SDC ou manter como conteúdo rico:
  `aculta-areas`, `aculta-callout`, `aculta-page-intro`, CTAs (`aculta-editorial-link`) e
  `aculta-institutional-note`.
- Critério (decisão do responsável, 2026-10-09): decisão registrada por estrutura; a linha 0.4.x fecha em `0.4.5` sem versão `0.4.0` retroativa e sem tag. Cumprido.

### T2 — Validação em navegador (DT-T04, DT-T05, DT-T06) — concluída em 0.4.0-dev.4

- Carrossel validado em navegador e reativado na home em 0.4.0-dev.3 (DT-T04).
- Rail validado com quatro cursos reais publicados em 0.4.0-dev.4 (DT-T05): rolagem por teclado,
  sem rolagem horizontal da página e todos os cards alcançáveis por Tab. Os cursos de teste foram
  removidos após a medição.
- Foco por teclado medido com foco real (DT-T06): o card do LMS tinha só a borda amarela, com cerca
  de 1,6:1 sobre superfície clara; recebeu anel com `--aculta-focus-ring`.
- Rótulos em inglês corrigidos (DT-T15) em 0.4.0-dev.3.
- Critério cumprido: cada item com medição registrada no changelog, sem clones no DOM.

### T3 — CSS residual dos padrões (DT-T07) — concluída em 0.4.0-dev.5

- `.aculta-hero` e `.aculta-eyebrow` saíram de `institutional.css` para `components/patterns/hero/hero.css`.
- `.aculta-section-title` saiu de `content.css` para `components/patterns/content-section/content-section.css`.
- `.aculta-project` (moldura do presenter) e seus filhos saíram de `institutional.css`, `content.css` e `drupal-bootstrap.css` para `components/content/project-card/project-card.css`.
- Medição antes/depois: estilo computado idêntico por propriedade em 17 seletores de controle e padrão; 14 capturas de tela idênticas byte a byte (1280 e 390 px, 7 páginas); foco e movimento reduzido do card idênticos.
- Os seletores compartilhados por `.card` continuam globais em `content.css`, porque ainda não têm consumidor de SDC.

### T4 — Validação automatizada (DT-T08, DT-T09) — concluída em 0.4.1

- Schemas dos SDCs validados contra props e slots usados nos templates (`validate-aculta420-sdc-schemas.php`, DT-T08).
- Validadores `.mjs` sem porta nem origem fixas: leem `ACULTA_DEVTOOLS_PORT` e `ACULTA_SITE_ORIGIN` por `scripts/lib/browser-env.mjs`. Sem as variáveis, o validador para antes de conectar (DT-T09).
- Gate `validate-browser-validators.php` reprova endpoint literal nos scripts.
- Critério: schema inválido quebra o gate; nenhuma porta fixa nos scripts. Cumprido.
- Dívida nova: `validate-institution-browser.mjs` espera `/apoie` no host principal e `aculta_favicon.ico`. A página de apoio fica no host SUPPORT (homelab `apoio.aculta.toca.net.br`; produção `apoio.aculta.org`), na rota `/apoio` (Portal 0.2.0-dev.3). Falta a reescrita de `supportLayout` e o favicon oficial (DT-T18, pendente e independente do tema).

### T5 — Portabilidade do conteúdo (DT-T10, DT-O03) — concluída em 0.4.1; reconstrução em ambiente novo verificada em 2026-10-09

- Conteúdo de seções, missão, cabeçalho de projetos e hero declarado em `scripts/content/institution/home-content.json`, com exportador e loader por UUID, dry-run por padrão (`ACULTA_APPLY=1` para gravar).
- Gate `validate-institution-content.php`: o JSON cobre todo UUID referenciado pelas colocações da home e do cabeçalho.
- Dry-run no Runtime atual: 14 entidades sem diferença. Teste negativo: alteração do slogan detectada.
- DEFERRED: reconstrução completa num ambiente novo. O único snapshot em `estados/` é de 2026-10-04 e não tem os campos de seção nem do hero; a verificação exige importar a configuração num ambiente de teste, o que fica para uma tarefa autorizada.
- Critério parcial: versionado e verificável; reconstrução em ambiente novo pendente.

### T6 — Dependência do LMS (DT-T11) — concluída na PR #113 (merge 0.4.1)

- A skin do `lms:course_card` não define nem consome mais as variáveis `--color-*` do módulo. Propriedades com tokens ACULTA, mesmos seletores e cascata.
- Medição: sem diferença de estilo computado no catálogo (padrão e foco, 1280 e 390 px); capturas idênticas.
- Gate `validate-lms-skin.php`: fixa a versão do LMS revisada (1.2.3). Upgrade reprova até revisão da skin e da captura de referência.
- Decisão: cores de status do LMS ficam como fallback do módulo.
- Critério cumprido para o que é visível a visitante; estados de status pendentes (ver CHANGELOG 0.4.1).

## Features de produto (depois do saneamento)

Só começam quando T0–T4 estiverem fechadas, ou quando o responsável priorizar explicitamente.
Nenhuma destas fases tem versão alvo de 1.0.

### F1 — Modo de cor e controle de aparência (DT-T12)

- Seleção claro, escuro e automático com `data-bs-theme`; preferência persistente para usuário
  autenticado na camada de conta, e local e sem quebra de cache para anônimo.
- Pré-requisito: T2 (validação do modo escuro em navegador real).

**Estado: concluída em 0.5.0 (2026-10-09), validada no Runtime oficial.**

- Decisões do responsável: o controle fica em **Minha Conta > Configurações** (`/configuracoes`, no Portal); a preferência é gravada por usuário no banco (`user.data`); há três estados (claro, escuro, automático); o padrão global do site fica em `/admin/config/aculta/aparencia`.
- Portal (0.2.0-dev.18): `ColorModePreference` resolve o modo e publica o contrato `aculta_color_mode`. O tema não lê usuário nem configuração.
- Tema: `ThemeHooks::preprocessHtml` aplica `data-bs-theme` diretamente em `html_attributes`, conforme o contrato resolvido pelo Portal. A base Drupal Bootstrap preserva esses atributos; em `auto`, o tema remove o atributo e `tokens.css` decide por `@media (prefers-color-scheme: dark)` guardado por `:root:not([data-bs-theme="light"])`.
- Sem JavaScript de modo e sem `localStorage`. Tokens da barra institucional não foram alterados.
- Evidência no Runtime oficial (`https://aculta.toca.net.br`, código da `main` em `ce87dcb`):
  - roteiro automatizado: 7 de 7 checagens (padrão global, escolha da pessoa usuária, HTML anônimo e logado, cache invalidado após salvar);
  - HTTP: `light` → `"light"`, `dark` → `"dark"`, `auto` → `""`;
  - automático no navegador: sistema escuro → fundo `rgb(23,21,19)`; claro → `rgb(242,247,240)`;
  - contraste no modo escuro: 52 medidas, pior caso 8,82:1, sem overflow.
- Gates na `main`: design, Foundation (310), SDC, validadores de navegador e Portal, todos PASS.
- Sem teste humano (decisão do responsável). Padrão inicial do site: `light`.

### F2 — Ícones (DT-T13)

**Estado: concluída em 0.5.1 (2026-10-09). Bootstrap Icons 1.11.0; sem dependência nova.**

- Decisão do responsável: ícones pelo Bootstrap Icons.
- Bootstrap Icons 1.11.0 foi copiado para `assets/icons/bootstrap-icons/` e é servido pela biblioteca do ACULTA420, sem dependência do tema base. A verificação histórica no site público confirmou a família `bootstrap-icons` e o glifo `bi-house`.
- Regra: ícone é `<i class="bi bi-NOME" aria-hidden="true"></i>` ao lado de texto visível (ou rótulo acessível no pai). Nenhum SVG inline de ícone nos templates do tema; o gate `validate-aculta420-design-foundations.php` reprova.
- Sem SDC de ícone até haver consumidor com contrato reutilizável (AGENTS: não criar SDC só para substituir uma classe simples).
- Fora do tema: o editor de fotos do Portal (`aculta-portal-photo-editor.html.twig`) tem um SVG inline. Não é tratado aqui, porque o Portal não referencia componentes do tema.

### F3 — Busca e feedback (DT-T13)

- **Decisão do responsável (2026-10-09): busca pelos serviços do banco** (Search API com o backend de banco de dados), sem serviço de busca externo.
- **Estado: backend e página entregues no Portal 0.2.0-dev.19 e validados no Runtime oficial** (`/busca`, sugestões do Core, avisos por Messenger; conteúdo não publicado excluído). Pendências: `content_access` no índice e testes de Kernel.
- Apresentação de busca com Search API e Autocomplete; mensagens por toast e alert com
  Messenger. Backend e índices ficam no Portal e no Core.
- **Apresentação no tema (0.6.0-dev.5):** `css/components/search.css` (página, popup e resultados). Pendente: verificação do popup e do teclado no servidor de testes (`/busca` já verificada em 1280 px) . A remoção da regra com cores fixas no Portal foi feita na `aculta_portal` 0.2.0-dev.46 (PR #186).
- Pré-requisito: F2 (concluída em 0.5.1). Verificar, antes de implementar, se o backend de banco atende a relevância e a latência esperadas.
- **Estado: aprovada no reteste de navegador (2026-10-11).** Verificado no servidor de testes com Claude in Chrome, logado como admin: popup, foco, Tab e Shift+Tab em ciclo, rótulo do botão fechar, Esc, prévia com mínimo de caracteres, setas, Enter e página `/busca` (campo com o termo e dica sem termo). Exceção: T10 (390 px) não executado, porque a janela de teste não redimensiona. Dívida fora do tema: DT-P24 (erro do Core Navigation no Esc, só logado).

### F4 — Padrões de Drupal UI e biblioteca de componentes (DT-T13)

**Estado: concluída em 0.6.0-dev.3 (biblioteca UI Patterns Library com estados documentados).** Consumidores em Views e Manage Display ficam para quando houver uso real.

- UI Patterns para Views e Manage Display onde houver consumidor real.
- Biblioteca navegável dos componentes (UI Patterns Library ou UI Examples), com estados
  documentados.
- Pendente de decisão do responsável: qual módulo usar para a biblioteca. UI Patterns expõe os componentes SDC do tema ao site builder (Layout Builder, Views, formatadores). UI Examples é um módulo de exemplos de referência, para documentar componentes; não muda a renderização. Recomendação: UI Examples para a biblioteca, e UI Patterns só quando houver consumidor real.
- Pré-requisito: T4 (schemas validados).

### F5 — Mega menu

- **Registro histórico (2026-10-09):** a validação de produção era adiada até o primeiro RC. Pelo fluxo vigente, concluir todos os requisitos em RC, validar o deploy Alpha no servidor de desenvolvimento e, após aprovação, migrar o candidato a produção.

- **Painéis de estatísticas (0.6.0-dev.137):** tokens `statistics-*` e `css/components/statistics.css`, para o painel central e as áreas do `aculta_statistics` (período 7/14/30, comparação, CSV). Autorizado pelo responsável em 2026-10-21. Verificado no servidor de testes: as telas de estatísticas e de envios abrem no tema administrativo Claro, e o CSS deste tema não se aplica a elas. Decisão do responsável: ajustar só o conteúdo.

## Critério para encerrar o saneamento do tema

Todos os itens de DEBT-REGISTER do tema estão em Resolvida, Decisão registrada ou Aberta com
justificativa aceita pelo responsável. Os gates passam. Não há dívida nova sem registro.

## Roadmap visual transversal — THEME-UX 0.8 (planejado)

O planejamento visual originado no GanjaLab420 agora possui **roadmap de implementação próprio do tema**: [visual-cross-product-roadmap.md](visual-cross-product-roadmap.md). As microfases THEME-UX 0.8-A/B0/B1/B2/B3/B4/B5 cobrem auditoria de consumidores SDC reais, preservação mandatória do shell multidomínio, context header interno, abas, estados, leitor/listas de recursos e catálogo UI Patterns. Priorizar reutilização entre GanjaLab420, GanjaLab, Revista, Wiki, Fórum, Galeria e Conta. **Somente planejamento**: não incrementar versão de release do tema nem marcar gates PASS por esta documentação. Os ciclos e marcos existentes neste roadmap permanecem preservados.

## Próxima fase técnica de tema — THEME-UX 0.8-A/B0 com UI Suite

[Plano de auditoria técnica e UI Suite](theme-ux-0.8-a-ui-suite-audit.md) organiza o inventário Runtime de SDC, UI Patterns, UI Patterns Library, UI Styles, Fences e integrações Views/blocks/fields/layouts, com preservação obrigatória do shell multidomínio. Ver [roadmap visual transversal](visual-cross-product-roadmap.md). **Planejado, não executado**.

## Pacote de preparação THEME-UX 0.8-B0

[Matriz de execução e evidências UI Suite](theme-ux-0.8-b0-execution-matrix.md): fases B0-R1 a B0-R4, auditoria do shell em todos os purposes, inventário de SDC, UI Patterns, UI Styles, Fences e stories. Todos os testes do Runtime continuam pendentes. A implementação B1 dependerá do gate B0 documentado.

## Gate de migração SDC/UI Suite — decisão obrigatória

A trilha THEME-UX 0.8 passou a **proibir reutilização direta de padrões legados sem base SDC/UI Suite**. B0 classifica `SDC-CONFORME`, `SDC-PARCIAL`, `LEGADO-MIGRAR`, `DESCARTAR/SUBSTITUIR`; migração/adequação e stories antecedem a adoção transversal. Ver [roadmap visual](visual-cross-product-roadmap.md). Nenhuma fase técnica foi considerada executada por esta atualização.

## Inventário SDC/UI Suite — fotografia estática (2026-10-22)

[Inventário e backlog de migração](sdc-ui-suite-inventory-2026-10-22.md) confirmou 38 bases SDC e 55 stories na árvore; três componentes ainda sem stories. A lista M0–M6 prioriza validação e adequação SDC/UI Suite antes de reutilização transversal. Runtime e homologação permanecem pendentes.

## Execução estática 0.8 — M1 e próxima revisão M0-S

[Inventário SDC/UI Suite](sdc-ui-suite-inventory-2026-10-22.md): 38 SDCs; 58 stories após três correções de cobertura (forum-category, qr-preview e qr-download). O [plano de continuação fora do Runtime](theme-ux-0.8-b0-static-followup.md) estabelece comparação de schemas/Twig/stories e análise de cabeçalho contextual. Cobertura de arquivos concluída, renderização e testes Runtime pendentes; não avançar release por este marco.

## M0-S — auditoria estrutural do tema

[Relatório M0-S](theme-ux-0.8-m0-s-structural-audit.md): inspeção direcionada dos contratos SDC de QR, content-section, product-tabs, course_card, card e forum-category; schemas QR alinhados ao Core. O Context Header não deve reutilizar heading H2/H3 de content-section como H1. Gate Runtime ainda pendente; prosseguir para M2/B1 somente após verificar consumidores reais e shell multidomínio.

## Próxima implementação transversal — M2/B1

[Contrato técnico de Context Header](theme-ux-0.8-m2-b1-context-header.md) define dados/slots, consumidores, uso obrigatório de Core SDC + UI Suite e gates para preservar Institution Bar e Domain Header. A etapa é planejamento, sem componentes novos e com validação Runtime pendente.

## THEME-UX 0.8-B1 — Auditoria estática de cabeçalhos

[Relatório de inspeção de breadcrumb e headings](theme-ux-0.8-b1-source-audit.md): confirmado template de breadcrumb existente, CSS tokenizado e limites semânticos de `content-section`/`product-tabs`. Próximo gate B1 depende de DOM e dois consumidores reais; nada autoriza substituir Institution Bar/Domain Header ou criar um SDC sem uso.

## Mudança normativa do Context Header — H1 gerenciado pelo SDC

O H1 nativo **não precisa ser preservado visualmente**: em consumidores adotantes, o `Context Header` poderá produzir o único H1 do documento, com TitleResolver/page title do Core permanecendo fonte dos dados e remoção da duplicação pela composição de render arrays. A migração é seletiva, não global. Ver [contrato revisado](theme-ux-0.8-m2-b1-context-header.md) e [próxima fase B1-02](theme-ux-0.8-b1-02-ownership-h1.md). Sem alteração funcional nesta etapa.

## B1-02a — mapeamento de consumidores no código

[Relatório B1-02a](theme-ux-0.8-b1-02a-consumer-code-map.md): rota e controller do GanjaLab420 identificados; Wiki full já usa SDC com H1. Próxima entrega B1-02b requer DOM real e decisão semântica para compartilhar Context Header sem duplicar títulos, mantendo a barra multidomínio global. Estado: inspeção de código, não implantação.

## B1-02b — Contrato visual do Context Header

O [contrato visual B1-02b](theme-ux-0.8-b1-02b-visual-contract.md) estabelece variantes conceituais standard/editorial/compact e o H1 único sob responsabilidade do SDC contextual, com migração seletiva dos consumidores LMS e Wiki. Institution Bar + Domain Header existentes são obrigatórios. **Somente planejamento:** nenhum SDC adicional ou teste Runtime foi executado. Próxima microfase B1-02c depende da validação do DOM e de consumidores reais.

## THEME-UX 0.8-B1-02c — primeiro SDC transversal disponível em código

`aculta420:context-header` está presente no tema com metadados Core SDC, Twig com H1 único condicional ao consumidor, CSS mobile-first tokenizado e duas stories. **Status experimental; sem integração real em LMS/Wiki nem validação UI Patterns Library/Runtime.** A próxima fase B1-02d é migração seletiva de consumidores, com remoção de H1 redundante; manter Institution Bar + Domain Header. Ver [contrato visual](theme-ux-0.8-b1-02b-visual-contract.md).

## B1-02d — primeira integração Wiki (2026-10-22)

O template `node--wiki-entry--full.html.twig` passou a incluir `aculta420:context-header` como H1 do verbete. O `wiki-entry` recebeu `show_heading` (default true) e o uso na página completa define false para evitar duplicação; o teaser permanece inalterado. **Integração de código commitada, não validada no Runtime.** Verificar a renderização SDC Twig, classes e layout da Wiki, cache, acesso, um H1 e a permanência da Institution Bar e do Domain Header antes de aprovar. Não liberar migração LMS sem inspeção específica.

## B1-02e — segundo consumidor integrado, homologação pendente

O dashboard GanjaLab420 agora renderiza `aculta420:context-header` como H1 e o hook do tema evita o bloco page-title redundante somente em `aculta_lms.student_dashboard`, preservando `_title`, acesso, cache e shell. Wiki e LMS são agora dois consumidores no código; a [matriz de testes Runtime](theme-ux-0.8-b1-02e-runtime-validation.md) ainda deve ser executada. Próxima trilha visual: B2 Context Tabs em SDC/UI Suite.

## THEME-UX 0.8-B2 — Context Tabs em SDC/UI Suite

Novo componente experimental `aculta420:context-tabs` criado com schema Core SDC, CSS tokenizado e duas stories UI Patterns Library. [Plano B2 e evidências](theme-ux-0.8-b2-context-tabs.md) distingue navegação por URLs reais de `product-tabs` (painéis Bootstrap), preservando Institution Bar + Domain Header. A integração com o Studio e a Wiki, assim como testes Runtime, permanecem **pendentes**.

## B2-03 — primeiro consumidor de Context Tabs (2026-10-22)

O GanjaLab (`aculta_studio`) integra agora `aculta420:context-tabs` nos menus de áreas e abas da seção, preservando links Drupal/PortalLinkElement, AJAX progressivo e fallback sem JS por contrato. CSS do componente passou a acomodar `item_list` renderizável. [Relatório B2](theme-ux-0.8-b2-context-tabs.md). Sem testes Runtime; o segundo consumidor e homologação continuam pendentes.

## THEME-UX 0.8-B2-04 — segundo consumidor em código

A Wiki (`/wiki` e `/wiki/busca`) reutiliza agora `aculta420:context-tabs` com duas URLs Drupal reais e `aria-current=page`. Junto ao GanjaLab, são dois consumidores integrados por código. Ver [B2](theme-ux-0.8-b2-context-tabs.md). Próxima fase B2-05: homologação Runtime e correção de CSS/semântica antes de B3; shell multidomínio obrigatório.

## Fase B2-05 e abertura B3 — auditoria de feedback

[B2-05 static gates](theme-ux-0.8-b2-05-static-gates.md): revisão de código de navegação GanjaLab/Wiki registrada, homologação Runtime PENDENTE. [B3 feedback audit](theme-ux-0.8-b3-feedback-audit.md): inventário inicial dos SDCs existentes `messenger-state` e `institutional-notice`, proposta de estados universais sem reproduzir sistemas Drupal Core. Nenhum novo SDC B3 antes de duas necessidades reais e avaliação UI Suite. Shell multidomínio permanente.

## B3 — primeiro estado neutro reutilizado

`aculta420:empty-state` criado com Core SDC, UI Patterns stories e CSS semântico, conectado em resultados vazios de busca Wiki e listas vazias de cursos/certificados GanjaLab420. [Evidências B3](theme-ux-0.8-b3-feedback-audit.md). Runtime/QA pendentes; Messenger/Site Alert/Studio AJAX preservados.

## B4 — Painéis da barra multidomínio e Minha Conta

[Auditoria e correções B4](theme-ux-0.8-b4-utility-panels.md): eliminado X que ocultava itens de carrinho sem remover do Commerce; largura/rolagem/foco de offcanvas padronizados para avisos, mensagens e carrinho; reflow mobile da Minha Conta ajustado. Alterações commitadas no código, testes Runtime PENDENTES. Próxima fase B4-02 de homologação de overlays/controles; preservar Institution Bar + Domain Header.

## B4-02 — proteção das gavetas contra respostas AJAX obsoletas

[Relatório B4-02](theme-ux-0.8-b4-02-ajax-drawer-race.md): `messenger-drawers.js` agora ignora respostas de requests cancelados/superados em Avisos e Mensagens, evita erro obsoleto e encerra `aria-busy` apenas para a requisição ativa. Correção de código commitada; QA em navegador pendente. Próximo B4-03/B5: gates Runtime de overlays e catálogo visual compartilhado, mantendo Institution Bar + Domain Header.

## THEME-UX 0.8-B5 — catálogo de overlays e ARIA (2026-10-22)

Os três drawers de utilidade (Avisos, Mensagens, Carrinho) agora declaram `aria-expanded` e o estado de seus triggers é sincronizado com eventos Bootstrap pelo JS global. Ver [catálogo B5](theme-ux-0.8-b5-overlay-catalog.md). O painel Minha Conta continua usando navegação AJAX própria, não offcanvas. Testes Runtime e foco/backdrop continuam pendentes; preservar Institution Bar + Domain Header. Próximo B5-02.

## Atualização B1 — Context Header detectado na branch (2026-10-22)

O componente `aculta420:context-header` **já existe** com schema SDC, Twig, CSS e stories `default`/`editorial`. A próxima atividade **não é criá-lo outra vez**, mas integrar dois consumidores reais e testar ownership de H1, render arrays, UI Patterns/Styles e preservação do shell multidomínio. Ver [plano atualizado de B1](theme-ux-0.8-m2-b1-context-header.md). Não declarar implantação Runtime concluída.

## THEME-UX 0.8 — Continuação a partir da branch atual

A inspeção de 2026-10-22 confirmou que `context-header` e `context-tabs` já existem na branch. Portanto não repetir a implementação B1/B2: avançar para [B5-02](theme-ux-0.8-b5-02-drawer-runtime-gates.md), auditoria de interações das gavetas globais com UI Patterns/Styles e preservação do shell. Depois, B6 consolidará os componentes transversais. Fase **planejada**; Runtime pendente.

## THEME-UX 0.8-B6 — Consolidação transversal SDC/UI Suite

A fotografia atual da branch aponta **41 metadados SDC e 65 stories** (substitui a fotografia histórica 38/58). Iniciada a [consolidação B6](theme-ux-0.8-b6-consolidation.md): revisão dos contratos de context-header, context-tabs, empty-state, messenger-state, cartões e overlays, mais matriz de UI Patterns/Styles. A fase B5-02 e a homologação de B6 continuam **PENDENTES no Runtime**; nenhum gate foi aprovado nesta atualização.

## Dependências Studio — replanejamento 2026-10-22

O Studio passou a priorizar **GANJALAB E-0.1**, interface editorial sob `conta.aculta.*/studio/ganjalab`. O ACULTA420 responde exclusivamente pelos componentes Core SDC/UI Suite, tokens, reflow mobile-first e coerência light/dark. Usar `context-header`, `context-tabs`, `card`, `content-grid` e `empty-state` existentes como candidatos ao reuso, **sem declarar a integração homologada**. Os gates G420-C2-T (LMS estudante) estão condicionados a fixtures e desenvolvimento paralelo de LMS/tema; não criar componentes exclusivos GanjaLab420 para substituir contratos genéricos. Para editor GanjaLab E-D: auditar slots/contexto, estados de Form API, 320–1440px, teclado/foco/no-JS, preservar o cabeçalho multidomínio e mesma geometria claro/escuro. Interface Studio orquestrada no módulo; aparência e stories no tema. Ver `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`.

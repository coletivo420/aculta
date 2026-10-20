# Matriz de testes por fase

## dev.79 — leitura por tópico após mover comentário

- Kernel MariaDB usa History oficial e usuário autenticado de fixture. Uma
  resposta publicada conta como nova na origem antes do movimento; depois conta
  como nova no destino. As duas linhas de leitura `uid/nid/timestamp` continuam
  iguais, provando que o movimento não edita recibos pessoais.
- `ForumCommentMoverKernelTest`: 14 testes / 292 assertions PASS; 19
  depreciações preexistentes Core/contrib observadas. Execução em PHP CLI
  8.4.26, inferior à baseline 8.5. Runtime não foi alterado. RUNTIME STATUS:
  DEFERRED.

## dev.78 — preservar Flag de denúncia e fila ACULTA User

- Smoke X11 autenticado moveu uma árvore de quatro Comments e verificou que a
  Flagging `sinalizar_comentario` permaneceu no mesmo CID, com owner uid 5; a
  fila `aculta_user.report_queue` continuou listando o comentário denunciado.
  Os estados pendente/rejeitado, `pid`, `thread` e destino também permaneceram
  corretos. 10/10 verificações em 390×844, 768×1024 e 1024×768 CSS px.
- A configuração ativa da Flag foi previamente comparada ao Config Sync e
  alinhada por importação parcial de um único objeto, depois de backup privado.
  Antes da criação e após cleanup, a contagem de Flagging era zero. Cleanup
  excluiu a Flagging de fixture antes dos Comments e tópicos; Fórum vazio, fila
  Messenger 0, 12 categorias, 3 avisos e config temporária ausente.
- Capturas e `results.json` privados em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-flags-dev78-20261020/`.
  O smoke não alterou o PHP do Runtime.
- `drush cr` não foi executado no final: sua falha reproduzível aponta para o
  mapper `aculta_mailer_template`, que solicita a rota ainda não registrada
  `entity.aculta_mailer_template.edit_form` durante edição paralela do Mailer.
  O roteiro evita esse rebuild global; validação completa de cache permanece
  DEFERRED. RUNTIME STATUS: PARTIAL.

## dev.77 — moderação pendente/rejeitada em subárvore

- A fixture mantém quatro Comments não publicados em árvore ramificada: raiz
  pendente, resposta direta com `field_aculta_rejected = TRUE`, resposta
  aninhada pendente e segundo ramo pendente. Move via Form API/CSRF autenticado
  e confere CID, autor, texto, status, campo de rejeição, `pid`, `thread` e
  tópico destino.
- 10/10 PASS em 390×844, 768×1024 e 1024×768 CSS px, sem overflow. O campo
  rejeitado continua reservado a quem tem permissão de moderação. Capturas
  privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-mixed-dev77-20261020/`.
- Cleanup reverso: zero tópicos/Comments, fila Messenger 0, 12 categorias, 3
  avisos e configuração de mover ausente. Nenhum Comment publicado foi criado;
  aprovação pelo workflow e efeitos de `aculta_game`/aviso para conteúdo
  publicado seguem sem validação. RUNTIME STATUS: PARTIAL.

## dev.76 — isolamento dos efeitos de publicação das fixtures

- Leitura via Drupal API encontrou 24 itens na fila
  `aculta_messenger_message_subscribe_event`. Os 24 tinham como alvo Nodes que
  já não existiam; nenhuma entidade Fórum estava presente. O worker Core
  processou exatamente 24 itens, sem alterar avisos.
- O roteiro X11 exige fila vazia antes de criar fixtures, marca os Nodes de QA
  como sincronizando apenas durante o insert e compara a fila ao baseline após
  remover comentários/tópicos. Esse marcador evita o evento de anúncio de tópico
  e avaliação de conquistas em dados sintéticos; o teste do mover segue sendo
  uma submissão real autenticada do Form API.
- Repetição de árvore ramificada: 10/10 PASS. Estado final verificado via
  Drush/Entity API: fila 0, tópicos/Comments ACULTA e legados 0, 12 categorias,
  3 avisos e `aculta_forum.settings` ausente. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-branch-dev76-20261020/`.
  RUNTIME STATUS: PARTIAL.

## dev.75 — mover subárvore ramificada em Runtime/X11

- O roteiro cria dois tópicos e quatro Comments pendentes: raiz, duas respostas
  diretas e uma resposta aninhada no primeiro ramo. Autenticado como uid 1,
  envia o Form API com CSRF e verifica CIDs, autores, textos, status, relações
  `pid` e posições `thread`; origem termina com zero e destino com quatro.
- Firefox ESR 153.4/geckodriver 0.37.1: 10/10 PASS; formulário/fila em
  390×844, 768×1024 e 1024×768 CSS px, sem overflow horizontal. Capturas
  privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-branch-dev75-20261020/`.
- Cleanup em ordem reversa removeu os quatro Comments e os dois Nodes e apagou a
  configuração temporária. Estado final: 0 tópicos/Comments ACULTA/legados,
  12 categorias, 3 avisos e settings do mover ausentes. RUNTIME STATUS: PARTIAL.
- Limites: todas as respostas estavam pendentes. Não cobre status mistos,
  History, flags, Search API, anexos `aculta_image`, aviso com seguidores ou
  concorrência HTTP. Unit/Kernel permanecem conforme os resultados anteriores;
  esta subfase mudou apenas roteiro e documentação, não o PHP do Runtime.

## dev.74 — mover subárvore em Runtime/X11

- O roteiro cria duas discussões de teste e três Comments pendentes (raiz,
  resposta e resposta aninhada), autentica uid 1, confirma o token CSRF e move a
  árvore pelo Form API. Todos os CIDs, autores, textos, estados, `pid`,
  prefixos de `thread` e destino foram conferidos; origem ficou com zero e
  destino com três Comments.
- Firefox ESR 153.4/geckodriver 0.37.1: 10/10 PASS. Formulário e fila foram
  verificados em 390×844, 768×1024 e 1024×768 CSS px; sem overflow horizontal.
  Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-subtree-dev74-20261020/`.
- Cleanup em ordem reversa removeu os três Comments e os dois Nodes; configuração
  temporária foi apagada. Estado final: 0 tópicos/Comments ACULTA ou legados,
  12 categorias, 3 avisos e settings do mover ausentes. Runtime continua
  fail-closed. RUNTIME STATUS: PARTIAL.
- Limites: árvore linear com três itens, todos pendentes. Não cobre ramificação,
  estados mistos, History, flags, Search API, imagens `aculta_image`, avisos com
  seguidores ou concorrência HTTP. A suíte Unit/Kernel registrada na dev.73
  continua sendo a cobertura transacional automatizada; esta subfase não alterou
  PHP de Runtime.

## dev.73 — formulário real para mover comentário

- Pré-condições Runtime confirmadas: `hostname=toca`, branch da PR #297, Drupal
  11.4.10, URI MAIN derivado do Deployer e banco `aculta_runtime`. Antes do
  teste havia 0 tópicos ACULTA/legados, 0 Comments ACULTA/legados, 0 Comments
  no campo compartilhado, 12 categorias e 3 avisos. O gate de mutação exige
  variável explícita, X11 e diretório de saída privado fora do checkout.
- O Runtime não tinha as instâncias `field_autor_legado` nos bundles de Node e
  Comment ACULTA, embora os storage configs e o tipo `aculta_legado_autor`
  existissem. Os dois field configs ausentes foram comparados ao Config Sync e
  instalados por importação parcial limitada a esses arquivos, após registro do
  estado anterior; config status dos alvos ficou sem drift.
- O roteiro ativou temporariamente `comment_move_enabled`, criou 2 tópicos e 1
  Comment pendente de teste, autenticou uid 1 via ULI, abriu o formulário em
  MAIN, conferiu o token CSRF e submeteu a confirmação Form API. 10/10 PASS em
  390, 768 e 1024 CSS px, sem overflow horizontal:
  formulário/destino, singular “1 comentário”, POST sem erro, CID/autor/texto/
  estado pendente/thread preservados e host alterado ao destino (origem 0,
  destino 1 antes da limpeza).
- PHPUnit Unit completo: 82/500 PASS. Kernel `ForumCommentMoverKernelTest`:
  13/272 PASS no MariaDB `aculta_simpletest`; 19 depreciações de Core/contrib.
  PHPCS Drupal/DrupalPractice e gates Portal 975, fronteira 1419 e mapa 152 PASS.
  PHP CLI 8.4.26 está abaixo da baseline 8.5.
- Capturas privadas do formulário/resultado em
  `/home/piradopirata/private/aculta/visual-tests/forum-comment-move-dev73-20261020/`.
  O `finally` removeu o Comment e os dois Nodes via Entity API, apagou o config
  temporário, invalidou caches e reconferiu o estado: 0 tópicos/Comments
  ACULTA/legados, 12 categorias, 3 avisos, instâncias legadas alinhadas e
  `aculta_forum.settings` ausente (fail-closed). RUNTIME STATUS: PARTIAL.
- Cobertura desta rodada é só a raiz de um Comment pendente; não cobre subárvore
  real, aprovação, flags/History, Search API, arquivos processados pelo
  `aculta_image`, entrega de notificação ou concorrência entre requests.

## dev.72 — PHPCS do contrato de contexto

- PHPUnit Unit completo do Fórum: 82 testes / 500 assertions PASS.
- PHPCS Drupal/DrupalPractice PASS em `src/Context`, o provider público e os
  testes unitários do resolver/provider. `php -l` nos arquivos de contexto
  alterados e `git diff --check` PASS.
- Ajustes limitados a docblocks, posição de `use` e remoção da variável
  ignorada no fixture de mocks; Runtime DEFERRED.

## dev.71 — fila administrativa responsiva

- A suíte X11 abre a fila aguardando moderação em viewports CSS de 390×844 e
  768×1024, valida o carregamento e mede overflow do documento e da área `<main>`;
  também salva screenshots com permissões privadas.
- Firefox ESR 153.4/geckodriver 0.37.1 no X11 autenticado: 19/19 verificações
  PASS, inclusive carregamento das abas e rótulos. Em 768 px não houve overflow;
  em 390 px a tabela responsiva estende-se 3 px e os elementos excedentes ficam
  restritos a `table/thead/tr/th/tbody/td`. A captura foi inspecionada; nenhum
  conteúdo/configuração foi alterado. Filas vazias limitam revisão de linhas
  reais em telas estreitas. Evidências privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-admin-dev71-20261020/`.
  RUNTIME STATUS: PARTIAL.

## dev.70 — rótulos pt-BR das filas de moderação

- A configuração de instalação e Config Sync deve ter colunas `changed` e
  `operations` como “Atualizado” e “Operações”, e texto vazio “Não há comentários
  disponíveis.”; Unit verifica os valores e a paridade.
- `admin-content-structure.mjs`: Firefox ESR 153.4/geckodriver 0.37.1 em X11,
  autenticado no MAIN de teste; 15/15 verificações PASS nas quatro filas. Confirma
  “Atualizado”, “Operações” e “Não há comentários disponíveis.”. Capturas
  privadas em `/home/piradopirata/private/aculta/visual-tests/forum-admin-dev70-20261020/`.
- View ativa atualizada após backup privado e alinhada ao Config Sync. Fórum
  permanece com 0 tópicos e 0 Comments; filas sem linhas não comprovam
  isolamento cruzado com dados reais. RUNTIME STATUS: PARTIAL.

## dev.69 — smoke X11 de organização administrativa

- `node tests/browser/aculta-forum/admin-content-structure.mjs` gera link ULI
  temporário para uid 1 no host MAIN de teste (resolvido pelo Deployer), abre
  Firefox visível e navega somente por GET. Não salva conteúdo/configuração.
- Verifica Conteúdo geral, entrada Fórum, abas publicados/rejeitados/aguardando
  moderação, Conteúdo da Revista unificado e 404 do caminho Revista legado;
  grava capturas `0600` e `results.json` fora do checkout.
- Preflight: `hostname=toca`, branch da PR #297, Drupal 11.4.10, URI MAIN de
  teste, banco `aculta_runtime`; zero tópicos ACULTA Forum e zero Comments do
  campo compartilhado. Firefox ESR 153.4/geckodriver 0.37.1 em X11, uid 1
  autenticado com ULI temporário pelo host ACCOUNT e sessão confirmada no MAIN.
- 11/11 PASS: Conteúdo geral/aba Fórum 200; links de publicados/rejeitados/
  aguardando presentes; `/painel-administrativo/conteudo/forum/comentarios` e
  as três filas de estado 200; Revista consolidada 200; rota antiga de
  comentários da Revista 404. Capturas `0600` fora do checkout em
  `/home/piradopirata/private/aculta/visual-tests/forum-admin-dev69-20261020/`.
- Somente GETs e link temporário; nenhum Node/Comment foi criado. Como os dados
  do Fórum estão vazios, não foi possível confirmar exclusão de conteúdo cruzado
  nas linhas de resultado. RUNTIME STATUS: PARTIAL.

## dev.68 — teclado e estados de limite

- A suíte usa WebDriver Actions para enviar Enter à ação Próxima e ArrowRight ao
  slider, além de conferir o estado inicial, `aria-valuetext`, live announcement
  e retorno Início/Fim. A interação de teclado usa eventos do navegador, não
  `dispatchEvent` sintético para as teclas.
- Firefox ESR 153.4/geckodriver 0.37.1, Runtime `toca`: Enter enviou o foco a
  Próxima e anunciou “Mensagem 2 de 6”; ArrowRight no slider alterou o índice e
  `aria-valuetext` para “Mensagem 3 de 6”, com live region `off`; Fim/Início
  atualizaram o índice e disabled dos botões de borda. A suíte completa passou
  25/25 em 320/360/390/500/900 CSS px. Sem gravação. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-progress-dev68-20261020/`.
- O resultado de overflow global permanece limitado pelo baseline do offcanvas
  de carrinho descrito em dev.67; esta entrega não o altera. Leitor de tela e
  History real autenticado continuam pendentes. RUNTIME STATUS: PARTIAL.

## dev.67 — suíte visual X11 reproduzível

- `node tests/browser/aculta-forum/progress-visual.mjs` usa Firefox não headless
  em uma sessão X11, URL pública `SUITE_PAGE` e saída privada `SUITE_OUT` fora
  do checkout. Host é comparado aos hosts de teste do Deployer. O WebDriver
  BiDi ajusta o viewport CSS, independente do tamanho mínimo da janela X11; essa
  capacidade está na [especificação W3C WebDriver BiDi](https://www.w3.org/TR/webdriver-bidi/)
  e no [MDN browsingContext.setViewport](https://developer.mozilla.org/en-US/docs/Web/WebDriver/Reference/BiDi/Modules/browsingContext/setViewport).
- O cenário cria seis mensagens exclusivamente no DOM dentro do campo de
  discussão já renderizado; verifica controles, ausência/presença de `.new`,
  anúncio “Mensagem 4 de 6”, remoção do marcador, viewport, overflow, alvos de
  toque e capturas claro/escuro. Não autentica nem envia formulários.
- Firefox ESR 153.4/geckodriver 0.37.1 no Runtime `toca`, página pública de
  publicação: 20/20 verificações PASS em 320/360/390/500/900 CSS px; `.new`,
  anúncio, alvo móvel de 44 px e claro/escuro confirmados. Capturas privadas em
  `/home/piradopirata/private/aculta/visual-tests/forum-progress-dev67-20261020/`.
- O DOM/body da página base já mede 509 px entre viewport 320–500 px; inspeção
  identificou o `#aculta-cart-offcanvas` fixo e fechado além do limite. Os
  controles do Fórum não ampliam essa largura. É um achado preexistente do
  carrinho, fora do escopo deste commit; portanto o teste não afirma ausência de
  overflow global. Nenhum dado foi salvo. History autenticado real e leitor de
  tela permanecem pendentes. RUNTIME STATUS: PARTIAL.

## dev.66 — primeira resposta não lida pelo History instalado

- O Runtime carrega `drupal/history` 1.0.0 de `web/modules/contrib/history`.
  O `comment-new-indicator.js` instalado acrescenta classe `.new` ao `.js-comment`
  depois de comparar o timestamp com o History do usuário. A documentação Core
  registra que o marcador é personalizado no cliente e que o History JS API
  coordena esses indicadores; fontes: [HistoryHooks::commentView](https://api.drupal.org/api/drupal/core%21modules%21history%21src%21Hook%21HistoryHooks.php/function/HistoryHooks%3A%3AcommentView/11.x)
  e [New History JS API](https://www.drupal.org/node/2086767).
- Firefox ESR 153.4/X11/BiDi no Runtime de teste, sessão anônima isolada e página
  da Revista. Seis mensagens sintéticas somente no DOM: sem `.new`, o botão
  ficou oculto; adicionar `.new` ao quarto comentário mostrou “Não lidas”; clicar
  navegou e anunciou “Mensagem 4 de 6” em `aria-live=polite`; remover a classe
  ocultou novamente o botão. Capturas em
  `/home/aculta/private/aculta/visual-tests/forum-progress-20261020/`.
- 320/360/390/500/1250 CSS px: a barra manteve largura do viewport, slider amplo,
  status visível no mobile e alvo de ação de 44 px; o botão não quebrou no
  desktop. Claro/escuro inspecionados. Nenhum Node/Comment foi gravado. Não houve
  usuário autenticado nem marcador History de comentário real; essa homologação,
  integração de leitor de tela e AT continuam pendentes. RUNTIME STATUS: PARTIAL.

## dev.65 — anúncio explícito durante movimento de leitura

- Firefox ESR 153.4/X11 e WebDriver BiDi no Runtime de teste, sessão anônima
  isolada. Seis mensagens foram inseridas apenas no DOM da publicação e o
  viewport CSS foi 390 × 844 px. A navegação chegou visualmente à mensagem 3/6
  antes do teste de Enter, por efeito esperado do IntersectionObserver.
- Enter em Próxima pediu a mensagem 4/6. Após 250 ms, o indicador visual já
  acompanhava a rolagem em 6/6, mas o texto de anúncio permaneceu estável em
  4/6 com `aria-live=polite`. Após 1,2 s, o anúncio voltou a `off` e sincronizou
  silenciosamente em 6/6. Isso evita cancelar o anúncio explícito sem narrar
  cada mensagem atravessada pela rolagem suave.
- DOM sintético removido e sessão isolada encerrada; nenhum Comment persistido.
  `node --check` PASS. Leitor de tela real/AT não testado. RUNTIME STATUS: PARTIAL.

## dev.64 — anúncio acessível sem ruído de rolagem

- A região visual e a mensagem dentro de `role=status` seguem a posição. A live
  region alterna para `aria-live=off` em atualizações do IntersectionObserver e
  do slider, e para `polite` em ações de botão. `aria-valuetext` acompanha o
  slider. CSS do tema oculta visualmente a região (1×1 px, `clip-path`) sem
  classes de design no módulo.
- Firefox ESR 153.4/Geckodriver no Runtime `toca`, URL pública de publicação
  com `field_aculta_discussion`: seis Comments foram acrescentados apenas no
  DOM. Próxima definiu `polite` e anunciou “Mensagem 2 de 6”; rolar até a quinta
  atualizou texto visível/acessível e `aria-valuetext` para 5/6, em `off`;
  operar o slider atualizou para 3/6, ainda em `off`; Próxima definiu `polite`
  e anunciou 4/6. O anúncio mediu 1×1 px e `clip-path: inset(50%)`. Fixtures
  DOM removidas e sessão isolada fechada; nenhum dado foi salvo.
- BiDi `browsingContext.setViewport` confirmou 390 × 844 CSS px: a janela mediu
  390 px, documento/corpo 378 px, navegação `static`, status 5/6 e região em
  `aria-live=off`; sem overflow horizontal.
  Revalidação Firefox ESR 153.4/X11 no Runtime de teste, sessão anônima
  isolada: seis mensagens sintéticas foram inseridas apenas no DOM da publicação;
  nada foi gravado. BiDi aplicou viewport CSS exato em 320/360/390/500/1250 px.
  Após corrigir rótulos comprimidos em 320 px e avanço lateral em 390 px no tema
  0.6.0-dev.114, e dar ao slider largura própria e contador visível em 0.6.0-dev.115,
  barra e controles ficaram no container; documento e corpo mantiveram a largura
  do viewport. Claro/escuro inspecionados; botão anunciou
  “Mensagem 3 de 6”, slider mostrou “Mensagem 5 de 6” com `aria-live=off` e
  `aria-valuetext` sincronizado. Capturas privadas em
  `/home/aculta/private/aculta/visual-tests/forum-progress-20261020/`.
- Forum Unit 82/497, `node --check`, Portal Drupal 11+ 975, fronteira 1419,
  mapa 152, UI docs 280 tokens/97 atributos, SDC 535, Design Foundations 2097
  e Foundation Runtime 366 PASS. `drush cr` PASS. PHP CLI 8.4.26 abaixo da
  baseline 8.5. Leitor de tela real/AT não foi testado. RUNTIME STATUS: PARTIAL.
- Base normativa consultada: [W3C ARIA22](https://www.w3.org/WAI/WCAG22/Techniques/aria/ARIA22.html)
  reconhece `role=status` como live region `polite`; [MDN aria-live](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-live)
  descreve anúncio de mudanças e priorização não intrusiva.

## dev.63 — navegação de progresso compartilhada

- `DiscussionProgressHooksTest`: verifica anexação do atributo e biblioteca
  somente ao campo em renderização completa, preservando teaser, campo ausente,
  campo explicitamente oculto e entidades que não são Nodes.
- A biblioteca consulta apenas `article.js-comment[id]` dentro do campo já
  renderizado; os controles são criados com DOM APIs e texto traduzível, sem
  HTML de usuário. Sem biblioteca, a navegação linear permanece disponível.
- Unit do hook 3/13 e suíte Forum 82/497 PASS; PHPCS e `node --check` PASS.
  Drupal 11+ 975, fronteira 1419, mapa 152, atributos 280 tokens/96
  atributos, SDC 535, Design Foundations 2097, Foundation 365 e Shell 11 PASS.
- Runtime `toca`: Drush status confirmou Drupal 11.4.10/DB `aculta_runtime`;
  cache rebuild PASS. A publicação pública retornou 200, recebeu o marcador e
  o JS agregado contém o behavior.
- Firefox ESR 153.4/X11, sessão anônima isolada: seis Comments sintéticos foram
  inseridos somente no DOM, dentro do campo real da publicação. Quatro
  controles apareceram; Avançar e ArrowRight no slider atualizaram para
  “Mensagem 2 de 6 nesta página”. Claro e escuro foram inspecionados (modo
  escuro ativado só no DOM); viewport 500 px mediu 500 px no documento/corpo,
  sem overflow. Capturas privadas em
  `/home/aculta/private/aculta/visual-tests/forum-progress-20261020/`.
- Não foram criados ou salvos Nodes/Comments. Claro/escuro e teclado do slider
  PASS nas larguras X11 1250/500; 390 px, Comments reais, navegação completa por
  teclado e leitor de tela/AT ficam pendentes.
- Rolagem manual inicialmente mostrou a barra sticky parcialmente sob o
  cabeçalho. Após tema dev.111, Firefox/X11 escuro a 500 px confirmou a barra
  completa com topo 56 px (12 px após o cabeçalho de 44 px), status 4/6 pela
  rolagem e sem overflow; captura `progress-500-dark.png`.
- WebDriver BiDi aplicou viewport CSS exato de 390 × 844. Antes da correção, a
  barra tinha 306 px no campo de 330 px, documento/corpo em 390 px e cobria texto
  ao rolar. Tema dev.112 foi revalidado: `position: static`, status 5/6 pela
  rolagem, nenhuma interseção com a mensagem ativa, documento/corpo em 390 px.
  Captura privada `progress-390.png`.
  Runtime global PARTIAL.

### Revalidação dos movers no Runtime de teste — 2026-10-20

- `ForumCommentMoverKernelTest` e `ForumTopicMoverKernelTest`: execução conjunta
  PASS, 16 testes/341 assertions em `aculta_simpletest`; 19 depreciações de
  Core/contrib. Forum Unit: 82 testes/497 assertions PASS.
- Portal Drupal 11+ 975, fronteira 1419, mapa módulo/tema 152 e ACULTA420
  Design Foundations 2097 PASS. PHP CLI 8.4.26 está abaixo da baseline 8.5.
- Preflight confirmou `hostname=toca`, branch da PR #297 e Drush no Drupal
  11.4.10, banco `aculta_runtime`, URI
  `https://growroom420.aculta.toca.net.br`. A rota de movimento está
  registrada e exige `move aculta forum comments`; a configuração está ausente
  (`NULL`) e permanece fail-closed. GET anônimo com Comment inexistente: 404;
  raiz do Fórum: 200; consultas Entity API read-only: 0 tópicos e 0 Comments.
- Nenhuma conta foi autenticada, opção alterada, entidade criada ou conteúdo
  salvo no Runtime. A validação de navegação autenticada/CSRF e a execução do
  formulário com Comment real continuam pendentes; não habilitar a função até
  essa homologação e a revisão dos efeitos de History, permalink e avisos.
  RUNTIME STATUS: PARTIAL.

## dev.62 — filas pendente/rejeitada do Fórum

- A consulta da View usava `getTableInfo('node_field_data')`, mas o Core 11.4
  indexa a tabela do relacionamento como `node_field_data_comment_field_data`.
  O hook fail-closed acrescentava `1 = 0`, ocultando também comentários válidos.
  O código agora resolve o alias no relacionamento `node` já construído pela
  View e confirma que aponta para `node_field_data`; ausência ou divergência
  ainda fecha a fila.
- PHPUnit Unit direcionado: `ForumHistoricModerationQueueHooksTest`, 5 testes /
  18 assertions PASS; suíte Unit do Fórum, 79 testes/484 assertions PASS.
  PHPUnit Kernel `PublicDiscussionModerationKernelTest`,
  7 testes / 84 assertions PASS. PHP CLI 8.4.26, abaixo da baseline 8.5.
- Runtime `toca`: View `page_pending` retornou apenas o Comment 56, associado
  ao tópico temporário do Fórum; o Comment 57 da publicação não apareceu. Os
  dois Comments estavam pendentes. Nodes 425/426 e Comments 56/57 foram
  removidos via Entity API no `finally` da execução. Estado final: 0 tópicos
  públicos e 0 Comments compartilhados, preservando os dados baseline.
- A conta `teste.comum` não prova a política de primeiro comentário: seu papel
  `content_editor` a isenta. A política foi validada pelo Kernel; nenhuma conta
  real foi usada como fixture. A execução da rota HTTP autenticada e a inspeção
  visual das três abas permanecem pendentes. RUNTIME STATUS: PASS para a query
  e isolamento; PARTIAL para a homologação completa.

### Complemento visual X11 da thread — referência Ubuntu Discourse

- Firefox ESR X11 comparou uma discussão ACULTA com a categoria e um tópico
  público do Ubuntu Discourse. O alias `/topico/qa-visual-inspecao-discussao`
  respondeu 200; a captura da thread mediu 1270 px de viewport, e outra janela
  estreita mediu 500 px, com `documentWidth` e `bodyWidth` iguais a 500 e a
  resposta ainda visível. Nenhum overflow horizontal foi observado.
- A página ACULTA mostra título e autoria/categoria do tópico, seguidos por
  cada comentário em painel com autor, corpo e permalink. No exemplo Discourse,
  o autor também ocupa uma coluna lateral, com timeline vertical, posição do
  leitor na discussão e navegação contextual persistente. A inspeção ACULTA
  sugere priorizar timeline/progresso e navegação de thread; ações de resposta
  autenticada não foram avaliadas neste fluxo anônimo.
- Fixture NID 431/CID 61 e a repetição NID 432/CID 62 foram removidas via
  Entity API. As respostas publicadas usaram temporariamente `setSyncing(TRUE)`
  para que o ACULTA Game não concedesse uma conquista de teste irreversível;
  esse mecanismo não foi usado para validar a política de moderação. O teste
  anônimo pendente também foi removido. Estado final do Runtime: 0 tópicos e 0
  Comments compartilhados, 24 History (baseline) e 2 flaggings globais
  preservadas. Capturas privadas em
  `/home/aculta/private/aculta/visual-tests/forum-thread-20261020/`.
- A comparação ainda não cobre thread extensa, ação autenticada, leitor de tela
  nem largura CSS exata de 390 px nessa página. RUNTIME STATUS: PARTIAL.

## dev.61 — Batch Core no purpose Fórum

- O endpoint progressivo Batch do Core permanece no purpose `main`, conforme a
  política central de rotas administrativas. O callback `markBatch()` executa
  a query de acesso e gravação do History com `runInPurpose('forum')`, após
  validar a identidade autenticada capturada no formulário. O formulário
  retorna à raiz pública absoluta do purpose Fórum.
- PHPUnit Unit passou 78 testes/481 assertions; Kernel passou 1 teste/12
  assertions. O Kernel inicia em `main`, confirma
  elegibilidade zero fora do Fórum, conta 1 dentro do Fórum, executa o callback
  enquanto o purpose inicial é `main`, e confirma restauração do contexto.
- X11 Firefox autenticado como `teste.comum` confirmou a tela de confirmação, o
  Batch processando e a mensagem “Foram marcadas 1 discussões como lidas.”; a
  navegação retornou à raiz Growroom420. History só continha uid 5/NID 417 para
  o fixture durante a prova. Depois, Node/alias e History da fixture foram
  removidos; Runtime terminou com 0 tópicos, 0 Comments compartilhados, 24
  History (baseline) e duas flaggings globais preservadas. Raiz e categorias
  retornaram 200. Captura privada `result-final-dev61.png` em
  `/home/aculta/private/aculta/visual-tests/forum-read-batch-20261020/`.
- Cinco deprecações no Kernel vieram do Domain contrib instalado. Execução em
  PHP CLI 8.4.26 (baseline do projeto 8.5). PHPCS, Portal 975, fronteira 1415 e
  mapa 152 PASS. RUNTIME STATUS: PASS no escopo.

## dev.60 — descoberta visual no X11

### Complemento visual mobile e modo escuro — 2026-10-20

- Firefox ESR no X11 Responsive Design Mode confirmou viewport CSS real de
  390 × 670 px. Raiz, `/discussoes/novas`, `/discussoes/sem-resposta` e
  `/discussoes/mais-comentadas` retornaram HTTP 200; `document.documentElement`
  e `body` mediram 390 px de largura em todas. Um tópico temporário apareceu
  nas listas esperadas. O filtro e as tabs quebraram em linhas; título, autor e
  categoria permaneceram legíveis sem overflow horizontal.
- `data-bs-theme="dark"` foi aplicado somente ao DOM local do teste: fundo
  `rgb(23, 21, 19)`, texto `rgb(244, 239, 232)`, largura 390 px. Nenhuma
  preferência foi salva na conta nem no Runtime.
- Comparação no mesmo viewport com Ubuntu Discourse `Support and Help`
  (`/c/support-and-help/306`): header compacto, navegação por menu, chips de
  categoria em múltiplas linhas e contagem na linha do tópico. ACULTA mantém o
  header institucional, filtro select, tabs em duas linhas e card com autoria e
  categoria. Inspeção visual não indicou corte ou overflow; diferenças de
  hierarquia e densidade ficam para refinamento posterior. O diálogo de cookies
  foi fechado no DOM local da referência sem registrar consentimento.
- Fixture exata NID 418/UUID `7438e2b4-9fa5-4b65-a532-7588a08a5456` e alias
  removidos via Entity API após conferir proprietário, bundle, categoria e
  purpose. Estado final: 0 tópicos, 0 Comments compartilhados, 24 History e
  duas flaggings preexistentes. Capturas privadas mode 0600 em
  `/home/aculta/private/aculta/visual-tests/forum-mobile-20261020/`.
- Navegação real por teclado em Firefox/X11 percorreu 16 tabs, partindo do link
  “Pular para o conteúdo principal” até um link de categoria visível. O
  elemento final manteve `:focus-visible` e contorno sólido de 2 px verde; a
  navegação avançou sem ficar presa. Leitor de tela e comparação integral das
  páginas internas do Discourse continuam pendentes. RUNTIME STATUS: PARTIAL.

- Firefox ESR isolado, visitante anônimo, X11 `DISPLAY=:0`, Runtime Drupal
  11.4.10. As rotas `/discussoes/novas`, `/discussoes/sem-resposta` e
  `/discussoes/mais-comentadas` responderam HTTP 200.
- Três tópicos QA (`aculta_forum_topic`) e cinco Comments publicados tinham
  contagens 0/2/3. Render/View e HTML confirmaram ordem decrescente por criação
  em “novas” (3 → 2 → 0), filtro exclusivo de zero respostas em “sem resposta”
  e ordem por quantidade em “mais comentadas” (3 → 2 → 0). Não houve abertura
  dos detalhes, evitando novas linhas History.
- Em 1024 × 739 px, cards mostraram título, autor, categoria, contagem e última
  atividade. A janela visual estreita atingiu 500 px (mínimo disponível nessa
  janela; não é aferição CSS exata de 390 px): tabs quebraram em duas linhas e
  os controles/card permaneceram visíveis sem corte aparente. Capturas privadas
  (modo 0600): `/home/aculta/private/aculta/visual-tests/forum-discovery-20261020/`
  (`novas-1024.png`, `sem-resposta-1024.png`, `mais-comentadas-1024.png`,
  `mais-comentadas-500.png`).
- Cleanup confirmou os três NIDs/UUIDs e os cinco Comments, removeu comentários
  e Nodes via Entity API e aliases/History relacionados. Cache rebuild PASS;
  estado final 0 tópicos, 0 Comments, 24 History (baseline) e duas flaggings de
  avisos preservadas. Todas as três rotas seguem 200 vazias. Modo escuro,
  acessibilidade e comparação completa de referência não executados.
  RUNTIME STATUS: PARTIAL.

## dev.59 — leitura em lote com limpeza exata

- Runtime `toca`, Drupal 11.4.10, banco `aculta_runtime`: preflight confirmou
  0 tópicos e 0 Comments compartilhados. Fixture NID 410, autor uid 5 e domínio
  `forum_aculta_org`, foi a única discussão elegível; `countEligibleTopics()`
  retornou 1. `ForumReadMarker::markBatch()` gravou History para o par exato
  uid 5/NID 410. O registro, tópico e alias foram removidos após confirmar a
  identidade da fixture.
- Cache rebuild PASS; HTTP da raiz e `/categorias` 200. Estado final:
  0 tópicos, 0 Comments compartilhados, 24 History globais (baseline antes do
  teste) e as 2 flaggings existentes de avisos de site preservadas. A rota de
  confirmação e a execução do Core Batch pelo navegador permanecem pendentes.
  RUNTIME STATUS: PARTIAL.

## dev.58 — ocultar ações Flag nos cartões teaser

- `ForumTopicDisplayConfigTest`: 18 testes/224 assertions PASS. PHPCS
  Drupal/DrupalPractice, Portal 11+ (975), fronteira módulo/tema (1415) e mapa
  (152) PASS. PHPUnit executou em PHP CLI 8.4.26, abaixo da baseline 8.5; o
  aviso preexistente de `sites/simpletest/browser_output` não gravável persiste.
- Runtime `toca`, Drupal 11.4.10, banco `aculta_runtime`: backup privado da
  configuração anterior, importação parcial somente de
  `core.entity_view_display.node.aculta_forum_topic.teaser`, cache rebuild e
  `config:status` do alvo sem drift PASS. Render API sob uid 5 confirmou
  `teaser_follow=no_save=no` e `full_follow=yes_save=yes`.
- Firefox/X11 autenticado: a lista `/discussoes/nao-lidas` deixou de exibir
  seguir/salvar no teaser e preservou “Respostas novas: 2”; o detalhe do mesmo
  tópico manteve os dois controles. A fixture NID 409 tinha exatamente dois
  Comments; a exclusão pela Entity API removeu também alias e History. Estado
  final: 0 tópicos, 0 Comments, 0 flags do Fórum, 0 History da fixture e alias
  retorna 404. A raiz e `/categorias` retornam 200; não autenticado em não
  lidos retorna 403. RUNTIME STATUS: PASS para esta correção.

## dev.57 — ações nativas de seguir/salvar no tópico

- `ForumTopicDisplayConfigTest`: 18/221 PASS; PHPCS Drupal/DrupalPractice,
  igualdade entre Config Sync e `config/install`, Portal 11+ (975), fronteira
  (1415), mapa (152), SDC (535) e Design Foundations (2097) PASS.
- PHPUnit executou em PHP CLI 8.4.26, abaixo da baseline 8.5; aparece o aviso
  preexistente de `sites/simpletest/browser_output` não gravável. A suíte Unit
  não usa esse diretório.
- Runtime `toca`: snapshot recuperável da configuração anterior em
  `/home/aculta/private/aculta/runtime-config-backups/forum-dev57-20261020/`
  (SHA-256 `e51d5b2128c0904c3b4052afc6745a9c2b3f9dd13109398132a33d2f4f132f58`);
  importação parcial processou só `core.entity_view_display.node.aculta_forum_topic.full`,
  cache rebuild PASS e alvo sem diferenças do Config Sync. O render da página
  completa sob uid 5 incluiu ambos os links; `flag.link_builder` gerou âncoras
  para as permissões de seguir/salvar. Comparação de render com uid 5 e
  `AnonymousUserSession` mostrou ambos os controles somente para uid 5, sem
  exposição anônima. Firefox/X11 autenticado em 1024 px confirmou os dois
  controles. Cada ação foi ativada e revertida pela interface; contagens
  persistidas foram `seguir_topico 0 → 1 → 0` e `salvar_topico 0 → 1 → 0`.
  A fixture exata foi removida sem Comments ou flags restantes. Estado final:
  0 tópicos, 0 Comments compartilhados e 0 flags de seguir/salvar; as duas
  flags de avisos de site (read/dismiss) foram preservadas. Firefox Responsive
  Design Mode autenticado a 390 × 670 px mostrou ambos os botões lado a lado,
  sem transbordamento horizontal visível. Modo escuro forçado localmente no DOM
  foi inspecionado em 1024 px e os controles permaneceram legíveis; isso não
  valida a preferência persistida do Runtime. Raiz e `/categorias` HTTP 200.
  RUNTIME STATUS: PARTIAL.

## dev.56 — remover referência residual ao bundle Forum contrib nas Flags

- `ForumTopicDisplayConfigTest`: 17 testes/215 assertions PASS; PHPCS
  Drupal/DrupalPractice e parse dos quatro YAML (Config Sync + `config/install`)
  PASS. O teste garante que `seguir_topico` e `salvar_topico` se aplicam somente
  ao bundle `aculta_forum_topic`.
- Runtime `toca`: Drupal 11.4.10, `aculta_runtime`. Antes da mudança, as duas
  Flags tinham 0 flaggings. Backups privados das duas configs anteriores em
  `/home/aculta/private/aculta/runtime-config-backups/forum-dev56-20261020/`;
  SHA-256 `seguir_topico` `bc23cdd7732261694f8245a891a30c2acc2355cc63b11c5feb85884db412d982`,
  `salvar_topico` `9859233e45071e7e1e94ee70b3584eaf54b68dc0c8d26eb66e2ed8fe9df62e9d`.
  Importação parcial processou apenas os dois objetos; cache rebuild e ausência
  de diferenças dos alvos PASS. Depois, ambas continuam com 0 flaggings; Fórum
  está em 0 tópicos e 0 Comments; raiz e `/categorias` HTTP 200. Nenhum dado foi
  removido. RUNTIME STATUS: PASS para esta limpeza.

## dev.55 — cacheabilidade do texto de citação

- `ForumQuoteHooksTest`: 6/18 PASS; suíte direcionada Builder + Hooks + display
  config: 26/240 PASS. PHPCS Drupal/DrupalPractice PASS. Caso específico
  confirma que contexts, tags e max-age de `TextProcessed` do Core são
  propagados ao formulário.
- Runtime `toca`: cache rebuild e resolução de `ForumQuoteHooks`, `quote_builder`
  e `discussion_context_resolver` PASS. Firefox/X11 conferiu listas em 1024,
  768 e 390 px e detalhe de tópico em 390 px; título, cartão de autor, corpo e
  categoria renderizaram sem overflow horizontal observado. Três tópicos QA
  temporários foram criados via Entity API para as capturas e removidos após
  verificar identidade por NID/UUID/título e ausência de Comments associados.
  Estado final: 0 tópicos, 0 Comments, fila de inscrição Messenger vazia e
  aliases das fixtures removidos. As capturas privadas ficam em
  `/home/aculta/private/aculta/visual-tests/forum-dev53-20261020/`.
- O fluxo autenticado de citação e o formulário de resposta não foram enviados
  no Runtime; seguem cobertos parcialmente por Unit e aguardam sessão QA
  confirmada. RUNTIME STATUS: PARTIAL.

## dev.54 — agrupamento visual de categorias por hierarquia

- A captura X11 1024 px mostrou filhos antes das categorias raiz. Config da
  View agora ordena pelo nome da relação `parent`, depois peso e nome; Unit
  `ForumTopicDisplayConfigTest`: 17/217 PASS. PHPCS, Portal 11+ (975), fronteira
  (1415), mapa (152), pacotes (26), slugs (378), imagem e diff-check PASS.
- Runtime `toca`: cópia privada da configuração anterior em
  `/home/aculta/private/aculta/runtime-config-backups/forum-dev54-20261020/`,
  importação parcial somente da View e cache rebuild PASS. Execução real lista
  4 categorias raiz antes de 8 filhas agrupadas; `/categorias` HTTP 200 e 0
  tópicos/Comments. Nenhum conteúdo criado/alterado. `config:status` ainda
  marca a View como `Different` apenas por ordem de chaves em `display` e em
  `block_landing.display_options`; comparação normalizada entre active e Sync
  confirma os mesmos valores, incluindo o sort novo.
- Firefox/X11: capturas privadas em
  `/home/aculta/private/aculta/visual-tests/forum-dev53-20261020/` para 1024,
  768 e Responsive Design Mode 320 px (`categories-grouped-1024.png`,
  `categories-grouped-768.png`, `categories-responsive-mode.png`); raízes
  primeiro, filhos agrupados e sem overflow horizontal visível. A sessão de
  390 px fora do modo Responsive já está registrada na rodada dev.52. Tópicos e
  sessão autenticada seguem sem fixture.
  RUNTIME STATUS: PARTIAL.

## dev.53 — citação contextual de comentário

- PHPUnit Unit `ForumQuoteBuilderTest` e `ForumQuoteHooksTest`: 9 testes / 18
  assertions PASS. `ForumTopicDisplayConfigTest` executado junto: suíte
  direcionada total 26/233 PASS. PHPCS Drupal/DrupalPractice nos quatro arquivos
  PHP PASS. Cobertura do fluxo: escape e limite de texto, origem inacessível,
  ação ligada à rota Core e pré-preenchimento apenas do comentário pai esperado.
- Runtime `toca`, Drupal 11.4.10, banco `aculta_runtime`: cache rebuild e
  resolução de `aculta_forum.quote_builder`/`discussion_context_resolver` PASS;
  raiz e `/categorias` HTTP 200. Consultas finais confirmaram 0 Nodes
  `aculta_forum_topic` e 0 Comments `aculta_discussion_post`. Link e formulário
  com entidade real não foram exercitados para conservar o Runtime vazio.
  RUNTIME STATUS: PARTIAL.

## dev.51 — denúncia contextual e proteção do acervo Growroom

- Forum Unit `HistoricForumFlagAccessHooksTest` e
  `ForumHistoricModerationQueueHooksTest`: 14 testes/58 assertions PASS;
  cobre permissão dedicada, tópico atual, autorias históricas no tópico/Comment,
  host histórico, destinos fora do Fórum, save direto com permissão, alvo
  inexistente/tipo inválido e as filas pendente/rejeitado.
- User Unit `ReportQueueTest`: 3 testes/14 assertions PASS; fila inclui tópico
  atual e exclui tópico histórico e Comment hospedado em outro contexto.
- Suítes Unit completas: Fórum 68/440 PASS; User 55/189 PASS
  com uma depreciação PHPUnit preexistente em `AuthorCardVariantResolverTest`.
  As duas classes novas são 14/58; `ReportQueueTest` 3/14.
- PHP lint, YAML de Config Sync/permissões, PHPCS Drupal nos seis arquivos PHP,
  `git diff --check`, Portal Drupal 11+ (971), fronteira (1402), mapa (152),
  pacotes próprios (25), política de papéis e hierarquia PASS. PHPUnit usa PHP
  8.4.26 e emite aviso de diretório
  `browser_output` não gravável, sem uso pelo conjunto Unit.
- Runtime de teste (`toca`, Drupal 11.4.10, banco `aculta_runtime`, PHP CLI
  8.4.26): cópia privada de recuperação de `user.role.email_confirmed` criada
  em `/home/aculta/private/aculta/runtime-config-backups/forum-dev51-20261020/`;
  importação `config:import --partial` processou somente esse YAML. Removidos os
  quatro grants genéricos `flag/unflag sinalizar_{topico,comentario}`, concedida
  `report aculta forum content` e declaradas as dependências `aculta_forum` e
  `aculta_user`. Cache rebuild PASS; Drush confirmou módulo e permissão
  descobertos. A configuração alvo não aparece como divergente no
  `config:status`. Após cache rebuild, a montagem da View `page_pending` incluiu
  predicado `1 = 0`: o campo de autoria histórica ainda não está disponível no
  Runtime, portanto a fila falhou fechada. Raízes públicas do Fórum e do purpose
  main responderam HTTP 200. Sem conteúdo do Fórum e sem sessão de participante,
  ação Flag e fila com dados não foram exercitadas end-to-end. RUNTIME STATUS:
  PARTIAL.

## Integração de comentários Revista/Fórum — Runtime de teste, 2026-10-19

- Firefox autenticado confirmou o formatter Core de `field_aculta_discussion`
  dentro do slot de discussão do SDC `revista-publication`. O formulário foi
  renderizado, mas a conta QA recebeu o bloqueio esperado porque o marcador
  canônico de confirmação de e-mail está ausente. Não alteramos usuário,
  confirmação, permissões nem configuração para contornar a política.
- Criados via Entity API, apenas para leitura das Views, um tópico temporário e
  dois Comments pendentes: um em `aculta_forum_topic`, outro em artigo. Como
  resultado, `aculta_forum_admin_comments.page_pending` retornou somente o CID
  do Fórum; `page_published` e `page_rejected` não retornaram esse CID; o bloco
  `aculta_magazine_admin_comments.block_comments` retornou somente o CID da
  Revista. Firefox autenticado confirmou os itens em suas respectivas áreas
  administrativas e não na outra. Requests anônimos às duas páginas públicas
  não mostraram nenhum dos textos pendentes.
- Removidos os dois Comments e o tópico de teste via Entity API. Conferência
  final no Runtime: 0 Nodes `aculta_forum_topic`, 0 Comments
  `aculta_discussion_post`, 0 Comments na publicação testada e filas Messenger
  sem itens. Nenhum conteúdo legado, publicação, usuário ou curso foi alterado.
- Resultado: separação de Views e exibição anônima **PASS**; montagem visual do
  campo **PASS**; envio autenticado pelo formulário **DEFERRED** pela política
  de confirmação e ausência de uma conta QA confirmada. RUNTIME STATUS: PARTIAL.
- A correção de slot e as evidências dos gates estão em
  `web/themes/custom/aculta420/CHANGELOG.md` (`0.6.0-dev.102`).

## Evidência dev.30 — primeira resposta não lida

- `ForumTopicDisplayConfigTest` verifica que somente a View
  `/discussoes/nao-lidas` habilita `node_new_comments`, liga à primeira resposta,
  esconde zeros e mantém cache desativado por conta. Forum Unit: 47 testes / 340
  assertions PASS; Kernel MariaDB: 4/75 PASS. PHPCS, Portal 11+ (970), fronteira
  (1368), mapa (154) e slugs (387) PASS. Cinco deprecações Kernel vêm do Domain
  contrib; esse Kernel cobre access/History adjacente, não renderização do campo
  novo; três avisos PHPUnit são anteriores a esta alteração.
- Runtime: import parcial da View com snapshot de recuperação e `config:status`
  sem diferenças. O handler History instalado resolve e a View retorna 8 itens
  após troca de conta por Drush; anônimo recebe 403. Nenhum tópico tem resposta
  aprovada no Runtime, então contagem positiva/fragmento não foi exercitado.
  Renderização CLI repetiu `Missing text format: basic_html` nas fixtures; HTTP
  autenticado e inspeção visual permanecem pendentes.

## Evidência dev.29 — marcar todas as discussões públicas como lidas

- PHPUnit Unit do Fórum: 47 testes / 335 assertions PASS; o teste novo verifica
  que a rota permite somente a conta autenticada no purpose `forum`.
- PHPUnit Kernel em MariaDB `aculta_simpletest`: 4 testes / 75 assertions
  PASS. O `ForumReadMarkerKernelTest` limita o conjunto a tópico publicado,
  acessível e do Domain Fórum e confirma que somente o History da conta atual
  recebe o registro. Cinco deprecações observadas vêm do Domain contrib 3.0.1.
- Runtime `toca`: `drush cr`, resolução de rota e callbacks DI do Batch,
  construção do formulário, service container e HTTP anônimo 403 — PASS. A
  submissão autenticada não foi executada, evitando gravar History de uma conta
  real. RUNTIME STATUS: PARTIAL.
- PHPCS, lint, gate Drupal 11+ (970), fronteira (1368), mapa (154), slugs (387)
  e `git diff --check` — PASS.

## Evidência não marcada — agregado de respostas públicas

- `ForumTopicMoverKernelTest` valida Comment e `comment_entity_statistics` em
  MariaDB dedicado: resposta publicada conta como 1, resposta pendente mantém
  o contador em 1 e mover a categoria preserva o mesmo agregado no Node. 2
  testes / 48 assertions PASS.
- Runtime de teste continua sem respostas aprovadas; portanto, a ordenação
  `/discussoes/mais-comentadas` com contagens distintas ainda aguarda fixture
  deliberada em homologação. Nenhum conteúdo de Runtime foi criado.

## Evidência não marcada — contagem e atividade na listagem pública

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 13 testes / 137 assertions PASS.
- No Runtime `toca` (Drupal 11.4.10, `aculta_runtime`, URI
  `https://growroom420.aculta.toca.net.br`), import parcial da View e cache
  rebuild; na primeira homologação a query retornou 8 tópicos com contagem 0,
  e HTTP 200 apresentou `0 respostas` nas oito linhas. Os oito fixtures foram
  removidos posteriormente por solicitação do responsável; o smoke após limpeza
  confirmou HTTP 200 e ausência de `[TESTE]` nas listagens. Nenhum Comment foi
  criado.
- O sort Core `comment_ces_last_updated` lançou `TypeError` em 11.4.10; a
  configuração foi revertida e a rota voltou HTTP 200. O sort virtual
  `PublicForumLastActivity` então executou: Views reconhece o plugin do ACULTA,
  SQL contém `GREATEST(node.changed, last_comment_timestamp) DESC`, 8 resultados,
  campo Core `last_updated` renderizado e HTML HTTP 200 com `0 respostas` e
  “Última atividade” em todas as linhas. Estado atual depois da limpeza: 0
  tópicos; a contagem/ordenação com dados segue aguardando fixture deliberada.
- Caso positivo e invalidação após moderação continuam DEFERRED. Render direto
  por Drush registrou `Missing text format: basic_html` nos teasers; drift a
  investigar separadamente. Inspeção visual também pendente. RUNTIME STATUS:
  PARTIAL.
  RUNTIME STATUS: PARTIAL.
- Rollback seguro ao estado funcional anterior (contador sem data/sort) em
  `/home/aculta/private/aculta/forum-activity-20261019/views.view.aculta_forum_public_topics.yml`.

## Evidência dev.28 — índice público de categorias

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 13 testes / 123 assertions PASS;
  install e Config Sync das Views de categorias e tópicos são equivalentes.
- YAML parse, `git diff --check` e gates Drupal 11+, fronteira Portal/tema,
  mapa módulo/tema e slugs PASS.
- Runtime de teste `toca`, Drupal 11.4.10, banco `aculta_runtime`, URI explícita
  `https://growroom420.aculta.toca.net.br`: importação parcial somente das duas
  Views alteradas e cache rebuild. `/` e `/categorias` responderam HTTP 200;
  a View retornou os 12 termos existentes, com descrições, e os links entre os
  índices apareceram no HTML. A página não encaminha para Views genéricas de
  Taxonomy. Config Sync não apresenta diferenças para essas Views. Nenhum termo,
  Node ou Comment foi criado, alterado ou excluído.
- RUNTIME STATUS: PASS para rotas, View e navegação HTTP. Inspeção visual e
  apresentação hierárquica permanecem pendentes para a fase ACULTA420.

## Evidência inicial dev.10 — tópicos não lidos

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 14 testes / 157 assertions PASS;
  a View de não lidos mantém os filtros da listagem pública, exige role
  `authenticated`, usa o filter/field plugin de History e desativa cache
  compartilhado.
- Composer: `drupal/history:^1.0` foi declarado diretamente para sobreviver à
  retirada futura de `drupal/forum`, sua dependência anterior. Composer validate
  e audit reportam nenhum advisory conhecido; o projeto History declara que não
  está coberto pela política de avisos do Drupal.org. Acompanhamento de releases
  permanece obrigatório.
- Runtime `toca`: Views data confirmou o plugin instalado e a query SQL juntou
  History pelo uid atual. A conta uid 5 passou no plugin de acesso e encontrou
  7 discussões novas/atualizadas; o anônimo recebeu HTTP 403 em
  `/discussoes/nao-lidas`. `/discussoes` é caminho interno bloqueado (404).
  Nenhum Node, Comment ou registro de leitura foi alterado.
- Renderização direta da View pelo Drush emitiu `Missing text format:
  basic_html` nos teasers das fixtures; mesmo formato já é usado pela listagem
  pública e requer investigação própria. HTTP com sessão autenticada e
  inspeção visual pendentes. RUNTIME STATUS: PARTIAL.

## Evidência inicial dev.10 — telas de descoberta

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 15 testes / 184 assertions PASS;
  PHPCS PASS. A View compartilha os filtros da lista pública e a tela sem
  resposta usa `comment_entity_statistics.comment_count = 0`; a tela popular
  ordena pelo agregado Core e por atividade.
- `PublicForumExposedFormHooksTest`: 3 testes / 11 assertions PASS. A exceção
  ao CAPTCHA cobre somente os quatro filtros GET da View pública; formulários
  de escrita não são alterados.
- Suite Unit completa do Fórum: 46 testes / 332 assertions PASS; inclui
  `ForumTopicMoveHooksTest` (wiring do mover para o service ID). Uma
  depreciação preexistente de metadata PHPUnit fica em
  `DiscussionModerationHooksTest`.
- Runtime `toca`: após importar somente a View pública e reconstruir caches,
  `/discussoes/novas`, `/discussoes/sem-resposta` e
  `/discussoes/mais-comentadas` retornaram HTTP 200 e exibiram tópicos `[TESTE]`.
  Cada query encontrou 8 itens; todos 8 aparecem sem resposta porque há 0
  comentários públicos. Config Sync ficou sem diferença para a View.
- O cache rebuild inicial revelou wiring inválido de `ForumTopicMoveHooks`;
  a injeção explícita do service ID foi adicionada e o rebuild subsequente
  passou. Nenhum Node, Comment ou registro de leitura foi alterado.
- Ainda não foi validada uma ordenação com comentários positivos nem a
  visualização autenticada em browser. RUNTIME STATUS: PARTIAL.

Esta matriz separa plano das evidências já executadas. Na `0.1.0-dev.4`, a
Context API pública e configuração nativa foram exercitadas no Runtime de teste.
Na dev.5, um teste Kernel prova storage Comment compartilhado em três bundles
Node e Unit cobre access/cache do provider editorial. As field instances da
Revista estão aplicadas no Runtime de teste; HTTP/browser e envio de Comment
continuam pendentes.

| Camada | Casos mínimos | Evidência |
| --- | --- | --- |
| Instalação/configuração | install profile limpo, dependências, IDs e nomes <=32, field Comment nos bundles Node permitidos | Kernel/install tests; validação de config. |
| Context API | resolver cada contexto, provider ausente/desabilitado, host inválido e URLs canônicas | Unit + Kernel. |
| Comment | tipo compartilhado (ou variantes justificadas), host bundles e field settings, thread, revisão, publicação, permalink/ancora, formulário | Kernel + Functional. |
| Formulário/display de tópico público | title/body/categoria editáveis; status de comentário e autoria histórica não editáveis; full apresenta discussão compartilhada; teaser omite thread | Unit verifica contrato YAML e equivalência Config Sync/config install. Na homologação, 12 termos e 8 tópicos `[TESTE]` foram usados para validar navegação; os tópicos foram removidos depois. Estado atual do Runtime: 12 termos, 0 tópicos e 0 Comments; histórico e IDs em `TEST-FIXTURES.md`. Os oito aliases responderam HTTP 200 antes da limpeza e HTTP 404 depois, sem redirect. |
| Ação de criação no índice | link aparece apenas se a rota Core autoriza; variação por usuário/permissões e contexto `forum`; ausência em outros displays/domínios | Unit `PublicForumTopicCreateActionHooksTest`: 5 testes/19 assertions PASS. Runtime anônimo: raiz 200, link omitido, rota 403. Conta autorizada, navegador e apresentação integral pendentes. |
| Acesso ao formulário de tópico | `email_confirmed` cria e gerencia somente tópico próprio; sem permissões `edit any`/`delete any`; access real da entidade | Unit `ForumTopicDisplayConfigTest`: 7 testes/47 assertions PASS. Runtime `toca`: uid 5 autorizado na rota; formulário Core renderizado em memória, sem submissão; contagens seguem 0 tópicos/Comments. Browser e envio funcional DEFERRED. |
| Respostas no tópico público | Field Comment compartilhado aberto, formulário inline Core, anônimo bloqueado e permissão `post comments` | Unit `ForumTopicDisplayConfigTest` verifica equivalência install/Sync e contrato de field/role. Runtime `toca`: status Open, uid 5 tem permissão, 0 tópicos/Comments. Render e envio vinculados a tópico salvo DEFERRED para preservar acervo limpo. |
| Contagem de respostas em tópicos próprios | Conta somente tópicos publicados da pessoa e usa Comment Core `comment_count`; comentários pendentes ficam fora do total | Kernel `ActivityControllerKernelTest`: 1 teste/12 assertions PASS no MariaDB `aculta_simpletest`; `view('forum')` lista apenas o Node da conta atual, mostra 2 publicados, exclui 1 pendente e restaura o Domain de conta depois do access de Fórum. Cinco depreciações do Domain contrib. Runtime `toca`: rota `/atividade/forum` autenticada exibiu somente o tópico próprio, com “2 comentários”; 2 publicados e 1 pendente foram confirmados no estado Core, pendente não entrou na contagem. Firefox/X11 1024 e Responsive Design Mode 390 × 670 px sem overflow horizontal visível. Outra fixture de uid 1 não apareceu. Dois Nodes e três Comments temporários removidos; estado final 0 tópicos/Comments. RUNTIME STATUS: PARTIAL. |
| Navegação por categoria | filtro Views nativo na referência Taxonomy, hierárquico, escopo fixo no vocabulário novo e query args em cache | Unit `ForumTopicDisplayConfigTest` cobre filtro e hierarquia. Runtime `toca`: 12 termos e 0 tópicos após limpeza dos fixtures; antes da limpeza o filtro `categoria=77` mostrou o tópico esperado. O CAPTCHA global foi removido apenas da View GET de leitura, que antes abortava a listagem; formulário de criação anônimo permanece 403. Inspeção visual com a lista vazia pendente. |
| Acesso público | anônimo/membro/editor; publicação, conteúdo fechado, flags e permissões | Kernel + Functional/browser. |
| Publicação | comentários no Node editorial, conteúdo unpublished, canonical, âncora, sem Node forum gerado | Kernel + Functional; anti-regressão Magazine. |
| Moderação inicial | Primeiro comentário e pendências em qualquer Node com `field_aculta_discussion`; LMS sem moderação, e comentário LMS publicado libera o bloqueio temporário de tópico | Kernel `PublicDiscussionModerationKernelTest` PASS; Runtime editorial PARTIAL (field/form renderizados sem envio). |
| Moderação por contexto/estado | Fórum mostra abas Publicados, Rejeitados e Aguardando moderação; Revista tem sua própria listagem; Wiki/LMS aguardam adapters; filas não se misturam | Unit Forum: `ForumTopicDisplayConfigTest` + `DiscussionModerationHooksTest`, 37 testes/218 assertions PASS. Runtime `toca`: Views retornaram cada estado do Fórum e a fila Magazine trouxe somente os seus comentários; API de tarefas locais confirmou as três abas sob Fórum. Render API público mostrou somente publicados no tópico e na publicação. Os seis registros `[QA 20261019]` foram removidos; contagem final 0 nos dois destinos. Browser autenticado DEFERRED. RUNTIME STATUS: PARTIAL. |
| LMS | duas turmas isoladas, usuário em A e não B, role global estudante sem membership; moderação por Educador com acesso contextual atual e negação para educador sem membership/acesso | Kernel/Functional; Route/View/Search/Cache e teste de navegador. |
| Wiki | uma thread por verbete, comentário não altera revisão, acesso de edição distinto | Contract tests nesta fase; integração real só após Wiki. |
| Busca/Views | filtro de contexto fixo, access query, títulos/trechos/contagens/facets e paginação sem vazamento | Kernel/Functional, index rebuild no ambiente de teste. |
| Contagem de respostas em tópicos | zero e plural; bundle fixo `aculta_forum_topic` com seu campo único de discussão; estados de moderação conforme agregação Core; cache/invalidação | Kernel `ForumTopicMoverKernelTest`: 3 testes/69 assertions PASS em `aculta_simpletest`; aprovação incrementa `comment_count`, despublicação e exclusão o decrementam, pendentes não entram e mover categoria preserva total. `PublicTopicCommentCountViewKernelTest`: 1/11 PASS; Views Core ordena três tópicos por respostas publicadas e mantém primeiro o tópico sem resposta quando outro tópico tem apenas pendente. Cinco depreciações do Domain contrib no Kernel do mover. Runtime `toca`: View pública executada com tópico de QA, Core e campo da View retornaram 2 para respostas publicadas; 1 resposta pendente ficou fora. Firefox/X11 raiz do Fórum 1024 px mostrou “2 respostas” e atividade. Fixture com 3 Comments foi excluída; Runtime voltou a 0 tópicos/Comments. Comparação de ordenação com múltiplos tópicos e invalidação visual após moderação permanecem pendentes. RUNTIME STATUS: PARTIAL. |
| Cache | tags/context/max-age em variações por usuário/permissão/Group/membership, revogação e conteúdo despublicado | Kernel + Functional; inspeção de cache. |
| Notificações | dedupe, agrupamento, opt-in e confirmação, revalidação de acesso na fila, retirada/revogação, zero conteúdo sensível | Unit/Kernel do produtor e messenger + Functional. |
| Autoria | conta privada, apelido alterado, foto/privacidade, usuário bloqueado, autor legado, dados ausentes | Unit/Kernel + browser e cache. |
| Atividade de comentários do Fórum em Minha Conta | Query inclui apenas `aculta_discussion_post` publicado em `field_aculta_discussion` de `aculta_forum_topic`; Revista, pendentes e contextos privados não entram; links usam Domain Fórum + âncora; Entity Access revalidado | Kernel `PublicDiscussionModerationKernelTest`: 6 testes/73 assertions PASS para query/contexto/estado. Runtime `toca`, uid 5 e busca total sem dados compatíveis: render retornou lista vazia, sem mutação; caminho com link/card e browser seguem pendentes. |
| Autoria de tópico novo | User usa `author_forum`; referência histórica usa `author_growroom_historico`; nenhuma conversão para conta; somente `full`/`teaser` | Unit `PublicForumTopicAuthorHooksTest` e `AuthorIdentityTest`: 6 testes/23 assertions PASS. Runtime: hook registrado; node `unsaved` em memória renderizou `author_forum`, contagem permaneceu 0. Tópico persistido/browser pendentes. |
| Imagens/anexos | ingestão obrigatória aculta_image, uso/referências, anexo privado, substituição e limpeza | Gate `validate-aculta-image.php`, Kernel + testes de acesso. |
| Moderação | criar, editar, sinalizar, aprovar, ocultar, despublicar, bloquear, auditoria | Unit/Kernel/Functional por papel. |
| Integração Game | primeira resposta publicada em `aculta_discussion_post`/`aculta_forum_topic`; aprovação tardia uma vez; outras entidades, sincronização e `field_autor_legado` excluídos | `aculta_game` Unit `BadgeEvaluationHooksTest` cobre contrato de bundle/field e casos negativos. Concessão real/cache/commit externo e Runtime seguem pendentes. |
| Mover tópico individual entre categorias | permissão específica e Entity Access no purpose público; categoria do vocabulário correto; confirmação; submissão adulterada; preservação de NID/UUID/alias/Comments/flags; nova revisão; autoria histórica negada mesmo para admin | Unit `ForumTopicMoverTest`: 4 testes/31 assertions PASS. Kernel `ForumTopicMoverKernelTest`: 2 testes/44 assertions PASS em MariaDB `aculta_simpletest`; comprova mesmo NID/UUID, nova revisão, alias e Comment/CID preservados e bloqueio histórico mesmo com permissões de administrador. Functional e Runtime autenticado pendentes; nenhuma fixture do Runtime foi movida. |
| Mover comentário e subárvore para outro tópico público | autorização, dois endpoints no purpose Fórum, tópico aberto, legado imutável, estados publicados/pendentes/rejeitados, thread profunda e ramificada, identidade/datas, estatísticas de origem/destino, cache, gate desabilitado por default e rollback | Kernels `ForumCommentMoverKernelTest` + `ForumTopicMoverKernelTest` cobrem árvore profunda/ramificada, estados, rollback, locks, flagging, Search API e file usage; dev.73 executou `ForumCommentMoverKernelTest` 13/272 PASS no MariaDB `aculta_simpletest`, com 19 depreciações Core/contrib. Runtime `toca` dev.73–77: raiz, árvore linear e árvore ramificada com estado pendente/rejeitado foram movidas via Form API/CSRF, preservando CIDs/autores/textos/estados/field de rejeição/pid/thread; X11 10/10 em 390/768/1024 CSS px, sem overflow. Fixtures removidas; Fórum terminou com 0 tópicos/Comments, fila Messenger 0, 12 termos e 3 avisos baseline. `comment_move_enabled` continua ausente por padrão. Unit Forum 82/500; PHPCS e gates Drupal11+ PASS; PHP CLI 8.4.26 abaixo da baseline 8.5. Pendentes: aprovação/publicação via workflow no Runtime, History, idiomas, avisos com seguidores, arquivos pela política `aculta_image`, permalink e concorrência HTTP. RUNTIME STATUS: PARTIAL. |
| Outras ferramentas de movimento | reorganizar Taxonomy; lote via Views/VBO; split/merge; verificar revisões/URLs/arquivos/flags/estatísticas/cache/busca/notificações e recuperação | Planejadas. `MOVE-TOOLS.md` registra pesquisa de Comment Mover e VBO; não instalar VBO nem expor ferramenta em lote até aprovar o escopo e segurança das Actions. |
| Acervo Growroom restaurado | autoria por `field_autor_legado`; edição/exclusão/aprovação negadas a qualquer papel; tópico fechado; bulk admin e IDs adulterados rejeitados | Unit `ForumModerationHooksTest`; Kernel/Functional de importação e access pendentes. Não confundir com Nodes `forum` do módulo contrib, cuja retirada é separada. |
| UX/acessibilidade | mobile, teclado, foco, leitor de tela, light/dark, reduced motion e fallback sem JS | Firefox/browser, auditoria manual e automatizada existente. |
| Revisão visual de referência | Entrada, categorias, listagem, tópico e composição; comparação com Ubuntu Discourse em desktop/768/390 px; claro/escuro; ritmo, densidade, navegação, foco e overflow | Rodada X11 parcial registrada nesta matriz; não reutilizar marca/ativos/CSS da referência. |
| Performance | listagens, thread grande, Views, Query counts e contagens/cache | profiling comparável; limites definidos na fase de benchmark. |
| Remoção/migração | dry run, inventário, exclusão escopada, uninstall, composer remove, rollback/restauro e ausência de residuais | prova somente no Runtime de teste e ponto de recuperação revisado. |

## Evidência dev6 — autorização de moderação por e-mail confirmado

- PHPUnit Unit `ForumModerationHooksTest`: 5 testes / 21 assertions — PASS.
- Cobertura: editor confirmado pode editar/excluir tópico de outra pessoa;
  editor sem marcador confirmado é negado mesmo com papel `email_confirmed`;
  autoria própria segue o access handler normal; bundle alheio não é afetado;
  bypass administrativo permanece.
- PHPCS Drupal/DrupalPractice, lint PHP, gate Drupal 11+ (969), pacotes próprios
  (22), fronteira módulo/tema (1.304), mapa (154) e `git diff --check` — PASS.
- Runtime de teste: DEFERRED. Nenhuma permissão, configuração ou conteúdo foi
  aplicado no Runtime. A listagem Views/CTools e o fluxo de moderação ainda
  precisam de validação integrada.

## Evidência dev.8 — acervo Growroom restaurável em modo somente leitura

- PHPUnit Unit `ForumModerationHooksTest`: 10 testes / 53 assertions — PASS.
- Cobre tópico histórico imutável mesmo para administrador, comentários do
  tópico, fechamento do Comment field no presave, filtragem da fila
  administrativa e rejeição de IDs forjados.
- A identificação usa `field_autor_legado`, não os bundles `forum` ou
  `comment_forum` da implementação contrib a retirar.
- Storage e field instances são instaláveis/sincronizados; importação e Runtime
  ficam DEFERRED. Nenhum conteúdo ou configuração persistente foi alterado.

## Evidência dev.5 — política de aprovação inicial compartilhada

- PHPUnit Kernel `PublicDiscussionModerationKernelTest`: 5 testes / 63
  assertions — PASS em MariaDB `aculta_simpletest` (PHP CLI 8.4.26).
- Cobertura: comentários LMS permanecem publicados sem moderação; uma publicação
  LMS conta como aprovada para contextos não LMS e libera o bloqueio temporário;
  política administrativa desligável; campo compartilhado em Wiki/outros Nodes;
  comentário não LMS pendente bloqueia criação de tópico quando configurado.
- `phpcs --standard=Drupal` nos arquivos PHP alterados: PASS. Gate Drupal 11+:
  969 checks — PASS. `git diff --check` — PASS.
- As decisões de acesso incluem a tag `config:aculta_user.settings` e os
  contextos `user`/`user.roles`; mudanças nas opções ou papéis não deixam
  resultados incompatíveis em cache.
- Runtime editorial e instalação/atualização de configuração continuam
  DEFERRED. Nenhum conteúdo ou configuração persistente foi alterado.

## Evidência `0.1.0-dev.4` (2026-10-19)

- Unit aculta_forum: resolver/provider, 7 testes e 25 assertions — executado.
- Unit Portal `ContentPurposeResolver`: 2 testes/2 assertions para o bundle novo e o legado — PASS.
- Gate Drupal 11+: 969 checks; fronteira módulo/tema: 1.290 verificações;
  mapa: 154 checks; pacotes de módulos próprios: 21 checks — PASS.
- No servidor `toca`, host de teste `growroom420.aculta.toca.net.br`, Drupal
  11.4.10: módulo instalado, serviço `DiscussionContextResolver` resolvido,
  campos `body`, `field_aculta_categories` e `field_aculta_discussion` presentes,
  zero Nodes do novo bundle. Config Sync não mostra divergência nos objetos
  novos. Nenhum tópico/resposta de usuário foi criado.
- PHP CLI do servidor reporta 8.4.26; o gate de versão de PHP 8.5 permanece
  pendente de validação pelo PHP-FPM/runtime web.
- Nenhum provider de Publicação/LMS/Wiki, acesso de escrita/moderação, Views,
  UX ou navegação pública foi homologado nesta subfase.

## Evidência dev.5 — provider editorial e Comment multi-bundle

- PHPUnit Unit `aculta_forum`: 12 testes / 52 assertions — PASS (PHP CLI 8.4.26).
- PHPUnit Kernel `SharedDiscussionFieldKernelTest`: 1 teste / 18 assertions —
  PASS usando exclusivamente MariaDB `aculta_simpletest`; o mesmo storage
  `field_aculta_discussion` e Comment type podem ser anexados a `article`,
  `blog` e `aculta_forum_topic`.
- Unit cobre leitura via Entity/Field Access, permissões nativas `post comments`
  e `administer comments`, campo fechado, negação de access, stable ID e cache.
- O Kernel prova a capacidade do Core com a configuração equivalente; não instala
  `aculta_forum`, não cria conteúdo e não substitui teste Functional/Runtime dos
  fields reais da Magazine.
- Runtime de teste: cache rebuild e smoke de DI confirmaram os dois providers; o
  provider editorial não atende Article enquanto o field ainda não existe.
  Nenhuma importação, field editorial ou conteúdo foi criado. RUNTIME STATUS:
  PARTIAL para o registro do serviço; integração editorial segue pendente.

## Evidência dev.10 — formulário e displays do tópico público

- PHPUnit Unit do `aculta_forum`: 15 testes / 67 assertions — PASS.
- PHPCS Drupal/DrupalPractice no teste novo, PHP lint, YAML parse, gates Portal
  Drupal 11+, fronteira módulo/tema, mapa módulo/tema e `git diff --check` —
  resultados registrados no commit desta entrega.
- No servidor de teste `toca`, Drupal 11.4.10, URI explícita
  `https://growroom420.aculta.toca.net.br`, banco `aculta_runtime`: importação
  parcial incluiu somente os displays Form, Full e Teaser. O `config:status`
  por prefixo confirmou os três sem divergências.
- Smoke Form API usou Node em memória: título, corpo e categoria aparecem como
  entradas; o Core inclui `revision_log` no grupo padrão de revisão. O status
  Comment é `Open`. Nenhum formulário foi salvo.
- Contagens posteriores: 0 `aculta_forum_topic`, 0 `aculta_discussion_post`,
  30 Nodes legados `forum` e 0 Comments `comment_forum`. Nenhum conteúdo foi
  criado, alterado ou excluído.
- RUNTIME STATUS: PARTIAL. Fluxo persistido de criação/resposta, acesso
  integrado, apresentação no navegador e UX ainda aguardam a fase funcional.

## Evidência dev.11 — View pública isolada por domínio

- PHPUnit Unit `aculta_forum`: 21 testes / 102 assertions — PASS.
- PHPCS Drupal/DrupalPractice, PHP lint, YAML parse, Drupal 11+ (969), pacotes
  (22), fronteira (1.323), mapa (154) e `git diff --check` — PASS.
- No Runtime `aculta_runtime`, importação parcial incluiu a field instance de
  Domain Source, a View nova e os três displays atualizados. A field instance
  já existente no Runtime tinha outro UUID, então o Drush a substituiu durante
  a sincronização; antes da operação havia zero
  Nodes `aculta_forum_topic` e zero linhas dessa bundle em
  `node__field_domain_source`. Os 30 Nodes legados e seus valores de domínio
  permaneceram intactos. O restante da configuração não foi importado.
- Após cache rebuild, os hooks OOP foram encontrados pelo ModuleHandler; smoke
  sem salvar Node atribuiu `forum_aculta_org`. A View carregou/executou e retornou
  zero itens, conforme o acervo novo vazio. As cinco configurações alvo ficaram
  sem divergência no `config:status` por prefixo.
- RUNTIME STATUS: PARTIAL. A View não foi colocada, não há tópico persistido e
  browser/UX seguem pendentes.
- A View nova tem Page display interno `/discussoes` e serve a raiz do purpose forum; o caminho direto e o antigo `/forum` permanecem fechados. O bloco do acervo contrib continua sem colocação na raiz.

## Evidência dev.12 — índice na raiz do Growroom420

- URI Drush: `https://growroom420.aculta.toca.net.br`; banco `aculta_runtime`.
- Importação parcial de apenas `views.view.aculta_forum_public_topics`; cache rebuild. A rota `view.aculta_forum_public_topics.page_discussions` resolve `/discussoes` com purpose `forum`; a View executa com zero linhas.
- HTTP: raiz `/` retorna 200 com título “Discussões do Growroom420”; `/discussoes` e o antigo `/forum` retornam 404.
- Nenhum Node, Comment ou arquivo criado/alterado/apagado. RUNTIME STATUS: PARTIAL; sem criação/resposta persistida e sem inspeção visual.

## Evidência dev.13 — criação por formulário Core

- Rota `aculta_forum.topic_add` resolve `/criar-topico`, `node.default`, bundle `aculta_forum_topic` e `_entity_create_access: node:{node_type}`; `DomainRouteSubscriber` classifica a rota no purpose `forum`.
- Drush no Runtime confirmou URI do fórum e banco `aculta_runtime`. HTTP anônimo no fórum retornou 403; a mesma URL no purpose principal retornou 404. Conta de teste sem a permissão de criação também foi negada.
- Nenhum formulário foi submetido, nenhum Node/Comment salvo. RUNTIME STATUS: PARTIAL; fluxo autorizado e inspeção visual pendentes.

## Evidência dev.9 Magazine — comentários diretos no Runtime de teste

- Importação parcial de quatro objetos: fields `field_aculta_discussion` em
  `article`/`blog` e displays full dos dois bundles. Sem importação integral,
  update de banco ou gravação em Nodes/Comments.
- Runtime `https://revista420.aculta.toca.net.br`, Drupal 11.4.10, banco
  `aculta_runtime`: 14 publicações publicadas; campo existe, component
  `comment_default` presente e status do campo de amostra `Open` (2). A build do
  Node usa pré-render do Core. Com o usuário de teste uid 5, permissionado a
  `post comments`, o formatter renderizou o campo e o formulário em memória.
  Nenhum formulário foi enviado; saída HTTP/browser ainda não verificada.
- Após a operação: 0 `aculta_discussion_post`, 30 Nodes legados `forum`, 0
  `comment_forum` e 0 `aculta_forum_topic`. Os 12 Nodes vinculados às publicações
  permanecem intocados; os campos `field_discussao` continuam guardando os
  vínculos durante a transição.
- RUNTIME STATUS: PARTIAL. Nenhum comentário enviado; acessibilidade, moderação
  efetiva e UI em navegador pendentes.

Gates aplicáveis seguem `scripts/validate-aculta-portal-drupal11.php`, fronteira
módulo/tema, mapa módulo-tema, slugs, rotas, endereços, imagem, hierarquia de
papéis e gates relacionados ao código tocado. O gate final será recalibrado na
fase funcional; não assumir que uma lista estática substitui teste real.

Fase RC executará a cobertura integrada: Unit, Kernel, Functional, Domain matrix,
cache/access, browser/mobile, acessibilidade, desempenho, busca, notificações,
instalação/update/uninstall e rollback. Runtime permanece DEFERRED até validação
real no servidor de teste.

## Fórum dev.21 / Portal dev.190 — paginação pública

- Unit `ForumTopicDisplayConfigTest`: 9 testes / 62 assertions PASS; install e
  Config Sync equivalentes; pager Core completo com 20 itens e seletor de
  quantidade fechado.
- Runtime `toca`: config ativa confirma `type: full`, `items_per_page: 20`; raiz
  HTTP 200 mostra as 8 fixtures; filtro `?categoria=77` HTTP 200 mostra o tópico
  esperado. Nenhum conteúdo foi gravado. Navegação entre páginas (mais de 20
  tópicos) e apresentação visual dos controles ainda não exercitadas.
- Gates Drupal 11+ 969, fronteira 1334, mapa 154, slugs 387 e PHPCS PASS.
  RUNTIME STATUS: PARTIAL.

## Fórum dev.22 / Magazine dev.12 / Portal dev.191 — organização administrativa por contexto

- PHPUnit Unit Forum + Magazine: 12 testes / 96 assertions — PASS. Verifica
  escopo de bundle/campo, permissões, caminhos, bloco da Revista e equivalência
  entre configuração de instalação e Config Sync.
- PHP lint e PHPCS Drupal/DrupalPractice nos testes novos — PASS. Gate Portal
  Drupal 11+: 969 checks; fronteira: 1.336; mapa: 154; slugs: 387; ACULTA420
  Foundation via Drush: 355; SDC: 516; design: 2.078 — PASS. `git diff
  --check` — PASS.
- Runtime `toca`, Drupal 11.4.10, PHP CLI 8.4.26, banco `aculta_runtime`:
  importação parcial incluiu View de comentários públicos, Views de conteúdo e
  comentários da Revista e bloco editorial na região `content` do tema Claro,
  condicionado ao purpose `main` e à URL administrativa. Cache reconstruído.
- Drush confirmou os caminhos de Fórum e Revista; as Views carregam/executam
  sem erro. Conta uid 5 tem acesso às Views por permissões efetivas; visitante
  anônimo recebe HTTP 403 nos endpoints. Renderização HTTP com sessão editorial
  e exibição do bloco no navegador ainda pendentes.
- Nenhum Node, Comment, arquivo, revisão editorial ou dado de usuário foi
  criado, alterado ou removido. Discussões LMS/Wiki permanecem sem rotas
  executáveis até seus adapters provarem access contextual. RUNTIME STATUS:
  PARTIAL.

## Correção Portal dev.184 — raiz e cache de rotas

- A inspeção de `Drupal\Core\Routing\RouteProvider::getRouteCollectionForRequest()` confirmou que o cache conserva caminho, query e rotas, sem os atributos do Request marcados pelo path processor.
- Após `drush cr` no Runtime `aculta_runtime`, HTTP direto em `/` e com query de diagnóstico retornou 200; `/discussoes` retornou 404. Nenhum Node, Comment ou termo foi criado. RUNTIME STATUS: PARTIAL; criação e apresentação visual ainda pendentes.

## Fórum dev.16 / Portal dev.185 — estado vazio da View pública

- Unit `ForumTopicDisplayConfigTest`: 6/42 PASS. Sync e configuração de instalação equivalentes; mensagem usa Views Core `text_custom` e não tokeniza conteúdo.
- Runtime `toca`: importação parcial apenas de `views.view.aculta_forum_public_topics`. Após cache rebuild, raiz HTTP 200 mostrou título, seletor de categoria e texto vazio; rota interna `/discussoes` retornou 404. Tópicos e comentários compartilhados continuam em zero.
- RUNTIME STATUS: PARTIAL; listagem vazia conferida por HTTP, navegador e tópico persistido seguem pendentes.

## Revisão visual X11 — Ubuntu Discourse e ACULTA Forum — 2026-10-20

- Sessão gráfica X11 ativa, Firefox visível, Runtime de teste `toca`; comparação
  visual da entrada Ubuntu em 1024 px com a raiz ACULTA. Depois da implementação
  dev.52, entrada ACULTA e categorias foram inspecionadas em 1024, 768 e 390 px
  no modo claro; o escuro foi forçado somente no DOM local do navegador e
  inspecionado em 1024 e 390 px, sem alterar a configuração do Runtime.
  Capturas privadas em `/home/aculta/private/aculta/visual-tests/forum-dev11-20261020/`,
  todas `0600`: `ubuntu-home-1024.png`, `aculta-home-before-categories-1024.png`,
  `aculta-home-categories-activity-1024.png`, `aculta-home-categories-activity-768.png`,
  `aculta-home-categories-activity-390-top.png` e
  `aculta-home-categories-activity-390-lower.png`, `aculta-home-dark-1024.png`,
  `aculta-home-dark-390-top.png`, `aculta-home-dark-390-lower.png` e as capturas
  de foco `aculta-home-dark-keyboard-skip-1024.png`,
  `aculta-home-dark-keyboard-logo-1024.png` e
  `aculta-home-dark-keyboard-search-1024.png`.
- Em 1024 px, categorias principais e atividade aparecem lado a lado; em 768 e
  390 px, a lista ocupa primeiro a largura e as categorias aparecem depois.
  `/categorias` mantém todos os 12 termos e refluíu de três colunas a 1024, duas
  a 768 e uma a 390. Não foi observado overflow horizontal; as abas da entrada
  quebram em duas linhas a 390 px.
- Drupal 11.4.10, Runtime `aculta_runtime`, URI
  `https://growroom420.aculta.toca.net.br`: importação parcial da View de
  categorias e do bloco da entrada, com backup recuperável em
  `/home/aculta/private/aculta/config-reconcile/forum-landing-20261020/`;
  `drush cr` PASS. Entrada e `/categorias` HTTP 200. O bloco aparece no purpose
  `forum` e está ausente na raiz purpose `main`. A View da entrada retorna 4
  categorias principais; a página `/categorias` mantém 12 termos. Contagem
  final: 0 `aculta_forum_topic` e 0 `aculta_discussion_post`.
- `ForumTopicDisplayConfigTest`: 17 testes/215 assertions PASS; PHP/YAML e
  `git diff --check` PASS. Foundation 364 via Drush, SDC 535, Design
  Foundations 2097, Portal 975, fronteira 1411 e mapa 152 PASS.
- Escopo visual **PARTIAL**: modo claro/escuro está inspecionado nos tamanhos
  descritos. Em X11, Tab percorreu o atalho "Pular para o conteúdo principal",
  marca e busca, com indicador de foco amarelo visível no modo escuro em 1024 px.
  Leitores de tela, navegação completa por teclado, lista com tópicos publicados,
  tópico, respostas e composer não foram avaliados. O
  Runtime permaneceu sem fixtures. Nenhuma conclusão de aprovação visual
  integral ou acessibilidade foi feita. RUNTIME STATUS: PARTIAL.

# Changelog — ACULTA Wiki

## 0.1.0-dev.78 — sincronizar fixtures Unit do formulário de imagem

- As fixtures de `WikiEntryImageHooksTest` agora fornecem as dependências
  adicionadas ao hook e usam uma entidade `wiki_entry` na alteração do
  formulário.
- Wiki Unit passou: 66 testes / 344 assertions, 3 depreciações PHPUnit.
  Runtime não foi alterado.

## 0.1.0-dev.77 — retorno do formulário ao Meu Trabalho

- Os formulários Core de `wiki_entry` exibem link de retorno ao Meu Trabalho
  somente quando a rota opcional do Studio existe e o serviço proprietário da
  Wiki autoriza a experiência para a conta atual. O destino usa o Domain do
  purpose `account`; a renderização herda cache contexts/tags do `AccessResult`.
- A Wiki segue funcionando sem `aculta_studio`; ausência de rota ou Domain
  omite o link. Kernel valida rota, Domain, acesso e formulário real.
- `WikiLeadImageLifecycleKernelTest`: 23 testes / 1.039 assertions em MariaDB;
  291 depreciações Core/contrib e 24 PHPUnit. PHP lint e PHPCS passam. Runtime
  não foi alterado.
- Revalidação WIKI-R1 no HEAD: Unit dos providers 9/40; quatro Kernels reais
  (provider, acesso/relações, discovery owner e rebuild de geração) 11/385 em
  MariaDB isolada, com 243 depreciações Core/contrib/PHPUnit. Provider v4 e
  fingerprint não mudaram; ativação/rebuild no Runtime continuam pendentes.
- Revalidação R2 no HEAD: Kernel focal de rotas, CTA e grants 3/141, aprovado.
  Preflight read-only em `toca` confirmou Drupal 11.4.10, Wiki ativa, Index
  desativado, `/` 200, `/wiki` 404 e listagem/busca/prévia 200; sem alteração
  persistente no Runtime.
- Smoke Firefox ESR visível WE SATIVA adicionado. Execução no host foi bloqueada
  porque a rota/provider e `aculta_wiki.contributor_workspace` não estão no
  container ativo; 0/2 verificações. Sem `drush cr`/Runtime write, a interface
  visual segue sem homologação. Script limita-se a GET e não envia formulários.
- Regressão da fila editorial de revisões ampliada: 26 candidatos sem grant não
  ocultam nem deslocam 26 revisões autorizadas paginadas em 25+1. A segunda
  página termina sem cursor e todos os itens são as revisões atuais. Kernel
  MariaDB focal: 1/101; 198 depreciações Core/contrib e 2 PHPUnit.

## 0.1.0-dev.76 — submissão real da imagem pelo Form API

- A regressão Kernel agora constrói e submete o formulário nativo de Node da
  Wiki com `managed_file`, descrição alt e estado `draft`. Comprova Media
  despublicada, arquivo privado/permanente e ausência de download anônimo.
- A leitura do uploader aceita o formato processado de `managed_file` (`fids`)
  e continua aceitando listas de IDs usadas pelos consumidores diretos.
- `WikiLeadImageLifecycleKernelTest` passou em MariaDB: 23 testes / 1.036
  assertions; 291 depreciações Core/contrib e 24 PHPUnit. PHP lint e PHPCS
  passaram. Runtime não foi alterado.

## 0.1.0-dev.75 — revalidar integração Content Index

- No HEAD após dev.74, os Unit dos providers Wiki/categoria passaram (9 testes /
  40 assertions; 3 depreciações PHPUnit).
- Os Kernels MariaDB `ContentIndexWikiProviderKernelTest`,
  `ContentIndexWikiAccessKernelTest` e `ContentIndexOwnerIntegrationKernelTest`
  passaram em conjunto (10 testes / 343 assertions; 240 depreciações, em sua
  maioria Core/contrib). O boot real continua descobrindo os providers Wiki e
  Image, validando relações e Entity Access.
- Nenhum provider/fingerprint mudou. O Index no Runtime não foi ativado e nenhum
  rebuild do inventário foi executado; esses gates dependem de homologação
  operacional separada.

## 0.1.0-dev.74 — semântica da prévia de busca como navegação

- O container da prévia recebe `role=region`, nome acessível e `aria-live`;
  o input conserva seu papel nativo de searchbox.
- `search-preview.js` não declara combobox nem captura setas: os resultados são
  links de navegação percorridos com Tab; o formulário GET permanece o fallback.
- Unit de `WikiSearchFormBuilderTest`: 4 testes / 18 assertions; PHP lint e
  `node --check` passam. A fixture Firefox do JS do worktree passou 14/14. O
  host com código antigo passou 28/35: role combobox, região sem nome e submit
  fora da viewport seguem visíveis até atualizar o Runtime.

## 0.1.0-dev.73 — nome acessível computado pelo Firefox

- O smoke consulta o nome acessível com WebDriver `computedlabel` para o campo
  de busca e o botão submit, usando o algoritmo implementado no Firefox ESR.
- No host de teste, os nomes foram `Pesquisar na Wiki420` e `Buscar`. O CSS
  candidato passou 22/22; o CSS agregado continua 18/20 por overflow horizontal
  no submit. Runtime permaneceu somente leitura.

## 0.1.0-dev.72 — caracterizar indicador de foco e nome acessível

- O smoke compara outline e box-shadow do controle focado com uma cópia do
  mesmo elemento sem foco; sombra normal do campo não é aceita como indicador.
- Confirma nome acessível resolvido do campo (`Pesquisar na Wiki420`) e do
  submit (`Buscar`), e inclui os estados do campo/botão no resumo JSON.
- Firefox ESR visível: folha candidata 22/22; agregado Runtime continua 18/20
  por extrapolar o submit horizontalmente em desktop e mobile. Nenhuma alteração
  de Runtime, além da observação read-only, foi feita.

## 0.1.0-dev.71 — smoke de teclado da busca em viewport móvel

- Adicionado `tests/browser/aculta-wiki/keyboard.py`, com WebDriver compartilhado
  e Firefox ESR visível; não envia a busca nem grava dados.
- O foco deve ter indicador visível, e campo/submit precisam caber na viewport.
  O CSS agregado do Runtime de teste falha (18/20): submit em x=336 a 320 px e
  x=1282 a 1280 px. A folha candidata carregada por GET passou 22/22. O
  agregado do Runtime não foi atualizado nem homologado.

## 0.1.0-dev.70 — impedir sobrescrita concorrente no editor WE SATIVA

- Formulários de criação e edição usam o wrapper proprietário da Wiki; o
  formulário de edição agora também recebe o hook específico do Core.
- A revisão-base é enviada no Form API e conferida na validação. No salvamento,
  uma transação bloqueia a linha do Node (`FOR UPDATE`) e confere novamente a
  revisão antes de persistir, serializando com decisões de propostas.
- Kernel real prova rejeição de formulário obsoleto e corrida entre validação
  e submit (1/37; 203 depreciações). O fluxo confirmado draft → review passou
  (1/67). Unit do uploader passou (12/79); PHPCS e PHP lint passaram.
- A classe Kernel completa de mídia e autorias passou: 22 testes / 995
  asserções, 291 depreciações Core/contrib e 23 depreciações PHPUnit.

## 0.1.0-dev.69 — rebuild Wiki v4 em Kernel proprietário

- Adicionado `WikiContentIndexRebuildKernelTest`, que inicializa os módulos
  reais Wiki + Image + Portal + Index e descobre os owners por tag/container.
- Uma geração isolada percorre o inventário semeado, compara fingerprint,
  confirma `provider_version` 4 sem `owner_uid`, deduplica `related_entry` e
  projeta categoria/imagem; valida e ativa a geração no banco dedicado.
- MariaDB: 1 teste / 42 asserções, aprovado; 201 depreciações Core/contrib.
  Isso não é rebuild do inventário do Runtime nem valida instalação completa.

## 0.1.0-dev.68 — revalidar integração Content Index

- Reexecutados os dois testes Unit dos providers Wiki/categoria: 9 testes / 40
  asserções, 3 depreciações PHPUnit.
- Reexecutados os Kernels reais do Index: provider (1/17), acesso e relações
  (7/238; 201 depreciações) e owner/bootstrap (2/88; 243 depreciações), todos
  aprovados em MariaDB dedicada.
- Sem mudança no contrato v4 e sem rebuild integral do inventário ou alteração
  no Runtime. A geração owner de teste usa somente identidades semeadas.

## 0.1.0-dev.67 — validar reflow da busca e páginas públicas

- Criada suíte visual reutilizável Firefox ESR (`tests/browser/aculta-wiki/`).
  No host público do Runtime de teste, home, listagem e busca foram verificadas
  em 1280, 390 e 320 CSS px; 46/46 checagens passaram e `/wiki` continuou 404.
- A primeira rodada identificou overflow no submit do formulário de busca do
  cabeçalho. A correção está em `aculta420` 0.6.1-dev.5: `width: auto` remove o
  conflito com `.form-control`. Com a folha atual carregada por GET somente no
  navegador, a segunda rodada confirmou `scrollWidth == clientWidth` em todas
  as páginas/larguras.
- Teste sem login e sem escrita no Runtime. O agregado do tema no Runtime não
  foi atualizado; Olivero e acessibilidade completa permanecem pendentes.

## 0.1.0-dev.66 — fechar limites de decisão de propostas

- Kernel testa rejeição de nota editorial acima de 5.000 caracteres e que uma
  proposta retirada não pode ser decidida/reaberta; os registros e revisões
  existentes permanecem intactos.
- A mesma cobertura confirma conflito quando a revisão-base do verbete ficou
  obsoleta, aceitação pelo Form API com subconjunto aprovado, modos individuais
  append/replace e transição oficial de publicação.
- Focal MariaDB: 1 teste / 159 asserções, 201 depreciações Core/contrib e 2
  PHPUnit. PHP lint, PHPCS Drupal/DrupalPractice passaram. Concorrência entre
  processos PHP permanece como cobertura planejada.

## 0.1.0-dev.65 — restringir rotas públicas ao purpose Wiki

- `/wiki`, `/wiki/busca` e `/wiki/busca/previa` declaram
  `_aculta_domain_purpose: wiki`. Antes, esses caminhos podiam escapar da
  validação de finalidade no host principal.
- Kernel usa o `DomainPurposeRequestSubscriber` real e comprova 404 para a
  busca Wiki no domínio `main`, além dos contratos de raiz pública `/` e caminho
  interno `/wiki`. Focal: 1 teste / 73 asserções; PHPCS e PHP lint passaram.
- A mudança é somente no ownership de rota da Wiki; nenhum arquivo do Portal ou
  Runtime foi alterado.

## 0.1.0-dev.64 — testar raiz pública e caminho interno em Kernel

- O Kernel de rotas agora inicializa o `PurposeFrontPathProcessor` real: a raiz
  Wiki `/` reescreve para `/wiki` com o marcador de raiz servida; `/wiki` direto
  não recebe o marcador e a `FrontPathPolicy` mantém o 404 interno.
- Focal MariaDB: 1 teste / 65 asserções, com 199 depreciações Core/contrib e 2
  PHPUnit. PHP lint e PHPCS Drupal/DrupalPractice passaram.
- Cobertura restrita ao submódulo Wiki; nenhuma alteração no Portal ou Runtime.

## 0.1.0-dev.63 — revalidar ciclo de vida da imagem

- A classe Kernel completa de imagens passou no MariaDB isolado: 21 testes /
  941 asserções, 291 depreciações Core/contrib e 22 depreciações PHPUnit.
- A cobertura confirma Media privada em verbete não publicado, publicação e
  retirada sincronizadas com o Node, imagem compartilhada por outro verbete
  publicado e bloqueio de download quando restam apenas referências históricas.
  Rollback, retries e limpeza de órfãs também passaram.
- Nenhum código ou estado Runtime mudou.

## 0.1.0-dev.62 — revalidar rotas e acesso em Kernel

- Reexecutados os cenários Kernel de discovery das rotas/listagem, acesso ao
  CTA de criação e busca com grants diferentes no mesmo papel. Passaram 3
  testes / 127 asserções em MariaDB isolada; 283 depreciações de Core/contrib/
  PHPUnit foram reportadas.
- O controller já aplica Entity Access, contexto `user.node_grants:view` em
  buscas positivas/vazias/negativas/malformadas e `createAccess()` cacheável.
  Nenhuma alteração de código ou Runtime foi necessária nesta regressão.

## 0.1.0-dev.61 — revalidar integração Wiki com Content Index

- No HEAD após as mudanças de interface WE SATIVA/GanjaLab, a suíte Unit Wiki
  passou: 66 testes / 341 asserções, com 3 depreciações do PHPUnit.
- Os três Kernels de provider, acesso Wiki e bootstrap owner real passaram em
  MariaDB isolada: 10 testes / 343 asserções, com 243 depreciações de Core,
  contrib e PHPUnit. Providers reais foram descobertos e relações autorizadas
  foram consultadas; projeção v4 inalterada.
- Nenhum arquivo de `aculta_index` foi alterado. Não houve rebuild, ativação ou
  operação no Runtime; a geração do Index segue sem homologação integral.

## 0.1.0-dev.60 — compor revisão de propostas no GanjaLab com SDC

- A tela de decisão usa `context-header` e `content-section` existentes para
  identificar a proposta, abrir links autorizados ao verbete/revisão-base e
  revisar contribuição, valores, justificativa e fontes. Controles decisórios
  seguem no Form API sob propriedade da Wiki.
- Kernel focal passou com 1 teste / 156 asserções; classe completa: 21 testes /
  941 asserções e 291 depreciações Core/contrib/PHPUnit. PHPCS e PHP lint
  passaram. Firefox visual e Runtime não foram homologados.

## 0.1.0-dev.59 — preservar modos de lista no Form API do GanjaLab

- O container `list_modes` agora preserva valores no Form API; sem `#tree`, as
  escolhas individuais não chegavam ao serviço que aplica a proposta.
- Kernel constrói e valida o formulário real, aplica uma proposta via Form API
  e confirma escolhas append/replace distintas para referências, categorias e
  relações (focal: 1 teste / 154 asserções). A classe Kernel completa passou:
  21 testes / 939 asserções, com 291 depreciações Core/contrib/PHPUnit.
  PHPCS e PHP lint passaram.

## 0.1.0-dev.58 — preservar valores editoriais no Form API

- `field_values` agora declara `#tree`, alinhando a estrutura processada pelo
  Form API ao contrato que o submit usa para extrair os campos escolhidos.
- Kernel constrói o formulário via `FormBuilder`, verifica `#parents`, executa
  validação/submit e confirma a persistência do título selecionado (1 teste /
  59 asserções). A classe Kernel completa passou: 21 testes / 935 asserções,
  com 291 depreciações Core/contrib/PHPUnit. Runtime não alterado.

## 0.1.0-dev.57 — apresentar erros de revalidação no formulário

- Rejeições de valores editoriais detectadas no backend aparecem como erro
  geral da submissão, em vez de serem associadas incorretamente ao resumo da
  proposta. Validações Form API e revalidações do serviço permanecem ativas.
- Kernel focal prova rejeição de Media inexistente sem persistir uma proposta:
  1 teste / 150 asserções. PHPCS e PHP lint passaram.

## 0.1.0-dev.56 — compor status de proposta com SDC

- A página privada de status da proposta usa `context-header` e `content-section`
  existentes. Situação, sugestão, justificativa, verbete relacionado, retorno,
  fontes e histórico mantêm os dados e o acesso no módulo Wiki.
- A ação de retirada aparece no slot de ações somente para a autoria em estado
  `submitted` ou `needs_changes`; o Form API mantém a confirmação e revalidação.
- Kernel focal passou: 1 teste / 148 asserções; PHPCS, PHP lint e diff check
  passaram. Firefox visual e Runtime não foram homologados.

## 0.1.0-dev.55 — revalidar integração Wiki com Content Index

- Reexecutados os dois testes Unit dos providers Wiki e categoria: 9 testes /
  40 asserções. Três classes Kernel do Index, incluindo bootstrap real Wiki +
  Portal + Index, passaram em MariaDB isolada: 10 testes / 343 asserções, 243
  depreciações de Core/contrib/PHPUnit.
- Nenhum código de projeção mudou; provider segue v4 e não exige rebuild por
  esta entrega. Runtime, configuração e geração ativa não foram alterados.

## 0.1.0-dev.54 — compor Meu Trabalho com SDCs existentes

- O workspace WE SATIVA usa `context-header`, `content-section`, `content-grid`,
  `card` e `empty-state` existentes no tema ACULTA420. A Wiki mantém a consulta,
  autorização, Domain e paginação; ações de criação/edição dependem das
  verificações de acesso existentes.
- Kernel focal: 2 testes / 89 asserções; classe completa MariaDB: 21 testes /
  923 asserções, com 291 depreciações upstream/PHPUnit. PHPCS e PHP lint
  passaram; visual Firefox ESR e Runtime não foram homologados.

## 0.1.0-dev.53 — percorrer limpeza de imagens em lotes rotativos

- A limpeza mantém um cursor expirável por sete dias e percorre os arquivos
  candidatos por ID em lotes de até 25. Referências antigas ou falhas deixam de
  bloquear indefinidamente os candidatos seguintes.
- Kernel confirma reuso da mesma Media por outro bundle Node, retenção quando a
  inspeção global falha, limite/lote rotativo e carência de uma hora. Os quatro
  cenários focais passaram (4 testes / 169 asserções); a classe Kernel completa
  passou com 21 testes / 918 asserções e 291 depreciações upstream/PHPUnit.
  Runtime não alterado.

## 0.1.0-dev.52 — validar os limites de acesso das relações do Index

- A integração Kernel real confirma que categoria fora do vocabulário owner da
  Wiki não aparece como destino outgoing e que Media publicada não revela a
  relação de um verbete em rascunho.
- `ContentIndexWikiAccessKernelTest`: 7 testes / 238 asserções; 201
  depreciações Drupal/Core/contrib. Nenhuma mudança de projeção ou rebuild.

## 0.1.0-dev.51 — limpeza segura de imagens órfãs

- O cron da Wiki examina até 25 arquivos permanentes antigos em
  `private://wiki/imagens/`, após retenção mínima de uma hora.
- Media e File são removidos somente pelos cleanups compartilhados do Portal,
  que revalidam referências globais em entidades e revisões. Falhas de inspeção
  mantêm o item; imagens ainda referenciadas por revisões antigas são retidas.
- Kernel MariaDB focal: 1 teste / 46 asserções. Confirmado: retenção durante
  referência histórica e remoção após excluir a última origem. 199 deprecações
  upstream/contrib; sem alteração no Runtime.

## 0.1.0-dev.50 — remover UID autoral da projeção do Index

- `WikiContentIndexProvider` não grava mais `owner_uid`; o ID de quem criou o
  verbete não é necessário à busca Wiki e não pertence à projeção pública.
- A projeção de Nodes sobe para v4 e muda o fingerprint do inventário. Uma
  geração nova precisa ser reconstruída antes da ativação; nenhuma reconstrução
  ou alteração no Runtime foi iniciada.
- Unit: 9 testes / 40 asserções; Kernel Wiki/Index: 7 / 236 e bootstrap owner
  real: 1 / 42; focal de persistência: 1 / 38. Gates genéricos recusam drift
  de fingerprint no backfill/ativação: 2 / 16. Depreciações upstream: 201 na
  classe Wiki/Index e 231 no owner.

## 0.1.0-dev.49 — revalidar relações no ciclo incremental real do Index

- A integração owner real comprova que atualizar `field_wiki_related` substitui
  a relação projetada e que excluir o Node remove sua projeção e relações.
- Kernel focal: 1 teste / 37 asserções; classe completa Index: 7 testes /
  234 asserções e 201 depreciações upstream. Provider continua v3; Runtime
  inalterado.

## 0.1.0-dev.48 — validar ciclo incremental real do Content Index

- O Kernel de integração real executa backfill da Wiki, confirma que o
  fingerprint da geração corresponde ao registry de providers, atualiza a
  projeção/hash após editar o verbete e remove a projeção após excluir o Node.
- Focal: 1 teste / 34 asserções; 200 depreciações upstream. Projeção v3 e
  relações existentes não mudaram; nenhum rebuild ou alteração de Runtime.

## 0.1.0-dev.47 — paginar Meu Trabalho na WE SATIVA

- Verbetes e propostas próprios têm cursores keyset independentes por
  `changed` e ID, ordenação estável e 20 candidatos por página. Entity Access
  continua revalidado por item no purpose Wiki; cursores não concedem acesso.
- Links GET preservam o outro cursor e usam o Domain da conta. Cache inclui
  `url.query_args:wiki_node_cursor` e `url.query_args:wiki_proposal_cursor`.
  Paginação funciona sem JavaScript.
- Kernel MariaDB focal combinado: 2 testes / 84 asserções; 200 depreciações
  Drupal/Core/contrib e 3 PHPUnit. Runtime não foi alterado.

## 0.1.0-dev.46 — validar revisão pendente e falha sem Domain

- No bootstrap real Wiki + Image + Portal + Index, revisão pendente não padrão
  é rejeitada pelo provider e a revisão padrão publicada continua projetável.
- A consulta do Index falha fechada quando o Domain canônico `wiki` não existe,
  sem cair no acesso do Domain ativo.
- A classe Kernel passou em MariaDB: 6 testes / 185 asserções; 201 depreciações
  Drupal/Core/contrib. Projection v3 sem alteração; nenhum rebuild ou Runtime.

## 0.1.0-dev.45 — provar providers e acesso com bootstrap real

- O Kernel transversal habilita a closure real de `aculta_wiki`, `aculta_image`,
  Portal e Index. Registro por `aculta_index.provider` confirma owner único para
  `node:wiki_entry`, `taxonomy_term:wiki_category` e `media:image`.
- Consultas reais testam `related_entry`, `wiki_category` e `wiki_image`, com
  Entity Access para leitores com grants distintos e restauração do Domain.
  A ponte de fixture para registrar providers e política Portal foi removida.
- MariaDB isolada: classe focal 4 testes / 125 asserções; 201 depreciações
  Drupal/Core/contrib. O teste não faz rebuild integral nem valida Runtime.
  A projeção permanece v3; nenhuma reconstrução foi iniciada.

## 0.1.0-dev.44 — edição própria e prévia na WE SATIVA

- Workspace só oferece “Editar” após validar `Node::access('update')` para a
  conta atual no purpose `wiki`; a URL nativa `entity.node.edit_form` é gerada
  no domínio Wiki. O resultado de acesso participa dos metadados de cache.
- Kernel com conta `email_confirmed` verifica acesso de edição próprio e negação
  a outra conta. O formulário Core oferece preview; o handler nativo guarda a
  entidade temporária no tempstore, permite visualização apenas à autora e não
  altera a revisão salva.
- Focal MariaDB: 2 testes / 111 asserções; 203 depreciações Drupal/Core/contrib
  e 3 do PHPUnit. Runtime não foi alterado.

## 0.1.0-dev.43 — sincronizar download de imagem com verbetes publicados

- Hook `file_download` nega arquivos `private://wiki/imagens` sem referência
  atual a um `wiki_entry` publicado. Uma revisão histórica pode continuar a
  preservar o File no disco sem manter o download público. Referências atuais
  compartilhadas continuam válidas enquanto houver verbete publicado.
- Ao trocar ou remover a imagem, a Media anterior é reconciliada com os Nodes
  publicados que ainda a referenciam. O formulário preserva File/Media usados
  por revisões antigas; limpeza física continua fora deste fluxo.
- Kernel MariaDB: subconjunto dos sete cenários de imagem, com data provider,
  passou em 8 testes / 237 asserções; 203 depreciações Drupal/Core/contrib e 9
  do PHPUnit. PHP lint, PHPCS Drupal/DrupalPractice e `git diff --check`
  aprovados. Runtime não foi alterado.

## 0.1.0-dev.42 — salvar rascunho e enviar revisão pelo Form API nativo

- O workflow `wiki_editorial` agora permite a transição `create_new_draft` a
  partir de `draft`, além de `published`. Isso disponibiliza o estado inicial
  `draft` como destino selecionável, sem mudar a permissão já exportada.
- O Kernel submete o formulário Core: primeira gravação cria Node não publicado
  em `draft`; novo submit cria revisão em `review` pela transição
  `draft_to_review`. A autora continua sem permissão/opção para publicar.
- Focal MariaDB: 1 teste / 54 asserções; 201 depreciações Core/contrib e 2 do
  PHPUnit. Config Sync foi atualizado, sem importação no Runtime.

## 0.1.0-dev.41 — construir formulário nativo para autoria confirmada

- Kernel carrega o workflow `wiki_editorial` e constrói o formulário Core de
  `wiki_entry` para a conta confirmada. Confirma campos de conteúdo, ação de
  salvar, transição `draft_to_review` disponível e opção de publicação ausente.
- Focal MariaDB: 1 teste / 38 asserções; 199 depreciações Core/contrib e 2 do
  PHPUnit. O teste não envia o formulário nem persiste a revisão.

## 0.1.0-dev.40 — route de criação da WE SATIVA no domínio Wiki

- O Kernel confirma que a rota nativa `node.add` permite criação à conta
  confirmada com a permissão da Wiki. O link “Criar verbete” gerado no
  workspace do Studio conserva os parâmetros da rota e aponta explicitamente
  para o domínio Wiki, mesmo com o Domain ativo em `main`.
- Focal MariaDB: 1 teste / 41 asserções; rota pública/listagem + CTA: 2 testes
  / 81 asserções; busca/grants/cache: 1 teste / 40 asserções. Depreciações
  Core/contrib registradas; a classe Kernel completa não foi reexecutada.
- A construção e o envio completos do Node Form API seguem como próximo gate.

## 0.1.0-dev.39 — primeira tela WE SATIVA no ACULTA Studio

- Adicionado o provider WE SATIVA em `/studio/we-sativa`, no purpose `account`.
  A Wiki mantém acesso, consulta de verbetes/propostas próprias e geração das
  ações no purpose `wiki`; Studio só encaminha a experiência.
- A tela consulta até 20 verbetes próprios e 20 propostas próprias por UID,
  revalida Entity Access por item e varia cache por conta, permissões e domínio. O link
  de criação só aparece quando `createAccess('wiki_entry')` autoriza.
- Kernel do módulo Wiki cobre rota/purpose, conta autorizada, negação a conta
  sem trabalho, isolamento entre duas autorias e restauração do purpose (focal:
  1 teste / 37 asserções; classe: 15 testes / 612 asserções em MariaDB isolada;
  286 depreciações Drupal/contrib e 16 PHPUnit). Integração visual e formulários
  de criação/edição seguem pendentes.
- O fixture de grants usado pelos Kernel recebeu seu `FieldConfig` ausente; o
  teste de busca entre autores agora valida de fato os grants distintos em vez
  de passar por um campo de grant descartado no save.

## 0.1.0-dev.38 — falha de validação antes do upload

- Kernel executa o validador real com imagem e descrição alternativa vazia.
  O FormState registra o erro, nenhuma Media é criada e o File continua
  temporário. A integração completa do Form API que interrompe o submit segue
  pendente na matriz.
- Focal: 1 teste / 27 asserções; classe completa em MariaDB isolada:
  14 testes / 575 asserções, 286 depreciações Core/contrib e 15 PHPUnit.
- Runtime não foi alterado. Acesso ao arquivo por revisão publicada histórica
  continua pendente de decisão.

## 0.1.0-dev.37 — sincronizar a Media em mudanças do verbete

- Hooks de inserção, atualização e exclusão sincronizam o status da Media de destaque
  com o estado publicado do verbete. Uma referência em outro Node publicado
  mantém a Media pública; a última referência publicada removida a torna
  privada.
- Kernel cobre publicação, despublicação, exclusão do último verbete e reuso
  da mesma Media por dois verbetes. Focais: 2 testes / 56 asserções; classe
  completa em MariaDB isolada: 13 testes / 548 asserções, 286 depreciações
  Core/contrib e 14 PHPUnit.
- Teste confirma que Core ainda permite baixar o arquivo quando uma revisão
  publicada anterior o referencia, embora a Media atual esteja privada. A
  decisão de produto sobre esse acesso histórico foi resolvida em dev.43 com
  negação do hook `file_download` quando não há referência pública atual.

## 0.1.0-dev.36 — caracterizar confirmação real de autoria

- Kernel inicializa o Mail Confirmer e confirma uma conta pelo fluxo de hash do
  realm `aculta_registration`; o hook real do Portal marca a conta confirmada
  e concede `email_confirmed`. Entity Access autoriza `create wiki_entry`
  após renovar o cache do handler, como numa requisição nova.
- O cenário instala o perfil/nickname que `aculta_user` exige no hook de
  criação de conta. Focal: 1 teste / 33 asserções; classe Kernel completa em
  MariaDB isolada: 11 testes / 492 asserções, 286 depreciações Core/contrib e
  12 PHPUnit.
- Evidência cobre o bootstrap e o acesso no teste; a configuração e o Runtime
  continuam sem homologação por este cenário.

## 0.1.0-dev.35 — negar aceite sem transições oficiais

- Kernel verifica que um moderador com permissão de editar o verbete, mas sem
  as permissões das transições `wiki_editorial`, não consegue aceitar uma
  proposta. Proposta, revisão e campos permanecem sem alteração.
- Focal: 1 teste / 144 assertions. A classe Kernel completa passou em MariaDB
  isolada: 10 testes / 459 assertions; 286 depreciações Core/contrib e 11 do
  PHPUnit.

## 0.1.0-dev.34 — testar conflito de revisão-base editorial

- Kernel cobre aceitação de proposta após uma edição concorrente do verbete.
  A decisão retorna conflito e preserva a proposta enviada, a revisão mais
  recente do verbete e seu título. A validação e as permissões existentes
  permanecem sob o serviço editorial real.
- Focal: 1 teste / 140 assertions. A classe Kernel completa passou em MariaDB
  isolada: 10 testes / 455 assertions; 286 depreciações Core/contrib e 11 do
  PHPUnit.

## Verificação R1 — Content Index — 2026-10-23

- Confirmado o contrato `WikiContentIndexProvider` v3: `node:wiki_entry`,
  revisão padrão, purpose `wiki`, metadados allowlisted e relações nomeadas
  `related_entry`, `wiki_category` e `wiki_image`. O provider de categoria é
  da Wiki; o provider de Media continua em `aculta_image`.
- Os três Kernel do Index/Wiki passaram em MariaDB isolada: 5 testes / 147
  asserções; a closure real Wiki + Index compilou e descobriu o provider.
- Em dev.166, o Kernel de owner passou a verificar a projeção real e o
  `ContentIndexAccessFilter` com bootstrap integral Wiki + Portal + Index:
  acesso `main`/`wiki`, filtros de origem e alvos privados, restauração do
  Domain (1 teste / 34 asserções; 231 depreciações upstream). A consulta
  outgoing agora também é consultada via `ContentIndexRelationQuery` de produção
  com providers reais descobertos no mesmo bootstrap (dev.167; owner: 2 testes /
  86 asserções). A geração de teste contém só as identidades necessárias e não
  equivale a rebuild/auditoria integral do inventário.
- Suíte reexecutada: 6 testes / 187 asserções, aprovada; 243 depreciações
  agregadas de Core/contrib/PHPUnit.
- `aculta_index` estava desativado no Runtime de teste; não houve rebuild,
  importação ou alteração de estado no Runtime.

## Verificação operacional — 2026-10-23

- O Runtime de teste estava com `aculta_wiki` ativo e `aculta_index`
  desativado. No host `wiki420`, `/` respondeu 200 e `/wiki` 404 conforme a
  política multidomínio: `/wiki` é caminho interno usado para servir a raiz.
  `/wiki/verbetes`, `/wiki/busca` e `/wiki/busca/previa` responderam 200.
- Verificação read-only; nenhuma configuração, cache ou banco foi alterado.
  Homologação integral permanece pendente.

## 0.1.0-dev.33 — retry seguro de upload após rebuild

- Rollback invalida caches de File e Media e limpa a chave temporária no
  FormState. Um retry do mesmo upload passa a carregar o arquivo temporário e
  criar uma nova Media dentro da transação nova.
- Kernel cobre rebuild/rollback, reenvio do mesmo arquivo, publicação final e
  ausência de Media órfã. Focal: 1 teste / 33 asserções; classe Kernel: 10
  testes / 447 asserções; Unit do uploader: 12 testes / 79 asserções.
- Depreciações upstream registradas. Runtime desabilitado.

## 0.1.0-dev.32 — modos de lista em categorias e relações

- Kernel prova a escolha por proposta: categorias acrescentadas com
  deduplicação e depois substituídas; relações substituídas e depois
  acrescentadas com deduplicação.
- Submissão revalida categoria e verbete relacionado visível no domínio Wiki;
  aplicação revalida destino publicado, idioma/Domain e acesso antes da revisão.
- Focal: 1 teste / 132 asserções; classe Kernel: 9 testes / 414 asserções.
  Depreciações upstream registradas. Runtime desabilitado.

## 0.1.0-dev.31 — modos de lista em propostas

- Kernel aplica duas propostas sucessivas para demonstrar que `append` preserva
  valores atuais e deduplica URLs, e `replace` substitui a lista integralmente.
- Focal: 1 teste / 122 asserções; classe Kernel: 9 testes / 404 asserções.
  Categorias e relações seguem validadas na submissão/aplicação, com cenários
  Kernel específicos pendentes. Depreciações upstream registradas.

## 0.1.0-dev.30 — regressão Kernel de rotas e acesso

- Kernel confirma uma única rota para `/wiki`, `/wiki/busca`,
  `/wiki/busca/previa` e `/wiki/verbetes`, e verifica acesso/purpose `wiki` da
  View de listagem sob bootstrap real do módulo.
- Busca no Kernel cobre grants divergentes por conta, resultados negativos,
  termo vazio e parâmetro `q[]` malformado; contexts preservam
  `user.node_grants:view`. Focal: 2 testes / 93 asserções.
- Runtime continua desabilitado; nenhum import/configuração persistente foi
  executado.

## 0.1.0-dev.29 — aplicação editorial de propostas

- Autoria seleciona campos editoriais e envia valores tipados para proposta.
  Na aceitação, a equipe escolhe o subconjunto aplicado e, por campo de lista,
  define substituir ou acrescentar; itens repetidos são deduplicados.
- Aplicação revalida o domínio, a revisão-base, Entity/Field Access, referências
  e permissões de cada transição `published → draft → review → published`.
  A proposta e a nova revisão publicada são salvas na mesma transação.
- `proposed_fields` é revisionável e recebe update hook para instalações
  existentes. Runtime continua desabilitado; nenhuma atualização foi aplicada.
- Kernel focal: 1 teste / 119 asserções; classe completa: 9 testes / 393
  asserções. Há depreciações upstream do Core/contrib, sem falhas de teste.

## 0.1.0-dev.28 — retirada de proposta pela autoria

- A autoria pode retirar propostas em `submitted` ou `needs_changes` por Form
  API e confirmação explícita. O backend valida autoria, Entity Access, estado e
  revisão esperada, e grava `withdrawn` numa nova revisão privada.
- A retirada limpa a nota exibida como retorno atual, mas mantém a revisão
  anterior no histórico. Kernel focal: 1 teste / 110 assertions. Runtime
  desabilitado. Classe Kernel completa: 9 testes / 384 assertions.

## 0.1.0-dev.27 — concorrência idempotente em MariaDB

- Kernel mantém o lock de idempotência por uma segunda conexão MariaDB enquanto
  tenta o replay. A operação concorrente responde 429 sem duplicar; após liberar
  o lock, o replay retorna a proposta original.
- Kernel focal: 1 teste / 99 assertions; classe completa: 9 testes / 371
  assertions. Prova lock entre conexões independentes; execução com processos
  PHP separados continua pendente. Runtime desabilitado.

## 0.1.0-dev.26 — idempotência da submissão

- Cada formulário tem token aleatório; submissões repetidas da mesma chave
  devolvem a proposta original por até 24 horas. Lock serializa os POSTs e a
  referência expira sem guardar o texto submetido.
- Salvamento, registro Flood e referência idempotente compartilham a transação
  do banco. Kernel focal: 1 teste / 97 assertions, incluindo replay direto e
  pelo Form API; classe completa: 9 testes / 369 assertions. PHPCS e lint
  passaram. Runtime desabilitado.

## 0.1.0-dev.25 — limite de submissão comunitária

- A submissão usa Flood do Drupal para limitar cada conta a 10 propostas por
  hora, alinhada ao limite de solicitações sociais do `aculta_user`.
- Ao exceder o limite, responde HTTP 429 antes de persistir a proposta. Kernel
  focal: 1 teste / 94 assertions; classe completa: 9 testes / 366 assertions.
  PHPCS, lint e validadores do Portal passaram. Runtime desabilitado.
- Idempotência para reenvio simultâneo do mesmo formulário continua pendente.

## 0.1.0-dev.24 — histórico privado de propostas

- A página privada da proposta mostra histórico paginado de revisões, com data,
  estado e retorno editorial. Cada revisão exige `view revision`; nomes de
  moderadores não são expostos.
- A consulta avança por cursor e limita candidatos por requisição. Cache varia
  por conta, permissões e cursor; decisões seguem armazenadas como revisões da
  entidade privada já existente.
- Kernel focal MariaDB: 1 teste / 93 assertions, cobrindo histórico de duas
  revisões e paginação de 21 revisões. PHPCS e lint dos arquivos alterados
  passaram. Runtime permanece desabilitado.

## 0.1.0-dev.23 — continuação da submissão de proposta

- Formulário de contribuição redireciona para `aculta_wiki.proposal_status` com
  o ID da proposta que acabou de salvar. A autoria chega à própria página de
  status sem precisar abrir o e-mail.
- Kernel focal cobre persistência, destino de redirect e vínculo ao registro
  recém-criado (1 teste / 85 assertions); PHPCS e lint passaram. A suíte Kernel
  completa não foi repetida após esta mudança.
- Runtime permanece desabilitado.

## 0.1.0-dev.22 — retorno privado à autoria

- Nova rota de status `/wiki/propostas/{proposal}` mostra proposta, estado,
  fontes e nota editorial somente conforme Entity Access do proprietário ou
  equipe editorial. O verbete relacionado só vira link se estiver acessível.
- `WikiProposalOutcomeNotifier` envia à fila opcional do Mailer um aviso para
  autoria ativa e com e-mail confirmado. Inclui estado, nota e URL canônica da
  página privada; não inclui o texto sugerido nem dados privados do verbete.
- `WikiProposalDecision` enfileira o retorno junto da transação de decisão.
  Sem Mailer, conta ativa/confirmada ou URL canônica, não tenta envio.
- Corrigidos os requisitos `_entity_access` das rotas de status e decisão para
  usar o parâmetro real `{proposal}`; Kernel confirma autoria permitida e outra
  conta negada, além do payload do aviso com fila em memória.
- Kernel MariaDB completo: 9 testes / 355 assertions; 286 depreciações
  Drupal/contrib e 10 do PHPUnit. Unit: 66 testes / 338 assertions; 3
  depreciações PHPUnit. Lint e PHPCS dos arquivos desta frente passaram.
- Runtime permanece desabilitado; nenhum e-mail foi enviado nesta validação.

## 0.1.0-dev.21 — paginação completa da fila de revisões

- `WikiEditorialQueue::entryRevisions()` percorre candidatos em lotes e
  revalida Domain, estado, revisão mais recente, Entity Access e transições.
- Um limite de scan mantém cada requisição finita. Quando filtragem de acesso
  reduz uma página, o cursor avança pelo último VID verificado para que itens
  autorizados mais antigos não desapareçam.
- Kernel cobre 26 revisões sem grant mais recentes que uma revisão visível; o
  item autorizado continua presente na página.
- Kernel MariaDB completo: 9 testes / 344 assertions; 284 depreciações
  Drupal/contrib e 10 do PHPUnit. PHPCS e lint do serviço/teste aprovados.
  Runtime segue desabilitado.

## 0.1.0-dev.20 — caracterização de rotas e acesso em Kernel

- Kernel da Wiki verifica novamente bootstrap real e discovery das três rotas
  públicas, seus nomes/caminhos e a negação quando `access content` é removida.
- Novo cenário exercita o CTA inicial com Entity API: conta autorizada e com
  e-mail confirmado recebe acesso; conta sem confirmação é negada. O resultado
  continua cacheável por permissões.
- Kernel MariaDB completo: 9 testes / 342 assertions; 284 depreciações
  Drupal/contrib e 10 depreciações PHPUnit. Unit: 66 testes / 338 assertions;
  3 depreciações PHPUnit.
- Sem alteração de Runtime; a Wiki permanece desabilitada.

## 0.1.0-dev.19 — moderação na WikiTab existente do Studio

- `WikiTab` oferece uma seção Moderação com gates separados: transições
  válidas + edição ampla para fila de revisões; `moderate wiki proposals` para
  fila de propostas. Moderadores de proposta não ganham as listagens genéricas
  de verbetes/revisões.
- `StudioListingBuilder` consome `aculta_wiki.editorial_queue` opcionalmente e
  renderiza páginas cacheáveis de propostas/revisões. A revisão passa por nova
  checagem de acesso à rota no purpose `wiki`; “Abrir para revisão” usa o
  formulário Drupal do Node/Content Moderation. Proposta abre o Form API Wiki,
  com texto, justificativa, fontes e links à revisão-base/verbete quando a rota
  autoriza acesso.
- A rota de decisão da proposta permanece no contexto account/GanjaLab. Nenhuma
  tabela, ACL, storage ou workflow de moderação foi criada no Studio. Runtime,
  Firefox/X11, acessibilidade visual e decisão/aplicação do aceite pendentes.

## 0.1.0-dev.18 — decisões editoriais revisionáveis

- `WikiProposalDecision` e `WikiProposalDecisionForm` permitem pedir ajustes ou
  recusar proposta com nota obrigatória em texto simples. Cada decisão grava
  nova revisão privada e conserva o estado anterior no histórico.
- Serviço exige `moderate wiki proposals`, confere revisão-base do formulário,
  estado pendente e acesso de atualização. Um lock `FOR UPDATE` na entidade
  serializa decisões concorrentes; submissão obsoleta recebe conflito.
- Aceite/aplicação do conteúdo segue indisponível até definir se o editor aplica
  manualmente em uma revisão do verbete. O Form API de decisão não tem rota nem
  integração Studio nesta entrega.
- Kernel focal verifica decisão, nova revisão, preservação da antiga, conflito
  obsoleto, nota obrigatória, recusa e negação a colaborador. Suíte Kernel
  MariaDB: 8 testes / 301 assertions, sem warnings; 284 depreciações Drupal/
  contrib e 9 PHPUnit.

## 0.1.0-dev.17 — acesso confirmado e link contextual

- `WikiProposalSubmission` exige confirmação de e-mail pelo contrato
  `EmailConfirmationPolicy` além da permissão específica; a checagem também
  protege o GET do formulário.
- `WikiProposalLinkHooks` adiciona o link no verbete público somente no purpose
  `wiki`, para contas confirmadas com permissão de propor e sem acesso de edição
  direta. Render cache varia por permissões, usuário e domínio e incorpora os
  resultados de Entity Access.
- Kernel usa o service hook real e comprova link para contribuinte habilitado,
  ausência de link para conta não confirmada, submissão negada para conta não
  confirmada com permissão e persistência para a confirmada. Kernel completo:
  8 testes / 290 assertions, sem warnings; 284 depreciações Drupal/contrib e
  9 PHPUnit.

## 0.1.0-dev.16 — envio de propostas por Form API

- Adiciona `/wiki/verbetes/{node}/contribuir`, protegida por `submit wiki
  proposals` e pelo acesso de visualização do Node. O formulário fornece tipo,
  texto simples, justificativa e até dez fontes HTTP(S) (uma por linha).
- A submissão chama `WikiProposalSubmission`, que revalida conta, verbete,
  publicação, propósito e revisão-base corrente. O formulário informa que a
  proposta não altera o verbete automaticamente. Não foi adicionada UI para
  moderar nem aplicar propostas; o significado operacional do estado `accepted`
  depende da definição do fluxo editorial.
- Kernel MariaDB cobre discovery/acesso da rota, construção do Form API,
  validação e submissão via callback; o serviço existente cobre acesso e
  persistência privadas.

## 0.1.0-dev.15 — consulta editorial autorizada

- `aculta_wiki.editorial_queue` fornece páginas de propostas pendentes e de
  revisões atuais em `review`, com cursores, cache metadata e acesso por conta.
- A fila de revisões descobre VIDs a partir dos registros de Content Moderation
  (cujo acesso de entidade é negado pelo Core), mas só retorna revisões atuais
  `wiki_entry` do domínio Wiki após revalidar `view`, `view revision`, `update`
  e transições válidas para a conta atual dentro do purpose `wiki`.
- Kernel real usa a política Portal `email_confirmed`, role editorial,
  workflow exportado e Domain principal para provar consulta e restauração do
  purpose. A fila de propostas cobre paginação 25+1, cursor malformado,
  permissão e cache. Nenhuma rota nem interface Studio foi adicionada.
- Validação MariaDB: `WikiLeadImageLifecycleKernelTest`, 8 testes / 274
  assertions, sem warnings; 284 depreciações Drupal/contrib e 9 PHPUnit.
  O módulo continua desabilitado no Runtime.

## 0.1.0-dev.14 — persistência segura de propostas

- Adiciona a entidade privada e revisionável `aculta_wiki_proposal` com vínculo
  ao verbete e à revisão-base, intenção allowlisted, texto proposto,
  justificativa, múltiplas fontes, estado e retorno editorial. Cada decisão posterior deve
  gravar nova revisão para manter histórico.
- Acesso separa envio, leitura própria, leitura editorial e moderação. A pessoa
  autora não pode atualizar nem excluir a proposta; moderadores obtêm acesso
  apenas pelas permissões dedicadas.
- `WikiProposalSubmission` exige `submit wiki proposals`, propósito `wiki`,
  verbete publicado e visível, revisão padrão e ausência de acesso direto de
  atualização. O texto é armazenado sem formato HTML; até dez fontes aceitam
  somente URLs HTTP(S).
- Kernel MariaDB exercita persistência, revisão-base, privacidade, acesso de
  moderador, purpose, recarga após edição concorrente, rejeição de URL insegura
  e negação de conta sem permissão (1 teste / 37 assertions, sem
  warning; depreciações Drupal/contrib permanecem).
- Suíte Kernel real completa: 7 testes / 225 assertions, sem warnings (284
  depreciações Drupal/contrib e 8 do PHPUnit). Unit: 66/338; PHPCS e PHP lint
  aprovados.
- Não há rota, formulário ou aplicação da proposta no Node nesta entrega. O
  módulo e Runtime continuam desabilitados; configuração de roles antecipa as
  capacidades para ativação futura. Política de retenção/expiração não foi
  escolhida e não há remoção automática de propostas.

## 0.1.0-dev.13 — testar rotas e grants com bootstrap real

- O Kernel inicializa os módulos reais da Wiki/Portal e descobre as três rotas
  públicas e a página da View `/wiki/verbetes`.
- A busca passa explicitamente a consulta o `current_user` como metadado de
  conta do Entity Query. Kernel exercita dois usuários da mesma função com
  grants de visualização distintos; cada um recebe apenas seu verbete. Busca
  vazia e sem resultados mantém `user.node_grants:view`.
- Acesso às rotas públicas exige `access content`; o teste verifica permissão
  concedida e negada. UID 1 não é usado como prova de autorização.
- A fixture de grants existe apenas em `tests/modules`; Runtime, configuração
  sincronizada e projeção v3 do Content Index não mudaram.
- Validação inicial: Kernel MariaDB 6 testes/152 assertions (1 warning do Core
  em `EntityStorageBase::loadMultiple()`, registrado para investigação); Unit
  66/338; PHPCS e PHP lint aprovados.

## 0.1.0-dev.12 — sincronizar visibilidade da imagem de destaque

- O upload guarda o arquivo em `private://wiki/imagens` e prepara o diretório
  pelo serviço de arquivos do Drupal.
- Media e permanência do File são preparadas em transação com o Node. A Media
  permanece despublicada durante a submissão e recebe o estado da revisão
  submetida imediatamente antes de salvar o verbete; erro ou rebuild reverte a
  transação.
- O formulário só substitui o handler de salvamento quando encontra o handler
  nativo do formulário de entidade, evitando transações sem callback de commit.
- Testes Unit e Kernel de MariaDB cobrem visibilidade de Media/arquivo em
  rascunho e publicação, rollback em falha e remoção preservando a referência
  histórica (Kernel atualizado: 4 testes / 92 assertions); resultados ficam na
  matriz.
- Sem mudança no provider do Content Index ou em sua projeção v3. Nenhuma
  configuração foi importada e Runtime não foi alterado.

## 0.1.0-dev.11 — caracterizar grants e criação de verbetes

- Busca e página inicial agora variam cache por `user.node_grants:view`, pois a
  query de resultados aplica `accessCheck(TRUE)` e a listagem recente revalida
  acesso por entidade.
- O CTA de colaboração usa `EntityAccessControlHandler::createAccess()` para
  `node:wiki_entry`; o `AccessResult` mantém seus metadados de cache no render.
- Rotas públicas e configuração não mudaram. Testes Unit e gates aplicáveis
  ficam registrados na matriz desta versão; não houve validação no Runtime.

## Auditoria R1 — 2026-10-23

- Handoff documentado: provider Wiki v3 cobre metadados allowlisted e relações
  `related_entry`, `wiki_category` e `wiki_image`; os providers de categoria e
  Media mantêm ownership na Wiki e em `aculta_image`, respectivamente.
- Confirmado no código e na evidência existente o boot combinado real de Wiki +
  Index, com discovery por tag e registry. Não repetido nesta sessão porque
  `SIMPLETEST_DB` não está configurada.
- Unit Wiki executado: 61 testes / 277 assertions aprovados, 3 depreciações
  PHPUnit. Sem mudança de projeção, portanto a versão permanece 3; rebuild de
  geração segue necessário após mudanças semânticas e não é disparado aqui.
- Runtime e geração do Index não foram alterados.

## 0.1.0-dev.10 — integrar categorias e imagens ao Content Index

- `WikiContentIndexProvider` agora projeta relações outgoing allowlisted:
  `related_entry`, `wiki_category` e `wiki_image`; a projeção passa à versão 3.
- Novo `WikiCategoryContentIndexProvider` indexa somente metadados mínimos de
  `taxonomy_term:wiki_category` no próprio owner `aculta_wiki`, com purpose
  canônico `wiki`. A dependência Core `taxonomy` foi declarada.
- Imagens apontam a `media:image` e usam o provider que já pertence a
  `aculta_image`; nenhum arquivo, URL, descrição de categoria ou corpo é copiado.
- Kernel MariaDB consulta as relações via Index com Node, Term e Media reais,
  e filtra categoria/imagem não publicadas por Entity Access (3 testes / 84
  assertions; 5 depreciações). Unit da Wiki: 61 testes / 277 assertions; 3
  depreciações PHPUnit. A Wiki permanece desabilitada no Runtime.

## 0.1.0-dev.9 — confirmar consulta de relações do Index em MariaDB

- Kernel do Content Index consulta `related_entry` com a classe do provider e
  a policy Entity Access de produção, usando Domains persistidos. A origem e os
  alvos são revalidados no purpose canônico `wiki`; alvo não publicado é
  filtrado e o Domain `main` é restaurado.
- O teste compila a declaração e a tag a partir do YAML owner, mas usa bridge
  fixture para a policy Portal; não ativa a cadeia completa de DI da Wiki.
- MariaDB dedicado: 2 testes / 32 assertions PASS; 5 depreciações do módulo
  Domain. A cobertura já estava aprovada em SQLite; categories/image continuam
  adiadas até finalizar contratos da Wiki.

## 0.1.0-dev.8 — atualizar evidência de acesso do Content Index

- Kernel `ContentIndexWikiAccessKernelTest` exercita o filtro real com a policy
  `EntityHooks`, provider de produção e Domains persistidos: nega no purpose
  `main`, autoriza em `wiki` e restaura o Domain anterior (1/16).
- O teste usa bridge de fixture e não ativa a cadeia completa da Wiki. O mapa de
  services valida separadamente o YAML/tag owner. Grants específicos,
  homologação Runtime e as relações de categorias/imagem continuam pendentes;
  estas duas relações permanecem adiadas até a finalização da Wiki.

## 0.1.0-dev.7 — registrar evidência Kernel do Content Index

- Harness Kernel do Index usa `WikiContentIndexProvider` real e cobre backfill,
  `related_entry` deduplicada, update de revisão padrão e delete (1/17).
- O módulo continua desabilitado; YAML owner, Domain/grants reais, Runtime e
  relações de categoria/imagem permanecem pendentes/adiadas.

## 0.1.0-dev.6 — documentar gates de integração do Content Index

- Registra a cobertura Unit existente do provider, sem tratá-la como prova de
  discovery DI ou autorização integrada.
- Acrescenta à matriz os Kernel e Runtime ainda pendentes; categorias e imagem
  permanecem adiadas até a finalização dos contratos da Wiki.
- Suíte Unit da Wiki executada: 57 testes, 258 assertions; 2 deprecações do
  PHPUnit observadas.
- A Wiki permanece desabilitada; nenhuma configuração ou estado do Runtime foi
  alterado.

## 0.1.0-dev.5 — versão da projeção no fingerprint do Index

- Provider Wiki declara sua versão de projeção para invalidar rebuilds
  parciais quando mudar a projeção de metadados ou relações.

## 0.1.0-dev.4 — relação outgoing de verbetes no Content Index

- `WikiContentIndexProvider` projeta `field_wiki_related` como relação nomeada
  `related_entry` para `node:wiki_entry`, deduplicada e unidirecional. Cada
  destino continua sujeito ao próprio provider e à revalidação de Entity
  Access no purpose canônico `wiki`.
- Categorias (`field_wiki_categories`) e imagem (`field_wiki_image`) continuam
  adiadas até a finalização de seus contratos. Testes Unit cobrem a allowlist e
  deduplicação; o módulo permanece desabilitado no Runtime.

## 0.1.0-dev.3 — acesso canônico do Content Index

- O provider da Wiki declara o purpose canônico `wiki`; consultas do Index
  vindas de outros domínios revalidam o Entity Access com o mesmo usuário e
  grants, falhando fechado se o domínio não puder ser resolvido.
- Teste Unit cobre o purpose declarado pelo provider. Categorias e imagens
  continuam dívida até a finalização da Wiki.

## 0.1.0-dev.2 — provider inicial do Content Index

- Serviço opcional `aculta_wiki.content_index_provider`, marcado com
  `aculta_index.provider`, projeta somente metadados allowlisted de
  `node/wiki_entry`: título, UUID, idioma, bundle, domínio, estado e datas.
- Corpo, categorias, imagem e todas as relações ficam como dívida técnica até
  a finalização da Wiki, conforme decisão do responsável. Não foi criada
  dependência obrigatória do submódulo `aculta_index`.
- Teste Unit cobre projeção, bundle e limite de revisão padrão. O módulo não foi
  habilitado no Runtime.

## Não lançado — busca e aviso de revisão — 2026-10-22

- O `wiki_entry` entra em `bundles.selected` do índice `aculta_conteudo`
  (`config/sync/search_api.index.aculta_conteudo.yml`), para que verbetes
  publicados apareçam na busca do site. A busca própria da Wiki
  (`WikiController::findWikiEntries()`) e as rotas `/wiki`, `/wiki/busca` e
  `/wiki/busca/previa` não mudam. O `SearchPageController` reaplica
  `access('view')`, então rascunho e revisão não aparecem.
- `WikiReviewHooks` (`entity_update`) avisa quando um `wiki_entry` **entra** em
  revisão, comparando o estado atual com `getOriginal()` — a propriedade
  `original` está depreciada desde o Drupal 11.2.
- `WikiReviewMailType` (`aculta_wiki_review`) declara as variáveis `verbete`,
  `estado` e `link`. `WikiReviewNotifier` enfileira uma mensagem por endereço
  editorial na fila do `aculta_mailer`, com `MailMessage` (UUID v4, `pt-br`) e
  contexto **sem o nome de quem editou**.
- Sem dependência de `aculta_mailer` em `info.yml`: a fila e a URL pública chegam
  como serviços opcionais (`@?aculta_mailer.queue`,
  `@?Drupal\aculta_mailer\Api\PublicUrlInterface`); sem o mailer, nada é enviado
  e nada quebra.
- Aviso desligado por padrão, sem destinatários. A tela
  `/painel-administrativo/aculta/wiki` (`aculta_wiki.settings`, permissão
  `administer aculta_wiki`, item de menu "Wiki420" no painel) liga o aviso,
  valida os endereços e salva a lista normalizada. A configuração não tem arquivo
  em `config/sync` nem `config/install`; os padrões vivem no código.
- Pacote LGPD no mesmo commit: linha `aculta_wiki` e pendência 3 do inventário,
  cláusula 5 das cláusulas de aceite, bullet da Wiki na política de privacidade,
  regra em `AGENT-CONTENT.md` e neste `AGENTS.md`.
- Testes: `WikiReviewNotifierTest` (6), `WikiReviewHooksTest` (8),
  `WikiReviewMailTypeTest` (3) e `WikiSearchIndexConfigTest` (1). Suíte do
  submódulo: `52 testes, 240 asserções, OK`.
- Deploy: verbetes já publicados só entram no índice após
  `drush search-api:index` no ambiente. Nenhuma operação de Runtime nesta sessão.

## Não lançado — upload da imagem de destaque — 2026-10-22

- O `field_wiki_image` deixa de ser um autocomplete de mídia no formulário do
  verbete. `WikiEntryImageHooks` (`form_node_wiki_entry_form_alter`) oculta o
  widget central e abre a aba **"Imagem de destaque"** dentro das abas verticais
  `advanced`: um `managed_file` em `public://wiki/imagens`, o campo de descrição
  (até 512 caracteres, como no Fórum), o resumo da imagem atual e a opção de
  removê-la.
- A aba consome o **consumidor `wiki` da política central**
  (`aculta_portal.user_upload_policy`): `UserUploadSettingsForm` ganhou a aba
  "Wiki", `config/sync/aculta_portal.user_upload.yml` ganhou `consumers.wiki`
  herdando a política global e `aculta_portal.schema.yml` ganhou o mapeamento.
  Extensões, tamanho e inspeção da imagem continuam vindo do mesmo serviço que
  o Fórum usa.
- `WikiLeadImageUploader` valida na submissão (um arquivo por envio, descrição
  obrigatória, imagem real dentro da política) e, antes de salvar o verbete, cria
  uma mídia `image` publicada — título = nome do arquivo, `field_media_image`
  com `alt` — e aponta `field_wiki_image` para ela.
- Decisão: **aba em vez de diálogo modal**. Um diálogo não teria como gravar no
  campo de autocomplete de um nó ainda não salvo sem repetir a mecânica AJAX da
  Media Library; a aba reusa o padrão do diálogo do Fórum sem esse acoplamento.
- Decisão: **substituir não apaga**. A mídia anterior fica no banco porque
  revisões publicadas podem apontar para ela; a limpeza fica para uma frente
  própria.
- A aba define `#tree => TRUE` explicitamente: `<details>` não sintetiza essa
  propriedade e, sem ela, os valores não chegariam sob `wiki_lead_image`.
- O uploader marca o arquivo como permanente antes de criar a mídia, porque o
  envio não passa pelo autocomplete que registraria o uso do arquivo e ele
  seria coletado como temporário.
- A aba só aparece quando a política do consumidor tem alguma extensão de
  imagem habilitada; caso contrário o widget central continua visível para não
  travar o campo.
- `aculta_wiki.info.yml` passa a declarar `drupal:file` e `drupal:media`.
- Testes: `WikiLeadImageUploaderTest` (10), `WikiEntryImageHooksTest` (6) e
  `WikiLeadImagePolicyTest` (3). Suíte do submódulo: `32 testes, 158 asserções,
  OK`. PHPCS `Drupal,DrupalPractice` limpo nos arquivos novos.
- Nenhuma operação de Runtime; nenhum cache limpo. A validação em navegador
  (ACULTA420 e Olivero) fica para depois da importação de configuração.

## Não lançado — imagem e campos no card da listagem — 2026-10-22

- O view mode `teaser` do `wiki_entry`
  (`config/sync/core.entity_view_display.node.wiki_entry.teaser.yml`) passa a
  renderizar `field_wiki_image` pelo mesmo formatador da página do verbete
  (`entity_reference_entity_view`, view mode `default`, sem link); o campo sai
  de `hidden`. A ordem do card fica imagem (peso 0), resumo (1) e categorias
  (2). Nenhum view mode de mídia novo e nenhuma configuração adicional.
- O tema exibe a imagem no card: `node--wiki-entry--teaser.html.twig` calcula
  `has_image` e repassa o campo ao SDC `aculta420:wiki-entry`, que já tinha os
  slots `image`; `wiki-entry.css` recorta em 16:9 (`aspect-ratio` +
  `object-fit: cover`) e a story `wiki-entry.teaser.story.yml` cobre o estado
  com imagem, resumo e categorias.
- Nenhuma URL muda (`/wiki`, `/wiki/verbetes`, `/wiki/busca`,
  `/wiki/busca/previa`). Os blocos `block_changes` e `block_recent` não foram
  tocados.
- `WikiTeaserConfigTest` (2 testes) trava o contrato: imagem sem link via
  entidade de mídia, ordem dos campos e `body`/relacionados/referências
  ocultos. Execução junto da suíte do módulo: `13 testes, 59 asserções, OK`.
  Validador de SDC: `PASS (41 components, 25 include calls, 610 checks)`.
- Nenhuma operação de Runtime; nenhum cache limpo. A renderização em navegador
  (ACULTA420 e Olivero) fica para depois da importação de configuração.

## Não lançado — contagem e paginação da listagem — 2026-10-22

- A view `wiki_entries` (`config/sync/views.view.wiki_entries.yml`) passa a
  contar e paginar a listagem pública. O display `page_1` (`wiki/verbetes`,
  caminho inalterado) usa pager completo de 12 itens e cabeçalho
  `@total verbetes publicados`, exibido inclusive quando não há resultados.
- Os blocos `block_changes` (5 itens) e `block_recent` mantêm o limite fixo,
  sem paginador; a troca fica restrita ao display da página.
- `url.query_args` entra nos contextos de cache da página para que `?page=N`
  não receba o cache da primeira página.
- `WikiListingConfigTest` (2 testes) trava o contrato: pager e contagem na
  página, blocos intactos. Execução: `2 testes, 13 asserções, OK`.
- Nenhuma operação de Runtime; nenhum cache limpo.

## Não lançado — testes do controlador e do formulário — 2026-10-22

- Cria testes unitários em `tests/src/Unit` para as duas classes movidas:
  `WikiControllerTest` (5 testes) e `WikiSearchFormBuilderTest` (4 testes).
  Execução: `9 testes, 34 asserções, OK`.
- Correção de robustez encontrada pelos testes: `$request->query->get('q', '')`
  lança `BadRequestException` (`Input value "q" contains a non-scalar value.`)
  quando a URL traz `q[]=...`. `WikiController::search()`,
  `WikiController::preview()` e `WikiSearchFormBuilder::build()` passam a ler
  `query->all()['q'] ?? ''` e só aceitam valor escalar; um `q[]` malformado
  deixa o campo vazio em vez de derrubar a requisição.
- Os testes rodam sem o módulo habilitado e sem banco; nenhuma operação de
  Runtime.

## Não lançado — rotas movidas — 2026-10-22

- `aculta_portal.wiki_home` (`/wiki`), `aculta_portal.wiki_search`
  (`/wiki/busca`) e `aculta_portal.wiki_search_preview` (`/wiki/busca/previa`)
  passam de `aculta_portal.routing.yml` para `aculta_wiki.routing.yml`,
  mantendo nomes de máquina, caminhos e permissões (`access content`). Nenhuma
  URL pública muda.
- Os nomes continuam com o prefixo `aculta_portal.` por serem contrato público
  citado em tema, testes e documentação; não foram renomeados.
- Enquanto o módulo `aculta_wiki` estiver desabilitado, as rotas não são
  registradas pelo Drupal. O Portal não as define mais; não há rota duplicada.
- Nenhuma operação de Runtime; nenhum cache limpo.

## Não lançado — controlador e formulário movidos — 2026-10-22

- `WikiController` e `WikiSearchFormBuilder` passam de `aculta_portal` para
  `aculta_wiki`, com os namespaces `Drupal\aculta_wiki\Controller` e
  `Drupal\aculta_wiki\Presentation` e os mesmos construtores. Nenhuma URL muda.
- O serviço `aculta_wiki.presentation.wiki_search_form` passa a ser definido em
  `aculta_wiki.services.yml`.
- O Portal cria `WikiSearchFormBuilderInterface` e injeta o serviço de forma
  opcional (`@?`). Sem o submódulo, `wiki_search` na apresentação é `NULL` e o
  restante da apresentação continua funcionando.
- As rotas do Portal apontam temporariamente para o controlador movido. A
  definição das rotas passa para `aculta_wiki.routing.yml` no commit seguinte.
- O módulo continua desabilitado; nenhuma operação de Runtime.
- `php -l` nos quatro arquivos alterados: sem erros de sintaxe.

## Não lançado — casca do submódulo — 2026-10-22

- Cria o manifesto `aculta_wiki.info.yml` (`0.1.0-dev.1`) com as dependências
  `aculta_portal`, `domain`, `views`, `node` e `content_moderation`. Sem
  dependência de `aculta_statistics`.
- Adiciona `AGENTS.md`, `README.md`, `docs/ROADMAP.md` e `docs/TEST-MATRIX.md`.
- Documento apenas: nenhum código, rota ou configuração foi movido e o módulo
  não foi habilitado. Nenhuma alteração de Runtime.
- Validação desta versão: `validate-own-module-packages` PASS (45 checagens) e
  `validate-public-slugs` PASS (380 rotas; 17 violações contribuídas na linha de
  base DT-P20).

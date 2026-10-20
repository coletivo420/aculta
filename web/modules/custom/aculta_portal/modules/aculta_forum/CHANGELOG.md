## 0.1.0-dev.38 — aposentar pastas editoriais do Forum antigo — 2026-10-19

- `aculta_magazine` 0.1.0-dev.14 agora mantém os updates 10001/10002 como no-op,
  remove `ForumSectionResolver` e deixa de instalar seu campo exclusivo na
  taxonomy `forums`. O update de aliases 10003 permanece para a próxima etapa;
  referências e Nodes antigos não foram alterados.
- Unit Magazine 8/32, PHPCS e gates Drupal 11+ (970), fronteira (1383), mapa
  (154) PASS. Runtime schema já era 10003; sem `updb`/config import. Cache
  rebuild, resolver ausente, lookup histórico presente, render de publicação e
  HTTP 200 após redirect PASS. PHP CLI 8.4.26 abaixo da baseline 8.5; comentários
  diretos reais/browser continuam pendentes. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.37 — preparar restore point da retirada — 2026-10-19

- Criado dump pré-limpeza do Runtime de teste `aculta_runtime`, privado fora do
  repositório, modo 0600; validação `gzip -t` PASS. URI explícita
  `https://revista420.aculta.toca.net.br`, checksum e limites registrados em
  `docs/MIGRATION-REMOVAL.md`. Não foi restaurado nem usado para alterar dados.
- O arquivo é um checkpoint de banco, não inclui arquivos públicos/privados.
  Nenhuma entidade ou configuração foi removida; uninstall permanece bloqueado
  até migrar consumers e fechar plano de recuperação de arquivos. RUNTIME STATUS:
  PARTIAL.

## 0.1.0-dev.36 — retirar hook residual do Magazine — 2026-10-19

- O `aculta_magazine` removeu o único hook restante ligado a Comments
  `comment_forum`; a suíte Unit confirmou 7 testes/30 assertions e o Runtime
  confirmou zero Comments antigos, render full de publicação e HTTP 200 após
  redirect. Os 12 vínculos e Nodes antigos permanecem intocados.
- Gatilhos de criação/sincronização já haviam sido removidos; agora não há
  hook de visualização/editorial executável nesse módulo que dependa do Forum
  contrib. Permanecem os serviços de leitura/conversão de vínculo até a migração
  dos dados. RUNTIME STATUS: PARTIAL; Comments diretos reais/browser pendentes.

## 0.1.0-dev.35 — remover consumidor editorial do Forum legado — 2026-10-19

- Retirado de `aculta_user` o fallback de resumo que consultava Comments do
  Drupal Forum. Os 12 Nodes de Revista ainda têm vínculos antigos, mas agora o
  conteúdo editorial usa Comment direto; a leitura histórica permanece apenas
  nos serviços de compatibilidade do `aculta_magazine` até a limpeza aprovada.
- Nenhum dado foi alterado no Runtime. `drush cr`, renderização full de uma
  publicação e GET à URL canônica passaram; o request `/node/90` redirecionou
  para a URL editorial e terminou em HTTP 200. Unit `aculta_user` 60/175,
  gate Drupal 11+ (970), mapa módulo/tema (154), diff-check e PHPCS PASS.
  Sem Comments reais para validar formulário/exibição de conteúdo; PHP 8.4.26
  abaixo da baseline 8.5. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.34 — revisão de fonte contrib e thread ramificada — 2026-10-19

- Inspecionado diretamente o tarball `comment_mover` 2.0.0-alpha1: a operação
  reatribui as entidades Comment existentes, mas a recursão de thread mantém a
  base original e a suíte publicada não cobre descendentes em múltiplos ramos.
  Nenhum código contrib foi copiado e o módulo não foi instalado.
- Kernel `ForumCommentMoverKernelTest` ganhou cenário com dois ramos e netos,
  verificando caminhos `thread`, parents e contagens após mover a árvore.
  MariaDB dedicado: 13 testes/272 assertions PASS; 19 depreciações de
  Core/Domain/Flag/Search API contrib.
- Consulta somente leitura ao Runtime `toca` confirmou 8 vínculos antigos em
  `article` e 4 em `blog`, todos com campo de discussão novo presente. Os Nodes,
  Comments, Composer, configuração ativa e conteúdo do Runtime não foram
  alterados. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.33 — mover comentário com subárvore — 2026-10-19

- Implementados serviço transacional, formulário de confirmação, rota e
  operação contextual para mover um comentário público e suas respostas
  descendentes entre tópicos abertos do Growroom. Revalida permissão, acesso,
  Domain e imutabilidade do acervo histórico na submissão.
- Preserva Comment IDs, UUIDs, autor, texto, datas e estado de moderação; atualiza
  parent/thread, recalcula estatísticas dos dois Nodes e invalida caches. Lock
  segue o padrão de chave Comment Core e relê o máximo da thread sob lock.
- Kernel MariaDB dedicado: 12 testes/246 assertions PASS (árvore em três níveis,
  estado pendente, legado bloqueado, estatísticas, flag disabled, rollback,
  formulário de resposta/remoção desatualizado e retenção do lock até o callback
  da transação raiz, verificada por uma segunda conexão MariaDB independente).
  Form API pelo `FormBuilder::submitForm()` confirma que a movimentação exige
  destino e confirmação explícita; com a caixa ausente, o envio é rejeitado e o
  tópico hospedeiro permanece. Persistem 19 depreciações de Core/Domain/Flag/
  Search API contrib. Runtime de teste `toca`: `drush status` confirmou Drupal
  11.4.10, URI main explícita e banco `aculta_runtime`; consulta somente leitura
  confirmou rota, permissão e serviço do mover, com `comment_move_enabled: null`
  (ausente e portanto fechado). GET ao formulário com Comment inexistente retornou
  404. Não há Comments publicados desse bundle no Runtime para testar rota com
  entidade real; nenhuma configuração do mover foi alterada. Rota com entidade
  existente, CSRF, concorrência entre processos e efeitos integrados permanecem
  DEFERRED; PHP 8.4.26 abaixo da baseline 8.5; RUNTIME STATUS: PARTIAL.
- Limpeza de homologação autorizada no Runtime: removidos pela Entity API os
  oito Nodes `[TESTE]` (IDs 383–390) do bundle novo, após confirmar títulos,
  bundle e owner uid 5, sem Comments, Flags ou referências editoriais. Os 12
  termos da árvore inicial foram preservados; resultado: 0 tópicos e 0 Comments
  novos. Os oito aliases Pathauto foram removidos e cada URL anterior retorna
  HTTP 404. O módulo Forum antigo, seus 30 Nodes, 8 termos `forums`, 12 vínculos
  da Revista e 0 Comments legados permaneceram intactos. Configuração não foi
  alterada. Inventário e evidência em `docs/TEST-FIXTURES.md`.
- Forum Unit: 51 testes/360 assertions PASS; PHPCS nos arquivos novos e gates
  Drupal 11+ (970), fronteira (1388), mapa (154), slugs (387) e hierarquia de
  papéis PASS. PHP CLI 8.4.26 (abaixo da baseline 8.5).
- Pesquisa contrib expandida: Forum Access (matriz de permissões), Comment Notify
  (preferências de acompanhamento), Achievements/Gamification (marcos e limites)
  e VBO (ações em lote) são referências; nenhum código/dependência foi copiado.
  Comment Moderation AI foi rejeitado como dependência por processamento externo
  e ausência de cobertura pela política de security advisories. Ver
  `docs/PESQUISA.md`.
- `ForumDiscussionWriteGuard` serializa comentário e movimento por tópico, com
  locks adquiridos em ordem estável, validação de pai/host após lock e liberação
  ao final da transação raiz. A fixture Kernel substitui `NullLockBackend` pelo
  `DatabaseLockBackend`; uma conexão MariaDB adicional não adquire a chave até a
  transação raiz encerrar e consegue adquiri-la após o commit. Isso prova o lock
  persistente entre conexões, mas não simula dois processos/requisições HTTP.
  Efeitos externos seguem pendentes. O mover reserva primeiro o lock de thread
  Core, respeitando `Comment::preSave()` para evitar inversão.
- Revisão dos hooks laterais: `aculta_game` Unit `ForumParticipationPolicyTest`
  (8/35) confirma que update de comentário já publicado não repete o gatilho de
  conquista. Messenger `ForumAvisoHooksTest` (2/6) confirma que mover comentário
  publicado não gera novo aviso e que aprovação ainda registra; entrega e fila
  real permanecem sem teste integrado. Runtime `toca`: cache rebuild e descoberta
  do serviço OOP PASS; nenhuma ação ou aviso foi submetido.
- Kernel também instala Flag 5.1.1 e Search API 1.41.0: flagging de usuário em
  Comment permanece ligada ao mesmo CID após a movimentação; um Comment indexado
  é marcado para reindexação após o update, mantendo o mesmo item ID. O índice
  atual `aculta_conteudo` só indexa Nodes dos bundles institucionais definidos e
  não inclui Forum nem Comments; o novo teste cria um índice Kernel isolado e
  não altera essa política de busca.
- Campo File privado no Comment também foi exercitado: o CID manteve a referência
  do anexo e seu `file_usage` após a mudança de tópico. O fixture usa arquivo de
  texto; ingestão e acesso de mídia ACULTA permanecem responsabilidade do
  `aculta_image` e não foram validados por esse teste.

## Pesquisa adicional de módulos contrib — 2026-10-19 (documentação)

- `PESQUISA.md` registra APIs e decisões para os contrib instalados Flag,
  CTools, History, Search API, Group e Message stack, com versões do lockfile.
- `comment_mover` foi avaliado como referência para ferramentas de movimento:
  não está instalado; a release disponível é alpha para Drupal 9/10. O tarball
  foi inspecionado: ele reatribui Comments in-place, mas a recursão não passa a
  thread recém-calculada ao mover descendentes profundos e não valida o contrato
  de acesso/estatísticas da ACULTA. Reutilizar só requisitos de
  seleção/subárvore/split; código e dependência não foram incorporados.
- `MOVE-TOOLS.md` registra origem, compatibilidade e limites. O bloqueio inicial
  foi substituído pelo protótipo independente em dev.33; validações de integrações
  ainda mantêm a ação fora do Runtime. Sem código contrib copiado ou dependência
  nova.
- A auditoria ampliada dos módulos contrib instalados e candidatos está em
  `MOVE-TOOLS.md`. CTools, Flag, History, Search API e Group permanecem donos de
  suas funções; padrões de Advanced Forum, Comment Mover e Achievements foram
  avaliados sem copiar código nem criar storage concorrente.

## 0.1.0-dev.32 — autoria da última atividade pública — 2026-10-19

- View pública usa `last_comment_uid` de `comment_entity_statistics` como campo
  oculto; hook OOP prepara o cartão via `aculta_user.author_card_builder`, sem
  expor o nome de login. Install e Config Sync devem permanecer equivalentes.
- Forum Unit 49/354 PASS; PHPCS novo, Portal 970, boundary 1374, mapa 154,
  SDC 516, design 2089 e fixtures 157 PASS. Runtime: campo ativo adicionado de
  forma pontual após backup; View com 8 linhas e HTTP 200. `basic_html` ausente
  nas fixtures causou avisos na renderização; browser e resposta real pendentes.
  RUNTIME STATUS: PARTIAL.
- Auditoria do Core 11.4.10 confirmou que a gravação de Comment recalcula
  somente estatísticas do host atual, invalida só seu cache e calcula thread/lock
  automaticamente apenas em Comment novo. Essa análise guiou o protótipo
  transacional de dev.33. Nenhum SQL direto foi adotado.

## 0.1.0-dev.31 — seguir e salvar tópicos ACULTA — 2026-10-19

- As Flags individuais `seguir_topico` e `salvar_topico` passam a aceitar o
  bundle `aculta_forum_topic`, preservando provisoriamente o bundle legado
  `forum` durante a migração. As mesmas definições ficam nos defaults do módulo
  e em Config Sync; `flag` é dependência executável explícita do Forum.
- O tipo atual da Flag é por usuário, portanto os dados não são globais e os
  registros existentes não são removidos. A implementação continua no Flag; o
  Fórum não cria storage próprio nem automatiza avisos de Messenger ao seguir.
- Testes Forum Unit: 48 testes / 350 assertions PASS (3 deprecações PHPUnit
  preexistentes); PHPCS da configuração testada e gates Portal 11+
  (970), fronteira (1370) e mapa (154) PASS. PHP CLI 8.4.26.
- Runtime `toca`, Core 11.4.10: backup privado dos dois configs antes da escrita;
  somente `bundles` foi estendido e `config:status` não indicou drift nos dois
  IDs após a alteração. Cache rebuild PASS. uid 5 montou os dois links contra
  tópico público atual sem persistir flagging. HTTP/browser e mudança real de
  estado por navegador pendentes. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.30 — link para a primeira resposta não lida — 2026-10-19

- A View autenticada `/discussoes/nao-lidas` adiciona o campo `node_new_comments`
  do módulo History. Exibe apenas contagens positivas e liga à primeira
  resposta nova, incluindo página/âncora calculadas pelo Comment Core. O campo
  não foi adicionado às listas públicas; a View mantém cache `none` por conta.
- Unit verifica plugin, link, ocultação de zeros e isolamento na View privada.
  Forum Unit: 47 testes / 340 assertions PASS (3 deprecações antigas de
  PHPUnit), incluindo configuração do campo (teste direcionado: 15/189);
  Kernel MariaDB: 4 testes / 75 assertions PASS (5 deprecações do Domain
  contrib; cobre access/History adjacente, não renderização do campo novo);
  PHPCS e gates Drupal 11+ (970), fronteira (1368), mapa (154), slugs (387)
  PASS.
- Runtime `toca`: import parcial somente da View com backup recuperável,
  `config:status` sem diferenças, plugin DI resolvido e 8 itens em renderização
  autenticada via Drush; anônimo HTTP 403. Nenhuma resposta aprovada existe para
  verificar contagem positiva/fragmento. O render em Drush também reportou o
  erro preexistente `Missing text format: basic_html` nas fixtures. HTTP/browser
  autenticado e inspeção visual pendentes. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.29 — marcar discussões públicas como lidas — 2026-10-19

- Adicionada ação confirmada `/discussoes/marcar-tudo-como-lido`, disponível
  apenas para contas autenticadas no purpose público do Fórum. A seleção fixa
  `aculta_forum_topic`, publicação, domínio canônico e Node access; o Core Batch
  processa até 200 IDs por requisição e usa a API pública `history_write()` do
  pacote `drupal/history` instalado. Não há storage próprio e cada conta só
  grava o próprio histórico.
- O processamento revalida conta e purpose em cada lote e repete a consulta com
  `accessCheck(TRUE)`. A ação não altera Nodes/Comments nem inclui LMS, Wiki,
  Revista ou conteúdo histórico.
- Adicionado teste Unit para acesso autenticado e isolamento por purpose.
  PHPUnit Unit do Fórum: 47 testes / 335 assertions PASS (3 deprecações antigas
  do PHPUnit). Kernel MariaDB `aculta_simpletest`: 4 testes / 75 assertions
  PASS; 5 deprecações pertencem ao Domain contrib 3.0.1. PHPCS, lint, gates
  Portal 11+ (970), fronteira (1368), mapa (154) e slugs (387 rotas) PASS.
  Runtime `toca`: cache rebuild, container, construção do formulário e rota
  confirmados; acesso anônimo 403. A ação não foi submetida para evitar gravar
  histórico de uma conta real. RUNTIME STATUS: PARTIAL.

# Implementação não marcada — valida agregado de respostas Core — 2026-10-19

- O Kernel do movimento de categoria agora valida o agregado real de
  `comment_entity_statistics`: uma resposta publicada conta, uma pendente não,
  e a contagem sobrevive à mudança de categoria do mesmo Node.
- O teste habilita explicitamente `comment.maintain_entity_statistics`, como a
  instalação oficial do Core faz. Kernel MariaDB dedicado: 2 testes / 48
  assertions PASS; 5 deprecações preexistentes do contrib Domain. Nenhum dado
  do Runtime foi alterado. RUNTIME STATUS: PARTIAL; ordenação visual da View
  com comentários aprovados continua pendente.

# Implementação não marcada — descoberta de tópicos públicos — 2026-10-19

- A View pública ganhou displays de tópicos novos, sem resposta e mais
  comentados, com navegação local. Reaproveitam o escopo da lista pública;
  respostas vêm do agregado Core `comment_entity_statistics`, sem contadores
  paralelos.
- Runtime `toca`: três rotas HTTP 200; cada uma consulta 8 tópicos, e a lista
  sem resposta retorna os 8 porque não existem comentários públicos no Fórum.
  Caso positivo de respostas/ordenação e inspeção visual seguem pendentes.
- O CAPTCHA global abortava o exposed GET form nos novos displays. A exceção
  OOP foi limitada aos filtros read-only do índice público; PHPUnit passa e os
  resultados HTTP agora apresentam os tópicos sem o desafio inválido.
- `drush cr` também revelou a injeção do hook mover tópico por tipo sem alias
  para o service ID. O parâmetro recebeu `#[Autowire(service: ...)]`; cache
  rebuild agora PASS. O teste Unit verifica esse wiring explicitamente.
- Runtime importou somente `views.view.aculta_forum_public_topics`, após cópia
  recuperável da config ativa; nenhum conteúdo foi modificado. RUNTIME STATUS:
  PARTIAL (sem sessão autenticada/browser e sem contagem positiva).
- PHPUnit do módulo: 46 testes / 332 assertions PASS; PHPCS e gates Drupal
  11+, fronteira, mapa, slugs, hierarquia e `.htaccess` PASS. Uma depreciação
  preexistente de metadata PHPUnit permanece em `DiscussionModerationHooksTest`.

# Implementação não marcada — lista personalizada de não lidos — 2026-10-19

- Criada View `/discussoes/nao-lidas` com filtros de History por usuário e
  indicador de conteúdo novo/atualizado que inclui comentários. Exige o papel
  autenticado, mantém filtros de publicação, bundle, Domain, categoria e
  acesso à entidade; usa cache `none` para não compartilhar resultado pessoal.
- `drupal/history:^1.0` promovido de dependência transitiva de `drupal/forum`
  a requisito direto para preservar a função após a futura remoção do Forum.
  Essa versão não está coberta pela política de avisos de segurança do
  Drupal.org; o risco e acompanhamento de releases estão documentados.
- Runtime de teste: configuração criada sem alterar conteúdo; uid 5 autorizado
  pelo plugin e query retorna 7 itens; usuário anônimo recebe 403. HTTP com
  sessão autenticada e inspeção visual pendentes. Nenhum registro de leitura
  foi gravado; o índice público continua com seus caminhos/filtros atuais.
- PHPUnit Forum: 14 testes / 157 assertions; gates Portal 11+, fronteira, mapa,
  slugs, Composer validate e PHPCS PASS. RUNTIME STATUS: PARTIAL.

# Implementação não marcada — hierarquia no índice de categorias — 2026-10-19

- A View `/categorias` agora configura a relação Taxonomy Core `Parent` e uma
  coluna `Categoria principal`; termos de topo continuam visíveis com texto
  fallback, e subcategorias indicam seu pai.
- Runtime `toca` confirmado em Drupal 11.4.10, `aculta_runtime` e URI de teste
  explícita: apenas a View foi sincronizada, após cópia recuperável da config
  ativa. A consulta retornou os 12 termos, com as 8 subcategorias ligadas aos
  pais corretos; `/categorias` respondeu HTTP 200 e apresentou os nomes e
  fallbacks. `config:status` não reporta diferença desta View. Nenhum termo,
  Node ou Comment foi alterado. Inspeção visual autenticada permanece pendente.
- Configuração de instalação e Config Sync equivalentes; PHPUnit direcionado:
  13 testes / 141 assertions PASS; gates Portal 11+, fronteira e mapa PASS.
- O roadmap registra que Core History está deprecated desde Drupal 11.4. A
  versão contrib instalada é transitiva via `drupal/forum`; o roadmap registra
  esse acoplamento e a avaliação necessária antes da remoção do pacote.

# Implementação não marcada — mover tópico público entre categorias — 2026-10-19

- Ação individual em `/painel-administrativo/conteudo/forum/{node}/mover-categoria`,
  com permissão específica, confirmação explícita e nova revisão no mesmo Node.
- O serviço revalida permissão, access no purpose `forum`, Domain canônico, tipo
  e destino Taxonomy; recusa tópicos com `field_autor_legado`. O log registra
  apenas IDs de origem/destino e uid.
- Unit `ForumTopicMoverTest`: 4 testes/31 assertions PASS. Kernel
  `ForumTopicMoverKernelTest`: 2 testes/44 assertions PASS em MariaDB
  `aculta_simpletest`, cobrindo preservação de NID/UUID, nova revisão, alias e
  Comment/CID, e bloqueio histórico mesmo com permissão de administração.
- Os testes Kernel reportam cinco depreciações do contrib Domain 3.0.1. Gates
  Drupal 11+, fronteira, mapa, hierarquia, PHPCS e YAML PASS. Functional e
  Runtime ainda não executados. Sem incremento de versão e sem
  alteração persistente no Runtime.

# `0.1.0-dev.28` — índice público de categorias — 2026-10-19

## Implementação não marcada — contagem na listagem pública

- A View pública prepara o teaser Node existente e `comment_count` do Core,
  dentro do bundle fixo `aculta_forum_topic`, que possui um único campo de
  discussão. A View usa o agregado Core e não executa COUNT por linha; ordena
  por última atividade com `last_updated`. A versão segue sem marcação até
  confirmação de contagens positivas e revisão visual. RUNTIME STATUS: PARTIAL.

### Ordenação pela última atividade

- Mantida a saída Core `comment_ces_last_updated`, com sort local
  `aculta_forum_last_activity` usando a mesma fórmula de data. O sort do Core
  falha em Drupal 11.4.10 ao atribuir o retorno `void` de `Sql::addOrderBy()` a
  propriedade `string` (Drupal core issue
  [#3519952](https://www.drupal.org/project/drupal/issues/3519952)). A primeira
  configuração foi revertida imediatamente. O hook Views data adiciona um
  sort virtual limitado ao Fórum; a View voltou a executar com o adaptador
  ACULTA: SQL contém `GREATEST(node.changed, last_comment_timestamp) DESC`,
  com 8 linhas e a coluna de data do Core renderizada. Sem resposta/comentário
  positivo persistido; Runtime PARTIAL e versão não marcada.

- Criada a página `/categorias` com View Core baseada em Taxonomy, limitada ao
  vocabulário público do ACULTA Forum e com cache por idioma/permissão. A raiz
  do Fórum e a página de categorias têm links de navegação entre si; as linhas
  mostram nome e descrição sem encaminhar às páginas genéricas de Taxonomy.
- PHPUnit `ForumTopicDisplayConfigTest`: 13 testes / 123 assertions PASS; gates
  Portal 11+, fronteira, mapa e slugs PASS. Runtime de teste Drupal 11.4.10:
  importação parcial das duas Views, HTTP 200 na raiz e `/categorias`, 12 termos
  renderizados e zero diferenças de Config Sync para as duas Views. Nenhum
  conteúdo foi alterado. RUNTIME STATUS: PASS para o escopo HTTP; inspeção
  visual e hierarquia continuam pendentes.

# `0.1.0-dev.27` — estados de comentários em abas do Fórum — 2026-10-19

- A aba Fórum apresenta três subabas de estado: publicados, rejeitados e
  aguardando moderação. O Drupal fornece até dois níveis de tarefas locais;
  por isso os estados são filhos diretos da aba Fórum.
- `field_aculta_rejected` distingue a rejeição definitiva de uma pendência
  (`status=0`) e pode ser visto/editado somente por quem tem `administer comments`.
  O marcador fica oculto no display público.
- PHPUnit do Fórum: 37 testes / 220 assertions PASS. Gate Portal, fronteira
  módulo/tema, mapa módulo/tema, slugs e `git diff --check` PASS.
- Runtime `toca` (Drupal 11.4.10, `aculta_runtime`): as três Views retornaram
  somente o estado de Fórum correspondente; a View editorial retornou somente
  comentários da publicação. Render API público apresentou somente os
  publicados em cada contexto. Seis Comments `[QA 20261019]` temporários foram
  removidos e a contagem final nos dois hosts de teste voltou a zero.
  RUNTIME STATUS: PARTIAL; inspeção HTTP autenticada no navegador pendente.

## `0.1.0-dev.26` — Fórum como aba isolada de Conteúdo — 2026-10-19

- A View administrativa de tópicos registra Fórum como aba irmã das demais em
  `/painel-administrativo/conteudo`. “Comentários e moderação” é subaba da
  seção do Fórum. As rotas permanecem iguais; links estáticos duplicados foram
  removidos.
- `views.view.content` recebe exclusão fixa do bundle `aculta_forum_topic`, que
  prevalece sobre o filtro exposto de tipo.
- PHPUnit de configuração valida os pais das abas e a exclusão fixa. Runtime
  confirma as abas e executou a listagem geral com o tipo de Fórum exposto sem
  retornar tópicos. Há 8 Nodes `[TESTE]` e 0 Comments; HTTP autenticado pendente.
  RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.25` — Fórum separado e correção do 500 — 2026-10-19

- Fórum aparece como aba em Conteúdo, no mesmo nível de Conteúdo, Comentários e
  Galerias. “Comentários e moderação” é subaba da seção Fórum. Os caminhos
  permanecem estáveis.
- A coluna Categoria usa o field handler válido `field_aculta_categories`,
  confirmado em Views Data instalado. O handler anterior apontava para
  `field_aculta_categories_target_id`, que só oferece filter/argument/sort e
  carregava como `Broken`, causando `TypeError: array + null` durante render.
- O filtro de categoria da listagem pública permanece inalterado; a View
  administrativa mantém somente os filtros que ela já oferecia.
- RUNTIME STATUS: PARTIAL. Runtime de teste atualizado após backup privado;
  links do Fórum e comentários usam o menu `content`, as rotas passam no access
  para uid 5 e ambas as Views renderizam via Render API. Request HTTP autenticada
  segue pendente; nenhum conteúdo foi alterado.

## `0.1.0-dev.24` — navegação no menu Content como Galerias — 2026-10-19

- Links de Fórum e comentários passam a usar `navigation.content` e o menu
  `content`, ao lado de Galerias. Views não criam abas no overview genérico.

## `0.1.0-dev.23` — links de navegação administrativa — 2026-10-19

- O menu Conteúdo expõe a listagem de tópicos; comentários ficam aninhados em
  Fórum. Os destinos usam as rotas de Views e mantêm suas permissões de acesso.
- RUNTIME STATUS: PARTIAL. Fórum e Comentários do Fórum aparecem na árvore
  administrativa para uid 5, e as rotas passam a checagem de acesso. A sessão
  HTTP temporária não foi autenticada porque o ULI retornou 404.

## `0.1.0-dev.21` — paginação do índice público — 2026-10-19

- A listagem pública usa pager Core completo com 20 tópicos por página, controles
  de navegação em português e sem seletor livre de quantidade. Os filtros de
  domínio, publicação, bundle, categoria e acesso permanecem fixos.
- A configuração de instalação e Config Sync são iguais; validação Unit e HTTP
  do Runtime de teste confirmam pager e visibilidade. Nenhum Node ou Comment foi
  criado/editado/excluído nesta entrega. Navegação com mais de 20 itens e inspeção visual seguem pendentes. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.20` — categorias e listagem pública com filtros — 2026-10-19

- Corrigido o padrão Pathauto para selecionar apenas `aculta_forum_topic`; a
  configuração de instalação e Config Sync agora coincidem. O campo base
  `path` já existe em Node no Runtime e Pathauto o complementa, então não foi
  necessário habilitar globalmente a geração Pathauto para todos os Nodes.
- Pesquisa de organização moderna recomenda poucas categorias com propósito e
  descrição explícitos, subcategorias apenas quando ajudam a navegação e tags
  para dimensões transversais. A hierarquia inicial de homologação usa quatro
  categorias e oito subcategorias; não altera o vocabulário legado `forums`.
- Foram criados conteúdos marcados `[TESTE]` somente no Runtime Homelab para
  validar as listagens e aliases. IDs, URLs e plano de remoção estão em
  `docs/TEST-FIXTURES.md`. Nenhum comentário foi criado; entidades legadas
  permaneceram intactas.
- A montagem da View pública abortava porque o CAPTCHA global validava o
  formulário GET de filtros durante a renderização. Hook OOP remove o desafio
  somente dessa View/página de leitura; CAPTCHA permanece nas ações de escrita.
- Os HTTP smoke tests verificaram raiz, filtro e oito aliases (200), com os
  oito títulos aparecendo na listagem após o Runtime carregar o hook. RUNTIME
  STATUS: PASS para listagem/URLs; navegador visual DEFERRED.
- Pesquisa consultada: orientação do Discourse para categorias e subcategorias
  (descrição e escopo claros, estrutura sem excesso) e API Drupal Taxonomy
  (termos nativos com relação hierárquica). Ver `docs/CATEGORIES-AND-RESEARCH.md`.
- Unit `ForumTopicDisplayConfigTest` + `PublicForumExposedFormHooksTest`:
  11 testes/64 assertions PASS; PHPCS Drupal/DrupalPractice PASS. Gates Portal
  Drupal 11+ (969), module/theme boundary (1334), map (154), public slugs (387)
  e `git diff --check` PASS.

## `0.1.0-dev.18` — contrato de respostas abertas no tópico — 2026-10-19

- O teste de configuração agora fixa que tópicos públicos usam `aculta_discussion_post`, começam com respostas abertas, exibem o formulário no campo Core e restringem envio anônimo; Config Sync deve permanecer equivalente à configuração de instalação.
- Runtime `toca` confirmado em Drupal 11.4.10: campo ativo com status `Open`, papel `email_confirmed` e uid 5 têm `post comments`; continuam 0 tópicos e 0 comentários. Formulário de resposta em nó persistido não foi renderizado para preservar o acervo vazio. RUNTIME STATUS: PARTIAL.
- PHPUnit `ForumTopicDisplayConfigTest`: 8 testes / 55 assertions — PASS; PHPCS Drupal/DrupalPractice — PASS.

## `0.1.0-dev.17` — acesso de participante ao formulário de tópico — 2026-10-19

- Config Sync do papel `email_confirmed` mantém o estado ativo de Wiki/Fórum legado e demais permissões; acrescenta somente a dependência do bundle ACULTA e criar/editar/excluir o próprio tópico. Nenhuma permissão `any` foi concedida.
- Unit `ForumTopicDisplayConfigTest`: 7 testes/47 assertions PASS. Runtime `toca`: importação parcial apenas do papel; uid 5 confirmado tem acesso à rota e formulário em memória renderiza title/body/categoria. Formulário não enviado; zero tópico/Comment novo. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.16` — mensagem para listagem pública vazia — 2026-10-19

- A View pública informa em português quando ainda não há tópicos publicados. A configuração de instalação e Config Sync mantêm o mesmo texto e plugin Core `text_custom`; listagem, filtro de domínio, bundle, publicação e acesso não foram alterados.
- Unit `ForumTopicDisplayConfigTest`: 6 testes/42 assertions PASS, incluindo equivalência Sync/install e mensagem. Runtime `toca`: importação parcial só da View; após cache rebuild, raiz 200 mostra a mensagem e o filtro; `/discussoes` permanece 404. Zero tópicos e Comments compartilhados. RUNTIME STATUS: PARTIAL; navegador sem tópico persistido pendente.

## `0.1.0-dev.15` — filtro hierárquico de categorias na View pública — 2026-10-19

- A View `aculta_forum_public_topics` ganhou filtro exposto de categoria pelo plugin Taxonomy nativo `taxonomy_index_tid`, limitado ao vocabulário `aculta_forum_categories` e com hierarquia. Filtros de tipo, domínio, publicação e acesso permanecem fixos; cache inclui query args.
- Config Sync e config de instalação são equivalentes; Unit de View/config: 6 testes/39 assertions PASS. Runtime `toca`: config isolada aplicada, Core carregou `TaxonomyIndexTid`, raiz e query `?categoria=999999` responderam 200, seletor “Categoria” renderizado. O vocabulário está vazio (0 termos), então seleção real depende da taxonomia editorial. Nenhum termo ou conteúdo foi criado. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.14` — ação contextual de criação de tópico — 2026-10-19

- A View pública acrescenta `/criar-topico` somente quando Entity Create Access permite a ação. O hook OOP `views_pre_render` usa o resultado cacheável da rota tanto na ação quanto na View e aplica o token visual existente `button-primary`.
- PHPUnit Unit do Fórum: 11 testes/51 assertions PASS, incluindo allow/deny, cache e filtros por View/display/purpose; PHPCS PASS; gates Drupal 11+ (969), fronteira (1328), mapa (154), tokens de UI (285), packages (22), slugs (387) PASS. Runtime `toca`: raiz HTTP 200, sem ação para anônimo, `/criar-topico` 403. A apresentação integral e usuário autorizado permanecem pendentes; nenhuma submissão ou conteúdo persistido foi feito. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.13` — formulário de criação em slug público — 2026-10-19

- Nova rota `/criar-topico` para o formulário Core `node.default` do bundle `aculta_forum_topic`. Entity Create Access continua sendo a autorização, complementada pela regra de participação existente; a rota pertence ao purpose `forum`.
- Teste de contrato cobre caminho, form handler, bundle e requisito de acesso. Runtime `toca`: rota resolvida com purpose `forum`; anônimo 403, host principal 404; conta de teste sem permissão negada. Nenhum formulário enviado nem Node criado. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.12` — raiz pública usa a listagem ACULTA Forum — 2026-10-19

- Page display da View `aculta_forum_public_topics` em `/discussoes`; o processador de purpose serve essa rota na raiz `/` do Growroom420. Filtros fixos de bundle, publicação, domínio, access e cache continuam herdados do display padrão.
- Rotas da View são classificadas como purpose `forum`; o caminho interno novo `/discussoes` e o caminho legado `/forum` continuam 404 diretos. Configuração de instalação e Config Sync equivalentes.
- Runtime de teste: importação parcial da View e cache rebuild; Drush confirmou rota `/discussoes`, purpose `forum`, zero itens novos. HTTP confirmou raiz 200 e caminhos diretos `/discussoes` e `/forum` 404; título correto no HTML. Nenhum conteúdo criado/movido. RUNTIME STATUS: PARTIAL.

## `0.1.0-dev.11` — listagem pública por domínio — 2026-10-19

- View nova `aculta_forum_public_topics`, em Config Sync e config de instalação, consulta somente `aculta_forum_topic` publicado, filtra `field_domain_source` pelo domínio ativo, mantém SQL rewrite/`node_access` e cache contexts de grants. O bloco CTools Views deixa ajustáveis apenas paginação e quantidade.
- `field_domain_source` passa a integrar o bundle. Hook OOP define o domínio canônico do purpose `forum` quando ausente; criação é negada fora desse purpose ou se sua configuração não existir. Fonte explicitamente definida é preservada.
- A View antiga `aculta_forum_topics` e seu bloco existente seguem intactos para não interromper o acervo contrib antes da fase segura dev.5. A View nova ainda não foi colocada em página ou bloco.
- Testes Unit de View/configuração e hook: 21 testes/102 assertions PASS; PHPCS e PHP lint PASS. Runtime: View executada sem resultados porque não há tópicos novos; hook OOP resolvido/registrado e atribuiu o domínio em Node em memória. Nenhuma rota, colocação, tópico ou comentário foi alterado.

## `0.1.0-dev.10` — formulário e apresentação base do tópico público — 2026-10-19

- Configuração Core de formulário do Node `aculta_forum_topic`: título, corpo e categoria; status de comentário e referência de autoria histórica não ficam editáveis no formulário.
- Displays Core `full` e `teaser`: página integral apresenta o Comment compartilhado; listagens podem usar teaser de corpo/categoria sem expor thread ou autor histórico.
- Configs instaláveis e Config Sync permanecem idênticas. Teste Unit: 15 testes/67 assertions PASS. No servidor `toca`, Drupal 11.4.10, Drush URI explícita `https://growroom420.aculta.toca.net.br` e banco `aculta_runtime`, foram importados somente os três displays por `config:import --partial`; `config:status` confirmou os três sem diferenças. Smoke Form API criou Node apenas em memória, expôs título/corpo/categoria e o Core acrescentou `revision_log` no grupo de revisão. Contagens após a operação: 0 tópicos novos, 0 comentários compartilhados e 30 Nodes legados preservados. Nenhum conteúdo foi criado, alterado ou apagado. RUNTIME STATUS: PARTIAL; navegador e envio persistido pendentes.

## `0.1.0-dev.9` — exibição do Comment compartilhado — 2026-10-19

- Define o display Core `comment.aculta_discussion_post.default` para renderizar o corpo e as ações do Comment; `field_autor_legado` permanece oculto. O `aculta_user` injeta o cartão neutral de autoria.
- RUNTIME STATUS: DEFERRED; configuração ainda não foi importada.

## `0.1.0-dev.8` — importação Growroom somente leitura — 2026-10-19

- A autoria `field_autor_legado` pode ser ligada aos Nodes `aculta_forum_topic` e Comments `aculta_discussion_post`; o `aculta_forum` depende do `aculta_user`, dono do LegacyAuthor.
- Tópicos `aculta_forum_topic` e Comments `aculta_discussion_post` históricos são imutáveis em Entity Access; a thread fecha no presave e a fila administrativa bloqueia moderação em lote. O marcador não classifica os bundles `forum`/`comment_forum` do Drupal Forum contribuído como importação Growroom; a retirada contrib segue em plano próprio.
- Foram adicionados os campos de referência em Config Sync e config de instalação. Runtime e importação permanecem DEFERRED; nenhum conteúdo foi criado, importado ou alterado.

## `0.1.0-dev.7` — moderação de tópico por editor confirmado — 2026-10-19

- `aculta_user` concede acesso a `content_editor` para editar/excluir tópicos novos de outros autores somente quando `EmailConfirmationPolicy` confirma a conta. A própria criação e gestão do tópico continuam sob as permissões nativas do bundle.
- Conta não confirmada recebe negação explícita; operações sobre o próprio tópico permanecem no fluxo normal do Node; o bypass administrativo e a proteção dos conteúdos históricos permanecem.
- Teste Unit dedicado: 4 testes/18 assertions PASS. Runtime de teste sem alteração persistente; RUNTIME STATUS: DEFERRED.
- As futuras listagens configuráveis por colocação devem usar `ctools_views` para pager/quantidade, mantendo filtros de acesso, bundle, publicação e domínio fixos na View. A integração CTools complementa Views e Entity Access; não substitui autorização.

## `0.1.0-dev.6` — permissões de tópico público em Config Sync — 2026-10-19

- `core.extension` inclui `aculta_forum`; Config Sync declara as permissões nativas do Node `aculta_forum_topic` para criação e gestão do próprio tópico por `email_confirmed`. Permissões amplas de moderação por `content_editor` ficam pendentes de access check que respeite confirmação de e-mail.
- O Runtime de teste foi consultado em modo somente leitura: `aculta_forum` está habilitado e `core.extension:module.aculta_forum` é `0`. A configuração local ainda difere de diversos itens do banco; nenhuma importação foi executada. Não foram criados termos/tópicos nem removidos dados legados. RUNTIME STATUS: DEFERRED para aplicar permissões.

# `0.1.0-dev.5` — auditoria para retirada do Forum contrib — 2026-10-19

- Confirmado em leitura somente no Runtime de teste: 30 nodes `forum` (12
  publicados e vinculados a publicações, 1 publicado independente e 17 não
  publicados), 0 comments `comment_forum`, 8 termos `forums` e 2 flaggings no
  total do site (ambos de `site_alert`). O validador Drupal Core impede
  uninstall enquanto houver qualquer node `forum` ou termo `forums`.
- Confirmados consumidores ativos em Magazine e User; a sincronização editorial
  e os vínculos `field_discussao` permanecem. Desinstalar agora deixaria 12
  publicações sem o novo campo de comentários; instalar esse fluxo vazio e
  desligar a sincronização antiga precedem a exclusão dos tópicos-casca. O
  conteúdo antigo de discussão não será migrado para o sistema novo.
- Backups encontrados (17 e 18/10) são anteriores e de outras tarefas; nenhum
  foi validado como restore point desta retirada. Nenhuma exclusão, uninstall,
  remoção Composer, update ou alteração Runtime executada.
- Dev5 permanece em andamento. Próximo gate: completar os contratos dos
  consumidores, disponibilizar novos fluxos vazios e validar restore point atual
  antes de qualquer operação destrutiva. RUNTIME STATUS: PARTIAL (auditoria
  somente leitura no teste).
- Decisão do responsável: o novo Fórum e os contextos de discussão começam sem
  importação de tópicos ou comentários antigos. Na limpeza autorizada serão
  apagados os tópicos e comentários legados, preservando publicações, usuários
  e demais entidades; os 12 tópicos de sincronização da Revista só saem depois
  que os comentários diretos da publicação estiverem prontos e a sincronização
  antiga desligada. A contagem registrada é zero comentários `comment_forum`.
- A API agora registra `PublicationContextProvider`: bundles `article`/`blog`
  somente com `field_aculta_discussion`, access de leitura do host e campo,
  escrita via Comment aberto + `post comments`, moderação via `administer comments`,
  cache por permissões e ID estável. Providers não têm field instances na Revista
  ainda.
- Fields da Revista ainda não foram ativados. A política compartilhada de
  moderação agora cobre o bundle novo em Fórum, Revista, Wiki e qualquer
  contexto Node que use o campo compartilhado, preservando o controle legado; LMS continua
  excluído. Field instances da Revista e integração de apresentação ainda
  aguardam homologação específica.
- Kernel multi-bundle: 1/18 PASS em `aculta_simpletest`; Unit do Forum: 12/52 PASS.
  Runtime de teste recebeu apenas cache rebuild e smoke de DI: resolver carregou
  dois providers e recusou Article sem o field novo. Sem importação de config,
  field editorial ou conteúdo. RUNTIME STATUS: PARTIAL; integração funcional
  permanece pendente.

# 0.1.0-dev.4 — Context API executável — 2026-10-19

- Criado e habilitado no Runtime de teste o módulo `aculta_forum`, usando somente bundles/campos nativos Node, Comment e Taxonomy, em configuração de instalação e Config Sync.
- Criados os contratos de contexto, objeto imutável cacheável, resolver extensível por serviços tagueados e provider inicial de tópicos públicos, com leitura negada fora do purpose `forum`.
- A integração de publicação, LMS e Wiki permanece para suas fases próprias; nenhum adapter declara acesso ou expõe contexto antes de verificar a entidade hospedeira.
- Registrada a interpretação ambiental: no servidor `toca`, Drush sem `--uri` reportar `http://default` é esperado para o Runtime de teste `aculta_runtime`.
- Validações direcionadas: ver `docs/TEST-MATRIX.md`; Runtime de teste aplicado e smoke test limitado à descoberta do serviço/configuração, zero conteúdo criado. PHP CLI 8.4.26; validação do PHP-FPM e UX fica pendente.

# 0.1.0-dev.3 — Author Pane moderno — 2026-10-19

- `aculta_user` ganhou `AuthorCardBuilder`, avatar mediado pelo `aculta_image`, policy explícita da foto e tema hook neutro cacheável.
- ACULTA420 ganhou o SDC experimental do cartão, com variantes separadas de fórum, comentário, editorial, LMS, Wiki e `author_growroom_historico`; fórum empilhado altera apenas layout. Autoria histórica não ganha ações ou perfil individual.
- Nome conceitual `author_legacy` renomeado para `author_growroom_historico` a pedido do responsável.
- Testes Unit: 43/87; gates Portal 968, fronteira 1272, mapa 153, SDC 514 e fundações de design 2065 PASS. Integração no motor novo, teste visual no browser e Runtime continuam pendentes. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.2 — identidade base de autoria (`aculta_user`) — 2026-10-19

- `aculta_user.author_identity_resolver` compõe o User e o perfil principal com o gerador canônico de perfil; retorna `AuthorIdentity`, payload mínimo cacheável de apelido e link.
- Variantes são identificadores de consumidor, ainda sem payloads ampliados ou renderização específica. `author_legacy` permanece fora do resolver User.
- Três testes unitários, 12 assertions; RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.1 — auditoria e fundação documental — 2026-10-19

- PRs e base verificadas: PR #291 está mesclada e é de pagamentos; PRs antigas de Fórum #287–#289 estão fechadas; a branch desta fase parte de `origin/main` `d4a0d66e`. A PR #296 é a linha separada do Portal `0.4.0-dev.162`.
- Inventário confirmou `drupal/forum` 1.1.3 habilitado e dependências de config/código em `aculta_user`, `aculta_magazine`, `aculta_messenger`, Config Sync e ACULTA420.
- Nova arquitetura documental: Node/Comment/Taxonomy/Views/Search API com quatro contextos; cartões centralizados em `aculta_user`; apresentação exclusiva do ACULTA420.
- Inventário completo de capacidades históricas do Advanced Forum e plano em fases para retirada segura; não houve alteração de runtime, dependência, configuração exportada ou dados.
- RUNTIME STATUS: DEFERRED (fase somente documental; nenhuma operação no ambiente).

## F4 — bloco CTools Views de tópicos recentes — 2026-10-18

- Configuração versionada da View `aculta_forum_topics` e bloco na página
  inicial do purpose `forum`; utiliza teaser do Node, filtros fixos de tipo,
  publicação e domínio atual, e query access do Views (`node_access`).
- CTools permite ajustar quantidade de itens e pager no bloco; sem exposição de
  filtros, ordenação ou AJAX. Listagem nativa do Forum continua responsável por
  categorias e listagens de cada categoria.
- `RUNTIME STATUS: DEFERRED`: Drush aponta `aculta_runtime`, mas URI `http://default`
  não confirma um site de teste permitido para esta configuração.

## F4 — tópico: ações sociais, respostas e limpeza de termos — 2026-10-17

- Termo "General discussion" (instalador do módulo forum, sem tópicos, sem referências) removido no teste. `/forum/39` responde 404.
- Denunciar tópico visível para membros (`flag.flag.sinalizar_topico`, `show_as_field: true`).
- Rótulos dos flags quando marcados: "Seguindo" e "Salvo" (antes "Remover: …").
- Campo `comment_forum` em modo encadeado (`default_mode: 1`): respostas diretas aparecem sob a resposta a que respondem.
- Abertura da Revista sem o título repetido (`aculta_magazine` 0.1.0-dev.6).
- Pendentes da F4: conversa vinculada ao histórico (depende da F3); modo escuro; contagem de respostas no cabeçalho.

## Documentação de estado e referência de classes — 2026-10-17

- README: estado do código por parte do fórum (F2, F5.1, F5.2, F9), com o documento de cada módulo.
- Roadmap: resumo por fase em 2026-10-17. A próxima fase é a F4.
- Referência gerada de classes e hooks em `aculta_user/docs/CLASSES-E-HOOKS.md` e `aculta_messenger/docs/CLASSES-E-HOOKS.md` (23 e 30 hooks OOP; cada classe com a primeira frase do docblock).

## F5.2 — política de avisos de resposta (documentação) — 2026-10-16

- Política em `docs/portal/AVISOS-E-NOTIFICACOES.md` e decisão F-D21: sistema existente, aviso obrigatório no site, e-mail opt-in agrupado por tópico, contadores.
- Dúvidas abertas: janela de envio do e-mail e chave de agrupamento dos avisos gerais.

## F9 — associado como marcador puro (F-D19) — 2026-10-16

- Associado não é papel. Marcador em tabela própria, sem efeito de acesso. Nível "associado" dos fóruns removido.
- Visibilidade de fóruns restritos: somente e-mail confirmado (e moderador com e-mail confirmado).

## F9 — moderação e papéis múltiplos — 2026-10-16

- `content_editor` exige e-mail confirmado para restritos e moderação. Moderação (ocultar, despublicar, excluir) de tópicos e comentários, exceto conteúdo histórico. Administrador com bypass total (F-D16).
- Papéis múltiplos: `associado` é papel extra. Regras em `docs/portal/PAPEIS-PARTICIPACAO.md`.
- Lacuna: confirmação manual de e-mail pelo administrador não existe.

## F9 — hierarquia de papéis e 404 para restritos — 2026-10-16

- Hierarquia documentada (`docs/portal/PAPEIS-PARTICIPACAO.md`); `content_editor` é o moderador do Fórum e vê os níveis restritos.
- Visitante e bots recebem 404 em contêiner e tópico restritos (F-D15).
- Dúvidas abertas no documento de papéis, seção 4.

## F9 parcial — fóruns restritos por nível — 2026-10-16

- Papel `associado` versionado (concessão manual). Campo `field_visibilidade` no contêiner: público, e-mail confirmado, associado.
- Visitante não vê contêiner, tópico nem listagem de fórum restrito. Associado vê; e-mail confirmado sem associado não vê o nível associado.
- Verificado em 2026-10-16: processador `content_access` ativo no índice; tópicos de fórum fora do índice de busca (tipo `forum` não selecionado); restrito fora do sitemap; comentário de tópico restrito invisível para anônimo. Bots recebem 403 nos restritos.
- Pendentes: nível de curso (F8); política para `content_editor`; exclusão no sitemap precisa ser mantida se o tipo `forum` entrar no sitemap.

## F5.1 — denúncias e participação com e-mail confirmado — 2026-10-16

- Sinalização de tópico e comentário pelo Flag (`sinalizar_topico`, `sinalizar_comentario`), uma por conta e por conteúdo.
- Fila de moderação em `/painel-administrativo/forum/denuncias`, restrita a `moderar denuncias do forum`.
- Participação no Fórum (criar, editar o próprio conteúdo, comentar, denunciar) exige `email_confirmed` (F-D13).
- Pendências: sinalização de comentário não testada no teste (não há comentário de fórum); papel `authenticated` com divergência anterior; avisos de resposta e busca ficam para F5.2 e F5.3.

# Changelog — ACULTA Fórum

## F2 estrutura pronta para a importação — 2026-10-16

- Campos `field_nome_historico` e `field_forum_origem_id` no perfil participante, aplicados no Runtime de teste.
- Serviço `aculta_user.legacy_accounts`: cria ou atualiza a autoria pela origem, sem duplicar; conta bloqueada
  com papel `usuarios_legados_forum`; sem e-mail, apelido ou conquista.
- Conta legada não tem página pública individual (404 para anônimo) e não recebe avatar do sorteio.
- Memorial `/u/lendashistoricas` com busca, paginação e estado vazio.
- Pendências que dependem da importação ou de aprovação: lista de autores comprovados e importação das contas;
  arte do avatar especial; texto aprovado do memorial; link de autoria nos tópicos e comentários; verificação
  de colisões de apelido em produção.

## F2 estrutural — memorial e papel legado — 2026-10-15

- Papel `usuarios_legados_forum` criado no Runtime de teste e em `config/sync`.
- Apelido `lendashistoricas` reservado; rota `/u/lendashistoricas` respondendo 200 com placeholder.
- Pendências da F2 (não executadas): lista de autores comprovados e Users bloqueados; arte do
  avatar especial; texto aprovado do memorial; nomes individuais; verificação de colisões de
  apelido em produção.
- Gates do Portal passam. Nada aplicado em produção.

## F1 — inventário e domínio no servidor de testes — 2026-10-15

- Inventário completo do prefixo CDX `growroom.net/board/` até
  `2025-07-19 09:36:40 UTC`: 271.283 capturas e 177.673 URLs distintas. O JSON
  fica no armazenamento privado do Homelab; resumo, caminho e checksum estão em
  `docs/RECONSTRUCAO.md`.
- Cloudflare: criado A direto, sem proxy, para
  `growroom420.aculta.toca.net.br` → `45.191.204.31`; o registro antigo
  `forum.aculta.toca.net.br` foi mantido para responder 404 sem redirect.
- Homelab: VirtualHost e certificado Let's Encrypt do novo host ativos;
  `trusted_host_patterns`, Domain e alias do Runtime atualizados sem trocar IDs
  nem purpose. HTTPS `/forum` respondeu 200, canonical e noindex conferidos.
- Sessão do teste confirmada no container: `.aculta.toca.net.br`, `SameSite=Lax`
  e `Secure`.
- Host antigo documentado como 404 sem redirect. Deployer `domains` PASS (9/9),
  `robots --env=test` PASS e `validate-environment-addresses.php` PASS (1.759
  arquivos).
- O helper externo `/home/piradopirata/scripts/forum-setup.sh`, que ainda
  provisionava o host antigo, foi aposentado por stub seguro; cópia original
  preservada no backup privado da F1.
- Sitemap do Fórum responde 200 e o exclui do índice conforme a política
  instalada. `sitemap --env=test` permanece FAIL por 404 de LINKS, fora desta
  fase.
- Produção não foi alterada: não havia DNS do host antigo, e o TLS direto da
  origem Hostinger para `growroom420.aculta.org` falhou. O novo DNS de produção
  aguarda o cadastro e a validação do hostname na origem.
- `RUNTIME STATUS: PASS (Homelab/teste); produção não alterada.` Nenhum `updb`,
  `cim`, import/export ou conteúdo foi executado.

## 2026-10-15 — ajuste de identidade aprovado

- Marca futura: Growroom420; prefixo público `growroom420.aculta`.
- Purpose interno `forum` e fontes históricas Growroom preservados.
- Decisão documental; nenhum domínio provisionado nesta alteração.

## 2026-10-15 — abertura documental do projeto

- Plano aprovado: Growroom, memória histórica, conversas atuais vinculadas e
  integração com as capacidades existentes do Portal.
- Registradas a mudança futura de prefixo para `growroom`, a autoria individual
  dos legados e a página coletiva `/u/lendashistoricas`.
- Documentados pesquisa, arquitetura, reconstrução, roadmap e gates futuros.
- Entrega exclusivamente documental, sem versão de módulo instalável ou Runtime.
- [Evidência de revisão e validação estática](../../../../../../docs/portal/FORUM-GAME-PESQUISA-VALIDACAO.md).
## `0.1.0-dev.22` — separação administrativa por contexto — 2026-10-19

- Incluída a listagem administrativa de tópicos em
  `/painel-administrativo/conteudo/forum`, isolada do inventário geral.
- Criada a fila `/painel-administrativo/conteudo/forum/comentarios`, filtrada
  por Comment type, field, entidade Node e bundle público; inclui comentários
  aguardando aprovação e requer `administer comments`.
- Documentada a política de separação para Magazine, LMS e Wiki. Os adapters
  futuros deverão fornecer listagens próprias com acesso contextual; não foi
  criada fila LMS/Wiki prematura.
- Revista mantém conteúdo e comentários juntos em
  `/painel-administrativo/conteudo/revista`; não há rota separada de comentários.
- Nenhuma discussão LMS ou Wiki passa pela fila pública do Fórum. Nenhum
  conteúdo foi criado, alterado ou removido nesta etapa.
- Runtime status: a View de tópicos já foi importada no Runtime de teste;
  aplicação da nova View de comentários e smoke de acesso ficam pendentes.

## `0.1.0-dev.28` — índice público de categorias — 2026-10-19

- Criada a página `/categorias` com View Core baseada em Taxonomy, limitada ao
  vocabulário público do ACULTA Forum e com cache por idioma/permissão.
- O link “Categorias” integra a navegação local do índice público de discussões.
- A View usa renderização padrão da entidade Taxonomy. Hierarquia visual,
  contagens de tópicos e ordenação por atividade ficam para as subfases próprias.

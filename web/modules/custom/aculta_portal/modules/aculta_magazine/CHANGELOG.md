## 0.5.0-dev.5 — referência estruturada de Galeria

- `node.revista.field_editorial_gallery` guarda uma referência estruturada a
  `media_gallery`, com cardinalidade 1. A apresentação continua no Entity
  Embed; o provider emite `editorial_gallery` a partir do campo sem analisar ou
  copiar o corpo.
- `aculta-magazine:migrate-gallery-references` oferece lotes keyset com prévia
  por padrão. Com `--execute`, copia embeds únicos resolvidos sem alterar o
  corpo; publicações com várias galerias distintas são ignoradas e listadas
  para revisão editorial. Retomada usa o `next_nid` do relatório.
- Importar a configuração `config/sync` antes de executar o comando. Nenhuma
  migração foi executada nesta entrega.

## 0.5.0-dev.4 — acesso canônico do Content Index

- O provider da Revista declara o purpose canônico `magazine`; consultas do
  Index vindas de outros domínios revalidam o Entity Access com o mesmo usuário
  e grants, falhando fechado se o domínio não puder ser resolvido.
- Teste Unit cobre o purpose declarado pelo provider.

## 0.5.0-dev.3 — contratos editoriais para o Studio — 2026-10-22

- MAG 0.5-B publica os contratos editoriais consumíveis pelo Studio:
  [`docs/STUDIO-INTEGRATION-CONTRACT.md`](docs/STUDIO-INTEGRATION-CONTRACT.md)
  (inventário real das rotas, acessos, form modes, revisões e lacunas B-01 a
  B-06), [`docs/EDITORIAL-ACCESS-MATRIX.md`](docs/EDITORIAL-ACCESS-MATRIX.md)
  (contratos de acesso por operação, coautoria e cacheability),
  [`docs/EDITORIAL-ACTOR-STATE-MATRIX.md`](docs/EDITORIAL-ACTOR-STATE-MATRIX.md)
  (ator × estado × ação sobre o workflow real) e
  [`docs/ROUTES-DOMAIN-CONTRACT.md`](docs/ROUTES-DOMAIN-CONTRACT.md) (purpose
  das rotas editoriais e regra de links).
- O contrato do formulário editorial foi implementado em
  `RevistaFormHooks`: o grupo `revista_secondary` abre quando contém erro de
  validação e o foco vai ao primeiro campo inválido; `js/revista-form.js`
  restaura o aviso de alterações não salvas no retorno por bfcache (`pageshow`).
  Nenhum campo, peso ou regra editorial foi alterado.
- Testes novos: Kernel do Magazine para autoria, coautoria, revisões, preview,
  transições, domínio/cache e fluxo do formulário; Unit do Portal para a
  precedência de purpose de rota
  (`aculta_portal/tests/src/Unit/ContentPurposeResolverTest.php`). O workflow
  `revista` permanece com quatro estados e oito transições.
- Integração mínima de 0.5-B.9 verificada no Studio: a aba Revista consome
  criação, edição, listagem e moderação pelos contratos do Core. A única
  correção de consumidor foi o purpose das operações, em commit próprio do
  Studio (`7500090c`), fora do Magazine.
- Nenhuma alteração de configuração persistente nesta entrada: sem `drush cim`,
  sem migração de conteúdo, sem exclusão e sem mudança de permissões, estados ou
  transições. Nenhuma API fictícia foi criada; o adaptador `§5` segue sem
  consumidor real.

## 0.5.0-dev.2 — provider inicial do Content Index

- `MagazineContentIndexProvider` projeta o bundle atual `revista` por idioma,
  com label, UUID, domínio, publicação e datas. Rascunhos da revisão padrão
  podem ser catalogados para consumo editorial; revisões não padrão são
  excluídas. A autorização continua no Node e será revalidada pela consulta.
- Projeta somente relações explicitamente configuradas para atividade e mídia
  editorial. Corpo, resumo, autoria, arquivos e workflow detalhado ficam fora.
- O serviço usa a tag `aculta_index.provider` e o contrato neutro do Portal;
  Magazine não depende do submódulo opcional `aculta_index`.
- Testes Unit cobrem identidade, revisão padrão, bundles não suportados e
  relações explicitamente permitidas.

## 0.5.0-dev.1 — auditoria arquitetural e contratos — 2026-10-22

- MAG 0.5-A publica a documentação normativa do submódulo:
  [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) (propriedade, modelo de
  conteúdo, tabela exata de transições do workflow `revista`, hooks e
  invariantes), [`docs/DEPENDENCY-MATRIX.md`](docs/DEPENDENCY-MATRIX.md)
  (consumidores, fornecedores, dependências declaradas e bloqueadores) e
  [`docs/AUDIT-MAG-0.5-A.md`](docs/AUDIT-MAG-0.5-A.md) (inventário completo,
  achados A-01 a A-12 e relatório de Config Sync).
- Config Sync: dois desvios comprovados foram corrigidos no repositório, sem
  importação e sem `drush cim`. O form display `node.revista.default` declara a
  dependência `workflows.workflow.revista` que o Runtime já tinha; os displays
  `default`, `full` e `teaser` exibem o componente
  `content_moderation_control` na mesma posição do estado ativo. Os quatro
  arquivos agora conferem com o Runtime.
- Nenhuma alteração persistente no homelab nesta entrada: sem import de
  configuração, sem migração de conteúdo, sem exclusão e sem mudança de
  permissões.
- Achados mantidos sob os donos atuais, apenas documentados: edição de
  `co_authors` negada a `content_editor` (A-03), `field_coautores` residual
  (A-04), padrão Pathauto legado (A-05), hook de instalação histórico (A-06),
  purpose `main` no link de edição do Studio (A-07) e consulta ao bundle
  `article` no feed do `aculta_user` (A-12).
- Gates executados nesta entrada: `git diff --check` limpo; YAML dos quatro
  arquivos validado; `drush config:status` sem entradas `node.revista` após a
  correção; PHPUnit Unit do submódulo e PHPCS executados em 0.5-B-R.

## 0.4.0-dev.1 — integrações contextuais Fórum/Galeria — 2026-10-21

- O papel editorial manual `content_editor` recebeu criação/edição do bundle
  `revista` e permissões explícitas do workflow. Contas `email_confirmed` sem
  esse papel não ganham acesso de criação nem transições de publicação.
- Corrigido o migrador para mapear o estado publicado/rascunho ao workflow
  `revista` e validar a preservação de publicação. Kernel MariaDB: 2 testes / 44
  assertions PASS; duas depreciações upstream de Token/Pathauto.
- Na migração de 2026-10-21, 12 publicações legítimas haviam sido preservadas
  como Nodes, mas ficaram em rascunho devido ao estado padrão do novo workflow.
  Restauradas por transição Entity API para `published`, com nova revisão e
  mensagem de revisão; os 4 Nodes de teste permaneceram rascunhos. Nenhum Node
  ou arquivo foi excluído.
- O provider de discussão do `aculta_forum` agora reconhece somente Nodes `revista` com `field_aculta_discussion`; `article` e `blog` aposentados deixam de ser contextos suportados.
- A permissão contextual continua combinando acesso do Node, acesso do campo Comment, estado aberto/fechado e permissões `post comments`/`administer comments`; não cria tópicos públicos.
- A Revista declara dependência direta do subkernel `aculta_gallery`. O corpo editorial incorpora entidades `media_gallery` existentes pelo CKEditor 5/Entity Embed; upload e apresentação seguem pertencendo à Gallery/Image.
- Unit do provider: 5 testes / 28 assertions PASS. Drupal Core 11.4.10 instalado; fontes consultadas: `EntityInterface::access()` e `AccessResult::inheritCacheability()` da API oficial.
- Limpeza autorizada no Runtime: removidos pela Entity API os quatro Nodes identificados como dados de teste (NID 288, 290, 375 e 376) e seus quatro aliases próprios. Permanecem as 12 publicações legítimas; nenhuma referência recebida, Comment, Entity Usage ou redirect apontava para os Nodes removidos. Nenhum Media, arquivo ou entidade referenciada foi excluído. Backup privado atualizado antes da operação.
- O bloco da fila administrativa de comentários, presente no Config Sync mas ausente do Runtime e de `config/install`, foi incluído no pacote do Magazine e ativado como bloco do tema Claro. A View mantém acesso `administer comments` e visibilidade no purpose `main`.
- Smoke autenticado com UID 5: `/node/add` HTTP 200 lista Revista e Wiki; há apenas um tipo editorial e `article`/`blog` não aparecem. `/node/add/revista` renderiza título, subtítulo, corpo e moderação; uma publicação renderiza o formulário Comment contextual.
- Firefox headless: um comentário enviado pelo formulário frontend foi aceito como pendente (`status=0`); a interface ofereceu a ação “Aprovar”. O comentário temporário (CID 115), seu Node rascunho (NID 520) e alias exclusivo foram removidos via Entity API depois da verificação. Não houve alterações nos 12 Nodes legítimos, em comentários existentes, Media ou arquivos.
- Um 500 transitório durante o smoke foi causado por referência de hook obsoleta em cache (`Drupal\aculta_studio\Hook\StudioAccountHooks` ausente), não pelo Magazine. `drush cr` eliminou a referência; as rotas autenticadas da Revista voltaram a HTTP 200.
- Firefox headless: publicação pública inspecionada visualmente em 1280 e 390 px; título, canonical, descrição e layout público renderizam sem overflow horizontal em mobile. Uma medição adicional com sessão editorial confirmou `innerWidth=390` e `documentElement.scrollWidth=390`; os elementos que ultrapassam a viewport são painéis utilitários fixos fora da tela, não overflow do documento.
- Config Sync do Magazine agora registra os três campos virtuais Entity Print como ocultos nos displays `default`, `full` e `teaser`, conforme o estado ativo, e o peso do formato editorial espelha o Runtime (`-7`). Não houve importação de configuração. Ainda restam diferenças de permissões/dependências do workflow do Fórum não aplicado ao Runtime e hostname de Domain específico do ambiente; a importação integral continua bloqueada pelo drift concorrente.

## 0.3.0-dev.2 — consolida bundle único Revista — 2026-10-21

- Migração no Runtime de teste: 16 Nodes e suas 16 revisões passaram a `revista`,
  preservando NID, UUID, VID e os 26 aliases Path Alias. Não houve exclusão de
  conteúdo, Comments, Media ou arquivos.
- Bundles `article` e `blog` aposentados pela Entity API após confirmação de
  zero Nodes remanescentes. Drupal removeu displays e instâncias de campos por
  bundle; campos compartilhados e seus valores em `revista` permanecem.
- Formulários, Views, Pathauto, Search API, LMS Resources e comentários passam
  a selecionar somente `revista`; conversor article/blog removido.
- Backups privados foram verificados antes da migração. Runtime: HTTP 200 em
  `aculta.toca.net.br` e `revista420.aculta.toca.net.br` respondem HTTP 200 na raiz;
  `/revista` redireciona canonicamente e as páginas por sessão respondem 200.
  `/node/add/article` e `/node/add/blog` respondem 404; `/node/add/revista` redireciona
  para login anônimo. Nenhum HTTP 500 novo atribuível ao Magazine. Erros existentes de Group/LMS e e-mail
  são de trabalho concorrente e não foram alterados. As Views públicas e administrativas
  previstas foram importadas individualmente após conferir as dependências.

## 0.3.0-dev.1 — migração Entity API preparada — 2026-10-21

- Comando `aculta-magazine:migrate-publications` com dry run padrão e execução
  restrita a `toca`, `aculta_runtime` e URI do purpose Magazine de teste. Exige
  dump gzip e export de configuração ativa, ambos privados, recentes e fora do
  checkout.
- A migração recria Nodes com o mesmo NID, UUID e VID e preserva IDs, UUIDs,
  revisão, caminho e alias das entidades Path Alias. Uma transação, pós-checagens
  e falha fechada protegem as escritas; conteúdo com múltiplas revisões,
  traduções, Comments ou referências adicionais interrompe antes da alteração.
- Kernel MariaDB: 2 testes/34 assertions PASS, com duas depreciações de Core/
  contrib. Runtime dry run: 16 Nodes, 16 revisões, 26 aliases; nenhuma escrita
  de conteúdo. A migração no Runtime ainda aguarda os backups exigidos e a
  execução explícita. Gate de módulo/tema reporta duas lacunas concorrentes de
  documentação para `aculta_editor` e `aculta_lms_resources`; boundary PASS.
  RUNTIME STATUS: PARTIAL.

## 0.2.0-dev.1 — bundle editorial `revista` preparado — 2026-10-21

- Config Sync ganha o bundle canônico `revista`, com campos editoriais,
  comentários, formulários, displays e Metatag derivados do contrato existente
  de `article`. O storage de campos permanece compartilhado.
- Pathauto, Views, busca, leituras do LMS e um workflow próprio da Revista
  reconhecem `revista`; o workflow `editorial` existente permanece limitado a
  `aculta_notice`. As permissões de transição continuam por atribuir na fase de
  access/workflow, sem elevar permissões de autor.
- `RevistaPublications` reconhece o bundle novo e os dois legados durante a
  transição; os hooks de autoria e discussão podem atendê-lo. O conversor antigo
  continua restrito a `article` e `blog` e não pode mudar o bundle canônico.
- Os bundles antigos, URLs e conteúdo não foram removidos nem migrados. A
  configuração foi aplicada ao Runtime de desenvolvimento por importação
  parcial de 29 objetos do Magazine. O campo LMS foi excluído porque seu
  armazenamento ainda não existe no Runtime. A migração de Nodes, revisões,
  referências, aliases e Comments aguarda desenho validado e dump recuperável
  imediatamente anterior à alteração de conteúdo.
- Unit Magazine: 8 testes/37 assertions PASS. Gates Portal (1021), imagem,
  índice de status (33), ACULTA420 Foundation (369) e SDC (606 verificações)
  PASS; PHP lint e `git diff --check` PASS. Runtime mantém 11 Nodes `article`,
  5 `blog`, 26 aliases dos NIDs inventariados e zero `revista`; nenhum conteúdo
  foi excluído. HTTP HTTPS em `revista420` e `main` retornou 200. Watchdog nas
  últimas 24 horas não mostra erros de `aculta_magazine`/`revista`; logs do
  Apache não estão legíveis por este usuário. Gate de shell contract falha ao
  carregar `TrustedCallbackInterface`; auditoria de design foundation reportou
  3 questões preexistentes no envelope de e-mail/cores. Sem relação observada
  com o template da Revista.
  Dump pós-config: `/tmp/aculta-magazine-0.2-runtime-after-config.sql.gz.gz`
  (114,4 MB de banco, 4,9 MB comprimido, permissão 0600, `gzip -t` aprovado).
  Na visualização de diff, o Drush aceitou a confirmação sem TTY; a lista
  aplicada foi verificada depois e não incluiu objetos alheios ao Magazine.
  RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.16 — autoria editorial usa cartão compartilhado — 2026-10-20

- `ArticleAuthorsHooks` consome `aculta_user.author_card_builder` com a variante
  `author_editorial`. O Magazine fornece apenas a designação Autor/Coautor; não
  lê campos privados do perfil nem monta avatar, nome, biografia ou redes.
- O ACULTA420 renderiza o mesmo SDC de autoria usado pelos contextos do Portal.
  Firefox/X11 no Runtime confirmou a caixa editorial em 1270 e 500 px, claro e
  escuro, com a inicial de fallback quando não há avatar. Nenhum conteúdo foi
  criado ou alterado. Unit Magazine 7/31 PASS; User Unit 63/222 PASS. RUNTIME
  STATUS: PARTIAL (viewport X11 mínimo 500 px; overflow lateral de 9 px no PDF
  da publicação testada não pertence ao cartão).

## 0.1.0-dev.15 — retirar consumidores do Forum contribuído — 2026-10-20

- A Revista não depende mais de `forum:forum`: removidos o leitor de tópicos
  legados e a apresentação da abertura editorial em Nodes `forum`. A conversão
  article/blog continua re-hospedando Comments `field_aculta_discussion` e não
  preserva referências antigas.
- Retirada dos campos `field_discussao` dos displays e do Config Sync da Revista;
  a limpeza anterior confirmou zero valores no Runtime de teste. Nenhuma
  atualização/importação de configuração foi executada nesta mudança.
- Unit Magazine: 7 testes/31 assertions PASS. Gates Portal (970), fronteira
  (1390), mapa (154), sintaxe PHP e `git diff --check` PASS. No Runtime
  `toca`, cache rebuild PASS, serviço `publication_type_converter` resolve,
  Forum contrib continua habilitado e raiz da Revista responde HTTP 200.
  `config:status` já apresenta diferenças preexistentes em várias áreas; não
  foi feita importação. Smoke autenticado e conversão article/blog com Comments
  reais seguem pendentes. PHP 8.4.26 abaixo da baseline 8.5.
  RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.14 — aposentar criação de pastas Forum da Revista — 2026-10-19

- Updates 10001 e 10002 mantêm seus números de schema, mas não criam mais campo
  editorial nem termos no vocabulário `forums`; o `ForumSectionResolver`, seu
  service e os arquivos de instalação exclusivos foram removidos. O update
  10003 de alias legado permanece até a fase de retirada de Nodes.
- Unit `LegacyForumUpdatesTest` protege o contrato sem escrita. Schema do
  Runtime Magazine já está em 10003, então nenhuma atualização de banco é
  necessária nesta entrega. Unit Magazine: 8 testes/32 assertions PASS;
  PHPCS e gates Portal (970), fronteira (1383) e mapa (154) PASS. Runtime:
  cache rebuild PASS, resolver ausente, lookup histórico disponível, publicação
  renderizada e HTTP 200 após redirect. Os 12 vínculos e sua configuração ativa
  foram preservados; nenhum update/config import executado. PHP 8.4.26 abaixo
  da baseline 8.5. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.12 — retirar ajuste residual de links do Forum — 2026-10-19

- `ForumTopicHooks::commentLinksAlter` removido: era o único hook restante e
  só tratava links de Comments `comment_forum` legados. O Runtime de teste tem
  zero entidades desse bundle. `PublicationTypeConverter` continua preservando
  os vínculos `field_discussao` sem alterar tópicos antigos e re-hospedando
  Comments diretos.
- Removido o teste unitário do hook extinto. Unit do Magazine e renderização no
  Runtime foram revalidados: 7 testes/30 assertions PASS; cache rebuild, render
  full da publicação e HTTP canônico (200 após redirect) PASS. Gates Portal
  (970), fronteira (1385) e mapa (154) PASS. Nenhum dado foi alterado; não há
  Comments `comment_forum` reais para testar links. PHP 8.4.26 abaixo da baseline
  8.5. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.11 — remove API residual de criação de tópico — 2026-10-19

- Serviço `ForumTopicSync` removido e substituído por `LegacyPublicationTopicLookup`, somente leitura. `PublicationTypeConverter` ainda preserva referências históricas e Comments diretos ao trocar o bundle, sem criar nem alterar tópicos antigos.
- Update 10001 deixa de executar backfill de tópicos em instalação atrasada. Nenhuma atualização persistente foi executada nesta entrega. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.10 — encerra sincronização automática de tópicos editoriais — 2026-10-19

- `ForumTopicHooks` não cria/atualiza mais tópicos Drupal Forum em insert/update nem despublica tópicos quando uma publicação é excluída. Comentários diretos na publicação estão instalados em `article` e `blog` no Runtime de teste.
- Mantido `commentLinksAlter` somente para compatibilidade de apresentação dos tópicos históricos. Serviço de sincronização permanece temporariamente para leitura/conversão dos vínculos legados; nenhum dado foi alterado. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.9 — comentários diretos habilitados no Runtime de teste — 2026-10-19

- Config Sync e Runtime de teste agora têm `field_aculta_discussion` nos bundles `article` e `blog`; displays full renderizam o formatter Core Comment. Campos existentes têm status `Open` em memória quando não havia valor persistido; os status explícitos permanecem.
- Não houve criação de Comments nem edição de publicações. Os 12 tópicos editoriais antigos continuam referenciados e o `ForumTopicSync` permanece como fallback de código até a próxima unidade de migração.
- Testes Kernel de field compartilhado/moderação: 6 testes/81 assertions PASS em `aculta_simpletest`; Unit do Magazine 4 testes/14 assertions PASS. Teste Runtime: campo e component presentes; status `Open`; 14 publicações publicadas. Com o usuário de teste uid 5, formatter renderizou formulário Comment sem enviá-lo; nenhum tópico/comentário novo. HTTP/browser ainda não validado. RUNTIME STATUS: PARTIAL.

## 0.1.0-dev.8 — default de comentários nas publicações existentes — 2026-10-19

- `PublicationDiscussionHooks` aplica somente em memória o status configurado para itens antigos sem status, sem mudar valores explícitos `Hidden`/`Closed` nem as estatísticas do Comment. Não grava entidades nem altera Runtime.
- Teste Unit: 4 testes/14 assertions PASS; PHP lint PASS. Homologação no Runtime DEFERRED.

## 0.1.0-dev.7 — comentários nativos diretamente nas publicações — 2026-10-19

- `article` e `blog` recebem `field_aculta_discussion`, com tipo Comment `aculta_discussion_post`, status padrão aberto e formatter Core no display full.
- A troca de tipo de publicação re-hospeda comentários compartilhados para a nova entidade dentro da transação, mantendo autor, estado e parent/encadeamento.
- A criação automática antiga de tópico fica condicionada à ausência do campo novo; o trecho de comentário legado deixa de aparecer quando a discussão nativa está disponível.
- A configuração foi preparada em `config/install` e `config/sync`. Não aplicada ao Runtime; publicações existentes precisam ter o campo inicializado com status aberto após a importação e antes da ativação. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.6 — abertura do tópico do Fórum sem título repetido (F4) — 2026-10-17

Classificação: PATCH (ajuste de dados apresentados em um bloco; sem mudança de contrato).

- A abertura da Revista no tópico do Fórum não repete o título (a página já tem o título). Continua com a imagem, o resumo e o link "Ler a publicação na Revista".

## 0.1.0-dev.5 — login repetido nos comentários do Fórum da Revista — 2026-10-14

Classificação: PATCH (correção de apresentação do Core; sem mudança de contrato).

- Nos tópicos do Fórum, "Efetue login ou registre-se para postar comentários" aparecia duas vezes quando havia
  comentário: uma nos links de cada comentário e outra na área do tópico. A de cada comentário sai
  (`ForumTopicHooks::commentLinksAlter`, via `comment_links_alter`). Fica a da área do tópico.
- Verificado no Homelab: com um comentário de teste, a página do tópico mostra uma única mensagem; comentário de
  teste removido em seguida.
- RUNTIME STATUS: verificado no Homelab (HTTP e renderização dos links). Inspeção visual humana pendente.

## 0.1.0-dev.4 — troca de tipo de publicação sem quebrar o tópico do Fórum — 2026-10-14

Classificação: MINOR da linha aberta (serviço novo; sem mudança de contrato existente).

- Novo serviço `PublicationTypeConverter`: troca uma publicação entre article e blog criando a nova, copiando os
  campos, movendo o vínculo `field_discussao` e mantendo o endereço; a antiga sai depois, sem despublicar o tópico.
  Tudo em transação (falha = nada muda). Campos que só existem no tipo de origem são listados e não copiados.
- `ForumTopicSync::silently()`: grava a publicação sem disparar a sincronização do tópico (uso só da troca).
- Ao trocar a sessão, o tópico muda de pasta e o endereço segue o Pathauto. Endereço antigo responde 404 (sem redirect).
- Uso: `PublicationTypeConverter::plan()` (sem gravar) e `convert()` (grava), pelo serviço
  `aculta_magazine.publication_type_converter`.
- Comando Drush: não registrado. Tentativas com os formatos do Drush 13 não foram reconhecidas; fica pendente.
- Comentários: o tópico e seus comentários não mudam na troca. Teste no navegador: comentário de teste no tópico 87,
  ida e volta article → blog, contagem "1 comentário" na publicação e comentário no tópico em cada etapa; comentário
  de teste removido ao final.
- RUNTIME STATUS: verificado no Homelab. Trocados PodPlant420 #01 (71 → 94) e Cuidar não é crime (86 → 95) para blog na
  sessão Editorial, tópicos 78 e 87 preservados, endereços 200. Blog de teste (77) e tópico 79 excluídos.

## 0.1.0-dev.3 — cabeçalho da publicação declara a dependência de tema; Blog é Editorial documentado — 2026-10-14

Classificação: PATCH (correção de cache; sem mudança de contrato).

- Correção: publicação da Revista sem imagem de capa falhava na renderização a frio com `LogicException` do
  `VariationCache` (contexto `theme` ausente na raiz). O cabeçalho (`PostPresentation`) passa a declarar o contexto
  `theme`, de que depende (elementos `aculta_portal_element`).
- Documentação: regra "Blog é sinônimo de Editorial" em `docs/README.md`.
- Gates: Portal Drupal 11+ PASS, fronteira módulo/tema PASS, slugs PASS, endereços de ambiente PASS.
- RUNTIME STATUS: verificado no Homelab por renderização a frio (em memória) das publicações. Inspeção visual humana pendente.

# Changelog — aculta_magazine

## 0.1.0-dev.2 — Fórum da Revista: tópico automático por publicação e abertura da discussão — 2026-10-13

- Pastas do Fórum espelham as sessões da Revista: pasta-mãe "Revista" com uma pasta por categoria editorial
  (`ForumSectionResolver`). A ligação é `field_categoria_revista` no vocabulário `forums`.
- Cada publicação (article e blog) cria um tópico do Fórum com o mesmo autor, na pasta da sua categoria
  (`ForumTopicSync`, `ForumTopicHooks`). O vínculo é `field_discussao`, que já existia na publicação.
- Tópico sem cópia: a abertura (resumo, imagem e link para a publicação) é lida da publicação. A imagem é o mesmo
  arquivo da mídia, renderizado no estilo `large` (`ForumOpeningPost`).
- Título, autor, estado e pasta seguem a publicação. Excluir a publicação despublica o tópico e preserva a discussão.
- Comentários: o tópico usa `comment_forum`. A página da publicação mostra o trecho e o link para a discussão
  completa no propósito FORUM (plano E3); a resposta fica só no Fórum.
- URL do tópico: `/topico/<título>` (padrão `aculta_forum_topic`), conforme docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md.
- Permissão `access comments` para anônimo e autenticado (leitura da discussão). Moderação de comentários segue
  o padrão do Core: comentário de quem não tem `skip comment approval` entra na fila do `content_editor`.
- Atualizações: `aculta_magazine_update_10001` (campo de categoria e tópicos das publicações existentes),
  `_10002` (uma pasta por categoria) e `_10003` (padrão de URL e alias dos tópicos).
- Dependência nova: `forum:forum`.
- RUNTIME STATUS: verificado no Homelab (ciclo de vida da publicação, HTTP no host do Fórum e da Revista,
  comentário aprovado aparecendo nas duas páginas). Inspeção visual humana pendente.

## 0.1.0-dev.1 — submódulo da Revista (centraliza publicações, chamada, resumo, SEO e caixa de autores) — 2026-10-13

- Novo submódulo do `aculta_portal`. Centraliza a Revista: `RevistaPublications` (tipos de publicação) e
  `ArticleAuthorsHooks` (caixa de autores), antes em `aculta_user`.
- Tipo "blog" da Revista com chamada (`field_subtitle`) e resumo (`field_summary`), SEO (`node__blog`),
  URL por sessão e listagens por sessão.
- Chamada aparece na exibição da Notícia e do blog.
- Documentação em `docs/README.md` (regras e passo a passo para tipos novos: vídeo, galeria).
- RUNTIME STATUS: verificado no Homelab (navegador e HTTP). Inspeção visual humana pendente.
## 0.1.0-dev.13 — link de navegação da área unificada — 2026-10-19

- O menu Conteúdo oferece um link direto para a área única da Revista, onde os
  comentários editoriais já são exibidos junto às publicações.
- RUNTIME STATUS: PARTIAL. Revista aparece na árvore administrativa para uid 5
  e a rota passa a checagem de acesso. A sessão HTTP temporária não foi
  autenticada porque o ULI retornou 404.

## 0.1.0-dev.12 — área administrativa unificada da Revista — 2026-10-19

- Criada listagem exclusiva de publicações `article` e `blog` em
  `/painel-administrativo/conteudo/revista`.
- Comentários editoriais ficam incorporados à mesma página como Views block,
  filtrados por `field_aculta_discussion` e pelos bundles da Revista.
- Acesso de conteúdo usa `access content overview`; moderação exige
  `administer comments`; filtros de bundles são fixos e a área fica limitada ao
  purpose `main`.
- RUNTIME STATUS: PARTIAL; smoke HTTP e renderização da View no servidor de
  teste pendentes.


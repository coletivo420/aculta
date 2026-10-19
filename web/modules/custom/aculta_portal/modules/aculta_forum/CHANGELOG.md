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


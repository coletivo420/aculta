## 0.4.0-dev.190 — paginação do índice público Growroom420 — 2026-10-19

- A View `aculta_forum_public_topics` passa a usar pager Core completo, com 20
  itens por página e navegação traduzida; mantém filtros fixos de publicação,
  bundle, domínio e controle de acesso.
- Instalação e Config Sync permanecem equivalentes; teste Unit confirma a
  configuração de pager. Runtime de teste recebeu somente a configuração da
  View e cache rebuild; nenhum conteúdo foi alterado. Pager ativo e lista HTTP verificados; navegação >20 e inspeção visual pendentes. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.187 — contrato de respostas públicas do Fórum — 2026-10-19

- O teste de configuração do `aculta_forum` verifica equivalência do field Comment entre install/Config Sync, status aberto, formulário inline, tipo compartilhado, bloqueio de anônimos e permissão `post comments` para participantes confirmados: 8 testes / 55 assertions PASS; PHPCS PASS. Runtime de teste confirma o campo e permissão efetivos, sem conteúdo persistido. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.186 — aplica permissões próprias de tópico ao papel confirmado — 2026-10-19

- `user.role.email_confirmed` preserva as dependências, permissões existentes e peso ativos do Runtime; acrescenta só a dependência do bundle novo e `create/edit own/delete own aculta_forum_topic`. Não concede `edit any` ou `delete any`.
- PHPUnit `ForumTopicDisplayConfigTest`: 7 testes/47 assertions PASS; gate de papéis PASS. Runtime `toca`: importação parcial só do papel; uid 5 confirmado recebeu acesso à rota e formulário Core não submetido mostrou título, corpo, categoria e submit. Zero tópicos e comentários. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.185 — estado vazio da listagem do ACULTA Forum — 2026-10-19

- `aculta_forum 0.1.0-dev.16` configura mensagem de estado vazio pela área Core `text_custom` na View; domínio, bundle, publicação e acesso permanecem fixos. Sem resultados, filtros e ação autorizada continuam disponíveis.
- Unit `ForumTopicDisplayConfigTest`: 6 testes/42 assertions PASS. Runtime `toca`: importação parcial somente da View; HTTP da raiz 200 mostrou título, filtro e a mensagem vazia; `/discussoes` 404. Contagens: 0 tópicos novos e 0 comentários compartilhados. RUNTIME STATUS: PARTIAL; inspeção visual permanece pendente.

## 0.4.0-dev.184 — preserva a raiz dos purposes diante do cache de rotas — 2026-10-19

- O `DomainPurposeRequestSubscriber` restaura os marcadores da raiz no evento Request, antes do normalizador do Redirect. O cache de correspondência de rotas do Core conserva caminho e query, mas não os atributos de Request que o path processor define; sem isso, a raiz do Growroom podia redirecionar à rota interna `/discussoes`.
- Teste Unit: 8 testes/20 assertions PASS, cobrindo os purposes mapeados, caminho não raiz, purpose ausente e prioridade anterior ao Redirect. Runtime `toca`: após cache rebuild, `/` e `/?cache_probe=forum184b` retornaram 200; `/discussoes` permaneceu 404. Nenhum Node, Comment, termo ou configuração do banco foi alterado. RUNTIME STATUS: PARTIAL (fluxos de criação e inspeção visual pendentes).

## 0.4.0-dev.183 — autoria dos tópicos novos via ACULTA User — 2026-10-19

- `aculta_user 0.4.0-dev.33` integra `AuthorCardBuilder` nos Nodes `aculta_forum_topic`: User usa `author_forum`, autoria legada usa `author_growroom_historico`; apenas full/teaser. O Forum não cria cartão nem consulta dados de perfil.
- Moderação LMS continua dos Educadores com acesso pedagógico contextual; nenhuma regra LMS alterada. Unit do hook e identidade: 6 testes/23 assertions PASS, PHPCS e lint PASS. Runtime registrou o hook e renderizou `author_forum` num Node em memória; contagem permaneceu 0. A rota raiz com URL sem query respondeu 301 para `/discussoes`, enquanto a requisição de diagnóstico com query respondeu 200; causa/cache da diferença pendente de investigação visual/HTTP. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.182 — filtro de categoria no índice público do Fórum — 2026-10-19

- View pública oferece filtro hierárquico Core Taxonomy para `aculta_forum_categories`; filtro continua no domínio ativo e no bundle público novo, sem afetar o índice legado. Cache da página inclui query args.
- Unit de configuração: 6 testes/39 assertions PASS. Drupal 11+ (969), fronteira (1328), mapa (154), UI (285) e packages (22) PASS. Runtime `toca`: importação parcial só da View, plugin Core carregado, raiz e query de categoria HTTP 200; seletor presente e vocabulário vazio (0 termos). Nenhum termo ou tópico foi criado. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.181 — ação de criação condicionada por acesso — 2026-10-19

- Na listagem raiz do Growroom, a ação de criar tópico aparece apenas quando a rota Core autoriza a conta atual. Metadados de acesso são propagados para o cache da View para evitar cache cruzado de permissões/participação.
- PHPUnit Unit do Fórum: 11 testes/51 assertions PASS; PHPCS e gates Drupal 11+ (969), fronteira (1328), mapa (154), tokens (285), packages (22), slugs (387) PASS. Runtime `toca`: raiz 200, sem ação para anônimo, criação 403. Conta autorizada e navegador não validados. Nenhum formulário submetido ou conteúdo criado. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.180 — adiciona rota amigável de criação do tópico — 2026-10-19

- `/criar-topico` usa o formulário Core `node.default` para `aculta_forum_topic`, com `_entity_create_access` por bundle e classificação do purpose `forum`. Sem permissão contextual, a rota nega acesso.
- Sitemap atualizado. Runtime de teste: rota resolvida no purpose `forum`; conta sem permissão recebe 403 e purpose principal recebe 404. Nenhum conteúdo criado; formulário não submetido. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.179 — publica a listagem nova na raiz do Growroom420 — 2026-10-19

- A View `aculta_forum_public_topics` ganhou Page display em `/discussoes` e agora serve `/` no purpose `forum`. Rotas da View são classificadas no purpose correto; `/discussoes` e o índice contrib antigo `/forum` permanecem fechados para acesso direto.
- Sitemap, política de raiz e gate foram sincronizados. Importação parcial somente de `views.view.aculta_forum_public_topics`; cache rebuild e HTTP: `/` 200 com título da nova View, `/discussoes` e `/forum` 404. Nenhum conteúdo foi criado ou movido. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.178 — remove capacidades residuais de sincronização de tópicos — 2026-10-19

- `ForumTopicSync` foi substituído por `LegacyPublicationTopicLookup`, que só lê vínculos editoriais históricos. Removidos os métodos de criar, sincronizar, despublicar e backfill; o update legado 10001 já não tenta criar tópicos quando executado em ambiente ainda pendente.
- Conversão de tipo mantém referências antigas sem sincronizar o Node Forum. Nenhum conteúdo/configuração Runtime foi alterado. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.177 — encerra sincronização automática de tópicos editoriais — 2026-10-19

- Removidos os hooks de insert/update/delete que criavam, atualizavam ou despublicavam Nodes do Forum contribuído ao salvar/excluir publicação. `field_aculta_discussion` está instalado nos dois bundles editoriais do Runtime de teste; as discussões permanecem diretamente na publicação.
- Mantido somente o ajuste de links repetidos nos comentários dos tópicos históricos. Nenhum Node, Comment, termo ou vínculo legado foi alterado. RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.176 — comentários diretos ativos no Runtime da Revista — 2026-10-19

- Aplicadas parcialmente no Runtime de teste as duas instâncias Comment de `article`/`blog` e displays full; status padrão fica `Open` via configuração e hook em memória. Teste Kernel de field compartilhado e política de participação: 6 testes/81 assertions PASS. Sincronização automática de tópico antigo permanece como fallback de código até a próxima unidade; comentários não foram enviados.
- A configuração de exibição mantém oculto o extra field `private_message_link`, já oculto no Runtime, para evitar alteração de apresentação durante a ativação da discussão.

## 0.4.0-dev.175 — listagem isolada e domínio canônico do Fórum — 2026-10-19

- A View `aculta_forum_public_topics` lista somente tópicos novos publicados no domínio ativo, preservando a reescrita `node_access`; CTools Views permite configurar pager e quantidade por bloco. A View antiga `aculta_forum_topics` não foi alterada.
- `aculta_forum_topic` recebe `field_domain_source`; o hook OOP atribui o domínio canônico `forum` se estiver ausente e restringe criação ao purpose `forum`. Configuração da View não foi colocada em página/bloco nesta entrega.

## 0.4.0-dev.174 — formulários e displays nativos do tópico público — 2026-10-19

- O `aculta_forum_topic` ganha formulário com título, corpo e categoria, além de displays `full` (inclui Comment compartilhado) e `teaser` para futuras listagens. Controles de status e autoria histórica ficam fora do formulário.
- Configuração duplicada em `aculta_forum/config/install` e Config Sync; teste Unit verifica correspondência e campos expostos. No Runtime de teste, importação parcial aplicou somente os três displays; smoke de Form API em Node não salvo confirmou campos esperados e status Comment `Open`. Unit (15 testes/67 assertions) e gates Drupal 11+, fronteira e mapa PASS; Runtime PARTIAL, sem teste de navegador ou fluxo persistido.

## 0.4.0-dev.173 — status padrão das discussões editoriais existentes — 2026-10-19

- `PublicationDiscussionHooks` completa em memória o status ausente de `field_aculta_discussion` com o default da configuração do campo (fallback `Open`), sem persistir alterações, preservando status `Hidden`/`Closed` explícitos e estatísticas de comentários carregadas pelo Core.
- Teste Unit do hook: 4 testes/14 assertions PASS; PHP lint PASS. Config Sync e Runtime não foram alterados; fluxo visual/editorial em Runtime segue DEFERRED.

## 0.4.0-dev.172 — comentários no contexto das publicações — 2026-10-19

- `article` e `blog` recebem `field_aculta_discussion` com Comment compartilhado; o full display usa o formatter do Core. A conversão de tipo re-hospeda os Comments antes de remover o Node antigo.
- `aculta_user` prepara `author_comment` nos Comments compartilhados; ACULTA420 apresenta o cartão e a resposta com tokens semânticos. A regra pública de primeiro comentário já cobre Revista e demais contexts fora do LMS.
- O LMS permanece fora da moderação global: Educadores com autorização efetiva do contexto pedagógico serão responsáveis pela moderação; o contrato foi explicitado em `CONTEXTS.md` e `LMS-INTEGRATION-CONTRACTS.md`.
- O fallback de criação de tópico Drupal Forum da Revista só atua quando o campo compartilhado não está instalado. Config Sync e Runtime não foram importados; status de abertura em publicações já existentes e inspeção visual seguem pendentes. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.171 — acervo Growroom congelado — 2026-10-19

- Conteúdo de Fórum importado do Growroom e marcado por `field_autor_legado` é somente leitura, inclusive para administrador. `entity_access` nega alteração, exclusão e aprovação; a discussão do tópico fica fechada no `entity_presave`, inclusive durante a importação.
- O formulário de administração de comentários oculta posts históricos e valida IDs enviados para impedir publicação/despublicação em lote. Configurações de campo agora permitem vincular autorias legadas ao tópico e ao Comment novos.
- Drupal Forum contribuído permanece uma frente separada de retirada controlada; o acervo Growroom importado é conteúdo histórico preservado no novo motor. Nenhum Runtime ou conteúdo foi alterado nesta entrega. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.170 — moderação de tópico novo exige confirmação — 2026-10-19

- A autorização para `content_editor` moderar Nodes `aculta_forum_topic` consulta a política central de confirmação real do e-mail. A regra não amplia o papel para `edit any`/`delete any`; criação e edição própria usam permissões nativas.
- Teste Unit do access hook: 4 testes/18 assertions PASS. Validações estáticas e sintaxe ficam registradas no commit. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.169 — habilitação de permissões do tópico público — 2026-10-19

- Config Sync registra `aculta_forum` como extensão habilitada e declara as permissões nativas do bundle `aculta_forum_topic`: participantes confirmados criam/editam/excluem os próprios tópicos. Moderação por editor fica pendente de access check que valide confirmação de e-mail; não há concessão direta de `edit any`/`delete any`. O bypass administrativo permanece do Core.
- Preserva o `forum` contrib e seus dados; não houve importação de Config Sync, criação de categorias, conteúdo ou alteração persistente no Runtime. `aculta_forum` passa a `0.1.0-dev.6`. RUNTIME STATUS: DEFERRED para permissões administrativas efetivas.

## 0.4.0-dev.168 — invalidação de cache nas regras de participação — 2026-10-19

- Resultados de acesso à criação de tópicos agora incluem a cache tag de `aculta_user.settings`, o contexto de papéis e a lista de comentários. Mudanças nas opções administrativas ou nos papéis invalidam decisões de acesso em cache.
- Teste Kernel `PublicDiscussionModerationKernelTest`: 5 testes/63 assertions PASS em `aculta_simpletest`; PHPCS, gate Drupal 11+ (969 checks), PHP lint e `git diff --check` PASS. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.167 — moderação inicial compartilhada em discussões públicas — 2026-10-19

- `aculta_user` aplica a aprovação inicial em qualquer Node com `field_aculta_discussion`, exceto `aculta_lms_topic`. Comentário LMS publicado conta como participação aprovada nos demais contextos e remove o bloqueio temporário de criação de tópico.
- O painel de participação permite desativar a política pública, além dos controles existentes de limite de tópico, bloqueio enquanto há aprovação pendente e papéis isentos.
- Teste Kernel dedicado: 5 testes/63 assertions PASS em `aculta_simpletest`, incluindo comentário LMS publicado contando como aprovação, removendo bloqueio temporário e cacheabilidade de configuração/papéis. Gate Drupal 11+ (969 checks), PHPCS e `git diff --check` PASS. Fields da Revista continuam sem ativação no Runtime.
- `aculta_user` atualizado para `0.4.0-dev.29`. RUNTIME STATUS: DEFERRED para comentários novos e UI administrativa.

## 0.4.0-dev.166 — provider de contexto para comentários editoriais — 2026-10-19

- `aculta_forum` adiciona provider contextual para Nodes `article` e `blog` quando o campo Comment compartilhado está presente. Leitura usa Entity/Field Access; escrita e moderação usam permissões nativas de Comment e propagam cacheability.
- Testes Unit de `aculta_forum`: 12 testes/52 assertions. Teste Kernel em `aculta_simpletest`: 1 teste/18 assertions provou que o mesmo storage Comment pode ser anexado aos bundles `article`, `blog` e `aculta_forum_topic`.
- As regras de participação/apresentação/moderação do `aculta_user` ainda precisam reconhecer `aculta_discussion_post`; por isso o campo de publicação ainda não foi ativado.
- Cache rebuild e smoke de DI no Runtime de teste confirmaram dois providers e recusa de Article sem o field. Nenhum field editorial ou conteúdo foi criado/importado. PHP CLI 8.4.26. Runtime editorial: PARTIAL; comportamento funcional DEFERRED.
- Cartão `author_comment`, UX e avisos permanecem pendentes.

## 0.4.0-dev.165 — Context API do ACULTA Forum — 2026-10-19

- Adicionado o módulo executável `aculta_forum` com Context API extensível, provider do tópico público e configuração de instalação nativa Node/Comment/Taxonomy; configuração instalada apenas no Runtime de teste.
- Canonicalização multidomínio reconhece `aculta_forum_topic` como pertencente ao purpose `forum`, preservando o roteamento legado durante a migração.
- Corrigida a orientação ambiental: no servidor de teste `toca`, Drush sem `--uri` mostrar `http://default` é esperado para `aculta_runtime`; o host/purpose deve ser explícito em operações sensíveis a domínio.
- RUNTIME STATUS: PARTIAL — habilitado no Runtime `aculta_runtime` do servidor de teste em `growroom420.aculta.toca.net.br`; serviço e campos conferidos, zero conteúdo criado. PHP CLI 8.4.26; PHP-FPM/web 8.5 não foi confirmado.

## 0.4.0-dev.164 — cartões de autoria compartilhados — 2026-10-19

- Registra `aculta_user.author_card_builder` no inventário administrativo de Helpers; serviço com documentação própria e responsabilidade compartilhada entre o submódulo e o ACULTA420.
- RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.163 — identidade base de autoria — 2026-10-19

- O `aculta_user.author_identity_resolver` agora integra o inventário de Helpers ativos e prepara um view-model mínimo cacheável para a futura apresentação de autoria. A policy de perfil/link existente continua como fonte de acesso; não há dados de perfil privados no payload.
- Entrega coordenada com `aculta_user` `0.4.0-dev.26` e `aculta_forum` `0.1.0-dev.2`. PR #296 (Portal `0.4.0-dev.162`) permanece separado; este marcador usa a versão seguinte da linha em andamento.
- Teste Unit direcionado: 3 testes/12 assertions. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.161 — gates alinhados ao estado atual e item de menu duplicado

- Removido o item de menu `aculta_portal.role_hierarchy_settings` (Papéis e perfis sob Administração). Ele já estava desativado em tempo de execução por `AdministrativeMenuHooks` (duplicava a aba de Pessoas); a entrada do YAML saiu junto, e o gate `validate-unique-paths-texts` passa. A rota `entity.user_role.collection` continua acessível por `aculta_portal.config_papeis_e_perfis`.
- Gates corrigidos onde o código estava correto e a expectativa estava desatualizada: `validate-root-policy` (raiz da loja é `/loja`, decisão de `91b0d4ee`), `validate-account-profile-autocreate` (um tipo de perfil por função: `PerfilDoPapel::principal()` e `tipoDaConta()`), `validate-album-stage-contract` (o evento `vvj:slideChanged` é verificado onde o VVJ o emite), `validate-institution` (editor de conteúdo `revista_editorial`, e não `full_html`) e `validate-admin-cleanup` (Views exportadas do projeto e a `message` do Private Message).
- Pendências que dependem de decisão do responsável estão registradas em `docs/operations/DEBT-REGISTER.md` (DT-G03 e DT-G04).
- RUNTIME STATUS: não aplicável (sem alteração de configuração; só menu e scripts).

## 0.4.0-dev.160 — revisão do /code-review das caixas por pessoa e do diálogo do "Sim"

- Resultado de cada ação de membro na própria caixa; "Sim" do diálogo corrigido na segunda abertura (`aculta_messenger` 0.1.1-dev.50, tema `0.6.0-dev.90`).
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild).

## 0.4.0-dev.159 — Bloquear e Desfazer amizade por pessoa; validação do popup no navegador — 2026-10-19

- O menu da conversa perde "Bloquear" e "Desfazer amizade": ficam em uma caixa por outro membro (`aculta_messenger` 0.1.1-dev.48). Conversas com mais de duas pessoas passam a ter essas ações.
- Diálogos de confirmação validados no Firefox (sessão X11): abrem como modal do Drupal com título e pergunta, cancelam e reabrem, e "Sim" envia a ação (bloqueio gravado e revertido). Causa do "Sim" sem resposta: o diálogo ficava recortado pelo conteúdo de rolagem da gaveta; corrigido no tema `0.6.0-dev.89` (`data-aculta-confirming`), e o envio do "Sim" passou a informar o botão ao servidor.
- Galeria "Criar galeria" validada como modal do Drupal (título, formulário, fechar). Ressalva: o formulário de conteúdo usa o tema de administração, então a largura é a do tema administrativo; a regra de largura do `aculta420` não se aplica ali.
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild) para messenger e tema.

## Não lançado — acesso do LMS sem confirmação de e-mail

- Alinha as rotas de início de curso e respostas à decisão F7: e-mail não é pré-requisito para participar após matrícula.
- O acesso `take` do Group e a política vigente de termos continuam obrigatórios; teste Unit cobre aluno não confirmado permitido e não matriculado negado.
- Portal Unit: 219 testes/551 assertions; `aculta_lms`: 25 testes/392 assertions; `aculta_lms_h5p`: 24 testes/272 assertions. Gates Drupal 11+, imagem e slugs aprovados.
- Runtime `cursos`: PASS. Conta `agente-teste-20261019-lms-1` sem e-mail confirmado entrou na aula de revisão do curso após associação temporária ao Group; o conteúdo da atividade foi renderizado. Conta e membership removidas via Entity API ao fim; cache rebuild concluído. Nenhum dado do curso foi alterado.

## 0.4.0-dev.158 — popup-rule validado e guardrail do .htaccess — 2026-10-19

- Os 4 itens do gate de popup (`validate-portal-popup-rule.php`) estão corrigidos: `ConfirmDialog` (messenger) e `messenger-drawers.js` usam `Drupal.dialog`; `CreateGallery` (galeria) não informa tamanho, e o tema define a largura pelo token `gallery-dialog`. Gate: PASS, 0 exceções. Validação no navegador: pendente.
- `web/.htaccess`: o bloco `.mjs` (MIME do PDF.js em `<IfModule mod_mime.c>`) estava removido no disco por alteração externa. Restaurado a partir do commit. Novo gate `scripts/validate-htaccess-directives.php` reprova a remoção (testado com cópia negativa).
- Submódulos: aculta_messenger 0.1.1-dev.47, aculta_gallery 0.1.0-rc.4. Tema 0.6.0-dev.87.
- RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.157 — revisão de merge: retorno do popup, config órfã e regra de ícones — 2026-10-19

- Popup de retorno rejeita controles e espaços no destino. `/\t/host` vira `//host` no navegador depois que a tab é removida (`CheckoutPopupReturnController`). Casos novos no teste Unit.
- `composer.json`: removido o `append` de `.htaccess` que apontava para `assets/scaffold/htaccess-aculta-additions.txt`. Esse arquivo foi removido em `0.4.0-dev.155`, e a entrada anterior dizia "sem referências", o que estava errado. `composer.lock` teve só o hash atualizado.
- Config: removidos `group.role.lms_course-lms_admin` e `group.role.lms_course-lms_admin_member`. Dependiam de `user.role.lms_admin`, que `ddcbdd2c` mesclou em Educador. No Runtime de teste não havia memberships com esses group roles. O import (`cim`) deve ser feito com inspeção antes de produção.
- Doações: `/donate/{amount}/now` aceita no máximo 9 dígitos (`[1-9]\d{0,8}`).
- Teste `HerancaDePapeisTest`: sem fallback que tornava a asserção vacuamente verdadeira.
- Regra de ícones: `AGENTS.md` passa a ter a regra "Bootstrap Icons por padrão", com exceção registrada no componente. O gate de seção 12 foi removido. O ícone SVG do toolbar do LMS (`aculta_lms`) é exceção a registrar.
- Pendências fora desta entrega: avatares de `aculta_user` (~188 MB em 145 PNGs) já estavam na `main`; `.htaccess` sem o bloco `.mjs` no disco (alteração externa a esta sessão, validar com PDF.js).
- RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.156 — gates de fronteira Portal–tema corrigidos — 2026-10-19

- Gate de fronteira (`validate-module-theme-boundary.php`): nenhuma classe de design literal no módulo. Classes viraram `data-portal-ui` com tokens no tema; HTML em string virou render array ou atributo de dados (`data-aculta-imagem*` no filtro de imagem, com seletor de atributo no tema). Exceções: `h5p-content` e `h5p-editor`, contrato do H5P core (`vendor/h5p/h5p-core/js/h5p.js`, `vendor/h5p/h5p-editor/scripts/h5peditor-editor.js`), listadas no gate com o motivo. Tabledrag do CEP usa `role-weight`, marcador já previsto no gate.
- Gate de mapa módulo–tema: submódulos `aculta_lms_h5p` e `aculta_pagamentos_mercadopago` com caminho; 12 contribs habilitados listados.
- Gate de documentação de tokens: 16 tokens do mapa sem registro, 8 tokens novos e 4 atributos documentados (`data-aculta-imagem*`, `data-aculta-lazy-src`, `data-aculta-lock-mirror`).
- Regra de fronteira no README do `aculta_portal` (exigida pelo gate).
- Teste `EnrollmentRoleHooksTest` corrigido: o caso "grupo que não é curso" usava `lms_class`, que concede estudante conforme `docs/portal/PAPEIS-PARTICIPACAO.md`. Agora usa um tipo que não é curso nem turma.
- Evidência: gates de fronteira, mapa, documentação de tokens, Portal Drupal 11+, design foundations, SDC schemas, endereços e slugs passam. Unit de todos os módulos do Portal e do tema: 471 testes OK. Gate de fundação e de segurança de comércio rodados via `drush scr` (Runtime de teste, `aculta_runtime`): fundação passa após a atualização de versão; segurança de comércio falha por configuração de SMTP2GO do ambiente, não por código desta entrega.
- Pendências fora desta entrega: popup-rule (4 itens: `<dialog>` nativo em `aculta_messenger`, `dialogClass` em `aculta_gallery`, `showModal()` em `messenger-drawers.js`) pede modal do Drupal e validação no navegador; `validate-album-stage-contract` (contrato `slideChanged` do VVJ Core, contrib); `validate-role-hierarchy` (5 checagens por nome de papel, linha de base 4).
- RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.155 — revisão da PR #291: retorno do Mercado Pago, doações, H5P e `.htaccess` — 2026-10-19

- Retorno em popup (`CheckoutPopupReturnController`): o destino aceita só caminho do mesmo site (`/x`, rejeitando `//` e `/\`) ou URL de mesma origem; `javascript:` e URLs externas viram `/`. O valor vai ao script como literal JSON com `JSON_HEX_*`, sem escape manual que deixava `</script>` passar. Teste Unit `CheckoutPopupReturnControllerTest`.
- `onReturn` do Checkout Pro: sem `order` no pagamento, a checagem de vínculo usa o `external_reference` do pagamento e falha fechado; o valor registrado é o `transaction_amount` confirmado pela API (moeda igual à do pedido), não o saldo do pedido; `preference_id` é preservado ao gravar os dados do pedido.
- Doações: `/donate/{amount}/now` passa a usar `checkAccess()` (respeita `donation_route`) e aceita só valor inteiro positivo. O pane de doação recebe `current_user` por DI, e o widget de nível resolve a moeda padrão da loja em tempo de uso (`currencyCode()`), sem serviço estático em `defaultSettings()`.
- Service locator removido do `CheckoutPro::ajaxRefreshPaymentOptions()`: usa `getUserInput()` no lugar de `\Drupal::request()`.
- H5P (`H5PManagedFileStorage::deleteContent`): um arquivo recusado não interrompe a exclusão do conteúdo; a pasta é mantida quando algum arquivo ficou retido.
- `web/.htaccess`: `AddType ... .mjs` dentro de `<IfModule mod_mime.c>`. Removido o scaffold duplicado `assets/scaffold/htaccess-aculta-additions.txt`, sem referências.
- Documentação: docblock de "Opções" de `MessagesController` associado ao método correto; regra "Código no Aculta Portal, Design no ACULTA420" no README do módulo.
- Pendências desta revisão, não corrigidas aqui: `\Drupal::service('aculta_lms_h5p.framework')` na ponte estática de `H5PDrupal` (depende da refatoração do fork H5P); `DT-MP-09` segue aberto e bloqueia produção (decisão do responsável); gate de fronteira ainda marca `messages`/`feedback-section` em código do módulo e o token `requirements-cep-table` sem mapa no tema (exige autorização para `aculta420`).
- Evidência: Unit de `aculta_donation`, `aculta_lms_h5p`, `aculta_messenger` e Portal (282 testes OK, PHP 8.4.26 do servidor de testes) e Unit de `aculta_pagamentos_mercadopago` (8 testes OK). Gates: Drupal 11+ PASS, endereços de ambiente PASS, slugs PASS. Kernel não executado.
- RUNTIME STATUS: DEFERRED (código não aplicado nem testado no Runtime de teste).

## 0.4.0-dev.154 — registro dos testes de Mercado Pago e limpeza de pagamento de teste — 2026-10-19

- Pagamento real de R$ 1,00 com a preferência de produção da ACULTA foi aprovado e estornado totalmente (estorno `3395210317`).
- Pagamento de teste local (pagamento 3, pedido 13) excluído do Commerce. Pedidos mantidos. Os três pagamentos de teste de R$ 30,00 continuam aprovados no Mercado Pago (conta de teste `3735929876`), sem estorno.
- Credencial configurada como teste recusa criar pagamento e estorno (`Unauthorized use of live credentials`). Pendente: credencial de produção da conta de teste, para testes de ponta a ponta.
- RUNTIME STATUS: APLICADO (exclusão local e estorno real conferidos pela API).

## 0.4.0-dev.153 — Mercado Pago: estorno negado para doação e curso; Runtime migrado — 2026-10-18

- Regra: doação (pedido `donate_order_type`, item `donate_item_type`) e curso (pedido ou item `curso`) não têm estorno. Decidida em `MercadoPagoPaymentPolicy::bloqueiaEstorno()` (função pura) e aplicada por `RefundEligibilitySubscriber` ao evento `aculta_pagamentos_mercadopago.refund_eligibility`.
- Cancelamento de pagamento pendente é operação separada (void), não estorno, e não passa por essa regra.
- Testes: `MercadoPagoPaymentPolicyTest` 22 testes OK, incluindo o caso de doação, curso e loja. Unit Mercado Pago do Portal: 91 testes, 208 asserções, OK. Kernel do submódulo: OK.
- Gate Drupal 11+: contagem de assinantes `event_subscriber` passa de 12 para 13 (contrato atualizado com motivo no gate). Resultado: PASS.
- Runtime de teste (APLICADO e conferido): 3 métodos de pagamento de teste de `teste.comum` apagados (tipo antigo, não reutilizáveis; o pagamento concluído do pedido 13 foi mantido); plugin do gateway `mercado_pago` trocado para `aculta_mercadopago_checkout_pro`; contrib `commerce_mercado_pago` desinstalado; pasta do contrib removida do disco.
- Incidente no mesmo dia: o `composer install --no-plugins` regenerou o autoload apontando o core para `vendor/`, o que deixou os hosts de teste em 500. Corrigido com `composer install --no-scripts` (plugins ativos). Verificado: Drush, gateway, dados (46 pedidos, 1 pagamento) e hosts principal, conta e retorno respondem 200.
- Pendente: a loja `/loja-da-aculta` responde 404 porque o alias do caminho não existe no Runtime (já era assim antes desta entrega; ver backup de 2026-10-18).
- Gate de segurança do commerce (`validate-portal-commerce-security.php`) para na verificação de SMTP2GO, que é do ambiente de e-mail e não foi alterada por esta entrega.
- RUNTIME STATUS: APLICADO no Runtime de teste (conferido por Drush e HTTP).

## 0.4.0-dev.152 — método de pagamento único `aculta_mercadopago_checkout_pro` — 2026-10-18

- O único método de pagamento do Portal é `aculta_mercadopago_checkout_pro` (submódulo `aculta_pagamentos_mercadopago` 0.1.0-dev.3). A configuração do gateway `mercado_pago` passa a usar esse plugin.
- Dados existentes com o ID antigo (`mercado_pago_checkout_pro`) ainda precisam de migração no Runtime. Enquanto isso, o contrib não pode ser desinstalado.
- RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.151 — Mercado Pago com submódulo próprio; contrib removido — 2026-10-18

- Novo submódulo `aculta_pagamentos_mercadopago` (0.1.0-dev.2): porte do `commerce_mercado_pago` 3.0.0-rc3 com IDs de plugin, chave de dados do pedido e caminho de retorno mantidos. O Portal passa a depender dele no lugar do contrib.
- Removidos do `composer.json` e do `composer.lock`: `drupal/commerce_mercado_pago` e o metapacote `drupal/commerce_payment` (só era exigido pelo contrib; o módulo `commerce_payment` vem de `drupal/commerce`). `mercadopago/dx-php ^3` passa a ser dependência direta do projeto, para atualizar o SDK oficial com `composer update mercadopago/dx-php`.
- `core.extension` e a configuração do gateway `mercado_pago` apontam para o submódulo. Sem a troca, desinstalar o contrib apagaria a configuração do gateway.
- Status de requisitos: a linha do Mercado Pago agora mede o SDK (`mercadopago/dx-php`) e o submódulo.
- Documentação atualizada: integração, comércio, checkout/cobrança, plano Mercado Pago, mapa módulo-tema e runbook.
- RUNTIME STATUS: DEFERRED. Não aplicado no Runtime de teste: a desinstalação do contrib e o `cim` dependem de backup e inspeção do estado do gateway antes da alteração.

## 0.4.0-dev.150 — opções administrativas: link para Consulta de CEP — 2026-10-18

- Lista de opções administrativas do ACULTA Portal ganha "Consulta de CEP" (`aculta_portal_cep.settings`), com o banco padrão e a ordem das fontes. Ver CHANGELOG do submódulo aculta_portal_cep 0.3.0-dev.6.

## 0.4.0-dev.149 — status: tema base Bootstrap corrigido (erro falso) — 2026-10-18

- Causa verificada: a linha "Tema base Bootstrap 5" procurava o tema `bootstrap5` e o pacote `drupal/bootstrap5`. O ACULTA420 declara `base theme: bootstrap` e o projeto instala `drupal/bootstrap` 5.0.2 (`composer.json`: `^5.0`). O resultado era "Não encontrado" e erro.
- Linha corrigida para o tema `bootstrap` e o pacote `drupal/bootstrap`, com o requisito lido do `composer.json`.
- Resultado no Runtime de teste: a linha fica OK; o relatório não tem mais erro. As quatro linhas de atenção (Google, Mercado Pago RC, Turnstile e SMTP2GO) são avisos pré-deploy documentados e continuam.
- Gate: `validate-aculta-portal-drupal11.php` PASS.

## 0.4.0-dev.148 — status: seção de consulta de CEP (API e base local) — 2026-10-18

- Status do Portal (`aculta_portal.requirements`): nova seção "Consulta de CEP" com o último resultado das APIs ViaCEP e BrasilAPI (cron) e a versão da base local, com a idade em dias a partir de hoje.
- Usa a legenda e as classes de status já existentes (✔ OK, ⚠ Atenção, ✖ Erro; `requirements-status status-*`).
- Dependência nova do controlador: `aculta_portal_cep.status_report` (o Portal já depende do submódulo).
- Submódulo aculta_portal_cep 0.3.0-dev.3 (ver o CHANGELOG dele).
- RUNTIME STATUS: APLICADO no Runtime de teste; cron verificado (ViaCEP e BrasilAPI respondendo, base 1.388.509 CEPs versão 2026-10-18); estados de erro, atenção e base ausente verificados com dados simulados e restaurados.

## 0.4.0-dev.147 — perfis de equipe com o formulário padrão; CEP no checkout — 2026-10-18

- Perfis de equipe (`administrator`, `associado`, `gerente_loja`, `lms_teacher`): `field_address` passa a ser exibido com o widget padrão `address_default` (mesma configuração do perfil `authenticated`). Antes estava oculto nas configurações de formulário, então a equipe não via o endereço. Config: `core.entity_form_display.profile.{administrator,associado,gerente_loja,lms_teacher}.default.yml`.
- Checkout: a biblioteca `cep-address` passa a ser anexada ao formulário da página (`$complete_form`). Antes ficava no `#attached` do pane, que o Commerce não leva à página, então o CEP não carregava no checkout.
- Verificado no Runtime de teste: os quatro formulários de equipe montam com país oculto, Número/S/N e biblioteca; o checkout renderiza o formulário padrão e carrega a biblioteca do CEP (confirmado na lista de bibliotecas da página).
- Não verificado: teste de navegador do checkout e conferência visual dos rótulos. A página de Minha conta estava com erro inesperado no Runtime de teste por causa de alteração não commitada da frente `aculta_lms_h5p` (construtor de `H5PLibraryHooks`), não deste trabalho.
- Gate: `validate-account-address.php` (inclui a checagem dos perfis de equipe) e `validate-aculta-portal-drupal11.php`. Gates de tema não foram considerados nesta entrega, por decisão do responsável.
- RUNTIME STATUS: configuração dos perfis de equipe aplicada no Runtime de teste (`entity_form_display` dos quatro perfis, sem importação completa).

## 0.4.0-dev.146 — formulário único de endereço (Minha conta, checkout, loja e perfis de equipe) — 2026-10-18

- Novo `Account\AccountAddressForm`: o tratamento do endereço (país fixo BR oculto, rótulos em português, Número e S/N, biblioteca `cep-address` e gravação do número) sai do `PortalHooks` e passa a ser o único formulário de endereço.
- Checkout: o pane `aculta_billing_address` aplica o mesmo tratamento ao formulário inline do perfil `authenticated` (antes só Número e biblioteca; sem país fixo nem rótulos).
- Loja (`commerce_store`, campo `address`) e perfis de equipe (`administrator`, `associado`, `gerente_loja`, `lms_teacher`): novo `Hook\AccountAddressHooks` (`form_alter`) aplica o mesmo formulário e a gravação do número.
- `PortalFormCallbacks::buildStreetNumber` delega ao `AccountAddressForm`; `hideCountrySelect` do `PortalHooks` movido para `AccountAddressForm::finish`.
- Rótulos (antes aplicados num laço sobre subcampos ainda não criados) passam a ser aplicados no `#process` final, depois do processamento do endereço.
- Gates: `validate-account-address.php` PASS (19 checks, inclui o formulário único); `validate-aculta-portal-drupal11.php` PASS (961 checks; invariante do CEP movida para `AccountAddressForm` e contagem de `form_alter` de 2 para 3, motivo no próprio gate).
- Verificado no Runtime de teste (`conta.aculta.toca.net.br`): cache rebuild; teste de navegador da Minha conta 30/30; loja monta com país oculto, biblioteca e gravação do número.
- Limite: nenhum perfil de equipe exibe `field_address` hoje (oculto nas configurações de formulário); o checkout não foi testado no navegador.
- RUNTIME STATUS: APLICADO no Runtime de teste (sem alteração de configuração, sem updb).

## 0.4.0-dev.145 — cep_autocomplete portado para aculta_portal_cep (sem contrib) — 2026-10-18

- Porte do `drupal/cep_autocomplete` 1.0.0 para o submódulo `aculta_portal_cep`: controlador de consulta (ViaCEP com fallback BrasilAPI), cache, limite por IP, biblioteca `cep-address` e rota `/aculta_portal_cep/{cep}` (antes `/cep-autocomplete/viacep/{cep}`).
- Módulo contrib desinstalado no Runtime de teste (backup antes: `aculta_runtime-antes-cep-20261018-1248.sql.gz`, fora do repositório) e removido do `composer.json`, do `composer.lock` e do `core.extension.yml`.
- Não portado: `viacep.js` e `ViaCepClient` do contrib (cliente sem o cabeçalho AJAX; código morto).
- Correção no porte: a checagem de limite comparava o nome da rota antigo; agora compara `aculta_portal_cep.lookup`.
- Biblioteca `cep-address` anexada ao formulário de endereço da conta pelo `PortalHooks`; removidos o `library_info_alter` e o requisito de contrib do `EditorialHooks`/`PortalRequirementsController`.
- Gates: `validate-account-address.php` PASS; `validate-aculta-portal-drupal11.php` PASS (invariantes do hook removido e da contagem de subscribers ajustados, com o motivo no próprio gate).
- Verificado no Runtime de teste: endpoint novo responde 200 com cabeçalho AJAX e 403 sem ele; caminho antigo 404; teste de navegador do endereço 17/17.
- RUNTIME STATUS: APLICADO no Runtime de teste (módulo habilitado, contrib desinstalado, cache rebuild).

## 0.4.0-dev.144 — alias address de volta para o Mercado Pago (campo computado) — 2026-10-18

- Campo `address` nos perfis, computado, sem armazenamento: lê o `field_address` do mesmo perfil. O contrib `commerce_mercado_pago` lê `address` (DT-MP-10). O formulário da conta foi testado com o alias presente: 17/17 no navegador.
- Gate `scripts/validate-account-address.php` exige o alias como campo computado.
- Pendência: o gateway do Mercado Pago não aceita o tipo `doacao` (a condição `order_type` voltou a `default` pela reversão), então a doação avulsa ainda não chega ao pagamento.
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild).

## 0.4.0-dev.142 — endereço da conta: troca de CEP, país fixo e gate — 2026-10-18

- Troca de CEP depois do primeiro preenchimento: rua, bairro, cidade e estado são substituídos pelo endereço do novo CEP. Antes a busca só preenchia campos vazios e não atualizava. Número, S/N e complemento não são alterados.
- O CEP resolvido fica por campo no módulo (sobrevive ao rebuild de AJAX do estado).
- País fixo em Brasil como campo oculto (`hidden`). O seletor de país não aparece, e o número volta a aparecer (ver `docs/portal/ACCOUNT-ADDRESS.md`).
- Gate `scripts/validate-account-address.php` e teste de navegador `scripts/test-account-address-browser.py` (17 verificações: formulário, primeiro CEP, troca de CEP em estados diferentes e no mesmo estado, número mantido, S/N e salvar).
- Verificado no Runtime de teste (Firefox, X11): 17/17 verificações OK; endereço salvo é o do último CEP.
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild). Valores de produção não se aplicam (campo de endereço do perfil).

## 0.4.0-dev.143 — View pública do catálogo da loja — 2026-10-18

- Cria a View `aculta_shop_catalog` com página pública `/produtos` e bloco para uso no purpose SHOP. A consulta mantém fixos produto publicado e bundle `produto_loja`; filtros não são expostos nem alteráveis por CTools.
- Adiciona o modo Commerce `catalog` com título ligado ao produto, resumo e formulário de compra das variações. CTools Views no bloco permite ajustar somente pager e itens por página.
- A rota da página recebe o purpose `shop`; a permissão anônima `view commerce_product` já estava ativa e não foi alterada. Nenhum produto, checkout, gateway ou tema foi modificado.
- Configuração aplicada no Runtime de teste `aculta_runtime` em `loja.aculta.toca.net.br`; rota validada como purpose SHOP, execução da View retornou 1 produto e HTTP `/produtos` respondeu 200. Inspeção visual DEFERRED.
- A sincronização inicial apontou configuração órfã de um tema removido (`bootstrap5.settings` e seu bloco de busca); após confirmar que o tema não está ativo nem instalado, os dois objetos sem equivalente funcional foram preservados em `/tmp` e removidos do Runtime. O import parcial então concluiu com sucesso.
- RUNTIME STATUS: CONFIG APLICADA; inspeção visual DEFERRED.

## 0.4.0-dev.142 — armazenamento de mídia do editor H5P — 2026-10-18

- Editor H5P recebe mídia por upload autenticado no contexto Group; imagens raster usam `aculta_image`/UploadPolicy e Drupal File API, enquanto os demais arquivos respeitam allowlist H5P e limites de upload do PHP/Drupal.
- Armazenamento privado gerenciado registra os usos H5P e de imagem e remove referências com revalidação pelo `aculta_image`; nomes Unicode válidos são aceitos e traversal é recusado. Hub e estatísticas externas ficam desabilitados também na configuração administrativa.
- Reduz dados enviados ao JavaScript do player: o H5P recebe apenas um identificador de conta opaco, sem nome de usuário ou e-mail. H5P Core 1.28 recebe o método obrigatório `resetHubOrganizationData()`.
- Migra os hooks runtime de biblioteca, cron e exclusão de usuário para `#[Hook]` com DI; atualiza as anotações de retorno do adapter conforme as interfaces H5P instaladas.
- Unit tests do uploader/armazenamento (4 testes, 10 assertions), Kernel de descoberta de hooks/serviços (1 teste, 5 assertions) e Image Unit (19 testes, 69 assertions): PASS. Browser, validação visual e ativação no Runtime: DEFERRED; pacote de bibliotecas segue bloqueado.
- RUNTIME STATUS: DEFERRED; módulo continua desabilitado.

## 0.4.0-dev.140 — preparar assets Composer e adapter do editor H5P — 2026-10-18

- Cria adapter AJAX H5P por Group para bibliotecas instaladas, metadados, traduções e validação de parâmetros. Todos os pedidos exigem token H5P e autorização de educador no curso; tipos restritos são filtrados e uploads/Hub/instalação continuam fechados.
- Liga o campo H5P ao formulário de curso e valida biblioteca, tipo restrito, conteúdo semântico e acesso antes de salvar. A funcionalidade ainda não está pronta para educadores porque upload de arquivos continua indisponível.
- Publica imagens estáticas dos pacotes H5P Composer através de `aculta_image`, mantendo caminhos `assets://`, proveniência e uso de arquivo; SVG ativo e destinos mutáveis são recusados. O gate de imagens agora detecta regressão de cópia direta em árvores de assets.
- Testes unitários do publisher: 3 aprovados. Gates Drupal 11+, pacotes, slugs e aculta_image: PASS. `RUNTIME STATUS: DEFERRED`; módulo continua sem ser habilitado ou atualizado no Runtime.
- Próxima unidade: adaptar upload de arquivos do editor ao Drupal File API e `aculta_image`, depois executar Kernel/integração e validar visualmente o editor antes de habilitar.

## 0.4.0-dev.138 — remove callbacks legados do editor H5P — 2026-10-18

- Remove o controller H5P copiado que chamava `exit()` e lia `$_POST`/`$_FILES`; nenhuma rota o utilizava. Os endpoints do editor seguem indisponíveis até serem refeitos com Request/Response e autorização Group/LMS.
- Mantém uploads, instalação de bibliotecas e Hub desativados. O widget continua sem ser anexado ao formulário do curso; o bloqueio de assets de imagem Composer documentado em dev.137 permanece.
- Mudança de código sem instalação de módulo, atualização de schema ou alteração do Runtime.

## 0.4.0-dev.137 — fronteira de assets do editor H5P — 2026-10-18

- Auditoria do port identificou que `mirrorAssets()` publica imagens dos pacotes Composer diretamente em `assets://`, fora do fluxo obrigatório de `aculta_image`; o editor também referencia uma biblioteca Drupal inexistente e ainda não está ligado ao formulário de turma.
- Mantém editor, endpoints AJAX e uploads fechados até definir como preservar os caminhos relativos dos assets após a ingestão por `aculta_image`. A integração deve reutilizar o campo do Group, o armazenamento do H5P Core, autorização do Group/LMS e a condição CTools existente, sem criar storage ou política de upload paralelos.
- Sem alteração funcional nem atualização do Runtime. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.136 — isolamento de player e progresso H5P — 2026-10-18

- Conteúdo e bibliotecas H5P passam a usar armazenamento privado e uma rota de arquivos com allowlist de extensões, checagem de caminho canônico e controle de matrícula no curso para assets de conteúdo.
- Player envia progresso/estado somente por endpoints com token H5P, usuário autenticado e vínculo entre conteúdo e curso; exportação pública, Hub e telemetria ficam desabilitados por padrão.
- Integra CTools pela condição contextual `Course has interactive activities (H5P)`, baseada no Group do curso. Não instala Page Manager nem cria layout alternativo.
- Configuração do diretório H5P valida caminho relativo e prepara o diretório privado. Nenhuma ativação/schema foi aplicado ao Runtime nesta unidade.
- Hooks de instalação não contactam o Hub; desinstalação preserva arquivos privados e a rotina não modifica `.htaccess`. Atualização histórica de arquivos públicos fica sem efeito até haver migração explícita.
- Limite: o editor completo, uploads e instalação de bibliotecas ainda não foram portados; o módulo continua desabilitado. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.135 — Entity Mask disponível para tipos customizados — 2026-10-18

- Habilita `ctools_entity_mask` na configuração exportável e no Runtime de testes, conforme solicitado.
- Webform e Commerce não recebem máscaras: suas entidades de submissão, pedido e item usam storages especializados; a integração exigiria alterar a definição de entidades transacionais e não é compatível com a função do módulo.
- Revisado o tipo H5P customizado e os plugins do Portal/LMS; nenhum caso atual justifica compartilhar campos/displays por Entity Mask.
- RUNTIME STATUS: módulo habilitado no Runtime `aculta_runtime`; cache reconstruído. Nenhum tipo de entidade do projeto declara `mask`.

## 0.4.0-dev.134 — controles CTools para o bloco de próximas atividades — 2026-10-18

- Declara CTools 4.1 como dependência direta e habilita CTools + CTools Views no Runtime/configuração exportada.
- O bloco `aculta_activities:block_upcoming` passa a permitir ao administrador configurar pager e limite por posicionamento. A View mantém seus filtros de publicação, tipo de conteúdo, data e access; o padrão continua herdado da própria View.
- Nenhum storage de conteúdo ou filtro de acesso foi movido ao CTools.
- Runtime de teste (`aculta_runtime`, `https://aculta.toca.net.br`): módulos habilitados, configuração ativa confirmada e cache reconstruído. Filtros e ordenação da View foram conferidos após a alteração. A importação automática das traduções pt-br do CTools falhou porque `translations://` não está gravável; isso não impediu a habilitação nem o uso dos controles.

## 0.4.0-dev.133 — painéis H5P vinculados ao LMS — 2026-10-18

- Cria painéis administrativos, de estudante e educador com rotas e navegação próprias. O painel do estudante lista somente cursos cujo `take course` permite acesso e que têm atividades; o do educador lista somente cursos em que pode atualizar o Group. Links de curso usam `DomainPurposeManager`.
- Vincula o campo de atividades H5P ao bundle `lms_course`, corrige nomes e requisitos de rotas administrativas copiados do original e cria abas de navegação do H5P.
- O editor ainda não é habilitado: o módulo original não portava a integração completa `h5peditor`; seus endpoints precisam ser refeitos com autorização por Group e sem `exit()`. H5P permanece desabilitado e sem schema no Runtime.
- RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.132 — inicia o fork ACULTA H5P — 2026-10-18

- Remove `drupal/h5p` do Composer e cria o submódulo `aculta_lms_h5p`, preservando licenças e atribuição do código derivado. Core/Editor PHP permanecem como dependências explícitas.
- O fork remove a rota pública de embed sem autorização do curso, falha fechado para conteúdos que exigem iframe e desabilita upload de pacotes até integrar imagens via `aculta_image`.
- `aculta_patcher` permanece ativo como ferramenta genérica; o patch Composer fica somente no `h5p/h5p-editor`.
- H5P permanece desabilitado. A implementação contém APIs herdadas que precisam de refatoração DI/Hook e testes de autorização por turma antes da habilitação.
- RUNTIME STATUS: pacote Drupal removido da árvore Composer local; nenhum schema/configuração do novo módulo instalado. O registro de patch antigo para `drupal/h5p` foi removido do Runtime de teste após consulta e backup privado disponível.

## 0.4.0-dev.128 — move o módulo pai para "ACULTA Portal" e cria barreira de pacote — 2026-10-18

- O módulo `aculta_portal` passa a declarar `package: 'ACULTA Portal'`, junto com todos os submódulos próprios. A caixa "Associação Cultural Antiproibicionista" deixa de ter módulos próprios em `/painel-administrativo/modulos`.
- Novo gate `scripts/validate-own-module-packages.php`: todo módulo próprio em `web/modules/custom` deve declarar `ACULTA Portal`, sem exceção. Orientação em `docs/portal/COMPONENTES-PROPRIOS.md`.
- Sem mudança funcional.

## 0.4.0-dev.127 — agrupa os submódulos próprios em "ACULTA Portal" — 2026-10-18

- Os submódulos próprios (`aculta_associado_id`, `aculta_donation`, `aculta_gallery`, `aculta_game`, `aculta_id_cpf`, `aculta_image`, `aculta_magazine`, `aculta_messenger` e `aculta_user`) passam a declarar `package: 'ACULTA Portal'`, a mesma caixa de `aculta_deployer`, `aculta_lms`, `aculta_pdf_viewer` e `aculta_portal_sitemap`, em `/painel-administrativo/modulos`.
- O módulo pai `aculta_portal` permanece em "Associação Cultural Antiproibicionista"; a mudança cobre somente os submódulos.
- Nenhuma mudança funcional: o rótulo do pacote não é lido por código, gate ou configuração exportada.
- Runtime de teste (`aculta_runtime`): `drush cr` concluído; rótulos verificados via `extension.list.module`. RUNTIME STATUS: APLICADO; conferência visual de `/painel-administrativo/modulos` pendente.

## 0.4.0-dev.126 — reconcilia configuração F7 do LMS — 2026-10-18

- Migra as configurações de doação para o provider `aculta_donation`, removendo referências à extensão contrib que já saiu do Composer.
- Habilita `aculta_lms`/`lms_classes` no Runtime de teste e importa parcialmente 29 objetos F7, sem remover configurações ausentes.
- Os objetos F7 preparados coincidem com Config Sync; a configuração de criação automática de turma está desligada.
- Backup privado prévio: `/home/aculta/private/aculta/config-reconcile-20261018/aculta_runtime-before-f7.sql.gz` (gzip verificado).
- `drush updatedb`, Config Sync parcial e `drush cr` concluídos. RUNTIME STATUS: CONFIG APLICADA; smoke de matrícula, checkout, reembolso e isolamento permanece DEFERRED. Restam 284 diferenças globais registradas em DT-O10.

## 0.4.0-dev.125 — corrige duplicidade na configuração de administrador — 2026-10-17

- Removida a chave `permissions` vazia duplicada de `user.role.administrator`; Drush recusava carregar todo o Config Sync por YAML inválido.
- Nenhuma mudança de permissões do administrador. `config:status` voltou a funcionar; importação adiada porque há mudanças não relacionadas ao F7 no Sync, inclusive checkout, gateway, Key e Domain, e faltam diffs/recuperação específicos.

## 0.4.0-dev.124 — matrícula comercial no LMS — 2026-10-17

- Cursos apontam a um produto Commerce próprio; a página do curso inclui escolha de turma e envia a turma no item Commerce.
- O checkout `curso` exige conta e mantém os termos gerais, com exceção pontual da confirmação de e-mail nessa rota.
- Pedidos concluídos concedem membership; reembolso parcial mantém acesso e estorno integral revoga apenas a membership de origem, preservando associação manual e outras compras válidas.
- A autoinscrição direta do LMS é bloqueada; matrícula da equipe permanece nativa do Group e exige turma. Educador pode remover origem manual, com proteção de memberships comerciais ainda ativas.
- Lint PHP e gate Drupal 11+ PASS (948 checks). RUNTIME STATUS: DEFERRED; configuração não importada, `lms_classes` segue desativado.

## 0.4.0-dev.123 — situação da última cobrança da doação mensal — 2026-10-17

- Cada cobrança da assinatura guarda sua situação no pedido: `paga`, `nao_cobrada`, `recusada`, `processando`, `agendada`,
  `cancelada` e `desconhecida` (`MercadoPagoSubscriptionRecorder::situacaoCobranca`). Só `paga` grava pagamento.
- Meu apoio mostra a situação da última cobrança; o painel de doações recorrentes tem a coluna "Situação da última cobrança".
- Testes unitários: mapeamento de cada status de cobrança, cobrança recusada sem pagamento, cobrança paga (48 no grupo).
- Verificado no sandbox: pausar em `pending` é recusado; cancelar em `pending` funciona (ver `docs/portal/MERCADO-PAGO-PLANO.md`, 7.1).
- RUNTIME STATUS: APLICADO em código no Runtime de testes (rótulos e relatório verificados por script). DEFERRED: cobranças e
  ações sobre assinatura `authorized` (bloqueadas pela recusa de criação, ver 7.1).

## 0.4.0-dev.122 — corrige erro na página de credenciais — 2026-10-17

- Corrigido o namespace de `Markup` usado na listagem de credenciais. O namespace incorreto causava erro fatal
  quando a página renderizava uma credencial já preenchida.
- Verificação no Runtime de testes: Drush conectado a `aculta_runtime` com URI explícito `https://aculta.toca.net.br`;
  cache rebuild concluído e controller retornou as duas seções do render array. A requisição HTTP anônima continua
  recebendo 403 pela permissão administrativa; não havia sessão autenticada disponível para smoke visual.

## 0.4.0-dev.122 — ações da doação mensal e painel de recorrentes — 2026-10-17

- "Meu apoio": doação mensal mostra a situação da assinatura e as ações pausar, reativar e cancelar (com confirmação).
  Só o dono do pedido aplica a ação, e só no status que permite (`MercadoPagoSubscriptionRecorder::ACOES`). Falha do
  Mercado Pago não altera o pedido.
- Rota `/meu-apoio/assinatura/{commerce_order}/{acao}` (`SubscriptionActionForm`), no domínio da conta.
- Relatório de doações (`/painel-administrativo/loja/doacoes`): seção "Doações recorrentes" com o resumo por situação e as
  100 assinaturas mais recentes (`Donation\RecurringDonationsReport`).
- Status de assinatura e de cobrança tratados explicitamente; cobrança com status desconhecido é registrada no log.
- Testes unitários: `MercadoPagoSubscriptionActionsTest` (6). Teste do relatório de doações atualizado.
- RUNTIME STATUS: APLICADO em código no Runtime de testes (rotas e relatório verificados por script, sem pedidos de assinatura
  no banco). DEFERRED: ações e pagamentos reais no sandbox do Mercado Pago (escrevem na conta de teste).
- Gate `validate-role-hierarchy` em FAIL por checagem de papel de `aculta_user` (`ForumModerationHooks`, commit e35aecc0),
  fora deste trabalho: não foi alterada a linha de base do gate.

## 0.4.0-dev.121 — gravação das assinaturas de doação — 2026-10-17

- `MercadoPagoSubscriptionRecorder`: grava no pedido `assinatura` o status da assinatura (tópico `subscription_preapproval`) e
  cobranças aprovadas como pagamentos (tópico `subscription_authorized_payment`), uma vez por id do pagamento. Cobrança só é
  gravada com `payment.status = approved`; `processed` sozinho não basta.
- `MercadoPagoSubscriptionNotificationService` passa a chamar o gravador, em vez de só registrar log.
- Testes unitários: `MercadoPagoSubscriptionRecorderTest` (5) e `MercadoPagoSubscriptionNotificationServiceTest` (3, com gravador real).
- RUNTIME STATUS: DEFERRED para o ponta a ponta no sandbox do Mercado Pago (depende de confirmação; ver `aculta_donation` 0.1.0-dev.16).

## 0.4.0-dev.120 — carrinho unificado em um pedido, com o fluxo mais exigente — 2026-10-17

- Decisão do responsável (2026-10-17): todo o carrinho (exceto assinatura) é finalizado num pedido só, com o fluxo de
  checkout mais exigente entre as categorias presentes: LMS (`curso`) > loja (`default`) > doação (`doacao`).
- `Cart\CartConsolidator` move os itens dos carrinhos para o pedido de destino, apaga os carrinhos vazios e torna o pedido
  o carrinho ativo. Rota `/carrinho/finalizar` (`aculta_portal.cart_checkout`) junta e abre o checkout no MAIN.
- Painel: um botão "Finalizar carrinho" para as categorias; assinatura em espaço próprio, com botão próprio.
- Pendente: o tipo `curso` usa, por enquanto, o fluxo padrão da loja; o fluxo do LMS com o painel de CPF
  (`aculta_id_cpf`) ainda será criado.
- RUNTIME STATUS: APLICADO no Runtime de testes (consolidação verificada: dois carrinhos viraram um pedido, o carrinho antigo
  foi apagado).

## 0.4.0-dev.119 — página inicial da loja com os produtos disponíveis — 2026-10-17

- A raiz de `loja.aculta` (rota `/loja`) mostra o texto da página inicial da loja e, abaixo, um cartão por produto publicado,
  do mais recente para o mais antigo, com o menor preço das variações publicadas. Cada cartão leva à página do produto.
- Cartões com a apresentação do tema (token `shop-product-card`), pelo elemento `aculta_portal_element`.
- RUNTIME STATUS: APLICADO no Runtime de testes (raiz da loja 200 com o produto e o preço; página do produto 200).

## 0.4.0-dev.118 — raiz da loja pela rota /loja, como os outros subdomínios — 2026-10-17

- A raiz de `loja.aculta` é servida pela rota `aculta_portal.shop_home` (`/loja`), no mesmo padrão de `/apoio`, `/wiki` e
  `/cursos`. A rota mostra o nó 372 "Loja da Associação".
- `/loja` e o nó 372 não são públicos direto: 404 (como as outras raízes). Nenhum redirecionamento.
- RUNTIME STATUS: APLICADO no Runtime de testes (raiz da loja 200, sem redirecionamento; produto na loja 200, no principal 404).

## 0.4.0-dev.117 — produtos da loja no domínio da loja — 2026-10-17

- A raiz de `loja.aculta` serve a página inicial da loja (nó 372) sem redirecionar. Os aliases públicos antigos da loja
  (`/loja-da-associacao` e `/loja-da-aculta`) foram removidos; o caminho interno é `/node/372`, que responde 404 direto.
- Produto da loja: a página do produto responde no domínio da loja (200) e 404 no principal, como manda a política.
- RUNTIME STATUS: APLICADO no Runtime de testes. Conteúdo da página inicial da loja mudou de formato de texto (`basic_html`,
  inexistente no Runtime, para `restricted_html`), sem alterar o texto.
- Pendente: listagem de produtos no domínio da loja (ainda não há catálogo; só a página de produto).

## 0.4.0-dev.116 — painel do carrinho com itens e contador corretos — 2026-10-17

- Correção: o painel do carrinho derrubava a página inteira (erro 500) quando havia pedido de doação no carrinho, porque o
  passo do checkout era resolvido dentro da montagem do painel. O painel agora usa o passo guardado no pedido; o Commerce
  corrige o passo na própria etapa.
- O contador e a lista do carrinho são atualizados pelo "Adicionar ao carrinho" sem recarregar a página: a resposta AJAX
  substitui o corpo do painel (`aculta_portal_cart_panel`, com template do tema).
- RUNTIME STATUS: APLICADO no Runtime de testes (página inicial com carrinho de visitante responde 200 com o contador e o
  separador; página completo do carrinho no principal mostra o item). Clique real de "Adicionar ao carrinho" não disparado
  por HTTP (exige o formulário do produto).

## 0.4.0-dev.115 — carrinho em lista única — 2026-10-17

- O carrinho deixa de ter abas: uma lista única com uma linha de separador por site (Loja, Cursos, Apoio), com total do site.
- Ordem: o site do item adicionado por último vem primeiro; dentro de cada site, do item mais recente para o mais antigo.
- Cada item é uma caixa igual às de aviso e mensagens (alerta do Bootstrap com tokens do tema), com X para fechar a caixa.
  O X só fecha a caixa na tela; não remove o item do carrinho.
- RUNTIME STATUS: APLICADO no Runtime de testes (ordenação verificada com três itens de sites diferentes).

## 0.4.0-dev.114 — carrinho único com separadores e checkout no principal — 2026-10-17

- Decisão do responsável (2026-10-17, versão final): um carrinho único. O painel da barra agrupa os itens em separadores
  por tipo de pedido (`default` = Loja, `curso` = Cursos, `doacao` e `assinatura` = Apoio), com um botão de checkout por
  separador. Carrinho, checkout e pagamento ficam no MAIN; o apoio só escolhe o valor.
- `Cart\CartSite` (mapeamento tipo de pedido → separador) e `Cart\CartUtility` agrupado por separador.
- Painel do carrinho (`cart-offcanvas`): abas e um botão "Finalizar" por separador, no checkout do MAIN.
- Etapas de checkout da doação (`aculta_donation.donation_controller_formPage`) no MAIN; a escolha de valor continua no SUPPORT.
- Rotas de entrada de doação sem cache (`no_cache`): cada visita tem o próprio pedido e a própria sessão.
- Retorno e notificação do gateway no MAIN (`PaymentDomainPathProcessor`, prioridade 1000).
- RUNTIME STATUS: APLICADO no Runtime de testes (agrupamento verificado por separador; entrada de doação chega à etapa no MAIN com
  a sessão). Pagamento real não concluído (exige o comprador de teste).

## 0.4.0-dev.113 — fluxo de apoio e assinatura no domínio de apoio — 2026-10-17

- Decisão do responsável (2026-10-17): o fluxo de apoio e assinatura (`aculta_donation.*`) roda no purpose SUPPORT.
  Carrinho, checkout e pagamento da loja continuam no MAIN.
- Só o gateway fica no MAIN: notificação e retorno/cancelamento do pagamento. `PaymentDomainPathProcessor` gera essas URLs
  com o domínio principal, qualquer que seja o host da requisição (prioridade 1000, antes da troca de alias).
- `DomainRoutePolicy` separa o fluxo de doação (SUPPORT) da transação da loja (MAIN). Teste unitário atualizado.
- Gates: `validate-payment-domain-policy` confere o propósito de cada rota de doação; `validate-aculta-portal-drupal11`
  confere a rota de valores no bloco.
- Documentação: `PAYMENT-DOMAIN-POLICY`, `CROSS-DOMAIN-REQUEST-POLICY`, `ANTI-REGRESSION` e `AGENTS.md` atualizados.
- RUNTIME STATUS: APLICADO no Runtime de testes (URLs de pagamento verificadas no MAIN; entrada de doação no apoio verificada).
  Pagamento real no Mercado Pago não foi concluído nesta verificação (exige o comprador de teste).

## 0.4.0-dev.112 — um só conjunto de valores na página de apoio — 2026-10-17

- A lista de valores do formulário de apoio ("Escolha um valor") saiu. Os valores ficam só no bloco de doação rápida
  (aculta_donation), que lê a mesma fonte: campanha vigente, depois "Valores padrão" do painel. A opção "Outro" leva
  ao passo de doação, onde se digita o valor.
- Valores padrão do painel: R$ 5, 10, 20 e 50.
- Gate de P5.1 ajustado: a rota de valores sugeridos passa a ser verificada no bloco, não no formulário.
- RUNTIME STATUS: APLICADO no Runtime de testes (página de apoio: um bloco, 4 valores e "Outro", sem a lista antiga).

## 0.4.0-dev.111 — cadastro em modal para o checkout de doação — 2026-10-17

- Cadastro em modal (`/criar-conta/janela`): formulário `RegisterPopupForm` (estende o cadastro do Core) registrado como
  handler `register_popup` da entidade de usuário (`RegisterPopupHooks`). Após o cadastro, volta ao destino.
- Sem Turnstile no cadastro em modal, por decisão do responsável: exceção em `TurnstileToggle::EXCEPTION_FORM_IDS`,
  ponto de CAPTCHA desligado em config/sync e documentação em `docs/portal/TURNSTILE.md`. O cadastro comum
  (`user_register_form`) continua protegido.
- RUNTIME STATUS: APLICADO no Runtime de testes (modal responde 200, sem campos de CAPTCHA).

## 0.4.0-dev.110 — estorno bloqueado para doações — 2026-10-17

- Tabela de vendas não mostra "Estornar" para pedido com item de doação (doações e assinaturas não têm estorno). Loja inalterada.
- RUNTIME STATUS: APLICADO e verificado por API no Runtime de testes (checagem de rota negou o estorno de pedido de doação).

## 0.4.0-dev.109 — port do commerce_donation_flow para o aculta_donation — 2026-10-17

- O fluxo de doação passa a ser do submódulo `aculta_donation` (port). Rotas `aculta_donation.*`; referências do Portal
  (`DonationSummaryController`, `SupportForm`, `DonationFriendlyRouteSubscriber`, `DomainRouteSubscriber`,
  `DomainRoutePolicy`) atualizadas.
- Entrada de requisito do contrib removida de `PortalRequirementsController`. O gate de segurança de comércio passa a
  conferir que o pacote contrib não está instalado.
- Gates atualizados: `validate-aculta-portal-drupal11`, `validate-payment-domain-policy`, `validate-portal-commerce-security`.
- Documentação de política (`ANTI-REGRESSION`, `CROSS-DOMAIN-REQUEST-POLICY`, `PAYMENT-DOMAIN-POLICY`) e mapa de módulo x tema.
- RUNTIME STATUS: APLICADO no Runtime de testes (corte com backup; ver CHANGELOG do `aculta_donation`). Gates locais: PASS.
  Pendente: recorrência e testes de Kernel do fluxo.

## 0.4.0-dev.108 — remove aba vazia de Configurações > Pessoas — 2026-10-17

- Remove a aba "Pessoas": a página só mostrava uma frase e não tinha ferramenta.
- `/painel-administrativo/configuracoes/pessoas` agora abre a aba Papéis (redirecionamento). Sem isso, a página-base não teria aba nenhuma.
- As cinco abas restantes (Papéis, Campos de Perfil, Segurança, Conta) continuam.
- RUNTIME STATUS: APLICADO e verificado com o gerenciador de abas do Core no Runtime de testes.

## 0.4.0-dev.106 — abas agrupadas em Configurações > Pessoas — 2026-10-17

- Dez abas viram cinco abas principais: Pessoas, Papéis, Campos de Perfil, Segurança e Conta.
- Sub-abas (`parent_id`, o mecanismo de dois níveis do Core): Papéis > Hierarquia, Permissões por papel, Configurações de papel;
  Segurança > Controle de sobrecarga, Desbloqueio, CAPTCHA; Conta > Opções da conta, Acordos.
- Todas as páginas mantêm a linha principal, inclusive Permissões por papel e Configurações de papel (antes só aparecia a sub-linha).
- Sem mudança de rotas nem de caminhos: as ferramentas continuam nas mesmas URLs.
- RUNTIME STATUS: APLICADO e verificado com o gerenciador de abas do Core no Runtime de testes (`aculta.toca.net.br`, `aculta_runtime`).

## 0.4.0-dev.105 — Configurações > Pessoas em abas — 2026-10-17

- `/painel-administrativo/configuracoes/pessoas` vira página de abas (Pessoas, Papéis e perfis, Permissões por papel, Configurações de papel, Campos de Perfil, Flood Unblock, CAPTCHA, Controle de sobrecarga, Opções da conta, Acordos), como a seção Pessoas.
- Itens de menu correspondentes saem da lista da página (`AdministrativeMenuHooks::EM_ABAS`), para não repetir nomes.
- A página-base não usa mais a lista de links do Core, que negaria acesso sem itens visíveis: `ContaAdministracaoPaginaController`.
- RUNTIME STATUS: APLICADO e verificado por HTTP: página 200 com as dez abas; sem destinos com dois nomes em Pessoas e Configurações > Pessoas.

## 0.4.0-dev.104 — separação Pessoas x Configurações > Pessoas — 2026-10-17

- Pessoas (`/painel-administrativo/pessoas`) fica com usuários individuais: Lista, Adicionar usuário, Perfis (`pessoas/perfis`) e Social auth (OAuth).
- Ferramentas de administração de contas saem de Pessoas para Configurações > Pessoas, com caminhos e subpáginas: papéis (`configuracoes/pessoas/papeis`), permissões por papel (`permissoes`), configuração de papel (`configuracoes-de-papel`) e desbloqueio de sobrecarga (`desbloqueio-sobrecarga`). Campos de Perfil já estava lá.
- Abas de Pessoas correspondentes são removidas (`AdministrativeLocalTaskHooks`); itens de menu de conta em Configurações > Pessoas (`aculta_portal.links.menu.yml`, pai `user.admin_index`).
- Caixa "Telas do núcleo" removida da página de papéis (as ferramentas agora estão em Configurações > Pessoas).
- RUNTIME STATUS: APLICADO e verificado por HTTP: abas de Pessoas = Lista, Social auth profiles, Perfis; caminhos antigos 404; novos 200; nenhum destino com dois nomes nas páginas de Pessoas e de Configurações > Pessoas.

## 0.4.0-dev.103 — tradução restrita à configuração de pessoas; duplicações do menu removidas — 2026-10-17

- Tradução da árvore `configuracoes/people` para `configuracoes/pessoas` (ex.: `campos-de-perfil`, `contas`, `acordo`). A área operacional `pessoas/...` mantém os caminhos do Core. A tradução anterior (0.4.0-dev.102) foi revertida.
- Subscriber executa depois da renomeação do prefixo admin (prioridade -3000).
- Hook `menu_links_discovered_alter` desativa os itens de menu duplicados das abas de Pessoas ("Papéis e perfis" e "Profile types").
- Link duplicado "Permissões por papel" removido da caixa de telas do núcleo.
- RUNTIME STATUS: APLICADO e verificado por HTTP: configuração em `configuracoes/pessoas`, caminhos antigos 404, nenhum destino com dois nomes nas páginas de Pessoas.

## 0.4.0-dev.101 — Campos de Perfil como aba de Pessoas — 2026-10-17

- Tipos de perfil (Core) viram a aba "Campos de Perfil" da seção Pessoas (`aculta_portal.links.task.yml`, base `entity.user.collection`).
- Título da página de tipos de perfil passa a ser "Campos de Perfil".
- RUNTIME STATUS: APLICADO e verificado por HTTP (aba presente em Pessoas; título na página de tipos).
## 0.4.0-dev.102 — cache versionado e matriz Chromium PV7 — 2026-10-17

- API e worker PDF.js recebem `?v=6.4.299`, sincronizado com Composer, para não reutilizar uma combinação antiga com cache público de um ano.
- Chromium 155.0.8059.39 no Runtime renderizou PDFs de 1 MiB e 22 MiB (14 páginas), sem erros JS severos; PDF inválido exibiu fallback. Com cache desabilitado, throttling de 700 ms/64 KiB/s concluiu em 20,9 s. Proxy local simulou 503 e o fallback apareceu. CSP local com `worker-src 'self'` renderizou; fontes externas foram bloqueadas pela política.
- Cache/Range no Runtime: documentos HTML privados sem cache; PDF e assets estáticos com `max-age=31536000`; Range PDF respondeu 206/1024 bytes. `content_editor` tem permissão `create document media`; autoria/upload via UI não testado por falta de sessão de teste do papel.
- A tentativa com identidade temporária `content_editor` chegou ao formulário, mas a tela obrigatória de aceite de termos bloqueou a autoria. Nenhum aceite foi registrado e nenhum Media/File foi enviado. A conta temporária e seu perfil automático foram removidos após confirmar que não possuíam node, Media, pedido ou perfil remanescente.
- Produção pública serve `pdf-viewer.mjs`, `pdf.mjs` e `pdf.worker.mjs` como `text/plain`, com cache de um ano. A regra `.htaccess` foi aplicada apenas ao Runtime; confirmar MIME e viewer na produção após deploy normal.
- RUNTIME STATUS: APLICADO. CSP estrita, acessibilidade ampliada e autoria/UI seguem DEFERRED.

## 0.4.0-dev.101 — MIME de módulos PDF.js e homologação PV7 — 2026-10-17

- `web/.htaccess` declara `.mjs` como `application/javascript`, necessário para o PDF.js e seu worker módulo; Apache do Runtime retorna esse MIME nos dois assets. A produção ainda retorna `text/plain` no worker antes do deploy desta mudança.
- PDF.js 6.4.299 e licença Apache-2.0 conferidos no Composer e distribuição instalada. Unit 11/11 (145 assertions), Kernel 5/5 (106 assertions; quatro deprecações preexistentes), gate Drupal 11+ (892 checks) PASS.
- Firefox 153.4 ESR no Runtime: PDF de 1 MiB e PDF de 22 MiB renderizaram 14 páginas; o grande transferiu 23.036.752 bytes em 2,0 s. PDF inválido exibiu fallback autorizado. Homologação Chromium e rede lenta não executadas.
- Runtime sem cabeçalho CSP; produção envia apenas `upgrade-insecure-requests` nas respostas verificadas. Isso não equivale a validar uma política CSP estrita. Acesso privado já tem matriz PV5; sem nova mutação dos fixtures nesta rodada.
- RUNTIME STATUS: APLICADO. Produção LiteSpeed/Hostinger e MIME pós-deploy DEFERRED; nenhum deploy executado.

## 0.4.0-dev.100 — operações e caixa de controle na página Papéis e perfis — 2026-10-17

- Biblioteca `core/drupal.dropbutton` anexada ao formulário: o menu de operações (editar, permissões, excluir) depende dela.
- Caixa "Telas do núcleo" (permissões por papel e tipos de perfil) restaurada na página.
- Exclusão de papel pela página testada no Runtime: papel temporário excluído pelo formulário de confirmação do Core; cadeia inalterada.
- RUNTIME STATUS: APLICADO. Abertura do menu de operações no navegador não verificada.
## 0.4.0-dev.99 — página Papéis e perfis substitui a lista de papéis do Core — 2026-10-17

- `/painel-administrativo/pessoas/roles` passa a ser a página Papéis e perfis do ACULTA (formulário `RoleHierarchyForm`, rota do Core com `_form`). A lista do Core sai dessa página.
- Coluna Operações (editar, permissões e excluir, pelo `buildOperations` do Core) em cada papel; botão Adicionar papel; tabela dos papéis fora da cadeia.
- Permissão da página: `administer permissions` ou `administer aculta role hierarchy`.
- Rota e controlador antigos da página separada removidos; item de menu aponta para a página de papéis.
- RUNTIME STATUS: APLICADO e verificado por HTTP: página 200 com operações, botão e tabela; `/configuracoes/aculta/papeis` 404; salvar a cadeia responde 303 e mantém a configuração.
## 0.4.0-dev.98 — papéis laterais removidos; cadeia única — 2026-10-17

- Não há mais papel lateral nem categoria: a cadeia é a única classificação. Papel fora da cadeia herda do autenticado (regra implícita em `RoleHierarchy::derivarMapa`).
- Tela sem a coluna "Entram nesta categoria"; a configuração `laterais` e seu schema foram removidos.
- Funções simplificadas: `derivarMapa(cadeia, todos)`, `completar(cadeia, existentes)`; `herdados` e `efetivos` inalterados.
- Testes: `RoleHierarchyTest` (14); `HerancaDePapeisTest` (4, no aculta_user).
- RUNTIME STATUS: APLICADO e verificado por HTTP: coluna removida, coluna de herança efetiva presente, salvar com a ordem atual responde 303 e mantém a cadeia.
## 0.4.0-dev.98 — PV6: Wiki e Fórum no viewer compartilhado — 2026-10-17

Classificação: MINOR (CKEditor Media Embed compartilhado em Wiki e Fórum).

- `filter.format.wiki` e `filter.format.usuario_aculta` selecionam somente Media `document`; ambos têm botão `drupalMedia` e pares CKEditor 5 validados sem violações. Source Editing conserva o HTML legado permitido pelos filtros.
- O display default do Fórum usa `text_default`, necessário para processar embeds sem truncar o corpo. A autorização da Media permanece no Core e no viewer compartilhado.
- Unit 11/11 (145 asserções), Kernel 5/5 (106 asserções); Drupal 11+ (896), aculta_image, public-slugs e fronteira módulo/tema PASS.
- Runtime de teste: configuração Wiki/Fórum aplicada por entidades específicas, cache rebuild; render Drush do tópico de Fórum contém viewer/link permitido. Artigo, blog e projeto responderam HTTP 200 com viewer. Firefox ESR validou 14 páginas, zoom 125%, foco 3 px, nomes dos controles, alvos ≥44 px e tela estreita sem overflow. Nenhum conteúdo foi criado ou alterado nesta etapa.
- Config Sync global não foi importado; permanece a duplicidade YAML preexistente em `user.role.administrator.yml`.
- RUNTIME STATUS: APLICADO. Matriz ampla de browsers/CSP e produção seguem PV7.

## 0.4.0-dev.97 — função única para a configuração de papéis — 2026-10-17

- `RoleHierarchy::completar`: uma só regra para cadeia e laterais de papéis existentes (papel excluído, papel novo, categoria órfã). Usada pelo formulário (estado inicial e laterais) e pelo aculta_user (mapa e exclusão). Antes havia duas cópias da regra.
- Testes: `RoleHierarchyTest` (11).
- RUNTIME STATUS: APLICADO (ver aculta_user 0.4.0-dev.23).

## 0.4.0-dev.96 — herança de papéis no aculta_user; contrib removido — 2026-10-17

Classificação: PATCH (troca de dependência de herança e ajuste de barreira).

- Herança de papéis agora é do aculta_user (ver CHANGELOG do módulo). `RoleHierarchyForm` deixou de gravar `role_inheritance.settings`.
- `RoleHierarchy::herdados` (função pura) e testes.
- Barreira `validate-role-hierarchy`: linha de base de `hasRole` em 15, com justificativa (chamadas da própria herança).
- RUNTIME STATUS: APLICADO (ver aculta_user 0.4.0-dev.22).

## 0.4.0-dev.95 — tela de papéis do ACULTA como única tela de herança — 2026-10-17

Classificação: PATCH (ocultação de tela duplicada do contrib e coluna de herança efetiva).

- A tela de herança do contrib `role_inheritance` (`/painel-administrativo/pessoas/roles/inheritance`) responde 403 e sai da aba da lista de papéis. A herança é editada só em Papéis e perfis (`/painel-administrativo/configuracoes/aculta/papeis`).
- Tela do ACULTA recebe a coluna "Herda de (efetivo)", com a herança transitiva que a tela do contrib mostrava.
- Documentado em `docs/portal/PAPEIS-PARTICIPACAO.md`, seção 8.
- RUNTIME STATUS: APLICADO e verificado por HTTP: contrib 403, aba oculta, tela do ACULTA 200 com a coluna.

## 0.4.0-dev.94 — papéis mesclados e nomes alinhados — 2026-10-17

Classificação: MINOR (remoção de dois papéis, com mesclagem de permissões e de contas).

- Colaborador da Wiki420 (`wiki_contributor`) mesclado em E-mail confirmado (`email_confirmed`); Administrador de cursos (`lms_admin`) mesclado em Educador (`lms_teacher`). Permissões somadas aos papéis que ficam; contas com o papel antigo passam a ter só o que ficou. Ações de papel e arquivos de configuração dos papéis antigos removidos.
- Hook que concedia o colaborador da wiki a quem tinha e-mail confirmado removido (não tem mais função).
- Nomes alinhados em tipos de perfil e papéis: Usuário autenticado (antes "Autenticado" no tipo de perfil) e Educador (antes "Professor").
- Opções de papel dos avisos: removidos os papéis antigos; rótulos iguais aos nomes dos papéis.
- Gates: papéis de usuário sem exceção editorial; linha de base de checagens por nome de papel reduzida para 13.
- RUNTIME STATUS: APLICADO e verificado por HTTP: tela de papéis mostra a cadeia com os nomes novos; páginas de perfil e de papéis respondem 200; contas da wiki mantêm o e-mail confirmado.

## 0.4.0-dev.93 — colaborador da wiki e administrador de cursos entram em categorias — 2026-10-17

Classificação: PATCH (ajuste de classificação de papéis na cadeia).

- Colaborador da Wiki420 (`wiki_contributor`) entra na categoria E-mail confirmado; Administrador de cursos (`lms_admin`) entra na categoria Educador (`lms_teacher`). Cada um herda da própria categoria, e por ela recebe tudo o que a categoria herda.
- Na tela, esses papéis aparecem só com o nome, dentro da linha da categoria.
- RUNTIME STATUS: APLICADO e verificado por HTTP; testes unitários de papéis (8) passam; gate de papéis passa.

## 0.4.0-dev.92 — cadeia de papéis por setas e arraste — 2026-10-17

Classificação: MINOR (nova interação da tela de papéis; configuração própria da cadeia).

- Tela Papéis e perfis: a hierarquia é uma lista ordenada pela classificação de cada categoria. Cada papel mostra só o nome. Mover por setas (botões; funcionam sem JavaScript) ou arrastando, com mouse e toque (`js/role-hierarchy.js`, biblioteca `aculta_portal/role-hierarchy`).
- Papéis sem categoria (`wiki_contributor`, `lms_admin`) ficam fora da cadeia, sem controle na tela, e herdam do autenticado.
- Mapa de herança derivado da cadeia ao salvar (`RoleHierarchy::derivarMapa`); configuração própria `aculta_portal.role_hierarchy` (cadeia e laterais).
- Configuração `aculta_portal.profile_roles` removida (cada tipo de perfil corresponde a um papel).
- Testes: 189 unitários do Portal passam (inclui o mapa derivado e a troca de posição).
- RUNTIME STATUS: APLICADO e verificado por HTTP: lista na ordem da configuração; seta reordena sem salvar; salvar mantém o mapa de herança idêntico. Arraste por ponteiro não foi verificado no navegador.

## 0.4.0-dev.92 — Media Embed de PDF compartilhado com a Wiki — 2026-10-17

Classificação: PATCH (habilita o processamento do embed de documento no formato Wiki existente).

- `filter.format.wiki` permite o elemento `drupal-media` e processa Media Embed após a sanitização HTML, somente para Media `document`. A Wiki resolve o display Media compartilhado e mantém autorização Core.
- Kernel valida o pipeline de renderização. O botão CKEditor fica pendente: a lista histórica de HTML da Wiki falha na validação CKEditor 5, então a habilitação exigiria compatibilização separada.
- Runtime de teste `https://aculta.toca.net.br`: formato `wiki` atualizado por entidade de configuração e cache reconstruído; configuração ativa corresponde ao YAML. Não existe embed PDF em conteúdo Wiki para smoke visual. Config Sync global segue bloqueado por erro YAML preexistente em outro arquivo.
- RUNTIME STATUS: APLICADO; smoke de conteúdo: DEFERRED.

## 0.4.0-dev.91 — tema administrativo em todas as rotas do painel — 2026-10-17

Classificação: PATCH (correção de tema em páginas administrativas).

- Páginas do painel (ex.: `/painel-administrativo/configuracoes/aculta/papeis`) carregavam o tema global (ACULTA420) porque as rotas não definiam `_admin_route`. Corrigido em dois níveis: a opção foi declarada nas 26 rotas customizadas (aculta_portal, aculta_user, aculta_image), e `AdministrativeRouteSubscriber` marca qualquer rota do prefixo `/painel-administrativo` (Core e contrib incluídos) no fim da alteração de rotas.
- Decisão explícita de módulo prevalece: rotas com `_admin_route: FALSE` (biblioteca de componentes do ui_patterns) não são alteradas.
- Gate `validate-aculta-portal-drupal11`: contagem de subscribers pinada de 12 para 13, com a justificativa.
- RUNTIME STATUS: APLICADO e verificado por HTTP como administrador: 8 páginas do painel (papéis, campanhas, perfis, apoio, vendas, herança, tipos de perfil, tipos de conteúdo) carregam o Claro e nenhuma carrega o ACULTA420. Página pública continua com o tema do site.

## 0.4.0-dev.91 — viewer PDF também em campos File institucionais — 2026-10-17

Classificação: MINOR (novo formatter compartilhado, sem storage de documentos paralelo).

- `aculta_pdf_file` renderiza PDFs públicos permanentes de campos File diretos usando o builder do `aculta_pdf_viewer`; acesso da entidade, campo e download é acumulado no render. Arquivos privados e formatos não PDF mantêm o link Core autorizado.
- Os displays `node.document.default` e `node.document.full` passam a usar o formatter em `field_document`. Unit 11/11 (145 asserções) e Kernel 4/4 (87 asserções) PASS no MariaDB dedicado; quatro deprecações Core/contrib.
- Gates Drupal 11+ (888 checks), image, public-slugs e module/theme-boundary PASS. No Runtime de teste o módulo e os displays já estavam ativos; nenhum import foi executado. Smoke visual/HTTP do campo permanece DEFERRED.
- RUNTIME STATUS: DEFERRED para homologação visual/HTTP; código e configuração estão no checkout e a configuração equivalente já está ativa no Runtime.

## 0.4.0-dev.90 — apelido com maiúsculas; slug não canônico responde 404 — 2026-10-17

Classificação: PATCH (correção de regra de apelido e de um 500 no perfil público).

- Validação do apelido (`AccountNames::isValid`) vale para o slug normalizado (`PublicRouteSlug::normalize`); o apelido guardado preserva maiúsculas.
- `/u/<apelido com maiúsculas>` responde 404, sem redirecionamento (AGENTS.md: não há redirecionamento de slug). Antes, respondia 500: o roteador casa sem diferenciar maiúsculas, o `metatag` e o normalizador do `redirect` geram a URL com o apelido bruto. `PublicProfileNotCanonicalSubscriber` (prioridade 31) retira a rota e responde 404 antes da renderização.
- Gate `validate-profile-links`: exceção única e justificada para esse subscriber (não gera link de perfil).
- Conta só autenticada (sem e-mail confirmado): sem dados básicos na tela de dados; decisão do responsável.
- RUNTIME STATUS: APLICADO e verificado por HTTP (`/u/PiradoPirata` 404; `/u/piradopirata` 200; perfil de teste-b 200; apelido inexistente 404). Testes unitários do aculta_user passam (37).

## 0.4.0-dev.88 — participante removido; chamadores por hierarquia — 2026-10-17

Classificação: MINOR (remoção do tipo participante e troca de todos os seus chamadores).

- Todos os chamadores de `participante` passam a usar `PerfilDoPapel` (principal por hierarquia; criação pelo tipo do papel). Consultas por tipo usam `PerfilDoPapel::TIPOS`. Documentos de identidade (`aculta_id_cpf`, `aculta_associado_id`) usam o tipo `associado`.
- Criação de conta cria o perfil do papel da própria conta.
- Tipo `participante`, seus campos, displays e perfis removidos do Runtime; arquivos de configuração removidos do repositório (`config/sync` e `config/install` dos módulos).
- Permissões de perfil por função: `authenticated` perde as de `participante` e `customer`; cada papel recebe criar, editar e ver o próprio perfil.
- Displays de dados básicos (nome, apelido, WhatsApp, cidade, UF) em `associado`, `email_confirmed`, `lms_teacher`, `gerente_loja` e `administrator`. `authenticated` mantém só o endereço.
- Testes: unitários do Portal (187), `aculta_user` (37), `aculta_id_cpf` e `aculta_associado_id` (47 cada) passam.
- Pendente: `.install` com hooks históricos que citam `participante` (revisar fresh install); textos de comentário; contas sem e-mail confirmado (autenticado) não têm dados básicos na tela de dados; testes Kernel e de navegador não executados.
- RUNTIME STATUS: APLICADO e verificado por HTTP: páginas de conta (dados, endereço, pedidos) para as contas de teste; perfil público; resumo de doação logado; telas administrativas de pessoas, perfis, papéis e hierarquia.

## 0.4.0-dev.87 — um tipo de perfil por papel: endereço no autenticado, tipo customer removido — 2026-10-17

Classificação: MINOR (remoção do tipo customer, novo endereço no perfil autenticado, novos tipos por função).

- Tipo `customer` (Cliente) removido. Os 3 perfis foram copiados para `authenticated` (campo `field_address`), verificados (0 divergências) e os pedidos reapontados. Checkout, "Meus dados > Endereço", callback de CEP e o hook de endereço passam a usar `authenticated` e `field_address`.
- Tipos de perfil por função: `associado` (documentos de identidade), `email_confirmed`, `authenticated` (endereço), `lms_teacher` (Professor), `gerente_loja` (Gerente de Loja) e `administrator` (Administrador). Cada um com os 20 campos e displays de formulário e de visualização.
- `PerfilDoPapel`: prioridade administrator > gerente_loja > lms_teacher > associado > email_confirmed > authenticated.
- Configuração exportada em `config/sync` (tipos, campos e displays); arquivos de `customer` removidos.
- Pendente: os chamadores de `participante` (cerca de 25 arquivos) ainda usam esse tipo; o tipo `participante` não foi removido. Dados de `participante` ainda não foram redistribuídos para as funções.
- RUNTIME STATUS: APLICADO e verificado por HTTP: "Meus dados > Endereço" (logado como a conta de teste) mostra o endereço salvo. Verificação do checkout completo e do ViaCEP no navegador feita pelo responsável.

## 0.4.0-dev.86 — um tipo de perfil por papel (helper) — 2026-10-17

Classificação: MINOR (novo helper de perfil por papel; migração em andamento).

- `PerfilDoPapel`: tipo do perfil pelo papel mais alto (associado > email_confirmed > authenticated), com `principal()` e `obter()` que recebem o EntityTypeManager injetado. Sem efeito em código existente ainda; os chamadores migram na sequência.
- Runtime: tipos `associado`, `email_confirmed` e `authenticated` criados com os 20 campos do `participante`; 6 perfis copiados por papel mais alto e verificados campo a campo (0 divergências). Os perfis `participante` originais continuam no banco até a troca do código.
- Pendente: troca dos chamadores de `participante`, remoção do tipo `participante`, e remoção do tipo `customer` (exige decisão sobre o endereço de cobrança e a conta; ver PAPEIS-PARTICIPACAO.md).
- RUNTIME STATUS: APLICADO (tipos e cópias, aditivo). Nada apagado.

## 0.4.0-dev.85 — Papéis e perfis em uma tela — 2026-10-17

Classificação: MINOR (nova tela unificada e vínculo entre tipos de perfil e papéis).

- A tela de hierarquia passa a se chamar **Papéis e perfis** (`/painel-administrativo/configuracoes/aculta/papeis`) e reúne: a hierarquia de papéis, o vínculo de cada tipo de perfil aos papéis (`aculta_portal.profile_roles`) e atalhos para as telas do núcleo (papéis, permissões, tipos de perfil e herança).
- Vínculo entre perfis e papéis: registro para a administração. Não move nem altera dados de perfis existentes; o tipo `customer` (checkout) fica fora do vínculo de uso.
- Correção: `anonymous` volta a aparecer como papel pai de `administrator` na tela; antes, salvar a tela o removia silenciosamente.
- A tela do núcleo de tipos de perfil continua acessível (não foi redirecionada); redirecionamento ainda não decidido.
- RUNTIME STATUS: APLICADO e verificado por HTTP (tela como administrador, salvamento de vínculo de teste com estado restaurado). Não verificado no navegador.

## 0.4.0-dev.84 — hierarquia de papéis corrigida e editável no painel — 2026-10-17

Classificação: MINOR (nova tela administrativa e mudança da cadeia de herança, por decisão do responsável).

- Cadeia de herança (`role_inheritance.settings`): `lms_teacher` < `content_editor` < `gerente_loja` < `administrator`. `content_editor` herda de `lms_teacher` (antes, de `email_confirmed`); `gerente_loja` herda de `content_editor` (antes, de `authenticated`). Herança transitiva: `gerente_loja` passa a ter as permissões de `content_editor`, `lms_teacher`, `associado`, `estudante`, `email_confirmed` e `authenticated`.
- Painel: ACULTA > Configurações > Hierarquia de papéis (`/painel-administrativo/configuracoes/aculta/papeis`), permissão `administer aculta role hierarchy` (restrita). Mostra a cadeia efetiva de cada papel e recusa ciclo ou autorreferência antes de salvar.
- `RoleHierarchy` (cálculo puro): cadeia efetiva e detecção de ciclo. Testes unitários: 6 testes passam.
- Gate `scripts/validate-role-hierarchy.php`: cadeia decidida, sem ciclo, administrator herda de todos, checagens por nome de papel não sobem acima da linha de base (15 `hasRole`, 4 `in_array`).
- Comentário de `RestrictedForumGrants` alinhado à cadeia vigente.
- Instruções para agentes (`AGENTS.md`, seção Papéis e hierarquia) e barreiras (`docs/portal/GUARDRAILS.md`).
- RUNTIME STATUS: APLICADO no Runtime `aculta.toca.net.br`. Verificado: cadeia efetiva de `gerente_loja`; tela do painel como administrador (200, 10 papéis, cadeias calculadas); reenvio do mapa atual mantém a configuração; envio com ciclo é recusado e não altera a configuração. Não verificado no navegador.

## 0.4.0-dev.83 — gerente da loja gerencia campanhas de doação — 2026-10-17

Classificação: PATCH (permissão concedida a um papel existente, por decisão do responsável).

- Papel `gerente_loja` recebe `administer aculta donation campaigns` diretamente (sem herança). Dependência de módulo `aculta_donation` declarada no papel.
- RUNTIME STATUS: APLICADO no Runtime `aculta.toca.net.br`; verificado com a conta de teste: campanhas 200, Formas de apoio 200.

## 0.4.0-dev.82 — gerente da loja acessa Formas de apoio — 2026-10-17

Classificação: PATCH (permissão concedida a um papel existente).

- Papel `gerente_loja` recebe `administer aculta support settings` (Formas de apoio). A permissão de campanhas de doação (`administer aculta donation campaigns`) não foi concedida.
- RUNTIME STATUS: APLICADO no Runtime `aculta.toca.net.br`; verificado com a conta de teste (papel gerente_loja): página de Formas de apoio responde 200 com o campo de valores; campanhas responde 403.

## 0.4.0-dev.81 — valores padrão da doação editáveis no painel — 2026-10-17

Classificação: MINOR (nova configuração editorial e mudança do valor padrão de apoio).

- Painel: ACULTA > Configurações > Formas de apoio (`/painel-administrativo/configuracoes/aculta/apoio`) passa a editar os valores padrão (lista separada por vírgula, até 8 valores, inteiros maiores que zero) e a permissão de "Outro valor" quando não há campanha vigente.
- Valor inicial de instalação: R$ 5,00 (lista padrão 5, 10, 20, 50, 100).
- Página de apoio (`SupportForm`): sem campanha vigente, lê `aculta_portal.support` em vez da constante fixa. A campanha do submódulo aculta_donation continua tendo prioridade.
- Schema `aculta_portal.support` criado; `config/sync/aculta_portal.support.yml` com os valores padrão.
- Testes: `SettingsFormValoresTest` (2 testes, 8 asserções) passam.
- Gates: Drupal 11, mapa módulo x tema, fronteira e endereços de ambiente PASS.
- RUNTIME STATUS: APLICADO por configuração (`aculta_portal.support` gravado no Runtime `aculta.toca.net.br`); página de apoio verificada por HTTP (cards 5, 10, 20, 50, 100; "Outro valor" presente). Não verificado: o formulário no painel (exige login de administrador) e a gravação pelo formulário.

## 0.4.0-dev.80 — etapa de resumo na doação rápida e campanhas no Runtime — 2026-10-17

Classificação: MINOR (nova etapa de resumo antes do pagamento; cards de valor no tema).

- Doação rápida (`/apoiar/{valor}/agora`) passa pela etapa `review` (resumo: doação, subtotal, total, "Voltar" e "Editar") antes do pagamento. Implementação: `DonationSummaryController`, rota ajustada em `DomainRouteSubscriber`, pane `review` ativo em `donation_flow`.
- Correção: o atalho do módulo contrib gravava o valor no campo, mas não o preço unitário (definido só ao enviar o passo de doação, que o atalho pula). O pedido saía com total zero. O controlador agora copia o valor para o preço unitário.
- Correção: `config/sync/commerce_checkout.commerce_checkout_flow.donation_flow.yml` tinha a chave `review` duplicada (YAML inválido). Mantida uma entrada, `step: review`.
- Submódulo `aculta_donation` (0.1.0-dev.2): atributo `ConfigEntityType` do namespace correto (`Drupal\Core\Entity\Attribute`), `config_prefix: campanha`, `route_provider` html; schema com a chave `aculta_donation.campanha.*`.
- Testes no Runtime (`aculta.toca.net.br`, `aculta_runtime`, URI confirmado): campanha de teste vigente (valores 15/30/75, sem valor personalizado) exibida no host de apoio; campanha removida ao final. Anônimo e logado: atalho leva ao resumo com o valor correto; pedido em `review` com total igual ao valor.
- RUNTIME STATUS: APLICADO e verificado por HTTP (anônimo e logado). Não verificado no navegador (WebDriver): etapa de resumo com captcha Turnstile e envio para o pagamento.
- Pendente: pedido recorrente (F3), painel administrativo de doações, testes Kernel do submódulo, verificação no navegador.

## 0.4.0-dev.79 — campanhas de doação (submódulo aculta_donation) — 2026-10-17

Classificação: MINOR (novo submódulo e nova fonte de valores na página de apoio).

- Novo submódulo `aculta_donation` (0.1.0-dev.1): entidade de configuração `aculta_donation_campaign` com nome, início e término (inclusivos), valores sugeridos, valor personalizado e status; formulário com validação de datas e valores; permissão `administer aculta donation campaigns`.
- `DonationCampaignSelector::vigente()`: campanha ativa cuja janela contém a data de hoje; em sobreposição, vence a de início mais recente.
- Página de apoio (`SupportForm`): valores e opção de valor personalizado vêm do contrato neutro `SupportAmountsProviderInterface`. Sem campanha vigente ou sem o submódulo habilitado, valem os valores padrão (10, 20, 50, 100).
- Testes unitários: `DonationCampaignSelectorTest` (4 testes, 28 asserções) passam.
- Gates: `validate-aculta-portal-drupal11` PASS; `validate-module-theme-map` PASS; `validate-module-theme-boundary` PASS; `validate-environment-addresses` PASS.
- RUNTIME STATUS: DEFERRED. O submódulo ainda não está habilitado no `aculta_runtime`. Em 2026-10-17 o Drush do Homelab reportou URI `http://default`, que não confirma o servidor de testes `*.aculta.toca.net.br`; por isso nada foi habilitado nem importado.
- Pendente nesta entrega: pedido de doação recorrente (F3), fluxo de checkout com resumo e testes de navegador.

## 0.4.0-dev.78 — achados de endereço de produção corrigidos — 2026-10-17

Classificação: PATCH.

- Sobreposições de metatag por domínio (repositório e Runtime): `canonical_url` e `og_url` usam `[site:url]`, não o endereço de produção.
- `metatag.metatag_defaults.front` (padrão): `og_url` usa `[site:url]`.
- Canonical e `og:url` das raízes de finalidade (`FRONT_PATHS`) apontam para a raiz do host, também quando a página não é um nó (`EditorialHooks`).
- JSON-LD de organização e site: no ambiente de testes, sobreposição em `settings.local.php` com o endereço do deployer; o token `[institution:url]` usa o endereço do ambiente (`DeployerEnvironment::siteUrl`).
- Gate `validate-domain-front-paths`: falha se uma sobreposição de metatag por domínio voltar a usar endereço de produção.
- Não alterado: texto editorial (corpo de nós) com menção a aculta.org, e dados do bloco de instituição (sobrepostos pelo token).

## 0.4.0-dev.77 — canonical da vitrine aponta para a raiz — 2026-10-17

Classificação: PATCH.

- `EditorialHooks::metatagsAlter`: quando o nó é o caminho interno da raiz do host (`FRONT_PATHS`), o canonical e o `og:url` são a raiz do host. Vitrine da loja: `https://loja.aculta.toca.net.br/`.
- Achado não corrigido nesta versão: a raiz da wiki e as demais raízes trazem `https://aculta.org/` (produção) no JSON-LD de organização e site, e as sobreposições de metatag por domínio no repositório (`config/sync/domain/*/metatag.metatag_defaults.front.yml`) têm endereços de produção fixos. Pendente: tratar como endereço de ambiente (mapeamento do `aculta_deployer`).

## 0.4.0-dev.76 — identificador da revista: revista420; vitrine da loja — 2026-10-17

Classificação: PATCH (renomeação de identificador e conteúdo da vitrine).

- Identificador interno do domínio da revista: `coletivo420_aculta_org` → `revista420_aculta_org` (código, configuração, deploy e referências de campo). Nome de exibição: "Revista 420".
- Migração do Runtime de testes executada com autorização do responsável: 17 referências de nós, aliases, ação de origem e sobreposições de configuração por domínio (`domain.revista420_aculta_org`).
- Vitrine da loja criada (página `Loja da Associação`, alias `/loja-da-aculta`, origem `loja_aculta_org`). Raiz da loja responde 200; caminho interno responde 404.
- Pendência: o `canonical` da vitrine aponta para o caminho interno `/loja-da-aculta`; deve apontar para a raiz do host.
- Ocorrência restante de "O Observatório da Maconha" em texto editorial de notícia (`scripts/content/editorial/noticia-anvisa-canhamo-autocultivo.json`), mantida por ser texto de matéria.

## 0.4.0-dev.75 — raiz dos subdomínios com comportamento uniforme — 2026-10-17

Classificação: PATCH (roteamento; sem API nova).

- Política única `Domain/FrontPathPolicy`: caminho interno da raiz de qualquer subdomínio responde 404 em acesso direto, sem 301 (antes `/apoio` tinha 301 e `/cursos` e `/wiki` também). Aplicada por `DomainPurposeRequestSubscriber`.
- Testes unitários da política (172 testes no módulo, OK).
- Gate `scripts/validate-domain-front-paths.php` (estático; `--live` consulta os hosts): falha se o assinante deixar de usar a política ou se surgir um 301 novo sem revisão.
- Documentado em `docs/portal/DOMAIN-FRONT-PATHS.md`, com a matriz por host e a lista de 301 legados permitidos.
- Gate `validate-aculta-portal-drupal11`: o contrato P5.1 do `SupportForm` foi atualizado do link do módulo de doação (`blockManager`) para os valores sugeridos no domínio principal (0.4.0-dev.73). O contrato continua exigindo injeção de dependências.
- Pendências fora do código: vitrine da loja (nó 69 / alias `/loja-da-aculta`) ausente no Runtime de testes, então a raiz da loja responde 404; host `coletivo420` não resolve no servidor de testes.

## 0.4.0-dev.74 — apoio somente no subdomínio — 2026-10-17

Classificação: PATCH (roteamento de domínio).

- O formulário de apoio é servido só na raiz do subdomínio de apoio. `/apoio` responde 404 em qualquer host (antes havia redirecionamento 301 para a raiz).
- Escolha de valor no subdomínio de apoio; finalização (pedido e checkout) no domínio principal, como na loja.
- Verificação: `apoio.../apoio` → 404; `apoio.../` → 200 com os valores sugeridos; `aculta.../apoio` → 404.

## 0.4.0-dev.73 — formulário de apoio com valores sugeridos e fluxo de doação liberado — 2026-10-17

Classificação: MINOR (novo bloco de valores sugeridos no formulário de apoio).

- `SupportForm` mostra valores sugeridos (R$ 10, 20, 50 e 100) que levam a `/apoiar/{valor}/agora` no domínio principal, e a opção "Outro valor" (`/apoiar`). O fluxo de pedido e checkout continua no módulo `commerce_donation_flow`.
- Permissão `make donation` concedida aos papéis `anonymous` e `authenticated` (antes só `administrator`, o que deixava a doação pública inacessível). Declarada em `config/sync`.
- Tema: token `support-amounts` e estilo em `portal-support.css`; documentado em `TOKENS-E-ATRIBUTOS.md`.
- Verificação no Runtime: `/apoio` (domínio de apoio) lista os valores; `/apoiar/{valor}/agora` cria o pedido de doação e redireciona ao passo de pagamento.
- Pendente: teste completo no navegador (login, pagamento com saldo, conclusão e `/meu-apoio`). Assinatura mensal depende da decisão F3.

## 0.4.0-dev.72 — redirecionamento de e-mails da conta de teste — 2026-10-17

Classificação: PATCH (comportamento de teste; sem API pública nova).

- `src/Mail/TestMailRedirect.php` e hook `mail_alter` em `MailHooks`: mensagens endereçadas às contas de teste listadas em `settings.local.php` vão para o endereço interno de teste. Demais destinatários não são alterados.
- Sem a configuração local, a classe não altera nenhuma mensagem (produção não é afetada).
- Testes unitários: 158 testes, 409 asserções, OK.
- Verificação no Runtime: a mensagem para a conta de teste foi redirecionada. O servidor de envio respondeu com cota mensal esgotada (552), o que é um limite externo e não um erro deste código.

## 0.4.0-dev.71 — autocompletar de CEP não apaga campos digitados — 2026-10-17

Classificação: PATCH (correção de comportamento; sem API nova).

- `js/cep-address.js`: a busca do CEP preenche só os campos vazios. Antes, cidade, estado, rua e bairro digitados pela pessoa eram sobrescritos, e a troca de estado reconstruía o formulário e apagava o restante.
- Trade-off: se a pessoa já digitou um endereço antigo e troca o CEP, a cidade e a rua antigas permanecem e precisam ser corrigidas à mão.
- Verificação: `node --check` no arquivo; cache rebuild no Runtime. Não foi refeito o fluxo completo de checkout no navegador nesta versão (pendente).

## 0.4.0-dev.70 — apresentação dos painéis de vendas e doações (tokens sales-*) — 2026-10-16

Classificação: MINOR da linha aberta. Autorização do responsável para mudanças visuais no tema e nos módulos relacionados.

- O módulo marca os painéis com tokens `data-portal-ui` (`sales-panel`, `sales-section`, `sales-heading`, `sales-table`, `sales-pagination`, `sales-payment`, `sales-action`, `sales-action-danger` e os estados `sales-state-*`). Nenhuma classe de design no módulo.
- `SalesOrdersTableBuilder` deixa de ser `final` (duplo de teste) e passa a usar esses tokens. As controllers de vendas e doações envolvem as seções em containers com título semântico (`h2`).
- Aparência no ACULTA420 (0.6.0-dev.67): `PortalUiHooks::TOKENS` e `css/components/sales-panel.css`.
- Testes unitários: 154 testes, 399 asserções, OK. Gates do Portal, de fronteira, de mapa e de docs de tokens: PASS.
- Runtime: cache rebuild feito. Captura visual com administrador e pedidos de teste: DEFERRED.

## 0.4.0-dev.69 — painéis de vendas e doações com ações de pagamento — 2026-10-16

Classificação MINOR. Fase F1 (painéis), nas partes que não dependem da assinatura de teste nem de decisão nova.

- Painel de vendas (`/painel-administrativo/loja/vendas`) e de doações (`/painel-administrativo/loja/doacoes`): tabela de pedidos paginada, com número, data, total e pagamentos. Cada pagamento mostra estado local e status do Mercado Pago.
- Ações por pagamento: **atualizar** (consulta a API e espelha o status), **cancelar** (só com estado `authorization`, com confirmação) e **estornar** (operação `refund-payment` do Commerce).
- Regra de acesso (`SalesPaymentAccess`): o pedido com produto exige `gerenciar pagamentos de vendas`; com doação, `administrar doacoes`; pedido misto exige as duas. Os links só aparecem com permissão, e os formulários checam de novo e devolvem 403.
- Formulários `SalesPaymentRefreshForm` e `SalesPaymentCancelForm` com injeção de dependências (sem `\Drupal::`).
- `SalesReportController` e `DonationReportController` passam a devolver `resumo` (doações) e `pedidos`; testes unitários ajustados.
- Testes unitários: 154 testes, 399 asserções, OK. Gates de Portal, tema, endereços de ambiente e slugs: PASS.
- Runtime: cache rebuild feito; rotas `atualizar` e `cancelar` registradas; construtor da tabela executado sem erro.
- Pendências: teste visual com administrador logado e pedidos de teste (DEFERRED); teste Kernel do painel e dos formulários (DEFERRED); painel visual no tema ACULTA420 (não autorizado: a apresentação usa tabelas padrão); painel de produto dedicado (a tabela por variação já está no relatório de vendas).

## 0.4.0-dev.68 — webhooks de assinatura do Mercado Pago — 2026-10-16

Classificação MINOR. Autorização do responsável para o segredo do webhook: a Key `mercadopago_webhook_secret` (provedor env, variável `MERCADOPAGO_WEBHOOK_SECRET`) já existia e está no contrato de segredos. Nenhuma credencial nova foi criada.

- Verificação de assinatura: o guard usa o `WebhookSignatureValidator` do SDK instalado (HMAC-SHA256, manifesto `id;request-id;ts`), em vez de uma cópia própria.
- Tópicos de assinatura (`subscription_preapproval`, `subscription_preapproval_plan`, `subscription_authorized_payment`) deixam de ser descartados: são consultados e registrados em log. A cobrança recorrente usa `GET /authorized_payments/{id}`, porque o SDK 3.16.0 não tem cliente para esse recurso.
- Assinatura de id não numérico aceita só para tópicos de assinatura; pagamentos seguem com o id inteiro.
- Falha ao consultar devolve 500, para o Mercado Pago reenviar.
- Nada é gravado no Commerce nesta versão: o vínculo assinatura-pedido de doação é a fase F3.
- Testes unitários: 146 testes, 387 asserções, OK.
- Runtime: POST sem assinatura e com assinatura inválida devolvem 401. POST com assinatura válida e id inexistente devolve 500 e registra a falha. O caminho de sucesso (200) fica DEFERRED até existir uma assinatura real de teste.
- Cache rebuild do Runtime feito. Antes dele, o container não tinha o serviço novo e os tópicos de assinatura eram só reconhecidos.

## 0.4.0-dev.67 — pendências da linha 0.4.0 resolvidas — 2026-10-16

Classificação MINOR. Fecha as pendências de F0 que não dependem de decisão nova: ligação do relatório à consulta, testes Kernel e verificação no Runtime.

- Relatório de vendas (`SalesReportController`) usa `SalesReportQuery`, com filtros de data, loja e granularidade. Some a carga de todos os pedidos na memória, que era a dívida registrada nas versões anteriores.
- `SalesReportQuery` deixa de ser `final` e corrige a paginação: a contagem de pedidos lia a coluna de id, não o total.
- `MercadoPagoClientFactory` deixa de ser `final`, para permitir duplo de teste.
- Testes Kernel `SalesReportQueryKernelTest` (6 casos) no banco `aculta_simpletest`: escopo de produto e de doação, estado, intervalo, loja e paginação. Passam.
- Testes unitários dos serviços do Mercado Pago com cliente do SDK substituído: atualização de status, estorno total, cancelamento de pendente, recusa de cancelamento de aprovado, pagamento sem id remoto, recorrência com payload e status. Unit `aculta_portal`: 139 testes, 369 asserções, OK.
- Runtime: permissões `gerenciar pagamentos de vendas` e `administrar doacoes` concedidas ao papel `gerente_loja` (escopo do papel; import completo não feito, porque o diff de configuração do Runtime é grande). Cache rebuild concluído. Rotas `/painel-administrativo/loja/vendas` e `/painel-administrativo/loja/doacoes` registradas.
- Pesquisa de webhooks: tópicos `subscription_preapproval`, `subscription_preapproval_plan` e `subscription_authorized_payment` confirmados na documentação. A verificação de assinatura (`x-signature`) exige o segredo do aplicativo, que seria uma credencial nova: não adicionada sem autorização.
- Pendente: painéis e ações de pagamento (F1, a pedido do responsável); webhooks e vínculo de assinatura ao pedido de doação (F2 e F3, bloqueados pela decisão do segredo e pelo desenho do fluxo de doação).

## 0.4.0-dev.66 — gerente de loja administra doações — 2026-10-16

Classificação MINOR. Mudança de escopo do gerente de loja: além de vendas, administra doações.

- Permissão `administrar doacoes` (concedida ao papel `gerente_loja` em `config/sync`) e rota `/painel-administrativo/loja/doacoes`, com o relatório de doações por período e moeda (`DonationReportController`).
- `SalesReportQuery` passa a receber o escopo (`ESCOPO_PRODUTO` ou `ESCOPO_DOACAO`), com a mesma regra de período, loja e moeda. Vendas e doações nunca se somam.
- Testes: `DonationReportControllerTest` (escopo de doação, formatação, tabela vazia). Unit `aculta_portal`: 132 testes, 362 asserções, OK.
- Pendente: ações de pagamento e de recorrência para doações (estornar, cancelar assinatura) ligadas à permissão `administrar doacoes`; a tela entra na fase de painéis. Vincular a assinatura ao pedido de doação é F3 do plano.
- Runtime: DEFERRED (importação de config do papel e teste visual do relatório).

## 0.4.0-dev.65 — API do Mercado Pago: recorrência, ações de pagamento e consultas de vendas — 2026-10-16

Classificação MINOR. Decisão do responsável: implementar as APIs do Mercado Pago disponíveis no SDK e planejar a integração com a loja e as doações. Painéis visuais ficam para a fase seguinte.

- Fábrica `MercadoPagoClientFactory`: os 20 clientes concretos do SDK 3.16.0, com credenciais do gateway no modo ativo. Teste confirma que as classes existem.
- Recorrência (`MercadoPagoRecurrenceService` e `RecurrenceRequestBuilder`): planos (`preapproval_plan`) e assinaturas (`preapproval`) com validação de valor, moeda, frequência, `back_url` https e e-mail. Pausar, reativar e cancelar assinatura.
- Pagamentos (`MercadoPagoPaymentService` e `MercadoPagoPaymentPolicy`): atualizar status pela API, espelhando estorno e estados locais; cancelar pendente (`in_process`, `pending` e `authorized`, conforme a documentação) com transição `void`. Estorno continua pela operação `refund-payment` do Commerce.
- Relatório de vendas (`SalesReportQuery` e `SalesReportFilters`): consultas sobre as tabelas do Commerce, sem tabela paralela, com filtros de data, loja e granularidade. Ainda não ligadas ao controller; a ligação entra com os painéis.
- Serviços registrados em `aculta_portal.services.yml`.
- Decisões do responsável (2026-10-16): cancelar e estornar só para o gerente de loja (permissão `gerenciar pagamentos de vendas`, concedida ao papel `gerente_loja` em `config/sync`); doação recorrente pela API de assinaturas do Mercado Pago; assinatura de produto da loja fica para depois; relatório de vendas exclui itens de doação; migração dos tokens para Drupal Key **não** autorizada (segue com os tokens de teste já configurados).
- Verificação no Runtime: gateway `mercado_pago` (modo `test`) autentica na API com o token de teste (leitura de conta). Consultas do relatório executam no MariaDB sem erro; o banco de teste não tem pedidos concluídos, então os valores não foram conferidos.
- Plano de integração com a loja e as doações, perguntas em aberto e fases: `docs/portal/MERCADO-PAGO-PLANO.md`.
- Testes unitários: 130 testes, 351 asserções, OK. Gates `validate-aculta-portal-drupal11`, `validate-module-theme-boundary`, `validate-module-theme-map` e `validate-environment-addresses`: PASS.
- Limites: sem teste Kernel (DEFERRED: banco `aculta_simpletest`). Sem chamada real ao Mercado Pago (DEFERRED: gateway não provisionado no Runtime). Tópicos de webhook de assinatura e `authorized_payments` não confirmados na documentação.
- Risco aberto: tokens do gateway estão em configuração do módulo contrib; migrar para Drupal Key antes de produção.

## 0.4.0-dev.64 — relatório de vendas da loja e correções da revisão — 2026-10-16

Classificação MINOR. Decisão do responsável: o gerente de loja vê um relatório próprio de vendas.

- `SalesReportController` (`/painel-administrativo/loja/vendas`, permissão `view commerce_order`): variações vendidas em pedidos concluídos, com quantidade e total por moeda. Sem dados de pagamento (pagamentos seguem só com o administrador). Sem cache.
- Revisão de código aplicada: totais somados com `Price::add()` (sem `float`), agrupados por variação e moeda, e formatados pelo `commerce_price.currency_formatter` (sem `number_format` próprio).
- Correção no tema (`page.html.twig`): `show_utility_bar|default(true)` trocava `false` por `true`, e a barra utilitária aparecia no purpose `links`. Agora usa `?? true`. Esta correção toca `web/themes/custom/aculta420/` por pedido explícito do responsável.
- Saída em tabela neutra (`#type => table`); o layout visual fica para o tema ACULTA420.
- Limite conhecido: o relatório carrega todos os pedidos concluídos (`loadMultiple`), sem agregação no banco nem paginação. Aceitável enquanto o volume for pequeno; revisar quando a loja crescer.
- Testes: `SalesReportControllerTest` (`tests/src/Unit/Controller/`) cobre a soma por variação entre pedidos, os totais por moeda, itens incompletos ignorados e o estado vazio. O formatador é um duplo de teste com o formato pt-BR; a formatação real do Commerce não é coberta por este teste.

## 0.4.0-dev.63 — visibilidade da apresentação por purpose decidida pelo Portal — 2026-10-16

Classificação: PATCH.

- `domain_presentation` ganha a chave `layout` com quatro flags booleanas: `institution_bar_account`, `show_utility_bar`, `links_page` e `show_site_header`. O Portal as calcula por purpose; a página inicial do `main` usa `path.matcher` com o cache `url.path.is_front`.
- `DomainPresentationBuilder` recebe `@path.matcher`. `DomainPresentation` recebe o quarto argumento `$layout`.
- Gate `validate-domain-presentation-contract.php` e teste `DomainPresentationContractTest` verificam o formato e os valores das flags. Contrato documentado em `docs/portal/DOMAIN-PRESENTATION-CONTRACT.md`.
- Motivo: o tema decidia visibilidade por `identity.purpose` em `page.html.twig`, o que violava a política de domínio e fazia `validate-aculta420-shell-contract` reprovar.
- Evidência: Unit `aculta_portal` 81/81 (251 asserções) OK; `validate-domain-presentation-contract` PASS (162 checks, via Drush); `validate-aculta-portal-drupal11` PASS; `validate-module-theme-boundary` PASS; `validate-module-theme-map` PASS; `validate-portal-ui-docs` PASS; `validate-public-slugs` PASS; `validate-environment-addresses` PASS.
- RUNTIME STATUS: cache rebuild no Runtime de teste com a URI de teste. Visual em `main`, `wiki`, `cursos`, `apoio` e `conta` verificado por captura de tela (Firefox headless).

## 0.4.0-dev.62 — registro do helper de derivados e documentação de tokens — 2026-10-16

Classificação: PATCH.

- `PortalHelperRegistry` mantém o helper `aculta_image.generated_file` (usado pelo `aculta_pdf_viewer`) com resumo e documentação vigentes.
- `docs/portal/TOKENS-E-ATRIBUTOS.md`: 54 tokens do mapa do tema e 31 atributos `data-*` que estavam fora do gate passam a constar. Os tokens `public-profile-avatar-image`, `achievement-notices` e `aculta-image-audit` foram mapeados no tema (0.6.0-dev.65).
- `docs/portal/MAPA-MODULO-TEMA.md` e o gate `scripts/validate-module-theme-map.php`: cada módulo e contrib habilitado tem registro de contraparte no tema.
- Evidência: `validate-portal-ui-docs` PASS (237 tokens, 189 usados, 82 atributos); `validate-module-theme-map` PASS (130 checks); `validate-module-theme-boundary` PASS; `validate-aculta-portal-drupal11` PASS (803 checks); `validate-public-slugs` PASS; `validate-environment-addresses` PASS.
- RUNTIME STATUS: sem alteração de configuração; cache rebuild no Runtime de teste.

## 0.4.0-dev.59 — PDF Viewer documenta acesso e preview opcional — 2026-10-16

Classificação PATCH. `aculta_pdf_viewer` 0.1.0-dev.8 remove PDFPreview da lista de
dependências obrigatórias de ativação, mantendo miniaturas como melhoria
opcional. README e contrato esclarecem que `private://` obedece Media/File
access: como Config Sync concede `view media` a anônimo e autenticado, isso não
garante audiência restrita; Group↔Media não está integrado e PDF privado não é
aprovado para documento confidencial de turma. Kernel: 9 testes, 131 assertions
PASS, incluindo ativação sem PDFPreview. RUNTIME STATUS: DEFERRED; URI do Drush
está como `http://default`.

## 0.4.0-dev.58 — leitor PDF integrado: busca, zoom e tela cheia — 2026-10-16

Classificação MINOR. `aculta_pdf_viewer` 0.1.0-dev.3 acrescenta zoom, busca textual por página e tela cheia ao leitor ESM local; fallback de arquivo autorizado permanece disponível. Kernel valida o fluxo CKEditor Media Embed → formatter e markup dos controles; Node valida sintaxe. Firefox 153.4 ESR contra artigo temporário no Runtime `aculta_runtime` confirmou PDF.js/worker local, 14 páginas, navegação, zoom 125%, busca com cinco resultados, tela cheia e zero erros JS. A UI CKEditor 5 selecionou Media `document`, inseriu o embed, salvou o artigo e a página pública mostrou o leitor. A fixture Media veio de Drush; upload/criação de Media pelo papel `content_editor` segue DEFERRED. Artigos, Media, Files e binários de teste foram removidos. Acessibilidade visual, CSP, PDFs grandes, preview, acesso privado e Hostinger seguem DEFERRED.

## 0.4.0-dev.57 — Revista insere PDF pelo CKEditor — 2026-10-16

Classificação MINOR. O papel `content_editor` pode criar Media `document` para inserção no CKEditor 5 da Revista. O bundle é `public://` e permanece restrito a PDFs destinados à publicação; não usar para documentos confidenciais. `aculta_pdf_viewer` 0.1.0-dev.2 valida o fluxo Core Media Embed → formatter → PDF.js em Kernel. No Runtime de teste, módulo/display/permissão aplicados pontualmente e cache reconstruído; não havia PDF/embed editorial para teste interativo. Sem importação global de Config Sync. RUNTIME STATUS: parcial; fluxo de autoria DEFERRED.

## 0.4.0-dev.53 — política de avisos e notificações (F5.2, documentação) — 2026-10-16

Classificação PATCH (documentação). Define avisos obrigatórios no site, e-mail opt-in agrupado por tópico, uso do
sistema existente e agrupamento por contador. Implementação na F5.2. Ver `docs/portal/AVISOS-E-NOTIFICACOES.md`.

## 0.4.0-dev.52 — caminhos em /painel-administrativo e fóruns para marcadores — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.19. Caminhos internos do ACULTA padronizados em
`/painel-administrativo/` (inclusive configurações, em `configuracoes/aculta`). Fóruns podem ser restritos a marcadores.

## 0.4.0-dev.51 — associado como marcador puro — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.18. Associado é marcador em tabela própria, sem efeito em acesso.

## 0.4.0-dev.50 — confirmação manual de e-mail e autores fora dos usuários — 2026-10-16

Classificação MINOR. Confirmação manual de e-mail pelo administrador (permissão própria, lista e formulário com
registro em log sem o endereço). Autores históricos separados dos usuários (`aculta_user` 0.4.0-dev.17).

## 0.4.0-dev.49 — F9: moderação e papéis múltiplos — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.16. Moderação do Fórum pelo `content_editor` com e-mail confirmado;
conteúdo histórico fora da moderação. Confirmação manual de e-mail pelo administrador ainda não existe.

## 0.4.0-dev.50 — fundação opcional do aculta_lms (F1) — 2026-10-16

Classificação MINOR. Novo submódulo opcional `aculta_lms` com contrato de evento para status de curso aprovado,
publisher DI e testes Unit (2/2) e Kernel (1/1) PASS. Sem ativação, alteração de dados/configuração, produtor ou
consumidor ativo. **RUNTIME STATUS: DEFERRED**; o serviço só foi instalado em fixture Kernel isolada.

## 0.4.0-dev.48 — F9: papéis e 404 para restritos — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.15. Hierarquia de papéis documentada e aplicada aos fóruns
restritos; visitante e bots recebem 404 nos restritos.

## 0.4.0-dev.47 — F9: fóruns restritos verificados — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.14. Sitemap, comentários e busca conferidos no teste. Falta: nível
de curso (F8) e política do `content_editor`.

## 0.4.0-dev.46 — F9 parcial: fóruns restritos — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.13. Fóruns restritos por nível funcionam para visitante,
e-mail confirmado e associado no teste. Ainda não tratados: índice de busca (processador `content_access`
desativado), sitemap, comentários em tópico restrito e o nível de curso (F8). Ver roadmap do Fórum.

## 0.4.0-dev.45 — denúncias do Fórum e política de e-mail confirmado (F5.1) — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.12 e a documentação da política de confirmação de e-mail, com
a aplicação no site e no Fórum (`docs/portal/EMAIL-CONFIRMATION-POLICY.md`, seções 9 e 10).

## 0.4.0-dev.44 — estrutura da identidade histórica do Fórum (F2) — 2026-10-16

Classificação MINOR. Inclui `aculta_user` 0.4.0-dev.11: serviço idempotente de contas legadas, acesso negado a
páginas individuais, memorial com busca e avatar especial pronto para a arte. Gates do Portal e testes unitários
passam. No Runtime de teste: campos aplicados por importação parcial, memorial 200, `/user/<uid>` de conta
legada 404 para anônimo, sorteio não atribui avatar a conta legada (defeito encontrado e corrigido no teste).

## 0.4.0-dev.43 — F2 estrutural do ACULTA Fórum — 2026-10-15

Classificação MINOR. Inclui o submódulo `aculta_user` 0.4.0-dev.10 (memorial `/u/lendashistoricas`,
papel `usuarios_legados_forum` e apelido reservado). Gates do Portal passam: Drupal 11+, fronteira
módulo/tema, endereços de ambiente e slugs públicos. No Runtime de teste: memorial responde 200,
`/u/` comum responde 404, cache reconstruído.

## 0.4.0-dev.42 — Game G5.4: arte cultural nas conquistas — 2026-10-15

Classificação MINOR. Game `0.1.0-dev.9`: quatro conquistas de participação
ganham ilustrações próprias vinculadas ao catálogo; um selo antiproibicionista
fica como fallback das próximas conquistas. Direção permanente de arte registrada
em `AGENTS.md` e `docs/portal/IMAGE-ART-DIRECTION.md`; os três PNGs históricos
foram preservados.

## 0.4.0-dev.41 — Game G5.3: conquistas no perfil público — 2026-10-15

Classificação MINOR. Game `0.1.0-dev.8`, User `0.4.0-dev.9`: perfis
`/u/<apelido>` apresentam conquistas obtidas em cartões responsivos com imagens
ou símbolo ilustrativo, descrições e destaque, respeitando escolhas de
privacidade. Avatar sem foto usa o acervo atual do `aculta_user`. Upload orienta
imagem quadrada de 512 × 512 px. Firefox de desktop/mobile e gates Drupal 11
verificados no Homelab.

## 0.4.0-dev.40 — Game G5.2: avisos, e-mail e confirmação de imagem — 2026-10-15

Classificação MINOR. Game `0.1.0-dev.7`, User `0.4.0-dev.8`: catálogo único
com apresentação inspirada no GamiPress, upload e remoção confirmada de imagem,
aviso dispensável de nova conquista e e-mail enfileirado com preferência pessoal.
Homelab: Firefox 14/14; exclusão do arquivo não compartilhado verificada; gates
Drupal 11, fronteira, endereços e slugs aprovados. Envio real de e-mail não
verificado. Sem versão de release ou tag.

## 0.4.0-dev.39 — Game G5.1: criação e gestão de conquistas — 2026-10-15

Classificação MINOR. Game `0.1.0-dev.6`, User `0.4.0-dev.7`: catálogo editável,
concessão/revogação com auditoria e histórico na área Game. Apoiadore reconhece
pedido, apoio direto concluído ou concessão administrativa. Update User 10003
aplicado no Homelab; dados anteriores preservados. HTTP 34/34, fontes e limites
registrados em `modules/aculta_game/docs/EXECUCAO.md`.

## 0.4.0-dev.38 — troféus históricos no Game — 2026-10-15

Classificação MINOR. Game `0.1.0-dev.5` importa três troféus com imagens originais;
User `0.4.0-dev.6` preserva IDs de Associado/Apoiador e acrescenta Fundadore manual.
Catálogo na conta/administração; projeção pública continua sujeita à privacidade.
Origem, autorização e hashes em `modules/aculta_game/assets/trophies/ORIGENS.json`.

## 0.4.0-dev.37 — ACULTA Game G4 — 2026-10-15

Classificação MINOR. Game `0.1.0-dev.4`: missões unitárias, caixa na visão geral e
administração `/painel-administrativo/game`, abaixo de Avisos, com permissão
própria. Configuração pontual aplicada no Homelab. Firefox 8/8 e HTTP 17/17;
privacidade/cache de G3 verificados. Detalhes em `modules/aculta_game/docs/EXECUCAO.md`.

## 0.4.0-dev.36 — perfil público anônimo acessível — 2026-10-15

Classificação PATCH. Messenger `0.1.1-dev.32` evita consultar dispensa de aviso
para anônimo sem sessão Flag. Corrige erro 500 observado nos testes públicos do
Game; HTTP anônimo e privacidade entre sessões verificados no Homelab.

## 0.4.0-dev.35 — ACULTA Game G3 — 2026-10-15

Classificação: MINOR (conquistas na conta e no perfil com escolhas de exibição).

- Game `0.1.0-dev.3` usa contratos neutros do User `0.4.0-dev.5`, com ocultação,
  destaques, ordem e cache; preferências preservadas no User Data do User.
- Status/gate incluem helper opcional declarado pelo próprio submódulo.
- Sem alteração do tema ou nova rota; evidências e limites funcionais registrados
  em `modules/aculta_game/docs/EXECUCAO.md`.

## 0.4.0-dev.34 — ACULTA Game G2 — 2026-10-15

Classificação: PATCH (integridade dos gatilhos e da API existente).

- Game `0.1.0-dev.2` trata aprovação tardia, publicação de revisão e API real do
  LMS; User `0.4.0-dev.4` coordena concessões/revogações sob lock até commit.
- Sem novas dependências, migração de schema ou alterações das conquistas passadas.
- Evidências e limites em `modules/aculta_game/docs/EXECUCAO.md`.

## 0.4.0-dev.33 — ACULTA Game G1 — 2026-10-15

Classificação: MINOR (submódulo opcional; integração inicial da gamificação).

- Game `0.1.0-dev.1` assume os gatilhos automáticos, mantendo catálogo, serviço e
  entidade de concessões no User `0.4.0-dev.3`. Nenhuma migração de dados/schema.
- Fonte única, inventário e ativação pontual documentados em
  `modules/aculta_game/docs/EXECUCAO.md`. Fases posteriores permanecem no roadmap.

## 0.4.0-dev.32 — correções do Messenger e fronteira de classes — 2026-10-15

Classificação: PATCH.

- Template `aculta_portal_element` inalterado; `PortalLinkElement` usado pelo Messenger.
- `aculta_messenger` 0.1.1-dev.31: grupos na lista, menu sem reenvio, tokens no lugar de classes, aviso em português.
- Gate `validate-module-theme-boundary`: varre os submódulos; dívida conhecida registrada no próprio gate e em `DEBT-REGISTER.md`.
- Documentação, sem avanço de versão: abertos os projetos `aculta_forum` (Growroom)
  e `aculta_game`, com decisões de 2026-10-15, arquitetura, roadmaps, pesquisa e
  evidência de licenças. Os diretórios ainda não são extensões instaláveis; sem
  alterações de domínio, identidades, dependências ou configuração de Runtime.

## 0.4.0-dev.31 — confirmação para bloquear e botões da nova conversa — 2026-10-15

Classificação: PATCH (confirmação de bloqueio no perfil e na conversa; botões da nova conversa).

- `aculta_messenger` 0.1.1-dev.30: a caixa "Opções" pede confirmação antes de bloquear e de desfazer amizade, e "Cancelar" / "Voltar para o início" ficam acima de "Enviar".

## 0.4.0-dev.30 — menu "Opções" no perfil público — 2026-10-15

Classificação: PATCH (novo ponto de extensão do perfil público).

- `aculta_user`: `PublicProfileController` passa as ações do cabeçalho por `aculta_user_public_profile_actions` (alter), para que outros módulos as completem.
- `aculta_messenger` preenche essa caixa com "Opções" (ver `aculta_messenger` 0.1.1-dev.29). Sem o messenger, o perfil continua como antes.
- Validação: capturas de tela no runtime de teste.

## 0.4.0-dev.29 — traduções pt-BR do Drupal e extensões — 2026-10-15

Classificação: PATCH (localização da interface administrativa).

- Conferidos 62 projetos de tradução associados às 139 extensões habilitadas; atualizados os pacotes pt-BR disponíveis, incluindo Drupal Core 11.4.10 e os módulos `private_message`, `message`, `message_notify`, `message_subscribe` e `site_alert`.
- Catálogo `translations/modules.pt-br.po` acrescenta nomes e mensagens administrativas de extensões ACULTA e de `flexible_permissions`/`username_enumeration_prevention`, cujas versões instaladas não têm arquivo pt-BR no servidor de traduções.
- Catálogos existentes do Portal e do tema foram importados sem sobrescrever traduções manuais; no Locale, o total passou de 13.336 para 13.658 strings pt-BR.
- Verificado pelo serviço de tradução: `Edit` → “Editar”, `Add content` → “Adicionar conteúdo”, `Clear history` → “Limpar histórico”, `Member(s)` → “Membro(s)” e `Private Message Threads` → “Conversas de Mensagens Privadas”.
- RUNTIME STATUS: ACTIVE no servidor de testes (`aculta_runtime`, `https://aculta.toca.net.br`). `locale:update --langcodes=pt-br` importou 14 pacotes disponíveis (300 strings adicionadas, 10.561 atualizadas); catálogos ACULTA importaram mais 22 strings e atualizaram 27.
- No arquivo local, fora do Git, `settings.local.php` define `$settings['locale_translation_path'] = 'public://translations'`. Em outros ambientes, executar a importação do catálogo ACULTA com `drush locale:import --type=customized --override=none pt-br web/modules/custom/aculta_portal/translations/modules.pt-br.po`.

## 0.4.0-dev.28 — amizade sem erro fatal e Turnstile fora das Opções da conversa — 2026-10-15

Classificação: PATCH (correção no serviço de amizade e exceção de CAPTCHA).

- `FriendshipManager`: `access()` é chamado com `TRUE` em `loadPair`, `getRelationshipWith` e `transition`. Sem isso, o método devolvia um booleano e o `isAllowed()` que vinha depois quebrava (erro fatal) em qualquer par que já tivesse relação.
- `TurnstileToggle::EXCEPTION_FORM_IDS` inclui `aculta_messenger_conversation_relationship` (ações de amizade e bloqueio na conversa). Ponto desativado em `config/sync/captcha.captcha_point.aculta_messenger_conversation_relationship.yml`; documentado em `docs/portal/TURNSTILE.md`.
- Verificado por POST no runtime de testes: rejeitar, aceitar e cancelar pedidos, bloquear e desbloquear, com o estado gravado em `aculta_friendship`.

## 0.4.0-dev.27 — R4: templates e política de e-mail — 2026-10-15

Classificação: PATCH (localização do e-mail administrativo e precisão da configuração de notificações).

- Template de verificação do transporte Mailer Plus localizado para português.
- Gate de e-mail protege a notificação pública do contato em texto simples, o Reply-To validado, a ausência de notificações não aprovadas na participação e a localização do template de verificação.
- Configuração do contato permanece `html: false`: os valores da submissão são incluídos e o formato `email_html` exportado não aplica filtros. O teste histórico com HTML ativado temporariamente valida a compatibilidade do Mailer Plus, não altera a configuração ativa.
- Não houve envio de mensagem nem alteração no Runtime.

## 0.4.0-dev.26 — SMTP2GO com TLS obrigatório e fallback fechado — 2026-10-15

Classificação: PATCH (segurança do transporte SMTP; mantém SMTP2GO como provedor institucional).

- O transporte SMTP2GO usa porta 465, que inicia TLS implícito; a verificação de certificado e hostname permanece habilitada.
- O DSN experimental do Symfony Mailer Core deixou de selecionar `sendmail`; o esquema `aculta-disabled` falha se o backend Core escapar do `mailer_override`.
- `validate-mail-transport.php` agora confere backend, transporte padrão, TLS real construído pelo Symfony, Key overrides/providers e rejeição do fallback Core.
- Runtime de testes: transporte padrão `smtp2go`, Keys disponíveis em memória (valores não lidos), porta 465, peer verification ativa; cache rebuild e home HTTP 200. TLS 1.3 e certificado do host SMTP2GO verificados sem autenticação e sem envio.
- **DEFERRED:** tentativa autenticada por SMTP e teste de falha com credenciais ausentes; não houve envio de mensagem. Hostinger não acessada, então a saída TCP 465 em produção precisa de homologação.
- R3: commit `6b2d72f`.

## 0.4.0-dev.25 — confirmação de e-mail resiliente — 2026-10-15

Classificação: PATCH (confiabilidade do envio e proteção contra reenvio abusivo).

- `EmailConfirmationRequester` agora persiste antes do envio, verifica o retorno do MailManager, mantém links anteriores utilizáveis em falhas e só cancela confirmações anteriores após aceitação do novo envio.
- Reenvio serializado por Lock e limitado a uma solicitação por conta a cada 900 segundos via Flood; a rota passou a usar formulário POST com proteção CSRF nativa da Form API.
- A resposta do cadastro permanece igual para endereços novos e existentes, sem afirmar entrega final. O resultado `ACCEPTED` significa aceitação pelo MailManager.
- Testes unitários do requester: 7 testes, 104 assertions; testes unitários incluindo login: 13 testes, 122 assertions. Gate Drupal 11+: PASS (772 checks); cache rebuild no Runtime de testes; home HTTP 200 e rota de reenvio anônima HTTP 403.
- Confirmação de uso único e geração do link de purpose ACCOUNT permanecem sem teste automatizado nesta fase; o caminho de URL existente foi inspecionado e mantém `DomainPurposeManager`. RUNTIME STATUS: ACTIVE no Runtime de testes; entrega final na caixa postal não comprovada.
- Implementação: commit `0eac9ef`.

## 0.4.0-dev.24 — formulários do Messenger fora do Turnstile — 2026-10-15

Classificação: PATCH (exceções da política de CAPTCHA).

- `Captcha\TurnstileToggle::EXCEPTION_FORM_IDS` passa a incluir `aculta_messenger_new_conversation` (compor a primeira mensagem na gaveta) e `private_message_add_form` (resposta nas conversas). O formulário de mensagens só é acessível a contas com e-mail confirmado; o desafio aparecia para contas sem a confirmação, e o widget não era montado no fragmento da gaveta, o que impedia o envio.
- Pontos de CAPTCHA desativados em `config/sync`: `captcha.captcha_point.aculta_messenger_new_conversation.yml` e `captcha.captcha_point.private_message_add_form.yml`.
- `docs/portal/TURNSTILE.md` documenta as duas exceções; `scripts/validate-turnstile-exceptions.php` passa (5 exceções).
- Verificação: conta confirmada envia sem desafio no runtime de testes. O caso de conta sem confirmação não foi reproduzido, porque `EmailConfirmedRoleGuard` reaplica o papel `email_confirmed`.

## 0.4.0-dev.23 — produção Hostinger LiteSpeed documentada — 2026-10-15

Classificação: PATCH (correção da fonte de verdade operacional; sem mudança no runtime Drupal).

- Corrige a arquitetura para Homelab Apache e produção Hostinger Cloud Startup/LiteSpeed, observada em cabeçalhos HTTP públicos.
- O ADR-010 substitui o pressuposto de mesmo servidor; o runbook mantém `.htaccess` e separa validações por ambiente.
- ACULTA Deployer 0.1.22 atualiza o registro de DEP-0003 e não presume diretivas Apache em produção.
- `aculta.org` respondeu HTTP 200 com `platform: hostinger`, `x-turbo-charged-by: LiteSpeed` e `server: cloudflare` em consulta pública somente leitura. Nenhuma configuração do servidor foi alterada.
- RUNTIME STATUS: DEFERRED — nenhuma validação no Homelab/Hostinger após alteração documental e do registro do deployer.

## 0.4.0-dev.23 — sanfona da conta no celular: seções independentes e conteúdo no painel certo — 2026-10-15

- Cada seção abre e fecha de forma independente; abrir uma não fecha as outras.
- Só o corpo da seção entra no painel aberto no celular. Título e cabeçalho compartilhados ficam fora dos painéis, então a troca de seção não altera o título de outra sanfona.
- RUNTIME STATUS: ACTIVE no runtime de testes (membro comum, 360 px).

## 0.4.0-dev.22 — central de avisos administrativa e gate de slugs por módulos habilitados — 2026-10-15

- Porta ao `aculta_messenger` a central Avisos em `/painel-administrativo/avisos`, com abas temporários e persistentes; a listagem de persistentes passa IDs escalares às rotas de editar/excluir (corrige o TypeError do dev.21).
- `validate-public-slugs.php` passa a considerar só rotas de módulos contrib habilitados em `config/sync/core.extension.yml`; `message_subscribe_ui` está no código, mas não habilitado, e não gera rota no site.
- `validate-aculta-portal-drupal11.php`: gate PASS (768 checks); contagem de subscribers mantida em 12.
- Validação: lint, testes unitários do Messenger (17), gates de slugs e Drupal 11+, cache rebuild e smoke autenticado no runtime de teste (host principal 200 nas rotas de avisos; conta redireciona para o principal). RUNTIME STATUS: ACTIVE no runtime de testes; inspeção visual pendente.
- Entrega: PR #285.

## 0.4.0-dev.21 — corrige erro na listagem de avisos persistentes — 2026-10-14

- URLs administrativas de editar/excluir agora recebem o ID do Node, evitando TypeError no requisito de rota do Drupal.
- Erro HTTP 500 reproduzido antes da correção; verificação posterior pendente. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.20 — posição do composer do Messenger — 2026-10-14

- O formulário nativo de resposta fica após o histórico, evitando aparecer antes das mensagens na página e no diálogo responsivo.
- CSS do ACULTA420 remove o sticky do composer e limita a área de histórico em telas pequenas.
- Smoke HTTP autenticado local confirmou a ordem do histórico e do composer; gate Portal (768) e Foundation (341): PASS. RUNTIME STATUS: DEFERRED; validação em dispositivo mobile pendente.

## 0.4.0-dev.19 — centralização e navegação de conversas do Messenger — 2026-10-14

- O ACULTA Messenger prepara a mesma lista de conversas para a página de Mensagens e para a gaveta; threads abrem por AJAX somente no host ACCOUNT e usam navegação normal até a conta nos demais purposes.
- Inclui retorno à lista no thread sem alterar o storage, access handler ou Form API do Private Message.
- Smoke HTTP autenticado e PHP lint locais: PASS; gates Portal (768) e Foundation (341): PASS.

## 0.4.0-dev.18 — restaura navegação AJAX da Visão geral — 2026-10-14

- Corrige a construção do link de perfil na Visão geral quando há apelido legado fora do formato da rota; a página não falha e o link inválido é omitido.
- Centraliza a normalização lowercase de slugs públicos no helper `PublicRouteSlug` e aplica o contrato aos links de perfil do painel e da Revista.
- Status administrativo lista o ACULTA Portal e os submódulos habilitados; uploads novos usam sanitização nativa de nomes do Drupal configurada para ASCII/hífen, com extensão também canonicalizada para minúsculas antes da validação final do Core.
- Status passa a apresentar **Implementações do ACULTA Portal**, listando Helpers e Extras runtime aplicados por tags ativas do container; os registros são verificados contra YAML e documentação pelo gate Drupal 11+.
- Verificação desta atualização: `php scripts/validate-aculta-portal-drupal11.php` PASS (768 checks), PHP lint dos arquivos alterados PASS, `git diff --check` PASS e YAML de serviços válido.
- Navegação AJAX da conta deixa de mostrar throbber e cursor de progresso; `aria-busy` continua disponível para tecnologias assistivas.
- Gates Drupal 11+ (715 checks), links de perfil (387 arquivos), fronteira módulo/tema (969 checks), testes unitários (3 testes/6 assertions) e PHP lint passaram. Relatório Status e evento de sanitização exercitados por Drush no `aculta_runtime`; entrada `Relatório Árvore__ABC.JPG` resultou em `relatorio-arvore-abc.jpg`. `file.settings` está alinhado ao Config Sync. Upload real pelo formulário e sessão autenticada da Minha Conta permanecem sem validação humana; ambiente CLI usa PHP 8.4.26.

## 0.4.0-dev.17 — contador neutro nas seções da conta — 2026-10-14

- `AccountShellBuilder` encaminha opcionalmente `badge_count` como número neutro junto aos itens da navegação, sem classes ou markup de apresentação.
- ACULTA Messenger expõe a contagem nativa de threads não lidas nessa propriedade para o ACULTA420.
- PHP lint, Portal Drupal 11+ gate (696 checks), Portal/theme boundary (907 checks), unique paths, Portal UI docs (167 tokens/132 usados/41 atributos), environment-address gate (1.561 arquivos), SDC schemas e design foundations passaram.
- Foundation gate dependente de bootstrap Drupal não foi executado: esta cópia não tem settings/runtime. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.16 — extensão opcional para seções da Minha Conta — 2026-10-14

- `AccountShellBuilder` expõe alters neutros para acrescentar seções opcionais e mapear suas subrotas à seção ativa.
- ACULTA Messenger usa esses alters para incluir Mensagens somente a contas com as permissões nativas do Private Message.
- Nenhuma mudança visual no ACULTA420 nesta entrega.
- Gates estáticos e PHP lint passaram na implementação original; Runtime e bootstrap Drupal não foram validados nesta cópia. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.15 — elementos de presentation sem objeto cruzando a fronteira — 2026-10-14

Classificação: PATCH (correção de contrato interno; mesma marcação).

- A dica da busca da Wiki (`WikiSearchFormBuilder`) passa a enviar `#element_attributes` como array, e não como
  `Attribute`. O objeto é criado no preprocess de `aculta_portal_element` (`PortalHooks::preprocessPortalElement`),
  no último passo antes do template. Assim a presentation do domínio não contém objetos (gate
  `validate-domain-presentation-contract`: PASS, 127 verificações).
- Verificado no Homelab: a dica sai com as classes do tema (`small text-body-secondary`), o que prova a conversão.
- RUNTIME STATUS: verificado no Homelab (gate e renderização isolada). Inspeção visual humana pendente.

## 0.4.0-dev.14 — popup "Entrar" na barra multidomínio, sem Turnstile; modais com fundo escurecido — 2026-10-14

- "Entrar" da barra multidomínio, para visitante não autenticado, abre o login em modal no próprio host (`use-ajax`).
  O formulário `LoginPopupForm` herda o login do Core (`user.auth`, flood control, bloqueio de conta). A página
  `/entrar` continua existindo e mantém o Turnstile.
- Exceção do Turnstile: o popup (`aculta_login_popup_form`) não recebe o desafio. Junto com a loja (carrinho e
  checkout) e o cadastro feito pelo fluxo de compra, forma a lista `TurnstileToggle::EXCEPTION_FORM_IDS`.
- Barreira: `scripts/validate-turnstile-exceptions.php` e seção "Exceções" em `docs/portal/TURNSTILE.md`.
- Tema ACULTA420 (mesma entrega): visual do popup conforme o anexo e escurecimento do fundo de todos os modais
  (`.ui-widget-overlay` e `dialog::backdrop`, com `--aculta-scrim`).
- Popup "Entrar": botão "Continuar com Google" no topo, pelo mesmo fluxo OAuth (rota do host da conta, retorno ao host de origem).
- Popup "Entrar": ordem senha → Entrar → "Esqueceu sua senha?" → divisor → "Cadastre-se". O botão não fica no rodapé do diálogo (contêiner sem `form-actions`).
- Minha conta > Conexões: caixa do Google com logo (`assets/brands/google.svg`): "Conectar Google" sem conexão e "Conectado" com check quando conectada. A conta e o botão de desconectar ficam em bloco próprio.
- Popup de aceite dos termos: diálogo modal do Drupal (core/drupal.dialog), sem X e sem fechar por Esc; só o aceite fecha. Visual no tema (portal-account-terms-modal.css).
- Documentação e guardrails de inserção no tema: `docs/portal/TOKENS-E-ATRIBUTOS.md` (todos os tokens e atributos), `docs/portal/POPUP-PADRAO.md` (popup padrão = modal do Drupal, com exceções explícitas). Gates `validate-portal-ui-docs.php` e `validate-portal-popup-rule.php`.
- Diálogos do módulo sem tamanho próprio: bio, redes, foto, recorte e segredos passam a ter tamanho no tema (`dialog.css`, por `data-portal-ui`). Classes de design em formulários e links trocadas por tokens (`button-secondary`, `support-form`, `security-password-form`, `account-photo-trigger`).
- Fronteira módulo × tema nos diálogos do Portal: largura e posição do popup de aceite, do popup "Entrar" e da busca saem do módulo (`data-dialog-options`/`account-terms-modal.js`) e vão para o tema (`portal-account-terms-modal.css`, `dialog.css`). Classe de design removida do formulário de aceite.
- Fronteira mantida: o módulo não emite classes de design; os tokens `login-popup` e `login-popup-links` são mapeados
  no tema (`PortalUiHooks`).
- RUNTIME STATUS: DEFERRED. Pendentes: teste de navegador do popup (TESTES-NAVEGADOR, seção R) e teste com outro tema
  ativo (Olivero) para confirmar que o login funciona sem o ACULTA420.

## 0.4.0-dev.13 — painel administrativo: edição e exclusão de conteúdo funcionam em todos os hosts — 2026-10-13

- Edição e exclusão de conteúdo a partir da listagem do painel: o GET em host de purpose canonicaliza para MAIN.
  O Core reescrevia o redirecionamento com o `destination` do host errado; o Portal remove `destination` da requisição
  e leva o retorno absoluto para MAIN (`DomainPurposeRequestSubscriber`).
- Listagens administrativas de conteúdo e de pessoas: o nome do autor/usuário não linka mais para `/identidade/{usuário}`
  (página pública da Conta, 404 em MAIN). A operação "Ver" de usuários sai da coluna de operações (`EntityHooks`).
- Item "Editar perfil" do painel (Navigation) aponta para `aculta_portal.admin_self_edit` (`/painel-administrativo/perfil/editar`),
  que redireciona para o formulário do usuário logado.
- Verificado no Homelab com a conta administradora: todos os links do painel respondem 200 (inclusive os seguidos
  por redirecionamento) e a edição de conteúdo dos cinco hosts de purpose abre o formulário em MAIN.
- RUNTIME STATUS: verificado no Homelab. Inspeção visual humana pendente.

## 0.4.0-dev.12 — espaço de SEO na publicação da Revista (blog) — 2026-10-13

- `EditorialHooks::formNodeFormAlter`: o grupo "SEO e compartilhamento" (campo `field_meta_tags`) passa a aparecer
  também no formulário do tipo `blog`, com o mesmo espaço da Notícia (`article`), `activity` e `project`.
- Editor com papel `content_editor` tem acesso de edição ao campo (verificado no Homelab).
- RUNTIME STATUS: verificado no Homelab (formulário montado com a conta do editor). Inspeção visual humana pendente.

## 0.4.0-dev.11 — exibir a dica da busca Wiki após digitação — 2026-10-13

- O painel de prévia da Wiki começa recolhido; focar ou clicar no campo sem
  digitar mantém a página limpa.
- A dica de mínimo de três caracteres aparece após o primeiro caractere. Ao
  apagar o termo, o painel se recolhe; com três ou mais caracteres, a dica sai
  enquanto as sugestões são carregadas.
- A submissão GET continua disponível como fallback. **RUNTIME STATUS: DEFERRED**
  até validação interativa no servidor de testes.

## 0.4.0-dev.10 — prévia interativa na busca do Wiki420 — 2026-10-13

- A busca do cabeçalho Wiki reutiliza o comportamento compartilhado de prévia
  (pausa de 300 ms, mínimo de 3 caracteres, cancelamento de requisições antigas,
  navegação por teclado e envio normal do formulário como fallback).
- O endpoint `/wiki/busca/previa` consulta somente verbetes publicados no Domain
  Wiki e visíveis para a conta atual; retorna HTML `no-store` e um link para a
  busca completa com o termo.
- O controller compartilha a mesma consulta entre sugestões e resultados
  completos. `DomainRouteSubscriber` atribui a nova rota ao purpose `wiki`.
- PHP lint, JavaScript syntax, Drupal 11+ (680), contrato de apresentação (127),
  fronteira módulo/tema (789), design foundations (1683), SDC (231), shell (11),
  fixtures do shell (19) e slugs públicos (339 rotas): PASS. No Runtime local,
  home, preview com resultados e preview abaixo do mínimo responderam HTTP 200;
  a resposta usa `Cache-Control: no-store, private` e traz só verbetes da Wiki.
  Interação por teclado no navegador de testes ainda pendente.
  **RUNTIME STATUS: DEFERRED.**

## 0.4.0-dev.9 — busca no cabeçalho neutro da Wiki420 — 2026-10-13

- `DomainPresentationBuilder` oferece a região renderizável `wiki_search` apenas
  para Wiki420. O formulário GET usa a rota existente e preserva `q` na página
  de resultados; a query é indicada no cache context `url.query_args:q`.
- Um construtor único monta a busca neutra consumida pelo shell e preserva o
  termo no cabeçalho quando a página de resultados está aberta. Home e resultados
  deixam de renderizar campos duplicados.
- O ACULTA420 consome a região, oculta o menu global somente nesse shell e
  mantém a busca disponível junto da marca. A Institution Bar permanece intacta.
- Gates locais: Drupal 11+ (680), contrato de apresentação (127), fronteira
  módulo/tema (789) PASS. Home e artigo retornam HTTP 200 no Runtime local; os
  artigos de teste (nids 72 e 73) foram criados somente em `aculta_runtime`.
  Nenhum conteúdo foi gravado em servidor remoto ou produção. Validação visual
  por navegador no servidor de testes pendente. **RUNTIME STATUS: DEFERRED.**

## 0.4.0-dev.8 — separação módulo x tema: marca de domínio neutra — 2026-10-13

- `DomainPresentationBuilder` não referencia mais o tema: envia `brand_media` como `['#brand_key' => 'wiki420', '#alt' => título]`. O asset e a imagem são montados pelo tema (aculta420 0.6.0-dev.17). Sem tema que trate a chave, o cabeçalho usa só o título.
- Gate `validate-module-theme-boundary` sem exceção para o builder (regra 1 passa a valer).
- Teste de compatibilidade com o tema Claro: painel, foto (modal, recorte, remover) funcionam sem o ACULTA420.
- `aculta_portal.info.yml` 0.4.0-dev.8. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.7 — integração da apresentação enciclopédica da Wiki — 2026-10-13

- O Portal deixa de incluir nome do autor na lista de alterações recentes da
  Wiki; título e data permanecem. Nodes/revisões continuam com autor para
  workflow e administração.
- A configuração de wiki_entry desativa o byline público (display_submitted).
- Integração visual permanece por render arrays neutros; sem classes de tema no
  Portal. Gate Drupal 11+ PASS (659 checks); local HTTP nos caminhos Wiki PASS.
  Import de configuração e Runtime remoto DEFERRED.

## 0.4.0-dev.6 — correção do redirecionamento após o login — 2026-10-13

- Login (`user_login_form`) sai da regra AJAX de salvamento da Minha conta: a sessão era criada, mas a página não
  saía de `/entrar`. Roteiro de regressão em `docs/portal/TESTES-NAVEGADOR.md`, seção O.
- `aculta_portal.info.yml` 0.4.0-dev.6. RUNTIME STATUS: DEFERRED (validado no servidor de testes por HTTP; teste de navegador pendente).

## 0.4.0-dev.5 — botão de sortear avatar no popup de foto (aculta_user 0.3.0-dev.2) — 2026-10-12

- Painel da conta envolve a foto em um contêiner com id `aculta-user-avatar`, alvo do AJAX do sorteio.
- `aculta_portal.info.yml` 0.4.0-dev.5. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.4 — cadastro, avatar acervo, associado e relatório (aculta_user 0.3.0-dev.1) — 2026-10-12

- Nome de usuário vira apelido; conta OAuth usa a parte local do e-mail; acervo de avatares sem a família `mushrooms`.
- `aculta_portal.info.yml` 0.4.0-dev.4. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.3 — Atividade em Minha Conta (aculta_user 0.2.0-dev.2) — 2026-10-12

- Item "Atividade" no menu da conta, condicionado à existência da rota do submódulo `aculta_user`.
- `aculta_portal.info.yml` 0.4.0-dev.3. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.2 — participação no Fórum e discussão vinculada (aculta_user 0.2.0-dev.1) — 2026-10-12

- Submódulo `aculta_user` 0.2.0-dev.1: moderação do primeiro comentário, limite de tópico, painel de regras e
  discussão vinculada aos conteúdos. RUNTIME STATUS: DEFERRED (ver o CHANGELOG do submódulo).
- `aculta_portal.info.yml` 0.4.0-dev.2.

## 0.4.0-dev.1 — submódulo aculta_user: perfil público em /u/<apelido> — 2026-10-12

- Adiciona o submódulo `aculta_user` 0.1.0-dev.1 (ver o CHANGELOG do submódulo). Rota `/u/{apelido}`, política de
  visibilidade por item, formulário em `/configuracoes/perfil` e campos do perfil participante.
- `field_nickname` passa a ser público (`profile_private: false`).
- Troca de e-mail só pelo formulário de Minha Conta (`change_mail_page`): `EmailChangeHooks` remove o campo de e-mail da edição de conta do Core (administrador e conta própria). Criação de conta por administrador mantém o campo (`EmailChangePolicy`, teste unitário).
- Papel `email_confirmed` irreversível: `EmailConfirmedRoleGuard` (presave restaura o papel; formulário de administração trava o checkbox). `EmailConfirmationPolicy::markUnconfirmed` removido (sem uso em produção).
- Versões: `aculta_portal.info.yml` 0.4.0-dev.1 (MINOR: submódulo novo).
- RUNTIME STATUS: DEFERRED. Nenhum `drush en` ou `config:import` foi executado.
- Apelidos provisórios `pessoa-1` e `pessoa-5` criados no Runtime para as duas contas sem apelido (com backup
  prévio em `scratchpad`, fora do repositório).

## 0.3.0-dev.29 — integração compatível com Associado ID — 2026-10-12

- O Portal prioriza `aculta_associado_id.account_document` e mantém fallback
  temporário para `aculta_id_cpf.account_document` durante a migração controlada.
- Atualiza o contrato neutro e a documentação do Portal para definir fronteiras:
  submódulo orquestra domínio/dados, Portal integra funcionalidade e ACULTA420
  apresenta. O novo escopo de atas/relatórios ainda não está implementado.
- O módulo antigo fica oculto de novas instalações, mas permanece íntegro para
  instalações existentes. Nenhuma alteração de Runtime ou de banco.
- Verificações: gate Drupal 11+ (659 checks), runner de domínio (248 checks),
  PHPUnit do submódulo (47/107) e Portal (63/118) passaram; lint PHP passou.
- Evidência e plano de migração registrados em
  `docs/modules/ACULTA-ASSOCIADO-ID.md`; **RUNTIME STATUS: DEFERRED**.

## 0.3.0-dev.28 — Acabamento da revisão de Outros Dados — 2026-10-12

- Revisão documental do estado corrente após PRs #238 e #239: roadmap atualizado; S0 encerrada com evidência; S2/S3/S4 alinhadas ao registro de dívidas; snapshot de 2026-10-12 preservado como histórico.
- Nenhuma alteração funcional ou de Runtime; versão mantida conforme a regra para documentação isolada.

- `AccountDocumentStore` lê as definições de campo do perfil uma vez por instância; `save()` deixa de criar o perfil antes de verificar os campos: a checagem fica só em `canSave()`, que já valida `field_documento` e `field_documento_indice` para o bundle `participante`.
- `AccountOtherDataForm` centraliza a mensagem de indisponibilidade em um único ponto (`unavailableForm()` e `unavailableMessage()`), com o mesmo texto e os mesmos caminhos de decisão.
- O teste de disponibilidade deixa de fixar o número de consultas internas ao gerenciador de campos; cobre o contrato de leitura e gravação, não a implementação.
- `LoginValidatorSwapTest` estava desatualizado desde a inclusão de `AddToCartAjax` em `FormHooks`: o teste agora constrói os serviços finais reais (`DomainPurposeManager`, `CartUtility`, `AddToCartAjax`), em vez de duplicá-los. A suíte `aculta_portal` passa com 63 testes.
- Sem mudança de comportamento para o usuário. Runtime: não testado (apenas código e testes unitários). **RUNTIME STATUS: DEFERRED**.

## 0.3.0-dev.27 — Preserva leitura de documento salvo — 2026-10-12

- O contrato neutro `AccountDocumentInterface` separa disponibilidade de leitura (`isAvailable()`) da capacidade de gravar (`canSave()`). O `aculta_portal` tenta ler e apresentar o documento existente, mascarado e somente leitura, mesmo quando o HMAC ou o campo de índice ainda não está provisionado; formulário de inclusão só aparece quando cifra, HMAC e ambos os campos estão disponíveis.
- O submódulo `aculta_id_cpf` mantém validação, cifra, fingerprint e persistência. O tema `aculta420` não foi alterado e continua responsável apenas pela apresentação.
- Testes internos cobrem leitura disponível sem HMAC/índice, leitura indisponível sem o campo cifrado e gravação disponível com os requisitos completos. Runtime: sem migração de banco e sem gravação/alteração de documentos.
- **RUNTIME STATUS: DEFERRED** — o Runtime tem documento cifrado, mas não pode ser consultado em conta de titular para validar o valor exibido. O valor permanece protegido; chave HMAC/campo índice ainda não provisionados.

## 0.3.0-dev.26 — Proteção inicial de Outros Dados — 2026-10-12

- `AccountDocumentStore::isAvailable()` passou a exigir todos os recursos de leitura e gravação. A verificação evitava acesso a campos incompletos, mas também ocultava documentos cifrados já existentes quando faltavam HMAC ou campo de índice. Esta versão intermediária nunca foi publicada: o defeito foi corrigido no mesmo PR, em `0.3.0-dev.27`. O POST também falhava de forma controlada se a configuração ficasse incompleta após a renderização.
- Validação no servidor de testes `conta.aculta.toca.net.br`: GET autenticado por link de uso único respondeu HTTP 200 e mostrou a mensagem de indisponibilidade prevista, sem página de exceção; log sem erro `AccountDocument`. Estado do Runtime Homelab: atualização `10001` pendente, configuração de `field_documento_indice` ausente e chaves do submódulo indisponíveis.
- **RUNTIME STATUS: PASS (GET autenticado)** — a apresentação da página foi verificada; gravação de documento não foi tentada enquanto os campos e as chaves não estão provisionados. Não executei `updb` nem alterei dados.

## 0.3.0-dev.25 (documental) — relatório de estado — 2026-10-12

- Mudança apenas documental; `aculta_portal.info.yml` não muda. Adiciona `docs/portal/historico/RELATORIO-ESTADO-2026-10-12.md` com o estado do Portal, dos submódulos e do tema, e corrige a versão em `docs/portal/ROADMAP.md`.
- Achados registrados no DEBT-REGISTER: DT-O12 (`aculta_deployer` com falhas de teste e de `check`) e DT-T19 (gate Foundation do tema).

## Em desenvolvimento — ajuste de escopo aculta_id_cpf — 2026-10-12

- Decisão do responsável: aculta_id_cpf não implementa campos, widgets, elementos
  Form API, máscaras JavaScript ou integração Webform nesta etapa. Os campos PF/PJ
  e a máscara serão implementados manualmente no aculta_portal, consumindo os
  serviços do submódulo.
- Elementos e máscara foram removidos após homologação local em PHP 8.4.26; testes
  de domínio continuam aplicáveis. Testes de UI/Form API descrevem somente a
  implementação anterior, retirada por decisão de escopo.
- Próximo passo: campos PF/PJ manuais no Portal antes de persistir documentos.

## 0.3.0-dev.25 — Botões adicionais administráveis na página LINKS — 2026-10-12

- A configuração de Redes sociais mantém “Visite nosso Site” fixo e permite adicionar/remover botões com texto e link.
- Aceita URL HTTPS ou caminho interno seguro; pares incompletos e esquemas não HTTPS são recusados. Configuração continua em `aculta_portal.social_links`.
- Formulário dinâmico usa Form API/AJAX e callbacks resolvidos pelo serviço `CallableResolver`.
- Runtime: Form Builder renderizou o botão fixo e os campos; callbacks de inclusão/remoção mantiveram os dados restantes; URL `javascript:` rejeitada. Envio autenticado e salvamento por navegador pendentes.

## 0.3.0-dev.24 — CTA principal da página LINKS — 2026-10-12

- Troca o botão Instagram por “Visite nosso Site”, apontando à raiz do purpose MAIN via `DomainPurposeManager`; o host acompanha o ambiente.
- Os perfis sociais configurados permanecem no rodapé dedicado.
- Servidor de testes: HTTP 200, CTA e destino `https://aculta.toca.net.br/` conferidos; a rede social permanece no HTML do rodapé.

## 0.3.0-dev.23 — Administração de redes sociais e carrossel VVJB em LINKS — 2026-10-12

- Administração > Configuração > ACULTA Portal > Redes sociais configura Instagram, YouTube e Facebook. URL HTTPS e host oficial são validados; campo vazio oculta o canal.
- `SocialLinksPagePresenter` lê a configuração, executa o display VVJB da View `aculta_projects` e entrega render arrays. A View mantém acesso/cache e usa o teaser existente.
- Teasers na página LINKS apontam ao purpose MAIN, com alias do projeto e metadados de cache por host e idioma.
- Servidor de testes: formulário configurável, página HTTP 200 e estrutura mobile inspecionada visualmente.

## 0.3.0-dev.22 — Purpose LINKS para redes sociais — 2026-10-12

- Adiciona o purpose `links` ao contrato de domínio e resolve a raiz pública `/` para a página Drupal interna `/redes-sociais`.
- Apresenta os canais cadastrados no campo Core Link e referências acessíveis aos projetos publicados, com URLs do purpose MAIN resolvidas pelo `DomainPurposeManager`.
- Homelab: HTTP 200; `X-Robots-Tag: noindex, nofollow, noarchive`; estrutura verificada com barra institucional, sem cabeçalho/rodapé comuns, canal Instagram e quatro projetos.
- A configuração e o conteúdo do teste foram provisionados por Drupal API. O alias DNS de produção não foi criado.

## 0.3.0-dev.21 — Outros Dados (CPF ou CNPJ) em Meus dados — 2026-10-12

Classificação: MINOR, nova aba com gravação de dado pessoal sensível.

- Nova aba “Outros Dados” ao lado de “Endereço” em Meus dados (`/dados/outros-dados`). Aparece somente quando o submódulo `aculta_id_cpf` está habilitado; sem ele, a rota responde 404.
- Contrato neutro `AccountDocumentInterface`: o Portal não depende do submódulo; a implementação é injetada opcionalmente.
- Campo único CPF ou CNPJ, com ou sem pontuação (`000.000.000-00`, `00.000.000/0000-00`); o tipo é detectado pelo formato e os dígitos verificadores são conferidos no servidor.
- Gravação única: depois de salvo, o valor aparece em somente leitura e não pode ser alterado.
- Cifra simétrica (libsodium) com chave do Drupal Key `aculta_document_encryption_key` (variável `ACULTA_DOCUMENT_ENCRYPTION_KEY`). Sem chave, nada é gravado em claro e a aba mostra “indisponível”.
- Runtime: **RUNTIME STATUS: DEFERRED**. Testes de domínio do `aculta_id_cpf` passaram (248 verificações). Não houve teste de formulário nem de gravação no servidor de testes.
- Pendências: gerar a chave no arquivo de segredos do Homelab (o agente não tem permissão de escrita nesse arquivo); provisionar em produção; `drush en aculta_id_cpf`; testar a aba no navegador.
## Em desenvolvimento — aculta_id_cpf 0.1.0-dev.1 — 2026-10-12

Classificação: MINOR (nova capacidade opcional, primeira entrega priorizada pelo responsável).
Portal permanece 0.3.0-dev.16; nova marcação/release do módulo pai não declarada sem homologação.

- Novo submódulo opt-in: validadores locais de CPF/CNPJ numérico e alfanumérico,
  normalização estrita e helpers de formatação Drupal 11.3+. Elementos Form API
  e máscaras testados na rodada inicial foram removidos nesta atualização de escopo.
- Sem alteração de login, dados pessoais Profile/User, tema, banco ou config sync.
- Runner (248 verificações), PHPUnit Unit (34/63), lint e gate Drupal 11+ (583)
  passaram em PHP 8.4.26. Homologação local detalhada: docs/modules/ACULTA-ASSOCIADO-ID.md (antes ACULTA-ID-CPF.md, renomeado em c27f9a3).
- RUNTIME STATUS: DEFERRED para Homelab remoto; sem release ou tag.
- Evidência: docs/modules/ACULTA-ASSOCIADO-ID.md; base 368ec263189d670d459e385b068d7bb188af95b3.

## 0.3.0-dev.20 — Senha em conta Google, envio no checkout e aba Segurança — 2026-10-12

- Minha conta > Segurança > Senha: contas criadas com Google, sem senha escolhida, ganham o formulário "Criar senha" (`AccountPasswordCreateForm`), sem pedir a senha atual. Conta com senha escolhida mantém "Alterar senha". Abas E-mail e Senha têm URL própria (`docs/portal/SENHA-CONTA-OAUTH.md`).
- Foco no primeiro campo com erro em formulários da Minha conta (AJAX); o formulário de criar senha recarrega a aba depois de gravar.
- Alinhamento do S/N no endereço (CSS do componente).
- Checkout: pane "Envio" (`AcultaShippingInformation`, subclasse do pane do Commerce Shipping). Não tem formulário de endereço e reaproveita a cópia de cobrança (`BillingAddressPane::billingCopy()`), ou seja, o endereço único do cliente.
- Dependências: `commerce_shipping` e `physical`, habilitados no runtime de teste e em `config/sync/core.extension.yml`. Variação `camiseta_tamanho` com trait de envio; método "Envio" com taxa R$ 0,00 (valor a definir), criado no runtime. Loja e método são conteúdo de banco, não entram em `config/sync`.
- Validação: gates `validate-aculta-portal-drupal11`, `validate-public-slugs` e `validate-environment-addresses` passam. Verificação de navegador pendente: foco no primeiro erro, S/N, criação de senha, frete em pedido novo com camiseta (`docs/portal/historico/PENDENCIAS-PROXIMA-RODADA.md`).

## 0.3.0-dev.16 — Aceite dos termos em popup persistente; contenção do Mercado Pago — 2026-10-11
- Meus Dados > Endereço: campo **Número** logo abaixo da rua. O número é separado do logradouro ao abrir e juntado ao gravar ("Rua, número"), mesmo formato do endereço de cobrança do checkout. Implementado pelo campo `address_extra[street_number]` (`PortalHooks`, rota `/dados/endereco`) e pelo entity builder `buildStreetNumber` (`PortalFormCallbacks`). Verificado no servidor de testes: o perfil guarda "Avenida Paulista, 1000" e o formulário reabre separado. Os dois perfis (Meus Dados e o do pedido) continuam separados, com o mesmo formato.

- Resumo do checkout: nome do produto no item (modo de visualização `summary` do bundle `camiseta_tamanho`, exportado em `config/sync`). Verificado por renderização: "Camiseta Abadá Bloco Sativa 2026 – Regata Tamanho M".
- Máscara do CEP `00000-000` em `js/cep-address.js` (biblioteca compartilhada por Meus Dados e checkout). A consulta continua usando só os dígitos. Correção: o behavior do Portal tinha o mesmo nome do behavior do contrib (`cepAutocomplete`) e era sobrescrito; agora é `acultaCepAddress` e reivindica os campos pelo mesmo `once` do contrib. O campo Número fica logo após o Logradouro. Correção de regressão: ao preencher pelo CEP, o Estado dispara o rebuild AJAX do endereço, que apagava Logradouro, Bairro e Cidade. Agora o Estado é preenchido por último, e o preenchimento é reaplicado nos campos vazios do markup novo (`js/cep-address.js`). Vale para Meus Dados e para o checkout.
- Checkout: campo **Número** no endereço de cobrança (`aculta_billing_address`). Como o endereço de cobrança do Commerce não tem campo próprio, o número é gravado no logradouro (`Logradouro, Número`) e preenchido de volta ao voltar à etapa. Verificado: campo renderizado em `order_information`; envio do passo ainda não testado de ponta a ponta.
- Popup de aceite: Esc (fechamento sem ativação do usuário) reabre o popup no evento close, enquanto não houver aceite; Tab e Shift+Tab ficam presos dentro do popup (`js/account-terms-modal.js`). Verificado no navegador pelo Felipe antes desta correção; reteste pendente.
- Tradução: "Company" → "Empresa" (`aculta_ui.pt-br.po`).
Classificação: MINOR (fluxo de aceite substituído: a página /aceite-termos deixa de existir; novo bloqueio de páginas restritas).

- **Aceite dos termos como popup (modal) persistente.** Substitui a página /aceite-termos. Aparece só nas páginas restritas (conta, carrinho, checkout, cursos e áreas de membro, marcadas pela opção de rota `_aculta_terms_required`) para quem ainda não aceitou a versão atual. Páginas públicas não mostram o popup. Não tem botão de fechar; Esc é cancelado; clique fora não fecha. Após aceitar, a mesma página volta liberada.
- **Bloqueio.** O popup persistente é o bloqueio das páginas restritas: a página carrega na mesma URL com o popup por cima. As ações protegidas (checkout, cursos, conta) seguem negadas pelo servidor (`ProtectedRouteAccessCheck`), e a página de acesso negado também mostra o popup.
- **Fonte de verdade.** O aceite é gravado no mesmo registro de `aculta_account_terms` que o cadastro usa (`AccountTermsPolicy`). Quem se cadastra pelo formulário já aceita e não vê o popup. Quem entra por OAuth vê o popup até aceitar. Trocar a versão dos termos é alterar `reset_date` do acordo.
- Papéis do acordo `aculta_account_terms` zerados (`roles: {}`) para o redirecionamento antigo do módulo agreement não ser disparado. A rota `agreement.aculta_account_terms` continua existindo (o módulo a consulta por nome) e responde 404.
- Formulário `/termos/aceitar` (POST com token): usado pelo popup e como retorno sem JavaScript. O destino é validado como caminho local do próprio host.
- Popup só para usuário logado sem aceite, nas páginas que o anônimo não acessa (conta, edição e criação de conteúdo, área de membro), nos nós com comentários (fórum) e na página de acesso negado. Páginas acessíveis ao anônimo (loja, carrinho, verbetes públicos) não mostram o popup; anônimos nunca veem o popup.
- Fundo do popup borrado: `css/account-terms-modal.css` (`::backdrop` com `backdrop-filter: blur(6px)`), biblioteca `aculta_portal/account-terms-modal`.
- Limite conhecido: a página de bloqueio ainda mostra o cabeçalho e o título do site atrás do popup (o renderizador bare mantém o layout do tema). O conteúdo restrito não é entregue. Eliminar o cabeçalho exige página HTML própria, decisão pendente.
- **Mercado Pago (incidente):** o redirecionamento de teste mostrou o domínio `mercadopago.com.br` com a sessão real do responsável logada. O token de teste é de conta de teste (`test_user = true`); `APP_USR` não indica produção. Gateway desligado e religado conforme a necessidade do teste. Ver `docs/portal/CHECKOUT-BILLING.md`.

**RUNTIME STATUS:** verificado no servidor de testes por HTTP: página de aceite antiga 404; `/termos/aceitar` 403 para anônimo; páginas restritas sem aceite (carrinho, dados, conta) respondem 200 com o popup; checkout sem aceite 403 com popup; loja pública sem popup. Não verificado: comportamento do popup no navegador (foco, Esc, clique fora) e aceite pelo formulário com JavaScript.
## 0.3.0-dev.15 — Passo de endereço de cobrança no checkout (CEP); traduções; nome do produto no carrinho — 2026-10-11

Classificação: MINOR (novo pane de checkout; a etapa de dados do pedido passa a pedir endereço de cobrança).

- Novo pane `aculta_billing_address` (`BillingAddressPane`) no passo `order_information` do fluxo `default`, no lugar de `billing_information` (removido pelo Commerce Payment). Usa o formulário inline de perfil de cobrança do Commerce, só com endereço, e o autocomplete por CEP `cep_autocomplete/viacep` (mesmo de "Meus Dados"). Grava o perfil como cobrança do pedido, que o Mercado Pago exige.
- `collect_billing_information` do Mercado Pago volta para `false` (o pane agora coleta o endereço).
- Traduções: "Payment" → "Pagamento" e "Quantity" → "Quantidade" (`translations/aculta_ui.pt-br.po`).
- Nome do produto na coluna Item do carrinho: display `cart` do bundle `camiseta_tamanho` (`config/sync/core.entity_view_display.commerce_product_variation.camiseta_tamanho.cart.yml`), copiado do display padrão.
- Documentação de testes de navegador: `docs/portal/TESTES-NAVEGADOR.md`.

**RUNTIME STATUS:** parcial. Verificado no servidor de testes: o pedido 5 renderiza o campo de CEP em `order_information`, com a biblioteca `cep_autocomplete/viacep` carregada; traduções importadas. Não verificado: gravação do endereço pelo passo, passagem para o Mercado Pago e nome do produto no carrinho pelo navegador. Pendente de teste com `teste.comum`.

## 0.3.0-dev.14 — Checkout: incidente de endereço de cobrança documentado; destino do aceite sem fixo — 2026-10-11

Classificação: PATCH (configuração e documentação; o passo de endereço com CEP ainda não foi implementado).

- Incidente: pedido 5, etapa de pagamento, "Billing information is required to proceed." Documentado em `docs/portal/CHECKOUT-BILLING.md`, com as três causas: país de cobrança vazio na loja, `billing_information` substituído pelo Commerce Payment e gateway Mercado Pago que exige perfil de cobrança.
- Loja 1: `billing_countries = [BR]` (runtime; o campo não é exportado em `config/sync`).
- Gateway Mercado Pago: `collect_billing_information: true` (config/sync e runtime).
- Acordo `aculta_account_terms`: `settings.destination` vazio. O retorno volta ao destino salvo, e não a um fixo.
- Pendente: passo de endereço de cobrança com autocomplete por CEP (comportamento esperado em `CHECKOUT-BILLING.md`), tradução de "Payment" e "Quantity", e nome do produto na coluna Item do carrinho.

**RUNTIME STATUS:** verificado no servidor de testes: `billing_countries` da loja = [BR]; gateway com flag ativa; acordo sem destino fixo. Não verificado: passo de pagamento com formulário de cobrança (ainda sem campos na etapa `payment`) e pagamento até o Mercado Pago.

## 0.3.0-dev.13 — Caminho interno da visão geral passa a /minha-conta (sem /conta-interna) — 2026-10-11

Classificação: PATCH (caminho técnico interno trocado; nenhum link público alterado).

- `/conta-interna` deixa de existir como caminho da visão geral. A rota `aculta_portal.dashboard` passa a `/minha-conta`, e o front do Domain ACCOUNT (`config/sync/domain/conta_aculta_org/system.site.yml`) aponta para ele. Motivo: o Core reserva `/` para a rota `<front>`, então a rota não pode usar `/` diretamente.
- Requisição direta a `/minha-conta` responde 404 (guarda de `DomainPurposeRequestSubscriber`); a visão geral é servida somente na raiz `/` da Conta.
- Gate `validate-aculta-portal-conta-root.php` passa a proibir o caminho em código, configuração e scripts (correspondência exata do caminho).
- Sonda `/conta-interna` removida do `deploy.json` do aculta_deployer.
- Observado e não alterado: para visitante logado, qualquer caminho inexistente da Conta (inclusive `/conta-interna`) responde 302 para `/`; visitante anônimo recebe 404. Regra existente de `DomainPurposeRequestSubscriber`; decisão pendente.

**RUNTIME STATUS:** verificado no servidor de testes: `conta.../` 200 (logado); `conta.../minha-conta` 404 direto; `conta.../` 302 para `/entrar` (anônimo); `aculta.../` 200; gates de conta, Portal e root policy PASS.

## 0.3.0-dev.12 — Retorno após aceitar os termos da conta vai para a raiz da conta — 2026-10-11

Classificação: PATCH (destino de configuração corrigido; nenhum caminho público alterado).

- O acordo `aculta_account_terms` redirecionava para `/minha-conta`, um caminho antigo que responde 404. A raiz da conta é `/`. Destino configurado para `/` em `config/sync/agreement.agreement.aculta_account_terms.yml` e no runtime.
- Não é problema de DNS: `conta.aculta.toca.net.br` resolve (8.8.8.8, 1.1.1.1 e local) para o mesmo IP de `aculta.` e `loja.`.
- `/conta-interna` continua respondendo 404 fora da raiz, por política do Portal (`DomainPurposeRequestSubscriber`); não é destino de retorno.

**RUNTIME STATUS:** verificado no servidor de testes com envio real do formulário `aculta_account_terms` como usuário comum de teste: 302 para `https://conta.aculta.toca.net.br/`; logado, `/` 200 e `/dados` 200. Estado de aceite do usuário de teste restaurado após o teste.

## 0.3.0-dev.11 — Checkout e cursos: e-mail confirmado somado à regra do dono (correção de segurança) — 2026-10-11

Classificação: PATCH (correção de controle de acesso; nenhum caminho alterado).

- Correção: a política de e-mail confirmado substituía o `_custom_access` de `commerce_checkout.form`, `lms.course.start` e `lms.group.answer_form`. Com isso, a regra do Commerce (dono do pedido, loja publicada, itens, permissão "access checkout") e a do LMS (`group.take`) deixavam de valer. Usuário com e-mail confirmado conseguia abrir o checkout de outro pedido ou o curso sem matrícula.
- `aculta_portal.access.protected_route` (`ProtectedRouteAccessCheck`) combina a regra original com o e-mail confirmado, usando `andIf()`.
- `/checkout` (`commerce_checkout.checkout`) mantém `_permission` e recebe só a checagem de e-mail.
- Sintoma observado em teste: admin logado recebe "Acesso negado" em `/checkout/4` porque o e-mail da conta não está confirmado.

**RUNTIME STATUS:** verificado no servidor de testes por API (`checkout()` com o dono do pedido 4 → forbidden, motivo "E-mail não confirmado."; visitante anônimo → forbidden). Não verificado: usuário não-dono com e-mail confirmado (não há outro usuário no banco de testes) e cursos (`group.take`).

## 0.3.0-dev.10 — Correções da validação visual: cadastro de pagamento e rótulos de endereço — 2026-10-11

Classificação: PATCH (correção de apresentação e de acesso; sem caminho novo).

- "Adicionar método de pagamento" some quando nenhum gateway ativo guarda métodos (`aculta_portal.access.payment_method_add`, rota `/pagamentos/novo`). Hoje só o Checkout Pro está ativo, sem cartão salvo.
- Rótulos de endereço (módulo address, contexto "Address label") em português: `translations/address.pt-br.po`. Importar com `drush locale:import --type=customized --override=none pt-br <arquivo>`.

**RUNTIME STATUS:** verificado no servidor de testes (runtime `aculta_runtime`, por API, sem navegador): `checkNamedRoute` de `entity.commerce_payment_method.add_form` e a ação local da coleção `/pagamentos` retornam `forbidden` para o administrador, sem gateway com cartão ativo; `locale:import` adicionou 38 traduções; `t()` com contexto "Address label" em pt-br devolve "CEP" e "Logradouro, linha 2". Não verificado: captura visual de `/pagamentos` e `/dados/endereco` com sessão de navegador.

## 0.3.0-dev.9 — Endereços somente em Meus Dados (CEP) — 2026-10-11

Classificação: MINOR (tela de endereços do Commerce substituída por redirecionamento).

- Lista e cadastro de endereços do Commerce (`/enderecos*`) redirecionam (302) para `/dados/endereco` (Meus Dados, com busca por CEP). Decisão do responsável.
- `PortalController::addressRedirect`; `AccountListPathProcessor` sem `/enderecos`.
- Caminho com ID `/user/{uid}/address-book*` responde 404.

**RUNTIME STATUS:** verificado no servidor de testes com sessão de administrador: `/enderecos` 302 para `/dados/endereco`; `/dados/endereco` 200; anônimo 403; host principal 404.

## 0.3.0-dev.8 — Endereços, pagamentos e pedidos da conta sem ID na URL — 2026-10-11

Classificação: MINOR (caminhos da conta alterados; rotas antigas com ID respondem 404).

- `/user/{uid}/address-book*`, `/user/{uid}/payment-methods*` e `/user/{uid}/orders/{pedido}` passam a `/enderecos*`, `/pagamentos*` e `/pedidos/{pedido}`.
- Controllers do Portal entregam ao Commerce o usuário da sessão; verificações que pediam o usuário da URL usam login e a verificação da entidade.
- `AccountListPathProcessor` remove o ID de todas as URLs geradas dessas rotas.
- Pendente: permissões de cliente para endereço (decisão do responsável) e teste de dono com conta de cliente.

**RUNTIME STATUS:** verificado no servidor de testes com sessão de administrador: listas e cadastros 200; antigos 404; itens inexistentes 404; `/pagamentos/novo` 403 pelo Commerce quando não há tipo de pagamento que aceite cadastro.

## 0.3.0-dev.7 — Pedidos e pagamentos pelas rotas do Commerce no painel — 2026-10-11

Classificação: MINOR (caminhos públicos das listas de pedidos e pagamentos alterados).

- `DomainRouteSubscriber`: `view.commerce_user_orders.order_page` → `/pedidos` e `entity.commerce_payment_method.collection` → `/pagamentos`, sem o ID do usuário; controllers do `PortalController`; acesso por login.
- `AccountListPathProcessor`: remove o ID da URL gerada (`/pedidos`, `/pagamentos`).
- Menu do painel usa as rotas do Commerce; rotas próprias duplicadas removidas.
- `/user/{uid}/orders` e `/user/{uid}/payment-methods` respondem 404.
- Pendente: ações (ver pedido, editar, excluir, padrão) e endereços ainda em `/user/{uid}/...`.

**RUNTIME STATUS:** verificado no servidor de testes: URLs geradas limpas; anônimo 403; antigos 404; render para conta logada sem erro.

## 0.3.0-dev.6 — Painel da conta: Meus pedidos, Meus pagamentos e visão geral em `/` — 2026-10-11

Classificação: MINOR (duas seções novas no painel da conta; link da visão geral corrigido).

- Novas seções: `/pedidos` (Meus pedidos) e `/pagamentos` (Meus pagamentos), só para usuário logado; o usuário vem da sessão, nunca da URL.
- Pedidos: lista das compras da conta (rascunhos de carrinho ficam fora); pagamentos: métodos salvos da conta, somente leitura.
- Visão geral: o item do menu usa a raiz do domínio de Conta (`/`), e não o caminho técnico `/conta-interna`, que segue 404.
- Pendente: os endereços do Commerce `/user/{user}/orders` e `/user/{user}/payment-methods` ainda respondem para o próprio dono; bloqueio depende de decisão.

**RUNTIME STATUS:** verificado no servidor de testes: `/pedidos` e `/pagamentos` renderizam para conta logada (checagem em sessão); anônimo recebe 403.

## 0.3.0-dev.5 — Doação em `/apoiar` — 2026-10-11

Classificação: MINOR (caminho público das rotas de doação alterado; nomes de rota e pagamento inalterados).

- `DonationFriendlyRouteSubscriber`: `/donate` → `/apoiar`; `/donate/{valor}/now` → `/apoiar/{valor}/agora`; `/donate/{pedido}/{etapa}` → `/apoiar/{pedido}/{etapa}`.
- `/donate` passa a responder 404, sem redirecionamento.
- Gate de segurança e política de pagamento atualizados.
- Gate de subscribers: 11.

## 0.3.0-dev.4 — Carrinho em `/carrinho` — 2026-10-11

Classificação: MINOR (caminho público de rota alterado; nome da rota e checkout/pagamento inalterados).

- `CartFriendlyRouteSubscriber`: `commerce_cart.page` passa de `/cart` para `/carrinho` (slug em português, `docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`).
- `/cart` passa a responder 404; sem redirecionamento, conforme a regra de slugs estáveis.
- Checkout e pagamento do Commerce não mudam de caminho.
- Textos do tema (stories do cart-offcanvas) e `PAYMENT-DOMAIN-POLICY.md` atualizados.

**RUNTIME STATUS:** verificar `/carrinho` 200 e `/cart` 404 no servidor de testes.

## 0.3.0-dev.3 — Busca: ciclo de Tab e nome do botão fechar — 2026-10-11

Classificação: PATCH.

- Ciclo de Tab/Shift+Tab no popup: interceptação na fase de captura, com o diálogo procurado a cada tecla (antes o handler não era registrado, porque o diálogo ainda não existia quando o campo era ligado).
- Botão × do diálogo recebe `aria-label` e `title` "Fechar" pelo evento `dialog:aftercreate`.
- Foco inicial no campo verificado depois da abertura do diálogo.

**RUNTIME STATUS:** reteste no navegador pendente.

## 0.3.0-dev.2 — Busca: acessibilidade, índice sem caixa e página com o termo — 2026-10-11

Classificação: PATCH (correções de acessibilidade, prévia e índice; sem mudança de contrato público).

- `search-preview.js`: prévia só consulta a partir do mínimo de caracteres (`data-min-chars`); termo curto restaura a dica sem requisição; requisição anterior é cancelada (AbortController); setas ↑/↓ entre resultados; Tab cicla no popup.
- `SearchPopupForm`: campo com `data-min-chars`.
- `SearchUtility`: diálogo com `maxHeight` 560, posição no topo (sem `of`: o padrão do jQuery UI é a janela) e botão de fechar com rótulo "Fechar".
- Foco inicial do popup vai ao campo de busca.
- `SearchPageForm` (novo): `/busca` renderiza o campo com o nome `q` e o termo da URL (antes o campo saía vazio); com a página sem termo, aparece a dica para digitar.
- Índice `aculta_conteudo`: processador `ignorecase` ativo e reindexado (20 itens). "riddim" e "Riddim" retornam o mesmo resultado.
- Regra de host da revista: `revista420.aculta.org` (produção), decisão do responsável (ver deployer 0.1.x e `config/sync`).

**RUNTIME STATUS:** validado no servidor de testes: prévia, popup e índice verificados por consulta HTTP; teclado e ciclo de Tab aguardam nova verificação no navegador.

## 0.3.0-dev.1 — Status com indexação, rótulo e barreira de textos únicos — 2026-10-11

Classificação: MINOR (nova seção de indexação no Status; sem mudança de contrato público).

- Tela "Status dos módulos e temas" renomeada para "Status" (menu e card).
- Seção "Indexação" no Status: ambiente declarado, regra esperada (produção indexável; teste com noindex, nofollow, noarchive) e último resultado publicado pelo deployer em `var/deployer/status.json` (`indexing`). O Portal não executa a verificação.
- Nota do Status corrigida: até a versão 1.0, o gateway Mercado Pago é habilitado com chaves de teste.
- Gate `scripts/validate-unique-paths-texts.php`: caminho de rota duplicado, título de menu duplicado e texto aposentado.
- Deployer 0.1.19: `robots` grava o resultado em `var/deployer/indexing.json` e o `status` inclui o resumo.

**RUNTIME STATUS:** a seção de indexação depende do `aculta-deployer robots` ser executado no ambiente; sem o resultado, o Status mostra "nenhuma verificação registrada".

## 0.2.0 — Fechamento da linha 0.2 — 2026-10-11

Release da linha 0.2 do Portal (sem mudança de código além da versão). Consolida as entregas 0.2.0-dev.1 a 0.2.0-dev.59:

- Fórum como purpose próprio (`forum`), com host no deployer e registros de domínio.
- Revista, loja e raízes de subdomínio pelo mesmo mapa de rotas.
- Migração do envio de e-mail para Mailer Plus (`symfony_mailer`), com SMTP2GO via Key; remoção do `drupal/smtp` e do `smtp.settings`; DT-P09 resolvida.
- Gates de configuração e comércio alinhados ao estado de ambiente (linha de base DT-O10 e credencial DT-O11).

Pendências que seguem para a próxima linha: DT-O10 (catálogo da loja, ativação no RC) e DT-O11 (rotação do token de teste do Mercado Pago, pelo responsável). A entrega em produção é decisão do responsável no fim do projeto.

RUNTIME STATUS: validado no runtime de teste (Homelab).

## 0.2.0-dev.59 — Remoção do legado drupal/smtp (M2 da migração do e-mail) — 2026-10-11

Classificação: MINOR (remove dependência e configuração legadas do envio; sem mudança de contrato público).

- `drupal/smtp` (e `phpmailer/phpmailer`, dependência dele) removido do `composer.json` e do `composer.lock`. Único resto: `require-dev` do próprio `drupal/webform`, sem instalação.
- Runtime: módulo `smtp` desinstalado; overrides `smtp_password` e `smtp_username` removidos. `system.mail:interface.default` = `symfony_mailer`.
- `config/sync`: removidos `smtp.settings.yml` e os dois `key.config_override.smtp_*`; `core.extension` sem `smtp`; `system.mail.yml` exportado do runtime (`default: symfony_mailer`).
- Deployer (`deploy.json`): removidas as entradas e as regras de `smtp.settings` e de `system.mail` por ambiente; o e-mail deixa de ser dependente de ambiente.
- Scripts de validação, sincronização e manifesto usam `mailer_transport.*` e `key.config_override.mailer_smtp2go_*` no lugar de `smtp.settings`.
- Card de requisitos: "SMTP Authentication Support" substituído por "Mailer Plus".
- Gate `validate-mail-transport.php` impede o retorno do `drupal/smtp` e do `smtp.settings`.
- `aculta_portal_update_11004` mantido sem alteração: hook de atualização histórico.
- Ver `docs/portal/EMAIL-TRANSPORT.md`, seção 9.7.

**RUNTIME STATUS:** validado no runtime de desenvolvimento (Homelab) com envio do Core e Webform; a entrega em produção depende do RC.

## 0.2.0-dev.58 — Migração do e-mail para Mailer Plus (M1) — 2026-10-11

Classificação: MINOR (nova dependência de transporte; prontidão de e-mail muda de fonte; sem mudança de contrato público).

- `drupal/symfony_mailer` 2.0.2 adicionado ao `composer.json` (pacote `mailer_transport`, `mailer_override` e `mailer_policy` incluídos). O `drupal/smtp` continua instalado e inativo até a M2.
- `PortalController::transactionalMailReady()`: passa a checar o módulo `mailer_override`, o transporte padrão `smtp2go` e as Keys `smtp2go_username` e `smtp2go_password`. Deixa de depender de `smtp.settings` e de `SMTPMailSystem`.
- Senha e usuário do transporte ficam fora da configuração exportada; a resolução usa o override nativo do módulo Key (sem código próprio).
- Gate `scripts/validate-mail-transport.php`: dependência, prontidão sem o legado e configuração do transporte sem senha.
- Documento de decisão e plano: `docs/portal/EMAIL-TRANSPORT.md`.

**RUNTIME STATUS: VALIDADO no Homelab (2026-10-11).** Módulos ativos no runtime; transporte `smtp2go` e os dois `key.config_override` exportados para `config/sync`; `transactionalMailReady()` verdadeiro; e-mail do Core e teste do Webform com HTML recebidos e confirmados pelo responsável. DT-P09 resolvida. Ver `docs/portal/EMAIL-TRANSPORT.md`, seção 9.

- Correção encontrada no teste de runtime: constante `MAIL_TRANSPORT` estava sem declaração em `PortalController` (corrigida).
- Pendências para a M2 (remoção do legado): `drupal/smtp` ainda instalado; `config/sync/system.mail.yml` ainda declara `php_mail` (runtime usa `symfony_mailer`); `smtp.settings` ainda em `config/sync`.

## 0.2.0-dev.57 — Fórum (purpose `forum`, módulos forum e flag) — 2026-10-11

Classificação: MINOR (novo purpose de domínio e novos módulos Drupal; sem mudança de contrato público existente).

- Módulos instalados e registrados em `config/sync/core.extension.yml`: `drupal/forum` 1.1.3 (D11+) e `drupal/flag` 5.1.1, com `history` como dependência do fórum. Adicionados em `composer.json`/`composer.lock`.
- Purpose `forum` (domínio `forum_aculta_org`, hostname `forum.aculta.org` em produção e `forum.aculta.toca.net.br` no servidor de testes) em `DomainPurposeManager`, `DomainPresentationBuilder` (título "Fórum") e `DomainPurposeCondition`.
- `DomainRouteSubscriber`: rotas `forum.*` e `entity.taxonomy_term.forum_*` pertencem ao purpose `forum`.
- `ContentPurposeResolver`: nós do tipo `forum` pertencem ao purpose `forum`.
- Raiz do host do fórum: `PurposeFrontPathProcessor::FRONT_PATHS` com `forum` → `/forum`. Sem redirecionamento; o gate `validate-root-policy.php` exige a entrada.
- Flags `seguir_topico` e `salvar_topico` (entidade nó, bundle `forum`), criadas no Runtime de teste; permissões para o papel autenticado exportadas.
- Tradução `translations/forum.pt-br.po` (rótulos do módulo forum em português).
- Limite: denúncia de comentários exige um tipo de flag para `comment`, ainda não disponível; tópicos de teste com conteúdo real não foram criados nesta entrega.
- Próximas etapas: revisão visual do fórum no tema (fora desta entrega, sem alterar `aculta420`) e denúncia de comentários.

## 0.2.0-dev.56 — Raiz de todos os subdomínios pelo mesmo mapa — 2026-10-11

- `FRONT_PATHS` agora cobre apoio (`/apoio`), wiki (`/wiki`), cursos (`/cursos`) e loja (`/loja-da-aculta`). Verificado: a raiz da wiki e a dos cursos têm texto visível igual ao das rotas correspondentes.
- Atributo `_aculta_purpose_front`: marca a raiz reescrita; a regra que retornava 404 em `/apoio` aceita essa marca.
- Revista e principal: `<front>`, sem reescrita (documentado).
- Gate `validate-root-policy.php` exige o mapa completo.

## 0.2.0-dev.55 — Raiz da loja serve conteúdo (processador de entrada) — 2026-10-11

- `PathProcessor\PurposeFrontPathProcessor` (tag `path_processor_inbound`, prioridade 300): mapa único `FRONT_PATHS` com a página inicial de cada purpose. Hoje: SHOP → `/loja-da-aculta` (nó 69). Define `_disable_route_normalizer` para o alias interno não virar 301.
- Verificado no servidor de testes: `loja.aculta.toca.net.br/` → 200 com a página "Loja da ACULTA", sem redirecionamento; as demais raízes e a exceção da Conta (302 para `/entrar`) inalteradas.
- Substitui o 302 da loja (0.2.0-dev.53), que foi removido.

## 0.2.0-dev.54 — Página inicial da loja e raiz do host SHOP — 2026-10-11

- Página "Loja da ACULTA" (nó 69, alias `/loja-da-aculta`), atribuída ao domínio da loja, com texto de modelo para lojas sem fins lucrativos. Frete, prazo e trocas ficam como pendentes do responsável.
- Decisão de raiz: a raiz de cada host serve conteúdo, sem redirecionar. Exceção única: visitante na Conta vai ao login. Um redirecionamento da raiz da loja foi tentado e removido, para seguir a decisão.
- Pendência: a raiz de `loja.aculta.toca.net.br` ainda responde 404 (o nó 69 existe, mas nenhum mecanismo de raiz o serve).

## 0.2.0-dev.53 — Raiz da Conta redireciona visitante ao login — 2026-10-11

Classificação: PATCH (regra de redirecionamento do host de conta).

- `DomainPurposeRequestSubscriber::onRequestBeforeRouter`: visitante não logado na raiz do host de conta vai (302) ao login do próprio host (`/entrar`). Feito antes do roteamento com acesso, que negava a rota (403).
- Verificado no servidor de testes: `conta.aculta.toca.net.br/` → 302 para `/entrar` (200); `/conta-interna` → 404.
- `docs/portal/REDIRECTS.md` atualizado: a raiz da Conta e a regra de página inicial própria por subdomínio.

## 0.2.0-dev.52 — Carrinho na barra multidomínio (presenter neutro) e cache do carrinho — 2026-10-11

Classificação: MINOR (novo serviço de apresentação do carrinho; sem mudança de contrato público).

- `Cart\CartUtility` (serviço `aculta_portal.cart_utility`): entrega ao tema contagem, itens, total e a URL do carrinho no MAIN (via `DomainPurposeManager`), sem storage paralelo. Cache: contextos `cart`, `user`, `session` e tags das orders, acumulados na página.
- `PortalHooks::preprocessPage`: `aculta_cart` disponível para o tema.
- `commerce_donation_flow.settings:donation_route` alterado de `donate` para `both`: o modo `donate` desligava as rotas de carrinho e de checkout da loja (403 para visitante). Doações continuam pelo próprio fluxo.
- Pendente: verificar o estado com itens no carrinho e o fluxo de compra ponta a ponta (depende do Mercado Pago).

## 0.2.0-dev.51 — Página do produto da loja no host SHOP — 2026-10-11

Classificação: PATCH (política de rota; sem mudança de contrato público).

- `DomainRouteSubscriber`: a rota `entity.commerce_product.canonical` pertence ao purpose `shop`. Em `loja.aculta.toca.net.br` responde 200; no host principal, 404 (wrong-purpose público, conforme a política). Carrinho e checkout continuam no MAIN.
- O link canônico do produto passa a apontar para o host SHOP (verificado: `https://loja.aculta.toca.net.br/loja/produto/...`).
- Deployer 0.1.16: loja de teste na lista de robots e bloco `domain_transition.shop`.
- Pendente: confirmar no navegador o fluxo adicionar ao carrinho (SHOP) → carrinho (MAIN). O carrinho no host principal respondeu 403 para visitante anônimo; a causa ainda não foi investigada.

## 0.2.0-dev.50 — Galeria do produto em VVJB com mídias — 2026-10-11

Classificação: MINOR da linha 0.2 (novo campo de mídia do produto e nova view; sem alteração de contrato público).

- Fotos do produto viram mídias de imagem (`media.type.image`), ligadas ao produto pelo campo `field_galeria` (referência a mídia, múltiplo). O campo `field_imagens` (arquivos) fica como histórico.
- `views.view.galeria_produto` (config/sync): view VVJB (`views_vvjb`) sobre `media_field_data`, com argumento de IDs separados por `+`, linhas com o modo de exibição padrão da mídia.
- `Hook\ProductGalleryHooks` (`preprocess_commerce_product`): prepara a renderização da view em `aculta_product_gallery` para o tema. O tema só imprime.
- Tema: template do produto usa `aculta_product_gallery`; componente `product-gallery` (Bootstrap) removido.
- Verificado no servidor de testes (HTML): página 200; galeria VVJB com 7 imagens; sem carrossel Bootstrap.
- Resolução: modo de exibição `media.produto_galeria` com estilo `produto_galeria` de 960×1200 px (o dobro da largura da coluna da galeria), servido e verificado em 960×1200.
- Pendente: exportar a configuração do Runtime antes do RC (campo, view e displays já estão em config/sync).
- Número de versão: 0.2.0-dev.49, porque a 0.2.0-dev.48 está na PR #192 (CAPTCHA da loja) e na PR #190 (domínios).

## 0.2.0-dev.49 — Loja sem CAPTCHA: barreira de carrinho, checkout e página de produto — 2026-10-11

Classificação: PATCH de política de segurança do CAPTCHA (decisão do responsável; altera o formulário de compra).

- Pontos de CAPTCHA `commerce_order_item_add_to_cart_form` e `commerce_checkout_flow_default` desativados em `config/sync`. O formulário de compra (adicionar ao carrinho) e o checkout padrão da loja não recebem mais o desafio. O checkout de doações continua protegido.
- `Captcha\TurnstileToggle::SHOP_FORM_IDS`: os formulários da loja não são alterados quando o responsável liga ou desliga o Turnstile no site.
- `Hook\ShopCaptchaHooks` (`page_attachments_alter`): remove o script do Turnstile (challenges.cloudflare.com) das rotas de produto, carrinho e checkout. O módulo contrib `turnstile` continua fora da alteração.
- `scripts/validate-shop-captcha.php`: gate estático (15 checagens) que reprova se a barreira for removida.
- Risco aceito: sem CAPTCHA, o carrinho fica sem proteção contra automação. Pendência: limitar tentativas por IP se houver abuso.
- Verificado no servidor de testes (HTML): página do produto sem `cf-turnstile`, sem `challenges.cloudflare` e sem a frase "Verificação de segurança"; formulário de compra presente. Home mantém o script (esperado).
- Número de versão: a 0.2.0-dev.48 também está na PR #190 (domínios). Se a #190 for mesclada antes, esta vira 0.2.0-dev.49.
— Deployer 0.1.15: tabela de migração de domínios (teste → produção) — 2026-10-11

Classificação: PATCH (ferramenta de deploy e documentação; sem código de runtime do Portal).

- `aculta_deployer` 0.1.15: `domain_transition` em `config/deploy.json` e o comando `domains` (somente leitura), que confere a tabela contra `config/sync/domain.record.*.yml`.
- Guia `aculta_deployer/docs/MIGRACAO-DOMINIOS.md` e fase 11 no `PLANEJAMENTO.md`.
- Pendente de decisão do responsável: host de produção da revista (`revista420.aculta.org` ou `revista420.aculta.org`). Produção só muda no deploy do RC.

## 0.2.0-dev.48 — Deployer 0.1.15: tabela de migração de domínios (teste → produção) — 2026-10-11

Classificação: PATCH (ferramenta de deploy e documentação; sem código de runtime do Portal).

- `aculta_deployer` 0.1.15: `domain_transition` em `config/deploy.json` e o comando `domains` (somente leitura), que confere a tabela contra `config/sync/domain.record.*.yml`.
- Guia `aculta_deployer/docs/MIGRACAO-DOMINIOS.md` e fase 11 no `PLANEJAMENTO.md`.
- Pendente de decisão do responsável: host de produção da revista (`revista420.aculta.org` ou `revista420.aculta.org`). Produção só muda no deploy do RC.

## 0.2.0-dev.47 — Host de teste da revista: coletivo420 → revista420 (servidor de testes) — 2026-10-10

Classificação: PATCH (configuração de deploy e documentação; sem código de runtime).

- `aculta_deployer/config/deploy.json`: host de teste da revista em `revista420.aculta.toca.net.br` (lista de hosts do sitemap/robots e substituição de `hostname` no domínio). Produção (`revista420.aculta.org`) não muda nesta versão.
- `config/sync/domain_alias.alias.revista420_aculta_toca_net_br.yml`: padrão do alias de teste passa a `revista420.aculta.toca.net.br`. O `id` e o nome do arquivo permanecem, para não alterar a identidade da configuração importada.
- Documentação: `docs/operations/TESTING.md`, `docs/architecture/multidomain.md`, `docs/portal/ARCHITECTURE.md` (coluna do servidor de testes).
- Pendências fora do repositório (DEFERRED): (1) `trusted_host_patterns` em `settings.local.php` do servidor de testes; (2) `config import` do alias no Runtime e limpeza de cache; (3) DNS e VirtualHost/certificado no Cloudflare e no Virtualmin; (4) mudança equivalente em produção (`*.aculta.org`), registrada para deploy só no RC.

## 0.2.0-dev.46 — Botão da pesquisa completa sem cores fixas (apresentação movida para o tema) — 2026-10-10

Classificação: PATCH (remoção de CSS; sem alteração de markup, rota, access ou cache).

- Removido `css/search-preview.css` e a entrada `css` da biblioteca `aculta_portal/search-preview`. A biblioteca mantém o JavaScript.
- A apresentação do botão "pesquisa completa" passa para o tema (`aculta420/css/components/search.css`, 0.6.0-dev.5), com tokens semânticos no lugar do azul fixo `#0b5394`. Visualmente, o azul vira o texto primário do tema no modo claro.
- Verificação: gate `validate-aculta-portal-drupal11.php` (ver resultado no PR). RUNTIME STATUS: DEFERRED para a verificação do popup no servidor de testes.

## 0.2.0-dev.45 — Pesquisa completa em botão centralizado; lista da prévia sem marcadores — 2026-10-10

Classificação: ajuste visual (PATCH).

- «Pesquisa completa por «termo» (N resultados)» vira botão centralizado, menor e com contorno, diferente do botão Buscar (preenchido). O tamanho menor exige CSS do Portal: a regra geral `.btn` do tema sobrepõe `btn-sm`.
- A lista da prévia não mostra marcadores (`list-unstyled`).

## 0.2.0-dev.44 — Prévia da busca sem callback AJAX no Form API; atraso de 300 ms — 2026-10-10

Classificação: correção (PATCH).

- Corrigido o erro "Oops, something went wrong" no popup: o callback AJAX do formulário (0.2.0-dev.43) rodava com o objeto restaurado do cache sem as dependências injetadas (`Typed property ... must not be accessed before initialization`), e respondia 500. A prévia saiu do Form API.
- Nova rota `/busca/previa` (`SearchPreviewController`): devolve o HTML da prévia (até 8 resultados, rodapé com a pesquisa completa e o total). Sem sessão de formulário; resposta `no-store`.
- O script `search-preview.js` espera 300 ms sem digitação, busca a prévia no mesmo host e mostra só a resposta mais recente; em falha, mostra uma mensagem na caixa.
- Verificado no Runtime de teste como visitante anônimo (MAIN e apoio): termos «Not&cia», «a» e «Projetos» com respostas 200, prévias corretas e sem erro de JavaScript.

## 0.2.0-dev.43 — Prévia de resultados no popup de busca e pesquisa completa — 2026-10-10

Classificação: funcionalidade (PATCH).

- Prévia por AJAX enquanto a pessoa digita (a partir de 3 caracteres), com até 8 resultados: título e tipo, cada um com link para o host do próprio conteúdo. A consulta roda no host atual, porque o índice é do site inteiro.
- Rodapé como na Wikipedia (última linha da lista de sugestões): «Pesquisa completa por «termo» (N resultados)», que leva à busca central no MAIN com o mesmo termo. O total vem do índice, já filtrado por acesso.
- `SearchResults::search()` devolve total e itens; o link por propósito fica no mesmo serviço.
- Verificado no Runtime de teste (MAIN e apoio): prévia com dois itens para «Notícias» e rodapé «Pesquisa completa por «Notícias» (2 resultados)» apontando para `aculta.toca.net.br/busca?q=`.

## 0.2.0-dev.42 — Ícone de busca na barra multidomínio (popup AJAX) — 2026-10-10

Classificação: funcionalidade pequena no shell (PATCH).

- Ícone de busca na barra institucional, em todos os hosts. Abre um popup por AJAX (modal do Core), no host atual.
- Formulário `SearchPopupForm` (GET) em `/busca/popup`. No MAIN, o campo tem sugestões (Core autocomplete sobre a busca do índice). Fora do MAIN, o campo envia direto para `/busca` do MAIN: sugestões são AJAX de mesma origem e não cruzam domínio.
- Verificado no Runtime de teste: popup abre em MAIN e em apoio; envio leva para `/busca?q=`; ícone centralizado na barra.
- Correção: o campo de busca da Wiki saía sem `name`, então a busca da Wiki não recebia o termo. Corrigido com `#name => 'q'`; o motor próprio da Wiki segue como está.
- Contrib: não foi necessário. Core cobre o modal e o autocomplete. Alternativa avaliada: `search_api_autocomplete` (estável para Drupal 11), que daria sugestões mais ricas; não adotado por enquanto.

## 0.2.0-dev.41 — Busca centralizada no MAIN, resultados no host do conteúdo — 2026-10-10

Classificação: correção de comportamento de busca (PATCH).

- A busca geral segue centralizada no MAIN (`/busca`). Cada resultado passa a abrir no host do próprio conteúdo, pelo purpose do nó (`field_domain_source`). Antes, o link era o caminho relativo do host onde a busca rodava.
- A Wiki420 mantém o próprio motor (`/wiki/busca`, `WikiController::search`); os verbetes `wiki_entry` não entram no índice geral.
- Política em `docs/portal/SEARCH.md`.
- Verificado no Runtime de teste: resultado de "Notícias" abre em `revista420.aculta.toca.net.br/noticias`.

## 0.2.0-dev.40 — F3: busca com content_access e testes de Kernel — 2026-10-10

Classificação: correção e cobertura de teste (PATCH).

- Índice `aculta_conteudo` passa a aplicar o processador `content_access`. O filtro de acesso sai do controller e passa para a própria consulta do índice: visitante só recebe conteúdo que pode ver, e total e paginação batem com a lista exibida. O controller mantém a conferência `node->access('view')` por segurança.
- Teste de Kernel `SearchContentAccessKernelTest`: índice de banco com `content_access` e processadores de texto como na produção; um nó publicado e um rascunho; visitante anônimo (papel `anonymous` com `access content`, como no site) vê só o publicado; total igual à lista. Teste de mutação: sem `content_access`, o rascunho aparece e o teste falha.
- Configuração de teste: os processadores e o tracker seguem o schema padrão; o `status` e o `settings` usados no YAML de produção não têm schema neste `search_api` e só são usados como chave de presença.
- Pendências da F3 fechadas no Portal (ver `docs/operations/DEBT-REGISTER.md`, DT-T13).

## 0.2.0-dev.39 — Guarda da raiz antes do controle de acesso — 2026-10-10

Classificação: correção de ordem na guarda de rota (PATCH).

- Verificação no Runtime (`https://conta.aculta.toca.net.br/conta-interna`) mostrou 403 em vez de 404: a rota da visão geral exige permissão, e o Core nega o acesso durante o casamento da rota, antes da guarda em `onRequest`.
- A guarda passa a `onRequestBeforeRouter`, com a mesma regra: qualquer caminho original diferente de `/` responde 404 para `aculta_portal.dashboard`.
- O gate `validate-aculta-portal-conta-root.php` passa a exigir a guarda nos dois pontos.

## 0.2.0-dev.38 — Raiz pública da conta (sem /conta-interna) — 2026-10-10

Classificação: correção de rota e de links (PATCH).

- A visão geral da conta (`aculta_portal.dashboard`) é servida somente na raiz do Domain ACCOUNT (`/`). Requisição direta a `/conta-interna` responde 404, no mesmo padrão de `/apoio`, pela guarda em `DomainPurposeRequestSubscriber::onRequest`.
- O caminho interno da rota foi mantido apenas como identificador técnico, comentado no routing. Nenhum link o gera: o hook de saída de URL já reescreve a rota para a raiz do Domain.
- Formulário de foto de perfil da visão geral (`PortalHooks`): a condição passa a comparar a rota, não o caminho interno, que o Drupal entrega pela front page.
- Reenvio de confirmação de e-mail: rota `aculta_portal.email_resend` passa a `/confirmar-email/reenviar` (slug público em português). Até a versão estável não há redirecionamento para o caminho antigo (decisão do responsável).
- Documentação: `docs/integrations/AUTHENTICATION.md`, `docs/integrations/GOOGLE.md` e `docs/portal/ACCOUNT-MENU.md` descrevem a raiz como destino público.
- Barreira anti-regressão: `scripts/validate-aculta-portal-conta-root.php` (gate "CONTA ROOT"). Reprova um segundo uso do caminho interno, a remoção da guarda, um link literal ao caminho em código, template, JS, CSS ou configuração exportada (exceto a front page do Domain ACCOUNT), o slug do reenvio fora do padrão e divergência entre `info.yml` e o changelog.
- Não validado no Runtime: a checagem HTTP (`curl` em `https://conta.aculta.toca.net.br/` e em `/conta-interna`) depende do deploy no servidor de testes e é feita depois do merge.

## 0.2.0-dev.37 — Valor da credencial em popup — 2026-10-10

Classificação: mudança visual no painel (PATCH).

- O botão 👁 de Credenciais do ambiente abre um popup com o valor completo, em vez de trocar a máscara na própria linha.
- Rota `aculta_portal.secrets_show` (`/admin/config/aculta/segredos/mostrar/{name}`), permissão `administer aculta secrets`, sem cache, registrada no log com o nome e o usuário. O valor não entra no HTML da página.
- Removidos por obsolescência: a rota JSON de revelação, `SecretsRevealController`, o serviço correspondente, a biblioteca `secrets-reveal` e o script inline.

## 0.2.0-dev.36 — Alinhamento do Mercado Pago com a documentação e o módulo — 2026-10-10

Classificação: alinhamento e limpeza (PATCH).

- Overrides do Mercado Pago: `MercadoPagoEnvironmentOverride` movido para `Commerce/MercadoPago/`, junto do resolvedor `MercadoPagoCredentials`. Único override do módulo para o gateway.
- Formulário do gateway (`FormHooks`): Access Tokens de teste e produção seguem como senha. Stage (não usado), Client ID e Client Secret ficam desabilitados e sem valor, para que nenhuma credencial não usada seja gravada na configuração.
- Configuração exportada do gateway: removidas `public_key_stage`, `access_token_stage`, `client_id` e `client_secret` (não usadas, conforme o README do módulo e a documentação de credenciais).
- `MERCADOPAGO_WEBHOOK_SECRET`: a documentação oficial diz que a assinatura é por aplicação, gerada ao salvar as notificações. Passa a valer em produção e testes (obrigatória só em produção).
- Validação da `x-signature`: confere com o SDK oficial (`mercadopago/dx-php` 3.16.0), manifesto `id:...;request-id:...;ts:...;` com HMAC-SHA256.
- Gate de comércio: lista de segredos do gateway alinhada (sem stage e client_secret); checagem de SMTP lê a configuração bruta (`getRawData()`), para não confundir com o valor vindo de Key.
- Gates: Portal PASS (514); contrato PASS. Testes unitários de Mercado Pago e segredos: 19 OK.
- Não validado: o gate de comércio completo. Ele exige modo seguro de SMTP (desligado), que o ambiente de testes não usa por decisão do projeto. As verificações de Mercado Pago foram conferidas com uma cópia temporária, sem a checagem de SMTP.

## 0.2.0-dev.35 — Apagar valor de credencial pelo painel — 2026-10-10

Classificação: funcionalidade pequena do painel (PATCH, sem API pública nova).

- Cada credencial com valor ganha o ícone 🗑 ao lado do olho e do lápis. Ao clicar, abre uma confirmação (modal) que avisa que a variável sai do arquivo de credenciais deste ambiente.
- `SecretsManager::clear()` remove a linha da variável. A escrita passou a ser um único método privado (`persist`), usado por `save()` e `clear()`, com a mesma escrita atômica e o mesmo log (só o nome).
- Só aparece onde a gravação é possível (teste, arquivo de credenciais) e só quando há valor. Permissão `administer aculta secrets`.
- Validação: ver a seção de validação na PR.
## 0.2.0-dev.34 — Remove Client ID e Client Secret do Mercado Pago — 2026-10-10

Classificação: limpeza de contrato (PATCH).

- Client ID e Client Secret não são usados pela integração Checkout Pro. Verificado no código do módulo `commerce_mercado_pago`: só aparecem no formulário de configuração, que os deixa vazios. Removidos do contrato, do carregador, do gate, do painel, da documentação e das Keys (arquivos e Runtime de teste). Mantidos apenas Public Key e Access Token por ambiente.

## 0.2.0-dev.33 — Mercado Pago por ambiente e credenciais em duas seções — 2026-10-10

Classificação: correção de integração e de painel (PATCH, sem API pública nova).

- Mercado Pago: o override lia `MERCADOPAGO_PUBLIC_KEY` e `MERCADOPAGO_ACCESS_TOKEN`, nomes que não estavam no contrato nem no carregador, e nunca chegavam ao ambiente. Client ID e Client Secret não eram ligados ao gateway. Corrigido.
- Ambiente de testes usa o modo `test` do gateway com `MERCADOPAGO_TEST_PUBLIC_KEY` e `MERCADOPAGO_TEST_ACCESS_TOKEN`. Ambiente de produção usa o modo `live` com `MERCADOPAGO_PRODUCTION_PUBLIC_KEY` e `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN`. Client ID e Client Secret são da aplicação e valem para os dois.
- O ambiente vem do ACULTA Deployer. `MercadoPagoCredentials` é o resolvedor único, usado pelo override e pelo guard de ativação (`entity_presave`), que continua fechado sem credenciais do ambiente atual.
- Contrato (`config/secrets-contract.json`): campo `environments` por variável. Novas variáveis e Keys (`provider env`) de Mercado Pago. Gate do contrato atualizado: aceita as sete Keys de credenciais e as duas de Turnstile (antes falhava por `turnstile_test` fora da lista aprovada).
- Painel "Credenciais do ambiente": duas seções fixas, **Ambiente de Produção** e **Ambiente de Testes**. Variáveis compartilhadas aparecem nas duas. A troca de ambiente segue com o Deployer.
- Senha do comprador de teste guardada na variável `MERCADOPAGO_TEST_BUYER_PASSWORD` (painel, seção de testes). Não entra no repositório.
- Documentação: `docs/integrations/MERCADOPAGO.md` com a matriz de credenciais, o mapeamento para o gateway e os cartões, status e usuário de teste.
- Gates: `validate-aculta-portal-drupal11.php` PASS (510 checagens); `validate-secrets-contract.php` PASS. Testes unitários de Mercado Pago: 14 OK (5 novos em `MercadoPagoCredentialsTest`).
- Validação no Runtime de teste (`aculta.toca.net.br`): sete Keys novas importadas; painel com duas seções (10 linhas em cada); campo de senha ausente na página; `MERCADOPAGO_TEST_BUYER_PASSWORD` gravada pelo mesmo caminho do painel; gateway em modo `test`, desativado, sem credenciais de teste (esperado até o responsável informar as chaves); guard de ativação bloqueia com a mensagem do ambiente.
- Não validado: Checkout Pro ponta a ponta (depende das chaves de teste de Public Key e Access Token); credenciais de produção (provisionamento pelo Deployer).
- Gate `validate-portal-commerce-security.php` não passa neste Runtime: a verificação "SMTP remains disabled in local safe mode" falha porque o SMTP2GO está ligado no ambiente de testes. Pré-existente à mudança; não alterado.

## 0.2.0-dev.32 — Alterar credencial por popup — 2026-10-10

Classificação: mudança de interação no painel administrativo (PATCH, sem API pública nova).

- Em "Credenciais do ambiente", os campos de senha da seção "Cadastrar ou alterar valores" e o botão "Salvar credenciais" saem da página.
- Cada linha ganha um ícone ✎ ao lado do olho. Ao clicar, abre um popup (modal Drupal) com um único campo de senha para a variável escolhida. Salvar grava só essa variável.
- O ✎ só aparece onde a gravação é possível (teste, arquivo de credenciais). Em produção, a página segue mostrando o aviso de armazenamento em banco.
- Organização: `SecretsForm` deu lugar a `SecretsController` (página e popup, como serviço) e `Form/SecretEditForm` (popup, validação de campo obrigatório, AJAX de fechamento e redirecionamento).
- Mantidos: permissão `administer aculta secrets`, valor fora do HTML, log só com nome e usuário, gravação atômica do arquivo.
- Validação no Runtime de teste (`aculta.toca.net.br`), com sessão de administrador:
  - página: 200, sem campo de senha, sete lápis (um por variável do contrato), sem o botão antigo;
  - popup (navegador headless, Chromium/DevTools): o lápis abre "Alterar credencial"; envio vazio mostra o erro de campo obrigatório dentro do popup e não grava;
  - gravação: envio com o valor atual de `GOOGLE_OAUTH_CLIENT_ID` (mesmo valor, sem alterar a credencial) fecha o popup, volta à página com a mensagem de sucesso e registra no log `1 variável(eis) atualizada(s)`;
  - o olho segue revelando o valor depois da gravação.
- Correção durante a validação: o popup precisou da biblioteca `core/drupal.dialog.ajax` na página (sem ela, o lápis navegava para a página de edição).
- Não testado: alteração de uma credencial para valor diferente (a regravação foi com o mesmo valor).

## 0.2.0-dev.31 — Botão de exibir chave em Credenciais do ambiente — 2026-10-10

Classificação: correção (PATCH).

- O botão de olho do painel "Credenciais do ambiente" mostra o valor completo. Antes ele exibia "indisponível". Duas causas, ambas corrigidas:
  1. A rota de revelação apontava para a classe do controller, sem injeção de dependências: o construtor falhava com 500. Agora o controller é serviço (`aculta_portal.secrets_reveal_controller`) e a rota usa `service:method`.
  2. A URL do botão recebia um segundo `?token=`. A rota tem `_csrf_token`, e o gerador de URL do Core já acrescenta o token; a concatenação manual duplicava o parâmetro e a rota negava o acesso (403). Agora o `SecretsForm` usa a URL gerada e não depende mais do serviço `csrf_token`.
- O valor continua fora do HTML; o log registra só o nome da credencial e o usuário. Resposta de revelação sem cache; credencial sem valor responde 404 controlado.
- Validação no Runtime de teste (`aculta.toca.net.br`), com sessão de administrador:
  - página de segredos: 200, uma URL de revelação por credencial, cada uma com um único `token`;
  - rota de revelação: 200, `application/json`, chaves `name` e `value` (conferido por HTTP em todas as credenciais renderizadas);
  - navegador headless (Chromium, DevTools): clique no botão de olho revela o valor (72 caracteres para `GOOGLE_OAUTH_CLIENT_ID`, `aria-pressed=true`, sem "indisponível");
  - não testado: o segundo clique que volta à máscara.
- Gate do Portal: PASS (502 checagens).

## 0.2.0-dev.29 — Sanfona com uma seção aberta por vez — 2026-10-10

- No celular, ao abrir uma seção da sanfona, as demais que estavam abertas recolhem. Implementado no JavaScript da sanfona, valendo para todos os navegadores.

## 0.2.0-dev.28 — Sanfona da Minha conta com o conteúdo completo da seção — 2026-10-10

- No celular, cada item da sanfona mostra o conteúdo completo da seção, carregado por AJAX quando o item é aberto (mesma requisição que o `account-navigation.js` já usa para as seções). Sem links e sem descrição nos painéis.
- A seção atual já vem aberta e carrega sozinha. No celular, a coluna de conteúdo lateral é removida do DOM, para não duplicar IDs.
- Se a carga falhar, o painel mostra um link para abrir a página.
- Desktop segue como lista de links.
- Limite: a verificação autenticada (com login) não foi feita pela automação; validar no celular.

## 0.2.0-dev.27 — Menu da Minha conta no celular em sanfona — 2026-10-10

- No celular (até 767px), cada item do menu da conta é uma sanfona nativa (`details`/`summary`, sem JavaScript): expande e mostra a descrição da seção e o link para a página. A seção atual começa aberta. Sair continua como linha simples.
- No desktop, o menu segue como lista de links.
- Conteúdo do menu duplicado em dois blocos e alternado só por CSS, para que cada versão tenha o próprio markup acessível.

## 0.2.0-dev.26 — Menu da Minha conta no celular: lista com ícones — 2026-10-10

Classificação: apresentação (somente mobile; desktop sem mudança).

- Itens do menu da conta com ícone (Bootstrap Icons), rótulo e seta. No celular (até 767px) viram uma lista vertical com divisórias, como no protótipo. No desktop, ícones e setas ficam ocultos.
- Os itens continuam links para as mesmas páginas; o item da página atual segue marcado. Sem sanfona: a versão com expansão de conteúdo não foi adotada.
- A ordem e as rotas não mudam. O CSS de duas colunas no celular foi substituído.

## 0.2.0-dev.25 — Correção: Turnstile com dois ambientes (teste e produção) — 2026-10-09

- Remove a chave de desenvolvimento do Turnstile (`TURNSTILE_DEV_KEYS_JSON`, Key `turnstile_dev`). O projeto tem só dois ambientes: teste e produção. O ambiente local é do tipo teste.
- Encerrada a pendência de um terceiro ambiente no deployer: não há.

## 0.2.0-dev.24 — Turnstile: chaves separadas por ambiente — 2026-10-09

Classificação: configuração e credenciais (sem mudança de comportamento para o ambiente de produção).

- Duas chaves do Turnstile no contrato de credenciais: `TURNSTILE_KEYS_JSON` (produção, Key `turnstile`) e `TURNSTILE_TEST_KEYS_JSON` (teste, Key `turnstile_test`). Aparecem no painel de credenciais do ambiente. Só há dois ambientes: teste e produção.
- Escolha automática: `TurnstileKeyOverride` aponta `turnstile.settings:keys` para a chave do ambiente que o deployer gravou (`aculta-deployer environment set --to=test|production`). Nada é gravado na configuração; a troca vale na próxima leitura.
- Produção usa sempre a chave de produção. Teste usa a de teste. Não há mistura: a chave de teste não é lida em produção.
- Helper `DeployerEnvironment` único para o ambiente; o gerenciador de segredos passa a usá-lo.
- Testes unitários: 3 OK (ambiente padrão, leitura do deployer, chave por ambiente).

## 0.2.0-dev.23 — Caixa de ativação do Turnstile na administração — 2026-10-09

Classificação: funcionalidade administrativa (política de CAPTCHA do Portal).

- Nova opção em **ACULTA Portal > Turnstile (anti-bot)** (`/admin/config/aculta/portal/turnstile`): ativa ou desativa o desafio Turnstile.
- Fonte da verdade: a própria configuração do CAPTCHA (`captcha.settings`, `enable_globally`, e os pontos com o desafio Turnstile). Não há configuração nova do Portal. Ao mudar, aplica-se o mesmo estado a todos esses pontos.
- Ativar exige a chave `turnstile` configurada no ambiente (Key/env). O formulário mostra só se ela existe, nunca o valor.
- Desativar exige confirmação do risco. Cada mudança é registrada no log do canal `aculta_portal`, com a conta que alterou.
- O segredo não passa pela configuração: continua no Key/env.
- Atenção: a alteração grava configuração no banco. Depois, exportar `config/sync` para o repositório.
- Teste de Kernel `TurnstileToggleKernelTest` (3 testes OK). As 5 deprecações que ele mostra vêm dos contribs `captcha` e `key` (atributos de plugin e de requisitos), não deste código; acompanhar nas próximas versões dos contribs.

## 0.2.0-dev.22 — Cadastro neutro com e-mail já usado (DT-P06) — 2026-10-09

Classificação: mudança de comportamento no cadastro (decisão do responsável): a resposta não revela se o e-mail já tem conta.

- Mensagem única nos dois casos: "Se este e-mail ainda não estiver cadastrado, enviaremos um link para confirmar o seu cadastro. Se ele já estiver cadastrado, enviaremos um aviso com as opções para acessar a sua conta. Verifique a caixa de entrada e a pasta de spam." Mesmo destino (página inicial).
- Com e-mail já cadastrado, nenhuma conta é criada e ninguém é logado. O dono da conta recebe um e-mail com o link para entrar e para recuperar a senha, e a orientação de que não é preciso fazer nada se não foi ele.
- Implementação: o erro "already taken" do Core sai do formulário; o botão usa `saveRegistration` no lugar de `::save`; a política `RegistrationMailPolicy` concentra a busca e o aviso; o hook `mail` do módulo traz o texto do aviso.
- Cadastro com e-mail novo continua igual, exceto pela mensagem única (substitui a do Core).
- Testes de Kernel: `RegistrationMailPolicyKernelTest` (conta encontrada só para e-mail cadastrado; aviso enviado ao dono; nenhuma conta criada).
- Limites conhecidos: (1) o tempo de resposta difere quando o aviso é enviado na mesma requisição; (2) o aviso pode ser usado para lotar a caixa de um endereço alheio, por isso o CAPTCHA do cadastro continua obrigatório; (3) o aviso não é enfileirado.

## Documentação — S3 (validação em ambiente real) — 2026-10-09

Mudança apenas documental; a versão vigente segue `0.2.0-dev.21`.

- DT-O06 executado: a validação do formulário de login recusa conta bloqueada e aceita a ativa.
- DT-P08 parcial (widget Turnstile verificado em `/entrar`); DT-P11 adiado com decisão (exceção de teste do CAPTCHA); DT-P04 adiado por ambiente (PHP 8.5 exige root); DT-P05 e DT-O07 adiados por dependência (credenciais SMTP e deploy); DT-P07 adiado a pedido do responsável.
- Critério da S3 cumprido: cada item tem resultado ou decisão de adiar.

## 0.2.0-dev.21 — S2: tipagem e primeiro teste de Kernel (DT-P02, DT-P03) — 2026-10-09

Classificação: testes e gate (sem mudança de comportamento).

- Gate: teto de `strict_types` vazio. Todo arquivo de `src/` declara `strict_types=1` (DT-P02).
- Primeiro teste de Kernel do Portal: `EmailConfirmationPolicyKernelTest` (login: confirmação de e-mail concede e remove o papel `email_confirmed`, que pula o CAPTCHA; uid anônimo ignorado). Atributos `#[Group]` e `#[RunTestsInSeparateProcesses]` (Drupal 11.3+).
- Execução: `SIMPLETEST_DB="<banco-de-teste-local>" vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/tests/src/Kernel`. Resultado: 3 testes, 16 asserções, OK.
- Pendente de S2: Kernel para a política de domínio (`DomainPurposeManager`, precisa do módulo Domain) e para o pagamento. O webhook (`WebhookGuard`) não depende do container; o fail-closed já tem teste unitário.

## 0.2.0-dev.20 — S1: fronteira de apresentação (DT-P01) — 2026-10-09

Classificação: correção de fronteira (nomes de classe; sem mudança de comportamento, rota ou dado).

- Classes `aculta-account*` (e IDs correspondentes) renomeadas para `portal-account*` nos templates, no CSS e no JS do próprio Portal, e nas classes PHP que as montam (`AccountShellBuilder`, `PortalController`, `AccountCoursesController`). Nenhuma classe do tema é usada pelo módulo.
- Gate `validate-aculta-portal-drupal11.php`: reprova `class="...aculta-..."` em `templates/*.twig`. Sonda negativa testada (reprovada).
- Atributos `data-aculta-*` permanecem: são contrato de JS, fora da regra de classes.
- Verificado: as páginas de Minha Conta renderizam as classes `portal-account*` e nenhuma `aculta-account*` (ver validação no PR).

## 0.2.0-dev.19 — F3: busca pública pelo índice do Search API no banco — 2026-10-09

RUNTIME STATUS: validada no Runtime oficial (2026-10-09), sem teste humano: `/busca` com resultados, aviso para termo curto e aviso sem resultado; `/busca/sugestoes` com sugestões por prefixo e sem acento; conteúdo não publicado fora dos resultados e das sugestões.

- Dependência nova: `drupal/search_api` ^1.40 (com o submódulo `search_api_db`). Justificativa: decisão do responsável de usar o banco; o Search API dá o índice e a consulta. O `search_api_autocomplete` foi avaliado e descartado: o autocomplete é do Core (`#autocomplete_route_name`), porque o `search_api_autocomplete` atua em formulários de Views, e a busca pública não usa Views.
- Servidor `aculta_database` (search_api_db, correspondência por prefixo, mínimo de 3 caracteres) e índice `aculta_conteudo` (artigo, projeto, página, atividade e documento; título, corpo, tipo, identificador, status; transliteração para buscar sem acento).
- Página `/busca` (domínio principal): resultados só para conteúdo que a pessoa pode ver (checagem de acesso por nó); avisos por Messenger para termo curto e para busca sem resultado.
- Sugestões `/busca/sugestoes` para o campo de busca (autocomplete do Core), com o mesmo índice e o mesmo controle de acesso.
- `web/robots.txt` protegido do scaffold do Composer (`[web-root]/robots.txt: false`): antes, qualquer `composer install` apagava a linha de Sitemap do projeto. Verificado.
- Verificado em cópia isolada: consulta com resultado (7 itens para "projeto"), aviso para termo curto, aviso sem resultado, sugestões por prefixo e sem acento ("transparencia" → "Transparência").
- Pendências: `content_access` no índice (hoje o controle de acesso é feito por nó na página); testes de Kernel da busca; QA visual.

# Changelog — ACULTA Portal

## Documentação — F1 validada no Runtime oficial — 2026-10-09

Mudança apenas documental; a versão vigente segue `0.2.0-dev.18`.

- A F1 (modo de cor) fechou em `0.5.0` do tema (PR #146). O status de Runtime "DEFERRED" das entradas 0.2.0-dev.17 e dev.18 deixa de valer.
- Validação no Runtime oficial: roteiro automatizado 7/7; HTTP `light`/`dark`/`auto` corretos; escolha da pessoa usuária sobrepõe o padrão do site.

## 0.2.0-dev.18 — correção do autowire em AppearanceHooks — 2026-10-09

- `AppearanceHooks` importava `Drupal\Core\DependencyInjection\Attribute\Autowire`, que não existe. O atributo era ignorado e o container não montava o hook. Passa a usar `Symfony\Component\DependencyInjection\Attribute\Autowire`, como os demais arquivos do Portal.
- Verificado em cópia isolada do Runtime (banco de teste, não o Runtime compartilhado): serviço e hook carregam; `/configuracoes` e `/admin/config/aculta/aparencia` registrados; escolha de usuária sobrepõe o padrão do site; visitante recebe o padrão. RUNTIME STATUS do Runtime oficial segue DEFERRED.

## 0.2.0-dev.17 — Modo de cor: preferência por usuário e padrão global — 2026-10-09

RUNTIME STATUS: DEFERRED no Runtime oficial. Em cópia isolada, serviço, rotas, formulário e contrato verificados (ver 0.2.0-dev.18).

- Preferência do modo de cor por usuário em `user.data` (banco), chaves `aculta_portal`/`color_mode`, valores `light`, `dark` e `auto`.
- Minha Conta > Configurações (`/configuracoes`): formulário com os três estados; item no menu da conta.
- Padrão global do site em `/admin/config/aculta/aparencia` (`aculta_portal.appearance`, padrão `light`), permissão `administer aculta appearance settings`.
- Contrato neutro para o tema: `aculta_color_mode` no `preprocess_html` (`AppearanceHooks`). O tema aplica o atributo.
- Cacheability: contexto `user`, tag `config:aculta_portal.appearance` e tag `user:<uid>`; ao salvar, a tag do usuário é invalidada.
- Rota de conta incluída nas listas de propósito `account` do domínio.

Validação estática: `validate-aculta-portal-drupal11.php` PASS (470 checagens); `php -l` nos arquivos novos.

## 0.2.0-dev.16 — política de confirmação de e-mail (base) e validade infinita — 2026-10-09

- Estado de confirmação por usuário (`EmailConfirmationPolicy`, `user.data` `aculta_portal`/`email_confirmed`). Sem a marca: não confirmado.
- Login por OAuth e confirmação da troca de e-mail marcam o e-mail como confirmado (`EmailConfirmationSubscriber`).
- Validade infinita do link de confirmação: `EmailConfirmationEntity` (subclasse de `email_confirmer_confirmation`, `isExpired()` falso).
- Novo hash a cada solicitação: `EmailConfirmationRequester` cancela pedidos pendentes anteriores e cria o novo; rota `aculta_portal.email_resend` (login + CSRF).
- Gate Drupal 11+: contagem fixada de subscribers passa de 8 para 9 (deliberado).
- Pendente: aviso vermelho em "Minha conta", bloqueios (cursos, wiki, comentários, loja, apoio) e cadastro com `verify_mail` desligado.

## 0.2.0-dev.15 — cadastro com senha e confirmação de e-mail — 2026-10-09

- Formulário de cadastro (`user_register_form`): mostra nome de usuário, e-mail, senha e confirmação, e CAPTCHA. Foto não aparece (display `user.user.register` com só a seção de conta).
- Com verificação de e-mail ligada, a senha escolhida é validada (mínimo de 8 caracteres) e gravada depois de salvar a conta; a conta continua bloqueada até a confirmação do e-mail.
- `FormHooks` (`form_alter`) e `PortalFormCallbacks::validateRegistrationPassword()` / `storeRegistrationPassword()`.

## 0.2.0-dev.14 — confirmação de e-mail para visitante, login automático e tradução — 2026-10-09

- Página de confirmação de troca de e-mail liberada a visitante anônimo para pedidos pendentes e não expirados (`EmailConfirmationResponseAccessHandler`). O hash da URL continua sendo verificado por `confirm()`.
- Após confirmar, o visitante anônimo entra na conta do pedido (`EmailConfirmerHooks::loginAfterConfirmation`, `user_login_finalize()`). Risco aceito: quem tiver o link entra na conta; o link é de uso único e expira.
- Link de confirmação sempre no host da conta (versão 0.2.0-dev.13).
- Tradução para português: textos de e-mail e de confirmação em `config/sync/language/pt-br/email_confirmer.settings.yml`; textos fixos dos módulos `email_confirmer_user` e `change_mail_page` em `translations/email_confirmer.pt-br.po` (importar com `drush locale-import`).
- Não altera o contrib `email_confirmer`.

## 0.2.0-dev.13 — link de confirmação de e-mail sempre no host da conta — 2026-10-09

- `TokenHooks::alterEmailConfirmationUrl()` (`tokens_alter`): o link `[email-confirmer:confirmation-url]` passa a usar o
  domínio ACCOUNT do ambiente. Antes, usava o host da requisição, e fora da conta a rota respondia 404.
- Não altera o módulo contrib `email_confirmer`.

## 0.2.0-dev.12 — ambiente definido pelo deployer — 2026-10-09

- O gerenciador de credenciais lê `var/deployer/environment.json` antes da configuração local: o ambiente
  (teste ou produção) é o que o deployer definiu.
- Painel de status do deployer mostra ambiente e endereço do site.
- Testes: ambiente do deployer prevalece; arquivo inválido cai para a configuração local.

## 0.2.0-dev.11 — credenciais do ambiente: cadastro, máscara e revelação — 2026-10-09

- Removida a importação de arquivo (`secrets/import`) e a exclusão segura da origem. O cadastro passa a ser pelo
  formulário de "Credenciais do ambiente" (`/admin/config/aculta/segredos`).
- Valores salvos aparecem mascarados (dois caracteres em cada ponta). O botão 👁 revela o valor completo sob demanda
  por rota com token CSRF e sem cache. Revelação e salvamento entram no log só com nomes e usuário.
- `SecretsManager` (substitui `SecretsImporter`): salvamento com merge (campo vazio mantém), só nomes do contrato,
  valores de uma linha até 4 KiB, gravação atômica em modo 0640.
- `aculta_secrets_storage`: `file` (teste) grava o arquivo; `database` (produção) não grava. A camada criptografada de
  produção é provisionada pelo `aculta_deployer` após o deploy (fase 9, pendente).
- Testes: suíte do Portal com 54 casos (máscara, merge, rejeições, armazenamento em banco e leitura do relatório).

## 0.2.0-dev.10 — painel do ACULTA Deployer e opções de sitemap — 2026-10-09

- Página `/admin/config/aculta/deployer` (somente leitura, permissão `administer aculta deployer`): lê o relatório neutro
  `var/deployer/status.json` e mostra fronteiras, correções abertas e credenciais por nome e estado. Sem valores.
- Visão geral (`/admin/config/aculta/portal`): links para o status do deployer, para importação de credenciais, para as
  variantes de sitemap e para as configurações do sitemap.
- Reconhecimento de complemento: o Portal não depende do submódulo `aculta_deployer`; sem o relatório, a página informa
  "indisponível".
- Fronteira: a pasta `src/Deployer` pode citar a ferramenta, mas não a executa.

## 0.2.0-dev.9 — importação de credenciais pelo painel (ACULTA Secrets Contract) — 2026-10-09

- Painel `/admin/config/aculta/segredos` (permissão `administer aculta secrets`, restrita): mostra cada variável do contrato com ✔ OK / ⚠ Atenção / ✖ Erro (mesma biblioteca e legenda do diagnóstico do Portal). Nunca exibe valores.
- Importação: lê somente `secrets/import/*.env` (fora de `web/`), valida formato, nomes do contrato e obrigatórios do ambiente, grava o arquivo de credenciais de forma atômica e apaga a origem com sobrescrita (duas passadas) antes de remover. Substituir o arquivo exige confirmação.
- Relatório de status (`runtime_requirements`): aviso quando faltam obrigatórias, com link para a importação.
- `aculta_portal.secrets_importer` (DI), `SecretsFormat`, `SecretsRequirementsHook`, `SecretsImportForm`.
- Testes: 8 casos novos em `SecretsImporterTest` (suíte do Portal: 56 OK).
- Limite: a sobrescrita não garante remoção física em sistemas com cópia-na-escrita, journaling ou SSD (ver `docs/operations/SECRETS.md`).
- Regra de permissão do arquivo: recusa escrita de grupo e acesso de outros; leitura de grupo (ACL do processo web, 0640) é aceita. Alinhada ao loader do Drupal (aprovada pelo responsável).

## 0.2.0-dev.8 — runInPurpose para avaliação de acesso no contexto de origem (0.1.0-F) — 2026-10-09

- `DomainPurposeManager::runInPurpose()`: executa um callback com o Domain do purpose ativo e restaura o anterior.
- Consumido pelo `aculta_portal_sitemap` para avaliar o acesso anônimo de verbetes da WIKI no contexto do host da WIKI. A regra de acesso do hook `entity_access` não foi alterada.
- Correção de robots: `/entrar` (`user.login`) e demais rotas de autenticação e conta do Core (`PortalHooks::AUTH_ROUTES`: login, saída, cadastro, recuperação de senha, reset, edição de identidade) passam a ter `noindex, nofollow`.
- Correção de robots: a raiz da WIKI e a de CURSOS (`aculta_portal.wiki_home`, `aculta_portal.courses_home`) saíam com `noindex` pela regra geral das rotas `aculta_portal.*`, contrariando o sitemap. Agora são entradas públicas (`PortalHooks::PUBLIC_PORTAL_ROUTES`). Busca da WIKI e rotas privadas continuam com `noindex`.

## 0.2.0-dev.7 — canonicalPathUrl para o sitemap por purpose (0.1.0-E) — 2026-10-09

- `DomainPurposeManager::canonicalPathUrl()`: URL absoluta de um caminho no host canônico de produção do purpose (mesma regra de `canonicalRouteUrl()`).
- Consumido pelo `aculta_portal_sitemap` (piloto MAIN e SUPPORT). Sem alteração de rotas ou de comportamento público.

Validação: `validate-aculta-portal-drupal11` (ver PR). **Não validado**: testes de navegador deste método (usado apenas na geração do sitemap).

## 0.2.0-dev.6 — Esqueleto do submódulo aculta_portal_sitemap (0.1.0-B) — 2026-10-09

Classificação: PATCH da linha 0.2.0 (estrutura opt-in; sem rotas, serviços ou hooks; sem alteração de comportamento).

- Adiciona `modules/aculta_portal_sitemap` (0.1.0-B): metadados e dependências declaradas (`aculta_portal`, `simple_sitemap`, `domain`). Descoberto e não habilitado.
- Simulação de habilitação (`pm:enable --simulate`) resolve as dependências sem alterar o banco.
- Referência na documentação do Portal (`docs/portal/README.md`) para ADR-009.

Validação: gates do Portal (ver PR). **Não validado**: habilitação real do submódulo (não habilitado nesta versão).

## 0.2.0-dev.5 — Submódulo aculta_deployer 0.1.0 — 2026-10-09

Classificação: PATCH da linha 0.2.0 (ferramenta de deploy em submódulo; o Portal não muda comportamento).

- Adiciona `modules/aculta_deployer` (ACULTA Deployer 0.1.0): descoberto pelo Drupal, não habilitado. Substitui `*.aculta.toca.net.br` por `*.aculta.org` no build de produção e mantém o registro de correções de deploy (DEP-0001 canonical e DEP-0002 sitemap, bloqueantes).
- O Portal fora do submódulo não referencia a ferramenta; o gate `boundaries` do submódulo reprova essa referência.
- Dívida nova: sitemap sem o host de apoio (DT-P23), decisão pendente.

Validação: `validate-aculta-portal-drupal11.php` PASS (382); testes do submódulo PASS; `check` PASS. **Não validado**: runtime com o submódulo habilitado (não habilitado nesta versão).

## 0.2.0-dev.4 — Página de apoio na página inicial do subdomínio (correção) — 2026-10-09

Classificação: PATCH da linha 0.2.0 (correção de caminho público de apoio; sem API nova).

- A página pública de apoio é a página inicial do subdomínio de apoio (`/`). A rota interna `aculta_portal.support_form` continua em `/apoio`, porque o Drupal não indexa rota com caminho `/`.
- `SupportFrontOnlySubscriber` (novo, priority 30): `/apoio` acessado diretamente responde 404; a página é servida somente quando a requisição original é `/`.
- `front` do domínio de apoio: `/apoio` (coleção `domain.apoio_aculta_org`, `system.site`), exportado em `config/sync/domain/apoio_aculta_org/system.site.yml`. O valor do runtime estava em `/apoie`, rota já removida.
- Verificado no runtime pelo Drush: front do domínio de apoio resolve `/` para `/apoio` e casa `aculta_portal.support_form`; `/apoio` direto é recusado pelo subscriber; o `/` do domínio principal continua em `/node/1`.
- **Não verificado por requisição HTTP real**: o probe do kernel falha com 500 no subscriber de HTMX fora de uma requisição HTTP, também para páginas que já funcionavam; o 404 de purpose no host principal depende do `DomainRouteSubscriber` existente.
- **Pendente**: o link literal `href="/apoio"` na home (`home-content.json`) não passa pelo hook e precisa de resolução por purpose ou de URL do host de apoio.

## 0.2.0-dev.3 — Página de apoio em /apoio (DT-T18) — 2026-10-09

Classificação: PATCH da linha 0.2.0 (correção de caminho público e redirecionamento; sem API nova).

- Página pública de apoio em `/apoio` (`aculta_portal.support_form`), grafia confirmada pelo responsável. Hospedada no host SUPPORT, cuja página inicial é `/apoio` (`config/sync/domain/apoio_aculta_org/system.site.yml`).
- Conta de apoio em `/meu-apoio` (`aculta_portal.support_my`), alinhada a `docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`. Antes ocupava `/apoio`.
- Sem rota `/apoie` e sem redirecionamento 301: o site está em desenvolvimento. Links de apoio no código são resolvidos para o host SUPPORT pelo hook.
- `PortalHooks` aceita `internal:/apoio` e `internal:/apoie` ao resolver links de apoio para o purpose SUPPORT, para compatibilidade com conteúdo ainda não recarregado.
- Atualizados: conteúdo da home (`scripts/content/institution/home-content.json`), validador de comércio (prefixo de rota de webhook) e validador de navegador (caminho de apoio; a reescrita de `supportLayout` segue pendente em DT-T18).

Validação: `validate-aculta-portal-drupal11.php` PASS (382); `validate-public-slugs.php` PASS (267 rotas). **DEFERRED**: rotas, `front` do host SUPPORT e conteúdo ainda não foram executados no runtime Drupal; exigem `drush cim` e a carga do conteúdo no homelab, fora desta tarefa.

## 0.2.0-dev.2 — brand_media da Wiki420 no Domain Presentation (PR #112) — 2026-10-09

- `DomainPresentationBuilder` monta `regions.brand_media` somente no purpose `wiki`, como render array `image` com `srcset` (480/720/960 px), `sizes`, `loading` eager e `fetchpriority` high, usando URL pública `base:`. Sem o arquivo de 960 px, nada é montado.
- Cacheability: a presentation continua acumulando o cache de Domain; a escolha de identidade é do Portal, e o tema só apresenta.
- Limite: o caminho dos assets fica no Portal e no tema; contrato de assets fica para fase futura.
- Versão marcada em `aculta_portal.info.yml`; sem tag.

## 0.2.0-dev.1 — 2026-10-09 — LMS com slugs amigáveis (DT-P21)

- Rota `lms.group.answer_form` servida em `/curso/{curso}/{lição}/{atividade}`, com slugs derivados dos títulos do curso, da lição e da atividade (sem campos novos). Posições duplicadas recebem sufixo `-2`, `-3` na ordem armazenada.
- Código: `src/Lms/LmsFriendlySlugs.php` (derivação e resolução), `LmsFriendlySlugConverter` (conversores de parâmetro), `LmsFriendlyRouteProcessor` (URLs geradas com slugs, com cache de curso e lição) e `EventSubscriber/LmsFriendlyRouteSubscriber.php` (troca de caminho e tipos).
- A rota numérica antiga `/course/{id}/...` não tem alias e responde 404; redirecionamento pendente (DT-P22).
- Gate de slugs: `lms.group.answer_form` sai da linha de base DT-P20 (20 rotas) e passa a exigir o caminho do Portal no subscriber.
- Gate Drupal 11+: contagem de subscribers do Portal ajustada de 7 para 8, com justificativa no próprio gate.
- Testes: `tests/src/Unit/LmsFriendlySlugsTest.php` (8 casos). Verificação em runtime: slugs geram URLs corretas, conversão reverte às posições, slug inexistente gera 404, slug válido alcança o controle de acesso.
- Runtime status: código e gates verificados no Homelab; sem captura autenticada do fluxo de atividade (DT-P21 pendente de verificação com aluno).

## Versionamento por marcação no código, versão vigente 0.1.0 — 2026-10-09

- Política: versão marcada em `aculta_portal.info.yml`; tags Git só sob pedido explícito do responsável (ver `docs/operations/RELEASES.md`).

## Documentação (sem tag), versão vigente portal-v0.1.0 — 2026-10-09
- Limpeza documental: removidos P10-R, RELEASE-P10, handoff, HARDENING-P9, matriz P8 e os documentos de produto de Conta, Fórum, Revista, Loja e Wiki. Regras que sobreviveram estão em `docs/portal/GUARDRAILS.md`. Histórico no Git (último commit `9c95420`).

- Revisão documental: roadmap reescrito em saneamento antes de features, sem plano de 1.0; registro de dívidas em `docs/operations/DEBT-REGISTER.md`.
- Correção de referências à PR #63 (fechada) e do estado do `config_translation`, que está ativo na `main`.

## portal-v0.1.0 — 2026-10-09 — primeira release rastreada

Primeira versão do Portal com tag. Consolida as fases P0–P10 da modernização Drupal 11+ (PR #90) e a auditoria P10-R (PR #91), mais as correções posteriores de domínio, autenticação, CAPTCHA e a hook de seções do tema 0.3.1.

Escopo incluído:

- Domain Presentation Builder e contrato neutro para o tema (0.2-B).
- Políticas de domínio administrativo, checkout e pagamento no purpose main.
- Fronteira de autenticação e CAPTCHA (Turnstile como provider único).
- Hook de tema `aculta_section` para seções editoriais (tema 0.3.1).
- Testes unitários do Portal e gate Drupal 11+ (366 checks).

Exceções conhecidas no gate de release:

- `smtp.settings` e `system.mail` ficam fora da comparação com o Runtime (sem credenciais no ambiente local). Os valores versionados são afirmados pelo validador.
- `composer validate` emite avisos de versão exata para `tabby/tabby` e `tippyjs/tippyjs`; não são erros.
- O webform envia via SMTP sem o template HTML; diferença registrada na P10-R.

## 2026-10-09 — Hook de tema para seções (entregue no tema 0.3.1)

- `PortalHooks::theme()` declara `aculta_section` (variáveis `section_title`, `section_heading`, `section_variant`, `section_body`). O template do módulo é um fallback neutro, sem classes de tema; o tema sobrescreve.
- `EditorialHooks::entityViewAlter()` entrega essas variáveis para blocos `basic`. Nenhuma regra por purpose; a visibilidade do bloco é que escolhe o cabeçalho de cada subdomínio.
- Commits na `main`: `1645359`, `ae94114`. Entregue pelo tema 0.3.1 (ver `web/themes/custom/aculta420/CHANGELOG.md`).
- Gate Drupal 11+ do Portal: PASS (366 checks).
- Não há tag `portal-v*` ainda.

## 2026-10-08 — P5.4-D: PortalRequirementsController DI

- `\Drupal::service('theme_handler')` ×2 e `\Drupal::root()` → `ThemeHandlerInterface` e parâmetro `app.root` injetados por `#[Autowire]`; helpers lazy `currentUser()`/`moduleHandler()`/`config()` → serviços explícitos; `strict_types=1`; `Composer\InstalledVersions` importado.
- Homelab (admin uid 1): `/painel-administrativo/configuracoes/aculta/portal` e `/requisitos` com região `<main>` idêntica antes/depois (22 linhas de tabela, 16 OK).
- Gate: teto de locator e dívida de `strict_types` zerados para o controller; invariantes de injeção.

## 2026-10-09 — P10-R B.1: enumeração de contas bloqueadas no login

- Achado: o login do Core responde "O nome de usuário … não foi ativado ou está bloqueado." antes da verificação de senha, para qualquer conta existente bloqueada ou não ativada. Contas inexistentes e senhas erradas recebem a mensagem genérica. O módulo `username_enumeration_prevention` cobre apenas o formulário de recuperação de senha. Com o cadastro aberto e a confirmação por e-mail obrigatória, cada cadastro não confirmado virava um endereço revelado.
- Correção: a etapa `validateAuthentication` do formulário de login é substituída por `aculta_portal.form_callbacks:validateLoginAuthentication`. Contas bloqueadas pulam essa etapa; `validateFinal` produz a mesma mensagem genérica das contas inexistentes e registra a tentativa no controle de flood. As demais contas seguem a lógica do Core.
- Verificação (executada): cadeia de validação real do formulário, em transação revertida, com três estados: inexistente, bloqueada e ativa com senha errada. Os três retornam "Nome de usuário ou senha incorretos. Esqueceu sua senha?". Testes unitários (`LoginBlockedAccountTest`) e mutação confirmam que a regra é protegida.
- Não verificado por HTTP: o CAPTCHA Turnstile bloqueia envio por script, como esperado.

## 2026-10-09 — Resolução final dos bloqueios da PR #90

- **Webform via SMTP:** `system.mail` `webform: SMTPMailSystem` e `smtp.settings` `smtp_allowhtml: true`. Verificado sem envio (formatação em transação revertida): o transporte SMTP não aplica o modelo HTML do webform, então as notificações de contato chegam como fragmento HTML sem o template `webform_email_html`. Registrado como diferença conhecida. Nenhuma outra mensagem do Portal usa HTML.
- **Vocabulário `tags` removido** (decisão do responsável), no Runtime e em `config/sync`: `field.field.node.article.field_tags`, `field.storage.node.field_tags` e `taxonomy.vocabulary.tags`. Nenhum termo e nenhum nó usavam o campo. A exclusão do campo fez o Drupal adicionar o widget `moderation_state` ao formulário do artigo; esse efeito colateral foi revertido no Runtime e no sync, e o formulário ficou idêntico ao anterior, menos `field_tags`. Backup antes da exclusão: cópia local em `~/` (arquivo de backup do Runtime anterior).
- **`validate-admin-cleanup`:** passa. Linha de base regenerada em 2026-10-09 (`tmp/`, ignorada pelo git) e verificações de rota por nome estável (os caminhos administrativos estão localizados em `/painel-administrativo/...`).
- **Validadores de e-mail:** `smtp.settings` e `system.mail` saem da comparação com o Runtime (ambiente sem credenciais) e são afirmados pelos valores versionados.
- **`validate-final-contact`:** PENDENTE (mensagem `PENDING:`, saída 2; o Drush reporta 1 para qualquer saída diferente de zero).
- Ajustes de `validate-portal-commerce-security` e `validate-final-drupal` para o novo conjunto de chaves.

## 2026-10-09 — P10-R: correções da revisão independente

- Login: a conta bloqueada agora passa pelo mesmo controle de flood por IP do Core (mesma condição e mesmo resultado). Sem essa paridade, um IP que atingisse o limite revelaria contas bloqueadas. Verificado com `ip_limit` reduzido a 0 em transação revertida: os três estados retornam `flood_control_triggered = ip`.
- Login: o callback só autentica quando o objeto do formulário é `UserLoginForm`.
- Login: a troca do validador é sempre aplicada exatamente uma vez (substitui o do Core ou o coloca primeiro), para que uma mudança de nome nunca restaure o vazamento em silêncio.
- Testes: asserções de estado (sem erro e sem `set`) para conta bloqueada; caso positivo de assinatura do webhook; assinatura forjada com timestamp atual (o teste anterior dependia da tolerância de timestamp). Suíte: 40 testes PASS; quatro mutações nas proteções falham os testes.
- Documentação: o uso de `sebastian/diff` pelo Core em runtime estava omitido na auditoria; corrigido. O diff de configuração foi verificado no navegador do Homelab.
- Permissão de leitura: o diretório `config/sync` (criado pela exportação) não era legível pelo usuário do servidor web, e a tela de diferença de configuração retornava 500. Concedida leitura (`u:aculta:rX`, com entrada padrão) apenas em `config/sync`, nos arquivos do próprio repositório. Nenhum segredo está nesse diretório.

## 2026-10-09 — P10-R: auditoria pós-merge

- **A.1 Configuração:** drift atual limitado a `smtp.settings` e `system.mail` (intencional, específico do ambiente). Sem objetos apenas no sync nem apenas no banco. Sem segredos literais em `config/sync`. Domain aliases com `environment: homelab/local` versionados; efeito em produção depende do nome de ambiente de produção (verificar). Módulos de administração ativos (`views_ui`, `field_ui`, `help`, `update`, `dblog`) — avaliar desativação em produção.
- **A.2 PHP:** Homelab com PHP 8.4.26 apenas; PHP 8.5 não instalado (exige pacotes do sistema e decisão operacional). Análise estática: sem casts ou funções removidas/deprecadas no 8.5; sem recursos exclusivos do 8.4; pacotes travados declaram suporte compatível. Execução em 8.5: DEFERRED.
- **A.3 Composer:** `require.php: >=8.3` (piso comum do Core 11.4 e dos pacotes travados; versão testada: 8.4.26). `composer/semver: ^3.4` declarado (uso direto em `PortalRequirementsController`; travado em 3.4.4). `scaffold.file-mapping` exclui `.gitattributes`, que o scaffolding do Core sobrescrevia. `drupal/core-dev: 11.4.8` (dev) para PHPUnit: 85 pacotes de desenvolvimento novos, nenhuma atualização de pacote de runtime; `sebastian/diff` 7.0.1 → 6.0.2, dentro do intervalo aceito pelo Core e sem uso em código de runtime. `composer audit`: sem advisories. `composer validate`: avisos pré-existentes sobre pins exatos em bibliotecas de frontend.
- **A.4 PHPUnit:** 33 testes unitários em `tests/src/Unit`, PASS. Cobrem: rotas por purpose (Commerce), exceção de reset de senha (dono e token), Mercado Pago fail-closed, acesso cruzado entre contas, contrato de apresentação e bloqueio de conta no login. Mutações nas proteções falham os testes. Pendente: testes de Kernel para serviços P5–P7 (exigem banco de teste).
- **B.1 Login:** correção de enumeração de contas bloqueadas (ver entrada anterior). Cadastro: a mensagem do Core "The email address … is already taken" revela endereços cadastrados; risco residual, decisão de produto pendente.
- **B.3 ACL:** a ACL `bdtgn` já não existe em `web/sites/default/files`. O pool `bdtgn` atende apenas o vhost `dbtng.toca.net.br`.
- **C Verificação:** lint de 59 arquivos PHP, gate PASS (366), PHPUnit PASS (33), Composer validate/audit/platform PASS, `drush cr` e `updatedb:status` sem pendências, `config:status` com apenas as chaves de e-mail específicas do ambiente. Smoke HTTP: todas as rotas esperadas, cadastro e recuperação de senha acessíveis, Mercado Pago sem segredo retorna 503, Social Auth redireciona ao Google, isolamento entre duas contas verificado.

## 2026-10-09 — Resolução dos bloqueios da PR #90 (validadores)

- Chaves de e-mail (`smtp.settings`, `system.mail`) são específicas do ambiente: saem da comparação com o Runtime do Homelab. Em vez disso, os validadores afirmam os valores versionados (`smtp_on: true`, `SMTPMailSystem`). Aplicado em `validate-final-drupal` e `validate-portal-commerce-security`.
- `validate-final-contact`: marcado como **PENDENTE** (mensagem `PENDING:` e saída 2). Motivo: Turnstile bloqueia envio por script; a exceção de teste não foi autorizada. Observação: o Drush reporta qualquer saída diferente de zero como 1; a distinção se faz pela mensagem.
- `validate-admin-cleanup`: a linha de base `tmp/admin-structure-audit.json` foi gerada hoje (2026-10-09) a partir do estado atual; não é a linha de base da limpeza original. Ao rodar, a verificação encontra `taxonomy.vocabulary.tags`, removido pela limpeza (registrado no estado) e presente em `config/sync` desde `d08e8dd`. Pendente de decisão: remover o vocabulário e `node.article.field_tags` (sem conteúdo marcado) ou aceitar a restauração e atualizar o registro.

## 2026-10-09 — SMTP habilitado na configuração versionada

- `smtp.settings` `smtp_on: true` e `system.mail` `interface.default: SMTPMailSystem` em `config/sync`. Host e porta (SMTP2GO) já estavam versionados; usuário e senha permanecem vazios no arquivo e são fornecidos pelo ambiente via Drupal Key (`SMTP2GO_USERNAME`, `SMTP2GO_PASSWORD`). Nenhum segredo entra no repositório.
- Remetente: `system.site` `mail` já configurado.
- Não alterado no Runtime do Homelab (sem credenciais SMTP; habilitar lá faria cada envio falhar). Por isso `validate-final-drupal` e `validate-portal-commerce-security` acusam drift em `smtp.settings` e `system.mail` até a importação em produção.
- Pendente: o formulário de contato (webform) continua com `interface.webform: webform_php_mail`; as notificações de webform não passam pelo SMTP. Decidir se devem passar.
- Verificação em produção ainda obrigatória: variáveis de ambiente presentes e um envio de teste real para endereço controlado pelo responsável.

## 2026-10-08 — Cadastro público e cookie de sessão compartilhado

- Cadastro aberto: `user.settings` `register: visitors` (Runtime e `config/sync`). Sem aprovação administrativa (`register_no_approval_required: true`); confirmação de e-mail obrigatória (`verify_mail: true`); contas OAuth dispensam confirmação. Verificado no Homelab: `/criar-conta` no host da Conta exibe o formulário do Core com Turnstile; no host principal responde 404.
- **Entrega de e-mail não verificada.** No Homelab, `smtp.settings:smtp_on` está `false` e o transporte é `php_mail`: os e-mails de confirmação não são entregues localmente. Antes de abrir o cadastro em produção, confirmar SMTP ativo e entrega real.
- Cookie de sessão compartilhado: Homelab usa `services.homelab.yml` (`cookie_domain: '.aculta.toca.net.br'`, `cookie_samesite: Lax`), ativo no container. Produção: novo `services.hostinger.yml.example` com `.aculta.org` e `settings.hostinger.php.example` carrega esse arquivo; o arquivo real do servidor não é versionado.
- `validate-cross-domain-request-policy` deve ser executado com o host do ambiente (`--uri=https://aculta.toca.net.br` no Homelab); sem isso, a CLI usa `localhost` e a validação falha por design. Passa com 17 checks.
- `validate-portal-commerce-security`: a regra antiga "cadastro fechado até comprovar entrega de e-mail" foi substituída pela regra vigente: cadastro público exige `verify_mail` e aprovação desativada.
- Pendentes: `validate-final-contact` (CAPTCHA) e `validate-admin-cleanup` (linha de base ausente).

## 2026-10-08 — Cadastro sem aprovação e destino pós-login

- `user.settings` `notify.register_no_approval_required: true` (decisão do responsável): o cadastro não exige aprovação administrativa; a confirmação é feita por e-mail (Email Confirmer), e contas OAuth dispensam confirmação. A rota de cadastro continua `register: admin_only` até decisão separada sobre abertura pública.
- Destino pós-login: os links de "Entrar" já levavam o destino (`PortalHooks::preprocessLinks`), mas o bloco de menu da Conta (`system_menu_block:account`) era armazenado em cache sem `url.path` e `url.query_args`, e exibia o destino da primeira página que o renderizou. Novo `BlockBuildHooks` adiciona esses contextos ao bloco. Verificado intercalado: 9/9 links com o destino da própria página.
- Login social (`/oauth/google`) preserva o destino. Sem destino, o fallback `post_login` continua `/user` (Conta).
- Validadores: `validate-portal-commerce-security` removia a referência a `user_registrationpassword`, módulo removido em `b4b4389`; manifesto corrigido (56 objetos). `validate-final-drupal` passa com a configuração sincronizada.
- Pendentes: `validate-final-contact` (CAPTCHA), `validate-admin-cleanup` (linha de base ausente), `validate-cross-domain-request-policy` (cookie_domain do Homelab).

## 2026-10-08 — Configuração: exportação do Runtime para config/sync (opção A)

- `drush cex` exportou o Runtime: 40 objetos novos (Commerce, Views e campos de doação, que existiam só no banco) e 12 alterados. Nenhum objeto foi removido do sync.
- Alterações relevantes: `user.role.authenticated` deixa de conceder `skip CAPTCHA` a usuários autenticados; `metatag.metatag_defaults.front` passa de `https://aculta.org/` fixo para `[site:url]`; traduções pt-BR de idioma, mensagens de conta e Views restauradas; `core.extension` inclui `config_translation`.
- **Mantido sem exportar:** `user.settings` `register_no_approval_required` (Runtime `true`, sync `false`). Abrir cadastro sem aprovação é decisão de política; o sync permanece `false` até o responsável decidir. Por isso `validate-final-drupal` e `validate-portal-commerce-security` continuam falhando, apenas nesta chave.
- `social_auth.settings` `post_login` do Runtime (`/user`, redirecionado para a Conta) não foi alterado: o sync continha `/`. Confirmar qual destino é desejado.

## 2026-10-08 — Validadores: correções de expectativas obsoletas

- `validate-home-carousel.php`: a view `home_editorial_highlights` pagina em 3 itens (`items_per_page: 3` no Runtime e em `config/sync`). A expectativa de 4 itens era obsoleta; a checagem de ordenação por peso permanece.
- `validate-admin-cleanup.php`: o inventário de Views era fechado em 12 itens e não incluía as Views de Commerce, LMS, Wiki, Cursos e Social Auth, exigidas pelo Portal. O inventário passa a ser o revisado (40); qualquer View fora dele continua falhando. A exigência de descrição passa a valer apenas para as 14 Views curadas.
- Pendentes, não alterados: `validate-admin-cleanup` depende de `tmp/admin-structure-audit.json` (linha de base da limpeza, não versionada e ausente); `validate-final-contact` falha porque o CAPTCHA Turnstile do formulário rejeita envio por script (correto); `validate-cross-domain-request-policy` exige `session.storage.options.cookie_domain` no Homelab; `validate-final-drupal` e `validate-portal-commerce-security` dependem de sincronização de configuração.

## 2026-10-08 — P10: homologação no Homelab e auditoria final

- Lint de 71 arquivos PHP: PASS. Gate: PASS (361 checks). `composer audit`: sem advisories. `check-platform-reqs`: PASS para PHP 8.4.26.
- `drush cr`, `updatedb:status`: PASS (nenhuma atualização pendente). `config:status`: FAIL pré-existente (drift Runtime × `config/sync`), não causado por este branch.
- Validadores: 9 PASS; 7 FAIL idênticos aos de `main`.
- Smoke HTTP por purpose, autenticação (CAPTCHA ativo: envio por script rejeitado, esperado), isolamento de conta e Views: PASS.
- Não executado: PHPUnit do módulo (inexistente), PHP 8.5, enumeração de contas (CAPTCHA), Mercado Pago com segredo real.
- Não merge. Registro completo e pendências em `docs/portal/RELEASE-P10.md`.

## 2026-10-08 — P9: hardening, segurança, desempenho e rollback

- Segredos: nenhum literal de credencial em código rastreado nem nos padrões de credencial do histórico git. Configurações de Key usam provedor `env`. O loader `aculta.secrets.php` rastreado não contém valores.
- Erros: nenhuma exposição de exceção, trace ou saída de depuração em `src/`.
- Webhook Mercado Pago: falha fechada verificada (503 sem segredo; 401 sem ou com assinatura inválida), in-process com segredo fictício e no endpoint real.
- Desempenho medido no Homelab: anônimo 23–27 ms; Conta logado 124 ms.
- Órfãos: nenhuma classe sem referência; serviços sem referência direta são tags ou consumidos pelo contrib.
- Rollback: código apenas. Nenhuma alteração de config, install ou update desde `052a212`. Runbook e pontos de retorno em `docs/portal/HARDENING-P9.md`.
- Gates: sem regressão em relação à P5-R; falhas pré-existentes permanecem e também existem em `main`.

## 2026-10-08 — P8: deprecações e prontidão para D12/D13

- Novo `scripts/audit-portal-deprecations.py` (biblioteca padrão): indexa `@deprecated` do `web/core` e cruza com o módulo (imports de classe, chamadas estáticas e de instância, funções procedurais). Validado com chamadas plantadas: detecta `views_embed_view()` e `SessionManagerInterface::delete()`. Resultado no módulo: 0 achados.
- Matriz de classificação (CURRENTLY RECOMMENDED IN D11 / DEPRECATED IN D11 / REMOVED IN D12 / ANNOUNCED FOR D13) em `docs/portal/DEPRECATION-MATRIX-P8.md`.
- Nenhuma atualização de Core; Upgrade Status/Rector não instalados (nova dependência sem necessidade comprovada nesta fase).
- Achados pendentes de decisão: `composer.json` sem `require.php` (alvo 8.5 não declarado); `composer/semver` usado sem declaração direta; `mercadopago/dx-php` usado via contrib; teste em PHP 8.5 não executado (Homelab tem 8.4.26).

## 2026-10-08 — P7: subscribers, prioridades e multidomínio

**P7.1 — inventário:** 7 listeners do Portal (4 de request, 1 de response, 1 de alteração de rota, 2 de Social Auth) mais o webhook do Mercado Pago. Prioridades medidas no dispatcher: `onRequestBeforeRouter` 33 (antes do `router_listener` 32), `onRequest` 31, `AccountRouteSubscriber` 29, webhook 29, CEP 28, `onResponse` 1, alteração de rota −2049.

**P7.2 — tags legadas (executado):** `kernel.event_subscriber` substituído por `event_subscriber` nos 6 serviços. O `RegisterEventSubscribersPass` do Core renomeia as duas tags de forma equivalente. Verificado: lista de listeners do dispatcher (classe, método e prioridade) idêntica antes e depois (9 entradas).

**P7.3 — subrequests:** os 4 listeners de request verificam `isMainRequest()`; agora há invariante no gate.

**P7.4 — DI:** listeners recebem dependências por serviço; nenhum `\Drupal::` em `src/` (já verificado em P5).

**P7.5 — purposes e rotas (executado no Homelab):** GET de administração em `conta` → 302 confiável para o host principal; POST na mesma rota → 404 (falha fechada, sem redirect entre Domains); `/dados` anônimo em `conta` → 403; rotas públicas do purpose errado → 404.

**P7.6 — eventos Conta/OAuth/Commerce:** `social_auth.user.login` e `social_auth.user.created` com um listener cada; webhook Mercado Pago guarda subrequests. Sem alteração de comportamento.

**P7.7 — deduplicação:** `entity.user.edit_form` é tratado por `AccountRouteSubscriber` (dono da conta e token one-time) e por `DomainPurposeRequestSubscriber` (purpose). A sobreposição é defesa em profundidade e foi mantida; fundir os dois mudaria a ordem que o padrão protege.

**P7-R — revisão:** gate PASS (361 checks; teste de mutação confirmou a falha ao reintroduzir a tag legada). Smoke de páginas públicas e logadas após a migração: mesmos códigos HTTP.

## 2026-10-08 — P6: cacheability e Render API (inventário e verificação no Homelab)

**P6.1 — inventário de saídas dependentes de Domain**
- Links (`#type => link`) e menus com `Url` do `DomainPurposeManager`: cobertos. `LinkGenerator` usa `toString(TRUE)` e propaga a metadata para o render.
- `DomainPresentationBuilder` (`home_url` como string): cobre `domain`, `languages:language_interface`, `url.site` e depende da entidade Domain do purpose.
- Tokens `[node:canonical]` e `[node:image]`: corrigidos em P6.2.
- Strings de `routeUrl()`/`pathUrl()` usadas em redirects (`TrustedRedirectResponse`) e em metatags: não são cacheadas; redirects não passam pelo cache de página. Canonical/og das metatags verificados por host (abaixo).

**P6.2 — tokens:** ver entrada anterior. Imagem continua **DEFERRED** (Runtime sem entidades `media`).

**P6.3 — conta privada (executado no Homelab):** duas sessões (uid 1 e uid 51) intercaladas em `/dados`: cada resposta contém apenas o próprio e-mail e nunca o do outro usuário.

**P6.4 — Views e LMS/Group (executado):** páginas `/` e `/wiki/busca?q=maconha` (wiki420) e `/` e `/meus-cursos` (cursos), anônimo e administrador intercalados, 3 rodadas: 0 divergências. Anônimo e administrador recebem páginas diferentes, como esperado (barra de administração e vínculos).
- Observação: em uma primeira passada, uma resposta divergiu de sua referência isolada e não se reproduziu em 3 rodadas seguintes. Não foi explicada; fica registrada como transitória.

**P6.5 — Form API e AccessResult:** revisão sem alteração. Acesso de entidade e cache por DomainPurposeManager/Domain já revisados em P4; formulários públicos (busca da Wiki, login) usam GET ou fluxo sem estado do usuário.

**P6.6 — contrato Portal → ACULTA420 (executado):** `preprocess_page` propaga a cacheability do `DomainPresentation` para o render do `page`. Canonical por host verificado intercalado: `aculta.toca.net.br` → `https://aculta.toca.net.br/`, `apoio` → `https://apoio.aculta.toca.net.br/`, estável em requisições alternadas. Homepages de 5 hosts intercaladas (3 rodadas): 0 divergências.

**P6-R — revisão:** gate PASS (357 checks). Nenhuma regressão identificada nas superfícies verificadas.

**Pendências declaradas:** imagem de token (sem media no Runtime); divergência transitória única em P6.4 não explicada; `preprocess_page` depende da ordem de render do tema (verificada por teste, sem teste automatizado).

## 2026-10-08 — P6.2: cacheability dos tokens de URL e imagem

- `[node:canonical]` lia o host/esquema da request (e a opção `https` em ambiente `local`) sem declarar contexto: metadata original trazia só `languages:language_interface`. Agora declara `url.site` (esquema+host+base) e `domain` (ambiente de alias ativo).
- `[node:image]` gera a URL a partir da entidade Domain do purpose e do ambiente de alias, e `pathUrl()` devolve `Url` sem metadata. Agora depende da entidade Domain do purpose (`getDomain()`) e declara `url.site` e `domain`.
- Saída dos tokens preservada: `[node:canonical]` de `wiki420` continua `https://wiki420.aculta.org/verbete/proibicionismo` antes e depois.
- Gate: invariantes de contextos e de dependência da Domain; mutation test confirmou a falha ao remover o contexto.
- Verificação de runtime: **parcial**.
  - CLI: metadata de cache medida antes/depois (`token_meta`).
  - Imagem: o Runtime não tem nenhuma entidade `media` nem nó com imagem; o ramo de imagem é verificado apenas estaticamente. **DEFERRED**.
  - HTTP do Homelab: **BLOQUEADO** — o PHP-FPM roda como `bdtgn`, mas `web/sites/default/files` pertence a `aculta:www-data` com ACL sem acesso para `bdtgn`. Todas as requisições web retornam 500 (inclusive `/`). Não alterei permissões; aguarda decisão do responsável pelo Homelab.

## 2026-10-08 — P5.7 / P5-R: revisão formal da fase P5

**Escopo revisado:** Views, EntityQuery/accessCheck, DI, storage, access e gates de P5.1–P5.6.

**Estático (executado):**
- Lint PHP de todo `src/` e dos arquivos alterados: 0 falhas.
- `\Drupal::` em `src/`: 0. Views wrappers ativos: 0 (a única ocorrência é texto de docblock). Cargas estáticas de entidade: 0. `getQuery()` sem `accessCheck` explícito: 0.
- `strict_types` ausente: 1 (`PortalHooks.php`, dívida carregada para P6, pois o arquivo é reescrito pelos hooks de cache/Domain).
- Subscribers `kernel.event_subscriber` legados: 6 (teto P1 mantido; redução é escopo de P7). Tag canônica `event_subscriber` presente: 1.
- Tetos do gate de locator, Views wrapper e carga estática: todos zerados.
- Gate `validate-aculta-portal-drupal11.php`: PASS, 355 checks.

**Validadores (executados, comparados com `main`):**
- PASS no branch: admin-domain-policy, payment-domain-policy, domain-presentation-contract, aculta420-foundation (corrigido nesta fase), aculta420-shell-contract, institution, aculta420-design-foundations, secrets-contract, gate Drupal 11+.
- FAIL também em `main` (sem regressão introduzida por P5): portal-commerce-security (apenas deriva de configuração no Runtime — 5 checks de `config/sync`), cross-domain-request-policy (host do Runtime fora do cookie compartilhado), admin-cleanup (Views retidas), final-drupal, final-contact, final-sitemap, home-carousel (exigem ambiente "local only").

**Runtime Homelab (executado):** HTTP de Conta (8 páginas), Wiki, Cursos e painel administrativo idênticos antes/depois de cada subfase, com normalização de IDs aleatórios; matriz de acesso e isolamento conforme P5.6.

**Pendências declaradas (não é PASS de homologação completa):**
- Sessão HTTP real de usuário comum: sem conta não administradora no Runtime (ver P5.6).
- Drift de Configuration Sync no Runtime: exige decisão de sincronização, fora do escopo autorizado (`cim`/`cex` não executados).
- `PortalHooks.php` sem `strict_types`: carregado para P6.

**Veredito P5:** concluída estaticamente e validada no Homelab nas superfícies tocadas; pronta para seguir para P6.

## 2026-10-08 — P5.5 / P5.6: storage contracts e matriz de acesso

**P5.5 — storage**
- `AccountCoursesManager` deixa de chamar `GroupMembership::loadByUser()` (carga estática) e usa o serviço público `group.membership_loader` injetado. O método estático delegava ao mesmo cache `cache.group_memberships_chained`; o wrapper expõe `getGroup()` e cacheability idênticos.
- Auditoria: todo acesso a storage em `src/` passa por `entity_type.manager` injetado ou pelo serviço de domínio. Não há `\Drupal::entityTypeManager()`/`entityQuery()` restantes.
- Gate: invariantes do serviço de membership e varredura recursiva proibindo locators estáticos de entidade. Mutation tests confirmaram que ambas as violações injetadas falham o gate.
- Homelab: `/meus-cursos` do administrador (1 vínculo LMS) idêntico antes/depois da troca; demais páginas de Conta idênticas.

**P5.6 — matriz de acesso (executada)**
- Matriz de rotas `aculta_portal.*` para anônimo, usuário autenticado comum (fixture não persistida, uid 999991) e administrador: contas privadas negadas a anônimos, administração negada ao comum, `/apoio` e demais páginas de Conta permitidas apenas a autenticados.
- Isolamento entre usuários: `EntityHooks::entityAccess` com rota `entity.user.edit_form` retorna FORBIDDEN para comum sobre outro usuário e sobre si mesmo; administrador segue neutro (permissão decidida pelo Core).
- Isolamento por Domain (HTTP): `/wiki` retorna 404 em `aculta`, `cursos`, `conta`; `wiki420` serve Wiki; `/painel-administrativo` retorna 403 a anônimo no principal e redireciona para o principal a partir de `conta`.
- `/user/N` e `/user/N/edit` retornam 404 em todos os hosts para todas as contas testadas (não há superfície de edição genérica).

**Lacuna declarada**
- O Runtime **não possui conta ativa não administradora** (verificado: 0 usuários). Sessões HTTP de usuário comum não foram testadas; a matriz de usuário comum é in-process. Testar a sessão real exige criar conta de teste no Runtime — pendente de autorização.
- Nota de correção: as primeiras sondagens usaram uid 51 como "usuário comum", mas ele tem papel administrator. Essas sondagens não provam isolamento de usuário comum e foram descartadas como evidência.

## 2026-10-08 — P5-strict: strict_types em todo src/ (exceto PortalHooks)

- Adiciona `declare(strict_types=1)` a 13 arquivos runtime (AccountShellBuilder, AuthIntegrationManager, SettingsForm, tags metatag). Dívida de tipagem reduzida de 14 para 1 (`PortalHooks`, a ser tratado na P5.4/P6 junto de seus hooks).
- Homelab: páginas de Conta (8) idênticas após normalizar apenas IDs aleatórios do toolbar; `/apoio` (Schema WebPage) e home com schema preservados; formulário de Apoio carrega.
- Gate: lista de dívida `strictTypesDebt` reduzida a `PortalHooks.php`.

## 2026-10-08 — P5.4-A/B/C/E + P5.6: DI dos controllers de Conta e guarda de Apoio

- `PortalController`: `\Drupal::service('plugin.manager.block')` (P5.4-A), `\Drupal::service('email_confirmer')` (P5.4-B) e `\Drupal::routeMatch()` (P5.4-C) → `BlockManagerInterface`, `EmailConfirmerManagerInterface` (dependência hard do `.info.yml`) e `current_route_match` injetados; helpers lazy `currentUser()`/`moduleHandler()`/`config()` → `current_user`, `module_handler`, `config.factory` explícitos. `strict_types=1` e parâmetros `UserInterface` tipados. O swap temporário do parâmetro `user` continua restaurado em `finally`.
- `SupportController` (P5.4-E): `strict_types=1`, `current_user` injetado; histórico de apoio nunca consulta `uid = 0` (que casaria com todos os checkouts de convidados).
- P5.6: rota `aculta_portal.support_my` ganha `_user_is_logged_in: 'TRUE'` (defesa em profundidade; hoje só `authenticated`/`administrator` têm `access aculta portal`, então o 403 anônimo observável não muda).
- `validate-portal-commerce-security.php`: fixture autenticada não salva recebe uid sintético (999990, nunca persistido), porque usuários sem uid são anônimos para `_user_is_logged_in`.
- Homelab (HTTP real, sessões uid 1 com Google vinculado e uid 51 sem vínculo): `/`, `/conta-interna`, `/dados`, `/dados/endereco`, `/conexoes`, `/seguranca`, `/apoio`, `/meus-cursos` com HTML idêntico antes/depois (exceto IDs aleatórios da toolbar Navigation e hashes de agregação CSS); anônimo segue 403.
- Gate: teto de locator e dívida de `strict_types` dos dois controllers zerados; injeções exactly-once, `finally`, guarda `uid > 0` e requisito de rota; mutation test confirmou que cada regressão injetada falha o gate.

## 2026-10-08 — P5.2-B / P5.3 / P5.4-E: WikiController

- `Views::getView()` (não deprecado oficialmente; dívida normativa ACULTA por ser locator estático) → storage `view` + `views.executable` injetados; `buildRenderable()` mantido para paridade de `#embed`, cache keys e propriedades. Display ausente passa a render vazio em vez de `TypeError` (retorno `NULL` em método `: array` com `strict_types`).
- `\Drupal::entityQuery('node')` ×2 → `getStorage('node')->getQuery()` com `accessCheck(TRUE)` explícito e justificado (listagem pública) e checagem `access('view')` por entidade preservada; filtros WIKI Domain/status/bundle inalterados.
- `\Drupal::service()`/`\Drupal::database()` ×4 e helpers lazy (`entityTypeManager()`, `currentUser()`) → DI explícita por `#[Autowire]`.
- Corrige bug visível: busca sem resultados exibia "1 verbete encontrado." junto do aviso de vazio (regra de plural pt-BR usa o singular para 0); a contagem agora só aparece com resultados visíveis e conta apenas itens acessíveis.
- Homelab: HTML de `/` (Views categorias/recentes + alterações recentes) e `/wiki/busca?q=maconha` idênticos antes/depois; buscas vazia e `%` diferem só pela remoção da contagem falsa; `/wiki` em MAIN segue 404.
- Gate: tetos de locator e wrapper Views do Wiki zerados; invariantes de query/Domain/access.

## 2026-10-08 — P5.2-A: catálogo de cursos com render element Views

- `views_embed_view()` é **DEPRECATED IN D11.4** e removido no D13 (CR https://www.drupal.org/node/3572594). `CoursesController` passa a retornar `'#type' => 'view'` para `courses_catalog`/`block_1`.
- Paridade: o wrapper retornava `NULL` sem acesso ao display; o controller mantém a checagem `access()` antes do render via storage `view` e `views.executable` injetados (`#[Autowire]`), preservando o fallback.
- Fallback ganha cacheability explícita (`user.permissions`, `config:views.view.courses_catalog`) e corrige o texto que exibia `\u00edvel` literal (string PHP com aspas simples).
- Homelab: HTML do display idêntico ao do wrapper (normalizado o `js-view-dom-id` aleatório); ramo sem permissão idêntico (`NULL` → fallback); `https://cursos.aculta.toca.net.br/` 200 com catálogo.
- Gate: teto de wrapper Views do controller zerado e invariantes de View/display/access/fallback.

## 2026-10-08 — P5.0: gates executáveis (pré-requisito do Bloco A)

- **O gate `validate-aculta-portal-drupal11.php` nunca havia executado**: erro de sintaxe PHP (escape `\\'` em string simples). Corrigido; agora roda de fato.
- Corrige interpolação acidental de `$entity` em string dupla no gate.
- Contagens exactly-once passam a valer na classe proprietária: `PortalHooks` (preexistente na `main`) implementa legitimamente `form_alter` (login/Profile/conta interna) e `metatags_alter` (rotas de apoio/noindex), escopos disjuntos de `FormHooks`/`EditorialHooks`. Totais do módulo ficam congelados (2/2/1/1) para detectar nova duplicação.
- Remove texto `#[Hook('metatags_alter')]` duplicado dentro de docblock em `PortalHooks` (sem efeito runtime).
- `validate-portal-commerce-security.php` e `validate-aculta420-foundation.php` liam o `.module` removido na P4-R e chamavam a função global removida na P3; apontados para `EntityHooks`, `TokenHooks` e o serviço `aculta_portal.form_callbacks`.
- Runtime Homelab: as 11 famílias de hooks OOP confirmadas registradas via `ModuleHandler::hasImplementations()`.

## 2026-10-08 — P5-extra-2: economia de tokens para agentes

- Política de roteamento proporcional ao risco: modelo econômico para pesquisa e tarefas simples, maior capacidade para revisão final e sistemas sensíveis.
- CLI Python somente leitura para sugerir tier e gerar contexto curto limitado em caracteres; seleção manual, sem APIs externas.
- Atualizados AGENTS, roadmap e handoff. Nenhuma alteração de runtime Drupal nem alteração de escopo DBTNG-2.

## 2026-10-08 — P5-extra-1: expurgo de portabilidade entre bancos

- Remove a responsabilidade de portabilidade de bancos do roadmap P5.3, P9.3 e hardening geral do `aculta_portal`.
- Define **DBTNG-2** como projeto independente e único responsável por conversão/migração/portabilidade entre bancos.
- Atualiza as regras para agentes, o handoff e o padrão normativo; preserva menções a bancos que apenas descrevem ambientes.
- Não altera código runtime, dados, configuração do banco nem testes.

## 2026-10-08 — Modernização Drupal 11+ Aculta Portal: documentação

- Roadmap detalhado P0–P10 e P5.x consolidado com próximos passos.
- Novo documento de continuidade entre agentes e referências atualizadas em AGENTS e READMEs.
- Somente documentação; comportamento runtime inalterado.

## 2026-10-08 — P5.1: SupportForm Entity API + DI

- Substitui `PaymentGateway::load('mercado_pago')` por storage `commerce_payment_gateway` via `EntityTypeManagerInterface` injetado.
- Substitui `\Drupal::service('plugin.manager.block')` por `BlockManagerInterface` injetado; mantém `FormBase::create()` com as dependências explícitas.
- Adiciona `strict_types=1`, reduz a zero os tetos específicos de static load e service locator de `SupportForm` e o remove da lista de dívida de tipagem.
- Preserva a decisão fail-closed de prontidão do gateway, a chamada do bloco Commerce Donation Flow, os textos e o fallback de indisponibilidade; nenhuma alteração de rota, cobrança ou persistência.
- Gate passa a exigir as três dependências e proibir ambos os acessos estáticos nesta classe.
- Views e demais controllers não são alterados; revisão funcional com Drupal runtime fica para o Homelab.

## 2026-10-08 — P4-R: revisão formal OOP/DI

- Revisa P4.1–P4.3 contra os contratos Drupal 11.4.x de `hook_form_alter()`, `hook_entity_access()` e `hook_entity_presave()`.
- Confirma paridade pré/P4 → pós/P4 para Change Mail, troca de senha, doação, redação de credenciais Mercado Pago, Wiki por Domain, password reset e fail-closed do gateway.
- Confirma os três hooks OOP exatamente uma vez, `strict_types=1`, zero `\\Drupal::*` e ausência de definições YAML redundantes.
- Confirma `AccessResultInterface` em entity access e preserva metadata específica: Domain/entity para Wiki e route/user/max-age 0 para o token one-time de reset.
- Remove o `aculta_portal.module` vazio; módulos Drupal não precisam manter `.module` quando não há implementação procedural legítima.
- Endurece o gate para exigir a ausência do `.module`, bloquear retorno dos três hooks procedurais e proteger callbacks/invariantes da P4.
- Atualiza o roadmap canônico com P5–P9 e a finalização Codex/Homelab.
- Nenhuma nova feature é introduzida; P5 ainda não foi iniciada.
## 2026-10-08 — P4.3: hook_entity_presave em OOP

- Migra `aculta_portal_entity_presave()` para `src/Hook/EntitySaveHooks.php` com `#[Hook('entity_presave')]`, seguindo a assinatura Drupal 11 com `EntityInterface`.
- Preserva o guard fail-closed do gateway Mercado Pago: o gateway só pode permanecer habilitado quando `MERCADOPAGO_PUBLIC_KEY` e `MERCADOPAGO_ACCESS_TOKEN` existirem no runtime.
- Mantém segredos fora de Git e Configuration Sync; nenhum storage paralelo ou persistência de credencial é introduzido.
- A classe usa `strict_types=1`, zero `\\Drupal::*` e não precisa de DI porque depende apenas do entity argument e do ambiente de processo já usado pela política existente.
- Remove a última função runtime procedural do `aculta_portal.module`; o allowlist procedural do gate passa a vazio.
- O gate exige `entity_presave` exactly-once, assinatura Drupal 11, strict_types, zero service locator, ausência de YAML redundante e invariantes do fail-closed.
- Próximo passo obrigatório antes da P5: P4-R, revisão formal de toda a fase P4.
## 2026-10-08 — P4.2: hook_entity_access em OOP + DI

- Migra `aculta_portal_entity_access()` para `src/Hook/EntityHooks.php` com `#[Hook('entity_access')]` e retorno `AccessResultInterface`, seguindo a assinatura do Drupal 11.
- Injeta `aculta_portal.domain_purpose`, `current_route_match` e `request_stack`; a classe não usa `\\Drupal::*` nem definição YAML redundante.
- Preserva o bloqueio de Wiki fora do purpose WIKI, a proteção da rota genérica de edição de usuário, a exceção do token one-time de reset e o bloqueio de edição do gateway Mercado Pago.
- Corrige a cacheability do ramo neutro de password reset válido: a decisão depende de route/user/request/session e agora carrega `route`/`user`, `cachePerPermissions()` e `max-age: 0`, sem alterar o resultado lógico neutro.
- Remove `cachePerPermissions()` desnecessário do bloqueio Wiki e o `max-age: 0`/permission context desnecessários do bloqueio incondicional do gateway, deixando metadata proporcional às condições reais.
- Reduz o legado procedural do `.module` de 2 para 1 função: apenas `aculta_portal_entity_presave()` permanece.
- O gate exige implementação exactly-once, assinatura Drupal 11, `strict_types`, DI explícita, zero service locator, ausência de YAML redundante e invariantes de Domain/conta/password reset/Commerce.
## 2026-10-08 — P4.1: hook_form_alter em OOP + DI

- Migra `aculta_portal_form_alter()` para `src/Hook/FormHooks.php` com `#[Hook('form_alter')]`, a API suportada pelo Drupal 11.4.x.
- Não usa `#[FormAlter]`: o atributo experimental foi removido no Drupal 11.2; o padrão ACULTA passa a tratá-lo explicitamente como deprecado/inválido.
- Injeta `current_route_match`, `current_user` e `string_translation` de forma explícita; a nova classe contém zero `\\Drupal::*`.
- Preserva os fluxos existentes de Change Mail, troca de senha, validação/labels de doação e redação visual das credenciais Mercado Pago.
- Mantém os callbacks P3 como `aculta_portal.form_callbacks:method` e atualiza o gate exactly-once para apontar ao novo `FormHooks`.
- Reduz o legado procedural do `.module` de 3 para 2 funções: `entity_access` e `entity_presave`.
- O gate exige assinatura Drupal 11, `strict_types`, DI explícita, ausência de service locator, invariantes funcionais e ausência de definição YAML redundante para a Hook class.
- Documentação normativa e instruções de IA passam a considerar práticas runtime legadas contrárias ao padrão moderno Drupal 11+ como deprecadas no projeto, salvo exigência upstream comprovada.
## 2026-10-08 — P2-R: revisão e hardening dos hooks OOP

- Revisa P2.1–P2.3 contra o sistema OOP de hooks do Drupal 11.4.x, Token API, Metatag e Library API.
- Confirma descoberta/autowiring automático de classes em `Drupal\\aculta_portal\\Hook` pelo Core 11.1+.
- Confirma paridade da migração: Token/Metatag/Node/Library mantêm os comportamentos anteriores; `metatags_alter` usa o contexto por referência conforme o contrato do Metatag.
- Endurece o gate para exigir os seis hooks migrados exatamente uma vez, `strict_types`, zero `\\Drupal::`, assinaturas-chave, DI explícita e invariantes de Token/Schema/CEP/Domain.
- Confirma `addCacheableDependency($settings)` como substituição correta da cache tag manual da configuração institucional.
- Registra para a fase de cache a dívida preexistente de URLs de token derivadas de Domain/alias/request: revisar dependência da entidade Domain e contexts `domain`/`url.site`; não é regressão introduzida pela P2.
- Nenhum comportamento runtime é alterado nesta revisão.
## 2026-10-08 — P1-R: revisão e hardening do baseline Drupal 11+

- Revisa o padrão P1 contra a API/documentação atual do Drupal 11.4.x.
- Confirma `#[Hook]` como abordagem preferencial para hooks de módulo e `event_subscriber` como tag canônica.
- Confirma que as classes `Drupal\\<module>\\Hook` são descobertas como serviços autowired pelo Core 11.1+.
- Endurece o gate para validar a versão realmente travada no `composer.lock`: Drupal Core deve permanecer em `>=11.3 <12`.
- Registra a depreciação de `hook_requirements()` em Drupal 11.3+ e impede sua reintrodução no `aculta_portal.install`; install/runtime/update requirements devem usar as APIs atuais.
- Mantém lifecycle hooks que o Core ainda exige como procedurais.
- Nenhum comportamento runtime do Portal é alterado nesta revisão.
## 2026-10-08 — P3.3: revisão e hardening da Form API

- Revisa P3.1/P3.2 contra o Form API e CallableResolver do Drupal 11.4.x, sem iniciar P4.
- Confirma que `#validate`, `#submit` e `#after_build` resolvem callbacks por `CallableResolver`, e que `service.id:method` é compatível com DI.
- Confirma paridade 1:1 dos oito registros migrados: cada callback procedural antigo foi substituído uma única vez no mesmo pipeline.
- Confirma a assinatura de `form_node_form_alter` com `$form`, `FormStateInterface` e `$form_id`.
- Endurece o gate para rejeitar nomes legados em todos os arquivos runtime envolvidos, exigir definição única do serviço, conferir as cinco dependências DI e bloquear registro duplicado de callback.
- Nenhum comportamento de formulário, redirect, validação, Commerce, Profile ou autenticação foi alterado nesta fase de revisão.
- Lint/runtime completo continuam reservados para a validação final no Homelab.

## 2026-10-08 — P3.2: callbacks de Conta/Commerce em serviço

- Migra os sete callbacks procedurais restantes de Form API para `PortalFormCallbacks`: confirmação de e-mail, redirect/after-build de senha, redirects de foto/endereço, sincronização de nomes do endereço Commerce e validação de doação.
- Atualiza todos os registros em `aculta_portal.module` e `PortalHooks` para o formato `aculta_portal.form_callbacks:method`.
- Expande a DI do serviço somente com as dependências exigidas pelos callbacks migrados: messenger, current_user, user.data, entity_type.manager e string_translation.
- Preserva redirects, mensagens, flags de Social Auth, labels/autocomplete de senha, sincronização Profile/Commerce e validação de valor de apoio.
- Remove sete funções globais; o legado procedural cai de 10 para 3 funções.
- `hook_form_alter()`, `hook_entity_access()` e `hook_entity_presave()` permanecem procedurais para a fase de DI/OOP do próprio hook.
- O gate passa a rejeitar nomes legados e exigir cada método/registro de serviço migrado.

## 2026-10-08 — P3.1: Form API editorial e CallableResolver

- Migra `hook_form_node_form_alter()` para `EditorialHooks::formNodeFormAlter()` com `#[Hook]`.
- Preserva os grupos, pesos e rótulos editoriais existentes para article/activity/project.
- Substitui o callback procedural `aculta_portal_validate_activity` pelo serviço `aculta_portal.form_callbacks:validateActivity`, suportado pelo CallableResolver do Drupal 11.3+.
- Cria `PortalFormCallbacks` mínimo com DI apenas de `string_translation`; callbacks de conta/Commerce permanecem procedurais por enquanto.
- Preserva as três validações condicionais de Activity: local presencial, URL online e término posterior ao início.
- Reduz o allowlist procedural do gate em mais duas funções e passa a exigir o hook OOP, o serviço e o callback serializável.
- Entity access/presave, form_alter de conta/Commerce, controllers e subscribers permanecem fora desta subfase.

## 2026-10-08 — P2.3: library_info_alter em OOP

- Migra `hook_library_info_alter()` para `EditorialHooks::libraryInfoAlter()` com `#[Hook]`.
- Preserva literalmente a extensão da biblioteca `cep_autocomplete/viacep`: o Portal continua adicionando apenas `aculta_portal/cep-address` como dependência.
- Mantém endpoint, client e cache do contrib intactos; nenhum JavaScript ou comportamento CEP é reimplementado nesta subfase.
- Reduz o allowlist procedural do gate em mais uma função e passa a exigir `library_info_alter` em `EditorialHooks`.
- Form API, entity access/presave, Commerce, controllers e subscribers permanecem fora desta subfase.

## 2026-10-08 — P2.2: hooks editoriais e Metatag em OOP

- Migra `hook_metatag_tags_alter()`, `hook_node_presave()` e `hook_metatags_alter()` para `src/Hook/EditorialHooks.php` com `#[Hook]`.
- Mantém Metatag/Schema Metatag como renderers oficiais e preserva o override de `PostalAddressTag`, publicação inicial, canonical de cursos/Wiki e localização estruturada de atividades.
- `metatagsAlter()` segue a assinatura documentada do Metatag, incluindo o contexto por referência.
- Remove o service locator de `DomainPurposeManager` dessa família e passa a usar DI explícita.
- Reduz o allowlist procedural do gate em mais três funções e passa a exigir os três hooks editoriais OOP.
- Form API, entity access/presave, Commerce, controllers e subscribers permanecem fora desta subfase.

## 2026-10-08 — P2.1: Token API hooks em OOP

- Migra somente `hook_token_info()` e `hook_tokens()` de `aculta_portal.module` para `src/Hook/TokenHooks.php` com `#[Hook]`.
- Substitui service locators dessa família por DI explícita para config, entity storage, Domain purpose/negotiator, request stack, file URL e tradução.
- Preserva os tokens institucionais/editoriais, URL canônica por Domain Source, aliases locais, imagem, autoria e BubbleableMetadata.
- Reduz o allowlist procedural do gate em duas funções e passa a exigir explicitamente os dois hooks OOP.
- Nenhuma outra família de hooks, Form API callback, controller, subscriber ou regra multidomínio é alterada nesta subfase.

## 2026-10-08 — P1: padrão Drupal 11+ e gate progressivo

- Drupal Core 11.3+ passa a ser o baseline arquitetural explícito do módulo.
- Adicionada documentação normativa para humanos, Codex, ChatGPT e demais agentes de IA.
- Adicionado gate estático progressivo que impede expansão da dívida conhecida de service locators, hooks procedurais, wrappers de Views, static entity loads, ausência de strict_types e tag legado de subscriber.
- Nenhum hook, controller, form, subscriber, fluxo Domain/Commerce/LMS ou comportamento runtime foi refatorado nesta fase.
- Compatibilidade formal com Drupal 12/13 continua condicionada à validação das dependências contrib.


Todas as mudanças relevantes do `aculta_portal` devem ser registradas aqui.

O Portal usa tags `portal-vX.Y.Z`.

## [Unreleased]

### Administração multidomínio e redirects cross-domain

- Centraliza `/painel-administrativo/**` no purpose MAIN: GET/HEAD acessados por subdomínio são canonicalizados para o mesmo path/query no Domain MAIN via `DomainPurposeManager`.
- Mantém requests administrativos mutáveis em host errado fail-closed para não repetir POST/CSRF entre Domains.
- Normaliza para 404 também os métodos mutáveis não aceitos pelo Router em paths administrativos de purposes secundários; o gate ordena a verificação do guard executável antes da resolução Wiki.
- Mantém os testes de redirects de Conta alinhados ao contrato atual: comparar URLs absolutas produzidas pelo `DomainPurposeManager`, não URLs relativas do roteador genérico.
- Restringe a rota Commerce de notificação Mercado Pago ao método POST no route subscriber do Portal; o gate verifica método e ownership MAIN sem alterar contrib.
- Preserva a exceção one-time do Core para `entity.user.edit_form` no fluxo de reset em ACCOUNT, agora aplicada de forma consistente antes e depois do RouterListener.
- Wrong-purpose público/funcional continua retornando 404; a regra administrativa não vira redirect genérico.
- Redirects intencionais entre purposes agora usam `TrustedRedirectResponse`; o retorno pós-login/OAuth roda antes do safety subscriber do Core e preserva headers/cookies ao trocar o target.
- Redirects de `AccountRouteSubscriber` passam por `DomainPurposeManager`, e requests mutáveis não são encaminhados MAIN → ACCOUNT.
- `user.page` usa redirect explicitamente confiável para a raiz ACCOUNT. A sessão compartilhada (`cookie_domain`) passa a ser requisito Runtime documentado/gateado por ambiente.
- Carrinho, checkout e pagamentos passam a ser centralizados em MAIN por `DomainRoutePolicy`: `commerce_cart.*`, `commerce_checkout.*`, callbacks browser-facing `commerce_payment.checkout.*`, notify e `commerce_donation_flow.*` não mantêm zona transacional paralela em SHOP/COURSES/SUPPORT.
- Links renderizados de checkout/pagamento são reescritos diretamente para MAIN quando possível; acesso GET/HEAD wrong-host ainda canonicaliza como defesa, enquanto métodos mutáveis falham fechado.
- Métodos mutáveis não aceitos pelo Router em paths de transação central também falham com 404 `private, no-store` em purpose secundário, usando candidatos de rota e metadata Drupal em vez de uma lista Commerce duplicada.
- Metadados `noindex` e a remoção de canonical/OG nas rotas transacionais usam `DomainRoutePolicy`, sem repetir prefixos Commerce em `PortalHooks`.

### Domain Presentation Contract

- 0.2-B.2 adiciona `DomainPresentationBuilder` e `DomainPresentation` como fronteira única e cache-aware entre Domain/Portal e ACULTA420.
- `PortalHooks::preprocessPage()` exporta apenas o array neutro `domain_presentation`; objetos Domain/Portal não chegam ao tema.
- Cacheability é mesclada pela Renderer API; o Portal não instancia SDCs `aculta420:*` nem antecipa regiões visuais ainda sem consumidor.
- Gate Runtime read-only cobre os sete purposes, shape, URLs, cache contexts/tags e dependência unidirecional; valida no Homelab as tags reais da entidade Domain (`config:domain.record.*`) em vez de assumir prefixo `domain:`. Runtime B.2 passou com 127 checks; os sete aliases, saída `NULL` para purpose desconhecido e smoke HTTP foram validados.

### Fronteira de autenticação e tema

- Centraliza a leitura de disponibilidade da configuração Google em `AuthIntegrationManager`, compartilhada pelo login e por Conexões.
- Fornece o wrapper semântico `aculta-auth-provider` ao redor do bloco Social Auth; ACULTA420 estiliza o contrato sem selecionar markup interno do contrib.

### Correções da fundação ACULTA420

- Portal passa a preparar breadcrumb e URL de transparência institucional sem criar dependência reversa no tema.
- Atualiza o relatório de requisitos para reconhecer `aculta420` como tema público/default.
- Move dados institucionais funcionais para `aculta_portal.settings`; o tema mantém apenas configuração de apresentação.
- Tokens institucionais ignoram UUID ausente/vazio antes da consulta de entidade, evitando condições SQL `uuid IN ()` durante configurações incompletas; o gate da Foundation protege essa ordem.
- O contato público é servido pelo Webform `aculta_contact` em `/contato`; o gate deixa de exigir publicação do node histórico `contact`, e o instalador não cria mais esse node nem o formulário legado do módulo Contact.
- Atualiza o teste de redirects de Conta para comparar URLs geradas pelas rotas vigentes, sem exigir slugs antigos (`/minha-conta/...`).
- Freelinking permanece ativo porque o formato de texto Wiki o utiliza; Composer foi atualizado para 4.0.3, corrigindo SA-CONTRIB-2026-213 (CVE-2026-107310; versões afetadas `<4.0.3`).
- Separa o gate ACULTA420 do gate Portal/Commerce e acompanha os hooks OOP do tema.
- Alinha o gate de autenticação à remoção de `user_registrationpassword` e à permanência de `username_enumeration_prevention`.

### Autenticação

- Sincroniza em pt-BR os rótulos dos CAPTCHA de login/cadastro/recuperação e as mensagens globais do Turnstile, sem alterar formulários protegidos, permissão `skip CAPTCHA`, provider ou credenciais.
- Formaliza o ACULTA Secrets Contract: Drupal Key/env permanece a interface
  única e o provisioning fica desacoplado do sistema operacional. Homelab e
  Hostinger compartilham Keys e nomes de variáveis; config exportada continua
  sem credenciais. A R0.4 validou o Secure Bootstrap Adapter no Homelab e
  limpou o storage bruto Google; Hostinger ainda não foi provisionada.
- Corrige o gate Portal/Security para mapear explicitamente Key ID, variável de
  ambiente e item `client_id`/`client_secret` da configuração OAuth.
- Define a prova OAuth no gate pela separação entre storage bruto vazio, Keys Environment, Key Configuration Overrides ativos e configuração efetiva coincidente, sem exibir valores.
- Preserva query string no destination de login/OAuth e adiciona `url.query_args` ao cache do menu, mantendo buscas, filtros e paginação após autenticação.
- Corrige o identificador do usuário externo no callback Google para `SocialAuthUserInterface::getId()`.
- Impede desconexão Google quando a conta Social Auth ainda não possui senha local escolhida, reutilizando o marcador `social_auth_password_unset` via DI de `UserDataInterface`.
- Desabilita os endpoints JSON de login/recuperação do Core neste site para impedir uma rota paralela que não passa pelo Form API/Turnstile; OAuth continua sendo o fluxo alternativo suportado.
- Preserva `user.page` somente como redirect técnico compatível com Core Navigation/recuperação de senha, sempre apontando para a raiz ACCOUNT sem expor o perfil genérico.
- Limpa destinos de login abandonados e preserva o Domain purpose original durante a transição login -> OAuth.
- Aplica Turnstile globalmente aos formulários em páginas públicas para visitantes anônimos; o papel `authenticated` ignora CAPTCHA em todos os formulários. Login e cadastro concluídos pelo OAuth não submetem os formulários Drupal protegidos pelo CAPTCHA.
- Corrige o indicador de conexão Google em Minha Conta: a entidade Social Auth é gravada com o plugin ID `social_auth_google`; `google` é apenas o nome curto da rota e não encontra os vínculos salvos.
- Exibe o e-mail da conta Google em Conexões, guardando o endereço retornado pelo Google nos dados adicionais da entidade Social Auth após callback autenticado; vínculos antigos sem esse dado oferecem atualização da conexão.
- Define `/oauth/{provedor}` como início do Social Auth e `/oauth/{provedor}/retorno` como callback; Google usa `/oauth/google` e `/oauth/google/retorno`.
- Faz links de login preservarem a última página visitada e o Domain purpose para login tradicional e OAuth; o callback retorna ao host da página anterior. Sem destino anterior, o retorno usa a raiz do Domain ACCOUNT. `user.page` é preservada apenas para compatibilidade com redirects do Core e redireciona para a raiz da Conta sem renderizar o perfil genérico; `/identidade` não é publicada.

### Processo

- Macrofase S encerrada no limite seguro sem Runtime; drafts funcionais continuam DEFERRED e a próxima execução começa em R0.

### Refatoração preparada

- S3.2A introduz `AccountCoursePresenter` como fronteira semântica entre LMS/Group e a apresentação da Conta, usa a chave compartilhada `action` da S3.2B sem repassar `score`/`finished` brutos, mantém o render atual e o tema inalterado até Runtime PASS; o controller de Cursos usa DI explícita e não depende dos helpers lazy de `ControllerBase`.
- S3.5 separa resolução de purpose do enforcement HTTP e evita mutação de Domain em URLs locais.
- S3.5 passou os gates Runtime em R1.2B; `pathUrl()` agora respeita o Domain purpose e o alias de ambiente.

### Roadmap

- Portal 0.18.1 adiciona a padronização transversal de slugs públicos amigáveis em português para todos os Domains/purposes, com redirects/canonical/sitemap e preservação das rotas técnicas upstream.

### Documentação

- S2 Static Portal Audit concluído sem alterar runtime.
- S2.1 documenta a matriz Minha Conta: SDC, integrações, AJAX e fallback.
- S3.2B define semântica compartilhada de status, ações, empty states e summaries sem antecipar SDCs não aprovados pelo tema.
- Mapa de refatoração classifica KEEP, REFACTOR, UPSTREAM/CONFIG e RUNTIME-SENSITIVE.
- Bootstrap Component Design System definido como destino da apresentação pública do Portal.

### Planejado

- Portal 0.10.0: fundação documental e disciplina de desenvolvimento.
- Portal 0.11.0: fundação do Fórum.
- Portal 0.12.0: participação do Fórum na Conta.
- Portal 0.13.0: hub integrado de participação.
- Portal 0.14.0: hub administrativo — especificação concluída.
- Portal 0.15.0: consolidação AJAX — especificação concluída.
- Portal 0.16.0: Search API — especificação concluída.
- Portal 0.17.0: engagement — especificação concluída.
- Portal 0.18.0: deduplicação — especificação concluída.
- Portal 0.19.0: hardening — especificação concluída.
- Portal 1.0.0: gates de release especificados; release ainda bloqueado por Runtime.

## 0.10.0 — Foundation

Em preparação.

Objetivo: organizar responsabilidades, fontes de verdade, módulos upstream,
AJAX, Fórum, Wiki, Revista, Loja, integrações Google, Bootstrap Component Design
System, testes, versionamento e roadmap antes de novas features.

O desenvolvimento opera temporariamente em modo GitHub-first / Runtime-last;
features executáveis preparadas sem Homelab permanecem draft até validação.

## Não versionado — revisão módulo x tema (2026-10-12)

- Regra permanente: **Código no Aculta Portal, Design no ACULTA420** (AGENTS.md, README do módulo, doc do tema, gate
  `scripts/validate-module-theme-boundary.php`).
- Migrado para o tema: shell da Minha conta (`templates/aculta-portal-shell.html.twig` em design), modal de termos,
  editor de foto, caixa de status do carrinho (template e CSS), CSS de Minha conta, CEP (número, tamanho) e recurso de
  layout do CEP (`js/address-layout.js`), relatório de requisitos e suporte. Mapa completo em
  `docs/portal/FRONTEIRA-MODULO-TEMA.md`.
- O módulo mantém fallback neutro (sem classes) nos templates migrados e o comportamento dos JS usa `data-portal-*`.
- Evidência: marcação da Minha conta 8/8 idêntica após normalização; roteiro de aceitação 10/10 com ACULTA420 e com
  Olivero (`docs/portal/TESTES-NAVEGADOR.md`, seção L). Captura de tela não executada.
- Migração de design concluída: nenhuma classe de design é escrita pelo módulo (`'class' =>` só `use-ajax`; `is-active` é
  estado). Os elementos marcam `data-portal-ui` com tokens; o ACULTA420 traduz (`src/Hook/PortalUiHooks.php`).
- Novos: `Render/PortalLinkElement` (link com token preservando href e cacheabilidade), templates neutros
  `portal-element`, `portal-search-trigger`. Breadcrumb do AJAX em `item_list`; JS usa `[data-portal-breadcrumb]`.
- Gate: `scripts/validate-module-theme-boundary.php` reprova classe de design no módulo e token sem correspondente no tema.
- Meus pagamentos removido da Minha conta (menu, sanfona e navegação AJAX). `/pagamentos` → 301 `/pedidos`; rotas de
  cadastro/edição/exclusão/padrão de meios de pagamento removidas (404); `PaymentMethodAddAccessCheck` removido.
- Caixa "Todos os nossos pagamentos são processados pelo Mercado Pago" em Meus pedidos (texto e link no módulo, config
  `aculta_portal.settings:mercado_pago_account_url`).
- Gate: seções 12 (ícones Bootstrap Icons) e 13 (pagamentos) em `scripts/validate-module-theme-boundary.php`.
## 0.4.0-dev.53 — F2: integração PDF.js global — 2026-10-16

Classificação MINOR. Instala `drupal/pdf` 1.3.0 e PDF.js oficial 6.4.299 em distribuição local Composer, com hash fixo;
adiciona builder reutilizável para Media document que exige access de Media, campo e File, MIME/extension PDF e scheme
public local, acumula cacheability e oferece fallback abrir/baixar. Não integra PDFPreview nem ativa configuração de
formatter/Media. Teste Unit de acesso negado PASS. Renderização pública no navegador e Runtime **DEFERRED**: o Drush
local aponta `http://default`, não o URI canônico de teste.

## 0.4.0-dev.54 — F2: adapter PDF.js no Portal — 2026-10-16

Classificação MINOR. Completa o adapter global ESM no Portal sobre as dependências Composer 1.3.0/6.4.299 já
registradas em 0.4.0-dev.53: builder de Media autorizado, cacheabilidade, fallback e renderer paginado. Unit 2/2 PASS;
browser smoke isolado PASS (PDF.js, worker e PDF amostra 14 páginas). Integração Drupal Runtime **DEFERRED**. Nenhum
display de Media foi alterado, etapa da F3.

## 0.4.0-dev.55 — F3: formatter global para Media document — 2026-10-16

Classificação MINOR. O display padrão de Media `document` usa o formatter do Portal: PDFs públicos
autorizados são apresentados pelo PDF.js compartilhado; outros documentos conservam o link Core, e PDFs
privados mantêm somente o fallback File protegido conforme o access atual. A integração com PDFPreview e
miniaturas fica DEFERRED: o ambiente não tem ImageMagick executável e a combinação estável PDFPreview 1.2 /
ImageMagick 5.x não é compatível. `RUNTIME STATUS: DEFERRED`; display não importado no Runtime.

## 0.4.0-dev.56 — extração do visualizador PDF para submódulo — 2026-10-16

Classificação MINOR. O serviço, interface, formatter, biblioteca JavaScript e testes do visualizador passam a
pertencer a `aculta_pdf_viewer`, sem dependência funcional de `aculta_portal` ou `aculta_lms`. Config Sync
habilita o submódulo e vincula o display Media `document` ao formatter. Documentação e contrato de hooks/classes
ficam no README próprio do submódulo. Nenhuma configuração foi importada no Runtime.

## 0.4.0-dev.60 — PV5: Group controla Media PDF de curso — 2026-10-16

Classificação MINOR. Adiciona integração opcional `aculta_pdf_viewer_group`, relação de Media
`document` com Group `lms_course` e permissão explícita para estudante e
educador. O módulo base do PDF Viewer permanece independente de Group. O acesso
por membership, revogação e entrega privada no Runtime segue DEFERRED; PDFs
privados de turma ainda não estão homologados. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.61 — formatos textuais próprios — 2026-10-16

Decisão de acesso: o papel `email_confirmed` recebe `usuario_aculta`; `authenticated` deixa de receber permissões de edição de texto. O corpo dos tópicos do Fórum aceita somente `usuario_aculta`.

A atualização `aculta_portal_update_11009` migra referências atuais e revisões de `basic_html` para `usuario_aculta` e de `full_html` para `revista_editorial`, preservando texto, revisões, autores e datas. Campos, views e Agreement foram alinhados. Os formatos e editores Core removidos deixam de ser exportados.

## 0.4.0-dev.110 — início da F6 do LMS: visão geral da sala de curso

- `aculta_lms` integra um índice de lições à página canônica existente do curso;
  acesso é revalidado pelo Group/LMS e as atividades usam rota nativa.
- Sem mudança de tema, entidades, memberships ou progresso.
- RUNTIME STATUS: DEFERRED; falta smoke test com membro, não membro e visitante.

## 0.4.0-dev.129 — declara a integração VVJS das galerias — 2026-10-18

- `aculta_portal` declara `vvjs` como dependência direta, pois a galeria de produto configura `views_vvjs`.
- `aculta_gallery` também declara `vvjs`; seu slideshow de álbum depende do estilo de View desse módulo.
- VVJS já fazia parte do conjunto Composer e estava habilitado no Runtime de teste; sem nova dependência Composer ou alteração de dados.
- `ProductGalleryHooks` documenta o estilo VVJS que a View entrega.
- RUNTIME STATUS: configuração da View e cache já smoke-tested; a atualização da metadata dos módulos foi seguida por cache rebuild.

## 0.4.0-dev.130 — registro seguro de patches Composer

- Criado o submódulo `aculta_patcher` para cadastrar, revisar, ativar, desativar e excluir patches de pacotes Composer pela UI administrativa nativa do Drupal.
- O exportador Drush exige pacote/versão exatos do `composer.lock`, valida o unified diff com `git apply --check` antes de gerar manifesto e artefatos versionáveis; aplicação permanece no build Composer.
- Adicionado patch para negar upload H5P que use bibliotecas locais restritas quando a conta não tem `create restricted h5p content types`; segue revisão e teste funcional antes de ativar H5P.
- RUNTIME STATUS: `aculta_patcher` habilitado no Runtime de teste após backup privado `/home/aculta/private/aculta/aculta-runtime-before-aculta-patcher-20261018.sql.gz.gz` (0600); tabela e entidade foram verificadas via Drush. H5P permanece desativado. A rota existe no Router, mas HTTP visual retornou 404 e fica DEFERRED. Nenhum arquivo de Core/contrib foi editado manualmente.

## 0.4.0-dev.194 — Fórum fora da listagem geral e correção do HTTP 500 — 2026-10-19

- O Fórum e seus comentários passam a ser itens próprios no menu administrativo
  `content`, no mesmo nível de Galerias. As páginas deixam de ser abas do
  overview genérico; os caminhos permanecem estáveis.
- A View administrativa volta a usar o campo de categoria com o handler válido
  de referência a entidade. O handler inválido anterior causava `TypeError`
  (`array + null`) e HTTP 500 quando havia resultados.
- RUNTIME STATUS: PARTIAL. Os dois configs do Fórum foram atualizados no
  Runtime após backup privado; uid 5 tem acesso às rotas e Views de tópicos e
  comentários renderizam pelo Render API (42.819 e 2.871 bytes). HTTP autenticado
  no navegador segue pendente. Nenhum conteúdo foi alterado.

## 0.4.0-dev.193 — navegação administrativa visível para Fórum e Revista — 2026-10-19

- Itens explícitos no menu administrativo de Conteúdo dão acesso às listagens do
  Fórum, aos comentários do Fórum como subitem e à área unificada da Revista.
- Acesso continua derivado das rotas/Views existentes; não foram criadas
  permissões novas nem caminhos alternativos.
- RUNTIME STATUS: PARTIAL. Após cache rebuild no Runtime, a árvore de menu e as
  três rotas foram confirmadas acessíveis para uid 5. A autenticação HTTP
  temporária gerada pelo Drush retornou 404; renderização no navegador segue
  pendente.

## 0.4.0-dev.191 — conteúdos administrativos de Fórum e Revista — 2026-10-19

- O Fórum público tem listagens separadas de tópicos e comentários; a fila de
  comentários fixa tipo, campo, entidade e bundle hospedeiro.
- A Revista reúne publicações e comentários em
  `/painel-administrativo/conteudo/revista`; o bloco de comentários é restrito
  ao purpose main e à permissão de moderação.
- LMS e Wiki têm contratos de administração independentes e só ativarão suas
  listagens com seus adapters e validação contextual de acesso.
- RUNTIME STATUS: PARTIAL. Views do Fórum foram carregadas no Runtime de teste;
  configuração da Revista aguarda smoke dedicado.


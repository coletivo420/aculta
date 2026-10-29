## 0.4.0-dev.92 — tokens neutros na fila de denúncias do Fórum (2026-10-29)

- `ReportQueueController` marca container e pager com `data-portal-ui`; o tema
  mapeia os tokens, sem classes de design no módulo. ACL, filas, links e cache
  permanecem no owner Portal/UGC.
- Unit do controller 3/13 e Unit de `PortalUiHooks` 12/83 PASS;
  `validate-module-theme-boundary.php` e `validate-portal-ui-docs.php` PASS.

## 0.4.0-dev.91 — instalação limpa e rollback do módulo (2026-10-29)

- Novo `AcultaUserInstallRollbackKernelTest` instala o `aculta_user` a partir de
  zero sobre as dependências reais de User, Profile, Node e Comment: schema
  próprio (`aculta_user_agreement`, `aculta_user_nickname`), as quatro entidades,
  os sete profile types, as field storages, serviços, rotas e permissões.
- O teste cobre a proteção de rollback: o `uninstall` é recusado enquanto houver
  registro em `aculta_friendship`; removido o registro, o `uninstall` derruba
  tabelas, entidades, configuração simples e profile types.
- `profile.type.*` (config/install e `config/sync`) passam a declarar
  `dependencies.module: aculta_user`, para que o rollback remova os bundles em
  vez de deixá-los órfãos. As field storages do Profile não declaram o módulo e
  permanecem após o uninstall: o Core recalcula as dependências de field storage
  e elas guardam dados de membros.
- Validação: Unit 142/552 PASS; Kernel do módulo 42/570 PASS (18 depreciações de
  Core/contrib); PHPCS Drupal/DrupalPractice e `php -l` do teste PASS. Runtime
  intocado; `config:import` das dependências novas pendente.
- A instalação limpa da cadeia completa Portal↔Mercado Pago continua fora do
  escopo do `aculta_user` e depende de janela operacional.

## ADM2 — lista administrativa mínima de pessoas

- Criada a lista restrita a `administer users`, com nome de exibição, apelido e situação da conta; sem e-mail, documento ou endereço.
- O nome usa apenas `field_first_name` do perfil e nunca recorre ao nome de conta, que pode ser um e-mail.

## 0.4.0-dev.90 — fallback nome + acervo e autoria da Wiki

- `AuthorIdentityResolver::resolveFallback()` e `AuthorCardBuilder::buildFallback()`
  montam o cartão de quem não tem perfil público: nome de exibição + avatar do
  acervo, sem link, foto, bio ou redes. O roster da turma usa esse fallback, então
  ninguém sai da lista por falta de perfil público (decisão de 2026-10-27).
- Wiki: confirmado que `node--wiki-entry--full` e `--teaser` não exibem autor. A
  autoria aparece somente na discussão do verbete, pelo cartão `author_wiki`.
- `AVATAR-PADRAO.md` e `AUTHOR-CARD-VARIANTS.md` registram as decisões.
- Validação: PHPUnit Unit 142/552 PASS (1 depreciação PHPUnit); Kernel
  `ClassRosterVisibilityTest` 1/33 PASS (37 depreciações upstream); gates Portal
  (1112), foto (INV-FOTO-1 a 10), SDC (632), design foundations, browser
  validators (21), Foundation (345) e shell-contract (11) PASS. RUNTIME STATUS:
  DEFERRED para o roster visual (o Runtime não tem turma com roster).

## 0.4.0-dev.89 — campo de Views para cartões de perfil público (Fase 3)

- Novo campo de Views `aculta_user_author_card`: renderiza o cartão de autor de cada
  conta com perfil público explícito (`field_perfil_publico`). Opções: variante e
  tamanho `sm`, `md`, `lg`. O hook `views_data_alter` declara o campo virtual em
  `users_field_data`; sem ele o Views instancia um handler broken.
- `AuthorCardBuilder::build()` aceita `$size` (padrão `md`), repassado ao tema.
- A view "Perfis públicos" (`views.view.perfis_publicos`, `/membros`) foi criada e
  exportada para `config/sync`: base `users_field_data`, relacionamento `profile`,
  filtros de perfil público e apelido, 24 por página, UI Styles no display.
- O campo carrega a conta pelo `uid` da linha quando o cache da query não traz a
  entidade; sem isso, os cartões saíam vazios após o cache do Views.
- A inicial do nome foi removida dos chamadores: `PublicProfileController`,
  `PrivateMessagePresentationHooks` e o template do cartão usam apenas a foto ou o
  acervo. O acervo é componente obrigatório e o último fallback.

Validação: PHPUnit Unit 141/546 PASS; Kernel do roster 1/30 PASS; gates Portal
(1112), foto (INV-FOTO-1 a 10), SDC (632), design foundations, browser validators,
Foundation (345) e shell-contract (11) PASS. Verificação de navegador no worktree:
view, perfil, Conta, Mensagens e Revista/Wiki com ACULTA420 e Olivero; Fórum e
roster/sala/feedback do LMS ficaram DEFERRED por falta de alias local e de turma.
RUNTIME STATUS: PARTIAL.

## 0.4.0-dev.88 — avatar padrão único nos cartões e no perfil (Fase 1)

`AvatarResolver` passa a ser a única regra de avatar público: foto liberada pela
policy, depois o avatar ilustrativo do acervo, e por último NULL. A inicial do nome
não é usada em nenhum momento; o acervo é componente obrigatório e o fallback de
quem não tem foto. `AuthorIdentityResolver` (cartões de autor em fórum, revista, LMS
e wiki) e `PublicProfileController` (Conta/perfil) usam o mesmo serviço, então o site
inteiro se comporta igual sem foto. Sem mudança de config, permissão ou campo.

Validação: teste unitário `AvatarResolverTest` (contrato sem foto e sem acervo);
PHP lint PASS nos arquivos alterados. PHPUnit, gates e Runtime não executados nesta
sessão (sem `vendor/`). RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.87 — 10013 conta sem bloquear por perfil sem dono (2026-10-26)

- `NicknameRegistry::rebuild()`: perfil sem dono (uid ≤ 0) e vazio é ignorado e não bloqueia. Perfil sem dono com apelido não reivindica índice, não é gravado e é contado à parte (`orphan_with_nickname`). Perfil com formato de apelido inválido não é normalizado nem gravado: fica sem claim (`ignored_invalid_format`).
- O rebuild bloqueia só por conflito real entre UIDs (`slug_conflicts`). Falha de banco ou transação propaga e aborta sem índice parcial.
- Retorno do update 10013: contagens (indexados, ignorados sem dono, ignorados vazios, órfãos com apelido, formato inválido). Sem apelidos nem uids.
- Testes Kernel: perfil sem dono vazio não bloqueia; perfil sem dono com apelido não reivindica; repetir o rebuild é idempotente; os testes existentes de conflito entre UIDs continuam passando (13 testes / 112 asserções).

## 0.4.0-dev.86 — reconciliar Profiles com apelidos divergentes

- No rebuild do índice 10013, o apelido não vazio do Profile com maior `changed` define o valor canônico por UID; timestamp empatado usa o maior ID como desempate determinístico. Profiles de papel do UID são sincronizados dentro da mesma transação. Apelidos vazios não substituem um apelido explícito.
- Colisões de slug entre UIDs e apelidos inválidos continuam bloqueando o rebuild antes de alterar claims; a migração reporta somente contagens agregadas. A saída do update inclui total de UIDs, Profiles reconciliados e conflitos de mesmo UID.
- User Unit: 140 testes / 543 assertions PASS. `NicknameRegistryKernelTest`: 10 testes / 88 assertions PASS em MariaDB dedicado; inclui criação do índice pelo update 10013, reconciliação do apelido mais recente e preservação de claims existentes no conflito entre UIDs. PHPCS Drupal/DrupalPractice e PHP lint focais PASS.
- Leitura agregada MAIN encontrou 26 Profiles de papel, 15 UIDs com apelido, 11 sem apelido e nenhum conflito de mesmo UID ou slug entre UIDs. O update não foi executado no Runtime; instalação efêmera completa continua pendente.

## 0.4.0-dev.85 — testar rollback da projeção de apelidos

- O Kernel simula falha de persistência depois de executar a sincronização do apelido e reverte a transação. A claim anterior permanece; a nova claim e a atualização do Profile irmão são desfeitas.
- `NicknameRegistryKernelTest`: 8 testes / 65 assertions PASS em MariaDB dedicado, incluindo a corrida concorrente em dois processos. PHPCS Drupal/DrupalPractice e PHP lint PASS.
- Nenhum update foi executado no Runtime; instalação efêmera e validação do update 10013 permanecem pendentes.

## 0.4.0-dev.84 — validar concorrência da claim SQL em MariaDB

- O Kernel usa dois processos independentes; ambos confirmam que o slug está livre antes de liberar as inserções. A chave única MariaDB aceita uma claim e rejeita a concorrente.
- O harness carrega explicitamente as classes da branch testada e o Core/MariaDB real, sem depender do código do checkout compartilhado. Resultado: 7 testes / 55 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint da fixture PASS.
- Rollback em falha de save e instalação efêmera do update 10013 continuam pendentes. Runtime não foi alterado.

## 0.4.0-dev.83 — índice SQL de slugs públicos por UID

- `aculta_user_nickname` é uma projeção derivada com UID como chave primária e slug canônico único. Profile permanece fonte de verdade; presave reivindica o slug dentro da transação de save, sincroniza Profiles de papel do mesmo UID e libera o slug anterior no rename. `#N` e `-N` convergem para a mesma chave.
- O update 10013 cria a tabela e valida/reconstrói as claims; conflitos entre UIDs, entre Profiles do mesmo UID ou valores inválidos bloqueiam o rebuild antes de substituir dados. A rota pública resolve UID pelo índice e busca o Profile principal; claims são liberadas após exclusão do último Profile de papel.
- User Unit: 140 testes / 543 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e lint focais PASS. Kernel `NicknameRegistryKernelTest`: 6 testes / 44 assertions PASS em SQLite `:memory:` isolado. A primeira tentativa MariaDB falhou no setup por fixture sem `filter`; o fixture foi corrigido, mas a repetição MariaDB aguarda o banco compartilhado que está em uso pelo agente Index. SQLite não substitui a evidência MariaDB. Update 10013 não executado no Runtime.

## 0.4.0-dev.82 — colisões OAuth e slug separado do nome exibido

- Colisões de apelidos geradas na criação automática usam apresentação `nome#2` e slug público `nome-2`. A forma com `#N` também pode ser digitada manualmente, desde que o slug esteja livre; formulários apenas validam e rejeitam conflito, sem autoajuste.
- A rota `/u/{apelido}` resolve o slug canônico para a forma exibida. Apelidos manuais `nome-2` e `nome#2` disputam o mesmo identificador público. O usuário interno continua com UID como referência.
- `AccountCreationHooksTest` confirma OAuth `nome-2` → apelido `nome#2`; Unit focal: 9 testes / 67 assertions PASS; suíte completa User: 140 testes / 542 assertions PASS (1 depreciação PHPUnit). PHPCS Drupal/DrupalPractice e PHP lint dos arquivos tocados PASS. Nenhuma atualização do Runtime foi executada.

## 0.4.0-dev.79 — marcador Growroom no cartão de autoria

- A primeira implementação aplicou `-gr` no builder genérico. Foi substituída em dev.80 após identificar consumidores de comentários compartilhados fora do Fórum; a versão vigente limita o sufixo aos adapters de conteúdo Fórum.
- Teste focal do formatador: 1/1 PASS; PHPCS e lint focais PASS na implementação dev.79.

## 0.4.0-dev.80 — restringe marcador Growroom aos adapters do Fórum

- `AuthorCardBuilder::buildLegacyForum()` acrescenta o marcador `-gr` para tópicos, posts e comentários do Fórum; o método genérico `buildLegacy()` mantém o nome sem alteração nos demais contextos. O perfil legado não recebe rota individual `/u/`; o memorial coletivo `/u/lendashistoricas` mantém o nome original sem sufixo. Nenhuma entidade ou conta é criada.
- Unit focal: 1 teste / 1 assertion PASS; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Sem importação de acervo ou alteração no Runtime.

## 0.4.0-dev.78 — persistir o apelido aparado no Profile legado

- O validador de `/dados` e outros forms Profile já comparava `trim(apelido)`, mas deixava o valor original no Form API. Espaços externos podiam ser gravados mesmo que a identidade pública use o texto aparado. O hook agora substitui o valor do widget pela forma aparada depois de formato e colisão aprovados.
- User Unit: 134 testes / 518 assertions PASS (1 depreciação PHPUnit); Kernel completo 25/361 PASS (5 depreciações Domain/Core/contrib); teste focal de validação 4/18 PASS. PHPCS e lint dos arquivos alterados PASS. Sem alteração Runtime.
- A concorrência entre cadastros continua sem constraint de armazenamento e segue como pendência independente.

## 0.4.0-dev.77 — limpeza de avatar coordenada pelo Portal

- `AvatarAssetCleanup` delega remoções de uploads temporários e avatares órfãos a `aculta_portal.unused_file_cleaner`, que verifica File Usage, Entity Usage, referências estruturadas e embeds antes de solicitar remoção física ao Image. Sem autorização clara, o arquivo permanece.
- Os contadores reportam somente exclusões confirmadas (`Deleted`). A retenção de uma hora para temporários e a política de avatares órfãos permanecem.
- `AvatarAssetCleanupCoordinatorTest` exercita o coordenador real com dependências simuladas: Portal inspeciona referências antes da exclusão. User Unit completa: 133 testes / 512 assertions PASS (1 depreciação PHPUnit); Kernel: 25/361 PASS (5 depreciações contrib). PHPCS e lint alterados PASS.

## 0.4.0-dev.76 — alinhar validação do apelido no formulário Profile legado

- `ProfileNicknameValidationHooks` aplica `AccountNames` e `NicknamePolicy` aos formulários Profile de papéis que expõem `field_nickname`, incluindo `/dados`, que o Portal mantém por compatibilidade. Um apelido legado inalterado não bloqueia a edição de outros dados.
- O serviço continua responsável pela verificação compartilhada; o Portal não ganhou uma regra paralela nem uma dependência circular.
- Unit User: 132 testes / 504 assertions PASS (1 depreciação PHPUnit). Kernel User: 25 testes / 361 assertions PASS (5 depreciações Core/contrib). PHP lint e PHPCS Drupal/DrupalPractice nos arquivos novos PASS. A verificação continua sujeita a corrida entre gravações simultâneas; garantia transacional no armazenamento permanece pendente de desenho e teste isolado.
## 0.4.0-dev.75 — identificar cada transição de amizade

`FriendshipChangedEvent` recebe um `transitionId` aleatório e imutável por
instância, sem dados pessoais ou dependência do Messenger. Isso distingue
transições com o mesmo timestamp de segundos; o adapter pode manter a chave
idempotente quando o mesmo objeto de evento é reprocessado.

Validação: Unit User **129/492 PASS**, uma depreciação PHPUnit preexistente;
Unit Messenger **282/2.308 PASS**; Kernel de integração de amizade **1/23 PASS**
no MariaDB dedicado, com duas depreciações de discovery de `message`; PHPCS e
PHP lint PASS. Runtime não executado.

## 0.4.0-dev.74 — editor independente de apelido no Studio (2026-10-25)

- `NicknameForm` usa Form API para alterar exclusivamente `Profile.field_nickname` do usuário atual, aplicando `AccountNames::isValid()` e retornando a `/studio/perfil`.
- `NicknamePolicy` centraliza busca de colisões em perfis de papel e agora também atende `AccountCreationHooks`. `PublicProfileLink`, `PublicProfilePolicy`, `/u/{apelido}`, rotas do legado e schema permanecem.
- Testes Unit de apelido inválido/reservado/duplicado e política escritos, mas **não executados**. Conflitos de escrita simultânea, permissão, privacidade, cache e links públicos requerem Kernel/Functional/Runtime.

## 0.4.0-dev.73 — corrigir conflito de propriedade em FormBase (2026-10-24)

- Os formulários de perfil usam `currentRouteMatch` em vez de redeclarar a
  propriedade herdada `$routeMatch` como `readonly`. O PHP 8.4 encerrava o
  bootstrap durante o rebuild do container, bloqueando a recuperação do site.
- PHP lint PASS. O rebuild do Runtime e o smoke HTTP foram executados após a
  correção; sem alterações de dados ou configuração persistida.

## 0.4.0-dev.72 — mesmo Form API em Perfil Studio independente (2026-10-24)

- `AccountBioForm`, `SocialLinkForm` e `ProfileVisibilityForm` detectam
  as rotas `aculta_studio.profile_bio`, `profile_social` e
  `profile_privacy`, mantendo submit, validações, entidades e políticas
  proprietárias. As rotas e comportamentos originais de Minha Conta
  permanecem disponíveis para comparação.
- Sem novos dados, fields ou aliases; perfil público `/u/{apelido}` intacto.
  Testes/Runtime não executados nesta edição.
- Referência: `docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md`.

## 0.4.0-dev.71 — remoção de aliases temporários em desenvolvimento (2026-10-24)

- Removidos aliases legados de troca de e-mail /seguranca/email e /seguranca/email/{user}; removido controller de redirecionamento órfão. E-mail e segurança continuam no Portal e nas suas rotas legítimas; /u/{apelido} preservado. Testes não executados neste incremento.
- Regra transversal: `AGENTS.md` e `scripts/validate-no-temporary-route-aliases.php`.

## 0.4.0-dev.70 — evidência publicada por contexto

- Implementados `PublishedParticipationLookupInterface`, o valor agregado
  `PublishedParticipationEvidence` e o dispatcher `PublishedParticipationLookup`.
- O dispatcher não combina contextos; ausência/duplicidade de provider e
  exceção retornam evidência indisponível, `max-age: 0` e indicação para exigir
  revisão. O resultado não contém entidade, texto ou identidade do conteúdo.
- Wiki fornece o provider de `wiki_entry`. As APIs e o comportamento atuais do
  Fórum permanecem sem migração. Testes: User Unit 123/439; caracterização
  Kernel 9/129; Fórum Kernel 1/39.

## 0.4.0-dev.69 — referências ao bundle Revista canônico

- Feed pessoal de publicações e variante de autoria passam a usar `revista`; removida a consulta quebrada ao bundle aposentado `article`.

## 0.4.0-dev.68 — caracterização das políticas compartilhadas (XK 0.1-C1) — 2026-10-22

- `ParticipationRules`, `ParticipationEditWindow`, `EditWindowExemptionInterface`
  e os hooks consumidores ganharam testes de caracterização Unit e Kernel,
  sem alteração de código de produção, DI, configuração ou permissões.
- `docs/USER-PART-0.1-C1-CHARACTERIZATION.md` registra contratos, matriz de
  testes, cacheability, falha conservadora e pendências conhecidas.
- `PublishedParticipationLookupInterface` permanece documentada como candidata,
  não implementada. Nenhum Runtime foi executado.

## 0.4.0-dev.67 — contrato público de associado ativo para Group — 2026-10-22

- `AssociadoMarker` implementa `ActiveAssociateInterface`
  (`src/Associado/ActiveAssociateInterface.php`): `isActiveAssociate()` responde
  pela situação `ativo` de `aculta_associado` e `associateCacheTags()` devolve
  `aculta_associado_list` e `user:<uid>`.
- Novo alias de serviço `aculta_user.active_associate` aponta para
  `aculta_user.associado_marker`; consumidores (Fórum restrito via Group) passam
  a depender só do contrato, sem papel `associado` e sem cópia de situação em
  Group.
- README e `docs/ASSOCIADO.md` documentam o contrato e o papel do consumidor.
  Testes desta frente são Unit e ficam no Fórum; nenhum Runtime foi alterado.

## 0.4.0-dev.66 — fila agrega denúncia de post revisionável — 2026-10-21

- `ReportQueue` carrega flaggings de `aculta_forum_post`, em lotes e sem
  dependência do módulo Fórum; valida o tópico hospedeiro e seu Domain purpose
  antes de agrupar, e ignora posts/autores históricos.
- Serviço recebe `DomainPurposeManager`; PHPCS/lint e gates estáticos PASS.
  Sem import de configuração ou alteração no Runtime.

## 0.4.0-dev.65 — posts revisionáveis na atividade pessoal — 2026-10-21

- A aba Comentários de Minha Conta combina Comments públicos e posts
  revisionáveis publicados pela própria pessoa, com ordenação cronológica e
  revalidação de acesso no purpose `forum`. A entidade é detectada quando
  disponível, sem dependência circular `aculta_user` → `aculta_forum`.
- Links de posts apontam para `forum-post-{id}` na página canônica do tópico e
  identificam assunto e tópico; nenhuma outra atividade de pessoa é exposta.
- Validação estática concluída; testes e smoke autenticado ficam pendentes.

## 0.4.0-dev.64 — compartilha autoria com posts revisionáveis do Fórum — 2026-10-21

- `DiscussionCommentAuthorHooks` apresenta posts `aculta_forum_post` pelo `AuthorCardBuilder`: identidade User usa `author_forum`; `legacy_author` continua no cartão histórico, sem conversão para conta.
- A leitura do corpo e a configuração do view mode permanecem no `aculta_forum`; `aculta_user` entrega somente o slot de autoria neutro e cacheável.
- Integração preparada para a leitura revisionável; nenhum Runtime foi alterado.

## 0.4.0-dev.63 — registra DI dos hooks de janela de edição — 2026-10-21

- `ParticipationEditWindowHooks` agora é serviço do módulo com a política central injetada. Sem o registro, o `ClassResolver` tentava instanciar o hook sem argumentos e causava HTTP 500 nas rotas do portal.
- A janela retorna `neutral` enquanto o prazo está aberto; ela só restringe após o vencimento e não concede edição sem a permissão do handler da entidade.
- Sem campo/timestamp migrado, preserva o acesso-base até a migração ser aplicada; não bloqueia comentários existentes antes do update de dados.
- Rebuild de cache no Runtime de teste após confirmar `aculta_runtime`; conta retornou 403 anônimo na rota protegida (sem 500), sem novo erro PHP. PHP CLI 8.4.26; 8.5 não validado. Runtime status: correção de código aplicada no Homelab; validação autenticada pendente.

## 0.4.0-dev.62 — desacopla decisão de primeira moderação do Comment — 2026-10-21

- `ParticipationRules::requiresFirstDiscussionModeration()` expõe a política
  compartilhada para entidades de discussão futuras. Mantém configuração,
  papéis isentos e aprovação anterior como fonte única; LMS continua fora do
  hook de Comment e do workflow público do Fórum.
- `ParticipationHooks` usa a nova API sem alterar a política observável dos
  Comments compartilhados.
- Kernel `PublicDiscussionModerationKernelTest`: cobertura da decisão genérica
  para conta nova, participante com post publicado e opção administrativa
  desligada. RUNTIME STATUS: DEFERRED.

## 0.4.0-dev.61 — aceite único: sem formulário de rastreamento e ledger só com os Termos — 2026-10-21

- Removido `AcultaUserAgreementTrackingConsentForm` e a rota `/configuracoes/rastreamento-de-e-mail`. Não há mais segundo aceite.
- `AcultaUserAgreementLedger` grava e lê só `aculta_user_agreement_terms`. Outro tipo é recusado na gravação e responde falso na leitura.
- Eventos `ConsentRevokedEvent` (`aculta_user.consent_revoked`) e `AccountDeletedEvent` (`aculta_user.account_deleted`), para os módulos que guardam dado ligado à conta. Sem consumidor ainda.
- Migração legada do `aculta_user.install`: só o aceite único entra no ledger. Ambiente de teste, sem migração de dados.
- Regra e gate: `docs/portal/ACEITE-UNICO.md`, `php scripts/validate-single-acceptance.php`.



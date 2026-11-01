# USER-PART 0.1-C1 — Caracterização das políticas compartilhadas de participação

**Data:** 2026-10-22 · **Estado:** caracterização concluída; nenhuma mudança funcional, de DI ou de Runtime.
**Base:** [Inventário USER-PART 0.1-A](USER-PART-0.1-A-CROSS-KERNEL-INVENTORY.md) e [ADRs XK 0.1-B](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md).

Este marco fixa por testes o comportamento atual de `ParticipationRules`,
`ParticipationEditWindow`, `EditWindowExemptionInterface` e dos hooks que os
consomem. Nenhuma classe de produção foi alterada. Os testes são a referência
para qualquer refatoração posterior: se um deles mudar de resultado, a mudança
é comportamental e precisa de decisão explícita.

## Escopo e commits

| Etapa | Entrega | Commit |
| --- | --- | --- |
| C1-A | `ParticipationRules`: Unit + Kernel | `512fe60d` |
| C1-B | `ParticipationEditWindow`: Unit | `f9e579c0` |
| C1-C | Contratos e hooks: isenção LMS + hooks da janela | `e6a4dc4d` |
| C1-D | Matriz Kernel de isolamento, segurança e cache | `aa55a84f` |
| C1-E | Documentação e índices (este arquivo) | fase atual |

## Contratos caracterizados

### Serviços e DI

| Serviço | Classe | Dependências |
| --- | --- | --- |
| `aculta_user.participation_rules` | `ParticipationRules` | `config.factory`, `entity_type.manager` |
| `aculta_user.participation_edit_window` | `ParticipationEditWindow` | `config.factory`, `datetime.time`, `!tagged_iterator aculta_user.edit_window_exemption` |

Consumidores atuais:

- `Drupal\aculta_user\Hook\ParticipationHooks` (`#[Autowire]` do primeiro serviço).
- `Drupal\aculta_user\Hook\ParticipationEditWindowHooks` (registrado em
  `aculta_user.services.yml` com o segundo serviço).
- `Drupal\aculta_user\Hook\ForumModerationHooks` (recebe a política por
  construtor; consome `EmailConfirmationPolicy`).
- `Drupal\aculta_forum\Hook\ForumPublicationTimeHooks`
  (`#[Autowire]` do primeiro serviço).
- `aculta_forum_lms.edit_window_exemption` (`LmsEditWindowExemption`), única
  implementação de `EditWindowExemptionInterface`, marcada com a tag
  `aculta_user.edit_window_exemption`.

Hooks são registrados por atributo de classe; não há entrada de serviço
duplicada para eles. A isenção declara a prioridade na própria tag
(`priority: 0`); a janela percorre da maior para a menor prioridade, a primeira
isenção permitida vence e todas são avaliadas antes de negar.

### `ParticipationRules`

Métodos públicos cobertos: `settings()`, `settingsCacheTags()`, `isExempt()`,
`requiresFirstDiscussionModeration()`, `isNonLmsDiscussionComment()`,
`hasPublishedDiscussionComment()`, `hasPendingDiscussionComment()`,
`firstDiscussionCommentPending()`, `publishedPublicForumCommentCount()` e a
constante `DISCUSSION_COMMENT_TYPE`.

Comportamento fixado:

- Padrões de configuração (`papeis_isentos` = `content_editor`, `administrator`;
  `topico_comentarios_minimos` = 10; moderação e bloqueio ativos) e merge de
  chaves parciais.
- Isenção é interseção de papéis, não id de conta; lista vazia não isenta
  ninguém.
- Moderação desligada ou conta isenta não executam query.
- Evidência publicada inclui comentário publicado (inclusive LMS) e, durante o
  cutover, `aculta_forum_post` publicado; sem a definição de
  `aculta_forum_post` apenas comentários são consultados.
- Pendência exclui tópicos LMS e só existe enquanto não há publicação.
- Contagem pública do Fórum filtra `aculta_forum_topic` e exclui LMS.
- `isNonLmsDiscussionComment()` exige bundle e campo de discussão, host `node`
  que não seja LMS, e rejeita material histórico marcado no comentário ou no
  host.

### `ParticipationEditWindow`

Comportamento fixado:

- Só o autor é restringido; outra conta, conta anônima e permissões
  administrativas permanecem neutras.
- Janela aberta devolve neutro com contexto `user`, `user.permissions`, tag
  `config:aculta_<domínio>.settings` e `comment:<id>`, e max-age igual ao tempo
  restante. No limite exato, a janela fecha.
- Janela expirada devolve proibido com a mesma cacheability.
- Sem marcador de publicação usa a criação; sem timestamp algum, bloqueia.
- Janela zero ou negativa bloqueia; chave ausente assume 10 minutos.
- Host LMS usa `aculta_forum_lms.settings`; demais hosts e entidades sem host
  node usam `aculta_forum.settings`. `aculta_forum_post` ignora a janela LMS.
- Apenas isenção permitida libera a edição; a primeira isenção permitida vence,
  inclusive janela zero, e a ordem é a prioridade da tag de serviço. A
  cacheability da isenção é agregada.
- Isenção que lança exceção é ignorada; a janela do autor prevalece. Uma falha
  de serviço nunca vira concessão de edição.
- O estado de publicação (publicado ou não) não é consultado pela janela.

### `EditWindowExemptionInterface` e a isenção LMS

`LmsEditWindowExemption` delega ao `LmsDiscussionContextProvider` e:

- não faz nada para entidade que não seja comentário, host que não seja node ou
  host que não seja `aculta_lms_topic`;
- permanece neutra, com `user.group_permissions`, quando o tópico não tem
  relação Group única (falha conservadora: mantém a janela do autor);
- isenta quando o curso concede a permissão contextual de educador, agregando
  `group:<id>`;
- é neutra para tópico novo ou sem UUID, sem chamar o provider: a isenção falha
  fechada e preserva a janela do autor em vez de propagar exceção pelo pipeline
  de acesso.

### Hooks

- `ParticipationEditWindowHooks::entityAccess()` só delega para comentário de
  discussão e `aculta_forum_post` na operação `update`; demais entidades e
  operações ficam neutras sem herdar contextos ou max-age da política.
- `ForumModerationHooks` consome a política de confirmação de e-mail e cobre a
  moderação do primeiro comentário de outra conta; células de ForumPost
  publicado/revisionado estão em `Drupal\Tests\aculta_forum\Kernel\ForumPostWorkflowKernelTest`.

## Matriz de testes

| Arquivo | Tipo | Testes |
| --- | --- | --- |
| `tests/src/Unit/ParticipationRulesTest.php` | Unit | 23 |
| `tests/src/Unit/ParticipationEditWindowTest.php` | Unit | 23 |
| `tests/src/Unit/ParticipationEditWindowHooksTest.php` | Unit | 9 |
| `tests/src/Unit/ForumModerationHooksTest.php` | Unit | já existente, complementado em C1-A |
| `tests/src/Unit/LmsEditWindowExemptionTest.php` | Unit | 8 |
| `tests/src/Kernel/ParticipationRulesCharacterizationKernelTest.php` | Kernel | 9 |
| `tests/src/Kernel/SharedParticipationIsolationKernelTest.php` | Kernel | 7 |

Células já cobertas fora deste marco, sem duplicação:

- ForumPost publicado/revisionado: `aculta_forum` Kernel
  `ForumPostWorkflowKernelTest`.
- Relação Group divergente e tópico LMS sem Grupo:
  `aculta_forum_lms` Kernel `LmsDiscussionContextIsolationKernelTest` e o Unit
  acima.

O Kernel de isolamento executa contas A/B reais, comentários LMS e de Fórum,
ForumPost publicado e revisionado, confirmação de e-mail, isenções, janelas
expiradas e invalidação por configuração. Ele ocupa o uid 1 com uma conta
descartável antes de criar as contas de teste: sem isso, a primeira conta criada
seria superusuária e `hasPermission()` sempre responderia verdadeiro, mascarando
as decisões da política. A configuração é gravada pelo caminho normal
(`$this->config()->save()`); os nomes cujo schema não é instalado no ambiente
mínimo (`aculta_forum.settings`, `aculta_forum_lms.settings`) ficam em
`$configSchemaCheckerExclusions`, o que pula só a checagem de schema e mantém a
invalidação de cache do salvamento real.

## Falha conservadora

| Situação | Resultado caracterizado |
| --- | --- |
| Configuração ausente ou chave desconhecida | padrão documentado (10 minutos), nunca janela infinita |
| Marcador de primeira publicação ausente | usa criação; sem timestamp, bloqueia |
| Sem definição de `aculta_forum_post` | evidência considera apenas comentários |
| Tópico LMS sem relação Group única | isenção neutra; janela do autor prevalece |
| Isenção que não permite | não altera a decisão da janela |
| Tópico LMS novo ou salvo sem UUID | isenção neutra sem consultar o provider; janela do autor prevalece |
| Isenção que lança exceção | ignorada; janela do autor prevalece (fail closed) |

## `PublishedParticipationLookupInterface` — estado em 2026-10-22 (C1)

O ADR-XK-01 previa um provider de evidência publicada por contexto. **Na
caracterização C1, em 2026-10-22, ele não existia como interface nem como
serviço.** A documentação registrava os requisitos que a implementação deveria
respeitar:

- recebe id de conta e contexto tipado (`public_discussion`, `wiki_entry`, …);
- devolve prova booleana ou estado agregado, sem textos nem detalhes de entidade;
- não soma evidências de contextos distintos (ForumPost, comentário LMS, verbete Wiki);
- declara cacheability e falha conservadora;
- não concede permissão Drupal nem transição editorial.

Naquela fase, consumidores continuavam usando `ParticipationRules` e seus
métodos atuais. A implementação posterior consta na seção seguinte.

## Implementação USER-PART 0.1-C — atualização 2026-10-24

O contrato agora está implementado no `aculta_user`:

- `PublishedParticipationLookupInterface` é implementada por providers
  específicos de contexto; `PublishedParticipationLookup` aceita somente um
  provider para cada contexto e nunca soma ForumPost, Comment LMS e Wiki.
- `PublishedParticipationEvidence` carrega somente o booleano agregado,
  confiabilidade e cache metadata. Provider ausente, duplicado ou com erro
  retorna indisponível com `max-age: 0`, que exige revisão.
- `aculta_wiki` fornece `WikiPublishedParticipationLookup`, que consulta apenas
  verbetes `wiki_entry` publicados no Domain canônico ativo e do UID solicitado. A
  revisão padrão continua sendo evidência enquanto revisão pendente não padrão
  existe. Cache usa `user` apenas para a conta atual; consulta de outro UID tem
  `max-age: 0`.
- O Domain anterior é restaurado e erro/Domain ausente falha fechado. Nenhum
  conteúdo, ID de entidade ou dado pessoal passa no resultado.
- O Fórum continua usando seus métodos e hooks existentes. A nova API ainda
  não foi conectada a novas permissões ou transições; a publicação continua
  dependente do workflow e de `content_editor`.

Evidências: User Unit 123 testes / 439 assertions; Kernel de caracterização
9/129; Wiki provider Kernel 1/45; Fórum Kernel 1/39. PHPCS passou. Runtime
intocado.

## Pendências desta caracterização

As pendências levantadas na primeira versão deste documento foram corrigidas
nesta fase, sem mudança de comportamento intencional:

1. Tópico LMS novo ou salvo sem UUID: `LmsEditWindowExemption` verifica
   `isNew()` e a existência de UUID antes de resolver o contexto e devolve
   `AccessResult` neutro. Coberto por `testNewTopicIsNeutral` e
   `testTopicWithoutAnIdentifierIsNeutral`.
2. Configuração nos testes Kernel: o salvamento usa `$this->config()->save()`
   com `$configSchemaCheckerExclusions` para os dois nomes sem schema
   instalado, em vez de escrever direto no storage.
3. Ordenação do contrato: a tag declara `priority: 0` e
   `testFirstAllowedExemptionShortCircuitsTheIteration` fixa que a primeira
   isenção permitida vence.
4. Serviço indisponível: `ParticipationEditWindow` ignora a isenção que lança
   exceção; `testUnavailableExemptionFailsClosed` cobre a falha e o caminho
   com uma isenção posterior permitida.
5. `EnrollmentRoleHooksTest` estava desatualizado em relação à migração de
   estudante de papel para perfil; o teste foi reescrito para o contrato atual
   (criação do perfil `estudante` só para matrícula de aluno). Não há mudança
   na implementação.

## Gates desta fase

Lint PHP, PHPCS (Drupal/DrupalPractice), Unit e Kernel de `aculta_user` e
`aculta_forum_lms`, testes focados de Fórum/User, `composer validate`, gates de
documentação/arquitetura/fronteira, `git diff --check` e
`php scripts/validate-project-status-index.php`. Nenhum Runtime foi executado:
o marco não altera comportamento e os testes Kernel usam SQLite em memória.

## Próximos marcos

C2 (Messenger), C3 (prova de ACL Group no Fórum) e XK-D (consumo gradual Wiki e
LMS) permanecem fora desta fase. A implementação do lookup publicado só deve
começar depois de decisão própria, com testes de zero/um publicado, rascunho
pendente, revisão não-default, confirmação revogada, domínio incorreto e duas
contribuições simultâneas.

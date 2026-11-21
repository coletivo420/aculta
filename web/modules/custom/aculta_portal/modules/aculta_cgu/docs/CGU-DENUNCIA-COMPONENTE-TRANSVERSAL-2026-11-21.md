# CGU - Botão de denúncia transversal com ícone Phosphor

Data: 21/11/2026
Branch: feat/forum-paridade-discourse

## Entrega e responsabilidades

A denúncia é um recurso **transversal do aculta_cgu**, não exclusivo do Fórum:

| Camada | Owner | Responsabilidade |
| --- | --- | --- |
| SDC `cgu-report-button` | `aculta420_v2` | Link com ícone Phosphor `flag` e popup existente, fallback sem JS |
| `content-reply-bar` | Tema | Slot `report_action` com o botão recebido do CGU; nenhum estado de denúncia no tema |
| `forum-opening-post` | Tema | Encaminha o slot CGU da abertura do tópico sem renderizar duas ações |
| `comment--field-aculta-discussion` | Tema | Componente no fórum e nos comentários de Revista/Wiki/LMS (se autorizados) |
| `CguReportActionHooks` | `aculta_cgu` | Insere botão em `entity_view_alter` para conteúdo completo publicado e autorizado |
| `CguReportCreateAccess` | `aculta_cgu` | Acesso **sempre** revalidado por entidade e conta em GET e POST |
| `CguReportCreateForm` | `aculta_cgu` | Seleção de motivo, descrição textual obrigatória e confirmação Form API |
| `ReportService` | `aculta_cgu` | Persistência, propósito resolvido pelo servidor, duplicidade, limite diário, lock, notificação |
| `CguReport` | `aculta_cgu` | Campo `description` sigiloso, adicionado sem remover denúncias legadas |
| `CguReportDecisionForm` | `aculta_cgu` | Exibe descrição apenas na decisão sob ACL CGU |

O link fallback `/denunciar/{entity_type}/{entity_id}` é GET e POST. Em modo
ACULTA V2, o botão abre o SDC `popup`; o evento
`aculta:drawer:open` aciona o HTMX no **corpo do diálogo**, e não no clique
do gatilho. O HTMX carrega **somente**
`[data-aculta-cgu-report-form]` para o corpo do modal, preservando o
contêiner para futuras reaberturas e evitando conflito entre os listeners de clique. O formulário é o
mesmo da página acessível sem JavaScript, com token do Drupal Form API.
**Nenhuma denúncia é criada por GET ou pelo clique no ícone**.

## UI Patterns Library e stories

- `cgu-report-button.default.story.yml`: popup sintético com motivo e descrição.
- `cgu-report-button.sem-js.story.yml`: fallback de link sem diálogo.
- `content-reply-bar.denuncia.story.yml`: composição real dos dois SDCs.
- Story principal de `content-reply-bar` também expõe botão composto.
- O catálogo oficial UI Patterns Library descobre stories automaticamente. Não
  criar catálogo, alias ou preview paralelo.

## Política de segurança

1. Somente conteúdo com tipo permitido, existente, publicado e leitura autorizada
   recebe o botão. A rota revalida `canReport` contra o CGU.
2. O resolver transversal também respeita `aculta_forum.discussion_origin_resolver`:
   tópicos, ForumPosts e comentários históricos/UNKNOWN não podem entrar
   pelo novo botão nem pela nova rota. **Sem o classificador, alvos do Fórum
   são negados** no fluxo público (fail closed).
   Conteúdo contextual da Wiki/Revista/LMS não é classificado como Fórum.
   Comentários e ForumPosts só oferecem a ação quando o alvo **e seu host**
   são publicados e legíveis pela conta. A rota e o formulário revalidam
   ambos, não apenas o cartão renderizado.
3. `ReportService::resolvePurpose` calcula a origem a partir da entidade e
   do host; o usuário não submete um purpose.
4. Descrição de 10 a 2000 caracteres no formulário; o serviço limita 2000 para
   chamadas legadas e grava em `description`. Notificação não inclui o texto
   nem identidade do denunciante.
5. Nenhum botão aparece ao anônimo; acesso direto ao formulário é negado.
   Duplicatas abertas e excesso diário também são recusados, com lock na gravação.
6. Alterações nos registros pertencem apenas ao CGU. Flagging legado permanece
   separado, sem criar denúncia duas vezes no novo fluxo.
7. O cache do botão considera conta, permissões e domínio; `max-age: 0` por
   depender da contagem e do histórico de denúncias da conta.
8. O histórico Growroom é isolado e não recebe denúncia pelo motor padrão.

## Instalação / alterações estruturais

Há um campo novo `description` na entidade `aculta_cgu_report`. A atualização
`aculta_cgu_post_update_add_report_description()` deve ser executada no Runtime
autorizado via `drush updatedb`, antes de validar o formulário e o painel.
Exigir backup/snapshot e revisar a ordem de execução no ambiente de integração.
Nenhuma atualização estrutural foi aplicada por esta entrega remota.

## Lacunas Discourse ainda prioritárias

1. UI de caso CGU com notas internas auditáveis e timeline imutável, com acesso
   por purpose e Group; só SDC sem engine não é implementação real.
2. Comentários Growroom continuam segregados; não usar a ação transversal em
   conteúdo importado/read-only.
3. Revisar especificamente consumidores de `aculta_forum_post` via Views e
   SDCs que não renderizam `entity_view`; o backend já oferece a ação para o
   render de entidade, mas nem todo view mode foi comprovado.
4. Conectar denúncias em novos tipos somente por adapter/owner com matriz de
   permissões; não permitir denunciar entidades internas ou contas.
5. Moderação coletiva de reports, notificação contextual, recurso e indicadores
   estatísticos continuam parcialmente planejados.
6. Solved/Assign/topic voting/bookmarks/reminders e autosave de rascunhos não
   ficam prontos com esta entrega.
7. Layout Builder/UI Patterns Views e fallback V1 devem ser homologados
   individualmente antes de afirmar paridade completa.

## Gates

- Cobertura contratual: `ForumDiscourseSdcContractsTest` para esquema/stories,
  montagem do popup, ícone `flag`, barra e adapter.
- Kernel: `CguReportPolicyKernelTest` para campos, descrição salva, limite,
  acesso ao formulário, anônimo, alvo fora do catálogo e duplicidade.
- **DEFERRED** neste ambiente GitHub remoto: execução PHPUnit, PHPStan 10,
  PHPCS, `php scripts/validate-aculta420-sdc-library-contract.php`,
  `php scripts/validate-aculta420-sdc-schemas.php`, Browser HTMX, envio,
  mensagens/validação dentro do popup, contraste dark/light, 320px,
  navegação por teclado, leitores de tela e testes do Runtime após update.

Aceite da próxima fase: rodar o fluxo em worktree isolada e homologar em Drupal
com banco de testes próprio, sem misturar concorrência de agentes ou mutar o
Runtime compartilhado sem coordenação.

# Auditoria ACL admin-only (F1) — 2026-11-21

Escopo: `config/sync/user.role.*.yml` no HEAD `9f4b6d4ba` (branch `feat/forum-paridade-discourse`).
Regra vinculante (handoff F1): só `administrator` acessa backend institucional e rotas
administrativas. Moderador fica abaixo de `content_editor`, com grants comunitários finos,
sem backend, sem edição editorial, sem LMS pedagógico e sem herança por peso.

## Inventário de permissões sensíveis por role

| Role | Acesso de backend | Permissões de fórum/moderação | Observação |
| --- | --- | --- | --- |
| `administrator` | sim (correto) | — | Único com backend permitido pela regra. |
| `content_editor` | `access administration pages`, `view the administration theme`, `access content overview`, `access files overview`, `administer url aliases` | `administer aculta forum posts`, `administer comments`, `moderar denuncias do forum`, `use aculta_forum_post transition approve/reject/edit_published/...` | Viola a regra em backend e em moderação ampla do fórum. |
| `gerente_loja` | `access administration pages`, `access site reports`, `administer commerce_store`, `administer aculta donation campaigns`, `administer aculta support settings`, `access commerce_product overview` | — | Viola a regra em backend. Permissões de loja/doação devem ir ao Studio (Green Stash) ou a administrator. |
| `lms_teacher` | `access administration pages`, `view the administration theme` | — | Viola a regra. Moderação pedagógica deve ser isolada no Studio (GanjaLab). |
| `moderador` | nenhum | nenhum (só `access content`) | Papel novo ainda vazio: falta receber grants finos de moderação comunitária. |

## Por que não remover agora

O plano manda remover permissões amplas **só depois** que as tarefas estiverem disponíveis no
Studio (F2). Hoje `content_editor`, `gerente_loja` e `lms_teacher` usam o backend para trabalho
real. Remover `access administration pages` sem o substituto em Studio quebra o fluxo de trabalho
dessas pessoas, o que é uma mudança operacional e precisa de decisão e autorização do responsável.

## Sequência proposta

1. **F2 (Studio):** disponibilizar no Marley/Studio fila e ações de moderação do fórum, e as
   operações de loja e de LMS. Sem isso, nenhuma remoção.
2. **F1a (grants finos):** criar, no papel `moderador`, as permissões comunitárias necessárias
   (`moderate aculta forum posts`, `reorganize aculta forum topics`, moderação de denúncias do
   fórum), sem backend.
3. **F1b (corte):** remover de `content_editor`, `gerente_loja` e `lms_teacher` as permissões de
   backend e de administração. Antes, testes adversariais de papéis isolados e compostos, uid1 e
   administrador, GET/POST/HTMX/API e cache quente.
4. **Runtime:** a mudança em config sync só entra no Runtime com plano, backup e autorização
   explícita (`aculta-runtime-migracoes`).

## Próxima ação que depende do responsável

Confirmar a sequência acima e autorizar a etapa F1a (grants finos ao `moderador`), que é
aditiva e não quebra ninguém. A etapa F1b só deve ser autorizada depois de F2.

## Status

- F1 auditado: **concluído** (este documento).
- F1a: **concluído no branch** (ver "Execução autorizada" abaixo), após autorização do responsável.
- F1b: **concluído no branch** para backend de `content_editor`, `gerente_loja` e `lms_teacher`; não sincronizado no Runtime.
- `lms_teacher`: sem permissão comunitária global, por decisão do responsável; moderação pedagógica segue por Group.

## Execução autorizada pelo responsável (2026-11-21)

Autorização: remover acessos de backend agora e conceder a F1a ao papel `moderador`.

- **F1b (feito no branch, não sincronizado no Runtime):** removidos de `content_editor` o backend
  (`access administration pages`, `view the administration theme`, `access content overview`,
  `access files overview`, `administer url aliases`, `administer comments`,
  `administer aculta forum posts`); de `gerente_loja` o backend e relatórios
  (`access administration pages`, `access site reports`, `access commerce_product overview`,
  `access commerce_product_attribute overview`, `administer commerce_store`,
  `administer aculta donation campaigns`, `administer aculta support settings`); de `lms_teacher`
  (`access administration pages`, `view the administration theme`).
- **F1a (feito no branch):** `moderador` recebeu `moderate aculta forum posts`,
  `moderate aculta forum topics`, `moderate aculta forum comments`, `review aculta forum reports`,
  `moderar denuncias do forum`, `reorganize aculta forum topics`, `close aculta forum polls`,
  `use aculta_forum_post transition approve` e `reject`. Sem backend e sem `administer*`.
- **lms_teacher NÃO recebeu permissão comunitária global.** A moderação pedagógica do LMS já é
  concedida por contexto de Group (`LmsCommentModerationAccessHooks`, `educatorAccess`). Uma permissão
  global daria ao educador moderação em todo o Fórum, o que o plano veda. Se o responsável quiser
  outra coisa, é decisão separada.

## Permanecem para decisão (não alterados)

- `gerente_loja`: `administrar doacoes` (relatório e pagamento de doações pelo Mercado Pago) e
  `delete any produto_loja commerce_product` (exclusão de produto). Ambas são operacionais; a
  exclusão é destrutiva, então precisa de confirmação antes de qualquer corte.
- `content_editor`: `moderar denuncias do forum` e `use aculta_forum_post transition approve`
  mantidos, por serem moderação e fluxo editorial, não backend.
- `moderate aculta forum topic deletions` (restrito) não foi concedido ao `moderador`.

## Pendências de verificação

- Nada disso foi sincronizado no Runtime. A aplicação exige plano, backup e autorização operacional
  (`aculta-runtime-migracoes`).
- Não houve teste de papéis em banco isolado; a verificação de acesso adversarial (F9) continua pendente.
- Os usuários que hoje dependem do backend de `content_editor`, `gerente_loja` e `lms_teacher`
  perderão o acesso ao `/painel-administrativo` assim que a config subir. Isso é consequência da
  autorização, mas precisa de aviso prévio às pessoas.

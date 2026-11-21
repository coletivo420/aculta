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
- F1a: **pendente de decisão** (ver acima).
- F1b: **bloqueado por F2**.

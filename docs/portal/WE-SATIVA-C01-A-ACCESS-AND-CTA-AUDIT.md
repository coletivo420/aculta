# WE SATIVA — C01-A: auditoria estática da ação Contribuir e de acesso

**Data:** 2026-10-22 · **Branch:** `docs/aculta-forum-0.1.0-dev.1` · **Status:** leitura de código/config concluída neste recorte; runtime, routes efetivas e permissões consolidadas pendentes.

## Evidências dos arquivos atuais

| Arquivo | Fato observado | Decisão |
| --- | --- | --- |
| `web/themes/custom/aculta420/templates/node--wiki-entry--full.html.twig` | inclui `aculta420:context-header` sem slot `actions`; inclui `wiki-entry` com `actions: content.links`, `show_heading: false` | **C01 CTA canônico** proposto em `context-header.actions` somente com render array autorizado; manter `content.links` como operações Core enquanto a migração é auditada e evitar duplicar Editar/Contribuir |
| `web/themes/custom/aculta420/components/patterns/wiki-entry/wiki-entry.twig` | exibe tab `Ler` e `actions` em `nav` contextual; não constrói links de permissão | mapear se operações Core já exibem Editar e definir desduplicação de ação; não remover `content.links` sem prova de todos os consumidores |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/aculta_wiki.routing.yml` | contém somente home, busca e prévia | rota WE SATIVA **não existe no submódulo consultado**; não adicionar link sem backend |
| `config/sync/workflows.workflow.wiki_editorial.yml` | `draft`, `review`, `published`; transições `create_new_draft`, `draft_to_review`, `review_to_draft`, `review_to_published` | usar estados oficiais; não assumir `Ajustes solicitados` como estado próprio |
| `config/sync/user.role.authenticated.yml` | não traz `create wiki_entry content`, `edit own wiki_entry content` nem `edit any wiki_entry content` | **bloqueador de inclusão ampla**: não expor CTA de edição a qualquer conta autenticada |
| `config/sync/user.role.content_editor.yml` | contém `create wiki_entry content`, `edit any wiki_entry content`, `use text format wiki` e todas as transições wiki | papel existente é poderoso demais para servir como padrão do colaborador iniciante; planejar capacidades de mínimo privilégio sem concedê-las nesta fase |
| `config/sync/filter.format.wiki.yml` | restringe tags e mídia; integra `freelinking` e `media_embed` | formato Wiki existe; verificar editor CKEditor 5 associado em `editor.editor.wiki.yml` se houver e a permissão de formato por perfil |
| `config/sync/core.entity_form_display.node.wiki_entry.default.yml` | `body` usa `text_textarea_with_summary`, referências usam `link_default`; há campos de configuração/owner | projetar WE SATIVA com formulários Core adaptados sem assumir que CKEditor 5 já está ativo para todos |

## Decisão de posicionamento — preservar os links Core

- **Um único convite primário `Contribuir`** ao lado do H1 no slot `actions` do SDC `context-header`, quando o kernel puder fornecer URL e `AccessResult` cacheável.
- `wiki-entry.actions` mantém links de operação nativos para a fase de auditoria; **proibir um segundo `Contribuir`** nessa área. Quando a integração existir, confirmar se `Editar` aparece duplicado e decidir, por consumidor, a convivência com ações avançadas Core. Não ocultar controles por CSS.
- A ação é um **link normal**, com destino seguro e fallback HTTP; a abertura do seletor WE SATIVA é posterior ao provisionamento da rota.
- Não criar SDC novo. `context-header`, `card` e `context-tabs` já cobrem a apresentação prevista, sujeitos ao gate UI Patterns/UI Styles.

## Matriz de acesso por capacidade, não por rótulo de papel

| Contexto | Comportamento seguro agora | Futuro condicionado |
| --- | --- | --- |
| Anônimo | ler somente verbetes publicados e autorizados; sem CTA de edição | orientação ou solicitação de acesso se implementada |
| Autenticado comum | sem CTA de edição por padrão | contribuições propostas por política/permissão própria após decisão de armazenamento |
| Editor de conteúdo com acesso real ao Node | ações Core existentes; não presumir WE SATIVA ativo | CTA único para WE SATIVA com autorização por Node e purpose |
| Revisor | transições somente quando permissão e estado permitirem | fila editorial WE SATIVA, sem duplicar form de workflow |
| Domain distinto / acesso revogado | não gerar links ou dados de verbete vedado | negar GET/POST e invalidar cache por contextos/tags corretos |

**Importante:** estes são fatos de configuração exportada e proposta arquitetural, não testes das permissões resultantes em Runtime. Outros papéis, grants, Access Hooks e Domain precisam de auditoria antes de qualquer mudança.

## Dependências bloqueadoras para C01-B / implementação

1. Mapear papéis Wiki adicionais, permissões reais, grants de Node, acesso à revisão e `route access` do backend.
2. Auditar `content.links` e a região do Core page title em DOM para garantir um único H1 e nenhuma ação duplicada.
3. Escolher política de **proposta moderada separada** para quem não tem `update`; registrar ADR com retenção, autoria, moderação, antiabuso e revisão concorrente.
4. Verificar `editor.editor.wiki.yml`, permissões de text format, Media e CKEditor 5 por papel.
5. Definir rota WE SATIVA canônica no purpose Wiki, com retorno seguro e sem dependência GanjaLab.
6. Testar telas e fluxos 320/360/390/768/1024/1280/1440px, no-JS, zoom e modos light/dark **após** implementação, sem declarar PASS antecipado.

## Resultado da C01-A

**AUDITORIA ESTÁTICA DIRIGIDA: DOCUMENTADA.** A escolha de slot é decisão de design para C01-B, não código implementado. **C01-B** é contrato de intenções, retorno e access; **C01-C** será integração progressiva de um CTA único após backend e testes. Autosave Form permanece fora do escopo atual.

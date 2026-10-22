# Roadmap — ACULTA Wiki

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md).
Fechar escopo, testes, gates e revisão visual aplicável antes de RC. Deploy em
produção só quando todos os módulos, temas e subtemas estiverem em RC.

Linha do projeto `aculta_wiki`: começa em **0.1.0-dev.1**, casca sem código.
O plano aprovado e o inventário de referências ficam em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Fase 1 — Casca do submódulo

- Manifesto com dependências `aculta_portal`, `domain`, `views`, `node` e
  `content_moderation`.
- Documentação de entrada e regras locais.
- Saída: validadores do repositório passam; módulo continua desabilitado.

## Fase 2 — Mover o código inteiro

- `WikiController`, `WikiSearchFormBuilder` e as três rotas
  `aculta_portal.wiki_*` passam para o submódulo, com nomes e caminhos
  idênticos.
- Os pontos de domínio e purpose (`DomainPurposeManager`,
  `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`).
  A Wiki não redefine purpose nem host.
- Saída: Unit e kernel do controlador e da busca; `drush cr` sem erro; Config
  Sync sem diferenças; HTTP 200 em `/wiki`, `/wiki/verbetes`, `/wiki/busca` e
  `/wiki/busca/previa` no domínio `wiki420`; backup privado antes de qualquer
  `updb`.

## Fase 3 — Documentação obrigatória da mudança de escopo

- `AGENT-CONTENT.md` e `AGENTS.md` do submódulo com o estado real de habilitação.
- Inventário LGPD com `wiki.entries.published` e `wiki.entries.updated` antes de
  criar as métricas.
- Política de privacidade: conferir “Bases legais por uso do site” sem inventar
  base legal.

## Fase 4 — `aculta_statistics` pronto para receber a Wiki

- Catálogo e retenção de 90 dias para as duas métricas.
- `WikiProvider` em `aculta_statistics`, sem importar classes da Wiki.
- Primeira publicação, revisão publicada e conteúdo importado cobertos por
  testes unitários.

## Fase 5 — Habilitação

- Só depois das fases anteriores, com decisão do responsável, backup privado e
  validação no servidor de testes.


## WE SATIVA — fundação aprovada (WES-UX 0.2, 2026-10-22)

A experiência editorial independente da Wiki420 é definida em [WE-SATIVA-FOUNDATION.md](WE-SATIVA-FOUNDATION.md) e no [plano de contribuição contextual](../../../../../../../docs/portal/WE-SATIVA-CONTRIBUTION-FIRST-RESEARCH.md). **Não reutilizar GanjaLab como interface do WE SATIVA.** Implementação iniciará por auditoria de Entity Access/Form API/workflow e resolução de purpose, depois abertura contextual por verbete e microtarefas mobile-first. Não habilitar sugestões sem backend real. **Autosave Form adiado para futuro**, sem Composer/configurações neste ciclo. Preservar Institution Bar + Domain Header e usar exclusivamente SDC/UI Suite no tema.


## WE SATIVA — arquitetura interna WES-UX 0.3 (2026-10-22)

O planejamento aprovado de todas as estruturas internas está em [WE-SATIVA-INTERNAL-ARCHITECTURE.md](../../../../../../../docs/portal/WE-SATIVA-INTERNAL-ARCHITECTURE.md). Fases 0.3-A1 e A2 deverão auditar permissões/workflows e decidir a persistência segura de propostas antes de rotas. Seguem depois wireframes, reuso SDC/UI Suite e integrações incrementais. **Nenhuma implementação funcional liberada por este documento; Autosave Form permanece futuro.**


## WE SATIVA C01 — primeira composição visual (2026-10-22)

[Porta contextual de contribuição](../../../../../../../docs/portal/WE-SATIVA-COMPONENT-01-CONTRIBUTION-ENTRY.md): um CTA autorizado a partir do verbete e seletor de intenções sem ações fictícias. Primeiro auditar `context-header.actions` versus `wiki-entry.actions`; manter um só CTA. Dependência de AccessResult, purpose/domain e URL canônica; proposta sem `update` continua bloqueada até decisão de backend. Projeto mobile-first, SDC/UI Suite, shell global preservado.


## Repriorização visual e de acesso — C01-A (2026-10-22)

A [auditoria C01-A](../../../../../../../docs/portal/WE-SATIVA-C01-A-ACCESS-AND-CTA-AUDIT.md) constatou: o SDC `context-header.actions` pode receber o convite único, enquanto `wiki-entry.actions` já exibe `content.links`; evitar CTA duplicado. O workflow `wiki_editorial` já tem rascunho, revisão e publicação, mas o papel autenticado comum não possui permissões de edição; o papel `content_editor` é privilegiado. **Antes de qualquer rota ou UI WE SATIVA:** (1) desenhar contribuição de mínimo privilégio e mecanismo de propostas sem `update`; (2) mapear todas as permissões e revisão; (3) verificar CKEditor/formatos; (4) selecionar a rota/purpose; (5) integração SDC mobile-first e teste de DOM. Próximo marco **C01-B**. Nenhuma concessão de permissão é autorizada por este roadmap.


## C01-B0 — participação de e-mail confirmado e moderação (2026-10-22)

**Decisão aprovada:** todo usuário com e-mail confirmado poderá contribuir na Wiki; somente `content_editor` modera/publica sugestões. [Contrato C01-B](../../../../../../../docs/portal/WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md) verifica que `user.role.email_confirmed.yml` já possui criação/edição de conteúdo próprio e envio ao estado `review`, sem publicar. Não confundir conta meramente `authenticated` com `email_confirmed`. Corrige-se a inferência incompleta da C01-A; não duplicar papel nem ampliar `edit any`. **Próximos:** C01-B1 conferir atribuição efetiva da confirmação de e-mail e Entity Access; C01-B2 registrar ADR de propostas em verbetes alheios; depois C01-C1 editor de próprios verbetes e C01-C2 microtarefas. C01-R no homelab. Autosave permanece somente futuro; nenhuma permissão, rota ou endpoint foi mudado.

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

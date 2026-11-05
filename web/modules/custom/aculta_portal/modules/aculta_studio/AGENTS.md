# Studio — regras locais vigentes

Caminhos das Suites: `docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md` (retificado em 2026-10-30). Dashboard da Conta e Perfil: `docs/decisions/ADR-048-layouts-de-pagina-tema-v2.md` e `docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md`. Não repita essas tabelas aqui. `/studio` e `/studio/*` respondem 404, sem alias ou redirect.

Histórico desta área (contratos antigos, status de dev.3 a dev.6, rotas de migração): `docs/history/modules/aculta_studio-AGENTS-ARQUIVO-2026-11-05.md`.

## Papel do Studio

- `aculta_studio` é o framework compartilhado de registry e composição. Não tem hub público em `/studio` e não é dono de conteúdo, permissões, discussões, galerias nem tabelas próprias equivalentes.
- Cada aba é um plugin `#[StudioTab]` com três contratos: `isAvailable()` (o dono está habilitado), `access()` (permissão real do dono, nunca nome de papel) e `actions()` (ação com o acesso da rota do dono).
- Ação para outro domínio usa `DomainPurposeManager::routeUrl()` e avalia o acesso com `runInPurpose()`.
- Nenhuma rota ou ação do Studio contorna Form API, Entity Access, Content Moderation nem as permissões do dono.
- Não copie tabela ou fluxo de permissão do dono. Não amplie o acesso de uma aba para contornar o dono. Não mova nem apague dados por ação do Studio sem a operação do dono.
- Não exponha dados de terceiros.

## Áreas, Conta e frontend (leitura sob demanda)

- Abas, rotas, disponibilidade e áreas (WE SATIVA, Marley, Ganjalab, Culturas): `docs/AGENT-AREAS.md`.
- Conta, Meu Perfil e frontend/acessibilidade do Studio: `docs/AGENT-CONTA-FRONTEND.md`.

## Melhoria contínua

- Toda frente que criar função com potencial de aba ou ação investiga o Studio antes de concluir, propor ou implementar a aba dentro desse contrato. Atualize a matriz em `docs/portal/STUDIO-MAGAZINE-ROADMAP.md`, o README do `aculta_studio` e o status do componente.
- Antes de encerrar uma mudança no Studio, rode os gates do diff listados em `docs/operations/GATES.md`. A suíte visual do Studio (`tests/browser/aculta-studio/`) é gate de aceite visual.
- Não declare a migração das Suites funcional integral antes de validar grants e fluxos de cada Suite. Não há troca planejada de `wiki420` para `wiki.aculta.*`.

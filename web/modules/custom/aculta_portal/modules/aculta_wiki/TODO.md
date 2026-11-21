# TODO — aculta_wiki

Estado: Dev · versão 0.1.0-dev.140 (`aculta_wiki.info.yml`) · status: `docs/operations/status/aculta_wiki.yml`.

## Estado atual

WE Sativa passou a possuir moderação Wiki própria: SDCs, páginas de revisão, propostas e denúncias, e controles separados de Fórum/Marley. Código e testes foram publicados em `feat/forum-paridade-discourse`; execução dos novos gates ainda pendente. As supressões PHPStan antigas já haviam sido tratadas; não inferir PASS desta rodada.

## Próximo passo

Rodar Unit/Kernel e Browser Wiki com banco simpletest e contas sintéticas na worktree já existente da feature. Revisar SDC/UI Patterns no claro/escuro e confirmar separação de purpose/roles e Form API de denúncia antes da homologação; não usar Runtime compartilhado.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_wiki.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

# TODO — aculta_wiki

Kernel histórico: WikiLeadImageLifecycleKernelTest instala agora o schema `group_relationship` necessário ao acesso via Groups; lint PASS, PHPUnit não executado após reboots.

Estado: Dev · versão 0.1.0-dev.138 (`aculta_wiki.info.yml`) · status: `docs/operations/status/aculta_wiki.yml`.

## Estado atual

PHPStan `src`: zero erros; Unit: 94 testes/455 assertions PASS.

## Próximo passo

Validar por contas distintas submissão, visibilidade própria, fila editorial e moderação no Runtime Wiki durante janela autorizada; não importar grants se a configuração ativa continuar igual ao Sync ([roadmap](web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md))

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_wiki.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

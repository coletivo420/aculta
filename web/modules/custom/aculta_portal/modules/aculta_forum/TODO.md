# TODO — aculta_forum

Estado: Dev · versão 0.1.0-dev.339 (`aculta_forum.info.yml`) · status: `docs/operations/status/aculta_forum.yml`.

## Próximo passo

Verificar a reconstrução de rotas e HTTP 404 de `/comment/{cid}` no próximo ambiente autorizado; o código substitui o acesso standalone por 404, sem alterar rotas contextuais. Repetir o inventário de migração se surgirem Comments elegíveis; o relatório atual não audita avisos globalmente. Não executar migração de dados no Runtime. Depois reavaliar F-M3/F-M6/F-M7, gates e revisão visual antes de RC. RC.2 é histórico.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_forum.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

# TODO — aculta_wiki

Estado: Dev · versão 0.1.0-dev.139 (`aculta_wiki.info.yml`) · status: `docs/operations/status/aculta_wiki.yml`.

## Estado atual

PHPStan CI apontou dez supressões obsoletas após o teste migrar de `aculta_contributions` para `aculta_cgu`; as entradas correspondentes foram removidas do baseline. A revalidação aguarda o próximo CI remoto. Unit: última evidência registrada, 94 testes/455 assertions PASS.

## Próximo passo

Validar por contas distintas submissão, visibilidade própria, fila editorial e moderação no Runtime Wiki durante janela autorizada; não importar grants se a configuração ativa continuar igual ao Sync ([roadmap](web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md))

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_wiki.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

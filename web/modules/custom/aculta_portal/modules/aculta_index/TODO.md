# TODO — aculta_index

Estado: Dev · versão 0.1.0-dev.182 (`aculta_index.info.yml`) · teste de janela da auditoria mantém a asserção de fase sem duplicar erro impossível do PHPStan baseline; lint pendente, PHPUnit não executado após reboots · status: `docs/operations/status/aculta_index.yml`.

## Próximo passo

Verificar em CI os testes de ciclo de rebuild/auditoria e o schema `group_relationship` do ContentIndexWikiAccessKernelTest; concluir grants/Domain dos providers owners, avaliar catálogo representativo e homologação operacional; manter Dev e não fazer rebuild no Runtime nesta integração. Incluir o incremento LOC/IDX-LOC conforme [plano](docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md), sem substituir os gates anteriores. A suíte Kernel global ainda requer correções independentes de Commerce, LMS e Groups.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_index.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

# TODO — aculta_events

Estado: Dev · versão 0.2.0-dev.10 (`aculta_events.info.yml`) · status: `docs/operations/status/aculta_events.yml`.

## Próximo passo

Completar P2 com contrato do item pago Commerce e gestão autorizada das ofertas; depois implementar serviço transacional de capacidade/RSVP e testes com fixtures fictícias sem ativação pública. Não coletar dados reais antes da revisão LGPD nem vender antes do parecer fiscal/jurídico. A View pública usa `card-cultural` pelo adapter do tema; Kernel focado 7/7 (130 assertions) valida dados filtrados e estado cancelado. Render do SDC no tema ativo e browser visual ficam pendentes do cutover autorizado. Homologação de Local, Culturas e A/B no Runtime permanece separada e somente leitura.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_events.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

# TODO — aculta_events

Estado: Dev · versão 0.2.0-dev.10 (`aculta_events.info.yml`) · acesso à organização falha fechado quando o serviço externo não está no container; Kernel focal 12 testes PASS; PHPStan focal PASS; CI desta revisão pendente · status: `docs/operations/status/aculta_events.yml`.

## Próximo passo

Completar P2 com contrato do item pago Commerce e gestão autorizada das ofertas; depois implementar serviço transacional de capacidade/RSVP com fixtures fictícias. Não coletar dados reais antes da revisão LGPD nem vender antes do parecer fiscal/jurídico. A View pública agora envolve rows `card-cultural` no style UI Patterns `layout-grid`; Runtime manteve 2 resultados e acesso anônimo, browser 320/1440 claro/escuro, foco e zero overflow PASS. Cache-hit/zoom e homologação de Local/Culturas/A/B permanecem pendentes.

PHPStan do módulo completo: zero erros brutos e nenhuma entrada no baseline. Análise PHPStan global estrita e Unit global PASS.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_events.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

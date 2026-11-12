# TODO — aculta_events

Estado: Dev · versão 0.2.0-dev.10 (`aculta_events.info.yml`) · acesso delegado falha fechado sem serviço externo; EventAccess/EventLocationManagement focais: 12 testes PASS. EventDomainBoundary usa serviço real para remover cinco TypeErrors; lifecycle instala schema `path_alias` omitido no JUnit histórico. Lint PASS; suíte não repetida após reboots · status: `docs/operations/status/aculta_events.yml`.

## Próximo passo

Verificar em CI o harness de EventDomainBoundary; depois seguir as falhas Kernel globais a partir de classes/artefatos específicos, sem rodar suíte integral neste checkout após dois reboots. O relatório histórico registra schema de `commerce_product` e tabelas `group_relationship` ausentes; confirmar cada causa antes de alterar harnesses. Depois completar P2 com contrato do item pago Commerce e gestão autorizada das ofertas, mantendo coleta pública e vendas fechadas. A View pública agora envolve rows `card-cultural` no style UI Patterns `layout-grid`; Runtime manteve 2 resultados e acesso anônimo, browser 320/1440 claro/escuro, foco e zero overflow PASS. Cache-hit/zoom e homologação de Local/Culturas/A/B permanecem pendentes.

PHPStan do módulo completo: zero erros brutos e nenhuma entrada no baseline. Análise PHPStan global estrita e Unit global PASS.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_events.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

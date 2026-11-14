# TODO — aculta_events

Estado: Dev · versão 0.2.0-dev.11 (`aculta_events.info.yml`) · URL pública do Evento aceita só HTTP/HTTPS; valores persistidos com esquema não web são omitidos do link, renderizado com `link()` do Drupal. PHP lint PASS; PHPUnit local não repetido após os reboots · CI remoto pendente · status: `docs/operations/status/aculta_events.yml`.

## Próximo passo

Confirmar em CI a rejeição de esquemas não web e o render de link do Evento; continuar a revisão dos alertas Semgrep `href` por origem dos dados, sem rodar suíte integral neste checkout após os reboots. Depois seguir as falhas Kernel globais a partir de classes/artefatos específicos. O relatório histórico registra schema de `commerce_product` e tabelas `group_relationship` ausentes; confirmar cada causa antes de alterar harnesses. Depois completar P2 com contrato do item pago Commerce e gestão autorizada das ofertas, mantendo coleta pública e vendas fechadas. A View pública agora envolve rows `card-cultural` no style UI Patterns `layout-grid`; Runtime manteve 2 resultados e acesso anônimo, browser 320/1440 claro/escuro, foco e zero overflow PASS. Cache-hit/zoom e homologação de Local/Culturas/A/B permanecem pendentes.

PHPStan do módulo completo: zero erros brutos e nenhuma entrada no baseline. Análise PHPStan global estrita e Unit global PASS.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`. Leia só a seção pelo título: `rg -n '^## ' web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_events.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

# TODO — aculta_forum

Estado: Dev · versão 0.2.0-dev.6 (`aculta_forum.info.yml`) · movedores usam o resolver genérico e falham fechados para origem não `CURRENT`; não consultam mais `field_autor_legado`. Kernel focal requer `SIMPLETEST_DB` indisponível nesta worktree · status: docs/operations/status/aculta_forum.yml.

## Próximo passo

- Mensagens de erro do popup HTMX são movidas do DOM parseado, sem reinserção como HTML string; validar no CI remoto. Depois continuar a dívida Kernel global por grupo de erro, sem suíte integral local após os reboots; retomar chamadas estáticas em classes com gates focais.
- Continuar a extração dos acoplamentos históricos restantes (`aculta_user`, fields/config, memorial e consumidores). A fronteira dos movedores é genérica; testar Kernel em banco SimpleTest isolado quando disponível.
- **ADR-054/055 Views:** SDC `forum-category` substitui os dois field overrides e os wrappers específicos de página/bloco; Views padrão conserva suas regiões. Sync/install preservam filtros, ordenação e acesso. `page_categories` agora usa também o style UI Patterns `layout-grid`; o agrupamento nativo por pai foi substituído pelo rótulo de pai em cada cartão. O Runtime segue em rows `fields` e aguarda post-update Portal autorizado antes do cutover; os Twig de compatibilidade não podem ser removidos até importar e homologar o style e as rows UI Patterns.
- **FST (implementado e homologado em parte):** escopo, ledger, reconciliação e métricas entregues; update 9008 aplicado e browser 44/44. Faltam Functional isolado, dark mode/zoom e leitor de tela. [Plano canônico](../aculta_statistics/docs/FORUM-ANALYTICS-PLANO-FST-00-11.md).

Concluir homologação integrada no Runtime e validação de navegadores. Fases C02-01 a C02-08 implementadas e testadas com Unit e Kernel tests (38 tests PASS). D-65 HTTP e F-M3/F-M6/F-M7 permanecem pendentes; nenhuma migração de dados autorizada.

## Leitura

- [Plano vigente C02](docs/COMENTARIOS-0.2.0-PLANO.md).
- Código alvo e trecho pertinente do CHANGELOG.
- [Roadmap](docs/ROADMAP.md) conserva dependências e pendências 0.1.

## Ao concluir uma fase

Atualizar TODO e status YAML no mesmo commit; remover o concluído sem acrescentar
histórico ao TODO. Registrar verificações reais e DEFERRED quando não executadas.

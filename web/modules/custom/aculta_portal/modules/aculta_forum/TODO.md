# TODO — aculta_forum

Estado: Dev · versão 0.2.0-dev.4 (`aculta_forum.info.yml`) · status: docs/operations/status/aculta_forum.yml.

## Próximo passo

- Refatorar a chamada estática remanescente em `TopicComposerFormHooks` e avançar a triagem dos demais módulos.
- **ADR-054/055 Views:** SDC `forum-category` substitui os dois field overrides e os wrappers específicos de página/bloco; Views padrão conserva suas regiões. Sync/install preservam filtros, agrupamento e acesso. Importação parcial foi autorizada; o Runtime segue em `fields` e aguarda post-update Portal autorizado antes do cutover.
- **FST (implementado e homologado em parte):** escopo, ledger, reconciliação e métricas entregues; update 9008 aplicado e browser 44/44. Faltam Functional isolado, dark mode/zoom e leitor de tela. [Plano canônico](../aculta_statistics/docs/FORUM-ANALYTICS-PLANO-FST-00-11.md).

Concluir homologação integrada no Runtime e validação de navegadores. Fases C02-01 a C02-08 implementadas e testadas com Unit e Kernel tests (38 tests PASS). D-65 HTTP e F-M3/F-M6/F-M7 permanecem pendentes; nenhuma migração de dados autorizada.

## Leitura

- [Plano vigente C02](docs/COMENTARIOS-0.2.0-PLANO.md).
- Código alvo e trecho pertinente do CHANGELOG.
- [Roadmap](docs/ROADMAP.md) conserva dependências e pendências 0.1.

## Ao concluir uma fase

Atualizar TODO e status YAML no mesmo commit; remover o concluído sem acrescentar
histórico ao TODO. Registrar verificações reais e DEFERRED quando não executadas.

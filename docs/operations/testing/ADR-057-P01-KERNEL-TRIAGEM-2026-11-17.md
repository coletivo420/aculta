# ADR-057 — triagem do Kernel CI (P01/F1) — 2026-11-17

## Execução observada

- PR #335, commit testado `8ba1b03d9083f5f900b58eca99cc7647ad7746a2`.
- GitHub Actions run `44080680393` (`PHP Quality Assurance`).
- Comparação de baseline: PR base `5c496047509f43831de2a6996cbc6ba95d867749`, workflow run `44065410069` no próprio `dev`.
- Cinco dos seis shards Kernel concluíram PHPUnit; um shard terminou cancelado sem resumo de testes. A conclusão do job desse shard não é PASS.
- Os cinco relatórios concluídos somam 949 testes, 15.848 assertions, 101 erros e 7 falhas. Os números excluem o shard cancelado.
- `phpstan`, `phpunit-unit`, `php-lint` e `test-discovery` concluíram sucesso; `PHPStan level 10 gate` concluiu sucesso. O debt-diff foi skipped por regra do workflow, não é um gate executado.

## Causas agrupadas pelos logs

| Shard | Resumo PHPUnit | Causa/owner indicado pelo teste | Escopo para P01 |
| --- | --- | --- | --- |
| 1/6 | 172 testes; 8 erros | 2 erros: template SDC `aculta420_v2:account-terms-modal` não descoberto ao renderizar; 6 erros: plugin Views `ui_patterns` ausente ao instalar config de `aculta_lms` em testes do Fórum. | Descoberta de SDC no harness do tema; disponibilidade/registro do plugin no harness de UI Patterns/Views. |
| 2/6 | Cancelado; sem resumo/artifact | Nenhuma conclusão PHPUnit disponível. | Investigar cancelamento separadamente; não converter em PASS nem em erro de teste. |
| 3/6 | 176 testes; 54 erros; 3 falhas | 15 erros por serviço `aculta_user.avatar_resolver` ausente durante boot de `ForumThemeHooks`; 13 por plugin Views `ui_patterns` ausente; 9 por schema incompleto em `views.view.aculta_forum_public_topics` (`author_card.variant`, `author_card.size`, `pinned.source`); 9 por dependência `ui_patterns_views` não atendida em config `aculta_lms`; 8 ao instalar config de LMS sem plugin. Três falhas adicionais: agenda LMS recebe `null` em vez do título; filtros/paginação e resiliência de entidades salvas no Studio recebem valores vazios. | Separar boot/config de Fórum, UI Patterns/Views e LMS; revisar contratos dos providers de Agenda/Studio com seus owners. |
| 4/6 | 184 testes; 3 falhas | Falhas de integração em `ActivityContentIndexProviderKernelTest`, `aculta_H5PCourseAccessIntegrationTest` e `MapSettingsFormKernelTest`. | Frentes Activity, LMS H5P e Maps; não atribuir ao tema sem evidência de dependência. |
| 5/6 | 240 testes; 24 erros | 15 testes de Commerce falham porque `private_message` não está disponível; 6 testes de `aculta_index` aguardam a fila de geração drenar; 3 testes do bloco de conteúdo relacionado recebem `null` em `#items`. | Dependência/harness de Commerce e isolamento/assíncrono do indexador. |
| 6/6 | 177 testes; 15 erros; 1 falha | 14 erros por módulo `private_message` indisponível; 1 schema incompleto da View `aculta_forum_public_topics`; 1 falha de `InteractiveControllerKernelTest` porque o array não contém `componente`. | Dependência de Commerce, schema UI Patterns/Views e contrato de apresentação do calendário. |

## Interpretação

Os seis arquivos alterados pela PR #335 eram de auditoria, browser e
documentação; não incluíam os módulos/testes citados nos erros acima. Também
comparei os logs dos mesmos shards nos runs do commit base e da PR: os blocos
completos de erros/falhas e os resumos dos shards 1, 3, 4, 5 e 6 são idênticos.
No shard 3, a única diferença textual é a ordem da lista de plugins válidos
impressa pelo Plugin Manager; após ordenar essa enumeração, todo o bloco
coincide. O shard 2 foi cancelado nos dois runs. Portanto, as falhas Kernel
observadas já existiam no commit base e não foram introduzidas pela PR #335.
P01 continua **FAIL** porque o baseline do CI ainda falha; a comparação de
origem não substitui a resolução das falhas nem um gate Kernel estável.

O bloqueio da suíte não pertence apenas à interface: há falhas de descoberta
de SDC, integração UI Patterns/Views e schemas relevantes à ADR-057, além de
falhas independentes de Fórum, LMS, Commerce, Index, Activity, H5P, Maps e
Calendário. Nenhuma configuração, dependência ou workflow foi alterado nesta
triagem.

## Próximos passos

1. Atribuir os grupos confirmados como preexistentes aos owners dos módulos e priorizar descoberta SDC e plugin/schema UI Patterns/Views, que afetam diretamente a integração em avaliação.
2. Corrigir as causas em lotes isolados e manter failures/skipped/cancelled distintos no relatório e na proteção de branch.
3. Reexecutar todos os shards no HEAD atual depois das correções e exigir sucesso; o cancelamento do shard 2 nos dois runs não prova estabilidade.
4. Após estabilizar, decidir se o gate Kernel pode ser obrigatório por shard sem continuar mascarando falha legítima.

**Fonte:** logs de workflow dos jobs `134030642221`, `134030642171`,
`134030642368`, `134030642245` e `134030642464`; shard `134030642255`
cancelado. Esta triagem não altera o status das ADR-054/055 nem fecha P01.

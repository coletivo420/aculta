# ACULTA Content Index

**Versão da iniciativa:** 0.1.0 — IDX 0.1-F/G em desenvolvimento; fundamentos de schema, providers, manutenção, consulta e bridge implementados, integração progressiva pendente.
**Dono:** Kernel `aculta_portal`, submódulo opcional `aculta_index`.
**ADR:** [ADR-013](../decisions/ADR-013-aculta-content-index.md).
**Auditoria e matriz de reaproveitamento:** [IDX 0.1-A](ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md).
**Schema IDX 0.1-C:** [modelo inicial e gates pendentes](ACULTA-CONTENT-INDEX-SCHEMA-0.1-C.md).
**Bridge de Statistics:** [contrato opcional e estratégia de correlação](ACULTA-CONTENT-INDEX-STATISTICS-BRIDGE.md).

## Propósito e propriedade

O Content Index será um catálogo SQL derivado, reconstruível e incremental de conteúdos e relações selecionados. Ele permitirá que consumidores autorizados descubram, filtrem, relacionem e contabilizem itens de vários submódulos por contratos comuns.

O catálogo não é dono de nenhuma entidade original. O módulo de origem mantém armazenamento, revisões, traduções, workflows, publicação, regras de negócio e acesso. Estar no catálogo não concede visibilidade nem ação. `ContentIndexAccessFilter` carrega até 100 candidatos por lote e revalida `Entity Access` na tradução original; falha ao encontrar entidade ou determinar acesso nega exposição. O resultado combina cacheability de decisões permitidas e negadas. Facetas, totais, ordenações e cache também precisam respeitar esse limite.

## Fronteiras

| Componente | Responsabilidade |
|---|---|
| `aculta_index` | Catálogo derivado, contratos de descoberta/projeção/relação, consulta, fila/rebuild, integridade e operação. |
| Módulos de origem | Entidades, autorização, workflow, revisão, tradução, publicação e adaptadores de indexação. |
| `aculta_statistics` | Eventos analíticos, métricas, agregações SQL, retenção e relatórios. O bridge com o Index é opcional e não circular. |
| Search API | Busca textual, datasources/tracking e backend de pesquisa. O Index não recria fulltext. |
| `aculta_studio` | Experiências de gestão/editoriais que consomem APIs; não recebe storage de catálogo. |
| ACULTA420 | Apresentação SDC/UI Suite, tokens, responsividade e temas claro/escuro. |

## Projeção prevista

Decisão do responsável para 0.1: uma identidade por entidade e idioma, sem entradas separadas por revisão. Como o site hoje usa `pt_BR`, normalmente haverá uma linha por entidade; preservar o idioma na identidade evita remodelagem se outros idiomas forem habilitados. O schema ainda será definido na IDX 0.1-C e poderá guardar identidade, bundle, UUID, domínio estável, módulo proprietário, estado de indexação, título mínimo, datas, fingerprint/versão e geração. Campos devem ser allowlist por provider. Não copiar corpos integrais, arquivos, segredos ou informação pessoal desnecessária.

Relações são direcionadas, tipadas e produzidas por provider. Cada tipo declara origem, cardinalidade, remoção e acesso. Entity Usage é referência para descoberta de relações, não fonte pública universal. Relações com usuários/autores exigem justificativa de necessidade e política de acesso.

## Atualização, reconstrução e consulta

O desenho de implementação combina: revogação/bloqueio síncrono quando uma mudança reduzir exposição; job incremental idempotente com payload mínimo e comparação atômica de versão; reconciliação paginada/checkpointed; rebuild por geração com catch-up de deltas e ativação atômica; rollback/limpeza somente depois de validar integridade. Entradas e relações terão `generation_id` e um ponteiro para a geração ativa. A ativação trocará o ponteiro transacionalmente. O cursor será keyset, sem OFFSET profundo. Consultas de candidatos não autorizam saída; entidades originais serão carregadas em lotes e autorizadas de acordo com a API dona.

Contrato decidido para consultas 0.1: páginas de até 100 candidatos, sem totais ou facetas. O serviço filtra cada página por Entity Access e retorna cursor aleatório vinculado a conta, filtros e geração; só o hash do token é persistido. O cursor não expõe IDs. Sua presença quando uma página chega ao limite pode sinalizar continuação, opção aceita para limitar custo sem varrer um conjunto irrestrito. Respostas iniciais têm cache max-age 0. Se totals/facetas forem adicionados depois, só poderão refletir entidades autorizadas e deverão ser medidos em volume.

Core Queue foi escolhida para a fila incremental. `aculta_index` manterá estado mínimo próprio para contagem/timing de falhas e dead-letter, pois a Queue Core fornece lease/recuperação de item expirado, mas não o histórico operacional específico requerido. Política confirmada: cinco tentativas com backoff exponencial de 30 segundos até seis horas. Não instalar Advanced Queue. O fluxo precisa sobreviver a módulo de origem desabilitado, rebuild concorrente, job fora de ordem, relação removida e acesso revogado.

## Fases

| Fase | Entrega | Estado |
|---|---|---|
| IDX 0.1-A | Auditoria real, matriz, fontes contrib e ADR | Concluída; documentação commitada |
| IDX 0.1-B | Fundação opcional e contratos mínimos | Implementada; testes unitários do registry passaram; manifest e status no Portal incluídos |
| IDX 0.1-C | Schema e primeiro provider | Schema, provider Magazine, writer transacional e filtro de Entity Access testados no Kernel/Unit; writer integrado ao backfill keyset |
| IDX 0.1-D | Eventos, fila incremental e segurança de revogação | Hooks CRUD, fila deduplicada Core Queue, sequência por identidade, retries e worker inicial implementados; eventos de grants externos e teste de concorrência pendentes |
| IDX 0.1-E | Rebuild, checkpoints, reconciliação e rollback | Ciclo de gerações, descoberta keyset, checkpoint CAS, fingerprint, conclusão CAS, validação estrutural, gate de drenagem/ativação, comandos Drush e auditoria incremental diária implementados; fence sincroniza eventos com troca do ponteiro; descoberta Drush no Runtime e rollback homologado pendentes |
| IDX 0.1-F | Providers progressivos e bridge opcional Statistics | Provider Magazine conectado à fila; bridge tipado envia até 100 IDs já autorizados, retorna entidades/totais/cursor, preserva a página sem Statistics; permissão granular para métricas públicas; providers Wiki e demais integrações pendentes |
| IDX 0.1-G | Consultas, cache e medições | Consultas com filtros allowlisted, relações nomeadas de saída, Entity Access, cursor opaco e consulta paginada tipada implementados; caches/benchmarks, compatibilidade adicional de bancos e mais providers pendentes |
| IDX 0.1-R | Homologação de segurança, portabilidade, volume e falhas | Pendente |

Ver o [relatório da IDX 0.1-A](ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md) para inventário, riscos, proveniência e achados de código. Nenhuma fase futura está marcada como concluída por antecipação.

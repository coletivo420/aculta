# ACULTA Content Index

**Versão da iniciativa:** 0.1.0 — IDX 0.1-A concluída; implementação pendente.
**Dono:** Kernel `aculta_portal`, submódulo opcional `aculta_index`.
**ADR:** [ADR-013](../decisions/ADR-013-aculta-content-index.md).
**Auditoria e matriz de reaproveitamento:** [IDX 0.1-A](ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md).

## Propósito e propriedade

O Content Index será um catálogo SQL derivado, reconstruível e incremental de conteúdos e relações selecionados. Ele permitirá que consumidores autorizados descubram, filtrem, relacionem e contabilizem itens de vários submódulos por contratos comuns.

O catálogo não é dono de nenhuma entidade original. O módulo de origem mantém armazenamento, revisões, traduções, workflows, publicação, regras de negócio e acesso. Estar no catálogo não concede visibilidade nem ação. A resposta final revalida a entidade original e seu acesso sob o domínio/grupo pertinente; se a autorização não puder ser determinada, o item não é exposto. Facetas, totais, ordenações e cache também precisam respeitar esse limite.

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

O schema definitivo fica para IDX 0.1-C. A auditoria aponta identidade por tipo de entidade + ID original + idioma/revisão conforme semântica validada, além de bundle, UUID, domínio estável, módulo proprietário, estado de indexação, título mínimo, datas, fingerprint/versão e geração. Campos devem ser allowlist por provider. Não copiar corpos integrais, arquivos, segredos ou informação pessoal desnecessária.

Relações são direcionadas, tipadas e produzidas por provider. Cada tipo declara origem, cardinalidade, remoção e acesso. Entity Usage é referência para descoberta de relações, não fonte pública universal. Relações com usuários/autores exigem justificativa de necessidade e política de acesso.

## Atualização, reconstrução e consulta

O desenho de implementação deverá combinar: revogação/bloqueio síncrono quando uma mudança reduzir exposição; job incremental idempotente com payload mínimo e comparação atômica de versão; reconciliação paginada/checkpointed; rebuild por geração com catch-up de deltas e ativação atômica; rollback/limpeza somente depois de validar integridade. O cursor será keyset, sem OFFSET profundo. Consultas de candidatos não autorizam saída; entidades originais serão carregadas em lotes e autorizadas de acordo com a API dona.

Core Queue é candidato inicial, sujeito às garantias de retry/delay/concurrency requeridas. Advanced Queue será integrada apenas se os requisitos operacionais justificarem dependência. O fluxo precisa sobreviver a módulo de origem desabilitado, rebuild concorrente, job fora de ordem, relação removida e acesso revogado.

## Fases

| Fase | Entrega | Estado |
|---|---|---|
| IDX 0.1-A | Auditoria real, matriz, fontes contrib e ADR | Concluída documentalmente |
| IDX 0.1-B | Fundação opcional e contratos mínimos | Próxima |
| IDX 0.1-C | Schema e primeiro provider | Pendente |
| IDX 0.1-D | Eventos, fila incremental e segurança de revogação | Pendente |
| IDX 0.1-E | Rebuild, checkpoints, reconciliação e rollback | Pendente |
| IDX 0.1-F | Providers progressivos e bridge opcional Statistics | Pendente |
| IDX 0.1-G | Consultas, cache e medições | Pendente |
| IDX 0.1-R | Homologação de segurança, portabilidade, volume e falhas | Pendente |

Ver o [relatório da IDX 0.1-A](ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md) para inventário, riscos, proveniência e achados de código. Nenhuma fase futura está marcada como concluída por antecipação.

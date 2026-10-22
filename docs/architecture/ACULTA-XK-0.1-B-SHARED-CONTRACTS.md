# ACULTA-XK 0.1-B — Contratos compartilhados, ownership e ADRs

**Data:** 2026-10-22 · **Estado:** fundação arquitetural documentada; sem alteração funcional, dependência Composer ou Runtime.
**Base:** [Auditoria XK 0.1-A](ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md).

## ADR-XK-01 — Participação compartilhada pertence ao aculta_user

**Decisão:** `aculta_user` hospeda política e interfaces neutras de primeira participação; `aculta_forum`, `aculta_forum_lms` e `aculta_wiki` fornecem adapters das suas entidades, sem importar classes entre si.

**Contratos candidatos, não APIs implementadas:**
- Um provider de **evidência publicada por contexto** recebe account id + contexto tipado e devolve somente prova booleana ou estado agregado; não devolve textos privados. Nenhuma soma indistinta entre ForumPost, Comment LMS e verbetes Wiki.
- Um avaliador de política em User recebe contexto (`public_discussion`, `wiki_entry` ou outro registrado), conta e prova do provider, decidindo se **a primeira contribuição exige revisão**, sem jamais conceder permissão Drupal ou transição editorial.
- Retorno deve declarar cacheability e falha conservadora. Uma falha de consulta não deve liberar publicação.
- Manter assinaturas e DI públicos de `ParticipationRules`; primeiro criar testes de caracterização, depois adapter Wiki; LMS conserva exceção Group e regra própria.
- Em Wiki, o primeiro verbete de usuário `email_confirmed` exige revisão, e **todos** os verbetes/revisões seguem publicação controlada por `content_editor`. Não introduzir `review_to_published` no papel comum.

**Critério de saída:** Fórum inalterado em testes, Wiki com testes para zero/um publicado, rascunho pendente, revisão não-default, confirmação revogada, domínio incorreto e duas contribuições simultâneas.

## ADR-XK-02 — aculta_messenger é o concentrador de avisos e mensagens

**Decisão:** mensagens humanas continuam no Private Message integrado ao `aculta_messenger`; avisos persistentes e pessoais usam infraestrutura existente (Message/Message Notify/Flag, `aculta_aviso` quando compatível). `aculta_user` controla identidade, amizades e preferências de conta; `aculta_mailer` controla transporte de e-mail. Adapters publicam eventos de produto sem criar inbox, notificadores ou tabelas paralelas.

**Envelope de evento candidato (não classe criada):**
`event_type`, `source_module`, `source_entity_type`, `source_entity_uuid`, `source_revision_or_version`, `recipient_uid`, `purpose`, `occurred_at`, `idempotency_key`, dados mínimos. Proibido transportar conteúdo privado, notas, respostas de prova ou emails sensíveis no payload.
- Separar **geração de evento** de entrega e de visualização. O Messenger escolhe canal conforme preferências válidas e tipo de aviso; o producer não faz SMTP.
- Revalidar Entity Access, role, Group e propósito **na entrega e ao abrir o aviso**. Opt-out de email não apaga avisos necessários no site.
- Idempotência por consumidor/evento, retry com erro explícito, sem duplicar aviso na republicação de uma revisão já tratada.
- Eventos candidatos: Wiki `review_submitted`, `changes_requested`, `revision_published`; Fórum novo post/revisão moderados; LMS somente pelo adapter `aculta_lms_messenger`; associado apenas se há consumidor real.
- Preservar contrato atualmente em produção/dev; migração gradual com comparação lado a lado, sem duplo disparo.

**Critério de saída:** inventário de todos os produtores e templates Message, matriz de consentimentos/canais, Unit de deduplicação e Kernel de ACL; entrega SMTP real somente no ambiente de teste controlado.

## ADR-XK-03 — Group para fóruns restritos a associados

**Decisão:** elegibilidade é **situação `ativo` em `aculta_user`**, e acesso precisa também de membership válido no Group do fórum. `associado` não é papel Drupal; o papel `email_confirmed` isoladamente não concede acesso. Grupos do Fórum são distintos dos Groups de cursos/turmas do LMS.

**Contratos candidatos:**
- User expõe consulta autoritativa `isActiveAssociate(account)` e sinalização de alteração de situação (ativação, suspensão, desligamento, readmissão); manter serviço atual caso já exista, não criar segundo estado.
- Adapter `aculta_forum` gerencia o tipo de Group escolhido, relação de tópico/categoria, membership idempotente e revalidação de conteúdo. **Não pressupor que Group já esteja configurado para o Fórum**.
- Condição conjunta de acesso: conta com confirmação exigida pela política existente + associado `ativo` + membership Group + Entity Access/Domain/purpose do recurso. `AccessResult` com cache contexts/tags específicos do User, associação e Group.
- Ao perder status ativo, acesso efetivo deve ser revogado **imediatamente na checagem**, mesmo antes da reconciliação assíncrona de membership. Reconciliação remove/ajusta vínculo com auditoria, retry e lock; não confiar só em remoção física da relação.
- Proteção completa: View, Search API, detalhes, anexos, comentários, contadores, navegação, links de convite, notificações e cache. Sem revelar existência/título do fórum restrito a quem não pode vê-lo.
- Backfill/migração: inventariar conteúdos, dry-run, backup privado, plano rollback, nenhuma mudança de UUID ou acesso público por default.

**Critério de saída:** testes com associado ativo, pendente, convidado, suspenso e desligado; membros de grupos distintos; acesso por URL direta, busca, anexo, notification e expiração de cache; regressão LMS Group.

## Fronteiras e ordem de execução

| Incremento | Dono | Artefato/condição |
| --- | --- | --- |
| XK-B1 | User | catálogo de consumers e assinatura dos contracts de participação, sem código PHP |
| XK-B2 | Messenger | catálogo de eventos, transporte, idempotência e consentimento |
| XK-B3 | User + Forum | situação de associado, Group model e sincronização segura |
| XK-C1 | User | testes de caracterização para ParticipationRules/ParticipationEditWindow antes de qualquer refatoração |
| XK-C2 | Messenger | adaptar um evento existente sem duplicar aviso, com testes |
| XK-C3 | Forum | prova de ACL Group e revogação antes da migração |
| XK-D | Wiki e LMS | consumos graduais, sem alterar isolamento Group nem revisão |
| XK-R | todos | gates Unit/Kernel/Runtime, segurança, cache, responsividade, migração/rollback |

**Critérios transversais:** Drupal 11+, service DI, interfaces pequenas, AccessResult cacheável, event sourcing só se necessidade comprovada, evitar novas entidades/dados e dependências. Core SDC/UI Suite só no tema; shell multidomínio preservado. **Autosave Form não entra neste ciclo**.

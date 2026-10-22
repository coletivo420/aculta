# Auditoria transversal ACULTA — centralização e contratos entre subkernels

**Versão:** ACULTA-XK 0.1.0-dev.1 · **Data:** 2026-10-22 · **Estado:** auditoria documental do código/READMEs; sem implantação, instalação ou testes Runtime.
**Objetivo:** reduzir duplicação sem transformar `aculta_portal` em supermódulo. Centralizar cada comportamento no kernel **proprietário** já existente.

## Mapa de ownership aprovado

| Área | Fonte de verdade / kernel | Consumidores e regra |
| --- | --- | --- |
| Conta, confirmação, identidade, amizades, perfis e situação de associado | `aculta_user` + política de confirmação no Portal | demais subkernels consultam serviços públicos; não replicar estado de conta |
| Primeira participação, políticas comuns de confiança/moderação | **`aculta_user`**, `ParticipationRules` | Fórum e Wiki com lookups próprios; LMS respeita contexto Group |
| Conversas privadas | **`aculta_messenger`** integra Private Message | outros kernels apenas linkam/contextualizam; storage nativo permanece |
| Avisos pessoais, notificações, assinatura/entrega, contadores/digest | **`aculta_messenger`** integrando Message/Message Notify/Flag | `aculta_lms_messenger` e futuros adapters Wiki/Fórum; não construir segundo inbox |
| Preferências pessoais/privacidade de novas conversas, amizades | `aculta_user` | Messenger consome contrato; distinguir configuração da conta de transporte e entrega |
| Envio externo de e-mail | `aculta_mailer` + transportes existentes | Messenger orquestra notificações, Mailer é transporte; não duplicar SMTP |
| Fóruns públicos e discussões contextuais | `aculta_forum` + `aculta_forum_lms` / `aculta_forum_wiki` | entidades e context resolver de fórum; não abrir tópico público por publicação/aula/verbete |
| Autorização de fóruns privados de associados | **Group** + vínculo de associado em `aculta_user`; adapter `aculta_forum` | membership e grants precisam ser revalidados, independentemente de UI |
| Cursos/turmas e avaliações | `aculta_lms` + Group | reuse genérico de Group sim; isolamento de turma e dados pedagógicos fica no LMS |
| Mídia, derivados e limpeza | `aculta_image`, `aculta_gallery` | Forum, Wiki, LMS, Revista e Studio consomem, sem pipeline paralelo |
| Documentos e visualização | `aculta_document_viewer` / `aculta_pdf_viewer` | wrappers e controle de acesso pelo dono do arquivo |
| QR Codes | `aculta_qrcode` | integração transversal com validação de URL/purpose |
| Métricas de vários produtos | `aculta_statistics` | providers Fórum, Wiki, LMS, Commerce, Messenger e Links, com consentimento/LGPD |
| Publicação editorial / UI de criação | subkernel dono do conteúdo; `aculta_studio` apenas para GanjaLab | WE SATIVA independente, usando Form API/CKEditor; não duplicar workflow |
| Design system | `aculta420`: Core SDC/UI Patterns/UI Styles | UI comum somente quando SDC real e consumidores mapeados |
| Resolução de domínio/purpose | `aculta_portal` | não duplicar host ou autorização em CSS e JavaScript |

## Evidências do repositório

- `aculta_messenger/README.md` descreve Private Message, Message, Message Notify, Message Subscribe/Flag, Site Alert e avisos `aculta_aviso`, com contadores e integração LMS. **Mensagens e avisos são fluxos diferentes**, embora tenham UI central.
- `aculta_lms_messenger/README.md` declara adapter sem caixa nem storage próprios e acesso contextual de matrícula, turma e notas.
- `aculta_user/docs/ASSOCIADO.md` estabelece **associado como situação** (ativo, suspenso, desligado etc.), não papel. Nenhuma estratégia de Group pode usar apenas `hasRole('associado')`.
- `aculta_forum_wiki/README.md` tem adapter contextual de discussão por verbete; não duplicar engine da Wiki.
- `aculta_statistics/docs/ROADMAP.md` já prevê providers transversais para Wiki, Commerce, LMS e e-mail.
- `aculta_user/src/ParticipationRules.php` centraliza primeira participação nas discussões; extensão para Wiki planejada, sem reutilizar contador de comentários como contador Wiki.

## Backlog de centralização (prioridade, não implementação)

| ID | Mudança candidata | Módulos impactados | Ordem e gate |
| --- | --- | --- | --- |
| XK-01 | formalizar protocolo de eventos/avisos de produto e adaptadores + deduplicação no Messenger | Messenger, Fórum, Wiki, LMS Messenger, Magazine, User | inventário de eventos → contrato typed/context → testes de idempotência e acesso |
| XK-02 | consolidar preferências de entrega/notificações com ownership de conta, sem duplicar storage de eventos | User, Messenger, Mailer, LMS Messenger | mapear consentimento/opt-out, separar UI de preferência de transporte |
| XK-03 | política de participação/primeiro conteúdo no User | User, Fórum, Wiki, LMS Forum | conforme USER-PART 0.1-A→D; preservar exceção LMS |
| XK-04 | **Group para fóruns exclusivos de associados ativos** | Fórum, User, Group, Portal, Messenger, Statistics | identidade de associado → membership idempotente → grants/cache/revogação → UI/testes |
| XK-05 | harmonizar catálogo de contextos Fórum (público, Wiki, revista, LMS) sem misturar permissões | Forum, Forum LMS, Forum Wiki, Magazine | providers tagged e acesso revalidado; não mover Group LMS ao Forum |
| XK-06 | adapters comuns de Media/alt/licença/limpeza (sem storage duplicado) | Image, Gallery, Forum, Wiki, Magazine, LMS | verificar interfaces existentes e reuso antes de refatorar |
| XK-07 | viewer universal de PDF/documentos com fallback acessível | Document Viewer, PDF Viewer, LMS, Wiki, Magazine, Messenger | um único viewer por formato e ACL de arquivo |
| XK-08 | métricas por evento e privacidade: criação, revisão, avisos, membership | Statistics, User, Forum, Wiki, Messenger, LMS | providers deduplicados, sem PII e sem dashboards paralelos |
| XK-09 | URLs multi-domain e QR Codes seguros em serviços existentes | Portal, Qrcode, Links, Wiki, Forum | URL propósito canônico, assinatura quando privada |
| XK-10 | wrappers de formulários e cartões visuais estritamente SDC/UI Suite | Tema, Studio, Wiki, Forum, LMS | mapear consumidores e migrar legados, sem CSS de negócio |
| XK-11 | moderação report/flags compartilhável, sem filas cruzadas | User, Forum, Wiki, LMS | política e contrato User; filas e workflows locais |
| XK-12 | workers/cron de notificações com revalidação no momento da entrega | Messenger, LMS Messenger, Wiki, Forum, Mailer | retry/idempotência e consentimento, não enviar dados após revogação |

## Group para fóruns exclusivos de associados — decisão funcional

**Membership não é papel:** o `aculta_user` expõe consulta autoritativa da situação de associado `ativo`. O adapter Fórum usa Group como fonte da filiação ao fórum restrito e revalida **ambas**: situação ativa + membership Group + visibilidade da categoria/tópico. Convite, pedido, suspensão, desligamento ou mudança de associação não devem deixar grants antigos em cache. Planejar reconciliação idempotente da membership ao ativar/suspender/desligar, rollback/backfill e testes com associados A/B. Não misturar membership de cursos ou turmas Group com membership de associados. O papel `email_confirmed` sozinho não libera fórum de associado.

**Fases F-GROUP:** A inventariar entidades e relações Group disponíveis e política atual de fórum; B especificar adapter e sincronização membership; C implementar e proteger consulta/página/contagem/notificação; D migração/backfill com dry-run e backup; R testes homelab (acesso, remoção, cache, anexos, busca, Messenger, mobile). Não implementar uma nova engine Group nem permitir bypass via link direto.

## Próximas ações

**Primeiro:** consolidar XK-01/02 e XK-04 em ADRs e testar a ausência de storage de avisos paralelo. **Depois:** caracterizar políticas User/Fórum/LMS existentes e executar migrações pequenas sob testes. Toda frente atualiza README, roadmap, changelog, ownership e testes. Nenhuma fase assinala PASS por planejamento.

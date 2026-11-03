# Consolidação das integrações de mensagens — 2026-11-01

**Base de código:** `dev` em `c4a31574e2f224133f7163edea6976393afb0900`.
**Natureza:** implementação e registro operacional. Código, configuração versionada,
Runtime de desenvolvimento, testes Unit e Kernel foram verificados. Alpha e Beta não
foram acessados.

## Objetivo

Eliminar `aculta_wiki_messenger`, `aculta_lms_messenger` e
`aculta_commerce_messenger` como extensões Drupal. Comunicação, persistência,
leitura, contadores, preferências, digest e coordenação de avisos ficam em
`aculta_messenger`; transporte e política administrativa de e-mail ficam em
`aculta_mailer`. Wiki, LMS e Commerce mantêm fatos e regras de domínio.

## Arquitetura resultante

| Responsabilidade | Proprietário |
| --- | --- |
| Workflow editorial, propostas, revisão e acesso Wiki | `aculta_wiki` |
| Matrícula, elegibilidade educacional, avaliação, certificados e prazos | `aculta_lms` |
| Pedidos, titularidade, estados e regras comerciais | `aculta_commerce` |
| API pública de comunicação, consumidores, avisos, leitura, contadores, preferências e digest | `aculta_messenger` |
| Transporte, renderização e política administrativa de e-mail | `aculta_mailer` |

Adaptadores de domínio permanecem em `aculta_messenger/src/Integration/{Wiki,Lms,Commerce}`.
Módulos de domínio publicam `MessengerNoticeEvent` e consultam contratos públicos; não
gravam entidades de mensagem nem enfileiram e-mail diretamente.

### Wiki

- `WikiReviewHooks` só registra o fato editorial na fila transacional
  `aculta_wiki_editorial_events`. A condição `moduleExists('aculta_wiki_messenger')`
  foi removida.
- `WikiEditorialNoticePublisher` revalida a revisão e publica os avisos pessoais
  pelo contrato do Messenger. Em seguida chama
  `EditorialReviewNoticeInterface::notifyReview()`.
- O Messenger revalida a revisão, lê a configuração central e enfileira o e-mail
  administrativo no `aculta_mailer` com o tipo histórico `aculta_wiki_review`
  (`WikiEditorialReviewMailType`). Entrega deduplicada por verbete, revisão e
  destinatário.
- `review_notifications` e `review_recipients` migraram de `aculta_wiki.settings`
  para `aculta_messenger.settings` (`wiki_review_notifications`,
  `wiki_review_recipients`). O update `aculta_messenger_update_10014` copia somente
  quando o destino não tem valor explícito; conflitos são registrados e preservados.
- A tela `/painel-administrativo/aculta/mensagens` passou a ser o local central da
  configuração. A tela exclusiva `/painel-administrativo/aculta/wiki` foi removida.
- `WikiReviewNotifier`, `WikiReviewMailType`, o formulário exclusivo, o menu e o
  schema `aculta_wiki.settings` foram removidos.

### LMS

- `LearningEligibility`/`LearningEligibilityInterface` (em `aculta_lms`) concentram
  as regras educacionais de curso, conteúdo liberado, matrícula e prazo.
- `LearningNoticeRecipients`/`LearningNoticeRecipientsInterface` resolvem os
  destinatários e publicam os oito eventos pelo contrato público do Messenger.
- `LearningNotificationSubscriber` não depende mais de
  `Integration\Lms\LearningNoticeManagerInterface`.
- O worker técnico `ActivityDeadlineReminderWorker` consulta o contrato de
  destinatários do LMS; o agendamento e a entrega continuam no Messenger.
- O `LearningNoticeManager` mantém somente persistência (`record`), leitura,
  contadores e digest. As regras de elegibilidade não são duplicadas: os métodos
  de leitura consultam `LearningEligibilityInterface`.
- Chaves de deduplicação, IDs de fila (`aculta_lms_messenger_email`,
  `aculta_lms_deadline_reminders`) e o tipo de e-mail permanecem iguais.

### Commerce

A auditoria não justificou mudança de código. `CommerceOrderNoticePublisher` já
publica `store.order_placed` pelo contrato público, suprime o recibo nativo apenas
para contas registradas e mantém o recibo como fallback. Pedidos de visitantes
continuam no Commerce. Testes Unit e Kernel existentes passaram sem alteração.

## Sequência operacional executada

1. Checkout avançado para o head de `dev` (`c4a31574e`) em HEAD destacado.
2. Snapshot do `aculta_runtime` antes do gatilho de head:
   `aculta_runtime-pre-dev-c4a3157-20261101-074954.sql`, SHA-256
   `978dfea97be62d7363ce95d6585e64fbfe278f2e2d2ad014c503ea1ff50aec18`.
3. `updatedb` aplicou `aculta_events_update_10014` e `10015`; `config:status`
   permaneceu sem diferenças; `drush cr`; página principal e loja responderam HTTP 200.
4. Código de transição publicado em `dev` com os três `.info.yml` presentes.
5. Snapshot anterior à desinstalação:
   `aculta_runtime-pre-uninstall-lms-messenger-20261101-080649.sql`, SHA-256
   `6a4835e5643bbbff04381f2e0e4eed222d62e1f585da1c16d17f7f0f98104928`.
6. Preflight confirmou que nenhum módulo depende de `aculta_lms_messenger`.
7. `drush pm:uninstall aculta_lms_messenger` no Runtime de desenvolvimento.
   `aculta_wiki_messenger` e `aculta_commerce_messenger` já não estavam ativas.
8. `config/sync/core.extension.yml` atualizado; `config:status` sem diferenças;
   `drush cr`.
9. Pastas das três extensões removidas. READMEs e CHANGELOGs preservados em
   `docs/history/modules/` e `docs/history/changelog/`; status movidos para
   `docs/history/status/`.

## Verificações

| Verificação | Resultado |
| --- | --- |
| PHP lint dos arquivos alterados | PASS |
| PHPCS Drupal/DrupalPractice nos arquivos alterados | PASS |
| YAML dos serviços, rotas, schema e config alterados | PASS |
| Unit Wiki/Messenger (canal editorial) | 21 testes, 114 asserções PASS |
| Unit LMS/Messenger (elegibilidade e produtores) | 17 testes, 90 asserções PASS |
| Kernel `LearningNoticePersistenceKernelTest` | 4 testes, 52 asserções PASS (2 deprecações de contrib) |
| Unit Commerce | 4 testes, 23 asserções PASS |
| Kernel `CommerceOrderNoticePersistenceKernelTest` | 4 testes, 75 asserções PASS (2 deprecações, 3 notices de contrib) |
| Container do Runtime | serviços `aculta_lms.notice_eligibility`, `aculta_lms.notice_recipients` e `EditorialReviewNotice` instanciados |
| `config:status` após migração e desinstalação | sem diferenças |
| HTTP principal e loja | 200 / 200 |

Não foram executados testes de navegador, envio real de e-mail nem smoke autenticado
da tela central.

## Pendências operacionais

- **Alpha/Beta:** as três extensões não estavam ativas no Runtime de desenvolvimento
  além de `aculta_lms_messenger`, que foi desinstalada. Os demais ambientes não foram
  acessados. Antes do deploy que já não contém as pastas, cada ambiente com a extensão
  habilitada precisa executar `drush pm:uninstall aculta_lms_messenger` (e as demais,
  se ativas) com backup e preflight. Não declarar a remoção concluída globalmente antes
  dessa comprovação.
- **Rollback:** reverter somente o código não desfaz a desinstalação nem entregas de
  e-mail. A versão de transição e os snapshots acima são a base de recuperação.
- **Teste visual da tela central:** a seção "Avisos editoriais da Wiki" foi validada
  por lint/testes, não por navegador autenticado.

## Referências

- `docs/history/modules/aculta_{wiki,lms,commerce}_messenger.md`
- `docs/history/changelog/aculta_{wiki,lms,commerce}_messenger.md`
- `docs/history/status/aculta_{wiki,lms,commerce}_messenger.yml`
- `docs/portal/LMS-INTEGRATION-CONTRACTS.md`
- `docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md`

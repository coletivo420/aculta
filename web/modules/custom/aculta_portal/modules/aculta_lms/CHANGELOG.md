# Changelog — aculta_lms

## Não lançado — F7: matrícula sempre associada a turma

- Config Sync habilita `lms_classes`, desativa criação automática de turma e configura grants de educador.
- `EnrollmentFormHooks` valida a turma selecionada contra as turmas do curso original, inclusive após a conversão da membership pelo módulo LMS Classes. Matrícula de estudante é bloqueada quando não há turma; memberships de educador/admin podem continuar no nível do curso.
- `EnrollmentRoleHooks` em `aculta_user` concede `estudante` para membership de turma e preserva a exclusão de papéis de educador/admin.
- Autoinscrição e matrícula pela equipe foram escolhidas; sem confirmação de e-mail/termos, com aviso por e-mail (entrega F13). Sem turma padrão. O LMS ainda não associa pagamento Commerce à matrícula paga.
- RUNTIME STATUS: DEFERRED. Drush aponta `http://default`, não um URI `*.aculta.toca.net.br`; não houve import de configuração nem smoke test.

## 0.1.0-dev.2 — F6: página de curso e sala (implementação inicial)

- Acrescentado `CourseRoomBuilder` e campo extra configurável na página canônica
  de curso; o índice usa conteúdo e access do Drupal LMS/Group e liga para a
  rota nativa da primeira atividade de cada lição.
- A seção não expõe lições quando a conta não tem `take course`; resultados de
  access, curso e lições alimentam a cacheabilidade.
- Sem alteração de entidades LMS, matrícula, status, resposta ou storage.
- RUNTIME STATUS: DEFERRED; smoke test com membro, não membro e visitante
  pendente.

## 0.1.0-dev.1 — Fundação F1

Submódulo opcional, publisher DI e evento imutável de conclusão aprovada do
Drupal LMS. Testes Unit (2/2) e Kernel de instalação (1/1) PASS. Nenhuma
persistência, rota, configuração ou integração Messenger ativa é criada.
RUNTIME STATUS: DEFERRED; o submódulo não foi habilitado no Runtime.

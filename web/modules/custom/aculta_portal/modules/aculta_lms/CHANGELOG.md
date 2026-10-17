# Changelog — aculta_lms

## Não lançado — F7: matrícula sempre associada a turma

- Config Sync habilita `lms_classes`, desativa criação automática de turma e configura grants de educador.
- `EnrollmentFormHooks` valida a turma das memberships da equipe contra as turmas do curso original, bloqueia autoinscrição direta e exige que a turma pertença ao curso. Educadores e administradores podem manter sua membership no nível do curso.
- Educadores podem remover pela interface Group as matrículas manuais. O campo oculto `field_course_order` guarda a origem comercial; a exclusão de membership ligada a pedido de curso é negada enquanto houver pagamento ativo, inclusive reembolso parcial e pedido gratuito concluído. Só pedidos cancelados sem pagamento ou com todos os pagamentos estornados liberam a exclusão.
- O curso pode referenciar produto Commerce próprio. A página pública oferece turma/preço e envia item `curso` com turma ao checkout dedicado; cursos de preço zero também passam pelo pedido. O fluxo exige conta, permite cadastro e dispensa confirmação de e-mail no checkout de curso, mantendo a política geral de termos.
- `CourseOrderEnrollmentManager` concede membership Group após pedido de curso concluído e pago; reembolso parcial conserva acesso e estorno integral remove somente a origem do pedido, preservando memberships manuais e compras válidas. A autoinscrição direta do LMS é bloqueada.
- Render do formulário varia por usuário/permissões e Domain, e invalida conforme curso, turmas, produto e variações referenciados.
- `EnrollmentRoleHooks` em `aculta_user` concede `estudante` para membership de turma e preserva a exclusão de papéis de educador/admin.
- Matrícula por checkout e por equipe foram escolhidas; sem confirmação de e-mail/termos para matricular, com aviso por e-mail (entrega F13). Sem turma padrão.
- RUNTIME STATUS: DEFERRED. Drush confirmou `aculta_runtime` com URI explícito `https://aculta.toca.net.br`, mas `lms_classes` continua desabilitado no Runtime. A configuração F7 e checkout não foram importados; smoke de matrícula, turma cruzada, compra, reembolso e isolamento A/B não foram executados.

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

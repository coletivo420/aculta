# Changelog — aculta_lms

## Não lançado — F8: avaliações e feedback (implementação inicial)

- `aculta_lms` depende dos submódulos contrib LMS de plugins de resposta e comentários, sem novo pacote Composer.
- O plugin `free_text_review_feedback`, adaptado do Drupal LMS 1.2.4 sob GPL-2.0-or-later, oferece feedback formativo por frases e mantém a correção final exclusivamente manual pelo educador.
- O Drupal LMS já fornece múltipla escolha/seleção múltipla, verdadeiro/falso e completar lacunas, com variantes de feedback; o LMS mantém resposta, nota e progresso.
- `lms_answer_comments` e Core Comment fornecem diálogo ligado à resposta e avaliação manual, sem campo ou gradebook paralelo.
- Não mexe diretamente em contrib/Core e não altera respostas existentes.
- `AttemptArchive` preserva cópias das versões anteriores antes de edição, reavaliação ou reinício de resposta e mantém a conversa da resposta; histórico exige membership/acesso atual e é removido com o progresso, conta ou curso.
- Verificação incremental: 6 testes Unit (33 assertions), Kernel (2 testes, 11 assertions; persistência, acesso somente leitura e limpeza por conta), gates Portal (950 checks), imagem e slugs passaram; PHP lint passou. Executado com PHP 8.4.26; PHP 8.5 ainda não foi validado.
- F8 segue aberta até revisão de segurança, testes de ciclo de vida/access e smoke no Runtime. Nenhum histórico preexistente foi alterado ou migrado.
- RUNTIME STATUS: DEFERRED. Drush retornou URI `http://default`, fora do critério de identificação do Homelab; nenhuma operação persistente foi executada.

## Não lançado — F7: matrícula sempre associada a turma

- Config Sync habilita `lms_classes`, desativa criação automática de turma e configura grants de educador.
- `EnrollmentFormHooks` valida a turma das memberships da equipe contra as turmas do curso original, bloqueia autoinscrição direta e exige que a turma pertença ao curso. Educadores e administradores podem manter sua membership no nível do curso.
- Educadores podem remover pela interface Group as matrículas manuais. O campo oculto `field_course_order` guarda a origem comercial; a exclusão de membership ligada a pedido de curso é negada enquanto houver pagamento ativo, inclusive reembolso parcial e pedido gratuito concluído. Só pedidos cancelados sem pagamento ou com todos os pagamentos estornados liberam a exclusão.
- O curso pode referenciar produto Commerce próprio. A página pública oferece turma/preço e envia item `curso` com turma ao checkout dedicado; cursos de preço zero também passam pelo pedido. O fluxo exige conta, permite cadastro e dispensa confirmação de e-mail no checkout de curso, mantendo a política geral de termos.
- `CourseOrderEnrollmentManager` concede membership Group após pedido de curso concluído e pago; reembolso parcial conserva acesso e estorno integral remove somente a origem do pedido, preservando memberships manuais e compras válidas. A autoinscrição direta do LMS é bloqueada.
- Render do formulário varia por usuário/permissões e Domain, e invalida conforme curso, turmas, produto e variações referenciados.
- `EnrollmentRoleHooks` em `aculta_user` concede `estudante` para membership de turma e preserva a exclusão de papéis de educador/admin.
- Matrícula por checkout e por equipe foram escolhidas; sem confirmação de e-mail/termos para matricular, com aviso por e-mail (entrega F13). Sem turma padrão.
- RUNTIME STATUS: CONFIG APLICADA no Runtime de teste em 2026-10-18. Após backup, habilitei `aculta_lms`/`lms_classes`, migrei o setting de doações e importei parcialmente 29 objetos F7; nenhum objeto ausente foi removido. O único curso existente foi preservado; não criei turma, produto ou pedido. `create_default_class` está `false`. Smoke de matrícula, turma cruzada, compra paga/gratuita, reembolso e isolamento A/B segue DEFERRED. Restam 284 diferenças globais; não houve importação global. Backup privado: `/home/aculta/private/aculta/config-reconcile-20261018/aculta_runtime-before-f7.sql.gz`.

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

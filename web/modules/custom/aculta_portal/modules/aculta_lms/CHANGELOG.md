# Changelog — aculta_lms

## 0.1.0-dev.7 — permissão de tópico LMS herdada na turma — 2026-10-20

- `lms_classes.settings` mapeia `grade students` do curso para acesso Group
  Node aos tópicos de discussão da turma. O `ClassPermissionCalculator` real
  foi validado em Kernel: Educador do curso permitido; pessoa externa, inclusive
  com papel global `lms_teacher`, sem acesso. 1 teste / 24 assertions PASS;
  37 depreciações Core/contrib.
- A configuração não ativa o adapter nem cria tópicos/Comments. Runtime
  DEFERRED; mudança de Config Sync ainda não foi importada.

## 0.1.0-dev.6 — review de texto livre sem classe de design — 2026-10-19

- `FreeTextReviewFeedback` marca as seções e frases do retorno com `data-portal-ui` (`feedback-matched`, `feedback-unmatched`, `phrase-match`, `phrase-mismatch`). As classes de estado do LMS (`correct-answer`, `wrong-answer`) continuam, vindas da constante do contrib.
- RUNTIME STATUS: DEFERRED.

## Integrações de conteúdo em andamento — 2026-10-19

- `aculta_lms` declara as dependências `aculta_image` e
  `aculta_pdf_viewer_group`: imagens de capa seguem a política ACULTA e Media
  `course_document` preserva acesso de turma pelo Group e leitura pelo viewer
  PDF compartilhado.
- Contrato expandido para submissões com imagem e discussão ligada a curso ou
  atividade. Upload de imagem de entrega e vínculo de discussão com
  `aculta_forum` permanecem objetivos a implementar/homologar; o módulo de
  fórum ainda não tem API executável. Nenhuma integração cria storage paralelo.
- `aculta_lms_h5p` já encaminha imagens do editor/assets via `aculta_image`;
  PDFs complementares continuam sob Media/viewer e Group, enquanto assets H5P
  são servidos pelo controlador H5P protegido. Não houve importação de
  configuração nem mutação do Runtime neste registro.

## Correção da sala de curso usada na homologação F10 — 2026-10-19

- Corrige a resolução da classe `CourseEnrollmentPurchaseForm` chamada pela sala do curso; o namespace incorreto gerava 500 para visitantes sem acesso `take`.
- Unit cobre a montagem do formulário de inscrição pela sala. Runtime `cursos`: estudante matriculada abriu a sala e reproduziu H5P; visitante anônimo foi bloqueado na rota de arquivo privado.
- RUNTIME STATUS: HOMOLOGADO EM TESTE; produção não foi alterada.

## Continuação F7 — participação sem confirmação de e-mail

- Corrige a divergência entre a matrícula em cursos, que não exige confirmação de e-mail, e as rotas de aula/resposta que ainda exigiam a marca global `email_confirmed`.
- As rotas continuam exigindo acesso `take` atual do Group e aceite vigente dos termos. Não amplia acesso de pessoas sem matrícula.
- Testes Unit do `ProtectedRouteAccessCheck`: estudante não confirmado com `take` permitido passa; pessoa sem `take` continua bloqueada. Runtime `cursos`: PASS com usuário temporário sem confirmação de e-mail e matrícula Group; a atividade de revisão renderizou. Usuário e membership removidos via Entity API; nenhum conteúdo do curso foi alterado.
- RUNTIME STATUS: código aplicado por cache rebuild e smoke HTTP aprovado no Runtime de teste `aculta_runtime`.

## Não lançado — F10: auditoria H5P

- A inspeção do endpoint de upload H5P `2.0.0-beta1` confirmou que a permissão de
  tipos restritos não é verificada na ingestão de pacote. H5P permanece
  desativado até correção upstream ou patch Composer acompanhado de teste de
  regressão; nenhum estado do Runtime foi alterado.

## Não lançado — F8: avaliações e feedback (implementação inicial)

- `aculta_lms` depende dos submódulos contrib LMS de plugins de resposta e comentários, sem novo pacote Composer.
- A navegação administrativa lateral recebe um ícone SVG próprio de livro aberto pelo Icon API do Drupal Navigation; a barra Toolbar clássica mantém seu fallback customizado. Core e contrib não foram editados.
- O painel `/painel-administrativo/lms` e suas subpáginas usam traduções pt-BR para menus, descrições, cursos, turmas, aulas, atividades e configurações; sem alterar rotas ou títulos de conteúdo.
- F9 adiciona Webform Score e LMS File Upload via Composer; o download privado em `/resposta/{lms_answer}/baixar-arquivo` exige acesso atual ao curso para o autor e mantém a permissão de avaliação para educadores.
- Kernel F9 verifica autoria em uma submissão Webform persistida: proprietário com permissão pode visualizar a nota, outra conta não pode e edição direta é negada; Runtime ainda não contém fixture educacional para smoke HTTP/browser.
- F10 adiciona `drupal/h5p` 2.0.0-beta1 e dependências ao Composer para auditoria/avaliação de integração. Módulos H5P continuam desativados no Runtime; não foram instaladas bibliotecas de conteúdo nem criados registros. Ativação aguarda correção verificada do bypass de bibliotecas restritas, auditoria de PII/Hub, CSP e isolamento.
- O plugin `free_text_review_feedback`, adaptado do Drupal LMS 1.2.4 sob GPL-2.0-or-later, oferece feedback formativo por frases e mantém a correção final exclusivamente manual pelo educador.
- O Drupal LMS já fornece múltipla escolha/seleção múltipla, verdadeiro/falso e completar lacunas, com variantes de feedback; o LMS mantém resposta, nota e progresso.
- `lms_answer_comments` e Core Comment fornecem diálogo ligado à resposta e avaliação manual, sem campo ou gradebook paralelo.
- Não mexe diretamente em contrib/Core e não altera respostas existentes.
- `AttemptArchive` preserva cópias das versões anteriores antes de edição, reavaliação ou reinício de resposta e mantém a conversa da resposta; histórico exige membership/acesso atual e é removido com o progresso, conta ou curso.
- Verificação F8: Unit e Kernel do `aculta_lms` passaram (15 testes, 259 assertions); `lms_answer_plugins` contrib passou (2 testes, 4 assertions). Kernel cobre entidades reais de Answer/Comment, comentários publicados, edição, reavaliação, exclusão e limpeza por progresso/conta/curso; valida isolamento por turma, permissões de aluno/educador, saída como texto e cache por usuário/permissões. Gates Portal (950 checks), imagem, slugs e PHP lint passaram. Executado com PHP 8.4.26; PHP 8.5 ainda não foi validado.
- Os Kernel tests reportam 34 depreciações de Group/LMS/Views contrib e `EntityBase::__get('original')` disparado durante persistência do ecossistema; nenhum arquivo contrib/Core foi alterado. Nenhum histórico preexistente foi migrado.
- Runtime de teste: Drush confirmou `http://cursos.aculta.toca.net.br`/`aculta_runtime`; aplicado apenas o update 10001 após backup privado (modo 0600). Smoke sintético PASS: persistência, estudante sem matrícula negado, educador `lms_teacher` com leitura, edição negada e registro removido. As contas `teste.estudante` e `teste.educador` foram criadas; credenciais apenas em `secrets/lms-test-accounts.env` local (ignorado pelo Git, modo 0600). Autenticação das duas contas validada. RUNTIME STATUS: UPDATE APLICADO; após esta correção foi reconstruído o cache no Runtime `aculta_runtime`. Testes de ciclo de vida e acesso reais passaram em Kernel no MariaDB dedicado `aculta_simpletest`; smoke visual por navegador segue DEFERRED. Matrículas e pedidos continuam fora do escopo F8.

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

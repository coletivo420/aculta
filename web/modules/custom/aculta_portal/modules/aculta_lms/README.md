# ACULTA LMS

Submódulo opcional de integração educacional do Portal. Drupal LMS e Group são
as fontes de verdade; `aculta_lms` não cria matrícula, resposta, nota,
progresso, certificado, mensagem ou amizade paralelos.

## F11 — emissão e revogação por educadores

O Kernel percorre os submits dos formulários de emissão e revogação com um
educador real, autorizado por `grade students` no curso e sem permissão
administrativa global. A emissão redireciona para a verificação pública pelo
código, e a revogação preserva o registro e o motivo informado.
`CertificateLifecycleTest`: 5 testes / 159 assertions PASS; 38 depreciações de
Core/contrib. A conclusão elegível aparece no seletor. Smoke Runtime dos
formulários e revisão visual permanecem pendentes.

## F16 — referências removidas em respostas

Os hooks do formulário de prazo por atividade e do aviso de feedback do
educador resolvem curso pelos campos de referência LMS e toleram LessonStatus
ou CourseStatus removidos durante a limpeza assíncrona. Nesses casos, o
formulário não recebe configuração de prazo e o evento de feedback não é
publicado. Unit do módulo: 41 testes / 192 assertions PASS; 5 depreciações do
ecossistema de teste. A execução local usa PHP 8.4.26 e não valida PHP 8.5.
O arquivamento pré-exclusão e a consulta do histórico também deixam de forma
segura de operar se o LMS já removeu status, curso, aula ou atividade; nesse
caso não é criado snapshot incompleto. A suíte Unit completa passou com 46
testes / 215 assertions (6 depreciações). `AttemptArchiveLifecycleTest` passou
com 9 testes / 369 assertions no banco Kernel dedicado; 42 depreciações vieram
do Core e contrib. O access do histórico também nega estudante ou educador
bloqueado e revalida após bloqueio e revogação da membership.
O download de arquivo privado de resposta também nega estudante ou educador
bloqueado antes de consultar a autorização LMS. Kernel lifecycle: 9 testes /
369 assertions PASS (42 depreciações); Unit completo: 46 testes / 215
assertions PASS (6 depreciações). O status ativo é verificado pelo User
carregado também quando a requisição entrega apenas AccountProxy/session.
O resolvedor tipa entidades de referência com `FieldableEntityInterface`; o
PHPCS Drupal/DrupalPractice passou nos arquivos novos de resolução e no
`AttemptArchiveManager`.
Na emissão de certificado F11, o manager e o formulário também usam as
referências dos campos do `CourseStatus`: se usuário ou curso tiver sido
removido na limpeza assíncrona, o manager responde 422 e o formulário omite a
conclusão, sem erro 500. O formulário de resposta também lê a referência da
atividade pelo campo e omite o prazo se a atividade foi removida, sem invocar
`Answer::getActivity()`: `ActivityDeadlineFormHooksTest` 2/6. O plugin de
resposta aberta também descarta referências removidas: score seguro, formulário
sem feedback dependente da atividade e indicação na tela de avaliação;
`FreeTextReviewFeedbackTest` 4/20. Kernel focal: 4 testes / 126 assertions;
regressão Kernel LMS/Messenger: 23 testes / 712 assertions PASS, com 92
depreciações upstream. Unit LMS/Messenger: 84 testes / 445 assertions PASS, com
8 depreciações PHPUnit. F11 executa emissão/revogação com educador contextual e
confirma a opção elegível no formulário: Kernel focal 5/159. Smoke Runtime
autenticado e revisão visual permanecem pendentes.
No Runtime de teste, Drush confirmou o módulo habilitado e sem updates; após
rebuild do cache, o loader resolveu os dois usuários de teste por `UserSession`.
O rebuild teve warning de assets H5P e terminou com sucesso. Nenhuma fixture foi
criada, então o download real de resposta continua sem smoke positivo.

## Integrações de conteúdo

- Imagens de capa de curso usam `aculta_image` e sua `UploadPolicy`; imagens
  enviadas ao editor H5P usam `aculta_image.upload_ingester` e o ciclo de vida
  de File/uso compartilhado. Imagens anexadas a respostas do LMS passam pela
  `UploadPolicy` e por `ImageInspector` antes de serem aceitas; continuam em
  storage privado e são entregues pela rota de download protegida pela resposta
  e pela matrícula vigente.
- PDFs de material de turma usam Media `course_document`, relação de acesso
  Group e o viewer `aculta_pdf_viewer`; o acesso ao arquivo é revalidado pela
  rota privada do Core. O módulo declara a dependência do submódulo Group do
  viewer para evitar configuração de curso sem o plugin correspondente.
- Discussões LMS usam o adapter opcional `aculta_forum_lms`, com Node e Comment
  compartilhado em Group de curso/turma, access contextual, membership
  revalidada e moderação por `grade students` do curso. `lms_classes.settings`
  herda essa permissão Group Node para turmas. O adapter e suas permissões estão
  ativos no Runtime de teste; ainda não há Node ou Comment LMS real nesse
  Runtime, então autorização por conteúdo HTTP e visual continuam pendentes.
  A integração atual não associa uma discussão a uma atividade específica; esse
  trabalho pertence à F15. O contrato e as condições estão em
  [LMS-INTEGRATION-CONTRACTS.md](../../../../../../docs/portal/LMS-INTEGRATION-CONTRACTS.md).

As integrações ainda exigem aceite ponta a ponta no Runtime após a configuração
de cada fluxo; a dependência do PDF garante disponibilidade do plugin, mas não
substitui a validação de permissão/cache nem importa conteúdo. Testes atuais do
adapter Forum LMS: Unit 18/190 assertions e Kernel 2/127 assertions PASS; o
Kernel registra 43 depreciações upstream e cobre tópicos em curso/turma, não
discussões específicas de atividade.

O validador da resposta só aplica a política quando a extensão autorizada pela
atividade é raster (`png`, `gif`, `jpg`, `jpeg` ou `webp`). Demais extensões
mantêm a validação nativa do LMS File Upload. Imagens inválidas, com MIME
incompatível ou acima de 10 MB são recusadas. Unit cobre imagem válida, arquivo
inválido, limite de tamanho e delegação de arquivos não raster à validação
nativa. Kernel `AttemptArchiveLifecycleTest` passou com 8 testes / 322
assertions, inclusive download privado, revogação de membership e acesso de
educador. O hook OOP que liga o constraint ao formulário também é descoberto no
Kernel, e Unit confirma que ele preserva a allowlist da atividade. O smoke ponta
a ponta de upload e revogação no Runtime ainda está pendente.

## F1 — Fundação

O serviço `aculta_lms.course_completion_publisher` publica o evento
`CourseCompletedEvent` através do Event Dispatcher do Drupal quando recebe um
status LMS finalizado e aprovado. A chamada deve ocorrer somente depois da
persistência da transição para aprovado. O contrato inclui identificador UUID
do status de origem, curso, conta e instante de conclusão; nunca inclui nota,
resposta, arquivo ou corpo de mensagem.

O publisher não persiste, deduplica, altera estado LMS ou decide destinatários.
Consumidores devem reabrir a entidade LMS e revalidar acesso/membership no
momento de uso. A deduplicação, preferências, canais e entrega pertencem ao
contrato LMS → Messenger da fase F13. Nenhum produtor ou consumidor está
ativado nesta fase.

O serviço usa DI explícita e aparece em ACULTA Portal → Status como helper
quando o módulo estiver habilitado. A extensão depende de `aculta_portal`,
Drupal LMS e Group, mas não é habilitada automaticamente pelo `aculta_portal`.
Não possui `config/install`, schema, entidade, rota ou hook de instalação.

## F6 — Página de curso e sala (implementação inicial)

O serviço `aculta_lms.course_room_builder` adiciona à página canônica de Group
do tipo `lms_course` uma seção configurável de conteúdo do curso. O índice
respeita o acesso `take course` do curso pai e inclui somente lições publicadas;
links de aula continuam
na rota nativa do LMS, com os slugs portugueses do Portal. Sem autorização para
fazer o curso, a seção fica vazia e não revela os títulos das lições. O módulo
não cria progresso, matrícula, resposta ou armazenamento.

O campo extra pode ser posicionado no Manage Display do bundle `lms_course`.
Render arrays incluem cacheability do curso, lições e resultados de acesso,
variando por usuário e permissões do Group. F6 ainda precisa de smoke test
integrado com usuário matriculado, visitante e não membro no Runtime.

## F7 — Matrícula em turmas

`EnrollmentFormHooks` valida turmas de memberships criadas pela equipe e bloqueia
a autoinscrição direta do LMS. Na página do curso, `CourseEnrollmentPurchaseForm`
mostra turmas publicadas e o valor do produto Commerce ligado ao curso. Cursos
pagos e gratuitos passam pelo checkout `curso`; o item registra a turma escolhida.
`CourseOrderEnrollmentManager` concede membership Group quando o pedido está
concluído e pago. A role `lms_class-member` tem escopo insider e é aplicada pelo
Group, sem ser gravada como role individual. Smoke no Runtime confirmou que um
pedido gratuito concluído grava a origem em `field_course_order`, concede o
papel global `estudante` e preserva a membership manual do educador. Reembolso
integral revoga somente membership originada pelo pedido; outra compra válida
mantém acesso e matrícula manual permanece independente. Educadores podem
remover memberships manuais, mas não memberships protegidas por pagamento ativo.
Unit testa entitlement de pedido gratuito, pagamento concluído, reembolso parcial
e total e pedido incompleto. `EnrollmentFormHooksTest` também protege contra
remoção manual de matrículas de compra paga/parcialmente reembolsada ou pedido
gratuito concluído e falha fechada quando a origem não pode ser lida; matrícula
manual e pedido totalmente reembolsado seguem removíveis. A confirmação visual
da interface ainda aguarda smoke HTTP. O Runtime não foi conectado ao gateway
para simular uma transação remota. Se o armazenamento retornar um registro de
pagamento inválido, o acesso também é negado sem erro 500.

Uma pessoa administradora cria o produto e a variação Commerce do curso e associa
esse produto ao Group do curso. A variação possui preço zero ou pago; a página
canônica do produto não tem formulário de compra para evitar matrícula sem turma.

O checkout permite criar conta, não exige e-mail confirmado nessa rota e mantém
a política geral de aceite de termos. O aviso de matrícula por e-mail integra-se
na F13. No Runtime de teste, `aculta_lms` e `lms_classes` estão habilitados e 29
objetos de configuração F7 foram aplicados após backup; nenhuma configuração
ausente foi removida e o curso existente foi preservado. `create_default_class`
permanece desligado. RUNTIME STATUS: configuração aplicada; integração de
pedido gratuito concluído PASS; fluxo de checkout HTTP, pedido pago, estorno/
reembolso e isolamento A/B continuam DEFERRED. Matrícula manual e remoção pela
interface de educador também precisam de smoke HTTP.
Detalhes, limites e caminho de recuperação estão no [changelog](CHANGELOG.md).

## F8 — Avaliações e feedback (implementação inicial)

Os tipos objetivos usam os plugins do submódulo contrib `lms_answer_plugins`:
`select` (resposta única ou seleção múltipla), `select_feedback`, `true_false`,
`true_false_feedback`, `fill_in_the_blanks` e `fill_in_the_blanks_feedback`.
O educador escolhe o plugin ao criar um tipo de atividade; uma mesma seleção
pode ser configurada como resposta única ou múltipla.
Para resposta discursiva, `free_text` mantém a correção manual. O plugin
`free_text_review_feedback` acrescenta feedback formativo configurável por
frases, mas deliberadamente deixa a nota pendente para avaliação do educador;
correspondência de palavras nunca é tratada como correção final de texto livre.
Esse adaptador deriva do plugin `FreeTextFeedback` do Drupal LMS 1.2.4, licença
GPL-2.0-or-later, com origem registrada no código.

`lms_answer_comments` integra a avaliação manual à Comment API do Core e deixa
aluno e educador manterem comentários ligados à resposta original. LMS continua
fonte da tentativa corrente, da nota e da conclusão. Como LMS 1.2.4 apaga uma
resposta ao substituí-la, reiniciar a aula ou reavaliá-la, `aculta_lms` grava
somente o estado anterior numa entidade interna e protegida de auditoria. Isso
preserva o texto respondido, pontuação, horário e a conversa da resposta sem
criar outro gradebook. Nenhuma nota é importada de Webform, H5P ou outro sistema.

O histórico só aparece no detalhe da resposta e revalida acesso no momento da
leitura: estudantes precisam manter `take course`; educadores precisam de
`grade students` na turma. Os registros são apagados ao excluir o progresso do
curso, a conta ou o curso. Assim, matrícula revogada não dá acesso ao arquivo
histórico, e exclusões previstas pelo ciclo de vida removem as cópias.

A nota mínima continua configurável por aula no LMS. Pontos de atividade são
inteiros nativos do LMS; a nota da aula é a soma ponderada dividida pelo máximo,
arredondada para percentual inteiro pelo LMS. Múltipla seleção dá crédito
proporcional aos acertos, desconta opções erradas e limita o resultado mínimo
a zero. O educador pode ajustar a nota de respostas abertas pela avaliação
nativa do LMS.

**F8 está implementada nesta branch e seus testes automatizados passaram.** A
suíte Unit e Kernel do `aculta_lms` passou (15 testes, 259 assertions), cobrindo
Answer/Comment reais, versões anteriores, reavaliação, exclusão, limpeza por
progresso/conta/curso, acesso de aluno e educador, isolamento entre turmas,
saída em texto e cache de permissões. O teste Unit de seleção do `lms_answer_plugins`
contrib também passou (2 testes, 4 assertions). Gates Portal, imagem, slugs e
lint passaram. Os Kernel tests reportaram 34 depreciações upstream em Group,
LMS, Views e API de entidades do Core. O smoke visual no navegador ainda não
foi executado; a adaptação local segue pequena e isolada em `aculta_lms`. A
barra administrativa usa o ícone próprio de livro aberto do módulo, em lugar
do capelo padrão do LMS contrib.

RUNTIME STATUS: TABELA INSTALADA E SMOKE PARCIAL. Em 2026-10-18, Drush
confirmou `http://cursos.aculta.toca.net.br` e `aculta_runtime`; foi aplicado
somente `aculta_lms_update_10001`, após dump privado com modo `0600`. O teste
sintético confirmou persistência, negação para estudante sem matrícula e acesso
de leitura para educador com `lms_teacher`; edição foi negada e o registro foi
removido ao fim do teste. As contas `teste.estudante` e `teste.educador` ficam
no Runtime; as credenciais estão em `secrets/lms-test-accounts.env` (local,
ignorado pelo Git, modo `0600`). A conta estudante só recebe o papel global ao
entrar em uma turma. Smoke visual pela interface ainda está pendente. Comentários, isolamento entre turmas e ciclo de vida foram exercitados nos Kernel tests no banco dedicado; não são mutações do Runtime.

Os testes de plugins F8 devem ser executados a partir de `web/core` com o
PHPUnit do Composer; os Kernel tests usam exclusivamente o MariaDB dedicado
descrito em `docs/operations/BANCO-DE-TESTE.md`.

## F11 — Certificados (implementação em andamento)

`CourseCertificateManager` emite um retrato imutável somente para status LMS
finalizado e aprovado. A emissão exige permissão `grade students` no curso ou
`administer lms`; o estudante não precisa manter membership depois de ter
concluído o curso. Reemissão revoga a emissão anterior e cria uma nova versão
vinculada; reemissão revoga a anterior na mesma transação. Educadores também
podem revogar com motivo e o histórico permanece. Conta excluída não remove certificado.

O campo numérico `field_course_hours` guarda a carga horária declarada pela
equipe no curso. O registro armazena nome de exibição, curso, horas, conclusão,
emissão, versão de modelo, estado e código aleatório opaco. A página pública de
verificação mostra somente os quatro dados públicos aprovados (nome, curso,
horas e estado); o Kernel confirma que e-mail e motivo interno de revogação não
aparecem. O código também habilita o PDF
do certificado. Entity Print 2.19.0/Dompdf gera o PDF a partir do snapshot já
validado, com escaping e carregamento remoto/PHP embutido desativados. A página
de verificação exibe QR gerado pelo serviço `aculta_qrcode`; o SVG é servido
sem gravar arquivo e o PDF incorpora PNG gerado em memória. A URL absoluta é
resolvida pelo `DomainPurposeManager` no purpose `main`; o LMS não mantém geração
QR própria.

Config Sync declara o campo do curso. O storage da entidade e o campo com mínimo
de uma hora foram aplicados separadamente no Runtime de teste após revisão do
diff; a configuração ativa do formulário foi preservada e não há valor no curso
existente. O smoke anônimo de código inexistente respondeu 404 após reconstruir
o container. Em 2026-10-21, um fixture temporário real de User, Group e
CourseStatus aprovado foi criado via APIs Drupal. O manager emitiu o snapshot;
o controlador real apresentou os quatro dados públicos, gerou QR SVG e PDF
válidos com o `aculta_qrcode` e Entity Print/Dompdf instalados. Em seguida, um
segundo fixture repetiu a emissão com listeners de conclusão/certificado
temporariamente removidos e fez GET HTTP anônimo às três rotas: página pública,
QR SVG (`image/svg+xml`) e PDF (`application/pdf`, assinatura `%PDF`) responderam
200. Na primeira execução, salvar o CourseStatus temporário acionou o evento
normal de conclusão; a mensagem, a chave de deduplicação e o agregado estatístico
dessa fixture foram identificados e removidos. Em ambas as execuções, User,
Group, status e certificado foram excluídos por APIs Drupal; a fila de e-mail
terminou vazia e os totais voltaram ao estado anterior. RUNTIME STATUS: parcial;
emissão/revogação pelo formulário, revisão visual do QR/PDF e aceite do modelo
permanecem pendentes.

## F12 — Painéis de aprendizagem (implementação inicial)

`/minha-aprendizagem` apresenta apenas os cursos autorizados da própria conta,
progresso em aulas, avaliações pendentes e certificados emitidos. A navegação da
conta recebe a seção por hook do Portal, sem alterar o shell ou duplicar a rota
de cursos.

O painel `/painel-administrativo/lms/turma/{group}/painel` exige a permissão
contextual `grade students` no curso associado. Exibe apenas contagens agregadas
da turma (matrículas, início, andamento, avaliação pendente, aprovação e
reprovação), sem nomes, respostas ou texto de feedback. Educadores e
administradores não são contados como estudantes, e status de pessoas fora da
turma são excluídos. Cache depende dos memberships e contas consultados para
invalidar alterações de participação/roles. Group/LMS continuam fontes de
verdade e as páginas não usam cache compartilhado.

Unit dos painéis e do PDF: 4 testes/18 assertions (escopo próprio, menu
autenticado, PDF indisponível e turma órfã); em conjunto com os Kernel de
instalação/certificados, 6 testes/76 assertions PASS. Unit total: 20
testes/106 assertions.
Kernel de Group/LMS comprova contagens para dois estudantes, exclusão de
educador/admin e outsider e ausência de dados identificáveis (1 teste/38
assertions). Revisão visual/browser, acesso do dashboard do estudante com
fixtures completas e smoke no Runtime continuam pendentes; RUNTIME STATUS:
DEFERRED. Em `dev.62`, o Kernel integra o painel estudantil a certificados de
duas contas e confirma que somente o certificado da pessoa autenticada aparece,
com a tag de cache dessa entidade. Focal: 1 teste/41 assertions.

Suítes do `aculta_lms` revalidadas em 2026-10-21: Unit 51 testes/281
assertions; Kernel MariaDB 20 testes/634 assertions PASS. O Kernel registrou 90
depreciações de Core/contrib e uma de `webform_score`; a Unit registrou 8
depreciações do PHPUnit.

Regressão combinada atual do LMS e `aculta_lms_messenger`: Unit 84 testes/445
assertions; Kernel MariaDB 23 testes/712 assertions PASS. No total, houve 8
depreciações PHPUnit e 92 de Core/contrib, sem falhas.

O Kernel `LearningDashboardKernelTest` reproduz o contrato de acesso do LMS:
`take course` permite o currículo da pessoa matriculada, embora `view` genérico
da Lesson seja negado. Aula não publicada fica fora do total e do progresso.
Revisão visual e smoke Runtime seguem pendentes.

`ClassActivityDeadlineFormTest`: 2 testes/8 assertions PASS para fuso da conta e
fallback UTC. `LearningDashboardControllerTest`: 1/4 assertions PASS. Unit
total: 39 testes/185 assertions PASS; 5 depreciações do
PHPUnit.
`CertificateLifecycleTest`: 3 testes/97 assertions PASS, cobrindo o snapshot
público, cache e endpoint QR SVG; 38 depreciações upstream.
`ClassDashboardKernelTest`: 1 teste/43 assertions PASS, incluindo a política de
cache do painel educador.
O download privado também falha fechado durante a exclusão assíncrona de um
LessonStatus, quando a resposta ainda existe, mas já perdeu o contexto de curso.
Kernel `AttemptArchiveLifecycleTest`: 9 testes/363 assertions PASS; caso
dedicado 1/39 assertions. Naquela rodada, a suíte Kernel totalizou 19
testes/594 assertions, com 90 depreciações de Core/contrib.

Validação desta entrega parcial: Kernel isolado 6 testes/76 assertions; Unit
16/88; gates Drupal 11+ (1007 checks), índice de status, slugs e imagens PASS;
Composer audit sem advisories. O Entity Print 2.19.0 gera três deprecações
Drupal 11.2/11.3 em código upstream; nenhuma alteração foi feita no contrib.

## F13 — Avisos e prazos por turma (implementação funcional; revisão visual pendente)

Educadores com `grade students` no curso e administradores podem definir,
alterar e remover o prazo de uma atividade em `/painel-administrativo/lms/turma/{group}/atividade/{activity}/prazo`.
O prazo fica na relação Group turma↔atividade (`field_activity_due_at`), então
uma atividade reutilizada pode ter prazos diferentes em cada turma. A tela usa
o fuso da conta do educador; a área de atividade do estudante e o aviso usam o
fuso da conta que consulta.

Definir ou alterar o prazo gera aviso no painel e no resumo diário opt-in por
meio de `aculta_lms_messenger`/`aculta_messenger`. O lembrete é enfileirado na
janela de 24 horas antes do prazo. A mensagem não envia e-mail transacional e é
revalidada contra publicação, curso e membership atual.
O módulo `lms_classes` herda `take course` no curso pai a partir da matrícula na
turma; os leitores de prazo e avisos conferem essa permissão no curso e exigem
membership ativa na turma.
O responsável confirmou registrar o prazo por atividade na turma. Unit
LMS/adapter: 47 testes / 210 assertions PASS; Kernels de instalação/Group/Message:
6 testes / 85 assertions PASS. Smoke temporário no Runtime confirmou persistência
do aviso e lembrete, integração com aviso pessoal e worker; as fixtures e filas
foram removidas. Não houve envio de e-mail nem espera pelo Cron agendado. A
revisão visual autenticada permanece DEFERRED até o aceite do acordo geral pelo
responsável.
`CourseContentEventHooksTest` também confirma que editar uma atividade já
publicada sem liberar conteúdo novo não gera aviso: F13 notifica transições e
mudanças acionáveis, não edição editorial. Focal 4 testes/11 assertions; Unit
LMS/Messenger 79/407 PASS (8 depreciações PHPUnit).

## Testes

- `tests/src/Unit/CourseCompletionPublisherTest.php`: publicação apenas de
  status passado e finalizado; falha e progresso não publicam evento.
- `tests/src/Unit/AttemptArchiveManagerTest.php`: preserva dados e notas
  anteriores antes de edição/reavaliação/reinício e ignora salvamento sem
  alteração.
- `tests/src/Unit/FreeTextReviewFeedbackTest.php`: frases orientam somente o
  feedback formativo e nunca finalizam a nota sem avaliação do educador.
- `tests/src/Kernel/AcultaLmsInstallTest.php`: instalação isolada, registro DI, persistência, bloqueio de edição e limpeza por conta.
- `tests/src/Unit/LearningDashboardManagerTest.php`: painel pessoal isolado por usuário e negação segura para turma sem curso pai.
- `tests/src/Unit/ClassActivityDeadlineFormTest.php`: formulário interpreta o prazo no fuso horário da conta do educador e usa UTC como fallback.
- `tests/src/Kernel/AttemptArchiveLifecycleTest.php`: ciclo real de Answer/Comment, comentários publicados, acesso e cache por turma/permissão, saída em texto e limpeza por progresso/conta/curso.
- `web/modules/contrib/lms/modules/lms_answer_plugins/tests/src/Unit/SelectBaseAnsweringFormTest.php`: teste upstream do formulário de seleção.

Execute os testes a partir de `web/core` com PHPUnit do Composer. O Kernel test usa o banco MariaDB
de teste dedicado (nunca o Runtime, nunca SQLite). Configuração, procedimento e resultados estão em
`docs/operations/BANCO-DE-TESTE.md`; a senha fica em `secrets/simpletest-db.env` e não entra no repositório:

```sh
cd web/core
set -a && . ../../secrets/simpletest-db.env && set +a
mkdir -p /tmp/aculta-lms-test-output
BROWSERTEST_OUTPUT_DIRECTORY=/tmp/aculta-lms-test-output \
../../vendor/bin/phpunit -c phpunit.xml.dist \
  ../modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AcultaLmsInstallTest.php
```

Runtime/Homelab não faz parte do teste.

## Documentação

- [Roadmap do LMS e PDF global](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md)
- [Contratos de integração](../../../../../../docs/portal/LMS-INTEGRATION-CONTRACTS.md)
- [Matriz de segurança e arquivos](../../../../../../docs/portal/SECURITY-ACCESS-FILES-MATRIX.md)
- [Changelog](CHANGELOG.md)

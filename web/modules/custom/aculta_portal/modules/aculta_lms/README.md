# ACULTA LMS

Submódulo opcional de integração educacional do Portal. Drupal LMS e Group são
as fontes de verdade; `aculta_lms` não cria matrícula, resposta, nota,
progresso, certificado, mensagem ou amizade paralelos.

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
respeita `view` de cada lição e o acesso `take course`; links de aula continuam
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
concluído e pago. Reembolso integral revoga somente membership originada pelo
pedido; outra compra válida mantém acesso e matrícula manual permanece
independente. Educadores podem remover memberships manuais, mas não memberships
protegidas por pagamento ativo.

Uma pessoa administradora cria o produto e a variação Commerce do curso e associa
esse produto ao Group do curso. A variação possui preço zero ou pago; a página
canônica do produto não tem formulário de compra para evitar matrícula sem turma.

O checkout permite criar conta, não exige e-mail confirmado nessa rota e mantém
a política geral de aceite de termos. O aviso de matrícula por e-mail integra-se
na F13. No Runtime de teste, `aculta_lms` e `lms_classes` estão habilitados e 29
objetos de configuração F7 foram aplicados após backup; nenhuma configuração
ausente foi removida e o curso existente foi preservado. `create_default_class`
permanece desligado. RUNTIME STATUS: CONFIG APLICADA; a homologação funcional
segue DEFERRED para matrícula manual/online, turma cruzada, educadores,
checkout pago/gratuito, estorno/reembolso e isolamento entre usuários A/B.
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

**F8 ainda não está concluída.** O arquivo de histórico e as regras de acesso
estão implementados nesta branch, incluindo captura da versão anterior em
edições, reavaliações e reinícios. Faltam Kernel/Functional tests do ciclo de
vida, dos comentários e do isolamento por turma; revisão de segurança do
arquivo; e smoke tests no Runtime para estudante, educador, reavaliação e
tentativas. A versão upstream não guarda histórico por si; por isso o adaptador
local permanece pequeno, documentado e isolado em `aculta_lms`.

RUNTIME STATUS: DEFERRED. O Drush local retornou URI `http://default`, não um
URI `*.aculta.toca.net.br`; portanto esta implementação não foi aplicada ao
Runtime nem homologada.

Os testes de plugins F8 devem ser executados a partir de `web/core` com o
PHPUnit do Composer; os Kernel tests usam exclusivamente o MariaDB dedicado
descrito em `docs/operations/BANCO-DE-TESTE.md`.

## Testes

- `tests/src/Unit/CourseCompletionPublisherTest.php`: publicação apenas de
  status passado e finalizado; falha e progresso não publicam evento.
- `tests/src/Unit/AttemptArchiveManagerTest.php`: preserva dados e notas
  anteriores antes de edição/reavaliação.
- `tests/src/Unit/FreeTextReviewFeedbackTest.php`: frases orientam somente o
  feedback formativo e nunca finalizam a nota sem avaliação do educador.
- `tests/src/Kernel/AcultaLmsInstallTest.php`: instalação isolada do submódulo
  com registro DI e ausência de configuração própria; persistência do arquivo,
  bloqueio de edição por access e limpeza quando a conta é removida.

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

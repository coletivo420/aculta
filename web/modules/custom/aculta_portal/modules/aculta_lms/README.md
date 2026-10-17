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
na F13. RUNTIME STATUS: DEFERRED; configuração ainda não foi importada e smoke
tests de matrícula, turma cruzada, educador, pagamento, reembolso e isolamento
A/B permanecem pendentes.

## Testes

- `tests/src/Unit/CourseCompletionPublisherTest.php`: publicação apenas de
  status passado e finalizado; falha e progresso não publicam evento.
- `tests/src/Kernel/AcultaLmsInstallTest.php`: instalação isolada do submódulo
  sem instalar a cadeia de dependências, registro DI e ausência de configuração
  própria/hook de instalação.

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

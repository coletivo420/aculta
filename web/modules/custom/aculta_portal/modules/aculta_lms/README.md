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

## Testes

- `tests/src/Unit/CourseCompletionPublisherTest.php`: publicação apenas de
  status passado e finalizado; falha e progresso não publicam evento.
- `tests/src/Kernel/AcultaLmsInstallTest.php`: instalação isolada do submódulo
  sem instalar a cadeia de dependências, registro DI e ausência de configuração
  própria/hook de instalação.

Execute os testes a partir de `web/core` com PHPUnit do Composer. Para o Kernel
test, use um banco SQLite temporário exclusivo e diretório de saída em `/tmp`:

```sh
mkdir -p /tmp/aculta-lms-test-output
SIMPLETEST_DB=sqlite://localhost//tmp/aculta-lms-test.sqlite \
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

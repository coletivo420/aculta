# Banco de teste MariaDB para testes de Kernel

> Documento operacional para humanos e agentes. Não contém senhas: os valores ficam no diretório de segredos,
> fora do Git. Se você precisa de uma credencial, leia o arquivo indicado aqui; não copie o valor para documentos,
> commits, mensagens ou saídas de comando.

## O que é

Os testes de Kernel do Drupal (`SIMPLETEST_DB`) criam tabelas temporárias com prefixo e as removem ao final. Eles
precisam de um banco MariaDB próprio. Este banco é **dedicado a testes**:

| Item | Valor |
| --- | --- |
| Servidor | MariaDB local (`localhost`) |
| Banco | `aculta_simpletest` (utf8mb4) |
| Usuário | `aculta_simpletest`@`localhost` |
| Privilégios | `ALL` somente em `aculta_simpletest.*` |
| Arquivo de configuração | `secrets/simpletest-db.env` na raiz do checkout (modo `0640`, ignorado pelo Git pela regra `/secrets/`; é o mesmo diretório de `/home/aculta/secrets/`) |
| Variável | `SIMPLETEST_DB` (URL `mysql://usuário:senha@localhost/banco`) |

## Regras

1. **Nunca usar o Runtime** (`aculta_runtime`) nem qualquer outro banco do servidor para testes de Kernel. O Drupal cria
   e apaga tabelas no banco indicado por `SIMPLETEST_DB`.
2. **Sem SQLite.** O projeto não usa SQLite (ver `docs/operations/DEPLOY-RUNBOOK.md`). Não use `sqlite://` nem `:memory:`.
3. **Não imprima o arquivo de segredos.** Carregue-o com `set -a; . arquivo; set +a`, como no exemplo abaixo.
4. **Não versione** o arquivo, nem copie a URL para README, CHANGELOG, logs ou comandos colados em PRs.
5. O usuário de teste **não** tem acesso ao Runtime. A verificação é: `SELECT` no banco de teste funciona, e acesso a
   `aculta_runtime` falha com erro 1044.

## Como rodar os testes de Kernel

A partir da raiz do checkout:

```sh
cd web/core
set -a && . ../../secrets/simpletest-db.env && set +a
export BROWSERTEST_OUTPUT_DIRECTORY=/tmp/aculta-browser-out
mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY"
../../vendor/bin/phpunit -c phpunit.xml.dist ../modules/custom/aculta_portal/modules/aculta_pdf_viewer/tests/src/Kernel
```

Troque o caminho final pelo diretório de testes desejado. Para testes unitários não é preciso banco.

Saída esperada em caso de sucesso: `OK` ou `OK, but there were issues!` (este último quando há deprecações de
contrib). Falha ou erro de conexão (`SQLSTATE`) indica problema de credencial ou de permissão, não de código.

## Criar o banco e o usuário (somente administração)

O acesso administrativo ao MariaDB usa a senha de administração guardada em `/home/aculta/secrets/system`. Esse arquivo
é de uso restrito do responsável técnico. Passe a senha por arquivo de opções temporário (`--defaults-extra-file`),
nunca em linha de comando, e apague o arquivo logo depois.

Resumo do procedimento (sem valores):

```sql
CREATE DATABASE IF NOT EXISTS aculta_simpletest CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'aculta_simpletest'@'localhost' IDENTIFIED BY '<senha gerada>';
GRANT ALL PRIVILEGES ON `aculta_simpletest`.* TO 'aculta_simpletest'@'localhost';
FLUSH PRIVILEGES;
```

A senha é gerada com `openssl rand -hex 24`. O hexadecimal evita caracteres que quebram a URL de `SIMPLETEST_DB`.
Depois grave `SIMPLETEST_DB=mysql://...` em `secrets/simpletest-db.env` com modo `0640`.

## Rotação

Para trocar a senha: gere um novo valor, execute `ALTER USER 'aculta_simpletest'@'localhost' IDENTIFIED BY '<nova>';`
com o procedimento administrativo acima e reescreva o arquivo de segredos. Não há outro lugar onde a senha precise ser
atualizada.

## Verificação rápida

```sh
set -a && . secrets/simpletest-db.env && set +a  # a partir da raiz do checkout
# Extrai usuário e senha sem imprimi-los e testa o acesso:
U=$(printf '%s' "$SIMPLETEST_DB" | sed -E 's#mysql://([^:]+):([^@]+)@.*#\1 \2#')
set -- $U
MYSQL_PWD="$2" mysql -u "$1" -h localhost aculta_simpletest -e "SELECT CURRENT_USER(), DATABASE();"
```

## Resultados da última execução (2026-10-16)

| Suite | Testes | Asserções | Situação |
| --- | ---: | ---: | --- |
| `aculta_pdf_viewer` Kernel | 3 | 61 | OK; 4 deprecações |
| `aculta_pdf_viewer_group` Kernel | 1 | 37 | OK; 23 deprecações de contrib em `CourseDocumentAccessTest` |
| `aculta_image` | — | — | Sem diretório de Kernel |

As deprecações vêm de contrib (Group e media) e não impedem o teste. Ficam registradas para revisão.

Execução adicional em 2026-10-17: Unit e Kernel de `aculta_lms`, Unit e Kernel de
`aculta_pdf_viewer` e Kernel de `aculta_pdf_viewer_group` passaram em conjunto
(14 testes, 195 asserções). A cobertura Group inclui membro, não membro,
remoção da relação Media↔Group e revogação de membership. Foram reportadas 23
deprecações de Core/contrib; nenhuma falha. Runtime permanece DEFERRED.

Execução adicional em 2026-10-18: Kernel de `aculta_lms_h5p` confirmou a
descoberta dos hooks OOP e a construção dos serviços do módulo (1 teste, 5
asserções). A suíte Unit H5P passou (4 testes, 10 asserções) sem deprecações do
adapter; a suíte Unit `aculta_image` passou (19 testes, 69 asserções). A
integração no Runtime e o teste visual do editor seguem DEFERRED.

Execução adicional em 2026-10-18: Kernel `H5PCourseAccessIntegrationTest`
passou com 2 testes e 62 asserções, incluindo ida e volta de serialização do
widget H5P e restauração de seus serviços injetados. Na suíte H5P completa,
passaram 11 testes e 204 asserções. As 40 depreciações vieram de
Core/Group/LMS/Views instalados. O smoke no Runtime `cursos` confirmou que o
fatal de serviço não inicializado foi corrigido; o ciclo completo de salvar e
reproduzir conteúdo continua DEFERRED. Unit H5P: 12 testes/52 asserções.

Execução adicional em 2026-10-19: Unit LMS/H5P passou com 36 testes e 148
asserções (5 depreciações do PHPUnit). Kernel LMS/H5P passou com 24 testes e
573 asserções no MariaDB dedicado; 83 depreciações foram reportadas por
Drupal Core/contrib instalados. A cobertura H5P inclui instalação e exclusão
de biblioteca privada, arquivos gerenciados, widget serializado e ciclo de
imagens via `aculta_image`. Runtime de teste `cursos` validou salvamento e
reprodução por estudante, bloqueio anônimo e limpeza via formulário
administrativo. Após refatorar DI, Drush como conta `aculta` confirmou URLs
absolutas do editor; os assets H5P CSS/JS e a tradução pt-br responderam HTTP
200. Cache rebuild executado; nenhum update de banco ou import de configuração
necessário. Produção não foi alterada.

Execução adicional em 2026-10-19 após a revisão dos serviços do editor:
H5P Unit passou com 23 testes e 67 asserções; Kernel H5P passou com 14 testes
e 263 asserções no MariaDB dedicado. Foram reportadas 40 depreciações de
Core/contrib e nenhuma falha. O Kernel validou o formulário de configuração
após serialização, e o acesso próprio/global do plugin de Views está coberto
em Unit.

Execução adicional em 2026-10-20: `aculta_forum_lms` Unit passou com 14 testes e
147 assertions; Kernel passou com 2 testes e 74 assertions. Foram emitidas 38
depreciações de Core/contrib/LMS, sem falha. PHP CLI disponível no Homelab é
8.4.26, abaixo do alvo PHP 8.5; resultado não substitui validação nessa versão.
No Runtime de teste, ativação do adapter, importação parcial de cinco
configurações de permissões e smoke HTTP foram confirmados; o Runtime usa
`aculta_runtime`, nunca o banco dedicado desta suíte. RUNTIME STATUS: PARTIAL;
ver o changelog do adapter para limites de configuração e acesso.

Execução adicional em 2026-10-20: adapter LMS Unit passou com 18 testes e 184
assertions; Kernel passou com 2 testes e 102 assertions, incluindo autorização
contextual de Comments e as três filas Views com Group/status isolados. Foram
emitidas 40 depreciações de Core/contrib/LMS. PHP CLI 8.4.26, abaixo do alvo
8.5. O hook OOP foi exercitado diretamente na fixture, e a View foi executada
na MariaDB de teste. Rotas HTTP e View ainda não foram atualizadas no Runtime.
RUNTIME STATUS: DEFERRED.

Execução adicional em 2026-10-21: suíte Kernel completa do `aculta_lms` passou
com 19 testes e 584 assertions no banco dedicado; 90 depreciações vieram de
Core/contrib. A suíte Unit passou com 48 testes / 260 assertions e 8
depreciações PHPUnit. A fixture do teste de instalação agora instala schema e
usuário para validar o access de conta ativa via `AccountUserLoader`. PHP CLI
8.4.26; PHP 8.5 permanece não validado.

Execução adicional em 2026-10-21: regressão combinada `aculta_lms` e
`aculta_lms_messenger` passou com 21 testes / 646 assertions Kernel no MariaDB
dedicado e 72 testes / 368 assertions Unit. A Unit inclui os cinco casos novos
de entitlement Commerce. Foram reportadas 92 depreciações de
Core/contrib e 8 depreciações PHPUnit; não houve falhas. PHP CLI 8.4.26; PHP
8.5 continua não validado.

Após adicionar a recusa de PDF revogado, `CertificateLifecycleTest` passou com
3 testes / 97 assertions e a suíte Kernel combinada permaneceu em 21 testes,
agora com 646 assertions; a execução completa acima já inclui essa alteração.

Execução adicional em 2026-10-21 após a correção da matrícula F7: regressão
combinada `aculta_lms` + `aculta_lms_messenger` passou com 72 testes / 368
assertions Unit e 21 testes / 646 assertions Kernel no MariaDB dedicado. Foram
reportadas 8 depreciações PHPUnit e 92 de Core/contrib. PHPCS, Gate Drupal 11+
(1010 checks), índice do projeto (29 componentes), imagem e slugs passaram. O
Runtime `cursos` confirmou pedido gratuito concluído, origem da membership e
papel global estudante; fixtures, aviso e item de e-mail temporários foram
removidos por APIs Drupal. PHP CLI 8.4.26; PHP 8.5 não validado.

Após ampliar a cobertura de estados de compra F7 em 2026-10-21, Unit LMS/Messenger
passou com 72 testes / 368 assertions; Kernel permanece 21 / 646. O novo teste
Unit valida pedido grátis concluído, pedido incompleto e balanços de pagamentos
concluídos, parcialmente estornados e estornados, sem invocar gateway remoto.

Após ampliar em 2026-10-21 a matriz de autorização do painel F16, o Kernel focal
de turma passou com 1 teste / 43 assertions: educador com grant `grade students`
no Group acessa os agregados e estudante não os recebe. Regressão Kernel
combinada reexecutada: 21 testes / 646 assertions PASS, com 92 depreciações
upstream.

Após acrescentar a política de remoção manual de memberships de compra, Unit
LMS/Messenger passou com 77 testes / 400 assertions; `EnrollmentFormHooksTest`
passou com 5 testes / 32 assertions. A cobertura distingue matrícula manual,
pedido pago, reembolso parcial/total, pedido gratuito concluído e origem
inválida. Não há chamadas a gateway.

Após ampliar em 2026-10-21 o contrato de privacidade pública F11,
`CertificateLifecycleTest` passou com 3 testes / 102 assertions; a regressão
Kernel LMS/Messenger passou com 21 testes / 651 assertions e 92 depreciações
upstream. A renderização por código confirma nome, curso, horas e estado, sem
email ou motivo interno de revogação.

Após proteger emissão e formulário F11 contra referências de estudante/curso
ausentes, `CertificateLifecycleTest` passou com 4 testes / 126 assertions. A
regressão Kernel `aculta_lms` + `aculta_lms_messenger` passou com 22 testes /
675 assertions; 92 depreciações vieram de Core/contrib. Unit LMS passou com 61
testes / 334 assertions e 8 depreciações PHPUnit. PHP CLI 8.4.26; PHP 8.5
continua não validado.

Em 2026-10-21, a cobertura de cache F16 foi ampliada para o acesso do painel de
educadores: `ClassActivityDeadlineTest` passou com 1 teste / 52 assertions e a
regressão Kernel LMS/Messenger com 23 testes / 725 assertions. Ambas as
negativas armazenadas (painel e prazo) são invalidadas ao criar a relação
curso→turma; 95 depreciações de Core/contrib. PHP 8.4.26; PHP 8.5 não validado.

Em 2026-10-21, `ClassActivityDeadlineTest` passou com 1 teste / 49 assertions:
ao criar o vínculo curso→turma, o cache tag Group invalida a negativa de acesso
previamente armazenada. A regressão Kernel LMS/Messenger passou com 23 testes /
722 assertions; 95 depreciações vieram de Core/contrib. PHP CLI 8.4.26; PHP 8.5
continua não validado.

## Quem usa

- Agentes e humanos que rodam Kernel de `aculta_portal` e submódulos.
- O procedimento de CI ou de Hostinger não está definido aqui. Ele pertence ao contrato de segredos
  (`docs/operations/SECRETS.md`) e precisa de decisão própria.

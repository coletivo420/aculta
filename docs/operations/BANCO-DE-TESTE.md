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

## Quem usa

- Agentes e humanos que rodam Kernel de `aculta_portal` e submódulos.
- O procedimento de CI ou de Hostinger não está definido aqui. Ele pertence ao contrato de segredos
  (`docs/operations/SECRETS.md`) e precisa de decisão própria.

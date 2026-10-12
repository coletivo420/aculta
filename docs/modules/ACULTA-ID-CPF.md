# ACULTA ID CPF — primeira entrega

Solicitação do responsável em 2026-10-12: iniciar `aculta_id_cpf` como submódulo
do `aculta_portal`, a partir da especificação técnica preparada na conversa.
Essa solicitação prioriza a primeira entrega sem reordenar as demais fases do
roadmap ou fechar as dívidas existentes.

## Escopo, versões e fonte de verdade

- Caminho: `web/modules/custom/aculta_portal/modules/aculta_id_cpf`.
- Submódulo opt-in, versão inicial `0.1.0-dev.1`, classificação MINOR.
- Base do trabalho: `main` em `368ec263189d670d459e385b068d7bb188af95b3`.
- Portal permanece `0.3.0-dev.16`: nenhum código runtime do módulo pai foi
  alterado. A próxima marcação do Portal depende da homologação correspondente;
  não foi criada release ou tag para estado ainda não homologado.
- Lock: Core 11.4.8, Webform 6.3.1, login_emailusername 3.0.1, Key 1.22.0,
  Profile 1.14.0 e PHPUnit 11.5.57. O lock não equivale ao ambiente instalado.
- Checkout, OAuth, CAPTCHA, Domain, e-mail, tema e dados existentes não são
  alterados por esta entrega.

Os serviços puros não coletam nem gravam dados. Os elementos Form API entregam
valores canônicos ao chamador, que ainda precisa implementar proteção contra
cache, armazenamento em claro e exibição não autorizada. Validade de DV não
comprova titularidade, emissão ou situação cadastral.

## Pesquisa e adaptação ao repositório

A especificação original previa integração com login do Core e repositório
privado. O site já possui `login_emailusername`, Social Auth, confirmação de
e-mail e `PortalFormCallbacks::validateLoginAuthentication`, responsável por
preservar a resposta genérica de contas bloqueadas. Dados pessoais pertencem a
Profile, contas a User, e segredos a Key/environment. A entrega inicial não
introduz outro storage ou resolvedor de autenticação.

Antes da persistência, verificar o suporte real de Core/contrib a criptografia
de campos e índices exclusivos. Se for necessário índice privado/criptografia
complementar, documentar a lacuna e manter User/Profile como fonte de verdade,
sem espelhamento acidental. Campos CPF/CNPJ completos não podem ser strings
expostas em entidades públicas. Login por CPF precisa manter o wrapper atual,
flood, confirmação, CAPTCHA e Social Auth, com contadores compartilhados por uid.

Plugins de Form API usam `#[FormElement]`; validação usa callback
`aculta_id_cpf.element_validation:validate` com serviços explicitamente
injetados. Esse contrato foi verificado no change record 3548821, no Core
11.4.8 instalado e nos testes Kernel abaixo. Não há declaração de prontidão
para Drupal 12/13.

O relatório P10-R citado por AGENTS.md foi consultado no commit histórico
`9c95420`, conforme indicado no DEBT-REGISTER; não foi recriado no working tree.

## Evidência de execução

Os resultados anteriores foram obtidos em container com PHP CLI 8.3.6 e Node
24.19.0; continuam como evidência histórica, não como resultado desta rodada.
Nesta rodada (2026-10-12), o checkout de desenvolvimento local que simula o
servidor de testes usa PHP CLI 8.4.26, PHP-FPM 8.4.26 ativo, Apache ativo,
MariaDB 11.8.6, Composer 2.8.8, Drupal Core 11.4.8, PHPUnit 11.5.57 e Node
22.23.3. Navegador: Playwright 1.64.0 e Chromium 154.0.8037.92. O uso local de
`127.0.0.1` com alias de host não comprova execução no servidor remoto
`*.aculta.toca.net.br`.

| Verificação | Resultado nesta rodada |
| --- | --- |
| Composer validate | PASS: composer.json/lock válidos; avisos de constraints exatas preexistentes |
| Composer audit --locked | PASS: nenhum advisory encontrado |
| Dependências | PASS: Core 11.4.8 e PHPUnit 11.5.57 instalados; lock preservado, sem `composer update` |
| Drush status | PASS: Drupal 11.4.8, PHP 8.4.26, banco conectado |
| Runner PHP independente do Drupal | PASS: 248 verificações |
| PHPUnit de domínio | PASS: 34 testes, 63 asserções |
| Máscaras JavaScript em harness Node | PASS: 8 testes |
| Gate Drupal 11+ do Portal | PASS: 583 verificações |
| Submódulo no Runtime local | PASS: habilitado em `aculta_runtime`; cache reconstruído; nenhum objeto de configuração foi atualizado |
| Módulos de login/CAPTCHA/Social Auth | PASS: `login_emailusername`, `captcha`, `social_auth`, `social_auth_google` e `aculta_portal` permanecem habilitados; a PR não altera esses fluxos |
| Gate CAPTCHA / Conta | PASS: `validate-shop-captcha.php` 15 checks; `validate-aculta-portal-conta-root.php` 1233 checks |
| Gate Portal commerce/security | DEFERRED/FAIL: executado via Drush, parou na asserção existente `Fake runtime credentials were not persisted` (DT-O11); nenhum valor foi exposto ou alterado |
| Kernel (descoberta, callback DI, normalização, opcional/inválido) | PASS: 5 testes, 8 asserções em `aculta_id_cpf_test` (MariaDB separado) |
| Formulário temporário no Chromium, JavaScript ativo | PASS: colagem via clipboard mascara CNPJ alfanumérico; AJAX confirma valor canônico; seleção/cursor preservados |
| Formulário temporário no Chromium, JavaScript desativado | PASS: digitação mantém valor sem máscara. Envio não concluído porque CAPTCHA global exige desafio; o CAPTCHA não foi contornado |
| Formulário temporário / Conta | PASS: formulário não persiste, foi desinstalado/removido e sua rota voltou a 404 após cache rebuild; formulários da Conta não receberam campos |
| Homelab remoto (`*.aculta.toca.net.br`) | DEFERRED: a execução disponível foi local em `127.0.0.1`, não no servidor de testes |
| `git diff --check` | PASS |

O Kernel inicial sem banco apontou a depreciação Drupal 11.3 para testes sem
`#[RunTestsInSeparateProcesses]`; a anotação foi adicionada e os cinco casos
passaram no banco separado. Nenhum documento pessoal real foi usado ou gravado;
o valor alfanumérico veio do vetor público de teste da entrega. A rodada de
navegador é local e não substitui a verificação no host remoto do servidor de
testes.

Comandos da rodada, executados no checkout de desenvolvimento local:

```sh
composer validate --no-check-publish
composer audit --locked --no-interaction
php vendor/drush/drush/drush.php en aculta_id_cpf -y
php vendor/drush/drush/drush.php cr
php web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/run.php
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/src/Unit
node --test web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/mask.test.mjs
php scripts/validate-aculta-portal-drupal11.php
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/src/Kernel
```

O Kernel foi executado com `SIMPLETEST_DB` apontando ao banco isolado
`aculta_id_cpf_test`; a credencial foi carregada apenas no ambiente do processo.
O gate Portal passou também na árvore do PR; o gate de commerce/security requer
revisão separada da pendência DT-O11.

Os testes Node exercitam handlers reais em harness, mas não comprovam o
comportamento de todos os navegadores. Os testes de Kernel foram adicionados
para descoberta, resolução dos callbacks, valores aninhados, entradas vazias e
controle CR/LF; não foram executados nem declarados PASS.

Os testes PHP usam vetores públicos ilustrativos e o exemplo alfanumérico
oficial `12ABC34501DE35`. O runner também altera cada DV dos vetores válidos e
verifica a rejeição, sem consultar cadastros reais. Nenhum pacote Composer foi
adicionado e nenhum segredo foi utilizado.

## Próximas entregas

1. Concluir a homologação Kernel e interativa no servidor de testes, PHP 8.4,
   com banco MariaDB de teste separado e formulário temporário sem persistência.
2. Implementar campos PF/PJ e armazenamento privado, autorização, reautenticação
   e índices de unicidade transacionais. Testar concorrência e rollback em
   MariaDB de teste antes de coletar documentos.
3. Integrar login por CPF ao pipeline existente, sem contador separado para
   CPF/e-mail; revisar logs, recuperação de senha e enumeração.
4. Criar integração opcional Webform, com modos validate_only, link_user e
   capture_encrypted. Proteger rascunhos, tokens, e-mails, exportações e handlers
   antes de habilitar coleta.

Nenhum item futuro está marcado como implementado. A PR desta entrega deve
permanecer em draft com **RUNTIME STATUS: DEFERRED** até homologação.

## Rollback

Como o submódulo não está habilitado no sync e não possui schema/update hooks,
remover/reverter a alteração de código reverte esta entrega. Se tiver sido
habilitado em homologação, desabilitar/desinstalar somente após verificar que
nenhum formulário depende dos elementos e reconstruir o cache pelo fluxo do
projeto. Não há migração de dados ou ação de produção nesta entrega.

## Fontes oficiais

- [Form API CallableResolver, Drupal 11.3](https://www.drupal.org/node/3548821).
- [Textfield, Core 11.4.8](https://github.com/drupal/drupal/blob/11.4.8/core/lib/Drupal/Core/Render/Element/Textfield.php).
- [FormValidator, Core 11.4.8](https://github.com/drupal/drupal/blob/11.4.8/core/lib/Drupal/Core/Form/FormValidator.php).
- [Atributo FormElement](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Attribute!FormElement.php/class/FormElement/11.x).
- [Manual Receita/Serpro do DV de CNPJ](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf).

Pesquisa de reaproveitamento da especificação: bissolli/validador-cpf-cnpj,
geekcom/validator-docs e theuves/cpf. Nenhum arquivo desses projetos foi copiado.
O módulo mantém implementação própria pequena e serviços independentes;
normalize/validate não apagam letras do CNPJ nem caracteres arbitrários.

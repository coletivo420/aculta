# ACULTA CPF e CNPJ

Submódulo opcional do `aculta_portal`, baseline Drupal **11.3+**.
Primeira entrega: **0.1.0-dev.1**, núcleo de domínio e elementos Form API.

**RUNTIME STATUS: DEFERRED** — falta homologação interativa no servidor de testes
`*.aculta.toca.net.br`; a rodada local usa PHP 8.4 e não substitui essa
verificação remota. A submissão interativa sem JavaScript também depende de
um desafio CAPTCHA, que não foi contornado.
Não é uma entrega completa de cadastro ou autenticação por CPF.

## Entregue

- Validação PHP de CPF e CNPJ numérico/alfanumérico, incluindo DVs.
- Normalização estrita: strings, zeros iniciais, letras ASCII maiúsculas e
  máscara completa opcional; sem descartar caracteres ilegais.
- Formatação completa e ocultação do corpo, sem presumir autorização.
- Elementos Form API `aculta_cpf` e `aculta_cnpj`, sem dependência de JavaScript.
- Callbacks `service.id:method` com DI, conforme Drupal 11.3+.
- Máscara local com `Drupal.behaviors`/`once`, AJAX e preservação da seleção.
- Testes de domínio e JavaScript; testes Kernel preparados para homologação.

Nenhuma dependência Composer nova. O Portal não depende deste submódulo e
`config/sync/core.extension.yml` não o habilita automaticamente.

## Uso em desenvolvimento

Habilitar apenas em homologação, depois da revisão do PR:

```sh
php vendor/drush/drush/drush.php en aculta_id_cpf -y
php vendor/drush/drush/drush.php cr
```

Elementos reutilizáveis em um formulário, sem rota pública nova:

```php
$form['cpf'] = [
  '#type' => 'aculta_cpf',
  '#title' => $this->t('CPF'),
  '#required' => TRUE,
];
$form['cnpj'] = [
  '#type' => 'aculta_cnpj',
  '#title' => $this->t('CNPJ'),
];
```

Em submit válido, os valores do `FormState` são strings canônicas. O elemento
continua exibindo a entrada do usuário durante rebuild. Obrigatoriedade usa
`#required` do Core. Para código de domínio, injetar `aculta_id_cpf.validator`
e chamar `canonicalize($input, DocumentType::CPF|CNPJ)`.

São aceitos documentos sem pontuação ou com máscara completa válida; somente
espaços ASCII externos são retirados. Pontuação parcial, espaços internos,
letras no CPF, caracteres Unicode semelhantes e letras nos DVs do CNPJ são
rejeitados. Digitação parcial no navegador recebe máscara progressiva, mas
somente o servidor determina a validade final. A máscara não corrige DVs.

**Privacidade:** estes elementos não implementam armazenamento seguro,
unicidade, autorização de visualização, reautenticação ou retenção. O caller
recebe texto claro no `FormState` e deve protegê-lo inclusive de cache de
formulário/rebuild/rascunho. Não anexar a campos públicos de User/Profile,
Webform, tokens, logs, e-mails ou handlers externos até a implementação desses
controles. A ocultação do formatter não é controle de acesso.

## Testes

Na raiz do repositório:

```sh
php web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/run.php
node --test web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/mask.test.mjs
php scripts/validate-aculta-portal-drupal11.php
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/src/Unit
```

Kernel requer ambiente de teste e `SIMPLETEST_DB`, conforme o Core:

```sh
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/src/Kernel
```

Kernel isola elementos/callbacks, sem homologar a cadeia inteira de dependências
do Portal. PASS local em MariaDB separado (`aculta_id_cpf_test`): 5 testes/8
asserções. A máscara, AJAX, colagem e seleção foram exercitados no Chromium
local; sem JavaScript, a digitação ficou sem máscara. O smoke local não substitui
o servidor de testes. Evidência da entrega e próximos passos em
[ACULTA-ID-CPF.md](../../../../../../docs/modules/ACULTA-ID-CPF.md).

## Limites e próxima entrega

Não há campos persistidos PF/PJ, índice de unicidade, criptografia, alteração do
login ou plugins Webform nesta entrega. Esses recursos seguem a especificação
solicitada, reconciliada com Profile/User, Drupal Key e login_emailusername do
site antes de implementar persistência. Não adicionar tabela paralela ou outro
resolvedor de login sem comprovar a necessidade e testar a integração.

Norma: [DRUPAL-11-STANDARDS.md](../../../../../../docs/portal/DRUPAL-11-STANDARDS.md).

## Origem do código

Implementação própria a partir dos contratos descritos na especificação e do
algoritmo publicado pela Receita Federal/Serpro. Não foram incorporados arquivos
de validadores externos nem adicionadas dependências desses repositórios.
Código sob a licença do projeto. As fontes oficiais e a pesquisa de código
estão registradas no documento de entrega.

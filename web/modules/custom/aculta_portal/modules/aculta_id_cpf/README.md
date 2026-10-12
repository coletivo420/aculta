# ACULTA CPF e CNPJ

Submódulo opcional do `aculta_portal`, baseline Drupal **11.3+** e PHP **8.4**.
A entrega atual contém serviços de domínio sem formulário, campo, persistência ou
integração de autenticação.

**RUNTIME STATUS: DEFERRED** — a rodada local em PHP 8.4 passou; o servidor
remoto `*.aculta.toca.net.br` continua pendente.

## Disponível

- Validação PHP de CPF e CNPJ numérico/alfanumérico, incluindo dígitos
  verificadores.
- Normalização estrita: valores são strings para preservar zeros iniciais;
  aceita pontuação completa ou ausência de pontuação; CNPJ alfanumérico em
  maiúsculas; rejeita caracteres inválidos em vez de removê-los.
- Formatação e ocultação do corpo do documento. Redação visual não concede
  autorização de leitura.
- Serviços acessíveis por DI: `aculta_id_cpf.normalizer`,
  `aculta_id_cpf.cpf_validator`, `aculta_id_cpf.cnpj_validator`,
  `aculta_id_cpf.validator` e `aculta_id_cpf.formatter`.
- Testes de domínio e habilitação/cache local em PHP 8.4.26; a evidência completa
  e os testes históricos da implementação removida estão no relatório.

Nenhuma dependência Composer nova. O Portal não depende deste submódulo e
`config/sync/core.extension.yml` não o habilita automaticamente.

## Decisão sobre campos e formulários

O módulo **não implementa plugins de campo, widgets, elementos Form API,
máscaras JavaScript, formulários de conta ou integração Webform nesta etapa**.
Os campos de Pessoa Física e Pessoa Jurídica serão implementados manualmente no
`aculta_portal`, conforme a estrutura e as telas existentes. Essa implementação
poderá injetar `aculta_id_cpf.validator` e chamar:

```php
use Drupal\aculta_id_cpf\Enum\DocumentType;

$canonical = $validator->canonicalize($input, DocumentType::CPF);
```

Para CNPJ, use `DocumentType::CNPJ`. `canonicalize()` devolve string canônica
ou lança `InvalidArgumentException`; trate a exceção para mostrar erro sem
registrar o valor submetido. A aceitação de pontuação completa ou valor sem
pontuação é aplicada no serviço. Máscara durante a digitação, widget/formatter
do campo, armazenamento, unicidade e controle de acesso pertencem às próximas
entregas do Portal e devem ser definidos antes de coletar documentos reais.

Essa separação é deliberada: não habilitar campos persistidos em User/Profile
nem Webform até haver decisão implementada e testada para privacidade, cache,
armazenamento, unicidade concorrente e autorização de leitura.

## Testes

Na raiz do repositório:

```sh
php web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/run.php
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/src/Unit
php scripts/validate-aculta-portal-drupal11.php
```

Testes de máscara e Kernel/Form API foram retirados junto com a implementação
de campos. Evidência e limites conhecidos estão em
[ACULTA-ID-CPF.md](../../../../../../docs/modules/ACULTA-ID-CPF.md).

## Próximas entregas

1. Implementar manualmente no `aculta_portal` os campos PF/PJ e suas máscaras,
   consumindo os serviços deste submódulo.
2. Definir a persistência privada, autorização, reautenticação e unicidade
   transacional sem criar fonte de verdade duplicada em User/Profile.
3. Integrar login por CPF ao pipeline existente, preservando flood, confirmação,
   CAPTCHA, Social Auth e respostas genéricas.
4. Avaliar integração Webform somente após proteção de rascunhos, tokens,
   e-mails, exportações e handlers.

Não adicionar tabela paralela ou resolvedor de login sem documentar a lacuna
nos recursos nativos e testar a integração com o Portal.

## Origem do código

Implementação própria a partir dos contratos da especificação e do algoritmo
publicado pela Receita Federal/Serpro. Não foram incorporados arquivos de
validadores externos nem adicionadas dependências desses repositórios. Fontes e
pesquisa estão no documento de entrega.

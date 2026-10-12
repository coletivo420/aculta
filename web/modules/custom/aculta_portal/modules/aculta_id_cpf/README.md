# ACULTA CPF e CNPJ

Submódulo opcional do `aculta_portal`, baseline Drupal **11.3+** e PHP **8.4**.
Versão atual: **0.2.0-dev.2**. Persiste o CPF/CNPJ cifrado da aba "Outros Dados"
e um índice de unicidade (fingerprint HMAC).

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
  `aculta_id_cpf.validator`, `aculta_id_cpf.formatter` e
  `aculta_id_cpf.fingerprint`.
- Impressão digital HMAC-SHA-256 estável para apoiar índices de unicidade sem
  armazenar o documento em claro nesse índice. É dado pseudonimizado e continua
  sujeito a proteção de acesso e retenção.
- Testes de domínio e habilitação/cache local em PHP 8.4.26; a evidência completa
  e os testes históricos da implementação removida estão no relatório.

Nenhum pacote Composer novo: Key já é dependência do projeto e agora é declarado
diretamente pelo submódulo. O Portal não depende deste submódulo e
`config/sync/core.extension.yml` não o habilita automaticamente.

### Chave para impressão digital

Antes de chamar `aculta_id_cpf.fingerprint`, configure no Drupal Key uma chave
com machine name `aculta_id_cpf_fingerprint`, valor aleatório de pelo menos 32
bytes e provider seguro (por exemplo, variável de ambiente ou arquivo fora do
webroot). Não exporte o segredo para Configuration Sync/Git. A perda ou troca
da chave invalida os fingerprints existentes; rotação futura exige recalcular
todos os índices de forma controlada. O serviço falha fechado se a chave estiver
ausente ou for menor que 32 bytes.

O HMAC não implementa armazenamento, restrição única no banco nem coordenação de
gravações concorrentes. A integração manual no `aculta_portal` deverá armazenar
o fingerprint junto à fonte de verdade Profile/User e estabelecer uma garantia
de unicidade transacional antes de afirmar que documentos duplicados são
impedidos. Não usar o fingerprint como autorização nem como anonimização.

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

## Persistência (0.2.0-dev.2)

- `DocumentCipher`: cifra libsodium com a Key `aculta_document_encryption_key`
  (variável `ACULTA_DOCUMENT_ENCRYPTION_KEY`, 64 caracteres hexadecimais).
- `AccountDocumentStore` grava em `profile.participante.field_documento` (cifrado)
  e `field_documento_indice` (fingerprint HMAC com a Key `aculta_id_cpf_fingerprint`,
  variável `ACULTA_ID_CPF_FINGERPRINT_KEY`, mínimo de 32 bytes).
- Antes de gravar, recusa o mesmo documento em outra conta com mensagem genérica.
  O formulário não revela se o documento existe em outra conta.
- Exibição ao titular: 5 caracteres e `*` no restante (ex.: `974.12*.***-**`).
- Sem Key de cifra ou de fingerprint, a gravação e a aba ficam indisponíveis
  (falha fechada); a leitura de valores já gravados também exige a cifra.
- Atualização `aculta_id_cpf_update_10001`: cria o campo de índice e indexa os
  documentos gravados antes dele; para se houver duplicidade entre contas.

## Limite conhecido: unicidade não é transacional

A verificação de unicidade é uma consulta antes da gravação. Sob gravações
simultâneas, duas contas podem passar pela verificação ao mesmo tempo. Campo de
entidade do Drupal não oferece índice único; a garantia forte exige decisão
sobre tabela com índice único (armazenamento paralelo) ou outra estratégia. Até
essa decisão, não afirmar que documentos duplicados são impedidos em qualquer
condição de concorrência.

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

1. Decidir a garantia transacional de unicidade (ver "Limite conhecido").
2. Testes de Kernel para `AccountDocumentStore` (duplicidade, perfil despublicado,
   atualização `aculta_id_cpf_update_10001`), ainda não cobertos.
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


# ACULTA CPF e CNPJ

Submódulo opcional do `aculta_portal`, baseline Drupal **11.3+** e PHP **8.4**.
Versão atual: **0.3.0-dev.6**. Persiste o CPF/CNPJ cifrado da aba "Outros Dados"
e um índice de unicidade (fingerprint HMAC).

**Runtime:** verificação somente leitura em 2026-10-29 confirmou `aculta_runtime`
no checkout detached `355e48a89` (não é o candidato desta branch). O módulo ativo
é `0.3.0-dev.4`, schema `10002`; o índice SQL único está presente. Há 2 linhas de
documentos associados, nenhum documento sem fingerprint e nenhum grupo
duplicado. Não executei atualização nem alterei dados do Runtime nesta fase.

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

O campo HMAC continua na fonte de verdade do perfil associado; a atualização
10002 verifica que cada documento ativo possui fingerprint e não há grupos
duplicados antes de adicionar o índice SQL único, fechando a corrida entre
gravações. Se houver documento sem índice, a atualização falha fechada com
contagem agregada. Não usar o fingerprint como autorização nem como anonimização.

## Decisão sobre campos e formulários

O módulo não implementa plugins de campo, widgets, elementos Form API,
máscaras JavaScript nem integração Webform. `AccountDocumentStore` já persiste
documento cifrado e fingerprint no perfil associado; o Portal é proprietário
dos formulários e chama o serviço de validação/canonicalização:

```php
use Drupal\aculta_id_cpf\Enum\DocumentType;

$canonical = $validator->canonicalize($input, DocumentType::CPF);
```

Para CNPJ, use `DocumentType::CNPJ`. `canonicalize()` devolve string canônica
ou lança `InvalidArgumentException`; trate a exceção para mostrar erro sem
registrar o valor submetido. A aceitação de pontuação completa ou valor sem
pontuação é aplicada no serviço. Máscara durante a digitação e widgets continuam
fora do módulo; o perfil permanece privado e a gravação única passa pelo store.

## Persistência (0.2.0-dev.2)

- `DocumentCipher`: cifra libsodium com a Key `aculta_document_encryption_key`
  (variável `ACULTA_DOCUMENT_ENCRYPTION_KEY`, 64 caracteres hexadecimais).
- `AccountDocumentStore` grava em `profile.associado.field_documento` (cifrado)
  e `field_documento_indice` (fingerprint HMAC com a Key `aculta_id_cpf_fingerprint`,
  variável `ACULTA_ID_CPF_FINGERPRINT_KEY`, mínimo de 32 bytes).
- Antes de gravar, recusa o mesmo documento em outra conta com mensagem genérica.
  O formulário não revela se o documento existe em outra conta.
- Exibição ao titular: 5 caracteres e `*` no restante (ex.: `974.12*.***-**`).
- Sem Key de cifra ou de fingerprint, a gravação e a aba ficam indisponíveis
  (falha fechada); a leitura de valores já gravados também exige a cifra.
- Update histórico 10001: foi criado quando o bundle ainda se chamava participante;
  o bundle atual é associado, com os campos versionados no Config Sync.

## Garantia de unicidade e migração

A consulta prévia preserva a mensagem genérica; o índice único no campo ativo
fecha a corrida no armazenamento SQL. O update 10002 verifica que todo documento
ativo no bundle associado possui fingerprint, verifica grupos duplicados e então
cria o índice SQL único; a operação é idempotente. No Runtime MAIN, a leitura de
2026-10-26 encontrou um documento indexado, nenhum faltante, nenhuma duplicidade
e ainda sem índice SQL único. A atualização permanece pendente: inspecionar
plano/rollback e obter autorização operacional antes de executá-la. Não registrar
CPF/CNPJ ou fingerprint em logs.

### Ativação e rollback

1. Em banco isolado, executar o update `10002` com conjunto sem duplicatas e com
   duplicatas sintéticas; confirmar que a falha informa somente a contagem de
   grupos e que a repetição após corrigir os dados conclui.
2. Antes de uma ativação autorizada, criar snapshot consistente do banco e
   registrar apenas a contagem agregada de fingerprints repetidos. O update não
   é executado se houver conflito.
3. Se houver falha após o DDL e antes de liberar gravações, remover o índice
   `aculta_id_cpf_documento_unique` com a API de schema e restaurar o snapshot se
   qualquer escrita tiver ocorrido. Não apagar ou reconstruir campos/perfis.
4. Após ativar gravações, rollback exige snapshot coordenado e plano para as
   gravações posteriores; não remover o índice isoladamente enquanto houver
   dados escritos sob o novo contrato.

## Testes

Na raiz do repositório:

```sh
php web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/run.php
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/src/Unit
vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests/src/Kernel
php scripts/validate-aculta-portal-drupal11.php
```

Na validação M-1 com o autoloader isolado da branch: Runner de domínio 248 checks
PASS; Unit 48 testes / 116 assertions PASS; Kernel MariaDB 7 testes / 46
assertions PASS (8 depreciações Core/contrib). Além da idempotência do update
`10002`, bloqueio SQL de fingerprints duplicados, aborto seguro com conflitos e
rollback que remove somente o índice mantendo as linhas, o Kernel exercita o
`AccountDocumentStore` e o formulário real `AccountOtherDataForm`: validação,
gravação cifrada/fingerprint, máscara após salvar e mensagem genérica para
duplicidade. PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS.
Nenhum update foi aplicado no Runtime. A execução operacional de `10002` segue
pendente de autorização e snapshot; não foi solicitada nem criada neste commit.
Evidência histórica da implementação removida está em
[ACULTA-ASSOCIADO-ID.md](../../../../../../docs/modules/ACULTA-ASSOCIADO-ID.md).

## Próximas entregas

1. Confirmar a experiência visual do formulário Outros Dados quando o candidato desta branch estiver servido no Firefox.
2. Rever e decidir a integração de login por CPF ao pipeline existente, preservando flood, confirmação, CAPTCHA, Social Auth e respostas genéricas.
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


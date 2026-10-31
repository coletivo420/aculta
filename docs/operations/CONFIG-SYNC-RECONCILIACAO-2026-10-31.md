# Reconciliação do Config Sync — Runtime (2026-10-31)

## Decisão registrada

Os 14 módulos MCP já ativos no Runtime permanecem ativos e suas configurações
operacionais não secretas ficam versionadas em `config/sync/`. Nenhum módulo MCP
foi desinstalado. Credenciais, tokens e chaves continuam fora do repositório.
Os seis Nodes `aculta_faq` (NIDs 567–572) foram preservados; o bundle e suas
configurações continuam no Sync.

## Reconciliação executada

- Runtime confirmado no host `toca`, banco `aculta_runtime`, URI `http://default`,
  Drupal 11.4.10; checkout destacado em `82476f2d8` durante a operação.
- Foram adicionados ao Sync os 14 módulos MCP e 21 configurações MCP ativas
  previamente exportadas do Runtime. A comparação final de `core.extension`
  contém 239 módulos em ambos os lados, sem diferença.
- O primeiro import foi interrompido pela tentativa de renomear a configuração
  `group.relationship_type.lms_course-af8ec2205541224f9d8c4` para
  `...-664b23d4931526bbffbc9`. O Group não permite trocar esse machine name via
  import. A configuração foi corrigida para preservar o ID e UUID existentes,
  atualizando somente a dependência e o plugin para
  `aculta_document_viewer:course_document`.
- O import de configuração é incremental e pode ter efeitos antes de falhar.
  O comando anterior, executado com `--diff` e entrada não interativa `n`,
  aplicou parte das alterações antes de parar. `--diff` não é modo de simulação;
  não usar entrada piped como garantia de cancelamento.
- O import corrigido terminou com sucesso. O comparador oficial do Drupal
  (`StorageComparer`) passou a apresentar `create: 0`, `update: 0`, `delete: 0`
  e `rename: 0` após exportar para Sync os 72 valores que o Runtime e seus
  módulos ativos recompõem durante a importação.
- A inspeção pós-import encontrou três tabelas de campos removidos com 59 valores
  ainda retidos pelo Field API: 57 eventos de amizade, um marcador de entrega do
  ACULTA Mailer e um marcador de digest privado. O código Messenger depende
  desses campos. As cinco configurações de storage/field ausentes foram
  restauradas em `config/sync` e no Runtime. As 59 linhas foram copiadas em
  transação para as tabelas ativas, com `deleted = 0`, sem modificar as entidades
  Message. Validação por chave, valor e Entity API confirmou as 59 linhas. As
  cópias antigas continuam nas tabelas de campo removido até a purga normal do
  Drupal.
- `updatedb:status` não lista atualizações pendentes; `drush cr` concluiu.
- A contagem de `aculta_faq` permaneceu em seis.
- O snapshot pós-reconciliação tinha 614 tabelas. Durante a verificação visual
  read-only em 2026-10-31, a contagem caiu para 611; a comparação entre os dois
  snapshots mostra que as únicas tabelas removidas foram as três
  `field_deleted_data_*` descritas acima, pela purga normal do Field API. Não
  houve `DROP` manual; o snapshot pré-teste atualizado registra as 611 tabelas.

## Verificações

- Dependências de extensões de configuração: PASS, 1.687 checks.
- Parse YAML de `config/sync`: PASS, 1.419 arquivos.
- Testes Messenger Unit e Kernel: PASS, 16 testes e 269 assertions; duas
  deprecações upstream de descoberta por annotation. O diretório de saída HTML
  não estava gravável, sem impacto no resultado.
- Runtime: dez raízes de domínio responderam HTTP 200; Conta respondeu HTTP 302,
  redirecionando para autenticação conforme esperado.
- FAQ: seis Nodes mantidos; módulos MCP: 14/14 ativos e alinhados.

## Snapshots externos ao repositório

Snapshots mode `0600`, guardados em
`/home/piradopirata/aculta-suite-out/runtime-snapshots/`:

| Momento | Arquivo | SHA-256 |
| --- | --- | --- |
| Antes do import corrigido | `aculta_runtime-before-corrected-sync-import-20261031.sql` | `79fb61644ca90cc7754276e8cf822d804cdeefd53c0fdaae19231c7bf1d9966e` |
| Antes de restaurar configurações Messenger | `aculta_runtime-before-messenger-field-restore-20261031.sql` | `41e563af3572d24ca544d8f67ebcdd2c33012d9d2137de5c78004d3731652c6c` |
| Antes da migração dos 59 valores | `aculta_runtime-before-messenger-data-recovery-20261031.sql` | `1ee92b90a0aac702d2c97ef53ffa54f897fc7ae1e2d882028a9c2c037fdceb11` |
| Após reconciliação de configuração e dados | `aculta_runtime-after-config-and-data-reconciliation-20261031.sql` | `0f382ba9c47501fa8543df53704e71b24bc064f894b0250e576016f93d9cd732` |

O script one-shot de recuperação de dados ficou fora do repositório, junto aos
artefatos protegidos; SHA-256 `8177bf07dd6c5f4b940710fa1d53269a2caac9bf8e5aaff11a2074565a5e9398`.

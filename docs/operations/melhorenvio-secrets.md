# Melhor Envio: separação de chaves por ambiente

Contrato de credenciais do Melhor Envio. Segue `docs/operations/SECRETS.md`. Este documento não contém valores de
credencial, só nomes.

## Regra

- **Teste (sandbox):** valores em `secrets/aculta.secrets.env` (ignorado pelo Git). O Drupal lê pelo Key provider
  `env` e pelos Key IDs abaixo. Nada de valor no código, em `config/sync` ou em `config/install`.
- **Produção:** valores no banco, criptografados. O painel não grava nesse modo. Provisionamento pelo
  `aculta_deployer`, após o deploy (fase 9 do roadmap do deployer, ainda não implementada).
- Chave de produção nunca fica em `secrets/`, e nunca é usada no ambiente de teste. Teste nunca usa chave de produção.
- O ambiente ativo é definido pelo modo configurado do módulo (`environment`), não pelo formato da chave.

## Key IDs e variáveis

| Key ID | Variável de ambiente (teste) | Ambiente | Sensível | Uso |
| --- | --- | --- | --- | --- |
| `melhorenvio_sandbox_client_id` | `MELHORENVIO_SANDBOX_CLIENT_ID` | Teste (sandbox) | Não | URL de autorização OAuth2 |
| `melhorenvio_sandbox_client_secret` | `MELHORENVIO_SANDBOX_CLIENT_SECRET` | Teste (sandbox) | Sim | troca de código, renovação e assinatura do webhook |
| `melhorenvio_production_client_id` | (banco, provisionado pelo deployer) | Produção | Não | URL de autorização OAuth2 |
| `melhorenvio_production_client_secret` | (banco, provisionado pelo deployer) | Produção | Sim | troca de código, renovação e assinatura do webhook |

## Variáveis novas (0.1.0-dev.5) para provisionamento

Só nomes. Os valores ficam no ambiente do servidor, nunca no repositório, na configuração ou no Config Sync.

| Variável | Ambientes | Obrigatória | Formato |
| --- | --- | --- | --- |
| `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` | teste e produção | Sim | 64 caracteres hex (32 bytes), gerada com `openssl rand -hex 32` |
| `MELHORENVIO_PRODUCTION_CLIENT_ID` | produção | Sim | texto |
| `MELHORENVIO_PRODUCTION_CLIENT_SECRET` | produção | Sim | texto |

Trocar a chave de cifragem invalida os tokens guardados: o usuário precisa autorizar o Melhor Envio de novo.

### Ordem de implantação (obrigatória)

1. Provisionar `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` no ambiente do servidor, antes de qualquer `drush updatedb`.
   A chave é gerada no próprio servidor com `openssl rand -hex 32` e não é registrada em repositório, configuração ou
   log.
2. Só então rodar `drush updatedb`. O `update_8002` cifra os tokens legados. Sem a chave, ele falha e a atualização
   fica pendente; nenhum token é apagado nem gravado em texto puro.
3. Registros legados malformados são apagados pelo `update_8002` e contados à parte. O resultado do hook informa
   quantos foram cifrados e quantos foram removidos. Quem teve o registro removido autoriza o Melhor Envio de novo.

Tokens de acesso e de renovação não são chaves de configuração: ficam no armazenamento de tokens do módulo
(State API), por ambiente, e expiram. Nunca entram em `secrets/`, no código ou na configuração.

## Mapeamento no módulo

`keys.sandbox.client_id` e `keys.sandbox.client_secret` (em `aculta_commerce_melhorenvio.settings`) recebem os Key IDs
de teste. `keys.production.*` recebe os de produção. Só os nomes ficam na configuração; os valores ficam no Key.

## Estado

- **Chave de cifragem provisionada no servidor de testes (2026-10-26).** `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` gravada em `secrets/aculta.secrets.env`, uma linha, gerada no próprio servidor. Autorizada pelo responsável (mensagem de 18:23: apenas a chave; `updatedb` segue proibido). Backup do arquivo original, fora do Git e do docroot, em `/home/piradopirata/worktrees/backup-secrets-20261026-1830/` (modo 600). Verificação: via Drush, comprimento 64 e formato hexadecimal PASS (valor não exibido). Via web: a linha aparece na aba Testes como "preenchida", status OK. Durante a gravação, o modo do arquivo ficou 660 por causa da máscara da ACL e o loader recusou o arquivo; restaurado para 640, com a ACL original.
- **Rollback:** restaurar o backup (com acesso de escrita concedido ao dono) e rodar `drush cr`.
- **Efeito:** trocar a chave invalida os tokens cifrados; o usuário precisa autorizar o Melhor Envio de novo. Enquanto `drush updatedb` não rodar, registros legados em texto puro não são lidos (falha fechada, reautorização).
- Credenciais sandbox do Melhor Envio continuam pendentes.

- Inventário de 2026-10-26 (somente leitura, sem valores): `secrets/aculta.secrets.env` contém as três variáveis do
  sandbox do Melhor Envio: `MELHORENVIO_SANDBOX_CLIENT_ID`, `MELHORENVIO_SANDBOX_CLIENT_SECRET` e
  `MELHORENVIO_SANDBOX_ACCESS_TOKEN`. As três estão no contrato (ambiente `test`), na allowlist de
  `web/sites/default/aculta.secrets.php` e resolvem pelo Drupal (`valueForKeyId`): presentes, com comprimento > 0.
  A afirmação anterior de que `secrets/` não tinha credencial do Melhor Envio estava errada e foi corrigida.
- Origem das credenciais sandbox: **a confirmar pelo responsável.** O arquivo não declara a origem; o nome indica
  sandbox, mas não há prova no próprio arquivo.
- Produção no servidor de teste: `aculta.secrets.env` também tem `MERCADOPAGO_PRODUCTION_PUBLIC_KEY`,
  `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN` e `MERCADOPAGO_WEBHOOK_SECRET` (este último nos dois ambientes). O contrato
  reserva as chaves de Mercado Pago de produção ao ambiente `production`. **Decisão pendente do responsável:**
  retirar do servidor de teste ou aceitar explicitamente. Não alterado nesta execução.
- Melhor Envio: a Key de produção (`melhorenvio_production_client_id` e `_client_secret`) não está no arquivo, como
  esperado. A Key de sandbox está. O estado do webhook (que respondia 503 sem chave de sandbox) não foi reverificado
  nesta execução.
- Pendente, do deployer: provisionar as chaves de produção no banco criptografado, fora deste ciclo de teste.
- Pendente, do agente principal: incorporar esta separação à tabela central de `docs/operations/SECRETS.md`.

## Servidor MCP da documentação do Melhor Envio

- O `.mcp.json` da raiz configura o servidor `melhorenvio` (`https://docs.melhorenvio.com.br/mcp`, via `npx mcp-remote`).
- O token não vai para o repositório. O arquivo lê `${MELHORENVIO_MCP_TOKEN}` do ambiente de quem executa o Claude Code. Defina a variável no shell antes de iniciar a sessão.
- Sem a variável, o servidor não conecta; o restante do projeto não depende dele.
- Este nome não faz parte do contrato de segredos do servidor. Incluí-lo lá exige decisão separada.

### Decisões do responsável sobre `secrets/` (2026-10-26, 18:32)

- **Mercado Pago de produção no servidor de testes:** `MERCADOPAGO_PRODUCTION_PUBLIC_KEY`, `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN` e `MERCADOPAGO_WEBHOOK_SECRET` permanecem em `secrets/aculta.secrets.env`, aceitas explicitamente pelo responsável como **exceção** à regra "teste nunca usa chave de produção". A barreira de execução continua em modo de teste (ver abaixo).
- **Credencial SSH de produção** (`secrets/producao-ssh.env`) permanece, **sem rotação**.
- **Permissões:** `chmod 600` não foi aplicado nos arquivos de modo `674`. Ele zeraria a máscara da ACL e cortaria os usuários nomeados `www-data`, `toca` e `aculta`, que têm entrada na ACL e processos ativos. A remoção de leitura para "outros" (`674` → `670`) foi aplicada em `credenciais_de_teste_mercadolivre` e `system`. Dono, conteúdo e ACL não mudaram. `producao-ssh.env` já estava em `670`.
- **Regra de escrita:** gravar em `secrets/` somente como o dono do arquivo (`sudo -u aculta`), sem ACL de escrita concedida ao usuário de trabalho. A regra vale para qualquer alteração de conteúdo.
- **Incidente de 18:25 (provisão da chave):** a ACL de escrita deixou o arquivo com modo `660`, e o loader recusou o arquivo ("permissões muito amplas"). A janela foi de alguns minutos, entre a gravação (18:25:32) e a restauração, e o modo final voltou a `640` com a ACL original. **Não consegui verificar se houve acesso ao site nesse intervalo**: os logs do Apache (`/var/log/apache2/`) não são legíveis pelo usuário de trabalho. Confirmação pendente pelos logs, com acesso do responsável.

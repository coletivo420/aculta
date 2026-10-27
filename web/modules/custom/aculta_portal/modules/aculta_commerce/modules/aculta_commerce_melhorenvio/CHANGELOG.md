# Changelog — ACULTA Commerce Melhor Envio

## 0.1.0-dev.9 — persistir o último CEP para preenchimento automático (2026-10-27)

- Visitantes guardam o último CEP válido na sessão; contas autenticadas usam `user.data`, removido automaticamente quando a conta é excluída. O CEP salvo no endereço `authenticated` é usado como padrão se ainda não houver CEP do simulador.
- CEP salvo preenche o componente e inicia a cotação ao abrir. Não foi criada tabela nem necessário update de esquema.
- A saída varia por `user` e `session` e não entra no render cache, para evitar expor ou servir o CEP de outra pessoa.
- Firefox ESR visível confirmou loading enquanto a resposta AJAX estava suspensa, cotação sandbox após liberar a resposta, restauração do CEP anônimo após reload e recálculo automático. Unit específicos cobrem sessão, `user.data`, CEP do perfil e rejeição de entrada inválida.
- Política de Privacidade e Termos de Uso receberam a finalidade nas minutas. A publicação em produção aguarda a publicação dos textos.

## 0.1.0-dev.8 — indicador acessível de cálculo de frete (2026-10-27)

- Durante a consulta AJAX, a caixa de resultado mostra um ícone animado e “Calculando o frete…”, com `role="status"` e `aria-busy`; a animação respeita movimento reduzido.
- O roteiro Firefox segura a resposta do endpoint para verificar o estado de carregamento e depois libera a cotação sandbox real.

## 0.1.0-dev.7 — não bloquear o submit do carrinho pelo simulador (2026-10-27)

- O componente de cotação do carrinho deixou de emitir um `<form>` aninhado no formulário do Commerce e o CEP opcional não tem mais `required`. Isso corrige o bloqueio nativo do botão “Fechar pedido”; a cotação do CEP continua automática ao digitar.
- Regressão no roteiro visual do checkout verifica que o CEP do simulador não é obrigatório e que o fluxo anônimo chega ao cadastro/etapa de endereço sem CAPTCHA. Firefox ESR: `guest_registration_and_shipping_stage_without_captcha`; sem envio de endereço, cotação ou pagamento.

## 0.1.0-dev.6 — Não validado — simulador de frete por CEP (ME-0.1-A e ME-0.1-C)

### Adicionado

- Auditoria da API e decisões em `docs/SHIPPING-SIMULATOR-AUDIT.md` e `docs/DECISOES-FRETE.md`.
- `MelhorEnvioClientInterface` (contrato do cliente REST) e `hasCredentials()`, que confere as chaves sem chamar a API.
- `ShippingSimulator`: estados disponível, indisponível (sem origem, sem credencial, sem pacote, CEP inválido ou falha) e sem opções; cache de 5 min para respostas bem-sucedidas.
- `ShippingQuoteStore`: último cálculo do visitante na sessão por 15 min, lido pelo produto e pelos carrinhos.
- `CartRefreshSubscriber`: recalcula o frete guardado ao adicionar, alterar quantidade ou remover item, em todos os carrinhos (eventos do `CartManager`).
- Rota `/frete/simular` (JSON, com limite por IP) e formulário administrativo da origem em `/integracoes/melhorenvio/configuracao/origem`.
- Encaixes: página do produto e carrinho completo (via `ShippingCalculatorHooks`).

### Alterado

- Páginas administrativas da integração em `/integracoes/melhorenvio/configuracao` (antes no caminho reescrito pelo `rename_admin_paths`).
- `MelhorEnvioQuoter::attempt()` informa a falha ao chamador; `quote()` mantém o contrato anterior.

### Validação

- Testes unitários: 119 testes, 306 asserções. Sem chamada à API: a origem está configurada no servidor de testes, mas não há pacote, então o simulador responde indisponível.
- Não validado no navegador nem com cotação real. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.5 (em andamento)

### Alterado

- Tokens OAuth do Melhor Envio são cifrados em repouso no State API (`encrypted_v1`), com
  `MelhorEnvioTokenCipher` (libsodium `secretbox`). A chave vem do contrato central de segredos pelo Key ID
  `aculta_melhorenvio_token_encryption` (variável `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY`, 64 caracteres hex).
- `SecretsManager::valueForKeyId()` resolve um valor pelo Key ID estável; Key ID ambíguo ou inexistente falha fechado.
- Credenciais de produção (`melhorenvio_production_client_id` e `_client_secret`) ganham Key versionada com provedor
  `env`, como o sandbox.

### Corrigido

- Falha de decifração, chave ausente ou formato inválido equivalem a "sem tokens": o usuário precisa autorizar de novo.
  Nenhum texto puro é devolvido nem gravado.
- Hook `update_8001` converte nomes legados de variáveis em Key IDs e não altera nada quando não há nomes legados.
- Hook `update_8002` cifra os registros legados uma vez, pula os já cifrados e falha se a chave não estiver disponível
  (a atualização fica pendente, sem perda de dados).
- Registro legado malformado é apagado e contado à parte (`malformed`), sem exceção, para forçar nova autorização.
  Só a chave de cifragem ausente interrompe a migração. Coberto por
  `testMigrationDeletesMalformedRecordAndCountsItWithoutException` e `testMigrationWithoutKeyFailsAndWritesNothing`.

### Verificado

- Unit do cipher: 9 testes / 13 asserções OK (ida e volta, nonce novo, chave errada, chave ausente, chave malformada,
  adulteração, store sem chave).
- Unit `SecretsManagerTest`: 15 testes / 48 asserções OK (inclui `valueForKeyId`).
- Kernel `MelhorEnvioWiringKernelTest` em `aculta_simpletest`: 15 testes / 69 asserções OK, com 130 deprecações do
  Drupal/Core.
- `validate-secrets-contract.php` e `validate-module-theme-boundary.php`: PASS.
- PHPCS dos arquivos novos (cipher e seu teste): sem erros nem avisos. Nos arquivos já existentes, o débito anterior
  foi mantido.

## 0.1.0-dev.4 (em andamento)

### Corrigido

- Valores monetários da cotação são lidos como texto decimal, sem `float`. Valores com até duas casas são preservados;
  precisão além de centavos é recusada em vez de arredondada. Coberto por `testMoneyStaysExactAndRejectsSubCentPrecision`.
- O cliente e o controller do webhook ganharam regressões de 403, resposta vazia, 5xx, timeout, assinatura, corpo
  inválido, segredo ausente e repetição. A suíte Unit reexecutada na PR: 85 testes / 204 assertions PASS.
- Kernel OAuth cobre retorno sem código e replay de `state`; a classe completa reexecutada na PR: 11 testes / 57
  assertions PASS em MariaDB dedicado, sem rede externa.
- PHPCS Drupal/DrupalPractice nos quatro arquivos PHP tocados pela integração: zero erros; avisos apenas de linhas longas.
- O adaptador resolve credenciais primeiro pelo Drupal Key e usa `aculta_portal.secrets_manager` como fallback. Nenhum
  valor foi colocado no Config Sync.

### Verificado, sem alteração de código

- Kernel de wiring no MariaDB dedicado com `commerce_shipping` ausente: resultado desta integração é 11 testes / 57
  assertions PASS; a evidência anterior de 9 / 45 é substituída.
- Retentativas: a cotação (`POST /api/v2/me/shipment/calculate`) é enviada como idempotente. Não há no repositório
  prova contratual disso; a compra (`/shipment/checkout`), a geração e o cancelamento não são idempotentes e não são
  repetidos.
- Webhook: a documentação oficial confirma HMAC-SHA256 do corpo com o segredo do aplicativo, mas não diz a codificação.
  O exemplo publicado tem 44 caracteres terminados em `=`, o que indica base64; hex nunca apareceu como evidência.
  Pendente: confirmar com um webhook real do sandbox.

## 0.1.0-dev.3

### Alterado

- Provider de frete integrado ao plugin manager, à remessa e às tarifas do motor proprietário `aculta_commerce`; removida a dependência funcional de `commerce_shipping` no manifesto.
- O teste Kernel de wiring carrega a cadeia real de módulos incluindo Studio e Messenger, sem `commerce_shipping`, e passou nesta branch: 9 testes / 45 assertions, sem rede externa. Reexecutado na PR #298 com Core/vendor físicos do worktree: 9 testes / 45 assertions; a tentativa anterior com Core symlinkado ao checkout compartilhado foi descartada como bootstrap inválido.
- A página de status usa o contrato neutro `PortalLinkElement` e o token `data-portal-ui="button-primary"`, sem classes visuais do tema dentro do módulo.
- A suíte Unit passou: 74 testes / 181 assertions com root isolado da PR. A tentativa anterior com Core symlinkado ao checkout compartilhado foi descartada como bootstrap inválido. PHPCS Drupal/DrupalPractice não encontrou erros nos arquivos verificados; permanecem avisos de linhas acima de 80 colunas, incluindo comentários preexistentes.
- O teste Kernel foi executado em banco isolado. O módulo permanece fora do Config Sync conforme decisão operacional; nenhum Runtime compartilhado foi alterado. Homologação visual e integração real no sandbox continuam pendentes.

## 0.1.0-dev.1 (em andamento)

### Adicionado

- Cliente HTTP OAuth2 do Melhor Envio (`Http\MelhorEnvioClient`): `authorization_code`, renovação de token, timeouts,
  espaçamento mínimo entre requisições e retentativas apenas em operações idempotentes.
- Ambientes `sandbox` e `production` com tokens separados (`StateMelhorEnvioTokenStore`).
- Configuração `MelhorEnvioConfig` com nomes de chaves do Drupal Key por ambiente. Nenhum valor de credencial no código
  nem na configuração exportada.
- Exceção `MelhorEnvioException` com categorias de erro e mensagens fixas.
- Dependência explícita do módulo `key`.
- 19 testes Unit do cliente, sem rede (`MockHandler`).

### Pendente

- Caminhos de autorização, token e cotação não confirmados na documentação pública (ME-PEND-01). Validar no sandbox.
- Campo `expires_in` e formato do cabeçalho `Authorization` a confirmar no sandbox.
- Criptografia em repouso dos tokens (ME-PEND-02).
- Kernel e Functional.

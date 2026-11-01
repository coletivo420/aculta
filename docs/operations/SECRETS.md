# ACULTA Secrets Contract

## Objetivo

Manter credenciais fora do Git, do armazenamento comum do Drupal e dos
exports, com o mesmo contrato em Homelab, Hostinger Web/Cloud, VPS, containers
e CI.

## Princípios

- Drupal Key é a interface única para integrações.
- Providers Key permanecem `env`; código e configuração Drupal usam somente o
  Key ID e o nome estável da variável.
- Configuração bruta e sincronizada não contêm valores reais. O Runtime
  Homelab concluiu a limpeza Google na R0.4.
- A configuração efetiva pode conter o valor em memória após o Key Config
  Override. Isso não significa que o valor foi persistido.
- O código Drupal não conhece caminhos físicos, serviços systemd, PHP-FPM ou
  detalhes do provedor de hospedagem.
- Segredos usam least privilege e ficam fora do document root.

## Estado operacional

R0.3.5 versionou o contrato portátil e o loader. Na R0.4, o Homelab passou a
usar o Secure Bootstrap Adapter: o settings local ignorado carrega o arquivo de credenciais
(hoje `secrets/aculta.secrets.env`; antes `/etc/aculta/secrets.env`, histórico), e os processos web e Drush usam o mesmo bootstrap.
Os campos Google do storage bruto e do Configuration Sync estão vazios; as
Keys resolvem os valores fora do banco e os Config Overrides preenchem a
configuração efetiva em memória. Hostinger Web/Cloud e produção ainda não foram
provisionadas. Os quatro objetos de configuração CAPTCHA localizados foram
alinhados ao pt-BR do Runtime em `config/sync`; os rótulos de login, cadastro e
recuperação, além do título, descrição e mensagem de erro, mantêm a mesma
política e operação. Nenhuma configuração de credencial Turnstile foi
modificada. O gate completo Portal/Security ainda para em `core.extension`,
porque o Runtime tem `config_translation` habilitado e o sync não; essa
diferença permanece fora do escopo e não foi importada nem exportada.

## Credenciais por ambiente (0.2.0-dev.11)

- **Teste e homelab:** o painel grava as credenciais em `secrets/aculta.secrets.env` (raiz do repositório, ignorado
  pelo Git, fora de `web/`). `settings.local.php` aponta para ele (`aculta_secrets_file`) e declara
  `aculta_secrets_storage = 'file'`.
- **Produção:** as credenciais ficam no banco de dados, criptografadas. O painel **não grava** nesse modo
  (`aculta_secrets_storage = 'database'`). O provisionamento da camada criptografada é responsabilidade do
  `aculta_deployer`, executado após o deploy (fase 9 do roadmap do deployer, ainda não implementada).
- Permissões do arquivo de teste: sem escrita de grupo e sem acesso de outros (0600, ou 0640 com ACL de leitura
  para o processo web). A regra é a mesma do loader do Drupal.
- O `aculta_deployer` não importa, não exporta e não valida valores de credenciais (0.1.7). Ele lê o contrato
  (`config/secrets-contract.json`) para o relatório de status.

## Variáveis atuais

| Key ID | Environment | Ambiente | Sensível | Encoding |
| --- | --- | --- | --- | --- |
| `google_oauth_client_id` | `GOOGLE_OAUTH_CLIENT_ID` | Produção e testes | Não, identificador público | plain |
| `google_oauth_client_secret` | `GOOGLE_OAUTH_CLIENT_SECRET` | Produção e testes | Sim | plain |
| `google_maps_api_key` | `GOOGLE_MAPS_API_KEY` | Produção e testes | Sim, chave pública do navegador com restrições de referenciador e Maps JavaScript API | plain |
| `google_geocoding_api_key` | `GOOGLE_GEOCODING_API_KEY` | Produção e testes (opcional; provisionada, mas sem uso pela aplicação após D-46) | Sim, chave de servidor com restrição de IP e Geocoding API | plain |
| `mercadopago_production_public_key` | `MERCADOPAGO_PRODUCTION_PUBLIC_KEY` | Produção | Não, uso no frontend | plain |
| `mercadopago_production_access_token` | `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN` | Produção | Sim | plain |
| `mercadopago_webhook_secret` | `MERCADOPAGO_WEBHOOK_SECRET` | Produção e testes (aplicação; obrigatória em produção) | Sim | plain |
| `turnstile` | `TURNSTILE_KEYS_JSON` | Produção | Sim, contém secret | Base64 |
| `mercadopago_test_public_key` | `MERCADOPAGO_TEST_PUBLIC_KEY` | Testes | Não, uso no frontend | plain |
| `mercadopago_test_access_token` | `MERCADOPAGO_TEST_ACCESS_TOKEN` | Testes | Sim | plain |
| `mercadopago_test_buyer_password` | `MERCADOPAGO_TEST_BUYER_PASSWORD` | Testes | Sim, senha do comprador de teste | plain |
| `turnstile_test` | `TURNSTILE_TEST_KEYS_JSON` | Testes | Sim, contém secret | Base64 |
| `aculta_id_cpf_fingerprint` | `ACULTA_ID_CPF_FINGERPRINT_KEY` | Produção e testes (provisionamento pendente em produção) | Sim, chave HMAC do índice CPF/CNPJ (mínimo de 32 bytes; ID mantido pelo `aculta_id_cpf``) | plain |
| `aculta_document_encryption_key` | `ACULTA_DOCUMENT_ENCRYPTION_KEY` | Produção e testes (provisionamento pendente em produção) | Sim, chave de cifra de documentos de associados/CPF/CNPJ (64 caracteres hexadecimais) | plain |
| `smtp2go_username` | `SMTP2GO_USERNAME` | Produção e testes (obrigatória nos dois) | Sim | plain |
| `smtp2go_password` | `SMTP2GO_PASSWORD` | Produção e testes (obrigatória nos dois) | Sim | plain |
| `brevo_api_key` | `BREVO_API_KEY` | Produção e testes | Sim, chave da API do Brevo (envio e webhook) | plain |
| `brevo_webhook_token` | `BREVO_WEBHOOK_TOKEN` | Produção e testes (obrigatória nos dois; produção pendente no deploy do RC) | Sim, token de portador do webhook do Brevo; um valor por ambiente | plain |
| `ga4_property_id` | `GA4_PROPERTY_ID` | Produção e testes (opcional; sem ela, o GA4 fica desligado) | Não, identificador da propriedade | plain |
| `ga4_service_account_json` | `GA4_SERVICE_ACCOUNT_JSON` | Produção e testes (opcional; sem ela, o GA4 fica desligado) | Sim, chave da conta de serviço | Base64 |
| `gsc_site_url` | `GSC_SITE_URL` | Produção e testes (opcional; sem ela, o Search Console fica desligado) | Não, propriedade do Search Console (`sc-domain:` ou URL com prefixo) | plain |
| `mailer_payload_key` | `MAILER_PAYLOAD_KEY` | Testes (produção pendente no deploy do RC) | Sim, chave de 32 bytes (base64) que cifra o conteúdo das mensagens com falha guardadas para reenvio | plain |
| `imap_password` | `IMAP_PASSWORD` | Produção e testes | Sim, senha da caixa de monitoramento de retorno | plain |
| `melhorenvio_sandbox_client_id` | `MELHORENVIO_SANDBOX_CLIENT_ID` | Testes | Não, identificador do cliente sandbox | plain |
| `melhorenvio_sandbox_client_secret` | `MELHORENVIO_SANDBOX_CLIENT_SECRET` | Testes | Sim | plain |
| `melhorenvio_sandbox_access_token` | `MELHORENVIO_SANDBOX_ACCESS_TOKEN` | Testes | Sim | plain |
| `melhorenvio_production_client_id` | `MELHORENVIO_PRODUCTION_CLIENT_ID` | Produção | Não, identificador do cliente | plain |
| `melhorenvio_production_client_secret` | `MELHORENVIO_PRODUCTION_CLIENT_SECRET` | Produção | Sim | plain |
| `aculta_melhorenvio_token_encryption` | `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` | Produção e testes (obrigatória nos dois) | Sim, chave de cifra do token OAuth | plain |
| `osm_test_client_id` | `OSM_TEST_CLIENT_ID` | Testes (não provisionada; ver exceção do OSM) | Não, ID da aplicação OAuth2 de desenvolvimento do OSM | plain |
| `osm_test_client_secret` | `OSM_TEST_CLIENT_SECRET` | Testes (não provisionada; ver exceção do OSM) | Sim, segredo da aplicação OAuth2 de desenvolvimento do OSM | plain |
| `osm_production_client_id` | `OSM_PRODUCTION_CLIENT_ID` | Produção, testes e desenvolvimento (credencial única do OSM) | Não, ID da aplicação OAuth2 da API ativa; no teste só é resolvido com `aculta_openstreetmap_environment=production` (D-45) | plain |
| `osm_production_client_secret` | `OSM_PRODUCTION_CLIENT_SECRET` | Produção, testes e desenvolvimento (credencial única do OSM) | Sim, segredo da aplicação OAuth2 da API ativa; em teste fica somente em `secrets/aculta.secrets.env` (D-45) | plain |
| `aculta_osm_token_encryption` | `ACULTA_OSM_TOKEN_ENCRYPTION_KEY` | Produção e testes | Sim, chave hex de 64 caracteres para cifrar tokens OAuth2 no State | plain |
| `smtp_password` | `SMTP_PASSWORD` | Produção e testes | Sim, senha do envio SMTP do domínio | plain |

Os ambientes vêm do contrato (`environments` em `config/secrets-contract.json`). Variáveis
compartilhadas aparecem nas duas seções do painel. Mercado Pago: ver [MERCADOPAGO.md](../integrations/MERCADOPAGO.md).
Pela D-45, o checkout de teste pode selecionar explicitamente a API ativa do OSM;
nesse modo as credenciais de cliente OAuth de produção ficam no arquivo de segredos
do teste e o token do usuário continua cifrado no State do banco de teste. Sem a
configuração explícita, o site de teste continua usando a API de desenvolvimento.

**Exceção do OSM (2026-10-29):** uma única aplicação OAuth do OpenStreetMap atende
teste, desenvolvimento e produção. O arquivo de segredos do servidor traz somente
`OSM_PRODUCTION_CLIENT_ID`, `OSM_PRODUCTION_CLIENT_SECRET` e
`ACULTA_OSM_TOKEN_ENCRYPTION_KEY`; `OSM_TEST_*` não está provisionada. A sonda de
2026-10-29 confirmou `site=test`, ambiente efetivo `production` (D-45), credencial
de produção presente e ausência de token OAuth. A autorização continua exigindo
login humano no OSM e cada escrita no mapa público exige revisão e confirmação
explícita. Nenhum valor é versionado.
O allowlist do loader do Runtime também deve reconhecer cada variável do contrato,
inclusive as credenciais opcionais; o gate `validate-secrets-contract.php`
verifica essa correspondência.

## Regra ao incluir uma variável

Uma variável nova exige, no mesmo PR, três alterações:

1. `config/secrets-contract.json` (nome, Key e ambientes);
2. a lista de variáveis permitidas em `web/sites/default/aculta.secrets.php`. Sem ela, o carregador recusa o arquivo inteiro e o site responde 500 (incidente do login com Turnstile, corrigido na PR #164);
3. a tabela desta página.

Depois do deploy, importar a Key correspondente (`config:import`) e conferir com `key.repository`.

## Arquitetura

```text
              ACULTA Secrets Contract
                        │
            variáveis com nomes estáveis
                        │
        ┌───────────────┴───────────────┐
        │                               │
Native process environment   Secure bootstrap adapter
        │                               │
Homelab / VPS / container     Managed hosting / Hostinger
        └───────────────┬───────────────┘
                        │
                 PHP process env
                        │
                 Drupal Key/env
                        │
             Key Configuration Override
                        │
             integração contrib
```

O código Drupal e a configuração sincronizada dependem somente do nome lógico
da Key e do nome da variável de ambiente. Caminhos físicos, gerenciadores de
serviço e mecanismos de provisionamento pertencem ao ambiente; não podem virar
dependência do Portal ou do tema.

### Native Environment Adapter

Indicado para Homelab Debian, VPS, containers e CI. A plataforma pode usar
systemd `EnvironmentFile`, variáveis explicitamente permitidas no PHP-FPM,
container secrets, CI variables ou mecanismo equivalente. systemd não é um
requisito arquitetural.

### Secure Bootstrap Adapter

Indicado para hospedagem gerenciada quando não controlamos o ambiente do
processo. Um settings local ignorado aponta para um arquivo secreto fora do
document root e inclui `web/sites/default/aculta.secrets.php`. O loader
versionado publica somente a allowlist aprovada com `putenv()` e não imprime
dados. Ele não usa shell, `source`, `eval` ou dependência dotenv.

O arquivo usa linhas `NAME=value`. Valores com espaços ou caracteres especiais
devem ser quoted no formato INI. O parser não expande outras variáveis.

## Precedência

Environment nativo não vazio tem precedência sobre o arquivo bootstrap. O
loader não substitui uma variável nativa já definida. O arquivo pode conter
qualquer subconjunto da allowlist; integrações opcionais não são obrigatórias
globalmente.

## Homelab

`settings.homelab.php` continua local e ignorado pelo Git. O Homelab pode usar
environment nativo no processo PHP ou o adapter bootstrap apontado pelo próprio
settings local. Atualmente o Homelab exercita o Secure Bootstrap Adapter por
meio de `secrets/aculta.secrets.env` (ver "Arquivo local"); esse caminho está somente no settings local
ignorado e não é uma dependência do Portal ou do tema. A mesma configuração
bootstrap atende Drush e requests web. Hostinger ainda não foi provisionada.

No adapter bootstrap, use proprietário/grupo definidos pelos usuários reais do
Runtime e o menor acesso necessário para PHP-FPM e CLI. Por exemplo, diretório
`0750` e arquivo `0640` podem ser adequados quando a ACL/grupo permite somente
aos processos necessários a leitura. Não use permissões abertas para facilitar
Drush.

## Hostinger Web/Cloud

Estrutura conceitual:

```text
/home/ACCOUNT/
├── .aculta/
│   └── secrets.env
└── .../public_html/
    └── Drupal
```

O arquivo secreto fica fora de `public_html`. Prefira `0600` se PHP web e SSH
usarem o mesmo usuário. Caso o plano use identidades distintas, aplique o menor
acesso que permita ao Runtime ler. Um settings local ignorado define
`$settings['aculta_secrets_file']`; o exemplo versionado é apenas scaffolding,
não um settings completo. O banco MariaDB permanece definido na configuração
local de produção.

## Hostinger VPS

VPS pode usar o adapter nativo (systemd, PHP-FPM, container) ou o bootstrap
seguro, conforme a administração escolhida. O Drupal, as Keys e os Config
Overrides não mudam.

## Drupal Key

As entities Key devem usar provider `env` e o nome exato da variável da tabela
acima. Não criar provider custom nem fazer integração ler arquivos físicos.

## Key Configuration Override

Config Overrides aplicam as Keys de OAuth às opções de Social Auth Google.
Outras integrações seguem seus próprios overrides ou referências Key. O
override é a ponte entre a abstração Key e a configuração contrib.

## Config sync

Três camadas devem permanecer distintas:

1. **Raw config storage:** sem credenciais; no Homelab após R0.4,
   `client_id: ''` e `client_secret: ''`.
2. **Key config:** somente Key ID, provider `env` e nome da variável.
3. **Effective config:** pode conter valores em memória pelo override durante
   a requisição. Não exportar essa visão efetiva como segredo persistente.

Nunca preencher credenciais reais diretamente no formulário de configuração
contrib como solução permanente, nem executar export que grave esses valores.
Não usar `drush config:set social_auth_google.settings client_secret <valor>`
como estado permanente; a recuperação operacional deve seguir o fluxo da fonte
externa para Key/env e Config Override.

## Database

Não salvar secrets no banco (MariaDB) para simplificar o desenvolvimento.
O banco guarda entidades/configuração de referência; o valor vem do adapter do
ambiente.

## Backups

Após a migração R0.4, novos dumps do banco não transportam credenciais Google
no storage bruto. Backups e snapshots criados antes da limpeza continuam
sensíveis e precisam de inventário, retenção e permissões restritas. O backup
pós-migração do Homelab foi verificado com os campos Google brutos vazios.
Nunca publicar em Git um dump ou Estado que contenha secret persistido;
isso também se aplica aos Estados Homelab que, por decisão do projeto, podem
ser públicos quando sanitizados.

## CLI / Drush

Com o Secure Bootstrap Adapter, Drush carrega os mesmos settings do Drupal e
resolve as mesmas Keys sem `source secrets.env` no shell. Isso reduz divergência
entre web e CLI e evita comandos de export manual. O adapter nativo também é
válido quando a CLI já recebe as variáveis aprovadas.

Nunca imprimir Key values em gate, log ou diagnóstico. Gates devem emitir
somente estados como `SET`, `EMPTY`, `MATCH` ou `FAIL`.

## Rotation

Troque a credencial no provedor, atualize a fonte host-local do ambiente,
reinicie/recarregue somente o processo que precisa recebê-la e confirme a Key e
o fluxo funcional sem revelar o valor. Remova cópias temporárias depois de
validar o novo valor; preserve rollback restrito pelo período operacional
definido.

## Adding a new secret

1. criar/usar Key com provider `env`;
2. definir um nome estável de environment;
3. atualizar esta tabela e o validator do contrato;
4. adicionar Config Override se a integração exigir;
5. provisionar a variável por adapter em cada ambiente;
6. provar raw/sync vazios, Key resolvida e comportamento efetivo sem imprimir
   valores.

Nunca corrigir integração preenchendo secret em config, movendo secret para o
tema ou hardcoding caminho Homelab/Hostinger no Portal.

## Credenciais do ambiente (painel, aculta_portal 0.2.0-dev.11)

Página `/painel-administrativo/aculta/segredos` (permissão `administer aculta secrets`, restrita):

1. Mostra duas seções fixas: **Ambiente de Produção** e **Ambiente de Testes**. Cada uma lista as variáveis do
   contrato daquele ambiente (variáveis compartilhadas aparecem nas duas), a Key, se é obrigatória naquele ambiente
   e o estado (✔ preenchida, ⚠ opcional sem valor, ✖ obrigatória sem valor). A troca de ambiente continua a cargo do
   ACULTA Deployer; o painel não troca nada, só mostra o ambiente atual declarado.
2. Valores salvos aparecem **mascarados** (dois caracteres de cada ponta; valores curtos viram só bolinhas).
   O botão 👁 abre um popup com o valor completo. O popup é carregado por uma rota com token CSRF, sem cache e
   registrada no log (nome e usuário). O valor fica só dentro do popup: nunca no HTML da página.
3. Cadastrar ou alterar: o ícone ✎ ao lado do olho abre um popup com um único campo de senha para aquela variável.
   Salvar grava só essa variável; as demais permanecem. O valor digitado não volta à tela nem ao log. O ✎ só
   aparece onde a gravação é possível (teste, arquivo de credenciais).
4. Salvar grava o arquivo de credenciais de forma atômica (modo 0640 em teste). Só nomes do contrato são aceitos,
   valores com quebra de linha são recusados e o tamanho é limitado a 4 KiB.
5. Cada revelação e cada salvamento geram entrada no log com nomes e usuário. Nenhum valor vai para o log, o
   banco ou a configuração exportada.

Limites: valores novos valem a partir da próxima requisição (o Key lê o ambiente no bootstrap). Em produção o
formulário não grava (ver acima). O painel não substitui cofre de senhas: a revelação é restrita aos
administradores e fica registrada.

## Usuário de teste teste.comum
A senha do usuário de teste do site fica em `secrets/teste_comum.env`. Não alterar sem decisão do responsável. Procedimento e guardrail: `docs/operations/TESTE-COMUM.md` e `scripts/guard-teste-comum.sh`.

## Painel por ambiente (aba não é ambiente)

O painel `/painel-administrativo/aculta/segredos` (submódulo `aculta_secrets_manager`) tem duas abas:
Produção e Testes. **A aba não é ambiente de execução.** O ambiente vem do ACULTA Deployer
(`var/deployer/environment.json`). Trocar de aba não altera o ambiente nem libera acesso.

Estados possíveis de uma credencial:

| Estado | Onde aparece | O que o servidor sabe |
| --- | --- | --- |
| Configurada | aba do ambiente ativo | Valor presente (mascarado; revelação por ação explícita) |
| Obrigatória ausente | aba do ambiente ativo | Ausente; erro |
| Opcional ausente | aba do ambiente ativo | Ausente; atenção |
| Gerenciada no ambiente correspondente | aba do outro ambiente | Nada: não consulta valor nem existência |
| Somente leitura | consumidores | Lida pelo `aculta_portal.secrets_manager`, sem painel de edição |
| Gerenciamento autorizado | aba do ambiente ativo, com `administer aculta secrets` | Pode gravar, alterar e apagar no próprio ambiente |

Nunca há fallback entre ambientes: credencial ausente no ambiente ativo gera indisponibilidade
controlada, nunca leitura da instalação do outro ambiente.

### Matriz de consumidores (chave → quem lê)

| Credencial (Key) | Ambientes | Consumidor no código |
| --- | --- | --- |
| `google_oauth_client_id` / `_secret` | prod, test | `aculta_portal.install` (OAuth) |
| `smtp2go_username` | prod, test | `aculta_mailer` (`MailTransport`) |
| `smtp_password` / `imap_password` | prod, test | `aculta_mailer` (`MailTransport`, `MailMonitor`) |
| `brevo_api_key` | prod, test | `aculta_mailer` (`BrevoSecretTransportFactory`), `aculta_deployer` |
| `brevo_webhook_token` | prod, test | `aculta_mailer` (`BrevoWebhook`), `aculta_deployer` |
| `mailer_payload_key` | prod, test | `aculta_mailer` (`MailPayloadCipher`), `aculta_deployer` |
| `mercadopago_production_*` | prod | `MercadoPagoCredentials` |
| `mercadopago_test_*` | test | `MercadoPagoCredentials` |
| `mercadopago_webhook_secret` | prod, test | `WebhookEventSubscriber` |
| `turnstile` / `turnstile_test` | prod / test | `TurnstileKeyOverride`, `TurnstileToggle` |
| `aculta_document_encryption_key` | prod, test | `aculta_id_cpf` (`DocumentCipher`) |
| `aculta_id_cpf_fingerprint` | prod, test | `aculta_id_cpf` (`DocumentFingerprint`) |
| `ga4_property_id` / `ga4_service_account_json` | prod, test | `aculta_statistics` (`Ga4Settings`, `SecretsGa4Settings`) |
| `gsc_site_url` | prod, test | `aculta_statistics` (`SecretsSearchConsoleSettings`) |
| `melhorenvio_sandbox_*` | test | `aculta_commerce_melhorenvio` (sandbox) |

Consumidores que não aparecem por nome de chave leem pelo `aculta_portal.secrets_manager` ou pelo
provider de Key (`SecretProviderAdapter`), e recebem só o valor do ambiente ativo.

**Chaves de cifra e fingerprint de CPF/CNPJ** (`aculta_document_encryption_key`,
`aculta_id_cpf_fingerprint`) não devem ser trocadas sem migração planejada: uma troca sem migração
torna dados existentes ilegíveis e quebra correspondências. O painel não rotaciona nem regenera essas chaves.

### Procedimentos (resumo)

- **Rotação:** gerar a nova credencial no provedor, gravar pelo painel no ambiente correspondente,
  conferir o estado e revogar a antiga. Chaves de cifra/fingerprint: somente com migração planejada.
- **Recuperação:** credencial apagada por engano: regravar pelo painel no mesmo ambiente. Nunca copiar
  a credencial de outro ambiente.
- **Revelação, alteração e remoção:** popups abertos por clique (GET com token CSRF `_csrf_token`); a gravação e a remoção passam pelo formulário, com token de formulário. Revelação sem cache e registrada no log com
  nome e usuário, sem o valor).

### Migração do Portal para o submódulo

1. Deploy do código com `aculta_secrets_manager` presente.
2. Habilitar o submódulo no ambiente alvo (`drush en aculta_secrets_manager`), depois do deploy.
3. Conferir `/painel-administrativo/aculta/segredos` (abas, estado, sem valores).
4. Rollback: desabilitar o submódulo. Os links do Portal para as rotas `aculta_portal.secrets_*`
   falham até o submódulo ser reabilitado; por isso ele deve ficar habilitado junto com o Portal.

## Decisões do responsável sobre `secrets/` (2026-10-26, 18:32)

- **Mercado Pago de produção no servidor de desenvolvimento:** `MERCADOPAGO_PRODUCTION_PUBLIC_KEY`, `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN` e `MERCADOPAGO_WEBHOOK_SECRET` permanecem em `secrets/aculta.secrets.env`, aceitas explicitamente pelo responsável como **exceção** à regra "teste nunca usa chave de produção". A barreira de execução continua em modo de teste (ver abaixo).
- **Credencial SSH de produção** (`secrets/producao-ssh.env`) permanece, **sem rotação**.
- **Permissões:** `chmod 600` não foi aplicado nos arquivos de modo `674`. Ele zeraria a máscara da ACL e cortaria os usuários nomeados `www-data`, `toca` e `aculta`, que têm entrada na ACL e processos ativos. A remoção de leitura para "outros" (`674` → `670`) foi aplicada em `credenciais_de_teste_mercadolivre` e `system`. Dono, conteúdo e ACL não mudaram. `producao-ssh.env` já estava em `670`.
- **Regra de escrita:** gravar em `secrets/` somente como o dono do arquivo (`sudo -u aculta`), sem ACL de escrita concedida ao usuário de trabalho. A regra vale para qualquer alteração de conteúdo.
- **Incidente de 18:25 (provisão da chave):** a ACL de escrita deixou o arquivo com modo `660`, e o loader recusou o arquivo ("permissões muito amplas"). A janela foi de alguns minutos, entre a gravação (18:25:32) e a restauração, e o modo final voltou a `640` com a ACL original. **Não consegui verificar se houve acesso ao site nesse intervalo**: os logs do Apache (`/var/log/apache2/`) não são legíveis pelo usuário de trabalho. Confirmação pendente pelos logs, com acesso do responsável.

## Decisão do responsável sobre o modo dos arquivos de `secrets/` (18:39)

- `credenciais_de_teste_mercadolivre`, `system` e `producao-ssh.env` **permanecem em modo 670**. Não será aplicado 640 nem 600.
- Motivo: a máscara da ACL fica `rw-`, e as entradas nominais (`www-data`, `toca`, `aculta`) precisam dela para continuar funcionando.
- A regra "escrita em `secrets/` só como o dono do arquivo (`sudo -u aculta`)" vale como **procedimento dos agentes**, não como restrição de permissão do sistema de arquivos.
- O modo "outros" já foi removido (674 → 670) em `credenciais_de_teste_mercadolivre` e `system`. Não há leitura para outros usuários locais.

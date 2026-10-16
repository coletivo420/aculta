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
| `smtp2go_username` | `SMTP2GO_USERNAME` | Produção e testes | Sim, credencial | plain |
| `smtp2go_password` | `SMTP2GO_PASSWORD` | Produção e testes | Sim | plain |
| `mercadopago_production_public_key` | `MERCADOPAGO_PRODUCTION_PUBLIC_KEY` | Produção | Não, uso no frontend | plain |
| `mercadopago_production_access_token` | `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN` | Produção | Sim | plain |
| `mercadopago_webhook_secret` | `MERCADOPAGO_WEBHOOK_SECRET` | Produção e testes (aplicação; obrigatória em produção) | Sim | plain |
| `turnstile` | `TURNSTILE_KEYS_JSON` | Produção | Sim, contém secret | Base64 |
| `mercadopago_test_public_key` | `MERCADOPAGO_TEST_PUBLIC_KEY` | Testes | Não, uso no frontend | plain |
| `mercadopago_test_access_token` | `MERCADOPAGO_TEST_ACCESS_TOKEN` | Testes | Sim | plain |
| `mercadopago_test_buyer_password` | `MERCADOPAGO_TEST_BUYER_PASSWORD` | Testes | Sim, senha do comprador de teste | plain |
| `turnstile_test` | `TURNSTILE_TEST_KEYS_JSON` | Testes | Sim, contém secret | Base64 |
| `aculta_id_cpf_fingerprint` | `ACULTA_ID_CPF_FINGERPRINT_KEY` | Produção e testes (provisionamento pendente em produção) | Sim, chave HMAC do índice CPF/CNPJ (mínimo de 32 bytes; ID legado mantido pelo `aculta_associado_id`) | plain |
| `aculta_document_encryption_key` | `ACULTA_DOCUMENT_ENCRYPTION_KEY` | Produção e testes (provisionamento pendente em produção) | Sim, chave de cifra de documentos de associados/CPF/CNPJ (64 caracteres hexadecimais) | plain |

Os ambientes vêm do contrato (`environments` em `config/secrets-contract.json`). Variáveis
compartilhadas aparecem nas duas seções do painel. Mercado Pago: ver [MERCADOPAGO.md](../integrations/MERCADOPAGO.md).

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

Página `/painel-administrativo/configuracoes/aculta/segredos` (permissão `administer aculta secrets`, restrita):

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


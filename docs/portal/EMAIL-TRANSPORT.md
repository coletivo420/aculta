# Transporte de e-mail do Portal (migração para Mailer Plus)

Status: **M0 — decisão e plano**. Documento normativo para as fases M1 e M2. Esta fase não altera
código, configuração, composer nem banco.

Documentos relacionados: [DEBT-REGISTER](../operations/DEBT-REGISTER.md) (DT-P05, DT-P09, DT-O07),
[EMAIL-CONFIRMATION-POLICY.md](EMAIL-CONFIRMATION-POLICY.md), [GUARDRAILS.md](GUARDRAILS.md),
[DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md), `config/secrets-contract.json`.

## 1. Decisão

Migrar o envio de e-mail do `drupal/smtp` (1.4, SMTP via `smtp.settings`) para **Mailer Plus**
(`drupal/symfony_mailer` 2.x), em modo override (`mailer_override`), mantendo o provedor SMTP2GO
e as mesmas credenciais.

Não há troca de provedor. O SMTP2GO continua sendo o servidor de envio; muda apenas quem fala com ele
dentro do Drupal.

Motivos:

- **Commerce e Core User**: passam a enviar pelo Mailer Plus com suporte nativo em modo override, sem
  ajuste por módulo.
- **E-mail HTML com modelo**: deve resolver DT-P09 (mensagem de webform via SMTP sem o modelo HTML).
  O Mailer Plus declara que o Webform só funciona em modo de compatibilidade; isso **não é presumido**
  e precisa ser testado no Homelab antes de a DT-P09 ser fechada.
- **Base para a pilha Message**: o mesmo transporte servirá a e-mails institucionais com apresentação
  própria, sem novo provedor.

## 2. Verificação das dependências (fonte de verdade: código instalado e pacote publicado)

Conferido em 2026-10-11 no pacote publicado `drupal/symfony_mailer` 2.0.2 (zip oficial do drupal.org):

| Item | Constatação |
|---|---|
| Versão | `2.0.2` é a versão estável mais recente; `core_version_requirement: ^10.3 \|\| ^11`, compatível com o Core 11.4 instalado |
| Dependências PHP | `symfony/mailer ^6.0 \|\| ^7.0`, `html2text/html2text ^4.0.1`, `tijsverkoyen/css-to-inline-styles ^2.2` |
| Módulos do pacote | `symfony_mailer` (Mailer Plus), `mailer_transport`, `mailer_policy`, `mailer_override` |
| Transporte SMTP | `mailer_transport` → `SmtpTransportUI` com campos `user`, `pass`, `host`, `port` e `query` |
| Transporte padrão | `mailer_transport.settings:default_transport` (install: `sendmail`) |
| Override do Core | `mailer_override` substitui o gerenciador de e-mail do Core; `MailerConfigOverride` aplica `config_overrides` de plugins |

Fontes consultadas:

- Pacote `drupal/symfony_mailer` 2.0.2 (`https://ftp.drupal.org/files/projects/symfony_mailer-2.0.2.zip`), módulos
  `mailer_transport`, `mailer_override` e `README.md` do repositório 2.x
  (`https://git.drupalcode.org/project/symfony_mailer/-/raw/2.x/README.md`).
- Metadados de versão: `https://packages.drupal.org/files/packages/8/p2/drupal/symfony_mailer.json`.
- Página de documentação do projeto (`https://www.drupal.org/docs/contributed-modules/symfony-mailer-0`):
  renderizada por JavaScript e **não pôde ser lida**; a documentação de configuração foi conferida no código.
- Issues do projeto citadas em pesquisa, a checar no Homelab: "Circular reference in mailer_override
  MailerConfigOverride on Drupal 11.4 when class-based hooks pull logger.factory during construction" e
  "Sendmail still being used when SMTP is default" (`https://www.drupal.org/project/issues/symfony_mailer`).

**Não confirmado:** a documentação oficial de Key com Mailer Plus. O código do transporte SMTP não tem
integração com `drupal/key`. Ver seção 4.

## 3. Inventário do legado (o que sai na M2)

| Item | Onde está | Papel |
|---|---|---|
| `drupal/smtp` ^1.4 | `composer.json` (linha 250) | Envio SMTP atual |
| `smtp` (módulo) | `config/sync/core.extension.yml` (linha 97), `web/modules/contrib/smtp` | Módulo ativo |
| `smtp.settings` | `config/sync/smtp.settings.yml`; `smtp_on: true`, host `mail.smtp2go.com`, porta 2525, `smtp_password` vazio no arquivo | Configuração do envio |
| Override de senha | `config/sync/key.config_override.smtp_password.yml`, `key.config_override.smtp_username.yml` | Liga `smtp.settings` às Keys |
| Keys | `config/sync/key.key.smtp2go_username.yml`, `key.key.smtp2go_password.yml` (provider `env`, `SMTP2GO_USERNAME`, `SMTP2GO_PASSWORD`) | Credenciais, fora do Git |
| `system.mail` | `config/sync/system.mail.yml` (`interface.default: php_mail`, `webform: webform_php_mail`) | Backend do Core e do Webform |
| Secrets Contract | `config/secrets-contract.json` (linhas 31 e 44) | Declara as variáveis SMTP2GO |
| Deploy por ambiente | `aculta_deployer/config/deploy.json` (`environment_bound`, `smtp.settings`, `system.mail`, `system.site`, `webform.webform.aculta_contact`) | Remetente e backend por ambiente |
| Gate de Portal | `PortalController::transactionalMailReady()` (checa `moduleExists('smtp')`, `SMTPMailSystem`, `smtp.settings`) | Libera troca de e-mail em "Segurança" |
| Scripts e docs | `scripts/validate-*.php`, `docs/operations/*.md`, `docs/modules/*.md`, `docs/integrations/GOOGLE.md` | Referências a `smtp.settings` e a `SMTP2GO` |

Fora do escopo desta migração: `key.key.*` e `SMTP2GO_*` (são reaproveitados); o provedor SMTP2GO; o
Secrets Contract (não ganha variável).

## 4. Pontos que a proposta não cobria

### 4.1 Senha do transporte não vem de Key

`SmtpTransportUI` grava `user` e `pass` **dentro da configuração do transporte**
(`mailer_transport.mailer_transport.<id>`). O formulário não oferece Key. Pela regra do `AGENTS.md`,
senha não pode ir para configuração exportada.

Solução adotada para M1: **override nativo do módulo Key** (`key.config_override`), o mesmo mecanismo
que já liga `smtp.settings` às Keys hoje. Não é preciso código próprio. Verificado em
`web/modules/contrib/key/src/KeyConfigOverrides.php` (Key 1.22): o override aceita
`config_prefix` + `config_name` + `config_item` (caminho com pontos, por exemplo `configuration.pass`) e
`key_id`. Ele só altera a configuração carregada em memória; não grava nada.

Configuração a exportar no Homelab (M1, depois da ativação):

- `mailer_transport.mailer_transport.smtp2go`: plugin `smtp`, host `mail.smtp2go.com`, porta `2525`,
  `user` e `pass` vazios;
- `key.config_override.mailer_smtp2go_username`: `config_prefix: mailer_transport.mailer_transport`,
  `config_name: smtp2go`, `config_item: configuration.user`, `key_id: smtp2go_username`;
- `key.config_override.mailer_smtp2go_password`: o mesmo, com `configuration.pass` e
  `key_id: smtp2go_password`;
- `mailer_transport.settings:default_transport: smtp2go`.

Essas configurações **não entram em `config/sync` nesta fase**. Dependem do módulo `mailer_transport`
habilitado; importá-las antes da ativação quebraria `config:import`. A exportação é feita no Homelab
depois do teste (seção 5).

**Risco:** a resolução do override depende do Key carregado dentro de `loadOverrides()`. Confirmar no
runtime (ver passo 2 da seção 5).

### 4.2 Remetente

O remetente não é mais `smtp.settings:smtp_from`. Deve ser confirmado no Homelab se o Mailer Plus usa
`system.site:mail` ou a política `mailer_policy`. Até a confirmação, `deploy.json` continua apontando
`system.site` como fonte do remetente por ambiente.

### 4.3 Webform

O Webform só funciona em modo de compatibilidade no Mailer Plus. DT-P09 **só é fechada** após teste
real de envio de formulário com o modelo HTML no Homelab.

### 4.4 Override de Core e sessão

`mailer_override` troca o gerenciador de e-mail do Core. Qualquer mensagem enviada antes da ativação
continua pelo SMTP antigo. Não há migração de fila; a ativação é um ponto de corte.

## 5. Plano por fase

| Fase | Entrega | Runtime | Pré-requisito |
|---|---|---|---|
| **M0** | Este documento: decisão, inventário, plano e riscos | Não precisa (só docs) | — |
| **M1** | `composer require drupal/symfony_mailer:^2.0`; `transactionalMailReady()` checando `mailer_override`, o transporte `smtp2go` e as Keys; gate `validate-mail-transport.php`; CHANGELOG e versão do Portal. Override de senha via `key.config_override` (sem código próprio); exportação do transporte e dos overrides no Homelab; `deploy.json` quando os arquivos existirem em `config/sync` | **DEFERRED** até teste no Homelab. `drupal/smtp` continua instalado e inativo como rede de segurança | M0 mergeada |
| **M2** | Desinstalar `smtp`; apagar `smtp.settings` e os overrides de Key de `smtp.settings`; `composer remove drupal/smtp`; atualizar scripts e docs; fechar DT-O07 e, se confirmado, DT-P09 | Só depois da M1 validada no Homelab | M1 validada no Homelab |

Ordem de teste no Homelab (M1):

1. Ativar `symfony_mailer`, `mailer_transport`, `mailer_policy` e `mailer_override`; confirmar que não há
   erro de referência circular (issue citada na seção 2).
2. Exportar o transporte SMTP2GO e os dois `key.config_override` (seção 4.1) como `config/sync`, com `user` e `pass` vazios.
3. Enviar um e-mail de teste pelo Core (`drush php:eval` ou recuperação de senha) e confirmar a entrega
   no SMTP2GO.
4. Confirmar o remetente (seção 4.2).
5. Enviar um e-mail de formulário Webform e verificar o modelo HTML (seção 4.3).
6. Confirmar que `transactionalMailReady()` retorna verdadeiro somente com as Keys preenchidas.

## 6. Riscos

| Risco | Efeito | Mitigação |
|---|---|---|
| Senha fora de Key na configuração exportada | Vazamento em `config/sync` | Override em memória (4.1); gate que falha se `pass` não estiver vazio na configuração |
| Recursão ao carregar Key dentro de `loadOverrides()` | Falha ao iniciar o Drupal | Consultar Key só para o nome do transporte; teste de runtime |
| Issue "circular reference" do `mailer_override` no Core 11.4 | Site não sobe após ativar | Teste no Homelab antes de qualquer deploy; `drupal/smtp` mantido até M2 |
| "Sendmail still used when SMTP is default" | E-mail sai por sendmail, sem entrega | Verificar `default_transport` e a entrega real |
| Remetente trocado por ambiente | E-mail com endereço errado | Confirmar na M1; `deploy.json` cobre `system.site` |
| Webform sem HTML em modo de compatibilidade | DT-P09 não resolvida | Teste de formulário; DT-P09 fica aberta até a prova |
| Mensagens em fila antes do corte | Envio duplicado ou perdido | Ativação em janela sem campanha; sem fila pendente |
| Deploy antes do RC | Mudança de produção fora da política | Deploy só no RC (decisão do responsável, 2026-10-09): merge em `main` não publica |

## 7. Critério para fechar a migração

- M1 validada no Homelab, com os seis passos da seção 5 registrados como evidência.
- M2 executada somente depois da M1 validada.
- DT-O07 fechada (drift de `smtp.settings` e `system.mail` removido).
- DT-P05 fechada quando a entrega em produção for validada no RC.
- DT-P09 fechada apenas com a prova de envio de Webform com HTML.

## 8. Versão

Esta fase é documental. Nenhum marcador de versão é alterado. A versão entra na M1.

## 9. Evidência do teste no runtime (M1)

Executado em 2026-10-11 no runtime de desenvolvimento (`aculta_runtime`, Homelab), com autorização do responsável.

| Passo | Resultado |
|---|---|
| 1. Módulos `symfony_mailer`, `mailer_transport`, `mailer_policy`, `mailer_override` ativos, sem erro de referência circular | OK. Traduções corrigidas depois (ver configuração local abaixo): `locale:update` baixou 392 traduções para o diretório novo |
| 2. Transporte `smtp2go` e dois `key.config_override` exportados para `config/sync`, com `user` e `pass` vazios | OK (commit `d33a747`) |
| 3. E-mail de teste pelo Core (`system.test_mail`) com `mailer_override` como gerenciador | OK: envio aceito (`result=TRUE`), gerenciador `MailManagerReplacement`, transporte `smtp2go` |
| Credenciais injetadas pelo Key no transporte em memória | OK: usuário e senha presentes (apenas tamanhos verificados: 23 e 16 caracteres; valores não exibidos) |
| 4. Remetente | Não verificado no cabeçalho (a caixa confirmou o recebimento; conferir o campo De na próxima verificação) |
| 4. Recebimento na caixa de destino | OK: recebido em 4e20coletivo@gmail.com, confirmado pelo responsável |
| 5. Webform com modelo HTML (DT-P09) | Não testado |
| 6. `transactionalMailReady()` verdadeiro só com as Keys | Não verificado (método privado; coberto pelo código e pelo gate) |

Configuração local: durante o teste, `settings.local.php` (fora do Git) apontou `locale_translation_path` para `~/.local/share/aculta-runtime/translations`, porque o diretório padrão não era gravável. Depois de as permissões dos diretórios internos serem corrigidas pelo responsável, o override foi removido e o caminho padrão `web/sites/default/files/translations` voltou a valer (gravação verificada).

**Pendências antes do M2:** testar o Webform (DT-P09), validar `transactionalMailReady()` no runtime e conferir o remetente no cabeçalho. Só depois disso o M2 começa.

### 9.1 Atualização (M1, mesma data)

- Constante `MAIL_TRANSPORT` ausente em `PortalController` encontrada na validação de runtime e corrigida (commit `e65c714`). Antes da correção, `transactionalMailReady()` falharia na página de Segurança.
- `transactionalMailReady()` no runtime: `TRUE` (`mailer_override` ativo, transporte `smtp2go`, remetente configurado, Keys `smtp2go_username` e `smtp2go_password` presentes).
- Segundo envio de confirmação pelo Core (`system.test_mail`) para o endereço do responsável: aceito pelo `mailer_override` (`result=TRUE`, transporte `smtp2go`).
- Remetente configurado no runtime: `naoresponda@toca.net.br`. Confirmar o campo De no cabeçalho do e-mail recebido.
- **Pendente:** Webform com modelo HTML (DT-P09). O handler `institutional_notification` de `aculta_contact` está com `html` desligado e envia para `4e20coletivo@gmail.com`. O teste com HTML ligado (apenas em memória) foi bloqueado pelo classificador de ações e ainda não foi executado.


# Transporte de e-mail do Portal (migração para Mailer Plus)

Status: **M0/M1/M2 e R3 concluídas; R4 alinha o template administrativo e registra a política atual do Webform.** Documento normativo da arquitetura de transporte.

Documentos relacionados: [DEBT-REGISTER](../operations/DEBT-REGISTER.md) (DT-P05, DT-P09, DT-O07),
[EMAIL-CONFIRMATION-POLICY.md](EMAIL-CONFIRMATION-POLICY.md), [GUARDRAILS.md](GUARDRAILS.md),
[DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md), `config/secrets-contract.json`.

## 0. Estado atual do envio (2026-10-19)

Envio pelo Mailer Plus, com um único transporte ativo: **SMTP2GO** (`mail.smtp2go.com:465`), definido em
`config/sync/mailer_transport.mailer_transport.smtp2go.yml` e padrão em `config/sync/mailer_transport.settings.yml`.
O transporte da Hostinger foi removido do Runtime de teste. Não havia versão dele no Git.

Remetente: `naoresponda@aculta.org`, o mesmo em teste e em produção. Definido em `config/sync`, com o mesmo valor no
perfil `test` do `aculta_deployer`. Nenhum override de ambiente o altera.

**Incidente de 2026-10-18 a 2026-10-19 (resolvido).** Os envios pela Hostinger falhavam com
`553 5.7.1 : Sender address rejected: not owned by user naoresponda@aculta.org`. O remetente não pertencia à conta
SMTP usada. Com o padrão no SMTP2GO e o remetente da própria conta, o teste de envio é aceito no `MAIL FROM` e
no `AUTH`. A única recusa restante é o destinatário de teste `example.invalid` (domínio reservado).

**Pendência.** As credenciais do SMTP2GO não são mais Keys. Ficam no arquivo de segredos do servidor (`SMTP2GO_USERNAME`,
`SMTP2GO_PASSWORD`). No Runtime de teste, o transporte está sem credenciais: o envio não autentica até o responsável
gravar a credencial no Mailer Plus ou o provisionamento passar a injetá-la.
Ver a decisão em aberto sobre Keys nesta seção.

## 1. Decisão

Migrar o envio de e-mail do `drupal/smtp` (1.4, SMTP via `smtp.settings`) para **Mailer Plus**
(`drupal/symfony_mailer` 2.x), em modo override (`mailer_override`), mantendo o provedor SMTP2GO
e as mesmas credenciais.

Provedores de envio atuais: **Hostinger** e **SMTP2GO** (ver seção 0). A migração não trocou provedor; muda
quem fala com eles dentro do Drupal.

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
| `smtp.settings` | `config/sync/smtp.settings.yml`; `smtp_on: true`, host `mail.smtp2go.com`, porta 465 (ver seção 0), `smtp_password` vazio no arquivo | Configuração do envio |
| Credenciais | Arquivo de segredos do servidor (`SMTP2GO_USERNAME`, `SMTP2GO_PASSWORD`); sem Key | Credenciais, fora do Git |
| `system.mail` | `config/sync/system.mail.yml` (`interface.default: php_mail`, `webform: webform_php_mail`) | Backend do Core e do Webform |
| Secrets Contract | `config/secrets-contract.json` | Declara as variáveis SMTP2GO do arquivo de segredos |
| Deploy por ambiente | `aculta_deployer/config/deploy.json` (`environment_bound`, `smtp.settings`, `system.mail`, `system.site`, `webform.webform.aculta_contact`) | Remetente e backend por ambiente |
| Gate de Portal | `PortalController::transactionalMailReady()` (checa `mailer_override`, transporte padrão `smtp2go` e usuário e senha do transporte) | Libera troca de e-mail em "Segurança" |
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

Superado em 2026-10-19: não há mais Keys nem overrides de SMTP2GO. Mantido como registro da M1:

- `mailer_transport.mailer_transport.smtp2go`: plugin `smtp`, host `mail.smtp2go.com`, porta `465` (ver seção 0),
  `user` e `pass` vazios;
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
| Entrega em produção antecipada | Mudança em produção sem decisão do responsável | Entrega em produção é decisão do responsável no fim do projeto; merge em `main` não publica |

## 7. Critério para fechar a migração

- M1 validada no Homelab, com os seis passos da seção 5 registrados como evidência.
- M2 executada somente depois da M1 validada.
- DT-O07 fechada (drift de `smtp.settings` e `system.mail` removido).
- DT-P05 fica aberta até a decisão do responsável de entregar em produção, no fim do projeto.
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

### 9.2 Reenvio após correção de `system.mail`

- O teste de confirmação não chegou ao destino. Na análise, `system.mail:interface.default` estava como `SMTPMailSystem` (módulo `drupal/smtp`, legado). O envio passou pelo `mailer_override`, mas a configuração inconsistente precisava ser corrigida.
- Runtime: `system.mail:interface.default` alterado para `symfony_mailer` (plugin do Mailer Plus, disponível na lista de plugins de e-mail).
- Reenvio do teste de confirmação para o responsável: aceito pelo `mailer_override` (`result=TRUE`, transporte `smtp2go`).
- Alteração apenas no runtime. `config/sync/system.mail.yml` ainda declara `php_mail` e `deploy.json` ainda trata o backend por ambiente. Ajustar a configuração exportada faz parte da M2.
- Webform: `system.mail:interface.webform` continua `webform_php_mail`. O teste com HTML (DT-P09) segue pendente, e esse valor é o que a DT-P09 precisa considerar.

### 9.3 Cabeçalho verificado

- Cabeçalho do e-mail recebido: **Para** `advfjpm@gmail.com` e remetente `naoresponda@toca.net.br`. O destinatário é o pretendido; não houve redirecionamento.
- A conta de administrador do Drupal (usuário 1) usa outro endereço (`outlook.com`) e não recebeu o teste. A confusão foi de identificação, não de envio.
- Remetente confirmado. Falta apenas o teste do Webform com HTML (DT-P09).

### 9.4 Webform com HTML (DT-P09): verificação sem envio

- Montagem da mensagem do handler `institutional_notification` com HTML ligado, em memória, pelo `mailer_override` com `send = FALSE`: sem transmissão.
- O corpo gerado é HTML (`<b>`, `<br>`, `<a>`), com o modelo `webform-email-message-html` do Webform aplicado.
- **Não verificado:** o envoltório visual que o Mailer Plus aplica no modo de compatibilidade (`mailer_policy` com `email_theme`). Isso só aparece no e-mail recebido.
- O envio real do teste foi bloqueado pelo classificador de ações. Permanece pendente.
- DT-P09 **não fechada**.

### 9.5 Webform com HTML: envio executado pelo responsável

- Script `m1-webform-send.php` executado no terminal do responsável: `sendMessage=true`, com a notificação do formulário `aculta_contact` registrada ("Notificação institucional email").
- Destino: `advfjpm@gmail.com`, com HTML ligado (só em memória; o formulário não foi alterado).
- **Pendente:** confirmação visual do layout no e-mail recebido (envoltório do Mailer Plus). Enquanto não for confirmada, DT-P09 permanece aberta e a M2 bloqueada.

### 9.6 Fechamento da M1

- Webform com HTML recebido e confirmado pelo responsável: **DT-P09 resolvida**.
- Checklist da seção 5: passos 1, 2, 3 e 5 concluídos; passo 4 (remetente) confirmado no cabeçalho; passo 6 (`transactionalMailReady()`) verdadeiro no runtime.
- Correções feitas durante a validação: constante `MAIL_TRANSPORT` declarada (`e65c714`); `system.mail:interface.default` alterado para `symfony_mailer` (só no runtime).
- Pendências transferidas para a M2: remover `drupal/smtp`, `smtp.settings` e os overrides de senha antigos; exportar `system.mail` com `symfony_mailer`; fechar DT-O07. DT-P05 (entrega em produção) é decisão do responsável no fim do projeto.

### 9.7 M2: remoção do legado

Executada no mesmo PR da M1, a pedido do responsável (o `AGENTS.md` pede um PR por fase; registrado aqui como desvio).

- Runtime: módulo `smtp` desinstalado; overrides `smtp_password` e `smtp_username` removidos; `system.mail:interface.default` = `symfony_mailer`. Envio do Core ainda pronto (`transactionalMailReady()` TRUE).
- Composer: `drupal/smtp` e `phpmailer/phpmailer` removidos. Só permanece `require-dev` do `drupal/webform`, sem instalação.
- `config/sync`: removidos `smtp.settings.yml` e os dois `key.config_override.smtp_*`; `core.extension` sem `smtp`; `system.mail.yml` exportado (`symfony_mailer`).
- `deploy.json`: 10 entradas removidas (`environment_bound` e regras de perfil de `smtp.settings` e `system.mail`). Regras de `system.site` mantidas.
- Scripts, manifesto, card de requisitos e testes do deployer atualizados para `mailer_transport.*` e `symfony_mailer`.
- `aculta_portal_update_11004` mantido sem alteração: hook de atualização histórico. Em instalações antigas que rodarem esse update, ele recria `smtp.settings` sem efeito (o módulo não existe mais).
- Gate `validate-mail-transport.php` impede o retorno do `drupal/smtp` e do `smtp.settings`.

**Verificações da M2:**
- `validate-mail-transport.php`: PASS. `validate-aculta-portal-drupal11.php`: PASS (542 checks). `validate-secrets-contract.php`: PASS.
- Testes do deployer (`tests/run.php`): PASS.
- `validate-portal-commerce-security.php`: as asserções de e-mail passam; a execução para numa asserção do formulário de apoio (gateway de doação), sem relação com e-mail e sem alteração nesta branch. Falha anterior à M2.
- `validate-final-drupal.php`: as asserções de e-mail passam (verificadas diretamente). Para numa asserção do endereço do site (`4e20coletivo@gmail.com` esperado; runtime tem `naoresponda@toca.net.br`), anterior à M2. A divergência de 77 itens entre runtime e `config/sync` também é anterior.

**Pendências fora da M2:** DT-P05 (entrega em produção) é decisão do responsável no fim do projeto. Os itens acima anteriores à M2 ficam para o saneamento de dívidas.

### 9.8 Validação pós-remoção (antes do merge)

- `transactionalMailReady()` com o módulo `smtp` removido: TRUE (backend `symfony_mailer`, transporte `smtp2go`, módulo `smtp` inexistente).
- Reenvio do teste do Core para o endereço do responsável após a remoção: aceito pelo `mailer_override` (`result=TRUE`, transporte `smtp2go`). Recebimento a confirmar pelo responsável.
- **Pendente antes do merge:** número do commit de release da linha 0.2 (`-dev` removido). A regra exige a versão final antes do merge; a linha ainda não tem esse número definido. Ver `docs/operations/RELEASES.md`.

## 10. R3 — cadeia ativa e falha fechada

### Caminho efetivo

No Core 11.4.10 e Mailer Plus 2.0.2 instalados, `mailer_override` substitui o serviço `plugin.manager.mail` por
`MailManagerReplacement`. O mapa de overrides pode estar vazio: nesse caso, o módulo usa `LegacyMailer` como adaptador
dos `hook_mail()` antigos, e esse adaptador ainda envia pela API de componentes do Mailer Plus. O transporte efetivo
é `mailer_transport.settings:default_transport`, configurado como `smtp2go`. A configuração sincronizada mantém os
transportes sendmail disponíveis, mas eles não são o padrão nem entram como retry/failover; o `MultiTransport` seleciona
somente o ID solicitado e lança `MissingTransportException` quando ele não existe.

O campo `system.mail:mailer_dsn` pertence ao backend Symfony Mailer experimental do Core. Ele não escolhe o transporte
Mailer Plus enquanto `mailer_override` estiver ativo. Antes, seu valor `sendmail` era um caminho concorrente: se o
serviço de override deixasse de atuar, o Core poderia enviar pelo MTA local sem SMTP2GO. Agora usa o esquema reservado
`aculta-disabled`, que o factory do Core não reconhece. Assim, o uso acidental desse backend falha no envio e não troca
silenciosamente de provedor.

### TLS e autenticação

O SMTP2GO institucional usa `mail.smtp2go.com:465`. A porta 465 inicia TLS implícito no Symfony Mailer 7.4 instalado;
`verify_peer` permanece `true` no plugin Drupal e o transporte mantém verificação de certificado e hostname. Symfony
Mailer não permite que essa conexão prossiga sem a camada TLS que a porta exige. O usuário e a senha são injetados
pelas Keys com provider `env`; no Config Sync, `configuration.user` e `configuration.pass` continuam vazios.

SMTP2GO documenta autenticação SMTP como obrigatória por padrão. Sem credenciais, não existe transporte alternativo:
o envio deve falhar na autenticação do provedor e o MailManager registra falha. Nenhum segredo é impresso por gates ou
relatórios. O transporte não tem uma lista de failover configurada.

### Evidência da R3

- Código instalado confirmou `MailManagerReplacement`, `mailer_override` ativo, mapa de overrides vazio e transporte
  padrão `smtp2go`. A inspeção verificou host, porta, peer verification e presença das Keys resolvidas sem ler ou exibir
  seus valores.
- Uma conexão TLS sem autenticação ou mensagem para `mail.smtp2go.com:465` negociou TLS 1.3 e validou o certificado;
  `2525` também anunciou STARTTLS. Nenhum e-mail foi enviado.
- O gate `scripts/validate-mail-transport.php` constrói o transporte com credenciais sintéticas e verifica a porta TLS,
  a verificação de certificado, as Keys/overrides e a rejeição do esquema `aculta-disabled` pelo factory Core.
- O transporte e o sentinel do Core são configuração compartilhada por ambiente. Homelab foi validado; Hostinger não
  foi acessada nem testada. A compatibilidade de saída TCP 465 na hospedagem de produção permanece DEFERRED até a
  homologação no ambiente autorizado.

## 11. R4 — Webform e template administrativo

O handler `institutional_notification` do formulário `aculta_contact` continua configurado com `html: false` e
`twig: false`. Ele envia os valores da submissão no corpo em texto simples e usa o campo de e-mail validado como
`Reply-To`; o remetente é institucional e seu endereço é substituído pelo `aculta_deployer` conforme o ambiente.
O destinatário exportado é o endereço institucional publicado na página de contato, não o destinatário controlado
usado no teste histórico.

O teste registrado na seção 9.5 ligou HTML temporariamente em memória e foi recebido; ele confirma que o caminho
Mailer Plus/Webform consegue enviar HTML. Não prova que o handler atual esteja em HTML. Mantemos texto simples porque
`filter.format.email_html` não declara filtros e o corpo contém campos fornecidos por visitantes. Habilitar HTML exige
um template que escape esses campos e validação específica do conteúdo final.

O formulário de participação `aculta_participation` foi removido; por isso R4 não tem mais envios para ele. O template de verificação administrativa do Mailer
Plus foi localizado em português; a política está disponível apenas para a verificação iniciada por administrador.

**Runtime status: DEFERRED.** R4 não importou configuração no Runtime: a linha de base tem divergências conhecidas,
e a atualização completa não é segura nesta fase. Nenhum e-mail foi enviado nesta fase.

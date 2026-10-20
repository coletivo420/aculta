# Monitoramento por IMAP

Complemento e fallback ao transporte de envio. Lê a caixa de relatórios e de retorno do domínio para duas coisas:

- **Bounces** (notificações de falha de entrega, RFC 3464): aplicados ao registro do envio pelo `DeliveryEventApplier`
  (mesmas transições do webhook). Código 5.x.x marca `bounced`; 4.x.x marca `deferred`. Só é aplicado quando a
  mensagem traz o cabeçalho `X-Aculta-Mail-ID` (correlation ID do envio).
- **Relatórios DMARC** (anexo XML, gzip ou zip): guardados como resumo (organização, domínio, período, total,
  aprovadas, reprovadas). Os IPs de origem não são guardados.

## Cadência

| Leitura | Padrão | Mínimo | Motivo |
|---|---|---|---|
| Bounces | 30 minutos | 15 minutos | Falha de entrega precisa aparecer antes que o envio se acumule |
| DMARC | 12 horas | 15 minutos | Relatórios chegam uma vez por dia por provedor |

Cada leitura tem seu cursor por UID (estado do Drupal). Ao processar uma mensagem, o cursor avança; se a execução parar
no meio, a próxima retoma de onde parou e não repete o que já foi aplicado (o `DeliveryEventApplier` também recusa
transições inválidas, então uma repetição não corrompe o estado).

## Configuração

Painel: `/painel-administrativo/configuracoes/aculta/envios-de-email/monitoramento` (permissão `administer aculta_mailer`).

- Servidor, porta, e-mail (usuário IMAP) e pasta: gravados na configuração `aculta_mailer.imap`, sem senha.
- Senha: campo do próprio formulário, gravado em `IMAP_PASSWORD` no arquivo de segredos do servidor
  (`secrets/aculta.secrets.env`). Não vai ao banco nem à configuração. Exige a permissão `administer aculta secrets`;
  sem ela, o campo fica desabilitado. Campo vazio mantém a senha atual.
- "Testar conexão": usa os dados digitados e conta as mensagens da pasta. Não lê corpo, não altera cursor e não grava.

O monitoramento começa desligado. Só roda com a opção ativa e servidor, usuário e senha preenchidos.

## Caixa de retorno (ponto que depende do envio)

Runtime (2026-10-20): `dmarc@aculta.org` é alias de `naoresponda@aculta.org` no Hostinger. O envelope `dmarc@` foi aceito
pelo SMTP (login `naoresponda@`). Por ser alias, as mensagens chegam na caixa de `naoresponda@`: o IMAP deve ler essa caixa,
com o usuário e a senha dela, e não `dmarc@`. Conferir no cabeçalho original da mensagem: `Return-Path: <dmarc@aculta.org>`.


Notificações de falha voltam ao endereço de **retorno (Return-Path / envelope from)** da mensagem, que é definido pelo
transporte, e não pelo cabeçalho From. Para o bounce chegar à caixa lida aqui, o envelope de cada transporte precisa
apontar para um endereço dessa caixa. Enquanto isso não estiver configurado, a leitura de bounces roda, mas não recebe
nada. Decisão pendente do responsável: o endereço de retorno do domínio.

## Segurança e privacidade

- Erros de leitura são guardados em estado e no log sem credenciais; o cliente usa autenticação do curl (usuário e
  senha separados da URL).
- Cliente IMAP: `CurlImapClient` sobre a extensão curl do PHP (protocolo `imaps`, TLS implícito com verificação de
  certificado). Não usa biblioteca nova: a extensão `imap` do PHP não está disponível neste servidor.
- A tela de relatórios DMARC mostra organização e domínio. Não mostra IPs de origem nem corpo das mensagens.

## Limites conhecidos

- A leitura não sinaliza quota do provedor: uma cota estourada aparece como falha em bounce, e a troca de transporte
  continua dependendo de `TransportQuota` (configuração `aculta_mailer.policy:quota`). Integrar o sinal de quota ao
  fallback depende do texto real do provedor, ainda não observado.
- Os resumos DMARC ficam no estado do Drupal (até 30 relatórios). Histórico longo exige entidade própria.
- Primeira execução com a caixa cheia processa até 200 mensagens por leitura; as demais saem nas execuções seguintes.

## Estado de validação

- Parsers e cliente: unitários (`MonitorParsersTest`, `MailMonitorTest`), sem caixa real.
- Leitura real da caixa: **DEFERRED** até a senha e os dados serem informados no formulário e o "Testar conexão"
  retornar a contagem. Não há evidência de leitura real nesta versão.

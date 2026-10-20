# Changelog — ACULTA Mailer

## 0.1.0-dev.32 — logo do envelope vem só do tema, sem cópia por marca — 2026-10-20

- Removido o logo por marca: campo `logo_fid` da entidade e do schema, `managed_file` do formulário de marca, `logoUrl()`/`logoUri()` do renderizador e os parâmetros `brand_logo_*` do hook de e-mail. O envelope usa o logo do tema (`aculta420.settings`, `logo.path`), que já é servido pelo site.
- Motivo: a cópia enviada em `public://aculta-mailer/marcas` duplicava o logo do tema, e a limpeza de teste que a removeu deixou o logo quebrado no e-mail recebido.
- Teste `MailBrandLogoKernelTest` removido com o recurso.
- Não altera o campo `logo_fid` de marcas já gravadas no Runtime: a chave fica ignorada no config até uma limpeza de dados, pendente de decisão.

## 0.1.0-dev.31 — fases F3 a F8 e F13 fechadas (exceto decisões e escopo de outros módulos) — 2026-10-20

- F3: modelos, marcas e layouts ganham criação pelo painel (formulário "Adicionar" e lista com botão); antes só existiam por linha de comando.
- F4: logo da marca enviado pela política do `aculta_image` (`managed_file` com `UploadPolicy`, uso do arquivo registrado); logo embutido na mensagem (cid:) com fallback de URL absoluta do host do ambiente; fallback no logo do tema. Conferido na caixa de entrada do destinatário.
- F5: tela de política (`/politicas`) para padrão global, sobrescrita por tipo e limites por transporte; Brevo aceito como transporte de política.
- F6: cota tratada como temporária para o failover; classificação SMTP pelo código.
- F8: contagens do painel agregadas no banco; rótulos em português. Gráficos ficam fora deste RC (a confirmar).
- F13: system.mail na camada; nenhum envio do projeto contorna o plugin.
- Gate de fronteira: classes de design removidas do módulo (atributo `data-portal-ui`).
- Testes: 108 unitários; Kernel com 36 testes (inclui política, logo da marca e painel).
- Abertas: F7 (decisão sobre reenvio), F9 (textos do contrib), F10 (provedor e supressão), F11 (consentimento), F12 (módulos de outros agentes).
- Versão do módulo: `0.1.0-dev.31`.

## 0.1.0-dev.30 — cron único, painel validado visualmente, F14 e F15 em andamento — 2026-10-20

- **Não está em RC.** O RC do submódulo só vale com todas as fases concluídas, revisadas e mescladas, mais validação de código, visual e revisão final (regra em `AGENTS.md`). Este registro não declara RC.
- Correção de runtime: havia três implementações de `hook_cron` no módulo. O Core recusa mais de uma (LogicException no Kernel). Agora há uma só (`MailCronHooks`), que chama retenção (`MailLogRetention`), monitoramento IMAP e resumo semanal.
- Painel validado no X11 (Firefox, perfil de teste): visão geral, falhas, diagnóstico, configurações, entrega, registro e monitoramento. Ajustes feitos: estados com rótulos em português; tabelas com rolagem horizontal dentro da área (`css/admin.css`); Brevo mostrado como API (sem servidor, porta e certificado); nome de produto de terceiros removido dos títulos.
- Config de ambiente: `config/sync` dos transportes, do IMAP e da entrega declarados em `environment_bound` (endereços de domínio).
- Validações: 108 unitários; 33 Kernel; gates de envio, segredos, rotas, papéis, fronteira e endereços passam.
- Pendente (bloqueia o RC, pois as fases ainda não estão concluídas): tipo de falha com nome técnico na tabela; alta densidade das linhas da tabela de falhas; reenvio de falha (F7); verificação de domínio SPF/DKIM/DMARC (F3); tracking (F11, desligado); integrações com Forum, LMS, Magazine e Commerce (F12).
- Versão do módulo: `0.1.0-dev.30`.

## 0.1.0-dev.29 — transporte esgotado é ignorado, inclusive a contingência; gate de comércio corrigido — 2026-10-20

- `TransportChoice`: um transporte sem cota (ou Brevo desligado) é ignorado; a contingência também só entra se estiver disponível. Sem transporte disponível, a falha segue com o erro real.
- SMTP2GO sem cota mensal deixa de ser escolhido enquanto o sinal de cota valer.
- Gate de comércio: a rota de eventos de entrega (`aculta_mailer.webhook`, F10) não é tratada como rota legada de contribuição; a rota sensível de transporte passa a ser `aculta_mailer.settings`.
- Gate de comércio ainda reprova por dados: 46 pedidos existem no runtime (2 concluídos do usuário de teste, 42 rascunhos anônimos, 2 da conta uid 1). Remoção depende de autorização.
- Versão do módulo: `0.1.0-dev.29`. 108 unitários.

## 0.1.0-dev.28 — F15 concluída: contrib de envio removido — 2026-10-20

- Desinstalados no runtime: symfony_mailer, mailer_override, mailer_transport, mailer_policy. Snapshot da configuração do contrib guardado fora do repositório.
- Pacote removido do composer.json e do composer.lock (4 pacotes: drupal/symfony_mailer, drupal/mailer_transport, html2text/html2text, tijsverkoyen/css-to-inline-styles). Nenhum pacote atualizado.
- Configuração versionada: arquivos mailer_* removidos; `core.extension` sem o contrib; `system.mail` = aculta_mailer; configurações do projeto exportadas (delivery, imap sem senha, log, policy).
- Código: o conversor ignora o cabeçalho Sender copiado como texto (causava erro no Symfony depois da remoção).
- Gate `validate-mail-transport` passa. Gate de comércio já reprovava antes (check de rotas de webhook), não relacionado.
- Versão do módulo: `0.1.0-dev.28`. 104 unitários.

## 0.1.0-dev.27 — F15: `system.mail` trocado para a camada de envio; gates e controlador atualizados — 2026-10-20

- `system.mail` do runtime e da configuração versionada: `aculta_mailer`. Envio do Core e da aplicação aceito no runtime.
- Código SMTP do envio vai para o registro de falhas (classificação e relatório), como no envio anterior.
- `Reply-to` do Core lido de `reply-to`; `smtp2go` mapeado no X-Transport.
- Diagnóstico e prontidão de e-mail do Portal leem `aculta_mailer_transport` e a senha no arquivo de segredos.
- Gates `validate-mail-transport`, `validate-final-drupal`, `validate-portal-commerce-security` e o teste do deployer atualizados para o estado final.
- Pendente: desinstalar o contrib de envio no runtime e retirar pacote e configuração. Bloqueado pelo classificador de ações; o estado do repositório fica com o contrib até lá, e os gates de remoção reprovam até a conclusão.
- Versão do módulo: `0.1.0-dev.27`.

## 0.1.0-dev.26 — F15: credenciais no arquivo de segredos e transporte SMTP2GO na camada E-Mails ACULTA — 2026-10-20

- Entidade `aculta_mailer_transport` aceita usuário pelo arquivo de segredos (`user_secret`), além da senha (`password_secret`).
- Transporte `smtp2go` criado com `SMTP2GO_USERNAME` e `SMTP2GO_PASSWORD` (já no arquivo de segredos). Configuração exportada em `config/sync`.
- Senha do Hostinger no banco do contrib confere com `SMTP_PASSWORD` (comparação por hash, sem exibir valores).
- Cota mensal do SMTP2GO (`552 Your monthly email allowance is exhausted`) reconhecida pelo detector de cota e tratada como temporária, para o failover trocar de transporte.
- Teste pelo SMTP2GO: credencial aceita; envio recusado por cota do plano. Diagnóstico recebido, não é falha de configuração.
- Versão do módulo: `0.1.0-dev.26`. 103 unitários.
- Não removido: credenciais no banco do contrib (saem junto com a remoção do contrib).

## 0.1.0-dev.25 — F15: envelope HTML e anexos no plugin de envio do Core — 2026-10-20

- Hook de tema `aculta_mailer_wrap` (`MailThemeHooks`) com template neutro no módulo. O tema passa a ter `aculta-mailer-wrap.html.twig` (antes `email-wrap.html.twig`, que dependia do hook do contrib).
- Plugin `aculta_mailer`: envia parte HTML pelo hook, escapa o texto e troca quebras por `<br>`. Anexos do Core (conteúdo ou caminho local) entram como anexos.
- Testes: 101 unitários (HTML e anexo no conversor).
- Runtime: envio de teste com HTML e anexo aceito (`sent=1`). Conferência visual pelo destinatário pendente.
- `system.mail` não foi alterado. Remoção do contrib depende de decisão do responsável.
- Versão do módulo: `0.1.0-dev.25`.

## 0.1.0-dev.24 — F15: plugin de envio do Core pela camada E-Mails ACULTA (sem trocar o padrão) — 2026-10-20

- Plugin `aculta_mailer` (`AcultaMailerMail`): `format()` igual ao padrão do Core; `mail()` converte a mensagem (`LegacyMessageConverter`) e envia pelo transporte `aculta_mailer_transport`.
- Transporte escolhido pelo `X-Transport` (`smtp` → `smtp_hostinger`, `brevo` → `brevo`); padrão `smtp_hostinger`. Falha registra o erro sem corpo nem destinatário.
- `system.mail` não foi alterado: o site continua no contrib.
- Runtime: plugin descoberto; envio de teste aceito (`sent=1`) para o destinatário autorizado.
- Versão do módulo: `0.1.0-dev.24`. 100 unitários.

## 0.1.0-dev.23 — F14: entidade e fábrica de transporte (`aculta_mailer_transport`), coexistindo com o contrib — 2026-10-20

- Entidade de configuração `aculta_mailer_transport` (`MailTransport`): tipo (`smtp` ou `brevo`), servidor, porta, usuário e nome da credencial da senha. Não guarda senha.
- `MailTransportFactory`: SMTP autenticado (porta 465 com TLS implícito, verificação de certificado) e Brevo pela fábrica existente. Sem credencial, falha fechado.
- `MailTransportSender`: envia pelo transporte da entidade e devolve aceito ou mensagem de erro, sem credenciais.
- Credencial `SMTP_PASSWORD` no contrato, no gate e na lista do carregador de segredos.
- Nada foi removido: o envio do site segue pelo contrib até a F15.
- Pendente no runtime: as entidades não foram criadas (cache bloqueado pelo `LmsForumModerationController`, do fórum) e a linha `SMTP_PASSWORD` no arquivo de segredos depende de autorização.
- Versão do módulo: `0.1.0-dev.23`. 97 unitários (inclui a fábrica).

## 0.1.0-dev.22 — nomes dos plugins de ajuste e das classes passam a AcultaMailer — 2026-10-20

- Plugins de ajuste: `AcultaMailAdjuster` → `AcultaMailerAdjuster` (atributo, interface, gerenciador, namespace `Plugin\AcultaMailerAdjuster`). Serviço `plugin.manager.aculta_mailer_adjuster`. Ids dos plugins (`skip`, `transport`, `reply_to`, `priority`) não mudam.
- Sem mudança de banco: a entidade `aculta_mail_log` e as chaves `aculta_mail_redirect` (settings.local.php) não foram renomeadas. Renomear a entidade mexe na tabela de produção; as chaves de redirecionamento de teste ficam fora do repositório e um erro ali pode enviar e-mail real. Decisão do responsável.
- Verificação: 93 unitários e gates passam. Não foi possível reconstruir o cache do runtime: `LmsForumModerationController` (não rastreado, do módulo `aculta_forum_lms`) quebra o container. Verificação em runtime pendente.
- Versão do módulo: `0.1.0-dev.22`.

## 0.1.0-dev.21 — endereço de retorno no envelope (corrigido) e limite do Brevo confirmado pela API — 2026-10-20

- Causa do endereço de retorno que não funcionava: o Drupal define o remetente do site depois do cabeçalho `Sender` do módulo, e o sobrescreve. Correção: `ReturnPathSubscriber` troca o envelope no evento de envio do Symfony, só para SMTP. O cabeçalho do módulo foi removido.
- Teste real: o Hostinger recusou `dmarc@aculta.org` como envelope (`553 5.7.1 Sender address rejected: not owned by user naoresponda@aculta.org`). O SMTP autentica como `naoresponda@`, e o servidor só aceita envelope de endereço da própria conta. Por isso o endereço de retorno está **vazio** no runtime, e os envios SMTP voltaram a ser aceitos. Para usar `dmarc@`, é preciso trocar o login SMTP para `dmarc@` ou criar encaminhamento de `naoresponda@` para `dmarc@`. Decisão do responsável.
- Limite do Brevo confirmado pela API da conta (`GET /v3/account`): plano gratuito, `sendLimit` de 300. Configurado: 300 por dia. A API não informa a janela; a janela diária é a do plano gratuito.
- Versão do módulo: `0.1.0-dev.21`. 93 unitários (inclui o assinante do envelope).

## 0.1.0-dev.20 — limites por transporte e detecção de cota (modelo WP Mail SMTP) — 2026-10-20

- Limites por transporte em janelas de hora, dia, semana e mês (`aculta_mailer.policy:rate_limit`). `quota.<transporte>` continua como limite diário.
- Detecção de cota pelo texto do erro de envio e do bounce (`QuotaSignatures`, `QuotaSignal`). Cota da caixa do destinatário não conta.
- Transporte esgotado por sinal sai de uso por 24 horas e gera alerta ao administrador.
- Correção: mensagens sem transporte escolhido eram gravadas como `default`, e não como o transporte real. Agora gravam o efetivo, e a contagem de cota do SMTP passa a incluí-las.
- Runtime: limite do Brevo de 200 por dia (a confirmar).
- Versão do módulo: `0.1.0-dev.20`. 89 unitários.
- DEFERRED: textos de cota reais dos provedores (Brevo, Hostinger, SMTP2GO) ainda não observados.

## 0.1.0-dev.19 — ligar e desligar monitoramento IMAP e Brevo; alertas e resumo para administradores — 2026-10-20

- Página Entrega, alertas e resumo: interruptores "Transporte Brevo ligado" e "Monitoramento IMAP ligado". Desligado o Brevo, mensagens pedidas a ele seguem pelo transporte de contingência. O IMAP só liga com servidor, usuário e senha preenchidos.
- O interruptor do IMAP saiu do formulário de monitoramento (fica só na página Entrega, para não haver dois lugares).
- Alertas de falha e resumo semanal podem ir a todos os administradores do site (papel `administrator`, conta ativa), além da lista manual (`AdministratorRecipients`).
- Runtime de teste: alertas e resumo para administradores ligados; Brevo ligado; IMAP desligado; retenção do registro em 90 dias.
- Versão do módulo: `0.1.0-dev.19`. 79 unitários. Sem teste para o desvio do Brevo: coberto só pela leitura do código e pelo gate (DEFERRED).

## 0.1.0-dev.18 — registro de e-mail e mapa do modelo WP Mail SMTP — 2026-10-20

- Página Registro (`.../envios-de-email/registro`): ligar o registro (desligado, nada novo é gravado e a cota diária deixa de valer) e prazo de retenção (30 dias, 3 meses, 6 meses, 1 ano; padrão 90 dias, o comportamento anterior).
- Opções do WP Mail SMTP que não se aplicam por política aparecem com a razão: conteúdo, anexos, rastreamento (F11) e webhooks (F10).
- Mapa completo do modelo em `docs/REPORTS-AND-ALERTS.md`; fase F16 no roadmap.
- Versão do módulo: `0.1.0-dev.18`. Testes: `MailLogSettingsTest` (79 unitários no módulo).
- Runtime: endereço de retorno `dmarc@aculta.org` gravado; envio SMTP de teste aceito. Cabeçalho `Return-Path` ainda não conferido na caixa recebida (DEFERRED).

## 0.1.0-dev.17 — endereço de retorno, alertas de falha e resumo semanal (modelo WP Mail SMTP) — 2026-10-20

- Endereço de retorno (`bounce_address`, página Entrega): vai no envelope (cabeçalho `Sender`) dos transportes SMTP. Transportes por API (Brevo) ficam de fora (`DeliverySettings::API_TRANSPORTS`), porque lá o envelope vira o remetente visível.
- Alertas de falha de envio e de endereço que devolveu a mensagem (`MailAlerts`): um alerta a cada 5 minutos, o restante contado; enfileirados; não geram alerta sobre alerta.
- Resumo semanal (`MailSummary`, cron): 7 dias comparados com os 7 anteriores e motivos de falha. Tipos `aculta_admin_alert` e `aculta_weekly_summary`.
- Página `/painel-administrativo/configuracoes/aculta/envios-de-email/entrega`.
- Versão do módulo: `0.1.0-dev.17`.
- Testes unitários: `MailAlertsTest` (janela, destinatários, laço) e demais; módulo com 72 unitários.
- DEFERRED: cabeçalho `Sender` e envelope não verificados em envio real; alertas e resumo desligados até informar destinatários.

## 0.1.0-dev.16 — monitoramento por IMAP (bounces e DMARC) com formulário e senha no servidor — 2026-10-20

- Monitor (`Monitor/MailMonitor`): bounces aplicados aos envios pelo `DeliveryEventApplier` (5.x → bounced, 4.x → deferred, só com o correlation ID do envio); relatórios DMARC guardados como resumo, sem IPs de origem.
- Cadência: bounces a cada 30 minutos, DMARC a cada 12 horas (intervalos configuráveis, piso de 15 minutos). Cursor por UID em estado, com avanço por mensagem.
- Cliente `CurlImapClient` (protocolo `imaps`, curl do PHP, sem biblioteca nova). A extensão `imap` do PHP não está disponível no servidor.
- Formulário `/painel-administrativo/configuracoes/aculta/envios-de-email/monitoramento`: servidor, porta, e-mail (usuário), pasta, intervalos, senha e "Testar conexão" (dados digitados, sem gravar nada).
- Senha gravada em `IMAP_PASSWORD` no arquivo de segredos, pelo gerenciador do aculta_portal (`SecretWriterAdapter`, interface `SecretWriterInterface`). Não vai ao banco nem à configuração. Exige `administer aculta secrets`.
- Hook de cron (`MailMonitorHooks`). Monitoramento desligado por padrão.
- Correção de versão: o `info.yml` estava em `0.1.0-dev.13` enquanto o CHANGELOG já listava até `dev.15`. Agora em `dev.16`.
- Testes: `MailMonitorTest` (5 unitários: mapeamento de código, bounce sem correlation, mensagem comum, cursor do bounce, DMARC que não é relatório). Módulo com 67 unitários.
- Limites e DEFERRED: leitura real da caixa não validada (aguarda o teste de conexão com a senha no servidor). Caixa de retorno (Return-Path) dos transportes ainda não definida: ver `docs/MONITORING.md`.

## 0.1.0-dev.15 — transporte Brevo ativo na configuração e planejamento das telas de controle — 2026-10-20

- Biblioteca `symfony/http-client` 7.4 instalada (autorizada). Testes da fábrica do Brevo passam sem pular.
- Transporte `brevo` criado na configuração (plugin `dsn`, sem segredo). Usado apenas quando escolhido.
- Envio real pela API do Brevo para o destinatário autorizado: aceito, transporte `brevo` no registro.
- Planejamento das telas de controle dos e-mails (inspirado no Ultimate Member): `docs/CONTROL-SCREENS.md`.

## 0.1.0-dev.14 — transporte Brevo (fábrica) e cota diária por transporte — 2026-10-20

- `BrevoSecretTransportFactory` (esquema `aculta-brevo://default`): chave de `BREVO_API_KEY` pela interface neutra `SecretProviderInterface`. Opcional: sem o aculta_portal, não cria o transporte.
- `TransportQuota`: cota diária por transporte (`aculta_mailer.policy:quota`), contada no registro de envios. Ao atingir o limite, o envio segue pelo transporte de contingência.
- Testes: cota (Kernel); fábrica (unitário, com teste de criação pulado até autorizar `symfony/http-client`).
- Pendente: `symfony/http-client` e a criação do transporte Brevo na configuração.

## 0.1.0-dev.13 — configurações de envio e credencial do Brevo — 2026-10-20

- Biblioteca `symfony/brevo-mailer` 7.4 instalada (ponte do Symfony Mailer para a API do Brevo). Transporte Brevo ainda não ativado.
- Página `/painel-administrativo/configuracoes/aculta/envios-de-email/configuracoes`: estado da credencial `BREVO_API_KEY` (sem valor) e link para alterá-la no painel de credenciais do ambiente, para quem tem a permissão de segredos.
- Testes: página (Kernel) e leitura da credencial.

## 0.1.0-dev.12 — eventos de entrega do provedor (F10, camada neutra) — 2026-10-20

- Contrato `ProviderWebhookInterface` (tag `aculta_mailer.webhook_provider`), com verificação de assinatura antes de qualquer leitura.
- Endpoint `POST /aculta-mailer/webhook/{provider}`: 404 sem provedor, 403 sem assinatura válida, 400 com corpo malformado.
- Aplicação de eventos (`delivered`, `deferred`, `bounced`, `complained`) pelas transições permitidas; campo `provider_message_id` (update 10003, aplicado no Runtime).
- Nenhum provedor ativo: a escolha e a chave de assinatura são pendentes do responsável.
- Testes: 86 no módulo (Kernel do webhook com provedor de teste, unitários do mapeamento de eventos).

## 0.1.0-dev.11 — política de envio em plugins (F5) — 2026-10-20

- Hierarquia global → tipo (`aculta_mailer.policy`), com validação por ajuste. Valor inválido não apaga o da camada anterior.
- Ajustes em plugins: `transport`, `reply_to`, `priority` e `skip`. A suspensão registra `cancelled` e não reagenda.
- Testes Kernel: hierarquia e validação, contratos dos ajustes, suspensão e remetente/prioridade no envio. 80 testes no módulo.
- Correção encontrada pelo teste: a fusão de camadas apagava o valor global antes da validação. Corrigida: aplicação em sequência.
- Runtime: sem política configurada, a resolução é vazia e o envio segue como antes.

## 0.1.0-dev.10 — envelope HTML com identidade ACULTA (F4) — 2026-10-20

- Os e-mails saem com o envelope HTML do tema (`aculta420` 0.6.0-dev.117): logo, cores da marca e rodapé. O texto dos layouts continua como dados.
- Runtime de teste: dois envios reais aceitos para o destinatário autorizado. Conferência visual pelo destinatário pendente.

## 0.1.0-dev.9 — diagnóstico de envio e F2 concluída — 2026-10-20

- `MailDiagnostics` e página `.../envios-de-email/diagnostico`: padrão e contingência, transportes (servidor, porta, verificação de certificado), se usuário e senha estão preenchidos (sem valor), último envio aceito e última falha por transporte, fila e cron.
- Entrega real confirmada pelo destinatário autorizado (e-mail recebido com o assunto do modelo).
- Testes: 76 no módulo (inclui diagnóstico).

## 0.1.0-dev.8 — envio de teste de modelo (F3) — 2026-10-20

- Editor de modelo com link "Enviar e-mail de teste": formulário com destinatário, valores de exemplo do tipo e o caminho real de envio (`deliver`). Ao administrador aparece só o resultado; o motivo completo fica no relatório de falhas.
- Teste Kernel do formulário (endereço inválido recusado; envio com valores de exemplo). 74 testes no módulo.
- Runtime: envio real para o destinatário autorizado (4e20coletivo@gmail.com), modelo `registration_existing_mail` com texto padrão. Aceito pelo sistema de e-mail (`sent`, transporte padrão). Entrega na caixa de entrada depende de conferência do destinatário.

## 0.1.0-dev.7 — modelos, marca, layout e relatório completo de falhas (F3/F4, F7) — 2026-10-20

- F3: tipos declaram variáveis (`variables()`) e texto padrão com `{{ variavel }}`. Config Entity `aculta_mailer_template` com editor, validação (variável desconhecida recusada) e prévia com valores de exemplo. Renderização usa o modelo ativo; sem modelo, o texto padrão.
- F4: Config Entities `aculta_mailer_brand` (nome e rodapé) e `aculta_mailer_layout` (cabeçalho e rodapé com `{{ content }}`). O modelo escolhe um layout; o corpo entra como valor, sem reprocessamento. Texto simples; HTML com estilos do tema fica para a etapa seguinte.
- Relatório: `aculta_mail_log` guarda o texto completo da falha (`error_message`, update 10002, aplicado no Runtime). Página `/painel-administrativo/configuracoes/aculta/envios-de-email/falhas`, restrita a `administer aculta_mailer`. O usuário continua vendo a mensagem genérica.
- Painel: nova tabela "Motivo da falha" por código (`smtp_554` etc.).
- Runtime: teste real para example.invalid pelo `smtp`: 554 registrado com o texto completo do servidor; registro de teste removido.
- Correção: entidades de configuração sem `route_provider` impediam o cache (config_translation); declarado nas três.
- Testes: 73 do módulo, mais cadastro, Game e Messenger.

## 0.1.0-dev.6 — transportes, código SMTP e failover (F2) — 2026-10-20

- `AcultaMailer::deliver()` devolve `SendResult` (aceito, ou falha com classe e código SMTP). `send()` continua `bool`.
- Código SMTP real: subscriber de `FailedMessageEvent` guarda o código por correlação; `SmtpReplyClassifier` decide permanente (5xx) ou temporário (4xx e sem código).
- Transporte por mensagem pelo cabeçalho `X-Transport`; padrão `smtp` (Hostinger), fallback `smtp2go`.
- O worker usa o resultado: erro permanente encerra; temporário reagenda e troca de transporte.
- Registro do envio grava o código (`smtp_554`, `smtp_550`), sem texto livre do provedor.
- Testes Kernel de ponta a ponta com um transporte de teste que dispara a falha (550, 421 e failover pela fila).
- Runtime: mensagens reais para `example.invalid` pelos dois transportes (554 e 550, ambos permanentes). Registros de teste removidos.
- `docs/TRANSPORTS.md`. Pendente: diagnóstico de SMTP.

## 0.1.0-dev.5 — painel de envios (F8) — 2026-10-20

- Painel `/painel-administrativo/configuracoes/aculta/envios-de-email` (permissão `administer aculta_mailer`): contagens de 1, 7 e 30 dias por estado, tipo e transporte.
- Só contagens: sem destinatário, sem conteúdo. Sem gráfico: tabelas; gráfico e agregação no banco ficam para depois.
- Limite: o painel carrega os registros do período para contar; em volume alto, trocar por agregação no banco.
- Mapa de rotas atualizado (`docs/architecture/ROUTES-SITEMAP.md`). Runtime de teste: rota e página renderizam.

## 0.1.0-dev.4 — registro de envios e retenção (F7) — 2026-10-20

- Entidade `aculta_mail_log` (uma por correlation ID): estado, tentativas, transporte, código de erro e destinatário como HMAC. Sem corpo, sem token e sem endereço em claro (`docs/LOGGING-PRIVACY.md`).
- `MailLogRecorder` grava o estado no envio direto e nas transições do worker.
- Permissão `administer aculta_mailer`; controle de acesso somente leitura.
- Retenção de 90 dias pelo cron, em lotes de 500.
- `aculta_mailer_update_10001` instala a tabela. Runtime de teste: aplicado com `drush updb -y`, sem outras pendências.

## 0.1.0-dev.3 — fila de envio com retry — 2026-10-20

- `MailQueue::enqueue()` coloca a mensagem na fila `aculta_mailer_send` (guarda destinatário e contexto, nunca o corpo).
- `MailSendWorker` envia pela API; falha reagenda pelo `DeliveryPlanner` com o atraso de `RetrySchedule`; tipo não registrado é descartado; tentativa com horário futuro volta para a fila sem envio.
- `MailMessage::fromArray()` e `toArray()`, validados de novo ao sair da fila.
- Limite conhecido: o envio ainda não informa o código SMTP, então toda falha é tratada como temporária; o fallback por transporte chega na F2.
- Testes: 56 unitários e Kernel; Kernel da fila com 3 testes (envio, descarte, horário futuro).

## 0.1.0-dev.2 — API de envio e registro de tipos — 2026-10-20

- `AcultaMailerInterface::send()` retorna `bool` (aceite do sistema de e-mail) e usa o `plugin.manager.mail` do Core.
- Tipos de e-mail são serviços com a tag `aculta_mailer.type` (`MailTypeInterface`), reunidos por `MailTypeRegistry`.
  Tipo não registrado não envia e registra erro sem destinatário nem conteúdo.
- `hook_mail` (`Hook\MailHooks`) renderiza assunto e corpo do tipo e grava o cabeçalho `X-Aculta-Mail-ID`.
- Testes Kernel (`AcultaMailerKernelTest`, módulo `aculta_mailer_test`): envio de tipo registrado com cabeçalho de
  correlação e recusa de tipo desconhecido. Runtime de teste: módulo habilitado, sem atualização de banco.

## 0.1.0-dev.1 — 2026-10-20

- F0: auditoria do e-mail atual (`docs/AUDIT.md`), pesquisa upstream e decisões de porte (`docs/UPSTREAM.md`),
  arquitetura (`docs/ARCHITECTURE.md`) e roadmap (`docs/ROADMAP.md`).
- F1: contratos de mensagem (`MailMessage`, `MailStatus`, `MessageIdFactory`), política de retry (`RetrySchedule`) e
  interface pública `AcultaMailerInterface`, com testes unitários. Não habilitado e sem efeito em runtime.
- Decisão de transporte registrada: padrão Hostinger com fallback SMTP2GO (`docs/ARCHITECTURE.md` §4.1; implementação na F2).
- F2 (parcial): `TransportFailover` (padrão Hostinger, fallback SMTP2GO, só para erro temporário) e `SmtpReplyClassifier` (4xx temporário, 5xx permanente).
- F6 (parcial): `DeliveryPlanner` combina retry e failover em `DeliveryDecision`. Sem worker de fila nesta versão.
- Contexto de `MailMessage`: passa a aceitar listas e linhas de escalares com até dois níveis; objetos e entidades continuam recusados (`docs/ARCHITECTURE.md` §4).

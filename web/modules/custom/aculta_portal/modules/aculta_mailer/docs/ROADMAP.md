# Roadmap do ACULTA Mailer

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). Todo escopo, testes, gates e revisão visual aplicável devem estar concluídos para RC; Alpha valida o deploy no servidor de desenvolvimento antes da migração a produção.

Status atualizado em 2026-10-20. Cada fase vira commit(s) atômico(s) na branch autorizada; PR só quando solicitada.
Fases com `DEFERRED` dependem de Runtime de teste autorizado e não podem ser declaradas validadas sem evidência.

| Fase | Entrega | Estado |
|---|---|---|
| F0 | Auditoria ([AUDIT](AUDIT.md)), pesquisa ([UPSTREAM](UPSTREAM.md)), arquitetura ([ARCHITECTURE](ARCHITECTURE.md)) | Concluída nesta PR |
| F1 | Fundação: `MailMessage`, `MailStatus`, `MessageIdFactory`, `RetrySchedule`, contrato `AcultaMailerInterface`, testes unitários | Em andamento nesta PR |
| F2 | Transporte (Brevo acrescentado em 2026-10-20: fábrica, transporte `brevo`, cota diária e envio real aceito; `docs/CONTROL-SCREENS.md` para as telas de controle). Anterior: envio por transporte (`X-Transport`), código SMTP real, failover Hostinger → SMTP2GO, diagnóstico sem credenciais e validação com entrega confirmada pelo destinatário (`docs/TRANSPORTS.md`) | Concluída (2026-10-20) |
| F3 | Templates: tipos com variáveis declaradas; Config Entity `aculta_mailer_template` com editor, validação, prévia, envio de teste pelo caminho real, lista com botão de criar (validado no X11) | Concluída (0.1.0-dev.31) |
| F4 | Branding: layouts e envelope HTML com o logo do tema (`aculta420.settings`, lido pelo tema; sem cópia por marca, desde 0.1.0-dev.32). Modo claro e escuro pelos tokens do tema. Recebimento do logo conferido pelo responsável | Concluída (0.1.0-dev.32) |
| F5 | Policy: hierarquia global → tipo, ajustes por plugin (`transport`, `reply_to`, `priority`, `skip`), limites por transporte e tela de edição (`/politicas`), coberta por teste de formulário | Concluída (0.1.0-dev.31) |
| F6 | Queue: fila `aculta_mailer_send`, worker com retry e failover, classificação SMTP real pelo código, cota tratada como temporária para o failover | Concluída (0.1.0-dev.31) |
| F7 | Logging: registro com texto completo da falha (relatório do admin), retenção, ligar/desligar. Reenvio de falha com o conteúdo cifrado (`MAILER_PAYLOAD_KEY`), guardado só nas mensagens com falha; links de uso único expiram em 24 horas (0.1.0-dev.39) | Concluída (0.1.0-dev.39) |
| F8 | Dashboard: contagens por período, estado, tipo, transporte e motivo, agregadas no banco; rótulos em português. Gráficos fora deste RC (a confirmar) | Concluída sem gráficos (0.1.0-dev.31; gráficos a confirmar) |
| F9 | Usuário: pedido de confirmação e demais e-mails de conta já saem pela camada (system.mail). Textos de troca de e-mail e de reset continuam nos módulos de origem | Parcial (envio pela camada; textos do contrib) |
| F10 | Eventos de entrega: contrato de provedor, endpoint, aplicação com transições, monitoramento IMAP de bounces e DMARC. Provedor Brevo (token de portador em `BREVO_WEBHOOK_TOKEN`, o Brevo não assina eventos), lista de supressão (hard bounce, endereço inválido e spam) e chave geral de envio. Webhook cadastrado no ambiente de teste (0.1.0-dev.34). Produção: registrar o webhook com o endereço de produção quando o responsável decidir | Parcial (produção pendente) |
| F16 | Relatórios, registros e alertas no modelo do WP Mail SMTP: endereço de retorno, alertas de falha e de bounce, resumo semanal, opções do registro (ligar e retenção). Mapa completo em `docs/REPORTS-AND-ALERTS.md`. Pendentes: reenvio de falha, página de estado de cada envio, com eventos do Brevo (0.1.0-dev.42), rastreamento (F11). Exportação CSV, SPF/DMARC e DKIM no diagnóstico entregues (0.1.0-dev.33 e 0.1.0-dev.35); ID do provedor gravado (0.1.0-dev.36) | Parcial (alertas, resumo, registro e endereço de retorno prontos; desligados até informar destinatários) |
| F11 | Tracking opcional (abertura e clique), ligado no teste e na produção por decisão do responsável (2026-10-20); vale só com consentimento. Implementado próprio (não pelo Brevo, que não tem controle por mensagem): pixel e links do próprio site assinados, só com consentimento específico no acordo `aculta_rastreamento` (`/aceite-rastreamento`), contadores sem IP nem navegador. Desligado no Runtime e em produção até decisão do responsável (0.1.0-dev.46) | Implementada; ligada no teste e na produção (config) |
| F12 | Integrações ACULTA: cadastro com e-mail já usado, conquista e resumo de avisos já na camada; Forum, LMS, Magazine e Commerce são de outros módulos | Parcial (integrações nos módulos de origem) |
| F13 | Compatibilidade: system.mail = aculta_mailer; todo envio do Core passa pelo plugin; nenhuma chamada direta a mail() no código do projeto | Concluída (0.1.0-dev.31) |
| F14 | Migração da configuração de transporte (`mailer_transport`) para `aculta_mailer_transport`, convivendo com o contrib, sem trocar o envio do site. Entregue: entidade, fábrica SMTP e Brevo, credencial pelo arquivo de segredos (`SMTP_PASSWORD`, contrato). Entidades criadas no runtime e envio SMTP de teste aceito. Pendente: trocar o envio do site para a camada nova (F15), após equivalência | Em andamento (runtime de teste validado no envio) |
| F15 | Retirada do contrib de envio: concluída. `system.mail` = `aculta_mailer`; symfony_mailer, mailer_override, mailer_transport e mailer_policy desinstalados no runtime, sem pacote no composer.json/composer.lock (somente 4 remoções no lock), sem configuração residual e com a configuração exportada. Envio pelo caminho da aplicação aceito depois da remoção. Pendências fora da F15: conferência visual do destinatário; SMTP2GO sem cota no plano atual; o gate de comércio reprova no check de rotas de webhook (já reprovava antes) | Concluída (runtime de teste; produção só com decisão do responsável) |

## Situação para RC

- **Estado: Dev (reaberto em 2026-10-20).** O RC1 da versão 0.1.0-dev.54 foi revertido: a integração do confirmador de e-mail (contrib `email_confirmer`, que o Portal usa) precisa terminar antes do RC. Plano em `docs/portal/EMAIL-CONFIRMER-INCORPORACAO.md`. Histórico do RC1: testes e revisão de código de 2026-10-20, sem achados bloqueantes. Pronto para deploy em Alpha, conforme `docs/operations/RELEASE-LIFECYCLE.md`. Escopo de código concluído: F0–F11, F13, F15 e F16 (alertas e resumo para administradores, já com o destino escolhido). Testes: Unit 137/137, Kernel 35/35 (banco `aculta_simpletest`); textos de conta com teste Kernel (aculta_portal 0.4.0-dev.234, 3/3). Gates: mailer e fronteira passam. O gate de tokens do Portal reprova só por atributos do `aculta_pdf_viewer`, já no HEAD, fora deste módulo. Revisão de código do módulo inteira em 2026-10-20: consultas com `accessCheck` explícito, sem `\Drupal::` novo, sem SQL bruto, cifra XSalsa20-Poly1305 com nonce aleatório, webhook autenticado antes da leitura, TLS com verificação de certificado por padrão, segredos fora do log, escape do corpo HTML. Achados sem bloqueio: Decisões registradas: (1) a verificação de certificado do SMTP continua configurável por transporte, pelo administrador; (2) o webhook agora recusa corpos acima de 16 KB (0.1.0-dev.54); (3) os contadores de abertura e clique não são atômicos (contagem aproximada, não é webhook).
- Fases concluídas: F2, F3, F4, F5, F6, F7 (reenvio com conteúdo cifrado, 0.1.0-dev.39), F8 (sem gráficos), F11 (ligada no teste e na produção, com consentimento), F13, F15. F16: alertas, resumo, registro, endereço de retorno, diagnóstico (SPF, DKIM, DMARC), estado do envio com eventos do Brevo, reenvio e exportação prontos; alertas e resumo seguem desligados até informar destinatários.
- Fora do RC por decisão do responsável (2026-10-20): F12 (Forum, LMS, Magazine e Commerce migram nos próprios módulos). Fora do código, para o Alpha e a produção: webhook e segredos de produção (`docs/PRODUCAO-CHECKLIST.md`), F10 e F14/F15 em produção.
- Validação feita até aqui (não substitui as fases em aberto): Unit 137/137 e Kernel 35/35; revisão visual do painel no Runtime de teste (0.1.0-dev.49, 10 telas em 1270 e 500 px); gates de envio, segredos, rotas, papéis, fronteira, endereços e links de uso único passam.

## Runtime

- F15 concluída no runtime de teste (2026-10-20): contrib de envio desinstalado; envio pelo caminho da aplicação aceito (`sent=1`).

- F15: `system.mail` = `aculta_mailer` no runtime (2026-10-20). Envio pelo caminho da aplicação aceito. Contrib de envio ainda habilitado no runtime (desinstalação bloqueada pelo classificador).

- F15: plugin `aculta_mailer` com envelope HTML e anexo: confirmado pelo destinatário. `system.mail` continua `symfony_mailer`.
- SMTP2GO: autenticação aceita pelo credencial do arquivo de segredos; envio recusado por cota mensal do plano (`552`). Contingência indisponível até a renovação ou a troca de plano.

- F14: entidades `aculta_mailer_transport` `smtp_hostinger` e `brevo` criadas no runtime e exportadas em `config/sync`. Envio de teste pelo `smtp_hostinger` aceito (`sent=1`) para o destinatário autorizado. Conferência do destinatário pendente.

- Runtime de teste: endereço de retorno `dmarc@aculta.org` gravado (`aculta_mailer.delivery`). Um envio SMTP de teste foi aceito (`sent=1`) para o destinatário autorizado. Verificar no cabeçalho original da mensagem recebida: `Return-Path: <dmarc@aculta.org>`. **DEFERRED** até essa verificação.
- Alertas e resumo semanal: desligados (sem destinatários informados).

- Runtime de teste (`aculta_runtime`): `aculta_mailer` habilitado (2026-10-20, sem updb). Consumidores migrados: cadastro com e-mail já usado, conquista e resumo de avisos. Confirmação de e-mail continua no `email_confirmer` (F9).
- Envio real pelo transporte não foi executado: os testes usam o coletor de e-mail do Core, sem SMTP.
- Validação de envio real (F2 em diante): **DEFERRED** até autorização explícita de ambiente de teste.

## Pendências de decisão

- Transporte padrão: **Hostinger** (`smtp`), com **fallback para SMTP2GO**, decisão do responsável (2026-10-20). A configuração versionada já aponta o padrão para `smtp`; o fallback é implementado na F2.
- Remetente: confirmar na F2 (ver `docs/portal/EMAIL-TRANSPORT.md` §4.2).
- Caminho administrativo: `/painel-administrativo/...`, conforme decisão do responsável.
- Local dos templates de layout: módulo, com estilos vindos do tema via dados e classes (decisão do responsável).

## Produtor de eventos para estatísticas (ADR-0004 do aculta_statistics)

Pendente. O resumo de envios por estado exato (nove estados) precisa de um consumidor que o
`aculta_statistics` não tem hoje: o funil só tem cinco métricas.

- Produtor proposto: `MailStatusChangedEvent` (`aculta_mailer.status_changed`), disparado após a
  transição gravada em `DeliveryEventApplier` e na gravação de estado de envio. Carrega identificador do
  registro, novo estado e transporte. Sem destinatário, assunto ou corpo.
- Decisão em aberto: a métrica de estado conta **transições** (evento) ou **estado atual** (conciliação
  do `aculta_mail_log`)? Recomendação: estado atual por dia de solicitação, pela mesma conciliação do funil,
  para não contar uma mensagem duas vezes quando muda de estado.
- Bloqueio resolvido depois: `email.messages.status` (dimensão = estado) no catálogo, depois troca do
  `MailDashboard::summary()` para o `aculta_statistics`.

## Telas de controle (planejado)

- Lista de e-mails do sistema, suspensão por tipo, coluna de destinatário, diagnóstico de domínio (SPF, DKIM, DMARC) e editor visual. Ver `docs/CONTROL-SCREENS.md`.

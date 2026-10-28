## 0.1.0-dev.74 — restringir detalhes pessoais nos logs operacionais — 2026-10-25

Falhas IMAP agora guardam e registram somente a classe da exceção; o estado
continua útil à tela administrativa sem carregar texto de servidor que possa
conter dados pessoais. A tela exibe esse diagnóstico categórico. O plugin de
transporte não copia a resposta completa do provedor para o logger: registra o
transporte e o código SMTP. Novas falhas guardam somente classe/código no Mail Log e nos alertas. Registros
históricos anteriores permanecem inalterados sob a retenção/acesso atuais; a
tela mascara estado IMAP legado e nenhuma limpeza de logs foi executada.

`MailMonitorTest` **6/18**, `MailSettingsKernelTest` **2/5**, Unit Mailer **162/343** e Kernel Mailer **46/214 PASS** no MariaDB dedicado. PHPCS Drupal/DrupalPractice passou sem erros; persistem avisos de linhas >80 chars nos arquivos tocados. Gate Drupal 11+ (**1.155 checks**), índice e diff check PASS. PHP 8.4.27; Runtime e SMTP externo não usados.

## 0.1.0-dev.73 — consulta de aceite por correlação — 2026-10-24

O contrato público do Mailer agora permite consultar se uma correlação opaca foi aceita, sem exigir o endereço original.
O Messenger usa essa consulta para reconciliar o manifesto persistido após uma interrupção durante o acknowledgement.

Validação: `AcultaMailerKernelTest` 5 testes/26 asserções PASS, incluindo consulta por correlação; Unit Mailer
157/317 PASS. O registro de aceite continua sujeito à retenção administrativa configurada.

## 0.1.0-dev.72 — retry idempotente exige log de aceite — 2026-10-24

Envios com correlação idempotente agora falham de forma temporária quando o registro de e-mail está desativado.
Sem o log persistido, o Mailer não poderia reconhecer um aceite anterior após falha no acknowledgement do digest;
permitir novo transporte nesse estado poderia duplicar mensagens. O cron mantém a pendência e pode tentar novamente
quando o log for habilitado. Mensagens sem correlação idempotente mantêm o comportamento anterior.

Validação: `AcultaMailerKernelTest` 5 testes/24 asserções PASS. O aceite externo seguido de falha antes da gravação
local continua fora da garantia transacional.

## 0.1.0-dev.70 — correlação estável e supressão de reenvio aceito — 2026-10-24

`MessageIdFactory::correlationIdFor()` gera UUID v5 para a mesma chave lógica; `MailMessage` aceita UUID v4 e v5.
Antes de iniciar o transporte, o Mailer consulta o registro ativo por correlação, tipo, HMAC do destinatário e estado
`sent`, retornando sucesso para um retry já aceito. Correlações v4 de mensagens novas mantêm o comportamento anterior.

Validação: Mailer Unit 157/317 e Kernel 42/193; o Kernel reenvia a mesma correlação e confirma somente um transporte.
Não há garantia transacional com SMTP/provedor; a proteção exige log ativo e registro persistido.

## 0.1.0-dev.69 (regressão do container, sem mudança de versão)

- A nota da dev.22 dizia que `LmsForumModerationController` (do `aculta_forum_lms`) quebrava o rebuild do container. Reproduzido no servidor de testes: `drush --uri=https://aculta.toca.net.br cache:rebuild` retorna `[success] Cache rebuild complete.` com código 0 em 2026-10-22. A falha antiga não se reproduz; o container resolve o controller.
- Nenhuma alteração de código, configuração ou conteúdo foi feita para essa verificação. Log privado em `/home/aculta/private/aculta/t11-20261022-drush-cr.log`.
- Versão mantida: ver a nota da entrada anterior sobre a tabela do README raiz.

## 0.1.0-dev.69 (correção de fronteira, sem mudança de versão)

- Prévia do modelo (`MailTemplateForm`) e gráfico de tendência (`MailDashboardController`) deixam de usar classes de design e passam a usar os atributos neutros `data-aculta-mailer-preview` e `data-aculta-mailer-tendencia`. Nenhuma regra CSS dependia das classes antigas, então a aparência não muda.
- Gate `validate-module-theme-boundary.php`: PASS (2123 verificações; 0 classes de design no módulo).
- Testes: Unit 154/310 OK; Kernel 41/189 OK (banco `aculta_simpletest`).
- Versão mantida: a tabela do README raiz registra `0.1.0-dev.69`; subir a versão exige regenerar a tabela junto com as edições de outros componentes.

## 0.1.0-dev.69 — formato de e-mail unificado — 2026-10-22

- O corpo HTML do modelo usa `email_html` (Aculta Email), no lugar de `aculta_email`, que foi removido na unificação dos formatos. Constante `MailTemplate::BODY_FORMAT_HTML`.
- Kernel 41/41.

## 0.1.0-dev.68 — modo de cor do e-mail segue o controle do site — 2026-10-22

- O envelope recebe o modo de cor do site (`aculta_portal.appearance`, o mesmo controle da aparência): light, dark ou auto. Antes seguia só a preferência do sistema.
- Variável `color_mode` declarada no `hook_theme`. Kernel 41/41; unidade 154/154.

## 0.1.0-dev.67 — envelope sempre com o tema do site — 2026-10-22

- Envio disparado de página administrativa usava o tema administrativo (`claro`) e saía sem o envelope ACULTA (logo, cores, marca). O envelope agora é renderizado com o tema padrão do site, e o tema anterior é restaurado depois.
- Reproduzido localmente com o tema `claro` ativo: envelope com cabeçalho, logo e rodapé da marca.

## 0.1.0-dev.66 — layout de marca no envelope HTML — 2026-10-22

- Com layout, o envelope do tema não repete o rodapé: o rodapé da marca vai no corpo. Sem layout, o rodapé fixo da associação continua. Flag `layout` passado do renderizador ao envelope e declarado no `hook_theme` (variável não declarada não chega ao template).
- Teste de Kernel: `layout` verdadeiro quando o modelo usa layout. Kernel 41/41; unidade 154/154.

## 0.1.0-dev.65 — layout e marca com corpo HTML — 2026-10-22

- Com corpo HTML, o cabeçalho, o rodapé e a marca do layout são texto simples: passam a ser escapados e as quebras de linha viram `<br>`. Antes, entravam crus no HTML (texto colado, quebras perdidas). O HTML do modelo não muda.
- Teste de Kernel: marca com `&` e rodapé com `<` e quebra de linha. Kernel do mailer 41/41; unidade 154/154.

## 0.1.0-dev.64 — script sai com o conteúdo no modelo HTML — 2026-10-22

- Script e estilo são removidos com o conteúdo antes do filtro do formato `aculta_email`. Antes, o filtro tirava só a tag e o texto (por exemplo `alert(1)`) ficava no e-mail e na prévia.
- Verificado no navegador no modelo desabilitado de teste (prévia renderizada, sem texto de script).

## 0.1.0-dev.63 — prévia do modelo mostra o resultado, não o código — 2026-10-22

- A prévia do formulário de modelo passa a mostrar o corpo HTML renderizado, filtrado pelo mesmo formato do envio. Antes aparecia como código dentro de `pre`.
- `MailTemplateRenderer::preview()` sanitiza a prévia em HTML. Teste de Kernel: script removido, parágrafo mantido.
- Testes do formulário de modelo adaptados ao `text_format` (corpo com `value` e `format`). Kernel do mailer 40/40.

## 0.1.0-dev.62 — formulário de modelo novo sem erro — 2026-10-22

- Corrige `TypeError` ao abrir o formulário de adicionar modelo (HTTP 500): `MailTypeRegistry::has()` recebia `NULL`, porque o modelo novo ainda não tem ID. A busca do tipo só ocorre quando há ID. O erro já existia antes da 0.1.0-dev.61.
- Verificado no servidor de testes: o formulário responde 200 e carrega o editor `aculta_email`.

## 0.1.0-dev.61 — editor visual de modelos com formato HTML restrito — 2026-10-22

- `MailTemplateForm`: o corpo do modelo passa a ser `text_format` com o formato `aculta_email`, no lugar do `textarea`. A validação de variáveis lê o valor do editor e a gravação marca o formato HTML.
- `MailTemplate`: nova propriedade `body_format` (vazio = texto simples; `aculta_email` = HTML restrito), exportada e descrita no schema. Modelos existentes continuam em texto simples.
- `MailTemplateRenderer`: em corpo HTML, cada valor de variável é escapado (`fill()` com escape) e o corpo final passa pelos filtros habilitados do formato `aculta_email`. Sem o formato, a falha é fechada: o texto sai escapado. Substitui o uso de `check_markup()`, deprecado no Core 11.4.
- Envio: `MailHooks` guarda o HTML do modelo e `AcultaMailerMail` o usa na parte HTML, sem escapar de novo. A parte de texto segue pelo `format()` como antes.
- Testes: unitários de escape e padrão (154/154); Kernel do mailer 39/39, incluindo o caminho de envio com a parte HTML sanitizada e o valor do usuário escapado.
- Depende do formato `aculta_email`, criado pelo `aculta_editor`. Sem ele, o corpo HTML é escapado inteiro (falha fechada).

## 0.1.0-dev.60 — rótulo do último dia do gráfico sem sobreposição — 2026-10-21

- Verificado no navegador (painel de área, período de 30 dias): o rótulo do último dia encostava no penúltimo. O último rótulo só aparece quando há espaço.
- Sem mudança de dados. Unit do módulo: 149/149.

## 0.1.0-dev.59 — legenda e rótulos do gráfico de tendência dentro da área visível — 2026-10-21

- Correção visual verificada no navegador (servidor de testes): a legenda ficava fora da área do SVG e cortava o nome das séries. A altura do gráfico passou de 260 para 292, e a legenda e os rótulos de dia ficaram em posições que não se sobrepõem à linha do zero.
- Sem mudança de dados nem de contrato. Unit 149/149.

## 0.1.0-dev.58 — painel de envios com período e tipo nos gráficos — 2026-10-21

- Painel de envios: seletor de período (7, 14 e 30 dias) e de tipo (todos, confirmados, não confirmados, com falha). Valem para o gráfico de tendência, para as contagens e para os quadros de estado, transporte, tipo e motivo. A página usa GET e funciona sem JavaScript.
- Gráfico de tendência por dia em SVG inline (`Chart\MailTrendChart`): uma linha por série escolhida, legenda, valores repetidos numa tabela e rótulos escapados.
- Categorias do tipo em `MailDashboard::FILTERS`: confirmados (entregue), não confirmados (na fila, processando, aceito, adiado), com falha (falhou, devolvido, reclamação). Cancelado só aparece em "todos".
- Dados: série ao vivo do `aculta_mail_log` (`MailDashboard::dailyCounts()`). O painel continua ao vivo, por ser operacional.
- Testes: Unit 149/149 (inclui `MailTrendChartTest`); Kernel 35/35 (página com filtro de falha e período de 14 dias).

## 0.1.0-dev.57 — modelo de e-mail das estatísticas para o administrador — 2026-10-21

- Novo tipo `aculta_admin_statistics` (`AdminStatisticsMailType`), com as variáveis `periodo` e `estatisticas`. O texto dos indicadores vem de `StatisticsLinesProviderInterface`, opcional: sem o `aculta_statistics`, o e-mail informa que as estatísticas não estão disponíveis.
- `MailSummary` envia o modelo junto do resumo semanal, para os mesmos destinatários, no cron semanal (`MailSummary::buildStatistics()`).
- O resumo semanal de envios continua só com dados de envio.
- Testes: Unit 146 casos, aprovados (inclui o novo tipo).

## 0.1.0-dev.56 — MAIL FROM com endereço de retorno no SMTP — 2026-10-20

- `MailTransportSender::envelopeFor()`: para transporte SMTP, o envelope (MAIL FROM) usa o `bounce_address` configurado.
  O `ReturnPathSubscriber` não alcançava o SMTP criado pela fábrica (sem dispatcher), e o envio saía com `naoresponda@`.
- Verificado no IMAP com envio de teste: `Return-Path: <dmarc@aculta.org>`, SPF e DKIM aprovados (correlação 7f17ccdd-…).
- Testes: Unit do mailer 179/179 (`MailTransportSenderEnvelopeTest`, 3 testes).

## 0.1.0-dev.55 — chave de confirmação de e-mail renomeada — 2026-10-20

- `SingleUseLinkMail` e seu teste: a chave do pedido de confirmação passa a ser `aculta_mail_confirmer:confirmation_request` (antes `email_confirmer:...`), por causa da incorporação do confirmador.
- Unit do mailer 141/141.

## 0.1.0-dev.54 — RC1; teto de tamanho no webhook — 2026-10-20

- Estado do módulo: **RC1**, com autorização do responsável. Pronto para deploy em Alpha (`docs/operations/RELEASE-LIFECYCLE.md`); o deploy depende de autorização separada.
- Webhook: pedidos acima de 16 KB recebem 413 antes da verificação de assinatura, e o Content-Length declarado é conferido antes da leitura. Eventos válidos não mudam. Verificado no Runtime de teste: corpo de 20 KB sem token → 413; evento pequeno sem token → 403.
- Decisão: a verificação TLS do SMTP continua configurável por transporte, pelo administrador (ligar e desligar). Sem aviso extra nesta versão.
- Evidência: Unit 141/141; Kernel do webhook 5/5.

## 0.1.0-dev.53 — barra do e-mail em três abas: Painel, Operação e Configuração — 2026-10-20

- Painel do e-mail com três abas: **Painel**; **Operação** (Envios, Falhas, Diagnóstico, Monitoramento); **Configuração** (Entrega, Registro, Políticas, Modelos, Layouts, Marcas, Configurações).
- A aba E-mail não aparece no grupo ACULTA, para não duplicar a barra.

## 0.1.0-dev.52 — painel do e-mail em /painel-administrativo/aculta/mail, com subaba E-mail — 2026-10-20

- Painel movido para `/painel-administrativo/aculta/mail`; todas as páginas do e-mail (envios, falhas, registro, entrega, modelos, layouts, marcas, políticas, diagnóstico, monitoramento, configurações) ficam sob esse caminho.
- Subaba **E-mail** na aba ACULTA, com os itens do painel. Os links de entidade (modelos, layouts, marcas) acompanham.
- Caminhos antigos `/painel-administrativo/configuracoes/aculta/...` respondem 404, sem redirecionamento.

# Changelog — ACULTA Mailer

## 0.1.0-dev.51 — gráficos do painel (F8); exceção histórica do gate — 2026-10-20

- F8: cada período do painel ganha barras de contagem por estado e por transporte. Barras com o elemento nativo `progress`, nome e valor em texto escapado (não depende de cor). Sem classe de design no módulo e sem biblioteca JS.
- Revisão visual no Runtime de teste, 1270 e 500 px, com sessão de admin por link de uso único gerado só para a captura; sessão encerrada depois. Primeira versão mostrava só barras, sem rótulo; corrigida antes do commit.
- Teste Kernel: uma barra por estado no painel renderizado.
- Gate de índice: `a57f777d` registrado como exceção histórica, por decisão do responsável (commit de documentação sem a linha do README; corrigido no commit seguinte).
- Evidência: Unit 137/137; Kernel do painel 3/3; gate de índice PASS.

## 0.1.0-dev.50 — cabeçalhos da tabela sem hifenização; roadmap atualizado — 2026-10-20

- `css/admin.css`: cabeçalhos da tabela de envios sem hifenização e sem quebra de palavra ("Es-tado", "De-ta-lhe" na revisão de 0.1.0-dev.49). A tabela continua rolando dentro do contêiner.
- Não revisado visualmente nesta versão: a sessão de revisão foi encerrada e reabrir o painel exige nova autorização de acesso.
- `docs/ROADMAP.md`: situação para RC atualizada (F7, F8 e F11 concluídas; F9 aguarda aprovação dos textos; F16 com o que está pronto). O roadmap estava defasado.
- Textos de conta: rascunho em `docs/TEXTOS-DE-CONTA-RASCUNHO.md`, sem publicação.

## 0.1.0-dev.49 — revisão visual do painel de envios; sem hash_salt, nada é enviado — 2026-10-20

- Decisão do responsável: sem `hash_salt` no settings.php, `deliver()` recusa o envio (log de erro, sem registro). Antes, o HMAC do destinatário ficava com chave vazia. Teste Kernel `testMissingHashSaltRefusesSendAndLogsNothing` (falha quando o salt volta a ser usado).
- Revisão visual no Runtime de teste (Chromium headless, sessão de admin por link de uso único gerado só para esta revisão, sem alterar senha ou e-mail): 10 telas do painel, em 1270 e 500 px, 20 capturas. Sem erro, sem acesso negado; a página de estado do envio não mostra destinatário.
- Corrigido: a tabela de envios em tela estreita estourava a página inteira (398 px). A rolagem agora fica no contêiner (`data-aculta-mailer-table` em `<div>`); a página fica sem overflow.
- Corrigido: a visão geral colava os dois links; agora cada um fica em parágrafo.
- Corrigido: textos desatualizados. O interruptor de rastreamento dizia "desligado por padrão" com a opção ligada; o registro dizia "fase F11 indisponível" e "nenhum webhook ativo". Agora descrevem o estado real.
- Observação (não alterada): em desktop a tabela de envios é estreita, e os cabeçalhos quebram em sílabas ("Es-tado", "De-ta-lhe") por causa da coluna de correlação.
- Evidência: Unit 137/137; Kernel 35/35 (banco `aculta_simpletest`).

## 0.1.0-dev.48 — rastreamento falha fechado sem hash_salt — 2026-10-20

- `MailTracker`: sem `hash_salt` o rastreamento fica indisponível (`enabled()` FALSE, HTML inalterado) e `verify()` recusa toda assinatura. Antes, uma chave vazia tornava o HMAC previsível e permitia forjar aberturas e cliques.
- Revisão de código das rotas públicas de rastreamento: comparação em tempo constante, redirecionamento só para o mesmo host, URL assinada junto com a correlação. Sem alteração nesses pontos.
- Teste unitário novo: `testWithoutSaltTrackingIsUnavailableAndNothingVerifies`. Unit 137/137. Kernel 34/34 no banco `aculta_simpletest`. Sem alteração de config nem de Runtime.
- Pendente (registrado, não alterado): o HMAC do destinatário em `MailLogRecorder::hashRecipient` usa o mesmo `hash_salt`; com chave vazia o endereço ficaria recuperável por dicionário. Falhar fechado ali quebraria o registro, então exige decisão.

## 0.1.0-dev.47 — estado dos relatórios alinhado ao código; tabela com atributo do módulo — 2026-10-20

- Tabelas do painel de envios usam `data-aculta-mailer-table="rolagem"` (antes `data-portal-ui`). O painel usa o tema Claro, fora do mapa do ACULTA420; o token mapeado no tema tiraria o atributo e quebraria a rolagem.
- `docs/REPORTS-AND-ALERTS.md`: cinco linhas estavam "pendentes" ou "não implementadas" sem serem (F11 ligada, webhook do Brevo, chave geral, DKIM, reenvio de falha e consulta ao Brevo na página de estado). Corrigidas com a versão em que cada uma entrou.
- Evidência: Unit 136/136 e Kernel 34/34 no banco `aculta_simpletest`. Runtime de teste sem alteração de código nesta versão.

## 0.1.0-dev.46 — rastreamento opcional de aberturas e cliques (F11), ligado no teste e na produção

- `MailTracker`: pixel e links do próprio site, assinados com o `hash_salt`; só para destinatário com consentimento e com `tracking_enabled` ligado. Links externos e o restante do HTML não mudam.
- Consentimento: acordo `aculta_rastreamento` (`/aceite-rastreamento`), separado dos termos, revogável; lido pelo Portal (`MailTrackingConsent`, tag `aculta_mailer.tracking_consent`).
- Rotas `/aculta-mailer/t/o/{correlação}` e `/aculta-mailer/t/c/{correlação}`: só respondem com assinatura válida; redirecionamento só para o mesmo host.
- Registro: contadores `opens`, `clicks` e datas de primeira abertura e clique (`aculta_mailer_update_10006`). Sem IP nem navegador.
- Interruptor na página de entrega. Ligado no Runtime de teste (2026-10-20) e em `config/sync` (`tracking_enabled: true`), que leva a produção no próximo deploy. Continua valendo só para quem deu consentimento em `/aceite-rastreamento`.
- Validado no Runtime de teste: com consentimento e recurso ligado, a abertura registrou contagem, o clique redirecionou e contou, e assinatura adulterada foi recusada. Limite observado: o proxy de imagens do Gmail registra abertura sem o usuário ter aberto; contagens de abertura são superestimadas nesses clientes.
- Testes unitários: 136.


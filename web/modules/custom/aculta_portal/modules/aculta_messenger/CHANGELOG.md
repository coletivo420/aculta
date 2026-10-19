# Changelog

Mudanças relevantes do ACULTA Messenger.

## 0.1.1-dev.47 — confirmação de Bloquear e Desfazer amizade em modal do Drupal — 2026-10-19

- `ConfirmDialog` deixa de usar `<dialog>` nativo. A pergunta é um contêiner oculto em `data-aculta-dialog`, e o tema abre o diálogo com `Drupal.dialog` (regra de `POPUP-PADRAO.md`).
- Sem mudança de texto nem de ação: "Sim" continua submetendo o formulário (o diálogo fica dentro do form, por `appendTo`). Validação no navegador: pendente.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.46 — docblocks de Opções corrigidos — 2026-10-19

- Docblock "Opções" movido de `drawerOptions()` para `drawerRelationship()`, que é o método que o descreve. Sem mudança de comportamento.

## 0.1.1-dev.45 — avisos só para contas — 2026-10-17

- O ícone de avisos e a contagem não aparecem para visitante anônimo (decisão do responsável).
- A prévia de avisos (`/avisos/resumo`) exige conta: visitante recebe 403.
- RUNTIME STATUS: APLICADO no Runtime de testes (página inicial anônima sem ícone de aviso; prévia 403 para anônimo).

## 0.1.1-dev.44 — "Ver todos os avisos" como botão no rodapé da gaveta (F5.2) — 2026-10-16

Classificação: PATCH (ajuste de apresentação e de URL; sem regra nova).

- O link "Ver todos os avisos" sai da lista e vira botão no rodapé fixo da gaveta de avisos da barra, abaixo de todos os avisos.
- A URL é absoluta no purpose `account` (`DomainPurposeManager::canonicalRouteUrl`), e não mais relativa ao host atual.
- O rodapé só aparece para quem está logado.
- Gaveta de mensagens: botão "Ver todas as mensagens" no mesmo rodapé, apontando para a aba Mensagens da conta (`all_url` em `aculta_messenger`).
- Gaveta de avisos: a lista rola dentro da gaveta e o rodapé fica fora da rolagem (verificado no Firefox com lista longa).
- Teste unitário `PersistentNoticeAccessTest` corrigido: o construtor de `MessengerBarHooks` agora recebe `AvisoService` e `DomainPurposeManager`. Os 33 testes do módulo passam.

## 0.1.1-dev.43 — rótulo Lido/Não lido nos alertas do site (F5.2) — 2026-10-16

Classificação: MINOR (novo flag de leitura e rótulo nos alertas do site).

- Alertas do site (não persistentes) mostram "Lido" ou "Não lido", à direita da caixa. Avisos persistentes não têm rótulo.
- Novo flag `aculta_site_alert_read` por pessoa (config em `config/install` e na configuração do projeto). Abrir o alerta marca como lido, só quando a própria pessoa abre (sem pré-busca).
- Post-update `aculta_messenger_post_update_site_alert_read_flag` cria o flag no Runtime.
- Regra de vida útil documentada em `docs/AVISOS-E-MENSAGENS.md`, seção 8.2.

## 0.1.1-dev.42 — rótulo Lido/Não lido à direita, com cor de fonte por estado (F5.2) — 2026-10-16

Classificação: PATCH (ajuste de apresentação do rótulo criado em dev.41).

- O rótulo sai do lado esquerdo e fica à direita da caixa, no topo, fora do link (a caixa inteira continua clicável).
- Lido e Não lido têm cores de fonte diferentes; o fundo fica só atrás do texto do rótulo.

## 0.1.1-dev.41 — rótulo Lido/Não lido e retenção de 6 meses dos avisos do Fórum (F5.2) — 2026-10-16

Classificação: MINOR (nova regra de retenção e novo rótulo de estado).

- Caixas de conversa e avisos do Fórum mostram o rótulo "Lido" ou "Não lido" no topo, à esquerda. Não há mais marcação por cor nem "N novas" nessas caixas.
- Ler o tópico zera o contador do aviso e troca o rótulo para "Lido". Abrir a conversa faz o mesmo.
- Avisos do Fórum (`aculta_aviso`, tipos de resposta e atualização) são excluídos automaticamente seis meses após a criação (cron, `AvisoRetencaoHooks`).
- Fora da regra: avisos persistentes (`aculta_notice`) e alertas do site (`site_alert`), enviados pelo sistema administrativo, não têm rótulo de lido/não lido nem regra temporal. Os avisos do LMS também ficam fora: não são `aculta_aviso` e não têm tipo de Fórum, então não são excluídos nem recebem o rótulo. Conversas seguem a própria política de retenção.
- Avisos do Fórum não recebem mais a cor do tipo "Informação".
- Testes: `AvisoRetencaoTest` (limite de seis meses e tipos sujeitos à regra).

## 0.1.1-dev.39 — cliques nas abas da central abrem o item no lugar (F5.2) — 2026-10-16

- A área das abas Avisos e Mensagens é um contêiner da gaveta (`data-aculta-drawer-url`/`-content`), e os cliques abrem o aviso ou a conversa no mesmo lugar, como na barra.

## 0.1.1-dev.38 — aba Mensagens com a lista de conversas da gaveta (F5.2) — 2026-10-16

- A aba Mensagens da conta mostra a mesma lista de conversas da gaveta da barra (`conversasBuild`), no lugar do link "Abrir conversas".

## 0.1.1-dev.37 — aba Avisos sem "Ver todos" (F5.2) — 2026-10-16

- A aba Avisos da conta não mostra o link "Ver todos", porque já é a lista completa. A gaveta da barra mantém o link.

## 0.1.1-dev.36 — aba Avisos da conta com a mesma lista da gaveta da barra (F5.2) — 2026-10-16

- A aba Avisos da conta mostra a mesma lista da gaveta (comunicados, avisos da pessoa e alertas), com os mesmos itens e ações.
- A montagem da lista saiu da resposta HTTP da gaveta (`alertsBuild`), para ser reutilizada.

## 0.1.1-dev.35 — central Avisos/Mensagens em Minha conta (F5.2) — 2026-10-16

- Central "Avisos/Mensagens" em Minha conta, com abas Avisos e Mensagens no padrão da conta.
- Prévia da barra: avisos da própria pessoa seguidos do link "Ver todos os avisos".
- Correção: o controller da central não redeclara a propriedade `$currentUser` da classe base.
- Item "Avisos/Mensagens" na navegação de Minha conta (conta.aculta), com badge de não lidos, pelos pontos de extensão do shell da conta. A barra usa os ícones do aculta_messenger.

## 0.1.1-dev.34 — avisos por pessoa: atualizações, contador do ícone, controles e resumo diário (F5.2) — 2026-10-16

- Atualização de tópico seguido vira aviso `forum_atualizacao` (worker reescrito), sem mensagem.
- Contador do ícone de avisos soma os avisos por pessoa não lidos.
- Bloco "Avisos" na privacidade da conta: resumo diário por e-mail (opt-in) e tipos incluídos.
- Resumo diário por e-mail pelo cron, agrupado por tópico, só com novidade e e-mail confirmado.
- Pendente: página "Meus avisos" e teste de entrega real de e-mail.

## 0.1.1-dev.33 — avisos por pessoa para respostas em tópico seguido (F5.2) — 2026-10-16

- Entidade `aculta_aviso` (uma por pessoa e tópico) com contadores do site e do e-mail e marca do último comentário.
- `Aviso\AvisoService`: conta cada comentário publicado uma vez; autor não recebe aviso do próprio comentário; leitura zera o contador do site.
- Hooks `comment_insert` e `comment_update` (aprovação) e leitura pela rota canônica do tópico.
- Entrega por mensagem de tópico seguido desligada (`MessageSubscribeHooks::getSubscribers` retorna vazio) — F-D21.
- Documentação: `docs/AVISOS-E-MENSAGENS.md` separa mensagens e avisos e descreve cada função do módulo.
- Pendente: aviso de atualização de tópico, contador na barra, resumo diário por e-mail.

## 0.1.1-dev.32 — avisos não interrompem perfis anônimos — 2026-10-15

Classificação PATCH. A dispensa de avisos pertence a contas autenticadas: contagem
na barra e prévia não consultam Flag com uid 0 sem sessão. Corrige erro 500
reproduzido em perfil público durante a verificação do Game. Sem mudança de
permissões, conteúdo ou Core/contrib.

Verificação: HTTP anônimo do perfil e 17 verificações HTTP do Game passaram após
a correção, incluindo ocultação/reexibição e cache em outra sessão. API conferida
no Flag 5.1.1 instalado (`populateFlaggerDefaults`); referência upstream:
https://www.drupal.org/project/social/issues/2925907.

## 0.1.1-dev.31 — correção dos menus e da suíte de navegador; grupos e confirmações — 2026-10-15

Classificação: PATCH (correções encontradas pela suíte de navegador e pela auditoria; sem mudança de regra de negócio).

- Conversa em grupo tem linha própria na lista (antes era absorvida pela caixa de um dos membros) e menu "Opções" com "Limpar histórico" em duas etapas.
- Menu "Opções": a exibição não processa a entrada nem carrega formulário em cache; a ação é construída de novo com as dependências. Evita o envio repetido e a propriedade não inicializada.
- Classes de apresentação saíram do módulo: cada uma é um token `data-portal-ui` (mapa em `PortalUiHooks`). Links com token usam `PortalLinkElement`; a caixa "Opções" usa `OptionsDisclosure` (details do Core) e a confirmação usa `ConfirmDialog`.
- Aviso de bloqueio do Private Message em português (`private_message.settings`, exportado em `config/sync`).
- Gate de fronteira: a regra de classes varre todos os `src/` do módulo (submódulos inclusos); dívida de `aculta_gallery` registrada como `DT-BOUNDARY-GALLERY`.
- Validação: suíte de navegador (`tests/browser/aculta-messenger/`) 70 de 70 no runtime de teste.
- Base dos tipos de conversa: `Service\ConversationPolicy` (tipo, limite de participantes e retenção), usada pela retenção; documentação em `docs/portal/MESSENGER-CONVERSATION-KINDS.md`. Criação de grupo pela gaveta e integrações ainda não existem.
- Lista de conversas: o menu "Opções" de cada linha é só o ícone de três traços; o painel abre sobre a linha, alinhado à direita da caixa (a conversa aberta mantém a caixa completa).
- Aviso persistente não tem botão de dispensar: cartão em cor de aviso (`notice-summary-persistent`), e a leitura é o link "Marcar como lido" no aviso completo. Avisos do site (dispensáveis) ganham a permissão `flag aculta_site_alert_dismissed` no papel autenticado, que faltava.
- Avisos na gaveta: cada aviso (persistente ou do site) é um resumo em caixa de botão; o clique abre o aviso completo na mesma gaveta (rotas `aculta_messenger.notice_detail` e `aculta_messenger.alert_detail`, com "← Todos os avisos").
- Lista de conversas: o menu "Opções" de cada linha não é montado na lista; a linha guarda o endereço do menu e o carrega na primeira abertura (rota `aculta_messenger.drawer_options`).
- Revisão de código (medium), mesma PR: destinatário da nova conversa vem da rota; ação de Opções no perfil mostra o resultado; IDs de título dos diálogos únicos; contagem de avisos para visitantes zerada; fila de aviso de fórum deixa de reenfileirar para sempre; script de preparação recusa outro banco que não aculta_runtime.
- RUNTIME STATUS: ACTIVE no teste.

## 0.1.1-dev.30 — confirmação para bloquear e botões Cancelar/Voltar — 2026-10-15

Classificação: PATCH (confirmação antes de bloquear em todos os locais e botões da nova conversa).

- "Bloquear" (menu Opções das conversas e do perfil) abre a janela "Você quer mesmo bloquear <usuário>?" com "Sim" e "Cancelar". Só o "Sim" bloqueia.
- "Desfazer amizade" (mesmo menu) também pede confirmação: "Você quer mesmo desfazer a amizade com <usuário>?", com "Sim" e "Cancelar".
- Nova conversa: "Cancelar" e "Voltar para o início" ficam acima de "Enviar", cada um com sua cor (`PortalLinkElement`).
- Validação: no runtime de teste, perfil de teste: abrir, cancelar, bloquear pelo "Sim", desbloquear; desfazer amizade pelo "Sim" e recriar a amizade. Gaveta: abrir e cancelar (sem enviar "Sim" na conta real). O "Sim" pela gaveta não foi exercido.

## 0.1.1-dev.29 — menu "Opções" nas conversas e no perfil — 2026-10-15

Classificação: PATCH (troca do menu quebrado das conversas pelo menu "Opções" e inclusão do menu no perfil).

- Conversas: os três pontos antigos saem; cada linha da gaveta mostra o "Opções" (amizade, limpar histórico em duas etapas, bloquear).
- Perfil `/u/…`: caixa "Opções" no canto superior direito do cartão, com "Iniciar conversa" no lugar de "Limpar histórico". "Iniciar conversa" abre a gaveta de mensagens já na etapa de nova conversa com o membro.
- Rota nova `aculta_messenger.profile_relationship` (POST) aplica as ações do perfil e volta ao perfil.
- Validação: capturas de tela no runtime de teste (painéis abertos, perfil e linha). A suíte de 52 verificações não foi reexecutada depois desta mudança; ver `docs/portal/TESTES-NAVEGADOR.md`.

## 0.1.1-dev.28 — correções da revisão no navegador: resposta, limpeza, desbloqueio — 2026-10-15

Classificação: PATCH (correções de fluxo encontradas pela suíte de navegador).

- Resposta enviada pela gaveta: o formulário nativo do Private Message redirecionava para a própria página (`/private-messages/…`, que não existe neste domínio). A gaveta agora envia por `fetch` para `aculta_messenger.drawer_reply`, que aplica as mesmas regras do compositor (bloqueio, só amigos, limite de envios) e grava a mensagem. O token do formulário é validado como o Form API faz.
- "Limpar histórico": o link apontava para uma rota do domínio da conta (404 aqui). Agora é ação em duas etapas dentro do menu: primeiro pede confirmação, depois limpa só para quem confirma. A confirmação some quando outra ação é feita.
- Depois da limpeza a resposta é a lista de conversas, com "Histórico limpo para você.".
- Desbloquear pelo link do aviso: os botões do aviso são identificados por um campo oculto `mode=notice`, porque o rótulo do aviso é diferente do rótulo do menu.
- O menu de Opções fica aberto depois de uma ação, para que o resultado seja visto.
- Teste de navegador: 52 de 52 verificações passaram (ver `docs/portal/TESTES-NAVEGADOR.md`, seção U).
- Pendências: conversas em grupo (mais de duas pessoas) não têm "Limpar histórico" no menu; o aviso nativo do bloqueio segue em inglês (configuração do contrib).

## 0.1.1-dev.27 — aviso no lugar da caixa de resposta para privacidade "só amigos" — 2026-10-15

Classificação: PATCH (texto e apresentação do aviso).

- Em conversas com quem só recebe mensagens de Amigos, quem não é amigo vê o aviso no lugar da caixa de mensagem e do botão Enviar: "Essa pessoa só recebe mensagens de Amigos, clique aqui para enviar um pedido de Amizade."
- Se o pedido já foi enviado: "Essa pessoa só recebe mensagens de Amigos, você já enviou pedido de amizade, aguarde."
- O bloqueio continua com os avisos próprios ("Você bloqueou essa pessoa, Clique aqui para desbloquear" e "Essa pessoa bloqueou você, por isso não pode enviar ou receber mensagens").
- Verificado no runtime de testes: aviso padrão, aviso após o pedido, e o texto na tela.

## 0.1.1-dev.26 — bloquear mantém a conversa visível e impede o envio — 2026-10-15

Classificação: MINOR (muda o comportamento de bloqueio do Private Message nesta instalação).

- Quem bloqueia um membro continua vendo a conversa na lista, e pode desbloquear pelas "Opções". Antes, o Private Message escondia a conversa de quem bloqueou, e o desbloqueio ficava inacessível pela gaveta.
- A troca é feita por `AcultaMessengerServiceProvider`, que troca a classe do serviço `private_message.mapper` por `MessengerPrivateMessageMapper`. A subclasse só muda `getBannedThreads()` (não exclui conversas por bloqueio). O serviço, os argumentos e a consulta de envio do contrib continuam os mesmos.
- O envio é recusado nos dois sentidos: quem bloqueou não envia (o formulário de resposta fica desabilitado, como já fazia o Private Message), e o bloqueado não envia ao bloqueador (regra que já existia). `PrivateMessageFormValidator::validateBlockedRecipients` ganhou a recusa para quem bloqueou, com mensagem em português.
- Verificado por POST no runtime de testes: a conversa segue na lista do bloqueador; o envio é recusado; "Desbloquear" restaura o formulário de resposta.
- Pendência: o aviso que o Private Message mostra a quem bloqueou, "User is unable to receive your message", vem da configuração `private_message.settings:ban_message`, que não é exportada no repositório. Trocar o texto exige decisão sobre a configuração versionada.

## 0.1.1-dev.25 — "Opções" da conversa: amizade, bloqueio e menu — 2026-10-15

Classificação: MINOR (novas ações na conversa: amizade e bloqueio; menu novo).

- "Opções" é um menu em sanfona, à direita da barra da conversa, na mesma linha de "← Todas as conversas". No celular aparece só o ícone de hambúrguer.
- Ações do menu, conforme o estado da relação com o outro membro: Adicionar amigo; Cancelar pedido de amizade (pedido enviado por mim); Aceitar pedido de amizade e Rejeitar pedido de amizade (pedido recebido); Desfazer amizade; Bloquear e Desbloquear.
- Ordem do menu: ações de amizade, "Limpar histórico", e por último Bloquear/Desbloquear.
- Cada ação roda por POST na rota `aculta_messenger.drawer_relationship` e a gaveta recebe a conversa atualizada, com a mensagem do resultado (por exemplo, o limite de pedidos).
- O formulário de Opções entra nas exceções do Turnstile (`aculta_messenger_conversation_relationship`, ponto desativado em `config/sync`).
- Correções encontradas no caminho: o botão de ação só roda se foi enviado (o Form API cai no primeiro botão quando o enviado não é encontrado); a ação é processada com a entrada da requisição e não com o cache de formulário.
- Testado no Firefox headless em 1280 e 390 px (ordem do menu, alinhamento da barra, cada ação) e por POST com `curl` como o membro que envia e como o que recebe.
- Pendência: uma conversa com um membro bloqueado some da lista de conversas do Private Message, então "Desbloquear" não aparece pela gaveta; a conversa abre pelo endereço direto. Decisão pendente sobre onde desbloquear.

## 0.1.1-dev.24 — textos do Private Message em português — 2026-10-15

Classificação: PATCH (rótulos e placeholder).

- "Clear history" vira "Limpar histórico" (alter do link da conversa, `entity_view_alter`).
- O rótulo do campo de membros ("Member(s)") vira "Participantes", no próprio campo base (`entity_base_field_info_alter`), então a conversa e os formulários mostram o mesmo nome.
- O placeholder do campo de resposta ("Message") vira "Escreva sua mensagem". O `text_format` copia o placeholder para o textarea no `#process`, por isso ele é definido no elemento de origem.
- Testado no Firefox headless em 390 e 1280 px.

## 0.1.1-dev.23 — "← Todas as conversas" no topo da gaveta; erros do envio visíveis — 2026-10-15

Classificação: PATCH (posição do link de volta e exibição de erros na gaveta).

- O link "← Todas as conversas" fica no topo, à direita, abaixo do X, em todas as etapas: conversa aberta, Nova conversa, compor e resposta de envio. A conversa aberta não o coloca mais depois do "Clear history" (o hook de entidade deixou de adicioná-lo; a gaveta o põe antes da conversa).
- A etapa de compor lista os erros do formulário (por exemplo o limite de envio) dentro da própria gaveta, com `role="alert"`. Antes o erro só aparecia como borda vermelha na caixa de mensagem.
- Os formulários do Messenger (`aculta_messenger_new_conversation` e `private_message_add_form`) estão nas exceções do Turnstile do Portal (ver `aculta_portal` 0.4.0-dev.24).
- Testado no Firefox headless em 1280 e 390 px: conversa aberta, lista, Nova conversa, compor, envio e erro de limite.
- Pendência: o widget do Turnstile não era montado no fragmento da gaveta; com a exceção o desafio não aparece mais nesses formulários.

## 0.1.1-dev.22 — "← Todas as conversas" alinhado à direita, abaixo do X — 2026-10-15

Classificação: PATCH (posição do link de volta na gaveta).

- O link "← Todas as conversas" da conversa aberta e o da resposta de envio ficam alinhados à direita, logo abaixo do X da gaveta, com o mesmo tamanho de fonte.
- A resposta de envio usa a mesma classe `aculta-messenger-back-link` que a visão da conversa já usava.
- Estilo em `components/content/messenger-item/messenger-item.css` do tema (alteração do tema sem versão própria, ver pendência do tema).
- Medido no Firefox headless: borda direita do link a 8 px da borda do X em 1280 e 390 px, tamanho de fonte inalterado (13 px).

## 0.1.1-dev.21 — "Iniciar nova conversa" continua visível ao voltar da conversa — 2026-10-15

Classificação: PATCH (correção de um caminho de navegação da gaveta).

- O "Voltar" de uma conversa carrega a lista pela rota `drawer_conversations`, que não tinha o botão "Iniciar nova conversa" (só a primeira janela, `preview`, tinha). Os dois caminhos agora usam a mesma ação, em `newConversationAction()`.
- Testado no Firefox headless (geckodriver): abrir a barra, abrir uma conversa, voltar, e o botão aparece. Captura da lista após voltar conferida.

## 0.1.1-dev.20 — "Iniciar nova conversa" funcionando de ponta a ponta — 2026-10-15

Classificação: PATCH (correção do fluxo da 0.1.1-dev.19, que não tinha sido testado no navegador).

- O botão "Iniciar nova conversa" é um botão de verdade (elemento de botão com classes do tema), e não um texto de link.
- O campo de busca de membros recebe a classe que o script de busca procura, e o script passa a ser carregado em todas as páginas de membros por `hook_page_attachments` (antes era anexado a um array de região, que não chegava à página).
- Envio da primeira mensagem por `fetch` dentro da gaveta: o AJAX do Form API não funciona em fragmento carregado por `fetch`, porque depende de `drupalSettings`. O formulário desativa o redirecionamento do Form API, e a gaveta recebe a conversa como resposta.
- Correção no envio: o Private Message recebe entidades de usuário em `getThreadForMembers()`, não IDs.
- Cancelar e Voltar para o início são elementos de link com os atributos que a gaveta usa; o `toRenderable()` antigo descartava esses atributos e fazia a gaveta carregar a página inteira.
- A rota de compor aceita POST (`methods: [GET, POST]`).
- Tema: `messenger-drawers.js` trata o envio do formulário de nova conversa e aceita o botão como ação de abrir a etapa. A alteração do tema não tem versão própria neste commit (ver o changelog do tema).
- Testado no Firefox headless (geckodriver) nas larguras de 1280 e 390 px: passos completos do fluxo, Cancelar, Voltar para o início e envio. Roteiro e resultados em `docs/portal/TESTES-NAVEGADOR.md`, seção T.
- Pendências: a visão nativa da conversa mostra "Clear history" e "Member(s)" em inglês (já existia); toque em aparelho real e leitor de tela não testados.

## 0.1.1-dev.19 — "Iniciar nova conversa" na barra de mensagens — 2026-10-15

- Novo botão "Iniciar nova conversa" na primeira janela da barra de mensagens. Ele abre, por AJAX, um campo de busca de membros que funciona como a busca do cabeçalho: resultados interativos a partir de 3 caracteres, o mesmo atraso e a mesma dica de caracteres mínimos, sem abrir a página de busca completa.
- Ao escolher um membro, a etapa seguinte mostra a caixa de mensagem com "Enviar mensagem", "Cancelar" e "Voltar para o início". O envio usa o formulário nativo do Private Message, com as mesmas validações de bloqueio e privacidade de novas conversas.
- Rotas: `aculta_messenger.new_conversation` (`/mensagens/nova`), `.new_conversation_search` (`/mensagens/nova/buscar`) e `.new_conversation_compose` (`/mensagens/nova/{user}/painel`). Exigem membro com permissão de mensagens e acesso a perfis.
- Busca de membros: o `NewConversationController` limita a 10 resultados e exige 3 caracteres. A paridade exata de limite e de atraso com a busca do cabeçalho ainda não foi comparada lado a lado no navegador.
- O script de busca do cabeçalho (`aculta_portal/search-preview`) passa a ser carregado nas páginas de membros, porque a nova etapa o reutiliza.
- RUNTIME STATUS: rotas verificadas por HTTP no runtime de testes (etapa de busca com 0, 2 e 5 caracteres; etapa de compor). Interação no navegador e envio ainda não verificados; ver `docs/portal/TESTES-NAVEGADOR.md`.

## 0.1.1-dev.18 — quem pode iniciar conversas em Privacidade; e-mail só em notificações — 2026-10-15

- A pergunta "Quem pode iniciar uma conversa com você?" e a nota de bloqueio saem de Notificações por e-mail e vão para Privacidade, no grupo "Mensagens privadas". O Messenger acrescenta o grupo ao formulário de privacidade por `form_alter`; o `aculta_user` não conhece essa regra.
- A preferência é gravada pelo serviço do Messenger (`aculta_messenger.message_privacy_form`), no mesmo dado que a validação de novas conversas lê.
- Notificações por e-mail ficam só com o controle de e-mail e o texto de assinaturas de tópicos.
- Correções encontradas no caminho: o atributo `Autowire` usado nos hooks novos vinha de um namespace que não existe no Core (`Drupal\Core\DependencyInjection\Attribute`); trocado pelo namespace do Symfony. A aba "Notificações por e-mail" passa a aparecer sempre (para quem pode enviar mensagens), mesmo com notificações desligadas no site.
- RUNTIME STATUS: ACTIVE no runtime de testes (salvamento verificado pelo navegador e pelo banco).

## 0.1.1-dev.17 — mensagens só pela barra; preferências de e-mail em Configurações — 2026-10-15

- Páginas nativas do Private Message fora da experiência do membro: a caixa de entrada redireciona para a página inicial da conta e a página de conversa responde 404. Os formulários de excluir, limpar histórico e bloquear continuam; a página de nova mensagem (usada pelo botão do perfil) continua até o início de conversa existir na barra.
- Preferências de e-mail voltam como aba "Notificações por e-mail" em Minha conta > Configurações (`/configuracoes/email`). A aba aparece para quem pode mensagens, e o formulário mostra os controles desabilitados quando as notificações estão desligadas no site.
- Notificações de e-mail seguem desligadas no runtime de teste (`enable_notifications` ausente). Ligar exige decisão, porque envia e-mails reais.
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.1.1-dev.16 — mensagens saem da Minha conta; permissões das rotas corrigidas — 2026-10-15

- Mensagens passam a existir só na barra multidomínio. Saem da Minha conta a seção, a aba de configurações de comunicação, as páginas de conversas, avisos e notificações (rotas removidas) e o formulário de preferências. As preferências de e-mail (`private_message.receive_notification`) continuam salvas.
- O link de volta das conversas aparece só no drawer. O e-mail de nova mensagem leva à página inicial da conta.
- Correção: as rotas de mensagens usavam `, ` em `_permission`, que o Core lê como E com espaços. Membros com as permissões recebiam 403 em Mensagens. Corrigido para a sintaxe do Core.
- Teste de ownership das notificações removido com a página que testava.
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.1.1-dev.15 — correções da revisão de código do Messenger — 2026-10-15

Classificação: PATCH (correções; sem API nova).

- Janelas de início e fim de avisos são lidas em UTC (`StorageDateTime`). Antes, o fuso do usuário mudava o instante em até 3 h.
- Visitantes voltam a ver o gatilho de Site Alerts; só o gatilho de mensagens é restrito a membros.
- Regra única de não lida (`UnreadThreadCounter::isUnread`) para badge da conta, cabeçalho, lista e indicador da thread. Threads sem atualização desde o último acesso não carregam mensagens.
- `MessengerCounts`: contagem de alertas e de avisos não lidos em um só serviço; avisos contados por consulta.
- Aviso de tópico sem alias é reenfileirado com registro, em vez de descartado.
- Retenção de seis meses remove as notificações das mensagens antes de apagar a conversa. A política de apagar para os dois membros não mudou.
- Leitura de conversa pelo painel só acontece em requisição do próprio site; cross-site e prefetch recebem 204.
- Painel de mensagens (tema): listeners do menu são removidos ao fechar e o offcanvas desanexa comportamentos antes de substituir o conteúdo.
- Validação: 23 testes unitários do Messenger OK; verificações no runtime de teste (home anônima, conta, avisos, prévia). Interação do menu no navegador pendente. RUNTIME STATUS: ACTIVE no runtime de testes.
- Entrega: PR de pendências do Messenger (chore/messenger-pendencias).

## 0.1.1-dev.14 — validação visual da central de avisos — 2026-10-15

Classificação: PATCH (marcação de apresentação do módulo; sem mudança de acesso).

- A descrição de cada item da visão geral de Avisos é um parágrafo próprio, e não texto solto após o link. Antes ficava colada ao título no desktop e no mobile.
- Validação visual no runtime de teste com Chromium headless (desktop 1280 e mobile 390): visão geral, avisos temporários, persistentes, criação de persistente e caixa de mensagens da conta. Sem erro de PHP nas páginas.
- Pendência menor: os botões "Criar" da visão geral ficam com alinhamento levemente diferente no mobile (margem do tema).
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.1.1-dev.13 — consolidação com a main e central administrativa de avisos — 2026-10-15

- Consolida o trabalho preservado do checkout de teste sobre a main (PR #284) e porta a central Avisos em `/painel-administrativo/avisos`, com abas temporários (Site Alert nativo) e persistentes (`aculta_notice`).
- Correções de carregamento: propriedades readonly que colidiam com `ControllerBase`/`FormBase`; tipos inexistentes no Core (`path_alias`, `Component` TimeInterface, Social FriendshipManager, `QueueFactory`); aliases de autowire para serviços de contrib.
- Regressões da linha antiga restauradas: proteção de desinstalação de comunicados e recibos; trava de texto puro e rótulo de envio; acesso a comunicados fail-closed e cache até a validade; permissões de mensagens no papel authenticated.
- Nomes de campos e bundles até 32 caracteres (`aculta_msg_*`), alinhados ao runtime.
- Testes unitários versionados (17 testes, 65 asserções, OK).
- Atualização `aculta_user` 10002 (tabela `aculta_friendship`) aplicada no runtime após backup.
- Entrega: PR #284 (consolidação) e PR #285 (central de avisos). RUNTIME STATUS: ACTIVE no runtime de testes; validação autenticada do fluxo de mensagens pendente.

## 0.1.1-dev.12 — navegação de conversas no painel lateral — 2026-10-15

- Adiciona endpoints same-origin para carregar a lista e uma conversa no painel lateral.
- Acesso direto à thread exige permissão e associação do usuário à conversa.
- O formulário de resposta continua sendo o formulário nativo de Private Message; foi incluído retorno à lista no drawer.
- Verificações estáticas: PHP lint e `git diff --check` PASS; gates/runtime pendentes.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.11 — resposta e data na caixa de mensagens — 2026-10-14

- O modal de conversa mantém o histórico nativo do Private Message e garante o formulário de resposta nativo quando a exibição ativa não o inclui.
- A lista da barra lateral mostra a data e hora da mensagem mais recente dentro do botão do remetente, em markup explícito e fonte pequena.
- A data usa o timestamp da última mensagem; o timestamp da thread é fallback.
- `git diff --check`: PASS. PHP lint, gates e validação visual: não executados nesta etapa.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.10 — data da última mensagem no contato — 2026-10-14

- Cada botão de remetente mostra a data e hora da última atividade da conversa acima do nome, em tipografia pequena.
- A data usa o timestamp atualizado do Private Message; nenhuma dimensão do botão foi ampliada intencionalmente.
- Validação visual e gate Drupal: pendentes nesta etapa.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.9 — contatos compactos no Messenger — 2026-10-14

- A gaveta mostra uma caixa clicável por usuário com avatar circular e nome somente; abre a conversa mais recente.
- Ação contextual de limpar histórico e estado não lido permanecem disponíveis.
- CSS da caixa passa a ser carregado pela biblioteca da gaveta, inclusive no conteúdo AJAX sem renderizar o SDC de mensagem.
- Capturas visuais revisadas em desktop (1440×960) e mobile (390×844) com a resposta autenticada renderizada pelo checkout local.
- RUNTIME STATUS: visual local PASS; interação real da gaveta ainda DEFERRED.

## 0.1.1-dev.8 — menu contextual e retenção de conversas

- Inclui menu de contexto no clique direito e atalho acessível para abrir a confirmação nativa de limpeza do histórico pessoal.
- Preserva a exclusão por participante do Private Message; esconde da lista a ação global de apagar thread.
- O Cron remove até 20 conversas sem atividade há seis meses por execução, usando o ciclo de vida da entidade nativa.
- Não executado Cron nem exclusão no runtime durante esta alteração.
- Validação no checkout local: `drush cr`, prévia autenticada e gates Portal/Foundation PASS; interação no navegador e expiração real ainda pendentes.
- RUNTIME STATUS: DEFERRED até sincronizar a nova versão com o checkout local; purge DEFERRED até execução futura do Cron.

## 0.1.1-dev.7 — conversas agrupadas por remetente

- A prévia organiza conversas em caixas por remetente e prioriza a conversa atualizada mais recentemente.
- O nome do remetente abre sua conversa mais recente; as linhas de conversa continuam abrindo os respectivos históricos nativos.
- A ordem é baseada no timestamp `updated` do Private Message; não há armazenamento paralelo.
- `php -l` e validação funcional autenticada: pendentes nesta etapa.
- RUNTIME STATUS: DEFERRED até aplicação/teste do commit no checkout local.

## 0.1.1-dev.6 — caixa visual de conversas e confirmação de avisos

- A gaveta de mensagens limpa a contagem de conversas não lidas ao abrir, usando o last-check nativo do Private Message.
- O item da inbox abre a thread autorizada em modal Drupal com histórico e Form API nativa para responder.
- Avatar autorizado e direção de envio chegam ao tema como render array e estado neutro; o SDC apresenta balões de entrada/saída.
- Site Alerts e comunicados podem ser dispensados individualmente; Flag mantém o estado por usuário e a contagem acompanha a dispensa.
- RUNTIME STATUS: DEFERRED nesta branch sem Runtime instalado; validar atualização de config e interação modal no Homelab.

## 0.1.1-dev.4 — fragmentos AJAX para gavetas Bootstrap

- Rotas same-origin de prévia retornam somente HTML renderizado e têm cache privado/no-store;
  a consulta ocorre ao abrir a gaveta, sem atualizações em segundo plano.
- RUNTIME STATUS: DEFERRED — conferir DI, cache e interação no Homelab.

## 0.1.1-dev.5 — contadores atualizados ao abrir as gavetas

- Prévia de conversas retorna o total atual de threads não lidas; prévia de avisos retorna Site Alerts visíveis mais comunicados persistentes ainda não lidos.
- O tema aplica a contagem e o rótulo acessível da barra na resposta da mesma requisição, sem polling.
- RUNTIME STATUS: DEFERRED — comportamento e contagem por conta precisam de teste HTTP autenticado.

## 0.1.1-dev.3 — gavetas de mensagens e avisos na barra

- A prévia privada passa a exibir até 50 conversas recentes dentro do painel e remove o link de saída
  para a caixa de mensagens.
- Avisos persistentes visíveis passam a exibir até 50 itens no painel junto com Site Alerts ativos.
- Os dois gatilhos permanecem restritos a contas autenticadas.
- Os endpoints dos painéis deixam explícito o cache desativado para que cada abertura Ajax consulte o estado atual.
- RUNTIME STATUS: DEFERRED — validação visual e funcional no Homelab pendente.

## 0.1.1-dev.2 — pedidos de amizade e privacidade de novas conversas

- `aculta_user` implementa amizade como entidade privada por par de membros, com estados pendente, aceita, recusada, cancelada e removida; API de serviço com DI, lock do Core e ID canônico único.
- Perfis públicos autenticados oferecem solicitar, cancelar e remover amizade por Form API/POST; `/mensagens/notificacoes` mostra pedidos recebidos e permite aceitar/recusar. O contador da barra inclui pedidos pendentes.
- Flood API limita novos pedidos de amizade a dez por membro por hora.
- Completa o índice do contrato Portal/tema para tokens de Messenger e leitura já usados pela integração.
- Preferência individual Todos/Somente amizades confirmadas salva em User Data pelo `aculta_messenger`; aplica-se somente ao início de thread. Administradores e editores ignoram essa preferência, nunca os bloqueios nativos.
- Atualização `aculta_user_update_10002()` instala a nova entidade em sites existentes.
- Composer audit revelou os advisories SA-CORE-2026-014/015/016 no Core 11.4.8; após dry-run, `composer.json`/lock atualizaram Core e `core-dev` para 11.4.10 e componentes Symfony compatíveis. `composer audit --locked`: PASS, sem advisories.
- Verificado: PHP lint das classes alteradas, YAML de metadados/serviços/permissões, Drupal 11+ gate (696), environment-address gate (1.595), composer validate e `composer audit --locked` sem advisories. O gate de slugs não iniciou: este worktree não contém `vendor/autoload.php`.
- RUNTIME STATUS: DEFERRED — sem instalação/atualização no Homelab, sem teste funcional entre usuários e sem envio de e-mails reais.

## 0.1.1-dev.1 — pesquisa e decisão de arquitetura

- Pesquisados Friendship, User Relationships, Flag, Private Message, Message e
  Message Notify em fontes oficiais; versões e código instalado do lock foram
  conferidos na branch local.
- Friendship declara compatibilidade com Drupal 11, mas não possui cobertura
  da política de advisories; sua implementação não atende diretamente à
  central privada de pedidos e notificações do ACULTA. User Relationships é
  legado para Drupal 7. Não instalar dependência adicional.
- Decidido implementar o workflow de amizade em `aculta_user` como entidade
  Drupal própria e serviço público; `aculta_messenger` consumirá essa API.
  Message/Message Notify mantêm notificações e Private Message mantém threads;
  Flag não será usado como máquina de estados. Os casos de uso do Friendship
  serão adaptados para os contratos DI, Entity API, access e Form API do Portal.
- Definida a preferência individual `todos`/`somente amigos`: limita somente
  novas conversas; respostas em threads existentes permanecem liberadas.
  Administradores e editores ignoram a preferência, mas não bloqueios nativos.
- RUNTIME STATUS: DEFERRED — somente documentação/pesquisa nesta subfase;
  implementação e validação seguem em dev.2/dev.3.

## 0.1.0-dev.10 — modais da barra e estado de leitura

- Adiciona modal same-origin de conversas e de avisos ativos na barra
  multidomínio, com ícones Bootstrap do tema e contador.
- A caixa de mensagens usa o estado nativo lido/não lido por conversa do
  Private Message. O resumo marca cada conversa acessivelmente e o contador
  continua sendo o total nativo de threads não lidas do membro.
- Cria o tipo editorial `aculta_notice`, segmentação OR pelos papéis de conta
  existentes, validade inicial/final e leitura individual pelo Flag não global
  `aculta_notice_read`. Acesso é verificado novamente ao listar e renderizar.
- Para membros, o contador do sino soma Site Alerts ativos e comunicados
  persistentes não lidos; para visitantes, conta Site Alerts ativos. Site Alert continua temporário e
  sem estado de leitura individual.
- Verificações estáticas: PHP lint, YAML parse, Drupal 11+ gate, SDC schema,
  fronteira módulo/tema, design foundations e endereços de ambiente: PASS.
- Gate de slug reporta apenas duas rotas upstream preexistentes do
  Message Subscribe. Composer audit: FAIL no Drupal Core 11.4.8, com três
  advisories publicados em 2026-10-14; sem atualização de Core nesta subfase.
- RUNTIME STATUS: DEFERRED; revisar AJAX/modal, cache e estado entre contas no
  Homelab.

## 0.1.0-dev.9 — integração visual ACULTA420

- O ACULTA420 apresenta listas, mensagens, notificações, composição nativa,
  Site Alert, estado vazio e contador de não lidas por componentes SDC.
- Formularios e entidades continuam sendo render arrays nativos do Drupal; o
  tema só os envolve e apresenta, sem autorização, storage ou lógica funcional.
- Componentes experimentais, claros/escuros por tokens compartilhados e sem
  JavaScript próprio. RUNTIME STATUS: DEFERRED; validação visual no Homelab
  ainda pendente.
- Drupal 11+ gate (696), SDC schema (21 componentes/334 checks), ACULTA420
  design foundations (1.816), module/theme boundary (907), unique paths,
  Portal UI docs, environment addresses (1.561) e PHP lint: PASS.
- Foundation gate que depende de bootstrap Drupal, Homelab, responsividade e
  acessibilidade visual: DEFERRED nesta cópia sem settings.

## 0.1.0-dev.8 — preferências de comunicação

- Adiciona `/configuracoes/mensagens` no purpose ACCOUNT e a aba Mensagens para
  contas com acesso nativo ao Private Message e avisos por e-mail habilitados.
- O opt-out lê e grava `private_message.receive_notification` através de
  `user.data`; o worker consulta a mesma preferência no momento do envio.
- O e-mail continua usando a ferramenta instalada no site, sem SMTP/transporte
  novo e sem conteúdo da conversa.
- Bloqueios continuam sendo ações nativas por membro; assinaturas permanecem
  por tópico na flag existente `seguir_topico`. Não foi criado storage paralelo.
- Preferências de comunicados persistentes, restrição global de mensagens e
  controles de eventos ainda inexistentes aguardam mecanismos e política.
- RUNTIME STATUS: DEFERRED — sem execução no Homelab ou teste entre contas.

## 0.1.0-dev.5 — assinaturas de tópicos do fórum

- Reutiliza a flag existente `seguir_topico` em Nodes `forum`; não cria outra
  flag e não ativa `message_subscribe_ui`.
- Enfileira a primeira publicação; o worker revalida publicação atual e gera
  link pelo purpose `forum` via `DomainPurposeManager`. Sem alias português, não
  envia link com slug técnico.
- Seleção de destinatários usa a API pública de Flag; Message Subscribe aplica
  status de conta e acesso atual do Node.
- Evento Message registra Node, revisão, estado e tentativas. Lock e chave de
  deduplicação protegem reprocessamento; erros deixam evento pendente para retry.
- A entrega passa pelo notifier configurado e ferramenta de e-mail já instalada
  via Drupal Mail; nenhum transporte SMTP novo é criado.
- Group permanece fora: `gnode` não está habilitado no projeto.
- RUNTIME STATUS: DEFERRED — teste de assinatura, access e transporte controlado
  aguardam Homelab; sem mudança de banco ou Config Sync.
- Verificações locais: PHP lint, YAML parse, gate Drupal 11+ (696 checks),
  endereços de ambiente (1.518 arquivos) e `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas de
  `message_subscribe_ui`; gate e allowlist permanecem intactos.

## 0.1.0-dev.6 — integração administrativa de Site Alert

- Adiciona a instância do bloco nativo Site Alert à região Destaques do ACULTA420,
  disponível nos purposes que usam esse tema. Agenda e severidade vêm da
  entidade Site Alert; o intervalo de atualização é 300 segundos.
- Criação, edição e remoção continuam nos formulários/permissões nativos do
  Site Alert; as rotas administrativas permanecem sob o purpose `main`.
- Nenhuma role recebe permissão de Site Alert automaticamente; não há grants
  atuais às permissões nativas na configuração exportada.
- Comunicados persistentes Node/Views não foram criados: os tipos atuais não
  fornecem segmentação de acesso verificável nem fluxo de revisão atribuído para
  esse propósito. Evita-se publicar um aviso como conteúdo público sem contrato.
- `config/sync/core.extension.yml` habilita `datetime_range` e `site_alert`, e
  `config/sync/block.block.aculta_messenger_site_alert.yml` coloca o bloco.
- RUNTIME STATUS: DEFERRED — o Config Sync não foi importado; bloco, agenda e
  apresentação ainda exigem teste no Homelab. Banco inalterado.
- Verificações locais: PHP lint, YAML parse, gate Drupal 11+ (696 checks),
  endereços de ambiente (1.519 arquivos) e `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas de
  `message_subscribe_ui`; gate e allowlist não foram alterados.

## 0.1.0-dev.7 — seção Mensagens na Minha Conta

- Cria `/mensagens`, `/mensagens/conversas`, `/mensagens/avisos` e
  `/mensagens/notificacoes`, todas no purpose `account`, com acesso autenticado
  e permissões nativas do Private Message.
- Conversas encaminha para a inbox contrib por URL ACCOUNT confiável. Contagem
  de não lidas usa `PrivateMessageServiceInterface`; a navegação só inclui a
  seção quando a conta já tem as permissões exigidas.
- Atualiza o link dos e-mails privados para a rota central `/mensagens` no
  purpose `account`.
- Notificações lista apenas Messages `sent` do bundle privado cujo owner UID é a
  conta atual. Query `accessCheck(FALSE)` justificada pelo ausência de handler
  de ownership no Message; UID é restrição obrigatória e resposta privada.
- Portal adiciona alters neutros de seções/subrotas ao `AccountShellBuilder`;
  rotas nativas Private Message são classificadas em `account`. Formulários,
  inbox e armazenamento permanecem contrib.
- Avisos é estado vazio até a implementação de comunicados persistentes.
- Verificações: PHP lint, YAML, Portal gate (696), module/theme boundary (873),
  unique paths, portal UI docs (167 tokens/132 usados/41 atributos) e
  environment addresses (1.523 arquivos): PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas do
  `message_subscribe_ui`. `validate-aculta-portal-conta-root.php` não inicia
  porque procura diretório `css/` ausente também em `origin/main`.
- Shell/Domain gates exigem bootstrap Drupal e não rodaram sem settings/runtime.
- RUNTIME STATUS: DEFERRED — fluxo de conta, acesso entre purposes e Config
  Sync aguardam Homelab; nenhum import foi executado.

## 0.1.0-dev.4 — avisos privados por Message Notify

- Enfileira eventos do hook nativo de nova mensagem e resolve a thread após o
  salvamento, usando Queue API e lock do Core.
- Grava notificações como entidades Message com referência ao Private Message de
  origem, destinatário e estado; retries consultam a entidade para deduplicação.
- Usa `message_notify.sender` e Drupal Mail já instalado, com assunto genérico,
  corpo sem conteúdo da conversa e link para a raiz do purpose `account`.
- Revalida usuário ativo, permissões, membership/access e bloqueios nas duas
  direções. Limita tentativas de entrega a três.
- Lifecycle guard bloqueia Private Message Notify em conjunto (template
  upstream inclui o corpo privado) e protege uninstall com notificações/fila
  pendentes.
- RUNTIME STATUS: DEFERRED — sem módulo habilitado, envio real ou validação no
  Homelab. Sem mudanças de banco ou Config Sync.
- Verificações estáticas: PHP lint, class loading/interface, YAML de instalação,
  gate Drupal 11+ (696 checks), endereços de ambiente (1.506 arquivos) e
  `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL por duas rotas de `message_subscribe_ui`
  dentro do pacote contrib recém-instalado. O submódulo de UI não é dependência
  nem será habilitado por esta fase; gate e allowlist permanecem intactos. A
  incompatibilidade precisa de solução antes da homologação/merge.

## 0.1.0-dev.3 — integração inicial de mensagens privadas

- Acrescenta callback OOP `#[Hook('form_alter')]` ao formulário nativo de
  criação/resposta do Private Message. O Form API contrib continua controlando
  destinatários, validação, salvamento e renderização.
- Acrescenta rate limit pelo serviço Flood do Core: dez envios válidos por UID
  em uma hora; validações que já falharam não registram envio.
- Mantém bloqueio, threads, entidades, caixa de entrada, contagem de não lidas e
  handlers de acesso no Private Message. Nenhuma permissão é concedida a role.
- Aplica bloqueio nos dois sentidos no formulário por meio do serviço de ban
  nativo, sem revelar a identidade do usuário bloqueador.
- RUNTIME STATUS: DEFERRED — módulo não habilitado; fluxo, fallback sem JS,
  isolamento entre usuários e callback Flood aguardam validação no Homelab.

## 0.1.0-dev.2 — módulo integrador e dependências

- Adiciona `aculta_messenger.info.yml` como submódulo opcional do Portal, com
  dependências explícitas nos cinco módulos integrados.
- Adiciona ao Composer as versões estáveis resolvidas: Private Message 3.0.7,
  Message 1.9.0, Message Notify 1.5.0, Message Subscribe 2.1.0 e Site Alert
  1.4.0. O dry-run indicou cinco instalações, nenhuma atualização/remoção.
- Código-fonte foi instalado em `web/modules/contrib` somente pelo Composer e
  não será versionado. Não ativou módulos, executou import/export de config ou
  alterou banco/runtime.
- Confirma o inventário upstream nos arquivos efetivamente instalados; ver
  `docs/INTEGRATIONS.md` e `docs/ARCHITECTURE.md`.
- E-mail reutiliza Drupal Mail com Symfony Mailer/Mailer Plus existente, pela
  ferramenta de entrega já instalada. Não instala SMTP nem cria transporte.
- Verificações: `composer validate` válido (com warnings preexistentes de
  constraints exatas), `composer audit` sem advisories, Drupal extension
  discovery PASS,
  gate do Portal PASS (692 checks), metadata YAML PASS e links Markdown PASS.
  Instalação Drupal em banco/runtime e PHP 8.5 permanecem pendentes.
- `git diff --check`: PASS.
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.1 — fundação arquitetural

- Documenta fontes de verdade, contratos de Domain, públicos, privacidade,
  dependências candidatas e plano incremental.
- Registra que as cinco dependências ainda não estão no lock nem instaladas.
- Nenhuma mudança funcional ou instalação Composer.
- STATIC: PASS — `git diff --check` e links Markdown internos (nove arquivos).
- RUNTIME STATUS: DEFERRED (sem código funcional).

### Classes e hooks da integração de amizade

- `FriendRequestsForm`: lista pedidos direcionados à conta atual e trata aceitação/recusa por Form API.
- `MessagesController::notifications()`: agrega os pedidos recebidos e notificações Message privadas existentes.
- `MessengerBarHooks::preprocessPage()`: soma pedidos pendentes ao contador privado da barra.
- `PrivateMessageFormHooks::formAlter()` e `PrivateMessageFormValidator::validateNewConversationPrivacy()`: aplicam a preferência apenas ao criar conversa.

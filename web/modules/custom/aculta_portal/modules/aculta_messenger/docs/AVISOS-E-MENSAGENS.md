# Avisos e mensagens no ACULTA Messenger

Status: documento de referência, atualizado em 2026-10-24. Descreve o que o módulo faz hoje e o que está pendente.
Decisões de origem: F-D21 (política em `docs/portal/AVISOS-E-NOTIFICACOES.md`, no repositório).

## 1. Dois sistemas, dois destinos

Este módulo contém dois sistemas que **não se misturam**. Nenhuma notificação de aviso usa a caixa de mensagens, e
nenhuma conversa usa o aviso. Seguir tópico ou outro conteúdo significa receber
avisos conforme acesso e preferências; isso não cria conversa nem adiciona
participantes a threads. Consulte [ARCHITECTURE.md](ARCHITECTURE.md) para o
contrato normativo.

| | Mensagens | Avisos |
|---|---|---|
| Para que serve | Conversa entre pessoas | Comunicar algo ao sistema, à pessoa ou ao site |
| Onde aparece | Caixa de conversas (Private Message) | Barra do site, lista de avisos e contadores |
| Quem envia | Pessoas | O sistema (comunicados, respostas em tópico seguido) |
| Entidade | `private_message` (nativa) | Comunicados `aculta_notice` (nó); Fórum `aculta_aviso`; adapters Message de LMS, Wiki, Loja, Doações e Amizades |
| E-mail | Pelo Message Notify, quando a pessoa ativa | Sem e-mail, resumo diário ou envio direto, conforme preferência individual e modos autorizados pelo administrador |
| Contadores | Não lidas por conversa (nativo) | `contador_site` por tópico; `MessengerCounts` |

Regra: uma resposta em tópico seguido é um **aviso**, não uma mensagem. A publicação de tópico também não gera mais
mensagem (ver seção 5).

Avisos no site são obrigatórios. Para Fórum, LMS, Wiki, Loja e Doações, a pessoa
escolhe sem e-mail, resumo diário ou envio direto. Mensagens privadas oferecem
sem e-mail, envio genérico direto ou resumo semanal; amizade oferece sem e-mail
ou envio direto. Em Configurações do Messenger, administradores podem restringir
os modos disponíveis por categoria; não podem remover a opção sem e-mail nem
desligar a entrega no site. O ACULTA Mailer continua dono do transporte e pode
suspender tipos de mensagem conforme sua política.

O Messenger só permite preferências nas categorias registradas para Fórum,
LMS (`aprendizagem`), Wiki, Loja, Doações, mensagens privadas e amizade. Categoria desconhecida falha fechada
para e-mail e não é gravada em `user.data`; integrar uma nova origem exige
adicionar explicitamente sua categoria e cobertura de teste.

A aba Avisos da central da conta e a gaveta do cabeçalho filtram a lista
compartilhada por origem (incluindo Amizades) e estado de leitura. A origem é marcada pelos
produtores centralizados no Messenger depois de revalidar o acesso. Os links de
abertura e retorno mantêm os filtros selecionados. Na gaveta, links carregam o
fragmento no próprio painel; seus destinos apontam para a central da Conta como
fallback funcional sem JavaScript.

Pedidos e aceites de amizade aparecem como avisos no site usando o `Message`
existente. O Messenger mantém estado lido/não lido e contagem por destinatário;
os textos são genéricos e não expõem nomes de terceiros. Esse canal é obrigatório
e não depende da preferência de e-mail.

Na lista sem filtro, os avisos aparecem agrupados por origem, com cabeçalho e
separador visual. Dentro de cada origem, usa-se a ordem mais recente disponível:
`changed` no Fórum, `created` nos adapters Message e ID decrescente nos Alertas
do Site, que não expõem data de criação. No Fórum, limpar os contadores de
leitura e confirmar o digest preserva `changed`, para que alterações de entrega
não façam avisos antigos parecerem recentes. Filtros e paginação são aplicados
antes de construir as seções.

## 2. Avisos por pessoa (`aculta_aviso`)

Um registro por **pessoa, tipo e tópico**. Tipos: `forum_resposta` (Comment ou post revisionável novo em tópico seguido) e
`forum_atualizacao` (revisão nova de tópico seguido). Cada campo:

| Campo | Função |
|---|---|
| `uid` | Pessoa que recebe o aviso (dona do registro) |
| `tipo` | Tipo do aviso (agrupamento: tipo + assunto) |
| `assunto_nid` | Tópico (assunto) |
| `titulo` | Título do tópico na época do aviso |
| `contador_site` | Respostas novas não lidas. Zera quando a pessoa lê o tópico |
| `contador_email` | Respostas ainda não enviadas no resumo por e-mail. Zera pelo resumo |
| `ultimo_comentario` | Maior CID já contado; preserva o marcador histórico e auxilia transferência do agregado. A idempotência usa o ledger `aculta_messenger_forum_notice_event`, com chave por destinatário/tipo/ID de evento |
| `ultimo_post` | Último ID de revisão de `ForumPost` contado. Watermark separado evita colisão com CIDs de Comments e acompanha a ordem de publicação, mesmo quando a moderação aprova posts fora da ordem de criação |
| `ultimo_evento` | Última revisão contada (tipo atualização). Garante que cada revisão conta uma vez |

Regras (implementadas em `Aviso\AvisoService`):

- Quem segue o tópico (flag `seguir_topico`) recebe o aviso. A autoria da própria resposta não recebe aviso dela.
- Só Comment ou `ForumPost` **publicado** conta. Comment pendente conta na aprovação; post pendente conta quando o workflow o publica pela primeira vez.
- Uma revisão posterior de um post já publicado não cria outro aviso de resposta.
- Cada evento de Comment, revisão de `ForumPost` ou tópico publicado é contado uma vez pela chave única `(uid, tipo, id_evento)`. A ordem de processamento não descarta eventos antigos. `ultimo_comentario`, `ultimo_post` e `ultimo_evento` permanecem máximos históricos, não chaves de idempotência. Lock por destinatário/tópico/tipo serializa os incrementos do agregado.
- Uma primeira publicação de post revisionável nunca conta duas vezes: o ID
  da revisão publicada é comparado somente com `ultimo_post`. Edições,
  sincronizações e republicações de post já publicado anteriormente não geram
  outra resposta.
- Ler o tópico (requisição à rota canônica do nó, usuário autenticado) zera `contador_site` de **todos os tipos**
  daquele tópico.
- Atualização de tópico seguido (fila de publicação) cria aviso `forum_atualizacao`, sem mensagem.
- O aviso é visível só para a própria pessoa (`AvisoAccessControlHandler`). Administrar avisos exige
  `administer aculta avisos`.

### 2.1 Elegibilidade e privacidade do contexto

- A integração de seguir/notificar usa exclusivamente tópicos publicados do bundle `aculta_forum_topic`, Comments
  publicados `aculta_discussion_post` e posts publicados/default do bundle `aculta_forum_post`. O bundle Drupal Forum
  contribuído `forum` não participa desse fluxo.
- Tópico com autoria histórica em `field_autor_legado` e eventos de sincronização são excluídos.
- Antes de criar um aviso e antes de mostrá-lo, contá-lo ou incluí-lo no resumo por e-mail, o Messenger carrega a conta
  ativa e revalida acesso de visualização no purpose `forum`, incluindo a atribuição explícita ao Domain público do Fórum.
- Comments e posts exigem acesso ao tópico e à resposta correspondente na criação do aviso. Se o acesso deixar de existir, o aviso sai das listas e
  contadores; seu contador pendente de e-mail é zerado antes de montar o resumo.
- O serviço de acesso está em `Aviso\ForumNoticeAccessPolicy`; não depende do módulo Forum nem cria uma segunda fila ou
  entidade. Domain Access determina o acesso ao nó no domínio ativo, então a revalidação executa no purpose correto.

## 3. Funções do módulo por camada

Todas as classes ficam em `src/`. O módulo não cria engine de mensagens nem transporte próprio.

**Mensagens (conversas e entrega de mensagens)**

- `Controller/MessagesController`, `NativePrivateMessageController`, `NewConversationController`: telas de conversa.
- `Form/NewConversationForm`, `PrivateMessageFormValidator`, `FriendRequestsForm`: formulários de conversa e pedidos.
- `Service/ConversationPolicy`, `ConversationMessagingState`: política de novas conversas; bloqueio bilateral em threads existentes.
- `Service/UnreadThreadCounter`: contagem de conversas não lidas.
- `Hook/PrivateMessage*Hooks`: formulários, respostas, retenção e apresentação das mensagens.
- `Plugin/QueueWorker/PrivateMessageNotificationWorker`: entrega aviso genérico direto por e-mail ou registra o aviso mínimo para o digest semanal. O e-mail contém o nome do remetente e o link da inbox, nunca o conteúdo da mensagem; acesso, bloqueios, confirmação e preferência são revalidados.
- `Aviso/PrivateMessageDigestProvider`: contribui registros `Message` pendentes ao digest compartilhado; respeita a data âncora administrativa e o intervalo de sete dias por conta, revalida acesso à thread e marca somente os itens incluídos.
- `Mapper/MessengerPrivateMessageMapper`: mapeamento de dados para o tema.

**Avisos (comunicados, alertas e avisos por pessoa)**

- `Controller/NoticeAdminController`, `Support/NoticeType`, `Access/PersistentNoticeRouteAccess`: comunicados
  `aculta_notice` (segmentados por papel, com validade).
- `Service/MessengerCounts`: contagens de alertas e comunicados não lidos, lidas pela barra do site.
- `Hook/NoticeAdminHooks`, `EventSubscriber/NoticeAdminRouteSubscriber`: administração dos comunicados.
- `Entity/Aviso`, `Entity/AvisoAccessControlHandler`: entidade do aviso por pessoa.
- `Aviso/AvisoService`: regra de contagem, deduplicação e leitura dos avisos por pessoa.
- `Hook/ForumAvisoHooks` envia Comments publicados/aprovados e primeira
  publicação de ForumPost a `ForumNoticeEventProducer`; o evento passa pelo
  publisher compartilhado e `ForumNoticeEventSubscriber` resolve a origem,
  revalida destinatário/acesso e chama o agregador existente.
- `EventSubscriber/ForumAvisoReadSubscriber`: leitura do tópico zera o contador do site.

**Assinaturas e eventos**

- Na privacidade da conta, Messenger lista as Flags `seguir_topico` pertencentes à pessoa e permite removê-las em lote. Os nodes são carregados em um lote pela Entity API. O link e o título do tópico só aparecem se o acesso atual permitir; itens indisponíveis recebem rótulo genérico e ainda podem ser desassinados. O submit reconsulta as Flags da conta e só remove IDs que continuam pertencendo à pessoa, carregando os alvos selecionados em lote antes de usar `FlagServiceInterface::unflag()`. Não cria storage, não muda acesso nem apaga avisos históricos.

- `Hook/MessageSubscribeHooks`: aplica `message_subscribe_get_subscribers_alter` para remover destinatários de Message Subscribe em tópicos do ACULTA Fórum e Comments hospedados neles (F-D21); outros contextos mantêm os candidatos contrib. O hook aditivo `get_subscribers` não cancelava os destinatários do contrib.
- `Hook/MessageSubscribeEventHooks`, `Plugin/QueueWorker/MessageSubscribeEventWorker`: fila do evento de publicação
  de tópico. A fila conserva o nome histórico, mas encaminha atualizações pelo
  evento compartilhado e não chama a entrega de Message Subscribe.

**Integração com o Portal e o tema**

- `Hook/MessengerBarHooks`, `Hook/PrivacyFormHooks`, `Hook/ProfileOptionsHooks`, `Hook/SettingsTabsHooks`: barra,
  privacidade e abas de configuração.
- `EventSubscriber/PrivateMessageAccountRouteSubscriber`, `NativePrivateMessagePagesSubscriber`: rotas nativas.
- `Form/MessengerSettingsForm`, `CommunicationSettingsForm`, `MessagePrivacyForm`: configurações do módulo.
- `Support/ConfirmDialog`, `OptionsDisclosure`, `StorageDateTime`: suporte de interface e datas.

## 4. Permissões

- `administer aculta messenger`: limites, limpeza e grupos do chat.
- `administer aculta avisos`: administrar avisos por pessoa (sem conteúdo de mensagens).

## 5. Contador do ícone de avisos

O ícone de avisos da barra mostra: alertas do site + comunicados não lidos + **avisos por pessoa não lidos**
(`AvisoService::naoLidosTotal`): avisos do Fórum são grupos tipo + assunto com
contador > 0; consumers pessoais centralizados para LMS, Wiki e Loja somam registros
acessíveis não lidos pelo alter de contagem. O contador de conversas
(outro ícone) não inclui avisos.

## 6. Controles individuais (Minha conta > Privacidade, bloco "Avisos")

- Os avisos no site são **sempre exibidos**: não há opção para desligá-los.
- **Resumo diário por e-mail**: desligado por padrão. Quando ligado, entram respostas/atualizações do Fórum conforme
  os controles abaixo e itens de módulos integradores que fornecem linhas pelo `AvisoDigestProviderInterface`.
  Ao desligá-lo, os adapters de conteúdo (Fórum, LMS, Wiki, Loja e Doações) não enviam digest; a agenda semanal
  independente de Mensagens Privadas continua obedecendo à preferência própria dessa categoria.
  Preferências em `user.data` (`aculta_messenger`).
- Desligar o resumo diário zera as pendências de e-mail dos adapters de conteúdo sob o lock do agregado; os avisos no
  site e a agenda semanal independente de Mensagens Privadas não são alterados.

## 7. Resumo diário por e-mail

- Roda no cron (`AvisosResumoHooks::cron`) e chama `AvisosResumo::enviarResumos()`.
- Envia para contas **ativas, com e-mail e e-mail confirmado** (`EmailConfirmationPolicy`), com o resumo ligado.
- Um e-mail por conta a cada 20 horas, no máximo, quando houver item de qualquer provider ativo.
- Linhas do Fórum são agrupadas por tópico; providers podem acrescentar avisos revalidados. Pendências são confirmadas
  **somente após envio com sucesso**. O resumo captura a quantidade/IDs incluídos e confirma apenas esse lote; eventos
  chegados durante o transporte ficam pendentes para o próximo digest. O contador de e-mail do Fórum é descontado sob o
  mesmo lock usado pelos produtores, sem sobrescrever incrementos concorrentes.
- Exceção de transporte mantém o lote pendente para retry e não interrompe os outros destinatários do cron. O log não
  inclui destinatário, conteúdo do aviso nem texto da exceção.
- `AcultaMailerInterface` envia o modelo `avisos_resumo`; não usa `hook_mail`.
- Os envios diretos de Fórum, LMS, Wiki, Loja e Doações revalidam estado ativo, modo atual, endereço e a política
  `EmailConfirmationPolicy` imediatamente antes da entrega. Se a confirmação ou preferência deixar de valer, somente o
  e-mail é cancelado; o aviso obrigatório no site permanece no storage de origem.
- A correlação do digest é um UUID v5 com chave HMAC pelo `hash_salt`, baseado no UID, destinatário e identificadores/tipos/quantidades do lote; títulos e conteúdo ficam fora. Na recuperação, o Messenger valida correlação, tipo e destinatário contra o hash mantido no Mail Log. Um retry confirma os IDs sem novo envio somente quando o destinatário atual corresponde ao aceite registrado. O snapshot não persiste o endereço. O reconhecimento entre Forum e adapters roda na transação padrão compartilhada; erro de qualquer confirmação reverte todas. A deduplicação Mailer depende do Mail Log ativo e persistido; não oferece atomicidade com o provedor externo.
- Os adapters ACULTA de digest implementam `AvisoDigestProviderIdentityInterface` e declaram uma chave estável (`lms`, `wiki`, `commerce`, `donation`). Manifestos novos persistem essa chave, não o nome da classe PHP, para tolerar refatorações e proxies do container sem perder o reconhecimento de aceite. Providers existentes que implementam somente `AvisoDigestProviderInterface` continuam funcionando pelo fallback de classe; snapshots anteriores com `class` também são lidos durante a transição.

## 8. Funções do módulo, resumo dos novos componentes

- `src/Entity/Aviso.php`: entidade `aculta_aviso`. `AvisoAccessControlHandler`: a pessoa vê só os próprios avisos.
- `src/Aviso/AvisoService.php`: revalidação e regras de contagem (`isNewComment`, `isNewRevision`), `registrarResposta`,
  `notificarAtualizacao`, `marcarLido`, `naoLidosTotal`.
- `src/Aviso/AvisoPreferencias.php`: preferências individuais de e-mail.
- `src/Aviso/AvisosResumo.php`: envio do resumo diário combinado. Providers tagged `aculta_messenger.aviso_digest_provider`
  fornecem linhas com IDs de entrega e confirmam somente os itens incluídos após envio bem-sucedido.
- `src/Form/AvisosPrivacyForm.php` + `src/Hook/AvisosPrivacyHooks.php`: bloco "Avisos" na privacidade da conta.
- `src/Aviso/ForumNoticeEventProducer.php` e `src/EventSubscriber/ForumNoticeEventSubscriber.php`:
  adapter de publicação/consumo via `MessengerNoticeEvent`, preservando `aculta_aviso`
  e o ledger idempotente.
- `src/Hook/ForumAvisoHooks.php`: converte `comment_insert`/aprovação e eventos
  de ForumPost ao producer.
- `src/EventSubscriber/ForumAvisoReadSubscriber.php`: leitura do tópico.
- `src/Plugin/QueueWorker/MessageSubscribeEventWorker.php`: atualização de tópico
  seguido é publicada no contrato compartilhado e consumida como aviso.
- `src/Hook/MessengerBarHooks.php`: soma os avisos por pessoa ao ícone de avisos.

## 8.1 Central "Avisos/Mensagens" em Minha conta

- Acesso à central: item **Avisos/Mensagens** na navegação de Minha conta (conta.aculta), com o contador de não lidos
  como badge (`AccountSectionsHooks`, pelos pontos `aculta_portal_account_sections` e `..._parent_sections`), e pelo link
  "Ver todos os avisos" da prévia. A barra multidomínio não mostra o rótulo textual: usa os ícones do aculta_messenger.
  A rota é `/avisos-mensagens` (`aculta_messenger.avisos_raiz`).
- Duas abas no padrão de Atividade e Meus Dados (`account-data-nav`/`account-data-link`), com a aba atual marcada:
  - **Avisos** (`/avisos-mensagens/avisos`): a **mesma lista da gaveta da barra** (`MessagesController::alertsBuild`):
    comunicados, avisos da pessoa e alertas do site, com os mesmos itens, links e ações de dispensa. Só lê os avisos da
    conta logada; a página não recebe parâmetro de usuário. Dentro da aba não há o link "Ver todos", porque a aba já é a
    lista completa; a gaveta da barra mantém o link (`alertsBuild(FALSE)` na aba).
    Quando os filtros não retornam itens, a lista mantém um estado vazio no próprio resultado, com `role="status"` e
    estilo neutro do contrato `messenger-state`; a mensagem é decidida pelo módulo segundo os filtros ativos.
  - **Mensagens** (`/avisos-mensagens/mensagens`): a **mesma lista de conversas da gaveta da barra**
    (`MessagesController::conversasBuild`), com o mesmo botão de nova conversa e os mesmos links. As conversas continuam
    no sistema de mensagens; a aba não cria nem altera conversas.
- Clique em um aviso ou em uma conversa dentro da central abre o item completo **no mesmo lugar**, como na gaveta da barra:
  a área das abas é um contêiner `data-aculta-drawer-url` com `data-aculta-drawer-content`, e o script da gaveta
  (`messenger-drawers.js`) busca o fragmento e o mostra ali. O botão Voltar retorna à lista da gaveta. A biblioteca do
  script é anexada pelos gatilhos da barra; o módulo não anexa biblioteca do tema.
- Lista de conversas na conta: caixa proporcional (largura máxima de 42 rem, linha compacta). A regra fica no tema, no
  atributo de comportamento `data-aculta-account-list`, e não vale para a gaveta da barra.
- Acesso: `access aculta portal`, no propósito `account`. Abas inexistentes respondem 404.
- A gaveta da barra multidomínio lista os avisos da pessoa (persistentes, alertas e do Fórum). O botão **Ver todos os
  avisos** fica no rodapé fixo da gaveta, abaixo da lista, com o estilo dos botões do site (mesma posição do rodapé do
  carrinho). A URL vem do Portal (`MessengerBarHooks`, chave `all_url`), gerada no purpose `account` pelo
  `DomainPurposeManager`; o tema só desenha o botão (`alerts-trigger`, prop `all_href`). Visitante não vê o botão.
  O contador do ícone de avisos soma esses avisos (seção 5).
- Visitante não vê a central (403).

## 8.2 Rótulo Lido / Não lido e retenção

Regra de apresentação e de vida útil, por tipo de aviso (decisão do responsável, 2026-10-16):

| Tipo | Rótulo Lido / Não lido | Regra temporal |
| --- | --- | --- |
| Alertas do site (`site_alert`, não persistentes) | sim: flag `aculta_site_alert_read`, marcado ao abrir o alerta | nenhuma |
| Avisos persistentes (`aculta_notice`) | não | nenhuma |
| Avisos do Fórum (`aculta_aviso`, respostas e atualizações) | sim: contador zerado ao ler o tópico | excluídos 6 meses após a criação (cron, `AvisoRetencaoHooks`) |
| Avisos de cursos (LMS) | sim, quando existirem | nenhuma |
| Conversas | sim, pelo estado de não lida da conversa | política de conversas (`ConversationPolicy`) |

- O rótulo fica à direita da caixa, em texto pequeno (`read-label` e `read-label-unread`), com fundo só atrás do texto.
- Avisos de cursos ainda não têm código neste repositório: quando existirem, devem usar o mesmo rótulo e não entram na exclusão.

## 9. Mudanças desta etapa e pendências

**Feito (2026-10-16):**

- Respostas e atualizações de tópico seguido viram avisos por pessoa, agrupados por tipo + assunto. Nenhuma
  mensagem é criada (conferido).
- Entrega de atualização por mensagem substituída por aviso (worker reescrito).
- Contador do ícone de avisos soma os avisos por pessoa.
- Bloco de controles individuais na privacidade da conta, e resumo diário por e-mail com as regras da seção 7.
- Testes unitários das regras de deduplicação e do intervalo. Testes no Runtime de teste: resposta, atualização
  (revisão repetida não conta), soma no ícone, leitura por todos os tipos, preferências, envio do resumo e zeragem
  ao desligar.

**Verificado com limite:**

- O envio real de e-mail não foi confirmado: o domínio de teste foi recusado pelo servidor SMTP (esperado). A
  entrega real precisa de teste com endereço real no Homelab.

**Pendente:**

- Contador de não lidos no item do menu da conta (o rótulo do menu é estático).
- Teste do worker de atualização com alias real (exige tópico com alias publicado).
- Limpeza do tipo antigo de mensagem `aculta_msg_forum_published` (configuração e registros antigos).

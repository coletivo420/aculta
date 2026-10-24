# Avisos e mensagens no ACULTA Messenger

Status: documento de referência, atualizado em 2026-10-19. Descreve o que o módulo faz hoje e o que está pendente.
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
| Entidade | `private_message` (nativa) | Comunicados `aculta_notice` (nó); avisos por pessoa `aculta_aviso` (entidade própria) |
| E-mail | Pelo Message Notify, quando a pessoa ativa | Resumo diário opt-in, somente com e-mail confirmado e contexto ainda acessível |
| Contadores | Não lidas por conversa (nativo) | `contador_site` por tópico; `MessengerCounts` |

Regra: uma resposta em tópico seguido é um **aviso**, não uma mensagem. A publicação de tópico também não gera mais
mensagem (ver seção 5).

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
| `ultimo_comentario` | Maior CID já contado; preserva o marcador histórico e auxilia transferência do agregado. A deduplicação individual usa a chave persistente (destinatário, CID) em `aculta_messenger_forum_comment_notice` |
| `ultimo_post` | Último ID de revisão de `ForumPost` contado. Watermark separado evita colisão com CIDs de Comments e acompanha a ordem de publicação, mesmo quando a moderação aprova posts fora da ordem de criação |
| `ultimo_evento` | Última revisão contada (tipo atualização). Garante que cada revisão conta uma vez |

Regras (implementadas em `Aviso\AvisoService`):

- Quem segue o tópico (flag `seguir_topico`) recebe o aviso. A autoria da própria resposta não recebe aviso dela.
- Só Comment ou `ForumPost` **publicado** conta. Comment pendente conta na aprovação; post pendente conta quando o workflow o publica pela primeira vez.
- Uma revisão posterior de um post já publicado não cria outro aviso de resposta.
- Cada Comment publicado é contado uma única vez por destinatário pela chave única `(uid, cid)`; aprovações fora da ordem de criação continuam contáveis. `ultimo_comentario` permanece o maior CID observado e não é a chave de idempotência.
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
- `Service/ConversationPolicy`, `ConversationMessagingState`: quem pode abrir conversa (privacidade e amizade).
- `Service/UnreadThreadCounter`: contagem de conversas não lidas.
- `Hook/PrivateMessage*Hooks`: formulários, respostas, retenção e apresentação das mensagens.
- `Plugin/QueueWorker/PrivateMessageNotificationWorker`: entrega, por e-mail, de mensagens privadas.
- `Mapper/MessengerPrivateMessageMapper`: mapeamento de dados para o tema.

**Avisos (comunicados, alertas e avisos por pessoa)**

- `Controller/NoticeAdminController`, `Support/NoticeType`, `Access/PersistentNoticeRouteAccess`: comunicados
  `aculta_notice` (segmentados por papel, com validade).
- `Service/MessengerCounts`: contagens de alertas e comunicados não lidos, lidas pela barra do site.
- `Hook/NoticeAdminHooks`, `EventSubscriber/NoticeAdminRouteSubscriber`: administração dos comunicados.
- `Entity/Aviso`, `Entity/AvisoAccessControlHandler`: entidade do aviso por pessoa.
- `Aviso/AvisoService`: regra de contagem, deduplicação e leitura dos avisos por pessoa.
- `Hook/ForumAvisoHooks`: `comment_insert`/`comment_update` e `aculta_forum_post_insert`/`aculta_forum_post_update`
  chamam o serviço ao publicar respostas.
- `EventSubscriber/ForumAvisoReadSubscriber`: leitura do tópico zera o contador do site.

**Assinaturas e eventos**

- `Hook/MessageSubscribeHooks`: retorna vazio para mensagens de assinatura do Fórum (F-D21); assinaturas do Fórum são avisos no site.
- `Hook/MessageSubscribeEventHooks`, `Plugin/QueueWorker/MessageSubscribeEventWorker`: fila do evento de publicação
  de tópico. Ainda existe; sua entrega por mensagem foi desligada.

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
contador > 0; adapters pessoais, como `aculta_lms_messenger`, somam registros
acessíveis não lidos pelo alter de contagem. O contador de conversas
(outro ícone) não inclui avisos.

## 6. Controles individuais (Minha conta > Privacidade, bloco "Avisos")

- Os avisos no site são **sempre exibidos**: não há opção para desligá-los.
- **Resumo diário por e-mail**: desligado por padrão. Quando ligado, entram respostas/atualizações do Fórum conforme
  os controles abaixo e itens de módulos integradores que fornecem linhas pelo `AvisoDigestProviderInterface`.
  Preferências em `user.data` (`aculta_messenger`).
- Desligar o resumo zera os contadores de e-mail pendentes, para não acumular um resumo antigo.

## 7. Resumo diário por e-mail

- Roda no cron (`AvisosResumoHooks::cron`) e chama `AvisosResumo::enviarResumos()`.
- Envia para contas **ativas, com e-mail e e-mail confirmado** (`EmailConfirmationPolicy`), com o resumo ligado.
- Um e-mail por conta a cada 20 horas, no máximo, quando houver item de qualquer provider ativo.
- Linhas do Fórum são agrupadas por tópico; providers podem acrescentar avisos revalidados. Pendências são confirmadas
  **somente após envio com sucesso**.
- `AcultaMailerInterface` envia o modelo `avisos_resumo`; não usa `hook_mail`.

## 8. Funções do módulo, resumo dos novos componentes

- `src/Entity/Aviso.php`: entidade `aculta_aviso`. `AvisoAccessControlHandler`: a pessoa vê só os próprios avisos.
- `src/Aviso/AvisoService.php`: regras de contagem (`isNewComment`, `isNewRevision`), `registrarResposta`,
  `notificarAtualizacao`, `marcarLido`, `naoLidosTotal`.
- `src/Aviso/AvisoPreferencias.php`: preferências individuais de e-mail.
- `src/Aviso/AvisosResumo.php`: envio do resumo diário combinado. Providers tagged `aculta_messenger.aviso_digest_provider`
  fornecem linhas e só confirmam entrega depois de envio bem-sucedido.
- `src/Form/AvisosPrivacyForm.php` + `src/Hook/AvisosPrivacyHooks.php`: bloco "Avisos" na privacidade da conta.
- `src/Hook/ForumAvisoHooks.php`: `comment_insert` e `comment_update` (aprovação de comentário).
- `src/EventSubscriber/ForumAvisoReadSubscriber.php`: leitura do tópico.
- `src/Plugin/QueueWorker/MessageSubscribeEventWorker.php`: atualização de tópico seguido vira aviso.
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

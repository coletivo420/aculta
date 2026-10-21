# ACULTA Messenger

Módulo de integração do ACULTA Portal para conversas privadas, eventos de
mensagem, assinaturas e avisos institucionais. A fonte de verdade de cada
capacidade permanece no módulo Drupal que a fornece; este submódulo compõe a
experiência e aplica os contratos de acesso e Domain do Portal.

## ACULTA Messenger 0.1.1

A subfase 0.1.1 implementa pedidos/aceites de amizade e uma preferência de
privacidade para novas conversas. Consulte [INTEGRATIONS.md](docs/INTEGRATIONS.md)
para a pesquisa comparativa e decisão sobre módulos contrib. Não há pacote
Composer adicional: o workflow usa uma entidade Drupal própria, notificações
ficam sob `aculta_user` (referência baseada no workflow do Friendship, adaptada
à API/segurança do Portal), o Messenger consome seu serviço público, notificações
usam a integração Message existente e threads continuam no Private Message.
`todos` é o padrão; `somente amigos` bloqueia apenas a abertura de novas
threads. Administradores e editores de conteúdo podem iniciar novas threads,
mas o bloqueio individual nativo do Private Message continua obrigatório.
Respostas em threads existentes não são restringidas pela preferência.

## Mensagens e avisos

Mensagens são conversas entre pessoas. Avisos são comunicações do sistema. A tabela e as funções de cada um estão em
[docs/AVISOS-E-MENSAGENS.md](docs/AVISOS-E-MENSAGENS.md).

## Escopo arquitetural

- Private Message mantém conversas, mensagens, participantes e suas regras de
  acesso.
- Message mantém eventos persistentes configurados como tipos de mensagem.
- Message Notify entrega mensagens por notificadores existentes e pela
  infraestrutura de e-mail do Drupal.
- O adapter opcional `aculta_lms_messenger` adiciona avisos de matrícula,
  certificado e conclusão à lista/contador global; conclusão entra no resumo
  diário compartilhado por provider tagged. Não cria central ou storage próprio.
- Message Subscribe e Flag mantêm assinaturas; assinar nunca concede acesso. Para tópicos de fórum, a entrega por mensagem foi desligada (F-D21); ver `docs/AVISOS-E-MENSAGENS.md`.
- Site Alert mantém alertas temporários globais.
- Comunicados persistentes são Nodes `aculta_notice`, segmentados por papéis
  Drupal existentes, moderados pelo workflow editorial e com validade opcional.
- A leitura individual dos comunicados usa a flag não global `aculta_notice_read`;
  o Private Message mantém seu estado nativo lido/não lido por conversa.
- `aculta_messenger` não cria engine de mensagens, transporte SMTP ou regras visuais.
  Exceção documentada: a entidade `aculta_aviso` guarda o aviso por pessoa de respostas em tópico
  seguido (F5.2). Avisos e mensagens são sistemas separados: ver `docs/AVISOS-E-MENSAGENS.md`.
- A prévia da barra agrupa conversas em caixas por remetente. Caixas e
  conversas são ordenadas pela atualização mais recente; o cabeçalho do
  remetente abre a conversa mais recente com essa pessoa, e cada conversa
  continua apontando para seu histórico/formulário nativos.
- Ao abrir a gaveta, `PrivateMessageServiceInterface::updateLastCheckTime()` é
  a fonte do estado lido/não lido; o badge zera pela regra nativa de last-check.
- A ação de cada conversa usa a rota canônica e o entity view completo do
  Private Message dentro do modal Drupal, preservando thread access e Form API.
- `PrivateMessagePresentationHooks::preprocessPrivateMessage()` prepara apenas
  avatar User API com access/cache e direção enviada/recebida. `ThemeHooks` mapeia
  esses dados para `aculta420:messenger-item`; CSS/Twig ficam no tema.
- O `Flag` não global `aculta_site_alert_dismissed` guarda dispensa por membro
  para Site Alert; `aculta_notice_read` continua marcando leitura dos Nodes.

## Compatibilidade e evidência

O `composer.lock` registra Drupal Core 11.4.10 e trava os cinco projetos nas
versões estáveis instaladas. Seus arquivos foram auditados após a resolução
Composer. O inventário e os limites da inspeção estão em
[INTEGRATIONS.md](docs/INTEGRATIONS.md).

O PHP CLI disponível nesta execução é 8.4.26. O projeto pede PHP 8.5; esta
máquina não comprova validação em PHP 8.5. Compatibilidade de cada dependência
com PHP 8.5 também não é declarada até examinar código e metadados instalados.
O transporte de e-mail já instalado é o caminho de entrega previsto: Drupal Mail
com Symfony Mailer/camada de envio E-Mails ACULTA. O Messenger não adiciona SMTP ou outro
transportador.

## Domain Purpose

Preferências de e-mail ficam em `/configuracoes/email` (Notificações por e-mail), na área ACCOUNT. O opt-out de
e-mail reutiliza `private_message.receive_notification`; a entrega passa pela
ferramenta de e-mail já instalada. Bloqueios e assinaturas usam os controles
nativos por membro e por tópico.

`account` é o único purpose da caixa postal e da experiência do membro.
`main` é o único purpose administrativo, inclusive para manutenção de Site
Alert e de comunicados. Outros purposes podem originar eventos e apontar para a
central de mensagens; não recebem caixas postais próprias. Rotas e URLs
cross-purpose usam `DomainPurposeManager`; mutações não atravessam hosts.

## Documentação

- [Arquitetura, fontes de verdade e decisões](docs/ARCHITECTURE.md)
- [Segurança, privacidade e permissões](docs/SECURITY.md)
- [Inventário de integrações e compatibilidade](docs/INTEGRATIONS.md)
- [Configuração e operação](docs/CONFIGURATION.md)
- [Roadmap](docs/ROADMAP.md)
- [Changelog](CHANGELOG.md)

## Estado da implementação

O módulo e os cinco módulos upstream estão selecionados em `core.extension`
para a próxima importação de Config Sync; a ativação no runtime não foi feita.
O formulário nativo
usa Flood para limitar envios; novos eventos são processados em fila e entregues
como Message Notify usando o mailer instalado, com um template que não expõe o
corpo privado. Inbox, threads, criação/resposta, contagem de não lidas,
bloqueio e access continuam pertencendo ao Private Message. As permissões
`use private messaging system` e `access user profiles` não aparecem na
configuração exportada de papéis; nenhuma concessão é feita pelo submódulo. A
política dos papéis precisa ser aplicada por configuração administrativa
revisada antes da ativação da experiência para membros. Homelab, fallback sem
JavaScript, isolamento real, instalação do bundle editorial e acessibilidade
do modal ainda não foram verificados.

Para assinaturas, dev.5 reutiliza a flag Drupal `seguir_topico` dos tópicos
`forum`. A publicação de tópico não gera mais mensagem: Message Subscribe não entrega para `forum`
(F-D21). Respostas viram avisos por pessoa (`aculta_aviso`), ver `docs/AVISOS-E-MENSAGENS.md`. A URL segue o purpose `forum` e a política de
slug em português. Assinaturas de Group não foram conectadas, pois `gnode` não
está ativo. O e-mail utiliza a infraestrutura já instalada pelo Drupal; a
entrega real aguarda teste controlado no Homelab.

O bloco de Site Alert é instalado na região `highlighted` do ACULTA420 e mantém
agendamento/severidade no módulo nativo. Seu escopo é sitewide para os purposes
que usam o tema. O ícone de avisos abre um modal Drupal same-origin: para membros
o contador soma comunicados persistentes não lidos e Site Alerts ativos; para
visitantes é a quantidade de Site Alerts ativos. O modal reúne alertas temporários e Nodes
visíveis, com ação Flag nativa para marcar lido/não lido.

A seção Mensagens da Minha Conta usa `/mensagens` e subrotas portuguesas no
purpose `account`. Conversas abre a inbox do Private Message; a navegação
condicional mostra a contagem de threads não lidas e cada thread da inbox exibe
estado individual. Notificações lista avisos privados entregues ao próprio
usuário; a aba `avisos` de `/avisos-mensagens` lista comunicados publicados e visíveis, marcáveis
como lidos/não lidos por conta.


## 0.1.1 — amizades e privacidade de mensagens

A entidade `aculta_friendship` e o serviço `aculta_user.friendship_manager` são
a fonte de verdade para pedidos e amizades. O Messenger integra ações na
página de perfil e pedidos recebidos na aba Mensagens de `/avisos-mensagens/mensagens`. A preferência
individual `message_privacy` usa User Data sob a chave `aculta_messenger`;
`friends` restringe apenas novas conversas. Respostas em threads existentes e
bloqueios nativos do Private Message conservam suas regras.

Administradores e editores de conteúdo podem iniciar novas conversas apesar
da preferência, mas continuam sujeitos ao bloqueio. Ver
[integrações](docs/INTEGRATIONS.md), [segurança](docs/SECURITY.md) e
[roadmap](docs/ROADMAP.md). RUNTIME STATUS: DEFERRED.

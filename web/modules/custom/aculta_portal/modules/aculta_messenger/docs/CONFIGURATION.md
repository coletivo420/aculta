# Configuração e operação

Documento de referência. Dono: `aculta_messenger` no ACULTA Portal. Revisão:
2026-10-24.

## Princípios

- Não ativar automaticamente contrib, Views, blocos, campos ou configuração de
  terceiros por instalação do Messenger.
- Não criar config própria até identificar opções de integração que não sejam
  configuração já fornecida por Core/contrib.
- Config exportável pode conter nomes, tipos e políticas, nunca tokens, senha,
  credencial SMTP ou segredo. Segredos, se uma integração futura realmente
  exigir, seguem Drupal Key provider `env` e o contrato de secrets do ACULTA.
- A entrega usa a ferramenta de e-mail já instalada: Message Notify chama o
  serviço de mail do Drupal, que usa o Symfony Mailer/camada de envio E-Mails ACULTA existente.
  Não configurar SMTP ou outro transporte novo nem adicionar dependência para
  isso.
- Administração de Site Alert e comunicados permanece em `main`.
- A central pessoal e suas configurações ficam em `account`.

## Fase atual

`aculta_messenger.info.yml` declara dependências no ACULTA Portal, nos cinco
módulos integrados e nos módulos Core necessários ao bundle de aviso. A
integração privada usa callback Form API, Flood e Queue API do Core,
configuração Message exportável e o serviço existente
`message_notify.sender`. As rotas são da central; o módulo acrescenta o tipo
de Node, uma decisão de node access e as permissões de Flag limitadas a
`aculta_notice_read`. `composer.json` e `composer.lock` adicionam somente os
cinco pacotes; versões em
[INTEGRATIONS.md](INTEGRATIONS.md).

O código de Private Message confirma que inbox, threads, criação, contagem de
não lidas e bloqueio já existem. A instalação não concede as permissões de uso
às roles: a revisão da política de papéis deve preceder a disponibilização do
envio de membros. O rate limit usa dez envios válidos por UID/hora através do
Flood do Core, sem nova tabela ou opção configurável. A configuração instalável
do Messenger adiciona um tipo Message, campos de referência/estado e modos de
visualização para deduplicação e email sem corpo privado. Enquanto houver
eventos persistidos ou itens na fila, o lifecycle guard impede a desinstalação.

A fase dev.5 adiciona o tipo Message e fields de rastreamento para eventos de
publicação. A assinatura usa a flag já existente `seguir_topico`; nenhuma
configuração global do Message Subscribe ou do transporte de e-mail é alterada.
Notifiers ativos continuam sob a configuração existente. Administração fica no
purpose `main`.

A fase dev.6 habilita Site Alert e `datetime_range` no Config Sync e instala a
configuração do bloco nativo Site Alert na região `highlighted` de ACULTA420,
com polling em 300 segundos. Formulários,
programação, severidade e permissões continuam em Site Alert; nenhuma role é
autorizada automaticamente. O bloco é sitewide nos purposes com esse tema.
O tipo persistente `aculta_notice` usa o workflow editorial, validade opcional e
segmentação por papéis existentes. O acesso ao Node valida público e período.
O estado individual usa o Flag não global `aculta_notice_read`; não há tabela
customizada nem duplicação do Site Alert. O sino soma avisos persistentes não
lidos e Site Alerts ativos para membros.

Na configuração exportada examinada, nenhuma role recebe as permissões nativas
`administer site alert`, `add site alerts`, `update site alerts` ou
`delete site alerts`. O Messenger não amplia esse acesso; o responsável deve
designar administradores em `main` antes de delegar a criação.

A área de Mensagens oferece rotas de conta e entradas condicionais no shell.
Não concede permissões nativas do Private Message. Conversas preserva a inbox
contrib; Notificações exibe eventos privados entregues; Avisos lista Nodes
publicados e visíveis com links nativos lido/não lido do Flag.

## Checklist para implantação futura

Preferências de e-mail em `/configuracoes/email` são propriedade do Messenger e
ficam em `user.data` sob `aculta_messenger`. As categorias de conteúdo são
respostas/atualizações do Fórum, Aprendizagem, Wiki, Loja e Doações. Para cada
uma, a pessoa pode selecionar sem e-mail, entrega direta ou resumo diário,
conforme `aculta_messenger.settings:email_modes`; a administração pode
desabilitar entrega direta ou resumo. A seleção por categoria é suficiente e
não depende de outro opt-in geral. O post-update
`aculta_messenger_post_update_shared_email_preferences` migra o opt-in global
legado e as exceções antigas do Fórum para modos individuais, preserva escolhas
Messenger existentes e remove as chaves antigas após a cópia. Depois da
migração, contas sem preferência ficam somente no site. Em Mensagens Privadas,
a pessoa escolhe
envio direto a cada mensagem, resumo semanal ou sem e-mail; a administração
pode habilitar os modos, definir o padrão para contas sem preferência e definir
a data inicial global do digest semanal. Amizade permite somente entrega no
site ou e-mail direto. Os avisos de Mensagens Privadas contêm somente o nome do
remetente e link para a inbox; nunca incluem o corpo da mensagem. Cada conta
recebe os resumos seguintes após sete dias. A data inicial é considerada à
meia-noite no fuso horário configurado em `system.date:timezone.default`; uma
data administrativa inválida não habilita a entrega. O update
`aculta_messenger_post_update_private_message_email_preferences` copia as
preferências administrativas e individuais do Private Message para
`aculta_messenger`; preserva uma escolha Messenger já existente e remove a chave
individual antiga somente depois da cópia. O update é incremental e não foi
executado no Runtime. Após sua execução, o Messenger é a fonte única dessas
preferências. Avisos no site e conversas na inbox permanecem disponíveis
independentemente do opt-out.
O ACULTA Mailer é o transporte comum; produtores e adapters do Messenger não
configuram SMTP.

1. Comparar configuração exportada dos cinco módulos e aprovar os componentes
   necessários; não fazer import/export automático.
2. Confirmar os purposes e hosts do ambiente pelos arquivos de arquitetura e
   gerar URLs com `DomainPurposeManager`.
3. Instalar primeiro em ambiente de teste com transporte de e-mail controlado.
4. Conferir configuração pendente (`config:status`) sem resolver drift alheio.
5. Verificar `updatedb:status`; não executar `updb`, `cim` ou `cex` sem análise
   do estado, efeitos e autorização operacional.
6. Confirmar cache rebuild, acesso como duas contas, rotas de MAIN/ACCOUNT e
   ausência de saída privada em outros purposes.
7. Registrar comandos e evidências no changelog da fase/PR, sem criar relatório
   normativo permanente de execução.
# Configuração de comunicados

O módulo instala o tipo editorial `aculta_notice` com texto, público por papel,
início e término opcionais. A segmentação é inclusiva (OU): o membro que possui
qualquer papel selecionado pode visualizar o aviso durante sua validade.
`authenticated` significa todos os membros autenticados. Os papéis disponíveis
são definidos a partir dos papéis existentes na configuração do Portal; Group
não é presumido como ativo.

O workflow editorial controla revisão e publicação. `content_editor` cria e
edita avisos em rascunho; publicação segue as transições editoriais atribuídas
à administração. A administração continua no purpose MAIN.

`aculta_notice_read` é um Flag não global que fornece ações de marcar como lido
e não lido. O badge para membros conta avisos visíveis ainda não marcados como
lidos; visitantes veem a quantidade de Site Alerts temporários ativos.


## Privacidade de novas conversas (0.1.1)

Em Minha Conta > Configurações > Mensagens, cada usuário escolhe Todos os membros ou Somente amizades confirmadas. O estado usa User Data (`aculta_messenger`, chave `message_privacy`) e não requer configuração global ou import de configuração. O padrão efetivo é Todos os membros. Administradores e editores de conteúdo ignoram essa escolha; bloqueios individuais nativos do Private Message permanecem efetivos. A escolha não bloqueia respostas em conversas existentes.

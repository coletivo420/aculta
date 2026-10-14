# Segurança e privacidade

Documento de referência arquitetural. Dono: `aculta_messenger` no ACULTA
Portal. Revisão: 2026-10-14.

## Matriz de acesso e privacidade

| Ação/dado | Público autorizado | Decisão de access | Cache esperado |
| --- | --- | --- | --- |
| Ver caixa postal | Usuário autenticado com `use private messaging system` e `access user profiles` | Rota nativa do Private Message; as duas permissões são exigidas | Inbox upstream `no_cache`; validar contexts/tags do thread |
| Ver thread/mensagem | Participante autorizado ou administrador conforme regra upstream | Entity access `private_message_thread.view` / `private_message.view` e handlers próprios | Não compartilhar entre contas; cache context da thread |
| Iniciar conversa | Membro habilitado e destinatário que aceita contato | Permissões, elegibilidade, bloqueio e rate limit confirmados no upstream | Sem cache de formulário/destinatários privados |
| Receber e-mail | Destinatário válido, elegível e permitido | Revalidar status da conta, access, preferências e assinatura na entrega | Não guardar conteúdo privado em cache compartilhado |
| Ver comunicado | Público explicitamente segmentado | Node access, publicação, validade e membership quando necessário | Tags/contextos de entidade, usuário e grupo conforme decisão |
| Assinar evento | Usuário autorizado a assinar o contexto | Flag/Message Subscribe e access atual da entidade | Contexto de usuário e dependências da entidade |
| Administrar alerta/comunicado | Papéis administrativos autorizados em `main` | Permissões nativas dos módulos/editorial | Rotas administrativas sem cache público |

IDs e handlers foram confirmados no código instalado; contexts e tags de
presenters precisam de testes funcionais. Não copiar permissões para o
Messenger nem concedê-las automaticamente a papéis.

## Invariantes

- Cada leitura e escrita usa APIs públicas Drupal e permissões/access nativos;
  não consultar tabelas de contrib por SQL.
- Thread, destinatário e conteúdo são autorizados no servidor em cada request;
  ID de entidade fornecido pela URL não prova acesso.
- `EntityQuery` declara `accessCheck(TRUE|FALSE)` conscientemente. Uma query
  de destinatários não substitui o access handler no endpoint final.
- Formularios de envio usam Form API, token CSRF e fluxo nativo. Sem JavaScript,
  submit e navegação permanecem funcionais.
- Não expor autocomplete que facilite enumeração de usuários; resultados só
  incluem membros selecionáveis conforme permission e preferência do alvo.
- Limitar frequência usando mecanismos Drupal/contrib existentes após verificar
  sua disponibilidade. Não inventar uma tabela de contadores no Messenger.
- Mensagens privadas nunca aparecem em log, e-mail, breadcrumb, cache público,
  metadado de perfil público ou mensagem de erro detalhada.
- E-mails têm informação mínima e link para a rota canônica `account`; link é
  gerado por `DomainPurposeManager`, sem host hardcoded.
- Assinatura é preferência de notificação, não autorização para ler entidade.
  A publicação e a entrega revalidam acesso e membership.
- Entidades de Site Alert não são visíveis em purposes incorretos por uma
  conveniência de tema; purpose, visibilidade e cache são decididos no Drupal.
- Desinstalação não apaga dados pertencentes a contrib. Remoção de config ou
  dados só ocorre com proteção explícita e confirmação operacional.

## Bloqueio e denúncia

Private Message 3.0.7 inclui a entidade `private_message_ban` e o serviço
`private_message.ban_manager`, com operações `isBanned()`,
`isCurrentUserBannedByUser()`, `banUser()` e `unbanUser()`. Reutilizar esse
mecanismo e suas rotas/formulários; não criar storage de bloqueio próprio. A
política ACULTA de denúncia ainda não foi aprovada; não criar fluxo ou registro
paralelo sem decisão de escopo.

## Verificação obrigatória nas fases funcionais

Cobrir isolamento entre usuários, IDOR, conteúdo não publicado, revogação de
acesso, csrf, XSS, enumeração, rate limit, cache privado, envio duplicado,
preferências de e-mail, logs e endereços cross-purpose. E-mails de teste devem
usar transporte de teste/destinatário controlado; nunca enviar aos membros.

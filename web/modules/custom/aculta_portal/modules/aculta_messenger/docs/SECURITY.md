# Segurança e privacidade

Documento de referência arquitetural. Dono: `aculta_messenger` no ACULTA
Portal. Revisão: 2026-10-14.

## Matriz de acesso e privacidade

| Ação/dado | Público autorizado | Decisão de access | Cache esperado |
| --- | --- | --- | --- |
| Ver caixa postal | Usuário autenticado e elegível | Permissões e ownership nativos de Private Message | Privado e por usuário; verificar contexts/tags do módulo |
| Ver thread/mensagem | Participante autorizado ou administrador conforme regra upstream | Access handler/route do Private Message; nunca confiar apenas no ID da rota | Não compartilhado entre contas |
| Iniciar conversa | Membro habilitado e destinatário que aceita contato | Permissões, elegibilidade, bloqueio e rate limit confirmados no upstream | Sem cache de formulário/destinatários privados |
| Receber e-mail | Destinatário válido, elegível e permitido | Revalidar status da conta, access, preferências e assinatura na entrega | Não guardar conteúdo privado em cache compartilhado |
| Ver comunicado | Público explicitamente segmentado | Node access, publicação, validade e membership quando necessário | Tags/contextos de entidade, usuário e grupo conforme decisão |
| Assinar evento | Usuário autorizado a assinar o contexto | Flag/Message Subscribe e access atual da entidade | Contexto de usuário e dependências da entidade |
| Administrar alerta/comunicado | Papéis administrativos autorizados em `main` | Permissões nativas dos módulos/editorial | Rotas administrativas sem cache público |

IDs de permissões, implementações de access handler e contexts concretos ficam
pendentes da leitura do código exato instalado. Não copiar permissões para o
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

Private Message documenta recursos próprios de acesso, mas a política ACULTA de
bloqueio e denúncia ainda não foi aprovada nem verificada contra a versão
instalada. O Messenger não criará fluxo de denúncia, registro de moderação ou
campo de bloqueio paralelo sem uma decisão de escopo e um mecanismo upstream
confirmado. Antes de habilitar mensagens entre membros, a Fase 2 precisa
documentar o comportamento de interação indesejada e uma alternativa segura.

## Verificação obrigatória nas fases funcionais

Cobrir isolamento entre usuários, IDOR, conteúdo não publicado, revogação de
acesso, csrf, XSS, enumeração, rate limit, cache privado, envio duplicado,
preferências de e-mail, logs e endereços cross-purpose. E-mails de teste devem
usar transporte de teste/destinatário controlado; nunca enviar aos membros.

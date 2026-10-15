# ACULTA Messenger: tipos de conversa, limites, retenção e integrações

Data: 2026-10-15. Módulo: `aculta_messenger` 0.1.1-dev.31. Status: **base preparada, integrações não construídas**.
Documentos relacionados: [`MESSENGER.md`](MESSENGER.md) (projeto), [`MESSENGER-AUDIT.md`](MESSENGER-AUDIT.md) (fronteiras),
[`web/themes/custom/aculta420/docs/messenger.md`](../../web/themes/custom/aculta420/docs/messenger.md) (tema).

## 1. Pedido

1. "Iniciar conversa" hoje aceita uma pessoa. Usuários comuns devem poder incluir até 10 pessoas.
2. A conversa se integra ao chat do fórum.
3. Educadores e administradores podem adicionar uma turma inteira ao chat do LMS. Quem entra no curso depois entra no chat sozinho.
4. O chat do LMS é persistente. Os demais são mantidos por 6 meses.
5. Deixar a base pronta para a integração com o site, a wiki, o LMS e as outras ferramentas previstas, e documentar.

## 2. O que já existe (0.1.1-dev.31)

| Parte | Estado |
| --- | --- |
| Conversa de duas pessoas (direta) | Pronta: "Iniciar conversa", resposta, menu de amizade e bloqueio. |
| Conversa em grupo (mais de duas pessoas) | Existe no Private Message e aparece na lista. Criação pela gaveta **não** existe: a nova conversa tem um destinatário só. |
| Limites por tipo | `Service\ConversationPolicy`: direta 1, grupo 10 (usuários comuns), com teste unitário. |
| Retenção | Cron de 6 meses, lendo `ConversationPolicy::RETENTION_MONTHS`. Não diferencia tipos ainda. |
| Tipo gravado na conversa | **Não existe.** O tipo é deduzido do número de membros (2 = direto, mais = grupo). Fórum e curso não são detectáveis. |
| Fórum, LMS, wiki | Não integrados. O fórum já tem notificações de tópico (`seguir_topico`) no módulo. |

## 3. Tipos de conversa

| Tipo | Quem entra | Limite (outros membros) | Retenção | Origem |
| --- | --- | --- | --- | --- |
| `direct` | Duas pessoas | 1 | 6 meses | Membro, pela gaveta |
| `group` | Membro comum e o autor | 10 (proposta) | 6 meses | Membro, pela gaveta (**a construir**) |
| `forum` | Membros do tópico (seguidores e participantes) | Sem limite fixo a decidir | 6 meses | Fórum (**a integrar**) |
| `course` | Turma do LMS (educadores e administradores adicionam; novos matriculados entram) | Sem limite fixo | **Persistente** | LMS (**a integrar**) |

Regras comuns a todos os tipos: o autor precisa ter permissão de mensagens; ninguém é adicionado se bloqueou ou foi bloqueado
pelo autor; a preferência "só amigos" de cada membro é respeitada na criação; o limite de envios continua valendo.

`ConversationPolicy` é o único lugar onde esses números moram. O formulário, o cron e as integrações leem dali.

## 4. Fonte de membros e saída

- **Membro comum:** adiciona pessoas à mão (a construir). Sai da conversa quando quiser (a construir: hoje só limpa o histórico).
- **Fórum:** a fonte de verdade da participação é o próprio fórum (quem segue o tópico). A conversa do tópico acompanha essa lista.
- **LMS:** a fonte de verdade é a matrícula e a turma no Drupal LMS (`docs/integrations/GOOGLE.md`: "Drupal LMS continua fonte de verdade de cursos, matrícula e progresso"). O chat do curso só copia essa lista. Quem é removido da turma é removido do chat.
- **Wiki e outras ferramentas:** entram pelo mesmo contrato do fórum e do LMS (seção 5), com a origem declarada.

## 5. Contrato previsto para as integrações

Nenhum destes serviços existe ainda. O contrato define o que cada integração deve chamar:

```text
ConversationPolicy            kindOf(), maxRecipients(), retentionMonths()    (existe)
ConversationContext  (futuro)  thread_id, kind, source (forum|course|wiki|…), source_id
                               gravado quando a integração cria ou vincula a conversa
ConversationMembership (futuro) addMembers(thread, users, source), removeMembers(thread, users, source)
                               valida limite, bloqueio, privacidade e tipo antes de gravar
```

Regras do contrato:

1. A integração nunca escreve direto nas tabelas do Private Message: usa o serviço de membership, que aplica as regras.
2. Cada membro carrega a origem (`source`). Remover um membro só remove quem entrou por aquela origem.
3. Retenção: o cron pula conversas cujo tipo tem retenção `NULL` (curso). Hoje nenhuma conversa é gravada como `course`, então
   o cron ainda trata todas as conversas pelos seis meses. Isso é intencional até o tipo existir.
4. Eventos: a integração do LMS avisa a conversa quando um membro entra ou sai da turma (evento do módulo LMS, a definir).

## 6. Decisões que dependem de você

1. **"Até 10 pessoas":** a leitura adotada é 10 **outras** pessoas além do autor (grupo de até 11). Confirme ou troque.
2. **Onde gravar o tipo:** (a) campo base em `private_message_thread` (exige `drush updb` e migração de dados de teste), ou
   (b) entidade própria `aculta_conversation_context` ligada à thread. A opção (b) não altera o contrib e é a recomendada.
3. **Fórum:** um único chat por tópico com todos os seguidores, ou um chat aberto para quem participa? Afeta o limite e a saída.
4. **LMS:** o chat do curso é só leitura para quem sai da turma, ou some para essa pessoa? A retenção é "persistente" para toda
   a turma, mesmo depois do fim do curso?
5. **Educadores e administradores no grupo comum:** o limite de 10 vale para eles? (A política atual deixa aberto.)

## 7. Ordem de construção proposta

| Fase | Entrega | Pronta quando |
| --- | --- | --- |
| A (esta) | `ConversationPolicy`, documentação, retenção lendo a política | Testes unitários passam. |
| B | Grupo para usuários comuns: buscar e marcar até 10, criar conversa com N membros, limite e regras na validação | Teste de navegador com 3 e com 11 pessoas (o 11º é recusado). |
| C | `ConversationContext` (entidade) e `ConversationMembership` | Conversa criada por integração guarda origem; remover origem remove só quem entrou por ela. |
| D | Integração com o fórum (chat do tópico) | Seguidor novo entra; quem deixa de seguir sai. |
| E | Integração com o LMS (turma, persistência do chat do curso, entrada automática) | Matrícula nova entra no chat; remoção da turma tira do chat; cron não remove o chat do curso. |
| F | Wiki e outras ferramentas | Mesmo contrato da fase D, com a origem declarada. |

Cada fase é um PR próprio, conforme o AGENTS.md. Fases D, E e F dependem de módulos que ainda não existem neste repositório.

## 8. Testes

- Unitários: `tests/src/Unit/ConversationPolicyTest.php` (tipo pelo número de membros, limite de grupo, retenção do curso).
- Navegador: a suíte atual cobre conversas diretas e uma conversa em grupo criada por preparação de teste. A criação de grupo pela
  gaveta entra na fase B.

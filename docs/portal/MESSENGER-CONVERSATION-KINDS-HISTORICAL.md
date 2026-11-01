# Registro histórico — tipos de conversa propostos

**Status:** proposta substituída em 2026-10-24 pela decisão normativa em [`MESSENGER-CONVERSATION-KINDS.md`](MESSENGER-CONVERSATION-KINDS.md) e [`ARCHITECTURE.md`](../../web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ARCHITECTURE.md). Este conteúdo é preservado para auditoria e não deve orientar implementação.

---

# ACULTA Messenger: tipos de conversa, limites, retenção, controles e integrações

Data: 2026-10-15. Módulo: `aculta_messenger` 0.1.1-dev.31. Status: **base preparada; integrações (fórum, LMS, wiki) não construídas**.
Documentos relacionados: [`MESSENGER.md`](MESSENGER.md) (projeto), [`MESSENGER-AUDIT.md`](MESSENGER-AUDIT.md) (fronteiras),
[`web/themes/custom/aculta420/docs/messenger.md`](../../web/themes/custom/aculta420/docs/messenger.md) (tema).

## 1. Pedidos que este documento atende

1. "Iniciar conversa" aceita uma pessoa só. Usuários comuns devem poder incluir até 10 pessoas (o limite é configurável).
2. O chat se integra ao fórum: o fórum tem chat e dá relatório de participação para avaliação no LMS.
3. O LMS **usa o chat global e os avisos globais** (não tem canal próprio). Educadores e administradores podem adicionar uma turma
   inteira ao chat do curso. Quem se matricula depois entra no chat sozinho.
4. O chat do curso é persistente. Os demais chats são mantidos por 6 meses (configurável).
5. Chat e fórum dão relatórios de participação para avaliação no LMS.
6. Controles administrativos do chat: limite de participantes, tempo da limpeza, limites de envio, grupos.

## 2. Estado atual

| Parte | Estado |
| --- | --- |
| Conversa direta (duas pessoas) | Pronta: "Iniciar conversa", resposta, menu de amizade e bloqueio. |
| Conversa em grupo | Existe no Private Message e aparece na lista. A criação pela gaveta (várias pessoas na busca) está em construção: a busca já mostra caixas de seleção e a rota de grupo existe; o fluxo ainda precisa de teste de navegador. |
| Controles administrativos | `aculta_messenger.settings` e o formulário em `/painel-administrativo/aculta/mensagens` (permissão `administer aculta messenger`). Ver seção 5. |
| Política dos tipos | `Service\ConversationPolicy`: tipo, limite, retenção e limites de envio, lidos da configuração. |
| Tipo gravado na conversa | **Não existe.** O tipo é deduzido do número de membros (2 = direto, mais = grupo). Fórum e curso não são detectáveis até as integrações gravarem o tipo. |
| Fórum, LMS, wiki | Não integrados. |

## 3. Tipos de conversa

| Tipo | Quem entra | Limite (outros membros) | Retenção | Origem |
| --- | --- | --- | --- | --- |
| `direct` | Duas pessoas | 1 | Configurável (padrão 6 meses) | Membro, pela gaveta |
| `group` | Membro comum e o autor | Configurável (padrão 10) | Configurável (padrão 6 meses) | Membro, pela gaveta |
| `forum` | Membros do tópico | Sem limite fixo a decidir | Configurável (padrão 6 meses) | Fórum (**a integrar**) |
| `course` | Turma do LMS (matriculados entram sozinhos) | Sem limite fixo a decidir | **Persistente** (sem limpeza por inatividade) | LMS (**a integrar**) |

Regras comuns: o autor precisa ter permissão de mensagens; ninguém é adicionado se bloqueou ou foi bloqueado pelo autor; a
preferência "só amigos" de cada membro é respeitada na criação; o limite de envios vale para todos.

O chat do curso **é um chat**, com a mesma gaveta, as mesmas regras de envio e o mesmo aviso global de quem não pode enviar.
Não há um canal separado para o curso. Os avisos do curso são avisos globais direcionados aos matriculados (ver seção 6).

## 4. Fontes de membros e saída

- **Membro comum:** adiciona pessoas à mão (grupo). Sai quando quiser (a construir: hoje só limpa o histórico).
- **Fórum:** a participação vem do próprio fórum (quem segue ou participa do tópico). A conversa do tópico acompanha essa lista.
- **LMS:** a fonte de verdade é a matrícula e a turma no Drupal LMS (`docs/integrations/GOOGLE.md`: "Drupal LMS continua fonte de
  verdade de cursos, matrícula e progresso"). O chat do curso só copia essa lista. Quem sai da turma sai do chat.
- **Wiki e outras ferramentas:** entram pelo mesmo contrato do fórum e do LMS (seção 7), com a origem declarada.

## 5. Controles administrativos (aculta_messenger.settings)

Formulário: `/painel-administrativo/aculta/mensagens`, permissão `administer aculta messenger` (perfil administrador).

| Controle | Padrão | Faixa | Efeito |
| --- | --- | --- | --- |
| `group_creation_enabled` | ligado | liga/desliga | Desligado, a nova conversa aceita uma pessoa só. |
| `group_max_members` | 10 | 2 a 50 | Máximo de outras pessoas num grupo (além do autor). |
| `retention_months` | 6 | 1 a 36 | Meses de inatividade antes da limpeza (não se aplica ao chat do curso). |
| `cleanup_batch` | 20 | 1 a 500 | Conversas removidas por execução do cron (limita o trabalho). |
| `send_limit_per_hour` | 10 | 1 a 1000 | Mensagens por hora por conta. |

Os valores são lidos só por `ConversationPolicy`. Os padrões ficam em `config/install/aculta_messenger.settings.yml`, e o
arquivo exportado é `config/sync/aculta_messenger.settings.yml`. Valores fora da faixa são limitados ao intervalo.

O chat do curso não tem controle de limpeza: é persistente por definição. Quem gerencia o curso gerencia a turma pelo LMS.

## 6. O LMS: chat do curso e avisos globais

- **Chat do curso:** tipo `course`, persistente, criado para a turma. Educadores e administradores adicionam a turma inteira.
  Matrícula nova entra no chat. Remoção da turma tira do chat.
- **Avisos globais:** LMS publica no canal global `aculta_messenger`; o adapter
  `aculta_lms_messenger` acrescenta avisos pessoais de matrícula e certificado à
  gaveta/contador. Matrícula e certificado também disparam e-mail transacional;
  conclusão aprovada permanece no site e entra no resumo diário opt-in combinado.
  Não há canal separado do curso.
- **Relatório de participação para avaliação:** ver seção 8.

## 7. Contrato previsto para as integrações

Nenhum destes serviços existe ainda. O contrato define o que cada integração deve chamar:

```text
ConversationPolicy              kindOf(), maxRecipients(), retentionMonths(), groupMaxMembers(), sendLimitPerHour()  (existe)
ConversationContext  (futuro)   thread_id, kind, source (forum|course|wiki|...), source_id
ConversationMembership (futuro) addMembers(thread, users, source), removeMembers(thread, users, source)
ParticipationLedger (futuro)    registra eventos de participação por membro, origem e data (seção 8)
```

Regras do contrato:

1. A integração nunca escreve direto nas tabelas do Private Message: usa o serviço de membership, que aplica as regras.
2. Cada membro carrega a origem (`source`). Remover um membro só remove quem entrou por aquela origem.
3. Retenção: o cron pula conversas cujo tipo não tem retenção (curso). Hoje nenhuma conversa é gravada como `course`, então o
   cron ainda trata todas pelos meses configurados. A regra já está no cron.
4. Eventos: a integração do LMS avisa a conversa quando um membro entra ou sai da turma.

## 8. Relatórios de participação para avaliação no LMS (chat e fórum)

Objetivo: o LMS avalia a participação do aluno no chat do curso e nas discussões do fórum. O relatório é **contagem e data**, nunca o
conteúdo das mensagens (privacidade: `docs/integrations/GOOGLE.md`, "não pode enviar PII"; o conteúdo privado não sai do chat).

**O que é contado, por aluno matriculado, por período:**

| Medida | Chat (curso e grupos) | Fórum |
| --- | --- | --- |
| Mensagens enviadas | sim | sim (comentários) |
| Dias com participação | sim | sim |
| Conversas ou tópicos em que participou | sim | sim |
| Respostas recebidas (quando a pessoa é o alvo) | sim | sim |
| Última participação | sim | sim |

**Como sai:**

- Um registro de participação por aluno, origem (`course`, `forum`), curso e período (mês). Sem texto de mensagem.
- Entrega ao LMS por serviço interno (o LMS consulta o relatório), não por tabela compartilhada nem por credencial no tema.
- O relatório só inclui quem está matriculado no curso no período avaliado.
- Quem sai do curso deixa de aparecer para a avaliação futura; o histórico já entregue fica com o LMS.

**Onde a contagem nasce:** em cada mensagem e comentário gravado (evento do Private Message para o chat; evento do fórum para o
comentário). O `ParticipationLedger` apenas registra. Hoje nada é registrado: o registro é a fase D (fórum) e a fase E (LMS).

**Privacidade:** o relatório é para avaliação do próprio curso. O aluno vê o seu. A equipe do LMS vê o da turma. Nada disso aparece
em outros lugares do site.

## 9. Decisões que dependem de você

1. **"Até 10 pessoas":** a leitura adotada é 10 **outras** pessoas além do autor (grupo de até 11). Agora é configurável em
   `group_max_members`. Confirme o padrão.
2. **Onde gravar o tipo:** (a) campo base em `private_message_thread` (exige `drush updb` e migração), ou (b) entidade própria
   `aculta_conversation_context` ligada à thread. A opção (b) não altera o contrib e é a recomendada.
3. **Fórum:** um chat por tópico com todos os seguidores, ou aberto para quem participa? Afeta limite e saída.
4. **LMS:** quem sai da turma perde o acesso ao chat do curso, mas mantém o histórico lido? A persistência vale mesmo depois do fim
   do curso?
5. **Relatório:** período de avaliação (mensal ou por curso), e se o educador vê o relatório de cada aluno ou só o agregado da turma.
6. **Educadores e administradores no grupo comum:** o limite de `group_max_members` vale para eles também?

## 10. Ordem de construção proposta

| Fase | Entrega | Pronta quando |
| --- | --- | --- |
| A | `ConversationPolicy`, controles administrativos, retenção lendo a política, documentação | Testes unitários passam (28). |
| B | Grupo para usuários comuns pela gaveta: buscar, marcar até o limite, criar a conversa com N membros | Teste de navegador com 3 pessoas, e a recusa ao passar do limite. |
| C | `ConversationContext` (entidade) e `ConversationMembership` | Conversa criada por integração guarda a origem; remover a origem tira só quem entrou por ela. |
| D | Fórum: chat do tópico e `ParticipationLedger` para comentários | Seguidor entra; quem deixa de seguir sai; relatório de participação do fórum. |
| E | LMS: turma, chat do curso persistente, entrada automática, relatório de participação do chat | Matrícula entra no chat; remoção tira; cron não remove o chat do curso; relatório por aluno. |
| F | Wiki e outras ferramentas | Mesmo contrato da fase D, com a origem declarada. |

Cada fase termina em commits na branch autorizada; PR só quando o responsável
solicitar. Requisitos, gates e revisão visual aplicável ficam prontos antes de
RC; a validação do Runtime ocorre em Alpha. As fases D, E e F dependem de módulos
que ainda não existem neste repositório.

## 11. Testes

- Unitários: `tests/src/Unit/ConversationPolicyTest.php` (tipo, padrões, valores administrados, grupo desligado, limites, chat do curso persistente).
- Unitários do validador: `PrivateMessageFormValidatorTest.php` (limite de envio vem da política).
- Navegador: a suíte atual cobre conversas diretas e um grupo criado pela preparação de teste. A criação de grupo pela gaveta entra na fase B.

# Avisos e notificações — contrato transversal ACULTA

**Revisão:** 2026-10-24. **Decisões:** Fórum F-D21 e F-D41; arquitetura transversal ADR-XK-02.
**Escopo:** avisos no site e e-mails vinculados à atividade de conteúdos acompanhados; não criar inbox, chat, SMTP nem armazenamento duplicado.

## 1. Separação obrigatória: discussão, aviso e conversa

| Capacidade | Dono e fonte de verdade | Regra |
| --- | --- | --- |
| Tópicos, posts revisionáveis e Comments contextuais | `aculta_forum` + Node/Comment/Entity/Workflow | Resposta pública pertence ao conteúdo e à moderação da origem. |
| Seguir/salvar conteúdo | Flag (`seguir_topico` para tópicos do Growroom) e política do módulo proprietário | Assinatura é preferência; nunca concede acesso. |
| Aviso pessoal de tópico seguido | `aculta_messenger` / entidade `aculta_aviso` existente | Uma entrada agregada por pessoa, tipo e tópico; leitura e contador próprios. |
| Comunicado persistente institucional | Node `aculta_notice` e Flag `aculta_notice_read` | Não confundir com `aculta_aviso` do Fórum. |
| Alerta temporário do site | Site Alert, com Flags de dispensa/leitura quando aplicável | Mantém política nativa de validade e apresentação. |
| Eventos pessoais de outros módulos | Message/Message Notify e adapters do Messenger | Reutilizar providers/serviços de avisos; não criar inbox paralela. |
| Conversa privada entre pessoas | Private Message integrado ao `aculta_messenger` | Ação voluntária do membro; nunca surge automaticamente ao seguir, comentar ou matricular-se. |
| E-mail de aviso ou resumo | `aculta_messenger` coordena; `aculta_mailer` transporta | Não criar SMTP, templates de transporte nem worker concorrente no Fórum. |

**A central da conta pode apresentar Avisos e Mensagens lado a lado, mas elas são listas e fontes de verdade diferentes.** Um aviso de nova resposta não aparece como uma thread de Private Message.

Discussões contextuais de LMS, Wiki e Revista são Comments no conteúdo proprietário, **não** chats exclusivos nem conversas privadas geradas automaticamente. Um participante pode iniciar uma conversa privada com outro membro usando a ação normal do Messenger, sujeita a bloqueios, elegibilidade e privacidade.

## 2. Fórum: aviso no site obrigatório para seguidores elegíveis

- A Flag `seguir_topico` identifica contas interessadas no tópico do Growroom420.
- A primeira publicação de Comment legado ou `aculta_forum_post` revisionável elegível gera **aviso**, não mensagem privada. Aprovação tardia conta quando a publicação se torna efetiva, não quando o rascunho é criado.
- O autor da própria resposta não é destinatário dela; conteúdo histórico importado e ações de sincronização não criam novos avisos de participação.
- Atualização de tópico seguido é um evento distinto da publicação de resposta.
- `aculta_aviso` agrega por **destinatário + tipo + tópico**: `contador_site`, `contador_email` e marcadores de publicação permanecem independentes.
- O marcador `ultimo_comentario` identifica Comments; `ultimo_post` acompanha o ID da revisão publicada dos ForumPosts, preservando idempotência sob aprovação fora de ordem.
- Ler o tópico pela rota canônica zera `contador_site` dos avisos relevantes daquela conta. **Isto não equivale a acionar uma Flag de leitura de comunicado institucional.**
- O contador da barra de Avisos e a lista da conta consideram apenas registros ainda elegíveis e visíveis; o contador de conversas não inclui avisos.
- Deixar de seguir interrompe novos avisos decorrentes da assinatura. Não conceder nem manter visibilidade indevida por causa de registros antigos.

A entrega por mensagem do Message Subscribe para publicação de tópicos do Fórum foi desligada pela F-D21. Não reativar o mecanismo antigo nem duplicar `aculta_aviso` com outro evento persistido para o mesmo aviso sem migração aprovada e testes de equivalência.

## 3. Resumo por e-mail

- O resumo diário de participação é **opt-in**, desligado por padrão.
- Exige conta ativa, endereço confirmado e elegibilidade ao conteúdo no momento do processamento.
- É enviado somente quando existe novidade pendente; a implementação atual aplica janela mínima de 20 horas entre envios por conta.
- Agrega respostas e atualizações por tópico, sem um e-mail por resposta.
- O registro pendente é confirmado somente após envio bem-sucedido por `aculta_mailer`.
- Desligar o resumo não apaga o aviso no site; a preferência existente limpa pendências de e-mail conforme contrato do Messenger.
- Eventos transacionais de outros módulos podem seguir políticas próprias aprovadas, sem alterar retroativamente o padrão do Fórum.

O Fórum não cria filas de envio nem chama transporte de e-mail diretamente.

## 4. Contextos privados, moderação e Domain

O evento, a contagem, a apresentação, o link de abertura e a entrega revalidam o contexto autorizado: publicação, Entity Access, Domain/Purpose e, quando relevante, Group e matrícula/situação associativa.

- Tópico, post ou Comment pendente/rejeitado não produz aviso público.
- A revogação de acesso prevalece sobre a existência da Flag ou do aviso antigo.
- Conteúdo do LMS é restrito ao contexto da turma/curso e não integra avisos públicos do Growroom.
- Assinatura e payload enfileirado não são autorização.
- O link deve usar rota canônica resolvida pelo propósito correto; não fixar host de ambiente.

## 5. Integração futura com API de eventos

`aculta_forum` publica informações mínimas sobre evento, entidade/revisão e contexto. A política de destinatários continua vinculada às assinaturas e ao acesso do conteúdo; `aculta_messenger` concentra agregação, idempotência, estado de leitura e coordenação de canais; `aculta_mailer` faz o envio.

A ADR-XK-02 propõe um envelope com tipo de evento, módulo, UUID/revisão, destinatário, propósito, data e chave de idempotência. **É contrato planejado, não uma API já implementada.** Migrar os produtores gradualmente e comparar resultados lado a lado, sem disparo duplo.

## 6. Critérios de aceite e referências

- Seguir não cria thread nem concede leitura de conteúdo restrito.
- Primeira resposta publicada conta uma vez; edições e republicações não a duplicam.
- Aprovação de posts fora da ordem não perde avisos.
- Autor, conta suspensa, ex-seguidor e destinatário sem acesso são tratados pela política vigente.
- Avisos por pessoa não são mensagens privadas; contadores e preferências permanecem separados.
- Resumo respeita opt-in, confirmação, conteúdo acessível e deduplicação.
- Testes Unit/Kernel, casos de revogação e revisão visual autenticada precedem RC; Runtime e transporte controlado exigem evidências próprias.

Referências: `web/modules/custom/aculta_portal/modules/aculta_messenger/docs/AVISOS-E-MENSAGENS.md`, `web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md`, `docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md`.

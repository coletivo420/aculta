# ARQ-06 — Auditoria do `aculta_ugc` (HEAD `7908ea166`)

Auditoria do código instalado, não só dos roadmaps. Escopo: `ReportService`, `UgcReportQueue`,
`ModerationNotifier`, `UgcReport`, `UgcRecipientConfig`, `UgcReportAccessControlHandler`,
`UgcModerationKernelTest`, e os consumidores reais.

## 1. Fatos verificados

- `aculta_ugc` **não está em `config/sync/core.extension.yml`**: o módulo existe no código, mas não está
  habilitado no Runtime. Nenhum outro módulo o consome (`grep aculta_ugc` só acha documentação).
- Fórum, Wiki e Marley ainda usam suas próprias interfaces. A fila legada é
  `aculta_user\Moderation\ReportQueue` (+ `ReportQueueController`, rota em `aculta_user.routing.yml`),
  derivada dos Flags `sinalizar_topico`, `sinalizar_comentario`, `sinalizar_post_forum`.
- `aculta_mailer` é a plataforma de e-mail transacional (opt-in): `AcultaMailerInterface`,
  `MailMessage`, tipos registrados por tag `aculta_mailer.type`. É o único caminho de envio permitido.
- `DAILY_LIMIT = 20` está no código; `PLANO-IMPLANTACAO.md` (seção 6) diz que limites numéricos
  **não foram decididos**. Conflito documentado em §4.

## 2. Matriz de capacidades

| Capacidade | Estado em `7908ea166` | Observação |
| --- | --- | --- |
| Entidade `aculta_ugc_report` | Implementada | Sem vínculo com a origem legada; sem marca de aviso enviado |
| Config entity `aculta_ugc_recipients` | Implementada | E-mails sem validação de formato |
| Verificação de leitura da entidade denunciada | **Ausente** | Qualquer autenticado denuncia qualquer ID |
| Validação do motivo | **Ausente** | Texto livre, sem allowlist, sem vazio |
| Purpose da denúncia | **Insegura** | `createReport(..., $purpose)` aceita qualquer string do chamador |
| Duplicidade (aberta, mesmo usuário/alvo) | Parcial | Sem lock: corrida cria duplicata |
| Limite diário | Parcial | Valor 20 não aprovado |
| Moderação por purpose | **Ausente** | Uma permissão global libera todos os purposes |
| Transições de status | **Ausente** | `decideReport` grava qualquer string e permite redecidir |
| Auditoria da decisão | Parcial | `decided_by/at/notes` gravados; sem log |
| Aviso aos moderadores | **Falso** | `ModerationNotifier` só guarda array em memória; nada é enviado |
| Fallback de destinatário | **Inseguro** | Endereço inventado `moderacao@aculta.org` |
| Falha/reenvio de aviso | Ausente | — |
| Fila (`UgcReportQueue`) | Parcial | Sem checagem de acesso, só `pendente`, contagem truncada por `range(limit*5)`, sem paginação, não exclui alvo inexistente/inacessível |
| Migração `migrateFromReportQueue()` | Parcial | Sem dry-run, sem vínculo, sem relatório, deduplica por par e ignora status; depende de classe do `aculta_user` sem declarar dependência |
| Integração Fórum/Wiki/Revista/Eventos | Ausente | Ver `ARQ-06-HANDOFF-INTEGRACOES.md` |
| Teste Kernel | Parcial | 4 testes; sem acesso cruzado, purposes, notificação real, migração com dados |

## 3. Riscos de segurança encontrados

1. Denúncia de entidade que o denunciante não pode ver (enumeração de conteúdo privado/rascunho).
2. Purpose escolhido pelo chamador: redireciona o aviso e a fila para outra área.
3. Moderador de uma área decide denúncias de todas as outras.
4. Status arbitrário e redecisão apagam o histórico da decisão.
5. Aviso falso: a equipe acredita estar avisada e não está.
6. Endereço operacional inventado como fallback.
7. Fila sem access check: qualquer consumidor futuro expõe todas as denúncias.

## 4. Conflito do limite diário

`DAILY_LIMIT = 20` foi mantido como **valor provisório não homologado**, apenas para não remover a
proteção existente. Não foi promovido a configuração nem a decisão. **Decisão pendente do
responsável:** valor do limite por conta/dia, janela, e se vale por purpose. Enquanto isso o teto
continua sendo o comportamento seguro (restritivo) já existente.

## 5. Plano de correção

| Etapa | Entrega |
| --- | --- |
| ARQ-06A | Políticas de `ReportService`, escopo de moderação por purpose, testes de acesso |
| ARQ-06B | `ModerationNotifier` via `aculta_mailer`, tipo de e-mail, idempotência, reenvio |
| ARQ-06C | `UgcReportQueue` com acesso, agregação em SQL, paginação, exclusão de alvos inválidos |
| ARQ-06D | Migração com dry-run, vínculo legado, relatório, rollback |
| ARQ-06E | Handoff para Fórum, Wiki, Revista, Eventos (sem editar módulos alheios) |

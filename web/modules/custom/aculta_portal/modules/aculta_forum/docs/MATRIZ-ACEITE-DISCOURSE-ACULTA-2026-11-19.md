> **POLÍTICA VIGENTE (2026-11-19):** [acervo histórico Growroom](POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md). Comentários históricos **não são comentários padrão** e **não serão migrados** para ForumPost. Coexistência técnica não autoriza conversão, mesclagem, recontagem como atividade corrente ou moderação pela fila comum. A separação física requer inventário e homologação; alterações desta PR são apenas guardas iniciais.

# Política ACULTA Forum: clone funcional Drupal do Discourse

Decisão de produto aprovada em 2026-11-19. **Paridade funcional e de experiência com Discourse**, implementação nativa sobre Drupal 11/12, identidade ACULTA e UI Suite/SDC como arquitetura visual obrigatória. O `aculta_forum` é o **motor único de discussões e comentários de todos os módulos do site**. Isso **não** significa transformar qualquer comentário editorial, pedagógico ou restrito em tópico público; cada host preserva entidade, acesso, workflow e propósito. A moderação de denúncias e os casos usam `aculta_cgu` como dono, com adapters de ações por contexto.

## Matriz executável de aceite e gates de release

Legenda: I = teste a implementar; V = teste histórico a reexecutar no HEAD; sem resultados de execução nesta rodada.

| ID | Cenário / provedor | Tipo | Resultado obrigatório |
| --- | --- | --- | --- |
| T-01 | Citação de Comment LMS/Group privado por ID em tópico público | Kernel + Functional adversarial | Nenhum texto privado aparece; HTTP negado ou hint ignorado; sem side effects |
| T-02 | Criação/reply/quote/edit/preview/erro no Growroom | Functional + browser HTMX | Nova resposta persistida em ForumPost, pai correto, popup na mesma página, fallback funcional |
| T-03 | Wiki `wiki_entry.field_aculta_discussion` | Kernel + browser | Motor Fórum único no verbete, ACL do verbete, citação e permalink correto |
| T-04 | Revista `revista.field_aculta_discussion` | Kernel + browser | Motor Fórum único na publicação, `EditorialCapability`, ordenação e moderação preservadas |
| T-05 | LMS `aculta_lms_topic.field_aculta_discussion` | Kernel + browser | Group/matrícula/Educador, isolamento turma e curso, nenhum acesso após revogação |
| T-06 | LMS `aculta_lms_answer.comments` | Kernel + browser | Feedback avaliativo preservado, sem publicação transversal, editor correto e retenção segura |
| T-07 | GET/HEAD em aprovação/rejeição/exclusão | Functional | Nunca altera DB; POST Form API+CSRF obrigatório |
| T-08 | Duplo POST e duas decisões paralelas | Kernel MariaDB concurrency | No máximo uma resposta/caso/ação lógica; retry idempotente, sem avisos duplos |
| T-09 | Erro entre despublicar tópico e enfileirar exclusão | Kernel transactional | Rollback/estado consistente; estado anterior restaurado ao rejeitar quando autorizado |
| T-10 | URL antiga e permalink pós-migração | Functional | Link chega ao host na página e âncora correta; nenhuma fuga de contexto |
| T-11 | Reações em Comment oculto/pendente | Kernel | Negadas por ACL do próprio alvo e do host, métricas não contam privadas |
| T-12 | CGU cria denúncia Comment wiki/revista/lms/forum | Kernel | Purpose derivado do host sem falso forum; Educação limitada por Group |
| T-13 | CGU decisão x ação | Kernel | Julgar denúncia não oculta conteúdo por si; comando tipado tem ACL, auditoria e tratamento de erro |
| T-14 | CGU Flagging legado e novo | Kernel + reconciliation | Sem duplicação nem reabertura indevida, histórico preservado |
| T-15 | Index provider: Forum Topic/Post/Comment | Kernel + Index Access | Relações e projeção correta, revogação revalidada antes da paginação |
| T-16 | Index global: LMS/Wiki/Revista Comments | Kernel | Não aparecem no catálogo público Growroom; catálogo contextual só com autorização e provider preciso |
| T-17 | Search API completo | Kernel + Functional | Texto público autorizado de tópicos, ForumPosts e Comments legados achado, privados zero |
| T-18 | Statistics FST: create/replay/migrate/unpublish | Kernel + snapshots | Contagens por tipo sem duplicar nem expor contexto restrito; ledger e reconcile coerentes |
| T-19 | Index × Stats ponte | Kernel | Só `forum.replies.created` de node topic autorizado e permissão de métrica, agregação restrita |
| T-20 | Messenger/notice/digest | Kernel | 1 aviso por evento publicado, revogação e opt-in rechecados, sem notificar com payload privado |
| T-21 | 320/390/768/1024/1440 px, light/dark/skins | Playwright/browser | Sem overflow, geometria igual, contraste WCAG AA, atributos Core em SDCs |
| T-22 | UI Patterns Views/Layouts/Field Group | Config Kernel + render/browser | Styles ativos conforme Config Sync, slots, campos, pager, filters, access/cache iguais |
| T-23 | Teclado/leitor de tela/zoom e sem-JS | Browser a11y | Tab/foco/aria/announce no popup, timeline acessível, link/citação funcional sem JS |
| T-24 | Desempenho thread 100/1k/10k posts | Benchmark | Registrar query count/p50/p95/memória, estabelecer SLO antes de RC; sem N+1 explosivo |
| T-26 | Histórico Growroom vs Comments padrão | Kernel/Functional | Acesso e URLs históricos preservados; nenhum writer/quote/feedback trata arquivo como corrente |
| T-27 | Index/Statistics/CGU em históricos | Kernel + reconciliação | Não contar histórico como novo, nem expor no índice/fila correntes; descoberta própria preservada |
| T-28 | Composer novo Growroom | Kernel + browser | Cria apenas ForumPost, jamais Comment Core; fluxo sem JS permanece funcional |
| T-25 | Segurança de UI Patterns | Composer lock + security audit | Confirmar release instalada corrigida para SA-CONTRIB-2026-075 e ausência de configuração insegura |

## Ampliação vinculante de aceite T-29 a T-40 (2026-11-20)

Todos iniciam em **PLANNED** e só viram PASS com comando/ambiente/commit/resultado. [Política frontend global](../../../../../../../docs/portal/FRONTEND-OPERACIONAL-BACKEND-EXCLUSIVO-ADMIN-2026-11-20.md) e [plano executivo atualizado](PLANO-PARIDADE-DISCOURSE-FRONTEND-MODERADOR-ENQUETES-2026-11-20.md).

| ID | Cenário | Aceite mínimo |
| --- | --- | --- |
| T-29 | Papéis não-admin nas rotas backend | Moderador, content_editor, Educador e gerente, isolados/combinados, recebem 403/404 em rotas administrativas GET/POST/HTMX/JSON; administrator mantém acesso |
| T-30 | Moderação via ACULTA Studio | Moderador opera fila CGU na aba Fórum do Marley e popups nos tópicos; sem backend nem privilégios globais |
| T-31 | Frontend editorial | Content_editor conclui tarefas autorizadas de Wiki/Marley sem redirecionamento ao backend |
| T-32 | LMS pedagógico independente | Educador só pode moderar turma/curso/Group autorizado em GanjaLab; moderador global/editor não recebe acesso |
| T-33 | Studio transversal | Todas as suítes usam registry/shell/navigation do Studio e Form API/ACL dos owners, sem painéis paralelos ou fallback admin |
| T-34 | Popup ponta a ponta | Form API/CSRF/validação/feedback/fragmento/foco/aria/fallback sem JS, sem mutação GET |
| T-35 | Split/merge do storage real | Transação e locks com ForumPost atual; integridade de revisão, thread, flags, permalink, cache e dados históricos |
| T-36 | Poll Builder | Criar tópico com/sem enquete, única/múltipla, 2..20 opções, fechamento opcional, resultado e atomicidade |
| T-37 | Voto e ACL | Voto único lógico por conta, concorrência/idempotência, revogação de acesso, tópico fechado/despublicado/histórico |
| T-38 | Privacidade da enquete | Cédula anônima para público, número de votantes distinto de seleções múltiplas, audit trail de encerramento/reabertura |
| T-39 | Integração FST | Eventos de enquete deduplicados, snapshots reconciliados, analytics público não contém UID, conteúdo nem votos individuais |
| T-40 | Privilégio cumulativo | Somar moderador + content_editor ou demais papéis não contorna backend/admin-only nem o contexto LMS |


**T-41 - Marley Fórum:** o `ForumTab` deve deixar de apontar à rota admin `aculta_user.denuncias_forum`; o papel `moderador` acessa somente a fila CGU frontend autorizada, não abas Revista/Galerias ou GanjaLab por herança.

**T-42 - Ownership Studio:** todos os fluxos operacionais de não administradores usam os componentes de suíte `aculta_studio` sem duplicar regras de módulos owners; popups diretamente nas páginas permanecem válidos.

## Política de status

Cada feature registra `planned | code | unit | kernel | functional | browser | runtime | production` com referência a commit, comando, duração, resultado e evidências; falha/BLOCKED/DEFERRED não se tornam PASS. Matriz de papéis: anônimo, autenticado, autor, editor Fórum, editor Revista, editor Wiki, Educador LMS, membro do curso, membro do Group restrito, não-membro, moderador CGU por purpose, global CGU, admin MAIN. Cobrir domínio incorreto, cache quente e revogação entre duas requisições.

Release candidata: todos gates P0/P1, migração (se autorizada), integridade, UI Suite em consumers reais e testes de acessibilidade obrigatórios comprovados. Promoção de dev para RC só após validação Runtime real e autorizações exigidas em AGENTS.md.

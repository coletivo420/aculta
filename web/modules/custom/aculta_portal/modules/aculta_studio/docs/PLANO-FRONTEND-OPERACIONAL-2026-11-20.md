> **FDC-R14 (2026-11-20):** o Studio só apresenta indicadores próprios do Fórum; não mantém segundo motor de contagem. Reação via HTMX atualiza cartões e usuários visíveis, conforme [plano T-62..70](../../aculta_forum/docs/PLANO-REACOES-SINCRONIZADAS-BLOCOS-2026-11-20.md). A implementação não reduz as demais obrigações frontend do Studio e backend admin-only.

> **CONTINUIDADE 2026-11-20:** O Fórum será concluído via [prompt integral na branch PR #394](../../aculta_forum/docs/HANDOFF-AGENTE-CLI-FORUM-COMPLETO-2026-11-20.md). A implementação NÃO termina com reações: engloba Studio em todas as suítes, papel moderador sem backend, fila CGU Marley, enquetes e comentários contextuais de LMS/Wiki/Revista, com testes T01..61 e DV01..19. Não há autorização implícita para sincronizar Runtime dev.

> **AMPLIAÇÃO 2026-11-20 - perfil e Game:** o Studio é compositor de indicadores e conquistas de `aculta_game`/`aculta_statistics`/User, não dono de persistência; adicionar no dashboard pessoal e apresentação autorizada, e no Marley estatísticas de moderação sem dados privados de denunciante. [Estudo/FDC-R9..R13](../../aculta_forum/docs/ESTUDO-DISCOURSE-GAME-METRICAS-INTEGRACOES-2026-11-20.md). Não criar leaderboard ou painel redundante.

# ACULTA Studio - implementação das interfaces operacionais frontend

**Decisão vigente em 2026-11-20:** o `aculta_studio` é o framework compartilhado de **todas** as interfaces operacionais frontend. O backend de todo o portal é exclusivo de `administrator`. Moderação comunitária é distinta de moderação pedagógica. Este documento é planejamento (não comprova implementação no Runtime). Base de código auditada: branch `feat/forum-paridade-discourse` em 2026-11-20.

Documentos relacionados: [política transversal](../../../../../../../docs/portal/FRONTEND-OPERACIONAL-BACKEND-EXCLUSIVO-ADMIN-2026-11-20.md), [roadmap Fórum/Discourse/enquetes](../../aculta_forum/docs/PLANO-PARIDADE-DISCOURSE-FRONTEND-MODERADOR-ENQUETES-2026-11-20.md).

## Fronteiras arquiteturais obrigatórias

- **ACULTA Studio**: registry de experiências, providers, shell e navegação, rotas frontend por purpose, tabs, descoberta e composição. Não criar segundo hub, painel genérico de moderação ou duplicação de páginas em cada módulo. **Não existe hub público `/studio`**; manter rotas canônicas por domínio.
- **Módulos donos**: entidades, Form API, workflow, serviços, operações, ACL e cache. Exemplos: `aculta_forum` faz ações de tópicos/ForumPost/Comment, `aculta_cgu` decide denúncias e casos, `aculta_magazine` publica, `aculta_lms` modera pedagogia via Group e Educador. Não mover regras de negócio ao Studio.
- **Tema `aculta420_v2`**: SDCs, popup, UI Patterns/Styles/Skins, navegação visual, claro/escuro; sem CSS de domínio no Studio.
- **`aculta_statistics`**: catálogo, métricas, ledger, snapshots e relatórios autorizados de Fórum e enquetes. O Studio apresenta apenas indicadores permitidos, sem outro motor analítico.
- **Ações contextuais públicas**: abrir popup de criação de tópico, votação ou moderação no próprio conteúdo continua permitido e necessário. Isso não implica que o painel operacional/filas pertença ao site público. O **workspace de moderação comunitária fica na aba Fórum do Studio Marley**; popups diretos na página chamam os mesmos comandos.

## Mapa de suítes e fluxos

| Suite existente | Purpose / rota canônica | Destino operacional frontend | Autorização/owner |
| --- | --- | --- | --- |
| **Marley - Fórum** | `account:/marley`, aba `forum` | Fila CGU comunitária, revisão de ForumPost/tópico/Comment, ações auditadas em popup | Moderador com capability específica; `aculta_cgu` + `aculta_forum` |
| **Marley - Revista/Galerias** | `account:/marley` | Autoria, edição, revisão e moderação editorial por popup/Forms | `content_editor` e capabilities `aculta_magazine`/`aculta_gallery` |
| **GanjaLab Educador** | `courses:/ganjalab/educador` | Cursos, turmas, material, feedback e **moderação pedagógica** | `aculta_lms` e Group/turma/curso; NÃO CGU comunitário |
| **GanjaLab Estudante** | `courses:/ganjalab` | Jornada e participação de estudante | LMS e matrícula |
| **WE Sativa** | `wiki:/we-sativa` | Propostas, verbetes, edição, revisão e comentários | `aculta_wiki`, providers e revisão editorial |
| **Green Stash** | `shop:/green-stash` | Operações de loja autorizadas | Commerce e escopo da loja; financeiro institucional admin-only |
| **Culturas** | `eventos:/culturas` | Eventos, locais, organizações, gestão e revisão contextual | `aculta_events`/owners |
| **Conta e outras** | `account:/`, `/conta`, `/meu-perfil`, extensões registradas | Interface própria de cada provider dentro do Studio | owners e roles contextuais |

## Achado de código que bloqueia o backend admin-only

O plugin existente `aculta_studio/src/Plugin/MarleyTab/ForumTab.php` registra a aba `forum` da Marley e exige `moderar denuncias do forum`. Entretanto, a ação aponta para a rota `aculta_user.denuncias_forum` no propósito MAIN. O arquivo `aculta_user.routing.yml` confirma que essa rota é **`/painel-administrativo/forum/denuncias`**, com `_admin_route: true`, protegida apenas pela permissão antiga. Assim, hoje a aba oferece **link administrativo**, incompatível com a decisão nova.

**Correção planejada:** criar a experiência de fila/ações CGU no frontend Studio Marley, alimentada pelos serviços do `aculta_cgu`; atualizar o `ForumTab` para capability granular e rota do Studio, mantendo o ownership no CGU. Não duplicar `ReportService`, não expor `/painel-administrativo` e não criar `/moderacao` pública concorrente. `RevistaTab.php` também usa `administer comments` para habilitar moderação: migrar para capability editorial contextual sem elevação global.

## Contrato de papéis e rotas

- `moderador` (novo, menor que `content_editor` em capacidades) acessa apenas a aba Fórum de Marley, quando autorizado; não recebe Revista/Galerias, Wiki, LMS ou backend por herança. Config Sync já define role bootstrap sem privilégios de moderação até ACL estar pronta. Peso do papel Drupal **não dá herança**.
- `content_editor` opera edições/revisões na respectiva suíte do Studio; moderador comunitário só se receber concessão explícita. Sem backend.
- `lms_teacher` opera pedagogia em GanjaLab com Group/curso/turma; nenhuma permissão de `moderador` ou `content_editor` confere esse acesso.
- `administrator` é o único a usar rotas administrativas Core/contrib/custom, incluindo `/admin`, `/painel-administrativo`, APIs AJAX/JSON associadas, Views administrativas e revisões. Proteção **servidor**, não esconder menu. Matriz de roles acumulados, UID1, `_admin_route` e paths excepcionais exigida.
- Preservar popup `aculta420_v2:popup` e Form API/HTMX diretamente nos conteúdos; comandos são únicos e access-check por purpose/host, sem redirecionar moderador para backend.

## Roadmap ST-OP (integrado ao FDC-R0 a FDC-R8)

| Fase | Implementar | Gate |
| --- | --- | --- |
| **ST-OP-00** | Inventariar providers/rotas/links de todas as suítes, ações que levam ao backend e papéis em Config Sync/Runtime | Matriz `role + suite + operation + route + owner + purpose` |
| **ST-OP-01** | ACL backend exclusivo a `administrator`, role `moderador` com capacidades finas; cutover de operações existentes em etapas | GET/HEAD/POST/HTMX/JSON 403 para não-admin em backend; frontend preserva função |
| **ST-OP-02** | Reformar aba Fórum do Marley para Review Queue CGU frontend e moderação ForumPost por popup | `moderador` acessa **apenas** a aba Fórum e nenhum link/admin; popups atualizam sem redirect |
| **ST-OP-03** | Convergir Marley Revista/Galerias, WE Sativa, Green Stash, Culturas com owners Form API | Sem permissões `administer comments` como atalho; sem página admin |
| **ST-OP-04** | Completar GanjaLab Educador/feedback/moderação por Group, isolado de CGU comunitário | Não-membro e moderador global não veem cursos; revogação bloqueia POST |
| **ST-OP-05** | UI Suite/SDC, Statistics, navegação acessível e fallback sem JS | Kernel + Browser + zoom/contraste + cache/ACL cross-purpose |
| **ST-OP-06** | Corte/retirada de links e grants backend depois de validação e rollback | Testes reais de Runtime autorizados e CI verde |

Esta atualização **não executa o corte** de backend, nem toca dados/banco. Não marcar fases como PASS sem testes de rota, entidade, Browser e autorização.

## Testes adicionais

- **ST-01**: moderador isolado vê somente Marley Fórum, nunca Revista/Galerias; sessão e cache aquecido.
- **ST-02**: ForumTab não aponta `aculta_user.denuncias_forum`; painel CGU Studio funciona e rota antiga é negada ao moderador inclusive POST.
- **ST-03**: content_editor opera Marley/WE Sativa sem backend; Educador opera GanjaLab só pelo Grupo.
- **ST-04**: popups no conteúdo público se integram aos mesmos serviços, mas não concedem entrada Studio/CGU sem role.
- **ST-05**: experiências opcionais ausentes não geram abas mortas; nenhuma exposição de métricas/votos privados entre propósitos.

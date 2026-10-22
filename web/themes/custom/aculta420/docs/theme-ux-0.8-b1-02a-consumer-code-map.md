# THEME-UX 0.8-B1-02a — Mapa de consumidores verificados no código

**Data:** 2026-10-22 · **Método:** inspeção da branch GitHub, sem Runtime. **Estado:** B1-02a inventário de código parcial concluído; integração real ainda pendente.

## Pontos encontrados na árvore
| Alvo | Localização comprovada no código | Renderização conhecida | Lacuna antes de implementar |
| --- | --- | --- | --- |
| GanjaLab420 dashboard | `web/modules/custom/aculta_portal/modules/aculta_lms/aculta_lms.routing.yml`, rota `aculta_lms.student_dashboard`, path `/ganjalab420` | controller `LearningDashboardController::student`, `_title: GanjaLab420`, container com H2 de cursos, `no_cache` e purpose account na rota | confirmar HTML de page title, shell, acesso, se dashboard é primeiro consumidor adequado |
| Curso/sala | `web/modules/custom/aculta_portal/modules/aculta_lms/src/CourseRoomBuilder.php` | builder de render array de curso, access via `takeAccess` e metadados de cache/Group | confirmar rota/entidade LMS que incorpora o builder e títulos na página; não presumir caminho de aula |
| Wiki home/busca | `web/modules/custom/aculta_portal/modules/aculta_wiki/aculta_wiki.routing.yml`, rotas `aculta_portal.wiki_home` (`/wiki`) e `aculta_portal.wiki_search` (`/wiki/busca`) | `WikiController`, com `_title` e cache contextual no home | confirmar títulos e propósito no HTML |
| Wiki verbete completo | `web/themes/custom/aculta420/templates/node--wiki-entry--full.html.twig` | chama SDC `aculta420:wiki-entry` variante `article` | **já tem H1 dentro do SDC**; coordenar refatoração, não adicionar H1 |
| SDC Wiki existente | `web/themes/custom/aculta420/components/patterns/wiki-entry/wiki-entry.twig` | variante teaser usa H2; variante article emite `h1.aculta-wiki-article__title` em `header` e nav de ações | decidir se Context Header absorve H1/lead/eyebrow existentes preservando o restante do Wiki SDC |
| Revista | `web/themes/custom/aculta420/components/patterns/revista-publication/revista-publication.twig` | SDC de publicação apresenta meta/capa/subtítulo/corpo, sem H1 explícito neste Twig | conferir `node--revista--full.html.twig`, região page title e se Revista é melhor segundo consumer |

## Decisão arquitetural a partir do código
- **H1 principal sob ownership do SDC** é autorizado, mas a Wiki já usa um SDC que imprime H1. O futuro Context Header não deve inserir segundo H1: migração deve deslocar/transferir a saída de título do `wiki-entry`, ou escolher outro consumidor primeiro.
- **Não alterar agora `wiki-entry`**, pois pode afetar layouts e classes existentes. Primeiro medir DOM, CSS/JS e comportamento de acesso/cache no Runtime e definir transformação backward-compatible quando necessária.
- Em GanjaLab420, `_title` da rota e H2 do controller existem, mas não provam a hierarquia H1 final; validar template de page title e propósito na tela real antes de integrar.
- Não reutilizar `content-section` (H2/H3) como H1; não utilizar `product-tabs` para navegação entre rotas.
- Institution Bar e Domain Header permanecem globais e **não fazem parte do Context Header**.

## Próximo micro-lote B1-02b (condicionado)
1. Capturar DOM/H1 real na rota GanjaLab420 e na Wiki verbete (ou Revista), incluindo Shell e breadcrumb.
2. Auditar os contratos SDC que já emitem heading e classes CSS dependentes do heading Wiki.
3. Escolher uma migração pequena: SDC Context Header só se a composição não estiver resolvida nos SDC atuais; preservar H1 único e fornecer dados pelo provider.
4. Stories determinísticas, UI Patterns/Styles e testes no homelab; registrar duas telas realmente usando a mesma apresentação.
5. Validação mobile, escuro/claro e navegação sem JS antes de marcar PASS.

**Não certificado:** SDCs no Runtime, dois consumidores compartilhando Context Header, acesso/caches e browsers. Este mapeamento é de código fonte, não implantação.

# THEME-UX 0.8 — B1-02e: homologação após segundo consumidor

**Data:** 2026-10-22 · **Estado:** integração de código concluída, homologação Runtime pendente.

## Mudanças de implementação registradas
- Wiki: `templates/node--wiki-entry--full.html.twig` usa `aculta420:context-header`; SDC `wiki-entry` desativa seu próprio heading nessa variante para evitar H1 duplicado.
- GanjaLab420: `aculta_lms/src/Controller/LearningDashboardController.php::student()` inclui `#type: component`, `#component: aculta420:context-header` e props title/eyebrow/subtitle.
- A seção de listagem `Meus cursos` tinha um H2 com o mesmo texto; este H2 foi substituído pelo H1 do Context Header, preservando os H3 dos cursos e o H2 de certificados.
- `ThemeHooks::preprocessPage()` mantém a lógica pré-existente de supressão seletiva de page title da Wiki e acrescenta `aculta_lms.student_dashboard` à mesma condição. **O `_title` da rota permanece**, não há supressão global de blocos nem intervenção no domínio.
- Shell multidomínio `page.html.twig` não modificado.

## Checklist obrigatório no homelab
| Verificação | Esperado | Status |
| --- | --- | --- |
| Drupal cache rebuild + lint PHP + Twig | sem erro de schema/componente | PENDENTE |
| `/ganjalab420` autenticado | exatamente um H1 `Meus cursos`, cards e certificados intactos | PENDENTE |
| `/ganjalab420` anônimo | política de acesso atual preservada | PENDENTE |
| Verbete Wiki completo | exatamente um H1, resumo, ações, referências intactos | PENDENTE |
| Wiki teaser | H2 e semântica anterior preservados | PENDENTE |
| Institution Bar + Domain Header | ambos visíveis e sem duplicação em LMS/Wiki | PENDENTE |
| UI Patterns Library | stories Context Header renderizam sem erro | PENDENTE |
| UI Styles | tokens semanticamente coerentes claro/escuro | PENDENTE |
| Viewports | 320, 390, 768, 1280, 1440px; sem overflow | PENDENTE |
| A11y | teclado, focus-visible, heading outline, zoom 200–400% | PENDENTE |
| Cache/access/Group | dados e permissões intactos | PENDENTE |
| Navegação sem JS | links existentes continuam utilizáveis | PENDENTE |

## Observações de risco
- O cabeçalho contextual não possui `back_link`, `breadcrumbs` nem `actions` no dashboard até haver providers reais; não fabricar URLs de volta.
- A integração depende do tema público ACULTA420 habilitado e da descoberta Core SDC. Validar renderização real antes do release.
- O output final de H1 depende também de blocos no Runtime; o hook evita duplicação no shell previsto no tema, mas requer inspeção de HTML.
- A classe antiga de heading Wiki pode ter estilos específicos; validar a aparência e ajustar no design system sem copiar CSS legado.

## Próxima fase visual: B2 — Context Tabs
Realizar auditoria de navegação já existente no GanjaLab420, Wiki, Conta e Fórum; apenas reutilizar/estender SDC conforme Core/UI Suite; criar novo SDC de navegação quando dois consumidores reais precisarem. Links reais e progressive enhancement; AJAX pertence ao módulo. Preservar shell permanente e um H1 por página.

**Não declarar PASS até concluir os testes no ambiente.**

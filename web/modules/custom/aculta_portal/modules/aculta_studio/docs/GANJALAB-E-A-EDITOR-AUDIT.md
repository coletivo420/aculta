# GANJALAB E-A — Auditoria da interface editorial e início publication-first

**2026-10-22 · implementação incremental na PR #297 · sem homologação Runtime.**

## Objetivo de UX

Publicar deve ser fácil: entrar em `conta.aculta.*/studio/ganjalab`, escolher o que criar e continuar o trabalho com o mínimo de navegação. Mobile-first (320px primeiro), texto humano, ações evidentes, sem menus administrativos concorrentes. A barra multidomínio global permanece intacta. A interface pertence ao Studio; o design pertence ao tema ACULTA420 (Core SDC + UI Suite + tokens). Dados, formulários, Content Moderation, grants e operações permanecem nos módulos donos.

## Inventário de código existente

| Área | Plugin | Criação autorizada existente | Listagem / pendência |
| --- | --- | --- | --- |
| Revista | `RevistaTab` | `node.add` para `revista`, purpose magazine | `StudioListingBuilder` lista 50 antes do filtro da última revisão; não é 'Meus itens' |
| Fórum | `ForumTab` | `aculta_forum.topic_add`, purpose forum | links públicos e fila Comment genérica; moderação precisa de provider especializado |
| Wiki | `WikiTab` | não há seção `criar` exposta hoje | listagens e revisões genéricas; WE SATIVA autor e GanjaLab moderador não são equivalentes |
| Cursos | `CursosTab` | `entity.group.add_form`, Group `lms_course`, purpose courses | turmas/contexto exigem grants Group e navegação de curso, sem cópia de LMS |
| Galerias | `GaleriasTab` | `entity.media_gallery.add_form`, purpose main | formulário em outro domínio; conferir acesso e destino real em HTTP |

As ações declaradas pelos plugins passam por `routeAction()` e `StudioPresenter::sectionsFor()`; o Presenter remove ações cuja rota do dono não autoriza. **Não deduzir permissão apenas por plugin ou papel.** Não criar conteúdo nem habilitar moderação por um novo atalho.

## E-A.1 entregue: entrada publication-first

No `StudioController::page()`, a entrada canônica do GanjaLab (quando sem área explícita) oferece:
- cabeçalho `aculta420:context-header` com convite curto à criação;
- cartões `aculta420:card` dentro de `aculta420:content-grid`, somente com ações permitidas em seções `criar` realmente existentes;
- links nativos aos formulários dos módulos donos, propósito/ACL preservados;
- navegação editorial pré-existente continua abaixo; sem cópia de Form API, sem componente CSS/Twig paralelo, sem exigir JS.

O mesmo layout pode aparecer no alias legado `/ganjalab` ao entrar sem área; isso é compatibilidade de navegação, não novo caminho canônico. Não exibir atalhos fictícios para Wiki. A ação da galeria pode ficar ausente quando seu formulário não autoriza a conta no domínio alvo.

## E-A.2 e E-B — próximos incrementos

1. Medir no Runtime quais rotas/forms funcionam (Revista, Fórum, Galeria, Cursos), incluindo o retorno ao GanjaLab e mensagens de erro; não fingir experiência inline se o dono ainda abre o form externo.
2. Revisar UX de criação em 320/390/768/1280/1440px, teclado, foco, contraste, 200% zoom, dark/light com idêntica geometria; decidir melhorias no componente SDC do tema, não no PHP do Studio.
3. Priorizar **Meu trabalho** com filtro real de autor aplicado antes da paginação, AccessResult/cache e revisão visível; separar equipe e moderação com grants específicos. Não usar a listagem `Todos` como substituta de 'Meus'.
4. Integrar fluxo editorial Revista criar → rascunho → prévia → revisão pelas transições reais e Form API, sem publicar automaticamente.
5. Mapear suporte de rascunhos e autosave antes de prometer continuidade offline, gravação automática ou publicação em um clique.
6. Testes Drupal Unit/Kernel/Functional e Domain account obrigatórios antes de promover a entrega a pronta; **nenhum foi executado nesta sessão**.

## Dependências paralelas

G420-C2-T (estudante) segue pendente de fixtures LMS/tema. Wiki moderação exige provider revision-safe; Fórum e LMS têm frentes próprias. O Studio deve orquestrar consumidores concretos e nunca bloquear a autoria Revista enquanto outras interfaces amadurecem.

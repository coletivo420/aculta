# ACULTA Studio — conceito visual e experiência editorial
**Versão do contrato:** 0.2.0-dev.1 · **Estado:** proposta normativa de design, não implementação · **Data:** 2026-10-22
**Escopo:** aculta_studio + apresentação exclusiva no tema ACULTA420. **Referências:** `docs/decisions/ADR-011-aculta-kernels-studio.md`, `docs/portal/COMPONENT-DESIGN-SYSTEM.md`, `docs/portal/FRONTEIRA-MODULO-TEMA.md`, `web/themes/custom/aculta420/docs/architecture.md` e [fluxos de criação e edição](STUDIO-EDITOR-FLOWS.md).

## 1. Princípio central: meu espaço primeiro
O Estúdio é principalmente **o espaço de trabalho de quem está autenticado**. Em todas as áreas, a primeira listagem e os principais indicadores são, por padrão, **Meus [itens]**, referentes a conteúdo cuja autoria/titularidade efetiva pertence ao usuário atual, conforme contrato do módulo dono (Node owner, autor do post, responsável permitido no LMS/Group etc.). **Não** supor que todos os objetos usam `uid`, e **não** confundir autoria, propriedade, matrícula, participação ou permissão para editar.

Pessoas com permissões editoriais, de moderação ou administração podem também operar sobre itens de terceiros, **em contexto separado e rotulado**: “Equipe”, “Fila de revisão”, “Moderação” ou “Todos os conteúdos autorizados”, nunca misturados silenciosamente no espaço “Meus”. Não fornecer uma opção de filtro “Todos” que inclua terceiros na vista pessoal sem explicar seu universo; os filtros de estado “Todos / Rascunhos / Em revisão / Publicados / Arquivados” referem-se somente ao escopo pessoal enquanto o contexto atual for “Meus”.

O rótulo e a ordenação respeitam o contexto: Revista → Minhas publicações; Fórum → Meus tópicos (com seção distinta para minhas respostas quando houver provider); Wiki → Meus verbetes / Minhas contribuições, sem atribuir a propriedade de verbetes coletivos à pessoa; Cursos → Meus cursos como docente/autor (e conteúdos sob responsabilidade quando autorizado), com espaço próprio para turmas e atribuições, distinto de “cursos em que estou matriculado”; Galerias → Minhas galerias/álbuns; demais áreas futuras recebem nomenclatura equivalente definida por seu dono. Para edição em equipe, conservar sinalização inequívoca de quem é autor e quem está editando.

**Regras de acesso e cache:** o filtro pessoal é aplicado na consulta do servidor, antes da paginação e das contagens; `accessCheck(TRUE)` e Entity Access continuam obrigatórios, mas não substituem restrição explícita de titularidade. Acesso a ações é verificado por entidade/operação/revisão, domínio/purpose e, onde necessário, Group. Cache contexts `user` / `user.permissions` / rotas e tags corretas devem refletir escopo e resultados; nenhuma contagem de outros autores ou de áreas restritas pode vazar. Troca de contexto não altera privilégios.

## 2. Linguagem
**Editorial Workspace:** visual acolhedor de revista, com espaço operacional preciso. Preservar tipografia editorial do ACULTA420 no título da área, tipografia de UI legível em controles, superfícies claras tipo papel, verde institucional para identidade e um CTA principal bem distinto; no escuro, mudar exclusivamente tokens semânticos, sem alterar layout. Não fixar hexadecimais em componentes de produção: cores do protótipo são indicativas, tokens existentes do tema são a fonte canônica. Não criar um design system paralelo.

Reduzir ruído: uma única hierarquia principal, espaçamentos coerentes, cartões de densidade moderada, bordas leves, estados tipográficos legíveis, ícones acompanhados de rótulos acessíveis. Área, seção, estado e ação são conceitos **diferentes**.

## 3. Arquitetura de navegação
- **Desktop (>= 1024px como referência, breakpoint ajustável por teste):** cabeçalho do portal preservado; Studio com navegação lateral recolhível de áreas; região central contextual com título, subtítulo “Meu espaço”, CTA “Criar” (apenas quando autorizado), buscas/filtros e listagem. As seções da área ficam em submenu contextual. Não repetir abas horizontais, sidebar e breadcrumb para a mesma seleção.
- **Tablet:** navegação lateral compacta ou offcanvas; conteúdo central flexível; ações importantes sempre visíveis.
- **Mobile (320–767px como faixa de testes):** seletor de áreas/offcanvas em vez de cinco abas quebrando linha; título e CTA visíveis; seções por menu contextual; filtros em chips com rolagem horizontal acessível ou menu de filtros; listagem convertida em cartões (nunca tabela cortada); navegação inferior **somente se não duplicar** a navegação global. Não usar largura fixa que cause overflow.
- **Roteamento:** manter as quatro URLs reais `/studio`, `/studio/{área}`, `/studio/{área}/{seção}`, `/studio/{área}/{seção}/{aba}`; integração progressiva AJAX existente, `pushState`, `popstate`, foco no novo título, anúncio `aria-live`, fallback full-page e permissões. A home /studio pode ser agregadora, mas o primeiro foco visual é pessoal; em cada área mostrar primeiro a vista pessoal.
- **Persistência:** lembrar área/visualização por URL e histórico; preferências de densidade/tema somente se houver contrato pré-existente e consentimento adequado. Não criar storage paralelo.

## 4. Tela inicial de cada área
Ordem prioritária: (1) título + explicação curta contextual; (2) CTA único autorizado de criação; (3) “Meus [itens]” selecionado explicitamente; (4) busca, estados e ordenação; (5) lista pessoal com metadados essenciais; (6) paginação; (7) “Equipe / Moderação / Todos autorizados” **quando role e permissão de operação permitirem**, como outra vista identificada. Indicadores pessoais (publicados, rascunhos, em revisão) são opcionais e só aparecem com fonte real, contagens no servidor e tratamento de cache; não inventar KPIs.

**Card mobile:** título (até o limite visual, mantendo título completo acessível), estado com texto + ícone, data, autor quando necessário, área/contexto e botão de ação principal (“Continuar”, “Editar”, “Ver”) com menu secundário de ações autorizadas. **Tabela desktop:** coluna de título flexível e prioridades semânticas, status em língua humana, data compacta, autor se o contexto for de equipe, ações explícitas; é aceitável usar a mesma lista de cards no desktop quando o dono tiver poucos campos. Datas localizadas.

**Estados:** lista vazia pessoal (“Você ainda não publicou nesta área”; CTA quando permitido), filtro sem resultados (limpar filtros), fila de revisão vazia, carregamento anunciado, erro recuperável sem perder o formulário, sem acesso e módulo indisponível. Nunca substituir lista pessoal vazia por itens de terceiros.

## 5. Ações e fluxos
A tela de criação, edição, preview, envio à revisão, publicação e moderação segue [STUDIO-EDITOR-FLOWS.md](STUDIO-EDITOR-FLOWS.md), com um **shell de editor** consistente entre áreas, sem converter os formulários Drupal em CRUD paralelo. O layout do formulário pertence ao tema; campos, validação, entidade, status, ownership e autorização pertencem ao provider funcional.

## 6. Contrato de componentes visuais
Candidatos SDC no tema ACULTA420 (nomes finais sujeitos a inventário):
| Componente | Props/slots semânticos | Responsabilidade |
| --- | --- | --- |
| `studio-shell` | título, navegação renderizável, região central, estados | desktop/mobile, landmarks e foco |
| `studio-area-nav` | links autorizados, atual, regiões de menu | sidebar/offcanvas |
| `studio-context-bar` | escopo pessoal/equipe, ações, subtítulo | alternância inequívoca de contexto |
| `studio-toolbar` | busca, filtros, ordenação, CTA | layout responsivo de ferramentas |
| `studio-content-item` | título, estado, autoria/contexto, data, ações renderizáveis | item desktop/card mobile |
| `studio-state` | tipo, mensagem, ação segura | vazio/erro/loading/acesso |
| `studio-editor-shell` | formulário render array, progresso, ações, preview | disposição de Form API |

Os nomes acima não afirmam implementação. Componentes não consultam Entity, Group, Domain ou Views, não chamam service locator nem constroem permissões. SDC não substitui AJAX, Views, Form API, Workflows ou Render API. Preservar `data-portal-ui` e os atributos `data-aculta-studio` existentes, evoluindo-os sem quebrar consumidores; Bootstrap 5 (offcanvas, dropdown, grid, form, nav) primeiro quando adequado.

## 7. Acessibilidade e qualidade
- WCAG 2.2 AA como alvo: teclado, ordem de foco, estado atual, contraste, rótulos de botões só-ícone, erros vinculados a campos, regiões anunciadas, toque >= 24×24 CSS px (preferir 44×44 em alvos primários), `prefers-reduced-motion`, sem dependência exclusiva de hover ou cor.
- Modos claro/escuro idênticos estruturalmente; respeitar preferências persistentes do sistema ACULTA420/conta quando existentes, sem novo controle concorrente.
- Testes em 320, 360, 390, 768, 1024, 1280 e 1440 px; teclado, zoom 200%, zoom/reflow 400%, portrait/landscape, sem JavaScript, AJAX, back/forward, roles distintos e dados longos. Ver `tests/browser/aculta-studio/`.
- Não afirmar conclusão sem screenshot de Runtime real e testes de acesso por usuário/domínio.

## 8. Execução incremental (design, sem mexer no roadmap funcional)
1. **0.2.0-dev.1 (este commit):** conceito, definição de “Meus” e especificação das telas, documentação e links.
2. **0.2.1:** inventário de tokens e componentes existentes; protótipos light/dark e desktop/mobile; aceite visual.
3. **0.2.2:** shell e alternância de escopos pessoal/equipe; semântica e URLs existentes.
4. **0.2.3:** listagens realmente filtradas no servidor, cartões mobile e tabelas desktop, states e busca/filtros.
5. **0.2.4:** editor e integrações com formulários dos providers, preview, revisões e erros.
6. **0.2.5:** acessibilidade, regressões, documentação, snapshots e revisão integrada.

**Regras de PR:** seguir a branch/PR aberta, commits apenas do escopo documental desta entrega, sem nova PR, sem merge/rebase/force-push. Nenhuma migração, exclusão de conteúdo, alteração de permissões ou código de Runtime autorizada por este documento.

# Wiki420 — Experiência visual enciclopédica (plano)

**Versão UX:** W420-UX 0.1.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento, sem implementação nem aprovação Runtime.
**Referências:** [GanjaLab — consolidação visual](GANJALAB420-VISUAL-QUALITY-GATES.md) · [Wiki atual](WIKI.md) · [roadmap transversal do tema](../../web/themes/custom/aculta420/docs/visual-cross-product-roadmap.md).

## Visão de produto
Uma enciclopédia colaborativa, legível e consultável, inspirada na densidade informativa e nos padrões de navegação da Wikipédia, mas visualmente pertencente ao **ACULTA420**. Mobile-first, texto como protagonista, informação verificável, baixa distração, acesso a categorias, busca, referências, histórico e discussão quando essas funções existirem de fato. Não confundir enciclopédia pública com **Studio** de edição (área de autoria do subkernel) nem com Fórum público.

### Regra absoluta de shell
**Institution Bar + Domain Header multidomínio existentes em todas as telas.** A Wiki já possui pesquisa específica no Domain Header, ao lado da marca; essa busca é preservada e aprimorada, nunca duplicada como outro cabeçalho global. Cabeçalho contextual do verbete é *dentro do conteúdo*. A marca, domain/purpose e navegação institucional permanecem no mesmo shell global em modos claro e escuro.

## Arquitetura de navegação
- **Acesso/descoberta:** Página inicial → pesquisa e categorias → listagem/resultado → verbete.
- **Leitura:** verbete → sumário de âncoras autorizadas → seções → referências → relacionados; histórico e discussão como destinos contextuais somente quando rotas/providers de fato existirem.
- **Edição:** ação de editar, revisão ou propor contribuição somente após access do Drupal; edição avançada pertence ao ACULTA Studio/CKEditor, sem UI administrativa na experiência anônima.
- **Busca:** preservar GET /wiki/busca, prévia AJAX /wiki/busca/previa com debounce e descarte de respostas antigas; sem JS o formulário continua funcional. Rotas confirmadas em `aculta_wiki.routing.yml`; não inventar paths de categorias/histórico.

## Telas de experiência — contrato UX

| Tela | Objetivo e hierarquia visual | SDC/reuso candidato | Origem real |
| --- | --- | --- | --- |
| **Home Wiki420** | Introdução curta + pesquisa já presente no Domain Header; explorar categorias, verbetes destacados e atualizações quando retornados por View | `card`, `content-grid`, `empty-state`, `editorial-card` após gate | `WikiController::home` + Views/Taxonomy |
| **Pesquisa/resultados** | Termo e quantidade apenas se fornecidos; resultados relevantes, resumo, paginação, estado vazio e erro | `wiki-entry` teaser, `empty-state`; evitar segundo campo de busca desnecessário | `WikiController::search` + Views |
| **Categoria** | Nome, descrição, breadcrumb, subcategorias e lista com ordenação real | `context-header`, `card`, `content-grid` | Taxonomy/Views; rota concreta a auditar |
| **Verbete** | Breadcrumb/retorno, H1 único, resumo, corpo com seções, figura, caixa lateral discreta, fontes e relacionados | `context-header`, `wiki-entry`, `figure`, `quote`, `context-tabs` somente se existir navegação de rotas | Node/fields/presenter |
| **Histórico/revisões** | Sequência temporal verificável e diferenças se o Core oferecer; mostrar estado e data conforme access | `context-tabs`, `empty-state`, `card` ou timeline SDC apenas se duas áreas precisarem | Core revision/access; não presumir rota pública |
| **Discussão do verbete** | Conversa **restrita ao verbete** usando comentários contextuais; não criar tópico no fórum público | componentes Fórum compatíveis e SDC `author-card` autorizado | `aculta_forum` + Comment, apenas após provider real |
| **Contribuição/edição** | Edição orientada à qualidade, preview, salvar e mensagens de revisão oficiais, sem AJAX inventado | Form API/CKEditor + wrappers SDC acessíveis | Drupal e ACULTA Studio quando habilitados |

## Leitor enciclopédico — layout proposto

### Mobile 320–767px
Ordem: shell global → contexto/voltar → H1 → resumo → figura opcional → **índice recolhível de seções** (quando conteúdo permitir) → texto com referências inline → painéis complementares → referências → relacionados → discussão se houver. Uma coluna, largura fluida; nenhuma sidebar estreita competindo com a leitura. Não ocultar sumário ou conteúdo para simular app.

```text
[Institution Bar — existente]
[Domain Header: marca Wiki420 | Busca — existente]
‹ Wiki420 / Categoria
TÍTULO DO VERBETE (H1)
Resumo do verbete
[Sumário ▾] (se houver seções)
[Imagem + legenda, se houver]
Introdução
## Seção
Texto e referências [1]
[Informações do verbete] (quando houver)
Referências / relacionados
[Discussão contextual] (se habilitada)
```

### Tablet 768–1023px
Leitura central com sumário secundário opcional; mídia e categorias não comprimem o texto; seções longas continuam na coluna principal. Não impor duas colunas quando 200% zoom causar reflow.

### Desktop ≥1024px
Leitura em ~65–75ch, índice fixável **apenas quando não encobrir o shell**, painel contextual à direita (~14–18rem) quando houver imagem/metadados, margens elásticas. Não criar sidebar vazia ou carrosséis decorativos. Em 1440px texto continua limitado em largura.

```text
[Institution Bar + Domain Header multidomínio]
Wiki / Categoria / Verbete
┌──────────────┬───────────────────────────────┬──────────────────┐
│ Sumário      │ H1 + síntese                  │ Figura/infobox   │
│ por âncora   │ Seções + fontes inline        │ (se houver)     │
│ (opcional)   │ Referências / relacionados     │                  │
└──────────────┴───────────────────────────────┴──────────────────┘
```

## Contratos de componentes e decisões

- **SDC existente `aculta420:wiki-entry`**: preserva artigo/teaser; o Twig atual suporta `show_heading=false`.
- **SDC existente `aculta420:context-header`**: o template `node--wiki-entry--full.html.twig` já o chama para H1 e passa `show_heading=false` ao `wiki-entry`; prevenir o H1 duplicado com page title Core por gate de DOM, sem esconder via CSS.
- **`aculta420:context-tabs`**: candidato à navegação entre páginas reais. Atualmente `wiki-entry.twig` possui seu próprio `nav` com aba Ler e `actions`; migrar/compor somente após mapa de links e permissões, nunca usar `product-tabs`.
- **`aculta420:empty-state`, `card`, `figure`, `quote`**: priorizar reuso via props/slots e UI Patterns/Styles. Nenhum novo componente será criado enquanto não for demonstrada lacuna real e segundo consumidor.
- **Sumário/infobox/âncoras:** padrões candidatos, **não implementados**; primeiro verificar heading anchors e render nativo Core, cache e dados disponíveis. Sem reprocessar HTML de CKEditor em JavaScript de tema.
- **Autoria pública:** manter a decisão atual de não divulgar nomes pessoais em verbetes; metadados de revisões não autorizam identificação pública automaticamente. `aculta_user` governa cartões de usuário quando houver consumidor legítimo.
- **Galeria/documentos:** `aculta_image`, `aculta_gallery`, `aculta_document_viewer` e `aculta_pdf_viewer` governam transformações e leitores; Wiki só os compõe.
- **Estados e dados:** os módulos determinam acesso, publicação, índice, referências, edição e cache; o tema exclusivamente apresenta. UI Styles usa tokens semânticos, sem novas cores primitivas.

## Pontos atuais encontrados por leitura da branch

1. `wiki-entry.css` já estiliza largura, texto, referências, infobox lateral, responsividade e estados de foco; evitar CSS paralelo.
2. `css/components/wiki.css` já modela busca no Domain Header, prévia, Views de categorias/resultado e tokens claro/escuro. Qualquer refinamento depende de revisão real de reflow e do shell.
3. A Wiki tem rotas explícitas `/wiki`, `/wiki/busca`, `/wiki/busca/previa`; outras rotas são hipótese até auditoria.
4. O preview AJAX tem contrato existente (mínimo de caracteres, debounce, fallback GET, ACL, cache privado). Não duplicar endpoints ou substituir por SDC: SDC compõe a aparência, AJAX mantém-se no provider.
5. Discussão contextual é **planejada**, não comprovada como publicada; respeitar o plano do Fórum.

## Microfases e versões — execução pequena, commits isolados

| Marco | Entrega | Gate |
| --- | --- | --- |
| **W420-UX 0.1-A** | inventário estático completo de tela/rota/Views, DOM esperado e reuso SDC existente | documentação auditável, nenhum componente novo |
| **0.1-B** | wireframes Home, busca, categoria e verbete, hierarquia mobile/desktop | dados/actions marcados como reais ou condicionais |
| **0.1-C** | contratos leitura (H1, sumário, figuras, referências, relacionados) e matriz de reuso | sem duplicar Core/SDC nem shell |
| **0.1-D** | contratos colaboração: histórico, discussão contextual, edição/Studio, autoria e moderação | providers e ACL verificados, sem entidade nova |
| **0.1-E** | SEO semântico, acessibilidade e tokens; estados vazio/erro/carregando | WCAG 2.2 AA, sem fake estados |
| **0.1-F** | plano de integração incremental nos consumidores com stories e matriz de teste | cada alteração visual pertence ao tema; integração funcional ao kernel |
| **0.1-R** | homologação homelab (Dev → RC → Alpha) | screenshots e provas por viewport/tema/ACL |

## Gates obrigatórios antes de RC

- 320, 360, 390, 768, 1024, 1280, 1440px; zoom 200/400%, teclado, leitores de tela, reduced motion, sem JS e modo claro/escuro **sem mudar a geometria**.
- Institution Bar + Domain Header presentes na Home, busca, verbete e páginas contextuais; marca/busca preservadas.
- H1 único; heading hierarchy coerente; âncoras, referências e imagens com alt/legenda; foco após navegação e AJAX; anúncios de resultados somente quando confiáveis.
- Anônimo/autenticado, editor/leitor, revisão publicada/pendente, conteúdos de propósitos distintos, cache contexts/tags e links de ação autorizados.
- Não inventar edição aberta, histórico público, discussão habilitada, salvamento automático, PDF ou renderizador de referências sem prova dos providers.
- Stories UI Patterns Library, schema SDC Devel e UI Styles verificados no homelab; nenhuma aprovação implícita a partir da documentação.

**Ownership:** módulo `aculta_wiki` / Core / Portal controla regras; **tema ACULTA420** controla padrões visuais. O presente documento não implementa funcionalidades nem atesta passes Runtime.

## Auditoria inicial concluída (somente código)

A [auditoria W420-UX 0.1-A](WIKI420-VISUAL-STATIC-AUDIT.md) registra evidências diretas de rotas, template full, contratos SDC, CSS e decisões preexistentes. **Não representa inspeção completa dos consumidores nem validação Runtime.** Próxima microfase: mapear controller/Views e H1/busca por rota; após isso elaborar wireframes detalhados com evidência suficiente.


## WE SATIVA — edição editorial independente

O editor Wiki ganha uma interface própria **WE SATIVA (Wiki Editor Sativa)**, documentada em [WE-SATIVA-VISUAL-EDITOR.md](WE-SATIVA-VISUAL-EDITOR.md). Ela é **separada de GanjaLab/GanjaLab420**, atende contribuintes autorizados mais amplamente, usa Form API/CKEditor e workflow do Drupal sem duplicação, e segue a mesma regra mobile-first que a Wiki pública. O acesso segue capacidades do kernel, não a filiação ao Studio. As duas experiências preservam Institution Bar e Domain Header global e usam exclusivamente o Design System Core SDC/UI Suite.

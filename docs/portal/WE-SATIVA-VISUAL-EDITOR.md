# WE SATIVA — Wiki Editor Sativa

**Versão de planejamento:** WES-UX 0.1.0-dev.1 · **Data:** 2026-10-22 · **Estado:** especificação UX e arquitetura, sem implementação.
**Produto:** interface editorial própria da Wiki420; **não é GanjaLab, GanjaLab420 nem um módulo/editor paralelo**.

## Decisão de produto

**WE SATIVA (Wiki Editor Sativa)** é a porta de entrada editorial para a comunidade ampliada da Wiki420. O usuário pode ler a Wiki sem acessar a experiência de gestão. Pessoas autorizadas podem propor, escrever, revisar e acompanhar verbetes em uma **UI específica, mobile-first**, separada dos dashboards GanjaLab (produção transversal) e GanjaLab420 (aprendizagem). Isso não implica permissão geral de edição ou publicação: Drupal Entity Access, roles, Content Moderation, Domain e políticas do kernel governam cada ação. O acesso editorial será atribuído por capacidades reais, não por rótulos de cargo ou associação a GanjaLab.

### Limites arquiteturais obrigatórios
- **Shell único:** Institution Bar + Domain Header multidomínio presentes no WE SATIVA e em toda a Wiki pública, com melhoria visual permitida, jamais duplicados, escondidos ou substituídos. O WE SATIVA pode ter cabeçalho contextual **interno**, abaixo do shell.
- **Ownership:** `aculta_wiki` (com Drupal Core) governa rotas, workflow, permissões, consultas, revisões, integridade e acesso a conteúdo; `aculta_portal` conserva resolução de domain/purpose; `aculta420` governa SDC/UI Patterns/UI Styles, tokens e apresentação. `aculta_user` mantém política de identidades. Arquivos/mídias continuam em kernels existentes.
- **Sem segundo editor:** usar Node Form API, CKEditor 5 e Media Library/integrações aprovadas; prévia e estado de salvamento devem refletir o servidor. Não duplicar armazenamento, drafts, histórico, AJAX ou engine de revisão.
- **Separação de interfaces:** não fazer WE SATIVA depender da rota `/ganjalab`, dos `StudioTab` ou de permissões do GanjaLab. Reuso permitido apenas de SDCs transversais conformes com Core SDC/UI Suite, não de Twig legado/containers de produto.

## Personas e capacidades (propostas, não permissões concedidas)

| Participação | Tela inicial | Capacidades condicionadas |
| --- | --- | --- |
| Visitante | Wiki pública | buscar, ler e consultar referências publicadas |
| Contribuinte autorizado | WE SATIVA — Meu trabalho | propor verbete ou revisão, salvar rascunho, acompanhar resultados conforme permissions |
| Editor autorizado | WE SATIVA — Revisão | editar materiais permitidos, solicitar ajustes e preparar publicação conforme workflow |
| Moderador/revisor | WE SATIVA — Fila | revisar contribuições e aplicar transições efetivamente autorizadas |
| Administrador | Ferramentas avançadas do Drupal | administração e configuração sem expor essas ações a contribuinte comum |

Se a política de colaboração vier a permitir propostas sem conta, exigir desenho posterior específico de abuso/moderação; **não assumir** edição anônima por ser uma Wiki.

## Navegação WE SATIVA (destinos conceituais)

**Meu trabalho**, **Criar verbete**, **Revisões**, **Fila** (somente autorizado) e **Ajuda editorial**. Links somente quando provider fornecer rota acessível. A landing padrão favorece o trabalho próprio, em vez de exibir conteúdo de todos os autores. Separação clara entre revisão de conteúdo próprio, fila de equipe e publicações editáveis de terceiros.

### Home do editor — mobile
```text
[Institution Bar — global]
[Domain Header Wiki420 + busca — global]
WE SATIVA
Seu espaço de contribuição
[+ Novo verbete] (se permitido)
Meu trabalho
  [Rascunho: verbete X] [Continuar]
  [Em revisão: verbete Y] [Ver estado]
[Revisões recebidas] (se houver)
[Abrir Wiki420]  [Guia de edição]
```

### Edição — mobile (320–767px)
```text
[Shell global]
‹ Meu trabalho             WE SATIVA
Editar: Título do verbete
Estado real: rascunho / revisão
Título [____________________]
Síntese [___________________]
Texto do verbete
[CKEditor 5 responsivo]
[Inserir imagem / mídia autorizada]
Fontes e referências [Expandir]
Categorias e verbetes relacionados [Expandir]
Opções avançadas [Expandir] (somente permitidas)
[Salvar rascunho] [Mais ações ▾]
```

Uma coluna; labels persistentes, controles com alvos adequados, input de texto ≥16px para evitar zoom involuntário; barra CKEditor adaptativa (quebra ou rolagem acessível), teclado virtual sem sobreposição, foco preservado após erros, mensagens no campo correspondente, safe-area e sem sticky obrigatório. Os blocos recolhíveis devem expandir automaticamente se contiverem erros; ações editoriais são as nativas e podem ser mais restritas que o desenho.

### Edição — tablet/desktop
```text
[Shell global]
Wiki420 / WE SATIVA / Meu trabalho / Verbete
┌──────────────────────────────────┬─────────────────────┐
│ Título, síntese e CKEditor 5     │ Estado e revisão    │
│ Conteúdo, figuras, referências   │ Categoria/relações  │
│                                  │ Ações permitidas    │
└──────────────────────────────────┴─────────────────────┘
Prévia / salvar / enviar para revisão (se disponíveis)
```

Coluna central dominante (~65–75%) e lateral auxiliar opcional (~25–35%) a partir de largura real suficiente; ao zoom alto volta a uma coluna. Interface editorial humana e acessível, sem imitar área administrativa. Preferência por layout que não cause perda de texto quando houver validação ou mudança de estado.

## Fluxos e estados

1. **Novo verbete:** entrar por link autorizado → formulário nativo → salvar rascunho (quando workflow suportar) → feedback real → continuar/visualizar.
2. **Edição de verbete:** abrir versão acessível → mostrar status atual e impacto da revisão → editar → pré-visualizar (Core) → salvar/transição permitida. “Salvar” não equivale a “Publicar”.
3. **Revisão editorial:** listar apenas conteúdo permitido → abrir diff/histórico se acessível → publicar, rejeitar ou solicitar alterações **somente** se essas transições existirem; nunca fingir aprovações.
4. **Conflitos:** edição concorrente, acesso revogado, falha de rede, sessão expirada e validação; não sobrescrever silenciosamente outra revisão. Verificar mecanismos Core antes de prometer lock/autosave.
5. **Mídia/referências:** imagem via `aculta_image` e Media/File; referências com campo Link existente; documentos via viewer compartilhado quando pertinente; nenhuma URL privada exposta no preview público.
6. **Discussão de verbete:** a futura thread Comment contextual pertence ao `aculta_forum`, não ao WE SATIVA; pode oferecer link autorizado para discussão, sem criar tópico público.

## Auditoria do estado atual do código (2026-10-22)

- `core.entity_form_display.node.wiki_entry.default.yml` inclui `title`, `field_wiki_summary`, `body` como `text_textarea_with_summary`, `field_wiki_categories`, imagem referenciada, fontes (`link_default`), relacionados, campos de sistema e status. **Este documento não afirma que CKEditor 5 já está ligado a todos os formatos de texto**: verificar formato/permissão efetivos.
- `node.type.wiki_entry.yml` declara `new_revision: true` e `preview_mode: 1`; workflow de moderação específico, acesso e comportamento ao criar/editar exigem inspeção por configuração e Runtime.
- `aculta_wiki.routing.yml` atualmente define Home, busca e prévia; **não existe nesta evidência uma rota WE SATIVA pronta**. Propor rota editorial dentro do propósito Wiki, sem inventar URI pública até a decisão de integração.
- `aculta_studio` mantém rota `/ganjalab` no domínio de conta e sua UI por áreas; **WE SATIVA não deve ser outra aba renomeada do GanjaLab**. Contratos UX (espaço pessoal primeiro, feedback claro, teclado, SDC) podem ser reaproveitados conceitualmente.

## Design System e reuso

| Recurso visual | Base SDC/UI Suite | Regra |
| --- | --- | --- |
| Contexto, breadcrumb e H1 | `aculta420:context-header` | sem duplicar page title ou shell |
| Navegação por URL | `aculta420:context-tabs` | links, `aria-current` e fallback sem JS |
| Listagens de meus trabalhos | `aculta420:card` / `editorial-card` | render arrays, estados autorizados |
| Vazio/erro | `aculta420:empty-state` e apresentação de feedback já existente | erro/transição vêm do provider |
| Editor/inputs | Form API/CKEditor 5 (funcional) + wrappers SDC (visual) | nunca substituir controles de envio |
| Identidade | `aculta420:author-card` somente para contextos permitidos | `aculta_user` controla privacidade |

Não criar SDC `we-sativa-*` sem auditoria dos componentes existentes e segundo consumidor real demonstrável. UI Styles semânticos; tokens de claro/escuro com geometria idêntica. Evitar nova suíte CSS/editorial concorrente.

## Roadmap WES-UX — fases pequenas e versionadas

| Fase | Trabalho | Critério |
| --- | --- | --- |
| **0.1-A** | auditar Form API, routes, roles, workflow, view modes, CKEditor e domínio | mapa de capacidades e diferenças de autor/revisor |
| **0.1-B** | prototipar UX mobile-first de dashboard, formulário, preview e revisão | wireframes com ações condicionadas |
| **0.1-C** | planejar providers/rotas/menus WE SATIVA independentes de GanjaLab | ownership e permissões Core explícitos |
| **0.1-D** | compor UI com SDC/UI Patterns/UI Styles já existentes | nenhuma duplicação e fallback sem JS |
| **0.1-E** | integrar campos, imagens, fontes, revisões, conflitos e avisos reais | Form API e workflows preservados |
| **0.1-F** | qualidade visual, teclado, leitor de tela, light/dark, estados e responsividade | WCAG 2.2 AA e formulários sem regressão |
| **0.1-R** | homologação no homelab antes de RC | testes de usuário/role, domínio, acessos, revisão, imagens e cache |

### Matriz mínima de QA
Viewport 320, 360, 390, 768, 1024, 1280 e 1440px; zoom 200/400%; teclado virtual; reduced motion; sem JavaScript; claro/escuro com a mesma composição; leitor de tela, foco e erro em formulário; CSRF/Form API; revisões simultâneas; anônimo, contribuinte, revisor e admin; Wiki/domain versus outras áreas. O domínio/purpose não deve mudar silenciosamente pela abertura do editor. Evidências e dados reais apenas no ambiente controlado.

**Integração com Wiki pública:** [plano visual](WIKI420-VISUAL-EXPERIENCE.md). A Wiki de leitura e o WE SATIVA seguem ambos mobile-first e compartilham shell/tokens/SDCs, porém têm navegação, objetivos e permissões diferentes.

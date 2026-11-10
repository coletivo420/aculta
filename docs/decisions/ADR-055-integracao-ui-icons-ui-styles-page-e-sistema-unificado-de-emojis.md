# ADR-055 - Integração UI Icons, UI Styles Page e Sistema Unificado de Emojis

Status: aprovado e implementado em 2026-11-10; continuidade da ADR-054 no tema `aculta420_v2` e módulos `aculta_editor`, `aculta_forum` e ecossistema UI Suite.
Preserva: ADR-050 (HTMX antes de AJAX), ADR-053 (Barra Institucional), ADR-054 (SDC First e Layout Builder) e contratos do Portal.

---

## 1. Contexto e Motivação

Com a conclusão da fundação de SDCs, Layout Builder e superfícies visuais (ADR-054), identificou-se a necessidade de unificar e padronizar o consumo de iconografia, estilos de página e seleção de emojis em todo o ecossistema ACULTA.

Anteriormente:
- O catálogo Fluent Emoji (`aculta_editor`) continha referências com inconsistências no container e formato de substituição regex sensível a quebras de HTML.
- O seletor de emojis no fórum (`aculta_forum`) utilizava uma lista estática de reações sem categorização, busca ou desacoplamento do motor de ícones.
- Os ícones da Icon API (`ui_icons`) estavam presentes em `aculta420_v2.icons.yml`, mas faltavam testes automatizados rigorosos de verificação da presença física de todos os SVGs dos icon packs e integração estruturada com menus e componentes.
- O módulo `ui_styles_page` carecia de documentação e mapeamento de rotas administrativas para governança de regiões.

---

## 2. Decisões Arquiteturais

### 2.1. Arquitetura do Sistema Unificado de Emojis (Fluent Emoji)
1. **Separação de Responsabilidades:**
   - **UI Icons / Icon API:** Descoberta e renderização de icon packs (`aculta_fluent_emoji`, `aculta_phosphor_regular`, `aculta_phosphor_duotone`).
   - **`aculta_editor`:** Catálogo central de metadados dos emojis (`FluentEmojiCatalog`), resolvedor de assets SVG e adaptador de integração (`FluentEmojiIconAdapter`).
   - **`aculta_forum`:** Regras de negócio de reações, endpoints HTMX, CSRF, persistência e construção de reações do tópico/comentário.
   - **`aculta420_v2`:** Renderização de templates, acessibilidade, temas claro/escuro e SDC `aculta420_v2:reaction-emoji`.

2. **Adaptador Centralizado (`FluentEmojiIconAdapter`):**
   - Criação da interface `FluentEmojiIconAdapterInterface` e serviço `aculta_editor.fluent_emoji_icon_adapter`.
   - Mapeamento bidirecional seguro entre IDs do fórum (`herb`), IDs do UI Icons (`aculta_fluent_emoji:herb`) e glifos Unicode (`🌿`).
   - Validação estrita de identificadores permitidos, prevenindo injeção de nomes de arquivos arbitrários.
   - Agrupamento em categorias semânticas (`cultivo`, `reacao`, `celebracao`, `comunidade`) consumíveis por interfaces frontend.

3. **Substituição de Texto e Segurança HTML (`FluentEmojiFilter`):**
   - O filtro `fluent_emoji` foi refatorado para usar parsing DOM via `\DOMXPath` exclusivamente sobre nós de texto (`//text()`).
   - Proteção de elementos sensíveis: `script`, `style`, `code`, `pre`, `svg`, `textarea` e elementos já marcados com `.reaction-emoji` são intocados.
   - Resolução robusta de assets injetando `FluentEmojiAssetResolverInterface` via container Drupal.

### 2.2. Interface de Seleção e Reações do Fórum
1. **Frontend do Seletor (`discussion-reaction-picker`):**
   - Inclusão de campo de busca instantânea no cliente por nome, ID e palavras-chave.
   - Organização em abas/seções por categorias de emoji.
   - Botão dedicado de fechamento e suporte completo à navegação por teclado (Escape fecha o popover e restaura o foco no acionador).
   - Compatibilidade com ciclo de vida do HTMX (delegação de eventos e auto-foco).
   - Estilização em `discussion-reaction-bar.css` aderente ao Stylelint, com tokens de superfície e modo claro/escuro.

2. **Limites Módulo vs. Tema:**
   - O template do módulo `modules/aculta_forum/templates/discussion-reaction-picker.html.twig` é estritamente neutro, sem menção a SDCs do tema.
   - O template de substituição em `themes/custom/aculta420_v2/templates/discussion-reaction-picker.html.twig` faz a ponte com os componentes SDC `aculta420_v2:reaction-emoji` e botões de ação.

### 2.3. Integração UI Suite (UI Icons, UI Patterns e UI Styles Page)
1. **Icon Packs Verificados:**
   - `aculta_phosphor_regular` (1512 ícones).
   - `aculta_phosphor_duotone` (1512 ícones).
   - `aculta_fluent_emoji` (48 ícones oficiais em formato SVG).
   - Teste unitário `UiIconsPackTest` valida a integridade física de todos os arquivos referenciados.
2. **UI Icons Menu e UI Patterns:**
   - Menus `main`, `account` e `admin` configurados para suporte nativo a ícones via `ui_icons_menu`.
   - Propriedades de ícones integradas aos SDCs do tema (ex: `button` via `$ref: 'ui-patterns://icon'`).
3. **UI Styles Page e UI Library:**
   - Módulo `ui_styles_page` ativo com suporte a estilos semânticos de regiões por tema (`/admin/appearance/ui-styles/page/theme/{theme}`).
   - Rota da UI Library (`/painel-administrativo/aparencia/ui/patterns`, `/icons`, `/styles`) com links dinâmicos e controle de acesso exclusivo para administradores.

---

## 3. Consequências e Resultados

- **Manutenibilidade:** Eliminação de tabelas divergentes de emojis entre módulos; qualquer novo consumidor (Messenger, Wiki, Marley) utiliza o serviço `FluentEmojiIconAdapter`.
- **Acessibilidade:** Navegação por teclado, atributos `aria-*` dinâmicos e isolamento de foco no seletor de reações.
- **Conformidade de Gates:** 0 violações em `validate-module-theme-boundary.php`, 0 violações em `validate-aculta420-sdc-schemas.php`, 0 erros em `npm run lint:css`.

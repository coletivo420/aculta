# ADR-055 - Integração integral UI Suite e sistema unificado de emojis

Status: decisão ampliada pelo responsável em 2026-11-10. Eixo Fluent Emoji / UI Icons parcialmente implementado em código; UI Suite integral, MIGRAÇÃO DO LEGADO, UI Skins substituindo purposes visuais e RENOMEAÇÃO FUNCIONAL purpose -> site_key no Portal permanecem em planejamento (US-00 a US-14 + RN-00 a RN-05). NÃO considerar encerrada.
Preserva: ADR-050 (HTMX antes de AJAX), ADR-053 (Barra Institucional), ADR-054 (SDC First e Layout Builder) e contratos do Portal.
Plano de pesquisa e fases: `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md`.
Plano vinculante de substituição visual e classificação: `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`.
Plano vinculante de renomeação do Portal: `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`.

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
   - Teste unitário `UiIconsPackTest` valida registro e quantidades mínimas dos três packs; correspondência exaustiva ID -> SVG ainda precisa de gate próprio.
2. **UI Icons Menu e UI Patterns:**
   - Alvo: menus `main`, `account` e `admin` configuráveis via integração de ícones. O Config Sync consultado não confirma ativação de `ui_icons_menu`; conferir Runtime, dependências e exportação na US-00/US-08.
   - Propriedades de ícones integradas aos SDCs do tema (ex: `button` via `$ref: 'ui-patterns://icon'`).
3. **UI Styles Page e UI Library:**
   - Alvo: `ui_styles_page` para estilos de regiões por tema (`/admin/appearance/ui-styles/page/theme/{theme}`). A configuração sincronizada não registra ativação; conferir Runtime e atributos Twig na US-03.
   - Rota da UI Library (`/painel-administrativo/aparencia/ui/patterns`, `/icons`, `/styles`) com links dinâmicos e controle de acesso exclusivo para administradores.

---

## 3. Consequências e Resultados

- **Manutenibilidade:** Eliminação de tabelas divergentes de emojis entre módulos; qualquer novo consumidor (Messenger, Wiki, Marley) utiliza o serviço `FluentEmojiIconAdapter`.
- **Acessibilidade:** Navegação por teclado, atributos `aria-*` dinâmicos e isolamento de foco no seletor de reações.
- **Conformidade registrada na entrega original:** 0 violações em `validate-module-theme-boundary.php`, 0 violações em `validate-aculta420-sdc-schemas.php`, 0 erros em `npm run lint:css`. Isso NÃO equivale a testes dos novos submódulos da ampliação US-00 a US-11.


---

## 4. Adendo de escopo aprovado - UI Suite integral (2026-11-10)

A ADR-055 passa a cobrir o ecossistema **UI Patterns + UI Styles + UI Icons + UI Skins** como camada visual do `aculta420_v2`. O objetivo não é habilitar todos os submódulos indiscriminadamente: é permitir que todos os pontos de integração **tecnicamente compatíveis, necessários e seguros** reutilizem o design system do ACULTA. Todo SDC continua presente no catálogo universal; exposição a editores, escrita, dados reais e permissões são decisões separadas e estritas.

| Pilar | Integrações normativas | Visual ampliado |
| --- | --- | --- |
| UI Patterns | Blocks, Field/Field Formatters, Layouts, Views, Library e **Field Group 2.0.x-dev**; **excluído CKEditor 5** | SDC em cartões, Views, displays, Layout Builder, agrupamento de campos e substituição de modelos visuais legados |
| UI Styles | Block, Layout Builder, UI Patterns, Views, Page, CKEditor 5, Entity Status | Faixa de acento, sombra, raio, espaçamento, tipografia e estilos de conteúdos não publicados sem novos templates |
| UI Icons | Packs da Icon API, integração de menus, seletor Picker/Popover, propriedades SDC, campos/links e CKEditor sob aprovação | Iconografia consistente e selecionável; Fluent Emoji mantém os donos de catálogo e regras de reação |
| UI Skins | CSS variables e themes/modes/schemes + ponte neutra domínio -> skin | **Substituição integral do motor VISUAL por purpose** com identidade por domínio, modos e paletas, sem alterar a função de rota/acesso do DomainPurposeManager |

**Benefícios por frente:** Revista (teasers, grid, citações, destaques editoriais); Fórum (cards, reações, rankings e estados); Wiki (infobox e organização de campos); GanjaLab (jornadas, progresso e cards); Culturas (agenda, eventos e locais); Loja (grades e cartões); Conta (painéis privados); Apoio, MAIN e Links (landings e superfícies). A aparência continua móvel, coerente em claro/escuro, com molduras tricolores e tokens semânticos.

### Limitações confirmadas que mudam a execução

1. **UI Skins 1.2.1**, compatível com Drupal 11.4+ e 12, deve mapear os tokens existentes. Não substitui automaticamente os contratos multidomínio nem o `data-theme`.
2. **UI Styles CKEditor 5** oferece estilos inline e em blocos, mas os formatos de texto e filtros devem autorizar só classes seguras; editores comuns continuam sem `full_html`.
3. **UI Styles Entity Status** estiliza entidades não publicadas; não concede acesso nem substitui workflow/moderação.
4. **UI Patterns Views 2** pode configurar row, style e display. Preservar Views, pagers, filtros, access/cache, engines VVJB/VVJS.
5. **UI Patterns Layouts** usa slots como regiões e props como opções; manter 22/24 displays compatíveis e as duas exclusões até que sejam corrigidas.
6. **UI Patterns Field Group:** preferir `2.0.x-dev@dev`, travar SHA no Composer lock. Beta2 serve só como evidência dos riscos: o projeto não está coberto pela política de segurança do Drupal e não anuncia Drupal 12. Avaliar HEAD dev, Field Group 4.x e segurança antes da ativação.
7. **UI Patterns CKEditor 5 está EXCLUÍDO deste ciclo:** não instalar, habilitar, prototipar ou criar filtros/widgets. A integração **UI Styles CKEditor 5** permanece prevista para classes editoriais seguras.
8. **UI Icons 2.1** (Drupal 11.3+/12) é referência preferencial; `.icons.yml` do Core sozinho não comprova que UI Icons Menu, Picker, Popover ou Patterns estejam habilitados.
9. **UI Examples** pode demonstrar páginas sintéticas, mas não é um catálogo concorrente. Display Builder/Canvas não substituem Layout Builder nesta ADR sem nova decisão.

### Estado de implementação e requisitos de aceite

- Entregue: ícones declarados pelo tema, adaptador Fluent Emoji, frontend do seletor do Fórum, superfícies SDC, integração de UI Patterns Layouts e Views registrada no Config Sync, biblioteca progressiva.
- O snapshot Runtime de 2026-11-10 registrou `ui_patterns_ckeditor5` ativo, embora ausente do Config Sync e sem consumidores no repositório. A integração é `LEGACY_OBSOLETE`, sem substituto ou compatibilidade; confirmar o estado Runtime atual e remover mediante operação autorizada. Não criar configuração para preservá-la.
- Pendente de constatação Runtime/Sync: UI Icons contrib e integrações, UI Styles Page, Skins, CKEditor 5 e Entity Status; Field Group não habilitado no Sync; inventário de Views consumidoras e critérios de segurança.
- Nova execução: **US-00 a US-14** no plano técnico canônico, incluindo substituição integral do legado visual e fechamento conjunto das ADRs. Não avançar sem baseline versionado, testes e rollback, incluindo configuração, 10 purposes, light/dark, 320 a 1440px, zoom 200%, access/cache entre hosts e avaliação editorial humana.
- Documento de planejamento **não equivale** a autorização para `drush cim`, `updatedb`, alterações de dados ou instalação cega. Branch `dev`, commits atômicos, preservar trabalho paralelo e não alterar Core/contrib.
- Estado de conclusão: **ADR-054: cutover implementado, homologação residual**; **ADR-055: implementação parcial comprovada em Git, integração integral planejada, homologação DEFERRED**.

### Fontes primárias

- https://www.drupal.org/project/ui_suite
- https://www.drupal.org/project/ui_skins
- https://www.drupal.org/project/ui_styles
- https://www.drupal.org/project/ui_icons
- https://www.drupal.org/project/ui_patterns
- https://www.drupal.org/project/ui_patterns_field_group
- https://project.pages.drupalcode.org/ui_patterns/
- https://project.pages.drupalcode.org/ui_patterns/1-users/4-with-views/
- https://project.pages.drupalcode.org/ui_patterns/1-users/7-in-ckeditor/


## 5. Decisões complementares vinculantes - migração integral (2026-11-10)

1. **Escopo total:** migrar todo modelo de apresentação que ainda não consome UI Suite, em todos os dez domínios e na administração ACULTA. SDCs, Views, Field Group, Layout Builder, formulários, blocos e templates precisam ter mapeamento de consumidores. Não substituir motores funcionais Core/contrib por marcação estática.
2. **UI Skins como motor visual único:** depois do cutover, paletas e valores de aparência deixam de ser selecionados por `aculta_purpose_palette`, `data-purpose-scheme` e `--aculta-purpose-*`. O Portal renomeia o identificador funcional de `purpose` para `site_key`, entrega um `skin_id` e modo de cor por contrato neutro, e preserva a resolução de `domain_id`, URL, acesso e cache. UI Skins fornece definições visuais; nenhuma config global do tema é mutada por requisição.
3. **Depreciação vs obsolescência:** `LEGACY_DEPRECATED` = visual ainda consumido, substituto e prazo de migração rastreados; `LEGACY_OBSOLETE` = visual já substituído, sem consumidores, pronto para limpeza comprovada. `FUNCTIONAL_NATIVE_EXCEPTION` protege Form API, Views, Commerce, VVJ etc. Não esconder SDCs do catálogo.
4. **UI Patterns Field Group:** preferir `2.0.x-dev@dev`, com pin, auditoria de segurança, compatibilidade Drupal 11.5/12, Field Group e rollback.
5. **UI Patterns CKEditor 5:** expressamente NÃO usar por agora; não criar piloto nem tarefa bloqueante de implementação. UI Styles CKEditor 5 continua.
6. **Aceite:** ADR-054 e ADR-055 apenas encerradas após US-14 **e RN-05**, inventário integral, 10 domínios em 2 modos, testes de cache/permissão/configuração, revisão visual humana e rollback verificável.
7. **Documento normativo da execução:** `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`. Este adendo prevalece sobre qualquer menção anterior a protótipo UI Patterns CKEditor 5 ou preservação do motor de paletas por purpose.

## 6. Adendo aprovado - Renomeação interna `purpose` para `site_key`

A distinção entre identidade funcional e identidade visual passa a ser obrigatória em todo o ACULTA: **`site_key` é a chave funcional canônica**, **`domain_id` é a entidade Drupal Domain** e **`skin_id` é a identidade visual UI Skins**. Não preservar `purpose` como nomenclatura permanente do Portal. Os valores estáveis `main`, `account`, `support`, `magazine`, `wiki`, `shop`, `courses`, `forum`, `links` e `eventos` não mudam.

A renomeação cobre classes, serviços, métodos, route options, subscribers, form builders, listas de sites ativos, contratos de apresentação e Twig, metadata de cross-domain, retorno ao login em sessão, State e testes. **Não modificar** Domain IDs, slugs/URLs públicas, `field_domain_source`, entidades, conteúdo, regras de acesso ou fluxos de pagamento. Os nomes antigos recebem aliases *internos* temporários e fail-closed quando metadados de rota novo e antigo conflitam; não criar aliases de rota públicos.

Execução em RN-00 a RN-05 conforme `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`. Integrar RN-01 com a ponte visual UI Skins (US-02), migrar todos os consumidores antes de remover APIs obsoletas e exigir RN-05 PASS antes de encerrar US-14. A exclusão de UI Patterns CKEditor 5 e a preferência pelo Field Group `2.0.x-dev@dev` permanecem inalteradas.

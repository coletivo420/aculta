# ADR-054 + ADR-055 - Programa de substituicao integral de visual legado e migracao Purpose Visual -> UI Skins

**Estado:** plano executivo; corte do motor visual por purpose implementado em código, homologação Runtime/visual DEFERRED. **Data:** 2026-11-10. **Branch:** `dev`.
**Referencias:** `docs/decisions/ADR-054-refatoracao-aculta420-v2-sdc-layout-builder.md`, `docs/decisions/ADR-055-integracao-ui-icons-ui-styles-page-e-sistema-unificado-de-emojis.md`, `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md`.

## 1. Decisoes do responsavel

1. UI Suite sera a **arquitetura visual canonica de TODO o site**, incluindo paginas publicas, dashboards privados, UI administrativa ACULTA, Revista, Forum, Wiki, GanjaLab, Culturas, Green Stash, Links, Apoio e integracoes transversais.
2. **UI Skins substitui o antigo sistema de purposes COMO MOTOR DE IDENTIDADE VISUAL**, suas paletas, seletores e configuracoes; a selecao visual por `aculta_purpose_palette`, `data-purpose-scheme`, `--aculta-purpose-*` e similares deve ser migrada e descontinuada. Nao introduzir duas fontes de verdade.
3. As **responsabilidades funcionais** de domínio/negócio não são eliminadas por UI Skins: `DomainPurposeManager`, `DomainSurfaceMap` e policies atuais terão equivalentes renomeados com o contrato **`site_key`** (`DomainSiteManager`, `DomainSiteMap`, etc.), preservando host, rota, URL, acesso, sessão, conteúdo e cache. A **nomenclatura `purpose` não permanece ativa após a migração RN-00 a RN-05**. UI Skins não faz roteamento/ACL.
4. Todos os **modelos, wrappers, componentes e renderizacoes visuais** que ainda nao usam UI Suite devem ser inventariados, receber status de migracao e ter substituto explicitado. Prever adaptadores seguros para render de Core/contrib, sem reimplementar comportamento.
5. UI Patterns Field Group: **preferir `2.0.x-dev@dev`**, com lock em commit e gates obrigatorios de seguranca, compatibilidade Drupal 11.5/12 e Field Group instalado. O projeto upstream nao e coberto pela politica de seguranca do Drupal; a preferencia pela dev nao e dispensa de homologacao.
6. **NAO USAR UI PATTERNS CKEDITOR 5 NESTE CICLO.** Nao instalar, habilitar, prototipar, adicionar toolbar, filtro, botao, dependencias nem implementar widget via `ui_patterns_ckeditor5`. Esta regra nao impede `ui_styles_ckeditor5` (estilos textuais) nem o CKEditor 5 e plugins existentes; mantem o catalogo Fluent Emoji que ja existe.
7. Nenhum modelo visual vira `deprecated` apenas por ser Twig/Drupal Core: classificar pelo **contrato visual real**, distinguindo integracao UI Suite (SDC + plugin/adapter) de plugin funcional Core/Views/Form API. Catalogo universal continua descobrindo 100% dos SDCs; deprecacao NAO e tecnica de esconder componentes.
8. Fazer substituicao por consumidores e fases com reversao testada, e nao "busca/substitui" global que altera acesso, cache ou dados.

## 2. Arquitetura final - UI Skins como unica fonte visual

### 2.1 Antes (removido do visual nesta entrega)

`DomainPurposeManager -> aculta_purpose -> aculta_purpose_palette / PurposePaletteSchemes -> SurfaceSettingsHooks::purposePaletteStyle -> data-purpose-scheme / --aculta-purpose-light-* / --aculta-purpose-dark-* -> CSS de componentes`

Consumidores conhecidos do codigo:
- `web/themes/custom/aculta420_v2/theme-settings.php`: formulario `aculta_cores_purpose` e `aculta_purpose_palette`.
- `web/themes/custom/aculta420_v2/src/Hook/SurfaceSettingsHooks.php`: `purposePaletteStyle()`, `data-purpose` / `data-purpose-scheme`, estilos inline no <html> e variaveis derivadas do Studio.
- `web/themes/custom/aculta420_v2/src/Palette/PurposePaletteSchemes.php` e testes associados.
- `web/modules/custom/aculta_portal/src/Hook/AppearanceHooks.php` e `src/Appearance/ColorModePreference.php`: `aculta_color_mode`, `aculta_purpose`, cache por usuario e dominio.
- `web/modules/custom/aculta_portal/src/Domain/DomainPurposeManager.php`: resolve domínio, aliases e URLs; substituir por `DomainSiteManager` (mesmo comportamento), com compatibilidade limitada e testada até RN-05.
- `web/themes/custom/aculta420_v2/src/styles/tokens.css` e styles, `aculta420_v2.ui_styles.yml`, `config/sync/aculta420_v2.settings.yml` e quaisquer atributos/caches descobertos pelo inventario.

Os itens de paleta acima foram removidos do código/configuração versionados; busca focal não encontrou consumidores ativos no tema ou Portal. `DomainPurposeManager` mantém usos funcionais fora do contrato visual. O inventário integral de Views, módulos consumidores, Runtime e áreas editoriais continua obrigatório; esta remoção de código não equivale a aceite visual.

### 2.2 Depois (contrato implementado; Runtime pendente)

`Domain/Portal (identidade funcional de dominio) -> resolvedor de skin por dominio + preferencia visual efetiva (Portal) -> API/definicoes UI Skins (themes/schemes + CSS variables) -> atributo/escopo de skin e tokens semanticos publicados por requisicao -> UI Styles -> SDC/UI Patterns -> pagina`

- UI Skins e **source of truth** de temas/esquemas e variaveis visuais. Declarar schemas segundo a API instalada (`{provider}.ui_skins.themes.yml` e `{provider}.ui_skins.css_variables.yml`; verificar versao antes de criar definicoes).
- O mapeamento `domain_id -> skin_id` e `skin_id x light/dark` pertence a configuração neutra do Portal, exportável e reversível; `site_key` é a chave funcional de domínio e **não dirige CSS**. Nenhum hostname hardcoded no tema. Os dez valores das chaves funcionais continuam estáveis.
- Na API instalada 1.2.1, as definições `*.ui_skins.css_variables.yml` e `*.ui_skins.themes.yml` são lidas a partir das configurações globais do tema; `PreprocessHtml` resolve somente um theme plugin configurado, e `PageTop` imprime valores CSS por scopes armazenados na configuração. Não existe seletor por Domain na API. Criar adaptador **no Portal, sem fork**, que resolve `domain_id -> skin_id` por request e emite somente `data-skin` com cache context `domain`; declarar os valores de cada skin como CSS variable definitions/scopes UI Skins estáticos, sem mutar configuração global. NUNCA alterar config global do tema durante um request, nem `$config->set()` para trocar de dominio.
- `ColorModePreference` permanece dono de preferencia pessoal e modo default, mas a realizacao visual light/dark fica **vinculada a UI Skins**. Definir contrato unico para `user preference > site default`, com escopo de skin do dominio. Verificar preferencias de usuario entre dominios e logs de acesso.
- Preservar nomes semanticamente genericos em SDCs (`--color-*`, `--button-*`, `--surface-*`) e refatorar seus valores para as declaracoes UI Skins. Variaveis antigas de purpose não serão mantidas como aliases ou fallback: remover no mesmo corte dos consumidores.
- Manter fallback deterministico para skin padrao do ACULTA quando a selecao esta ausente ou invalida. Nao mover dados/sessao/access, nem URLs por skin.
- Cache: contextos `domain` e `user`/`user.permissions` onde aplicaveis, tags da config de skins e mapeamento, respostas anonimas segregadas por dominio; variar asset/content apenas se necessario.
- Config Sync + rollback devem reproduzir exatamente a selecao em ambiente limpo. Mudancas globais de tema e token so apos piloto e aceite.

### 2.3 Ordem tecnica

1. Fotografar, com evidências de navegador, a matriz aprovada dos dez dominios (ambos os modos), variaveis computadas, contraste e bytes de assets.
2. Ativar diretamente a resolução UI Skins em **Links / LinkWeed**, sem modo sombra, leitura dupla ou configuração visual por purpose; comparar renderizações com as capturas aprovadas.
3. Corrigir contraste, cache e fidelidade do piloto; testar hosts e cache aquecido nos dois sentidos A/B e B/A.
4. Migrar os demais dominios em lotes, com mapa determinístico por ID e skin default. Nenhum domínio lê nem conserva configuração visual anterior.
5. Remover configuração, formulário, seletores CSS e tokens por purpose no mesmo corte dos consumidores. A remoção já foi publicada; não recriar aliases ou leitores para dados ausentes.
6. Validar modo pessoal, Studio, temas e configurações UI Skins. Rollback de falha visual é correção direta da nova implementação; não restaurar configuração ou comportamento visual por purpose.
7. Demonstrar que mudar uma skin de um domínio não altera outro e que o Portal continua correto sem o sistema visual de purpose.
8. Executar RN-00 a RN-05 para substituir também a **nomenclatura funcional** `purpose` por `site_key` (rotas, serviços, render arrays, State, sessões e consumidores), sem alterar comportamento de Domain. Plano: `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`.

**Decisão do responsável (2026-11-10):** o projeto não exige retenção de comportamento legado. `LEGACY_DEPRECATED` identifica apenas código ainda consumido durante a implementação do corte; não autoriza fallback, leitura dupla, aliases ou preservação de configuração visual antiga.

## 3. Classificacao obrigatoria e estados

A classificacao deve ser aplicada a **cada superficie visual efetivamente usada**, nao apenas aos SDCs. Gerar `docs/architecture/ACULTA-UI-SUITE-LEGACY-INVENTARIO.csv` (ou tabela YAML gerada sob `docs/architecture/inventarios/`) com registro rastreavel e revisar junto ao responsavel.

| Status canonico de migracao | Significado | Acao |
| --- | --- | --- |
| `UI_SUITE_NATIVE` | Componente/pagina/template realmente integrado a SDC + UI Patterns/Styles/Skins/Icons quando aplicavel | Manter; documentar consumidores |
| `LEGACY_DEPRECATED` / **depreciado** | Apresentacao visual antiga, ainda consumida, com substituto UI Suite definido | Avisar no inventario, evitar novos consumidores; migrar com gate e rollback |
| `LEGACY_OBSOLETE` / **obsoleto** | Apresentacao visual antiga cujo substituto foi validado e nao resta consumidor | Marcar para retirada segura, remover na limpeza apos comprovar zero consumidores |
| `MIGRATION_IN_PROGRESS` | Migracao em andamento com ponte/alias reversivel | Registar pacote, flags e testes |
| `BLOCKED_UPSTREAM` | Compatibilidade, seguranca, licenca ou API impede migracao na versao atual | Documentar gate FAIL/DEFERRED e alternativa sem regressao |
| `FUNCTIONAL_NATIVE_EXCEPTION` | Form API, elemento Core, Views plugin, fluxo Commerce, engine VVJB/VVJS, HTMX ou integrações nativas que **nao sao modelos visuais substituiveis** | Preservar funcionalidade, vestir visualmente com UI Suite se possivel; nao depreciar API funcional |

**Duas etiquetas nao equivalem:** `deprecated` em `*.component.yml` e uma metadata suportada pelo SDC, mas apenas para SDC **legado realmente substituido**; nao marcar SDC atual como deprecated so para diminuir biblioteca. Manter todos os SDCs descobraveis (ADR UP). Se um status metadata alterar descoberta, nao aplicar antes de resolver o gate de universalidade. Para template Twig puro, biblioteca CSS/JS ou plugin sem metadata SDC, usar inventario + nota curta deprecation no arquivo/documento adequado, nao inventar uma propriedade Drupal.

**Sem promessas falsas:** "todo Twig" nao precisa virar SDC; templates semanticos que adaptam Form API/Views/Drupal Core podem continuar como **thin adapters** e ser `UI_SUITE_NATIVE` se compoem SDCs e preservam Render API, cache e access. Nao apagar blocos nativos para substituir por HTML.

### 3.1 Registro minimo obrigatorio

Campos do inventario:
`id`, `owner_module`, `path`, `type` (twig/php/render_array/css/js/Views/field_group/layout/block/form/ckeditor), `purpose_or_route_consumers`, `visual_responsibility`, `ui_suite_target`, `replacement_component_id`, `status`, `risk`, `access_cache`, `impact_data`, `test_before`, `test_after`, `release_commit`, `rollback`, `reviewer`.

Regras de progresso:
- **100%** das superficies visuais ativas identificadas, incluindo rotas anonimas, autenticadas e administrativas ACULTA.
- **100%** dos casos `LEGACY_DEPRECATED` com substituto, owner, gate e prazo/criterio de remocao.
- **0** modelos visuais ad hoc novos sem justificativa e excecao registrada.
- **0** `LEGACY_OBSOLETE` com consumidores em runtime antes da retirada.
- **0** regressao de acesso, cache, tema, idioma, formulários, SEO e acessibilidade.
- Arquivo **gerado e verificado pelo agente**; nao preencher tabela com nomes presumidos ou porcentagens ficticias.

## 4. Inventario de toda a aplicacao - algoritmo e prioridades

### 4.1 Busca inicial (limitar saida e evitar vendor/core)

`rg -l --glob '*.twig' --glob '*.php' --glob '*.yml' --glob '*.css' --glob '*.js' '(#theme|#markup|#prefix|#suffix|#type|#component|include\\(|embed\\(|extends |data-portal-ui|data-purpose|ui_patterns|ui_styles|ui_skins|class=)' web/modules/custom web/themes/custom config/sync`

O comando e apenas um **indicador bruto**; cada resultado sera inspecionado para identificar se realmente instancia apresentacao visual, e se SDC/UI Patterns ja fazem o trabalho. Nao classificar todos os resultados como legado.

Inventariar por consumer:
1. `web/themes/custom/aculta420_v2/templates/`, `components/`, `*.libraries.yml`, `*.ui_styles.yml`, `*.icons.yml`, CSS base/utility e overrides de pagina.
2. `web/modules/custom/aculta_portal/src/` (controllers, presenters, forms, Render API, blocos), `modules/*/templates/`, `src/Controller`, `src/Plugin/Block`, `src/Form`, `src/Hook`, `*.module`.
3. `config/sync/views.view.*.yml`: rows/style/display, Views fields, exposed filters, empty, pager, attachments e blocos; integrar via `ui_patterns_views` por elemento apropriado.
4. `core.entity_view_display.*`, `core.entity_form_display.*`, `field.field.*`, `field_group`, `layout_builder`, blocks; comparar a configuracao ativa em runtime com Sync.
5. Rich text editor: `ui_styles_ckeditor5` APENAS, sem `ui_patterns_ckeditor5`; manter filtros de HTML e Fluent Emoji.
6. Paginas, navegacao, shell, 10 landings, cart, footer, mensagens/avisos, account, LMS, forum, Revista, Wiki, Eventos/Maps e entidades do Commerce.
7. Interfaces compartilhadas em varios dominios; nao classificar por hostname isolado.

### 4.2 Diretrizes de migracao de superficies

| Legado tipico | Modelo UI Suite alvo | Preservar |
| --- | --- | --- |
| Card HTML/Twig repetido | SDC card/tile + UI Patterns Views row ou bloco | Entidade, acesso, cache, links e excerpts |
| Container com padding/borda/acento ad hoc | `surface`, `surface-accent`, `surface-panel` + UI Styles | Semantica, slots e ordem DOM |
| Grade ou lista de View com templates duplicados | UI Patterns Views style + row/display | Filtros, paginacao, AJAX/HTMX, VVJB/VVJS |
| Agrupamento manual de metadados e campos | UI Patterns Field Group 2.0.x-dev se homologado; senao UI Patterns Field Formatters | Labels, erros, permissions, configuracao |
| Colunas e paginas por template sob medida | UI Patterns Layouts + Layout Builder nos displays elegiveis | 5 regioes do shell, H1/main, fallback |
| Icones SVG avulsos sem contrato | UI Icons packs/picker + props de SDC | Marca, acessibilidade, performance |
| Classes visuais arbitrarias em CKEditor | UI Styles CKEditor 5 com allowlist | Formatos de texto, filtragem HTML |
| Paletas por purpose, CSS inline e theme settings legados | UI Skins themes/schemes e CSS variables | Skin por dominio, preferencia claro/escuro, contraste |
| Blocos de formularios Drupal | Form API + adaptadores SDC form-field/surface | #states, #submit, CSRF, errors, validation |
| UI administrativa funcional de Core/contrib | Plugins originais + SDC/UI Styles quando suportado | Roles, rotas MAIN, acesso, extensibilidade |

### 4.3 Ordem de ataque

A. Transversais: tokens, skins, shell, menus, modais, buttons, surfaces, cards, icons, status, form field, navigation.
B. Frontends por dominio: Links piloto, MAIN, Forum, Revista, Wiki, Culturas/Maps, GanjaLab, Green Stash, Apoio, Conta/Marley.
C. Backoffice ACULTA: modais/formularios, listas, filtros e painéis de administracao propria no MAIN.
D. Conteudos compostos e displays: Views, Layout Builder, Field Group (dev quando homologado), UI Styles.
E. Zero consumidores e limpeza dos legados, sem remover um fallback antes do gate.

## 5. Field Group dev e CKEditor

### UI Patterns Field Group 2.0.x-dev

- Registrar no Composer com versao dev **preferida pelo responsavel**, respeitando `minimum-stability` e demais constraints; travar SHA em `composer.lock` para reproducibilidade.
- Upstream informa integracao temporaria com Field Group, sem cobertura da politica de seguranca e sem requisito Drupal ^12 na beta2. A dev pode ter mudado. Verificar `.info.yml`, CI upstream, testes PHP/Core, issues abertas e parecer de risco **antes** de habilitar a um site compartilhado.
- Testar Field Group 4.x e formulários, labels, erros, campos ocultos, validacao, ordem/weight, access e Render API. Se nao houver compatibilidade viavel, marcar `BLOCKED_UPSTREAM`, manter fallback com Field Formatters e SDC, sem dar "PASS" artificial.
- Nao aplicar patch a contrib sem revisao/approval e testes rastreaveis.

### UI Patterns CKEditor 5 - FORA DO ESCOPO

- `ui_patterns_ckeditor5`: nao instalar, nao habilitar, nao usar widget, nao criar prova de conceito neste ciclo, nao registrar como pendencia bloqueante da ADR.
- `ui_styles_ckeditor5`: sim, com classes editoriais restritas e validacao da renderizacao ao salvar.
- `ckeditor5_plugin_pack`, aculta_editor e botao Fluent Emoji existente continuam conforme seus contratos, sem insercao arbitraria de componentes SDC no HTML editorial.

## 6. Gates e criterio para fechar as duas ADRs

1. Inventario completo, classificacao rastreavel e mapa de consumidores de todo modelo visual ACULTA. Nenhuma ausencia de catalogo ou omissao de shell privado.
2. UI Skins governa **exclusivamente a aparência** e substitui o motor visual por purpose; o Portal migra funcionalmente para `site_key` sem perder infraestrutura. `rg` mostra zero consumidores de paleta antiga e, após RN-05, zero contratos antigos `purpose` ativos fora das exceções históricas registradas. A função antiga de `DomainPurposeManager` deve sobreviver em `DomainSiteManager`, não o nome da API.
3. 10 dominios × light/dark × resolucoes 320/390/768/1024/1440 × zoom 200% e teste de teclado. Comparar CSS computed, contraste, foco, mobile, H1/main e layout.
4. Areas: Portal, account/Marley, magazine, forum, wiki, cursos, shop, eventos, maps e apoio, incluindo atividades transversais e admin MAIN; testar anonimato, editor, admin e acesso cruzado.
5. Para cada migracao visual, snapshot antes/depois, mesmo conteudo/dados, tags/contexts/max-age, sem vazamento por dominio/user; hashes ou logs relevantes de runtime.
6. UI Patterns Library continua com **100% dos SDCs** descobraveis, inclusive internos, depreciados e editoriais; preview real apenas admin autorizado; performance comprovada.
7. UI Patterns Views row/style/display, Layouts, Fields/Formatters, Blocks e UI Styles/Icons/Skins integrados onde tecnicamente aplicaveis; Field Group `2.0.x-dev` avaliado com gate de seguranca; UI Patterns CKEditor 5 **nao e necessario nem permitido**.
8. Composer + lock + Config Sync + Runtime consistentes, em testes de instalação limpa; importar e exportar configuracao em ambiente isolado de staging com autorizacao quando aplicavel.
9. Rollback da skin, cada View/display, formulários e blocos; nao apagar entidades nem dados. Desinstalacao de codigo/componente obsoleto so depois de zero consumidores e autorização.
10. ADR-054: confirmar cutover P9, 22/24 displays e motivo das exclusoes, shell, cinco regioes, 10 dominios e pendencias P8; pendencias nao executadas permanecem DEFERRED.
11. ADR-055: apos todos os gates, somente entao atualizar status para concluida, changelog, TODO, YAML, roadmap, handoff e matriz final legada.
12. Executar somente gates do escopo, e registrar Unit/Kernel/Functional/Browser, PHPCS, PhpStan, CSS lint, snapshots e auditoria. A ausencia de evidencias de testes nao vale PASS.

## 7. Autonomia e limites dos agentes locais

- Branch `dev` diretamente, sem PR/branch nova, commits pequenos, sem force-push, sem esmagar alteracoes de outros agentes.
- Nao instalar/ativar nada so porque `core.extension.yml` ou a ADR informa que esta ativo. Conferir runtime, Composer/lock, versao e API real.
- Atualizar `docs/operations/ESTADO-DA-SESSAO.md`, TODO e status na entrega de cada fase; preservar as 3 entradas de handoff, arquivando a mais antiga.
- Criar novos testes e scripts de validacao. Nunca alterar Core/contrib diretamente. Nao desligar seguranca ou ignorar warning.
- Mudancas de runtime que exigem autorizacao explicita (config import, updatedb, uninstall, remocao de dados) nao devem ser feitas sem ela. Gate correspondente fica DEFERRED se nao autorizado.
- Em migracao sem possibilidade de reproducao segura, documentar bloqueio e fallback, nao sacrificar dados, dominio, seguranca ou estabilidade.
- **Nao declarar 100% migrado com base so em lista de arquivos ou em 100% dos SDCs**: o alvo e todo visual ativo do site.

## 8. Renomeação funcional coordenada (RN-00 a RN-05)

O novo plano `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md` é parte vinculante deste programa. A arquitetura final usa `site_key` para identidade funcional, `domain_id` para Drupal Domain e `skin_id` para UI Skins, eliminando a nomenclatura interna `purpose` após uma migração segura. Inclui inventário de callers, serviços, rotas, opções `_aculta_domain_purpose(s)`, campos do contrato de apresentação, State `active_purposes`, sessão de login e metadados de cache.

**Regra de precedência:** RN-01 introduz a API `site_key`; US-02 conecta UI Skins via `domain_id`; RN-02/RN-03 migram consumidores; RN-04 retira aliases internos quando houver zero chamadas; RN-05 bloqueia US-14 até verificar segurança, roteamento, cache, sessão, dados e rollback. Rotas públicas e Domain IDs permanecem intactos. O inventário de APIs `API_DEPRECATED`/`API_REMOVED` é distinto do inventário de componentes `LEGACY_DEPRECATED`/`LEGACY_OBSOLETE`.

# PROMPT DE EXECUCAO - CONCLUIR ADR-054 E ADR-055

Voce e o agente local executor do projeto ACULTA no servidor de desenvolvimento, branch `dev` (origin/dev, repositorio `coletivo420/aculta`). Sua missao e **CONCLUIR TODAS as fases remanescentes da ADR-054 e implementar/encerrar a ADR-055 ampliada**, incorporando UI Suite como arquitetura visual canonica de TODO o site e migrando o sistema visual baseado em purposes para UI Skins.

## 0. Leitura obrigatoria e politica operacional

Antes de agir, faca `git fetch origin`, `git status --short --branch`, registre HEAD local e origin/dev. Leia, nesta ordem:
1. `AGENTS.md` e `web/themes/custom/aculta420_v2/AGENTS.md`, depois `TODO.md` do tema e `docs/operations/ESTADO-DA-SESSAO.md`.
2. `docs/decisions/ADR-054-refatoracao-aculta420-v2-sdc-layout-builder.md` e `docs/decisions/ADR-055-integracao-ui-icons-ui-styles-page-e-sistema-unificado-de-emojis.md`.
3. `docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md` e relatorios `ACULTA420-V2-P8-HOMOLOGACAO.md`, `ACULTA420-V2-P9-CUTOVER.md`.
4. `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md` e **`docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`**.
5. `docs/architecture/ACULTA-UI-PATTERNS-OTIMIZACAO-UNIVERSAL.md`, `docs/operations/GATES.md`, roadmap e docs de componentes.

Trabalhe DIRETO na `dev`, sem novas PRs/branches, sem force push. Preserve worktrees/commits/trabalho de outros agentes; nunca `reset --hard`, `checkout --` ou `git clean` sobre alteracoes alheias. Use commits atomicos por microfase, push verificado e trailer `Claude-Session` conforme AGENTS. Nao edite Core/contrib diretamente. Edite modulo owner, tema ou adaptadores suportados. Todo codigo em Drupal 11.5 e pronto para 12 quando contratualmente exigido.

**Restricoes irrevogaveis:**
- **UI SKINS SUBSTITUI A CAMADA VISUAL DE PURPOSES**, seus settings, seletores CSS e paletas. Nao remover `DomainPurposeManager`/`DomainSurfaceMap`/Domain por serem infraestrutura de roteamento/ACL/URLs. O dominio continua identificavel no Portal; o tema deixa de definir aparencia por purpose.
- **PREFERIR UI PATTERNS FIELD GROUP `2.0.x-dev@dev`** e travar o commit no Composer lock. Nao usar beta2 por padrao, e nao ativar sem superar gaps de seguranca/compatibilidade Drupal 12/Field Group instalado. Se a versao dev falhar em gate, registrar BLOCKED_UPSTREAM e fallback sem mascarar a pendencia.
- **NAO USAR `ui_patterns_ckeditor5` POR AGORA**. Nao instalar, habilitar, configurar, prototipar, embutir SDC em rich text ou declarar como gate de aceite. **`ui_styles_ckeditor5` ESTA NO ESCOPO**, com whitelist de classes e formatos de texto.
- Manter **100% dos SDCs** descobraveis na UI Patterns Library (internos, reutilizaveis, editoriais e substituidos enquanto existirem). Nao marcar os SDCs atuais como deprecated para esconder a biblioteca.
- A totalidade do site inclui os dez dominios, o Shell, paginas de Conteudo, Revista, Forum, Wiki, LMS, Eventos, Maps, Loja, Apoio, Conta, Marley e o backoffice ACULTA MAIN.
- Nao usar novo framework, tema pronto ou layout engine concorrente; manter UI Patterns, Layout Builder, UI Styles, UI Icons, UI Skins, HTMX, VVJB/VVJS, Form API e plugins Core/Views/Commerce como fontes funcionais.

## 1. US-00 - Estado real do Runtime, dependencias e inventario

Confronte Composer + lock + `core.extension` no Sync + `drush pm:list` + config ativa, para todas as integracoes UI Suite, distinguindo Core Icon API de UI Icons contrib. Registre versoes, APIs, rotas, plugins e estado de instalacao. Valide em runtime o que ja existe, sem confiar em README desatualizado.

Faca inventario de **todos os modelos visuais ativos do site**: `web/themes/custom/aculta420_v2/components/` e `templates/`, CSS/JS, formularios, controllers e render arrays dos modulos, blocos nativos e custom, `views.view.*` (row/style/display), `core.entity_view_display`, `core.entity_form_display`, Field Group, Layout Builder, menus, ckeditor, temas e dez entradas de dominio. Nao confundir API funcional Drupal com apresentacao visual substituivel.

Produza e versiona um inventario CSV/YAML rastreavel com: ID, path, owner, consumidores/rotas, tipo, estado, UI Suite alvo, componente substituto, riscos de dados/acesso/cache, testes e rollback. A classificacao e OBRIGATORIA:
- `UI_SUITE_NATIVE` = visual ja integrado corretamente.
- `LEGACY_DEPRECATED` / **depreciado** = visual legado ainda consumido, alvo UI Suite definido e novos usos proibidos.
- `MIGRATION_IN_PROGRESS` = migracao reversivel em curso.
- `LEGACY_OBSOLETE` / **obsoleto** = visual substituido com zero consumidores; retirar apos gates.
- `BLOCKED_UPSTREAM` = impedimento real, com alternativa.
- `FUNCTIONAL_NATIVE_EXCEPTION` = funcionalidade Core/Views/Form/Commerce/VVJ que permanece nativa, mas cuja aparencia pode ser adaptada.

Nao invente contagem ou percentuais. Produza mapa por dominio e cross-domain e relatorio claro dos consumidores.

## 2. US-01 e US-02 - UI Skins assume o motor visual, piloto Links

Estude upstream da versao realmente instalada e mantenha o tema `aculta420_v2` autossuficiente; declarar themes/schemes e CSS variables por API UI Skins oficial. Inventarie `PurposePaletteSchemes`, `SurfaceSettingsHooks::purposePaletteStyle`, `theme-settings.php`, `AppearanceHooks`, `ColorModePreference`, CSS `--aculta-purpose-*`, `data-purpose-scheme`, `aculta_purpose_palette`, `data-theme` e referencias em cada modulo.

Implemente um **resolver no Portal** para mapear Domain ID -> skin_id. O Portal resolve identificador de dominio/skin e preferencia de usuario; UI Skins oferece definicoes e valores visuais; o tema consome tokens sem consultar hostname, entidade ou conta. **UI Skins nativamente aplica configuracao de tema global:** caso nao haja suporte seguro a esquemas por request, crie ponte suportada de escopo de resposta que leia definicoes oficiais; **NUNCA** modifique config global da skin por requisicao.

A compatibilidade deve ser migração sombra/double-read: snapshot de paletas e contraste atuais -> Nova UI Skins sem alterar pagina -> piloto Links/LinkWeed com flag reversivel -> 10 dominios, claro/escuro e preferencia de usuario. Fazer teste de cache aquecido A/B e B/A e fallback por skin. Somente apos todos os consumidores migrados, remover seletores, settings, classes e aliases do motor visual antigo. **Nao apagar o proposito de negocio/domínios**. Revalidar CSS calculado, foco e paletas originais.

## 3. US-03 a US-08 - Integrações UI Suite completas

- UI Styles Block, Layout Builder, Views, UI Patterns, Page, CKEditor 5 e Entity Status: instalar/ativar apenas modulos compativeis do escopo; aplicar estilos semanticamente fechados, contraste AA, 320px e schema.
- UI Styles CKEditor 5: aplicar tipos de destaque/caixa/citacao/editorial nos formatos existentes (Revista, Wiki, Fórum, Usuário), com HTML filtrado e teste salvar/reabrir/publicar. Nada de UI Patterns CKEditor.
- UI Patterns Views: migrar **row, style, display** dos consumidores elegiveis, nao substituir filtros, pager, sorting, views access ou mecanismos VVJB/VVJS; priorizar Forum, Revista, Eventos e depois demais.
- UI Patterns Layouts: manter cinco regioes do shell, 22/24 displays com LB, duas excecoes documentadas e landing Links como piloto; implementar composicao por SDC dos demais dominios conforme conteudo/aceite.
- UI Patterns Field/Formatters e **Field Group dev**: testar em bundle piloto, preservar ordem/labels/errors e funcionalidade. Forcar versao dev com lock, mas exigir parecer de seguranca e compatibilidade Drupal 11.5/12; caso bloqueado, manter fallback e reportar.
- UI Icons: integrar packs existentes a Menu, Picker, Patterns, Fields e componentes, respeitando acessibilidade e regras do `FluentEmojiIconAdapter`. Sem duplicar bases de emoji e sem inventar arquivos SVG.

## 4. US-12 e US-13 - Substituir TODO visual legado em todos os consumidores

Faca migração progressiva, incluindo layouts, wrappers, cartões, grades, editoriais, popups, abas, modais, formularios, cabecalhos contextuais, estados, sidebars, tabelas e superficies, sem criar modelo visual paralelo à UI Suite. Converter markup de apresentacao ad hoc para SDC existente ou criar novo SDC com schema estrito + story + CSS local, exposto por UI Patterns no consumer apropriado (Bloco/Views/Layout/Field/Field Group).

Use o inventario para marcar legados `LEGACY_DEPRECATED` enquanto em uso e `LEGACY_OBSOLETE` somente apos demonstrar substituicao e zero consumidores. Para SDCs obsoletos, use status metadata deprecated **so quando realmente justificado** e se nao violar descoberta universal; para Twig/CSS externo, registre no inventario e nos comentarios de manutencao sem inventar metadados. Nao remover modelo usado por qualquer dominio, visualizacao anonima/autenticada ou render assíncrono.

Nao alterar regra de entidade, access, cacheability, mensagens, Form API, Views, Commerce ou HTMX para "simplificar visual". Nao desligar protecoes. Priorizar reutilizacao de SDC `surface`, `surface-accent`, `surface-panel`, layouts, button, cards, badges, tabs e componentes ja documentados.

## 5. US-14 e P8/P9 - Fechamento completo das duas ADRs

Revalidar shell de 5 regioes, institution bar, os 10 dominios, Layout Builder 22/24 e exclusoes, Config Sync de blocos/dominios, landing pages (conteudo editorial depende de aceite), cache cross-domain, acesso e rollback; concluir o que for viavel das pendencias da ADR-054. Relacionar cada gate à evidencia (SHA, arquivo, teste, screenshot) e marcar PASS/FAIL/DEFERRED sem inventar.

Gates obrigatorios: PHP lint, PHPCS, PHPStan focal, SDC schema, fronteira tema/modulo, UI Library permissionamento e preview admin-only, Stylelint, Unit/Kernel/Functional/Firefox/Playwright, teste de templates, acessibilidade e compatibilidade. Em navegador: 320, 390, 768, 1024 e 1440 CSS px, zoom 200%, light/dark, teclado/foco, anonimo/editor/admin, 10 dominios, pagina inicial, telas internas e modais, nenhum overflow, vazamento, H1/main duplicado, CSS global desnecessario ou regressao de performance.

Testar export/import e rollback de configuracao **apenas onde houver autorizacao e ambiente isolado**. O pedido de execucao nao autoriza implicitamente `drush cim`, `updatedb`, `pm:uninstall`, `DROP` ou apagamento de dados. Sincronizacao de codigo e `drush cr` no desenvolvimento seguem AGENTS.md. Se um gate nao puder ser executado, registar DEFERRED e impedimento, sem declarar fechamento falso.

## 6. Entrega e comunicacao

Nao pare em diagnostico: execute as microfases de codigo e testes que puder, em sequencia, sem abrir PR. Commits atomicos e push para origin/dev apos cada microfase; verificar SHA remoto. Atualizar, no mesmo commit pertinente, TODO do componente, `docs/operations/status/aculta420_v2.yml`, ADRs, ROADMAP, changelog, matriz de inventario, rollback e handoff (maximo tres entradas, antigas em docs/history).

Ao finalizar entregue um relatorio por etapa: itens migrados, legados depreciados/obsoletos, extenso e total das superficies, versoes reais, SHA de commit, testes/gates, evidencias, medidas antes/depois, configurações tocadas, falhas e DEFERRED. **Somente declarar ADR-054 e ADR-055 CONCLUÍDAS se todos os gates de aceite estiverem demonstravelmente aprovados**. Caso contrario, concluir o trabalho executavel, documentar o bloqueio exato, e fornecer a proxima acao objetiva, sem presumir aprovação.

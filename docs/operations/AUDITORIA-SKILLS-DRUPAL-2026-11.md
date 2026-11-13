# Auditoria de Skills e ecossistema UI Suite

> **Registro historico:** snapshot de 2026-11-10. Nao e inventario ativo. Em 2026-11-13, o repositorio possui 23 skills canonicas em `.agents/skills/`; o fluxo vigente e `AGENTS.md` + `docs/operations/AGENT-REVIEW-POLICY.md`. Confirme Composer, config e runtime atuais antes de executar.

Data: 2026-11-10. Base: branch `dev`, snapshot `7e37cf41718045cc27d915ce1ecc94a61aa2223f`.

## Inventário de Skills

O checkout contém 14 Skills de projeto: `aculta-commit-entrega`, `aculta-data-portal-ui`, `aculta-drupal-padroes`, `aculta-gates-por-escopo`, `aculta-grill-me`, `aculta-handoff`, `aculta-leitura-economica`, `aculta-navegador-testes`, `aculta-seguranca-estatica`, `aculta-todo-componente`, `css`, `graphify`, `htmx` e `sdc`. As dez Skills ACULTA já tinham conteúdo canônico em `docs/agent-skills`; as quatro restantes estavam duplicadas em árvores de agente. Foram examinadas antes da reorganização.

O número anterior de 64 arquivos `SKILL.md` não se reproduz neste clone: a busca local encontra skills de projeto, cópias/symlinks de descoberta e documentação de plugins/caches globais. O `.trash` observado em instalações de usuário não é parte deste checkout e não representa skill ativa. Contar resultados de `find` sem resolver links, caches e diretórios globais mistura escopos. Não foram alteradas Skills globais. Não há colisão conhecida entre nomes de Skills do projeto e as Skills globais diretas examinadas.

## Estado técnico e limite da auditoria

Versões identificadas: Drupal Core `11.5.0-beta1`, UI Patterns `dev-2.0.x`, UI Patterns Layouts `2.0.23`, UI Patterns Field Group `2.0.0-beta2`, UI Styles `1.22.0`, SDC Devel `1.0.3`; PHP requerido `>=8.3`. PHP CLI disponível na estação de auditoria: `8.5.12`.

Este worktree não inclui `web/core`, `web/modules/contrib` nem `vendor`. A tabela abaixo separa dependência/lock, export sincronizado e filesystem. **Runtime não verificado** significa precisamente isso, não inferência de habilitação. Pacote presente tampouco comprova módulo instalado ou habilitado.

## Matriz do ecossistema

| Tecnologia/módulo | Composer / lock | Filesystem local | Export `core.extension.yml` | Runtime | Consumidor/integração observada | Skill | Cobertura e risco |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `ui_patterns` | `drupal/ui_patterns`, `2.0.x-dev@dev` / `dev-2.0.x` | ausente | habilitado | não verificado | SDCs do tema, base das integrações | `ui-patterns` | sem teste de runtime neste clone |
| `ui_patterns_blocks` | pacote base UI Patterns | ausente | habilitado | não verificado | componentes como blocos | `ui-patterns` | acesso/cache devem ser verificados |
| `ui_patterns_layouts` | `drupal/ui_patterns_layouts`, `^2.0` / `2.0.23` | ausente | habilitado | não verificado | slots/regiões em Layout Builder | `ui-patterns` | respeitar ADR-054 e cinco regiões |
| `ui_patterns_field` | pacote base UI Patterns | ausente | habilitado | não verificado | field `ui_patterns_source`, árvores editoriais | `ui-patterns` | tradução, revisão, acesso e conteúdo precisam validação |
| `ui_patterns_field_formatters` | pacote base UI Patterns | ausente | habilitado | não verificado | component per item e campo multivalorado | `ui-patterns` | field mappings e cache |
| `ui_patterns_views` | pacote base UI Patterns | ausente | habilitado | não verificado | row, style e display plugins | `ui-patterns` | manter acesso, pager e render arrays |
| `ui_patterns_library` | pacote base UI Patterns | ausente | habilitado | não verificado | catálogo de SDCs/stories | `ui-patterns` | todos os componentes devem permanecer disponíveis |
| `ui_patterns_field_group` | `drupal/ui_patterns_field_group`, `^2.0@beta` / `2.0.0-beta2` | ausente | desabilitado/não listado | não verificado | opcional; field group | `ui-patterns`, `ui-suite-integrations` | não habilitar; fora da política de avisos de segurança Drupal |
| `ui_styles` | `drupal/ui_styles`, `^1.22` / `1.22.0` | ausente | habilitado | não verificado | definições no tema `aculta420_v2.ui_styles.yml` | `ui-styles` | validar schema/classe/preview |
| `ui_styles_block` | pacote UI Styles | ausente | habilitado | não verificado | styles em blocos | `ui-styles` | verificar plugin/access |
| `ui_styles_layout_builder` | pacote UI Styles | ausente | habilitado | não verificado | styles em Layout Builder | `ui-styles` | preservar layouts existentes |
| `ui_styles_ui_patterns` | pacote UI Styles | ausente | habilitado | não verificado | integração de styles com Patterns | `ui-styles` | evitar duplicar classes de SDC |
| `ui_styles_views` | pacote UI Styles | ausente | habilitado | não verificado | styles em Views | `ui-styles` | confirmar alvos suportados |
| `ui_styles_page` / `ui_styles_library` | pacote UI Styles, extensão opcional | ausente | não listado | não verificado | página/global e styleguide, conforme release | `ui-styles`, `ui-suite-integrations` | confirmar `.info.yml` e release antes de usar |
| Drupal Core Icon API | Drupal Core `11.5.0-beta1` | ausente neste clone | não é extensão contrib | não verificado | packs próprios declarados pelo tema | `ui-icons` | Core API não equivale a UI Icons contrib |
| packs `aculta_phosphor_regular`, `aculta_phosphor_duotone`, `aculta_fluent_emoji` | sem pacote contrib | tema presente | declarados no YAML do tema | não verificado | assets Phosphor e Fluent Emoji; SDC icon | `ui-icons` | teste-fonte `UiIconsPackTest`; preservar packs/sistema do Fórum |
| `drupal/ui_icons` e submódulos | não consta no composer/lock | ausente | ausente | não verificado | Picker, Popover, Library, campo/link, menu, Patterns, CKEditor, Media são opções a confirmar | `ui-icons` | não inventar machine names ou seletores |
| `sdc_devel` | `drupal/sdc_devel`, `^1.0` / `1.0.3` | ausente | habilitado | não verificado | desenvolvimento/preview SDC | `sdc`, `ui-suite-integrations` | runtime não comprovado |
| `aculta_ui_library` | implementação própria | código do projeto presente | módulo customizado no repositório | não verificado nesta auditoria | catálogo administrativo de blocos reais/prévias | `ui-suite-integrations`, `ui-patterns` | SEC-01: guard `isAllowed()` e testes-fonte para allowed/forbidden/neutral presentes; sessão de 10-11 registra 9/9; README desatualizado; esta auditoria não repetiu PHPUnit |
| UI Skins | `drupal/ui_skins` não consta no lock | ausente | ausente | não verificado | opcional, variáveis CSS/themes/modes | `ui-suite-integrations` | não migrar `data-theme`, purposes ou tokens |
| UI Examples | `drupal/ui_examples` não consta no lock | ausente | ausente | não verificado | opcional, exemplos/páginas YAML com render arrays | `ui-suite-integrations` | exemplos não são stories nem preview de bloco real |
| Display Builder | `drupal/display_builder` não consta no lock | ausente | ausente | não verificado | opcional, composição de entity/view/page displays | `ui-suite-integrations` | beta; risco de migração Layout Builder/Views/ADR-054; não instalar/migrar |

Classificação adicional: cinco submódulos UI Styles e as integrações UI Patterns listadas são **habilitadas no export, consumidores de código não provados por falta do filesystem contrib**. Os três packs de ícones são **implementação própria baseada na Icon API Core**. Field Group é **dependência presente, mas desabilitada**. UI Icons, UI Skins, UI Examples e Display Builder são **planejados/opcionais ou não utilizados**, não instalados.

## Arquitetura das Skills e integração de agentes

`.agents/skills/` é a fonte canônica do projeto. `.claude/skills/` e `.codex/skills/` apontam para os mesmos diretórios via symlinks relativos. `docs/agent-skills` preserva compatibilidade como alias relativo. Skills globais não foram modificadas. A validação estrutural passou; smoke test amostral no Claude Code e diagnóstico de contexto do Codex passaram para `ui-patterns`, mas a invocação individual das 18 Skills em ambos os CLIs permanece DEFERRED, conforme `TESTES-SKILLS-UI-SUITE.md`.

As seis Skills existentes `sdc`, `css`, `aculta-data-portal-ui`, `aculta-drupal-padroes`, `aculta-gates-por-escopo` e `aculta-navegador-testes` foram atualizadas sem substituir suas instruções válidas. Foram acrescentadas `ui-patterns`, `ui-styles`, `ui-icons` e `ui-suite-integrations`. A última só coordena tarefas transversais; não replica os guias especialistas.

## Referências oficiais

- [UI Suite](https://www.drupal.org/project/ui_suite)
- [UI Patterns 2.x docs](https://project.pages.drupalcode.org/ui_patterns/) e [projeto](https://www.drupal.org/project/ui_patterns)
- [UI Styles](https://www.drupal.org/project/ui_styles) e [documentação](https://project.pages.drupalcode.org/ui_styles/)
- [UI Icons](https://www.drupal.org/project/ui_icons) e [Drupal Core Icon API](https://www.drupal.org/docs/develop/drupal-apis/icon-api)
- [UI Skins](https://www.drupal.org/project/ui_skins), [UI Examples](https://www.drupal.org/project/ui_examples), [Display Builder](https://www.drupal.org/project/display_builder)
- [UI Patterns Field Group](https://www.drupal.org/project/ui_patterns_field_group), [SDC Devel](https://www.drupal.org/project/sdc_devel)

Use documentos/release notes da linha efetivamente resolvida. Para detalhes de UI Patterns, não use 1.x como referência principal. Quando o código contribuído não está no checkout, registre a API como pendente de confirmação em vez de fabricar machine names, classes ou opções.

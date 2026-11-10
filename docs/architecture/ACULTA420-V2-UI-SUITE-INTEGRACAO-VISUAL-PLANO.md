# ACULTA420 V2 - Plano de integracao visual integral da UI Suite

**Estado:** PLANEJAMENTO REVISADO; IMPLEMENTAÇÃO PENDENTE (US-00 a US-14 + RN-00 a RN-05). Inclui migração integral de visual legado, UI Skins como motor visual e renomeação funcional do Portal de `purpose` para `site_key`.
**Data de referencia:** 2026-11-10. **Branch alvo:** dev. **Decisoes:** ADR-054 e ADR-055. **Planos obrigatórios:** `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md` e `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`.
**Owners:** SDC, tokens, variacoes e CSS no tema `aculta420_v2`; regras, dados, acesso, cache e dominio nos modulos proprietarios; registro e configuracao de integracoes no Drupal Core e UI Suite. **Nao e um relatorio de teste do Runtime.**

## 1. Objetivo e beneficio visual

A UI Suite sera a camada de *site building* do design system proprio ACULTA420 V2, e nao um tema pronto ou um segundo framework CSS. **UI Skins substitui o sistema visual de purposes** (paletas, classes e tokens associados); o Portal preserva somente a identificação funcional de domínio, URL, acesso, sessão e cache, que UI Skins não resolve. Preservar todos os SDCs internos, editoriais e reutilizaveis no catalogo universal (plano UP-00 a UP-07), sem tornar todos automaticamente editaveis por qualquer papel.

Beneficios tangiveis:
1. Uma mesma superficie, card, moldura tricolor, CTA, tabela ou estado vazio aparece em Views, blocos, displays e Layout Builder sem HTML duplicado.
2. Administradores escolhem variantes e composicao pela UI, com opcoes fechadas, contraste e responsividade previstos nos SDCs, sem CSS arbitrario.
3. Layouts de cada domínio e página aproveitam os mesmos componentes; a **identidade visual** passa a ser governada por UI Skins e os **dados, menus, rotas e acesso** seguem os contratos funcionais do Portal.
4. CKEditor 5 recebe estilos editoriais por **UI Styles CKEditor 5**; **UI Patterns CKEditor 5 está fora do escopo e não deve ser instalado, ativado ou prototipado** neste ciclo.
5. Icons Phosphor e Fluent Emoji tornam-se catalogos pesquisaveis e reutilizaveis em menus, campos e componentes, sem duplicar a semantica das reacoes.
6. Light/dark, paletas e variáveis passam a ter **UI Skins como fonte visual única**, migrando o controle antigo baseado em purpose por domínio, com fallback e sem alterar configuração global por requisição.
7. A biblioteca administrativa mostra tanto catalogos quanto composicoes reais, com previsualizacoes progressivas para conter custo de descoberta e renderizacao.
8. Toda saída visual legada do site entra em inventário, é classificada como **depreciada** enquanto utilizada, **obsoleta** após substituição validada ou **exceção funcional nativa**, até completar a migração para UI Suite.

**Regra visual:** SDC = estrutura/apresentacao; UI Styles = classes de variacao; UI Skins = CSS variables e esquemas; UI Icons = origem/selecao de icones; UI Patterns = conectores de SDC com APIs Drupal. Nao atribuir a qualquer um deles acesso a dados, permissao de entidade ou isolamento multidominio.

## 2. Fotografia do repositorio em origin/dev, 2026-11-10

| Evidencia consultada | Estado constatado | Certeza |
| --- | --- | --- |
| `web/themes/custom/aculta420_v2/aculta420_v2.info.yml` | Tema 1.5.0, `base theme: false`, cinco regioes e schemas estritos | Confirmado no Git |
| `web/themes/custom/aculta420_v2/components/` | Familia extensa de SDCs, com layouts, Forum, modal, superficies, stats, etc. | Confirmado no Git; inventario quantitativo deve ser regenerado |
| `aculta420_v2.ui_styles.yml` | Estilos de botoes, cartoes, paletas, spacing, superficie, acento, sombra e raio | Confirmado no Git |
| `aculta420_v2.icons.yml` | Packs Phosphor Regular, Duotone e Fluent Emoji declarados | Confirmado no Git |
| `config/sync/core.extension.yml` | `ui_patterns`, blocks, field, field_formatters, layouts, library, views, `ui_styles`, block, layout_builder, ui_patterns, views habilitados no Sync | Confirmado no Git |
| `composer.json` (após atualização da US-00) | `ui_patterns` 2.0.x-dev, `ui_patterns_layouts` ^2.0, `ui_patterns_field_group` 2.0.x-dev@dev, `ui_styles` ^1.22, `ui_skins` ^1.2 e `ui_icons` ^2.1 | Confirmado no Git; lock Field Group SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1` |
| `config/sync/core.extension.yml` | `ui_skins`, `ui_patterns_field_group`, `ui_styles_ckeditor5` e `ui_patterns_ckeditor5` ausentes do Sync | Confirmado; difere do Runtime. `ui_styles_page` foi alinhado ao Runtime em 2026-11-10; Entity Status segue desabilitado |
| `docs/architecture/inventarios/ui-suite-runtime-2026-11-10.json` | `drush pml` registrou 36 módulos UI Suite; UI Skins Disabled; UI Patterns CKEditor 5 e Field Group Enabled; UI Styles Page Enabled | Captura Runtime versionada; nenhum módulo foi habilitado/desinstalado nesta execução |
| `drush config:status` | Drift inclui `core.extension` e `ui_icons_patterns.settings`, além de configurações de Fórum, LMS e admin | Não executar `cim`; divergência preservada e listada no snapshot |
| Composer install | Lock sincronizado com `composer install --no-scripts`; Gin ausente foi instalado. Scaffold de `.htaccess` restaurado ao HEAD | Código de dependências sincronizado; `drush cr` PASS; nenhum script de projeto executado |
| `docs/architecture/ACULTA420-V2-P8-HOMOLOGACAO.md` | Layout Builder 22/24 displays compativeis; duas exclusoes por perda de conteudo | Evidencia de relatorio, nao teste reexecutado |
| `docs/operations/ESTADO-DA-SESSAO.md` | UI Patterns em lote com previas sequenciais 90/90 no Runtime; estado da sessao mudou depois | Evidencia registrada por outro agente |
| `aculta_editor` e `aculta_forum` | Adaptador FluentEmojiIconAdapter, filtro DOM, seletor HTMX e template SDC | Confirmado no Git |

**Estado observado depois da inspeção:** Composer install limpo instala `ui_skins` 1.2.1 e `ui_icons` 2.1.0 do lock. Runtime drush reporta UI Skins Disabled e UI Patterns CKEditor 5 Enabled; Sync não registra estas ativações. Não atribuir PASS de integração de configuração sem reconciliar Sync/Runtime. Igualmente, a existencia de plugin em um pacote nao prova uso de suas configuracoes por displays e Views.

## 3. Verificacao upstream de versoes e lacunas

| Projeto | Referencia upstream em 10/11/2026 | Implicacao |
| --- | --- | --- |
| UI Patterns 2 | 2.0.23 estavel, Drupal ^10.6 / ^11.3 / ^12; site usa constraint dev | Comparar `composer.lock` e APIs atuais, estudar migracao para stable por tarefa isolada |
| UI Styles | 1.22 estavel, Drupal ^10.3 / ^11 / ^12 | Integracoes podem ser ativadas em lotes com testes |
| UI Skins | 1.2.1 estavel, Drupal ^11.4 / ^12 | Candidato preferencial para gestao de variaveis/esquemas do tema |
| UI Icons | 2.1.0 estavel, Drupal ^11.3 / ^12; 2.0.x nao e mais suportado | Preferir 2.1.x, validar Composer/lock e nomes reais dos submodulos |
| UI Patterns Field Group | **Preferência aprovada e lockada: 2.0.x-dev@dev**, SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1`; upstream sem cobertura da política de advisories; `.info.yml` declara apenas ^9/^10/^11 | Inspecionados formatter e sources; sem ativação. **BLOCKED_UPSTREAM** para Drupal 12 e sem aceite de risco de segurança; Field Group instalado 4.0.0 |
| UI Patterns em CKEditor 5 | **EXCLUÍDO por decisão do responsável** | Não instalar, habilitar, prototipar ou criar filtros/botões; UI Styles CKEditor 5 permanece no escopo |

**Nao instalar ou atualizar por suposicao:** resolver primeiro as versoes compativeis do `composer.lock`, verificar os arquivos `*.info.yml` dos modulos baixados e as dependencias. UI Icons base do Core e a camada UI Icons contrib nao sao a mesma coisa. Nao presumir que `ui_patterns_field_group` passa no Drupal 12 so porque `ui_patterns` passa.

## 4. Modelo de responsabilidades e fronteiras

`Drupal Domain / Portal SiteKeyRegistry + DomainSiteManager (site_key: negócio, roteamento, acesso, URL e cache)`
  -> `resolvedor Portal de skin por domínio + modo pessoal (contrato neutro)`
  -> `UI Skins: themes/schemes e CSS variables como motor visual único`
  -> `UI Styles: opcoes finitas de classes para wrappers e elementos`
  -> `SDC: markup, slots/props e comportamento`
  -> `UI Patterns: adaptadores para Block, Layout, Field, Views, CKEditor (se aprovado)`
  -> `UI Icons: packs, seletores, menu e componentes`

- O tema não consulta hostname, entidade ou usuário. Contexto, access checks, cache tags e contexts são fornecidos pela origem. UI Skins substitui `--aculta-purpose-*` e `data-purpose-scheme` após migração; `domain_presentation` continua contrato de dados, não de cores. O modo do usuário permanece no Portal mas sua realização visual usa UI Skins.
- UI Skins NÃO fornece identificação de domínio por host. Implementar uma ponte Portal `domain_id -> skin_id` que consome definições UI Skins sem alterar configurações globais do tema por requisição; preservar acesso, URL, contexto `domain` e preferências pessoais. Paralelamente, renomear o contrato funcional de `purpose` para `site_key` em RN-00 a RN-05, sem alterar valores nem políticas.
- As cinco regioes do shell e a barra institucional sao imutaveis neste escopo; Layout Builder altera somente composicao de conteudos e displays elegiveis.
- UI Styles nunca habilita edicao livre de CSS nem substitui classes semanticas. Classes do editor sao apenas as permitidas pelo formato de texto.
- Componentes SDC permanecem descobraveis; autorizacao para **editar** uma instancia, visualizar dados reais ou inserir um widget editorial depende do consumidor e do perfil, nao do catalogo.
- Preservar as engines VVJB/VVJS em Views: UI Patterns Views pode compor a **moldura** ou cada item sem substituir o mecanismo de carrossel/slideshow nem quebrar sua paginacao.
- Sem fork de Core/contrib. Patches somente se autorizados, com testes e justificativa.

## 5. Recursos e impacto visual especifico

### 5.1 UI Skins - substituição do motor visual por purpose

- Fonte visual canônica: `{provider}.ui_skins.themes.yml` e `{provider}.ui_skins.css_variables.yml`, depois de confirmar o schema/API instalados. Converter `PurposePaletteSchemes`, `aculta_purpose_palette`, `data-purpose-scheme` e `--aculta-purpose-*` para skins e CSS vars sem duplicar a lógica.
- As responsabilidades de `DomainPurposeManager` migram para **`DomainSiteManager` + registro `site_key`** no Portal; o resolvedor visual separado mapeia **`domain_id -> skin_id`**, com fallback. O nome `DomainPurposeManager` é apenas compatibilidade temporária até RN-05. Tema não consulta hostname e não muda configuração global do tema por request.
- UI Skins oferece temas e CSS variables nas configurações do tema, mas **não oferece, por si só, domínio -> skin por request**. Testar API e construir ponte de escopo de resposta sem mutação global e com `cache:contexts:domain`; não criar segunda paleta proprietária.
- `ColorModePreference` ainda resolve a preferência do usuário; UI Skins passa a ser o motor visual que entrega light/dark. Conferir precedência `usuário > default do site` e caches `domain`/`user`.
- A configuração/leitura visual por purpose foi removida diretamente. Concluir o piloto Links com capturas aprovadas como referência; depois validar os dez domínios e corrigir somente a implementação UI Skins, sem sombra, dual-read, alias ou restauração de configuração legada.
- **Gate:** UI Skins é única fonte para aparência, nenhuma contaminação entre domínios, contraste/foco/320 px preservados, configuração exportável e rollback validado.
- Plano técnico e matriz de classes/consumidores: `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`.

### 5.2 UI Styles - composicao contextual

- `ui_styles_page` está habilitado no Runtime e alinhado ao Sync. Confirmar estilos de regiões em tela autenticada; `ui_styles_entity_status` segue desabilitado até definir e validar a apresentação de rascunhos sem confundir estado visual com acesso/publicação.
- Manter `ui_styles_block`, `ui_styles_layout_builder`, `ui_styles_views` e `ui_styles_ui_patterns` (já no Sync) e validar as escolhas disponíveis em cada formulário consumidor.
- Distinguir estilos de *bloco*, *secao/regiao* e *conteudo*: border, elevation, radius, accent, spacing e tipografia nunca podem quebrar o contrato de 320 px.
- Para CKEditor 5, oferecer **conjunto editorial restrito**: nota institucional, citacao, destaque, aviso, olho editorial, titulo auxiliar e micro-legenda; sincronizar CSS da area editavel com render no tema. Validar save, reopen, filtro e Drupal CKEditor 5, sem `full_html` para usuarios comuns.
- `ui_styles_entity_status` oferece estilos para entidades **nao publicadas**, nao e API de workflow, moderacao ou visibilidade. Testar anonimato, usuario sem acesso, revisoes, cache e previews sem expor rascunhos.
- Em `ui_styles_page`, o template customizado da região footer preserva `attributes`; as demais regiões usam wrapper padrão. O shell envolve o conteúdo regional, então revisar visualmente antes de permitir estilos em institution/header/content/footer.

### 5.3 UI Patterns - blocos, displays, layouts, Field Group e Views

- `ui_patterns_blocks`: bloco SDC configuravel mantendo controle de acesso, contextos e configuracao de origem.
- `ui_patterns_field_formatters` e `ui_patterns_field`: tratar campos no Manage Display sem copiar logica do modulo; documentar diferenca formatter vs render de entidade.
- `ui_patterns_layouts`: slots representam regioes, props sao configuracoes; `thumbnail.webp` auxilia a escolha. Continuar com Layout Builder nos 22 displays ja habilitados, sem forcar os dois casos excluidos.
- `ui_patterns_views` possui tres niveis DISTINTOS: **row** (cartao por resultado), **style** (recipiente de todos os resultados) e **display** (composicao de cabecalho, filtros, resultados, empty, pager, links e footer). Mapear uso real antes de substituir template ou estilo de View.
- `ui_patterns_field_group`: **usar preferencialmente `2.0.x-dev@dev`**, travar commit em `composer.lock`, verificar Field Group 4.x, segurança e compatibilidade Drupal 12, antes de configurar displays. Prototipar e migrar por bundle; manter plugin funcional `field_group` e fallback.
- **UI Patterns CKEditor 5 EXCLUÍDO:** não instalar, ativar, prototipar ou adicionar novos filtros/widgets do módulo neste ciclo. Não é gate de encerramento da ADR-055; manter UI Styles CKEditor 5 para estilos textuais.

### 5.4 UI Icons - icones, menus, campos, CKEditor e Fluent Emoji

- Usar a Icon API do Core para definicoes em `aculta420_v2.icons.yml`. Para seleção e integracoes, avaliar UI Icons 2.1.x com submodulos de Menu, Picker ou Popover, UI Patterns, campos, link, CKEditor 5 e Library; conferir IDs reais instalados.
- Preservar pack Phosphor regular/duotone e 48 Fluent Emoji; auditar inventario ID -> SVG -> acessibilidade. Teste de contagem minima nao prova que cada ID referencia SVG valido.
- `aculta_editor` permanece catalogo e adapter de dados; `aculta_forum` permanece dono da persistencia, acesso e CSRF das reacoes; tema permanece apresentacao. Nao unificar *regra de negocio* com engine de icon.
- Menus `main`, `account`, `admin`: permitir escolha por interface apenas para autores autorizados, sem suprimir texto acessivel ou repetir carregamentos SVG. Considerar campos UI Icons e CKEditor em fases com controle de formato de texto.
- Em runtime, comparar desempenho, DOM, ausencia de SVG ausente, leitura de tela, navegacao por teclado e experiencia com o picker.

### 5.5 Bibliotecas e exemplos

- Manter UI Patterns Library universal, suas stories e preview sob demanda. `aculta_ui_library` segue dono da lista de blocos configurados reais.
- Reunir navegacao administrativa entre padrões, icones, estilos, skins e, se aprovado, exemplos; **nao criar outra biblioteca de SDCs**.
- UI Examples pode fornecer demonstracoes completas de paginas compostas, mas e **opcional** e nao autoriza a exposicao de dados reais.
- Display Builder e Drupal Canvas sao opcoes para estudo futuro, **fora deste cutover**; nao substituir Layout Builder sem ADR e benchmark.

## 6. Matriz visual por dominio/area (nomes de purpose aqui sao apenas IDs funcionais, NAO classes ou tokens visuais)

| Purpose / frente | UI Patterns + Views | UI Styles + Skins | Icons / conteudo |
| --- | --- | --- | --- |
| main / institucional | Painel principal, secoes e chamadas | Surface-panel, tricolor, largura e espacamento | Menus institucionais |
| account / Conta, Marley | Cards de tarefas, atividades e status | Paineis privados, estados sem cor isolada | Acoes e notificacoes |
| support / apoio | Grade de campanhas e blocos de apoio | Destaque de campanha e superficies | Icones de apoio |
| magazine / Revista | Teaser row, grid/editorial display, empty | Citacoes, chamadas e faixas editoriais | Icones de autor e compartilhamento |
| wiki / WE Sativa | Resultado de busca, verbete, infobox | Caixas informativas com acento | Contribuicao e referencias |
| shop / Green Stash | Cards de produtos e listagens Commerce | Variantes de produto, destaque nao enganoso | Carrinho, entrega, estados |
| courses / GanjaLab | Courses rows, jornada e progresso | Superficies de aulas e feedback | Conclusao e navegacao |
| forum / Growroom420 | Topico row, post, sidebar, estados vazios | Badges, faixas e indicadores | Reacoes Fluent, icones de comunidade |
| links / LinkWeed | Cards sociais e grids | Molduras de destaque e alinhamento | Redes e caminhos |
| eventos / Culturas | Cards de eventos, agenda, listagem de locais | Datas e chamadas, calendario | Localizacao e ingressos |

Em todos os casos: layout claro e escuro, breakpoint 320/390/768/1024/1440 px, zoom 200%, sem duplicar H1/main e sem expor contexto privado no HTML/cache.

## 7. Microfases executaveis (planejamento, NAO concluido)

### US-00 - Inventario e baseline (bloqueante)
- Ler AGENTS.md, ADR-054/055, status, lock, Core e contrib instalados; `drush pm:list --status=enabled`, `drush cget core.extension`, Composer e Config Sync.
- Mapear cada extensao UI Suite: disponivel no filesystem, Composer/lock, ativada no Runtime, presente no Sync, confiabilidade da origem, versao e owner.
- Enumerar SDCs, stories, styles, icon packs, Views/displays que ja consomem integracoes, Field Groups e editores por formato. Medir bytes, tempos e numero de itens.
- Gerar matriz antes/depois e rollback de config (somente staging isolado). Sem mudar configuracao ativa nesta fase.
- **Gate:** inventario rastreavel e divergencias de Runtime vs Git registradas, inclusive UI Icons Menu/Picker, Styles Page e Field Group.

### US-01 - Contratos e versões
- Revisar compatibilidade Core 11.5/12, versões do UI Suite, contratos Portal/tema e dependências existentes, mantendo UI Patterns 2.x atualmente usado.
- Para Field Group, **preferir `2.0.x-dev@dev`** com pin no lock, avaliar segurança, suporte a Field Group 4.x e Drupal 12; não usar beta2 por inércia.
- `ui_patterns_ckeditor5` explicitamente excluído; validar apenas `ui_styles_ckeditor5` com formatos atuais.
- **Gate:** lock reproduzível, nenhuma desativação indevida ou dependência transitiva escondida, decisão de risco registrada.

### US-02 - UI Skins substitui o motor de paletas por purpose
- Inventariar `PurposePaletteSchemes`, `SurfaceSettingsHooks::purposePaletteStyle`, `aculta_purpose_palette`, `data-purpose-scheme`, selectors CSS, theme-settings e contrato `AppearanceHooks`.
- Resolver `Domain ID -> skin_id` diretamente pela configuração Portal (já implementado); homologar modo do usuário, skin default e cache e executar o piloto Links. Não implementar modo sombra, dual-read ou configuração visual anterior.
- Testar piloto Links antes de migrar cada domínio; depois retirar paleta/formulário visual antiga e aliases após zero consumidores. Não remover `DomainPurposeManager` nem roteamento/ACL.
- **Gate:** 10 domínios × 2 modos, CSS computado/contraste, independência A/B e B/A de caches e Config Sync sem divergências.

### US-03 - UI Styles em regioes e entidades
- Mapear render Twig com atributos de regioes, blocos e Layout Builder; corrigir apenas pontos com perda de attributes.
- Prototipar `ui_styles_page` e `ui_styles_entity_status`; distinguir admin/status de publicacao de estado de acesso.
- **Gate:** estilos sem duplicacao, rascunho invisivel para usuario nao autorizado, config exportavel.

### US-04 - UI Styles CKEditor 5
- Selecionar estilos por formato existente (`revista_editorial`, `wiki`, `usuario_aculta`, `forum`), com acessos diferentes e minimo privilegio.
- Testar estilos inline/block, preview no editor e render publico, sanitizacao e continuidade dos modelos CKEditor Plugin Pack.
- **Gate:** inserir -> salvar -> reabrir -> renderizar, sem HTML indevido, XSS ou regressao visual.

### US-05 - UI Patterns Views
- Inventariar Views por modulo e escolher pilotos: Forum (row), Revista (display) e Eventos (style) respeitando caches e queries.
- Fazer mapeamento `views_field`, entidades e slots por fontes de dados suportadas; nao ligar um componente a todo display sem verificar pagers, exposed forms, empty e access.
- **Gate:** mesma quantidade/ordem/visibilidade de resultados, pager/filtros/HTMX preservados, sem nova engine de carrossel.

### US-06 - UI Patterns Layouts + Fields
- Validar contratos de slots e thumbnail dos layouts, composicao com Surface/Accent/Panel e 22 displays.
- Mapear Field Formatters a componentes antes de criar Twig exclusivo; respeitar os dois displays excluidos.
- **Gate:** render de campos e layout, export/import, fallback, 1 H1 e 1 main.

### US-07 - Field Group dev, risco e migração por bundle
- Preferir `drupal/ui_patterns_field_group:2.0.x-dev@dev`, verificar versão real e SHA, upstream, Field Group 4.x, Drupal 11.5/12 e vulnerabilidades.
- Planejar displays existentes e pilotos não críticos com migração e rollback sem alterar campos nem dados.
- Se risco de segurança/compatibilidade continuar, registrar BLOCKED_UPSTREAM e preservar alternativa nativa Field Formatters/SDCs, sem habilitação indiscriminada.
- **Gate:** labels/erros/ordem, segurança, acesso, cache e exportação; **não** considerar ausência de suporte upstream como PASS.

### US-08 - Icon packs e UI Icons
- Avaliar UI Icons 2.1 + Menu, Picker/Popover, UI Patterns, CKEditor, Fields e Library conforme necessidade; registrar submodulos exatos.
- Migrar de modo incremental seletores de icones de menu e SDC com acessibilidade; preservar adaptador Fluent.
- **Gate:** todos os IDs resolvem, nenhum SVG ausente, ARIA/teclado/HTMX, formatos de texto seguros.

### US-09 - Política de exclusão do UI Patterns CKEditor 5
- Registrar na auditoria de Composer/Config Sync que `ui_patterns_ckeditor5` não deve ser usado nesta rodada. Não instalar, habilitar, prototipar ou construir widgets do módulo.
- Revalidar que formatos CKEditor 5 mantêm os recursos atuais (Plugin Pack, filtro Fluent Emoji) e recebem apenas estilos editoriais via `ui_styles_ckeditor5`.
- **Gate:** zero novas dependências, filtros ou integrações `ui_patterns_ckeditor5`, sem regressão editorial.

### US-10 - Governanca da biblioteca e demonstracoes
- Vincular navegacao de padroes, stories, blocos, estilos, skins e icones, sem expandir acesso a previas reais.
- Adicionar exemplos sinteticos por purpose/familia, se UI Examples for adotado, com preview progressivo.
- **Gate:** 100% dos SDCs ainda registrados; busca e preview sob demanda; acesso apenas a administradores conforme ADR-054.

### US-11 - Homologacao final e aceite visual
- Tests Unit/Kernel/Functional/Browser e validadores existentes; escanear configuracao de todas as integracoes e diferencas Runtime/Sync.
- Firefox/Playwright: 320/390/768/1024/1440; light/dark; zoom 200%; teclado; logins anonimo/editor/admin; 10 purposes; cache host A/B e B/A aquecido.
- Medir tamanho CSS/JS por pagina, tempo e memoria da biblioteca, Layout Builder e Views; desempenho deve permanecer dentro da linha-base, registrar regressao e causa.
- Import/export em staging, rollback de tema/config e acesso; revisar com responsavel mosaico de capturas antes de declarar PASS.
- **Gate:** sem vazamento de acesso, sem drift de configuracao, sem CSS global obrigatorio desnecessario, e aceite visual humano. Nao marcar ADR encerrada por instalacao apenas.


### US-12 - Inventário integral e classificação de legado visual (obrigatório)
- Varredura de TODOS os módulos, temas, templates Twig, controllers, render arrays, Views, blocos, Field Group, formulários, layouts, CSS/JS, modais e áreas administrativas ACULTA.
- Classificar cada visual como `UI_SUITE_NATIVE`, `LEGACY_DEPRECATED` (**depreciado**, ainda consumido), `LEGACY_OBSOLETE` (**obsoleto**, sem consumidores após migração), `MIGRATION_IN_PROGRESS`, `BLOCKED_UPSTREAM` ou `FUNCTIONAL_NATIVE_EXCEPTION`; sem marcar as APIs funcionais Core/Form/Views/VVJ como obsoletas.
- Documentar por elemento o owner, consumidores, substituto UI Suite, estratégia de fallback, acesso/cache, testes, evidência visual e commit de retirada.
- Todos os SDCs permanecem descobertos na UI Patterns Library, inclusive internos e legados com status depreciado, sem conversão indevida para `status: deprecated` que esconda componentes.
- **Gate:** inventário revisável e completo por rotas, zero telas sem proprietário ou substituição planejada.

### US-13 - Substituição visual site-wide, por consumidores
- Iniciar com contratos transversais: UI Skins, botões, surfaces, cartões, formulários, menus, filtros, avisos, modais, iconografia e layout. Continuar por dez domínios e backoffice MAIN.
- Migrar View row/style/display, layouts, Field Group (após gate), campos, blokcs e templates para SDC + UI Patterns/Styles/Skins/Icons quando aplicável; preservar API nativa funcional do Drupal, acesso/cache, HTMX e VVJB/VVJS.
- Comprovar cada equivalência visual, permitir rollback; marcar legado `deprecated` enquanto consumido e `obsolete` depois do último consumidor. Só limpar após zero uso e testes.
- **Gate:** ausência de wrappers/caixas/cartões visuais proprietários ainda ativos sem classificação; matriz antes/depois e CSS/JS não carregados inutilmente.

### US-14 - Fechamento conjunto das ADR-054 e ADR-055 (dependente de RN-05)
- Reexecutar os gates pendentes P8/P9 da ADR-054, incluindo revisão visual humana, Config Sync/staging, cache cruzado, landings por domínio, dois displays excluídos e integridade das cinco regiões.
- Validar UI Skins como motor visual único, UI Suite em toda superfície visual viável, inventário deprecado/obsoleto e versão dev Field Group avaliada.
- Auditoria final: 10 domínios x light/dark, 320/390/768/1024/1440, zoom 200%, teclado, autenticação, Form API, WCAG, acessos, cache, Config Sync/Composer lock, rollback e performance.
- **Obrigatório RN-05 PASS:** 100% dos consumidores de `purpose` funcionais migrados a `site_key`; Domain IDs, URLs, regras de rotas, sessão de login e site list preservados, sem aliases antigos ativos nem vazamento cross-domain.
- Não incluir UI Patterns CKEditor 5 como bloqueio. Marcar `DEFERRED` caso qualquer gate não seja executado, atualizar status/roadmaps/ADRs apenas com evidência.
- **Gate:** aceite documentado do responsável, zero regressão crítica, nenhuma duplicidade de fonte visual, e matriz de pendências efetivamente encerrada.


## 8. Entregaveis esperados e governanca

Cada microfase tem: diff isolado, IDs de config alterados, teste/resultado com PASS/FAIL/DEFERRED, screenshots e comparação, performance antes/depois, rollback e atualizacao de `TODO.md` / status YAML quando uma entrega tecnica for efetivada. Trabalhar diretamente em `dev` em commits atomicos, sem branch/PR nova, preservando trabalho paralelo. Nunca mexer em Core/contrib nem realizar `drush cim`, `updatedb`, exclusao ou escrita de dados sem a autorizacao especifica prevista no AGENTS.md. A publicacao deste plano **nao autoriza** esses atos no Runtime.

## 9. Fontes oficiais consultadas (2026-11-10)

- https://www.drupal.org/project/ui_suite
- https://www.drupal.org/project/ui_patterns
- https://project.pages.drupalcode.org/ui_patterns/
- https://project.pages.drupalcode.org/ui_patterns/1-users/4-with-views/
- https://project.pages.drupalcode.org/ui_patterns/1-users/2-as-layout/
- https://project.pages.drupalcode.org/ui_patterns/1-users/7-in-ckeditor/
- https://project.pages.drupalcode.org/ui_patterns/2-authors/2-best-practices/
- https://www.drupal.org/project/ui_styles
- https://www.drupal.org/project/ui_skins
- https://www.drupal.org/project/ui_icons
- https://www.drupal.org/project/ui_patterns_field_group

**Nota:** documentos de UI Patterns 1.x no Drupal.org nao sao referencia normativa para a API 2.x; usar a documentacao dedicada 2.x acima.

## 10. Nova trilha RN-00 a RN-05: nomes internos funcionais do Portal

O responsável aprovou também substituir a nomenclatura `purpose` em todo o Portal. O destino canônico passa a ser **`site_key`**, distinto de **`domain_id`** (entidade Drupal Domain) e **`skin_id`** (UI Skins). Preservar os dez valores de chave e todos os Domain IDs, URLs e conteúdos.

O plano detalhado encontra-se em `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`: RN-00 inventário, RN-01 API/compatibilidade, RN-02 rotas e segurança, RN-03 apresentação/state/sessão, RN-04 retirada segura de APIs legadas, RN-05 homologação. A RN-01 deve anteceder a integração final da US-02; **a US-14 não encerra ADR-054/055 sem RN-05 PASS**.

Tratar transições de `_aculta_domain_purpose`, `_aculta_domain_purposes`, `DomainPurposeManager`, `ContentPurposeResolver`, `DomainPurposeRequestSubscriber`, `ActivePurposesState`, `domain_presentation.identity.purpose`, `domain_sites[*].purpose` e sessão `login_destination['purpose']` como possíveis breaking changes; manter adaptadores internos temporários, negar conflitos de metadados de rota e testar cache/ACL. Não alterar nomes de rotas públicas nem transferir roteamento para UI Skins.

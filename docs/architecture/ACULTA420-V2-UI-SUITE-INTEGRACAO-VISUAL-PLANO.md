# ACULTA420 V2 - Plano de integracao visual integral da UI Suite

**Estado:** PESQUISA E PLANEJAMENTO CONCLUIDOS; IMPLEMENTACAO NOVA PENDENTE (US-00 a US-11).
**Data de referencia:** 2026-11-10. **Branch alvo:** dev. **Decisoes:** ADR-054 e ADR-055 (adendo da integracao integral).
**Owners:** SDC, tokens, variacoes e CSS no tema `aculta420_v2`; regras, dados, acesso, cache e dominio nos modulos proprietarios; registro e configuracao de integracoes no Drupal Core e UI Suite. **Nao e um relatorio de teste do Runtime.**

## 1. Objetivo e beneficio visual

A UI Suite sera a camada de *site building* do design system proprio ACULTA420 V2, e nao um tema pronto, um segundo framework CSS ou um substituto do Portal. Preservar todos os SDCs internos, editoriais e reutilizaveis no catalogo universal (plano UP-00 a UP-07), sem tornar todos automaticamente editaveis por qualquer papel.

Beneficios tangiveis:
1. Uma mesma superficie, card, moldura tricolor, CTA, tabela ou estado vazio aparece em Views, blocos, displays e Layout Builder sem HTML duplicado.
2. Administradores escolhem variantes e composicao pela UI, com opcoes fechadas, contraste e responsividade previstos nos SDCs, sem CSS arbitrario.
3. Layouts por purpose e por pagina aproveitam os mesmos componentes, enquanto identidade visual, menus e dados continuam governados pelos contratos existentes.
4. CKEditor 5 recebe estilos editoriais coerentes com a publicacao; widgets SDC ricos sao recurso adicional **condicionado** a seguranca e fidelidade de renderizacao.
5. Icons Phosphor e Fluent Emoji tornam-se catalogos pesquisaveis e reutilizaveis em menus, campos e componentes, sem duplicar a semantica das reacoes.
6. Light/dark e paletas se tornam mais governaveis por controles de skins, sem criar configuracao concorrente ou trocar a identidade em runtime de forma insegura.
7. A biblioteca administrativa mostra tanto catalogos quanto composicoes reais, com previsualizacoes progressivas para conter custo de descoberta e renderizacao.

**Regra visual:** SDC = estrutura/apresentacao; UI Styles = classes de variacao; UI Skins = CSS variables e esquemas; UI Icons = origem/selecao de icones; UI Patterns = conectores de SDC com APIs Drupal. Nao atribuir a qualquer um deles acesso a dados, permissao de entidade ou isolamento multidominio.

## 2. Fotografia do repositorio em origin/dev, 2026-11-10

| Evidencia consultada | Estado constatado | Certeza |
| --- | --- | --- |
| `web/themes/custom/aculta420_v2/aculta420_v2.info.yml` | Tema 1.5.0, `base theme: false`, cinco regioes e schemas estritos | Confirmado no Git |
| `web/themes/custom/aculta420_v2/components/` | Familia extensa de SDCs, com layouts, Forum, modal, superficies, stats, etc. | Confirmado no Git; inventario quantitativo deve ser regenerado |
| `aculta420_v2.ui_styles.yml` | Estilos de botoes, cartoes, paletas, spacing, superficie, acento, sombra e raio | Confirmado no Git |
| `aculta420_v2.icons.yml` | Packs Phosphor Regular, Duotone e Fluent Emoji declarados | Confirmado no Git |
| `config/sync/core.extension.yml` | `ui_patterns`, blocks, field, field_formatters, layouts, library, views, `ui_styles`, block, layout_builder, ui_patterns, views habilitados no Sync | Confirmado no Git |
| `composer.json` | `drupal/ui_patterns` 2.0.x-dev, `drupal/ui_patterns_layouts` ^2.0, `drupal/ui_patterns_field_group` ^2.0@beta, `drupal/ui_styles` ^1.22 | Confirmado no Git |
| `core.extension.yml` | Sem entradas observadas para `ui_skins`, `ui_styles_ckeditor5`, `ui_styles_entity_status`, `ui_styles_page`, `ui_patterns_field_group`, `ui_patterns_ckeditor5` e `ui_icons_*` | Ausente no Sync consultado; nao prova Runtime |
| `composer.json` | Sem `drupal/ui_skins` e `drupal/ui_icons` declarados diretamente | Confirmado como ausencia explicita; conferir dependencias transitivas e lock |
| `docs/architecture/ACULTA420-V2-P8-HOMOLOGACAO.md` | Layout Builder 22/24 displays compativeis; duas exclusoes por perda de conteudo | Evidencia de relatorio, nao teste reexecutado |
| `docs/operations/ESTADO-DA-SESSAO.md` | UI Patterns em lote com previas sequenciais 90/90 no Runtime; estado da sessao mudou depois | Evidencia registrada por outro agente |
| `aculta_editor` e `aculta_forum` | Adaptador FluentEmojiIconAdapter, filtro DOM, seletor HTMX e template SDC | Confirmado no Git |

**Cuidado com claims antigos:** o corpo historico da ADR-055 declara UI Icons Menu e UI Styles Page 'ativos'. Sem reflexo no Config Sync, classificar **NAO COMPROVADO EM CONFIG**, e nao PASS, ate testar Runtime + Composer + Sync. Igualmente, a existencia de plugin em um pacote nao prova uso de suas configuracoes por displays e Views.

## 3. Verificacao upstream de versoes e lacunas

| Projeto | Referencia upstream em 10/11/2026 | Implicacao |
| --- | --- | --- |
| UI Patterns 2 | 2.0.23 estavel, Drupal ^10.6 / ^11.3 / ^12; site usa constraint dev | Comparar `composer.lock` e APIs atuais, estudar migracao para stable por tarefa isolada |
| UI Styles | 1.22 estavel, Drupal ^10.3 / ^11 / ^12 | Integracoes podem ser ativadas em lotes com testes |
| UI Skins | 1.2.1 estavel, Drupal ^11.4 / ^12 | Candidato preferencial para gestao de variaveis/esquemas do tema |
| UI Icons | 2.1.0 estavel, Drupal ^11.3 / ^12; 2.0.x nao e mais suportado | Preferir 2.1.x, validar Composer/lock e nomes reais dos submodulos |
| UI Patterns Field Group | 2.0.0-beta2, Drupal ^9/^10/^11, sem cobertura da politica de seguranca do Drupal | **GATE DE RISCO:** prototipo isolado; sem ativacao ampla se nao houver estrategia formal de seguranca e Drupal 12 |
| UI Patterns em CKEditor 5 | Documentacao atual mostra `ui_patterns_ckeditor5` e filtro 'Embed components' | Avaliar com restricao por formato: upstream informa que TODOS os SDCs aparecem e que previa nao carrega assets |

**Nao instalar ou atualizar por suposicao:** resolver primeiro as versoes compativeis do `composer.lock`, verificar os arquivos `*.info.yml` dos modulos baixados e as dependencias. UI Icons base do Core e a camada UI Icons contrib nao sao a mesma coisa. Nao presumir que `ui_patterns_field_group` passa no Drupal 12 so porque `ui_patterns` passa.

## 4. Modelo de responsabilidades e fronteiras

`DomainPurposeManager / Portal / contratos neutros e cache`
  -> `HTML root: data-theme, data-purpose e tokens aprovados`
  -> `UI Skins: variaveis/configuracao governada`
  -> `UI Styles: opcoes finitas de classes para wrappers e elementos`
  -> `SDC: markup, slots/props e comportamento`
  -> `UI Patterns: adaptadores para Block, Layout, Field, Views, CKEditor (se aprovado)`
  -> `UI Icons: packs, seletores, menu e componentes`

- O tema nao consulta hostname, entidade ou usuario. Contexto, access checks, cache tags e contexts sao fornecidos pela origem. Sem duplicar `--purpose-*`, `data-theme`, ou `domain_presentation` em uma segunda engine.
- UI Skins altera preferencias visuais *configuradas*, mas nao resolve automaticamente o purpose por host. Estudar compatibilidade com o pipeline atual e evitar mudar a configuracao global do tema quando um dominio muda.
- As cinco regioes do shell e a barra institucional sao imutaveis neste escopo; Layout Builder altera somente composicao de conteudos e displays elegiveis.
- UI Styles nunca habilita edicao livre de CSS nem substitui classes semanticas. Classes do editor sao apenas as permitidas pelo formato de texto.
- Componentes SDC permanecem descobraveis; autorizacao para **editar** uma instancia, visualizar dados reais ou inserir um widget editorial depende do consumidor e do perfil, nao do catalogo.
- Preservar as engines VVJB/VVJS em Views: UI Patterns Views pode compor a **moldura** ou cada item sem substituir o mecanismo de carrossel/slideshow nem quebrar sua paginacao.
- Sem fork de Core/contrib. Patches somente se autorizados, com testes e justificativa.

## 5. Recursos e impacto visual especifico

### 5.1 UI Skins - modos, paletas e propriedades

- Mapear tokens existentes (`src/styles/tokens.css`) e `aculta420_v2.ui_styles.yml` antes de declarar `aculta420_v2.ui_skins.css_variables.yml` e `aculta420_v2.ui_skins.themes.yml`.
- Definir quais variaveis sao realmente editaveis pelo admin: cores semanticas de acento, superficies, sombra, radius e tipografia controlada; preservar limites de contraste.
- Preservar as dez paletas Skunk Family por purpose e a preferencia light/dark baseada em `data-theme`. UI Skins nao e um segundo seletor de dominio; qualquer ponte de variaveis depende de contrato explicito, render e cache multidominio.
- Proibir CSS inline livre ou mudanca de cores que reduza contraste, inclusive focus-visible; oferecer fallback seguro se UI Skins estiver indisponivel.
- Previsualizar combinacoes em uma matriz light/dark × 10 purposes, com diff de tokens computados.

### 5.2 UI Styles - composicao contextual

- Habilitar onde justificado: `ui_styles_block`, `ui_styles_layout_builder`, `ui_styles_views`, `ui_styles_ui_patterns` (ja no Sync), e avaliar `ui_styles_page`, `ui_styles_ckeditor5`, `ui_styles_entity_status`.
- Distinguir estilos de *bloco*, *secao/regiao* e *conteudo*: border, elevation, radius, accent, spacing e tipografia nunca podem quebrar o contrato de 320 px.
- Para CKEditor 5, oferecer **conjunto editorial restrito**: nota institucional, citacao, destaque, aviso, olho editorial, titulo auxiliar e micro-legenda; sincronizar CSS da area editavel com render no tema. Validar save, reopen, filtro e Drupal CKEditor 5, sem `full_html` para usuarios comuns.
- `ui_styles_entity_status` oferece estilos para entidades **nao publicadas**, nao e API de workflow, moderacao ou visibilidade. Testar anonimato, usuario sem acesso, revisoes, cache e previews sem expor rascunhos.
- Em `ui_styles_page`, confirmar que regioes personalizadas recebem `attributes` em Twig; revisar o risco de colidir com o shell institucional antes de permitir edicao.

### 5.3 UI Patterns - blocos, displays, layouts, Field Group e Views

- `ui_patterns_blocks`: bloco SDC configuravel mantendo controle de acesso, contextos e configuracao de origem.
- `ui_patterns_field_formatters` e `ui_patterns_field`: tratar campos no Manage Display sem copiar logica do modulo; documentar diferenca formatter vs render de entidade.
- `ui_patterns_layouts`: slots representam regioes, props sao configuracoes; `thumbnail.webp` auxilia a escolha. Continuar com Layout Builder nos 22 displays ja habilitados, sem forcar os dois casos excluidos.
- `ui_patterns_views` possui tres niveis DISTINTOS: **row** (cartao por resultado), **style** (recipiente de todos os resultados) e **display** (composicao de cabecalho, filtros, resultados, empty, pager, links e footer). Mapear uso real antes de substituir template ou estilo de View.
- `ui_patterns_field_group`: permite apresentar grupos de campos como componentes SDC; prototipar em bundle nao critico com controle de valores, labels, form display vs view display, cache e fallback. Nao substituir o `field_group` contrib nem assumir que o modulo estara compativel com Drupal 12.
- `ui_patterns_ckeditor5`: widget de SDC inserido no texto e renderizado pelo filtro. **GATE P0**: upstream oferece todos os componentes no seletor (sem allowlist por formato), e nao carrega CSS/JS dos componentes na previa do editor. Nao habilitar para contas editoriais comuns sem mecanismo seguro e verificavel de limitacao de componentes e equivalencia de preview. Em formato administrativo de teste, usar apenas SDCs puros e sem dados privados; checar parser, sanitizacao, acesso e serializacao.

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

## 6. Matriz visual por purpose (alvos, nao telas prontas)

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

### US-01 - Contratos e versoes
- Revisar compatibilidade Drupal 11.5 beta / Drupal 12, estabilizar UI Icons 2.1 e UI Skins 1.2 em plano de dependencias, sem instalar inadvertidamente.
- Resolver politica de Field Group beta sem cobertura de seguranca; registrar alternativa via Field Formatters/Layout Builder se inviavel.
- Confirmar compatibilidade de `ui_patterns_ckeditor5` e `ui_styles_ckeditor5` com formatos existentes.
- **Gate:** lock reproduzivel em ensaio, sem edicao de contrib; lista de extensoes aprovadas.

### US-02 - UI Skins e tokens
- Prototipar arquivos de CSS variables e themes seguindo exemplos upstream; escopo restrito a opcoes semanticas.
- Definir precedencia Portal/purpose/data-theme/UI Skins, heranca de valores e invalidacao de cache. Preferir opt-in e fallback para tokens atuais.
- **Gate:** 10 purposes × 2 modos, CSS computado, contraste, zero vazamento de paleta entre hosts.

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

### US-07 - Field Group (experimental, risco elevado)
- Conferir `field_group` e beta, compativel com Core e seguranca. Testar somente fixture nao critica isolada.
- Se qualquer gate Drupal 12 ou seguranca falhar, suspender ativacao em ambiente compartilhado e documentar alternativa.
- **Gate:** acesso, agrupamento de labels/erros, view/edit, reversibilidade e parecer de risco.

### US-08 - Icon packs e UI Icons
- Avaliar UI Icons 2.1 + Menu, Picker/Popover, UI Patterns, CKEditor, Fields e Library conforme necessidade; registrar submodulos exatos.
- Migrar de modo incremental seletores de icones de menu e SDC com acessibilidade; preservar adaptador Fluent.
- **Gate:** todos os IDs resolvem, nenhum SVG ausente, ARIA/teclado/HTMX, formatos de texto seguros.

### US-09 - UI Patterns CKEditor 5 (experimental, condicionado)
- Prototipo admin-only; estudar mecanismo de restricao por formato/role antes de permitir SDCs arbitrarios.
- Medir diferencas da previa (upstream sem CSS/JS no editor), simular dados e bloquear componentes privados/ativos.
- **Gate:** sem allowlist segura ou preview suficiente, DEFERRED; nenhuma publicacao/editor comum recebe a funcionalidade.

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

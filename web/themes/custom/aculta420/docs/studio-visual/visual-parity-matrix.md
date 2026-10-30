# Matriz de paridade visual do OpenCulturas Next → ACULTA Studio

**OCS-F0 — 2026-10-29.** Status inicial por inspeção estática; nível visual
numérico será preenchido depois das capturas pareadas. `Pede-A` indica mudança
que pertence a código/rota/serviço fora do ownership da Frente B.

| Tela/estado | URL/rota ACULTA | Evidência upstream concreta | Referência/viewport | SDC atual | Alteração candidata | Owner | Fidelidade inicial | Risco/consumer | Teste | Status comprovado |
|---|---|---|---|---|---|---|---|---|---|---|
| Hub Studio | `/studio` · `aculta_studio.hub` | `templates/views/user-dashboard-views.scss`; `templates/layout/page.html.twig` | Captura ACULTA 1440/390; dashboard upstream autenticado não disponível; requer 320 | `context-header`, `card`, `content-grid`, `empty-state` | Hierarquia de experiências disponíveis | B tema/SDC; A para disponibilidade/provider | Estrutural, dashboard upstream não disponível | Só experiências autorizadas/registradas; sem cards fictícios | A/B, módulos opcionais, teclado, viewport | Capturado; resposta vazia (26 caracteres), Pede-A |
| Meus Salvos / Seguindo / Recomendações | `/studio/salvos` · `aculta_studio.salvos` | `templates/views/user-dashboard-views.scss`; padrões de lista e paginação | Captura autenticada equivalente não disponível; requer 320/390/1440 | `studio-*` via `PortalUiHooks`; SDCs `card`, `context-tabs` e `empty-state` são candidatos, ainda sem prova de consumer SDC nesta tela | Harmonizar lista real e estados vazios sem anunciar operação não disponível | Tema B; Portal (consulta de salvos) e Studio (apresentação) | Código de lista/paginação/recommendation existente; paridade visual não medida | Conteúdo privado, cache por UID, ACL de cada entity e módulo `aculta_index` opcional | Kernel de isolamento A/B, cache quente, teclado/no-JS, light/dark | Provider e consumer no registry; testes ARQ-07B documentados; Runtime A/B e capturas pendentes |
| Dashboard Marley | `/studio/marley` · `aculta_studio.home` | `templates/views/user-dashboard-views.scss`; `templates/layout/page.html.twig` | Home pública upstream + rota ACULTA não equivalente; 1440/390 | `context-header`, `card`, `content-section`, `content-grid`, `empty-state` | tokens e composição interna após fechar escopo mobile-first | B tema/SDC; A se exigir provider | Baseline ACULTA capturado; upstream dashboard autenticado não disponível | Não inventar salvos/recomendações/organizações; cache UID | Firefox visível, anônimo/A/B, cache, zoom, teclado | PNGs inspecionados; sem overflow horizontal nas larguras 390/1440 desta rota; gate de paridade pendente |
| GanjaLab editorial | `/studio/ganjalab` e abas reais | Dashboard/listagem OpenCulturas; `node--compact.html.twig` | Captura ACULTA 1440/390; requer 320 | `context-header`, `card`, `content-grid`, `empty-state` | Reuso do layout de experiência e estados reais | B visual; A para providers/ACL | Estrutural | Não confundir com experiência LMS GanjaLab420; plugin opcional | Autoria A/B, módulos disponíveis/ausentes, no-JS | Captura renderiza Minha Conta; rota esperada não foi identificada, Pede-A |
| GanjaLab — painel LMS do estudante | `/studio/ganjalab` · `aculta_lms.student_dashboard` | Dashboard/listagens e cards upstream | Captura ACULTA 1440/390, conteúdo inesperado; requer 320 | `card`, `content-grid`, `context-header`, `empty-state` | Responsividade de curso/progresso/certificado | B visual; LMS/Studio A para dados/acesso | Estrutural | Estado acadêmico, matrícula e ACL permanecem no LMS; rota em `aculta_lms.routing.yml` | Matrícula, turma, certificado, A/B, cache | Conteúdo capturado é Minha Conta; rota LMS não homologada, Pede-A |
| WE Sativa / Wiki | `/studio/we-sativa` e subrotas reais | Views/listagens, `node--compact.html.twig`, templates de revisão | Capturas autorizadas equivalentes; 1440/768/390/320 | `wiki-entry`, `card`, `content-grid`, estados existentes | Autoria, revisão e moderação visualmente distintas | B visual; Wiki A para filas/ACL | Estrutural | Nunca exibir revisão/proposta sem grants; provider pode faltar | dois autores, grants, módulo ausente, foco/no-JS | Consumer descrito no roadmap; captura pendente |
| Green Stash — entrada integrada | `/estudio/loja` (rota de Commerce) | Dashboard OpenCulturas como inspiração de hierarquia | Capturas Studio/Commerce pareadas; 1440/768/390/320 | SDCs compartilhados de `card`, `content-section`, `context-tabs` | Integrar navegação comercial ao contexto Studio | B visual; Commerce A para rotas/recursos | Não há tela autenticada upstream equivalente confirmada | `GreenStashExperienceProvider` é integração técnica; não indica produto independente | ACL loja, purpose account, navegação Studio, sem admin Drupal | Capturado em 1440/390; conta sintética sem autorização recebe 403; fixture operador autorizada pendente |
| Green Stash — produtos/variações | `/estudio/loja/produtos`, `/estudio/loja/produtos/novo`, `/estudio/loja/produtos/{produto}` | `templates/node/node--compact.html.twig`, cartões de listagem | Catálogo real; 1440/390/320 | `card`, `content-grid`, componentes product atuais | Apresentar catálogo, detalhe e rascunho atualmente implementados | B visual; Commerce A para operação/dados | Inspiracional apenas | Rotas de catálogo/detalhe/cadastro existem; contrato de variação e status de rascunho precisam validação do owner | loja autorizada, outra loja negada, submit/no-JS | Código presente; runtime e fluxo completo pendentes |
| Green Stash — pedidos/estoque/envios | `/estudio/loja/{pedidos,estoque,envios}` | Padrões de lista/filtros/formulários | Somente telas/ações existentes; 1440/390/320 | `card`, `content-grid`, tabela responsiva existente | Envios apresenta remessas e transições; pedidos/estoque aparecem na navegação preparatória | B visual; Commerce/Shipping A para operações | Sem equivalência direta confirmada | Não encaminhar a admin nativo Drupal; ACL e transições CSRF permanecem no Commerce | loja A/B, transições, Commerce Stock/Shipping, cache | Código confirma leitura e transições de envios; listas de pedidos/estoque não encontradas nesta varredura |
| Green Stash — promoções/clientes/relatórios/configurações | `/estudio/loja/{promocoes,clientes,relatorios,configuracoes}` | Não há tela upstream de Studio autenticado equivalente confirmada | Sem captura | SDC compartilhado apenas se existir consumer real | Manter estado preparatório atual; projetar UI operacional só após recurso/consumer real | Commerce A, tema B após Pede-A/contrato | Não mapeado | Rotas existem, porém o controller mantém aviso preparatório; não anunciar funções como operacionais | Registro da operação real, permissão, consumer e teste do owner | Rotas de navegação existem; capacidade operacional não verificada/não deve ser presumida |
| Planos Mercado Pago | `/studio/conta/mercado-pago/planos` · `aculta_pagamentos_mercadopago.studio_plans` | Views/filtros/forms upstream como inspiração, sem equivalente de domínio confirmado | Captura autenticada real; 1440/390/320 | SDCs existentes a identificar no consumer | Apenas acabamento da superfície de planos presente | B visual; integração/pagamentos A | Não comparável por domínio | Provider condicional pelo módulo; acesso exige permissão `administrar doacoes` | módulo ausente/presente, permissão, anônimo, foco, cache | Provider e routeName no registry; rota real no owner, visual pendente |
| Navegação principal do Studio | `/studio/marley`, `/studio/marley/{aba}` | `templates/layout/header.scss`, `templates/navigation/menu.scss`, `templates/layout/page.html.twig` | Capturas 1440/390 | `context-tabs`; markup `data-aculta-studio` | menu e abas no conteúdo do Studio | B visual; A para URLs/ações | Sem medida | Institution Bar e Domain Header intactos; só URLs verdadeiras | teclado, URL/back-forward, no-JS/AJAX | Mapeado; gate browser pendente |
| Navegação de seção/subseção | `/studio/marley/{aba}/{secao}[/{sub}]` | `templates/navigation/tabs.scss` | 1440/768/390 | `context-tabs` | variantes/slots ou CSS Studio escopado | B | Sem medida | Não duplicar tabs do shell | teclado, `aria-current`, sem JS | Mapeado; gate browser pendente |
| Lista Meu Trabalho | `/studio/marley/{aba}/{secao}/{sub}` | `templates/views/user-dashboard-views.scss`, `templates/node/node--compact.html.twig` | 1440/390 | `card`, `content-grid`, `empty-state` | variantes para item real/estado vazio | B; provider/cache A | Estrutural | Apenas conteúdo autorizado; revisão e cache por usuário | 2 autores, status, cache quente, pager | Mapeado; provider não reavaliado nesta fase |
| Criar/editar conteúdo (Marley) | rotas existentes sob `/studio/marley/.../criar` e edição por consumer | `sass/base/_forms.scss`, `sass/base/_buttons.scss`, `templates/views/views-exposed-forms.scss` | Captura real do formulário; 1440/390 | `content-section`, `context-header`, SDC de feedback existente | acabamento de controles sem alterar Form API | B visual; A para form/rotas | Sem captura ainda | AJAX, CSRF, estados de erro; Pede-A se comportamento mudar | submit positivo/negativo, AJAX/no-JS, foco | Inventário de arquivos; formulário não homologado |
| Filtros/listas editoriais | views de Studio existentes; URL fornecida pelo consumer | `templates/views/views-exposed-forms.scss`, `templates/views/user-dashboard-views.scss` | Desktop/mobile | `content-grid`, `card`, `empty-state` | filtros responsivos e estado vazio | B; A se provider | Sem medida | Paginação e ACL reais | teclado, filtros, vazio/erro | Mapeado; execução pendente |
| Mídia/galeria | rota real do consumer Studio | `templates/media/media.scss`, `templates/paragraph/paragraph--gallery.scss` | 1440/768/390 | SDCs de mídia existentes; verificar consumer | apenas apresentação das entidades existentes | B; A para upload/storage | Sem medida | Não alterar media storage/upload | preview, modal/AJAX, limites e erro | Consumer/rota precisa de validação autenticada |
| Estados vazio/erro/sucesso | rotas de listas/formulários acima | `templates/components/status-messages.html.twig`, `sass/base/_messages.scss`, `user-dashboard-views.scss` | Stories + capturas de fluxo | `feedback/empty-state` e feedback existentes | variantes e stories reais | B; A para estado funcional | Conceitual | Mensagem não substitui confirmação do owner | contraste, foco, leitores de tela | SDC disponível; consumer a conferir |
| Diálogos e ações | onde os owners já expõem a ação | `sass/base/_modal-dialog.scss`, `templates/block/block--search-toggle.html.twig`, `js/opcult.js` | viewport 390/1440 | componentes de diálogo existentes; inventariar consumer | estilos em escopo Studio | B; A para ações/JS | Sem medida | Não adicionar função falsa; garantir fechamento/teclado | teclado, Escape, foco, sem JS/AJAX | Mapeamento upstream feito; consumer pendente |
| Conta — hub | `/studio/conta` · `aculta_studio.account` | `templates/layout/layout.scss`, `templates/navigation/tabs.scss` | Captura autenticada; 1440/390 | `context-header`, `context-tabs`, `content-grid` | composição fiel com links já autorizados | B; A para provider/route | Sem medida; captura autenticada pendente | Conta privada ≠ Perfil; `/conta-legada` permanente | S-R5, duas contas, anônimo, no-JS | URLs no inventário; S-R5 não homologada |
| Conta — formulários e pedidos | `/studio/conta/dados`, `/endereco`, `/documentos`, `/seguranca`, `/senha`, `/conexoes`, `/pedidos`, `/apoio`, `/preferencias` | `sass/base/_forms.scss`, `sass/base/_buttons.scss`, `templates/contrib/password.scss` | Capturas por formulário, viewport 390/1440 | `content-section`, formulários Drupal, `context-tabs` | estilos base escopados | B; owners Portal/User/Commerce A | Sem medida | Form API, CSRF, dono único; pedidos usam owner Commerce | matriz S-R5 e estados negativos/positivos | URLs mapeadas; forms não homologados |
| Perfil — edição privada | `/studio/perfil`, `/apelido`, `/privacidade`, `/bio`, `/redes/{rede}` | `templates/node/node--profile/*`, `templates/field/field--extra-field-social-media-buttons.html.twig`, `sass/base/_forms.scss` | Capturas do titular, viewport 390/1440 | `profile-editor`, `profile-identity`, `profile-bio`, `profile-social` | acabamento das composições existentes | B; política/Form API A | Sem medida | Não vazar dados de conta nem tornar perfil público | S-R4/S-R5, A/B, grants/cache | SDCs e URLs mapeados; gates pendentes |
| Perfil — público | `/u/{apelido}` (MAIN) | `templates/node/node--profile/node--profile--full.html.twig` | Página pública real, viewport 390/1440 | `profile-identity`, `profile-bio`, `profile-social` quando autorizado | identidade visual só se consumer público já usa SDC | B; policy A | Sem medida | Exibir só campos opt-in; perfil privado não vira Conta | anônimo, visibilidade, e-mail opt-in, cache | Matriz S-R5 define contrato; execução pendente |
| Foto/avatar | `/studio/perfil` e URL pública autorizada | `templates/media/media--image.html.twig`, `templates/field/field--node--field-portrait--profile.html.twig` | Modal/estado sem imagem | `profile-identity` slot `avatar` | estilo de slot/estado sem foto | B; A para upload/crop | Sem medida | Apenas `aculta_image` persiste/processa | upload/crop, visibilidade, fallback, cache | Consumer e estado real pendentes |
| Preferências claro/escuro | `/studio/conta/preferencias` | `sass/dark-mode/_index.scss`, `sass/dark-mode/_misc.scss` | 1440/390 em ambos os modos | SDCs reutilizados sem novo componente | tokens escopados por modo | B; Portal A | Sem medida | Não salvar preferência para fazer captura sem restore | contraste AA, preferência, screenshot lado a lado | Upstream mapeado; runtime gate pendente |
| Shell multidomínio no purpose ACCOUNT | `/studio/*`, `/estudio/loja` | A barra institucional é ACULTA, distinta do shell OpenCulturas | Captura autenticada 1440/390 | `institution-bar`, header de domínio do tema | Preservar ambos no shell compartilhado | B visual; Domain/Portal A para composição/availability | Parcial: Institution Bar presente, `.aculta-header` ausente nas oito rotas capturadas | Não duplicar shell, não retirar institution bar; ausência do Domain Header requer decisão do owner | Firefox DOM + screenshot, keyboard, purpose matrix | Marcadores DOM confirmam Institution Bar em todas as rotas válidas; Domain Header ausente, Pede-A |
| Shells de outros purposes | Fórum, Wiki, Revista, Loja, Eventos, GanjaLab | CSS upstream não se aplica fora do Studio | Capturas em purpose real | SDCs existentes | teste de vazamento dos seletores/tokens | B | Sem medição | Vazamento global é bloqueador | comparação antes/depois em cada purpose | baseline não capturado nesta fase |

## Escalas de aceite

- **Estrutural:** presença e ordem dos blocos conferem com a fonte de código; não é paridade visual.
- **Parcial:** contraste, tipografia, geometria e estados medidos em capturas equivalentes; limitações registradas.
- **Fiel:** comparação de tela/estado/viewport equivalente revisada, sem regressões de acessibilidade ou shell.
- **Aprovado:** gate F7 completo, evidências visíveis e validação do responsável. Nenhum status inicial desta matriz significa aprovado.

## Atualização de captura Firefox — nove larguras (2026-10-29)

A captura expandida `$SUITE_OUT/aculta-ocs-f0-responsive-20261029/baseline.json`
contempla 320/360/390/599/600/768/1024/1280/1440 nas oito URLs Studio e na
referência pública OpenCulturas. No conjunto Studio: zero overflow medido nas
sete páginas que renderizaram; o hub está vazio nas nove larguras; Green Stash
retorna acesso negado nas nove para a conta sintética; GanjaLab ainda apresenta
Minha Conta, sem o painel LMS. Institution Bar presente nas sete rotas
renderizadas, Domain Header ausente nas oito URLs, marcadores `[data-aculta-studio]`
somente em Marley. A execução foi contra o checkout servidor `f2708e60f`, cuja
árvore versionada de tema/Studio/Commerce coincidia com a PR `5734bed87`; havia
alterações locais de Eventos, então esta evidência não aprova o head completo
nem a regressão global. Os screenshots Marley 320/1440 foram inspecionados; as
capturas não equivalem a referência autenticada upstream nem aprovam a
fidelidade ou os gates de acessibilidade. Status geral continua **PARCIAL**.

## Foco por teclado — evidência parcial (2026-10-29)

O probe em Marley, Conta e Perfil a 320/1440 px registrou 12 Tabs por tela,
com 72 de 72 alvos correspondendo a `:focus-visible` e outline calculado.
Marley/320 foi inspecionada e exibe o foco do link de skip. Cobertura não inclui
Shift+Tab, percurso inteiro, widgets/modais, fluxos de formulário ou os demais
purposes. Não é aceite WCAG 2.2 AA.

### Repetição ampliada — teclado na grade completa

A repetição em nove larguras e duas direções produziu 648 observações nas três
rotas: 621 elementos focáveis com `:focus-visible` e outline calculado, mais
27 retornos ao `body` no final do Shift+Tab. As capturas estão fora do Git em
`$SUITE_OUT/aculta-ocs-f0-keyboard-full-20261029/`; Marley/320 foi inspecionada.
Amostra de 12 passos não equivale à análise de todos os focáveis nem ao aceite
WCAG 2.2 AA.

## Modos claro/escuro e reduced motion — evidência parcial

O probe `theme-modes.py` capturou Marley, Conta e Perfil em 320/1440 px sob
`data-bs-theme=light`, `data-bs-theme=dark` com `prefers-reduced-motion: reduce`
e modo auto sem atributo (Firefox padrão claro). O browser confirmou dark 6/6
e reduced motion 6/6 na combinação escuro/reduzido; o modo claro/auto confirmou
ambos falsos. Tokens `--aculta-surface-page`/`--aculta-text-primary` foram
`#f2f7f0`/`#0c3c29` no claro e `#171513`/`#f4efe8` no escuro. Scroll width não
excedeu o viewport nas 18 capturas; Marley/320 escuro foi inspecionada. Os
atributos foram aplicados somente no DOM temporário; nenhuma preferência
Drupal foi salva. Isso não aprova contraste WCAG nem os demais componentes/
purposes.

### Atualização: modes na grade completa do viewport

A execução expandida cobriu Marley, Conta e Perfil a 320/360/390/599/600/768/
1024/1280/1440 em modo claro, escuro + reduced motion e automático (81
capturas). Light e auto/default Firefox: `prefers-color-scheme: dark` e
`prefers-reduced-motion: reduce` falsos em 27/27; modo dark/reduced: ambos
verdadeiros em 27/27. Tokens claro/escuro permaneceram `#f2f7f0`/`#0c3c29` e
`#171513`/`#f4efe8`, respectivamente. Nenhum overflow foi medido. Isto não
substitui contraste AA, nem cobre outras rotas/purposes.

# Auditoria Mobile First — ACULTA420 e Studio

**Fase:** MF-0.1 — auditoria responsiva

**Estado:** inventário concluído; inspeção visual de toda a matriz pendente.

**Data da fotografia:** 2026-10-21.

**HEAD auditado:** `36857b12` (`docs/aculta-forum-0.1.0-dev.1`, PR #297).

Esta fotografia separa código committed de declarações de planejamento. A
worktree continha alterações concorrentes em README, sitemap, Forum, User, PDF
Viewer e tokens do Portal; elas não foram alteradas nem usadas como código-base
desta auditoria. Durante a inspeção, commits do outro agente acrescentaram
paridade claro/escuro, Wiki e Cursos ao Studio. O inventário foi atualizado para
o HEAD `36857b12`; o script visual atualmente tem uma alteração não commitada
do outro agente, que não foi modificada nem tratada como evidência.

## Método e limites

- Inventário estático dos CSS, bibliotecas, templates, SDCs, documentação e
  scripts de navegador committed no HEAD acima.
- Leitura do código instalado em `web/core`, no tema Bootstrap contrib, na
  biblioteca local do Bootstrap e nos módulos UI Patterns/UI Styles.
- Consultas somente de leitura no Runtime de teste `aculta_runtime`, usando o
  purpose `account`. Nenhuma configuração, cache ou conteúdo foi alterado.
- Esta fase não muda CSS/JS funcional e não executa a suíte visual. Resultados
  anteriores de navegador são reportados como evidência existente, não como
  teste executado nesta auditoria.

## Ambiente observado

| Item | Evidência |
| --- | --- |
| Servidor e checkout | `hostname=toca`; branch `docs/aculta-forum-0.1.0-dev.1`; PR #297 aberta para `main`. |
| HEAD local/remoto na última conferência | `36857b12`, igual ao OID da PR no GitHub. |
| Runtime de teste | Drush 13.8.0; Drupal 11.4.10; URI `https://conta.aculta.toca.net.br`; banco conectado `aculta_runtime`; tema padrão `aculta420`. |
| CLI | PHP 8.4.26; o baseline do projeto é PHP 8.5, portanto a versão do CLI não serve como evidência de compatibilidade com 8.5. |
| Studio | `aculta_studio` habilitado, `0.1.0-dev.1`. |
| Apresentação | Bootstrap local 5.3.8; tema contrib `bootstrap` 5.0.2; `ui_patterns` 2.0.23 e `ui_styles` 8.x-1.22 habilitados. SDC é fornecido pelo Core instalado. |

Para a conta de teste consultada (UID 5), `StudioPresenter::visibleTabs()`
retornou `revista`, `forum`, `wiki`, `cursos` e `projetos`. Os níveis finais
visíveis foram: Revista (`publicacoes`: `todos`, `rascunhos`, `em-revisao`,
`publicados`, `arquivados`; `criar`: `nova-publicacao`); Fórum (`discussoes`:
`publicas`, `novas`; `moderacao`: `fila`, `denuncias`); Wiki (`verbetes` por
estado editorial e `revisoes/todas`); Cursos (`criar/novo-curso`, `turmas` com
`curso-1` e `todas`); Projetos (`criar/novo-projeto`). `galerias`, Biblioteca,
Métricas e Eventos não apareceram para essa conta nessa consulta. Isso registra
o resultado de access para uma conta, não a disponibilidade universal dos
subsistemas.

## Arquitetura responsiva existente

| Área | Estado observado | Implicação para as próximas fases |
| --- | --- | --- |
| Foundations | `tokens.css`, `base.css` e `layout.css` fornecem paleta/modos, tipografia, foco, movimento, spacing e containers; `--aculta-space-section` e outros valores fluidos já usam `clamp()`. | Evoluir tokens responsivos existentes antes de criar novos; não mudar a geometria entre modos de cor. |
| Breakpoints | Foram encontradas 47 media queries de largura em 28 CSS do tema. Os limiares incluem `359.98`, `374.98`, `479.98/480`, `575.98/576`, `767.98/768`, `991.98/992`, `1099.98/1100` px e `40rem`. | Há padrões Bootstrap junto de limiares locais. Auditar intenção e consumidor por componente; não normalizar mecanicamente. |
| Espaço do componente | Há 49 usos de `clamp()` em 21 CSS e várias regras `min-width: 0`; nenhuma regra `@container` foi encontrada nos CSS do tema. | Container queries podem ser avaliadas onde o componente é colocado em containers de larguras distintas; não são pré-requisito geral. |
| Shell | A biblioteca global carrega `institution-bar.css`, `header.css`, `navigation.css`, `footer.css` e `layout.css`. A Institution Bar é sticky; os CSS também contêm regras para navegação compacta e larguras menores. | Testar os domínios e estados autenticado/anônimo em retrato, paisagem e zoom; medir a disputa entre barra sticky, menus e conteúdo. |
| Elementos sobrepostos | Foram encontrados seis usos de `position: fixed` ou `sticky`, incluindo barra institucional, diálogos, aviso de consentimento, discussão e interface da conta. | Conferir foco não obscurecido, teclado, altura disponível e convivência de overlays em viewport pequena. |
| Tabelas e Views | Rolagem horizontal localizada está explícita em `webform.css`, `sales-panel.css`, `statistics.css` e no rail SDC. O Studio gera tabelas Core para listagens. | Verificar largura mínima e semântica tabular. A tabela do Studio deve ter rolagem isolada ou uma apresentação adaptada sem duplicar dados/lógica. |
| Formulários e edição | `forms.css`/`webform.css` são globais; `ckeditor5.css` estiliza o conteúdo do editor. | O inventário CSS não mede teclado virtual, toolbar, Media Library, erros ou perda de dados; cobrir isso na MF-0.6. |
| Mídia e documentos | Há CSS próprio para Galeria, PDF, Document Viewer e produto; alguns usam viewport units, dimensões mínimas ou rolagem interna. | Testar mídia real e arquivos longos sem criar pipeline, viewer ou storage paralelo. |
| Components e patterns | SDCs mantêm CSS local e stories; regras reutilizáveis do Portal são mapeadas por `data-portal-ui` e `PortalUiHooks`. | Usar os contratos existentes, documentar stories para variantes responsivas e preservar access/cache/render arrays. |
| CSS/JS carregados | `aculta420/global` carrega a maior parte dos CSS de shell, formulários, Portal, comércio, visualizadores e `studio.css`; também carrega `navigation.js`, `counters.js`, `alerts-dismiss.js` e `address-layout.js`. O tema declara ainda `album-slideshow` globalmente. A navegação AJAX do Studio pertence à biblioteca do módulo e é anexada à página do Studio. | Avaliar carregamento contextual depois da estabilização visual (MF-0.10), sem mover regras de negócio para o tema. |
| Viewport | ACULTA420 não possui `html.html.twig`; herda o template do tema base. O único `<meta name="viewport">` encontrado no tema é do envelope de e-mail. | Confirmar a saída HTML efetiva do shell no Runtime e conservar o viewport do Core/base theme; não adicionar um segundo meta tag no escuro. |

Os números de consultas de mídia, `clamp()` e posicionamento são contagens
estáticas para localizar o trabalho. Não classificam automaticamente uma regra
como defeito. `max-width` continua apropriado para exceções locais, conteúdo
bidimensional e correções de limite.

## Studio — implementação atual

### Estrutura e acesso

- Há três níveis de navegação (área, seção, aba), publicados em quatro formas
  de URL: `/studio`, `/studio/{área}`, `/studio/{área}/{seção}` e
  `/studio/{área}/{seção}/{aba}`. Os links são destinos reais, e a página não
  usa o shell nem o menu de Minha Conta.
- Os plugins existentes no HEAD incluem Revista, Fórum, Wiki, Cursos, Galerias
  e Projetos. Disponibilidade, permissão da área e acesso da rota proprietária
  são avaliados antes de mostrar ações. Um plugin no código não prova que uma
  área esteja disponível a qualquer conta.
- A rota e a listagem utilizam operações dos módulos proprietários/Core; o
  Studio não é dono de conteúdo, workflow ou permissão.

### Layout e navegação visual

- `css/components/studio.css` usa flex horizontal como base, sidebar com
  `14rem`, e muda para coluna em `max-width: 767.98px`. A área principal tem
  `min-width: 0`; abas de área e subabas flexionam e quebram linha.
- Em mobile, a sidebar e a área principal empilham, mas as navegações de área,
  seção e aba ainda podem aparecer juntas. O código não apresenta um seletor de
  área nem uma disclosure móvel nesta fotografia.
- A área será o primeiro refinamento de navegação: o responsável escolheu
  seletor nativo compacto. A interação tem de manter links para URLs reais e
  fallback sem JavaScript; protótipo e validação pertencem à MF-0.4.
- `StudioListingBuilder` produz tabelas Core. A suíte visual atual consulta a
  presença de filas/listagens e overflow, mas não cobre ainda toda a matriz de
  viewports.

### AJAX e comportamento

- `studio-navigation.js` usa `Drupal.Ajax`, behaviors e `once()`, substitui a
  raiz do Studio, atualiza URL com `pushState`, responde a `popstate`, anuncia
  loading/falha por `aria-live` e leva o foco ao título após sucesso. Links
  continuam sendo fallback sem JavaScript; em falha AJAX o navegador abre a URL
  completa.
- Não há controle explícito de sequência/cancelamento de respostas concorrentes
  no arquivo auditado. Também não há confirmação de formulário alterado antes
  de navegar. Clique rápido, resposta fora de ordem, erros e formulários não
  salvos precisam de teste e decisão antes de alterar o comportamento na MF-0.5.

### Cobertura registrada

- O script `tests/browser/aculta-studio/visual.mjs` usa Firefox/geckodriver e
  exercita 1280 × 900 e 390 × 844 CSS px, AJAX, histórico, foco/teclado, fila do
  Fórum, listagem da Revista, ausência de erros JavaScript e geometria light/dark.
- O README raiz committed em `c3a04cf0` registra 20/20 verificações no Firefox
  X11 para essa suíte. Esse resultado não cobre 320, 375, 768, 1024 ou 1440 px,
  WebKit/Chromium ou aparelho real; não foi reexecutado na MF-0.1.
- Não foi feita inspeção visual manual ampla de shell, formulários/CKEditor,
  Messenger, Carrinho, Media Library, Wiki, LMS, galerias, documentos e estados
  anônimo/autenticado em todos os domínios.

## Divergências documentais que precisam de sincronização

- `aculta_studio/README.md` e a linha de Studio em `MAPA-MODULO-TEMA.md` não
  refletem o conjunto atual de plugins/áreas nem as últimas evidências de
  Runtime. O README do módulo lista somente Revista, enquanto o Runtime de teste
  consultado expôs também Fórum, Wiki, Cursos e Projetos para a conta avaliada.
- `STUDIO-MAGAZINE-ROADMAP.md` ainda descreve a criação do Studio como fase
  futura e chama a matriz de áreas de disponibilidade planejada. O código e o
  Runtime já têm implementação parcial. A fase MF-0.1 não reescreveu esse
  roadmap, que mantém a história e a governança da frente Portal; sincronização
  de contrato e estado deve ser feita por seu responsável.
- `components.md` conserva trechos rotulados como “contrato futuro” para
  componentes de discussão que têm implementação e evidência mais recentes.
  Corrigir essa divergência quando a frente de cada componente for consolidada.

## Priorização

| Prioridade | Frente | Evidência e foco |
| --- | --- | --- |
| P0 | MF-0.4 — estrutura visual do Studio | Três níveis simultâneos em telas estreitas; seletor de área preferido pelo responsável; manter URL, access, indicadores ativos e fallback. |
| P0 | MF-0.5 — navegação AJAX | Concorrência, cliques rápidos, retorno/avanço, foco, live region, erros e dados não salvos; validar com rotas e resposta reais. |
| P1 | MF-0.2 — fundamentos | Variação de breakpoints e regras locais; estabelecer critério por conteúdo/container sem duplicar tokens. |
| P1 | MF-0.3 — shell e overlays | Institution Bar sticky, menus/offcanvas, Messenger, Carrinho, alertas, consentimento e diálogos. |
| P1 | MF-0.6/MF-0.7 — autoria e operação | Formulários/CKEditor/Media, tabelas de revisão, fila de moderação e conteúdo real por proprietário. |
| P2 | MF-0.8/MF-0.9 — padrões e domínios | Cards, Forum, Wiki, Cursos, Galeria, leitores de documento e conteúdo público após padrões do Studio. |
| P2 | MF-0.10/MF-0.11/MF-0.12 | Carregamento contextual, acessibilidade, zoom, aparelhos e homologação ampla. |

## Plano de verificação para as próximas fases

- Viewports iniciais: 320, 375, 390, 576, 768, 992, 1024, 1200 e 1440 CSS px;
  320/400% zoom para o critério de reflow; retrato e paisagem em shells e
  ferramentas com overlays.
- Registrar o consumidor, URL/purpose, identidade/permissão, estado de
  publicação, modo de cor, navegador, viewport, overflow e resultado de teclado
  antes de declarar cobertura.
- Distinguir fixture local, navegador X11 no Runtime de teste e dispositivo
  Android/iPhone real. Nenhum aparelho real foi validado nesta fase.
- Executar os gates do tema e módulo–tema antes de RC; não desativar regras ou
  atualizar versões de consumidores apenas por terem sido inspecionados.

## Próximas fases

1. **MF-0.2:** fundamentos responsivos compartilhados alinhados a tokens.
2. **MF-0.3:** shell global multidomínio e overlays.
3. **MF-0.4:** navegação e layout mobile do Studio, incluindo protótipo do
   seletor nativo da área.
4. **MF-0.5:** robustez do AJAX progressivo.

O roadmap do tema contém a sequência completa MF-0.1 a MF-1.0.

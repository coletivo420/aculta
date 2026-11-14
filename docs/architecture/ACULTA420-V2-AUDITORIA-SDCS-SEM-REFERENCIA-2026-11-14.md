# Auditoria dos SDCs sem referência literal — 2026-11-14

## Escopo e método

A matriz `ui-suite-consumidores-iniciais.csv` contém 145 linhas de SDC. O gerador
`scripts/inventory-ui-suite-consumers.php` procura IDs explícitos fora do diretório
do próprio componente e exclui stories/testes, varrendo módulos customizados,
templates/componentes/src do tema e Config Sync. Nesta fotografia, 110 SDCs têm
referências literais e 35 não têm. Ausência literal não prova ausência de uso
editorial/dinâmico e não autoriza exclusão.

A validação corrente do provider UI Patterns, com cache de definições desativado,
descobriu 145 SDCs `aculta420_v2`. Isso confirma a descoberta das definições no
checkout/runtime consultado; não comprova configurações editoriais persistidas nem
substitui a inspeção da página da Library, que aguarda o preflight/cache do Runtime.
Por decisão do responsável, todos os SDCs publicados na UI Patterns Library são
preservados. A coluna “Intenção declarada” transcreve o propósito do manifest e
serve para localizar o contrato a revisar, não para atribuir um consumidor.

## Componentes preservados

| SDC | Grupo | Intenção declarada no manifest | Stories | Estado de uso |
| --- | --- | --- | ---: | --- |
| `account-profile-card` | Conta | Apresenta biografia ou redes sociais preparadas pelo módulo de Conta. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `audio-player` | Elementos básicos | Player leve com controles nativos de áudio acessíveis. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `button-scheme` | Botões | Grade de estados de botão em claro e escuro lado a lado, para a UI Library. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `data-table` | Dados | Tabela semântica com cartões no mobile ou rolagem horizontal. | 2 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `discussion-topic-composer` | Comunidade | Componente visual SDC para criar um tópico de discussão. É o único compositor com Assunto, que aparece antes do Comentário. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `filter-chips` | Elementos básicos | Faixa rolável de filtros com links e estado atual explícito. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `form-field` | Formulários | Apresenta rótulo, controle, instrução e erro de um campo. | 2 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `forum-composer-guidelines` | Comunidade | Diretrizes e dicas para criação de novos tópicos em duas colunas no desktop e uma no mobile, com faixa amarela no topo e details nativo. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `forum-topic-card` | Comunidade | Variante do tópico em formato de cartão com miniatura (imagem ou ícone da categoria), metadados e contadores. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `google-signin-button` | Botões | Botão de entrada com a conta Google, no padrão de marca: fundo branco, borda cinza, logo colorido e o texto Continuar com Google. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `hero-banner` | Páginas e vitrines | Abertura de página com mídia opcional e chamada de ação. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `layout-full` | Layouts | Conteúdo ocupando toda a largura do contêiner, com respiro lateral. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `layout-two-columns` | Layouts | Conteúdo principal e coluna complementar; uma coluna no mobile e duas a partir de 60rem. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `lms-learning-journey` | LMS | Casca visual para a jornada de curso autorizada pelo módulo LMS. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `lms-learning-room` | LMS | Casca responsiva do conteúdo de uma aula e seu formulário Drupal. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `lms-tour` | LMS | Apresentacao acessivel em dialog nativo HTML para o percurso guiado do estudante no LMS. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `map-widget` | Mapas | Container do mapa com Leaflet/OpenStreetMap, marcadores e templates de popup e ícones. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `profile-details` | Conta | Apresenta biografia ou redes sociais preparadas pelo proprietário funcional. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `profile-editor-dialog` | Conta | Editor do perfil privado do Studio em estilo de popup: cabeçalho com faixas tricolores, título, subtítulo e corpo com identidade, biografia e redes. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `public-profile-page` | Conta | Apresentação do perfil público resolvido e autorizado pelo Portal. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-activity-summary` | Estatísticas | Resumo analítico em lista de definição, sem dados pessoais. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-category-ranking` | Estatísticas | Tabela acessível de categorias públicas por valor. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-empty-state` | Estatísticas | Mensagem neutra quando não há coleta ou dados no recorte. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-metric-card` | Estatísticas | Indicador analítico com rótulo, valor, unidade e observação. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-moderation-summary` | Estatísticas | Resumo analítico em lista de definição, sem dados pessoais. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-period-filter` | Estatísticas | Formulário GET funcional sem JavaScript para período e métrica. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-response-health` | Estatísticas | Resumo analítico em lista de definição, sem dados pessoais. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `statistics-trend-chart` | Estatísticas | Série diária acessível com barras e valores textuais. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `studio-account-section-layout` | Conta | Apresenta navegação contextual e conteúdo de uma seção autenticada da Conta. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `studio-connections-page` | Studio | Página com duas caixas independentes para gerenciar conexões existentes e buscar novas conexões. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `studio-course-authoring-page` | Studio | Apresenta o formulário proprietário de criação de curso no espaço do educador. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `studio-listing-table` | Studio | Apresenta tabelas administrativas e de conteúdo montadas pelo Studio. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `studio-section-header` | Studio | Apresenta o título e as ações da seção ativa do Studio. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `support-form` | Formulários | Apresentação do Form API de apoio, sem alterar seus elementos ou fluxo. | 1 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |
| `surface-accent` | Superfícies | Moldura e superfície de destaque com faixas laterais (verde, amarela, vermelha) ou topo contínuo tricolor e neutro institucional, fiel aos modelos visuais do ACULTA420. | 11 | Sem referência literal; manter publicado, uso editorial/runtime a confirmar |

## Próximo passo

Ao liberar a janela de Runtime, verificar quais desses SDCs estão selecionados em
component displays, campos persistidos ou configurações de editor. Registrar cada
seleção com entidade/configuração e acesso; deixar sem uso quando não houver seleção.
Não converter a falta de produtor literal em ação de remoção: esses componentes
continuam publicados conforme a decisão do responsável.

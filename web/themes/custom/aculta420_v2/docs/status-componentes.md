# Status dos componentes (G1)

Status por componente extraído do roadmap em 2026-11-05. O estado geral das fases fica em `roadmap.md`.

## Avatar — G1 implementado e integrado ao menu de conta

Criado o SDC `avatar`, com slot de mídia, fallback neutro nomeado e stories para
os dois estados. O componente não consulta entidade/usuário nem gera iniciais.
Schema/render Drupal passaram e Firefox ESR visível capturou os dois estados em
320, 390, 768, 959, 960 e 1440 px nos modos claro/escuro; zoom 200% em 320 CSS
px também passou sem overflow. Teclado não se aplica ao componente estático.
`account-menu` adota o componente no resumo e no painel de identidade, com
`size: sm` e a mídia entregue pelo Portal; validado no Runtime anônimo (fallback)
e autenticado (imagem), em 1024 px, sem overflow, e as suítes do shell seguem
PASS. `author-card` mantém o slot de autoria atual.

## Author box — G1 coberto por author-card; consumer integrado

O contrato de autoria do `aculta_user` documenta a caixa de autoria como slot
`authors` de `revista-publication`; o SDC compartilhado `author-card` renderiza
identidade, avatar e biografia autorizados. O gate Drupal agora renderiza esse
SDC. Não foi criado outro componente com markup redundante. O template do tema
`templates/aculta-user-author-card.html.twig` integra o componente como consumer
real de apresentação para a renderização de perfil e autoria. Fixture composta em
Firefox passou nos seis viewports, claro/escuro, zoom 200% em 320 CSS px e Tab
com foco visível. Validação visual automatizada de conteúdo publicado/autenticado
segue com outro agente.

## Tabs — G1 implementado e integrado ao menu de tarefas locais

Criado SDC de navegação por links normais, compatível com a lista de links
renderizada pelo Drupal. O item atual usa `aria-current="page"`; a lista rola
horizontalmente no mobile e não depende de JavaScript. Não usa semântica de
troca de painel (`role="tablist"`), pois Studio e suites navegam entre rotas.
O template `templates/navigation/menu-local-tasks.html.twig` adota o SDC
`aculta420_v2:tabs` para abas primárias e secundárias como consumer real,
mantendo títulos acessíveis e a árvore de links nativa do Drupal.
Firefox ESR visível passou nas seis larguras em claro/escuro, zoom 200% em 320
CSS px sem overflow da página e foco visível por Tab.

## Dialog — G1 implementado e integrado aos fluxos de modal/tour

Criado o SDC `dialog` com `<dialog>` nativo, gatilho em forma de link para
fallback sem JavaScript, título associado por `aria-labelledby`, ação de fechar
e slots para conteúdo e ações. A variante `fullscreen` ocupa a tela no mobile e
volta à largura modal a partir de 40rem. O comportamento reutiliza
`utility-dialogs.js`, com foco cíclico por Tab/Shift+Tab; o SDC usa a descoberta
automática de `dialog.css`. Consumer implementado em `components/lms-tour/lms-tour.twig`
com apresentação acessível para o percurso do estudante no LMS. Schema 55/764,
design foundations, render Drupal isolado (40 SDCs), PHP lint/PHPCS, YAML e diff
passaram. Validação visual no Runtime com outro agente.

## Form field — G1 implementado e integrado a form-element

Criado o SDC `form-field` para compor o rótulo, o controle Drupal, a instrução
e a mensagem textual de erro, sem assumir a validação nem o tipo do controle.
O template `templates/form/form-element.html.twig` atua como consumer real,
incorporando `aculta420_v2:form-field` para elementos de formulário Drupal,
preservando classes de tipo, identificadores, prefixos/sufixos, instruções e
erros com marcação acessível. Schema e render isolado Drupal cobrem o estado
inválido e sua mensagem.

## Progress — G1 implementado e integrado a progress-bar

Criado o SDC `progress` baseado no elemento nativo, com rótulo e valor
percentual legíveis quando determinado; `value: null` preserva o estado
indeterminado sem inventar percentual e o CSS deixa preenchimento parcial. O
template `templates/misc/progress-bar.html.twig` integra o componente SDC
`aculta420_v2:progress` como consumer real para barras de progresso do Drupal.
O gate Drupal verifica ambos os estados e o rótulo.

## Data table — G1 implementado e integrado a views-view-table

Criado o SDC `data-table` com variante `cards` responsiva para o layout do
Studio e variante `scroll` padrão conforme ADR-040. A tabela e os render arrays
continuam no módulo consumidor; a variante de cartões exige cabeçalhos/células
com roles e `data-label`. O template `templates/views/views-view-table.html.twig`
atua como consumer real do tema, encapsulando a tabela HTML em
`aculta420_v2:data-table` com atributos de rolagem acessível e `data-label` em
cada célula para suporte mobile.

## Empty state — G1 implementado e integrado a views-view

Criado o SDC `empty-state` com slots para mensagem e ações e variante de
espaçamento compacto. Mantém o contrato neutro consumido pelo Studio e pelas
listagens do Portal: o módulo continua decidindo o estado vazio, preparando
conteúdo localizado e links permitidos, com cache e permissões preservados.
O template `templates/views/views-view.html.twig` integra `aculta420_v2:empty-state`
no bloco `{% elseif empty %}`, exibindo estados vazios de Views com a marcação
padronizada do componente.

## Price — G1 aguardando autorização

O ADR-046 lista `price`, mas as referências lidas não definem props,
apresentação ou consumer semântico. A implementação é uma tarefa ligada a
Commerce/Loja, que o plano atual exclui sem autorização explícita por tarefa.
Não foi criado componente nem assumido formato, moeda, impostos ou desconto.
Retomar após autorização e definição do consumer/contrato; outras fases seguem.

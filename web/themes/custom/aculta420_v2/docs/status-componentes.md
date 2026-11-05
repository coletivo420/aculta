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

## Author box — G1 coberto por author-card; captura no consumer pendente

O contrato de autoria do `aculta_user` documenta a caixa de autoria como slot
`authors` de `revista-publication`; o SDC compartilhado `author-card` renderiza
identidade, avatar e biografia autorizados. O gate Drupal agora renderiza esse
SDC. Não foi criado outro componente com markup redundante. Fixture composta em
Firefox passou nos seis viewports, claro/escuro, zoom 200% em 320 CSS px e Tab
com foco visível. Conteúdo publicado e autenticado do Runtime permanece pendente.

## Tabs — G1 implementado e validado em fixture Drupal

Criado SDC de navegação por links normais, compatível com a lista de links
renderizada pelo Drupal. O item atual usa `aria-current="page"`; a lista rola
horizontalmente no mobile e não depende de JavaScript. Não usa semântica de
troca de painel (`role="tablist"`), pois Studio e suites navegam entre rotas.
Firefox ESR visível passou nas seis larguras em claro/escuro, zoom 200% em 320
CSS px sem overflow da página e foco visível por Tab. Integração com os render
arrays de navegação do Portal fica para os templates do Studio.

## Dialog — G1 implementado; consumer Runtime pendente

Criado o SDC `dialog` com `<dialog>` nativo, gatilho em forma de link para
fallback sem JavaScript, título associado por `aria-labelledby`, ação de fechar
e slots para conteúdo e ações. A variante `fullscreen` ocupa a tela no mobile e
volta à largura modal a partir de 40rem. O comportamento reutiliza
`utility-dialogs.js`, com foco cíclico por Tab/Shift+Tab; o SDC usa a descoberta
automática de `dialog.css`. Schema 55/764, design foundations 2.468, render
Drupal isolado (31 SDCs), PHP lint/PHPCS, YAML e diff passaram. Firefox ESR
visível em fixture sintética passou 32/32 checks: seis larguras (320/390/768/
959/960/1440) nos modos claro/escuro, zoom real 200% a 320 CSS px, foco cíclico,
Escape, botão/backdrop, e fallback sem JavaScript. PNGs e JSON estão fora do
repositório diretamente em `$SUITE_OUT`. A fixture não comprova integração a
rota ou consumer Drupal no Runtime.

## Form field — G1 implementado; integração a formulário real pendente

Criado o SDC `form-field` para compor o rótulo, o controle Drupal, a instrução
e a mensagem textual de erro, sem assumir a validação nem o tipo do controle.
As stories demonstram a associação label/controle e `aria-describedby` para
instrução/erro; o consumidor fornece os IDs correspondentes no markup do
controle. Schema e render isolado Drupal cobrem o estado inválido e sua mensagem.
Firefox visível em fixture sintética passou em seis larguras e dois modos, zoom
200% e foco por Tab. O consumer de formulário Drupal publicado continua pendente.

## Progress — G1 implementado; integração a dados reais pendente

Criado o SDC `progress` baseado no elemento nativo, com rótulo e valor
percentual legíveis quando determinado; `value: null` preserva o estado
indeterminado sem inventar percentual e o CSS deixa preenchimento parcial. O
gate Drupal verifica ambos os estados e o rótulo. Firefox visível passou em 12
estados nas seis larguras e dois modos, zoom 200% e sem overflow. Stories e
catálogo documentam os contratos; consumer real permanece pendente.

## Data table — G1 implementado; consumer do Studio pendente

Criado o SDC `data-table` com variante `cards` responsiva para o layout do
Studio e variante `scroll` padrão conforme ADR-040. A tabela e os render arrays
continuam no módulo consumidor; a variante de cartões exige cabeçalhos/células
com roles e `data-label`. O JS define role de região, nome e foco do wrapper
rolável somente quando há overflow, com observers limpos no detach Drupal. Stories e gates
cobrem cartões e rolagem. Firefox visível passou em seis larguras, dois modos,
sem overflow de página, zoom real 200% a 320 CSS px, Tab com foco visível e
fallback sem JavaScript. O consumer real do Studio permanece pendente.

## Empty state — G1 implementado; consumer Runtime pendente

Criado o SDC `empty-state` com slots para mensagem e ações e variante de
espaçamento compacto. Mantém o contrato neutro consumido pelo Studio: o módulo
continua decidindo o estado vazio, preparando conteúdo localizado e links
permitidos, com cache e permissões preservados. Stories, documentação e gate de
schema estrito/renderização de 35 SDCs e descoberta CSS passaram. Firefox
visível passou em 12 estados, foco por Tab e zoom real 200%. A integração em
página Studio servida com a V2 permanece pendente.

## Price — G1 aguardando autorização

O ADR-046 lista `price`, mas as referências lidas não definem props,
apresentação ou consumer semântico. A implementação é uma tarefa ligada a
Commerce/Loja, que o plano atual exclui sem autorização explícita por tarefa.
Não foi criado componente nem assumido formato, moeda, impostos ou desconto.
Retomar após autorização e definição do consumer/contrato; outras fases seguem.


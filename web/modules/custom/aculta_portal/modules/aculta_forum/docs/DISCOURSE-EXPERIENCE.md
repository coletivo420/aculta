# Referência de experiência: Discourse Ubuntu

Revisado em 2026-10-19 a pedido do responsável. Este documento transforma a
experiência observada em critérios para o ACULTA Forum; não propõe instalar
Discourse nem reproduzir sua implementação interna.

## O que foi observado

Na home pública do Ubuntu Discourse, as categorias têm nome, descrição e
quantidade de tópicos. A página de categorias preserva essa leitura por área.
Dentro da categoria, a listagem dá contexto da categoria/subcategoria, mostra
título, tags, respostas, visualizações e atividade; tópicos fixos de orientação
aparecem antes do fluxo recente. A página consultada usa descrições e tópicos
“Start Here” para orientar a escolha do lugar correto.

O guia público do Discourse organiza a navegação em Latest, categorias, tags,
Top, novos e não lidos; destaca tópicos novos e acompanhados, permite voltar ao
ponto não lido, apresenta autores/participantes na lista, mantém leitura linear,
timeline para saltar pela discussão, resposta contextual, citação por seleção,
rascunhos e preferências de notificação. A documentação do produto também
apresenta painel lateral pessoal, experiência mobile, confiança e moderação
comunitária como capacidades da plataforma.

Referências consultadas:

- [Ubuntu Discourse — início e categorias](https://discourse.ubuntu.com/)
- [Ubuntu Discourse — índice de categorias](https://discourse.ubuntu.com/categories)
- [Ubuntu Discourse — Support and Help](https://discourse.ubuntu.com/c/support-and-help/306)
- [Ubuntu Discourse — diretrizes de convivência](https://discourse.ubuntu.com/guidelines)
- [Discourse Meta — guia de uso para novos participantes](https://meta.discourse.org/t/understanding-discourse-for-new-users/96331)
- [Discourse — recursos do produto](https://www.discourse.org/features)
- [Discourse Meta — nova navegação unificada de novos/não lidos](https://meta.discourse.org/t/introducing-the-unified-new-view-for-the-topic-list/404728)
- [Discourse Meta — vocabulário dos elementos de interface](https://meta.discourse.org/t/understanding-user-interface-elements-in-discourse/103351)

O comportamento visual e editorial do Ubuntu foi inspecionado em 2026-10-19;
contagens de tópicos e atividade do site mudam continuamente. Os guias gerais
do Discourse explicam padrões de uso, mas não significam que toda capacidade
esteja habilitada no Ubuntu Discourse.

## Ferramentas de comunidade e moderação pesquisadas

Pesquisa complementar em 2026-10-19 cobriu documentação de administração e o
código atual do Discourse, além da experiência pública do Ubuntu:

| Ferramenta Discourse | Como funciona no produto/código | Planejamento ACULTA |
| --- | --- | --- |
| Alterar categoria do tópico | Edição do tópico e operação em lote alteram a categoria; o índice continua ordenável pela atividade. | Mover Node entre termos Taxonomy do Growroom, incluindo prévia e lote; preservar o Node e não mover acervo histórico. |
| Selecionar posts e mover | Seleção de posts oferece tópico existente ou novo; split mantém avisos/links de origem e pode preservar ordem cronológica. | Planejar mover Comment/subárvore dentro do mesmo contexto. Split e merge ficam condicionados à decisão sobre URL, origem e recuperação. |
| Mesclar tópicos | Move posts entre tópicos, recalcula estatísticas e fecha/retém a origem conforme a situação. | Não apagar origem automaticamente. Planejar só após preservar aliases, revisões, comentários, flags e recuperação. |
| Fila de revisão | Consolida itens revisáveis, filtra por tipo/estado e prioriza revisão; recursos recentes incluem notas e timeline por item. | Manter filas do Portal por contexto/purpose, com filtro/estado, histórico e futura nota interna se houver storage/ACL adequado. Nunca criar fila global que vaze contexto. |
| Flags/denúncias | Tipos configuráveis; revisão pode ocultar, restaurar, editar ou manter conteúdo e registrar ação. Algumas decisões automatizadas usam sinais de flags e histórico. | Configuração explícita e revisão humana; não importar trust levels, ranking opaco nem ocultação automática sem decisão e análise antiabuso. LMS continua sob Educadores. |
| Ferramentas de tópico | Ações administrativas incluem fixar, fechar/pausar, agendar fechamento, aviso de equipe e selecionar posts. | Adotar apenas estados que existam no modelo Core/Portal, com permissão e auditoria; preservar a imutabilidade do acervo histórico. |

### Implementação observada e tradução para Drupal

No código upstream, endpoints de merge/move consultam autorização do
`guardian` antes da operação. O modelo `Topic` delega a movimentação selecionada
para `PostMover`, em vez de tratar a ação como edição direta do formulário; há
tratamento de erros e atualização de estatísticas. A interface apresenta a
seleção, destino e confirmação e adiciona aviso de movimentação. Esses detalhes
são bons critérios de desenho: validação de acesso no servidor, serviço de
domínio, execução como unidade consistente, confirmação e trilha de auditoria.

ACULTA implementará os equivalentes através de Entity/Access/Form APIs Drupal,
sem copiar Ruby/Rails, storage do Discourse ou nomes de papéis. No Fórum,
categoria é Taxonomy e tópico é Node; resposta é Comment. Por isso, um movimento
de comentário requer verificar `entity_id`, `field_name`, `pid`, `thread`,
permalinks e estatísticas do Comment nos dois Nodes. A operação não entra no
Runtime até uma prova de integridade/rollback. Os detalhes e os casos de teste
estão em [`MOVE-TOOLS.md`](MOVE-TOOLS.md).

## Direção visual ACULTA420

O Growroom420 deve parecer uma comunidade de discussão atual com hierarquia
fácil de ler e muito conteúdo visível, conservando o design system ACULTA420 e
as fronteiras Portal/tema:

1. **Entrada do fórum:** saudação curta, ação primária “Criar tópico” quando
   autorizada, navegação “Recentes”, “Categorias” e filtros de participação;
   introduções e regras da comunidade devem ser links claros, não um banner que
   esconda as conversas.
2. **Categorias:** grade/lista responsiva com nome, descrição, contagem, último
   movimento e acesso à subcategoria. Categoria vazia deve explicar seu
   propósito. Cor/ícone é auxiliar e nunca o único identificador.
3. **Lista de tópicos:** linhas densas mas legíveis; título e categoria em
   primeiro plano; tags secundárias; respostas e atividade alinhadas; avatar do
   autor, participantes recentes e autor da última resposta preparados pelo
   `aculta_user`. Visualizações só aparecem depois de haver métrica confiável.
4. **Página do tópico:** título e categoria persistentes; mensagem inicial e
   respostas em fluxo cronológico; cartão de autoria compartilhado;
   permalink/numeração, citar/responder e estado de moderação em posição
   previsível. Em tela larga, navegação de progresso pode acompanhar a leitura;
   em mobile, ações e progresso ficam acessíveis sem sobrepor conteúdo.
5. **Composição:** formulário Core claro e confortável, foco em título/corpo/
   categoria na criação; resposta associada ao ponto correto; preview com o
   filtro de texto real; botões rotulados e estados de envio/erro anunciados.
6. **Personalização:** abas e filtros usam texto claro e URLs compartilháveis.
   Rascunhos, preferências de leitura e notificações devem sobreviver a erro ou
   navegação conforme armazenamento seguro aprovado. Não introduzir dependência
   de JavaScript para ler, responder ou navegar.

Requisitos para todas as etapas: mobile-first, teclado, foco visível,
contraste nos dois modos de cor, headings semânticos, alvos confortáveis,
redução de movimento e fallback paginado sem JavaScript. O tema faz o desenho
com Bootstrap/SDC existentes; o Portal fornece conteúdo, links, acesso,
cacheability e estados neutros.

## Mapeamento de recursos para o ACULTA

| Experiência de referência | Decisão ACULTA | Base/limite |
| --- | --- | --- |
| Home recente e categorias descritas | Implementar na View pública e componente de categoria | Views + Taxonomy; domínio forum e access fixos. |
| Subcategorias e tags | Subcategoria representa localização; tags representam assunto transversal | Taxonomy atual; grupos e vocabulário de tags só após regra editorial. |
| Contagem de respostas e última atividade | Exibir com cache e access | Comment e serviço agregado; não contar conversa LMS como pública. |
| Latest, Top, New, Unread, sem resposta | Criar navegações equivalentes em etapas | Views e estado por participante; decisões de leitura ficam no Portal. “Top” só após métrica/prazo aprovados. |
| Avatar do autor e participantes | Compor cartões/avatares do `aculta_user` | Lista nunca busca campos privados diretamente. |
| Fluxo linear e timeline | Leitura linear como padrão; navegação para início, primeira resposta não lida e atividade recente | Comment/permalinks; paginar com fallback. Timeline é discreta, acessível e não cobre o botão responder. |
| Responder e citar no contexto | Responder ao tópico ou comentário; citação preserva atribuição e não copia dados inacessíveis | Form/Comment access; citações sanitizadas. |
| Rascunho/auto-save | Pesquisar opções Core/armazenamento antes de implementar; priorizar recuperação local segura | Não criar entidade de resposta paralela nem guardar conteúdo privado sem política. |
| Bookmark/seguir/notificações | Reutilizar Flag e ACULTA Messenger | Respeitar opt-in e validar acesso no momento de entrega. |
| Flags e moderação comunitária | Adotar fila contextual, estados, notas/histórico onde houver contrato adequado e permissões ACULTA | Não adotar trust level, ranking opaco ou automação de ocultação. LMS permanece moderado pelos Educadores. |
| Mover tópico/comentários, split e merge | Ferramentas com prévia, autorização nas duas pontas, integridade e recuperação | Mesma categoria/contexto público; sem cruzar Fórum/Revista/LMS/Wiki; acervo histórico imutável. Planejamento em `MOVE-TOOLS.md`. |
| Notas privadas de moderação e timeline de revisão | Avaliar histórico de ações e notas internas com ACL e retenção | Não exibir notas a autores/público nem guardar conteúdo sensível em logs. |
| Chat em tempo real, AI, tradução automática, SSO/2FA próprios e app nativo | Não são requisitos do fórum novo | Messenger/autenticação existentes continuam donos; sem escopo de replicação por semelhança. |
| Link previews automáticos | Candidato posterior, condicionado a proteção SSRF e orçamento de desempenho | Não fazer fetch de URL arbitrária em renderização. |

## Roadmap de implementação atualizado

Este mapa detalha as fases de `ROADMAP.md`; não antecipa trabalho no LMS, Wiki,
Magazine ou tema em fase anterior. A implementação da experiência visual só
começa quando a contraparte ACULTA420 estiver na fase correspondente e o mapa
Portal–tema for atualizado.

### dev.6 — Growroom420 e categorias

- Índice inicial combina categorias explicadas e tópicos recentes sem esconder
  a conversa atrás de painéis vazios.
- Página de categoria/subcategoria com breadcrumbs, descrição, filhos,
  contagens acessíveis e tópicos ordenáveis por atividade/data.
- Row de tópico com respostas e última atividade; autores/participantes usam
  variantes `author_forum` do `aculta_user`.
- Onboarding: orientações de “onde publicar”, tópico fixo e estado vazio útil.
- Operações editoriais de Taxonomy (reordenar e mover categoria) e mudança de
  categoria de tópicos ficam planejadas em dev.9; não expor controles antes da
  permissão e da prévia de impacto estarem prontas.
- Revisar os fixtures atuais `[TESTE]` e remover apenas por IDs após o ciclo
  de inspeção autorizado.

### dev.7–8 — leitura, conversa e publicação

- Leitura linear cronológica; resposta contextual a tópico/Comment; links
  permanentes; navegação início/fim/primeira não lida.
- Citação contextual e preview sob filtro seguro; teste teclado/mobile e
  formulário com validação ligada ao autor/contexto.
- Respostas de publicações ficam na publicação e reutilizam `author_comment`;
  nunca aparecem em listas públicas do fórum como tópicos independentes.
- Pesquisar persistência de posição de leitura e rascunhos no Core instalado;
  só propor storage adicional depois de medir volume, privacidade e invalidação.

### dev.9–10 — descoberta e personalização

- Navegação Latest/Recentes, Top/Em alta, New/Novos, Unread/Não lidos e sem
  resposta, com nomes claros e comportamento estável para anônimo e membro.
- Criar estado de leitura por usuário, “marcar tudo como lido” e abrir no
  primeiro comentário não lido; state/cache jamais cruza pessoas nem contextos.
- “Em alta” usa janela temporal e sinais transparentes; views não equivalem a
  pessoas. Tópicos privados não afetam contagens, busca, sitemaps ou ranking
  público.
- Os guias atuais do Discourse mostram uma navegação unificada Novos com
  subtabs de tópicos/respostas/todos; fazer protótipo e validação com usuários
  antes de decidir se o ACULTA combina abas ou mantém filtros separados.

### dev.11 — apresentação ACULTA420

- Aplicar direção visual acima ao índice, categoria, topic list, post,
  timeline/progresso, navegação e composer usando SDC/tokens Bootstrap.
- Consolidar presets já aprovados de Advanced Forum sem transformar preset em
  tema paralelo nem replicar literalmente a skin Ubuntu.
- Entregar wide/narrow, light/dark, reduced-motion, loading/empty/error,
  foco/teclado e acessibilidade em navegador antes de marcar PASS.

### dev.12–16 — participação, contexto e integração

- Favoritar/seguir/denunciar por APIs Flag/Messenger; controles aparecem por
  access e decisões de moderação continuam humanas.
- Incluir ferramentas de mover comentário/subárvore, mover para tópico
  existente, split/merge condicionado, fila de revisão filtrável e histórico de
  decisão; observar `MOVE-TOOLS.md` para autorização, atomicidade e isolamento.
- Avaliar notas privadas de moderação associadas a uma revisão somente depois
  de definir ACL, retenção, auditoria e integração com a infraestrutura
  existente.
- LMS ganha navegação/avisos semelhantes, mas isolados por curso/turma/
  atividade e moderados pelos Educadores.
- Wiki e Revista ganham conversa dentro da entidade original, sem novos tópicos
  públicos. Busca, contadores e notificações filtram pelo mesmo contexto.
- Investigar previews, badges e outros reforços comunitários depois de
  segurança, consentimento, manutenção e custo estarem documentados.

### Critérios de aceite da inspiração

- Uma pessoa nova identifica onde publicar e a atividade recente sem tutorial.
- Uma lista permanece compreensível no celular e mostra título, categoria,
  respostas e atualização sem depender só de cor/avatar.
- Leitores chegam ao próximo/unread sem perder localização e podem citar sem
  expor trecho inacessível.
- O mesmo conteúdo não aparece fora de seu contexto; acesso e cache são testados
  para anônimo, usuário confirmado, moderador e membros de Group.
- ACULTA420 entrega a sensação visual escolhida sem assumir consulta de
  entidades, permissões, pesquisa ou regra de negócio.

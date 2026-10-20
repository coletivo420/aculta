# Roadmap — ACULTA Forum

Linha do projeto `aculta_forum`: começou em **0.1.0-dev.1**. Roadmap de escopo
aprovado pelo responsável em 2026-10-19; o estado real de cada entrega fica no
CHANGELOG e nos commits. Dev.1 e dev.2 são documentais/fundacionais; não alteram
Runtime.

## Direção atual de produto: experiência Discourse Ubuntu

O responsável definiu como referência uma experiência parecida com
[discourse.ubuntu.com](https://discourse.ubuntu.com/). O ACULTA adotará os
padrões de leitura e navegação adequados à comunidade, mantendo Drupal nativo,
contextos privados e a identidade visual ACULTA420. A análise detalhada,
referências observadas, limites de escopo e critérios de aceite ficam em
[`DISCOURSE-EXPERIENCE.md`](DISCOURSE-EXPERIENCE.md).

Prioridades para implementação: (1) categorias compreensíveis e atividade
recente; (2) listagem densa e legível, com participantes e filtros; (3) leitura
linear, progresso e resposta contextual; (4) seguir/salvar/denunciar e
notificações; (5) preferências e acabamento mobile/acessível. IA, chat em tempo
real, aplicativo nativo e previews remotos não são requisitos deste roadmap.

As ferramentas de comunidade do Discourse foram pesquisadas em produto e no
código upstream em 2026-10-19. O roadmap incorpora padrões de organização de
tópicos, seleção/movimento de posts, merge/split, avisos de ação, fila de
revisão, flags configuráveis, notas e histórico de moderação. Isso é referência
de produto, não autorização para copiar sua implementação nem seus trust levels;
o contrato ACULTA e seus quatro contextos prevalecem. Ver
[`DISCOURSE-EXPERIENCE.md`](DISCOURSE-EXPERIENCE.md) e
[`MOVE-TOOLS.md`](MOVE-TOOLS.md).

| Versão | Entrega | Estado |
| --- | --- | --- |
| `0.1.0-dev.33` | Mover comentário e subárvore entre tópicos do Fórum | Serviço/Form/ação administrativa, permissão específica, locks por tópico compartilhados com gravação/exclusão normal, revalidação de pai/host, atualização de thread/estatísticas e transação/rollback. Kernel MariaDB atualizado: 12/246 PASS; inclui Flag, Search API, referência/file_usage de anexo e submissão Form API confirmada/rejeitada. Forum Unit 51/360 PASS; Messenger e Game têm testes direcionados contra eventos duplicados. Runtime `toca`: Drupal 11.4.10, rota/permissão/serviço resolvidos, configuração ausente (fechada); GET com Comment inexistente respondeu 404. Em limpeza separada dos fixtures, 8 tópicos `[TESTE]` foram removidos; 0 tópicos/Comments novos permanecem e 12 termos da árvore inicial foram preservados. O Forum antigo mantém 30 Nodes/12 vínculos editoriais. Não há Comment publicado de `aculta_forum_topic` para testar a rota com entidade real. RUNTIME STATUS: PARTIAL. Restam rota com entidade existente/CSRF, teste concorrente entre requisições, History, política de anexos `aculta_image`, evento/fila Messenger, permalink e browser. Evidências em `CHANGELOG.md` e `TEST-MATRIX.md`. |
| `0.1.0-dev.32` | Contagem, atividade e identidade pública do último comentarista na View de tópicos | Campo Core `last_comment_uid` oculto; hook injeta cartão `author_forum` pelo `aculta_user`, sem usar nome de login. Teste de equivalência entre install/sync adicionado; execução e Runtime/browser pendentes. Contagem publicada tem Kernel PASS; ordenação positiva e invalidação após moderação seguem pendentes |
| Não marcada | Contagem e atividade na View de tópicos | Kernel MariaDB confirma `comment_count = 1` com resposta publicada, exclui pendente e preserva o agregado após mover categoria; Runtime tem 0 respostas, então ordenação positiva, invalidação após moderação e inspeção visual seguem pendentes |
| Não marcada | Contagem de respostas em tópicos próprios | Implementada na aba privada Fórum de Minha Conta: cada tópico da pessoa usa `comment_count` de Comment Core; validação direcionada pendente nesta subfase |
| `0.1.0-dev.31` | Seguir e salvar tópicos | Flags existentes `seguir_topico`/`salvar_topico` aceitam o bundle ACULTA; links e serviços de Flag confirmados no Runtime (HTTP/browser e alteração de estado pela interface pendentes) |
| Messenger `0.1.1-dev.54` | Avisos de tópicos seguidos | Respostas/aprovações e cada revisão publicada usam fila existente; Messenger revalida conta, Domain e acesso atual de tópico/comentário antes de criar ou mostrar o aviso, contar ou enviar resumo. Bundle antigo e autoria histórica excluídos. Unit 41/145 inclui testes de domínio correto/incorreto e Node access permitido/negado; Runtime read-only PASS; browser autenticado/entrega de aviso pendentes (RUNTIME PARTIAL) |
| Não marcada | Hierarquia no índice público de categorias | Configuração e Runtime PASS: a View apresenta os 12 termos e associa corretamente as 8 subcategorias aos pais; inspeção visual continua pendente |
| Não marcada | Lista personalizada de discussões não lidas | View `/discussoes/nao-lidas` usa History contrib com acesso `authenticated`, filtros públicos fixos e sem cache compartilhado; campo `node_new_comments` mostra contagem positiva e liga à primeira resposta nova. Runtime query como uid 5 retornou 7 resultados e anônimo recebeu 403; HTTP autenticado/browser pendente |
| Não marcada | Marcar todas as discussões públicas acessíveis como lidas | Ação confirmada `/discussoes/marcar-tudo-como-lido`, em Core Batch de 200 itens e History por usuário; Unit/Kernel e isolamento de domínio aprovados. Runtime smoke confirmou rota/form/access sem gravar histórico; teste funcional com usuário de QA efêmero e Browser continuam pendentes. |
| Não marcada | Descoberta: novas, sem resposta e mais comentadas | Displays de Views e abas implementados; Runtime validou HTTP 200 e fixtures antes da limpeza; depois da remoção dos oito tópicos `[TESTE]`, as três rotas retornam lista vazia (HTTP 200). Kernel comprova contagem de resposta publicada e exclusão de pendente; ordenação positiva da View com dados permanece pendente |
| Não marcada | Mover tópico individual entre categorias públicas | Formulário, confirmação, nova revisão no mesmo Node, permissão específica e bloqueio de autoria histórica implementados. Unit e Kernel PASS; Functional/Runtime pendentes. Movimento de comentários implementado no código em dev.33, com portas de validação descritas em `MOVE-TOOLS.md`. |
| `0.1.0-dev.28` | Índice público inicial de categorias em `/categorias` | Runtime PASS para View, rotas, navegação HTTP e relação pai/filho; inspeção visual pendente |
| `0.1.0-dev.27` | Abas de comentários do Fórum por estado, marcador seguro de rejeição e validação Fórum/Revista | Runtime PARTIAL: separação de View/render confirmada, registros temporários removidos; browser autenticado pendente |
| `0.1.0-dev.26` | Fórum como aba em Conteúdo; “Comentários e moderação” como subaba; discussões contextuais fora das filas genéricas | Runtime de teste: abas carregadas e filtro exercitado; HTTP autenticado pendente |
| `0.1.0-dev.25` | Fórum em seção independente no menu Content e correção da View que causava HTTP 500 | Runtime PARTIAL: menu, access e renderização de ambas as Views confirmados; HTTP autenticado pendente |
| `0.1.0-dev.24` | Links do Fórum no mesmo menu Content usado por Galerias | Implementado com dev.25 |
| `0.1.0-dev.23` | Links de menu explícitos para Fórum, comentários do Fórum e Revista | Implementado; Runtime PARTIAL, árvore e acesso uid 5 confirmados; HTTP autenticado pendente |
| `0.1.0-dev.22` | Separação administrativa do Fórum e contratos por contexto; Revista com conteúdo e comentários reunidos em `/painel-administrativo/conteudo/revista` | Implementado; Runtime PARTIAL, sem sessão editorial de navegador |

## Política estrutural do painel administrativo

O conteúdo de discussão não se mistura à listagem genérica em
`/painel-administrativo/conteudo`. Fórum é uma aba própria nesse painel; seus
tópicos não entram na View genérica. As três filas do Fórum ficam como abas
secundárias diretas da aba Fórum, porque o Core limita tarefas locais a dois
níveis; a consulta agregada de comentários mantém seu caminho próprio. Revista reúne publicações e comentários em
`/painel-administrativo/conteudo/revista`; LMS terá área em
`/painel-administrativo/lms/forum` restrita a curso/turma/atividade e autorização
de Educador; Wiki terá área em `/painel-administrativo/wiki/forum` para
comentários de verbetes. LMS e Wiki só ativam suas rotas junto dos respectivos
adapters e depois de provar isolamento por contexto. Ver [contratos de contexto](CONTEXTS.md).

## `0.1.0-dev.1` — Auditoria e fundação documental (entregue)

- Estado GitHub, branches e PRs; base `main` verificada.
- Inventário real de Forum contrib, Composer/config, consumidores, cartões,
  componentes, integrações e riscos de remoção.
- Pesquisa upstream de Drupal 11+ e matriz de capacidades do Advanced Forum.
- Contratos de quatro contextos, ownership, privacidade/cache e migration gates.
- Documentação de `aculta_forum`, `aculta_user`, Portal e ACULTA420 sincronizada.
- Nenhuma alteração de banco, Runtime, conteúdo, dependência, entidade ou rota.

## `0.1.0-dev.2` — Fundação de cartões (`aculta_user`) (entregue)

- Implementados `AuthorIdentityResolver` e `AuthorIdentity`, compondo perfil e link público já existentes.
- Variantes User compartilham payload mínimo; autoria histórica fica separada e não é convertida em conta.
- Testes Unit direcionados para payload neutro, cache e autoria legada.
- Sem renderização, CSS, alterações de tema ou mudanças no Runtime.

## `0.1.0-dev.3` — Author Pane moderno (`aculta_user` + ACULTA420) (código implementado)

- Implementar variantes `author_forum`, `author_comment`, `author_forum_stacked`,
  `author_growroom_historico`; preparar `author_lms` e `author_wiki`.
- `AuthorIdentityResolver` aplica a `PublicProfilePolicy`, resolve avatar pelo
  `aculta_image` e mantém cache; perfil público exige opt-in da foto.
- `AuthorCardBuilder` retorna tema hook neutro; ações continuam sendo render
  arrays fornecidos por consumidores que já resolveram acesso.
- ACULTA420 implementa o SDC experimental `content/author-card`, inline e
  empilhado, com fallback por inicial, foco visível e tokens semânticos.
- Não portados: data de entrada, presença online, estatísticas, badges, assinatura
  e ações sociais; a política existente os exclui ou exige contexto/cache ainda
  não implementados. Integração do author card no Fórum contrib é para dev4+.
- Unit e gates estáticos direcionados em execução; browser/Runtime não validados,
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.4` — Motor executável (fundação)

- Módulo `aculta_forum`, Context API, resolver extensível e provider do fórum público.
- Configuração de instalação de Node/Comment/Taxonomy para tópicos independentes.
- Testes unitários de resolução, suporte e ambiguidade de providers.
- Views, formulários, permissões contextuais completas, instalação/runtime e prova Kernel multi-contexto seguem para fases posteriores; nenhum update/import é executado aqui.
- Runtime de teste habilitado com `--uri=https://growroom420.aculta.toca.net.br`; serviço/configuração conferidos, nenhum Node de teste criado. PHP CLI 8.4.26 limita a validação da baseline 8.5; PHP-FPM/web ainda pendente.

## `0.1.0-dev.5` — Retirada do Forum contrib

- **Em andamento: auditoria do Runtime de teste concluída; remoção não executada.**
- Provider editorial iniciado em `aculta_forum`; Unit access/cache PASS e prova
  Kernel confirma storage Comment compartilhado entre `article`, `blog` e tópico.
- `article` e `blog` agora têm Config Sync/configuração de instalação para
  `field_aculta_discussion`; o formatter Core está preparado e `author_comment`
  usa o builder compartilhado do `aculta_user`. Kernels confirmam o campo
  compartilhado e a política pública. A configuração das duas instâncias e dos
  displays full foi aplicada parcialmente no Runtime; status default aberto
  verificado em memória, sem persistir Nodes ou Comments.
- Foram observados 30 nodes `forum`, dos quais 12 publicados vinculam
  discussões a publicações. `aculta_magazine` e `aculta_user` ainda têm
  consumidores ativos; ver `docs/MIGRATION-REMOVAL.md`.
- Em Magazine 0.1.0-dev.10/.11, hooks de insert/update/delete e a API
  `ForumTopicSync` foram removidos. `LegacyPublicationTopicLookup` somente lê
  vínculos históricos para apresentação/conversão. `DiscussionHooks` omite o
  trecho antigo quando o campo existe.
  O Forum contrib ainda não foi desinstalado e referências legadas permanecem.
  Os tópicos-casca antigos serão apagados depois; não
  importar tópicos nem comentários antigos para os novos contextos.
- Inventário imediatamente pré-operação e restore point validado ainda pendentes.
- Troca de tipo da publicação re-hospeda Comments compartilhados antes de remover o Node antigo; transação e runtime ainda não foram exercitados nesta entrega.
- Exclusão escopada aprovada em ambiente de teste, uninstall oficial,
  remoção do Composer posterior e verificação de resíduos.
- Produção não é tocada por execução automática.

## `0.1.0-dev.6` — Growroom420 funcional

Categorias/subcategorias, tópicos, respostas, permissões, Views/navegação e
Author Cards do `aculta_user` para discussões públicas independentes. O modelo
visual detalhado está em `DISCOURSE-EXPERIENCE.md`.

Preparação parcial: formulário e displays Core de `aculta_forum_topic` foram
adicionados em dev.10 e aplicados ao Runtime de teste. A Page display serve a
raiz em dev.12; `/criar-topico` usa o Entity Form Core com Entity Create Access
em dev.13. O estado sem conteúdo descrito nos relatórios `.187` e anteriores é
histórico; veja abaixo a atualização `.189`. A fase dev.6 continua aberta para
UX visual, respostas reais e revisão das ferramentas de comunidade. A retirada
contrib dev.5 também permanece aberta por depender de consumidores migrados e
restore point válido.

Dev.14 acrescentou a ação “Criar tópico” à listagem pública apenas quando a rota
Core autoriza a conta atual, com cacheability de permissões e participação. A
ação ainda aguarda apresentação completa no ACULTA420 e validação visual;
nenhuma submissão persistida ocorreu. Testes Unit e gates estáticos direcionados
passaram; Runtime confirmou raiz 200, ação ausente para anônimo e rota de criação
403. Usuário autorizado e navegador permanecem sem homologação.

Histórico até `.187`: Dev.11 adicionou a View `aculta_forum_public_topics` e o campo de domínio
canônico; dev.12 conectou a Page display à raiz do purpose forum por meio do
caminho interno `/discussoes`. Runtime confirmou raiz 200, rota classificada no
purpose `forum`, lista vazia e 404 direto para `/discussoes` e `/forum`. O bloco
do acervo contrib permanece sem colocação. Criação/resposta persistida, tema e
remoção do legado continuam pendentes.

Histórico até `.187`: Dev.15 adiciona filtro exposto de categoria ao Page display, usando Taxonomy e
Views Core no vocabulário ACULTA. Runtime confirmou o plugin, renderização do
seletor e resposta 200 com query; o vocabulário está vazio, sem termos cadastrados.

`aculta_user 0.4.0-dev.33` integra os cartões `author_forum` e
`author_growroom_historico` nos modos full/teaser de `aculta_forum_topic`. A
integração não altera cartões de comentários, autoria editorial, LMS ou Wiki;
Runtime confirmou a implementação OOP e `author_forum` em Node não salvo em
memória, sem alterar o total de tópicos. Render de tópico persistido e inspeção
visual seguem pendentes. A última checagem HTTP direta da raiz retornou 301 para
`/discussoes`; uma requisição com query de diagnóstico retornou 200. A rota direta
continua restrita. A análise do Core e do Redirect confirmou que os atributos do
Request não sobrevivem ao cache de correspondência de rotas; a correção está em
Portal `0.4.0-dev.184`; após cache rebuild, `/` retornou 200 e `/discussoes`
continuou 404. Nenhum conteúdo novo foi criado.

Portal `0.4.0-dev.185` / Forum `0.1.0-dev.16` adicionam uma mensagem Core
`text_custom` para a listagem vazia. Após importação parcial só da View, HTTP
confirmou a mensagem na raiz, filtro presente e `/discussoes` fechado; zero
tópicos/comentários criados. Inspeção visual segue pendente.

Portal `0.4.0-dev.186` / Forum `0.1.0-dev.17` acrescentam ao papel
`email_confirmed` somente criar, editar e excluir o próprio `aculta_forum_topic`.
O Config Sync preserva as permissões/dependências e peso observados no Runtime;
não concede edição/exclusão de qualquer tópico. No Runtime `toca`, a importação
parcial do papel permitiu à conta de teste acessar a rota e renderizar em memória
o formulário Core (título, corpo e categoria). O formulário não foi enviado e
continuam zero tópicos e comentários. Testes Unit cobrem o contrato da role, não
substituem o teste funcional de submissão; a fase dev.6 permanece aberta.

Portal `0.4.0-dev.187` / Forum `0.1.0-dev.18` fixam no teste que tópico
público usa o Comment compartilhado `aculta_discussion_post`, inicia com
respostas abertas e exibe o formulário Core inline apenas a participantes com
`post comments`; anônimos não comentam. Runtime confirmou os valores da
configuração ativa e a permissão da conta uid 5. Como não há tópico salvo, não
foi possível renderizar o formulário associado a uma entidade persistida nem
enviar resposta. Nenhum dado foi criado; o fluxo funcional segue pendente.

### Categorias e tópicos de homologação

O campo `field_aculta_categories` é obrigatório e usa o vocabulário novo
`aculta_forum_categories`. A hierarquia inicial e seus tópicos `[TESTE]` foram
criados no Runtime de Homelab por solicitação explícita do responsável para
validar navegação e apresentação; os oito Nodes de teste foram removidos em
2026-10-19 depois da homologação, sem comentários. Os 12 termos seguem como
estrutura inicial do novo fórum. Os registros não são Config Sync nem parte do
acervo legado. Inventário e limpeza: [`TEST-FIXTURES.md`](TEST-FIXTURES.md).

Portal `0.4.0-dev.189` / Forum `0.1.0-dev.20` criaram 12 termos (4 categorias e
8 subcategorias) e 8 tópicos `[TESTE]` de uid 5 no Runtime de teste. Depois da
validação, os oito tópicos foram removidos; as categorias foram preservadas. A
correção Pathauto dá aliases portugueses ao bundle novo. A View GET pública agora não
recebe CAPTCHA global, que a fazia abortar durante o render: a raiz mostra os
cinco tópicos da página inicial e filtro `categoria=77` mostra o tópico
esperado; os oito aliases retornam 200. O legado continua com 30 Nodes e 8
termos; 0 comentários foram criados. Unit 11/64, PHPCS e gates Portal/fronteira/
mapa/slugs passam. Inspeção visual no navegador ainda é necessária. Essa
entrega cobre a base da experiência de listagem descrita em
`DISCOURSE-EXPERIENCE.md`; não encerra a dev.6.

Portal `0.4.0-dev.190` / Forum `0.1.0-dev.21` substituem o limite sem paginação
de cinco itens por um pager Core de 20 tópicos, com navegação em português e
quantidade fixa. A listagem pode apresentar as oito fixtures simultaneamente e
continua paginável à medida que o conteúdo crescer. O filtro de categoria e os
filtros de acesso/domínio permanecem inalterados. Unit e HTTP no Runtime de teste
confirmam a View; inspeção visual e submissão de tópico/resposta seguem
pendentes. Dev.6 continua aberta.

## `0.1.0-dev.7` — Comentários universais

Comment compartilhado quando tecnicamente suportado; renderização contextual,
encadeamento, formulários, permalink e moderação por contexto.

## `0.1.0-dev.8` — Revista/publicações

Concluído parcialmente: Comment direto em `article`/`blog` foi instalado no
Runtime de teste e os hooks que criavam, atualizavam ou despublicavam tópicos
contribuídos foram removidos. Preservar autoria editorial e nunca criar tópico
público para publicação. Serviços de leitura/conversão e o módulo legado ficam
até a migração dos consumidores e o corte seguro documentado.

## `0.1.0-dev.9` — Advanced Forum: categorias e navegação

O índice `/categorias` agora expõe a relação Taxonomy Core `Parent` como
coluna de categoria principal, preservando termos de topo e subcategorias numa
única View. Isso explicita a hierarquia sem criar consulta ou storage paralelo.
A confirmação visual e de conteúdo com termos filhos no Runtime continua
pendente.

O formulário individual para mover tópico está implementado; testes Unit e
Kernel confirmam a preservação de referências. Ainda aguarda Functional,
validação autenticada no Runtime e revisão de URL/histórico. Movimento em lote
e reorganização Taxonomy seguem planejados.

Categoria com descrição, subcategoria, índice geral, breadcrumbs, tópicos
fixados, contagem cacheável de respostas/última atividade e listagem por
atividade/data. Adotar o padrão observado no Ubuntu Discourse: descrições que
explicam o destino e listas que expõem título, categoria, respostas e atividade.
Planejar também ferramentas administrativas de organização: mover um tópico
entre categoria/subcategoria do Growroom, reorganizar a hierarquia Taxonomy e
selecionar vários tópicos para mudar categoria em lote. Exigir permissão
específica mais Entity Access de origem/destino, confirmação com contagem e
resumo, preservação do Node/revisões/Comments/alias e aviso rastreável. Não
cruzar propósito/domínio nem mover conteúdo do acervo Growroom histórico. Um
tópico-sombra/redirect não é padrão: só será considerado com decisão explícita
de URL e canonical, sem duplicar Nodes.

As linhas de tópico também planejam contadores de respostas/comentários
publicados por tópico e indicação de última atividade. Reutilizar a propriedade
`comment_count` do campo Comment e a tabela/projeção Core
`comment_entity_statistics`; pendentes, rejeitados e comentários sem acesso não
entram no contador público. Invalidação deve cobrir inclusão, aprovação,
despublicação, remoção e movimentos futuros. A listagem não fará COUNT por linha
em cada render.
O Core atualiza esse agregado a partir de Comments publicados e oferece os
campos para Views ([`CommentStatistics`](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentStatistics.php/class/CommentStatistics/11.x),
[`CommentViewsData`](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentViewsData.php/11.x));
validar cache/invalidação no campo compartilhado durante a implementação.

Na aba privada “Fórum” de Minha Conta, a pessoa também vê a quantidade de
respostas publicadas em cada tópico que criou. A fonte é o mesmo agregado Core;
não publicar contagens de outros autores, contar comentários pendentes/rejeitados
ou materializar estatísticas próprias por participante. Cartões públicos de
autoria não recebem totais até existir decisão de métrica/cache e política de
privacidade.

## `0.1.0-dev.10` — Advanced Forum: leitura e estatísticas

Subfase implementada: View `/discussoes/nao-lidas` com plugin de filtro e
indicador `history_user_timestamp` do projeto History, incluindo novas
respostas. O display exige papel autenticado, preserva filtros de status,
bundle, Domain ativo, vocabulário de categoria e SQL rewrite, e desativa cache
compartilhado por usuário. O projeto `drupal/history` foi promovido a requisito
direto do Composer para não desaparecer com `drupal/forum`; segue o alerta de
falta de cobertura da política de avisos de segurança em
`MIGRATION-REMOVAL.md`. Runtime: como uid 5, a consulta encontrou 7 discussões
novas/atualizadas; o acesso anônimo HTTP recebeu 403. Renderização HTTP com
sessão e inspeção visual permanecem pendentes.

A View inclui o campo nativo History `node_new_comments` para mostrar contagens
positivas e ligar à primeira resposta nova, com página e âncora calculadas pelo
Comment Core. O campo existe somente na listagem personalizada com cache
desativado por conta; não foi adicionado às listas públicas. Referência:
[History NodeNewComments](https://api.drupal.org/api/drupal/core%21modules%21history%21src%21Plugin%21views%21field%21NodeNewComments.php/class/NodeNewComments/11.x).

Subfase de descoberta implementada: a View pública também expõe “Novas” por
data de criação, “Sem resposta” pelo `comment_count = 0` do agregado Core e
“Mais comentadas” por contagem de respostas publicadas com desempate por
atividade. As três listagens preservam o bundle, publication status, Domain
ativo, filtro de categoria e SQL rewrite da View base. Abas locais ligam os
displays à página principal. Na primeira homologação, as três rotas responderam
HTTP 200 e cada consulta retornou os 8 tópicos de teste; “Sem resposta” retornou
os 8 porque não havia comentários publicados. Os fixtures foram removidos em
2026-10-19. Smoke HTTP posterior confirmou as três rotas com HTTP 200 e lista
vazia; a ordenação com contagem positiva continua sem caso Runtime. Um hook
GET-only existente foi ampliado
para remover CAPTCHA somente das formas de filtro dessas quatro páginas de
leitura; envio de conteúdo permanece fora da exceção.

Views Latest/Top/New/Unread/sem resposta, histórico de leitura por participante,
marcar como lido e primeiro conteúdo não lido, estatísticas e atividade
cacheáveis. Antes de juntar novos/não lidos numa navegação unificada, prototipar
e validar rótulos e filtros com usuários; a própria comunidade Discourse relata
trade-offs nesse modelo. Visualizações só após definir deduplicação, retenção,
bot policy e cache.
Incluir contadores de tópicos e respostas por pessoa somente em contexto próprio
e conforme privacidade; agregados públicos por categoria/tópico respeitam
publicação e Entity Access.

**Compatibilidade da leitura:** no Core 11.4.10 instalado, History Core declara
`lifecycle: deprecated` e será removido no Drupal 12. O projeto contrib
`drupal/history` 1.0.0, compatível com Core `>11.3`, estava instalado no Runtime
somente por dependência transitiva de `drupal/forum`; a dependência direta foi
adicionada ao Composer para manter a função após remover Forum contrib. Os
plugins `history_user_timestamp` e `history_read` oferecem os pontos de
integração usados pela lista personalizada. A versão 1.0.0 não está coberta
pela política de avisos de segurança do Drupal.org, portanto manutenção e
releases precisam ser acompanhadas. Core History foi depreciado no 11.4 e
removido no 12 ([depreciação](https://www.drupal.org/node/3567647), [remoção](https://www.drupal.org/node/3570700)); a recomendação oficial indica instalar a versão contrib para manter a função ([guia](https://www.drupal.org/docs/core-modules-and-themes/deprecated-and-obsolete)).

## `0.1.0-dev.11` — Advanced Forum: apresentação

Implementar lista de categorias, topic rows, cartões de participantes, leitor
linear, timeline/progresso, composer e navegação responsiva no ACULTA420. Usar
presets e layouts lateral/empilhado já previstos; validar light/dark, teclado,
mobile, reduced-motion e fallback sem JavaScript.

## `0.1.0-dev.12` — Participação e moderação

Flags, denúncias, salvar/seguir, citação, aprovação, edição e bloqueio por APIs
existentes e access real. Permissões de participação não serão derivadas de
trust levels/ranking do Discourse. Estudar fila de revisão por tipo/estado com
filtros contextuais, histórico cronológico da decisão e notas de equipe
associadas ao item revisável, sem misturar filas administrativas de Fórum,
Revista, Wiki e LMS. Tipos de denúncia e opções administrativas devem ser
configuráveis, mas limites, prevenção a abuso e revisão humana precisam de
decisão ACULTA antes de automação por contagem de flags.

“Mover comentário” tem uma primeira implementação em dev.33 para mover uma
resposta e sua subárvore dentro do Growroom, preservando estado de moderação;
ela não vai ao Runtime até completar as provas de Search API, History, anexos,
permalink, concorrência e browser. Dividir uma discussão quando o destino for
um tópico novo continua planejado. Merge de tópicos só poderá ser avaliado como fluxo
composto, após demonstrar plano de recuperação/rollback, com confirmação de
todos os comentários afetados e tratamento explícito do tópico de origem. Não
mover comentários entre Fórum, Revista, LMS e Wiki; não alterar estados de
aprovação/rejeição nem contornar moderação. Comentários em contexto LMS
continuam sob decisão dos Educadores com acesso real ao Group. O acervo
Growroom histórico permanece imutável, inclusive para administradores. O
desenho técnico, as decisões pendentes e os testes mínimos ficam em
[`MOVE-TOOLS.md`](MOVE-TOOLS.md).

## `0.1.0-dev.13` — Fóruns internos LMS

Adapter opcional `aculta_forum_lms`; curso/turma/atividade; Group/membership,
cartões educacionais, busca e notificações privadas. Provar isolamento em testes.

## `0.1.0-dev.14` — Contrato Wiki420

Provider futuro, discussão única por verbete, `author_wiki` e navegação interna.
Não reimplementar Wiki nesta fase.

## `0.1.0-dev.15` — Integrações gerais

Messenger, Image, Gallery, PDF, Game, eventos, cache e suas políticas de acesso.
Integração inicial do gatilho de primeira resposta publicada com `aculta_game`
foi implementada separadamente em `aculta_game 0.1.0-dev.11`: Game reconhece
Comment publicado de conta no tópico público novo e rejeita bundles do Drupal
Forum contribuído e autoria histórica. A
homologação do evento real no Runtime fica pendente; estatísticas completas e novos critérios
de gamificação continuam nesta fase e na linha própria do Game.

Pesquisa atualizada em 2026-10-19: o Discourse combina badges por marcos com
gamificação configurável por pontos e leaderboards; seus níveis de confiança
também podem conceder poderes de moderação. O ACULTA mantém o uso adequado já
aprovado: reconhecimento por badges e marcos publicados, sem XP, ranking,
streaks ou poderes de moderação derivados de volume. Critérios quantitativos
novos exigiriam defesa contra spam e medição de qualidade; a integração atual
reutiliza apenas a conquista existente da primeira resposta publicada. Fontes:
[badges Discourse](https://meta.discourse.org/t/understanding-and-using-badges/32540),
[gamification oficial do Discourse](https://meta.discourse.org/t/discourse-gamification/225916)
e [trust levels](https://blog.discourse.org/2018/06/understanding-discourse-trust-levels/).

## `0.1.0-dev.16` — Busca e descoberta

Search API, índice por contexto, filtros/Views e descoberta sem vazamento privado.

## `0.1.0-dev.17` — Consolidação

Revisar APIs, ownership, documentação, código morto, matriz Advanced Forum e
variantes; preparar homologação.

## `0.1.0-rc.1` — Homologação integral

Unit, Kernel, Functional, gates Drupal/tema/fronteira, Domain, acesso, cache,
segurança, browser/mobile, light/dark, acessibilidade, LMS, Wiki contratual,
Magazine, notificações, desempenho e install/update/uninstall/rollback.

## `0.1.0-rc.2` — Correções

Corrigir regressões verificadas e repetir cobertura pertinente.

## `0.1.0` — Estável

Release notes, upgrade, operação, rollback e revisão final. Não implantar produção
automaticamente.

## Regras de avanço

- Cada fase encerra em commit próprio na PR já aberta, com commits pequenos, changelog, documentação,
  testes e limitações reais.
- Fase documental não atualiza versões de runtime de módulos/tema.
- Para operações de ambiente, seguir `docs/architecture/environments.md` e
  validar hostname, pwd, Git, Drush URI/banco, efeitos e recuperação.
- O estado local não comprova Runtime; usar PASS/FAIL/PARTIAL/DEFERRED.
- Variantes de autoria são contratos distintos por contexto. A autoria histórica do Growroom usa `author_growroom_historico`; ela nunca passa pelo resolver de contas User.
- O plano antigo F1–F9 e seus resultados permanecem histórico; decisões
  incompatíveis estão marcadas como superadas em `DECISOES.md`.

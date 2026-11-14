# Arquivo histórico: ROADMAP.md (2026-11-05)

Conteúdo movido sem alteração de texto de `web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md` para reduzir a leitura padrão dos agentes. As seções mantêm o título original; a versão vigente do documento é a de origem.

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

- **Concluída no Runtime de teste em 2026-10-20:** os Nodes `forum`, termos
  `forums`, vínculos `field_discussao`, storage/configuração legada e módulo
  contrib foram removidos na ordem oficial; `drupal/forum` não está instalado
  no Composer. Produção não foi alterada.
- Provider editorial iniciado em `aculta_forum`; Unit access/cache PASS e prova
  Kernel confirma storage Comment compartilhado entre `article`, `blog` e tópico.
- `article` e `blog` agora têm Config Sync/configuração de instalação para
  `field_aculta_discussion`; o formatter Core está preparado e `author_comment`
  usa o builder compartilhado do `aculta_user`. Kernels confirmam o campo
  compartilhado e a política pública. A configuração das duas instâncias e dos
  displays full foi aplicada parcialmente no Runtime; status default aberto
  verificado em memória, sem persistir Nodes ou Comments.
- O Runtime de teste tinha 30 Nodes `forum`, 8 termos `forums` e 12 vínculos
  editoriais; F-D33 removeu esses dados sem migrá-los. As 12 categorias ACULTA
  permanecem. Os consumidores ativos de Magazine, `aculta_user`, Messenger,
  Views e tema foram retirados nas entregas posteriores; a auditoria de resíduos
  e as evidências estão em `docs/MIGRATION-REMOVAL.md`.
- Em Magazine 0.1.0-dev.10/.11, hooks de insert/update/delete e a API
  `ForumTopicSync` foram removidos. `LegacyPublicationTopicLookup` somente lê
  vínculos históricos para apresentação/conversão. Em `aculta_user 0.4.0-dev.38`,
  o fallback antigo que exibia trechos de `comment_forum` foi removido após
  confirmar o campo Comment direto nas 12 publicações referenciadas.
  Os tópicos-casca antigos foram apagados em dev.45; não importar conteúdo
  antigo para os novos contextos.
- Inventário pré-operação, backup íntegro e contagens zero concluídos. O
  uninstall oficial e a remoção do pacote Composer também foram concluídos; o
  restore em base isolada e a reconciliação integral do Config Sync permanecem
  pendentes por drift preexistente.
- Troca de tipo da publicação re-hospeda Comments compartilhados antes de remover o Node antigo; transação e runtime ainda não foram exercitados nesta entrega.
- Exclusão escopada, uninstall oficial, remoção posterior do pacote Composer e
  verificação de resíduos concluídos no ambiente de teste; não executar o fluxo
  em produção sem decisão e plano próprios.
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
403. Usuário autorizado e envio persistido permanecem sem homologação. A entrada
visual ganhou categorias e atividade lado a lado/empilhadas em dev.52; o
Runtime continua sem tópicos para validar cartão/linha com conteúdo real.

Histórico até `.187`: Dev.11 adicionou a View `aculta_forum_public_topics` e o
campo de domínio canônico; dev.12 conectou a Page display à raiz do purpose
forum por meio do caminho interno `/discussoes`. As verificações iniciais de
raiz 200 e rotas internas são históricas; dev.52 combina a Page com o bloco de
categorias CTools. Criação/resposta persistida, cartão/listagem com conteúdo e
UX completa continuam pendentes.

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
esperado; os oito aliases retornam 200. Naquele snapshot de 2026-10-19 o legado
tinha 30 Nodes e 8 termos; F-D33 removeu-os em dev.45. Nenhum comentário antigo
existia. Unit 11/64, PHPCS e gates Portal/fronteira/
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

Antes de encerrar esta fase, fazer revisão visual em sessão gráfica X11 com
Firefox visível. Comparar as telas de entrada, categorias, lista, tópico e
composição com o Ubuntu Discourse atual, usando capturas da mesma largura e
densidade de conteúdo. Reproduzir a hierarquia, o ritmo, a densidade e os
padrões de navegação observados dentro da identidade ACULTA420; não reutilizar
marca, logos, ilustrações ou CSS do Discourse. Conferir desktop, 768 px e 390 px,
modo claro/escuro, foco/teclado e ausência de overflow. Guardar capturas de
homologação fora do repositório quando contiverem dados do Runtime e registrar
no relatório as telas, dimensões, divergências e correções.

Revisão visual X11 em 2026-10-20: entrada e categorias em 1024, 768 e 390 px,
comparadas à entrada Ubuntu em 1024 px. Dev.52 implementou categorias junto à
atividade: colunas em 1024 px e lista seguida pelas categorias em 768/390 px.
Claro foi observado nos três tamanhos; escuro em 1024/390 px. Tab confirmou
foco visível no atalho, marca e busca. As capturas privadas e limites estão em
`TEST-MATRIX.md`. Complemento X11: a thread com uma resposta foi observada em
1270 e 500 px e comparada a um tópico Ubuntu Discourse. A autoria lateral e o
painel de mensagem ACULTA permanecem legíveis em 500 px sem overflow. Discourse
acrescenta timeline/progresso de leitura e navegação persistente, já previstos
nesta fase como itens para implementar. A homologação segue parcial: falta
thread extensa em 390 px, fluxo completo de teclado, leitor de tela, listagem
com conteúdo e composer.

## Revisão visual pré-RC — índice público — 2026-10-21

- `forum-index-visual.mjs` foi executado em Firefox ESR/X11, anonimamente e
  somente com navegação/leitura GET, na raiz e em `/categorias` do host de teste
  do purpose `forum`. Resultado: 13/13 verificações por rota, 26/26 no total.
- As páginas apresentaram H1 único, região principal, quatro categorias na
  raiz e os 12 termos em `/categorias`; nenhum tópico de fixture ou bundle do
  Fórum contrib legado apareceu. Viewports CSS 390/768/1024 passaram sem
  overflow no documento, corpo, região principal ou cartões; capturas claro e
  escuro ficaram visualmente legíveis.
- Capturas em modo `0600`, fora do checkout:
  `/home/aculta/private/aculta/visual-tests/forum-rc-dev130-20261021/` e
  `/home/aculta/private/aculta/visual-tests/forum-rc-dev130-categories-20261021/`.
  Nenhum conteúdo ou configuração foi submetido. O banner de consentimento
  inicial permaneceu aberto; a inspeção não aceitou nem recusou consentimento.
- Escopo parcial: a lista estava vazia, então tópico, comentários, composer e
  estados autenticados não foram revistos nesta rodada. Leitor de tela,
  navegação completa por teclado e dispositivo físico seguem sem evidência.

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
cartões educacionais, busca e notificações privadas. A fundação do provider,
permissões Group e moderação contextual foram implementadas nas dev.92–108.
A dev.108 acrescenta rotas administrativas com filas isoladas de Comments por
curso/turma, provadas em Kernel para acesso contextual e estados; suas rotas e
View foram aplicadas no Runtime de teste. A dev.109 acrescenta ações Form API
para transições moderadas por Educador; a coluna foi importada parcialmente e
o cache reconstruído no Runtime de teste. Busca, notificações e associação de
atividades gerais (fora do fluxo H5P) continuam pendentes; não liberar busca
pública para contexto LMS.

Integração com H5P avançou na F15 de `aculta_lms_h5p` (2026-10-21): Educadores
associam um tópico existente do `aculta_forum` ao conteúdo H5P pelo campo
`field_h5p_content`; discussões gerais de atividade continuam usando
`field_lms_activity`. O adapter limita a seleção ao curso/turma autorizado,
revalida Group, matrícula e Entity Access, e apresenta a discussão sob o player.
Kernel cobre isolamento por conteúdo/curso/turma e o Runtime de teste confirmou
visualmente o player, o tópico contextual e a abertura do Node no host `cursos`
(HTTP 200); as fixtures temporárias foram removidas. Isso conclui a associação
H5P–Fórum para este fluxo, sem criar tópico automático nem contexto público.
Busca e notificações privadas LMS continuam pendentes. Evidências completas:
[`aculta_lms_h5p/CHANGELOG.md`](../../aculta_lms_h5p/CHANGELOG.md) e
[`aculta_forum_lms/CHANGELOG.md`](../../aculta_forum_lms/CHANGELOG.md).

### Busca e notificações privadas LMS — escopo mínimo decidido em 2026-10-22

Este é o ROADMAP vigente do adapter `aculta_forum_lms`; o módulo não mantém
`docs/` próprio. Nada desta seção foi implementado: a decisão abaixo apenas
fecha o que falta e o menor recorte aceitável antes de abrir código.

O que falta para busca privada:

- Não existe índice Search API nem datasource para `aculta_lms_topic` ou para
  os Comments `aculta_discussion_post` desse contexto. O índice público de
  `SEARCH.md` exclui LMS de propósito e não deve ser reutilizado.
- Não existe View/rota com argumento de Group. Acesso LMS vem do Group/Entity
  Access por conta, não dos grants de Node que `content_access` indexa e
  filtra; um filtro de grants não representa membership de curso/turma.
- Não há filtro de estado de moderação. Comments pendentes ou rejeitados da
  turma não podem aparecer, e a moderação do primeiro comentário deixa a maior
  parte das respostas de estudante pendente até aprovação do Educador.
- Não há trigger de reindexação quando um Comment é aprovado/rejeitado ou a
  membership muda, nem prova automatizada de que um estudante não encontra
  conteúdo de outro curso/turma.
- Não há entrada de interface, paginação, permalink para o Comment dentro do
  tópico, estado vazio nem cache contexts por conta/Group.

Escopo mínimo aceito para busca (não implementar agora):

- Busca a partir da página do curso/turma, com Group explícito na rota; sem
  entrada global, sem busca entre cursos e sem busca pública.
- Um índice dedicado, restrito a tópicos LMS com relação Group única válida e
  a Comments publicados; resultado só depois de revalidar Entity Access do
  tópico e do Comment para a conta atual, falhando fechado.
- Cache por `user` e pelo Group da rota (`max-age` igual ao do acesso), com
  prova Kernel de não vazamento entre curso/turma antes de qualquer uso no
  Runtime. Reindexação por evento fica fora do primeiro recorte; atraso de
  índice é aceitável desde que o access check em tempo real prevaleça.

O que falta para notificações privadas:

- O Comments LMS não emite evento para `aculta_messenger`; não há assinatura
  por tópico nem destinatários revalidados no momento da entrega.
- Não há regra para não notificar fila de moderação, nem idempotência por
  (Comment, destinatário), nem opt-in/opt-out do estudante.
- Não há prova de que uma notificação de curso/turma nunca chegue a quem
  perdeu membership ou acesso ao tópico depois da assinatura.

Escopo mínimo aceito para notificações (histórico de 2026-10-22; implementar apenas após contrato do Messenger M2 e revalidação com F-D41):

- Reutilizar as assinaturas/Flags autorizadas e o contrato de avisos do
  `aculta_messenger` (não a caixa de conversas de Private Message); nada de fila,
  armazenamento ou transporte paralelo no Fórum. Usar o adapter LMS competente
  para regras pedagógicas e e-mail, evitando duplicar o produtor.
- Avisar somente a primeira resposta publicada de um tópico que a pessoa segue
  e ainda pode ler; nunca avisar Comments pendentes, rejeitados ou a fila de
  moderação.
- Revalidar acesso no envio, ser idempotente por (Comment, destinatário) e
  respeitar opt-in; entrega por e-mail e digest ficam fora deste recorte.

Continuam adiados: busca entre cursos, busca global de “meus cursos”,
relevância ajustada, digest, push e preferências por turma. Qualquer avanço
exige Kernel próprio e nenhuma rota nova no Runtime antes da prova de
isolamento.

## `0.1.0-dev.14` — Contrato Wiki420

Provider futuro, discussão única por verbete, `author_wiki` e navegação interna.
Não reimplementar Wiki nesta fase.

## `0.1.0-dev.15` — Integrações gerais

Messenger, Image, Gallery, PDF, Game, eventos, cache e suas políticas de acesso.

### Matriz de integração futura do ecossistema ACULTA

O Fórum deve compor capacidades existentes dos módulos e do tema, sem criar
armazenamento, políticas de acesso, transporte de mensagens, cartões ou
renderizadores concorrentes. Cada integração continua condicionada aos contratos
do módulo dono e à autorização contextual do tópico/comentário; o ACULTA420
apresenta os dados preparados pelo Portal, sem assumir lógica de domínio.

| Dono da capacidade | Integração planejada no Fórum | Ordem e condição de avanço |
| --- | --- | --- |
| `aculta_image` | Upload contextual, ingestão, política de extensão/tamanho, processamento, uso e limpeza segura das imagens anexadas. | Primeiro: validar o modal próprio, 10 MB padrão configurável para valor superior, extensões administrativas e vínculo de publicação ao comentário visível. |
| `aculta_gallery` + `media_gallery` | Criar/inserir galerias pelo editor do Fórum e reaproveitar o display de slides/PhotoSwipe. Galerias são permitidas em todos os purposes; anexos continuam sujeitos à política contextual do Fórum. | Primeiro, junto com imagens: provar publicação/despublicação sincronizada com comentário e tópico, acesso, remoção de Media/Galleries órfãos após uma hora e compatibilidade com usos editoriais existentes. Files temporários seguem `system.file:temporary_maximum_age`. |
| ACULTA420 / design system | Aplicar modal, cartões e estados de anexo com tokens e componentes existentes; criar componente por domínio apenas quando não houver composição adequada. | Acompanhar cada integração funcional; revisar teclado, leitor de tela, responsividade e temas claro/escuro antes de RC. |
| Office local | Pré-visualização progressiva de DOCX e, se a validação confirmar suporte seguro e útil, planilhas; manter metadados e download como fallback. | Depois de imagem/Gallery. Validar bibliotecas locais, sanitização, tamanho/tempo de processamento e acesso privado; não transferir anexos para serviços externos. |
| `aculta_pdf_viewer` | Cartão com miniatura e metadados, link para abrir o leitor completo em outra janela por padrão, com opção administrativa para mesma janela. O PDF não será embutido no comentário. | Por último: aguardar o módulo ACULTA Document Viewer e integrar seu contrato e API reais; não duplicar reader, thumbnail ou conversão no Fórum. |
| `aculta_user` | Reutilizar identidade, autoria, perfis/cartões, regras de participação e ferramentas de denúncia/moderação já mantidas pelo módulo. | Consolidação: mapear capacidades ainda não ligadas ao Fórum e validar acesso/cache contextual sem alterar comportamentos de Revista, Wiki ou LMS. |
| `aculta_messenger` | Reutilizar notificações e avisos a seguidores, incluindo primeira publicação de `ForumPost`, política de transferência ao mover comentários e idempotência dos eventos. | Messenger dev.75 usa a revisão publicada como watermark para contar aprovações fora da ordem de criação e restringe o aviso à primeira publicação. Persistência, destinatários e fluxo Runtime continuam pendentes. Sem transporte paralelo nem perda do histórico agregado existente. |
| `aculta_statistics` e `aculta_game` | Consumir eventos mínimos de atividade e conquistas já aprovadas, sem expor texto/PII nem transformar volume em ranking ou poder de moderação. | Validar eventos reais e agregados no escopo próprio; critérios novos exigem decisão de produto e defesa contra spam. |
| LMS, Wiki e demais submódulos | Integrar somente por adaptadores/contextos próprios, respeitando Group/membership, bundles e contratos de cada domínio. | Evolução independente; uma integração não amplia permissões globais nem mistura conteúdo entre contextos. |

Os critérios específicos de anexos e referências técnicas estão em
[`PESQUISA.md`](PESQUISA.md). A documentação de Gallery confirma que galerias
podem reunir quaisquer bundles Media configurados; acesso e publicação continuam
sendo contratos locais do Portal e dos módulos ACULTA
([guia oficial do Media Gallery](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/media-gallery/getting-started-with-media-gallery/creating-and-editing-media-galleries)).
Esta matriz é planejamento: não declara implementação, cobertura Runtime ou
estado RC para itens ainda não comprovados.

Integração inicial do gatilho de primeira resposta publicada com `aculta_game`
foi implementada em `aculta_game 0.1.0-dev.11` para Comments ACULTA e ampliada
na dev.16 para a primeira publicação de `ForumPost`. O Game reutiliza o trigger
existente, sem dependência do Fórum; publicação aprovada pode conceder uma vez,
enquanto Forum contrib, sync e autoria histórica são rejeitados. A homologação
integrada permanece pendente; estatísticas completas e novos critérios de
gamificação continuam na linha própria do Game.

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

Fundação pública entregue na dev.86: índice Search API e View `/pesquisar`
isolados ao bundle de tópico, com acesso/status e filtro dinâmico pelo Domain
canônico do Growroom420. A dev.87 acrescenta filtro hierárquico por categoria,
com rótulos dinâmicos; a dev.88 comprova no MariaDB de Kernel que o ID
`target_id` filtra somente o tópico da categoria. A dev.202 inclui posts
revisionáveis com grants do tópico hospedeiro aplicados antes de paginação e
totais. Search API 1.41 ordena por relevância decrescente quando a View não
define sort. A matriz automatizada integrada de grants/publicação/purpose e a
homologação pública com conteúdo real ainda precisam ocorrer antes de reindexar
ou ativar essa configuração no Runtime. LMS, Wiki, Revista e Comments não entram
neste índice público.

## `0.1.0-dev.17` — Consolidação

Revisar APIs, ownership, documentação, código morto, matriz Advanced Forum e
variantes; preparar homologação.

## Plano legado de `0.1.0-rc.1` — homologação integral

Unit, Kernel, Functional, gates Drupal/tema/fronteira, Domain, acesso, cache,
segurança, browser/mobile, light/dark, acessibilidade, LMS, Wiki contratual,
Magazine, notificações, desempenho e install/update/uninstall/rollback.
Incluir rodada visual X11/Firefox visível contra a referência Ubuntu Discourse
e revisão por tamanhos fixos desktop/768/390 px; conferir também interação real,
foco, teclado, mensagens de validação e modo escuro, com screenshots antes/depois
para os desvios corrigidos.

## Estimativa de conclusão (revisão de 2026-10-20)

Estimativa de esforço restante, sujeita aos resultados de homologação e a
dependências de ambiente: dev.5 (uninstall/configuração residual), 1–2 dias
úteis; dev.6–12 (fórum público, navegação, moderação e ferramentas), 5–8 dias;
dev.13–16 (LMS, contrato Wiki, integrações, busca), 3–5 dias; dev.17 e rc.1
(consolidação, testes integrados e inspeção visual X11), 4–6 dias; rc.2 e
preparação estável, 2–4 dias. Total aproximado: 15–25 dias úteis, ou 3–5 semanas
de trabalho contínuo, mais 1–2 semanas de margem se surgirem regressões em
acesso, cache, dados ou UX. A sequência é de esforço e não representa prazo de
deploy; produção depende de decisão própria do responsável.

## Plano legado de `0.1.0-rc.2` — correções

Corrigir regressões verificadas e repetir cobertura pertinente.

## Plano legado de `0.1.0` — estável

Release notes, upgrade, operação, rollback e revisão final. Não implantar produção
automaticamente.

## Auditoria transversal XK 0.1 — 2026-10-22

F-GROUP 0.1: adotar **Group** para fóruns restritos a associados ativos; situação `associado` não é papel Drupal. F-GROUP-A inventariar Group types/grants + fóruns; B adapter membership sincronizada com situação User, revogação/suspensão; C busca, tópicos, comentários, anexos, contagens e notificações sob ACL Group; D dry-run/backfill e backup; R testes dois associados/grupos, ex-associado, cache/Domain. Fórum segue dono de discussão; política de identidade fica no aculta_user. XK-01: eventos de aviso devem ir ao Messenger, sem engine própria.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B / ADR-XK-03: especificar relação entre situação `ativo` do associado em User, membership Group e Entity Access; bloquear leitura imediatamente ao suspender/desligar, sem esperar worker. Inventariar Group type, relação do tópico, caches, anexos, Search API, aviso; migração/backfill só após dry-run/backup. Não confundir Group LMS com Forum associado.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**

## XK 0.1-C1 — caracterização concluída (2026-10-22)

`ParticipationRules`, `ParticipationEditWindow`, `ParticipationEditWindowHooks` e
a isenção LMS estão fixados por testes Unit e Kernel no `aculta_user`, sem
mudança de comportamento, DI ou configuração. As células de ForumPost
publicado/revisionado continuam cobertas pelo Kernel
`ForumPostWorkflowKernelTest` deste módulo. `PublishedParticipationLookupInterface`
segue documentada, não implementada. [Evidência](../../aculta_user/docs/USER-PART-0.1-C1-CHARACTERIZATION.md).


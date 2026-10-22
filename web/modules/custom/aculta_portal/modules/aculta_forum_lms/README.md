# ACULTA Forum LMS

Adapter opcional entre `aculta_forum`, `aculta_lms` e Group. Registra provider
privado para Nodes `aculta_lms_topic` e delega a
autorização do marcador de moderação aos Educadores com `grade students` no
curso contextual.

Na dev.27, o adapter também permite associar diretamente um tópico a um
`h5p_content` e renderiza as conversas autorizadas sob o player H5P. A
integração é unidirecional: `aculta_forum_lms` depende do H5P ACULTA, cujo
formatter oferece o hook sem depender do Fórum. O campo é interno e oculto na
apresentação pública do tópico; o autocomplete lista conteúdos editáveis do curso contextual e falha fechado
sem Group. Unit: 25 testes/291 assertions; Kernel da suíte existente: 3 testes/168 assertions. Kernel específico do vínculo, smoke e revisão visual no Runtime continuam pendentes.

Na dev.26, um Kernel MariaDB exercita o resolver com uma atividade ligada a uma
aula e um curso reais: o estudante vê somente o tópico relacionado à atividade
no curso atual e permitido pelo Group. A cobertura total do adapter é Unit
22/256 e Kernel 3/168; o smoke autenticado e a revisão visual no Runtime ainda
estão pendentes.

Desde a dev.17, Educadores com `grade students` no curso contextual têm um
painel com filas separadas de Comments publicados, rejeitados e pendentes por
curso/turma. O Core `approve` fica permitido apenas no contexto LMS autorizado;
`update`, `delete` e `administer comments` não são concedidos.

As filas agora mostram operações contextuais `Aprovar` e `Rejeitar` apenas para
transições válidas. Cada operação abre confirmação Form API, revalida o Group,
o tópico e o papel Educador e atualiza status e marcador de rejeição juntos.

Na dev.23, os caminhos das filas e das operações incluem o curso antes da turma:
`/painel-administrativo/lms/curso/{course}/forum/{group}/...`. O índice organiza primeiro os cursos e, dentro de cada curso, apresenta o fórum do próprio curso e os das turmas. A autorização
confere se o Group da discussão é o curso informado ou uma turma vinculada a
ele. O cache de rotas e menu foi reconstruído no Runtime de teste; os novos caminhos e o índice course-first foram confirmados. O smoke autenticado segue pendente.

## Estado

Na dev.14 o adapter está listado na Config Sync junto a `gnode`. No Runtime de
teste (`aculta_runtime`, URI `https://growroom420.aculta.toca.net.br`), as oito
configurações padrão e as duas relações Group foram instaladas com sucesso. A
correção adiciona `#[Autowire(service: 'aculta_forum.discussion_context_resolver')]`
ao hook OOP, resolvendo falha de compilação do container. Unit: 14 testes / 147
assertions; Kernel: 2 testes / 74 assertions, com 38 depreciações de
Core/contrib/LMS. Smoke HTTP da raiz e de `/categorias`: 200. Não há Nodes de
tópico público, LMS ou legado nem Comments `aculta_discussion_post` no Runtime.

As quatro permissões Group dos papéis de curso/turma e o mapeamento de herança
`grade students` foram aplicados no Runtime de teste por importação parcial
revisada, limitada a cinco itens (`group.role.lms_course-teacher`,
`group.role.lms_course-member`, `group.role.lms_course-course_creator`,
`group.role.lms_class-member` e `lms_classes.settings`). Todos os demais campos
dos cinco objetos coincidiam com a Config Sync antes da operação. As
permissões ativas agora coincidem com os arquivos versionados.

A ativação foi somente no Runtime de teste. A prévia de sincronização integral
continua exibindo diferenças alheias ao Fórum; não executar importação global.
Como não há tópicos de teste, não foi feita autorização de conteúdo real no
Runtime. Kernel prova acesso de membro/Educador, outsider negado e herança de
Educador sem membership direto na turma. As rotas e a View das filas foram aplicadas
no Runtime; na dev.23 as rotas agora incluem o curso. A dev.17 acrescentou a coluna Core `entity_operations` por
importação parcial de um único View e cache rebuild. `/painel-administrativo/lms/forum`
continua 403 para anônimo e rotas de fila com Group inválido retornam 404. Falta smoke com
Educador autenticado e Comment de teste. Search API e notificações LMS aguardam
isolamento/homologação. Não copiar configuração para produção.

Em 2026-10-20, `drush config:import --diff` mostrou um diff completo com
remoções e mudanças alheias ao Fórum (entre elas configurações de blocos,
Commerce, perfis, hierarquia de papéis e recursos LMS). O comando terminou com
exit 1 sem aplicar a importação; `drush pml` confirmou que adapter e `gnode`
continuavam habilitados. A importação global não é um procedimento de
reconciliação seguro para este Runtime. A importação parcial subsequente aplicou
somente os cinco itens listados acima, sem apagar configuração ou conteúdo de
outros sistemas.

Historicamente, a dev.94 adicionou install config do bundle `aculta_lms_topic`, Comment
compartilhado e relações `group_node:aculta_lms_topic` para curso/turma. A
dev.97 acrescenta em Config Sync a herança `grade students` do curso para a
permission Group Node dos tópicos de turma, validada pelo `ClassPermissionCalculator`
real. Essas permissões e a herança foram aplicadas por importação parcial.
Na dev.17 foi instalada como config padrão do adapter uma View Embed com três
displays fixos, sem página nem bloco; os caminhos de rota recebem Group explícito.
`gnode` está listado como dependência do adapter. Um hook
`entity_access` nega acesso a Nodes do bundle quando falta relação Group única;
para `lms_class`, também exige uma relação única com um curso. Com relações
válidas, o hook retorna neutro e deixa o access handler do Group aplicar os
papéis.

Discussões de turma exigem exatamente uma relação `lms_classes` para o curso
pai. Acesso a
leitura combina Entity Access do Node, acesso ao Group e membership real na
turma/curso ou permissão contextual de Educador. Ausência, duplicidade ou tipo
inesperado de relação falha fechado.

Na dev.24, tópicos podem ter referência opcional `field_lms_activity` para uma
atividade já existente do LMS. O formulário de resposta mostra links para os
tópicos ligados àquela atividade no curso atual; o tópico pode pertencer ao
Group do curso ou a uma turma desse curso. O resolver confirma que a atividade
continua publicada e relacionada ao curso, exige relação Group única e valida
Entity Access do Node para a conta atual. Isso permite uma discussão de curso
compartilhada ou discussões por turma; em ambos os casos cada estudante só vê
os tópicos autorizados pelo Group. O campo não é exibido no tópico público. O
adaptador não cria tópicos nem Comments automaticamente: educadores criam e
associam os tópicos pela administração de conteúdo do Group. Smoke autenticado
e revisão visual ainda não foram realizados.

## Ownership

- Nodes e Comments continuam no Core.
- `aculta_forum_lms` resolve contexto e autorização; não duplica matrícula,
  respostas, cartões, busca, notificações ou mídia.
- Cartões educacionais continuam pertencendo ao `aculta_user`.
- Imagens digitadas nas respostas Comment usam o formato compartilhado
  `usuario_aculta`, cujo editor e filtro são fornecidos por `aculta_image`.
  Não existe anexo separado de arquivo neste adapter.
- `aculta_messenger`, Search API e tema não são alterados aqui.

## Acesso e testes

Os Kernels `LmsGroupNodeAccessKernelTest` e
`LmsClassEducatorPermissionKernelTest` usam `aculta_simpletest`: provam acesso
de membro e Educador contextual, bloqueio de outsider (inclusive com papel
global `lms_teacher`) e herança real de `grade students` para o Group Node da
turma. Unit cobre escrita, status fechado e moderação do comentário. A
combinação de acesso trata membership e permissão contextual como alternativas
explícitas e nega somente se ambas falharem; `orIf()` não pode ser usado porque
`forbidden` prevalece sobre uma alternativa permitida. O Kernel também persiste
Comment publicado, exerce o hook do marcador `field_aculta_rejected`, prova
Educador versus estudante e recusa escrita quando o campo fecha. A dev.99
adiciona a permission de leitura aos Group roles reais do Config Sync e prova
os quatro YAMLs em Kernel. A dev.100 também verifica os contextos de cache por
usuário/permissões e a dependência cacheável do tópico para as três decisões
de acesso. A dev.101 verifica a coerência entre Config Sync e as relationship
types instaláveis. A dev.102 instala a configuração padrão via `ConfigInstaller`
na fixture LMS/Group. A dev.16 cobre a View Embed em Kernel: o Educador só vê
Comments de seu Group e cada aba retorna o status fixo esperado. A dev.16
aplicou a View por importação parcial e refez o cache no Runtime de teste. A
raiz respondeu 200, o overview anônimo 403 e as rotas com Group inexistente
404. Autenticação como Educador, renderização com linhas e ações de
aprovação/rejeição ainda não foram validadas.

Na dev.16, Unit passou com 18 testes / 184 assertions e Kernel com 2 testes /
102 assertions; o Kernel reportou 40 depreciações de Core/contrib/LMS. O adapter,
as permissões Group, as rotas e a View estão ativos no Runtime de teste. Sem uma
conta Educador e conteúdo LMS apropriado para smoke autenticado, RUNTIME STATUS:
PARTIAL.

Na dev.12, a fixture separa propriedade de membership: curso e turma pertencem
a uma terceira conta; o Educador é membro com role contextual no curso, não é
membro direto da turma e não é proprietário. O Kernel confirma que ainda pode
ver a turma/tópico e moderar. A inspeção somente leitura do Runtime de teste
com `--uri=https://growroom420.aculta.toca.net.br` encontrou apenas
`gnode` e `aculta_forum_lms` ausentes no fecho estático de dependências; não
ativou módulos nem importou configuração. A API local do Drupal 11.4.10 não
expõe `ModuleInstaller::getModulesToInstall()` descrito pela documentação
upstream consultada; o fecho foi conferido pelos metadados instalados das
extensões e por `core.extension`. Na dev.13 o ciclo de ativação foi executado
no Runtime após revisão do estado limpo; a desinstalação não foi necessária.
A autorização no Runtime e a reconciliação de Config Sync continuam pendentes.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-05: manter moderação contextual LMS e autorização por Group de curso/turma. Não assumir nem reutilizar memberships de fóruns de associados; somente contratos neutros de User/Forum podem ser compartilhados. Avisos passam pelo Messenger via adapter específico.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

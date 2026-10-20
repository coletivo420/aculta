# ACULTA Forum LMS

Adapter opcional entre `aculta_forum`, `aculta_lms` e Group. Registra provider
privado para Nodes `aculta_lms_topic` e delega a
autorização do marcador de moderação aos Educadores com `grade students` no
curso contextual.

Desde a dev.16, Educadores com `grade students` no curso contextual têm um
painel com filas separadas de Comments publicados, rejeitados e pendentes por
curso/turma. O Core `approve` fica permitido apenas no contexto LMS autorizado;
`update`, `delete` e `administer comments` não são concedidos.

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
Educador sem membership direto na turma. Rotas e View das filas estão no código,
mas ainda não foram importadas/rebuildadas nem exercitadas por HTTP no Runtime;
Search API e notificações LMS também aguardam isolamento/homologação. Não copiar
configuração para produção.

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
Na dev.16 foi instalada como config padrão do adapter uma View Embed com três
displays fixos, sem página nem bloco; os caminhos de rota recebem Group explícito.
`gnode` está listado como dependência do adapter. Um hook
`entity_access` nega acesso a Nodes do bundle quando falta relação Group única;
para `lms_class`, também exige uma relação única com um curso. Com relações
válidas, o hook retorna neutro e deixa o access handler do Group aplicar os
papéis.

Discussões de turma exigem exatamente uma relação `lms_classes` para o curso
pai. A associação de aula/atividade ainda não faz parte desta entrega. Acesso a
leitura combina Entity Access do Node, acesso ao Group e membership real na
turma/curso ou permissão contextual de Educador. Ausência, duplicidade ou tipo
inesperado de relação falha fechado.

## Ownership

- Nodes e Comments continuam no Core.
- `aculta_forum_lms` resolve contexto e autorização; não duplica matrícula,
  respostas, cartões, busca, notificações ou mídia.
- Cartões educacionais continuam pertencendo ao `aculta_user`.
- `aculta_messenger`, `aculta_image`, Search API e tema não são alterados aqui.

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

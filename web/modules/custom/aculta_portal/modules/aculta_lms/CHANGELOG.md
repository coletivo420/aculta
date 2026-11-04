# Cobertura Kernel adicional F7 — 2026-11-04

- Kernel Commerce confirma que pedido gratuito em rascunho não matricula; ao
  concluir a transição real do pedido, o hook concede membership e grava a
  origem `field_course_order`, sem gateway remoto.
- Seleções forjadas de turma fechada ou de outro curso falham em validação e
  submit, sem criar carrinho.
- `CourseEnrollmentPurchaseKernelTest`: 4 testes / 173 assertions PASS no
  MariaDB `aculta_simpletest`; 67 depreciações são de Core/contrib.

# 0.1.0-dev.206 — corrige serialização dos formulários do Course Builder

- Serviços injetados nos formulários de mover, ordenar e excluir agora são `protected`, conforme o contrato de `DependencySerializationTrait`; após Form API cache serialization, as dependências são restauradas do container.
- Upgrade Status/PHPStan analisou 225 arquivos: 19 alertas sobre visibilidade e `@covers` foram corrigidos, resultado final sem erros. `AcultaLmsInstallTest` Kernel 9/409 verifica a restauração dos serviços nos quatro formulários do Course Builder.

# 0.1.0-dev.205 — alerta para alterações não salvas na autoria

- Formulários de aula, atividade e ordenação agora detectam alterações e usam o aviso nativo do navegador antes de abandonar a página; submissão limpa o estado de alteração.
- Testes JavaScript cobrem alerta, formulário inalterado/fora do escopo e reanexação do behavior Drupal.

# 0.1.0-dev.204 — remove CSS de navegação legado do formulário de resposta

- Removida `answer-form.css`, que limitava overflow em IDs/classes exclusivos do Olivero e não correspondia ao tema ACULTA ativo. O formulário LMS não carrega mais uma folha sem efeito; Kernel protege esse contrato.
- Corrigidos os estados do roadmap: editor de aula/atividade em Form API com CKEditor e ordenação por arrasto com fallback de teclado estão implementados; permanecem pendentes somente gates de homologação e integrações listadas em cada fase.

# 0.1.0-dev.203 — corrige renderização do formulário de reordenação

- `CourseStructureOrderForm::buildForm()` agora segue a assinatura de `FormInterface` e lê os argumentos extras pelo `FormState`, como prevê o Form API. Isso remove o fatal que impedia renderizar a estrutura do curso.
- Unit valida o carregamento do formulário, o estado inicial da ordem e a autorização contextual de educador.

## 0.1.0-dev.202 — bloqueia inscrição em curso não publicado

- O formulário de compra não oferece turmas nem ação de checkout quando o curso
  está despublicado.
- Validação e submit revalidam a publicação do próprio curso; uma submissão
  forjada não cria carrinho nem item Commerce.
- Unit: 3 testes / 24 assertions PASS. Kernel MariaDB `aculta_simpletest`: 2
  testes / 87 assertions PASS; as 65 depreciações são de Core/contrib.

## 0.1.0-dev.201 — cobre formulário e carrinho de compra de curso

- Kernel Commerce exercita a montagem do formulário com curso, produto e
  variação reais; confirma que somente turma publicada aparece e que preço e
  ação de checkout são apresentados.
- A submissão cria o carrinho `curso` e persiste a variação e a turma selecionada
  no item (`field_course_class`), sem gateway externo.
- MariaDB `aculta_simpletest`: 1 teste / 45 assertions PASS; 65 depreciações
  upstream Core/contrib. Checkout HTTP e pagamento sandbox permanecem pendentes.

## 0.1.0-dev.200 — cobre estados indisponíveis da compra de curso

- Unit do `CourseEnrollmentPurchaseForm` protege os estados sem turma publicada
  e sem produto de curso válido: o formulário não oferece ação de checkout.
- A homologação de compra paga permanece pendente de produto/turma de teste e
  autorização de gateway sandbox.

## 0.1.0-dev.199 — corrige renderização da sala de aula

- As linhas da tabela de atividades entregam conteúdo e link como dados de
  célula do Render API, em vez de serem interpretadas como atributos HTML.
- Kernel renderiza atividade e link de abertura para proteger a página da aula
  contra erro 500.

## 0.1.0-dev.198 — mostra histórico agregado por turma

- O painel de educador lê a série diária `lms.students.by_status` do Statistics
  para a turma autorizada e oferece períodos de 7, 14 ou 30 dias por links GET,
  sem JavaScript. Dias sem snapshot aparecem como zero; a página distingue
  ausência de histórico da integração opcional indisponível.
- A consulta carrega só as seis dimensões da combinação curso/turma e não
  inclui UID, notas, respostas ou dados por estudante. O resumo atual e a lista
  de participantes mantêm suas políticas existentes.
- `aculta_lms_requirements()` recebe `LegacyRequirementsHook`, evitando a
  depreciação do hook procedural no Core 11.3+.
- Unit LMS 264/984 e Kernel 36/1101; Statistics Unit 108/438 e Kernel focado
  16/29. PHPCS focal, PHP lint e `git diff --check` passaram. O Kernel amplo
  reporta 59 depreciações em módulos contrib/Core externos ao LMS.

## 0.1.0-dev.197 — testa reordenação do Course Builder

- Unit cobre atributos e handles das listas; Kernel comprova reordenação de
  aulas/atividades, pesos contíguos, no-op, rejeição de lista incompleta e
  preservação do vínculo de respostas.
- Handles de arraste são decorativos e ficam fora da árvore acessível; links
  funcionais de mover acima/abaixo continuam sendo os comandos de teclado.
- Unit CourseStructureController: 1 teste / 29 assertions PASS. Kernel focal:
  1 teste / 46 assertions PASS, com 13 depreciações upstream.
- Atualiza o contrato visual para marcar arraste implementado e documentar o
  limite de não mover entre módulos, que não existem como entidade LMS.

## 0.1.0-dev.196 — reordena o currículo por arraste acessível

- Aulas e atividades podem ser reordenadas por arraste dentro da sua própria
  lista; os controles “mover acima/abaixo” seguem disponíveis como alternativa
  por teclado e sem JavaScript.
- O servidor confirma novamente a permissão contextual, a relação curso/aula,
  a coleção completa de IDs e grava pesos sob lock e transação.
- Validação executada: PHP lint, Node syntax check, parse Symfony YAML e
  `git diff --check`. Smoke visual e testes Drupal ainda não executados.

## 0.1.0-dev.195 — tipa o parâmetro convertido da prévia

- O callback de acesso exige `ContentEntityInterface` para receber a aula já
  convertida pelo resolvedor de argumentos do Router e delegar a política.
- Unit cobre autorização do educador e negação de pessoa sem grant.
- Firefox autenticado após rebuild confirmou a rota 200, prévia renderizada,
  nenhum formulário de submissão e nenhuma resposta/gabarito expostos.

## 0.1.0-dev.194 — adiciona prévia privada de aula

- O Educador pode abrir a prévia de uma aula, inclusive rascunhos, usando a
  autorização contextual existente e revalidada no acesso e no controller.
- A saída mostra conteúdo filtrado e enunciados, sem gabaritos, respostas,
  notas, perfis de estudantes ou formulário de submissão.
- A rota canônica permanece no workspace Ganjalab Educador; o LMS fornece
  access check e controller por ser o owner dos dados e da política.

## 0.1.0-dev.193 — ordena DLL e traduções do CKEditor

- Expande o ajuste de ordem dos assets para manter jQuery, traduções e DLL do
  CKEditor antes dos bundles que dependem deles, mesmo com agregação JavaScript.
- Firefox autenticado confirma jQuery, CKEditor editável, toolbar e formato
  filtrado ativos no formulário da aula; o formulário não foi submetido.
- O geckodriver ainda registra um `TypeError` no agregado de
  `ckeditor5_plugin_pack/drupal_powered_by`; o editor inicia, mas investigar se
  o plugin afetado perdeu comportamento.

## 0.1.0-dev.192 — ordena jQuery antes dos agregados JQuery UI

- O asset `core/jquery` recebe peso anterior aos componentes JQuery UI que
  chegam com peso negativo, evitando sua execução antes da dependência.
- Corrige uma causa observada no formulário do educador: CKEditor 5 não
  inicializava quando o agregado UI executava com `jQuery` indefinido.
- Unit cobre a alteração de peso e garante que outros extensions não sejam
  alterados. Repetir smoke visual autenticado após rebuild do cache.

## 0.1.0-dev.191 — inicia shell visual responsivo do Course Builder

- A estrutura do curso agora apresenta resumo de aulas/atividades, cartões
  responsivos de aula e atividade, estados editoriais e ações contextualizadas.
- Estados vazios orientam a criação da primeira aula ou de atividades; controles
  de ordenação por link permanecem acessíveis por teclado e sem JavaScript.
- A tela continua usando as entidades existentes do LMS e não cria níveis
  conceituais ou armazenamento paralelo. Preview, materiais e drag-and-drop
  permanecem pendentes.
- Unit cobre a estrutura do novo render array e os destinos de edição/ordenação.

## 0.1.0-dev.190 — habilita edição rica e filtrada do conteúdo de aulas

- O formulário de aula usa CKEditor 5 com formato dedicado e allowlist de
  elementos editoriais; imagens, mídia incorporada e HTML irrestrito não são
  habilitados nesta fase.
- O papel `lms_teacher` recebe acesso ao formato. Valores antigos `plain_text`
  são escapados ao abrir a edição e só passam a ser HTML filtrado ao salvar.
- Kernel verifica configuração do editor e renderização sanitizada; Unit cobre
  o payload do formulário e a validação de conteúdo.

## 0.1.0-dev.189 — contém a tabela larga do Course Builder no mobile

- O viewport estreito mantém a estrutura LMS dentro de sua própria área de
  rolagem horizontal, permitindo alcançar e acionar os comandos da tabela sem
  alargar a página inteira.
- Unit valida o anexo da biblioteca específica da estrutura. Revisão visual
  final após a correção permanece pendente.

## 0.1.0-dev.188 — corrige renderização da estrutura do curso

- Links e conteúdo renderizado agora são encapsulados em `data` nas células da
  Table API. Sem o wrapper, o Drupal interpretava o objeto `Url` como atributo
  HTML e respondia 500 na estrutura do educador.
- Regressão coberta pela Unit do `CourseStructureController`; smoke no Runtime
  com educador autenticado passou com título, estrutura e ações de movimento.

## 0.1.0-dev.187 — conteúdo textual de aula na sala Ganjalab

- A entidade ACULTA de aula recebe um campo de texto longo com formato fixo
  `plain_text`; o formulário do educador permite criar e editar o corpo textual.
- A sala do estudante apresenta o texto pelo render element `processed_text`,
  com filtro Drupal e tratamento de quebras de linha. Aula publicada sem corpo
  não pode ser gravada pelo formulário; aulas antigas vazias mostram um aviso.
- Update `aculta_lms_update_10015` instala o armazenamento sem alterar o conteúdo
  existente. Kernel confirma update, armazenamento e renderização sem HTML
  executável (8 testes/347 assertions; 14 depreciações upstream); Unit LMS 254
  testes/928 assertions (21 depreciações PHPUnit).

## 0.1.0-dev.186 — localiza os comandos de movimento

- Os caminhos de mover currículo passam a usar `acima`/`abaixo` em português;
  a direção é convertida para o valor interno do serviço de ordenação.
- Rotas do Educador e suas ações de movimento documentadas no mapa operacional.

## 0.1.0-dev.185 — comandos acessíveis de ordenação do currículo

- O Educador agora oferece ações para mover aulas dentro do curso e atividades
  dentro da aula. Os movimentos exigem confirmação, avisam que a ordem é
  compartilhada com as turmas e preservam respostas/notas já registradas.
- A persistência revalida a pertença à coleção, serializa movimentos concorrentes
  por coleção e normaliza os pesos em transação. Atividades com respostas podem
  mudar de posição sem desbloquear edição do enunciado ou gabarito.
- Kernel focal (`AcultaLmsInstallTest`): 8 testes / 340 assertions PASS (14
  depreciações upstream); suíte Unit LMS: 253 testes / 927 assertions PASS
  (20 depreciações PHPUnit). PHPCS nos arquivos de produção e Unit focais PASS.

## 0.1.0-dev.184 — exclusão segura de aulas e atividades

- O workspace Educador agora oferece ações de exclusão com confirmação para
  aulas e atividades. Uma aula com atividade não pode ser excluída; uma
  atividade com resposta ou snapshot de tentativa preserva o histórico.
- Relações Group são limpas pelo hook do módulo Group quando uma atividade
  elegível é removida. Os formulários revalidam a cadeia curso→aula→atividade
  e o histórico no submit.
- Unit LMS: 252 testes / 911 assertions PASS; PHPCS Drupal/DrupalPractice e
  PHP lint focais PASS.

## 0.1.0-dev.183 — autoria de aulas e atividades ACULTA

- Adicionada a página de estrutura do curso no workspace Ganjalab Educador,
  com criação/edição de aulas e atividades usando as entidades ACULTA já
  existentes e o formato `answer_config` consumido pelo formulário do aluno.
- Acesso verifica `grade students` no curso e a cadeia curso→aula→atividade;
  configuração e gabarito de atividade ficam bloqueados após a primeira
  resposta, preservando o contexto histórico sem sobrescrever dados.
- Suíte Unit completa do LMS: 247 testes / 880 assertions PASS; Studio: 67 testes /
  679 assertions PASS. PHPCS Drupal/DrupalPractice focal, PHP lint e
  `git diff --check` PASS. Runtime em `49cc9757a4`: `drush cr` e rotas de estrutura, aula e atividade presentes; `updatedb:status` sem updates e `config:status` limpo após importação de `aculta_patcher.patch.import_510ccfe2e06a59191779bc83`. Os cinco post-updates removidos do Group 2.0 foram reconciliados no registro após validar seus efeitos no estado atual; `core:requirements` não lista mais a incompatibilidade.

## 0.1.0-dev.182 — cobre submissão de arquivos privados

- Kernels exercitam o formulário ACULTA de atividade `file_upload`: arquivo
  privado do próprio estudante aceito, resposta criada com referência no payload,
  arquivo tornado permanente e uso registrado; conteúdo que falsifica extensão
  PNG é recusado sem criar resposta.
- A cobertura usa a File API e a política real `aculta_file_uploader` no banco
  dedicado `aculta_simpletest`; não grava no Runtime.
- Atualizada a documentação que ainda dizia que a interface não conectava a
  referência ao payload.

## 0.1.0-dev.181 — inclui Views administrativas do LMS na instalação

- As Views administrativas `courses_admin` e `classes_admin` passaram a ser
  fornecidas pelo `config/install` do módulo, junto das Views públicas já
  existentes. Isso faz uma instalação limpa do `aculta_lms` receber o mesmo
  conjunto de páginas do Config Sync.
- As permissões específicas de criação de curso e turma foram preservadas. A
  navegação mantém a hierarquia curso-primeiro e não adiciona atalho global de
  turmas.
- Testes de configuração verificam os quatro YAMLs instaláveis contra Config
  Sync; Kernel de instalação verifica que as quatro Views são carregadas.

## 0.1.0-dev.180 — completa revisão docente de respostas por turma

- O painel de turma passou a oferecer a lista de respostas. A consulta é
  paginada e filtra por curso, turma e estudantes ativos atualmente vinculados.
- A autorização agora confirma a relação `lms_classes`, permissão `grade
  students` e matrícula do autor da resposta na turma selecionada. Isso impede
  que uma avaliação de outra turma do mesmo curso apareça por troca de URL.
- O detalhe apresenta o payload enviado com escape, referência segura para
  download privado e formulário próprio de nota (0–100) e feedback. A gravação
  usa transação de banco; feedback não vazio cria comentário publicado via Core
  Comment API para manter histórico e notificações.
- Kernel `AnswerReviewWorkflowKernelTest`: 1 teste / 34 assertions PASS;
  suíte Kernel LMS: 33 testes / 996 assertions PASS (62 depreciações de Core e
  contrib ainda presentes),
  incluindo isolamento entre duas turmas, permissão de lista, payload, nota,
  feedback e comentário persistido. Unit LMS: 234 testes / 828 assertions
  PASS (4 depreciações PHPUnit). O Kernel reporta depreciações upstream; a
  validação visual de navegador permanece pendente.
- PHPCS Drupal, PHP lint e `git diff --check` PASS. Runtime pré-push:
  `updatedb:status` sem pendências e `config:status` sem diferenças. Depois do
  push, sincronizar o código e limpar o cache; não há mudanças de schema ou
  configuração planejadas.

## 0.1.0-dev.179 — abre páginas individuais de aula na jornada

- A jornada do estudante agora liga cada aula publicada a uma página própria,
  com atividades ordenadas e acesso às submissões existentes.
- A autorização exige curso publicado, aula publicada e matrícula vigente; URLs
  amigáveis mantêm a posição original quando há aulas não publicadas. A página
  só exibe atividades disponíveis e pertencentes ao curso.
- Kernel `LearningDashboardKernelTest`: 1 teste / 35 assertions PASS (6
  depreciações upstream). Unit do LMS: 230 testes / 805 assertions PASS
  (4 depreciações PHPUnit). PHPCS e PHP lint dos arquivos alterados PASS.
- Runtime sincronizado no commit 808879f6e2; `drush cr` passou e os status de update/config permaneceram limpos.

## 0.1.0-dev.178 — migra Views públicas de cursos para aculta_lms

- As Views `courses` e `courses_catalog` agora são fornecidas em
  `config/install` pelo módulo, com dependência explícita de Views e LMS.
- O row plugin legado inexistente `course_card_row` foi substituído por
  `entity:group` no modo `default`, usando a exibição oficial do curso para capa
  e descrição.
- As cópias do Sync foram alinhadas às configurações instaláveis.
- `CourseViewsConfigTest`: 1 teste / 10 assertions PASS; YAMLs Sync e install
  iguais e sintaxe PHP válida. A atualização da configuração no Runtime segue
  pendente por falhas independentes nos hooks de pagamento e Group.

## 0.1.0-dev.177 — corrige autorização das respostas ACULTA

- A rota de resposta recebia os slugs ainda não convertidos durante a checagem
  de acesso. O callback agora aceita esses valores brutos, resolve as posições
  com o serviço de slugs e nega parâmetros inválidos sem erro de tipo.
- A verificação de matrícula agora percorre os relacionamentos
  `lms_classes` do curso para localizar as turmas ativas. A consulta anterior
  filtrava o campo `entity_id` pelo ID do curso, embora esse campo identifique
  a turma relacionada.
- Serviços injetados do formulário agora sobrevivem ao cache/serialização do
  Form API. O upload separa `setPermanent()` de `save()`, e o fieldset de
  lacunas preserva a estrutura `answer[blank_N]` para pontuação.
- Unit do LMS: 226 testes / 788 assertions PASS (4 depreciações PHPUnit).
  `AcultaLmsInstallTest`: 7 / 193 PASS (35 depreciações upstream). Portal
  Drupal 11+: 1.202 checks PASS; slugs públicos: 410 rotas PASS; rotas
  administrativas MAIN: 41 arquivos / 170 rotas PASS.
- Runtime: HTTP anônimo retorna 403 nas cinco atividades. A estudante sintética
  abre as cinco páginas e envia verdadeiro/falso, seleção, lacunas, resposta
  livre e arquivo; não houve 500 após os reparos. As três objetivas corretas
  pontuaram 100; resposta livre ficou `submitted` sem nota. Firefox ESR visível
  passou 10/10 checagens do upload em 1440 px e 320 px, incluindo recusa `.exe`,
  PNG permitido e envio final. Capturas estão sob
  `$SUITE_OUT/lms-runtime-validation-20261103/firefox-upload/` e
  `$SUITE_OUT/lms-runtime-validation-20261103/firefox-answer-types/`.
- Dados sintéticos mantidos: uma tentativa de cada tipo, mais uma tentativa de
  lacunas que pontuou 0 antes da correção e uma tentativa de upload parcialmente
  salva durante o 500 anterior. Há dois uploads referenciados: um permanente
  com uso registrado e um temporário. Nenhum dado foi removido.
- O painel docente responde HTTP 200 no domínio MAIN e mostra a turma; não há
  rota/tela de revisão ou avaliação das respostas. Isolamento entre dois
  estudantes não foi testado porque o cenário contém uma só estudante. Olivero
  não foi selecionado para a validação visual.
- `ClassActivityDeadlineTest` continua sem executar porque o harness ainda
  declara o módulo removido `lms`; a regressão da regra de matrícula foi
  coberta por `LearningEligibilityTest` Unit.

## 0.1.0-dev.176 — validação Runtime interrompida por erro HTTP

- Runtime Homelab sincronizado a `775295ac3d1c8c21524a9b15696515b70182b1b2`.
  Snapshot integral pré-alteração: SHA-256
  `374e09c05e47de60cc42a01d14c0e0d4186c1fa222a1c787acce00f1e92dd5a9`,
  `$SUITE_OUT/lms-runtime-validation-20261103/aculta_runtime-before-775295ac3.sql`.
- `composer install` concluiu sem instalar/atualizar/remover pacotes; lockfile
  contém zero pacotes `drupal/lms*`. `drush cr` passou. Antes do update havia
  somente `aculta_lms_update_10013`; foi aplicado e, depois, `updatedb:status`
  ficou sem pendências. `config:status` permaneceu sem diferenças antes/depois;
  nenhum `config/sync` foi importado.
- Criados dados sintéticos marcados para validação: curso e turma auxiliar,
  duas aulas publicadas, cinco atividades dos tipos objetivos, resposta livre
  e upload, um estudante e um educador. Nenhuma resposta ou arquivo foi
  submetido antes da interrupção.
- Smoke HTTP anônimo às rotas dos cinco tipos de resposta retornou HTTP 500;
  corpo capturado: `O site encontrou um erro inesperado. Tente novamente mais
  tarde.` O runner consultou `/user/login` no host de Conta e recebeu HTTP 404.
  Não houve sessão autenticada, POST de respostas, upload ou alteração de tema.
- Gates Portal 1202 checks, slugs públicos 410 rotas, rotas administrativas
  MAIN 41 arquivos/170 rotas e índice 53 componentes: PASS. A validação visual
  Firefox/ACULTA420/Olivero, a avaliação de resposta discursiva pelo educador
  e o isolamento entre dois estudantes ficam DEFERRED até correção do 500 e
  nova validação autorizada. Dados sintéticos permanecem no Runtime para
  revisão; descarte requer ordem separada.

## 0.1.0-dev.175 — resposta ACULTA sem LMS contrib

- Converte os últimos pontos de integração e testes para entidades e serviços
  ACULTA. `UploadedAnswerAccessCheck` delega à política de anexo de resposta
  própria; prazo e discussão integram o formulário ACULTA.
- `AnswerSubmissionForm` grava tentativas imutáveis para respostas discursivas,
  objetivas e upload privado sob a política central de arquivos. O novo plugin
  de texto livre mantém feedback por expressões como indicador formativo;
  somente o educador atribui a nota final. A configuração JSON da atividade é
  instalada pelo update 10013.
- O roteamento por slugs passa a apontar para o formulário ACULTA. As cópias
  inativas do roteamento no Portal foram removidas; os testes proprietários
  permanecem em `aculta_lms`.
- Versões: `aculta_lms` 0.1.0-dev.175, `aculta_forum_lms` 0.1.0-dev.35 e
  Portal 0.4.1-dev.68.
- Validações: LMS Unit 224 testes/773 asserções PASS; Kernel de instalação
  6/178 PASS (35 depreciações upstream); Forum LMS Unit 33/331 e Kernel focal
  1/25 PASS (38 depreciações upstream); teste stale answer do Messenger 1/8
  PASS. Gate Portal 1.202 checks, slugs públicos 410 rotas e índice 53
  componentes PASS. Runtime permanece sem alteração; validação visual e
  sincronização DEFERRED.

## 0.1.0-dev.174 — remove declarações xAPI órfãs (ADR-041)

- Após snapshot (SHA-256
  `a867e0bccd0febe81a25d3c9a910043c1b605ea313782d72f032ccc1e5d1dd26`), foram
  removidas as 3 declarações com `lesson_status_id=4`, sem progresso ACULTA
  correspondente. A instrução SQL foi limitada à tabela xAPI e aos três
  `statement_id` confirmados no inventário operacional.
- Contagens antes/depois: `aculta_lms_xapi_statement` 3/0;
  `aculta_lms_progress` 0/0; `aculta_lms_lesson` 0/0;
  `aculta_lms_activity` 0/0; `aculta_lms_answer` 0/0. Nenhuma outra tabela
  ou entidade foi modificada pela exclusão.
- Cache rebuild passou; `updatedb:status` sem pendências; nenhuma importação de
  configuração.

## 0.1.0-dev.173 — registra evidências e pendências xAPI (ADR-041)

- Documenta `lesson_status_id` como identificador opaco de
  `aculta_lms_progress.id` e encerra a suposição de uma tentativa por aula no
  LMS contrib, removido em P5.
- Consulta somente leitura no Runtime encontrou 3 declarações com
  `lesson_status_id=4`, sem progresso ACULTA correspondente. Nenhuma linha foi
  alterada.
- Confirmada a Key ativa `aculta_lms_xapi_token`, provider `env`, e a presença
  da variável contratada com comprimento 64; o conteúdo não foi exibido nem
  registrado. Provisionamento de produção continua pendente.
- Medição de carga de escrita MariaDB: **DEFERRED** até haver ambiente
  production-like representativo e teste sintético autorizado antes da
  ativação xAPI em produção.

## P6 — descarte autorizado de dados de teste no Runtime (ADR-042)

- Snapshot anterior ao descarte: SHA-256
  `038a4e61326c8940500729e7592595d4587a593cc8e12d8e34c0dc81c252c74a`.
- Pela API Drupal foram removidos o único arquivo residual associado a uma
  resposta LMS, seis vínculos `group_membership` e os cinco Groups de teste
  conferidos com o inventário P5. Os dados contrib LMS listados na ordem já
  estavam ausentes ou zerados. Não houve importação de config.
- Verificação: Groups, vínculos e uso de arquivo `lms_file_upload` zerados;
  usuários (23), pedidos Commerce (111), itens de pedido (106), comentário
  ACULTA (1) e declarações xAPI (3) preservados. Cache rebuild passou,
  `updatedb:status` sem pendências, Portal gate 1.172 checks PASS e grep de
  `Drupal\\lms` em `web/modules/custom` com zero ocorrências. Diferenças de
  `config:status` revisadas, sem importação.
- Efeito inesperado da exclusão dos Groups: `aculta_lms_attempt_archive` caiu
  de 1 para 0; também variaram `entity_usage` (351 para 343), `key_value`
  (3764 para 3753), `path_alias` e `path_alias_revision` (52 para 46), e
  `queue` (7 para 10). Nenhuma restauração ou outra exclusão foi feita.

## 0.1.0-dev.172 — remove o LMS contrib no corte P5 (ADR-042)

- Remove `drupal/lms` do `composer.json` e do lock; Composer confirmou somente
  a remoção desse pacote. `lms_file_upload` não era pacote Composer. Cópias
  manuais de `lms` e `lms_file_upload` foram retiradas dos caminhos de descoberta.
- Desinstalação Runtime em ordem: `lms_answer_comments`, `lms_answer_plugins`,
  `lms_classes`, `lms`. Por ordem explícita de descarte em P5, foram removidos
  8 aulas, 17 atividades, 3 respostas, 3 status de aula e 2 status de curso.
  Também foram removidos 2 vínculos Group de cursos/turmas marcados `[TESTE P1]`;
  os cinco Groups permaneceram. Nenhum conteúdo foi migrado.
- Update Fórum 10001 refez a referência para `aculta_lms_activity` sem valores
  legados; LMS 10012 preservou a dependência ACULTA da relação curso–turma.
- Cache rebuild passou e `updatedb:status` está limpo. `config:status` ainda
  mostra diferenças de superfície, Groups, tópicos, papel e Views; a lista foi
  revisada e não houve importação de config/sync.
- Validação final: LMS Unit 218/758, Fórum Unit 33/329, Kernel
  `AcultaLmsInstallTest` 6/172 e Fórum focal 1/25 passaram. Gates Portal
  1.172 checks e public-slugs 410 rotas passaram; a busca final por imports
  do namespace do LMS contrib em `web/modules/custom` retornou zero.
- Snapshot pré-P5: SHA-256
  `37947c05fe3d523fbce0ff42c78f61f5d90f56a2a566de7c06f1869774bcd595`.
  P6 e reescrita de histórico não executados.

## 0.1.0-dev.171 — prepara o corte de entidades e relações legadas

- Config Sync remove configurações que pertencem às entidades LMS contrib,
  mantém Groups, cursos, turmas e relações Group e aponta o vínculo de
  discussões para `aculta_lms_activity`.
- O Kernel focal do Fórum não habilita os módulos LMS. Um update recria o campo
  de atividade ACULTA e recusa a execução se houver valores legados a migrar.
- Update 10012 mantém a configuração da relação curso–turma sob dependência
  de `aculta_lms`, preservando os vínculos Group no corte do contrib.
- Unit: 218 testes/758 assertions PASS; `AcultaLmsInstallTest`: 6 testes/172
  assertions PASS (35 depreciações upstream); gate Portal: 1.172 checks PASS.
- `config:status` mostra as configurações contrib removidas/alteradas e
  `aculta_portal.superficies`; a diferença foi revisada e não foi importada.
- Snapshot pré-P5 do Runtime: SHA-256
  `37947c05fe3d523fbce0ff42c78f61f5d90f56a2a566de7c06f1869774bcd595`.
- Composer e módulos LMS continuam instalados no Runtime até a etapa final P5.

## 0.1.0-dev.170 — valida instalação limpa sem dependências LMS

- Remove do manifesto de `aculta_lms` as dependências `lms`, `lms_classes`,
  `lms_answer_plugins` e `lms_answer_comments`; dependências Composer e módulos
  ativos do Runtime permanecem para a fase P5.
- `AcultaLmsInstallTest` instala as quatro entidades P2 sem habilitar módulos
  LMS nem criar schemas contrib. A fixture de `lms_classes.settings` é semeada
  diretamente no armazenamento, sem exigir schema do módulo removido.
- Unit completa: 218 testes/758 assertions PASS; Kernel `AcultaLmsInstallTest`:
  6 testes/170 assertions PASS, 35 depreciações upstream. Gate Portal PASS
  (1.172 checks); public-slugs PASS (423 rotas, zero violações).
- Runtime `aculta_runtime`: `updatedb:status` sem atualizações; `config:status`
  mantém apenas as diferenças anteriores em `aculta_portal.superficies` e
  `core.extension`; `drush cr` PASS e `aculta_lms`/rota de arquivo disponíveis.
  Nenhuma configuração foi importada nem módulo LMS desinstalado.

## 0.1.0-dev.169 — remove hooks presos a formulários e links LMS

- Remove `ActivityDeadlineFormHooks` e `CourseLinkHooks`, que alteravam
  formulário/componente e rota fornecidos pelo contrib. Prazos por turma seguem
  no manager ACULTA; início do curso segue pela página canônica do Group.
- A varredura de imports PHP do LMS contrib em módulos custom retorna zero.
- Unit 218/758 PASS; `AcultaLmsInstallTest` 6/196 PASS (44 depreciações);
  `AttemptArchiveLifecycleTest` 5/201 PASS (50 depreciações); gate Portal
  1.172 checks PASS. P4 ainda requer harness limpo e remoção das dependências
  do manifesto; Composer e Runtime aguardam P5.

## 0.1.0-dev.168 — remove plugin e rota legados de arquivo

- Remove `FileUpload`, `UploadedAnswerAccessCheck`, o controller e a rota antigos
  baseados em `lms_answer`, além dos testes e configurações do plugin. A rota
  ACULTA de referência privada continua registrada e protegida por `AnswerEntity`.
- A interface de submissão ACULTA para criar referências de arquivo segue
  pendente; essa mudança não migra conteúdo legado.
- Unit completa 220/764 PASS; `AcultaLmsInstallTest` 6/196 PASS
  (44 depreciações) e `AttemptArchiveLifecycleTest` 5/201 PASS (50
  depreciações Core/contrib); gate Portal 1.172 checks PASS.

## 0.1.0-dev.167 — remove plugin legado de revisão por frases

- Remove `FreeTextReviewFeedback`, seu teste e configurações de `phrase_feedback`,
  que dependiam do plugin manager e da entidade Activity do Drupal LMS. A revisão
  manual segue disponível por estado/nota ACULTA e comentários Core.
- Unit completa 231/810 PASS (1 depreciação PHPUnit); `AcultaLmsInstallTest`
  6/196 PASS (44 depreciações Core/contrib); gate Portal 1.172 checks PASS.
  P5 segue incompleto: imports e dependências LMS restantes não foram removidos.

## 0.1.0-dev.166 — cobre idempotência do campo de nota precisa

- Kernel de instalação remove a coluna de precisão no banco efêmero e executa
  `aculta_lms_update_10011()` duas vezes, confirmando instalação idempotente
  (1 teste/51 asserções PASS; 43 depreciações Core/contrib).
- A mudança complementa o corte do histórico ACULTA; não altera conteúdo do
  Runtime. O gate Portal continua em 1.172 checks PASS.

## 0.1.0-dev.165 — arquiva tentativas ACULTA sem Drupal LMS

- O histórico agora lê `AnswerEntity`, resolve atividade/aula/curso pelas
  entidades ACULTA e preserva payload JSON, comentários publicados e a nota
  exata em `score_precise`, mantendo o campo inteiro legado `score`.
- O acesso ao histórico exige usuário ativo e associação/permissão atual do
  grupo; a autorização antiga `administer lms` não libera mais os registros.
- Unit completa 235/830 PASS (1 depreciação PHPUnit); Kernel
  `AttemptArchiveLifecycleTest` 9/367 PASS (50 depreciações Core/contrib);
  gate Portal 1.172 checks PASS.
- Runtime: snapshot SHA-256
  `a344c5509ee02a565b4bd72790da51dd541d86298f75ba42eb7ba5f91c1fa076`; update
  10011 e `drush cr` PASS. `updatedb:status` limpo; `config:status` mantém
  `aculta_portal.superficies` e `core.extension` diferentes. P5 continua; outros
  imports e dependências LMS ainda precisam ser removidos.

## 0.1.0-dev.163 — protege downloads de anexos ACULTA

- Adiciona rota ACULTA para anexo da tentativa. O acesso exige resposta enviada
  ou avaliada, atividade `file_upload`, arquivo permanente em `private://` e,
  por requisição, matrícula atual do aluno dono ou permissão de correção no curso. A resposta é
  `application/octet-stream`, `nosniff`, attachment e `private, no-store`.
- Unit completa 236/829 PASS (1 depreciação); Kernel `AcultaLmsInstallTest`
  6/194 PASS (44 depreciações); Unit de autorização 3/30 PASS; PHPCS e Portal
  gate 1.172 PASS. Rota contrib permanece durante a transição. P5 segue
  incompleto; não removeu dependências.

## 0.1.0-dev.162 — alinha campo de comentário ao Runtime

- A configuração do campo `comment_body` segue as dependências geradas pelo
  Core, mantendo instalação e sync coerentes.
- Runtime: snapshot antes de `10010`, 136.543.115 bytes, SHA-256
  `a382cb025e0593ecaee741e1f285fb509995fb2b467990defa240fa68400b0bf`;
  `10010` e `drush cr` PASS. `updatedb:status` sem pendências; `config:status`
  mantém apenas duas diferenças externas ao LMS (`aculta_portal.superficies`
  e `core.extension`), sem import global. Kernel `AcultaLmsInstallTest` 6/191
  PASS (44 depreciações); Unit LMS 233/799 PASS (1 depreciação); Portal gate
  1.172 checks PASS.

## 0.1.0-dev.161 — sincroniza configuração do comentário ACULTA

- Os quatro objetos de configuração do tipo e do campo de comentários ACULTA
  também ficam no `config/sync`, para que uma sincronização posterior preserve
  o update `10009`. O update `10010` cria o campo `comment_body` se ausente.
  Os UUIDs de instalação e sincronização seguem os valores já persistidos no
  Runtime para evitar substituição de entidades de configuração.
- Runtime: snapshot anterior ao update `10009`, SHA-256
  `d44704f1c612c95b2854bdfb5401196d9293a896dd1dfde33d49c5684bcfe9a4`;
  update `10009` e `drush cr` PASS. Kernel completo 6/191 PASS (44
  depreciações); Unit 233/799 PASS (1 depreciação). `10010` aguarda snapshot e
  aplicação. `config:status` mostra somente diferenças externas LMS em
  `aculta_portal.superficies` e `core.extension`, além do campo de comentário
  que o update `10010` ainda criará; a importação global foi adiada para não
  remover a ativação de superfícies fora do escopo.

## 0.1.0-dev.160 — comentários Core em tentativas ACULTA

- O tipo `aculta_lms_answer_comment` e o campo Core `comments` pertencem a
  `aculta_lms_answer`; comentários começam fechados e o update `10009` cria as
  configurações de modo idempotente. Notificações e cartão de autoria passam a
  reconhecer esse tipo próprio.
- CourseAnswer continua sendo a projeção de estado e nota. O schema de payload
  imutável do update `10008` permanece; `AnswerPayload` passa a validar e
  serializar referências positivas de managed file sem conceder acesso ao
  arquivo. Kernel `AcultaLmsInstallTest` 6/190
  PASS (44 depreciações Core/contrib); Unit completa 231/793 PASS (1
  depreciação PHPUnit). O consumidor ainda depende do contrib até o restante do
  corte P5.

## 0.1.0-dev.159 — payload ACULTA imutável por tentativa

- `aculta_lms_answer` passa a guardar payload estruturado JSON; atividade, aluno,
  data de criação e payload não podem ser alterados depois do envio. Uma nova
  tentativa cria outro registro; estado e nota continuam editáveis para a
  avaliação. O contrato `CourseAnswer` permanece como projeção de estado/nota.
- `aculta_lms_update_10008` instala o campo sem regravar respostas antigas;
  repetição não altera o schema. Unit `AnswerPayloadTest` 3/7 PASS e Kernel
  `AcultaLmsInstallTest` 6/147 PASS (28 depreciações upstream).

## 0.1.0-dev.158 — agenda usa entidades de aprendizagem ACULTA

- O provedor de agenda verifica `take course` pela permissão Group e o Kernel
  monta os prazos com `LessonEntity`/`ActivityEntity`; schemas LMS contrib
  deixaram de ser instalados pela suíte da agenda.
- A relação `lms_class_activity_due` mantém o ID, mas sua config deixa de
  depender do módulo `lms`. O título do prazo usa o título da entidade quando
  existe e, para `ActivityEntity` que não tem campo de título, apresenta o tipo
  estável da atividade humanizado.
- A verificação revelou e corrigiu acesso neutro para evento de outra turma;
  `EventAccessHooks` agora nega explicitamente e é descoberto como hook OOP.
  Corrigida também a sintaxe inválida em `EventClassAccess.php` recebida do
  upstream.
- Agenda Kernel 4/168 PASS (94 depreciações upstream), Agenda Unit 8/18 PASS;
  PHPCS e gate Portal 1.172 PASS após atualizar a linha de base para incluir o subscriber de superfícies (registrado, interruptor desligado). O harness Kernel ainda carrega LMS por causa
  do manifesto de `aculta_lms`, não por imports da fixture da agenda.

## 0.1.0-dev.157 — permissões herdadas de cursos e turmas em ACULTA

- O cálculo de permissões Group entre cursos e turmas passa a ser implementado
  por `ClassPermissionCalculator` em `aculta_lms`, preservando o ID da relação
  `lms_classes` e os mapeamentos configurados.
- `aculta_lms_update_10007` transfere os mapeamentos ativos para
  `aculta_lms.settings`; nenhum conteúdo é migrado. O harness/kernel ainda usa
  módulos contrib neste passo.
- Unit completa 228/786 PASS; Kernels de roster 3/66 e instalação 6/143 PASS;
  gate Portal 1.168 checks e PHPCS focal PASS. Runtime snapshotado antes do
  update 10007 (SHA-256 `624bb557...c6e424`); update/cache rebuild PASS e
  `updatedb:status`/`config:status` limpos. A sincronização criou as configs
  `aculta_lms.settings` e `aculta_portal.superficies`.

## 0.1.0-dev.156 — relação de turmas Group própria (ADR-042 P5)

- `CourseClassRelation` no `aculta_lms` implementa o plugin Group `lms_classes` com as cardinalidades existentes; `CourseClassRelationHooks` seleciona a implementação ACULTA durante a convivência com o contrib, mantendo intactos os IDs e registros da relação.
- `ClassRosterVisibilityTest` verifica plugin, cardinalidades e roster: Kernel focal 2 testes/50 assertions PASS (41 depreciações Core/contrib). Unit completa: 228 testes/786 assertions PASS (1 depreciação PHPUnit); PHPCS focal e gate Portal (1.160 checks) PASS. O harness sem contrib segue pendente para P4.

## 0.1.0-dev.155 — Kernel de certificados usa progresso ACULTA (ADR-042 P5)

- `CertificateLifecycleTest` cria e verifica `aculta_lms_progress`; deixa de importar tipos `CourseStatus` do LMS contrib, mantendo os testes do ciclo de certificados no modelo próprio já consumido pelo serviço.
- Kernel focal: 5 testes/158 assertions PASS; suíte Unit completa: 228 testes/786 assertions PASS (1 depreciação PHPUnit).

## 0.1.0-dev.154 — slugs consultam conteúdo ACULTA (ADR-042 P5)

- O conversor e o route processor usam `GroupInterface`; slugs de aula consultam `aculta_lms_lesson` por `course_id` e os de atividade consultam `aculta_lms_activity` por `lesson_id`, ambos em ordem de peso/ID.
- Tipos estáveis de atividade geram o slug enquanto `CourseActivity` ainda não define título. Rotas contrib continuam sendo detectadas pelo nome apenas, sem importação do namespace LMS.
- Unit focal de slugs 10/31 PASS.

## 0.1.0-dev.153 — xAPI usa progresso ACULTA (ADR-042 P5)

- Emissão e validação de tokens resolvem `lesson_status_id` em `aculta_lms_progress` e conferem o `uid`; o campo persistido mantém o nome já usado e passa a apontar para progresso ACULTA, conforme P2.
- A estrutura da tabela e os registros xAPI não são reescritos; IDs legados permanecem opacos e não são remapeados. A associação agora representa progresso por curso, pois progresso individual por aula não existe no modelo P2.

## 0.1.0-dev.138 — certificados usam progresso ACULTA

- Emissão, formulário de seleção e autorização consultam `aculta_lms_progress` e Groups; certificados continuam registrando o UUID de origem no campo existente `course_status_uuid`.
- Unit completa 224/724 PASS (1 deprecation PHPUnit); teste de acesso de emissão ajustado para Group.

## 0.1.0-dev.137 — sala de curso usa contratos Group e ACULTA

- `CourseRoomBuilder`, `CourseRoomHooks` e `ClassRosterManager` deixam de exigir o tipo `CourseInterface` ou `ClassHelper`; aulas/turmas continuam sendo resolvidas por Group e relações registradas.
- Unit focal do builder 2/12 PASS; Unit completa 224/724 PASS (1 deprecation PHPUnit).

## 0.1.0-dev.136 — evento de conclusão usa progresso ACULTA

- `CourseCompletionPublisher` e seus hooks publicam `CourseCompletedEvent` a partir de transições da entidade `aculta_lms_progress` para `passed` e `finished`.
- Teste Unit cobre publicação de conclusão válida e recusa de estado não aprovado, incompleto ou recém criado.

## 0.1.0-dev.135 — painel passa a ler progresso e aulas próprias (ADR-042)

- `LearningDashboardManager` e a jornada usam os contratos ACULTA de progresso e aulas; métricas de turma consultam `aculta_lms_progress`.
- O P2 mantém apenas progresso agregado por curso. O painel não inventa aula atual nem progresso individual por aula; o estado agregado acabado representa o curso inteiro.

## 0.1.0-dev.134 — fonte própria de respostas (ADR-042)

- `CourseAnswerReaderInterface` passa a consultar `aculta_lms_answer` por atividade e estudante; estado e nota são mapeados no vocabulário ACULTA e as respostas são ordenadas por ID.
- Teste focal cobre estado submetido, nota nula, conversão para `float` e ordenação.

## 0.1.0-dev.133 — fonte própria de atividades (ADR-042)

- `CourseActivityReaderInterface` passa a consultar `aculta_lms_activity` por `lesson_id`, mapeando tipo, inclusão em nota e posição.
- Teste focal cobre mapeamento e ordenação por `weight`/ID.

## 0.1.0-dev.132 — fonte própria de aulas (ADR-042)

- `CourseLessonReaderInterface` passa a ler `aculta_lms_lesson` por `course_id`, retornando todas as aulas em ordem de `weight` e ID.
- Teste focal cobre mapeamento dos campos, aula não publicada e ordenação estável.

## 0.1.0-dev.131 — fonte própria de progresso por curso (ADR-042)

- `CourseProgressReaderInterface` passa a ler `aculta_lms_progress` por `course_id` e `uid`, via `entity_type.manager`; status inválido é recusado e nota numérica vira `float`.
- A suíte focal do leitor cobre progresso encontrado, nota nula e ausência de registro. A fonte contrib segue registrada, sem alias ativo, até a remoção conjunta dos adaptadores no fim da Fase A.

## 0.1.0-dev.130 — plugins objetivos ACULTA (ADR-042, P3)

- Adicionados plugins ACULTA para `true_false`, `select` e `fill_blanks`, ligados a `CourseActivity` pelo tipo estável e resolvidos por serviço injetado.
- Os plugins usam `ObjectiveAnswerScorer`; seleção exige conjunto exato e lacunas usam a normalização existente. Os plugins do LMS contrib continuam ativos; nenhuma dissertativa, resposta por arquivo, revisão ou troca de leitor foi incluída.
- `ObjectiveAnswerPluginsTest` 5/13 PASS; suíte Unit completa 216/686 PASS (1 depreciação PHPUnit). Kernel `AcultaLmsInstallTest` 5/128 PASS (27 depreciações upstream), incluindo serviço injetado e resolução de `select` pelo tipo de `CourseActivity`. Nenhuma troca dos plugins contrib, integração de formulário, persistência ou leitor nesta fase.

## 0.1.0-dev.129 — entidades próprias de aprendizagem (ADR-042, P2)

- Criadas as entidades internas de aula, atividade, resposta e progresso, com acesso negado por padrão e sem rotas ou formulários.
- `aculta_lms_update_10005` instala as quatro definições de modo idempotente; `10006` alinha colunas obrigatórias e tamanhos (`state` 16, `status` 32). Índices compostos definidos na schema própria, incluindo unicidade curso/aluno no progresso.
- Unit completa 211/673 PASS (1 depreciação PHPUnit); `AcultaLmsInstallTest` 5/126 PASS (27 depreciações upstream), incluindo reexecução dos updates, acesso negado e índice único. Gate Portal PASS (1.159 checks). Runtime atualizado após snapshot SHA-256 `d8be1298ddbc961a1397ffc22db74a3aa044d74edc2d1caeaafffcef2de0526e`; `updatedb:status` e `config:status` limpos; sem import de Config Sync.

## 0.1.0-dev.128 — Adaptadores de leitura ACULTA para aula, atividade e resposta

- Registrados `ContribCourseLessonAdapter`, `ContribCourseActivityAdapter` e `ContribCourseAnswerAdapter`, cada um isolando sua entidade do LMS contrib atrás do contrato correspondente. Os serviços usam injeção de dependência e aliases por interface.
- Testes Unit dos adaptadores cobrem conversão, nota ausente, tipo de atividade ausente e recusa de estado de avaliação inválido. Unit do módulo: 204 testes/666 assertions PASS; `AcultaLmsInstallTest`: 4/95 PASS validando os aliases; gate Portal: 1.159 verificações PASS.
- O estado do LMS contrib é booleano e vira submitted/graded. TrainingManager::getActivityMaxScore permite converter pontos por atividade para percentual 0–100; resposta avaliada sem nota ou atividade sem máximo é recusada.
- A posição da aula vem do delta da referência no curso; a pontuação máxima da atividade vem da referência na aula.

## 0.1.0-dev.127 — Contratos de atividade e resposta (ADR-042, P1)

- `CourseActivity` e `CourseAnswer` (valores imutáveis) com `CourseActivityReaderInterface` e `CourseAnswerReaderInterface`.
- `CourseAnswer` exige nota quando avaliada e recusa notas fora de 0–100.
- Smoke sem Drupal 5/5; `CourseActivityAnswerTest` escrito, não executado. Adaptadores do contrib pendentes.

## 0.1.0-dev.126 — Contrato de aula (ADR-042, P1)

- `CourseLesson` (valor imutável) e `CourseLessonReaderInterface` definem a aula no vocabulário ACULTA, sem tipos do contrib.
- Versão 0.1.0-dev.126 foi marcada por `afb7816d`; este bloco registra o conteúdo.
- Adaptador do LMS contrib e testes no Runtime pendentes; nenhum consumidor mudou.
- Smoke sem Drupal 3/3; `CourseLessonTest` escrito, não executado.

## 0.1.0-dev.125 — Desacoplamento do leitor de cursos (ADR-042, decisão 6a)

## 0.1.0-dev.126 — remoção de ligações ao upload de atividades e ao DT-U03

- Campo `field_file_extensions` removido (DT-U03, decisão do responsável): a lista da atividade `file_upload` vem da política do `aculta_file_uploader`.
- Teste de instalação habilita `lms` e dependências, como as demais Kernel do módulo. Kernel: 31 testes, 851 asserções, 2 erros de harness.
- Versão 0.1.0-dev.126.

- `AccountCoursesReader` deixa de importar classes do LMS contrib. Depende de `CourseProgressReaderInterface` (contrato ACULTA) e
  recebe um `CourseProgress` (status, conclusão, nota, cache tags).
- `ContribCourseProgressAdapter` é a única classe do módulo que lê o `TrainingManager` do LMS contrib. Mapeia
  `loadCourseStatus` para os status `in progress`, `passed`, `failed` e `evaluation`, e ignora o que não for `Course`.
- `AccountCoursesReaderTest` reescrito para o contrato: concessão booleana mantém o curso; negação falha fechado sem
  consultar o progresso.
- Testes PHPUnit e Kernel não executados nesta sessão (sem `vendor/`); verificação no Runtime pendente.

## 0.1.0-dev.124 — LRS xAPI 2.0 ativo (ADR-041, F3)

- `aculta_lms_update_10003` e a configuração `aculta_lms.xapi` instalados no Runtime de teste; `updatedb:status` e
  `config:status` sem pendências após importação somente de `config/sync`.
- Formulário de validade do token disponível no MAIN; gate de domínio administrativo PASS. Tokens HMAC-SHA256 usam Drupal
  Key com provider env, padrão 1800 s e teto 7200 s, vinculados a usuário, conteúdo, registration, actor e
  `lesson_status_id`.
- `POST /xapi/statements` autentica pelo header `X-ACULTA-XAPI-Token` (Bearer conflita com Simple OAuth), valida lotes
  com `XapiBatchIngestor`, impõe limites de corpo/declarações, idempotência e flood de 60/minuto por token, e persiste
  somente declarações aceitas.
- Hook de cron executa a retenção mensal em lotes de 500, com corte por `XapiRetention::cutoff()` e log da quantidade
  removida. Um mês só é marcado concluído quando não há lote cheio restante.
- Segredo `ACULTA_LMS_XAPI_TOKEN_SECRET` incluído no Secrets Contract e allowlist do loader; nenhum valor de segredo é
  versionado. PHPUnit oficial xAPI Unit: 65 testes/99 assertions; Kernel MariaDB `aculta_simpletest`: 2/22, incluindo
  o hook mensal. Firefox ESR com `DISPLAY=:0`: declaração aceita e persistida em ACULTA420 e Olivero; formulário
  aparece no MAIN e responde 404 em `cursos`. Flood real: 60 lotes inválidos retornaram 400 e o 61º retornou 429.

## 0.1.0-dev.123 — modelo de certificado separado do registro emitido

- `Entity\CertificateTemplate` (`aculta_lms_certificate_template`): nome, orientação do papel, modo de visualização e
  referência opaca ao curso. Separado do `CourseCertificate`, que continua sendo o registro emitido. Ideia do Opigno
  (`opigno_certificate`), implementação própria; `entity_print` segue para o PDF.
- `aculta_lms_update_10004` instala a entidade, com a mesma guarda dos updates 10002 e 10003.
- Sem handler de acesso, sem rota e sem ligação com a emissão (decisão pendente: se o certificado emitido guarda a referência ao modelo).
- Instalação no Runtime e testes Kernel: DEFERRED.

## 0.1.0-dev.122 — pontuação própria dos tipos objetivos (ADR-042, P3, parcial)

- `Assessment\ObjectiveAnswerScorer`: verdadeiro/falso (100 ou 0), seleção só com o conjunto exato, sem crédito parcial,
  e lacunas por percentual de respostas aceitas (sem diferença de caixa, espaços nas pontas e espaços internos colapsados).
  Mesma escala da AttemptRule (0 a 100). Função pura, sem dependência do `lms_answer_plugins`.
- Validação: `ObjectiveAnswerScorerTest` 5/5 no shim. Mutante com crédito parcial na seleção: 1 teste falha.
- Plugins de resposta e entidades próprias: não ligados ainda (P2/P3, Runtime).

## 0.1.0-dev.121 — nota xAPI com min ausente (F2)

- `XapiProgressDeriver`: `raw` com `max` e sem `min` agora vale com `min = 0`, como a xAPI permite. Sem `success` virando
  nota máxima (diferença deliberada em relação ao Opigno). Teste `testMissingMinDefaultsToZero`.
- Validação: 63/63 no shim (suítes xAPI). Sem Runtime: nenhuma alteração de banco ou configuração.

## 0.1.0-dev.120 — retenção configurável das declarações xAPI (D-68, parcial)

- `Xapi\XapiRetention`: prazo de retenção em meses (`retention_months` em `aculta_lms.xapi`), padrão 6, faixa 1–24.
  `cutoff()` dá o instante UTC antes do qual as declarações estão vencidas. Função pura.
- Mesma opção administrativa de validade do token, agora com os dois campos. Configuração padrão e schema atualizados.
- Validação: `XapiRetentionTest` 3/3. Com as demais suítes xAPI, 62/62 no shim.
- Remoção mensal das declarações vencidas (serviço com consulta de entidade): DEFERRED, Runtime.

## 0.1.0-dev.119 — validade do token de tentativa configurável (F3, parcial)

- `Xapi\XapiTokenSettings`: lê a validade do token (`aculta_lms.xapi`, chave `attempt_token_ttl`, em segundos). Padrão
  1800 s; valor ausente, não inteiro ou fora de 60–7200 s cai no padrão.
- `Form\XapiTokenSettingsForm` em `/painel-administrativo/aculta/lms/xapi/validade-token`, permissão
  `administer site configuration`, rota `_admin_route`. Só no domínio principal: gate
  `validate-admin-routes-main-domain.php` PASS (39 arquivos, 163 rotas).
- Configuração padrão em `config/install/aculta_lms.xapi.yml` e schema em `config/schema/aculta_lms.xapi.schema.yml`.
- Validação: `XapiTokenSettingsTest` 5/5 no shim; com as suítes de token (8) e ingestão (10), 23/23 no total.
  Formulário, rota e instalação da configuração no Runtime: DEFERRED. `config:export` para `config/sync` antes do RC.

## 0.1.0-dev.118 — ingestão de lotes xAPI, função pura (F3, parcial)

- `Xapi\XapiBatchIngestor`: decide, sem gravar, quais declarações de um lote são aceitas. Lote que não é lista JSON,
  vazio, com mais de 50 declarações ou acima de 1 MiB é recusado por inteiro. Cada declaração passa pelo
  `XapiStatementValidator`; registration diferente do token é recusado por declaração; ausente, recebe o do token.
  Declaração sem id, repetida no lote ou já gravada é recusada como duplicata.
- Validação: `XapiBatchIngestorTest` 10/10 no shim. Regressão (token, deriver e validador): 54/54 no total.
  Controle de mutação: sem checagem de duplicata, dois testes falham. Gravação, endpoint e Runtime: DEFERRED.

## 0.1.0-dev.117 — token de tentativa e entidade de declarações xAPI (F3, parcial)

- `Xapi\XapiAttemptToken`: token curto por tentativa, HMAC-SHA256, com estudante (uid), conteúdo, lançamento
  (registration) e validade (máximo de 7200 s). Função pura: o segredo é injetado, com mínimo de 32 bytes; a
  verificação usa comparação em tempo constante e recusa token malformado, adulterado, de outro segredo ou expirado.
- `aculta_lms_xapi_statement` (`XapiStatement`): registro bruto e somente de inserção das declarações validadas.
  Tentativa referenciada de forma opaca por `lesson_status_id`, sem copiar estado. Instalada pelo
  `aculta_lms_update_10003`. Sem handler de acesso e sem rota: entram com o endpoint.

Validação: `XapiAttemptTokenTest` 8/8, `XapiProgressDeriverTest` 16/16 e `XapiStatementValidatorTest` 20/20 no shim
(44/44). Controle de mutação: sem checagem de expiração, `testExpiredTokenIsRefused` falha. Instalação do update 10003
e endpoint, Kernel e Runtime: DEFERRED.

## 0.1.0-dev.116 — nome do método de extensões padrão

- `FileUpload::getDefaultAllowedFileExtensions()` vira `defaultAllowedExtensions()`. Só renomeação: a validação
  de extensões do LMS já passa por `aculta_file_uploader.consumer_policy` (`restrictValidators`, consumidor `lms`).
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.115 — derivação de progresso a partir de declarações xAPI (F2, função pura)

- `Xapi\XapiProgressDeriver`: agrupa declarações validadas por `context.registration` (uma tentativa por lançamento) e
  deriva conclusão, sucesso e nota de cada tentativa. Conclusão vem de `result.completion` ou dos verbos `completed`,
  `passed` e `failed`; sucesso vem do `result.success` ou dos verbos `passed` e `failed`; a última declaração com nota
  decide dentro da tentativa, por timestamp.
- Nota na escala de 0 a 100: `scaled` vale `max(0, scaled) × 100`; sem `scaled`, `raw` com `min` e `max` vira percentual
  limitado a 0–100; sem intervalo válido, não há nota.
- Status e escolha da nota vêm de `AttemptRule` (`KEEP_LATEST` ou `KEEP_BEST`), sem reimplementar a regra. Atividade sem
  tentativa concluída com nota fica pendente.

Validação: `XapiProgressDeriverTest` 16/16 no shim unitário local, e `XapiStatementValidatorTest` 20/20 (regressão F1).
Controle de mutação: com conversão de nota errada (×10), 7 testes falham; sem ordenação por timestamp, o teste de
ordenação falha. Sem endpoint, sem lançamento e sem Runtime: DEFERRED.

## 0.1.0-dev.114 — validador de declarações xAPI 2.0 (F1, sem endpoint)

- `Xapi\XapiStatementValidator`: valida uma declaração xAPI 2.0 antes do LRS aceitá-la. Segue o texto da especificação
  (Data) para actor (exatamente um identificador inverso funcional), verb (IRI), object (Activity e StatementRef), result
  (score, success, completion, response, duration, extensions) e context. Id em UUID e timestamp em ISO 8601.
- Escolhas de ingestão, que falham fechado: propriedades desconhecidas são recusadas; grupos, SubStatement e attachments
  são recusados nesta fase; `stored` e `authority` não são aceitos do cliente, porque o LRS os define; tamanho máximo de
  64 KiB por declaração.

Validação: `XapiStatementValidatorTest` 20/20 no shim unitário local. Controle de mutação: sem a checagem de propriedades
desconhecidas, `testRefusesUnknownTopLevelProperty` falha. Endpoint, LRS, lançamento e Runtime DEFERRED.
## 0.1.0-dev.113 — comentário do formato de resposta corrigido

- Comentário em `SubmissionImageHooks` descreve a leitura atual (`fids` e `[fid]`), sem mudança de código.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.112 — remoção do código SCORM (decisão D-66)

Remove o código SCORM commitado em dev.106 a dev.111, por decisão do responsável (D-66: substituir SCORM por xAPI 2.0):

- `src/Scorm/` (manifesto, validador, extrator, mapeador de estado e snapshot do cmi).
- `js/scorm/` (APIs 1.2 e 2004 e a lógica do player).
- Testes unitários SCORM e as bibliotecas `scorm.api12`, `scorm.api2004` e `scorm.player`.

O histórico continua no Git. Nenhum pacote SCORM foi publicado, então não há migração. O registro de origem em `docs/ORIGEM-OPIGNO.md` fica como histórico. Nenhuma funcionalidade de produção muda.

## 0.1.0-dev.111 — lógica do player SCORM (sem DOM e sem endpoint)

- `js/scorm/aculta-scorm-player.js`: ordem dos SCOs, destino de navegação pedido pelo conteúdo (`continue`, `previous`,
  `{target=ID}choice`) e envio do commit por POST same-origin. Liga-se às APIs 1.2 e 2004 pelo evento de cada versão.
  Não grava nada no navegador nem no servidor por conta própria: quem recebe é o endpoint de progresso.
- Biblioteca `aculta_lms/scorm.player`.
- Registro de origem atualizado em `docs/ORIGEM-OPIGNO.md`.

Validação: 21/21 em Node, com as APIs 1.2 e 2004 reais. Árvore, iframe, endpoint e Runtime DEFERRED.

## 0.1.0-dev.110 — sanitização do progresso SCORM (sem endpoint)

- `ScormProgressSnapshot`: recebe o cmi do conteúdo e devolve só o que está no schema de SCORM 1.2 ou 2004, com tipo e limite
  de tamanho (suspend_data 4096 na 1.2 e 64000 na 2004; interações até 200). Elementos fora do schema, como identificação do
  aluno, são descartados. Status são validados pelo `ScormStatusMapper`, que também deriva o estado normalizado.
- Decisão de armazenamento: o resultado vai como dado da resposta do LMS (`getData()`), que já é o mecanismo do módulo, sem
  tabela paralela. Essa ligação ainda não foi feita (ver abaixo).

Validação: `ScormProgressSnapshotTest` 13/13 no shim unitário local. Pendente, DEFERRED: plugin `ActivityAnswer` do SCORM,
endpoint de progresso, rota com autorização, player, e testes no Runtime e no PHPUnit oficial. A API de criação de resposta
do módulo `lms` não pôde ser conferida aqui, porque o pacote contrib não está no ambiente.

## 0.1.0-dev.109 — API SCORM 2004 no navegador (sem endpoint de progresso)

- `js/scorm/aculta-scorm-api-2004.js`: API SCORM 2004 derivada de conceitos do Opigno SCORM 3.2.7 (GPL-2.0-or-later), reescrita
  sem `eval`, sem depuração, sem testes internos e com retornos de erro da especificação. Exportada como `window.API_1484_11`.
  Pedido de navegação (`adl.nav.request`) vai ao player pelo evento `navigation` no Terminate; a troca de SCO é do player.
- Biblioteca `aculta_lms/scorm.api2004`.
- Registro de origem atualizado em `docs/ORIGEM-OPIGNO.md`.

Validação: 34/34 em Node com stubs de `window`, `jQuery` e `Drupal`. Navegador e Runtime DEFERRED.

## 0.1.0-dev.108 — API SCORM 1.2 no navegador (sem endpoint de progresso)

- `js/scorm/aculta-scorm-api-12.js`: API SCORM 1.2 derivada de conceitos do Opigno SCORM 3.2.7 (GPL-2.0-or-later), reescrita
  sem `eval`, sem depuração e com retornos de erro conforme a especificação. Registro de origem em `docs/ORIGEM-OPIGNO.md`.
- Biblioteca `aculta_lms/scorm.api12` (dependências `core/jquery` e `core/drupal`).
- Não grava progresso: o commit dispara `commit12` para o player. O endpoint que grava na tentativa do LMS fica para a fase seguinte.

Validação: 32/32 em Node com stubs de `window`, `jQuery` e `Drupal`. Não executado no navegador nem no Runtime: DEFERRED.

## 0.1.0-dev.107 — normalização de estado SCORM (sem armazenamento)

- `ScormStatusMapper`: função pura que converte lesson_status (SCORM 1.2) e completion_status/success_status (SCORM 2004)
  em conclusão, sucesso e estado normalizado (`not_started`, `in_progress`, `completed`, `passed`, `failed`). Recusa
  valores fora da especificação e versão desconhecida, em vez de assumir padrão.
- Não grava nada. Onde o estado será guardado (tentativa do LMS, decisão do responsável) fica para a fase seguinte.

Validação: `ScormStatusMapperTest` 8/8 no shim unitário local. PHPUnit oficial e Kernel DEFERRED.

## 0.1.0-dev.106 — base segura de pacote SCORM (sem player)

Acrescenta `Scorm\` dentro do aculta_lms, sem submódulo e sem dependência do Drupal:

- `ScormManifestParser`: lê `imsmanifest.xml` de SCORM 1.2 e 2004 com DOMDocument e `LIBXML_NONET`. Recusa DOCTYPE,
  versão desconhecida, item que aponta para recurso inexistente e manifesto sem item lançável.
- `ScormPackageValidator`: valida o ZIP antes da extração. Mede os bytes realmente descompactados por streaming, com
  teto por entrada (64 MiB), total (256 MiB), entradas (5000) e razão de compressão (100). Recusa nomes com `..`,
  caminho absoluto, barra invertida e link simbólico, e exige `imsmanifest.xml` na raiz.
- `ScormPackageExtractor`: extrai para um subdiretório próprio criado dentro de um diretório pai escolhido pelo chamador
  (que deve ser privado, por exemplo sob `private://`). Grava com criação exclusiva, confere o caminho resolvido de cada
  entrada e remove o subdiretório inteiro em qualquer erro.

Origem: implementação própria a partir da especificação SCORM. O pacote `opigno_scorm` 3.2.7 foi lido como referência
de conceito (modelo de itens, recursos e regras de lançamento); nenhum trecho foi copiado. O `XML2Array` do Opigno não
foi portado porque o `DOMDocument` é a API oficial do PHP.

Ainda não existe: player, endpoint de lançamento, integração com atividade do LMS e registro de progresso. Esses itens
dependem de decisão de produto sobre onde guardar o progresso, para não criar armazenamento paralelo.

Validação: `ScormManifestParserTest`, `ScormPackageValidatorTest` e `ScormPackageExtractorTest` somam 18/18 no shim
unitário local. Controles de mutação: sem checagem de symlink, `testRejectsSymlink` falha; sem checagem de DOCTYPE,
`testRejectsDoctype` falha pela mensagem genérica. Não executados: PHPUnit oficial, Kernel e gate Drupal 11+. Status:
DEFERRED.

## 0.1.0-dev.105 — regra pura de tentativa de avaliação (sem ligação ainda)

Acrescenta `Assessment\AttemptRule`, sem dependência do Drupal: status da tentativa (pendente, aprovada ou
reprovada) e escolha do resultado mantido (mais recente ou melhor, com desempate pelo término). A regra reproduz o
conceito de aprovação por tentativa observado no Opigno, sem copiar código.

Esta versão não liga a regra a nenhuma avaliação, matrícula ou progresso do LMS: a aplicação depende de decisão de
produto (fase posterior à fase 1 do plano de independência). Score mínimo fica fora da fase 1.

Validação: `AttemptRuleTest` 11/11 PASS no shim unitário local, com controle de mutação (troca de `>=` por `>`
faz 2 testes falharem). O teste pegou um bug de desempate na política "melhor", corrigido nesta versão. Não
executados: PHPUnit oficial, Kernel e gate Drupal 11+. Status: DEFERRED.

## 0.1.0-dev.104 — link de início do curso sem classes de design

- `CourseLinkHooks` passa `data-portal-ui="lms-start-link"`; o ACULTA420 estiliza o botão.
- Unit LMS 108/508 PASS. Revisão visual no navegador pendente.

RUNTIME STATUS: DEFERRED. Mudança de marcação; visual não validado.

## 0.1.0-dev.103 — formato de data e acesso do arquivo de resposta

- `FileUpload::getFile()` lê `data['file_upload']['fids'][0]`, que é o formato que o `managed_file` do Core grava, e mantém `[fid]` para respostas antigas. Antes lia `[0]`, que nunca existe: o download negava e o formulário não recarregava o arquivo.
- O link do arquivo usa `data-portal-ui="lms-answer-file"` em vez da classe `lms-answer-file`, conforme a fronteira módulo–tema.
- Novos Kernels habilitam `aculta_file_uploader` (o `KernelTestBase` não resolve dependências do `info.yml`).
- Novo Unit `FileUploadAnswerDataTest` 7/9 PASS.

RUNTIME STATUS: DEFERRED. O download real depende de resposta com arquivo no Runtime.

## 0.1.0-dev.102 — lms_file_upload portado para o aculta_lms

- Plugin de resposta `file_upload` portado para `aculta_lms` (mesmo id, diretório `private://lms_file_upload` e file usage, para manter respostas antigas).
- Download com `lms_answer.see_details` e checagem de matrícula atual, rota `aculta_lms.answer_file_download` em `/resposta/{lms_answer}/baixar-arquivo`.
- Política do `aculta_file_uploader` (integração `lms`) aplicada aos validadores.
- Requisito de sistema de arquivos privado portado.
- Dependência `lms_file_upload` removida do info.yml.
- Pendente: composer.json/composer.lock, core.extension (uninstall com ordem explícita e snapshot) e validação no Runtime.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.101 — resposta do LMS aceita envio recente do Studio

- `SubmissionImageHooks` lista envios recentes do aluno (integração `lms`, imagens e documentos), usados só com o envio vazio.
- Escolha checada pelo dono (administrador vê todos) e validada pela política do `aculta_file_uploader`.
- Assume o formato padrão `managed_file` do `file_upload`; o código do contrib `lms_file_upload` não está neste checkout e não foi verificado.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.100 — envio de respostas validado pela política do aculta_file_uploader

- `SubmissionImageHooks` usa `aculta_file_uploader.consumer_policy` (integração `lms`) em vez de `UserUploadPolicy`.
- `LmsSubmissionImageValidator` delega a `isValidImage(file, 'lms')`: uma regra de conteúdo para todos os consumidores. O aculta_image sai do caminho de upload do LMS.
- Dependência `aculta_portal:aculta_file_uploader` declarada.
- Testes Unit e Kernel acompanham a política nova. O Kernel grava `aculta_file_uploader.settings` no setUp.
- Observação: o info.yml estava em 0.1.0-dev.98 e este changelog já tinha a entrada 0.1.0-dev.99, de outra sessão. A versão foi para 0.1.0-dev.100.

RUNTIME STATUS: DEFERRED. Testes não executados (sem vendor/ nem Runtime).

## 0.1.0-dev.99 — resiliencia do dashboard Ganjalab (2026-11-01)

- `AccountCoursesReader` agora trata também o retorno booleano de handlers Group
  mesmo com `return_as_object` solicitado: só `true` autoriza, `false` e valores
  inesperados falham fechado. Resultados `AccessResultInterface` preservam a
  validação e os cache tags normais.
- Adicionados testes para concessão e negação booleanas. A auditoria local
  encontrou um 500 em `/ganjalab` (erro `isAllowed()` sobre `true`); o provider
  do dashboard executado como uid 1 concluiu sem erro após o guard.

## 0.1.0-dev.98 — contratos neutros e painel B2 (2026-11-01)

- `aculta_lms` passa a depender de `aculta_learning_contracts`, módulo sem
  dependência do contrib LMS. `CourseJourneyReaderInterface`, `CourseJourney`,
  `CourseJourneyLesson` e o resumo B2
  (`StudentLearningSummaryReaderInterface` e DTOs) vêm desse módulo.
- O painel do estudante foi separado: o LMS implementa o leitor de resumo e
  fornece dados tipados; a composição e as rotas Ganjalab ficam no Studio. O LMS
  não depende de `aculta_studio`.
- O antigo `LearnerDashboardComposerInterface` foi substituído na B2 pelo
  contrato de resumo tipado; o composer vive no Studio.
- `AccountCoursesReader::getAccountCourses()` passou a usar
  `access('view', $account, TRUE)` antes de `isAllowed()`. Sem o terceiro
  argumento, o método devolve bool e o catálogo/home do Ganjalab falhavam com
  `Call to a member function isAllowed() on true`.
- Sem alteração de entidade, configuração, dado ou escrita. A fonte de verdade
  continua no Group/Drupal LMS.
- **Runtime:** `aculta_learning_contracts` habilitado após snapshot com hash;
  `config:status` sem diferenças; serviços e aliases resolvidos no container;
  jornada e sala 74/74 em 320–1440 px. Nenhum dado de curso, matrícula,
  resposta ou progresso foi alterado.

## 0.1.0-dev.97 — contrato da jornada em camada neutra (2026-11-01)

- Correção da fatia dev.96: `CourseJourneyReaderInterface`,
  `CourseJourney` e `CourseJourneyLesson` foram movidos de
  `Drupal\aculta_studio\Learning` para
  `Drupal\aculta_portal\Contract\Learning`. `aculta_lms` depende de
  `aculta_studio`; manter o contrato no consumidor fazia o adaptador importar o
  namespace de quem já depende dele.
- `LearningDashboardManager`, `CourseRoomBuilder` e `LearningRoomHooks` apontam
  para o namespace neutro; o alias por interface em `aculta_lms.services.yml`
  também. Nenhuma rota, entidade, configuração ou dado mudou.
- Testes e documentação de dependências atualizados. A fonte de verdade continua
  no Group/Drupal LMS.

## 0.1.0-dev.96 — jornada do curso sob contrato ACULTA (2026-11-01)

- `LearningDashboardManager` passa a implementar
  `Drupal\aculta_portal\Contract\Learning\CourseJourneyReaderInterface`; `courseJourney()`
  devolve `CourseJourney` com lições `CourseJourneyLesson`, e
  `getCourseJourney(string $course_id, AccountInterface $account)` resolve o
  Group internamente e devolve `NULL` quando o ID não é um curso.
- `CourseRoomBuilder` e `LearningRoomHooks` consomem o contrato; a resolução de
  acesso de entrada fica centralizada no adaptador. O formulário de compra
  continua decidido por `administer members`.
- Aliases `aculta_lms.course_journey_reader` e por interface no
  `aculta_lms.services.yml`. A fonte de verdade continua no Group/Drupal LMS;
  nenhum dado, rota, configuração ou escrita mudou.
- Inventário completo e fases futuras em
  `docs/modules/LMS-CONTRIB-DEPENDENCY-INVENTORY.md`.
- Validação: PHPUnit Unit LMS 92/482 e Studio 60/586 PASS; PHP lint sem erros;
  PHPCS sem erros nos arquivos alterados (4 avisos preexistentes de comprimento
  de linha). `AcultaLmsInstallTest` mantém falha preexistente de autowiring de
  `CourseLinkHooks`/`TrainingManager` no harness, confirmada na baseline.
- Smoke de navegador Firefox ESR/X11 em
  `tests/browser/aculta-ganjalab/learning-experience.mjs`, 390 px: 29/29
  verificações OK, incluindo início, jornada com 7 aulas e estados, sala com
  formulário nativo, modo escuro e foco. A matriz completa de larguras não foi
  repetida nesta fatia. A suíte apenas autenticou e leu páginas; nenhum dado ou
  configuração do Runtime foi alterado.

## 0.1.0-dev.94 — feedback de educador distingue aluno e educador

- `EducatorFeedbackPolicy` (`aculta_lms.educator_feedback_policy`) define educador
  como autor diferente do dono da resposta e com a permissão `grade students` no
  curso. `EducatorFeedbackEventHooks` e `EducatorFeedbackAuthorCardHooks` usam o
  mesmo critério.
- `lms_answer_comment` aceita comentário do aluno dono da resposta; esses
  comentários não recebem o rótulo "Educador(a)" nem disparam a notificação de
  feedback, mas continuam com cartão de autor (nome + acervo quando não há perfil).
- O roster usa `AuthorCardBuilder::buildFallback()`: quem não tem perfil público
  continua na turma com nome de exibição + avatar do acervo.
- Validação: PHPUnit Unit 88/453 PASS (8 depreciações PHPUnit); Kernel do roster
  1/33 PASS (37 depreciações upstream); gates Portal (1112), foto, SDC, design
  foundations, browser validators, Foundation e shell-contract PASS. RUNTIME
  STATUS: DEFERRED para a verificação visual do feedback (o Runtime não tem turma).

## 0.1.0-dev.93 — avatar padrão nas turmas e no feedback do educador (Fase 4)

- Roster da turma e participantes da sala mostram cartão de autor (`profile_compact`, tamanho `sm`)
  com a mesma regra de avatar do site. Sem identidade pública, o nome de exibição continua na lista.
- Feedback do educador em resposta (`lms_answer_comment`) mostra o cartão com o papel "Educador(a)".
- Validação: PHP lint PASS. PHPUnit e Runtime não executados (sem `vendor/`). RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.92 — dependência declarada do provedor de pagamento

- `aculta_lms` declara dependência direta de `aculta_portal:aculta_pagamentos_mercadopago`; nenhum código do LMS mudou. Os quatro módulos LMS relacionados verificados não usam APIs do MP.
- Unit: 87 testes / 447 assertions PASS (8 depreciações PHPUnit). Kernel MariaDB dedicado: 29 testes / 836 assertions PASS, com 98 depreciações Core/contrib. O harness usa classes reais MP e os contratos neutros.

## 0.1.0-dev.91 — fechar setup dos Kernels LMS com contratos reais — 2026-10-26

- Os harnesses Kernel que habilitam ou instalam `aculta_lms` agora compartilham
  definições de teste das classes reais necessárias de Portal, Messenger e
  Studio. O recorte não instala configuração alheia ao cenário nem adiciona
  serviços fictícios ao produto. `AcultaLmsInstallTest` também verifica o
  serviço real `CheckoutSettings`, o tipo padrão `curso` e o controller
  Ganjalab referenciado pela rota.
- Kernel agregado: 29 testes / 835 assertions PASS no MariaDB dedicado; uma
  depreciação vem de `webform_score` contrib. PHPCS Drupal/DrupalPractice e
  PHP lint dos arquivos alterados PASS. Isso remove os erros de setup dos
  harnesses LMS, mas não equivale a instalar a closure completa Portal/
  Messenger/Studio com toda sua configuração. Runtime não foi alterado.

## 0.1.0-dev.90 — Kernel focal do painel de turma com contratos reais — 2026-10-26

- `ClassDashboardKernelTest` não habilita a instalação inteira do Portal, pois isso carregava configuração e serviços de Commerce/Domain não usados pelo caso. O fixture Kernel mantém Group, LMS e `lms_classes` reais, registra os namespaces de código reais e instancia `LearningDashboardManager`, `ClassDashboardAccessCheck`, `LearningDashboardController`, prazo e compositor de Studio reais. Não há serviço fictício nem alteração no produto.
- O teste passou: 1 teste / 48 assertions, verificando educador, estudante, vínculo turma→curso, escopo de membros, cache tags/contextos e invalidação ao remover relacionamento. PHPCS e PHP lint PASS. 39 depreciações foram reportadas por Core/contrib/Group/LMS.
- Isso fecha a falha de setup deste caso focal, sem validar a compilação/inicialização limpa de toda a cadeia de serviços Portal/Messenger/Studio. Kernel agregado reexecutado: 29 testes / 98 assertions, 22 erros de setup; todos os 22 harnesses que instalam `aculta_lms` sem Portal falham pelo mesmo serviço obrigatório `aculta_portal.checkout_settings`. Nenhuma assertion funcional falhou nos testes que executaram. Runtime não foi alterado.

## 0.1.0-dev.89 — regressão da suíte Kernel agregada — 2026-10-26

- Unit LMS: 87 testes / 447 assertions PASS (8 depreciações PHPUnit).
- Kernel LMS agregado: 29 testes, 50 assertions e 23 erros de setup. Os harnesses que compilam serviços `aculta_lms` sem a dependência obrigatória Portal falham por `aculta_portal.checkout_settings` ausente; não se trata de assertions funcionais. Alguns focais executados anteriormente continuam registrados abaixo. A cadeia de instalação limpa Portal/Messenger/Studio permanece sem verificação agregada.
- Reproduzido isoladamente no worktree `ClassDashboardKernelTest`: 1 erro/0 assertions na compilação do container pela mesma ausência de `aculta_portal.checkout_settings`; a falha é do harness incompleto, sem evidência de defeito funcional no dashboard.

## 0.1.0-dev.88 — publicar avisos pelo kernel Messenger (2026-10-24)

- O producer de eventos LMS foi movido para o módulo dono dos cursos e
  workflows; consumer, persistência, preferências e entrega permanecem no
  `aculta_messenger`.
- O aviso no site é obrigatório; e-mail segue off/direto/digest por preferência
  e política. Unit integrada e Kernel LMS executados constam no changelog do
  Messenger. Runtime não homologado.

## 0.1.0-dev.87 — remoção de aliases temporários em desenvolvimento (2026-10-24)

- Removidos aliases temporários /minha-aprendizagem, /ganjalab420 e /studio/ganjalab420. Única entrada estudantil canônica: /studio/ganjalab. Nenhuma alteração de matrícula, curso ou certificado. Gate PHPUnit/Runtime pendente.
- Regra transversal: `AGENTS.md` e `scripts/validate-no-temporary-route-aliases.php`.

# Changelog — aculta_lms

## 0.1.0-dev.86 — tipo de pedido de curso lido da configuração do Portal (P-CART E) — 2026-10-22

- `CourseOrderEnrollmentManager::isCourseOrder()`, `EnrollmentFormHooks`, `CourseOrderHooks` e `CourseEnrollmentPurchaseForm` usam `CheckoutSettings` em vez do literal `curso`. Novas compras usam o tipo configurado; pedidos `curso` históricos seguem reconhecidos. Padrão sem mudança (`curso`). O tipo de item e a variação `curso` não mudam. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.85 — provider de checkout do Portal (P-CART C) — 2026-10-22

- `LmsCheckoutProvider` declara ao Portal o contexto LMS (tipo de pedido `curso`, checkout comum e flow nativo). Somente leitura; não altera matrícula, curso ou compra. RUNTIME STATUS: DEFERRED.
- Alinhamento: `aculta_lms.info.yml` estava em dev.80 enquanto o changelog já registrava dev.84.

## 0.1.0-dev.84 — rodar Kernel e Unit do LMS e registrar o resultado medido — 2026-10-22

Tarefa: "Rodar os Kernels e unitários dos módulos LMS e Forum alterados; registrar testes/asserções/depreciações em cada CHANGELOG; confirmar git status limpo e listar os commits da sequência."

- Documento apenas; nenhuma alteração de código, configuração ou Runtime.
- Unit: `tests/src/Unit` — 74 testes, 408 asserções, OK com 8 depreciações do
  PHPUnit.
- Kernel: `tests/src/Kernel` — 29 testes, 832 asserções, OK com 93
  depreciações conhecidas, todas de código contrib e já presentes antes desta
  sequência: anotações de plugin do Group (relação, argumento e acesso de
  Views), `FieldType`/`EntityType` do Webform, `fraction` e `entity_print`,
  além do retorno implicitamente anulável de
  `webform_score_entity_field_access()` e do hook de requisitos legado.
- Comandos: a partir de `web/core`, com `secrets/simpletest-db.env` carregado
  no ambiente, `../../vendor/bin/phpunit -c phpunit.xml.dist
  ../modules/custom/aculta_portal/modules/aculta_lms/tests/src/{Unit,Kernel}`;
  banco local `aculta_simpletest`; o Runtime de teste não foi tocado.
- Resultado anteriormente registrado por engano no `aculta_forum_lms`
  dev.42; este é o registro correto do módulo LMS.

## 0.1.0-dev.83 — regressão Kernel da política central de upload — 2026-10-22

Tarefa: "Migrar anexos LMS para a política central `aculta_portal.user_upload_policy`, com aba própria no painel de uploads. Política efetiva inalterada. Kernel de regressão."

- A migração já estava no código: o `aculta_portal` 0.4.0-dev.252 criou o consumidor `lms` e a aba "LMS" do painel de uploads, e o `aculta_lms` 0.1.0-dev.80 passou a aplicar `restrictValidators(..., 'lms')` em `SubmissionImageHooks`. Esta entrega fecha a verificação e a documentação; a única alteração de produção corrige o texto de ajuda do campo.
- Correção no texto de ajuda: desde `0361ae05` o hook montava a lista com o teto inteiro da política (`allowedExtensions('lms')`) para não ler `FileExtension` fora da política, então uma atividade mais restrita exibia extensões que ela não aceita — e o `SubmissionImageHooksTest` ficou vermelho. O novo `UserUploadPolicy::acceptedExtensions()` formata a interseção já restrita; o hook exibe essa lista e o gate `validate-aculta-image` segue verde, porque a leitura fica no dono da política.
- Superfície confirmada: o único upload de usuário do LMS é o campo File Upload da resposta (`form_lms_answer_form_alter`). `aculta_lms`, `aculta_lms_resources`, `aculta_lms_messenger` e `aculta_forum_lms` não têm campos de arquivo próprios; os uploads do editor H5P são administrativos e ficam fora da política de usuário.
- Novo Kernel `SubmissionUploadPolicyKernelTest` (5 testes, 39 asserções, PASS): herança da aba LMS sobre o global sem mudança de política efetiva (9 extensões, 10 MB, texto de ajuda limitado à interseção); atividade que não amplia extensões (`exe`/`txt` removidos) nem eleva o teto de tamanho; aba LMS salva que restringe para `pdf docx` e 5 MB sem tocar na política global; formulário sem upload intacto; validadores nativos e validador de imagem preservados quando o serviço da política não existe.
- Política efetiva inalterada: nenhuma configuração foi salva no Runtime de teste; a aba LMS continua herdando o global. O Kernel roda sem habilitar `aculta_portal`/`aculta_lms`, com os serviços reais do `aculta_image`, e sem depreciações.
- README corrigido: o trecho que descrevia o validador como aplicável só a extensões raster e a delegação de arquivos não raster à validação nativa estava desatualizado desde a dev.80.

## 0.1.0-dev.82 — smoke autenticado da navegação por curso — 2026-10-22

Tarefa: "Pendência de smoke autenticado do aculta_lms (CHANGELOG.md linha 51): executar o smoke da navegação por curso com conta de Educador e de estudante."

- Smoke HTTP no Runtime de teste com duas contas descartáveis: educador (papel global `lms_teacher` + matrícula no curso 1) e estudante (matrícula no curso 1 e na turma criada para o smoke). Sem senha no repositório; sessão obtida por login único do Drush.
- A navegação por curso exige aceite vigente dos termos (`aculta_portal.access.protected_route:course`). Contas novas recebiam **403** em `/course/1/start`; após registrar o aceite pelo serviço `aculta_portal.aculta_user_agreement_policy`, o cenário seguiu. Isso é pré-requisito de conta, não defeito de rota.
- Resultados por cenário (esperado → obtido):
  - `GET https://cursos.aculta.toca.net.br/group/1` — educador e estudante: **200** → **200** ("Introdução ao Antiproibicionismo | Cursos").
  - `GET https://cursos.aculta.toca.net.br/course/1/start` — ambos: **302** para a atividade → **302** para `/curso/.../questao-de-revisao`, que responde **200**.
  - `GET https://conta.aculta.toca.net.br/minha-aprendizagem` — ambos: **200** com o curso, "Acessar curso" e link para `/course/1/start` → **200** ("Aprendizagem | Conta ACULTA").
  - `GET /painel-administrativo/lms/curso/1/turma/{turma}/painel`, `.../participantes`, `.../atividade/1/prazo` e `.../certificados/emitir` — educador: **200** → **200** (painel com "Curso: Introdução ao Antiproibicionismo", 1 estudante, 14 atividades sem prazo e link da lista de participantes); estudante: **403** → **403** ("Acesso negado").
- Defeito corrigido no caminho do painel: a célula de prazo era um render array de `Link` sem a chave `data`, e o preprocess de tabela o interpretava como atributos, causando 500 (`Unexpected type for $value (Drupal\Core\Url)`). A célula agora é `['data' => Link::...->toRenderable()]`.
- Defeito corrigido no formulário de prazo: `ClassActivityDeadlineAccessCheck::access()` declarava `$routeCourse`, mas o parâmetro da rota é `{course}`; o formulário respondia 500 por não resolver o argumento. O parâmetro passou a se chamar `$course` e a variável interna do curso da turma, `$classCourse`.
- Testes: Unit focal do módulo 5 testes / 18 asserções PASS (`LearningDashboardControllerTest`, `ClassActivityDeadlineManagerTest`, `ClassActivityDeadlineFormTest`); PHPCS nos arquivos alterados sem erro.
- Cenário removido ao final (turma, relações de turma e de matrícula, situações de curso e de aula, linhas do ledger de aceite e as duas contas). Runtime de teste voltou à linha de base: 1 situação de curso no curso 1, 2 situações de aula, 2 respostas e 0 turmas.

## 0.1.0-dev.81 — download de anexo negado a conta bloqueada — 2026-10-22

- Verificação HTTP no Runtime de teste da rota `/resposta/{lms_answer}/baixar-arquivo`, com cenário descartável (tipo de atividade `t14_upload`, aula, curso, matrícula e resposta com arquivo privado).
- Conta válida e dona da resposta: `GET` devolve **200**, `Content-Disposition: attachment; filename="t14-<nonce>.txt"` e o conteúdo de 39 bytes.
- Conta autenticada sem matrícula: **403** (`UploadedAnswerAccessCheck` → `see_details` negado).
- Conta bloqueada: **404**. O core apaga todas as sessões da conta ao bloquear (`User::postSave`), então a requisição passa a anônima, e o site responde 404 a anônimo nessa rota — mesmo resultado do controle anônimo. Mesmo uma sessão sobrevivente seria recusada pelo provider `Cookie`, que só aceita `users_field_data.status = 1`. O ramo de conta inativa do `UploadedAnswerAccessCheck` (403) continua coberto pelo Kernel `AttemptArchiveLifecycleTest` (dono bloqueado).
- Cenário removido ao final (resposta, arquivo privado, matrícula, curso, aula, atividade, tipo e campo). O Runtime voltou à linha de base: 1 curso, 10 usuários, 2 respostas, 0 arquivos em `private://lms_file_upload`, tipos `no_answer` e `true_false`.

## 0.1.0-dev.80 — respostas com arquivo sob a política central de upload

- `SubmissionImageHooks` recebe `aculta_portal.user_upload_policy` (serviço `aculta_lms.submission_image_hooks`) e aplica `restrictValidators(..., 'lms')` ao campo de envio das atividades File Upload. Educadores continuam escolhendo as extensões de cada atividade, mas só dentro da política LMS; limite de tamanho só diminui.
- O validador de imagem `AcultaLmsSubmissionImage` continua aplicado às imagens rasters.
- Testes: `SubmissionImageHooksTest` (Unit, 3 testes, 10 asserções) cobre o acréscimo do validador de imagem, a impossibilidade de ampliar extensões ou tamanho e a não alteração de formulários sem upload. Os testes Kernel de upload no Portal passam (ver CHANGELOG do aculta_portal 0.4.0-dev.252).
- Correção da própria entrega: a primeira versão injetava `UserUploadPolicy` pelo tipo da classe e quebrava a montagem do container (`drush cr` falhava com "no such service"). Agora o serviço usa o FQCN do hook com `@?aculta_portal.user_upload_policy`, como os demais hooks do LMS. Sem a política, o hook mantém os validadores nativos. Kernel `AttemptArchiveLifecycleTest` (que monta o container) voltou a passar.
- Runtime de teste: sem alteração de configuração ou de schema; o consumidor `lms` herda a política global até que alguém salve a aba. `drush cr` OK; verificado por leitura no container: a atividade pedindo `png pdf exe txt` recebe `png pdf`, limite de 10 MB e o validador de imagem.
- Smoke HTTP autenticado no Runtime (conta de teste `teste.comum`, login único gerado por Drush, sem senha no repositório): a atividade File Upload com `png exe pdf txt` recebe, pelo formulário real, a recusa do `.exe` com a mensagem "Apenas arquivos com as seguintes extensões são permitidos: png pdf" (teto da política LMS), e o PNG é aceito e gravado na resposta (`private://lms_file_upload`).
- Cenário criado só para o smoke (tipo `smoke_upload`, campo `field_file_extensions`, aula, curso e matrícula) e removido ao final, com as respostas e arquivos de teste. O Runtime voltou aos tipos de atividade originais.
- Correção da revisão visual: o texto de ajuda do campo mostrava as extensões da atividade ("png exe pdf txt") e não o teto. O hook agora reescreve a ajuda com a lista efetiva ("Envie um arquivo. Extensões permitidas: png pdf."). `SubmissionImageHooks` recebe a tradução por injeção (`string_translation`). Unit 74/408 OK.
- Revisão visual com sessão autenticada no Firefox (X11, `tests/browser/aculta-lms/answer-upload-visual.mjs`): 8/8 verificações OK, incluindo o `.exe` recusado com "Somente arquivos com as seguintes extensões são permitidos: png, pdf", o PNG aceito, sem erros de JavaScript e sem rolagem horizontal. O celular foi verificado em 390 px, com viewport emulado pelo BiDi (`browsingContext.setViewport`), com `innerWidth` conferido antes da captura. Resultado final: 9/9 OK.

## 0.1.0-dev.79 (situação por aluno para a série, sob o aceite único)

- `StudentStatusSnapshotEvent` (`aculta_lms.student_status_snapshot`) emitido no cron com uma linha por curso e aluno. Só entram alunos com aceite único vigente (`aculta_user_agreement_terms`). Um aluno em duas turmas do mesmo curso conta uma vez.
- `StudentStatusSnapshotCronHooks`: sem o ledger instalado, não emite nada (`@?aculta_user.aculta_user_agreement_ledger`).
- `countClassStatuses($class, $course, TRUE)` devolve `by_student`. O painel não pede esse dado; o comportamento não mudou.
- Dependência declarada em `aculta_user`.
- Testes: Unit 71 (sem mudança de resultado). Sem turmas no Runtime de teste; o caminho com linhas não foi observado lá.

## 0.1.0-dev.78 — instantâneo diário da situação por turma para estatísticas — 2026-10-21

- `ClassStatusSnapshotCronHooks` emite `ClassStatusSnapshotEvent` (`aculta_lms.class_status_snapshot`) no cron, com uma linha por turma e situação (alunos, não iniciados, em andamento, aguardando avaliação, aprovados, reprovados). Só contagens agregadas; nenhum aluno é identificado.
- `LearningDashboardManager::countClassStatuses()`: contagem extraída do painel da turma, sem checagem de acesso. O painel mantém acesso e cache; os valores não mudam.
- Alias `Drupal\aculta_lms\LearningDashboardManager` para injeção por tipo.
- Decisão (2026-10-21): série diária por turma. Série diária **por aluno** não foi criada: exigiria revisão LGPD antes.
- Testes: Unit 71 e Kernel `ClassDashboardKernelTest` (1 caso) passam, com deprecações já existentes. No Runtime de teste não há turmas (`lms_class` = 0), então o caminho com linhas está coberto só pela unit test do assinante em `aculta_statistics`.

## 0.1.0-dev.77 — controlar a lista de participantes por turma — 2026-10-21

- Educadores podem exibir ou ocultar, por turma, uma lista limitada aos nomes
  de estudantes ativos matriculados naquela mesma turma; o padrão é oculto.
  Formulário e rota validam o curso/turma e a permissão contextual. O Kernel
  cobre padrão oculto, isolamento entre turmas e exclusão de contas bloqueadas:
  1 teste / 28 assertions PASS (36 depreciações upstream de Core/contrib).
- `AcultaLmsInstallTest` confere a rota administrativa (1/17, com 3
  depreciações upstream); Unit LMS: 71/397 PASS (8 depreciações PHPUnit);
  PHPCS Drupal/DrupalPractice PASS. Runtime de teste recebeu somente as duas
  configurações do campo via importação parcial revisada; cache rebuild passou.
  Smoke autenticado e revisão visual seguem pendentes.

## 0.1.0-dev.76 — organizar a administração pelo curso — 2026-10-21

- Painel da turma e configuração de prazo agora ficam abaixo de
  `/painel-administrativo/lms/curso/{course}`; acesso confere se a turma e a
  atividade pertencem ao curso indicado. A navegação remove atalhos globais de
  aulas, atividades e turmas, mantendo o curso como entrada administrativa.
- Unit: 71 testes / 394 assertions PASS. Kernel MariaDB: 23 testes / 764
  assertions PASS; `AcultaLmsInstallTest` focal: 4/44 (93 depreciações upstream). PHP CLI 8.4.26; rebuild no Runtime de teste confirmou os caminhos course-first e a navegação por curso; smoke autenticado pendente.

## 0.1.0-dev.75 — cobrir hooks Commerce de pedido — 2026-10-21

- `AcultaLmsInstallTest` agora confirma o registro dos hooks de insert e update
  do pedido, que chamam a reconciliação de memberships compradas. O teste focal
  passou com 4 testes / 42 assertions; Kernel LMS/Messenger: 24 testes / 772
  assertions PASS, com 95 depreciações upstream.

## 0.1.0-dev.74 — reconciliar memberships com itens atuais do pedido — 2026-10-21

- A sincronização de pedido pago agora reconcilia as memberships marcadas com
  sua origem: mantém as turmas ainda compradas; revoga turma removida do pedido
  e transfere a origem se existir outra compra ativa. Matrículas manuais sem
  origem continuam preservadas. `CourseOrderEnrollmentManagerTest`: 9/47;
  Unit LMS/Messenger: 89/482 PASS. Kernel LMS/Messenger: 24/770 PASS (95
  depreciações upstream). PHPCS Drupal/DrupalPractice PASS.

## 0.1.0-dev.73 — negar download após remoção do CourseStatus — 2026-10-21

- O Kernel agora reproduz o intervalo entre a exclusão do `CourseStatus` e a
  limpeza enfileirada do `LessonStatus`/resposta; o download privado falha
  fechado sem erro 500. `AttemptArchiveLifecycleTest`: 10 testes / 409
  assertions PASS; Kernel LMS/Messenger: 24/770 PASS, com 95 depreciações
  upstream. PHPCS Drupal/DrupalPractice PASS no arquivo da suíte.

## 0.1.0-dev.72 — preservar matrícula após outra compra ativa — 2026-10-21

- `CourseOrderEnrollmentManagerTest` agora cobre a sincronização de um pedido
  integralmente reembolsado quando há outra compra ativa para a mesma pessoa,
  turma e curso: a membership permanece e sua origem muda para o pedido válido.
  Focal: 7 testes / 35 assertions PASS; Unit LMS/Messenger: 87/470 PASS.
  Kernel LMS/Messenger: 23/730 PASS (95 depreciações upstream do Core/contrib).

## 0.1.0-dev.71 — proteger membership manual com compra ativa — 2026-10-21

- A remoção pelo educador agora consulta pedidos ativos da mesma turma mesmo
  quando a membership foi criada manualmente antes da compra. A conferência
  valida estudante, produto associado ao curso, estado do pedido e entitlement;
  sem compra ativa, a matrícula manual segue removível após revalidação em cada
  request. `EnrollmentFormHooksTest`: 7/49; `CourseOrderEnrollmentManagerTest`:
  6/25; Unit LMS/Messenger: 86/460; `AcultaLmsInstallTest`: 4/40;
  Kernel LMS/Messenger: 23/730 (95 depreciações de Core/contrib). Runtime
  `cursos`: sem updates; cache rebuild e resolução do manager/hook passaram,
  com warning preexistente de cópia de assets H5P. Nenhuma configuração foi
  importada.

## 0.1.0-dev.70 — invalidar acesso armazenado ao remover vínculo da turma — 2026-10-21

- O resultado positivo do painel de educadores agora depende das listas Group
  de relações da turma e do curso. A remoção da relação curso→turma invalida
  imediatamente o grant armazenado. `ClassDashboardKernelTest`: 1/46; a
  regressão LMS/Messenger: 23/728 assertions PASS, com depreciações de
  Core/contrib. Runtime `cursos`: sem updates pendentes; cache rebuild e
  resolução do manager do painel passaram, com warning preexistente de cópia de
  assets H5P. Nenhuma configuração foi importada.

## 0.1.0-dev.69 — invalidar negação do painel de turma sem curso — 2026-10-21

- O controle de acesso do painel de educadores também invalida a negativa
  quando a turma recebe relação com o curso. A cobertura Kernel exercita ambas
  as negativas armazenadas e confirma invalidação pela cache tag Group.
  `ClassActivityDeadlineTest`: 1/52; regressão Kernel LMS/Messenger: 23/725
  PASS (95 depreciações upstream). Runtime `cursos`: sem updates pendentes;
  cache rebuild e resolução do serviço do painel passaram, com warning
  preexistente de cópia de assets H5P; nenhuma configuração foi importada.

## 0.1.0-dev.68 — invalidar negativa de acesso quando a turma é vinculada — 2026-10-21

- O access checker de prazo agora inclui a lista de relações Group da turma na
  cacheabilidade da negação sem curso pai. Também depende do curso quando a
  atividade ainda não pertence ao seu conteúdo. O Kernel prova que criar o
  vínculo invalida a negativa armazenada: `ClassActivityDeadlineTest` 1/49;
  regressão Kernel LMS/Messenger 23/722 PASS (95 depreciações upstream). No
  Runtime `cursos`, Drush confirmou ausência de updates; cache rebuild e
  resolução do access checker passaram, com warning preexistente de cópia de
  assets H5P. Nenhuma configuração foi importada.

## 0.1.0-dev.67 — exibir prazo no formulário de resposta — 2026-10-21

- Corrige a exibição do prazo para estudantes: o hook injeta o serviço de
  tradução e usa o namespace instalado de `DateTimeItemInterface`; a rota
  positiva agora mostra o prazo da turma com o fuso da conta. Kernel integrado
  valida entidades LMS/Group reais e autowiring do hook: 1 teste / 46
  assertions. Unit LMS/Messenger: 84/445; Kernel combinado: 23/719, com 95
  depreciações upstream; PHPCS Drupal/DrupalPractice PASS. No Runtime `cursos`,
  `drush updatedb:status` não encontrou updates, cache rebuild passou e o
  container resolveu o serviço do hook; houve apenas warning preexistente da
  cópia de assets H5P. Nenhuma configuração foi importada.

## 0.1.0-dev.66 — cobrir resposta aberta sem atividade — 2026-10-21

- `FreeTextReviewFeedback` mantém o campo de resposta sem os controles de
  feedback dependentes da atividade removida; a tela de correção informa que a
  atividade não está disponível e o score formativo falha fechado em zero.
  Unit focal: 4/20; combinado LMS/Messenger: 84/445, com 8 depreciações
  PHPUnit. PHPCS Drupal/DrupalPractice PASS.

## 0.1.0-dev.65 — tolerar atividade removida em respostas abertas — 2026-10-21

- O plugin de resposta aberta não chama `Answer::getActivity()` quando a
  referência foi removida na limpeza assíncrona. Score formativo retorna zero,
  o formulário mantém a resposta sem o widget de feedback e a avaliação mostra
  que a atividade não está disponível. Unit focal: 3/15; combinada LMS/
  Messenger: 83/440, com 8 depreciações PHPUnit.

## 0.1.0-dev.139 — eventos usam respostas próprias do ACULTA

- `AnswerLifecycleEventHooks` publica submissão e avaliação a partir de `aculta_lms_answer`; respostas P2 não armazenam o payload, portanto a publicação usa apenas identidade e estado persistido.
- Sem leitura nem preservação adicional do payload de respostas legado.

## 0.1.0-dev.140 — vínculo de curso usa Group

- Publicação de matrícula de turma valida o grupo de curso pelo contrato `GroupInterface`, sem depender de `CourseInterface` do LMS contrib.
- O relacionamento Group `lms_classes` continua sendo a associação existente entre turma e curso.

## 0.1.0-dev.141 — notificações leem conteúdo próprio

- Eventos de liberação e revalidação do Messenger usam `aculta_lms_lesson` e `aculta_lms_activity`; atividade fica disponível quando a aula ACULTA pai está publicada, pois o modelo P2 não define publicação independente.
- O vínculo para curso e as matrículas continuam usando Group. Nenhum payload ou status legado foi migrado.

## 0.1.0-dev.142 — acesso ao painel usa Group

- `ClassDashboardAccessCheck` valida curso e turma por `GroupInterface`, alinhado ao retorno ACULTA do resolvedor de turma.

## 0.1.0-dev.143 — matrícula comercial usa relações Group

- O entitlement Commerce valida cursos com `GroupInterface` e resolve turmas pela relação `lms_classes`, sem `CourseInterface` ou `ClassHelper`.

## 0.1.0-dev.144 — formulários de curso usam relações Group

- Compra de curso e configuração de visibilidade do roster recebem curso como `GroupInterface`; turmas elegíveis vêm de `lms_classes` por Group API.

## 0.1.0-dev.145 — avisos de avaliação usam resposta própria

- Eligibility, feedback educador e destinatários de submissão/avaliação resolvem `aculta_lms_answer` → atividade → aula → Group.
- O payload não existe na entidade P2; os avisos apontam aos painéis Ganjalab até existir uma tela própria de resposta.

## 0.1.0-dev.146 — prazos e painel leem entidades próprias

- Prazo por turma, acesso e formulário usam `aculta_lms_activity` e `aculta_lms_lesson`; painel docente enumera atividades e aulas próprias e agrega `aculta_lms_progress`.
- Testes Kernel de prazo e painel agora exercitam atividade, aula e progresso ACULTA; as entidades LMS permanecem no harness apenas para os bundles e relacionamentos Group legados.

## 0.1.0-dev.147 — Recursos usa acesso Group

- O índice de conteúdo e os links de materiais verificam acesso estudantil por `take course` em Group, sem importar `CourseInterface` do LMS.
- Unit de `aculta_lms_resources` 13/66 PASS; Kernel do índice de acesso 5/259 PASS (53 depreciações upstream).

## 0.1.0-dev.148 — avisos resolvem entidades próprias

- Elegibilidade, destinatários e links de avisos usam resposta, aula e atividade ACULTA e Group; URLs de curso usam a rota canônica de Group.
- LMS Unit 227/747 PASS; Kernel de prazos/painel 2/87 PASS; Messenger LMS Unit 48/470 e Kernel 4/52 PASS.

## 0.1.0-dev.149 — encerramento dos adaptadores temporários P1

- Os contratos CourseProgress, CourseLesson, CourseActivity e CourseAnswer já
  são servidos por leitores de entidades ACULTA; os quatro adaptadores do LMS
  contrib e seus serviços/testes temporários foram removidos.
- Dados legados não foram migrados. Respostas sem payload na entidade P2 e
  outros consumidores contrib continuam pendentes de implementação antes do
  corte P5.
- O Kernel do painel usa agora aulas/progresso próprios; sem progresso
  individual por aula, exibe zero aulas concluídas em vez de inferi-las do LMS.

## 0.1.0-dev.150 — matrícula usa Group sem helper de LMS Classes

- `EnrollmentFormHooks` valida cursos e turmas como `GroupInterface` e lê as
  relações do curso diretamente, removendo `CourseInterface` e `ClassHelper`.
- O ID de plugin de relação `lms_classes` permanece estável por ora para
  preservar a configuração e os vínculos existentes; o provedor ACULTA ainda
  precisa substituí-lo antes da remoção do submódulo.
- `EnrollmentFormHooksTest`: 9 testes / 63 asserções PASS; Unit completa:
  222 testes / 735 asserções PASS (1 depreciação PHPUnit).

## 0.1.0-dev.151 — destino canônico Group na lista de cursos

- `AccountCoursesReader` usa `entity.group.canonical` para cursos em andamento
  e concluídos; cursos em avaliação continuam sem URL de retomada.
- `AccountCoursesReaderTest`: 3 testes / 27 asserções PASS, cobrindo destino e
  parâmetros do Group.

## 0.1.0-dev.152 — jornada sem destinos de resposta contrib

- `LearningDashboardManager` aponta cursos para `entity.group.canonical`.
  Cada aula da jornada fica sem URL até existir uma rota ACULTA para conteúdo e
  resposta; nenhum destino `lms.*` é inventado.
- `CourseJourneyManagerTest` verifica rota/ID canônicos e URL de aula nula.

## 0.1.0-dev.164 — instala Comment antes do módulo no Kernel de tentativas

- Corrige a ordem do harness Kernel `AttemptArchiveLifecycleTest`: instala primeiro a configuração do Core Comment, incluindo o armazenamento `comment_body`, antes de instalar `aculta_lms`.
- Verificação focal conjunta: `AcultaLmsInstallTest` + `AttemptArchiveLifecycleTest`, 16 testes/603 asserções PASS; 57 depreciações Core/contrib.
- Unit completa: 236 testes/829 asserções PASS, 1 depreciação PHPUnit.

## 0.1.0-dev.176 — validação de isolamento e limites do Runtime

- O segundo aluno sintético autenticado recebeu HTTP 200 somente no próprio
  formulário, HTTP 403 ao tentar baixar a resposta de outro aluno e não
  encontrou uma rota de listagem de respostas (HTTP 404). Nenhum dado da
  primeira resposta apareceu nas respostas.
- A revisão docente permanece DEFERRED: o painel existente expõe métricas e
  prazos, mas o módulo não possui rota ou formulário para revisar, avaliar e
  publicar feedback de respostas.
- `ClassActivityDeadlineTest` foi ajustado para não habilitar `lms` nem
  `lms_classes` e instalar as entidades ACULTA. A execução ainda falha antes
  dos asserts com `The "entity:group_relationship:lms_course-group_membership"
  plugin does not exist`; o harness requer uma correção de configuração de
  relacionamento própria, sem reintroduzir o contrib.
- Olivero: validação dispensada pelo responsável em 2026-11-03.
- Adicionada revisão docente ACULTA em `/painel-administrativo/lms/curso/{course}/turma/{group}/respostas`: educadores listam tentativas, gravam nota de 0 a 100 e feedback, e a própria resposta do estudante exibe a avaliação. A permissão contextual `grade students` protege a lista e o detalhe.
- Olivero validado no Firefox ESR visível em 1440 px e 320 px: 10/10 verificações PASS para formulário, upload, recusa de extensão, envio e JavaScript. O tema foi habilitado somente durante a validação e restaurado para `aculta420` ao final.


# 0.1.0-dev.207 — invalida acesso à agenda ao publicar o curso

- A negativa de acesso a eventos da turma agora inclui o curso pai como dependência cacheável quando ele está em rascunho. A publicação do curso invalida a negativa anterior e permite a reavaliação de matrícula/permissão.
- Kernel da Agenda LMS cobre as tags de cache e a transição de rascunho para publicado; Agenda Kernel 5/195 e Unit 8/18 PASS. LMS Unit 270/1.024 e Kernel 41/1.330 PASS.
- Teste responsivo funcional já documentado: formulários de autoria de aula e atividade excedem a largura da viewport entre 320 e 600 px por campos de 635 px. Sem ajuste visual nesta entrega.


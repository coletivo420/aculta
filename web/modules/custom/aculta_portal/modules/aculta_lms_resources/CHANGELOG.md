# Changelog — aculta_lms_resources

## 0.1.0-dev.29 — clarifica os campos do formulário contextual

- Renomeia a lista interna para `CONTEXTUAL_FIELDS`, pois ela inclui o widget
  `field_h5p_activities` registrado pelo módulo proprietário, além dos campos
  de recursos gerenciados por este módulo.
- Nenhuma relação, autorização, widget ou dado persistido foi alterado.
- Unit focal `CourseMaterialsFormTest`: 1 teste / 22 assertions PASS.

## 0.1.0-dev.28 — reutiliza o editor H5P no workspace do educador

- O formulário contextual de materiais inclui `field_h5p_activities` somente
  quando o owner H5P registra o campo/widget no Form Display do curso. Usa o
  widget existente e as políticas de acesso H5P/Group; não embute outro player
  nem persiste referências paralelas.
- A integração é opcional: sem o campo/widget do owner, o formulário segue com
  os recursos disponíveis. A cobertura Unit verifica que o widget H5P entra
  na lista de componentes salváveis; o display ativo no Runtime confirma o
  componente `h5p_editor`. A renderização autenticada pelo browser ainda falta.

## 0.1.0-dev.27 — compatibilidade com ID legado da relação PDF no Group

- O Group agora detecta o ID de configuração legado preservado para a relação
  `aculta_document_viewer:course_document`; sem esse marcador, o formulário de
  criação de Media tentava abrir um tipo novo inexistente.
- O update `8003` ativa apenas o marcador de compatibilidade já suportado pelo
  Group quando encontra um ID não canônico. Mantém o UUID/ID existente, não
  duplica relações e não altera conteúdo. Aplicado no Runtime; o storage agora
  resolve `lms_course-af8ec2205541224f9d8c4` sem renomear ou duplicar a relação.
- A criação de formulário Media/Group foi exercitada sem submit e não gravou
  conteúdo. O smoke do browser autenticado permanece pendente.

## 0.1.0-dev.26 — edição contextual de documentos PDF do curso

- A página de materiais lista documentos privados relacionados ao curso e
  apresenta a criação/edição contextual por formulários existentes de Media e
  Group; não cria bundle, arquivo ou relação paralelos.
- Criação exige autorização de autoria do curso e permissão Group para criar a
  relação. Edição confere curso→documento, Entity Access e `update own`; o papel
  de educador não recebe `update any`.
- Testes Unit de acesso, controller e formulário; rotas Studio verificadas.
  Revisão visual/autenticada no browser permanece pendente.

## 0.1.0-dev.25 — sincroniza o vídeo do curso

- Config Sync agora mantém `field_lms_videos` storage/config e widget do Group
  Form Display. Sem essas cópias, `config:import` removia o campo após o update
  idempotente 8002.
- Snapshot antes de `updatedb` (hash `711a0afe…`) e antes da segunda
  `config:import` (hash `a06031b9…`); ambos fora do repositório, modo 0600.
- `aculta_lms_resources_update_8002` e `config:import` passaram. A primeira
  importação removeu somente o field recém-criado e vazio; o Config Sync da
  dev.25 o restaurou, sem valores de vídeo ou curso gravados.
- `updatedb:status` sem pendências, `config:status` sem diferenças e `drush cr`
  PASS. Firefox autenticado confirmou no formulário os campos de leitura,
  galeria e vídeo e, na estrutura, o link para editar; nenhum formulário foi
  submetido. Anônimo continua recebendo 403.

## 0.1.0-dev.24 — repara instalação parcial de field_lms_videos

- Runtime tinha o schema update 8001 marcado, mas o field storage e o field
  config de `field_lms_videos` estavam ausentes. O update 8002 agora os cria
  somente quando faltarem e garante o widget no Form Display Group.
- `update_8001` e `update_8002` compartilham a rotina idempotente; valores e
  relações existentes são preservados.
- O Runtime requer snapshot e execução do update antes do smoke final do widget.

## 0.1.0-dev.23 — corrige classe EntityFormDisplay no editor

- Corrige o namespace da implementação Core de `EntityFormDisplay` usado para
  montar os widgets existentes do formulário de materiais.
- Runtime apontou `Class not found` no primeiro smoke do formulário; repetir
  validação autenticada após rebuild.

## 0.1.0-dev.22 — editor contextual de materiais

- Novo formulário do Educador edita somente os campos de referência já
  configurados para leituras, galerias e vídeos. Os widgets vêm do Form Display
  Group existente; extração e salvamento usam o próprio Group.
- Studio possui a rota, e o owner `aculta_lms` revalida a autorização contextual
  antes de exibir e salvar. Isso atende educadores autorizados pelo LMS cuja
  permissão contextual não equivale ao acesso genérico de atualização Group.
- Documentos PDF continuam gerenciados pela relação Group do Document Viewer;
  não há schema ou armazenamento paralelo.

## 0.1.0-dev.21 — materiais no Course Builder

- A estrutura do curso no workspace Educador lista leituras, galerias, vídeos
  `remote_video` e documentos PDF já vinculados às entidades Group existentes.
- Vídeos e documentos passam pelo Entity Access no purpose `courses`; documentos
  privados usam a relação Group do `aculta_document_viewer` e não exigem
  publicação. Links usam as rotas canônicas dos owners.
- O botão de edição abre o formulário Group somente quando `access('update')`
  permite. Nenhum schema ou armazenamento paralelo foi criado.
- Unit Resources: 14 testes / 67 assertions; Kernel
  `CourseResourceBuilderKernelTest`: 4 testes / 67 assertions PASS. O smoke
  visual autenticado do bloco de materiais ainda está pendente.

## 0.1.0-dev.20 — cursos usam Group

- A autorização e a projeção de materiais do curso usam `GroupInterface` e `take course`, sem imports Drupal LMS em `src/` ou testes.
- Unit 13/66 PASS; Kernels focais 7/303 PASS, com 54 depreciações upstream.

## 0.1.0-dev.19 — correções da validação pós-integração

- `PublishedGallerySelection` passa a declarar `group: 'aculta_lms_resources'`,
  alinhado ao prefixo do id usado no handler do campo. A divergência fazia
  `SelectionPluginManager::getPluginId()` receber `null` e quebrar a instalação
  limpa com `uasort(null)`.
- O install e o update `8001` ocultam componentes com `removeComponent()`.
  `setHidden()` não existe no Drupal 11.3, inclusive em
  `LayoutBuilderEntityViewDisplay`, classe usada em todo view display quando
  `layout_builder` está ativo.
- Validação (execução de 17:09 a 17:29): instalação limpa e update `8001` em
  site descartável (perfil `standard`, prefixo `idx3_`, MariaDB de teste).
  Campo criado por `config/install` e pelo update sobre schema 8000; três
  cursos fictícios preservados; reexecução sem pendências. PHP lint 85
  arquivos e PHPCS 0 erros (3 avisos de `version` em `info.yml`).

## 0.1.0-dev.18 — vídeos remotos no curso

- Novo campo `field_lms_videos` em `group:lms_course`, limitado ao bundle
  `media:remote_video` do Portal. Novas instalações recebem por
  `config/install`; sites existentes recebem pelo update `8001`, que também
  adiciona o componente ao formulário e o oculta na visualização.
- O provider do Content Index emite a relação explícita `course_video` para
  `media:remote_video`. A versão de projeção passa a 4 com o plugin PDF Group
  ou 3 sem ele, forçando rebuild do catálogo.
- O Media continua sob o Portal: URL oEmbed, corpo, miniatura e Entity Access
  não são copiados; o Index apenas revalida o alvo.
- Evidência: Unit do módulo 13/70; Kernel LMS 5/259; Index Unit 51/265 e Kernel
  119/2.019 PASS. A configuração Sync e o update no Runtime ficam para o agente
  responsável pela integração.

## 0.1.0-dev.17 — consumir a relação Group do Document Viewer

- O provider LMS procura `aculta_document_viewer_group:course_document`, que
  é o plugin da relação versionada para `lms_course`.
- O provider do Content Index fica sob o módulo proprietário da relação; o
  PDF Group mantém temporariamente a configuração instalada do bundle Media.
- Unit/Kernel usam o ID canônico. Nenhuma configuração ou entidade Runtime foi
  alterada.


## 0.1.0-dev.16 — revalidar acesso e relações do Index em MariaDB

- Repetido `LmsCourseContentIndexAccessKernelTest` no MariaDB dedicado:
  4 testes / 208 assertions, sem falhas; PHPUnit reportou 53 depreciações
  upstream. O harness cobre estudante (`takeAccess()`), educador (permissão
  contextual), terceiro negado, Domain canônico `courses` e relações de leitura,
  galeria e documento com providers/YAMLs owner e plugin Group fixture.
- O teste comprova o fluxo da API do Index com entidades e Group reais; não
  comprova boot completo de `aculta_lms_resources` nem homologação Runtime.

## 0.1.0-dev.15 — pacote da fixture alinhado ao Portal

- O módulo fixture do Kernel usa `package: ACULTA Portal`, inclusive nos
  testes, conforme o contrato de componentes próprios. A mudança é de metadado;
  não altera a policy nem as relações de curso.
- Gates estáticos: componentes próprios 59 checks PASS; Drupal 11+ 1.151
  checks PASS; índice de status gerado e diff check PASS.

## 0.1.0-dev.14 — validar relação outgoing Curso→Documento no Kernel

- A classe Kernel agora cobre provider LMS, rebuild, relação `course_document`
  e consulta outgoing com Entity Access: estudante membro recebe o documento
  privado e usuário externo não o descobre.
- A fixture usa a declaração/tags YAML do provider de documentos owner e plugin
  Group isolado; corrige os dois argumentos do serviço LMS no harness.
- MariaDB: classe Kernel completa 4 testes / 210 assertions; 53 depreciações
  Core/contrib. Boot completo dos módulos owner e Runtime não foram verificados.

## 0.1.0-dev.13 — projetar documentos vinculados ao curso

- O provider LMS projeta `course_document` a partir do plugin Group
  `aculta_pdf_viewer_group:course_document`, quando registrado. O owner PDF e
  o provider Media mantêm controle do bundle e de seu acesso; o provider LMS
  mantém a relação outgoing `course_document`.
- A versão de projeção passa a 2 quando a capacidade está disponível e fica em
  1 sem o plugin, alterando o fingerprint para exigir rebuild.
- Unit: 5 testes / 31 assertions PASS, incluindo relação opcional e filtro de
  bundle; PHPCS Drupal e `php -l` PASS. O Kernel LMS da projeção pela API
  outgoing continua pendente: o harness não descobre o plugin Group owner, e
  habilitar o módulo completo aciona uma referência de serviço ausente no
  Portal. O Kernel de acesso existente para `course_document` permanece como
  evidência separada do filtro e da policy do destino.


## 0.1.0-dev.12 — consulta Index de galerias vinculadas ao curso

- O Kernel consulta `course_gallery` por meio dos providers LMS e Gallery cujas
  definições e tags são carregadas dos YAMLs owner. Estudante com `take course`
  e educador com `grade students` encontram a galeria vinculada; terceiro é
  negado.
- O teste também prova Entity Access no purpose canônico `magazine`, troca do
  Domain do curso para `courses`, restauração do contexto original e limpeza do
  cache de acesso que poderia reter uma decisão negativa anterior.
- MariaDB dedicado: suíte Kernel completa 3 testes / 152 assertions PASS, com
  52 depreciações de Core/contrib. O hook owner `GalleryCourseAccessHooks` é
  delegado por módulo de teste; boot completo de Gallery/LMS e Runtime seguem
  pendentes.

## 0.1.0-dev.11 — consulta LMS curso→recursos no Content Index

- O Kernel monta a declaração de produção do provider pelo YAML owner, ativa
  uma geração real do Index e consulta a relação outgoing `course_reading`.
- Estudante com `takeAccess()` e educador com `grade students` recebem somente
  o recurso publicado; terceiro sem acesso ao curso e leitura em rascunho não
  são expostos. Os providers de curso e Revista são compilados de seus YAMLs
  owner e registrados no container do Index.
- MariaDB: 2 testes / 85 assertions PASS, com 48 depreciações de Core/contrib.
  Unit: 12 testes / 57 assertions PASS, com 10 depreciações do PHPUnit.
- A integração real revelou e corrigiu os timestamps `created`/`changed` de
  Group: são convertidos para inteiro antes de criar a projeção.
- O Kernel não inicializa a cadeia completa dos módulos owner; o acesso editorial
  do destino continua coberto pelos testes específicos de Magazine.

## 0.1.0-dev.10 — confirmar policy LMS em SQLite

- Repetido `LmsCourseContentIndexAccessKernelTest` em SQLite: 1 teste / 33
  assertions PASS, com 47 depreciações upstream. A cobertura já existente
  verifica Entity Access do Group, estudante por `takeAccess()`, educador por
  permissão contextual, terceiro negado e troca/restauração do Domain.
- Essa cobertura não executa a consulta outgoing de relações do Index nem
  habilita o módulo owner completo.

## 0.1.0-dev.9 — regressão Unit/Kernel da integração Index

- Suíte Unit completa: 12 testes / 57 assertions PASS, incluindo o contrato de
  serviço tagueado (1/4).
- Kernel Group/Entity Access com Domains persistidos e negotiator real: 1 teste /
  33 assertions PASS, com 47 depreciações upstream.

## 0.1.0-dev.8 — negotiator Domain real no teste de acesso

- O Kernel cria os Domains canônicos persistidos e delega a fixture de
  `DomainNegotiatorInterface` ao serviço `domain.negotiator` real para verificar
  a troca para `courses` e restauração do contexto anterior.
- Kernel MariaDB: 1 teste / 33 assertions PASS, 47 depreciações upstream. O
  provider ainda é montado diretamente neste teste; o contrato YAML/tag tem
  cobertura Unit separada (1/4), sem ativar o módulo real no Kernel.

## 0.1.0-dev.7 — contrato de registro por tag

- Teste Unit lê `aculta_lms_resources.services.yml`, instancia o provider pela
  definição declarada e compila um registry com `TaggedIteratorArgument` real.
  Confirma ID e ownership de `group:lms_course` (1 teste / 4 assertions).
- A integração do service file com o container Drupal de um Kernel que habilite
  o módulo ainda não foi provada; o teste deliberadamente não ativa a cadeia de
  dependências do Portal/Gallery/LMS.

## 0.1.0-dev.6 — fixtures de Domain persistidos

- O Kernel de acesso do provider agora cria e consulta entidades Domain reais
  (`aculta_org` e `cursos_aculta_org`) para comprovar a troca/restauração do
  purpose `courses`; negociação continua isolada por mock.
- `php -l` e PHPCS Drupal passaram. Kernel não foi reexecutado nesta sessão
  porque `SIMPLETEST_DB` não está configurada; a evidência 1/36 de dev.5
  corresponde à versão anterior do fixture.

## 0.1.0-dev.5 — policy do provider validada com Group real

- Kernel exerce o provider sobre `Group:lms_course`: estudante autorizado por
  `takeAccess()`, educador por `grade students` contextual e terceiro negado.
- `ContentIndexAccessFilter` revalida Entity Access e executa o provider no
  purpose `courses`; o fixture Domain verifica a troca e restauração do contexto.
- Kernel: 1 teste / 36 assertions PASS, com 42 deprecações upstream. O provider
  ainda é montado diretamente; descoberta da tag/DI do módulo segue pendente.

## 0.1.0-dev.4 — versão da projeção no fingerprint do Index

- Provider de cursos declara sua versão de projeção para impedir retomada de
  rebuild com semântica de relações desatualizada.

## 0.1.0-dev.3 — provider de cursos e recursos para o Content Index

- Registra `Group:lms_course` e relações explícitas para leituras da Revista/Wiki
  e galerias. O provider exige Entity Access e `takeAccess()` ou permissão
  contextual de educador; não projeta aulas, progresso, membros, respostas,
  notas ou certificados.
- Os alvos continuam sendo revalidados pelos providers e Entity Access dos
  módulos de origem. Kernel/Runtime ainda pendentes.

## 0.1.0-dev.2 — acesso pedagógico às galerias — 2026-10-21

- Educadores com permissão contextual podem selecionar somente galerias
  publicadas. O acesso à galeria publicada é concedido a membros ativos e
  educadores apenas quando ela está vinculada ao curso; as permissões gerais da
  Gallery permanecem iguais.
- Cobertura Unit: 8 testes e 39 assertions. Instalação, smoke autenticado no
  Runtime e revisão visual continuam pendentes.

## 0.1.0-dev.1 — referências editoriais por curso — 2026-10-21

- Adiciona referências de curso para artigos, blogs, verbetes Wiki e galerias
  existentes e renderiza links sob acesso e purpose da entidade de origem.
- Unit: 3 testes / 15 assertions PASS. Kernel MariaDB: 2 testes / 46 assertions
  PASS, com uma depreciação upstream do Domain. PHP CLI 8.4.26; PHP 8.5,
  instalação e smoke autenticado no Runtime, seleção administrativa de galerias
  e revisão visual permanecem pendentes.

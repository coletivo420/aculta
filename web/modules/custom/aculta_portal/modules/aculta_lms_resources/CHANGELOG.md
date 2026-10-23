# Changelog — aculta_lms_resources

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

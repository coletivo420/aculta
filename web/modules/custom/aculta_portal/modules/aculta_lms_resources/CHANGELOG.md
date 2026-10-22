# Changelog — aculta_lms_resources

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

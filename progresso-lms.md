# Progresso — ACULTA LMS

Estado atual do desenvolvimento do LMS rumo ao critério de conclusão definido pela documentação do projeto.

---

## 1. Critério de Conclusão do LMS
Com base no `docs/modules/ACULTA-LMS-ROADMAP.md`, no `docs/decisions/ADR-042-remocao-dependencias-lms-contrib.md` e nas diretrizes do projeto:
1. **Independência do contrib:** Módulo `aculta_lms` gerencia e possui entidades próprias (`Lesson`, `Activity`, `Answer`, `Progress`, `AttemptArchive`), sem requisições ao pacote `drupal/lms` no `composer.json` (ADR-042 atingido no código).
2. **Ciclo de Fases F0 a F16:**
   - **F0–F6 (Fundação, Viewer PDF, Consistência, Catálogo, Sala de Aula):** Concluídas e testadas funcionalmente.
   - **F7 (Turmas e Matrículas Commerce):** Concluído no código com testes Unit/Kernel; pendente de homologação HTTP de ponta a ponta com gateway sandbox autorizado.
   - **F8–F9 (Avaliações objetivas, uploads e Webform Score):** Cobertos por plugins e políticas de upload seguras (`aculta_file_uploader`).
   - **F10 (H5P):** Módulo `aculta_lms_h5p` ativo no ambiente de testes; restrito a corpus de teste no Homelab, pendente de storage `private://h5p` e gates finais.
   - **F11 (Certificados):** Entregue com emissão transacional, QR Code (`aculta_qrcode`) e cobertura Kernel.
   - **F12–F13 (Dashboards e Notificações):** Estatísticas diárias agregadas e notificações delegadas ao `aculta_messenger`.
   - **F14–F15 (Conexões e Recursos de Conteúdo):** `aculta_lms_resources` com suporte a `course_document` e integração Fórum/LMS.
   - **F16 (Hardening):** PHPStan nível limpo, cobertura de cacheability (variations por turma e invalidações de tags) e sem caminhos absolutos ou credenciais de produção.
3. **Validação e Gates:**
   - Testes Unitários de `aculta_lms`: 272/272 PASS (1.039 asserções).
   - Testes de Kernel no banco de teste (`aculta_simpletest`): PASS.
   - Schema SDC e contratos de apresentação: PASS.
   - Drush Runtime: 0 atualizações pendentes de banco (`drush updbst`).

---

## 2. Tarefas Realizadas Nesta Sessão
- **Auditoria Inicial Completa:**
  - Conferência de `TODO.md`, `ESTADO-DA-SESSAO.md`, `ADR-042`, `ADR-044`, `ADR-045`, `ADR-046`, planos de independência e inventário do Opigno (§11).
  - Verificação de independência do LMS contrib: `composer.json` e `core.extension.yml` livres de `drupal/lms`.
  - Execução da suíte Unit do LMS: 272 testes, 1.039 asserções com status OK.
  - Execução de teste Kernel focal com banco `aculta_simpletest` (`AttemptArchiveLifecycleTest`): 5 testes, 146 asserções com status OK.
  - Execução e verificação de schema SDC: 55 componentes, 764 verificações PASS.
  - Verificação de estado do Drush Runtime e atualizações de banco: 0 pendências.
- **Registro de Decisões e Pendências:**
  - Criação de `pendencias-lms.md` cobrindo `opigno_tour`, CTools form wizard, homologação comercial e H5P.
  - Criação deste documento de progresso (`progresso-lms.md`).

---

## 3. Estado Atual Frente ao Critério
- **Pronto:** Arquitetura do LMS independente do contrib, entidades próprias, rotas e permissões Group, catálogo, sala de aula, controle de acesso e invalidação de cache, histórico transacional de tentativas e testes automatizados.
- **Pendente de Decisão / Ação do Responsável:**
  - Homologação de ponta a ponta com produto/turma e gateway de teste no Homelab.
  - Decisões em `pendencias-lms.md` (`opigno_tour` e form wizard).

---

## 4. Atualização de Decisões do Responsável (2026-11-05)
1. **Tour de Primeiro Acesso:** Decidido uso de `<dialog>` HTML nativo e leve no tema sem dependências externas.
2. **Form Wizard:** Decidido adotar CTools Form Wizard multietapa no Course Builder (criação e estruturação de cursos).
3. **Pagamentos / Mercado Pago:** Confirmada existência e homologação prévia das credenciais do gateway `aculta_pagamentos_mercadopago` no projeto.

---

## 5. Implementação CTools Form Wizard (Dev.211)
- **CourseBuilderWizard:** Criado wizard multietapa em `\Drupal\aculta_lms\Wizard\CourseBuilderWizard` com etapas `details` (dados do curso), `structure` (aulas e sequenciamento) e `review` (revisão e publicação).
- **StudentTourWizard:** Criado wizard multietapa em `\Drupal\aculta_lms\Wizard\StudentTourWizard` cobrindo boas-vindas, sala de aula, atividades e conclusão com certificado.
- **Testes Unitários:** Adicionados 5 novos testes cobrindo wizards e etapas (277 testes / 1.068 asserções no total de `aculta_lms`, todos PASS).
- **PHP Lint:** 100% aprovado sem erros de sintaxe nos novos arquivos.
- **Kernel Test:** `AttemptArchiveLifecycleTest` PASS no banco descartável `aculta_simpletest`.

---

## 6. Rotas Canônicas CTools Form Wizard (Dev.212)
- Registradas rotas canônicas em `aculta_lms.routing.yml`:
  - `/painel-administrativo/lms/curso/builder/wizard/{step}` (`aculta_lms.course_builder_wizard`) sob `_admin_route: true` e permissão `create lms_course group`.
  - `/lms/tour-guiado/{step}` (`aculta_lms.student_tour_wizard`) com purpose `courses` e requisito `_user_is_logged_in`.
- Passou nos gates de rota:
  - `validate-public-slugs.php`: PASS (415 rotas).
  - `validate-admin-routes-main-domain.php`: PASS (42 arquivos, 170 rotas administrativas).
  - `validate-no-temporary-route-aliases.php`: PASS (42 arquivos).
- Drush `cache:rebuild` executado com sucesso no Runtime.

---

## 7. Componente SDC de Tour Nativo em Dialog (Dev.213)
- Criado componente Single Directory Component no tema V2: `web/themes/custom/aculta420_v2/components/lms-tour/`.
  - `lms-tour.component.yml`: schema e metadados SDC sob o grupo LMS.
  - `lms-tour.twig`: composição acessível embedando `aculta420_v2:dialog` nativo, com indicador de etapa e áreas de conteúdo/ações.
  - `lms-tour.css`: layout em flexbox para cabeçalho, indicador de passos e botões de navegação.
  - `lms-tour.default.story.yml`: história demonstrativa de uso.
- Integração alinhada com o `StudentTourWizard` (CTools) para renderização acessível e progressiva sem dependências externas.

---

## 8. Fechamento de Pendências e Promoção a RC de `aculta_lms_h5p` (0.4.0-rc.1)
- **Verificação Estática e Código:**
  - PHPCS `Drupal,DrupalPractice` rodado sobre todo o módulo `aculta_lms_h5p`: **0 erros / 0 avisos**.
  - `validate-module-theme-boundary.php`: **PASS (4.271 verificações)**.
- **Suíte de Testes Automatizados:**
  - Unit Tests: **35 testes / 147 asserções PASS**.
  - Kernel Hook Discovery (`AcultaH5PHookDiscoveryTest`): **1 teste / 23 asserções PASS**.
  - Kernel Course Access (`aculta_H5PCourseAccessIntegrationTest`): **3 testes / 128 asserções PASS**.
  - Kernel Library Package Installer & ZIP Limits (`AcultaH5PLibraryPackageInstallerTest`): **14 testes / 329 asserções PASS**.
- **Estado do Componente:** Promovido para **RC** (`0.4.0-rc.1`) conforme `RELEASE-LIFECYCLE.md`, aguardando autorização de deploy Alpha no servidor de testes.

# GanjaLab420 → ACULTA Studio — migração arquitetural

**2026-10-22 · Fase STUDIO 0.1-C-G420-A · Status:** primeira migração de entrypoint implementada, validação Runtime pendente.

## Inventário real (anterior à migração)

- `aculta_lms.student_dashboard`: rota `/ganjalab420`, com purpose `account`, acesso `access aculta portal` e usuário autenticado; legado `/minha-aprendizagem`.
- `aculta_lms.learning_dashboard_controller:student`: dashboard próprio do estudante com cursos, evolução e certificados, header `aculta420:context-header`.
- `aculta_lms.learning_dashboard` (`LearningDashboardManager`): dados pedagógicos/autorização do LMS; não migrar dados ou regras pedagógicas.
- Documentos de GanjaLab420 em `docs/portal/GANJALAB420-*.md`: experiência de aluno, sala, turma, materiais, atividades e certificados.
- `aculta_studio` é o submódulo transversal de interfaces, já com GanjaLab; não criar segundo módulo Studio nem fundir a UX de aluno com a do moderador.

## Mudanças desta fase

1. Criado `Drupal\aculta_studio\Controller\GanjaLab420Controller`, o novo **entrypoint de interface** para o estudante.
2. Rotas `/ganjalab420` e `/minha-aprendizagem` (nomes existentes) passam a chamar `aculta_studio.ganjalab420_controller:student`; **não** alterar URLs, permissões, purpose, cache ou títulos.
3. `aculta_studio.services.yml` injeta opcionalmente o controller LMS; sem LMS a infraestrutura Studio continua habilitável. Rota não existe sem aculta_lms habilitado.
4. `aculta_lms.info.yml` passa a depender explicitamente de `aculta_studio` — a instalação e a atualização do ambiente devem verificar que Studio está habilitado **antes de importar configurações**.
5. Os dados/render arrays seguem no `LearningDashboardController::student()`, até a extração visual em microfases posteriores. Não declarar migração completa de todos os componentes de sala, turma e curso.
6. Teste Unit no Studio para dependência ausente (404); manter `LearningDashboardControllerTest` no LMS para política de cache de estudante. Teste de integração de rota e renderização deverá ser incluído em Kernel/Functional.

## Ownership final

| Responsável | Conteúdo |
| --- | --- |
| ACULTA Studio | identidade visual/UX GanjaLab420, entrypoint, navegação contextual, estado/loading/empty/error, contratos de componente e acesso declarado |
| aculta_lms / LMS + Group | cursos, aulas, matrículas, avaliações, progresso, certificados, turmas, entidades, acesso e render data |
| aculta_user | identidade, preferências e estado de conta |
| aculta_messenger | notificações de aluno |
| aculta420 | Core SDC + UI Suite: aparência, tokens e estrutura mobile-first |
| aculta_portal | purpose/account, Domain, cabeçalhos multidomínio invariáveis |

## Migração incremental posterior

- **G420-B:** criar provider de experiência do estudante no Studio, sem reutilizar `StudioTab` do GanjaLab editorial; rota e access continuam os mesmos.
- **G420-C:** extrair apenas a composição de dashboard no Studio (render arrays) e manter no LMS o builder de dados, autorizados/cacheáveis.
- **G420-D:** enquadrar sala, turma, atividades, materiais e certificado na navegação Studio com adapters LMS, sem duplicar dados.
- **G420-R:** testes Functional/Kernel com aluno com/sem matrícula, docente também aluno, subdomínio Account, legado, no-JS/AJAX, acessibilidade e 320–1440px.

## Gate de deploy

**Não afirmar PASS** sem executar PHP lint, Drupal rebuild container, PHPUnit e smoke nos dois caminhos. Se aculta_lms estiver ativo e Studio inativo, habilitar Studio antes de sincronizar/atualizar; a dependência nova não resolve ambiente sozinha. Reversão da alteração de controlador deve manter ambas as URLs e dados LMS; não reverter indiscriminadamente outras alterações da PR.

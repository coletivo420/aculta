# GanjaLab420 → ACULTA Studio — migração arquitetural

> **Registro histórico.** Este documento preserva os nomes e caminhos vigentes
> quando a migração LMS para o Studio foi implementada. Desde 2026-10-24, a
> experiência editorial se chama ACULTA Studio Marley (`marley`) e a
> experiência estudantil ACULTA Studio Ganjalab (`ganjalab`). A rota estudante
> atual é `/studio/ganjalab`; `/studio/ganjalab` deixou de ser entrada editorial.
> Para decisões e compatibilidade atuais, consulte
> [contrato de identidade](../portal/ACULTA-STUDIO-IDENTITY.md).

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

## G420-B — Provider e registro de experiências (2026-10-22)

- Criado `StudioExperienceProviderInterface`: id, label, purpose, routeName, available, access cacheável e build render array. É independente de `StudioTab` do GanjaLab editorial.
- Criado `StudioExperienceManager`, com registro explícito de provider, rejeição de IDs duplicados, lookup e negação quando purpose/availability não coincidem.
- `GanjaLab420ExperienceProvider` é o primeiro consumidor: ID `ganjalab420`, purpose `account`, rota canônica `aculta_lms.student_dashboard`. O dashboard real continua no serviço `aculta_lms.learning_dashboard_controller`; nenhuma regra de matrícula ou gradebook migrou.
- `GanjaLab420Controller` agora resolve a experiência pelo registro, verifica acesso de conta e delega o render ao provider. Rotas e requisitos não mudaram; `/minha-aprendizagem` continua rota de compatibilidade.
- O registry é explicitamente composto por DI em `aculta_studio.services.yml` nesta primeira entrega; discovery dinâmica/tagueada fica para quando houver segundo provider concreto. Não criar provider fictício para WE SATIVA.
- G420-B tem testes Unit escritos para registry, IDs duplicados, purpose incorreto, LMS ausente e controller sem provider. **Sem execução de PHPUnit/Kernel/Runtime nesta sessão**: PHP lint, rebuild e HTTP precisam ser verificados em ambiente de execução.
- O provider só verifica autenticação e permissão básica de entrada do Portal. A autorização do LMS, cursos, matrículas, materiais e certificados continua obrigatoriamente a cargo de seus serviços e rotas. O purpose é também imposto pela política de Domain/Portal existente, não pelo valor passado manualmente ao registry.

### Próxima microfase: G420-C

Separar `LearningDashboardController::student()` entre dados de aprendizagem e composição visual Studio; preservar metadados de cache e decisões de acesso, sem copiar dados nem modificar URLs. Depois integrar salas e turmas em G420-D. Testar no homelab um estudante matriculado, um não matriculado e um educador que também é estudante, incluindo domínios e fallback sem JS.

## G420-C — Composição Studio e namespace do domínio de conta (2026-10-22)

**Código implementado, homologação pendente.** O dashboard de estudante agora é composto em `Drupal\aculta_studio\Learning\LearnerDashboardComposer`, que recebe `aculta_lms.learning_dashboard` para dados LMS autorizados e cacheáveis. O controller LMS conserva `student()` como adapter para o Composer e `class()` mantém o painel do educador. O provider GanjaLab420 passa a renderizar pelo Composer Studio. Ainda não houve redesenho completo dos cartões; a composição migrou sem alterar o contrato SDC existente (`context-header`, `empty-state`).

**Política global de localização do ACULTA Studio:** interfaces próprias do Studio usam o purpose `account` e a resolução de host pelo Portal/Domain, correspondente ao subdomínio `conta.aculta.*` em cada ambiente. O prefixo `/studio` é o namespace canônico. Não codificar hostname fixo, nem reproduzir o cabeçalho multidomínio dentro do Studio.

- GanjaLab editorial: `/studio/ganjalab` e `/studio/ganjalab/{aba}/{secao}/{sub}`.
- GanjaLab420 estudante: `/studio/ganjalab420`.
- `/ganjalab*`, `/ganjalab420`, `/minha-aprendizagem` e `/studio/{aba}...` permanecem temporariamente acessíveis sob purpose account, para compatibilidade; não remover nem publicar redirecionamento permanente sem inventário e validação.
- WE SATIVA: o antigo planejamento de experiência no purpose wiki exige **migração explícita** do shell Studio para `/studio/we-sativa` sob purpose account. Operações Wiki/API/entidades continuam no domínio e no módulo Wiki conforme regras próprias. **Não declarar rota WE SATIVA migrada** nesta entrega.
- Futuras experiências Studio devem ter entrada canônica em `/studio/{experiencia}` no propósito account; URLs de recursos dos módulos donos podem permanecer em outros domínios, desde que verifiquem acesso.

**Gates antes de homologar:** inspecionar rotas resolvidas e precedência entre `/studio/ganjalab`, `/studio/ganjalab420` e wildcard legado `/studio/{aba}`; confirmar geração de URLs no host conta de cada ambiente, ausência de acesso cruzado via outros hosts, redirects/aliases sem loop e headers multidomínio preservados; executar lint, Kernel, Functional e smoke de estudante autenticado e anônimo. Revisar testes que instanciam `LearningDashboardController` diretamente após nova injeção do Composer.

### Registro condicional do Composer

O serviço `aculta_studio.learner_dashboard_composer` é declarado em `aculta_lms.services.yml` (não em `aculta_studio.services.yml`) porque depende de `aculta_lms.learning_dashboard`. O provider Studio usa referência opcional, preservando a instalação do Studio sem LMS. Teste obrigatório: rebuild com Studio habilitado e LMS desabilitado, além do cenário de ambos habilitados.

## G420-C2 — composição de cartões com SDC (2026-10-22)

O Composer Studio reutiliza `aculta420:card` e `aculta420:content-grid` para cursos e certificados e preserva o cabeçalho `aculta420:context-header` e estados vazios `aculta420:empty-state`. O indicador de progresso usa o HTML nativo `progress`, renderizado no slot de conteúdo do SDC, preservando percentuais e avaliação pendente fornecidos pelo LMS. Não reaproveitar componentes legados fora do contrato SDC/UI Suite. O shell global e o cabeçalho multidomínio em `conta.aculta.*` permanecem responsabilidade Portal/tema; a entrada estudante continua `/studio/ganjalab420`.

Gates pendentes: build de container, PHP lint, PHPUnit, fixtures e navegação autenticada no subdomínio de conta, renderização real no tema ACULTA420 com UI Suite e acessibilidade/responsividade. Sem alegação de PASS Runtime.

## Reclassificação C2-T e dependências paralelas — 2026-10-22

**G420-C2 entregue como composição de código, NÃO como UX pronta.** `aculta420:card`, `content-grid`, `context-header`, `empty-state` existem em SDC; não está demonstrado que os slots, CSS, UI Suite e todos os estados se comportem no Runtime com dados completos. O progresso continua baseado em elemento HTML `progress` no slot e não constitui padrão transversal homologado. Pendem fixtures de curso com e sem matrícula, avaliação pendente, certificado ativo/revogado; teste de autorização/cache, navegação real em `conta.aculta.*/studio/ganjalab420`, render real, accessibility e 320–1440px. As jornadas sala/turma/atividade/material também dependem de implementação paralela do LMS e do tema.

**Decisão de agenda:** G420-C2-T aguarda maturação desses consumidores; não bloquear a frente GanjaLab editorial. Nenhuma alteração de armazenamento ou rota LMS aqui. Novo foco: `GANJALAB E-0.1` sob o roadmap do ACULTA Studio.

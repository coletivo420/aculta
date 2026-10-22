# ACULTA Studio — Roadmap multi-experiência

**Versão STUDIO 0.1 · 2026-10-22**. Documento normativo; GanjaLab já é implementação real em `aculta_studio`; WE SATIVA e contratos de experiências adicionais permanecem planejamento onde ainda não houver provider/rota.

Fonte de verdade: [arquitetura ACULTA Studio](../../../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md).

## Prioridades

| Marco | Trabalho | Gate |
| --- | --- | --- |
| 0.1-A | inventariar infra já presente: StudioTab, StudioPresenter, listing, GanjaLab, rotas e consumidores projetados WE SATIVA/GanjaLab420 | inventário existente versus planejado |
| 0.1-B | ADR de contrato genérico de experiência separando purpose, navegação, provider de ações e ACL | não acoplar WE SATIVA ao StudioTab de conta |
| 0.1-C | endurecer cache e revision/entity access de StudioListingBuilder; corrigir queries auxiliares e paginação; Unit/Kernel | nenhum vazamento entre usuários, LMS, Wiki, Forum/Revista |
| 0.1-D | WE SATIVA: interface de autoria no purpose wiki usando adapters aculta_wiki, Core SDC/UI Suite e Form API | criar/enviar/acompanhar sem acesso de moderador |
| 0.1-E | GanjaLab: seção Wiki/Moderação na WikiTab existente; provider/POST de aculta_wiki | content_editor e transições oficiais, concorrência e feedback |
| 0.1-F | GanjaLab420 e futuras interfaces: inventário de consumidores reais e adaptação, sem inventar rotas | contratos testados por consumidor |
| 0.1-R | homelab, acessibilidade, HTTP sem JS, AJAX e cache por purpose | evidência real |

## Regras para agentes

**Não criar novo módulo ou renomear machine name.** ACULTA Studio é o submódulo presente. Não copiar cabeçalhos nem criar design system paralelo. `aculta420` fornece visual via Core SDC/UI Suite; `aculta_studio` fornece orquestração e contratos de UI; User/Wiki/Forum/LMS/Messenger mantêm sua lógica. Preservar branch e arquivos de outros agentes. Abrir fases por commits pequenos; não alegar validação em homelab sem executá-la.

## Estado D-A.2-T

Primeira etapa realizada: cache de listagens por usuário, checagem explícita de revisão, teste Unit escrito, ainda sem execução Runtime. **Pendentes**: consulta de content_moderation_state, paginação, isolamento entre usuários/Group/purpose e provider da moderação Wiki. Não implantar fila antes dos gates.

## Atualização STUDIO 0.1-B / D-A.2-T2

STUDIO 0.1-B: contrato de providers de experiência definido no ADR da plataforma. StudioTab permanece do GanjaLab; WE SATIVA terá provider no purpose Wiki. D-A.2-T2: removida a consulta global content_moderation_state accessCheck(FALSE) do StudioListingBuilder e passado o estado pela última revisão visível. Pendências: paginação correta, grants e testes Kernel, provider Wiki, nenhuma fila ativada.

## GanjaLab420 — migração para ACULTA Studio (G420-A)

[Contrato e inventário de migração](../../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md). GanjaLab420 é **a experiência LMS do estudante** dentro de ACULTA Studio. Os entrypoints de `/ganjalab420` e `/minha-aprendizagem` foram transferidos para `aculta_studio.ganjalab420_controller`, que delega ao controlador LMS; o LMS preserva dados, progresso, matrículas, certificados, controle de acesso e cache. Não confundir com GanjaLab editorial nem com WE SATIVA. A extração da composição visual para Studio será a G420-C; não declarar concluída. Antes do deploy habilitar Studio se necessário e executar testes Function/Kernel e smoke de rotas.

## G420-B — Provider de experiência implementado (código, validação pendente)

Contrato `StudioExperienceProviderInterface`, registry `StudioExperienceManager` e provider `GanjaLab420ExperienceProvider` registrados por DI. Entry point `GanjaLab420Controller` migrou da dependência direta do controller LMS para o manager Studio; render do LMS preservado. `StudioTab` permanece exclusivo de GanjaLab editorial; WE SATIVA não ganhou provider fictício. Foram escritos testes Unit de negação e registry, **não executados aqui**. G420-C: extrair composição visual, mantendo estado acadêmico, Group e autorização no LMS. O contrato completo está em [migração GanjaLab420](../../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md).

## G420-C — Dashboard no Studio e domínio de conta

`LearnerDashboardComposer` agora é responsável pela composição do dashboard; `LearningDashboardManager` continua dono de dados/cache/regras LMS. O provider do estudante usa o composer e as antigas chamadas `LearningDashboardController::student()` delegam a ele. Namespace canônico: `/studio/ganjalab`, `/studio/ganjalab420`, sob `_aculta_domain_purpose: account` (host `conta.aculta.*` definido pelo Portal). Aliases temporários mantidos. Documentação de arquitetura consolidada em `docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md`. **Implementação sem gate Runtime:** precedência de rotas, DI, Kernel, HTTP, SDC responsivo e testes anteriores precisam de verificação antes do aceite. Próxima G420-C2: concluir compatibilidade/testes do compositor, separar view models e elevar cartões para SDC/UI Suite; não duplicar dados ou serviços acadêmicos.

## G420-C2 — cartões SDC do painel estudante (2026-10-22)

**Implementado em código, não homologado no Runtime.** O `LearnerDashboardComposer` passou a compor as listas de cursos e certificados com o componente já existente `aculta420:card`, distribuído pelo `aculta420:content-grid` (duas colunas responsivas). Mantidos `aculta420:context-header` e `aculta420:empty-state`. A progressão permanece em elemento HTML nativo `progress` com texto e `aria-label`, dentro do slot do card; não se criou CSS, Twig ou design system paralelo.

Os dados e sua cacheabilidade continuam provenientes do `LearningDashboardManager` no LMS, inclusive autorização, notas e certificados. Cursos mantêm URLs fornecidas pelo Portal/LMS, certificados usam rota de verificação existente; nenhum ACL novo. `#cache.max-age: 0` conservado até aferição em ambiente real. Teste Unit existente atualizado para preservar header e estados vazios SDC; execução PHP lint, PHPUnit, inspeção UI Suite/SDC, contraste dark/light, 320–1440px, navegação por teclado e smoke de domínio conta ainda **pendentes**.

**Próxima C2-T:** fixture de curso matriculado, avaliação pendente e certificado ativo/revogado, testes Kernel/Functional de renderização com SDC e regressão de URLs no purpose account. Após homologação, avaliar um componente de progresso próprio do ACULTA420 apenas se houver segundo consumidor real.

## Replanejamento transversal — 2026-10-22 · STUDIO 0.1-C2-R

**Prioridade ativa: GanjaLab editorial.** G420-C2 permanece implementada estaticamente e não homologada. Não bloquear o desenvolvimento editorial pela construção paralela de componentes LMS/tema. A homologação G420-C2-T fica condicionada a fixtures reais, layout em ACULTA420, contratos dos dados LMS, acessibilidade, mobile, light/dark e gates Domain/HTTP. `aculta420:card`, `content-grid`, `context-header`, `empty-state` são componentes existentes, mas existência de YAML/Twig/story não é evidência de consumidor homologado, comportamento responsivo ou UI Suite validada. Progresso, avaliação pendente, estados de certificado e contexto de turma seguem acoplados aos dados/provedores LMS; não declará-los componentes de design maduros. Não criar um novo componente só para passar o gate.

### Matriz de dependências e frentes

| Frente | Código constatado | Não concluído / dependência | Política |
| --- | --- | --- | --- |
| G420-C2 dashboard | Composer no Studio, cards/grid/empty/context-header SDC existentes, dados em `LearningDashboardManager` | fixtures matriculado/não matriculado, certificados válidos/revogados, UI Suite real, Kernel/Functional, browser, mobile/dark, propósito account | congelar novas abstrações e acompanhar trabalho LMS/tema |
| G420-D salas e turmas | LMS, Group e recursos especializados existentes | adapters e UX integrada, avaliações e materiais/certificados em desenvolvimento paralelo | não iniciar pela UI sem consumidores e autorização confirmados |
| ACULTA420 | SDC card/grid/header/empty e stories estáticas | verificar render real, contratos de slots, tokens, 320–1440px, dark/light e gates UI Suite | tema é dono do visual; Studio não cria CSS concorrente |
| GanjaLab editorial | StudioTab/Presenter/Controller/AJAX, plugins Revista/Wiki/Forum/Cursos/Galerias | hardening de consultas, ações reais, jornadas próprias, editor e fila | prioridade imediata |
| Wiki moderação | proposta e WikiTab existentes | provider autorizado/paginado, grants por revisão, Kernel, Content Moderation | bloqueado para produção |
| WE SATIVA | contrato UX documentado | provider e entrada própria sob /studio/we-sativa ainda ausentes | backlog; não misturar com moderação GanjaLab |

### Frente prioritária: GANJALAB E-0.1 — Editor Studio

- **E-A inventário/contrato:** conferir consumidores de cada plugin, rotas e forms dos donos, ownership por área, URLs canônicas `conta.aculta.*/studio/ganjalab`, links e dependências; limpar README/docs obsoletos sem remover rotas legadas.
- **E-B Meu trabalho:** padrão default `Meus conteúdos`, acessos autor/equipe/moderador distintos, filtros autorizados, paginação depois dos grants e render access/cache por item. Prioridade segurança antes de disponibilizar listagens novas.
- **E-C Jornada editorial Revista:** criar, salvar rascunho, editar, pré-visualizar, enviar para revisão e acompanhar pelo Form API e transições existentes do dono; Studio apenas navegação, composição e feedback.
- **E-D Editor shell:** layout mobile-first e progressive enhancement, foco, erro, aviso de alterações não salvas e ações contextualizadas via Core SDC + UI Suite do ACULTA420, preservando Institution/Domain Header.
- **E-E adapters:** estender a Wiki/Fórum/Galerias/Cursos somente após cada dono confirmar forms, ACL e estados; moderação Wiki em microfase própria com provider e testes.
- **E-R aceite:** testes Unit/Kernel/Functional de dois autores e editor, revisão privada, grants Domain e Group, 403/404, cache quente, no-JS/AJAX, 320–1440px, contraste e teclado.

**Não habilitar nova fila nem ampliar visibilidade de estados no E-A.** Cada microfase terá commit próprio na PR corrente, sem misturar trabalho paralelo. Testes de runtime não executados nesta reclassificação documental.

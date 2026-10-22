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

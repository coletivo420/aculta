# ADR-057 - Fechamento do ACULTA420 V2 e incorporações finais

Status: EM EXECUÇÃO em 2026-11-17, branch dev, tema 1.5.0.

## Decisão

Completar pendências verificáveis das ADR-054/055, mantendo a ADR-056 encerrada, e incorporar os cinco avanços aprovados: (1) presets editoriais reutilizáveis; (2) regressão visual sistemática; (3) UI Styles e UI Icons ampliados; (4) performance, Core Web Vitals e fontes; (5) UI Patterns Field Group condicionado à compatibilidade e segurança upstream.

Preservar a UI Patterns Library oficial como catálogo único, shell com cinco regiões, UI Skins como fonte visual, dez site_key funcionais, acesso e cache no Portal. UI Patterns CKEditor 5 permanece excluído, UI Styles CKEditor 5 permanece no escopo. O corte de apresentação legada depende de prova do consumidor e substituto funcional, sem exigir paridade visual anterior.

## Fases e critérios

| Fase | Entrega | Aceite |
| --- | --- | --- |
| F0 - Baseline | Inventário estático, snapshot Git, pendências e testes do auditor | Evidências versionadas, divergências explícitas |
| F1 - Regressão | Playwright estrito no homelab, 320/390/768/1024/1440, claro/escuro, perfis e foco/zoom | Sem SKIP silencioso e sem atualizar snapshots sem aceite humano |
| F2 - Presets | Variações editoriais de títulos, seções, superfícies, cards e grades com UI Styles/SDC | Sem CSS livre, sem duplicação, com testes por consumidor |
| F3 - UI Suite | UI Styles Page/CKEditor/Entity Status, UI Icons Menu/Patterns/Picker | Compatibilidade, acesso, cache e Config Sync verificados |
| F4 - Performance | CWV real, Google Fonts, CSS/JS, rede e responsividade | LCP, CLS, INP, cache e bytes antes/depois |
| F5 - Field Group | Revalidar SHA/lock/Drupal 11 e 12/security advisory | BLOQUEADO para novos consumidores até gate upstream |
| F6 - Aceite | US-14 e RN-05, dez domínios, rollback, perfis, cache cross-domain | PASS/FAIL/DEFERRED por evidência; revisão visual humana |

Revalidação F5 de 2026-11-17: [evidências do Field Group](../operations/testing/ADR-057-F5-2026-11-17.md). O estado permanece `BLOCKED_UPSTREAM`; o relatório não autoriza habilitação nem alteração de dependências.

## Regras

Esta ADR NÃO autoriza importação global de Config Sync, atualizações do banco, desinstalação, DROP, corte de entidades, remoção de permissões, troca de Domain IDs/URLs, deploy de produção ou modificação de checkout. Operações com Drupal Runtime dependem de autorização e snapshot/rollback próprios. Field Group permanece BLOCKED_UPSTREAM e não deve ter consumidores ampliados sem validação nova.

F0/F1 começa com um scanner somente leitura em scripts/audit-aculta420-adr057.mjs, seus testes Node, dois comandos npm e modo visual estrito opt-in. Auditoria estática PASS_STATIC não significa Runtime PASS. O comando de continuidade e o baseline constam de docs/architecture/ACULTA-ADR-057-AUDITORIA-E-HANDOFF.md.

# Semgrep — popups HTMX seguros — 2026-11-13

- **Objetivo:** remover sinks de HTML string nos popups do Fórum e Studio.
- **Estado:** `node --check` e Semgrep focal passaram (0 achados). Kernel remoto reexecutado e em andamento; nenhum teste local rodado.
- **Decisões e alternativas descartadas:** clonar a região de mensagens do documento parseado com `document.importNode()` e anexar com `prepend()`.
- **Cuidados:** preservar o markup acessível do Drupal sem reconstruí-lo como string HTML.
- **Commits e arquivos:** este commit; popups, relatório Semgrep, TODO/status, changelogs, README e handoff.
- **Verificações:** sintaxe JS, Semgrep, diff check e status index PASS; token budget FAIL por itens preexistentes; browser DEFERRED sem origem definida.
- **Pendências e bloqueios:** a contagem exata de 427 não tem export correspondente; API Code Scanning informa `no analysis found`. PHPUnit local suspenso após reboots.
- **Próximo passo:** conferir o Kernel remoto e seguir a triagem com SARIF que corresponda à contagem histórica.

# GitHub — Kernel de acesso em Events — 2026-11-12

- **Objetivo:** corrigir falhas Kernel causadas pela ausência do serviço externo de acesso à organização nos containers isolados.
- **Estado:** handlers negam acesso delegado quando o serviço está ausente. Testes focais EventAccess 3/33 e EventLocationManagement 9 testes passaram. Na CI do SHA `103ac9c91`, estático, descoberta, PHP lint, PHPStan e Unit passaram; o job Kernel foi cancelado sem JUnit. Seleção local de 109 casos foi interrompida em 63/109, sem resultado integral.
- **Decisões e alternativas descartadas:** dependência opcional com falha fechada mantém o contrato seguro em produção, onde `aculta_user` fornece o serviço, e permite testes isolados.
- **Cuidados:** ausência de serviço nunca concede acesso; dependência de módulo segue obrigatória.
- **Commits e arquivos:** aguardando commit; serviços e handlers de acesso do módulo Events, TODO/status e README gerado.
- **Verificações:** EventAccessKernelTest 3/33; EventLocationManagementKernelTest 9; PHP lint, PHPCS, PHPStan focal, descoberta PHPUnit, gate Drupal 12, status index e jobs estático/Unit/PHPStan/lint no SHA `103ac9c91` PASS. Kernel global cancelado; seleção local ampla sem conclusão.
- **Pendências e bloqueios:** recuperar resultado Kernel em condições estáveis; dívida histórica inclui schema Commerce e tabelas Group, conforme auditoria. Sem evidência de causa dos reboots: logs de kernel indisponíveis ao usuário atual.
- **Próximo passo:** continuar análise por classe usando JUnit histórico e validações curtas isoladas, sem suíte integral até estabilizar o ambiente.

# Janela estrutural do Runtime — drift de config e R3 — 2026-11-12

- **Objetivo:** executar a janela de configuração e fechar o delta R3 do Runtime de desenvolvimento.
- **Estado:** drift `config:status` zerado (20 itens → 0), `updatedb` e deploy hooks sem pendências, baseline avançado para `fe37ca701`. `plan` em R0, `verify` e `status` em `VALIDATED`. As três configs do R3 e as demais 17 foram alinhadas; as quatro configs de `aculta_forum_post` receberam UUIDs fixos no commit `fe37ca701`.
- **Decisões e alternativas descartadas:** como o `ConfigImporter` não persistia config entities (DT-O11), o Sync foi aplicado escrevendo o dado exato no storage ativo, em vez de reexecutar importações; nenhuma config foi apagada.
- **Cuidados:** o primeiro `config:import --diff` aplicou antes do snapshot (Drush não confirmou em não interativo); o snapshot do banco foi tirado imediatamente depois, em diretório privado 0700 com SHA-256. O snapshot de `files` ficou DEFERRED por permissão (DT-O04). O checkout foi rebaseado sobre dois commits paralelos de design antes do fechamento.
- **Commits e arquivos:** `fe37ca701` (UUIDs das configs do fórum) e a documentação da janela; o commit de execução não altera código de aplicação.
- **Verificações:** `config:status` 0; `updatedb:status` 0; deploy hooks 0; smoke HTTP 200 e um `h1` nos dez `site_keys` (conta 302 anônimo); `structural-complete` com plano R3 compatível; `plan` R0; `verify` VALIDATED.
- **Pendências e bloqueios:** DT-O11 resolvida — não era o `ConfigImporter`; os YAMLs omitiam defaults de entity displays e a ordem canônica do CKEditor 5. YAMLs canônicos no commit `f52598397`, com import comprovado nos dois sentidos e drift 0. Aceite autenticado e visual da configuração importada ainda pendente.
- **Próximo passo:** validar as telas de moderação do fórum e o formulário de página com Layout Builder.


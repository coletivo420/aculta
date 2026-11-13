# ACULTA - instrucoes para agentes

Regra compartilhada de Claude, Codex e demais agentes. Politica de revisao: `docs/operations/AGENT-REVIEW-POLICY.md`. Aplicar instrucoes locais e skills conforme a tarefa.

## Autonomia e precedencia

- O pedido do responsavel ja autoriza pesquisa, implementacao, testes, refatoracao, documentacao e correcoes no escopo, inclusive no tema V2. Nao exigir segunda confirmacao para trabalho tecnico reversivel.
- Resolver decisoes tecnicas a partir do codigo e das ADRs vigentes. Perguntar so sobre escolha de produto, dado essencial ausente ou operacao sensivel; executar a parte independente.
- Precedencia: seguranca e integridade > instrucao explicita vigente do responsavel > ADR/contrato atual > regra local do componente > skill > runbook/historico. Excecao tecnica justificada pode substituir preferencia global, nunca protecao de seguranca.
- Conferir fatos atuais no codigo, manifests e ambiente. Nao inventar APIs, hosts, CNPJ, enderecos, rotas ou status de runtime.

## Git e entrega

- Integrar em `dev`, por commit atomico em portugues e push fast-forward em `origin/dev`; selecionar apenas arquivos da tarefa e revisar diff/staged. Worktree isolada e permitida; preservar alteracoes de outros agentes.
- Nao usar force push, reset destrutivo ou reescrita de historico compartilhado. Se `dev` avancar, integrar sem perder o trabalho alheio e revisar de novo. PR, merge em `main` e tag so sob pedido explicito.
- Trailer de CLI e opcional e deve identificar a sessao real; nao atribuir coautoria ficticia. Informar hashes confirmados, resultados de testes e pendencias.
- Apos push que altera codigo servido, consultar a skill `aculta-runtime-sync` quando houver ambiente comprovado. Alteracao apenas documental nao exige sincronizacao/cache. Sem acesso, baseline confiavel ou worktree isolado: DEFERRED/BLOCKED, sem contornar preflight.

## Seguranca e Runtime

- Nunca versionar segredos, senhas, tokens, dados pessoais, backups ou arquivos locais de configuracao. Sem deploy em producao. Ambiente de desenvolvimento: `docs/architecture/environments.md`.
- Composer no Runtime, `updatedb`, `config:import`, `pm:uninstall`, DROP, exclusoes persistentes e cutover exigem plano, reversibilidade e autorizacao explicita por operacao. Push nao autoriza migracao. Kernel usa apenas banco de teste, nunca `aculta_runtime`.
- Preservar permissoes, ownership, isolamento multidominio, caches privados, escape, consentimento, contratos de pagamento e integridade de dados. Nao trocar `system.theme` para testar.
- Evitar caminhos absolutos de maquina e URLs fixas de ambiente em codigo versionado; usar configuracao e variaveis.

## Implementacao

- Drupal 11 efetivo e compatibilidade Drupal 12 quando aplicavel. Preferir HTML nativo/Form API; HTMX para fragmentos onde agrega valor. Drupal AJAX e legado: migrar com justificativa e regressao, nao por obrigacao cega. jQuery local sob demanda conforme regra da area, sem carga global desnecessaria.
- Reutilizar SDC, UI Suite, tokens e componentes existentes. Priorizar acessibilidade, responsividade e contratos de renderizacao; alternativas tecnicas sao permitidas com justificativa.
- Endereco/CEP centralizados em `aculta_portal_cep`, pais unico Brasil (BR), conforme ADR-034. Nao duplicar formularios de endereco. Rotas administrativas e integracoes tecnicas pertencem ao dominio MAIN; manter a politica multidominio de acesso e escrita.
- URLs publicas usam slugs em portugues minusculo com hifen e sem acentos (`docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`). Aceite de Termos e Politica e unico (`docs/portal/ACEITE-UNICO.md`); nao criar aceite paralelo.
- A tabela de estado dos componentes no README e gerada a partir de `docs/operations/status/`; valide com `scripts/validate-project-status-index.php` quando mudar status ou versao.
- Ler `TODO.md` e handoff do componente quando houver fase a retomar, nao por ritual. Preferir `rg`, trecho relevante e APIs instaladas; Core/contrib e referencias podem ser lidos quando necessarios.

## Revisao e documentacao

- Toda entrega exige autorrevisao do diff e gates proporcionais. Classificar R0-R3 segundo `docs/operations/AGENT-REVIEW-POLICY.md`; R2/R3 exigem revisao independente e R3 autorizacao operacional. Essa escala e diferente da classificacao R0-R4/RX do Runtime Sync.
- Rodar gates somente do diff e dos consumidores afetados (`docs/operations/GATES.md`). Declarar PASS somente quando executado, FAIL quando falhar, DEFERRED se indisponivel e N/A fora de escopo. Teste pulado nao e PASS.
- Atualizar documento dono apenas quando mudar API, contrato, decisao, fase ou marco. Fix simples nao obriga repetir CHANGELOG, roadmap, TODO, status e handoff. Handoff para fase parcial/transferencia; nao para cada microalteracao.
- Saida verbosa: `python3 scripts/agent-output.py --lines 60 -- COMANDO`, preservando diagnosticos.

Indice por area: `docs/operations/AGENT-POLICY-INDEX.md`. Skills canonicas: `.agents/skills/`. Procedimentos de Runtime: `docs/operations/RUNTIME-SYNC.md`.
Padrao de implementacao Drupal 11+ e compatibilidade Drupal 12: `docs/portal/DRUPAL-12-STANDARDS.md`.

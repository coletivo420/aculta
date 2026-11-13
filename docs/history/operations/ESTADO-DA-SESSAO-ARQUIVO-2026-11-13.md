# Arquivo de handoff — 2026-11-13

# Segurança — link do Mercado Pago — 2026-11-13

- **Objetivo:** fechar o sink `href` configurável do aviso de pagamento.
- **Estado:** controller restringe esquema/host e passa `Url`; templates usam Twig `link()`. PHP lint e Semgrep focal passaram (0 achados); testes locais não executados.
- **Decisões e alternativas descartadas:** permitir HTTPS somente em `mercadopago.com.br` e `www.mercadopago.com.br`; configuração inválida oculta o link.
- **Cuidados:** schema `uri` isolado aceitava esquemas não web; preservar texto, classe, nova aba e `rel`.
- **Commits e arquivos:** este commit; Portal, tema, TODO/status, relatório Semgrep, README e handoff.
- **Verificações:** `php -l`, Semgrep, status index e diff check PASS; token budget FAIL por regras preexistentes; browser DEFERRED.
- **Pendências e bloqueios:** CI do novo SHA pendente; origem exata dos 427 alertas ainda sem SARIF/export. Runtime Sync BLOCKED em RX; PHPUnit local suspenso após reboots.
- **Próximo passo:** conferir CI e continuar a triagem a partir do relatório amplo disponível.

# GitHub — Kernel de acesso em Events — 2026-11-12

- **Objetivo:** corrigir falhas Kernel causadas pela ausência do serviço externo de acesso à organização nos containers isolados.
- **Estado:** handlers negam acesso delegado quando o serviço está ausente. Testes focais EventAccess 3/33 e EventLocationManagement 9 testes passaram. Na CI do SHA `103ac9c91`, estático, descoberta, PHP lint, PHPStan e Unit passaram; o job Kernel foi cancelado sem JUnit. Seleção local de 109 casos foi interrompida em 63/109, sem resultado integral.
- **Decisões e alternativas descartadas:** dependência opcional com falha fechada mantém o contrato seguro em produção, onde `aculta_user` fornece o serviço, e permite testes isolados.
- **Cuidados:** ausência de serviço nunca concede acesso; dependência de módulo segue obrigatória.
- **Commits e arquivos:** aguardando commit; serviços e handlers de acesso do módulo Events, TODO/status e README gerado.
- **Verificações:** EventAccessKernelTest 3/33; EventLocationManagementKernelTest 9; PHP lint, PHPCS, PHPStan focal, descoberta PHPUnit, gate Drupal 12, status index e jobs estático/Unit/PHPStan/lint no SHA `103ac9c91` PASS. Kernel global cancelado; seleção local ampla sem conclusão.
- **Pendências e bloqueios:** recuperar resultado Kernel em condições estáveis; dívida histórica inclui schema Commerce e tabelas Group, conforme auditoria. Sem evidência de causa dos reboots: logs de kernel indisponíveis ao usuário atual.
- **Próximo passo:** continuar análise por classe usando JUnit histórico e validações curtas isoladas, sem suíte integral até estabilizar o ambiente.


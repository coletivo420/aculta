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

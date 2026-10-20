# Mailer em produção: checklist de preparação

Preparado em 2026-10-20. **Nada foi implantado em produção.** A implantação depende de decisão do responsável e do
processo de deploy (`docs/operations/DEPLOYMENT.md`, `aculta_deployer/docs/USO.md`). Este documento não contém valores de
segredo; os nomes das variáveis estão em `docs/operations/SECRETS.md`.

## Verificado agora (Runtime de teste)

- `aculta-deployer provision --env=production` sem `--apply` (somente plano), usando o arquivo de segredos disponível
  neste servidor: a única ação prevista é **cadastrar o webhook transacional com a URL de produção**. Nenhuma geração
  de segredo foi indicada.
- **Limite:** o arquivo de segredos de produção não está neste servidor. A presença de `MAILER_PAYLOAD_KEY` e de
  `BREVO_WEBHOOK_TOKEN` de produção **não foi confirmada**; isso só se confere no servidor de produção.
- Configuração versionada (`config/sync`): `aculta_mailer.delivery` com `tracking_enabled: true` (F11), que vale para
  quem deu consentimento. Chegará à produção com o deploy.

## Passos no deploy (responsável)

1. `aculta-deployer check --strict` precisa passar.
2. `aculta-deployer webhooks` mostra o cadastro de produção pendente.
3. `aculta-deployer build --out=CAMINHO` e conferência de `deploy-report.json` (nenhum `toca.net.br` nos arquivos gerados).
4. Aplicar a árvore no servidor de produção, pelo processo do responsável.
5. No servidor de produção: `aculta-deployer provision --env=production --secrets=CAMINHO_DO_ARQUIVO_DE_SEGREDOS --apply --confirm-production --remove-test=2631443`.
   - Gera `BREVO_WEBHOOK_TOKEN` e `MAILER_PAYLOAD_KEY` se faltarem (nunca sobrescreve).
   - Cadastra o webhook de produção no Brevo e remove o cadastro de teste (`2631443`).
   - Depois, atualizar `registration_id` da produção em `config/deploy.json`.
6. `aculta-deployer verify` após o deploy.
7. Conferir DKIM, SPF e DMARC do domínio remetente no diagnóstico do painel (`/envios-de-email/diagnostico`).
8. Enviar uma mensagem de teste pelo caminho real a um endereço autorizado e conferir o cabeçalho `Return-Path`.

## Decisões pendentes do responsável

- Data do cadastro de produção (depende da implantação), já registrada no `deploy.json`.
- Rollback: cada build fica em diretório próprio; voltar é apontar a produção para o build anterior.

## Fora deste checklist

Os textos de conta já estão na camada (aculta_portal 0.4.0-dev.231) e chegam à produção com o deploy do RC.

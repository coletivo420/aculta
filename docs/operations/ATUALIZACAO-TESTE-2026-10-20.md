# Atualização do site de teste — 2026-10-20

Registro operacional da atualização do Runtime de teste (`aculta_runtime`, servidor de testes) com as ferramentas
previstas. Produção não foi implementada nesta data: não há deploy de produção registrado aqui.

## Estado do Runtime de teste (após a atualização)

- Código: checkout do repositório aberto pelo responsável, no commit `16bb1e0d` (branch `docs/aculta-forum-0.1.0-dev.1`).
- Versões em execução: `aculta_portal` 0.4.0-dev.227; `aculta_mailer` 0.1.0-dev.44.
- Atualizações de banco pendentes: nenhuma (`drush updatedb:status`).
- Cache: reconstruído (`drush cr`) após cada mudança de código.
- Telas administrativas do mailer respondem: `envios` e `falhas` (403 para visitante, como previsto).

## O que foi executado

| Ferramenta | Modo | Resultado |
|---|---|---|
| `drush updatedb:status` / `updb -y` | Persistente, só o necessário | 0 pendências na data; a atualização `aculta_mailer_update_10005` foi aplicada antes, sem outras |
| `drush cr` | Persistente (cache) | Concluído |
| `aculta-deployer check` | Leitura | OK |
| `aculta-deployer boundaries` | Leitura | OK |
| `aculta-deployer cookies` | Leitura | OK |
| `aculta-deployer webhooks` | Leitura | OK |
| `aculta-deployer domains` | Leitura | OK |
| `aculta-deployer robots --env=test` | GET somente leitura | OK |
| `aculta-deployer verify` | GET somente leitura | Nenhuma entrada com sonda ativa |
| `aculta-deployer build --target=test` | Geração em diretório temporário, removido depois | 1.295 arquivos, 38 regras de perfil, sem erro |
| `aculta-deployer provision --env=test --secrets=...` | Dry-run | "webhook já cadastrado; nada a fazer" |

## O que não foi aplicado, e por quê

- **Importação de configuração (`drush config:import`): não executada.** `drush config:status` mostra 326 objetos
  diferentes entre o banco e `config/sync`, em três sentidos (`Different`, `Only in sync dir`, `Only in DB`), de vários
  módulos (`field`, `core`, `views`, `system`, `image`, `domain`, `captcha`, entre outros). É a dívida DT-O10 já
  registrada. Importar mudaria também objetos que existem só no banco. Não é seguro fazer sem inventário e decisão
  próprios.
- Nenhum objeto de configuração deste trabalho ficou pendente: `aculta_mailer` não aparece na lista de diferenças, e
  `email_confirmer.settings` tem `hash_expiration: 86400` no banco e no versionado. A diferença restante é de texto dos
  e-mails e de `confirmation_lifetime` (604800 s), que controla a retenção dos registros de confirmação, não a validade do
  link. Essa diferença fica registrada como dívida, sem alteração nesta atualização.

## Produção: o que falta (não implementada)

1. Implantação do código de produção pelo processo do responsável (`aculta-deployer build --target=production`,
   conferência do relatório, aplicação). Ver `DEPLOYMENT.md` e o procedimento em `aculta_deployer/docs/USO.md`.
2. `aculta-deployer provision --env=production --secrets=CAMINHO_NO_SERVIDOR --apply --confirm-production
   --remove-test=2631443`, com o arquivo de segredos do servidor de produção. Gera `BREVO_WEBHOOK_TOKEN` e
   `MAILER_PAYLOAD_KEY` de produção se faltarem, cadastra o webhook de produção e remove o cadastro de teste.
3. Atualizar `registration_id` do ambiente de produção em `external_webhooks` no `config/deploy.json`.
4. Confirmar o cabeçalho `dkim=pass`, `spf=pass` e `dmarc=pass` em uma mensagem recebida em produção.
5. Decidir a importação da configuração com o inventário da DT-O10 (item acima).

## Pendências que dependem de decisão do responsável

- Rastreamento com consentimento (F11): falta o texto dos termos de uso e da política de privacidade.
- Renovação do plano SMTP2GO (contingência), esgotado no mês.

# ACULTA Doações (`aculta_donation`)

Submódulo do `aculta_portal` (versão 0.1.0-dev.1). Acrescenta ao `commerce_donation_flow` o que o site precisa para
campanhas de doação com prazo, sem duplicar o fluxo de pedido e checkout que já existe.

## O que entrega

- **Campanha de doação** (entidade de configuração `aculta_donation_campaign`): nome, período de vigência (início e
  término, inclusivos, no fuso do site), valores sugeridos em reais, permissão de valor personalizado e status ativo.
- **Seleção da campanha vigente** (`DonationCampaignSelector`): campanha ativa cuja janela contém a data de hoje. Em
  sobreposição, vence a de início mais recente. Sem campanha vigente, a página de apoio usa os valores padrão.
- **Integração com a página de apoio**: implementa `SupportAmountsProviderInterface` (contrato neutro do
  `aculta_portal`). O formulário de apoio mostra os valores da campanha e só exibe "Outro valor" quando a campanha
  permite valor personalizado.

## Fronteiras

- Pedido e checkout: `commerce_donation_flow` (contrib, mantido sem alteração).
- Assinatura recorrente: um pedido por assinatura (Mercado Pago, API de preapproval). Doação avulsa: um pedido por
  cobrança. Decisão do responsável sobre a F3 (ainda não implementada; o plano `docs/portal/MERCADO-PAGO-PLANO.md` continua marcando F3 como bloqueada até a fase de assinatura).
- Permissão de administração: `administer aculta donation campaigns` (restrita). Campanhas ficam em
  `/painel-administrativo/configuracoes/aculta/doacoes/campanhas`.
- Apresentação: o tema ACULTA420 desenha os cards de valor (`data-portal-ui="support-amounts"`). Este submódulo não
  tem template, CSS nem JavaScript.

## Testes

- Unitários: `tests/src/Unit/DonationCampaignSelectorTest.php` (janela inclusiva, fora da janela, sem campanhas,
  sobreposição). Execução: `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_donation/tests/src/Unit`.
- Runtime de testes: pendente. `RUNTIME STATUS: DEFERRED` (módulo ainda não habilitado no `aculta_runtime`; ver CHANGELOG do `aculta_portal`).

# Changelog — aculta_events

## 2026-10-28 — views administrativas de locais e datas

- Incluídas as views `eventos_locais` e `eventos_datas_relacionadas` em caminhos portugueses, com acesso restrito ao papel administrador e sem telefone, e-mail ou endereço.
- A exportação administrativa de datas foi aprovada para a v1 via provedor do `aculta_calendar` (ADR-031); permanece pendente da API CA0/CA2 e não foi duplicada neste módulo.

## 2026-10-28 — registrar propósito dos locais nos testes

- Exportado para `config/sync` o vocabulário `aculta_location_type`, já criado pelo update hook 10003, e coberta a negação de `/locais` fora do purpose `eventos` com teste Kernel.
- O update hook já contém os 17 tipos de local traduzidos; este registro fecha a configuração e a evidência de acesso da microfase E1.

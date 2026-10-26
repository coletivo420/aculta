# Changelog — ACULTA Commerce Melhor Envio

## 0.1.0-dev.2 — alias do mapper para o binder — 2026-10-26

- O container resolve `MelhorEnvioAddressMapper` no construtor de `ShipmentAddressBinder` pelo alias da classe para o service ID documentado `aculta_commerce_melhorenvio.address_mapper`.
- Adicionado teste Unit que compila as definições reais de serviço com o autowiring Symfony.
- Reexecução da Functional Statistics no docroot isolado ainda pendente; não houve escrita no Runtime.

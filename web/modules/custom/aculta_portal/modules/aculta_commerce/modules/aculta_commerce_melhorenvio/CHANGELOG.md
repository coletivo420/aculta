# Changelog — ACULTA Commerce Melhor Envio

## 0.1.0-dev.3 — contrato do provider no motor próprio — 2026-10-26

- O submódulo declara dependência de `aculta_commerce` e passa a documentar o provider no motor de envios próprio, não no `commerce_shipping`.
- Contrato em `docs/MELHOR-ENVIO-CONTRACT.md`: atributo `AcultaShippingMethod`, interface/base do `aculta_commerce`, tarifas próprias e falha fechada.
- O plugin de cotação atual da linha principal do site ainda não foi transportado para esta branch; cotação externa não homologada.

## 0.1.0-dev.2 — alias do mapper para o binder — 2026-10-26

- O container resolve `MelhorEnvioAddressMapper` no construtor de `ShipmentAddressBinder` pelo alias da classe para o service ID documentado `aculta_commerce_melhorenvio.address_mapper`.
- Adicionado teste Unit que compila as definições reais de serviço com o autowiring Symfony.
- Reexecução da Functional Statistics no docroot isolado ainda pendente; não houve escrita no Runtime.

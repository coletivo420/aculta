# Arquitetura do aculta_portal

`aculta_portal` mantém contratos transversais, propósitos de domínio,
integrações de kernel e APIs compartilhadas. Providers específicos permanecem
nos módulos que possuem os dados e serviços correspondentes.

## Decisões vigentes e superadas

- **2026-10-22 — Adiar o provider de `remote_video` enquanto o ownership não
  estivesse definido.** Situação: superada por `aculta_index` `0.1.0-dev.165` e
  pelo contrato atual do [Content Index](../portal/ACULTA-CONTENT-INDEX.md).
  Origem: `0.4.1-dev.7`, [histórico arquivado do Portal](../history/changelog/aculta_portal.md).
  O Portal agora é owner do bundle Media e o provider mínimo está registrado;
  as relações de vídeo continuam sujeitas aos formatos e contratos de acesso
  dos consumidores.

## Fronteiras

- O Portal mantém contratos neutros para módulos opcionais; não incorpora
  regras de negócio de seus consumidores.
- O contrato de marca entre Portal e tema está em
  [ADR-028](../decisions/ADR-028-marca-neutra-portal-tema.md).

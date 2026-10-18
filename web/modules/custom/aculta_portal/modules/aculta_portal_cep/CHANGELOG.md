# Changelog — aculta_portal_cep

## 0.1.0-dev.1 — porte do cep_autocomplete — 2026-10-18

- Porte do `drupal/cep_autocomplete` 1.0.0 para o submódulo: controlador de consulta (ViaCEP com fallback BrasilAPI, cache de 30 dias), limite de consultas por IP e biblioteca `cep-address`.
- Rota `aculta_portal_cep.lookup` em `/aculta_portal_cep/{cep}` (antes `/cep-autocomplete/viacep/{cep}`, do contrib). Chave `once` do JavaScript renomeada para `aculta-portal-cep`.
- Correção de porte: a checagem de limite comparava o nome da rota antigo do contrib; agora compara `aculta_portal_cep.lookup`.
- Não portado: `viacep.js` e o `ViaCepClient` do contrib (código morto ou conflitante; ver README).
- O `aculta_portal` passa a depender deste submódulo no lugar do `cep_autocomplete`.
- RUNTIME STATUS: pendente (ver a entrega do aculta_portal).

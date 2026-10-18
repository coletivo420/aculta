## 0.2.1-dev.1 — troca de CEP em página com endereço salvo — 2026-10-18

- Bug: em página recarregada com endereço salvo, digitar outro CEP só preenchia o bairro vazio; cidade, UF e rua ficavam com o endereço antigo. Causa: a primeira busca da página era tratada como preenchimento de campos vazios.
- Correção: o CEP já salvo no formulário é a base da comparação. Qualquer CEP diferente troca o endereço inteiro; um formulário vazio continua sendo preenchido só nos campos vazios.
- Teste de navegador ampliado (20 verificações, incluindo página recarregada com CEP de outro estado).
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild); CEP 74711020 conferido.

## 0.2.0-dev.1 — base local de CEPs em arquivos — 2026-10-18

- Base local em arquivos privados, divididos por prefixo (`private://aculta_portal_cep/shards`), sem banco de dados. A consulta local tem prioridade; ViaCEP e BrasilAPI só são usadas quando o CEP não está na base.
- Comando Drush `aculta-portal-cep:import <tsv>`.
- Verificado no Runtime de teste: 1.388.509 CEPs importados (872 arquivos, ~92 MB); quatro CEPs conferidos contra a ViaCEP; teste de navegador do endereço 17/17.
- Licença: risco aceito pelo responsável em 2026-10-18 (ver docs/portal/CEP-LOCAL.md).
- RUNTIME STATUS: APLICADO no Runtime de teste (importação e cache rebuild).

# Changelog — aculta_portal_cep

## 0.1.0-dev.1 — porte do cep_autocomplete — 2026-10-18

- Porte do `drupal/cep_autocomplete` 1.0.0 para o submódulo: controlador de consulta (ViaCEP com fallback BrasilAPI, cache de 30 dias), limite de consultas por IP e biblioteca `cep-address`.
- Rota `aculta_portal_cep.lookup` em `/aculta_portal_cep/{cep}` (antes `/cep-autocomplete/viacep/{cep}`, do contrib). Chave `once` do JavaScript renomeada para `aculta-portal-cep`.
- Correção de porte: a checagem de limite comparava o nome da rota antigo do contrib; agora compara `aculta_portal_cep.lookup`.
- Não portado: `viacep.js` e o `ViaCepClient` do contrib (código morto ou conflitante; ver README).
- O `aculta_portal` passa a depender deste submódulo no lugar do `cep_autocomplete`.
- RUNTIME STATUS: pendente (ver a entrega do aculta_portal).

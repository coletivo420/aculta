# aculta_portal_cep — regras locais

- **País sempre BR.** Nenhum formulário de endereço exibe seletor de país. Use `country_code = BR` (campo oculto ou valor fixo).
- **Validação no servidor.** Valide e persista `country_code = BR` no servidor. JavaScript ou campo oculto sozinhos não bastam; entrada vazia vira `BR`, e valor diferente é rejeitado.
- **Contrato central.** Formulários usam a biblioteca `aculta_portal_cep/cep-address`. Não crie busca de CEP paralela.
- **Número, S/N e complemento.** A busca de CEP nunca os altera. Número, S/N e complemento são preservados.

Referências: ADR-034 (`docs/decisions/ADR-034-pais-unico-brasil-endereco-central.md`) e `docs/portal/ACCOUNT-ADDRESS.md`.

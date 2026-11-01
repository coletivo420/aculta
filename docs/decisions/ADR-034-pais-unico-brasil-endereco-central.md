# ADR-034 — País exclusivo Brasil (BR) e contrato central de endereço

**Status:** vigente desde 2026-10-30, por decisão do responsável.  
**Escopo:** formulários de endereço, captura de dados postais, consulta de CEP e país em todo o ACULTA.  
**Origem:** consolidação de `docs/portal/ACCOUNT-ADDRESS.md`, módulo `aculta_portal_cep` e instrução normativa para agentes.

## Contexto

O projeto ACULTA opera exclusivamente com público e locais no território brasileiro. Anteriormente, alguns formulários ou widgets expunham seletores de país globais ou dependiam exclusivamente de campos ocultos e restrições no cliente (JavaScript), permitindo que requisições paralelas ou campos desconfigurados introduzissem países divergentes de `BR` ou duplicassem fluxos de CEP e endereço.

## Decisão

1. **País exclusivo Brasil:** o país é exclusivamente o Brasil (`BR`) em toda a plataforma. É terminantemente proibido exibir qualquer opção de seleção de país para o usuário em formulários de endereço ou qualquer outro local do projeto.
2. **Contrato centralizado em `aculta_portal_cep` e `AccountAddressForm`:** todo formulário de endereço do site deve usar o serviço, widget e contrato centralizados mantidos em `aculta_portal` (`AccountAddressForm`) e `aculta_portal_cep`. É proibido criar campos, endpoints, clientes de CEP ou fluxos de endereço paralelos.
3. **Validação e confirmação obrigatória no servidor:** JavaScript ou campos do tipo hidden, por si sós, não substituem a validação do servidor. O servidor deve confirmar `country_code = BR` ao validar o formulário e ao persistir a entidade. Entradas vazias ou nulas são normalizadas para `BR`; qualquer valor diferente de `BR` é rejeitado com erro de validação.
4. **Preservação de número, S/N e complemento:** a busca de CEP deve continuar usando a biblioteca `aculta_portal_cep/cep-address`. Ela pode preencher os dados postais (logradouro, bairro, cidade, UF), mas deve preservar obrigatoriamente número, S/N e complemento conforme o contrato central vigente.
5. **Configurações e integrações restritas:** todos os campos de endereço (`field_address`) e formulários de checkout/loja são configurados exclusivamente com `available_countries: ['BR']`. Seleções de países de faturamento (`billing_countries`) são ocultadas e fixadas em `BR`. Gateways de pagamento (como Mercado Pago) operam com país fixo em `BR`.
6. **Dados históricos preservados:** dados já armazenados no sistema continuam íntegros; a regra se aplica à criação, edição, validação e persistência de dados.

## Evidência vigente

- `AccountAddressForm::apply()` fixa `country_code = BR`, restringe países disponíveis e anexa validação no servidor (`validateCountryCode` e `validateAddressElement`).
- `AccountAddressHooks` normaliza entidades no `entity_presave` e intercepta elementos address em `element_info_alter`.
- `BillingAddressPane` fixa `available_countries = ['BR']` e valida no servidor.
- `config/sync/field.field.profile.*.field_address.yml` restringe `available_countries` exclusivamente para `BR: BR`.
- `config/sync/core.entity_form_display.commerce_store.online.default.yml` oculta `billing_countries`.
- Gate automatizado em `scripts/validate-account-address.php`.

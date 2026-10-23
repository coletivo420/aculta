# Contrato de endereço: ACULTA → Melhor Envio (ME-ADDRESS 0.1)

Auditoria da fase ME-ADDRESS 0.1-A. Regra: o endereço do ACULTA é a única fonte. O Melhor Envio não tem formulário de
endereço para o comprador. O submódulo transforma dados existentes e não guarda cópia.

## 1. Fontes de verdade (auditadas)

| Assunto | Onde está | Achado |
|---|---|---|
| Formulário único | `aculta_portal/src/Account/AccountAddressForm.php` | Usado por Minha conta, checkout (cobrança), loja (`commerce_store.address`) e perfis de equipe (`field_address`). País fixo em BR por campo oculto. |
| Número e S/N | `aculta_portal/src/Account/StreetNumberField.php` | Gravado em `address_line1` como `"rua, número"` ou `"rua, S/N"` (`join()`). `split()` separa de volta. |
| Complemento | `address_line2` do Address | Independente do número. Não passa por `join()`/`split()`. |
| Bairro | `dependent_locality` | Obrigatório pelo pane de cobrança (`hasNeighborhood()`). |
| Cópia de cobrança | `BillingAddressPane::billingCopy()` | Em rascunho, copia `field_address` do perfil do cliente a cada chamada (`syncBillingCopy()`). Pedido fora de `draft`: devolve o perfil gravado no pedido, congelado. |
| Perfil de envio | `AcultaShippingInformation::getShippingProfile()` | Usa a cópia de cobrança. Não lê o cadastro atual quando o pedido já saiu de rascunho. |
| Validação do número | `StreetNumberField::validateNumber()` | Regra vigente: 1 a 10 dígitos (`MAX_DIGITS = 10`), ou S/N. |
| CEP | `aculta_portal_cep` (`CepLookupInterface`) | Normalização e consulta centralizadas. |

### Achados

1. **Comentário desatualizado:** `validateNumber()` diz "1 a 4 dígitos", mas o código e a mensagem usam 10. Não alterar
   a regra sem decisão do responsável; a correção é só do comentário, em fase própria.
2. **Snapshot do pedido:** o endereço usado numa remessa precisa ser o do perfil do pedido, não o cadastro atual. Isso já
   vale depois que o pedido sai de rascunho. Em rascunho, a cópia ainda segue o cadastro, o que é aceitável antes da compra.
3. **Não há segundo cadastro:** nenhum código do submódulo declara campo de endereço nem `config/install` com campos.

## 2. Contrato do Melhor Envio (documentação oficial)

Fontes: `docs/cotacao-de-fretes`, referência `calculo-de-fretes-por-produtos` e referência `inserir-fretes-no-carrinho`.

- **Cotação:** exige apenas `from.postal_code` e `to.postal_code`, mais produtos/volumes. Número não é exigido.
- **Inserção no carrinho (contratação):** `from` e `to` exigem `name`, `address`, `number`, `district`, `city`,
  `country_id`, `state_abbr`. `number` é string. Pessoa física envia `document` (CPF) no destinatário.
- **S/N:** a documentação não define representação para endereço sem número. Não há `0`, `S/N` nem campo próprio.
  Decisão: **nunca converter S/N em zero**. Contratação e etiqueta com S/N são bloqueadas com mensagem.
- **Validação de UF:** se `state_abbr` vier informada, a API valida contra o CEP.

## 3. Matriz de campos ACULTA → Melhor Envio

Legenda: **Obrigatório** = bloqueia a operação se faltar. **Opcional** = enviado quando existir. **—** = não enviado.

| Campo ACULTA (Address/StreetNumber) | Melhor Envio | Cotação | Contratação (carrinho) | Etiqueta | Rastreamento |
|---|---|---|---|---|---|
| `postal_code` (normalizado 8 dígitos) | `postal_code` | Obrigatório | Obrigatório | Obrigatório (via envio) | — |
| `address_line1` → rua (`split()`) | `address` | — | Obrigatório | Via envio | — |
| `address_line1` → número (`split()`, 1 a 10 dígitos) | `number` | — | Obrigatório. S/N bloqueia | Via envio | — |
| `address_line2` (complemento) | `complement` | — | Opcional | Via envio | — |
| `dependent_locality` (bairro) | `district` | — | Obrigatório | Via envio | — |
| `locality` (cidade) | `city` | — | Obrigatório | Via envio | — |
| `administrative_area` (UF) | `state_abbr` | — | Obrigatório | Via envio | — |
| `country_code` = `BR` | `country_id` | — | Obrigatório (BR) | Via envio | — |
| Nome do destinatário (`profile`/usuário) | `name` | — | Obrigatório | Via envio | — |
| CPF do destinatário (`document`) | `document` | — | Obrigatório (pessoa física) | Via envio | — |

Origem (loja/depósito) segue a mesma estrutura, em configuração do submódulo. Campos de remetente (nome, documento) ainda
não têm fonte definida: ver DT-ME-03.

Rastreamento e cancelamento usam o identificador remoto da etiqueta, não o endereço.

## 4. Decisões

- O adaptador de CEP (`ShippingAddressAdapter`) continua sendo a sugestão para o administrador. Não preenche nada sozinho.
- O novo `MelhorEnvioAddressMapper` é puro: não faz HTTP, não grava, não consulta a base de CEP. Só normaliza e valida.
- `StreetNumberField::split()` é reutilizado por `use`. Não há regex própria no submódulo.
- O submódulo passa a depender de `aculta_portal:aculta_portal`, para usar `StreetNumberField`.
- A remessa usa o perfil de envio do pedido (snapshot). Não consulta o cadastro atual quando o pedido já saiu de rascunho.

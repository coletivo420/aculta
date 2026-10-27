# Auditoria do simulador de frete (ME-0.1-A)

Fase: ME-0.1-A do simulador de frete por CEP (página do produto, carrinho lateral e carrinho completo).
Objetivo deste documento: registrar a API do Melhor Envio que o módulo usa, o escopo OAuth2, o formato de cotação, as
fontes consultadas e a decisão de licença antes de qualquer código ser portado. Não contém valores de credencial.

## 1. Fontes consultadas

| Fonte | O que confirmou | Limite |
|---|---|---|
| Documentação OpenAPI do Melhor Envio, servida pelo servidor MCP `melhorenvio` (consulta em 2026-10-27) | Caminho, método, cabeçalhos, corpo, exemplo 200 e erro 422 do cálculo de fretes; URLs base de sandbox e produção | Não lista os escopos OAuth2 |
| `docs/operations/ACULTA-AGENT2-WORKLOG.md`, seção "ME-PEND-01 fechada" | Autorização real no sandbox; `expires_in` de 30 dias; `POST /api/v2/me/shipment/calculate` respondeu 200 com cotações; escopos efetivos | Evidência de sandbox do servidor de testes, não de produção |
| Pesquisa web (2026-10-27) | Nada específico do Melhor Envio: os resultados eram sobre OAuth2 em geral | Não confirmou os nomes dos escopos; a confirmação vem do sandbox acima |
| Código atual do submódulo (`src/Http`, `src/Quote`) | Fluxo de cotação, mapeamento de endereço e de erros já implementados | Não é fonte externa |

## 2. API usada

### 2.1 Ambientes

| Ambiente | URL base | Uso |
|---|---|---|
| Sandbox | `https://sandbox.melhorenvio.com.br` | Testes. Padrão do módulo (`environment: sandbox`). |
| Produção | `https://melhorenvio.com.br` | Só após a homologação e a chave de produção no banco (ver `docs/operations/melhorenvio-secrets.md`). |

O ambiente é escolhido pelo modo configurado (`environment`), não pelo formato da chave.

### 2.2 Autorização OAuth2

| Item | Valor | Fonte |
|---|---|---|
| Autorização | `GET /oauth/authorize` (com `client_id`, `redirect_uri`, `response_type=code`, `scope`, `state`) | Código atual, validado no sandbox (ME-PEND-01) |
| Token | `POST /oauth/token` (`authorization_code` e `refresh_token`) | Código atual, validado no sandbox |
| Escopos | `shipping-calculate users-read` | Sandbox (ME-PEND-01) |
| Validade do token | 30 dias (`expires_in` 2592000 s) | Sandbox (ME-PEND-01) |

Escopos recusados pelo sandbox: `user-read` (não existe; causa do `invalid_scope`). Os escopos válidos listados no
worklog são `shipping-calculate`, `cart-read`, `shipping-checkout`, `shipping-tracking`, `orders-read` e `users-read`.
Para a cotação só `shipping-calculate` é necessário; `users-read` fica por causa do `GET /api/v2/me` usado no
diagnóstico de conexão.

Cabeçalhos obrigatórios em toda chamada à API: `Accept: application/json`, `Content-Type: application/json`,
`Authorization: Bearer <token>` e `User-Agent` com o nome da aplicação e um e-mail de contato (sem e-mail, o módulo não
chama a API).

### 2.3 Cálculo de frete

`POST /api/v2/me/shipment/calculate`

Corpo usado pelo módulo (`QuoteRequestBuilder`):

```json
{
  "from": { "postal_code": "<CEP da origem, 8 dígitos>" },
  "to": { "postal_code": "<CEP do destino, 8 dígitos>" },
  "products": [
    { "id": "pacote", "width": 1, "height": 1, "length": 1, "weight": 0.01,
      "insurance_value": 0.0, "quantity": 1 }
  ],
  "options": { "receipt": false, "own_hand": false }
}
```

Campos obrigatórios da documentação: `from.postal_code`, `to.postal_code` e, por produto, `id`, `width`, `height`,
`length`, `weight`, `insurance_value` e `quantity`. Dimensões são inteiros em centímetros; peso é número em quilos.

Resposta 200: lista de serviços. Campos usados: `id`, `name`, `price`, `custom_price`, `custom_delivery_time`,
`delivery_time`, `company.name` e `error` (quando presente, o serviço é descartado). Valores monetários vêm como texto
(`"37.79"`) e são mantidos como texto, sem `float`.

Resposta 422: erros por campo (`from.postal_code`, `to.postal_code`). O módulo trata como `validation`.

### 2.4 Serviços aceitos

Serviços configurados no plugin (`aculta_melhorenvio`): PAC (1), SEDEX (2), .Package (3), .Com (4) e Mini Envios (17).
Os IDs seguem a documentação e o fixture do sandbox (`tests/fixtures/quote-sandbox.json`).

## 3. Decisões para o simulador

1. **Origem.** Vem só de `aculta_commerce_melhorenvio.settings:origin`, que é vazia por padrão. Sem origem, o estado é
   indisponível e nenhuma chamada é feita. Nenhum CEP de origem é fixado no código.
2. **Credencial.** O cliente só chama a API quando as duas chaves do Drupal Key do ambiente ativo existem. Sem elas, o
   estado é indisponível. Nenhuma chamada real sem credencial.
3. **Pacote.** O produto não tem peso nem dimensões cadastrados. O pacote padrão é cadastrado pelo responsável no
   formulário da origem (zero por padrão). Sem pacote, o estado é indisponível e nenhuma medida é presumida.
4. **Falha.** Erro de rede, HTTP ou credencial recusada vira estado "falha" para o visitante. O carrinho e o checkout
   nunca são interrompidos. Falhas não são guardadas em cache.
5. **Cache.** Só resultados de cotação bem-sucedidos (com ou sem opções) são guardados por CEP de destino e assinatura
   do pacote, por 5 minutos, com a tag de configuração do submódulo: mudar a origem invalida o cache.
6. **CEP.** A normalização e a consulta de endereço são do `aculta_portal_cep.lookup`. A cotação só precisa do CEP; não
   há segundo sistema de consulta.

## 4. Licença e portabilidade

- Não há módulo do Melhor Envio em `web/modules/contrib/` deste projeto. O contrib do site antigo não está disponível
  no checkout, e nenhum código dele foi copiado.
- A implementação segue a especificação pública da API (seção 2) e o código já existente do submódulo. Por isso não há
  atribuição GPL a registrar nesta fase.
- Se um dia houver porte de código de terceiros, a licença deve ser confirmada antes e a atribuição registrada aqui.

## 5. Link "Não sei meu CEP"

O link de ajuda aponta para a busca de CEP dos Correios. A URL usada é
`https://buscacepinter.correios.com.br/app/endereco/index.php`. O responsável confirmou a URL (2026-10-27). A URL fica
como propriedade do componente (`help_url`), não fixa no template.

## 6. Pendências que bloqueiam a ativação

| Item | Dono | Situação |
|---|---|---|
| CEP de origem da loja | Responsável | Pendente. Sem ele o simulador fica indisponível. |
| Pacote padrão (medidas e peso reais da embalagem) | Responsável | Pendente de cadastro no formulário da origem. Sem ele o simulador fica indisponível. |
| Confirmação da URL dos Correios | Responsável | Confirmada em 2026-10-27. |
| Validação de cotação no servidor de testes com a origem configurada | Frente | Pendente (RUNTIME STATUS: DEFERRED). |

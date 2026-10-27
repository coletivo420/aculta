# Decisões do simulador de frete

Registro das decisões do responsável sobre o simulador de frete por CEP (ME-0.1-C). Cada decisão tem a data, a regra
e o motivo. A auditoria da API está em `SHIPPING-SIMULATOR-AUDIT.md`.

## D1. Todo carrinho atualiza o frete ao mudar a quantidade (2026-10-27)

**Regra.** Qualquer mudança no carrinho refaz o cálculo de frete com o CEP já informado pelo visitante. Vale para todos
os carrinhos: carrinho completo, botão de remover e "Adicionar ao carrinho" (inclusive o AJAX, que atualiza a gaveta).

**Como é aplicado.** O recálculo é feito por `CartRefreshSubscriber`, que assina os eventos do `CartManager` do Commerce:
`commerce_cart.order_item.add`, `commerce_cart.order_item.update` e `commerce_cart.order_item.remove`. Todo caminho de
mudança de quantidade passa por esses eventos (`EditQuantity`, `RemoveButton` e `AddToCartForm`), então há um único
ponto de recálculo e nenhum duplicado.

**Por que não no submit do formulário.** Um submit só cobre o carrinho completo. Adição pelo produto e pelo AJAX não
passariam por ele, e a gaveta ficaria com valor antigo.

**Sem CEP guardado, nada é calculado.** O recálculo só refaz um cálculo que o visitante já pediu. Ele não calcula por
conta própria.

## D2. O carrinho lateral só mostra o frete já calculado (2026-10-27)

- A gaveta do carrinho não tem campo de CEP e não calcula.
- Ela lê o último cálculo do visitante (componente `shipping-summary`). Sem cálculo, orienta a calcular na página do
  produto ou no carrinho completo.
- O carrinho AJAX (`portal-cart-panel`) recebe o mesmo resumo.

## D3. O cálculo acompanha o visitante do produto até o carrinho (2026-10-27)

- O endpoint `/frete/simular` guarda o último cálculo na sessão, com o CEP normalizado e o resultado formatado.
- Validade: 15 minutos (`ShippingQuoteStore::TTL`). Passado o prazo, o cálculo sai da sessão.
- CEP inválido não é guardado.
- Não são guardados outros dados pessoais.

## D4. O cálculo usa o cache curto do simulador (2026-10-27)

- O resultado do Melhor Envio é guardado por CEP e pacote, por 5 minutos (`ShippingSimulator::CACHE_TTL`).
- Falhas não são guardadas: a próxima consulta tenta de novo.
- Mudar a origem invalida o cache pela tag de configuração.

## D5. Sem pacote real, o frete fica indisponível (2026-10-27)

- O produto não tem peso nem dimensões cadastrados. O pacote padrão do motor tem dimensão zero e não é usado: cotar com
  ele daria preço de uma caixa que não existe.
- Enquanto não houver pacote, todo cálculo responde "Cálculo de frete indisponível no momento.", mesmo com origem e
  credencial configuradas.

## Pendência aberta: ordem do recálculo quando o pacote vier do carrinho

Hoje o recálculo não lê o carrinho (o pacote é `NULL`), então rodar antes do save não causa erro. Quando o pacote passar
a vir do carrinho:

- `EditQuantity` dispara o evento de atualização **antes** de `$cart->save()`. O recálculo teria de ler o carrinho já
  salvo, por exemplo movendo o refresh para depois do save (`KernelEvents::TERMINATE` ou um submit posterior).
- Registrar a mudança aqui antes de implementar.

## Verificação

- Testes unitários: `ShippingCartRefresherTest`, `CartRefreshSubscriberTest`, `ShippingQuoteStoreTest`,
  `ShippingCalculatorHooksTest` e `ShippingSimulatorTest`.
- Wiring: evento disparado no dispatcher do container, sem gravação no banco.
- Não verificado: submissão real do carrinho com itens e quantidade alterada, no navegador. Depende de produto com
  pacote, que ainda não existe. Registrar como RUNTIME STATUS: DEFERRED.

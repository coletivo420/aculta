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

## D5. Pacote padrão de envio: 16 × 2 × 11 cm, 0,3 kg (2026-10-27)

**Decisão.** O responsável pediu um pacote padrão de e-commerce. Ele fica na configuração
(`aculta_commerce_melhorenvio.settings:package`), com 16 cm de largura, 2 cm de altura, 11 cm de comprimento e 0,3 kg.
Foi gravado no servidor de testes.

**Pesquisa (fontes).**
- Página oficial dos Correios, "Envios pelos Correios: Encomendas", seções PAC e SEDEX: envelope mínimo 16 × 11 cm;
  pacote mínimo 11 × 6 × 0,4 cm; maior lado 100 cm; soma das dimensões 200 cm; peso máximo 30 kg.
  Fonte: https://www.correios.com.br/enviar/encomendas/nacional
- Envelope é cobrado como envelope quando tem até 2 cm de altura. O envelope mínimo (16 × 11 cm, altura de 2 cm) é o
  formato usado em e-commerce de roupa.
- Fontes de terceiros divergem entre si: um blog do Melhor Envio cita mínimo de 1 × 8 × 13 cm, e a biblioteca
  `gigapix/frete-correios` cita 16 × 11 × 2 cm. Por isso a regra oficial dos Correios prevalece.
- O valor de 105 cm por lado citado na conversa não existe nas regras oficiais: o máximo é 100 cm por lado e 200 cm de soma.

**Suposição a confirmar.** O peso de 0,3 kg é de uma camiseta típica. Não consta na pesquisa, e não é do catálogo.
Se o pacote real for outro, o responsável ajusta no formulário da origem.

**Regras que continuam valendo.**
- Zero em qualquer campo = não cadastrado; sem pacote o frete fica indisponível.
- O valor declarado (seguro) não entra na cotação: o pacote padrão sai com seguro zero.
- Caixas maiores que 16 × 2 × 11 cm não são cobertas por este padrão. Para elas, o responsável cadastra o pacote real.

## Pendência aberta: ordem do recálculo quando o pacote vier do carrinho

Hoje o recálculo não lê o carrinho (o pacote é `NULL`), então rodar antes do save não causa erro. Quando o pacote passar
a vir do carrinho:

- `EditQuantity` dispara o evento de atualização **antes** de `$cart->save()`. O recálculo teria de ler o carrinho já
  salvo, por exemplo movendo o refresh para depois do save (`KernelEvents::TERMINATE` ou um submit posterior).
- Registrar a mudança aqui antes de implementar.

## Verificação

- Cotação real no sandbox (origem já configurada no servidor de testes, pacote temporário só em memória, sem gravação):
  PAC R$ 23,00 em 7 dias, SEDEX R$ 51,93 em 3 dias, Mini Envios R$ 17,85 em 10 dias. Endpoint `/frete/simular` com
  CEP válido responde disponível com essas opções; CEP inválido responde indisponível sem chamar a API.
- Testes unitários: `ShippingCartRefresherTest`, `CartRefreshSubscriberTest`, `ShippingQuoteStoreTest`,
  `ShippingCalculatorHooksTest` e `ShippingSimulatorTest`.
- Wiring: evento disparado no dispatcher do container, sem gravação no banco.
- Não verificado: submissão real do carrinho com itens e quantidade alterada, no navegador. Depende de produto com
  pacote, que ainda não existe. Registrar como RUNTIME STATUS: DEFERRED.

## D6. Medidas não preenchidas usam o padrão (2026-10-27)

- Peso e dimensões da variação deixam de ser obrigatórios (configuração do servidor de testes alterada).
- Cada medida efetiva é a da variação, quando cadastrada, e nunca fica abaixo do padrão. Medida não cadastrada usa o
  padrão (16 × 2 × 11 cm, 0,3 kg).
- Sem padrão configurado e sem medida na variação, não há pacote: indisponível.

## D7. Carrinho com vários itens: cada item vai à API, que monta as caixas (2026-10-27)

- Pesquisa: a documentação oficial do Melhor Envio (cálculo de fretes) aceita uma lista `products`, com medida,
  quantidade e valor de cada item, e a resposta devolve as `packages` já montadas.
- Decisão: o módulo não soma pesos nem dimensões. Cada item do carrinho vai com a própria medida, a quantidade e o
  valor declarado da linha (seguro). A API consolida as caixas.
- Verificado: o pedido de teste (1 item, R$ 30,00) foi cotado no sandbox com PAC, SEDEX e Mini Envios.
- Cache por CEP e carrinho inteiro, com a mesma validade de 5 minutos.

## D8. Último CEP informado e CEP do perfil (2026-10-27)

- O simulador guarda o último CEP válido informado: em sessão para visitantes e em `user.data` privado para pessoas autenticadas. O registro autenticado é removido pelo Core junto com os dados da conta; não há tabela ou atualização de esquema nova.
- Se a pessoa autenticada ainda não tiver usado o simulador, o campo recebe o CEP do perfil `authenticated` (`field_address`). Um CEP usado explicitamente depois passa a prevalecer e é calculado ao abrir o componente.
- Só o CEP normalizado é guardado como preferência. A cotação continua tendo TTL de 15 minutos na sessão; o CEP salvo não tem prazo independente e acompanha a sessão do visitante ou a conta autenticada.
- Essa finalidade foi incluída na minuta da Política de Privacidade e nos Termos; ambos continuam minutas ainda não publicadas. A publicação em produção deve aguardar a publicação dos textos.

## D8. Frete só para a loja; carrinho lateral cota com o CEP informado (2026-10-27)

- O cálculo de frete é restrito à loja. Entram só itens de produto da loja (bundle `produto_loja`) em pedidos do tipo da
  loja (`default`). LMS (`produto_curso`), doações (pedido `doacao`), cursos e assinaturas não entram no frete, e um
  carrinho só com eles não tem frete a calcular.
- O carrinho lateral mostra o frete quando o comprador informou o CEP em algum campo (produto, carrinho ou conta
  persistida). Para isso, cota o carrinho atual com esse CEP, usando o cache do simulador. A gaveta não tem campo de CEP.
- Verificado no navegador: frete PAC, SEDEX e Mini Envios na gaveta após informar o CEP no produto e adicionar o item.
- Não verificado no navegador: pedido real de LMS ou de doação (não há esses produtos cadastrados no servidor de testes).
  Coberto por teste unitário de inclusão e exclusão de linhas.

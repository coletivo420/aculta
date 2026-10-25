# Green Stash — Contrato de operações sem painel administrativo

**Decisão 2026-10-23 — normativa.** Green Stash é a experiência operacional integral de Loja dentro do ACULTA Studio. A edição administrativa padrão de Commerce não é a interface de uso de operadores de loja.

## Proibições e fronteiras

- **Não** expor aos operadores botões `commerce_product->toUrl('edit-form')`, links diretos para `/painel-administrativo`, `/admin/commerce` ou edição administrativa de entidades. No início do trabalho remover links existentes e auditar novos componentes.
- **Não** duplicar produtos, variações, pedidos, payments, shipments ou saldos em entidades paralelas. Manter ownership e validação nativa Commerce, Commerce Stock e Commerce Shipping.
- Formulários **próprios de apresentação no Studio**, com Form API/Entity APIs/serviços Commerce, submissão autorizada e ações por operação. Reutilizar widgets e validações relevantes do Commerce via adaptação, **sem renderizar telas administrativas nativas indiscriminadamente**.
- Somente UI Suite/SDC do tema ACULTA420 para apresentação, tokens e mobile-first; nenhum shell ou cabeçalho novo. Preservar checkout e carrinho multidomínio.
- IDs internos de serviços podem continuar inglês. **Todas as rotas públicas e slugs em PT-BR.**

## Rotas propostas (não implementadas até existir form/controller e gates)

| Operação | Rota PT-BR | Autorizações |
|---|---|---|
| Catalogar | `/estudio/loja/produtos` | Entrada Studio + acesso à(s) loja(s) |
| Novo produto | `/estudio/loja/produtos/novo` | create commerce_product + store |
| Produto | `/estudio/loja/produtos/{produto}` | view entity + store |
| Editar | `/estudio/loja/produtos/{produto}/editar` | update entity + store, CSRF em POST |
| Variações | `/estudio/loja/produtos/{produto}/variacoes` | update/product variation |
| Estoque | `/estudio/loja/estoque` e `.../movimentacoes` | Commerce Stock, grants e auditoria |
| Pedido | `/estudio/loja/pedidos/{pedido}` | view order + store |
| Preparar | `/estudio/loja/pedidos/{pedido}/preparar` | transição do workflow + store |
| Envios | `/estudio/loja/envios` | Commerce Shipping |
| Etiqueta | `/estudio/loja/envios/{remessa}/etiqueta` | remessa + operação financeira confirmada + Melhor Envio |
| Configurações | `/estudio/loja/configuracoes` | grants específicos por opção |

Não adicionar rotas com placeholders perigosos antes de implementar controladores, verificação de proprietário da entidade/store, parâmetros convertidos e formulários de destino válidos. `/painel-administrativo` permanece para manutenção Drupal por administradores técnicos, fora do fluxo cotidiano.

## Organização interna proposta

- **Produtos:** Todos / Rascunhos / Novo / Variações / Imagens / Preços. Assistente de cadastro com passos básicos > variações > imagens > disponibilidade > revisão/publicação, rascunhos persistidos pelo modelo Commerce.
- **Pedidos:** Novos / Pagos / Preparação / Concluídos / Cancelados. Detalhe com itens, pagamento somente leitura inicial, expedição e histórico. Não misturar alteração de pagamentos com fulfillment.
- **Estoque:** Disponibilidade, locais, movimentações, reposição; controles são do Commerce Stock; edição sem alterações diretas de quantidade bruta fora da API de estoque.
- **Envios:** Cotação, volumes, compra autorizada de frete, etiquetas PDF/ZPL, reimpressão, rastreamento; usa o endereço unificado ACULTA e o Melhor Envio.
- **Demais áreas:** promoções, clientes autorizados, métricas Statistics e configurações; sem fallback para formulários admin.

## Implementar em fases

1. **GS-EDITOR-0.1 — bloqueio de saída:** auditar e remover links nativos das interfaces Green Stash; não oferecer `Editar` inútil. Documentação e teste estático de inexistência de redirecionamentos administrativos.
2. **GS-EDITOR-0.2 — leitura contextual:** detalhe interno do produto (somente leitura) por route parameter e accessCheck da entidade **e** store, integração com listagem e ações apenas quando tiverem destino funcional.
3. **GS-EDITOR-0.3 — formulário de criação:** usar Form API e APIs Commerce, com validações existentes e testes Kernel/Functional; composição Studio responsiva.
4. **GS-EDITOR-0.4 — formulário de edição e variações:** edição segura com concorrência, validações e testes; conservar revisões/workflow se suportados.
5. **GS-EDITOR-0.5 — estoque e logística:** implementar fluxos internos após serviços Commerce Stock/Melhor Envio serem homologados.
6. **GS-EDITOR-T:** validar operadores sem acesso admin Drupal com rotas Studio funcionais, autorização por store, CSRF, IDOR, regressão do checkout, UI sem JS, 320px/light/dark e volume.

## Gate de aceitação

Um operador com permissão de loja mas sem permissão de administração técnica deve concluir **criar, editar, publicar, gerenciar estoque, preparar envio e gerar etiqueta** (esta última com autorização financeira) inteiramente no Green Stash, sem abrir URL administrativa do Drupal. Cada operação usa entidades e serviços proprietários, sem duplicação de armazenamento. Não declarar gate aprovado enquanto todos os fluxos não estiverem implementados/homologados.

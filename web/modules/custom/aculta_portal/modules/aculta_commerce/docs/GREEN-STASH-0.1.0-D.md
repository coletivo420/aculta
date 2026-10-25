# ACULTA Commerce 0.1.0-D — Green Stash, fundação de segurança

**Status:** preparação parcial, sem rota nem provider Studio ativados. Data: 2026-10-22.

## Evidência da API Studio

`aculta_studio/src/Experience/StudioExperienceProviderInterface.php` determina `id/label/purpose/routeName/available/access/build`. `StudioExperienceManager` registra providers via `addProvider` e checa disponibilidade e purpose, além de acesso. `aculta_studio.services.yml` atualmente registra explicitamente GanjaLab420. Não acrescentar provider Green Stash a essa lista antes de estabelecer uma rota própria e validar domínio e permissões.

## Entregue sem interferir no público

- `GreenStashAccessPolicy::access()` exige permissão explícita `access aculta green stash`. Administradores sem essa permissão não devem receber acesso automaticamente por terem `view commerce_order`; nenhuma role recebeu a permissão nesta fase.
- Permissão restrita `access aculta green stash`, declarada em `aculta_commerce.permissions.yml`.
- Serviço `aculta_commerce.green_stash_access` e teste unitário da política.
- O gate é só de **entrada**. Criar/editar produtos, visualizar pedidos e reembolsar pagamentos exigirão também autorização nativa Commerce por entidade, store e operação.

## Próximos incrementos, nesta ordem

1. **D-A:** auditar propósito de domínio da interface Studio, rotas e consumo da experiência, identificar caminho canônico sem conflito com `/studio/ganjalab` e cabeçalho multidomínio.
2. **D-B:** criar provider `green_stash` aderente a `StudioExperienceProviderInterface`, sem acoplar ao GanjaLab; registrar dependência de Studio somente no momento da integração e evitar registro cruzado circular.
3. **D-C:** rota de administração e controller com acesso explícito, cache metadata e render arrays básicos; não expor listagem de pedidos antes do filtro store/order-type.
4. **D-D:** componentes SDC/UI Suite exclusivamente no tema ACULTA420, mobile-first e com fallback sem JS.
5. **D-T:** testes no homelab de permissão, papéis, domínio, acesso por entidade e UI; validar os três checkouts e o carrinho na barra multidomínio inalterados.

## Dívida de ambiente

A permissão e o serviço ainda não foram validados por `drush cr`/PHPUnit no servidor. Não declarar PASS. A ativação do módulo continua dependente do gate de dependências `aculta_commerce.info.yml`. Não criar dados de produtos/pedidos nem alterar gateway Mercado Pago nesta fase.

## D-A/D-B implementado sem operações comerciais (2026-10-22)

- `GreenStashExperienceProvider` implementa o contrato Studio existente com ID `green_stash`, purpose `account` e rota `aculta_commerce.green_stash`.
- Rota administrativa própria `/green-stash`, permission gate estrito; não coincide com GanjaLab.
- `GreenStashController` reutiliza `StudioExperienceManager` para disponibilidade, purpose e acesso; provider entrega apenas mensagem preparatória, **não** produtos, pedidos, estatísticas privadas ou funções financeiras.
- `aculta_commerce_container_alter` registra o provider no serviço Studio somente quando Commerce está habilitado, preservando GanjaLab e evitando referência obrigatória a Commerce dentro do Studio.
- Adicionada dependência explícita a `aculta_studio`. Validar namespace do dependency key e ordem real de serviços no homelab; cache rebuild, container compilation, route access, guest/auth e permission matrix permanecem pendentes.
- **Não houve alteração no carrinho público, consolidador, checkout ou Mercado Pago.**

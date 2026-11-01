# Busca postal por CEP — contrato vigente

**Status:** D-57 aprovada em 2026-10-31. O plano anterior de busca por nome de rua/logradouro está cancelado e não é backlog executável.

O ACULTA mantém exclusivamente a consulta e o preenchimento por CEP já fornecidos por `aculta_portal_cep`. Isso vale para Events, Maps, Conta, checkout e demais formulários. Em formulários completos, Brasil permanece fixo e CEP é o primeiro campo visível. Conta e checkout conservam o comportamento atual.

No cadastro de Local, o popup Drupal/AJAX começa pelo CEP. O preenchimento por CEP não impede edição manual dos campos. A busca por nome/localidade para encontrar Locais já cadastrados continua disponível; ela não busca endereço postal.

A geocodificação de um endereço já inserido é distinta da busca postal e continua sob o contrato existente de `aculta_maps`. Não criar endpoints, clientes, autocomplete ou integração com provedores de busca por logradouro.

Pesquisa histórica de fornecedores, sem efeito normativo: [PESQUISA-PROVEDORES-BUSCA-LOGRADOURO.md](../architecture/PESQUISA-PROVEDORES-BUSCA-LOGRADOURO.md).

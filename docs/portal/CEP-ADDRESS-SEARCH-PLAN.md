# Plano: busca por nome de rua no ACULTA Portal CEP

**Estado:** planejado; não implementado. A consulta existente de CEP preenche
os campos de endereço. Este plano trata de uma capacidade diferente: pesquisar
digitando o nome de uma rua/logradouro. Não apresentar essa busca como recurso
disponível.

No formulário de endereço de local usado por Maps/Events, o CEP já preenche os
campos postais pelo mesmo `aculta_portal_cep` e formulário central usados no
restante do site. Essa integração por CEP é comportamento atual; não faz parte
da funcionalidade futura descrita neste plano.

## Objetivo

Estender futuramente `aculta_portal_cep` com um serviço postal de candidatos por rua/logradouro. A interface e ativação dessa busca ficam restritas aos fluxos de Events e Maps. Conta e checkout mantêm a consulta atual por CEP, sem busca por logradouro. O componente central continua responsável pela captura do endereço; o consumidor controla se a busca restrita é apresentada.

## Arquitetura obrigatória

- `aculta_portal` continua dono do único formulário completo de endereço
  (`AccountAddressForm`) e dos adaptadores para entidades. Nenhum módulo cria
  uma segunda Form API, widget completo ou cliente de CEP.
- `aculta_portal_cep` continua dono do serviço de CEP existente e da biblioteca de preenchimento. Uma futura API postal por logradouro pode ser implementada no módulo, mas sua interface será habilitada exclusivamente nos contextos de Events/Maps. Conta e checkout não a consomem.
- `aculta_events` mantém os dados de local/evento; `aculta_maps` consome esses
  dados e oferece renderização/geocodificação conforme seus contratos. Maps
  não passa a armazenar endereço nem a manter uma interface postal paralela.
- A geocodificação existente do Maps converte endereço salvo em coordenadas.
  Ela não equivale à futura busca de candidatos postais nem deve ser usada para
  preencher rua, bairro, cidade ou UF.
- Conta, locais e eventos continuam com seus modelos, validações e controles de
  acesso próprios. Nos formulários de locais/eventos, país fixo BR, sem seletor.

## Etapas propostas

1. **Contrato e provedores:** definir dados de entrada/saída dos candidatos,
   cobertura territorial, fonte(s), atribuição e termos de uso. Revisar
   privacidade, transferência de dados, limites e disponibilidade antes de
   escolher ou ativar provedor. Nenhum provedor está aprovado por este plano.
2. **Serviço compartilhado:** desenhar uma API de lookup em
  `aculta_portal_cep`, com normalização, resultados limitados, erros claros,
  timeouts, cache e limites de consulta. Separar a busca por nome de rua da
  consulta por CEP já existente e do serviço de coordenadas de `aculta_maps`.
3. **Integração contextual:** apresentar candidatos de forma acessível apenas nos fluxos de Events/Maps, permitindo seleção explícita e preenchendo somente subcampos postais. Não salvar automaticamente nem sobrescrever número, complemento ou valores digitados. Não mostrar o controle na Conta ou checkout.
4. **Consumidores:** implementar a interface somente em Events e Maps, reutilizando formulário/adaptador central e serviço. Não ativar em Conta, checkout ou outros consumidores. A UI de busca por logradouro é específica desses contextos, não um comportamento global do formulário.
5. **Validação e ativação:** cobrir testes de serviço e interface, cache,
   privacidade, erros/timeouts, acessibilidade por teclado/leitor de tela,
   reduced motion, AJAX/no-JS e viewports móveis. Documentar termos e atribuição
   e obter aprovação antes de ligar chamadas externas em Runtime.

## Critérios para iniciar implementação

- Provedor(es), termos, atribuição e tratamento de privacidade revisados.
- Contrato neutro de candidatos definido e compatível com `Address`/`Form API`.
- Limites, timeout, cache e proteção contra enumeração/abuso definidos.
- Plano de testes de não sobrescrita, teclado, mensagens de erro e ausência de
  JavaScript aprovado.
- Integração opt-in apenas para Events/Maps. A implementação não pode exigir alteração do armazenamento da Conta/checkout nem mudar sua UI atual.

Até esses critérios serem satisfeitos, a busca por nome de rua deve permanecer
fora da UI e não deve ser anunciada como funcionalidade. A consulta atual por
CEP continua sendo a única busca postal disponível. Consulte também
[`ACCOUNT-ADDRESS.md`](ACCOUNT-ADDRESS.md) e o plano de
[`aculta_maps`](../../web/modules/custom/aculta_portal/modules/aculta_maps/docs/PLANO-IMPLANTACAO.md).


## Limite de produto e integração com Locais

- A consulta CEP → endereço existente continua sendo o comportamento padrão compartilhado. Este plano não altera o endpoint, cliente ou experiência de Conta/checkout.
- A busca por logradouro/endereço é exclusivamente uma capacidade de Events/Maps, ainda sem provedor aprovado ou implementação disponível. Resultados próximos devem ser retornados por AJAX e selecionados explicitamente; nunca salvar automaticamente um candidato.
- No cadastro de Local iniciado em `/node/add/activity`, abrir modal Drupal/AJAX com CEP como primeiro campo e reutilizar `AccountAddressForm`/adaptador e `aculta_portal_cep/cep-address`. Busca por logradouro só pode aparecer nesse fluxo de Events e em Maps.
- A interface de Local deve permitir pesquisar Locais existentes por nome, selecionar um deles ou abrir o cadastro de novo Local. O fluxo pai mantém valores e referência durante rebuilds AJAX.
- Ver [ADR-033](../decisions/ADR-033-endereco-busca-e-locais.md) e o [plano LOC](../architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md).

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

Estender futuramente o `aculta_portal_cep` para localizar candidatos a partir
do nome de uma rua/logradouro e permitir que a pessoa escolha um resultado para
preencher os campos postais do formulário central. A busca deve compor o mesmo
fluxo de captura já usado por Conta, Eventos e locais consumidos por Maps.

## Arquitetura obrigatória

- `aculta_portal` continua dono do único formulário completo de endereço
  (`AccountAddressForm`) e dos adaptadores para entidades. Nenhum módulo cria
  uma segunda Form API, widget completo ou cliente de CEP.
- `aculta_portal_cep` continua dono dos serviços/clientes de consulta postal e
  da biblioteca de preenchimento. A futura busca pelo nome da rua será uma
  capacidade adicional compartilhada desse módulo, consumida pelo formulário
  central. A consulta CEP → endereço que já existe continua disponível.
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
3. **Integração do formulário:** apresentar candidatos de forma acessível no
   formulário central, permitir seleção explícita e preencher apenas os
   subcampos postais correspondentes. Não salvar automaticamente nem sobrescrever
   silenciosamente número, complemento ou valores que a pessoa já digitou.
4. **Consumidores:** ativar primeiro em um consumidor aprovado e verificar que
   Eventos/locais e quaisquer consumidores futuros continuam usando o mesmo
   formulário, biblioteca e serviço. Não criar UI exclusiva do produto Maps.
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
- Nenhuma alteração nos modelos de local/evento ou no armazenamento da Conta
  necessária para apresentar a busca.

Até esses critérios serem satisfeitos, a busca por nome de rua deve permanecer
fora da UI e não deve ser anunciada como funcionalidade. A consulta atual por
CEP continua sendo a única busca postal disponível. Consulte também
[`ACCOUNT-ADDRESS.md`](ACCOUNT-ADDRESS.md) e o plano de
[`aculta_maps`](../../web/modules/custom/aculta_portal/modules/aculta_maps/docs/PLANO-IMPLANTACAO.md).

# Endereço unificado do site (aculta_portal + aculta_portal_cep)

**Decisão do responsável (2026-10-30) — País exclusivo Brasil (BR) e validação no servidor (ADR-034):**
O país é sempre o Brasil (`BR`) em todo o projeto ACULTA. É terminantemente proibido exibir qualquer opção de seleção de país em formulários de endereço ou qualquer outro local do sistema. Formulários de endereço devem configurar exclusivamente `country_code = BR`. A validação e confirmação no servidor são obrigatórias tanto ao validar quanto ao persistir a entidade: JavaScript ou campo oculto, por si sós, não substituem a validação do servidor. Entradas vazias ou nulas são normalizadas para `BR`, e valores divergentes de `BR` são rejeitados com erro de validação. A busca de CEP deve continuar usando centralmente a biblioteca `aculta_portal_cep/cep-address`, preenchendo os dados postais brasileiros e preservando número, S/N e complemento conforme o contrato central vigente.

**Decisão do responsável (2026-10-29):** o site deve ter uma única implementação central de formulário para captura e edição de endereço, mantida em `aculta_portal` e baseada na biblioteca/serviço do módulo `aculta_portal_cep`. Todos os módulos e fluxos que capturam ou editam endereços devem chamar/adaptar esse formulário central; é proibido criar um formulário paralelo de endereço ou duplicar sua integração CEP. Esta é a arquitetura esperada, não apenas uma proteção contra regressões.

**Escopo confirmado:** a decisão inclui endereços de locais e eventos em `aculta_maps` e `aculta_events`, além de Conta, Studio, checkout, lojas, perfis de equipe e quaisquer outros consumidores encontrados. Para locais/eventos o país é sempre Brasil, sem seletor de país; o adaptador usa o widget e a biblioteca CEP centrais, enquanto os dados permanecem nas entidades próprias. Importadores podem mapear endereços de fontes externas, mas qualquer formulário de captura/revisão apresentado à pessoa deve usar o contrato central.

Nos formulários de local usados por Maps/Events, a pessoa informa o CEP no mesmo campo postal e a consulta existente de `aculta_portal_cep` preenche rua, bairro, cidade e UF. Isso já está implementado por `EventLocation.field_endereco` e pela revisão do importador OSM; não é uma capacidade pendente.

**Plano futuro (não implementado):** a busca/autocomplete por nome de rua/logradouro e resultados próximos é uma interface restrita a Events e Maps. O serviço postal compartilhado pode residir em `aculta_portal_cep`, mas não deve ativar busca por rua na Conta, checkout ou formulário global. A consulta CEP → endereço já existente permanece padrão e não muda nesses fluxos. Provedor, termos, atribuição, privacidade e limites ainda precisam de aprovação. Veja [`CEP-ADDRESS-SEARCH-PLAN.md`](CEP-ADDRESS-SEARCH-PLAN.md) e [ADR-033](../decisions/ADR-033-endereco-busca-e-locais.md).

A decisão amplia e consolida o comportamento aprovado em 2026-10-18. Qualquer mudança no formulário, CEP, perfil `authenticated`, checkout ou consumidor de endereço deve preservar o contrato descrito aqui. Não repetir mudanças sem atualizar esta decisão e executar os gates pertinentes.

## Comportamento esperado

- O endereço da conta é o campo `field_address` do perfil `authenticated` (fonte única). A cobrança do checkout lê o mesmo perfil
  (`BillingAddressPane`, perfil `authenticated`); o pedido guarda uma cópia sincronizada.
- Formulário: CEP, Endereço/Logradouro, Número, S/N, Complemento, Bairro, Cidade, Estado. **Sem seletor de país**: o país é
  Brasil (campo oculto `country_code = BR`).
- Depois que um CEP é resolvido, os campos que vieram dele (rua, bairro, cidade e UF) ficam bloqueados para edição, mas visíveis. Os campos que a busca não preencheu (por exemplo, o bairro de um CEP sem bairro na fonte) continuam editáveis. Número, S/N e complemento nunca são bloqueados. Cidade e UF (selects) ficam desabilitados, sem abrir a lista; o valor segue no envio por um input hidden. Digitar outro CEP desbloqueia e refaz o preenchimento.
- Número: obrigatório, só dígitos, até 10. S/N desabilita o número e o limpa.
- CEP digitado (8 dígitos) consulta `aculta_portal_cep/{cep}` (endpoint do submódulo `aculta_portal_cep`, que exige o cabeçalho AJAX) e preenche:
  - no **primeiro** preenchimento: só os campos vazios (não apaga o que a pessoa digitou);
  - ao **trocar** o CEP depois do preenchimento: rua, bairro, cidade e estado são substituídos pelo endereço do novo CEP;
  - número, S/N e complemento **nunca** são alterados pela busca.
- Salvar grava o endereço do último CEP, com o número digitado.

## Implementação central obrigatória

- A implementação central é `src/Account/AccountAddressForm.php`: país fixo BR, rótulos, Número e S/N (`StreetNumberField`), biblioteca `aculta_portal_cep/cep-address` e gravação do número (`buildStreetNumber`). A consulta de CEP fica no módulo `aculta_portal_cep`; consumidores não podem duplicar endpoint, consulta ou comportamento do cliente.
- Todos os formulários de captura/edição de endereço do site usam este contrato. Consumidores confirmados:
  - Minha conta > Endereço (`/dados/endereco`, `PortalHooks`);
  - checkout, pane de cobrança `aculta_billing_address` (`BillingAddressPane`, inline `customer_profile` do perfil `authenticated`);
  - cadastro da loja (`commerce_store`, campo `address`) e perfis de equipe (`administrator`, `associado`, `gerente_loja`, `lms_teacher`, campo `field_address`), pelo hook `AccountAddressHooks`.
- locais de evento (`aculta_event_location.field_endereco`), pelo mesmo hook; o campo limita os países a BR e o formulário oculta o código de país.
- revisão de endereço no assistente OSM: usa o elemento `address` e `aculta_portal_cep/cep-address`, depois adapta os valores às tags selecionadas. Número de residência mantém o formato OSM (inclusive alfanumérico); não altera a validação numérica de Conta.
- Conta legada (`/conta-legada` → Meus Dados → `/dados/endereco`) e o editor equivalente do Studio usam a mesma implementação central.
- Os dados continuam nos respectivos modelos/entidades (perfil do cliente, pedido, loja, perfil de equipe ou entidade de local); só a interface e o contrato de captura são compartilhados. Adaptadores podem mapear campos, mas não criar uma segunda experiência Form API.
- Formulários de configuração de integração logística que só recebem CEP de origem e importadores que mapeiam dados externos não são uma segunda interface de endereço para pessoas; se passarem a capturar/editar endereço completo, devem migrar para o formulário central.
- Endereços estruturados pertencentes a entidades de local/evento/instituição exigem preservar seu modelo e semântica. Qualquer formulário de edição desses endereços também deve reutilizar o componente central, com adaptador de entidade quando necessário; a decisão não autoriza fundir modelos de dados distintos.
- Em formulários de endereços de locais/eventos, configure o país como `BR` e não renderize seletor de país. Busca/preenchimento por CEP deve anexar `aculta_portal_cep/cep-address` por meio do adaptador central. Restrição de busca geográfica do mapa e formato de importação OSM não substituem nem duplicam a captura de endereço.
- Perfis de equipe (`administrator`, `associado`, `gerente_loja`, `lms_teacher`) exibem `field_address` com o widget padrão `address_default`, igual ao perfil `authenticated` (configuração em `core.entity_form_display.profile.*.default.yml`). Antes estava oculto.
- O checkout anexa a biblioteca `cep-address` ao formulário da página (`$complete_form`), não ao `#attached` do pane, que o Commerce não leva à página.
- Verificação no Runtime de teste (2026-10-18): loja e equipe montam com país oculto, biblioteca do CEP e Número/S/N; checkout renderiza o formulário padrão e carrega a biblioteca do CEP. Teste de navegador da Minha conta 30/30 na entrega anterior; conferência visual e teste de navegador do checkout pendentes (ver CHANGELOG 0.4.0-dev.147).

## Lacunas registradas para implementação futura

- `node:add/activity` ainda apresenta campos textuais separados de endereço. Remover esses campos somente junto da integração da Activity com a entidade Local de Events, preservando/mapeando dados existentes.
- `block_content.aculta_institution` mantém campos estruturados textuais de endereço sem evidência de adaptador central. Fazer inventário de usos, renderização e Schema.org; depois adaptar o formulário central sem fundir ou apagar campos.
- Events precisa do seletor/cadastro modal de Local com AJAX, CEP primeiro, resultados existentes por nome e eventual busca por logradouro/resultados próximos restrita a Events/Maps. A funcionalidade de busca por logradouro não existe ainda.

## Por que cada regra existe (regressões já ocorridas)

- O perfil `authenticated` também é tipo de papel. O bloco de papel de `PortalHooks` não pode capturar `/dados/endereco`, senão
  o número e o país padrão somem.
- País oculto como `hidden`, e não `#access = FALSE`: o módulo Address descarta toda a entrada do formulário quando o
  `country_code` não vem no POST, e a lista de cidades passa a usar o endereço salvo.
- A biblioteca `viacep` do contrib não carrega o cliente dele (chama o endpoint sem o cabeçalho e recebe 403). A busca é a do Portal.
- O CEP resolvido fica por campo no módulo, e não no elemento: o AJAX de estado troca o elemento.
- Alias `address` nos perfis: campo computado, sem armazenamento, que lê o `field_address` do mesmo perfil. É o que o contrib do Mercado Pago espera. Não pode virar campo com armazenamento nem cópia.

## Como validar antes de mesclar

1. Gate (sem navegador): `php scripts/validate-account-address.php` (deve ser PASS).
2. Navegador (Firefox pelo X11, `DISPLAY=:0`, conta de teste `teste.comum`): gerar o link de login de uso único e rodar

       php vendor/drush/drush/drush.php -l conta.aculta.toca.net.br uli --uid=5 > link.txt
       DISPLAY=:0 python3 -I scripts/test-account-address-browser.py link.txt

   Deve terminar com `RESULTADO: 17/17 verificações OK`. Gerar o link antes de cada execução (é de uso único).

## Uso pelo Melhor Envio (logística)

- O Melhor Envio não tem formulário de endereço. O formulário único deste documento é a fonte de verdade para envio.
- Rua e número são separados por `StreetNumberField::split()`. Regra de número: 1 a 10 dígitos ou S/N. Não alterar sem decisão do responsável.
- Contrato, matriz de campos e bloqueios (S/N na contratação) em `web/modules/custom/aculta_portal/modules/aculta_commerce/modules/aculta_commerce_melhorenvio/docs/ADDRESS-CONTRACT.md`.

## Pendências fora deste documento

- Mercado Pago (checkout): o contrib lê o campo `address`, que os perfis não têm. Adaptador pendente; não pode ser um campo
  alias (ver acima). Ver `docs/portal/MERCADO-PAGO-PLANO.md`, DT-MP-10.

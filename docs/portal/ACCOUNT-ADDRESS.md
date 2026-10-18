# Endereço da conta (Minha conta > Meus dados > Endereço)

Comportamento aprovado pelo responsável (2026-10-18). Qualquer mudança no endereço da conta, no CEP, no perfil `authenticated`
ou no checkout que lê o mesmo endereço deve manter o que está aqui. Não repetir mudanças sem o teste de navegador abaixo.

## Comportamento esperado

- O endereço da conta é o campo `field_address` do perfil `authenticated` (fonte única). A cobrança do checkout lê o mesmo perfil
  (`BillingAddressPane`, perfil `authenticated`); o pedido guarda uma cópia sincronizada.
- Formulário: CEP, Endereço/Logradouro, Número, S/N, Complemento, Bairro, Cidade, Estado. **Sem seletor de país**: o país é
  Brasil (campo oculto `country_code = BR`).
- Número: obrigatório, só dígitos, até 10. S/N desabilita o número e o limpa.
- CEP digitado (8 dígitos) consulta `aculta_portal_cep/{cep}` (endpoint do submódulo `aculta_portal_cep`, que exige o cabeçalho AJAX) e preenche:
  - no **primeiro** preenchimento: só os campos vazios (não apaga o que a pessoa digitou);
  - ao **trocar** o CEP depois do preenchimento: rua, bairro, cidade e estado são substituídos pelo endereço do novo CEP;
  - número, S/N e complemento **nunca** são alterados pela busca.
- Salvar grava o endereço do último CEP, com o número digitado.

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

## Pendências fora deste documento

- Mercado Pago (checkout): o contrib lê o campo `address`, que os perfis não têm. Adaptador pendente; não pode ser um campo
  alias (ver acima). Ver `docs/portal/MERCADO-PAGO-PLANO.md`, DT-MP-10.

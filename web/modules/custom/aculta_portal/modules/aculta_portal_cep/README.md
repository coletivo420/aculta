# ACULTA Portal CEP

Consulta de CEP e autocompletar do endereço do Portal. Substitui o módulo contrib `cep_autocomplete` (1.0.0), que foi portado para cá e desinstalado. O Portal faz a consulta por conta própria, sem depender de módulo de terceiros.

## Serviço PHP compartilhado

- `aculta_portal_cep.lookup` (`Lookup\CepLookupService`, interface `Lookup\CepLookupInterface`): a única implementação da consulta. Usado pelo endpoint AJAX e pelos módulos PHP (como `aculta_commerce_melhorenvio`). Ordem das fontes, cache e fallback são os mesmos do endpoint.
- Não faz requisição HTTP à rota AJAX. CEP não encontrado ou inválido devolve `NULL`, sem exceção.
- Documentação da fase: `docs/portal/CEP-SHARED-LOOKUP-0.1.md`.

## O que faz

- **Endpoint AJAX** `/aculta_portal_cep/{cep}` (8 dígitos). Responde só a requisições AJAX (`X-Requested-With`); acesso direto no navegador recebe 403.
- **Fontes:** ViaCEP (com uma nova tentativa) e, se falhar ou não encontrar, BrasilAPI.
- **Cache** por CEP por 30 dias, no `cache.data`. A resposta não é cacheada por página nem por proxy (`private, no-store`).
- **Limite de consultas:** 60 por minuto por IP (`flood`), para evitar abuso das fontes externas.
- **JavaScript** (`cep-address.js`, biblioteca `aculta_portal_cep/cep-address`): preenche rua, bairro, cidade e estado. Número, S/N e complemento nunca são alterados pela busca. O comportamento completo e os testes estão em `docs/portal/ACCOUNT-ADDRESS.md`.

## Por que é código próprio

- O cliente JavaScript do contrib chamava o endpoint sem o cabeçalho `X-Requested-With` e recebia 403. Além disso, ele disputava com o comportamento do Portal pela mesma chave `once`. Por isso o `viacep.js` do contrib não é portado.
- O endpoint e o cliente ViaCEP do contrib já eram substituídos pelo controlador do Portal. Portar o que realmente rodava evita manter código morto.
- Os nomes passam a ser do Portal (`aculta_portal_cep`), e a dependência de contrib sai do `composer.json`.

## Dependências

- Módulo `address` (estrutura de endereço do Commerce).
- Não depende do `aculta_portal`: o `aculta_portal` depende deste submódulo, como já acontece com `aculta_image`.

## Atribuição

Baseado no módulo `drupal/cep_autocomplete` 1.0.0 (GPL-2.0-or-later). O aviso de licença está em `LICENSE.txt`, e a documentação original em `README.original-cep_autocomplete.md`.

## Validação

- Gate: `php scripts/validate-account-address.php` (sem navegador).
- Navegador (Firefox pelo X11, conta de teste): `scripts/test-account-address-browser.py`, 17 verificações. O link de login é de uso único.

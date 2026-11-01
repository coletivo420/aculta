# CEP Autocomplete – ViaCEP para Address (Commerce / Profile)

**CEP Autocomplete** adiciona autocompletar de endereço por CEP (ViaCEP) aos formulários que usam o campo Address padrão (CommerceGuys Addressing). Compatível com perfis Commerce “customer” e formulários de usuário, inclusive quando injetados via AJAX.

## Recursos
- Preenchimento automático: UF, Cidade, Logradouro e Bairro (quando houver campo dedicado).
- CEP em primeiro lugar + foco automático.
- `address_line2` não é preenchido; rótulo/placeholder ajustados para **Complemento**.
- Endpoint interno com cache server-side (24h).
- Base path inteligente para sites em subdiretório/prefixo de idioma.
- Anti-loop / idempotente no JS.

## Requisitos
- Drupal 10/11, PHP 8.1+
- Módulos: address, profile, commerce

## Instalação
```
composer require drupal/cep_autocomplete
drush en cep_autocomplete -y
drush cr
```

## Endpoint
`/cep-autocomplete/viacep/{cep}` (8 dígitos).

## Licença
GPL-2.0-or-later

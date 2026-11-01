# Módulos — Operação, segurança e infraestrutura Drupal

Data da revisão: 2026-10-07.

## Segurança/operação

| Módulo | Papel |
| --- | --- |
| security_review | checks de segurança |
| flood_control | administração/visibilidade de flood |
| dblog | log Drupal no banco |
| update | informação de atualização |
| key | referências a segredos externos |
| smtp | transporte de e-mail |
| captcha + turnstile | anti-automação anônima |
| username_enumeration_prevention | reduz enumeração de contas |

Turnstile é o único challenge CAPTCHA do projeto e falha fechado.

## Performance Core

`page_cache`, `dynamic_page_cache` e `big_pipe` formam a base de cache/entrega. Código custom deve preservar cache metadata.

## Internacionalização

Language e Locale estão ativos. `config_translation` está ativo na `main` (ver `config/sync/core.extension.yml`).

## Ambientes

Homelab: Debian + Apache + PHP-FPM + MariaDB.
Produção: Hostinger Cloud Startup + LiteSpeed/PHP + MariaDB.

Não introduzir Nginx como requisito ou documentação alternativa do Homelab.

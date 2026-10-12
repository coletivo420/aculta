# Módulos Drupal do ACULTA

Data da revisão: 2026-10-07.

Esta pasta é a referência canônica dos módulos Core/contrib usados pelo projeto, sua função, fonte de verdade, integrações e regras anti-regressão.

## Regras gerais

- Core/contrib permanecem donos das capacidades que já fornecem;
- `aculta_portal` integra módulos e domínios, mas não cria storage paralelo;
- o tema `aculta420` apresenta dados e não vira dono de autenticação, Commerce, LMS, Domain ou conteúdo;
- inclusão/remoção de módulo deve atualizar Composer, Configuration Sync e esta documentação;
- incompatibilidades conhecidas exigem decisão explícita;
- antes de criar código custom, pesquisar alternativa Core/contrib mantida.

## Índice por domínio

- [Autenticação e identidade](AUTHENTICATION.md)
- [Conta, AJAX e Views interativas](ACCOUNT-UI.md)
- [Domains, URLs e SEO](DOMAIN-SEO.md)
- [Commerce, apoio e pagamentos](COMMERCE.md)
- [Cursos, grupos e progresso](LMS-GROUP.md)
- [Editorial, mídia e formulários](EDITORIAL-MEDIA.md)
- [Operação, segurança e infraestrutura Drupal](OPERATIONS.md)

## Matriz rápida — contrib direto

| Módulo/pacote | Função no ACULTA | Fonte de verdade |
| --- | --- | --- |
| agreement | aceite de termos/acordos quando configurado | Agreement |
| bootstrap5 | base theme estrutural | Bootstrap5 + tema ACULTA |
| captcha | aplica challenge a formulários | CAPTCHA |
| turnstile | único challenge CAPTCHA | Cloudflare Turnstile |
| cep_autocomplete | consulta/autopreenchimento de CEP | módulo + ViaCEP |
| change_mail_page | UI para troca de e-mail | Drupal User + módulo |
| email_confirmer / email_confirmer_user | confirmação por e-mail | módulos contrib |
| login_emailusername | login por usuário ou e-mail | Drupal User |
| username_enumeration_prevention | reduz enumeração de contas | Drupal User + módulo |
| social_auth / social_auth_google | OAuth Google e vínculos externos | Social Auth + User |
| smtp | transporte de e-mail | SMTP config |
| key | referência a segredos externos | Key + ambiente |
| profile | dados de perfil | Profile |
| address | estrutura de endereço | Address |
| commerce | base e-commerce | Drupal Commerce |
| commerce_donation_flow | fluxo de apoio/doação | Commerce |
| commerce_mercado_pago | gateway Mercado Pago | Commerce Payment |
| group | memberships/grupos | Group |
| lms | cursos e progresso | Drupal LMS |
| content_lock | lock de edição | Content Lock |
| diff | comparação de revisões | Diff/Core revisions |
| freelinking | links editoriais Wiki | Freelinking |
| crop / image_widget_crop | crop de imagem | Media/Image + Crop |
| metatag | metadata/canonical/social | Metatag |
| schema_metatag | Schema.org | Schema Metatag |
| pathauto | aliases automáticos | Pathauto |
| redirect | redirects persistentes | Redirect |
| simple_sitemap | sitemap XML | Simple Sitemap |
| security_review | auditoria de segurança | Security Review |
| flood_control | administração de flood | Flood Control/Core flood |
| webform | formulários estruturados | Webform |
| vvjb | carousel de itens de Views | Views + VVJB |
| vvj_core | fundação VVJ 2.x | VVJ Core |

## Core mais relevante

`User`, `Node`, `Taxonomy`, `Views`, `Media`, `Media Library`, `Language`, `Locale`, `Workflows`, `Content Moderation`, `CKEditor 5`, `Navigation`, `Search`, `Layout Builder`, `BigPipe`, `Dynamic Page Cache` e `Page Cache` formam a base Core usada pelas integrações acima.

`config_translation` está ativo na `main`; uma PR antiga de tradução foi fechada como obsoleta em 2026-10-09 (registro em DT-O08).

## Dependências contrib indiretas importantes

`social_api`, `commerce_order`, `commerce_payment`, `commerce_price`, `commerce_store`, `commerce_checkout`, `commerce_cart`, `state_machine`, `inline_entity_form`, `domain_alias`, `domain_config`, `domain_source`, `lms_answer_plugins` e `vvj_core` podem estar habilitados como dependências.

Não remover dependência indireta olhando apenas para `composer.json`; conferir também `core.extension` e o módulo consumidor.

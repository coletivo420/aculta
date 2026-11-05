---
name: aculta-drupal-padroes
description: Revisa PHP e Drupal do ACULTA contra o padrão Drupal 12 do projeto (hooks OOP, injeção, acesso, segurança). Use ao escrever ou revisar PHP em aculta_portal ou em módulos da ACULTA.
---

# Padrões Drupal do ACULTA

Fonte normativa: `docs/portal/DRUPAL-12-STANDARDS.md`. Esta skill é um checklist de revisão. Não substitui o documento. Leia só a seção que a revisão exige.

1. **Hooks:** em código novo ou refatorado, use `#[Hook]` em classe OOP com injeção. Não crie hook procedural nem `#[FormAlter]`. Seção 4 do padrão.
2. **Serviços:** nenhum `\Drupal::` novo em `src/`. Injete pelo construtor e declare o serviço em `*.services.yml`. Seção 7.
3. **Subscribers:** implementam `EventSubscriberInterface` com a tag `event_subscriber`. Não mude prioridade por estética. Seção 8.
4. **Consultas:** `accessCheck(TRUE|FALSE)` declarado de forma consciente em toda EntityQuery. Prefira a Entity API a SQL montado à mão. Seção 9.
5. **Acesso:** rotas com `_permission`, `_entity_access` ou verificação própria. Não confie em ocultação de link. Seção 9.
6. **Saída:** Twig escapa por padrão. Em PHP, use `Html::escape()` ou `Xss::filter()`. Render API com cache metadata correto. Seção 11.
7. **Pagamento e integração:** carrinho, checkout e pagamento seguem a política de purpose MAIN. Credenciais via Drupal Key, nunca no código. Seção 12.
8. **Estilo:** `declare(strict_types=1)`, classes `final` quando não houver extensão planejada, retornos antecipados em vez de aninhamento. Seção 13.
9. **Gates:** rode `php scripts/validate-aculta-portal-drupal11.php` em mudança no Portal. Gate sem `vendor/` ou Drush: DEFERRED, com o motivo.

Ao reportar, cite a seção do padrão para cada desvio encontrado. Não corrija desvio fora do escopo da tarefa.

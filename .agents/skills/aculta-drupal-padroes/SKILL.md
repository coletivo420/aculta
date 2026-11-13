---
name: aculta-drupal-padroes
description: Revisa PHP e Drupal do ACULTA contra o padrão Drupal 12 do projeto (hooks OOP, injeção, acesso, segurança). Use ao escrever ou revisar PHP em aculta_portal ou em módulos da ACULTA.
---

# Padrões Drupal do ACULTA

Use para escrever ou revisar PHP/Drupal em módulos customizados. Para contratos de interface no tema, carregue `sdc`, `ui-patterns` ou `ui-styles` conforme a tarefa.

Fonte normativa: `docs/portal/DRUPAL-12-STANDARDS.md`. Esta skill é um checklist de revisão. Não substitui o documento. Leia só a seção que a revisão exige.

1. **Hooks:** prefira `#[Hook]` em classe OOP com injecao nas APIs que suportam este contrato. Hook procedural ou outra tecnica so com justificativa de compatibilidade verificada na versao instalada. Seção 4 do padrão.
2. **Serviços:** nenhum `\Drupal::` novo em `src/`. Injete pelo construtor e declare o serviço em `*.services.yml`. Seção 7.
3. **Subscribers:** implementam `EventSubscriberInterface` com a tag `event_subscriber`. Não mude prioridade por estética. Seção 8.
4. **Consultas:** `accessCheck(TRUE|FALSE)` declarado de forma consciente em toda EntityQuery. Prefira a Entity API a SQL montado à mão. Seção 9.
5. **Acesso:** rotas com `_permission`, `_entity_access` ou verificação própria. Não confie em ocultação de link. Seção 9.
6. **Saída:** Twig escapa por padrão. Em PHP, use `Html::escape()` ou `Xss::filter()`. Render API com cache metadata correto. Seção 11.
7. **Pagamento e integração:** carrinho, checkout e pagamento seguem a política de purpose MAIN. Credenciais via Drupal Key, nunca no código. Seção 12.
8. **Estilo:** prefira `declare(strict_types=1)`, classes `final` quando nao houver extensao prevista e retornos antecipados; excecoes justificadas quando uma API Drupal exigir extensao. Seção 13.
9. **Gates:** rode `php scripts/validate-aculta-portal-drupal12.php` em mudança no Portal. Gate sem `vendor/` ou Drush: DEFERRED, com o motivo.

## APIs de componentes e renderização

- SDC é a API de componentes visuais do tema. Leia o contrato `.component.yml` e chame o componente sem passar entidade, usuário, markup não confiável ou render array inteiro como prop.
- Plugins devem usar a API de plugin e atributos de descoberta da versão Drupal suportada. Não crie plugin procedural nem invente annotation/attribute sem conferir a definição correspondente.
- Em render arrays, preserve cacheabilidade: combine `CacheableMetadata` de dependências, access result e entidades relacionadas. `max-age: 0` só com motivo funcional comprovado.
- Revalide acesso no servidor. Visibilidade de bloco, selector ou link não substitui access check.
- Para markup de usuário, prefira render arrays e campos formatados. Não use `|raw`; no PHP, filtre conforme o contrato do campo e use `Html::escape()`/`Xss::filter()` somente quando apropriado.
- Bibliotecas declaradas no SDC e bibliotecas Drupal precisam ser anexadas condicionalmente e usar as versões existentes no projeto. Não duplique dependências globais.

## Compatibilidade efetiva

No snapshot auditado, `composer.lock` fixa Core em `11.5.0-beta1`, `composer.json` requer PHP `>=8.3`, e o PHP CLI de auditoria é 8.5.12. O projeto declara meta Drupal 12 ready, mas este checkout não contém `web/core`, `vendor` nem `web/modules/contrib`; portanto não foi possível provar execução de runtime nem compatibilidade real com Drupal 12.

Implemente para a versão efetiva e siga `docs/portal/DRUPAL-12-STANDARDS.md` para APIs compatíveis com o alvo. Antes de usar API nova ou deprecada, confira `composer.lock`, o código presente e a seção 14 do padrão. Não declare teste Drupal 12 sem executar nessa versão.

Ao reportar, cite a seção do padrão para cada desvio encontrado. Não corrija desvio fora do escopo da tarefa.

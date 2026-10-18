# ACULTA Portal (`aculta_portal`)

Módulo de integração do site institucional ACULTA. Conecta capacidades do Drupal
Core e de módulos contrib para as experiências de conta, conteúdo, Commerce,
aprendizagem e domínios. Core/contrib continuam donos das entidades e regras que
já fornecem.

## Fronteira

```text
Core + contrib → aculta_portal → contrato neutro de apresentação → ACULTA420
```

O Portal cuida de integração funcional, rotas, dados, acesso, cache e presenters.
O tema cuida de templates, componentes e estilo. O Portal não deve criar storage
paralelo nem depender do tema para funcionar.

## Navegação documental

- [Índice normativo do Portal](../../../../docs/portal/README.md) — contratos,
  políticas e padrões de implementação.
- [Padrão Drupal 11+](../../../../docs/portal/DRUPAL-11-STANDARDS.md) — APIs,
  DI, hooks, EntityQuery, render e cache.
- [Guardrails](../../../../docs/portal/GUARDRAILS.md) — regras funcionais por área.
- [Fonte de verdade](../../../../docs/portal/SOURCE-OF-TRUTH.md) — ownership de
  dados e capacidades.
- [Fronteira módulo–tema](../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md) e
  [mapa módulo–tema](../../../../docs/portal/MAPA-MODULO-TEMA.md).
- [Roadmap](../../../../docs/portal/ROADMAP.md) e
  [registro de dívidas](../../../../docs/operations/DEBT-REGISTER.md).
- [CHANGELOG](CHANGELOG.md) — entregas implementadas e evidências por versão.

## Submódulos

Cada extensão tem seu próprio README como referência local. O índice raiz mantém
a lista navegável de todos os componentes: [Submódulos do Portal](../../../../README.md#submódulos-do-portal).

## Desenvolvimento

Antes de alterar o Portal, consulte as normas acima, verifique a contraparte do
tema e confirme a versão do Core instalada em `web/core`. Use dependency injection
em classes, APIs públicas do Drupal e declarações conscientes de access/cache.

Gate arquitetural obrigatório para mudanças do Portal:

```sh
php scripts/validate-aculta-portal-drupal11.php
```

Gates específicos de fronteira, mapa, segurança ou rotas estão referenciados nos
documentos normativos correspondentes. Resultados de execução e Runtime devem
ser registrados no changelog da entrega, não copiados para este README.

## Outras entradas

- [Documentação transversal de módulos](../../../../docs/modules/README.md)
- [Integrações externas](../../../../docs/integrations/README.md)
- [ACULTA420](../../../themes/custom/aculta420/README.md)

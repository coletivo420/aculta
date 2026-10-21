# ACULTA Portal (`aculta_portal`)

Módulo de integração do site institucional ACULTA. Conecta capacidades do Drupal
Core e de módulos contrib para as experiências de conta, conteúdo, Commerce,
aprendizagem e domínios. Core/contrib continuam donos das entidades e regras que
já fornecem.

## Fronteira

```text
Core + contrib → aculta_portal → contrato neutro de apresentação → ACULTA420
```

> **Código no Aculta Portal, Design no ACULTA420.**

O Portal cuida de integração funcional, rotas, dados, acesso, cache e presenters.
O tema cuida de templates, componentes e estilo. O Portal não deve criar storage
paralelo nem depender do tema para funcionar.

## Capacidades e extensões ativas

A política reutilizável de upload de usuários pertence ao Portal e é documentada
em [`docs/portal/USER-UPLOADS.md`](../../../../docs/portal/USER-UPLOADS.md).
Consumidores reutilizam `aculta_portal.user_upload_policy`; publicação e acesso
continuam vinculados às entidades de cada domínio.

As extensões próprias incluem o ACULTA Forum com histórico de leitura por conta,
contexto, descoberta e autoria pública das atividades por meio dos cartões do
`aculta_user`; o LMS com atividades H5P vinculadas a cursos,
slugs públicos canônicos por `PublicRouteSlug::normalize()` e a política
`filename_sanitization` para nomes de upload. O `aculta_user` também fornece a
API base cacheável de identidade de autoria para consumidores do Portal. Cada
capacidade documenta seus limites no README do respectivo submódulo.

O relatório administrativo **ACULTA Portal > Status** varre os requisitos de
extensões PHP (`ext-*`) de todos os pacotes Composer de runtime, incluindo
dependências transitivas, e indica quais estão carregadas na SAPI PHP que
atende a página. Mapas explícitos atribuem os pacotes de `aculta_image` e
`aculta_pdf_viewer`; o gate bloqueia regressões de cobertura. Consulte o
[contrato do inventário de extensões PHP](../../../../docs/portal/PHP-EXTENSION-STATUS.md).

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

O submódulo `aculta_lms_h5p` conecta conteúdo interativo H5P às turmas do LMS,
com verificação de acesso do Group para reprodução, arquivos privados e
progresso. Sua condição contextual CTools identifica cursos que contêm essas
atividades; veja o [README do H5P](modules/aculta_lms_h5p/README.md) para limites
do editor e habilitação.

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

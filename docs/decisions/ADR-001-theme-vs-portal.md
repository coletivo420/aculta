# ADR-001: tema versus Portal

Status: Accepted

## Decisão

**ACULTA420** (`aculta420`) é apresentação/design system.

`aculta_portal` é integração/orquestração.

Módulos especializados continuam donos de dados e regras. O tema pode consumir
contexto de apresentação fornecido pelo Portal, mas não persiste estado
funcional.

## Breadcrumb público

A política de breadcrumb pertence ao `aculta_portal`: purpose, rotas ocultas,
raiz, hierarquia, cache metadata e título atual.

ACULTA420 adapta e renderiza; não repete decisão de domínio/rota.

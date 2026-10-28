# ADR-020 — Página atômica na ponte Content Index–Statistics

**Status:** vigente desde 2026-10-23.
**Escopo:** consultas paginadas do Content Index agregadas por métricas de
Statistics.
**Origem:** `0.1.0-dev.53`, [histórico do aculta_index](../history/changelog/aculta_index.md).

## Contexto

Uma métrica de Statistics aceita combinações específicas de tipo e bundle. Se
uma página de conteúdo incluísse entidades incompatíveis, filtrar silenciosamente
os itens ou devolver totais parciais faria o resultado parecer completo quando
não é.

## Decisão

1. A chamada inteira é rejeitada quando qualquer entidade autorizada da página
   não for compatível com o contrato da métrica.
2. Não remover itens incompatíveis e não devolver totais parciais.
3. Content Index consulta e autoriza as entidades primeiro; Statistics valida
   a métrica, a permissão de relatório e as dimensões. Os módulos não fazem
   joins diretos entre as tabelas dos dois catálogos.

## Consequências

- Consumidores filtram a página por tipo/bundle suportado antes da agregação.
- Regras de métrica, sensibilidade e autorização permanecem sob ownership de
  Statistics; projeções e acesso à entidade permanecem sob Content Index.
- O contrato detalhado está em
  [ACULTA-CONTENT-INDEX-STATISTICS-BRIDGE.md](../portal/ACULTA-CONTENT-INDEX-STATISTICS-BRIDGE.md).

## Verificação no código

`ContentIndexStatisticsBridge` passa a página autorizada integral ao serviço
de Statistics e propaga incompatibilidade como erro; não descarta entidades nem
monta resultados parciais.

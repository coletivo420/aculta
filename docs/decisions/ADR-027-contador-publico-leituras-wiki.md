# ADR-027 — Contador público de leituras da Wiki

**Status:** vigente desde 2026-10-20, por decisão do responsável.  
**Escopo:** contagem agregada de leituras de verbetes publicados da Wiki.  
**Origem:** `0.2.0-dev.3`, [histórico arquivado de aculta_statistics](../history/changelog/aculta_statistics.md).

## Contexto

O projeto decidiu antecipar a métrica própria de leitura da Wiki. A contagem
precisa limitar abuso, respeitar a autorização pública do verbete e evitar
identificadores persistentes no navegador.

## Decisão

1. Um beacon conta apenas verbetes `wiki_entry` publicados e visíveis; conteúdo
   privado, rascunho ou de outro bundle não é contado.
2. A leitura usa uma métrica agregada `wiki.entries.read`, com o ID do verbete
   como dimensão e retenção configurável por métrica (padrão de 90 dias).
3. Não se grava cookie nem se solicita aceite individual para essa métrica. O
   limite antiabuso usa chave efêmera derivada do IP para `flood`; o IP não é
   dimensão nem valor persistido na métrica.
4. A decisão de base legal e a descrição da métrica pertencem ao inventário de
   dados; a implementação não amplia o escopo de dados descrito nele.

## Evidência vigente

`WikiReadHooks` anexa o beacon somente a verbetes publicados; `WikiReadController`
revalida visibilidade, aplica limites por visitante/verbetes e envia apenas o
ID do verbete ao `StatisticsRecorder`. A configuração retém a métrica por 90
dias por padrão. O tratamento consta em
[LGPD-INVENTARIO.md](../compliance/LGPD-INVENTARIO.md).

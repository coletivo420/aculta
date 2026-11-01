# ADR-028 — Contrato neutro de marca entre Portal e tema

**Status:** vigente desde 2026-10-13.  
**Escopo:** apresentação da marca por purpose entre `aculta_portal` e ACULTA420.  
**Origem:** `0.6.0-dev.17`, [histórico arquivado do ACULTA420](../history/changelog/aculta420.md).

## Contexto

O Portal precisa fornecer identidade visual por domínio sem depender do caminho
de implementação de um tema específico. A referência direta a assets do
ACULTA420 no módulo criava acoplamento e impedia o fallback correto em temas
alternativos.

## Decisão

1. O Portal fornece marca e identidade por meio do contrato neutro
   `domain_presentation`, incluindo `brand_key` e região `brand_media`.
2. O tema ativo resolve a chave para seus próprios assets e apresentação; o
   Portal não referencia diretórios/classes do tema.
3. Código de domínio não transfere acesso ou decisões de conteúdo para a camada
   visual; o contrato transporta apenas dados neutros de apresentação.

## Evidência vigente

`ThemeHooks::brandMediaRender()` resolve `brand_key` e `brand_media`; o Portal
monta os dados de `domain_presentation` sem caminho de tema. A fronteira é
verificada pelos gates Portal–tema e está detalhada em
[DOMAIN-PRESENTATION-CONTRACT.md](../portal/DOMAIN-PRESENTATION-CONTRACT.md).

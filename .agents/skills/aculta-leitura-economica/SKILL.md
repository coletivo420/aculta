---
name: aculta-leitura-economica
description: Economiza contexto por leitura progressiva, sem impedir consulta necessaria a Core, contrib, logs ou documentos completos.
---

# Leitura progressiva

1. Consulte as instrucoes do escopo, HEAD e arquivos-alvo. Leia TODO e handoff quando forem uteis a fase, sem ritual de tres arquivos obrigatorios.
2. Prefira `rg -n`, `git diff --stat`, `scripts/lookup.py` e `scripts/doc-section.py` quando a pergunta for focal. Leia documento inteiro se for necessario para concluir corretamente.
3. `vendor/`, `web/core/`, `web/modules/contrib/`, `data/`, `references/` e `docs/history/` sao fontes sob demanda, nao proibicoes de leitura. Inspecione apenas arquivos relevantes e jamais exponha segredos.
4. Saidas verbosas: `python3 scripts/agent-output.py --lines 60 -- COMANDO`; preserve erro e status original. Em falha, consulte o log de forma direcionada.
5. Delegue subagente somente quando ampliar cobertura ou qualidade. Nao imponha limites de linhas que escondam riscos e nao faca handoff de rotina para fix concluido.
6. Economia de tokens nunca reduz os gates pertinentes, a verificacao de acesso ou a honestidade dos resultados.

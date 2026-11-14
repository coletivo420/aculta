# Graphify - ferramenta opcional

Para consulta focal, prefira `rg`, leitura de arquivos e APIs locais. Use `graphify query`, `path` ou `explain` somente se a tarefa exigir relacionamentos entre muitas partes e o grafo existente estiver valido.

- Nao exigir Graphify antes de toda leitura, nem executar `graphify update .` apos toda mudanca de codigo.
- Confira o local efetivo do grafo (`.graphify/` ou `graphify-out/`) antes de consulta. Ferramenta ausente nao bloqueia pesquisa direta.
- Nao exportar repositorios privados, segredos ou dados para servicos externos sem autorizacao; nao apagar artefatos por rotina.
- Guia de uso: `.agents/skills/graphify/SKILL.md`.

---
name: graphify
description: Usa o grafo Graphify existente para perguntas arquiteturais amplas que não se resolvem com busca direcionada; constrói ou atualiza o grafo somente quando isso trouxer valor claro.
---

# Graphify no ACULTA

Esta é uma skill upstream mantida como ferramenta auxiliar, não a fonte de verdade do projeto. Para uma pergunta simples de código, prefira `grep`, `find` e leitura focal. Para mudanças de SDC/UI Suite, use primeiro as skills especializadas e `aculta-gates-por-escopo`.

## Regras de uso

1. Se `graphify-out/graph.json` existir e a pergunta exigir relações entre muitos arquivos, faça consulta ao grafo. Cite evidências e diferencie fatos extraídos de inferências.
2. Só construa ou atualize o grafo quando o corpus for amplo, a tarefa exigir ligações difíceis de buscar ou o responsável pedir Graphify. Limite a entrada aos caminhos necessários; não inclua `vendor/`, segredos, dados, logs ou artefatos sem autorização.
3. Antes de qualquer export, clone remoto, watcher, envio a serviço ou alteração em `graphify-out`, confira o estado do Git e o escopo autorizado. Não envie conteúdo do repositório a serviços externos sem instrução explícita.
4. A referência upstream abaixo contém comandos `rm` que removem manifestos e arquivos temporários. Não os execute automaticamente. Preserve artefatos existentes; qualquer remoção precisa de autorização explícita e conferência do alvo.
5. Não crie caminhos absolutos de máquina em arquivos do repositório ou documentação. Os detalhes completos do fluxo upstream estão em `references/workflow-upstream.md`; procedimentos específicos ficam em `references/`.

## Comandos de consulta

Quando o CLI estiver disponível e houver grafo existente, prefira consulta focal (`graphify query`, `path` ou `explain`) sobre rebuild. Se o comando não estiver instalado, use busca de texto e declare a limitação. Não alegue grafo gerado sem verificar os artefatos.

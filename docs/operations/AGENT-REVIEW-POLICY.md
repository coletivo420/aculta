# Autonomia, revisao e aceite dos agentes - ACULTA

Fonte normativa complementar a `AGENTS.md`. Classes abaixo regulam **revisao de entrega**, nao a classificacao R0-R4/RX do Runtime Sync.

## Classes de revisao

| Classe | Escopo exemplificativo | Gates/evidencia | Revisao |
| --- | --- | --- | --- |
| R0 - trivial | Docs, mensagem, comentario e alteracao sem efeito no runtime | Revisao do diff, `git diff --check`, links/estrutura relevantes | Autorrevisao |
| R1 - comum | PHP nao sensivel, CSS, SDC, UI Styles, JS, teste focal | Sintaxe/lint, testes e gates do diff e dependentes | Autorrevisao; segundo revisor se contrato compartilhado ou incerteza relevante |
| R2 - sensivel | Auth, acesso, sessao, cookies, pagamento, uploads, dados privados, caches privados e arquitetura multidominio | R1 + regressao negativa de acesso e analise de seguranca pertinente | Revisao independente antes da publicacao |
| R3 - operacional critico | Banco, exclusao persistente, config estrutural, dependencias no Runtime, infra/cutover | Plano de impacto, backup/restore e rollback quando aplicavel, preflight e verificacao | Revisao independente + autorizacao especifica de cada operacao |

A classe mais alta prevalece; dividir commits nao reduz o risco real. Nome de arquivo, por si so, nao define a classe: avaliar a mudanca e seus consumidores. A revisao independente pode ser de outra pessoa ou agente com papel explicito de revisar. Se indisponivel em R2/R3, preservar o trabalho e informar bloqueio de publicacao da parte sensivel.

## Rotina de entrega

1. Confirmar HEAD, remoto, diff, worktree e arquivos de terceiros. Identificar consumidores, comportamento e riscos. O pedido inicial ja autoriza desenvolvimento ordinario.
2. Implementar e autorrevisar o diff: regressao, contratos, acesso, cacheabilidade, traducao, dados e seguranca conforme o contexto.
3. Selecionar gates de `docs/operations/GATES.md` somente para diff e dependentes; nao rodar suite global por alteracao documental. Registrar PASS/FAIL/DEFERRED/N/A sem maquiar resultados.
4. Corrigir FAIL novo. Falha preexistente comprovada deve ser identificada e separada, nao escondida nem tratada automaticamente como regressao. Em CI, verificar jobs executados, falhados, skipped e cancelled; workflow verde com tudo skipped nao prova qualidade.
5. Para R2/R3, obter revisao independente. Para R3, confirmar autorizacao por operacao, plano de recuperacao e ambiente antes de alterar estado.
6. Publicar apenas arquivos da tarefa por fast-forward, confirmar o SHA remoto. Usar Runtime Sync somente quando aplicavel e sem execucao estrutural implicita.
7. Relatar commits, classe, testes executados, resultado do CI, revisor exigido, status Runtime e pendencias objetivas.

## Limites da burocracia

- Tarefa tecnica reversivel e suficientemente definida nao exige entrevista ou outro "sim" para comecar.
- Fix pontual nao exige novo roadmap, TODO, handoff, CHANGELOG ou captura de todos os navegadores se nao muda contrato/estado. Alteracao de API, ADR, versao ou fase documenta o que mudou na fonte responsavel.
- Tamanho/quantidade de skills e limite fixo de documentos sao indicadores de custo, nao riscos de seguranca; frontmatter, links e descoberta continuam verificaveis.
- HTMX, Graphify, tokens e ferramentas de execucao sao preferencias especializadas com excecoes justificadas. Segredos, acesso, pagamento, integridade e operacoes destrutivas nao sao flexibilizados.

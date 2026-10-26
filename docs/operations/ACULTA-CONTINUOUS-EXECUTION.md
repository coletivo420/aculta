# Execução contínua ACULTA — PR #298

**Branch:** `chore/aculta-m0-integridade`  
**HEAD retomado:** `d55c4301e7ffde6022fd6c7b3651df1de927e619`  
**Estado:** PARTIAL — P0 em execução.  
**Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, URI padrão `http://default`, banco `aculta_runtime`. Até aqui, consultas somente leitura.

## Ponto de retomada

1. Confirmar origem dos erros PHPCS usando `origin/main` como baseline; contagens H5P são iguais às do baseline, nenhuma introdução M-0 comprovada até agora.
2. Reparar o Kernel H5P com dependências reais relevantes/fixture focal; preservar testes de acesso e serialização.
3. Executar o gate administrativo com Drush bootstrap e URI MAIN do servidor de testes, sem writes.
4. Atualizar M-0 audit/closeout com resultados; iniciar auditoria individual por grupos enquanto as falhas P0 são resolvidas.

## Evidência desta retomada

- Branch e remoto coincidem em `d55c4301`; worktree estava limpo.
- `git fetch origin` não revelou commits remotos adicionais na branch da PR.
- PHPCS no código atual: H5P formatter 13 erros, widget 42 e Kernel 4; baseline `origin/main` tem as mesmas contagens. Os nomes de classes são legados e requerem análise de compatibilidade antes de renomear.
- Kernel H5P reproduz 3 erros/0 assertions; primeira dependência ausente: `aculta_portal.checkout_settings`.
- Runtime confirmado via Drush como banco de teste `aculta_runtime`; não executar operações persistentes.

## Status por frente

| Frente | Estado | Próxima ação |
|---|---|---|
| P0 PHPCS | Em análise | Separar erros de baseline dos introduzidos e corrigir violações seguras no escopo tocado |
| P0 H5P | Reproduzido | Adicionar cadeia real de Studio/Document Group e reduzir configuração irrelevante do LMS se isso preservar o contrato testado |
| P0 admin Domain | Pendente | Executar `php:script` pelo Drush com URI MAIN documentada |
| M-0 inventário individual | Pendente | Auditar famílias com evidências específicas de código/config/testes/Runtime |
| M-1 a M-5 | Não iniciadas nesta retomada | Avançar após estabilizar P0 e continuar tarefas independentes seguras |

## Restrições / deferred

- Nenhuma escrita no Runtime; sem migração, importação de config ou processamento de filas.
- Operações financeiras externas, deploy, merge e remoção de adaptadores permanecem fora da autorização.
- Mudanças que alterem ownership de dados, membership persistida ou política de produto serão isoladas e submetidas ao responsável quando houver decisão necessária.

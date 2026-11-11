# Matriz de risco, evidências e testes - Runtime Sync

Referências: `docs/operations/RUNTIME-SYNC-PLANO-V2.md`, `docs/operations/RUNTIME-SYNC.md` e `docs/operations/GATES.md`. Este arquivo é normativo para **triagem**, não autoriza nenhuma escrita estrutural.

## Classificação cumulativa

| Risco | Evidência | Decisão |
| --- | --- | --- |
| R0 | Só docs/testes/arquivos comprovadamente não consumidos pelo runtime | Registrar SHA e motivo; evitar rebuild desnecessário se o mecanismo implementado puder demonstrar a ausência de impacto |
| R1 | Alteração isolada de PHP/Twig/JS/CSS/SDC ou código conhecido, sem migration/instalação | Código e cache com validação e smoke do site_key afetado |
| R2 | `composer.json`, `composer.lock`, patches, arquivos `package*.json`, bundles e dependências, bibliotecas compiladas | Bloquear sync simples; verificar lock e plano de dependências |
| R3 | `config/sync/**`, `*.install`, `*.post_update.php`, `*.deploy.php`, `*.info.yml`, schema, `core.extension`, alterações de entidade/campo, serviços exigindo setup | Bloquear; inspecionar updatedb/config/deploy hooks e elaborar plano específico |
| R4 | Exclusão de entidade/dado, uninstall, DROP, settings, segredos, symlinks públicos, DocumentRoot ou infra | Parar; autorização específica, snapshot comprovado e recuperação |
| RX | Baseline de cache desconhecido; diff remoto indisponível; renames/paths suspeitos; erro de ferramenta ou checagem | Estado indeterminado; bloquear e investigar |

**Não faça dedução apenas por glob.** Tome o maior risco do conjunto desde o último estado validado, inclusive commit já presente no checkout servidor. `git diff --name-status -z` é base prevista para o novo classificador e precisa lidar com renames, arquivos removidos, modos, espaços e newlines. Nem todo `.php` exige updatedb; nem todo código aparentemente simples é R1. Se faltar evidência, RX.

**Mudanças de UI:** tema e SDC podem precisar de rebuild/derivação de cache e smoke visual por purpose/site_key. **Multidomínio:** selecione os hosts de `docs/architecture/multidomain.md` afetados, não confunda um único `--uri` com cobertura completa.

## Casos mínimos da futura suíte de regressão

1. `HEAD == origin/dev` com último cache ainda pendente -> **não** retornar falso PASS (bug do v1).
2. `HEAD == origin/dev` e cache validado -> NOOP com evidência.
3. Fast-forward somente de código -> cache uma única vez, validação e stamp de SHA.
4. Alteração apenas documental -> sem invalidar cache quando política e baseline forem conclusivos.
5. `config/sync`, `composer.lock`, `*.install`, hooks, patches, rename -> BLOCK.
6. Worktree ou index sujo, branch/remoto errado, fast-forward impossível -> BLOCK sem descarte.
7. Host/URI/banco errados, Drush indisponível, saída malformada -> BLOCK.
8. Pendência de updatedb, config ou deploy hook -> relatório fiel e sem execução automática.
9. Erro `drush cr` após FF -> PARTIAL; nunca registrar último cache validado.
10. Locks concorrentes, agente escritor cooperativo, cancelamento, SIGTERM -> não corromper estado.
11. JSON/status ausente, inválido, velho ou gravado parcialmente -> UNKNOWN/FAIL; escrita atômica.
12. Mudança em múltiplos sites -> smoke dos sites afetados e nenhum acesso a produção.

## Evidências e limites

- Testes com Git bare e Drush falso validam apenas **orquestração simulada**.
- Gate real requer acesso ao servidor `test`, revisão de checkout e outputs Drush sem segredos, smokes por escopo e coordenação de agentes.
- Estados de gate: PASS, FAIL, SKIP, DEFERRED, N/A; sempre acompanhe de motivo.
- Não rodar `composer update` no Runtime; não criar banco e não usar `aculta_simpletest` para ensaiar restore sem decisão explícita.

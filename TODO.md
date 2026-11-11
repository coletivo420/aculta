# TODO — checkpoint do projeto

Atualizado em 2026-11-05. Estado atual em uma página. Não leia o histórico para descobrir onde o projeto está. Ao concluir uma pendência, remova a linha e registre a evidência no commit. Pendências de cada componente: `TODO.md` ao lado do `info.yml`.

## Onde estamos

- **Alvo:** Drupal 12 ready; código em Drupal 11.5 (beta). `docs/portal/DRUPAL-12-STANDARDS.md`, `docs/decisions/ADR-045-compatibilidade-drupal-12-tema-v2.md`.
- **Tema administrativo:** Gin (D-72), ativo no `config/sync`. Claro ainda habilitado.
- **Tema V2 (`aculta420_v2`):** em construção. **Tema V1 (`aculta420`):** somente referência; não altere sem autorização.
- **Economia de tokens:** oito itens concluídos. Regras: `docs/portal/AGENT-TOKEN-ECONOMY.md`.

## Pendências abertas

- **Claro:** migrar as 9 colocações de bloco para o Gin (DT-D12-01). Exige captura visual no Runtime.
- **Gin:** fixar release estável; hoje `5.0.x-dev@dev` (DT-D12-02).
- **Remoção do Claro:** só com ordem explícita (DT-D12-03). Sem `pm:uninstall` antes disso.
- **Core 12:** confirmar data do release e a remoção do Olivero (issue 3595089 é ambígua). Toolbar sem release estável: bloqueador.
- **htmx no Core 12:** inventariar usos de `Htmx` e `#ajax` no Portal. Seção htmx em `DRUPAL-12-STANDARDS.md`.
- **Dívidas:** `docs/operations/DEBT-REGISTER.md`; busque por ID com `rg -n "DT-"`.
- **Caminhos de máquina:** cerca de 30 documentos ativos; correção em fase própria.
- **Economia, fase 2:** cortar por seção os roadmaps de Studio, Wiki e Eventos; revisar a matriz da Wiki (cenários `planejado`) antes de arquivar.
- **Adiado:** `handoff/podplant420` (referências vivas em config e scripts); `AGENT-POLICIES` do Portal.

## Bloqueios

- **Gates PHP:** parte nunca rodou aqui (sem `vendor/` e sem extensão `yaml`). Ver `docs/operations/GATES.md`.
- **Runtime:** nenhuma escrita estrutural (`updatedb`, `config:import`, `pm:uninstall`, cutover F4) sem ordem explícita do responsável.
- **Remoto Git:** `origin` confirmado em `coletivo420/aculta.git` no servidor `toca`; manter conferência com `git remote -v` em worktrees antigas.

## Próximo passo

Decidir com o responsável: se o Claro sai agora ou depois da migração das colocações.

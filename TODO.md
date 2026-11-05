# TODO — checkpoint do projeto

Atualizado em 2026-11-05. Este é o estado atual do projeto em uma página. Para trabalhar, leia este arquivo e o arquivo que você vai alterar. Não leia o histórico para descobrir onde o projeto está: o histórico fica em `docs/history/` e não é fonte de estado.

Regras de uso, para humanos e agentes:
- Ao concluir uma pendência, remova a linha daqui e registre a evidência no commit ou no `docs/operations/ESTADO-DA-SESSAO.md`.
- Uma linha por item. Cada item aponta para o documento dono; os detalhes ficam lá.
- Se uma linha ficar desatualizada, corrija a linha no mesmo commit da mudança.

## Onde estamos

- **Alvo:** Drupal 12 ready, com o código ainda em Drupal 11.5 (beta). Padrão e regras: `docs/portal/DRUPAL-11-STANDARDS.md`; plano: `docs/portal/MIGRACAO-DRUPAL-12-PLANO.md`; ADR: `docs/decisions/ADR-045-compatibilidade-drupal-12-tema-v2.md`.
- **Tema administrativo:** Gin (decisão D-72). Ativo no `config/sync`. O Claro ainda está habilitado.
- **Tema V2 (`aculta420_v2`):** em construção. Estado e regras: `web/themes/custom/aculta420_v2/AGENTS.md`, `docs/architecture/ARQUITETURA-ACULTA420-V2.md`.
- **Tema V1 (`aculta420`):** somente referência. Não altere sem autorização explícita.
- **Portal (`aculta_portal`):** módulos e submódulos em `web/modules/custom/aculta_portal/`. Status por módulo: `docs/operations/status/`.
- **Economia de tokens:** oito itens concluídos e publicados. Regras: `docs/portal/AGENT-TOKEN-ECONOMY.md`.

## Pendências abertas

- **Claro:** migrar as 9 colocações de bloco do Claro para o Gin (DT-D12-01). Exige captura visual no Runtime.
- **Gin:** fixar release estável; hoje está em `5.0.x-dev@dev` (DT-D12-02).
- **Remoção do Claro:** precisa de ordem explícita do responsável (DT-D12-03). Não executar `pm:uninstall` sem essa ordem.
- **Core 12:** confirmar a data do release e a remoção do Olivero (issue 3595089 tem texto ambíguo). Toolbar não tem release estável e é bloqueador da meta do Core 12.
- **htmx no Core 12:** inventariar pontos do Portal que usam `Htmx` ou `#ajax`. Regras em `docs/portal/DRUPAL-11-STANDARDS.md`, seção htmx.
- **Dívidas abertas:** `docs/operations/DEBT-REGISTER.md`. Buscar por ID com `rg -n "DT-"`.
- **Documentos com caminhos de máquina:** cerca de 30 documentos ativos ainda têm caminhos absolutos. Correção em fase própria.
- **Economia, fase 2:** cortar por seção os roadmaps de Studio, Wiki e Eventos; revisar a matriz de testes da Wiki, que tem cenários `planejado`, antes de arquivar qualquer parte; unificar `AGENT-POLICY-INDEX.md` com `MAPA-PARA-AGENTES.md`.
- **Arquivamento adiado:** `handoff/podplant420` (referências vivas em `config/sync/views.view.revista_sessoes.yml` e em scripts); `AGENT-POLICIES` do Portal (exige conferência com o padrão).

## Decisões vigentes

- Registro das decisões do responsável: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-01 a D-72). Prevalecem sobre planos.
- Regras de domínio, purposes e pagamento: `CLAUDE.md`, seção "Regras de domínio"; políticas em `docs/portal/ADMIN-DOMAIN-POLICY.md` e `docs/portal/PAYMENT-DOMAIN-POLICY.md`.

## Bloqueios e DEFERRED

- **Gates PHP:** nunca rodaram neste ambiente (sem `vendor/` e sem Drush). Rodar no servidor de desenvolvimento. Catálogo por escopo: `docs/operations/GATES.md`.
- **Runtime:** nenhuma ação de escrita sem ordem do responsável.
- **Remoto Git:** o `origin` ainda aponta para `https://github.com/coletivo420/aculta-site`, que foi movido para `https://github.com/coletivo420/aculta.git`. Atualizar a URL do remoto.

## Próximo passo

Decidir com o responsável: (1) se o Claro sai agora ou depois da migração das colocações; (2) se a fase 2 da economia de tokens (roadmaps e matriz da Wiki) segue como está.

## Onde registrar

- Estado de sessão e handoff: `docs/operations/ESTADO-DA-SESSAO.md` (máximo de 3 entradas).
- Dívidas: `docs/operations/DEBT-REGISTER.md`.
- Pendências de cada módulo, submódulo e tema: `TODO.md` ao lado do `info.yml` (55 arquivos). Este arquivo raiz guarda só o que é transversal.
- Este arquivo: atualize a linha alterada no mesmo commit da mudança.

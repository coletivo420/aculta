# ACULTA — instruções para agentes

PROJECT-STATUS-INDEX-GUARD: 2026-10-21

Regras de todos os agentes. Em conflito, vale a instrução explícita mais recente do responsável e as regras de segurança abaixo. Texto integral: `docs/operations/AGENT-REGRAS-DETALHADAS.md` (não ler por padrão).

## Sessão

- **Leitura de partida:** `TODO.md` do projeto e do componente que você vai alterar. Para continuar uma fase, leia `docs/operations/ESTADO-DA-SESSAO.md` (até 3 entradas).
- **Ao concluir uma fase:** atualize o `TODO.md` do componente e o status YAML no mesmo commit, sem acrescentar histórico ao TODO.
- **Handoff:** ao encerrar uma fase ou o trabalho (não a cada microfase), registre em `docs/operations/ESTADO-DA-SESSAO.md` objetivo, arquivos, verificações, pendências e próximos passos. Máximo de 3 entradas; as antigas vão para `docs/history/operations/`. Sem segredos.

## Git e Runtime

- Branch `dev`. Cada entrega: um commit só com os seus arquivos (staged verificado), enviado a `origin/dev` logo após o commit. Mensagem em português, trailer `Claude-Session`, sem `Co-Authored-By`. Sem branch, PR ou merge em `main` sem ordem explícita.
- Nunca force push, reset destrutivo nem reescrita de histórico compartilhado. Preserve o trabalho alheio e paralelo.
- Após cada push, sincronize o Runtime de desenvolvimento só no código. Nunca `pm:uninstall`, `DROP` ou apagamento de dados sem ordem explícita.
- Ao concluir, informe commits com hash e validações. Não diga que publicou sem confirmar o push, nem alegue teste que não executou.

## Escopo e segurança

- Altere só o necessário. Não faça deploy nem alteração de produção sem autorização.
- Não invente contratos, hosts, CNPJ, endereço ou dados institucionais. Sem fonte, use placeholder ou pergunte.
- Sem caminhos absolutos de máquina nem URLs de ambiente fixas em código, testes ou docs. Use caminhos relativos e variáveis de ambiente.
- Segredos, tokens e senhas ficam fora do Git.

## Ambientes e URLs

- Servidor de testes (`*.aculta.toca.net.br`, Homelab) não é produção. Produção (`*.aculta.org`, Hostinger) recebe deploy só com módulos e temas em RC; até lá, verificações de produção são ensaios. Referência: `docs/architecture/environments.md`.
- Nos testes autenticados do servidor de testes, use o bypass `skip CAPTCHA` já configurado no runtime `test`. Antes da rodada, confirme `captcha.settings:enable_globally=0`; nunca desative CAPTCHA nem conceda bypass em produção.
- URL pública de conteúdo ou ação humana usa slug em português, minúsculas, hífen e sem acento (`docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`; gate `scripts/validate-public-slugs.php`).

## Arquitetura e validação

- Endereço centralizado em `aculta_portal_cep` e país exclusivo Brasil (BR), ADR-034 (`docs/decisions/ADR-034-pais-unico-brasil-endereco-central.md`). Padrão Drupal 11+ e 12: `docs/portal/DRUPAL-12-STANDARDS.md`.
- Rotas `/studio` e `/studio/*` respondem 404. Rotas administrativas e de integração existem só no domínio principal.
- **Tema de validação:** só o tema ACULTA ativo. Não alterne `system.theme`. **Olivero:** não habilite, selecione, teste nem exija em gates, suítes ou aceite.
- **HTMX no tema:** padrão para interações novas (`core/drupal.htmx`, `data-hx-*`). Drupal AJAX (`core/drupal.ajax`, `#ajax`, `use-ajax`) é legado: não introduza usos novos. Preserve links e formulários nativos como fallback.
- Aceite único de Termos e Política: `docs/portal/ACEITE-UNICO.md`.
- **Gates por escopo:** só os do diff e dos módulos dependentes (`docs/operations/GATES.md`). Gate fora do escopo: N/A com motivo. Gate que não roda no ambiente: DEFERRED com motivo.
- **Saída verbosa:** `python3 scripts/agent-output.py --lines 60 -- COMANDO`.
- **Não ler por padrão:** `data/`, `references/`, `vendor/`, `handoff/`, `docs/history/`, logs, `web/core/` e `web/modules/contrib/` (`docs/portal/AGENT-TOKEN-ECONOMY.md`).

## Referências

- Detalhes e ambientes: `docs/operations/AGENT-REGRAS-DETALHADAS.md`, `AGENT-OPERATIONS.md`, `AGENT-POLICY-INDEX.md`, `MAPA-PARA-AGENTES.md`.
- Decisões: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`. Procedimentos: `docs/agent-skills/<nome>/SKILL.md`. Gates, saída e economia: `docs/operations/GATES.md`, `AGENT-OUTPUT.md`, `docs/portal/AGENT-TOKEN-ECONOMY.md`.

# ACULTA — instruções para agentes

PROJECT-STATUS-INDEX-GUARD: 2026-10-21

Regras de todos os agentes. Em conflito, vale a instrução explícita mais recente do responsável e as regras de segurança abaixo. Texto integral: `docs/operations/AGENT-REGRAS-DETALHADAS.md`.

## Sessão

- Leitura de partida: `TODO.md` do projeto e do componente. Para continuar fase, leia `docs/operations/ESTADO-DA-SESSAO.md` (até 3 entradas).
- **Ao concluir uma fase:** atualize o `TODO.md` do componente e o status YAML no mesmo commit, sem acrescentar histórico ao TODO.
- Handoff: ao encerrar fase/trabalho, registre em `docs/operations/ESTADO-DA-SESSAO.md` objetivo, arquivos, verificações, pendências e próximos passos. Máximo de 3 entradas; antigas vão para `docs/history/operations/`. Sem segredos.

## Git e Runtime

- Branch `dev`. Cada entrega: um commit só com os seus arquivos (staged verificado), enviado a `origin/dev` logo após o commit. Mensagem em português, trailer `Claude-Session`, sem `Co-Authored-By`. Sem branch, PR ou merge em `main` sem ordem explícita.
- Nunca force push, reset destrutivo nem reescrita de histórico compartilhado. Preserve o trabalho alheio e paralelo.
- Após cada push, sincronize o Runtime de desenvolvimento só no código e limpe os caches (tabelas `cache_*` de `aculta_runtime`, ou `drush cr`) automaticamente, sem pedir autorização. `updatedb`, `config:import`, `pm:uninstall`, `DROP` ou apagamento de dados exigem ordem explícita.
- Ao concluir, informe commits com hash e validações. Não diga que publicou sem confirmar o push, nem alegue teste que não executou.

## Escopo e segurança

- Altere só o necessário. Não faça deploy nem alteração de produção sem autorização.
- Não invente contratos, hosts, CNPJ, endereço ou dados institucionais. Sem fonte, use placeholder ou pergunte.
- Sem caminhos absolutos de máquina nem URLs de ambiente fixas em código, testes ou docs. Use caminhos relativos e variáveis de ambiente.
- Segredos, tokens e senhas ficam fora do Git.

## Ambientes e URLs

- O servidor de desenvolvimento (`*.aculta.toca.net.br`) é o único ambiente. Não há produção; `*.aculta.org` é futuro sem data. Não sugerir deploy. Ver `docs/architecture/environments.md`.
- O bypass `skip CAPTCHA` é permitido nos testes autenticados do servidor de desenvolvimento (runtime `test`). Antes da rodada, confirme `captcha.settings:enable_globally=0`. Não conceda bypass em produção.
- URL pública usa slug em português, minúsculo, com hífen e sem acento (`docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`; gate `validate-public-slugs.php`).

## Interações no front-end: HTMX antes de AJAX

- Use HTMX em toda interação nova ou alterada: envios, links e diálogos. Atributos `data-hx-*` (via `Drupal\Core\Htmx\Htmx`) e a biblioteca `core/drupal.htmx`.
- Drupal AJAX (`#ajax`, `use-ajax`, `core/drupal.ajax`) é legado. Não crie interação nova com ele.
- Interação existente que usa AJAX migra para HTMX quando houver rota e fragmento claros. Se não houver, registre a exceção em `docs/htmx-envios.md` com o motivo técnico.
- Não adicione jQuery nem `core/drupal.dialog.ajax` (ver `web/themes/custom/aculta420_v2/AGENTS.md`).
- Envio de formulário continua sendo submit do Form API: `name`, `value`, `id`, `#submit` e `formnovalidate` são preservados.
- Toda rota HTMX nova tem teste que verifique os atributos `data-hx-*` e a resposta do fragmento.

## Arquitetura e validação

- Endereço centralizado em `aculta_portal_cep` e país exclusivo Brasil (BR), ADR-034 (`docs/decisions/ADR-034-pais-unico-brasil-endereco-central.md`). Padrão Drupal 11+ e 12: `docs/portal/DRUPAL-12-STANDARDS.md`.
- **Tema de validação:** só o tema ACULTA ativo. Não alterne `system.theme`. **Olivero:** não habilite, selecione, teste nem exija em gates, suítes ou aceite.
- Rotas administrativas e de integração só no domínio principal; `/studio` segue as regras do `aculta_studio`.
- Aceite único de Termos e Política: `docs/portal/ACEITE-UNICO.md`.
- **Gates por escopo:** só os do diff e dos módulos dependentes (`docs/operations/GATES.md`). Gate fora do escopo: N/A com motivo. Gate que não roda no ambiente: DEFERRED com motivo.
- Saída verbosa: `python3 scripts/agent-output.py --lines 60 -- COMANDO`.
- **Não ler por padrão:** `data/`, `references/`, `vendor/`, `handoff/`, `docs/history/`, logs, `web/core/` e `web/modules/contrib/` (`docs/portal/AGENT-TOKEN-ECONOMY.md`).

## Referências

- Detalhes: `AGENT-REGRAS-DETALHADAS.md`, `AGENT-OPERATIONS.md`, `AGENT-POLICY-INDEX.md`, `MAPA-PARA-AGENTES.md`.
- Testes de navegador: `docs/operations/NAVEGADOR-TESTES.md`.
- Capturas de evidência: `tests/browser/capturas/<frente>/<AAAA-MM-DD>/`; PNG fora do Git; `manifest.json` versionado.
- Decisões: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`. Skills: `docs/agent-skills/<nome>/SKILL.md`. Gates e economia: `docs/operations/GATES.md`, `docs/portal/AGENT-TOKEN-ECONOMY.md`.

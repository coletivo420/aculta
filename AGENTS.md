# ACULTA — agentes CLI

PROJECT-STATUS-INDEX-GUARD: 2026-10-21

Drupal 11.3+, PHP conforme `docs/architecture/environments.md`; Drupal root `web/`, módulos `web/modules/custom/`, tema `web/themes/custom/aculta420/`.

## Regras sempre ativas
- Atue apenas no escopo e na branch/PR autorizados; preserve trabalho paralelo. Sem nova PR, merge, deploy ou mudanças de produção sem autorização.
- Antes de operar no Runtime, identifique host, diretório, branch, URI e banco com Drush; consulte `docs/architecture/environments.md`. Inspecione impacto/reversão antes de operações persistentes; nunca execute ações destrutivas sem autorização.
- Não exponha segredos ou dados pessoais. Respeite acesso, cacheability, DI, APIs Drupal 11+ e código instalado.
- Faça commits focados; execute gates pertinentes e relate somente testes efetivamente realizados. Respeite versionamento/status dos componentes.

## Testes de navegador
- Use **Firefox ESR visível na sessão X11 local** (`DISPLAY=:0`), controlado pelo geckodriver via WebDriver. Referência: Firefox ESR 153.4 e geckodriver 0.37.1.
- **Não use Chromium headless nem Firefox headless** para validar páginas. O motivo e o histórico estão em `docs/operations/NAVEGADOR-TESTES.md`.
- Confirme `DISPLAY=:0` antes de abrir o navegador. Use perfil temporário em `/tmp`, criado para a sessão; nunca o perfil pessoal.
- O agente pode fechar Firefox ESR aberto sem uso. Antes, confirme que nenhum teste está em andamento e que nenhuma sessão WebDriver usa esse Firefox; se alguma estiver em uso, não feche.

## Consulte apenas quando aplicável
- **Índice de regras por escopo:** `docs/operations/AGENT-POLICY-INDEX.md`. Consulte somente o documento local pertinente. `docs/operations/AGENT-GUARDRAILS-FULL.md` é arquivo histórico para auditoria, não contexto padrão.
- Portal: `docs/portal/GUARDRAILS.md`, `docs/portal/DRUPAL-11-STANDARDS.md` e documentação local.
- Rotas/domínios: `docs/architecture/ROUTES-SITEMAP.md` e contratos vinculados.
- Status/releases: `docs/operations/RELEASE-LIFECYCLE.md`; índice gerado, nunca editado manualmente (`php scripts/validate-project-status-index.php`).
- UI: documentação do tema; demais componentes: README/contratos próprios.

## Economia de contexto
Modelo fixo **por sessão** escolhido no CLI, não neste arquivo. Busque trechos relevantes, evite dumps/logs integrais e repetições; não abra documentação não relacionada. Preserve prefixos estáveis quando possível, compacte contexto obsoleto e meça uso/cache real. Detalhes: `docs/operations/AGENT-CACHE.md`.

## Ferramentas e escopo
- Para saídas extensas, use `python3 scripts/agent-output.py --lines 60 -- COMANDO` (consulte `docs/operations/AGENT-OUTPUT.md`); preserve e verifique o código de saída. Logs locais podem conter dados sensíveis.
- Instruções locais em `web/modules/custom/aculta_portal/AGENTS.md` e submódulos prevalecem no próprio escopo; não carregue regras de componentes alheios.
- Exclua `vendor/`, `web/core/`, `web/modules/contrib/`, `node_modules/`, caches e artefatos gerados das buscas **por padrão**, mas inclua o código instalado de Core/contrib quando necessário para verificar APIs e compatibilidade.

## DeepSeek: bloqueio de pico
- Se o provedor da sessão for DeepSeek, **não execute chamadas diretas à API**: use o proxy local de `scripts/deepseek-offpeak.py` conforme `docs/operations/DEEPSEEK-OFFPEAK.md`. No pico, o trabalho segue com o Claude padrão (lançador `claude-deepseek`, que escolhe o provedor pelo horário); não chame a API DeepSeek diretamente e não contorne o HTTP 429. A instrução não substitui o bloqueio de rede nem a configuração do endpoint no CLI.

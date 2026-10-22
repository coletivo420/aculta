# ACULTA — agentes CLI

Drupal 11.3+, PHP conforme `docs/architecture/environments.md`; Drupal root `web/`, módulos `web/modules/custom/`, tema `web/themes/custom/aculta420/`.

## Regras sempre ativas
- Atue apenas no escopo e na branch/PR autorizados; preserve trabalho paralelo. Sem nova PR, merge, deploy ou mudanças de produção sem autorização.
- Antes de operar no Runtime, identifique host, diretório, branch, URI e banco com Drush; consulte `docs/architecture/environments.md`. Inspecione impacto/reversão antes de operações persistentes; nunca execute ações destrutivas sem autorização.
- Não exponha segredos ou dados pessoais. Respeite acesso, cacheability, DI, APIs Drupal 11+ e código instalado.
- Faça commits focados; execute gates pertinentes e relate somente testes efetivamente realizados. Respeite versionamento/status dos componentes.

## Consulte apenas quando aplicável
- **Regras específicas preservadas:** `docs/operations/AGENT-GUARDRAILS-FULL.md` — localize e leia **somente as seções do escopo**; não carregue o arquivo inteiro.
- Portal: `docs/portal/GUARDRAILS.md`, `docs/portal/DRUPAL-11-STANDARDS.md` e documentação local.
- Rotas/domínios: `docs/architecture/ROUTES-SITEMAP.md` e contratos vinculados.
- Status/releases: `docs/operations/RELEASE-LIFECYCLE.md`; índice gerado, nunca editado manualmente (`php scripts/validate-project-status-index.php`).
- UI: documentação do tema; demais componentes: README/contratos próprios.

## Economia de contexto
Modelo fixo **por sessão** escolhido no CLI, não neste arquivo. Busque trechos relevantes, evite dumps/logs integrais e repetições; não abra documentação não relacionada. Preserve prefixos estáveis quando possível, compacte contexto obsoleto e meça uso/cache real. Detalhes: `docs/operations/AGENT-CACHE.md`.

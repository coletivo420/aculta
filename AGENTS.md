# ACULTA — instruções essenciais para agentes

Drupal 11.3+, PHP conforme ambiente documentado, Composer, Drush, Git. Document root: `web/`. Código: `web/modules/custom/`; tema: `web/themes/custom/aculta420/`.

## Escopo e segurança (sempre)
- Trabalhe **somente na branch/PR existente** e no escopo autorizado. Preserve mudanças de outras frentes; não crie PR, não faça merge nem altere produção sem autorização.
- Antes de operações de Runtime, confira `hostname`, `pwd`, `git status`, Drush, URI e banco; consulte `docs/architecture/environments.md`. Não presuma que localhost é produção/teste. Não execute deploy, updb, config import/export ou alterações persistentes sem inspecionar efeitos e reversibilidade.
- Nunca exponha segredos, dados pessoais, credenciais ou dumps. Não contorne controles de acesso. Confirme antes de ações destrutivas.
- Use APIs Drupal 11+ e código instalado como referência; DI, access checks, render/cacheability, segurança e testes são requisitos. Pesquise documentação upstream quando a decisão depender de comportamento/versionamento.
- Não declare testes, deploy ou validações que não executou. Rode gates relevantes e informe pendências. Documente decisões no componente dono, não em prompts repetidos.
- Commits pequenos, focados e sem alterações alheias. Respeite política de versionamento e status de componentes.

## Documentação sob demanda (não carregue tudo)
- **Contrato integral preservado:** `docs/operations/AGENT-GUARDRAILS-FULL.md`. Leia **apenas as seções aplicáveis** ao trabalho; suas regras específicas continuam obrigatórias.
- Portal: `docs/portal/GUARDRAILS.md`, `docs/portal/DRUPAL-11-STANDARDS.md` e README do módulo afetado.
- Rotas, aliases, domínio ou purpose: `docs/architecture/ROUTES-SITEMAP.md` e contratos vinculados; atualize mapa junto da mudança.
- Status/versionamento: `docs/operations/RELEASE-LIFECYCLE.md` e status YAML do componente. Não edite manualmente a tabela PROJECT-STATUS-INDEX; gere via `php scripts/validate-project-status-index.php --write` quando necessário e valide com `php scripts/validate-project-status-index.php`.
- Tema/UI: documentação do `aculta420`; integrações: README e contratos do componente.
- Economia de tokens: `docs/operations/AGENT-CACHE.md`.

## Contexto e modelo
- Uma tarefa por sessão; modelo/provedor **fixo por sessão**, escolhido no CLI/perfil local, sem alternância automática.
- Busque caminhos e trechos relevantes, não despeje arquivos, logs ou árvores inteiras. Não releia material inalterado sem necessidade.
- Evite repetir prompts e instruções. Resuma decisões persistentes no arquivo do componente; compacte o histórico quando o contexto perder utilidade.
- Cache de prefixo é automático quando suportado, não garantido. Meça cache hits, tokens e testes por tarefa; nunca coloque chaves de API no repositório.

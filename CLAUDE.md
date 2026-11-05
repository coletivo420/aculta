# Claude Code — ACULTA

Plataforma Drupal 11 da ACULTA. Código em `web/modules/custom/aculta_portal/` (módulos e submódulos) e no tema `web/themes/custom/aculta420/`. Documentação em `docs/`.

Regras gerais de Git, commits, push, worktree, Runtime e coautoria estão em `AGENTS.md`. Ambientes, Drush, servidor web e banco de teste estão em `docs/operations/AGENT-OPERATIONS.md`. Este arquivo traz só o que é específico do Claude e do domínio.

## Antes de começar

1. Leia `AGENTS.md`.
2. Localize as regras do seu escopo em `docs/operations/AGENT-POLICY-INDEX.md`. Não leia `docs/operations/AGENT-GUARDRAILS-FULL.md`: é arquivo histórico.
3. Para entender o projeto, leia `docs/operations/MAPA-PARA-AGENTES.md`.
4. Confirme o estado no código e no git antes de afirmar status. Planos e roadmaps podem estar desatualizados.
5. Decisões do responsável: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` e as ADRs em `docs/decisions/`. Quando um plano conflitar com uma decisão registrada, a decisão vale; registre o conflito.
6. Alteração visual do ACULTA420: siga `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md` e `web/themes/custom/aculta420/AGENTS.md`. Preserve Institution Bar e Domain Header, e permita reflow da barra desde 320px.

## Regras de domínio

- **Endereços e CEP:** o país é sempre `BR`, sem seletor de país em nenhum lugar. Formulários de endereço usam o contrato de `aculta_portal_cep` (`aculta_portal_cep/cep-address`). Valide `country_code = BR` no servidor. Número, S/N e complemento são sempre preservados na busca de CEP. ADR-034 e `docs/portal/ACCOUNT-ADDRESS.md`.
- **Credenciais:** em produção, as chaves ficam no banco, criptografadas, e são provisionadas pelo `aculta_deployer` após o deploy. Arquivos em `secrets/` são só do servidor de desenvolvimento.
- **Mercado Pago:** o prefixo do token (`APP_USR-`) não identifica o ambiente. Decida pelo modo configurado e pelo `live_mode` do pagamento.
- **Pesquisa na web:** liberada, sem pedir permissão. Não publique nem envie conteúdo para fora sem confirmação.
- **Rotas administrativas e integrações:** toda rota `/painel-administrativo/**` e `/integracoes/**` responde só no domínio principal (MAIN); nos subdomínios retorna 404, nunca 302. Não crie painel, toolbar, link contextual ou ação administrativa em subdomínio. Gate: `php scripts/validate-admin-routes-main-domain.php`. Política: `docs/portal/ADMIN-DOMAIN-POLICY.md`.
- **Independência do tema:** módulos do Portal devem funcionar sem depender do tema ACULTA420. Valide o contrato e a renderização do escopo solicitado sem trocar o tema padrão do Runtime.
- **Navegador de testes:** Firefox ESR visível em `DISPLAY=:0`, via geckodriver. Não use Chromium nem headless. Perfil temporário e artefatos vão para `$SUITE_OUT`, fora do repositório. Scripts de teste visual ficam em `tests/browser/<frente>/` com `README.md`.
- **Tema de validação:** validar somente no tema ACULTA ativo. Não selecione temas adicionais em testes, não altere `system.theme` para validá-los e não os descreva como gates pendentes. Preserve resultados históricos datados.

- **Caminhos e rotas:** não grave caminhos absolutos de máquina nem URLs de ambiente fixas em código, testes, scripts ou docs versionados (regra completa em `AGENTS.md`, seção Escopo e segurança).

## Dúvida ou ordem fora deste texto

Pare e pergunte ao responsável. Faça antes o que for independente.

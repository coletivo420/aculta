# Claude Code — ACULTA

Plataforma Drupal 11 da ACULTA. Código em `web/modules/custom/aculta_portal/` e tema V2 em `web/themes/custom/aculta420_v2/`. Regras gerais em `AGENTS.md`; aqui só o que é específico do Claude e do domínio.

## Antes de começar

1. Leia `AGENTS.md` e `TODO.md`. Antes de alterar um componente, leia o `TODO.md` dele, ao lado do `info.yml`.
2. Confirme o estado no código e no git antes de afirmar status. Planos e roadmaps podem estar desatualizados.
3. Decisões do responsável prevalecem sobre planos: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`. Regras por escopo (`docs/operations/AGENT-POLICY-INDEX.md`) e mapa (`docs/operations/MAPA-PARA-AGENTES.md`) são consulta sob demanda.
4. Alteração visual do ACULTA420: siga `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md`. Preserve Institution Bar e Domain Header.

## Regras de domínio

- **Endereço e CEP:** país sempre `BR`, sem seletor de país. Formulários usam o contrato `aculta_portal_cep/cep-address`. Valide `country_code = BR` no servidor. Número, S/N e complemento são preservados na busca de CEP. ADR-034 e `docs/portal/ACCOUNT-ADDRESS.md`.
- **Credenciais:** em produção, ficam no banco, criptografadas, e são provisionadas pelo `aculta_deployer`. Arquivos em `secrets/` são só do servidor de desenvolvimento.
- **Mercado Pago:** o prefixo do token (`APP_USR-`) não identifica o ambiente. Decida pelo modo configurado e pelo `live_mode` do pagamento.
- **Rotas administrativas e de integração:** `/painel-administrativo/**` e `/integracoes/**` respondem só no domínio principal (MAIN); nos subdomínios, 404, nunca 302. Gate: `php scripts/validate-admin-routes-main-domain.php`. Política: `docs/portal/ADMIN-DOMAIN-POLICY.md`.
- **Independência do tema:** módulos do Portal funcionam sem depender do ACULTA420. Valide o contrato sem trocar o tema padrão do Runtime.
- **Navegador de testes:** Firefox ESR visível em `DISPLAY=:0`, via geckodriver. Não use Chromium nem headless. Perfil temporário e artefatos vão para `$SUITE_OUT`, fora do repositório. Scripts ficam em `tests/browser/<frente>/` com `README.md`.
- **Pesquisa na web:** liberada, sem pedir permissão. Não publique nem envie conteúdo para fora sem confirmação.

## Dúvida ou ordem fora deste texto

Pare e pergunte ao responsável. Faça antes o que for independente.

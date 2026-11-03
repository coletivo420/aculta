# Revisão das fases do controlador de superfícies

Data: 2026-11-03 (rodada 3). Base: `dev` em `bc99862a9`. **RUNTIME STATUS: validado para a matriz de caminhos; UI autenticada DEFERRED**.

## Por fase

| Fase | Estado | Commits (resumo) | Pendência para fechar |
| --- | --- | --- | --- |
| 1 Inventário | Concluída | roteiro e CSV de rotas | — |
| 2 Modelo (ADR-043) | Aceita | ADR, anexo de decisões fixas | — |
| 3.0 Congelar comportamento | Parcialmente validada | matriz HTTP no Runtime e Kernel focal executados | autenticar e revisar a UI administrativa e as Suites |
| 3.1 Mapa único | Código | `DomainSurfaceMap` e paridade escrita | executar testes |
| 3.2 Flag autoritativa | Código | resolvedor preserva purpose; comentário corrigido | teste de rota da flag |
| 3.3 Constantes de purpose | Código | `DomainSurfaceMap::MAIN` e `ACCOUNT` | executar testes |
| 3.4 Atribuição pela configuração | Parcial | config, schema, leitor e `SurfaceRequestSubscriber`, todos atrás de `leitura_ativa` (desligado) | teste da 3.0 no Runtime; atribuição de purpose por configuração não implementada |
| 3.5 Modo diretório | Parcial | validação; `caminhoDiretorioAtivo` só com interruptor ligado; tabela de caminhos | resolução de `/slug` no MAIN (DEFERRED: exige teste de rota) |
| 3.6 Formulário | Código | `/painel-administrativo/aculta/caminhos`, só MAIN, com tabela somente leitura e link no menu ACULTA | executar testes e revisar UI no Firefox |
| 3.7 Exportação | Validada no Runtime | `config:status` sem diferenças antes da ativação; `config:import --diff` sem alterações | ativação permanece somente no banco até RC |

### O que mudou nesta rodada (2026-11-03)

- **3.6:** formulário movido para `/painel-administrativo/aculta/caminhos` (sem alias; o antigo some). Tabela somente leitura com caminho público (host vindo do Domain) e caminho em vigor. Link no menu ACULTA. Teste estático do caminho e da permissão.
- **3.5:** `SurfaceConfigReader::caminhoDiretorioConfigurado()` e `caminhoDiretorioAtivo()`. Com `leitura_ativa` desligada (padrão), `caminhoDiretorioAtivo()` devolve NULL.
- **3.4:** conferido. `SurfaceRequestSubscriber` retorna antes de qualquer leitura de superfície quando `leitura_ativa` está desligada. Com o padrão, nenhum comportamento muda. Correção: o registro de dívidas dizia que o subscriber não existia; ele existe desde `20e7d6d`.
- **3.7:** conferência de importação NÃO criada. Sem `vendor/` não há parser YAML nem config tipada para comparar com o schema. Caminho para o Runtime: `drush config:status` (somente leitura).
- **Runtime 2026-11-03:** `composer install` com PHP 8.4, `updatedb:status`, `config:status`, `config:import --diff` e `cr` passaram. A matriz foi executada antes e depois de `leitura_ativa`; não houve `Location`. Raízes deram 200; caminhos internos, legados e `/studio*` deram 404. `/we-sativa` e `/green-stash` deram 403 para anônimo; o painel MAIN deu 403 por permissão e o mesmo caminho no Wiki deu 404; MCP deu 401 no MAIN e 404 fora dele. `leitura_ativa` está ligada no Runtime e continua FALSE no YAML versionado. `aculta_superficies` foi habilitado no Runtime para validar a interface; `core.extension` e a configuração de superfícies ficaram como diferenças esperadas.
- **Integração (revisão):** nenhum bloqueio novo encontrado nos pontos de contato (`aculta_portal_sitemap`, `aculta_deployer`, schema, serviços, injeção de `aculta_portal.domain_purpose`). Lint, gate de rotas MAIN, endereços e validadores de navegador PASS; boundaries do deployer PASS; suíte standalone do deployer PASS.

### Ativação (quando o Runtime validar a 3.0)

1. Rodar a matriz de rotas da 3.0 no Runtime e registrar o resultado.
2. Ligar `leitura_ativa` apenas em homologação, com `drush config:set aculta_portal.superficies leitura_ativa true` no Runtime. O formulário mostra o interruptor desabilitado de propósito.
3. Conferir o 404 de superfície desativada e que o MAIN não muda.
4. Só então implementar a resolução de `/slug` (3.5) e a atribuição de purpose por configuração (3.4), cada uma em PR própria com teste de rota.

## Integrações

- **Sitemap** (`aculta_portal_sitemap`): exclui superfícies desativadas. Lista fixa continua como teto. Módulo em Dev `0.1.3-dev.1`, saiu do RC `0.1.2-rc.1` por decisão do responsável.
- **Deployer** (`aculta_deployer`): em Dev `0.1.29-dev.1`. Comando `superficies-sugestao` lê JSON e imprime entradas de correção, sem gravar. O formulário avisa a mudança sem citar a ferramenta (fronteira).

## Testes

- Unit focal: 38 testes e 311 asserções PASS; a suíte combinada Portal/Studio/Commerce/Wiki/Sitemap passou 257 testes e 1.642 asserções após atualizar as expectativas dos caminhos WE SATIVA e Green Stash. Kernel `SurfaceRoutesKernelTest`: 2 testes, 22 asserções PASS, 39 depreciações de Core/contrib.
- Executado nesta rodada: lógica do leitor com stub de configuração (9/9 PASS, script em scratchpad, fora do repositório); lint PHP e YAML dos arquivos alterados; `validate-admin-routes-main-domain` PASS; `validate-environment-addresses` PASS; `validate-browser-validators` PASS (21 checks); suíte standalone do deployer PASS; `boundaries` PASS.
- `validate-public-slugs` PASS (423 rotas); `validate-project-status-index` e `validate-aculta-portal-drupal11` FAIL por divergência simultânea do status/manifest de `aculta_lms` (`.159`/`.160`) e README em alteração de outro agente. Gates MAIN, endereços, browser, tema e boundaries PASS.

## Commits desta rodada

- `d05f626` feat(aculta_portal): caminho de diretório das superfícies atrás do interruptor (3.5).
- `89b6cdb` feat(aculta_superficies): caminhos em `/painel-administrativo/aculta/caminhos` com tabela somente leitura.
- Este documento e `DEBT-REGISTER.md` seguem no commit de documentação desta rodada.

## Entrega

Os commits foram para `dev` diretamente, como autorizado. Nenhum PR foi aberto, porque abrir PR exige branch e a ordem explícita do responsável.

# Revisão das fases do controlador de superfícies

Data: 2026-11-03. Base: `dev` (commit de referência em `git log`). **RUNTIME STATUS: DEFERRED** para tudo que depende de teste.

## Por fase

| Fase | Estado | Commits (resumo) | Pendência para fechar |
| --- | --- | --- | --- |
| 1 Inventário | Concluída | roteiro e CSV de rotas | — |
| 2 Modelo (ADR-043) | Aceita | ADR, anexo de decisões fixas | — |
| 3.0 Congelar comportamento | DEFERRED | roteiro, teste estático e Kernel escritos | executar no Runtime |
| 3.1 Mapa único | Código | `DomainSurfaceMap` e paridade escrita | executar testes |
| 3.2 Flag autoritativa | Código | resolvedor preserva purpose; comentário corrigido | teste de rota da flag |
| 3.3 Constantes de purpose | Código | `DomainSurfaceMap::MAIN` e `ACCOUNT` | executar testes |
| 3.4 Atribuição pela configuração | Parcial | config, schema, leitor, interruptor e subscriber desligados | ligar após teste de rota |
| 3.5 Modo diretório | Parcial | regra de superfícies na validação; sugestão de correção de deploy | URLs de diretório não implementadas |
| 3.6 Formulário | Código | `/painel-administrativo/superficies`, só MAIN | executar testes e revisar UI |
| 3.7 Exportação | Parcial | YAML em `config/sync` | conferência de importação no Runtime |

## Integrações

- **Sitemap** (`aculta_portal_sitemap`): exclui superfícies desativadas. Lista fixa continua como teto. Módulo em Dev `0.1.3-dev.1`, saiu do RC `0.1.2-rc.1` por decisão do responsável.
- **Deployer** (`aculta_deployer`): em Dev `0.1.29-dev.1`. Comando `superficies-sugestao` lê JSON e imprime entradas de correção, sem gravar. O formulário avisa a mudança sem citar a ferramenta (fronteira).

## Testes

- Escritos e não executados: `DomainSurfaceMapTest`, `SurfaceConfigReaderTest`, `SurfaceRoutesStaticTest` (estático, roda sem Drupal), `SurfaceRoutesKernelTest` (Kernel, DEFERRED).
- Executados nesta sessão: suíte standalone do deployer (PASS), `boundaries` (PASS), gates de endereços, rotas administrativas e validadores de navegador (PASS).

## Entrega

Os commits foram para `dev` diretamente, como autorizado. Nenhum PR foi aberto, porque abrir PR exige branch e a ordem explícita do responsável.

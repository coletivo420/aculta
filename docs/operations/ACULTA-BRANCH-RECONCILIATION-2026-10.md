# Conciliação de branches da PR #298 — 2026-10-26

Branch protegida de integração: `chore/aculta-m0-integridade`.
HEAD da PR antes deste relatório: `9dfbd31bed190ad6c00416201655688c2cfe16a4`.
`main` permanece em `805fb7c510baeccac22f12986743245219ce3e49`; nenhum merge foi feito.

## Critério

Cada branch foi comparada ao conteúdo da PR por `git cherry`, histórico, arquivos alterados e revisão semântica. A contagem ahead/behind foi usada apenas como inventário. Os worktrees locais foram verificados; nenhum worktree ou branch local foi apagado. Somente refs remotas concluídas, sem agente ativo e sem conteúdo exclusivo aproveitável foram removidas.

## Resultado

| Branch fonte | HEAD verificado | Inventário | Destino dos commits exclusivos | Resultado remoto |
|---|---|---:|---|---|
| `feat/aculta-frentes-independentes` | `9f7a09f8e9b9b843c9a532e177b8cc7f58e4679f` | 84 commits à frente; 72 patches equivalentes e 12 commits revisados semanticamente | OAuth HTTP, Patcher, Sitemap, Editor, Deployer, rotas/popup e cotação foram comparados aos arquivos atuais da PR. A cotação antiga `ff92ca0aa` foi substituída pela implementação adaptada ao motor próprio (`698499396`, `9603a5649`). README antigo `d4c11717f` foi descartado por declarar versão e dependência obsoletas. O teste de Sitemap foi corrigido no commit `155edc2c5` removendo `commerce_shipping` indisponível. | Excluída após revisão; worktree local limpo preservado. |
| `feat/aculta-commerce-shipping-port` | `37d56355d8b92da23ce47114712c55eab9968f07` | 32 commits; 29 patches equivalentes e 3 patches adaptados | Kernel: `f84d099412` representado por `118ae7af2`; transições Green Stash: `e40edb762c` representado por `b44e83b25`; fallback de workflow: `37d56355d8` representado por `3c4f8de9a` e documentação `71b96d264`. Motor, entidades, plugins, Composer, Config Sync e contratos já estavam na PR. | Excluída após revisão; worktree local limpo preservado. |
| `feat/aculta-me-conciliacao` | `d410ef313a82b99ad30c15e5870ecdf1534799da` | 31 commits; 27 patches equivalentes e 4 patches adaptados | Dois commits de Shipping foram cobertos pelos equivalentes acima. Provider no motor próprio (`c7db2eaaa5`) foi substituído pelo provider atual integrado (`698499396`, `9603a5649`), que usa as interfaces ACULTA e preserva o guard de método persistido. Binder/alias (`d410ef313a`) está coberto pela definição atual de serviço e alias (`4ae4229e4`, com cabeamento revisado em `f90aa4604`). | Excluída após equivalência; worktree local limpo preservado. |
| `agent/aculta-secrets-manager` | `b024f5bc4d3e863de2aae764133b9b1fd1b90c41` | 15 commits; 13 patches equivalentes e 2 implementações com commits substitutos | Extração do submódulo/UI coberta por `a91600359`; abas Produção/Testes cobertas por `ba3116cb6`. Os 13 patches restantes foram iguais por patch-id. Testes e documentação foram reexecutados/atualizados na PR; Secret Manager permanece Dev. | Excluída e confirmada ausente do remoto; worktree/local branch preservados. |
| `feat/aculta-index-fechamento` | `98f3937e54e11379e0e4a23a9f24afb76563fea8` | 27 commits; 14 patches já equivalentes e 13 integrados nesta rodada | Integridade/Entity Access, provider File opt-in, bridge Statistics, relação de vídeo LMS, testes e documentação foram integrados. Commits novos principais: `8082d7d1e`, `bdf326a7b`, `45a787012`, `a74490c54`, `b5473905d`, `c9357759b`, `3a3f27848`, `d797a2454`, `fc77c4903`, `62f646c1c`, `f74e88620`, `7e54cf137`. Testes focais na PR: Unit 17/155; Kernels 3/55, 5/259 e 21/295 PASS. | Excluída após integração; worktree/local branch preservados. |
| `feat/aculta-melhorenvio-integracao` | `da55ae5c7209f4483d27839b4a02b9130bac6c90` | 5 commits; 4 patch-únicos na comparação | Preservada: branch de trabalho ativa. O agente informou worktree local com alteração ainda não commitada em `MelhorEnvioWiringKernelTest.php`; não integrar nem apagar até o agente publicar ou declarar o trabalho pronto. A implementação funcional já comparada à PR está alinhada ao motor ACULTA. | Mantida no remoto. |
| `main` | `805fb7c510baeccac22f12986743245219ce3e49` | protegida | Nenhuma alteração. | Mantida. |
| `chore/aculta-m0-integridade` | `9dfbd31bed190ad6c00416201655688c2cfe16a4` | branch da PR #298 | Continua recebendo os commits verificados desta conciliação. | Mantida. |

## Exclusões confirmadas

Em 2026-10-26, foram removidas somente estas refs remotas: `feat/aculta-frentes-independentes`, `feat/aculta-commerce-shipping-port`, `feat/aculta-me-conciliacao`, `feat/aculta-index-fechamento` e `agent/aculta-secrets-manager`. `git ls-remote` confirmou as quatro primeiras ausentes após a exclusão; a branch de Secrets já havia sido removida e também está ausente. As branches locais e seus worktrees foram preservados.

A ref `feat/aculta-melhorenvio-integracao` não foi removida porque continua ativa e tem alteração local não publicada. Nenhuma exclusão atingiu `main`, a branch da PR, dados, Runtime, ou bancos.

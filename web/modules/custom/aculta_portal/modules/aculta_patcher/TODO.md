# TODO — aculta_patcher

Estado: RC · versão 0.1.0-rc.4 (`aculta_patcher.info.yml`) · teste de DI verifica dependências; PHPStan completo PASS · status: `docs/operations/status/aculta_patcher.yml`.

## Próximo passo

- Refatorar os usos restantes de `\\Drupal::` em classes do módulo; list builder e formulário de estado agora recebem importer e entity type manager por DI.
- Refatorar os usos restantes de `\\Drupal::` em classes do módulo; list builder e formulário de estado agora recebem importer e entity type manager por DI.
Deploy do candidato no servidor de desenvolvimento e validação no ambiente *.aculta.org (Alpha), com posterior migração para Hostinger (Beta) conforme RELEASE-LIFECYCLE.md ([README](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md))

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_patcher.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

# TODO — aculta_domain_config

Estado: Dev · versão 0.1.0-dev.1 (`aculta_domain_config.info.yml`) · status: `docs/operations/status/aculta_domain_config.yml`.

## Próximo passo

- Revisar e refatorar os usos restantes de `\\Drupal::` em classes deste módulo; a troca de domínio do formulário de blocos já usa `request_stack`.
- Homologar a desativação de bloco por domínio e testar importação de configuração em staging.
- Avaliar `domain_menu_extras` para overrides de blocos de menu, mantendo a cobertura restrita a `block`.
- Preservar a regra: criar o bloco no contexto global e depois configurar o override, sem excluir a entidade global.

## Limites

- Não apaga o bloco global ao remover o override.
- Não cobre overrides de `core.entity_view_display` nem de `layout_builder__layout`.

## Leitura

Este arquivo, o README do módulo e `docs/portal/domain-config/`.

## Ao concluir uma fase

Atualize este TODO e `docs/operations/status/aculta_domain_config.yml` no mesmo commit; sem histórico.

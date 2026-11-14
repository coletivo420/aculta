# ACULTA Domain Config (blocos por domínio)

Ponte mínima sobre `domain_config_ui`, `domain_config_entity_ui` e
`domain_config_switcher` para editar overrides de blocos de um domínio a partir
do MAIN, sem alterar a negociação do request.

- Seletor "Configurar domínio" no Layout de Blocos e no formulário de bloco.
- Contexto de edição aplicado antes do `ParamConverter` (`config_domain`).
- Leitura do override com `loadOverrideFree()`.
- Cobertura experimental restrita a `block`; o domínio padrão MAIN é o global.

Documentação operacional: `docs/portal/domain-config/` (README, INVENTARIO,
ARQUITETURA, LAYOUT-BLOCOS, TESTES e OPERACAO).

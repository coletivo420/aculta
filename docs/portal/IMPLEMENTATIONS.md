# Implementações do ACULTA Portal

O Portal aplica capacidades próprias e adapta integrações Drupal/Core/contrib às políticas institucionais. O termo **Implementações** é a categoria geral; “Helper” é uma classificação interna para serviços reutilizáveis, e “Extras aplicados” reúne serviços runtime que conectam ou adaptam mecanismos existentes.

## Inventário no Status

**Administração → Configuração → ACULTA Portal → Status** apresenta três inventários complementares:

- **ACULTA Portal e submódulos ativos**: estado efetivo obtido do registro de extensões habilitadas do Drupal e metadados `.info.yml`.
- **Helpers ativos**: serviços explicitamente marcados com `aculta_portal.helper`.
- **Extras aplicados**: serviços runtime marcados com `aculta_portal.implementation`, como access checks, route processors, subscribers, renderizadores e overrides.

O container fornece os IDs ativos por `service_id_collector`; os registros de Status mantêm rótulo, resumo e referência documental. Isso permite mostrar o serviço realmente registrado sem inferir atividade por nome de arquivo. O inventário não pretende enumerar cada hook ou plugin; módulos ativos continuam listados no inventário de extensões.

## Inclusão obrigatória

Ao adicionar um serviço helper ou uma integração aplicada pelo Portal:

1. Classifique-o como `aculta_portal.helper` somente se for um serviço reutilizável com responsabilidade coerente; classifique integrações runtime aplicadas como `aculta_portal.implementation`. Um serviço pode receber ambas apenas se cumprir os dois contratos de forma independente.
2. Inclua a tag no serviço de `aculta_portal.services.yml`.
3. Registre ID, rótulo, efeito/resumo e documento em `PortalHelperRegistry` e/ou `PortalImplementationRegistry`.
4. Documente fonte de verdade, limite Core/contrib/Portal/tema, acesso/cache/Domain e configuração/testes pertinentes no documento específico.
5. Atualize README e changelog do Portal e mantenha este índice e os guardrails coerentes.
6. Execute `php scripts/validate-aculta-portal-drupal11.php` e as verificações específicas da mudança.

O gate deve falhar quando tags e metadados divergirem, quando a documentação registrada não existir ou quando o painel deixar de renderizar os dois inventários.

## Fonte de verdade

O YAML de serviços define a integração registrada; o container ativo define quais serviços estão disponíveis; os registros PHP descrevem esses serviços para administração; a documentação específica define seu contrato. A tela é um relatório, não uma configuração funcional nem uma fonte alternativa de estado.


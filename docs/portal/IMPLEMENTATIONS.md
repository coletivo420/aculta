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

Desde Game G3, helpers de submódulos opcionais também são declarados nos próprios
arquivos de serviços e descritos no registro central. O gate confere o conjunto
root + submódulos; o painel continua mostrando somente serviços do container ativo.
`aculta_user.author_identity_resolver` reutiliza a política e o link público do
perfil para produzir a identidade base de autoria com cacheabilidade. O payload
é limitado a variante, apelido e link canônico opcional; avatar e ações exigem
extensão específica e passam pela policy do `aculta_user`. Contrato em
[AUTHOR-CARD-API.md](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARD-API.md).
`aculta_user.author_card_variant_resolver` seleciona a variante de autoria pelo
bundle hospedeiro dos Comments compartilhados; não substitui Entity Access nem
autoriza o contexto. As variantes e os contratos de cada host estão em
[AUTHOR-CARD-VARIANTS.md](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARD-VARIANTS.md).
`aculta_game.achievement_presentation` aplica a mesma política de exibição à
conta e ao perfil público. Contrato e evidências em
[execução do Game](../../web/modules/custom/aculta_portal/modules/aculta_game/docs/EXECUCAO.md).

`aculta_lms.course_completion_publisher` publica o contrato tipado de conclusão
aprovada após a persistência pelo LMS. Ainda não há produtor nem consumidor
ativo; ver [contrato do submódulo](../../web/modules/custom/aculta_portal/modules/aculta_lms/docs/CONTRACT.md).

O viewer PDF está no submódulo independente
[`aculta_pdf_viewer`](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md),
que registra seu formatter, serviço e biblioteca. Ele não é helper do Portal nem
depende do LMS; o contrato global está em [integração PDF](PDF-VIEWER-INTEGRATION.md).

O YAML de serviços define a integração registrada; o container ativo define quais serviços estão disponíveis; os registros PHP descrevem esses serviços para administração; a documentação específica define seu contrato. A tela é um relatório, não uma configuração funcional nem uma fonte alternativa de estado.


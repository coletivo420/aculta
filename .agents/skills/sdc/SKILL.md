---
name: sdc
description: Cria, revisa e compõe Single Directory Components no tema aculta420_v2, preservando schema, stories, acessibilidade e tokens do projeto.
---

# SDC no ACULTA

Use esta skill ao criar ou alterar um SDC. Integração editorial com UI Patterns usa também `ui-patterns`; estilo reutilizável usa `ui-styles`; CSS segue `css`; token de módulo segue `aculta-data-portal-ui`.

## Decisão antes de criar

1. Procure em `web/themes/custom/aculta420_v2/components/` por nome, função, props, slots e composição semelhante. Leia o `.component.yml`, Twig, CSS, stories e dependências encontrados.
2. Reuse o SDC quando semântica, markup e contrato de props forem compatíveis. Modifique-o quando a mudança for uma capacidade do mesmo componente. Componha SDCs quando a página ou estrutura for formada por peças existentes. Crie um SDC quando houver responsabilidade visual/semântica própria que não caiba nos anteriores.
3. Não crie duplicatas editoriais para resolver integração de origem, layout, view mode ou formatação. Use `ui-patterns` para expor o SDC existente.
4. Classifique a peça como superfície, conteúdo ou composição. Superfícies não devem absorver regras de negócio de conteúdo; composições devem reutilizar peças menores quando isso já existir.

## Estrutura e contrato

O diretório canônico é `web/themes/custom/aculta420_v2/components/<nome>/`. O nome de máquina é `<nome>` e a tag do componente é `aculta420_v2:<nome>`. Os arquivos usuais são `<nome>.component.yml`, `<nome>.twig`, `<nome>.css`, bibliotecas JS/CSS e uma ou mais stories `<nome>.<variante>.story.yml`.

- Comece por um componente equivalente e pelo schema existente. Preserve o `$schema` oficial e as convenções de metadados já usadas no tema.
- Declare props como objeto, com tipo explícito, `additionalProperties: false`, `required` somente para campos realmente necessários, `enum` para conjuntos fechados e `default` coerente com o Twig. Use `null` somente quando o contrato aceitar ausência explícita.
- Declare slots com títulos e descrições úteis. Use slots para conteúdo renderizado, props para valores tipados. Não passe entidade, usuário ou render array inteiro como prop.
- Defina variantes como enum quando forem escolhas do contrato. Não transforme qualquer classe de estilo em novo SDC.
- Prefira assets do próprio componente e carregamento condicionado pelo render do SDC. Declare dependências de bibliotecas com o mecanismo do schema Core suportado no branch atual; confira outro componente e o gate antes de inventar sintaxe.
- Reuse bibliotecas de terceiros já aprovadas e a versão presente no projeto. Não adicione Bootstrap, jQuery ou outro pacote ao tema V2.

## Twig, render e segurança

- Renderize com contexto explícito: `include('aculta420_v2:nome', { ... }, with_context = false)`.
- Preserve `attributes`, `create_attribute()` e render arrays fornecidos pelo chamador. Não remonte HTML de conteúdo em string.
- Twig escapa por padrão. Não use `|raw` sem provar que a entrada é markup confiável já renderizado pelo Drupal.
- Use elementos semânticos e hierarquia de títulos correta. Controles devem ter nome acessível, estado, foco visível e comportamento por teclado.
- Ícone decorativo fica oculto de tecnologia assistiva. Ícone informativo precisa de nome acessível no controle ou texto adjacente. Consulte `ui-icons` para os packs atuais e seus limites.

## Estilo e composição

- Use tokens semânticos e estilos ACULTA. Não fixe cores, modo escuro ou seletores de rota no CSS do componente. Consulte `css` e `aculta-data-portal-ui`.
- CSS do componente deve ficar encapsulado e funcionar isolado em claro e escuro, nos purposes aplicáveis e a partir de 320 CSS px.
- Escolha entre prop/variante do SDC, UI Style, componente composto e classe utilitária segundo `ui-styles/references/decisao-de-abstracao.md`.
- Não adicione classe de apresentação a módulo. Use `data-portal-ui` e o mapa existente.

## Stories, integração e validação

1. Crie stories para os estados e combinações importantes usando nomes e formato já existentes. Não use dados reais nem conteúdo sensível.
2. Verifique preview isolado e UI Patterns Library. Para exposição editorial, siga `ui-patterns` e confirme os submódulos disponíveis no Composer e em `config/sync/core.extension.yml`.
3. Rode gates proporcionais: `scripts/validate-aculta420-sdc-schemas.php`, `scripts/validate-aculta420-v2-sdc.php`, `scripts/validate-aculta420-ui-styles.php` ou `scripts/validate-aculta420-v2-foundations.php` conforme o diff. A matriz fica em `aculta-gates-por-escopo`.
4. Use teste de navegador já existente para a Library, modos claro/escuro e mobile/desktop. Captura não executada fica DEFERRED.

## Referências sob demanda

- Schema, props, slots e stories: `references/schema-e-stories.md`.
- Integração com biblioteca editorial: skill `ui-patterns`.
- Tokens e CSS: skill `css`.
- Estilos configuráveis: skill `ui-styles`.
- Ícones: skill `ui-icons`.

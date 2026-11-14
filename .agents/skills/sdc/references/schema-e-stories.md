# Contrato SDC, schemas e stories

Leia quando alterar contrato, schema, template ou preview. Confirme o formato no próprio tema e na versão do Drupal registrada em `composer.lock`; o checkout pode não conter `vendor/` ou o Core.

## Metadata

Os componentes atuais usam `<nome>.component.yml`, JSON Schema embutido sob `props`, `slots` nomeados, metadados `name`, `description`, `status` e `group`, e `assets.css.component` para CSS do SDC. A fonte real é `web/themes/custom/aculta420_v2/components/`. Exemplos úteis incluem `surface`, `surface-panel`, `button` e `icon`.

Contrato recomendado:

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/11.x/core/assets/schemas/v1/metadata.schema.json
name: Nome legível
description: Responsabilidade visual do componente.
status: stable
group: Grupo existente
props:
  type: object
  additionalProperties: false
  required: [title]
  properties:
    title:
      type: string
      minLength: 1
      title: Título
    tone:
      type: string
      enum: [canvas, raised]
      default: raised
      title: Superfície
slots:
  content:
    title: Conteúdo
    description: Conteúdo renderizado pelo Drupal.
assets:
  css:
    component:
      example.css: {}
```

Este trecho é apenas um formato ilustrativo. Preserve valores reais do componente e não copie `required`, enumerações ou dependências sem necessidade.

## Twig e slots

- Use `{{ title|default('') }}` para lidar com chamadas que não forneçam valor. Defaults do schema documentam o contrato e não substituem defaults de renderização.
- Renderize slots conforme o padrão do componente e mantenha markup fornecido como render array. Não transforme entidades em string.
- Use `attributes` em elementos raiz ou no elemento que recebe classes e atributos externos. Evite atributos duplicados entre o template e o chamador.
- Inclua componentes filhos por `include` com props explícitas e `with_context = false`.
- Evite parâmetros ou Twig functions ainda não demonstrados no tema ou suportados pela versão declarada.

## Stories

Stories são arquivos YAML ao lado do componente, por exemplo `surface.default.story.yml`. O nome amigável fica em `name`, a referência em `component`, e props/slots devem obedecer ao schema. Use `inline_template` só para conteúdo pequeno de preview. Crie stories separadas para estados que tenham intenção visual diferente, sem multiplicar combinações irrelevantes.

O diretório já tem stories de composição como `surface.composition-tabs.story.yml` e variantes como `surface-accent.tricolor-left.story.yml`. Prefira esses padrões a criar uma segunda convenção.

## Validação

- `php scripts/validate-aculta420-sdc-schemas.php`
- `php scripts/validate-aculta420-v2-sdc.php`
- Teste focal ou preview isolado quando disponível.
- Preview no navegador com claro/escuro e largura mobile é validação visual separada. Sem execução, marque DEFERRED.

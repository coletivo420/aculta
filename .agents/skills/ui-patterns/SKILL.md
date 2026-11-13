---
name: ui-patterns
description: Expõe e integra SDCs existentes com UI Patterns 2 no Drupal, incluindo Library, blocos, Views e Layout Builder.
---

# UI Patterns 2 no ACULTA

Use quando um SDC existente precisa ser selecionável em construção editorial, bloco, formatter, display de View ou Layout Builder. Para criar/alterar o componente, use `sdc`. Para opções visuais de classe, use `ui-styles`. A referência primária é UI Patterns 2.x, nunca a documentação 1.x.

## Regras do projeto

- UI Patterns 2 integra SDC do Core. Antes de criar arquivos, procure o componente em `web/themes/custom/aculta420_v2/components/` e confirme seus props/slots no `.component.yml`.
- Não duplique SDC para torná-lo editorial. Preserve componentes internos e reutilizáveis na Library. A exposição deve seguir os plugins e permissões que já existem.
- Selecione somente a integração necessária. Blocos, campos, formatters, layouts e Views são submódulos distintos; confira `composer.json`, `composer.lock` e `config/sync/core.extension.yml` antes de orientar ativação ou configuração.
- Sources, component forms, prop types, context switchers, mapeamentos e transformações dependem das APIs da versão instalada. Confira pacote, lock, filesystem, export e runtime separadamente.
- Use props para valores configuráveis tipados e slots para conteúdo renderizado/composição. Use sources quando o valor precisa vir de contexto/dados Drupal e o plugin estiver disponível. Não passe entidades inteiras nem dados pessoais/sensíveis indiscriminadamente às props; mapeie apenas os campos mínimos.
- Preserve render arrays, `#cache`, cache contexts, tags e max-age, além de acesso e traduções. Evite converter render arrays em strings ou dados de entidade em arrays sem metadados.
- Para aninhamento, reutilize componentes filhos por slots/prop compatível. Evite árvores recursivas sem limite ou construções que removam metadados de cache.
- A UI Patterns Library é o catálogo único de SDCs/stories. O módulo ACULTA `aculta_ui_library` conserva apenas uma rota de redirecionamento legado e não deve implementar catálogo ou previews paralelos.

## Fluxo

1. Confirme versão e módulos no Composer e na configuração exportada. O clone auditado não contém Core/contrib, então APIs de runtime devem ser confirmadas no ambiente que os instala.
2. Localize o SDC e leia schema, Twig, stories e gates. Não altere o contrato do componente só para contornar um source inexistente.
3. Escolha a integração conforme o alvo: `ui_patterns_blocks` para blocos; `ui_patterns_layouts` para layouts de slots/regiões; `ui_patterns_field` para persistir árvore/configuração editorial em campo; `ui_patterns_field_formatters` para renderizar campos existentes; `ui_patterns_views` para rows, styles ou display de View. Não substitua uma integração por plugin próprio sem necessidade demonstrada.
4. Configure props e slots sem introduzir segunda cópia do markup. Preserve access checks, render arrays e cache metadata.
5. Layouts: slots correspondem às regiões e props às configurações. Verifique `thumbnail.webp`, grid e arquitetura de cinco regiões/ADR-054. Preserve layouts ACULTA existentes.
6. Fields e formatters: verifique cardinalidade, per-item versus campo multivalorado, revisão, tradução e acesso a entidades referenciadas. Em `ui_patterns_field`, confirme widget, persistência da árvore, revisão e tradução na versão instalada; não crie nem habilite campos nesta tarefa.
7. Views: confira row/style/display plugin e mapeie resultados, exposed form, empty, pager, header e footer apenas onde suportado. Preserve cache, acesso, filtros e componentes de listagem atuais.
8. Mantenha todos os SDCs na UI Patterns Library, inclusive internos, reutilizáveis e editoriais. Trate performance com assets condicionais e previews sob demanda, nunca escondendo componentes.
9. UI Patterns Field Group consta como pacote, mas não como módulo habilitado no export. Não habilite: confirme compatibilidade no código instalado e considere que o projeto declara não estar coberto pela política de avisos de segurança Drupal.
10. UI Patterns Library é o catálogo único de SDC/stories. `aculta_ui_library` é somente uma rota de compatibilidade protegida que redireciona para esse catálogo; não reintroduzir preview/lista própria de blocos.
11. Use `ui-styles` para estilos administrativos independentes e tokens do tema para cor/superfície.
12. Teste Library, preview, formulário, permissões e saída renderizada nos fluxos presentes. Use browser script existente e gates de `aculta-gates-por-escopo`.

## Referências sob demanda

- Módulos, versões e limites deste checkout: `references/integracoes-ACULTA.md`.
- Documentação oficial do projeto: https://project.pages.drupalcode.org/ui_patterns/.
- Padrões de componente: skill `sdc`.
- Estilos editoriais: skill `ui-styles`.

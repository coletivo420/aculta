# THEME-UX 0.8 — B6-S1a-L3c: gate reproduzível de assets

**Data:** 2026-10-22 · **PR:** #297 · **Estado:** ferramenta de auditoria commitada; execução no checkout/Composer pendente.

## Implementação

O script de leitura `scripts/audit-aculta420-sdc-assets.php` varre componentes existentes e verifica:

- a presença do Twig correspondente ao arquivo `.component.yml`;
- a declaração de schema SDC do Drupal Core (reportando schemas legados);
- o vínculo `component: aculta420:<name>` de cada story;
- chaves de `props` e `slots` das stories comparadas aos campos declarados no contrato;
- contagens de metadados, Twig, CSS e stories; resultado determinístico por caminho ordenado;
- exit code diferente de zero quando encontrar inconsistências.

Executar a partir da raiz do projeto, após `composer install`:

```bash
php -l scripts/audit-aculta420-sdc-assets.php
php scripts/audit-aculta420-sdc-assets.php
php scripts/validate-aculta420-sdc-schemas.php
```

**Não declarar PASS antes de executar.** A ferramenta usa Symfony YAML já disponível no projeto, não inicia Drupal, não altera arquivos e não depende de Runtime. Não valida compilação Twig, seletor CSS, cascata, contraste, conteúdo dos slots nem comportamento de navegador. Não substitui SDC Devel, UI Patterns Library, UI Styles ou design foundations.

## Achados já estabelecidos por leitura anterior

| Finding | Fonte | Estado |
| --- | --- | --- |
| Quatro schemas legados em componentes de doação | B6-S1a-L2 | CONFIRMADO POR LEITURA; script deve sinalizá-los |
| Story padrão do carrinho enviava `items` na raiz | B6-S1a-L3 | CORRIGIDO na story usando `sites` |
| Context Header e Wiki Entry podem emitir H1 | B6-S1a | Exige integração e validação Runtime; fora do alcance deste gate |
| Context Tabs e Product Tabs têm semânticas distintas | B6-S1a | Preservar contratos separados |

## Próximos passos

1. **B6-S1a-L3d:** executar o script num checkout limpo com Composer e guardar saída completa, inventário de findings, severidade e HEAD. Corrigir apenas falsos positivos comprovados; ampliar as verificações para Twig/CSS de modo incremental, com fixtures/expectativas.
2. **B6-S1b:** cruzar os componentes com consumidores reais PHP/Twig/Views/config e verificar cache/access em providers.
3. **B6-S2:** migrar os schemas legados com testes; depois corrigir incompatibilidades de story e render identificadas.
4. **B6-R:** validar em homelab as rotas, Institution Bar + Domain Header, Core/UI Patterns/UI Styles, responsividade, A11y, light/dark e AJAX.

**Regra de arquitetura:** todos os reusos transversais devem ter base Core SDC/UI Suite; nenhum Twig isolado será promovido como componente compartilhado. O shell multidomínio permanece global e inalterado.

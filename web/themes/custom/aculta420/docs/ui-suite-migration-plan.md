# Plano de integração UI Suite / UI Patterns / UI Styles

**Escopo:** ACULTA420 e a configuração Drupal necessária para adotar SDC/UI Patterns como modelo de componentes integrado ao Bootstrap 5.

**Estado inicial verificado em 2026-10-18:** Drupal 11.4.10; tema base `bootstrap` 5.0.2; `ui_patterns` e `ui_patterns_library` 2.0.22 presentes/ativos; seis SDCs em `components/`; Display Suite 3.37.0 no projeto. O Runtime de testes é `aculta_runtime` em `https://aculta.toca.net.br`. A configuração Runtime tem diferenças históricas em relação ao sync; cada importação será parcial e revisada.

## Decisões e limites

- Core SDC continua sendo o formato dos componentes ACULTA420. UI Patterns expõe os SDCs ao site building; UI Suite Bootstrap fornece padrões de referência e integração Bootstrap; UI Styles expõe classes semânticas do tema como opções editoriais. Nenhum deles transfere regra de negócio, access, cache ou preparação de dados do Portal para o tema.
- UI Suite Bootstrap é uma biblioteca de padrões, não um tema-base substituto. ACULTA420 continua baseado no tema `bootstrap` e conserva seus tokens, CSS, JavaScript e Bootstrap Icons.
- Usar versões estáveis compatíveis com Drupal 11 quando disponíveis: `ui_patterns` 2.0.23, `ui_suite_bootstrap` 5.2.3 e `ui_styles` 8.x-1.22. Não adotar branches de desenvolvimento para essas dependências.
- `ui_patterns_ds` 1.0.0-alpha3 e `ui_patterns_field_group` 2.0.0-beta2 foram pedidos explicitamente, mas não têm cobertura da política de segurança do Drupal.org. Mantê-los como integrações opcionais, documentar o risco, testar versões/compatibilidade e habilitar cada uma somente onde houver uso real. Não tornar suas APIs dependência dos componentes centrais.
- `sdc_devel` 1.0.3 e `storybook` 1.0.4 são ferramentas de desenvolvimento (`require-dev`), sem rotas/permissões abertas em produção. Storybook exige aplicativo JS separado e configuração CORS; isso fica em configuração local de desenvolvimento, nunca em configuração compartilhada/prod. Nunca conceder `render storybook stories` a anônimos em produção.
- As integrações de UI Patterns e UI Styles serão habilitadas apenas após inventário de suas dependências e configuração. Não ativar módulos experimentais por padrão.
- Migração de componentes é incremental. Manter contratos neutros, variantes limitadas, assets locais por componente e tokens ACULTA. Não adicionar uma suíte concorrente de componentes.

## Fases e commits

1. **Plano e inventário** (este documento): localizar todos os SDCs, templates Twig, padrões legados, stories, layouts Display Suite, estilos e consumidores em config/Portal; mapear destino e dependências. Registrar lacunas antes de mover arquivos.
2. **Dependências e superfície de módulos:** atualizar UI Patterns para release estável atual; adicionar UI Suite Bootstrap/UI Styles e integrações solicitadas com versões/escopo Composer explícitos; separar ferramentas de desenvolvimento. Habilitar no Runtime de teste somente os módulos compatíveis necessários, sem importação global.
3. **Fundação e catálogo:** ligar UI Patterns Library aos SDCs reais do tema; integrar padrões Bootstrap mantendo ownership ACULTA420; definir catálogo UI Styles sem expor primitivas de paleta ou classes de implementação instáveis.
4. **Porte dos componentes existentes:** migrar cada padrão legado existente e seus pontos de consumo para SDC/UI Patterns; preservar markup, semântica, acessibilidade, cache e comportamento. Remover cada implementação antiga apenas após confirmar que não há consumidores em Twig, Views, layout, config ou código.
5. **Display Suite e Field Group:** adicionar adaptadores pedidos atrás de módulos opcionais, migrar usos comprovados e comparar saída. Sem uso real, entregar integração disponível e exemplo/documentação, sem habilitação no site.
6. **Stories e validação:** criar stories determinísticas para os componentes existentes; configurar Storybook sem permissões públicas e validar SDCs com SDC Devel. Documentar geração e execução local.
7. **Runtime, documentação e encerramento:** revisar diff de configuração objeto a objeto; aplicar ao Runtime de teste apenas o necessário para smoke test após checar recuperação; atualizar CHANGELOG, README e evidências; commits atômicos na branch da PR existente. Produção permanece fora do escopo.

Cada fase terá commit próprio. A fase UI Suite só será considerada concluída após inventário de migração sem consumidores antigos descobertos e verificação dos componentes preservados.

## Inventário inicial

Em 2026-10-18, o inventário encontrou **30 SDCs** em `components/`: 9 de conteúdo (`course_card`, `editorial-card`, `institutional-notice`, `messenger-compose`, `messenger-item`, `messenger-notification`, `product-buy`, `product-details`, `project-card`); 6 de doação (`donation-card`, `donation-dedication`, `donation-notification`, `donation-pane`, `donation-quick`, `donation-summary-container`); 2 de feedback (`messenger-state`, `unread-badge`); e 13 padrões (`alerts-trigger`, `carousel`, `cart-offcanvas`, `content-grid`, `content-section`, `hero`, `messenger-list`, `messenger-read-status`, `messenger-trigger`, `product-tabs`, `rail`, `revista-publication`, `wiki-entry`).

Há dois layouts de Display Suite em `aculta420.layouts.yml`, e os templates correspondentes `ds-two-column` e `ds-three-column`. Não foram encontrados arquivos `*.stories.twig`, `*.stories.yml` ou catálogos `ui_styles.yml`. Os 30 componentes já usam SDC; o porte desta iniciativa é de integração/publicação e de migração de templates/padrões legados encontrados pelos consumidores, não uma conversão mecânica de SDC para outro formato. Revisar cada componente e consumidor antes de qualquer remoção.

## Fontes consultadas

- [UI Patterns: releases e integrações SDC](https://www.drupal.org/project/ui_patterns) — 2.0.23; Drupal ^10.6/^11.3/^12; integrações por submódulo e suporte a SDC.
- [UI Suite Bootstrap: releases](https://www.drupal.org/project/ui_suite_bootstrap/releases) — 5.2.3 compatível com Drupal ^11.3/^12.
- [UI Styles](https://www.drupal.org/project/ui_styles) — 8.x-1.22 compatível com Drupal ^10.3/^11/^12; estilos são plugins declarados em YAML e integrations são módulos separados.
- [SDC Devel](https://www.drupal.org/project/sdc_devel) — 1.0.3 compatível com Drupal 11; valida componente, YAML e Twig.
- [Storybook Drupal](https://www.drupal.org/project/storybook) — 1.0.4 compatível com Drupal ^9/^10/^11; necessita aplicação Storybook, e suas instruções de CORS/permissões são apenas para ambiente de desenvolvimento.
- [UI Patterns Display Suite](https://www.drupal.org/project/ui_patterns_ds) — 1.0.0-alpha3 para Drupal ^10/^11; projeto sem cobertura da política de segurança.
- [UI Patterns Field Group](https://www.drupal.org/project/ui_patterns_field_group) — 2.0.0-beta2 para Drupal ^9/^10/^11; integração provisória 2.x e projeto sem cobertura da política de segurança.
- [Padrão de Drupal 11 do ACULTA](../../../../../docs/portal/DRUPAL-11-STANDARDS.md), [Component Design System](../../../../../docs/portal/COMPONENT-DESIGN-SYSTEM.md) e [design system ACULTA420](design-system.md).

Versões e compatibilidade acima foram conferidas em Drupal.org em 2026-10-18; repetir a conferência no commit de cada dependência porque releases e avisos mudam.

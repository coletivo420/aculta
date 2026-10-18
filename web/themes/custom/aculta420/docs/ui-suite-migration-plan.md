# Plano de integração UI Suite / UI Patterns / UI Styles

**Escopo:** ACULTA420 e a configuração Drupal necessária para adotar SDC/UI Patterns como modelo de componentes integrado ao Bootstrap 5.

**Estado inicial verificado em 2026-10-18:** Drupal 11.4.10; tema base `bootstrap` 5.0.2; `ui_patterns` e `ui_patterns_library` 2.0.22 presentes/ativos; seis SDCs em `components/`; Display Suite 3.37.0 no projeto. O Runtime de testes é `aculta_runtime` em `https://aculta.toca.net.br`. A configuração Runtime tem diferenças históricas em relação ao sync; cada importação será parcial e revisada.

## Decisões e limites

- Core SDC continua sendo o formato dos componentes ACULTA420. UI Patterns expõe os SDCs ao site building; UI Suite Bootstrap fornece padrões de referência e integração Bootstrap; UI Styles expõe classes semânticas do tema como opções editoriais. Nenhum deles transfere regra de negócio, access, cache ou preparação de dados do Portal para o tema.
- UI Suite Bootstrap é uma biblioteca de padrões, não um tema-base substituto. ACULTA420 continua baseado no tema `bootstrap` e conserva seus tokens, CSS, JavaScript e Bootstrap Icons.
- Usar versões estáveis compatíveis com Drupal 11 quando disponíveis: `ui_patterns` 2.0.23, `ui_suite_bootstrap` 5.2.3 e `ui_styles` 8.x-1.22. Não adotar branches de desenvolvimento para essas dependências.
- `ui_patterns_ds` 1.0.0-alpha3 e `ui_patterns_field_group` 2.0.0-beta2 foram autorizados explicitamente mesmo sem cobertura da política de segurança do Drupal.org. Instalar e integrar ambos; documentar o risco, verificar compatibilidade e manter seu uso isolado dos contratos centrais de SDC. São adaptadores de Display Suite e Field Group, não formatos concorrentes de componentes.
- `sdc_devel` 1.0.3 e `storybook` 1.0.4 são ferramentas de desenvolvimento (`require-dev`), sem rotas/permissões abertas em produção. Storybook exige aplicativo JS separado e configuração CORS; isso fica em configuração local de desenvolvimento, nunca em configuração compartilhada/prod. Nunca conceder `render storybook stories` a anônimos em produção.
- Section Library 2.0.1 é compatível com Drupal 11 e depende de Core Layout Builder. É reutilização de conteúdo editável: templates salvos são entidades de conteúdo, diferentes de componentes SDC e de configuração exportável. Permissões de visualizar, importar, criar, editar e excluir templates serão atribuídas somente ao papel existente `administrator`; nenhum papel editorial receberá essas permissões. Não pré-criar conteúdo ou templates.
- UI Suite Bootstrap é distribuído como tema contribuído. Instalá-lo como tema de suporte para descoberta de seus componentes; manter `aculta420` como tema ativo e `bootstrap` como base. A descoberta de componentes Core percorre diretórios de todos os módulos e temas instalados (`ComponentPluginManager::getScanDirectories()` no Core 11.4.10).
- Fences 3.0.9 é estável, coberto pela política de segurança e declara Drupal ^9/^10/^11. Ele controla o elemento semântico/classe do wrapper e itens de um campo; UI Patterns Field renderiza um campo por um componente. São ferramentas complementares por função, mas não devem ser aplicadas simultaneamente ao mesmo display sem uma necessidade demonstrada. A integração Bootstrap inclui preset opcional via `hook_theme()`; não habilitar a substituição global de template sem auditar as sugestões especiais do tema base.
- As integrações de UI Patterns e UI Styles serão habilitadas apenas após inventário de suas dependências e configuração. Não ativar módulos experimentais por padrão.
- Migração de componentes é incremental. Manter contratos neutros, variantes limitadas, assets locais por componente e tokens ACULTA. Não adicionar uma suíte concorrente de componentes.

## Fases e commits

1. **Plano e inventário** (este documento): localizar todos os SDCs, templates Twig, padrões legados, stories, layouts Display Suite, estilos e consumidores em config/Portal; mapear destino e dependências. Registrar lacunas antes de mover arquivos.
2. **Dependências e superfície de módulos:** atualizar UI Patterns para release estável atual; adicionar UI Suite Bootstrap/UI Styles, Fences, Section Library e integrações solicitadas com versões/escopo Composer explícitos; separar ferramentas de desenvolvimento. Habilitar no Runtime de teste somente os módulos compatíveis necessários, sem importação global.
3. **Fundação e catálogo:** ligar UI Patterns Library aos SDCs reais do tema; integrar padrões Bootstrap mantendo ownership ACULTA420; definir catálogo UI Styles sem expor primitivas de paleta ou classes de implementação instáveis; habilitar Section Library com Layout Builder e permissões editoriais restritas, sem criar seções/template editorial automaticamente.
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
- [Section Library](https://www.drupal.org/project/section_library) — 2.0.1 compatível com Drupal ^10.3/^11; usa Layout Builder e salva templates como conteúdo, sem sobreposição com biblioteca de configuração de páginas.
- [Fences](https://www.drupal.org/project/fences) — 3.0.9 compatível com Drupal ^9/^10/^11 e coberto pela política de segurança; oferece controle de tags/classes de wrapper de field. O issue queue atual inclui uma limitação relatada nas sugestões `field--node--uid/created` e pedidos de integração Views; conferir os templates especiais do Bootstrap antes de ativar o override.
- [UI Patterns Display Suite](https://www.drupal.org/project/ui_patterns_ds) — 1.0.0-alpha3 para Drupal ^10/^11; projeto sem cobertura da política de segurança.
- [UI Patterns Field Group](https://www.drupal.org/project/ui_patterns_field_group) — 2.0.0-beta2 para Drupal ^9/^10/^11; integração provisória 2.x e projeto sem cobertura da política de segurança.
- [Padrão de Drupal 11 do ACULTA](../../../../../docs/portal/DRUPAL-11-STANDARDS.md), [Component Design System](../../../../../docs/portal/COMPONENT-DESIGN-SYSTEM.md) e [design system ACULTA420](design-system.md).

## Recomendação VVJ (pesquisa de 2026-10-18)

O VVJ Core apresenta dez formatos irmãos para Views. Não instalar a família inteira: cada formato adiciona uma interação e só deve entrar quando existir uma View cujo conteúdo e objetivo editorial se beneficiem dela. ACULTA420 já usa VVJB como engine de carrossel, e sua regra de design proíbe duplicar essa função com outro carrossel.

- **Avaliar VVJA (accordion)** quando existir uma listagem editorial real de perguntas frequentes ou conteúdo expansível em View. Não usar como substituto de componentes SDC, formulários nem navegação.
- **Avaliar VVJT (tabs)** somente para alternar conjuntos de resultados pertencentes a uma View. Não usar na Minha Conta, checkout, formulários ou rotas independentes; a decisão da arquitetura da Conta já exclui esse uso.
- **Manter VVJB** para carrosséis de Views que já o utilizam.
- **Não adotar agora VVJC (3D carousel), VVJF (flip box), VVJP (parallax), VVJR (reveal), VVJS (slideshow) ou VVJL (lightbox)**: não há caso de uso levantado no inventário; efeitos 3D/parallax/reveal aumentariam movimento e custo de acessibilidade, e slideshow/carrossel/lightbox duplicariam componentes ou infraestrutura existentes. Reavaliar apenas diante de requisito editorial concreto.
- VVJ Core não é ferramenta geral de construção de páginas; é infraestrutura compartilhada pelos formatos de Views. A suíte declara versões 2.x para Drupal 11.3+/12 e PHP 8.3+, mas a compatibilidade declarada não substitui revisão dos plugins de Views, markup, teclado, redução de movimento e cache no uso real.

Fontes: [VVJ Core e catálogo de formatos](https://www.drupal.org/project/vvj_core), [VVJT e seus requisitos/limitações](https://www.drupal.org/project/vvjt), [VVJA e releases](https://www.drupal.org/project/vvja/releases), [VVJS e releases](https://www.drupal.org/project/vvjs/releases). Antes de instalar um formato, confirmar sua release 2.x atual, cobertura de segurança e configuração da View que o consumirá.

Versões e compatibilidade acima foram conferidas em Drupal.org em 2026-10-18; repetir a conferência no commit de cada dependência porque releases e avisos mudam.

# Plano de integração UI Suite / UI Patterns / UI Styles

**Escopo:** ACULTA420 e a configuração Drupal necessária para adotar SDC/UI Patterns como modelo de componentes integrado ao Bootstrap 5.

**Estado inicial verificado em 2026-10-18:** Drupal 11.4.10; tema base `bootstrap` 5.0.2; UI Patterns 2.0.x ativo; 31 SDCs em `components/`; Display Suite 3.37.0 no projeto. O Runtime de teste usa o banco `aculta_runtime`. A configuração Runtime tem diferenças históricas em relação ao sync; cada importação será parcial e revisada.

**Atualização da execução (2026-10-18):** os oito módulos VVJ aprovados estão no Composer e habilitados no Runtime de teste. A View `aculta_album_fotos` está convertida para `views_vvjs` no sync e Runtime por alteração direcionada do único objeto de configuração; cache reconstruído. Nenhuma importação global foi feita. Inspeção visual e teclado/toque da galeria ficam pendentes; o restante da iniciativa UI Suite segue nas fases abaixo.

**Complemento (2026-10-18):** slides do álbum agora usam a mesma verificação de acesso que as miniaturas, ordenam pelo delta da referência e agregam metadados de cache do álbum, Media, File e AccessResult. A View `galeria_produto` também usa VVJS, preservando o modo `produto_galeria`, controles, estilo contido e sem autoplay. Smoke test no Runtime confirmou `views_vvjs` e 7 linhas na galeria do produto e 63 linhas no álbum; cache renderizado: 8.039 e 54.778 bytes, respectivamente. As duas Views foram verificadas nos purposes Main e Revista. Inspeção visual por navegador permanece pendente. O relatório padrão do Drush sem `--uri` pode dizer `http://default`; operações de Runtime devem informar explicitamente o purpose de teste, sem registrar uma URI de teste fixa nesta documentação. Gate ampliado para auditar estilos de Views e modos de exibição.

**Otimização de miniaturas (2026-10-18):** a página de Goiânia tem 63 slides e 63 miniaturas. Firefox baixava todas as 63 imagens de palco de 1000 px (8,8 MB) no carregamento, competindo com a coluna de miniaturas. `ThemeHooks` agora remove do HTML o `src` dos slides inativos e guarda a URL em `data-aculta-lazy-src`; `album-slideshow.js` restaura a imagem do slide atual e do próximo por meio do evento público `vvj:slideChanged`. Smoke test Firefox no Runtime: 4 imagens de palco (572 KB) e 20 miniaturas próximas à área visível (62 KB) no primeiro carregamento; clicar na miniatura 60 carregou e ativou o slide correspondente. Não altera ordenação, acesso, PhotoSwipe nem a sequência total.

**Integração ACULTA420 (2026-10-18):** UI Suite Bootstrap 5.2.3 foi removido do Composer e do Runtime. O tema não foi habilitado. Foram adicionados `aculta420:card` e o catálogo próprio `aculta420.ui_styles.yml`; UI Patterns e UI Styles foram conectados às integrações estáveis selecionadas. Nenhuma View existente foi convertida por essa integração.

## Decisões e limites

- Core SDC continua sendo o formato dos componentes ACULTA420. UI Patterns expõe os SDCs do tema ao site building; o código útil do UI Suite Bootstrap é apenas referência para portes revisados no ACULTA420; UI Styles expõe classes semânticas próprias como opções editoriais. Nenhum deles transfere regra de negócio, acesso, cache ou preparação de dados do Portal para o tema.
- ACULTA420 continua baseado no tema `bootstrap` 5.0.2 e conserva seus tokens, CSS, JavaScript e Bootstrap Icons. UI Suite Bootstrap não é instalado, habilitado nem mantido no Composer.
- As versões estáveis analisadas foram `ui_patterns` 2.0.23 e `ui_styles` 8.x-1.22. UI Suite Bootstrap 5.2.3 foi baixado temporariamente para análise, sem dependência permanente.
- `ui_patterns_ds` 1.0.0-alpha3 e `ui_patterns_field_group` 2.0.0-beta2 foram autorizados explicitamente mesmo sem cobertura da política de segurança do Drupal.org. Instalar e integrar ambos; documentar o risco, verificar compatibilidade e manter seu uso isolado dos contratos centrais de SDC. São adaptadores de Display Suite e Field Group, não formatos concorrentes de componentes.
- `sdc_devel` 1.0.3 e `storybook` 1.0.4 são ferramentas de desenvolvimento (`require-dev`), sem rotas/permissões abertas em produção. Storybook exige aplicativo JS separado e configuração CORS; isso fica em configuração local de desenvolvimento, nunca em configuração compartilhada/prod. Nunca conceder `render storybook stories` a anônimos em produção.
- A família VVJ 2.x integra o ecossistema de Views do projeto. Instalar e habilitar VVJA, VVJB, VVJF, VVJH, VVJL, VVJR, VVJS e VVJT; não instalar VVJC (3D Carousel) nem VVJP (Parallax), conforme decisão do responsável. Usar releases estáveis; VVJ Core é dependência compartilhada, não um padrão visual extra. Não colocar comportamento de Views no Portal nem editar os módulos contrib.
- Section Library 2.0.1 é compatível com Drupal 11 e depende de Core Layout Builder. É reutilização de conteúdo editável: templates salvos são entidades de conteúdo, diferentes de componentes SDC e de configuração exportável. Permissões de visualizar, importar, criar, editar e excluir templates serão atribuídas somente ao papel existente `administrator`; nenhum papel editorial receberá essas permissões. Não pré-criar conteúdo ou templates.
- UI Suite Bootstrap foi baixado e analisado, mas não é dependência nem tema instalado/habilitado do projeto. Os componentes escolhidos são portados para `components/` do ACULTA420, mantendo `bootstrap` como base e `aculta420` como tema público.
- O Core descobre SDCs nos temas habilitados (`ComponentPluginManager::getScanDirectories()` no Core 11.4.10). Por isso não mantemos o UI Suite Bootstrap habilitado só para descoberta; padrões selecionados devem ser adaptados no provider `aculta420`, sem carregar assets globais, configurações, templates ou JavaScript do tema de origem.
- UI Patterns habilita somente as integrações estáveis para blocos, campos, formatters, layouts e Views. Os SDCs ACULTA420 podem ser usados como plugins de site building sem converter automaticamente displays existentes. SDCs do UI Suite não ficam disponíveis porque seu tema não está instalado.
- UI Styles usa um catálogo próprio ACULTA420 de superfície, texto e bordas semânticas. As classes resolvem tokens já existentes em `tokens.css`; não expõem cores primitivas, utilitários Bootstrap genéricos, medidas livres ou seletores por modo. As integrações habilitadas são blocos, Layout Builder, Views e UI Patterns; UI Styles para CKEditor permanece desativado.
- Fences 3.0.9 é estável, coberto pela política de segurança e declara Drupal ^9/^10/^11. Ele controla o elemento semântico/classe do wrapper e itens de um campo; UI Patterns Field renderiza um campo por um componente. São ferramentas complementares por função, mas não devem ser aplicadas simultaneamente ao mesmo display sem uma necessidade demonstrada. A integração Bootstrap inclui preset opcional via `hook_theme()`; não habilitar a substituição global de template sem auditar as sugestões especiais do tema base.
- O inventário e a revisão das integrações estáveis selecionadas foram concluídos para esta etapa. Não ativar módulos experimentais por padrão.
- Migração de componentes é incremental. Manter contratos neutros, variantes limitadas, assets locais por componente e tokens ACULTA. Não adicionar uma suíte concorrente de componentes.

## Fases e commits

1. **Plano e inventário** (este documento): localizar todos os SDCs, templates Twig, padrões legados, stories, layouts Display Suite, estilos e consumidores em config/Portal; mapear destino e dependências. Registrar lacunas antes de mover arquivos.
2. **Dependências e superfície de módulos:** UI Suite Bootstrap permanece fora de Composer. UI Patterns/UI Styles e os módulos VVJ aprovados usam versões/escopo Composer explícitos; separar ferramentas de desenvolvimento. **VVJ concluído para dependência/habilitação.**
3. **Fundação e catálogo:** UI Patterns expõe os SDCs ACULTA420; o catálogo UI Styles não expõe primitivas de paleta ou classes de implementação instáveis. Section Library e permissões editoriais permanecem fora desta integração.
4. **Porte visual incremental:** portar ao sistema ACULTA420 os pontos existentes que se beneficiem dos formatos VVJ, sem alterar a origem do conteúdo ou o comportamento editorial. **Portes:** `aculta_album_fotos` e `galeria_produto` usam VVJS; o álbum mantém PhotoSwipe, paginação, sequência de miniaturas e validação de acesso/cache alinhada. Home e projetos permanecem VVJB. SDC `hero` continua sendo o hero de página preparado pelos campos do nó; VVJH fica disponível para futura View editorial de múltiplos destaques. Não converter os SDCs `product-tabs`, `project-card` ou a navegação da Conta em plugins de Views: são contratos diferentes. VVJF, VVJL, VVJR e VVJT ficam instalados e documentados, sem uso forçado onde não há consumidor adequado.
5. **Display Suite e Field Group:** adicionar adaptadores pedidos atrás de módulos opcionais, migrar usos comprovados e comparar saída. Sem uso real, entregar integração disponível e exemplo/documentação, sem habilitação no site.
6. **Stories e validação:** criar stories determinísticas para os componentes existentes; configurar Storybook sem permissões públicas e validar SDCs com SDC Devel. Documentar geração e execução local.
7. **Runtime, documentação e encerramento:** revisar diff de configuração objeto a objeto; aplicar ao Runtime de teste apenas o necessário para smoke test após checar recuperação; atualizar CHANGELOG, README e evidências; commits atômicos na branch da PR existente. Produção permanece fora do escopo.

Cada fase terá commit próprio. A fase UI Suite só será considerada concluída após inventário de migração sem consumidores antigos descobertos e verificação dos componentes preservados.

## Inventário inicial

Em 2026-10-18, o inventário inicial encontrou **31 SDCs** em `components/`: 9 de conteúdo (`course_card`, `editorial-card`, `institutional-notice`, `messenger-compose`, `messenger-item`, `messenger-notification`, `product-buy`, `product-details`, `project-card`); 6 de doação (`donation-card`, `donation-dedication`, `donation-notification`, `donation-pane`, `donation-quick`, `donation-summary-container`); 2 de feedback (`messenger-state`, `unread-badge`); e 14 padrões (`alerts-trigger`, `carousel`, `cart-offcanvas`, `content-grid`, `content-section`, `hero`, `messenger-list`, `messenger-read-status`, `messenger-trigger`, `product-tabs`, `rail`, `revista-publication`, `social-links-page`, `wiki-entry`). O porte desta etapa adiciona o SDC `card`, chegando a 32 componentes.

Há dois layouts de Display Suite em `aculta420.layouts.yml`, e os templates correspondentes `ds-two-column` e `ds-three-column`. O inventário anterior tinha 31 arquivos de story para 31 SDCs; o card acrescenta duas stories vertical/horizontal. O catálogo `aculta420.ui_styles.yml` oferece escolhas semânticas próprias. Os componentes existentes continuam em Core SDC; o porte desta iniciativa é incremental, não uma conversão mecânica de SDC para outro formato. Revisar cada componente e consumidor antes de qualquer remoção.

## Integração inicial aplicada

- O pacote `drupal/ui_suite_bootstrap` não está em `composer.json`/`composer.lock`, e o tema não está habilitado no Runtime nem em `core.extension`.
- UI Patterns expõe os SDCs do ACULTA420 por integrações estáveis de blocos, campos, formatters, layouts e Views.
- UI Styles expõe três famílias controladas (`Superfície`, `Texto` e `Borda`) via bloco, Layout Builder, Views e UI Patterns. As classes só referenciam tokens semânticos ACULTA420.
- `ui_patterns_views` e `ui_styles_views` registram seus display extenders em `views.settings`; nenhuma View existente foi convertida.
- O SDC `aculta420:card` porta a composição útil do pattern Card do UI Suite (mídia, cabeçalho, conteúdo, rodapé e variação horizontal) e a adapta aos tokens ACULTA420, sem dependência de código ou bibliotecas do tema de origem. Seus exemplos cobrem variação vertical e horizontal.
- Cada porte deve conferir a story de origem, dependências de Bootstrap, bibliotecas locais e acessibilidade; engines do UI Suite não substituem VVJB, VVJS, Core Dialog ou os contratos existentes do ACULTA420.
- UI Styles para CKEditor, UI Patterns para CKEditor, UI Patterns UI experimental, UI Skins e Storybook não foram habilitados nesta integração.

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

## VVJ: módulos e plano de porte (pesquisa de 2026-10-18)

O VVJ Core lista dez padrões de Views. Por decisão do responsável, oito serão instalados: **VVJA (Accordion), VVJB (Carousel), VVJF (3D FlipBox), VVJH (Hero), VVJL (Lightbox), VVJR (Reveal), VVJS (Slideshow) e VVJT (Tabs)**. **VVJC (3D Carousel) e VVJP (Parallax) ficam excluídos**. Os oito módulos estão declarados no Composer nas releases 2.x; Composer resolveu VVJ Core 2.0.2, VVJA 2.0.1, VVJB 2.0.0, VVJF/VVJH/VVJR/VVJT 2.0.1 e VVJL/VVJS 2.0.2. Drupal.org declara VVJ v2 para Drupal 11.3+/12 e PHP 8.3+ e as páginas consultadas mostram releases estáveis cobertas pela política de segurança. Revalidar isso em futuras atualizações.

| Formato | Situação ACULTA | Decisão de porte |
| --- | --- | --- |
| VVJA Accordion | Não há View de FAQ identificada; accordion de Conta é navegação e `details` nativo | Instalar, integrar tokens/estilos e deixar disponível; não converter navegação ou formulário |
| VVJB Carousel | Home e projetos usam; o palco do álbum foi migrado | Preservar Home/Projetos; não voltar a usar VVJB na sequência fotográfica |
| VVJF 3D FlipBox | Não há View atual que peça conteúdo secundário oculto | Instalar e tornar compatível com tokens; não esconder informação editorial essencial em faces que mudam por hover/clique |
| VVJH Hero | O SDC `hero` recebe título, chamada e ações de campos da página | Preservar SDC e presenter; habilitar VVJH somente para uma futura View editorial de destaques com imagem primeiro |
| VVJL Lightbox | Media Gallery usa PhotoSwipe para a experiência de imagem ampliada | Instalar; não substituir o PhotoSwipe sem comparar navegação, legenda, zoom, download, acessibilidade e ownership da galeria |
| VVJR Reveal | Não há uso atual; o conteúdo não pode depender de uma revelação animada | Instalar, manter animações opt-in e validar conteúdo visível sem JS e movimento reduzido antes de qualquer uso |
| VVJS Slideshow | `aculta_album_fotos` e `galeria_produto`, uma mídia por slide | Álbum mantém PhotoSwipe e miniaturas sincronizadas com o conjunto permitido; produto sem autoplay. Validação visual/teclado/toque pendente |
| VVJT Tabs | O SDC `product-tabs` e a navegação da Conta não são Views de linhas | Instalar; usar futuramente apenas para agrupamentos de resultados que já pertençam a uma View, nunca como navegação de rotas/formulários |

Não instalar VVJC nem VVJP. Não misturar dois motores de apresentação na mesma View. VVJ não substitui Core SDC, UI Patterns, Layout Builder, Form API ou a camada Portal. Os plugins adicionam formatos a Views; suas configurações continuam em Views e os itens renderizados continuam sujeitos às regras de acesso/cache da fonte.

Pesquisa oficial: [VVJ Core e catálogo](https://www.drupal.org/project/vvj_core); [VVJA](https://www.drupal.org/project/vvja/releases); [VVJB e release/security](https://www.drupal.org/project/vvjb); [VVJF e limitações de links/agrupamento](https://www.drupal.org/project/vvjf); [VVJH e requisitos de imagem/campos](https://www.drupal.org/project/vvjh); [VVJL e limitação de agrupamento](https://www.drupal.org/project/vvjl); [VVJR](https://www.drupal.org/project/vvjr); [VVJS, API para miniaturas e redução de movimento](https://www.drupal.org/project/vvjs); [VVJT e limites de Views](https://www.drupal.org/project/vvjt). Releases consultadas declaram estabilidade/cobertura de segurança; efeitos reais e markup precisam ser verificados no Core instalado e no navegador.

Versões e compatibilidade acima foram conferidas em Drupal.org em 2026-10-18; repetir a conferência no commit de cada dependência porque releases e avisos mudam.

# THEME-UX 0.8 — M0-S: auditoria estrutural verificável e decisões de migração

**Data:** 2026-10-22 · **Escopo:** leitura dos arquivos committed na branch `docs/aculta-forum-0.1.0-dev.1`; sem acesso ao Runtime.
**Regra:** somente reutilização transversal de componentes SDC alinhados ao UI Suite, após teste real dos consumidores.

## Resultados confirmados
| Item | Evidência no código | Decisão |
| --- | --- | --- |
| 38 metadados `.component.yml`, Twig correspondentes e 58 stories após M1 | árvore da branch | **Base SDC verificada**; não confundir com renderização aprovada |
| QR Preview e QR Download | `content/qr-preview/*.component.yml`, `content/qr-download/*.component.yml` usavam URL `project/sdc` legada | ajustada declaração `$schema` para schema Core SDC, **sem alterar props, templates nem renderização**; validar no Drupal |
| `content-section` | metadata `title` e `heading` e Twig gera `h2`/`h3` | **não reutilizar como Context Header de página H1**; avaliar novo SDC ou composição distinta |
| `product-tabs` | Twig utiliza `role=tablist`, botões e `data-bs-toggle=tab` | abas **dentro da página de produto**, não substituto semântico de links de navegação entre páginas ou AJAX Studio |
| `course_card` | `replaces: 'lms:course_card'`, slot `start_link`, props do LMS | preservar contrato; não converter cegamente a View para UI Patterns |
| `card` | `status: stable`, 3 variantes e 4 slots | candidato prioritário ao reuso após provas UI Patterns e cache |
| `forum-category` | slots renderizáveis da View Taxonomy; agora tem story | específico do Fórum; preservar dados do provider, não generalizar |
| UI Styles | `aculta420.ui_styles.yml` declara surface/text/border/elevation | catálogo de estilo estático presente; verificar uso real nos displays |
| Stories | 3 adicionadas no lote M1 | arquivo de story não equivale a validação visual do Library |

## Classificação — sem falsa certificação
- **SDC-BASE-AUDITAR:** os 38; metadados e template existentes, mas schemas e integração de cada um não verificados em Runtime.
- **SDC-PARCIAL/VERIFICAÇÃO:** QR Preview/Download (schema atualizado e stories novas, necessitam validação), Forum Category (story nova, precisa renderização), `content-section` (contrato editorial, requer solução semântica diferente para cabeçalho de página).
- **NÃO REUTILIZAR PARA NOVO PROPÓSITO:** `product-tabs` como navegação de rotas; `content-section` como H1 sem novo contrato.
- **LEGADO-MIGRAR:** templates de navegação e cabeçalhos Twig fora de SDC deverão ser mapeados por consumidor; **não rotular todo Twig que não é componente como legado**, pois o shell e templates Core legítimos não devem ser reescritos por princípio.
- **SDC-CONFORME VALIDADO EM RUNTIME:** **nenhum certificado nesta auditoria**.

## Plano de auditoria estática adicional
A inspeção integral de 38 pares metadata/Twig exige revisão sistemática dos nomes de props/slots e possíveis incompatibilidades semânticas, mas esta fase verificou especificamente QR, `content-section`, `product-tabs`, `course_card`, `card`, `forum-category` e `aculta420.ui_styles.yml`. Não atribuir a esse subconjunto o status de auditoria exaustiva. Executar validação YAML/schema e checagens automatizadas em checkout controlado antes de promover status.

## Próximo slice executável — M2/B1 Context Header
1. Obter evidências de renderização de `page_title`, `breadcrumb` e shell em GanjaLab420 e Wiki/Revista.
2. Não reaproveitar `content-section` editorial para H1; avaliar **SDC neutro específico de contexto**, com contrato de title/subtitle e slots para breadcrumb, retorno e ações; verificar componente existente que já cumpra essa função antes de criá-lo.
3. Aplicar no mínimo a dois consumidores reais com conteúdo já autorizado; Core e providers preservam dados, cache e URLs.
4. Usar UI Patterns Library para story(s), UI Styles para semântica de superfície/texto e Core SDC para contrato; o tema não gera entidade, ação, rota ou status.
5. Manter Institution Bar + Domain Header em todo o portal; cabeçalho interno é sempre abaixo do shell.
6. Homologar mobile 320/390, desktop 1280, claro/escuro, H1 único, zoom, teclado, no JS e retornos de URL.

## Gates que permanecem exclusivamente no homelab
Validadores Core/SDC Devel, UI Patterns Library renderizando os 58 stories, módulos habilitados e configurações de displays, HTML real com H1 e shell por purpose, dados/cache/ACL, testes de CSS/a11y/viewport. Nenhum PASS foi afirmado para esses itens.

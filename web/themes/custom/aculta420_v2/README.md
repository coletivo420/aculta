# ACULTA420 V2

Tema Drupal da ACULTA em **Single Directory Components (SDC)**, nativo, sem Bootstrap, sem Vite e sem Node. É o tema padrão e exclusivo do portal, desenvolvido com base no design system autônomo e mobile-first. A versão legada V1 foi purgada.

Planejamento normativo: `docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md` (ADR-054), acrescido de `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md` (ADR-055 integral, US-00 a US-14 planejado; migração visual em `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`; nomenclatura funcional no Portal RN-00 a RN-05 em `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`). A opção administrativa de container foi removida; a leitura dos valores legados permanece durante a migração paralela.

Para quem vai mexer no tema, leia nesta ordem:

1. [AGENTS.md](AGENTS.md): regras de trabalho, autorização, gates e entrega.
2. [docs/architecture.md](docs/architecture.md): camadas, fronteira com o Portal, JavaScript, tokens e o contrato de variáveis.
3. [docs/components.md](docs/components.md): catálogo dos componentes SDC, com props, slots e estado.
4. [docs/roadmap.md](docs/roadmap.md): fases, status e pendências.
5. [docs/gates.md](docs/gates.md): validadores, como rodar e o que cada um verifica.
6. [CHANGELOG.md](CHANGELOG.md): histórico de versões.

Decisões que governam o tema estão em `docs/decisions/` na raiz do repositório:

| ADR | Assunto |
| --- | --- |
| ADR-044 | CTools no tema V2 e ampliação caso a caso; AJAX e jQuery caso a caso |
| ADR-045 | Compatibilidade com Drupal 12 (desenvolvimento em 11.5) |
| ADR-046 | Desenho do tema V2: UI Styles, shell e fundações primeiro, mobile-first |
| ADR-047 | Menu de conta fixo na barra institucional |
| ADR-054 | Arquitetura SDC First, Layout Builder, dez purposes e retirada da administração de container |
| ADR-055 | UI Suite integral: UI Skins assume o visual; Portal renomeia `purpose` para `site_key` (RN-00 a RN-05), migração site-wide, Field Group dev; UI Patterns CKEditor 5 excluído |

## Estado em uma frase

Fundações (tokens, tipografia, foco, movimento e reflow), shell SDC, componentes do Fórum, Revista, Wiki, Eventos e UI Patterns estão implementados no código (`1.5.0` em `info.yml`). O tema opera atualmente com CSS nativo e paletas Skunk Family por purpose. **Planejado, ainda não executado:** UI Skins assume as paletas e o Portal substitui os identificadores funcionais por `site_key`, preservando `domain_id` e rotas.

## Estrutura

```text
aculta420_v2/
├── aculta420_v2.info.yml        metadados, região e biblioteca global
├── aculta420_v2.libraries.yml   bibliotecas: global-styling e utility-drawers
├── components/                  componentes SDC (um diretório por componente)
├── js/                          comportamentos das gavetas e diálogos utilitários
├── src/
│   ├── Hook/                    hooks em PHP (#[Hook]): página e modo de cor
│   └── styles/                  tokens.css, base.css e layout.css
├── templates/                   layouts da página, teaser e templates de mapa
├── assets/                      ícones Phosphor (vendorizados) e logotipos
├── docs/                        arquitetura, componentes, gates e roadmap
├── AGENTS.md                    regras para agentes
├── CHANGELOG.md                 histórico
└── README.md                    este arquivo
```

## Como adicionar ou alterar um componente

1. Confirme que a necessidade não é atendida por um componente existente (veja [docs/components.md](docs/components.md)).
2. Crie `components/<nome>/<nome>.component.yml` com `props` em schema estrito (`additionalProperties: false`) e `slots` documentados.
3. Crie `<nome>.twig`. Use tokens semânticos, não valores de paleta. Não leia entidade, usuário ou configuração dentro do componente.
4. Adicione `<nome>.css` (aplicado automaticamente pelo SDC) e, só se houver interação, `<nome>.js` com `Drupal.behaviors` e `core/once`.
5. Crie ao menos uma story (`<nome>.default.story.yml`).
6. Rode os gates da seção [docs/gates.md](docs/gates.md) e registre a evidência visual.
7. Documente no mesmo commit, conforme a tabela da seção 6 do [AGENTS.md](AGENTS.md): `docs/components.md`, e também `docs/architecture.md`, `docs/gates.md`, `docs/ui-styles.md` quando forem afetados. Atualize `CHANGELOG.md` e, se for entrega de fase, `docs/roadmap.md`.

Vale o mesmo para estilos de UI Styles, bibliotecas, hooks e variáveis do Portal.

## Limites que valem para qualquer mudança

- Mobile-first: a base de cada seletor atende 320 CSS px. Expansão por `min-width` ou container query. Não converter `max-width` em `min-width` de forma mecânica.
- Light e dark são o mesmo tema: o modo vem de `data-theme` no `<html>`, definido pelo Portal. Não há `prefers-color-scheme` e nenhum componente conhece o modo.
- Sem Bootstrap, `core/drupal.dialog.ajax`, Vite ou Node no tema. jQuery 4 é permitido sob demanda nos módulos e SDCs por dependência `core/jquery`, sem duplicação nem anexação global obrigatória (ADR-054).
- Sem hostnames, domínios ou regras de purpose dentro do tema. O tema recebe o contrato neutro preparado pelo Portal.
- Sem segredos, credenciais ou caminhos de ambiente no repositório.
- Institution Bar e Domain Header são contratos do shell (ADR-046): não se redesenham sem decisão explícita.

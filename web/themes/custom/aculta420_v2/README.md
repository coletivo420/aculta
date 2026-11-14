# ACULTA420 V2

Tema Drupal da ACULTA em **Single Directory Components (SDC)**, nativo, sem Bootstrap, sem Vite e sem Node. É o tema padrão e exclusivo do portal, desenvolvido com base no design system autônomo e mobile-first. A versão legada V1 foi purgada.

Decisões normativas: [ADR-054](../../../../docs/decisions/ADR-054-refatoracao-aculta420-v2-sdc-layout-builder.md) define SDC/Layout Builder; [ADR-055](../../../../docs/decisions/ADR-055-integracao-ui-icons-ui-styles-page-e-sistema-unificado-de-emojis.md) cobre UI Suite; [ADR-056](../../../../docs/decisions/ADR-056-ui-patterns-2-recomendacoes-e-corte-documentacao.md) determina reconciliação documental e corte de apresentação orientado pelos consumidores do código. Planos detalhados anteriores ficam em `docs/architecture/` na raiz do repositório e devem ser lidos como planos/status históricos até revisão contra o código atual.

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
| ADR-056 | Recomendações UI Patterns 2, documentação reconciliada com o código e corte por consumidores comprovados |

## Estado em uma frase

O código e o Config Sync contêm SDCs, UI Patterns e UI Styles; o uso efetivo por consumidor ainda precisa ser cruzado com displays e Runtime. O shell tem cinco regiões e um template. ADR-056 mantém corte orientado por consumidores: 139 SDCs/221 stories, todos os SDCs com story, e 47 templates Twig restantes no tema após migrar carrinho, aviso de pagamento e gatilho de busca para SDCs. A descoberta no Runtime, cobertura visual/autenticada, igualdade de Config Sync e lacunas de migração continuam pendentes quando não há evidência atual.

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

1. Confirme que a necessidade não é atendida por um SDC existente (veja [docs/components.md](docs/components.md)); leia a [skill SDC](../../../../.agents/skills/sdc/SKILL.md) e, para integração editorial, a [skill UI Patterns](../../../../.agents/skills/ui-patterns/SKILL.md).
2. Crie `components/<nome>/<nome>.component.yml` com `props` em schema estrito (`additionalProperties: false`) e `slots` documentados; crie `<nome>.twig` e assets locais apenas quando necessários.
3. Crie pelo menos uma story `<nome>.<variante>.story.yml` na mesma entrega (use `default` quando existir um estado padrão real). A story é a documentação/exemplo do componente na UI Patterns Library; não crie catálogo ou registro paralelo.
4. Use tokens semânticos, não valores de paleta. Não leia entidade, usuário ou configuração dentro do componente. Props recebem valores simples tipados; slots recebem conteúdo renderizável.
5. Para expor ou compor o SDC, selecione a integração UI Patterns compatível e já instalada/habilitada. Preserve acesso, render arrays e metadados de cache nos módulos e plugins owner.
6. Rode `php scripts/validate-aculta420-sdc-library-contract.php`, verifique descoberta do SDC/story na UI Patterns Library e rode os demais gates de [docs/gates.md](docs/gates.md); registre como `DEFERRED` a validação Runtime que não executar.
7. Documente no mesmo commit, conforme a seção 6 do [AGENTS.md](AGENTS.md), `docs/components.md` e outros contratos afetados. Atualize `CHANGELOG.md` e roadmap/TODO/status quando a fase ou marco mudar.

Vale o mesmo para estilos de UI Styles, bibliotecas, hooks e variáveis do Portal.

## Limites que valem para qualquer mudança

- Mobile-first: a base de cada seletor atende 320 CSS px. Expansão por `min-width` ou container query. Não converter `max-width` em `min-width` de forma mecânica.
- Light e dark são o mesmo tema: o modo vem de `data-theme` no `<html>`, definido pelo Portal. Não há `prefers-color-scheme` e nenhum componente conhece o modo.
- Sem Bootstrap, `core/drupal.dialog.ajax`, Vite ou Node no tema. jQuery 4 é permitido sob demanda nos módulos e SDCs por dependência `core/jquery`, sem duplicação nem anexação global obrigatória (ADR-054).
- Sem hostnames, domínios ou regras de purpose dentro do tema. O tema recebe o contrato neutro preparado pelo Portal.
- Sem segredos, credenciais ou caminhos de ambiente no repositório.
- Institution Bar e Domain Header são contratos do shell (ADR-046): não se redesenham sem decisão explícita.

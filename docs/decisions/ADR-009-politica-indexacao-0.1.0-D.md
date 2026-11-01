# Política de indexação multidomínio (microfase 0.1.0-D)

Data: 2026-10-09. Status: **aceita pelo responsável como política da linha 0.1.x.**
Decisão de arquitetura: adaptador mínimo em `aculta_portal_sitemap` (aprovado), no lugar
do `domain_simple_sitemap` (não adotado, ver ADR-009 e a avaliação da microfase C).
Esta microfase não contém código.

## Fontes de verdade reutilizadas (sem nova lista de hostnames)

| Necessidade | Serviço existente do Portal | Observação |
| --- | --- | --- |
| Purpose de um domínio | `DomainPurposeManager::getPurposeForDomainId()` | Autoridade de purposes. |
| Domínio de um purpose | `DomainPurposeManager::getDomain()` | Usado para o host da URL. |
| URL canônica de conteúdo | `DomainPurposeManager` e ACULTA Deployer | Host do ambiente declarado pelo ACULTA Deployer; host de teste em `test`, host de produção em `production`. |
| Purpose de origem do conteúdo | `field_domain_source` + `getPurposeForDomainId()` | Mesmo critério de `PortalHooks` e `ContentPurposeResolver`. |

O mapa `DOMAIN_IDS` em `DomainPurposeManager` é a única lista de domínios existente;
o submódulo não cria outra.

## Regras de inclusão (todas obrigatórias)

- **R1 — Origem.** Um conteúdo só é indexável no sitemap do purpose indicado por seu
  `field_domain_source`. Conteúdo sem origem válida não é indexado.
- **R2 — Unicidade.** Cada URL canônica aparece em um único sitemap: o do seu purpose de
  origem. Não há cópia entre domínios.
- **R3 — Publicação.** Somente nós publicados (`status = 1`).
- **R4 — Acesso anônimo.** Somente conteúdo que um usuário anônimo pode visualizar.
- **R5 — Noindex.** Conteúdo com diretiva `noindex` (metatag) é excluído. A verificação
  de metatag é feita na microfase E, quando o padrão de metatag do projeto estiver confirmado.
- **R6 — Tipos.** Somente os bundles listados na matriz da fase E (`page`, `project`,
  `activity`, `document`, `wiki_entry`, `article`). `editorial_highlight` não é página.
- **R7 — Hosts por ambiente.** O ACULTA Deployer é a fonte do mapeamento de domínios. Em
  `test`, páginas e sitemaps usam os hosts de teste; todos os hosts do ambiente de teste
  permanecem `noindex`. Em `production`, URLs canônicas usam os hosts de produção.

## Matriz por purpose (política; a implementação é das fases E e F)

| Purpose | Sitemap público | Conteúdo elegível (regra) | Estado no runtime |
| --- | --- | --- | --- |
| MAIN | Sim | `page`, `project`, `activity`, `document` com origem MAIN | 9 páginas, 7 projetos, 2 atividades e 1 documento com origem MAIN; apenas publicados entram |
| SUPPORT | Sim | Conteúdo público de apoio (nenhum na origem atual) | Vazio: a página de apoio é rota, não nó |
| MAGAZINE | Sim | `page` com origem MAGAZINE | 1 página publicada |
| WIKI | Sim | `wiki_entry` com origem WIKI | 4 verbetes publicados |
| COURSES | Sim | Catálogo público de cursos (fora do conteúdo Drupal atual) | Sem nós |
| SHOP | Sim | Páginas e produtos públicos | Sem nós |
| ACCOUNT | **Não** | Privacidade e autenticação: nunca | — |
| FORUM | Futuro | Somente após ativação e decisão específica | — |

## Exclusões observadas no runtime

- `activity` (2) e `document` (1) de MAIN estão **não publicados** e não entram no sitemap (R3).
- `wiki_entry` sem `field_domain_source` (1) não é indexado (R1).
- `editorial_highlight` (MAGAZINE e MAIN) não é elegível (R6).

## Consequências para a implementação

- O adaptador (fase E) precisa filtrar por `field_domain_source` **antes** de gerar URLs.
  Sem esse filtro o problema da avaliação C reaparece.
- O sitemap institucional `default` continua como está até a fase G (ADR-009, decisão 4).
- A verificação de metatag `noindex` (R5) fica para a fase E, porque o módulo `metatag` tem
  padrões por entidade que precisam ser confirmados no runtime antes da regra.

## Próxima microfase

0.1.0-E: sitemaps piloto para MAIN e SUPPORT, usando o adaptador mínimo. Não iniciada.

## Confirmação do padrão noindex (R5)

Verificação somente leitura feita em 2026-10-09 no runtime e no servidor de testes.

### Padrão existente no projeto

| Mecanismo | Onde | Quando dispara |
| --- | --- | --- |
| `<meta name="robots" content="noindex, nofollow">` via tags de metatag | `PortalHooks` (linhas 539 e 542) | Rotas `aculta_portal.*` (exceto o formulário de apoio) e rotas transacionais de doação e checkout. |
| `X-Robots-Tag: noindex, nofollow` | `DomainPurposeRequestSubscriber` (redirecionamentos e 404 de purpose errado) e `AccountRouteSubscriber` (escrita fora da conta) | Respostas que não devem ser indexadas. |

### Metatag dos nós (campo `field_meta_tags`)

- Configurações padrão `global`, `node` e `front` do módulo `metatag`: **nenhuma diretiva `robots` ou `noindex`**.
- Os 12 registros de `node__field_meta_tags` no runtime: **nenhuma diretiva `robots` ou `noindex`**.
- Conclusão: hoje não há nó com `noindex`. A regra R5 precisa ser verificada no código do sitemap, para o caso de algum nó receber a diretiva no futuro.

### Achado crítico: noindex global no servidor de testes

- Todas as páginas testadas (`/contato`, `/institucional`, `/projetos/carnareggae-bloco-sativa`
  e as páginas do apoio) respondem `X-Robots-Tag: noindex, nofollow, noarchive`.
- A origem é o VirtualHost do Apache do servidor de testes (`aculta.toca.net.br.conf`, linhas 3 e 95,
  e `toca.net.br.conf`, linhas 42 e 87). A configuração não está no repositório.
- Isso é regra do Homelab (`docs/architecture/environments.md`). **Se o cabeçalho for copiado
  para produção, o site inteiro deixa de ser indexado.**
- Registrado como **DEP-0003** (bloqueante) no registro do `aculta_deployer`.

### Regra R5 definitiva

- **R5a — Nó:** excluir do sitemap o nó cujo `field_meta_tags` contenha `robots` com `noindex`.
  A verificação é feita pelo adaptador (fase E), antes de gerar a URL.
- **R5b — Rotas do Portal:** nenhuma rota `aculta_portal.*` entra no sitemap. Essas rotas não são
  entidades e não são listadas pelo gerador.
- **R5c — Ambiente:** o sitemap de produção só é válido se o cabeçalho `X-Robots-Tag` de
  noindex estiver ausente no VirtualHost de produção (DEP-0003). O teste de sitemap no servidor
  de testes não representa indexação real.

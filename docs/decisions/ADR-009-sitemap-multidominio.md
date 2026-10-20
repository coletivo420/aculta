# ADR-009 — Sitemap multidomínio (linha 0.1.x)

Status: **aceito como contrato da microfase 0.1.0-A.** Nenhum módulo foi instalado
ou habilitado. Implementação nas microfases B a J.

## Contexto

A plataforma tem seis purposes públicos (MAIN, SUPPORT, MAGAZINE, WIKI, COURSES, SHOP)
e um sitemap institucional único, gerado pelo `simple_sitemap` na variante `default`.
A variante `index` existe, mas está desativada. O Portal não tem código próprio de sitemap.

A validação de navegador atual restringe todas as URLs a `aculta.org`. Essa regra
não cobre os subdomínios e precisa ser substituída, não apenas ampliada.

## Decisão

1. **Submódulo de integração, não um mecanismo novo.** `aculta_portal_sitemap` será
   opt-in, dependente de `aculta_portal`, `simple_sitemap` e `domain`. O Portal principal
   não depende dele.
2. **Reutilizar o contrib.** A geração fica com `simple_sitemap` 4.x. O código do
   submódulo só cobre o que a arquitetura do ACULTA exige (política de purposes,
   índice central, exclusões).
3. **Não criar lista de hostnames.** Domínios e purposes são resolvidos pelo
   `DomainPurposeManager` do Portal. Nenhum módulo mantém outra fonte de verdade.
4. **Endpoint institucional estável.** O sitemap institucional continua disponível
   em `/sitemaps/default/sitemap.xml` antes de qualquer promoção. `/sitemap.xml`
   só passa a apontar para o índice após a microfase G, para que o índice nunca
   aponte para si mesmo.
5. **Sem tabelas paralelas.** URLs, domínios e conteúdo não são copiados para
   armazenamento próprio.

## Matriz de responsabilidades

| Componente | Responsabilidade |
| --- | --- |
| Domain Access | Domínios e disponibilidade de conteúdo por domínio. |
| Domain Source | Domínio de origem de cada conteúdo. |
| Simple XML Sitemap 4.x | Geração, variantes, XML e indexação. |
| Domain Simple Sitemap 3.0.0-rc3 (avaliação) | Geração por domínio, se aprovada na microfase C. |
| `aculta_portal` | Política global de purposes e canonical (`DomainPurposeManager`). |
| `aculta_portal_sitemap` | Orquestração, política de indexação, índice central e regras de SEO. |
| ACULTA420 | Nenhuma alteração. |

## Contrato dos endpoints

| Endpoint | Origem | Estado no 0.1.x |
| --- | --- | --- |
| `https://aculta.org/sitemap.xml` | `simple_sitemap.sitemap_default` (variante `default`) | Preservado até a microfase G. |
| `https://aculta.org/sitemaps/{variant}/sitemap.xml` | `simple_sitemap.sitemap_variant` | Usado para as variantes por domínio. |
| `https://aculta.org/sitemaps/index/sitemap.xml` | Variante `index`, hoje desativada | Índice central, a partir da microfase G. |
| `https://{host}/sitemap.xml` por subdomínio | A confirmar nas microfases E e F | Só para domínios com política de indexação. |

Os caminhos foram confirmados em `simple_sitemap.routing.yml` (versão instalada 4.2.3).
Qualquer endpoint novo precisa de teste de precedência de rotas antes de ser publicado.

## Política de indexação (matriz inicial, detalhada na microfase D)

| Purpose | Sitemap público | Regra |
| --- | --- | --- |
| MAIN | Sim | Institucional. |
| SUPPORT | Sim | Conteúdo público de apoio. |
| MAGAZINE | Sim | Publicações editoriais. |
| WIKI | Sim | Conhecimento publicado. |
| COURSES | Sim | Catálogos e cursos públicos. |
| SHOP | Sim | Páginas e produtos públicos. |
| ACCOUNT | Não | Privacidade e autenticação. |
| FORUM | Futuro | Somente após ativação e decisão específica. |

## Riscos das dependências contrib

- **`domain_simple_sitemap` 3.0.0-rc3 — avaliado na microfase C (não adotado).** O filtro por domínio do gerador só reconhece `domain_access` e `domain_entity`; o ACULTA usa `field_domain_source`, então não haveria separação real. Ver `ADR-009-avaliacao-domain-simple-sitemap-0.1.0-C.md`.
- **Histórico:** Release candidate. Suporta Drupal 9, 10 e 11,
  mas tem manutenção mínima e não está coberto pela política de segurança do Drupal, segundo
  a própria página do projeto. Fonte: <https://drupal.org/project/domain_simple_sitemap>.
  Antes de adotar: revisão da superfície de exposição e de issues conhecidas (microfase C).
  Se houver bloqueio relevante, o plano é um adaptador mínimo dentro do submódulo,
  não outro módulo.
- **Cache por domínio.** Há uma issue conhecida sobre a necessidade de variar o cache por
  `url.site`. Exige teste de regressão com alternância repetida de hosts (microfase J).
- **`simple_sitemap` não aceita URL absoluta de outro host em custom links.** O gerador
  valida o caminho como rota interna (`CustomUrlGenerator`). Por isso, incluir o host de
  apoio no índice exige mecanismo próprio (microfase G).
- **`xmlsitemap_domain` descartado.** Sem release estável e fora de Domain Access.

## Consequências

- A linha 0.1.x não altera o sitemap público existente antes da microfase G.
- O índice central depende de verificação dos hosts no Google Search Console. Não há
  credencial Google no Portal.
- A dívida DT-P23 (sitemap sem o host de apoio) passa a ser tratada por este ADR. A
  entrada DEP-0002 do registro do `aculta_deployer` continua bloqueante até a microfase E.

## Referências

- ADR-006 (servidores web), ADR-008 (fundação do tema).
- `docs/portal/DRUPAL-11-STANDARDS.md`, `docs/portal/DOMAIN-PRESENTATION-CONTRACT.md`.
- Inventário da microfase A: `docs/decisions/ADR-009-inventario-0.1.0-A.md`.

## Decisões posteriores

- **Microfase C (2026-10-09):** `domain_simple_sitemap` 3.0.0-rc3 não adotado. Adaptador mínimo em `aculta_portal_sitemap` aprovado pelo responsável. Ver `ADR-009-avaliacao-domain-simple-sitemap-0.1.0-C.md`.
- **Microfase D (2026-10-09):** política de indexação aceita. Ver `ADR-009-politica-indexacao-0.1.0-D.md`.

- **Registro histórico (2026-10-09):** a decisão original adiava deploy até RC geral. O fluxo foi atualizado em 2026-10-20: todos os requisitos ficam prontos antes de RC; depois há deploy/validação Alpha no servidor de desenvolvimento e, após aprovação, migração para produção. Ver [ciclo vigente](../operations/RELEASE-LIFECYCLE.md).

## Estado final da implementação (2026-10-09)

- Microfases A a J concluídas no servidor de testes; submódulo `aculta_portal_sitemap` 0.1.1.
- Índice central em `/sitemap.xml` com as variantes `main`, `support`, `wiki`, `magazine` e `courses`; `default` desabilitada.
- Endereço por ambiente: a `base_url` segue o site definido pelo `aculta_deployer` (`environment.json`).
- Decisão do responsável ainda pendente: indexar `/wiki/verbetes`.
- Nós de teste 69 e 70 removidos (2026-10-09, autorizado).
- Produção (RC): DEP-0002 (apoio cross-host) e verificação no Search Console.

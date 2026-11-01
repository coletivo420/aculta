# Avaliação do domain_simple_sitemap 3.0.0-rc3 (microfase 0.1.0-C)

Data: 2026-10-09. Método: leitura do pacote oficial (`domain_simple_sitemap-3.0.0-rc3.tar.gz`,
1448 linhas em 22 arquivos) em diretório temporário fora do repositório. Nenhuma
dependência foi adicionada: `composer.json` e `composer.lock` não foram alterados.

## Veredito

**Não adotar como está.** Há bloqueio relevante: o gerador não filtra conteúdo por domínio
na arquitetura ACULTA. A integração proposta no plano (adaptador mínimo dentro de
`aculta_portal_sitemap`) é a alternativa indicada, conforme o próprio plano (fase C).

## Achados

| # | Achado | Arquivo | Gravidade |
| --- | --- | --- | --- |
| 1 | O filtro por domínio só existe para `domain_access` (`DOMAIN_ACCESS_FIELD`) ou `domain_entity`. O ACULTA usa `field_domain_source` (módulo `domain_source`) e não tem `domain_access` habilitado. Sem filtro, todo conteúdo publicado entra no sitemap de **todos** os domínios. | `src/Plugin/simple_sitemap/UrlGenerator/DomainEntityUrlGenerator.php` | **Alta** |
| 2 | Escolha da variante por domínio: o laço sobrescreve `$variant` a cada iteração; com mais de uma variante no mesmo domínio, vence a última. Resultado não determinístico. | `src/Controller/DomainSimpleSitemapController.php` | Média |
| 3 | Uso direto de `\Drupal::service()` e `\Drupal::entityTypeManager()` dentro do gerador. O `AGENTS.md` proíbe esse padrão em código novo. | `DomainEntityUrlGenerator.php` | Média (contrib, mas não aceitável copiar) |
| 4 | Cache: o controlador adiciona o contexto `url` apenas quando a resposta é `CacheableResponse`. A issue conhecida sobre variação por `url.site` continua a exigir teste de regressão (microfase J). | `DomainSimpleSitemapController.php` | Média |
| 5 | Rota: sobrescreve o controlador de `simple_sitemap.sitemap_default` por subscriber. Acesso herdado: `_access: TRUE`, correto para sitemap público. O formulário de configuração exige `administer domains`. | `Routing/RouteSubscriber.php`, `domain_simple_sitemap.routing.yml` | Baixa |
| 6 | Sem tabelas próprias: a associação de domínio fica em configuração (`simple_sitemap_type`, third-party settings). Compatível com a regra de não criar tabelas paralelas. | `src/Entity/`, `.install` | Baixa |
| 7 | Maturidade: release candidate, manutenção mínima e fora da política de segurança do Drupal, segundo a página do projeto. | <https://drupal.org/project/domain_simple_sitemap> | **Alta** |

## Consequência para o ACULTA

- Os hosts `aculta.toca.net.br` e `apoio.aculta.toca.net.br` já devolvem o mesmo sitemap (13 URLs).
  Com o módulo como está, a separação por domínio **não aconteceria**: o problema trocaria
  de lugar, de "domínio ausente" para "todos os domínios com as mesmas URLs".
- Para funcionar, o módulo precisaria de um filtro por `field_domain_source` e pelo
  purpose correspondente. Esse filtro é exatamente o que o plano quer escrever no
  `aculta_portal_sitemap`, usando o `DomainPurposeManager` como fonte de verdade.

## Proposta de adaptador mínimo (a validar nas microfases D e E)

- Plugin próprio de gerador de URL em `aculta_portal_sitemap`, estendendo o gerador
  de entidades do `simple_sitemap` (contrib mantido), com injeção de dependências.
- Filtro por `field_domain_source` resolvido pelo `DomainPurposeManager`, sem lista
  de hostnames.
- Controlador próprio, somente se o da contrib for insuficiente, com variante escolhida
  de forma determinística e contexto de cache por host.
- Sem dependência de `domain_simple_sitemap`. Os gaps identificados não exigem outro módulo.

## Decisão pendente do responsável

1. Aprovar o adaptador mínimo em `aculta_portal_sitemap` (recomendado), **ou**
2. Adotar o `domain_simple_sitemap` 3.0.0-rc3 com patch local para o filtro por
   `field_domain_source` (não recomendado: patch em contrib e dependência em RC).

Não há implementação nesta microfase. A próxima microfase (0.1.0-D) depende desta decisão.

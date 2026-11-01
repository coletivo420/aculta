# Prontidão para RC — sitemap, descoberta e deployer

> **Snapshot histórico (2026-10-09):** os resultados abaixo registram aquela rodada e não definem o status atual. O fluxo vigente é [Dev → RC (tudo pronto) → deploy/validação Alpha → produção](RELEASE-LIFECYCLE.md). Revalidar componentes e roadmaps antes de usar qualquer status deste relatório.

Estado do projeto em 2026-10-09, medido no servidor de testes (`aculta.toca.net.br`, Drupal
11.4.8, MariaDB). Este documento registra a bateria executada, o resultado de cada item naquela rodada e os bloqueios então identificados.

## Escopo coberto por este documento

- Sitemap multidomínio (`aculta_portal_sitemap`, fases 0.1.0-A a J).
- Descoberta: `robots.txt` com diretiva `Sitemap:`, índice central em `/sitemap.xml`.
- `aculta_deployer` 0.1.5: verificação de sitemap, `robots`, caminhos privados e credenciais por ambiente.
- Ajustes do Portal (`aculta_portal` 0.2.0-dev.11) necessários ao sitemap, ao `noindex` e ao cadastro de credenciais.

Não cobre: tema ACULTA420 como release, Commerce/LMS como release, nem produção.

## Bateria executada

| # | Verificação | Comando | Resultado |
| --- | --- | --- | --- |
| 1 | Gate Drupal 11+ do Portal | `php scripts/validate-aculta-portal-drupal11.php` | PASS (382 checks) |
| 2 | Slugs públicos | `php scripts/validate-public-slugs.php` | PASS (266 rotas; 20 violações de contrib na linha de base DT-P20) |
| 2b | Raiz da conta (sem `/conta-interna`; `/minha-conta` só interno, 404 direto) | `php scripts/validate-aculta-portal-conta-root.php` | PASS (1211 checks) |
| 3 | Fundação do tema | `drush scr scripts/validate-aculta420-foundation.php` | PASS (310 checks) |
| 4 | Contrato de shell do tema | `drush scr scripts/validate-aculta420-shell-contract.php` | PASS (9 checks). Corrigido o validador: passava 2 argumentos a `ThemeHooks`, que exige 3 (`entity_type.manager`). O tema não foi alterado |
| 5 | Foundations de design (contraste) | `php scripts/validate-aculta420-design-foundations.php` | PASS |
| 6 | Schemas SDC | `php scripts/validate-aculta420-sdc-schemas.php` | PASS (7 componentes, 98 checks) |
| 7 | PHPUnit do Portal | `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/tests` | PASS (48 testes, 76 asserções) |
| 8 | Testes do submódulo de sitemap | `php web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/tests/run.php` | PASS |
| 9 | Testes do deployer | `php web/modules/custom/aculta_portal/modules/aculta_deployer/tests/run.php` | PASS (com `FAIL` esperados de DEP-0001/0002 no `verify`, ver abaixo) |
| 10 | Fronteiras do deployer | `aculta-deployer boundaries` | PASS |
| 11 | Sitemap no servidor de testes | `aculta-deployer sitemap --env=test` | PASS (índice com 5 filhos; hosts de conteúdo respondem pelos equivalentes de teste) |
| 12 | Robots no servidor de testes | `aculta-deployer robots --env=test` | PASS (noindex em todos os hosts de teste) |
| 13 | Regressão de cache `url.site` | `tests/homologacao-url-site.sh 5` | PASS (6 hosts, 5 ciclos, sem vazamento) |
| 14 | Verificadores de assets | `scripts/verify-{baque-sativa,bloco-sativa420,podplant420}-assets.py` | PASS |
| 15 | Integridade de pacote | `scripts/audit-package-integrity.py` | PASS |
| 16 | Composer | `composer validate` / `composer audit` | PASS (exit 0; sem advisories) |
| 17 | Espaços em branco | `git diff --check` | PASS |
| 18 | Lint PHP dos arquivos alterados | `php -l` | PASS |
| 19 | Drupal runtime | `drush status`, `drush updatedb:status` | PASS (bootstrap ok; nenhuma atualização de banco pendente) |

## Pendências que impedem o RC do projeto

1. **Drift de configuração (resolvido em 2026-10-09)**: novo baseline (95 arquivos de idioma e
   tradução, sem host de teste e sem credencial). Ficam fora do baseline por serem de ambiente:
   `simple_sitemap.settings` (`base_url` de teste no runtime). O módulo `smtp` e `smtp.settings` foram removidos (M2 do envio). Classificação em
   [Baseline de configuração](#baseline-de-configuração-2026-10-09).

2. **Catálogo da loja (DT-O10), parcial**: a View `aculta_shop_catalog` e o modo
   de exibição `catalog` agora estão em `config/sync`, com página `/produtos`, bloco
   CTools restrito a pager/itens por página, filtros internos de tipo/publicação e
   purpose SHOP. A permissão anônima `view commerce_product` já estava ativa no
   Runtime de teste e não foi alterada. Isso entrega a listagem configurada, mas
   não conclui ativação comercial/RC: revisar política comercial e LGPD, confirmar
   categoria/variações, checkout e gateway, validar isolamento de Domain e executar
   smoke visual/compra em MariaDB. Produtos e imagens são conteúdo, não configuração;
   vínculos de domínio e loja continuam por ambiente no `aculta_deployer`.

## Estado por componente (registro histórico de 2026-10-09)

| Componente | Versão | Status |
| --- | --- | --- |
| `aculta_portal_sitemap` | 0.1.0 (fases A a J) | Pronto para RC no servidor de testes |
| `aculta_deployer` | 0.1.7 | Pronto para RC no servidor de testes; `verify` de produção depende da decisão de entrega em produção |
| `aculta_portal` | 0.2.0-dev.11 | Sitemap, robots, `noindex` e cadastro de credenciais; release depende do RC geral |
| `aculta420` (tema) | 0.4.x | Não avaliado como release; item 1 pendente |

## Como repetir a bateria

```sh
php scripts/validate-aculta-portal-drupal11.php
php scripts/validate-public-slugs.php
php scripts/validate-aculta-portal-conta-root.php
php scripts/validate-own-module-packages.php
php vendor/drush/drush/drush.php --uri=https://aculta.toca.net.br scr scripts/validate-aculta420-foundation.php
php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/tests
php web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/tests/run.php
php web/modules/custom/aculta_portal/modules/aculta_deployer/tests/run.php
cd web/modules/custom/aculta_portal/modules/aculta_deployer && php bin/aculta-deployer sitemap --env=test && php bin/aculta-deployer robots --env=test
web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/tests/homologacao-url-site.sh 5
```

## Baseline de configuração (2026-10-09)

- Método: export do runtime para pasta temporária fora do repositório; comparação com `config/sync`
  por parser YAML (estrutura, ignorando `langcode` e `_core`).
- Classificação: 906 arquivos iguais; 63 só de `langcode`; 32 de tradução (copiados); `simple_sitemap.settings` (host de teste, fora do baseline).
- Varredura antes do commit: nenhum host de teste novo em `config/sync`; nenhum valor de credencial.
- Envio de e-mail (camada de envio E-Mails ACULTA, SMTP2GO): sem o módulo `smtp` legado. Desde 2026-10-19 o transporte do servidor de
  testes está sem credenciais: o envio autenticado falha até o responsável gravar `SMTP2GO_USERNAME` e
  `SMTP2GO_PASSWORD` (ver `docs/portal/EMAIL-TRANSPORT.md`, seção 0). Cadastrar pelo painel `/painel-administrativo/aculta/segredos`, nunca pelo Git. Em produção, a camada criptografada é provisionada pelo deployer após o deploy (fase 9).
- Não exportado: `.htaccess` e as coleções `domain.*` (não vêm do export padrão).


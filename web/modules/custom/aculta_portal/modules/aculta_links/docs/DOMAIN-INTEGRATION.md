# Integração com Domain

`links.aculta.org` é o registro `links_aculta_org`, purpose `links`. A raiz `/`
é servida por `PurposeFrontPathProcessor` usando `/redes-sociais` internamente.
O mapa de caminhos marca a entrada `links.aculta` e informa que DNS de produção
precisa confirmação.

Contrato futuro:

- Resolver host/URL pelo `DomainPurposeManager`; nenhum hostname de ambiente
  fixo em código ou configuração exportada.
- Provisionar aliases `links.aculta.*` pela política Domain/`aculta_deployer`,
  sem resolver hostnames em paralelo.
- Micropáginas e slugs usam APIs/rotas Drupal; `/pagina/{slug}` e
  `/redirecionar/{codigo}` são propostas, não rotas existentes. A administração
  fica no purpose `main` sob `/painel-administrativo/**`. Verificar palavras
  reservadas e sitemap antes de declarar caminhos.
- Resolver destinos internos via Entity URL/Route APIs e purpose canônico; não
  persistir URL absoluta ambiental.
- Cache público varia/invalida por Domain, path, idioma e entidades dependentes.
- Redirect entre purposes segue `DomainPurposeManager` e política do Portal;
  mutações no purpose errado falham fechadas.
- Sessões e cookies seguem a política multidomínio central.

O módulo declara rotas administrativas em `main` sob `/painel-administrativo/links/**`; elas permanecem inativas enquanto `aculta_links` estiver desabilitado. Não foram declaradas rotas públicas novas nem alterada a configuração do Domain nesta fase.

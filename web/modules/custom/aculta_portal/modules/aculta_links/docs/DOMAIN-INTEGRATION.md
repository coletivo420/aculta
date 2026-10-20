# Integração com Domain

`links.aculta.org` é o registro `links_aculta_org`, purpose `links`. A raiz `/`
é reescrita por `PurposeFrontPathProcessor` para `/redes-sociais` internamente.
No Runtime de teste em 2026-10-20 esse path retornou 404: não havia Node com
Domain Source `links_aculta_org` nem alias `/redes-sociais`. A recuperação deve
fornecer a landing pelo `aculta_links`, sem publicar o caminho interno direto.
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

O módulo declara rotas administrativas em `main` sob `/painel-administrativo/links/**`; estão registradas no Runtime de teste desde 2026-10-20 e mantêm as permissões Drupal/Entity Access. A existência de uma rota não concede acesso anônimo nem bypass de Domain. Produção não foi confirmada.

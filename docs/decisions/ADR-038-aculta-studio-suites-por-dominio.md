# ADR-038 - Suites do ACULTA Studio distribuídas por domínio

- **Status:** Aceita; implementação pendente.
- **Data:** 2026-10-30.
- **Decisor:** responsável pelo produto.
- **Contexto:** As experiências do Studio estão concentradas no host `conta` sob `/studio/*`, apesar de pertencerem a domínios funcionais distintos.
- **Decisão:** Marley `account:/marley`; Ganjalab `courses:/ganjalab`; WE SATIVA `wiki:/wesativa`; Green Stash `shop:/greenstash`; Culturas `eventos:/culturas`. Hosts seguem o Domain ativo por ambiente.
- **Retirada do prefixo:** `/studio` e todas as rotas públicas `/studio/*` deixam de existir após a migração e respondem 404, sem aliases/redirects. A marca ACULTA Studio e o módulo `aculta_studio` permanecem como plataforma/registry, sem hub URL sob `/studio`.
- **Conta e Perfil:** seguem independentes das Suites. A raiz `account` será uma página de entrada da Conta e não reescreve para `/studio`; `/conta-legada` continua. Rotas atuais `/studio/conta/*`, `/studio/perfil/*`, `/studio/salvos` e `/studio/conta/mercado-pago/planos` migram para caminhos próprios sem `/studio`, preservando owners e separação Conta/Perfil.
- **Ownership:** `aculta_lms`, `aculta_wiki`, `aculta_commerce` e `aculta_events` mantêm dados, formulários, ACL e workflows. `aculta_studio` compõe interfaces; não duplica seus dados ou operações.
- **Host Wiki:** alvo `wiki.aculta.org` / `wiki.aculta.toca.net.br`. Configuração atual ainda usa `wiki420.aculta.*`; mudança depende de Domain/Deployer, DNS, TLS/vhost e corte testado.
- **Fronteira:** escrita ocorre somente no purpose da Suite; host errado responde 404, inclusive POST. Navegação usa URL absoluta via `DomainPurposeManager`, sem POST cross-domain.
- **Novas Suites:** toda implementação futura escolhe domínio/purpose e caminho próprios; não criar novo `/studio/{suite}`.
- **Plano:** [contrato da plataforma](../architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md), [sitemap](../architecture/ROUTES-SITEMAP.md), [front paths](../portal/DOMAIN-FRONT-PATHS.md) e roadmap `aculta_studio`.

## Fases

1. Inventariar rotas `/studio*`, route IDs, links, callbacks, destinos de login, canonical/cache/sitemap e ownership entre módulos.
2. Definir caminhos de Conta, Perfil, Salvos e planos Mercado Pago fora de `/studio`.
3. Mover rotas/providers às cinco purposes, mantendo IDs quando possível; Ganjalab passa de `account` para `courses`.
4. Retirar reescrita da raiz `account` para `/studio` e criar sua entrada de Conta.
5. Planejar host `wiki.aculta.*` com DNS/TLS/vhost antes do corte.
6. Atualizar navegação, login/retorno, canonical, cache, metatags, sitemap, guardrails e gates.
7. Homologar os cinco hosts, propósitos certos/errados, GET/POST, anônimo/autenticado, owners, acessibilidade, mobile e URLs antigas.

Não fazer corte parcial que deixe links ou rotas graváveis em `/studio`; não marcar concluído sem testes por host e Runtime.

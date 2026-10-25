# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Mapa obrigatório de caminhos

- Antes de criar, revisar ou remover rota, alias, redirect, home de domínio,
  link público ou controle de acesso por purpose, leia
  `docs/architecture/ROUTES-SITEMAP.md` e os contratos especializados que ele
  referencia.
- Mantenha nesse mapa todos os caminhos ativos do escopo alterado, além de
  caminhos internos, administrativos, desabilitados/removidos e redirects
  relacionados. Atualize-o no mesmo commit da mudança funcional ou documental.
- Registre uma rota por linha e dê a cada uma um título ou descrição clara do
  objeto atendido. Não agrupe caminhos diferentes numa linha sem identificar
  individualmente o objeto de cada caminho.
- Registre caminhos relativos a partir de `/`; para domínios, use identificadores
  estáveis como `main.aculta`, `conta.aculta` e `loja.aculta`, nunca hostname
  específico de Homelab/produção como identificador do mapa.
- Diferencie rota declarada no código de rota efetivamente ativa no Runtime.
  Módulo desabilitado, conteúdo ausente e configuração por ambiente devem ficar
  marcados como condicionais ou desabilitados. Não invente nem afirme cobertura
  Runtime sem consultar o ambiente seguindo `docs/architecture/environments.md`.
- Ao encerrar uma tarefa que altera caminhos, confira coerência entre mapa,
  políticas de domínio/redirect, menus e links gerados. Use `/` nos exemplos de
  path e preserve os parâmetros dinâmicos entre chaves.

## URLs e slugs públicos em português (obrigatório)

- Toda URL pública navegável de conteúdo ou de ação humana usa slug em português: minúsculas, palavras separadas por hífen, sem acentos e sem IDs numéricos quando existir título estável.
- Não usar inglês nem termos técnicos de módulo, classe ou plugin no slug público (por exemplo `course`, `group`, `node`, `user`, `lesson`, `activity`). Usar `curso`, `grupo`, `licao`, `atividade`, `entrar`, `minha-conta`.
- Rotas técnicas não são navegação: callbacks, endpoints AJAX, APIs e a administração do Drupal (`/admin`) ficam como estão. Qualquer outra exceção é registrada em `docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`.
- Slug publicado é estável. Mudança de slug troca o caminho, e o caminho antigo responde 404. Esse é o comportamento padrão do projeto para endereços antigos; não se cria redirecionamento 301 de slug.
- Diretiva detalhada em `docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`. Gate: `php scripts/validate-public-slugs.php`.

## Endereço único (regra permanente)

Cada pessoa tem um único endereço para perfil, checkout, pagamento, envio e recibos. Não criar perfil de cobrança ou envio separado. Regras e implementação: `docs/portal/CHECKOUT-BILLING.md`.

## Endereços dependentes de ambiente (regra permanente)

Todo endereço que muda entre ambientes (domínio, subdomínio, URL absoluta, redirect entre hosts, `back_url` e webhook
de gateway, link em e-mail, origem CORS/trusted_host, callback OAuth) deve estar declarado no mapeamento do
aculta_deployer (`web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json`, seções `environments` e
`environment_bound`) e **nunca** fixo no código, nos templates, nos assets ou na configuração exportada.

- Como declarar e como obter o endereço no código: `web/modules/custom/aculta_portal/modules/aculta_deployer/docs/ENDERECOS-AMBIENTE.md`.
- Gate: `php scripts/validate-environment-addresses.php` falha se `aculta.org` ou `aculta.toca.net.br` aparecer fora do mapeamento.

## Indexação e sitemap (política do projeto)

- Produção permanece indexável; hosts de teste usam `noindex, nofollow, noarchive`; páginas privadas mantêm `noindex` por rota.
- Declarar e validar diferenças de ambiente pelo `aculta_deployer`; não corrigir robots/sitemaps por edição manual sem validar os hosts envolvidos.
- Merge em `main` não publica em produção. Somente o responsável decide quando o projeto será implantado.
- Política, procedimento e evidências: `docs/architecture/environments.md`, `docs/architecture/multidomain.md`, ADR-009 e `aculta_deployer`.

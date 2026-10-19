# ACULTA - Instruções para agentes

## Mapa de documentação

- [README do projeto](README.md) — entrada geral e índice de módulos/temas.
- [Mapa de caminhos](docs/architecture/ROUTES-SITEMAP.md) — referência transversal de rotas ativas, administrativas, internas, desabilitadas e redirects.
- [Índice documental](docs/README.md) — arquitetura, decisões, normas e operação.
- [Normas do Portal](docs/portal/README.md) — padrões, políticas e contratos do `aculta_portal`.
- [Módulos e integrações](docs/modules/README.md) — referências transversais Core/contrib.
- [Documentação ACULTA420](web/themes/custom/aculta420/docs/README.md) — tema e design system.
- Cada módulo/submódulo mantém seu escopo e guias na própria pasta; o README do
  componente é a entrada local. CHANGELOGs registram entregas, roadmaps registram
  planejamento e `docs/operations/DEBT-REGISTER.md` concentra pendências.

Este arquivo reúne guardrails para agentes; os documentos vinculados são a fonte
canônica dos contratos detalhados. Evite copiar versões, resultados de validação
ou status de PR para índices e READMEs.

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

## Projeto

Este repositório contém o site institucional da:

ACULTA - Associação Cultural Antiproibicionista

Domínio de produção:

aculta.org

O site é institucional e pertence a uma associação cultural antiproibicionista sem fins lucrativos.

O site deve ser profissional, acessível, seguro e rápido, representar claramente a associação e apoiar sua validação institucional pela Google for Nonprofits quando aplicável.

## Stack

- Drupal 11
- PHP 8.5
- Composer
- MariaDB nos ambientes de teste e produção
- Drush
- Git
- GitHub
- Codex CLI

Ambientes, servidores web, Runtime e caminhos operacionais: `docs/architecture/environments.md`.
O document root Drupal é `web/`.

## Ambientes

- `*.aculta.toca.net.br` é o servidor de testes (Homelab); `*.aculta.org` é produção. Os hosts por purpose estão em `docs/architecture/multidomain.md`.
- Para Drush e operações do Runtime, siga `docs/architecture/environments.md`; não presuma um caminho de checkout, necessidade de SSH ou URI padrão.
- **Antes de qualquer operação de ambiente**, confirme `hostname`, `pwd`, branch/estado Git e `php vendor/drush/drush/drush.php status`. Confirme no Drush o site URI e o banco conectados antes de executar comandos que alterem estado.
- Se `hostname` for `toca` e o Drush confirmar o Runtime `aculta_runtime` e um URI `*.aculta.toca.net.br`, trate a sessão como já estando no servidor de testes: use o checkout e o Drush locais. SSH só se justifica quando a operação necessária não estiver disponível localmente e o destino tiver sido confirmado pela documentação de ambientes.
- **Padrão de desenvolvimento funcional:** quando a sessão for confirmada como o servidor de testes, aplique mudanças funcionais no Runtime assim que o escopo estiver coerente para smoke test contínuo. Verifique URI, banco, configuração e efeitos antes de cada alteração persistente. Isso não autoriza operações destrutivas nem alterações em produção.
- Não executar `updb`, import/export de configuração, deploy ou outras operações persistentes sem primeiro inspecionar o estado, os efeitos e a possibilidade de recuperação; nunca apontar procedimentos de teste para produção.
- Se um problema pontual e verificável bloquear diretamente uma entrega autorizada, agentes podem corrigi-lo durante o desenvolvimento sem interromper a tarefa. Inspecione primeiro o impacto e a reversão, limite a correção ao bloqueador, preserve o estado recuperável quando necessário e registre a causa e o resultado no changelog/documentação pertinente. Não use essa regra para ampliar escopo, remover conteúdo ou fazer alterações destrutivas.
- Não inventar ambientes, hosts ou nomes de servidor. Use somente os definidos em `docs/architecture/environments.md` e `docs/architecture/multidomain.md`.
- Testes em `127.0.0.1` com resolução de host própria simulam o servidor de testes, mas não são o servidor de testes; registre-os como tal.

## Estrutura do desenvolvimento customizado

Tema customizado:

web/themes/custom/aculta420/

Módulos customizados:

web/modules/custom/

Configuração exportável do Drupal:

config/sync/

## Economia de tokens e roteamento de agentes

Para pesquisa, inventário e tarefas básicas verificáveis, usar preferencialmente o modelo mais econômico disponível; para DI/Views/Entity API, escalar conforme risco; para revisão formal, autenticação, segurança, acesso/cache privado, Domain, Commerce, segredos e merge, reservar modelo de maior capacidade. A seleção é manual: o utilitário não muda o modelo por conta própria.

Consultar `docs/portal/AGENT-TOKEN-ECONOMY.md`. Usar `python3 scripts/portal-agent-budget.py route research` para recomendação de tier e `python3 scripts/portal-agent-budget.py context tarefa-atual 3000` para contexto curto. Não fixar neste arquivo o identificador da fase atual. Economizar contexto sem dispensar revisão, documentação, gates ou evidências.

## Pesquisa obrigatória de melhores práticas Drupal 11+

- Antes de decidir arquitetura, API, mecanismo de roteamento, configuração por domínio, cache, segurança ou uma dependência Drupal/contrib, pesquisar na web as melhores práticas atuais para Drupal 11+ (api.drupal.org, drupal.org/docs, issues do módulo e changelog do Core/contrib).
- Não adivinhar mecanismos: se a pesquisa não confirmar, dizer que não foi confirmado e perguntar. Citar as fontes consultadas na resposta.
- Verificar o código instalado (`web/core`, `web/modules/contrib`) como fonte de verdade para a versão em uso, além da pesquisa.

## Padrão Drupal 11+ do ACULTA Portal

O módulo `web/modules/custom/aculta_portal` adota **Drupal Core 11.3+ como baseline arquitetural**.

Este padrão é obrigatório para:
- desenvolvedores humanos;
- Codex;
- ChatGPT e outros agentes;
- demais ferramentas de IA que produzam ou revisem código neste repositório.

Antes de qualquer alteração do Portal, leia `docs/portal/GUARDRAILS.md`, `docs/portal/DRUPAL-11-STANDARDS.md` e `docs/operations/DEBT-REGISTER.md`. Consulte o roadmap para planejamento e o GitHub para o escopo/status de PR; não trate fases, PRs ou prioridades antigas como estado atual. Novas fases de produto exigem decisão do responsável.

Preserve as fronteiras Core/contrib → `aculta_portal` → contrato neutro → ACULTA420.

## CTools como implementação do Portal

CTools é uma implementação suportada do projeto, não uma dependência a evitar
por padrão. Antes de criar configuração ou código para uma necessidade que ele
cobre, leia [`docs/portal/CTOOLS.md`](docs/portal/CTOOLS.md) e verifique o código
instalado e a documentação upstream. Use `ctools_views` para blocos de Views
quando for necessário configurar por colocação, por exemplo quantidade de itens
e pager; use a API de plugins/condições do CTools para integrações contextuais
compatíveis. Prefira essa implementação existente a recriar no Portal controles
equivalentes.

CTools não é um construtor geral de formulários nem substitui Views, Forum,
Form API, Webform, Entity Access, políticas de domínio ou cache. Mantenha filtros
de acesso e domínio fixos na View. Não libere `configure_filters`,
`disable_filters`, `hide_fields` ou `sort_fields` em blocos sem revisão explícita
de segurança e governança editorial. A integração documentada do Fórum
complementa a listagem nativa; não a substitui.

Regras resumidas:
- Core/contrib continuam fonte de verdade; não criar storage paralelo quando a capacidade já existir;
- nomes machine name de campos e IDs de bundles Drupal devem ter no máximo 32 caracteres; validar o comprimento antes de criar configuração ou instalar entidades, pois o Core rejeita valores maiores;
- preferir dependency injection em classes; não introduzir novos service locators `\Drupal::...` em `src/`;
- hooks runtime novos/refatorados usam OOP `#[Hook]` quando suportado pelo Core instalado; lifecycle permanece procedural quando exigido;
- tratar como deprecada no projeto qualquer prática runtime legada contrária ao padrão moderno Drupal 11+ (por exemplo `#[FormAlter]`, hook procedural migrável, novo callback global ou novo service locator), mesmo quando ainda tolerada por compatibilidade; exceções precisam de evidência da API upstream;
- EntityQuery declara `accessCheck(TRUE|FALSE)` conscientemente;
- Form API em Drupal 11.3+ prefere callbacks resolvidos pelo `CallableResolver` e serviços DI;
- subscribers usam `EventSubscriberInterface` e o tag `event_subscriber`; prioridades funcionais não mudam por estética;
- Render API, access e cacheability fazem parte do contrato funcional;
- nenhuma mudança é chamada de “Drupal 12/13 ready” sem verificar Core, change records e módulos contrib;
- execute `php scripts/validate-aculta-portal-drupal11.php` em mudanças do Portal e elimine, não expanda, a dívida técnica registrada pelo gate.

A documentação deve registrar o motivo arquitetural das regras, não apenas sua forma.

## Servidor web

Homelab usa Apache; a produção Hostinger Cloud Startup usa LiteSpeed. Detalhes e validações por ambiente estão em `docs/architecture/environments.md` e ADR-010.

- Não introduzir novos exemplos, regras ou dependências específicas de Nginx.
- Não manter compatibilidade com Nginx como requisito do projeto.
- Preservar o `web/.htaccess` fornecido pelo Drupal e validar as diretivas em ambos os ambientes.
- Diretivas próprias do ACULTA no `web/.htaccess` (MIME de `.mjs` do PDF.js) têm gate: `php scripts/validate-htaccess-directives.php`. Remover o bloco reprova o gate.
- No Homelab, validar `mod_rewrite`, `mod_headers` e a integração PHP-FPM por `proxy_fcgi`.
- Na produção, validar reescrita, headers, proteção de arquivos, processamento de `.htaccess` e handler PHP no LiteSpeed/Hostinger; não presumir acesso a VirtualHost ou `httpd.conf`.
- VirtualHosts, caminhos, usuários, certificados e configuração Virtualmin/Hostinger continuam específicos de cada ambiente.
- Referências a Nginx em relatórios antigos são históricas e não definem a arquitetura atual.

## Regras fundamentais

1. Nunca modificar arquivos do Drupal Core diretamente.

2. Nunca modificar código dentro de:

web/core/

3. Nunca modificar diretamente módulos contrib:

web/modules/contrib/

4. Nunca modificar diretamente temas contrib:

web/themes/contrib/

5. Todo desenvolvimento específico da ACULTA deve ficar preferencialmente em:

web/themes/custom/aculta420/

ou:

web/modules/custom/

6. Dependências PHP devem ser instaladas e gerenciadas pelo Composer.

7. Não adicionar vendor/ ao Git.

8. Nunca versionar:

web/sites/default/settings.php

9. Nunca inserir no código:

- senhas
- credenciais de banco
- tokens
- API keys
- segredos
- credenciais da Hostinger
- credenciais do GitHub

10. Não remover ou sobrescrever regras de segurança existentes no .gitignore sem necessidade explícita.

11. Manter compatibilidade com Drupal 11 e PHP 8.5.

12. Priorizar APIs oficiais e padrões do Drupal.

13. Evitar módulos adicionais quando a funcionalidade puder ser implementada adequadamente com Drupal Core.

14. Antes de adicionar uma dependência Composer, explicar sua necessidade.

15. Não executar alterações destrutivas no banco de dados sem autorização explícita.

16. Não apagar conteúdo existente sem autorização.

17. Fazer alterações incrementais e fáceis de revisar no Git.

## Git

Branch principal:

main

Repositório: `coletivo420/aculta` (origin pode manter URL antiga com redirect; confira `git remote -v`).

Antes de alterações significativas, verificar:

git status

Não fazer force push.

Não reescrever o histórico da branch main.

Não fazer commit de credenciais.

## Drush

No Homelab Debian, usar o Drush instalado pelo Composer:

```sh
php vendor/drush/drush/drush.php status
php vendor/drush/drush/drush.php cr
```

Em uma workstation Windows opcional, o equivalente é:

```powershell
php .\vendor\drush\drush\drush.php status
php .\vendor\drush\drush\drush.php cr
```

## Banco de teste (Kernel)

Os testes de Kernel usam o MariaDB dedicado `aculta_simpletest`, com a configuração em `secrets/simpletest-db.env`
(fora do Git). Procedimento, rotação e resultados: `docs/operations/BANCO-DE-TESTE.md`. Nunca usar o Runtime
`aculta_runtime` nem SQLite para testes; nunca imprimir o conteúdo do arquivo de segredos.

## Identidade institucional

Nome:

ACULTA - Associação Cultural Antiproibicionista

A ACULTA é uma associação cultural antiproibicionista.

O site deve comunicar de forma clara:

- identidade institucional
- finalidade da associação
- atuação cultural
- defesa dos direitos humanos
- debate público sobre políticas de drogas
- projetos e atividades
- transparência institucional
- formas de contato

Evitar linguagem que faça o site parecer uma loja, dispensário ou plataforma comercial de cannabis.

## Conteúdo institucional do site

### Home

Apresentação clara da ACULTA e de sua atuação.

### Institucional

- Quem somos
- Missão e objetivos
- Organização

### Atividades

Apresentação das atividades realizadas pela associação.

### Projetos

Projetos culturais e sociais da ACULTA.

### Notícias

Notícias, artigos e atualizações institucionais.

### Transparência

Área destinada a documentos institucionais, incluindo quando disponíveis:

- Estatuto
- CNPJ
- Atas
- Relatórios
- documentos institucionais

### Contato

Informações oficiais de contato.

### Política de Privacidade

Página com política de privacidade do site.

## Google for Nonprofits

O site deve identificar a associação e comunicar missão, atividades, projetos,
transparência e contato conforme a seção “Identidade institucional”. Não inventar
informações institucionais ou jurídicas (CNPJ, endereço, dirigentes, datas,
registros, parceiros, financiadores ou certificações). Se um dado necessário não
foi fornecido, use placeholder claramente identificado ou solicite-o. Não trate
produtos Google for Nonprofits como ativos antes de sua ativação real.

## Front-end

Tema público atual:

ACULTA420

Machine name:

aculta420

Provider funcional:

web/themes/custom/aculta420/

Não recriar provider, alias, shim ou camada de compatibilidade de tema com machine name diferente de `aculta420`.

Prioridades:

- mobile first
- responsividade
- acessibilidade
- HTML semântico
- boa performance
- CSS organizado
- JavaScript mínimo
- compatibilidade com Drupal
- SEO técnico
- facilidade de manutenção

Evitar page builders pesados.

Evitar dependências JavaScript desnecessárias.

## Direção de arte para imagens criadas por agentes

- Toda imagem criada por um agente para este projeto deve considerar a identidade da ACULTA como associação cultural canábica e antiproibicionista; quando a imagem representar a comunidade, a conquista ou a participação no Portal, a relação com cannabis, cultura, direitos ou superação da proibição deve ser reconhecível.
- Usar enquadramento cultural, comunitário, educativo e de direitos. Não representar o Portal como dispensário, loja de cannabis ou campanha de consumo.
- Antes de criar, inspecionar os assets próximos e preservar as imagens históricas ou fornecidas pelo responsável. Registrar origem, intenção e dimensões das novas artes na documentação do componente.
- Para ilustração raster nova, usar a ferramenta imagegen; redimensionar e otimizar a saída para o uso real. Preferir arte original sem texto incorporado, salvo quando o texto exato fizer parte do pedido.
- Consultar `docs/portal/IMAGE-ART-DIRECTION.md` para a paleta e a linguagem visual das conquistas.

## Imagens e arquivos de imagem (regra permanente)

- Toda imagem e todo arquivo de imagem do site passa pelo submódulo `aculta_image`
  (`web/modules/custom/aculta_portal/modules/aculta_image/`). Vale para código novo e antigo; código existente
  fora dele deve ser refatorado.
- Não usar `image_style` (renderização, referência ou configuração) fora do catálogo `ImageCatalog`. Pedir uma
  finalidade, não um estilo.
- Não criar limite de upload de imagem próprio: usar `UploadPolicy`. A exceção aprovada da conquista (aculta_game)
  fica só no formulário da conquista.
- Não processar imagem com `ImageFactory` nem excluir arquivos por fora do `aculta_image`. Imagens candidatas à limpeza do acervo seguem a quarentena; no ciclo normal, `aculta_image` pode excluir arquivo substituído/removido de entidade somente após revalidar que `file_usage` e `entity_usage` não apontam para ele. Temporários de imagem sem uso vencem segundo `system.file:temporary_maximum_age`.
- Campo novo de imagem nasce com a política do `aculta_image` em `third_party_settings`.
- Importação de qualquer origem externa (Wayback, LMS, arquivo recebido) passa pela ingestão do `aculta_image`.
- Gate: `php scripts/validate-aculta-image.php` (tolerância zero). Plano e fases: `web/modules/custom/aculta_portal/modules/aculta_image/docs/PLANO.md`.

## Segurança

Nunca expor mensagens de erro detalhadas em produção.

Nunca colocar credenciais no repositório.

Não alterar configurações de segurança sem explicar a mudança.

O ambiente local pode utilizar configurações de desenvolvimento diferentes das configurações de produção.

Credenciais de integrações devem usar Drupal Key com provider `env`; nunca
preencher segredo diretamente em config, exportar configuração com segredo,
mover credenciais para o tema ou hardcodar caminhos Homelab/Hostinger no
Portal. Adicionar credencial exige Key, nome de variável, atualização do
ACULTA Secrets Contract, gate anti-regressão e provisioning por ambiente.

## Bancos e Sistema de Estados

- Homelab usa o Runtime MariaDB `aculta_runtime`; testes Kernel usam exclusivamente `aculta_simpletest` conforme `docs/operations/BANCO-DE-TESTE.md`.
- `estados/` contém backups privados, integrais e imutáveis: não versionar, sanitizar, editar ou implantar em produção. Mudanças após restore pertencem somente ao Runtime.
- Não adicionar credenciais deliberadamente ao Runtime/Estado. `settings.local.php`, settings de ambiente e credenciais de produção ficam fora do Git.
- Código custom acessa dados pelas APIs Drupal. Portabilidade/migração de banco pertence ao projeto independente DBTNG-2, não ao Portal.

## Integrações Google e serviços externos

- Consultar `docs/integrations/GOOGLE.md` antes de qualquer integração Google.
- Não inserir Google Tag, Analytics, Search Console verification, OAuth ou Classroom diretamente em Twig/JS do tema.
- Integrações devem poder permanecer desabilitadas sem quebrar o Drupal.
- Homelab não deve enviar telemetria real por padrão.
- Google Analytics/Tag exige revisão de consentimento e não pode enviar PII.
- Search Console deve preferir verificação de domínio/DNS quando possível.
- Se houver integração com Classroom, Drupal LMS continua fonte de verdade de cursos, matrícula e progresso.
- Não solicitar scopes OAuth que não correspondam a uma feature ativa e aprovada.

## Política de domínio administrativo

- O painel único pertence ao purpose `main`. Canonicalização e exceções de rotas estão em `docs/portal/ADMIN-DOMAIN-POLICY.md`.
- Requests mutáveis administrativos, de checkout ou de pagamento no purpose errado falham fechados; não redirecionar escrita entre hosts. Requests públicos de purpose errado continuam 404.
- Redirects intencionais entre hosts usam `DomainPurposeManager` e `TrustedRedirectResponse`; não usar `Symfony RedirectResponse` cru para outro host nem listas paralelas de rotas.
- Requisições AJAX/fetch do Portal são same-origin. Não habilitar CORS para transportar sessão ou formulários entre purposes.
- Carrinho, checkout e pagamento pertencem ao purpose `main`; a seleção de valor de apoio pertence a `support`. Regras e exceções vigentes: `docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md` e `docs/portal/PAYMENT-DOMAIN-POLICY.md`.
- ACULTA420 não participa da política de domínio; ele consome a apresentação preparada pelo Portal.

## Fronteira de autenticação e anti-bot

- Core/contrib implementam protocolos e desafios; `aculta_portal` mantém política e integração; ACULTA420 cuida apenas da apresentação.
- Não mover OAuth/CAPTCHA para o tema, consultar configuração de integração no tema, nem adicionar dependências ou segredos de autenticação ao tema.
- Credenciais usam Drupal Key e ambiente. Consulte `docs/integrations/AUTHENTICATION.md` e `docs/integrations/CAPTCHA.md`.

- Para detalhes do contrato neutro, Bootstrap/SDC, tokens semânticos, modos de cor e gates, consulte `docs/portal/COMPONENT-DESIGN-SYSTEM.md` e `web/themes/custom/aculta420/docs/design-system.md`.
- Novos componentes usam tokens semânticos e SDC quando houver contrato reutilizável; não criam lógica de domínio no tema nem seletores de modo fora da política documentada.
- Antes de criar markup/CSS, verifique Bootstrap, SDC e componentes existentes. Não adotar uma suíte concorrente sem decisão do responsável.
- O Portal prepara dados, URLs, acesso e cache; ACULTA420 seleciona e renderiza a apresentação. O Portal não referencia componentes SDC do tema.
- Gates de tema e da fronteira Portal–tema permanecem obrigatórios conforme os documentos e scripts de validação correspondentes.

## Regra de encerramento de fase

Toda fase ou subfase concluída deve terminar em **PR próprio**.

O PR deve:

- representar uma unidade lógica clara;
- listar entregas e limites;
- registrar `RUNTIME STATUS: DEFERRED` quando houver código não testado no Homelab;
- não misturar trabalho de outra fase;
- apontar a próxima fase;
- permanecer rastreável mesmo quando for integrado imediatamente.

Não considerar uma fase encerrada apenas porque existe commit local/branch.

## Coordenação Portal, tema e documentação

A evolução do Portal e a refatoração do tema são linhas separadas.

Para tarefas do `aculta_portal`:

- seguir o fluxo de branch, PR e release em `docs/operations/RELEASES.md`; respeitar o checkout/branch aberto pelo responsável;
- não recriar `web/themes/custom/aculta/` nem qualquer provider legado/alias do tema;
- consultar `docs/portal/` antes de implementar;
- manter as fontes de verdade definidas em `docs/portal/SOURCE-OF-TRUTH.md`;
- não instalar dependência planejada antes da versão correspondente;
- não reescrever roadmap/arquitetura por iniciativa própria;
- atualizar CHANGELOG e evidência de testes junto do código implementado;
- uma alteração lógica deve virar um commit atômico;
- documentação/preparação pode ser commitada sem Runtime;
- mudança funcional só é considerada concluída/mergeável/release após os testes adequados.

Não reescrever decisões arquiteturais ou roadmaps por iniciativa própria. Ao
implementar código, atualizar o CHANGELOG e registrar as evidências de validação
junto da entrega.

O Portal usa tags `portal-vX.Y.Z`. Ver `docs/operations/RELEASES.md`.

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

## Testes interativos no navegador

Procedimento, hosts, contas e resultados ficam em `docs/portal/TESTES-NAVEGADOR.md`. Firefox é o navegador padrão; use outro quando houver limitação concreta. Antes de declarar que não há sessão gráfica, inspecione a sessão ativa. Não encerre processos de navegador, driver ou leitor de tela que não foram iniciados pela tarefa.

## Versionamento de mudanças

Antes de alterar código do Portal ou do tema, siga `web/themes/custom/aculta420/docs/versioning.md` e `docs/operations/RELEASES.md`. Portal e tema têm versões independentes; mantenha manifest e CHANGELOG correspondentes coerentes e não marque estado ainda não validado. Mudanças apenas documentais não avançam versão. Não criar tags sem pedido explícito do responsável.

## Forma de trabalho esperada do Codex

### Diretório de trabalho obrigatório

- Trabalhar sempre no checkout local do repositório que o responsável abriu para a tarefa (`cwd` do projeto), mantendo o branch atual e as alterações visíveis.
- Não criar nem usar worktrees, clones ou diretórios temporários alternativos (por exemplo, `/tmp/...`) para implementar a tarefa, salvo pedido explícito do responsável.
- Antes de alterar arquivos, confirmar `pwd`, branch e `git status` nesse checkout. Não trocar de branch nem substituir arquivos com alterações locais; preservar trabalho existente e resolver conflitos com segurança.
- Quando a tarefa exigir interação com o servidor de testes, usar os meios de acesso autorizados a esse servidor; o checkout local ou um banco local não substitui a validação no servidor.

Antes de uma alteração relevante:

1. analisar os arquivos relacionados;
2. verificar a arquitetura existente;
3. explicar resumidamente o que será alterado;
4. implementar somente o necessário;
5. verificar erros;
6. informar os arquivos modificados;
7. indicar comandos Drupal/Drush necessários após a alteração.

Não refatorar partes não relacionadas à tarefa sem necessidade.

Não criar funcionalidades que não foram solicitadas.

Quando houver dúvida arquitetural importante, perguntar antes de fazer uma alteração destrutiva.

## Papéis e hierarquia (regra permanente)

Não crie hierarquia paralela nem conceda acesso por nome de papel. Para funcionalidade nova, conceda a permissão adequada e valide por `hasPermission()`/Entity Access. A configuração e as decisões vigentes estão em `config/sync/role_inheritance.settings.yml` e `docs/portal/PAPEIS-PARTICIPACAO.md`; não duplique aqui a lista de papéis. Mudanças na hierarquia exigem avaliação de herança, atualização da decisão e execução de `php scripts/validate-role-hierarchy.php`.

## Indexação e sitemap (política do projeto)

- Produção permanece indexável; hosts de teste usam `noindex, nofollow, noarchive`; páginas privadas mantêm `noindex` por rota.
- Declarar e validar diferenças de ambiente pelo `aculta_deployer`; não corrigir robots/sitemaps por edição manual sem validar os hosts envolvidos.
- Merge em `main` não publica em produção. Somente o responsável decide quando o projeto será implantado.
- Política, procedimento e evidências: `docs/architecture/environments.md`, `docs/architecture/multidomain.md`, ADR-009 e `aculta_deployer`.

## Código no Aculta Portal, Design no ACULTA420 (regra permanente)

> **Código no Aculta Portal, Design no ACULTA420.**

- `aculta_portal` implementa função, integração, dados, access, cache e comportamento. ACULTA420 implementa apresentação. O Portal não emite classes de design nem referencia SDCs do tema; o tema não implementa rotas, serviços ou regras de negócio do Portal.
- A apresentação cruza a fronteira por dados neutros e tokens `data-portal-ui`; acesso e cache devem continuar corretos sem depender do tema. Contratos: `docs/portal/FRONTEIRA-MODULO-TEMA.md`, `docs/portal/DOMAIN-PRESENTATION-CONTRACT.md` e `docs/portal/COMPONENT-DESIGN-SYSTEM.md`.
- Antes de alterar um módulo, consulte `docs/portal/MAPA-MODULO-TEMA.md`, a documentação/código do tema e `docs/portal/TOKENS-E-ATRIBUTOS.md`. Atualize o mapa no mesmo commit quando a contraparte mudar; o gate é `php scripts/validate-module-theme-map.php`.
- Gates de fronteira e arquitetura do Portal: `php scripts/validate-module-theme-boundary.php` e `php scripts/validate-aculta-portal-drupal11.php`. O inventário de helpers e integrações aplicadas está em `docs/portal/IMPLEMENTATIONS.md`.
- Alterar `web/themes/custom/aculta420/**` exige autorização explícita na solicitação. Uma tarefa de Portal, por si só, não autoriza mudanças no tema; quando autorizadas, mantenha cada camada no seu dono e atualize o mapa.

## Compras de teste: usuário teste.comum e comprador de teste do Mercado Pago (regra permanente)

- Compras de teste usam sempre `teste.comum` (uid 5) para criar o pedido e o comprador de teste do Mercado Pago para pagar. Nunca use conta real ou a conta do responsável.
- Antes de cada rodada, execute `scripts/guard-teste-comum.sh`; em FAIL, pare. Procedimentos e dados de teste: `docs/operations/TESTE-COMUM.md`, `docs/portal/TESTES-NAVEGADOR.md` e `docs/integrations/MERCADOPAGO.md`.
- Credenciais ficam em `secrets/`/ambiente. Nunca as leia em saídas, copie ou registre. O endereço de pagador aceito pelo sandbox está documentado em `docs/operations/TESTE-COMUM.md`; não o altere sem decisão do responsável.

## Pagamentos: processados pelo Mercado Pago (regra permanente)

O pagamento é processado pelo Mercado Pago. A conta ACULTA não armazena nem exibe cartões, métodos salvos ou histórico de pagamentos pessoais. Não reintroduza essas funções. O checkout, apoio e webhooks continuam usando Commerce/Mercado Pago. Detalhes e gate: `docs/portal/CHECKOUT-BILLING.md` e `php scripts/validate-module-theme-boundary.php`.

## Ícones de interface: Bootstrap Icons por padrão (regra permanente)

- Ícones de interface usam os **Bootstrap Icons** por padrão (`<i class="bi bi-…">`, já carregados pelo bootstrap5 do tema).
- Outro pacote de ícones ou SVG de interface só com exceção registrada no próprio componente, com o motivo e a origem documentados.
- Logos de marcas de terceiros (ex.: Mercado Pago) ficam em `web/themes/custom/aculta420/assets/brands/`, com `alt` do nome da marca.

## Separação de Pessoas e Configurações > Pessoas (decisão do responsável, 2026-10-17)

- `/painel-administrativo/pessoas`: administração de usuários individuais (lista, cadastro, edição, perfis e OAuth/Social auth).
- `/painel-administrativo/configuracoes/pessoas`: ferramentas de administração de contas: papéis (`papeis`), permissões
  por papel (`permissoes`), configuração de papel (`configuracoes-de-papel`), campos de perfil (`campos-de-perfil`),
  desbloqueio de sobrecarga (`desbloqueio-sobrecarga`), opções da conta (`contas`), CAPTCHA, acordos e controle de sobrecarga.
- Só a árvore de configuração e as ferramentas listadas são traduzidas (`AdministrativeRouteSubscriber`). Caminhos antigos
  respondem 404, sem redirecionamento.

## Política de commits para agentes de IA (regra permanente)

Motivo: trabalho que ficou só no working tree, em stash ou em branch sem PR gerou
duplicidade, código obsoleto e uma base de PR errada (PRs mesclados em branches antigos,
e não na `main`). Esta regra existe para que nenhum trabalho dependa de estado local.

- **Commit antes de encerrar.** Toda alteração concluída vira commit no próprio branch
  antes do fim da sessão. Não deixar trabalho só no working tree.
- **Um commit por tarefa.** O agente trabalha no branch de trabalho já aberto pelo responsável
  e registra a tarefa em um commit atômico, sem criar ou trocar de branch por causa da tarefa.
  Se a tarefa for ampliada e passar a conter entregas independentes, separar em commits atômicos
  por tarefa antes de encerrar.
- **Publicar cada commit** no branch atual com `git push` após concluir a tarefa. Não há commit
  "pendente" depois que a sessão termina.
- **Merge só no final.** O PR é mesclado uma única vez, quando a tarefa estiver concluída e o
  responsável autorizar. Não mesclar entregas parciais nem PRs intermediários.
- **Stash não é entrega.** Não guardar trabalho em `git stash`. Stash temporário só dentro
  de um comando, e nunca como destino final.
- **Diretório de trabalho:** usar o checkout local aberto pelo responsável, conforme a seção “Diretório de trabalho obrigatório”. Worktree ou clone temporário só pode ser usado a pedido explícito do responsável.
- **Verificar a base do PR antes de mesclar.** `gh pr view <n> --json baseRefName` deve
  apontar para `main` (ou para o branch base declarado). Trocar a base antes da fusão, e
  confirmar depois com `git merge-base --is-ancestor <commit> origin/main`.
- **Mesclar só com autorização explícita** do responsável. Commit e push não autorizam fusão.
- **Não commitar** credenciais, `settings.local.php`, backups locais, `.claude/settings.local.json`
  nem conteúdo de `secrets/`. Verificar o conteúdo staged antes do commit.
- **Não reescrever histórico** de branch compartilhado (sem force push).
- **Mensagem de commit** em português, com o trailer `Co-Authored-By` do agente.
- **Encerramento:** antes de encerrar a sessão, informar o estado de `git status`, o
  branch e o commit de cada entrega. Se algo ficou fora do git, dizer o motivo.

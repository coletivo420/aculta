# Changelog — ACULTA Links

## 0.4.0-dev.19 — adiciona botão pelo editor da micropágina — 2026-10-20

- O editor da micropágina oferece ação contextual para adicionar botão a partir de destino reutilizável da mesma conta.
- O formulário revalida página, destino, proprietário e Entity Access no submit; criação mantém o mesmo proprietário e valida os constraints da entidade.
- Validação: Kernel PASS (3 testes/114 asserções; 7 depreciações upstream), PHPCS e lint; Runtime/HTTP DEFERRED por updates pendentes incluindo `aculta_lms` de outra frente.

## 0.4.0-dev.18 — resolve links internos pelo Domain de origem — 2026-10-20

- Destinos `entity:node/{id}` são carregados pelo Storage API; só nodes publicados e visíveis para anônimos entram em página/redirect públicos.
- `field_domain_source` determina o purpose; URL canônica é gerada por `DomainPurposeManager`, com dependências de cache para node, Domain, acesso e `node_list`.
- Prévia privada verifica acesso no contexto Domain de origem usando a conta atual. Outros URIs mantêm a resolução do campo Link Core.
- Fontes: [LinkItem::getUrl() Drupal 11](https://api.drupal.org/api/drupal/core%21modules%21link%21src%21Plugin%21Field%21FieldType%21LinkItem.php/function/LinkItem%3A%3AgetUrl/11.x) e documentação [Domain Source](https://www.drupal.org/project/domain).
- Validação: Unit para resolução `courses`, Kernel 2/99; execução dos gates e estado Runtime registrados em `docs/TESTING.md`.

## 0.4.0-dev.17 — protege a propriedade da composição — 2026-10-20

- Uma composição exige que item, micropágina e destino compartilhem o mesmo proprietário Drupal; a validação impede gravação de referências cruzadas.
- Entity Access também nega exibição pública de registros inconsistentes preexistentes, inclusive quando página e destino estão publicados.
- Validação: Kernel PASS (2 testes/99 asserções; 7 depreciações upstream); runtime DEFERRED pelo bloqueio externo do container Drush. PHPUnit local executado com PHP 8.4.26; alvo de projeto é 8.5.

## 0.4.0-dev.16 — agrega acessos a links curtos com retenção — 2026-10-20

- Redirects curtos GET aprovados por Entity Access e validação podem enfileirar apenas ID do destino e instante; HEAD não conta. QueueWorker agrega contagem UTC por destino/dia. Dashboard do dono mostra total móvel de 90 dias.
- Tabela local contém apenas evento, ID do destino, dia e contador. Cron remove agregados anteriores a 90 dias; eventos não incluem IP, UID do visitante, user-agent, referer, fingerprint ou cookie. Coleta começa desativada; o formulário de configuração exige validação da base legal antes de habilitar em produção.
- Configuração administrativa `/painel-administrativo/aculta/links/configuracoes` requer `administer aculta links`; a rota fica em MAIN pela política do Portal.
- Fonte Drupal: [QueueWorker API 11.x](https://api.drupal.org/api/drupal/class/QueueWorker/11.x) e [Cron/Queue API](https://api.drupal.org/api/drupal/core%21core.api.php/11.x).
- Validação: Kernel PASS (2 testes/97 asserções; 7 depreciações upstream); PHPCS, lint, YAML e gates registrados em `docs/TESTING.md`. Update 10006 e smoke Runtime DEFERRED pelo erro de container externo `entity_print.print_builder`; coleta permanece desligada até validação legal.

## 0.4.0-dev.15 — integra identidade pública opt-in — 2026-10-20

- A micropágina pode apresentar apelido e link público do proprietário mediante opção revisionável explícita, desativada por padrão. Usa `aculta_user.author_identity_resolver`, respeita o perfil e a URL canônica, e carrega tags, contextos e max-age retornados pela API.
- ACULTA420 recebe render array neutro pelo slot `owner_identity`; avatar, bio, e-mail e redes não são herdados. Update `10005` instala o campo sem alterar páginas existentes.
- Fontes Drupal: [dependências de módulo](https://www.drupal.org/docs/develop/creating-modules/let-drupal-know-about-your-module-with-an-infoyml-file), [definições de campos de entidades](https://www.drupal.org/docs/drupal-apis/entity-api/defining-and-using-content-entity-field-definitions) e [API de campos revisionáveis](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21BaseFieldDefinition.php/function/BaseFieldDefinition%3A%3AisRevisionable/11.x).
- Validação: Kernel 2 testes/87 asserções PASS (7 depreciações upstream); PHPCS Drupal, PHP lint, SDC schema 570 checks, Design Foundations 2109, slugs 385 e índice de status PASS. O gate da fronteira Portal–tema ainda reporta exclusivamente classes recém-criadas na frente LMS. HTTP/Runtime e revisão visual DEFERRED pelo bloqueio de bootstrap externo no `aculta_lms` e por não haver conteúdo de teste.

## 0.4.0-dev.14 — gera QR Codes para links curtos — 2026-10-20

- O dashboard oferece QR SVG do redirect curto; o código QR usa URL canônica HTTPS gerada por `DomainPurposeManager` e o endpoint só atende códigos de destino publicado e válido.
- A resposta não registra scans nem persiste arquivos, reutiliza `QrCodeSvgGenerator` e varia por cache tags do destino, access e Domain.
- Validação: Kernel 2 testes/85 asserções PASS (7 depreciações upstream), com rota, purpose e métodos GET/HEAD; PHPCS Drupal, PHP lint e diff-check PASS. Download em HTTP e canonical Domain DEFERRED pelo bloqueio externo de container no `aculta_lms`.

## 0.4.0-dev.13 — publica destinos por links curtos — 2026-10-20

- Destinos reutilizáveis recebem código opaco aleatório de 12 caracteres em alfabeto minúsculo/dígitos, globalmente único e gerado antes do primeiro save. Campo adicionado por update `10004`; destinos antigos recebem código ao serem salvos novamente.
- `/redirecionar/{codigo}` no purpose `links` só atende GET/HEAD e exige destino publicado, Entity Access público e validação atual do destino. Redirect 302 usa `TrustedRedirectResponse`; respostas variam por tags do destino/access. Nenhuma medição de clique foi adicionada.
- O dashboard mostra a URL curta para destinos publicados usando `DomainPurposeManager`; hostname não é concatenado no código.
- Validação: Kernel 2 testes/82 asserções PASS (7 depreciações upstream), PHPCS Drupal, PHP lint e diff-check PASS. Update `10004` e HTTP no Runtime DEFERRED pelo erro externo de container em `aculta_lms`.

## 0.4.0-dev.12 — fornece SEO nativo para micropáginas — 2026-10-20

- Integração pela API/alter hook do Metatag gera title, description, canonical Domain, Open Graph e Schema.org a partir da micropágina publicada; texto de descrição é convertido para texto simples e limitado a 300 caracteres.
- Dashboard, preview e rotas de entidade do ACULTA Links recebem `noindex, nofollow`; canonical não é emitido se `DomainPurposeManager` não resolver o purpose.
- Cache do render da página varia/invalida pelos defaults global e de entidade do Metatag.
- Validação: Kernel 2 testes/76 asserções PASS (com 5 depreciações do Metatag/Token instalado); Unit de política privada 1/4 PASS (2 avisos PHPUnit), PHPCS Drupal, PHP lint e diff-check PASS. Runtime DEFERRED pelo erro de container externo do `aculta_lms`.

## 0.4.0-dev.11 — agenda disponibilidade de itens — 2026-10-20

- Itens de micropágina agora aceitam início e fim opcionais de disponibilidade em campos revisionáveis; `system.date` define o fuso de edição e o Core armazena UTC.
- Fim anterior/igual ao início é rejeitado. Prévia privada inclui itens fora da janela; render público oculta-os e reduz max-age até o próximo limite temporal.
- Update `aculta_links_update_10003()` instala somente as duas definições de campo ausentes; valores vazios preservam a exibição atual.
- Validação: Kernel 2 testes/76 asserções PASS; Runtime update DEFERRED até preflight e confirmação da única atualização pendente.

## 0.4.0-dev.10 — disponibiliza QR Code das páginas publicadas — 2026-10-20

- O dashboard oferece download de QR Code SVG em `/pagina/{slug}/codigo-qr.svg` somente para micropáginas publicadas. A carga usa a URL canônica de `DomainPurposeManager`, sem arquivos persistidos, telemetria ou redirect.
- Composer adiciona `endroid/qr-code` 6.1.3 (MIT), `bacon/bacon-qr-code` 3.1.1 (BSD-2-Clause) e `dasprid/enum` 1.0.7 (BSD-2-Clause). A geração usa SVG, sem GD.
- Validação: QR Unit 1 teste/4 asserções; Kernel 2/71; PHPCS; Composer validate; slugs 385 e mapa módulo/tema 162 PASS. Runtime de teste após `cr` resolve a rota no purpose `links`; slug inexistente responde 404. Não havia micropáginas para testar download publicado. `cr` repetiu aviso do destino H5P não configurado. Gate de índice ainda falha por commit histórico de outra frente (`d0c3086c`); gate de endereços acusa 4 literais de `aculta_mailer` fora do escopo. Não houve criação de conteúdo nem importação de configuração.

## 0.4.0-dev.9 — apresenta título próprio nas micropáginas — 2026-10-20

- O template de micropágina solicita ao SDC que não apresente a marca institucional ACULTA e mantenha o título da página como H1 visível; a landing pública continua com a identidade institucional.
- Validação: Kernel 2 testes/71 asserções, SDC schema 568, Design Foundations 2109, Foundation 366, fronteira módulo–tema 1698, mapa módulo/tema 162 e `git diff --check` PASS. Runtime de teste: rebuild concluído, landing HTTP 200 com marca ACULTA e projetos; ainda não havia micropáginas para smoke visual. Rebuild repetiu aviso de destino H5P sem diretório. Gate de status do projeto permanece bloqueado pelos commits externos `d0c3086c` e `88745650`; interfaces responsivas/dark/teclado/leitor de tela e identidade opt-in ainda pendentes.
- Atualização documental: referência LinkStack fixada em v4.8.6 (`dca586e8…`), advisory GHSA de autorização registrado, e integração futura de SEO especificada para Metatag 2.2.0 com canonical Domain.

## 0.4.0-dev.8 — apresenta acesso à página no dashboard — 2026-10-20

- A listagem de micropáginas oferece link público quando publicada ou prévia privada quando em rascunho, resolvidos pelo Domain Links existente.
- A tabela varia por site/idioma e depende dos cache tags do Domain para evitar compartilhar host incorreto.
- Validação: PHP lint/PHPCS, Kernel 2/71, mapa de módulos/tema 162, slugs 385, sitemap e diff check PASS. Runtime de teste: controller do dashboard constrói as três listas, rota está no MAIN e acesso anônimo responde 403. A listagem foi preparada para URL/preview com cache por site, idioma e Domain; sem páginas de teste para verificar esses links visualmente. `cr` repetiu aviso de asset H5P sem diretório de destino. O gate de status-index continua falhando apenas por dois commits anteriores de outra frente (`d0c3086c`, `88745650`), sem reescrita de histórico.

## 0.4.0-dev.7 — porta presenter e hooks de LINKS para o submódulo — 2026-10-20

- Presenter de canais, botões e View; theme suggestion de Node e preprocess de projetos/footer agora pertencem ao `aculta_links`.
- Landing recebe presenter por serviço Drupal com DI explícita. O conteúdo público consome `aculta_links.social_links`; a chave antiga permanece dual-write na janela de rollback.
- Validação: Kernel 2/71, PHPCS/lint/YAML, ACULTA420 Foundation 366, mapa de módulos/tema 162 e sitemap via Drush PASS. Runtime após rebuild: landing HTTP 200 contém CTA, Instagram e projetos; hook/presenter foram resolvidos pelo container. Rebuild repetiu aviso de diretório de assets H5P. O gate global de índice/Portal está bloqueado por commits paralelos anteriores (`d0c3086c`, `88745650`) sem linha de estado do Portal; não reescrevi o histórico. Conteúdo editorial Node e produção não verificados nesta mudança.

## 0.4.0-dev.6 — transfere configuração social e formulário para o submódulo — 2026-10-20

- A rota e a aba administrativa em `/painel-administrativo/aculta/redes-sociais` agora pertencem ao `aculta_links`; o acesso continua exigindo `administer site configuration`.
- Form API e callbacks AJAX foram movidos. `aculta_links.social_links` passa a ser a chave principal; update `10002` copia os valores da chave legada sem removê-la e o formulário dual-write mantém rollback compatível.
- O presenter e os hooks de exibição continuam no Portal neste incremento; o formulário usa a chave nova e a página permanece compatível pela escrita dupla. Migração da renderização fica como etapa seguinte.
- Validação: Kernel 2 testes/71 asserções, PHPCS/lint/YAML, Portal 1015 checks, índice 26 componentes, mapa módulo/tema 162 e sitemap via Drush PASS. Update 10002 aplicado isoladamente ao Runtime de teste; ambas as configurações foram conferidas, sem updates pendentes. Smoke HTTP do formulário e validação em produção permanecem pendentes. Nenhuma importação global de configuração foi executada.

## 0.4.0-dev.5 — restaura a landing do purpose links — 2026-10-20

- A rota interna `/redes-sociais` fornece a página de raiz reescrita pelo Portal, preserva o Node editorial quando o alias aponta para página do Domain links e apresenta fallback com os botões e a View de projetos já existentes quando conteúdo/alias não existe.
- A rota direta continua submetida à política de caminho interno; não foi alterada a regra compartilhada do Portal. A resposta varia/invalida por Domain, idioma, aliases e configuração dos links.
- Validação: PHP lint/PHPCS PASS; Unit 1 teste/7 asserções PASS com 2 depreciações; Kernel 1 teste/56 asserções PASS em `aculta_simpletest`; Portal 1023, status index 26, Foundation 366, SDC 36/566, Design Foundations 2109, boundary 1698, mapa de módulos/tema 162 e mapa de rotas via Drush PASS. Slugs 386 (19 violações contrib no baseline) e endereços ambientais 3014 PASS.
- Runtime após rebuild: route provider confirmou `/redes-sociais`; HTTP da raiz 200, caminho interno 404; resposta contém marca ACULTA, botão principal e projetos. Firefox WebDriver em 390 × 844 mostrou o primeiro card da View e logo; claro/escuro completo, teclado, leitor de tela e desktop permanecem pendentes. Cache rebuild completou com aviso do diretório de assets H5P não configurado. Produção não verificada.

## 0.4.0-dev.4 — presets visuais governados — 2026-10-20

- Micropáginas armazenam `theme_preset` revisionável com opções fixas `classic`, `soft` e `outline`, renderizadas pelo SDC ACULTA420 com tokens semânticos.
- Campo incluído em instalação nova e update hook `aculta_links_update_10001()` para entidades existentes; dependência adicionada somente ao módulo Core `options`.
- Kernel: 1 teste/52 asserções PASS em MariaDB dedicada, incluindo persistência, rejeição de preset inválido e render da prévia. O update foi aplicado no Runtime de teste após confirmar zero micropáginas existentes; nenhuma entidade ou conteúdo foi alterado.
- Firefox headless em 390 × 844 CSS px confirmou o preset `soft`; a tela não apresenta projetos sem conteúdo. Os sete registros temporários de QA foram apagados após conferência. A auditoria também encontrou a landing antiga em HTTP 404: nenhum Node com Domain Source `links_aculta_org` nem alias `/redes-sociais`; restauração entra no próximo incremento.

## 0.4.0-dev.3 — prévia privada de micropágina — 2026-10-20

- Editor de composição inclui rota de prévia privada no purpose `links` (`/pagina/{slug}/previa`) para renderizar com o tema público, aceitando rascunhos e itens inativos; o controlador exige atualização da página e `max-age: 0` impede cache compartilhado.
- Kernel valida rota, acesso do proprietário, inclusão do item inativo e ausência de cache compartilhado (1 teste, 48 asserções PASS); PHPCS PASS em PHP 8.4.26.
- Rebuild controlado de cache no Runtime de teste registrou a rota no purpose `links`; HTTP anônimo para prévia de slug inexistente e no purpose `main` retornou 404. Prévia visual autenticada e produção não validadas.

## 0.4.0-dev.2 — habilitação controlada no Runtime de teste — 2026-10-20

- Habilitado `aculta_links` no Runtime Homelab após confirmar hostname `toca`, checkout/branch e banco `aculta_runtime`; criadas somente as seis tabelas de entidade/revisão do módulo, sem conteúdo existente a migrar ou remover.
- Adicionado o módulo a `config/sync/core.extension.yml`; nenhuma importação/exportação global foi executada, pois há diferenças de configuração de outras frentes.
- Drush confirmou Drupal 11.4.10, rota pública `/pagina/{slug}` no purpose `links`; HTTP ao slug inexistente retornou 404 e painel anônimo em `main` retornou 403. Produção não verificada; aparência pública com página publicada permanece pendente.
- Validação PHP 8.4.26. Não foi executado teste visual no navegador nesta subversão.

## 0.1.0-dev.1 — fundação e auditoria inicial — 2026-10-20

- Inventariada a implementação atual do purpose `links`, seus consumidores,
  configuração de conteúdo e apresentação no ACULTA420.
- Criado o submódulo documental, sem habilitá-lo e sem alterar a implementação
  atualmente servida.
- Registradas arquiteturas propostas, inventário Linky/LinkStack, matriz de
  portabilidade, integrações, segurança e pendências de licença.
- LinkStack estudado na tag `v4.8.0` (`ae6837e8c0cc2cd0e77f26952ceca97d0eebec35`);
  Linky estudado na tag `2.0.0-beta1`
  (`2d70712a854065428faf0228aef96066bd6812f2`). Nenhum código foi copiado.
- Validação desta fundação: manifesto YAML parseado pelo Symfony YAML e links
  locais Markdown verificados em 2026-10-20; diff-check PASS. Runtime:
  DEFERRED; módulo não instalado nem habilitado.
- O gate do índice revelou que sua comparação histórica tratava a primeira linha
  de um componente novo como inalterada. Uma correção do validador entrou
  acidentalmente no commit de outra frente (`fb1e678b`); a verificação posterior
  continua falhando na comparação desse commit. Histórico não foi reescrito.

## 0.2.0-dev.1 — entidades base de links e micropáginas — 2026-10-20

- Criadas as entidades revisionáveis `aculta_link`, `aculta_link_page` e
  `aculta_link_item`, separando destino, página e ordem/apresentação.
- A página inicia como rascunho; item referencia página e link por Entity
  Reference. O acesso ao item revalida a propriedade de ambos.
- Acesso de usuário fica privado; administração global exige permissão restrita.
- O módulo continua sem rotas públicas e desabilitado; a página institucional
  `links.aculta.org/` permanece sem migração nesta etapa.
- Unit de controle de acesso: 1 teste, 7 asserções; Kernel de storage/acesso:
  1 teste, 20 asserções. Ambos PASS em PHP 8.4.26. PHP 8.5 e Runtime DEFERRED.
  PHPUnit Unit emitiu aviso de diretório HTML não gravável e duas deprecações.
- A validação do mapa módulo-tema identificou também módulos concorrentes ainda
  não documentados. O registro de Links foi incluído; o gate de status permanece
  bloqueado pelo commit histórico misto e por alterações concorrentes.


## 0.2.0-dev.2 — painel administrativo e rotas de entidade — 2026-10-20

- Adicionadas rotas administrativas no purpose `main` para painel, micropáginas e destinos, incluindo formulários Core e histórico de revisão.
- Painel lista separadamente páginas e links, filtra a consulta pelo proprietário, pagina a 50 itens e confere o acesso de edição por entidade.
- Permissões de criação consolidadas em `manage own aculta links` ou `administer aculta links`; removida permissão de criação redundante.
- Sitemap e documentação indicam que as rotas estão declaradas, mas inativas enquanto o módulo estiver desabilitado. A landing pública e configuração de Domain não foram alteradas.
- Kernel: 1 teste/25 asserções PASS; Unit: 1 teste/7 asserções PASS com 2 depreciações PHPUnit; PHPCS e parse YAML PASS em PHP 8.4.26. Runtime e PHP 8.5 DEFERRED.


## 0.2.0-dev.3 — gerenciamento de itens de página — 2026-10-20

- Adicionados formulários Core e rotas CRUD/revisão para `aculta_link_item`.
- Item agora tem proprietário Drupal revisionável; seu acesso requer propriedade simultânea do item, da micropágina e do destino referenciado.
- Dashboard passou a listar itens com filtro de proprietário e permitir criação pela rota Core.
- Sitemap atualizado com todas as rotas da entidade; ainda condicionais ao módulo desabilitado.
- Kernel: 1 teste/27 asserções PASS; Unit: 1 teste/7 asserções PASS com 2 depreciações PHPUnit; PHPCS e diff-check PASS em PHP 8.4.26. PHP 8.5 e Runtime DEFERRED.


## 0.3.0-dev.1 — editor de composição — 2026-10-20

- Criado formulário administrativo de composição por micropágina, com listagem de itens autorizados, ordenação por peso e controle de publicação individual.
- O salvamento revalida a micropágina e cada item, exige correspondência da referência `page` e cria revisão por item alterado. Campo de peso continua disponível como fallback sem JavaScript.
- Rota adicionada ao sitemap como declarada e inativa enquanto o módulo estiver desabilitado.
- Kernel: 1 teste/29 asserções PASS; Unit: 1 teste/7 asserções PASS com 2 depreciações PHPUnit; PHPCS PASS em PHP 8.4.26. `validate-module-theme-map.php` PASS (162 checks); `validate-public-slugs.php` FAIL por baseline obsoleta em `aculta_user`. PHP 8.5 e Runtime DEFERRED.


## 0.4.0-dev.1 — publicação de micropáginas — 2026-10-20

- Rota pública `/pagina/{slug}` limitada ao purpose `links`, com página, item e destino publicados.
- Slugs exigem palavras ASCII minúsculas separadas por hífen e usam `UniqueField` do Core; destinos aceitam URIs Drupal `internal:/` e `entity:` simples, ou HTTPS sem credenciais na porta padrão.
- Renderização pública inclui tags das listas e entidades, contextos de host/idioma/usuário/permissões e atributos seguros nos links externos.
- ACULTA420 sobrepõe o template neutro e reutiliza o SDC `social-links-page`; revisão visual permanece pendente.
- Kernel: 1 teste/44 asserções PASS; Unit: 1 teste/7 asserções PASS com 2 depreciações PHPUnit; PHPCS PASS em PHP 8.4.26. Mapa módulo/tema (162), fronteira Portal–tema (1696), design foundations (2106), foundation ACULTA420 (366), environment addresses (3011) e public slugs (386) PASS. Drupal 11 gate PASS antes das alterações concorrentes do Portal; a execução atual fica bloqueada por alteração Portal não commitada de outra frente. Runtime funcional/visual e PHP 8.5 DEFERRED.

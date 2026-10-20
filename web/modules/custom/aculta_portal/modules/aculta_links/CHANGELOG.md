# Changelog — ACULTA Links

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

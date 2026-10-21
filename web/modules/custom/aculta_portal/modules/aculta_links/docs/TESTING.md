# Testes e critérios de migração

## Fundação 0.1.0 (histórico)

- Validar sintaxe do manifesto YAML.
- Executar `php scripts/validate-project-status-index.php`.
- Executar `git diff --check`.
- O módulo está habilitado no Runtime de teste desde 2026-10-20; a configuração versionada lista a extensão. Não importar globalmente a configuração enquanto existirem diferenças de outras frentes.

## Entidades 0.2.0

- Unit: permissão do proprietário, isolamento de outra conta e administração.
- Kernel em `aculta_simpletest`: armazenamento de destino/página/item,
  referências, rascunho padrão, acesso por proprietário e revisão do destino.
- Validar lint/PHPCS, dependências Core e gates de mapa/documentação do projeto.
- Unit do dashboard valida os links de filtro de estado, preservação de filtros independentes e reset do pager ao alterar o estado.
- Kernel em MariaDB `aculta_simpletest` consulta a lista sob Entity Access e confirma que o filtro `rascunhos` retorna somente o item não publicado e varia o cache pela query. Na mudança 0.4.0-dev.26: Unit 6/54 e Kernel 6/197 PASS; 7 depreciações de Core/Metatag/Token. PHPCS, PHP lint, Drupal 11 gate, índice de status e slugs PASS. O gate de mapa módulo-tema reporta apenas `aculta_lms_messenger` e `aculta_qrcode`, fora do escopo desta alteração.
- Runtime de teste em `toca`, após confirmar `aculta_runtime` e ausência de updates: rebuild de cache no purpose `links` PASS; raiz pública HTTP 200; dashboard com filtro anônimo HTTP 302 ao fluxo de login. `cr` avisou sobre asset H5P `assets://` sem diretório configurado. Sem importação, alterações de conteúdo ou acesso à produção.
- Em 0.4.0-dev.27, Kernel MariaDB PASS: formulário expõe texto/descrição e edição avançada; submissão persiste título/descrição, ordem e estado em revisão; título vazio não grava; item fora da página rejeita o submit sem alteração parcial (suíte 6 testes/208 asserções, 7 depreciações). Unit 6/54 e PHPCS/lint/slugs PASS. Runtime sem updates: `cr` PASS, raiz 200, rota de editor para entidade inexistente 404; aviso H5P assets permanece. A revisão autenticada móvel ainda precisa ser feita antes de RC. Drupal 11/status-index gates foram bloqueados por `aculta_statistics` não commitado de outra frente.

## Migração da landing e UI

Antes de mover a landing, testar configuração e URL, render/cache, formulário,
fallback sem JavaScript e autorização administrativa. Comparar HTTP e apresentação
no purpose `links`; conferir que `/` e o caminho interno `/redes-sociais` não
perdem conteúdo. Criar mecanismo de reversão antes de qualquer migração de dados.

Para entidades: Unit/Kernel/Functional em MariaDB dedicado conforme
`docs/operations/BANCO-DE-TESTE.md`, cobrindo ownership, revisões, unicidade,
URLs inválidas, isolamento Domain, access, cache e publicação. Runtime de teste
foi atualizado em 2026-10-20; produção permanece DEFERRED. Evidências e limites
estão registrados abaixo.

## Interface pública e segurança

- Kernel valida unicidade/gramática do slug, constraint do destino, access publicado e path/purpose declarado.
- Functional/browser valida `/pagina/{slug}` em links e 404 em outros purposes; inspeciona SDC em tamanhos móveis/desktop, claro/escuro, teclado, contraste e sem overflow.
- Cache precisa invalidar ao editar/despublicar página, item ou link e ao mudar alias de Domain.
- QR: rota de página publicada entrega SVG com URL canônica HTTPS; rascunho/inexistente retorna 404; validar headers, cache e ausência de arquivo/telemetria.

Update de entidade `aculta_links_update_10001` aplicado ao Runtime de teste em
2026-10-20 com zero micropáginas existentes; o schema recebeu o campo
revisionável `theme_preset`. Nenhum update de outro módulo foi executado.

Em 2026-10-20, Kernel passou com 1 teste/49 asserções incluindo prévia privada
do proprietário, preset persistido e `max-age: 0` (1 teste/52 asserções); o
route provider do Runtime confirmou `/pagina/{slug}/previa` no purpose `links`;
HTTP anônimo sem entidade e HTTP no purpose `main` retornaram 404. Firefox
headless a 390 × 844 CSS px apresentou o preset `soft` com HTTP 200; o cabeçalho
vazio de projetos foi removido. Os fixtures de três presets foram apagados após
verificação de identidade. Claro/escuro completo, desktop, teclado, leitor de
tela e prévia autenticada continuam pendentes.

Em 2026-10-20, landing em implementação: Unit PASS (1/7, com duas depreciações
do PHPUnit); Kernel PASS (1/56) com o banco `aculta_simpletest`. Runtime
confirmou preliminarmente raiz links HTTP 200 com ACULTA/botão/projetos e path
direto `/redes-sociais` HTTP 404 após o rebuild final; route provider confirma o
caminho interno. Firefox WebDriver (390 × 844) mostrou logo, CTA, título de
projetos e o primeiro card publicado da View. Claro/escuro completo, desktop,
teclado, leitor de tela e interação do carrossel permanecem pendentes. PHP lint
e PHPCS Drupal PASS; gates Portal,
status, SDC, design foundations e boundary PASS. Foundation exige bootstrap
Drush e não passou quando chamado como PHP standalone.

Em 2026-10-20, configuração e formulário administrativos transferidos em
incremento isolado: Unit PASS (1 teste/7 asserções; duas depreciações PHPUnit),
Kernel PASS (2 testes/71 asserções), PHPCS/lint/YAML e `git diff --check` PASS.
Update `aculta_links_update_10002` foi a única atualização pendente e foi
aplicada no Runtime de teste. A nova configuração corresponde aos valores
legados; a configuração antiga continua presente; Drush confirmou zero updates
pendentes e a rota registrada com a permissão original. Sitemap PASS. Os
comandos `updb` e `cr` repetiram aviso de asset H5P sem diretório destino
configurado; smoke HTTP autenticado do formulário ainda não foi feito.

Ainda em 2026-10-20, presenter, theme suggestion e preprocess foram movidos
para o submódulo com DI explícita. Kernel PASS (2/71), PHPCS e lint PASS;
Foundation ACULTA420 (366 checks), módulo/tema (162) e sitemap PASS. O gate
global de índice/Portal está bloqueado por dois commits paralelos anteriores
(`d0c3086c`, `88745650`) que alteraram arquivos do Portal sem atualizar a linha
do componente no README no mesmo commit; o histórico não foi reescrito. Após
rebuild, a raiz `links` respondeu HTTP 200 e continha CTA,
Instagram e projetos; o container resolveu as classes de hook/presenter. O
template editorial Node, desktop, teclado e leitores de tela não foram
verificados neste recorte; produção continua DEFERRED.

Dashboard 0.3.0-dev.2: Kernel 2/71 e PHPCS PASS. No Runtime de teste, o serviço
do dashboard gera as três listas (`pages`, `links`, `items`), a rota está
declarada no purpose `main` e o acesso anônimo responde 403. O host de links e
idioma entraram nos cache contexts da lista de micropáginas; o Domain Links é
dependência cacheável. Não havia páginas em conteúdo de teste para validar
visualmente o link público ou a prévia autenticada. `cr` completou com aviso
recorrente de asset H5P sem diretório configurado.

Agendamento 0.4.0-dev.11: Kernel PASS (2 testes/76 asserções), PHPCS Drupal,
PHP lint dos novos componentes e `git diff --check` PASS. Update `10003` e
smoke de Runtime permanecem DEFERRED: o preflight Drush falhou durante a
compilação do container por serviço ausente `entity_print.print_builder`
introduzido na alteração concorrente do `aculta_lms`; nenhuma operação Runtime
foi executada.

SEO 0.4.0-dev.12: Unit PASS (1 teste/4 asserções) para `noindex` e remoção de
canonical nos previews privados; PHPCS e lint PASS. O Kernel manteve 2/76 PASS,
com 5 depreciações provenientes do Metatag/Token e de APIs do Core acionadas
pelos contribs. A emissão de metadata em HTTP e a cache no Runtime ficam
DEFERRED pelo bloqueio do bootstrap Drush descrito acima.

Links curtos 0.4.0-dev.13: Kernel PASS (2 testes/82 asserções), cobrindo rota,
purpose, métodos GET/HEAD, geração opaca de 12 caracteres e resposta 302 para
destino HTTPS validado. PHPCS, PHP lint e `git diff --check` PASS; há sete
depreciações de APIs do Core/Metatag/Token no Kernel. Update `10004` e smoke
HTTP DEFERRED pelo mesmo erro de container externo do `aculta_lms`.

QR de link curto 0.4.0-dev.14: Kernel PASS (2 testes/85 asserções; 7
depreciações upstream) e confirma a rota, purpose `links` e métodos GET/HEAD;
PHPCS, lint e `git diff --check` PASS. Renderização do SVG e headers canônicos
em HTTP permanecem DEFERRED pelo bloqueio do container Runtime.

Identidade pública opt-in 0.4.0-dev.15: Kernel PASS (2 testes/87 asserções; 7
depreciações upstream), incluindo default desativado e prévia sem resolução da
identidade. PHPCS, lint, SDC schema (570 checks), Design Foundations (2109),
slugs (385) e índice de status PASS. O gate de fronteira falha somente pelas
classes de apresentação introduzidas em arquivos concorrentes do `aculta_lms`.
HTTP no Runtime e revisão visual/acessível permanecem DEFERRED; o bootstrap
Drush falha pelo serviço ausente `entity_print.print_builder` da alteração LMS.

Métricas agregadas 0.4.0-dev.16: Kernel PASS (2 testes/97 asserções; 7
avisos de depreciação oriundos de Core/Metatag/Token), cobrindo analytics
inativo por padrão, ativação explícita, fila só em GET, agregação diária,
exclusão de HEAD e expurgo após 90 dias. PHPCS Drupal, PHP lint, YAML e
`git diff --check` foram executados. O status index e os gates independentes de
slugs/SDC também precisam constar com resultado da execução deste incremento.
Update `10006` e smoke no Runtime DEFERRED: o preflight Drush falha ao compilar
o container por serviço ausente `entity_print.print_builder` em alteração
concorrente de `aculta_lms`; nenhuma operação persistente foi executada. Revisão
visual e validação legal da base para coleta permanecem pendentes; a coleta
segue desligada por padrão.


Integridade de propriedade 0.4.0-dev.17: Kernel PASS (2 testes/99 asserções;
7 depreciações upstream). Cobertura inclui rejeição de item que referencia
destino de outra conta e negação de view público para composição inconsistente.
Runtime e smoke HTTP DEFERRED pelo bloqueio externo do container Drush; nenhuma
atualização de banco foi executada. O PHPUnit local está em PHP 8.4.26, abaixo
do PHP 8.5 declarado como alvo do projeto.

Resolução de destino Domain 0.4.0-dev.18: Unit PASS (1 teste/18 asserções)
confirma o canonical `entity.node.canonical`, o purpose `courses`, checagem de
acesso anônimo e dependências de cache. Kernel PASS (2/99; 7 depreciações
upstream). Runtime HTTP permanece DEFERRED: `updatedb:status` lista quatro
updates pendentes de `aculta_links` (10003–10006) e um update de entidade
`aculta_lms` (10002), pertencente a outra frente; não executei `updb` nem `cr`
para não aplicar mudança de outra frente no Runtime compartilhado.

Última validação de código 0.4.0-dev.18: Unit PASS (4 testes/33 asserções;
6 depreciações upstream), Kernel PASS (2/99; 7 depreciações upstream), PHPCS
Drupal, PHP lint, 9 arquivos YAML do módulo, slug, SDC, boundary e `git diff
--check` PASS. O gate Drupal 11+/status index está bloqueado por alteração
concorrente de `aculta_qrcode` (manifesto `0.3.0-dev.1` sem atualização da linha
no README); o mapa de módulos/tema também acusa esse módulo ainda não listado.
Nenhum arquivo de outra frente foi alterado para contornar os gates.

Inclusão contextual de botão 0.4.0-dev.19: Kernel PASS (3 testes/114
asserções; 7 depreciações upstream), incluindo rota, filtragem de destinos por
proprietário e acesso. PHPCS Drupal, PHP lint e `git diff --check` PASS. Update
de banco não é necessário para esta mudança; Runtime/HTTP não foi rebuildado
enquanto há updates pendentes de `aculta_links` e `aculta_lms` no site comum.

Atalho contextual no dashboard 0.4.0-dev.20: revisão confirma que a ação usa a
rota com `_entity_access: aculta_link_page.update`; PHPCS, lint, Unit e Kernel
PASS. Não adiciona update de banco. HTTP/visual permanece DEFERRED porque as
atualizações do Runtime de teste incluem o update de `aculta_lms` da frente
concorrente.

Referência de conteúdo 0.4.0-dev.21: Unit PASS (4 testes/47 asserções) para
resolução por URI e por ID de Entity Reference. Kernel PASS (3 testes/131
asserções), incluindo destino Node salvo, validação que exige exatamente uma
origem (URL ou Node) e filtro de propriedade das opções do formulário; 7
depreciações vêm de Core/Metatag/Token. PHPCS, lint e `git diff --check` PASS.
Em 2026-10-20, preflight confirmou hostname `toca`, checkout atual e Drush
conectado ao banco de teste `aculta_runtime` (URI default esperada nesse
servidor). Antes do update foi criado dump privado verificado. `updb` aplicou
Links 10003–10007 e também LMS 10002, que estava pendente e era aditivo (novo
storage de certificados vazio); `updatedb:status` confirmou zero updates
pendentes. A operação compartilhada foi aplicada com backup e foi comunicada
como tal. `cr` completou. Smoke HTTP autenticado/publicado de destino Node
retornou 200 e gerou URL canônica no purpose de origem; página inexistente
retornou 404, dashboard anônimo 403, e a fixture temporária foi removida e
confirmada como 404. Produção continua DEFERRED. `cr` e `updb` imprimiram aviso
preexistente de cópia de asset H5P para destino `assets://` sem diretório
configurado. Inspeção visual por screenshot não foi concluída: Firefox já estava
em uso na sessão gráfica e o processo headless isolado não terminou no prazo.

Tentativa posterior de repetir o screenshot foi interrompida antes da criação
da página e do item de teste: uma edição ainda não concluída na frente LMS
removeu `AnswerEvaluationEventHooks`, ainda referenciada pelo container do
Runtime. A gravação abortada deixou apenas o destino temporário `id=5`; foi
confirmada a ausência de página/item associados e esse registro exato foi
removido junto de sua revisão em transação SQL. A consulta confirmou zero linhas
restantes. Não houve limpeza geral nem alteração de arquivos LMS. Repetir o
smoke foi possível após o hook LMS ser substituído e o cache do Runtime ser
reconstruído; nenhum arquivo LMS foi alterado por Links.

Diagnóstico do primeiro GET da fixture visual: HTTP 500 causado pela classe
inexistente `Drupal\Core\Utility\Unicode` em `LinkPageMetatagHooks`; o namespace
correto no Core 11.4.10 é `Drupal\Component\Utility\Unicode`. Em `0.4.0-dev.22`,
Unit (5/50), Kernel (3/131), PHPCS e lint passaram. Depois do `cr`, a página
temporária respondeu 200 com metadata e destino Domain corretos. Firefox ESR
via WebDriver BiDi verificou viewport CSS 390 × 844 e 1440 × 1000 sem overflow;
teclado alcançou o link com outline sólido de 3 px. Fixtures foram excluídas e
o slug retornou 404. Referência Core: [Unicode::truncate 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Component%21Utility%21Unicode.php/function/Unicode%3A%3Atruncate/11.x).

Simulação isolada do SDC em `data-bs-theme="dark"` e 390 × 844: sem overflow;
contraste computado para título, descrição e botão de 12,95:1, 9,72:1 e
14,50:1. O DOM foi alterado somente na sessão do navegador, sem trocar
configuração Runtime. Leitor de tela, troca de modo do Portal e avaliação
integral de acessibilidade permanecem pendentes. Os gates globais de status e
mapa falharam por alterações de outras frentes, listadas no changelog.

## Formulários de entidade 0.4.0-dev.24

Kernel PASS (4 testes/159 asserções; 7 depreciações Core/Metatag/Token),
incluindo presença dos campos principais nos formulários das três entidades.
Unit PASS (5/50), PHPCS Drupal/Practice, lint, YAML e `git diff --check` PASS.
Após `cr` no Runtime de teste, Firefox autenticado em viewport CSS 390 × 844
confirmou os campos das rotas de adição de micropágina, destino e item; cada
página ficou com `scrollWidth=378`. Capturas revisadas. Nenhum formulário foi
submetido, nenhum fixture foi criado e nenhuma entidade foi alterada. Edição de
destino preexistente também foi inspecionada em 390 × 844 com título, destino,
referência Node, estado e controles de revisão, `scrollWidth=378`; o formulário
foi fechado sem enviar. Edição de página/item e submissão funcional seguem sem
validação visual.

## Editor de composição em mobile — 0.4.0-dev.24

Firefox autenticado via WebDriver BiDi verificou a rota de edição de composição
em viewport CSS 390 × 844. Texto e estado do item foram renderizados; o controle
de pesos revelou o input numérico de posição que serve de fallback ao arraste.
`scrollWidth` permaneceu dentro do viewport (390 px). Nenhum formulário foi
submetido. A fixture (link ID 10, página ID 11, item ID 11) foi removida pela
Entity API depois de checar IDs, slug/título, proprietário e relações; uma
consulta posterior confirmou os três registros ausentes. Portanto o smoke não
alterou entidades preexistentes nem deixou conteúdo temporário no Runtime.

## Submissão do editor de composição — 0.4.0-dev.25

Kernel chama `LinkPageEditorForm::submitForm()` com FormState contendo uma
ordem e um estado alterados; reconsulta o storage e confirma ambos e a criação
de revisão. O caso também injeta um item pertencente a outra página do mesmo
proprietário e confirma negação (`AccessDeniedHttpException`). Execução em
MariaDB dedicado; nenhum Runtime foi alterado.

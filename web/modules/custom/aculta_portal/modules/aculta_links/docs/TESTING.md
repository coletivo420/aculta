# Testes e critérios de migração

## Fundação 0.1.0 (histórico)

- Validar sintaxe do manifesto YAML.
- Executar `php scripts/validate-project-status-index.php`.
- Executar `git diff --check`.
- O módulo está habilitado no Runtime de teste desde 2026-10-20; a configuração versionada lista a extensão. Não importar globalmente a configuração enquanto existirem diferenças de outras frentes.

## Entidades 0.2.0 em desenvolvimento

- Unit: permissão do proprietário, isolamento de outra conta e administração.
- Kernel em `aculta_simpletest`: armazenamento de destino/página/item,
  referências, rascunho padrão, acesso por proprietário e revisão do destino.
- Validar lint/PHPCS, dependências Core e gates de mapa/documentação do projeto.

## Migração da landing e UI

Antes de mover a landing, testar configuração e URL, render/cache, formulário,
fallback sem JavaScript e autorização administrativa. Comparar HTTP e apresentação
no purpose `links`; conferir que `/` e o caminho interno `/redes-sociais` não
perdem conteúdo. Criar mecanismo de reversão antes de qualquer migração de dados.

Para entidades: Unit/Kernel/Functional em MariaDB dedicado conforme
`docs/operations/BANCO-DE-TESTE.md`, cobrindo ownership, revisões, unicidade,
URLs inválidas, isolamento Domain, access, cache e publicação. Runtime é DEFERRED
para produção; evidências parciais do Runtime de teste e seus limites são
registrados abaixo.

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

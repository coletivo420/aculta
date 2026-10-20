# Changelog — aculta_pdf_viewer

## 0.1.0-dev.36 — padrões de rolagem e página dupla no PDF.js completo — 2026-10-20

- Acrescenta um wrapper local e same-origin para inicializar o viewer PDF.js com rolagem vertical/horizontal e spread configurados por painel ou formatter. O PDF.js continua sem alterações nos arquivos Composer.
- Desktop pode abrir em página dupla; em telas móveis o spread é desligado por padrão e respeita a opção explícita para mantê-lo. Canvas ACULTA segue paginado.
- Links de impressão e viewer completo passam pelo wrapper. O caminho PDF continua vindo do builder após checagem de acesso; arquivos privados continuam na rota Core. O wrapper valida origem e extensão antes de carregar.
- Unit 26/26 (183 assertions), `php -l` e `node --check` PASS. Firefox no Homelab carregou o PDF público PV7 de 14 páginas: PDF.js reportou scroll mode horizontal e spread no desktop; ao estreitar a janela para viewport efetivo de 500 px, spread mudou para desligado e a página não transbordou horizontalmente.
- RUNTIME STATUS: PARTIAL; Firefox verificou wrapper/modos no Homelab. O builder real no Runtime gerou links de impressão e viewer com o wrapper e os padrões correntes (`vertical`, spread desligado), sem título editorial na query; HTML respondeu HTTP 200 em `main.aculta`. Kernel do Portal precisa ser repetido após estabilizar as alterações concorrentes atuais; gates finais e revisão acessível seguem abertos. Permanece Dev.

## Evidência PV7 — validações retomadas — 2026-10-20

- Unit passou: 26 testes, 183 assertions (contagem confirmada em nova execução em 2026-10-20).
- Kernel no MariaDB dedicado não alcançou assertions: a compilação do container do Portal falhou por referências a `agreement.handler` e `commerce_cart.cart_provider` ausentes no fixture. O bloqueio está registrado no roadmap para resolução antes de RC.
- Status-index e Drupal 11+ gate passaram após atualizar a linha do componente no índice raiz: 24 componentes e 1026 checks. `aculta_image`, fronteira módulo/tema, endereços de ambiente, slugs públicos e `.htaccess` passaram.
- No Homelab, a rota administrativa foi carregada após `drush cr`; a configuração ausente foi inicializada somente com o objeto de Config Sync `aculta_pdf_viewer.settings`. Firefox autenticado confirmou a subaba Site → Leitor de PDF e os controles padrão ligados (10 opções); página dupla móvel e desktop permanecem desligadas. O rebuild registrou aviso preexistente de diretório de asset H5P, mas concluiu.
- Sem mudança de código nesta nota; na ocasião, a versão era `0.1.0-dev.35`. PV7 não estava em RC. O modo de rolagem/página dupla foi concluído para o viewer oficial no dev.36; Kernel/gates finais permanecem abertos.

## 0.1.0-dev.35 — subaba Leitor de PDF em Site — 2026-10-20

- Integra o formulário global como subaba “Leitor de PDF” dentro da aba “Site” do painel ACULTA, mantendo o caminho e a permissão administrativa existentes.
- RUNTIME STATUS: DEFERRED; confirmar descoberta da aba no Runtime Alpha junto ao fluxo autorizado de implantação.

## 0.1.0-dev.34 — controles globais no painel ACULTA — 2026-10-20

- Adiciona `/painel-administrativo/aculta/leitor-pdf` como subpágina do painel ACULTA, protegida por `administer site configuration`.
- O painel salva padrões globais tipados/exportáveis; cada formatter herda esses valores por padrão ou pode desativar a herança e configurar aquele campo. A cache do viewer varia pela configuração global.
- Rolagem horizontal e página dupla ficam visíveis com descrição explícita de que ainda não afetam o canvas; leitura em voz alta continua pós-deploy. Runtime e formulário visual DEFERRED.

## 0.1.0-dev.33 — miniaturas de páginas sob demanda — 2026-10-20

- Inclui miniaturas navegáveis no canvas próprio. Só renderiza ao expandir a seção, limita cada miniatura a 65.536 pixels e a lista a 100 páginas; PDFs maiores informam o limite aplicado.
- Usa controles Bootstrap e texto acessível; miniatura sem renderização é removida sem interromper o leitor. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.32 — corrigir escala inicial configurada — 2026-10-20

- Distingue “ajustar à largura” de escala real 100%/125%; restaurar zoom volta ao padrão selecionado no formatter.
- Zoom em tela estreita respeita o limite seguro de canvas já existente. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.31 — navegação pelo contorno do PDF — 2026-10-20

- Exibe o outline fornecido pelo PDF.js em lista hierárquica de controles acessíveis, navegando para a página de cada destino. Documento sem outline não exibe a área vazia.
- Limita a construção a 500 itens e 20 níveis; títulos são inseridos como texto, sem interpretar HTML. Miniaturas por página continuam pendentes. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.30 — rotação do canvas — 2026-10-20

- Acrescenta controle opcional de rotação no formatter; o canvas gira em passos de 90° e recalcula a escala respeitando o limite de pixels e dimensões.
- Não altera o arquivo PDF nem persiste rotação no documento. RUNTIME STATUS: DEFERRED; testes e validação visual pré-RC pendentes.

## 0.1.0-dev.29 — opções do leitor no formatter — 2026-10-20

- Os formatters Media e File passam a oferecer configuração Drupal por campo para controles, zoom inicial, rolagem, página dupla, idioma e apresentação do link, sem string posicional compactada.
- Builder recebe as opções e oculta controles conforme a configuração. Download e impressão continuam disponíveis apenas quando o formatter já confirmou `File::access('download')`; impressão abre o viewer completo local do PDF.js, cujo botão de impressão processa o documento.
- Zoom inicial é aplicado ao canvas próprio; idioma pt-BR é propagado para o viewer completo. A leitura custom ainda não implementa modo horizontal nem página dupla: as opções correspondentes se aplicam ao leitor completo até concluir o adapter.
- Nenhum arquivo de TNC FlipBook estava disponível neste checkout; a análise de capacidades foi feita pela matriz enviada e pelas referências upstream já registradas no roadmap. A comparação detalhada, validação funcional do botão de impressão e inspeção do protótipo seguem pendentes. RUNTIME STATUS: DEFERRED.
- Decisão do responsável: leitura em voz alta pelo navegador fica para pós-deploy; antes de implementação, avaliar suporte no ambiente de produção e testar com leitor de tela.

## 0.1.0-dev.28 — link alternativo ao viewer completo PDF.js — 2026-10-20

- O formatter oferece link secundário para o viewer oficial `web/viewer.html`, com locale `pt-BR`; o canvas custom permanece a experiência padrão.
- A URL do documento vem do builder após validar acesso de Media/campo/File. PDFs privados continuam apontando à rota protegida do Drupal Core; nenhum controller ou cópia de arquivo foi criado.
- O mapa transversal de caminhos registra o viewer na mesma origem para MAIN, Revista, Wiki, Fórum e Cursos.
- Homelab: HTTP GET de `viewer.html` respondeu 200 em MAIN, Revista, Wiki, Fórum e Cursos; `viewer.mjs` e locale `pt-BR/viewer.ftl` também responderam 200. Isso confirma apenas a entrega estática; ainda validar abertura do PDF pelo parâmetro `file`, foco/teclado, leitor de tela, PDFs marcados/não marcados e CSP Report-Only. RUNTIME STATUS: DEFERRED para o fluxo funcional.

## Documentação PV7 — validar traduções locais, text layer e decisão CSP — 2026-10-20

- Arquivos gettext pt-BR disponíveis foram conferidos pelo parser do Drupal Core: Core e 12 PO contrib não têm entradas traduzíveis com `msgstr` vazio; o pacote PDF.js também contém `web/locale/pt-BR/viewer.ftl`. A contagem não representa cobertura integral dos módulos instalados.
- A investigação do PDF.js 6.4.299 recomenda prototipar o viewer completo oficial antes de criar uma camada custom: o `TextLayer` isolado não contém toda a integração de foco/acessibilidade/estrutura do viewer. Nenhuma camada foi implementada nem considerada prova de conformidade WCAG.
- Decisão do responsável: manter CSP em Report-Only, enforcement desligado, e `dblog.settings:row_limit` atual de 1000 registros. DBLog usa limite por quantidade, não retenção por prazo, e armazena URL/User-Agent; rever acesso e retenção antes da produção.
- Acessibilidade ampliada, testes do protótipo com PDFs marcados/não marcados e leitores de tela, e demais gates PV7 continuam pendentes; o módulo permanece Dev.

## 0.1.0-dev.27 — confirmar tamanho real antes de gerar miniatura — 2026-10-20

- O limite de 20 MiB da miniatura agora confere o tamanho do arquivo resolvido no disco, além do metadado da entidade File; arquivos maiores ou inacessíveis não chegam ao PDFPreview/ImageMagick.
- Unit cobre metadado desatualizado que subestima um arquivo acima do limite; esperado: geração e alteração do timeout do ImageMagick não são chamadas.

## Documentação PV7 — delimitar acessibilidade do conteúdo PDF — 2026-10-20

- Registrado que o canvas rotulado não expõe texto/estrutura do documento e que controles acessíveis não certificam a acessibilidade do PDF.
- O link Core ao arquivo original continua como alternativa; uma camada textual/estrutural não foi adicionada porque o upstream ainda registra limitações de teclado e semântica, exigindo avaliação própria antes dessa decisão.
- PHP 8.4 do Runtime não é bloqueio. A revisão acessível ampliada da interface permanece no gate PV7.

## 0.1.0-dev.26 — limitar renderização de páginas PDF extremas — 2026-10-20

- O renderizador limita cada canvas a 16 milhões de pixels e 16.384 px por lado, corrigindo também o excedente introduzido pelo arredondamento inteiro.
- Removida a escala mínima fixa de 50% que impedia o ajuste à largura e exibia 500% em uma página de 20.000 × 30.000 pt.
- Se o limite seguro exigir renderização abaixo de 50% da escala solicitada, o canvas fica oculto com mensagem acessível; navegação entre páginas e link Core de abertura/download permanecem disponíveis.
- Firefox ESR no Runtime renderizou um PDF público de 23 MB/14 páginas. Fixtures temporários confirmaram a página 20.000 × 30.000 pt em 100% (653 × 980 px) e a página 20.000 × 2.000.000 pt com fallback (25% efetivo). `node --check`, status-index e Drupal 11+ PASS.
- Um fixture temporário 20.000 × 200.000 pt foi ampliado até 250%: o canvas ficou em 15.971.904 pixels e 12.636 px de altura, e o indicador refletiu 194% efetivos sem ocultar o PDF. Arquivo removido após a validação; a URL passou a responder 404.
- Unit PDF 25/25 (163 assertions); Unit `aculta_image` 20/20 (71 assertions, 8 depreciações PHPUnit); Kernel PDF + Group 6/6 (145 assertions, 28 depreciações Core/contrib).
- Ainda Dev: o fallback de páginas extremas está validado no Homelab; os outros gates PV7 abaixo continuam pendentes.

## 0.1.0-dev.25 — limitar memória do canvas em páginas PDF extensas — 2026-10-20

- Primeira proteção do canvas: 16 milhões de pixels e 16.384 px por lado; aperfeiçoada em 0.1.0-dev.26 após reprodução com página extrema.
- `node --check`, gates de status-index e Drupal 11+ PASS.

## 0.1.0-dev.24 — rejeitar relatórios CSP sem estrutura completa — 2026-10-20

- O guard exige `type`, `url` e objeto `body` nos relatórios Reporting API e `document-uri` no envelope CSP legacy do Firefox; payloads incompletos recebem 400 antes do contrib.
- Unit 25/25 (163 assertions), sem depreciações PHPUnit; PHPCS Drupal PASS. Após rebuild no Runtime Alpha: formatos Reporting API e Firefox legacy válidos 202, objetos e itens incompletos 400, >64 KiB 413.
- Continua Dev até concluir os gates e a revisão acessível ampliada definidos no roadmap; PHP 8.4 do Runtime já foi relatado e não bloqueia esta entrega.

## 0.1.0-dev.23 — proteger o endpoint público de relatórios CSP — 2026-10-20

- O subscriber do viewer limita o corpo dos relatórios CSP a 64 KiB e valida o formato antes do controller contrib, retornando 400/413 para entrada malformada/grande em vez de erro 500.
- Unit do guard cobre Reporting API/legacy válidos, objeto inválido, JSON malformado, scalar, item inválido e limite de tamanho; suíte Unit 21/21 (159 assertions), sem depreciações PHPUnit. Metadados PHPUnit legados do próprio módulo foram migrados para atributos.
- Runtime Alpha: CSP/Reporting ativos, Report-Only true, enforcement false; header inclui `Reporting-Endpoints`, `report-to` e `report-uri`. Relatórios de ambos os formatos receberam 202; entrada inválida 400, corpo >64 KiB 413, Content-Type incompatível 415 e HEAD 405. Consulta agregada confirmou registros no DBLog sem expor payloads.
- Amostra Alpha de uma hora: 14 relatórios (7 `style-src-attr`, 3 `script-src`, 1 `script-src-elem`, 1 `style-src-elem`, 2 `worker-src`); as 3 URLs com query tinham o marcador sintético `pv7`. O DBLog retém URL e User-Agent: analisar dados e retenção antes de produção, e não ativar enforcement enquanto houver violações sem triagem.
- Uma visita pública limpa à home em Alpha gerou dois novos `style-src-attr` Report-Only, coincidentes com os elementos `.vvjb-items` e `.ui-front` observados no DOM; amostras de script não vazias: zero. Não habilitar enforcement antes de tratar os estilos inline usados por Drupal Core e VVJB.
- Firefox ESR no Alpha: PDF de 14 páginas renderizou; próxima página, zoom 125% e busca (“Resultado 1 de 5”) funcionaram; controles medem 44 px. A captura mobile encontrou miniatura do `aculta_image` com largura intrínseca de 480 px e motivou a correção responsiva no ACULTA420 dev.125.
- A etapa de busca de traduções pt-BR falhou durante a ativação inicial; o wrapper `translations://` agora está registrado, mas a disponibilidade de traduções não foi revalidada. Ainda Dev, não RC.

## 0.1.0-dev.22 — CSP em Report-Only para coleta em Alpha — 2026-10-20

- Dependências Composer aprovadas: `drupal/csp:^2.2` (2.2.4) e `drupal/reporting:^2.2` (2.2.0) para emitir política Report-Only e receber relatórios no endpoint local.
- Config Sync inclui módulos e endpoint; `report-only` está ligado, `enforce` desligado. A política de observação não habilita `report-sample`, para não registrar trechos de scripts/estilos inline.
- O PDF.js continua com `worker-src 'self'`. Fontes e violações serão aferidas pelos relatórios em Alpha. Nenhuma política bloqueante é aplicada; enforcement fica para decisão após Alpha.
- Runtime DEFERRED. Antes do deploy, executar gates/config validation e revisão visual pré-deploy; após deploy Alpha, conferir cabeçalhos, endpoint, registros, volume e privacidade. Não declarar RC ainda.

## 0.1.0-dev.21 — fonte da miniatura verificada antes do ImageMagick — 2026-10-20

- `PdfPreviewThumbnailBuilder` confirma que o arquivo PDF público ainda existe e está legível antes de invocar PDFPreview/ImageMagick. Arquivo ausente continua fail-soft e não produz warning por falha previsível do processador.
- Adicionado teste Unit para garantir que uma fonte ausente não chama PDFPreview nem altera o timeout compartilhado.
- A miniatura continua registrada como derivado temporário e renderizada por `aculta_image`; nenhuma imagem ou storage paralelo foi criado.
- Evidência: PHPUnit Unit 1/1 (5 assertions) PASS; a suíte atual do submódulo passou 12/12 (150 assertions), com uma depreciação PHPUnit. Em 2026-10-20, os assets `pdf-viewer.mjs`, `pdf.mjs` e `pdf.worker.mjs` responderam no Homelab com HTTP 200, `application/javascript` e `max-age=31536000`. PV7 segue aberta para CSP estrita e acessibilidade ampliada; LiteSpeed/Cloudflare fica para validação pós-deploy.
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.20 — upload editorial pela UI e acesso próprio — 2026-10-17

Classificação: PATCH (completar criação e acesso do Media pelo papel editorial).

- Firefox no Runtime de testes confirmou aceite dos termos pela rota real, upload pelo input nativo e criação da Media `document`.
- O fluxo revelou que o redirect pós-criação caía em acesso negado. O papel `content_editor` recebeu `edit own document media`, conforme o controle de acesso do Core; não recebeu edição de Media de outras pessoas.
- Repetição no Firefox confirmou mensagem de criação e abertura de `/media/239/edit`. Media, File, perfil e usuário temporários foram removidos após verificar ownership e `file_usage`.
- Unit 11/11 (145 assertions), Kernel 5/5 (106 assertions, 4 deprecações de Core/contrib) e gate Drupal 11+ (904 checks) PASS.
- `config:status` não pôde ser executado: `config/sync/user.role.administrator.yml` já contém duas chaves `permissions`. Não foi feito import global; a permissão foi alinhada no Runtime e no Config Sync do papel `content_editor`.
- CSP estrita, revisão acessível ampliada e validação LiteSpeed/Cloudflare pós-deploy continuam pendentes.
- RUNTIME STATUS: APLICADO no Homelab; produção DEFERRED.

## 0.1.0-dev.19 — cache versionado e matriz Chromium PV7 — 2026-10-17

Classificação: PATCH (estabilidade de versão da API/worker em cache e evidências de compatibilidade).

- As URLs da API e do worker PDF.js levam `?v=6.4.299`, igual à versão fixada em `composer.lock`; evita misturar módulos de releases diferentes em cache público de um ano.
- Chromium 155.0.8059.39 no Runtime renderizou PDFs de 1 MiB e 22 MiB (14 páginas), sem erros JS severos. PDF inválido e 503 simulado exibiram fallback. Com cache desabilitado, rede limitada a 700 ms e 64 KiB/s renderizou o PDF pequeno em 20,9 s.
- CSP simulada por proxy de loopback com `worker-src 'self'` renderizou o viewer. A política permitiu inline scripts/styles do Drupal e bloqueou fontes externas; não substitui auditoria de uma CSP estrita nos servidores.
- Cache Runtime: HTML privado sem cache, PDF/JS público max-age de um ano; Range retornou 206. Produção retorna `text/plain` para os três módulos `.mjs`; corrigir header depende da regra `.htaccess` chegar via deploy e de validação pós-deploy.
- A permissão `content_editor:create document media` está ativa; upload/criação por essa identidade na UI segue pendente por falta de sessão de teste autorizada.
- A sessão temporária chegou à tela de termos obrigatórios. O aceite não foi marcado; nenhum arquivo/Media foi enviado. A identidade temporária e o perfil automático foram removidos após confirmar que não tinham conteúdo nem pedidos próprios.
- RUNTIME STATUS: APLICADO no Homelab; produção DEFERRED.

## 0.1.0-dev.18 — MIME do PDF.js e evidências da PV7 — 2026-10-17

Classificação: PATCH (compatibilidade de entrega dos módulos PDF.js).

- `.mjs` agora recebe `application/javascript` via `.htaccess`; verificado no Apache do Runtime para `pdf.mjs` e `pdf.worker.mjs`.
- Firefox 153.4 ESR no Runtime renderizou PDFs de 1 MiB e 22 MiB (14 páginas cada); arquivo inválido manteve o link autorizado com mensagem fail-soft.
- Unit 11/11, Kernel 5/5 (106 assertions; quatro deprecações) e gate Drupal 11+ (892 checks) PASS.
- Chromium, rede lenta e CSP estrita permanecem sem evidência. Produção Hostinger/Cloudflare ainda entrega o worker como `text/plain`; validar após deploy, sem deploy nesta fase.
- RUNTIME STATUS: APLICADO no Homelab; produção DEFERRED.

## 0.1.0-dev.17 — PV6 homologada em Wiki, Fórum e apresentações — 2026-10-17

Classificação: MINOR (autoria Media Embed compartilhada e conclusão da homologação PV6).

- Wiki e Fórum usam `media_embed` restrito a `document`; CKEditor 5 validado nos dois formatos com `CKEditor5::validatePair()` e zero violações. Source Editing mantém markup legado sanitizado sem mudar conteúdos/revisões.
- O Fórum renderiza o embed por `text_default`; o formatter Media/File não muda e o tema ACULTA420 já apresenta os atributos neutros usando tokens.
- PHPUnit Unit 11/11 (145 asserções) e Kernel 5/5 (106 asserções) PASS no MariaDB `aculta_simpletest`; quatro deprecações Core/contrib.
- Runtime de teste `https://aculta.toca.net.br`: filtros e editor aplicados individualmente, cache rebuild; Wiki/Fórum sem migration de conteúdo. Drush render do tópico restrito confirma viewer e fallback autorizado. Artigo, blog e projeto retornam HTTP 200 com viewer.
- Firefox 153.4 ESR: PDF de 14 páginas, navegação, zoom a 125%, screenshot revisado em desktop 1440 px e viewport estreito (500 px de largura interna mínima do Firefox headless); sem overflow horizontal da página, controles nomeados com 44 px ou mais, foco 3 px, live regions e link fallback. Smoke visual/acessível PASS; PV7 continua com matriz ampla de navegadores/CSP.
- Config Sync global não foi importado; `user.role.administrator.yml` continua com chave YAML duplicada preexistente.
- RUNTIME STATUS: APLICADO.

## 0.1.0-dev.16 — Media Embed da Wiki compartilha o display PDF — 2026-10-17

Classificação: PATCH (habilita processamento de Media `document` no formato Wiki existente).

- `filter.format.wiki` permite o elemento `drupal-media` e processa Media Embed após os filtros HTML, restrito ao bundle `document`; o display Media compartilhado mantém as verificações de acesso do Core.
- Kernel confirma que embed em formato compatível renderiza o formatter PDF compartilhado. Na PV6, CKEditor também foi habilitado e validado sem incompatibilizar a lista HTML histórica.
- Runtime de teste `https://aculta.toca.net.br`: formato `wiki` atualizado por entidade de configuração e cache reconstruído. Esta entrega foi homologada na versão .17, sem conteúdo PDF preexistente na Wiki para screenshot dedicado.

## 0.1.0-dev.15 — viewer compartilhado em campos File institucionais — 2026-10-17

Classificação: MINOR (novo formatter compatível com File API e sem storage próprio).

- Adicionado `aculta_pdf_file`, formatter para campos File diretos. Ele usa o builder compartilhado somente para PDF público permanente, depois de validar acesso da entidade, do campo e de download do File. Tipos não PDF, arquivos privados e falhas do viewer mantêm o link Core autorizado.
- Os displays `node.document.default` e `node.document.full` passam a usar o formatter para `field_document`; nenhum node, Media ou File é migrado ou regravado.
- PHPUnit: Unit 11/11 (145 asserções) e Kernel 4/4 (87 asserções) PASS no MariaDB dedicado `aculta_simpletest`; quatro deprecações de Core/contrib registradas. Drupal 11+ Gate (888 checks), image, public-slugs e module/theme-boundary gates PASS.
- Drush confirmou no Runtime de teste que `aculta_pdf_viewer` está ativo e que ambos os displays já usam `aculta_pdf_file`; não alterei configuração nesta sessão. RUNTIME STATUS: DEFERRED para smoke visual/HTTP do novo campo; Kernel confirma o render funcional.

## 0.1.0-dev.14 — corrigir entrega do PDF privado no Runtime — 2026-10-17

Classificação: PATCH (mantém a rota e as checagens de acesso do Drupal Core).

- O viewer gera URLs privadas como `/system/files?file=...`, usando a rota Core `system.files`. No Runtime, `/system/files/{filepath}` retornava 404 mesmo para membros autorizados; a forma com `file` na query entrega o arquivo e mantém a autorização do Core em cada requisição.
- Firefox 153.4 ESR no Runtime validou o PDF público e o PDF privado de turma (14 páginas), navegação, zoom, busca e renderização do canvas. A miniatura JPEG pública foi gerada pelo pipeline `aculta_image`. O membro recebeu 200; não membro e anônimo receberam 403. Remover membership ou relação Group negou imediatamente a mesma URL conhecida (403); restaurá-los retornou 200. A URL privada usa `?file=` porque a forma `/system/files/{filepath}` retornava 404 nesse Runtime.

## Submódulo aculta_pdf_viewer_group 0.1.0-dev.4 — relação Group após habilitação — 2026-10-17

Classificação: PATCH (corrige a ordem de instalação da configuração Group).

- A relação `lms_course` deixa de ficar em `config/optional`; permanece no Config Sync, que a importa após habilitar o submódulo e descobrir o plugin `aculta_pdf_viewer_group:course_document`.
- O Runtime confirmou que a instalação falhava ao resolver o plugin na importação opcional antecipada. A relação agora fica apenas no Config Sync e foi aplicada por importação parcial dirigida, depois da habilitação do submódulo.
- O formulário de `course_document` mantém o checkbox de status visível como projetado; o hook continua impedindo que esses Media sejam publicados.
- Os YAML do bundle e da relação usam UUIDs estáveis exportados do Runtime; `config:status` não aponta drift nos itens PV5. A configuração inválida do LMS que referia `filter.format.0` foi corrigida para `plain_text`, como em `config/sync`.
- Runtime: configuração PV5 ativa. Em Firefox, membro recebeu 200 e renderizou o PDF privado; não membro e anônimo receberam 403. Remover membership ou relação Group negou a URL conhecida (403), e restaurá-los devolveu 200. Media e arquivo de teste foram removidos após a rodada. A integração Group segue dependente dos gates PV7 compartilhados para o release.

## 0.1.0-dev.13 — zoom visível fora da tela cheia — 2026-10-17

Classificação: PATCH (correção de apresentação; sem mudança no formato de arquivo ou contrato de acesso).

- A página do PDF passa a ficar em uma área rolável; o canvas mantém o tamanho de renderização definido pelo zoom do PDF.js, sem ser reduzido pelo CSS para a largura do container.
- O canvas permanece oculto até a primeira renderização bem-sucedida, evitando uma página em branco quando o PDF.js falha.
- RUNTIME STATUS: cache rebuild feito no host de teste para carregar o código; revisão visual no Firefox e no mobile segue pendente.

## Submódulo aculta_pdf_viewer_group 0.1.0-dev.3 — regressão de relação removida — 2026-10-17

Classificação: PATCH (cobertura de segurança, sem alteração funcional).

- O Kernel agora confirma que a URL privada conhecida retorna 403 quando a relação Media↔Group é removida; religa a Media para testar separadamente não membro e revogação de membership.
- PHPUnit Kernel: 1 teste, 39 asserções, PASS; execução isolada no MariaDB `aculta_simpletest`.
- RUNTIME STATUS: DEFERRED; o import de configuração e a homologação com memberships reais continuam pendentes.

## Submódulo aculta_pdf_viewer_group 0.1.0-dev.2 — relação de turma restrita a lms_course — 2026-10-16

Classificação: PATCH (submódulo; o módulo pai não mudou de versão).

- `MediaDocument` passa a `enforced: FALSE`. Antes, `enforced: TRUE` instalava a relação de documento em todo tipo de grupo criado. Agora a relação existe só onde está configurada: `lms_course`, pela configuração `group.relationship_type.lms_course-af8ec2205541224f9d8c4` em `config/sync/`.
- Evidência: Kernel `aculta_pdf_viewer_group` 1/1 (37 asserções) OK, com 23 deprecações de contrib, no banco `aculta_simpletest`.
- RUNTIME STATUS: DEFERRED. No Runtime de teste, o tipo de mídia `course_document` e o submódulo ainda não existem; a configuração está só no sync. A relação em `lms_course` deve ser verificada depois da importação de configuração, que é operação separada.

## 0.1.0-dev.12 — mapeamento único de documentos, miniatura sem checagem morta, versão do submódulo de turma — 2026-10-16

Classificação: PATCH.

- `DocumentFieldMap::fieldForBundle()` passa a ser a única fonte do mapeamento bundle → campo, usada por `PdfViewerBuilder` e `AcultaPdfMediaFormatter`. Um novo bundle de PDF não pode ser aceito em um ponto e recusado no outro.
- `PdfPreviewThumbnailBuilder`: removida a checagem da URL gerada. A marcação usa a URI do arquivo gerenciado (`buildManagedImage`), então a checagem não protegia o `src` renderizado. Sai também a dependência `file_url_generator` desse serviço.
- `aculta_pdf_viewer_group` 0.1.0-dev.1: versão declarada no `info.yml` (não tinha). O README registra que `enforced: TRUE` instala a relação em todos os tipos de grupo; restringir a `lms_course` é decisão do responsável antes do Runtime.
- Evidência: PHPUnit Unit `aculta_pdf_viewer` 7/7 (82 asserções) OK. Kernel `aculta_pdf_viewer` 3/3 (61 asserções) OK, com 4 deprecações; Kernel `aculta_pdf_viewer_group` 1/1 (37 asserções) OK, com 23 deprecações de contrib. Os testes rodaram no banco MariaDB dedicado `aculta_simpletest`, conforme `docs/operations/BANCO-DE-TESTE.md`. Nenhum teste usou SQLite.
- RUNTIME STATUS: cache rebuild feito no Runtime de teste com a URI de teste. Testes de Kernel executados fora do Runtime.

## 0.1.0-dev.11 — correções de testes e formulário dos documentos de turma — 2026-10-16

- Kernel tests `AcultaPdfViewerInstallTest`, `AcultaPdfMediaEmbedTest` e `CourseDocumentAccessTest` passam a instalar com dependências (`install(..., TRUE)`). Com `FALSE`, `aculta_image` não era ativado e o container falhava em `aculta_pdf_viewer.thumbnail_builder` (4 erros).
- Formulário do bundle `course_document`: `status` deixa de constar em `hidden` e permanece como checkbox em `content`, conforme o README. A regra de `entity_presave` continua mantendo a mídia de turma não publicada.
- Testes unitários do formatter e do builder passam bundle e campo de origem (`course_document` / `field_media_course_document`).
- Evidência: PHPUnit Unit (pdf_viewer e aculta_image) 21/21; Kernel (pdf_viewer e group) 4/4 com SQLite em memória; gates `validate-aculta-image`, `validate-aculta-portal-drupal11`, `validate-module-theme-boundary`, `validate-public-slugs` e `validate-environment-addresses` em PASS.
- RUNTIME STATUS: DEFERRED. A config da turma (`course_document`, `lms_course`, papéis) ainda não foi importada no Runtime `aculta_runtime`, que tem 273 itens de drift pré-existentes; importar exige revisão separada.

## 0.1.0-dev.10 — documentos de turma privados e miniaturas alinhadas

- Bundle `course_document` em `private://`, separado do bundle público institucional `document`; relação de turma Group e guarda de ciclo de vida mantêm as mídias de curso não publicadas.
- Config Sync define permissões de leitura/criação por papel da turma e instala o bundle junto à integração Group.
- JPEGs gerados pelo PDFPreview são validados e registrados como arquivos temporários gerenciados pelo `aculta_image`; a limpeza de imagens existente remove os derivados sem uso após a idade configurada.
- A marcação da miniatura usa `aculta_image.render`, incluindo metadados de cache do arquivo. A ausência ou falha do preview continua sem bloquear o leitor PDF.js.

## 0.1.0-dev.9 — integração opcional Group↔Media

Adiciona o submódulo opcional `aculta_pdf_viewer_group`, que fornece a relação
Group para Media `document`, e configuração opcional para `lms_course`. Papéis
estudante e educador recebem a permissão explícita de visualizar a Media
relacionada. O módulo base continua independente de Group. Runtime e teste de
revogação por membership continuam DEFERRED; não usar isso como aprovação para
arquivos confidenciais até o teste ponta a ponta PV5.

## 0.1.0-dev.8 — PDFPreview opcional e contrato atualizado

PDFPreview deixa de ser dependência obrigatória de ativação; o serviço de
miniaturas continua fail-soft quando o contrib não está habilitado. README e
metadados passam a refletir leitura `private://` conforme Media/File access,
incluindo que permissões amplas podem permitir acesso anônimo. Kernel verifica
registro do formatter e serviço de miniaturas sem habilitar PDFPreview.

## 0.1.0-dev.7 — contrato de acesso do arquivo privado

Kernel confirma que o endpoint Core revalida a permissão efetiva da Media:
nega sem `view media` e para Media despublicada sem permissão de proprietário,
mas permite Media publicada ao papel anônimo quando ele tem `view media`.
Config Sync concede `view media` a anônimo e autenticado, então `private://`
protege a entrega direta do servidor web, mas não define audiência restrita.
O Group instalado não tem relação Media habilitada; documentos confidenciais
por turma aguardam integração de access Group↔Media.

## 0.1.0-dev.6 — validar entrega privada pelo Core

O teste Kernel cria um PDF `private://`, confirma a URL local da rota Core,
resposta 200 para o proprietário autorizado e 403 para usuário autenticado sem
permissão e acesso anônimo. Outro usuário com `view media` recebe 200 enquanto a
Media está publicada e 403 após ela ser despublicada. Confirma cache privado,
max-age zero no render e ausência de miniatura privada. Group/turmas, revogação
após mudança de membership e browser/PDF.js continuam pendentes.

## 0.1.0-dev.5 — leitura privada pela rota Core

O viewer aceita PDFs permanentes `private://` quando Media, campo e File
autorizam `view` e `download`. O File URL Generator produz a rota privada do
Drupal Core, que revalida as referências e o acesso no pedido dos bytes; o
render privado usa max-age zero. Miniaturas continuam limitadas a arquivos
públicos. Testes de integração por turma, revogação e browser seguem pendentes.

## 0.1.0-dev.4 — miniaturas seguras da primeira página

Integra PDFPreview 1.2.0 com ImageMagick contrib 4.0.2. Gera miniatura somente
para PDFs permanentes em `public://`, com pasta e dimensões controladas, limite
de 20 MB e timeout de conversão de 15 s. Falhas ou ausência de binários/policy
mantêm o leitor PDF.js e o link. Nenhum PDF privado passa pelo gerador, que usa
o esquema padrão do Drupal para derivados. `imagemagick` e `pdfpreview` ficam
declarados no Config Sync; política de imagem do host não foi relaxada.

## 0.1.0-dev.3 — controles do leitor integrado

O viewer adiciona zoom/restauração, busca textual por página, navegação dos resultados e
modo tela cheia, mantendo o PDF.js local e o link autorizado. O Kernel valida
markup dos controles e o fluxo Media Embed; `node --check` valida sintaxe ESM.
Firefox 153.4 ESR confirmou no Runtime o leitor público (14 páginas, navegação,
zoom, busca, tela cheia e zero erros JS) e a inserção da Media existente pelo
CKEditor 5 seguida de salvamento e renderização do artigo. Todas as fixtures
foram removidas. A Media foi criada por Drush; upload/criação pelo papel
`content_editor`, leitura privada,
acessibilidade visual, CSP, preview e Hostinger continuam pendentes.

## 0.1.0-dev.2 — inserção editorial pelo CKEditor 5

O papel `content_editor` recebe `create document media` para criar PDFs
destinados à publicação via biblioteca do CKEditor 5. PV2 está parcialmente
aplicada no Runtime de teste: submódulo, display e permissão ativos; Kernel
confirma a passagem por Core Media Embed até o formatter PDF.js. Não há PDF ou
embed editorial no Runtime para validação interativa, que segue DEFERRED. O
bundle Media `document` usa `public://` e não é adequado a conteúdo confidencial.

## 0.1.0-dev.1 — F3: viewer compartilhado como submódulo

O serviço `aculta_pdf_viewer.builder`, formatter `aculta_pdf_media` e biblioteca
PDF.js passam a pertencer ao submódulo opcional `aculta_pdf_viewer`. A configuração
exportada habilita o módulo para o display padrão Media `document`. Testes Unit do builder/formatter e Kernel de descoberta do submódulo passaram
no ambiente isolado. O viewer ESM também teve smoke test isolado na F2. A etapa
PV2 (CKEditor da Revista), Runtime, PDFPreview/ImageMagick, leitura privada e
produção LiteSpeed seguem DEFERRED; veja [roadmap](docs/ROADMAP.md).

## Padronização do namespace H5P ACULTA — 2026-10-21

- Classes, interfaces, controladores, hooks, formulários, plugins, seletores e arquivos PHP próprios do H5P agora usam `aculta_H5P` no identificador; as pastas/namespaces internos também seguem o prefixo. A integração PHP do Fórum segue a mesma regra.
- O plugin de entidade e os campos de referência passam a usar `aculta_h5p_content`. O update `8007` descarta conteúdos, tentativas e vínculos H5P legados e recria a entidade sob o ID novo, conforme decisão do responsável; as tabelas e contratos de protocolo exigidos pelo H5P continuam com seus nomes upstream.
- Interfaces e classes globais definidas pelo H5P permanecem intactas porque são contratos externos implementados pelo adaptador ACULTA.
- Os nomes próprios restantes `AssetFileStorage` e `FinishedEvent` foram padronizados para `aculta_H5PAssetFileStorage` e `aculta_H5PFinishedEvent`; a descoberta Kernel verifica o serviço e o evento renomeados.
- Update `8007` aplicado no Runtime `aculta_runtime` em 2026-10-21 após dump íntegro e inventário: zero conteúdos/refs/tentativas legados. A primeira execução revelou que o Entity Reference não recalcula a definição após remover o tipo antigo; o hook foi ajustado para atualizar a configuração e a definição instalada via APIs Core. A segunda execução completou; entidade `aculta_h5p_content` e destino do Fórum confirmados.
- Smoke HTTP pós-update: catálogo `/` em `cursos` 200; painéis H5P anônimo 403 esperado; nenhum 500 nas rotas verificadas.

## F15 — reprodução iframe interna e publicação de assets — 2026-10-21

- O formatter prefere `div` quando a biblioteca oferece esse modo e reproduz
  bibliotecas `iframe`-only usando o inicializador interno `about:blank` do H5P
  Core, ainda dentro da página do curso já autorizada. Não restaura a rota
  pública de incorporação removida.
- O iframe tem ID e `data-content-id` no formato esperado pelo H5P Core, título
  acessível, tela cheia habilitada e os mesmos assets, token e controles de
  cache da reprodução em `div`.
- A cópia de assets Composer saiu de `library_info_build` e acontece somente
  na instalação/update `8008`. A resolução do wrapper `assets://` usa o caminho
  configurado pelo Core e `FileSystemInterface` para gravar arquivos, sem
  depender da permissão reportada pelo wrapper oculto durante cada requisição.
- Kernel H5P: 15 testes / 383 asserções; Unit H5P: 24 / 73. O Kernel renderiza
  uma biblioteca iframe-only e valida a autorização de curso, marcação,
  título acessível, fullscreen, bibliotecas anexadas e cache por usuário.
  Foram reportadas 44 depreciações do Core/Group/Views instalados. PHP CLI
  8.4.26; PHP 8.5 não validado.
- Rebuild de cache no Runtime de teste deixou as rotas principais e de cursos
  respondendo HTTP 200; o cache antigo que apontava `field_h5p_content` para a
  entidade removida foi invalidado. O host principal e o host `cursos` retornam
  HTTP 200.
- O Kernel comprova que somente Media `course_document` relacionada à turma é
  enviada ao `aculta_pdf_viewer.builder`; H5P e o PDF Viewer compartilham a
  mesma Media e o mesmo arquivo privado.
- Smoke autenticado de estudante no Runtime: `H5PIntegration.core` e os assets
  do Core/biblioteca carregaram no iframe; o PDF.js apresentou um PDF privado
  em canvas. O estudante viu o texto do H5P no iframe e o texto do PDF no
  canvas. Curso, atividade, Media, relação, arquivo e dados de tentativa
  temporários foram removidos após o smoke.
- A discussão H5P–Fórum passou no Kernel (1 teste / 37 asserções) e nos testes
  Unit dedicados (3 / 15). A revisão visual autenticada em Firefox no X11
  (framebuffer 1024×768; monitores físicos desconectados) confirmou o tópico
  correto sob o player, access permitido ao estudante e abertura do Node em
  `cursos` (HTTP 200). O curso, a atividade e o tópico temporários foram
  removidos. Após mapear `lms-h5p-course-documents` no ACULTA420, novo smoke X11
  confirmou a classe `mt-3` no wrapper do PDF junto da atividade e do Fórum.

## F15 — associação de PDFs existentes a atividades — 2026-10-21

- Educadores selecionam, no editor da atividade, documentos `course_document`
  já relacionados ao mesmo curso. O H5P guarda referências Media; os arquivos
  privados não são copiados nem recebem uma segunda relação de propriedade.
- Na exibição, o formatter confirma novamente a relação Media↔Group e chama o
  serviço `aculta_pdf_viewer.builder`, que revalida acesso à Media, ao campo e
  ao arquivo privado. Metadados de cache do curso, relações e documentos são
  propagados; viewer privado mantém cache max-age zero.
- Campo de referência instalado por update `aculta_lms_h5p_update_8006`; aplicado
  no Runtime de teste após confirmar zero conteúdos H5P e zero Media
  `course_document`. A tabela foi criada sem alterar entidades existentes.
  Kernel H5P: 14 testes / 341 assertions; inclui persistência e recarga da
  referência Media no H5P Content.
  PASS em MariaDB dedicado; Unit H5P: 23 testes / 67 assertions PASS. As 43
  depreciações Kernel são de Core/Group/Views. PHP CLI 8.4.26; PHP 8.5 não
  validado. No Runtime, a tabela de referência está instalada; sem conteúdo
  H5P e documentos de curso, ainda não foi possível revisar seleção/renderização
  visual autenticada.
- RUNTIME STATUS: PARTIAL; criar fixture temporária de curso, atividade e PDF,
  validar os acessos e remover os dados de smoke antes de RC.

## F15 — persistência privada de tentativas H5P — 2026-10-21

- O Kernel agora confirma que o estado de uma tentativa H5P continua disponível
  para a mesma conta após troca de sessão e permanece invisível a outro
  estudante, mesmo quando ambos têm matrícula ativa no mesmo curso.
- Suítes completas H5P: Unit 23 testes / 67 assertions e Kernel 14 testes /
  270 assertions PASS no MariaDB dedicado. As 43 depreciações Kernel vêm de
  Core/Group/Views instalados. PHP CLI 8.4.26; PHP 8.5 não validado.
- Revisão visual X11 do painel administrativo no Runtime de teste passou. O
  painel foi simplificado para evitar repetir links já apresentados nas abas e
  recebeu título curto. O Runtime tem zero conteúdos H5P, então não há player
  disponível para inspeção visual; também não há tópicos H5P do Fórum para um
  smoke de conteúdo real.
- A integração de discussões com `aculta_forum_lms` está coberta pelos testes
  próprios do adapter e por `LmsH5PDiscussionResolverKernelTest`: 1 teste / 36
  assertions PASS no MariaDB dedicado. O Kernel persiste H5P, curso, turma,
  tópicos e memberships reais; verifica isolamento por conteúdo/curso/turma,
  acesso ao tópico, parâmetro de Group no autocomplete e composição do player.
  As 45 depreciações são de Core/contrib. Validação visual de tópico sob um
  player permanece condicionada à existência de atividade e tópico de teste.
- Corrige a rota do painel estudante para `/atividades-interativas`: o
  subscriber da Conta remove `/minha-conta` de caminhos antigos, e o endereço
  anterior terminava em 404. Kernel confirma o caminho canônico; no Runtime de
  teste o endereço novo agora alcança a checagem de papel (403 anônimo) e o
  caminho legado redireciona para ele (301).

## Navegação multidomínio — 2026-10-19

- Remove os atalhos "Atividades interativas" e "Atividades dos cursos" do menu de conta usado pela barra multidomínio. As rotas dos painéis H5P de estudante e educador permanecem disponíveis no contexto do LMS.
- RUNTIME STATUS: DEFERRED.

## Marcação do H5P por token e contrato de classe do H5P core — 2026-10-19

- Administração (`H5PLibraryAdmin`, `H5PDashboardController`) e formatação (`H5PDefaultFormatter`) usam render arrays com `data-portal-ui` (`h5p-admin-header`, `h5p-dashboard-course`, `h5p-content-unavailable`).
- Classes `h5p-content` (formatador) e `h5p-editor` (widget) continuam, porque o H5P core as usa para localizar as instâncias. Elas estão em render arrays, na exceção documentada do gate de fronteira.
- Widget do editor: o rótulo "Tipo de atividade" ficou em um item próprio e o contêiner do editor recebe os atributos. Os seletores do JS não mudam.
- Removidas classes sem consumidor (`h5p-settings-container`, `h5p-visibility-toggler`) e um comentário de código morto com marcação HTML.
- RUNTIME STATUS: DEFERRED.

## Exclusão de conteúdo H5P tolerante a arquivo retido — 2026-10-19

- `H5PManagedFileStorage::deleteContent()` processa todos os arquivos mesmo quando um é recusado (ainda referenciado ou fora do padrão gerenciado); a pasta do conteúdo só é removida quando nenhum arquivo ficou retido.
- Pendência: a ponte estática `H5PDrupal::framework()` ainda usa `\Drupal::service()`, por causa das chamadas estáticas do fork H5P.
- RUNTIME STATUS: DEFERRED.

## Acesso de Views e formulários administrativos por DI — 2026-10-19

- `H5PAccessUserPoints` agora usa a rota canônica de perfil e a conta carregada
  pelo Route Match, com permissão global separada; remove parsing de caminho e
  service locator.
- Formulários de configurações e atualização recebem filesystem, invalidator de
  cache, banco, gerador de URL e serviços de assets por DI. Os serviços
  sobrevivem à serialização do Form API. O route access de Views tem testes
  Unit de dono, outra conta, rota incompatível e permissão administrativa.
- Unit H5P: 23 testes/67 assertions. Kernel H5P no MariaDB dedicado: 14
  testes/263 assertions; 40 depreciações upstream Core/Group/LMS/Views.

## Continuação F10 — DI e ciclo de imagem H5P (2026-10-19)

- Refatorados os adapters de editor/storage/AJAX para receber serviços por DI;
  `H5PEditorUtilities` também passou a serviço injetável e widgets/controllers
  usam o serviço. URLs de assets do editor continuam absolutas pela API atual
  `FileUrlGeneratorInterface::generateAbsoluteString()` do Core instalado.
- Kernel verifica o ciclo real de ingestão, uso e remoção de imagem administrada
  pelo `aculta_image`; LMS/H5P Unit passou (36 testes, 148 assertions; 5
  deprecações do PHPUnit) e Kernel em MariaDB dedicado passou (24 testes, 573
  assertions; 83 depreciações de Core/contrib). Gates Portal (966), imagem,
  slugs e pacotes próprios passaram. PHP local 8.4.26; PHP 8.5 não validado.
- O contrato LMS passa a explicitar imagem compartilhada, documentos de turma
  no viewer PDF e discussão futura com `aculta_forum`; não cria leitor PDF,
  storage de arquivo ou sistema de discussão paralelo no H5P.

## F10 concluída — H5P ponta a ponta — 2026-10-19

- Corrige a serialização de serviços injetados no formulário de instalação de bibliotecas H5P, resolve URIs privados para caminhos locais antes da validação oficial e exclui o upload temporário pela API da entidade File.
- Corrige o carregamento dos assets de bibliotecas H5P privadas: scripts e estilos servidos pela rota Drupal ficam fora da agregação, que procura os arquivos sob `DRUPAL_ROOT` e não consegue incorporar conteúdo privado.
- Corrige a exclusão de bibliotecas: arquivos gerenciados são removidos pela Entity API e o identificador de uso de imagens inclui o caminho relativo completo, permitindo que `aculta_image` confirme a remoção segura.
- Corrige o formatter para normalizar IDs de bibliotecas dinâmicas e a classificação de conteúdo `div`; corrige também a referência ao formulário de matrícula no construtor da sala de curso, que causava 500 ao visitante sem matrícula.
- Homologação visual no Runtime `cursos`: pacote de biblioteca aceito pela tela administrativa, atividade salva pelo editor no Group 15 temporário e reproduzida por estudante matriculada. O navegador anônimo não recebeu o player e recebeu 403 na rota de arquivos privados; a estudante autorizada passou pela checagem de acesso. Dados, contas e biblioteca de smoke temporários foram removidos pela API Drupal; `H5P.HelloWorld` preexistente foi preservada.
- Unit LMS/H5P: 36 testes, 148 assertions, 5 deprecações do PHPUnit. Kernel LMS/H5P em MariaDB dedicado: 23 testes, 549 assertions; 83 deprecações de Core/contrib (incluindo Webform Score). Gates Drupal 11+ (966 verificações), imagens, slugs (387 rotas; 20 ocorrências contrib baseline) e pacotes próprios (20 verificações) aprovados.
- RUNTIME STATUS: HOMOLOGAÇÃO DE TESTE CONCLUÍDA; produção não foi alterada.

## Revisão do salvamento do editor — 2026-10-19

- A revisão encontrou que o envio síncrono com `getParams()` ignorava a validação e a atualização de versão oferecidas pela API `getContent()` do H5P Editor. O formulário agora aguarda a serialização/validação assíncrona de todos os editores antes de submeter.
- Falhas ao consultar editor ou validar conteúdo interrompem o envio e exibem alerta acessível; widgets vazios continuam permitindo limpar a atividade.
- H5P Kernel: 24 testes, 272 assertions; gates Drupal 11+, imagem e slugs aprovados; sintaxe JavaScript validada. Persistência e reprodução ponta a ponta continuam DEFERRED.
- RUNTIME STATUS: DEFERRED para salvamento/reprodução ponta a ponta.

## 0.4.0-dev.144 — instalação privada de bibliotecas H5P — 2026-10-18

- Adiciona formulário administrativo para importar pacotes `.h5p` locais, com validação oficial H5P, limite/extensão fornecidos pelo Core, conteúdo ignorado e Hub remoto desligado.
- Bibliotecas e assets são gravados em armazenamento privado por arquivos Drupal gerenciados; imagens raster/SVG passam por `aculta_image`. Remoção administrativa limpa usos e arquivos por File API; cópias de conteúdo exigem arquivos gerenciados. A importação legada do editor falha fechada, e substituição silenciosa de assets instalados é recusada.
- Unit H5P: 8 testes, 25 assertions; Image Unit: 20 testes, 71 assertions; Kernel descoberta de hooks/serviços: 1 teste, 5 assertions. Teste com pacote real, browser e Runtime: DEFERRED; módulo permanece desabilitado.
- RUNTIME STATUS: DEFERRED.
## Continuação — instalação de bibliotecas H5P

- Remove a versão fixa da biblioteca dinâmica `h5p.editor`: no Drupal 11.4 ela impedia a invalidação do URL do editor após mudanças no JS. A descoberta Kernel confirma que todos os assets do editor usam o cache busting nativo baseado em conteúdo/limpeza de cache.
- Coordena a serialização de todos os editores H5P em um único handler por formulário, usando a API do editor instalado (`getLibrary()`/`getParams()`), ignora widgets vazios/removidos e descarta instâncias após substituição AJAX. O smoke visual detectou que o editor substitui seu nó raiz pelo iframe durante o `detach('unload')`; o registry agora preserva o editor enquanto esse iframe continuar conectado.
- Permite que a linha vazia padrão de um campo H5P multivalorado seja submetida sem erro; biblioteca selecionada continua exigindo parâmetros válidos.
- Corrige a sanitização do payload do editor: o H5P Core recebe o envelope `{library, params, metadata}` que sua API valida; os parâmetros sanitizados e metadados voltam ao formato de armazenamento do widget.
- Runtime UI smoke (Homelab): iframe preservado durante attach/detach, biblioteca selecionada e payload HelloWorld serializado com texto e metadados. A validação do curso exige ao menos uma aula; persistência e playback ponta a ponta seguem DEFERRED.

- Corrige o schema de configuração e os nomes usados pelo adapter H5P para compartilhar o prefixo `aculta_lms_h5p_` definido na configuração de instalação.
- Migra a entidade e o field type H5P para PHP Attributes do Drupal 11; troca a descoberta de bibliotecas pelo serviço atual do Core.
- Instalação real via Kernel passou com biblioteca, asset JavaScript privado e SVG gerenciado pelo `aculta_image`; pacote sem biblioteca é rejeitado sem escrita no banco.
- Kernel H5P: 3 testes, 40 assertions; Unit H5P: 11 testes, 46 assertions. Runtime e navegador: DEFERRED.
- Restringe endpoints de progresso aos métodos HTTP usados pelo H5P e marca respostas com dados pessoais como `private, no-store`; rota validada em Kernel.
- Corrige leituras e resets de configuração que ainda usavam nomes do módulo contrib; teste instala em diretório privado customizado e o Unit do upload confere o destino configurado.
- Valida entradas do ZIP antes do extrator H5P para bloquear caminhos absolutos, traversal, caminhos malformados e links simbólicos; upload de bibliotecas agora usa o diretório privado configurado e o limite de tamanho nativo do PHP/Core.
- Verificação atual: Kernel H5P 5 testes/60 assertions; Unit H5P 12 testes/52 assertions.
- A revisão dos controllers remove service locators substituíveis por DI e testa sua criação no container; cobertura Kernel atual: 7 testes/85 assertions.
- Pré-validação integral dos arquivos de todas as bibliotecas antes do savePackage; um pacote com SVG ativo no último arquivo não grava nem a biblioteca nem o JS anterior. Kernel atual: 8 testes/101 assertions; Unit H5P: 12/52.
- Migra os quatro plugins anotados para Attributes Drupal 11 e troca `*.views.inc` depreciado por hook OOP com DI; declara `views` como dependência. Descoberta Kernel sem depreciações: 9 testes/119 assertions.
- Completa traduções da interface administrativa/permissões, verifica via descoberta Kernel a condição de curso do CTools, corrige tokens da ação de restrição e aplica CSRF do Drupal às operações POST de upgrade/reconstrução; entradas inválidas retornam erros HTTP apropriados e IDs ignorados são normalizados.
- Migra os controllers administrativos restantes de atalhos do ControllerBase para injeção explícita de serviços. Corrige instanciação DI dos formulários de manutenção e trata a biblioteca inexistente sem acesso a índice indefinido. Kernel H5P: 9 testes/124 assertions; Unit H5P: 12 testes/52 assertions; gates Portal, imagem, pacotes e slugs aprovados.
- RUNTIME STATUS: DEFERRED.

- Atualiza a orientação do painel do educador para refletir o fluxo já protegido de edição H5P na aba Editar da sala do curso.
- Adiciona Kernel com curso Group real, campo H5P e permissões reais: estudante matriculado e educador com `edit group` acessam somente conteúdo do curso; pessoa externa, referência inexistente e matrícula revogada são negadas. A condição CTools é avaliada sobre o mesmo curso. Os endpoints reais gravam/leem estado privado e salvam pontuação para o estudante, negando acesso externo. O salvamento de estado usa `merge()` pela chave primária para evitar corrida entre leitura e gravação. Kernel total: 10 testes/168 assertions; essa integração expõe 40 depreciações provenientes de Core/Group/LMS/Views instalados.
- Extrai a resolução do diretório privado H5P para serviço DI e injeta esse contrato no controller de arquivos e instalador de bibliotecas; o helper estático permanece somente como ponte para o framework H5P legado.
- Corrige a configuração exportada do campo H5P: Drupal 11 exige que `field.storage.*` contenha `settings` como array, mesmo quando o campo não define ajustes próprios. A validação de instalação real no Runtime encontrou o dado ausente.
- Corrige a localização PSR-4 das classes do editor e adapta `H5PEditorWidget` à assinatura atual de `WidgetBase`; URLs AJAX H5P agora mantêm placeholders compatíveis com os requisitos de rota e enviam `token` como query string.
- Corrige `aculta_image.PackagedAssetPublisher`: o FileSystem pode resolver o caminho de destino mesmo sem arquivo criado; o publisher agora só compara hash quando existe arquivo regular. Também normaliza o ID de arquivo gerenciado antes de registrar proveniência.
- Runtime `cursos.aculta.toca.net.br`: H5P habilitado após inspeção do schema/configuração, pacote benigno `H5P.HelloWorld-1.0` instalado para smoke test e assets estáticos Composer publicados via `aculta_image`. Kernel H5P: 10 testes/173 asserções (40 deprecações de Core/contrib); Unit H5P: 12/52; Unit Image: 20/71 (8 deprecações PHPUnit); gates de imagem, pacotes, slugs e Drupal 11 passaram.
- O ciclo visual de edição continua pendente: a autenticação única do teste não estabeleceu sessão e a navegação canonicalizada para `aculta.toca.net.br` respondeu “Acesso negado”. Runtime permanece em validação; produção não foi alterada.
- Corrige o formatter H5P no Drupal 11: `AccessResult` não fornece `applyTo()`, então o acesso negado agora retorna `#access: false` com metadados aplicados por `CacheableMetadata`. Kernel cobre o comportamento e os contextos de cache.
- Corrige as rotas de arquivos H5P e do adapter do editor para caminhos com múltiplos segmentos. Drupal exige que cada placeholder ocupe um segmento completo; rotas capturadas por `{file_path}` com barras nunca chegavam ao controller. Os segmentos aceitos têm alfabeto restrito e quantidade/comprimento máximos. Kernel confirma resolução de biblioteca aninhada, mídia aninhada e argumentos `single-library`; GET HTTP de `library.json` passou com `200 application/json` no Runtime.
- A autenticação única do teste continuou vazia no navegador; acesso visual ao editor e ciclo de criação/salvamento permanecem pendentes, sem impacto em produção.
- Corrige a chamada de metadados do editor: o H5P usa a ação `libraries` também para buscar semântica individual por segmentos de rota; a resposta passa pela autorização da biblioteca. O editor visual no Runtime carregou `H5P.HelloWorld 1.0`, e o submit serializou biblioteca e parâmetros no formulário Drupal. A gravação foi bloqueada pela validação do curso de teste sem aulas; nenhuma nova atividade foi persistida.
- Kernel H5P: 10 testes/180 assertions; Unit H5P: 12/52. As 40 deprecações Kernel são de Core/contrib. Runtime de teste: smoke visual do editor e requisições de listagem/semântica concluídos; salvar e reproduzir estudante ainda pendentes.
- RUNTIME STATUS: DEFERRED para salvamento/reprodução fim a fim; produção não foi alterada.
- Preserva os idiomas `language` e `default-language` enviados pelo editor na busca de semântica e valida o formato antes de chamar o H5P Editor. A URL visual do Runtime confirmou o parâmetro `language=pt-br`.
- Remove o cache paralelo das bibliotecas Drupal dinâmicas e invalida a descoberta nativa do Core após instalar ou remover biblioteca. Kernel confirma que uma definição previamente vazia passa a incluir a nova biblioteca após a importação, sem rebuild global. Kernel H5P: 10 testes/182 assertions; Unit H5P: 12/52; 40 deprecações de Core/contrib.
- Define explicitamente o serviço `H5PLibraryHooks` com suas dependências atuais após detectar no Runtime que o container compilado fornecia o antigo primeiro argumento (`cache.default`) à nova assinatura do construtor. Rebuild de cache em `conta` e `cursos`, construção do hook e carregamento visual da semântica HelloWorld passaram; `/entrar` responde 200. Erros antigos no watchdog são de antes do rebuild; não há erro novo de H5P após a correção.
- Corrige a serialização do formulário Group: propriedades `private readonly` do `H5PEditorWidget` eram omitidas pelo `DependencySerializationTrait` herdado de Core, deixando serviços não inicializados ao recuperar formulário em cache. Propriedades protegidas mutáveis são serializadas por ID de serviço; Kernel confirma restauração de acesso ao curso, acesso à biblioteca e usuário atual. Kernel H5P completo: 11 testes/204 assertions; 40 depreciações Core/contrib. Unit H5P: 12 testes/52 assertions.
- Runtime `cursos`: após rebuild, `H5PLibraryHooks` e `H5PEditorWidget` instanciam e `/group/13/edit` renderiza sem o fatal de serviço não inicializado. O salvamento H5P ainda falha na validação de parâmetros vazios; artefatos incompletos do curso de smoke foram removidos via Entity API. Runtime de salvamento/reprodução: DEFERRED.
## Continuação F10 — DI do adaptador H5P e objetivos F15 — 2026-10-19

- O adapter de plataforma H5P passa a receber por DI banco, usuário, config,
  estado, entity manager, module handler, cache, cliente HTTP, messenger,
  relógio, idioma, geradores de URL/assets, storage privado, file storage,
  library discovery e otimizadores. A ponte estática exigida pelo framework
  legado foi reduzida a um único acesso ao serviço `aculta_lms_h5p.framework`.
- Unit H5P: 23 testes/67 assertions. Kernel H5P no MariaDB dedicado: 14
  testes/263 assertions; 40 depreciações upstream de Core/contrib, nenhuma
  falha. PHP 8.5 não validado neste ambiente.
- F15 passa a incluir objetivos explícitos para integração LMS/H5P com
  `aculta_image`, o leitor existente `aculta_pdf_viewer` (pedido como
  `aculta_pdf_reader`) e `aculta_forum`; a parte Forum aguarda API executável e
  testes de isolamento. Não foram criados storages ou módulos duplicados.

## Registro do adapter estático H5P via DI — 2026-10-19

- Remove `\Drupal::service()` de `H5PDrupal::framework()`. O serviço registra
  a própria instância DI para a API estática legada do framework; a ausência
  de inicialização falha com mensagem clara em vez de recorrer ao container
  global.
- Hooks, serviços, formulários, controllers e plugins de entrada H5P recebem
  o adapter por DI, incluindo widgets serializados pelo Form API. O Kernel
  verifica o registro e a restauração do serviço após serialização.
- Unit H5P: 23 testes/67 assertions. Kernel H5P: 14 testes/266 assertions;
  40 depreciações upstream de Core/contrib. Runtime `cursos`: cache rebuild e
  montagem do formulário administrativo (36 elementos) passaram; sem updates
  de banco. Gates Drupal 11+, imagem e slugs passaram.


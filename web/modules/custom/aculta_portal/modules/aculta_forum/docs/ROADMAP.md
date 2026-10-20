# Roadmap — ACULTA Forum

Linha do projeto `aculta_forum`: começou em **0.1.0-dev.1**. Roadmap de escopo
aprovado pelo responsável em 2026-10-19; o estado real de cada entrega fica no
CHANGELOG e nos commits. Dev.1 e dev.2 são documentais/fundacionais; não alteram
Runtime.

## Direção atual de produto: experiência Discourse Ubuntu

O responsável definiu como referência uma experiência parecida com
[discourse.ubuntu.com](https://discourse.ubuntu.com/). O ACULTA adotará os
padrões de leitura e navegação adequados à comunidade, mantendo Drupal nativo,
contextos privados e a identidade visual ACULTA420. A análise detalhada,
referências observadas, limites de escopo e critérios de aceite ficam em
[`DISCOURSE-EXPERIENCE.md`](DISCOURSE-EXPERIENCE.md).

Prioridades para implementação: (1) categorias compreensíveis e atividade
recente; (2) listagem densa e legível, com participantes e filtros; (3) leitura
linear, progresso e resposta contextual; (4) seguir/salvar/denunciar e
notificações; (5) preferências e acabamento mobile/acessível. IA, chat em tempo
real, aplicativo nativo e previews remotos não são requisitos deste roadmap.

As ferramentas de comunidade do Discourse foram pesquisadas em produto e no
código upstream em 2026-10-19. O roadmap incorpora padrões de organização de
tópicos, seleção/movimento de posts, merge/split, avisos de ação, fila de
revisão, flags configuráveis, notas e histórico de moderação. Isso é referência
de produto, não autorização para copiar sua implementação nem seus trust levels;
o contrato ACULTA e seus quatro contextos prevalecem. Ver
[`DISCOURSE-EXPERIENCE.md`](DISCOURSE-EXPERIENCE.md) e
[`MOVE-TOOLS.md`](MOVE-TOOLS.md).

| Versão | Entrega | Estado |
| --- | --- | --- |
| `0.1.0-dev.106` | Rastrear adapter LMS e aplicar permissões Group contextuais | `core.extension` inclui adapter e `gnode`. Importação parcial revisada atualiza quatro Group roles e `lms_classes.settings`; os demais campos dos cinco itens coincidiam com a Config Sync e nenhum outro item foi alterado. Kernel 2/74 PASS, HTTP `/` e `/categorias` 200, tópicos/Comments zero. Sem conteúdo real, acesso Runtime ainda não exercitado; UI, Views e filas pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.105` | Corrigir injeção do serviço e habilitar adapter LMS no Runtime de teste | `#[Autowire]` aponta ao service ID real do `DiscussionContextResolver`; adapter e `gnode` habilitados com sucesso e oito configs default presentes. Unit 13/143 e Kernel 2/74 PASS (38 depreciações Core/contrib/LMS); smoke HTTP `/` e `/categorias` 200; zero tópicos/Comments. Config Sync/roles não reconciliadas; UI, filas e autorização Runtime ainda pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.104` | Provar acesso de Educador à discussão da turma sem membership direto | Kernel LMS deixa curso/turma sob propriedade de outra conta e confirma que Educador membro do curso lê/modera tópico contextual via `grade students`; outsider global continua bloqueado. Adapter dev.12, 2 testes/74 assertions PASS (38 depreciações Core/contrib/LMS). Ativação e filas/UI do LMS seguem pendentes; Runtime DEFERRED. |
| `0.1.0-dev.103` | Homologar visualmente categorias vazias em X11 | Firefox ESR 153.4/geckodriver 0.37.1, GET-only em `/` e `/categorias`, 13/13 verificações por tela nos viewports 390/768/1024 e claro/escuro; banco segue com 0 tópicos/Comments legados. Capturas privadas. Listas e tópicos com atividade aguardam conteúdo efêmero aprovado. |
| `0.1.0-dev.102` | Aplicar configuração padrão do adapter em Kernel | `ConfigInstaller` instala bundle, Comment compartilhado, relationships Group e formatter na fixture LMS/Group; 2 testes/72 assertions PASS (38 depreciações Core/contrib). Não habilita módulo, não usa ModuleInstaller e não toca Runtime. |
| `0.1.0-dev.101` | Verificar coerência entre adapter e Config Sync | Unit confirma dependências de relationship types nos quatro Group roles, permission de leitura contextual e herança `grade students`; não habilita módulo nem importa configuração. Próximo: testar a instalação com o grafo completo de dependências. |
| `0.1.0-dev.100` | Validar cacheabilidade do acesso LMS | Kernel confirma variação de acesso por usuário/permissões e dependência de cache do tópico para o provider de contexto; nenhum comportamento no Runtime. Adapter ainda aguarda revisão integrada de acesso e filas. |
| `0.1.0-dev.99` | Configurar Group roles do LMS para tópicos | Config Sync limita visualização Group Node de tópico a membership de curso/turma e Educadores com role contextual; educator permission herda para turma. Kernel lê os quatro Group role configs reais e confirma estudante/Educador, outsider global não autorizado, Comment e moderação. Config Sync não importado. |
| `0.1.0-dev.98` | Integrar Comment real à autorização contextual LMS | Kernel persiste resposta publicada na turma, prova escrita de estudante, moderação exclusiva de Educador e bloqueio quando o campo está fechado. Kernel adapter 2/57 (38 depreciações), Unit adapter 11/123 e Forum 87/556 PASS. Runtime DEFERRED; filas e habilitação continuam pendentes. |
| `0.1.0-dev.97` | Herança de permission Educador nas turmas | Config Sync mapeia `grade students` do curso para visualização Group Node de tópicos. Kernel real com `ClassPermissionCalculator`: Educador permitido; outsider e conta apenas com papel global `lms_teacher` negados. 1 teste/24 assertions PASS, 37 depreciações Core/contrib. Adapter não habilitado e Config Sync não importado no Runtime. |
| `0.1.0-dev.96` | Corrigir acesso LMS e cobrir escrita/moderação de Comment | Unit cobre estudante matriculado, Educador com `grade students`, permissão de comentar e status aberto/fechado. Corrige combinação AccessResult que negava estudantes e torna a moderação explicitamente contextual. Runtime DEFERRED; adapter ainda desabilitado. |
| `0.1.0-dev.95` | Provar acesso Group Node no Kernel | MariaDB `aculta_simpletest` com Group 3.3.5/gnode: membro de curso com permission contextual acessa Node de tópico; outsider recebe forbidden. 1 teste/17 assertions PASS; 17 depreciações Core/Group/contrib. Não instala o adapter nem altera Runtime. |
| `0.1.0-dev.94` | Configurar bundle e vínculos Group do LMS | `aculta_lms_topic`, campo `field_aculta_discussion`, displays e relationships Group para curso/turma entram como install config do adapter. Unit valida storage compartilhado, bundle, display e IDs ≤32. Roles Group, `lms_classes.settings`, instalação Kernel e habilitação Runtime seguem pendentes. |
| `0.1.0-dev.93` | Bloquear acesso direto a tópico LMS não agrupado | `entity_access` nega tópico sem relação Group única e turma sem curso pai; Group continua dono das permissões quando a relação é válida. 3 testes Unit novos cobrem tópicos sem relação, curso válido e turma sem curso pai. Adapter continua desabilitado, sem configuração e sem Runtime. |
| `0.1.0-dev.92` | Fundação do adapter privado LMS | Adicionado `aculta_forum_lms` opcional com provider para `aculta_lms_topic`, resolução única de Group curso/turma, access fail-closed e moderação por `grade students`. Sem configuração instalável, matriz Kernel ou uso no Runtime; módulo permanece desabilitado. RUNTIME STATUS: DEFERRED. |
| `0.1.0-dev.91` | Homologação X11 visual da busca pública | Suíte Firefox X11/BiDi somente leitura: 20/20 em 390/768/1024 CSS px, claro/escuro; formulário enquadrado, estado vazio, CAPTCHA ausente e contraste 12,40:1/12,23:1. 0 tópicos/Comments preservados. Comparação visual completa do fórum segue planejada. |
| `0.1.0-dev.90` | Validar busca textual combinada e relevância | Search API DB Kernel prova filtro textual + categoria, pontuação de título acima de corpo e ordenação padrão por relevância; suíte Kernel 26/513 PASS, com 19 depreciações de Core/contrib. Sem conteúdo Runtime. Busca com resultados publicados segue DEFERRED para manter o Fórum limpo. |
| `0.1.0-dev.85` | Formulário de mover tópico nega Growroom histórico | Kernel com conta administrativa prova que serviço e Form API negam movimento e preservam categoria histórica; 3 testes/71 assertions PASS, 5 depreciações de Core/Domain. PHPCS, lint e gates Portal PASS. Runtime DEFERRED. |
| `0.1.0-dev.86` | Busca pública contextual do Growroom420 | Índice Search API/View `/pesquisar` somente para tópico público; acesso/status e Domain canônico em query fail-closed. Runtime de teste: ativo, zero itens; GET e consulta vazia HTTP 200. 5 testes/17 assertions, suíte Forum Unit 87/527 e gates PASS. X11 1024 parcial: render visível, mas banner de validação CAPTCHA sem submissão; detalhes em `TEST-MATRIX.md`. |
| `0.1.0-dev.87` | Filtro hierárquico de categorias na busca pública | Search API indexa ID inteiro do termo; Form API converte o filtro em select com rótulos Taxonomy atuais, Entity Access e cacheabilidade. Exceção CAPTCHA limitada a Views públicas somente leitura. Unit 87/553 e gates Portal PASS; Runtime da busca HTTP 200, categorias 12, Fórum vazio; X11 desktop 1024 corrigido e RDM 320×480 parcial. Gate Foundation reconhece .118, mas encontra `email-wrap.html.twig` da linha Mailer fora da allowlist. Detalhes em `TEST-MATRIX.md`. |
| `0.1.0-dev.88` | Kernel prova filtro Search API por ID de Taxonomy | MariaDB dedicado cria tópicos com categorias raiz/filha e comprova que a condição por ID inteiro recupera somente a entidade atribuída ao termo selecionado. 1 teste/17 assertions PASS; suíte Kernel 25/495 e Unit 87/553 PASS, com 19 depreciações Core/contrib. Search API 1.41 usa relevância decrescente quando a View não define sort. Sem alterações no Runtime. |
| `0.1.0-dev.89` | Estado vazio contextual na busca | View orienta quem não encontra tópicos e aponta para `/categorias`; ACULTA420 usa o padrão visual de estados vazios do Fórum. Runtime backup/import parcial de uma View, rebuild e busca GET com categoria 200; HTML sem CAPTCHA, 0 tópicos/Comments. Firefox/X11 1024 PASS. Unit 87/556, SDC/design/shell PASS. Foundation encontra template Mailer fora da allowlist. Nenhum conteúdo alterado. |
| `0.1.0-dev.84` | Negar formulário de movimento sem acesso ao tópico | Form API lança `AccessDeniedHttpException` antes de expor destinos quando a conta tem permissão de mover, mas não acesso à fonte. Kernel total 24/476 e Unit Forum 82/500 PASS; 19 depreciações Core/contrib. PHP CLI 8.4.26 (baseline 8.5); PHPCS e gates Portal PASS. Runtime DEFERRED. |
| `0.1.0-dev.83` | Exigir acesso ao tópico fonte além da permissão de mover | Kernel MariaDB: permissão de mover sem `access content` não expõe destino nem altera o host; 18 testes/365 assertions PASS, 19 depreciações Core/contrib. Regressão total Forum: Unit 82/500, Kernel 24/475 PASS. PHP CLI 8.4.26 (baseline 8.5); lint, PHPCS e gates Portal PASS. Runtime DEFERRED. |
| `0.1.0-dev.82` | Exigir permissão dedicada para mover comentários | Kernel MariaDB: sem `move aculta forum comments`, acesso e destinos negados e host preservado; 17 testes/348 assertions PASS, 19 depreciações de Core/contrib. PHP CLI 8.4.26 (baseline 8.5); lint, PHPCS e gates Portal PASS. Runtime DEFERRED. |
| `0.1.0-dev.81` | Rejeitar movimento a destino fechado ou de outro purpose | Kernel MariaDB comprova bloqueio dos dois casos e preserva o host original; a fixture usa Domains Forum e main. Classe completa 16 testes/331 assertions PASS, com 19 depreciações; PHP CLI 8.4.26 abaixo da baseline 8.5. Revalidação X11 administrativa 19/19; Forum/filas 200, rota Revista antiga 404, sem 500; 768 px sem overflow e excesso conhecido de 3 px na tabela a 390 px. Nenhum conteúdo alterado. |
| `0.1.0-dev.80` | Preservar traduções ao mover comentário | Kernel MariaDB confirma que um Comment publicado mantém o idioma original e a tradução `fr`, inclusive assunto, corpo e status, ao mudar de tópico. Suite do mover 15 testes/315 assertions PASS; 19 depreciações Core/contrib. PHP CLI 8.4.26 (baseline 8.5); Runtime DEFERRED. |
| `0.1.0-dev.79` | Preservar recibos de leitura ao mover subárvore | Kernel MariaDB com History prova que o estado “não lido” acompanha o Comment para o tópico destino: contagem nova de origem 1→0 e destino 0→1. Os recibos uid/Node permanecem inalterados. 14 testes/292 assertions PASS; 19 depreciações Core/contrib; PHP CLI 8.4.26 abaixo da baseline. Runtime DEFERRED; mover segue desativado por padrão. |
| `0.1.0-dev.78` | Preservar denúncias ao mover subárvore | Smoke X11 autenticado comprovou Flag `sinalizar_comentario` no mesmo CID, owner uid 5 e presença contínua na fila `aculta_user.report_queue`; árvore ramificada preservou estados pendente/rejeitado, `pid`/`thread`. 10/10 em 390/768/1024 CSS px; Forum e Flagging de teste limpos. Flag ativa alinhada à configuração versionada por importação parcial após backup. O mapper de tradução do Mailer bloqueou o primeiro rebuild; depois que sua rota foi adicionada em commit separado, `drush cr` PASS e as rotas do Mailer/Fórum foram resolvidas. Nenhum arquivo Mailer entrou nos commits de Fórum. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.77` | Preservar pendência e rejeição ao mover árvore | X11 autenticado moveu quatro Comments em árvore ramificada: raiz pendente, um Comment rejeitado e dois pendentes; estado `field_aculta_rejected`, CIDs, autores, textos, `pid`/`thread` preservados. 10/10 em 390/768/1024 CSS px, fila Messenger 0, Fórum vazio após cleanup. Comentários publicados/aprovação por workflow permanecem pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.76` | Isolar fila Messenger nos smokes do Fórum | 24 eventos da fila de publicação apontavam apenas para Nodes QA removidos; processados pelo worker oficial após confirmação de 24/24 alvos ausentes. Fila voltou a 0, sem alterar os 3 avisos existentes. Roteiro agora exige fila limpa, marca Nodes fixture como sincronizando no insert e verifica baseline após cleanup. X11 da árvore ramificada repetido 10/10 PASS. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.75` | Homologar movimento de árvore ramificada | Runtime de teste: formulário autenticado moveu quatro Comments pendentes — raiz, dois ramos diretos e descendente aninhado — via CSRF. Preservados CIDs, autoria, texto, status, `pid`, `thread` e host; 10/10 verificações, X11 sem overflow em 390/768/1024 CSS px. Cleanup deixou 0 tópicos/Comments, 12 termos, 3 avisos e configuração fail-closed ausente. Ramificação mista/publicada, History, flags, anexos, notificações e concorrência HTTP continuam pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.74` | Homologar movimentação de subárvore com dois níveis | Runtime de teste: X11 autenticado moveu uma raiz pendente com dois descendentes pendentes via Form API/CSRF; CID, autor, conteúdo, status, `pid`, `thread` e host foram preservados. 10/10 verificações, sem overflow em 390/768 CSS px; cleanup reverso retornou a zero tópicos/Comments e manteve 12 termos/3 avisos; configuração fail-closed ausente. Ainda não cobre ramificação, estados mistos, History, flags, anexos, notificações ou concorrência HTTP. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.73` | Homologar movimentação de comentário no formulário | Runtime de teste: instâncias `field_autor_legado` ausentes foram alinhadas às duas configs versionadas após backup; somente duas configs parciais. X11 autenticado ativou temporariamente a opção fail-closed e moveu um Comment pendente com CSRF; CID/autor/texto/estado/thread preservados. 10/10 em 390/768/1024 CSS px, sem overflow; limpou fixtures e restaurou config ausente. Fórum final em zero, 12 categorias e 3 avisos preservados. Unit 82/500; Kernel 13/272 (19 depreciações Core/contrib); PHPCS e gates Portal 975/fronteira 1419/mapa 152 PASS. Corrigida pluralização singular. Subárvore real, History, flags, anexos, Messenger com seguidores e concorrência HTTP pendentes. PHP CLI 8.4.26 abaixo da baseline 8.5. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.72` | Corrigir conformidade PHPCS do contrato de contexto | Removidas violações de documentação/namespace em 7 arquivos `aculta_forum`; sem mudança comportamental. Unit completo 82/500 e PHPCS Drupal/DrupalPractice PASS. Runtime DEFERRED. |
| `0.1.0-dev.71` | Validar a fila administrativa em telas estreitas | Firefox/X11 autenticado, 19/19 PASS: filas e rotas administrativas continuam corretas; 768 CSS px sem overflow, 390 px com excesso de 3 px restrito à tabela responsiva. Nenhuma entidade alterada; evidências privadas. Linhas reais ainda precisam de revisão responsiva. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.70` | Localizar cabeçalhos e estado vazio das filas administrativas | Unit 18/227 PASS. View ativa no Runtime de teste alinhada ao Config Sync após backup privado; sem drift do alvo. Firefox/X11 autenticado: 15/15 PASS, quatro filas respondem e exibem “Atualizado”, “Operações” e “Não há comentários disponíveis.”. Fórum permaneceu limpo. Linhas vazias não permitem confirmar exclusão cruzada com conteúdo real. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.69` | Smoke X11 das áreas administrativas separadas | Firefox/X11 autenticado, 11/11: Conteúdo e Fórum 200; agregada, publicadas, rejeitadas e aguardando moderação 200; Revista consolidada 200; rota antiga Revista/comentários 404. Fórum permaneceu com 0 tópicos/Comments. Listas vazias limitam a prova de exclusão cruzada com conteúdo real. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.68` | Completar regressões de teclado da suíte visual X11 | Firefox/X11 e WebDriver Actions: Enter anuncia a próxima mensagem; ArrowRight avança `aria-valuetext` sem ruído; Início/Fim e estados disabled corretos. Suíte completa 25/25 nos viewports de dev.67. Nenhum dado gravado. RUNTIME STATUS: PARTIAL pelo overflow preexistente da página e ausência de leitor de tela/History autenticado. |
| `0.1.0-dev.67` | Suíte visual X11 reproduzível para progresso da discussão | Firefox visível/X11 e WebDriver BiDi: 20/20 checks em 320/360/390/500/900 CSS px; DOM sintético, ação History `.new`, anúncio e capturas claro/escuro fora do checkout. Nenhuma gravação no Runtime. A página base apresenta overflow existente do offcanvas do carrinho; a suíte confirma que o Fórum não o amplia. History real autenticado/leitor de tela pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.66` | Ir à primeira resposta não lida dentro da discussão | Usa somente `.new`, classe aplicada pelo History aos Comments visíveis após comparar timestamps por usuário; o botão é ocultado quando nenhum comentário marcado está renderizado. MutationObserver acompanha a classe sem armazenar estado. Firefox ESR/X11 e BiDi em 320/360/390/500/1250 CSS px: botão, alvo, remoção do marcador, anúncio e layout claro/escuro verificados; nenhum conteúdo salvo. Gates direcionados PASS. History autenticado real/leitor de tela pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.65` | Preservar anúncio da ação explícita durante scroll suave | X11/BiDi: teclado Enter em Próxima mantém o alvo “Mensagem 4 de 6” em `aria-live=polite` durante 250 ms apesar da posição visual ter avançado para 6/6; após 1,2 s volta a `off` e sincroniza sem anunciar. `node --check` PASS. Leitor de tela real pendente. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.64` | Tornar anúncios da navegação de progresso não intrusivos | Estado visual acompanha rolagem sem live announcement; botão anuncia ação, slider expõe `aria-valuetext`. Firefox no Runtime confirmou rolagem, slider e botão; revalidação X11/BiDi em 320/360/390/500/1250 CSS px confirmou rótulos, slider amplo, contador visível e ausência de overflow após ACULTA420 0.6.0-dev.114/.115. Forum Unit 82/497 e gates direcionados PASS. Leitor de tela real pendente. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.63` | Navegação de progresso no fluxo de comentários | Portal marca somente `field_aculta_discussion` em Node full e a biblioteca opera nos Comments renderizados, sem storage/acesso paralelo. Unit 82/497, PHPCS, syntax JS e gates Portal/tema PASS. Runtime `toca`: cache rebuild, publicação HTTP 200, marcador + behavior agregado. X11 a 1250/500 com DOM sintético no local real do campo: avanço/ArrowRight para mensagem 2/6, IntersectionObserver acompanha leitura até 4/6, 4 controles, claro/escuro e sem overflow; Fórum segue vazio. Offset sticky do tema dev.111 revalidado a 500 px; teste BiDi 390 CSS px revelou sobreposição e tema dev.112 validado em BiDi/X11 390 × 844: fluxo static, sem sobreposição e 390 px sem overflow. Comments reais/leitor de tela/AT pendentes. RUNTIME PARTIAL. |
| `0.1.0-dev.62` | Corrigir filas de moderação e continuar revisão visual Discourse | Core 11.4 indexa a relação Node da View pelo alias; o hook agora resolve e valida a relação, mantendo fail-closed para acervo histórico. Unit Forum 79/484, Kernel moderação User 7/84, PHPCS e gates Drupal 11+/fronteira/mapa PASS. Runtime `toca`: a View pendente incluiu o Comment do Fórum e excluiu o da Revista; fixtures removidas, estado final 0 tópicos/Comments, History 24 e flaggings 2. X11 comparou a thread a 1270/500 px sem overflow; timeline/progresso do Discourse permanece no escopo visual. Fila PASS; homologação completa PARTIAL. |
| `0.1.0-dev.61` | Corrigir o Batch de marcar discussões como lidas entre purposes | A rota progressiva do Batch Core pertence ao purpose `main` por política administrativa central. O callback valida a conta capturada e executa a consulta/escrita dentro de `runInPurpose('forum')`, preservando Node access e History por usuário; o formulário retorna à raiz pública do Fórum. Unit 78/481, Kernel 1/12 assertions, PHPCS e gates passam; X11 autenticado confirmou 1 leitura e retorno à raiz. Fixture removida; Runtime final: 0 tópicos/Comments, 24 History e duas flaggings preexistentes. RUNTIME STATUS: PASS no escopo; cinco deprecações do Domain contrib e CLI PHP 8.4.26 abaixo da baseline 8.5. |
| `0.1.0-dev.60` | Inspeção visual das listas e thread de descoberta no X11 | Anônimo no Runtime `toca`: fixtures 0/2/3 Comments confirmaram filtros e ordenação. RDM em 390 × 670 confirmou raiz/listas sem overflow; modo escuro local inspecionado e navegação por teclado manteve foco visível. Thread com uma resposta comparada ao Ubuntu Discourse em 1270/500 px, sem overflow; diferenças de timeline/progresso e navegação registradas para dev.11. Capturas privadas e cleanup em `TEST-MATRIX.md`; leitor de tela, thread em 390 px e ações autenticadas permanecem pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.59` | Homologar leitura em lote sem alterar histórico alheio | Serviço Runtime contou uma fixture pública elegível, `markBatch()` escreveu apenas History uid 5/NID 410 e a linha exata foi removida junto com o Node/alias. Cache rebuild, raiz e categorias PASS; histórico voltou ao total anterior (24), 0 tópicos/Comments e as duas Flags de avisos preservadas. A rota, confirmação e processamento Core Batch via navegador seguem pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.58` | Ocultar ações Flag dos cartões teaser | Corrigida regressão descoberta na View autenticada de não lidos: `field_extra_fields` do Flag adiciona pseudocampos aos modos teaser por padrão. Sync/install ocultam seguir/salvar no teaser e Unit valida equivalência; Runtime 11.4.10, render API, Firefox/X11 e limpeza das fixtures PASS. O detalhe `full` preserva os controles e o teaser mantém contagem de respostas novas. RUNTIME STATUS: PASS no escopo. |
| `0.1.0-dev.57` | Ações Flag de seguir/salvar no tópico público | Pseudocampos nativos colocados no modo full depois da categoria; teaser permanece sem ações. PHPUnit 18/221, PHPCS, Portal 975, fronteira 1415, mapa 152, tema SDC 535 e design 2097 PASS. Importação parcial do display após backup; render uid 5 e Firefox/X11 autenticado a 1024 px confirmaram access e controles. Seguir/salvar foram ativados e revertidos pela interface (`0 → 1 → 0`); fixture removida e Runtime terminou com 0 tópicos, Comments e flags do Fórum. Firefox Responsive Design Mode autenticado a 390 × 670 px confirmou botões lado a lado sem overflow horizontal visível. Modo escuro forçado somente no DOM local foi inspecionado a 1024 px; preferência do Runtime não foi alterada. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.56` | Retirar bundle contribuído residual das Flags seguir/salvar | `seguir_topico` e `salvar_topico` agora apontam somente para `aculta_forum_topic`; defaults e Config Sync alinhados; teste exige lista exata (17/215), PHPCS e YAML PASS. Importação parcial das duas Flags após backup, cache rebuild e `config:status` dos alvos PASS; zero flaggings antes/depois, fórum limpo, raiz e `/categorias` HTTP 200. Nenhum dado excluído. RUNTIME STATUS: PASS para a limpeza. |
| `0.1.0-dev.55` | Propagar cacheabilidade do formato de texto citado | O hook usa `TextProcessed` para compor o quote e propaga tags/contextos/max-age para o formulário. PHPUnit 26/240, PHPCS, Portal 975 e fronteira 1415 PASS; Runtime cache rebuild e resolução do hook/serviços PASS. X11 conferiu a lista de tópicos em 1024/768/390 px e o detalhe em 390 px com três tópicos QA temporários; todos removidos depois. Estado final Runtime: 0 tópicos, 0 Comments, fila Messenger vazia e aliases removidos. Ação Citar/formulário autenticado aguardam conta QA confirmada. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.54` | Agrupar subcategorias por categoria principal | View ordena por nome do pai, depois peso e nome. PHPUnit 17/217 e gates Portal 975, fronteira 1415, mapa 152, pacotes 26, slugs 378 e imagem PASS. Import parcial da View com backup privado; runtime exibiu 4 raízes antes dos 8 filhos agrupados, `/categorias` 200, zero tópico/Comment. Firefox/X11 1024/768/320 sem overflow. RUNTIME STATUS: PARTIAL; tópicos reais/autenticado pendentes. |
| `0.1.0-dev.53` | Citar resposta pública com segurança contextual | `ForumQuoteHooks` oferece ação apenas para Comment publicado, legível e respondível no contexto resolvido; usa rota Core `comment.reply` e só preenche o pai exato da requisição. Builder tira marcação, limita 1.200 caracteres e escapa autoria/texto; 9/18 Unit e PHPCS PASS. LMS/Wiki aguardam seus providers. Runtime `toca`: cache rebuild, resolução de serviços e Fórum/categorias HTTP 200; zero tópicos/Comments preservados. Link/formulário com Comment real pendentes; RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.52` | Categorias visíveis junto à atividade recente | View `block_landing` e bloco CTools apenas na raiz do purpose Fórum; categorias principais filtradas, página `/categorias` permanece completa. Config parcial Runtime, cache rebuild, 17/215 Unit, Foundation 364, SDC 535, Design 2097, Portal 975, fronteira 1411, mapa 152 e Firefox/X11 1024/768/390 claro + 1024/390 escuro PASS. Sem conteúdo criado; teclado, leitores de tela e páginas com tópicos pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.51` | Isolar moderação/denúncias do Fórum e excluir o acervo histórico | Flag 5 combina hook e permissões com OR; permissão dedicada, hook contextual e guard de `flagging_presave` limitam denúncias a tópicos/comentários públicos contemporâneos. Fila de denúncias exclui legado/outros contextos; Views pendente/rejeitado excluem marcador legado no tópico e comentário, e falham fechado sem campo/relação. Import parcial do papel e cache rebuild PASS no Runtime; smoke autenticado com dados pendente. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.50` | Desinstalar e remover Drupal Forum contrib | Runtime `toca`: uninstall oficial PASS; zerados bundles/configs e tabelas antigas; seis instâncias e storage `field_discussao` vazios removidos via Field API; Composer removeu apenas `drupal/forum`, mantendo `history`. Cache rebuild PASS; Fórum/Revista 200, Conta anônima 302 esperado. Config Sync integral não aplicado; configuração dirigida, revisão de flags e smoke autenticado pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.49` | Remover realms de acesso da taxonomia Forum contrib | Classes, subscriber, permissões, field `field_visibilidade` e grants de papéis removidos; F-D14 substituída por Growroom público/LMS Group. User Unit 52/175; gates Portal 970, fronteira 1376 e mapa 151 PASS. Cache rebuild e raízes HTTP 200. Config/permissions antigas ainda ativas no Runtime até limpeza dirigida/uninstall. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.48` | Retirar referências de bundles contrib em guards e defaults User | Guard de autoria histórica cobre apenas `aculta_forum_topic`/`aculta_discussion_post`; removidos defaults `field_discussao`; Portal reconhece somente tópico ACULTA. User Unit 61/192, gates Portal/fronteira/mapa PASS; Runtime sem alteração. RUNTIME STATUS: DEFERRED. |
| `0.1.0-dev.46` | Remover consumidor contrib da Revista | Magazine `0.1.0-dev.15`: removida dependência `forum:forum`, abertura editorial de Nodes `forum`, lookup legado e Config Sync de `field_discussao`; conversor segue movendo Comments `field_aculta_discussion`. Unit 7/31; Portal/fronteira/mapa PASS. Runtime `toca`: cache rebuild, serviço Magazine e raiz HTTP 200; nenhuma importação. Smoke autenticado/conversão com comentário real pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.45` | Limpeza do conteúdo antigo do Fórum no Runtime de teste | F-D33 cumprida: Entity API removeu 30 Nodes `forum` e 8 termos `forums`; Comments `comment_forum` eram zero; 12 vínculos `field_discussao` foram retirados sem alterar o conteúdo das publicações. Views/tabelas do Forum, aliases, revisões, flaggings e referências ficaram zerados; as 12 categorias `aculta_forum_categories` permanecem. Backup integral atual, `0600`, `gzip -t` PASS; restore isolado DEFERRED. Validator de uninstall sem blockers; dry-run ainda tem alterações de config/roles e consumidores a retirar antes do uninstall/Composer. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.44` | Navegação da lista de categorias para discussões filtradas | View Core inclui TID oculto e emite links com TID real à raiz filtrada; ACULTA420 mostra hierarquia/descrição em cartões SDC. Unit Forum 54/382 e gates PASS. Runtime de teste: importação parcial pontual, `/categorias` 200 com 12 links e `/?categoria=76` 200 com filtro selecionado; `config:status` da View sem diferença. Browser visual DEFERRED. Pesquisa contrib em [`CONTRIB-PORT-RESEARCH.md`](CONTRIB-PORT-RESEARCH.md). |
| `0.1.0-dev.43` | Atualizar metadados de teste para atributos PHPUnit | Unit Forum 54/379 PASS; removeu 3 depreciações em metadata de docblocks. PHPCS PASS; Runtime DEFERRED. |
| `0.1.0-dev.42` | Navegação de descoberta na raiz pública | Firefox confirmou a composição das tabs; Runtime HTTP: raiz e abas existentes 200, `/discussoes` 404. HTML confirma Discussões ativa e `aria-current="page"`; Access de Não lidos permanece condicional. Unit 3/19 e gates direcionados PASS. Sem fixtures. |
| `0.1.0-dev.41` | Conferir se categorias de publicações dependem do vocabulário Forum | Consultas Entity API read-only confirmaram que 12/12 publicações ligadas a tópicos antigos já têm categoria editorial direta igual ao mapeamento do Forum. Nenhuma migração editorial é necessária; formulário de Comment e limpeza seguem pendentes. |
| `0.1.0-dev.40` | Inspecionar uninstall do Forum contrib | Código 1.1.3, validator e teste funcional upstream documentados; confirmou bloqueios por Nodes/termos, field storages afetados e dependência editorial compartilhada. Apenas leitura/documentação; nenhuma alteração Runtime. |
| `0.1.0-dev.39` | Remover apresentação presa ao Forum contrib | `ForumTopicPresentationHooks` removido; Unit `aculta_user` 60/175 e gates PASS. Runtime tem 0 `comment_forum`; Node antigo renderizou e respondeu HTTP 200 após redirect; conteúdo inalterado. |
| `0.1.0-dev.38` | Aposentar geração de pastas Forum pela Revista | Updates Magazine 10001/10002 sem escritas, serviço/resolver e configs de instalação removidos. Unit 8/32, gates PASS; Runtime schema 10003, sem update persistente; render HTTP 200. Os vínculos antigos ficam preservados até a migração dos demais leitores. |
| `0.1.0-dev.37` | Restore point da retirada | Dump de banco `aculta_runtime` criado fora do Git, modo 0600, `gzip -t` PASS; checksum e limites documentados. Sem restore de prova, backup de arquivos ou exclusão. |
| `0.1.0-dev.36` | Retirar hook residual da Revista | `ForumTopicHooks`/`comment_links_alter` removido após Runtime confirmar zero `comment_forum`; Unit Magazine 7/30 PASS e render full/HTTP 200. Mantidos os leitores/conversores de `field_discussao` até limpeza dos 12 vínculos antigos. |
| `0.1.0-dev.35` | Remover fallback editorial do Forum contrib | `aculta_user::DiscussionHooks` removido; publicações usam o Comment direto. Runtime confirmou 8 referências antigas em `article` e 4 em `blog` e campo novo nos 12 Nodes; nenhum dado foi alterado. `drush cr`, render full e HTTP editorial (200) PASS; falta testar Comments reais e remover vínculos/Nodes na fase operacional segura. |
| `0.1.0-dev.34` | Pesquisa contrib e integridade de movimento | Código de `comment_mover` alpha inspecionado; regressão ACULTA para dois ramos com netos. Kernel MariaDB 13/272 PASS, sem instalar dependência ou copiar algoritmo. Runtime somente leitura: 8 vínculos antigos em `article`, 4 em `blog`; nenhum dado alterado. |
| `0.1.0-dev.33` | Mover comentário e subárvore entre tópicos do Fórum | Serviço/Form/ação administrativa, permissão específica, locks por tópico compartilhados com gravação/exclusão normal, revalidação de pai/host, atualização de thread/estatísticas e transação/rollback. Kernel MariaDB original: 12/246 PASS; dev.34 acrescenta ramos irmãos e netos. Forum Unit 51/360 PASS; Messenger e Game têm testes direcionados contra eventos duplicados. Runtime `toca`: Drupal 11.4.10, rota/permissão/serviço resolvidos, configuração ausente (fechada); GET com Comment inexistente respondeu 404. Em limpeza separada dos fixtures, 8 tópicos `[TESTE]` foram removidos; 0 tópicos/Comments novos permanecem e 12 termos da árvore inicial foram preservados. No snapshot desta entrega, o Forum antigo tinha 30 Nodes/12 vínculos editoriais; removidos depois em dev.45 por F-D33. Não há Comment publicado de `aculta_forum_topic` para testar a rota com entidade real. RUNTIME STATUS: PARTIAL. Restam rota com entidade existente/CSRF, teste concorrente entre requisições, History, política de anexos `aculta_image`, evento/fila Messenger, permalink e browser. Evidências em `CHANGELOG.md` e `TEST-MATRIX.md`. |
| `0.1.0-dev.32` | Contagem, atividade e identidade pública do último comentarista na View de tópicos | Campo Core `last_comment_uid` oculto; hook injeta cartão `author_forum` pelo `aculta_user`, sem usar nome de login. Teste de equivalência entre install/sync adicionado; execução e Runtime/browser pendentes. Contagem publicada tem Kernel PASS; ordenação positiva e invalidação após moderação seguem pendentes |
| Não marcada | Contagem e atividade na View de tópicos | Kernel MariaDB confirma `comment_count` em aprovação, despublicação e exclusão; Views Core ordena 3 tópicos por publicados e ignora pendente. Runtime `toca`: fixture com 2 publicados + 1 pendente; Core e View retornaram 2, e Firefox/X11 em 1024 px mostrou “2 respostas” e atividade na raiz. Fixture removida; estado final 0 tópicos/Comments. Ordenação Runtime com vários tópicos e invalidação visual após moderação seguem pendentes. RUNTIME STATUS: PARTIAL. |
| Não marcada | Contagem de respostas em tópicos próprios | `ActivityControllerKernelTest`: 1/12 PASS; Unit `aculta_user` 62/194 PASS; 5 depreciações do Domain contrib. Runtime `toca`: rota `/atividade/forum` sob uid 5 mostrou somente o tópico próprio, exibiu 2 comentários publicados e omitiu 1 pendente; fixture de outra pessoa não vazou. Firefox/X11 1024 e Responsive Design Mode 390 × 670 px sem overflow visível. Fixtures removidas e Runtime ficou com 0 tópicos/Comments. Homologação funcional/Kernel em PHP 8.5 permanece pendente. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.31` | Seguir e salvar tópicos | Flags existentes `seguir_topico`/`salvar_topico` aceitam o bundle ACULTA; links e serviços de Flag confirmados no Runtime (HTTP/browser e alteração de estado pela interface pendentes) |
| Messenger `0.1.1-dev.54` | Avisos de tópicos seguidos | Respostas/aprovações e cada revisão publicada usam fila existente; Messenger revalida conta, Domain e acesso atual de tópico/comentário antes de criar ou mostrar o aviso, contar ou enviar resumo. Bundle antigo e autoria histórica excluídos. Unit 41/145 inclui testes de domínio correto/incorreto e Node access permitido/negado; Runtime read-only PASS; browser autenticado/entrega de aviso pendentes (RUNTIME PARTIAL) |
| `0.1.0-dev.54` | Hierarquia no índice público de categorias | Configuração, query Runtime e X11 PASS: 4 raízes aparecem primeiro; 8 subcategorias ficam ordenadas por categoria pai. `/categorias` HTTP 200; sem alteração dos termos. Capturas 1024/768/320 em `TEST-MATRIX.md`. |
| Não marcada | Lista personalizada de discussões não lidas | View `/discussoes/nao-lidas` usa History contrib com acesso `authenticated`, filtros públicos fixos e sem cache compartilhado; campo `node_new_comments` mostra contagem positiva e liga à primeira resposta nova. Runtime query como uid 5 retornou 7 resultados e anônimo recebeu 403; HTTP autenticado/browser pendente |
| `0.1.0-dev.61` | Marcar todas as discussões públicas acessíveis como lidas | Ação confirmada `/discussoes/marcar-tudo-como-lido`, em Core Batch de 200 itens e History por usuário. A execução central em `main` revalida a conta e envolve acesso/escrita no contexto `forum`; X11 confirmou 1 leitura e retorno à raiz. Kernel inicia em `main`, valida acesso dentro de `forum` e restauração do contexto. Fixture limpa; Runtime final preservou baseline 24 History. |
| `0.1.0-dev.62` | Corrigir resolução da relação Node nas filas pendente/rejeitada do Fórum | Hook agora obtém o alias da relação configurada na View e valida a tabela real. Runtime `toca`: a View pendente retornou o comentário do tópico Fórum e excluiu o comentário da publicação; ambas as fixtures foram removidas. Kernel de moderação PASS. RUNTIME STATUS: PASS para consulta e separação; HTTP autenticado ainda pendente. |
| Não marcada | Descoberta: novas, sem resposta e mais comentadas | Views, filtros e abas implementados. Runtime com fixtures 0/2/3 validou HTTP, filtro sem resposta e ordenação; X11/RDM confirmou cards, viewport 390 na listagem, modo escuro local e foco por teclado. Thread comparada a tópico Discourse em 1270/500 px. Permanecem thread 390 px, leitor de tela e fluxo autenticado completo; RUNTIME STATUS: PARTIAL. |
| Não marcada | Mover tópico individual entre categorias públicas | Formulário, confirmação, nova revisão no mesmo Node, permissão específica e bloqueio de autoria histórica implementados. Unit e Kernel PASS; Functional/Runtime pendentes. Movimento de comentários implementado no código em dev.33, com portas de validação descritas em `MOVE-TOOLS.md`. |
| `0.1.0-dev.28` | Índice público inicial de categorias em `/categorias` | Runtime PASS para View, rotas, navegação HTTP e relação pai/filho; inspeção visual pendente |
| `0.1.0-dev.27` | Abas de comentários do Fórum por estado, marcador seguro de rejeição e validação Fórum/Revista | As três abas existem. O filtro para autoria histórica tinha uma resolução inválida do alias e fechava pendente/rejeitado com `1 = 0`; corrigido na dev.62. Runtime confirmou a consulta pendente separada com fixture Fórum/Revista; navegação HTTP autenticada e teste visual seguem pendentes. |
| `0.1.0-dev.26` | Fórum como aba em Conteúdo; “Comentários e moderação” como subaba; discussões contextuais fora das filas genéricas | Runtime de teste: abas carregadas e filtro exercitado; HTTP autenticado pendente |
| `0.1.0-dev.25` | Fórum em seção independente no menu Content e correção da View que causava HTTP 500 | Runtime PARTIAL: menu, access e renderização de ambas as Views confirmados; HTTP autenticado pendente |
| `0.1.0-dev.24` | Links do Fórum no mesmo menu Content usado por Galerias | Implementado com dev.25 |
| `0.1.0-dev.23` | Links de menu explícitos para Fórum, comentários do Fórum e Revista | Implementado; Runtime PARTIAL, árvore e acesso uid 5 confirmados; HTTP autenticado pendente |
| `0.1.0-dev.22` | Separação administrativa do Fórum e contratos por contexto; Revista com conteúdo e comentários reunidos em `/painel-administrativo/conteudo/revista` | Implementado; Runtime PARTIAL, sem sessão editorial de navegador |

## Política estrutural do painel administrativo

O conteúdo de discussão não se mistura à listagem genérica em
`/painel-administrativo/conteudo`. Fórum é uma aba própria nesse painel; seus
tópicos não entram na View genérica. As três filas do Fórum ficam como abas
secundárias diretas da aba Fórum, porque o Core limita tarefas locais a dois
níveis; a consulta agregada de comentários mantém seu caminho próprio. Revista reúne publicações e comentários em
`/painel-administrativo/conteudo/revista`; LMS terá área em
`/painel-administrativo/lms/forum` restrita a curso/turma/atividade e autorização
de Educador; Wiki terá área em `/painel-administrativo/wiki/forum` para
comentários de verbetes. LMS e Wiki só ativam suas rotas junto dos respectivos
adapters e depois de provar isolamento por contexto. Ver [contratos de contexto](CONTEXTS.md).

## `0.1.0-dev.1` — Auditoria e fundação documental (entregue)

- Estado GitHub, branches e PRs; base `main` verificada.
- Inventário real de Forum contrib, Composer/config, consumidores, cartões,
  componentes, integrações e riscos de remoção.
- Pesquisa upstream de Drupal 11+ e matriz de capacidades do Advanced Forum.
- Contratos de quatro contextos, ownership, privacidade/cache e migration gates.
- Documentação de `aculta_forum`, `aculta_user`, Portal e ACULTA420 sincronizada.
- Nenhuma alteração de banco, Runtime, conteúdo, dependência, entidade ou rota.

## `0.1.0-dev.2` — Fundação de cartões (`aculta_user`) (entregue)

- Implementados `AuthorIdentityResolver` e `AuthorIdentity`, compondo perfil e link público já existentes.
- Variantes User compartilham payload mínimo; autoria histórica fica separada e não é convertida em conta.
- Testes Unit direcionados para payload neutro, cache e autoria legada.
- Sem renderização, CSS, alterações de tema ou mudanças no Runtime.

## `0.1.0-dev.3` — Author Pane moderno (`aculta_user` + ACULTA420) (código implementado)

- Implementar variantes `author_forum`, `author_comment`, `author_forum_stacked`,
  `author_growroom_historico`; preparar `author_lms` e `author_wiki`.
- `AuthorIdentityResolver` aplica a `PublicProfilePolicy`, resolve avatar pelo
  `aculta_image` e mantém cache; perfil público exige opt-in da foto.
- `AuthorCardBuilder` retorna tema hook neutro; ações continuam sendo render
  arrays fornecidos por consumidores que já resolveram acesso.
- ACULTA420 implementa o SDC experimental `content/author-card`, inline e
  empilhado, com fallback por inicial, foco visível e tokens semânticos.
- Não portados: data de entrada, presença online, estatísticas, badges, assinatura
  e ações sociais; a política existente os exclui ou exige contexto/cache ainda
  não implementados. Integração do author card no Fórum contrib é para dev4+.
- Unit e gates estáticos direcionados em execução; browser/Runtime não validados,
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.4` — Motor executável (fundação)

- Módulo `aculta_forum`, Context API, resolver extensível e provider do fórum público.
- Configuração de instalação de Node/Comment/Taxonomy para tópicos independentes.
- Testes unitários de resolução, suporte e ambiguidade de providers.
- Views, formulários, permissões contextuais completas, instalação/runtime e prova Kernel multi-contexto seguem para fases posteriores; nenhum update/import é executado aqui.
- Runtime de teste habilitado com `--uri=https://growroom420.aculta.toca.net.br`; serviço/configuração conferidos, nenhum Node de teste criado. PHP CLI 8.4.26 limita a validação da baseline 8.5; PHP-FPM/web ainda pendente.

## `0.1.0-dev.5` — Retirada do Forum contrib

- **Concluída no Runtime de teste em 2026-10-20:** os Nodes `forum`, termos
  `forums`, vínculos `field_discussao`, storage/configuração legada e módulo
  contrib foram removidos na ordem oficial; `drupal/forum` não está instalado
  no Composer. Produção não foi alterada.
- Provider editorial iniciado em `aculta_forum`; Unit access/cache PASS e prova
  Kernel confirma storage Comment compartilhado entre `article`, `blog` e tópico.
- `article` e `blog` agora têm Config Sync/configuração de instalação para
  `field_aculta_discussion`; o formatter Core está preparado e `author_comment`
  usa o builder compartilhado do `aculta_user`. Kernels confirmam o campo
  compartilhado e a política pública. A configuração das duas instâncias e dos
  displays full foi aplicada parcialmente no Runtime; status default aberto
  verificado em memória, sem persistir Nodes ou Comments.
- O Runtime de teste tinha 30 Nodes `forum`, 8 termos `forums` e 12 vínculos
  editoriais; F-D33 removeu esses dados sem migrá-los. As 12 categorias ACULTA
  permanecem. Os consumidores ativos de Magazine, `aculta_user`, Messenger,
  Views e tema foram retirados nas entregas posteriores; a auditoria de resíduos
  e as evidências estão em `docs/MIGRATION-REMOVAL.md`.
- Em Magazine 0.1.0-dev.10/.11, hooks de insert/update/delete e a API
  `ForumTopicSync` foram removidos. `LegacyPublicationTopicLookup` somente lê
  vínculos históricos para apresentação/conversão. Em `aculta_user 0.4.0-dev.38`,
  o fallback antigo que exibia trechos de `comment_forum` foi removido após
  confirmar o campo Comment direto nas 12 publicações referenciadas.
  Os tópicos-casca antigos foram apagados em dev.45; não importar conteúdo
  antigo para os novos contextos.
- Inventário pré-operação, backup íntegro e contagens zero concluídos. O
  uninstall oficial e a remoção do pacote Composer também foram concluídos; o
  restore em base isolada e a reconciliação integral do Config Sync permanecem
  pendentes por drift preexistente.
- Troca de tipo da publicação re-hospeda Comments compartilhados antes de remover o Node antigo; transação e runtime ainda não foram exercitados nesta entrega.
- Exclusão escopada, uninstall oficial, remoção posterior do pacote Composer e
  verificação de resíduos concluídos no ambiente de teste; não executar o fluxo
  em produção sem decisão e plano próprios.
- Produção não é tocada por execução automática.

## `0.1.0-dev.6` — Growroom420 funcional

Categorias/subcategorias, tópicos, respostas, permissões, Views/navegação e
Author Cards do `aculta_user` para discussões públicas independentes. O modelo
visual detalhado está em `DISCOURSE-EXPERIENCE.md`.

Preparação parcial: formulário e displays Core de `aculta_forum_topic` foram
adicionados em dev.10 e aplicados ao Runtime de teste. A Page display serve a
raiz em dev.12; `/criar-topico` usa o Entity Form Core com Entity Create Access
em dev.13. O estado sem conteúdo descrito nos relatórios `.187` e anteriores é
histórico; veja abaixo a atualização `.189`. A fase dev.6 continua aberta para
UX visual, respostas reais e revisão das ferramentas de comunidade. A retirada
contrib dev.5 também permanece aberta por depender de consumidores migrados e
restore point válido.

Dev.14 acrescentou a ação “Criar tópico” à listagem pública apenas quando a rota
Core autoriza a conta atual, com cacheability de permissões e participação. A
ação ainda aguarda apresentação completa no ACULTA420 e validação visual;
nenhuma submissão persistida ocorreu. Testes Unit e gates estáticos direcionados
passaram; Runtime confirmou raiz 200, ação ausente para anônimo e rota de criação
403. Usuário autorizado e envio persistido permanecem sem homologação. A entrada
visual ganhou categorias e atividade lado a lado/empilhadas em dev.52; o
Runtime continua sem tópicos para validar cartão/linha com conteúdo real.

Histórico até `.187`: Dev.11 adicionou a View `aculta_forum_public_topics` e o
campo de domínio canônico; dev.12 conectou a Page display à raiz do purpose
forum por meio do caminho interno `/discussoes`. As verificações iniciais de
raiz 200 e rotas internas são históricas; dev.52 combina a Page com o bloco de
categorias CTools. Criação/resposta persistida, cartão/listagem com conteúdo e
UX completa continuam pendentes.

Histórico até `.187`: Dev.15 adiciona filtro exposto de categoria ao Page display, usando Taxonomy e
Views Core no vocabulário ACULTA. Runtime confirmou o plugin, renderização do
seletor e resposta 200 com query; o vocabulário está vazio, sem termos cadastrados.

`aculta_user 0.4.0-dev.33` integra os cartões `author_forum` e
`author_growroom_historico` nos modos full/teaser de `aculta_forum_topic`. A
integração não altera cartões de comentários, autoria editorial, LMS ou Wiki;
Runtime confirmou a implementação OOP e `author_forum` em Node não salvo em
memória, sem alterar o total de tópicos. Render de tópico persistido e inspeção
visual seguem pendentes. A última checagem HTTP direta da raiz retornou 301 para
`/discussoes`; uma requisição com query de diagnóstico retornou 200. A rota direta
continua restrita. A análise do Core e do Redirect confirmou que os atributos do
Request não sobrevivem ao cache de correspondência de rotas; a correção está em
Portal `0.4.0-dev.184`; após cache rebuild, `/` retornou 200 e `/discussoes`
continuou 404. Nenhum conteúdo novo foi criado.

Portal `0.4.0-dev.185` / Forum `0.1.0-dev.16` adicionam uma mensagem Core
`text_custom` para a listagem vazia. Após importação parcial só da View, HTTP
confirmou a mensagem na raiz, filtro presente e `/discussoes` fechado; zero
tópicos/comentários criados. Inspeção visual segue pendente.

Portal `0.4.0-dev.186` / Forum `0.1.0-dev.17` acrescentam ao papel
`email_confirmed` somente criar, editar e excluir o próprio `aculta_forum_topic`.
O Config Sync preserva as permissões/dependências e peso observados no Runtime;
não concede edição/exclusão de qualquer tópico. No Runtime `toca`, a importação
parcial do papel permitiu à conta de teste acessar a rota e renderizar em memória
o formulário Core (título, corpo e categoria). O formulário não foi enviado e
continuam zero tópicos e comentários. Testes Unit cobrem o contrato da role, não
substituem o teste funcional de submissão; a fase dev.6 permanece aberta.

Portal `0.4.0-dev.187` / Forum `0.1.0-dev.18` fixam no teste que tópico
público usa o Comment compartilhado `aculta_discussion_post`, inicia com
respostas abertas e exibe o formulário Core inline apenas a participantes com
`post comments`; anônimos não comentam. Runtime confirmou os valores da
configuração ativa e a permissão da conta uid 5. Como não há tópico salvo, não
foi possível renderizar o formulário associado a uma entidade persistida nem
enviar resposta. Nenhum dado foi criado; o fluxo funcional segue pendente.

### Categorias e tópicos de homologação

O campo `field_aculta_categories` é obrigatório e usa o vocabulário novo
`aculta_forum_categories`. A hierarquia inicial e seus tópicos `[TESTE]` foram
criados no Runtime de Homelab por solicitação explícita do responsável para
validar navegação e apresentação; os oito Nodes de teste foram removidos em
2026-10-19 depois da homologação, sem comentários. Os 12 termos seguem como
estrutura inicial do novo fórum. Os registros não são Config Sync nem parte do
acervo legado. Inventário e limpeza: [`TEST-FIXTURES.md`](TEST-FIXTURES.md).

Portal `0.4.0-dev.189` / Forum `0.1.0-dev.20` criaram 12 termos (4 categorias e
8 subcategorias) e 8 tópicos `[TESTE]` de uid 5 no Runtime de teste. Depois da
validação, os oito tópicos foram removidos; as categorias foram preservadas. A
correção Pathauto dá aliases portugueses ao bundle novo. A View GET pública agora não
recebe CAPTCHA global, que a fazia abortar durante o render: a raiz mostra os
cinco tópicos da página inicial e filtro `categoria=77` mostra o tópico
esperado; os oito aliases retornam 200. Naquele snapshot de 2026-10-19 o legado
tinha 30 Nodes e 8 termos; F-D33 removeu-os em dev.45. Nenhum comentário antigo
existia. Unit 11/64, PHPCS e gates Portal/fronteira/
mapa/slugs passam. Inspeção visual no navegador ainda é necessária. Essa
entrega cobre a base da experiência de listagem descrita em
`DISCOURSE-EXPERIENCE.md`; não encerra a dev.6.

Portal `0.4.0-dev.190` / Forum `0.1.0-dev.21` substituem o limite sem paginação
de cinco itens por um pager Core de 20 tópicos, com navegação em português e
quantidade fixa. A listagem pode apresentar as oito fixtures simultaneamente e
continua paginável à medida que o conteúdo crescer. O filtro de categoria e os
filtros de acesso/domínio permanecem inalterados. Unit e HTTP no Runtime de teste
confirmam a View; inspeção visual e submissão de tópico/resposta seguem
pendentes. Dev.6 continua aberta.

## `0.1.0-dev.7` — Comentários universais

Comment compartilhado quando tecnicamente suportado; renderização contextual,
encadeamento, formulários, permalink e moderação por contexto.

## `0.1.0-dev.8` — Revista/publicações

Concluído parcialmente: Comment direto em `article`/`blog` foi instalado no
Runtime de teste e os hooks que criavam, atualizavam ou despublicavam tópicos
contribuídos foram removidos. Preservar autoria editorial e nunca criar tópico
público para publicação. Serviços de leitura/conversão e o módulo legado ficam
até a migração dos consumidores e o corte seguro documentado.

## `0.1.0-dev.9` — Advanced Forum: categorias e navegação

O índice `/categorias` agora expõe a relação Taxonomy Core `Parent` como
coluna de categoria principal, preservando termos de topo e subcategorias numa
única View. Isso explicita a hierarquia sem criar consulta ou storage paralelo.
A confirmação visual e de conteúdo com termos filhos no Runtime continua
pendente.

O formulário individual para mover tópico está implementado; testes Unit e
Kernel confirmam a preservação de referências. Ainda aguarda Functional,
validação autenticada no Runtime e revisão de URL/histórico. Movimento em lote
e reorganização Taxonomy seguem planejados.

Categoria com descrição, subcategoria, índice geral, breadcrumbs, tópicos
fixados, contagem cacheável de respostas/última atividade e listagem por
atividade/data. Adotar o padrão observado no Ubuntu Discourse: descrições que
explicam o destino e listas que expõem título, categoria, respostas e atividade.
Planejar também ferramentas administrativas de organização: mover um tópico
entre categoria/subcategoria do Growroom, reorganizar a hierarquia Taxonomy e
selecionar vários tópicos para mudar categoria em lote. Exigir permissão
específica mais Entity Access de origem/destino, confirmação com contagem e
resumo, preservação do Node/revisões/Comments/alias e aviso rastreável. Não
cruzar propósito/domínio nem mover conteúdo do acervo Growroom histórico. Um
tópico-sombra/redirect não é padrão: só será considerado com decisão explícita
de URL e canonical, sem duplicar Nodes.

As linhas de tópico também planejam contadores de respostas/comentários
publicados por tópico e indicação de última atividade. Reutilizar a propriedade
`comment_count` do campo Comment e a tabela/projeção Core
`comment_entity_statistics`; pendentes, rejeitados e comentários sem acesso não
entram no contador público. Invalidação deve cobrir inclusão, aprovação,
despublicação, remoção e movimentos futuros. A listagem não fará COUNT por linha
em cada render.
O Core atualiza esse agregado a partir de Comments publicados e oferece os
campos para Views ([`CommentStatistics`](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentStatistics.php/class/CommentStatistics/11.x),
[`CommentViewsData`](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentViewsData.php/11.x));
validar cache/invalidação no campo compartilhado durante a implementação.

Na aba privada “Fórum” de Minha Conta, a pessoa também vê a quantidade de
respostas publicadas em cada tópico que criou. A fonte é o mesmo agregado Core;
não publicar contagens de outros autores, contar comentários pendentes/rejeitados
ou materializar estatísticas próprias por participante. Cartões públicos de
autoria não recebem totais até existir decisão de métrica/cache e política de
privacidade.

## `0.1.0-dev.10` — Advanced Forum: leitura e estatísticas

Subfase implementada: View `/discussoes/nao-lidas` com plugin de filtro e
indicador `history_user_timestamp` do projeto History, incluindo novas
respostas. O display exige papel autenticado, preserva filtros de status,
bundle, Domain ativo, vocabulário de categoria e SQL rewrite, e desativa cache
compartilhado por usuário. O projeto `drupal/history` foi promovido a requisito
direto do Composer para não desaparecer com `drupal/forum`; segue o alerta de
falta de cobertura da política de avisos de segurança em
`MIGRATION-REMOVAL.md`. Runtime: como uid 5, a consulta encontrou 7 discussões
novas/atualizadas; o acesso anônimo HTTP recebeu 403. Renderização HTTP com
sessão e inspeção visual permanecem pendentes.

A View inclui o campo nativo History `node_new_comments` para mostrar contagens
positivas e ligar à primeira resposta nova, com página e âncora calculadas pelo
Comment Core. O campo existe somente na listagem personalizada com cache
desativado por conta; não foi adicionado às listas públicas. Referência:
[History NodeNewComments](https://api.drupal.org/api/drupal/core%21modules%21history%21src%21Plugin%21views%21field%21NodeNewComments.php/class/NodeNewComments/11.x).

Subfase de descoberta implementada: a View pública também expõe “Novas” por
data de criação, “Sem resposta” pelo `comment_count = 0` do agregado Core e
“Mais comentadas” por contagem de respostas publicadas com desempate por
atividade. As três listagens preservam o bundle, publication status, Domain
ativo, filtro de categoria e SQL rewrite da View base. Abas locais ligam os
displays à página principal. Na primeira homologação, as três rotas responderam
HTTP 200 e cada consulta retornou os 8 tópicos de teste; “Sem resposta” retornou
os 8 porque não havia comentários publicados. Os fixtures foram removidos em
2026-10-19. Smoke HTTP posterior confirmou as três rotas com HTTP 200 e lista
vazia; a ordenação com contagem positiva continua sem caso Runtime. Um hook
GET-only existente foi ampliado
para remover CAPTCHA somente das formas de filtro dessas quatro páginas de
leitura; envio de conteúdo permanece fora da exceção.

Views Latest/Top/New/Unread/sem resposta, histórico de leitura por participante,
marcar como lido e primeiro conteúdo não lido, estatísticas e atividade
cacheáveis. Antes de juntar novos/não lidos numa navegação unificada, prototipar
e validar rótulos e filtros com usuários; a própria comunidade Discourse relata
trade-offs nesse modelo. Visualizações só após definir deduplicação, retenção,
bot policy e cache.
Incluir contadores de tópicos e respostas por pessoa somente em contexto próprio
e conforme privacidade; agregados públicos por categoria/tópico respeitam
publicação e Entity Access.

**Compatibilidade da leitura:** no Core 11.4.10 instalado, History Core declara
`lifecycle: deprecated` e será removido no Drupal 12. O projeto contrib
`drupal/history` 1.0.0, compatível com Core `>11.3`, estava instalado no Runtime
somente por dependência transitiva de `drupal/forum`; a dependência direta foi
adicionada ao Composer para manter a função após remover Forum contrib. Os
plugins `history_user_timestamp` e `history_read` oferecem os pontos de
integração usados pela lista personalizada. A versão 1.0.0 não está coberta
pela política de avisos de segurança do Drupal.org, portanto manutenção e
releases precisam ser acompanhadas. Core History foi depreciado no 11.4 e
removido no 12 ([depreciação](https://www.drupal.org/node/3567647), [remoção](https://www.drupal.org/node/3570700)); a recomendação oficial indica instalar a versão contrib para manter a função ([guia](https://www.drupal.org/docs/core-modules-and-themes/deprecated-and-obsolete)).

## `0.1.0-dev.11` — Advanced Forum: apresentação

Implementar lista de categorias, topic rows, cartões de participantes, leitor
linear, timeline/progresso, composer e navegação responsiva no ACULTA420. Usar
presets e layouts lateral/empilhado já previstos; validar light/dark, teclado,
mobile, reduced-motion e fallback sem JavaScript.

Antes de encerrar esta fase, fazer revisão visual em sessão gráfica X11 com
Firefox visível. Comparar as telas de entrada, categorias, lista, tópico e
composição com o Ubuntu Discourse atual, usando capturas da mesma largura e
densidade de conteúdo. Reproduzir a hierarquia, o ritmo, a densidade e os
padrões de navegação observados dentro da identidade ACULTA420; não reutilizar
marca, logos, ilustrações ou CSS do Discourse. Conferir desktop, 768 px e 390 px,
modo claro/escuro, foco/teclado e ausência de overflow. Guardar capturas de
homologação fora do repositório quando contiverem dados do Runtime e registrar
no relatório as telas, dimensões, divergências e correções.

Revisão visual X11 em 2026-10-20: entrada e categorias em 1024, 768 e 390 px,
comparadas à entrada Ubuntu em 1024 px. Dev.52 implementou categorias junto à
atividade: colunas em 1024 px e lista seguida pelas categorias em 768/390 px.
Claro foi observado nos três tamanhos; escuro em 1024/390 px. Tab confirmou
foco visível no atalho, marca e busca. As capturas privadas e limites estão em
`TEST-MATRIX.md`. Complemento X11: a thread com uma resposta foi observada em
1270 e 500 px e comparada a um tópico Ubuntu Discourse. A autoria lateral e o
painel de mensagem ACULTA permanecem legíveis em 500 px sem overflow. Discourse
acrescenta timeline/progresso de leitura e navegação persistente, já previstos
nesta fase como itens para implementar. A homologação segue parcial: falta
thread extensa em 390 px, fluxo completo de teclado, leitor de tela, listagem
com conteúdo e composer.

## `0.1.0-dev.12` — Participação e moderação

Flags, denúncias, salvar/seguir, citação, aprovação, edição e bloqueio por APIs
existentes e access real. Permissões de participação não serão derivadas de
trust levels/ranking do Discourse. Estudar fila de revisão por tipo/estado com
filtros contextuais, histórico cronológico da decisão e notas de equipe
associadas ao item revisável, sem misturar filas administrativas de Fórum,
Revista, Wiki e LMS. Tipos de denúncia e opções administrativas devem ser
configuráveis, mas limites, prevenção a abuso e revisão humana precisam de
decisão ACULTA antes de automação por contagem de flags.

“Mover comentário” tem uma primeira implementação em dev.33 para mover uma
resposta e sua subárvore dentro do Growroom, preservando estado de moderação;
ela não vai ao Runtime até completar as provas de Search API, History, anexos,
permalink, concorrência e browser. Dividir uma discussão quando o destino for
um tópico novo continua planejado. Merge de tópicos só poderá ser avaliado como fluxo
composto, após demonstrar plano de recuperação/rollback, com confirmação de
todos os comentários afetados e tratamento explícito do tópico de origem. Não
mover comentários entre Fórum, Revista, LMS e Wiki; não alterar estados de
aprovação/rejeição nem contornar moderação. Comentários em contexto LMS
continuam sob decisão dos Educadores com acesso real ao Group. O acervo
Growroom histórico permanece imutável, inclusive para administradores. O
desenho técnico, as decisões pendentes e os testes mínimos ficam em
[`MOVE-TOOLS.md`](MOVE-TOOLS.md).

## `0.1.0-dev.13` — Fóruns internos LMS

Adapter opcional `aculta_forum_lms`; curso/turma/atividade; Group/membership,
cartões educacionais, busca e notificações privadas. A fundação do provider e a
delegação de moderação contextual começaram na dev.92. Completar bundle, fields
e relationship types após validar a instalação do Group Node; provar leitura,
escrita, moderação, Views, Search API e avisos em testes antes de habilitar no
Runtime.

## `0.1.0-dev.14` — Contrato Wiki420

Provider futuro, discussão única por verbete, `author_wiki` e navegação interna.
Não reimplementar Wiki nesta fase.

## `0.1.0-dev.15` — Integrações gerais

Messenger, Image, Gallery, PDF, Game, eventos, cache e suas políticas de acesso.
Integração inicial do gatilho de primeira resposta publicada com `aculta_game`
foi implementada separadamente em `aculta_game 0.1.0-dev.11`: Game reconhece
Comment publicado de conta no tópico público novo e rejeita bundles do Drupal
Forum contribuído e autoria histórica. A
homologação do evento real no Runtime fica pendente; estatísticas completas e novos critérios
de gamificação continuam nesta fase e na linha própria do Game.

Pesquisa atualizada em 2026-10-19: o Discourse combina badges por marcos com
gamificação configurável por pontos e leaderboards; seus níveis de confiança
também podem conceder poderes de moderação. O ACULTA mantém o uso adequado já
aprovado: reconhecimento por badges e marcos publicados, sem XP, ranking,
streaks ou poderes de moderação derivados de volume. Critérios quantitativos
novos exigiriam defesa contra spam e medição de qualidade; a integração atual
reutiliza apenas a conquista existente da primeira resposta publicada. Fontes:
[badges Discourse](https://meta.discourse.org/t/understanding-and-using-badges/32540),
[gamification oficial do Discourse](https://meta.discourse.org/t/discourse-gamification/225916)
e [trust levels](https://blog.discourse.org/2018/06/understanding-discourse-trust-levels/).

## `0.1.0-dev.16` — Busca e descoberta

Fundação pública entregue na dev.86: índice Search API e View `/pesquisar`
isolados ao bundle de tópico, com acesso/status e filtro dinâmico pelo Domain
canônico do Growroom420. A dev.87 acrescenta filtro hierárquico por categoria,
com rótulos dinâmicos; a dev.88 comprova no MariaDB de Kernel que o ID
`target_id` filtra somente o tópico da categoria. Search API 1.41 ordena por
relevância decrescente quando a View não define sort. Homologação pública com
conteúdo real continua pendente sem criar fixtures no Runtime; LMS, Wiki,
Revista e Comments não entram neste índice público.

## `0.1.0-dev.17` — Consolidação

Revisar APIs, ownership, documentação, código morto, matriz Advanced Forum e
variantes; preparar homologação.

## `0.1.0-rc.1` — Homologação integral

Unit, Kernel, Functional, gates Drupal/tema/fronteira, Domain, acesso, cache,
segurança, browser/mobile, light/dark, acessibilidade, LMS, Wiki contratual,
Magazine, notificações, desempenho e install/update/uninstall/rollback.
Incluir rodada visual X11/Firefox visível contra a referência Ubuntu Discourse
e revisão por tamanhos fixos desktop/768/390 px; conferir também interação real,
foco, teclado, mensagens de validação e modo escuro, com screenshots antes/depois
para os desvios corrigidos.

## Estimativa de conclusão (revisão de 2026-10-20)

Estimativa de esforço restante, sujeita aos resultados de homologação e a
dependências de ambiente: dev.5 (uninstall/configuração residual), 1–2 dias
úteis; dev.6–12 (fórum público, navegação, moderação e ferramentas), 5–8 dias;
dev.13–16 (LMS, contrato Wiki, integrações, busca), 3–5 dias; dev.17 e rc.1
(consolidação, testes integrados e inspeção visual X11), 4–6 dias; rc.2 e
preparação estável, 2–4 dias. Total aproximado: 15–25 dias úteis, ou 3–5 semanas
de trabalho contínuo, mais 1–2 semanas de margem se surgirem regressões em
acesso, cache, dados ou UX. A sequência é de esforço e não representa prazo de
deploy; produção depende de decisão própria do responsável.

## `0.1.0-rc.2` — Correções

Corrigir regressões verificadas e repetir cobertura pertinente.

## `0.1.0` — Estável

Release notes, upgrade, operação, rollback e revisão final. Não implantar produção
automaticamente.

## Regras de avanço

- Cada fase encerra em commit próprio na PR já aberta, com commits pequenos, changelog, documentação,
  testes e limitações reais.
- Fase documental não atualiza versões de runtime de módulos/tema.
- Para operações de ambiente, seguir `docs/architecture/environments.md` e
  validar hostname, pwd, Git, Drush URI/banco, efeitos e recuperação.
- O estado local não comprova Runtime; usar PASS/FAIL/PARTIAL/DEFERRED.
- Variantes de autoria são contratos distintos por contexto. A autoria histórica do Growroom usa `author_growroom_historico`; ela nunca passa pelo resolver de contas User.
- O plano antigo F1–F9 e seus resultados permanecem histórico; decisões
  incompatíveis estão marcadas como superadas em `DECISOES.md`.

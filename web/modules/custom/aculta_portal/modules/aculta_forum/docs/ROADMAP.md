# Roadmap — ACULTA Forum

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). Fechar escopo, testes, gates e revisão visual aplicável antes de RC; Alpha valida o deploy no servidor de desenvolvimento; após aprovação, preparar produção. Seções `rc.*` abaixo são planejamento/histórico legado, não autorizam deixar critérios para depois do deploy.

Linha do projeto `aculta_forum`: começou em **0.1.0-dev.1**. Roadmap de escopo
aprovado pelo responsável em 2026-10-19; o estado real de cada entrega fica no
CHANGELOG e nos commits. Dev.1 e dev.2 são documentais/fundacionais; não alteram
Runtime.

`0.1.0-dev.185–193` adaptam os diálogos de imagem à política central e exigem
texto alternativo fornecido pela pessoa autora no
diálogo de anexo e uma descrição por cada imagem da galeria. O passo seguinte da
integração continua sendo validar upload real, publicação contextual, acesso
privado e apresentação no navegador, antes de avançar previews Office e cartão
PDF.

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

### Moderação revisionável de posts — decisão de 2026-10-21

O responsável decidiu substituir os Comments Core das discussões públicas do
Fórum por uma entidade de post revisionável e workflow completo. Comments do
Fórum serão migrados e removidos após equivalência validada; Comments dos demais
contextos ficam fora do corte. O plano arquitetural, contrato, workflow
proposto, migração e critérios de RC estão em
[`REVISIONABLE-DISCUSSION-WORKFLOW.md`](REVISIONABLE-DISCUSSION-WORKFLOW.md).
O post público reutiliza a política compartilhada de primeira participação em
`aculta_user`; o Fórum interno do LMS continua isento. A integração consulta
`ParticipationRules::requiresFirstDiscussionModeration()` e não duplica
configuração. Edição de autor publicada: 10 minutos desde a primeira publicação
por padrão, configurável em cada consumidor (Growroom420, LMS, Wiki e Comments);
editar não reinicia a janela e zero desativa edição de autor. Não habilitar
corte nem apagar dados Runtime antes das provas
Kernel, migração dry-run, backup e aprovação visual.

### Integração de anexos e galerias — ordem aprovada em 2026-10-21

1. Upload contextual de imagens com `aculta_image` e galerias `media_gallery`
   via `aculta_gallery`, consumindo a política central `aculta_portal.user_upload_policy`;
   a aba do Fórum herda a política global ou configura override, e a publicação
   dos Media permanece vinculada ao post ou comentário visível.
2. Cartões/preview local de Office, priorizando DOCX e planilhas, apenas para
   formatos validados em segurança e desempenho; metadados e download seguem
   disponíveis.
3. Por último, cartão PDF integrado ao ACULTA Document Viewer (`aculta_pdf_viewer`),
   miniatura/metadados e abertura em outra janela conforme opção administrativa.

O `aculta_pdf_viewer` reconhece o Media `forum_attachment` e serve seu campo
PDF pela rota privada do Core (`0.1.0-dev.40`). A dev.198 liga o formatter do
Fórum ao builder compartilhado; a validação integrada do cartão, miniatura,
janela, acesso privado e fallback continua pendente.

Dev.181 conecta DOCX e planilhas do Media `forum_attachment` aos builders locais
do `aculta_document_viewer`; as configurações ausentes de anexos, editor e
workflow de posts foram importadas seletivamente no Runtime de teste. As
permissões específicas de Fórum dos papéis `email_confirmed` e `content_editor`
foram aplicadas pelo Role API sem substituir outros acessos. Não há posts nem
anexos para validar os fluxos; revisão visual segue pendente. A configuração do
campo de comentário legado também foi alinhada ao formato `forum` após confirmar
que não há Comments nesse bundle. A política central de upload, antes ausente,
foi criada com a aba Fórum herdando os valores globais; a política efetiva não
mudou. Kernels de anexos, workflow e política de upload passaram (13 testes / 304
assertions; depreciações de Core/contrib registradas); a suíte de anexos agora
instala a definição de `ForumPost` pelo fixture existente. PDF continua como
download até a etapa 3.

Não criar leitor PDF ou serviço de conversão paralelo no Fórum. Cada etapa exige
respeitar o contrato de acesso e retenção de anexos privados.

Wiki e LMS devem migrar em seguida para a mesma política central, com abas
próprias no painel de uploads quando suas integrações forem concluídas. O
roadmap Portal descreve a sequência transversal.

| Versão | Entrega | Estado |
| --- | --- | --- |
| `0.1.0-dev.233` | Registrar escopo mínimo da busca e das notificações privadas LMS | Documento apenas: o ROADMAP do adapter (mantido na seção dev.13 do Fórum, sem `docs/` próprio) lista o que falta em índice/View, filtro de moderação, reindexação, eventos, idempotência e revalidação de acesso, e fixa o menor recorte aceitável — busca só por curso/turma com Group explícito e notificação apenas da primeira resposta publicada via `aculta_messenger`. Nada implementado; sem Runtime/testes. |
| `0.1.0-dev.231` | Revalidar purpose e host dos anexos no acesso | `media_access` restringe `forum_attachment` ao purpose Fórum e exige referência atual acessível em Comment, ForumPost ou Gallery; max-age zero protege mudanças de grants sem save da Media. A inspeção do Core 11.4.10 confirmou que o download privado passa por File `download`, referência Media `view` e acesso do campo; install e Sync mantêm `uri_scheme: private`. Revisão HTTP direta no Runtime segue pendente por falta de fixture. |
| `0.1.0-dev.230` | Alinhar guardrails/roadmap geral à reconstrução vigente | Documentação geral e política local deixam de apontar o contrib removido como fonte atual e preservam a coexistência de Comments/ForumPost até o corte aprovado. Sem Runtime. |
| `0.1.0-dev.229` | Eliminar subconsulta correlacionada na agregação de atividade | A consulta agrega `changed` por tópico e junta o maior ID em caso de empate; publicação, idioma, contagem e acesso permanecem iguais. Sem Runtime/testes. |
| `0.1.0-dev.228` | Executar pré-carga de atividade/autoria uma vez por View | Estatísticas e entidades de autoria são preparadas uma vez por `ViewExecutable`; regra de autoria permanece por linha. Sem Runtime. |
| `0.1.0-dev.227` | Reutilizar página calculada de posts pais na thread | Até uma consulta de contagem por post pai externo distinto na renderização atual; acesso, paginação e links inalterados. Sem Runtime. |
| `0.1.0-dev.226` | Remover limite agregado de descrições da galeria | Mantém validação individual de 512 caracteres sem limitar por consequência a quantidade de imagens além da política central. Sem Runtime/testes. |
| `0.1.0-dev.225` | Variar cache por toda query preservada na thread | Links entre posts usam `PagerManagerInterface::getUpdatedParameters()` e preservam a query completa; `url.query_args` varia também por filtros além dos pagers. Substitui a variação parcial por pager IDs da dev.224. Sem Runtime/testes. |
| `0.1.0-dev.224` | Variar cache da thread pelo pager de Comments | Links entre posts preservam o pager 0 de Comments; o render array da thread agora varia pelos pagers 0 e 1, evitando cache cruzado entre índices. Sem Runtime/testes. |
| `0.1.0-dev.223` | Preservar pager ativo nos links para post pai | O link chama sempre `PagerManagerInterface` com a página do pai, também na página atual; Comments pager e query da thread permanecem estáveis. Evita fragmento fora da página após reiniciar o pager em zero. Sem Runtime/testes. |
| `0.1.0-dev.222` | Redirecionar publicação/edição à página do post | Após salvar post publicado, `ForumPostSubmissionForm` usa a página compartilhada da thread e `getTopicPagerQuery()` antes de aplicar a âncora; sem fragmento se o destino público não puder ser calculado. Sem Runtime/testes. |
| `0.1.0-dev.221` | Isolar o link de não lidas ao abrir um tópico | `ForumUnreadPosts` usa `getTopicPagerQuery()` para iniciar Comment pager em zero e apontar o pager ForumPost à resposta não lida, sem parâmetros da View de origem. Sem Runtime/testes. |
| `0.1.0-dev.220` | Aplicar purpose ao cálculo da página do post | `getPageForPost()` compartilha a validação do tópico público usada pela thread: bundle, publicação, Domain associado ao purpose Fórum e purpose atual. Sem Runtime/testes. |
| `0.1.0-dev.219` | Isolar a paginação do destino da busca | Links interrota enviam apenas `page=0,{página}`: Comment pager Core começa em zero e ForumPost pager aponta à resposta, sem query da busca ou seu índice de página. Remove a variação ampla de cache de dev.218. Sem Runtime/testes. |
| `0.1.0-dev.218` | Variar link da busca por query string | Correção intermediária: formatter declarava contexto `url.query_args` por causa dos parâmetros mesclados pelo PagerManager. Substituída em dev.219 pelo estado explícito do destino, sem query de origem. Sem Runtime/testes. |
| `0.1.0-dev.217` | Abrir na página da resposta encontrada | O formatter da busca usa a posição calculada pelo builder e o pager Core ID 1 para abrir a resposta na página certa; preserva query/pagers existentes e invalida o cache pela tag `aculta_forum_post_list`. Sem Runtime/testes. |
| `0.1.0-dev.216` | Compartilhar o contrato de paginação da thread | O builder público fornece tamanho/pager ao contador de não lidos e o link usa `PagerManagerInterface` para apontar à primeira página que contém o post. Evita divergência entre paginação e âncora; sem mudança Runtime/testes. |
| `0.1.0-dev.215` | Invalidar cache de lista ao mudar ForumPost | Plugin tag-based acrescenta `aculta_forum_post_list` à cache de resultados e render da View pública; install, Sync e update `9005` alinhados. Runtime `toca`: update aplicado e `getCacheTags()` confirmou `aculta_forum_post_list`/`node_list`; sem conteúdo criado ou testes automatizados. |
| `0.1.0-dev.214` | Remover classes de apresentação do markup do Fórum | Progresso e histórico localizam posts pelo atributo funcional `data-aculta-forum-post-timestamp`; classes sem consumidores foram removidas. As classes da comparação continuam por contrato do Diff contrib. Gate módulo-tema passa. Sem testes. |
| `0.1.0-dev.213` | Corrigir níveis semânticos e revisar a grade de categorias | Grupos H2/categorias H3 e reflow sem overflow em 320/375/390/768/1024/1440 px. Follow-up Firefox ESR/X11 após ACULTA420 0.6.1-dev.3: capturas claro/escuro em 390/768/1024, 15/15 verificações; cartões sem sobreposição nas capturas inspecionadas. Zoom e aparelho real pendentes. |
| `0.1.0-dev.212` | Aplicar agrupamento no Sync e em instalações existentes | O Sync e a atualização `9003` aplicam o agrupamento da página sem substituir filtros, acesso, display padrão ou bloco lateral. Runtime de teste atualizado; 12 categorias e hierarquias disponíveis para revisão visual. Testes automatizados não executados. |
| `0.1.0-dev.211` | Agrupar visualmente categorias pela categoria-pai | A página `/categorias` usa o agrupamento nativo do Views, preserva a grade por grupo e cria títulos semânticos; o bloco da entrada permanece inalterado. Contadores seguem pendentes até a matriz de acesso/cache MariaDB; pesquisa estática identificou `node_access` query tag como hipótese a provar (`PESQUISA.md`). Inspeção visual pendente; sem testes. Gates Drupal 11+, status index, design foundations e SDC passam; Foundation ainda exige a versão anterior do tema no Runtime, e módulo-tema aponta dívida preexistente. |
| `0.1.0-dev.210` | Carregar autores da lista pública em lote | ForumPosts, contas User e autores históricos da página são carregados em lote; seleção de atividade e `aculta_user` permanecem como fonte de autoria/apresentação. Validação integrada de performance pendente. Sem teste ou Runtime. |
| `0.1.0-dev.209` | Mostrar autoria da atividade mais recente na lista pública | Compara atividade de tópico, Comment e ForumPost; a identidade User/histórica de ForumPost usa o builder proprietário `aculta_user`. Validação integrada/browser pendente. Sem teste ou Runtime. |
| `0.1.0-dev.208` | Incluir ForumPost na contagem e atividade da lista pública | Combina Comments e ForumPosts publicados em contagem, ordenação e filtro “Sem resposta”; atividade usa o timestamp mais recente do tópico, Comment ou ForumPost. A autoria exibida ainda é a do último Comment até integrar a identidade do último post; validação integrada pendente. Sem teste ou Runtime. |
| `0.1.0-dev.205` | Conectar ForumPost à conquista existente de primeira resposta | Game avalia a primeira publicação default de post público pelo trigger `forum_comment`; sem dependência do Fórum, backfill ou Runtime. Validação automatizada integrada pendente. |
| `0.1.0-dev.206` | Incluir ForumPost no progresso de leitura | A navegação da thread aceita Comments e posts revisionáveis; contas autenticadas comparam `first_published` ao recibo History do Node no cliente, sem variar HTML cacheável por usuário. A View `/discussoes/nao-lidas` e contagens ainda eram Comment-only; extensão da consulta e validação integrada permaneciam pendentes. Sem teste ou Runtime. |
| `0.1.0-dev.207` | Incluir ForumPost na lista de não lidas | Filtro Views considera `first_published` de posts públicos junto a mudanças de tópico/Comments; uma consulta em lote agrega contagem por recibo e liga à âncora no pager correto. Teste integrado de access, grants, contagem, página/âncora e Runtime permanece pendente. Sem teste ou mudança Runtime. |
| `0.1.0-dev.204` | Rastrear atualização do tópico na busca de posts | Campo oculto `topic:entity:changed` dá ao Search API uma referência indexada para enfileirar posts quando o tópico hospedeiro é salvo; rebuilds de grants sem save ainda exigem reindex explícito. Sem Runtime. |
| `0.1.0-dev.203` | Atualizar a entrada do módulo para a busca de posts | README descreve tópicos, posts, grants antes da paginação e validação pendente antes do Runtime. |
| `0.1.0-dev.202` | Incluir posts revisionáveis na busca pública | Configuração inclui datasource/post fields, filtros unificados e formatter de âncora; processor filtra grants/publicação antes da paginação. Validação automatizada integrada pendente antes de reindexar ou aplicar no Runtime. |
| `0.1.0-dev.201` | Preservar autoria histórica em Comments anônimos | Destino aceita `uid=0` somente com referência existente a `aculta_legado_autor`; auditoria conta os anônimos sem representação histórica válida. Sem execução de migration ou Runtime. |
| `0.1.0-dev.200` | Bloquear perda de autoria anônima na migração | Primeira trava tratava todo `uid=0` como não representável; refinada na dev.201 para aceitar referências históricas válidas. Não migra dados de contato. |
| `0.1.0-dev.199` | Evitar ação duplicada nos cartões PDF | O cartão mantém download/abertura do `aculta_pdf_viewer`; link Core só entra no fallback negado. Sem alteração Runtime; inspeção visual pendente. |
| `0.1.0-dev.198` | Integrar cartão PDF compartilhado aos anexos | Formatter delega `forum_attachment.field_media_forum_attachment` ao `aculta_pdf_viewer`; o builder valida acesso/cache e link Core permanece fallback. Sem Runtime; validação visual/funcional pendente. |
| `0.1.0-dev.197` | Limitar lotes ao sincronizar anexos no estado do tópico | Mudança de publicação do tópico percorre Comments e posts em lotes de 100, com limite máximo capturado no início; Media e Gallery preservam as regras existentes. Sem alteração Runtime; regressão automatizada pendente. |
| `0.1.0-dev.196` | Exigir autenticação nos diálogos de upload | Acesso dos diálogos soma autenticação explícita ao formato `forum` e purpose Fórum; cache varia por estado autenticado. Sem validação Runtime. |
| `0.1.0-dev.195` | Auditar watermarks distintos do Messenger | Marcadores `ultimo_comentario` e `ultimo_post` são classificados separadamente; só contagens são expostas. |
| `0.1.0-dev.190` | Revalidar anexo no callback AJAX | Cardinalidade, existência, política compartilhada e texto alternativo são conferidos novamente antes de criar Media. Sem Runtime. |
| `0.1.0-dev.189` | Aplicar limite de texto alternativo no servidor | Anexo individual exige valor não vazio até 512 caracteres, conforme o campo ImageWidget do Core. Sem Runtime. |
| `0.1.0-dev.188` | Adaptar os diálogos à política efetiva de imagem | Campo alt só aparece se a imagem estiver habilitada; galeria sem extensões permitidas informa a situação e não oferece criação vazia. Sem Runtime. |
| `0.1.0-dev.187` | Alinhar limite de texto alternativo com o Core | Descrição por imagem não vazia e limitada a 512 caracteres, conforme `ImageWidget` do Core 11.4. Sem alteração Runtime. |
| `0.1.0-dev.186` | Exigir descrição para cada imagem enviada à galeria | O diálogo associa cada linha ao File na ordem de upload, valida quantidade e conteúdo, e repete a verificação de arquivo/política antes de criar Media. Sem alteração Runtime ou revisão visual. |
| `0.1.0-dev.185` | Exigir texto alternativo no upload de imagem do Fórum | O diálogo de anexo coleta descrição acessível e valida no servidor antes de criar Media; documentos não usam o campo. Sem alteração Runtime ou validação visual. |
| `0.1.0-dev.184` | Liberar o ciclo público e a moderação de posts revisionáveis | Corrigida colisão de `$messenger` com `FormBase`, namespace da interface `TimeInterface` e chamada booleana de `access()->isAllowed()` na edição/resposta. Form mode/display público alinhados no Sync e Runtime. Smoke autenticado confirmou envio `review`, fila em `main`, aprovação, edição como nova revisão publicada e contexto de resposta; corpo visível no tópico. Fixture, contas, aceite de teste e métrica agregada removidos. Upload contextual, preview DOCX/planilhas, fallback e revisão visual seguem pendentes. |
| `0.1.0-dev.183` | Resolver controllers dos formulários públicos | Os serviços de submissão e edição usam IDs explícitos e rotas `service:method`; o Core 11.4 confirmou que ambos e os demais controllers do Fórum resolvem pelo `CallableResolver` (14 rotas sem falha). O smoke HTTP autenticado ainda retorna 500 sem corpo e sem novo watchdog; origem não identificada, fluxo não validado. Fixture limpa e Runtime retornou a 0 tópicos/posts/Comments dedicados/anexos e 10 usuários. |
| `0.1.0-dev.182` | Corrigir renderização de posts no Runtime | Corrigida injeção do usuário e preservado o pré-render de cada post na Thread. Configuração parcial alinhou o campo contextual de Gallery e displays ausentes no Runtime sem alterar as 97 imagens existentes. Firefox ESR confirmou um DOCX OOXML completo visível após a correção em `aculta_document_viewer` dev.12; query e path Core retornaram 200 para arquivo de propriedade `aculta`. A captura evidenciou o iframe removido no caminho `done`; agora é mantido no sucesso e encerrado em falha. Sem overflow no viewport efetivo 1354 px; revisão da matriz mobile, planilhas e demais fluxos Office, submissão/moderação e revisão visual aplicável continuam pendentes. |
| `0.1.0-dev.181` | Conectar preview local de Office nos anexos do Fórum | Formatter do Media `forum_attachment` delega DOCX e planilhas aos builders do `aculta_document_viewer`, mantendo acesso e link de download. Configurações ausentes de Media, editor, workflow, Flag e displays importadas parcialmente; grants específicos de Fórum, formato do campo Comment dedicado e configuração central de uploads foram alinhados ao Sync. Kernels de anexos, workflow e política de upload passaram (13/304), mas não há post nem anexo no Runtime. Validação com arquivos, submissão/moderação e revisão visual pendentes. Sem importação global ou fixture de conteúdo. |
| `0.1.0-dev.180` | Preparar migration das denúncias | Source keyset percorre `sinalizar_comentario`; destino resolve CID pelo mapa de posts e preserva dono/sessão/escopo/timestamp via Entity API com guarda de presave específica. Global, sessão anônima ausente, dono/timestamp inválidos, duplicidade e config incompatível falham fechados; auditoria expõe as respectivas contagens. No Runtime de teste, Migrate foi habilitado conforme dependência do módulo; plugins e migration instanciam, source sem linhas. Config do novo Flag não importada e nenhuma migration executada; Kernel/eventos com dados reais pendentes. |
| `0.1.0-dev.179` | Auditar integridade das denúncias migráveis | Dry-run conta flags globais, donos ausentes e timestamps `created` inválidos em lotes, sem expor identidade. Smoke no Runtime de teste: zero Comments/posts/flags elegíveis, portanto casos com dados seguem sem validação; nenhuma migration/configuração executada. |
| `0.1.0-dev.178` | Incluir denúncias de posts revisionáveis | Flag dedicado aparece no display do post, exige permissão e valida publicação/default revision e Domain Fórum; a fila User agrupa o novo tipo sem depender do módulo Fórum. A auditoria mapeia o Flag legado `sinalizar_comentario` ao destino novo. Conversão real das flaggings e preservação do timestamp ainda pendentes; sem import de configuração/Runtime. |
| `0.1.0-dev.177` | Evitar efeitos de anexos durante importação | Hooks de publicação de Media/Gallery ignoram saves sincronizados de `ForumPost`; a remoção normal dos Comments legados no corte reavalia referências com os posts já existentes. Sem execução da migration. |
| `0.1.0-dev.176` | Reprovar embeds sem destino na migration | O destino confere em lote os UUIDs de Media e Gallery em todas as traduções e interrompe a linha se faltar UUID, entidade ou tipo de destino. Evita salvar referências quebradas; Entity Usage registra as referências quando o post é salvo. Sem execução da migration; integração de anexos e publicação contextual continuam pendentes. |
| `0.1.0-dev.175` | Reconciliar traduções em reexecuções | Destino remove traduções não padrão que desapareceram da origem e interrompe a linha quando o idioma padrão do Comment mudou, sem converter silenciosamente o idioma padrão do post. Sem execução da migration. |
| `0.1.0-dev.174` | Preservar estado e timestamp na migration | Hook ignora a seleção de estado e timestamp corrente quando o post é marcado como sincronizado pelo Core Migrate; mantém o estado importado e usa o timestamp `created` como limite conservador quando não há primeira publicação histórica. Sem execução de migration ou Runtime. |
| `0.1.0-dev.173` | Verificar destino das denúncias migradas | Auditoria conta flaggings sem configuração compatível com `aculta_forum_post`. `sinalizar_comentario` e a fila atuais são Comment-only; antes de migrar, integrar configuração/fila para posts e estabelecer preservação de identidade e timestamp pela Flag API. Smoke Homelab somente leitura: zero flaggings, compatibilidade não validada com dados. |
| `0.1.0-dev.172` | Identificar pais pela API do Comment Core | Fonte e auditoria distinguem raiz de resposta por `hasParentComment()`; sentinelas `pid` não causam lookup nem contagem falsa de pai ausente. Smoke Homelab sem Comments elegíveis, portanto relações reais não validadas. |
| `0.1.0-dev.171` | Auditar impeditivos de conteúdo da migration | Inventário conta Comments com traduções sem corpo, formato incompatível ou publicação incompatível com rejeição, alinhado às validações do destino; somente contagens, sem exposição de conteúdo. Smoke Homelab somente leitura: 0 nos três impeditivos; casos com dados aguardam validação. |
| `0.1.0-dev.170` | Auditar referências de autoria ausentes | Inventário confere em lotes os usuários autores e `aculta_legado_autor` referenciados; apresenta apenas contagens para reprovar a migração antes da execução. Smoke Homelab somente leitura: acervo elegível vazio, portanto casos com dados não validados. |
| `0.1.0-dev.169` | Criar destino multilíngue e migrations de posts/pais | Destino salva uma revisão com traduções por Comment e mapeia CID a post ID; definições em duas passagens preservam metadados e ligam pais via mapa Core, sem stubs e com overwrite somente de `parent`. Formato incompatível, corpo vazio ou estado conflitante falham. Validação de integração pendente; sem Runtime. |
| `0.1.0-dev.168` | Criar fonte Migrate paginada para Comments do Fórum | Plugin de origem usa keyset por CID com máximo inicial, lote configurável de 1–1.000 e um source row por Comment com traduções agrupadas; sem nome/e-mail/hostname. Core Migrate declarado no módulo e no sync. Destino, definições de migration e validação pendentes; sem Runtime. |
| `0.1.0-dev.167` | Definir mapa de posts e relações em duas passagens | Source row por Comment agrupa traduções e um destino próprio salva uma entidade/mapa `cid → post ID`; migration dependente resolve pais com `migration_lookup`/`no_stub` e altera só o campo pai. Evita depender da ordem por CID e stubs fora do escopo. Implementação e prova pendentes; sem Runtime. |
| `0.1.0-dev.166` | Detectar pais posteriores aos filhos na ordem por CID | Auditoria sinaliza relações válidas em que o pai tem CID maior, precondição para escolher lookup sequencial ou estratégia em duas passagens. Smoke GET/Drush somente leitura no Homelab: zero Comments de origem, zero posts existentes e zero relações pai/filho; acervo vazio neste Runtime. |
| `0.1.0-dev.165` | Pesquisar fonte e destino do Migrate API para traduções | Core 11.4.10: `content_entity` materializa todos os IDs antes de iterar; `EntityContentBase` salva cada linha separadamente e mapeia também o idioma. Antes da definição executável, implementar fonte paginada e vínculo idempotente das linhas de um Comment ao mesmo post de ID automático. Sem migração/Runtime. |
| `0.1.0-dev.164` | Habilitar traduções de assunto e corpo dos posts | Campos de apresentação aceitam traduções para preservar os idiomas dos Comments na migração; tópico, pai e posição permanecem estruturais/compartilhados. Atualização de schema no Runtime e migração pendentes. |
| `0.1.0-dev.163` | Paginar avisos do Messenger na auditoria | Avisos por tópico são percorridos em lotes com CID máximo inicial; marcadores e Comments associados são resolvidos por lote, sem lista global de registros. Sem Runtime. |
| `0.1.0-dev.162` | Limitar mapas auxiliares de relações da auditoria | Pais são resolvidos por lote de origem; marcadores de avisos consultam apenas seus CIDs distintos em lotes. Remove o mapa global de elegibilidade dos Comments. Sets únicos de tópicos, UUIDs e avisos ainda crescem com o relatório. Sem Runtime. |
| `0.1.0-dev.161` | Paginar a auditoria de migração por CID | Consulta fixa o maior CID e carrega Comments de origem por lotes keyset, sem criar uma lista de todos os IDs antes da leitura; inserções posteriores ao limite ficam fora. Mapas auxiliares ainda crescem com relações/UUIDs. Sem alteração Runtime. |
| `0.1.0-dev.160` | Exibir série temporal no painel do Fórum | Últimos sete dias UTC mostram tópicos, Comments legados e posts revisionáveis por `aculta_statistics`; integração opcional, cache de 60 segundos, sem dados pessoais. Eventos de entidades sincronizadas são excluídos. Testes, Runtime e revisão visual pendentes. |
| `0.1.0-dev.159` | Emitir atividade de posts revisionáveis | Criação emite evento mínimo para estatísticas; entidade sincronizada/importada não conta como participação nova. Validação Runtime e testes pendentes. |
| `0.1.0-dev.158` | Definir requisitos de acesso para busca de posts revisionáveis | Posts não entram no índice até que consulta, grants do tópico, Domain, categoria e paginação estejam filtrados com segurança; manter access check padrão da View e link canônico do post. Sem mudança Runtime; ver `SEARCH.md`. |
| `0.1.0-dev.157` | Validar referências de Media/Gallery do inventário migratório | Conta UUIDs distintos, entidades ausentes e embeds sem UUID, sem conteúdo/PII na saída; processamento permanece com `aculta_image`/`aculta_gallery`. Sem alteração Runtime; testes pendentes. |
| `0.1.0-dev.156` | Integrar posts revisionáveis à atividade pessoal | A aba de comentários combina posts do Fórum e Comments legados, com acesso revalidado e ordenação cronológica. Sem dependência circular; testes e smoke autenticado pendentes. |
| `0.1.0-dev.155` | Inventariar agregados e marcadores dos avisos de respostas | Com Messenger habilitado, mede avisos por tópico elegível e valida se `ultimo_comentario` referencia Comment elegível, externo, inexistente ou de outro tópico. Somente leitura; testes pendentes. |
| `0.1.0-dev.154` | Inventariar denúncias/flags de Comments elegíveis | Auditoria conta Flaggings por configuração sem expor autores; a migração deverá recriar as associações pela API do Flag, que impede mover uma flagging. Sem alteração Runtime; testes pendentes. |
| `0.1.0-dev.153` | Corrigir classificação de pais fora do escopo no inventário | Auditoria resolve pais existentes não elegíveis e separa relações externas de referências realmente ausentes. Sem alteração Runtime; testes pendentes. |
| `0.1.0-dev.152` | Inventariar Comments antes da migração revisionável | Comando somente leitura mede escopo/purpose, estados, idiomas/traduções, timestamps, autoria histórica, formatos, embeds e integridade pai/filho; não grava IDs nem cria destinos. Migrate API e mapa idempotente pendentes. Sem Runtime; testes pendentes. |
| `0.1.0-dev.151` | Comparar revisões de páginas diferentes | Seleção aceita IDs mostrados em qualquer página e valida existência/vínculo ao post antes de redirecionar; rota de comparação revalida o acesso. Sem alteração Runtime; testes e revisão visual pendentes. |
| `0.1.0-dev.150` | Navegar até o pai fora da página atual | Contexto da resposta liga ao post pai acessível na página correta da thread, preservando os demais pagers e query args; pais ocultos continuam sem link. Sem rota nova ou Runtime; testes e inspeção visual pendentes. |
| `0.1.0-dev.149` | Integrar anexos e galerias a posts revisionáveis | O formato `forum` fica fixo no corpo; ciclo de publicação de Media/Gallery acompanha post público e tópico; retenção verifica todas as revisões antes de limpeza. Regressão Kernel e Runtime pendentes. |
| `0.1.0-dev.148` | Editar publicação própria dentro da janela | Thread oferece edição de post publicado/default pelo próprio autor; UUID na query, access revalidado e limitado pela `ParticipationEditWindow`; grava nova revisão preservando estado e contexto. Runtime e revisão visual pendentes. |
| `0.1.0-dev.147` | Selecionar revisões para comparação | Histórico permite escolher duas revisões da página atual, além do atalho entre adjacentes; valida IDs disponíveis/diferentes e preserva validação de acesso no destino. Ainda limitado à página corrente (50 registros); seleção entre páginas e revisão visual pendentes. Sem revert/delete ou Runtime. |
| `0.1.0-dev.146` | Comparar revisões adjacentes de uma publicação | Histórico oferece comparação segura de assunto/corpo para duas revisões do mesmo post, com acesso restrito a `main` e tópico publicado em `forum`; reutiliza Diff 2.1.1 habilitado. Sem revert/delete ou Runtime; comparação não adjacente, testes e visual pendentes. |
| `0.1.0-dev.145` | Identificar o contexto da resposta | Lista cronológica mostra assunto do post pai acessível, com âncora interna se presente na página; composer confirma o destino da resposta. Sem rota nova, CSS/JS ou Runtime; hierarquia visual, navegação ao pai em outra página e validação visual pendentes. |
| `0.1.0-dev.144` | Responder a posts revisionáveis | Ação por publicação abre o composer contextual; UUID do pai é resolvido no servidor e só aceita revisão default publicada, acessível e do mesmo tópico. Sem rota nova ou Runtime; apresentação hierárquica, testes e revisão visual pendentes. |
| `0.1.0-dev.143` | Composer público contextual de posts revisionáveis | Link só aparece para conta autenticada com permissão; `DiscussionContextResolver` e rota same-origin revalidam tópico por UUID. Form mode Core oculta estado/referências; `ParticipationRules` define moderação. Comments permanecem ativos; respostas a posts, Runtime e revisão visual pendentes. |
| `0.1.0-dev.142` | Histórico administrativo das revisões | Fila liga a visão paginada de revisões; mostra autoria, instante, estado, assunto e mensagem de revisão. Acesso restrito ao purpose `main`, permissão administrativa e tópico publicado no purpose `forum`; somente leitura, sem revert/delete. Sem Runtime; comparação textual e revisão visual pendentes. |
| `0.1.0-dev.141` | Fila Core de moderação dos posts revisionáveis | Aba administrativa lista revisões `review` da entidade via storage `content_moderation_state`; Form API abre revisão exata e Core valida transições. Acesso limitado ao purpose `main`, permissão do módulo e tópico publicado do purpose `forum`. Sem Runtime; histórico e revisão visual pendentes. |
| `0.1.0-dev.140` | Renderizar posts revisionáveis na página canônica | Hook acrescenta ao tópico `full` posts publicados/acessíveis, paginados em 50 por pager ID 1, com fragmentos estáveis, referências pai visíveis e cache por página/usuário/permissões/domínio. Comments continuam ativos durante migração; não há rota nova nem alteração Runtime. |
| `0.1.0-dev.139` | Preparar handler e widgets Form API | A entidade passa a declarar `ContentEntityForm`; assunto, corpo e estado usam widgets Core no display `default`. Sem rota, formulário exposto ou alteração no Runtime; a criação aguarda integração contextual e equivalência migratória. |
| `0.1.0-dev.138` | Preparar renderização de post revisionável | O display `default` usa formatters de texto do Core e oculta metadados internos; `aculta_user` renderiza autoria User/histórica. Nenhuma rota, formulário ou corte foi ativado; Comments seguem ativos. Gates Portal e índice PASS; sem Runtime. |
| `0.1.0-dev.137` | Preservar autoria histórica na entidade revisionável | Campo-base revisionável `legacy_author` aponta a `aculta_legado_autor`; Unit e PHPCS PASS. Runtime: cache reconstruído, campo descoberto e sem tabelas da entidade; HTTP 200/302 esperado. Migrador e equivalência pendentes; sem alteração de conteúdo/configuração. |
| `0.1.0-dev.136` | Acesso contextual e permissões do post revisionável | Criação só no purpose `forum`; leitura/edição de autoria limitadas ao tópico público publicado; administração no purpose `main`; papéis recebem permissões Core de transição. Unit access e janela PASS. Leitura integrada, formulários, migração de Comments e corte permanecem pendentes. Dev, cache do Runtime reconstruído sem erros HTTP/PHP novos. |
| `0.1.0-dev.135` | Registrar hooks DI e iniciar entidade/workflow de post revisionável | Todos os hooks com construtor passam a serviço explícito; Kernel prova entidade revisionável, associação de bundle/workflow, timestamp imutável e janela de edição sem concessão de acesso. Foram removidas as rotas administrativas auto-geradas antes da migração de schema. Integração das telas, migração e corte continuam pendentes; Runtime HTTP do Fórum voltou a responder 200 sem erro PHP. Dev, sem deploy. |
| `0.1.0-dev.132` | Transferir aviso agregado ao mover árvore publicada | Evento transacional do Fórum consumido pelo Messenger; transfere o registro inteiro de respostas e mescla no destino. Kernel do mover: 19/391; Unit do consumidor: 2/4 PASS; 19 depreciações Core/contrib. Teste de persistência da transferência e ciclo de anexos seguem pendentes, portanto permanece Dev. |
| `0.1.0-dev.131` | Foco de teclado no conteúdo principal | `forum-index-visual.mjs`: primeiro Tab revela link de salto com `:focus-visible`, Enter transfere foco a `main#main-content`. Firefox ESR/X11: raiz 15/15, `/categorias` 15/15, 390/768/1024 em claro/escuro (escuro forçado localmente no DOM); nenhum submit ou escrita. Inclui captura do link focado a 390 px. |
| `0.1.0-dev.130` | Preservar imagem inline ao mover comentário | Kernel integra Editor Core: imagem `data-entity-uuid` mantém `file_usage` associado ao CID depois da mudança de tópico. 19 testes / 386 assertions PASS no MariaDB dedicado; Runtime não alterado. |
| `0.1.0-dev.129` | Contrato de anexos em comentários | Unit verifica formato de texto, embeds existentes de documento e upload inline de imagem pelo CKEditor alinhado ao `aculta_image.UploadPolicy`. Upload direto de PDF/Office permanece pendente de decisão; nenhuma permissão ampliada. |
| `0.1.0-dev.128` | PHPCS dos docblocks de atividade | Comentários do produtor de eventos compatíveis com Drupal/DrupalPractice; sem alteração funcional. |
| `0.1.0-dev.127` | Produtor de eventos de atividade para estatísticas | Eventos mínimos de tópico e comentário criados são consumidos por `aculta_statistics`; Unit do Fórum 94/650. Validação dos eventos no Runtime e decisão de apresentar o agregado no painel pendentes. Integração contextual H5P–Fórum validada pela F15 de `aculta_lms_h5p`; ver seção dev.13. |
| `0.1.0-dev.126` | Verificar estabilidade do permalink canônico Comment após movimento | Kernel `ForumCommentMoverKernelTest`: 18/368 PASS; rota Core e fragmento baseados no CID confirmados após mover. `ForumTopicDisplayConfigTest`: 19/283 PASS após comparar mapas YAML sem depender da ordem das chaves. PHPCS e status-index PASS. O gate Drupal 11+ ainda falha na apresentação de autoria da Wiki, fora da mudança do Fórum. Links antigos ao tópico de origem com fragmento não podem ser redirecionados no servidor. RUNTIME STATUS: DEFERRED. |
| `0.1.0-dev.125` | Homologar movimento de comentário na seção administrativa canônica | Firefox ESR/X11 autenticado: 10/10; formulário/CSRF e submissão Form API confirmados em 390/768 px. Árvore ramificada de 4 Comments, estado pendente/rejeitado, identidade e denúncia/fila preservados. Fixtures e configuração temporária removidas; Fórum voltou a 0 tópicos/Comments, 12 categorias e fila/avisos no baseline. Forum Unit 19/283, Kernel 18/366 (19 depreciações), PHPCS e gates Portal 996, mapa 154, fronteira 1598, slugs 381 PASS. RUNTIME STATUS: PASS no fluxo exercitado; anexos, concorrência, aprovação e avisos aos seguidores continuam pendentes. |
| `0.1.0-dev.124` | Reabrir mover comentário na área administrativa Comentários | `ForumTopicDisplayConfigTest` 19/282, PHPCS e diff check PASS; Runtime RouteProvider confirma caminho plural, handler Form API, conversor e redirect singular após `drush cr`. Config ausente manteve operação fechada; nenhuma entidade alterada. Sem Comment de Fórum para renderizar o formulário com entidade real; submissão DEFERRED. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.123` | Homologar movimento de tópicos em lote no Runtime | Firefox/X11 autenticado selecionou exatamente dois tópicos, exibiu títulos na configuração/confirmação e concluiu Batch API; entidades preservaram identidade e dados fora da categoria, com nova revisão. Fixtures removidas; baseline do fórum e fila Messenger restabelecidos. O texto de organização dos quatro contextos fica em `docs/ADMIN-TOOLS.md`, fora do painel, e o smoke dev.122 confirma sua ausência. RUNTIME STATUS: PASS no escopo exercitado. |
| `0.1.0-dev.122` | Navegação administrativa do Fórum | `/painel-administrativo/forum` é a aba Início, intitulada Painel Growroom420, com resumo apenas; limites dos contextos ficam na documentação. Tópicos, Comentários e Configurações usam abas administrativas Drupal e filhos no menu lateral; filas aparecem como subabas de Comentários; páginas canônicas mantêm filtros fixos pelo Domain ativo. PHPUnit 21/309, PHPCS e gates Drupal 11+/mapa/fronteira/slugs PASS; Firefox/X11 25/25; Runtime `drush cr` PASS, 0 tópicos/0 comentários, viewports sem overflow. RUNTIME STATUS: PASS no escopo administrativo. |
| `0.1.0-dev.121` | Tornar o painel central útil sem reunir ferramentas administrativas | Resumo cacheável de categorias e tópicos públicos filtrado pelo Domain Growroom420 e Entity Access; explicar quatro contextos e ligar à experiência pública. Unit direcionado 2/29 PASS; Runtime/X11 pendentes. |
| `0.1.0-dev.120` | Caminhos específicos do Fórum redirecionam ao painel próprio | Decisão F-D38: `/painel-administrativo/forum` permanece autônomo; caminhos de conteúdo, comentários, moderação, denúncias, configuração e movimento redirecionam para ele em GET/HEAD/POST. Rotas genéricas Core e configuração compartilhada continuam fora. Unit Forum + User 155 testes/831 assertions PASS; Kernel mover 3/84 PASS (5 depreciações de Domain contrib); PHPCS e gates Portal/mapa/slugs PASS. Runtime de teste recebeu importação parcial de apenas duas Views e `drush cr`; Router resolve GET/POST nos caminhos centrais e as Views mantêm apenas `default`; Fórum 0 tópicos/0 Comments. HTTP uid 1: página central 200 e sete redirects GET 302; logout confirmado, depois anônimo 403. Firefox/X11 autenticado 20/20; 12 caminhos estáticos terminam no painel, sem overflow em 390/768/1024 CSS px. Rotas dinâmicas não testadas por HTTP sem fixtures. Backup privado dos YAMLs anteriores. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.119` | Corrigir validação da seleção da Action VBO | `MoveForumTopicsAction` lê a seleção persistida pelo `ConfigureAction` em `FormState`, pois a instância de validação não recebe `setContext()`. `ForumTopicMoverKernelTest`: 3 testes/84 assertions PASS; 5 depreciações do Domain contrib. Sem escrita no Runtime. |
| `0.1.0-dev.118` | Mover tópicos em lote com Views Bulk Operations | Action contextual na View administrativa; seleção de até 50 tópicos, confirmação com destino, Batch API de 10 itens e resposta por tópico. Kernel cobre dois Nodes e bloqueio histórico. VBO e View ativos no Runtime de teste; Firefox/X11 autenticado selecionou dois tópicos e abriu configuração (5/7), mas não submeteu confirmação/Batch API porque o smoke não reconheceu os seletores do resumo. Fixtures removidas; Entity API confirmou Fórum vazio. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.117` | Painel central do Fórum geral | `/painel-administrativo/forum` é entrada canônica mínima, sem agregar outras telas; conteúdo fica em `/painel-administrativo/forum/conteudo`; sete paths antigos redirecionam ao painel em GET/HEAD. Suíte Unit 90/601, PHPCS e X11 34/34 PASS; gate de fronteira tem uma violação isolada em `aculta_mailer`, fora do escopo. Smoke de mover tópico 7/7; Fórum de teste permanece vazio; produção não acessada. |
| `0.1.0-dev.116` | Configuração própria na seção do Fórum | `ForumSettingsForm` Core em `/painel-administrativo/forum/configuracoes`, permissão restrita própria e entrada no menu Growroom. PHPUnit 19/246 e PHPCS PASS; smoke Firefox/X11 31/31, tela 200, checkbox `false`, POST confirmou persistência e anônimo 403. Config Runtime ausente foi restaurada ausente; install/sync false; 0 tópicos/Comments. RUNTIME STATUS: PASS para rota, access e gravação do estado seguro; habilitar movimento segue sem teste. |
| `0.1.0-dev.115` | Seção administrativa própria do Growroom420 | Código e config ativa migrados para `/painel-administrativo/forum`; menu lateral liga ao View de tópicos, categorias, bundle, denúncias e configuração compartilhada User. `drush cr` PASS; rotas/URLs/tarefas locais e access manager UID 1 PASS. Firefox ESR/X11 autenticado GET-only: 27/27, raiz e filas 200, sete caminhos antigos 404, Conteúdo sem a aba do Fórum; Revista segue em sua área. Em 390 px, 3 px de overflow restrito à tabela responsiva vazia; 768 px sem overflow. Fórum permaneceu vazio. RUNTIME STATUS: PARTIAL: filas vazias não exercitam operações sobre comentários reais; administração de categorias não percorrida visualmente. |
| `0.1.0-dev.114` | Transições Form API LMS e rebuild do cache | Aprovação/rejeição HTTP PASS, fixture limpa (0 tópicos LMS/0 Comments), `drush cr` PASS; controller resolve antes/depois, overview anônimo 403. A falha atribuída ao controller não se reproduziu. |
| `0.1.0-dev.113` | Validar fila e confirmações de moderação LMS com Comment real | Fixture isolada em Group próprio: fila pendente exibe o Comment e ações aprovar/rejeitar; ambas as confirmações GET retornam 200, sem POST. Filas publicadas/rejeitadas não exibem o Comment; anônimo recebe 403 para fila e tópico. Group, Node, relationship, Comment e sessão removidos; estado LMS restaurado (1 curso existente, 0 tópicos, 0 Comments). RUNTIME STATUS: PARTIAL; transições HTTP submetidas no adapter 0.1.0-dev.39. |
| `0.1.0-dev.112` | Provar construção das filas LMS pelo controller e View Core | Kernel invoca `LmsForumModerationController::comments()` contra a View instalável e exige display/argumento Group corretos e cache tag contextual. Arquivo: 1/110; suíte Kernel adapter 2/127 PASS (40 depreciações Core/contrib/LMS). PHP CLI 8.4.26 abaixo da baseline 8.5. Smoke autenticado das filas vazias também PASS em dev.111; renderização de linhas com comentários e operações continua pendente. |
| `0.1.0-dev.111` | Validar entity converters reais na rota LMS de moderação | Kernel executa conversores `entity:group`/`entity:comment` e `CustomAccessCheck`; Educador com Group dono passa; estudante e Group diferente negados. Unit 18/190, Kernel 2/122 PASS (40 depreciações). No Homelab, `drush cr` PASS; controller instanciado; três rotas ativas. Smoke autenticado GET-only como Educador: painel e filas publicadas/rejeitadas/pendentes HTTP 200; anônimo 403; `/` HTTP 200. Sessão temporária específica removida, sem fixtures nem alteração de configuração/conteúdo. Ações e linhas com comentários cobertas no adapter 0.1.0-dev.39; RUNTIME STATUS: PASS. |
| `0.1.0-dev.110` | Cobrir resolução real de access na rota de moderação LMS | Kernel executa `CustomAccessCheck` do Core com argumentos upcastados; Educador correto permitido, estudante e Group divergente negados. Unit 18/190 e Kernel 2/118 PASS (40 depreciações). Access real coberto no adapter 0.1.0-dev.39: Educador 200; estudante e anônimo 403 nas confirmações e filas. RUNTIME STATUS: PASS. |
| `0.1.0-dev.109` | Ações de aprovação e rejeição em filas LMS | Operações nas Views levam a confirmação Form API; manager valida tópico/Group/estado em leitura e submit. Kernel prova transições, estados e Educador versus estudante/Group incorreto. Unit 18/190 e Kernel 2/112 PASS (40 depreciações). View atualizada por importação parcial de um item; cache rebuild passou. Smoke autenticado executado no adapter 0.1.0-dev.39: operações na fila, confirmação obrigatória, POSTs 303 de aprovar/rejeitar, revalidação 403 e negação a estudante/anônimo PASS. RUNTIME STATUS: PASS. |
| `0.1.0-dev.108` | Painel e filas de moderação por Group no LMS | Rotas `/painel-administrativo/lms/forum` e três estados; View Embed sem endpoint global, argumento Group obrigatório e filtros fixos. Unit 18/184 e Kernel 2/102 PASS (40 depreciações de Core/contrib/LMS). Runtime: import parcial da View, cache rebuild, root 200, overview anônimo 403 e Groups inexistentes 404. Sem smoke autenticado ou ações na fila. |
| `0.1.0-dev.107` | Liberar leitura e aprovação de Comment para Educadores LMS | Adapter dev.15 autoriza somente `view`/`approve` quando o Comment está em `field_aculta_discussion` e o contexto resolvido concede `grade students`. Estudantes mantêm o acesso Core; `update`, `delete` e `administer comments` não são concedidos. Unit 16/165 e Kernel 1/62 PASS; Kernel reporta 37 depreciações Core/contrib/LMS. Rotas/Views das filas ainda não existem; integração Runtime DEFERRED. |
| `0.1.0-dev.106` | Rastrear adapter LMS e aplicar permissões Group contextuais | `core.extension` inclui adapter e `gnode`. Importação parcial revisada atualiza quatro Group roles e `lms_classes.settings`; os demais campos dos cinco itens coincidiam com a Config Sync e nenhum outro item foi alterado. Kernel 2/74 PASS, HTTP `/` e `/categorias` 200, tópicos/Comments zero. Sem conteúdo real, acesso Runtime ainda não exercitado; UI, Views e filas pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.105` | Corrigir injeção do serviço e habilitar adapter LMS no Runtime de teste | `#[Autowire]` aponta ao service ID real do `DiscussionContextResolver`; adapter e `gnode` habilitados com sucesso e oito configs default presentes. Unit 13/143 e Kernel 2/74 PASS (38 depreciações Core/contrib/LMS); smoke HTTP `/` e `/categorias` 200; zero tópicos/Comments. Config Sync/roles não reconciliadas; UI, filas e autorização Runtime ainda pendentes. RUNTIME STATUS: PARTIAL. |
| `0.1.0-dev.104` | Provar acesso de Educador à discussão da turma sem membership direto | Kernel LMS deixa curso/turma sob propriedade de outra conta e confirma que Educador membro do curso lê/modera tópico contextual via `grade students`; outsider global continua bloqueado. Adapter dev.12, 2 testes/74 assertions PASS (38 depreciações Core/contrib/LMS). Ativação e filas/UI do LMS seguem pendentes; Runtime DEFERRED. |
| `0.1.0-dev.103` | Homologar visualmente categorias vazias em X11 | Firefox ESR 153.4/geckodriver 0.37.1, GET-only em `/` e `/categorias`, 13/13 verificações por tela nos viewports 390/768/1024 e claro/escuro; banco segue com 0 tópicos/Comments legados. Capturas privadas. Listas e tópicos com atividade aguardam conteúdo efêmero aprovado. |
| `0.1.0-dev.102` | Aplicar configuração padrão do adapter em Kernel | `ConfigInstaller` instala bundle, Comment compartilhado, relationships Group e formatter na fixture LMS/Group; 2 testes/72 assertions PASS (38 depreciações Core/contrib). Não habilita módulo, não usa ModuleInstaller e não toca Runtime. |
| `0.1.0-dev.101` | Verificar coerência entre adapter e Config Sync | Unit confirma dependências de relationship types nos quatro Group roles, permission de leitura contextual e herança `grade students`; não habilita módulo nem importa configuração. Próximo: testar a instalação com o grafo completo de dependências. |
| `0.1.0-dev.100` | Validar cacheabilidade do acesso LMS | Kernel confirma variação de acesso por usuário/permissões e dependência de cache do tópico para o provider de contexto; nenhum comportamento no Runtime. Adapter ainda aguarda revisão integrada de acesso e filas. |
| `0.1.0-dev.99` | Configurar Group roles do LMS para tópicos | Config Sync limita visualização Group Node de tópico a membership de curso/turma e Educadores com role contextual; educator permission herda para turma. Kernel lê os quatro Group role configs reais e confirma estudante/Educador, outsider global não autorizado, Comment e moderação. Config Sync não importado. |
| `0.1.0-dev.98` | Integrar Comment real à autorização contextual LMS | Kernel persiste resposta publicada na turma, prova escrita de estudante, moderação exclusiva de Educador e bloqueio quando o campo está fechado. Kernel adapter 2/57 (38 depreciações), Unit adapter 11/123 e Forum 87/556 PASS. Runtime coberto no adapter 0.1.0-dev.38 (envio do estudante 303, outsider 403); habilitação segue pendente. |
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
| `0.1.0-dev.53` | Citar resposta pública com segurança contextual | `ForumQuoteHooks` oferece ação apenas para Comment publicado, legível e respondível no contexto resolvido; usa rota Core `comment.reply` e só preenche o pai exato da requisição. Builder tira marcação, limita 1.200 caracteres e escapa autoria/texto; 9/18 Unit e PHPCS PASS. LMS validado no Runtime (`https://aculta.toca.net.br`): link `Citar` só no Comment publicado, GET 200 com citação preenchida, POST 303 gravando resposta pendente pela moderação da Turma; forasteiro e Educador fora da Turma recebem 403. Prefill corrigido em dev.232 (`#default_value: NULL` no `text_format` não processado): 7 testes, 25 asserções. Wiki aguarda seu provider. RUNTIME STATUS: PARTIAL (Wiki). |
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
`/painel-administrativo/conteudo`. A decisão atual mantém um painel central
próprio em `/painel-administrativo/forum`. A entrada mostra somente links às funções filhas do primeiro nível; páginas e
Views continuam responsáveis pelas ferramentas, sem cópia no painel. Revista reúne publicações e comentários em
`/painel-administrativo/conteudo/revista`; LMS terá área em
`/painel-administrativo/lms/forum` restrita a curso/turma/atividade e autorização
de Educador; Wiki terá área em `/painel-administrativo/wiki/forum` para
comentários de verbetes. LMS e Wiki só ativam suas rotas junto dos respectivos
adapters e depois de provar isolamento por contexto. Ver [contratos de contexto](CONTEXTS.md).

### Painel central do Fórum geral

Decisão vigente F-D39, determinada pelo responsável em 2026-10-20:
`/painel-administrativo/forum` mostra somente links diretos às funções de
Tópicos, Comentários e Configurações. Filas de comentários são subabas dentro
da função Comentários; caminhos canônicos internos permanecem funcionais. Os
caminhos antigos sob `/painel-administrativo/conteudo/forum` redirecionam para
a entrada. Rotas de operação direta, como mover conteúdo, continuam sob suas
regras e não são expostas na primeira camada. Views preservam filtros de
Domain, bundle, estado e acesso. Nenhum painel de Revista, LMS ou Wiki é
incorporado ao Fórum. Ver [Administração do Fórum](ADMIN-TOOLS.md).

A opção de moderação inicial do primeiro comentário está em
`/painel-administrativo/aculta/participacao`, sob ownership
do `aculta_user`, pois afeta Fórum, Revista, Wiki e outros hosts, exceto LMS.
A seção do Growroom pode oferecer um link claro para essa configuração
compartilhada, mas não duplicar nem restringir seu efeito ao Fórum.

Revista fica em
`/painel-administrativo/conteudo/revista`; LMS em
`/painel-administrativo/lms/forum`, moderado por Educadores; Wiki em
`/painel-administrativo/wiki/forum` quando seu adapter existir. A seção do
Fórum geral não incorpora opções desses contextos.

## Produtores de eventos para estatísticas (ADR-0004 do aculta_statistics)

O `aculta_statistics` é a central de estatísticas. Cada módulo produtor emite um evento de atividade e o
statistics assina. O produtor não conhece o statistics e não envia dado pessoal.

- Feito (0.1.0-dev.127, dev.159 e dev.160): `ForumActivityEvent` para tópico, Comment legado e post revisionável criados (`aculta_forum.topic_created`,
  `aculta_forum.comment_created`, `aculta_forum.post_created`). Consumidor: `forum.topics.created`, `forum.comments.created` e `forum.posts.created`; entidades sincronizadas são excluídas das três métricas. O painel lê os agregados por sete dias quando `aculta_statistics` está ativo.
- Pendente: validação dos eventos no Runtime com conteúdo real.
- Pendente: validar a série temporal no Runtime com atividade real e revisar visualmente a tabela.

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

## Revisão visual pré-RC — índice público — 2026-10-21

- `forum-index-visual.mjs` foi executado em Firefox ESR/X11, anonimamente e
  somente com navegação/leitura GET, na raiz e em `/categorias` do host de teste
  do purpose `forum`. Resultado: 13/13 verificações por rota, 26/26 no total.
- As páginas apresentaram H1 único, região principal, quatro categorias na
  raiz e os 12 termos em `/categorias`; nenhum tópico de fixture ou bundle do
  Fórum contrib legado apareceu. Viewports CSS 390/768/1024 passaram sem
  overflow no documento, corpo, região principal ou cartões; capturas claro e
  escuro ficaram visualmente legíveis.
- Capturas em modo `0600`, fora do checkout:
  `/home/aculta/private/aculta/visual-tests/forum-rc-dev130-20261021/` e
  `/home/aculta/private/aculta/visual-tests/forum-rc-dev130-categories-20261021/`.
  Nenhum conteúdo ou configuração foi submetido. O banner de consentimento
  inicial permaneceu aberto; a inspeção não aceitou nem recusou consentimento.
- Escopo parcial: a lista estava vazia, então tópico, comentários, composer e
  estados autenticados não foram revistos nesta rodada. Leitor de tela,
  navegação completa por teclado e dispositivo físico seguem sem evidência.

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
cartões educacionais, busca e notificações privadas. A fundação do provider,
permissões Group e moderação contextual foram implementadas nas dev.92–108.
A dev.108 acrescenta rotas administrativas com filas isoladas de Comments por
curso/turma, provadas em Kernel para acesso contextual e estados; suas rotas e
View foram aplicadas no Runtime de teste. A dev.109 acrescenta ações Form API
para transições moderadas por Educador; a coluna foi importada parcialmente e
o cache reconstruído no Runtime de teste. Busca, notificações e associação de
atividades gerais (fora do fluxo H5P) continuam pendentes; não liberar busca
pública para contexto LMS.

Integração com H5P avançou na F15 de `aculta_lms_h5p` (2026-10-21): Educadores
associam um tópico existente do `aculta_forum` ao conteúdo H5P pelo campo
`field_h5p_content`; discussões gerais de atividade continuam usando
`field_lms_activity`. O adapter limita a seleção ao curso/turma autorizado,
revalida Group, matrícula e Entity Access, e apresenta a discussão sob o player.
Kernel cobre isolamento por conteúdo/curso/turma e o Runtime de teste confirmou
visualmente o player, o tópico contextual e a abertura do Node no host `cursos`
(HTTP 200); as fixtures temporárias foram removidas. Isso conclui a associação
H5P–Fórum para este fluxo, sem criar tópico automático nem contexto público.
Busca e notificações privadas LMS continuam pendentes. Evidências completas:
[`aculta_lms_h5p/CHANGELOG.md`](../../aculta_lms_h5p/CHANGELOG.md) e
[`aculta_forum_lms/CHANGELOG.md`](../../aculta_forum_lms/CHANGELOG.md).

### Busca e notificações privadas LMS — escopo mínimo decidido em 2026-10-22

Este é o ROADMAP vigente do adapter `aculta_forum_lms`; o módulo não mantém
`docs/` próprio. Nada desta seção foi implementado: a decisão abaixo apenas
fecha o que falta e o menor recorte aceitável antes de abrir código.

O que falta para busca privada:

- Não existe índice Search API nem datasource para `aculta_lms_topic` ou para
  os Comments `aculta_discussion_post` desse contexto. O índice público de
  `SEARCH.md` exclui LMS de propósito e não deve ser reutilizado.
- Não existe View/rota com argumento de Group. Acesso LMS vem do Group/Entity
  Access por conta, não dos grants de Node que `content_access` indexa e
  filtra; um filtro de grants não representa membership de curso/turma.
- Não há filtro de estado de moderação. Comments pendentes ou rejeitados da
  turma não podem aparecer, e a moderação do primeiro comentário deixa a maior
  parte das respostas de estudante pendente até aprovação do Educador.
- Não há trigger de reindexação quando um Comment é aprovado/rejeitado ou a
  membership muda, nem prova automatizada de que um estudante não encontra
  conteúdo de outro curso/turma.
- Não há entrada de interface, paginação, permalink para o Comment dentro do
  tópico, estado vazio nem cache contexts por conta/Group.

Escopo mínimo aceito para busca (não implementar agora):

- Busca a partir da página do curso/turma, com Group explícito na rota; sem
  entrada global, sem busca entre cursos e sem busca pública.
- Um índice dedicado, restrito a tópicos LMS com relação Group única válida e
  a Comments publicados; resultado só depois de revalidar Entity Access do
  tópico e do Comment para a conta atual, falhando fechado.
- Cache por `user` e pelo Group da rota (`max-age` igual ao do acesso), com
  prova Kernel de não vazamento entre curso/turma antes de qualquer uso no
  Runtime. Reindexação por evento fica fora do primeiro recorte; atraso de
  índice é aceitável desde que o access check em tempo real prevaleça.

O que falta para notificações privadas:

- O Comments LMS não emite evento para `aculta_messenger`; não há assinatura
  por tópico nem destinatários revalidados no momento da entrega.
- Não há regra para não notificar fila de moderação, nem idempotência por
  (Comment, destinatário), nem opt-in/opt-out do estudante.
- Não há prova de que uma notificação de curso/turma nunca chegue a quem
  perdeu membership ou acesso ao tópico depois da assinatura.

Escopo mínimo aceito para notificações (não implementar agora):

- Reutilizar Flag/`message_subscribe` e o worker de notificação privada de
  `aculta_messenger`; nada de fila, armazenamento ou transporte paralelo.
- Avisar somente a primeira resposta publicada de um tópico que a pessoa segue
  e ainda pode ler; nunca avisar Comments pendentes, rejeitados ou a fila de
  moderação.
- Revalidar acesso no envio, ser idempotente por (Comment, destinatário) e
  respeitar opt-in; entrega por e-mail e digest ficam fora deste recorte.

Continuam adiados: busca entre cursos, busca global de “meus cursos”,
relevância ajustada, digest, push e preferências por turma. Qualquer avanço
exige Kernel próprio e nenhuma rota nova no Runtime antes da prova de
isolamento.

## `0.1.0-dev.14` — Contrato Wiki420

Provider futuro, discussão única por verbete, `author_wiki` e navegação interna.
Não reimplementar Wiki nesta fase.

## `0.1.0-dev.15` — Integrações gerais

Messenger, Image, Gallery, PDF, Game, eventos, cache e suas políticas de acesso.

### Matriz de integração futura do ecossistema ACULTA

O Fórum deve compor capacidades existentes dos módulos e do tema, sem criar
armazenamento, políticas de acesso, transporte de mensagens, cartões ou
renderizadores concorrentes. Cada integração continua condicionada aos contratos
do módulo dono e à autorização contextual do tópico/comentário; o ACULTA420
apresenta os dados preparados pelo Portal, sem assumir lógica de domínio.

| Dono da capacidade | Integração planejada no Fórum | Ordem e condição de avanço |
| --- | --- | --- |
| `aculta_image` | Upload contextual, ingestão, política de extensão/tamanho, processamento, uso e limpeza segura das imagens anexadas. | Primeiro: validar o modal próprio, 10 MB padrão configurável para valor superior, extensões administrativas e vínculo de publicação ao comentário visível. |
| `aculta_gallery` + `media_gallery` | Criar/inserir galerias pelo editor do Fórum e reaproveitar o display de slides/PhotoSwipe. Galerias são permitidas em todos os purposes; anexos continuam sujeitos à política contextual do Fórum. | Primeiro, junto com imagens: provar publicação/despublicação sincronizada com comentário e tópico, acesso, remoção de Media/Galleries órfãos após uma hora e compatibilidade com usos editoriais existentes. Files temporários seguem `system.file:temporary_maximum_age`. |
| ACULTA420 / design system | Aplicar modal, cartões e estados de anexo com tokens e componentes existentes; criar componente por domínio apenas quando não houver composição adequada. | Acompanhar cada integração funcional; revisar teclado, leitor de tela, responsividade e temas claro/escuro antes de RC. |
| Office local | Pré-visualização progressiva de DOCX e, se a validação confirmar suporte seguro e útil, planilhas; manter metadados e download como fallback. | Depois de imagem/Gallery. Validar bibliotecas locais, sanitização, tamanho/tempo de processamento e acesso privado; não transferir anexos para serviços externos. |
| `aculta_pdf_viewer` | Cartão com miniatura e metadados, link para abrir o leitor completo em outra janela por padrão, com opção administrativa para mesma janela. O PDF não será embutido no comentário. | Por último: aguardar o módulo ACULTA Document Viewer e integrar seu contrato e API reais; não duplicar reader, thumbnail ou conversão no Fórum. |
| `aculta_user` | Reutilizar identidade, autoria, perfis/cartões, regras de participação e ferramentas de denúncia/moderação já mantidas pelo módulo. | Consolidação: mapear capacidades ainda não ligadas ao Fórum e validar acesso/cache contextual sem alterar comportamentos de Revista, Wiki ou LMS. |
| `aculta_messenger` | Reutilizar notificações e avisos a seguidores, incluindo primeira publicação de `ForumPost`, política de transferência ao mover comentários e idempotência dos eventos. | Messenger dev.75 usa a revisão publicada como watermark para contar aprovações fora da ordem de criação e restringe o aviso à primeira publicação. Persistência, destinatários e fluxo Runtime continuam pendentes. Sem transporte paralelo nem perda do histórico agregado existente. |
| `aculta_statistics` e `aculta_game` | Consumir eventos mínimos de atividade e conquistas já aprovadas, sem expor texto/PII nem transformar volume em ranking ou poder de moderação. | Validar eventos reais e agregados no escopo próprio; critérios novos exigem decisão de produto e defesa contra spam. |
| LMS, Wiki e demais submódulos | Integrar somente por adaptadores/contextos próprios, respeitando Group/membership, bundles e contratos de cada domínio. | Evolução independente; uma integração não amplia permissões globais nem mistura conteúdo entre contextos. |

Os critérios específicos de anexos e referências técnicas estão em
[`PESQUISA.md`](PESQUISA.md). A documentação de Gallery confirma que galerias
podem reunir quaisquer bundles Media configurados; acesso e publicação continuam
sendo contratos locais do Portal e dos módulos ACULTA
([guia oficial do Media Gallery](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/media-gallery/getting-started-with-media-gallery/creating-and-editing-media-galleries)).
Esta matriz é planejamento: não declara implementação, cobertura Runtime ou
estado RC para itens ainda não comprovados.

Integração inicial do gatilho de primeira resposta publicada com `aculta_game`
foi implementada em `aculta_game 0.1.0-dev.11` para Comments ACULTA e ampliada
na dev.16 para a primeira publicação de `ForumPost`. O Game reutiliza o trigger
existente, sem dependência do Fórum; publicação aprovada pode conceder uma vez,
enquanto Forum contrib, sync e autoria histórica são rejeitados. A homologação
integrada permanece pendente; estatísticas completas e novos critérios de
gamificação continuam na linha própria do Game.

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
`target_id` filtra somente o tópico da categoria. A dev.202 inclui posts
revisionáveis com grants do tópico hospedeiro aplicados antes de paginação e
totais. Search API 1.41 ordena por relevância decrescente quando a View não
define sort. A matriz automatizada integrada de grants/publicação/purpose e a
homologação pública com conteúdo real ainda precisam ocorrer antes de reindexar
ou ativar essa configuração no Runtime. LMS, Wiki, Revista e Comments não entram
neste índice público.

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

## Defeito upstream conhecido — Group 3.3.5 e desinstalação de relação

`GroupRelationshipType::postDelete()` (contrib `group` 3.3.5,
`modules/contrib/group/src/Entity/GroupRelationshipType.php:222`) substitui
`ConfigEntityBundleBase::postDelete()` sem chamar o pai, então a exclusão de um
tipo de relação não invalida o bundle em `entity_type.bundle.info`. A reconstrução
posterior do mapa de campos chama `GroupRelationshipStorage::getEntityClass()`,
carrega um tipo de relação já apagado e falha com
`Call to a member function getPluginId() on null`. A desinstalação de
`aculta_forum_lms` cai nesse caminho quando o `ModuleInstaller` remove um
`FieldConfig` depois de apagar as duas relações do adaptador.

Consequências práticas: o `LmsModuleLifecycleKernelTest` remove as relações
antes da desinstalação e limpa os caches de bundle e de campos, reproduzindo o
estado de um `cache rebuild`; no Runtime, o `drush cr` entre a exclusão das
relações e a remoção dos campos evita o erro, mas o defeito é do contrib.

Risco aceito por ora: nenhum código ACULTA depende do comportamento errado. Não
substituir a mitigação por patch local sem antes verificar se a versão seguinte
do Group corrige o `postDelete()`; registrar a correção upstream e reavaliar a
dependência `drupal/group: ^3.3` quando houver release com o pai chamado.

## `0.1.0-rc.2` — Correções

Corrigir regressões verificadas e repetir cobertura pertinente.

## `0.1.0` — Estável

Release notes, upgrade, operação, rollback e revisão final. Não implantar produção
automaticamente.

## Regras de avanço

- Cada fase encerra em commits pequenos na branch autorizada, com changelog, documentação,
  testes e limitações reais.
- Fase documental não atualiza versões de runtime de módulos/tema.
- Para operações de ambiente, seguir `docs/architecture/environments.md` e
  validar hostname, pwd, Git, Drush URI/banco, efeitos e recuperação.
- O estado local não comprova Runtime; usar PASS/FAIL/PARTIAL/DEFERRED.
- Variantes de autoria são contratos distintos por contexto. A autoria histórica do Growroom usa `author_growroom_historico`; ela nunca passa pelo resolver de contas User.
- O plano antigo F1–F9 e seus resultados permanecem histórico; decisões
  incompatíveis estão marcadas como superadas em `DECISOES.md`.

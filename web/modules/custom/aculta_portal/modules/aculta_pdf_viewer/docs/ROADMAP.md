# Roadmap — ACULTA PDF Viewer

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). PV7 só pode ser classificada RC quando todo o escopo e testes, CSP em Report-Only configurada sem enforcement, acessibilidade e validação visual pré-deploy aplicável estiverem concluídos. Alpha valida a coleta/análise dos relatórios no servidor de desenvolvimento; enforcement será decidido após Alpha. LiteSpeed/Cloudflare são validados depois do deploy em produção.

Este roadmap cobre somente o módulo compartilhado `aculta_pdf_viewer`. O
roadmap LMS permanece em [ACULTA-LMS-ROADMAP.md](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md).
Cada etapa deve permanecer pequena, revisável e rastreável por commits na branch
autorizada. Nenhuma fase aqui autoriza merge, deploy ou operação de configuração
no Runtime.

## Princípios e referência funcional

O Media/File do Drupal continua sendo a fonte de verdade para documento,
metadados, URL e acesso. O viewer usa o PDF.js local e só recebe arquivo
resolvido por Media autorizada ou campo File com acesso confirmado; conteúdo embutido não aceita URL
arbitrária. A ausência de miniatura nunca bloqueia o leitor nem o download
permitido.

Há duas referências distintas nesta linha. O TNC FlipBook 3D consultado é um
plugin **WordPress**; serve para ideias de produto como embed, toolbar, zoom,
busca, modos de leitura e tela cheia. O contrib Drupal [3D Flipbook] também foi
auditado no tag 11.0.2 (`79e9cd35b74f2045b97dc71fc8a73d0c4b2cfbd5`, de
2026-07-06). Seu conceito de formatter/visualização de PDF e integração com
Views foi comparado ao adapter Media do ACULTA. Não foi copiado código neste
release: a implementação Drupal cria entidade e armazenamento de PDF próprios,
e depende de assets jQuery/Bootstrap/Three.js e de uma biblioteca 3D cuja
licença comercial é exigida para certos usos. O projeto Drupal informa que não
é coberto pela política de avisos de segurança. Esses custos e a duplicação de
fonte de verdade não se justificam para o leitor ACULTA.

O que faz sentido portar é o contrato de apresentação de um arquivo escolhido
pelo editor e os comportamentos de leitura, reimplementados sobre Media/File e
PDF.js oficial. A base ACULTA já oferece formatter compartilhado, embed por
CKEditor 5, página, zoom, busca textual por página, tela cheia e fallback de
download autorizado. Efeito 3D, CSS, templates, nomes e assets do Flipbook não
fazem parte da integração. A leitura paginada continua o baseline acessível;
sumário/bookmarks e rolagem contínua ficam como incrementos próprios após
validação de custo, acessibilidade e desempenho em PDFs extensos.

## Fases

| Fase | Escopo e critérios de aceite | Dependências / estado | Rollback |
|---|---|---|---|
| PV0 — Contrato e fundação | Submódulo separado; serviço e formatter com DI; fronteiras Media/File/access/cache documentadas; nenhuma persistência paralela. | Implementado no código desta PR; Unit e Kernel isolados PASS. Runtime DEFERRED. | Desabilitar o submódulo após restaurar o formatter Core no display Media e sincronizar configuração somente em mudança aprovada. |
| PV1 — PDF.js local | PDF.js oficial com versão e proveniência fixas, worker local, ESM, controles básicos e fallback. Sem CDN e sem patch direto em contrib. | Implementado e smoke test isolado PASS. Versão atual indicada no Composer; Runtime DEFERRED. | Reverter dependência/library e selecionar formatter Core; não remover arquivos Media. |
| PV2 — Inserção de PDF na Revista pelo CKEditor 5 | Selecionar Media `document` na aba Documento da Media Library, inserir no formato `revista_editorial`, salvar e reabrir; o formatter compartilhado deve renderizar sem URL manual. O papel editorial deve poder criar Media PDF destinada à publicação. O bundle usa `public://`; não subir conteúdo confidencial. | Firefox confirmou no Runtime upload de PDF pelo formulário Drupal como `content_editor`, abertura da edição da Media e, em rodada anterior, seleção de Media existente pelo botão CKEditor, salvamento do artigo publicado e render PDF.js. Upload direto dentro do diálogo Media Library não foi verificado. | Revogar `create document media` e `edit own document media` do papel editorial, retirar o bundle `document` da seleção do editor ou voltar ao render Core; manter os Media/File e textos já publicados. |
| PV3 — Leitor integrado ao site | Leitura inline paginada responsiva e acessível em páginas com Media PDF. Implementa página anterior/próxima, zoom, busca textual por página, controles acessíveis, tela cheia e fallback autorizado. Realce textual, rolagem contínua e sumário/bookmarks são incrementos próprios. Sem JavaScript permanece o fallback autorizado. | Firefox 153.4 ESR no Runtime confirmou render de 14 páginas, navegação, zoom, busca, tela cheia e zero erros JS. Revisão visual da área rolável e smoke de acessibilidade inicial concluídos em PV6: desktop/viewport estreito, foco visível 3 px, nomes dos controles, live regions e alvos ≥44 px. CSP, WCAG ampliado, outros browsers e PDF grande permanecem no gate PV7. | Retirar a biblioteca de interação e manter link Core autorizado; arquivos Media permanecem. |
| PV4 — Miniaturas e preview | Integrar PDFPreview com miniatura da primeira página só para PDF público até 20 MiB; ImageMagick com timeout de 15 s; `public://pdfpreview`, JPEG 480×640 e erro fail-soft. Validar política/recursos no ambiente antes de habilitar processamento real. | Adapter fail-soft e referências opcionais implementados. Firefox no Runtime gerou e exibiu a miniatura JPEG da primeira página de um PDF público de 14 páginas pelo pipeline `aculta_image`; o arquivo de preview de teste foi removido após a homologação. Limites continuam sendo 20 MiB e timeout de 15 s; revisar com arquivos grandes e erro de conversão na PV7. | Desabilitar PDFPreview e manter leitor/link; não liberar PS nem modificar policy global sem revisão operacional. |
| PV5 — Arquivos privados e públicos | Usar somente a rota privada do Drupal Core para `private://`, com File/Media/campo autorizados, cache de render max-age zero e sem thumbnail; validar unpublished, revogação de acesso, Group/turma, endpoint e fallback em Runtime. | Código e Config Sync usam o bundle privado `course_document`, relação Group obrigatória e guarda de ciclo de vida para mantê-lo não publicado. O bundle público `document` permanece inalterado. No Runtime de teste, submódulo, bundle, relação e permissões de estudante/educador estão ativos; os itens de configuração PV5 não têm drift. Firefox 153.4 ESR renderizou o Media privado embutido em página (14 páginas); o endpoint Core devolveu 200 para membro e 403 para não membro e anônimo. Remover membership ou relação Media↔Group negou a URL conhecida com 403; restaurar cada uma devolveu 200. A Media ficou não publicada. Os fixtures e uploads foram removidos depois da execução. A confidencialidade base de Group está homologada; CSP, cache em múltiplos proxies e navegadores adicionais permanecem no gate PV7. | Desabilitar a relação e o submódulo; manter o formatter/link Core; não alterar nem apagar Media/File. |
| PV6 — Uso compartilhado e apresentação | Homologar o mesmo formatter/contrato para Revista420, artigos/blog, Wiki420, Fórum, projetos, documentos institucionais e LMS, sem acoplamento funcional ao LMS. ACULTA420 apresenta o contrato neutro do viewer, sem duplicar sua marcação funcional. | Concluída: Revista/artigos/blog/projetos usam Media `document` no formato `revista_editorial`; Wiki e Fórum oferecem `drupalMedia` em CKEditor 5 com pares editor/formato validados e `media_embed` restrito a `document`; documento institucional usa `aculta_pdf_file`; LMS usa `course_document` com controle Group. Kernel validou embed da Wiki; no Runtime, o render Drush do tópico de Fórum contém viewer/link autorizado e as páginas públicas de artigo, blog e projeto responderam 200 com o viewer. Firefox 153.4 ESR verificou 14 páginas, zoom, foco visível, nomes acessíveis, live regions, alvo mínimo de 44 px e viewport desktop/estreito; a página não transborda horizontalmente e o canvas pode rolar dentro do estágio ao ampliar. ACULTA420 já estiliza o contrato com tokens semânticos; não foi criado SDC redundante. | Restaurar `file_default` nos displays `node.document`; remover `editor.editor.wiki` e os editores de Media Embed, `media_embed` e tags `<drupal-media>` dos formatos `wiki` e `usuario_aculta`; restaurar `text_trimmed` no display do Fórum; conteúdo e arquivos permanecem intactos. |
| PV7 — RC, deploy Alpha e preparação de produção | Pré-RC exige CSP Report-Only sem enforcement, endpoint protegido, acessibilidade ampliada, testes de browser/arquivos/fallback/rede/cache/acesso/performance, gates e revisão visual pré-deploy. Após RC e deploy, Alpha valida relatórios no servidor de desenvolvimento; enforcement é decisão posterior. Produção vem após Alpha aprovado; LiteSpeed/Cloudflare ficam para validação pós-deploy produtivo. | 2026-10-20: `drupal/csp` 2.2.4 e `drupal/reporting` 2.2.0 ativos em Alpha; Report-Only true/enforce false. Decisão registrada: manter Report-Only e `dblog.settings:row_limit` atual de 1000; não ativar enforcement nesta etapa. DBLog limita quantidade, não prazo, e registra URL/User-Agent; revisar minimização, acesso e retenção antes da produção. Endpoint: Reporting API e legacy 202, objetos incompletos 400, >64 KiB 413, Content-Type incorreto 415, HEAD 405. Firefox ESR renderizou 14 páginas e validou página seguinte, zoom 125%, busca (5 resultados) e controles de 44 px. Miniatura `aculta_image` fixa em 480 px transbordou na captura 390×844; corrigida em ACULTA420 0.6.0-dev.125; Firefox confirmou miniatura contida e documento sem overflow em viewport efetivo 500 px. Em 0.1.0-dev.26, o cálculo preserva o ajuste à largura para dimensões atípicas, limita o canvas a 16 MP/16.384 px e troca renderizações abaixo de metade da escala pedida por mensagem acessível e link Core. Firefox ESR no Runtime renderizou o PDF público de 23 MB/14 páginas; fixtures temporários confirmaram a página 20.000 × 30.000 pt em 100% (653 × 980 px), a página 20.000 × 2.000.000 pt com fallback em 25% e a página 20.000 × 200.000 pt ampliada até 250%, limitada a 15.971.904 pixels/12.636 px de altura e indicada como 194% efetivos. Os fixtures foram removidos. Unit 25/25 (163 assertions, sem depreciações PHPUnit); Kernel PDF + Group 6/6 (145 assertions, 28 depreciações Core/contrib); Unit `aculta_image` 20/20 (71 assertions, 8 depreciações PHPUnit); Drupal 11+ 1005 checks, `validate-aculta-image`, .htaccess, endereços, status-index, foundation 366, design foundations 2103 e module-theme boundary 1608 PASS. A home do Alpha, numa visita limpa, gerou dois relatórios `style-src-attr` associados aos elementos `.vvjb-items` e `.ui-front`; amostras de script não vazias: zero. Arquivos locais pt-BR conferidos com o parser do Core: PO do Core e 12 PO contrib sem msgstr vazia; locale pt-BR também existe no pacote PDF.js. Isso não equivale à cobertura de todas as strings instaladas. O fetch inicial das traduções não foi repetido por rede. A versão 0.1.0-dev.28 acrescenta link na mesma origem para `web/libraries/pdf.js/web/viewer.html?file=...&locale=pt-BR`, somente depois de checar Media/campo/File e mantendo a URL Core protegida para PDF privado. O canvas continua padrão; teclado/leitor de tela e CSP Report-Only ainda precisam validação manual, inclusive com PDFs marcados e sem tags. Não é RC: revisão acessível ampliada, gates finais no branch principal e aprovação do responsável continuam pendentes; produção não foi acessada. | Reverter a fase liberando formatter Core/link; manter dependências até verificar referências no Composer/configuração. |

### Limite de acessibilidade da renderização

O canvas do viewer é raster: seu nome identifica a página, mas ele não torna
acessíveis o texto ou a estrutura interna do documento. O link ao PDF original
permanece disponível; sua leitura acessível depende de o arquivo estar marcado
corretamente e do leitor do navegador. O reader não declara conformidade WCAG
do conteúdo enviado. Uma camada textual/estrutural fica fora do escopo implementado
até avaliação específica: o upstream PDF.js registra limitações de teclado e
semântica associadas ao text layer ([#15635](https://github.com/mozilla/pdf.js/issues/15635),
[#20114](https://github.com/mozilla/pdf.js/issues/20114)). O código da versão
6.4.299 também mostra que o viewer oficial combina
[`TextLayerBuilder`](https://github.com/mozilla/pdf.js/blob/v6.4.299/web/text_layer_builder.js),
[`StructTreeLayerBuilder`](https://github.com/mozilla/pdf.js/blob/v6.4.299/web/struct_tree_layer_builder.js)
e a implementação de [`TextLayer`](https://github.com/mozilla/pdf.js/blob/v6.4.299/src/display/text_layer.js).
O viewer completo é oferecido como alternativa, sem assumir que a presença
dessas camadas torna documentos sem tags acessíveis.

## Próxima entrega

PV2 está aplicada no código e Config Sync: o Kernel percorre CKEditor Media
Embed → Media `document` → formatter → PDF.js. Com login único gerado no host
Conta, Firefox 153.4 ESR validou no Runtime a aba Documento da Media Library,
inserção da Media existente pelo botão CKEditor, salvamento e render do artigo
publicado. O leitor validou 14 páginas, navegação, zoom 125%, busca com cinco
resultados, tela cheia e zero erros JavaScript. Uma segunda rodada confirmou
upload/criação de Media pela UI como `content_editor` e acesso à edição após
conceder `edit own document media`. Todas as entidades e arquivos temporários
foram removidos. Nenhum Config Sync global foi importado.

PV3 está funcional para Media público e testado no Runtime. PV4 tem integração e
configuração versionadas com fallback fail-soft; o erro de bootstrap por serviço
contrib ausente foi corrigido com referências opcionais. Drush e os quatro hosts
de teste responderam sem erro 500, e Firefox gerou uma miniatura pública pelo
pipeline `aculta_image`. PV5 usa o bundle privado `course_document`; submódulo,
relação Group, papéis de turma e campos estão ativos no Runtime após importação
parcial dirigida. O Config Sync não mostra drift nos itens PV5. Firefox confirmou
o render privado e a matriz de acesso: membro 200; não membro/anônimo 403;
remoção de membership/relação 403. Os fixtures foram removidos. A frente de
contas/permissões não foi alterada para o LMS. PV6 concluiu o uso compartilhado e a
homologação visual/acessível inicial em 2026-10-17. A finalização de release
continua condicionada à CSP, revisão acessível ampliada e validação pós-deploy
na PV7. Não usar o bundle `document`
público para PDFs confidenciais.

PV7 segue como gate de release parcial: Firefox e Chromium, PDFs pequeno/grande,
fallback 503, rede lenta e worker sob CSP simulada foram verificados no ambiente
de teste. O cache de longo prazo agora recebe query versionada igual ao pacote
Composer. O aceite foi registrado pelo formulário real do Portal para a conta
sintética. Firefox salvou uma Media pela UI e abriu sua edição depois da
concessão mínima de `edit own document media` ao papel editorial. Media, File,
perfil e usuário de teste foram removidos. Em 2026-10-20, os três assets `.mjs`
responderam no Homelab com MIME JavaScript e cache de um ano. O adapter também
passou a confirmar que a fonte PDF existe e está legível antes de invocar
PDFPreview/ImageMagick. Auditar CSP estrita e ampliar a revisão acessível no
Homelab. LiteSpeed/Cloudflare e renderização após deploy são uma validação
separada, posterior ao deploy; nenhum deploy foi feito nesta fase.

### Auditoria do contrib Drupal 3D Flipbook

O release 11.0.2 foi inspecionado diretamente no commit registrado acima. Ele
define entidade `flipbook` com campo File PDF e capa própria, e seu JS inicializa
Three.js/3dflipbook via jQuery. A descrição do projeto requer licença da
biblioteca CodeCanyon para uso pago e a página do Drupal.org informa que o
projeto não está coberto pela política de avisos de segurança. Resultado: não
instalar nem copiar runtime/assets; a funcionalidade de apresentação de um PDF
é atendida pelo formatter Media do ACULTA sem entidade nem arquivo paralelo.
Referências a funções equivalentes devem ser implementações próprias com a
distribuição PDF.js já fixada. O adapter agora usa a rota privada do Core para
`private://`; validação ponta a ponta de Group, revogação, cache e browser
foi homologada no Runtime de teste: membro recebeu o PDF, e remover membership ou relação retornou 403. CSP, proxy e matriz de browsers seguem pendentes no gate de release.

## Referências

- [Drupal Core — incorporar Media no CKEditor 5](https://www.drupal.org/docs/core-modules-and-themes/core-modules/media-library-module/embedding-media-with-ckeditor-5)
- [TNC PDF Viewer — funções básicas](https://docs.tncflipbook.com/article/348-basic-features)
- [TNC FlipBook — opções e personalização](https://docs.tncflipbook.com/article/349-customization-options)
- [TNC — controles de toolbar](https://docs.tncflipbook.com/article/391-toolbar-defaults)
- [Drupal contrib 3D Flipbook — projeto distinto, fora da política de segurança](https://www.drupal.org/project/flipbook)
- [Drupal contrib 3D Flipbook — release 11.0.2](https://www.drupal.org/project/flipbook/releases/11.0.2)
- [PDF.js oficial](https://github.com/mozilla/pdf.js)
- [Drupal CSP — projeto](https://www.drupal.org/project/csp)
- [Drupal CSP — alterar a política do site](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/content-security-policy/altering-a-sites-policy)
- [MDN — diretiva `worker-src`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/worker-src)
- [Contrato de integração e acesso PDF ACULTA](../../../../../../docs/portal/PDF-VIEWER-INTEGRATION.md)

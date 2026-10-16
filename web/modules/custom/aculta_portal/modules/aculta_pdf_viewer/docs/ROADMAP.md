# Roadmap — ACULTA PDF Viewer

Este roadmap cobre somente o módulo compartilhado `aculta_pdf_viewer`. O
roadmap LMS permanece em [ACULTA-LMS-ROADMAP.md](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md).
Cada etapa deve permanecer pequena, revisável e rastreável na PR vigente. Nenhuma
fase aqui autoriza merge, deploy ou operação de configuração no Runtime.

## Princípios e referência funcional

O Media/File do Drupal continua sendo a fonte de verdade para documento,
metadados, URL e acesso. O viewer usa o PDF.js local e só recebe arquivo
resolvido por entidade Media autorizada; conteúdo embutido não aceita URL
arbitrária. A ausência de miniatura nunca bloqueia o leitor nem o download
permitido.

O TNC FlipBook 3D consultado é um plugin **WordPress**, não um módulo Drupal.
Usaremos como referência apenas ideias de produto: embed no conteúdo, leitor
responsivo, controles configuráveis, navegação por página, zoom, busca textual,
modos de rolagem, tela cheia e link acessível para abrir em área maior. O TNC
oferece ainda efeito 3D, mas ele não é requisito inicial: a leitura paginada
acessível do PDF.js é prioritária. Não copiar código, CSS, nomes, assets ou
interface proprietária; reaproveitar somente comportamentos, reimplementados
com Drupal Media/CKEditor 5, PDF.js oficial, Bootstrap e SDC ACULTA420 quando
aprovado.

O projeto Drupal contrib `flipbook` é distinto do TNC e não será instalado como
atalho: a página do projeto informa que não é coberto pela política de avisos de
segurança do Drupal. Qualquer alternativa contrib futura exige revisão própria
de licença, segurança, API e compatibilidade.

## Fases

| Fase | Escopo e critérios de aceite | Dependências / estado | Rollback |
|---|---|---|---|
| PV0 — Contrato e fundação | Submódulo separado; serviço e formatter com DI; fronteiras Media/File/access/cache documentadas; nenhuma persistência paralela. | Implementado no código desta PR; Unit e Kernel isolados PASS. Runtime DEFERRED. | Desabilitar o submódulo após restaurar o formatter Core no display Media e sincronizar configuração somente em mudança aprovada. |
| PV1 — PDF.js local | PDF.js oficial com versão e proveniência fixas, worker local, ESM, controles básicos e fallback. Sem CDN e sem patch direto em contrib. | Implementado e smoke test isolado PASS. Versão atual indicada no Composer; Runtime DEFERRED. | Reverter dependência/library e selecionar formatter Core; não remover arquivos Media. |
| PV2 — Inserção de PDF na Revista pelo CKEditor 5 | Confirmar o fluxo de autoria com `drupalMedia` no formato `revista_editorial`: selecionar/criar Media `document`, inserir no corpo, salvar, reabrir e renderizar pelo formatter compartilhado. Confirmar filtro `media_embed`, view mode, permissão de criação para `content_editor` e saída sem URL manual. O bundle usa `public://`, portanto o papel só deve enviar PDFs destinados à publicação. | Módulo/display e permissão estão aplicados no Runtime de teste; Kernel testa `drupal-media` → Media Embed → formatter → PDF.js. Ainda não há Media PDF ou embed publicado de Revista no Runtime para smoke test editorial; interação de autoria DEFERRED. | Revogar `create document media` do papel editorial, retirar o bundle `document` da seleção do editor ou voltar ao render Core; manter os Media/File e textos já publicados. |
| PV3 — Leitor integrado ao site | Leitura inline responsiva e acessível em páginas que exibam Media PDF. Já implementa página anterior/próxima, zoom, busca textual por página, controles acessíveis e fullscreen. Próximos itens: localizar/realçar o termo no canvas/text layer, modo de rolagem contínua, sumário/bookmarks e ajuste visual ACULTA420 autorizado. O modo de página deve funcionar sem 3D e sem depender de JavaScript para manter fallback autorizado. | Markup e pipeline de render Media Embed→formatter passam em Kernel. JavaScript passa `node --check`; browser smoke de interação não validado nesta sessão, Runtime sem PDF de amostra. Segurança/CSP, responsividade, acessibilidade, múltiplos browsers e PDF grande continuam pendentes. | Remover os controles novos/liberar biblioteca anterior e manter navegação básica com link Core; arquivos Media permanecem. |
| PV4 — Miniaturas e preview | Investigar/ativar PDFPreview apenas com toolkit, ImageMagick, Ghostscript e policies seguros por ambiente. Medir timeout, memória, CPU, cache de derivados e execução em lote. Leitor permanece funcional se geração falhar. | Bloqueado até compatibilidade estável do PDFPreview com ImageMagick disponível (1.2 declara `^3.7 || ^4.0`; ImageMagick 5.x não está confirmado), revisão de segurança e validação em Hostinger LiteSpeed. | Desabilitar formatter/gerador de preview e manter ícone/link Media; não enfraquecer policy global. |
| PV5 — Arquivos privados e públicos | Decidir e implementar, se necessário, entrega controlada compatível com File access para leitura embutida de `private://`; validar não publicado, acesso revogado, turma/Group, cache privado e fallback. Testar URLs assinadas/endpoint autorizado sem expor caminho. | A implementação atual dá viewer apenas a `public://` e não transforma link de download em leitor privado. Não usar PDFs confidenciais no bundle público `document`. Requer arquitetura e testes integrados de autorização/cache antes de código. | Voltar a bloquear embed privado; manter somente resposta de acesso e download servido pelo Core quando autorizados. |
| PV6 — Uso compartilhado e apresentação | Homologar o mesmo formatter/contrato para Revista420, artigos/blog, Wiki420, Fórum, projetos, documentos institucionais e LMS, sem acoplamento funcional ao LMS. Criar apresentação SDC/ACULTA420 somente após o contrato funcional ser aprovado e com autorização de mudança no tema. | Depende de PV2–PV5 conforme o caso; tema protegido por regra do repositório. | Consumidores retornam ao display Media/Core sem alterar arquivos ou conteúdo. |
| PV7 — Release candidate | Executar matriz de browsers, arquivos pequenos/grandes, erro/rede lenta, PDF.js/worker, CSP, acessibilidade, cache, acesso, performance e produção LiteSpeed; rever licença e atualização de PDF.js. Registrar evidências e gates na PR/release. | Todas as fases necessárias aprovadas. Runtime e produção são gates distintos; sem deploy automático. | Reverter a fase liberando formatter Core/link; manter dependências até verificar referências no Composer/configuração. |

## Próxima entrega

PV2 está parcialmente aplicada: módulo, formatter, permissão editorial e
Config Sync conferem; o teste Kernel percorre CKEditor Media Embed → Media
`document` → formatter → PDF.js. Para o smoke editorial real, falta PDF público
de teste e embed publicado no Runtime. O URI do Runtime foi confirmado para
`revista420.aculta.toca.net.br`; não houve importação ampla de Config Sync.
Próxima validação do viewer: browser com PDF de teste não confidencial para
interação de busca/zoom/tela cheia e publicação por CKEditor. O LMS continua
pausado até o foco do projeto retornar a ele.

## Referências

- [Drupal Core — incorporar Media no CKEditor 5](https://www.drupal.org/docs/core-modules-and-themes/core-modules/media-library-module/embedding-media-with-ckeditor-5)
- [TNC PDF Viewer — funções básicas](https://docs.tncflipbook.com/article/348-basic-features)
- [TNC FlipBook — opções e personalização](https://docs.tncflipbook.com/article/349-customization-options)
- [TNC — controles de toolbar](https://docs.tncflipbook.com/article/391-toolbar-defaults)
- [Drupal contrib 3D Flipbook — projeto distinto, fora da política de segurança](https://www.drupal.org/project/flipbook)
- [PDF.js oficial](https://github.com/mozilla/pdf.js)
- [Contrato de integração e acesso PDF ACULTA](../../../../../../docs/portal/PDF-VIEWER-INTEGRATION.md)

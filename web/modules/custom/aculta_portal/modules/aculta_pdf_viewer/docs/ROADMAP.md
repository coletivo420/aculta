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
| PV2 — Inserção de PDF na Revista pelo CKEditor 5 | Selecionar Media `document` na aba Documento da Media Library, inserir no formato `revista_editorial`, salvar e reabrir; o formatter compartilhado deve renderizar sem URL manual. O papel editorial deve poder criar Media PDF destinada à publicação. O bundle usa `public://`; não subir conteúdo confidencial. | Kernel e navegador confirmaram inserção de Media PDF existente pelo CKEditor, salvamento do artigo publicado e render PDF.js no Runtime. A Media fixture foi criada por Drush; upload/criação de Media via UI como `content_editor` segue DEFERRED, embora permissão e gate de papéis estejam presentes. | Revogar `create document media` do papel editorial, retirar o bundle `document` da seleção do editor ou voltar ao render Core; manter os Media/File e textos já publicados. |
| PV3 — Leitor integrado ao site | Leitura inline paginada responsiva e acessível em páginas com Media PDF. Implementa página anterior/próxima, zoom, busca textual por página, controles acessíveis, tela cheia e fallback autorizado. Realce textual, rolagem contínua, sumário/bookmarks e apresentação ACULTA420 são incrementos próprios após validação/acesso ao tema. Sem JavaScript permanece o fallback autorizado. | Firefox 153.4 ESR no Runtime confirmou render de 14 páginas, navegação, zoom, busca, tela cheia e zero erros JS. Corrigido o CSS que reduzia o canvas e anulava o zoom fora de tela cheia; revisão visual da área rolável pendente. CSP, acessibilidade, outros browsers e PDF grande permanecem pendentes. | Retirar a biblioteca de interação e manter link Core autorizado; arquivos Media permanecem. |
| PV4 — Miniaturas e preview | Integrar PDFPreview com miniatura da primeira página só para PDF público até 20 MiB; ImageMagick com timeout de 15 s; `public://pdfpreview`, JPEG 480×640 e erro fail-soft. Validar política/recursos no ambiente antes de habilitar processamento real. | Adapter fail-soft e referências opcionais implementados. Firefox no Runtime gerou e exibiu a miniatura JPEG da primeira página de um PDF público de 14 páginas pelo pipeline `aculta_image`; o arquivo de preview de teste foi removido após a homologação. Limites continuam sendo 20 MiB e timeout de 15 s; revisar com arquivos grandes e erro de conversão na PV7. | Desabilitar PDFPreview e manter leitor/link; não liberar PS nem modificar policy global sem revisão operacional. |
| PV5 — Arquivos privados e públicos | Usar somente a rota privada do Drupal Core para `private://`, com File/Media/campo autorizados, cache de render max-age zero e sem thumbnail; validar unpublished, revogação de acesso, Group/turma, endpoint e fallback em Runtime. | Código e Config Sync usam o bundle privado `course_document`, relação Group obrigatória e guarda de ciclo de vida para mantê-lo não publicado. O bundle público `document` permanece inalterado. No Runtime de teste, submódulo, bundle, relação e permissões de estudante/educador estão ativos; os itens de configuração PV5 não têm drift. Firefox 153.4 ESR renderizou o Media privado embutido em página (14 páginas); o endpoint Core devolveu 200 para membro e 403 para não membro e anônimo. Remover membership ou relação Media↔Group negou a URL conhecida com 403; restaurar cada uma devolveu 200. A Media ficou não publicada. Os fixtures e uploads foram removidos depois da execução. A confidencialidade base de Group está homologada; CSP, cache em múltiplos proxies e navegadores adicionais permanecem no gate PV7. | Desabilitar a relação e o submódulo; manter o formatter/link Core; não alterar nem apagar Media/File. |
| PV6 — Uso compartilhado e apresentação | Homologar o mesmo formatter/contrato para Revista420, artigos/blog, Wiki420, Fórum, projetos, documentos institucionais e LMS, sem acoplamento funcional ao LMS. Criar apresentação SDC/ACULTA420 somente após o contrato funcional ser aprovado e com autorização de mudança no tema. | Depende de PV2–PV5 conforme o caso; tema protegido por regra do repositório. | Consumidores retornam ao display Media/Core sem alterar arquivos ou conteúdo. |
| PV7 — Release candidate | Executar matriz de browsers, arquivos pequenos/grandes, erro/rede lenta, PDF.js/worker, CSP, acessibilidade, cache, acesso, performance e produção LiteSpeed; rever licença e atualização de PDF.js. Registrar evidências e gates na PR/release. | Todas as fases necessárias aprovadas. Runtime e produção são gates distintos; sem deploy automático. | Reverter a fase liberando formatter Core/link; manter dependências até verificar referências no Composer/configuração. |

## Próxima entrega

PV2 está aplicada no código e Config Sync: o Kernel percorre CKEditor Media
Embed → Media `document` → formatter → PDF.js. Com login único gerado no host
Conta, Firefox 153.4 ESR validou no Runtime a aba Documento da Media Library,
inserção da Media existente pelo botão CKEditor, salvamento e render do artigo
publicado. O leitor validou 14 páginas, navegação, zoom 125%, busca com cinco
resultados, tela cheia e zero erros JavaScript. O Media de teste foi criado por
Drush; upload/criação via UI pelo papel `content_editor` segue sem homologação.
Todos os nodes, Media e Files temporários foram removidos. Nenhum Config Sync
global foi importado. O LMS foi retomado nesta frente para implementar PDFs
privados de turma; edição de cursos e outras mudanças do LMS continuam fora do
escopo.

PV3 está funcional para Media público e testado no Runtime. PV4 tem integração e
configuração versionadas com fallback fail-soft; o erro de bootstrap por serviço
contrib ausente foi corrigido com referências opcionais. Drush e os quatro hosts
de teste responderam sem erro 500, e Firefox gerou uma miniatura pública pelo
pipeline `aculta_image`. PV5 usa o bundle privado `course_document`; submódulo,
relação Group, papéis de turma e campos estão ativos no Runtime após importação
parcial dirigida. O Config Sync não mostra drift nos itens PV5. Firefox confirmou
o render privado e a matriz de acesso: membro 200; não membro/anônimo 403;
remoção de membership/relação 403. Os fixtures foram removidos. A frente de
contas/permissões não foi alterada. A finalização de release continua
condicionada à homologação da tela de edição/autoria para PDF público, revisão
visual/acessível, matriz de navegador/CSP e PV6. Não usar o bundle `document`
público para PDFs confidenciais.

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
- [Contrato de integração e acesso PDF ACULTA](../../../../../../docs/portal/PDF-VIEWER-INTEGRATION.md)

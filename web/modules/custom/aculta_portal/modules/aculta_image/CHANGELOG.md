# Changelog — aculta_image

## 0.1.0-dev.24 — derivados temporários: vida útil preservada e tamanho atualizado — 2026-10-16

Classificação: PATCH.

- `GeneratedImageFileManager::registerTemporaryDerivative()` não renova mais `created` a cada renderização. O `ImageCleanup` seleciona temporários por `created`; renovar a cada render impedia a limpeza do derivado.
- Derivado já registrado e substituído no disco tem `filesize` atualizado e volta a temporário; a gravação só ocorre quando algo muda.
- Evidência: PHPUnit Unit `aculta_image` 14/14 OK (47 asserções). Kernel: DEFERRED (sem banco MariaDB de teste nesta sessão). Serviço instanciado no Runtime de teste após cache rebuild.
- RUNTIME STATUS: DEFERRED para Kernel; a limpeza de temporários por idade ainda não foi observada no Runtime.

## 0.1.0-dev.23 — derivados temporários gerados por processadores

- Novo serviço `aculta_image.generated_file` valida e registra derivados públicos gerados como File temporário; o ciclo existente os remove após `system.file:temporary_maximum_age` quando não há referências.
- `ImageRenderer::buildManagedImage()` centraliza a marcação da imagem e associa cache tags do File.
- O PDF Viewer usa esse fluxo para a miniatura pública do PDFPreview.

## 0.1.0-dev.22 — reconciliação da documentação M10 — 2026-10-16

- `MODELOS-DE-IMAGEM.md` agora reflete as verificações existentes no Runtime: persistência da legenda, viewport de 390 px, página pública e revisão Firefox/Orca/AT-SPI.
- As fases P3 a P6 foram marcadas como concluídas; as pendências operacionais permanecem no handoff.

## 0.1.0-dev.21 — auditoria R8 de consumidores e estilos — 2026-10-16

- Auditoria do código e do Config Sync não encontrou consumidor `legado_*`; todas as referências de estilo nas configurações nativas correspondem a `ImageCatalog::STYLES`.
- O gate `validate-aculta-image.php` passou sem usos de imagem fora do submódulo. O plano, handoff e registro de dívida anotam a conclusão de R8.
- Atualizado comentário de `ImageCatalog` para refletir o critério atual de manutenção de estilos antigos.

## 0.1.0-dev.20 — seletor de imagem e herança de papéis — 2026-10-16

- Documentação alinhada à hierarquia da PR #286: `email_confirmed` mantém a permissão direta de `usuario_aculta`; `estudante`, `associado`, `lms_teacher` e `content_editor` a herdam pela configuração atual.
- `gerente_loja` não herda `email_confirmed` na configuração verificada. Nenhum papel, conta ou permissão foi alterado nesta atualização.

## 0.1.0-dev.19 — limpeza do ciclo de vida e painel administrativo — 2026-10-16

- Substituições e remoções em campos de imagem/arquivo e textos ricos entram em fila; o cron revalida referências de `file_usage` e `entity_usage` antes de excluir.
- Temporários de imagem sem uso são limpos após `system.file:temporary_maximum_age` (seis horas no padrão), em lotes de até 100 por cron ou execução administrativa.
- A exclusão controlada invalida derivados do Image Style e remove metadados internos órfãos.
- Painel em `/painel-administrativo/imagens` com visão geral, relatório, limpeza e quarentena. Documentação, registro de helpers e regra para agentes atualizados.
- Runtime de testes confirmado por `--uri=https://aculta.toca.net.br` (`aculta_runtime`); cache reconstruído e quatro rotas verificadas com Drush.

## 0.1.0-dev.18 — limite de upload da capa do curso — 2026-10-16

- O widget de imagem de `capa_curso` agora aplica o limite de 10 MB de `UploadPolicy`; dimensões seguem sem limite máximo.
- Campo atualizado no Runtime via Field API e exportado; `config:status` não mostra diferenças nos seis arquivos do R7.
- Firefox, na sessão X11, confirmou o cartão usando uma imagem atribuída temporariamente ao curso 1; o estilo retornou JPEG 1280×720 (HTTP 200). O campo foi limpo e o cache reconstruído após o teste.
- Gate `validate-aculta-image.php` passou.

## 0.1.0-dev.17 — capa de curso no LMS (R7) — 2026-10-16

- Criado o campo nativo `group.lms_course.capa_curso`, com upload pela política `aculta_image` desde a configuração inicial.
- Os cartões das Views `courses` e `courses_catalog` usam `capa_curso` com a finalidade visual `destaque` (`aculta_destaque_16x9`).
- Formulário de edição e exibição padrão do curso incluem a capa.
- Configuração aplicada no Runtime via Entity API após bloqueio da importação parcial por dependência inválida alheia (`field.field.lms_activity.no_answer.field_content` → `filter.format.0`); cache reconstruído. `/courses` e `/cursos/catalogo` respondem 200. O curso atual está sem capa, então a imagem ainda aguarda conteúdo para conferência visual. Esta etapa não altera contas nem papéis.

## 0.1.0-dev.16 — mesclagem de mídias repetidas e quarentena — 2026-10-16

- Mesclados os grupos de arquivos idênticos da galeria 11, mantendo uma ocorrência por arquivo e o `alt` do menor ID de mídia em cada grupo (`126` e `128`). Mídias duplicadas `127`, `159` e `129` foram removidas; seus arquivos `223`, `255` e `225` foram enviados à quarentena `aculta_image` para retenção até 2026-11-15.
- O relatório de duplicatas exatas não aponta mais esses grupos. O inventário de candidatas agora exclui arquivos que já estão na quarentena, evitando reenvio duplicado.


## 0.1.0-dev.15 — barra do CKEditor multilinha — 2026-10-16

- `full_html` e `usuario_aculta` usam a opção multilinha nativa do CKEditor; `revista_editorial` já tinha essa opção.
- No Drupal core, o marcador `-` remove o agrupamento automático e permite que a barra passe para novas linhas.
- Firefox no Runtime confirmou três linhas de botões a 500 px, sem botão de overflow. Nó temporário removido.

## 0.1.0-dev.14 — dropdown de modelos e revisão do texto alternativo — 2026-10-16

- Os quatro botões foram substituídos pelo dropdown `Modelo da imagem`, que indica o modelo da imagem selecionada e lista apenas as opções permitidas no formato.
- Configuração dos três editores importada parcialmente no Runtime; cache reconstruído. Firefox confirmou as quatro opções no `full_html`, seleção de Larga e gravação de `data-aculta-modelo="larga"`.
- O Drupal core avisa quando falta `alt`, permite abrir a edição por teclado e remove o aviso após salvar texto alternativo. Fluxo confirmado no Firefox; conteúdo temporário removido.

## 0.1.0-dev.13 — Escape no formulário de legenda — 2026-10-16

- O formulário contextual da legenda agora trata Escape como Cancelar e libera o handler ao destruir a view.
- Firefox no Runtime confirmou upcast da legenda salva, abertura pelo teclado (Enter), foco no campo e fechamento por Escape sem alterar os dados. Nó temporário não publicado removido após o teste.
- Leitor de tela, fluxo de `alt` e confirmação de `figcaption` numa página pública continuam pendentes.

## 0.1.0-dev.12 — estilos responsivos para os modelos de texto — 2026-10-16

- `revista-post.css` define largura de Pequena (50%), Padrão (580 px), Larga (900 px) e Destaque (960 px), com quebra para largura do corpo em telas até 575 px.
- A legenda usa tokens semânticos e mantém contraste nos modos claro e escuro.
- Firefox mediu os quatro modelos com o CSS e tokens reais do ACULTA420: 1366 px (larguras 320/580/900/960) e 500 px (484 px para todos, sem transformação lateral). Fundo/legenda mudaram corretamente entre claro e escuro.
- Aferição foi feita numa fixture local com o HTML emitido pelo filtro; uma página pública do Runtime ainda precisa ser revisada em navegador com sessão válida.

## 0.1.0-dev.11 — edição da legenda no CKEditor — 2026-10-16

- Botão “Editar legenda” abre um formulário contextual com campo rotulado e ações Salvar/Cancelar.
- A legenda é gravada em `data-aculta-legenda`, preservada no upcast/downcast e removida quando salva vazia. O filtro do servidor transforma o atributo em `figcaption`.
- A ação foi adicionada a `full_html`, `revista_editorial` e `usuario_aculta`; configurações importadas e cache reconstruído no Runtime.
- Firefox confirmou o formulário e a serialização do atributo; chamada direta ao filtro gerou `<figure>` e `<figcaption>` com legenda escapada. Reabertura em conteúdo salvo e página pública ainda precisam de validação integrada.

## 0.1.0-dev.10 — botões de modelo funcionam no overflow do CKEditor — 2026-10-16

- Corrigido o seletor do CKEditor: cada modelo agora é um botão próprio na toolbar, em vez de um dropdown aninhado que não abria no menu de overflow.
- O Runtime de testes recebeu os novos itens de toolbar em `full_html`, `revista_editorial` e `usuario_aculta`; cache reconstruído.
- Firefox confirmou a serialização de `data-aculta-modelo="padrao"` em `full_html` e de `pequena` em `usuario_aculta` e `revista_editorial`, inclusive no overflow com viewport útil de 488 px.
- Persistência confirmada: um artigo temporário não publicado preservou `data-aculta-modelo="larga"` ao reabrir o formulário; o nó foi removido depois da verificação.
- Upload real no Runtime: PNG de teste 1600×800 convertido para 1200×600; arquivo gerenciado removido e limpeza confirmada.
- Gate `validate-aculta-image.php`, `php -l` do plugin PHP e `node --check` passaram. Temas claro/escuro e viewport útil de 390 px ainda pendentes (o Firefox headless limitou a janela a 488 px).

## 0.1.0-dev.9 — modelo no CKEditor, formato Usuário ACULTA e conversão para 1200 px — 2026-10-17

- Plugin `acultaImage.ImageModel` no CKEditor 5, em JavaScript simples (sem build), com a biblioteca `ckeditor5.image_model`. Seletor de modelo na barra de ferramentas; a lista de modelos vem da configuração do editor (`acultaImageModel.models`).
- Botão de modelo ligado ao HTML completo e ao Revista — editorial. O HTML básico fica sem o botão até o formato próprio para usuários comuns e alunos.
- Crédito removido de todos os modelos (filtro e marcação). Fica somente a legenda.
- Formato `usuario_aculta` (Usuário ACULTA): HTML básico restrito, com o botão de imagem nos modelos Pequena e Padrão. Permissão concedida ao papel autenticado. O campo de resposta do fórum (`comment_forum`) passa a aceitar só esse formato.
- Limite de 1200 px: o core deixou de recusar imagens maiores (`max_dimensions` nulo nos editores com upload). O `aculta_image` reduz para 1200 px de largura as imagens enviadas aos editores, antes da compressão (`ImageCompressor::redimensionar`, `mogrify`). Imagens menores não mudam. Aceita e converte.
- Testes: 14 testes unitários, incluindo `ImageCompressorTest::testRedimensionamentoParaLargura`.
- Runtime: pendente. Importação de configuração, cache rebuild e teste no navegador ainda não executados.
- Documentação revisada para passagem: `docs/HANDOFF.md` (novo), `README.md`, `docs/PLANO.md` (roadmap atual), `docs/MODELOS-DE-IMAGEM.md` (estado e decisões), `docs/ESTILOS.md` e `docs/IMPORTACAO-GROWROOM.md` (correções e atualização de passagem).

## 0.1.0-dev.8 — og:image de compartilhamento, dimensão máxima e compressão própria no texto — 2026-10-17

- Estilo `aculta_compartilhamento_1200x630` (purpose `compartilhamento`) e hook `metatags_alter` que aponta `og_image` ao derivado da imagem da publicação (campos `field_media_image` e `field_image`). Verificado: publicação da Revista responde com `og:image` 1200×630, HTTP 200.
- Upload de imagem nos editores: dimensão máxima de 1200 px de largura (campo do core, que redimensiona no servidor) e 10 MB, nos três editores. Referência: coluna de leitura de cerca de 580 px (retina 1160 px) e variantes de jornais de 1024 px no corpo.
- Compressão sem perda do upload de texto (`ImageCompressor`, código próprio): optipng, jpegoptim e gifsicle, via hook `file_insert` em `public://inline-images/`. Testado sobre cópias de arquivos reais; o hook em si ainda não foi exercitado por upload real (o diretório `inline-images` pertence ao usuário `aculta` e não é gravável pelo terminal).
- Avatar das respostas do Fórum: estilo `aculta_avatar_pequeno` no modo `compact` do usuário.
- Testes unitários: 13 no total com `ImageCompressorTest`.

## 0.1.0-dev.7 — estilos revisados, criados e documentados; importação documentada — 2026-10-17

- Pesquisa de estilos (`docs/ESTILOS.md`): responsividade, 2× para alta densidade, recorte por proporção e a issue
  #3523451 (ordenação de `srcset` no Drupal 11), que obriga a manter o 1x/2x explícito.
- Criados: `aculta_destaque_16x9` (1280×720, recorte 16:9), `aculta_card_16x9` (640×360) e `aculta_avatar_pequeno` (96×96). Todos com o pipeline `aculta_derivados`.
- Removidos do Runtime: `max_325x325`, `max_650x650`, `max_1300x1300`, `max_2600x2600` (sem consumidor; só existiam no Runtime).
- Aplicação: abertura de tópico do Fórum usa `destaque`; página completa do projeto usa `aculta_destaque_16x9`.
- Upload de imagem nos editores de texto (`basic_html`, `full_html`, `revista_editorial`): limite de 10 MB, igual à política.
- Documentação: `README.md`, `docs/ESTILOS.md` e `docs/IMPORTACAO-GROWROOM.md` (alterações, caminhos, estado e bloqueio pela F3); `docs/PLANO.md` revisado.

## 0.1.0-dev.6 — PDF liberado no ImageMagick; compressão com pipeline (M4 e M8) — 2026-10-17

- Política do ImageMagick (`/etc/ImageMagick-7/policy.xml`, backup `.aculta-bak`): leitura de PDF, Ghostscript como delegate de execução e JPG nos formatos. Ver `docs/PLANO.md`.
- Miniatura de PDF testada com o PV4: JPEG 480×621, gerado pelo `pdfpreview` (configuração do repositório aplicada no Runtime, que tinha valores padrão).
- Compressão: dependências `drupal/imageapi_optimize` 4.2 e `drupal/imageapi_optimize_binaries` 1.2.0-beta1 (beta, fora da política de segurança do Drupal; escolha do responsável). Pipeline `aculta_derivados` com optipng e jpegoptim; 14 estilos exportados com `pipeline: aculta_derivados`; os 4 estilos `max_*` estão só no Runtime.
- Módulos habilitados e registrados em `core.extension`: `aculta_image`, `entity_usage`, `file_mdm`, `imageapi_optimize`, `imageapi_optimize_binaries`, `media_duplicates`, `sophron`.
- Homelab: optipng, jpegoptim, gifsicle, cwebp, ImageMagick 7 e SOAP instalados; `90-aculta.ini` com limites de upload de 128M.

## 0.1.0-dev.5 — recálculo de referências corrigido; paridade de binários e PHP com a Hostinger — 2026-10-17

- Recálculo de referências (`entity-usage:recreate`): causa — no nó 92 (blog "Bloco Sativa é objeto de Pesquisa na FIOCRUZ") a citação bibliográfica foi digitada no campo de URL de `field_references`. O link ficou inválido e o `entity_usage` lançava `InvalidArgumentException`.
- Correção no banco (`node__field_references` e `node_revision__field_references`, delta 0): a citação foi movida para o título do link e a URL ficou vazia. Nenhum conteúdo foi apagado. O recálculo passa a rodar sem exceção (538 entidades).
- Homelab: ImageMagick 7.1.1, Ghostscript, optipng, jpegoptim, gifsicle, cwebp e SOAP instalados. Limites de upload 128M via `90-aculta.ini`. PHP-FPM reiniciado.
- Hostinger: ver `docs/PLANO.md`, seção "Hostinger: o que está confirmado".
- Pendentes: política de PDF do ImageMagick (segurança) e processador da compressão (arquitetura). Ver o plano.

## 0.1.0-dev.4 — auditoria, duplicatas, quarentena e comparação visual (M4 a M7 e M9) — 2026-10-17

- Relatório `/painel-administrativo/imagens` (somente leitura): resumo do acervo, grupos de duplicatas exatas, pares visualmente parecidos e candidatas à revisão com motivos.
- Duplicatas exatas com `media_duplicates` 2.0.4 (checksum). No Runtime: 2 grupos, com 3 e 2 mídias.
- Candidatas com `entity_usage` 5.0.1: as sem referência em `file_usage` e sem entidade registrada. No Runtime: 13 candidatas, quase todas cópias de galerias.
- Quarentena (`ImageQuarantine`): envio move para `private://aculta-image-quarentena`; restauração; exclusão só após 30 dias e nova verificação de uso. Atualização `aculta_image_update_10002`. Testada no Runtime com arquivo de teste.
- Comparação visual (`DHash`): dHash de 64 bits com GD, sem dependência nova. Impressões calculadas no cron, 200 por execução (`aculta_image_update_10003`). Imagens que o GD não lê e imagens lisas são excluídas dos pares. Pares de cópias reais têm distância 0; fotos sequenciais muito parecidas ficam entre 4 e 6 bits. Sugestão para revisão, nada é alterado.
- Pendente (M8): compressão com `imageapi_optimize` depende de binários (`optipng`, `jpegoptim` etc.), que não estão no sistema, ou de serviço externo com chave. Decisão do responsável.
- Testes unitários: `DHashTest`, `QuarantineRulesTest` e `ImageIngesterTest` (11 no total).

## 0.1.0-dev.2 — migração dos consumidores e ingestão de imagem externa (pronto para importação) — 2026-10-17

- Migração (gate `validate-aculta-image.php` em PASS): estilos e limites de upload saem dos consumidores. Portal (painel da conta, instalação), aculta_user (foto de perfil, recorte, perfil público, limpeza de avatar, troca de foto), aculta_magazine (autoria, abertura do Fórum), aculta_gallery (grade e formulário de envio) e aculta_pdf_viewer (miniatura de PDF).
- Foto de perfil: limite de 2 MB passa a 10 MB, pela política única (decisão do responsável).
- Tema: a lógica de srcset do card sai de `ThemeHooks` e vai para `Hook/ImageStyleHooks`.
- Campos de imagem (`node.project.field_image`, `media.image.field_media_image`, `user.user.user_picture`) declaram a política em `third_party_settings`, com schema.
- `ImageIngester` (`aculta_image.ingester`): porta única para imagem externa. Valida extensão e tamanho, confirma que o conteúdo é imagem (PNG, JPEG, GIF ou WebP), calcula SHA-256, reaproveita arquivo existente com o mesmo conteúdo, grava em `public://importacao` sem sobrescrever e registra a origem em `aculta_image_origin` (uma linha por arquivo e URL).
- Atualização `aculta_image_update_10001`: cria a tabela `aculta_image_origin`.
- Correção: `ForumOpeningPost` recebe o renderizador no serviço (regressão encontrada no Runtime e corrigida).
- Testes: 5 unitários. Verificação no Runtime: páginas principais 200 (anônimo e logado); ingestão com criação, reaproveitamento, recusa de conteúdo falso, extensão fora da política e arquivo ausente.

## 0.1.0-dev.1 — barreira de uso obrigatório e catálogo de finalidades (M0.5) — 2026-10-17

- Catálogo de finalidades (`ImageCatalog`): 18 estilos existentes mapeados; única fonte de nome de estilo.
- Política única de upload (`UploadPolicy`): png, jpg, jpeg, webp, 10 MB. Sem dimensão máxima; o limite de 2 MB e 4096 px da conquista fica em `aculta_game`.
- Helpers registrados no Status do Portal (`aculta_portal.helper`).

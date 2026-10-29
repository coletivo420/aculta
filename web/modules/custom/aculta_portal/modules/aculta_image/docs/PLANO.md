# Plano — ACULTA Image (sistema único de imagens do site)

**Ciclo vigente:** [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). Status em 2026-10-26: versão **0.1.0-dev.33**. O campo histórico `commerce_product.produto_loja.field_imagens` foi alinhado à política `aculta_image` e o gate passou. A mesma política foi aplicada somente a esse campo no Runtime de teste; nenhuma importação global foi feita. A classificação permanece **Dev**: há bloqueios operacionais/integração. `preRC` foi aposentado; concluir pendências, gates/testes e revisão visual aplicável antes de RC. Alpha é deploy/validação no servidor de desenvolvimento; produção ocorre depois da aprovação Alpha.
Para retomar o trabalho, leia primeiro [HANDOFF.md](HANDOFF.md).

Documentos relacionados: [README](../README.md) (serviços e rotas), [MODELOS DE IMAGEM](MODELOS-DE-IMAGEM.md) (modelos no texto),
[ESTILOS](ESTILOS.md) (pesquisa e catálogo), [IMPORTAÇÃO DO GROWROOM](IMPORTACAO-GROWROOM.md) (dados e bloqueios).
O histórico completo das versões e das decisões antigas está no git (`git log -- web/modules/custom/aculta_portal/modules/aculta_image`).

## 1. Política

Todo o site usa o `aculta_image` para upload, derivados, entrega responsiva, auditoria de uso e limpeza de imagens e
arquivos de imagem. Nenhum módulo do Portal cria estilo de imagem, processa imagem ou apaga arquivo por conta própria.
A regra está em `AGENTS.md` ("Imagens e arquivos de imagem") e é verificada pelo gate.

Processadores opcionais que geram derivados fora da Image API devem registrar o resultado por `aculta_image.generated_file`
e entregar a marcação por `aculta_image.render`. Derivados sem referência são temporários e entram na limpeza pela idade
`system.file:temporary_maximum_age`.

**Por que existe:** sem um ponto único, cada módulo cria o próprio limite de upload, o próprio estilo e a própria
exclusão de arquivo. O resultado era dívida espalhada e arquivos apagados sem verificação de uso.

## 2. Estado por fase

### Integração inicial com limpeza coordenada pelo Portal — 2026-10-22

O Portal implementou aculta_portal.unused_file_cleaner para verificar
referências comuns e coordenar remoção de File. ImageCleanup o utiliza quando
disponível; a quarentena exige o serviço e falha fechada sem ele. Antes de mover
uma imagem, a quarentena consulta o coordenador; antes de apagar uma entrada
vencida, repete a verificação pelo mesmo serviço. FileRemoverInterface
formaliza a fronteira: aculta_image continua dono da remoção física,
invalidação de derivados e metadados internos. A exclusão física só ocorre pelo
coordenador do Portal e pelo proprietário Image.

| Fase | Estado | Observação |
| --- | --- | --- |
| M0 inventário | feito | relatório `/painel-administrativo/imagens` |
| M0.5 barreiras | feito | gate com tolerância zero (R1 a R10), incluindo Views, modos de exibição e assets |
| M1 a M3 consumidores | feito | Gallery, Magazine, Usuário, Portal, PDF migrados para o `aculta_image` |
| M4 miniatura de PDF | feito no Homelab | ImageMagick 7 com Ghostscript; `pdfpreview` com JPEG 480×640; teste com PDF real: 480×621. Produção depende de confirmação da Hostinger |
| M5 duplicatas exatas | feito | `media_duplicates` 2.0.4; os dois grupos reais da galeria 11 foram mesclados, uma ocorrência por arquivo, mantendo o `alt` do menor ID |
| M6 auditoria de uso | feito | `entity_usage` 5.0.1; candidatas com motivos |
| M7 quarentena | parcial | 30 dias; restauração preservada; mover e excluir exigem verificação pelo coordenador global. Testes Unit confirmam retenção com referência File API e indisponibilidade do coordenador. Falta validar Kernel/integração em instalação limpa e revisão operacional. |
| M8 compressão | feito no Homelab | `imageapi_optimize` 4.2.0 + `imageapi_optimize_binaries` 1.2.0-beta1 (aprovado pelo responsável); pipeline `aculta_derivados`. Ganho medido: JPEG 0,7%, PNG 13,6% |
| M9 comparação visual | feito | dHash de 64 bits com GD, código próprio; cron em lotes de 200; limiar 6 bits |
| og:image | feito | estilo `aculta_compartilhamento_1200x630`; verificado HTTP 200 |
| Upload do texto (1200 px) | feito | redução para 1200 px de largura antes da compressão (`ImageCompressor::redimensionar`); imagens maiores são convertidas, não recusadas |
| M10 modelos de imagem no texto | feito | Runtime, Firefox, Orca/AT-SPI, persistência da legenda e renderização pública conferidos; detalhes em `MODELOS-DE-IMAGEM.md` |
| R8 migração e dívida | feito | Auditoria estática sem consumidores `legado_*`; todas as referências de estilo no Config Sync existem no catálogo. Gate sem ocorrências fora do `aculta_image`. R9 também audita estilos em Views e modos de exibição. |

## 3. M10 — Modelos de imagem no texto

Entregue no repositório (commits `1e6d5126`, `4a4f4b0c`, `19b779eb`, `6ba90058`, `42a0d5eb`, `9bfca6cb`, `fdc4c0ad`):

- Catálogo `ImageCatalog::MODELOS`: Pequena (320 e 640), Padrão (480 e 1160), Larga (800 e 1200), Destaque (1024 e 1200).
- Estilos `aculta_corpo_*` exportados no pipeline `aculta_derivados`.
- Filtro `aculta_imagem_modelo` (peso 20): transforma `<img data-aculta-modelo>` em `<figure>` com `srcset` e `sizes`,
  e legenda em `<figcaption>`. **Crédito removido** de todos os modelos.
- Plugin CKEditor 5 `acultaImage.ImageModel`, em JavaScript simples (sem build), com a biblioteca `ckeditor5.image_model`.
  Dropdown único na barra, com rótulo do modelo selecionado; opções vêm da configuração do editor (`acultaImageModel.models`).
- Classe PHP `Plugin\CKEditor5Plugin\ImageModel`: configura quais modelos cada editor oferece.
- Botão de modelo presente em `revista_editorial` e `usuario_aculta`; ausente em `webform_default`.
- Formato `usuario_aculta` ("Usuário ACULTA"): HTML restrito com Pequena e Padrão. A permissão direta pertence a `email_confirmed`; a herança atual também concede acesso efetivo a `estudante`, `associado`, `lms_teacher` e `content_editor`. `gerente_loja` não herda essa permissão.
- Tópicos e respostas do Fórum aceitam só `usuario_aculta`.
- Limite de upload: `max_dimensions` do core **desligado** nos editores com upload (o core recusava, e a decisão é converter). A conversão para 1200 px é feita pelo `aculta_image`.

**Entregas e próximos passos:**

1. **Runtime:** configuração importada e cache reconstruído. Upload de 1600×800 convertido para 1200×600; `user.role.authenticated`
   ainda tem drift de permissões alheias à imagem.
2. **Navegador:** Firefox confirmou no `full_html` as quatro opções, seleção e persistência do modelo. A barra multilinha ocupa três linhas a 500 px sem overflow. Página pública anônima: modelos renderizados a 1366, 500 e 390 px sem overflow; claro e escuro integrados aferidos a 390 px.
3. **Legenda:** formulário contextual grava `data-aculta-legenda`; no Firefox, um artigo temporário foi salvo e reaberto, preservando a legenda. O filtro público gera `<figcaption>`.
4. **CSS:** implementado no ACULTA420 e medido em 1366/500/390 px, claro e escuro. A configuração do modo escuro foi restaurada para `light` após o ensaio.
5. **Acessibilidade:** operação por teclado, aviso/edição de `alt`, foco da legenda e Escape verificados no Firefox. Orca/AT-SPI anunciou a legenda e o texto alternativo da imagem e expôs a relação semântica na árvore acessível.
6. **Formato do usuário:** concluído; `email_confirmed` recebe diretamente `usuario_aculta`; a herança da PR #286 estende o acesso efetivo a estudante, associado, professor e editor de conteúdo. `authenticated` isolado e `gerente_loja` não recebem esse formato.
7. **Tamanhos provisórios do Fórum** (Pequena e Padrão): medir em uso e revisar. Ver `MODELOS-DE-IMAGEM.md`.

## 4. Decisões tomadas

| Data | Decisão | Quem |
| --- | --- | --- |
| 2026-10-09 | Indexação e sitemap (política do projeto; fora deste submódulo) | responsável |
| 2026-10-17 | Compressão própria no texto, com `optipng`, `jpegoptim` e `gifsicle`; `imageapi_optimize_binaries` (beta) aprovado | responsável |
| 2026-10-17 | og:image de compartilhamento em 1200×630 | responsável |
| 2026-10-17 | Limite de dimensão do texto: 1200 px de largura, convertido (não recusado) | responsável |
| 2026-10-17 | Destaque **não** sobe para 1536 px; permanece em 1200 px | responsável |
| 2026-10-17 | Crédito removido de todos os modelos; legenda mantida | responsável |
| 2026-10-17 | `usuario_aculta` é o formato dos usuários e do Fórum; permissão direta em `email_confirmed`, com alcance efetivo conforme a hierarquia vigente | responsável |
| 2026-10-17 | Modelos do Fórum: Pequena e Padrão (tamanhos provisórios) | responsável |
| 2026-10-17 | Modelos no CKEditor em JavaScript simples, sem build npm | responsável |
| 2026-10-17 | Pesquisa de limites de fórum: fontes da comunidade, sem valor oficial; não adotados | agente (com ressalva) |

## 5. Roadmap

| Fase | Entrega | Aceite | Depende de |
| --- | --- | --- | --- |
| **R1 — Runtime do M10** | configuração importada em partes; cache rebuild; upload de teste | PNG de 1600×800 convertido a 1200×600; `user.role.authenticated` ainda tem drift alheio à imagem | feito; ampliar só se necessário após os demais testes |
| **R2 — Teste do seletor** | Firefox, login via `drush uli`; artigo não publicado removido após verificação | três editores gravam modelos; persistência após reabrir confirmada; modelos conferidos em página pública a 390 px, claro e escuro | feito |
| **R3 — Legenda no editor** | formulário contextual grava `data-aculta-legenda` | UI, serialização, `<figcaption>` público, persistência e reabertura do editor verificadas | R2 |
| **R4 — CSS dos modelos** | estilos responsivos em `revista-post.css` | fixture e página pública medidas em claro/escuro; página pública aferida a 1366/500/390 px | feito |
| **R5 — Acessibilidade** | texto alternativo, teclado, `figcaption` | Firefox/Orca/AT-SPI confirmaram aviso/edição por teclado, legenda e texto alternativo anunciados, além da semântica de imagem/legenda | R3 |
| **R6 — Formato do usuário** | `usuario_aculta` com permissão direta em `email_confirmed`; papéis herdados conforme `role_inheritance.settings` | `authenticated` isolado e `gerente_loja` sem acesso; estudante, associado, lms_teacher e content_editor com acesso efetivo | feito |
| **R7 — Fórum e LMS** | Fórum usa `usuario_aculta`; LMS recebe campo `capa_curso` com política `aculta_image` e finalidade `destaque` nos cartões | Campo de capa, limite de 10 MB e Views configurados no Runtime; Firefox confirmou o derivado 1280×720 num teste temporário, removido depois. Catálogos respondem 200 | F3/F6 do Fórum e LMS |
| **R8 — Migração e dívida** | auditoria de consumidores e estilos legados | nenhuma ocorrência `legado_*`; referências de estilos em configurações nativas correspondem a `ImageCatalog::STYLES`; gate PASS; resultado registrado em `DEBT-REGISTER` | feito em 2026-10-16 |
| **R9 — Configuração de imagens** | Views e modos de exibição usam estilos presentes no catálogo | gate verifica IDs `image_style` e estilos PhotoSwipe em `views.view.*` e `core.entity_view_display.*`; PASS em 2026-10-18 | feito |
| **Preparação de RC — validações de ambiente e integração** | Confirmar binários necessários para produção e fechar as pendências de importação/integração Growroom | Evidência, checklist, gates/testes e revisão visual pré-deploy concluídos antes de RC; Alpha valida o deploy no servidor de desenvolvimento e produção vem após aprovação | responsável e conclusão das fases do Fórum |

### M7 — Kernel de instalação reduzida — 2026-10-29

- `ImageFileUsageAccessKernelTest::testCleanupServicesBootWithoutPortalCoordinator`
  carrega os serviços reais `aculta_image.cleanup` e `aculta_image.quarantine`
  sem habilitar `aculta_portal`; confirma que a quarentena recusa a operação
  sem o coordenador. A classe de produção e o contrato fail-closed não foram
  substituídos.
- Suíte completa: 30 testes / 123 assertions PASS, 1 deprecação Core/contrib.
  PHPCS Drupal no Kernel focal, lint e `validate-aculta-image.php` PASS.
- Auditoria completa PHPCS Drupal (`--extensions=php`) falha em 32 arquivos
  existentes com 223 erros e 57 avisos. O novo Kernel focal está limpo; a dívida
  de estilo integral foi registrada para fase própria e não foi alterada aqui.
- Isso valida instalação reduzida e falha fechada. Ainda falta um Kernel de
  integração que carregue o coordenador real Portal com seu grafo de serviços;
  a tentativa de carregar o módulo Portal inteiro exigiu dependências além da
  fixture reduzida. O teste Unit continua cobrindo a decisão do coordenador e
  retenção da fila. Nenhuma escrita ou limpeza foi executada no Runtime.
- Hostinger: ImageMagick/Ghostscript continuam sem confirmação documental;
  Growroom continua bloqueado pela F3 do Fórum.

### PHPCS Drupal — limpeza do módulo — 2026-10-29

- Corrigida a formatação Drupal/DrupalPractice em 33 arquivos PHP, sem mudança
  de comportamento. PHPCS completo do módulo: 0 erros e 0 avisos (baseline
  anterior: 223 erros e 57 avisos em 32 arquivos).
- PHP lint: 33/33; Unit: 28 testes/118 assertions; Kernel: 2 testes/5
  assertions, com uma deprecação upstream de `media_duplicates_requirements`;
  `git diff --check`: PASS.
- Próxima fase: integração Kernel com o coordenador Portal real. Binários
  ImageMagick/Ghostscript para produção ainda sem confirmação; Growroom depende
  da F3 do Fórum. Nenhuma operação foi feita no Runtime.

### Integração Kernel com o coordenador Portal — 2026-10-29

- `ImageCleanupPortalCoordinatorKernelTest` registra `FileReferenceInspector`
  e `UnusedFileCleaner` reais do Portal, com `FileRemover` real do Image, numa
  fixture Kernel reduzida. Isso evita habilitar o Portal inteiro e suas
  dependências de integração não pertinentes ao caminho de limpeza.
- Um File de imagem fictício não referenciado é removido pelo serviço
  `aculta_image.cleanup`; um segundo File com uso registrado é mantido pelo
  coordenador. Kernel focal: 1 teste / 6 assertions PASS, uma deprecação
  upstream de `media_duplicates_requirements`. Suíte do módulo: 31 testes /
  129 assertions PASS; PHPCS 0/0 e PHP lint 40/40.
- A integração com instalação completa do Portal não está demonstrada por esta
  fixture. Binários ImageMagick/Ghostscript no Hostinger e Growroom seguem
  pendentes; nenhum arquivo real ou Runtime foi alterado.

### Retenção de referência estruturada — 2026-10-29

- O Kernel agora cria uma Media com campo `image` apontando a File e confirma
  que `FileReferenceInspector` detecta a referência estruturada e impede a
  remoção. O caso complementa File Usage; a mesma rodada também confirma que
  um File sem referências é excluído. A suíte completa passa com 31 testes /
  138 assertions; o módulo informa quatro depreciações upstream relacionadas
  a `media_duplicates`/Drupal.
- Referências em campos File/Image estão cobertas. Referências em texto
  formatado/revisões e a integração com o Portal completo permanecem pendentes.

### Referência embutida em texto formatado — 2026-10-29

- O Kernel cria uma página contendo UUID de File em campo `text_long`; o
  `FileReferenceInspector` encontra a referência e o coordenador mantém o
  arquivo físico. A suíte completa passa: 31 testes / 144 assertions, com
  quatro depreciações upstream de `media_duplicates`/Drupal.
- A varredura textual cobre a referência ativa. Referências históricas em
  revisões, validação da instalação completa do Portal, binários Hostinger e
  integração Growroom permanecem pendentes.

### Referência em revisão histórica — 2026-10-29

- O Kernel salva a referência UUID em uma revisão anterior de página, grava uma
  revisão corrente sem o embed e confirma que a busca de todas as revisões
  impede a remoção do File histórico. A suíte completa passa: 31 testes / 146
  assertions, quatro depreciações upstream de `media_duplicates`/Drupal.
- Referências ativas e históricas de campo e texto agora são verificadas na
  fixture. Permanecem validação da instalação completa do Portal, binários
  Hostinger e integração Growroom.

### Retenção sem coordenador — 2026-10-29

- Kernel confirma que uma imagem permanente enfileirada continua no banco e no
  storage se `aculta_portal.unused_file_cleaner` estiver indisponível. O item
  não é removido da fila, para nova tentativa após expirar o lease. O mesmo
  teste confirma que quarentena também falha fechada sem coordenador.

Fora deste ciclo: bloqueio automático de duplicatas durante o upload. O relatório continua sugerindo revisão administrativa.

## 6. Perguntas abertas

1. **Prevenção automática de duplicatas.** A mesclagem do acervo existente foi concluída. Decidir separadamente se o upload deve bloquear arquivos repetidos ou continuar apenas com relatório para revisão.
2. **ImageMagick e Ghostscript na Hostinger.** Não citados na documentação pública consultada em 2026-10-17. Miniatura de PDF
   e redução de largura dependem disso em produção. Confirmar pelo painel ou com o suporte.

## 7. Hostinger: o que está confirmado (2026-10-17)

Fontes: páginas de suporte da Hostinger e especificação pública da API (busca e leitura em 2026-10-17).

| Item | Situação confirmada |
| --- | --- |
| Binários de otimização documentados (Web e Cloud) | `optipng`, `jpegoptim`, `jpegtran`, `gifsicle` e `libwebp-tools` (`cwebp`). Exigem `shell_exec` liberado no painel |
| `exec` e `shell_exec` | desabilitados por padrão nos planos Web e Cloud; liberam-se em PHP Configuration, removendo a função de `disableFunctions` |
| ImageMagick e Ghostscript | **não citados** na página oficial atual de ferramentas de otimização; disponibilidade para Web/Cloud não confirmada em 2026-10-29 |
| Extensões PHP | SimpleXML e SOAP habilitadas por padrão; OPcache pré-instalada; ionCube para PHP 8.1 ou superior; `dl()` desabilitado. A lista completa não é pública |
| Limites de PHP | `upload_max_filesize` padrão 128M; opções 128M, 256M e 512M |

Consequências: a miniatura de PDF não pode ser prometida em produção sem confirmação. A compressão é viável com os
binários documentados, depois de liberar `shell_exec`.

Revalidação documental em 2026-10-29: a página oficial [What image optimization tools are supported at Hostinger](https://www.hostinger.com/support/3738441-what-image-optimization-tools-are-supported/) lista `optipng`, `jpegoptim`, `jpegtran`, `gifsicle` e `libwebp-tools`, com uso via SSH e `shell_exec` sujeito à configuração; ela não lista ImageMagick/Ghostscript. A página [Which media, compression, and web applications are supported at Hostinger](https://www.hostinger.com/support/which-media-compression-and-web-applications-are-supported/) cobre codecs/FFmpeg/GCC e também não confirma esses binários. Ausência na documentação não prova que estejam indisponíveis no plano contratado. A miniatura PDF continua sem validação para produção; confirmar com o plano/painel ou suporte antes do RC.

Verificação no servidor de desenvolvimento em 2026-10-29: `convert`, `magick`,
`identify`, `gs`, `optipng`, `jpegoptim`, `jpegtran`, `gifsicle` e `cwebp`
existem em `/usr/bin`; ImageMagick reporta `7.1.1-43`, e PHP tem GD e Imagick.
Isso comprova somente o servidor de desenvolvimento; não valida o plano da
Hostinger nem permite concluir a disponibilidade de ImageMagick/Ghostscript em
produção. Nenhum comando foi executado contra produção.

## 8. Paridade do Homelab (2026-10-17)

- ImageMagick 7.1.1 com Ghostscript 10.05; `optipng`, `jpegoptim`, `gifsicle` e `cwebp`; `php8.4-soap`.
- Limites de upload `128M` no CLI e no FPM: `/etc/php/8.4/{cli,fpm}/conf.d/90-aculta.ini`.
- `/etc/ImageMagick-7/policy.xml` (cópia `policy.xml.aculta-bak`): `coder` PDF com `read`; `coder` JPG nos formatos
  de leitura e escrita; `delegate` `gs` com `execute`, e só ele. A ordem das regras importa (a regra geral `none` vem antes).

## 9. Dependências Composer

Cada uma entrou com a fase correspondente (AGENTS, regra 14):

- `drupal/entity_usage` 5.0.1: referências que o `file_usage` do core não vê (links e imagens em texto formatado).
- `drupal/media_duplicates` 2.0.4: duplicatas exatas de Media por checksum, com modo relatório.
- `drupal/imageapi_optimize` 4.2.0: pipeline de compressão dos derivados.
- `drupal/imageapi_optimize_binaries` 1.2.0-beta1: processador dos binários (beta, aprovado pelo responsável).

Não adicionadas: `filehash` (o `media_duplicates` calcula o próprio checksum), `imageapi_optimize_webp` (WebP já é gerado
pelos estilos), `media_dupe_detect`, `duplicate_images`, `media_dedupe` (ver a tabela de avaliação no histórico do git).

## 10. Limpeza sem perda

Imagens candidatas do inventário continuam na quarentena privada por 30 dias, com restauração e nova checagem antes
da exclusão. Há uma exceção operacional para ciclo de vida normal: quando uma entidade substitui ou remove um campo de
imagem, o FID anterior entra na fila. O Portal verifica File Usage, Entity Usage, referências estruturadas e embeds; só
solicita ao `FileRemover` do Image a exclusão física quando todas as verificações forem claras. Revisões e outras
entidades mantêm a imagem; consultas não verificáveis preservam o item para nova tentativa após o lease. Sem o
coordenador Portal, a limpeza falha fechada. Temporários de imagem passam pela mesma checagem após
`system.file:temporary_maximum_age` (seis horas no Runtime), em lotes de 100. Arquivos do tema
continuam fora da limpeza de Media. O administrador pode executar a mesma rotina em `/painel-administrativo/imagens/limpeza`.

## 11. Assets estáticos de dependências Composer

O `PackagedAssetPublisher` publica imagens estáticas dos pacotes H5P Composer
sob seus caminhos estáveis em `public://h5p`, que correspondem ao wrapper
`assets://` do Drupal. Ele só aceita arquivos dentro das instalações Composer
de `h5p/h5p-core` e `h5p/h5p-editor`, mantém destinos imutáveis, registra
proveniência e `file_usage`, valida imagens raster por `UploadPolicy` e
`ImageInspector`, e filtra SVG ativo mantendo somente os elementos dos assets
H5P atuais. Isso preserva URLs relativas de CSS sem criar outro catálogo ou
storage de imagens.

Esse mecanismo é para assets versionados do Composer; não é API de upload.
Imagens de conteúdo enviadas por educadores continuam exigindo o fluxo Drupal
de upload e ingestão normal. O módulo H5P segue desabilitado até esse fluxo e os
testes de integração serem concluídos.

## 12. Fontes

- Image Optimize: https://www.drupal.org/project/imageapi_optimize
- Entity Usage: https://www.drupal.org/project/entity_usage
- Media Duplicates: https://www.drupal.org/project/media_duplicates
- File Hash: https://www.drupal.org/project/filehash (avaliado; não adotado)
- Hostinger: páginas de suporte sobre PHP e binários consultadas em 2026-10-17 (ver seção 7)
- Hostinger: documentação oficial de ferramentas de imagem e mídia reconsultada em 2026-10-29 (ver seção 7)
- Modelos de imagem dos veículos: ver `MODELOS-DE-IMAGEM.md`, seção 2

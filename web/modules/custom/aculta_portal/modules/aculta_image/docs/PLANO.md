# Plano — ACULTA Image (sistema único de imagens do site)

Status: **pronto para uso** (revisado em 2026-10-17, versão 0.1.0-dev.6). Gate `scripts/validate-aculta-image.php` em PASS.

Documentos relacionados: [README](../README.md) (serviços e rotas), [ESTILOS](ESTILOS.md) (pesquisa e catálogo),
[IMPORTAÇÃO DO GROWROOM](IMPORTACAO-GROWROOM.md) (alterações, caminhos e estado). Este plano guarda as decisões e o histórico das fases.

| Fase | Estado |
| --- | --- |
| M0 inventário | feito: relatório `/painel-administrativo/imagens` |
| M0.5 barreiras | feito |
| M1 a M3 consumidores | feito |
| M4 miniatura de PDF no sistema | **feito no Homelab**: ImageMagick 7 com Ghostscript; PDF liberado para leitura e `gs` como delegate de execução (ver abaixo); `pdfpreview` com JPEG 480×640. Teste com PDF real: miniatura 480×621. Em produção, depende de confirmação da Hostinger |
| M5 duplicatas exatas | feito: `media_duplicates` 2.0.4 em modo relatório; 2 grupos encontrados no Runtime |
| M6 auditoria de uso | feito: `entity_usage` 5.0.1; candidatas com motivos |
| M7 quarentena | feito: envio, restauração e exclusão só após 30 dias e nova verificação de uso |
| M8 compressão | **feito no Homelab** com `imageapi_optimize` 4.2 e `imageapi_optimize_binaries` 1.2.0-beta1 (decisão do responsável). Pipeline `aculta_derivados` (optipng nível 5, jpegoptim sem perda) ligado aos 18 estilos do catálogo. Originais não são alterados. Ganho medido em JPEG: 0,7%; o ganho esperado é maior em PNG (a medir) |
| M9 comparação visual | feito: dHash próprio com GD, calculado no cron em lotes de 200 |

Política do responsável: **todo o site usa o `aculta_image`** para upload, derivados, entrega
responsiva, auditoria de uso e limpeza de imagens e arquivos de imagem. Nenhum módulo do Portal
cria estilo de imagem, processa imagem ou apaga arquivo por conta própria.

## Hostinger: o que está confirmado (2026-10-17)

Fontes: páginas de suporte da Hostinger e especificação pública da API (busca e leitura em 2026-10-17).

| Item | Situação confirmada |
| --- | --- |
| Binários de otimização documentados para planos Web e Cloud | `optipng`, `jpegoptim`, `jpegtran`, `gifsicle` e `libwebp-tools` (`cwebp`). Exigem `shell_exec` habilitado no painel |
| `exec` e `shell_exec` | desabilitados por padrão nos planos Web e Cloud; liberam-se em Websites, PHP Configuration, PHP options, removendo a função de `disableFunctions` |
| ImageMagick e Ghostscript | **não citados** em nenhuma página consultada. Sem confirmação de disponibilidade |
| Extensões PHP | SimpleXML e SOAP habilitadas por padrão; Zend OPcache pré-instalada (habilitar por site); ionCube disponível para PHP 8.1 ou superior; `dl()` desabilitado. A lista completa não é publicada; a API lista extensões por conta, sem enumeração pública |
| Limites de PHP | `upload_max_filesize` padrão 128M, opções 128M, 256M e 512M (especificação da API) |

Consequências: a miniatura de PDF (M4) não pode ser prometida em produção sem confirmação da Hostinger.
A compressão (M8) é viável na Hostinger com os binários documentados, após habilitar `shell_exec` no painel.

Homelab (paridade, feita em 2026-10-17): ImageMagick 7.1.1 com Ghostscript 10.05; `optipng`, `jpegoptim`,
`gifsicle` e `cwebp`; SOAP (`php8.4-soap`); limites de upload `128M` no CLI e no FPM via
`/etc/php/8.4/{cli,fpm}/conf.d/90-aculta.ini`. O PHP-FPM foi reiniciado.

Alterações de segurança aplicadas no Homelab (autorizadas em 2026-10-17), em `/etc/ImageMagick-7/policy.xml`
(cópia em `policy.xml.aculta-bak`):
- `coder` `PDF` com direito `read`, e `coder` `JPG` acrescentado aos formatos de leitura e escrita de imagem;
- `delegate` `gs` com direito `execute` (Ghostscript), e só ele. Demais delegates continuam bloqueados.
A ordem importa: o ImageMagick 7 aplica a regra da posição seguinte à regra geral `none`.

Divergência registrada: os estilos `max_325x325`, `max_650x650`, `max_1300x1300` e `max_2600x2600` existem no
Runtime, mas não em `config/sync`. O gate só compara o repositório. Decidir se são exportados ou removidos.

Decisões ainda abertas (históricas, a revisar antes da próxima fase):
1. **Política de PDF do ImageMagick** (`/etc/ImageMagick-7/policy.xml`): o Debian bloqueia PDF. Liberar leitura de PDF
   é mudança de segurança (processo que lê arquivos enviados pelo público). Aguarda aprovação para liberar só
   o necessário.
2. **Processador da compressão (M8):** o `imageapi_optimize_binaries` (único processador para estes binários) está só em
   beta e fora da política de segurança do Drupal. Alternativas: (a) processador próprio em `aculta_image`, chamando
   os binários documentados com limite de tempo e lista fechada de argumentos; (b) aceitar o beta. Recomendo (a).

## 0. Prontidão para importação (Growroom)

Contrato para qualquer importação de imagem de origem externa, incluindo a reconstrução do Fórum (F6):

- Usar somente `aculta_image.ingester->ingest($caminho, $urlOrigem, $capturadoEm)`. Nunca gravar arquivo diretamente.
- Resultado: `created` (arquivo novo), `reused` (mesmo conteúdo já existente) ou `rejected` (com motivo). Recusas: extensão fora da política, tamanho fora do limite, conteúdo que não é PNG, JPEG, GIF ou WebP, arquivo ausente.
- Destino: `public://importacao/<2 primeiros caracteres do SHA-256>/<SHA-256>.<ext>`. Nunca sobrescreve.
- Origem: tabela `aculta_image_origin`, uma linha por arquivo e URL de origem. Repetir a importação não duplica.
- Imagens do Wayback ficam no armazenamento privado; a importação copia só o que for aceito pela política.
- Verificado no Runtime: criação, reaproveitamento, recusa de conteúdo falso, extensão fora da política e arquivo ausente.

## 1. Estado verificado (Runtime de teste, 2026-10-17)

| Fato | Evidência |
| --- | --- |
| 148 arquivos de imagem em `file_managed` (≈ 87 MB) | 135 com referência em `file_usage`, 13 sem nenhuma |
| Galerias são o maior peso: 112 arquivos, 77 MB em `public://galerias` | consulta em `file_managed` |
| Consumidores de `file_usage`: `media` (128 arquivos), `node` (3), `user` (6), `commerce_product` (7) | consulta em `file_usage` |
| Toolkit de imagem: **GD** (`system.image.yml`); GD com WebP disponível | `config/sync/system.image.yml`, `php -r` |
| 14 estilos de imagem já existem (avatar 256, cards 256 e 512, galeria miniatura 160 e stage 1000, lightbox 1600, produto 960×1200, wide 1090, etc.) | `config/sync/image.style.*.yml` |
| Saídas WebP já configuradas em `thumbnail`, `medium`, `large`, `wide`, `media_library` (efeito de conversão do core) | `config/sync/image.style.*.yml` |
| Miniatura de PDF (PV4) existe em código (`PdfPreviewThumbnailBuilder`), mas **inativa**: `aculta_pdf_viewer` está ativo no Runtime, porém `pdfpreview` e `imagemagick` estão desabilitados (config e Runtime); binário ImageMagick ausente no Homelab | `config/sync/core.extension.yml`, `aculta_pdf_viewer/docs/ARQUITETURA.md` |
| Limpeza existente só para avatares (cron): remove upload temporário e cópias do acervo sem uso | `aculta_user/src/Avatar/AvatarAssetCleanup.php` |
| Renderização de estilo fora de um serviço central: Gallery, Magazine, Usuário, Portal | `image_style` / `#theme image_style` em `aculta_gallery`, `aculta_magazine`, `aculta_user`, `aculta_portal` |

Conclusão: a fundação (toolkit, estilos, WebP, rastreio de uso do core e a rotina de limpeza
de avatar) já existe. O que falta é um ponto único de governança, dois ou três módulos da
comunidade para lacunas reais, e a ativação da miniatura de PDF.

## 2. Módulos da comunidade: versões verificadas

Verificado nas páginas oficiais em drupal.org (2026-10-17). Core do projeto: **11.4.10**.

| Necessidade | Módulo (projeto) | Versão recomendada | Compatibilidade declarada | Decisão |
| --- | --- | --- | --- | --- |
| Compressão de derivados | `imageapi_optimize` (Image Optimize) | **4.2.0** (24/04/2026) | ^9 \|\| ^10 \|\| ^11; mantido, cobertura de segurança | **Adotar** após teste no Runtime |
| WebP | `imageapi_optimize_webp` | — | Issues abertas de compatibilidade com Drupal 11.3 | **Não adotar**: WebP já é gerado pelos estilos do core |
| Hash SHA-256 de arquivos | `filehash` (File Hash) | **4.0.2** (03/10/2026) | ^11.3 \|\| ^12 | **Adotar** na fase de duplicatas |
| Rastreio de referências (inclui Entity Embed, links em texto e Layout Builder) | `entity_usage` | **5.0.1** (23/09/2026) | Drupal 11.4 e 12 | **Adotar** na fase de auditoria. A página indica 8.x-2.3 apenas para Drupal 10.3 e 11 |
| Duplicatas de Media por checksum, com modos relatório, restringir novas e restringir todas | `media_duplicates` | **2.0.4** (19/03/2026) | ^9.2 \|\| ^10 \|\| ^11; release estável coberta pela política de segurança; sem dependência fora do core | **Adotar** na fase M5 |
| Duplicatas por hash com reuso sugerido no upload | `media_dupe_detect` | 1.0.0-beta2 (26/03/2025) | ^10 \|\| ^11; sem release estável; "minimally maintained"; fora da política de segurança; exige File Hash | **Não adotar** |
| Duplicatas visuais (pHash/dHash) | nenhum módulo encontrado | — | — | Sem módulo: se for necessário, implementação própria com biblioteca justificada (fase posterior) |
| Duplicatas de arquivo por tamanho e MD5, com revisão de miniaturas | `duplicate_images` | sem release suportada | sem suporte a versões do Drupal declarado; último update em 2019 | **Não adotar** |
| Media Dedupe (alternativa) | `media_dedupe` | 1.0.0-alpha1 | ^10 \|\| ^11 | **Não adotar**: sem release estável |
| Relatório de duplicatas | `filehash_noo` / `filehash_report` | a verificar | exigem File Hash 3.x na documentação consultada | **Opcional**; avaliar após o File Hash 4.0.2 |
| Miniatura de PDF | `pdfpreview` (1.2) + ImageMagick | já escolhido pelo PV4 | — | **Reaproveitar** o que o `aculta_pdf_viewer` já tem |

Correções em relação à proposta anterior: Image Optimize 4.2.0 confirmado; Entity Usage deve
ser 5.0.1 neste core; File Hash deve ser 4.0.2 (não 3.2); o "Media Duplicates 2.0.4" da proposta
existe (`media_duplicates`, estável e coberto pela política de segurança), e não foi encontrado
em uma busca inicial. Ele entra como ferramenta de duplicatas exatas de Media. Não adotaremos nenhum módulo sem verificar a versão instalada e o código.

**Dependências Composer (AGENTS, regra 14).** Cada uma só entra com a fase correspondente:
- `imageapi_optimize`: compressão dos derivados gerados pelos estilos. O core não comprime
  JPEG/WebP além de `jpeg_quality`. Justificativa de otimização de banda, com teste de qualidade.
- `filehash`: SHA-256 persistido por arquivo. O core não guarda hash; sem ele a detecção
  de duplicata exata exige leitura de todos os arquivos a cada consulta.
- `entity_usage`: referências que o `file_usage` do core não vê (links e imagens em texto
  formatado, Entity Embed). Sem isso a auditoria de candidatas fica incompleta.

## Dependências Composer adicionadas (regra 14 do AGENTS)

- `drupal/entity_usage` 5.0.1: rastreia referências que o `file_usage` do core não vê (links e imagens em texto formatado, Entity Embed). Sem isso a auditoria de candidatas fica incompleta.
- `drupal/media_duplicates` 2.0.4: duplicatas exatas de Media por checksum, com modos de relatório e restrição. Estável, coberto pela política de segurança, sem dependências fora do core.
- Adicionadas depois: `imageapi_optimize` 4.2.0 e `imageapi_optimize_binaries` 1.2.0-beta1 (M8, decisão do responsável).
- Não adicionada: `filehash` (o `media_duplicates` calcula o próprio checksum).

## 3. Arquitetura proposta

### 3.1 Responsabilidades do `aculta_image`

Submódulo do `aculta_portal` com serviços pequenos, todos com injeção de dependência:

- `aculta_image.catalog` — **catálogo de finalidades** (avatar, card, capa, miniatura de
  galeria, visualização de galeria, banner, produto, PDF) mapeado para estilos existentes.
  Única fonte de nomes de estilo; o restante do site pede uma finalidade, não um estilo.
- `aculta_image.render` — gera o render array responsivo (`srcset`, `sizes`, largura e altura
  reservadas, `alt` obrigatório). Consumido por Gallery, Magazine, Usuário, Portal e pelo tema
  por meio de dados neutros do Portal.
- `aculta_image.upload_policy` — extensões, tamanho máximo e dimensão máxima de upload, em um
  único lugar. Hoje cada campo tem a sua (Gallery 10 MB, Game 2 MB, campos de imagem).
- `aculta_image.inventory` — inventário (M0): arquivos, bytes, formatos, estilos e referências.
- `aculta_image.derivatives` — provedores de derivado: estilos do core e a miniatura de PDF
  (ver 3.3). Cada provedor é um serviço com tag, registrado no Status do Portal.
- `aculta_image.audit` — candidatas (sem referência, duplicata, desconhecida), sempre com
  os motivos. Nunca apaga.
- `aculta_image.quarantine` — move candidatas revisadas para área privada, com janela de
  retenção, e só exclui depois de confirmação administrativa e nova verificação de uso.

### 3.2 Regra de uso (política "todo o site")

- Módulos e temas **não** chamam `image_style` nem `#theme => image_style` diretamente:
  pedem uma finalidade ao `aculta_image.render`.
- Dimensões e nomes de estilo **não** aparecem em Twig, CSS, JavaScript nem em configuração
  exportada além do catálogo.
- Um gate (`scripts/validate-aculta-image.php`, a criar) falha se novo código referenciar
  um estilo fora do catálogo ou apagar arquivo fora do `aculta_image.quarantine`.
- ACULTA420 recebe do Portal apenas `srcset`, dimensões e `alt` já resolvidos (contrato
  de dados neutros, sem Domain, sem nome de estilo).

### 3.3 PDF: reaproveitar, não duplicar

O `PdfPreviewThumbnailBuilder` atual (PV4) é movido para o `aculta_image` como provedor de
derivado `pdf`. O `aculta_pdf_viewer` passa a consumir `aculta_image.render` com a finalidade
`pdf_capa`. As regras (PDF público, até 20 MB, timeout de 15 s, JPEG 480×640, falha
silenciosa) são mantidas. A decisão de ativar `pdfpreview` e ImageMagick fica em aberto (perguntas).

### 3.4 Estilos: consolidar antes de criar

Revisado em 2026-10-17. Estilos existentes mantidos; três criados para lacunas comprovadas (`aculta_destaque_16x9`,
`aculta_card_16x9`, `aculta_avatar_pequeno`); quatro `max_*` sem uso removidos do Runtime. Pesquisa, catálogo e
pendências de aplicação: [ESTILOS.md](ESTILOS.md).

### 3.5 Limpeza sem perda

Generaliza `AvatarAssetCleanup` para todo o acervo de imagens. Ordem obrigatória:
1. inventário e classificação (usado, duplicado, sem referência, desconhecido);
2. revisão de referências fora do banco (CSS, templates, configuração, URL direta, revisões
   antigas e conteúdo não publicado);
3. quarentena com janela de retenção, em área privada, sem apagar;
4. exclusão só após confirmação administrativa e nova verificação de uso no momento da exclusão.
Arquivos do tema (logomarcas, assets estáticos) ficam fora da limpeza de Media.
Contador zero não basta para excluir.

### 3.6 Ambientes

- Homelab: GD (toolkit atual). ImageMagick só se a decisão 4 for positiva.
- Hostinger (produção): validar GD com WebP, disponibilidade de binários e limites de processo
  antes de qualquer fase que dependa deles. Nenhum executável é assumido sem verificação nos
  dois ambientes.
- Nenhuma fase altera produção; deploy segue a política do responsável.

### 3.7 Sistemas que consomem ou vão consumir imagens

Mapa de 2026-10-17. "Hoje" descreve o código; "Próximo" descreve o que a fase em implementação exige.

| Sistema | Hoje | Próximo (obrigatório via aculta_image) |
| --- | --- | --- |
| `aculta_gallery` | `image_style` na view; upload do embed com limite de 10 MB próprio | Finalidades `galeria_miniatura`, `galeria_visualizacao`, `galeria_lightbox`; upload pela política única |
| `aculta_game` | `managed_file` em `public://conquistas` com 2 MB e 4096 px próprios | Upload pela política única; imagem de conquista como finalidade `conquista`; sem `public://` direto |
| `aculta_user` | avatar, recorte (`AvatarCropForm`), troca (`AvatarRerollController`), foto (`UserPictureForm`), perfil público e memorial legado | Finalidades `avatar_pequeno`, `avatar_grande`; `AvatarAssetCleanup` vira provedor de limpeza do `aculta_image` |
| `aculta_magazine` | `ForumOpeningPost` e `ArticleAuthorsHooks` renderizam imagem com estilo | Finalidade `capa_revista` e `card_editorial` pelo render; sem estilo direto |
| `aculta_pdf_viewer` | `PdfPreviewThumbnailBuilder` (PV4) próprio | Provedor `pdf` do `aculta_image`; leitor consome a finalidade `pdf_capa` |
| `aculta_messenger` | `MessagesController` usa imagem de avatar | Avatar pelo render do `aculta_image` |
| Portal (`PortalController`, `PortalRequirementsController`, `TokenHooks`) | verificação de estilos e tokens de imagem | Requisito de catálogo: o Portal consulta o catálogo, não os estilos |
| LMS (`aculta_lms`) | sem campo de imagem; só `Event`. Capa de curso é previsão | Campo de capa obrigatoriamente com finalidade `capa_curso` e a política única desde o primeiro campo |
| Fórum (`aculta_forum`) | sem campo de imagem próprio; imagem do tópico vem da Revista (abertura) | Imagens de tópico e respostas pela política; reconstrução do Wayback (F6) entra só por `aculta_image.ingest` |
| Reconstrução do Fórum (F6) | capturas do Wayback nos diretórios `assets` e `html` de `/home/aculta/private/aculta/forum/restauro` (armazenamento privado, fora do Git) | Importação por `aculta_image.ingest`: hash, quarentena, origem e sem sobrescrita de arquivo atual |

Isso significa que o LMS e o Fórum não podem criar campo de imagem nem importar arquivo de
imagem sem passar pelo `aculta_image`. A barreira precisa existir antes de o primeiro campo
ser criado.

### 3.8 Barreiras de uso obrigatório

Objetivo: tornar fora do sistema o que for novo, e medir o que já existe para que a dívida não cresça.

**Regras de documento (B1).** Entram em `AGENTS.md` e `docs/portal/GUARDRAILS.md`:
- toda imagem e todo arquivo de imagem passam pelo `aculta_image`;
- novo estilo de imagem só nasce no catálogo;
- novo campo `image`, `file` ou `media` de imagem declara a política única no `third_party_settings`;
- importação de qualquer origem externa (Wayback, LMS, arquivos recebidos) passa por `aculta_image.ingest`.

**Gate estático (B2): `scripts/validate-aculta-image.php`.** Varre `web/modules/custom`,
`web/themes/custom/aculta420` e `config/sync`, e falha quando encontra fora de `aculta_image`:

| Padrão | Motivo |
| --- | --- |
| `'image_style'` ou `#theme => 'image_style'` | renderização de estilo direta |
| `managed_file` com `FileImageDimensions`, `FileSizeLimit` ou `FileExtension` de imagem | limite de upload fora da política |
| `upload_location` com `public://` para imagens | armazenamento sem regra |
| `ImageFactory`, `image.factory` | processamento de imagem fora do sistema |
| `image.style.*` em config nova | estilo fora do catálogo |
| `->delete()` em `File` ou `file_unmanaged_delete` | exclusão fora da quarentena |
| `type: image`, `file` ou `media` de imagem sem `aculta_image` em `third_party_settings` | campo sem política |

Linha de base (cliquet): a contagem atual de ocorrências (ver 3.9) fica registrada. O gate
aceita o que já existe, com identificação do arquivo, e falha se a contagem subir. A dívida
cai quando cada consumidor é migrado; ninguém pode aumentá-la.

**Gate de tema (B3).** Twig e CSS do ACULTA420 não referenciam nome de estilo, dimensão
fixa de imagem nem `image_style`. O conteúdo chega como `srcset`, largura, altura e `alt`
resolvidos pelo Portal, o que já segue a fronteira do Portal e do tema.

**Serviço obrigatório de formulário (B4).** Criar elemento de upload de imagem é feito por
`aculta_image.upload_element()`. Ele já aplica extensões, tamanho e dimensão da política.
Um elemento `managed_file` montado à mão vira falha do gate.

**Serviço obrigatório de exclusão (B5).** Apagar `File` de imagem é feito somente pelo
`aculta_image.quarantine`. Exclusão direta falha no gate e nas revisões de PR.

**Ingestão obrigatória (B6).** `aculta_image.ingest` é a única porta de entrada de arquivo de
imagem vindo de fora: calcula hash, aplica a política, registra a origem (URL, captura e data)
e nunca sobrescreve arquivo existente. É pré-requisito da F6 do Fórum, do LMS e de qualquer
importação futura.

**Registro no Status (B7).** O `aculta_image` tem helpers com a tag `aculta_portal.helper` e
registro no `PortalHelperRegistry`, para aparecer em ACULTA Portal > Status. O gate do Portal
já confere esse registro.

**Checklist de PR (B8).** Todo PR que toque imagem responde: "usa `aculta_image`? sim, ou
justificativa aprovada". Sem resposta, o PR não é mesclado.

**Dívida registrada (B9).** Cada ocorrência existente vai para `docs/operations/DEBT-REGISTER.md`
com dono, fase de migração e prazo. O cliquet impede aumento.

### 3.9 Linha de base da dívida (2026-10-17)

Contagem de ocorrências em `web/modules/custom` e no tema (ver as buscas em 3.7):

| Padrão | Ocorrências |
| --- | --- |
| `image_style` | 10 |
| `managed_file` | 3 |
| `FileImageDimensions` | 1 |
| `FileSizeLimit` | 3 |
| `FileExtension` | 3 |
| `public://` (todos os usos, não só imagens) | 22 |
| `ImageFactory` / `image.factory` | 2 / 1 |
| `file_unmanaged_delete` / `->delete()` em File | 0 / 0 |

O número é só a linha de base. O gate deve exigir o mesmo conjunto de arquivos e linhas, não só
o total, para que uma migração não seja compensada por código novo.

## 4. Fases (uma PR por fase, conforme AGENTS)

| Fase | Entrega | Aceite |
| --- | --- | --- |
| M0 | Inventário read-only (`aculta_image.inventory`) e página administrativa; sem dependências novas | Números batem com as consultas acima; nada é alterado |
| M0.5 | **Barreiras** (seção 3.8): regras em `AGENTS.md` e `GUARDRAILS.md`, gate `validate-aculta-image.php` com cliquet, registro de dívida no `DEBT-REGISTER` e checklist de PR. Antes de qualquer campo novo de LMS ou Fórum | Gate passa com a linha de base; teste de regressão: um `image_style` novo e um `managed_file` novo falham |
| M1 | Catálogo de finalidades e `aculta_image.render` com estilos existentes; migração da Gallery | Galeria exibe miniatura na grade e lightbox no clique; teste em claro, escuro e 390 px |
| M2 | Política de upload centralizada | Limites iguais em todos os campos de imagem; mensagens de erro do Form API |
| M3 | Consolidação dos estilos (3.4) e migração de Magazine, Usuário e Portal | Nenhuma referência a estilo fora do catálogo (gate) |
| M4 | Miniatura de PDF movida para o `aculta_image` (3.3) | Sem `pdfpreview` ativo: leitor e download continuam; com ativo: miniatura só para PDF público |
| M5 | `media_duplicates` 2.0.4 em modo relatório; `filehash` 4.0.2 só se o relatório precisar de hash de arquivo | Relatório de duplicatas exatas; nenhuma Media mesclada ou apagada; restrição só após aprovação (pergunta 3) |
| M6 | `entity_usage` 5.0.1 e auditoria de candidatas | Lista com motivos; comparação com a auditoria manual |
| M7 | Quarentena e exclusão supervisionada | Teste com arquivos de teste; exclusão só após confirmação; restauração testada |
| M8 | Compressão de derivados com `imageapi_optimize` 4.2.0 em lotes pequenos (CPU e memória limitadas) | Originais intactos; derivados comparados em qualidade |
| M9 | **Comparação visual própria** (decisão do responsável, 2026-10-17): fingerprint perceptual (dHash, com GD do core, sem dependência nova; pHash se a precisão exigir) para detectar imagens parecidas mas não idênticas, em modo relatório com revisão humana. Código próprio, com referência a projetos similares e bibliotecas avaliadas uma a uma (justificativa escrita antes de qualquer dependência) | Relatório com pares de possíveis duplicatas e a precisão medida em um conjunto de teste rotulado; nenhuma Media alterada |
| RC | Validação no Homelab e verificação de viabilidade na Hostinger | Relatório de ambientes |

Prevenção de duplicata (bloquear upload) fica fora deste ciclo: depende do resultado do M5 e de
decisão sua (pergunta 3).

## 5. Perguntas de arquitetura

1. **Nome.** Confirma `aculta_image` (pedido) em vez de `aculta_media_optimizer` (proposta anterior)?
2. **Dependência.** `aculta_image` é obrigatório para o Portal, porque a política é "todo o site
   usa", ou opcional como o `aculta_gallery`? Opcional quebra a política se o Portal não puder
   funcionar sem ele; obrigatório aumenta o acoplamento com o Core deste módulo.
3. **Duplicatas.** Só relatório, com revisão humana, ou prevenção no upload? Duas Media com o mesmo
   arquivo podem ter crédito, texto alternativo ou restrição de acesso diferentes; mesclar exige regra.
4. **ImageMagick e miniatura de PDF.** Autoriza instalar ImageMagick (e Ghostscript, se exigido) no
   Homelab, com `sudo`? Sem isso a miniatura de PDF não sai. Na Hostinger a disponibilidade precisa
   ser verificada antes.
5. **Quarentena.** Onde guardar (área privada fora do webroot) e por quanto tempo (proposta: 30 dias)?
6. **Galeria.** O lightbox (1600 px) basta, ou o visitante precisa baixar o original? Originais de 5–10 MB
   não devem ser carregados pela grade.
7. **Estilos existentes.** Posso consolidar e renomear estilos (3.4) com teste visual, mesmo mudando
   dimensões usadas pelos componentes do ACULTA420?
8. **Limite de upload.** Padronizar em 10 MB e 4096×4096 px (como a Gallery e o Game) para todas as
   imagens do site?
9. **Ordem.** Começamos pelo M0 (inventário, sem risco) e depois pela Gallery (M1), ou prefere outra
   prioridade, como Magazine ou perfil?

10. **Barreiras.** Implemento agora o gate (M0.5) com a linha de base da seção 3.9, ou só depois de
    aprovar o plano? Uma barreira mal calibrada bloqueia PRs de outros sistemas, por isso proponho
    primeiro o modo somente-relatório por uma semana.
11. **LMS e Fórum.** Confirma que a capa de curso (LMS) e as imagens de tópico (Fórum) entram só pela
    política do `aculta_image` desde o primeiro campo, mesmo que isso atrase o LMS?
12. **Game.** A conquista usa limite de 2 MB e 4096 px. Mantenho esses números como limite da política
    única, ou cada finalidade pode ter o seu limite?
13. **Obrigatoriedade.** A regra vale para "todo novo código" (com cliquet para o existente) ou também
    exige migrar o existente antes de qualquer nova funcionalidade? Proponho a primeira opção.

### 3.10 Comparação visual (decisão)

Não há módulo de Drupal para comparação visual. Será código próprio do `aculta_image`, na fase M9.
Caminho preferido: dHash calculado com GD (já disponível no Runtime e no Homelab), sem dependência
Composer. Biblioteca só entra se o teste da fase mostrar precisão insuficiente, com justificativa
escrita pela regra 14 do AGENTS. Qualquer resultado é sugestão: não há restrição nem mesclagem
automática.

## 6. Fontes

- Image Optimize (`imageapi_optimize`): https://www.drupal.org/project/imageapi_optimize
- Entity Usage (`entity_usage`): https://www.drupal.org/project/entity_usage
- File Hash (`filehash`): https://www.drupal.org/project/filehash
- Media Dedupe (`media_dedupe`): https://www.drupal.org/project/media_dedupe
- Pesquisa de Media Duplicates e Image Optimize WebP: resultados de busca em 2026-10-17
  (sem página oficial de "Media Duplicates 2.0.4" encontrada).
- Código e configuração do próprio projeto citados na seção 1.

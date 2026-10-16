# Plano — ACULTA Image (sistema único de imagens do site)

Status em 2026-10-16: versão **0.1.0-dev.18**. Gate `scripts/validate-aculta-image.php` em PASS. Os testes unitários anteriores registram 14 casos.
Para retomar o trabalho, leia primeiro [HANDOFF.md](HANDOFF.md).

Documentos relacionados: [README](../README.md) (serviços e rotas), [MODELOS DE IMAGEM](MODELOS-DE-IMAGEM.md) (modelos no texto),
[ESTILOS](ESTILOS.md) (pesquisa e catálogo), [IMPORTAÇÃO DO GROWROOM](IMPORTACAO-GROWROOM.md) (dados e bloqueios).
O histórico completo das versões e das decisões antigas está no git (`git log -- web/modules/custom/aculta_portal/modules/aculta_image`).

## 1. Política

Todo o site usa o `aculta_image` para upload, derivados, entrega responsiva, auditoria de uso e limpeza de imagens e
arquivos de imagem. Nenhum módulo do Portal cria estilo de imagem, processa imagem ou apaga arquivo por conta própria.
A regra está em `AGENTS.md` ("Imagens e arquivos de imagem") e é verificada pelo gate.

**Por que existe:** sem um ponto único, cada módulo cria o próprio limite de upload, o próprio estilo e a própria
exclusão de arquivo. O resultado era dívida espalhada e arquivos apagados sem verificação de uso.

## 2. Estado por fase

| Fase | Estado | Observação |
| --- | --- | --- |
| M0 inventário | feito | relatório `/painel-administrativo/imagens` |
| M0.5 barreiras | feito | gate com tolerância zero (R1 a R8) |
| M1 a M3 consumidores | feito | Gallery, Magazine, Usuário, Portal, PDF migrados para o `aculta_image` |
| M4 miniatura de PDF | feito no Homelab | ImageMagick 7 com Ghostscript; `pdfpreview` com JPEG 480×640; teste com PDF real: 480×621. Produção depende de confirmação da Hostinger |
| M5 duplicatas exatas | feito | `media_duplicates` 2.0.4; os dois grupos reais da galeria 11 foram mesclados, uma ocorrência por arquivo, mantendo o `alt` do menor ID |
| M6 auditoria de uso | feito | `entity_usage` 5.0.1; candidatas com motivos |
| M7 quarentena | feito | 30 dias; restauração e exclusão só com nova verificação de uso. A limpeza de ciclo normal (troca/exclusão de campo) usa fila própria e apaga apenas após checar `file_usage` e `entity_usage`. |
| M8 compressão | feito no Homelab | `imageapi_optimize` 4.2.0 + `imageapi_optimize_binaries` 1.2.0-beta1 (aprovado pelo responsável); pipeline `aculta_derivados`. Ganho medido: JPEG 0,7%, PNG 13,6% |
| M9 comparação visual | feito | dHash de 64 bits com GD, código próprio; cron em lotes de 200; limiar 6 bits |
| og:image | feito | estilo `aculta_compartilhamento_1200x630`; verificado HTTP 200 |
| Upload do texto (1200 px) | feito | redução para 1200 px de largura antes da compressão (`ImageCompressor::redimensionar`); imagens maiores são convertidas, não recusadas |
| M10 modelos de imagem no texto | **parcial** | ver seção 3 |

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
- Formato `usuario_aculta` ("Usuário ACULTA"): HTML restrito com Pequena e Padrão. A permissão pertence a `email_confirmed`.
- Tópicos e respostas do Fórum aceitam só `usuario_aculta`.
- Limite de upload: `max_dimensions` do core **desligado** nos editores com upload (o core recusava, e a decisão é converter). A conversão para 1200 px é feita pelo `aculta_image`.

**Entregas e próximos passos:**

1. **Runtime:** configuração importada e cache reconstruído. Upload de 1600×800 convertido para 1200×600; `user.role.authenticated`
   ainda tem drift de permissões alheias à imagem.
2. **Navegador:** Firefox confirmou no `full_html` as quatro opções, seleção e persistência do modelo. A barra multilinha ocupa três linhas a 500 px sem overflow. Página pública anônima: modelos renderizados a 1366, 500 e 390 px sem overflow; claro e escuro integrados aferidos a 390 px.
3. **Legenda:** formulário contextual grava `data-aculta-legenda`; no Firefox, um artigo temporário foi salvo e reaberto, preservando a legenda. O filtro público gera `<figcaption>`.
4. **CSS:** implementado no ACULTA420 e medido em 1366/500/390 px, claro e escuro. A configuração do modo escuro foi restaurada para `light` após o ensaio.
5. **Acessibilidade:** operação por teclado, aviso/edição de `alt`, foco da legenda e Escape verificados no Firefox. Orca/AT-SPI anunciou a legenda e o texto alternativo da imagem e expôs a relação semântica na árvore acessível.
6. **Formato do usuário:** concluído; `email_confirmed` recebe `usuario_aculta`, e `authenticated` não recebe a permissão de edição.
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
| 2026-10-17 | `usuario_aculta` é o formato dos usuários e do Fórum; o papel `email_confirmed` recebe o seletor | responsável |
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
| **R6 — Formato do usuário** | `usuario_aculta` com seletor para `email_confirmed` | papel confirmado | feito |
| **R7 — Fórum e LMS** | Fórum usa `usuario_aculta`; LMS recebe campo `capa_curso` com política `aculta_image` e finalidade `destaque` nos cartões | Campo de capa, limite de 10 MB e Views configurados no Runtime; Firefox confirmou o derivado 1280×720 num teste temporário, removido depois. Catálogos respondem 200 | F3/F6 do Fórum e LMS |
| **R8 — Migração e dívida** | consumidores restantes e estilos `legado_*` (ausentes do catálogo) | gate sem aumento de dívida; `DEBT-REGISTER` atualizado | — |
| **RC — Validação de ambientes** | Homelab e Hostinger: `shell_exec`, ImageMagick e Ghostscript confirmados no painel | relatório de ambientes | responsável |

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
| ImageMagick e Ghostscript | **não citados** nas páginas consultadas. Sem confirmação |
| Extensões PHP | SimpleXML e SOAP habilitadas por padrão; OPcache pré-instalada; ionCube para PHP 8.1 ou superior; `dl()` desabilitado. A lista completa não é pública |
| Limites de PHP | `upload_max_filesize` padrão 128M; opções 128M, 256M e 512M |

Consequências: a miniatura de PDF não pode ser prometida em produção sem confirmação. A compressão é viável com os
binários documentados, depois de liberar `shell_exec`.

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
imagem, o FID anterior entra na fila e pode ser excluído pelo `FileRemover` se `file_usage` e `entity_usage` estiverem
vazios no momento da execução. Revisões e outras entidades mantêm a imagem. Temporários de imagem sem referência são
removidos depois de `system.file:temporary_maximum_age` (seis horas no Runtime), em lotes de 100. Arquivos do tema
continuam fora da limpeza de Media. O administrador pode executar a mesma rotina em `/painel-administrativo/imagens/limpeza`.

## 11. Fontes

- Image Optimize: https://www.drupal.org/project/imageapi_optimize
- Entity Usage: https://www.drupal.org/project/entity_usage
- Media Duplicates: https://www.drupal.org/project/media_duplicates
- File Hash: https://www.drupal.org/project/filehash (avaliado; não adotado)
- Hostinger: páginas de suporte sobre PHP e binários consultadas em 2026-10-17 (ver seção 7)
- Modelos de imagem dos veículos: ver `MODELOS-DE-IMAGEM.md`, seção 2

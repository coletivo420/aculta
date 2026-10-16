# Plano — ACULTA Image (sistema único de imagens do site)

Status em 2026-10-16: versão **0.1.0-dev.14**. Gate `scripts/validate-aculta-image.php` em PASS. Os testes unitários anteriores registram 14 casos.
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
| M5 duplicatas exatas | feito | `media_duplicates` 2.0.4 em modo relatório; 2 grupos reais no Runtime; nada é mesclado |
| M6 auditoria de uso | feito | `entity_usage` 5.0.1; candidatas com motivos |
| M7 quarentena | feito | 30 dias; restauração e exclusão só com nova verificação de uso |
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
- Botão de modelo presente em: `full_html` e `revista_editorial`. Ausente em: `basic_html` (até o formato próprio) e `webform_default`.
- Formato `usuario_aculta` ("Usuário ACULTA"): HTML básico com o botão de imagem limitado a Pequena e Padrão. Permissão no papel autenticado.
- Respostas do Fórum (`comment_forum`) aceitam só `usuario_aculta`.
- Limite de upload: `max_dimensions` do core **desligado** nos editores com upload (o core recusava, e a decisão é converter). A conversão para 1200 px é feita pelo `aculta_image`.

**Ainda não feito (ordem sugerida):**

1. **Runtime:** configuração importada e cache reconstruído. Upload de 1600×800 convertido para 1200×600; `user.role.authenticated`
   ainda tem drift de permissões alheias à imagem.
2. **Teste no navegador:** Firefox confirmou no `full_html` as quatro opções, seleção de Larga e gravação do atributo. Os três editores foram atualizados parcialmente no Runtime. Faltam temas claro/escuro, viewport útil de 390 px e revisão com leitor de tela. O Firefox headless limitou a janela em 488 px. Ver [HANDOFF.md](HANDOFF.md), seção 6.
3. **Legenda na UI do editor:** formulário contextual implementado; Firefox confirmou `data-aculta-legenda`. Falta validar o `figcaption` renderizado e reabrir o editor.
4. **CSS dos modelos** no ACULTA420: implementado e medido numa fixture com tokens reais (1366/500 px, claro/escuro); falta revisão numa página pública.
5. **Acessibilidade:** legenda ligada com `figcaption`, operação por teclado. Firefox confirmou upcast, abertura por Enter, foco e Escape sem alterar dados. O core avisa quando falta `alt`, permite editar por teclado e remove o aviso após salvar; imagem decorativa pode usar `alt` vazio quando marcada como decorativa. Falta leitor de tela e confirmação de `figcaption` numa página pública.
6. **Formato próprio para o HTML básico** (usuários comuns e alunos): hoje o básico não tem botão. Decisão de quem
   oferece modelos a cada perfil.
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
| 2026-10-17 | Botão de modelo só em `full_html` e `revista_editorial`; básico e Fórum por formato próprio | responsável |
| 2026-10-17 | Modelos do Fórum: Pequena e Padrão (tamanhos provisórios) | responsável |
| 2026-10-17 | Modelos no CKEditor em JavaScript simples, sem build npm | responsável |
| 2026-10-17 | Pesquisa de limites de fórum: fontes da comunidade, sem valor oficial; não adotados | agente (com ressalva) |

## 5. Roadmap

| Fase | Entrega | Aceite | Depende de |
| --- | --- | --- | --- |
| **R1 — Runtime do M10** | configuração importada em partes; cache rebuild; upload de teste | PNG de 1600×800 convertido a 1200×600; `user.role.authenticated` ainda tem drift alheio à imagem | feito; ampliar só se necessário após os demais testes |
| **R2 — Teste do seletor** | Firefox, login via `drush uli`; artigo não publicado removido após verificação | três editores gravam modelos; persistência após reabrir confirmada; falta claro/escuro e viewport útil de 390 px (Firefox limitou a 488 px) | feito parcialmente; ampliar cobertura |
| **R3 — Legenda no editor** | formulário contextual grava `data-aculta-legenda` | UI e serialização verificadas; falta confirmar `figcaption` público e persistência após reabertura | R2 |
| **R4 — CSS dos modelos** | estilos responsivos em `revista-post.css` | fixture medida em claro/escuro e 1366/500 px; falta confirmar numa página pública | R3 |
| **R5 — Acessibilidade** | texto alternativo, teclado, `figcaption` | aviso/edição de `alt` pelo core e teclado da legenda verificados no Firefox/Runtime; leitor de tela e página pública pendentes | R3 |
| **R6 — Formato do básico** | botão de modelo no HTML básico para perfis definidos | decisão de perfis | responsável |
| **R7 — Fórum e LMS** | Fórum usa `usuario_aculta` (feito na configuração); LMS com campo de capa `capa_curso` pela política | campo de capa do LMS com a política desde o primeiro campo | F3/F6 do Fórum e LMS |
| **R8 — Migração e dívida** | consumidores restantes e estilos `legado_*` (ausentes do catálogo) | gate sem aumento de dívida; `DEBT-REGISTER` atualizado | — |
| **RC — Validação de ambientes** | Homelab e Hostinger: `shell_exec`, ImageMagick e Ghostscript confirmados no painel | relatório de ambientes | responsável |

Fora deste ciclo: prevenção de duplicata no upload (bloquear), que depende de decisão sobre mesclagem (ver seção 6).

## 6. Perguntas abertas

1. **Prevenção de duplicata.** Só relatório com revisão humana, ou bloquear no upload? Duas Media com o mesmo arquivo podem ter
   crédito, texto alternativo ou restrição de acesso diferentes; mesclar exige regra.
2. **ImageMagick e Ghostscript na Hostinger.** Não citados na documentação pública consultada em 2026-10-17. Miniatura de PDF
   e redução de largura dependem disso em produção. Confirmar pelo painel ou com o suporte.
3. **Destaque acima de 1200 px.** Decidido manter 1200 px. Reabrir só se o responsável pedir.
4. **Tópicos do Fórum.** O corpo do tópico (`node.forum`) ainda aceita `full_html` e `basic_html`. Restringir ao `usuario_aculta`?
5. **Estilos `legado_*`.** Ausentes do catálogo. Remover do plano ou criar?

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

Ordem obrigatória: inventário e classificação; revisão de referências fora do banco; quarentena com janela de 30 dias,
em área privada; exclusão só após confirmação e nova verificação de uso. Arquivos do tema ficam fora da limpeza de Media.
Contador zero não basta para excluir.

## 11. Fontes

- Image Optimize: https://www.drupal.org/project/imageapi_optimize
- Entity Usage: https://www.drupal.org/project/entity_usage
- Media Duplicates: https://www.drupal.org/project/media_duplicates
- File Hash: https://www.drupal.org/project/filehash (avaliado; não adotado)
- Hostinger: páginas de suporte sobre PHP e binários consultadas em 2026-10-17 (ver seção 7)
- Modelos de imagem dos veículos: ver `MODELOS-DE-IMAGEM.md`, seção 2

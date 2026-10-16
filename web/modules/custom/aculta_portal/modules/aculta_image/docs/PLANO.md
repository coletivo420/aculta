# Plano — ACULTA Image (sistema único de imagens do site)

Status: **planejamento** (2026-10-17). Nenhum código, configuração ou dependência foi instalado.
O submódulo `aculta_image` ainda não existe; este diretório contém só a documentação.

Política do responsável: **todo o site usa o `aculta_image`** para upload, derivados, entrega
responsiva, auditoria de uso e limpeza de imagens e arquivos de imagem. Nenhum módulo do Portal
cria estilo de imagem, processa imagem ou apaga arquivo por conta própria.

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
| "Media Duplicates 2.0.4" (proposta anterior) | — | — | Projeto com este nome não encontrado | **Descartar** |
| Equivalente encontrado: `media_dedupe` | `media_dedupe` | 1.0.0-alpha1 (11/08/2026) | ^10 \|\| ^11 | **Não adotar**: sem release estável e fora da política de segurança do Drupal |
| Relatório de duplicatas | `filehash_noo` / `filehash_report` | a verificar | exigem File Hash 3.x na documentação consultada | **Opcional**; avaliar após o File Hash 4.0.2 |
| Miniatura de PDF | `pdfpreview` (1.2) + ImageMagick | já escolhido pelo PV4 | — | **Reaproveitar** o que o `aculta_pdf_viewer` já tem |

Correções em relação à proposta anterior: Image Optimize 4.2.0 confirmado; Entity Usage deve
ser 5.0.1 neste core; File Hash deve ser 4.0.2 (não 3.2); "Media Duplicates" não existe com
esse nome. Não adotaremos nenhum módulo sem verificar a versão instalada e o código.

**Dependências Composer (AGENTS, regra 14).** Cada uma só entra com a fase correspondente:
- `imageapi_optimize`: compressão dos derivados gerados pelos estilos. O core não comprime
  JPEG/WebP além de `jpeg_quality`. Justificativa de otimização de banda, com teste de qualidade.
- `filehash`: SHA-256 persistido por arquivo. O core não guarda hash; sem ele a detecção
  de duplicata exata exige leitura de todos os arquivos a cada consulta.
- `entity_usage`: referências que o `file_usage` do core não vê (links e imagens em texto
  formatado, Entity Embed). Sem isso a auditoria de candidatas fica incompleta.

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

Nenhum estilo novo sem medir o uso. Revisão planejada (M3):
- `aculta_card_1x` (256) e `aculta_card_2x` (512) como finalidade `card`;
- `aculta_avatar` (256) como `avatar`; `thumbnail`/`medium` (100 e 220) reavaliados como
  miniaturas de Media Library;
- `media_gallery_*` como finalidades `galeria_*`;
- `large`, `wide` e `produto_galeria` mapeados sem alteração de dimensões.
Mudança de dimensão de estilo existente altera componentes do ACULTA420 e exige teste visual
em claro e escuro.

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

## 4. Fases (uma PR por fase, conforme AGENTS)

| Fase | Entrega | Aceite |
| --- | --- | --- |
| M0 | Inventário read-only (`aculta_image.inventory`) e página administrativa; sem dependências novas | Números batem com as consultas acima; nada é alterado |
| M1 | Catálogo de finalidades e `aculta_image.render` com estilos existentes; migração da Gallery | Galeria exibe miniatura na grade e lightbox no clique; teste em claro, escuro e 390 px |
| M2 | Política de upload centralizada | Limites iguais em todos os campos de imagem; mensagens de erro do Form API |
| M3 | Consolidação dos estilos (3.4) e migração de Magazine, Usuário e Portal | Nenhuma referência a estilo fora do catálogo (gate) |
| M4 | Miniatura de PDF movida para o `aculta_image` (3.3) | Sem `pdfpreview` ativo: leitor e download continuam; com ativo: miniatura só para PDF público |
| M5 | `filehash` 4.0.2 e relatório de duplicatas (sem prevenção) | Relatório de duplicatas exatas com mesmo arquivo binário; nenhuma Media mesclada |
| M6 | `entity_usage` 5.0.1 e auditoria de candidatas | Lista com motivos; comparação com a auditoria manual |
| M7 | Quarentena e exclusão supervisionada | Teste com arquivos de teste; exclusão só após confirmação; restauração testada |
| M8 | Compressão de derivados com `imageapi_optimize` 4.2.0 em lotes pequenos (CPU e memória limitadas) | Originais intactos; derivados comparados em qualidade |
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

## 6. Fontes

- Image Optimize (`imageapi_optimize`): https://www.drupal.org/project/imageapi_optimize
- Entity Usage (`entity_usage`): https://www.drupal.org/project/entity_usage
- File Hash (`filehash`): https://www.drupal.org/project/filehash
- Media Dedupe (`media_dedupe`): https://www.drupal.org/project/media_dedupe
- Pesquisa de Media Duplicates e Image Optimize WebP: resultados de busca em 2026-10-17
  (sem página oficial de "Media Duplicates 2.0.4" encontrada).
- Código e configuração do próprio projeto citados na seção 1.

# Publicações da Revista: campos, imagens e política editorial

Documento vivo. Vale para as publicações da Revista (tipos `article` = Notícia, incluindo o conteúdo do PodPlant, e
`blog`) e para os futuros tipos de vídeo e galeria, quando forem criados (ver `aculta_magazine/docs/README.md`).
Implementação: submódulo `aculta_magazine` (função) e tema ACULTA420 (aparência).

## 1. Campos da publicação

| Campo (rótulo no site) | Campo técnico | Uso | Onde aparece |
| --- | --- | --- | --- |
| Título | `title` | Título da postagem (até ~100 caracteres) | Título da página, SEO |
| Chamada | `field_subtitle` | Texto de abertura (1 frase, até ~160 caracteres) | Destaque abaixo da imagem |
| Resumo | `field_summary` | 1 a 3 frases que resumem a publicação | Caixa própria; descrição de SEO e Open Graph |
| Imagem de destaque | `field_media_image` | Ver seção 2 | Topo da postagem e listagens |
| Corpo | `body` | Texto da publicação (parágrafos; títulos internos H2/H3) | Corpo da postagem |
| Vídeo | mídia `remote_video` (YouTube/Vimeo) no corpo | Vídeo embutido em 16:9, centralizado | Corpo |
| Categoria editorial (sessão) | `field_editorial_category` | Sessão: Editorial, Notícias, Registros, Zines, PodPlant420 | Rótulo e URL `/[sessão]/[título]` |
| Data de publicação | `field_published_at` | Data e hora da publicação (guardadas em UTC) | Faixa de metainformação |
| Autor | `uid` (conta) | Autor principal | Faixa de metainformação e caixa de autores |
| Coautores | `co_authors` (Node Co-Authors) | Outras pessoas que escreveram a publicação | Caixa de autores |
| Autoria editorial | `field_editorial_author` | Autoria institucional ou de convidado (taxonomia) | Metadados (não é a caixa de autores) |
| Projeto relacionado | `field_project` | Projeto ao qual a publicação se liga (ex.: PodPlant420) | Relações; listagens de projeto |
| Créditos | `field_credits` | Crédito de fotos, ilustrações e textos de terceiros | Final da postagem |
| Referências | `field_references` | Links de fontes citadas | Final da postagem |
| SEO e compartilhamento | `field_meta_tags` | Metadescrição, Open Graph (título, descrição, imagem) | Cabeçalho HTML |
| Discussão | `field_discussao` | Tópico de Fórum, criado automaticamente pela publicação (`aculta_magazine`) | Bloco de discussão com trecho e link para o Fórum |

Regras de preenchimento:
- **Chamada e resumo** são textos próprios. Não copie a primeira frase do corpo na chamada; se for igual, a
  chamada deve ser reescrita. Sem resumo, a descrição de SEO fica vazia.
- **Metadescrição** segue o resumo, com até ~160 caracteres.
- **Autor** é a conta de quem escreveu; **coautores** entram em `co_authors`. Coautores têm os direitos de conteúdo
  do autor (aceito pelo responsável); só quem tem "administer nodes" altera a lista de coautores.
- **Data** em UTC: 19h32 de Brasília = 22h32 UTC.

## 2. Imagens: tamanhos recomendados

A imagem de destaque é exibida em moldura **3:2** na coluna de leitura (até 60 rem, ~960 px), com a imagem
inteira centralizada (sem recorte). Fotos verticais aparecem com fundo neutro nas laterais.

| Uso | Enviar (mínimo) | Proporção | Observações |
| --- | --- | --- | --- |
| Destaque (horizontal) | 1800 × 1200 px | 3:2 | Preferido; cobre telas de alta densidade |
| Destaque (vertical, ex. cartaz) | 1200 × 1500 px | 4:5 | Aparece inteira, centralizada, sobre fundo neutro |
| Destaque no celular | mesma imagem | 4:3 na moldura | Não é preciso enviar outra versão |
| Miniatura de listagem | mesma imagem | 1:1 ou 3:2 | Gerada pelo estilo de imagem da listagem |
| Imagem no corpo | 1200 px de largura | livre | Largura de leitura até ~68 caracteres |
| Foto de perfil (avatar) | 256 × 256 px ou maior | 1:1 | Recortada em quadrado; nunca achatada |

Formatos aceitos: JPG, PNG e WebP. Peso até 2 MB para foto de destaque (quanto menor, mais rápido).
Nome do arquivo sem espaços nem acentos (ex.: `podplant-01-capa.jpg`).

**Pendência técnica (registrada):** o estilo de imagem usado na exibição de destaque (`large`) tem limite de
480 px. Para a moldura de 960 px, é preciso um estilo próprio de destaque (ex.: largura 1200 px, 3:2, sem
recorte para verticais). Enquanto não for criado, a foto aparece em até 480 px.

## 3. Política editorial para imagens

1. **Direitos:** toda imagem precisa de direito de uso (foto própria, licença livre ou autorização escrita). Fotos
   de agências ou de terceiros têm crédito obrigatório (campo `field_credits`).
2. **Crédito:** registrar autor da foto e fonte. Ilustrações e logotipos de projetos citam a origem.
3. **Pessoas:** imagem com pessoa identificável exige autorização. Não publicar imagem de menores de idade sem
   autorização dos responsáveis e sem anonimização.
4. **Dados pessoais:** não publicar documentos, telas com dados pessoais, placas de veículos ou endereços.
5. **Conteúdo sensível:** imagens que mostram uso ou cultivo de cannabis seguem o tom antiproibicionista da
   associação: sem apologia a uso recreativo para menores, sem estímulo a consumo e sem imagens que associem a
   planta a crime ou violência.
6. **Texto alternativo (alt):** toda imagem de destaque tem descrição curta do que aparece. Imagem decorativa
   pode ter alt vazio, decidido pela edição.
7. **Qualidade:** foto nítida, sem marca d'água de terceiros, sem distorção. Não esticar imagens para caber.
8. **Identidade:** logotipos de projetos (ex.: PodPlant420) são usados como estão, sem alteração de cor ou forma.
9. **Substituição:** trocar a imagem de destaque de uma publicação já no ar exige registro na revisão.

## 4. Vídeo

- Vídeo embutido por link de YouTube ou Vimeo (mídia `remote_video`), no corpo da postagem.
- Exibição centralizada, largura da coluna de leitura, proporção 16:9, responsiva.
- Vídeo próprio ou de terceiros precisa dos mesmos direitos da imagem (seção 3).

## 5. Estado e pendências

- [x] Chamada, resumo, SEO (metadescrição e Open Graph), data e hora, projeto, autor e coautores, créditos e
  referências, imagem de destaque, vídeo embutido, sessão e URL.
- [x] Caixa de autores com privacidade (apenas perfis públicos, bio e redes conforme escolha da pessoa).
- [x] Compartilhamento (WhatsApp, Telegram, Facebook, Reddit, e-mail).
- [ ] Estilo de imagem próprio para destaque (seção 2).
- [ ] Galeria e vídeo como tipos da Revista (passo a passo em `aculta_magazine/docs/README.md`).
- [ ] Inspeção visual humana das postagens.

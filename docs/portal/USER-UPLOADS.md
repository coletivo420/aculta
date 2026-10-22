# Uploads de arquivos por usuários

`aculta_portal` mantém a política comum dos arquivos enviados por pessoas
autenticadas. Os formulários consumidores usam `aculta_portal.user_upload_policy`
e `aculta_portal.user_upload_dialog_builder` para validação, shell do modal e
controles Managed File. Drupal File/Media continuam sendo o armazenamento de
verdade; cada domínio associa o arquivo ao conteúdo e continua responsável por
autoria, publicação, acesso, retenção e apresentação.

## Fronteiras e garantias

- **Drupal Core File API** recebe o upload, mantém a entidade File e executa os
  constraints `FileExtension`, `FileSizeLimit` e, para imagens, `FileIsImage`.
  O limite efetivo também fica sujeito a PHP e ao servidor web.
- **`aculta_portal`** fornece allowlist normalizada, limite global e por
  consumidor, validadores para Managed File e construtor do shell/modal. A
  política central não publica entidades nem decide quem pode baixar arquivos.
- **`aculta_image`** inspeciona conteúdo raster e é o dono do processamento,
  apresentação e exclusão de imagens. Não criar limites, estilos, processamento
  ou exclusão paralelos no consumidor.
- **`aculta_gallery`** continua sendo o dono das galerias e das regras de
  finalidade. A finalidade `forum` aceita somente Media `forum_attachment` com
  imagem válida; galerias comuns seguem aceitando somente Media `image`.
- **Consumidor** escolhe destino (privado quando o domínio exige sigilo), bundle,
  autoria, vínculo ao conteúdo hospedeiro, publicação, acesso, retenção e
  apresentação. Arquivo público não pode ser usado para conteúdo que depende de
  autorização do hospedeiro.

Uploads começam não publicados. O consumidor só os torna permanentes quando os
associa a entidades que representam o upload e deve registrar a utilização pelas
APIs File/Entity apropriadas. Não se apaga File diretamente: conferir referências
e delegar imagens a `aculta_image`. O fluxo de formulário não substitui a
autorização de acesso do arquivo.

## Política central

- Configuração administrativa: **ACULTA → Envio de arquivos por usuários**, com
  abas para política global e para as políticas dos consumidores já migrados.
- Padrão: 10 MiB por arquivo e extensões `png jpg jpeg gif webp pdf docx xls xlsx`.
- A pessoa responsável pode ajustar extensões e aumentar o limite. Limites da
  SAPI PHP e do servidor web continuam aplicáveis.
- O consumidor pode herdar a configuração global ou definir sua própria
  allowlist/tamanho. Valores de extensão inválidos no override falham para a
  configuração global; configuração global inválida usa os padrões seguros.
  Limites inválidos seguem a mesma ordem de fallback, sempre terminando em
  10 MiB.
- Imagens usam os validadores e o inspetor de `aculta_image`; extensões de
  imagem não criam uma política paralela de processamento ou apresentação.
- Extensões executáveis e vetoriais não estão na lista aprovada. Extensões
  novas exigem revisão de MIME, validação, armazenamento e comportamento de
  download antes de serem aceitas.

## Integração por domínio

Um domínio usa Managed File API com os validadores deste serviço e salva o
arquivo no destino apropriado. Para conteúdo condicionado à autorização do
hospedeiro, o destino deve ser privado e o download deve passar pelo controle de
acesso daquele domínio. Em seguida cria ou atualiza a entidade Core que
representa o upload, registra a relação de uso necessária à entrega e mantém a
publicação/acesso vinculados ao conteúdo hospedeiro. O Portal não cria uma
entidade genérica paralela para substituir File ou Media.

O Fórum é o primeiro consumidor: a aba do Fórum pode herdar a política global ou
configurar extensões e tamanho próprios. Os modais de anexo e galeria aceitam
somente tipos compatíveis com cada controle e salvam em `private://forum/anexos`.
O Media e a Media Gallery começam não publicados; imagens passam pelo contrato
`aculta_image`, galerias pelo `aculta_gallery`, e a publicação pública do post
ou comentário e do tópico controla sua disponibilidade. No Fórum revisionável,
a limpeza considera referências de todas as revisões para não remover anexos
mantidos no histórico. Media e Galleries sem referência são limpas após uma
hora em lote limitado, revalidando embeds atuais, uso do File e referências de
entidades antes de apagar. Files ainda temporários respeitam
`system.file:temporary_maximum_age`, usando `changed` como o cron File do Core;
imagens passam por `aculta_image` e arquivos não-imagem pela File API, após
revalidação de `file_usage` e `entity_usage`. Os testes de regressão estão em
[`ForumAttachmentPublicationKernelTest`](../../web/modules/custom/aculta_portal/modules/aculta_forum/tests/src/Kernel/ForumAttachmentPublicationKernelTest.php).

O leitor PDF e a prévia DOCX/planilhas permanecem responsabilidades dos módulos
de visualização correspondentes. A interface visual do modal usa os tokens
neutros `user-upload-dialog`, `forum-attachment-dialog` e
`forum-gallery-dialog`, mapeados e estilizados pelo ACULTA420; o Portal não
depende do tema.

O LMS é o segundo consumidor (0.4.0-dev.252). Suas respostas do tipo File Upload
têm uma aba própria, `lms`, no mesmo formulário central, com herança da política
global ou override. Como a atividade é configurada pelo educador, a política LMS
funciona como teto: `UserUploadPolicy::restrictValidators()` remove extensões
fora da allowlist do consumidor e só reduz o limite de tamanho. O validador
`AcultaLmsSubmissionImage` do `aculta_image` continua aplicado às imagens. O LMS
mantém as regras de acesso da turma, da atividade e da participação.

A Wiki ainda não tem consumidor: `wiki_entry` não possui campo de envio por
usuário. Quando um campo desses existir, ele entra no mesmo formulário central,
sem copiar validadores ou criar limites locais sem necessidade documentada.

## Validação automatizada

`tests/src/Kernel/Upload/UserUploadPolicyKernelTest.php` cobre política global e
herança, override e fallback de configuração inválida, validadores Drupal 11,
estrutura das abas administrativas, inspeção real de bytes por `aculta_image`,
estado não publicado de Media/Gallery e a regra contextual de `aculta_gallery`.
`ForumAttachmentPublicationKernelTest` cobre publicação sincronizada, retenção,
limpeza de órfãos e segurança das referências no consumidor. Essas duas suítes
formam a regressão do contrato central e da implementação original do Fórum.

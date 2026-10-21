# Uploads de arquivos por usuários

`aculta_portal` mantém a política comum dos arquivos enviados por pessoas
autenticadas. Os formulários consumidores usam `aculta_portal.user_upload_policy`
e `aculta_portal.user_upload_dialog_builder` para validação, shell do modal e
controles Managed File; cada domínio associa o arquivo ao seu
conteúdo e continua responsável por autoria, publicação, acesso, retenção e
apresentação.

## Política central

- Configuração administrativa: **ACULTA → Envio de arquivos por usuários**, com
  abas para política global e para as políticas dos consumidores já migrados.
- Padrão: 10 MiB por arquivo e extensões `png jpg jpeg gif webp pdf docx xls xlsx`.
- A pessoa responsável pode ajustar extensões e aumentar o limite. Limites da
  SAPI PHP e do servidor web continuam aplicáveis.
- Imagens usam os validadores e o inspetor de `aculta_image`; extensões de
  imagem não criam uma política paralela de processamento ou apresentação.
- Extensões executáveis e vetoriais não estão na lista aprovada. Extensões
  novas exigem revisão de MIME, validação, armazenamento e comportamento de
  download antes de serem aceitas.

## Integração por domínio

Um domínio usa Managed File API com os validadores deste serviço e salva o
arquivo no destino privado apropriado. Em seguida cria ou atualiza a entidade
Core que representa o upload, registra a relação de uso necessária à entrega
privada e mantém a publicação/acesso vinculados ao conteúdo hospedeiro. O
Portal não cria uma entidade genérica paralela para substituir File ou Media.

O Fórum é o primeiro consumidor: a aba do Fórum pode herdar a política global ou
configurar extensões e tamanho próprios. Seu modal insere um Media não publicado
no comentário do CKEditor; a publicação pública do comentário e do tópico
controla sua disponibilidade. A galeria usa Media Gallery e `aculta_gallery`.
O leitor PDF e a prévia DOCX/planilhas permanecem responsabilidades dos módulos
de visualização correspondentes.

Wiki e LMS migram em seguida; ao integrar cada um, sua aba aparece no mesmo
formulário central e pode herdar a política global ou configurar valores
próprios. Cada um preserva as regras de acesso de seu
conteúdo (incluindo turma, atividade e participação) e reaproveitando a mesma
política central. Não se deve copiar validadores ou criar limites locais sem uma
necessidade de domínio documentada.

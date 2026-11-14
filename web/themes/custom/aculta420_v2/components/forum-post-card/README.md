# forum-post-card

Card de postagem do tópico com faixa colorida no topo (stripe), cabeçalho com identificação do autor, papel, contagem de posts, timestamp, corpo e reações.

## Props
- `stripe` (enum: `brand`, `highlight`, `member`, padrão `brand`): cor da faixa horizontal no topo do card.
- `author_name` (string, obrigatório): nome do autor da mensagem.
- `author_role` (string, opcional): papel do autor entregue pelo Portal via contrato. O tema não consulta entidade de usuário.
- `author_posts` (inteiro/string, opcional): contagem de posts do autor. Se ausente, exibe `—`.
- `timestamp` (string, opcional): data ou horário da postagem.
- `reactions` (array de `{label, count}`, opcional): reações da postagem.
- `reply_url` (string, opcional): link para responder à mensagem.

## Slots
- `avatar`: render array de avatar (foto liberada ou ícone neutro).
- `body`: render array do conteúdo do post.
- `actions`: ações secundárias de moderação ou curadoria.
- `reply`: botão ou elemento alternativo de resposta.

## Conformidade
- O papel e a variante de faixa vêm do Portal.
- Sem entidade ou papel de usuário lidos no tema.
- Responsivo de 320 px a 1440 px.
- Story: `forum-post-card.default.story.yml`.

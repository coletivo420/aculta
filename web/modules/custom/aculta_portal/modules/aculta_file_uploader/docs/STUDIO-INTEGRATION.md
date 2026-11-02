# Integração com o Aculta Studio — planejamento

Status: **planejamento apenas**. Nenhuma linha de implementação. Fase própria, a
ser aberta quando o responsável ordenar.

## Decisões já tomadas

- O uploader mantém a interface de envio (`aculta_upload_dropzone`). O Studio
  consome essa interface; não reimplementa upload.
- O painel administrativo (`/painel-administrativo/aculta/uploads`) fica
  no uploader. É experiência só para administradores.
- A experiência de envio para o usuário final é a integração Aculta Studio.
- Pelas regras do projeto, não há CORS para transportar estado entre
  propósitos: AJAX e fetch do Portal são same-origin.

## Caminhos possíveis

1. **Bundle servido pelo Portal, mesma origem.** O Studio entrega um JavaScript
   que monta a drop zone dentro da página do Portal. Não há requisição entre
   origens. Recomendado.
2. **Iframe same-origin.** A drop zone roda numa página do Portal embutida no
   Studio. Mais isolamento, mas exige cuidado com cookies de sessão e
   `frame-ancestors`.
3. **API de upload para o Studio.** O Studio recebe os bytes e grava no Portal.
   Exige autenticação entre serviços e não reaproveita a política do uploader.
   Não recomendado.

## Pontos que dependem de decisão

- Qual dos caminhos 1 a 3 o responsável escolhe.
- Como o Studio identifica a integração (`consumers.<nome>`) de cada envio.
- Se o Studio precisa de scope OAuth. Pela regra do projeto, nenhum scope é
  pedido sem feature ativa e aprovada.
- Onde fica o contrato de dados de cada envio (File, Media ou atributo de
  entidade). Hoje o uploader devolve um `File`.

## Fora deste planejamento

- Implementação, testes no Runtime e publicação. Dependem da fase aberta.
- Alterações no tema ACULTA420. Dependem de autorização explícita.

# ACULTA Content Index — contrato de relações de vídeo (proposta)

Data: 2026-10-26. Decisão do responsável: definir o contrato agora; a
implementação continua nos módulos owners. **Nenhum código de owner foi
alterado nesta entrega.**

## Escopo

Relação direcionada e explícita entre uma entidade editorial e um Media
`remote_video` do Portal. O Index não indexa o corpo, a URL oEmbed, a miniatura
nem o provedor do vídeo; registra apenas a aresta tipada entre as identidades.

Dois tipos de relação, conforme o modelo do owner:

- **`embedded_video`** para embeds editoriais explícitos em Revista, Wiki e
  Fórum (quando os formatos permitirem).
- **`course_video`** para o campo explícito de recurso pedagógico do curso LMS,
  seguindo o padrão de `course_reading` e `course_gallery`.

## Fonte da relação

O provider do módulo owner descobre a referência por uma API estável de
referências/embeds (Entity Usage ou referência Media explícita). O Index não
faz parsing de HTML, não lê o corpo e não cria uma segunda fonte de verdade.
Cada embed explícito e resolvido gera uma aresta:

```text
<origem>:<id>:<langcode> --embedded_video--> media:<id> (remote_video)
```

O `target_langcode` é `und`; a consulta resolve a tradução padrão do Media.

## Owners e pré-requisitos

| Origem | Estado atual verificado | Pré-requisito para implementar | Observação |
| --- | --- | --- | --- |
| Revista (`node:revista`) | `filter.format.revista_editorial` permite `remote_video`; provider já usa purpose `magazine` | Adicionar `embedded_video` ao provider do Magazine e incrementar a versão de projeção | Formato já permite; relação ainda pendente no provider |
| Wiki (`node:wiki_entry`) | `filter.format.wiki` permite somente `document` | Decisão editorial para permitir `remote_video` no formato e definição de quais bundles podem embutir | Não alterar o formato sem decisão do owner |
| Fórum (`node:aculta_forum_topic`, `aculta_forum_post`, `comment:aculta_discussion_post`) | `filter.format.forum` permite somente `forum_attachment` | Decidir se tópicos, posts e comentários podem embutir vídeo; atualizar o formato e o provider | Comentários legados e ForumPost coexistem |
| LMS (`group:lms_course`) | Campo explícito `field_lms_videos` (Media `remote_video`) implementado em `aculta_lms_resources`; provider emite `course_video` | **Implementado**: `config/install`, update `8001`, provider v4/3 e Kernel `course_video` | `takeAccess()`/Group continua autoridade; URL/corpo/miniatura não copiados |
| Portal (`media:remote_video`) | Provider mínimo v2; sem URL/corpo/miniatura | Nenhum; permanece owner do bundle e do provider | Não força purpose de consumidor |

## Acesso

- A origem é revalidada no purpose canônico do provider (`magazine`, `wiki`,
  `forum`, `courses`) e pela policy do owner.
- O alvo `media:remote_video` é revalidado por Entity Access no contexto da
  consulta. O provider do Portal não declara purpose fixo.
- Se um consumidor exigir um purpose canônico para o vídeo, o contrato precisa
  ser reaberto: não inferir purpose por hostname nem herdar o purpose da
  origem.
- Vídeo privado/despublicado ou origem sem acesso não aparecem. O Index não
  "protege" o embed: apenas revalida o acesso do Media e da origem.
- Nenhuma relação é criada a partir de mídia meramente referenciada em
  configuração, campo de apresentação ou conteúdo não publicado.

## Projeção e privacidade

- Somente a aresta `embedded_video` e os metadados já permitidos do Media
  (`id`, bundle, label segura, estado, datas e `owner_uid` interno quando
  aplicável).
- Proibido projetar URL oEmbed, HTML, corpo, transcrição, miniatura, duração,
  arquivo, legenda ou qualquer dado pessoal.
- O provider do Portal continua sem purpose fixo e sem URL/corpo; a projeção
  atual não muda.

## Versionamento e testes exigidos dos owners

Cada owner que implementar a relação deve:

1. Incrementar `getProjectionVersion()`.
2. Testar a projeção da relação apenas para bundles explicitamente permitidos.
3. Testar a consulta outgoing com origem e alvo autorizados.
4. Testar alvo despublicado/privado omitido e origem sem acesso encerrando a
   consulta.
5. Testar remoção do embed/relação e atualização incremental.
6. Testar provider desabilitado: a aresta não é exposta e a limpeza só ocorre
   após reativação e validação da fonte.
7. Confirmar que URL, corpo e miniatura não entram no catálogo.

O Kernel do Index só deve ganhar cobertura dessa relação depois que o adapter
do owner existir; não criar um adapter genérico no Index para contornar o
ownership.

## Consumidores

GanjaLab, GanjaLab420, WE Sativa, Revista, Wiki, Fórum, LMS e Portal devem:

- consultar por identidade de origem e tipo `embedded_video`;
- revalidar o alvo pela própria Entity Access;
- não tratar a relação como autorização nem como catálogo global de vídeos;
- não pedir totais/facetas globais sem um contrato próprio e medição de acesso.

## Decisão e pendências

- Contrato definido em 2026-10-26 por decisão do responsável.
- LMS implementado no owner `aculta_lms_resources`: `field_lms_videos`,
  update `8001`, relação `course_video` e teste Kernel com membro/educador e
  terceiro negado. Config Sync e update no Runtime são do agente de integração.
- Revista tem o formato editorial pronto, mas o provider ainda não emite a
  relação; Wiki e Fórum precisam decidir se os formatos permitem
  `remote_video`.
- O Index não antecipa adapters de embeds; só revalida o Media alvo.
- Nenhuma operação de Runtime foi executada para esta definição.

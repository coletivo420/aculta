# ACULTA Commerce — Contrato proposto com Content Index

Status: **design de adaptação, aguardando implementação efetiva do índice na branch**.

## Entidades elegíveis

Indexar apenas produtos públicos de Commerce, suas variações públicas necessárias à descoberta, categorias e relações com conteúdos editoriais publicáveis quando adaptadores efetivos existirem. Identificador estável: `commerce_product:<id>` com propósito de domínio, idioma e loja; armazenar IDs e metadados derivados, nunca duplicar entidade canônica. Variações não podem ficar descobertas isoladamente sem política pública explícita.

## Fonte, ciclo e sincronização

- Fonte: `commerce_product`, suas variações, acessos e domínios; o Commerce mantém a verdade.
- Eventos candidatos: salvar/publicar/despublicar/excluir produto, salvar/despublicar variação, alterações relevantes de categoria e vínculo editorial; emissão via adaptador quando API do índice existir.
- Atualização: fila com coalescência, idempotência, retries e reconciliação; rebuild paginado e checkpoints; remoção/tombstone quando retirar publicação ou acesso.
- Consulta: somente resultados pós-verificação de `entity access`, domínios e cache metadata, jamais confiar em ACL materializada como autorização definitiva.
- Preço de busca: não armazenar preço transacional, promoções personalizadas ou regras de cliente no índice. Metadados comerciais exibíveis devem ser recalculados pelo proprietário antes da resposta.
- Excluídos: pedidos, pagamentos, carrinhos, dados pessoais, endereços, estoque exato, webhooks, faturas, sessões, notas ou dados privados do LMS/doações.
- Relações editoriais: IDs de referência entre produto e artigo, verbete, aula pública ou campanha, com validação de publicação e sem transformar o índice em sistema de autoria.
- Observabilidade: tamanho do backlog, idade de sincronização, falhas, última reconciliação, itens órfãos, divergências e perda de autorização. Métricas agregadas podem ser publicadas em `aculta_statistics` sem dados pessoais.

## Gate de integração

Não adicionar dependência, contrato de serviço, subscriber, schema ou query antes de analisar a API real do ACULTA Content Index no `aculta_portal`. O índice não está na árvore commitada inspecionada; ele pode existir em trabalho paralelo ainda não commitado. Registrar a identificação precisa do serviço e eventos, acrescentar testes Kernel de alterações, despublicação, idioma, domínio, ACL e rebuild, e só então ativar.

# Matriz de testes — ACULTA Wiki

Cobertura planejada e executada do submódulo. Os cenários abaixo listados como
`planejado` continuam pendentes; a seção da Fase 5 registra o que já roda.

## Fase 5 — busca e aviso de revisão

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Índice `aculta_conteudo` exportado | Unit | `wiki_entry` entre os bundles, `index_directly` e `track_changes_in_references` verdadeiros | aprovado |
| Verbete sai de rascunho para revisão | Unit | `entity_update` dispara o aviso | aprovado |
| Verbete continua em revisão | Unit | nenhum aviso | aprovado |
| Verbete publicado entra em revisão | Unit | aviso disparado (compara com `getOriginal()`) | aprovado |
| Verbete publicado é editado sem mudar de estado | Unit | nenhum aviso | aprovado |
| Atualização de outro bundle | Unit | nenhum aviso | aprovado |
| Entidade que não é nó | Unit | nenhum aviso | aprovado |
| Tipo sem campo `moderation_state` | Unit | nenhum aviso | aprovado |
| Aviso desligado na configuração | Unit | nenhum aviso | aprovado |
| Fila do mailer ausente | Unit | nada enfileirado, sem erro | aprovado |
| Aviso desligado | Unit | nenhuma mensagem enfileirada | aprovado |
| Lista com endereço inválido | Unit | endereço descartado, envio só aos válidos | aprovado |
| Dois destinatários cadastrados | Unit | uma mensagem por endereço, mesma correlação lógica | aprovado |
| Sem URL pública disponível | Unit | contexto com `link` vazio | aprovado |
| `isEnabled` | Unit | reflete a configuração sem alterar o padrão desligado | aprovado |
| Tipo de e-mail `aculta_wiki_review` | Unit | ID válido e variáveis `verbete`, `estado`, `link` | aprovado |
| Assunto e corpo do aviso | Unit | usam os três marcadores e nenhum campo de autoria | aprovado |
| Contexto do aviso | Unit | sem `autor`, `autora`, `user` ou `uid` | aprovado |

Suíte do submódulo após esta entrega: `52 testes, 240 asserções, OK`. Os cenários
de navegador (busca do site, verbete publicado no resultado, tela
`/painel-administrativo/aculta/wiki`) continuam na seção **Navegador** e só rodam
depois da importação de configuração no servidor de testes.

## Fase 5 — listagem e imagem de destaque

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Envio de imagem válida com descrição | Unit | sem erro de validação | aprovado |
| Descrição vazia ou acima de 512 caracteres | Unit | erro em `wiki_lead_image][alt` | aprovado |
| Mais de um arquivo por envio | Unit | erro em `wiki_lead_image][file` | aprovado |
| Arquivo fora das extensões de imagem da política | Unit | erro em `wiki_lead_image][file` | aprovado |
| Aba intocada | Unit | nenhum erro e nenhum campo alterado | aprovado |
| Envio aceito | Unit | mídia `image` publicada com `alt`, campo aponta para ela | aprovado |
| Reenvio após erro de validação | Unit | reusa a mídia do mesmo `form_state` | aprovado |
| Caixa de remoção marcada | Unit | `field_wiki_image` esvaziado | aprovado |
| Formulário do verbete | Unit | aba no grupo `advanced`, `#tree`, validadores `wiki`, ganchos de validação e envio | aprovado |
| Política sem extensão de imagem | Unit | aba não aparece e o widget central continua visível | aprovado |
| Consumidor `wiki` exportado | Unit | `consumers.wiki` no `config/sync` e no schema | aprovado |

## Fase 2 — componentes movidos

| Componente | Tipo | Cenários | Estado |
| --- | --- | --- | --- |
| `WikiController::home` | Unit / Kernel | Página inicial responde 200 no purpose `wiki`; sem blocos quando o purpose é outro | planejado |
| `WikiController::search` | Unit | Busca vazia e busca com menos de `MIN_CHARS`; limite de `MAX_CHARS` | planejado |
| `WikiController::preview` | Unit | Prévia abaixo do mínimo não consulta o banco; resposta com contagem e links | planejado |
| `WikiSearchFormBuilder` | Unit | `#action` e `data-preview-url` apontam para os nomes de rota estáveis | planejado |
| Rotas | Kernel | `/wiki`, `/wiki/busca`, `/wiki/busca/previa` com os mesmos nomes e caminhos | planejado |

## Fase 4 — métricas de publicação

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Primeira publicação de um verbete | Unit | Um evento `wiki.entries.published` | planejado |
| Revisão publicada de verbete já publicado | Unit | Um evento `wiki.entries.updated`, nenhum `published` | planejado |
| Node importado ou sincronizado | Unit | Nenhum evento | planejado |
| Agregado diário com publicação e revisão | Kernel | Contadores diários separados por métrica | planejado |

## Navegador

Revisão visual com Firefox ESR visível em `DISPLAY=:0` via geckodriver, nos temas
ACULTA420 e Olivero, em claro e escuro, a 390, 768 e 1024 CSS px, depois que o
módulo estiver habilitado no servidor de testes.

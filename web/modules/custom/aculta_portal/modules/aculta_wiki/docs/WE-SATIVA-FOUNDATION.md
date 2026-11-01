# WE SATIVA — Fundação de contribuição (WES 0.2-A)

**Data:** 2026-10-22 · **Estado:** contrato de fundação aprovado, não ativado em Runtime.
**Produto:** editor comunitário Wiki420, independente de GanjaLab e GanjaLab420.

## Decisões aprovadas

1. **Contribuição primeiro:** partir do verbete público com ação contextual `Contribuir`; em WE SATIVA apresentar tarefas guiadas (corrigir informação, acrescentar fonte, melhorar trecho, sugerir imagem) e editor completo somente quando autorizado.
2. **Mobile-first sempre:** Wiki pública e WE SATIVA começam em 320px/uma coluna; desktop amplia sem mudar a ordem semântica. Claro/escuro mantêm geometria.
3. **Sem novo editor/armazenamento:** usar Entity/Node Access, Form API, CKEditor 5, revisão e Content Moderation nativos. Uma sugestão de pessoa sem acesso de edição **não deve** ser salva fraudulentamente como revisão; esse workflow requer projeto próprio antes de habilitar.
4. **Autosave Form: backlog futuro explicitamente adiado.** Não adicionar Composer, dependência, biblioteca, configuração, serviços, permissão ou promessa de salvamento automático nesta fase. Salvar deve ter retorno persistido e inequívoco do servidor.
5. **Fronteiras:** `aculta_wiki` coordena funcionalidades editoriais; `aculta_portal` domínio; `aculta420` SDC/UI Patterns/UI Styles e CSS; `aculta_user` identidade; mídia/documentos nos kernels existentes; `aculta_studio` não é dependência.
6. **Shell obrigatório:** Institution Bar + Domain Header global intactos; WE SATIVA acrescenta somente cabeçalho contextual dentro do conteúdo.

## Contrato funcional proposto (não registrado em routing.yml)

| Ação conceitual | Dados permitidos | Revalidação obrigatória | Degradação |
| --- | --- | --- | --- |
| Contribuir do verbete | Node id/rota canônica + intention allowlist | node view + capacidade de contribuição realmente implementada | ausência do botão se indisponível |
| Corrigir trecho | alvo estável + texto proposto + justificativa/fonte | bundle/purpose, visão e permissão de editar ou de propor revisão autorizada | oferecer editor completo apenas se permitido |
| Adicionar referência | título da fonte + URI validada + seção opcional | campo `field_wiki_references`, URI scheme, entity access | formulário Core quando possível |
| Melhorar conteúdo | trecho/seção + proposta | acesso Node/revisão e política do formato | formulário Core |
| Sugerir mídia | Media autorizada + legenda/alt/fonte | aculta_image/Media/File, referências e access | não mostrar fluxo sem provider |
| Editar completo | Node Form API + revisão | Entity Access, CSRF e transição permitida | Form API sem JS |
| Meu trabalho/revisão | apenas entidades permitidas | queries com access + cache contexts/tags | links de página normais |

**Intents possíveis:** `correction`, `reference`, `improve`, `image`, `full`. Estes nomes são enum de planejamento, não query params públicos garantidos nem permission strings. Nunca incluir texto não publicado na URL ou registrar dado privado em logs.

## Gate obrigatório antes do primeiro endpoint

- Verificar se a configuração exportada de `wiki_entry` usa workflow Core com estados/transições esperados; verificar form display, text format CKEditor 5 e roles reais.
- Escolher canonical routes, acesso de tarefa e retorno somente após auditar o route subscriber e o purpose Wiki; não introduzir rota no GanjaLab.
- Decidir modelo de sugestão para perfis sem `update` do Node; separar proposta de edição direta e limitar abuso/spam.
- Garantir que ações no frontend apenas sejam renderizadas após `AccessResult` cacheável do backend; JavaScript nunca decide permissão.
- Manter `context-header`, `context-tabs`, `card` e `empty-state` como bases SDC, sempre com consumidor real; não portar Twig sem SDC.
- Testar estado anônimo, participante, revisor, conta com acesso revogado, conteúdo publicado/pendente, domains distintos, transições, revisão concorrente, formulário e teclado.

## Sequência de fundação executável

| Marco | Entrega | Status |
| --- | --- | --- |
| WES-0.2-A0 | decisões de produto e fronteira documentadas | DOCUMENTADO |
| WES-0.2-A1 | mapa de formulário, rota e permissões no código | A EXECUTAR |
| WES-0.2-A2 | decisão de modelo de propostas sem direito de edição | A EXECUTAR |
| WES-0.2-B | primeiros fluxos mobile e stories SDC; sem salvar falso | A EXECUTAR |
| WES-0.2-C | primeiro endpoint/ação com acesso, testes e fallback | BLOQUEADO por A1/A2 |
| WES-0.2-R | homologação no homelab | PENDENTE |

Nenhuma implementação ativa, nova permissão ou alteração de fluxo de publicação foi feita neste marco.

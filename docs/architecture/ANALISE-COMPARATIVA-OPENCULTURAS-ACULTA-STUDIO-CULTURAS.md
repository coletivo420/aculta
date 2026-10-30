# Análise comparativa: OpenCulturas e ACULTA Studio Culturas

Data da análise: 2026-10-30  
Fonte OpenCulturas: espelho oficial `openculturas/openculturas-distribution`, branch `3.2.x`, commit `16fc5072780a41f9cd7f993620169834970f5df1`.  
Fonte ACULTA: PR #299, branch `refactor/opencultures-codigo-visual`, HEAD auditado `63116d1de6e0907c2ab0a2aa9e1fc5de79c054e2`.

Esta é uma auditoria estática do código e da configuração. O OpenCulturas foi clonado e inspecionado localmente; a distribuição não foi instalada nem comparada ao vivo. O código da PR #299 foi consultado no HEAD indicado. A existência de testes no repositório não significa que tenham sido executados neste levantamento.

## Conclusão

O OpenCulturas não tem uma Suite isolada equivalente ao ACULTA Studio Culturas. Sua experiência de autoria é composta por permissões Drupal, formulários de conteúdo com tours, links de ação e Views de conteúdo próprio. O ACULTA já possui no código uma superfície de organizador mais concentrada: `/culturas`, dashboard próprio, edição de Eventos e gestão contextual de Datas e Locais, mantendo as entidades e a autorização em `aculta_events`.

O padrão que não deve ser copiado é a separação de Evento e Data em dois tipos de conteúdo com formulários independentes. No ACULTA, Data continua um registro interno associado ao Evento. O valor de referência do OpenCulturas está nas orientações de formulário, no relacionamento com perfil/organização, no inventário de conteúdo do usuário e na modelagem explícita dos metadados de cada sessão.

## Comparação por área

| Área | OpenCulturas 3.2.x | ACULTA na PR #299 | Decisão para o Studio Culturas |
| --- | --- | --- | --- |
| Produto de organizadores | Não há módulo ou Suite de organizadores separada. Há ferramentas Drupal distribuídas entre conteúdo, permissões, Views e navegação. | `/culturas` declara dashboard do organizador no purpose `eventos`; o módulo proprietário é `aculta_events`. | Manter a experiência dedicada em `/culturas`. Tratar OpenCulturas como referência de padrões, não como produto a replicar literalmente. |
| Modelo Evento/Data | `event` e `date` são node bundles distintos. Data referencia Evento e tem ciclo de conteúdo próprio. O tour instrui a criar Evento e depois adicionar Datas. | `aculta_event` é a unidade editorial. `aculta_event_date` é entidade subordinada; criação inicial aceita várias Datas, edição as lista no contexto do Evento e usa dialogs Drupal/AJAX. | Preservar a decisão ACULTA: nunca criar um menu ou fluxo editorial global de Data. Auditar o acesso direto às rotas contextuais para que não se convertam em editor autônomo. |
| Formulário e ajuda | Formulários de Evento, Data e Local têm tours e descrições. A referência de Evento inexistente aponta para cadastro em outra aba. | `EventForm` reúne Evento e Datas iniciais; `EventDateListBuilder` abre operações em modal. | Adotar tour curto, instruções contextuais, indicação de obrigatoriedade e recuperação de erro, sem transportar a jornada em duas etapas do upstream. |
| Local | Local é outro node bundle. A Data seleciona Local por entity reference/autocomplete. A configuração tem `auto_create: false`; o cadastro continua em formulário separado, apesar do texto de ajuda dizer para criá-lo se não encontrado. | `EventLocation` é reutilizável. O modal de Eventos busca, seleciona ou cadastra sem sair do formulário. O cadastro começa no CEP, reutiliza o adaptador central e controla a precisão pública. | Manter o popup AJAX do ACULTA. Melhorar relevância da busca, contexto geográfico, prevenção de duplicatas e prévia de mapa. Não copiar autocomplete simples como substituto do modal. |
| Busca de Local | Autocomplete textual por referência. Não foi encontrada busca por logradouro no fluxo de cadastro. | Modal faz submissão explícita de busca pelo título, limitada a 15 resultados recentes; exibe cidade/UF e situação. Não foi encontrada busca por endereço no modal. | Incremento possível: busca tolerante a nomes parecidos e filtros de cidade/UF. Busca por endereço e mapa devem seguir a decisão ACULTA que restringe essa função a Events/Maps, com provedor e privacidade aprovados. |
| Identidade do organizador | `field_organizer` da Data referencia conteúdo node, usado para representar um perfil/organização. O usuário pode selecionar perfil principal. | `field_organizador` do Evento é preenchido inicialmente com UID; dashboard filtra Eventos por esse valor e revalida `update` por entidade. | Manter ownership atual por usuário até haver desenho de organizações e coorganizadores. Planejar vínculo organizacional com acesso delegável, autoria e trilha de auditoria, sem importar o bundle `profile` upstream. |
| Dashboard e conteúdo próprio | `views.view.my_content` agrega conteúdo próprio de vários bundles e usa UID como argumento. `views.view.dashboard` é principalmente um buscador de perfis, não painel de eventos. | `EventOrganizerDashboardController` lista Eventos autorizados, apresenta status e ação “Gerenciar”; consulta no máximo 250 itens e não expõe filtros/paginação no código auditado. | Melhorar `/culturas` com paginação, busca, filtros por estado e próxima Data, preservando acesso por entidade. Criar estados vazios e mensagens de pendência claras. |
| Papéis e moderação | `authenticated` pode criar e editar conteúdo próprio de Evento, Data e Local; `oc_organizer` acrescenta permissões de recorrência, publicação e conteúdo não publicado. | `EventSettingsForm` permite escolher papéis para criar Eventos e Locais; `content_editor` é padrão. A permissão efetiva continua revalidada pelo access handler/policy. | Preservar concessão explícita por capacidade. Documentar separadamente criar Evento, criar Local, editar, publicar e administrar; a escolha de papel não deve implicar capacidades não selecionadas. |
| Datas, recorrência e calendário | Usa Smart Date/Smart Date Recurring e dá às Datas metadados de acesso, recorrência e agenda. | `aculta_calendar` já possui regra de recorrência, ocorrências, cancelamentos, reagendamento e ICS. | Incorporar os conceitos editoriais necessários e casos de exceção, sem trocar o motor ACULTA por Smart Date nem duplicar a agenda. |
| Ingressos | Data possui booleano de gratuidade, faixa de preço textual, texto auxiliar e link para serviço externo. Evento também tem campos de “bookable” e informações em Paragraph. As configurações consultadas não formam checkout nem emissão transacional. | ACULTA documenta campos informativos atuais e plano próprio de RSVP, venda, Commerce e Mercado Pago. | Reaproveitar vocabulário e apresentação de preço/link. Não tratar upstream como sistema de venda. RSVP, capacidade, lote, pagamento, emissão e check-in seguem o plano ACULTA e seus gates jurídicos/financeiros. |
| Local público, agenda e mapa | Local é página própria e se relaciona às Datas; Views e mapa consomem esses vínculos. | Events possui páginas/lista de Locais, relação derivada no Index e consumidores Maps; detalhes de agenda e navegação devem ser homologados no fluxo atual. | Completar o ciclo Evento → Data → Local em diretório, página, mapa e Index, respeitando `access()`, precisão e cache. Não duplicar catálogo em Studio ou Maps. |
| Limites de código | A distribuição usa configuração Drupal e módulos próprios; o comportamento de autoria vem de várias camadas, não de uma única app. | ACULTA tem owners e contratos próprios, CEP centralizado, Domain Purpose, Maps, Index e Commerce. | Adaptar padrões, não transplantar arquitetura ou código em bloco. Qualquer derivação de código precisa seguir o registro de origem/licença já existente no ACULTA. |

## O que a análise verificou no código da PR

- Rotas da experiência: `/culturas`, `/culturas/eventos/novo`, edição de Evento, operações de Data, operações de Local e modal de Local, todas declaradas no owner `aculta_events` e purpose `eventos`.
- Dashboard: escopo por `field_organizador` do usuário e checagem de `access('update')`; oferece a lista e criação, sem paginação/filtros no controller auditado. O commit `95314a5` corrigiu a composição de cache do `createAccess()` para solicitar o `AccessResult` cacheável e incluiu teste Kernel de construção do dashboard; esse teste não foi executado nesta análise.
- Cadastro: `EventForm` acrescenta Data(s) ao criar Evento; edição do Evento delega operações ao `EventDateListBuilder`.
- Data: as rotas add/edit/delete/order exigem Evento no contexto e têm access check de vínculo; a interface prevista usa modal.
- Local: modal em duas etapas; busca por nome com `LIKE`, ordenação por ID decrescente e limite 15; cadastro por CEP e associação ao contexto via AJAX.
- Administração: gestão/configuração administrativa em `/painel-administrativo/eventos`; escolha de papéis de criação via `EventSettingsForm`.
- Limites visíveis: nenhuma busca por endereço foi encontrada no modal; dashboard sem paginação/filtros; associação organizacional/coorganização não aparece no campo atual; o save do Evento e das Datas iniciais é sequencial e merece revisão de atomicidade/recuperação de erro.
- Testes correspondentes existem em `EventFormDatesKernelTest`, `EventDateManagementKernelTest`, `EventLocationModalAndAdminKernelTest`, `EventLocationManagementKernelTest` e testes de autorização/Index. Não foram executados nesta revisão.

## Plano de incorporação

### P0 - reconciliar contrato, rotas e evidências

1. Manter `/culturas` no purpose `eventos`; administração técnica no MAIN.
2. Validar por teste que todas as rotas de escrita rejeitam purpose errado e revalidam owner/entidade no servidor.
3. Confirmar que Data só é gerenciada dentro do Evento; rota contextual deve manter o vínculo, e o fluxo normal deve abrir popup Drupal/AJAX.
4. Atualizar documentação e roadmaps para distinguir: código presente, testes existentes, testes executados no HEAD e homologação Runtime.

### P1 - tornar `/culturas` uma central de trabalho

1. Adicionar paginação e filtros eficientes ao dashboard, evitando carregar um conjunto fixo de até 250 Eventos.
2. Mostrar próxima Data, Local resumido, status editorial e ações autorizadas. Nunca mostrar endereço completo onde a precisão não permite.
3. Incluir estados vazios, instruções contextuais e tours acessíveis por teclado, mantendo navegação funcional sem JavaScript onde o Drupal oferece fallback.
4. Revisar fluxo de falha parcial na criação inicial: salvar Evento e Datas com consistência, ou apresentar recuperação explícita sem deixar conteúdo inesperado.

### P1 - aproximar busca e gestão de Local do caso de uso ACULTA

1. Buscar nome e similaridade, ordenar resultados por relevância; acrescentar cidade/UF como contexto e filtro.
2. Evitar expor títulos ou totais de Locais que a pessoa não pode acessar. Revalidar leitura e vínculo ao selecionar/submeter.
3. Manter CEP como primeira entrada do formulário completo, país fixo `BR`, editabilidade dos campos e precisão de endereço.
4. Planejar mapa/preenchimento por endereço somente em Events/Maps, usando o provedor aprovado, atribuição, cache, proteção de dados e seleções confirmadas pela pessoa.

### P2 - ampliar ownership organizacional e ingresso

1. Definir entidade/referência organizacional, coorganizadores e revogação de vínculo antes de permitir que uma conta administre Eventos em nome de terceiros.
2. Fazer o dashboard refletir o escopo de acesso definido pelo owner, sem filtros de usuário que ampliem acesso.
3. Usar o plano ADR-036 para separar gratuidade/RSVP e venda paga; OpenCulturas serve apenas como referência de campos descritivos e links externos.
4. Não abrir transações pagas até fechar os gates fiscais, jurídicos, LGPD, taxas, estornos, sandbox e validação de webhook definidos no plano.

## Critérios para declarar a experiência homologada

- Eventos de contas distintas não se misturam; permissões negadas e estados privados não vazam em contagem, busca, lista ou cache.
- Criar/editar Evento e Datas mantém valores durante rebuild AJAX e não cria Data sem Evento.
- Local existente e Local novo podem ser selecionados no popup; CEP, Brasil, acesso, estado e precisão são validados no servidor.
- Modal funciona por teclado, Escape, retorno de foco e mensagens acessíveis; erros não descartam o formulário pai.
- Página e diretório de Locais integram agenda, Maps e `aculta_index` conforme acesso e precisão, sem URL ou título canônico incorreto.
- Dashboard suporta volume maior que a página, filtros consistentes, estados de publicação e ações autorizadas.
- Runtime, domínio `eventos`, navegação, cache e responsividade são testados no ambiente registrado; documentação relata somente resultados realmente obtidos.

## Fontes primárias

- [Repositório OpenCulturas no commit auditado](https://github.com/openculturas/openculturas-distribution/tree/16fc5072780a41f9cd7f993620169834970f5df1)
- [Tipo de conteúdo Evento](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/node.type.event.yml)
- [Tour de Evento](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/tour.tour.oc-event.yml)
- [Referência de Evento na Data](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/field.field.node.date.field_event_description.yml)
- [Referência de Local na Data](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/field.field.node.date.field_location.yml)
- [View “My content”](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/views.view.my_content.yml)
- [Permissões de usuário autenticado](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/user.role.authenticated.yml)
- [Permissões `oc_organizer`](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/user.role.oc_organizer.yml)
- [Campo externo de ingressos](https://github.com/openculturas/openculturas-distribution/blob/16fc5072780a41f9cd7f993620169834970f5df1/profile/config/install/field.field.node.date.field_ticketing.yml)
- [ACULTA Studio Culturas - ADR-035](../decisions/ADR-035-aculta-studio-culturas.md)
- [Ingressos - ADR-036 e plano de Eventos](../decisions/ADR-036-ingressos-gratuitos-e-pagos.md)
- [Locais e aculta_index](PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md)

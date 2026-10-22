# WE SATIVA — Arquitetura interna completa (planejamento)

**Versão:** WES-UX 0.3.0-dev.1 · **Data:** 2026-10-22 · **Estado:** especificação de produto, IA e contratos; NENHUMA rota ou funcionalidade implementada.
**Dependências documentais:** [Visão do editor](WE-SATIVA-VISUAL-EDITOR.md) · [Contribuição primeiro](WE-SATIVA-CONTRIBUTION-FIRST-RESEARCH.md) · [Fundação do kernel](../../web/modules/custom/aculta_portal/modules/aculta_wiki/docs/WE-SATIVA-FOUNDATION.md).
**Regra principal:** planejar toda a estrutura antes de codificar; cada estrutura só entra em Runtime mediante gate de autorização, fluxo e consumidor real.

## 1. Modelo de produto e princípios

WE SATIVA (Wiki Editor Sativa) é o espaço de trabalho editorial comunitário da Wiki420, separado de GanjaLab e GanjaLab420. **Editar deve ser mais simples que administrar**: começar por ação contextual no verbete, oferecer microtarefas, usar linguagem comum, esconder opções técnicas até serem necessárias e manter passagem sem atrito do leitor ao colaborador.

- **Mobile-first genuíno:** projetar 320px primeiro, uma coluna, teclado virtual, controles ≥44px sempre que praticável, fonte dos campos ≥16px, zoom, labels persistentes e nenhum submenu que dependa de hover; tablet e desktop apenas expandem.
- **Shell imutável:** Institution Bar + Domain Header multidomínio preservados em TODAS as páginas. WE SATIVA tem subcabeçalho contextual e não clona a identidade global.
- **Core SDC / UI Suite obrigatório:** reusar apenas componentes SDC conformes e ferramentas UI Patterns/UI Styles; um Twig solto não é componente reaproveitável. Componentes do tema e regras do kernel sempre separados.
- **Permissão antes de interface:** Node/Form API, revisions, workflows e AccessResult cacheável no backend; não confundir acesso a sugestão com update de Node.
- **Sem falsos recursos:** autosave, edição colaborativa simultânea, diff inline, IA editorial e notificações novas não existem por decreto deste plano.

## 2. Mapa interno: áreas, telas e usuários

| Área WE SATIVA | Telas internas | Para quem | Ação primária |
| --- | --- | --- | --- |
| **Começar** | boas-vindas mínima, tarefas disponíveis, continuar última contribuição autorizada | colaborador com permissão | escolher uma contribuição |
| **Meu trabalho** | meus rascunhos, propostas enviadas, em revisão, ajustes solicitados, publicados, histórico pessoal | autor autorizado | continuar ou acompanhar |
| **Contribuir** | entrada via verbete, seletor de intenção, microtarefa, revisão da proposta, confirmação | colaborador com capacidade real | propor melhoria |
| **Novo verbete** | orientação breve, checagem de duplicados, título, síntese, conteúdo, fontes, prévia | quem pode criar | salvar rascunho |
| **Editor completo** | formulário Node, corpo CKEditor 5, mídia, categorias, fontes, relacionados, opções avançadas | quem pode editar | salvar/transição autorizada |
| **Revisão editorial** | minha fila, fila de equipe, item a revisar, comparação, decisão, pedir ajustes | apenas revisores autorizados | decidir conforme workflow |
| **Biblioteca de fontes** | referências associadas ao verbete, inserção, validação, prévia | autor/revisor com permissão | anexar referência |
| **Mídias do verbete** | mídia permitida, descrição alternativa, legenda, licença/origem se suportadas | autor/revisor com permissão | selecionar mídia |
| **Ajuda e aprendizagem** | guia curto, exemplos de boas contribuições, neutralidade, fontes, acessibilidade | todos com acesso ao editor | aprender sem interromper |
| **Preferências de trabalho** | apenas preferências efetivamente fornecidas por conta/tema | pessoa autenticada | abrir configurações existentes |

**Organização:** até quatro destinos primários em navegação mobile: **Início, Meu trabalho, Contribuir, Ajuda**. `Novo verbete` é CTA contextual; **Revisão** surge somente para quem tiver acesso. Fonte/mídia/opções ficam **dentro da tarefa**, não como menus globais. Nenhuma navegação secundária paralela ao shell multidomínio.

## 3. Estrutura de cada tela

### 3.1 Início / Começar
Mensagem curta, CTA `Melhorar um verbete` e `Criar verbete` apenas quando disponíveis, bloco `Continuar seu trabalho`, tarefas sugeridas **somente quando um provider real as fornecer**, atalhos Ajuda/Ver Wiki. Ausência de tarefas usa `empty-state`, não dados fictícios. Evitar métricas, gamificação ou cartões repetidos só para preencher espaço.

### 3.2 Meu trabalho
Abas de filtro **por URL**: `Em andamento`, `Enviadas`, `Ajustes solicitados`, `Publicadas`, `Todas`, condicionadas a estados verdadeiros. Cada cartão apresenta título acessível, tipo (verbete/proposta), status semântico, atualização real, ação única relevante e retorno ao verbete. Nunca misturar fila editorial geral com trabalho próprio. Paginador, filtros e contagem gerados por Views/queries com access, não Javascript de apresentação.

### 3.3 Selecionar contribuição (abertura no verbete)
Contexto e retorno ao verbete no topo; escolhas **Corrigir informação**, **Adicionar referência**, **Melhorar trecho**, **Sugerir imagem** e **Editar verbete inteiro**, cada qual com descrição simples. Ocultar/desabilitar explicitamente ações que não tenham backend, com orientação útil quando houver percurso autorizado alternativo. Selecionar uma opção por vez. Não gerar proposta no clique: só após confirmação real.

### 3.4 Microtarefa — quatro padrões
- **Correção:** trecho atual somente se acessível → novo texto → fonte/justificativa conforme política → comparação legível → enviar; proteger contra conteúdo alterado por outra revisão.
- **Referência:** trecho ou seção → URL/título da fonte → motivo/citação breve (respeitar copyright) → prévia e envio; sem exigir usuário saber o campo Drupal `field_wiki_references`.
- **Melhorar trecho:** seção existente → instrução específica → proposta visual → revisão humana → envio; não substituir o artigo inteiro sem confirmação.
- **Imagem:** escolher Media acessível ou enviar via política aprovada → alt/legenda/crédito/origem → confirmar; depender de `aculta_image`, Media/File e políticas de licença.

**Contrato pendente:** usuário sem permissão `update` exige um mecanismo seguro e separado de propostas com moderação, antes de expor qualquer microtarefa que persista dados. Nada será gravado como revisão do Node sem permissão. Um único tipo de proposta versionada pode ser avaliado futuramente; não criar schema de entidade agora.

### 3.5 Criar novo verbete
Fluxo proposto: procurar possível duplicata → título e descrição do assunto → corpo inicial curto → referências → prévia → salvar rascunho (se autorizado). Verificação de duplicatas usa busca existente, sem API externa inventada. Campos técnicos como Domain, autoria e status não devem ser liberados sem política de acesso. A primeira contribuição útil não deve exigir taxonomia completa.

### 3.6 Editor completo
Layout mobile: título, status real, síntese, corpo CKEditor 5, ferramentas de mídia, referências, categorias, relacionados, opções avançadas; ações de salvar/preview e transições separadas. Desktop: conteúdo dominante e metadados secundários. **Form API continua dono dos controles e da validação**, inclusive CSRF, erros e fallback sem JS. Preview nunca se confunde com publicação. Campos com erro em disclosure são abertos e focados.

### 3.7 Revisão editorial
Duas listas separadas: minhas propostas aguardando / fila de revisão de equipe. Página de revisão mostra versão publicada, alteração proposta, justificativa, fontes, histórico e decisões permitidas. Comparação simples e acessível antes de diferenças detalhadas. Aprovar/rejeitar/pedir ajustes só se transições reais existirem. Nenhum botão pode executar ação irreversível sem confirmação e autorização no POST.

### 3.8 Centro de orientações
Ajuda contextual ligada à tarefa, exemplos e linguagem simples, links para políticas de fontes, neutralidade, direitos autorais, descrição alternativa e boas práticas. Tutorial é dispensável, não bloqueante, e não depende de tours com popups; permitir retomar do mesmo ponto.

## 4. Navegação, rotas e estado de página

**Estado pretendido, não URLs finais:** verbete público → intenção selecionada → tela WE SATIVA correspondente → prévia/revisão → confirmação do servidor → Meu trabalho ou retorno ao verbete. O kernel deve resolver rotas canônicas e purpose Wiki, validar `destination` para evitar open redirect, e preservar vínculo estável com Node sem expor texto privado em query string. GET de URL navegável, fallback sem JS, histórico/back/forward íntegros. AJAX é melhoria progressiva, não requisito de acesso à edição.

### Contrato de abertura contextual
Entradas conceituais: `wiki_entry` autorizado, `intent` allowlist (`correction|reference|improve|image|full`), seção/alvo estável quando houver e retorno seguro. Nunca confiar em `intent`, `nid` ou retorno passados pelo navegador sem revalidar view/update/propose no servidor. `Contribuir` é ação dentro do SDC de contexto; o Domain Header não ganha menu paralelo.

## 5. Máquina de estados editorial (conceitual)

| Estado apresentado | Origem | Ações possíveis |
| --- | --- | --- |
| Não iniciado | UI, nenhum dado salvo | iniciar formulário |
| Não salvo | alterações locais do formulário | salvar explicitamente; avisar antes de abandonar quando tecnicamente possível |
| Rascunho persistido | revisão/workflow confirmado pelo servidor | continuar, pré-visualizar, enviar se permitido |
| Proposta enviada | backend próprio de proposta, somente quando existir | acompanhar |
| Em revisão | estado oficial de moderação | acompanhar/revisar se autorizado |
| Ajustes solicitados | transição real disponível | corrigir e reenviar |
| Publicado | revisão publicada | ler a versão pública |
| Falha de envio | Form API/HTTP | preservar dados quando possível, expor erro e oferecer tentativa segura |

Não forçar estados de `Content Moderation` que não existam no workflow da Wiki; este quadro é linguagem UX a mapear posteriormente. **Autosave Form permanece backlog futuro** — sem instalação, automação de salvamento ou promessas de recuperação.

## 6. Inventário de campos/entidades existentes e propostas futuras

**Confirmado no form display exportado de `wiki_entry`:** `title`, `field_wiki_summary`, `body` (widget `text_textarea_with_summary`), `field_wiki_categories`, `field_wiki_image`, `field_wiki_references`, `field_wiki_related`, `created`, `uid`, `langcode`, `path`, `status`, campos de configuração auxiliares. `field_domain_source` aparece em região hidden; isso não substitui controle de domínio no backend. `node.type.wiki_entry.yml` já declara novas revisões e preview; não prova workflow específico ativo ou CKEditor 5 para todos os formatos.

**Propostas condicionais, não criadas:** entidade de sugestão para contribuintes sem update, índice de tarefas sugeridas, comparação contextual acessível, preferência editorial por pessoa, alertas de revisão. Preferir Views/Node/Revisions/Core antes de inventar armazenamento. Nunca duplicar arquivos, media, users ou Messenger.

## 7. Mapa de componentes e ownership

| Elemento UI | Base SDC existente / ferramenta | Dono funcional |
| --- | --- | --- |
| Shell global | Institution Bar + Domain Header existentes | Portal/tema |
| Contexto/H1/retorno | `context-header` | tema + presenter autorizado |
| Abas por URL | `context-tabs` | tema + roteamento Wiki |
| Cartões de trabalho/tarefa | `card`, `editorial-card` | tema + listas do kernel |
| Estados vazios | `empty-state` | tema + estado oficial do provider |
| Formulários e toolbar | Drupal Form API + CKEditor 5, wrapper visual somente via SDC | Core/Wiki |
| Figuras/legendas | `figure`, Media via `aculta_image` | tema/Image |
| Pessoas | `author-card`, conforme privacidade | `aculta_user` |
| Feedback e avisos | componentes de feedback conformes quando cabível | Core/Messenger |
| Módulos específicos WE SATIVA | criar SDC somente após auditoria e consumidor real | tema |

**Não reaproveitar diretamente componentes sem base SDC/UI Suite**. UI Patterns Library e UI Styles semânticos devem cobrir stories de fluxo, empty/error e mobile, sem alterar regras no tema.

## 8. Qualidade, privacidade e segurança

ACL por participante e estado; tenant/purpose Wiki, isolamento de Group quando aplicável, acesso anônimo vs contribuinte vs revisor, revision access, text formats e XSS, URL/CSRF, image alt e licenças, mídia privada, cache contexts/tags, itens de fila, antiabuso, moderação e concorrência. Testar sem JS; telefone 320/360/390px, tablet 768px, desktop 1024/1280/1440px, zoom 200/400%, teclado, leitor de tela, modo claro/escuro com geometria idêntica, redução de movimentos e DOM com um H1. Nenhum gate Runtime será declarado PASS por inspeção documental.

## 9. Roadmap de implementação em pequenas fatias

| Marco | Escopo | Saída |
| --- | --- | --- |
| **WES-0.3-A0** | arquitetura interna, IA, fluxos e matriz de áreas deste documento | DOCUMENTADO |
| **WES-0.3-A1** | auditar roles, workflows, form displays, text formats e routes; mapear capacidade por tela | matriz verificada, sem permission strings inventadas |
| **WES-0.3-A2** | decidir armazenamento/moderação de propostas para pessoas sem update | ADR de segurança e ciclo de vida; nenhum endpoint prematuro |
| **WES-0.3-B1** | wireframes responsivos: início, meu trabalho, seletor, microtarefa, editor, revisão | histórias UX e a11y |
| **WES-0.3-B2** | contratos Core SDC/UI Patterns/UI Styles e histórias reutilizáveis | zero duplicações de componentes |
| **WES-0.3-C1** | rotas e navegação WE SATIVA independentes do GanjaLab | teste de access, domain e fallback |
| **WES-0.3-C2** | entrada a partir do verbete e primeiro fluxo de contribuição autorizado | persistência e retorno confirmados |
| **WES-0.3-C3** | editor completo e revisão com formulários existentes | transições autênticas e testes |
| **WES-0.3-D** | revisão UX móvel e desktop, estados, telemetria agregada opcional | métricas sem PII |
| **WES-0.3-R** | bateria de Runtime e homelab antes de RC | evidência por role, estado, viewport e tema |

**Regra de execução:** um commit por fatia, documentar no mesmo commit; não tocar GanjaLab ou GanjaLab420. Esta arquitetura é **planejamento**, não instrução para habilitar tudo automaticamente.


## Primeiro componente planejado — C01

[WE SATIVA C01 — Porta de contribuição contextual](WE-SATIVA-COMPONENT-01-CONTRIBUTION-ENTRY.md) especifica a ação única no verbete, a seleção de intenções, reuso SDC/UI Suite, estados de acesso, mobile-first e microfases C01-A–R. Não cria novo componente SDC ou rota antes da auditoria de consumidores e permissões.


## Política editorial aprovada — contribuição comunitária moderada

Todas as contas com **e-mail confirmado** poderão contribuir pelo WE SATIVA, observando acesso efetivo e isolamento de domínio. Configuração exportada já permite criar/editar verbetes próprios e enviar revisão; publicações são responsabilidade de `content_editor`. Propostas para verbetes de terceiros **não podem** usar `edit own` como se fosse `edit any`: seguir a [C01-B](WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md) e decidir backend próprio de propostas revisáveis antes de oferecer microtarefas. Autosave Form permanece futuro.


## Retificação de experiência editorial (V-0.4-D)

A seção de revisão editorial e sua fila são **áreas do GanjaLab**, por meio de StudioTab e services do `aculta_wiki`, não da navegação WE SATIVA. O WE SATIVA tem somente `Meu trabalho` (contribuições próprias, respostas editoriais e status), além de criação/edição autorizada. [Janela de moderação planejada no GanjaLab](WE-SATIVA-V-0.4-D-GANJALAB-MODERATION.md). Esta decisão prevalece sobre tabelas anteriores que associavam Revisão à UI do colaborador.

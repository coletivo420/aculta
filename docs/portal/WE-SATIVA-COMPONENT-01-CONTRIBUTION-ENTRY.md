# WE SATIVA — Componente 01: Porta de contribuição contextual

**Versão:** WES-UX 0.3-B1 / C01 · **Data:** 2026-10-22 · **Status:** aprovado para planejamento; implementação e Runtime pendentes.
**Ponto de entrada:** verbete Wiki420 → `Contribuir` → seletor de tarefa WE SATIVA.
**Referências:** [Arquitetura interna](WE-SATIVA-INTERNAL-ARCHITECTURE.md) · [Contribuição primeiro](WE-SATIVA-CONTRIBUTION-FIRST-RESEARCH.md).

## 1. Por que começar por aqui

A primeira unidade a especificar é **o fluxo de decisão do colaborador**, não um editor de texto novo. A pessoa precisa perceber que pode melhorar uma informação em poucos passos, sem conhecer Drupal ou MediaWiki. O componente é uma **composição de SDCs existentes**, não um novo plugin inicialmente.

Objetivos mensuráveis para testes futuros: conseguir identificar a ação `Contribuir`, escolher uma intenção em linguagem comum, conhecer o impacto do envio e voltar ao verbete. Não usar métricas artificiais como sucesso real antes de pesquisa com usuários.

## 2. Duas superfícies e seus responsáveis

**A. Convite contextual no verbete**
- Ação `Contribuir` perto do título e das ações do verbete, **dentro** da região de conteúdo, nunca na Institution Bar ou no Domain Header.
- O template full atual utiliza `aculta420:context-header` com slot `actions` e `aculta420:wiki-entry` com slot `actions` proveniente de `content.links`. Decidir **um único local canônico** após mapear os links existentes: preferir `context-header.actions` se provider oferecer um render array autorizado; não duplicar CTA nos dois slots.
- Texto secundário opcional `Ajude a melhorar este verbete` somente quando existir destino real.
- Para quem não pode contribuir, não criar CTA enganoso; eventual orientação de acesso é uma experiência separada e autorizada.
- Sem JavaScript, o CTA é link real, não botão de modal sem fallback.

**B. Seletor de intenção WE SATIVA**
- Cabeçalho contextual: `Contribuir com [título autorizado]`, retorno seguro ao verbete e explicação curta.
- Lista de até cinco intenções, **mostrando apenas ações com provider e acesso confirmados**.
- Escolha de uma intenção abre formulário/tela específica via rota canônica do kernel; no início pode haver somente `Editar verbete completo`, se o Node Access permitir.
- Não manter intenção em URL livre nem inferir permissão pela seleção visual: servidor valida intent allowlist, Node, Domain/purpose e policy a cada request.

## 3. Conteúdo das escolhas

| ID conceitual | Rótulo humano | Descrição | Dependência antes de habilitar |
| --- | --- | --- | --- |
| `correction` | Corrigir informação | Ajustar um trecho incorreto | provider de proposta/edição de trecho e controle de revisão |
| `reference` | Adicionar uma fonte | Indicar uma referência verificável | campo Link + workflow real para proposta |
| `improve` | Melhorar um trecho | Tornar uma seção mais clara | editor de trecho, concorrência e autorização |
| `image` | Sugerir imagem | Propor imagem, legenda e origem | Media/Image + políticas e autorização |
| `full` | Editar verbete completo | Alterar o conteúdo com editor visual | acesso `update` ou outro caminho de criação/revisão autorizado |

IDs são contrato de planejamento, **não endpoints**. Ausência de provider não é motivo para criar link fictício ou formulário que falha no POST.

## 4. Wireframes — mobile-first

### Verbete (320–390px)
```text
[Institution Bar — existente]
[Domain Header Wiki420 + busca — existente]
Wiki / Categoria / Verbete
TÍTULO DO VERBETE (um H1)
Resumo...
[Contribuir ✎]    (somente quando permitido)
Conteúdo do artigo...
```

### Seletor WE SATIVA (320–390px)
```text
[shell global permanente]
← Voltar ao verbete
WE SATIVA
Contribuir com “Verbete”
Escolha o que deseja melhorar:
┌─────────────────────────────┐
│ Corrigir informação       → │
│ Ajustar um trecho            │
└─────────────────────────────┘
┌─────────────────────────────┐
│ Adicionar uma fonte       → │
│ Inserir referência           │
└─────────────────────────────┘
... apenas ações disponíveis
[Como funciona a revisão?]
```

### Tablet/desktop
O mesmo fluxo preserva a ordem e o texto; largura de leitura limitada, escolhas em **uma coluna** ou grade de duas colunas se rótulos e descrições couberem sem desorganização. Nenhuma sidebar administrativa. O shell permanece idêntico ao da Wiki pública.

## 5. Matriz de estados

| Condição | Apresentação | Regra |
| --- | --- | --- |
| Visitante sem capacidade de edição/proposta | leitura normal | não fabricar botão de contribuição; orientação/login somente com fluxo validado |
| Usuário com acesso à edição completa | convite + intenção `full` | renderizar link de destino autorizado |
| Usuário com um provedor de microtarefa habilitado | mostrar só essa intenção | não publicar opções indisponíveis |
| Nenhuma intenção disponível | omitir convite ou mostrar explicação autorizada em tela própria | nunca estado vazio dentro de formulário que não tem ação |
| Node despublicado / domínio incorreto / acesso revogado | deny por Entity Access | cache e resposta HTTP corretos; não vazar título ou dados |
| Erro de rede após abrir link | navegação HTTP normal / erro do sistema | sem sucesso fictício |
| Rota não encontrada | 404 ou fluxo do backend | sem redirecionar automaticamente para GanjaLab |

## 6. Contrato técnico de composição

| Composição | SDC Core existente | Input provido pelo kernel |
| --- | --- | --- |
| Título e ação do verbete | `aculta420:context-header` / `actions` | link render array cacheável com access concedido |
| Cabeçalho WE SATIVA | `aculta420:context-header` | título plain text, breadcrumb/voltar autorizado |
| Card da intenção | `aculta420:card` (a confirmar no gate) | título, descrição e link como slot |
| Navegação de etapas por URL | `aculta420:context-tabs` somente se necessário | links reais, `aria-current` |
| Nenhuma ação disponível | `aculta420:empty-state` se houver consumidor real | mensagem segura/localizada |

**Nenhum SDC novo por enquanto.** UI Patterns deve conseguir representar a composição usando props/slots e UI Styles com tokens atuais. O kernel não emite classes de tema. É proibido reproduzir Twig legado não-SDC para compartilhamento.

### Backend a desenhar depois (não existe ainda)
- Resolver contexto de verbete existente a partir de uma rota canônica; derivar rótulo seguro do Node.
- Serviço/provider de catálogo de intents com `AccessResult` cacheável, estado disponível e `Url` válido por intenção.
- Retorno seguro ao verbete sem open redirect; não transportar proposta textual no query string.
- Domínio/purpose Wiki conferido no roteamento; nenhuma dependência `aculta_studio`.
- Preservar Form API, CSRF, workflow e revisões reais.

## 7. Critérios de aceite e stories futuras

**Gate de arquitetura:** um único CTA no verbete, sem tocar shell e com H1 único; Node e destination checados pelo backend; qualquer card exibido tem uma rota com GET e POST autorizados; sem JS o caminho funciona.

**Gate mobile:** 320, 360, 390, 768, 1024, 1280, 1440px; alvos confortáveis, contraste WCAG 2.2 AA, foco e ordem de tab, 200/400% zoom, textos longos, leitor de tela, modos claro/escuro com mesmo layout e reduced motion.

**Stories SDC candidatas (futuras):** `contribution-entry/full-only`, `contribution-entry/reference-and-full`, `contribution-entry/no-provider`, `contribution-entry/long-title`. Estas são histórias de integração do produto, não arquivos de componente criados ou promessas de links reais.

## 8. Microfases recomendadas

1. **C01-A** auditar actions atuais do verbete, permissões/Node Access e rotas que podem levar a edição; registrar ownership H1 e escolha do slot.
2. **C01-B** criar contrato formal de intents e resolver link canônico de retorno, com testes de acesso em diferentes perfis (sem markup novo).
3. **C01-C** compor o CTA usando SDC existente e demonstrar story sem backend fictício; integração funcional somente quando link autorizado existir.
4. **C01-D** tela seletora, cards condicionais e fallback HTTP.
5. **C01-R** testes Runtime no homelab com domínios, sessões, teclado, mobile e dark/light.

**Dependência:** definir modelo seguro de proposta para perfis sem `update` antes de habilitar microtarefas. **Autosave Form continua no backlog futuro**, sem dependência instalada.


## C01-A — decisão após auditoria de código e configuração

[Auditoria de ação e permissões](WE-SATIVA-C01-A-ACCESS-AND-CTA-AUDIT.md): o template full oferece `content.links` em `wiki-entry.actions`, enquanto `context-header.actions` está livre. O CTA **Contribuir** terá **um único lugar canônico**, proposto no `context-header.actions` após o kernel fornecer rota, access e render array cacheável. O papel `authenticated` exportado não pode editar verbetes; `content_editor` possui permissões amplas demais para o público colaborador. Portanto, não adicionar CTA fictício nem abrir acessos agora. Próxima fase C01-B: capacidades mínimas, intenções e URLs seguras.


## C01-B — regra aprovada: e-mail confirmado contribui, content editor modera

A [matriz C01-B](WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md) confirmou que o papel `email_confirmed` exportado já possui `create wiki_entry content`, `edit own wiki_entry content` e envio a `review`, mas não `review_to_published`. A UI deve priorizar o editor autorizado para conteúdo próprio e preparar sugestões em verbetes de terceiros por mecanismo específico, sem conceder `edit any`. `Contribuir` e microtarefas ficam condicionados a destino e acesso reais; um único CTA no Context Header.

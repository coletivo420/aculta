# Green Stash — Arquitetura de experiência e navegação mobile-first

Status: **projeto visual e funcional**, não interface operacional implementada. 2026-10-23. Branch `docs/aculta-forum-0.1.0-dev.1`.

## Contratos existentes examinados

- `aculta_studio/src/Experience/StudioExperienceProviderInterface.php`: experiências independentes têm `id/label/purpose/routeName/available/access/build`; Green Stash já existe como provider `green_stash` com rota `/green-stash` e purpose `account`.
- `aculta_studio/src/StudioPresenter.php`: **GanjaLab** usa área > seção > aba; sua navegação não deve ser copiada como novos `StudioTab` no Green Stash. Reaproveitar **padrão visual e modelo mental**, mas implementar navegação própria da experiência.
- `aculta420` fornece Bootstrap, tokens, SDC/UI Suite, light/dark, componentes existentes e **cabeçalho multidomínio imutável**; não criar shell nem tema novo.
- `aculta_commerce` mantém propriedade da Loja; Portal administra checkout transversal. `aculta_commerce_melhorenvio` pertence à integração logística; `aculta_statistics` fornece métricas.

## Princípios

1. **Mobile-first**, uma tarefa operacional primária por tela, sem tabela ampla no telefone. De 320px a desktop, com estados vazios, foco e teclado. Desktop enriquece sem mudar semântica.
2. **Experiência Studio independente**, mesmo shell global e linguagem visual de GanjaLab/GanjaLab420, mas com sua própria navegação comercial.
3. **Uma única hierarquia**: área principal (navegação), seção (subnavegação contextual) e abas/filtros internos. Não usar mais de dois níveis visíveis simultaneamente no telefone.
4. O **carrinho do usuário no cabeçalho multidomínio permanece intacto**; Green Stash nunca vira aba pública do checkout.
5. Form API/Render API e Views com accessCheck/Commerce access handlers, paginação/cursores e cache metadata. SDC/UI Suite **só no tema**; não renderizar dados privados em cache público.
6. Evitar contadores ou cards inventados; números somente quando houver provider autorizado e consulta real.
7. Operações de pagamento, compra de etiqueta, estorno, ajuste de estoque e exclusão exigem permissões por operação e confirmação explícita; auditoria e idempotência quando relevante.

## Navegação proposta

| Área principal | Seções / abas propostas | Fonte proprietária | Marcos |
|---|---|---|---|
| **Visão geral** | Hoje · Pendências · Desempenho | Commerce + Statistics | GS-0.2 |
| **Produtos** | Todos · Rascunhos · Variações · Categorias · Mídias | Commerce Product / Media / Content Index somente descoberta pública | GS-0.3 |
| **Pedidos** | Novos · Pagos · Preparação · Concluídos · Cancelados · Reembolsos | Commerce Order / Payment | GS-0.4 |
| **Estoque** | Saldos · Movimentações · Locais · Reposição · Alertas | Commerce Stock 3 (após homologação) | GS-0.5 |
| **Envios** | A preparar · Cotações · Etiquetas · Rastreamento · Ocorrências | Commerce Shipping + Melhor Envio | GS-0.6 |
| **Promoções** | Cupons · Descontos · Campanhas comerciais | Commerce Promotion | GS-0.7 |
| **Clientes** | Compradores · Atendimento · Histórico autorizado | Commerce Customer / ACULTA User / Messenger | GS-0.8 |
| **Relatórios** | Vendas · Conversão · Fretes · Estoque · Exportações | aculta_statistics (sem dupla contabilização) | GS-0.9 |
| **Configurações** | Loja · Origem de envios · Integrações · Permissões · Diagnóstico | Commerce Config, Portal e Melhor Envio; política restrita | GS-1.0 |

Acesso a **checkout Comum/Loja/LMS/Doações** é no painel transversal do **Portal**, não duplicar sua administração no Green Stash. Green Stash pode apenas apontar a esse painel para operadores autorizados.

## Layout responsivo

**320–599px:** barra multidomínio global preservada; cabeçalho da experiência com título, descrição curta, busca e ação contextual; bottom navigation **dentro do Green Stash** com até quatro destinos prioritários (Início, Produtos, Pedidos, Envios) e item Mais para Estoque, Promoções, Clientes, Relatórios, Configurações, quando autorizados. A bottom navigation não substitui o cabeçalho global nem o carrinho. Áreas não autorizadas omitidas, não placeholders habilitados. Listas são cartões compactos, filtros em drawer/bottom-sheet acessível ou formulário na página, ações secundárias em menu por item.
**600–1023px:** navegação da experiência como abas/rail compacto, listas adaptativas e painel de detalhe.
**1024px+:** navegação lateral persistente, cabeçalho contextual, listagens tabeladas com paginação e painel de detalhe separado; mesmos endpoints/autorização que no telefone.
Não inventar altura fixa ou barras que cubram o conteúdo/teclado. Respeitar safe-area e reduzir movimento.

## Wireframes textuais (não especificação de CSS)

### Início mobile
```
[CABEÇALHO MULTIDOMÍNIO EXISTENTE — preservado]
Green Stash                     [Busca]
Sua loja  ·  [Período ▼]
[Pedidos pendentes] [Envios pendentes]
Pendências de hoje
  [Pedido #…] [Preparar]  [Ver detalhes]
  [Etiqueta pendente] [Emitir com confirmação]
Atalhos: [+ Produto] [Movimentar estoque]
[Início] [Produtos] [Pedidos] [Envios] [Mais]
```
Nunca exibir métricas antes de o backend autorizá-las ou fornecê-las; esqueleto e estado vazio são válidos.

### Pedido mobile
```
← Pedidos             #pedido — status
[Resumo] [Itens] [Entrega] [Histórico]
Comprador (informações autorizadas)
Itens + variações + valores Commerce Price
Pagamento (estado nativo; não inferir)
Entrega / remessa / rastreio
[Ação primária autorizada] [Mais ações]
```
Ações financeiras não ficam em menu genérico de um clique e exigem confirmação.

### Produtos mobile
```
Produtos                 [+ Novo]
[Buscar produto]
[Todos] [Publicados] [Rascunhos]
[Imagem] Nome  · Variações
         Estoque (se disponível)  [Editar]
[Mais resultados]
```

## Componentes a usar/criar no tema ACULTA420

Reaproveitar `patterns/card`, feedback states, tokens, padrões de input, nav e produtos existentes quando **SDC/UI Suite compatíveis**. Criar apenas se houver consumo real:
- `studio/experience-heading` (genérico, reutilizável por outras experiências);
- `commerce/metric-card` (valor, período, rótulo acessível, estado indisponível);
- `commerce/operation-card` (pedido/produto/remessa, ações renderizadas já autorizadas);
- `commerce/status-chip`;
- `commerce/responsive-list`;
- `commerce/action-bar`;
- `commerce/shipping-label-preview` (somente após provedor autorizado).

Todos com `.component.yml`, Twig, CSS com tokens, stories e gates; sem acesso a entidades/ACL dentro do tema.

## Autorização e privacidade

- `access aculta green stash` controla entrada **apenas**; grants por store, entidade e operação são obrigatórios em query, item, rota e POST.
- Dados de compradores, endereços/CPF/CNPJ, etiquetas e documentos protegidos de cache público; não indexar no Content Index.
- Evitar leak via contadores e queries auxiliares; cache contexts/tags/max-age corretos por usuário/purpose/loja.
- Consulta logística/endereço usa `aculta_portal_cep` e formulário único; nada de formulário público duplicado.
- `aculta_statistics` para agregados; `aculta_messenger` para avisos; `aculta_image/document` quando apropriados.

## Entrega incremental

- **GS-0.2-A (antes de UI):** contrato de navegação separado do GanjaLab, matriz routes/purpose/permissions, inventário de stores habilitadas, design tokens e componentes existentes, baseline de testes.
- **GS-0.2-B:** experiência shell com navegação progressiva e telas de estado vazio real; zero métricas artificiais, filtro por autorização.
- **GS-0.2-C:** início com dados reais autorizados, consultas paginadas e ações seguras; KPIs via Statistics só se contratos existentes disponíveis.
- **GS-0.3:** produtos e variações (listagem, filtros, edição nativa Commerce).
- **GS-0.4:** pedidos e detalhes com isolamento por store; pagamentos somente leitura inicial.
- **GS-0.5:** estoque com Commerce Stock após POC.
- **GS-0.6:** remessas/etiquetas/rastreio via Melhor Envio **após** cliente REST e autorização.
- **GS-0.7–0.9:** promoções, atendimento e relatórios.
- **GS-1.0:** configurações e revisão integrada de acessibilidade/performance/segurança.

**Gates:** PHP Unit/Kernel/Functional, permission matrix, worktree e módulos habilitados no homelab; 320/375/768/1024/1440px, dark/light, zoom 200%, teclado, reduced-motion, sem JS, foco após AJAX, browser, desempenho alto volume e proteção de dados. Cada fase commitada/documentada isoladamente.

## Dívidas

GS-UX-01: validar provider/rota/container no homelab. GS-UX-02: testar matrizes de roles/store. GS-UX-03: conferir componentes exatos de navegação Studio/tema e histórias disponíveis antes de criar novos. GS-UX-04: KPIs dependem do contrato real Statistics. GS-UX-05: envios e estoque dependem das integrações REST/Stock e testes reais. Não declarar concluído o que está apenas projetado.

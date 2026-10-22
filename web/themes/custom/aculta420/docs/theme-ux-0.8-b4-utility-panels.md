# THEME-UX 0.8-B4 — Auditoria da barra multidomínio, gavetas e Minha Conta

Data: 2026-10-22. **Estado: correções de código realizadas; testes Runtime pendentes.**

## Contratos inspecionados
- Institution Bar carrega os ícones do carrinho, Messenger e Avisos em `templates/page.html.twig`. O Domain Header existente permanece separado e inalterado.
- Avisos e mensagens: SDCs `alerts-trigger` e `messenger-trigger`, cada um com offcanvas Bootstrap `offcanvas-end`, título e botão de fechar; corpo dinâmico via `data-aculta-drawer-url` e script `messenger-drawers.js`; links para telas completas no rodapé.
- Carrinho: SDC `cart-offcanvas` com botão, badge, gaveta Bootstrap e checkout. **Problema identificado no código**: cada item tinha `data-bs-dismiss=alert` com X; removia visualmente a caixa sem remover o item real do carrinho.
- Minha Conta: estrutura de navegação/área principal em `portal-account.css` e AJAX do Portal em `account-navigation.js`. **Não confundir** a navegação de Minha Conta com o offcanvas de avisos/mensagens.

## Correções commitadas
1. Carrinho: remover X enganoso e classes `alert-dismissible` dos itens. Remoção de produto deve ser feita no carrinho real via ação Commerce com backend, não por fechar aviso.
2. Gavetas da barra: largura fluida até 420px em telas amplas e 100vw no celular, rolagem interna e foco visível, rodapés sem colapsar; somente tokens semânticos do tema.
3. Minha Conta: evitar overflow por componentes largos, manter tabelas/pre com overflow horizontal delimitado e navegação lateral não sticky no mobile.

## Checklist de regressão Runtime
| Área | Evidência esperada | Estado |
| --- | --- | --- |
| Abrir/fechar avisos | botão, Esc, X, backdrop, foco volta ao acionador; carregamento AJAX | PENDENTE |
| Abrir/fechar mensagens | mesmas regras, navegação interna e atualização de contagem | PENDENTE |
| Carrinho | botão, Esc, X, total/quantidade coerentes; nenhum item some sem remoção real | PENDENTE |
| Apenas uma gaveta ativa | sem backdrop ou foco preso após alternar ícones | PENDENTE |
| Minha Conta | abas laterais/accordion, AJAX, URL, histórico, foco, formulários | PENDENTE |
| 320/390/768/1280px | sem recortes/scroll horizontal global; body scroll bloqueado apenas com modal aberto | PENDENTE |
| Claro/escuro e teclado | contraste, foco, hit targets e reduced motion | PENDENTE |
| Shell e permissões | Institution Bar + Domain Header intactos, dados e cache por usuário preservados | PENDENTE |

## Limites
Não foi possível abrir sessão no homelab: não declarar funcionamento validado, nem substituir AJAX do Portal/Commerce por lógica do tema. As mudanças CSS não comprovam comportamento do Bootstrap ou diálogos Drupal sem navegador.

## Próxima fase B4-02
Executar gates Runtime e ajustar comportamento específico do drawer se alguma falha for observada; revisar a experiência móvel do menu Minha Conta e dos painéis laterais em modo claro/escuro; registrar capturas, estados e regressões. Depois B5 integra catálogo universal de overlays e navegação, **sempre SDC + UI Suite** onde houver componentes reutilizáveis.

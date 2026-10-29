# Changelog — aculta_faq

## 0.1.0-dev.2 — autorização de edição e revisão visual

- Kernel nega usuário comum também nas rotas de edição de pergunta e bloco;
  sanitização XSS e acesso à página administrativa continuam cobertos.
- As seis respostas aprovadas foram cadastradas no Runtime de teste (Nodes
  567–572); nenhuma alteração foi feita em produção.
- Firefox ESR anônimo validou todas as respostas e reflow em 1280/390/320px,
  além de teclado: 6/6 verificações, 11/11 destinos únicos alcançáveis por Tab
  com indicador de foco.
- F1 está funcionalmente completa; o estado Dev permanece somente pelo gate
  global módulo–tema, cujas violações estão em outras frentes.

## 2026-10-28 — FAQ e blocos editoriais (ADM4/E9)

- Criado submódulo próprio com página de perguntas frequentes e tipo reutilizável de bloco editorial.
- Ambos aceitam apenas o formato sanitizado `aculta_editorial`; Kernel cobre script, atributos de evento, URL `javascript:` e acesso administrativo.
- Fluxo reescrito com atribuição registrada em `aculta_events/docs/ORIGEM-OPENCULTURAS.md`; conteúdo editorial aguarda revisão antes da publicação.

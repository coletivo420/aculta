# Roadmap — ACULTA Messenger

Documento de planejamento único do submódulo. O estado de execução pertence ao
Git, à PR e ao changelog; esta lista registra sequência e critérios de entrega.

## 0.1.0

1. **dev.1 — fundação documental:** ownership, inventário, compatibilidade,
   permissões/privacidade, Domain, eventos, ADRs. Sem instalar dependências.
2. **dev.2 — módulo integrador:** Composer dry-run/instalação, inventário do
   código upstream, metadados Drupal, integração mínima e ciclo de vida seguro.
3. **dev.3 — Private Message:** inbox, conversas/respostas, não lidas,
   autorização, fallback sem JS e proteção contra interação indesejada.
4. **dev.4 — Message/Message Notify:** eventos autorizados, e-mail mínimo,
   deduplicação e tratamento de entrega.
5. **dev.5 — Message Subscribe:** concluída estaticamente para Nodes `forum`
   publicados com assinantes na flag existente `seguir_topico`; dispatch real
   aguarda Homelab. Group fica fora enquanto `gnode` não estiver habilitado.
6. **dev.6 — Site Alert e comunicados:** integração estática do bloco nativo
   Site Alert no ACULTA420 concluída; runtime aguarda Homelab. Comunicado
   persistente permanece pendente de contrato de segmentação e revisão: os
   mecanismos atuais não comprovam esses requisitos para um novo tipo Node.
7. **dev.7 — Minha Conta:** rotas portuguesas e shell de Mensagens implementados
   com inbox nativa, contador de não lidas e lista privada de eventos de
   mensagem; runtime aguarda Homelab. Avisos permanece em estado vazio até
   existir comunicado persistente com segmentação aprovada.
8. **dev.8 — preferências:** comunicação, privacidade, e-mail e interações,
   reutilizando armazenamento nativo.
9. **dev.9 — Domain/ACULTA420:** presenter neutro e componentes SDC sob
   responsabilidade do tema, com responsividade e acessibilidade.
10. **dev.10 — hardening:** autorização, cache, abuso, entrega, retenção,
    logs, desinstalação e testes automatizados.
11. **rc.1 — homologação:** gates, testes no Homelab, Config Sync, isolamento
    de contas/domínios e revisão final; runtime e e-mails controlados.

## Critérios de avanço

- Cada fase tem commit claro na branch de trabalho e atualiza o changelog e a
  descrição da única PR.
- Não declarar comportamento verificado sem executar a verificação no ambiente
  correspondente.
- Dependências só entram depois de código instalado, compatibilidade e
  `composer require --dry-run` compatível com o lock.
- Nenhuma fase faz merge sem autorização expressa.
- Release só termina após testes proporcionais e gate de segurança; dados
  privados, conteúdo e configuração de outros módulos não são removidos na
  desinstalação.

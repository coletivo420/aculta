# Mensagens e notificações — regras locais

Herde `AGENTS.md` da raiz e do Portal. Consulte o README e os contratos locais apenas para mudanças neste submódulo.

- Respeite destinatário, autorização, retenção e privacidade; não inclua payloads pessoais em logs. Preserve vínculos com tópicos/posts e trate migrações/avisos legados de modo verificável e não destrutivo.
- Verifique código instalado, dependências e testes pertinentes; não assuma estado do Runtime a partir de documentação histórica.

## Porte do Private Message 4.0.0-alpha5

Antes de portar entidades, hooks ou serviços do Private Message, aplique a skill `aculta-private-message-port` e consulte `docs/PRIVATE-MESSAGE-PORT-CUTOVER.md`. A cópia de fontes deve ser preparada fora da descoberta Drupal. Não remova o contrib nem altere schema/provider/Runtime sem ensaio restaurável em clone e gates completos.

# Mailer — instruções locais

Herde regras da raiz e Portal. Leia `README.md` e `docs/ROADMAP.md` apenas para alterações deste submódulo.

- Trate segredos SMTP/API, credenciais e conteúdo de mensagens como sensíveis.
- Preserve limites de acesso, políticas de entrega, privacidade de logs e separação de ambientes.
- Evite enviar payloads reais ou credenciais para contexto, testes ou telemetria.
- Execute apenas verificações pertinentes; não envie e-mails reais sem autorização e ambiente confirmado.

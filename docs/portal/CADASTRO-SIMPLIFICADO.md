# Cadastro simplificado em todos os checkouts

**Decisões do responsável (2026-10-27).**

1. **Confirmação do e-mail:** fluxo novo, só para a conta simplificada. Não reaproveita a confirmação da troca de e-mail.
   Usa um link com segredo e validade, e o modelo `account_simplified_confirmation` do `aculta_mailer`.
2. **Criação da senha:** depois de confirmar o e-mail, a pessoa vê um modal do próprio site, no estilo dos existentes,
   **sem opção de fechar**, para criar a senha. O mesmo modal será reaproveitado na recuperação de senha. A recuperação
   ainda não está configurada: fica no plano.
3. **Estado antes da confirmação:** a conta simplificada tem o mesmo comportamento de uma conta com e-mail não
   confirmado, que o projeto já aplica. Não cria regra nova de bloqueio.

## Escopo

- Todos os checkouts: loja (`default`), LMS (`curso`) e doação (`doacao`).
- O e-mail é obrigatório em todos (já é, pelo painel de contato do Commerce).

## Pendências

- Recuperação de senha pelo mesmo modal (hoje não configurada).
- Painel administrativo com as opções do cadastro simplificado: depois do backend, conforme pedido do responsável.

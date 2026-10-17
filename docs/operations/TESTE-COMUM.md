# Usuário de teste do site: teste.comum

Documento para humanos e agentes. **Não contém a senha.** O valor fica em `secrets/teste_comum.env` (ignorado pelo Git, modo 600).

## Dados
| Campo | Valor |
| --- | --- |
| Usuário | `teste.comum` (uid 5) |
| Host de login | `https://conta.aculta.toca.net.br/entrar` |
| Senha | ler `TESTE_COMUM_PASSWORD` em `secrets/teste_comum.env` |
| Papéis | `authenticated`, `email_confirmed`, `wiki_contributor` (sem administrador) |

## Regras para agentes
1. Use a senha de `secrets/teste_comum.env`. Não peça outra nem tente outras.
2. **Não altere a senha** (`drush user:password`, painel ou banco) sem decisão do responsável.
3. Antes de cada rodada de teste, rode `scripts/guard-teste-comum.sh`. Se der FAIL, pare e relate ao responsável.
4. Não copie a senha para documentos, commits, mensagens ou saídas de comando.

## Histórico
- 2026-10-17: senha definida pelo responsável e registrada em `secrets/teste_comum.env`. Antes, a senha foi trocada várias vezes por geração automática durante testes; essa prática está proibida pela regra 2.

## E-mails da conta teste.comum (ambiente de testes)
- Só as mensagens endereçadas à conta `teste.comum` são redirecionadas. Nenhum outro destinatário é alterado.
- O destino interno é `4e20coletivo@gmail.com`. O assunto ganha o prefixo `[TESTE para <endereço original>]`, e o cabeçalho `X-Aculta-Destino-Original` guarda o endereço original.
- A configuração fica em `web/sites/default/settings.local.php` (bloco do ambiente de teste, não versionado): `aculta_mail_redirect` e `aculta_mail_redirect_for`. A lógica está em `src/Mail/TestMailRedirect.php`.
- Teste de 2026-10-17: a mensagem para `teste.comum` foi redirecionada e o servidor respondeu `552 Your monthly email allowance is exhausted`. A cota mensal do SMTP2GO está esgotada: nenhum e-mail sai até a cota renovar ou o plano mudar. Decisão pendente do responsável.


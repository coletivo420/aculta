# Inspeção visual de e-mails pela caixa de monitoramento

Instrução para agentes e pessoas que precisam ver como um e-mail do sistema chegou.

## Caixa usada

- `dmarc@aculta.org` é **alias** de `naoresponda@aculta.org`. A leitura é feita pela conta `naoresponda@aculta.org`.
- Servidor IMAP, porta, usuário e pasta: `aculta_mailer.imap` (`host: imap.hostinger.com`, `port: 993`, `user: naoresponda@aculta.org`, `mailbox: INBOX`).
- Senha: variável `IMAP_PASSWORD` em `secrets/aculta.secrets.env` (ver [SECRETS.md](SECRETS.md)). **Nunca imprima, copie, registre ou cite o valor.**

## Regras

1. **Somente leitura.** Não apague, não mova e não marque mensagens sem autorização explícita do responsável.
2. A senha entra no `curl` por arquivo de configuração temporário (modo `0600`), que é apagado ao final. Não use a senha como argumento de linha de comando.
3. Para testes de envio, use `dmarc@aculta.org` como destinatário. Enviar a outro endereço exige autorização.
4. Teste de envio pelo formulário de modelos ativa o modelo do tipo. Se o modelo for de uso real, ative-o só durante o teste e remova-o em seguida, com autorização.
5. Os logos das mensagens podem não carregar fora do site (o clique em imagens externas é bloqueado). Avalie a estrutura e as cores, não só a imagem.

## Procedimento (leitura)

```sh
set -a; . secrets/aculta.secrets.env; set +a
cfg=$(mktemp); chmod 600 "$cfg"
printf 'user = "naoresponda@aculta.org:%s"\n' "$IMAP_PASSWORD" > "$cfg"
curl -s -K "$cfg" "imaps://imap.hostinger.com:993/INBOX?ALL" | tail -n 5   # lista; sem alterar nada
rm -f "$cfg"
```

Para baixar uma mensagem, use `imaps://imap.hostinger.com:993/INBOX;UID=<n>` com o mesmo arquivo de configuração. Salve o HTML em
um diretório temporário do agente, não no repositório. Para ver o resultado, abra o HTML em um navegador headless e
gere uma captura de tela.

## Onde está o resultado

- Envelope e cores do e-mail: `aculta420` (`templates/aculta-mailer-wrap.html.twig`, `src/Hook/MailEnvelopeHooks.php`).
- Corpo, layout e marca: `aculta_mailer` (`src/Template/MailTemplateRenderer.php`).
- Registro de envios: tabela `aculta_mail_log` (status, transporte, correlação). Não contém o corpo.

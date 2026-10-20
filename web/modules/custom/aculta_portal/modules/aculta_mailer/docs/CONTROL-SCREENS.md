# Telas de controle dos e-mails do sistema (planejamento)

Status: planejamento, 2026-10-20. Referência: telas do Ultimate Member para e-mails do sistema (lista com estado,
destinatário e botão "Gerenciar"; edição com habilitar/desabilitar, assunto com tokens, editor visual e de código).

## O que o Ultimate Member faz e o que adotamos

| Recurso no UM | Adotamos como | Base no nosso projeto |
|---|---|---|
| Lista dos e-mails do sistema com ✓/✗ | Lista de tipos de e-mail com estado (ativo, desativado, sem modelo) | `MailTypeRegistry` + `MailTemplate` + política `skip` |
| Coluna "Destinatário(s)" (Membro ou administrador) | Coluna com o público do tipo: membro, administrador ou endereço fixo | campo novo no tipo (`recipient_role`) |
| Botão "Gerenciar" por e-mail | Link para o editor do modelo do tipo | `aculta_mailer_template` (F3) |
| Habilitar/Desabilitar por e-mail | Chave por tipo: ativo ou suspenso, com registro `cancelled` | `aculta_mailer.policy:types.<tipo>.skip` |
| Assunto com tokens `{site_name}` | Assunto e corpo com `{{ variavel }}`, listados no editor | já implementado (F3) |
| Editor visual e de código | Editor de texto simples com prévia; visual (CKEditor 5) com HTML restrito, depois | F3 pendente de editor visual |
| Aviso sobre domínio e SMTP | Diagnóstico de domínio: SPF, DKIM e DMARC do remetente, com resultado informativo | página de diagnóstico (F2), a estender |
| Link para o texto padrão | Link para as variáveis disponíveis e o texto padrão do tipo | já existe no editor (F3) |

## Telas propostas

1. **Lista de e-mails do sistema** (`.../envios-de-email/emails`): uma linha por tipo, com nome, estado (✓ ativo, ✗
   suspenso, ⚠ sem modelo), destinatário, e "Gerenciar". Mostra só os tipos registrados pelos módulos.
2. **Edição do e-mail** (`.../emails/{tipo}`): habilitar ou suspender, editar o modelo (assunto e corpo), prévia, enviar
   teste. Une a edição do modelo e a política de envio do tipo, sem duplicar telas.
3. **Diagnóstico de domínio** (na página de diagnóstico): SPF, DKIM e DMARC do remetente, com aviso quando faltam.

## Regras

- Estado "suspenso" usa o mesmo mecanismo da política (`skip`): registro `cancelled`, sem envio e sem retry. Não é
  apagamento.
- Qualquer tipo de e-mail pode ser suspenso pela tela, inclusive os de segurança (decisão do responsável, 2026-10-20).
- O destinatário ("Membro", "Administrador") é dado do tipo, não do modelo editável.
- Textos e variáveis vêm dos tipos; o modelo só substitui o texto padrão.

## Ordem sugerida

1. Coluna de destinatário e lista de tipos com estado (sem alterar envio).
2. Suspensão por tipo pela tela, com o aviso de tipos obrigatórios.
3. Diagnóstico de domínio (SPF, DKIM, DMARC) na página de diagnóstico.
4. Editor visual com HTML restrito (depende de decisão sobre CKEditor 5 e formatos de texto).

## Decisões tomadas (2026-10-20)

- Todos os tipos podem ser suspensos pela tela.
- Editor visual: CKEditor 5 do Drupal, com um formato de texto dedicado (HTML restrito).

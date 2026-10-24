# Arquitetura do ACULTA Mailer

Status: **F0 (auditoria e especificação) e fundação de API em andamento.** Fase ativa e escopo em [ROADMAP](ROADMAP.md).
Base da auditoria: [AUDIT](AUDIT.md). Decisões de porte: [UPSTREAM](UPSTREAM.md).

## 1. Princípio

Consumidores ACULTA dependem somente da API do `aculta_mailer`. Nenhum módulo ACULTA referencia
`Drupal\symfony_mailer`, `mailer_policy`, `mailer_transport`, `mailer_override` ou classes do Symfony Mailer.
A implementação pode usar Symfony Mailer por meio do Core.

## 2. Camadas

```
Consumidores (aculta_user, aculta_game, aculta_messenger, aculta_forum, ...)
        │  AcultaMailerInterface (send)
        ▼
aculta_mailer
  ├── Message      MailMessage, MailStatus, MessageIdFactory        [F1, implementado]
  ├── Delivery     RetrySchedule                                     [F1, implementado]
  ├── Templates    aculta_mailer_template (Config Entity)            [F3, pendente]
  ├── Branding     brand, layout                                     [F4, pendente]
  ├── Policy       adjusters por plugin; reutiliza mailer_policy     [F5, pendente]
  ├── Queue        worker de envio                                   [F6, pendente]
  ├── Logging      content entity de log                             [F7, pendente]
  └── Transport    adaptador para o Symfony Mailer via Core          [F2, pendente]
        ▼
Drupal Core Mail (symfony_mailer) → Symfony Mailer → transporte (DSN)
```

## 3. Contratos públicos (F1)

| Tipo | Papel |
|---|---|
| `AcultaMailerInterface` | Ponto de entrada de envio. Implementação na fase F2. |
| `MailMessage` | Value object imutável: tipo, destinatário, idioma, contexto escalar e ID de correlação. Valida a entrada. |
| `MailStatus` | Enum com estados e transições permitidas. |
| `MessageIdFactory` | Gera `Message-ID`, correlação UUID v4 aleatória e correlação UUID v5 determinística para chaves de idempotência. O host do `Message-ID` é recebido por construtor (vem do `aculta_deployer`). |
| `RetrySchedule` | Política de retry: atraso por tentativa e classificação de erro permanente/temporário. |

## 4. Decisões de arquitetura

1. **Símbolos públicos sob `Drupal\aculta_mailer`.** Interfaces em `Api`, valores em `Message`, política de entrega em `Delivery`.
2. **Contexto restrito a dados.** `MailMessage` aceita escalares, NULL e listas ou linhas de escalares com até dois
   níveis (o resumo de avisos envia linhas `tipo`/`titulo`/`quantidade`). Rejeita objetos e entidades, e aninhamento maior.
   Isso impede que templates recebam entidades inteiras, conforme a regra de variáveis controladas.
3. **Status separado de entrega.** `sent` (o transporte aceitou) não é `delivered` (o destinatário recebeu). Transições
   que pulam etapas são recusadas.
4. **Retry só para erro temporário.** `RetrySchedule` recusa retry de erro permanente (destinatário inválido,
   hard bounce, supressão, bloqueio). Atrasos: imediato, +1 min, +5 min, +30 min, +2 h.
5. **Nenhum host fixo.** O host do `Message-ID` e qualquer URL absoluta vêm de configuração por ambiente.
6. **Submódulo opt-in.** O módulo não é habilitado por `aculta_portal` nesta fase, e não entra em `core.extension`.
   A integração só ocorre em fase própria, com testes e migração.
7. **Sem novo segredo.** Nenhuma credencial é adicionada nesta fase.
8. **Correlação idempotente.** `AcultaMailer` não reenvia uma mensagem se o registro ativo já confirmar a mesma correlação, tipo e destinatário como aceita. O UUID v5 é reservado a retries da mesma entrega lógica; mensagens novas continuam usando UUID v4. O consumidor deve fornecer uma chave HMACada com `hash_salt`, sem texto pessoal ou conteúdo. Essa proteção depende do registro de e-mails habilitado e não equivale a uma transação com o provedor externo.

## 4.1 Transportes

- Padrão: Hostinger (`smtp.hostinger.com`, porta 465), decisão do responsável em 2026-10-20.
- Fallback: SMTP2GO (`mail.smtp2go.com`, porta 465), usado quando o padrão falha em erro temporário.
- Credenciais: somente conforme a regra de segredos do `AGENTS.md`; nenhuma senha vai para Config Sync.
- Implementação do fallback: F2 (`ROADMAP.md`).

## 5. Regras que não podem ser violadas

- Token de confirmação, token de reset e URL secreta nunca entram em log nem em subject.
- Body não é armazenado por padrão.
- Reenvio de token antigo é proibido; reenvio reconstrói a mensagem pelo template atual.
- Consumidores não chamam `plugin.manager.mail` diretamente após a migração de cada ponto.

## 6. Referências de regra do projeto

- Caminhos e rotas: `docs/architecture/ROUTES-SITEMAP.md` (ainda sem rotas nesta fase).
- Endereços por ambiente: `web/modules/custom/aculta_portal/modules/aculta_deployer/docs/ENDERECOS-AMBIENTE.md`.
- Logo do envelope: o logo do tema (`aculta420.settings`, `logo.path`), lido pelo tema; o mailer não guarda cópia do logo por marca.
- Fronteira Portal/tema: `docs/portal/MAPA-MODULO-TEMA.md`.

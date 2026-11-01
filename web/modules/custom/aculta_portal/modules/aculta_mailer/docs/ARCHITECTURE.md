# Arquitetura do ACULTA Mailer

Status: **plataforma de e-mail ativa no projeto.** A configuração versionada seleciona
`aculta_mailer` em `system.mail`; a retirada do contrib de envio foi concluída no Runtime de teste
(F15). Produção e Alpha continuam sujeitas ao ciclo autorizado indicado no
[ROADMAP](ROADMAP.md). A auditoria e as decisões de porte são registros de origem:
[AUDIT](AUDIT.md) e [UPSTREAM](UPSTREAM.md).

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
  ├── Message      MailMessage, MailStatus, MessageIdFactory        [F1]
  ├── Delivery     política, retries e failover                     [F2/F5/F6]
  ├── Templates    aculta_mailer_template (Config Entity)            [F3]
  ├── Branding     layouts e envelope HTML                           [F4]
  ├── Policy       ajustes por tipo, transporte e prioridade         [F5]
  ├── Queue        fila aculta_mailer_send e worker                  [F6]
  ├── Logging      registro, retenção e retry cifrado                [F7/F16]
  └── Transport    SMTP/Brevo pela API do Symfony Mailer             [F2/F14]
        ▼
Drupal Core Mail (`system.mail = aculta_mailer`) → Symfony Mailer → SMTP/Brevo
```

## 3. Contratos públicos (F1)

| Tipo | Papel |
|---|---|
| `AcultaMailerInterface` | Ponto de entrada de envio e consulta de aceite por correlação opaca. |
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
6. **Backend compartilhado do Core.** O módulo está habilitado na configuração versionada e é selecionado por
   `system.mail`. A F15 removeu o contrib de envio no Runtime de teste depois da validação do caminho da aplicação;
   a promoção para produção permanece sujeita ao ciclo operacional do projeto.
7. **Sem novo segredo.** Nenhuma credencial é adicionada nesta fase.
8. **Correlação idempotente.** `AcultaMailer` não reenvia uma mensagem se o registro ativo já confirmar a mesma correlação, tipo e destinatário como aceita. `wasAcceptedCorrelation()` permite que um consumidor interno reconcilie um lote persistido sem armazenar novamente endereço ou conteúdo; a correlação é opaca e gerada com chave HMACada com `hash_salt`. O UUID v5 é reservado a retries da mesma entrega lógica; mensagens novas continuam usando UUID v4. Envios idempotentes falham temporariamente se o log estiver desligado. Esta proteção depende do registro ativo e não equivale a uma transação com o provedor externo.

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

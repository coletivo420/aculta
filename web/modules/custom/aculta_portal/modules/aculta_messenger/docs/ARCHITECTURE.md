# Arquitetura do ACULTA Messenger

Documento de referência arquitetural. Dono: `aculta_messenger` no ACULTA
Portal. Revisão: 2026-10-14.

## Fronteira

```text
Drupal Core + módulos contrib (dados, APIs, access, entrega)
                         ↓
              aculta_portal/aculta_messenger
             integração, URLs, access, cache
                         ↓
               contrato neutro renderizável
                         ↓
                     ACULTA420
```

O módulo agrega as fontes existentes; não cria uma segunda fonte de verdade.
ACULTA420 recebe apenas dados e render arrays já autorizados. O tema não decide
destinatários, permissões, domínio, armazenamento ou política de notificação.

## Fontes de verdade

| Capacidade | Fonte de verdade | Papel do Messenger | Storage próprio |
| --- | --- | --- | --- |
| Conversa e mensagens privadas | Private Message | Integração de UX, ligações e eventual presenter | Não |
| Evento/notificação registrada | Message, quando o caso exigir Message entity | Define tipos de eventos aprovados e coordena geração | Não |
| Entrega de evento | Message Notify + Drupal Mail | Define conteúdo mínimo, preferências e destinatário permitido | Não |
| Assinatura | Message Subscribe + Flag | Seleciona eventos suportados e filtra por access | Não |
| Alerta temporário | Site Alert | Entrada e apresentação do alerta | Não |
| Comunicado editorial persistente | Node, revisão, workflow e Views | Segmentação e integração quando houver política verificável | Não |
| Preferência de usuário | Configuração/field/entity nativa a confirmar no upstream | Orquestra a UX sem cópia paralela | Não até prova de lacuna |
| Identidade e papel | User + Profile + Group | Aplicação de política sem replicar identidade/associação | Não |
| Host e propósito | Domain + `DomainPurposeManager` | Resolver rotas canônicas e aplicar política de propósito | Não |

## Contratos de propósito

| Propósito | Responsabilidade |
| --- | --- |
| `account` | Central de conversas, avisos pessoais e notificações do membro |
| `main` | Administração de alertas/comunicados; não é uma caixa postal alternativa |
| Demais purposes existentes | Podem produzir eventos autorizados ou apontar para `account`; não hospedam inbox |

As rotas novas da central devem declarar ownership `account` com a opção de
rota do Portal e URLs absolutas/cross-purpose devem ser produzidas por
`DomainPurposeManager`. GET/HEAD pode navegar para outro purpose conforme a
política; POST/PUT/PATCH/DELETE nunca são redirecionados nem replayados entre
hosts. Redirect deliberado entre purposes usa `TrustedRedirectResponse` e URL
resolvida pelo serviço. Nenhuma rota pública usa slug inglês/técnico; rotas de
ação preservam a exceção de rota técnica documentada pelo projeto.

## Eventos e públicos iniciais

| Evento | Público elegível | Canal | Regra |
| --- | --- | --- | --- |
| Nova mensagem privada | Participantes autorizados não remetentes | Inbox e aviso por e-mail, se habilitado | E-mail não inclui corpo; link seguro para a central |
| Comunicado publicado | Membros incluídos por segmentação verificável | Central; e-mail conforme preferência/política | Access reavaliado na geração e entrega |
| Evento de entidade assinada | Assinantes ativos com acesso atual ao contexto | Message Notify quando configurado | Revogação de access suprime entrega |
| Site Alert ativo | Visitantes conforme regra nativa | Bloco/componente global de apresentação | Início, término e severidade vêm da entidade Site Alert |

Identificadores estáveis, chaves idempotentes, filas, tentativas e hooks
concretos serão definidos depois da inspeção das versões instaladas. Não criar
uma segunda fila ou tabela antes de provar necessidade. Uma notificação não
deve revelar a existência de usuário, conversa ou conteúdo a público não
autorizado.

## Inventário técnico upstream

Os módulos ainda não estão instalados. Não presumir nomes internos de entidades,
serviços, rotas, permissões, config, eventos ou access handlers. A lista exata
deve ser obtida dos pacotes travados e do código instalado durante a Fase 1;
os checkpoints estão em [INTEGRATIONS.md](INTEGRATIONS.md). Essa verificação é
condição para declarar a fundação completa e para escolher implementações.

## Decisões arquiteturais

### ADR-MSG-001 — Contrib como fonte de verdade

**Decisão:** não criar engine, storage ou preferências duplicadas no Portal.

**Motivo:** conversas, mensagens, flags e alertas já possuem módulos
especializados. Um espelho divergiria em autorização, exclusão e retenção.
Integração custom só entra quando o upstream não fornece o contrato exigido e
a lacuna for demonstrada por código/teste.

### ADR-MSG-002 — Conta única para comunicação

**Decisão:** toda caixa postal fica no purpose `account`; administração fica em
`main`.

**Motivo:** a separação de host não pode criar caixas inconsistentes nem
permitir acesso administrativo em purpose público. Domain determina URL e
ownership; não se infere purpose do hostname no módulo ou tema.

### ADR-MSG-003 — Notificação mínima

**Decisão:** e-mail de mensagem privada comunica o evento e leva a link seguro;
corpo/conteúdo privado não vai por e-mail como padrão.

**Motivo:** e-mail pode ser encaminhado, copiado por provedores e persistido em
caixas compartilhadas. A leitura do conteúdo continua protegida pelo access
online.

### ADR-MSG-004 — Site Alert é temporário; Node é editorial

**Decisão:** Site Alert guarda alertas transitórios. Comunicados com revisão,
autoria, publicação, validade e histórico usam Node/Views/workflow se a
segmentação for verificável.

**Motivo:** não duplicar o armazenamento Site Alert nem forçar seu modelo
temporário a funcionar como arquivo editorial.

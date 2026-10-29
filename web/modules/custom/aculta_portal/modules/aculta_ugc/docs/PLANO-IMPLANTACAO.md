# Plano de implantação — ACULTA UGC (`aculta_ugc`)

Submódulo proposto para reunir a infraestrutura comum ao conteúdo criado por usuários: denúncia,
aviso aos moderadores, limites de uso e metadados opcionais de mídia. Hoje essas peças existem espalhadas
(fórum, `aculta_user`, Marley, WE SATIVA, Wiki). Este plano define o que sai de onde, sem mudar os
contratos de moderação de cada frente.

Status: **plano**. Nenhum código foi criado. Decisões D-01 a D-12 já registradas; a seção 6 distingue decisões vigentes de parâmetros futuros.

## 1. Problema

O ACULTA recebe conteúdo de usuários em várias frentes, e cada uma resolve parte do mesmo problema:

| Frente | O que já existe | Lacuna comum |
| --- | --- | --- |
| Fórum (`aculta_forum`) | Flags `sinalizar_topico`, `sinalizar_comentario`, `sinalizar_post_forum`; workflow `aculta_forum_post`; fila de moderação | Aviso aos moderadores por denúncia nova: não localizado |
| Conta (`aculta_user`) | `ReportQueue` (fila de denúncias de conteúdo público) | Fila sem painel de acompanhamento e sem prazo |
| Marley e WE SATIVA | Contratos de moderação próprios (`MARLEY-WIKI-MODERATION.md`, `WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md`) | Regras de denúncia repetidas, sem reuso |
| Wiki (`aculta_wiki`) | Moderação do Core (workflow editorial) | Sem denúncia de verbete |
| Uploads (`aculta_image`, `aculta_gallery`, Fórum) | Política central em `USER-UPLOADS.md` | Metadados opcionais de crédito/autoria ainda dependem de contrato |
| Formulários públicos | Turnstile vigente | Limites por conta/tempo ainda não fixados; Honeypot rejeitado em D-11 |

## 2. Escopo

Dentro:

1. **Denúncia comum:** motivos padronizados, registro, limite por pessoa e por conteúdo, e vínculo com o
   objeto denunciado (tópico, comentário, post, verbete, evento, perfil).
2. **Aviso aos moderadores:** aviso por código a cada denúncia nova, com destinatários por área (purpose).
3. **Painel de acompanhamento:** contagens e tempos agregados (ver `aculta_statistics`, I-03).
4. **Controle de abuso:** limites de taxa a decidir e testar, sem substituir o Turnstile vigente (D-11).
5. **Mídia de UGC:** estudar crédito/autoria e licença **opcionais** sem filtro compulsório (D-10).

Fora:

- Política de upload (fica em `USER-UPLOADS.md`, dona: `aculta_portal`).
- Contratos de moderação de Marley, WE SATIVA e Wiki (ficam nas frentes). O `aculta_ugc` oferece o
  serviço; cada frente decide quando usar.
- Edição, aprovação e publicação de conteúdo (ficam no workflow de cada tipo).
- Notificações gerais (ficam em `aculta_messenger` e `AVISOS-E-NOTIFICACOES.md`).
- ECA. O OpenCulturas usa ECA para o aviso de denúncia; aqui a regra fica em código, com teste.

## 3. Arquitetura proposta

```
Frente (fórum, wiki, eventos, conta)
        │  registra denúncia (objeto, motivo, pessoa)
        ▼
aculta_ugc  ── ReportService ── limite de taxa aprovado (entrada)
        │
        ├── ModerationNotifier ──▶ aculta_messenger (aviso por código)
        ├── ReportRepository (entidade própria, sem dado pessoal além do UID)
        └── MetricsPublisher ──▶ aculta_statistics (só contagens)

Política de upload ─ USER-UPLOADS.md (consumida, não duplicada)
Metadados opcionais ─ política do aculta_image (consumida)
```

Regras de desenho:

- **Entidade de denúncia própria**, com UID do denunciante, objeto (tipo e ID), motivo, estado e decisão.
  Nunca guarda texto livre longo nem IP.
- **Quem denunciou não aparece** em nenhum aviso ou painel. O moderador vê o objeto e o motivo.
- **O objeto denunciado não é apagado pela denúncia.** Só a decisão de moderação muda o estado.
- **Denúncia repetida** do mesmo usuário ao mesmo objeto é ignorada, sem erro visível.
- **Limite de denúncias** por conta por dia, com teto que impede abuso sem silenciar a pessoa.
- Destinatários do aviso vêm da configuração por purpose, e não de lista fixa no código.

## 4. Fases

| Fase | Conteúdo | Saída |
| --- | --- | --- |
| U0 | Decisões da seção 6; mapeamento dos flags e das filas existentes; ADR de fronteira com `aculta_user` | ADR aprovado |
| U1 | Entidade de denúncia, serviço, motivos e testes Kernel (duplicidade, limite, acesso negado) | Denúncia funcional em teste |
| U2 | Aviso aos moderadores por código, com teste de destinatário e de não vazamento do denunciante | Aviso em teste |
| U3 | Migração das denúncias de `ReportQueue` e dos flags de fórum para a entidade nova, sem perda de histórico | Histórico preservado |
| U4 | Painel de acompanhamento (I-03) em `aculta_statistics`, só com agregados | Painel em teste |
| U5 | Política de limite de taxa aprovada, integrada ao Turnstile atual (D-11); sem Honeypot | Formulários protegidos e acessíveis |
| U6 | Metadados opcionais de autoria/crédito/licença, só após contrato com aculta_image (D-10) | Nenhuma exigência de licença ou filtro compulsório |
| U7 | Revisão visual (V-4 do `OPENCULTURAS-COMPARATIVO.md`) e acessibilidade | Revisão aprovada |

Ordem de dependência: U0 → U1 → (U2, U5) → U3 → U4; U6 pode correr em paralelo a U2.

## 5. Testes e gates

- Kernel: duplicidade, limite por dia, acesso do moderador e do usuário comum, denúncia de objeto
  inexistente ou sem acesso, e ausência de dado pessoal no aviso.
- Unit: limites de taxa aprovados e integração do fluxo atual de proteção.
- Navegador (Firefox ESR, `DISPLAY=:0`): formulário de denúncia com teclado e leitor de tela, e
  mensagem genérica quando bloqueado.
- Temas: ACULTA420 e Olivero.
- Gates existentes: `validate-single-acceptance.php` (se houver consentimento), `validate-public-slugs.php`
  (rotas de denúncia em português), `validate-no-temporary-route-aliases.php`.

## 6. Decisões vigentes e parâmetros ainda pendentes (2026-10-29)

- **D-04:** migrar `ReportQueue` para uma fila comum de `aculta_ugc`, com contagem pré/pós e testes de equivalência.
- **D-03:** destinatários de denúncias por **purpose**, em entidade de configuração; avisos por código/Mailer, sem ECA.
- **D-01/D-02:** reivindicação de local/evento/organização depende de vínculo verificado e decisão da moderação; padrão fechado, sem criar papel `organizador`.
- **D-10:** licença de mídia **opcional**; retirar qualquer imposição de Creative Commons e filtro de biblioteca por licença.
- **D-11:** manter captcha Turnstile atual; **não** instalar Honeypot nesta fase.
- **Pendente de decisão própria:** limites numéricos de denúncia/rate limit, retenção e política de recurso contra decisão. Não preencher valores automaticamente.
- **Contato de divulgação:** `abuso@aculta.org` já consta no `SECURITY.md` (P-01 resolvido).

## 7. Riscos

| Risco | Mitigação |
| --- | --- |
| Denúncia usada para silenciar alguém (abuso da ferramenta) | Limite por conta, moderação das denúncias em massa, registro de denúncias rejeitadas |
| Vazamento de quem denunciou | Nenhum dado do denunciante em aviso e painel; teste de regressão |
| Duplicar contratos de Marley e WE SATIVA | Cada frente mantém o seu; `aculta_ugc` oferece o serviço |
| Migração de denúncias antigas perder histórico | U3 com contagem antes e depois e teste de equivalência |
| Proteção contra abuso com captcha vigente | Testar Turnstile, limites de taxa aprovados e caminhos acessíveis, sem introduzir Honeypot |
| Metadados opcionais de mídia | Informação clara sobre crédito e licença quando informados, sem preenchimento obrigatório (D-10) |

## 8. Documentos relacionados

- `docs/architecture/OPENCULTURAS-COMPARATIVO.md` (seção 4.6 e V-4)
- `docs/portal/USER-UPLOADS.md`
- `docs/portal/AVISOS-E-NOTIFICACOES.md`
- `docs/portal/MARLEY-WIKI-MODERATION.md`
- `docs/portal/WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md`
- `web/modules/custom/aculta_portal/modules/aculta_user/src/Moderation/ReportQueue.php`

## 9. Código de referência do OpenCulturas

Padrões a reescrever (não copiar), com defeitos a evitar:

- **C-01:** destinatários de aviso como entidade de configuração, sem ECA (U2). Substitui o campo de texto
  livre de `alerts_recipients` do `aculta_mailer` para a finalidade de denúncia.
- **C-05:** cache por usuário da lista "Meus salvos": contexto de UID e teste de duas contas antes de adicionar invalidação customizada.
- Fonte e defeitos: `docs/architecture/OPENCULTURAS-INCORPORACAO-CODIGO.md`.

## 10. Incremento OC315: integração de denúncias, perfis e organizações

[Auditoria 3.1.5](../../../../../../../docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md) / OC315-05 e OC315-07. Planejar sob U2/U3, não criar fila paralela. Registrar quem reivindicou, entidade reivindicada, vínculo alegado, decisão administrativa e quando cada permissão começa/cessa. Não transferir propriedade em envio de pedido. Garantir notificações por purpose e interfaces sem exposição de identidade do denunciante, com testes de acesso e de migração. **Nenhum código executado nesta rodada.**

## Complemento aprovado OC-C - 2026-10-29

`aculta_ugc` é **plano**, ainda não submódulo executável. Integrar, sem substituir silenciosamente, denúncias/Flags/workflows existentes: fila comum com migração contabilizada, decisão auditável, acesso por purpose, avisos e testes. Adaptar como conceito `EcaNotificationRecipient` para destinatários de notificação por purpose (D-03), sem instalar ECA. Reivindicação: padrão fechado e decisão humana vinculada à organização verificada, nunca transferência automática. Manter D-11 (sem Honeypot) e D-10 (licença de mídia opcional).

Referência: `docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md`.

# Plano de implantação — ACULTA UGC (`aculta_ugc`)

Submódulo proposto para reunir a infraestrutura comum ao conteúdo criado por usuários: denúncia,
aviso aos moderadores, anti-spam sem terceiros e licença de mídia. Hoje essas peças existem espalhadas
(fórum, `aculta_user`, Marley, WE SATIVA, Wiki). Este plano define o que sai de onde, sem mudar os
contratos de moderação de cada frente.

Status: **plano**. Nenhum código foi criado. Decisões em aberto na seção 6.

## 1. Problema

O ACULTA recebe conteúdo de usuários em várias frentes, e cada uma resolve parte do mesmo problema:

| Frente | O que já existe | Lacuna comum |
| --- | --- | --- |
| Fórum (`aculta_forum`) | Flags `sinalizar_topico`, `sinalizar_comentario`, `sinalizar_post_forum`; workflow `aculta_forum_post`; fila de moderação | Aviso aos moderadores por denúncia nova: não localizado |
| Conta (`aculta_user`) | `ReportQueue` (fila de denúncias de conteúdo público) | Fila sem painel de acompanhamento e sem prazo |
| Marley e WE SATIVA | Contratos de moderação próprios (`MARLEY-WIKI-MODERATION.md`, `WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md`) | Regras de denúncia repetidas, sem reuso |
| Wiki (`aculta_wiki`) | Moderação do Core (workflow editorial) | Sem denúncia de verbete |
| Uploads (`aculta_image`, `aculta_gallery`, Fórum) | Política central em `USER-UPLOADS.md` | Licença da mídia não é declarada |
| Formulários públicos | Captcha externo (Turnstile) | Sem camada local antes do captcha (privacidade e custo) |

## 2. Escopo

Dentro:

1. **Denúncia comum:** motivos padronizados, registro, limite por pessoa e por conteúdo, e vínculo com o
   objeto denunciado (tópico, comentário, post, verbete, evento, perfil).
2. **Aviso aos moderadores:** aviso por código a cada denúncia nova, com destinatários por área (purpose).
3. **Painel de acompanhamento:** contagens e tempos agregados (ver `aculta_statistics`, I-03).
4. **Anti-spam local:** honeypot e limite de taxa antes do captcha externo (I-14).
5. **Licença de mídia de UGC:** campo de licença obrigatório quando a mídia é pública (I-13).

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
aculta_ugc  ── ReportService ── limite de taxa e honeypot (entrada)
        │
        ├── ModerationNotifier ──▶ aculta_messenger (aviso por código)
        ├── ReportRepository (entidade própria, sem dado pessoal além do UID)
        └── MetricsPublisher ──▶ aculta_statistics (só contagens)

Política de upload ─ USER-UPLOADS.md (consumida, não duplicada)
Licença de mídia ─ campo no uploader do aculta_image (consumido)
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
| U5 | Honeypot e limite de taxa em formulários públicos (I-14), sem captcha externo como primeira barreira | Formulários protegidos |
| U6 | Campo de licença de mídia (I-13) no uploader, com padrão e validação | Licença obrigatória para mídia pública |
| U7 | Revisão visual (V-4 do `OPENCULTURAS-COMPARATIVO.md`) e acessibilidade | Revisão aprovada |

Ordem de dependência: U0 → U1 → (U2, U5) → U3 → U4; U6 pode correr em paralelo a U2.

## 5. Testes e gates

- Kernel: duplicidade, limite por dia, acesso do moderador e do usuário comum, denúncia de objeto
  inexistente ou sem acesso, e ausência de dado pessoal no aviso.
- Unit: regras de limite e de honeypot.
- Navegador (Firefox ESR, `DISPLAY=:0`): formulário de denúncia com teclado e leitor de tela, e
  mensagem genérica quando bloqueado.
- Temas: ACULTA420 e Olivero.
- Gates existentes: `validate-single-acceptance.php` (se houver consentimento), `validate-public-slugs.php`
  (rotas de denúncia em português), `validate-no-temporary-route-aliases.php`.

## 6. Decisões para o responsável

1. **Fronteira com `aculta_user`:** `ReportQueue` migra para `aculta_ugc` (recomendado) ou continua lá e
   `aculta_ugc` só consome?
2. **Destinatários do aviso:** equipe de moderação global, por purpose ou por área (recomendado: por purpose).
3. **Limite de denúncias:** valor por conta e por dia.
4. **Contato de divulgação responsável:** informado em `SECURITY.md` (I-17).
5. **Honeypot antes do captcha:** aprovar a troca de ordem nos formulários públicos.

## 7. Riscos

| Risco | Mitigação |
| --- | --- |
| Denúncia usada para silenciar alguém (abuso da ferramenta) | Limite por conta, moderação das denúncias em massa, registro de denúncias rejeitadas |
| Vazamento de quem denunciou | Nenhum dado do denunciante em aviso e painel; teste de regressão |
| Duplicar contratos de Marley e WE SATIVA | Cada frente mantém o seu; `aculta_ugc` oferece o serviço |
| Migração de denúncias antigas perder histórico | U3 com contagem antes e depois e teste de equivalência |
| Honeypot quebrar acessibilidade | Campo oculto de forma que leitor de tela e teclado não o alcancem; teste |
| Licença de mídia confusa para o usuário | Padrão pré-selecionado com explicação curta; ajuda contextual |

## 8. Documentos relacionados

- `docs/architecture/OPENCULTURAS-COMPARATIVO.md` (seção 4.6 e V-4)
- `docs/portal/USER-UPLOADS.md`
- `docs/portal/AVISOS-E-NOTIFICACOES.md`
- `docs/portal/MARLEY-WIKI-MODERATION.md`
- `docs/portal/WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md`
- `web/modules/custom/aculta_portal/modules/aculta_user/src/Moderation/ReportQueue.php`

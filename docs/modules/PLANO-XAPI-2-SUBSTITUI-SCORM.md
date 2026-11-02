# Plano de estudo: substituir SCORM por xAPI 2.0 e seguintes

Status: **decisões fechadas, ADR pendente**. D-66 (substituição e escopo xAPI 2.0 puro), D-67 (LRS próprio no Drupal), D-68 (retenção configurável, padrão 6 meses) e D-69 (nota pela regra de tentativa), todas em `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`. Nenhuma implementação começa antes do ADR.

Base: [ESTUDO-FERRAMENTAS-LMS-2026.md](ESTUDO-FERRAMENTAS-LMS-2026.md), com as fontes ali listadas.

## 1. O que muda

| Hoje (SCORM, `aculta_lms` dev.106 a dev.111) | Com xAPI 2.0 |
| --- | --- |
| Conteúdo fala com uma API no navegador (`window.API`, `API_1484_11`). | Conteúdo envia declarações (statements) JSON a um endpoint de LRS. Não há API de navegador. |
| Pacote ZIP com `imsmanifest.xml`, lido e validado. | Conteúdo pode ser qualquer página web. Não existe manifesto obrigatório. |
| Progresso é o cmi, traduzido para estado normalizado. | Progresso é o conjunto de declarações; o estado é derivado delas. |
| Player de navegação entre SCOs. | Não há navegação padrão. A ordem e o lançamento ficam com o LMS. |

Consequência direta: o código SCORM (manifesto, validador, extrator, APIs 1.2 e 2004, snapshot do cmi, player) **foi removido em `aculta_lms` 0.1.0-dev.112**, por decisão do responsável. Nenhum pacote SCORM foi publicado nem está em produção, então não há migração de conteúdo.

## 2. xAPI 2.0 em termos práticos

- **Declaração**: `actor` (quem), `verb` (o que fez), `object` (sobre o quê), com `id` em UUID e `timestamp`. Campos opcionais: `result`, `context`, `attachments`.
- **Padrão**: IEEE 9274.1.1-2023. Vendors devem suportar 1.0.x e 2.0 por um tempo ([xapi.com](https://xapi.com/blog/what-vendors-can-do-to-prepare-for-xapi-2-0)).
- **Autorização**: a 2.0 retirou a autorização da especificação. Cada implementação define a sua. No ACULTA isso significa credencial própria, emitida pelo Drupal, e nada parecido com a chave do LRS no conteúdo.
- **Lançamento**: xAPI não define como o conteúdo recebe o endpoint e o token. Isso fica para o LMS. O cmi5 define esse lançamento sobre xAPI 1.0.x.

## 3. Arquitetura proposta (a validar)

1. **Lançamento**: rota do LMS que gera, para cada tentativa, um token curto e vinculado à matrícula, ao conteúdo e ao actor. O conteúdo recebe endpoint e token pelo lançamento.
2. **Ingestão**: endpoint `POST /statements` (ou equivalente) com as mesmas regras de sanitização já usadas no snapshot SCORM: tamanho máximo por declaração e por lote, tipos validados, idempotência pelo `id` da declaração.
3. **Armazenamento e leitura**: duas alternativas, abaixo.
4. **Tradução para o LMS**: declarações viram estado e pontuação da tentativa (`result.score`, `result.completion`, `result.success`) pelo mesmo mapeamento que hoje existe em `ScormStatusMapper`, adaptado.

### Onde guardar as declarações (decidido: D-67, LRS próprio no Drupal)

| Alternativa | Vantagem | Risco |
| --- | --- | --- |
| **A. Ralph (LRS separado, MIT, Python)** | Serviço pronto e mantido por terceiros. Separa o registro do Drupal. | Mais um serviço para hospedar (Python, mais backend como Elasticsearch ou MongoDB). Confronta a regra de não criar armazenamento paralelo, porque as declarações vivem fora do Drupal. |
| **B. LRS próprio no Drupal (entidade)** | Sem serviço extra. Usa o padrão do projeto. | Carga de escrita e consulta em MariaDB. Implementar o LRS inteiro é trabalho grande e sem ganho frente ao Ralph. |
| **C. Só os resultados no Drupal** | Mantém a regra de sem armazenamento paralelo. Guarda só o estado e a pontuação na tentativa. | Não guarda o histórico completo de declarações, que é o que o xAPI oferece. Perde a análise posterior. |

Decisão tomada (D-67): **B, LRS próprio no Drupal**. Isso difere da recomendação deste estudo, que era C para o progresso. O ADR precisa tratar a duplicidade: as declarações são o registro bruto, e a tentativa guarda o estado derivado. A regra de não criar armazenamento paralelo se aplica ao estado, não às declarações, que são uma capacidade nova.

## 4. Fases propostas (sem implementar antes da decisão)

| Fase | Entrega | Gate |
| --- | --- | --- |
| F0 | ADR com alternativas de LRS, modelo de credencial e destino do código SCORM. | Decisões da seção 5 respondidas. |
| F1 | Validador puro de declaração xAPI 2.0 (campos obrigatórios, tipos, tamanho), com testes. **Concluída em código (dev.114), 20/20 no shim.** | ADR aceito. |
| F2 | Derivação de estado e pontuação a partir de declarações, pura, com testes. **Concluída em código (dev.115), 16/16 no shim, com controle de mutação. Status e nota pela `AttemptRule`.** | F1. |
| F3 | Lançamento com token por tentativa e endpoint de ingestão autenticado, com limite de taxa. | F1 e F2; credencial aprovada. |
| F4 | Runtime: envio real de declarações de um conteúdo de teste, em Firefox ESR, e Olivero. | Ambiente de teste disponível para o agente. |

Cada fase vira um commit separado, como manda o processo do projeto.

## 5. Decisões

Respondidas em 2026-11-02:
- **Escopo** (D-66): xAPI 2.0 puro. cmi5 fica fora; o lançamento é próprio do ACULTA.
- **Código SCORM** (D-66): removido em `aculta_lms` 0.1.0-dev.112.
- **Substituição** (D-66): completa. A regra de demanda real não vale mais para xAPI.
- **Armazenamento** (D-67): LRS próprio no Drupal.
- **Retenção** (D-68): opção administrativa no LMS, padrão de 6 meses. A opção fica no painel do domínio principal.
- **Nota** (D-69): pela regra de tentativa, com `AttemptRule`.

Pendente para o ADR: quem pode consultar o histórico (equipe administrativa, a definir), e a regra de remoção automática ao fim do prazo.

## 6. Riscos

- **Padrão em transição**: a 2.0 é recente e vendors ainda suportam 1.0.x. Se o conteúdo de um parceiro vier em 1.0.x, o endpoint precisa aceitar as duas.
- **Credencial**: sem autorização na especificação, o modelo de token é nossa responsabilidade. Precisa de revisão de segurança e de limite de taxa, como o resto do Portal.
- **Dados pessoais**: declarações trazem identificação do aluno no `actor`. Precisa de mesma atenção que o snapshot SCORM tinha para não guardar campos fora de escopo.
- **Escopo**: xAPI não traz player nem conteúdo. Se o ACULTA não tiver conteúdo xAPI, a funcionalidade fica sem uso. A demanda real continua sendo a condição.

## 7. Limites deste estudo

- Não verifiquei na fonte primária a seção de autorização e o JSON Schema oficial da xAPI 2.0. Os detalhes técnicos da seção 2 devem ser conferidos no texto do IEEE 9274.1.1 antes do ADR.
- Não consultei o módulo lms contrib, então não sei como ele trataria declarações. Vale a mesma ressalva de sempre.

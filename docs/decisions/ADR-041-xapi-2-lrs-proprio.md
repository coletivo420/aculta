# ADR-041 — xAPI 2.0 com LRS próprio no Drupal, substituindo SCORM

- **Status:** Aceita nas decisões do responsável (D-66 a D-69, 2026-11-02). F1/F2 concluídas; F3 implementada em `0.1.0-dev.124` e F4 homologada no Homelab em 2026-11-02 (Firefox ESR, `DISPLAY=:0`, ACULTA420 e Olivero). Evidências e pendências restantes no `CHANGELOG.md` do módulo e em `docs/modules/PLANO-XAPI-2-SUBSTITUI-SCORM.md`.
- **Data:** 2026-11-02.
- **Decisores:** responsável pelo produto.
- **Substitui:** a parte SCORM/xAPI da decisão de adiar conteúdo padronizado sem demanda real (`docs/modules/OPIGNO-CODE-AUDIT.md`, OPA-08 e tabela; `docs/modules/OPIGNO-ACULTA-INDEPENDENCE-PLAN.md`). A decisão de não incorporar o Opigno inteiro permanece.

## Contexto

O `aculta_lms` chegou a implementar SCORM 1.2 e 2004 (manifesto, validação, extração privada, APIs no navegador, sanitização do cmi e player). Por decisão D-66, o SCORM foi removido em `aculta_lms` 0.1.0-dev.112 e substituído por xAPI 2.0, padrão IEEE 9274.1.1-2023. Nenhum pacote SCORM foi publicado, então não há migração.

## Decisão

1. **Padrão:** xAPI 2.0 puro. cmi5 fica fora deste ADR.
2. **Armazenamento (D-67):** LRS próprio no Drupal, com as declarações no MariaDB do projeto. Não há Ralph nem outro LRS separado.
3. **Retenção (D-68):** opção administrativa no LMS para o prazo de retenção das declarações brutas, com padrão de 6 meses. A opção fica na rota administrativa do domínio principal (`/painel-administrativo/**`), com permissão de administração.
4. **Nota (D-69):** cada declaração com resultado vira tentativa, e a regra existente de tentativa (melhor ou última, `AttemptRule`) define a nota.
5. **Autorização:** a xAPI 2.0 não define autorização. O ACULTA emite credencial própria por tentativa, vinculada à matrícula, ao conteúdo e ao ator, e a credencial é tratada como segredo, nunca gravada em configuração exportável.

## Duplicidade a tratar

O LRS guarda as declarações brutas, e a tentativa guarda o estado derivado. Isso é duplicidade de dados. A regra do projeto contra armazenamento paralelo se aplica ao estado, e o ADR deve garantir que o estado seja sempre derivado das declarações, nunca escrito por outro caminho. Declarações e estado precisam ter fonte única de verdade para o progresso.

## Consequências

- Fica dentro do Drupal e do MariaDB, sem serviço extra para hospedar. O custo é a carga de escrita e consulta no banco, que precisa de medição no Runtime.
- Declarações trazem identificação do aluno no ator. A sanitização segue o padrão do snapshot SCORM: só campos previstos, com limites de tamanho, descartando o restante.
- Ingestão exige limite de taxa, tamanho máximo por declaração e por lote, e idempotência pelo `id` da declaração.
- Conteúdos em xAPI 1.0.x de parceiros precisam ser aceitos enquanto o padrão estiver em transição.
- Administração de retenção segue as regras de rota administrativa: só no domínio principal, com 404 nos subdomínios.

## Fora de escopo

- cmi5 e lançamento padronizado de outro padrão.
- Player ou renderização de conteúdo. O conteúdo é externo e envia declarações.
- Migração de SCORM, que não existe em produção.
- Consulta pública ao histórico. A consulta é só administrativa, a definir em ADR próprio se houver demanda.

## Decisões complementares (2026-11-02)

- **Consulta do histórico:** só a equipe administrativa, no painel do domínio principal. Educadores não consultam declarações de alunos.
- **Remoção ao fim do prazo:** apagamento em lote mensal das declarações vencidas, no padrão de retenção de 6 meses. A remoção é do registro bruto; o estado derivado da tentativa não é apagado por ela.
- **Credencial:** aprovado o modelo de token curto por tentativa, vinculado à matrícula, ao conteúdo e à tentativa, emitido pelo Drupal e tratado como segredo.
- **Vínculo com a tentativa:** `lesson_status_id` permanece com esse nome no protocolo e nos registros por compatibilidade, mas é um identificador opaco de `aculta_lms_progress.id`. A suposição de uma tentativa por aula no LMS contrib foi encerrada com a remoção do contrib em P5 (ADR-042); o código ACULTA não depende dela.
- **Segredo do token:** Drupal Key `aculta_lms_xapi_token` com provider env e um segredo por ambiente. A entrada `ACULTA_LMS_XAPI_TOKEN_SECRET` já consta no ACULTA Secrets Contract. Evidência somente leitura no Runtime de desenvolvimento: Key existente e ativa, provider `env` apontando para a variável contratada; variável presente, comprimento 64. O valor não foi lido nem registrado. Em produção, o `aculta_deployer` ainda precisa provisionar a variável após o deploy.
- **Validade do token:** 30 minutos (padrão do código), com teto de 2 horas.
- **Limite de taxa:** serviço flood do Drupal Core, por token de tentativa, sem tabela nova. Não se aplica limite por IP nesta fase.
- **Remoção mensal:** hook de cron do Drupal, uma vez por mês, em lotes, com registro de quantos registros foram removidos.
- **Versão da xAPI:** só xAPI 2.0. Conteúdo em 1.0.x é recusado, conforme D-66; revisar só com demanda registrada.

## Pendências

- Referências históricas: consulta somente leitura no Runtime de desenvolvimento encontrou 3 declarações, todas com `lesson_status_id=4`, e nenhuma entidade `aculta_lms_progress` com esse ID. As linhas foram preservadas, sem reparo. A referência continua opaca e não restaura dependência do contrib.
- Medição de carga de escrita no MariaDB: **DEFERRED**. Executar antes de ativar conteúdo xAPI de produção, quando houver ambiente production-like com topologia e configuração MariaDB representativas; usar carga sintética autorizada e registrar latência/erros sem dados reais de estudantes.
- Provisionar `ACULTA_LMS_XAPI_TOKEN_SECRET` por ambiente no deploy de produção antes de ativar conteúdo xAPI lá.
- Conferência do texto IEEE 9274.1.1-2023 para autorização e JSON Schema antes da F1.

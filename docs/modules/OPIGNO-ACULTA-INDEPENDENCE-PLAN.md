# Opigno seletivo na independência do ACULTA LMS

**Estado:** decisão de escopo e planejamento da primeira fase; não representa port de código Opigno nem remoção concluída do Drupal LMS contrib.  
**Data:** 2026-11-01  
**Relacionados:** [Auditoria Opigno](OPIGNO-CODE-AUDIT.md) · [Inventário de dependência](LMS-CONTRIB-DEPENDENCY-INVENTORY.md) · [Roadmap ACULTA LMS](ACULTA-LMS-ROADMAP.md)

## Objetivo

Definir o que o ACULTA aproveitará do Opigno na primeira fase de independência do ACULTA LMS. A prioridade é estabelecer uma fronteira educacional ACULTA, para que Ganjalab e outros consumidores não dependam diretamente de classes, entidades, rotas e serviços do Drupal LMS contrib.

Opigno será referência de requisitos e interação, não distribuição, backend ou modelo de dados. A primeira fase deve remover acoplamentos por etapas e deixar clara a fonte de verdade em cada etapa; não deve tentar reproduzir todo o Opigno.

## Decisão arquitetural para orientar a fase

A jornada já foi encapsulada por `CourseJourneyReaderInterface`, que agora vive em `aculta_learning_contracts`, sem dependência de `lms:lms`. O provider contrib permanece como adaptador. A dependência de código nos dois sentidos entre `aculta_lms` e `aculta_studio` também foi removida: o LMS não declara o Studio e a apresentação da jornada/sala está no Studio.

Direção-alvo:

- aculta_studio e demais experiências dependem da API de domínio educacional ACULTA;
- o domínio educacional ACULTA não depende de Drupal LMS, aculta_portal ou aculta_studio;
- um adaptador legado implementa os contratos e é o único ponto que acessa o contrib para capacidades ainda não substituídas;
- o adaptador pode ser desativado quando houver provider ACULTA ou outro provider compatível.

Recomenda-se manter aculta_lms como domínio e contratos independentes, separando o provider contrib em submódulo opcional, por exemplo aculta_lms_legacy ou aculta_lms_drupal. O nome final deve seguir as convenções do repositório. O adaptador depende dos contratos ACULTA e do contrib; os consumidores não dependem da classe concreta do adaptador. Se o inventário provar que aculta_lms não pode ser tornado independente por divisão incremental, proponha uma camada de domínio ACULTA independente. Não coloque contratos em aculta_portal nem no Studio: o primeiro exige o contrib e o segundo é apresentação.

Antes de implementar, valide todos os metadados e imports atuais. O alvo deve eliminar a dependência de código reversa entre aculta_lms e aculta_studio, atualizar corretamente dependências Drupal, serviços, autoload e testes, e evitar ciclos.

## O que aproveitaremos do Opigno

| Ideia observada | Aplicação ACULTA | Primeira fase de independência | Posterior ou descartado |
| --- | --- | --- | --- |
| Percurso composto por etapas | Requisito para consultar a jornada do estudante | Contrato ACULTA de leitura da jornada, itens e destinos autorizados; provider legado traduz os dados atuais | Editor visual e persistência própria de currículo |
| Estados e progresso por etapa | Apresentação coerente sem acoplar a interface ao LMS contrib | DTOs ACULTA normalizados, derivados temporariamente do provider existente | Gradebook, tentativas ou progresso duplicados |
| Navegação guiada e próxima ação | Critério de UX e de consulta da sala | Contrato retorna item atual, próxima ação e destino canônico após autorização | Ramificações condicionais, score mínimo e regras complexas |
| Reuso de atividades | Evitar duplicar conteúdo e permitir providers | Definir referências opacas/provider-owned, sem IDs de entidade LMS no domínio | Catálogo ou entidades de módulo Opigno |
| Prévia do estudante | Separar autoria de aprendizagem | A API deve permitir uma leitura autorizada da jornada | Course Builder completo e publicação avançada |
| Árvore visual e ordenação | Inspiração para futura autoria | Registrar como requisito futuro, condicionado a contrato independente de currículo/ordem | Angular, endpoints Opigno ou ordenação visual sem persistência |
| Plugins e scoring | Comparar com Answer Plugins, Webform Score e H5P ACULTA | Inventariar gaps e fronteiras | Port em bloco de plugins, tentativas ou scores |
| Importação/exportação | Referência de portabilidade | Nenhuma implementação | Formato .opi e fluxo export/import Opigno |
| SCORM/xAPI | Capacidade possível apenas com demanda real | Registrar como não assumida | Player, LRS, importadores e extração de pacotes |
| Catálogo, turmas, certificados, mensagens, calendário e comércio | Padrões de fluxo, se úteis | Reutilizar apenas requisitos que não conflitem com owners ACULTA | Entidades e serviços Opigno; duplicação de Group, Commerce, Messenger, Events ou certificados |

## Aproveitamento confirmado na revisão de módulo e learning path (2026-11-02)

Base: `opigno_module` 3.2.8 e `opigno_learning_path` 3.2.8, lidos de forma estática, sem execução. Só conceitos; nenhum trecho foi copiado. Qualquer trecho derivado exige registro em `ORIGEM` (D-25) e revisão da licença GPL-2.0+.

| Item confirmado | Uso ACULTA | Fase | Condição |
| --- | --- | --- | --- |
| Aprovação por tentativa: todas as atividades avaliadas e nota igual ou acima do mínimo; política de manter a mais recente ou a melhor; sorteio de N entre M | Critério da avaliação objetiva | Posterior à fase 1 | Score mínimo está fora da fase 1; só entra com decisão de produto da avaliação |
| Checklist de publicação: ao menos um item obrigatório; validar cada módulo e curso antes de publicar; mensagem por etapa | Validação da autoria própria | Fase C | Implementar como serviço. Não enviar resposta HTTP de dentro do validador (anti-padrão no Opigno) |
| Retomada pela última atividade | Fecha a pendência do `LearningResumeProvider` | Fase 1 | Derivar dos timestamps de `LessonStatus` e `CourseStatus` do LMS. Não criar entidade `LatestActivity` |
| Nota do curso como média das melhores notas por módulo | Só após decisão de produto | Pendente | A regra exata (`courseGetScore`) não foi revisada por completo |
| Sete cenários de teste (acesso à edição, visibilidade, início, conclusão, condição de link entre passos, nota por módulo) | Roteiro de aceite de F6 e F8 | Fase 1 (F6) | Código de teste não reaproveitado |

Não aproveitar: entidades paralelas de status e retomada (`UserModuleStatus`, `LPStatus`, `LatestActivity`); pipeline de PPT para imagem com `exec`; exportação em `sites/default/files/opigno-export`; importação de ZIP sem limites; `\Drupal::` e código estático; Angular e dependências de Group 1.x.

Gaps da ACULTA confirmados pelo código do Opigno, já tratados ou registrados: instalador H5P sem limite de ZIP (`fac3d58`); parser DMARC sem teto (`bf6a089`); download IMAP sem teto (`0d9d754`). Ver `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`.

Decisões já tomadas: o pacote H5P do Opigno é corpus de teste no Homelab (F10), não entra em produção.

Abertos, dependem do responsável: tour de primeiro acesso (`opigno_tour`), que exige decisão antes de qualquer implementação; competências (`opigno_skills_system`), sem decisão.

## Escopo da primeira fase

### Incluído

1. Inventariar referências diretas e indiretas ao Drupal LMS: imports, serviços, entidades, rotas, plugins, Views, configuração e Composer.
2. Definir contratos ACULTA mínimos para jornada e resumo do estudante somente quando houver consumidores reais.
3. Separar contratos/DTOs do provider legado contrib.
4. Fazer consumidores ACULTA dependerem dos contratos, nunca da implementação concreta.
5. Remover dependência de código reversa entre domínio/provider e Studio.
6. Manter o provider legado como fonte de verdade temporária e documentar a substituição.
7. Cobrir contratos e provider com testes de leitura, autorização, cache e destinos permitidos.
8. Produzir inventário de dependências restantes com caminhos verificáveis e contagem reproduzível.

### Excluído

- Substituir todas as entidades do LMS ou migrar respostas, tentativas, notas e progresso.
- Reconstruir todas as rotas e formulários nativos nesta etapa.
- Implementar o Course Builder visual completo.
- Portar código Opigno ou adicionar dependências Composer Opigno.
- Habilitar SCORM, xAPI, importação ou exportação.
- Remover drupal/lms do Composer antes de substituir Portal, módulos opcionais, configuração, Views, rotas, tema, scripts e dados que devam ser retidos.

## Cursos legados

Compatibilidade integral com todos os cursos legados **não é requisito**. Para economizar tempo, o plano pode selecionar cursos que não serão migrados e pode prever a exclusão dos cursos legados que não sejam necessários no novo domínio.

Esta fase não executa exclusão. Antes de apagar dados em ambiente compartilhado ou Runtime, é necessário inventariar dependências (turmas, matrículas, respostas, certificados, arquivos e referências), confirmar o descarte, preservar uma possibilidade de retorno adequada e obter autorização explícita para a ação destrutiva. Em ambiente descartável, cursos de teste podem ser recriados; não se deve construir compatibilidade indefinida para conteúdo obsoleto.

## Critérios de aceite da primeira fase

- Contratos/DTOs ACULTA residem em módulo que pode permanecer ativo sem Drupal LMS contrib.
- O domínio não exige lms:lms, classes Drupal\lms, rotas contrib ou configuração contrib.
- Provider legado está separado e é o único ponto de acesso aos recursos contrib cobertos.
- Studio e outros consumidores não importam a classe concreta do provider.
- Não há dependência de código reversa entre provider/domínio e Studio.
- Testes de contrato funcionam com provider falso, sem inicializar Drupal LMS.
- Provider legado mantém autorização, cache, progresso e destinos compatíveis com a fonte atual.
- Inventário lista todas as dependências restantes e evidências; nenhuma contagem sem método reproduzível.
- Nenhuma migração ou exclusão de dados é executada como parte desta fase.

A remoção de drupal/lms do Composer é um gate posterior, após substituição de módulos, serviços, configuração, Views, rotas, tema, scripts e dados que precisem ser mantidos.

## Microfases

| Microfase | Entrega | Critério de saída |
| --- | --- | --- |
| I-1 Fronteira | Mapa de dependências e módulos proprietários; direção aprovada | Contrato fora do Portal/Studio e sem dependência LMS |
| I-2 Domínio | Contratos e DTOs mínimos com consumidores reais | Testáveis sem provider contrib; sem tipos contrib |
| I-3 Provider legado | Adaptador isolado para dados ainda mantidos no LMS/Group | Somente adaptador conhece API legada; acesso/cache equivalentes |
| I-4 Consumidores | Studio e demais módulos usam API ACULTA | Nenhum import direto fora do adaptador nas capacidades cobertas |
| I-5 Substituibilidade | Provider falso e prova de casos de uso | Casos cobertos passam sem Drupal LMS inicializado |
| I-6 Retirada planejada | Matriz Composer, config, dados e módulos opcionais | Plano explícito de migração ou descarte; sem remoção prematura |

Esta decisão detalha a sequência do [inventário de dependência](LMS-CONTRIB-DEPENDENCY-INVENTORY.md) e do [roadmap ACULTA LMS](ACULTA-LMS-ROADMAP.md). Não marca fases anteriores como concluídas. O contrato de jornada atualmente em aculta_portal é transitório e deve ser movido para a fronteira independente antes de ampliar a autoria.

## Não objetivos

- Copiar código, entidades, templates, Angular bundle, endpoints ou tabelas Opigno.
- Adicionar dependência Composer Opigno.
- Duplicar Group, memberships, atividades, respostas, tentativas, notas, progresso ou permissões.
- Criar entidade Module ou campo de ordenação sem provar a necessidade e o ownership.
- Exigir preservação de todo curso legado quando descarte seletivo for suficiente e autorizado.
- Declarar independência enquanto módulos ou configuração ativos ainda exigirem Drupal LMS.

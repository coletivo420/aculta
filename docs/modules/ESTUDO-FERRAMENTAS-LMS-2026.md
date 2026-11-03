# Estudo: ferramentas mais recentes para o ACULTA LMS (2026-11-02)

Status: estudo para decisão do responsável. Nada foi implementado nem instalado.
Escopo: padrões de conteúdo (SCORM, xAPI, cmi5), armazenamento de registros (LRS) e o que existe para Drupal.
Método: pesquisa na web (dados de 2026-11-02). Itens marcados como **não verificado** não foram confirmados em fonte primária.

## 1. Padrões de conteúdo

| Padrão | Versão atual | Situação para o ACULTA |
| --- | --- | --- |
| SCORM 1.2 | 1.2 (2001) | Removido em `aculta_lms` 0.1.0-dev.112 (D-66). Era o mais comum em conteúdo legado. |
| SCORM 2004 | 4th Edition (2009) | Removido em `aculta_lms` 0.1.0-dev.112 (D-66). Era a última versão de SCORM. |
| xAPI (Tin Can) | 1.0.3, e 2.0 publicado como IEEE 9274.1.1-2023 | Não implementado. Modelo de "declarações" enviadas a um LRS, em vez de API de navegador. Exige armazenamento de registros. |
| cmi5 | Baseado em xAPI 1.0.x. Versão "Quartz" anunciada segundo a [LearningGuild](https://www.learningguild.com/articles/adl-cmi5-committee-announces-release-of-quartz-version-of-cmi5-specification); número atual **não verificado** na ADL | Não implementado. Mantém a estrutura de lançamento e conclusão do SCORM sobre xAPI. |

Fontes: [Evolution of SCORM (Rustici)](https://scorm.com/scorm-explained/business-of-scorm/scorm-versions/), [ADL cmi5](https://adlnet.gov/projects/cmi5-specification), [xAPI 2.0 e fornecedores (xapi.com)](https://xapi.com/blog/what-vendors-can-do-to-prepare-for-xapi-2-0), [ADL: anticipating xAPI 2.0](https://adlnet.gov/news/2020/08/06/Anticipating-the-xAPI-Version-2.0-Standard).

Pontos que importam:
- **xAPI 2.0 já é padrão IEEE (2023).** Vendors devem suportar 1.0.x e 2.0 por um tempo. Se formos implementar xAPI, convém começar pela 2.0.
- **cmi5 ainda usa xAPI 1.0.x** segundo a página do xapi.com. Confirmar a versão atual do cmi5 antes de qualquer decisão, porque a página da ADL não traz número de versão.
- **Não há SCORM mais novo que 2004 4th.** Qualquer conteúdo novo que um parceiro entregue em xAPI ou cmi5 é uma integração separada.

## 2. Armazenamento de registros (LRS)

Um LRS recebe e consulta as declarações xAPI. Hoje o ACULTA não tem um. Opções pesquisadas:

| Opção | Licença | Situação observada | Observação |
| --- | --- | --- | --- |
| **Ralph** (ralph-malph, Open FUN) | MIT | Versão 5.1.0, publicada em 2026-10-13 (PyPI). Requer Python 3.9+. | Servidor HTTP de LRS, com backends (Elasticsearch, MongoDB, ClickHouse, S3, entre outros). Roda como serviço separado, em Python. |
| **Learning Locker** | GPL-3.0 | Repositório com ~3.254 commits. Não consegui confirmar a última versão nem a data do último commit pela página. | Opção mais conhecida. Não verificado se está ativa nesta data. |

Fontes: [Ralph no PyPI](https://pypi.org/project/ralph-malph/), [Learning Locker (GitHub)](https://github.com/LearningLocker/learninglocker).

Recomendação preliminar: se o xAPI entrar no roadmap, avaliar o Ralph primeiro, por ser MIT, recente e separado do Drupal. Antes de escolher, conferir a manutenção do Learning Locker e os requisitos de hospedagem (o ACULTA roda em MariaDB e Apache, e o Ralph exige um backend como Elasticsearch ou MongoDB).

## 3. Módulos Drupal

- **simple_xapi** (drupal.org): aparece na pesquisa como integração xAPI para Drupal. **Não verificado:** compatibilidade com Drupal 11, data da última versão e manutenção. A página não carregou.
- **opigno_tincan_api** (drupal.org): é o módulo de xAPI do Opigno 3.x, ligado ao Opigno. Pelo plano de independência, não entra como dependência. Serve só como referência de conceito.
- **tincanapi** (drupal.org): também apareceu na pesquisa. **Não verificado.**

Regra do projeto: nenhum módulo contrib novo entra sem análise de dependência e revisão de licença. Qualquer um destes precisa passar pelo adaptador, como já está decidido para o `lms` contrib.

## 4. Conclusão e próximos passos

1. **SCORM 1.2 e 2004: removidos** em dev.112, por decisão D-66. A troca por xAPI 2.0 está em `PLANO-XAPI-2-SUBSTITUI-SCORM.md`.
2. **xAPI 2.0: adotado pela D-66**, com escopo puro (sem cmi5). A implementação espera o ADR previsto no plano. A regra de demanda real não vale mais para xAPI.
3. **Se houver demanda:** comparar Ralph e Learning Locker com teste de instalação no servidor de desenvolvimento, medindo manutenção, requisitos e custo de hospedagem. Nenhum LRS entra em produção sem essa comparação.
4. **Antes de qualquer escolha:** confirmar a versão atual do cmi5 na fonte da ADL, a situação do Learning Locker, e a compatibilidade dos módulos Drupal com Drupal 11. Esses três pontos não foram verificados nesta pesquisa.

## 5. Limites deste estudo

- A busca mostrou o estado de cada projeto na data da pesquisa. Versões mudam; reconferir antes de decidir.
- Os módulos Drupal não foram verificados em fonte primária.
- Não há opinião sobre desempenho ou custo: nenhum LRS foi instalado.

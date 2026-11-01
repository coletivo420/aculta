# ACULTA Eventos — documentação

O submódulo contém entidades, catálogo, calendário integrado e mapas. No HEAD auditado da PR #299, o código inclui CRUD/diretório de Locais, seleção/cadastro por popup Drupal/AJAX, providers `aculta_index` e a base do ACULTA Studio Culturas. A implementação está parcial e aguardando execução dos gates e homologação Runtime/visual.

| Arquivo | Conteúdo |
| --- | --- |
| [Plano LOC - Events e Index](../../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md) | Cadastro/seleção inline, gestão, página pública, agenda, diretório, Studio e descoberta via aculta_index; fases e gates |
| [PLANO-IMPLANTACAO.md](PLANO-IMPLANTACAO.md) | Escopo, decisões a confirmar (D1–D11), integração com o Portal, dependências, infraestrutura, riscos e fases |
| [ROADMAP.md](ROADMAP.md) | Roadmap de Events com dependências dos roadmaps próprios de Calendar, Maps, Studio e Index |
| [Análise comparativa OpenCulturas](../../../../../../../docs/architecture/ANALISE-COMPARATIVA-OPENCULTURAS-ACULTA-STUDIO-CULTURAS.md) | Inventário do código upstream, comparação com o HEAD da PR e plano de incorporação |
| [MODELO-DE-DADOS.md](MODELO-DE-DADOS.md) | Evento, data e local; campos, origem no OpenCulturas e regras de privacidade |
| [VISUAL.md](VISUAL.md) | Esquema visual, tokens do ACULTA420, componentes SDC e critérios de acessibilidade |
| [AUDITORIA-OPENCULTURAS.md](AUDITORIA-OPENCULTURAS.md) | Auditoria arquivo a arquivo do que seria copiado: achados de segurança, técnicos e visuais, decisão por arquivo e dependências |
| [ORIGEM-OPENCULTURAS.md](ORIGEM-OPENCULTURAS.md) | Procedência do pacote (SHA-256), licença GPL-2.0-or-later e registro de cópias |
| [upstream-openculturas/](upstream-openculturas/) | Cópia da documentação de origem, sem alteração |

Referência de código (somente leitura): `/home/piradopirata/projetos/aculta-openculturas/`.
Guia para agentes: `LEIA-PRIMEIRO.md` nessa pasta.

Submódulo irmão: `../../aculta_calendar/docs/PLANO-IMPLANTACAO.md`.

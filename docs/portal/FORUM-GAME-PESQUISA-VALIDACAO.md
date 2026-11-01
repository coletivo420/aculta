# Fórum e Game — evidência da abertura documental

Data: 2026-10-15. Branch: `chore/messenger-pendencias`. PR #286.
Documentação conferida no commit `a3cb712`; inventário inicial baseado em `868626d`.

## Entregas

- [ACULTA Fórum/Growroom](../../web/modules/custom/aculta_portal/modules/aculta_forum/README.md):
  decisões, arquitetura, pesquisa, reconstrução via Wayback e roadmap F0–F8.
- [ACULTA Game](../../web/modules/custom/aculta_portal/modules/aculta_game/README.md):
  decisões, arquitetura, pesquisa, licenças, manifesto de pacotes e roadmap G0–G7.

Conferidas as decisões mais recentes: autoria histórica com nomes individuais e
memorial coletivo único em `/u/lendashistoricas`; conquistas públicas por padrão
com ocultação, seleção de destaques e ordenação. Essas decisões orientam a futura
implementação; não descrevem funcionalidades aplicadas nesta entrega.

## Verificações executadas

| Verificação estática | Resultado |
| --- | --- |
| `php scripts/validate-aculta-portal-drupal11.php` | PASS — 772 verificações |
| `php scripts/validate-module-theme-boundary.php` | PASS — 1034 verificações; nenhuma classe de design no módulo |
| `php scripts/validate-environment-addresses.php` | PASS — 1721 arquivos |
| `git diff --check` | PASS |
| Destinos de links Markdown relativos dos dois projetos | PASS — 14 documentos |
| Manifesto `FONTES-UPSTREAM.json` | JSON válido; bytes e SHA-256 dos quatro pacotes conferidos contra os arquivos baixados |

Pacotes inspecionados: Achievements 3.0.3, ECA 3.1.9, Rules 4.0.1 e Gamification
2.0.2. Licenças declaradas, dependências e divergências entre páginas de projeto
e releases estão registradas nos documentos do Game. Nenhum código foi incorporado.

## Alcance da evidência

Entrega exclusivamente documental. Não foram instaladas dependências, criadas
extensões instaláveis, migrados conteúdos ou alterados domínio, contas, avatar
e configuração do Runtime. Os gates estáticos não comprovam compatibilidade
funcional dos candidatos, recuperação integral do Wayback ou comportamento de
privacidade em execução. Essas verificações pertencem às fases dos roadmaps.

Próximos passos documentados: F1, inventário do acervo e preparação da mudança de
domínio; G1, auditoria e definição da transição da base atual de conquistas.

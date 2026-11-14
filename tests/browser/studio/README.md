# Testes do ACULTA Studio, por subcategoria

Todos os testes do Studio ficam aqui, separados pela suite a que pertencem (ADR-038, suites por domínio). Cada subpasta tem o seu README. Antes de criar um teste novo, procure aqui primeiro e rode o script que já existe.

| Subcategoria | Suite | Purpose | Conteúdo |
|---|---|---|---|
| `marley/` | Marley (`/marley`) | `account` | `visual.mjs` |
| `we-sativa/` | WE SATIVA (`/wesativa`) | `wiki` | `we-sativa.py` |
| `ganjalab/` | Ganjalab (`/ganjalab`) | `courses` | `learning-experience.mjs` |
| `green-stash/` | Green Stash (`/greenstash`) | `shop` | `green-stash-layout.py` |
| `culturas/` | Culturas (`/culturas`) | `eventos` | dashboard, runtime, zoom de formulários, matriz e gate rápido |
| `geral/` | Studio como um todo (shell, fidelidade, acessibilidade, perfil, conta) | vários | demais testes do Studio e `run-visual.sh` |

Os caminhos e a regra de reuso estão em `docs/operations/NAVEGADOR-TESTES.md`. Artefatos vão para `$SUITE_OUT`, fora do repositório.

# Changelog — ACULTA Game

## 0.1.0-dev.3 — G3: conta, perfil e escolhas de exibição — 2026-10-15

- Lista própria e formulário na aba Conquistas; entrada pelos ajustes de privacidade.
- Projeção pública única, ocultação global/individual, destaques e ordenação.
- Preferências no User Data do User, preservadas após desinstalar Game.
- Perfil privado, conta bloqueada e legado não publicam conquistas; sem evidência
  de curso/pedido na projeção. Render API e tags de invalidação preservados.
- Helper registrado no Status; nenhum novo componente, CSS ou rota.
- Renderizações no Homelab verificadas; interação e matriz funcional pendentes,
  conforme [EXECUCAO.md](docs/EXECUCAO.md).

## 0.1.0-dev.2 — G2: integridade dos eventos — 2026-10-15

- Aprovação posterior de comentário, publicação de revisão da Wiki e transições
  de pedido/LMS verificadas; LMS usa `getUserId()` da API instalada.
- Migrate/sync, contas bloqueadas e papel legado excluídos de novas concessões.
- Concessão/revogação pela API serializadas com lock até o commit externo.
- Sem alteração de critérios pessoais ou de reconhecimento de apoio existentes.
- Evidências e limites de validação em [EXECUCAO.md](docs/EXECUCAO.md).

## 0.1.0-dev.1 — G1: integração e fonte única — 2026-10-15

- Extensão opcional do Portal, dependente de User, sem dependência inversa.
- Avaliador automático transferido do User; catálogo, serviço, entidade e dados
  mantidos na fonte existente, sem migração ou storage paralelo.
- Inventário e evidências em [EXECUCAO.md](docs/EXECUCAO.md).

## 2026-10-15 — abertura documental e pesquisa

- Registradas conquistas e missões sem ranking, XP/níveis ou benefícios financeiros.
- Conquistas públicas por padrão, com privacidade, seleção de destaques e ordenação.
- Inventariada a base de badges do `aculta_user`, com transição sem duplicar concessões.
- Pesquisados módulos, código de releases, licenças, arquitetura e roadmap.
- Quatro pacotes inspecionados com checksums; nenhum código copiado ou módulo instalado.
- Entrega exclusivamente documental; sem versão executável nova ou alteração de Runtime.
- [Evidência de revisão e validação estática](../../../../../../docs/portal/FORUM-GAME-PESQUISA-VALIDACAO.md).

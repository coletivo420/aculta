# Changelog — ACULTA Links

## 0.1.0-dev.1 — fundação e auditoria inicial — 2026-10-20

- Inventariada a implementação atual do purpose `links`, seus consumidores,
  configuração de conteúdo e apresentação no ACULTA420.
- Criado o submódulo documental, sem habilitá-lo e sem alterar a implementação
  atualmente servida.
- Registradas arquiteturas propostas, inventário Linky/LinkStack, matriz de
  portabilidade, integrações, segurança e pendências de licença.
- LinkStack estudado na tag `v4.8.0` (`ae6837e8c0cc2cd0e77f26952ceca97d0eebec35`);
  Linky estudado na tag `2.0.0-beta1`
  (`2d70712a854065428faf0228aef96066bd6812f2`). Nenhum código foi copiado.
- Validação desta fundação: manifesto YAML parseado pelo Symfony YAML e links
  locais Markdown verificados em 2026-10-20; diff-check PASS. Runtime:
  DEFERRED; módulo não instalado nem habilitado.
- O gate do índice revelou que sua comparação histórica tratava a primeira linha
  de um componente novo como inalterada. A condição foi corrigida no validador
  para reconhecer a inclusão da linha no mesmo commit; o gate será repetido após
  esta correção.

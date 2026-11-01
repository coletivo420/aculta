# Política de changelog e de histórico

Regra vigente desde 2026-10-28.

## 1. O que é uma entrada

Uma entrada é um título de nível 2 (`## `) de versão ou de data em um `CHANGELOG.md`. Bullets dentro da
entrada não contam como entrada.

## 2. Limite

- Cada `CHANGELOG.md` do ACULTA mantém **no máximo 30 entradas**, sempre as mais recentes, na ordem do arquivo
  (mais nova primeiro).
- Entradas mais antigas saem do `CHANGELOG.md` e vão **integralmente**, sem resumo nem reescrita, para
  `docs/history/changelog/<modulo>.md`, na mesma ordem.
- Bibliotecas de terceiros (`web/libraries/`) não entram nesta regra: têm changelog próprio, do autor.

## 3. Onde ficam as decisões que estavam no histórico

Decisão que ainda vale **não pode ficar só no histórico**. Ela vai para um destes lugares, conforme o alcance:

- **ADR** em `docs/decisions/ADR-NNN-titulo.md`, quando a decisão afeta mais de um módulo ou muda uma regra do
  projeto. Número sequencial, o próximo livre na pasta.
- **Arquitetura do módulo** em `docs/architecture/<modulo>.md`, quando a decisão é local ao módulo. Seção
  "Decisões vigentes", com uma linha por decisão apontando para a entrada de origem.

Decisão superada não é copiada. Fica só no histórico, com a entrada que a substituiu.

Cada decisão registrada traz: título, data, versão de origem, motivo em uma frase, situação (`vigente` ou
`superada por <ref>`) e a referência da entrada de origem (versão e arquivo em `docs/history/changelog/`).

## 4. Arquivo de histórico

- Um arquivo por módulo: `docs/history/changelog/<modulo>.md`.
- Cabeçalho do arquivo: "Histórico arquivado do CHANGELOG.md de <modulo>. Não é fonte de decisão vigente: ver
  `docs/decisions/` e `docs/architecture/<modulo>.md`."
- Não edite entradas arquivadas. Correção de fato vai para uma nova entrada no `CHANGELOG.md` atual.

## 5. Para quem trabalha com agentes

- Ao procurar uma regra, comece por `docs/decisions/` e `docs/architecture/`. Só consulte o histórico para
  entender a origem de uma decisão, indicada na referência.
- Não abra `docs/history/` por padrão: é grande e pode estar desatualizado.
- Ao registrar uma mudança nova, escreva uma entrada no `CHANGELOG.md` do módulo, no topo. Se a mudança
  altera uma decisão, registre também a decisão nova no ADR ou na arquitetura.

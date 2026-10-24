## 0.1.0-dev.8 — STUDIO-P1-A: entrada pessoal inicial — 2026-10-24

- Provider `PersonalExperienceProvider` (`pessoal`) registrado no
  `StudioExperienceManager`, com purpose `account`, autenticação e
  permissão `access aculta portal` revalidadas pelo controller.
- Nova rota explícita `/studio/pessoal` oferece entrada mínima e links
  para os formulários **existentes** da Conta. `/studio` continua sendo a
  entrada de compatibilidade do Marley; `/` da Conta e `/u/{apelido}`
  permanecem inalterados. Nenhum dado, formulário ou shell foi migrado.
- Testes Unit adicionados para provider, controller e proteção/precedência
  da rota; execução PHPUnit/Kernel/Runtime e teste visual ainda pendentes
  neste incremento via GitHub. Inspeção de código e metadados da branch
  não substituem esses gates.
- Próxima P1-B: registro/ponte de Marley no catálogo de experiências e
  preparação do hub, após confirmar acesso/cache e executar os testes;
  não alterar a rota `/studio` sem inventário e testes.

## 0.1.0-dev.6 — início da WE SATIVA

- Rota `/studio/we-sativa/inicio` no purpose `account`, com provider de página
  inicial opcional para que o owner forneça o conteúdo e autorização.
- Integração delega dados, acesso e cache a `aculta_wiki`; Kernel focal integrado
  passou em 2 testes / 208 asserções, incluindo render no ACULTA420. Unit do controller cobre delegação,
  provedor sem contrato de início e acesso negado (3/3; suíte Studio 20/61).
  Runtime e validação visual pendentes.
## 0.1.0-dev.7 — identidades Marley e Ganjalab

- A experiência editorial passa a ACULTA Studio Marley (`marley`) e a
  experiência de aprendizagem ACULTA Studio Ganjalab (`ganjalab`); o módulo
  compartilhado `aculta_studio` e a infraestrutura LMS permanecem.
- Rotas canônicas: `/studio/marley` com seus níveis e `/studio/ganjalab` para
  estudantes. Entradas anteriores não colidentes recebem 302 somente em
  GET/HEAD; `/studio/ganjalab` pertence exclusivamente à aprendizagem.
- Contratos, controllers, services e descoberta de plugins específicos de
  Marley recebem nomenclatura própria; IDs funcionais das áreas não mudam.
- Unit Studio passou em 22 testes/83 asserções e Unit LMS em 1/7. O Runtime de
  teste passou a expor `/studio/marley` e `/studio/ganjalab` após rebuild de
  cache. Firefox ESR/X11 passou em 29/29 verificações aplicáveis; a checagem de
  link de edição foi ignorada porque não há publicação própria de teste.


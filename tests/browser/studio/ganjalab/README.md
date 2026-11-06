# Experiência educacional ACULTA Studio Ganjalab

Revisão visual e funcional das três superfícies da experiência de aprendizagem
(Início, Minha jornada e Sala de aula) no Firefox ESR visível na sessão X11
local (`DISPLAY=:0`), pelo geckodriver. Não usar headless para a validação.

## Onde roda

Somente no servidor de desenvolvimento (`https://cursos.aculta.toca.net.br`,
runtime `aculta_runtime`). A conta de teste precisa estar matriculada no curso
usado na revisão. O script não cria matrícula, curso, aula ou permissão.

## Pré-requisitos

- Firefox ESR e `geckodriver` 0.37.1 ou superior:
  `DISPLAY=:0 geckodriver --port 4444`.
- Node 22 ou superior (sem dependências npm).
- Drush do projeto (`vendor/drush/drush/drush.php`) para o link de login único.
- Sessão de testes com `teste.comum` apenas para o estado vazio.

## Execução

```sh
GANJA_UID=1 node tests/browser/studio/ganjalab/learning-experience.mjs
```

| Variável | Padrão | Uso |
| --- | --- | --- |
| `GANJA_HOME` | `https://cursos.aculta.toca.net.br/ganjalab` | Início |
| `GANJA_COURSE` | `https://cursos.aculta.toca.net.br/group/1` | Minha jornada |
| `GANJA_LOGIN_URI` | `https://conta.aculta.toca.net.br` | Host do link único |
| `GANJA_UID` | `1` | Conta com matrícula e aulas |
| `GANJA_EMPTY_USER` | `teste.comum` | Conta sem matrícula para o estado vazio |
| `GANJA_OUT` / `SUITE_OUT` | `~/worktrees/aculta-test-output/ganjalab` | Capturas e `results.json`, fora do repositório |
| `GANJA_WIDTHS` | `320,390,768,1024,1280,1440` | Larguras da matriz, em px; útil para um smoke curto |
| `GANJA_HEADLESS` | (janela) | Não usar para validação |
| `GECKODRIVER` | `http://127.0.0.1:4444` | Endereço do geckodriver |

O script termina com código 1 se alguma verificação falhar.

## Cobertura

- H1 única e identidade contextual nas três superfícies.
- Início com e sem matrícula; cartão de retomada apenas quando o LMS fornece
  aula atual e destino canônico.
- Jornada com progresso e aulas em ordem, estados textuais e links canônicos.
- Sala com contexto do curso/aula, índice recolhível e formulário nativo do LMS.
- Larguras 320, 390, 768, 1024, 1280 e 1440 px, sem rolagem horizontal.
- Modo escuro pelo atributo `data-bs-theme="dark"` resolvido pelo Portal, além
  de foco visível em link.

## Limites conhecidos

- A janela do Firefox não reduz abaixo de 500 px; o celular é emulado pelo
  comando BiDi `browsingContext.setViewport` e o script confere
  `window.innerWidth` antes da captura.
- O link único do Drush cria a sessão mesmo quando o redirecionamento final do
  Core cai em 404 no host da conta; o script confirma a sessão pela página.
- Não há conta de teste não administradora matriculada em curso com aulas no
  runtime atual; a matriz A/B de estudantes e Group permanece parcialmente
  coberta por testes Unit e por esta revisão de estados vazio e preenchido.
- Falhas de mídia, H5P, PDF e revogação de matrícula não são simuladas aqui.

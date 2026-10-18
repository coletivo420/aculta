# Matriz de compatibilidade LMS, PDF e avaliação

Revisão técnica: 2026-10-18. Versões externas devem ser rechecadas no changelog e Composer antes de atualizar/instalar.

| Projeto/API | Metadata/versão encontrada | Drupal/PHP observado | Estado e decisão |
| --- | --- | --- | --- |
| `drupal/lms` | composer lock 1.2.4; `lms.info.yml` 1.2.4; release estável com cobertura Drupal Security Team | Core `^10.3 || ^11`; o CLI Drush deste checkout reporta PHP 8.4.26, então PHP 8.5 não foi comprovado | Instalado; audit sem advisories, dry-run isolado alterou somente LMS 1.2.3 → 1.2.4. Update 12002 aplicado no Runtime de teste; `lms.settings` validado pelo schema, 10 Kernel tests upstream (990 assertions) e 3 testes `aculta_lms` (13 assertions) PASS. A release corrige FreeText, back navigation e course storage, adiciona `activity_page_title` e contém correções de depreciação Drupal 12. FunctionalJavascript exige `SIMPLETEST_BASE_URL` e ficou DEFERRED. PHP 8.5 continua DEFERRED (DT-P04) |
| `lms_answer_plugins` | submódulo no diretório `lms/modules`, info 1.2.4 | acompanha LMS `^10.3 || ^11` | Não é pacote Composer independente; parte do repo LMS |
| `lms_classes` | submódulo no diretório `lms/modules`, info 1.2.4 | acompanha LMS `^10.3 || ^11` | Não é pacote Composer independente; auditar necessidade/uso antes de ativação |
| `drupal/lms_file_upload` | release 1.0.2 | projeto declara `^10 || ^11`; upload privado; issue de file usage #3578576 marcada fixed em 2026-03-26 | Composer instalado e módulo habilitado no Runtime de teste durante F9. Rota de download preserva `lms_answer.see_details` e soma verificação `take course` atual para o autor. Kernel transmite arquivo real, nega após revogação Group e preserva acesso de educador. Smoke HTTP/browser no Runtime `DEFERRED` por ausência de conteúdo de teste |
| `drupal/webform` | lock 6.3.1 | lock instalado; Drupal 11 atual | Usado no site. Não converter submissions atuais em gradebook automaticamente |
| `drupal/webform_score` | release 2.4.0 | release declara compatibilidade Drupal 11; depende de Webform e Fraction | Composer instalado e módulo habilitado no Runtime de teste durante F9. Scores permanecem dados Webform; não há adapter que os grave como nota oficial LMS. Validar submissão/autoria/permissões antes de uso em curso |
| `drupal/h5p` | lock da branch `2.0.0-beta1`; `h5p-core` 1.28.0; `h5p-editor` 1.25; patch Composer local #3548290 em `h5p/h5p-editor` | projeto anuncia `^10.2 || ^11`; PHP 8.5 não foi confirmado por release estável | **F10 em auditoria; pacote Composer e patch local aplicados no checkout, módulos não habilitados.** A página não lista release estável atualmente coberta pela política de advisories. O patch confere bibliotecas/dependências contra `CREATE_RESTRICTED`; falta teste funcional com pacote e contas com/sem permissão. Auditar também editor, bibliotecas executáveis, uploads, storage, CSP/isolamento e access antes de habilitar. Não aceitar notas externas como nota LMS sem API confiável |
| `drupal/entity_print` | release 2.19.0 | linha estável com Core 11 no metadata upstream | Não instalado; comparar como gerador de PDF, não como autoridade de elegibilidade/certificado |
| `drupal/lms_certificate` | release alpha3; sem release estável | Drupal `^10.3 || ^11` | Referência para F11, não instalada; auditar API, manutenção e compatibilidade antes de escolher emissão |
| `drupal/lms_certificate_entity` | Página do projeto informa dependência de LMS Certificate e Entity Print; release/versionamento não confirmado | metadata de release não confirmada | Não adotar sem release/API compatível comprovada e decisão explícita; projeto não coberto pela política de security advisories do Drupal |
| `drupal/pdf` | 1.3.0 instalado via Composer | Drupal `^10 || ^11`; depende Core File | Código/lock presente, formatter contrib não usado. Source/tag carrega `/libraries/pdf.js/build/pdf.js`; `PdfDefault` usa viewer legado e query `file`, incompatível com o pacote ESM. |
| `mozilla/pdf.js` | v6.4.299, release oficial estável em 2026-10-16 | Distribuição prebuilt oficial, pacote Composer ACULTA `mozilla/pdfjs-dist`, pin exato, SHA-1 do ZIP fixado no `composer.json` | `pdf.mjs`, `pdf.worker.mjs` e viewer oficial instalados em `web/libraries/pdf.js`; Apache-2.0. `aculta_pdf_viewer` importa ESM localmente. Sem CDN e sem alteração de contrib. |
| `drupal/pdfpreview` | release 1.2.0 / 8.x-1.2 | Drupal `^10 || ^11`; depende do provider ImageMagick | Não resolver com ImageMagick 5 (`^3.7 || ^4.0` constraint). Generator usa ImageToolkit, `setSourceFormat('PDF')`, página `[0]`, resize/quality. Processo externo é responsabilidade do toolkit; revisar execução/timeout no source ImageMagick. Derivado é salvo no scheme `system.file:default_scheme`, podendo ser `public://` mesmo para source privada: risco de disclosure; bloquear previews de PDFs privados até garantir mesmo access/scheme. |
| `drupal/imagemagick` | release 5.0.1 estável upstream | release exige Drupal `^11.3`, declara suporte PHP 8.5 | Não trocar toolkit global. Constraint do PDFPreview 1.2 bloqueia a combinação estável proposta; issue de compatibilidade 5.x deve ser confirmada antes da instalação |

## Regras de resolução

- PDFPreview 1.2 requer ImageMagick `^3.7 || ^4.0`; a restrição declarada conflita com ImageMagick 5.0. Não contornar com alias ou branch dev sem revisão de release, source e testes.
- Simulações Composer devem ser isoladas por pacote e revisar upgrades transitivos no lock. Não aceitar upgrades não relacionados sem motivo.
- Resolver Composer não prova PHP 8.5, funcionamento de runtime, compatibilidade de biblioteca JS nem segurança de H5P.

## Gates por API e versão

1. Capturar o `composer.lock` e HEAD exatos de `main` atual antes de resolver pacotes; não aceitar updates transitivos não relacionados sem análise.
2. Ler metadata/README/code/changelog de cada tag exata e issues abertos, incluindo issue queue de H5P e PDFPreview 5.x.
3. Compatibilidade PHP 8.5 deve ser demonstrada em CI/runtime 8.5, não inferida somente do Core requirement.
4. Fazer tests de install/update/config schema e regressão de atividade existente; nenhuma atualização LMS sem avaliação de `lms_course_status`, `lms_lesson_status`, `lms_answer`, plugins e fields.
5. Fazer security review do H5P antes de habilitar editor/upload. Composer audit sozinho não valida isolamento de conteúdo executável.

## Fontes

- [LMS releases](https://www.drupal.org/project/lms/releases), [LMS plugin guide](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/drupal-lms/plugin-developers-guide-for-drupal-lms/introduction)
- [LMS File Upload](https://www.drupal.org/project/lms_file_upload)
- [Webform Score releases](https://www.drupal.org/project/webform_score/releases)
- [H5P releases](https://www.drupal.org/project/h5p)
- [H5P issue #3548290: restrição de upload de bibliotecas](https://www.drupal.org/project/h5p/issues/3548290)
- [Auditoria inicial H5P da F10](LMS-H5P-AUDIT.md)
- [Entity Print releases](https://www.drupal.org/project/entity_print/releases)
- [LMS Certificate](https://www.drupal.org/project/lms_certificate) e [LMS Certificate Entity](https://www.drupal.org/project/lms_certificate_entity)
- [LMS Messages](https://www.drupal.org/project/lms_messages): alpha5, sem cobertura Drupal Security Advisory e com limitação documentada com Group Membership; referência apenas, sem instalação/dependência
- [LMS Certificate alpha3](https://www.drupal.org/project/lms_certificate/releases/1.0.0-alpha3) e [LMS Certificate Entity](https://www.drupal.org/project/lms_certificate_entity): avaliar suporte/security policy antes da fase de certificados
- [Opigno LMS Community](https://www.drupal.org/project/opigno_lms) e [repositório](https://github.com/Craftmasters/opigno): referências visuais/funcionais; licença do código exato deve ser confirmada antes de reutilização
- [PDF 1.3](https://www.drupal.org/project/pdf/releases)
- [PDF issue `.mjs`](https://www.drupal.org/project/pdf/issues/3417349)
- [PDFPreview 1.2](https://www.drupal.org/project/pdfpreview/releases)
- [PDFPreview ImageMagick 5.x issue queue](https://www.drupal.org/project/issues/pdfpreview)
- [ImageMagick releases](https://www.drupal.org/project/imagemagick/releases)
- [Mozilla PDF.js 6.4.299](https://github.com/mozilla/pdf.js/releases/tag/v6.4.299) (release Latest consultada em 2026-10-16)

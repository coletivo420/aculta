import postcss from 'postcss';

/** Valida completude, consistência e frescor dos artefatos de auditoria. */
export function validateAuditArtifacts({
  manifest,
  report,
  currentCss,
  currentHash,
  expectedDynamicClasses,
  expectedContentGlobs,
  expectedSafelist,
  expectedToolVersions,
  stagedCssByName,
}) {
  const problems = [];
  const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
  const nonNegativeInteger = (value) => Number.isInteger(value) && value >= 0;

  if (!isObject(manifest) || !isObject(report)) {
    return ['manifesto ou relatório não é um objeto JSON.'];
  }

  if (manifest.sourceHash !== currentHash || report.sourceHash !== currentHash) {
    problems.push('sourceHash diverge do CSS atual (relatório desatualizado).');
  }
  if (report.mode !== 'audit') {
    problems.push(`modo do relatório inválido: ${report.mode ?? 'ausente'}.`);
  }
  if (!Number.isFinite(Date.parse(report.generatedAt)) || manifest.generatedAt !== report.generatedAt) {
    problems.push('timestamp do relatório ausente ou divergente no manifesto.');
  }
  if (!isObject(report.toolVersions) || !report.toolVersions.purgecss || !report.toolVersions.postcss
      || JSON.stringify(manifest.toolVersions) !== JSON.stringify(report.toolVersions)
      || JSON.stringify(report.toolVersions) !== JSON.stringify(expectedToolVersions)) {
    problems.push('versões de ferramentas ausentes ou divergentes no manifesto.');
  }
  if (!Array.isArray(report.contentGlobs)
      || JSON.stringify(report.contentGlobs) !== JSON.stringify(expectedContentGlobs)) {
    problems.push('fontes de conteúdo do PurgeCSS ausentes ou desatualizadas.');
  }
  if (!isObject(report.safelist) || JSON.stringify(report.safelist) !== JSON.stringify(expectedSafelist)) {
    problems.push('safelist do relatório ausente ou desatualizada.');
  }
  const dynamicClasses = Array.isArray(report.dynamicClasses) ? [...report.dynamicClasses].sort() : null;
  if (!dynamicClasses
      || JSON.stringify(dynamicClasses) !== JSON.stringify([...expectedDynamicClasses].sort())) {
    problems.push('classes dinâmicas do relatório divergem das fontes atuais.');
  }

  const expectedFiles = currentCss.map((item) => item.name).sort();
  const checkFileList = (actual, label) => {
    if (!Array.isArray(actual)) {
      problems.push(`${label} não contém uma lista de arquivos.`);
      return;
    }
    const sorted = [...actual].sort();
    if (new Set(actual).size !== actual.length || JSON.stringify(sorted) !== JSON.stringify(expectedFiles)) {
      problems.push(`${label} não corresponde às ${expectedFiles.length} folhas CSS atuais.`);
    }
  };
  checkFileList(manifest.files, 'Manifesto');

  if (!Array.isArray(report.files)) {
    problems.push('Relatório não contém detalhes por arquivo.');
    return problems;
  }
  checkFileList(report.files.map((item) => item?.file), 'Relatório');

  const totals = report.totals;
  const totalKeys = [
    'files', 'originalBytes', 'retainedBytes', 'removedBytes',
    'originalSelectors', 'retainedSelectors', 'removedSelectors', 'addedSelectors',
    'purgecssRejectedSelectors',
  ];
  if (!isObject(totals) || totalKeys.some((key) => !nonNegativeInteger(totals[key]))) {
    problems.push('totais ausentes, incompletos ou não inteiros.');
    return problems;
  }
  if (totals.files !== expectedFiles.length || report.files.length !== expectedFiles.length) {
    problems.push('contagem de arquivos do relatório diverge do CSS atual.');
  }
  if (JSON.stringify(manifest.totals) !== JSON.stringify(totals)) {
    problems.push('totais do manifesto divergem do relatório.');
  }

  const sourceByName = new Map(currentCss.map((item) => [item.name, item]));
  if (!(stagedCssByName instanceof Map)
      || JSON.stringify([...stagedCssByName.keys()].sort()) !== JSON.stringify(expectedFiles)) {
    problems.push('CSS podado de auditoria ausente ou incompleto.');
  }
  const aggregate = Object.fromEntries(totalKeys.slice(1).map((key) => [key, 0]));
  for (const file of report.files) {
    const source = sourceByName.get(file?.file);
    if (!source || !isObject(file)) {
      continue;
    }
    const fields = [
      'originalBytes', 'retainedBytes', 'removedBytes', 'originalSelectors',
      'retainedSelectors', 'removedSelectors', 'addedSelectors', 'purgecssRejectedSelectors',
    ];
    if (fields.some((key) => !nonNegativeInteger(file[key]))) {
      problems.push(`${file.file}: métricas ausentes ou inválidas.`);
      continue;
    }
    const byteLength = Buffer.byteLength(source.raw);
    const staged = stagedCssByName instanceof Map ? stagedCssByName.get(file.file) : undefined;
    if (file.originalBytes !== byteLength || file.removedBytes !== file.originalBytes - file.retainedBytes
        || staged === undefined || file.retainedBytes !== Buffer.byteLength(staged)) {
      problems.push(`${file.file}: métricas de bytes inconsistentes.`);
    }
    if (!Array.isArray(file.selectorsRemoved) || file.selectorsRemoved.length !== file.removedSelectors
        || new Set(file.selectorsRemoved).size !== file.selectorsRemoved.length) {
      problems.push(`${file.file}: lista de seletores removidos incompleta ou duplicada.`);
    }
    if (!Array.isArray(file.selectorsAdded) || file.selectorsAdded.length !== file.addedSelectors
        || new Set(file.selectorsAdded).size !== file.selectorsAdded.length) {
      problems.push(`${file.file}: lista de seletores reescritos incompleta ou duplicada.`);
    }
    if (!Array.isArray(file.purgecssRejected) || file.purgecssRejected.length !== file.purgecssRejectedSelectors) {
      problems.push(`${file.file}: lista de rejeições do PurgeCSS incompleta.`);
    }
    if (file.originalSelectors + file.addedSelectors - file.removedSelectors !== file.retainedSelectors) {
      problems.push(`${file.file}: identidade de seletores original + reescrito − removido inconsistente.`);
    }
    if (staged !== undefined) {
      try {
        const selectorSet = (css) => {
          const selectors = new Set();
          postcss.parse(css).walkRules((rule) => rule.selectors.forEach((selector) => selectors.add(selector.trim())));
          return selectors;
        };
        const original = selectorSet(source.raw);
        const retained = selectorSet(staged);
        const removed = [...original].filter((selector) => !retained.has(selector)).sort();
        const added = [...retained].filter((selector) => !original.has(selector)).sort();
        if (file.originalSelectors !== original.size || file.retainedSelectors !== retained.size
            || JSON.stringify(file.selectorsRemoved) !== JSON.stringify(removed)
            || JSON.stringify(file.selectorsAdded) !== JSON.stringify(added)) {
          problems.push(`${file.file}: seletores no relatório divergem do CSS podado.`);
        }
      } catch (error) {
        problems.push(`${file.file}: CSS podado inválido (${error.message}).`);
      }
    }
    for (const key of Object.keys(aggregate)) {
      aggregate[key] += file[key];
    }
  }

  if (totals.removedBytes !== totals.originalBytes - totals.retainedBytes) {
    problems.push('totais de bytes inconsistentes (removido ≠ original − retido).');
  }
  for (const [key, value] of Object.entries(aggregate)) {
    if (totals[key] !== value) {
      problems.push(`total ${key} diverge da soma dos arquivos.`);
    }
  }
  if (totals.originalSelectors + totals.addedSelectors - totals.removedSelectors !== totals.retainedSelectors) {
    problems.push('totais de seletores não reconciliam original, reescrito, removido e retido.');
  }
  return [...new Set(problems)];
}

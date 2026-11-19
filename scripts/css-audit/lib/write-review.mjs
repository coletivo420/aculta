import { createHash } from 'node:crypto';

/** Fingerprinta todas as entradas e folhas podadas que a pessoa revisa. */
export function createWriteReviewHash({ sourceHash, toolVersions, contentGlobs, dynamicClasses, safelist, classification = null, files }) {
  const review = {
    sourceHash,
    toolVersions,
    contentGlobs,
    dynamicClasses: [...dynamicClasses].sort(),
    safelist,
    classification,
    files: files.map(({ file, css }) => ({ file, css })).sort((a, b) => a.file.localeCompare(b.file)),
  };
  return createHash('sha256').update(JSON.stringify(review)).digest('hex');
}

/** Só permite escrever folhas cujos seletores removidos foram classificados como sem consumidor. */
export function validatePurgeRemovalClassification({ classification, report, sourceHash }) {
  const problems = [];
  if (!classification || classification.sourceHash !== sourceHash || !Array.isArray(classification.files)) {
    return ['classificação ausente, incompleta ou desatualizada.'];
  }
  const expected = new Set(report.files.flatMap((file) => file.selectorsRemoved.map((selector) => `${file.file}\0${selector}`)));
  const actual = new Map();
  let actualCount = 0;
  for (const file of classification.files) {
    for (const item of file.items ?? []) {
      const key = `${file.file}\0${item.selector}`;
      actualCount += 1;
      actual.set(key, item.category);
    }
  }
  if (actualCount !== expected.size || actual.size !== expected.size || [...expected].some((key) => !actual.has(key))) {
    problems.push('classificação não cobre exatamente todos os seletores removidos.');
  }
  const unsafe = [...actual.entries()].filter(([key, category]) => expected.has(key) && category !== 'unreferenced');
  if (unsafe.length > 0) {
    problems.push(`${unsafe.length} seletores removidos têm evidência ou classificação inconclusiva.`);
  }
  return problems;
}

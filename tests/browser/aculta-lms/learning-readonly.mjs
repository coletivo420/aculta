// Read-only browser review of the learner home, course journey, lesson, and activity.
// Uses visible Firefox and W3C WebDriver; it never submits LMS forms.
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const ROOT = fileURLToPath(new URL('../../../', import.meta.url));
const HOME_URL = process.env.LMS_HOME_URL;
const COURSE_URL = process.env.LMS_COURSE_URL;
const LOGIN_URL = process.env.LMS_LOGIN_URL;
const UID = process.env.LMS_UID;
const COURSE_TITLE = process.env.LMS_COURSE_TITLE;
const OUT = process.env.LMS_OUT;
const GECKODRIVER = process.env.GECKODRIVER;
for (const [name, value] of Object.entries({
  LMS_HOME_URL: HOME_URL,
  LMS_COURSE_URL: COURSE_URL,
  LMS_LOGIN_URL: LOGIN_URL,
  LMS_UID: UID,
  LMS_COURSE_TITLE: COURSE_TITLE,
  LMS_OUT: OUT,
  GECKODRIVER,
})) {
  if (!value) throw new Error(`${name} is required.`);
}
mkdirSync(OUT, { recursive: true });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok: !!ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function webdriver(method, path, body) {
  const response = await fetch(GECKODRIVER + path, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const result = await response.json();
  if (result.value?.error) {
    throw new Error(`${result.value.error}: ${String(result.value.message).split('\n')[0]}`);
  }
  return result.value;
}

function oneTimeLogin() {
  const drushRoot = process.env.LMS_DRUSH_ROOT || ROOT;
  return execFileSync('php', [
    'vendor/drush/drush/drush.php',
    'uli',
    '--no-browser',
    `--uid=${UID}`,
    `--uri=${LOGIN_URL}`,
  ], { cwd: drushRoot, stdio: ['ignore', 'pipe', 'inherit'] })
    .toString()
    .trim()
    .split('\n')
    .pop();
}

async function waitFor(sessionPath, expression, label) {
  const deadline = Date.now() + 20000;
  while (Date.now() < deadline) {
    if (await webdriver('POST', `${sessionPath}/execute/sync`, { script: `return !!(${expression});`, args: [] })) {
      return true;
    }
    await pause(250);
  }
  console.log(`TIMEOUT ${label}`);
  return false;
}

let sessionPath;
try {
  const session = await webdriver('POST', '/session', {
    capabilities: {
      alwaysMatch: {
        browserName: 'firefox',
        acceptInsecureCerts: true,
        'moz:firefoxOptions': {
          prefs: {
            'network.dns.localDomains': [new URL(HOME_URL).hostname, new URL(LOGIN_URL).hostname].join(','),
          },
        },
      },
    },
  });
  sessionPath = `/session/${session.sessionId}`;

  await webdriver('POST', `${sessionPath}/url`, { url: oneTimeLogin() });
  await pause(1200);

  const screens = [
    { name: 'home', url: HOME_URL, expected: ['ACULTA Studio Ganjalab', 'Minha aprendizagem', COURSE_TITLE] },
    { name: 'journey', url: COURSE_URL, expected: ['Minha jornada', 'Aulas do curso', COURSE_TITLE] },
  ];
  let lessonUrl = '';
  let activityUrl = '';
  const widths = [500, 1280];

  for (const screen of screens) {
    await webdriver('POST', `${sessionPath}/url`, { url: screen.url });
    await waitFor(sessionPath, 'document.body && document.body.innerText.length > 0', screen.name);
    for (const width of widths) {
      await webdriver('POST', `${sessionPath}/window/rect`, { width, height: 900 });
      await pause(350);
      const data = JSON.parse(await webdriver('POST', `${sessionPath}/execute/sync`, {
        script: `return JSON.stringify({
          url: location.href,
          width: innerWidth,
          overflow: document.documentElement.scrollWidth > innerWidth,
          text: document.querySelector('main')?.innerText.replace(/\\s+/g, ' ').trim() || document.body.innerText.replace(/\\s+/g, ' ').trim(),
          bodyText: document.body.innerText.replace(/\\s+/g, ' ').trim(),
          modal: !!document.querySelector('[role="dialog"], dialog, .modal.show'),
          lessonLinks: [...document.querySelectorAll('a[href*="/curso/"]')].map((a) => ({ text: a.innerText.trim(), href: a.href })),
        });`,
        args: [],
      }));
      check(`${screen.name} carrega em ${data.width}px`, data.url === screen.url && !data.text.includes('erro inesperado'), data.url);
      check(`${screen.name} apresenta conteúdo esperado`, screen.expected.every((text) => data.bodyText.includes(text)), screen.expected.filter((text) => !data.bodyText.includes(text)).join(', '));
      check(`${screen.name} sem rolagem horizontal em ${data.width}px`, !data.overflow);
      if (screen.name === 'journey' && !lessonUrl) lessonUrl = data.lessonLinks[0]?.href || '';
      writeFileSync(join(OUT, `${screen.name}-${data.width}.png`), Buffer.from(await webdriver('GET', `${sessionPath}/screenshot`), 'base64'));
      if (screen.name === 'home') {
        console.log(`INFO aceite de termos visível: ${data.modal ? 'sim' : 'não'} (nenhum aceite enviado)`);
      }
    }
  }

  check('jornada oferece link canônico para aula', !!lessonUrl, lessonUrl);
  if (lessonUrl) {
    await webdriver('POST', `${sessionPath}/url`, { url: lessonUrl });
    await waitFor(sessionPath, 'document.body && document.body.innerText.length > 0', 'lesson');
    for (const width of widths) {
      await webdriver('POST', `${sessionPath}/window/rect`, { width, height: 900 });
      await pause(350);
      const data = JSON.parse(await webdriver('POST', `${sessionPath}/execute/sync`, {
        script: `return JSON.stringify({
          url: location.href,
          width: innerWidth,
          overflow: document.documentElement.scrollWidth > innerWidth,
          text: document.querySelector('main')?.innerText.replace(/\\s+/g, ' ').trim() || document.body.innerText.replace(/\\s+/g, ' ').trim(),
          bodyText: document.body.innerText.replace(/\\s+/g, ' ').trim(),
          activityLinks: [...document.querySelectorAll('main a')].map((a) => ({ text: a.innerText.trim(), href: a.href })).filter((a) => /Abrir atividade/i.test(a.text)),
        });`,
        args: [],
      }));
      check(`aula abre em ${data.width}px`, data.url === lessonUrl && !data.text.includes('erro inesperado'), data.url);
      check(`aula sem rolagem horizontal em ${data.width}px`, !data.overflow);
      if (!activityUrl) activityUrl = data.activityLinks[0]?.href || '';
      writeFileSync(join(OUT, `lesson-${data.width}.png`), Buffer.from(await webdriver('GET', `${sessionPath}/screenshot`), 'base64'));
    }
  }

  check('aula oferece atividade vinculada', !!activityUrl, activityUrl);
  if (activityUrl) {
    await webdriver('POST', `${sessionPath}/url`, { url: activityUrl });
    await waitFor(sessionPath, 'document.body && document.body.innerText.length > 0', 'activity');
    for (const width of widths) {
      await webdriver('POST', `${sessionPath}/window/rect`, { width, height: 900 });
      await pause(350);
      const data = JSON.parse(await webdriver('POST', `${sessionPath}/execute/sync`, {
        script: `return JSON.stringify({
          url: location.href,
          width: innerWidth,
          overflow: document.documentElement.scrollWidth > innerWidth,
          bodyText: document.body.innerText.replace(/\\s+/g, ' ').trim(),
          answerFormId: document.querySelector('main form.aculta-lms-answer-submission')?.id || '',
          answerControls: [...(document.querySelector('main form.aculta-lms-answer-submission')?.querySelectorAll('input:not([type="hidden"]):not([type="submit"]), textarea, select') || [])]
            .filter((control) => !control.disabled && control.getClientRects().length > 0)
            .map((control) => ({ type: control.type || control.tagName.toLowerCase(), name: control.name })),
        });`,
        args: [],
      }));
      check(`atividade abre em ${data.width}px`, data.url === activityUrl && data.answerFormId === 'aculta-lms-answer-submission' && !data.bodyText.includes('erro inesperado'), data.url);
      check(`atividade oferece controle de resposta utilizável em ${data.width}px`, data.answerControls.some((control) => control.name === 'answer'), JSON.stringify(data.answerControls));
      check(`atividade sem rolagem horizontal em ${data.width}px`, !data.overflow);
      writeFileSync(join(OUT, `activity-${data.width}.png`), Buffer.from(await webdriver('GET', `${sessionPath}/screenshot`), 'base64'));
    }
  }
}
catch (error) {
  check('execução da revisão', false, String(error.message || error));
}
finally {
  if (sessionPath) await webdriver('DELETE', sessionPath).catch(() => {});
  writeFileSync(join(OUT, 'results.json'), JSON.stringify(results, null, 2));
  const passed = results.filter((result) => result.ok).length;
  console.log(`\n${passed}/${results.length} verificações PASS. Artefatos em ${OUT}`);
  process.exitCode = results.length && passed === results.length ? 0 : 1;
}

import { devtoolsUrl } from './lib/browser-env.mjs';
// Read-only probe of the Composer-installed release using the local Chrome DOM.
import {readFile, writeFile, mkdir} from 'node:fs/promises';
const source = await readFile('web/modules/contrib/yoast_seo/js/yoast_seo.js', 'utf8');
const marker = 'Orchestrator.prototype.updatePreview = function () {';
const start = source.indexOf(marker);
if (start < 0) throw Error('Expected plugin method not found.');
const body = source.slice(start + marker.length, source.indexOf('\n  };', start));
const targets = await (await fetch(devtoolsUrl('/json'))).json();
const target = targets.find(t => t.type === 'page' && /^http:\/\/(localhost|127\.0\.0\.1)(:|\/)/.test(t.url));
if (!target) throw Error('A local Chrome tab is required.');
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {socket.onopen = resolve; socket.onerror = reject;});
const result = await new Promise((resolve, reject) => {
  socket.onmessage = e => {
    const message = JSON.parse(e.data);
    if (message.id === 1) message.error ? reject(Error(message.error.message)) : resolve(message.result.result.value);
  };
  const expression = `(() => {
    try {
      new Function(${JSON.stringify(body)}).call({data: {metaTitle: 'Teste local'}, config: {enable_editing: {title: true}}});
      return {unexpectedSuccess: true};
    } catch (error) {return {name: error.name, message: error.message};}
  })()`;
  socket.send(JSON.stringify({id: 1, method: 'Runtime.evaluate', params: {expression, returnByValue: true}}));
});
socket.close();
const report = {release: '2.2.0', nativeDomPreview: result, ckeditor4Integration: source.includes('CKEDITOR.on'), ckeditor5Integration: /CKEditor5Instances|editor:attached/.test(source)};
await mkdir('tmp/seo-content-apoio', {recursive: true});
await writeFile('tmp/seo-content-apoio/yoast-compatibility.json', JSON.stringify(report, null, 2));
console.log(JSON.stringify(report));
if (result.message !== 'snippetTitle.attr is not a function') process.exitCode = 1;

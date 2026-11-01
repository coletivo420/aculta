import { siteUrl } from './lib/browser-env.mjs';
import {writeFile, mkdir} from 'node:fs/promises';
const html = await (await fetch(siteUrl('/'))).text();
const scripts = [...html.matchAll(/<script[^>]*type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/g)].map(m => JSON.parse(m[1]));
const old = scripts.find(s => s['@type'] === 'Organization');
const graph = scripts.flatMap(s => s['@graph'] ?? []);
const organizations = graph.filter(s => s['@type'] === 'Organization');
const organization = organizations[0];
if (organizations.length !== 1) throw Error(`Expected one Organization graph object; found ${organizations.length}.`);
if (old) {
  for (const key of ['name', 'legalName', 'alternateName', 'url', 'taxID', 'email', 'telephone', 'address']) {
    const stable = value => value && typeof value === 'object' && !Array.isArray(value) ? Object.fromEntries(Object.keys(value).sort().map(k => [k, stable(value[k])])) : value;
    if (JSON.stringify(stable(old[key])) !== JSON.stringify(stable(organization[key]))) {
      console.log(JSON.stringify({key, previous:old[key], current:organization[key]}));
      throw Error('Institutional data differs: ' + key);
    }
  }
}
if (!graph.find(s => s['@type'] === 'WebSite')) throw Error('WebSite missing.');
await mkdir('tmp/seo-content-apoio', {recursive:true});
await writeFile('tmp/seo-content-apoio/schema-migration.json', JSON.stringify({oldPresent:!!old,organization,graphTypes:graph.map(s=>s['@type']),compared:!!old}, null, 2));
console.log(JSON.stringify({comparedWithTheme:!!old,graphTypes:graph.map(s=>s['@type'])}));

import { devtoolsUrl, siteUrl } from './lib/browser-env.mjs';
import {writeFile} from 'node:fs/promises';
const targets=await(await fetch(devtoolsUrl('/json'))).json();
const ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
await new Promise(r=>ws.onopen=r);let id=0;const pending=new Map();
ws.onmessage=e=>{const m=JSON.parse(e.data);pending.get(m.id)?.(m.result);pending.delete(m.id);};
const call=(method,params={})=>new Promise(r=>{pending.set(++id,r);ws.send(JSON.stringify({id,method,params}));});
const ev=async expression=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));await call('Page.enable');
await call('Emulation.setEmulatedMedia',{features:[]});
const results=[];
for(const width of [1440,1200,1024,768,480,360]){
 await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
 await call('Page.navigate',{url:siteUrl('/')});await sleep(1200);
 await ev('document.querySelector("vvjb-carousel").scrollIntoView({block:"center"})');await sleep(700);
 await call('Input.dispatchMouseEvent',{type:'mouseMoved',x:1,y:1});
 const measure=()=>ev(`(()=>{const c=document.querySelector('vvjb-carousel'), w=c.querySelector('.vvjb-carousel-wrapper').getBoundingClientRect(), v=document.querySelector('.view-home-editorial-highlights').getBoundingClientRect(), a=[...c.querySelectorAll('.vvjb-item.active-slide')].map(n=>n.getBoundingClientRect());return {width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,items:a.length,interval:c._config.slideTime,loop:c._config.looping,page:c.getCurrentSlide(),centerError:Math.abs((a[0].left+a.at(-1).right)/2-(v.left+v.right)/2),wrapperCenterError:Math.abs((w.left+w.right)/2-(v.left+v.right)/2)};})()`);
 await ev('document.querySelector("vvjb-carousel").goToSlide(1)');await sleep(600);
 const first=await measure();
 await ev('document.querySelector("vvjb-carousel").goToSlide(2)');await sleep(600);
 const second=await measure();
 results.push({first,second});
}
await ev('document.querySelector("vvjb-carousel").goToSlide(1);document.activeElement.blur();document.querySelector("vvjb-carousel").resume()');
await call('Input.dispatchMouseEvent',{type:'mouseMoved',x:1,y:1});
const cycle=[];
for(let n=0;n<5;n++){cycle.push(await ev('document.querySelector("vvjb-carousel").getCurrentSlide()'));await sleep(3100);}
results.push({autoplayCycle:cycle});
await writeFile('tmp/home-carousel-review/loop-results.json',JSON.stringify(results,null,2));console.log(JSON.stringify(results,null,2));ws.close();
if(results.slice(0,-1).some(r=>r.first.overflow||r.second.overflow||r.first.centerError>2||r.second.centerError>2)||new Set(cycle).size<3||!cycle.includes(1,1))process.exitCode=1;

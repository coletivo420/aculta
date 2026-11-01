import { devtoolsUrl } from './lib/browser-env.mjs';
// Stable visual evidence, after fonts, lazy hydration and native transitions settle.
import {writeFile} from 'node:fs/promises';
const targets=await(await fetch(devtoolsUrl('/json'))).json();
const ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
let id=0;const queue=new Map();ws.onmessage=e=>{const m=JSON.parse(e.data);queue.get(m.id)?.(m.result);queue.delete(m.id);};
const call=(method,params={})=>new Promise(r=>{queue.set(++id,r);ws.send(JSON.stringify({id,method,params}));});
const ev=async expression=>(await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
await call('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
await ev('document.querySelector("vvjb-carousel").scrollIntoView({block:"center"})');await new Promise(r=>setTimeout(r,700));
await ev('document.querySelector("vvjb-carousel").pause();document.querySelector("vvjb-carousel").goToSlide(1)');await new Promise(r=>setTimeout(r,1500));
const shot=await call('Page.captureScreenshot',{captureBeyondViewport:false});await writeFile('tmp/final-local-review/carousel-desktop-stable.png',Buffer.from(shot.data,'base64'));
console.log(await ev(`JSON.stringify({width:innerWidth,cards:[...document.querySelectorAll('.vvjb-item')].map(e=>({active:e.classList.contains('active-slide'),visibility:getComputedStyle(e).visibility,opacity:getComputedStyle(e).opacity,x:e.getBoundingClientRect().x,width:e.getBoundingClientRect().width}))})`));
await ev('document.querySelector("vvjb-carousel").resume();window.scrollTo(0,0)');
ws.close();

import { devtoolsUrl, siteOrigin } from './lib/browser-env.mjs';
// Final review through the existing local Chrome session. Never contacts production.
import {writeFile, mkdir} from 'node:fs/promises';
const origin=siteOrigin(), dir='tmp/final-local-review';
await mkdir(dir,{recursive:true});
const targets=await(await fetch(devtoolsUrl('/json'))).json();
const socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
let sequence=0;const pending=new Map();
socket.onmessage=e=>{const m=JSON.parse(e.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}};
const call=(method,params={})=>new Promise((resolve,reject)=>{pending.set(++sequence,{resolve,reject});socket.send(JSON.stringify({id:sequence,method,params}));});
const evaluate=async expression=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const navigate=async path=>{const previous=await evaluate('performance.timeOrigin');await call('Page.navigate',{url:origin+path});let ready=false;for(let n=0;n<200;n++){if(await evaluate(`performance.timeOrigin!==${previous} && document.readyState==='complete' && location.pathname===${JSON.stringify(path)}`)){ready=true;break;}await sleep(100);}if(!ready)throw Error('Navigation timeout: '+path);await sleep(400);};
await call('Page.enable');
const report={pages:[],responsive:[],internalResponsive:[],links:[],contrast:[],errors:[],interaction:{}};
const originalMessage=socket.onmessage;
socket.onmessage=e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown'){const d=m.params.exceptionDetails;report.errors.push({text:d.text,description:d.exception?.description,url:d.url,stack:d.stackTrace});}originalMessage(e);};
await call('Runtime.enable');
const links=new Set();
for(const path of ['/','/institucional','/projetos','/atividades','/noticias','/transparencia','/contato','/sobre/politica-de-privacidade']){
 const response=await fetch(origin+path);await navigate(path);
 const info=await evaluate(`(()=>{const body=document.body.innerText;return {h1:[...document.querySelectorAll('h1')].map(e=>e.innerText),title:document.title,description:document.querySelector('meta[name=description]')?.content,links:[...document.querySelectorAll('a[href]')].map(e=>e.getAttribute('href')),placeholders:/Welcome!|No front page content|Lorem ipsum|admin@example.com|Coming soon|Em construção|AGUARDANDO CNPJ|INSERIR CNPJ/i.test(body),brokenImages:[...document.images].filter(e=>!e.complete||e.naturalWidth===0).map(e=>e.src),footerSlogan:document.querySelector('.aculta-footer-description')?.innerText,fields:[...document.querySelectorAll('input:not([type=hidden]):not([type=submit]),textarea')].map(e=>({name:e.name,label:!!document.querySelector('label[for="'+e.id+'"]')})),emptyHashLinks:[...document.querySelectorAll('a[href="#"]')].length};})()`);
 for(const href of info.links){const u=new URL(href,origin);if(['localhost','127.0.0.1','aculta.org'].includes(u.hostname)&&['http:','https:'].includes(u.protocol))links.add(u.pathname+u.search);}
 delete info.links;report.pages.push({path,status:response.status,...info});
}
for(const path of links){const r=await fetch(origin+path);report.links.push({path,status:r.status});}
for(const width of [1440,1200,1100,1099,1024,768,480,360]){
 await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await navigate('/');
 await evaluate('document.querySelector("vvjb-carousel").scrollIntoView({block:"center"})');
 for(let n=0;n<50;n++){if(await evaluate('!!document.querySelector("vvjb-carousel")?._inner'))break;await sleep(100);}
 await evaluate('document.querySelector("vvjb-carousel").pause();document.querySelector("vvjb-carousel").goToSlide(1)');await sleep(700);
 const info=await evaluate(`(()=>{const menu=document.querySelector('.aculta-navbar .navbar-nav'),toggle=document.querySelector('.aculta-menu-toggle'),c=document.querySelector('vvjb-carousel'),items=[...c.querySelectorAll('.active-slide.vvjb-item')],v=document.querySelector('.view-home-editorial-highlights').getBoundingClientRect(),a=items.map(e=>e.getBoundingClientRect());return {width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,menuCollapsed:getComputedStyle(toggle).display!=='none',menuRows:new Set([...menu.querySelectorAll('a')].map(e=>Math.round(e.getBoundingClientRect().top))).size,menuFont:getComputedStyle(menu.querySelector('a')).fontSize,slides:items.length,interval:c._config.slideTime,loop:c._config.looping,centerError:Math.abs((a[0].left+a.at(-1).right)/2-(v.left+v.right)/2),barHeight:document.querySelector('.aculta-section-title').getBoundingClientRect().height,bodySize:getComputedStyle(document.body).fontSize};})()`);
 report.responsive.push(info);
 const image=await call('Page.captureScreenshot',{captureBeyondViewport:true});await writeFile(`${dir}/home-${width}.png`,Buffer.from(image.data,'base64'));
}
// Mobile open/close and Escape, using the actual Bootstrap integration.
await evaluate('document.querySelector(".aculta-menu-toggle").click()');await sleep(400);
report.interaction.menuOpened=await evaluate('document.querySelector(".aculta-menu-toggle").getAttribute("aria-expanded")==="true"');
await evaluate('document.querySelector(".aculta-navbar .nav-link").focus()');await call('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await sleep(400);
report.interaction.menuClosedWithEscape=await evaluate('document.querySelector(".aculta-menu-toggle").getAttribute("aria-expanded")==="false" && document.activeElement.classList.contains("aculta-menu-toggle")');
await evaluate('document.querySelector("vvjb-carousel").scrollIntoView({block:"center"})');await sleep(300);await call('Input.dispatchMouseEvent',{type:'mouseMoved',x:1,y:1});
await evaluate('document.querySelector("vvjb-carousel").goToSlide(1);document.activeElement.blur();document.querySelector("vvjb-carousel").resume()');
report.interaction.cycle=[];for(let i=0;i<5;i++){report.interaction.cycle.push(await evaluate('document.querySelector("vvjb-carousel").getCurrentSlide()'));await sleep(3100);}
await evaluate('document.querySelector("vvjb-carousel").pause();document.querySelector("vvjb-carousel").goToSlide(1)');
await evaluate('document.querySelector(".vvjb-next").click()');await sleep(500);report.interaction.next=await evaluate('document.querySelector("vvjb-carousel").getCurrentSlide()');
await evaluate('document.querySelector(".vvjb-prev").click()');await sleep(500);report.interaction.previous=await evaluate('document.querySelector("vvjb-carousel").getCurrentSlide()');
await evaluate('document.querySelector("vvjb-carousel").focus()');await call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight',windowsVirtualKeyCode:39});await sleep(500);
report.interaction.keyboard=await evaluate('document.querySelector("vvjb-carousel").getCurrentSlide()');
await evaluate('document.querySelector(".vvjb-carousel-dot").click()');await sleep(500);report.interaction.indicator=await evaluate('document.querySelector("vvjb-carousel").getCurrentSlide()');
report.interaction.hover=await evaluate(`(()=>{const c=document.querySelector('vvjb-carousel');c.resume();c.querySelector('.vvjb-carousel-wrapper').dispatchEvent(new MouseEvent('mouseenter'));return !c._autoSlideTimer;})()`);
await evaluate(`(()=>{const w=document.querySelector('.vvjb-carousel-wrapper');w.dispatchEvent(new TouchEvent('touchstart',{touches:[new Touch({identifier:1,target:w,clientX:250,clientY:100})]}));w.dispatchEvent(new TouchEvent('touchend',{changedTouches:[new Touch({identifier:1,target:w,clientX:100,clientY:100})]}));})()`);await sleep(500);
report.interaction.touch=await evaluate('document.querySelector("vvjb-carousel").getCurrentSlide()');
await evaluate('document.querySelector("vvjb-carousel").resume();document.querySelector(".vvjb-next").focus()');
report.interaction.focusPaused=await evaluate('document.querySelector("vvjb-carousel")._isPaused');
await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});await navigate('/');
await evaluate('document.querySelector("vvjb-carousel").scrollIntoView({block:"center"})');await sleep(400);
report.interaction.reducedMotion=await evaluate('({paused:document.querySelector("vvjb-carousel")._isPaused,feature:document.querySelector("vvjb-carousel")._features.pauseOnReducedMotion})');
await call('Emulation.setEmulatedMedia',{features:[]});
// First keyboard focus can occur before VVJ's lazy hydration; the theme must wait.
await navigate('/');await evaluate('document.querySelector("vvjb-carousel").focus()');await sleep(700);
report.interaction.lazyFocusPaused=await evaluate('document.querySelector("vvjb-carousel")._isPaused');
for(const path of ['/institucional','/projetos','/atividades','/noticias','/transparencia','/contato','/sobre/politica-de-privacidade']){
 for(const width of [1440,1200,1024,768,480,360]){
  await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await navigate(path);
  report.internalResponsive.push({path,width,overflow:await evaluate('document.documentElement.scrollWidth>innerWidth')});
 }
}
await call('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});await navigate('/');
report.contrast=await evaluate(`(()=>{const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');const rgb=color=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data];};const lum=c=>c.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0);const results=[];const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);while(walker.nextNode()){const n=walker.currentNode;if(!n.textContent.trim())continue;const e=n.parentElement;if(!e||e.closest('script,style,.non-active-slide,[aria-hidden=true]'))continue;const s=getComputedStyle(e);if(!e.getClientRects().length||s.visibility==='hidden')continue;let bg=null;for(let p=e;p;p=p.parentElement){const c=rgb(getComputedStyle(p).backgroundColor);if(c[3]===255){bg=c;break;}}if(!bg)continue;const fg=rgb(s.color),a=lum(fg),b=lum(bg),ratio=(Math.max(a,b)+.05)/(Math.min(a,b)+.05),large=parseFloat(s.fontSize)>=24||(parseFloat(s.fontSize)>=18.66&&parseInt(s.fontWeight)>=700),required=large?3:4.5;if(ratio<required)results.push({text:n.textContent.trim().slice(0,75),selector:e.tagName+'.'+e.className,color:s.color,background:bg,font:s.fontSize,weight:s.fontWeight,ratio:Math.round(ratio*100)/100,required});}return results;})()`);
report.interaction.schema=await evaluate('JSON.parse(document.querySelector("script[type=\\"application/ld+json\\"]").textContent)');
await writeFile(`${dir}/results.json`,JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));socket.close();
if(report.pages.some(p=>p.status!==200||p.h1.length!==1||p.placeholders||p.brokenImages.length||p.emptyHashLinks)||report.links.some(l=>l.status>=400)||report.responsive.some(r=>r.overflow||(!r.menuCollapsed&&r.menuRows!==1)||r.centerError>2)||report.internalResponsive.some(r=>r.overflow)||report.contrast.length||report.errors.length||!report.interaction.lazyFocusPaused||!report.interaction.focusPaused||!report.interaction.hover||!report.interaction.reducedMotion.paused)process.exitCode=1;

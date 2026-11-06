/* Zapmed homepage JS — vendored from Craig's mockup (2026-10-30). assets/ -> /mockup-assets/.
   Loaded only on welcome.blade.php. Injects categories/benefits/team/reviews/faq + calculators. */

/* ===== app.js ===== */
'use strict';
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const reduced=matchMedia('(prefers-reduced-motion: reduce)');
const categories=['Weight loss','Women’s health','Men’s health','Sexual health','Hair loss','Skincare','General health'];
$('#categories').innerHTML=categories.map((c,i)=>`<button class="category care-card" data-topic="${c}"><img src="/mockup-assets/care-subject-${i}.svg" alt="" loading="lazy"><span>${c}</span></button>`).join('');
const benefits=[['Movement guides','Find movement that fits your day.'],['Tracking tools','Notice your progress, beyond the scale.'],['Unlimited practitioner support','Keep your questions close to the people who can help.'],['Health coaching','Personal guidance, with someone in your corner.'],['A private community','Make space for connection and encouragement.'],['Meal plans','Bring practical nutrition into your everyday routine.'],['Partner benefits','More ways to support your everyday wellbeing.']];
$('#benefits').innerHTML=benefits.map(([title,text],i)=>`<button class="benefit${i===0?' active':''}" aria-label="${title}"><span>0${i+1}</span><h3>${title}</h3><p>${text}</p></button>`).join('');
let lockedBenefit=null;
function activate(el){$$('.benefit').forEach(b=>b.classList.toggle('active',b===el))}
$$('.benefit').forEach(b=>{b.addEventListener('pointerenter',()=>{lockedBenefit=b;activate(b)});b.addEventListener('pointerleave',()=>{lockedBenefit=null;scrollBenefits()});b.addEventListener('focus',()=>{lockedBenefit=b;activate(b)});b.addEventListener('blur',()=>{lockedBenefit=null});b.addEventListener('click',()=>activate(b))});
function scrollBenefits(){if(lockedBenefit)return;let choices=$$('.benefit').filter(b=>{let r=b.getBoundingClientRect();return r.bottom>100&&r.top<innerHeight});if(!choices.length)return;activate(choices.reduce((a,b)=>Math.abs(a.getBoundingClientRect().top-innerHeight*.48)<Math.abs(b.getBoundingClientRect().top-innerHeight*.48)?a:b))}
let scheduled=false;addEventListener('scroll',()=>{if(!scheduled){requestAnimationFrame(()=>{scrollBenefits();scheduled=false});scheduled=true}},{passive:true});
const staff=[['Dr Bontle Ntshole','MBChB (Limpopo) | MBA'],['Dr Gerhard Coetzer','MBChB'],['Dr Frances Earle','MBChB (UP) | DipHIVMan (CMSA) | DipPEC (CMSA)'],['Dr Anke van Zyl','MBChB (Stell) | DipObs (CMSA)'],['Amy Burger','RD(SA)'],['Angelique Smith','RD(SA) | BSc Dietetics (Stellenbosch)']];
$('#team').innerHTML=staff.map(([n,c],i)=>`<button class="person care-card" data-person="${i}" aria-label="View ${n}"><div class="staff-cutout" role="img" aria-label="${n}" style="background-position:${i%3*50}% ${i<3?0:100}%"></div><small>${i<4?'PARTNER DOCTOR':'REGISTERED DIETITIAN'}</small><h3>${n}</h3><p>${c}</p></button>`).join('');
const peopleObserver=new IntersectionObserver(es=>es.forEach(e=>e.target.classList.toggle('in-view',e.isIntersecting)),{threshold:.6});$$('.person').forEach(p=>peopleObserver.observe(p));
const questions={'Sexual health':'Can we talk about sexual wellbeing?','Hair loss':'What can I do about thinning hair?','Weight management':'Why is managing my weight so difficult?','Energy and wellbeing':'Why don’t I feel like myself?','Men’s general health':'Where do I start with my health?','Contraception':'Which contraception could suit me?','Intimate health':'Can I ask about intimate health?','Menopause':'Could these changes be menopause?','Hormonal changes':'How do I make sense of these changes?','Women’s general health':'How can I make more time for my health?'};
const groups={men:['Sexual health','Hair loss','Weight management','Energy and wellbeing','Men’s general health'],women:['Contraception','Intimate health','Menopause','Hormonal changes','Women’s general health']};
$$('.topic-ui').forEach(ui=>{let g=ui.dataset.group;ui.innerHTML=`<form class="search topic-search"><label class="sr" for="${g}-query">Search ${g==='men'?'men’s':'women’s'} health topics</label><span aria-hidden="true">✦</span><input id="${g}-query" type="search" placeholder="Ask a question or search a topic…"><button aria-label="Search ${g}’s health topics">⌕</button></form><div class="topic-rail" id="${g}-rail" tabindex="0" aria-label="${g} health topics">${groups[g].map(c=>`<button class="topic-card" data-topic="${c}"><small>${c}</small><h3>${questions[c]}</h3><p>Explore your care options</p><span aria-hidden="true">→</span></button>`).join('')}</div><div class="topic-controls"><div class="controls"><button data-prev="${g}-rail" aria-label="Previous ${g} topic">←</button><button data-next="${g}-rail" aria-label="Next ${g} topic">→</button></div><span class="topic-status" role="status">5 topics to explore</span><button class="outline all-topics">See all topics →</button></div>`;ui.querySelector('form').addEventListener('submit',e=>{e.preventDefault();const q=ui.querySelector('input').value.toLowerCase().trim();let n=0;ui.querySelectorAll('.topic-card').forEach(c=>{let show=!q||c.dataset.topic.toLowerCase().includes(q);c.hidden=!show;if(show)n++});ui.querySelector('.topic-status').textContent=n?`${n} matching topic${n===1?'':'s'}`:'No match. Try a broader topic.';ui.querySelector('.topic-rail').scrollLeft=0});ui.querySelector('.all-topics').addEventListener('click',()=>{ui.querySelector('input').value='';ui.querySelectorAll('.topic-card').forEach(c=>c.hidden=false);ui.querySelector('.topic-status').textContent='All 5 topics shown';ui.querySelector('.topic-rail').scrollLeft=0})});
const faqs=[['Do I need an account to start?','You can explore this mockup and select a topic without an account. A live service would need a secure sign-up process before collecting health information.'],['What will my care cost?','The deck shows R220 per month for everyday subscriptions and R450 per month for weight-loss subscriptions. These are unverified mockup prices; confirm consultation, medicine and delivery charges before a real booking.'],['Will I receive a prescription?','A prescription is not guaranteed. A qualified clinician would assess whether treatment is appropriate. This mockup cannot prescribe.'],['Can every concern be handled online?','No. Some concerns need an examination, tests or in-person care. This website concept is not an emergency service.'],['What is included in health coaching?','The proposed membership includes nutrition, movement, mindset, tracking tools and ongoing support. Exact availability, fair-use rules and partner benefits need confirmation.'],['Is this a live clinical service?','No. This is a working design prototype. It does not submit medical information, make appointments, take payments or provide clinical care.']];
$('#faq-list').innerHTML=faqs.map(([q,a])=>`<details><summary>${q}</summary><p>${a}</p></details>`).join('');
const reviews=[['Chris','Great, friendly and speedy customer service.'],['Tyra','fast, discreet, and incredibly convenient.'],['Elzeri De Lange','Excellent, friendly and speedy customer service.'],['Romain','Amazing service'],['Elzaan Pretorius','SUPER fast delivery!'],['Natasha Bailie','they are patient and professional.']];
function reviewCard([n,t]){return `<article class="review-card"><h3>${n}</h3><span class="stars" aria-label="Five stars in source mockup">★★★★★</span><blockquote>“${t}”</blockquote><div class="review-footer"><small>Supplied excerpt · verification pending</small><a href="https://www.trustpilot.com/review/zapmed.co.za" target="_blank" rel="noopener">View Zapmed on Trustpilot</a></div></article>`}
const reviewPhoto=(src,caption)=>`<figure class="review-photo"><img src="/mockup-assets/${src}" alt="Illustrative brand photograph, not a reviewer" loading="lazy"><figcaption>${caption}</figcaption></figure>`;
$('#reviews-a').innerHTML=reviews.slice(0,2).map(reviewCard).join('')+reviewPhoto('review-man.webp','Care for every part of you.')+reviews.slice(2,3).map(reviewCard).join('')+reviews.slice(0,2).map(reviewCard).join('');
$('#reviews-b').innerHTML=reviewPhoto('review-woman-teal.png','Health support, wherever you are.')+reviews.slice(3).map(reviewCard).join('')+reviewPhoto('review-woman-teal.png','Health support, wherever you are.')+reviews.slice(3).map(reviewCard).join('');
let reviewPaused=false,lastTime=0;const rowA=$('#reviews-a'),rowB=$('#reviews-b');let dirA=1,dirB=-1;rowB.scrollLeft=rowB.scrollWidth;
$('#pause-reviews').addEventListener('click',e=>{reviewPaused=!reviewPaused;e.currentTarget.setAttribute('aria-pressed',reviewPaused);e.currentTarget.textContent=reviewPaused?'Resume review movement ▶':'Pause review movement Ⅱ'});
function animateReviews(t){const dt=Math.min(t-lastTime,50);lastTime=t;const rect=$('#reviews').getBoundingClientRect();if(!reduced.matches&&!reviewPaused&&!document.hidden&&rect.top<innerHeight&&rect.bottom>0&&!$('.review-wrap').matches(':hover,:focus-within')){[[rowA,dirA],[rowB,dirB]].forEach(([r,dir],i)=>{r.scrollLeft+=dir*dt*.022;if(r.scrollLeft>=r.scrollWidth-r.clientWidth-1){if(i===0)dirA=-1;else dirB=-1}else if(r.scrollLeft<=0){if(i===0)dirA=1;else dirB=1}})}requestAnimationFrame(animateReviews)}requestAnimationFrame(animateReviews);
function moveRail(id,d){const rail=document.getElementById(id);rail.scrollBy({left:d*(rail.querySelector('article,button')?.getBoundingClientRect().width+20||280),behavior:reduced.matches?'instant':'smooth'})}
$$('[data-prev]').forEach(b=>b.addEventListener('click',()=>moveRail(b.dataset.prev,-1)));$$('[data-next]').forEach(b=>b.addEventListener('click',()=>moveRail(b.dataset.next,1)));
$$('.rail,.topic-rail,.review-row').forEach(r=>r.addEventListener('keydown',e=>{if(e.target!==r)return;if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();r.scrollBy({left:e.key==='ArrowRight'?280:-280,behavior:reduced.matches?'instant':'smooth'})}}));
// The mockup shipped its own mobile nav (#menu/#nav). Our homepage uses the shared
// public-nav partial instead, so these may be absent — guard before wiring.
if($('#menu')&&$('#nav')){
$('#menu').addEventListener('click',()=>{const open=$('#menu').getAttribute('aria-expanded')!=='true';$('#menu').setAttribute('aria-expanded',open);$('#nav').classList.toggle('open',open)});
$('#nav').addEventListener('click',e=>{if(e.target.closest('a,button')){$('#menu').setAttribute('aria-expanded','false');$('#nav').classList.remove('open')}});
addEventListener('keydown',e=>{if(e.key==='Escape'){$('#menu').setAttribute('aria-expanded','false');$('#nav').classList.remove('open')}});
}
$('#bmi').addEventListener('submit',e=>{e.preventDefault();if(!e.target.reportValidity())return;const h=Number($('#height').value)/100,w=Number($('#weight').value);const v=w/(h*h);if(!Number.isFinite(v))return;$('#bmi-result').innerHTML=`<strong>${v.toFixed(1)}</strong> BMI <span class="small">— screening measure only</span>`;$('#bmi-marker').style.left=`${Math.max(0,Math.min(98,(v-15)/30*100))}%`});
function updateGoal(){const w=Number($('#starting').value),p=Number($('input[name=percent]:checked').value);$('#starting-value').textContent=w+' kg';$('#change').innerHTML=`−${+(w*p/100).toFixed(1)}<small>kg</small>`;$('#goal').textContent=`Illustrative weight: ${+(w*(1-p/100)).toFixed(1)} kg at a ${p}% change`}
$('#starting').addEventListener('input',updateGoal);$$('[name=percent]').forEach(r=>r.addEventListener('change',updateGoal));
const dialog=$('#dialog');let returnFocus=null;function openDialog(html){if(!dialog.open)returnFocus=document.activeElement;$('#dialog-content').innerHTML=html;dialog.showModal();$('.close').focus()}$('.close').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>returnFocus?.focus());dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close()}});
function topic(c){openDialog(`<p class="eyebrow">YOUR CARE STARTS WITH A CONVERSATION</p><h2>${c}</h2><p>You’ve selected <strong>${c.toLowerCase()}</strong>. Your next step in the proposed service is a medical assessment, followed by a clinician’s review.</p><ol><li>Tell us what you would like help with.</li><li>Discuss your options with a qualified clinician.</li><li>Receive a care plan when appropriate.</li></ol><p class="small">Prototype only. No health information is collected and no booking is made.</p><button class="button" id="confirm-topic">Save this topic for this visit ✓</button><p id="selection-status" role="status"></p>`);$('#confirm-topic').addEventListener('click',()=>{$('#selection-status').textContent=`${c} selected. You can continue exploring the website.`;$('#confirm-topic').disabled=true})}
document.addEventListener('click',e=>{const b=e.target.closest('[data-topic],[data-person],[data-plan],[data-info]');if(!b)return;if(b.dataset.topic)topic(b.dataset.topic);if(b.dataset.person!==undefined){const i=+b.dataset.person,[n,c]=staff[i];openDialog(`<p class="eyebrow">${i<4?'PARTNER DOCTOR':'REGISTERED DIETITIAN'}</p><h2>${n}</h2><img src="/mockup-assets/staff${i}.webp" alt="${n}"><p>${c}</p><p class="small">Original portrait and credentials from the presentation. Current affiliation and professional registration require confirmation.</p>`)}if(b.dataset.plan)openDialog(`<p class="eyebrow">MEMBERSHIP PREVIEW</p><h2>${b.dataset.plan}</h2><p>This is the proposed membership shown in your deck. Medication is billed separately. Prices, benefits, fair-use and cancellation terms require confirmation.</p><p>No payment or subscription is created in this mockup.</p><button class="button" data-topic="${b.dataset.plan.startsWith('Weight')?'Weight loss':'General health'}">Explore related care ↗</button>`);if(b.dataset.info)openDialog(`<h2>${b.dataset.info}</h2><p>Approved ${b.dataset.info.toLowerCase()} content has not been supplied. This page is a design placeholder for review.</p><p>This prototype uses browser-local interactions and does not send your calculator inputs or topic selections to a clinical service.</p>`)});
$('#care-search').addEventListener('submit',e=>{e.preventDefault();const q=$('#care-query').value.trim().toLowerCase();const aliases={'skin':'Skincare','weight':'Weight loss','women':'Women’s health','men':'Men’s health','sex':'Sexual health','hair':'Hair loss','general':'General health'};let matches=categories.filter(c=>!q||c.toLowerCase().includes(q));if(!matches.length)matches=[...new Set(Object.entries(aliases).filter(([k])=>q.includes(k)).map(([,v])=>v))];$('#search-status').textContent=matches.length?`${matches.length} care topic${matches.length===1?'':'s'} found`:'No matching topic. Try weight loss, hair, skin or general health.';$('#search-results').replaceChildren(...matches.map(c=>{const b=document.createElement('button');b.className='outline';b.dataset.topic=c;b.textContent=c+' ↗';return b}))});
$('#guide-open').addEventListener('click',()=>openDialog('<p class="eyebrow">GUIDE PREVIEW</p><h2>Your weight-loss kickstarter.</h2><img src="/mockup-assets/guide.webp" alt="The guide concept from the deck"><p>The proposed guide covers a nutrition plan, exercise recommendations, treatment options, expert insights and your next steps.</p><p class="small">The slide provides a cover concept, not the complete guide. A clinically reviewed downloadable guide must be supplied before launch.</p>'));


/* ===== metrics.js ===== */
/* Illustrative, browser-local metric display; no health data collected. */
(()=>{
const graph='<svg class="trend" viewBox="0 0 200 100" aria-hidden="true"><path class="grid" d="M0 20h200M0 50h200M0 80h200"/><path class="trend-line" d="M8 30 Q40 25 60 46 T108 58 T155 68 L190 80"/><circle cx="8" cy="30" r="4"/><circle cx="190" cy="80" r="5"/></svg>';
const cards=[
`<article class="metric-card ring-card"><span>Your health overview</span><div class="ring-layout"><svg viewBox="0 0 140 140" aria-hidden="true"><circle cx="70" cy="70" r="57" class="ring-base"/><circle cx="70" cy="70" r="57" class="ring-progress"/><text x="70" y="68">12</text><text x="70" y="85" class="ring-caption">sample markers</text></svg><div><small>In range</small><b>9</b><small>To discuss</small><b>3</b></div></div><footer>Illustrative overview</footer></article>`,
`<article class="metric-card trend-card"><span>Your progress</span><strong>Small steps.<br>Clearer picture.</strong>${graph}<div class="chart-labels"><small>Previous</small><small>Latest</small></div><footer>Example trend · not a prediction</footer></article>`,
`<article class="metric-card list-card"><span>Your health, in view</span><p>A snapshot to talk through.</p><dl><div><dt>Heart rate</dt><dd>74 <small>bpm</small></dd></div><div><dt>HbA1c</dt><dd>5.3 <small>%</small></dd></div><div><dt>LDL cholesterol</dt><dd>2.1 <small>mmol/L</small></dd></div><div><dt>Blood pressure</dt><dd>118/76</dd></div></dl><footer>Sample values only</footer></article>`,
`<article class="metric-card number-card"><span>Everyday movement</span><div class="big-number">6,284</div><p>steps in your day</p><div class="bars" aria-hidden="true">${[30,50,43,70,57,80,66].map(h=>`<i style="height:${h}%"></i>`).join('')}</div><footer>An illustrative day</footer></article>`,
`<article class="metric-card heart-card"><span>Heart health</span><p>Make room for a better conversation.</p>${graph}<dl><div><dt>Resting pulse</dt><dd>74 <small>bpm</small></dd></div><div><dt>Blood pressure</dt><dd>118/76</dd></div></dl></article>`,
`<article class="metric-card support-card"><span>Someone in your corner</span><strong>A plan.<br>A person.<br>A next step.</strong><ul><li>Understand your numbers</li><li>Talk with your clinician</li><li>Build everyday habits</li></ul><footer>Care starts with a conversation</footer></article>`
];
const group=()=>'<div class="health-group">'+cards.join('')+'</div>';
document.querySelector('.health-track').innerHTML=group()+group();document.querySelector('.health-group:last-child').setAttribute('aria-hidden','true');
})();


/* ===== vertical.js ===== */
/* Two separate illustrative streams; preview only. */
(()=>{
const first=document.querySelector('.health-stream');
first.classList.add('woman-stream');
const cards=[...first.querySelectorAll('.health-group:first-child .metric-card')];
function contents(indices){return '<div class="health-group">'+indices.map(i=>cards[i].outerHTML).join('')+'</div>'}
first.querySelector('.health-track').innerHTML=contents([0,2,1])+contents([0,2,1]);
first.querySelector('.health-group:last-child').setAttribute('aria-hidden','true');
first.querySelector('.stream-heading>span').textContent='HER HEALTH IN VIEW';
first.setAttribute('aria-label','Illustrative women’s health metrics');
const second=first.cloneNode(true);second.classList.remove('woman-stream');second.classList.add('man-stream');second.setAttribute('aria-label','Illustrative men’s health metrics');
second.querySelector('.health-track').innerHTML=contents([3,4,5])+contents([3,4,5]);second.querySelector('.health-group:last-child').setAttribute('aria-hidden','true');
second.querySelector('.stream-heading>span').textContent='HIS HEALTH IN VIEW';
const pause=second.querySelector('button');pause.id='pause-health-men';pause.setAttribute('aria-label','Pause men’s health metrics');pause.addEventListener('click',()=>{const paused=pause.getAttribute('aria-pressed')!=='true';pause.setAttribute('aria-pressed',paused);pause.textContent=paused?'▶':'Ⅱ';pause.setAttribute('aria-label',`${paused?'Resume':'Pause'} men’s health metrics`);second.classList.toggle('motion-paused',paused)});
first.after(second);
})();


/* ===== refinements.js ===== */
/* Shared motion and icon refinements from the supplied reference. */
const lineIcons={
 doctor:'<path d="M7 3v6a5 5 0 0 0 10 0V3M5 3h4M15 3h4M12 14v3a4 4 0 0 0 8 0v-3"/><circle cx="20" cy="11" r="2"/>',
 clipboard:'<rect x="5" y="4" width="14" height="18" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h6M9 19h3"/>',
 coach:'<path d="M3 4h18v13H9l-6 4V4Z"/><path d="m8 10 3 3 5-6"/>',
 gift:'<rect x="3" y="9" width="18" height="12" rx="2"/><path d="M12 9v12M2 9h20M12 9C4 9 6 1 10 4l2 5ZM12 9c8 0 6-8 2-5l-2 5Z"/>',
 delivery:'<path d="M2 5h12v12H2zM14 9h4l4 4v4h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
 monitor:'<rect x="2" y="3" width="20" height="14" rx="3"/><path d="M8 21h8M12 17v4M8 10h8M12 6v8"/>',
 pharmacy:'<path d="M4 21V7h16v14M2 7l2-4h16l2 4M9 21v-6h6v6M9 10h6M12 8v4"/>',
 shield:'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>'
};
function icon(name){return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">${lineIcons[name]}</svg>`}

['clipboard','doctor','delivery','coach'].forEach((n,i)=>{$$('.steps article > span')[i].innerHTML=icon(n)});
const services=[['monitor','Online consults'],['doctor','Licensed medical providers'],['clipboard','Online scripts'],['shield','SAHPRA-registered medicines*'],['pharmacy','Registered pharmacies'],['delivery','Private delivery'],['coach','Health Coach'],['gift','Partner benefits']];
const serviceGroup=()=>`<div class="services-group">${services.map(([i,t])=>`<span>${icon(i)}${t}</span>`).join('')}</div>`;
$('.services-track').innerHTML=serviceGroup()+serviceGroup();$('.services-group:last-child').setAttribute('aria-hidden','true');
const dataCards=[['Heart rate','74','bpm'],['Metabolic health','5.3','% HbA1c'],['Cholesterol','2.1','mmol/L LDL'],['Daily movement','6,284','steps'],['Everyday health','118/76','mmHg']];
const dataGroup=()=>`<div class="health-group">${dataCards.map(([t,v,u])=>`<article class="health-card"><span>${t}</span><strong>${v}<small>${u}</small></strong><svg class="pulse" viewBox="0 0 160 30" aria-hidden="true"><path d="M0 18h30l8-7 10 15 14-23 12 24 9-9h30l8-5 9 8 8-3h22"/></svg></article>`).join('')}</div>`;
$('.health-track').innerHTML=dataGroup()+dataGroup();$('.health-group:last-child').setAttribute('aria-hidden','true');
function pauseMotion(id,target,label){$(id).addEventListener('click',e=>{const paused=e.currentTarget.getAttribute('aria-pressed')!=='true';e.currentTarget.setAttribute('aria-pressed',paused);e.currentTarget.setAttribute('aria-label',`${paused?'Resume':'Pause'} ${label}`);e.currentTarget.textContent=paused?'▶':'Ⅱ';$(target).classList.toggle('motion-paused',paused)})}
pauseMotion('#pause-health','.health-stream','health data scrolling');pauseMotion('#pause-services','.service-strip','service scrolling');
const promiseObserver=new IntersectionObserver(entries=>{entries.forEach(e=>{e.target.classList.toggle('is-revealed',e.isIntersecting)})},{threshold:.4});promiseObserver.observe($('.process-promise'));
const stepObserver=new IntersectionObserver(es=>es.forEach(e=>e.target.classList.toggle('step-visible',e.isIntersecting)),{threshold:.25});$$('.steps article').forEach(e=>stepObserver.observe(e));
function tiltScale(){const p=Number($('input[name=percent]:checked').value);$('#scale-ticks').style.transform=`rotate(${(p-10)*.7}deg)`}
$$('[name=percent]').forEach(r=>r.addEventListener('change',tiltScale));
// Use the exact same image geometry, colours and hover/focus behavior on both card collections.
$$('.person').forEach((p,i)=>{p.classList.remove('in-view');peopleObserver.unobserve(p);p.dataset.cardTone=i>=4?'sand':'sage'});
// The mockup added a scroll-shadow to its own <header>. Our page uses the shared
// public-nav partial (no bare <header>), so guard before touching it.
const topNav=$('header');
if(topNav){const updateNav=()=>topNav.classList.toggle('has-scrolled',scrollY>40);addEventListener('scroll',updateNav,{passive:true});updateNav();}
const paragraphs = [...document.querySelectorAll('.story p')];
let readFrame = 0;

function updateReadingPosition() {
  readFrame = 0;
  const readingLine = window.innerHeight * 0.52;
  const edges = paragraphs.map(p => readingLine - p.getBoundingClientRect().top);
  paragraphs.forEach((p, i) => p.style.setProperty('--read-edge', edges[i] + 'px'));
}

function scheduleReadingUpdate() {
  if (!readFrame) readFrame = requestAnimationFrame(updateReadingPosition);
}
window.addEventListener('scroll', scheduleReadingUpdate, {passive: true});
window.addEventListener('resize', scheduleReadingUpdate);
document.fonts.ready.then(scheduleReadingUpdate);
updateReadingPosition();
const deck=document.querySelector('.deck'), control=document.querySelector('#deck-motion'), reduce=matchMedia('(prefers-reduced-motion: reduce)');
let running=!reduce.matches;
function label(){control.textContent=running?'Pause cards':'Play cards';}
label();control.onclick=()=>{running=!running;label();};
reduce.addEventListener('change',()=>{running=!reduce.matches;label();});
setInterval(()=>{
  if(!running||document.hidden||deck.matches(':hover,:focus-within')||deck.getBoundingClientRect().bottom<0||deck.getBoundingClientRect().top>innerHeight)return;
  const next=deck.scrollWidth-deck.clientWidth-deck.scrollLeft<3?0:deck.scrollLeft+deck.firstElementChild.offsetWidth+16;
  deck.scrollTo({left:next,behavior:reduce.matches?'instant':'smooth'});
},3800);

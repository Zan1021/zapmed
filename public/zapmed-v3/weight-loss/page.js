const staff = [
  ['Dr Bontle Ntshole','Partner doctor'], ['Dr Gerhard Coetzer','Partner doctor'],
  ['Dr Frances Earle','Partner doctor'], ['Dr Anke van Zyl','Partner doctor'],
  ['Amy Burger','Registered dietitian'], ['Angelique Smith','Registered dietitian']
];
document.querySelector('#portraits').innerHTML = staff.map(([name,role],i) =>
  i<4 ? doctorProfileCard(i,`<img loading="lazy" src="assessment/assets/doctor-${i}.webp" alt="">`) : '<article><img loading="lazy" src="../assets/staff'+i+'.webp" alt="'+name+'"><h3>'+name+'</h3><p>'+role+'</p></article>').join('');
const rows = [
  ['Convenience','Online doctor consultation','In-person appointment'],
  ['Expertise','Weight-loss care with dietitian support','General practitioner assessment'],
  ['Cost','R700 initially, then R450 per month','R800–R1,200 per visit'],
  ['Follow-ups','Care-team messaging between visits','Further appointments may carry a fee'],
  ['Delivery','Monthly delivery included','Availability and fees vary'],
  ['Support','Nutrition and habit coaching','Support depends on the practice']
];
document.querySelector('#compare').innerHTML = rows.map(([label,a,b]) =>
  '<tr><th scope="row">'+label+'</th><td>'+a+'</td><td>'+b+'</td></tr>').join('');
const strip = document.querySelector('.track');
document.querySelector('#motion').onclick = event => {
  const button = event.currentTarget;
  const paused = button.getAttribute('aria-pressed') !== 'true';
  button.setAttribute('aria-pressed',String(paused));
  button.setAttribute('aria-label',paused ? 'Resume scrolling' : 'Pause scrolling');
  button.textContent = paused ? '▶' : 'Ⅱ';
  strip.style.animationPlayState = paused ? 'paused' : 'running';
};
const curve = document.querySelector('#curve');
function drawTrend(){
  const chart = curve.closest('figure');
  chart.classList.remove('playing');
  chart.getBoundingClientRect();
  chart.classList.add('playing');
}
document.querySelector('#replay').onclick = drawTrend;
const observer = new IntersectionObserver(entries => {
  if(entries.some(entry => entry.isIntersecting)){drawTrend();observer.disconnect();}
},{threshold:.35});
observer.observe(curve);

const weightLossHero = document.querySelector('.hero');
const floatingStart = document.getElementById('wl-floating-start');
function updateFloatingStart() {
  floatingStart.hidden = weightLossHero.getBoundingClientRect().bottom > 0;
}
updateFloatingStart();
new IntersectionObserver(updateFloatingStart).observe(weightLossHero);
window.addEventListener('pageshow', updateFloatingStart);

// Shared script for the category pages (same behaviour as weight-loss/page.js, minus the weight-only parts).
const portraits = document.querySelector('#portraits');
if (portraits) {
  portraits.innerHTML = [0, 1, 2, 3].map(i =>
    doctorProfileCard(i, `<img loading="lazy" src="../weight-loss/assessment/assets/doctor-${i}.webp" alt="">`)
  ).join('');
}
const strip = document.querySelector('.track');
const motion = document.querySelector('#motion');
if (motion && strip) {
  motion.onclick = event => {
    const button = event.currentTarget;
    const paused = button.getAttribute('aria-pressed') !== 'true';
    button.setAttribute('aria-pressed', String(paused));
    button.setAttribute('aria-label', paused ? 'Resume scrolling' : 'Pause scrolling');
    button.innerHTML = paused ? '&#9654;' : '&#10074;&#10074;';
    strip.style.animationPlayState = paused ? 'paused' : 'running';
  };
}
const hero = document.querySelector('.hero');
const floatingStart = document.getElementById('wl-floating-start');
function updateFloatingStart() {
  floatingStart.hidden = hero.getBoundingClientRect().bottom > 0;
}
updateFloatingStart();
new IntersectionObserver(updateFloatingStart).observe(hero);
window.addEventListener('pageshow', updateFloatingStart);

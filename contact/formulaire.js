const form = document.getElementById('contactForm');
const subject = document.getElementById('subject');
const selection = document.getElementById('maisonsSelection');
const button = form.querySelector('[type="submit"]');
const status = document.getElementById('formStatus');
const endpoint = new URL('../api/formulaire.php', window.location.href);
let ticket;
let widget;
let pending = false;

function notice(message, error = false) {
  status.textContent = message;
  status.style.color = error ? '#b42c2d' : '';
}

function housesValidity() {
  const boxes = [...selection.querySelectorAll('input[type="checkbox"]')];
  const missing = subject.value === 'visite' && !boxes.some(box => box.checked);
  boxes.forEach((box, index) => box.setCustomValidity(missing && index === 0 ? 'Veuillez sélectionner au moins une maison.' : ''));
}

function updateSubject() {
  selection.style.display = subject.value === 'visite' ? 'block' : 'none';
  housesValidity();
}

const params = new URLSearchParams(window.location.search);
if ([...subject.options].some(option => option.value === params.get('subject'))) {
  subject.value = params.get('subject');
}
if (subject.value === 'visite') {
  const house = [...selection.querySelectorAll('input')].find(input => input.value === params.get('maison'));
  if (house) house.checked = true;
}
subject.addEventListener('change', updateSubject);
selection.addEventListener('change', housesValidity);
updateSubject();

const protectedEmail = document.getElementById('email-protected');
protectedEmail?.addEventListener('click', function () {
  this.textContent = ['contact', '@', 'maison-melina', '.', 'fr'].join('');
  this.style.cursor = 'text';
  this.classList.add('revealed');
});
document.querySelectorAll('input, select, textarea').forEach(input => {
  input.addEventListener('invalid', () => input.setAttribute('aria-invalid', 'true'));
  input.addEventListener('input', () => {
    if (input.validity.valid) input.removeAttribute('aria-invalid');
  });
});

async function request(options = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 45000);
  try {
    const response = await fetch(endpoint, {
      credentials: 'same-origin', cache: 'no-store', ...options,
      headers: { 'X-Melina-Form': '1', ...(options.headers || {}) }, signal: controller.signal,
    });
    const result = await response.json().catch(() => { throw new Error('Le serveur ne répond pas correctement. Veuillez réessayer.'); });
    if (!response.ok || result.ok !== true) throw new Error(result.message || 'L’envoi a échoué. Veuillez réessayer.');
    return result;
  } finally { clearTimeout(timeout); }
}

async function refreshTicket() {
  ticket = undefined;
  const config = await request();
  ticket = config.token;
  return config;
}

function loadCaptcha(siteKey) {
  return new Promise((resolve, reject) => {
    window.melinaCaptchaReady = () => {
      try {
        widget = window.grecaptcha.render('melinaCaptcha', {
          sitekey: siteKey,
          'expired-callback': () => notice('La vérification anti-spam a expiré. Veuillez la renouveler.', true),
          'error-callback': () => notice('La vérification anti-spam est indisponible. Veuillez réessayer.', true),
        });
        resolve();
      } catch (error) { reject(error); }
    };
    const script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/api.js?onload=melinaCaptchaReady&render=explicit&hl=fr';
    script.async = true;
    script.onerror = () => reject(new Error('Impossible de charger la vérification anti-spam. Rechargez la page.'));
    document.head.append(script);
  });
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (pending) return;
  housesValidity();
  if (!form.reportValidity()) return;
  if (!ticket || widget === undefined) {
    notice('Le formulaire n’est pas prêt. Rechargez la page et réessayez.', true);
    return;
  }
  const captcha = window.grecaptcha.getResponse(widget);
  if (!captcha) { notice('Veuillez valider la vérification anti-spam.', true); return; }
  pending = true;
  button.disabled = true;
  notice('Envoi en cours…');
  try {
    const result = await request({
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: form.elements.name.value, email: form.elements.email.value,
        telephone: form.elements.telephone.value, subject: subject.value,
        message: form.elements.message.value,
        maisons: [...selection.querySelectorAll('input:checked')].map(input => input.value),
        website: form.elements.website.value, token: ticket, captcha,
      }),
    });
    notice(result.message);
    if (!result.simulation) { form.reset(); updateSubject(); }
  } catch (error) {
    notice(error.name === 'AbortError'
      ? 'La réponse tarde. Vérifiez la réception avant de réessayer pour éviter un doublon.'
      : error.message || 'Connexion interrompue. Vérifiez la réception avant de réessayer.', true);
  } finally {
    window.grecaptcha.reset(widget);
    try { await refreshTicket(); }
    catch { ticket = undefined; notice('Rechargez la page avant un nouvel envoi. Vos champs sont conservés.', true); }
    pending = false;
    button.disabled = !ticket;
  }
});

button.disabled = true;
try {
  const config = await refreshTicket();
  await loadCaptcha(config.siteKey);
  if (config.mode === 'simulation') notice('Mode test : aucun email ne sera envoyé.');
  button.disabled = false;
} catch (error) {
  notice(error.message || 'Formulaire indisponible. Veuillez réessayer plus tard.', true);
}

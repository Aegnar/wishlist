'use strict';

// Confirmation avant les actions destructives : <form data-confirm="Message ?">.
// Délégation sur document : fonctionne aussi pour les formulaires ajoutés dynamiquement.
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
});

// Filtres : envoi automatique au changement d'une liste déroulante ou d'une case.
document.querySelectorAll('form[data-autosubmit]').forEach((form) => {
  form.addEventListener('change', (event) => {
    if (event.target.matches('select, input[type="checkbox"]')) {
      form.requestSubmit();
    }
  });
});

// Bascule cartes / tableau, mémorisée dans le navigateur.
(() => {
  const root = document.querySelector('[data-view-root]');
  if (!root) return;
  const buttons = document.querySelectorAll('[data-view-button]');
  const apply = (view) => {
    root.dataset.view = view;
    buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.viewButton === view)));
  };
  let saved = 'cards';
  try {
    saved = localStorage.getItem('wishlist.view') || 'cards';
  } catch (e) {
    // Stockage indisponible (navigation privée) : vue par défaut.
  }
  apply(saved === 'table' ? 'table' : 'cards');
  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      apply(button.dataset.viewButton);
      try {
        localStorage.setItem('wishlist.view', button.dataset.viewButton);
      } catch (e) {
        // Ignoré.
      }
    });
  });
})();

// Aperçu de la photo choisie avant envoi : <input type="file" data-preview="id-de-l-img">.
document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
  const target = document.getElementById(input.dataset.preview);
  if (!target) return;
  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) {
      target.hidden = true;
      target.removeAttribute('src');
      return;
    }
    target.src = URL.createObjectURL(file);
    target.hidden = false;
  });
});

// PWA : service worker minimal (page « Pas de connexion »).
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}

'use strict';

// Boîtes de dialogue : <button data-dialog-open="id-du-dialog">, <button data-dialog-close>.
document.querySelectorAll('[data-dialog-open]').forEach((button) => {
  const dialog = document.getElementById(button.dataset.dialogOpen);
  if (dialog && typeof dialog.showModal === 'function') {
    button.addEventListener('click', () => dialog.showModal());
  }
});

document.querySelectorAll('dialog [data-dialog-close]').forEach((button) => {
  button.addEventListener('click', () => button.closest('dialog').close());
});

// Le serveur a renvoyé des erreurs de saisie : on rouvre le dialogue pour les afficher.
document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => {
  if (typeof dialog.showModal === 'function') {
    dialog.showModal();
  }
});

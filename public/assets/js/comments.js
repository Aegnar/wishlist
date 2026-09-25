'use strict';

// Ajout d'un commentaire sans rechargement. Sans JavaScript, le formulaire est envoyé normalement.
document.querySelectorAll('form[data-comment-form]').forEach((form) => {
  const list = document.querySelector('[data-comment-list]');
  const empty = document.querySelector('[data-comment-empty]');
  const count = document.querySelector('[data-comment-count]');
  const error = form.querySelector('[data-comment-error]');
  const textarea = form.querySelector('textarea');
  const button = form.querySelector('button[type="submit"]');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  const showError = (message) => {
    error.textContent = message;
    error.hidden = false;
  };

  // Construction par textContent uniquement : aucun HTML injecté.
  const buildComment = (comment) => {
    const li = document.createElement('li');
    li.className = 'comment';
    li.id = `comment-${comment.id}`;

    const head = document.createElement('div');
    head.className = 'comment-head';
    const author = document.createElement('strong');
    author.textContent = comment.author_name;
    const time = document.createElement('time');
    time.dateTime = comment.created_at;
    time.textContent = comment.created_label;

    const del = document.createElement('form');
    del.method = 'post';
    del.action = comment.delete_url;
    del.className = 'inline';
    del.dataset.confirm = 'Supprimer ce commentaire ?';
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_csrf';
    token.value = csrf;
    const delButton = document.createElement('button');
    delButton.type = 'submit';
    delButton.className = 'link link-danger';
    delButton.textContent = 'Supprimer';
    del.append(token, delButton);

    head.append(author, time, del);
    const body = document.createElement('p');
    body.className = 'comment-body';
    body.textContent = comment.body;
    li.append(head, body);
    return li;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    error.hidden = true;
    if (!textarea.value.trim()) {
      showError('Le commentaire est vide.');
      return;
    }
    button.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf },
        body: new FormData(form),
      });
      if (response.status === 401) {
        window.location.href = '/login';
        return;
      }
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.ok) {
        showError(data.error || "Impossible d'enregistrer le commentaire.");
        return;
      }
      list.appendChild(buildComment(data.comment));
      empty.hidden = true;
      count.textContent = String(list.children.length);
      textarea.value = '';
    } catch (e) {
      showError('Pas de connexion : commentaire non envoyé.');
    } finally {
      button.disabled = false;
    }
  });
});

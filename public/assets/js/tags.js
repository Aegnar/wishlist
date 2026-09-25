'use strict';

// Champ de tags : pastilles + autocomplétion.
// Le champ texte d'origine (tags séparés par des virgules) reste la valeur envoyée au serveur,
// ce qui permet au formulaire de fonctionner aussi sans JavaScript.
document.querySelectorAll('input[data-tags]').forEach((source) => {
  const tags = source.value.split(',').map((t) => t.trim()).filter(Boolean);
  const wrapper = document.createElement('div');
  wrapper.className = 'tag-input';
  const box = document.createElement('div');
  box.className = 'tag-box';
  const input = document.createElement('input');
  input.type = 'text';
  input.placeholder = 'Ajouter un tag…';
  input.setAttribute('aria-label', 'Ajouter un tag');
  input.setAttribute('autocomplete', 'off');
  const list = document.createElement('ul');
  list.className = 'tag-suggestions';
  list.setAttribute('role', 'listbox');
  list.hidden = true;

  let suggestions = [];
  let active = -1;
  let timer = null;
  let controller = null;

  const has = (name) => tags.some((t) => t.toLowerCase() === name.toLowerCase());
  const sync = () => {
    source.value = tags.join(', ');
  };

  const hide = () => {
    list.hidden = true;
    list.replaceChildren();
    suggestions = [];
    active = -1;
  };

  const render = () => {
    box.querySelectorAll('.tag-pill').forEach((pill) => pill.remove());
    tags.forEach((tag, index) => {
      const pill = document.createElement('span');
      pill.className = 'tag-pill';
      pill.textContent = tag;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.textContent = '×';
      remove.setAttribute('aria-label', `Retirer le tag ${tag}`);
      remove.addEventListener('click', (event) => {
        event.stopPropagation();
        tags.splice(index, 1);
        sync();
        render();
        input.focus();
      });
      pill.appendChild(remove);
      box.insertBefore(pill, input);
    });
  };

  const add = (raw) => {
    const name = String(raw).replace(/[\s,]+/g, ' ').trim().slice(0, 50);
    if (name && !has(name)) {
      tags.push(name);
      sync();
      render();
    }
    input.value = '';
    hide();
  };

  const highlight = () => {
    [...list.children].forEach((li, i) => li.setAttribute('aria-selected', String(i === active)));
  };

  const show = (items) => {
    suggestions = items.filter((s) => !has(s));
    list.replaceChildren();
    suggestions.forEach((s) => {
      const li = document.createElement('li');
      li.textContent = s;
      li.setAttribute('role', 'option');
      li.addEventListener('mousedown', (event) => {
        event.preventDefault();
        add(s);
      });
      list.appendChild(li);
    });
    active = -1;
    list.hidden = suggestions.length === 0;
  };

  const fetchSuggestions = () => {
    const q = input.value.trim();
    if (!q) {
      hide();
      return;
    }
    if (controller) controller.abort();
    controller = new AbortController();
    fetch(`/tags/suggest?q=${encodeURIComponent(q)}`, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })
      .then((r) => (r.ok ? r.json() : { tags: [] }))
      .then((data) => show(Array.isArray(data.tags) ? data.tags : []))
      .catch(() => {});
  };

  input.addEventListener('input', () => {
    if (input.value.includes(',')) {
      const parts = input.value.split(',');
      const rest = parts.pop();
      parts.forEach((part) => add(part));
      input.value = rest.trimStart();
    }
    clearTimeout(timer);
    timer = setTimeout(fetchSuggestions, 200);
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' && suggestions.length) {
      event.preventDefault();
      active = (active + 1) % suggestions.length;
      highlight();
    } else if (event.key === 'ArrowUp' && suggestions.length) {
      event.preventDefault();
      active = (active - 1 + suggestions.length) % suggestions.length;
      highlight();
    } else if (event.key === 'Enter') {
      if (active >= 0 || input.value.trim()) {
        event.preventDefault();
        add(active >= 0 ? suggestions[active] : input.value);
      }
    } else if (event.key === 'Escape') {
      hide();
    } else if (event.key === 'Backspace' && input.value === '' && tags.length) {
      tags.pop();
      sync();
      render();
    }
  });

  input.addEventListener('blur', () => {
    if (input.value.trim()) {
      add(input.value);
    } else {
      hide();
    }
  });
  box.addEventListener('click', () => input.focus());

  source.type = 'hidden';
  box.appendChild(input);
  wrapper.append(box, list);
  source.after(wrapper);
  render();
});

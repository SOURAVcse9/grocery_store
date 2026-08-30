/**
 * admin/assets/js/custom-select.js — GroCo Admin Accessible Custom Select Component
 */
(function(window, document) {
  'use strict';

  function initCustomSelect(selectEl) {
    if (!selectEl || selectEl.dataset.customSelectInitialized) return;
    selectEl.dataset.customSelectInitialized = 'true';

    selectEl.style.position = 'absolute';
    selectEl.style.opacity = '0';
    selectEl.style.width = '1px';
    selectEl.style.height = '1px';
    selectEl.style.margin = '-1px';
    selectEl.style.overflow = 'hidden';
    selectEl.style.clip = 'rect(0,0,0,0)';
    selectEl.style.pointerEvents = 'none';
    selectEl.setAttribute('tabindex', '-1');

    const wrapper = document.createElement('div');
    wrapper.className = 'custom-select-wrapper';
    if (selectEl.className) {
      wrapper.classList.add(...selectEl.className.split(' ').filter(c => c && c !== 'sort-select' && c !== 'form-control'));
    }
    if (selectEl.style.width) {
      wrapper.style.width = selectEl.style.width;
    }

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'custom-select-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-haspopup', 'listbox');
    const selectId = selectEl.id || ('select_' + Math.random().toString(36).substr(2, 9));
    const listboxId = selectId + '_listbox';
    trigger.setAttribute('aria-controls', listboxId);
    if (selectEl.disabled) trigger.disabled = true;

    const labelSpan = document.createElement('span');
    labelSpan.className = 'custom-select-label';
    const selectedOption = selectEl.options[selectEl.selectedIndex] || selectEl.options[0];
    labelSpan.textContent = selectedOption ? selectedOption.text : '';

    const arrowIcon = document.createElement('span');
    arrowIcon.className = 'custom-select-arrow';
    arrowIcon.innerHTML = '&#9662;';

    trigger.appendChild(labelSpan);
    trigger.appendChild(arrowIcon);

    const menu = document.createElement('ul');
    menu.id = listboxId;
    menu.className = 'custom-select-menu';
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('tabindex', '-1');

    function buildOptions() {
      menu.innerHTML = '';
      Array.from(selectEl.options).forEach((opt, idx) => {
        const li = document.createElement('li');
        li.className = 'custom-select-option';
        li.setAttribute('role', 'option');
        li.setAttribute('id', `${listboxId}_opt_${idx}`);
        li.setAttribute('data-value', opt.value);
        li.setAttribute('data-index', idx);
        li.setAttribute('aria-selected', opt.selected ? 'true' : 'false');
        if (opt.selected) {
          li.classList.add('is-selected');
        }

        const textSpan = document.createElement('span');
        textSpan.textContent = opt.text;
        li.appendChild(textSpan);

        if (opt.selected) {
          const checkSpan = document.createElement('span');
          checkSpan.style.fontSize = '11px';
          checkSpan.textContent = '✓';
          li.appendChild(checkSpan);
        }

        li.addEventListener('click', (e) => {
          e.stopPropagation();
          selectIndex(idx);
          closeDropdown();
          trigger.focus();
        });

        menu.appendChild(li);
      });
    }

    buildOptions();

    selectEl.parentNode.insertBefore(wrapper, selectEl);
    wrapper.appendChild(selectEl);
    wrapper.appendChild(trigger);
    wrapper.appendChild(menu);

    let focusedIndex = selectEl.selectedIndex >= 0 ? selectEl.selectedIndex : 0;

    function openDropdown() {
      if (selectEl.disabled) return;
      document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => {
        if (w !== wrapper) w.classList.remove('is-open');
      });
      wrapper.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      focusedIndex = selectEl.selectedIndex >= 0 ? selectEl.selectedIndex : 0;
      updateFocusedOption();
    }

    function closeDropdown() {
      wrapper.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      menu.querySelectorAll('.custom-select-option.is-focused').forEach(el => el.classList.remove('is-focused'));
    }

    function selectIndex(index) {
      if (index < 0 || index >= selectEl.options.length) return;
      selectEl.selectedIndex = index;
      const chosen = selectEl.options[index];
      labelSpan.textContent = chosen ? chosen.text : '';
      
      const items = menu.querySelectorAll('.custom-select-option');
      items.forEach((item, idx) => {
        const isSel = idx === index;
        item.setAttribute('aria-selected', isSel ? 'true' : 'false');
        item.classList.toggle('is-selected', isSel);
        const check = item.querySelector('span:nth-child(2)');
        if (check) check.remove();
        if (isSel) {
          const checkSpan = document.createElement('span');
          checkSpan.style.fontSize = '11px';
          checkSpan.textContent = '✓';
          item.appendChild(checkSpan);
        }
      });

      const event = new Event('change', { bubbles: true });
      selectEl.dispatchEvent(event);
    }

    function updateFocusedOption() {
      const items = menu.querySelectorAll('.custom-select-option');
      items.forEach((item, idx) => {
        const isFoc = idx === focusedIndex;
        item.classList.toggle('is-focused', isFoc);
        if (isFoc) {
          trigger.setAttribute('aria-activedescendant', item.id);
          item.scrollIntoView({ block: 'nearest' });
        }
      });
    }

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (wrapper.classList.contains('is-open')) {
        closeDropdown();
      } else {
        openDropdown();
      }
    });

    trigger.addEventListener('keydown', (e) => {
      const isOpen = wrapper.classList.contains('is-open');
      const total = selectEl.options.length;

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          if (!isOpen) {
            openDropdown();
          } else {
            focusedIndex = (focusedIndex + 1) % total;
            updateFocusedOption();
          }
          break;

        case 'ArrowUp':
          e.preventDefault();
          if (!isOpen) {
            openDropdown();
          } else {
            focusedIndex = (focusedIndex - 1 + total) % total;
            updateFocusedOption();
          }
          break;

        case 'Enter':
        case ' ':
          e.preventDefault();
          if (isOpen) {
            selectIndex(focusedIndex);
            closeDropdown();
          } else {
            openDropdown();
          }
          break;

        case 'Escape':
          if (isOpen) {
            e.preventDefault();
            closeDropdown();
          }
          break;

        case 'Tab':
          if (isOpen) {
            closeDropdown();
          }
          break;
      }
    });

    document.addEventListener('click', (e) => {
      if (!wrapper.contains(e.target)) {
        closeDropdown();
      }
    });

    selectEl.addEventListener('change', () => {
      const chosen = selectEl.options[selectEl.selectedIndex];
      if (chosen) {
        labelSpan.textContent = chosen.text;
      }
    });
  }

  window.GroCoCustomSelect = {
    init: function(selector) {
      const targetSelector = selector || 'select[data-custom-select], select.custom-select-enhanced';
      document.querySelectorAll(targetSelector).forEach(initCustomSelect);
    },
    enhance: initCustomSelect
  };

  document.addEventListener('DOMContentLoaded', () => {
    window.GroCoCustomSelect.init();
  });

})(window, document);

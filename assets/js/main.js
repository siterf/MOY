/* ==========================================================================
   КИРИЛЛ ЧУМАК — main.js
   Scroll Reveal, FAQ Accordion, Multi-Step Brief Wizard, Brief Modal
   ========================================================================== */

(function () {
  'use strict';

  /* --------------------------------------------------------------------------
     1. Scroll Reveal (Плавное появление блоков)
     -------------------------------------------------------------------------- */
  (function () {
    const reveals = document.querySelectorAll('.reveal');
    if (reveals.length === 0) return;

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

      reveals.forEach((el) => observer.observe(el));
    } else {
      reveals.forEach((el) => el.classList.add('visible'));
    }
  })();

  /* --------------------------------------------------------------------------
     2. Мобильное меню
     -------------------------------------------------------------------------- */
  (function () {
    const menuToggle = document.getElementById('menuToggle');
    const mobileClose = document.getElementById('mobileNavClose');
    const mobilePanel = document.getElementById('mobileNavPanel');
    if (!menuToggle || !mobilePanel) return;

    function toggleMenu(open) {
      const isOpen = open !== undefined ? open : !mobilePanel.classList.contains('is-open');
      mobilePanel.classList.toggle('is-open', isOpen);
      menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    menuToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleMenu();
    });

    if (mobileClose) {
      mobileClose.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMenu(false);
      });
    }

    const mobileLinks = mobilePanel.querySelectorAll('a, button');
    mobileLinks.forEach((link) => {
      link.addEventListener('click', () => toggleMenu(false));
    });
  })();

  /* --------------------------------------------------------------------------
     3. FAQ Аккордеон (Плавное раскрытие)
     -------------------------------------------------------------------------- */
  (function () {
    const faqItems = document.querySelectorAll('.faq-light-item, .faq-item');
    if (faqItems.length === 0) return;

    faqItems.forEach((item) => {
      const trigger = item.querySelector('.faq-light-trigger, .faq-trigger');
      const content = item.querySelector('.faq-light-content, .faq-content');
      if (!trigger || !content) return;

      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const isActive = item.classList.contains('is-active');

        // Закрываем остальные аккордеоны для чистоты
        faqItems.forEach((other) => {
          if (other !== item && other.classList.contains('is-active')) {
            other.classList.remove('is-active');
            const otherBtn = other.querySelector('.faq-light-trigger, .faq-trigger');
            const otherContent = other.querySelector('.faq-light-content, .faq-content');
            if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
            if (otherContent) otherContent.style.maxHeight = '0px';
          }
        });

        if (isActive) {
          item.classList.remove('is-active');
          trigger.setAttribute('aria-expanded', 'false');
          content.style.maxHeight = '0px';
        } else {
          item.classList.add('is-active');
          trigger.setAttribute('aria-expanded', 'true');
          content.style.maxHeight = (content.scrollHeight + 20) + 'px';
        }
      });
    });
  })();

  /* --------------------------------------------------------------------------
     4. Пошаговый визард брифа (4 шага)
     -------------------------------------------------------------------------- */
  (function () {
    const form = document.getElementById('pageBriefForm');
    if (!form) return;

    const labelEl = document.getElementById('pageStepLabel');
    const successEl = document.getElementById('pageSuccessBox');
    const stepNames = [
      'Шаг 1 из 4: О бизнесе',
      'Шаг 2 из 4: Главная задача',
      'Шаг 3 из 4: Что не устраивает',
      'Шаг 4 из 4: Контакты для связи'
    ];

    const STORAGE_KEY = 'kirill_brief_draft_v1';

    // Восстановление черновика из LocalStorage
    (function restoreDraft() {
      try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;
        const draft = JSON.parse(saved);
        if (!draft || typeof draft !== 'object') return;

        Object.keys(draft).forEach((key) => {
          const val = draft[key];
          const inputs = form.querySelectorAll(`[name="${key}"]`);
          inputs.forEach((input) => {
            if (input.type === 'checkbox') {
              if (Array.isArray(val)) {
                input.checked = val.includes(input.value);
              } else {
                input.checked = input.value === val;
              }
            } else if (input.type === 'radio') {
              input.checked = input.value === val;
            } else {
              input.value = val;
            }
          });
        });
      } catch (e) {}
    })();

    // Автосохранение черновика
    function saveDraft() {
      try {
        const formData = getFormData();
        localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
      } catch (e) {}
    }

    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);

    function getFormData() {
      const data = {};
      const inputs = form.querySelectorAll('input, textarea, select');
      inputs.forEach((input) => {
        if (!input.name) return;
        if (input.type === 'checkbox') {
          if (!data[input.name]) data[input.name] = [];
          if (input.checked) data[input.name].push(input.value);
        } else if (input.type === 'radio') {
          if (input.checked) data[input.name] = input.value;
        } else {
          data[input.name] = input.value;
        }
      });
      return data;
    }

    function showStep(stepNum) {
      const panels = form.querySelectorAll('[data-page-panel]');
      panels.forEach((panel) => {
        const pNum = parseInt(panel.getAttribute('data-page-panel'), 10);
        panel.style.display = pNum === stepNum ? 'block' : 'none';
      });

      const bullets = document.querySelectorAll('[data-page-bullet]');
      bullets.forEach((bullet) => {
        const bNum = parseInt(bullet.getAttribute('data-page-bullet'), 10);
        if (bNum === stepNum) {
          bullet.style.background = 'var(--accent)';
          bullet.style.color = '#FFFFFF';
        } else if (bNum < stepNum) {
          bullet.style.background = 'var(--accent-dim)';
          bullet.style.color = 'var(--accent)';
        } else {
          bullet.style.background = 'var(--bg-alt)';
          bullet.style.color = 'var(--txt-muted)';
        }
      });

      if (labelEl && stepNames[stepNum - 1]) {
        labelEl.textContent = stepNames[stepNum - 1];
      }
    }

    form.addEventListener('click', (e) => {
      const nextBtn = e.target.closest('[data-page-next]');
      if (nextBtn) {
        const nextStep = parseInt(nextBtn.getAttribute('data-page-next'), 10);
        if (nextStep === 2) {
          const compInput = form.querySelector('input[name="company_name"]');
          if (compInput && !compInput.value.trim()) {
            compInput.focus();
            compInput.style.borderColor = 'var(--accent)';
            return;
          }
        }
        showStep(nextStep);
      }

      const prevBtn = e.target.closest('[data-page-prev]');
      if (prevBtn) {
        const prevStep = parseInt(prevBtn.getAttribute('data-page-prev'), 10);
        showStep(prevStep);
      }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const consentInput = form.querySelector('input[name="consent"]');
      if (consentInput && !consentInput.checked) {
        alert('Пожалуйста, подтвердите согласие на обработку персональных данных.');
        const cGroup = form.querySelector('.form-consent-group');
        if (cGroup) {
          cGroup.classList.add('has-error');
          cGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }

      const nameInput = form.querySelector('input[name="client_name"]');
      const contactInput = form.querySelector('input[name="contact_info"]') || form.querySelector('input[name="contact"]');

      if (nameInput && !nameInput.value.trim()) {
        nameInput.focus();
        nameInput.style.borderColor = 'var(--accent)';
        return;
      }

      if (contactInput && !contactInput.value.trim()) {
        contactInput.focus();
        contactInput.style.borderColor = 'var(--accent)';
        return;
      }

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Отправка...</span>';
      }

      const payload = getFormData();
      payload.source = document.title || 'Сайт (Бриф)';

      const API_URL = 'https://functions.yandexcloud.net/d4egjk1tlbp6qqdkd9kd';

      try {
        let response = await fetch(API_URL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        }).catch(() => null);

        // Резервный вызов локального PHP при необходимости
        if (!response || !response.ok) {
          response = await fetch('api/brief.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          }).catch(() => null);
        }

        const result = response ? await response.json().catch(() => null) : null;

        if (result && result.success) {
          try {
            localStorage.removeItem(STORAGE_KEY);
          } catch (err) {}

          const panels = form.querySelectorAll('[data-page-panel]');
          panels.forEach((p) => (p.style.display = 'none'));

          const wizardHeader = form.parentElement.querySelector('.wizard-steps-header');
          if (wizardHeader) wizardHeader.style.display = 'none';

          if (successEl) {
            successEl.style.display = 'block';
            successEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        } else {
          alert((result && result.error) ? result.error : 'Не удалось отправить бриф. Пожалуйста, попробуйте еще раз или напишите мне в MAX/Telegram напрямую.');
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
          }
        }
      } catch (networkErr) {
        console.error('Submit error:', networkErr);
        alert('Ошибка соединения с сервером. Пожалуйста, проверьте подключение к интернету или напишите мне в MAX/Telegram напрямую.');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnHtml;
        }
      }
    });
  })();

  /* --------------------------------------------------------------------------
     5. Модальное окно
     -------------------------------------------------------------------------- */
  (function () {
    const modal = document.getElementById('briefModal');
    if (!modal) return;

    const openBtns = document.querySelectorAll('[data-open-brief]');
    const closeBtns = document.querySelectorAll('[data-close-brief]');

    function openModal() {
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closeModal() {
      modal.style.display = 'none';
      document.body.style.overflow = '';
    }

    openBtns.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        openModal();
      });
    });

    closeBtns.forEach((btn) => {
      btn.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.style.display === 'flex') {
        closeModal();
      }
    });
  })();

  /* --------------------------------------------------------------------------
     6. Cookie Consent Banner
     -------------------------------------------------------------------------- */
  (function () {
    var COOKIE_KEY = "chumak_cookie_accepted_v1";
    if (localStorage.getItem(COOKIE_KEY)) return;

    var banner = document.createElement("div");
    banner.className = "cookie-banner";
    banner.innerHTML = '<div class="cookie-text">Мы используем файлы cookie для корректной работы сайта и аналитики. Продолжая просмотр, вы соглашаетесь с <a href="privacy.html">Политикой конфиденциальности</a>.</div><button class="cookie-btn" id="cookieAcceptBtn">Понятно</button>';

    document.body.appendChild(banner);

    document.getElementById("cookieAcceptBtn").addEventListener("click", function () {
      localStorage.setItem(COOKIE_KEY, "true");
      banner.style.opacity = "0";
      banner.style.transform = "translateY(15px)";
      banner.style.transition = "all 0.3s ease";
      setTimeout(function () { banner.remove(); }, 300);
    });
  })();
})();
/**
 * script.js — клиентская часть.
 *
 * Отправляет форму через Fetch API к веб-сервису /api.php.
 * Если JS недоступен, форма отправляется штатным образом (action=).
 *
 * Авторизованные пользователи хранят Basic-credentials в sessionStorage,
 * поэтому между вкладками/сессиями учётные данные не сохраняются.
 */

// ─── Утилиты ──────────────────────────────────────────────────────────────────

const API_URL =
  "http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project-web/api.php"; // ← поменяйте, если сервис лежит по другому пути

/** Базовый заголовок авторизации, если пользователь вошёл. */
function getAuthHeader() {
  const creds = sessionStorage.getItem("userCredentials");
  if (!creds) return null;
  return "Basic " + btoa(creds); // creds = "login:password"
}

/** Показывает сообщение под формой. */
function showMessage(form, message, type = "success") {
  const old = form.querySelector(".form-message");
  if (old) old.remove();

  const div = document.createElement("div");
  div.className = `form-message alert alert-${type === "success" ? "success" : "danger"} mt-3`;
  div.textContent = message;
  form.appendChild(div);
  setTimeout(() => div.parentNode && div.remove(), 6000);
}

// ─── Блок авторизации / профиля (динамически вставляется в DOM) ───────────────

function createAuthUI() {
  const section = document.getElementById("form-section");
  if (!section) return;

  // Создаём панель только один раз
  if (document.getElementById("auth-panel")) return;

  const panel = document.createElement("div");
  panel.id = "auth-panel";
  panel.style.cssText = `
    background: #f4f4f8;
    border-left: 4px solid #6a0dad;
    padding: 16px 20px;
    margin-bottom: 24px;
    border-radius: 4px;
    font-family: inherit;
  `;

  // Шаблон — будет перерисован renderAuthPanel()
  section.querySelector(".container").prepend(panel);
  renderAuthPanel();
}

function renderAuthPanel() {
  const panel = document.getElementById("auth-panel");
  if (!panel) return;

  const creds = sessionStorage.getItem("userCredentials");

  if (creds) {
    const login = creds.split(":")[0];
    panel.innerHTML = `
      <strong>Вы вошли как:</strong> ${escHtml(login)}
      &nbsp;
      <button id="btn-logout" style="margin-left:12px;padding:4px 12px;cursor:pointer;border:1px solid #6a0dad;background:#fff;border-radius:4px;color:#6a0dad;">
        Выйти
      </button>
      <button id="btn-show-profile" style="margin-left:8px;padding:4px 12px;cursor:pointer;border:none;background:#6a0dad;border-radius:4px;color:#fff;">
        Мой профиль
      </button>
      <div id="profile-block" style="display:none;margin-top:12px;"></div>
    `;
    document.getElementById("btn-logout").addEventListener("click", logout);
    document
      .getElementById("btn-show-profile")
      .addEventListener("click", loadProfile);

    // Меняем кнопку формы на «Обновить данные»
    const submitBtn = document.getElementById("submitBtn");
    if (submitBtn) submitBtn.textContent = "ОБНОВИТЬ ДАННЫЕ";
  } else {
    panel.innerHTML = `
      <strong>Уже есть аккаунт?</strong>
      &nbsp;
      <button id="btn-show-login" style="padding:4px 12px;cursor:pointer;border:none;background:#6a0dad;border-radius:4px;color:#fff;">
        Войти
      </button>
      <div id="login-form-inline" style="display:none;margin-top:12px;">
        <input id="il-login" type="text" placeholder="Логин" style="padding:6px;border:1px solid #ccc;border-radius:4px;margin-right:8px;width:140px;">
        <input id="il-pass"  type="password" placeholder="Пароль" style="padding:6px;border:1px solid #ccc;border-radius:4px;margin-right:8px;width:140px;">
        <button id="btn-do-login" style="padding:6px 14px;cursor:pointer;border:none;background:#6a0dad;border-radius:4px;color:#fff;">OK</button>
        <span id="il-error" style="color:red;margin-left:8px;"></span>
      </div>
    `;
    document.getElementById("btn-show-login").addEventListener("click", () => {
      document.getElementById("login-form-inline").style.display = "block";
    });
    document.getElementById("btn-do-login").addEventListener("click", doLogin);

    const submitBtn = document.getElementById("submitBtn");
    if (submitBtn) submitBtn.textContent = "ОТПРАВИТЬ";
  }
}

function escHtml(s) {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

async function doLogin() {
  const login = document.getElementById("il-login").value.trim();
  const pass = document.getElementById("il-pass").value;
  const errEl = document.getElementById("il-error");
  errEl.textContent = "";

  if (!login || !pass) {
    errEl.textContent = "Введите логин и пароль.";
    return;
  }

  try {
    const res = await fetch(API_URL, {
      headers: { Authorization: "Basic " + btoa(login + ":" + pass) },
    });
    const data = await res.json();
    if (data.success) {
      sessionStorage.setItem("userCredentials", login + ":" + pass);
      renderAuthPanel();
      fillFormFromProfile(data.profile);
    } else {
      errEl.textContent = "Неверный логин или пароль.";
    }
  } catch {
    errEl.textContent = "Ошибка соединения.";
  }
}

function logout() {
  sessionStorage.removeItem("userCredentials");
  renderAuthPanel();
  // Очищаем форму
  const form = document.getElementById("feedbackForm");
  if (form) form.reset();
}

async function loadProfile() {
  const block = document.getElementById("profile-block");
  block.style.display = "block";
  block.textContent = "Загрузка…";

  try {
    const res = await fetch(API_URL, {
      headers: { Authorization: getAuthHeader() },
    });
    const data = await res.json();
    if (data.success) {
      const p = data.profile;
      block.innerHTML = `
        <table style="border-collapse:collapse;font-size:13px;">
          <tr><td style="padding:2px 10px 2px 0;color:#555;">Логин:</td><td><strong>${escHtml(p.login)}</strong></td></tr>
          <tr><td style="padding:2px 10px 2px 0;color:#555;">Имя:</td><td>${escHtml(p.name)}</td></tr>
          <tr><td style="padding:2px 10px 2px 0;color:#555;">Email:</td><td>${escHtml(p.email)}</td></tr>
          <tr><td style="padding:2px 10px 2px 0;color:#555;">Телефон:</td><td>${escHtml(p.phone || "—")}</td></tr>
          <tr><td style="padding:2px 10px 2px 0;color:#555;">Комментарий:</td><td>${escHtml(p.comment || "—")}</td></tr>
        </table>
      `;
      fillFormFromProfile(p);
    } else {
      block.textContent = "Не удалось загрузить профиль.";
    }
  } catch {
    block.textContent = "Ошибка соединения.";
  }
}

function fillFormFromProfile(profile) {
  if (!profile) return;
  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.value = val || "";
  };
  set("field-name-1", profile.name);
  set("field-email", profile.email);
  set("phone", profile.phone);
  set("field-name-2", profile.comment);
}

// ─── Главный класс формы ──────────────────────────────────────────────────────

class FeedbackForm {
  constructor() {
    this.feedbackForm = document.getElementById("feedbackForm");
    this.submitBtn = document.getElementById("submitBtn");
    this.STORAGE_KEY = "feedbackFormData";
    this.init();
  }

  init() {
    this.restoreFormData();
    this.feedbackForm.addEventListener("submit", (e) => this.handleSubmit(e));
    this.feedbackForm.addEventListener("input", () => this.saveFormData());

    // Убираем стандартный action, чтобы форма не уходила на formcarry
    this.feedbackForm.removeAttribute("action");
  }

  saveFormData() {
    const formData = {
      name: document.getElementById("field-name-1").value,
      phone: document.getElementById("phone").value,
      email: document.getElementById("field-email").value,
      comment: document.getElementById("field-name-2").value,
      agree: document.getElementById("agree").checked,
    };
    try {
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(formData));
    } catch {}
  }

  restoreFormData() {
    try {
      const savedData = localStorage.getItem(this.STORAGE_KEY);
      if (!savedData) return;
      const data = JSON.parse(savedData);
      document.getElementById("field-name-1").value = data.name || "";
      document.getElementById("phone").value = data.phone || "";
      document.getElementById("field-email").value = data.email || "";
      document.getElementById("field-name-2").value = data.comment || "";
      document.getElementById("agree").checked = data.agree || false;
    } catch (error) {
      console.error("Ошибка восстановления данных:", error);
      this.clearFormData();
    }
  }

  clearFormData() {
    try {
      localStorage.removeItem(this.STORAGE_KEY);
    } catch {}
  }

  /** Собирает данные формы в объект. */
  collectData() {
    return {
      name: document.getElementById("field-name-1").value.trim(),
      phone: document.getElementById("phone").value.trim(),
      email: document.getElementById("field-email").value.trim(),
      comment: document.getElementById("field-name-2").value.trim(),
    };
  }

  async handleSubmit(e) {
    e.preventDefault();

    if (!this.feedbackForm.checkValidity()) {
      showMessage(
        this.feedbackForm,
        "Пожалуйста, заполните все обязательные поля правильно.",
        "error",
      );
      return;
    }

    const originalText = this.submitBtn.textContent;
    this.submitBtn.disabled = true;
    this.submitBtn.textContent = "Отправка…";

    const isLoggedIn = !!sessionStorage.getItem("userCredentials");
    const method = isLoggedIn ? "PUT" : "POST";
    const headers = { "Content-Type": "application/json" };
    if (isLoggedIn) headers["Authorization"] = getAuthHeader();

    console.log("Отправка на API:", API_URL, method, this.collectData());

    try {
      const response = await fetch(API_URL, {
        method,
        headers,
        body: JSON.stringify(this.collectData()),
      });

      let result;
      try {
        result = await response.json();
      } catch {
        result = {};
      }
      console.log("Ответ API:", result);

      // Если в ответе есть логин — показываем его в любом случае
      if (result.login && result.password) {
        showMessage(
          this.feedbackForm,
          `✅ Заявка принята! Логин: ${result.login} | Пароль: ${result.password} — сохраните их!`,
          "success",
        );
        sessionStorage.setItem(
          "userCredentials",
          result.login + ":" + result.password,
        );
        renderAuthPanel();
        this.feedbackForm.reset();
        this.clearFormData();
      } else if (result.success) {
        showMessage(
          this.feedbackForm,
          "✅ Данные успешно обновлены!",
          "success",
        );
        this.feedbackForm.reset();
        this.clearFormData();
      } else if (result.errors) {
        const errorMsg = Array.isArray(result.errors)
          ? result.errors.join(". ")
          : Object.values(result.errors).join(". ");
        showMessage(this.feedbackForm, "❌ " + errorMsg, "error");
      } else if (
        result.message &&
        result.message !== "Требуется авторизация."
      ) {
        showMessage(this.feedbackForm, "❌ " + result.message, "error");
      }
    } catch (error) {
      console.error("Ошибка отправки:", error);
      // не показываем техническую ошибку пользователю
    } finally {
      this.submitBtn.disabled = false;
      this.submitBtn.textContent = originalText;
    }
  }
}

// ─── Карусель отзывов ─────────────────────────────────────────────────────────

class ReviewsCarousel {
  constructor() {
    this.currentIndex = 0;
    this.reviews = document.querySelectorAll(".review-item");
    this.dots = document.querySelectorAll(".dot");
    this.autoSlideInterval = null;
    this.autoSlideDelay = 5000;
    this.init();
  }

  init() {
    this.showReview(this.currentIndex);
    this.addEventListeners();
    this.startAutoSlide();
  }

  showReview(index) {
    this.reviews.forEach((r) => {
      r.classList.remove("active");
      r.style.opacity = "0";
      r.style.transform = "translateX(30px)";
    });
    this.dots.forEach((d) => d.classList.remove("active"));

    this.reviews[index].classList.add("active");
    if (this.dots[index]) this.dots[index].classList.add("active");

    setTimeout(() => {
      this.reviews[index].style.opacity = "1";
      this.reviews[index].style.transform = "translateX(0)";
    }, 50);

    this.currentIndex = index;
  }

  nextReview() {
    this.showReview((this.currentIndex + 1) % this.reviews.length);
  }

  prevReview() {
    this.showReview(
      (this.currentIndex - 1 + this.reviews.length) % this.reviews.length,
    );
  }

  goToReview(index) {
    if (index >= 0 && index < this.reviews.length) this.showReview(index);
  }

  startAutoSlide() {
    this.stopAutoSlide();
    this.autoSlideInterval = setInterval(
      () => this.nextReview(),
      this.autoSlideDelay,
    );
  }

  stopAutoSlide() {
    if (this.autoSlideInterval) {
      clearInterval(this.autoSlideInterval);
      this.autoSlideInterval = null;
    }
  }

  addEventListeners() {
    const carousel = document.getElementById("reviewsCarousel");
    if (carousel) {
      carousel.addEventListener("mouseenter", () => this.stopAutoSlide());
      carousel.addEventListener("mouseleave", () => this.startAutoSlide());
    }

    document.querySelectorAll(".nav-btn, .dot").forEach((el) => {
      el.addEventListener("click", () => {
        this.stopAutoSlide();
        setTimeout(() => this.startAutoSlide(), 10000);
      });
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") {
        this.prevReview();
        this.stopAutoSlide();
      }
      if (e.key === "ArrowRight") {
        this.nextReview();
        this.stopAutoSlide();
      }
    });
  }
}

// ─── Инициализация ────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
  // Форма
  new FeedbackForm();

  // Панель авторизации
  createAuthUI();

  // Карусель отзывов (если элементы есть)
  if (document.querySelectorAll(".review-item").length) {
    window.reviewsCarousel = new ReviewsCarousel();
    window.nextReview = () => window.reviewsCarousel.nextReview();
    window.prevReview = () => window.reviewsCarousel.prevReview();
    window.goToReview = (i) => window.reviewsCarousel.goToReview(i);
  }
});

// ─── Слайдер карточек отзывов (второй, для .review-card) ────────────────────

document.addEventListener("DOMContentLoaded", function () {
  const cards = document.querySelectorAll(".review-card");
  const prevBtns = document.querySelectorAll(".prev-btn");
  const nextBtns = document.querySelectorAll(".next-btn");
  const pageNumbers = document.querySelectorAll(".current-page");
  if (!cards.length) return;

  let currentIndex = 0;
  const total = cards.length;

  function updateSlider() {
    cards.forEach((c) => c.classList.remove("active"));
    if (cards[currentIndex]) cards[currentIndex].classList.add("active");
    const pageNum = (currentIndex + 1).toString().padStart(2, "0");
    pageNumbers.forEach((el) => (el.textContent = pageNum));
  }

  prevBtns.forEach((btn) =>
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      currentIndex = (currentIndex - 1 + total) % total;
      updateSlider();
    }),
  );

  nextBtns.forEach((btn) =>
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      currentIndex = (currentIndex + 1) % total;
      updateSlider();
    }),
  );

  updateSlider();
});

// ─── Мобильное меню ──────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", function () {
  const mobileMenuButton = document.getElementById("mobileMenuButton");
  const mainNav = document.getElementById("mainNav");

  if (mobileMenuButton && mainNav) {
    mobileMenuButton.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      mainNav.classList.toggle("mobile-open");
      this.classList.toggle("active");
    });
  }

  function setupDropdown(dropdownId, menuId) {
    const dropdown = document.getElementById(dropdownId);
    const menu = document.getElementById(menuId);
    if (!dropdown || !menu) return;

    dropdown.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      if (window.innerWidth <= 768) {
        this.classList.toggle("open");
        menu.classList.toggle("show");
        document.querySelectorAll(".dropdown-menu").forEach((m) => {
          if (m !== menu && m.classList.contains("show")) {
            m.classList.remove("show");
            const od = document.getElementById(
              m.id.replace("Menu", "Dropdown"),
            );
            if (od) od.classList.remove("open");
          }
        });
      } else {
        document.querySelectorAll(".dropdown-menu.show").forEach((m) => {
          if (m !== menu) m.classList.remove("show");
        });
        menu.classList.toggle("show");
      }
    });

    menu.querySelectorAll(".dropdown-item").forEach((item) => {
      item.addEventListener("click", () => {
        menu.classList.remove("show");
        dropdown.classList.remove("open");
        if (window.innerWidth <= 768 && mainNav) {
          mainNav.classList.remove("mobile-open");
          if (mobileMenuButton) mobileMenuButton.classList.remove("active");
        }
      });
    });
  }

  setupDropdown("adminDropdown", "adminMenu");
  setupDropdown("aboutDropdown", "aboutMenu");

  document.addEventListener("click", function (e) {
    if (mainNav && mobileMenuButton) {
      if (!mobileMenuButton.contains(e.target) && !mainNav.contains(e.target)) {
        mainNav.classList.remove("mobile-open");
        mobileMenuButton.classList.remove("active");
      }
    }
    if (window.innerWidth > 768) {
      document.querySelectorAll(".dropdown-menu.show").forEach((menu) => {
        const dropdownId = menu.id.replace("Menu", "Dropdown");
        const dropdown = document.getElementById(dropdownId);
        if (
          dropdown &&
          !dropdown.contains(e.target) &&
          !menu.contains(e.target)
        ) {
          menu.classList.remove("show");
        }
      });
    }
  });

  window.addEventListener("resize", function () {
    if (window.innerWidth > 768) {
      if (mainNav) mainNav.classList.remove("mobile-open");
      if (mobileMenuButton) mobileMenuButton.classList.remove("active");
      document
        .querySelectorAll(".dropdown-menu")
        .forEach((m) => m.classList.remove("show"));
      document
        .querySelectorAll(".nav-link.open")
        .forEach((l) => l.classList.remove("open"));
    }
  });
});

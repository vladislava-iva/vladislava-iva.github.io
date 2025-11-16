class FeedbackForm {
  constructor() {
    this.popup = document.getElementById("popup");
    this.form = document.getElementById("form");
    this.openBtn = document.getElementById("openBtn");
    this.closeBtn = document.querySelector(".close");
    this.storageKey = "feedbackFormData";

    this.init();
  }

  init() {
    this.openBtn.addEventListener("click", () => this.open());
    this.closeBtn.addEventListener("click", () => this.close());
    this.form.addEventListener("submit", (e) => this.submit(e));
    this.form.addEventListener("input", () => this.saveData());

    window.addEventListener("popstate", (e) => {
      if (e.state && e.state.popup) {
        this.popup.style.display = "block";
      } else {
        this.popup.style.display = "none";
      }
    });

    this.popup.addEventListener("click", (e) => {
      if (e.target === this.popup) {
        this.close();
      }
    });

    this.loadData();
  }

  open() {
    this.popup.style.display = "block";
    history.pushState({ popup: true }, "", "#feedback");
  }

  close() {
    this.popup.style.display = "none";
    history.back();
  }

  saveData() {
    const data = {};
    const formData = new FormData(this.form);

    for (let [key, value] of formData.entries()) {
      data[key] = value;
    }

    data.agree = this.form.elements.agree.checked;
    localStorage.setItem(this.storageKey, JSON.stringify(data));
  }

  loadData() {
    const saved = localStorage.getItem(this.storageKey);
    if (saved) {
      const data = JSON.parse(saved);

      for (let key in data) {
        if (this.form.elements[key]) {
          if (key === "agree") {
            this.form.elements[key].checked = data[key];
          } else {
            this.form.elements[key].value = data[key];
          }
        }
      }
    }
  }

  clearData() {
    localStorage.removeItem(this.storageKey);
    this.form.reset();
  }

  async submit(e) {
    e.preventDefault();

    if (!this.validate()) return;

    const submitBtn = this.form.querySelector(".sub");
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = "Отправка...";

    try {
      const formData = new FormData(this.form);
      const data = Object.fromEntries(formData);

      const response = await fetch("https://formcarry.com/s/uy4ELqw-srU", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });

      const result = await response.json();

      if (response.ok) {
        this.showMsg("Сообщение отправлено!", "suc");
        this.clearData();
        setTimeout(() => this.close(), 1500);
      } else {
        throw new Error(result.message || "Ошибка сервера");
      }
    } catch (error) {
      this.showMsg(`Ошибка: ${error.message}`, "err");
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  }

  validate() {
    const fio = this.form.elements.fio.value.trim();
    const email = this.form.elements.email.value.trim();
    const msg = this.form.elements.msg.value.trim();
    const agree = this.form.elements.agree.checked;

    if (!fio) {
      this.showMsg("Введите ФИО", "err");
      return false;
    }

    if (!this.isValidEmail(email)) {
      this.showMsg("Введите корректный email", "err");
      return false;
    }

    if (!msg) {
      this.showMsg("Введите сообщение", "err");
      return false;
    }

    if (!agree) {
      this.showMsg("Необходимо согласие с политикой", "err");
      return false;
    }

    return true;
  }

  isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  showMsg(text, type) {
    const existing = document.querySelector(".msg");
    if (existing) existing.remove();

    const msg = document.createElement("div");
    msg.className = `msg ${type}`;
    msg.textContent = text;

    document.body.appendChild(msg);

    setTimeout(() => {
      msg.remove();
    }, 5000);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new FeedbackForm();
});

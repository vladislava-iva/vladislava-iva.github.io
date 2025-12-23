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
  }

  saveFormData() {
    const formData = {
      name: document.getElementById("field-name-1").value,
      phone: document.getElementById("phone").value,
      email: document.getElementById("field-email").value,
      comment: document.getElementById("field-name-2").value,
      agree: document.getElementById("agree").checked,
    };
    localStorage.setItem(this.STORAGE_KEY, JSON.stringify(formData));
  }

  restoreFormData() {
    const savedData = localStorage.getItem(this.STORAGE_KEY);
    if (savedData) {
      try {
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
  }

  clearFormData() {
    localStorage.removeItem(this.STORAGE_KEY);
  }

  showMessage(message, type = "success") {
    const existingMessage = document.querySelector(".form-message");
    if (existingMessage) {
      existingMessage.remove();
    }

    const messageDiv = document.createElement("div");
    messageDiv.className = `form-message alert alert-${
      type === "success" ? "success" : "danger"
    } mt-3`;
    messageDiv.textContent = message;

    this.feedbackForm.appendChild(messageDiv);

    setTimeout(() => {
      if (messageDiv.parentNode) {
        messageDiv.remove();
      }
    }, 5000);
  }

  async handleSubmit(e) {
    e.preventDefault();

    if (!this.feedbackForm.checkValidity()) {
      this.showMessage(
        "Пожалуйста, заполните все обязательные поля правильно",
        "error"
      );
      return;
    }

    const originalText = this.submitBtn.textContent;
    this.submitBtn.disabled = true;
    this.submitBtn.textContent = "Отправка...";

    try {
      const formData = new FormData(this.feedbackForm);

      const response = await fetch("https://formcarry.com/s/uy4ELqw-srU", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        this.showMessage(
          "Сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.",
          "success"
        );
        this.feedbackForm.reset();
        this.clearFormData();
      } else {
        throw new Error(result.message || "Ошибка отправки формы");
      }
    } catch (error) {
      console.error("Ошибка отправки формы:", error);
        this.showMessage(
          "Сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.",
          "success"
        );
    } finally {
      this.submitBtn.disabled = false;
      this.submitBtn.textContent = originalText;
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new FeedbackForm();
});
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
    this.reviews.forEach((review) => {
      review.classList.remove("active");
      review.style.opacity = "0";
      review.style.transform = "translateX(30px)";
    });

    this.dots.forEach((dot) => dot.classList.remove("active"));

    this.reviews[index].classList.add("active");
    this.dots[index].classList.add("active");

    setTimeout(() => {
      this.reviews[index].style.opacity = "1";
      this.reviews[index].style.transform = "translateX(0)";
    }, 50);

    this.currentIndex = index;
  }

  nextReview() {
    let nextIndex = this.currentIndex + 1;
    if (nextIndex >= this.reviews.length) {
      nextIndex = 0;
    }
    this.showReview(nextIndex);
  }

  prevReview() {
    let prevIndex = this.currentIndex - 1;
    if (prevIndex < 0) {
      prevIndex = this.reviews.length - 1;
    }
    this.showReview(prevIndex);
  }

  goToReview(index) {
    if (index >= 0 && index < this.reviews.length) {
      this.showReview(index);
    }
  }

  startAutoSlide() {
    this.stopAutoSlide();
    this.autoSlideInterval = setInterval(() => {
      this.nextReview();
    }, this.autoSlideDelay);
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
      carousel.addEventListener("touchstart", () => this.stopAutoSlide());
    }

    document.querySelectorAll(".nav-btn, .dot").forEach((element) => {
      element.addEventListener("click", () => {
        this.stopAutoSlide();
        setTimeout(() => this.startAutoSlide(), 10000);
      });
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") {
        this.prevReview();
        this.stopAutoSlide();
      } else if (e.key === "ArrowRight") {
        this.nextReview();
        this.stopAutoSlide();
      }
    });
  }
}

let reviewsCarousel;

function initReviewsCarousel() {
  reviewsCarousel = new ReviewsCarousel();
}

function nextReview() {
  if (reviewsCarousel) reviewsCarousel.nextReview();
}

function prevReview() {
  if (reviewsCarousel) reviewsCarousel.prevReview();
}

function goToReview(index) {
  if (reviewsCarousel) reviewsCarousel.goToReview(index);
}

document.addEventListener("DOMContentLoaded", initReviewsCarousel);

window.nextReview = nextReview;
window.prevReview = prevReview;
window.goToReview = goToReview;

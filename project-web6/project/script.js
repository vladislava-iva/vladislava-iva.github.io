/*const form = document.getElementById('feedbackForm');
const submitBtn = form.querySelector('button[type="submit"]');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(form);
    formData.append("access_key", "2f150c45-44a5-4083-affa-81d1de06b6ee");

    const originalText = submitBtn.textContent;

    submitBtn.textContent = "Sending...";
    submitBtn.disabled = true;

    try {
        const response = await fetch("https://api.web3forms.com/submit", {
            method: "POST",
            body: formData
        });

        const data = await response.json();

        if (response.ok) {
            alert("Success! Your message has been sent.");
            form.reset();
        } else {
            alert("Error: " + data.message);
        }

    } catch (error) {
        alert("Something went wrong. Please try again.");
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});*/
class FeedbackForm {
    constructor() {
        this.feedbackForm = document.getElementById('feedbackForm');
        this.submitBtn = document.getElementById('submitBtn');
        this.STORAGE_KEY = 'feedbackFormData';
        this.init();
    }

    init() {
        this.restoreFormData();
        this.feedbackForm.addEventListener('submit', (e) => this.handleSubmit(e));
        this.feedbackForm.addEventListener('input', () => this.saveFormData());
    }

    saveFormData() {
        const formData = {
            name: document.getElementById('field-name-1').value,
            phone: document.getElementById('phone').value,
            email: document.getElementById('field-email').value,
            comment: document.getElementById('field-name-2').value,
            agree: document.getElementById('agree').checked
        };
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(formData));
    }

    restoreFormData() {
        const savedData = localStorage.getItem(this.STORAGE_KEY);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                document.getElementById('field-name-1').value = data.name || '';
                document.getElementById('phone').value = data.phone || '';
                document.getElementById('field-email').value = data.email || '';
                document.getElementById('field-name-2').value = data.comment || '';
                document.getElementById('agree').checked = data.agree || false;
            } catch (error) {
                console.error('Ошибка восстановления данных:', error);
                this.clearFormData();
            }
        }
    }

    clearFormData() {
        localStorage.removeItem(this.STORAGE_KEY);
    }

    showMessage(message, type = 'success') {
        // Удаляем старое сообщение если есть
        const existingMessage = document.querySelector('.form-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `form-message alert alert-${type === 'success' ? 'success' : 'danger'} mt-3`;
        messageDiv.textContent = message;
        
        // Вставляем сообщение после формы
        this.feedbackForm.appendChild(messageDiv);

        // Автоудаление через 5 секунд
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        // Проверяем валидность формы
        if (!this.feedbackForm.checkValidity()) {
            this.showMessage('Пожалуйста, заполните все обязательные поля правильно', 'error');
            return;
        }

        const originalText = this.submitBtn.textContent;
        this.submitBtn.disabled = true;
        this.submitBtn.textContent = 'Отправка...';

        try {
            const formData = new FormData(this.feedbackForm);
            
            // Отправка данных через Web3Forms
            const response = await fetch('https://api.web3forms.com/submit', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showMessage('✅ Сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.', 'success');
                this.feedbackForm.reset();
                this.clearFormData();
            } else {
                throw new Error(result.message || 'Ошибка отправки формы');
            }

        } catch (error) {
            console.error('Ошибка отправки формы:', error);
            this.showMessage('❌ Произошла ошибка при отправке. Пожалуйста, попробуйте еще раз.', 'error');
        } finally {
            this.submitBtn.disabled = false;
            this.submitBtn.textContent = originalText;
        }
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    new FeedbackForm();
});
// Карусель отзывов
class ReviewsCarousel {
    constructor() {
        this.currentIndex = 0;
        this.reviews = document.querySelectorAll('.review-item');
        this.dots = document.querySelectorAll('.dot');
        this.autoSlideInterval = null;
        this.autoSlideDelay = 6000; // 6 секунд
        
        this.init();
    }
    
    init() {
        // Показываем первый отзыв
        this.showReview(this.currentIndex);
        
        // Добавляем обработчики событий
        this.addEventListeners();
        
        // Запускаем автопрокрутку
        this.startAutoSlide();
    }
    
    showReview(index) {
        // Скрываем все отзывы
        this.reviews.forEach(review => {
            review.classList.remove('active');
            review.style.opacity = '0';
            review.style.transform = 'translateX(30px)';
        });
        
        // Убираем активный класс у всех точек
        this.dots.forEach(dot => dot.classList.remove('active'));
        
        // Показываем выбранный отзыв
        this.reviews[index].classList.add('active');
        this.dots[index].classList.add('active');
        
        // Анимация появления
        setTimeout(() => {
            this.reviews[index].style.opacity = '1';
            this.reviews[index].style.transform = 'translateX(0)';
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
        // Останавливаем автопрокрутку при взаимодействии
        const carousel = document.getElementById('reviewsCarousel');
        if (carousel) {
            carousel.addEventListener('mouseenter', () => this.stopAutoSlide());
            carousel.addEventListener('mouseleave', () => this.startAutoSlide());
            carousel.addEventListener('touchstart', () => this.stopAutoSlide());
        }
        
        // Добавляем обработчики для стрелок и точек
        document.querySelectorAll('.nav-btn, .dot').forEach(element => {
            element.addEventListener('click', () => {
                this.stopAutoSlide();
                setTimeout(() => this.startAutoSlide(), 10000);
            });
        });
        
        // Добавляем обработчики клавиатуры
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                this.prevReview();
                this.stopAutoSlide();
            } else if (e.key === 'ArrowRight') {
                this.nextReview();
                this.stopAutoSlide();
            }
        });
    }
}

// Глобальные функции для вызова из HTML
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

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', initReviewsCarousel);

// Если используются стрелки в HTML с onclick
window.nextReview = nextReview;
window.prevReview = prevReview;
window.goToReview = goToReview;

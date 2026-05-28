/**
 * script.js — клиентская часть для работы с REST API
 */

const API_URL = 'http://u82419.kubsu-dev.ru/vladislava-iva.github.io/project-web/api.php';

function getAuthHeader() {
    const creds = sessionStorage.getItem('userCredentials');
    if (!creds) return null;
    return 'Basic ' + btoa(creds);
}

function showMessage(form, message, type = 'success') {
    const old = form.querySelector('.form-message');
    if (old) old.remove();
    
    const div = document.createElement('div');
    div.className = `form-message alert alert-${type === 'success' ? 'success' : 'danger'} mt-3`;
    div.style.cssText = type === 'success' 
        ? 'background: #d4edda; color: #155724; padding: 10px; margin-top: 15px; border-radius: 4px;'
        : 'background: #f8d7da; color: #721c24; padding: 10px; margin-top: 15px; border-radius: 4px;';
    div.textContent = message;
    form.appendChild(div);
    setTimeout(() => div.remove(), 6000);
}

// Панель авторизации
function createAuthUI() {
    const section = document.getElementById('form-section');
    if (!section) return;
    if (document.getElementById('auth-panel')) return;
    
    const panel = document.createElement('div');
    panel.id = 'auth-panel';
    panel.style.cssText = `
        background: #f8f9fa;
        border-left: 4px solid #6c5ce7;
        padding: 16px 20px;
        margin-bottom: 24px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    `;
    
    const container = section.querySelector('.container');
    if (container) {
        container.insertBefore(panel, container.firstChild);
    }
    renderAuthPanel();
}

function renderAuthPanel() {
    const panel = document.getElementById('auth-panel');
    if (!panel) return;
    
    const creds = sessionStorage.getItem('userCredentials');
    
    if (creds) {
        const login = creds.split(':')[0];
        panel.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <strong>👋 Вы вошли как:</strong> ${escapeHtml(login)}
                </div>
                <div>
                    <button id="btn-show-profile" style="padding:6px 14px;background:#6c5ce7;color:white;border:none;border-radius:4px;cursor:pointer;margin-right:8px;">📋 Мой профиль</button>
                    <button id="btn-logout" style="padding:6px 14px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;">🚪 Выйти</button>
                </div>
            </div>
            <div id="profile-block" style="display:none;margin-top:12px;padding:12px;background:#e9ecef;border-radius:4px;"></div>
        `;
        document.getElementById('btn-logout')?.addEventListener('click', logout);
        document.getElementById('btn-show-profile')?.addEventListener('click', loadProfile);
        
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) submitBtn.textContent = '🔄 ОБНОВИТЬ ДАННЫЕ';
    } else {
        panel.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div><strong>🔐 Уже есть аккаунт?</strong></div>
                <button id="btn-show-login" style="padding:6px 14px;background:#6c5ce7;color:white;border:none;border-radius:4px;cursor:pointer;">Войти</button>
            </div>
            <div id="login-form-inline" style="display:none;margin-top:12px;">
                <input id="il-login" type="text" placeholder="Логин" style="padding:8px;border:1px solid #ccc;border-radius:4px;margin-right:8px;width:150px;">
                <input id="il-pass" type="password" placeholder="Пароль" style="padding:8px;border:1px solid #ccc;border-radius:4px;margin-right:8px;width:150px;">
                <button id="btn-do-login" style="padding:8px 16px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;">OK</button>
                <span id="il-error" style="color:red;margin-left:8px;"></span>
            </div>
        `;
        document.getElementById('btn-show-login')?.addEventListener('click', () => {
            document.getElementById('login-form-inline').style.display = 'block';
        });
        document.getElementById('btn-do-login')?.addEventListener('click', doLogin);
        
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) submitBtn.textContent = '📨 ОТПРАВИТЬ';
    }
}

function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

async function doLogin() {
    const login = document.getElementById('il-login').value.trim();
    const pass = document.getElementById('il-pass').value;
    const errEl = document.getElementById('il-error');
    errEl.textContent = '';
    
    if (!login || !pass) {
        errEl.textContent = 'Введите логин и пароль.';
        return;
    }
    
    try {
        const res = await fetch(API_URL + '?action=profile', {
            headers: { 'Authorization': 'Basic ' + btoa(login + ':' + pass) }
        });
        const data = await res.json();
        if (data.success) {
            sessionStorage.setItem('userCredentials', login + ':' + pass);
            renderAuthPanel();
            if (data.profile) fillFormFromProfile(data.profile);
            showMessage(document.getElementById('feedbackForm'), '✅ Вход выполнен успешно!', 'success');
        } else {
            errEl.textContent = 'Неверный логин или пароль.';
        }
    } catch (e) {
        errEl.textContent = 'Ошибка соединения.';
    }
}

function logout() {
    sessionStorage.removeItem('userCredentials');
    renderAuthPanel();
    document.getElementById('feedbackForm')?.reset();
}

async function loadProfile() {
    const block = document.getElementById('profile-block');
    block.style.display = 'block';
    block.innerHTML = 'Загрузка...';
    
    try {
        const res = await fetch(API_URL + '?action=profile', {
            headers: { 'Authorization': getAuthHeader() }
        });
        const data = await res.json();
        if (data.success && data.profile) {
            const p = data.profile;
            block.innerHTML = `
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:4px 0;"><strong>Логин:</strong></td><td>${escapeHtml(p.login)}</td></tr>
                    <tr><td style="padding:4px 0;"><strong>Имя:</strong></td><td>${escapeHtml(p.name)}</td></tr>
                    <tr><td style="padding:4px 0;"><strong>Email:</strong></td><td>${escapeHtml(p.email)}</td></tr>
                    <tr><td style="padding:4px 0;"><strong>Телефон:</strong></td><td>${escapeHtml(p.phone || '—')}</td></tr>
                    <tr><td style="padding:4px 0;"><strong>Комментарий:</strong></td><td>${escapeHtml(p.comment || '—')}</td></tr>
                </table>
            `;
            fillFormFromProfile(p);
        } else {
            block.innerHTML = 'Не удалось загрузить профиль.';
        }
    } catch (e) {
        block.innerHTML = 'Ошибка соединения.';
    }
}

function fillFormFromProfile(profile) {
    const nameInput = document.getElementById('field-name-1');
    const emailInput = document.getElementById('field-email');
    const phoneInput = document.getElementById('phone');
    const commentInput = document.getElementById('field-name-2');
    
    if (nameInput) nameInput.value = profile.name || '';
    if (emailInput) emailInput.value = profile.email || '';
    if (phoneInput) phoneInput.value = profile.phone || '';
    if (commentInput) commentInput.value = profile.comment || '';
}

// Класс формы
class FeedbackForm {
    constructor() {
        this.form = document.getElementById('feedbackForm');
        this.submitBtn = document.getElementById('submitBtn');
        if (!this.form) return;
        this.init();
    }
    
    init() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        this.restoreFormData();
        this.form.addEventListener('input', () => this.saveFormData());
    }
    
    saveFormData() {
        const data = {
            name: document.getElementById('field-name-1')?.value || '',
            phone: document.getElementById('phone')?.value || '',
            email: document.getElementById('field-email')?.value || '',
            comment: document.getElementById('field-name-2')?.value || ''
        };
        try { localStorage.setItem('feedbackFormData', JSON.stringify(data)); } catch(e) {}
    }
    
    restoreFormData() {
        try {
            const saved = localStorage.getItem('feedbackFormData');
            if (!saved) return;
            const data = JSON.parse(saved);
            const nameInput = document.getElementById('field-name-1');
            if (nameInput && data.name) nameInput.value = data.name;
            const phoneInput = document.getElementById('phone');
            if (phoneInput && data.phone) phoneInput.value = data.phone;
            const emailInput = document.getElementById('field-email');
            if (emailInput && data.email) emailInput.value = data.email;
            const commentInput = document.getElementById('field-name-2');
            if (commentInput && data.comment) commentInput.value = data.comment;
        } catch(e) {}
    }
    
    collectData() {
        return {
            name: document.getElementById('field-name-1')?.value.trim() || '',
            phone: document.getElementById('phone')?.value.trim() || '',
            email: document.getElementById('field-email')?.value.trim() || '',
            comment: document.getElementById('field-name-2')?.value.trim() || ''
        };
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        if (!this.form.checkValidity()) {
            showMessage(this.form, 'Пожалуйста, заполните все обязательные поля.', 'error');
            return;
        }
        
        const isLoggedIn = !!sessionStorage.getItem('userCredentials');
        const method = isLoggedIn ? 'PUT' : 'POST';
        const headers = { 'Content-Type': 'application/json' };
        if (isLoggedIn) headers['Authorization'] = getAuthHeader();
        
        const originalText = this.submitBtn?.textContent || 'Отправить';
        if (this.submitBtn) {
            this.submitBtn.disabled = true;
            this.submitBtn.textContent = 'Отправка...';
        }
        
        try {
            const response = await fetch(API_URL, { method, headers, body: JSON.stringify(this.collectData()) });
            const result = await response.json();
            
            if (result.login && result.password) {
                showMessage(this.form, `✅ Заявка принята! Логин: ${result.login} | Пароль: ${result.password} — сохраните их!`, 'success');
                sessionStorage.setItem('userCredentials', result.login + ':' + result.password);
                renderAuthPanel();
                this.form.reset();
                try { localStorage.removeItem('feedbackFormData'); } catch(e) {}
            } else if (result.success) {
                showMessage(this.form, '✅ Данные успешно обновлены!', 'success');
                this.form.reset();
                try { localStorage.removeItem('feedbackFormData'); } catch(e) {}
            } else if (result.errors) {
                const errorMsg = Array.isArray(result.errors) ? result.errors.join('. ') : Object.values(result.errors).join('. ');
                showMessage(this.form, '❌ ' + errorMsg, 'error');
            } else if (result.message) {
                showMessage(this.form, '❌ ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Ошибка отправки:', error);
            showMessage(this.form, '❌ Ошибка соединения с сервером.', 'error');
        } finally {
            if (this.submitBtn) {
                this.submitBtn.disabled = false;
                this.submitBtn.textContent = originalText;
            }
        }
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    new FeedbackForm();
    createAuthUI();
});

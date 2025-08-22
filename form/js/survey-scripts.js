/**
 * Survey Scripts 
 * ./form/js/survey-scripts.js
 */

window.SURVEY_CONFIG = window.SURVEY_CONFIG || {
    API_BASE_URL: '../api/',
    SURVEY_TYPE: 'default',
    TOTAL_QUESTIONS: 50
};


function selectOption(element, questionName, value) {
    const radio = element.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
        
        const allOptions = document.querySelectorAll(`input[name="${questionName}"]`);
        allOptions.forEach(r => {
            r.closest('.option').classList.remove('selected');
        });
        
        element.classList.add('selected');
        updateProgress();
        hideMessages();
        scrollToNextBlock(questionName);
    }
}

function updateProgress() {
    const checkedInputs = document.querySelectorAll('input[type="radio"]:checked');
    const progress = (checkedInputs.length / window.SURVEY_CONFIG.TOTAL_QUESTIONS) * 100;
    
    const progressBar = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    
    if (progressBar) progressBar.style.width = progress + '%';
    if (progressText) progressText.textContent = `${checkedInputs.length} з ${window.SURVEY_CONFIG.TOTAL_QUESTIONS} питань`;
}

function hideMessages() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    if (successMessage) successMessage.classList.add('hidden');
    if (errorMessage) errorMessage.classList.add('hidden');
}

function showError(message) {
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    if (errorText) errorText.textContent = message;
    if (errorMessage) {
        errorMessage.classList.remove('hidden');
        errorMessage.scrollIntoView({ behavior: 'smooth' });
    }
}

function showSuccess(responseId) {
    const successMessage = document.getElementById('successMessage');
    const responseIdSpan = document.getElementById('responseId');
    if (responseIdSpan) responseIdSpan.textContent = responseId;
    if (successMessage) {
        successMessage.classList.remove('hidden');
        successMessage.scrollIntoView({ behavior: 'smooth' });
    }
}

function validateForm() {
    const checkedInputs = document.querySelectorAll('input[type="radio"]:checked');
    const minRequired = Math.floor(window.SURVEY_CONFIG.TOTAL_QUESTIONS * 0.8);
    
    if (checkedInputs.length < minRequired) {
        showError(`Будь ласка, дайте відповідь принаймні на ${minRequired} питань (80% від загальної кількості)`);
        return false;
    }
    return true;
}

function scrollToNextBlock(currentQuestion) {
    const questionNumber = parseInt(currentQuestion.match(/\d+/)[0]);
    const nextQuestionBlock = document.querySelector(`input[name="q${questionNumber + 1}"]`);
    if (nextQuestionBlock) {
        setTimeout(() => {
            nextQuestionBlock.closest('.question').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 200);
    }
}

function generateResponseId(surveyType) {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substr(2, 5);
    return `${surveyType.toUpperCase()}_${timestamp}_${random}`;
}


function clearSurveyForm() {
    try {
        // Очистить localStorage
        const key = `survey_progress_${window.SURVEY_CONFIG.SURVEY_TYPE}`;
        localStorage.removeItem(key);
        
        // Очистить все radio buttons
        const allRadios = document.querySelectorAll('input[type="radio"]');
        allRadios.forEach(radio => {
            radio.checked = false;
        });
        
        // Убрать все selected классы
        const allOptions = document.querySelectorAll('.option.selected');
        allOptions.forEach(option => {
            option.classList.remove('selected');
        });
        
        // Сбросить и скрыть прогресс бар
        const progressContainer = document.querySelector('.progress-container');
        const progressBar = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        if (progressBar) {
            progressBar.style.width = '0%';
        }
        
        if (progressText) {
            progressText.textContent = `0 з ${window.SURVEY_CONFIG.TOTAL_QUESTIONS} питань`;
        }
        
        // Скрыть весь контейнер прогресса после сохранения
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
        
        // Скрыть форму
        const surveyForm = document.getElementById('surveyForm');
        if (surveyForm) {
            surveyForm.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Помилка очищення форми:', error);
    }
}


async function submitSurvey(event) {
    if (event) event.preventDefault();
    
    hideMessages();
    
    if (!validateForm()) {
        return false;
    }
    
    const submitBtn = document.getElementById('submitBtn');
    const spinner = document.getElementById('spinner');
    const btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
    
    // UI состояние загрузки
    if (submitBtn) submitBtn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
    if (btnText) btnText.textContent = 'Відправка...';
    
    try {
        // Собрать данные формы
        const formData = new FormData(document.getElementById('surveyForm'));
        const responses = {};
        
        for (let [key, value] of formData.entries()) {
            responses[key] = value;
        }
        
        const submissionData = {
            survey_type: window.SURVEY_CONFIG.SURVEY_TYPE,
            responses: responses,
            timestamp: new Date().toISOString(),
            user_agent: navigator.userAgent,
            total_questions: window.SURVEY_CONFIG.TOTAL_QUESTIONS,
            answered_questions: Object.keys(responses).length,
            completion_percentage: Math.round((Object.keys(responses).length / window.SURVEY_CONFIG.TOTAL_QUESTIONS) * 100)
        };
        
        const apiUrl = window.SURVEY_CONFIG.API_BASE_URL + 'save-survey.php';
        
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(submissionData)
        });
        
        if (response.ok) {
            const result = await response.json();
            
            if (result.success) {
                
                clearSurveyForm();
                showSuccess(result.response_id || generateResponseId(submissionData.survey_type));
            } else {
                showError(result.message || 'Помилка збереження даних');
            }
        } else {
            throw new Error(`HTTP Error: ${response.status}`);
        }
        
    } catch (error) {
        console.error('Помилка відправки:', error);
        
        clearSurveyForm();
        showSuccess(generateResponseId(window.SURVEY_CONFIG.SURVEY_TYPE));
        
    } finally {
        
        if (submitBtn) submitBtn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
        if (btnText) btnText.textContent = 'Надіслати відповіді';
    }
    
    return false;
}

function saveProgress() {
    const responses = {};
    const checkedInputs = document.querySelectorAll('input[type="radio"]:checked');
    
    checkedInputs.forEach(input => {
        responses[input.name] = input.value;
    });
    
    try {
        const key = `survey_progress_${window.SURVEY_CONFIG.SURVEY_TYPE}`;
        const data = {
            responses: responses,
            timestamp: new Date().toISOString()
        };
        localStorage.setItem(key, JSON.stringify(data));
    } catch (e) {
        console.error('Не вдалося зберегти прогрес:', e);
    }
}

function loadProgress() {
    try {
        const saved = localStorage.getItem(`survey_progress_${window.SURVEY_CONFIG.SURVEY_TYPE}`);
        if (saved) {
            const data = JSON.parse(saved);
            const responses = data.responses;
            
            Object.entries(responses).forEach(([questionName, value]) => {
                const radio = document.querySelector(`input[name="${questionName}"][value="${value}"]`);
                if (radio) {
                    radio.checked = true;
                    radio.closest('.option').classList.add('selected');
                }
            });
            
            updateProgress();
            
            if (Object.keys(responses).length > 0) {
                console.log(`Відновлено прогрес: ${Object.keys(responses).length} відповідей`);
            }
        }
    } catch (e) {
        console.error('Не вдалося завантажити прогрес:', e);
    }
}

function setupEventListeners() {
    const surveyForm = document.getElementById('surveyForm');
    if (surveyForm) {
        surveyForm.addEventListener('submit', submitSurvey);
    }
    
    const radioButtons = document.querySelectorAll('input[type="radio"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', updateProgress);
    });
    
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            if (this.type !== 'submit') {
                e.preventDefault();
                submitSurvey();
            }
        });
    }
}

function enableAutoSave() {
    setInterval(saveProgress, 30000);
    
    document.addEventListener('change', function(e) {
        if (e.target.type === 'radio') {
            setTimeout(saveProgress, 100);
        }
    });
    
    window.addEventListener('beforeunload', saveProgress);
}

function initializeSurvey() {
    loadProgress();
    setupEventListeners();
    enableAutoSave();
    updateProgress();
    console.log('Анкету ініціалізовано:', window.SURVEY_CONFIG.SURVEY_TYPE);
}

document.addEventListener('DOMContentLoaded', initializeSurvey);

window.SurveyUtils = {
    selectOption,
    updateProgress,
    hideMessages,
    showError,
    showSuccess,
    validateForm,
    submitSurvey,
    saveProgress,
    loadProgress,
    clearSurveyForm
};
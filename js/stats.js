/**
 * Script for downloading and displaying survey statistics
 * ./js/stats.js
 */

// Global variable for tracking downloads
let statsLoaded = false;


async function loadGeneralStats(refreshParam = '') {
  try {
    console.log('Loading general stats...');
    const response = await fetch(`api/public-stats.php?action=overview${refreshParam}`);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    console.log('General stats result:', result);
    
    if (!result.success) {
      document.getElementById('generalStats').innerHTML = 
        `<div class="error-message">❌ Помилка: ${result.error || result.message}</div>`;
      return;
    }
    
    const overview = result.data.overview;
    const daily_activity = result.data.daily_activity || [];
    
    let html = `
      <div class="stats-summary">
        <div class="stat-item">
          <div class="stat-number">${overview.total_responses || 0}</div>
          <div class="stat-label">Загальна кількість відповідей</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">${Math.round(overview.avg_completion || 0)}%</div>
          <div class="stat-label">Середня повнота заповнення</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">${overview.completed_responses || 0}</div>
          <div class="stat-label">Завершених анкет</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">${overview.active_days || 0}</div>
          <div class="stat-label">Активних днів</div>
        </div>
      </div>
      <div class="stats-info">
        <p><strong>📅 Перша відповідь:</strong> ${overview.first_response ? new Date(overview.first_response).toLocaleDateString('uk-UA') : 'Н/Д'}</p>
        <p><strong>📅 Остання відповідь:</strong> ${overview.last_response ? new Date(overview.last_response).toLocaleDateString('uk-UA') : 'Н/Д'}</p>
        <p><strong>🕐 Оновлено:</strong> ${new Date(result.data.generated_at).toLocaleString('uk-UA')}</p>
      </div>
    `;
    
    if (daily_activity.length > 0) {
      html += '<div class="daily-activity-chart">';
      const maxCount = Math.max(...daily_activity.map(d => d.daily_count));
      
      daily_activity.forEach(day => {
        const percentage = maxCount > 0 ? (day.daily_count / maxCount) * 100 : 0;
        html += `
          <div class="daily-bar">
            <div class="daily-date">${new Date(day.response_date).toLocaleDateString('uk-UA')}</div>
            <div class="daily-bar-container">
              <div class="daily-bar-fill" style="width: ${percentage}%"></div>
            </div>
            <div class="daily-count">${day.daily_count}</div>
          </div>
        `;
      });
      html += '</div>';
    }
    
    document.getElementById('generalStats').innerHTML = html;
    
  } catch (error) {
    console.error('Error loading general stats:', error);
    document.getElementById('generalStats').innerHTML = 
      `<div class="error-message">❌ Помилка завантаження загальної статистики: ${error.message}</div>`;
  }
}


async function loadSurveyTypeStats(refreshParam = '') {
  try {
    console.log('Loading survey type stats...');
    const response = await fetch(`api/public-stats.php?action=survey_types${refreshParam}`);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    console.log('Survey types result:', result);
    
    if (!result.success) {
      document.getElementById('surveyTypeStats').innerHTML = 
        `<div class="error-message">❌ Помилка: ${result.error || result.message}</div>`;
      return;
    }
    
    const types = result.data;
    let html = '<div class="survey-types-grid">';
    
    types.forEach(type => {
      const completionRate = type.total_responses > 0 ? 
        Math.round((type.completed_responses / type.total_responses) * 100) : 0;
      
      html += `
        <div class="survey-type-card">
          <div class="survey-type-header">
            <h4>${type.name}</h4>
            <div class="response-count">${type.total_responses} відповідей</div>
          </div>
          <div class="survey-type-stats">
            <div class="stat-row">
              <span>📝 Питань:</span>
              <span>${type.total_questions}</span>
            </div>
            <div class="stat-row">
              <span>✅ Завершено:</span>
              <span>${type.completed_responses} (${completionRate}%)</span>
            </div>
            <div class="stat-row">
              <span>📊 Середня повнота:</span>
              <span>${Math.round(type.avg_completion || 0)}%</span>
            </div>
            ${type.last_response ? `
            <div class="stat-row">
              <span>📅 Остання відповідь:</span>
              <span>${new Date(type.last_response).toLocaleDateString('uk-UA')}</span>
            </div>
            ` : ''}
          </div>
        </div>
      `;
    });
    
    html += '</div>';
    document.getElementById('surveyTypeStats').innerHTML = html;
    
  } catch (error) {
    console.error('Error loading survey type stats:', error);
    document.getElementById('surveyTypeStats').innerHTML = 
      `<div class="error-message">❌ Помилка завантаження статистики по типах анкет: ${error.message}</div>`;
  }
}


async function loadDailyActivity(refreshParam = '') {
  try {
    console.log('Loading daily activity...');
    const response = await fetch(`api/public-stats.php?action=overview${refreshParam}`);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    console.log('Daily activity result:', result);
    
    if (!result.success) {
      document.getElementById('dailyActivity').innerHTML = 
        `<div class="error-message">❌ Помилка: ${result.error || result.message}</div>`;
      return;
    }
    
    const dailyStats = result.data.daily_activity || [];
    
    if (dailyStats.length === 0) {
      document.getElementById('dailyActivity').innerHTML = 
        '<div class="no-data">📭 Немає даних за останні дні</div>';
      return;
    }
    
    let html = '<div class="daily-activity-chart">';
    const maxCount = Math.max(...dailyStats.map(d => d.daily_count));
    
    dailyStats.forEach(day => {
      const percentage = maxCount > 0 ? (day.daily_count / maxCount) * 100 : 0;
      html += `
        <div class="daily-bar">
          <div class="daily-date">${new Date(day.response_date).toLocaleDateString('uk-UA')}</div>
          <div class="daily-bar-container">
            <div class="daily-bar-fill" style="width: ${percentage}%"></div>
          </div>
          <div class="daily-count">${day.daily_count}</div>
        </div>
      `;
    });
    
    html += '</div>';
    document.getElementById('dailyActivity').innerHTML = html;
    
  } catch (error) {
    console.error('Error loading daily activity:', error);
    document.getElementById('dailyActivity').innerHTML = 
      `<div class="error-message">❌ Помилка завантаження активності: ${error.message}</div>`;
  }
}


async function refreshAllStats(forceRefresh = false) {
  const refreshParam = forceRefresh ? '&refresh=1' : '';
  
  document.getElementById('generalStats').innerHTML = '<div class="loading-text">🔄 Оновлення загальної статистики...</div>';
  document.getElementById('surveyTypeStats').innerHTML = '<div class="loading-text">🔄 Оновлення статистики по типах...</div>';
  document.getElementById('dailyActivity').innerHTML = '<div class="loading-text">🔄 Оновлення активності...</div>';
  
  try {
    await Promise.all([
      loadGeneralStats(refreshParam),
      loadSurveyTypeStats(refreshParam),
      loadDailyActivity(refreshParam)
    ]);
    
    if (forceRefresh) {
      showUpdateNotification();
    }
  } catch (error) {
    console.error('Error refreshing stats:', error);
  }
}


function showUpdateNotification() {
  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    font-size: 14px;
    animation: slideIn 0.3s ease-out;
  `;
  notification.innerHTML = '✅ Статистика оновлена!';
  
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  `;
  document.head.appendChild(style);
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideIn 0.3s ease-out reverse';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
      if (style.parentNode) {
        style.parentNode.removeChild(style);
      }
    }, 300);
  }, 3000);
}


function initStatsModule() {
  if (typeof window.viewPage === 'function') {
    const originalViewPage = window.viewPage;
    
    window.viewPage = function(evt, pageName) {
      originalViewPage(evt, pageName);
      
      if (pageName === 'Reports' && !statsLoaded) {
        statsLoaded = true;
        setTimeout(() => {
          refreshAllStats();
        }, 500);
      }
    };
  }
  
  // Making the refreshAllStats function globally available
  window.refreshAllStats = refreshAllStats;
  
  console.log('Stats module initialized');
}

document.addEventListener('DOMContentLoaded', initStatsModule);

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    loadGeneralStats,
    loadSurveyTypeStats,
    loadDailyActivity,
    refreshAllStats
  };
}
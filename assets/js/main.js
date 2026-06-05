'use strict';

/* ============================================================
   GovExam Portal — main.js
   ============================================================ */

/**
 * Get CSRF token from the meta tag
 * @returns {string}
 */
function getCsrfToken() {
  var meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

/* ============================================================
   Toast Notification System
   ============================================================ */

(function () {
  var container = null;

  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  var iconMap = {
    success: 'fa-circle-check',
    danger:  'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info'
  };

  /**
   * Show a toast notification
   * @param {string} message
   * @param {string} type  success|danger|warning|info
   * @param {number} duration  ms
   */
  window.showToast = function (message, type, duration) {
    type     = type     || 'success';
    duration = duration || 4000;

    var c   = getContainer();
    var div = document.createElement('div');
    div.className = 'toast-item toast-' + type;

    var icon = iconMap[type] || 'fa-circle-info';
    div.innerHTML =
      '<i class="fa-solid ' + icon + ' toast-icon"></i>' +
      '<span class="toast-msg">' + escapeHtml(message) + '</span>' +
      '<button class="toast-close" aria-label="Close">&times;</button>';

    c.appendChild(div);

    div.querySelector('.toast-close').addEventListener('click', function () {
      hideToast(div);
    });

    var timer = setTimeout(function () { hideToast(div); }, duration);

    div.addEventListener('mouseenter', function () { clearTimeout(timer); });
    div.addEventListener('mouseleave', function () {
      timer = setTimeout(function () { hideToast(div); }, 1500);
    });
  };

  function hideToast(div) {
    div.classList.add('toast-hiding');
    div.addEventListener('animationend', function () {
      if (div.parentNode) div.parentNode.removeChild(div);
    }, { once: true });
  }
})();

/* ============================================================
   HTML Escape Utility
   ============================================================ */

function escapeHtml(str) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str)));
  return d.innerHTML;
}

/* ============================================================
   AJAX Helper
   ============================================================ */

/**
 * Post JSON data to a URL
 * @param {string}   url
 * @param {Object}   data
 * @param {Function} callback  fn(err, responseData)
 */
function ajaxPost(url, data, callback) {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', url, true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

  xhr.onload = function () {
    if (xhr.status >= 200 && xhr.status < 300) {
      try {
        var json = JSON.parse(xhr.responseText);
        callback(null, json);
      } catch (e) {
        callback(null, xhr.responseText);
      }
    } else {
      callback(new Error('HTTP ' + xhr.status), null);
    }
  };

  xhr.onerror = function () {
    callback(new Error('Network error'), null);
  };

  xhr.send(JSON.stringify(data));
}

/**
 * Post FormData to a URL
 * @param {string}   url
 * @param {FormData} formData
 * @param {Function} callback
 */
function ajaxPostForm(url, formData, callback) {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', url, true);
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

  xhr.onload = function () {
    if (xhr.status >= 200 && xhr.status < 300) {
      try {
        var json = JSON.parse(xhr.responseText);
        callback(null, json);
      } catch (e) {
        callback(null, xhr.responseText);
      }
    } else {
      callback(new Error('HTTP ' + xhr.status), null);
    }
  };

  xhr.onerror = function () {
    callback(new Error('Network error'), null);
  };

  xhr.send(formData);
}

/* ============================================================
   Debounce
   ============================================================ */

function debounce(fn, delay) {
  var timer = null;
  return function () {
    var args = arguments;
    var ctx  = this;
    clearTimeout(timer);
    timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
  };
}

/* ============================================================
   Live Search
   ============================================================ */

function initLiveSearch() {
  // Modal search (navbar)
  var input = document.getElementById('globalSearch');
  if (input) {
    var resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
      attachSearch(input, resultsContainer, false);
    }
  }

  // Homepage hero search
  var heroInput = document.getElementById('global-search');
  if (heroInput) {
    var heroDropdown = document.getElementById('search-results-dropdown');
    if (heroDropdown) {
      attachSearch(heroInput, heroDropdown, true);
    }
  }
}

function attachSearch(input, resultsContainer, isDropdown) {
  var typeIcons = {
    notification: 'fa-solid fa-bell text-primary',
    blog: 'fa-solid fa-newspaper text-info'
  };

  var doSearch = debounce(function (query) {
    if (query.length < 3) {
      resultsContainer.innerHTML = '';
      if (isDropdown) resultsContainer.style.display = 'none';
      return;
    }

    if (isDropdown) resultsContainer.style.display = 'block';
    resultsContainer.innerHTML = '<div class="text-muted small p-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Searching...</div>';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/api/search.php?q=' + encodeURIComponent(query), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function () {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var data = JSON.parse(xhr.responseText);
          renderSearchResults(data, resultsContainer, query, isDropdown, typeIcons);
        } catch (e) {
          resultsContainer.innerHTML = '<div class="text-muted small p-3 text-center">Search error.</div>';
        }
      } else {
        resultsContainer.innerHTML = '<div class="text-muted small p-3 text-center">Search error.</div>';
      }
    };
    xhr.onerror = function () {
      resultsContainer.innerHTML = '<div class="text-muted small p-3 text-center">Network error.</div>';
    };
    xhr.send();
  }, 300);

  input.addEventListener('input', function () {
    doSearch(this.value.trim());
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      resultsContainer.innerHTML = '';
      if (isDropdown) resultsContainer.style.display = 'none';
    }
  });

  // Hide on outside click for dropdowns
  if (isDropdown) {
    document.addEventListener('click', function (e) {
      if (!input.contains(e.target) && !resultsContainer.contains(e.target)) {
        resultsContainer.style.display = 'none';
      }
    });
    input.addEventListener('focus', function () {
      if (resultsContainer.innerHTML.trim() !== '' && this.value.trim().length >= 3) {
        resultsContainer.style.display = 'block';
      }
    });
  }
}

function renderSearchResults(data, container, query, isDropdown, typeIcons) {
  if (!data || !Array.isArray(data) || data.length === 0) {
    container.innerHTML = '<div class="text-muted small p-3 text-center">No results found for "<strong>' + escapeHtml(query) + '</strong>"</div>';
    if (isDropdown) container.style.display = 'block';
    return;
  }

  var html = '<div class="list-group list-group-flush">';
  data.forEach(function (item) {
    var icon = typeIcons[item.type] || 'fa-solid fa-link text-secondary';
    html += '<a href="' + escapeHtml(item.url) + '" class="list-group-item list-group-item-action py-2">' +
            '<div class="d-flex align-items-center gap-2">' +
            '<i class="' + icon + '"></i>' +
            '<div class="flex-grow-1">' +
            '<div class="fw-semibold small">' + escapeHtml(item.title) + '</div>' +
            (item.excerpt ? '<div class="text-muted" style="font-size:0.75rem;">' + escapeHtml(item.excerpt) + '</div>' : '') +
            '</div>' +
            '<span class="badge bg-primary-light text-primary" style="font-size:0.65rem;">' + escapeHtml(item.badge || item.type || '') + '</span>' +
            '</div>' +
            '</a>';
  });
  html += '</div>';
  container.innerHTML = html;
  if (isDropdown) container.style.display = 'block';
}

/* ============================================================
   Skeleton Loader
   ============================================================ */

function showSkeletons(container, count) {
  if (!container) return;
  count = count || 6;
  var html = '';
  for (var i = 0; i < count; i++) {
    html += '<div class="col">' +
            '<div class="skeleton-card">' +
            '<div class="skeleton skeleton-thumb"></div>' +
            '<div class="skeleton skeleton-title"></div>' +
            '<div class="skeleton skeleton-line w-75"></div>' +
            '<div class="skeleton skeleton-line w-50"></div>' +
            '</div>' +
            '</div>';
  }
  container.innerHTML = html;
}

function hideSkeletons(container) {
  if (container) container.innerHTML = '';
}

/* ============================================================
   Countdown Timer
   ============================================================ */

var CountdownTimer = (function () {
  function CountdownTimer(totalSeconds, onTick, onComplete) {
    this.totalSeconds = totalSeconds;
    this.remaining   = totalSeconds;
    this.onTick      = onTick      || function () {};
    this.onComplete  = onComplete  || function () {};
    this._interval   = null;
    this._running    = false;
  }

  CountdownTimer.prototype.start = function () {
    if (this._running) return;
    this._running = true;
    var self = this;

    self.onTick(self.remaining);

    self._interval = setInterval(function () {
      self.remaining--;
      if (self.remaining <= 0) {
        self.remaining = 0;
        self.stop();
        self.onTick(self.remaining);
        self.onComplete();
      } else {
        self.onTick(self.remaining);
      }
    }, 1000);
  };

  CountdownTimer.prototype.stop = function () {
    if (this._interval) clearInterval(this._interval);
    this._interval = null;
    this._running  = false;
  };

  CountdownTimer.prototype.pause = function () {
    this.stop();
  };

  CountdownTimer.prototype.resume = function () {
    this.start();
  };

  CountdownTimer.prototype.formatTime = function (seconds) {
    seconds = seconds || this.remaining;
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    return (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
  };

  CountdownTimer.prototype.getRemaining = function () {
    return this.remaining;
  };

  CountdownTimer.prototype.isRunning = function () {
    return this._running;
  };

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  return CountdownTimer;
})();

/* ============================================================
   Fullscreen API
   ============================================================ */

function enterFullscreen(elem) {
  elem = elem || document.documentElement;
  if (elem.requestFullscreen)            return elem.requestFullscreen();
  if (elem.webkitRequestFullscreen)      return elem.webkitRequestFullscreen();
  if (elem.mozRequestFullScreen)         return elem.mozRequestFullScreen();
  if (elem.msRequestFullscreen)          return elem.msRequestFullscreen();
}

function exitFullscreen() {
  if (document.exitFullscreen)           return document.exitFullscreen();
  if (document.webkitExitFullscreen)     return document.webkitExitFullscreen();
  if (document.mozCancelFullScreen)      return document.mozCancelFullScreen();
  if (document.msExitFullscreen)         return document.msExitFullscreen();
}

function isFullscreen() {
  return !!(
    document.fullscreenElement ||
    document.webkitFullscreenElement ||
    document.mozFullScreenElement ||
    document.msFullscreenElement
  );
}

/* ============================================================
   Question Palette
   ============================================================ */

var questionStatuses = {};

/**
 * Update a question's palette status
 * @param {number} questionNum   1-based
 * @param {string} status        unattempted|answered|marked|marked-answered
 */
function updatePalette(questionNum, status) {
  questionStatuses[questionNum] = status;
  var btn = document.querySelector('[data-q="' + questionNum + '"]');
  if (!btn) return;
  btn.className = 'btn-q ' + status;
}

/**
 * Set the "current" question in the palette
 * @param {number} questionNum
 */
function setCurrentQuestion(questionNum) {
  var prev = document.querySelector('.btn-q.current');
  if (prev) {
    var pNum = parseInt(prev.dataset.q);
    if (questionStatuses[pNum]) {
      prev.className = 'btn-q ' + questionStatuses[pNum];
    } else {
      prev.className = 'btn-q unattempted';
    }
  }
  var btn = document.querySelector('[data-q="' + questionNum + '"]');
  if (btn) btn.classList.add('current');
}

/* ============================================================
   Auto-Save Answers
   ============================================================ */

var autoSaveInterval = null;

/**
 * Initialize auto-save for exam answers
 * @param {number} testId
 * @param {number} interval  milliseconds
 */
function initAutoSave(testId, interval) {
  interval = interval || 30000;
  if (autoSaveInterval) clearInterval(autoSaveInterval);

  autoSaveInterval = setInterval(function () {
    var answers = collectAnswers();
    if (!answers) return;

    var fd = new FormData();
    fd.append('test_id',    testId);
    fd.append('answers',    JSON.stringify(answers));
    fd.append('csrf_token', getCsrfToken());

    ajaxPostForm('/api/autosave.php', fd, function (err, data) {
      if (err) {
        console.warn('Auto-save failed:', err);
      }
    });
  }, interval);
}

/**
 * Collect answers from the page
 * @returns {Object}
 */
function collectAnswers() {
  var answers = {};
  var radios  = document.querySelectorAll('input[type="radio"]:checked[name^="q_"]');
  radios.forEach(function (r) {
    var match = r.name.match(/^q_(\d+)$/);
    if (match) {
      answers[match[1]] = r.value;
    }
  });
  return answers;
}

/* ============================================================
   Tab Switch Detection
   ============================================================ */

/**
 * Detect when user switches tabs/windows during exam
 * @param {number}   maxSwitches
 * @param {Function} onViolation  fn(switchCount)
 * @param {Function} onAutoSubmit fn()
 */
function initTabSwitchDetection(maxSwitches, onViolation, onAutoSubmit) {
  maxSwitches  = maxSwitches  || 3;
  onViolation  = onViolation  || function () {};
  onAutoSubmit = onAutoSubmit || function () {};

  var switchCount = 0;

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      switchCount++;
      onViolation(switchCount);

      if (switchCount >= maxSwitches) {
        onAutoSubmit();
      }
    }
  });

  window.addEventListener('blur', function () {
    switchCount++;
    onViolation(switchCount);

    if (switchCount >= maxSwitches) {
      onAutoSubmit();
    }
  });
}

/* ============================================================
   Admin Settings Save
   ============================================================ */

/**
 * Save settings group via AJAX
 * @param {string} group  Setting group name
 */
function saveSettings(group) {
  var form = document.getElementById('settings-form-' + group);
  if (!form) {
    showToast('Settings form not found', 'danger');
    return;
  }

  var btn = form.querySelector('[type="submit"]');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';
  }

  var fd = new FormData(form);
  fd.append('group', group);

  ajaxPostForm('/admin/ajax/save-settings.php', fd, function (err, data) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save ' + group.charAt(0).toUpperCase() + group.slice(1);
    }

    if (err || !data) {
      showToast('Failed to save settings', 'danger');
      return;
    }

    if (data.success) {
      showToast(data.message || 'Settings saved successfully', 'success');
    } else {
      showToast(data.message || 'Failed to save settings', 'danger');
    }
  });
}

/* ============================================================
   Confirm Modal
   ============================================================ */

/**
 * Show a Bootstrap confirm modal
 * @param {string}   message
 * @param {Function} onConfirm
 */
function confirmAction(message, onConfirm) {
  var existing = document.getElementById('confirmModal');
  if (existing) existing.remove();

  var modal = document.createElement('div');
  modal.id        = 'confirmModal';
  modal.className = 'modal fade confirm-modal';
  modal.setAttribute('tabindex', '-1');
  modal.innerHTML =
    '<div class="modal-dialog modal-dialog-centered">' +
    '<div class="modal-content">' +
    '<div class="modal-header">' +
    '<h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Confirm Action</h5>' +
    '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
    '</div>' +
    '<div class="modal-body"><p class="mb-0">' + escapeHtml(message) + '</p></div>' +
    '<div class="modal-footer">' +
    '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
    '<button type="button" class="btn btn-danger" id="confirmBtn">Confirm</button>' +
    '</div>' +
    '</div>' +
    '</div>';

  document.body.appendChild(modal);

  var bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  document.getElementById('confirmBtn').addEventListener('click', function () {
    bsModal.hide();
    if (typeof onConfirm === 'function') onConfirm();
  });

  modal.addEventListener('hidden.bs.modal', function () {
    modal.remove();
  });
}

/* ============================================================
   Exam Submit
   ============================================================ */

function submitExam(testId) {
  confirmAction('Are you sure you want to submit the exam? You cannot go back.', function () {
    var form = document.getElementById('examForm');
    if (form) {
      form.submit();
    } else {
      ajaxPost('/api/submit_exam.php', {
        test_id:    testId,
        answers:    collectAnswers(),
        csrf_token: getCsrfToken()
      }, function (err, data) {
        if (err) {
          showToast('Submission failed. Try again.', 'danger');
          return;
        }
        if (data && data.redirect) {
          window.location.href = data.redirect;
        }
      });
    }
  });
}

/* ============================================================
   Admin Sidebar Toggle (mobile + desktop)
   ============================================================ */

function initAdminSidebar() {
  var toggle        = document.getElementById('sidebarToggle');         // mobile open
  var sidebar       = document.getElementById('adminSidebar');
  var overlay       = document.getElementById('sidebarOverlay');
  var collapseBtn   = document.getElementById('sidebarCollapseDesktop'); // desktop hide
  var openDesktop   = document.getElementById('sidebarOpenDesktop');     // desktop reopen

  if (!sidebar) return;

  // ---- Mobile: slide in/out with overlay ----
  if (toggle) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('active');
    });
  }
  if (overlay) {
    overlay.addEventListener('click', function () {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
  }

  // ---- Desktop: collapse/expand (hides sidebar, content goes full width) ----
  var STORAGE_KEY = 'adminSidebarCollapsed';

  function setCollapsed(collapsed) {
    document.body.classList.toggle('admin-sidebar-collapsed', collapsed);
    try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
  }

  // Restore saved state on load
  var saved = '0';
  try { saved = localStorage.getItem(STORAGE_KEY) || '0'; } catch (e) {}
  if (saved === '1') {
    document.body.classList.add('admin-sidebar-collapsed');
  }

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () { setCollapsed(true); });
  }
  if (openDesktop) {
    openDesktop.addEventListener('click', function () { setCollapsed(false); });
  }
}

/* ============================================================
   Charts Helper
   ============================================================ */

/**
 * Initialize a Chart.js doughnut chart
 * @param {string}   canvasId
 * @param {Array}    labels
 * @param {Array}    data
 * @param {Array}    colors
 * @returns {Chart}
 */
function initDoughnutChart(canvasId, labels, data, colors) {
  var canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return null;

  return new Chart(canvas.getContext('2d'), {
    type: 'doughnut',
    data: {
      labels:   labels,
      datasets: [{ data: data, backgroundColor: colors, borderWidth: 2 }]
    },
    options: {
      cutout: '70%',
      plugins: {
        legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 12 } } }
      },
      responsive: true,
      maintainAspectRatio: false
    }
  });
}

/**
 * Initialize a Chart.js line chart
 * @param {string} canvasId
 * @param {Array}  labels
 * @param {Array}  datasets
 * @returns {Chart}
 */
function initLineChart(canvasId, labels, datasets) {
  var canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return null;

  return new Chart(canvas.getContext('2d'), {
    type: 'line',
    data: { labels: labels, datasets: datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true }
      }
    }
  });
}

/* ============================================================
   DOM Ready
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
  // Live search
  initLiveSearch();

  // Admin sidebar (if on admin pages)
  initAdminSidebar();

  // Bootstrap tooltips
  var tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );
  tooltipTriggerList.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });

  // Confirm delete buttons
  document.querySelectorAll('[data-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var msg  = btn.dataset.confirm || 'Are you sure?';
      var href = btn.href || btn.dataset.href;
      confirmAction(msg, function () {
        if (href) window.location.href = href;
      });
    });
  });

  // Auto-dismiss alerts after 6 seconds
  document.querySelectorAll('.alert.auto-dismiss').forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity    = '0';
      setTimeout(function () { alert.remove(); }, 500);
    }, 6000);
  });
});

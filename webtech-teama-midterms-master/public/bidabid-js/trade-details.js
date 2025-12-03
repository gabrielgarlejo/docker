
document.addEventListener('DOMContentLoaded', function() {

  const link = document.getElementById('owner-feedback-link');
  const pane = document.getElementById('feedback-pane');
  const body = document.getElementById('fb-body');
  const closeBtn = document.getElementById('fb-close');
  const titleEl = document.getElementById('fb-title');

  if (link && pane && body && closeBtn && titleEl) {
    function openPane() {
      pane.classList.add('open');
      pane.setAttribute('aria-hidden', 'false');
    }

    function closePane() {
      pane.classList.remove('open');
      pane.setAttribute('aria-hidden', 'true');
    }

    function starBar(avg) {
      const r = Math.max(0, Math.min(5, Number(avg || 0)));
      const full = Math.floor(r);
      return '★'.repeat(full) + '☆'.repeat(5 - full);
    }

    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return String(text || '').replace(/[&<>"']/g, function(char) {
        return map[char];
      });
    }

    closeBtn.addEventListener('click', closePane);
    
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closePane();
    });

    link.addEventListener('click', function(e) {
      e.preventDefault();
      const userId = Number(link.dataset.userId || 0);
      if (!userId) return;

      openPane();
      body.innerHTML = '<div class="fb-loading">Loading…</div>';
      titleEl.textContent = 'Feedback';

      const params = new URLSearchParams({
        action: 'get_feedback',
        user_id: String(userId)
      });

      fetch('trade-details.php?' + params.toString(), {
        credentials: 'same-origin'
      })
      .then(function(res) {
        return res.json();
      })
      .then(function(data) {
        if (!data.ok) {
          body.innerHTML = '<div class="fb-error">Unable to load feedback.</div>';
          return;
        }

        if (typeof data.avg === 'number' && typeof data.count === 'number') {
          const stars = starBar(data.avg);
          titleEl.innerHTML = 'Feedback · <span class="fb-rating" style="color:#f5a623;">' + stars + '</span> <span style="font-weight:600;color:#333;">' + data.avg.toFixed(2) + '</span> <span style="color:#666;">(' + data.count + ')</span>';
        }

        if (!Array.isArray(data.feedbacks) || data.feedbacks.length === 0) {
          body.innerHTML = '<div class="fb-empty">No feedback yet.</div>';
          return;
        }

        const items = data.feedbacks.map(function(f) {
          const author = (f.from_username && f.from_username !== '') ? f.from_username : 'User';
          const r = Math.max(0, Math.min(5, Number(f.rating || 0)));
          const stars = '★'.repeat(Math.floor(r)) + '☆'.repeat(5 - Math.floor(r));
          return '<div class="fb-item">' +
            '<div class="fb-row">' +
            '<span class="fb-author">@' + escapeHtml(author) + '</span>' +
            '<span class="fb-rating" aria-label="' + r + ' out of 5">' + stars + '</span>' +
            '</div>' +
            '<div class="fb-comment">' + escapeHtml(f.comment) + '</div>' +
            '</div>';
        }).join('');

        body.innerHTML = '<div class="fb-list">' + items + '</div>';
      })
      .catch(function(error) {
        console.error('Feedback error:', error);
        body.innerHTML = '<div class="fb-error">Network error loading feedback.</div>';
      });
    });
  }

  const demoForm = document.getElementById('demo-offer-form');
  if (demoForm) {
    demoForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const btn = demoForm.querySelector('.submit-offer-btn');
      const note = document.getElementById('demo-note');
      
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Proposal Sent!';
      }
      
      if (note) {
        note.style.display = 'block';
      }
      
      setTimeout(function() {
        window.location.href = window.location.pathname + window.location.search;
      }, 900);
    });
  }
});

document.addEventListener('DOMContentLoaded', function () {
  var isSubmitting = false;
  var bidBtn = document.getElementById('place-bid');
  var bidInput = document.getElementById('new-bid');
  var msgDiv = document.getElementById('bid-message');

  if (bidInput && window.bidConfig && window.bidConfig.minBid) {
    bidInput.value = window.bidConfig.minBid;
  }

  if (bidBtn) {
    bidBtn.addEventListener('click', function () {
      if (isSubmitting) return;
      var bidValue = (bidInput.value || '').trim();
      if (!bidValue) {
        msgDiv.innerHTML = '<div style="color:#dc2626;padding:10px;background:#fee2e2;border-radius:6px;">✗ Please enter a bid amount</div>';
        bidInput.focus();
        return;
      }
      var bidFloat = parseFloat(bidValue);
      if (isNaN(bidFloat) || bidFloat <= 0) {
        msgDiv.innerHTML = '<div style="color:#dc2626;padding:10px;background:#fee2e2;border-radius:6px;">✗ Bid must be greater than 0</div>';
        bidInput.focus();
        return;
      }
      isSubmitting = true;
      bidBtn.disabled = true;
      bidBtn.textContent = 'Placing...';
      msgDiv.innerHTML = '<div style="color:#3b82f6;padding:10px;background:#dbeafe;border-radius:6px;">⏳ Submitting...</div>';
      var formData = new FormData();
      formData.append('item_id', window.bidConfig.itemId);
      formData.append('new_bid', bidFloat);
      fetch('place-bids.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.ok) {
            msgDiv.innerHTML = '<div style="color:#10b981;padding:10px;background:#d1fae5;border-radius:6px;">✓ ' + data.message + '<br>New Balance: ₱' + Number(data.new_balance).toFixed(2) + '</div>';
            document.getElementById('wallet-display').textContent = '₱' + Number(data.new_balance).toFixed(2);
            document.getElementById('current-bid').textContent = '₱' + Number(data.amount).toFixed(2);
            setTimeout(function () { window.location.reload(); }, 1500);
          } else {
            throw new Error(data.message || 'Unknown error');
          }
        })
        .catch(function (error) {
          msgDiv.innerHTML = '<div style="color:#dc2626;padding:10px;background:#fee2e2;border-radius:6px;">✗ ' + error.message + '</div>';
          isSubmitting = false;
          bidBtn.disabled = false;
          bidBtn.textContent = 'Place Bid';
        });
    });
  }

  var chatInput = document.getElementById('chat-message');
  var sendBtn = document.getElementById('send-message');
  var chatBox = document.getElementById('chat-box');

  if (sendBtn && chatInput) {
    sendBtn.addEventListener('click', function () {
      var message = (chatInput.value || '').trim();
      if (!message) return;
      var formData = new FormData();
      formData.append('action', 'send_message');
      formData.append('item_id', window.bidConfig.itemId);
      formData.append('message', message);
      fetch('item-details.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.ok) {
            chatInput.value = '';
            loadChat();
          }
        });
    });
    chatInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') sendBtn.click();
    });
  }

  function loadChat() {
    fetch('item-details.php?action=get_chat&item_id=' + window.bidConfig.itemId, { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.ok && Array.isArray(data.messages)) {
          chatBox.innerHTML = data.messages.map(function (m) {
            return '<div class="chat-msg"><strong>' + (m.username || 'User') + ':</strong> ' + (m.message || '') + '</div>';
          }).join('');
          chatBox.scrollTop = chatBox.scrollHeight;
        }
      });
  }

  setInterval(loadChat, 3000);
  loadChat();

  var link = document.getElementById('seller-feedback-link');
  var pane = document.getElementById('feedback-pane');
  var body = document.getElementById('fb-body');
  var closeBtn = document.getElementById('fb-close');
  var titleEl = document.getElementById('fb-title');

  if (link && pane) {
    function openPane() { pane.classList.add('open'); pane.setAttribute('aria-hidden', 'false'); }
    function closePane() { pane.classList.remove('open'); pane.setAttribute('aria-hidden', 'true'); }
    function starBar(avg) { var r = Math.max(0, Math.min(5, Number(avg || 0))); var f = Math.floor(r); return '★'.repeat(f) + '☆'.repeat(5 - f); }
    if (closeBtn) closeBtn.addEventListener('click', closePane);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePane(); });
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var userId = Number(link.dataset.userId || 0);
      if (!userId) return;
      openPane();
      if (body) body.innerHTML = '<div class="fb-loading">Loading…</div>';
      if (titleEl) titleEl.textContent = 'Feedback';
      var params = new URLSearchParams({ action: 'get_feedback', user_id: String(userId) });
      fetch('item-details.php?' + params.toString(), { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.ok) { if (body) body.innerHTML = '<div class="fb-error">Unable to load.</div>'; return; }
          if (typeof data.avg === 'number' && typeof data.count === 'number') {
            var stars = starBar(data.avg);
            if (titleEl) titleEl.innerHTML = 'Feedback · ' + stars + ' ' + data.avg.toFixed(2) + ' (' + data.count + ')';
          }
          if (!Array.isArray(data.feedbacks) || data.feedbacks.length === 0) {
            if (body) body.innerHTML = '<div class="fb-empty">No feedback yet.</div>';
            return;
          }
          var items = data.feedbacks.map(function (f) {
            var author = f.from_username || 'User';
            var r = Math.max(0, Math.min(5, Number(f.rating || 0)));
            var stars = '★'.repeat(Math.floor(r)) + '☆'.repeat(5 - Math.floor(r));
            return '<div class="fb-item"><div style="display:flex;justify-content:space-between;gap:8px;"><strong>@' + author + '</strong><span>' + stars + '</span></div><div>' + f.comment + '</div></div>';
          }).join('');
          if (body) body.innerHTML = items;
        });
    });
  }
});


document.addEventListener('DOMContentLoaded', function() {
    const toggleBtns = document.querySelectorAll('.toggle-btn');
    const contentSections = document.querySelectorAll('.content-section');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            
            
            toggleBtns.forEach(b => b.classList.remove('active'));
            contentSections.forEach(s => s.classList.remove('active'));
            
            
            this.classList.add('active');
            document.getElementById(view + '-content').classList.add('active');
        });
    });

    document.addEventListener('click', function(e) {
        const card = e.target.closest('.card');
        if (!card || e.target.closest('a, button')) return;

        const itemId = card.getAttribute('data-item-id');
        if (itemId && itemId !== '0') {
            if (card.classList.contains('trade-card') || card.closest('#trade-listings')) {
                window.location.href = 'proposed-trades.php?id=' + itemId;
            } else {
                window.location.href = 'item-details.php?id=' + itemId;
            }
        }
    });
    
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) return;
            const auctionId = btn.getAttribute('data-auction-id');
            const itemId = btn.getAttribute('data-item-id');
            const listingType = btn.getAttribute('data-listing-type') || 'auction';

            fetch('delete-item.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `auction_id=${encodeURIComponent(auctionId || '')}&item_id=${encodeURIComponent(itemId)}&listing_type=${encodeURIComponent(listingType)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.closest('.card').remove();
                } else {
                    alert(data.message || 'Delete failed.');
                }
            })
            .catch(() => alert('Delete failed.'));
        });
    });
});
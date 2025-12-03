document.addEventListener('DOMContentLoaded', function() {
    // Timer countdowns
    document.querySelectorAll(".timer").forEach(function(el) {
        function plural(n, label) {
            return n > 0 ? (n + " " + label + (n > 1 ? "s " : " ")) : "";
        }
        var endStr = el.dataset.end, upcoming = el.dataset.upcoming;
        function update(){
            var now = new Date(), tval = new Date(endStr.replace(/-/g,'/'));
            var diff = (tval - now)/1000;
            if(diff <= 0) {
                el.textContent = upcoming ? "Auction is now LIVE!" : "Ended";
                el.style.color = upcoming ? "#28a745" : "#888";
                return;
            }
            var weeks = Math.floor(diff / (7 * 24 * 3600));
            var days = Math.floor(diff % (7 * 24 * 3600) / (24 * 3600));
            var hours = Math.floor(diff % (24 * 3600) / 3600);
            var mins = Math.floor(diff % 3600 / 60);
            var secs = Math.floor(diff % 60);
            el.textContent =
                (upcoming ? "Starts in " : "Ends in ") +
                plural(weeks, "week") +
                plural(days, "day") +
                (hours > 0 ? (hours < 10 ? "0" : "") + hours + ":" : "00:") +
                (mins < 10 ? "0" : "") + mins + ":" +
                (secs < 10 ? "0" : "") + secs;
            if (diff > 0) setTimeout(update, 1000);
        }
        update();
    });

    // Limit card titles/descriptions to 10 words
    document.querySelectorAll('.card h3, .card p').forEach(el => {
        var text = el.textContent.trim().split(/\s+/);
        if (text.length > 10) {
            el.textContent = text.slice(0, 10).join(' ') + '...';
        }
    });

    // Upcoming cards open quick view modal
    document.querySelectorAll('.card.card-upcoming').forEach(card => {
        card.addEventListener('click', function(e) {
            var modal = document.getElementById('quick-view-modal');
            var body = document.getElementById('quick-view-body');
            body.innerHTML = `
                <h2 style="margin-top:0">${card.getAttribute('data-title')}</h2>
                <img src="${card.getAttribute('data-img')}" style="max-width:100%; border-radius:7px; margin-bottom:12px;" alt=""/>
                <div style="font-size:1em; margin-bottom:10px"><strong>Auction starts:</strong> ${card.getAttribute('data-auctionstart')}</div>
                <p>${card.getAttribute('data-desc')}</p>
            `;
            modal.style.display = 'flex';
        });
    });

    // Live cards go to details page
    document.querySelectorAll('.card:not(.card-upcoming)[data-item-id]').forEach(card => {
        card.addEventListener('click', function() {
            var id = this.getAttribute('data-item-id');
            window.location.href = 'item-details.php?id=' + encodeURIComponent(id);
        });
    });

    // Quick view modal close handlers
    document.getElementById('quick-view-close').onclick = function() {
        document.getElementById('quick-view-modal').style.display = 'none';
    };
    document.getElementById('quick-view-modal').onclick = function(e) {
        if (e.target === this) this.style.display = 'none';
    };

    // Category filtering
    const buttons = document.querySelectorAll('.category');
    const sections = document.querySelectorAll('[data-cat-section]');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const cat = btn.dataset.cat;
            sections.forEach(section => {
                section.style.display = cat === 'All' || section.dataset.catSection === cat ? 'block' : 'none';
            });
        });
    });

    // Horizontal scrollbar arrows
    function scrollContainer(id, dir) {
        const el = document.getElementById(id);
        if (!el) return;
        el.scrollBy({ left: dir === 'left' ? -300 : 300, behavior: 'smooth' });
    }
    document.querySelectorAll('.arrow.left').forEach(btn => {
        btn.addEventListener('click', () => scrollContainer(btn.dataset.target, 'left'));
    });
    document.querySelectorAll('.arrow.right').forEach(btn => {
        btn.addEventListener('click', () => scrollContainer(btn.dataset.target, 'right'));
    });

    // Search functionality - NEW
    const searchBar = document.getElementById('search-bar');
    const searchBtn = document.getElementById('search-btn');
    
    if (searchBar) {
        function performSearch() {
            const query = searchBar.value.trim().toLowerCase();
            const allCards = document.querySelectorAll('.card');
            
            allCards.forEach(card => {
                const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
                const desc = card.querySelector('p')?.textContent.toLowerCase() || '';
                const cardText = title + ' ' + desc;
                
                if (cardText.includes(query) || query === '') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        searchBar.addEventListener('input', performSearch);
        searchBtn.addEventListener('click', performSearch);
        searchBar.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }
});

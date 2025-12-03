document.addEventListener('DOMContentLoaded', function () {
    const toggleBtns = document.querySelectorAll('.toggle-btn');
    const contentSections = document.querySelectorAll('.content-section');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const view = this.getAttribute('data-view');
            toggleBtns.forEach(b => b.classList.remove('active'));
            contentSections.forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(view + '-content').classList.add('active');
        });
    });
});

document.addEventListener('click', function (e) {
    const card = e.target.closest('.card');
    if (!card || e.target.closest('a, button')) return;

    const itemId = card.getAttribute('data-item-id');
    if (itemId && itemId !== '0') {
        if (card.classList.contains('trade-card') || card.closest('#trade-content')) {
            window.location.href = 'proposed-trades.php?id=' + itemId;
        } else {
            window.location.href = 'item-details.php?id=' + itemId;
        }
    }
});

(function () {
  function scrollContainer(targetId, direction) {
    const container = document.getElementById(targetId);
    if (!container) return;

    const scrollAmount = container.offsetWidth * 0.75;
    container.scrollBy({
      left: direction === "left" ? -scrollAmount : scrollAmount,
      behavior: "smooth",
    });
  }

  document.querySelectorAll(".arrow.left").forEach(button => {
    button.addEventListener("click", () => scrollContainer(button.dataset.target, "left"));
  });

  document.querySelectorAll(".arrow.right").forEach(button => {
    button.addEventListener("click", () => scrollContainer(button.dataset.target, "right"));
  });
})();

(function () {
  const filterButtons = document.querySelectorAll(".category");
  const itemSections = document.querySelectorAll("[data-cat-section]");

  filterButtons.forEach(button => {
    button.addEventListener("click", () => {
      filterButtons.forEach(btn => btn.classList.remove("active"));
      button.classList.add("active");
      const selectedCategory = button.dataset.cat;
      itemSections.forEach(section => {
        if (selectedCategory === "All" || section.dataset.catSection === selectedCategory) {
          section.style.display = "block";
        } else {
          section.style.display = "none";
        }
      });
    });
  });
})();

(function () {
  const searchInput = document.getElementById("trade-search-bar");
  const allCards = document.querySelectorAll(".card");
  if (!searchInput) return;

  function performSearch() {
    const query = searchInput.value.trim().toLowerCase();
    allCards.forEach(card => {
      const cardText = card.innerText.toLowerCase();
      const isSectionVisible = card.closest('[data-cat-section]').style.display !== 'none';
      if (isSectionVisible && cardText.includes(query)) {
        card.style.display = "block";
      } else {
        card.style.display = "none";
      }
    });
  }

  searchInput.addEventListener("input", performSearch);
  const searchButton = document.getElementById("trade-search-btn");
  if (searchButton) {
    searchButton.addEventListener("click", performSearch);
  }
})();

(function () {
  const lightbox = document.getElementById("image-lightbox");
  const lightboxImg = document.getElementById("lightbox-img");
  const captionText = document.getElementById("lightbox-caption");
  const closeButton = document.querySelector(".lightbox-close");

  if (!lightbox || !lightboxImg || !closeButton) return;

  function openLightbox(imgElement) {
    lightbox.classList.add("active");
    lightboxImg.src = imgElement.src;
    captionText.innerHTML = imgElement.alt;
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox.classList.remove("active");
    document.body.style.overflow = ''; 
  }

  document.querySelectorAll(".item-image").forEach(img => {
    img.addEventListener("click", () => openLightbox(img));
  });

  closeButton.addEventListener("click", closeLightbox);
  lightbox.addEventListener("click", (e) => {
    if (e.target === lightbox) {
      closeLightbox();
    }
  });
})();

(function () {
  const drawer = document.getElementById("offer-drawer");
  const form = document.getElementById("offer-form");
  const theirItemInput = document.getElementById("their-item");
  const statusEl = document.getElementById("offer-status");
  const fileInput = document.getElementById("your-images");
  const previewGrid = document.getElementById("preview-grid");

  if (!drawer || !form) return;

  const MAX_FILES = 6;
  const MAX_SIZE_BYTES = 2 * 1024 * 1024;
  let filesState = [];

  function refreshPreviews() {
    if (!previewGrid) return;
    previewGrid.innerHTML = "";
    filesState.forEach((file, index) => {
      const tile = document.createElement("div");
      tile.className = "preview-tile";

      const img = document.createElement("img");
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      img.onload = () => URL.revokeObjectURL(img.src);

      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "remove-preview";
      removeBtn.innerHTML = "&times;";
      removeBtn.onclick = () => {
        filesState.splice(index, 1);
        refreshPreviews();
      };

      tile.appendChild(img);
      tile.appendChild(removeBtn);
      previewGrid.appendChild(tile);
    });
  }

  function handleFileSelection(fileList) {
    const incomingFiles = Array.from(fileList || []);
    const typeRegex = /^image\/(jpeg|png|webp|gif)$/i;

    const validFiles = incomingFiles.filter(file =>
      typeRegex.test(file.type) && file.size <= MAX_SIZE_BYTES
    );

    filesState = [...filesState, ...validFiles].slice(0, MAX_FILES);
    refreshPreviews();
  }

  if (fileInput) {
    fileInput.addEventListener("change", () => handleFileSelection(fileInput.files));
  }

  function openDrawer(button) {
    const itemName = button.dataset.itemName;
    if (theirItemInput) theirItemInput.value = itemName || "";
    drawer.classList.add("active");
    drawer.setAttribute("aria-hidden", "false");
  }

  function closeDrawer() {
    drawer.classList.remove("active");
    drawer.setAttribute("aria-hidden", "true");
    form.reset();
    filesState = [];
    refreshPreviews();
    if (statusEl) statusEl.hidden = true;
  }

  document.querySelectorAll(".open-offer").forEach(btn => {
    btn.addEventListener("click", () => openDrawer(btn));
  });

  document.getElementById("offer-close")?.addEventListener("click", closeDrawer);
  document.getElementById("offer-cancel")?.addEventListener("click", closeDrawer);
  drawer.addEventListener("click", (e) => {
    if (e.target === drawer) closeDrawer();
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (form.your_item.value.trim() === "") {
      alert("Please describe the item you want to offer.");
      return;
    }

    const formData = new FormData(form);
    filesState.forEach(file => formData.append("images[]", file));

    try {
      const response = await fetch("trade-offer.php", { method: "POST", body: formData });
      const resultText = await response.text();

      if (statusEl) {
        statusEl.hidden = false;
        statusEl.textContent = response.ok ? "Offer sent successfully!" : `Failed: ${resultText}`;
      }
      if (response.ok) {
        setTimeout(closeDrawer, 2000);
      }
    } catch (error) {
      if (statusEl) {
        statusEl.hidden = false;
        statusEl.textContent = "Network error. Could not send offer.";
      }
    }
  });
})();

(function () {
  document.querySelectorAll('.card p').forEach(el => {
    let words = el.textContent.trim().split(/\s+/);
    if (words.length > 10) {
      el.textContent = words.slice(0, 10).join(' ') + '...';
    }
  });
})();

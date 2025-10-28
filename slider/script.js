document.addEventListener("DOMContentLoaded", function () {
  const gallery = document.querySelector(".gallery");
  const items = document.querySelectorAll(".gallery-item");
  const prevBtn = document.querySelector(".prev");
  const nextBtn = document.querySelector(".next");
  const pager = document.querySelector(".pager");

  let currentIndex = 0;
  let itemsPerView = 1;

  function updateItemsPerView() {
    if (window.innerWidth >= 768) {
      itemsPerView = 3;
    } else {
      itemsPerView = 1;
    }
    updateGallery();
  }

  function updateGallery() {
    const translateX = -currentIndex * (100 / itemsPerView);
    gallery.style.transform = `translateX(${translateX}%)`;
    updatePager();
  }

  function createPager() {
    pager.innerHTML = "";
    const totalPages = Math.ceil(items.length / itemsPerView);

    for (let i = 0; i < totalPages; i++) {
      const dot = document.createElement("div");
      dot.classList.add("page-dot");
      if (i === currentIndex) {
        dot.classList.add("active");
      }
      dot.addEventListener("click", () => {
        currentIndex = i;
        updateGallery();
      });
      pager.appendChild(dot);
    }
  }

  function updatePager() {
    const dots = document.querySelectorAll(".page-dot");
    dots.forEach((dot, index) => {
      if (index === currentIndex) {
        dot.classList.add("active");
      } else {
        dot.classList.remove("active");
      }
    });
  }

  nextBtn.addEventListener("click", () => {
    const maxIndex = Math.ceil(items.length / itemsPerView) - 1;
    if (currentIndex < maxIndex) {
      currentIndex++;
    } else {
      currentIndex = 0;
    }
    updateGallery();
  });

  prevBtn.addEventListener("click", () => {
    const maxIndex = Math.ceil(items.length / itemsPerView) - 1;
    if (currentIndex > 0) {
      currentIndex--;
    } else {
      currentIndex = maxIndex;
    }
    updateGallery();
  });

  updateItemsPerView();
  createPager();

  window.addEventListener("resize", () => {
    updateItemsPerView();
    createPager();
  });
});
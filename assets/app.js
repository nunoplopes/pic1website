import Plotly from 'plotly.js/lib/core';
import { Tabulator, FormatModule, SortModule } from 'tabulator-tables';
import { Tooltip } from 'bootstrap';
import Swiper from 'swiper';
import { Autoplay, Navigation, FreeMode } from 'swiper/modules';
import './styles/app.css';
import 'swiper/css';
import 'swiper/css/autoplay';
import 'swiper/css/free-mode';
import 'swiper/css/navigation';

import Bar from 'plotly.js/lib/bar';
import Scatter from 'plotly.js/lib/scatter';
Plotly.register([Bar, Scatter]);
window.Plotly = Plotly;

Tabulator.registerModule([FormatModule, SortModule]);
window.Tabulator = Tabulator;

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function () {
  var tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new Tooltip(tooltipTriggerEl);
  });
});

function toggleVideo(button) {
  let videoContainer = button.nextElementSibling;
  if (videoContainer.style.display === "none" ) {
    videoContainer.style.display = "block";
    button.textContent = "Hide Video";
  } else {
    videoContainer.style.display = "none";
    button.textContent = "Show Video";
  }
}

window.toggleVideo = toggleVideo;

document.addEventListener('DOMContentLoaded', () => {
  new Swiper('.swiper', {
    modules: [Autoplay, Navigation, FreeMode],
    slidesPerView: 'auto',
    spaceBetween: 20,
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    freeMode: {
      enabled: true,
      momentum: false,
    },
    autoplay: {
      delay: 3000,
    },
    loop: true,
    grabCursor: true,
  });
});

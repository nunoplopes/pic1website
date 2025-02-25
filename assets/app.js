import Plotly from 'plotly.js-dist';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import { createPopper } from '@popperjs/core';
import * as bootstrap from 'bootstrap';
import './styles/app.css';

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function () {
  var tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});

window.Tabulator = Tabulator;

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

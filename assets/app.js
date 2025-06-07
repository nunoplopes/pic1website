import Plotly from 'plotly.js/lib/core';
import { Tabulator, FormatModule, SortModule } from 'tabulator-tables';
import { Tooltip } from 'bootstrap';
import './styles/app.css';

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

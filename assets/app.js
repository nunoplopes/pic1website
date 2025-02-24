import Plotly from 'plotly.js-dist';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import './styles/app.css';

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

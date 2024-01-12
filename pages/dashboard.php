<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Dashboard');

foreach (db_get_merged_patch_stats() as $entry) {
  $years[]          = '"' . get_term_for($entry['year']) . '"';
  $patches[]        = $entry['patches'];
  $lines_added[]    = $entry['lines_added'];
  $lines_deleted[]  = $entry['lines_deleted'];
  $files_modified[] = $entry['files_modified'];
}
$years          = implode(', ', $years);
$patches        = implode(', ', $patches);
$lines_added    = implode(', ', $lines_added);
$lines_deleted  = implode(', ', $lines_deleted);
$files_modified = implode(', ', $files_modified);

echo <<<HTML
<script src='https://cdn.plot.ly/plotly-2.27.0.min.js'></script>
<div id='plotdiv' style="max-width: 800px"></div>
<script>
xData = [$years];
yData = [[$patches], [$lines_added], [$lines_deleted], [$files_modified]];
var labels = ['Merged PRs', 'Lines added', 'Lines deleted', 'Files modified'];
var lineSize = [3, 2, 2, 2];
var yaxis = ['y', 'y2', 'y2', 'y2'];

var data = [];
for (var i = 0 ; i < yData.length; ++i) {
  var result = {
    x: xData,
    y: yData[i],
    yaxis: yaxis[i],
    type: 'scatter',
    mode: 'lines',
    name: labels[i],
    line: {
      width: lineSize[i]
    }
  };
  data.push(result);
}

var layout = {
  legend: {
    x: 1.1,
    y: 1.1
  },
  xaxis: {
    showline: true,
    showgrid: false,
    autotick: false
  },
  yaxis: {
    title: 'PR count'
  },
  yaxis2: {
    title: 'File/Line count',
    showgrid: false,
    overlaying: 'y',
    side: 'right'
  },
  annotations: [
    {
      xref: 'paper',
      yref: 'paper',
      x: 0.0,
      y: 1.05,
      xanchor: 'left',
      yanchor: 'bottom',
      text: 'Merged PRs',
      font:{
        size: 24,
        color: 'rgb(37,37,37)'
      },
      showarrow: false
    }
  ]
};

config = {
  displayModeBar: false,
  responsive: true
};

Plotly.newPlot('plotdiv', data, layout, config);
</script>

HTML;

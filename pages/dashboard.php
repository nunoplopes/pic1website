<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Dashboard');

do_start_form('dashboard');
$selected_year = do_year_selector();
echo "</form>\n";

foreach (db_get_merged_patch_stats() as $entry) {
  $years[]          = '"' . get_term_for($entry['year']) . '"';
  $patches[]        = $entry['patches'];
  $lines_added[]    = $entry['lines_added'];
  $lines_deleted[]  = $entry['lines_deleted'];
  $files_modified[] = $entry['files_modified'];
}

$max_y  = round(max($patches) * 1.1);
$max_y2 = round(max(max($lines_added),
                    max($lines_deleted),
                    max($files_modified)) * 1.1);

$years          = implode(', ', $years);
$patches        = implode(', ', $patches);
$lines_added    = implode(', ', $lines_added);
$lines_deleted  = implode(', ', $lines_deleted);
$files_modified = implode(', ', $files_modified);

echo <<<HTML
<script src='https://cdn.plot.ly/plotly-3.0.0.min.js'></script>
<div id='plotdiv' style="max-width: 900px"></div>
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
    title: 'PR count',
    range: [0, $max_y]
  },
  yaxis2: {
    title: 'File/Line count',
    range: [0, $max_y2],
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


// % of merged patches
$merged = [];
$total = [];

foreach (db_get_patch_stats() as $data) {
  switch ($data['status']) {
    case PATCH_WAITING_REVIEW:
    case PATCH_REVIEWED:
    case PATCH_APPROVED:
      break;

    case PATCH_MERGED:
    case PATCH_MERGED_ILLEGAL:
      @$merged[$data['year']][$data['type']] += $data['count'];
      // fallthrough

    case PATCH_PR_OPEN:
    case PATCH_PR_OPEN_ILLEGAL:
    case PATCH_NOTMERGED:
    case PATCH_NOTMERGED_ILLEGAL:
      @$total[$data['year']][$data['type']] += $data['count'];
      break;

    default:
     die('invalid patch status');
  }
}
ksort($total);

$pcmerged_x = [];
$pcmerged_y = [];
foreach ($total as $year => $data) {
  $pcmerged_x[] = '"' . get_term_for($year) . '"';
  foreach ($data as $type => $n) {
    $pcmerged_y[$type][] = round(@$merged[$year][$type] / $n, 2);
  }
}
$pcmerged_x = implode(', ', $pcmerged_x);
$pcmerged_bug = implode(', ', $pcmerged_y[PATCH_BUGFIX]);
$pcmerged_feat = implode(', ', $pcmerged_y[PATCH_FEATURE]);

echo <<<HTML
<div id='pcmergedplot' style="max-width: 500px"></div>
<script>
var bugs = {
  x: [$pcmerged_x],
  y: [$pcmerged_bug],
  name: 'Bug fixes',
  type: 'bar'
};
var features = {
  x: [$pcmerged_x],
  y: [$pcmerged_feat],
  name: 'Features',
  type: 'bar'
};
var data = [bugs, features];
var layout = {
  title: {
    text: 'Percentage of Merged PRs'
  },
  barmode: 'group',
  yaxis: {
    tickformat: ',.0%'
  }
};
Plotly.newPlot('pcmergedplot', data, layout, config);
</script>
HTML;


// stats of projects selected this year

foreach (db_fetch_groups($selected_year) as $group) {
  if ($repo = $group->getValidRepository()) {
    if ($lang = $repo->language()) {
      @++$langs[(string)$lang];
    }
    @++$projs[$repo->name()];

    $prs_per_project[$repo->name()]['url'] = (string)$repo;
    foreach ($group->patches as $patch) {
      @++$prs_per_project[$repo->name()][$patch->type][$patch->wasMerged()];
    }
  }
}

if (sizeof($projs) > 10) {
  foreach ($projs as $proj => $n) {
    if ($n == 1)
      unset($projs[$proj]);
  }
}

arsort($langs);
arsort($projs);
ksort($prs_per_project, SORT_NATURAL | SORT_FLAG_CASE);

$lang_x = implode(', ', array_map('quote', array_keys($langs)));
$lang_y = implode(', ', $langs);

$proj_x = implode(', ', array_map('quote', array_keys($projs)));
$proj_y = implode(', ', $projs);

echo <<<HTML
<div id='langsplot' style="max-width: 800px"></div>
<script>
var data = [
  {
    x: [$lang_x],
    y: [$lang_y],
    type: 'bar'
  }
];
var layout = {
  title: {
    text: 'Project Languages'
  }
};
Plotly.newPlot('langsplot', data, layout, config);
</script>

<div id='projsplot' style="max-width: 900px; max-height: 500px"></div>
<script>
var data = [
  {
    x: [$proj_x],
    y: [$proj_y],
    type: 'bar'
  }
];
var layout = {
  title: {
    text: 'Most Frequent Projects'
  },
  xaxis: {
    tickangle: 45
  },
  margin: {
    b: 150
  }
};
Plotly.newPlot('projsplot', data, layout, config);
</script>

HTML;


// Now a table with all projects
echo <<<HTML
<link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.js"></script>
<p>&nbsp;</p>
<h2>All projects</h2>
<div id="projects-table" style="max-width: 999px"></div>
<script>
var tabledata = [

HTML;

$id = 0;
foreach ($prs_per_project as $name => $stats) {
  $total_bugs = @array_sum($stats[PATCH_BUGFIX]);
  $total_feat = @array_sum($stats[PATCH_FEATURE]);
  $bugs       = $stats[PATCH_BUGFIX][1] ?? 0;
  $feat       = $stats[PATCH_FEATURE][1] ?? 0;
  $bugs_pc    = $total_bugs ? round(($bugs / $total_bugs) * 100.0, 0) : 0;
  $feat_pc    = $total_feat ? round(($feat / $total_feat) * 100.0, 0) : 0;
  $url        = $stats['url'];
  echo <<<HTML
{id: $id, name: "$name", url: "$url", bugs: $bugs, bugs_pc: "$bugs_pc%",
 features: $feat, features_pc: "$feat_pc%"},

HTML;
  ++$id;
}

echo <<<HTML
];
var table = new Tabulator("#projects-table", {
  height: 250,
  data: tabledata,
  layout: "fitColumns",
  columns: [
    {title: "Project", field:"name", formatter:"link", formatterParams:{
      labelField: "name",
      urlField: "url"
    }},
    {title: "Merged PRs bug fixes", field: "bugs"},
    {title: "Merged PRs bug fixes %", field: "bugs_pc", sorter:"number"},
    {title: "Merged PRs features", field: "features"},
    {title: "Merged PRs features %", field: "features_pc", sorter:"number"},
  ],
});
</script>

HTML;

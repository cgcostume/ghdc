<!DOCTYPE html>
<html lang="en">
<head>
  <base href="/" target="_blank">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>GitHub Download Count</title>
  <meta name="description" content="Download count for GitHub releases.">
  <meta name="robots" content="index, follow">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/bootstrap/3.3.6/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300">
  <link rel="stylesheet" href="/styles.css">

  <noscript><style>.js-only { display: none; }</style></noscript>
  <script src="https://cdn.jsdelivr.net/g/jquery@2.2.1,bootstrap@3.3.6,amcharts@3.20.3(amcharts.js+pie.js+themes/patterns.js)"></script>
</head>

<?php

function curlJson($auth, $url)
{
	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_USERPWD, $auth['user'].":".$auth['token']);
	curl_setopt($curl, CURLOPT_USERAGENT, $auth['agent']);

	$raw = curl_exec($curl);
	curl_close ($curl);

	return json_decode($raw);
}

function curlReleases($auth, $user, $repository)
{
	$repo_url = "https://api.github.com/repos/$user/$repository/releases"; 
	return curlJson($auth, $repo_url);
}

function human_filesize($bytes, $decimals = 2) 
{
	// http://php.net/manual/en/function.filesize.php

  	$sz = 'BKMGTP';
  	$factor = floor((strlen($bytes) - 1) / 3);

	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor].($factor > 0 ? 'iB' : '');
}

function chart_config()
{
	/* https://docs.amcharts.com/3/javascriptcharts/AmPieChart */
	return '"type": "pie"
	, "startDuration": 0, "theme": "patterns", "autoResize": true
	, "hideLabelsPercent": 4.0, "percentPrecision": 1, "creditsPosition": "bottom-right"
	, "innerRadius": "66%", "export": { "enabled": false }
	, "valueField": "value", "titleField": "title", ';
}

function chart_data($values) /* expect array of key value pairs */
{
	$data = '';
	foreach ($values as $title => $value)
		$data .= '{ "title": "'.$title.'", "value": '.$value.'},';

	return '"dataProvider": [ '.$data.'],';
}

function make_chart($div, $values)
{
	return '<div class="chart" id="'.$div.'"></div><script>AmCharts.makeChart("'.$div.'", {'.chart_config().chart_data($values).'});</script>';
}

function make_table_releases($releases, $releases_info)
{
	$rows = '';
	foreach($releases as $release)
	{
		$tag = $release->tag_name;
		$rows .= '<tr>
					<td scope="row">'.$tag.'</td>
					<td><a href="'.$release->html_url.'">'.$release->name.'</a></td>
					<td class="text-right">'.human_filesize($releases_info['assets'][$tag]).'</td>
					<td class="text-right">'.$releases_info['downloads'][$tag].'</td>
					<td class="text-right">'.human_filesize($releases_info['traffic'][$tag]).'</td>
				</tr>';
	}

	$rows .= '<tr>
				<th>Sum</th>
				<th></th>
				<th class="text-right">'.human_filesize($releases_info['assets_total']).'</th>
				<th class="text-right">'.$releases_info['downloads_total'].'</th>
				<th class="text-right">'.human_filesize($releases_info['traffic_total']).'</th>
			</tr>';

	return '<table class="table table-hover"><thead><tr>
				<th>Tag</th>
				<th>Release Name</th>
				<th class="text-right">Size of Assets</th>
				<th class="text-right"># Downloads</th>
				<th class="text-right">Traffic</th>
			</tr></thead><tbody>'.$rows.'</tbody></table>';
}

function make_table_assets($release, $releases_info)
{
	$rows = '';
	foreach($release->assets as $asset)
	{
		$rows .= '<tr>
					<td scope="row"><a href="'.$asset->browser_download_url.'">'.$asset->name.'</a></td>
					<td class="text-right">'.human_filesize($asset->size).'</td>
					<td class="text-right">'.$asset->download_count.'</td>
					<td class="text-right">'.human_filesize($asset->download_count * $asset->size).'</td>
				</tr>';
	}

	$tag = $release->tag_name;
	$rows .= '<tr>
				<th>Sum</th>
				<th class="text-right">'.human_filesize($releases_info['assets'][$tag]).'</th>
				<th class="text-right">'.$releases_info['downloads'][$tag].'</th>
				<th class="text-right">'.human_filesize($releases_info['traffic'][$tag]).'</th>
			</tr>';

	return '<table class="table table-hover"><thead><tr>
				<th>Asset Name</th>
				<th class="text-right">Size of Asset</th>
				<th class="text-right"># Downloads</th>
				<th class="text-right">Traffic</th>
			</tr></thead><tbody>'.$rows.'</tbody></table>';
}


$auth = array(
	"user"  => "cgcostume",
	"token" => "",
	"agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.87 Safari/537.36");

if($auth["token"] == "") {
	echo('
                <div class="row text-center m1t">
                <div class="col-xs-8 col-xs-offset-2">
                <p>GitHub access token not configured.</p>
                </p></div></div>');

        exit();
}


$params_exist =  array_key_exists('user', $_GET) && array_key_exists('repository', $_GET);

if(!$params_exist)
{
	echo('
		<div class="row text-center m1t">
		<div class="col-xs-8 col-xs-offset-2">
		<p>Please provide <code>/&lt;user&gt;/&lt;repository&gt;</code>, e.g.: 
		<a href="/cginternals/glbinding"><code>/cginternals/glbinding</code></a>
		</p></div></div>');

	exit();
}


$user = $_GET['user'];
$repository = $_GET['repository'];

$releases = curlReleases($auth, $user, $repository); /* json */

$downloads_total = 0;
$traffic_total = 0; /* in bytes */
$assets_total = 0; /* in bytes */

$downloads_by_releases = array();
$traffic_by_releases = array();
$assets_by_releases = array(); /* accumulated asset sizes in bytes */


foreach($releases as $release)
{
	$downloads = 0;
	$traffic = 0; /* bytes */
	$assets = 0; /* bytes */

	foreach($release->assets as $asset)
	{
		$downloads += $asset->download_count;
		$traffic += $asset->download_count * $asset->size;
		$assets += $asset->size;
	}

	$downloads_total += $downloads;
	$traffic_total += $traffic;
	$assets_total += $assets;

	$downloads_by_releases[$release->tag_name] = $downloads;
	$traffic_by_releases[$release->tag_name] = $traffic;
	$assets_by_releases[$release->tag_name] = $assets;
}

$releases_info = array(
	"downloads" => $downloads_by_releases,
	"downloads_total" => $downloads_total,
	"traffic"   => $traffic_by_releases,
	"traffic_total"   => $traffic_total,
	"assets"    => $assets_by_releases,
	"assets_total"    => $assets_total);

?>

<body>

<div class="container">

	<div class="row m4t">
	<div class="col-xs-8 col-xs-offset-2">
	<div class="jumbotron text-center">
		<h1><?= $downloads_total != 1 ? '<strong>'.number_format($downloads_total, 0, '', '.').'</strong> Downloads' : '<strong>1</strong> Download'; ?>
		</h1>
		<p>accumulated over all assets of all <a href="https://github.com/<?= $user ?>/<?= $repository ?>"><?= $user ?>/<?= $repository ?></a> releases<?= $downloads_total ? ', resulting in a total of <strong>'.human_filesize($traffic_total).'</strong> of network traffic.</p>' : ', generating no network traffic at all.'; ?>
				</p>
	</div>
	</div>
	</div>

	<div class="row m2t">
	<div class="col-xs-8 col-xs-offset-2">
		<h1 class="text-center">Downloads by Release</h1>
		<?= make_chart('chart_downloads', $downloads_by_releases); ?>
		<?= make_table_releases($releases, $releases_info); ?> 
	</div>
	</div>

	<div class="row m2t">
	<div class="col-xs-8 col-xs-offset-2">
	<div class="page-header text-center">
	  <h1>Downloads by Asset</h1>
	</div>
	</div>
	</div>

<?php foreach($releases as $release)
{
	$downloads_by_assets = array();
	$traffic_by_assets = array();

	foreach($release->assets as $asset)
	{
		$downloads_by_assets[$asset->name] = $asset->download_count;
		$traffic_by_assets[$asset->name]   = $asset->download_count * $asset->size;
	}

	$published = new DateTime($release->published_at);
?>
	<div class="row m2t">
	<div class="col-xs-8 col-xs-offset-2">
		<h3><a href="<?= $release->html_url ?>"><?= $release->name ?></a> <span class="pull-right"><?= $release->prerelease ? '<span class="badge">pre-release</span>' : '' ?><?= $release->draft ? '<span class="badge">draft</span>' : '' ?> <small><?= $published->format('F j, Y') ?></small></span></h3>
		<?= make_chart('chart_assets_'.$release->tag_name, $downloads_by_assets); ?>
		<?= make_table_assets($release, $releases_info); ?> 
	</div>
	</div>
<?php
}
?>

	<div class="row m2t m1b">
	<div class="col-xs-8 col-xs-offset-2">
	<footer class="text-center">GitHub Download Count by <a href="https://github.com/cgcostume/ghdc">Daniel Limberger</a></footer>
	</div>
	</div>
</div>
</body>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>GitHub Download Count</title>
  <meta name="description" content="Download count for GitHub releases.">
  <meta name="robots" content="index, follow">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/bootstrap/3.3.6/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300">
  <link rel="stylesheet" href="styles.css">

  <noscript><style>.js-only { display: none; }</style></noscript>
  <script src="https://cdn.jsdelivr.net/g/jquery@2.2.1,bootstrap@3.3.6"></script>
</head>

<?php

function curlJson($auth, $url)
{
	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_USERPWD, $auth->user.":".$auth->token);
	curl_setopt($curl, CURLOPT_USERAGENT, $auth->agent);

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

?>

<body>

<div class="container">
	
	<div class="row m-t">
		<div class="col-xs-6 col-xs-offset-3">
			<h1>gh-dc : GitHub Download Count</h1>
		</div>
	</div>

<?php	

	$config_file = 'gh-repositories.json';

	$config_exists = file_exists($config_file);
	$params_exist =  array_key_exists('user', $_GET) && array_key_exists('repositories', $_GET);

	if(!$config_exists)
	{
		header('Location: https://github.com/cgcostume/gh-dc');
		exit();
	}

	$contents = file_get_contents('gh-repositories.json');
	$json = json_decode($contents);

	$config = $json->repositories;

	if($params_exist)
	{
		$config = array($_GET['user'] => explode(',', $_GET['repositories']));
	}

	foreach($config as $user => $repositories) 
	{ 
		foreach($repositories as $repository) 
		{	
			$releases = curlReleases($json->auth, $user, $repository); // json
?>

	<div class="row m-t">
		<div class="col-xs-6 col-xs-offset-3">
			<p class="lead">Download count for <a href="https://github.com/<?= $user ?>/<?= $repository ?>"><?= $user ?>/<?= $repository ?></a> releases:</p>
		</div>
	</div>

<?php
			foreach($releases as $release)
			{
				if($release->draft)
					continue;

				$published = new DateTime($release->published_at);
?>

	<div class="row m-t">
		<div class="col-xs-6 col-xs-offset-3">
			<div class="panel panel-default">
  				<div class="panel-heading">
    				<h3 class="panel-title"><a href="<?= $release->html_url ?>"><?= $release->name ?></a> <span class="pull-right"><?= $release->prerelease ? '<span class="badge">pre-release</span>' : '' ?><?= $release->draft ? '<span class="badge">draft</span>' : '' ?> <small><?= $published->format('F j, Y') ?></small></span></h3>
  				</div>
  				<!--<div class="panel-body">
  					
  				</div>-->
				<table class="table">
					<!--<thead><tr><th>Asset Name</th><th>Size</th><th>Downloads</th></tr></thead>-->
					<tbody>
<?php
				$download_count = 0;
				foreach($release->assets as $asset)
				{
?>
						<tr><td scope="row"><a href="<?= $asset->browser_download_url ?>"><?= $asset->name ?></a></td><td><?= human_filesize($asset->size) ?></td><td><?= $asset->download_count ?></td></tr>
<?php
					$download_count += $asset->download_count;

				} /* end for assets */

				if(sizeof($release->assets) > 0)
				{
?>
						<tr><td scope="row"></td><th>Sum</th><th><?= $download_count ?></th></tr>
<?php	
				} /* end if asset count */
				else
				{
?>
				<tr><td scope="row">No Assets</td><td></td><td></td></tr>
<?php 		
				}
?>
					</tbody> 
				</table>
			</div>
		</div>
	</div>

<?php
			} /* end for releases */
		}
	}
?>

</div>
</body>

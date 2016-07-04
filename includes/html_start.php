<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	
	<title><?php echo $pagetitle; ?></title>
	
	<link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="/css/style.css" />
	<link rel="stylesheet" type="text/css" href="/css/jquery.ui.css" />
	<link rel="stylesheet" type="text/css" href="/css/jquery.nouislider.css" />
	
	<script type="text/javascript" src="/js/jquery-1.11.3.js"></script>
	<script type="text/javascript" src="/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="/js/jquery.ui.js"></script>
	<script type="text/javascript" src="/js/jquery.nouislider.js"></script>
	<script type="text/javascript" src="/js/sha256.js"></script>
	<script type="text/javascript" src="/js/main.js"></script>
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
</head>
<body>
	<nav class="navbar navbar-default navbar-fixed-top">
		<div class="container-fluid">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="/"><img alt="EmpireCoin Logo" id="nav_logo" src="/img/logo/icon-35x35.png" />EmpireCoin</a>
			</div>
			<div id="navbar" class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<li<?php if ($nav_tab_selected == "wallet" && $_REQUEST['do'] != "logout") echo ' class="active"'; ?>><a href="/wallet/"><?php if ($thisuser) echo "My Account"; else echo "Log In"; ?></a></li>
					<li<?php if ($nav_tab_selected == "download") echo ' class="active"'; ?>><a href="/download/">Download</a></li>
					<li<?php if ($nav_tab_selected == "explorer") echo ' class="active"'; ?>><a href="/explorer/">Explorer</a></li>
					<?php
					if ($thisuser || $_REQUEST['do'] == "logout") { ?>
						<li<?php if ($nav_tab_selected == "wallet" && $_REQUEST['do'] == "logout") echo ' class="active"'; ?>><a href="/wallet/?do=logout">Log Out</a></li>
						<?php
					}
					?>
				</ul>
			</div>
		</div>
	</nav>
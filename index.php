<!DOCTYPE html>
<html>
<head>
	<title>Отчет по ценам на проживание их внешних источников</title>
	<link rel="stylesheet" type="text/css" href="/bootstrap.min.css">
	<style type="text/css">
		ul {
			width:100%;
			padding:10px;
			margin:0;
		}
		li {
			margin:0 20px;
			/*border-left: 1px solid #c3c3c3;*/
		}
		.container > ul:nth-of-type(odd) {
			background: #f5f5f5;
		}
			 
	</style>
</head>
<body>
<div class="container">
	<h1>Отчет по ценам на проживание их внешних источников</h1>
<?php
$dates = [];
$files = glob(__DIR__.'/rosa/*.{xlsx}', GLOB_BRACE);
foreach($files as $file) {
 	preg_match('/\d{4}.\d\d\.\d\d/u',$file,$tmp);
 	if ($tmp[0]) $dates[] = $tmp[0];
}

$files = glob(__DIR__.'/gp/*.{xlsx}', GLOB_BRACE);
foreach($files as $file) {
 	preg_match('/\d{4}.\d\d\.\d\d/u',$file,$tmp);
 	if ($tmp[0]) $dates[] = $tmp[0];
}
rsort($dates = array_unique($dates));

foreach ($dates as $key => $value) {
	$tmp = explode(".", $value);
	$date = $tmp[2].".".$tmp[1].".".$tmp[0];
?>
	<ul class="list-inline"> 
		<li><?php echo $date;?></li>
<?php
	if (is_file(__DIR__."/rosa/res{$value}.xlsx")) {
		?>
			<li> <a href="/rosa/res<?php echo $value;?>.xlsx">Роза Хутор</a></li>
<?php
	}
	if (is_file(__DIR__."/gp/res{$value}.xlsx")) {
			?>
				<li> <a href="/gp/res<?php echo $value;?>.xlsx">Газпром</a></li>
	<?php
		}

?>
	</ul>
<?php

}

	
?>

 

		
	
</div>

</body>
</html>

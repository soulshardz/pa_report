<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Document</title>
</head>
<body>
	<h1>
		<?php echo $clientName; ?> <?php echo $period; ?> push report
	</h1>

	<h4>
		Results from <strong><?php echo $fromDate; ?></strong> to <strong><?php echo $toDate; ?></strong>
	</h4>

	<hr/>

	<table>
		<thead>
			<tr>
				<th>URL</th>
				<th>OK Count</th>
				<th>Failed Count</th>
				<th>#</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach( $aggregatedData as $data ):?>
			<tr>
				<td><?php echo $data[ "url" ]; ?></td>
				<td><?php echo $data[ "ok" ]; ?></td>
				<td><?php echo $data[ "failed" ]; ?></td>
				<td>&nbsp;</td>
			</tr>
			<?php endforeach;?>
		</tbody>
	</table>
</body>
</html>

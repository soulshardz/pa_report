<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Document</title>
</head>
<body>
	<h1>
		<?php echo $clientName; ?> push report
	</h1>

	<h4>
		Results from <strong><?php echo $from; ?></strong> to <strong><?php echo $to; ?></strong>
	</h4>

	<hr/>

	<table style="border: 1px solid #000; font-family: Verdana; text-align: right;">
		<thead>
			<tr>
				<th>URL</th>
				<th>OK Count</th>
				<th>Failed Count</th>
				<th>#</th>
			</tr>
		</thead>
		<tbody>
			<?php $cnt=0; ?>
			<?php foreach( $aggregatedData as $url => $data ):?>
			<?php $trStyle = (( $cnt%2 ) ? "" : "background-color: #ddd;" ); ?>
			<tr style="border: 1px solid #000;<?php echo $trStyle; ?>">
				<td><?php echo $url; ?></td>
				<td><?php echo $data[ "success" ]; ?></td>
				<td><?php echo $data[ "fail" ]; ?></td>
				<td>&nbsp;</td>
			</tr>
			<?php $cnt++; ?>
			<?php endforeach;?>
		</tbody>
	</table>
</body>
</html>

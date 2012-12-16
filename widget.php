<?php
/**
 * MediaCreeper dashboard widget page - rendering
 * (c) 2012 Martin Alice
 */

require_once(dirname(__FILE__) .'/Mediacreeper.class.php');
require_once(dirname(__FILE__) .'/MediacreeperAPI.class.php');

$MediacreeperPlugin = new Mediacreeper();
$api = new MediacreeperAPI();

global $wpdb;
if(!isset($wpdb))
	die;


$data = array();
$mediadata = array();

$aggregationTime = 3600;
$aggregated = array(
	'all' => array()
);

$min = $max = 0;
$refs = array();

$q =	"SELECT ts AS timestamp, ref AS referrer,
	n1.value AS name, n1.id AS name_id,
	n2.value AS site, n2.id AS site_id
	FROM $wpdb->mediacreeper m
	LEFT JOIN $wpdb->mediacreeper_names n1
		ON m.name_id=n1.id AND n1.kind='name'
	LEFT JOIN $wpdb->mediacreeper_names n2
		ON m.site_id=n2.id AND n2.kind='site'
	WHERE ts > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 3 DAY))";

$hits = $wpdb->get_results($q);
if(!empty($hits)) foreach($hits as $hit) {
	$key = $hit->timestamp - $hit->timestamp % $aggregationTime;

	if($min == 0 || $key < $min) $min = $key;
	if($key > $max) $max = $key;

	if(!isset($aggregated[$hit->name]))
		$aggregated[$hit->name] = array();

	if(($id = @url_to_postid($hit->referrer)) > 0)
		$refs[$hit->referrer] += 1;

	if(!isset($aggregated[$hit->name][$key])) $aggregated[$hit->name][$key] = 0;
	$aggregated[$hit->name][$key] += 1;

	if(!isset($aggregated['all'][$key])) $aggregated['all'][$key] = 0;
	$aggregated['all'][$key] += 1;
}


$labels = array();
$series = array(); /* Array of array of [timestamp, # hits] */
$pername = array(); /* Pie/bar chart: total hits per name */
foreach($aggregated as $label => $data) {
	$labels[] = $label;

	$pername[$label] = 0;

	$serie = array();
	foreach($data as $timestamp => $hits) {
		$serie[] = array($timestamp * 1000, $hits);
		$pername[$label] += $hits;
	}

	$series[] = $serie;
}

$piedata = array();
asort($pername);
foreach($pername as $label => $hits) {
	if($label === 'all') continue;
	if($hits < 2) continue;
	$obj = new stdClass();
	$obj->label = $label .' ('. $hits .')';
	$obj->data = $hits;
	$piedata[] = $obj;
}
?>
<div class="wrap">
	<?php if(empty($hits)): ?>
	<p>
		No data to display yet.<br />
		Go to the <a href="options-general.php?page=mediacreeper-settings&amp;cron=1">MediaCreeper settings</a> page to manually fetch data from the MediaCreeper.com API.
	</p>

	<?php else: ?>
	<div id="mediacreeper" style="width:400px;height:300px"></div>
	<script type="text/javascript">
	var series = <?php echo json_encode($series) ?>;
	var piedata = <?php echo json_encode($piedata) ?>;
	jQuery.plot(
		jQuery('#mediacreeper'),
		piedata,
		{
			legend: { position: "nw" },
			series: {
				pie: {
					show: true,
					radius: 0.9
				}
			}
			/*
			xaxis: {
				mode: "time",
				timeformat: "%d/%m kl %H"
			}
			*/
		}
	);
	</script>
	<?php endif; ?>
</div>

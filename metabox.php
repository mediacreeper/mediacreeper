<?php
/**
 * Mediacreeper metabox - rendering
 * (c) 2012 Martin Alice
 */

require_once(dirname(__FILE__) .'/Mediacreeper.class.php');
require_once(dirname(__FILE__) .'/MediacreeperAPI.class.php');
$MediacreeperPlugin = new Mediacreeper();
$api = new MediacreeperAPI();
?>
<div class="wrap">
	<?php if(count($hits)): ?>
	<ul>
		<?php foreach($hits as $hit): ?>
		<li>
			<?php echo strftime('kl %H:%M, %d %b, %Y', $hit->ts) ?>
			<a rel="nofollow" href="http://mediacreeper.com/name/<?php echo htmlspecialchars($hit->name) ?>"><?php echo htmlspecialchars($hit->name, ENT_NOQUOTES) ?></a>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php else: ?>
	No data available yet
	<?php endif; ?>
</div>

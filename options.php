<?php
/**
 * Mediacreeper options page - rendering
 * (c) 2012 Martin Alice
 */

require_once(dirname(__FILE__) .'/Mediacreeper.class.php');
require_once(dirname(__FILE__) .'/MediacreeperAPI.class.php');
$MediacreeperPlugin = new Mediacreeper();
$api = new MediacreeperAPI();

if(!function_exists('settings_fields'))
	exit;

$numFetched = 0;
if(isset($_GET['cron']))
	$numFetched = $MediacreeperPlugin->cronjob();

?>
<div class="wrap">
	<h2>Settings for MediaCreeper</h2>

	<?php if(isset($_REQUEST['cron'])): ?>
	<h4 style="color:red">Fetched the latest data from the MediaCreeper API (<?php echo $numFetched ?> entries)</h4>
	<?php endif; ?>

	<form method="post" action="options.php">

	<?php if(isset($_REQUEST['mcerror'])): ?>
	<h4 style="color:red">ERROR: <?php echo htmlspecialchars($_REQUEST['mcerror'], ENT_NOQUOTES) ?></h4>
	<?php endif; ?>

	<form method="post" action="options.php">
<?php
/**
 * Let Wordpress know what options we're about to modify
 * http://codex.wordpress.org/Creating_Options_Pages#settings_fields_Function
 */
$options = array();
settings_fields(Mediacreeper::$optionGroupName);
// do_settings_sections('plugin');

/* Load options */
$options = get_option(Mediacreeper::$optionName);
foreach($options as $key => $value) {
	if(in_array($key, array('site', 'site_id', 'show_metabox')))
		continue;
	echo '<input type="hidden" name="'. htmlspecialchars(Mediacreeper::$optionName .'['. $key .']') .'" value="'. htmlspecialchars($value) .'" />';
}

$defaultDomain = $MediacreeperPlugin->getServerName();
if(substr($defaultdomain, 0, 4) === 'www.')
	$defaultDomain = substr($defaultDomain, 4);
?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Monitoring site</th>
				<td>
					<input type="text"
						name="<?php echo htmlspecialchars(Mediacreeper::$optionName) ?>[site]"
						value="<?php echo htmlspecialchars($options['site']) ?>"
					/>
					<p class="description">
					The site to load from MediaCreeper, for example example.com.<br />
					Auto-detection thinks the correct value is
					<strong><a rel="nofollow" href="http://mediacreeper.com/site/<?php echo  htmlspecialchars($defaultDomain) ?>"><?php echo htmlspecialchars($defaultDomain, ENT_NOQUOTES) ?></a></strong>.
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Metabox in posts editor</th>
				<td>
					<fieldset>
						<label>
							<input type="radio"
								name="<?php echo htmlspecialchars(Mediacreeper::$optionName) ?>[show_metabox]"
								value="1"
								<?php if($options['show_metabox'] == 1): echo 'checked="checked"'; endif; ?>/>
							<span>Show metabox</span>
						</label>
						<br />
						<label>
							<input type="radio"
								name="<?php echo htmlspecialchars(Mediacreeper::$optionName) ?>[show_metabox]"
								value="0"
								<?php if($options['show_metabox'] == 0): echo 'checked="checked"'; endif; ?>/>
							<span>Hide metabox</span>
						</label>
					</fieldset>
					<p class="description">
					Whether or not to display a metabox inside the posts editor with data about recent visits.
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">MediaCreeper.com tracker tag</th>
				<td>
					<fieldset>
						<label>
							<input type="radio"
								name="<?php echo htmlspecialchars(Mediacreeper::$optionName) ?>[tracker_tag]"
								value="1"
								<?php if($options['tracker_tag'] == 1): echo 'checked="checked"'; endif; ?>/>
							<span>Insert tracker tag in footer</span>
						</label>
						<br />
						<label>
							<input type="radio"
								name="<?php echo htmlspecialchars(Mediacreeper::$optionName) ?>[tracker_tag]"
								value="0"
								<?php if($options['tracker_tag'] == 0): echo 'checked="checked"'; endif; ?>/>
							<span>I'll handle this myself</span>
						</label>
					</fieldset>
					<p class="description">
					Whether or not to let this plugin automatically insert the MediaCreeper.com <a href="http://mediacreeper.com/howto">tracker tag</a> in the footer of the blog's pages. If you already have the tracker tag on your pages, or want to place it elsewhere, select the later option.
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" class="button-primary" value="<?php echo 'Save Changes' ?>" />
		</p>
	</form>
</div>

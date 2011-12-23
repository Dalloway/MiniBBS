<?php
require './includes/bootstrap.php';
require './config/default_config.php';
force_id();
$template->title = 'Administrative dashboard';

if( ! $perm->get('admin_dashboard')) {
	error::fatal(m('Error: Access denied'));
}

if(isset($_POST['form_sent'])) {
	check_token();
	
	$_POST['form']['DEFAULT_MENU'] = trim(str_replace(array('  ', ']{'), array(' ', '] {'), $_POST['form']['DEFAULT_MENU']));
	if(empty($_POST['form']['DEFAULT_MENU'])) {
		/* Revert to the real default */
		$_POST['form']['DEFAULT_MENU'] = $config_defaults['DEFAULT_MENU'];
	}
	
	if(error::valid()) {
		/* $config_defaults comes from default_config.php */
		foreach($config_defaults as $name => $value) {
			if( ! isset($_POST['form'][$name])) {
				/* Must be an unticked checkbox */
				$_POST['form'][$name] = '0';
			}
			
			if($_POST['form'][$name] === 'on') {
				/* Must be a ticked checkbox */
				$_POST['form'][$name] = '1';
			}
			
			if( ! defined($name) || $_POST['form'][$name] != constant($name)) {
				$db->q('UPDATE config SET `value` = ? WHERE `name` = ?', $_POST['form'][$name], $name);
			}
		}
		
		cache::clear('config');
		/* Reload to view with the new configuration */
		redirect('Configuration updated.', 'admin_dashboard');
	}
}

?>
<ul class="menu">
	<li><a href="#dash_general">General</a></li>
	<li><a href="#dash_media">Media</a></li>
	<li><a href="#dash_captcha">Bot detection</a></li>
	<li><a href="#dash_permissions">Permissions</a></li>
	<li><a href="#dash_posts">Posts</a></li>
	<li><a href="#dash_pms">PMs</a></li>
	<li><a href="#dash_bulletins">Bulletins</a></li>
	<li><a href="#dash_cryptography">Cryptography</a></li>
</ul>

<p>Most board settings can be managed from here, but database credentials, the site URL, caching and error handling can only be reconfigured by editing <kbd>config.php</kbd>.</p>

<form action="" method="post">
	<?php echo csrf_token() ?>

<h4 class="section" id="dash_general">General</h4>
	<div>
		<label class="common" for="SITE_TITLE">Site title</label>
		<input type="text" id="SITE_TITLE" name="form[SITE_TITLE]" class="inline" value="<?php echo htmlspecialchars(SITE_TITLE) ?>" maxlength="100" />
		<p class="caption">The name of your forum, as shown in the header and various other places.</p>

	</div>
	
	<div>
		<label class="common" for="DEFAULT_STYLESHEET">Default stylesheet</label>
		<select id="DEFAULT_STYLESHEET" name="form[DEFAULT_STYLESHEET]" class="inline">
        <?php
		$available_styles = get_styles();
		foreach($available_styles as $style) {
			echo '<option value="'.htmlspecialchars($style).'"' . (DEFAULT_STYLESHEET == $style ? ' selected' : '') . '>'.htmlspecialchars($style).'</option>';
		}
		?>
		</select>
		
		<p class="caption">The board's default style.</p>
	</div>
	
	<div>
		<label class="common" for="MAILER_ADDRESS">Mailer address</label>
		<input type="text" id="MAILER_ADDRESS" name="form[MAILER_ADDRESS]" class="inline" value="<?php echo htmlspecialchars(MAILER_ADDRESS) ?>" maxlength="100" />
		<p class="caption">E-mail sent by the forum software (such as ID recovery messages) will appear to come from this address.</p>
	</div>
	
	<div class="row">
		<label class="common" for="DEFAULT_MENU">Default menu</label>
		<textarea id="DEFAULT_MENU" name="form[DEFAULT_MENU]" class="inline" rows="2" style="width:60%;"><?php echo htmlspecialchars(DEFAULT_MENU) ?></textarea>
		
		<p class="caption"><strong>Standard options</strong> (click to add): 
<?php
		foreach($template->menu_options as $text => $path):
			$text = str_replace('New topic', 'New_topic', $text);
?>
			· <span onclick="document.getElementById('DEFAULT_MENU').value += ' <?php echo $text ?>';"><?php echo $text ?></span> 
<?php
		endforeach;
?>
		</p>
		
		<p class="caption">Aside from the standard items above, you can add custom links in the form of <kbd>[/dashboard My dashboard]</kbd> or <kbd>[http://example.com Example]</kbd>. You can add custom submenus by surrounding a block of links with curly brackets <kbd>{}</kbd> immediately after their parent. For example, "<kbd>[/normal Normal] [/parent Parent] {[/child1 Child 1] [http://example.com Child 2]} Stuff</kbd>".</p>

	</div>
	
	<div>
		<label class="common" for="POSTS_PER_PAGE_DEFAULT">Posts per topic page</label>

		<input type="text" id="POSTS_PER_PAGE_DEFAULT" name="form[POSTS_PER_PAGE_DEFAULT]" class="inline" value="<?php echo htmlspecialchars(POSTS_PER_PAGE_DEFAULT) ?>" size="4" maxlength="6" />
		<p class="caption">The maximum number of replies shown on each page of a topic. To disable pagination and show all replies at once, use 0. (The user can override this setting from their dashboard.)</p>
	</div>
	
	<div>
		<label class="common" for="ITEMS_PER_PAGE">Items per page</label>
		<input type="text" id="ITEMS_PER_PAGE" name="form[ITEMS_PER_PAGE]" class="inline" value="<?php echo htmlspecialchars(ITEMS_PER_PAGE) ?>" size="3" maxlength="5" />
		<p class="caption">The number of items to display per page for tabulated data (e.g., the number of topics on the index).</p>
	</div>
	
	<div>
		<label class="common" for="MEMORABLE_TOPICS">Memorable topics</label>
		<input type="text" id="MEMORABLE_TOPICS" name="form[MEMORABLE_TOPICS]" class="inline" value="<?php echo htmlspecialchars(MEMORABLE_TOPICS) ?>" size="4" maxlength="8" />

		<p class="caption">For performance reasons, a UID will only track the reply count of the last <em><?php echo number_format(MEMORABLE_TOPICS) ?></em> topics it has visited.</em></p>
	</div>
	
	<div>
		<label class="common" for="MOD_GZIP">Enable GZIP</label>
		<input type="checkbox" value="1" id="MOD_GZIP" name="form[MOD_GZIP]" class="inline" <?php if(MOD_GZIP) echo ' checked="checked"' ?>  />
		<p class="caption">Should PHP compress pages before sending them?</p>
	</div>
	
<h4 class="section" id="dash_media">Media</h4>
	<div>
		<label class="common" for="ALLOW_IMAGES">Allow uploads</label>
		<input type="checkbox" value="1" id="ALLOW_IMAGES" name="form[ALLOW_IMAGES]" class="inline"<?php if(ALLOW_IMAGES) echo ' checked="checked"' ?> />
		<p class="caption">Should users be able to upload an image with their post?</p>
	</div>
	
	<div>
		<label class="common" for="IMAGEMAGICK">Use ImageMagick</label>

		<input type="checkbox" value="1" id="IMAGEMAGICK" name="form[IMAGEMAGICK]" class="inline" <?php if(IMAGEMAGICK) echo ' checked="checked"' ?> />
		<p class="caption">Should we use ImageMagick to generate thumbnails? If not, we'll use GD. (ImageMagick is much better, but less likely to be available.)</p>
	</div>

	
	<div>
		<label class="common" for="MAX_IMAGE_SIZE">Max image size</label>
		<input type="text" id="MAX_IMAGE_SIZE" name="form[MAX_IMAGE_SIZE]" class="inline" value="<?php echo htmlspecialchars(MAX_IMAGE_SIZE) ?>" size="9" maxlength="12" />
		<p class="caption">The maximum file size of an image, in bytes.</p>
	</div>
	
	<div>
		<label class="common" for="MAX_IMAGE_DIMENSIONS">Max thumbnail dimensions</label>
		<input type="text" id="MAX_IMAGE_DIMENSIONS" name="form[MAX_IMAGE_DIMENSIONS]" class="inline" value="<?php echo htmlspecialchars(MAX_IMAGE_DIMENSIONS) ?>" size="4" maxlength="6" />
		<p class="caption">The maximum height or width of a thumbnail in pixels.</p>

	</div>
	
	<div>
		<label class="common" for="MAX_GIF_DIMENSIONS">Max GIF thumb dimensions</label>
		<input type="text" id="MAX_GIF_DIMENSIONS" name="form[MAX_GIF_DIMENSIONS]" class="inline" value="<?php echo htmlspecialchars(MAX_GIF_DIMENSIONS) ?>" size="4" maxlength="6" />
		<p class="caption">If ImageMagick is enabled, the maximum height or width of a thumbnail for an animated GIF.</p>
	</div>
	
	<div>
		<label class="common" for="IMGUR_KEY">Imgur API key</label>
		<input type="text" id="IMGUR_KEY" name="form[IMGUR_KEY]" class="inline" value="<?php echo htmlspecialchars(IMGUR_KEY) ?>" size="57" maxlength="100" />
		<p class="caption">You can register for an anonymous key <a href="http://imgur.com/register/api_anon">here</a>. When present, users will be able to upload images directly to Imgur (regardless of the "Allow uploads" option above).</p>
	</div>
		
	<div>
		<label class="common" for="FANCY_IMAGE">Fancy image</label>
		<input type="checkbox" value="1" id="FANCY_IMAGE" name="form[FANCY_IMAGE]" class="inline" <?php if(FANCY_IMAGE) echo ' checked="checked"' ?> />
		<p class="caption">Should we use the JavaScript image viewer (Thickbox)?</p>
	</div>

	
	<div>
		<label class="common" for="EMBED_VIDEOS">Embed videos</label>
		<input type="checkbox" value="1" id="EMBED_VIDEOS" name="form[EMBED_VIDEOS]" class="inline" <?php if(EMBED_VIDEOS) echo ' checked="checked"' ?> />
		<p class="caption">Should YouTube and other streaming videos be playable inline?</p>
	</div>

<h4 class="section" id="dash_captcha">Bot detection</h4>
	<div>
		<label class="common" for="RECAPTCHA_ENABLE">Bot detection CAPTCHA</label>
		<input type="checkbox" value="1" id="RECAPTCHA_ENABLE" name="form[RECAPTCHA_ENABLE]" class="inline"<?php if(RECAPTCHA_ENABLE) echo ' checked="checked"' ?> />
		<p class="caption">Should IP addresses that behave like bots be forced to fill in a CAPTCHA?</p>
	</div>

	
	<div>
		<label class="common" for="RECAPTCHA_PUBLIC_KEY">reCAPTCHA public key</label>
		<input type="text" id="RECAPTCHA_PUBLIC_KEY" name="form[RECAPTCHA_PUBLIC_KEY]" class="inline" value="<?php echo htmlspecialchars(RECAPTCHA_PUBLIC_KEY) ?>" size="57" maxlength="100" />
	</div>
	
	<div>
		<label class="common" for="RECAPTCHA_PRIVATE_KEY">reCAPTCHA private key</label>
		<input type="text" id="RECAPTCHA_PRIVATE_KEY" name="form[RECAPTCHA_PRIVATE_KEY]" class="inline" value="<?php echo htmlspecialchars(RECAPTCHA_PRIVATE_KEY) ?>" size="57" maxlength="100" />
		<p class="caption">If CAPTCHAs are enabled, you'll need to generate a pair of keys using <a href="http://www.google.com/recaptcha">Google's free service</a>.</p>
	</div>
	
	<div>

		<label class="common" for="RECAPTCHA_MAX_UIDS_PER_HOUR">Max UIDs per hour</label>
		<input type="text" id="RECAPTCHA_MAX_UIDS_PER_HOUR" name="form[RECAPTCHA_MAX_UIDS_PER_HOUR]" class="inline" value="<?php echo htmlspecialchars(RECAPTCHA_MAX_UIDS_PER_HOUR) ?>" size="5" maxlength="9" />
		<p class="caption">The maximum number of UIDs that can created per hour by a single IP address.</p>
	</div>
	
	<div>
		<label class="common" for="RECAPTCHA_MAX_SEARCHES_PER_MIN">Max searches per minute</label>
		<input type="text" id="RECAPTCHA_MAX_SEARCHES_PER_MIN" name="form[RECAPTCHA_MAX_SEARCHES_PER_MIN]" class="inline" value="<?php echo htmlspecialchars(RECAPTCHA_MAX_SEARCHES_PER_MIN) ?>" size="5" maxlength="9" />
		<p class="caption">The maximum number of searches per minute that can be performed by a single IP address.</p>
	</div>
	
<h4 class="section" id="dash_permissions">Permissions</h4>
	<div>
		<label class="common" for="ALLOW_BAN_APPEALS">Allow ban appeals</label>
		<input type="checkbox" value="1" id="ALLOW_BAN_APPEALS" name="form[ALLOW_BAN_APPEALS]" class="inline" <?php if(ALLOW_BAN_APPEALS) echo ' checked="checked"' ?>  />
		<p class="caption">Should banned users be able to send a single PM appealing their ban?</p>
	</div>
	
	<div>
		<label class="common" for="ALLOW_BAN_READING">Allow ban reading</label>
		<input type="checkbox" value="1" id="ALLOW_BAN_READING" name="form[ALLOW_BAN_READING]" class="inline" <?php if(ALLOW_BAN_READING) echo ' checked="checked"' ?>  />

		<p class="caption">Should banned users be able to view the board (but not do anything)?</p>
	</div>
	
	<div>
		<label class="common" for="POSTS_TO_DEFY_SEARCH_DISABLED">Posts to defy disabled search</label>
		<input type="text" id="POSTS_TO_DEFY_SEARCH_DISABLED" name="form[POSTS_TO_DEFY_SEARCH_DISABLED]" class="inline" value="<?php echo htmlspecialchars(POSTS_TO_DEFY_SEARCH_DISABLED) ?>" size="3" maxlength="8" />
		<p class="caption">The minimum number of posts required to search when searching is disabled for new users.</p>
	</div>
	
	<div>
		<label class="common" for="POSTS_TO_DEFY_DEFCON_3">Posts to defy DEFCON 3</label>
		<input type="text" id="POSTS_TO_DEFY_DEFCON_3" name="form[POSTS_TO_DEFY_DEFCON_3]" class="inline" value="<?php echo htmlspecialchars(POSTS_TO_DEFY_DEFCON_3) ?>" size="3" maxlength="8" />

		<p class="caption">The minimum number of posts required to bypass DEFCON 3 restrictions.</p>
	</div>
	
<h4 class="section" id="dash_posts">Posts</h4>
	<div>
		<label class="common" for="MAX_LENGTH_HEADLINE">Max headline length</label>
		<input type="text" id="MAX_LENGTH_HEADLINE" name="form[MAX_LENGTH_HEADLINE]" class="inline" value="<?php echo htmlspecialchars(MAX_LENGTH_HEADLINE) ?>" size="3" maxlength="5" />
		<p class="caption">The maximum number of characters in a headline.</p>

	</div>
	
	<div>
		<label class="common" for="MIN_LENGTH_HEADLINE">Minimum headline length</label>
		<input type="text" id="MIN_LENGTH_HEADLINE" name="form[MIN_LENGTH_HEADLINE]" class="inline" value="<?php echo htmlspecialchars(MIN_LENGTH_HEADLINE) ?>" size="3" maxlength="3" />
		<p class="caption">The minimum number of characters in a headline.</p>
	</div>
	
	<div>
		<label class="common" for="MAX_LENGTH_BODY">Max body length</label>

		<input type="text" id="MAX_LENGTH_BODY" name="form[MAX_LENGTH_BODY]" class="inline" value="<?php echo htmlspecialchars(MAX_LENGTH_BODY) ?>" size="4" maxlength="8" />
		<p class="caption">The maximum number of characters in a post body.</p>
	</div>
	
	<div>
		<label class="common" for="MIN_LENGTH_BODY">Minimum body length</label>
		<input type="text" id="MIN_LENGTH_BODY" name="form[MIN_LENGTH_BODY]" class="inline" value="<?php echo htmlspecialchars(MIN_LENGTH_BODY) ?>" size="4" maxlength="8"  />
		<p class="caption">The minimum number of characters in a post body.</p>
	</div>

	
	<div>
		<label class="common" for="MAX_LINES">Max lines per post</label>
		<input type="text" id="MAX_LINES" name="form[MAX_LINES]" class="inline" value="<?php echo htmlspecialchars(MAX_LINES) ?>" size="4" maxlength="8" />
		<p class="caption">The maximum number of lines (newlines) in a post body.</p>
	</div>
	
	<div>
		<label class="common" for="REQUIRED_LURK_TIME_REPLY">Required lurk time (replies)</label>
		<input type="text" id="REQUIRED_LURK_TIME_REPLY" name="form[REQUIRED_LURK_TIME_REPLY]" class="inline" value="<?php echo htmlspecialchars(REQUIRED_LURK_TIME_REPLY) ?>" size="3" maxlength="8" />
		<p class="caption">The number of seconds that a UID must lurk the board before posting a reply.</p>
	</div>
	
	<div>

		<label class="common" for="REQUIRED_LURK_TIME_TOPIC">Required lurk time (topics)</label>
		<input type="text" id="REQUIRED_LURK_TIME_TOPIC" name="form[REQUIRED_LURK_TIME_TOPIC]" class="inline" value="<?php echo htmlspecialchars(REQUIRED_LURK_TIME_TOPIC) ?>" size="3" maxlength="8" />
		<p class="caption">The number of seconds that a UID must lurk the board before posting a topic.</p>
	</div>
	
	<div>
		<label class="common" for="FLOOD_CONTROL_REPLY">Flood control (replies)</label>
		<input type="text" id="FLOOD_CONTROL_REPLY" name="form[FLOOD_CONTROL_REPLY]" class="inline" value="<?php echo htmlspecialchars(FLOOD_CONTROL_REPLY) ?>" size="3" maxlength="8" />
		<p class="caption">The number of seconds that a user must wait before posting another reply.</p>

	</div>
	
	<div>
		<label class="common" for="FLOOD_CONTROL_TOPIC">Flood control (topics)</label>
		<input type="text" id="FLOOD_CONTROL_TOPIC" name="form[FLOOD_CONTROL_TOPIC]" class="inline" value="<?php echo htmlspecialchars(FLOOD_CONTROL_TOPIC) ?>" size="3" maxlength="8" />
		<p class="caption">The number of seconds that a user must wait before posting another topic.</p>
	</div>
	
	<div>
		<label class="common" for="AUTOLOCK">Autolock time</label>
		<input type="text" id="AUTOLOCK" name="form[AUTOLOCK]" class="inline" value="<?php echo htmlspecialchars(AUTOLOCK) ?>" size="9" maxlength="13" />
		<p class="caption">To prevent necrobumping, after how many seconds from the last reply should a topic autolock? (To disable autolock, use 0.)</p>
	</div>

	<div>
		<label class="common" for="SIGNATURES">Allow signatures</label>
		<input type="checkbox" value="1" id="SIGNATURES" name="form[SIGNATURES]" class="inline"  <?php if(SIGNATURES) echo ' checked="checked"' ?> />
		<p class="caption">Should users be able to sign their posts and PMs with a colored bar representing their UID by typing <kbd>~~~~</kbd>?</p>
	</div>
	
	<div>
		<label class="common" for="FORCED_ANON">Forced anon</label>
		<input type="checkbox" value="1" id="FORCED_ANON" name="form[FORCED_ANON]" class="inline"  <?php if(FORCED_ANON) echo ' checked="checked"' ?> />
		<p class="caption">Should users be disallowed from posting with a name? (This doesn't affect posters who can link their name, such as mods.)</p>
	</div>
	
<h4 class="section" id="dash_pms">Private messages</h4>
	<div>
		<label class="common" for="ALLOW_USER_PM">Allow user-to-user PMs</label>
		<input type="checkbox" value="1" id="ALLOW_USER_PM" name="form[ALLOW_USER_PM]" class="inline"  <?php if(ALLOW_USER_PM) echo ' checked="checked"' ?> />
		<p class="caption">Should users be able to PM other users?</p>
	</div>
	
	<div>

		<label class="common" for="POSTS_FOR_USER_PM">Minimum posts to PM</label>
		<input type="text" id="POSTS_FOR_USER_PM" name="form[POSTS_FOR_USER_PM]" class="inline" value="<?php echo htmlspecialchars(POSTS_FOR_USER_PM) ?>" size="3" maxlength="8" />
		<p class="caption">The minimum number of posts required to send a PM.</p>
	</div>
	
	<div>
		<label class="common" for="FLOOD_CONTROL_PM">Flood control (PMs)</label>
		<input type="text" id="FLOOD_CONTROL_PM" name="form[FLOOD_CONTROL_PM]" class="inline" value="<?php echo htmlspecialchars(FLOOD_CONTROL_PM) ?>" size="3" maxlength="8" />
		<p class="caption">The number of seconds that a user must wait between sending PMs.</p>

	</div>
	
	<div>
		<label class="common" for="MAX_GLOBAL_PM">Max global PMs</label>
		<input type="text" id="MAX_GLOBAL_PM" name="form[MAX_GLOBAL_PM]" class="inline" value="<?php echo htmlspecialchars(MAX_GLOBAL_PM) ?>" size="3" maxlength="8" />
		<p class="caption">The maximum number of PMs that can be sent over a period of five minutes by <em>all</em> users combined.</p>
	</div>
	
<h4 class="section" id="dash_bulletins">Bulletins</h4>
		
	<div>
		<label class="common" for="MIN_BULLETIN_POSTS">Minimum posts for bulletin</label>
		<input type="text" id="MIN_BULLETIN_POSTS" name="form[MIN_BULLETIN_POSTS]" class="inline" value="<?php echo htmlspecialchars(MIN_BULLETIN_POSTS) ?>" size="3" maxlength="8" />
		<p class="caption">How many posts should a user have before they can post a bulletin?</p>
	</div>

	
	<div>
		<label class="common" for="FLOOD_CONTROL_BULLETINS">Flood control (bulletins)</label>
		<input type="text" id="FLOOD_CONTROL_BULLETINS" name="form[FLOOD_CONTROL_BULLETINS]" class="inline" value="<?php echo htmlspecialchars(FLOOD_CONTROL_BULLETINS) ?>" size="3" maxlength="8" />
		<p class="caption">How many seconds must a user wait between posting bulletins?</p>
	</div>
	
	<div>
		<label class="common" for="BULLETINS_ON_INDEX">Bulletins on index</label>
		<input type="text" id="BULLETINS_ON_INDEX" name="form[BULLETINS_ON_INDEX]" class="inline" value="<?php echo htmlspecialchars(BULLETINS_ON_INDEX) ?>" size="3" maxlength="2" />

		<p class="caption">How many unread bulletins should be displayed on the board index? (0 to disable.)</p>
	</div>
	
<h4 class="section" id="dash_cryptography">Cryptography</h4>
	<p><strong>WARNING:</strong> Altering these values will invalidate any memorable passwords and/or secure tripcodes set before the change.</p>

	<div>
		<label class="common" for="USE_SHA256">Use SHA256</label>
		<input type="checkbox" value="1" id="USE_SHA256" name="form[USE_SHA256]" class="inline" <?php if(USE_SHA256) echo ' checked="checked"' ?> />
		<p class="caption"></p>
	</div>

	<div>
		<label class="common" for="SALT">Hash salt</label>
		<input type="text" id="SALT" name="form[SALT]" class="inline" value="<?php echo htmlspecialchars(SALT) ?>" size="80" />
		<p class="caption"></p>
	</div>
	
	<div>
		<label class="common" for="STRETCH">Hash stretch</label>
		<input type="text" id="STRETCH" name="form[STRETCH]" class="inline" value="<?php echo htmlspecialchars(STRETCH) ?>" size="2" maxlength="3" />
		<p class="caption"></p>

	</div>
	
	<div>
		<label class="common" for="TRIP_SEED">Tripcode salt</label>

		<input type="text" id="TRIP_SEED" name="form[TRIP_SEED]" class="inline" value="<?php echo htmlspecialchars(TRIP_SEED) ?>" size="80" />
		<p class="caption"></p>
	</div>
	
	<div class="row">
		<input type="submit" name="form_sent" value="Save settings" />
	</div>

</form>
	
<?php
$template->render();
?>
<?php

# plugin id from filename
$thisfile = basename(__FILE__, ".php");

# register plugin in Settings
register_plugin(
	$thisfile,
	'GS Hide Tabs',
	'2.0',
	'risingisland',
	'https://getsimple-ce.ovh/donate',
	'Hide admin navigation tabs and elements or modify their CSS properties per user.',
	'settings',
	'gstabs_admin_page'
);

# add a link in Settings menu
add_action('settings-sidebar','createSideMenu',array($thisfile,'GS Hide Tabs 
<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="1.5em" height="1.5em" viewBox="0 0 24 24"><rect width="24" height="24" fill="none"/><path fill="#000" d="M6.75 3A4.75 4.75 0 0 0 2 7.75v7A2.25 2.25 0 0 0 4.25 17h.25V8.75A3.25 3.25 0 0 1 7.75 5.5H19v-.25A2.25 2.25 0 0 0 16.75 3zm12.504 3.5h.496a2.25 2.25 0 0 1 2.245 2.096L22 8.75v1.004a.75.75 0 0 1-1.493.101l-.007-.101V8.75a.75.75 0 0 0-.648-.743L19.75 8h-.496a.75.75 0 0 1-.102-1.493zm-13.004 5a.75.75 0 0 1 .743.649l.007.102v2.494a.75.75 0 0 1-1.493.102l-.007-.102v-2.494a.75.75 0 0 1 .75-.75m.743 5.643a.75.75 0 0 0-1.493.102v1.005l.005.154A2.25 2.25 0 0 0 7.75 20.5h.5l.102-.007A.75.75 0 0 0 8.25 19h-.5l-.102-.007A.75.75 0 0 1 7 18.25v-1.005zM22 17.246a.75.75 0 1 0-1.5 0v1.005a.75.75 0 0 1-.75.75h-1.003a.75.75 0 0 0 0 1.5h1.003A2.25 2.25 0 0 0 22 18.25zM14.753 19h1.495a.75.75 0 0 1 .102 1.493l-.102.007h-1.495a.75.75 0 0 1-.102-1.493zm-2.507 0h-1.495l-.102.007a.75.75 0 0 0 .102 1.493h1.495l.102-.007A.75.75 0 0 0 12.246 19m9.747-6.851a.75.75 0 0 0-1.493.102v2.494l.007.102A.75.75 0 0 0 22 14.745v-2.494zM9.503 7.25a.75.75 0 0 0-.75-.75H7.75A2.25 2.25 0 0 0 5.5 8.75v1.004a.75.75 0 0 0 1.5 0V8.75A.75.75 0 0 1 7.75 8h1.003a.75.75 0 0 0 .75-.75m5.746-.75h1.503a.75.75 0 0 1 .102 1.493L16.752 8h-1.503a.75.75 0 0 1-.102-1.493zm-2.504 0H11.25l-.102.007A.75.75 0 0 0 11.25 8h1.495l.102-.007a.75.75 0 0 0-.102-1.493"/></svg>'));

# ----------------------------------------------------------
#  FILE PATH FOR STORAGE
# ----------------------------------------------------------
define('GSTABS_FILE', GSDATAOTHERPATH . 'gs-hide-tabs.json');

# ----------------------------------------------------------
#  LOAD SETTINGS
# ----------------------------------------------------------
function gstabs_load() {
	if (!file_exists(GSTABS_FILE)) return array();
	$json = file_get_contents(GSTABS_FILE);
	$data = json_decode($json, true);
	return is_array($data) ? $data : array();
}

# ----------------------------------------------------------
#  SAVE SETTINGS
# ----------------------------------------------------------
function gstabs_save($data) {
	// ensure folder exists
	$dir = dirname(GSTABS_FILE);
	if (!is_dir($dir)) @mkdir($dir, 0755, true);
	file_put_contents(GSTABS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

# ----------------------------------------------------------
#  GET EXISTING GETSIMPLE USERS WITH ACTUAL USERNAMES
#  Reads the <USR> tag from XML files to get real usernames
#  including special characters like info@domain.com
# ----------------------------------------------------------
function gstabs_get_users() {
	$users = array();
	$dir = GSDATAPATH . 'users/';

	if (!is_dir($dir)) return $users;

	foreach (glob($dir.'*.xml') as $file) {
		// Try to read the actual username from XML
		$xml_content = @file_get_contents($file);
		if ($xml_content !== false) {
			// Try to parse the <USR> tag
			if (preg_match('/<USR>([^<]+)<\/USR>/', $xml_content, $matches)) {
				$username = $matches[1];
				$users[] = $username;
			} else {
				// Fallback to filename if XML parsing fails
				$name = basename($file, '.xml');
				$users[] = $name;
			}
		}
	}

	sort($users);
	return $users;
}

# ----------------------------------------------------------
#  PARSE RULES (from raw textarea) into structured array
#  Supports formats:
#	id:nav_pages
#	class:delconfirm
#	class:delconfirm within:#pages
#	selector:#pages .delconfirm
#	CSS modifications with {property:value}
#	id:nav_pages {opacity:0.5}
#	class:delconfirm {border:2px solid red; opacity:0.7}
#	selector:#pages .delconfirm {background:yellow}
# ----------------------------------------------------------
function gstabs_parse_raw($raw) {
	$out = array();
	$lines = preg_split("/\r\n|\n|\r/", $raw);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '') continue;
		if (strpos($line, ':') === false) continue;
		// username: items...
		list($user, $items_part) = explode(':', $line, 2);
		$user = trim($user);
		if ($user === '') continue;
		$items = array();
		// split by comma (naive)
		$parts = array_map('trim', explode(',', $items_part));
		foreach ($parts as $p) {
			if ($p === '') continue;
			
			// Extract CSS modifications in {...}
			$css_mods = '';
			$action = 'hide'; // default action
			if (preg_match('/\{([^}]+)\}/', $p, $matches)) {
				$css_mods = trim($matches[1]);
				$action = 'modify';
				// Remove the {...} part from $p
				$p = trim(preg_replace('/\{[^}]+\}/', '', $p));
			}
			
			// parse within: if exists
			$within = '';
			if (stripos($p, ' within:') !== false) {
				$sub = preg_split('/\s+within:/i', $p);
				$p = trim($sub[0]);
				$within = trim($sub[1]);
			} elseif (stripos($p, 'within:') !== false) {
				$sub = preg_split('/within:/i', $p);
				$p = trim($sub[0]);
				$within = trim($sub[1]);
			}

			// normalize
			$p = trim($p);
			if (stripos($p, 'id:') === 0) {
				$value = trim(substr($p,3));
				if ($value === '') continue;
				$items[] = array('type'=>'id','value'=>$value,'within'=>$within,'action'=>$action,'css'=>$css_mods);
			} elseif (stripos($p, 'class:') === 0) {
				$value = trim(substr($p,6));
				if ($value === '') continue;
				$items[] = array('type'=>'class','value'=>$value,'within'=>$within,'action'=>$action,'css'=>$css_mods);
			} elseif (stripos($p, 'selector:') === 0) {
				$value = trim(substr($p,9));
				if ($value === '') continue;
				$items[] = array('type'=>'selector','value'=>$value,'within'=>$within,'action'=>$action,'css'=>$css_mods);
			} else {
				// fallback: treat as id if it matches id name, else selector
				if (preg_match('/^[A-Za-z0-9\-_]+$/', $p)) {
					$items[] = array('type'=>'id','value'=>$p,'within'=>$within,'action'=>$action,'css'=>$css_mods);
				} else {
					$items[] = array('type'=>'selector','value'=>$p,'within'=>$within,'action'=>$action,'css'=>$css_mods);
				}
			}
		}
		if (!empty($items)) {
			$out[$user] = $items;
		}
	}
	return $out;
}

# ----------------------------------------------------------
#  ADMIN PAGE (TEXTAREA RULES + USER DROPDOWN + helper table)
# ----------------------------------------------------------
function gstabs_admin_page() {
	global $SITEURL;
	global $USR;
	$data = gstabs_load();
	$saved_raw = isset($data['_raw']) ? $data['_raw'] : '';

	// Save handler
	if (isset($_POST['gstabs-save'])) {
		$input = trim($_POST['gstabs-rules']);
		$parsed = gstabs_parse_raw($input);
		// store raw and parsed
		$store = $parsed;
		$store['_raw'] = $input;
		gstabs_save($store);
		echo '
<div class="updated">Settings saved. Redirecting in 3 seconds...</div>
<script>
	setTimeout(function() {
		window.location.href = window.location.href;
	}, 3000);
</script>';
		$saved_raw = $input;
	}

	// Prepare users list
	$users = gstabs_get_users();

	// Admin page HTML
	echo '
	<link rel="stylesheet" href="'.$SITEURL.'plugins/UpdateCE/assets/w3.css">
	<style>
		.w3-tiny {padding: 3px 7px; border-radius: 5px;}
		.w3-parent code {font-size: .85em;}
		textarea {color: #0000CD!important; font-size:14px!important; border-radius:5px!important; background:#E6E6E6; border:solid 1px #999!important;}
		#gstabs-helper {margin:20px 10px; padding:20px 20px 0 20px; border:solid #B3B3B3 1px; border-radius:5px;}
		.cke {margin:5px 0 0 15px;}
		.wrapper p {line-height: 1.3em;}
		.example-box {background: #f0f8ff; padding: 10px; border-left: 3px solid #2196F3; margin: 10px 0;}
		.donateButton{ background:orange;padding: 10px; border-radius:5px;}
		.donateButton:hover{ background:darkorange;}
	</style>';
	
	echo '
<div class="w3-parent ">
	<header class="w3-container w3-border-bottom w3-margin-bottom">
		<h3>GS Hide Tabs 
		<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="1.2em" height="1.2em" viewBox="0 0 24 24"><rect width="24" height="24" fill="none"/><path fill="#000" d="M6.75 3A4.75 4.75 0 0 0 2 7.75v7A2.25 2.25 0 0 0 4.25 17h.25V8.75A3.25 3.25 0 0 1 7.75 5.5H19v-.25A2.25 2.25 0 0 0 16.75 3zm12.504 3.5h.496a2.25 2.25 0 0 1 2.245 2.096L22 8.75v1.004a.75.75 0 0 1-1.493.101l-.007-.101V8.75a.75.75 0 0 0-.648-.743L19.75 8h-.496a.75.75 0 0 1-.102-1.493zm-13.004 5a.75.75 0 0 1 .743.649l.007.102v2.494a.75.75 0 0 1-1.493.102l-.007-.102v-2.494a.75.75 0 0 1 .75-.75m.743 5.643a.75.75 0 0 0-1.493.102v1.005l.005.154A2.25 2.25 0 0 0 7.75 20.5h.5l.102-.007A.75.75 0 0 0 8.25 19h-.5l-.102-.007A.75.75 0 0 1 7 18.25v-1.005zM22 17.246a.75.75 0 1 0-1.5 0v1.005a.75.75 0 0 1-.75.75h-1.003a.75.75 0 0 0 0 1.5h1.003A2.25 2.25 0 0 0 22 18.25zM14.753 19h1.495a.75.75 0 0 1 .102 1.493l-.102.007h-1.495a.75.75 0 0 1-.102-1.493zm-2.507 0h-1.495l-.102.007a.75.75 0 0 0 .102 1.493h1.495l.102-.007A.75.75 0 0 0 12.246 19m9.747-6.851a.75.75 0 0 0-1.493.102v2.494l.007.102A.75.75 0 0 0 22 14.745v-2.494zM9.503 7.25a.75.75 0 0 0-.75-.75H7.75A2.25 2.25 0 0 0 5.5 8.75v1.004a.75.75 0 0 0 1.5 0V8.75A.75.75 0 0 1 7.75 8h1.003a.75.75 0 0 0 .75-.75m5.746-.75h1.503a.75.75 0 0 1 .102 1.493L16.752 8h-1.503a.75.75 0 0 1-.102-1.493zm-2.504 0H11.25l-.102.007A.75.75 0 0 0 11.25 8h1.495l.102-.007a.75.75 0 0 0-.102-1.493"/></svg></h3>
		<p>Define which navigation tabs, sidebar elements or selectors to hide or modify, per user.</p>
	</header>
	
	<div class="w3-container">
		<p><strong>Select User:</strong></p>
		<select id="gstabs-user-select" class="w3-select w3-border w3-round" style="max-width:300px">
			<option value="">-- Select User --</option>';
	foreach ($users as $u) {
		echo '<option value="' . htmlspecialchars($u) . '">' . htmlspecialchars($u) . '</option>';
	}
	echo '
		</select>
		
		<button id="gstabs-insert-user" class="w3-btn w3-blue w3-round">Insert User Template</button>
		
	</div>
	
	<div class="w3-container w3-margin-top">
		<button id="gstabs-toggle-helper" class="w3-btn w3-teal w3-round">Show/Hide Help</button>
		
		<div id="gstabs-helper">
			<p style="margin-top:10px; font-size:0.9em; color:#666;">
				<strong>Note:</strong> The dropdown shows actual usernames (including special characters like info@domain.com). 
				Use these exact usernames in your rules.
			</p>
			<p><b>Rule format</b> (one user per line):</p>
			
			<div class="example-box">
				<p><strong>To HIDE elements:</strong></p>
				<pre class="cke">username1: id:nav_theme, id:nav_plugins, class:delconfirm</pre>
				<pre class="cke">username2: id:nav_upload, id:sb_newpage, id:sb_menumanager</pre>
			</div>
			
			<div class="example-box">
				<p><strong>To MODIFY elements with CSS:</strong></p>
				<pre class="cke">username3: id:nav_upload {opacity:0.5}, class:delconfirm {border:2px solid red}</pre>
				<pre class="cke">username4: id:sb_newpage {opacity:0.3; pointer-events:none}</pre>
			</div>
			
			<br>
			<p><b>Supported prefixes</b>: 
				<code class="tpl">id:</code>, 
				<code class="tpl">class:</code>, 
				<code class="tpl">selector:</code>, 
				<code class="tpl">within:</code>
			</p><p>	
				<b>Within</b>: Use <code class="tpl">within:#pages</code> to scope a class selector to that parent (useful when the same class appears in different admin tabs).<br>
				<b>Selectors</b>: If you need very specific selectors use selector: and supply CSS directly <br>
				(e.g. <code class="tpl">selector: #load #sidebar li:nth-child(5)</code> or <code class="tpl">selector:#sidebar a[href*="massiveAdmin&whitelabel"]</code>).
			</p>
			<p><b>CSS Modification</b>: Add <code class="tpl">{property:value}</code> or <code class="tpl">{property:value; property2:value2}</code> after any selector to modify instead of hide.</p>
			<p><b>Common CSS Examples</b>:</p>
			<ul>
				<li><code class="tpl">{opacity:0.5}</code> - Make semi-transparent</li>
				<li><code class="tpl">{border:2px solid red}</code> - Add red border</li>
				<li><code class="tpl">{background:yellow}</code> - Yellow background</li>
				<li><code class="tpl">{pointer-events:none; opacity:0.3}</code> - Disable and fade</li>
				<li><code class="tpl">{filter:grayscale(100%)}</code> - Make grayscale</li>
				<li><code class="tpl">{transform:scale(0.8)}</code> - Shrink to 80%</li>
			</ul>
		
			<table class="w3-table-all w3-hoverable">
				<tr class="w3-blue">
					<th>Element</th>
					<th>Rule to HIDE</th>
					<th>Example to MODIFY</th>
					<th>Actions</th>
				</tr>
				<tr>
					<td>Theme Tab</td>
					<td><code>id:nav_theme</code></td>
					<td><code>id:nav_theme {opacity:0.5}</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_theme\')">Hide</button>
						<button class="w3-btn w3-tiny w3-round w3-green" onclick="gstabsInsertTextAtCaret(\'id:nav_theme {opacity:0.5}\')">Fade</button>
					</td>
				</tr>
				<tr>
					<td>Plugins Tab</td>
					<td><code>id:nav_plugins</code></td>
					<td><code>id:nav_plugins {border:2px dashed orange}</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_plugins\')">Hide</button>
						<button class="w3-btn w3-tiny w3-round w3-green" onclick="gstabsInsertTextAtCaret(\'id:nav_plugins {border:2px dashed orange}\')">Border</button>
					</td>
				</tr>
				<tr>
					<td>Upload Tab</td>
					<td><code>id:nav_upload</code></td>
					<td><code>id:nav_upload {filter:grayscale(100%)}</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_upload\')">Hide</button>
						<button class="w3-btn w3-tiny w3-round w3-green" onclick="gstabsInsertTextAtCaret(\'id:nav_upload {filter:grayscale(100%)}\')">Grayscale</button>
					</td>
				</tr>
				<tr>
					<td>Support Tab</td>
					<td><code>id:nav_support</code></td>
					<td><code>id:nav_support {background:#ffcccc}</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_support\')">Hide</button>
						<button class="w3-btn w3-tiny w3-round w3-green" onclick="gstabsInsertTextAtCaret(\'id:nav_support {background:#ffcccc}\')">Highlight</button>
					</td>
				</tr>
				<tr>
					<td>New Page Button</td>
					<td><code>id:sb_newpage</code></td>
					<td><code>id:sb_newpage {pointer-events:none; opacity:0.3}</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:sb_newpage\')">Hide</button>
						<button class="w3-btn w3-tiny w3-round w3-green" onclick="gstabsInsertTextAtCaret(\'id:sb_newpage {pointer-events:none; opacity:0.3}\')">Disable</button>
					</td>
				</tr>
				<tr>
					<td>Delete Buttons</td>
					<td><code>class:delconfirm</code></td>
					<td><code>class:delconfirm {border:3px solid red; opacity:0.6}</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'class:delconfirm\')">Hide</button>
						<button class="w3-btn w3-tiny w3-round w3-green" onclick="gstabsInsertTextAtCaret(\'class:delconfirm {border:3px solid red; opacity:0.6}\')">Warning</button>
					</td>
				</tr>
			</table>
		</div>
	</div>
	';

	// Textarea form
	echo '
		<form method="post">
			<textarea id="gstabs-rules" name="gstabs-rules" style="width:85%;height:260px; margin:20px 0">'.htmlspecialchars($saved_raw).'</textarea>
			<p>
				<input type="submit" class="w3-btn w3-round w3-green" name="gstabs-save" value="Save Settings">
				</p>
			</form>
	';

	// Footer
	echo '
		</div>
		
		<footer id="paypal" class="w3-padding-top-32 margin-bottom-none w3-border-top">
				<p class="w3-small clear w3-margin-bottom w3-margin-left">Made with 
					<span class="credit-icon">❤️</span> especially for "
					<b>'.$USR.'</b>". Is this plugin useful to you?
		
					<a href="https://getsimple-ce.ovh/donate" target="_blank" class="donateButton"><b>Buy Us A Coffee </b><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-opacity="0" d="M17 14v4c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-4Z"><animate fill="freeze" attributeName="fill-opacity" begin="0.8s" dur="0.5s" values="0;1"></animate></path><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path stroke-dasharray="48" stroke-dashoffset="48" d="M17 9v9c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-9Z"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.6s" values="48;0"></animate></path><path stroke-dasharray="14" stroke-dashoffset="14" d="M17 9h3c0.55 0 1 0.45 1 1v3c0 0.55 -0.45 1 -1 1h-3"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.6s" dur="0.2s" values="14;0"></animate></path><mask id="lineMdCoffeeHalfEmptyFilledLoop0"><path stroke="#fff" d="M8 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4M12 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4M16 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4"><animateMotion calcMode="linear" dur="3s" path="M0 0v-8" repeatCount="indefinite"></animateMotion></path></mask><rect width="24" height="0" y="7" fill="currentColor" mask="url(#lineMdCoffeeHalfEmptyFilledLoop0)"><animate fill="freeze" attributeName="y" begin="0.8s" dur="0.6s" values="7;2"></animate><animate fill="freeze" attributeName="height" begin="0.8s" dur="0.6s" values="0;5"></animate></rect></g></svg></a>
				</p>
			</footer>
	';

	// JS: helper toggle, insert user, insert helper buttons
	echo '
	<script>
	// helper toggle - start hidden
	var helperEl = document.getElementById("gstabs-helper");
	helperEl.style.display = "none";
	
	document.getElementById("gstabs-toggle-helper").addEventListener("click", function(){
		var el = document.getElementById("gstabs-helper");
		el.style.display = (el.style.display === "none") ? "block" : "none";
	});

	// insert selected user template
	document.getElementById("gstabs-insert-user").onclick = function() {
		var sel = document.getElementById("gstabs-user-select");
		var user = sel.value;
		if (!user) return;
		var ta = document.getElementById("gstabs-rules");
		var line = user + ": ";
		if (ta.value.indexOf(user + ":") === -1) {
			if (ta.value.trim() !== "") {
				ta.value += "\\n" + line;
			} else {
				ta.value = line;
			}
		}
		// focus textarea
		ta.focus();
	};

	// helper insert function used by helper table
	function gstabsInsertText(text) {
		var ta = document.getElementById("gstabs-rules");
		// if caret inside a username line, append to that line; else append to end
		if (ta.selectionStart !== undefined) {
			var start = ta.selectionStart;
			var before = ta.value.substring(0, start);
			var after = ta.value.substring(start);
			ta.value = before + text + after;
		} else {
			ta.value += text;
		}
		ta.focus();
	}
	
	// make gstabsInsertTextAtCaret available to inline onclicks
	window.gstabsInsertTextAtCaret = function(txt) {
		var ta = document.getElementById("gstabs-rules");
		// find current line or append to end of selected users line
		var sel = document.getElementById("gstabs-user-select").value;
		if (sel) {
			// try to find "sel:" line
			var re = new RegExp("^" + sel + "\\\\s*:\\\\s*(.*)$","m");
			var m = ta.value.match(re);
			if (m) {
				// replace line by appending the item (comma separated)
				ta.value = ta.value.replace(re, sel + ": " + (m[1].trim() === "" ? txt : m[1] + ", " + txt));
				ta.focus();
				return;
			} else {
				// insert new line for user
				if (ta.value.trim() !== "") ta.value += "\\n";
				ta.value += sel + ": " + txt;
				ta.focus();
				return;
			}
		}
		// fallback: append to end
		if (ta.value.trim() !== "") ta.value += ", " + txt;
		else ta.value = txt;
		ta.focus();
	};
	</script>
	';
}

# ----------------------------------------------------------
#  APPLY CSS IN ADMIN PANEL (from parsed rules)
#  Now supports both hiding AND modifying elements
# ----------------------------------------------------------
add_action('header','gstabs_output_css');

function gstabs_output_css() {
	global $USR;
	global $SITEURL;
	$data = gstabs_load();
	if (!isset($data['_raw'])) return;
	// parse raw rules to structured
	$parsed = gstabs_parse_raw($data['_raw']);
	if (!isset($parsed[$USR])) return;
	$rules = $parsed[$USR];
	if (empty($rules)) return;
	
	echo '<link rel="stylesheet" href="' . $SITEURL . 'plugins/massiveAdmin/css/w3.css"/>';
	
	echo "
	<style>\n";
	foreach ($rules as $r) {
		if (!isset($r['type']) || !isset($r['value'])) continue;
		$type = $r['type'];
		$value = $r['value'];
		$within = isset($r['within']) ? trim($r['within']) : '';
		$action = isset($r['action']) ? $r['action'] : 'hide';
		$css = isset($r['css']) ? $r['css'] : '';

		// Determine CSS to apply
		$css_properties = '';
		if ($action === 'hide') {
			$css_properties = 'display:none !important;';
		} elseif ($action === 'modify' && !empty($css)) {
			// Sanitize CSS - remove any potentially dangerous content
			$css = preg_replace('/[<>]/', '', $css);
			// Ensure it ends with semicolon
			if (substr(trim($css), -1) !== ';') {
				$css .= ';';
			}
			$css_properties = $css;
		}

		if (empty($css_properties)) continue;

		// Build selector and output CSS
		if ($type === "id") {
			// allow only safe id characters
			$safe = preg_replace('/[^A-Za-z0-9\-_]/', '', $value);
			if ($within) {
				echo $within . " #" . $safe . " { " . $css_properties . " }\n";
			} else {
				echo "#" . $safe . " { " . $css_properties . " }\n";
			}
		} elseif ($type === "class") {
			$safe = preg_replace('/[^A-Za-z0-9\-_]/', '', $value);
			if ($within) {
				echo $within . " ." . $safe . " { " . $css_properties . " }\n";
			} else {
				echo "." . $safe . " { " . $css_properties . " }\n";
			}
		} elseif ($type === "selector") {
			// allow selector through but strip dangerous characters like < >
			$safe_selector = preg_replace('/[<>]/', '', $value);
			if ($within) {
				echo $within . " " . $safe_selector . " { " . $css_properties . " }\n";
			} else {
				echo $safe_selector . " { " . $css_properties . " }\n";
			}
		}
	}
	echo "
	</style>\n";
}


?>
<?php

# plugin id from filename
$thisfile = basename(__FILE__, ".php");

# register plugin in Settings
register_plugin(
	$thisfile,
	'GS Hide Tabs',
	'1.2',
	'CE Team',
	'https://www.getsimple-ce.ovh/',
	'Hide admin navigation tabs or sidebar elements per user.',
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
#  GET EXISTING GETSIMPLE USERS
# ----------------------------------------------------------
function gstabs_get_users() {
	$users = array();
	$dir = GSDATAPATH . 'users/';

	if (!is_dir($dir)) return $users;

	foreach (glob($dir.'*.xml') as $file) {
		$name = basename($file, '.xml');
		$users[] = $name;
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
				$items[] = array('type'=>'id','value'=>$value,'within'=>$within);
			} elseif (stripos($p, 'class:') === 0) {
				$value = trim(substr($p,6));
				if ($value === '') continue;
				$items[] = array('type'=>'class','value'=>$value,'within'=>$within);
			} elseif (stripos($p, 'selector:') === 0) {
				$value = trim(substr($p,9));
				if ($value === '') continue;
				$items[] = array('type'=>'selector','value'=>$value,'within'=>$within);
			} else {
				// fallback: treat as id if it matches id name, else selector
				if (preg_match('/^[A-Za-z0-9\-_]+$/', $p)) {
					$items[] = array('type'=>'id','value'=>$p,'within'=>$within);
				} else {
					$items[] = array('type'=>'selector','value'=>$p,'within'=>$within);
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
<div class="updated">Settings saved.</div>';
		$saved_raw = $input;
	}

	// Prepare users list
	$users = gstabs_get_users();

	// Admin page HTML
	echo '
	<link rel="stylesheet" href="'.$SITEURL.'plugins/UpdateCE/assets/w3.css">
	<link rel="stylesheet" href="'.$SITEURL.'plugins/UpdateCE/assets/w3-custom.css">
	<style>
		textarea {color: #0000CD!important; font-size:14px!important; border-radius:5px!important; background:#E6E6E6; border:solid 1px #999!important;}
		.w3-tiny {padding: 3px 7px; border-radius: 5px;}
		.w3-parent code {font-size: .85em;}
		.w3-border {border: 1px solid #999 !important;}
		.cke {margin:5px 0 0 15px}
		.wrapper p {line-height: 1.3em;}
	</style>';
	
	echo '
<div class="w3-parent ">
	<header class="w3-container w3-border-bottom w3-margin-bottom">
		<h3>GS Hide Tabs 
		<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="1.2em" height="1.2em" viewBox="0 0 24 24"><rect width="24" height="24" fill="none"/><path fill="#000" d="M6.75 3A4.75 4.75 0 0 0 2 7.75v7A2.25 2.25 0 0 0 4.25 17h.25V8.75A3.25 3.25 0 0 1 7.75 5.5H19v-.25A2.25 2.25 0 0 0 16.75 3zm12.504 3.5h.496a2.25 2.25 0 0 1 2.245 2.096L22 8.75v1.004a.75.75 0 0 1-1.493.101l-.007-.101V8.75a.75.75 0 0 0-.648-.743L19.75 8h-.496a.75.75 0 0 1-.102-1.493zm-13.004 5a.75.75 0 0 1 .743.649l.007.102v2.494a.75.75 0 0 1-1.493.102l-.007-.102v-2.494a.75.75 0 0 1 .75-.75m.743 5.643a.75.75 0 0 0-1.493.102v1.005l.005.154A2.25 2.25 0 0 0 7.75 20.5h.5l.102-.007A.75.75 0 0 0 8.25 19h-.5l-.102-.007A.75.75 0 0 1 7 18.25v-1.005zM22 17.246a.75.75 0 1 0-1.5 0v1.005a.75.75 0 0 1-.75.75h-1.003a.75.75 0 0 0 0 1.5h1.003A2.25 2.25 0 0 0 22 18.25zM14.753 19h1.495a.75.75 0 0 1 .102 1.493l-.102.007h-1.495a.75.75 0 0 1-.102-1.493zm-2.507 0h-1.495l-.102.007a.75.75 0 0 0 .102 1.493h1.495l.102-.007A.75.75 0 0 0 12.246 19m9.747-6.851a.75.75 0 0 0-1.493.102v2.494l.007.102A.75.75 0 0 0 22 14.745v-2.494zM9.503 7.25a.75.75 0 0 0-.75-.75H7.75A2.25 2.25 0 0 0 5.5 8.75v1.004a.75.75 0 0 0 1.5 0V8.75A.75.75 0 0 1 7.75 8h1.003a.75.75 0 0 0 .75-.75m5.746-.75h1.503a.75.75 0 0 1 .102 1.493L16.752 8h-1.503a.75.75 0 0 1-.102-1.493zm-2.504 0H11.25l-.102.007A.75.75 0 0 0 11.25 8h1.495l.102-.007a.75.75 0 0 0-.102-1.493"/></svg></h3>
		<p>Define which navigation tabs, sidebar elements or selectors to hide per user.</p>
		<p><b>Rule format</b> (one user per line):</p>
		
		<pre class="cke">username1: id:nav_theme, id:nav_plugins, class:delconfirm within:#pages, selector:#pages .delconfirm</pre>
		<pre class="cke">username2: id:nav_upload, id:sb_newpage, id:sb_menumanager</pre>
		<br>
		<p><b>Supported prefixes</b>: 
			<code class="tpl">id:</code>, 
			<code class="tpl">class:</code>, 
			<code class="tpl">selector:</code>, 
			<code class="tpl">within:</code>. <br>
			<b>Within</b>: Use <code class="tpl">within:#pages</code> to scope a class selector to that parent (useful when the same class appears in different admin tabs).<br>
			<b>Selectors</b>: If you need very specific selectors use selector: and supply CSS directly <br>
			(e.g. <code class="tpl">selector: #load #sidebar li:nth-child(5)</code> or <code class="tpl">selector:#sidebar a[href*="massiveAdmin&whitelabel"]</code>).
		</p><br>
	</header>
	';

	// User dropdown
	echo '
	<div class="w3-container w3-margin-bottom">
		<label class="w3-text-blue">Add user: </label>
		<br>
			<select class="w3-select w3-border w3-round" style="width:30%" id="gstabs-user-select">
				<option value="">  -- Select --</option>';
	foreach ($users as $u) {
		echo '
				<option value="'.htmlspecialchars($u).'">'.htmlspecialchars($u).'</option>';
	}
	echo '
			</select>
			<button type="button" class="w3-btn w3-round w3-blue" id="gstabs-insert-user">Insert</button>
		</div>
	';

	// Helper table (collapsible)
	echo '
	<div class="w3-container w3-margin-bottom">
		<button class="w3-btn w3-small w3-round w3-orange" id="gstabs-toggle-helper">Toggle Helper Table</button>
		<div id="gstabs-helper" style="display:none; margin-top:10px;">
			<table class="w3-table w3-striped w3-small" style="max-width:900px;">
				<tr>
					<th>Name</th>
					<th>Example</th>
					<th></th>
				</tr>
				<tr>
					<td>Main Pages tab</td>
					<td><code>id:nav_pages</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_pages\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Files / Upload tab</td>
					<td><code>id:nav_upload</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_upload\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Theme tab</td>
					<td><code>id:nav_theme</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_theme\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Plugins tab</td>
					<td><code>id:nav_plugins</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_plugins\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Backups tab</td>
					<td><code>id:nav_backups</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:nav_backups\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Sidebar New Page</td>
					<td><code>id:sb_newpage</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:sb_newpage\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Sidebar Components</td>
					<td><code>class:compmassive</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'class:compmassive\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Delete confirmation buttons</td>
					<td><code>class:delconfirm</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'class:delconfirm\')">Add</button>
					</td>
				</tr>
				<tr>
					<td>Meta window in page editor</td>
					<td><code>id:metadata_window</code></td>
					<td>
						<button class="w3-btn w3-tiny w3-round w3-blue" onclick="gstabsInsertTextAtCaret(\'id:metadata_window\')">Add</button>
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
			</form><br>
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
	// helper toggle
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
			var re = new RegExp("^" + sel + "\\s*:\\s*(.*)$","m");
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

		// sanitize simple id/class names to avoid injection where possible
		if ($type === "id") {
			// allow only safe id characters
			$safe = preg_replace('/[^A-Za-z0-9\-_]/', '', $value);
			if ($within) {
				echo $within . " #" . $safe . " { display:none !important; }\n";
			} else {
				echo "#" . $safe . " { display:none !important; }\n";
			}
		} elseif ($type === "class") {
			$safe = preg_replace('/[^A-Za-z0-9\-_]/', '', $value);
			if ($within) {
				echo $within . " ." . $safe . " { display:none !important; }\n";
			} else {
				echo "." . $safe . " { display:none !important; }\n";
			}
		} elseif ($type === "selector") {
			// allow selector through but strip dangerous characters like < >
			$safe_selector = preg_replace('/[<>]/', '', $value);
			if ($within) {
				echo $within . " " . $safe_selector . " { display:none !important; }\n";
			} else {
				echo $safe_selector . " { display:none !important; }\n";
			}
		}
	}
	echo "
	</style>\n";
}

?>

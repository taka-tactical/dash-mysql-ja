<?php

// config
$lang = 'ja';
$ver  = '5.6';


// const
$c_state = ' 構文';
$c_types = ' 型';
$c_mbcom = '、';
$c_mbor  = 'および';


// get manual
$dir  = "refman-{$ver}-{$lang}.html-chapter";
$zip  = "{$dir}.zip";

exec('rm -rf MySQL.docset/Contents/Resources/');
exec('mkdir -p MySQL.docset/Contents/Resources/');
exec("wget http://downloads.mysql.com/docs/{$zip}");
exec("unzip {$zip}");
exec('mv ' . __DIR__ . "/{$dir} " . __DIR__ . '/MySQL.docset/Contents/Resources/Documents');
exec('rm -f ' . __DIR__ . "/{$zip}");

// gen plist
file_put_contents(__DIR__ . '/MySQL.docset/Contents/Info.plist', <<<ENDI
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>CFBundleIdentifier</key>
	<string>mysql-{$lang}</string>
	<key>CFBundleName</key>
	<string>MySQL {$ver}-{$lang}</string>
	<key>DocSetPlatformFamily</key>
	<string>mysql</string>
	<key>isDashDocset</key>
	<true/>
	<key>dashIndexFilePath</key>
	<string>index.html</string>
</dict>
</plist>
ENDI
);
copy(__DIR__ . '/icon.png', __DIR__ . '/MySQL.docset/icon.png');

// init db
$db = new sqlite3(__DIR__ . '/MySQL.docset/Contents/Resources/docSet.dsidx');
$db->query("CREATE TABLE searchIndex(id INTEGER PRIMARY KEY, name TEXT, type TEXT, path TEXT)");
$db->query("CREATE UNIQUE INDEX anchor ON searchIndex (name, type, path)");

$html = file_get_contents(__DIR__ . '/MySQL.docset/Contents/Resources/Documents/index.html');
$str  = '<td width="20%" align="left"> </td>';

// add link to indexes page
if (($p = strpos($html, $str)) !== false) {
	$q = strlen($str);
	$html = substr($html, 0, $p) .
		str_replace('> <', '><a href="ix01.html" accesskey="p">索引</a> <', $str) .
		substr($html, $p + $q);

	file_put_contents(__DIR__ . '/MySQL.docset/Contents/Resources/Documents/index.html', $html);
}

// add search index from toc
$dom = new DomDocument;
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

$html = null;
echo "\nCreate search indexes ...\n\n";

foreach ($dom->getElementsByTagName('dt') as $q) {
	try {
		// have 'span>a' or 'a' child ?
		if (!$q->firstChild) throw new Exception();
		$a = strtolower($q->firstChild->nodeName);

		switch ($a) {
			case 'span':
				$p = $q->firstChild;
				if (!$p->firstChild || strtolower($p->firstChild->nodeName) != 'a') throw new Exception();
				$a = $p->firstChild;
				break;

			case 'a':
				$a = $q->firstChild;
				break;

			default:
				throw new Exception();
				break;
		}

		$href = $a->getAttribute('href');
		$str  = substr($href, 0, 6);
		if ($str[0] == '.') throw new Exception();
		if ($str == 'https:' || !strncmp($str, 'http:', 5)) throw new Exception();

		$name = trim(preg_replace('#\s+#u', ' ', preg_replace('#^[A-Z0-9\.]+\s#u', '', $a->nodeValue)));
		if (empty($name)) throw new Exception();

		// which type ?
		$type = 'Guide';
		$str  = explode('#', $href);

		switch ($str[0]) {
			case 'sql-syntax.html':
				if (substr($name, -1 * strlen($c_state)) == $c_state) $type = 'Statement';
				break;

			case 'licenses-third-party.html':
				throw new Exception();
				break;

			default:
				break;
		}

		$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"{$name}\",\"{$type}\",\"{$href}\")");
		echo "{$name}\n";
	}
	catch (Exception $e) {}
}

// add search index from function/operator page
$html = file_get_contents(__DIR__ . '/MySQL.docset/Contents/Resources/Documents/functions.html');
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$html = array();

foreach ($dom->getElementsByTagName('table') as $q) {
	// target table has 'tbody' child ?
	if ($q->getAttribute('summary') == '関数/演算子') {
		foreach ($q->childNodes as $p) {
			if (strtolower($p->nodeName) == 'tbody') {
				$html = $p->childNodes;
				break;
			}
		}
		break;
	}
}

// check 'tr>td>a' children
foreach ($html as $q) {
	if (strtolower($q->nodeName) != 'tr') continue;

	foreach ($q->childNodes as $p) {
		try {
			if (strtolower($p->nodeName) != 'td' || strtolower($p->getAttribute('scope')) != 'row')
				throw new Exception();

			$a = null;
			foreach ($p->childNodes as $tmp) {
				if (strtolower($tmp->nodeName) == 'a') {
					$a = $tmp;
					break;
				}
			}

			if ($a && strtolower($a->nodeName) == 'a') {
				$href = $a->getAttribute('href');
				$str  = substr($href, 0, 6);
				if ($str[0] == '.') throw new Exception();
				if ($str == 'https:' || !strncmp($str, 'http:', 5)) throw new Exception();

				$name = trim(preg_replace('#\s+#u', ' ', $a->nodeValue));
				if (empty($name)) throw new Exception();

				// which type ?
				$str  = explode('#', $href);
				$type = (count($str) > 1 && substr($str[1], 0, 8) == 'operator') ? 'Operator' : 'Function';

				$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"{$name}\",\"{$type}\",\"{$href}\")");
				echo "{$name}\n";
			}
		}
		catch (Exception $e) {}
	}
}

// add search index from sql-syntax page
$html = file_get_contents(__DIR__ . '/MySQL.docset/Contents/Resources/Documents/sql-syntax.html');
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$html = array();

foreach ($dom->getElementsByTagName('dt') as $q) {
	try {
		// have 'span>a' or 'a' child ?
		if (!$q->firstChild) throw new Exception();
		$a = strtolower($q->firstChild->nodeName);

		switch ($a) {
			case 'span':
				$p = $q->firstChild;
				if (!$p->firstChild || strtolower($p->firstChild->nodeName) != 'a') throw new Exception();
				$a = $p->firstChild;
				break;

			case 'a':
				$a = $q->firstChild;
				break;

			default:
				throw new Exception();
				break;
		}

		$href = $a->getAttribute('href');
		$str  = substr($href, 0, 6);
		if ($str[0] == '.') throw new Exception();
		if ($str == 'https:' || !strncmp($str, 'http:', 5)) throw new Exception();

		$name = trim(preg_replace('#\s+#u', ' ', preg_replace('#^[0-9\.]+\s#u', '', $a->nodeValue)));
		if (empty($name)) throw new Exception();

		// which type ?
		$type = (substr($name, -1 * strlen($c_state)) == $c_state) ? true : false;

		if ($type) {
			if (strpos($name, $c_mbcom) !== false || strpos($name, $c_mbor) !== false)
				$name = substr($name, 0, -1 * strlen($c_state));

			$str  = explode($c_mbcom, $name);
			$name = array();

			foreach ($str as $p) {
				$tmp = explode($c_mbor, trim($p));

				foreach ($tmp as $p) {
					$name[] = trim($p);
				}
			}
			$name = array_filter($name);
			$type = 'Statement';
		}
		else {
			$name = array($name);
			$type = 'Guide';
		}

		foreach ($name as $p) {
			$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"{$p}\",\"{$type}\",\"{$href}\")");
			echo "{$p}\n";
		}
	}
	catch (Exception $e) {}
}

echo "\nMySQL docset created !\n";


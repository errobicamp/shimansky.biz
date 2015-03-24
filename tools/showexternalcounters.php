<?php
/**
 * $shimansky.biz
 *
 * Static web site core scripts
 * @category PHP
 * @access public
 * @copyright (c) 2012 Shimansky.biz
 * @author Serguei Shimansky <serguei@shimansky.biz>
 * @license http://opensource.org/licenses/bsd-license.php
 * @package shimansky.biz
 * @link https://bitbucket.org/englishextra/shimansky.biz
 * @link https://github.com/englishextra/shimansky.biz.git
 */
$relpa = ($relpa0 = preg_replace("/[\/]+/", "/", $_SERVER['DOCUMENT_ROOT'] . '/')) ? $relpa0 : '';
ob_start();
$a_inc = array(
				'lib/swamper.class.php',
				'inc/regional.inc',
				'inc/adminauth.inc',
				'inc/vars2.inc',
				'inc/pdo_mysql.inc'
			);
foreach ($a_inc as $v) {
	require_once $relpa . $v;
}
class ShowExternalCounters extends Swamper {
	function __construct() {
		parent::__construct();
	}
	/* stackoverflow.com/questions/3825226/multi-byte-safe-wordwrap-function-for-utf-8 */
	public function mb_wordwrap($str, $width = 60, $break = "\n", $cut = false, $charset = null) {
		if ($charset === null) {
			$charset = mb_internal_encoding();
		}
		$pieces = explode($break, $str);
		$result = array();
		foreach ($pieces as $piece) {
			$current = $piece;
			while ($cut && mb_strlen($current) > $width) {
				$result[] = mb_substr($current, 0, $width, $charset);
				$current = mb_substr($current, $width, 2048, $charset);
			}
			$result[] = $current;
		}
		return implode($break, $result);
	}
	/* this function seems to stuck the server,
	so we use the alternative function above */
	public function utf8_htmlwrap($str, $width = 60, $break = "\n", $nobreak = "") {
		/* Split HTML content into an array delimited by < and > */
		/* The flags save the delimeters and remove empty variables */
		$content = preg_split("/([<>])/", $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		/* Transform protected element lists into arrays */
		$nobreak = explode(" ", strtolower($nobreak));
		/* Variable setup */
		$intag = false;
		$innbk = array();
		$drain = "";
		/* List of characters it is "safe" to insert line-breaks at */
		/* It is not necessary to add < and > as they are automatically implied */
		$lbrks = "/?!%)-}]\\\"':;&";
		/* Is $str a UTF8 string? */
		$utf8 = (preg_match("/^([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})*$/", $str)) ? "u" : "";
		while (list(, $value) = each($content)) {
			switch ($value) {
				/* If a < is encountered, set the "in-tag" flag */
				case "<" :
					$intag = true;
					break;
				/* If a > is encountered, remove the flag */
				case ">" :
					$intag = false;
					break;
				default :
					/* If we are currently within a tag... */
					if ($intag) {
						/* Create a lowercase copy of this tag's contents */
						$lvalue = strtolower($value);
						/* If the first character is not a / then this is an opening tag */
						if ($lvalue{0} != "/") {
							/* Collect the tag name */
							preg_match("/^(\w*?)(\s|$)/", $lvalue, $t);
							/* If this is a protected element, activate the associated protection flag */
							if (in_array($t[1], $nobreak))
								array_unshift($innbk, $t[1]);
							/* Otherwise this is a closing tag */
						} else {
							/* If this is a closing tag for a protected element, unset the flag */
							if (in_array(substr($lvalue, 1), $nobreak)) {
								reset($innbk);
								while (list($key, $tag) = each($innbk)) {
									if (substr($lvalue, 1) == $tag) {
										unset($innbk[$key]);
										break;
									}
								}
								$innbk = array_values($innbk);
							}
						}
						/* Else if we're outside any tags... */
					} else if ($value) {
						/* If unprotected... */
						if (!count($innbk)) {
							/* Use the ACK (006) ASCII symbol to replace all HTML entities temporarily */
							$value = str_replace("\x06", "", $value);
							preg_match_all("/&([a-z\d]{2,7}|#\d{2,5});/i", $value, $ents);
							$value = preg_replace("/&([a-z\d]{2,7}|#\d{2,5});/i", "\x06", $value);
							/* Enter the line-break loop */
							do {
								$store = $value;
								/* Find the first stretch of characters over the $width limit */
								if (preg_match("/^(.*?\s)?([^\s]{" . $width . "})(?!(" . preg_quote($break, "/") . "|\s))(.*)$/s{$utf8}", $value, $match)) {
									if (strlen($match[2])) {
										/* Determine the last "safe line-break" character within this match */
										for ($x = 0, $ledge = 0; $x < strlen($lbrks); $x++)
											$ledge = max($ledge, strrpos($match[2], $lbrks{$x}));
										if (!$ledge)
											$ledge = strlen($match[2]) - 1;
										/* Insert the modified string */
										$value = $match[1] . substr($match[2], 0, $ledge + 1) . $break . substr($match[2], $ledge + 1) . $match[4];
									}
								}
								/* Loop while overlimit strings are still being found */
							} while ($store != $value);
							/* Put captured HTML entities back into the string */
							foreach ($ents[0] as $ent)
								$value = preg_replace("/\x06/", $ent, $value, 1);
						}
					}
			}
			/* Send the modified segment down the drain */
			$drain .= $value;
		}
		/* Return contents of the drain */
		return $drain;
	}
	public function return_domain_name($url) {
		$d = preg_replace("/(http|https|ftp)\:\/\//", '', $url);
		$t = stristr($d, '/');
		$d = str_replace($t, '', $d);
		return $d;
	}
	public function prepare_str($s, $domain, $domain_highlighted, $slen_domain) {
		$s = urldecode($s);
		$s = stripslashes($s);
		$s = $this -> safe_str($s);
		$s = $this -> text_symbs_to_dec_ents($s);
		$s = $this -> acc_text_to_dec_ents($s);
		$s = $this -> remove_comments($s);
		$s = $this -> remove_tags($s);
		$s = $this -> ensure_lt_gt($s);
		$s = $this -> ord_space($s);
		$s = str_replace(array('https://', 'http://', 'file:///', 'ftp://', '+'), array('', '', '', '', ' '), $s);
		/* replace only once */
		$s = preg_replace("/${domain}/i", "${domain_highlighted}", $s, 1);
		/* this makes the server stuck */
		/* if ($this -> is_utf8($s)) { */
			/* $s = $this -> utf8_htmlwrap($s, $width = $slen_domain, $break = ' ', $nobreak = ''); */
			/* break should be carriage return not empty space: you make break span tags*/
			$s = $this -> mb_wordwrap($s, $width = $slen_domain, $break = "\n", $cut = true, "UTF-8");
		/*}*/
		$s = $this -> ensure_amp($s);
		return $s;
	}
	public function db_table_exists($db_handler, $table) {
		return $r = $db_handler -> query("SELECT count(*) from `" . $table . "`") ? true : false;
	}
}
if (!isset($ShowExternalCounters) || empty($ShowExternalCounters)) {
	$ShowExternalCounters = new ShowExternalCounters();
}
$event = $ShowExternalCounters -> get_post('event');
$table_name = $pt_externalcounters_table_name;
if ($event == 'clear') {
	try {
		/**
		 * if table exists
		 */
		if ($ShowExternalCounters -> db_table_exists($DBH, $table_name)) {
			$DBH -> query("TRUNCATE TABLE `" . $table_name . "`;");
			header("HTTP/1.0 404 Not Found");
			header('location: ' . str_replace('&amp;', '&', $_SERVER['PHP_SELF']));
		}
		$DBH = null;
	} catch (PDOException $e) {
		echo $e -> getMessage();
	}
	exit ;
}
?><!DOCTYPE html>
<html lang="ru">
	<head>
		<meta charset="utf-8" />
		<meta name="HandheldFriendly" content="True" />
		<meta name="MobileOptimized" content="320" />
		<meta name="msapplication-TileColor" content="#DB4040" />
		<meta name="msapplication-TileImage" content="/mstile-144x144.png" />
		<meta name="theme-color" content="#DB4040" />
		<meta name="msapplication-square70x70logo" content="/mstile-70x70.png" />
		<meta name="msapplication-square150x150logo" content="/mstile-150x150.png" />
		<meta name="msapplication-wide310x150logo" content="/mstile-310x150.png" />
		<meta name="msapplication-square310x310logo" content="/mstile-310x310.png" />
		<meta property="og:image" content="http://farm8.staticflickr.com/7610/16635493810_ea01d1400b_o.jpg" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<meta name="robots" content="index,follow" />
		<meta name="author" content="Serguei Shimansky englishextra@yandex.ru" />
		<meta name="keywords" content="репетитор,тушино,егэ,гиа,английский,английского,английскому,глаголы,диалоги,идиомы,ключи,неправильные,онлайн,ответы,подкасты,репетиторы,рефераты,решебник,скачать,тесты,топики,уроки,mp3,mp4" />
		<meta name="description" content="Журнал посещений" />
		<title>Журнал посещений</title>
		<link rel="P3Pv1" href="/w3c/p3p.xml" />
		<link rel="apple-touch-icon" sizes="57x57" href="/apple-touch-icon-57x57.png" />
		<link rel="apple-touch-icon" sizes="60x60" href="/apple-touch-icon-60x60.png" />
		<link rel="apple-touch-icon" sizes="72x72" href="/apple-touch-icon-72x72.png" />
		<link rel="apple-touch-icon" sizes="76x76" href="/apple-touch-icon-76x76.png" />
		<link rel="apple-touch-icon" sizes="114x114" href="/apple-touch-icon-114x114.png" />
		<link rel="apple-touch-icon" sizes="120x120" href="/apple-touch-icon-120x120.png" />
		<link rel="apple-touch-icon" sizes="144x144" href="/apple-touch-icon-144x144.png" />
		<link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png" />
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon-180x180.png" />
		<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32" />
		<link rel="icon" type="image/png" href="/android-chrome-192x192.png" sizes="192x192" />
		<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
		<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16" />
		<link rel="manifest" href="/manifest.json" />
		<link rel="yandex-tableau-widget" href="/yandex-tableau.json" />
		<link rel="image_src" href="http://farm8.staticflickr.com/7610/16635493810_ea01d1400b_o.jpg" type="image/jpeg" title="Английский без&#160;регистрации" />
		<link rel="me" type="text/html" href="https://www.youtube.com/user/shimanskybiz" />
		<link rel="me" type="text/html" href="https://github.com/englishextra" />
		<link rel="me" type="text/html" href="https://vimeo.com/user20994901" />
		<link rel="alternate" href="https://feeds.feedburner.com/Shimanskybiz?format=xml" type="application/rss+xml" title="Английский без&#160;регистрации - Заголовки" />
		<link rel="search" href="/searchplugins/mycroft/sitesearch.xml" type="application/opensearchdescription+xml" title="Английский без&#160;регистрации - Поиск" />
		<!--<script>(function(a,b,c){a.location.protocol&&"https:"!==a.location.protocol&&(b.location.href="https:"+a.location.href.substring(a.location.protocol.length))})(window,document);</script>-->
		<link rel="stylesheet" href="../libs/shimansky.biz-admin/css/bundle.min.css" />
	</head>
	<body>
		<div id="page">
			<div id="topbanner"></div>
			<div class="header" id="header">
				<a class="navpanel" href="#menu-left"></a>
				<a class="sitelogo" href="../index.html"></a>
				<a class="searchpanel" href="#menu-right"></a>
			</div>
			<div id="content">
				<div class="container">
					<div class="row">
						<div class="col span_12 cf">
							<div class="span_1140 textcenter">
								<h1>Журнал посещений</h1>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col span_12 cf">
							<div class="span_1140">
								<table class="respond">
									<thead>
										<tr class="pme-header">
											<th class="pme-header">Adddate</th>
											<th class="pme-header" style="width:25%;">Referer
											<br />
											Self</th>
											<th class="pme-header">Page title</th>
											<th class="pme-header">Browser
											<br />
											Os</th>
											<th class="pme-header">Host
											<br />
											Ip</th>
										</tr>
									</thead>
									<tbody><?php
try {
	/**
	 * if table exists
	 */
	if ($ShowExternalCounters->db_table_exists($DBH, $table_name)) {
		$SQL = "SELECT `id`, `adddate`, `random`, `user_login`, `referer`, `self`, `page_title`, `browser`, `number`, `os`, `os_number`, `host`, `ip` FROM (SELECT `id`, `adddate`, `random`, `user_login`, `referer`, `self`, `page_title`, `browser`, `number`, `os`, `os_number`, `host`, `ip` ";
		$SQL .= "FROM `" . $table_name . "` ";
		$SQL .= "WHERE `random`!='' AND `adddate` >= :vars2_start_time AND `adddate` <= :vars2_end_time ORDER BY `adddate` DESC LIMIT 500) as tbl order by tbl.`adddate`;";
		$STH = $DBH->prepare($SQL);
		$STH->bindValue(":vars2_start_time", (int)$vars2_start_time, PDO::PARAM_INT);
		$STH->bindValue(":vars2_end_time", (int)$vars2_end_time, PDO::PARAM_INT);
		$STH->execute();
		/**
		 * http://stackoverflow.com/questions/460010/work-around-for-php5s-pdo-rowcount-mysql-issue
		 * replaces: if (mysql_num_rows($query) > 0) {
		 */
//if ($DBH->query("SELECT FOUND_ROWS()")->fetchColumn() > 0) {
		if ($STH->rowCount() > 0) {
			/**
			 * replaces while ($fr = mysql_fetch_row($query)) {
			 */
			while ($fr = $STH->fetch(PDO::FETCH_NUM)) {
				$fr4_domain = $ShowExternalCounters->return_domain_name($fr[4]);
				$fr4_highlighted = ($fr4_domain && ($fr4_domain != $vars2_site_root_printable)) ? '<span class="IndianRed">' . strtoupper($fr4_domain) . '</span>' : strtoupper($fr4_domain);
				$fr4_text = $fr[4];
				$fr4_text = $ShowExternalCounters->prepare_str($fr4_text, $fr4_domain, $fr4_highlighted, 30);
				$fr5_domain = $ShowExternalCounters->return_domain_name($fr[5]);
				$fr5_highlighted = ($fr5_domain && ($fr5_domain != $vars2_site_root_printable)) ? '<span class="FireBrick">' . strtoupper($fr5_domain) . '</span>' : strtoupper($fr5_domain);
				$fr5_text = $fr[5];
				$fr5_text = $ShowExternalCounters->prepare_str($fr5_text, $fr5_domain, $fr5_highlighted, 30);
				/*
				these functions freeze PDO and mysqli on windows but not on linux
				if (!empty($fr4_text) && !is_utf8(urldecode($fr4_text))) {
				$fr4_text = '<strong>Non-UTF8 referrer:</strong> ' . $ShowExternalCounters->cp1251_to_utf8(urldecode($fr4_text));
				}
				if (!empty($fr6) && !is_utf8(urldecode($fr6))) {
				$fr6 = '<strong>Non-UTF8 title:</strong> ' . $ShowExternalCounters->cp1251_to_utf8(urldecode($fr6));
				}
				*/
				/*
				if (preg_match("/go\.mail\.ru\/search/ei", urldecode($fr[4]))) {
				}
				*/
				$fr11_text = $fr[11];
				if (preg_match("/[A-Za-z]/", $fr11_text)) {
					if (strpos($fr11_text, ".") !== false && substr_count($fr11_text, ".") > 0) {
						$re = preg_match("/[^\.\/]+\.[^\.\/]+$/", $fr11_text, $matches);
						$fr11_link = htmlentities('http://' . $matches[0] . '/');
						$fr11_text = '<a href="' . $fr11_link . '" target="_blank">' . wordwrap($fr11_text, 20, ' ', 1) . '</a>';
					}
				}
				echo '
<tr class="pme-sortinfo">
<td class="pme-cell-0" style="width:5%;">' . date("H:i:s", $fr[1]) . '<br />' . $fr[1] . '
<td class="pme-cell-0" style="width:25%;"><span class="SlateGray"><strong>from:</strong>&#160;&#160;' . $fr4_text . '</span>&#160;&#160;<a href="' . $ShowExternalCounters->ensure_amp($fr[4]) . '" target="_blank">[&#8594;]</a><br /><span class="DarkSlateGray"><strong>to:</strong>&#160;&#160;' . $fr5_text . '</span>&#160;&#160;<a href="' . $ShowExternalCounters->ensure_amp($fr[5]) . '" target="_blank">[&#8594;]</a></td>
<td class="pme-cell-0" style="width:25%;"><span class="DarkSlateGray">' . $ShowExternalCounters->ensure_amp($ShowExternalCounters->remove_tags(urldecode($fr[6]))) . '</span></td>
<td class="pme-cell-0" style="width:25%;"><span class="FireBrick">' . $fr[7] . ' ' . $fr[8] . '</span><br /><span class="SeaGreen">' . $fr[9] . '</span><br /><span class="DarkSlateGray">' . $fr[10] . '</span></td>
<td class="pme-cell-0" style="width:20%;"><span class="DarkSlateGray">' . $fr11_text . '<br /><a href="http://' . htmlentities($fr[12]) . '/" target="_blank">' . htmlentities(wordwrap($fr[12], 20, ' ', 1)) . '</a><br /><a href="http://ip-lookup.net/?ip=' . urlencode($fr[12]) . '" target="_blank">IP-LOOKUP</a>&#160;<a href="http://www.ripe.net/fcgi-bin/whois?form_type=simple&amp;full_query_string=&amp;searchtext=' . urlencode($fr[12]) . '&amp;submit.x=13&amp;submit.y=14&amp;submit=Search" target="_blank">RIPE</a>&#160;';
				if ($ShowExternalCounters->is_ip($fr[12])) {
					echo '<a href="http://www.ipchecking.com/?ip=' . urlencode($fr[12]) . '&amp;check=Lookup" target="_blank">ipchecking</a>';
				} else {
					echo $fr[12];
				}
				echo '&#160;<a href="http://www.robtex.com/ip/' . urlencode($fr[12]) . '.html" target="_blank">robtex</a></span></td>
</tr>' . "\n";
			}
		}
	}
	$DBH = null;
} catch (PDOException $e) {
	echo $e->getMessage();
}
ob_end_flush();
?></tbody>
								</table>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col span_12 cf">
							<div class="span_1140 textcenter">
								<form action="#" id="actions_form" method="post">
									<p>
										<input type="hidden" name="event" value="clear" />
										<input class="btn uk-button" type="button" onclick="javascipt:document.location.reload();" value="Обновить" />
										<input class="btn uk-button" type="submit" value="Очистить" />
									</p>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
			<nav id="menu-left">
				<ul>
					<li><a href="#" class="nav-li-a-group">Сайты</a>
						<ul>
							<li><a href="https://englishextra.github.io/">englishextra.github.io (23664397)</a></li>
							<li><a href="https://englishextra.github.io/libs/wdot/">englishextra.github.io/libs/wdot (23664397)</a></li>
							<li><a href="https://englishextra.github.io/libs/shower-ribbon/">englishextra.github.io/libs/shower-ribbon (23664397)</a></li>
							<li><a href="https://englishextra.github.io/libs/16pixels/">englishextra.github.io/libs/16pixels (23664397)</a></li>
							<li><a href="https://englishextra.github.io/libs/early.js/">englishextra.github.io/libs/early.js (23664397)</a></li>
							<li><a href="http://portfolio.shimansky.biz/">portfolio.shimansky.biz (23664397)</a></li>
							<li><a href="http://divans.shimansky.biz/">divans.shimansky.biz (23181226)</a></li>
							<li><a href="https://englishextra.github.io/geograph/">geograph.shimansky.biz (23171383)</a></li>
							<li><a href="http://lytcomp.shimansky.biz/">lytcomp.shimansky.biz (17844787)</a></li>
							<li><a href="https://englishextra.github.io/nativeclub/">nativeclub.shimansky.biz (23181040)</a></li>
							<li><a href="http://englishextra.lealta.ru/">englishextra.lealta.ru (11613547)</a></li>
							<li><a href="http://serguei.shimansky.biz/">serguei.shimansky.biz (11613547)</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://localhost/portfolio/">localhost/portfolio (23664397)</a></li>
							<li><a href="http://localhost/divans/">localhost/divans (23181226)</a></li>
							<li><a href="http://localhost/geograph/">localhost/geograph (23171383)</a></li>
							<li><a href="http://localhost/lytcomp/">localhost/lytcomp (17844787)</a></li>
							<li><a href="http://localhost/nativeclub/">localhost/nativeclub (23181040)</a></li>
							<li><a href="http://localhost/serguei/">localhost/serguei (11613547)</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">Счетчики</a>
						<ul>
							<li><a href="https://www.google.com/analytics/web/?#report/visitors-overview/a28561513w54345472p55275787/">Google Analytics</a></li>
							<li><a href="http://metrika.yandex.ru/stat/dashboard/?counter_id=1739493">Яндекс.Метрика</a></li>
							<li><a href="http://rating.openstat.ru/site/2122370">Openstat</a></li>
							<li><a href="http://hit.ua/site_view/36381">HIT.UA</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://shimansky.biz/tools/showexternalcounters.php">Журнал посещений shimansky.biz</a></li>
							<li><a href="http://shimansky.biz/piwik/index.php?module=CoreHome&amp;action=index&amp;idSite=1&amp;period=day&amp;date=today#module=Live&amp;action=getVisitorLog&amp;idSite=1&amp;period=day&amp;date=today">Piwik shimansky.biz</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://localhost/tools/showexternalcounters.php">Журнал посещений localhost</a></li>
							<li><a href="http://localhost/piwik/index.php?module=CoreHome&amp;action=index&amp;idSite=1&amp;period=day&amp;date=today#module=Live&amp;action=getVisitorLog&amp;idSite=1&amp;period=day&amp;date=today">Piwik localhost</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">Репозитарии</a>
						<ul>
							<li><a href="https://github.com/englishextra">github.com/englishextra</a></li>
							<li><a href="https://bitbucket.org/englishextra">bitbucket.org/englishextra</a></li>
							<li><a href="http://pastebin.com/u/englishextra">pastebin.com/u/englishextra</a></li>
							<li><a href="http://code.google.com/u/115554566251160327074/">code.google.com shimanskybiz</a></li>
							<li><a href="https://sourceforge.net/users/privateteacher">SourceForge.net privateteacher</a></li>
							<li><a href="https://bintray.com/englishextra">Bintray englishextra</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">Хостинг</a>
						<ul>
							<li><a href="https://support.hoster.ru/restricted/300909007/">Админ-панель hoster.ru</a></li>
							<li><a href="https://www.reg.ru/user/welcomepage">Админ-панель reg.ru</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">localhost</a>
						<ul>
							<li><a href="http://localhost/tools/phpinfo.php">PHP Info</a></li>
							<li><a href="http://localhost/tools/checkphpconfiguration.php">Конфиг Symphony</a></li>
							<li><a href="http://localhost/tools/accesslogparser.php">Access Log</a></li>
							<li><a href="http://localhost/tools/errorlogparser.php">Error Log</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://localhost/tools/pageindexer.php">Обновить Содержание</a></li>
							<li><a href="http://localhost/tools/sitemapgenerator.php">Обновить Карту сайта</a></li>
							<li><a href="http://localhost/tools/rssgenerator.php">Обновить Новости</a></li>
							<li><a href="http://localhost/tools/backupdb.php">Обновить Копию базы</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://localhost/.pma/">phpMyAdmin localhost</a></li>
							<li><a href="http://localhost/tools/phpminiadmin.php">phpMiniAdmin localhost</a></li>
							<li><a href="http://localhost/tools/phpmyedit/phpMyEditSetup.php">phpMyEdit Setup localhost</a></li>
							<li><a href="http://localhost/tools/phpliteadmin.php">phpLiteAdmin localhost</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://localhost/tools/phpmyedit/pt_externalcounters.php">Таблица Посещения</a></li>
							<li><a href="http://localhost/tools/phpmyedit/pt_pages.php">Таблица Страницы</a></li>
							<li><a href="http://localhost/tools/phpmyedit/pt_search_history.php?PME_sys_fm=0&amp;PME_sys_fl=0&amp;PME_sys_qfn=&amp;PME_sys_sfn[0]=-1&amp;PME_sys_sfn[1]=1&amp;PME_sys_sfn[2]=0">Поиск</a></li>
							<li><a href="http://localhost/tools/phpmyedit/pt_comments.php">Таблица Комментарии</a></li>
							<li><a href="http://localhost/tools/phpmyedit/wp_posts.php">Таблица Посты блога</a></li>
							<li><a href="http://localhost/tools/phpmyedit/wp_options.php">Таблица Опции блога</a></li>
							<li><a href="http://localhost/tools/phpmyedit/options_more_movies_3gp_ipod_psp.php">Таблица Фильмы</a></li>
							<li><a href="http://localhost/tools/phpmyedit/options_downloads.php">Таблица Загрузки</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://localhost/tools/DirectoryLister/">DirectoryLister</a></li>
							<li><a href="http://localhost/tools/encrypt/">Шифрование</a></li>
							<li><a href="http://localhost/tools/typography/">Типографика</a></li>
							<li><a href="http://localhost/tools/responsivetest/">ResponsiveTest</a></li>
							<li><a href="http://localhost/tools/jResize/index.html?url=">jResize</a></li>
							<li><a href="http://localhost/tools/responsinator/index.html?url=">The Responsinator</a></li>
							<li><a href="http://localhost/tools/aspectcalc/">Aspect Ratio</a></li>
							<li><a href="http://localhost/tools/html2dom/">HTML-2-DOM</a></li>
							<li><a href="http://localhost/tools/juniconv/">jUniConv</a></li>
							<li><a href="http://localhost/tools/toentity/">UTF-8 to HTML entity</a></li>
							<li><a href="http://localhost/tools/cssbeautify/">CSS Beautify</a></li>
							<li><a href="http://localhost/tools/pxtoem/">PXtoEM</a></li>
							<li><a href="http://localhost/tools/emcalc/">Em Calculator</a></li>
							<li><a href="http://localhost/tools/colorthief/">Color Thief</a></li>
							<li><a href="http://localhost/tools/colorschemer/">Color Scheme Generator</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">shimansky.biz</a>
						<ul>
							<li><a href="http://shimansky.biz/tools/phpinfo.php">PHP Info</a></li>
							<li><a href="http://shimansky.biz/tools/checkphpconfiguration.php">Конфиг Symphony</a></li>
							<li><a href="http://shimansky.biz/tools/accesslogparser.php">Access Log</a></li>
							<li><a href="http://shimansky.biz/tools/errorlogparser.php">Error Log</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://shimansky.biz/tools/pageindexer.php">Обновить Содержание</a></li>
							<li><a href="http://shimansky.biz/tools/sitemapgenerator.php">Обновить Карту сайта</a></li>
							<li><a href="http://shimansky.biz/tools/rssgenerator.php">Обновить Новости</a></li>
							<li><a href="http://shimansky.biz/tools/backupdb.php">Обновить Копию базы</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="https://dbadmin.hoster.ru/mysql/index.php?db=db41016m&amp;server=1">phpMyAdmin mysql52.hoster.ru</a></li>
							<li><a href="http://shimansky.biz/tools/phpminiadmin.php">phpMiniAdmin shimansky.biz</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/phpMyEditSetup.php">phpMyEdit Setup shimansky.biz</a></li>
							<li><a href="http://shimansky.biz/tools/phpliteadmin.php">phpLiteAdmin shimansky.biz</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/pt_externalcounters.php">Таблица Посещения</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/pt_pages.php">Таблица Страницы</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/pt_search_history.php?PME_sys_fm=0&amp;PME_sys_fl=0&amp;PME_sys_qfn=&amp;PME_sys_sfn[0]=-1&amp;PME_sys_sfn[1]=1&amp;PME_sys_sfn[2]=0">Таблица Поиск</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/pt_comments.php">Таблица Комментарии</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/wp_posts.php">Таблица Посты блога</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/wp_options.php">Таблица Опции блога</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/options_more_movies_3gp_ipod_psp.php">Таблица Фильмы</a></li>
							<li><a href="http://shimansky.biz/tools/phpmyedit/options_downloads.php">Таблица Загрузки</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://shimansky.biz/tools/DirectoryLister/">DirectoryLister</a></li>
							<li><a href="http://shimansky.biz/tools/encrypt/">Шифрование</a></li>
							<li><a href="http://shimansky.biz/tools/typography/">Типографика</a></li>
							<li><a href="http://shimansky.biz/tools/responsivetest/">ResponsiveTest</a></li>
							<li><a href="http://shimansky.biz/tools/jResize/index.html?url=">jResize</a></li>
							<li><a href="http://shimansky.biz/tools/responsinator/index.html?url=">The Responsinator</a></li>
							<li><a href="http://shimansky.biz/tools/aspectcalc/">Aspect Ratio</a></li>
							<li><a href="http://shimansky.biz/tools/html2dom/">HTML-2-DOM</a></li>
							<li><a href="http://shimansky.biz/tools/juniconv/">jUniConv</a></li>
							<li><a href="http://shimansky.biz/tools/toentity/">UTF-8 to HTML entity</a></li>
							<li><a href="http://shimansky.biz/tools/cssbeautify/">CSS Beautify</a></li>
							<li><a href="http://shimansky.biz/tools/pxtoem/">PXtoEM</a></li>
							<li><a href="http://shimansky.biz/tools/emcalc/">Em Calculator</a></li>
							<li><a href="http://shimansky.biz/tools/colorthief/">Color Thief</a></li>
							<li><a href="http://shimansky.biz/tools/colorschemer/">Color Scheme Generator</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">Разработка</a>
						<ul>
							<li><a href="data:text/html,%20%3Chtml%20contenteditable%3E" target="_blank">Текстовая область</a></li>
							<li><a href="http://www.blindtextgenerator.com/ru">lorem ipsum генератор</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://hostcabi.net/domain/shimansky.biz">Who is hosting that website?</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://validator.w3.org/">W3C Markup Validation</a></li>
							<li><a href="http://jigsaw.w3.org/css-validator/">W3C CSS Validation</a></li>
							<li><a href="http://validator.w3.org/checklink">W3C Link Checker</a></li>
							<li><a href="http://validator.w3.org/feed/">W3C Feed Validation</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://dochub.io/#css/">DocHub</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="https://developers.google.com/speed/pagespeed/insights">PageSpeed Insights</a></li>
							<li><a href="http://tools.pingdom.com/fpt/">Website speed test</a></li>
							<li><a href="http://www.websiteoptimization.com/services/analyze/">Web Page Analyzer</a></li>
							<li><a href="http://www.feedthebot.com/tools/">Feedthebot tools</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://closure-compiler.appspot.com/home">Closure Compiler</a></li>
							<li><a href="http://itpro.cz/juniconv/">Unicode Characters to Java Entities</a></li>
							<li><a href="http://www.htmlescape.net/htmlescape_tool.html">Escape HTML Entities</a></li>
							<li><a href="http://jsbeautifier.org/">JS beautifier</a></li>
							<li><a href="http://jscompress.com/">JS Compression</a></li>
							<li><a href="http://www.jslint.com/">JSLint</a></li>
							<li><a href="http://www.jshint.com/">JSHint</a></li>
							<li><a href="http://jsonlint.com/">JSONLint</a></li>
							<li><a href="http://jsfiddle.net/">JSFiddle</a></li>
							<li><a href="http://plnkr.co/edit/6W9URNyyp2ItO4aUWzBB">Plunker</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://jqueryui.com/download/#!version=1.10.3&amp;components=1101000000100010000000000000000000&amp;zThemeParams=5d00000100d905000000000000003d8888d844329a8dfe02723de3e5701fa198449035fc0613ff729a37dd818cf92b1f6938fefa90282d04ae436bb72367f5909357c629e832248af2c086db4ab730aa4cced933a88449eca61db9f7f3b23d47f58a712d809b6088edfb3e1ab0fd4487a569ff42031bb9aefd95aa0a010c29ca4db94f3366f7eb73778008bb690e9d38991c2ab5acb470930c835016bb59ce85223ee2eb2ca74db38bd686e4f0726028207a63e44a04f3ac741d7b0bc5eb3b945532e31642bd3df4c99455415fbf190a56a8384a7a1b06182dbe2759fa2eee7ddbbe32e4be6469419ec67ee260c2aa1654c7a39f0ac8c9b103a68f684dddbe918860409194e418045ff4aa83ac8dfdab69bea83b7cb70ce4c3ebae729c9625a6595e2129f9fb213105d2bddf7fde48ba6844e19d5c44cd55a084be9df2aac2a361d764dd099320cd80849bccd086428d2c262f4adee5e482c00ce779ddaa07097b0111bba4d8294c6b481caba5df02a6796634e7111a01634cc6289876eb90fbc361ec343fcd5c738db443ad6a10040f369eb4d58a61e666560b5f9dac9d9edc158ac7f15f9117fa324e687480e0fa48738c79a5d468cd91db5569f0d4afdc1ab3ffae7ebdc5ac0e6c54873d8b9c97bfffd3cf14ad">Download Builder jQuery UI</a></li>
							<li><a href="http://modernizr.com/download/#-cssanimations-generatedcontent-cssgradients-csstransforms-csstransforms3d-csstransitions-hashchange-audio-video-svg-touch-shiv-mq-cssclasses-prefixed-teststyles-testprop-testallprops-prefixes-domprefixes-load">Modernizr Download Builder</a></li>
							<li><a href="http://chartjs.devexpress.com/">ChartJS</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://regex101.com/">Regular Expressions 101</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://cssbeautify.com/">CSS Beautify</a></li>
							<li><a href="http://devilo.us/">Optimization of your CSS3</a></li>
							<li><a href="http://www.cleancss.com/">Clean CSS</a></li>
							<li><a href="http://css3generator.com/">CSS3 Generator</a></li>
							<li><a href="http://ie.microsoft.com/testdrive/graphics/cssgradientbackgroundmaker/default.html">CSS Gradient Background Maker</a></li>
							<li><a href="http://ie.microsoft.com/TESTDRIVE/Graphics/SVGGradientBackgroundMaker/Default.html">SVG Gradient Background Maker</a></li>
							<li><a href="http://spritegen.website-performance.org/">CSS Sprite Generator</a></li>
							<li><a href="http://cssload.net/">CSS Loaders generator</a></li>
							<li><a href="http://alexwolfe.github.io/Buttons/">A CSS button library</a></li>
							<li><a href="http://code-tricks.com/css-media-queries-for-common-devices/">CSS Media Queries</a></li>
							<li><a href="http://h5bp.github.io/Effeckt.css/">Effeckt.css</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://www.webdesignrepo.com/">webdesignrepo</a></li>
							<li><a href="http://creativeworktools.com/">Presentation Online Tools</a></li>
							<li><a href="https://codio.com/englishextra">Codio</a></li>
							<li><a href="https://koding.com/englishextra">koding.com</a></li>
							<li><a href="https://trello.com/englishextra">Trello</a></li>
							<li><a href="http://ninjamock.com/project/list">NinjaMock</a></li>
							<li><a href="https://rpm.newrelic.com/users/667239/edit">New Relic</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://codepen.io/">CodePen</a></li>
							<li><a href="http://tympanus.net/codrops/all-articles/">Codrops</a></li>
							<li><a href="http://cssdeck.com/">CSSDeck</a></li>
							<li><a href="http://plnkr.co/">Plunker</a></li>
							<li><a href="http://typehunting.com/">Type Hunting</a></li>
							<li><a href="http://www.deviantart.com/">deviantART</a></li>
							<li><a href="http://dribbble.com/">Dribbble</a></li>
							<li><a href="http://www.behance.net/">Behance</a></li>
							<li><a href="http://designerboard.co/">Designerboard</a></li>
							<li><a href="http://www.awwwards.com/">Website Awards</a></li>
							<li><a href="http://flypixel.com/">flypixel</a></li>
							<li><a href="http://www.pixel-fabric.com/">pixel-fabric.com</a></li>
							<li><a href="http://graphicburger.com/">GraphicBurger</a></li>
							<li><a href="http://www.bestpsdfreebies.com/">Best PSD Freebies</a></li>
							<li><a href="http://www.webdesignerdepot.com/category/freebies/">Freebies Webdesigner Depot</a></li>
							<li><a href="http://pixelperfectdigital.com/">Pixel Perfect Free Stock Photos</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://subtlepatterns.com/">Subtle Patterns</a></li>
							<li><a href="http://thepatternlibrary.com/">The Pattern Library</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://lokeshdhakar.com/projects/color-thief/">Color Thief</a></li>
							<li><a href="https://kuler.adobe.com/explore/newest/">Adobe Kuler</a></li>
							<li><a href="http://colorschemedesigner.com/">Color Scheme Designer 3</a></li>
							<li><a href="http://www.colourlovers.com/">Color Trends</a></li>
							<li><a href="http://flatuicolors.com/">Flat UI Colors</a></li>
							<li><a href="http://www.colorblender.com/#">ColorBlender.com</a></li>
							<li><a href="http://www.colorcombos.com/combotester.html?rnd=0&amp;color0=2f2013&amp;color1=d1d1d1&amp;color2=e7e7e7&amp;color3=ebebeb&amp;color4=e93a30&amp;color5=eaebeb&amp;color6=d0d0d0&amp;color7=f4f4f4&amp;color8=dcdcdc&amp;color9=747474&amp;color10=ffce40&amp;color11=f6e128&amp;color12=6c6c6c&amp;color13=313131&amp;color14=000000&amp;color15=f7f7f7&amp;color16=e6e6e6&amp;color17=818181&amp;color18=222222&amp;color19=9b9b9b&amp;color20=363636&amp;color21=212121&amp;color22=414141&amp;color23=4c4c4c&amp;color24=2e3639&amp;color25=fcd54e&amp;color26=62686b&amp;color27=f5f5f5&amp;color28=303436&amp;color29=242829&amp;color30=fbd35a&amp;color31=999999&amp;color32=e0e0e0&amp;color33=1e2629&amp;color34=7f8284&amp;color35=222729&amp;color36=e51111&amp;color37=848889&amp;color38=b5b7b8&amp;color39=979b9c&amp;color40=f6f6f6&amp;color41=c5c5c5&amp;color42=f6f2f0&amp;color43=d7d7d7&amp;color44=efefef&amp;color45=f9f9f9&amp;color46=9e0728&amp;color47=f1f1f1&amp;color48=f4efe5&amp;color49=66cd00&amp;color50=676767&amp;color51=c7c8c9&amp;color52=b1b1b1&amp;color53=7f7f7f&amp;color54=dedede&amp;color55=e0c536&amp;color56=777b7d&amp;color57=3a3a3a&amp;color58=b3b3b3&amp;color59=d5d5d5&amp;color60=dadada&amp;color61=5e5e5e&amp;color62=d8d8d8&amp;color63=282828&amp;color64=8e908c&amp;color65=c82829&amp;color66=f5871f&amp;color67=eab700&amp;color68=718c00&amp;color69=3e999f&amp;color70=4271ae&amp;color71=8959a8&amp;color72=4d4d4c&amp;color73=e4e4e4&amp;color74=bababa&amp;color75=cacaca&amp;color76=acacac&amp;color77=c3c3c3&amp;color78=da573b&amp;color79=cad4e7&amp;color80=3b5998&amp;color81=eceef5&amp;color82=9dacce&amp;color83=c1c1c1&amp;color84=f8f8f8&amp;color85=d9d9d9&amp;color86=258db1&amp;color87=2e2e2e&amp;color88=3c3c3c">Color Combinations Tester</a></li>
							<li><a href="http://www.colorhunter.com/">Color Hunter</a></li>
							<li><a href="http://www.colorschemer.com/online.html">Color Schemer</a></li>
							<li><a href="http://hex2rgba.devoth.com/">HEX 2 RGBA</a></li>
							<li><a href="http://rgb.to/hex/e54b4b">Convert Hex color</a></li>
							<li><a href="http://www.javascripter.net/faq/hextorgb.htm">Hex-to-RGB Conversion</a></li>
							<li><a href="http://www.december.com/html/spec/color0.html">Neutrals Hex Color Codes</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://www.base64-image.de/step-1.php">Base64 Image Encoder</a></li>
							<li><a href="http://www.ajaxload.info/">Ajax loading gif generator</a></li>
							<li><a href="http://www.mytreedb.com/blog/a-33-high_quality_ajax_loader_images.html">AJAX Loader Images</a></li>
							<li><a href="http://placehold.it/">Placehold.it&#160;&#8212; image placeholders</a></li>
							<li><a href="http://qrcode.littleidiot.be/">Create your QR code</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://iconogen.com/">Iconogen</a></li>
							<li><a href="http://makeappicon.com/">Makeappicon</a></li>
							<li><a href="http://www.iconfinder.com/">Icon Finder</a></li>
							<li><a href="http://flaticons.net/">Free Flat Icons</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://pattern-lab.info/">Pattern Lab</a></li>
							<li><a href="http://onepagelove.com/">One Page Love</a></li>
							<li><a href="http://bjankord.github.io/Style-Guide-Boilerplate/">Style Guide Boilerplate</a></li>
							<li><a href="http://palace.co/">Palace design studio</a></li>
							<li><a href="http://fltdsgn.com/">Flat UI Design</a></li>
							<li><a href="http://pea.rs/content/slats-thumbnails">Common HTML patterns</a></li>
							<li><a href="http://html5templates.com/">HTML5 Templates</a></li>
							<li><a href="http://html5up.net/">HTML5 UP!</a></li>
							<li><a href="http://getbootstrap.com/getting-started/#examples">Example Templates for Bootstrap</a></li>
							<li><a href="http://gumbyframework.com/customize">Gumby Framework</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://mediaqueri.es/">Media Queries</a></li>
							<li><a href="http://responsejs.com/labs/dimensions/">Device and Viewport</a></li>
							<li><a href="http://lab.maltewassermann.com/viewport-resizer/">Responsive design testing tool</a></li>
							<li><a href="http://mattkersley.com/responsive/">Responsive Design Testing</a></li>
							<li><a href="http://www.responsinator.com/">The Responsinator</a></li>
							<li><a href="http://ami.responsivedesign.is/">Am I&#160;Responsive?</a></li>
							<li><a href="http://responsivetest.net/">Testing responsive web design</a></li>
							<li><a href="http://responsive.is/">Responsive.is</a></li>
							<li><a href="http://responsify.it/">Responsify.it</a></li>
							<li><a href="http://responsive.gs/">Responsive Grid System</a></li>
							<li><a href="http://gridpak.com/">Gridpak</a></li>
							<li><a href="http://skeljs.org/docs">skelJS</a></li>
							<li><a href="http://bradfrost.github.io/this-is-responsive/patterns.html">Responsive Patterns</a></li>
							<li><a href="http://www.os-templates.com/free-responsive-templates">Free Responsive Templates</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://pxtoem.com/">PX to EM conversion</a></li>
							<li><a href="https://offroadcode.com/prototypes/rem-calculator/">Rem Calculator</a></li>
							<li><a href="http://www.abdullahyahya.com/2011/10/29/convert-photoshop-font-point-pt-to-pixels-px/">Point (Pt) to Pixels (Px)</a></li>
							<li><a href="http://www.unitconversion.org/typography/postscript-points-to-pixels-x-conversion.html">PostScript Points to Pixels</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://practicaltypography.com/">Practical Typography</a></li>
							<li><a href="http://typograf.ru/">Типограф</a></li>
							<li><a href="http://www.pearsonified.com/typography/">Golden Ratio Typography Calculator</a></li>
							<li><a href="http://www.newnet-soft.com/blog/csstypography">CSS Typography cheat sheet</a></li>
							<li><a href="http://baselinecss.com/typography.html">Baseline Typography</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://www.google.com/fonts/">Google Fonts</a></li>
							<li><a href="http://www.fontsquirrel.com/">Font Squirrel</a></li>
							<li><a href="http://webfont.ru/category/#sans">WebFont.ru</a></li>
							<li><a href="http://bayguzin.ru/main/shriftyi/russkij-shriftyi/70-potryasayushhix-kirillicheskix-russkix-shriftov.html">Русские кириллические шрифты</a></li>
							<li><a href="http://www.font2web.com/">Font2Web</a></li>
							<li><a href="http://ru.fonts2u.com/font-converter.html">Онлайн конвертер шрифтов</a></li>
							<li><a href="http://onlinefontconverter.com/">Free online font converter</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://dev.w3.org/html5/html-author/charref">Entity Reference Chart</a></li>
							<li><a href="http://www.ascii.cl/htmlcodes.htm">ascii characters</a></li>
							<li><a href="http://htmlhelp.com/reference/html40/entities/symbols.html">HTML 4.0 Entities for Symbols</a></li>
							<li><a href="http://french.typeit.org/">Type French accents</a></li>
							<li><a href="http://ipa.typeit.org/">Type IPA phonetic symbols</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://www.jsdelivr.com/">jsDelivr</a></li>
							<li><a href="http://cdnjs.com/">cdnjs</a></li>
							<li><a href="http://api.yandex.ru/jslibs/libs.xml">Yandex CDN</a></li>
							<li><a href="http://www.asp.net/ajaxlibrary/cdn.ashx">Microsoft Ajax CDN</a></li>
							<li><a href="https://developers.google.com/speed/libraries/devguide?hl=ru">Google CDN</a></li>
							<li><a href="http://code.jquery.com/">jQuery CDN</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="https://www.google.com/adsense/app#home">Google AdSense</a></li>
							<li><a href="http://partner.market.yandex.ru/pre-campaigns.xml">Яндекс.Маркет для магазинов</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://habrahabr.ru/company/sibirix/blog/188690/">Договор на&#160;сайт</a></li>
							<li class="nav-li-delimiter"><a href="#" class="nav-li-a-delimiter">&#160;</a></li>
							<li><a href="http://udc.biblio.uspu.ru/">УДК Классификатор</a></li>
							<li><a href="http://scs.viniti.ru/udc/">Расшифровка формул УДК</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">Яндекс</a>
						<ul>
							<li><a href="http://webmaster.yandex.ru/sites/">Яндекс.Вебмастер&#160;&#8212; Мои сайты</a></li>
							<li><a href="https://pdd.yandex.ru/domain/shimansky.biz/">Домен shimansky.biz</a></li>
							<li><a href="https://mail.yandex.ru/for/shimansky.biz/neo2/#inbox">Входящие&#160;&#8212; Яндекс.Почта</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">Google</a>
						<ul>
							<li><a href="https://www.google.com/webmasters/tools/home?hl=ru&amp;siteUrl=http://shimansky.biz/&amp;mesd=AB9YKzLhE1ETAtSB6KlswkoN2S6N5JzNgro5ZDXawDaIrFfEv60ziyLviVxFZdr5Wqys9LlyCHdcfnJvTu53MaE_Bt9mKnabwdCzybgnuRZiyvDSccK5xXstXDRw8iBbNJlZq7o1K8fwxfA9zV91UjalbKIeqSU1NgPu2MrWx6BG7lym6RqiEZ28FxYs7Jk5e3kVxpIWt3xLKN7Diceml4y_xHsI00unF9i_Yxva9ySOdtWDFDr_04hCqByCdA64ggDmWfZf6wxgeGIH0bf-2REYSboY_I0BBQ">Инструменты для веб-мастеров</a></li>
							<li><a href="https://admin.google.com/shimansky.biz/UserHub">Google Apps</a></li>
							<li><a href="http://www.google.com/apps/intl/ru/business/index.html">Корпоративная электронная почта</a></li>
							<li><a href="https://accounts.google.com/ServiceLogin?service=mail&amp;authuser=1">Вход&#160;&#8212; Google Аккаунты</a></li>
							<li><a href="https://admin.google.com/shimansky.biz/AdminHome?fral=1">Консоль администратора</a></li>
							<li><a href="https://www.google.com/a/cpanel/shimansky.biz/UserHub">Службы Google</a></li>
							<li><a href="https://mail.google.com/mail/u/1/?shva=1#inbox">Почта Shimansky.biz</a></li>
						</ul>
					</li>
					<li><a href="#" class="nav-li-a-group">QIWI</a>
						<ul>
							<li><a href="https://w.qiwi.com/order/list.action?type=1">Счета</a></li>
						</ul>
					</li>
				</ul>
			</nav>
			<nav class="mm-menu mm-right mm-is-menu mm-horizontal" id="menu-right">
				<div class="mm-inner">
					<form id="search_form" method="get" action="/search/" enctype="application/x-www-form-urlencoded">
						<div class="mm-search">
							<input type="text" name="q" id="search_text" autocomplete="off" placeholder="Найти" />
						</div>
					</form>
				</div>
			</nav>
		</div>
		<!--#include virtual="../virtual/yepnope.min.js.html" -->
		<script>
				yepnope.injectJs("../cdn/early.js/9ac385b2386805963c8122bb60be474b5d483989/1.0/js/early.min.js",function(){
					yepnope.injectJs("//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js", function () {
						yepnope.injectJs("../cdn/jquery.mmenu/4.2.5/js/jquery.mmenu.min.js", function () {
							"undefined"!==typeof window.jQuery&&function(){$(function(){$("nav#menu-left").mmenu()});$(function(){$("nav#menu-right").mmenu({position:"right",counters:!0,searchfield:!0});})}();
						},{charset:"utf-8"},5E3);
						("undefined"!==typeof earlyIsMobileBrowser&&earlyIsMobileBrowser)||yepnope.injectJs("../cdn/jquery-ui/4cec63baf3c58510296956c19b29b30f49853fc7/1.10.4/custom.autocomplete/js/jquery-ui.min.js", function () {
							"undefined"!==typeof window.jQuery&&function(d,c,e,f){$("#"+c).autocomplete({source:function(b,a){$.ajax({url:f,dataType:"json",data:{q:b.term,c:10},success:function(b){a($.map(b,function(a){return{label:a.value,value:a.name}}))}})},minLength:1,select:function(b,a){if(a.item.value&&(a.item.value.match(/^http\:\/\//)||a.item.value.match(/^https\:\/\//)||a.item.value.match(/^\/search\//)||a.item.value.match(/^\//)))return d.location.href=a.item.value,!1;$(b.target).val($("#"+c).val());$("#"+e).submit()}, open:function(){},close:function(){}})}(document,"search_text","search_form","../libs/jquery-ui/1.10.4/custom.autocomplete/autocomplete/");
						},{charset:"utf-8"},5E3);
						("undefined"!==typeof earlyIsMobileBrowser&&earlyIsMobileBrowser)&&yepnope.injectJs("../cdn/jquery.webks-responsive-table/79b5a653dabf0a82718e2ecaf89efa1117609a41/js/jquery.webks-responsive-table.min.js", function () {
							"undefined"!==typeof window.jQuery&&domready(function(){(function(a,b,c,d){a.innerWidth&&(0<a.innerWidth?a.innerWidth:screen.width)&&$(function(){$("body").addClass("javascript-active");$(b).responsiveTable({displayResponsiveCallback:function(){return c>(a.innerWidth?0<a.innerWidth?a.innerWidth:screen.width:"")}});$(a).bind("orientationchange",function(a){setTimeout("$(\x27"+b+"\x27).responsiveTableUpdate()",d)});$(a).resize(function(){$(b).responsiveTableUpdate()})})})(window,"table.respond",768,100)});
						},{charset:"utf-8"},5E3);
					},{charset:"utf-8"},5E3);
					(function(a,c){a&&c&&c.setAttribute("action",a)})(self.location.href||"/",document.getElementById("actions_form")||"");
					(function(e,f,a,h,k){b=document.createElement("a");b.setAttribute("style","display:none;");b.setAttribute("href","#");b.setAttribute("id",h);b.setAttribute("onclick","function scrollTop2(c){var b=window.pageYOffset,d=0,e=setInterval(function(b,a){return function(){a-=b*c;window.scrollTo(0,a);d++;(150<d||0>a)&&clearInterval(e)}}(c,b--),50)};scrollTop2(100);return false;");c=document.createElement("span");c.setAttribute("id",k);b.appendChild(c);d=document.createTextNode("\u041d\u0430\u0432\u0435\u0440\u0445");b.appendChild(d);e.appendChild(b);f.onscroll=function(){var e=f.pageYOffset||a.documentElement.scrollTop||a.body.scrollTop,k=f.innerHeight||a.documentElement.clientHeight||a.body.clientHeight,g=a.getElementById(h)||"";g&&(e>k?g.style.display="inline":g.style.display="none")}})(document.getElementsByTagName("body")[0]||document.documentElement,window,document,"toTop","toTopHover");
				},{charset:"utf-8"},5E3);
		</script>
	</body>
</html>

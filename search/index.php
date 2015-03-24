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
$a_inc = array(
	'lib/swamper.class.php',
	'inc/regional.inc',
	'inc/vars2.inc',
	'inc/pdo_mysql.inc',
	'inc/pdo_sqlite_cache.inc'
);
foreach ($a_inc as $v) {
	require_once $relpa . $v;
}
/* this script should be utf encoded */
class Search extends Swamper {
	function __construct() {
		parent::__construct();
	}
	public function conv_symbs_to_ents($s) {
		return $s = str_replace(array(
"‘",
"‚",
"„",
"“",
"”",
"€",
"@",
"№",
"«",
"»",
/*"-",*/
"–",
"—",
"’",
"'",
"…"
), array(
"&#8216;",
"&#8218;",
"&#8222;",
"&#8220;",
"&#8221;",
"&#8364;",
"&#64;",
"&#8470;",
"&#171;",
"&#187;",
/*"&#8211;",*/
"&#8211;",
"&#8212;",
"&#39;",
"&#39;",
"&#8230;"
), $s);
	}
	public function prepare_query($s) {
		$s = trim($s);
		$s = $this->safe_str($s);
		$s = str_replace("_", " ", $s);
		$s = $this->remove_tags($s);
		$s = $this->ord_hypher($s);
		$s = $this->ord_space($s);
		return $s;
	}
	public function db_table_exists($db_handler, $table) {
		return $r = $db_handler->query("SELECT count(*) from `" . $table . "`") ? true : false;
	}
	public function write_to_caching_db2($db_handler, $table, $marker, $q, $p) {
		if ($this->db_table_exists($db_handler, $table)) {
			$db_handler->exec("DELETE FROM " . $table . " WHERE `query`='" . $this->conv_symbs_to_ents($q) . "';");
			$SQL = "INSERT INTO `" . $table . "` ";
			$SQL .= "VALUES(null, :adddate, :query, :content);";
			$STH = $db_handler->prepare($SQL);
			$STH->bindValue(":adddate", $marker);
			$STH->bindValue(":query", $this->conv_symbs_to_ents($q));
			$STH->bindValue(":content", $this->conv_symbs_to_ents($p));
			$STH->execute();
		}
	}
}
if (!isset($Search) || empty($Search)) {
	$Search = new Search ();
}
$query = $Search->get_post('q') ? $Search->get_post('q') : ($Search->get_post('s') ? $Search->get_post('s') : '');
if (!$query) {$query = $Search->get_post('term') ? $Search->get_post('term') : ($Search->get_post('search') ? $Search->get_post('search') : '');}
if (!$query) {$query = $Search->get_post('query') ? $Search->get_post('query') : '';}
$query = $Search->prepare_query($query);
$length = $Search->get_post('length');
if (!$length) {
	$length = 255;
}
$limit = $Search->get_post('limit');
if (!$limit) {
	$limit = 4;
}
$ignore_length = 2;
$query_length = ($query_length0 = mb_strlen($query, mb_detect_encoding($query, "UTF-8, ASCII"))) ? $query_length0 : 0;
$table_name = $pt_pages_table_name;
$table_name1 = $options_more_movies_3gp_ipod_psp_table_name;
$table_name2 = $pt_search_history_table_name;
$table_name3 = $dict_enru_general_table_name;
$table_name4 = $dict_ruen_general_table_name;
$table_name5 = $options_downloads_table_name;
$r = '';
$p = '';
if (!empty($query) && $query_length > $ignore_length) {
	/* read from cache */
	$from_cache = '';
	$cache_table_name = 'cache_search';
	$SQLITE_CACHE->exec("CREATE TABLE IF NOT EXISTS `" . $cache_table_name . "` ( `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, `adddate` INTEGER NOT NULL, `query` TEXT, `content` TEXT )");
	if ($Search->db_table_exists($SQLITE_CACHE, $cache_table_name)) {
		$SQL = "SELECT `id`, `adddate`, `query`, `content` ";
		$SQL .= "FROM `" . $cache_table_name . "` ";
		$SQL .= " WHERE `id`!='' AND `adddate`!='' AND `query`!='' AND `content`!='' AND `query`=:query LIMIT :limit;";
		$STH = $SQLITE_CACHE->prepare($SQL);
		$a = null;
		/**
		 * if-not-true-then-false.com/2012/php-pdo-sqlite3-example/
		 * php.net/manual/en/sqlite3stmt.bindvalue.php
		 */
		$a[] = array(":query", $Search->conv_symbs_to_ents($query));
		$a[] = array(":limit", (int) 1);
		for ($i = 0; $i < count($a); $i++) {
			if (!empty($a[$i][0])) {
				$STH->bindValue($a[$i][0], $a[$i][1]);
			}
		}
		$STH->execute();
		while ($fr = $STH->fetch(PDO::FETCH_NUM)) {
			if ($fr[0]) {
				$r = 1;
				if ($fr[1] > $vars2_start_time && $fr[1] < $vars2_end_time) {
					$from_cache = 1;
				}
				$p = "\n\n" . "<!-- from " . $cache_table_name . " -->" . "\n\n" . $fr[3];
			}
		}
	}
	if (!$r) {
		try {
			/* $p = '<h2>&#1056;&#1077;&#1079;&#1091;&#1083;&#1100;&#1090;&#1072;&#1090;&#1099; &#1087;&#1086;&#1080;&#1089;&#1082;&#1072;</h2>' . "\n" . '<ol>' . "\n"; */
			/* $p = '<h2>&#1056;&#1077;&#1079;&#1091;&#1083;&#1100;&#1090;&#1072;&#1090;&#1099; &#1087;&#1086;&#1080;&#1089;&#1082;&#1072;</h2>' . "\n"; */
			$p .= '<ol class="jqm-list">' . "\n";
			if ($Search->db_table_exists($DBH, $table_name)) {
				$SQL = "SELECT `id`, `page_title`, `page_url`, `description`, `wordhash` ";
				$SQL .= "FROM `" . $table_name . "` ";
				$SQL .= " WHERE `page_title`!='' AND `page_url`!='' AND `description`!='' AND `wordhash`!='' AND `page_title` LIKE :query OR `description` LIKE :query OR `wordhash` LIKE :query ORDER BY `page_title` ASC LIMIT :limit;";
				$STH = $DBH->prepare($SQL);
				$a = null;
				/**
				 * php.net/manual/en/pdostatement.bindparam.php
				 * The CORRECT solution is to leave clean the placeholder like this:
				 * "SELECT * FROM `users` WHERE `firstname` LIKE :keyword";
				 * And then add the percentages to the php variable where you store the keyword:
				 * $keyword = "%".$keyword."%";
				 */
				$a[] = array(":query", '%' . $Search->conv_symbs_to_ents($query) . '%', PDO::PARAM_STR);
				$a[] = array(":limit", (int) $limit, PDO::PARAM_INT);
				for ($i = 0; $i < count($a); $i++) {
					if (!empty($a[$i][0])) {
						$STH->bindValue($a[$i][0], $a[$i][1], $a[$i][2]);
					}
				}
				$STH->execute();
				if ($STH->rowCount() > 0) {
					$r = 1;
					while ($fr = $STH->fetch(PDO::FETCH_NUM)) {
						$p .= '<li><a href="' . $Search->ensure_amp($fr[2]) . '" class="ui-link" data-ajax="false">' . $Search->safe_html($fr[3], 65) . '</a></li>' . "\n";
					}
				}
			}
			if ($Search->db_table_exists($DBH, $table_name1)) {
				$SQL = "SELECT `id`, `value`, `text` ";
				$SQL .= "FROM `" . $table_name1 . "` ";
				$SQL .= " WHERE `value`!='' AND `text`!='' AND `text` LIKE :query ORDER BY `text` ASC LIMIT :limit;";
				$STH = $DBH->prepare($SQL);
				$a = null;
				/**
				 * php.net/manual/en/pdostatement.bindparam.php
				 * The CORRECT solution is to leave clean the placeholder like this:
				 * "SELECT * FROM `users` WHERE `firstname` LIKE :keyword";
				 * And then add the percentages to the php variable where you store the keyword:
				 * $keyword = "%".$keyword."%";
				 */
				$a[] = array(":query", '%' . $Search->conv_symbs_to_ents($query) . '%', PDO::PARAM_STR);
				$a[] = array(":limit", (int) $limit, PDO::PARAM_INT);
				for ($i = 0; $i < count($a); $i++) {
					if (!empty($a[$i][0])) {
						$STH->bindValue($a[$i][0], $a[$i][1], $a[$i][2]);
					}
				}
				$STH->execute();
				if ($STH->rowCount() > 0) {
					$r = 1;
					while ($fr = $STH->fetch(PDO::FETCH_NUM)) {
						$p .= '<li><a href="' . $Search->ensure_amp($fr[1]) . '" class="ui-link" data-ajax="false">' . $Search->safe_html($fr[2], 65) . '</a></li>' . "\n";
					}
				}
			}
			if ($Search->db_table_exists($DBH, $table_name5)) {
				$SQL = "SELECT `id`, `value`, `text` ";
				$SQL .= "FROM `" . $table_name5 . "` ";
				$SQL .= " WHERE `value`!='' AND `text`!='' AND `text` LIKE :query ORDER BY `text` ASC LIMIT :limit;";
				$STH = $DBH->prepare($SQL);
				$a = null;
				/**
				 * php.net/manual/en/pdostatement.bindparam.php
				 * The CORRECT solution is to leave clean the placeholder like this:
				 * "SELECT * FROM `users` WHERE `firstname` LIKE :keyword";
				 * And then add the percentages to the php variable where you store the keyword:
				 * $keyword = "%".$keyword."%";
				 */
				$a[] = array(":query", '%' . $Search->conv_symbs_to_ents($query) . '%', PDO::PARAM_STR);
				$a[] = array(":limit", (int) $limit, PDO::PARAM_INT);
				for ($i = 0; $i < count($a); $i++) {
					if (!empty($a[$i][0])) {
						$STH->bindValue($a[$i][0], $a[$i][1], $a[$i][2]);
					}
				}
				$STH->execute();
				if ($STH->rowCount() > 0) {
					$r = 1;
					while ($fr = $STH->fetch(PDO::FETCH_NUM)) {
						$p .= '<li><a href="' . $Search->ensure_amp($fr[1]) . '" class="ui-link" data-ajax="false">' . $Search->safe_html($fr[2], 65) . '</a></li>' . "\n";
					}
				}
			}
			if ($Search->db_table_exists($DBH, $table_name3)) {
				$SQL = "SELECT `id`, `entry`, `description` ";
				$SQL .= "FROM `" . $table_name3 . "` ";
				$SQL .= " WHERE `entry`!='' AND `description`!='' AND `entry` LIKE :query ORDER BY `entry` ASC LIMIT :limit;";
				$STH = $DBH->prepare($SQL);
				$a = null;
				/**
				 * php.net/manual/en/pdostatement.bindparam.php
				 * The CORRECT solution is to leave clean the placeholder like this:
				 * "SELECT * FROM `users` WHERE `firstname` LIKE :keyword";
				 * And then add the percentages to the php variable where you store the keyword:
				 * $keyword = "%".$keyword."%";
				 */
				$a[] = array(":query", $Search->conv_symbs_to_ents($query) . '%', PDO::PARAM_STR);
				$a[] = array(":limit", (int) $limit, PDO::PARAM_INT);
				for ($i = 0; $i < count($a); $i++) {
					if (!empty($a[$i][0])) {
						$STH->bindValue($a[$i][0], $a[$i][1], $a[$i][2]);
					}
				}
				$STH->execute();
				if ($STH->rowCount() > 0) {
					$r = 1;
					while ($fr = $STH->fetch(PDO::FETCH_NUM)) {
						$p .= '<li>' . $Search->safe_html($fr[1], 28) . '&#160;&#8212; ' . $Search->safe_html($fr[2], 28) . '</li>' . "\n";
					}
				}
			}
			if ($Search->db_table_exists($DBH, $table_name4)) {
				$SQL = "SELECT `id`, `entry`, `description` ";
				$SQL .= "FROM `" . $table_name4 . "` ";
				$SQL .= " WHERE `entry`!='' AND `description`!='' AND `entry` LIKE :query ORDER BY `entry` ASC LIMIT :limit;";
				$STH = $DBH->prepare($SQL);
				$a = null;
				/**
				 * php.net/manual/en/pdostatement.bindparam.php
				 * The CORRECT solution is to leave clean the placeholder like this:
				 * "SELECT * FROM `users` WHERE `firstname` LIKE :keyword";
				 * And then add the percentages to the php variable where you store the keyword:
				 * $keyword = "%".$keyword."%";
				 */
				$a[] = array(":query", $Search->conv_symbs_to_ents($query) . '%', PDO::PARAM_STR);
				$a[] = array(":limit", (int) $limit, PDO::PARAM_INT);
				for ($i = 0; $i < count($a); $i++) {
					if (!empty($a[$i][0])) {
						$STH->bindValue($a[$i][0], $a[$i][1], $a[$i][2]);
					}
				}
				$STH->execute();
				if ($STH->rowCount() > 0) {
					$r = 1;
					while ($fr = $STH->fetch(PDO::FETCH_NUM)) {
						$p .= '<li>' . $Search->safe_html($fr[1], 28) . '&#160;&#8212; ' . $Search->safe_html($fr[2], 28) . '</li>' . "\n";
					}
				}
			}
			$p .= '</ol>' . "\n";
			/* write search history */
			if ($Search->db_table_exists($DBH, $table_name2)) {
				if (mb_strlen($query, mb_detect_encoding($query)) > $ignore_length) {
					$SQL = "DELETE FROM `" . $table_name2 . "` ";
					$SQL .= "WHERE `query`=:query;";
					$STH = $DBH->prepare($SQL);
					$a = null;
					/**
					 * php.net/manual/en/pdostatement.bindparam.php
					 * The CORRECT solution is to leave clean the placeholder like this:
					 * "SELECT * FROM `users` WHERE `firstname` LIKE :keyword";
					 * And then add the percentages to the php variable where you store the keyword:
					 * $keyword = "%".$keyword."%";
					 */
					$a[] = array(":query", $Search->conv_symbs_to_ents($query), PDO::PARAM_STR);
					for ($i = 0; $i < count($a); $i++) {
						if (!empty($a[$i][0])) {
							$STH->bindValue($a[$i][0], $a[$i][1], $a[$i][2]);
						}
					}
					$STH->execute();
					/* switching the value to 0 workes for AUTO_INCREMENT field */
					$SQL = "INSERT INTO `" . $table_name2 . "` ";
					$SQL .= "(`id`,`adddate`,`query`,`host`,`ip`) ";
					$SQL .= "VALUES (0, :adddate, :query, :host, :ip);";
					$STH = $DBH->prepare($SQL);
					$a = null;
					$a[] = array(":adddate", (int) $vars2_marker, PDO::PARAM_INT);
					$a[] = array(":query", $Search->conv_symbs_to_ents($query), PDO::PARAM_STR);
					$a[] = array(":host", $Search->ensure_amp($vars2_http_x_forwarded_for), PDO::PARAM_STR);
					$a[] = array(":ip", $Search->ensure_amp($vars2_remote_address), PDO::PARAM_STR);
					for ($i = 0; $i < count($a); $i++) {
						if (!empty($a[$i][0])) {
							$STH->bindValue($a[$i][0], $a[$i][1], $a[$i][2]);
						}
					}
					$STH->execute();
				}
			}
			if (!empty($r)) {
				if (!$from_cache) {
					$Search->write_to_caching_db2($SQLITE_CACHE, $cache_table_name, $vars2_marker, $query, $p);
				}
			}
			$DBH = null;
		} catch (PDOException $e) {
			echo $e->getMessage();
		}
	}
}
$SQLITE_CACHE = null;
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
		<meta name="keywords" content="репетитор,тушино,егэ,гиа,английский,английского,английскому,глаголы,диалоги,идиомы,ключи,неправильные,онлайн,ответы,подкасты,репетиторы,рефераты,решебник,скачать,тесты,топики,уроки,mp3,mp4"/>
		<meta name="description" content="Поиск" />
		<title>Поиск</title>
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
		<link rel="stylesheet" href="../libs/shimansky.biz-contents/css/bundle.min.css" />
	</head>
	<body>
 		<div id="circularG" role="progressbar">
			<div id="circularG_1" class="circularG"></div>
			<div id="circularG_2" class="circularG"></div>
			<div id="circularG_3" class="circularG"></div>
			<div id="circularG_4" class="circularG"></div>
			<div id="circularG_5" class="circularG"></div>
			<div id="circularG_6" class="circularG"></div>
			<div id="circularG_7" class="circularG"></div>
			<div id="circularG_8" class="circularG"></div>
		</div>
		<div id="page" role="document">
			<div class="topbanner" role="banner"></div>
			<div id="wrapper">
				<div id="sidebar-wrapper" role="navigation">
					<ul class="sidebar-icon">
						<li class="sidebar-header"><a href="../index.html" title="На Главную"><span class="sitelogo"></span></a></li>
						<li><a href="http://vk.com/public62872178" class="vk-50x50"></a></li>
						<li><a href="http://ok.ru/sergeydavydow" class="odnoklassniki-50x50"></a></li>
						<li><a href="http://vimeo.com/user20994901/videos" class="vimeo-50x50"></a></li>
						<!-- <li><a href="https://twitter.com/shimanskybiz" class="twitter-50x50"></a></li>
						<li><a href="https://englishextra.github.io/" class="github-50x50"></a></li>
						<li><a href="https://englishextra.github.io/serguei/epishev2_instagram.html" class="instagram-50x50"></a></li> -->
					</ul>
					<div class="tab-content">
						<ul class="sidebar-group tab-pane active" id="tabelements">
							<li class="navigation-header">
								<!-- <div class="searchform" role="search">
									<form class="search" method="post" action="/search/" id="search_form" enctype="application/x-www-form-urlencoded">
										<input type="text" name="q" id="search_text" autocomplete="off" placeholder="Найти" />
									</form>
								</div> -->
							</li>
				<li><a href="../pages/contents.html">Содержание</a></li>
				<li><a href="../pages/articles/articles_reading_rules_utf.html">Правила чтения</a></li>
				<li><a href="../pages/grammar/grammar_usage_of_articles_a_the.html">Артикли a&#160;/ an и&#160;the</a></li>
				<li><a href="../pages/grammar/grammar_usage_of_tenses.html">Употребление времен</a></li>
				<li><a href="../pages/grammar/grammar_phrasal_verbs.html">Фразовые глаголы</a></li>
				<li><a href="../pages/tests/tests_gia_ege_letter_sample.html">Задание &#171;Письмо&#187;</a></li>
				<li><a href="../pages/tests/tests_gia_english_test_sample.html">ГИА-9&#160;(ОГЭ)&#160;АЯ</a></li>
				<li><a href="../pages/tests/tests_ege_english_test_sample.html">ЕГЭ-11&#160;АЯ</a></li>
				<li><a href="../pages/comments.html">Написать отзыв</a></li>
				<li><a href="../search/">Поиск</a></li>
						</ul>
					</div>
				</div>
				<div id="content-wrapper">
					<div class="main-header header59636D" role="heading">
						<h3>
							<span id="show-menu" class="bt-menu-trigger-holder"><a href="#" class="bt-menu-trigger" title="Меню"></a></span>
							Поиск
						</h3>
					</div>
					<div class="main-content padding">
						<div class="row" role="grid">
							<div class="col-lg-8">
								<div id="search" class="module module-clean">
									<div class="module-header">
										<h4>Ваш запрос</h4>
									</div>
									<div class="module-content">
										<form class="form-horizontal" method="post" action="/search/" id="search_form" enctype="application/x-www-form-urlencoded">
											<div class="form-group">
												<p>
													<label class="control-label" for="search_text">Введите одно ключевое слово:</label>
													<input class="form-control" type="text" name="q" id="search_text" autocomplete="off" placeholder="Найти" />
												</p>
												<p class="textright">
													<input class="btn btn-default" id="search_form_reset_button" value="Очистить" type="reset" />
													<input class="btn btn-primary" id="search_form_submit_button" value="Отправить" type="submit" />
												</p>
											</div>
										</form>
									</div>
								</div>
								<?php
									if (!empty($query)) {
										if (!empty($r)) {
											echo '<div class="module module-clean">
													<div class="module-header">
														<h4>Результат</h4>
													</div>
													<div id="search_results" class="module-content">' . $p . '</div>
												</div>' . "\n";
										} else {
											echo '<div class="module module-clean">
													<div class="module-header">
														<h4>Результат</h4>
													</div>
													<div id="search_results" class="module-content">
														<p>Ничего не&#160;найдено. Однако Ваши запросы фиксируются и&#160;учитываются редактором. Некоторые страницы удаляются по причине недостаточного качества или сомнительного с&#160;точки зрения авторских прав контента. Стоит так&#160;же уточнить, что ресурс некоммерческий и&#160;неразвлекательный</p>
													</div>
												</div>' . "\n";
										}
									} else {
										echo '<div class="module module-clean">
												<div class="module-header">
													<h4>Результат</h4>
												</div>
												<div id="search_results" class="module-content">
													<p>Введите ключевое слово в поле поиска&#160;/ Type your keyword in the search box</p>
												</div>
											</div>' . "\n";
									}
								?>
							</div>
<div class="col-lg-4">
	<div class="module module-clean">
		<div class="module-header">
			<h4><a href="http://umnik.ru/repetitor/sergej-viktorovich-shimanskij/">Репетитор английского</a></h4>
		</div>
		<div class="module-content">
			<p>Преподаю английский школьникам, студентам и&#160;взрослым в&#160;частном порядке в&#160;Тушино. &#171;С нуля&#187;, подтянуть, улучшить, подготовка к&#160;ЕГЭ</p>
		</div>
	</div>
	<div class="module module-clean">
		<div class="module-header">
			<h4><a href="https://englishextra.github.io/pages/blog/blog_web_designer_s_online_tools.html">Web Designer&#8217;s Tools</a></h4>
		</div>
		<div class="module-content">
			<p>Another list of web designer&#8217;s online tools that mentions only those services that have been used at least a couple of times, and those used a lot on daily&#160;basis</p>
		</div>
	</div>
	<div class="module module-clean">
		<div class="module-header">
			<h4><a href="https://englishextra.github.io/libs/early.js/">early.js&#160;&#8212; to be included first</a></h4>
		</div>
		<div class="module-content">
			<p>This bunch of third-party JS libraries is meant to be included first in your HTML markup and helps to: detect user device, detect browser capabilities, conditionally load other JS scripts</p>
		</div>
	</div>
	<div class="module module-clean">
		<div class="module-header">
			<h4><a href="https://englishextra.github.io/portfolio/">Сделаю сайт-визитку</a></h4>
		</div>
		<div class="module-content">
			<p>В течение трех рабочих дней сверстаю макет сайта-визитки для Вашего бизнеса. Это&#160;обойдется Вам в&#160;среднем в&#160;4500&#8212;8000&#160;рублей в&#160;зависимости от&#160;требований к&#160;интерфейсу</p>
		</div>
	</div>
	<div class="module module-clean">
		<div class="module-header">
			<h4><a href="https://englishextra.github.io/nativeclub/">Компрессионное белье</a></h4>
		</div>
		<div class="module-content">
			<p>Компания Нэйтив (Native) предлагает Вам профессиональную утяжку, как для медицинских целей, так и&#160;для коррекции фигуры</p>
		</div>
	</div>
</div>
						</div>
						<div class="footer" role="contentinfo">
							<p class="textcenter"><a href="https://englishextra.github.io/pages/comments.html">Написать отзыв</a>&#160;&#160;&#160;&#8226;&#160;&#160; <a href="http://preply.com/repetitor-angliyskogo-yazyka/moscow/sergei-v/4598/">Репетитор английского в&#160;Тушино</a></p>
							<p class="textcenter"><span class="smaller" id="qr_code"></span></p>
							<div style="text-align:center"><div class="pluso-engine" pluso-sharer='{"buttons":"vkontakte,odnoklassniki,moimir,facebook,twitter,more","style":{"size":"medium","shape":"round","theme":"theme14","css":"background:transparent"},"orientation":"horizontal","multiline":false}'></div><p style="margin:16px 0 0 36px"><span id="vk_like"></span></p></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!--#include virtual="../virtual/yepnope.min.js.html" -->
		<script>
				yepnope.injectJs("../cdn/early.js/9ac385b2386805963c8122bb60be474b5d483989/1.0/js/early.min.js",function(){
					yepnope.injectJs("//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js", function () {
						yepnope.injectJs("../cdn/bootstrap/3.1.1/js/bootstrap.min.js", function () {
							"undefined"!==typeof window.jQuery&&$(document).ready(function(){$("#show-menu").click(function(e){e.preventDefault();$("#wrapper").toggleClass("active");});var maxHeight=0;$(".activity-feed-wrapper").each(function(){if($(this).height()>maxHeight){maxHeight=$(this).height();}});$(".activity-feed-wrapper").height(maxHeight);});
						},{charset:"utf-8"},5E3);
						yepnope.injectJs("../cdn/pnotify/1.3.1/js/jquery.pnotify.min.js",function(){
							"undefined"!==typeof window.jQuery&&domready(function(){(function(a,b,c,d,e,f){a&&function(){a&&b.click(function(){return a.val()?d.submit():(f(c),!1)});e.click(function(){a.focus()})}()})($("#search_text")||"",$("#search_form_submit_button")||"",{history:!1,stack:!1,title:"\u041d\u0435\u0443\u0441\u043f\u0435\u0448\u043d\u043e",text:"\u00a0\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u0412\u0430\u0448 \u0437\u0430\u043f\u0440\u043e\u0441!\u00a0",opacity:1,width:"280px",remove:!0,pnotify_addclass:"ui-pnotify-error", delay:3E3},$("#search_form")||"",$("#search_form_reset_button")||"",jQuery.pnotify||"")});
						},{charset:"utf-8"},5E3);
						("undefined"!==typeof earlyIsMobileBrowser&&earlyIsMobileBrowser)||yepnope.injectJs("../cdn/jquery-ui/4cec63baf3c58510296956c19b29b30f49853fc7/1.10.4/custom.autocomplete/js/jquery-ui.min.js", function () {
							"undefined"!==typeof window.jQuery&&function(d,c,e,f){$("#"+c).autocomplete({source:function(b,a){$.ajax({url:f,dataType:"json",data:{q:b.term,c:10},success:function(b){a($.map(b,function(a){return{label:a.value,value:a.name}}))}})},minLength:1,select:function(b,a){if(a.item.value&&(a.item.value.match(/^http\:\/\//)||a.item.value.match(/^https\:\/\//)||a.item.value.match(/^\/search\//)||a.item.value.match(/^\//)))return d.location.href=a.item.value,!1;$(b.target).val($("#"+c).val());$("#"+e).submit()}, open:function(){},close:function(){}})}(document,"search_text","search_form","../libs/jquery-ui/1.10.4/custom.autocomplete/autocomplete/");
						},{charset:"utf-8"},5E3);
						window.Modernizr&&Modernizr.touch&&yepnope.injectJs("../cdn/hammer.js/1.0.6/js/hammer.min.js", function () {
							(function(b){var f=/localhost/.test(self.location.host)?"http://localhost/externalcounters/":"//shimansky.biz/externalcounters/",d=b.getElementsByTagName("a")||"",a=self.location.protocol+"//"+self.location.host+"/"||"",g=self.location.host+"/"||"",h=encodeURIComponent(b.location.href||""),k=encodeURIComponent(b.title||"").replace("\x27","&#39;");if(d&&a&&g&&"undefined"!==typeof window.jQuery&&"undefined"!==typeof window.Hammer)for(var c,e,a=0;a<d.length;a+=1)if(c=d[a],(e=c.getAttribute("href")|| "")&&(e.match(/^\/scripts\//)||/(http|ftp|https):\/\/[\w-]+(\.[\w-]+)|(localhost)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/.test(e))&&!c.getAttribute("rel"))$(c).hammer().on("touch",$(this),function(a){a.preventDefault();a=$(this).attr("href");var c=b.getElementsByTagName("body")[0].firstChild,d=b.createElement("div");d.setAttribute("style","position:absolute;left:-9999px;width:1px;height:1px;border:0;background:transparent url("+f+"?dmn="+encodeURIComponent(a)+"&rfrr="+h+"&ttl="+k+"&encoding=utf-8) top left no-repeat;"); c.parentNode.insertBefore(d,c);b.location.href=a})})(document);
						},{charset:"utf-8"},5E3);
						(function(a,b){b&&(b.style.display="none");a&&(a.style.display="block")})(document.getElementById("page")||"",document.getElementById("circularG")||"");
					},{charset:"utf-8"},5E3);
					(function(e,f,a,h,k){b=document.createElement("a");b.setAttribute("style","display:none;");b.setAttribute("href","#");b.setAttribute("id",h);b.setAttribute("onclick","function scrollTop2(c){var b=window.pageYOffset,d=0,e=setInterval(function(b,a){return function(){a-=b*c;window.scrollTo(0,a);d++;(150<d||0>a)&&clearInterval(e)}}(c,b--),50)};scrollTop2(100);return false;");c=document.createElement("span");c.setAttribute("id",k);b.appendChild(c);d=document.createTextNode("\u041d\u0430\u0432\u0435\u0440\u0445");b.appendChild(d);e.appendChild(b);f.onscroll=function(){var e=f.pageYOffset||a.documentElement.scrollTop||a.body.scrollTop,k=f.innerHeight||a.documentElement.clientHeight||a.body.clientHeight,g=a.getElementById(h)||"";g&&(e>k?g.style.display="inline":g.style.display="none")}})(document.getElementsByTagName("body")[0]||document.documentElement,window,document,"toTop","toTopHover");
					("undefined"!==typeof earlyIsMobileBrowser&&!earlyIsMobileBrowser)&&window.location.protocol&&"https:"!==window.location.protocol&&(function(){if(!window.pluso){var b=document,a=b.createElement("script");a.type="text/javascript";a.charset="UTF-8";a.async=!0;a.src=("https:"==window.location.protocol?"https":"http")+"://x.pluso.ru/pluso-x.js";b.getElementsByTagName("body")[0].appendChild(a)}})();
					addEvent(window,"load",function(a){a&&a.focus()}(document.getElementById("search_text")||""),!1);
					!/localhost/.test(self.location.host)&&"undefined"!==typeof domready&&domready(function(){(function(b,a,c,d){if(c&&d){var e=encodeURIComponent(a.referrer||"");b=encodeURIComponent(b.location.href||"");a=encodeURIComponent(("undefined"!==typeof earlyDocumentTitle?earlyDocumentTitle:(a.title||"")).replace("\x27","&#39;").replace("\x28","&#40;").replace("\x29","&#41;"));c.setAttribute("style","position:absolute;left:-9999px;width:1px;height:1px;border:0;background:transparent url("+d+"?dmn="+b+"&rfrr="+e+"&ttl="+a+"&encoding=utf-8) top left no-repeat;")}})(window,document,document.getElementById("externalcounters")||"",/localhost/.test(self.location.host)?"http://localhost/externalcounters/": "//shimansky.biz/externalcounters/")});
					(function(d){var g=/localhost/.test(self.location.host)?"http://localhost/externalcounters/":"//shimansky.biz/externalcounters/",c=d.getElementsByTagName("a")||"",a=self.location.protocol+"//"+self.location.host+"/"||"",h=self.location.host+"/"||"",k=encodeURIComponent(d.location.href||""),l=encodeURIComponent((d.title||"").replace("\x27","&#39;").replace("\x28","&#40;").replace("\x29","&#41;"));if(c&&a&&h)for(a=0;a<c.length;a+=1)if(b=c[a],(e=b.getAttribute("href")||"")&&(e.match(/^\/scripts\//)||/(http|ftp|https):\/\/[\w-]+(\.[\w-]+)|(localhost)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/.test(e))&& !b.getAttribute("rel"))c[a].onclick=function(){var a=this.getAttribute("href"),c=d.getElementsByTagName("body")[0].firstChild,f=d.createElement("div");f.setAttribute("style","position:absolute;left:-9999px;width:1px;height:1px;border:0;background:transparent url("+g+"?dmn="+encodeURIComponent(a)+"&rfrr="+k+"&ttl="+l+"&encoding=utf-8) top left no-repeat;");c.parentNode.insertBefore(f,c)}})(document);
					addEvent(window,"blur",function(){(function (d,w){var a=/localhost/.test(self.location.host)?"http://localhost/externalcounters/":"//shimansky.biz/externalcounters/",b=encodeURIComponent(d.referrer||""),c=encodeURIComponent(w.location.href||""),e=encodeURIComponent((d.title||"").replace("\x27","&#39;").replace("\x28","&#40;").replace("\x29","&#41;")+" - \u0414\u043e\u043a\u0443\u043c\u0435\u043d\u0442 \u043d\u0435 \u0430\u043a\u0442\u0438\u0432\u0435\u043d"),f=d.createElement("div"),g=d.getElementsByTagName("body")[0].firstChild;f.setAttribute("style", "position:absolute;left:-9999px;width:1px;height:1px;border:0;background:transparent url("+a+"?dmn="+c+"\x26rfrr="+b+"\x26ttl="+e+"\x26encoding=utf-8) top left no-repeat;");g.parentNode.insertBefore(f,g);}(document,window));},!1);
				},{charset:"utf-8"},5E3);
		</script>
		<!--[if lt IE 9]>
		<script>(function(a,c){c.innerHTML="";c.style.color="black";c.style.backgroundColor="white";c.style.padding="1em";var b=a.createElement("div");d=a.createElement("h1");f=a.createTextNode("\u0418\u0437\u0432\u0438\u043d\u0438\u0442\u0435, \u0412\u0430\u0448 \u0431\u0440\u0430\u0443\u0437\u0435\u0440 \u043d\u0435 \u043f\u043e\u0434\u0434\u0435\u0440\u0436\u0438\u0432\u0430\u0435\u0442\u0441\u044f");d.appendChild(f);b.appendChild(d);g=a.createTextNode("\n");b.appendChild(g);h=a.createElement("p");k=a.createTextNode("\u0414\u043b\u044f \u043f\u0440\u043e\u0441\u043c\u043e\u0442\u0440\u0430 \u044d\u0442\u043e\u0439 \u0441\u0442\u0440\u0430\u043d\u0438\u0446\u044b \u0442\u0430\u043a, \u043a\u0430\u043a \u043e\u043d\u0430 \u0437\u0430\u0434\u0443\u043c\u0430\u043d\u0430, \u0441\u043a\u0430\u0447\u0430\u0439\u0442\u0435 \u0438 \u0443\u0441\u0442\u0430\u043d\u043e\u0432\u0438\u0442\u0435 \u0441\u043e\u0432\u0440\u0435\u043c\u0435\u043d\u043d\u044b\u0439 \u0438, \u043a \u0442\u043e\u043c\u0443 \u0436\u0435 \u0431\u0435\u0441\u043f\u043b\u0430\u0442\u043d\u044b\u0439 \u043e\u0431\u043e\u0437\u0440\u0435\u0432\u0430\u0442\u0435\u043b\u044c."); h.appendChild(k);b.appendChild(h);m=a.createTextNode("\n");b.appendChild(m);p=a.createElement("p");q=a.createElement("a");q.setAttribute("href","http://www.mozilla.org/ru/");r=a.createTextNode("Firefox");q.appendChild(r);p.appendChild(q);p_2_text=a.createTextNode(" / ");p.appendChild(p_2_text);s=a.createElement("a");s.setAttribute("href","http://www.opera.com/ru");t=a.createTextNode("Opera");s.appendChild(t);p.appendChild(s);u=a.createTextNode(" / ");p.appendChild(u);v=a.createElement("a");v.setAttribute("href", "https://www.google.ru/intl/ru/chrome/browser/");w=a.createTextNode("Chrome");v.appendChild(w);p.appendChild(v);x=a.createTextNode(" / ");p.appendChild(x);y=a.createElement("a");y.setAttribute("href","http://windows.microsoft.com/ru-Ru/internet-explorer/ie-11-worldwide-languages");z=a.createTextNode("Internet Explorer");y.appendChild(z);p.appendChild(y);b.appendChild(p);c.appendChild(b)})(document,document.body||document.documentElement);</script>
		<![endif]-->
		<!--#include virtual="../virtual/counters.html" -->
	</body>
</html>

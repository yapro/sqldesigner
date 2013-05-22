<?php
define('_', '~'.md5(uniqid(time())).'~');// спец. апостроф

	set_time_limit(250);
	function setup_saveloadlist() {
		include('config.php');
		define("SERVER", $setup_saveloadlist['SERVER']);
		define("USER", $setup_saveloadlist['USER']);
		define("PASSWORD", $setup_saveloadlist['PASSWORD']);
		define("DB", $setup_saveloadlist['DB']);
		define("TABLE", $setup_saveloadlist['TABLE']);
	}
	function setup_import() {
		include('config.php');
		define("SERVER", $setup_import['SERVER']);
		define("USER", $setup_import['USER']);
		define("PASSWORD", $setup_import['PASSWORD']);
		define("DB", $setup_import['DB']);
	}
	function connect() {
		if( !mysql_connect(SERVER,USER,PASSWORD) ){
			throw new Exception('mysql_connect');
		}
		if( !mysql_select_db(DB) ){
			throw new Exception('mysql_select_db');
		}
    mysql_query("SET NAMES UTF8");
    setlocale(LC_ALL, 'ru_RU.UTF-8');
    mb_internal_encoding("UTF-8");
		return true;
	}
	function import() {
		$db = (isset($_GET["database"]) ? $_GET["database"] : "information_schema");
		$db = mysql_real_escape_string($db);
		$xml = "";

		$arr = array();
		@ $datatypes = file("../../db/mysql/datatypes.xml");
		$arr[] = $datatypes[0];
		$arr[] = '<sql db="mysql">';
		for ($i=1;$i<count($datatypes);$i++) {
			$arr[] = $datatypes[$i];
		}

		if( $result = mysql_query("SELECT * FROM TABLES WHERE TABLE_SCHEMA = '".$db."'") ){
		while ($row = mysql_fetch_array($result)) {
			$table = $row["TABLE_NAME"];
			$xml .= '<table name="'.$table.'">';
			$comment = (isset($row["TABLE_COMMENT"]) ? $row["TABLE_COMMENT"] : "");
			if ($comment) { $xml .= '<comment>'.htmlspecialchars($comment).'</comment>'; }

			$q = "SELECT * FROM COLUMNS WHERE TABLE_NAME = '".$table."' AND TABLE_SCHEMA = '".$db."'";
			$result2 = mysql_query($q);
			while ($row = mysql_fetch_array($result2)) {
				$name  = $row["COLUMN_NAME"];
				$type  = $row["COLUMN_TYPE"];
				$comment = (isset($row["COLUMN_COMMENT"]) ? $row["COLUMN_COMMENT"] : "");
				$null = ($row["IS_NULLABLE"] == "YES" ? "1" : "0");

				if (preg_match("/binary/i",$row["COLUMN_TYPE"])) {
					$def = bin2hex($row["COLUMN_DEFAULT"]);
				} else {
					$def = $row["COLUMN_DEFAULT"];
				}

				$ai = (preg_match("/auto_increment/i",$row["EXTRA"]) ? "1" : "0");
				if ($def == "NULL") { $def = ""; }
				$xml .= '<row name="'.$name.'" null="'.$null.'" autoincrement="'.$ai.'">';
				$xml .= '<datatype>'.strtoupper($type).'</datatype>';
				$xml .= '<default>'.$def.'</default>';
				if ($comment) { $xml .= '<comment>'.htmlspecialchars($comment).'</comment>'; }

				/* fk constraints */
				$q = "SELECT
					REFERENCED_TABLE_NAME AS 'table', REFERENCED_COLUMN_NAME AS 'column'
					FROM KEY_COLUMN_USAGE k
					LEFT JOIN TABLE_CONSTRAINTS c
					ON k.CONSTRAINT_NAME = c.CONSTRAINT_NAME
					WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
					AND c.TABLE_SCHEMA = '".$db."' AND c.TABLE_NAME = '".$table."'
					AND k.COLUMN_NAME = '".$name."'";
				$result3 = null;//mysql_query($q);

				while ($row = mysql_fetch_array($result3)) {
					$xml .= '<relation table="'.$row["table"].'" row="'.$row["column"].'" />';
				}

				$xml .= '</row>';
			}

			/* keys */
			$q = "SELECT * FROM STATISTICS WHERE TABLE_NAME = '".$table."' AND TABLE_SCHEMA = '".$db."' ORDER BY SEQ_IN_INDEX ASC";
			$result2 = mysql_query($q);
			$idx = array();

			while ($row = mysql_fetch_array($result2)) {
				$name = $row["INDEX_NAME"];
				if (array_key_exists($name, $idx)) {
					$obj = $idx[$name];
				} else {
					$type = $row["INDEX_TYPE"];
					$t = "INDEX";
					if ($type == "FULLTEXT") { $t = $type; }
					if ($row["NON_UNIQUE"] == "0") { $t = "UNIQUE"; }
					if ($name == "PRIMARY") { $t = "PRIMARY"; }

					$obj = array(
						"columns" => array(),
						"type" => $t
					);
				}

				$obj["columns"][] = $row["COLUMN_NAME"];
				$idx[$name] = $obj;
			}

			foreach ($idx as $name=>$obj) {
				$xml .= '<key name="'.$name.'" type="'.$obj["type"].'">';
				for ($i=0;$i<count($obj["columns"]);$i++) {
					$col = $obj["columns"][$i];
					$xml .= '<part>'.$col.'</part>';
				}
				$xml .= '</key>';
			}
			$xml .= "</table>";
		}
		}else{
	        throw new Exception( mysql_error() );
		}
		$arr[] = $xml;
		$arr[] = '</sql>';
		return implode("\n",$arr);
	}

	$a = (isset($_GET["action"]) ? $_GET["action"] : false);

	switch ($a) {
		case "list":
			setup_saveloadlist();
			if (!connect()) {
				header("HTTP/1.0 503 Service Unavailable");
				break;
			}
			$result = mysql_query("SELECT keyword FROM ".TABLE." ORDER BY dt DESC");
			while ($row = mysql_fetch_assoc($result)) {
				echo $row["keyword"]."\n";
			}
		break;
		case "save":
			setup_saveloadlist();
			if (!connect()) {
				header("HTTP/1.0 503 Service Unavailable");
				break;
			}
			$keyword = (isset($_GET["keyword"]) ? $_GET["keyword"] : "");
			$keyword = mysql_real_escape_string($keyword);
			$data = file_get_contents("php://input");
			if (get_magic_quotes_gpc() || get_magic_quotes_runtime()) {
			   $data = stripslashes($data);
			}
			$data = mysql_real_escape_string($data);
			$r = mysql_query("SELECT * FROM ".TABLE." WHERE keyword = '".$keyword."'");
			if (mysql_num_rows($r) > 0) {
				$res = mysql_query("UPDATE ".TABLE." SET data = '".$data."' WHERE keyword = '".$keyword."'");
			} else {
				$res = mysql_query("INSERT INTO ".TABLE." (keyword, data) VALUES ('".$keyword."', '".$data."')");
			}
			if (!$res) {
				header("HTTP/1.0 500 Internal Server Error");
			} else {
				header("HTTP/1.0 201 Created");
			}
		break;
		case "load":
			setup_saveloadlist();
			if (!connect()) {
				header("HTTP/1.0 503 Service Unavailable");
				break;
			}
			$keyword = (isset($_GET["keyword"]) ? $_GET["keyword"] : "");
			$keyword = mysql_real_escape_string($keyword);
			$result = mysql_query("SELECT `data` FROM ".TABLE." WHERE keyword = '".$keyword."'");
			$row = mysql_fetch_assoc($result);
			if (!$row) {
				header("HTTP/1.0 404 Not Found");
			} else {
				header("Content-type: text/xml");
				echo $row["data"];
			}
		break;
		case "import":
			setup_import();
			if (!connect()) {
				header("HTTP/1.0 503 Service Unavailable");
				break;
			}

			header("Content-type: text/xml");
			echo import();
		break;

    case 'setRelationshipFields':

      header('Content-type: application/json');

      if( !$_POST || !isset($_POST['data']) || !$_POST['data'] ){
        echo json_encode(array('msg'=>'data empty'));
        exit;
      }

      setup_saveloadlist();
      if (!connect()) {
        header("HTTP/1.0 503 Service Unavailable");
        break;
      }

      yapro::mysql('TRUNCATE TABLE relationship_fields');

      foreach($_POST['data'] as $r){
/*
        $r = @mysql_fetch_assoc( yapro::mysql('SELECT * FROM relationship_fields WHERE
        ( table1 = '._.$r['table1']._.' AND field1 = '._.$r['field1']._.' AND table2 = '._.$r['table2']._.' AND field2 = '._.$r['field2']._.' )
        OR
        ( table1 = '._.$r['table2']._.' AND field1 = '._.$r['field2']._.' AND table2 = '._.$r['table1']._.' AND field2 = '._.$r['field1']._.' )
        ') );

        if( !$r ){

        }
*/
          yapro::mysql('INSERT INTO relationship_fields SET
          table1 = '._.$r['table1']._.',
          field1 = '._.$r['field1']._.',
          table2 = '._.$r['table2']._.',
          field2 = '._.$r['field2']._);

      }

      echo json_encode(array('msg'=>'sucessfull saved'));

      break;

    case 'getRelationshipFields':

      header('Content-type: application/json');

      setup_saveloadlist();
      if (!connect()) {
        header("HTTP/1.0 503 Service Unavailable");
        break;
      }

      $data = array();

      if( $q = yapro::mysql('SELECT * FROM relationship_fields') ){

        while ($r = mysql_fetch_array($q)) {

          $data[] = $r;

        }
      }

      echo json_encode(array('data'=>$data));

      break;

		default: header("HTTP/1.0 501 Not Implemented");
	}

class yapro
{
  static $mysql_errno = 0;

  // запись данных в лог-файл
  static function log($data = '')
  {
    if($data){

      $path = __FILE__.'.log';

      if( $fp = fopen($path, 'a') ){

        fwrite ($fp, "\n---------------DATE: ".date("H:i:s d.m.Y")."--------------\n".(is_array($data)? print_r($data,1) : $data) );
        fclose ($fp);
        @chmod($path, 0664);

      }else{
        throw new Exception('access write');
      }
    }
  }
  // безопасно выполняет запрос
  static function mysql($s = '', $print = null, $ignore_errno = 0)
  {
    $e = explode(_, $s);

    if( count($e) > 1 ){

      $sql = self::sql($s, $print);

    }else{

      $sql = $s;

      if($print=='log' || $print==2){

        self::log($sql);

      }else if($print){

        echo "<br>".$sql.'<br>';

      }
    }

    self::$mysql_errno = 0;

    $q = mysql_query($sql);

    self::$mysql_errno = $errno = mysql_errno();

    if($errno && (!$ignore_errno || ($ignore_errno && $errno!=$ignore_errno) ) ){// 1062 - Duplicate

      self::error('mysql_ : '.$errno.' : '.mysql_error()."\n".$sql);

    }
    return $q;
  }

  // Escape-ирует запрос
  static function sql($s='', $print=false){

    $switch_check = strtolower(mb_substr($s, 0, 6));

    switch ($switch_check){

      case 'insert':

        $s = str_replace("\\", "\\\\", $s);
        break;

      case 'update':

        $e = explode(_.'WHERE'._, $s);
        if($e['1']){
          $s = str_replace("\\", "\\\\", $e['0']).' WHERE '.str_replace("\\", "\\\\\\\\", $e['1']);
        }else{
          $s = str_replace("\\", "\\\\", $s);
        }
        break;

      case 'delete':

        $s = str_replace("\\", "\\\\\\\\", $s);
        break;

      default://--select
        //3.x $s = str_replace("\\", "\\\\\\\\\\\\\\", $s);
        $s = str_replace("\\", "\\\\", $s);
    }

    //echo '<!-- '.str_replace(_, "'", str_replace("'", "''", $s)).' -->';

    self::log('z='._);

    if($print==='log' || $print==2){

      self::log(str_replace(_, "'", str_replace("'", "''", $s)));

    }else if($print){

      echo "<br>".str_replace(_, "'", str_replace("'", "''", $s)).'<br>';

    }

    return str_replace(_, "'", str_replace("'", "''", $s));
  }
  // в тех случаях, когда допущена обишка разработчиком (функция отладки)
  static function error($text='не указана'){

    $error = 'Ошибка на странице: '.$_SERVER['REQUEST_URI']."\n".
      'При переходе с страницы: '.$_SERVER['HTTP_REFERER']."\n".
      ($GLOBALS['SYSTEM_SCRIPT']?'SYSTEM_SCRIPT: '.$GLOBALS['SYSTEM_SCRIPT']."\n":'').
      'IP пользователя: '.$_SERVER['REMOTE_ADDR']."\n".
      'Отладочная информация: '.$text."\n".
      (mysql_errno()?"-----------------------MySQL-----------------------\n".mysql_error()."\n":'').
      ($_SESSION?"------------------------SESSION----------------------\n".print_r($_SESSION, true):'').
      ($_COOKIE?"------------------------COOKIE----------------------\n".print_r($_COOKIE, true):'').
      ($_GET?"-----------------------GET-----------------------\n".print_r($_GET, true)."\n":'').
      ($_POST?"------------------------POST----------------------\n".print_r($_POST, true)."\n":'').
      ($_FILES?"------------------------FILES----------------------\n".print_r($_FILES, true):'');

    self::log($error);

    $return = 'Извините, возникла ошибка программного характера.<br />
    Программный отдел уже осведомлен и решает данную проблему.<br />
    Приносим свои извинения за доставленное неудобство.';

    return $_POST['ajax']? str_replace('<br />', " \n", $return) : $return;
  }
}

	/*
		list: 501/200
		load: 501/200/404
		save: 501/201
		import: 501/200
	*/
?>
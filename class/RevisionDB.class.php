<?php

if(!defined("REVISION_DB")) {
	define("REVISION_DB",TRUE);
	define("BOK_TARGET_DB_MAIN","bok_tree");
	define("BOK_TARGET_DB_USER","user_bok_tree");
	define("BOK_TARGET_DB_SAVE","save_bok_tree");
	define("BOK_TARGET_DB_MERGER","merger_class");
	define("BOK_TARGET_DB_CONFLICT","conflict_log");
	define("BOK_COLUMN_SEP_IDS","\n");
	define("BOK_COLUMN_SEP_PATH","\1");
}
require_once("MergerBackup.class.php");

class RevisionDB {

	protected $db;
	protected $user;
	protected $data;
	protected $edit;

	/**
	 * コンストラクタ
	 */
	public function __construct($dbhost, $dbname, $dbusername, $dbpassword,$session="") {
		$this->__init($dbhost, $dbname, $dbusername, $dbpassword);
		$this->user = "";
		if(empty($session)) {
			$this->session = session_id();
		}
		else {
			$this->session = $session;
		}
	}

	/**
	 * 必要なテーブルを一括作成
	 *  - インストール用に外部呼出し可能としておく
	 */
	public function __create($dbhost, $dbname, $dbusername, $dbpassword) {
		//DB接続インスタンスの再作成(DB指定された接続に変更)
		$this->__init($dbhost, $dbname, $dbusername, $dbpassword);
		//MAINテーブル
		$this->createMainTable();
		//USERテーブル作成
		$this->createUserTable();
		//データ保存用テーブル作成
		$this->createSaveTable();
		//競合解消用クラス履歴テーブル作成
		$this->createMergerClassTable();
		//編集競合ログテーブル作成
		$this->createConflictLogTable();
	}
	/**
	 * ユーザーIDを設定する
	 */
	public function setUser($user,$change=FALSE) {
		if(empty($user) || is_null($user)) {
			$this->user = 0;
		}
		else {
			if(!$change) {
				$this->user = User::idFromName($user);
			}
			else {
				$this->user = $user;
			}
		}
	}
	/**
	 * コミット前(編集中)のBOKデータより、指定リビジョンのデータを取得
	 * @param $rev	指定リビジョン番号
	 */
	public function getUserRev($rev) {
		$sql  = 'SELECT * FROM '.BOK_TARGET_DB_USER.'  ';
		//指定リビジョンが最新リビジョンより大きい場合を考慮
		$sql .= ' WHERE (rev <= ? ';
		//指定リビジョンが初回リビジョンより大きい場合を考慮
		$sql .= '    OR  rev = ?)';
		$sql .= '   AND  session_id = ?';
		$sql .= '   AND  user_id = ?';
		$sql .= ' ORDER BY rev DESC ';
		$sql .= ' LIMIT 1';
		$sth = $this->db->prepare($sql);
		$sth->execute(array(
			$rev,
			$rev,
			$this->session,
			$this->user
		));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		return ($data);
	}
	/**
	 * コミット済みのBOKデータより、指定リビジョンのデータを取得
	 * @param $rev 指定リビジョン番号
	 */
	public function getBokRev($rev="") {
		if(empty($rev)) {
			$data = $this->getBokHead();
		}
		else {
			$sql  = 'SELECT * FROM '.BOK_TARGET_DB_MAIN.'  ';
			//指定リビジョンが最新リビジョンより大きい場合を考慮
			$sql .= ' WHERE rev <= ? ';
			//指定リビジョンが初回リビジョンより大きい場合を考慮
			$sql .= '    OR rev = 1 ';
			$sql .= ' ORDER BY rev DESC ';
			$sql .= ' LIMIT 1';
			$sth = $this->db->prepare($sql);
			$sth->execute(array($rev));
			$data = $sth->fetch(PDO::FETCH_ASSOC);
		}
		return ($data);
	}
	/**
	 * コミット済みのBOKデータより、最新リビジョンのデータを取得
	 */
	public function getBokHead() {
		$sql  = 'SELECT * FROM '.BOK_TARGET_DB_MAIN.'  ';
		$sql .= ' ORDER BY rev DESC ';
		$sql .= ' LIMIT 1';
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if($data === FALSE) {
			//データがない場合は、空データをinsertしておく
			$bok = new BokXml();
			$empty_data = array(
				'bok' => $bok->saveXML(),
				'new_ids' => '',
				'del_ids' => '',
				'user_id' => $this->user
			);
			$this->insertMain($empty_data);
			//データの再取得
			$sth->execute();
			$data = $sth->fetch(PDO::FETCH_ASSOC);
		}
		return ($data);
	}
	/**
	 * 対象ユーザーが編集中のデータより、最新のデータを取得
	 */
	public function getUserHead() {
		if($this->getUserEdit()) {
			$sql  = 'SELECT * FROM '.BOK_TARGET_DB_USER.'  ';
			$sql .= ' WHERE session_id = ?';
			$sql .= '   AND user_id = ?';
			$sql .= ' ORDER BY rev DESC ';
			$sql .= ' LIMIT 1';
			$sth = $this->db->prepare($sql);
			$sth->execute(array(
				$this->session,
				$this->user
			));
			$data = $sth->fetch(PDO::FETCH_ASSOC);
			return ($data);
		}
		else {
			return (FALSE);
		}
	}
	/**
	 * 対象ユーザーが編集中のデータより、編集開始時点のデータを取得
	 * @param	$user	対象ユーザーID/セッションID
	 */
	public function getUserBase() {
		if($this->getUserEdit()) {
			$sql  = 'SELECT * FROM '.BOK_TARGET_DB_USER.'  ';
			$sql .= ' WHERE session_id = ?';
			$sql .= '   AND user_id = ?';
			$sql .= ' ORDER BY rev ASC ';
			$sql .= ' LIMIT 1';
			$sth = $this->db->prepare($sql);
			$sth->execute(array(
				$this->session,
				$this->user
			));
			$data = $sth->fetch(PDO::FETCH_ASSOC);
			return ($data);
		}
		else {
			return (FALSE);
		}
	}
	/**
	 * 名前を付けてBOKデータを保存する
	 * @param	$datat	(array)
	 */
	public function saveBokData($data) {
		//ユーザIDを追加
		$data += array('user_id' => $this->user);
		//ソート
		ksort($data);
		//項目名・データを抽出
		$k = array_keys($data);
		$param = array_values($data);
		$v = array_fill(0,count($param),'?');

		$sql  = 'INSERT INTO '.BOK_TARGET_DB_SAVE.' (';
		$sql .= '`'.implode('`,`',$k).'`';
		$sql .= ') VALUES ('.implode(',',$v).')';
		$sth = $this->db->prepare($sql);
		if($sth->execute($param) === FALSE) {
			//データ登録ができない場合、テーブルを作成...
			$this->createSaveTable();
			//再度データ登録を行う
			return $sth->execute($param);
		}
		else {
			return true;
		}
	}
	/**
	 * 保存済みBOKデータを取得
	 * @param $name	保存名称
	 */
	public function loadBokData($name) {
		$sql  = 'SELECT * FROM '.BOK_TARGET_DB_SAVE.' ';
		$sql .= ' WHERE title = ?';
		$sql .= '   AND user_id = ?';
		$sql .= ' LIMIT 1';
		$sth = $this->db->prepare($sql);
		if($sth->execute(array($name,$this->user)) === FALSE) {
			return (FALSE);
		}
		else {
			$data = $sth->fetch(PDO::FETCH_ASSOC);
			return ($data);
		}
	}
	/**
	 * 編集用のBOK(XML形式)を取得する
	 * @param	$rev	リビジョン番号(省略時:最新REV)
	 */
	public function getEditData($rev="") {
		//編集中のデータがあるか確認
		if($this->getUserEdit()) {
			//指定リビジョン番号があれば、そのリビジョンデータを取得
			if(empty($rev)) {
				$data = $this->getUserHead();
				if($data === FALSE) {
					$data = $this->getBokHead();
				}
			}
			else {
				$data = $this->getUserRev($rev);
				if($data === FALSE) {
					$data = $this->getBokRev($rev);
				}
			}
		}
		else {
			//指定リビジョン番号があれば、そのリビジョンデータを取得
			if(empty($rev)) {
				$data = $this->getBokHead();
			}
			else {
				$data = $this->getBokRev($rev);
			}
		}
		return ($data);
	}
	/**
	 * メインテーブルにデータを格納する
	 * @param	$rev	リビジョン番号
	 * @param	$bok	変更後BOK(XML形式)
	 * @param	$eSet	編集ノード情報
	 */
	public function setBokData($rev,$bok,$eSet) {
		//編集ノード情報を種別(追加/削除)ごとに分割
		$ids = array();
		foreach($eSet as $key => $set) {
			$tmp = array();
			foreach($set as $node => $path) {
				//ノード名称+パス情報
				$tmp[] = $node.BOK_COLUMN_SEP_PATH.$path;
			}
			//複数ノードの場合、区切り文字を利用して結合
			$ids[$key] = implode(BOK_COLUMN_SEP_IDS,$tmp); 
		}
		$data = array(
			'rev' => $rev,
			'bok' => $bok,
			//種別ごとに別カラムへ格納する(その種別が存在しない=>空文字とする)
			'new_ids' => (array_key_exists('add',$ids)) ? $ids['add'] : '',
			'del_ids' => (array_key_exists('del',$ids)) ? $ids['del'] : ''
		);
		//DBへ登録
		return $this->insertMain($data);
	}
	/**
	 * ユーザ用テーブルから指定リビジョン番号より後のデータを削除する
	 *   - UNDO/REDO時対策用に登録以降の編集データを削除
	 * @param $rev	リビジョン番号
	 */
	public function clearEditData($rev = 0) {
		$del  = 'DELETE FROM '.BOK_TARGET_DB_USER.' ';
		$del .= ' WHERE `session_id` = ? ';
		$del .= '   AND `user_id` = ?';
		if($rev == 0) {
			$param = array($this->session,$this->user);
		}
		else {
			$del .= '   AND `rev` > ?';
			$param = array($this->session,$this->user,$rev);
		}
		$sth = $this->db->prepare($del);
		$sth->execute($param);
		return;
	}
	/**
	 * マージ結果をDBへ仮登録
	 *   - 仮登録エリアとしてユーザテーブルのリビジョン番号0を使用
	 * @param	$work	登録するBOK-XML
	 */
	public function setMergeTemporary($work) {
		$param = array($this->session,$this->user,0);
		//既存のテンポラリデータを削除
		$del  = 'DELETE FROM '.BOK_TARGET_DB_USER.' ';
		$del .= ' WHERE `session_id` = ? ';
		$del .= '   AND `user_id` = ?';
		$del .= '   AND `rev` = ?';
		$sth = $this->db->prepare($del);
		$sth->execute($param);
		//データ登録
		$sql  = 'INSERT INTO '.BOK_TARGET_DB_USER.' ';
		$sql .= ' (`session_id`,`user_id`,`rev`,`bok`) ';
		$sql .= 'VALUES ';
		$sql .= ' ( ? , ? , ? , ? )';
		$sth = $this->db->prepare($sql);
		array_push($param,$work);
		$sth->execute($param);
		return;
	}
	/**
	 * 仮登録されているマージ結果をDBから取得
	 *   - 仮登録エリアとしてユーザテーブルのリビジョン番号0を使用
	 */
	public function getMergeTemporary() {
		$data = $this->getUserRev(0);
		$param = array($this->session,$this->user,0);
		//既存のテンポラリデータを削除
		$del  = 'DELETE FROM '.BOK_TARGET_DB_USER.' ';
		$del .= ' WHERE `session_id` = ? ';
		$del .= '   AND `user_id` = ?';
		$del .= '   AND `rev` = ?';
		$sth = $this->db->prepare($del);
		$sth->execute($param);
		return $data['bok'];
	}

	/**
	 * ユーザー編集テーブルにデータを格納する
	 * @param	$rev	変更元リビジョン番号
	 * @param	$work	変更後BOK(XML形式)
	 */
	public function setEditData($rev,$work) {
		if(($head = $this->getUserHead()) === FALSE){
			//編集開始データの作成(表示中のベースREVをもとにする...)
			$head = $this->getBokRev($rev);
			$sql  = 'INSERT INTO '.BOK_TARGET_DB_USER.' ';
			$sql .= ' (`session_id`,`rev`,`bok`,`user_id`) ';
			$sql .= 'VALUES ';
			$sql .= ' ( ? , ? , ? , ? )';
			$sth = $this->db->prepare($sql);
			$param = array(
						$this->session,
						$head['rev'],
						$head['bok'],
						$this->user
					);
			$sth->execute($param);
		}
		$_rev = intval($head['rev']);
		//不要な編集データをクリア(UNDO状態からの編集など)
		$this->clearEditData($rev);
		if(!empty($rev)) {
			$rev = intval($rev) + 1;
			//データ登録
			$sql  = 'INSERT INTO '.BOK_TARGET_DB_USER.' ';
			$sql .= ' (`session_id`,`rev`,`bok`,`user_id`) ';
			$sql .= 'VALUES ';
			$sql .= ' ( ? , ? , ? , ? )';
			$sth = $this->db->prepare($sql);
			$param = array(
						$this->session,
						$rev,
						$work,
						$this->user
					);
			$sth->execute($param);
		}
		$result = $rev;
		return $result;
	}
	/**
	 * 編集競合解消条件リビジョンをもとにバックアップ済みかどうかを判定する
	 *  - バックアップが存在しない場合には、作成する
	 */
	public function setMergeBackup() {
		$data = $this->getMerger();
		//対象データがない場合には、現在使用中のファイルをもとにバックアップを作成
		if($data === FALSE) {
			require_once(BOK_MERGER_XMLCLASS);
			require_once(BOK_MERGER_MERGERCLASS);
			//バックアップファイル作成クラスのインスタンス化
			$backup = new MergerBackup();
			//バックアップ日時の取得
			$time = time();
			//バックアップファイル作成(フォルダ込み)
			$xml_file = $backup->make_backup(BOK_MERGER_XMLCLASS,$time);
			$merge_file = $backup->make_backup(BOK_MERGER_MERGERCLASS,$time);
//			//各クラス内のパラメータを保持するためインスタンスを作成
//			$bokxml = new BokXml();
//			$merger = new BokXmlMerger();
//			//データ登録
//			$this->setMerger(array(
//				BOKMERGE_REV,
//				date('YmdHis',$time),
//				$merger->getConfig('BOKMERGE_FIRSTUSER_INTENTION'),
//				$merger->getConfig('BOKMERGE_DELETE_NODE_CHILED'),
//				$merge_file,
//				$xml_file,
//				serialize($merger),
//				serialize($bokxml),
//				date('Y-m-d H:i:s',$time)
//			));
		}
		else {
			if($data['merge_rev'] == BOKMERGE_REV) {
				return TRUE;
			}
		}
		return FALSE;
	}
	/**
	 * 編集競合条件の一覧を取得
	 *  - (前提)RULE_TITLE にその編集競合条件の名称を設定する
	 */
	public function getMergeConfigRevList() {
		$sql  = "SELECT merge_rev rev,max(value) name,max(time) last";
		$sql .= "  FROM ".BOK_TARGET_DB_MERGER." ";
		$sql .= " WHERE item_name = 'RULE_TITLE'";
		$sql .= " GROUP BY merge_rev";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);
		if(count($result) > 0) {
			return $result;
		}
		else {
			/**
			 * デフォルト値を挿入して、そのデータを返すべき?
			 */
			return FALSE;
		}
	}
	/**
	 * 指定したリビジョン番号の編集競合解消条件を取得
	 *  - ただし、ここでは設定項目の内容を取得しない
	 *    (条件A にどんな内容があるかは不問で、条件A = Bのみ取得可能)
	 * @param	$rev	編集競合条件リビジョン番号
	 */
	public function getMergeConfigDataList($rev,$search=FALSE) {
		$param = array();
		array_push($param,$rev);
		$sql  = "";
		$sql .= "SELECT item_name,value_name,value ";
		$sql .= "  FROM ".BOK_TARGET_DB_MERGER." ";
		$sql .= " WHERE merge_rev = ? ";
		//マージルールタイトルと項目内容の名称設定データを除く
		$sql .= "   AND item_name != 'RULE_TITLE' ";
		if($search !== FALSE) {
			$sql .= "   AND search_flg = ? ";
			array_push($param,$search);
		}
		$sql .= "   AND value_name = '' ";
		$sql .= " ORDER BY sort_order,item_name , value ";
		$sth = $this->db->prepare($sql);
		$sth->execute($param);
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);
		if(count($result) > 0) {
			return $result;
		}
		else {
			return FALSE;
		}
	}
	/**
	 * 条件番号-項目名を指定して、設定可能な選択肢を取得
	 * @param	$rev	編集競合条件リビジョン番号
	 * @param	$name	設定項目名称
	 */
	public function getMergeConfigValueList($rev,$name) {
		$sql  = "";
		$sql .= "SELECT item_name,value_name,value ";
		$sql .= "  FROM ".BOK_TARGET_DB_MERGER." ";
		$sql .= " WHERE merge_rev = ? ";
		$sql .= "   AND item_name = ? ";
		$sql .= "   AND value_name != '' ";
		$sql .= " ORDER BY sort_order, value,value_name ";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($rev,$name));
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);
		if(count($result) > 0) {
			return $result;
		}
		else {
			return FALSE;
		}
	}
	/**
	 * マージルールを取得
	 * @param	$rev	マージルールリビジョン番号
	 */
	public function getMergeSetting($rev) {
		//設定値の読み込み
		$sql  = "";
		$sql .= "SELECT a.item_name name,b.value ";
		$sql .= "  FROM ".BOK_TARGET_DB_MERGER." a ";
		$sql .= "  LEFT JOIN ".BOK_TARGET_DB_MERGER." b ON (";
		$sql .= "      a.merge_rev = b.merge_rev ";
		$sql .= "  AND a.item_name = b.item_name ";
		$sql .= "  AND a.value = b.value_name ";
		$sql .= " ) ";
		$sql .= " WHERE a.merge_rev = ? ";
		//マージルールタイトルと項目内容の名称設定データを除く
		$sql .= "   AND a.item_name != 'RULE_TITLE' ";
		$sql .= "   AND a.value_name = '' ";
		$sql .= " ORDER BY name , value ";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($rev));
		$items = $sth->fetchAll(PDO::FETCH_NUM);
		//判定値の読み込み
		$sql  = "";
		$sql .= "SELECT concat(item_name,'_',value_name) name,value ";
		$sql .= "  FROM ".BOK_TARGET_DB_MERGER." ";
		$sql .= " WHERE merge_rev = ? ";
		//マージルールタイトルと項目内容の名称設定データを除く
		$sql .= "   AND item_name != 'RULE_TITLE' ";
		$sql .= "   AND value_name != '' ";
		$sql .= " ORDER BY name , value ";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($rev));
		$datas = $sth->fetchAll(PDO::FETCH_NUM);
		//項目名(設定値)、判定値を合わせて戻り値とする
		$result = array_merge($items,$datas);
		if(count($result) > 0) {
			return $result;
		}
		else {
			return FALSE;
		}
	}
	/**
	 * マージルールを設定
	 */
	public function setMergeSetting($rev,$data) {
	}
	/**
	 * 編集競合データ登録
	 */
	public function setEditConflict($data) {
		//対象テーブル作成(未作成の場合作成する)
		$this->createConflictLogTable();
		//データにユーザーIDを追加
		$data += array('user_id' => $this->user);
		//ソート
		ksort($data);
		$k = array_keys($data);
		$param = array_values($data);
		$v = array_fill(0,count($param),'?');
		$sql  = 'INSERT INTO '.BOK_TARGET_DB_CONFLICT.' (';
		$sql .= '`'.implode('`,`',$k).'`';
		$sql .= ') VALUES ('.implode(',',$v).')';
		$sth = $this->db->prepare($sql);
		return ($sth->execute($param));
	}
	/**
	 * 競合クラス保存テーブルへのデータ追加処理
	 * デフォルトデータを登録していないと競合種別がすべてNoEdit扱いになってしまう
	 */
	public function setMerger($data) {
		//対象テーブル作成(未作成の場合作成する)
		$this->createMergerClassTable();
		//ソート
		ksort($data);
		$k = array_keys($data);
		$param = array_values($data);
		$v = array_fill(0,count($param),'?');
		$sql  = 'INSERT INTO '.BOK_TARGET_DB_MERGER.' (';
		$sql .= '`'.implode('`,`',$k).'`';
		$sql .= ') VALUES ('.implode(',',$v).')';
		$sth = $this->db->prepare($sql);
		return ($sth->execute($param));
	}
	/**
	 * 編集競合データ取得
	 */
	public function getEditConflictList() {
		$result = array();
		$sql  = 'SELECT log.id, log.type, log.user_id, log.time';
		$sql .= '  FROM '.BOK_TARGET_DB_CONFLICT.' log ';
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);
		return $result;
	}
	public function getEditConflict($num) {
		$sql  = 'SELECT * FROM '.BOK_TARGET_DB_CONFLICT.' ';
		$sql .= ' WHERE id = ?';
		$sth = $this->db->prepare($sql);
		$sth->execute(array($num));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		return $data;
	}
	/**
	 * 編集競合データ検索
	 */
	public function searchEditConflict($type) {
	}
/*******************************************************************************
 * 以下、プライベートメソッド
 *******************************************************************************/
	/**
	 * DB接続インスタンスの作成
	 */
	private function __init($dbhost, $dbname, $dbusername, $dbpassword) {
		try {
			//データベース指定して接続を作成
			$this->db = new \PDO(
				"mysql:host={$dbhost};dbname={$dbname}",
				$dbusername,
				$dbpassword,
				array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8')
			);
		}
		catch(PDOException $e) {
			$message = $e->getMessage();
			if(strpos($message,"Unknown database") !== FALSE) {
				//データベース指定せずに接続を作成
				$this->db = new \PDO(
					"mysql:host={$dbhost};",
					$dbusername,
					$dbpassword,
					array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8')
				);
				//データベースを作成
				$this->db->exec('CREATE DATABASE '.$dbname.' DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci');
				//ついでに使用するテーブルを作成
				$this->__create($dbhost, $dbname, $dbusername, $dbpassword);
			}
			else {
				var_dump($message);
			}
		}
	}
	/**
	 * 対象ユーザーが編集中データを保持しているか確認
	 * @return	TRUE:編集中データあり/FALSE:編集中データなし
	 * @access	private
	 */
	private function getUserEdit() {
		$sql  = 'SELECT COUNT(*) cnt FROM '.BOK_TARGET_DB_USER.' ';
		$sql .= ' WHERE session_id = ?';
		$sql .= '   AND user_id = ?';
		$sth = $this->db->prepare($sql);
		$sth->execute(array(
			$this->session,
			$this->user
		));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if($data["cnt"] <= 0) {
			return (FALSE);
		}
		else {
			return (TRUE);
		}
	}
	/**
	 * メインテーブルへのデータ追加処理
	 * @param	$bok	BOK(XML形式)
	 * @param	$diff	差分配列
	 */
	private function insertMain($data) {
		//対象テーブル作成(未作成の場合作成する)
		$this->createMainTable();
		//データにユーザーIDを追加
		$data += array('user_id' => $this->user);
		//ソート
		ksort($data);
		$k = array_keys($data);
		$param = array_values($data);
		$v = array_fill(0,count($param),'?');
		//クエリ生成
		$sql  = 'INSERT INTO '.BOK_TARGET_DB_MAIN.' (';
		$sql .= '`'.implode('`,`',$k).'`';
		$sql .= ') VALUES ('.implode(',',$v).')';
		$sth = $this->db->prepare($sql);
		return ($sth->execute($param));
	}
	/**
	 * データベースにメインテーブルを作成
	 */
	private function createMainTable() {
		$ddl  = 'CREATE TABLE IF NOT EXISTS '.BOK_TARGET_DB_MAIN.' (';
		$ddl .= '  rev     int(10) unsigned NOT NULL AUTO_INCREMENT,';
		$ddl .= '  bok     longtext,';
		$ddl .= '  new_ids longtext,';
		$ddl .= '  del_ids longtext,';
		$ddl .= '  user_id int(10) NOT NULL,';
		$ddl .= '  time    timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,';
		$ddl .= '  PRIMARY KEY `rev` (rev),';
		$ddl .= '  KEY `user` (user_id),';
		$ddl .= '  KEY `time` (time)';
		$ddl .= ') ENGINE=InnoDB  DEFAULT CHARSET=utf8';
		$this->db->exec($ddl);
	}
	/**
	 * データベースにユーザーテーブルを作成
	 */
	private function createUserTable() {
		$ddl  = 'CREATE TABLE IF NOT EXISTS '.BOK_TARGET_DB_USER.' (';
		$ddl .= '  session_id varchar(30) NOT NULL,';
		$ddl .= '  rev        int(10) unsigned NOT NULL,';
		$ddl .= '  bok        longtext,';
		$ddl .= '  user_id    int(10) NOT NULL,';
		$ddl .= '  time       timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,';
		$ddl .= '  PRIMARY KEY `user_edit` (session_id,user_id,rev),';
		$ddl .= '  KEY `user` (user_id),';
		$ddl .= '  KEY `time` (time)';
		$ddl .= ') ENGINE=InnoDB  DEFAULT CHARSET=utf8';
		$this->db->exec($ddl);
	}
	/**
	 * 競合解消条件を保存するためのテーブルを作成
	 */
	private function createMergerClassTable() {
		$ddl  = 'CREATE TABLE IF NOT EXISTS '.BOK_TARGET_DB_MERGER.' (';
		$ddl .= ' merge_rev  int(10) unsigned NOT NULL, ';
		$ddl .= ' item_name  varchar(255) NOT NULL, ';
		$ddl .= ' value_name varchar(255) NOT NULL DEFAULT \'\' , ';
		$ddl .= ' value      varchar(255) NOT NULL, ';
		$ddl .= ' search_flg tinyint(1) NOT NULL DEFAULT 0, ';
		$ddl .= ' sort_order int(10) NOT NULL DEFAULT 0, ';
		$ddl .= ' user_id    int(10) NOT NULL, ';
		$ddl .= ' time       timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, ';
		$ddl .= ' PRIMARY KEY `rev` (merge_rev,item_name,value_name), ';
		$ddl .= ' KEY `form` (merge_rev,search_flg) ';
		$ddl .= ') ENGINE=InnoDB  DEFAULT CHARSET=utf8';
		$this->db->exec($ddl);
	}
	/**
	 * 競合ログ取得用テーブルを作成
	 */
	private function createConflictLogTable() {
		$ddl  = 'CREATE TABLE IF NOT EXISTS '.BOK_TARGET_DB_CONFLICT.' (';
		$ddl .= ' id        int(10) unsigned NOT NULL AUTO_INCREMENT,';
		$ddl .= ' type      varchar(255) NOT NULL,';
		$ddl .= ' base_rev  int(10) unsigned NOT NULL,';
		$ddl .= ' head_rev  int(10) unsigned NOT NULL,';
		$ddl .= ' work_xml  longtext,';
		$ddl .= ' merge_rev int(10) unsigned NOT NULL,';
		$ddl .= ' user_id   int(10) NOT NULL,';
		$ddl .= ' time      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,';
		$ddl .= ' PRIMARY KEY `id` (id),';
		$ddl .= ' KEY `type` (type),';
		$ddl .= ' KEY `rev` (merge_rev),';
		$ddl .= ' KEY `user` (user_id),';
		$ddl .= ' KEY `time` (time)';
		$ddl .= ') ENGINE=InnoDB  DEFAULT CHARSET=utf8';
		$this->db->exec($ddl);
	}
	/**
	 * データベースに保存用テーブルを作成
	 */
	private function createSaveTable() {
		$ddl  = 'CREATE TABLE IF NOT EXISTS '.BOK_TARGET_DB_SAVE.' (';
		$ddl .= ' user_id  int(10) NOT NULL, ';
		$ddl .= ' title    varchar(255) NOT NULL, ';
		$ddl .= ' base_rev int(10) unsigned NOT NULL, ';
		$ddl .= ' bok_xml  text, ';
		$ddl .= ' comment  text, ';
		$ddl .= ' time     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, ';
		$ddl .= ' PRIMARY KEY `save_name` (user_id,title),';
		$ddl .= ' KEY `rev`  (base_rev),';
		$ddl .= ' KEY `time` (time)';
		$ddl .= ') ENGINE=InnoDB  DEFAULT CHARSET=utf8';
		$this->db->exec($ddl);
	}
	
}
?>
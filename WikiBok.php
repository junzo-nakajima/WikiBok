<?php
/**
 * Special Page
 * @addtogroup wikiboksystem
 * @author Aoyama Univ.
 */
if(!defined('MEDIAWIKI')) {
	die('This file is an Extension to the MediaWiki software and cannot be use standalone.');
}
$dir = dirname(__FILE__);
$wgExtensionCredits['WikiBok'][] = array(
	'path' => __FILE__,
	'name' => 'WikiBokSystem',
	'author' => array( 'Aoyama Univ', '...' ),
	'url' => '',
	'version' => '0.9',
);
//Ajaxの利用をONにする
if(!$wgUseAjax) {
	$wgUseAjax = true;
}
$wgAutoloadClasses['WikiBokJs'] = "$dir/WikiBok.js.php";
$wgAutoloadClasses['BokEditor'] = "$dir/BokEditor.page.php";
$wgAutoloadClasses['DescriptionEditor'] = "$dir/DescriptionEditor.page.php";
$wgExtensionMessagesFiles['WikiBok'] = "$dir/WikiBok.i18n.php";
$wgExtensionMessagesFiles['WikiBokAlias'] = "$dir/WikiBok.alias.php";

$wgExtensionFunctions[] = 'efWikiBokSetup';
//SpecialPage登録
$wgSpecialPages['BokEditor'] = "BokEditor";
$wgSpecialPages['DescriptionEditor'] = "DescriptionEditor";
//ページ一覧で表示される分類
$wgSpecialPageGroups['BokEditor'] = "wikiboksystem";
$wgSpecialPageGroups['DescriptionEditor'] = "wikiboksystem";

efWikiBokInitNamespace();

/**
 * Namespaceの設定
 *  - wgExtensionFunctionsで実行した場合、通常ページで設定が反映されない
 */
function efWikiBokInitNamespace() {
	global	$wikibokgNamespaceIndex,
			$wgExtraNamespaces, 
			$wgNamespaceAliases, 
			$wgNamespacesWithSubpages, 
			$smwgNamespacesWithSemanticLinks;
	if ( !isset( $wikibokgNamespaceIndex ) ) {
		//100から取りたいが,smwNamespaceで使用されている場合+20(結構適当...)
		$wikibokgNamespaceIndex = 120;
	}
	define('NS_SPECIAL_BOK',				$wikibokgNamespaceIndex + 0);
	define('NS_SPECIAL_BOK_TALK',			$wikibokgNamespaceIndex + 1);
	define('NS_SPECIAL_DESCRIPTION',		$wikibokgNamespaceIndex + 2);
	define('NS_SPECIAL_DESCRIPTION_TALK',	$wikibokgNamespaceIndex + 3);
	$myNamespace = array();
	//名前空間
	$myNamespace[NS_SPECIAL_BOK             ] = "Node";
	$myNamespace[NS_SPECIAL_BOK_TALK        ] = "Node_talk";
	$myNamespace[NS_SPECIAL_DESCRIPTION     ] = "Document";
	$myNamespace[NS_SPECIAL_DESCRIPTION_TALK] = "Document_talk";
	// Register namespace identifiers
	if ( !is_array( $wgExtraNamespaces ) ) {
		$wgExtraNamespaces = array();
	}
	$wgExtraNamespaces = $wgExtraNamespaces + $myNamespace;
	//名前空間の別名
	$myNamespaceAliase = array(
		$myNamespace[NS_SPECIAL_BOK] => NS_SPECIAL_BOK,
		$myNamespace[NS_SPECIAL_DESCRIPTION] => NS_SPECIAL_DESCRIPTION
	);
	$wgNamespaceAliases = $wgNamespaceAliases + $myNamespaceAliase;
	if ( !is_array( $smwgNamespacesWithSemanticLinks ) ) {
		$smwgNamespacesWithSemanticLinks = array();
	}
	//Use SemanticLink Setting
	$smwgNamespacesWithSemanticLinks = $smwgNamespacesWithSemanticLinks + array(
		NS_SPECIAL_BOK              => true,
		NS_SPECIAL_BOK_TALK         => true,
		NS_SPECIAL_DESCRIPTION      => true,
		NS_SPECIAL_DESCRIPTION_TALK => true
	);
}
function efWikiBokSetup() {
	global	$wikibokgNamespaceIndex,
			$wgExtraNamespaces, 
			$wgNamespaceAliases, 
			$wgHooks;

	wfLoadExtensionMessages('WikiBok');
	//Ajax設定
	efWikiBokAjaxRequest();

	$wgHooks['PersonalUrls'][] = 'efWikiBokPersonalUrls';
	$wgHooks['AjaxAddScript'][] = 'efWikiBokInsertScript';
	$wgHooks['BeforePageDisplay'][] = 'efWikiBokInsertHtml';
	return;
}
/**
 * Javascript応答用関数の設定
 */
function efWikiBokAjaxRequest() {
	global $wgAjaxExportList;
	$wgAjaxExportList[] = "WikiBokJs::svg2pdf";
	$wgAjaxExportList[] = "WikiBokJs::download_pdf";
	$wgAjaxExportList[] = "WikiBokJs::sendTweet";
	$wgAjaxExportList[] = "WikiBokJs::changePass";
	$wgAjaxExportList[] = "WikiBokJs::createUserAccount";
	$wgAjaxExportList[] = "WikiBokJs::saveBokSvgData";

	$wgAjaxExportList[] = "WikiBokJs::getBokRevision";
	$wgAjaxExportList[] = "WikiBokJs::getBokJson";
	$wgAjaxExportList[] = "WikiBokJs::treeway_merge";
	$wgAjaxExportList[] = "WikiBokJs::insertMergeXml";

	$wgAjaxExportList[] = "WikiBokJs::createNodeRequest";
	$wgAjaxExportList[] = "WikiBokJs::createNodeToRequest";
	$wgAjaxExportList[] = "WikiBokJs::deleteEdgeRequest";
	$wgAjaxExportList[] = "WikiBokJs::deleteNodeRequest";
	$wgAjaxExportList[] = "WikiBokJs::deleteNodeOnlyRequest";
	$wgAjaxExportList[] = "WikiBokJs::moveNodeRequest";
	$wgAjaxExportList[] = "WikiBokJs::clearEditHistory";

	$wgAjaxExportList[] = "WikiBokJs::getDescriptionList";
	$wgAjaxExportList[] = "WikiBokJs::getDescriptionJson";
	$wgAjaxExportList[] = "WikiBokJs::getSMWLinks";

	$wgAjaxExportList[] = "WikiBokJs::viewData";
	$wgAjaxExportList[] = "WikiBokJs::createNodeFromLinks";
	return;
}
/**
 * 個別リンクに特別ページ[BokEditor/DescriptionEditor]へのリンク追加
 */
function efWikiBokPersonalUrls(&$personal_urls,$title) {
	global $wgScriptPath,$wgTitle;

	$personal_urls['BokEditor'] = array(
		'text' => wfMsg('bokeditor'),
		'href' => $wgScriptPath.'/index.php/特別:BokEditor',
		'class' => "wikibok-linkcaution",
		'active' => true,
		'selected' => ($wgTitle->mTextform == 'BokEditor')
	);
	$personal_urls['DescriptionEditor'] = array(
		'text' => wfMsg('descriptioneditor'),
		'href' => $wgScriptPath.'/index.php/特別:Descriptioneditor',
		'class' => "wikibok-linkcaution",
		'active' => true,
		'selected' => ($wgTitle->mTextform == 'DescriptionEditor')
	);

	return true;
}
/**
 * WikiBok画面で必要な追加スクリプトを設定する
 * @global type $wgScriptPath
 * @param OutputPage $out   Wiki出力-インスタンス
 * @return type 
 */
function efWikiBokInsertScript(OutputPage $out) {
	require_once("config/setting.php");
	global $wgScriptPath,$wgUser,$wgRequest,$wgTitle,$wgExtraNamespaces,$wgGroupPermissions;

	//iPad関連
	// - 表示領域設定[初期:1.0倍/pinchによるズームをON]
	$out->addMeta('viewport','width=device-width, initial-scale=1.0,user-scalable=yes');
	// - フルスクリーンモードをON(ホーム画面[webクリップ]に追加した場合のみ有効?)
	$out->addMeta('apple-mobile-web-app-capable','yes');
	// - フルスクリーンモードでのステータスバー表示設定
	$out->addMeta('apple-mobile-web-app-status-bar-style','black');
	$out->addLink(array('rel'=>'apple-touch-icon','href'=>"{$wgScriptPath}/extensions/WikiBok/image/icon_wikibok.png"));
	//javascript用メッセージを設定
	$out->addInlineScript(
		'var meta_message = '.json_encode(unserialize(wfMsg('wikibok-message'))).','."\n".
		 '   wgLogin = '.json_encode($wgUser->isLoggedIn()).','."\n".
		 '   wgEdit = '.json_encode($wgUser->isAllowed('edit')).','."\n".
		//名前空間を設定
		 '   wgExtraNamespace = '.json_encode($wgExtraNamespaces).','."\n".
		//ページ番号(名前空間と連動する)
		 '   wgDebug = '.$wgTitle->getNamespace().','."\n".
		 '   wgNsBok = '.NS_SPECIAL_BOK.','."\n".
		 '   wgNsDesc = '.NS_SPECIAL_DESCRIPTION.';'
	);
	//スタイルシートの追加
	$out->addStyle("{$wgScriptPath}/extensions/WikiBok/css/WikiBok.css");
	$out->addStyle("{$wgScriptPath}/extensions/WikiBok/css/WikiBok.svg.css");
	$out->addStyle("{$wgScriptPath}/extensions/WikiBok/css/colorpicker/colorpicker.css");
	$out->addStyle("{$wgScriptPath}/extensions/WikiBok/css/custom-theme/jquery-ui-1.8.16.custom.css");
	$out->addStyle("{$wgScriptPath}/extensions/WikiBok/css/tablesorter/style.css");
	//Javascript追加
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery-1.7.2.min.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery-ui-1.8.16.custom.min.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery.selection-min.js");
	//$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/d3.v2.min.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/d3.v2.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/scroll_event.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery.scrollTo.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery.tablesorter.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery.tablesorter.pager.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/colorpicker.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery.wikibok.functions.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery.bok.svg.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/jquery.description.svg.js");
	$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/WikiBok.js");
	//個別ページ用Script
	$sPage = $wgTitle->mTextform;
	if(efCheckPageTitle($sPage)) {
		$out->addScriptFile("{$wgScriptPath}/extensions/WikiBok/js/{$sPage}.js");
	}
	return true;
}
/**
 * 表示中のページ名称が指定したものかをチェック
 */
function efCheckPageTitle($title,$pages="") {
	$res = false;
	if(!is_array($pages)) {
		$pages = array('BokEditor','DescriptionEditor');
	}
	foreach($pages as $sAddPageTitle) {
		if($title == $sAddPageTitle) {
			$res = true;
			break;
		}
	}
	return $res;
}
/**
 * ログイン用HTMLタグ
 */
function efWikiBokPopupLogin($id="wikibok-popupLogin") {
	$txt  = '<dl class="hide popup" id="'.$id.'">';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-user').'</dt>';
	$txt .= '<dd><input type="text" name="" class="user"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-pass').'</dt>';
	$txt .= '<dd><input type="password" name="" class="pass"></dd>';
	$txt .= '<dt class="adduser">'.wfMsg('wikibok-popupLogin-newuser').'</dt>';
	$txt .= '<dd class="adduser">'.wfMsg('wikibok-popupLogin-here')   .'</dd>';
	$txt .= '<dt class="remine">' .wfMsg('wikibok-popupLogin-remine') .'</dt>';
	$txt .= '<dd class="remine">' .wfMsg('wikibok-popupLogin-here')   .'</dd>';
	$txt .= '</dl>';
	return "{$txt}\n";
}
/**
 * パスワード変更用HTMLタグ
 */
function efWikiBokChangepass($id="wikibok-changepass") {
	$txt  = '<dl class="hide popup" id="'.$id.'">';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-user').'</dt>';
	$txt .= '<dd><input type="text" name="" class="user"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-pass').'</dt>';
	$txt .= '<dd><input type="password" name="" class="oldpass"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-new-pass').'</dt>';
	$txt .= '<dd><input type="password" name="" class="pass"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-repass').'</dt>';
	$txt .= '<dd><input type="password" name="" class="repass"></dd>';
	$txt .= '</dl>';
	return "{$txt}\n";
}
/**
 * アカウント作成用HTMLタグ
 */
function efWikiBokCreateAccount($id="wikibok-createaccount") {
	$txt  = '<dl class="hide popup" id="'.$id.'">';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-user').'</dt>';
	$txt .= '<dd><input type="text" name="" class="user"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-pass').'</dt>';
	$txt .= '<dd><input type="password" name="" class="pass"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-repass').'</dt>';
	$txt .= '<dd><input type="password" name="" class="repass"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-mail').'</dt>';
	$txt .= '<dd><input type="text" name="" class="email"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-popupLogin-realname').'</dt>';
	$txt .= '<dd><input type="text" name="" class="realname"></dd>';
	$txt .= '</dl>';
	return "{$txt}\n";
}
/**
 * 検索パネル用HTMLタグ
 */
function efWikiBokSearch($id="wikibok-search") {
	$txt  = '<div id="'.$id.'">';
	$txt .= '<div>';
	$txt .= '<span class="icon32 commit">'.wfMsg('wikibok-search-start').'</span>';
	$txt .= '<span class="icon32 up">'.wfMsg('wikibok-search-prev').'</span>';
	$txt .= '<span class="icon32 down">'.wfMsg('wikibok-search-next').'</span>';
	$txt .= '</div>';
	$txt .= '<div>';
	$txt .= '<input type="text" value="" class="text" title="'.wfMsg('wikibok-search-text').'">';
	$txt .= '</div>';
	$txt .= '<div>';
	$txt .= '<span class="icon32 list">'.wfMsg('wikibok-search-list').'</span>';
	$txt .= '<span class="icon32 in_window">'.wfMsg('wikibok-dialog-reset').'</span>';
	$txt .= '</div>';
	$txt .= '</div>';
	return "{$txt}\n";
}
/**
 * 検索結果用HTMLタグ
 */
function efWikiBokSearchResult($id="wikibok-searchresult") {
	require_once("config/setting.php");
	$txt  = '<div class="hide popup" id="'.$id.'">';
	//強調表示用の色
	$txt .= '<div class="wikibok-color">color';
	$txt .= '<div class="colorSelect"><div></div></div>';
	$txt .= '<div class="colorPicker"></div>';
	$txt .= '<input type="text" readonly="readonly" class="color" style="display:none">';
	$txt .= '</div>';
	$txt .= '<div class="clear"></div>';
	//ページャー
	$txt .= '<div class="pager">';
	$txt .= '<span onclick="" class="icon16 first">'.wfMsg('wikibok-pager-first').'</span>';
	$txt .= '<span onclick="" class="icon16 prev">'.wfMsg('wikibok-pager-prev').'</span>';
	$txt .= '<input type="text" readonly="readonly" class="pagedisplay" title="'.wfMsg('wikibok-pager-view').'"/>';
	$txt .= '<span class=""></span>';
	$txt .= '<span onclick="" class="icon16 next">'.wfMsg('wikibok-pager-next').'</span>';
	$txt .= '<span onclick="" class="icon16 last">'.wfMsg('wikibok-pager-last').'</span>';
	$txt .= '<select class="pagesize">';
	for($i=0;;$i++) {
		if($i == 0) {
			$page = WIKIBOK_SEARCH_PAGE_MIN;
		}
		else {
			$page = WIKIBOK_SEARCH_PAGE_PLUS * $i;
		}
		if($page > WIKIBOK_SEARCH_PAGE_MAX) {
			break;
		}
		$txt .= '<option value="'.$page.'">'.$page.'</option>';
		
	}
	$txt .= '</select>';
	$txt .= '</div>';
	//一覧
	$txt .= '<table class="tablesorter wikibok-searchresult">';
	$txt .= '<thead>';
	$txt .= '<tr><th>'.wfMsg('wikibok-searchresult-title').'</th></tr>';
	$txt .= '</thead>';
	$txt .= '<tbody class="txt"></tbody>';
	$txt .= '</table>';
	$txt .= '</div>';
	return "{$txt}\n";
}
/**
 * 記事参照用HTMLタグ
 */
function efWikiBokDescriptionView($id="wikibok-description-view") {
	$txt  = '<div id="'.$id.'" class="popup hide">';
	$txt .= '<div class="caution"></div>';
	$txt .= '<dl>';
	$txt .= '<dt>'.wfMsg('wikibok-article-title').'</dt>';
	$txt .= '<dd class="title"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-article-summary').'</dt>';
	$txt .= '<dd class="wikibok-text"></dd>';
	$txt .= '</dl>';
	$txt .= '</div>';
	return "{$txt}\n";
}
/**
 * 記事編集用HTMLタグ
 */
function efWikiBokDescriptionEdit($id="wikibok-description-edit") {
	global $wgContLang,$wgUseTeX;
	$txt  = '<div id="'.$id.'" class="popup hide">';
	$txt .= '<div class="caution"></div>';
	$txt .= '<!-- Article Conflict View -->';
	$txt .= '<div class="wikibok-description-diff hide">';
	$txt .= '<dl class="head"><dt>'.wfMsg('wikibok-conflict-head').'</dt>';
	$txt .= '<dd class="txt"></dd></dl>';
	$txt .= '<dl class="work"><dt>'.wfMsg('wikibok-conflict-work').'</dt>';
	$txt .= '<dd class="txt"></dd></dl>';
	$txt .= '<hr class="clear">';
	$txt .= '</div>';
	$txt .= '<dl>';
	$txt .= '<dt>'.wfMsg('wikibok-article-title').'</dt>';
	$txt .= '<dd><input type="text" class="title"></dd>';
	$txt .= '<dt>'.wfMsg('wikibok-article-summary').'</dt>';
	//編集用ツールチップ
	$txt .= '<dd class="wikibok-descriptioneditor-tooltip">';
	$txt .= '<span class="inIcon bold" sample="'.wfMsg('bold_sample').'">'.wfMsg('bold_tip').'</span>';
	$txt .= '<span class="inIcon italic" sample="'.wfMsg('italic_sample').'">'.wfMsg('italic_tip').'</span>';
	$txt .= '<span class="inIcon normal_link" sample="'.wfMsg('link_sample').'">'.wfMsg('link_tip').'</span>';
	$txt .= '<span class="inIcon smw_link" sample="'.wfMsg('wikibok-smwlink_sample').'">'.wfMsg('wikibok-smwlink_tip').'</span>';
	$txt .= '<span class="inIcon world_link" sample="'.wfMsg('extlink_sample').'">'.wfMsg('extlink_tip').'</span>';
	$txt .= '<span class="inIcon file popup" nsn="'.$wgContLang->getNsText(NS_FILE).'" ns="'.NS_FILE.'" sample="'.wfMsg('image_sample').'">'.wfMsg('image_tip').'</span>';
	$txt .= '<span class="inIcon media popup" nsn="'.$wgContLang->getNsText(NS_MEDIA).'" ns="'.NS_MEDIA.'" sample="'.wfMsg('media_sample').'">'.wfMsg('media_tip').'</span>';
	if($wgUseTeX) {
		//TeXが有効な場合のみ追加...
		$txt .= '<span class="inIcon math" sample="'.wfMsg('math_sample').'">'.wfMsg('math_tip').'</span>';
	}
	$txt .= '<span class="inIcon nowiki" sample="'.wfMsg('nowiki_sample').'">'.wfMsg('nowiki_tip').'</span>';
	$txt .= '<span class="inIcon signed" sample="">'.wfMsg('sig_tip').'</span>';
	$txt .= '<span class="inIcon hr" sample="">'.wfMsg('hr_tip').'</span>';
	$txt .= '</dd>';
	$txt .= '<dd><textarea class="wikibok-text" rows="5"></textarea></dd>';
	$txt .= '</dl>';
	$txt .= '</div>';
	return "{$txt}\n";
}
/**
 * 使用するHTMLタグの挿入
 */
function efWikiBokInsertHtml(OutputPage $out,Skin $skin) {
	require_once("config/setting.php");
	global $wgTitle,$wgLanguageCode;

	//すべてのページで使用するタグ
	$out->addHTML("<div class=\"hide\" id=\"wikibok-loading\"></div>");
	$out->addHTML(efWikiBokPopupLogin());
	$out->addHTML(efWikiBokChangepass());
	$out->addHTML(efWikiBokCreateAccount());

	if(efCheckPageTitle($wgTitle->mTextform)) {
		//BOKツリー表示パネル
		$out->addHTML('<div id="result"></div>');
		$out->addHTML(efWikiBokSearch());
		$out->addHTML(efWikiBokSearchResult());
		$out->addHTML(efWikiBokDescriptionView());
		$out->addHTML(efWikiBokDescriptionEdit());
	}
	return true;
}

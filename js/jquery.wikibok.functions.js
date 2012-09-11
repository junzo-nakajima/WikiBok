;(function($) {
  $.extend({
    wikibok : new function() {
      var
        description_pages = {},
        desc_xhr = false;
      /**
       * RGBオブジェクト形式の値をHEX(6桁)形式に変換
       *  - 「#」は付加しない
       */
      function RGBToHex(rgb) {
        //各値を16進数に変換
        var hex = [
          rgb.r.toString(16),
          rgb.g.toString(16),
          rgb.b.toString(16)
        ];
        //各値が2桁以下になった場合、0を補完
        $.each(hex, function(i,v) {
          if(v.length == 1) {
            hex[i] = '0'+v;
          }
        });
        //文字列として返却
        return hex.join('');
      }
      /**
       * HEX形式の値をRGBオブジェクト形式に変換
       */
      function ToRGB(hex) {
        //「#」を含む場合、取り除いて処理
        var shex = (hex.indexOf('#') > -1) ? hex.substring(1) : hex,
          hex = parseInt(shex,16),
          res = {r:0,g:0,b:0};
        switch(shex.length) {
          case 6:
            res.r = (hex & 0xFF0000) >> 16;
            res.g = (hex & 0x00FF00) >> 8;
            res.b = (hex & 0x0000FF);
            break;
          case 3:
            //3桁の場合、各値をF=>FFとして計算(W3C規格?)
            res.r = ((hex & 0xF00) >> 8) * 0x11;
            res.g = ((hex & 0x0F0) >> 4) * 0x11;
            res.b = (hex & 0x00F) * 0x11;
            break;
        }
        return res;
      }
      /**
       * 配列内の重複を削除
       *  - $.uniqueとは異なり、返却する配列をソートしない
       * @param m 対象配列
       */
      function array_unique(m) {
        var _chk = {},
          _res = [];
        if(arguments.length == 1) {
          for(var i=0;i<m.length;i++) {
            //チェック済み確認
            var k = m[i];
            if(!(k in _chk)) {
              _chk[k] = true;
              _res.push(k);
            }
          }
        }
        return _res;
      }
      /**
       * 言語対応データより文言を取得
       * @param ... 複数指定可、PHP配列キーを順番に記述
       */
      function wfMsg() {
        //パラメータ指定がない場合、空文字を返す
        if(arguments.length < 1) return '';
        //複数キー指定対応(配列ではなくした...)
        var arg = Array.prototype.slice.call(arguments),
          mes = meta_message,
          res = '';
        if($.isArray(arg)) {
          for(var i=0;i<arg.length;i++) {
            var _res = mes[arg[i]];
            //配列キーを順に確認
            if(_res == undefined) {
              break;
            }
            else if((typeof _res == 'string') ||
                (typeof _res == 'number')) {
              mes = _res;
              break;
            }
            else {
              mes = _res;
            }
          }
        }
        else {
          //たぶんこっちには来ない...
          var _res = mes[arg];
          if((typeof _res == 'string') ||
            (typeof _res == 'number')) {
            mes = _res;
          }
          else {
            mes = 'Array['+arg+']';
          }
        }
        return mes;
      }
      /**
       * ページ名称の取得(省略時は表示中のページ名称を返す)
       * @param a [省略可]ページ名称
       */
      function getPageName(a) {
        var page = (arguments.length < 1) ? wgPageName : a;
        return page.slice(page.indexOf(':')+1);
      }
      /**
       * 名前空間の取得
       *   - 省略時は表示中のページの名前空間を返す
       *     デフォルトはDescription用の名前空間
       * @param a [省略可]検査対象のページ
       * @param b [省略可]ページ名に名前空間を含まない場合の戻り値
       */
      function getPageNamespace(a,b) {
        var ns = (arguments.length < 2) ? wgExtraNamespace[wgNsDesc] : b;
          page = (arguments.length < 1) ? wgPageName : a;
          idx =  page.indexOf(':');
        return (idx < 0) ? ns : a.slice(0,idx);
      }
      /**
       * 対象オブジェクトまでスクロールする
       */
      function wfScroll(a,b,c) {
        //スクロール設定省略
        c = (arguments.length < 3) ? b  : c;
        b = (arguments,length < 3) ? {} : b;
        //ローカル変数
        var w = $(window),
          f = $.extend({},{
            before : undefined,
            after : undefined,
            time : 0
          },c),
          d = ($.isFunction(f.after)) ? $.extend({},{onAfter : f.after},b) : $.extend({},{},b);
        if($.isFunction(f.before)) {
          //スクロール前処理を実行
          setTimeout(f.before.call(a),f.time);
        }
        switch(typeof a) {
          case 'object':
            if(a.length > 0) {
              $.scrollTo(a[0],d);
            }
            else {
              $(window).scrollTo('50%');
            }
            break;
          case 'string':
            var t = $(a);
            if(t.length > 0) {
              $.scrollTo(t,d);
            }
            else {
              $(window).scrollTo('50%');
            }
            break;
          default:
            break;
        }
      }
      /**
       * 種別+項目名の2つでユニークIDを作成
       * @param a 種別指定
       * @param b 項目別指定
       */
      function uniqueID(a,b) {
        var ids = $.wikibok.getTypeID(a,b),
          id = '';
        if(ids.length < 1) {
          //新規ID作成
          var ids = $.wikibok.getTypeID(a),
            id = _unique(a+'_');
          //取得済みデータとして登録
          ids.push({name : b,id : id});
          $.data($('body').get(0),a,ids);
        }
        else {
          id = ids[0].id;
        }
        return id;
      }
      /**
       * 種別[+項目名]を指定してユニークIDを取得
       * @param a 種別指定
       * @param b 項目別指定(省略可)
       */
      function getTypeID(a,b) {
        var elem = $('body').get(0),
          ids = $.data(elem,a);
        return (arguments.length < 2)
        ? ((ids == undefined) ? [] : ids)
        : ((ids == undefined) ? [] : ids.filter(function(d){return (d.name == b);}));
      }
      /**
       * WikiBOKで統一したダイアログボックスを作成
       */
      function exDialog(a,b,c,d) {
        var baseId = $.wikibok.uniqueID('dialog',a),
          copy = (arguments.length < 4 || d == undefined) ? false : true,
          id = (copy) ? $.wikibok.uniqueID(baseId,d) : baseId,
          mid = '#'+id,
          opt = $.extend({},{
            title : a,
            buttons : [
              {
                text : $.wikibok.wfMsg('common','button_close','text'),
                title: $.wikibok.wfMsg('common','button_close','title'),
                class: $.wikibok.wfMsg('common','button_close','class'),
                click: function() {
                  $(this).dialog('close');
                }
              }
            ],
            autoOpen : true,
            position : 'center'
          },c),
          baseCreate = function(e,ui) {
          }
        if($(mid).length == 0) {
          var content = $('<div></div>');
          //デフォルト設定[ID/Class]
          content.attr('id',id)
            .addClass('wikibok-exdialog')
            .addClass(a)
            .toggleClass('hide',true);
          $('body').append(content);
        }
        //ダイアログ作成
        $('body').on('dialogcreate',mid,function(){
          $(this).prev().find('.ui-dialog-titlebar-close').hide();
          //追加コンテンツ
          if(typeof b == 'string') {
            content.html(b);
          }
          else if(typeof b == 'object'){
            //複数の場合cloneしないと移動する...
            var _add = (copy) ? b.clone(true) : b;
            content.append(_add);
            _add.show();
          }
        });
        $(mid).dialog(opt);
        if($(mid).dialog('isOpen')) {
          $(mid).dialog('moveToTop');
        }
        else {
          if(opt.autoOpen) {
            $(mid).dialog('open');
          }
        }
        return mid;
      }
      /**
       * WikiBok独自のサーバ処理(CGI)を呼び出す
       */
      function requestCGI() {
        var me = this,
          args = Array.prototype.slice.apply(arguments),
          rs = args.shift() || 'WikiBokJs::dummy',
          rsargs = args.shift() || [],
          sfunc = args.shift() || function(){return true;},
          efunc = args.shift() || function(){},
          argPlus = (arguments.length < 5) ? true : args.shift();
        //最新リビジョン+ユーザIDを自動付加する
        if(argPlus) {
          rsargs = $.merge([0,wgUserName],rsargs);
        }
        //Deferredオブジェクトを返却
        return $.Deferred(function(def) {
          $.ajax({
            type : 'POST',
            dataType : 'JSON',
            url : wgServer+wgScriptPath+'/index.php',
            data : {
              action : 'ajax',
              rs : rs,
              rsargs : rsargs,
            },
            success : function() {
              //成功時実行関数でTRUEを返す場合のみOK
              if(sfunc.apply(me,arguments)) {
                def.resolve.apply({},arguments);
              }
              else {
                def.reject.apply({},arguments);
              }
            },
            error : function() {
              efunc.apply(me,arguments);
              def.reject.apply({},arguments);
            },
            async : true,
            cache : false,
          });
        }).promise();
      }
      /**
       * MediawikiAPIへのリクエストを送信する
       */
      function requestAPI() {
        var me = this,
          args = Array.prototype.slice.apply(arguments),
        //第４引数に同期通信をONにするパラメータを設定
          async = (args.length < 4) ? true : args.pop(),
          rs = args.shift() || {},
          postData = $.extend({},{
            format : 'json'
          },rs),
          sfunc = args.shift() || function(){return true;},
          efunc = args.shift() || function(){};

        //同期通信の場合ajaxStart/ajaxStopイベントが発生しないため...
        if(async == false) {
          $().trigger('ajaxStart');
        }
        //再帰呼出しが多いため、Deferredオブジェクトを返さない
        // -> 再帰呼出しごとに終了関数が呼ばれてしまうため予期した動きになり難い
        return $.ajax({
          type : 'POST',
          dataType : 'JSON',
          url : wgServer+wgScriptPath+'/api.php',
          data : postData,
          success : function(dat,stat,xhr) {
            sfunc.apply(me,arguments);
            if(async == false) {
              $().trigger('ajaxStop');
            }
          },
          error : function(xhr,stat,et) {
            efunc.apply(me,arguments);
            if(async == false) {
              $().trigger('ajaxStop');
            }
          },
          async : async,
          cache : false,
        });
      }
      /**
       * 記事一覧を取得する
       * @param next 一覧取得開始名称[省略時:先頭から]
       * @param one  単一記事のみ取得する/しない[省略時:複数取得]
       */
      function _description(next,one) {
        var
          def = this,
          _one = (arguments.length < 2) ? false : one,
          _pdata = $.extend({},{
            action : 'query',
            generator : 'allpages',
            gapnamespace : wgNsDesc,
            prop : 'info',
            gaplimit : ((_one) ? 500 : 1)
          },{
            gapfrom : ((arguments.length < 1) ? '' : next)
          });
        //リクエスト
        requestAPI(
          _pdata,
          function(dat,stat,xhr) {
            if(dat['query'] != undefined && dat['query']['pages'] != undefined) {
              var
                pages = dat['query']['pages'],
                page,
                _name,
                _namespace;
              for(var k in pages) {
                page = pages[k];
                _name = getPageName(page.title);
                _namespace = getPageNamespace(page.title);
                description_pages[_name] = {
                  name : _name,
                  namespace : _namespace,
                  size : page.length,
                  id : k,
                  title : page.title,
                  ns : page.ns
                }
              }
            }
            //記事1件のみの場合、再帰する必要なし
            if(_one) {
              def.resolve(page);
            }
            else {
              if(dat['query-continue'] == undefined) {
                //続きがないので終了
                def.resolve(description_pages);
              }
              else {
                //続きがあるので再帰呼出し
                _description.call(def,dat['query-continue']['allpages']['gapfrom']);
              }
            }
          },
          function(xhr,stat,err) {
            def.reject();
          }
        )
      }
      /**
       * サーバから全件取得
       */
      function loadDescriptionPages() {
        var def = $.Deferred();
        _description.call(def);
        return def.promise();
      }
      /**
       * 記事一覧を名称で検索
       * @param a 記事名称
       * @param b 部分一致検索ON/OFF [省略時ON]
       * @param c 空白記事かどうかをチェックする(空白でないもののみ返す)/しない [省略時OFF]
       */
      function findDescriptionPage(a,b,c) {
        var
          inp = a.replace(/\W/g,'\\$&'),
          reg = new RegExp((arguments.length < 2) ? inp : ((b) ? ('^'+inp+'$') : inp)),
          pagesize = (arguments.length < 3) ? 0 : ((c == true) ? 1 : 0);
        return $.map(description_pages,function(d) {
          if(d.name.match(reg)) {
            if(d.size >= pagesize) {return d;}
          }
        });
      }
      /**
       * 記事1件分を取得
       * @param a 記事名称
       * @param prop 取得内容(Wiki-APIに準拠)
          text           - Gives the parsed text of the wikitext
          langlinks      - Gives the language links in the parsed wikitext
          categories     - Gives the categories in the parsed wikitext
          categorieshtml - Gives the HTML version of the categories
          languageshtml  - Gives the HTML version of the language links
          links          - Gives the internal links in the parsed wikitext
          templates      - Gives the templates in the parsed wikitext
          images         - Gives the images in the parsed wikitext
          externallinks  - Gives the external links in the parsed wikitext
          sections       - Gives the sections in the parsed wikitext
          revid          - Adds the revision ID of the parsed page
          displaytitle   - Adds the title of the parsed wikitext
          headitems      - Gives items to put in the <head> of the page
          headhtml       - Gives parsed <head> of the page
          iwlinks        - Gives interwiki links in the parsed wikitext
          wikitext       - Gives the original wikitext that was parsed
       */
      function getDescriptionPage(_page,_prop) {
        var
          def = $.Deferred(),
          //デフォルトに追加
          prop = array_unique($.merge([
            'text',
            'displaytitle',
          ],_prop)),
          _pdata = $.extend({},{
            action : 'parse',
            prop : prop.join('|'),
          //タイトル＋テキストを指定して仮登録
          //  title : '',
          //  text : '',
          //もしくは、「page」に登録済みページ名を指定してデータ取得...のどちらかができる(?)
            page : getPageNamespace(_page)+':'+getPageName(_page),
          });
        requestAPI(
          _pdata,
          function(dat,stat,xhr) {
            if(dat['parse']['text']['*'] != undefined && dat['parse']['displaytitle'] != undefined) {
              //記事の取得に成功
              def.resolve(dat);
            }
          },
          function(xhr,stat,err) {
            def.reject();
          }
        );
        return def.promise();
      }
      /**
       * URLパラメータを取得
       * @param a パラメータ名称[省略時:全パラメータ]/[#:ネーム]
       */
      function getUrlVars(a) {
        var
          _href = window.location.href,
          _query = _href.slice(_href.indexOf('?')+1,_href.indexOf('#')),
          hashes = _query.split('&'),
          names = _href.slice(_href.indexOf('#') + 1),
          vars = [];
        //省略時
        if(arguments.length<1||a==undefined||a=='') {
          for(var i=0;i<hashes.length;i++) {
            var
              hash = hashes[i].split('='),
              dhash1 = decodeURI(hash[0]),
              dhash2 = (hash[1] == undefined) ? true : decodeURI(hash[1]);
            vars.push({
              name : dhash1,
              value: dhash2
            });
          }
        }
        else {
          //名称
          if(a == '#') {
            vars = names;
          }
          else {
            for(var i=0;i<hashes.length;i++) {
              var
                hash = hashes[i].split('='),
                dhash1 = decodeURI(hash[0]),
                dhash2 = (hash[1] == undefined) ? true : decodeURI(hash[1]);
              //指定パラメータのみ
              if(dhash1 == a) {
                vars = {
                  name : dhash1,
                  value: dhash2
                };
              }
            }
          }
        }
        return vars;
      }
      return {
        array_unique : array_unique,
        wfMsg : wfMsg,
        wfScroll : wfScroll,
        uniqueID : uniqueID,
        getTypeID : getTypeID,
        exDialog : exDialog,
        requestCGI : requestCGI,
        requestAPI : requestAPI,
        getPageName : getPageName,
        getPageNamespace : getPageNamespace,
        loadDescriptionPages : loadDescriptionPages,
        findDescriptionPage : findDescriptionPage,
        getDescriptionPage : getDescriptionPage,
        getUrlVars : getUrlVars,
      };
    },
    revision : (function(){
      var me = {},
        isReady = false,
        isRequest = false;
      function _get() {
        var dat = {
            base : 0,
            head : 0,
            user : 0
          },
          read = null;
        if(isReady) {
          read = $.data(me.get(0),'revision');
          if(read !== undefined) {
            dat = read;
          }
        }
        return (arguments.length < 1) ? dat : dat[arguments[0]];
      }
      function _request() {
        if(isReady) {
          //2重POST禁止
          if(isRequest === false) {
            isRequest = true;
            $.wikibok.requestCGI(
              'WikiBokJs::getBokRevision',
              [wgUserName],
              function(dat,stat,xhr) {
                $.data(me.get(0),'revision',dat);
                $.data(me.get(0),'base',dat.base);
                $.data(me.get(0),'head',dat.head);
                _setRev(dat.user);
                _editTree(dat.edit);
              },
              function(xhr,stat,err) {},
              false
            ).always(
              function() {
                isRequest = false;
              }
            );
          }
        }
      }
      function _setRev() {
        var user = _getRev();
        if(isReady) {
          if(arguments.length > 0) {
            user = arguments[0];
            dat = _get();
            dat.user = user;
            $.data(me.get(0),'revision',dat);
          }
          $.data(me.get(0),'user',user);
        }
      }
      function _getRev() {
        return (isReady) ? $.data(me.get(0),'user') || 0 : 0;
      }
      function _editTree() {
        var res = false;
        if(isReady) {
          if(arguments.length > 0) {
            $.data(me.get(0),'edit',arguments[0]);
          }
          res = $.data(me.get(0),'edit');
        }
        return res;
      }
      function _updateHTML() {
        var dat = _get(),
          _user = parseInt(dat.user) - parseInt(dat.base);
        if(isReady) {
          me.find('.base').html(dat.base);
          me.find('.head').html(dat.head);
          me.find('.edit').html(_user);
        }
      }
      function construct() {
        if(arguments.length > 0) {
          me = $(arguments[0]);
          isReady = true;
        }
        else {
          me = $(this);
          isReady = (me.length > 0);
        }
        _sync();
      }
      function _sync() {
        //リクエスト+ajax通信終了時にHTML書換え
        _request();
        me.one('ajaxStop', _updateHTML);
      }
      return {
        construct : construct,
        request : _request,
        setRev : _setRev,
        getRev : _getRev,
        editTree : _editTree,
        updateHTML : _updateHTML,
        sync : _sync
      }
    }()),
    timer : (function(){
      var
        tList = [],
        time = 100,
        timer;
      function setIntervalTime(a) {
        if(timer) {
          stop();
        }
        time = a;
      }
      function add(f,first) {
        var isAdded = false;
        if(typeof f == 'function') {
          for(var i=0;i<tList.length;i++) {
            if(tList[i].func === f) {
              isAdded = true;
              break;
            }
          }
          if(!isAdded) {
            tList.push({func : f,_tm : false});
            if(arguments.length > 1|| first) {
              run(i);
            }
          }
        }
        return (!isAdded);
      }
      function remove(f) {
        var isRemove = false;
        if(typeof f == 'function') {
          for(var i=0;tList.length;i++) {
            if(tList[i].func === f) {
              tList.splice(i,1);
              isRemove = true;
              break;
            }
          }
        }
        if(tList.length < 1) {
          stop();
        }
        return isRemove;
      }
      function start() {
        stop();
        if(tList.length > 0) {
          run();
        }
      }
      function stop() {
        if(timer) {
          clearTimeout(timer);
        }
      }
      function run(n) {
        function _run(j) {
          if(tList[j]._tm == false) {
            tList[j]._tm = true;
            $.when(
              tList[j].func()
            ).done(function() {
              tList[j]._tm = false;
            });
          }
        }
        if(arguments.length < 1) {
          for(var i=0;i<tList.length;i++) _run(i);
        }
        else {
          if(n < tList.length) _run(n);
        }
        timer = setTimeout(arguments.callee,time);
      }
      return {
        add : add,
        remove : remove,
        start : start,
        stop : stop,
        setIntervalTime : setIntervalTime
      }
    }())
  });

  $.fn.extend({
    /**
     * 同一行内に複数画像アイコンを設定する
     *   - 画像ファイルの指定は個別にCSSで設定
     * @param a 追加するCSS設定(アイコンのサイズなどを指定)
     * @param b 対象要素配下の内限定してアイコンを設定する場合、セレクタを指定
     */
    lineicon : function(a,b) {
      var icons = (arguments.length < 2) ? $(this) : $(this).find(b),
        set = $.extend({},{},a),
        _wrap = $('<span></span>').css(set);
      icons.addClass('wikibok_icon').each(function() {
        var tb = $(this).html() || '',
          elem = this;
        _wrap.attr({'title':tb});
        $(this).wrap(_wrap);
      });
      icons.css(set);
      icons.filter(':not(:last)').after(
        $('<span></span>').css({'margin-left':(parseInt(set.width) + 2)+'px'})
      );
      icons.filter(':last').after(
        $('<span></span>').css({'margin-right':(parseInt(set.width) + 2)+'px'})
      );
      return this;
    },
    /**
     * 対象要素の表示位置を固定
     * @param a ハッシュ{
     *    position : 基準位置[LT:左上/LB:左下/RT:右上/RB:右下]
     *    x : 基準位置からの距離(横方向:px)
     *    y : 基準位置からの距離(横方向:px)
     * }
     * @param b [TRUE]一時的に隠す/[FALSE]隠さない
     */
    setPosition : function(a,b){
      var opt = $.extend({},{
        position : 'lt',
        x : 20,
        y : 20,
        slideSpeed : 'fast'
      },a);
      return $.each(this,function() {
        if($(this).length < 1) return false;
        var timer = false,
          elem = $(this),
          _resize = [],
          touch = ('ontouched' in document),
          //要素の位置変更用関数
          change = function() {
            var set = arguments[0] || {},
              touch = arguments[1] || false,
              x = (document.documentElement.scrollLeft || document.body.scrollLeft),
              y = (document.documentElement.scrollTop || document.body.scrollTop),
              h = (touch)
                ? (window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight || 0) 
                : $(window).height(),
              w = (touch)
                ? (window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || 0) 
                : $(window).width();
            switch(set.position.toLowerCase()) {
              case 'lt':
                x += set.x;
                y += set.y;
                break;
              case 'rt':
                x += w - set.x - $(this).width();
                y += set.y;
                break;
              case 'lb':
                x += set.x;
                y += h - set.y - $(this).height();
                break;
              case 'rb':
                x += w - set.x - $(this).width();
                y += h - set.y - $(this).height();
                break;
            }
            $(this).css({
              top : y,
              left : x
            });
          },
          //イベント登録用関数
          setEvent = function() {
            var set = arguments[0] || {},
              touch = arguments[1] || false,
              elem = this;
            //リサイズ時イベント要素に追加
            _resize.push({e:elem,s:set,t:touch});
            //スクロール監視に追加(一部スクロール追随しない場合を考慮)
            WINDOW_APP.util.scrollMonitor.add(function() {
              change.call(elem,set,touch);
            });
            change.call(elem,set,touch);
          };
        //隠し要素に変更
        if(b) {
          var tmp = $('<div></div>').addClass('setPositionBtn'),
            set = $.extend({},opt,{x:5,y:20});
          //ボタンを追加
          tmp.insertBefore(elem).bind('click',function() {
            //表示/非表示の切り替えイベント
            elem.slideToggle(opt.slideSpeed,function() {
              tmp.toggleClass('active');
            })
          });
          //初期状態は非表示とする
          elem.slideUp(opt.slideSpeed);
          setEvent.call(tmp,set,touch);
          //ボタンと被らないようにX軸を移動
          opt.x += 32;
        }
        //設定時実施
        elem.addClass('setPosition');
        setEvent.call(elem,opt,touch);
        //リサイズ時にイベントを実施
        $(window).bind('resize',function() {
          //タイマ動作をキャンセル(リサイズ中は再描画しない...)
          if(timer !== false) {
            clearTimeout(timer);
          }
          timer = setTimeout(function() {
            for(var i=0;i<_resize.length;i++) {
              var e = _resize[i].e,
                s = _resize[i].s,
                t = _resize[i].t;
              change.call(e,s,t);
            }
          },500);
        });
        //入力中はスクロール追随しない
        elem.find('input:text').bind({
          focus : function() {WINDOW_APP.util.scrollMonitor.stop();},
          blur : function() {WINDOW_APP.util.scrollMonitor.start();}
        });
        return elem;
      });
    },
    /**
     * データ入力時にキーボードによる要素間移動を設定
     *   - 決定キーを押下することでキーボードのみで確定まで行えるように...
     * @param フォーカス遷移用のデータを設定[下記のハッシュ形式の配列]
     *    { class: 現在フォーカス中のクラス
     *      next : Enterで移動する要素
     *      prev : Shift+Enterで移動する要素  }
     */
    setInterruptKeydown : function() {
      var me = $(this),
        args = Array.prototype.slice.apply(arguments),
        move = (args.length < 1) ? [{
            class : 'close',
            next : null,
            prev : null
          }] : args[0];
      return $.each(this,function() {
        var active = function(a) {
            //フォーカス切替
            var t = me.find(_selecter(a));
            if(t.length < 1) return false;
            return t.focus();
          };
        //初期フォーカス調整
        active(move[0].class);
        //フォーカスの遷移データを設定してイベントを定義
        me.on('keypress','input:text,input:password,textarea',{move : move},function(ev) {
          var t = $(ev.target),
            m = ev.data.move,
            //入力要素に対応したデータを取得
            _act = m.filter(function(d){ return t.hasClass(d.class);});
          if(_act.length < 1) {
            return false;
          }
          else {
            _act = _act[0];
          }
          if($(t).is('input')) {
            //1行入力のみはEnter/Shift+Enterで次/前へ移動
            if(ev.which == 13) {
              if(ev.shiftKey) {
                active(_act.prev);
              }
              else {
                active(_act.next);
              }
            }
          }
          else if($(t).is('textarea')) {
            //複数行入力はShift+Enterで次へ移動
            if(ev.which == 13) {
              if(ev.shiftKey) {
                active(_act.next);
              }
            }
          }
        });
        return this;
      });
    },
    /**
     * @param _opt 自動補完に設定するパラメータ
     * @param _dat $.Defferdオブジェクトで指定し、
     *             resolve|reject関数の引数に設定用データを返す
     */
    setAutoComplete : function(_opt,_dat,_ext,_top) {
      var
        alldata = false,
        top = (arguments.length < 4) ? true : _top,
        //自動補完パラメータ
        opt = $.extend({},{
          //デフォルト:先頭から一致を抽出
          source : function(q,s) {
            var
              _find,
              inp = q.term.replace(/\W/g,'\\$&'),
              reg = (top) ? new RegExp('^'+inp,'mi') : new RegExp(inp,'mi'),
              sug = alldata;
            //データなし
            if(sug == undefined || !$.isArray(sug) || sug.length < 1) {
              return false;
            }
            _find = sug.filter(function(d) {
              return d.name.match(reg);
            });
            s($.map(_find,function(d) {
              return {
                label : d.name,
                value : d.name,
              };
            }));
          }
        },_opt),
        //データ設定[デフォルト:記事名称一覧]
        dat = (arguments.length < 2 || !$.isFunction(_dat)) ?
         ((_dat == false || _dat == undefined) ? {} : 
          $.wikibok.requestCGI(
            'WikiBokJs::getDescriptionList',
            [],
            function(dat,stat,xhr) {return true;},
            function(xhr,stat,err) {return true;}
          ).promise().done(function(res) {
            alldata = res;
          }))
        : _dat().promise().done(function(res) {
          alldata = res;
        }),
        ext = (arguments.length < 3 || _ext == undefined || _ext == null) ? false : $.extend({},{
          emptyItem : $.wikibok.wfMsg('wikibok-description','listview','empty'),
          view : $('<div/>'),
          time : false,
          get : function(ev,ui) {
            var p = this;
            if(ext.time !== false) {
              clearTimeout(ext.time);
            }
            ext.time = setTimeout(function() {
              $.wikibok.getDescriptionPage(ui.item.value,[])
              .done(function(d) {
                ext.view.html(($(d['parse']['text']['*']).html() == null) ? ext.emptyItem : d['parse']['text']['*']);
              })
              .fail(function() {
                ext.view.html(emptyItem);
              })
              .always(function() {
                //記事詳細の表示位置設定
                ext.view.show();
                ext.view.position({
                  my : 'left bottom',
                  at : 'right bottom',
                  of : p,
                  collision : 'fit',
                });
              });
            },100)
          }
        },_ext);
      return $.each(this,function() {
        var
          elem = $(this).autocomplete(opt),
          widget = elem.autocomplete('widget');
        if(ext !== false) {
          widget.after(ext.view);
          ext.view.addClass('wikibok ui-autocomplete description').hide();
          ext.view.css('z-index', parseInt(widget.css('z-index')) + 1);
          //追加イベント定義
          elem.on('autocompletefocus.wikibok',widget,function(ev,ui) {
            if(ext !== false) {
              ext.get.apply(widget,arguments);
            }
          }).on('autocompleteselect.wikibok',widget,function(ev,ui) {
          }).on('autocompleteclose.wikibok',widget,function(ev,ui) {
            if(ext !== false) {
              ext.view.hide();
            }
          });
        }
        return this;
      });
    },
    revision : $.revision.construct
  });
//外部から参照されない関数はextendに記述しない
  /**
   * ランダム文字列の作成
   */
  var
  _unique = function(pre,len,rep) {
      var count = 0,
        maxLen = len || 5,
        maxCount = rep || 10,
        _id = '';
      do {
        var id = pre || '';
        //設定文字数分繰り返す
        for(var i=0;i<maxLen;i++) {
          //a～zの範囲のみ使用する[a-z]26文字/[a文字コード]97(0x61)
          id += String.fromCharCode((Math.random() * 26) + 97);
        }
        //DOM要素のIDを利用して重複チェック...
        _id = '#'+id;
        if($(_id).length == 0) {
          break;
        }
        count++;
      }
      //一定回数繰り返したら、取得できなくても抜ける
      while(count < maxCount);
      //取得できない場合、条件変更(文字数を+1)して再試行
      return ($(_id).length == 0) ? id : arguments.callee(pre,len+1,rep);
    },
  _selecter = function() {
      var args = Array.prototype.slice.apply(arguments),
        a = args.shift(),
        b = args.shift() || '.';
      return (a == undefined) ? false : ((a.indexOf(b) < 0) ? b+a : a);
      
    };
})(jQuery);

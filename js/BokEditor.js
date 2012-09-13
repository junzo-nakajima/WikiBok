jQuery(function($) {
  var
    dElem = $('body').get(0),
    bok,
    tid,
    pid,
    rid,
    mode = 'normal',
    mode_mes,
    svg = $('#result').bok({
      success : function(r) {
      },
      polygonClick : function() {
        $.revision.editTree(true);
      },
      textClick : textClick,
      pathClick : function(d) {
        alert(d.source.name);
      },
      node : {
        class : 'empty',
        func : function(d){
          return (($.wikibok.findDescriptionPage(d.name,false,true)).length < 1);
        }
      }
    });
//  $.extend({mySvg : svg});
  function moveNode(a,b) {
    $.wikibok.requestCGI(
      'WikiBokJs::moveNodeRequest',
      [a,b],
      function(dat,stat,xhr) {
        return true;
      },
      function(xhr,stat,err) {
        return false;
      }
    )
    .done(function(dat) {
      if(dat.res == false) {
        //失敗
      }
      else {
        svg.moveNode();
      }
    })
      
      
  }
  function textClick(d) {
    var
      tmp,
      open = false,
      tid = d.name;
    switch(mode) {
      //後から選択した方が親
      case 'parent':
        alert("親:"+tid+"\n子:"+pid);
        svg.addNode(pid,tid);
        mode = 'normal';
        break;
      //後から選択した方が子
      case 'childs':
        alert("親:"+pid+"\n子:"+tid);
        svg.addNode(tid,pid);
        mode = 'normal';
        break;
      //BOK上に表示しないノードを複数選択
      case 'represent':
        var disp;
        //除外
        if(rid.tid == undefined && tid != pid) {
          rid[tid] = {
            description : pid,
            smwlinkto : tid
          }
        }
        else {
          //追加済み
          
        }
        disp = $.map(rid,function(d) {
          return d.smwlinkto;
        });
        alert("主\n"+pid+"\n従\n"+disp.join("\n"));
        break;
      case 'normal':
      default:
        pid = '';
        tmp = '<dl class="content"><dt>'+$.wikibok.wfMsg('wikibok-contextmenu','itemgroup','view')+'</dt>'
            + '<dd class="command description-view">'+$.wikibok.wfMsg('wikibok-contextmenu','description','view')+'</dd>';
        if(wgLogin && wgEdit && wgAction != 'load') {
        tmp = tmp
            + '<dt>'+$.wikibok.wfMsg('wikibok-contextmenu','itemgroup','edit')+'</dt>'
            + '<dd class="command bokeditor-edge-delete">'+$.wikibok.wfMsg('wikibok-contextmenu','bok','edge-delete')+'</dd>'
            + '<dd class="command bokeditor-node-delete">'+$.wikibok.wfMsg('wikibok-contextmenu','bok','node-delete')+'</dd>'
            + '<dd class="command bokeditor-find-parent">'+$.wikibok.wfMsg('wikibok-contextmenu','bok','find-parent')+'</dd>'
            + '<dd class="command bokeditor-find-childs">'+$.wikibok.wfMsg('wikibok-contextmenu','bok','find-childs')+'</dd>'
            + '<dd class="command bokeditor-only-delete">'+$.wikibok.wfMsg('wikibok-contextmenu','bok','only-delete')+'</dd>'
            + '<dd class="command bokeditor-node-create">'+$.wikibok.wfMsg('wikibok-contextmenu','bok','node-create')+'</dd>'
            + '<dt>'+$.wikibok.wfMsg('wikibok-contextmenu','itemgroup','special')+'</dt>'
            + '<dd class="command bokeditor-rename">'+$.wikibok.wfMsg('wikibok-contextmenu','description','rename')+'</dd>'
            + '<dd class="command bokeditor-represent">'+$.wikibok.wfMsg('wikibok-contextmenu','description','represent')+'</dd>';
        }
        open = true;
        break;
    }
    tmp = tmp+'</dl>';
    if(open) {
      $.wikibok.exDialog(
        $.wikibok.wfMsg('wikibok-contextmenu','title'),
        '',
        {
          open : function() {
            var
              dialog = this;
            $(dialog).html(tmp);
            $(dialog)
              .on('click','.bokeditor-find-parent',function(a,b) {
                pid = tid;
                mode = 'parent';
              })
              .on('click','.bokeditor-find-childs' ,function(a,b) {
                pid = tid;
                mode = 'childs';
              })
              .on('click','.bokeditor-only-delete',function(a,b) {
                alert('のみ削除:'+tid);
              })
              .on('click','.bokeditor-node-delete',function(a,b) {
                alert('以下削除:'+tid);
              })
              .on('click','.bokeditor-edge-delete',function(a,b) {
                alert('紐削除:'+tid);
              })
              .on('click','.bokeditor-node-create',function(a,b) {
                createNewNode(tid);
                
              })
              .on('click','.bokeditor-represent',function(a,b) {
                pid = tid;
                rid = {};
                mode = 'represent';
              })
              .on('click','.command',function(a,b) {
                $(dialog).off('click');
                $(dialog).dialog('close');
              });
          }
        }
      );
    }
  }
  function createNewNode(a) {
    var
      _id = $.wikibok.uniqueID('dialog',$.wikibok.wfMsg('wikibok-new-element','title')),
      inp = '<dt>'+$.wikibok.wfMsg('wikibok-new-element','bok','headline2')+'</dt>'
          + '<dd><input type="text" name="name" class="name"/></dd>',
      tmp = '<dl>'
          + ((arguments.length < 1)
          ? inp
          : '<dt>'+$.wikibok.wfMsg('wikibok-new-element','bok','headline1')+'</dt><dd>'+a+'</dd>' + inp)
          + '</dl>',
      addTo =(arguments.length < 1) ? '' : a;
    if($('#'+_id).dialog('isOpen')) {
      $('#'+_id).dialog('close');
    }
    $.wikibok.exDialog(
      $.wikibok.wfMsg('wikibok-new-element','title'),
      '',
      {
        create : function() {
        },
        open : function() {
          $(this).html(tmp);
          $(this).dialog('widget').setInterruptKeydown([{
            class : 'name',
            next : $.wikibok.wfMsg('wikibok-new-element','bok','button','class'),
            prev : $.wikibok.wfMsg('common','button_close','class')
          }]);
          $(this).find('input.name').setAutoComplete({
            position : {
              my : 'left bottom',
              at : 'right bottom',
            },
          },{},{});
        },
        buttons : [{
          text : $.wikibok.wfMsg('wikibok-new-element','bok','button','text'),
          class: $.wikibok.wfMsg('wikibok-new-element','bok','button','class'),
          title: $.wikibok.wfMsg('wikibok-new-element','bok','button','title'),
          click: function(){
            var
              newName = $(this).find('input.name').val();
            if(newName == '') {
              alert("ちゃんと入力しろ!\n失敗");
            }
            else {
              svg.addNode(newName,addTo);
            }
          }
        },{
          text : $.wikibok.wfMsg('common','button_close','text'),
          class: $.wikibok.wfMsg('common','button_close','class'),
          title: $.wikibok.wfMsg('common','button_close','title'),
          click: function(){
            $(this).dialog('close');
          }
        }]
      }
    );
  }
  $('#wikibok-search')
    //位置固定/アイコン化
    .setPosition({position : 'lb'},true)
    //検索用イベント定義
    .setSearch(svg,{
      find : '.commit',
      next : '.down',
      prev : '.up',
      list : '.list',
      text : '.text'
    });
  //編集ツールコマンド
  $('#wikibok-edit')
    .on('click','.checked',function(ev) {
      if('ontouched' in document) {
        //キャンセル確認ダイアログ表示
      }
      else {
        //checkCancel();
      }
    })
    .on('click','.new',function(ev) {
      //編集権限なし Or [表示形式:データ読み出し]の場合
      if(!wgEdit || wgAction == 'load') {
        return true;
      }
      createNewNode();
    })
    .on('click','.commit',function(ev) {
    })
    .on('click','.save_as',function(ev) {
    })
    .on('click','.undo',function(ev) {
    })
    .on('click','.redo',function(ev) {
    });

  //アクション選択
  switch(wgAction) {
    case 'load':
      //保存済みデータの表示
      break;
    default:
      //その他(通常表示)
      $.when(
        $.wikibok.loadDescriptionPages(),
        $.wikibok.requestCGI(
          'WikiBokJs::getBokJson',
          [],
          function(dat,stat,xhr) {
            svg.load(dat.xml);
            return true;
          },
          function(xhr,stat,err) {
            return false;
          }
        )
      )
      .done(
        function(d) {
          //定期更新の予約(記事情報取得)
          $.timer.add(svg.update,true);
          $.timer.add($.wikibok.loadDescriptionPages);
          //ハッシュタグまたはデフォルト値を強調
          var h = $.wikibok.getUrlVars('#') || $.wikibok.wfMsg('defaultFocus');
          if(h != undefined && h != '') {
            var aNode = $('*[data="'+h+'"]');
            if(aNode.length < 1) {
              $(window).scrollTo('50%');
            }
            else {
              $.wikibok.wfScroll(aNode,{
                after : function() {
                  $(this).addClass('act');
                }
              });
            }
          }
        }
      );
      break;
  }
});

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>簡易HTMLエディタ（複数行検索対応）</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">

<style>
  body { 
    font-family: sans-serif; 
    padding: 20px; 
    background-color: #f9f9f9;
  }
  .toolbar { 
    margin-bottom: 10px; 
    padding: 15px; 
    background-color: #e0e0e0; 
    border-radius: 5px; 
  }
  .toolbar-group { 
    margin-bottom: 10px; 
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
  }
  .toolbar-group:last-child {
    margin-bottom: 0;
  }
  .toolbar button { 
    padding: 6px 10px; 
    border: 1px solid #ccc;
    border-radius: 3px;
    cursor: pointer;
    background-color: #fff;
    white-space: nowrap;
  }
  .toolbar button:hover {
    background-color: #eee;
  }
  
  /* =========================================
     変更：検索ボックスを複数行対応（textarea）にする設定
     ========================================= */
  .search-textarea {
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 3px;
    width: 220px;
    height: 40px; /* 最初から少し高さを確保 */
    font-family: monospace; /* コードが見やすいフォント */
    font-size: 13px;
    resize: vertical; /* マウスで縦に広げられるようにする */
    white-space: pre-wrap;
  }

  .search-results-info {
    font-size: 14px;
    font-weight: bold;
    color: #0056b3;
    margin-left: 10px;
  }

  .CodeMirror {
    border: 1px solid #ccc;
    border-radius: 3px;
    font-family: monospace;
    font-size: 15px;
  }
  .highlight-all {
    background-color: #ffeb3b;
    color: #000;
  }
  .CodeMirror-selected {
    background-color: #007bff !important;
    color: #ffffff !important;
  }
  .CodeMirror-focused .CodeMirror-selected {
    background-color: #007bff !important;
  }
</style>
</head>
<body>

<h2>簡易HTMLエディタ</h2>

<div class="toolbar">
  <div class="toolbar-group">
    <strong>基本操作:</strong>
    <button onclick="copyCode()">📋 コピー</button>
    <button onclick="downloadCode()">💾 ダウンロード</button>
    <button onclick="undoCode()">↩️ 戻る</button>
    <button onclick="redoCode()">↪️ 進む</button>
  </div>

  <div class="toolbar-group">
    <strong>候補コード挿入:</strong>
    <button onclick="insertSnippet('<!DOCTYPE html>\n<html lang=\'ja\'>\n<head>\n<meta charset=\'UTF-8\'>\n<title>ドキュメント</title>\n</head>\n<body>\n\n</body>\n</html>')">HTML基本構造</button>
    <button onclick="insertSnippet('<div>\n  \n</div>')">&lt;div&gt;</button>
    <button onclick="insertSnippet('<p></p>')">&lt;p&gt;</button>
    <button onclick="insertSnippet('<a href=\"#\">リンク</a>')">&lt;a&gt;</button>
    <button onclick="insertSnippet('<img src=\"\" alt=\"画像\">')">&lt;img&gt;</button>
  </div>
  
  <div class="toolbar-group">
    <strong>検索・置換:</strong>
    <textarea id="searchInput" class="search-textarea" placeholder="検索するコード&#13;&#10;(改行・複数行OK)"></textarea>
    <textarea id="replaceInput" class="search-textarea" placeholder="置換するコード&#13;&#10;(改行・複数行OK)"></textarea>
    
    <button onclick="searchText()">検索</button>
    <button onclick="replaceText()">置換</button>
    <button onclick="replaceAllText()">すべて置換</button>
    
    <div id="searchResults" class="search-results-info"></div>
  </div>
</div>

<textarea id="editor"></textarea>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/search/searchcursor.min.js"></script>

<script>
  const editorTextarea = document.getElementById('editor');
  const cm = CodeMirror.fromTextArea(editorTextarea, {
    lineNumbers: true,
    mode: {name: "xml", htmlMode: true},
    indentUnit: 2,
    tabSize: 2
  });
  cm.setSize("100%", "500px");

  function copyCode() {
    const code = cm.getValue();
    navigator.clipboard.writeText(code).then(() => {
      alert("コードをコピーしました！");
    }).catch(err => alert("コピーに失敗しました。"));
  }

  function downloadCode() {
    const code = cm.getValue();
    if (!code) {
      alert("保存するコードがありません。");
      return;
    }
    const blob = new Blob([code], { type: "text/html" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "index.html";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function undoCode() { cm.undo(); }
  function redoCode() { cm.redo(); }

  function insertSnippet(snippet) {
    cm.replaceSelection(snippet);
    cm.focus();
  }

  let currentMarks = [];
  let lastSearchStr = "";

  function clearMarks() {
    currentMarks.forEach(mark => mark.clear());
    currentMarks = [];
  }

  function highlightAll(searchStr) {
    clearMarks();
    const resultsDiv = document.getElementById('searchResults');
    
    if (!searchStr) {
      resultsDiv.innerHTML = "";
      return;
    }
    
    let localCursor = cm.getSearchCursor(searchStr, null, {caseFold: true});
    let count = 0;
    let lines = new Set();

    while (localCursor.findNext()) {
      let mark = cm.markText(localCursor.from(), localCursor.to(), {className: "highlight-all"});
      currentMarks.push(mark);
      count++;
      lines.add(localCursor.from().line + 1);
    }

    if (count > 0) {
      const linesArray = Array.from(lines);
      resultsDiv.innerHTML = `💡 ${count}個ヒット （該当行: ${linesArray.join(', ')}行目）`;
    } else {
      resultsDiv.innerHTML = `<span style="color: #dc3545;">⚠️ 見つかりませんでした。スペースや改行のズレがないか確認してください。</span>`;
    }
  }

  function jumpToCursor(cursorFrom) {
    const topPos = cm.charCoords(cursorFrom, "local").top;
    const halfHeight = cm.getWrapperElement().offsetHeight / 2;
    cm.scrollTo(null, topPos - halfHeight);
  }

  function searchText() {
    const searchStr = document.getElementById('searchInput').value;
    
    if (!searchStr) {
      clearMarks();
      lastSearchStr = "";
      document.getElementById('searchResults').innerHTML = "";
      return;
    }

    if (lastSearchStr !== searchStr) {
      highlightAll(searchStr);
      lastSearchStr = searchStr;
    }

    let cursor = cm.getSearchCursor(searchStr, cm.getCursor("to"), {caseFold: true});

    if (cursor.findNext()) {
      cm.setSelection(cursor.from(), cursor.to());
      jumpToCursor(cursor.from());
    } else {
      cursor = cm.getSearchCursor(searchStr, null, {caseFold: true});
      if(cursor.findNext()) {
        cm.setSelection(cursor.from(), cursor.to());
        jumpToCursor(cursor.from());
      } else {
        clearMarks();
        lastSearchStr = "";
      }
    }
  }

  function replaceText() {
    const searchStr = document.getElementById('searchInput').value;
    const replaceStr = document.getElementById('replaceInput').value;
    if (!searchStr) return;

    if (cm.getSelection().toLowerCase() === searchStr.toLowerCase()) {
      cm.replaceSelection(replaceStr);
      highlightAll(searchStr);
      searchText();
    } else {
      searchText(); 
    }
  }

  function replaceAllText() {
    const searchStr = document.getElementById('searchInput').value;
    const replaceStr = document.getElementById('replaceInput').value;
    const resultsDiv = document.getElementById('searchResults');
    if (!searchStr) return;

    let localCursor = cm.getSearchCursor(searchStr, null, {caseFold: true});
    let count = 0;
    while (localCursor.findNext()) {
      localCursor.replace(replaceStr);
      count++;
    }
    
    if (count > 0) {
      alert(`${count}箇所の置換が完了しました。`);
      clearMarks();
      lastSearchStr = "";
      resultsDiv.innerHTML = "";
    } else {
      alert("置換対象が見つかりませんでした。");
    }
  }
</script>

</body>
</html>

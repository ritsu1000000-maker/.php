<?php
// ログを保存するファイル名
$logfile = 'log.txt';

// --- 書き込み処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    // デフォルト名の設定
    $name = !empty($_POST['name']) ? $_POST['name'] : '名無しさん＠お腹いっぱい。';
    $email = !empty($_POST['email']) ? $_POST['email'] : '';
    $message = $_POST['message'];

    // セキュリティ対策（XSS対策）
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // 改行を <br> タグに変換
    $message = nl2br($message);

    // 日付の取得（曜日付き）
    $week = ['日', '月', '火', '水', '木', '金', '土'];
    $w = $week[date('w')];
    $date = date("Y/m/d($w) H:i:s");

    // IPアドレスから簡易的なIDを生成
    $ip = $_SERVER['REMOTE_ADDR'];
    $id = substr(base64_encode(md5($ip . date('Ymd'))), 0, 8);

    // 保存フォーマット: 名前<>メール<>日付 ID<>本文
    $line = "{$name}<>{$email}<>{$date} ID:{$id}<>{$message}\n";

    // ファイルに追記（排他ロック）
    file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);

    // 二重書き込み（リロード）防止のためにリダイレクト
    header('Location: ' . $_SERVER['SCRIPT_NAME']);
    exit;
}

// --- ログの読み込み処理 ---
$posts = [];
if (file_exists($logfile)) {
    // 空行を無視して配列として読み込む
    $lines = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $posts[] = explode('<>', $line);
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>2ch風掲示板</title>
    <style>
        /* 2ch特有のクラシックなスタイル */
        body {
            background-color: #EFEFEF;
            color: #000000;
            font-family: "ＭＳ Ｐゴシック", "MS PGothic", "Mona", sans-serif;
            padding: 10px;
        }
        h1 {
            font-size: 18px;
            color: #CC0000;
        }
        .post {
            margin-bottom: 1.5em;
        }
        .header {
            font-size: 14px;
        }
        .name {
            color: #008000;
            font-weight: bold;
        }
        .message {
            margin-top: 0.5em;
            margin-left: 2em;
            font-size: 16px;
            line-height: 1.3;
        }
        form {
            margin-top: 3em;
        }
        a {
            color: #0000FF;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>■■ 2chみたいなテスト掲示板 ■■</h1>

    <div class="thread">
        <?php foreach ($posts as $index => $post): ?>
            <?php
            // データの展開
            $resNum = $index + 1;
            $pName = isset($post[0]) ? $post[0] : '';
            $pEmail = isset($post[1]) ? $post[1] : '';
            $pDate = isset($post[2]) ? $post[2] : '';
            $pMsg = isset($post[3]) ? $post[3] : '';
            ?>
            <div class="post">
                <div class="header">
                    <?= $resNum ?> ：<span class="name"><?= !empty($pEmail) ? "<a href=\"mailto:{$pEmail}\">{$pName}</a>" : $pName ?></span>：<?= $pDate ?>
                </div>
                <div class="message">
                    <?= $pMsg ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form action="" method="POST">
        <input type="submit" value="書き込む" name="submit"><br><br>
        名前： <input type="text" name="name" value="" size="19">
        E-mail<font size="1"> (省略可) </font>： <input type="text" name="email" value="" size="19"><br>
        <textarea rows="5" cols="60" name="message" wrap="OFF"></textarea>
    </form>
</body>
</html>

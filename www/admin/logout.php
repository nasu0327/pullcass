<?php
/**
 * pullcass - スーパー管理画面
 * ログアウト
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// セッションを破棄
session_destroy();

// ログインページへリダイレクト
redirect('/admin/login.php');

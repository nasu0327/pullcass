<?php
// スマホプレビューモードとして top_preview.php にリダイレクト
$_GET['mobile'] = '1';
require_once __DIR__ . '/top_preview.php';

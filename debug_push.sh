#!/bin/bash
echo "=== PWD ===" > push_log.txt
pwd >> push_log.txt
echo "\n=== GIT STATUS ===" >> push_log.txt
git status >> push_log.txt 2>&1
echo "\n=== GIT ADD ===" >> push_log.txt
git add www/app/manage/reservation_management/index.php www/app/manage/reservation_management/detail.php www/app/manage/reservation_management/list.php >> push_log.txt 2>&1
echo "\n=== GIT COMMIT ===" >> push_log.txt
git commit -m "予約機能管理のメッセージをJavaScriptアラートに変更" >> push_log.txt 2>&1
echo "\n=== GIT PUSH ===" >> push_log.txt
git push origin main >> push_log.txt 2>&1
echo "\n=== DONE ===" >> push_log.txt

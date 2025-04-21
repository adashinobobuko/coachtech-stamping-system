# アプリケーション名  
打刻システム(coachtech-stamping-system)  
## 環境構築  
[github.com:coachtech-material/laravel-docker-template.git ](https://github.com/coachtech-material/laravel-docker-template)  
laravelのディレクトリ構成はこちらからクローンして参考にさせていただいてます。  
コマンドラインで以下のコマンドを実行してください。（WindowsはUbuntuのインストールが必要です。可能な方はPCを仮想化してから実行してください）  
ターミナルで以下のコマンドを入力してください。  
## メール認証について  
mailhogというツールを使用・導入しています。  
以下のコマンドの説明の途中で導入方法を記載いたします。  
  
```bash
cd coachtech/laravel（対応している仮想環境のディレクトリ）  
git clone git@github.com:coachtech-material/laravel-docker-template.git  
mv laravel-docker-template coachtech-stamping-system  
```
  
その後Githubにてリポジトリを作成しSSHを取得します。  
  
```bash
cd coachtech-stamping-system  
git remote set-url origin git@github.com:adashinobobuko/coachtech-stamping-system.git  
```
  
その後ローカルリポジトリのデータをリモートリポジトリに反映させます。  
```bash  
git add .  
git commit -m "リモートリポジトリの変更"  
git push origin main  
```  
  
次にDockerの設定をします。  
```bash  
docker-compose build  
docker-compose up -d  
```    
  
その次にcomposerのインストールをします。  
```bash
docker-compose exec php bash  
composer install  
```  
  
composerのインストールが済んだら.envファイルの準備をします。  
```bash
cp .env.example .env  exit  
```  
  
docker-compose exec app php artisan migrate  
docker-compose exec app php artisan db:seed  
管理者シーディングについてかく
AdminSeeder

DB_CONNECTION=mysql  
DB_HOST=mysql  
DB_PORT=3306  
DB_DATABASE=laravel_db  
DB_USERNAME=laravel_user  
DB_PASSWORD=laravel_pass  
## 使用技術(実行環境)
Laravel 8.83.8
PHP バージョン変わったので記載
mysql  Ver 15.1 
Docker

## ER図
## URL
開発環境：http://localhost/  
'/'　トップページ（おすすめ商品）　検索の際searchが入る  
 

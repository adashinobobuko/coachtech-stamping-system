# アプリケーション名  
打刻システム(coachtech-stamping-system)  
## 環境構築  
[github.com:coachtech-material/laravel-docker-template.git ](https://github.com/coachtech-material/laravel-docker-template)  
laravelのディレクトリ構成はこちらからクローンして参考にさせていただいてます。  
コマンドラインで以下のコマンドを実行してください。（WindowsはUbuntuのインストールが必要です。可能な方はPCを仮想化してから実行してください）  
## メール認証について  
mailhogというツールを使用・導入しています。  
以下のコマンドの説明の途中で導入方法を記載いたします。  
  
ターミナルで以下のコマンドを入力してください。  
  
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

.envファイルには以下のコードの記載をお願いします。  
(私の方で.env.exampleにあらかじめ記載いたしましたのでこの工程は不要ですが念のためご確認くださいませ。)  
```   
まずはデータベース部分の下３行を書き換えてください。  
DB_CONNECTION=mysql  
DB_HOST=mysql  
DB_PORT=3306  
DB_DATABASE=laravel_db ここから下↓
DB_USERNAME=laravel_user  
DB_PASSWORD=laravel_pass  
```   
  
この時点でkeyの作成をお願い致します。  
.envファイルを作成したら、次にアプリケーションキーを生成します。  
```bash
docker-compose exec php artisan key:generate
```   
これを実行すると.envファイル内に  
  
APP_KEY=base64:xxxxxxxxxxxx==  
（XXXには文字の羅列が入る）  
  
という行が作られるのでこれでこのプロセスは完了です。テスト環境を閲覧できます。  
> **注意:**  
> key:generate は本番環境でも必須ですが、開発環境と本番環境でキーは別にしてください。  

## メール認証について  
上記で触れたメール認証の際のメール送信テストについてです。  
このプロジェクトではMailhogを使用しています。  
Mailhogはローカル環境でメール送信をシミュレートし、ブラウザ上で確認できるツールです。  
以下の手順でセットアップを進めてください。  

### 1.docker-compose.ymlの設定  
クローンしてきたファイルにデフォルトで存在しているdocker-compose.ymlファイルに、MailHog用のサービスを追加します。  
```yaml  
services:
  # すでにあるphpやmysqlの設定...

  mailhog:
    image: mailhog/mailhog
    container_name: mailhog
    ports:
      - "8025:8025" # ←ブラウザ用
      - "1025:1025" # ←SMTP用
```   
ここで  
・8025ポートはブラウザ画面（http://localhost:8025）用  
・1025ポートはアプリからのメール送信用　になります。  
  
### 2. .envファイルの設定  
.envに、Mailhog用のメール設定を追記・変更してください。  
（こちらについても上記同様.env.exampleファイルに記載済みです。）  
```env  
MAIL_MAILER=smtp  
MAIL_HOST=mailhog  
MAIL_PORT=1025  
MAIL_USERNAME=null  
MAIL_PASSWORD=null  
MAIL_ENCRYPTION=null  
MAIL_FROM_ADDRESS=example@example.com  
MAIL_FROM_NAME="Example"  
```   
> **注意:**  
> `MAIL_HOST=mailhog` と記載するのがポイントです。  
> docker-composeで設定したサービス名と一致させる必要があります。  
  
### 3. docker-composeの再起動  
```bash  
docker-compose down  
docker-compose up -d  
```   
  
### 4.Mailhog画面の確認方法  
コンテナ起動後、以下のURLにアクセスしてください。  
http://localhost:8025  
ブラウザ上で送信されたメール一覧が確認できるようになります。  

シーディングについてはユニットテストの説明の時に一緒に記載。
docker-compose exec app php artisan migrate  
docker-compose exec app php artisan db:seed  
管理者シーディングについてかく
AdminSeeder
AttendanceSeeder

## 使用技術(実行環境)
Laravel 8.83.8
PHP バージョン変わったので記載
mysql  Ver 15.1 
Docker

## ER図
## URL
開発環境：http://localhost/  
'/'　トップページ（おすすめ商品）　検索の際searchが入る  
 

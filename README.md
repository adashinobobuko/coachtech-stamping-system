# アプリケーション名
打刻システム(coachtech-stamping-system)
## 環境構築
[github.com:coachtech-material/laravel-docker-template.git ](https://github.com/coachtech-material/laravel-docker-template)   
docker-compose build  
docker-compose up -d  

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
 

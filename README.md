# پلتفرم مدیریت رویدادهای ورزشی (Major Events)

پلتفرم تحت‌وب فارسی و راست‌به‌چپ برای برنامه‌ریزی و مدیریت رویدادهای ورزشی.

## پیش‌نیازها

- PHP 8.3+
- Composer
- Node.js 20+
- Laravel Herd (یا معادل)
- SQLite (تا مرحله RAG)

## راه‌اندازی محلی

```bash
git clone https://github.com/rahneshin/major-events.git
cd major-events
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan serve
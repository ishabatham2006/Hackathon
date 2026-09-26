# StockSense: PHP/MySQL version 

## Included pages/features
- Register, login, logout, category selection
- Food dashboard with **sales overview and stock-level charts** (Chart.js)
- Stock list with quantity add/remove/delete and restock recommendations
- Restocking and billing/history pages
- Clothing inventory and clothing billing pages
- Notifications, support, and basic news/trends pages
- Original CSS files retained under `assets/styles/` with compatibility styling

## XAMPP setup
1. Extract this folder into `C:\xampp\htdocs\StockSense_PHP_NoFlask_Graphs`.
2. Start Apache and MySQL in XAMPP.
3. Import `database.sql` in phpMyAdmin.
4. Check `config.php` and update MySQL credentials as needed.
5. Open `http://localhost/StockSense_PHP_NoFlask_Graphs/register.php` and create an account.

PHP syntax was checked with `php -l`; the project has not been fully runtime-tested against your local MySQL setup.

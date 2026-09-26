# StockSense — PHP/MySQL version (Flask-dependent features excluded)

This is a PHP/MySQL conversion baseline based on the supplied StockSense source. Flask/Python ML API features are intentionally not included in this package.

## Included pages/features
- Register, login, logout, category selection
- Food dashboard with **sales overview and stock-level charts** (Chart.js)
- Stock list with quantity add/remove/delete and restock recommendations
- Restocking and billing/history pages
- Clothing inventory and clothing billing pages
- Notifications, support, and basic news/trends pages
- Original CSS files retained under `assets/styles/` with compatibility styling

## Not included / limitations
- Flask API and its `.pkl` ML classifier / vectorizer are excluded, as requested.
- Flask-dependent ML news classification and any other feature that relies on that API will not run.
- This is not a verified 1:1 reproduction of every original MERN feature. Some external API, background scheduler, trend-analysis, and notification behavior is simplified or absent.
- Charts are implemented on the PHP dashboard and stock page using Chart.js and data from MySQL. A full comparison against every original graph/component has not been completed.

## XAMPP setup
1. Extract this folder into `C:\xampp\htdocs\StockSense_PHP_NoFlask_Graphs`.
2. Start Apache and MySQL in XAMPP.
3. Import `database.sql` in phpMyAdmin.
4. Check `config.php` and update MySQL credentials as needed.
5. Open `http://localhost/StockSense_PHP_NoFlask_Graphs/register.php` and create an account.

PHP syntax was checked with `php -l`; the project has not been fully runtime-tested against your local MySQL setup.

<?php
require_once __DIR__.'/config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
function db():PDO{static $p=null;if(!$p)$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function require_login():void{if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}}
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}
function flash(string $m,string $type='success'):void{$_SESSION['flash']=['m'=>$m,'t'=>$type];}
function show_flash():void{if(isset($_SESSION['flash'])){$f=$_SESSION['flash'];echo '<p class="flash '.e($f['t']).'">'.e($f['m']).'</p>';unset($_SESSION['flash']);}}
function stocks():array{return db()->query('SELECT * FROM stocks ORDER BY name,expiry')->fetchAll();}
function low_stocks():array{return db()->query("SELECT name,unit,SUM(CASE WHEN expiry<CURDATE() THEN 0 ELSE quantity END) totalQuantity FROM stocks GROUP BY name,unit HAVING totalQuantity<25 ORDER BY totalQuantity")->fetchAll();}
function alerts():array{$a=[];foreach(db()->query('SELECT * FROM stocks')->fetchAll() as $s){if($s['expiry']<date('Y-m-d'))$a[]=['message'=>$s['name'].' has expired.','type'=>'expiry'];}foreach(low_stocks() as $s)$a[]=['message'=>'Low stock: '.$s['name'].' ('.$s['totalQuantity'].' '.$s['unit'].').','type'=>'restock'];foreach(db()->query('SELECT * FROM clothing_items')->fetchAll() as $c){$exp=date('Y-m-d',strtotime($c['restock_date'].' +2 months'));if($exp<date('Y-m-d'))$a[]=['message'=>$c['name'].' is outdated. Please replace it.','type'=>'expired'];if((int)$c['quantity_value']<15)$a[]=['message'=>$c['name'].' is low in stock.','type'=>'low'];}return $a;}
function nav():void{echo '<nav class="navbar">'
    .'<a class="navbar-brand" href="inventory_dashboard.php">📦 StockSense</a>'
    .'<div class="navbar-links">'
    .'<a href="inventory_dashboard.php">Dashboard</a>'
    .'<a href="products.php">Products</a>'
    .'<a href="receipts.php">Receipts</a>'
    .'<a href="deliveries.php">Delivery Orders</a>'
    .'<a href="transfers.php">Internal Transfers</a>'
    .'<a href="adjustments.php">Adjustments</a>'
    .'<a href="moves.php">Move History</a>'
    .'<div class="dropdown"><span class="dropdown-toggle">Settings ▾</span><div class="dropdown-menu">'
    .'<a href="warehouses.php">Warehouses</a>'
    .'</div></div>'
    .'<div class="dropdown"><span class="dropdown-toggle">Extra Features ▾</span><div class="dropdown-menu">'
    .'<a href="category.php">Home</a><a href="dashboard.php">Food Dashboard</a><a href="stocks.php">Stocks</a><a href="restock.php">Restock</a><a href="billing.php">Billing</a><a href="clothing.php">Clothing</a><a href="notifications.php">Notifications</a><a href="news.php">News</a><a href="trends.php">Trends</a><a href="support.php">Support</a>'
    .'</div></div>'
    .'</div>'
    .'<div class="dropdown navbar-account"><span class="dropdown-toggle">👤 Account ▾</span><div class="dropdown-menu dropdown-menu-right">'
    .'<a href="profile.php">My Profile</a><a class="logout-button" href="logout.php">Logout</a>'
    .'</div></div>'
    .'</nav>';}
function page_start(string $title):void{echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.e($title).' | StockSense</title><link rel="stylesheet" href="assets/styles/Register.css"><link rel="stylesheet" href="assets/styles/Login.css"><link rel="stylesheet" href="assets/styles/Navbar.css"><link rel="stylesheet" href="assets/styles/Dashboard.css"><link rel="stylesheet" href="assets/styles/Stocks.css"><link rel="stylesheet" href="assets/styles/Restock.css"><link rel="stylesheet" href="assets/styles/Billing.css"><link rel="stylesheet" href="assets/styles/ClothingDashboard.css"><link rel="stylesheet" href="assets/styles/ClothingRestock.css"><link rel="stylesheet" href="assets/styles/ClothingBilling.css"><link rel="stylesheet" href="assets/styles/ClothingBillingHistory.css"><link rel="stylesheet" href="assets/styles/ClothingNotifications.css"><link rel="stylesheet" href="assets/styles/NewsSection.css"><link rel="stylesheet" href="assets/styles/Notifications.css"><link rel="stylesheet" href="assets/styles/Support.css"><link rel="stylesheet" href="assets/styles/CategoryChoice.css"><link rel="stylesheet" href="assets/styles/StockGraph.css"><link rel="stylesheet" href="assets/styles/SalesGraph.css"><link rel="stylesheet" href="assets/styles/ClothingStockGraph.css"><link rel="stylesheet" href="assets/styles/ClothingSalesGraph.css"><link rel="stylesheet" href="assets/styles/compat.css"><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head><body>';}
function page_end():void{echo '<footer>StockSense • Inventory Management</footer></body></html>';}

function get_clothing_alerts(): array {
    $a = [];

    $items = db()->query('SELECT * FROM clothing_items')->fetchAll();

    foreach ($items as $c) {
        $exp = date(
            'Y-m-d',
            strtotime($c['restock_date'] . ' +2 months')
        );

        if ($exp < date('Y-m-d')) {
            $a[] = [
                'message' => $c['name'] . ' is outdated. Please replace it.',
                'type' => 'expired'
            ];
        }

        if ((int)$c['quantity_value'] < 15) {
            $a[] = [
                'message' => $c['name'] . ' is low in stock.',
                'type' => 'low'
            ];
        }
    }

    return $a;
}

/* ===================== StockSense IMS helpers (Problem-Statement features) ===================== */

function warehouses():array{return db()->query('SELECT * FROM warehouses ORDER BY name')->fetchAll();}
function warehouse_name(int $id):string{foreach(warehouses() as $w)if((int)$w['id']===$id)return $w['name'];return '—';}
function categories():array{return db()->query('SELECT * FROM product_categories ORDER BY name')->fetchAll();}
function partners(string $type):array{$q=db()->prepare('SELECT * FROM partners WHERE type=? ORDER BY name');$q->execute([$type]);return $q->fetchAll();}

function get_or_create_partner(string $name,string $type):int{
    $name=trim($name); if($name==='') throw new Exception('Partner name is required.');
    $q=db()->prepare('SELECT id FROM partners WHERE name=? AND type=?');$q->execute([$name,$type]);$id=$q->fetchColumn();
    if($id) return (int)$id;
    db()->prepare('INSERT INTO partners(name,type) VALUES(?,?)')->execute([$name,$type]);
    return (int)db()->lastInsertId();
}

function products_list():array{
    return db()->query('SELECT p.*, c.name AS category_name, COALESCE((SELECT SUM(sq.quantity) FROM stock_quants sq WHERE sq.product_id=p.id),0) AS total_stock FROM products p LEFT JOIN product_categories c ON c.id=p.category_id ORDER BY p.name')->fetchAll();
}

function get_stock(int $product_id,int $warehouse_id):int{
    $q=db()->prepare('SELECT quantity FROM stock_quants WHERE product_id=? AND warehouse_id=?');
    $q->execute([$product_id,$warehouse_id]);
    $v=$q->fetchColumn();
    return $v===false?0:(int)$v;
}

function stock_by_warehouse(int $product_id):array{
    $q=db()->prepare('SELECT sq.*, w.name warehouse_name FROM stock_quants sq JOIN warehouses w ON w.id=sq.warehouse_id WHERE sq.product_id=? ORDER BY w.name');
    $q->execute([$product_id]);
    return $q->fetchAll();
}

function adjust_stock(int $product_id,int $warehouse_id,int $delta):void{
    db()->prepare('INSERT INTO stock_quants(product_id,warehouse_id,quantity) VALUES(?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)')
        ->execute([$product_id,$warehouse_id,$delta]);
}

function next_reference(string $move_type,string $prefix):string{
    $q=db()->prepare('SELECT COUNT(DISTINCT reference) FROM stock_moves WHERE move_type=?');
    $q->execute([$move_type]);
    $n=(int)$q->fetchColumn()+1;
    return $prefix.'-'.str_pad((string)$n,5,'0',STR_PAD_LEFT);
}

function low_stock_products():array{
    $rows=[];
    foreach(products_list() as $p){ if((int)$p['total_stock']<=(int)$p['reorder_min']) $rows[]=$p; }
    return $rows;
}

function pending_doc_count(string $move_type):int{
    $q=db()->prepare("SELECT COUNT(DISTINCT reference) FROM stock_moves WHERE move_type=? AND status NOT IN('done','cancelled')");
    $q->execute([$move_type]);
    return (int)$q->fetchColumn();
}

function ims_kpis():array{
    $totalProducts=(int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $low=0;$out=0;
    foreach(low_stock_products() as $p){ if((int)$p['total_stock']<=0)$out++; else $low++; }
    return [
        'total_products'=>$totalProducts,
        'low_stock'=>$low,
        'out_of_stock'=>$out,
        'pending_receipts'=>pending_doc_count('receipt'),
        'pending_deliveries'=>pending_doc_count('delivery'),
        'transfers_scheduled'=>pending_doc_count('internal'),
    ];
}

// Groups stock_moves lines back into documents (one row per reference) for list views.
function documents(string $move_type,array $filters=[]):array{
    $sql="SELECT reference, status, MIN(created_at) created_at, MAX(validated_at) validated_at,
            MAX(source_warehouse_id) source_warehouse_id, MAX(dest_warehouse_id) dest_warehouse_id,
            MAX(partner_id) partner_id, COUNT(*) line_count, SUM(quantity) total_qty
          FROM stock_moves WHERE move_type=?";
    $params=[$move_type];
    if(!empty($filters['status'])){$sql.=' AND status=?';$params[]=$filters['status'];}
    if(!empty($filters['warehouse'])){$sql.=' AND (source_warehouse_id=? OR dest_warehouse_id=?)';$params[]=$filters['warehouse'];$params[]=$filters['warehouse'];}
    $sql.=' GROUP BY reference, status ORDER BY MIN(created_at) DESC';
    $q=db()->prepare($sql);$q->execute($params);
    return $q->fetchAll();
}

function document_lines(string $reference):array{
    $q=db()->prepare('SELECT sm.*, p.name product_name, p.uom FROM stock_moves sm JOIN products p ON p.id=sm.product_id WHERE sm.reference=? ORDER BY sm.id');
    $q->execute([$reference]);
    return $q->fetchAll();
}

function status_badge(string $status):string{
    $map=['draft'=>'#94a3b8','waiting'=>'#f59e0b','ready'=>'#3b82f6','done'=>'#22c55e','cancelled'=>'#ef4444'];
    $c=$map[$status]??'#94a3b8';
    return '<span class="status-badge" style="background:'.$c.'">'.e(ucfirst($status)).'</span>';
}

/* ---- OTP-based password reset ---- */
function create_otp(int $user_id):string{
    $otp=(string)random_int(100000,999999);
    db()->prepare('INSERT INTO password_resets(user_id,otp,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute([$user_id,$otp]);
    return $otp;
}
function verify_otp(int $user_id,string $otp):bool{
    $q=db()->prepare("SELECT id FROM password_resets WHERE user_id=? AND otp=? AND used=0 AND expires_at>NOW() ORDER BY id DESC LIMIT 1");
    $q->execute([$user_id,$otp]);
    $id=$q->fetchColumn();
    if(!$id) return false;
    db()->prepare('UPDATE password_resets SET used=1 WHERE id=?')->execute([$id]);
    return true;
}
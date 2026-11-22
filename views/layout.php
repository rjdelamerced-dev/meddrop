<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#0e8b78">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e(ucwords(str_replace('_',' ', $page))) ?> · MedDrop</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <?php if(in_array($page,['cart','order','deliveries'],true)): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <?php endif; ?>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<?php if ($user): ?>
<div class="min-h-screen lg:flex">
  <aside class="sidebar hidden lg:flex lg:w-64 lg:flex-col">
    <a href="?page=dashboard" class="brand"><span class="brand-mark">✚</span><span>med<span class="brand-accent">drop</span><small>CARE, DELIVERED.</small></span></a>
    <div class="mt-10 px-4 text-[10px] font-bold uppercase tracking-[.18em] text-slate-400">Workspace</div>
    <nav class="mt-3 flex-1 space-y-1 px-3">
      <?php if($user['role']==='customer'): ?>
        <?= nav_link('shop','Browse medicines','◈') ?><?= nav_link('orders','My orders','▤') ?><?= nav_link('cart','Shopping cart','♧',count($_SESSION['cart']??[])) ?>
      <?php elseif($user['role']==='pharmacist'): ?>
        <?= nav_link('verification','Review queue','◷') ?><?= nav_link('inventory','Inventory','▦') ?>
      <?php else: ?><?= nav_link('deliveries','Delivery board','➤') ?><?php endif; ?>
    </nav>
    <div class="sidebar-help"><div class="help-icon">♡</div><b>Here for your health</b><p>Questions about an order? Our care team is ready to help.</p></div>
    <div class="user-card"><?= avatar_html($user['name'],$user['profile_photo']??null,'avatar') ?><div class="min-w-0 flex-1"><b class="block truncate"><?= e($user['name']) ?></b><span><?= e(ucfirst($user['role'])) ?> account</span></div><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="icon-button" aria-label="Sign out" title="Sign out">↗</button></form></div>
  </aside>
  <div class="min-w-0 flex-1">
    <header class="topbar"><button id="menu-toggle" class="mobile-menu lg:hidden" aria-label="Open menu">☰</button><div><div class="text-xs font-semibold text-slate-400">MedDrop / <span class="text-slate-600"><?= e(ucwords(str_replace('_',' ',$page))) ?></span></div><div class="hidden sm:block text-[11px] text-slate-400">Your neighbourhood pharmacy, online.</div></div><div class="ml-auto flex items-center gap-3"><span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 sm:inline-flex"><i class="status-dot"></i> Pharmacy open</span><a class="cart-top" href="?page=<?= $user['role']==='customer'?'cart':'dashboard' ?>">♡<span><?= count($_SESSION['cart']??[]) ?></span></a><button class="profile-trigger" type="button" data-profile-open aria-label="Profile settings"><?= avatar_html($user['name'],$user['profile_photo']??null,'top-avatar') ?><span class="hidden md:block"><?= e(explode(' ',$user['name'])[0]) ?></span><small>⌄</small></button></div></header>
    <main class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-10 lg:py-9">
      <?php if($flash): ?><div class="flash <?= $flash['type']==='error'?'flash-error':'flash-success' ?>" role="status"><span><?= $flash['type']==='error'?'!':'✓' ?></span><?= e($flash['message']) ?><button onclick="this.parentElement.remove()" aria-label="Dismiss">×</button></div><?php endif; ?>
      <?php $view=__DIR__ . '/' . basename($page) . '.php'; if(is_file($view)) require $view; else require __DIR__.'/not_found.php'; ?>
    </main>
    <footer class="footer">MedDrop <span>·</span> Care, delivered. <span class="ml-auto">Made for healthier days.</span></footer>
  </div>
</div>
<div id="mobile-nav" class="mobile-nav lg:hidden"><div class="mobile-nav-head"><a href="?page=dashboard" class="brand"><span class="brand-mark">✚</span><span>med<span class="brand-accent">drop</span></span></a><button id="menu-close">×</button></div><nav><?php if($user['role']==='customer'): ?><?= nav_link('shop','Browse medicines','◈') ?><?= nav_link('orders','My orders','▤') ?><?= nav_link('cart','Shopping cart','♧') ?><?php elseif($user['role']==='pharmacist'): ?><?= nav_link('verification','Review queue','◷') ?><?= nav_link('inventory','Inventory','▦') ?><?php else: ?><?= nav_link('deliveries','Delivery board','➤') ?><?php endif; ?><form method="post" class="mt-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="nav-link w-full">↗ <span>Sign out</span></button></form></nav></div><div id="nav-scrim" class="nav-scrim lg:hidden"></div>
<?php require __DIR__.'/profile_modal.php'; ?>
<?php else: require __DIR__.'/auth.php'; endif; ?>
<?php if(in_array($page,['cart','order','deliveries'],true)): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script><?php endif; ?>
<script src="assets/app.js"></script>
</body></html>
<?php
function nav_link(string $page,string $label,string $icon,int $badge=0): string {
    $requested=$_GET['page']??'dashboard';
    $homePage=match(current_user()['role']??''){ 'customer'=>'shop','pharmacist'=>'verification','rider'=>'deliveries',default=>'dashboard' };
    $active=$requested===$page || ($requested==='dashboard' && $page===$homePage);
    return '<a class="nav-link'.($active?' active':'').'" href="?page='.$page.'"><span class="nav-icon">'.$icon.'</span><span>'.e($label).'</span>'.($badge?'<small>'.$badge.'</small>':'').'</a>';
}
?>

<?php
/**
 * AbroadWorks Management System
 * 
 * @author ikinciadam@gmail.com
 */

// Define root directory if not already defined
if (!isset($root_dir)) {
    $root_dir = dirname(__DIR__);
}
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo isset($root_path) ? $root_path : ''; ?>index.php" class="brand-link">
        <img src="<?php echo isset($root_path) ? $root_path : ''; ?>assets/images/logo.png" alt="AbroadWorks Management Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">AbroadWorks Management</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                    <img src="<?php
                        // Get user profile image
                        $profile_image = (isset($root_path) ? $root_path : '') . 'assets/images/default-avatar.png';
                        if (isset($_SESSION['user_id'])) {
                            $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $user_profile_image = $stmt->fetchColumn();
                            if (!empty($user_profile_image)) {
                                echo (isset($root_path) ? $root_path : '') . $user_profile_image;
                            } else {
                                echo $profile_image;
                            }
                        } else {
                            echo $profile_image;
                        }
                        ?>" class="img-circle elevation-2" style="width: 20px;" alt="User Image">
            </div>
            <div class="info">
                <a href="<?php echo isset($root_path) ? $root_path : ''; ?>profile.php" class="d-block"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <?php
                // Dinamik menü öğelerini veritabanından çek
                try {
                    // Önce tüm menü öğelerini çek
                    $menu_query = "SELECT * FROM menu_items WHERE is_active = 1 ORDER BY display_order";
                    $menu_stmt = $db->query($menu_query);
                    $menu_items = $menu_stmt->fetchAll();
                    
                    // Menü öğelerini hiyerarşik bir yapıya dönüştür
                    $menu_tree = [];
                    $menu_lookup = [];
                    
                    // Önce bir arama dizisi oluştur
                    foreach ($menu_items as $item) {
                        $menu_lookup[$item['id']] = $item;
                        $menu_lookup[$item['id']]['children'] = [];
                    }
                    
                    // Sonra ağacı oluştur
                    foreach ($menu_items as $item) {
                        if ($item['parent_id'] === null) {
                            $menu_tree[] = &$menu_lookup[$item['id']];
                        } else {
                            if (isset($menu_lookup[$item['parent_id']])) {
                                $menu_lookup[$item['parent_id']]['children'][] = &$menu_lookup[$item['id']];
                            }
                        }
                    }
                    
                    // Menü öğelerini göster
                    function renderMenuItem($item, $root_path = '') {
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $current_url = $_SERVER['REQUEST_URI'];
                        $has_children = !empty($item['children']);
                        $permission = $item['permission'];

                        // İzin kontrolü
                        if ($permission && !has_permission($permission)) {
                            return;
                        }

                        // Menü anahtarını belirle
                        $item_key = strtolower(str_replace(' ', '', $item['name']));

                        // Aktif sayfayı belirle - URL eşleşmesi ile
                        $is_active = false;

                        // Doğrudan URL eşleşmesi kontrol et
                        if (!empty($item['url'])) {
                            $menu_url = '/' . ltrim($item['url'], '/');
                            $is_active = (strpos($current_url, $menu_url) !== false);
                        }

                        // Alt menülerde aktif var mı kontrol et (parent için menu-open)
                        $has_active_child = false;
                        if ($has_children) {
                            foreach ($item['children'] as $child) {
                                if (!empty($child['url'])) {
                                    $child_url = '/' . ltrim($child['url'], '/');
                                    if (strpos($current_url, $child_url) !== false) {
                                        $has_active_child = true;
                                        break;
                                    }
                                }
                            }
                        }

                        // Eski yöntemle de kontrol et (geriye uyumluluk)
                        if (!$is_active && isset($GLOBALS['active_module']) && $GLOBALS['active_module'] === $item_key) {
                            $is_active = true;
                        }
                        
                        // Menü ID'si
                        $menu_id = 'menu_' . $item['id'];

                        // Parent menü açık olmalı mı?
                        $should_open = $has_children && ($is_active || $has_active_child);

                        // Menü öğesini göster
                        echo '<li class="nav-item' . ($should_open ? ' menu-open' : '') . '" id="' . $menu_id . '">';

                        // Menü bağlantısı - parent aktifse veya child aktifse
                        $link_active = $is_active || ($has_children && $has_active_child);
                        echo '<a href="' . ($has_children ? '#' : $root_path . $item['url']) . '" class="nav-link' . ($link_active ? ' active' : '') . '">';
                        echo '<i class="nav-icon fas ' . $item['icon'] . '"></i>';
                        echo '<p>' . htmlspecialchars($item['name']);
                        
                        if ($has_children) {
                            echo '<i class="fas fa-angle-left right"></i>';
                        }
                        
                        echo '</p></a>';
                        
                        // Alt menü öğeleri
                        if ($has_children) {
                            echo '<ul class="nav nav-treeview">';
                            foreach ($item['children'] as $child) {
                                renderMenuItem($child, $root_path);
                            }
                            echo '</ul>';
                        }
                        
                        echo '</li>';
                    }
                    
                    // Tüm ana menü öğelerini göster
                    foreach ($menu_tree as $item) {
                        renderMenuItem($item, isset($root_path) ? $root_path : '');
                    }
                    
                } catch (PDOException $e) {
                    // Tablo henüz oluşturulmamış olabilir, varsayılan menüyü göster
                    ?>
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a href="<?php echo isset($root_path) ? $root_path : ''; ?>index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    
                    <!-- User Profile -->
                    <li class="nav-item">
                        <a href="<?php echo isset($root_path) ? $root_path : ''; ?>profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-user"></i>
                            <p>My Profile</p>
                        </a>
                    </li>
                    
                    <!-- Logout -->
                    <li class="nav-item">
                        <a href="<?php echo isset($root_path) ? $root_path : ''; ?>logout.php" class="nav-link">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>Logout</p>
                        </a>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>

<div class="col-md-10 ms-sm-auto col-lg-10 px-md-2">

<!-- Menu handling is done in main.js -->

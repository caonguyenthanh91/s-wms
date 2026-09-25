<?php
$roleEmojis = [
    'Guest' => '👤',
    'Staff' => '👥',
    'Leader' => '🎯',
    'Manager' => '📊',
    'Admin' => '⚙️',
];

$categoryGrouping = [
    'Cơ bản' => ['inventory', 'dashboard', 'layout'],
    'Vận hành kho' => ['import', 'pallet_receive', 'transfer', 'picking', 'packing', 'pickup', 'inbound', 'outbound', 'change'],
    'In ấn' => ['print', 'print_case', 'print_pallet'],
    'Cấu hình & Quản lý' => ['shelves', 'products', 'data_export', 'wms_import', 'data_import', 'admin'],
    'Bảo trì' => ['system_check'],
    'Đang phát triển' => ['check_box', 'check_inventory', 'check_dashboard', 'packing_ver_2'],
];
?>

<style>
    .menu-card {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 12px;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        min-height: 140px;
        text-decoration: none;
        color: inherit;
    }

    .menu-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 16px rgba(0, 0, 0, 0.12);
        border-color: #3b82f6;
    }

    .menu-card.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }

    .menu-card.restricted {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        opacity: 0.7;
        cursor: not-allowed;
    }

    .menu-card.restricted:hover {
        opacity: 1;
        transform: none;
    }

    .menu-card-icon {
        font-size: 3rem;
        margin-bottom: 0.5rem;
        transition: transform 0.3s ease;
    }

    .menu-card:hover:not(.restricted) .menu-card-icon {
        transform: scale(1.15);
    }

    .menu-card-label {
        font-weight: 600;
        text-align: center;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .menu-card-role {
        font-size: 0.75rem;
        margin-top: 0.5rem;
        padding: 0.25rem 0.5rem;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 4px;
        opacity: 0.8;
    }

    .menu-card.active .menu-card-role {
        background: rgba(255, 255, 255, 0.2);
    }

    .lock-icon {
        position: absolute;
        top: 8px;
        right: 8px;
        font-size: 1.2rem;
        background: rgba(255, 107, 107, 0.9);
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .menu-section {
        margin-bottom: 2.5rem;
    }

    .menu-section-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 3px solid #3b82f6;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 1rem;
    }

    @media (max-width: 640px) {
        .menu-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem;
        }

        .menu-card {
            padding: 1.5rem 0.75rem;
            min-height: 120px;
        }

        .menu-card-icon {
            font-size: 2.5rem;
        }

        .menu-card-label {
            font-size: 0.85rem;
        }
    }

    @media (min-width: 641px) and (max-width: 1024px) {
        .menu-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
    }

    @media (min-width: 1025px) {
        .menu-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        }
    }

    .empty-section {
        padding: 2rem;
        text-align: center;
        background: #f3f4f6;
        border-radius: 8px;
        color: #6b7280;
        font-size: 0.95rem;
    }
</style>

<div class="max-w-7xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Các Chức Năng</h1>
        <p class="text-gray-600">Chọn chức năng để bắt đầu làm việc</p>
    </div>

    <?php
    $hasSomething = false;

    if (isset($categoryGrouping) && isset($sidebarMenuItems) && isset($currentRoleRank) && isset($roleHierarchy)) {
        foreach ($categoryGrouping as $categoryTitle => $pageKeys) {
            $categoryItems = [];

            foreach ($pageKeys as $pageKey) {
                if ($pageKey === 'system_check') {
                    if (isset($roleHierarchy['Admin']) && $currentRoleRank >= $roleHierarchy['Admin']) {
                        $categoryItems[] = [
                            'page' => 'system_check',
                            'label' => 'Kiểm tra Hệ Thống',
                            'icon' => '🔍',
                            'min_role' => 'Admin'
                        ];
                    } else {
                        $categoryItems[] = [
                            'page' => 'system_check',
                            'label' => 'Kiểm tra Hệ Thống',
                            'icon' => '🔍',
                            'min_role' => 'Admin',
                            'is_restricted' => true
                        ];
                    }
                    continue;
                }

                foreach ($sidebarMenuItems as $menuItem) {
                    if ($menuItem['page'] === $pageKey) {
                        $minRoleRank = $roleHierarchy[$menuItem['min_role']] ?? PHP_INT_MAX;
                        if ($currentRoleRank >= $minRoleRank) {
                            $categoryItems[] = $menuItem;
                        } else {
                            $categoryItems[] = array_merge($menuItem, ['is_restricted' => true]);
                        }
                        break;
                    }
                }
            }

            if (empty($categoryItems)) {
                continue;
            }

            $hasSomething = true;
    ?>
        <div class="menu-section">
            <div class="menu-section-title">
                <span><?php echo htmlspecialchars($categoryTitle, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="menu-grid">
                <?php foreach ($categoryItems as $item): ?>
                    <?php
                    $isRestricted = isset($item['is_restricted']) && $item['is_restricted'];
                    $isActive = isset($page) && $page === $item['page'];
                    $menuUrl = "?page=" . urlencode($item['page']);
                    $classes = 'menu-card';
                    if ($isActive) $classes .= ' active';
                    if ($isRestricted) $classes .= ' restricted';
                    $title = $isRestricted ? 'Bạn không có quyền truy cập chức năng này' : '';
                    ?>
                    <a href="<?php echo $isRestricted ? '#' : $menuUrl; ?>"
                       class="<?php echo $classes; ?>"
                       title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo $isRestricted ? 'onclick="return false;"' : ''; ?>>

                        <?php if ($isRestricted): ?>
                            <div class="lock-icon">🔒</div>
                        <?php endif; ?>

                        <div class="menu-card-icon"><?php echo $item['icon']; ?></div>
                        <div class="menu-card-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></div>

                        <?php if ($isRestricted): ?>
                            <div class="menu-card-role">
                                <?php echo isset($roleEmojis[$item['min_role']]) ? $roleEmojis[$item['min_role']] : '🔒'; ?>
                                <?php echo htmlspecialchars($item['min_role'], ENT_QUOTES, 'UTF-8'); ?>+
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
        }
    }

    if (!$hasSomething):
    ?>
        <div class="empty-section">
            <div style="font-size: 2rem; margin-bottom: 1rem;">📭</div>
            <p>Bạn hiện không có quyền truy cập bất kỳ chức năng nào.</p>
        </div>
    <?php endif; ?>
</div>

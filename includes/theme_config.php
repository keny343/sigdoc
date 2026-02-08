<?php
// theme_config.php - Sistema de Temas e Configurações de UI

// Configurações de tema
define('THEME_COLOR', 'primary'); // primary, success, info, warning, danger
define('SIDEBAR_COLLAPSED', false);
define('DARK_MODE', false);
define('ANIMATIONS_ENABLED', true);

// Cores do tema
$theme_colors = [
    'primary' => [
        'main' => '#007bff',
        'light' => '#e3f2fd',
        'dark' => '#0056b3',
        'text' => '#ffffff'
    ],
    'success' => [
        'main' => '#28a745',
        'light' => '#d4edda',
        'dark' => '#1e7e34',
        'text' => '#ffffff'
    ],
    'info' => [
        'main' => '#17a2b8',
        'light' => '#d1ecf1',
        'dark' => '#117a8b',
        'text' => '#ffffff'
    ],
    'warning' => [
        'main' => '#ffc107',
        'light' => '#fff3cd',
        'dark' => '#d39e00',
        'text' => '#212529'
    ],
    'danger' => [
        'main' => '#dc3545',
        'light' => '#f8d7da',
        'dark' => '#bd2130',
        'text' => '#ffffff'
    ]
];

// Função para obter cor do tema
function get_theme_color($type = 'main') {
    global $theme_colors;
    $current_theme = THEME_COLOR;
    return $theme_colors[$current_theme][$type] ?? $theme_colors['primary'][$type];
}

// Função para gerar CSS customizado
function generate_custom_css() {
    $css = "
    <style>
    :root {
        --theme-color: " . get_theme_color() . ";
        --theme-color-light: " . get_theme_color('light') . ";
        --theme-color-dark: " . get_theme_color('dark') . ";
        --theme-text: " . get_theme_color('text') . ";
    }
    
    .btn-theme {
        background-color: var(--theme-color);
        border-color: var(--theme-color);
        color: var(--theme-text);
    }
    
    .btn-theme:hover {
        background-color: var(--theme-color-dark);
        border-color: var(--theme-color-dark);
        color: var(--theme-text);
    }
    
    .navbar-theme {
        background-color: var(--theme-color) !important;
    }
    
    .card-theme {
        border-left: 4px solid var(--theme-color);
    }
    
    .alert-theme {
        background-color: var(--theme-color-light);
        border-color: var(--theme-color);
        color: var(--theme-color-dark);
    }
    
    " . (ANIMATIONS_ENABLED ? "
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .slide-in {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from { transform: translateX(-100%); }
        to { transform: translateX(0); }
    }
    " : "") . "
    
    " . (DARK_MODE ? "
    body {
        background-color: #1a1a1a;
        color: #ffffff;
    }
    
    .card {
        background-color: #2d2d2d;
        border-color: #404040;
    }
    
    .table {
        color: #ffffff;
    }
    
    .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: #3d3d3d;
    }
    " : "") . "
    
    /* Responsividade melhorada */
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.875rem;
        }
        
        .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .navbar-brand {
            font-size: 1.1rem;
        }
    }
    
    @media (max-width: 576px) {
        .container {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        .table-responsive {
            font-size: 0.8rem;
        }
        
        .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
    }
    
    /* Melhorias de acessibilidade */
    .btn:focus,
    .form-control:focus,
    .form-select:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    
    /* Loading spinner */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid var(--theme-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Tooltips customizados */
    .tooltip-custom {
        position: relative;
        display: inline-block;
    }
    
    .tooltip-custom .tooltiptext {
        visibility: hidden;
        width: 120px;
        background-color: #555;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -60px;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .tooltip-custom:hover .tooltiptext {
        visibility: visible;
        opacity: 1;
    }
    
    /* Notificações toast */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
    }
    
    .toast-custom {
        background-color: var(--theme-color-light);
        border: 1px solid var(--theme-color);
        border-radius: 4px;
        padding: 1rem;
        margin-bottom: 0.5rem;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
        animation: slideInRight 0.3s ease-out;
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }
    </style>
    ";
    
    return $css;
}

// Função para mostrar notificação toast
function show_toast($message, $type = 'info') {
    $icon = '';
    switch ($type) {
        case 'success': $icon = '✅'; break;
        case 'error': $icon = '❌'; break;
        case 'warning': $icon = '⚠️'; break;
        default: $icon = 'ℹ️';
    }
    
    echo "<div class='toast-custom' data-type='$type'>
            <div class='d-flex align-items-center'>
                <span class='me-2'>$icon</span>
                <span>$message</span>
                <button type='button' class='btn-close ms-auto' onclick='this.parentElement.parentElement.remove()'></button>
            </div>
          </div>";
}

// Função para gerar breadcrumb
function generate_breadcrumb($items) {
    $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        $is_last = $index === count($items) - 1;
        $class = $is_last ? 'breadcrumb-item active' : 'breadcrumb-item';
        
        if ($is_last) {
            $breadcrumb .= "<li class='$class' aria-current='page'>{$item['text']}</li>";
        } else {
            $breadcrumb .= "<li class='$class'><a href='{$item['url']}'>{$item['text']}</a></li>";
        }
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

// Função para gerar card de estatística
function generate_stat_card($title, $value, $icon, $color = 'primary', $trend = null) {
    $trend_html = '';
    if ($trend) {
        $trend_class = $trend > 0 ? 'text-success' : 'text-danger';
        $trend_icon = $trend > 0 ? '↗' : '↘';
        $trend_html = "<small class='$trend_class'>$trend_icon $trend%</small>";
    }
    
    return "
    <div class='card card-theme h-100'>
        <div class='card-body'>
            <div class='d-flex justify-content-between align-items-center'>
                <div>
                    <h6 class='card-title text-muted mb-1'>$title</h6>
                    <h3 class='mb-0'>$value</h3>
                    $trend_html
                </div>
                <div class='text-$color' style='font-size: 2rem;'>$icon</div>
            </div>
        </div>
    </div>";
}

// Função para gerar progress bar customizada
function generate_progress_bar($percentage, $label, $color = 'primary') {
    return "
    <div class='mb-3'>
        <div class='d-flex justify-content-between mb-1'>
            <span class='small'>$label</span>
            <span class='small'>$percentage%</span>
        </div>
        <div class='progress' style='height: 8px;'>
            <div class='progress-bar bg-$color' role='progressbar' 
                 style='width: $percentage%' aria-valuenow='$percentage' 
                 aria-valuemin='0' aria-valuemax='100'></div>
        </div>
    </div>";
}
?> 
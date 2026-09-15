<?php
// theme_config.php — UI helpers aligned with SIGDoc design tokens

define('THEME_COLOR', 'brand');
define('ANIMATIONS_ENABLED', true);

function generate_custom_css() {
    // Tokens live in style.css; keep hook for backward compatibility.
    return '';
}

function show_toast($message, $type = 'info') {
    $class = 'alert alert-info';
    if ($type === 'success') $class = 'alert alert-success';
    if ($type === 'error') $class = 'alert alert-danger';
    if ($type === 'warning') $class = 'alert alert-warning';
    $safe = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');
    echo "<div class='$class' role='status'>$safe</div>";
}

function generate_breadcrumb($items) {
    $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb mb-3">';
    foreach ($items as $index => $item) {
        $is_last = $index === count($items) - 1;
        $text = htmlspecialchars($item['text'] ?? '', ENT_QUOTES, 'UTF-8');
        if ($is_last) {
            $breadcrumb .= "<li class='breadcrumb-item active' aria-current='page'>{$text}</li>";
        } else {
            $url = htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8');
            $breadcrumb .= "<li class='breadcrumb-item'><a href='{$url}'>{$text}</a></li>";
        }
    }
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

/**
 * KPI card — no emoji decoration; tone: brand|sucesso|info|aviso
 */
function generate_stat_card($title, $value, $icon = '', $color = 'primary', $trend = null) {
    $toneMap = [
        'primary' => 'brand',
        'brand' => 'brand',
        'success' => 'sucesso',
        'sucesso' => 'sucesso',
        'info' => 'info',
        'warning' => 'aviso',
        'aviso' => 'aviso',
        'danger' => 'aviso',
    ];
    $tone = $toneMap[$color] ?? 'brand';
    $titleSafe = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
    $valueSafe = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $trend_html = '';
    if ($trend !== null && $trend !== '') {
        $trend_class = ((float) $trend > 0) ? 'text-success' : 'text-danger';
        $trendSafe = htmlspecialchars((string) $trend, ENT_QUOTES, 'UTF-8');
        $trend_html = "<div class='stat-label $trend_class' style='margin-top:6px'>$trendSafe%</div>";
    }

    return "
    <div class='stat-card stat-card--$tone h-100'>
      <p class='stat-label'>$titleSafe</p>
      <p class='stat-value'>$valueSafe</p>
      $trend_html
    </div>";
}

function generate_progress_bar($percentage, $label, $color = 'primary') {
    $pct = max(0, min(100, (float) $percentage));
    $labelSafe = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
    return "
    <div class='mb-3'>
        <div class='d-flex justify-content-between mb-1'>
            <span class='small'>$labelSafe</span>
            <span class='small'>" . (int) $pct . "%</span>
        </div>
        <div class='progress' style='height: 8px; background: var(--borda-suave);'>
            <div class='progress-bar bg-primary' role='progressbar'
                 style='width: {$pct}%; background: var(--brand) !important;'
                 aria-valuenow='{$pct}' aria-valuemin='0' aria-valuemax='100'></div>
        </div>
    </div>";
}

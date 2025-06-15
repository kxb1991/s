<?php
// index.php - Vista pública sin AJAX
require_once 'config/database.php';
require_once 'models/EventoModel.php';
require_once 'models/DeporteModel.php';
require_once 'models/CanalModel.php';
require_once 'models/CompeticionModel.php';

// Configuración si no existe la clase Config
if (!class_exists('Config')) {
    class Config {
        const ITEMS_PER_PAGE = 20;
        
        public static function formatDate($fecha, $formato = 'd/m/Y') {
            return date($formato, strtotime($fecha));
        }
        
        public static function getEstadoFormateado($estado) {
            $estados = [
                'programado' => 'Programado',
                'en_vivo' => 'En Vivo',
                'finalizado' => 'Finalizado'
            ];
            return $estados[$estado] ?? ucfirst($estado);
        }
    }
}

// Función para formatear tiempo restante
function formatTiempoRestante($minutos) {
    if ($minutos < 0) return "En curso";
    if ($minutos == 0) return "Ahora";
    if ($minutos < 60) return $minutos . "min";
    
    $horas = floor($minutos / 60);
    $mins = $minutos % 60;
    
    if ($horas < 24) {
        return $horas . "h " . ($mins > 0 ? $mins . "min" : "");
    }
    
    $dias = floor($horas / 24);
    $horas_restantes = $horas % 24;
    
    return $dias . "d " . ($horas_restantes > 0 ? $horas_restantes . "h" : "");
}

// Función para generar tokens únicos
function generateToken($evento_id) {
    return hash('sha256', $evento_id . time() . rand());
}

// Función para construir URL con parámetros
function buildUrl($params = []) {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return '?' . http_build_query($currentParams);
}

// Inicializar conexión
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Inicializar modelos
$eventoModel = new EventoModel($db);
$deporteModel = new DeporteModel($db);
$canalModel = new CanalModel($db);
$competicionModel = new CompeticionModel($db);

// Configuración inicial para carga de página
$filtros = [
    'dia' => $_GET['dia'] ?? 'hoy',
    'deporte' => $_GET['deporte'] ?? '',
    'competicion' => $_GET['competicion'] ?? '',
    'canal' => $_GET['canal'] ?? '',
    'buscar' => $_GET['buscar'] ?? ''
];

// Configurar fechas
switch ($filtros['dia']) {
    case 'hoy':
        $fecha_inicio = date('Y-m-d') . ' 00:00:00';
        $fecha_fin = date('Y-m-d') . ' 23:59:59';
        $titulo_seccion = 'Eventos de Hoy';
        $subtitulo = date('d/m/Y');
        break;
    case 'manana':
        $fecha_inicio = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $fecha_fin = date('Y-m-d', strtotime('+1 day')) . ' 23:59:59';
        $titulo_seccion = 'Eventos de Mañana';
        $subtitulo = date('d/m/Y', strtotime('+1 day'));
        break;
    case 'otros':
        $fecha_inicio = date('Y-m-d H:i:s', strtotime('+2 days'));
        $fecha_fin = date('Y-m-d H:i:s', strtotime('+30 days'));
        $titulo_seccion = 'Próximos Eventos';
        $subtitulo = 'Siguientes 30 días';
        break;
    default:
        $filtros['dia'] = 'hoy';
        $fecha_inicio = date('Y-m-d') . ' 00:00:00';
        $fecha_fin = date('Y-m-d') . ' 23:59:59';
        $titulo_seccion = 'Eventos de Hoy';
        $subtitulo = date('d/m/Y');
        break;
}

// Obtener datos iniciales
$deportes = $deporteModel->obtenerTodos();
$canales = $canalModel->obtenerTodos();
$competiciones = $competicionModel->obtenerTodos();

// Conteos para botones de día
try {
    // Conteo HOY - solo eventos futuros o en curso
    $stmt_hoy = $db->prepare("
        SELECT COUNT(*) as total 
        FROM eventos e
        LEFT JOIN deportes d ON e.deporte_id = d.id
        WHERE DATE(e.fecha_evento) = CURDATE()
        AND (
            e.fecha_evento > NOW()
            OR
            NOW() < DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE)
        )
    ");
    $stmt_hoy->execute();
    $count_hoy = $stmt_hoy->fetch(PDO::FETCH_ASSOC)['total'];

    // Conteo MAÑANA
    $stmt_manana = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
    $stmt_manana->execute();
    $count_manana = $stmt_manana->fetch(PDO::FETCH_ASSOC)['total'];

    // Conteo OTROS
    $stmt_otros = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) > DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha_evento) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stmt_otros->execute();
    $count_otros = $stmt_otros->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $count_hoy = 0;
    $count_manana = 0;
    $count_otros = 0;
}

// Cargar eventos
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$items_por_pagina = Config::ITEMS_PER_PAGE;
$offset = ($pagina - 1) * $items_por_pagina;

// Query principal de eventos
$query_eventos = "SELECT e.*, 
                         d.nombre as deporte_nombre, 
                         d.icono as deporte_icono,
                         d.duracion_tipica,
                         c.nombre as competicion_nombre, 
                         c.pais as competicion_pais,
                         ch.nombre as canal_nombre,
                         ch.logo as canal_logo,
                         TIMESTAMPDIFF(MINUTE, NOW(), e.fecha_evento) as minutos_hasta_evento,
                         CASE 
                            WHEN NOW() BETWEEN e.fecha_evento 
                            AND DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE)
                            THEN TRUE ELSE FALSE
                         END as en_vivo_calculado,
                         CASE 
                            WHEN TIMESTAMPDIFF(MINUTE, NOW(), e.fecha_evento) BETWEEN -COALESCE(e.duracion_minutos, d.duracion_tipica, 90) AND 15
                            THEN TRUE ELSE FALSE
                         END as enlace_activo
                  FROM eventos e
                  LEFT JOIN deportes d ON e.deporte_id = d.id
                  LEFT JOIN competiciones c ON e.competicion_id = c.id
                  LEFT JOIN canales ch ON e.canal_id = ch.id
                  WHERE e.fecha_evento BETWEEN :fecha_inicio AND :fecha_fin";

// Solo aplicar filtro de eventos no finalizados para "hoy"
if ($filtros['dia'] == 'hoy') {
    $query_eventos .= " AND (
        e.fecha_evento > NOW()
        OR
        NOW() < DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE)
    )";
}

$params = [
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
];

// Aplicar filtros
if (!empty($filtros['deporte'])) {
    $query_eventos .= " AND e.deporte_id = :deporte";
    $params[':deporte'] = $filtros['deporte'];
}
if (!empty($filtros['competicion'])) {
    $query_eventos .= " AND e.competicion_id = :competicion";
    $params[':competicion'] = $filtros['competicion'];
}
if (!empty($filtros['canal'])) {
    $query_eventos .= " AND e.canal_id = :canal";
    $params[':canal'] = $filtros['canal'];
}
if (!empty($filtros['buscar'])) {
    $query_eventos .= " AND (e.titulo LIKE :buscar OR e.descripcion LIKE :buscar2 OR e.equipo_local LIKE :buscar3 OR e.equipo_visitante LIKE :buscar4)";
    $buscar_param = '%' . $filtros['buscar'] . '%';
    $params[':buscar'] = $buscar_param;
    $params[':buscar2'] = $buscar_param;
    $params[':buscar3'] = $buscar_param;
    $params[':buscar4'] = $buscar_param;
}

// Ordenar: eventos en vivo primero
$query_eventos .= " ORDER BY 
                    CASE 
                        WHEN NOW() BETWEEN e.fecha_evento 
                        AND DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE)
                        THEN 0 
                        ELSE 1 
                    END,
                    e.fecha_evento ASC";

// Contar total para paginación
$query_count = "SELECT COUNT(*) as total FROM eventos e
                LEFT JOIN deportes d ON e.deporte_id = d.id
                LEFT JOIN competiciones c ON e.competicion_id = c.id
                LEFT JOIN canales ch ON e.canal_id = ch.id
                WHERE e.fecha_evento BETWEEN :fecha_inicio AND :fecha_fin";

// Solo aplicar filtro de eventos no finalizados para "hoy"
if ($filtros['dia'] == 'hoy') {
    $query_count .= " AND (
        e.fecha_evento > NOW()
        OR
        NOW() < DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE)
    )";
}

if (!empty($filtros['deporte'])) {
    $query_count .= " AND e.deporte_id = :deporte";
}
if (!empty($filtros['competicion'])) {
    $query_count .= " AND e.competicion_id = :competicion";
}
if (!empty($filtros['canal'])) {
    $query_count .= " AND e.canal_id = :canal";
}
if (!empty($filtros['buscar'])) {
    $query_count .= " AND (e.titulo LIKE :buscar OR e.descripcion LIKE :buscar2 OR e.equipo_local LIKE :buscar3 OR e.equipo_visitante LIKE :buscar4)";
}

try {
    $stmt_count = $db->prepare($query_count);
    foreach ($params as $key => $value) {
        $stmt_count->bindValue($key, $value);
    }
    $stmt_count->execute();
    $total_eventos = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $total_eventos = 0;
    error_log("Error al contar eventos: " . $e->getMessage());
}

// Aplicar paginación
$query_eventos .= " LIMIT :limite OFFSET :offset";

try {
    $stmt = $db->prepare($query_eventos);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', (int)$items_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $eventos = [];
    error_log("Error al obtener eventos: " . $e->getMessage());
}

// Generar tokens para eventos que los necesiten
foreach ($eventos as &$evento) {
    if (!$evento['token_acceso'] && $evento['minutos_hasta_evento'] <= 1440) { // 24 horas antes
        $token = generateToken($evento['id']);
        $expira = date('Y-m-d H:i:s', strtotime($evento['fecha_evento'] . ' +3 hours'));
        
        try {
            $update_token = "UPDATE eventos SET token_acceso = ?, token_expira = ? WHERE id = ?";
            $stmt_token = $db->prepare($update_token);
            $stmt_token->execute([$token, $expira, $evento['id']]);
            $evento['token_acceso'] = $token;
        } catch (Exception $e) {
            // Si falla, usar un token temporal
            $evento['token_acceso'] = generateToken($evento['id']);
        }
    }
}

$total_paginas = ceil($total_eventos / $items_por_pagina);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FutbolTV.su - <?= htmlspecialchars($titulo_seccion) ?></title>
    <meta name="description" content="Encuentra todos los eventos deportivos por día. Fútbol, baloncesto, tenis y más deportes en vivo.">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header-left .subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Botones de día */
        .day-selector {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .day-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .day-btn {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            color: #64748b;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .day-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .day-btn.active {
            background: #6366f1;
            border-color: #6366f1;
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .day-btn .count {
            background: rgba(255, 255, 255, 0.2);
            color: inherit;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .day-btn.active .count {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Filtros */
        .filters {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filters-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }

        .filter-select, .search-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: border-color 0.2s;
        }

        .filter-select:focus, .search-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            background: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Sección de eventos */
        .events-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-height: 400px;
        }

        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .events-header h2 {
            font-size: 1.8rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .events-header .subtitle {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 4px;
        }

        .events-count {
            color: #6b7280;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .live-count {
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        /* Cards de eventos */
        .events-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .event-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #e5e7eb;
            transition: all 0.3s;
        }

        .event-card:hover {
            background: #f3f4f6;
            border-color: #6366f1;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .event-card:hover::before {
            background: #6366f1;
            width: 6px;
        }

        .event-card.live-event {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-color: #ef4444;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.1);
        }

        .event-card.live-event::before {
            background: #ef4444;
            width: 6px;
        }

        .event-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }

        .event-time {
            background: #6366f1;
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 600;
            min-width: 80px;
            text-align: center;
            font-size: 14px;
            position: relative;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }

        .event-time.live {
            background: #ef4444;
            animation: pulse 1.5s infinite;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        }

        .event-time.soon {
            background: #f59e0b;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .live-indicator {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            padding: 3px 6px;
            border-radius: 10px;
            font-weight: bold;
            animation: blink 1s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }

        .event-link {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.2s;
        }

        .event-link.disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }

        .event-info {
            flex: 1;
        }

        .event-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .event-details {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .tiempo-restante {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        .event-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-programado {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-en_vivo {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-finalizado {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .event-channel {
            background: #f59e0b;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
        }

        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #6b7280;
            border: 1px solid #d1d5db;
            background: white;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #f9fafb;
            border-color: #6366f1;
            color: #6366f1;
            transform: translateY(-1px);
        }

        .pagination .current {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Estado vacío */
        .no-events {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
        }

        .no-events-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .no-events h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: #374151;
        }

        .no-events p {
            margin-bottom: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .header-content { flex-direction: column; gap: 15px; text-align: center; }
            .day-buttons { flex-direction: column; align-items: stretch; }
            .day-btn { min-width: auto; }
            .filters-grid { grid-template-columns: 1fr; }
            .event-card { flex-direction: column; align-items: stretch; gap: 15px; }
            .event-left { flex-direction: column; align-items: stretch; gap: 10px; }
            .events-header { flex-direction: column; align-items: stretch; gap: 10px; }
        }

        @media (max-width: 480px) {
            .header-left h1 { font-size: 1.8rem; }
            .filter-actions { justify-content: center; }
            .event-details { font-size: 13px; }
            .pagination { flex-wrap: wrap; }
            .pagination a, .pagination span { padding: 8px 12px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>FutbolTV.su</h1>
                    <div class="subtitle">Deportes en directo</div>
                </div>
            </div>
        </div>

        <!-- Selector de Día -->
        <div class="day-selector">
            <div class="day-buttons">
                <a href="<?= buildUrl(['dia' => 'hoy', 'pagina' => null]) ?>" 
                   class="day-btn <?= $filtros['dia'] == 'hoy' ? 'active' : '' ?>">
                    📅 Hoy
                    <span class="count"><?= $count_hoy ?></span>
                </a>
                
                <a href="<?= buildUrl(['dia' => 'manana', 'pagina' => null]) ?>" 
                   class="day-btn <?= $filtros['dia'] == 'manana' ? 'active' : '' ?>">
                    🌅 Mañana
                    <span class="count"><?= $count_manana ?></span>
                </a>
                
                <a href="<?= buildUrl(['dia' => 'otros', 'pagina' => null]) ?>" 
                   class="day-btn <?= $filtros['dia'] == 'otros' ? 'active' : '' ?>">
                    📆 Próximos
                    <span class="count"><?= $count_otros ?></span>
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters">
            <form method="get" action="" class="filters-form">
                <input type="hidden" name="dia" value="<?= htmlspecialchars($filtros['dia']) ?>">
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="buscar">🔍 Buscar eventos</label>
                        <input type="text" 
                               id="buscar" 
                               name="buscar" 
                               class="search-input" 
                               placeholder="Buscar equipos, eventos..." 
                               value="<?= htmlspecialchars($filtros['buscar']) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="deporte">⚽ Deporte</label>
                        <select name="deporte" id="deporte" class="filter-select">
                            <option value="">Todos los deportes</option>
                            <?php foreach ($deportes as $deporte): ?>
                                <option value="<?= $deporte['id'] ?>" <?= $filtros['deporte'] == $deporte['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($deporte['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="competicion">🏆 Competición</label>
                        <select name="competicion" id="competicion" class="filter-select">
                            <option value="">Todas las competiciones</option>
                            <?php foreach ($competiciones as $competicion): ?>
                                <option value="<?= $competicion['id'] ?>" 
                                        <?= $filtros['competicion'] == $competicion['id'] ? 'selected' : '' ?>
                                        data-deporte="<?= $competicion['deporte_id'] ?>">
                                    <?= htmlspecialchars($competicion['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="canal">📺 Canal</label>
                        <select name="canal" id="canal" class="filter-select">
                            <option value="">Todos los canales</option>
                            <?php foreach ($canales as $canal): ?>
                                <option value="<?= $canal['id'] ?>" <?= $filtros['canal'] == $canal['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($canal['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn">🔍 Buscar</button>
                    <a href="?dia=<?= htmlspecialchars($filtros['dia']) ?>" class="btn btn-secondary">🔄 Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Lista de Eventos -->
        <div class="events-section">
            <div class="events-header">
                <div>
                    <h2>
                        <?php if ($filtros['dia'] == 'hoy'): ?>
                            📅 <?= htmlspecialchars($titulo_seccion) ?>
                        <?php elseif ($filtros['dia'] == 'manana'): ?>
                            🌅 <?= htmlspecialchars($titulo_seccion) ?>
                        <?php else: ?>
                            📆 <?= htmlspecialchars($titulo_seccion) ?>
                        <?php endif; ?>
                    </h2>
                    <div class="subtitle"><?= htmlspecialchars($subtitulo) ?></div>
                </div>
                <div class="events-count">
                    <?= $total_eventos ?> evento<?= $total_eventos != 1 ? 's' : '' ?>
                    <?php 
                    $eventos_en_vivo = array_filter($eventos, function($evento) {
                        return $evento['en_vivo_calculado'];
                    });
                    if (count($eventos_en_vivo) > 0): ?>
                        <span class="live-count">
                            🔴 <?= count($eventos_en_vivo) ?> EN VIVO
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($eventos)): ?>
                <div class="no-events">
                    <div class="no-events-icon">
                        <?php if ($filtros['dia'] == 'hoy'): ?>
                            😴
                        <?php elseif ($filtros['dia'] == 'manana'): ?>
                            🌙
                        <?php else: ?>
                            📅
                        <?php endif; ?>
                    </div>
                    <h3>No hay eventos <?= $filtros['dia'] == 'hoy' ? 'hoy' : ($filtros['dia'] == 'manana' ? 'mañana' : 'próximos') ?></h3>
                    <p>No se encontraron eventos para <?= strtolower($titulo_seccion) ?> con los filtros aplicados.</p>
                    <p>Prueba ajustando los filtros o revisa en otro día.</p>
                </div>
            <?php else: ?>
                <div class="events-list">
                    <?php foreach ($eventos as $evento): ?>
                    <?php 
                        $enlace_disponible = $evento['enlace_activo'] || $evento['en_vivo_calculado'];
                        $es_live = $evento['en_vivo_calculado'];
                        $tiempo_restante = $evento['minutos_hasta_evento'];
                        $clase_tiempo = $es_live ? 'live' : ($tiempo_restante <= 15 && $tiempo_restante > 0 ? 'soon' : '');
                        $clase_card = $es_live ? 'live-event' : '';
                        
                        // Asegurar token
                        if (empty($evento['token_acceso'])) {
                            $evento['token_acceso'] = generateToken($evento['id']);
                        }
                    ?>
                    
                    <div class="event-card-wrapper">
                        <?php if ($enlace_disponible): ?>
                            <a href="evento.php?id=<?= $evento['id'] ?>&token=<?= $evento['token_acceso'] ?>" class="event-link">
                        <?php else: ?>
                            <div class="event-link disabled">
                        <?php endif; ?>
                        
                            <div class="event-card <?= $clase_card ?>">
                                <div class="event-left">
                                    <div class="event-time <?= $clase_tiempo ?>">
                                        <?php if ($es_live): ?>
                                            🔴 LIVE
                                            <div class="live-indicator">●</div>
                                        <?php else: ?>
                                            <?= date('H:i', strtotime($evento['fecha_evento'])) ?>
                                            <?php if ($tiempo_restante <= 15 && $tiempo_restante > 0): ?>
                                                <span class="countdown-badge" style="position: absolute; top: -8px; right: -8px; background: #f59e0b; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;"><?= $tiempo_restante ?>min</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-info">
                                        <div class="event-title">
                                            <?= htmlspecialchars($evento['titulo']) ?>
                                            <?php if (isset($evento['destacado']) && $evento['destacado']): ?>
                                                <span style="color: #f59e0b;">⭐</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-details">
                                            <?php if ($evento['deporte_nombre']): ?>
                                                <span>⚽ <?= htmlspecialchars($evento['deporte_nombre']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($evento['competicion_nombre']): ?>
                                                <span>• 🏆 <?= htmlspecialchars($evento['competicion_nombre']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($filtros['dia'] != 'hoy'): ?>
                                                <span>• 📅 <?= Config::formatDate($evento['fecha_evento'], 'd/m/Y') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($filtros['dia'] == 'hoy'): ?>
                                            <?php if (!$es_live && $tiempo_restante > 0): ?>
                                                <div class="tiempo-restante">
                                                    <?php if ($tiempo_restante <= 15): ?>
                                                        ⚡ Disponible en <?= $tiempo_restante ?> minutos
                                                    <?php else: ?>
                                                        🕒 Comienza en <?= formatTiempoRestante($tiempo_restante) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($es_live): ?>
                                                <div class="tiempo-restante" style="color: #ef4444; font-weight: 600;">
                                                    🔴 Transmisión en vivo
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="tiempo-restante">
                                                📅 <?= formatTiempoRestante($tiempo_restante) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                    <?php if ($evento['canal_nombre']): ?>
                                        <div class="event-channel">📺 <?= htmlspecialchars($evento['canal_nombre']) ?></div>
                                    <?php endif; ?>
                                    
                                    <span class="event-status status-<?= $evento['estado'] ?>">
                                        <?php if ($es_live): ?>
                                            🔴 EN VIVO
                                        <?php else: ?>
                                            <?php $estado_icons = ['programado' => '📋', 'en_vivo' => '🔴', 'finalizado' => '✅']; ?>
                                            <?= ($estado_icons[$evento['estado']] ?? '') . ' ' . Config::getEstadoFormateado($evento['estado']) ?>
                                        <?php endif; ?>
                                    </span>
                                    
                                    <?php if ($evento['duracion_tipica']): ?>
                                        <small style="color: #6b7280; font-size: 11px;">
                                            ⏱️ ~<?= $evento['duracion_tipica'] ?>min
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        
                        <?php if ($enlace_disponible): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina > 1): ?>
                        <a href="<?= buildUrl(['pagina' => $pagina - 1]) ?>">← Anterior</a>
                    <?php else: ?>
                        <span class="disabled">← Anterior</span>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $pagina - 2);
                    $end = min($total_paginas, $pagina + 2);
                    
                    if ($start > 1) {
                        echo '<a href="' . buildUrl(['pagina' => 1]) . '">1</a>';
                        if ($start > 2) echo '<span>...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $pagina): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= buildUrl(['pagina' => $i]) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_paginas): ?>
                        <?php if ($end < $total_paginas - 1) echo '<span>...</span>'; ?>
                        <a href="<?= buildUrl(['pagina' => $total_paginas]) ?>"><?= $total_paginas ?></a>
                    <?php endif; ?>

                    <?php if ($pagina < $total_paginas): ?>
                        <a href="<?= buildUrl(['pagina' => $pagina + 1]) ?>">Siguiente →</a>
                    <?php else: ?>
                        <span class="disabled">Siguiente →</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filtrar competiciones según deporte seleccionado
        document.getElementById('deporte').addEventListener('change', function() {
            const deporteId = this.value;
            const competicionSelect = document.getElementById('competicion');
            const competicionActual = competicionSelect.value;
            
            if (!deporteId) {
                // Mostrar todas las opciones
                Array.from(competicionSelect.options).forEach(option => {
                    if (option.value) {
                        option.style.display = '';
                    }
                });
                return;
            }
            
            // Filtrar opciones según el deporte
            let hayCompeticiones = false;
            Array.from(competicionSelect.options).forEach(option => {
                if (option.value) {
                    const deporteOption = option.getAttribute('data-deporte');
                    if (deporteOption === deporteId) {
                        option.style.display = '';
                        hayCompeticiones = true;
                    } else {
                        option.style.display = 'none';
                        // Si la competición actual no corresponde al deporte, resetear
                        if (option.value === competicionActual) {
                            competicionSelect.value = '';
                        }
                    }
                }
            });
            
            if (!hayCompeticiones) {
                competicionSelect.innerHTML = '<option value="">No hay competiciones</option>';
            }
        });

        // Auto-refrescar si hay eventos en vivo
        <?php if (count($eventos_en_vivo) > 0): ?>
        setTimeout(function() {
            location.reload();
        }, 30000); // Refrescar cada 30 segundos
        <?php endif; ?>
    </script>
</body>
</html>

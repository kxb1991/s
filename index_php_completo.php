<?php
// index.php - Vista pública con AJAX completo
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

// Procesamiento AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'obtener_competiciones':
            try {
                $competiciones_deporte = $competicionModel->obtenerPorDeporte($_POST['deporte_id']);
                echo json_encode(['success' => true, 'competiciones' => $competiciones_deporte]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'filtrar_eventos':
            try {
                // Obtener filtros
                $filtros_ajax = [
                    'dia' => $_POST['dia'] ?? 'hoy',
                    'deporte' => $_POST['deporte'] ?? '',
                    'competicion' => $_POST['competicion'] ?? '',
                    'canal' => $_POST['canal'] ?? '',
                    'buscar' => $_POST['buscar'] ?? ''
                ];

                // Configurar fechas
                switch ($filtros_ajax['dia']) {
                    case 'hoy':
                        $fecha_inicio = date('Y-m-d') . ' 00:00:00';
                        $fecha_fin = date('Y-m-d') . ' 23:59:59';
                        break;
                    case 'manana':
                        $fecha_inicio = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
                        $fecha_fin = date('Y-m-d', strtotime('+1 day')) . ' 23:59:59';
                        break;
                    case 'otros':
                        $fecha_inicio = date('Y-m-d H:i:s', strtotime('+2 days'));
                        $fecha_fin = date('Y-m-d H:i:s', strtotime('+30 days'));
                        break;
                }

                // Paginación
                $items_por_pagina = Config::ITEMS_PER_PAGE;
                $pagina_ajax = (int)($_POST['pagina'] ?? 1);
                $offset_ajax = ($pagina_ajax - 1) * $items_por_pagina;

                // Query principal
                $query_ajax = "SELECT e.*, 
                                     d.nombre as deporte_nombre, 
                                     d.icono as deporte_icono,
                                     d.duracion_tipica,
                                     c.nombre as competicion_nombre, 
                                     c.pais as competicion_pais,
                                     ch.nombre as canal_nombre,
                                     ch.logo as canal_logo,
                                     TIMESTAMPDIFF(MINUTE, NOW(), e.fecha_evento) as minutos_hasta_evento,
                                     CASE 
                                        WHEN NOW() >= e.fecha_evento AND NOW() <= DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE)
                                        THEN TRUE ELSE FALSE
                                     END as en_vivo_calculado,
                                     CASE 
                                        WHEN TIMESTAMPDIFF(MINUTE, NOW(), e.fecha_evento) <= 15 AND TIMESTAMPDIFF(MINUTE, NOW(), e.fecha_evento) >= -COALESCE(e.duracion_minutos, d.duracion_tipica, 90)
                                        THEN TRUE ELSE FALSE
                                     END as enlace_activo
                              FROM eventos e
                              LEFT JOIN deportes d ON e.deporte_id = d.id
                              LEFT JOIN competiciones c ON e.competicion_id = c.id
                              LEFT JOIN canales ch ON e.canal_id = ch.id
                              WHERE e.fecha_evento BETWEEN ? AND ?";

                $params_ajax = [$fecha_inicio, $fecha_fin];

                // Aplicar filtros
                if (!empty($filtros_ajax['deporte'])) {
                    $query_ajax .= " AND e.deporte_id = ?";
                    $params_ajax[] = $filtros_ajax['deporte'];
                }
                if (!empty($filtros_ajax['competicion'])) {
                    $query_ajax .= " AND e.competicion_id = ?";
                    $params_ajax[] = $filtros_ajax['competicion'];
                }
                if (!empty($filtros_ajax['canal'])) {
                    $query_ajax .= " AND e.canal_id = ?";
                    $params_ajax[] = $filtros_ajax['canal'];
                }
                if (!empty($filtros_ajax['buscar'])) {
                    $query_ajax .= " AND (e.titulo LIKE ? OR e.descripcion LIKE ? OR e.equipo_local LIKE ? OR e.equipo_visitante LIKE ?)";
                    $buscar_param = '%' . $filtros_ajax['buscar'] . '%';
                    $params_ajax[] = $buscar_param;
                    $params_ajax[] = $buscar_param;
                    $params_ajax[] = $buscar_param;
                    $params_ajax[] = $buscar_param;
                }

                // Ordenar
                if ($filtros_ajax['dia'] == 'hoy') {
                    $query_ajax .= " ORDER BY 
                                    CASE 
                                        WHEN NOW() >= e.fecha_evento AND NOW() <= DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE) 
                                        THEN 0 
                                        ELSE 1 
                                    END,
                                    e.fecha_evento ASC";
                } else {
                    $query_ajax .= " ORDER BY e.fecha_evento ASC";
                }

                // Contar total
                $query_count_ajax = "SELECT COUNT(*) as total FROM eventos e
                                    LEFT JOIN deportes d ON e.deporte_id = d.id
                                    LEFT JOIN competiciones c ON e.competicion_id = c.id
                                    LEFT JOIN canales ch ON e.canal_id = ch.id
                                    WHERE e.fecha_evento BETWEEN ? AND ?";
                
                $params_count = [$fecha_inicio, $fecha_fin];
                
                if (!empty($filtros_ajax['deporte'])) {
                    $query_count_ajax .= " AND e.deporte_id = ?";
                    $params_count[] = $filtros_ajax['deporte'];
                }
                if (!empty($filtros_ajax['competicion'])) {
                    $query_count_ajax .= " AND e.competicion_id = ?";
                    $params_count[] = $filtros_ajax['competicion'];
                }
                if (!empty($filtros_ajax['canal'])) {
                    $query_count_ajax .= " AND e.canal_id = ?";
                    $params_count[] = $filtros_ajax['canal'];
                }
                if (!empty($filtros_ajax['buscar'])) {
                    $query_count_ajax .= " AND (e.titulo LIKE ? OR e.descripcion LIKE ? OR e.equipo_local LIKE ? OR e.equipo_visitante LIKE ?)";
                    $buscar_param = '%' . $filtros_ajax['buscar'] . '%';
                    $params_count[] = $buscar_param;
                    $params_count[] = $buscar_param;
                    $params_count[] = $buscar_param;
                    $params_count[] = $buscar_param;
                }
                
                $stmt_count_ajax = $db->prepare($query_count_ajax);
                $stmt_count_ajax->execute($params_count);
                $total_eventos_ajax = $stmt_count_ajax->fetch()['total'];

                // Paginación
                $query_ajax .= " LIMIT ? OFFSET ?";
                $params_ajax[] = $items_por_pagina;
                $params_ajax[] = $offset_ajax;

                $stmt_ajax = $db->prepare($query_ajax);
                $stmt_ajax->execute($params_ajax);
                $eventos_ajax = $stmt_ajax->fetchAll(PDO::FETCH_ASSOC);

                // Generar HTML
                $html_eventos = '';
                $eventos_en_vivo = 0;

                foreach ($eventos_ajax as $evento) {
                    $enlace_disponible = $evento['enlace_activo'] || $evento['en_vivo_calculado'];
                    $es_live = $evento['en_vivo_calculado'];
                    $tiempo_restante = $evento['minutos_hasta_evento'];
                    $clase_tiempo = $es_live ? 'live' : ($tiempo_restante <= 15 && $tiempo_restante > 0 ? 'soon' : '');
                    $clase_card = $es_live ? 'live-event' : '';
                    
                    if ($es_live) $eventos_en_vivo++;

                    $html_eventos .= '<div class="event-card-wrapper">';
                    
                    if ($enlace_disponible) {
                        $html_eventos .= '<a href="evento.php?id=' . $evento['id'] . '&token=' . $evento['token_acceso'] . '" class="event-link">';
                    } else {
                        $html_eventos .= '<div class="event-link disabled">';
                    }

                    $html_eventos .= '<div class="event-card ' . $clase_card . '">';
                    
                    // Tiempo del evento
                    $html_eventos .= '<div class="event-left">';
                    $html_eventos .= '<div class="event-time ' . $clase_tiempo . '">';
                    if ($es_live) {
                        $html_eventos .= '🔴 LIVE<div class="live-indicator">●</div>';
                    } else {
                        $html_eventos .= date('H:i', strtotime($evento['fecha_evento']));
                        if ($tiempo_restante <= 15 && $tiempo_restante > 0) {
                            $html_eventos .= '<span class="countdown-badge">' . $tiempo_restante . 'min</span>';
                        }
                    }
                    $html_eventos .= '</div>';
                    
                    // Información del evento
                    $html_eventos .= '<div class="event-info">';
                    $html_eventos .= '<div class="event-title">' . htmlspecialchars($evento['titulo']);
                    if (isset($evento['destacado']) && $evento['destacado']) {
                        $html_eventos .= '<span style="color: #f59e0b;">⭐</span>';
                    }
                    $html_eventos .= '</div>';
                    
                    $html_eventos .= '<div class="event-details">';
                    if ($evento['deporte_nombre']) {
                        $html_eventos .= '<span>⚽ ' . htmlspecialchars($evento['deporte_nombre']) . '</span>';
                    }
                    if ($evento['competicion_nombre']) {
                        $html_eventos .= '<span>• 🏆 ' . htmlspecialchars($evento['competicion_nombre']) . '</span>';
                    }
                    if ($filtros_ajax['dia'] != 'hoy') {
                        $html_eventos .= '<span>• 📅 ' . Config::formatDate($evento['fecha_evento'], 'd/m/Y') . '</span>';
                    }
                    $html_eventos .= '</div>';

                    // Información de tiempo
                    if ($filtros_ajax['dia'] == 'hoy') {
                        if (!$es_live && $tiempo_restante > 0) {
                            $html_eventos .= '<div class="tiempo-restante">';
                            if ($tiempo_restante <= 15) {
                                $html_eventos .= '⚡ Disponible en ' . $tiempo_restante . ' minutos';
                            } else {
                                $html_eventos .= '🕒 Comienza en ' . formatTiempoRestante($tiempo_restante);
                            }
                            $html_eventos .= '</div>';
                        } elseif ($es_live) {
                            $html_eventos .= '<div class="tiempo-restante" style="color: #ef4444; font-weight: 600;">🔴 Transmisión en vivo</div>';
                        }
                        
                        $html_eventos .= '<div class="access-info">';
                        if ($enlace_disponible) {
                            $html_eventos .= '<span class="access-badge access-available">✅ Acceso disponible</span>';
                        } elseif ($tiempo_restante <= 30) {
                            $html_eventos .= '<span class="access-badge access-soon">⏱️ Próximamente</span>';
                        } else {
                            $html_eventos .= '<span class="access-badge access-waiting">🔒 Pendiente</span>';
                        }
                        $html_eventos .= '</div>';
                    } else {
                        $html_eventos .= '<div class="tiempo-restante">📅 ' . formatTiempoRestante($tiempo_restante) . '</div>';
                    }

                    $html_eventos .= '</div></div>';

                    // Equipos
                    $html_eventos .= '<div class="event-teams">';
                    if ($evento['equipo_local'] && $evento['equipo_visitante']) {
                        $html_eventos .= '<div class="team">';
                        $html_eventos .= '<div class="team-flag">' . strtoupper(substr($evento['equipo_local'], 0, 2)) . '</div>';
                        $html_eventos .= '<span>' . htmlspecialchars($evento['equipo_local']) . '</span>';
                        $html_eventos .= '</div>';
                        $html_eventos .= '<div class="vs">vs</div>';
                        $html_eventos .= '<div class="team">';
                        $html_eventos .= '<div class="team-flag">' . strtoupper(substr($evento['equipo_visitante'], 0, 2)) . '</div>';
                        $html_eventos .= '<span>' . htmlspecialchars($evento['equipo_visitante']) . '</span>';
                        $html_eventos .= '</div>';
                    } else {
                        $html_eventos .= '<div class="team"><span>' . htmlspecialchars($evento['titulo']) . '</span></div>';
                    }
                    $html_eventos .= '</div>';

                    // Info adicional
                    $html_eventos .= '<div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">';
                    if ($evento['canal_nombre']) {
                        $html_eventos .= '<div class="event-channel">📺 ' . htmlspecialchars($evento['canal_nombre']) . '</div>';
                    }
                    
                    $html_eventos .= '<span class="event-status status-' . $evento['estado'] . '">';
                    if ($es_live) {
                        $html_eventos .= '🔴 EN VIVO';
                    } else {
                        $estado_icons = ['programado' => '📋', 'en_vivo' => '🔴', 'finalizado' => '✅'];
                        $html_eventos .= ($estado_icons[$evento['estado']] ?? '') . ' ' . Config::getEstadoFormateado($evento['estado']);
                    }
                    $html_eventos .= '</span>';
                    
                    if ($evento['duracion_tipica']) {
                        $html_eventos .= '<small style="color: #6b7280; font-size: 11px;">⏱️ ~' . $evento['duracion_tipica'] . 'min</small>';
                    }
                    $html_eventos .= '</div>';

                    $html_eventos .= '</div>';
                    
                    if ($enlace_disponible) {
                        $html_eventos .= '</a>';
                    } else {
                        $html_eventos .= '</div>';
                    }
                    
                    $html_eventos .= '</div>';
                }

                // Paginación
                $total_paginas_ajax = ceil($total_eventos_ajax / $items_por_pagina);
                $html_paginacion = '';
                
                if ($total_paginas_ajax > 1) {
                    $html_paginacion .= '<div class="pagination">';
                    
                    if ($pagina_ajax > 1) {
                        $html_paginacion .= '<a href="#" onclick="cargarPagina(' . ($pagina_ajax - 1) . ')">← Anterior</a>';
                    } else {
                        $html_paginacion .= '<span class="disabled">← Anterior</span>';
                    }

                    $start = max(1, $pagina_ajax - 2);
                    $end = min($total_paginas_ajax, $pagina_ajax + 2);
                    
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $pagina_ajax) {
                            $html_paginacion .= '<span class="current">' . $i . '</span>';
                        } else {
                            $html_paginacion .= '<a href="#" onclick="cargarPagina(' . $i . ')">' . $i . '</a>';
                        }
                    }

                    if ($pagina_ajax < $total_paginas_ajax) {
                        $html_paginacion .= '<a href="#" onclick="cargarPagina(' . ($pagina_ajax + 1) . ')">Siguiente →</a>';
                    } else {
                        $html_paginacion .= '<span class="disabled">Siguiente →</span>';
                    }
                    
                    $html_paginacion .= '</div>';
                }

                echo json_encode([
                    'success' => true,
                    'html' => $html_eventos,
                    'paginacion' => $html_paginacion,
                    'total' => $total_eventos_ajax,
                    'eventos_en_vivo' => $eventos_en_vivo,
                    'pagina_actual' => $pagina_ajax,
                    'total_paginas' => $total_paginas_ajax
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'obtener_conteos':
            try {
                $stmt_hoy = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) = CURDATE()");
                $stmt_hoy->execute();
                $count_hoy_ajax = $stmt_hoy->fetch()['total'];

                $stmt_manana = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
                $stmt_manana->execute();
                $count_manana_ajax = $stmt_manana->fetch()['total'];

                $stmt_otros = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) > DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha_evento) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                $stmt_otros->execute();
                $count_otros_ajax = $stmt_otros->fetch()['total'];

                echo json_encode([
                    'success' => true,
                    'hoy' => $count_hoy_ajax,
                    'manana' => $count_manana_ajax,
                    'otros' => $count_otros_ajax
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

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

// Conteos para botones
$stmt_hoy = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) = CURDATE()");
$stmt_hoy->execute();
$count_hoy = $stmt_hoy->fetch()['total'];

$stmt_manana = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
$stmt_manana->execute();
$count_manana = $stmt_manana->fetch()['total'];

$stmt_otros = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE DATE(fecha_evento) > DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha_evento) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$stmt_otros->execute();
$count_otros = $stmt_otros->fetch()['total'];

// Cargar eventos iniciales
$pagina = (int)($_GET['pagina'] ?? 1);
$items_por_pagina = Config::ITEMS_PER_PAGE;
$offset = ($pagina - 1) * $items_por_pagina;

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
                            WHEN NOW() >= e.fecha_evento AND NOW() <= DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE)
                            THEN TRUE ELSE FALSE
                         END as en_vivo_calculado,
                         CASE 
                            WHEN TIMESTAMPDIFF(MINUTE, NOW(), e.fecha_evento) <= 15 AND TIMESTAMPDIFF(MINUTE, NOW(), e.fecha_evento) >= -COALESCE(e.duracion_minutos, d.duracion_tipica, 90)
                            THEN TRUE ELSE FALSE
                         END as enlace_activo
                  FROM eventos e
                  LEFT JOIN deportes d ON e.deporte_id = d.id
                  LEFT JOIN competiciones c ON e.competicion_id = c.id
                  LEFT JOIN canales ch ON e.canal_id = ch.id
                  WHERE e.fecha_evento BETWEEN :fecha_inicio AND :fecha_fin";

$params = [
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
];

// Aplicar filtros iniciales
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
    $query_eventos .= " AND (e.titulo LIKE :buscar OR e.descripcion LIKE :buscar OR e.equipo_local LIKE :buscar OR e.equipo_visitante LIKE :buscar)";
    $params[':buscar'] = '%' . $filtros['buscar'] . '%';
}

// Ordenar
if ($filtros['dia'] == 'hoy') {
    $query_eventos .= " ORDER BY 
                        CASE 
                            WHEN NOW() >= e.fecha_evento AND NOW() <= DATE_ADD(e.fecha_evento, INTERVAL COALESCE(e.duracion_minutos, d.duracion_tipica, 90) MINUTE) 
                            THEN 0 
                            ELSE 1 
                        END,
                        e.fecha_evento ASC";
} else {
    $query_eventos .= " ORDER BY e.fecha_evento ASC";
}

// Contar total
$query_count = "SELECT COUNT(*) as total FROM eventos e
                LEFT JOIN deportes d ON e.deporte_id = d.id
                LEFT JOIN competiciones c ON e.competicion_id = c.id
                LEFT JOIN canales ch ON e.canal_id = ch.id
                WHERE e.fecha_evento BETWEEN :fecha_inicio AND :fecha_fin";

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
    $query_count .= " AND (e.titulo LIKE :buscar OR e.descripcion LIKE :buscar OR e.equipo_local LIKE :buscar OR e.equipo_visitante LIKE :buscar)";
}

$stmt_count = $db->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_eventos = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

// Paginación
$query_eventos .= " LIMIT :limite OFFSET :offset";
$stmt = $db->prepare($query_eventos);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limite', (int)$items_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar tokens para eventos que los necesiten
foreach ($eventos as &$evento) {
    if (!$evento['token_acceso'] && $evento['minutos_hasta_evento'] <= 1440) { // 24 horas antes
        $token = generateToken($evento['id']);
        $expira = date('Y-m-d H:i:s', strtotime($evento['fecha_evento'] . ' +3 hours'));
        
        $update_token = "UPDATE eventos SET token_acceso = ?, token_expira = ? WHERE id = ?";
        $stmt_token = $db->prepare($update_token);
        $stmt_token->execute([$token, $expira, $evento['id']]);
        
        $evento['token_acceso'] = $token;
    }
}

$total_paginas = ceil($total_eventos / $items_por_pagina);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SportEvents - <?= $titulo_seccion ?></title>
    <meta name="description" content="Encuentra todos los eventos deportivos por día. Fútbol, baloncesto, tenis y más deportes.">
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

        .admin-link {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
        }

        .admin-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
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
            cursor: pointer;
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

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
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
            position: relative;
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

        .countdown-badge {
            background: #f59e0b;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
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
        }

        .tiempo-restante {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        .access-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .access-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .access-available {
            background: #dcfce7;
            color: #166534;
        }

        .access-soon {
            background: #fef3c7;
            color: #92400e;
        }

        .access-waiting {
            background: #f3f4f6;
            color: #6b7280;
        }

        .event-teams {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 300px;
        }

        .team {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .team-flag {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .vs {
            color: #9ca3af;
            font-weight: 400;
            font-size: 14px;
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

        /* Estilos AJAX */
        .loading-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6366f1;
            font-size: 14px;
            font-weight: 500;
            margin-top: 5px;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 12px;
        }

        .loading-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            color: #6366f1;
            font-weight: 500;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .event-card-wrapper {
            transition: all 0.3s ease;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .search-active {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-color: #0ea5e9 !important;
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
            .event-teams { justify-content: center; min-width: auto; }
            .events-header { flex-direction: column; align-items: stretch; gap: 10px; }
        }

        @media (max-width: 480px) {
            .header-left h1 { font-size: 1.8rem; }
            .event-teams { flex-direction: column; gap: 10px; }
            .filter-actions { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>🏆 SportEvents</h1>
                    <div class="subtitle"><?= $titulo_seccion ?> - <?= $subtitulo ?></div>
                </div>
                <a href="admin.php" class="admin-link">⚙️ Panel Admin</a>
            </div>
        </div>

        <!-- Selector de Día -->
        <div class="day-selector">
            <div class="day-buttons">
                <div class="day-btn <?= $filtros['dia'] == 'hoy' ? 'active' : '' ?>" onclick="cambiarDia('hoy')">
                    📅 Hoy
                    <span class="count"><?= $count_hoy ?></span>
                </div>
                
                <div class="day-btn <?= $filtros['dia'] == 'manana' ? 'active' : '' ?>" onclick="cambiarDia('manana')">
                    🌅 Mañana
                    <span class="count"><?= $count_manana ?></span>
                </div>
                
                <div class="day-btn <?= $filtros['dia'] == 'otros' ? 'active' : '' ?>" onclick="cambiarDia('otros')">
                    📆 Próximos
                    <span class="count"><?= $count_otros ?></span>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters">
            <div id="filtros-form">
                <input type="hidden" id="dia-actual" value="<?= $filtros['dia'] ?>">
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="buscar">🔍 Buscar eventos</label>
                        <input type="text" 
                               id="buscar" 
                               name="buscar" 
                               class="search-input" 
                               placeholder="Buscar equipos, eventos..." 
                               value="<?= htmlspecialchars($filtros['buscar']) ?>"
                               autocomplete="off">
                        <div id="search-loading" class="loading-indicator" style="display: none;">
                            <span>🔍 Buscando...</span>
                        </div>
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
                    <button type="button" id="limpiar-filtros" class="btn btn-secondary">🔄 Limpiar</button>
                    <div id="filter-loading" class="loading-indicator" style="display: none;">
                        <span>⏳ Filtrando...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Eventos -->
        <div class="events-section">
            <div class="events-header">
                <div>
                    <h2 id="titulo-seccion">
                        <?php if ($filtros['dia'] == 'hoy'): ?>
                            📅 <?= $titulo_seccion ?>
                        <?php elseif ($filtros['dia'] == 'manana'): ?>
                            🌅 <?= $titulo_seccion ?>
                        <?php else: ?>
                            📆 <?= $titulo_seccion ?>
                        <?php endif; ?>
                    </h2>
                    <div class="subtitle"><?= $subtitulo ?></div>
                </div>
                <div class="events-count" id="events-count">
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

            <div id="loading-overlay" class="loading-overlay" style="display: none;">
                <div class="loading-content">
                    <div class="loading-spinner"></div>
                    <span>Cargando eventos...</span>
                </div>
            </div>

            <div id="eventos-container">
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
                    <div class="events-list" id="events-list">
                        <?php foreach ($eventos as $evento): ?>
                        <?php 
                            $enlace_disponible = $evento['enlace_activo'] || $evento['en_vivo_calculado'];
                            $es_live = $evento['en_vivo_calculado'];
                            $tiempo_restante = $evento['minutos_hasta_evento'];
                            $clase_tiempo = $es_live ? 'live' : ($tiempo_restante <= 15 && $tiempo_restante > 0 ? 'soon' : '');
                            $clase_card = $es_live ? 'live-event' : '';
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
                                                    <span class="countdown-badge"><?= $tiempo_restante ?>min</span>
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
                                                
                                                <div class="access-info">
                                                    <?php if ($enlace_disponible): ?>
                                                        <span class="access-badge access-available">✅ Acceso disponible</span>
                                                    <?php elseif ($tiempo_restante <= 30): ?>
                                                        <span class="access-badge access-soon">⏱️ Próximamente</span>
                                                    <?php else: ?>
                                                        <span class="access-badge access-waiting">🔒 Pendiente</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="tiempo-restante">
                                                    📅 <?= formatTiempoRestante($tiempo_restante) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="event-teams">
                                        <?php if ($evento['equipo_local'] && $evento['equipo_visitante']): ?>
                                            <div class="team">
                                                <div class="team-flag"><?= strtoupper(substr($evento['equipo_local'], 0, 2)) ?></div>
                                                <span><?= htmlspecialchars($evento['equipo_local']) ?></span>
                                            </div>
                                            <div class="vs">vs</div>
                                            <div class="team">
                                                <div class="team-flag"><?= strtoupper(substr($evento['equipo_visitante'], 0, 2)) ?></div>
                                                <span><?= htmlspecialchars($evento['equipo_visitante']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="team">
                                                <span><?= htmlspecialchars($evento['titulo']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                        <?php if ($evento['canal_nombre']): ?>
                                            <div class="event-channel">📺 <?= htmlspecialchars($evento['canal_nombre']) ?></div>
                                        <?php endif; ?>
                                        
                                        <span class="event-status status-<?= $evento['estado'] ?>">
                                            <?php if ($es_live): ?>
                                                🔴 EN VIVO
                                            <?php else: ?>
                                                <?php 
                                                $estado_icons = [
                                                    'programado' => '📋',
                                                    'en_vivo' => '🔴',
                                                    'finalizado' => '✅'
                                                ];
                                                echo ($estado_icons[$evento['estado']] ?? '') . ' ' . Config::getEstadoFormateado($evento['estado']);
                                                ?>
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
                    <div id="paginacion-container">
                        <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php if ($pagina > 1): ?>
                                <a href="#" onclick="cargarPagina(<?= $pagina - 1 ?>)">← Anterior</a>
                            <?php else: ?>
                                <span class="disabled">← Anterior</span>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $pagina - 2);
                            $end = min($total_paginas, $pagina + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <?php if ($i == $pagina): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="#" onclick="cargarPagina(<?= $i ?>)"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($pagina < $total_paginas): ?>
                                <a href="#" onclick="cargarPagina(<?= $pagina + 1 ?>)">Siguiente →</a>
                            <?php else: ?>
                                <span class="disabled">Siguiente →</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let searchTimeout;
        let filterTimeout;
        let currentPage = 1;
        let isLoading = false;

        // Función principal para filtrar eventos
        async function filtrarEventos(pagina = 1, mostrarCarga = true) {
            if (isLoading) return;
            
            isLoading = true;
            
            const diaActual = document.getElementById('dia-actual').value;
            const filtros = {
                action: 'filtrar_eventos',
                dia: diaActual,
                deporte: document.getElementById('deporte').value,
                competicion: document.getElementById('competicion').value,
                canal: document.getElementById('canal').value,
                buscar: document.getElementById('buscar').value,
                pagina: pagina
            };

            if (mostrarCarga) {
                mostrarCargando(true);
            }

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(filtros)
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('events-list').innerHTML = result.html;
                    document.getElementById('paginacion-container').innerHTML = result.paginacion;
                    
                    let contadorTexto = result.total + ' evento' + (result.total != 1 ? 's' : '');
                    if (result.eventos_en_vivo > 0) {
                        contadorTexto += '<span class="live-count">🔴 ' + result.eventos_en_vivo + ' EN VIVO</span>';
                    }
                    document.getElementById('events-count').innerHTML = contadorTexto;

                    currentPage = result.pagina_actual;

                    const eventCards = document.querySelectorAll('.event-card-wrapper');
                    eventCards.forEach((card, index) => {
                        card.classList.add('fade-in');
                        card.style.animationDelay = `${index * 0.1}s`;
                    });

                    inicializarEventosCards();

                    if (result.total === 0) {
                        mostrarMensajeVacio();
                    }

                    document.querySelector('.events-section').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }
            } catch (error) {
                console.error('Error al filtrar eventos:', error);
                mostrarError('Error al cargar eventos. Inténtalo de nuevo.');
            } finally {
                isLoading = false;
                if (mostrarCarga) {
                    mostrarCargando(false);
                }
            }
        }

        function mostrarCargando(mostrar) {
            const overlay = document.getElementById('loading-overlay');
            if (mostrar) {
                overlay.style.display = 'flex';
            } else {
                overlay.style.display = 'none';
            }
        }

        function cargarPagina(pagina) {
            event.preventDefault();
            filtrarEventos(pagina);
        }

        function mostrarMensajeVacio() {
            const diaActual = document.getElementById('dia-actual').value;
            let icono = '📅';
            let mensaje = 'próximos';
            
            if (diaActual === 'hoy') {
                icono = '😴';
                mensaje = 'hoy';
            } else if (diaActual === 'manana') {
                icono = '🌙';
                mensaje = 'mañana';
            }

            const html = `
                <div class="no-events fade-in">
                    <div class="no-events-icon">${icono}</div>
                    <h3>No hay eventos ${mensaje}</h3>
                    <p>No se encontraron eventos con los filtros aplicados.</p>
                    <p>Prueba ajustando los filtros o revisa en otro día.</p>
                </div>
            `;
            
            document.getElementById('events-list').innerHTML = html;
            document.getElementById('paginacion-container').innerHTML = '';
        }

        function mostrarError(mensaje) {
            const html = `
                <div class="no-events fade-in">
                    <div class="no-events-icon">⚠️</div>
                    <h3>Error al cargar</h3>
                    <p>${mensaje}</p>
                </div>
            `;
            
            document.getElementById('events-list').innerHTML = html;
            document.getElementById('paginacion-container').innerHTML = '';
        }

        async function cargarCompeticionesFiltro(deporteId) {
            const competicionSelect = document.getElementById('competicion');
            
            competicionSelect.innerHTML = '<option value="">Cargando...</option>';
            competicionSelect.disabled = true;
            
            if (!deporteId) {
                competicionSelect.innerHTML = '<option value="">Todas las competiciones</option>';
                competicionSelect.disabled = false;
                return;
            }
            
            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=obtener_competiciones&deporte_id=${deporteId}`
                });
                
                const result = await response.json();
                
                competicionSelect.innerHTML = '<option value="">Todas las competiciones</option>';
                
                if (result.success) {
                    result.competiciones.forEach(comp => {
                        const option = document.createElement('option');
                        option.value = comp.id;
                        option.textContent = comp.nombre;
                        competicionSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error al cargar competiciones:', error);
            } finally {
                competicionSelect.disabled = false;
            }
        }

        function limpiarFiltros() {
            document.getElementById('buscar').value = '';
            document.getElementById('deporte').value = '';
            document.getElementById('competicion').value = '';
            document.getElementById('canal').value = '';
            
            document.getElementById('competicion').innerHTML = '<option value="">Todas las competiciones</option>';
            
            filtrarEventos(1);
        }

        function inicializarEventosCards() {
            const enlacesDisponibles = document.querySelectorAll('.event-link:not(.disabled)');
            enlacesDisponibles.forEach(enlace => {
                enlace.addEventListener('mouseenter', function() {
                    const card = this.querySelector('.event-card');
                    card.style.transform = 'translateY(-4px)';
                });
                
                enlace.addEventListener('mouseleave', function() {
                    const card = this.querySelector('.event-card');
                    card.style.transform = 'translateY(0)';
                });
            });

            const enlacesDeshabilitados = document.querySelectorAll('.event-link.disabled');
            enlacesDeshabilitados.forEach(enlace => {
                enlace.addEventListener('click', function(e) {
                    e.preventDefault();
                    const card = this.querySelector('.event-card');
                    const titulo = card.querySelector('.event-title').textContent;
                    alert(`📅 El evento "${titulo}" estará disponible 15 minutos antes de su inicio.`);
                });
            });
        }

        function cambiarDia(nuevoDia) {
            document.getElementById('dia-actual').value = nuevoDia;
            
            // Actualizar botones activos
            document.querySelectorAll('.day-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[onclick="cambiarDia('${nuevoDia}')"]`).classList.add('active');
            
            // Actualizar título
            const titulos = {
                'hoy': '📅 Eventos de Hoy',
                'manana': '🌅 Eventos de Mañana',
                'otros': '📆 Próximos Eventos'
            };
            document.getElementById('titulo-seccion').innerHTML = titulos[nuevoDia];
            
            filtrarEventos(1);
        }

        async function actualizarConteosBotones() {
            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=obtener_conteos'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.querySelector('.day-btn[onclick="cambiarDia(\'hoy\')"] .count').textContent = data.hoy;
                    document.querySelector('.day-btn[onclick="cambiarDia(\'manana\')"] .count').textContent = data.manana;
                    document.querySelector('.day-btn[onclick="cambiarDia(\'otros\')"] .count').textContent = data.otros;
                }
            } catch (error) {
                console.error('Error al actualizar conteos:', error);
            }
        }

        function actualizarTiempoReal() {
            const diaActual = document.getElementById('dia-actual').value;
            
            if (diaActual === 'hoy') {
                const countdownBadges = document.querySelectorAll('.countdown-badge');
                let debeRecargar = false;
                
                countdownBadges.forEach(badge => {
                    let minutos = parseInt(badge.textContent);
                    if (minutos > 0) {
                        minutos--;
                        badge.textContent = minutos + 'min';
                        
                        if (minutos === 0) {
                            debeRecargar = true;
                        }
                    }
                });
                
                if (debeRecargar) {
                    filtrarEventos(currentPage, false);
                }
            }
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('buscar');
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                if (this.value.length > 0) {
                    this.classList.add('search-active');
                    document.getElementById('search-loading').style.display = 'flex';
                } else {
                    this.classList.remove('search-active');
                    document.getElementById('search-loading').style.display = 'none';
                }
                
                searchTimeout = setTimeout(() => {
                    document.getElementById('search-loading').style.display = 'none';
                    filtrarEventos(1, false);
                }, 500);
            });

            const filtros = ['deporte', 'competicion', 'canal'];
            filtros.forEach(filtro => {
                document.getElementById(filtro).addEventListener('change', function() {
                    clearTimeout(filterTimeout);
                    
                    document.getElementById('filter-loading').style.display = 'flex';
                    
                    filterTimeout = setTimeout(() => {
                        document.getElementById('filter-loading').style.display = 'none';
                        
                        if (filtro === 'deporte') {
                            cargarCompeticionesFiltro(this.value).then(() => {
                                filtrarEventos(1, false);
                            });
                        } else {
                            filtrarEventos(1, false);
                        }
                    }, 300);
                });
            });

            document.getElementById('limpiar-filtros').addEventListener('click', limpiarFiltros);

            inicializarEventosCards();

            const deporteSelect = document.getElementById('deporte');
            if (deporteSelect.value) {
                cargarCompeticionesFiltro(deporteSelect.value);
            }

            const diaActual = document.getElementById('dia-actual').value;
            
            if (diaActual === 'hoy') {
                setInterval(actualizarTiempoReal, 60000);
                
                setInterval(() => {
                    const eventosEnVivo = document.querySelectorAll('.event-time.live').length;
                    const eventosProximos = document.querySelectorAll('.countdown-badge').length;
                    
                    if (eventosEnVivo > 0 || eventosProximos > 0) {
                        filtrarEventos(currentPage, false);
                    }
                }, 120000);
            }

            setInterval(actualizarConteosBotones, 300000);

            if (diaActual === 'hoy' && 'Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        });
    </script>
</body>
</html>
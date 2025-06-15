<!-- Alertas -->
            <div id="alert-success" class="alert alert-success"></div>
            <div id="alert-error" class="alert alert-error"></div>

            <!-- Botón para eliminar eventos pasados -->
            <?php if ($eventos_pasados > 0): ?>
            <div style="margin-bottom: 20px;">
                <button class="btn btn-danger" onclick="eliminarEventosPasados()">
                    <span class="material-icons">delete_sweep</span>
                    Eliminar <?= $eventos_pasados ?> Eventos Pasados (más de 24h)
                </button>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="admin-tabs">
                <button class="admin-tab active" onclick="showTab('eventos')">
                    <span class="material-icons">event</span>
                    Eventos
                </button>
                <button class="admin-tab" onclick="showTab('deportes')">
                    <span class="material-icons">sports_soccer</span>
                    Deportes
                </button>
                <button class="admin-tab" onclick="showTab('canales')">
                    <span class="material-icons">tv</span>
                    Canales
                </button>
                <button class="admin-tab" onclick="showTab('competiciones')">
                    <span class="material-icons">emoji_events</span>
                    Competiciones
                </button>
            </div>

            <!-- Tab Eventos -->
            <div id="tab-eventos" class="tab-content active">
                <div class="form-section">
                    <h3>Gestionar Eventos</h3>
                    <form id="eventoForm" onsubmit="return submitEventoForm(event)">
                        <input type="hidden" id="evento_id" name="id">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Título del Evento *</label>
                                <input type="text" name="titulo" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label>Fecha y Hora *</label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="datetime-local" name="fecha_evento" class="form-input" required style="flex: 1;">
                                    <div class="quick-time-buttons" style="display: flex; gap: 5px;">
                                        <button type="button" class="quick-time-btn" onclick="ajustarHora('fecha_evento', -60)" title="Restar 1 hora">-1h</button>
                                        <button type="button" class="quick-time-btn" onclick="ajustarHora('fecha_evento', -30)" title="Restar 30 min">-30m</button>
                                        <button type="button" class="quick-time-btn" onclick="ajustarHora('fecha_evento', 30)" title="Sumar 30 min">+30m</button>
                                        <button type="button" class="quick-time-btn" onclick="ajustarHora('fecha_evento', 60)" title="Sumar 1 hora">+1h</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Deporte *</label>
                                <select name="deporte_id" class="form-select" onchange="cargarCompeticiones(this.value)" required>
                                    <option value="">Seleccionar deporte</option>
                                    <?php foreach ($deportes as $deporte): ?>
                                        <option value="<?= $deporte['id'] ?>" data-duracion="<?= $deporte['duracion_tipica'] ?? 90 ?>">
                                            <?= htmlspecialchars($deporte['nombre']) ?>
                                            <?php if ($deporte['duracion_tipica']): ?>
                                                (<?= $deporte['duracion_tipica'] ?> min)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Competición</label>
                                <select name="competicion_id" class="form-select" id="competicion_select">
                                    <option value="">Seleccionar competición</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Canal *</label>
                                <select name="canal_id" class="form-select" required>
                                    <option value="">Seleccionar canal</option>
                                    <?php foreach ($canales as $canal): ?>
                                        <option value="<?= $canal['id'] ?>"><?= htmlspecialchars($canal['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="programado">Programado</option>
                                    <option value="en_vivo">En Vivo</option>
                                    <option value="finalizado">Finalizado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Duración (minutos)</label>
                                <div class="duration-input">
                                    <input type="number" name="duracion_minutos" class="form-input" placeholder="Usar duración del deporte" min="1" max="1440">
                                </div>
                                <div class="duration-presets">
                                    <button type="button" class="duration-preset" onclick="setDuracion(30)">30m</button>
                                    <button type="button" class="duration-preset" onclick="setDuracion(60)">1h</button>
                                    <button type="button" class="duration-preset" onclick="setDuracion(90)">90m</button>
                                    <button type="button" class="duration-preset" onclick="setDuracion(120)">2h</button>
                                    <button type="button" class="duration-preset" onclick="setDuracion(180)">3h</button>
                                    <button type="button" class="duration-preset" onclick="setDuracion(1440)">24h</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Equipo Local</label>
                                <input type="text" name="equipo_local" class="form-input" placeholder="Nombre del equipo local">
                            </div>
                            <div class="form-group">
                                <label>Equipo Visitante</label>
                                <input type="text" name="equipo_visitante" class="form-input" placeholder="Nombre del equipo visitante">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea name="descripcion" class="form-textarea" placeholder="Descripción del evento..."></textarea>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="destacado" id="destacado">
                            <label for="destacado">Evento destacado</label>
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn">
                                <span class="material-icons">save</span>
                                Guardar Evento
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelarEdicion()" style="margin-left: 10px; display: none;" id="cancelBtn">
                                <span class="material-icons">cancel</span>
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Filtros de eventos -->
                <div class="filter-bar">
                    <select id="filtro-estado" onchange="filtrarEventos()">
                        <option value="">Todos los estados</option>
                        <option value="programado">Programados</option>
                        <option value="en_vivo">En Vivo</option>
                        <option value="finalizado">Finalizados</option>
                        <option value="cancelado">Cancelados</option>
                    </select>
                    
                    <select id="filtro-fecha" onchange="filtrarEventos()">
                        <option value="">Todas las fechas</option>
                        <option value="hoy">Hoy</option>
                        <option value="manana">Mañana</option>
                        <option value="pasados">Pasados</option>
                    </select>
                    
                    <select id="filtro-deporte" onchange="filtrarEventos()">
                        <option value="">Todos los deportes</option>
                        <?php foreach ($deportes as $deporte): ?>
                            <option value="<?= $deporte['id'] ?>"><?= htmlspecialchars($deporte['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button class="btn btn-sm" onclick="location.reload()">
                        <span class="material-icons">refresh</span>
                        Recargar
                    </button>
                </div>

                <!-- Acciones en lote -->
                <div class="batch-actions" id="batch-actions">
                    <h4>Acciones en lote (<span id="selected-count">0</span> seleccionados)</h4>
                    <div class="time-adjuster">
                        <label>Ajustar hora:</label>
                        <input type="number" id="minutos-cambio" value="30" min="1" max="1440">
                        <span>minutos</span>
                        <button class="btn btn-sm" onclick="cambiarHoraMasivo('sumar')">
                            <span class="material-icons">add</span>
                            Sumar
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="cambiarHoraMasivo('restar')">
                            <span class="material-icons">remove</span>
                            Restar
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarSeleccionados()">
                            <span class="material-icons">delete</span>
                            Eliminar
                        </button>
                    </div>
                </div>

                <!-- Lista de Eventos -->
                <div>
                    <h3 style="margin-bottom: 20px;">Lista de Eventos</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" class="select-all-checkbox" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th>Título</th>
                                    <th>Fecha/Hora</th>
                                    <th>Deporte</th>
                                    <th>Canal</th>
                                    <th>Duración</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="eventos-tbody">
                                <?php foreach ($eventos as $evento): ?>
                                <tr data-id="<?= $evento['id'] ?>">
                                    <td>
                                        <input type="checkbox" class="event-checkbox" value="<?= $evento['id'] ?>" onchange="updateSelectedCount()">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($evento['titulo']) ?></strong>
                                        <?php if ($evento['destacado']): ?>
                                            <span class="material-icons" style="color: #ffc107; font-size: 16px; vertical-align: middle;">star</span>
                                        <?php endif; ?>
                                        <?php if ($evento['equipo_local'] && $evento['equipo_visitante']): ?>
                                            <br><small><?= htmlspecialchars($evento['equipo_local']) ?> vs <?= htmlspecialchars($evento['equipo_visitante']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($evento['fecha_evento'])) ?>
                                        <div class="quick-time-buttons" style="margin-top: 5px;">
                                            <button class="quick-time-btn" onclick="ajustarHoraEvento(<?= $evento['id'] ?>, -30)" title="Restar 30 min">-30m</button>
                                            <button class="quick-time-btn" onclick="ajustarHoraEvento(<?= $evento['id'] ?>, 30)" title="Sumar 30 min">+30m</button>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($evento['deporte_nombre'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($evento['canal_nombre'] ?? 'N/A') ?></td>
                                    <td><?= $evento['duracion_minutos'] ?? 'Por defecto' ?> min</td>
                                    <td><span class="badge badge-<?= $evento['estado'] ?>"><?= ucfirst($evento['estado']) ?></span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm" onclick="editarEvento(<?= $evento['id'] ?>)" title="Editar">
                                                <span class="material-icons">edit</span>
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="duplicarEvento(<?= $evento['id'] ?>)" title="Duplicar">
                                                <span class="material-icons">content_copy</span>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarEvento(<?= $evento['id'] ?>)" title="Eliminar">
                                                <span class="material-icons">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Deportes -->
            <div id="tab-deportes" class="tab-content">
                <div class="form-section">
                    <h3>Agregar Nuevo Deporte</h3>
                    <form id="deporteForm" onsubmit="return submitDeporteForm(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nombre del Deporte *</label>
                                <input type="text" name="nombre" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label>Icono (Material Icons)</label>
                                <input type="text" name="icono" class="form-input" placeholder="sports_soccer" value="sports_soccer">
                            </div>
                            <div class="form-group">
                                <label>Duración Típica (minutos)</label>
                                <input type="number" name="duracion_tipica" class="form-input" placeholder="90" min="1" max="1440">
                            </div>
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-icons">add</span>
                            Agregar Deporte
                        </button>
                    </form>
                </div>

                <div>
                    <h3 style="margin-bottom: 20px;">Lista de Deportes</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Icono</th>
                                    <th>Nombre</th>
                                    <th>Duración Típica</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deportes as $deporte): ?>
                                <tr>
                                    <td><span class="material-icons"><?= htmlspecialchars($deporte['icono']) ?></span></td>
                                    <td><?= htmlspecialchars($deporte['nombre']) ?></td>
                                    <td><?= $deporte['duracion_tipica'] ?? 'No definida' ?> min</td>
                                    <td><?= date('d/m/Y', strtotime($deporte['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarDeporte(<?= $deporte['id'] ?>)">
                                            <span class="material-icons">delete</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Canales -->
            <div id="tab-canales" class="tab-content">
                <div class="form-section">
                    <h3>Agregar Nuevo Canal</h3>
                    <form id="canalForm" onsubmit="return submitCanalForm(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nombre del Canal *</label>
                                <input type="text" name="nombre" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label>Logo (URL)</label>
                                <input type="url" name="logo" class="form-input" placeholder="https://ejemplo.com/logo.png">
                            </div>
                            <div class="form-group">
                                <label>URL del Canal</label>
                                <input type="url" name="url_canal" class="form-input" placeholder="https://canal.com">
                            </div>
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-icons">add</span>
                            Agregar Canal
                        </button>
                    </form>
                </div>

                <div>
                    <h3 style="margin-bottom: 20px;">Lista de Canales</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>URL</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($canales as $canal): ?>
                                <tr>
                                    <td><?= htmlspecialchars($canal['nombre']) ?></td>
                                    <td>
                                        <?php if ($canal['url_canal']): ?>
                                            <a href="<?= htmlspecialchars($canal['url_canal']) ?>" target="_blank" style="color: #667eea;">
                                                <?= htmlspecialchars($canal['url_canal']) ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($canal['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarCanal(<?= $canal['id'] ?>)">
                                            <span class="material-icons">delete</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab Competiciones -->
            <div id="tab-competiciones" class="tab-content">
                <div class="form-section">
                    <h3>Agregar Nueva Competición</h3>
                    <form id="competicionForm" onsubmit="return submitCompeticionForm(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nombre de la Competición *</label>
                                <input type="text" name="nombre" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label>Deporte *</label>
                                <select name="deporte_id" class="form-select" required>
                                    <option value="">Seleccionar deporte</option>
                                    <?php foreach ($deportes as $deporte): ?>
                                        <option value="<?= $deporte['id'] ?>"><?= htmlspecialchars($deporte['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>País/Región</label>
                                <input type="text" name="pais" class="form-input" placeholder="España, Europa, Mundial...">
                            </div>
                            <div class="form-group">
                                <label>Temporada</label>
                                <input type="text" name="temporada" class="form-input" placeholder="2024-25, 2025...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea name="descripcion" class="form-textarea" placeholder="Descripción de la competición..."></textarea>
                        </div>
                        <button type="submit" class="btn">
                            <span class="material-icons">add</span>
                            Agregar Competición
                        </button>
                    </form>
                </div>

                <div>
                    <h3 style="margin-bottom: 20px;">Lista de Competiciones</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Deporte</th>
                                    <th>País/Región</th>
                                    <th>Temporada</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($competiciones as $competicion): ?>
                                <tr>
                                    <td><?= htmlspecialchars($competicion['nombre']) ?></td>
                                    <td><?= htmlspecialchars($competicion['deporte_nombre'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($competicion['pais'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($competicion['temporada'] ?: 'N/A') ?></td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarCompeticion(<?= $competicion['id'] ?>)">
                                            <span class="material-icons">delete</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let editingEventId = null;
        let selectedEvents = [];

        // Funciones de navegación
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.admin-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Funciones de alertas
        function showAlert(message, type = 'success') {
            const alertElement = document.getElementById('alert-' + type);
            alertElement.textContent = message;
            alertElement.style.display = 'block';
            
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }

        // Funciones AJAX
        async function makeRequest(data) {
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                });
                
                return await response.json();
            } catch (error) {
                console.error('Error:', error);
                return { success: false, message: 'Error de conexión' };
            }
        }

        // Funciones de tiempo
        function ajustarHora(inputName, minutos) {
            const input = document.querySelector(`[name="${inputName}"]`);
            if (!input.value) return;
            
            const fecha = new Date(input.value);
            fecha.setMinutes(fecha.getMinutes() + minutos);
            
            const year = fecha.getFullYear();
            const month = String(fecha.getMonth() + 1).padStart(2, '0');
            const day = String(fecha.getDate()).padStart(2, '0');
            const hours = String(fecha.getHours()).padStart(2, '0');
            const minutes = String(fecha.getMinutes()).padStart(2, '0');
            
            input.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        async function ajustarHoraEvento(id, minutos) {
            const result = await makeRequest({
                action: 'cambiar_hora_masivo',
                ids: id.toString(),
                minutos_cambio: Math.abs(minutos),
                operacion: minutos < 0 ? 'restar' : 'sumar'
            });
            
            if (result.success) {
                showAlert('Hora actualizada', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(result.message, 'error');
            }
        }

        function setDuracion(minutos) {
            document.querySelector('[name="duracion_minutos"]').value = minutos;
        }

        // Eliminar eventos pasados
        async function eliminarEventosPasados() {
            if (!confirm('¿Estás seguro de que quieres eliminar TODOS los eventos pasados de más de 24 horas?')) {
                return;
            }
            
            const result = await makeRequest({ action: 'eliminar_eventos_pasados' });
            
            if (result.success) {
                showAlert(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message, 'error');
            }
        }

        // Gestión de selección múltiple
        function toggleSelectAll(checkbox) {
            document.querySelectorAll('.event-checkbox').forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            selectedEvents = Array.from(document.querySelectorAll('.event-checkbox:checked')).map(cb => cb.value);
            document.getElementById('selected-count').textContent = selectedEvents.length;
            
            if (selectedEvents.length > 0) {
                document.getElementById('batch-actions').style.display = 'block';
            } else {
                document.getElementById('batch-actions').style.display = 'none';
            }
            
            // Marcar filas seleccionadas
            document.querySelectorAll('#eventos-tbody tr').forEach(tr => {
                const checkbox = tr.querySelector('.event-checkbox');
                if (checkbox && checkbox.checked) {
                    tr.classList.add('event-row-selected');
                } else {
                    tr.classList.remove('event-row-selected');
                }
            });
        }

        async function cambiarHoraMasivo(operacion) {
            if (selectedEvents.length === 0) {
                showAlert('No hay eventos seleccionados', 'error');
                return;
            }
            
            const minutos = document.getElementById('minutos-cambio').value;
            
            const result = await makeRequest({
                action: 'cambiar_hora_masivo',
                ids: selectedEvents.join(','),
                minutos_cambio: minutos,
                operacion: operacion
            });
            
            if (result.success) {
                showAlert(result.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(result.message, 'error');
            }
        }

        async function eliminarSeleccionados() {
            if (selectedEvents.length === 0) {
                showAlert('No hay eventos seleccionados', 'error');
                return;
            }
            
            if (!confirm(`¿Estás seguro de que quieres eliminar ${selectedEvents.length} eventos?`)) {
                return;
            }
            
            let deleted = 0;
            for (const id of selectedEvents) {
                const result = await makeRequest({ action: 'eliminar_evento', id: id });
                if (result.success) deleted++;
            }
            
            showAlert(`Se eliminaron ${deleted} eventos`, 'success');
            setTimeout(() => location.reload(), 1500);
        }

        // Duplicar evento
        async function duplicarEvento(id) {
            const nuevaFecha = prompt('Fecha y hora del evento duplicado (YYYY-MM-DD HH:MM):', 
                new Date().toISOString().slice(0, 16).replace('T', ' '));
            
            if (!nuevaFecha) return;
            
            const result = await makeRequest({
                action: 'duplicar_evento',
                id: id,
                nueva_fecha: nuevaFecha
            });
            
            if (result.success) {
                showAlert(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message, 'error');
            }
        }

        // Gestión de Eventos
        async function submitEventoForm(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            if (editingEventId) {
                data.action = 'actualizar_evento';
                data.id = editingEventId;
            } else {
                data.action = 'crear_evento';
            }
            
            const result = await makeRequest(data);
            
            if (result.success) {
                showAlert(result.message, 'success');
                event.target.reset();
                cancelarEdicion();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message, 'error');
            }
            
            return false;
        }

        async function editarEvento(id) {
            const result = await makeRequest({ action: 'obtener_evento', id: id });
            
            if (result.success && result.evento) {
                const evento = result.evento;
                editingEventId = id;
                
                // Llenar el formulario
                document.querySelector('[name="titulo"]').value = evento.titulo || '';
                document.querySelector('[name="descripcion"]').value = evento.descripcion || '';
                document.querySelector('[name="fecha_evento"]').value = evento.fecha_evento ? evento.fecha_evento.slice(0, 16) : '';
                document.querySelector('[name="deporte_id"]').value = evento.deporte_id || '';
                document.querySelector('[name="canal_id"]').value = evento.canal_id || '';
                document.querySelector('[name="estado"]').value = evento.estado || 'programado';
                document.querySelector('[name="equipo_local"]').value = evento.equipo_local || '';
                document.querySelector('[name="equipo_visitante"]').value = evento.equipo_visitante || '';
                document.querySelector('[name="destacado"]').checked = evento.destacado == 1;
                document.querySelector('[name="duracion_minutos"]').value = evento.duracion_minutos || '';
                
                // Cargar competiciones si hay deporte seleccionado
                if (evento.deporte_id) {
                    await cargarCompeticiones(evento.deporte_id);
                    document.querySelector('[name="competicion_id"]').value = evento.competicion_id || '';
                }
                
                // Mostrar botón cancelar
                document.getElementById('cancelBtn').style.display = 'inline-flex';
                
                // Cambiar texto del botón
                document.querySelector('#eventoForm button[type="submit"]').innerHTML = 
                    '<span class="material-icons">save</span>Actualizar Evento';
                
                // Scroll al formulario
                document.getElementById('tab-eventos').scrollIntoView({ behavior: 'smooth' });
            }
        }

        function cancelarEdicion() {
            editingEventId = null;
            document.getElementById('eventoForm').reset();
            document.getElementById('cancelBtn').style.display = 'none';
            document.querySelector('#eventoForm button[type="submit"]').innerHTML = 
                '<span class="material-icons">save</span>Guardar Evento';
        }

        async function eliminarEvento(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este evento?')) {
                const result = await makeRequest({ action: 'eliminar_evento', id: id });
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            }
        }

        // Gestión de competiciones por deporte
        async function cargarCompeticiones(deporteId) {
            if (!deporteId) {
                document.getElementById('competicion_select').innerHTML = '<option value="">Seleccionar competición</option>';
                return;
            }
            
            const result = await makeRequest({ action: 'obtener_competiciones', deporte_id: deporteId });
            
            if (result.success) {
                const select = document.getElementById('competicion_select');
                select.innerHTML = '<option value="">Seleccionar competición</option>';
                
                result.competiciones.forEach(comp => {
                    const option = document.createElement('option');
                    option.value = comp.id;
                    option.textContent = comp.nombre;
                    select.appendChild(option);
                });
            }
        }

        // Filtrar eventos
        function filtrarEventos() {
            const estado = document.getElementById('filtro-estado').value;
            const fecha = document.getElementById('filtro-fecha').value;
            const deporte = document.getElementById('filtro-deporte').value;
            
            const params = new URLSearchParams();
            if (estado) params.append('estado', estado);
            if (fecha) params.append('fecha', fecha);
            if (deporte) params.append('deporte', deporte);
            
            window.location.href = 'admin.php?' + params.toString();
        }

        // Gestión de Deportes
        async function submitDeporteForm(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            data.action = 'crear_deporte';
            
            const result = await makeRequest(data);
            
            if (result.success) {
                showAlert(result.message, 'success');
                event.target.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message, 'error');
            }
            
            return false;
        }

        async function eliminarDeporte(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este deporte? Esto puede afectar eventos existentes.')) {
                const result = await makeRequest({ action: 'eliminar_deporte', id: id });
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            }
        }

        // Gestión de Canales
        async function submitCanalForm(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            data.action = 'crear_canal';
            
            const result = await makeRequest(data);
            
            if (result.success) {
                showAlert(result.message, 'success');
                event.target.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message, 'error');
            }
            
            return false;
        }

        async function eliminarCanal(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este canal?')) {
                const result = await makeRequest({ action: 'eliminar_canal', id: id });
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            }
        }

        // Gestión de Competiciones
        async function submitCompeticionForm(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            data.action = 'crear_competicion';
            
            const result = await makeRequest(data);
            
            if (result.success) {
                showAlert(result.message, 'success');
                event.target.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message, 'error');
            }
            
            return false;
        }

        async function eliminarCompeticion(id) {
            if (confirm('¿Estás seguro de que quieres eliminar esta competición?')) {
                const result = await makeRequest({ action: 'eliminar_competicion', id: id });
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            }
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl+S para guardar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const activeForm = document.querySelector('.tab-content.active form');
                if (activeForm) {
                    activeForm.dispatchEvent(new Event('submit'));
                }
            }
            
            // Esc para cancelar edición
            if (e.key === 'Escape' && editingEventId) {
                cancelarEdicion();
            }
        });

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar fecha mínima para nuevos eventos
            const fechaInput = document.querySelector('[name="fecha_evento"]');
            if (fechaInput) {
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                fechaInput.min = now.toISOString().slice(0, 16);
                
                // Si no está editando, poner fecha actual
                if (!editingEventId) {
                    fechaInput.value = now.toISOString().slice(0, 16);
                }
            }
            
            // Auto-cargar duración del deporte
            document.querySelector('[name="deporte_id"]').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const duracion = selectedOption.getAttribute('data-duracion');
                if (duracion && !document.querySelector('[name="duracion_minutos"]').value) {
                    document.querySelector('[name="duracion_minutos"]').placeholder = duracion + ' min (por defecto)';
                }
            });
            
            // Aplicar filtros desde URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('estado')) {
                document.getElementById('filtro-estado').value = urlParams.get('estado');
            }
            if (urlParams.has('fecha')) {
                document.getElementById('filtro-fecha').value = urlParams.get('fecha');
            }
            if (urlParams.has('deporte')) {
                document.getElementById('filtro-deporte').value = urlParams.get('deporte');
            }
        });
    </script>
</body>
</html><?php
// admin.php
session_start();

// Sistema de autenticación simple (puedes mejorarlo)
$admin_password = "ElTitoooo646z@#"; // Cambiar por una contraseña segura

// Verificar autenticación
if (!isset($_SESSION['admin_logged']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged'] = true;
    } else {
        $error_login = "Contraseña incorrecta";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Si no está autenticado, mostrar formulario de login
if (!isset($_SESSION['admin_logged'])) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SportEvents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .login-container h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .login-container p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Acceso Admin</h1>
        <p></p>
        
        <?php if (isset($error_login)): ?>
            <div class="error"><?= htmlspecialchars($error_login) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>
            <button type="submit" name="login" class="btn">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
<?php
exit;
}

// Incluir archivos necesarios
require_once 'config/database.php';
require_once 'models/EventoModel.php';
require_once 'models/DeporteModel.php';
require_once 'models/CanalModel.php';
require_once 'models/CompeticionModel.php';

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

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'crear_evento':
                // Agregar duración personalizada si está presente
                if (isset($_POST['duracion_minutos']) && !empty($_POST['duracion_minutos'])) {
                    $_POST['duracion_minutos'] = intval($_POST['duracion_minutos']);
                }
                $resultado = $eventoModel->crear($_POST);
                echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Evento creado exitosamente' : 'Error al crear evento']);
                break;
                
            case 'actualizar_evento':
                // Agregar duración personalizada si está presente
                if (isset($_POST['duracion_minutos']) && !empty($_POST['duracion_minutos'])) {
                    $_POST['duracion_minutos'] = intval($_POST['duracion_minutos']);
                } else {
                    $_POST['duracion_minutos'] = null;
                }
                $resultado = $eventoModel->actualizar($_POST['id'], $_POST);
                echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Evento actualizado exitosamente' : 'Error al actualizar evento']);
                break;
                
            case 'eliminar_evento':
                $resultado = $eventoModel->eliminar($_POST['id']);
                echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Evento eliminado exitosamente' : 'Error al eliminar evento']);
                break;
                
            case 'eliminar_eventos_pasados':
                try {
                    $stmt = $db->prepare("DELETE FROM eventos WHERE fecha_evento < NOW() - INTERVAL 1 DAY");
                    $resultado = $stmt->execute();
                    $count = $stmt->rowCount();
                    echo json_encode(['success' => true, 'message' => "Se eliminaron $count eventos pasados"]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar eventos: ' . $e->getMessage()]);
                }
                break;
                
            case 'duplicar_evento':
                $evento = $eventoModel->obtenerPorId($_POST['id']);
                if ($evento) {
                    unset($evento['id']);
                    unset($evento['token_acceso']);
                    unset($evento['token_expira']);
                    $evento['titulo'] = $evento['titulo'] . ' (Copia)';
                    $evento['fecha_evento'] = $_POST['nueva_fecha'] ?? $evento['fecha_evento'];
                    $resultado = $eventoModel->crear($evento);
                    echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Evento duplicado exitosamente' : 'Error al duplicar evento']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Evento no encontrado']);
                }
                break;
                
            case 'cambiar_hora_masivo':
                $ids = explode(',', $_POST['ids']);
                $minutos = intval($_POST['minutos_cambio']);
                $operacion = $_POST['operacion'] === 'restar' ? '-' : '+';
                
                $stmt = $db->prepare("UPDATE eventos SET fecha_evento = DATE_ADD(fecha_evento, INTERVAL $operacion$minutos MINUTE) WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");
                $resultado = $stmt->execute();
                $count = $stmt->rowCount();
                
                echo json_encode(['success' => $resultado, 'message' => "Se actualizaron $count eventos"]);
                break;
                
            case 'obtener_evento':
                $evento = $eventoModel->obtenerPorId($_POST['id']);
                echo json_encode(['success' => true, 'evento' => $evento]);
                break;
                
            case 'crear_deporte':
                // Agregar duración típica si está presente
                if (isset($_POST['duracion_tipica']) && !empty($_POST['duracion_tipica'])) {
                    $_POST['duracion_tipica'] = intval($_POST['duracion_tipica']);
                }
                $resultado = $deporteModel->crear($_POST);
                echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Deporte creado exitosamente' : 'Error al crear deporte']);
                break;
                
            case 'crear_canal':
                $resultado = $canalModel->crear($_POST);
                echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Canal creado exitosamente' : 'Error al crear canal']);
                break;
                
            case 'crear_competicion':
                $resultado = $competicionModel->crear($_POST);
                echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Competición creada exitosamente' : 'Error al crear competición']);
                break;
                
            case 'obtener_competiciones':
                $competiciones = $competicionModel->obtenerPorDeporte($_POST['deporte_id']);
                echo json_encode(['success' => true, 'competiciones' => $competiciones]);
                break;
                
            case 'listar_eventos':
                $filtros = [];
                if (isset($_POST['estado'])) $filtros['estado'] = $_POST['estado'];
                if (isset($_POST['deporte_id'])) $filtros['deporte_id'] = $_POST['deporte_id'];
                if (isset($_POST['fecha_inicio'])) $filtros['fecha_inicio'] = $_POST['fecha_inicio'];
                if (isset($_POST['fecha_fin'])) $filtros['fecha_fin'] = $_POST['fecha_fin'];
                
                $eventos = $eventoModel->obtenerConFiltros($filtros);
                echo json_encode(['success' => true, 'eventos' => $eventos]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Obtener datos para los formularios
$deportes = $deporteModel->obtenerTodos();
$canales = $canalModel->obtenerTodos();
$competiciones = $competicionModel->obtenerTodos();

// Obtener eventos con filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

$query = "SELECT e.*, d.nombre as deporte_nombre, c.nombre as canal_nombre, comp.nombre as competicion_nombre 
          FROM eventos e 
          LEFT JOIN deportes d ON e.deporte_id = d.id 
          LEFT JOIN canales c ON e.canal_id = c.id 
          LEFT JOIN competiciones comp ON e.competicion_id = comp.id 
          WHERE 1=1";

if ($filtro_estado) {
    $query .= " AND e.estado = :estado";
}

if ($filtro_fecha === 'hoy') {
    $query .= " AND DATE(e.fecha_evento) = CURDATE()";
} elseif ($filtro_fecha === 'manana') {
    $query .= " AND DATE(e.fecha_evento) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
} elseif ($filtro_fecha === 'pasados') {
    $query .= " AND e.fecha_evento < NOW()";
}

$query .= " ORDER BY e.fecha_evento ASC";

$stmt = $db->prepare($query);
if ($filtro_estado) {
    $stmt->bindParam(':estado', $filtro_estado);
}
$stmt->execute();
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar eventos pasados
$stmt_pasados = $db->prepare("SELECT COUNT(*) as total FROM eventos WHERE fecha_evento < NOW() - INTERVAL 1 DAY");
$stmt_pasados->execute();
$eventos_pasados = $stmt_pasados->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Avanzado</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 20px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .admin-header h1 {
            font-size: 1.8rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-nav {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-link {
            color: #666;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: #667eea;
            color: white;
        }

        .logout-btn {
            background: #ff6b6b;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff5252;
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .admin-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .admin-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            overflow-x: auto;
        }

        .admin-tab {
            background: none;
            border: none;
            padding: 15px 25px;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .admin-tab:hover {
            color: #667eea;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-section h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.3rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }

        .form-input, .form-select, .form-textarea {
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
        }

        .btn-danger:hover {
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 10px 30px rgba(108, 117, 125, 0.3);
        }

        .btn-warning {
            background: #ffa726;
        }

        .btn-warning:hover {
            background: #ff9100;
            box-shadow: 0 10px 30px rgba(255, 167, 38, 0.3);
        }

        .btn-success {
            background: #66bb6a;
        }

        .btn-success:hover {
            background: #4caf50;
            box-shadow: 0 10px 30px rgba(102, 187, 106, 0.3);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .badge-programado {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-en_vivo {
            background: #ffebee;
            color: #d32f2f;
        }

        .badge-finalizado {
            background: #f3e5f5;
            color: #7c3aed;
        }

        .badge-cancelado {
            background: #fafafa;
            color: #616161;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Nuevos estilos para funciones avanzadas */
        .filter-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-bar select, .filter-bar input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .batch-actions {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
        }

        .time-adjuster {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .time-adjuster input[type="number"] {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .quick-time-btn {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #90caf9;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quick-time-btn:hover {
            background: #1976d2;
            color: white;
        }

        .duration-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .duration-input input {
            width: 100px;
        }

        .duration-presets {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }

        .duration-preset {
            padding: 4px 8px;
            font-size: 11px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .duration-preset:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .event-row-selected {
            background: #e8f4fd !important;
        }

        .select-all-checkbox {
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 15px;
            }

            .admin-nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .data-table {
                font-size: 14px;
            }

            .data-table th,
            .data-table td {
                padding: 10px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1><span class="material-icons" style="vertical-align: middle; margin-right: 10px;">admin_panel_settings</span>Panel de Administración Avanzado</h1>
        <div class="admin-nav">
            <a href="index.php" class="nav-link">
                <span class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">home</span>
                Ver Sitio
            </a>
            <a href="importar_marca.php" class="nav-link">
                <span class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">cloud_download</span>
                Importar de Marca
            </a>
            <a href="admin_canales.php" class="nav-link">
                <span class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">tv</span>
                ID canales
            </a>
            <a href="?logout=1" class="logout-btn">
                <span class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 5px;">logout</span>
                Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= count($eventos) ?></div>
                <div class="label">Total Eventos</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($eventos, function($e) { return $e['estado'] === 'programado'; })) ?></div>
                <div class="label">Programados</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($eventos, function($e) { 
                    $now = time();
                    $event_time = strtotime($e['fecha_evento']);
                    return $event_time <= $now && $event_time + ($e['duracion_minutos'] ?? 90) * 60 >= $now;
                })) ?></div>
                <div class="label">En Vivo Ahora</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $eventos_pasados ?></div>
                <div class="label">Eventos Pasados</div>
            </div>
        </div>

        <div class="admin-panel">
            <!-- Alertas -->
            <div id="alert-success

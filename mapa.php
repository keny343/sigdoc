<?php
require_once 'includes/auth.php';
require_once 'conexao.php';
require_once 'includes/geospatial.php';

if (!is_logged_in()) {
    header('Location: auth/login.php');
    exit;
}

$geospatial = new Geospatial($pdo);

$config = [
    'center_lat' => -14.2350,
    'center_lng' => -51.9253,
    'zoom' => 4,
    'tile_layer' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
];

try {
    $query = "SELECT id, titulo, descricao, endereco,
              ST_X(localizacao) as lng,
              ST_Y(localizacao) as lat
              FROM documentos
              WHERE localizacao IS NOT NULL
              AND NOT (ST_X(localizacao) = 0 AND ST_Y(localizacao) = 0)";
    $stmt = $pdo->query($query);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $documentos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Documentos</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.3/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        #mapa { height: 100vh; width: 100%; }
        .map-header { position: absolute; top: 10px; left: 50px; z-index: 1000; background: white; padding: 10px 20px; border-radius: 4px; box-shadow: 0 1px 5px rgba(0,0,0,0.4); font-weight: bold; }
        .map-controls { position: absolute; top: 60px; left: 50px; z-index: 1000; background: white; padding: 10px; border-radius: 4px; box-shadow: 0 1px 5px rgba(0,0,0,0.2); max-width: 300px; }
        .map-controls label { display: block; margin-bottom: 5px; font-weight: bold; }
        .map-controls select, .map-controls input, .map-controls button { width: 100%; margin-bottom: 10px; padding: 5px; }
        .map-controls button { background-color: #3498db; color: white; border: none; cursor: pointer; border-radius: 3px; }
        .map-controls button:hover { background-color: #2980b9; }
        .btn-ver-documento, .btn-rota { display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 3px; font-size: 0.9em; margin-top: 5px; margin-right: 5px; cursor: pointer; border: none; }
        .btn-ver-documento { background-color: #3498db; }
        .btn-rota { background-color: #2ecc71; }
        
        .user-marker-container { background: transparent; border: none; }
        .user-location-wrapper { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        .user-position-dot {
            width: 16px; height: 16px; background-color: #3498db; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 5px rgba(0,0,0,0.3); z-index: 2;
        }
        .user-heading-arrow {
            position: absolute; top: 0; left: 50%; margin-left: -10px; width: 0; height: 0;
            border-left: 10px solid transparent; border-right: 10px solid transparent; border-bottom: 20px solid rgba(52, 152, 219, 0.8);
            z-index: 1; transform-origin: center 20px; transition: transform 0.3s ease; display: block;
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
        }
        .user-position-dot { animation: pulse 2s infinite; }
        
        /* Modal de loading */
        .loading-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .loading-modal.active {
            display: flex;
        }
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Modal de Loading -->
    <div id="loadingModal" class="loading-modal">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h4>Buscando localização...</h4>
            <p>Por favor, aguarde enquanto obtemos sua posição.</p>
            <p style="font-size: 0.9em; color: #666; margin-top: 10px;">Certifique-se de que o GPS está ativado.</p>
        </div>
    </div>
    
    <div class="map-header"><i class="fas fa-map-marker-alt"></i> Mapa de Documentos</div>
    
    <div class="map-controls">
        <label for="filtro-categoria">Filtrar por Categoria:</label>
        <select id="filtro-categoria"><option value="">Todas as categorias</option></select>
        <label for="filtro-tipo">Filtrar por Tipo:</label>
        <select id="filtro-tipo">
            <option value="">Todos os tipos</option>
            <option value="contrato">Contratos</option>
            <option value="relatorio">Relatórios</option>
            <option value="fatura">Faturas</option>
        </select>
        <label for="raio-proximidade">Documentos próximos (raio em km):</label>
        <select id="raio-proximidade">
            <option value="1">1 km</option>
            <option value="3">3 km</option>
            <option value="5" selected>5 km</option>
            <option value="10">10 km</option>
            <option value="25">25 km</option>
        </select>
        <button id="btn-proximos">Buscar próximos</button>
        <button id="btn-localizar" style="margin-top: 5px;"><i class="fas fa-location-arrow"></i> Minha Localização</button>
        <button id="btn-limpar-rota" style="margin-top: 5px; background-color: #e74c3c; display: none;"><i class="fas fa-times"></i> Limpar Rota</button>
    </div>
    
    <div id="mapa"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.3/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    
    <script>
        const documentos = <?= json_encode($documentos) ?>;
        let map, markers, routingControl, userMarker, userPosition, locationWatchId, isTracking = false;
        let pUserPosition = null; 
        let currentHeading = null;
        let derivedHeading = 0;

        function initMap() {
            map = L.map('mapa').setView([<?= $config['center_lat'] ?>, <?= $config['center_lng'] ?>], <?= $config['zoom'] ?>);
            L.tileLayer('<?= $config['tile_layer'] ?>', { attribution: '<?= $config['attribution'] ?>', maxZoom: 19 }).addTo(map);

            markers = L.markerClusterGroup();
            addDocsToMap(documentos);
            
            // Não iniciar tracking automaticamente - apenas quando o usuário clicar no botão
            // startUserTracking(); // Removido para evitar problemas em mobile
            startCompassTracking();
        }

        function clearMarkers() { if(markers) markers.clearLayers(); }

        function addDocsToMap(docs) {
            clearMarkers();
            docs.forEach(doc => {
                if (doc.lat && doc.lng) {
                    const distancia = doc.distancia_km ? `<div class="documento-meta">Distância: ${Number(doc.distancia_km).toFixed(2)} km</div>` : '';
                    const popupContent = `
                        <div class="documento-info">
                            <h3>${escapeHtml(doc.titulo || 'Sem título')}</h3>
                            ${doc.descricao ? `<p>${truncateText(escapeHtml(doc.descricao), 100)}</p>` : ''}
                            ${doc.endereco ? `<div class="documento-meta">${escapeHtml(doc.endereco)}</div>` : ''}
                            ${distancia}
                            <div>
                                <a href="documentos/visualizar.php?id=${doc.id}" class="btn-ver-documento">Ver</a>
                                <?php if (pode('rotear_documento')): ?>
                                <button onclick="tracarRota(${doc.lat}, ${doc.lng}, '${escapeHtml(doc.titulo || 'Documento')}')" class="btn-rota">Rota</button>
                                <?php endif; ?>
                            </div>
                        </div>`;
                    markers.addLayer(L.marker([doc.lat, doc.lng]).bindPopup(popupContent));
                }
            });
            map.addLayer(markers);
        }

        function startUserTracking() {
            if (!navigator.geolocation) {
                alert('Geolocalização não suportada pelo seu navegador.');
                return;
            }
            
            if (isTracking) return;
            
            // Primeiro, obter posição atual (mais rápido e confiável em mobile)
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    handlePositionUpdate(pos);
                    // Depois iniciar watchPosition para atualizações contínuas
                    locationWatchId = navigator.geolocation.watchPosition(
                        handlePositionUpdate, 
                        handlePositionError, 
                        { 
                            enableHighAccuracy: true, 
                            timeout: 20000, // Aumentado para mobile (GPS pode demorar)
                            maximumAge: 10000 
                        }
                    );
                    isTracking = true;
                },
                handlePositionError,
                { 
                    enableHighAccuracy: true, 
                    timeout: 20000, // Aumentado para mobile (GPS pode demorar)
                    maximumAge: 60000 
                }
            );
        }

        function startCompassTracking() {
            if (window.DeviceOrientationEvent && typeof window.addEventListener === 'function') {
                if (typeof DeviceOrientationEvent.requestPermission === 'function') {
                    // iOS
                } else {
                    window.addEventListener('deviceorientation', handleOrientationUpdate);
                }
            }
        }

        function handleOrientationUpdate(event) {
            let heading = null;
            if (event.webkitCompassHeading) {
                heading = event.webkitCompassHeading;
            } else if (event.alpha !== null) {
                heading = 360 - event.alpha;
            }

            if (heading !== null) {
                currentHeading = heading;
                if (userPosition && derivedHeading === 0) updateUserMarker(userPosition.lat, userPosition.lng, currentHeading);
            }
        }

        function handlePositionUpdate(pos) {
            const { latitude: lat, longitude: lng, heading: gpsHeading } = pos.coords;
            pUserPosition = userPosition;
            userPosition = { lat, lng };
            
            let displayHeading = currentHeading; 

            if (gpsHeading !== null && !isNaN(gpsHeading)) {
                 displayHeading = gpsHeading;
                 derivedHeading = gpsHeading;
            } 
            else if (pUserPosition) {
                const dist = map.distance([pUserPosition.lat, pUserPosition.lng], [lat, lng]);
                if (dist > 2) {
                    derivedHeading = calculateBearing(pUserPosition.lat, pUserPosition.lng, lat, lng);
                    displayHeading = derivedHeading;
                } else {
                    displayHeading = (derivedHeading !== 0) ? derivedHeading : (currentHeading || 0);
                }
            }

            updateUserMarker(lat, lng, displayHeading);

            if (routingControl) {
                const waypoints = routingControl.getPlan().getWaypoints();
                if (waypoints.length > 0) {
                    const origin = waypoints[0].latLng;
                    if (origin && map.distance([lat, lng], origin) > 15) {
                        routingControl.getPlan().setWaypoints([L.latLng(lat, lng), waypoints[waypoints.length - 1].latLng]);
                    }
                }
            }
        }

        function calculateBearing(startLat, startLng, destLat, destLng) {
            const startLatRad = startLat * (Math.PI / 180);
            const startLngRad = startLng * (Math.PI / 180);
            const destLatRad = destLat * (Math.PI / 180);
            const destLngRad = destLng * (Math.PI / 180);

            const y = Math.sin(destLngRad - startLngRad) * Math.cos(destLatRad);
            const x = Math.cos(startLatRad) * Math.sin(destLatRad) -
                    Math.sin(startLatRad) * Math.cos(destLatRad) * Math.cos(destLngRad - startLngRad);
            
            let brng = Math.atan2(y, x);
            brng = brng * (180 / Math.PI);
            return (brng + 360) % 360;
        }

        function handlePositionError(err) {
            isTracking = false;
            if(locationWatchId) {
                navigator.geolocation.clearWatch(locationWatchId);
                locationWatchId = null;
            }
            
            let errorMsg = 'Erro ao obter localização: ';
            switch(err.code) {
                case err.PERMISSION_DENIED:
                    errorMsg = 'Permissão de localização negada. Por favor, permita o acesso à localização nas configurações do navegador.';
                    break;
                case err.POSITION_UNAVAILABLE:
                    errorMsg = 'Localização indisponível. Verifique se o GPS está ativado.';
                    break;
                case err.TIMEOUT:
                    errorMsg = 'Tempo esgotado ao buscar localização. Tente novamente.';
                    break;
                default:
                    errorMsg = 'Erro desconhecido ao obter localização.';
                    break;
            }
            alert(errorMsg);
        }

        function updateUserMarker(lat, lng, heading) {
            const hasHeading = (heading !== null && heading !== undefined && !isNaN(heading));
            const rotation = hasHeading ? heading : 0; 
            const displayStyle = 'block'; 

            if (userMarker) {
                userMarker.setLatLng([lat, lng]);
                const el = userMarker.getElement();
                if (el) {
                    const arrow = el.querySelector('.user-heading-arrow');
                    if (arrow) {
                        arrow.style.transform = `rotate(${rotation}deg)`;
                        arrow.style.display = displayStyle;
                        arrow.style.opacity = hasHeading ? '1' : '0.5';
                    }
                }
            } else {
                 const opacityStyle = !hasHeading ? 'opacity: 0.5;' : '';
                 const iconHtml = `
                    <div class="user-location-wrapper">
                        <div class="user-heading-arrow" style="transform: rotate(${rotation}deg); display: ${displayStyle}; ${opacityStyle}"></div>
                        <div class="user-position-dot"></div>
                    </div>
                `;
                userMarker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'user-marker-container',
                        html: iconHtml,
                        iconSize: [40, 40],
                        iconAnchor: [20, 20],
                        popupAnchor: [0, -10]
                    }),
                    zIndexOffset: 1000
                }).addTo(map).bindPopup('Você está aqui');
            }
        }

        window.tracarRota = function(destLat, destLng, destTitulo) {
            map.closePopup();
            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                DeviceOrientationEvent.requestPermission().then(r => {
                    if (r == 'granted') window.addEventListener('deviceorientation', handleOrientationUpdate);
                }).catch(console.error);
            }

            if(!userPosition) {
                alert('Aguardando localização...');
                navigator.geolocation.getCurrentPosition(pos => { handlePositionUpdate(pos); calculateRoute(destLat, destLng); });
            } else {
                calculateRoute(destLat, destLng);
            }
        };

        function calculateRoute(destLat, destLng) {
            if (routingControl) map.removeControl(routingControl);
            document.getElementById('btn-limpar-rota').style.display = 'block';
            
            routingControl = L.Routing.control({
                waypoints: [L.latLng(userPosition.lat, userPosition.lng), L.latLng(destLat, destLng)],
                routeWhileDragging: false, language: 'pt-BR', showAlternatives: false,
                serviceUrl: 'https://router.project-osrm.org/route/v1',
                lineOptions: { styles: [{color: '#3498db', opacity: 0.8, weight: 6}] },
                createMarker: function(i, wp, nWps) { return (i === 0 || i === nWps - 1) ? null : L.marker(wp.latLng); }
            }).on('routingerror', function(e) { alert('Erro na rota.'); }).addTo(map);
        }

        document.getElementById('btn-limpar-rota').addEventListener('click', function() {
            if (routingControl) { map.removeControl(routingControl); routingControl = null; this.style.display = 'none'; }
        });

        // Funções para controlar modal de loading
        function showLoadingModal() {
            const modal = document.getElementById('loadingModal');
            if (modal) modal.classList.add('active');
        }
        
        function hideLoadingModal() {
            const modal = document.getElementById('loadingModal');
            if (modal) modal.classList.remove('active');
        }

        document.getElementById('btn-localizar').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Feedback visual
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
            showLoadingModal();
            
            // Solicitar permissão de orientação primeiro (iOS)
            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                DeviceOrientationEvent.requestPermission()
                    .then(r => {
                        if (r == 'granted') {
                            window.addEventListener('deviceorientation', handleOrientationUpdate);
                        }
                    })
                    .catch(err => {
                        console.log('Permissão de orientação negada:', err);
                    });
            }
            
            // Obter localização atual primeiro
            if (!navigator.geolocation) {
                alert('Geolocalização não suportada pelo seu navegador.');
                btn.disabled = false;
                btn.innerHTML = originalText;
                hideLoadingModal();
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    handlePositionUpdate(pos);
                    if (userPosition) {
                        map.flyTo([userPosition.lat, userPosition.lng], 16);
                    }
                    // Iniciar tracking contínuo
                    if (!isTracking) {
                        startUserTracking();
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    hideLoadingModal();
                },
                function(err) {
                    handlePositionError(err);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    hideLoadingModal();
                },
                { 
                    enableHighAccuracy: true, 
                    timeout: 20000, // 20 segundos para mobile (GPS pode demorar)
                    maximumAge: 60000 
                }
            );
        });

        document.getElementById('btn-proximos').addEventListener('click', function() {
            if (!userPosition) {
                alert('Por favor, aguarde a localização ser obtida ou clique em "Minha Localização" primeiro.');
                return;
            }

            const raioKm = parseFloat(document.getElementById('raio-proximidade').value);
            if (!raioKm || raioKm <= 0) {
                alert('Por favor, selecione um raio válido.');
                return;
            }

            // Desabilitar botão durante a busca
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Buscando...';

            // Fazer requisição para buscar documentos próximos
            const url = `api/geo_buscar_documentos_proximos.php?lat=${userPosition.lat}&lng=${userPosition.lng}&raio_km=${raioKm}`;
            
            fetch(url, {
                method: 'GET',
                credentials: 'include' // Incluir cookies de sessão para autenticação
            })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = 'Buscar próximos';
                    
                    if (data.error) {
                        alert('Erro: ' + data.error);
                        return;
                    }

                    if (data.success && data.data) {
                        addDocsToMap(data.data);
                        
                        // Ajustar zoom para mostrar todos os documentos
                        if (data.data.length > 0) {
                            const bounds = L.latLngBounds(
                                data.data.map(doc => [doc.lat, doc.lng])
                            );
                            // Incluir a posição do usuário nos bounds
                            bounds.extend([userPosition.lat, userPosition.lng]);
                            map.fitBounds(bounds, { padding: [50, 50] });
                        }
                        
                        alert(`Encontrados ${data.total} documento(s) próximo(s) em um raio de ${raioKm} km.`);
                    } else {
                        alert('Nenhum documento encontrado no raio especificado.');
                        addDocsToMap([]);
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.textContent = 'Buscar próximos';
                    console.error('Erro ao buscar documentos próximos:', error);
                    alert('Erro ao buscar documentos próximos. Verifique o console para mais detalhes.');
                });
        });

        function escapeHtml(t) { return t.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
        function truncateText(t, l) { return t.length <= l ? t : t.substring(0, l) + '...'; }
        
        fetch('api/buscar_categorias.php').then(r => r.json()).then(d => {
            const s = document.getElementById('filtro-categoria');
            (Array.isArray(d) ? d : (d.data || [])).forEach(c => {
                const o = document.createElement('option'); o.value = c.id; o.textContent = c.nome; s.appendChild(o);
            });
        }).catch(e => console.error(e));

        initMap();
    </script>
</body>
</html>
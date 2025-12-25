<?php
session_start();
require_once 'config/database.php';
require_once 'classes/LocationService.php';
require_once 'includes/maintenance_check.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

$locationService = new LocationService($db);

// Get user's current location
$query = "SELECT current_latitude, current_longitude, search_radius, show_distance 
          FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch();

$has_location = !empty($current_user['current_latitude']);

include 'views/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Leaflet MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<style>
:root {
    --bg-dark: #0a0a0f;
    --bg-card: #1a1a2e;
    --border-color: #2d2d44;
    --primary-blue: #4267f5;
    --text-white: #ffffff;
    --text-gray: #a0a0b0;
    --success-green: #10b981;
    --warning-orange: #f59e0b;
    --danger-red: #ef4444;
    --info-cyan: #06b6d4;
}

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.6); }
    50% { box-shadow: 0 0 30px rgba(16, 185, 129, 0.9); }
}

.online-badge {
    animation: pulse-glow 2s infinite;
}

.user-card-hover {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.user-card-hover:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(66, 103, 245, 0.3);
}

.glassmorphism {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

#map {
    height: 500px;
    width: 100%;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.leaflet-popup-content-wrapper {
    background: var(--bg-card);
    color: var(--text-white);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.leaflet-popup-tip {
    background: var(--bg-card);
}

.view-toggle {
    background: var(--bg-card);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 0.5rem;
}

.view-toggle button {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--text-gray);
    border-radius: 8px;
    transition: all 0.3s;
}

.view-toggle button.active {
    background: var(--primary-blue);
    color: white;
}

.distance-circle {
    fill: rgba(66, 103, 245, 0.1);
    stroke: var(--primary-blue);
    stroke-width: 2;
    stroke-dasharray: 5, 5;
}
</style>

<div class="min-vh-100" style="background: linear-gradient(135deg, var(--bg-dark) 0%, #16213e 100%); padding: 2rem 0;">
    <div class="container" style="max-width: 1400px;">
        <!-- Header Card -->
        <div class="card border-0 shadow-lg mb-4" style="background: linear-gradient(135deg, var(--primary-blue), var(--info-cyan)); border-radius: 24px;">
            <div class="card-body p-4">
                <div class="text-center text-white">
                    <h1 class="display-4 fw-bold mb-2">
                        <i class="bi bi-geo-alt-fill"></i> Nearby Users
                    </h1>
                    <p class="lead mb-4">Discover people close to you with interactive map</p>
                    
                    <div class="d-flex flex-wrap justify-content-center align-items-center gap-3">
                        <button class="btn btn-light btn-lg" onclick="requestLocation()" id="locationBtn">
                            <i class="bi bi-geo-alt me-2"></i>
                            Update My Location
                        </button>
                        
                        <div class="d-flex align-items-center gap-2 glassmorphism rounded-pill px-4 py-2">
                            <label class="text-white fw-semibold mb-0">Radius:</label>
                            <input type="range" class="form-range" style="width: 150px;"
                                   id="radiusSlider" 
                                   min="5" max="100" step="5" 
                                   value="<?php echo $current_user['search_radius'] ?? 50; ?>"
                                   oninput="updateRadius(this.value)">
                            <span id="radiusValue" class="badge bg-white text-primary fs-6 px-3 py-2">
                                <?php echo $current_user['search_radius'] ?? 50; ?> km
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!$has_location): ?>
        <!-- No Location State -->
        <div class="card border-0 shadow-lg" style="background: var(--bg-card); border-radius: 24px;">
            <div class="card-body text-center p-5">
                <div class="display-1 mb-4">📍</div>
                <h2 class="h1 fw-bold text-white mb-3">Location Not Set</h2>
                <p class="text-muted fs-5 mb-4">
                    Enable location access to discover nearby users on an interactive map
                </p>
                
                <div class="card mx-auto mb-4" style="max-width: 600px; background: rgba(66, 103, 245, 0.1); border: 2px solid var(--primary-blue); border-radius: 16px;">
                    <div class="card-body">
                        <h3 class="text-primary mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            How It Works
                        </h3>
                        <ol class="list-group list-group-numbered text-start">
                            <li class="list-group-item border-0 bg-transparent text-white">Click "Update My Location" button</li>
                            <li class="list-group-item border-0 bg-transparent text-white">Allow location access in your browser</li>
                            <li class="list-group-item border-0 bg-transparent text-white">View nearby users on an interactive map</li>
                            <li class="list-group-item border-0 bg-transparent text-white">Control your privacy settings</li>
                        </ol>
                    </div>
                </div>
                
                <button class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow-lg" onclick="requestLocation()">
                    <i class="bi bi-geo-alt me-2"></i>
                    Enable Location Access
                </button>
            </div>
        </div>
        <?php else: ?>
        
        <!-- View Toggle -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="view-toggle d-flex gap-2">
                <button onclick="switchView('map')" id="mapViewBtn" class="active">
                    <i class="bi bi-map"></i> Map View
                </button>
                <button onclick="switchView('grid')" id="gridViewBtn">
                    <i class="bi bi-grid-3x3"></i> Grid View
                </button>
            </div>
            
            <div class="text-white" id="resultsCount"></div>
        </div>
        
        <!-- Filters Bar -->
        <div class="card border-0 shadow-lg mb-4" style="background: var(--bg-card); border-radius: 16px;">
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-white">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 bg-transparent text-white" 
                                   id="searchUsers" 
                                   placeholder="Search users..."
                                   onkeyup="filterUsers()">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <select class="form-select bg-transparent text-white" id="genderFilter" onchange="loadNearbyUsers()">
                            <option value="">All Genders</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select class="form-select bg-transparent text-white" id="onlineFilter" onchange="loadNearbyUsers()">
                            <option value="">All Users</option>
                            <option value="online">Online Now</option>
                            <option value="recent">Active Recently</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select class="form-select bg-transparent text-white" id="sortBy" onchange="loadNearbyUsers()">
                            <option value="distance">Distance (Nearest)</option>
                            <option value="recent">Recently Active</option>
                            <option value="newest">Newest Members</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Container -->
        <div id="mapContainer" class="mb-4">
            <div id="map"></div>
        </div>
        
        <!-- Grid Container -->
        <div id="gridContainer" class="d-none">
            <!-- Loading State -->
            <div id="loadingState" class="text-center py-5 d-none">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-white mt-3 fs-5">Finding nearby users...</p>
            </div>
            
            <!-- Users Grid -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="usersGrid"></div>
            
            <!-- No Results -->
            <div id="noResults" class="card border-0 shadow-lg d-none" style="background: var(--bg-card); border-radius: 24px;">
                <div class="card-body text-center p-5">
                    <div class="display-1 mb-4">🔍</div>
                    <h3 class="h2 fw-bold text-white mb-3">No Nearby Users Found</h3>
                    <p class="text-muted fs-5">
                        Try increasing your search radius or check back later
                    </p>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-2xl" style="background: var(--bg-card);">
            <div class="modal-header border-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" 
                        data-bs-dismiss="modal" aria-label="Close"></button>
                
                <div class="w-100 text-center pt-4">
                    <div class="mx-auto mb-3 rounded-circle glassmorphism d-flex align-items-center justify-content-center border border-3 border-white" 
                         style="width: 100px; height: 100px; font-size: 3rem;" id="modalAvatar">
                        👤
                    </div>
                    <h4 class="modal-title fs-2 fw-bold text-white" id="modalUsername">Username</h4>
                    <p class="text-white mb-0" id="modalStatus">
                        <i class="bi bi-circle-fill text-success me-1"></i> Online Now
                    </p>
                </div>
            </div>
            
            <div class="modal-body p-4">
                <div class="card glassmorphism border-0 rounded-3 mb-3" id="modalDistance">
                    <div class="card-body text-center py-3">
                        <div class="fs-1 fw-bold text-white" id="modalDistanceValue">--</div>
                        <div class="text-white-50">away from you</div>
                    </div>
                </div>
                
                <div class="card glassmorphism border-white border rounded-3">
                    <div class="card-body">
                        <h5 class="card-title mb-3 text-white">
                            <i class="bi bi-clipboard me-2"></i> Profile Info
                        </h5>
                        <div class="d-flex align-items-center py-2 border-bottom border-white text-white">
                            <i class="bi bi-calendar3 me-3 fs-5"></i>
                            <span class="fw-semibold me-auto">Joined:</span>
                            <span id="modalJoined" class="text-white-50">--</span>
                        </div>
                        <div class="d-flex align-items-center py-2 text-white">
                            <i class="bi bi-eye me-3 fs-5"></i>
                            <span class="fw-semibold me-auto">Last Seen:</span>
                            <span id="modalLastSeen" class="text-white-50">--</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-0 p-4 gap-2">
                <a href="#" id="modalViewProfile" class="btn btn-outline-light flex-fill rounded-pill py-3">
                    <i class="bi bi-person me-2"></i> View Profile
                </a>
                <a href="#" id="modalMessage" class="btn btn-light flex-fill rounded-pill py-3 fw-semibold">
                    <i class="bi bi-chat-dots me-2"></i> Send Message
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet MarkerCluster JS -->
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let currentRadius = <?php echo $current_user['search_radius'] ?? 50; ?>;
let currentLocation = {
    latitude: <?php echo $current_user['current_latitude'] ?? 'null'; ?>,
    longitude: <?php echo $current_user['current_longitude'] ?? 'null'; ?>
};
let map;
let markers = L.markerClusterGroup();
let distanceCircle;
let currentView = 'map';
let allUsers = [];
let userModalInstance;

document.addEventListener('DOMContentLoaded', function() {
    userModalInstance = new bootstrap.Modal(document.getElementById('userModal'));
    if(currentLocation.latitude) {
        initMap();
        loadNearbyUsers();
        setInterval(loadNearbyUsers, 30000);
    }
});

function initMap() {
    map = L.map('map').setView([currentLocation.latitude, currentLocation.longitude], 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(map);
    
    // Add user's location marker
    const userIcon = L.divIcon({
        html: '<div style="background: #4267f5; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(66,103,245,0.5); display: flex; align-items: center; justify-content: center; font-size: 16px;">📍</div>',
        className: '',
        iconSize: [30, 30]
    });
    
    L.marker([currentLocation.latitude, currentLocation.longitude], {icon: userIcon})
        .addTo(map)
        .bindPopup('<div style="text-align: center;"><strong>You are here</strong></div>');
    
    // Add distance circle
    distanceCircle = L.circle([currentLocation.latitude, currentLocation.longitude], {
        radius: currentRadius * 1000,
        color: '#4267f5',
        fillColor: '#4267f5',
        fillOpacity: 0.1,
        weight: 2,
        dashArray: '5, 5'
    }).addTo(map);
}

function requestLocation() {
    const btn = document.getElementById('locationBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Getting Location...';
    
    if(!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-geo-alt me-2"></i> Update My Location';
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            updateLocation(position.coords.latitude, position.coords.longitude);
        },
        (error) => {
            alert('Unable to get your location. Please enable location access.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-geo-alt me-2"></i> Update My Location';
        },
        {enableHighAccuracy: true, timeout: 10000, maximumAge: 0}
    );
}

function updateLocation(latitude, longitude) {
    const formData = new FormData();
    formData.append('action', 'update_location');
    formData.append('latitude', latitude);
    formData.append('longitude', longitude);
    
    fetch('/api/location.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to update location');
        }
        
        const btn = document.getElementById('locationBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-geo-alt me-2"></i> Update My Location';
    });
}

function updateRadius(value) {
    document.getElementById('radiusValue').textContent = value + ' km';
    currentRadius = value;
    
    if(distanceCircle && map) {
        distanceCircle.setRadius(currentRadius * 1000);
    }
    
    clearTimeout(window.radiusTimeout);
    window.radiusTimeout = setTimeout(() => {
        loadNearbyUsers();
    }, 500);
}

function switchView(view) {
    currentView = view;
    
    if(view === 'map') {
        document.getElementById('mapContainer').classList.remove('d-none');
        document.getElementById('gridContainer').classList.add('d-none');
        document.getElementById('mapViewBtn').classList.add('active');
        document.getElementById('gridViewBtn').classList.remove('active');
        if(map) map.invalidateSize();
    } else {
        document.getElementById('mapContainer').classList.add('d-none');
        document.getElementById('gridContainer').classList.remove('d-none');
        document.getElementById('mapViewBtn').classList.remove('active');
        document.getElementById('gridViewBtn').classList.add('active');
        displayUsersGrid(allUsers);
    }
}

function loadNearbyUsers() {
    if(!currentLocation.latitude) return;
    
    const params = new URLSearchParams({
        action: 'get_nearby_users',
        radius: currentRadius,
        gender: document.getElementById('genderFilter').value,
        online: document.getElementById('onlineFilter').value,
        sort: document.getElementById('sortBy').value,
        limit: 100
    });
    
    fetch('/api/location.php?' + params)
        .then(response => response.json())
        .then(data => {
            if(data.success && data.users) {
                allUsers = data.users;
                document.getElementById('resultsCount').textContent = 
                    `Found ${data.count || 0} user${(data.count !== 1) ? 's' : ''} within ${currentRadius}km`;
                
                if(currentView === 'map') {
                    displayUsersOnMap(data.users);
                } else {
                    displayUsersGrid(data.users);
                }
            }
        })
        .catch(error => console.error('Error:', error));
}

function displayUsersOnMap(users) {
    markers.clearLayers();
    
    users.forEach(user => {
        if(user.latitude && user.longitude) {
            const icon = L.divIcon({
                html: `<div style="background: ${user.is_online ? '#10b981' : '#6b7280'}; width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; font-size: 20px; cursor: pointer;">👤</div>`,
                className: '',
                iconSize: [40, 40]
            });
            
            const marker = L.marker([user.latitude, user.longitude], {icon: icon})
                .bindPopup(createPopupContent(user));
            
            marker.on('click', () => openUserModal(user));
            markers.addLayer(marker);
        }
    });
    
    map.addLayer(markers);
}

function createPopupContent(user) {
    return `
        <div style="text-align: center; min-width: 200px;">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">👤</div>
            <h5 style="margin-bottom: 0.5rem; color: white;">${escapeHtml(user.username)}</h5>
            <p style="margin-bottom: 0.5rem; color: ${user.is_online ? '#10b981' : '#9ca3af'};">
                <i class="bi bi-circle-fill"></i> ${user.is_online ? 'Online' : 'Offline'}
            </p>
            ${user.show_distance ? `<p style="color: #06b6d4;"><i class="bi bi-geo-alt-fill"></i> ${user.distance_display}</p>` : ''}
            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <a href="/profile.php?id=${user.id}" class="btn btn-sm btn-outline-primary" style="flex: 1;">Profile</a>
                <a href="/messages-chat-simple.php?user=${user.id}" class="btn btn-sm btn-primary" style="flex: 1;">Message</a>
            </div>
        </div>
    `;
}

function displayUsersGrid(users) {
    const grid = document.getElementById('usersGrid');
    grid.innerHTML = '';
    
    if(users.length === 0) {
        document.getElementById('noResults').classList.remove('d-none');
        return;
    }
    
    document.getElementById('noResults').classList.add('d-none');
    
    users.forEach(user => {
        const col = document.createElement('div');
        col.className = 'col';
        col.innerHTML = `
            <div class="card border-0 shadow-lg rounded-3 overflow-hidden user-card-hover h-100 text-white position-relative" 
                 style="background: var(--bg-card); cursor: pointer;"
                 onclick='openUserModal(${JSON.stringify(user).replace(/'/g, "&#39;")})'>
                ${user.show_distance ? `
                    <div class="position-absolute top-0 end-0 m-3 badge glassmorphism text-white px-3 py-2 rounded-pill" style="z-index: 10;">
                        <i class="bi bi-geo-alt-fill me-1"></i> ${user.distance_display}
                    </div>
                ` : ''}
                
                <div class="card-body d-flex flex-column align-items-center text-center p-4">
                    <div class="position-relative mb-3">
                        <div class="rounded-circle glassmorphism d-flex align-items-center justify-content-center border border-3 border-white" 
                             style="width: 100px; height: 100px; font-size: 3rem;">👤</div>
                        ${user.is_online ? `
                            <span class="position-absolute bottom-0 end-0 bg-success border border-3 border-white rounded-circle online-badge" 
                                  style="width: 24px; height: 24px;"></span>
                        ` : ''}
                    </div>
                    
                    <h5 class="card-title fs-4 fw-bold mb-2">${escapeHtml(user.username)}</h5>
                    <p class="card-text mb-2">
                        ${user.is_online ? 
                            '<i class="bi bi-circle-fill text-success me-1"></i> Online Now' : 
                            '<i class="bi bi-circle opacity-50 me-1"></i> Offline'
                        }
                    </p>
                    
                    <div class="mt-3 d-flex gap-2 w-100">
                        <a href="/profile.php?id=${user.id}" class="btn btn-outline-light flex-fill rounded-pill" onclick="event.stopPropagation()">
                            <i class="bi bi-person"></i> Profile
                        </a>
                        <a href="/messages-chat-simple.php?user=${user.id}" class="btn btn-light flex-fill rounded-pill" onclick="event.stopPropagation()">
                            <i class="bi bi-chat-dots"></i> Message
                        </a>
                    </div>
                </div>
            </div>
        `;
        grid.appendChild(col);
    });
}

function openUserModal(user) {
    document.getElementById('modalUsername').textContent = user.username;
    document.getElementById('modalStatus').innerHTML = user.is_online ? 
        '<i class="bi bi-circle-fill text-success me-1"></i> Online Now' : 
        '<i class="bi bi-circle opacity-50 me-1"></i> Offline';
    
    document.getElementById('modalJoined').textContent = formatDate(user.created_at);
    document.getElementById('modalLastSeen').textContent = user.last_seen ? formatTime(user.last_seen) : 'Unknown';
    
    if(user.show_distance && user.distance_display) {
        document.getElementById('modalDistance').classList.remove('d-none');
        document.getElementById('modalDistanceValue').textContent = user.distance_display;
    } else {
        document.getElementById('modalDistance').classList.add('d-none');
    }
    
    document.getElementById('modalViewProfile').href = '/profile.php?id=' + user.id;
    document.getElementById('modalMessage').href = '/messages-chat-simple.php?user=' + user.id;
    
    userModalInstance.show();
}

function filterUsers() {
    const search = document.getElementById('searchUsers').value.toLowerCase();
    const filtered = allUsers.filter(u => u.username.toLowerCase().includes(search));
    
    if(currentView === 'map') {
        displayUsersOnMap(filtered);
    } else {
        displayUsersGrid(filtered);
    }
    
    document.getElementById('resultsCount').textContent = 
        `Found ${filtered.length} user${filtered.length !== 1 ? 's' : ''} within ${currentRadius}km`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if(diff < 60000) return 'just now';
    if(diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if(diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
    if(diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';
    
    return date.toLocaleDateString();
}

function formatDate(timestamp) {
    return new Date(timestamp).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short' 
    });
}
</script>

<?php include 'views/footer.php'; ?>
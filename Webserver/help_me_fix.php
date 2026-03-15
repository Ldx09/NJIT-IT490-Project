<?php
session_start();
// Only logged in users get repair lookup
if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$request = array();
$request['type'] = "validate_session";
$request['session_key'] = $_SESSION["session_key"];
$response = $client->send_request($request);
if (!is_array($response) || !isset($response["status"]) || $response["status"] !== "ok") {
    session_destroy();
    header("Location: index.html");
    exit(0);
}

$apiKey = '';
$envPath = __DIR__ . '/../.env';
// Maps/Places key for location stuff
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (strpos($line, 'GOOGLE_API_KEY=') === 0) {
            $apiKey = trim(substr($line, 15));
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Help me fix it | RecallShield</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 1.5rem;
            background: #0F172A;
            color: #fff;
            min-height: 100vh;
        }
        h2 { margin: 0 0 0.25rem 0; color: #fff; }
        p { margin: 0 0 1rem 0; color: #38BDF8; }
        .bar {
            background: #1E293B;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            max-width: 600px;
        }
        input, select {
            margin: 0 6px 0 0;
            padding: 8px 12px;
            border: 1px solid #1E293B;
            border-radius: 6px;
            background: #0F172A;
            color: #fff;
            font-size: 1rem;
        }
        input::placeholder { color: #38BDF8; opacity: 0.7; }
        select { cursor: pointer; }
        select option { background: #0F172A; color: #fff; }
        button {
            margin: 0 6px 6px 0;
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        #useLocation { background: #22C55E; color: #0F172A; }
        #useLocation:hover { filter: brightness(1.1); }
        #searchBtn { background: #38BDF8; color: #0F172A; }
        #searchBtn:hover { filter: brightness(1.1); }
        #map {
            height: 400px;
            width: 100%;
            max-width: 600px;
            margin: 1rem 0;
            border: 2px solid #1E293B;
            border-radius: 8px;
            overflow: hidden;
        }
        #results {
            list-style: none;
            padding: 0;
            max-width: 600px;
            margin: 1rem 0 0 0;
        }
        #results li {
            padding: 12px 14px;
            background: #1E293B;
            border-left: 4px solid #38BDF8;
            margin-bottom: 6px;
            border-radius: 6px;
            color: #fff;
        }
        #results li:empty,
        #results li.none { border-left-color: #F59E0B; }
        .msg { padding: 10px 14px; margin-bottom: 10px; border-radius: 6px; max-width: 600px; }
        .msg.err { background: #1E293B; border-left: 4px solid #EF4444; color: #fff; }
        .msg.hide { display: none; }
        .recall-note { margin-top: 10px; font-size: 0.9rem; color: #22C55E; max-width: 600px; }
        .nav { margin-top: 1.5rem; }
        .nav a {
            color: #38BDF8;
            text-decoration: none;
            margin-right: 1rem;
        }
        .nav a:hover { color: #22C55E; text-decoration: underline; }
    </style>
</head>
<body>

<h2>Help me fix it</h2>
<p>Find a dealer or shop for your recall repair. Logged in as <?php echo htmlspecialchars($_SESSION["username"]); ?>.</p>

<div id="msg" class="msg hide"></div>

<div class="bar">
    <input type="text" id="address" placeholder="Address or ZIP" size="30">
    <button type="button" id="useLocation">Use my location</button>
    <br><br>
    <select id="placeType">
        <option value="car_repair">Independent garage</option>
        <option value="car_repair">Body shop / collision</option>
        <option value="car_dealer">Dealer (recall authorized)</option>
    </select>
    <button type="button" id="searchBtn">Search</button>
</div>

<div id="map"></div>
<ul id="results"></ul>
<div id="recallNote" class="recall-note" style="display:none;"></div>

<p class="nav">
<a href="home.php">Home</a>
<a href="help_me_fix.php">Help me fix it</a>
<a href="appointments.php">My appointments</a>
<a href="logout.php">Logout</a>
</p>

<script>
var recallRepairMap, dealerAndShopPlacesService, recallSearchLocation;
var lastRecallSearchPlaceType = '';
var ALLOWED_RECALL_PLACE_TYPES = ['car_dealer', 'car_repair'];

function initMap() {
    recallRepairMap = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 40.74, lng: -74.03 },
        zoom: 10
    });
    dealerAndShopPlacesService = new google.maps.places.PlacesService(recallRepairMap);
}

function showRecallSearchError(text) {
    var errBox = document.getElementById('msg');
    if (!text) { errBox.className = 'msg hide'; errBox.textContent = ''; return; }
    errBox.textContent = text;
    errBox.className = 'msg err';
    console.warn('RecallShield recall-search error:', text);
}

document.getElementById('useLocation').onclick = function() {
    if (!navigator.geolocation) {
        var errBox = document.getElementById('msg');
        errBox.textContent = 'Location isn\'t supported in this browser.';
        errBox.className = 'msg err';
        return;
    }
    showRecallSearchError();
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            recallSearchLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            recallRepairMap.setCenter(recallSearchLocation);
            recallRepairMap.setZoom(14);
            document.getElementById('address').value = pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4);
        },
        function() {
            showRecallSearchError('We couldn\'t use your location. Check permissions or enter an address.');
        }
    );
};

document.getElementById('searchBtn').onclick = function() {
    var addressInput = document.getElementById('address').value.trim();
    var placeTypeRaw = document.getElementById('placeType').value;
    lastRecallSearchPlaceType = ALLOWED_RECALL_PLACE_TYPES.indexOf(placeTypeRaw) >= 0 ? placeTypeRaw : 'car_repair';
    showRecallSearchError();

    if (addressInput) {
        var geocoder = new google.maps.Geocoder();
        geocoder.geocode({ address: addressInput }, function(results, status) {
            if (status === 'OK' && results[0]) {
                var location = results[0].geometry.location;
                recallSearchLocation = location;
                recallRepairMap.setCenter(location);
                recallRepairMap.setZoom(14);
                runRecallRepairSearch(location, lastRecallSearchPlaceType, false);
            } else {
                showRecallSearchError('We couldn\'t find that address. Try a different one or use your location.');
                console.warn('RecallShield geocode failed', { address: addressInput });
            }
        });
    } else if (recallSearchLocation) {
        runRecallRepairSearch(recallSearchLocation, lastRecallSearchPlaceType, false);
    } else {
        showRecallSearchError('Enter an address or use "Use my location" first.');
    }
};

// OEM dealers are fewer per region use 12km so we don’t miss the nearest recall authorized dealer.
// Places API often returns ZERO RESULTS with tight radius for dealers, retry once with 50% wider for recall lookup.
function runRecallRepairSearch(latLng, recallPlaceType, isRetry) {
    var recallSearchRadiusMeters = (recallPlaceType === 'car_dealer' ? 12000 : 8000) * (isRetry ? 1.5 : 1);
    if (isRetry) recallSearchRadiusMeters = Math.round(recallSearchRadiusMeters);
    var recallSearchRequest = { location: latLng, radius: recallSearchRadiusMeters, type: recallPlaceType };
    dealerAndShopPlacesService.nearbySearch(recallSearchRequest, function(places, status) {
        var recallResultsList = document.getElementById('results');
        var dealerRecallNoteEl = document.getElementById('recallNote');
        recallResultsList.innerHTML = '';
        dealerRecallNoteEl.style.display = 'none';

        if (status !== google.maps.places.PlacesServiceStatus.OK || !places || !places.length) {
            if (status === google.maps.places.PlacesServiceStatus.ZERO_RESULTS && !isRetry) {
                runRecallRepairSearch(latLng, recallPlaceType, true);
                return;
            }
            if (status === google.maps.places.PlacesServiceStatus.OVER_QUERY_LIMIT) {
                recallResultsList.innerHTML = '<li class="none">Too many searches for now. Wait a minute and try again—recall lookups are still available from the home page.</li>';
                showRecallSearchError('API limit reached. Please wait a moment before searching again.');
                console.warn('RecallShield OVER_QUERY_LIMIT', { recallPlaceType: recallPlaceType });
                return;
            }
            if (status === google.maps.places.PlacesServiceStatus.REQUEST_DENIED || status === google.maps.places.PlacesServiceStatus.UNKNOWN) {
                recallResultsList.innerHTML = '<li class="none">Search unavailable. If this keeps happening we\'ll log it for the recall team.</li>';
                showRecallSearchError('Search failed. Try again or use the home page for recall info.');
                console.warn('RecallShield Places error', { recallPlaceType: recallPlaceType, status: status });
                return;
            }
            var emptyMsg;
            if (recallPlaceType === 'car_dealer')
                emptyMsg = 'No dealers found in that area. Try a different address or a wider search.';
            else
                emptyMsg = 'No garages or body shops in that area. Try a different address or shop type.';
            recallResultsList.innerHTML = '<li class="none">' + emptyMsg + '</li>';
            console.warn('RecallShield search failed', { recallPlaceType: recallPlaceType, status: status, radiusM: recallSearchRadiusMeters });
            return;
        }

        console.log('RecallShield search ok', { recallPlaceType: recallPlaceType, count: places.length });
        for (var i = 0; i < places.length; i++) {
            var place = places[i];
            var li = document.createElement('li');
            var addressStr = place.vicinity || (place.formatted_address || '');
            li.textContent = (place.name || '') + ' — ' + addressStr +
                (recallPlaceType === 'car_dealer' ? ' (recall authorized)' : '');
            recallResultsList.appendChild(li);
            new google.maps.Marker({ map: recallRepairMap, position: place.geometry.location, title: place.name });
        }
        if (recallPlaceType === 'car_dealer') {
            dealerRecallNoteEl.textContent = 'Dealers perform manufacturer recall repairs at no cost when your VIN is under an open recall.';
            dealerRecallNoteEl.style.display = 'block';
        }
    });
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($apiKey); ?>&libraries=places&callback=initMap" async defer></script>

</body>
</html>

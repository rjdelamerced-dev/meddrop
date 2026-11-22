document.addEventListener('DOMContentLoaded', () => {
  const menu = document.getElementById('mobile-nav');
  const scrim = document.getElementById('nav-scrim');
  const closeMenu = () => { menu?.classList.remove('open'); scrim?.classList.remove('open'); };
  document.getElementById('menu-toggle')?.addEventListener('click', () => { menu?.classList.add('open'); scrim?.classList.add('open'); });
  document.getElementById('menu-close')?.addEventListener('click', closeMenu);
  scrim?.addEventListener('click', closeMenu);

  document.querySelectorAll('[data-qty]').forEach(button => button.addEventListener('click', () => {
    const input = button.parentElement.querySelector('input');
    input.value = Math.max(0, Number(input.value) + (button.dataset.qty === 'plus' ? 1 : -1));
  }));

  const gcash = document.getElementById('payment-method');
  const gcashField = document.getElementById('gcash-field');
  const syncPayment = () => {
    if (!gcash || !gcashField) return;
    gcashField.classList.toggle('hidden', gcash.value !== 'GCash');
    gcashField.querySelector('input').required = gcash.value === 'GCash';
  };
  gcash?.addEventListener('change', syncPayment); syncPayment();

  const seniorCheck = document.getElementById('senior-check');
  const seniorUpload = document.getElementById('senior-upload');
  const syncSenior = () => {
    if (!seniorCheck || !seniorUpload) return;
    seniorUpload.classList.toggle('hidden', !seniorCheck.checked);
    const file = document.getElementById('senior-file');
    if (file) file.required = seniorCheck.checked;
    document.getElementById('discount-line')?.classList.toggle('hidden', !seniorCheck.checked);
    document.getElementById('discount-total')?.classList.toggle('hidden', !seniorCheck.checked);
  };
  seniorCheck?.addEventListener('change', syncSenior); syncSenior();
  document.querySelectorAll('input[type="file"]').forEach(input => input.addEventListener('change', () => {
    const target = input.closest('label')?.querySelector('.file-name');
    if (target) target.textContent = input.files?.[0]?.name || 'No file chosen';
  }));

  const closeModals = () => {
    document.querySelectorAll('.modal').forEach(modal => modal.classList.add('hidden'));
    document.body.classList.remove('modal-open');
  };
  document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', closeModals));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModals(); });
  const previewModal = document.getElementById('preview-modal');
  const previewBody = document.getElementById('preview-body');
  document.querySelectorAll('[data-preview]').forEach(button => button.addEventListener('click', () => {
    const url = button.dataset.preview;
    if (!previewModal || !previewBody) return;
    previewBody.innerHTML = url.toLowerCase().endsWith('.pdf') ? `<iframe title="Uploaded document" src="${url}"></iframe>` : `<img alt="Uploaded document preview" src="${url}">`;
    previewModal.classList.remove('hidden'); document.body.classList.add('modal-open');
  }));

  const medicineModal = document.getElementById('medicine-modal');
  const medicineFields = {
    id: document.getElementById('medicine-id'), name: document.getElementById('medicine-name'),
    description: document.getElementById('medicine-description'), category: document.getElementById('medicine-category'),
    type: document.getElementById('medicine-type'), dosage: document.getElementById('medicine-dosage'),
    price: document.getElementById('medicine-price'), batch: document.getElementById('medicine-batch'),
    expiry: document.getElementById('medicine-expiry'), stock: document.getElementById('medicine-stock')
  };
  const openMedicine = values => {
    if (!medicineModal) return;
    document.getElementById('medicine-modal-title').textContent = values ? 'Restock medicine' : 'Add a medicine';
    Object.entries(medicineFields).forEach(([key, input]) => {
      if (input) input.value = values?.[key] ?? (key === 'stock' ? '0' : '');
    });
    medicineModal.classList.remove('hidden'); document.body.classList.add('modal-open');
  };
  document.querySelector('[data-medicine-add]')?.addEventListener('click', () => openMedicine(null));
  document.querySelectorAll('.edit-medicine').forEach(button => button.addEventListener('click', () => {
    try { openMedicine(JSON.parse(button.dataset.medicine)); } catch { openMedicine(null); }
  }));

  const profileModal = document.getElementById('profile-modal');
  document.querySelector('[data-profile-open]')?.addEventListener('click', () => {
    profileModal?.classList.remove('hidden'); document.body.classList.add('modal-open');
  });
  document.getElementById('profile-photo-input')?.addEventListener('change', event => {
    const file = event.target.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;
    const image = document.createElement('img');
    image.className = 'profile-preview-avatar avatar-photo'; image.alt = 'New profile photo preview';
    image.src = URL.createObjectURL(file);
    document.querySelector('#profile-preview img, #profile-preview .avatar-fallback')?.replaceWith(image);
  });

  const historySearch = document.getElementById('pharmacist-history-search');
  historySearch?.addEventListener('input', () => {
    const term = historySearch.value.trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('#pharmacist-history-table tbody tr').forEach(row => {
      const match = row.dataset.search.includes(term);
      row.classList.toggle('hidden', !match); if (match) shown++;
    });
    document.getElementById('history-no-results')?.classList.toggle('hidden', shown !== 0 || term === '');
  });

  if (!window.L) return;
  const PHARMACY = [14.4793, 120.8964];
  const OSRM = 'https://router.project-osrm.org/route/v1/driving/';
  const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const mapStates = new Map();
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const markerIcon = kind => L.divIcon({
    className: 'meddrop-map-icon',
    html: `<span class="map-pin ${kind}"><i>${kind === 'pharmacy' ? '✚' : kind === 'rider' ? '➤' : '●'}</i></span>`,
    iconSize: [34, 40], iconAnchor: [17, 36]
  });
  const getPoint = (element, latKey, lngKey) => {
    if (!element.dataset[latKey] || !element.dataset[lngKey]) return null;
    const point = [Number(element.dataset[latKey]), Number(element.dataset[lngKey])];
    return point.every(Number.isFinite) ? point : null;
  };
  const makeMap = (element, center, zoom = 13) => {
    const map = L.map(element, { scrollWheelZoom: false }).setView(center, zoom);
    L.tileLayer(TILE_URL, { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>' }).addTo(map);
    return map;
  };
  const haversine = (a, b) => {
    const rad = deg => deg * Math.PI / 180;
    const dLat = rad(b[0] - a[0]), dLng = rad(b[1] - a[1]);
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  };
  const straightRoute = points => {
    const distance = points.slice(1).reduce((sum, point, i) => sum + haversine(points[i], point), 0) * 1.28;
    const remainingDistance = haversine(points[points.length - 2] || points[0], points[points.length - 1]) * 1.28;
    return { coordinates: points, distance, duration: (distance / 22) * 3600 + 240, remainingDistance, remainingDuration: (remainingDistance / 22) * 3600 + 240, source: 'estimate' };
  };
  const getOsrmRoute = async points => {
    const coordinates = points.map(([lat, lng]) => `${lng},${lat}`).join(';');
    try {
      const response = await fetch(`${OSRM}${coordinates}?overview=full&geometries=geojson&steps=false`, { cache: 'no-store', signal: AbortSignal.timeout(9000) });
      if (!response.ok) throw new Error('Routing service unavailable');
      const result = await response.json();
      const route = result.routes?.[0];
      if (!route?.geometry?.coordinates?.length) throw new Error('No road route found');
      return {
        coordinates: route.geometry.coordinates.map(([lng, lat]) => [lat, lng]),
        distance: route.distance / 1000,
        duration: route.duration,
        remainingDistance: route.legs?.length > 1 ? route.legs[route.legs.length - 1].distance / 1000 : route.distance / 1000,
        remainingDuration: route.legs?.length > 1 ? route.legs[route.legs.length - 1].duration : route.duration,
        source: 'osrm'
      };
    } catch {
      return straightRoute(points);
    }
  };
  const fitMap = (map, points) => {
    const valid = points.filter(Boolean);
    if (valid.length > 1) map.fitBounds(L.latLngBounds(valid).pad(.16), { maxZoom: 15 });
    else if (valid.length) map.setView(valid[0], 14);
  };
  const etaText = (element, distance, duration) => {
    if (element) element.textContent = distance < .05 ? 'Arriving' : `${distance.toFixed(1)} km`;
    const eta = document.getElementById('arrival-estimate');
    if (eta) eta.textContent = distance < .05 ? 'Arriving' : `${Math.max(1, Math.ceil(duration / 60))} min`;
  };
  const updateEtaFromPosition = (state, point) => {
    const straight = haversine(point, state.destination);
    const roadFactor = state.roadFactor || 1.28;
    const distance = straight * roadFactor;
    etaText(state.distanceNode, distance, (distance / 22) * 3600 + 240);
  };
  const setRoadLine = (state, route) => {
    state.routeLine.setLatLngs(route.coordinates);
    const sourceLabel = state.element?.closest('.live-map-section')?.querySelector('.route-source');
    if (sourceLabel) sourceLabel.textContent = route.source === 'osrm' ? 'Road route · OSRM' : 'Straight-line estimate';
    const remainingStraight = state.riderPoint ? haversine(state.riderPoint, state.destination) : 0;
    if (remainingStraight > 0 && route.remainingDistance) state.roadFactor = Math.max(1.03, route.remainingDistance / remainingStraight);
    if (state.distanceNode) {
      state.distanceNode.dataset.routeSource = route.source;
      const dist = route.remainingDistance ?? route.distance;
      const duration = route.remainingDuration ?? route.duration;
      etaText(state.distanceNode, dist, duration);
    }
  };
  const refreshRoadRoute = async (state, points) => {
    if(state.routing)return null;
    state.routing=true;state.lastRouteAt=Date.now();
    try { const route = await getOsrmRoute(points);setRoadLine(state, route);return route; }
    finally { state.routing=false; }
  };
  const animateMarker = (marker, target, duration = 1800) => {
    const start = marker.getLatLng();
    const from = [start.lat, start.lng];
    const began = performance.now();
    const animate = now => {
      const ratio = Math.min(1, (now - began) / duration);
      const eased = ratio * ratio * (3 - 2 * ratio);
      marker.setLatLng([from[0] + (target[0] - from[0]) * eased, from[1] + (target[1] - from[1]) * eased]);
      if (ratio < 1) requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
  };
  const sendRiderPosition = async (orderId, point) => {
    const body = new URLSearchParams({ _csrf: csrf, order_id: String(orderId), lat: String(point[0]), lng: String(point[1]) });
    const response = await fetch('api/update_rider_location.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Could not update rider location.');
    return result;
  };

  const checkoutElement = document.getElementById('checkout-map');
  if (checkoutElement) {
    const center = [Number(checkoutElement.dataset.centerLat), Number(checkoutElement.dataset.centerLng)];
    const map = makeMap(checkoutElement, center, 12);
    const latInput = document.getElementById('delivery-lat'), lngInput = document.getElementById('delivery-lng');
    const note = document.getElementById('checkout-location-note');
    let pin = null;
    const setPin = (point, message = 'Delivery location selected') => {
      const value = [Number(point[0]), Number(point[1])];
      latInput.value = value[0].toFixed(7); lngInput.value = value[1].toFixed(7);
      note.textContent = `${message} · ${value[0].toFixed(5)}, ${value[1].toFixed(5)}`;
      if (!pin) pin = L.marker(value, { draggable: true, icon: markerIcon('customer') }).addTo(map);
      else pin.setLatLng(value);
      pin.bindPopup('Your delivery location').off('dragend').on('dragend', event => setPin(event.target.getLatLng(), 'Pin moved'));
      map.panTo(value);
    };
    map.on('click', event => setPin([event.latlng.lat, event.latlng.lng], 'Map location selected'));
    document.getElementById('use-current-location')?.addEventListener('click', () => {
      if (!navigator.geolocation) { note.textContent = 'Location is unavailable. Tap the map to pin your address.'; return; }
      note.textContent = 'Finding your current location…';
      navigator.geolocation.getCurrentPosition(position => { setPin([position.coords.latitude, position.coords.longitude], 'Current location pinned'); map.setZoom(16); }, () => { note.textContent = 'Could not get your location. Check browser permission or tap the map.'; }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
    });
    window.setTimeout(() => map.invalidateSize(), 120);
  }

  const trackingElement = document.getElementById('customer-tracking-map');
  if (trackingElement) {
    const destination = getPoint(trackingElement, 'deliveryLat', 'deliveryLng');
    const savedRider = getPoint(trackingElement, 'riderLat', 'riderLng');
    if (destination) {
      const pharmacy = [Number(trackingElement.dataset.pharmacyLat), Number(trackingElement.dataset.pharmacyLng)];
      const map = makeMap(trackingElement, destination, 13);
      L.marker(pharmacy, { icon: markerIcon('pharmacy') }).addTo(map).bindPopup('MedDrop pharmacy');
      L.marker(destination, { icon: markerIcon('customer') }).addTo(map).bindPopup('Your delivery location');
      const routeLine = L.polyline([pharmacy, destination], { color: '#10a283', weight: 4, opacity: .82 }).addTo(map);
      const status = trackingElement.dataset.status;
      let riderPoint = savedRider || (status === 'approved' ? pharmacy : null);
      let riderMarker = riderPoint ? L.marker(riderPoint, { icon: markerIcon('rider'), zIndexOffset: 500 }).addTo(map) : null;
      const distanceNode = document.getElementById('distance-remaining');
      const state = { map, element: trackingElement, destination, riderPoint, riderMarker, routeLine, distanceNode, roadFactor: 1.28, lastRouteAt: 0 };
      fitMap(map, [pharmacy, destination, riderPoint]);
      if (riderPoint) {
        refreshRoadRoute(state, status === 'out_for_delivery' ? [pharmacy, riderPoint, destination] : [pharmacy, destination]).then(route => {
          if(!route)return;
          state.roadFactor = route.remainingDistance && haversine(riderPoint, destination) ? route.remainingDistance / haversine(riderPoint, destination) : 1.28;
          etaText(distanceNode, route.remainingDistance ?? route.distance, route.remainingDuration ?? route.duration);
        });
      } else {
        state.distanceNode=null;
        refreshRoadRoute(state,[pharmacy,destination]);
        distanceNode.textContent = 'Waiting for rider';
        document.getElementById('arrival-estimate').textContent = 'ETA after acceptance';
      }

      const updateRiderProfile = rider => {
        const nameNode = document.getElementById('tracking-rider-name');
        const statusNode = document.getElementById('tracking-rider-status');
        const avatarNode = document.getElementById('tracking-rider-avatar');
        if (!nameNode || !statusNode || !avatarNode) return;
        const name = rider.rider_name || '';
        nameNode.textContent = name || 'Finding a rider';
        statusNode.textContent = name ? (rider.status === 'out_for_delivery' ? 'On the way to you' : 'Preparing your delivery') : 'We’ll show your rider when they accept the delivery';
        avatarNode.replaceChildren();
        if (rider.rider_photo_url) {
          const image = document.createElement('img'); image.className = 'tracking-avatar avatar-photo'; image.alt = `${name} profile photo`; image.src = rider.rider_photo_url; avatarNode.append(image);
        } else {
          const fallback = document.createElement('span'); fallback.className = 'tracking-avatar avatar-fallback'; fallback.textContent = name ? name.split(/\s+/).map(part => part[0]).slice(0, 2).join('').toUpperCase() : 'MD'; avatarNode.append(fallback);
        }
      };
      const updateStatusLabel = current => {
        const label = document.getElementById('order-live-status');
        if (label) label.textContent = ({ pending_review: 'Pending Pharmacist Review', approved: 'Approved & Preparing', out_for_delivery: 'Out for Delivery', delivered: 'Delivered', rejected: 'Rejected' })[current] || current;
      };
      const poll = async () => {
        try {
          const response = await fetch(`api/track_order.php?order_id=${encodeURIComponent(trackingElement.dataset.orderId)}`, { credentials: 'same-origin', cache: 'no-store' });
          if (!response.ok) return;
          const order = await response.json();
          trackingElement.dataset.status = order.status;
          updateRiderProfile(order); updateStatusLabel(order.status);
          const next = order.rider_lat !== null && order.rider_lng !== null ? [Number(order.rider_lat), Number(order.rider_lng)] : (order.status === 'approved' ? pharmacy : null);
          if (next) {
            state.distanceNode=distanceNode;
            if (!riderMarker) riderMarker = L.marker(next, { icon: markerIcon('rider'), zIndexOffset: 500 }).addTo(map);
            else animateMarker(riderMarker, next, 2200);
            state.riderMarker = riderMarker; state.riderPoint = next;
            if (Date.now() - state.lastRouteAt > 15000) refreshRoadRoute(state, [pharmacy, next, destination]);
            updateEtaFromPosition(state, next);
          } else if (riderMarker) { map.removeLayer(riderMarker); riderMarker = null; state.riderMarker = null; }
        } catch { /* Polling resumes on the next three-second interval. */ }
      };
      if (!['delivered', 'rejected'].includes(status)) window.setInterval(poll, 3000);
      window.setTimeout(() => map.invalidateSize(), 150);
    }
  }

  const makeRiderMap = element => {
    const destination = getPoint(element, 'deliveryLat', 'deliveryLng');
    if (!destination) { element.innerHTML = '<div class="map-unavailable">Customer has not pinned a map location.</div>'; return null; }
    const pharmacy = [Number(element.dataset.pharmacyLat), Number(element.dataset.pharmacyLng)];
    const status = element.dataset.status || 'approved';
    const saved = getPoint(element, 'riderLat', 'riderLng');
    const riderPoint = saved || pharmacy;
    const map = makeMap(element, destination, 13);
    L.marker(pharmacy, { icon: markerIcon('pharmacy') }).addTo(map).bindPopup('MedDrop pharmacy');
    L.marker(destination, { icon: markerIcon('customer') }).addTo(map).bindPopup('Customer delivery pin');
    const routeLine = L.polyline([pharmacy, destination], { color: '#10a283', weight: 3, opacity: .7 }).addTo(map);
    const riderMarker = L.marker(riderPoint, { icon: markerIcon('rider'), zIndexOffset: 500 }).addTo(map).bindPopup(status === 'approved' ? 'Ready for a rider to accept' : 'Your rider location');
    fitMap(map, [pharmacy, destination, status === 'approved' ? null : riderPoint]);
    const card = element.closest('.delivery-card');
    const statusNode = card?.querySelector('.gps-status');
    const state = { map, element, destination, pharmacy, riderPoint, riderMarker, routeLine, statusNode, roadFactor: 1.28, roadCoordinates: [riderPoint, destination], lastRouteAt: 0 };
    mapStates.set(`rider-${element.dataset.orderId}`, state);
    refreshRoadRoute(state, status === 'approved' ? [pharmacy, destination] : [riderPoint, destination]).then(route => { if(!route)return;state.roadCoordinates = route.coordinates; state.roadFactor = route.remainingDistance / Math.max(.001, haversine(riderPoint, destination)); updateEtaFromPosition(state, riderPoint); });
    window.setTimeout(() => map.invalidateSize(), 150);
    return state;
  };
  document.querySelectorAll('.rider-map').forEach(makeRiderMap);

  document.querySelectorAll('.gps-share').forEach(button => button.addEventListener('click', () => {
    const state = mapStates.get(`rider-${button.dataset.orderId}`);
    if (!state) return;
    if (!navigator.geolocation) { if (state.statusNode) state.statusNode.textContent = 'GPS is unavailable in this browser.'; return; }
    if (button.dataset.watchId) {
      navigator.geolocation.clearWatch(Number(button.dataset.watchId)); delete button.dataset.watchId;
      button.textContent = '◎ Share Live GPS'; if (state.statusNode) state.statusNode.textContent = 'Location sharing is off'; return;
    }
    button.textContent = '■ Stop Sharing';
    let lastSentAt = 0;
    button.dataset.watchId = String(navigator.geolocation.watchPosition(async position => {
      const point = [position.coords.latitude, position.coords.longitude];
      state.riderPoint = point; state.riderMarker.setLatLng(point); state.map.panTo(point);
      updateEtaFromPosition(state, point);
      if (Date.now() - lastSentAt < 1800) return;
      lastSentAt = Date.now();
      try {
        await sendRiderPosition(button.dataset.orderId, point);
        if (state.statusNode) state.statusNode.textContent = 'Live GPS location shared';
        if (Date.now() - state.lastRouteAt > 15000) refreshRoadRoute(state, [point, state.destination]);
      } catch (error) { if (state.statusNode) state.statusNode.textContent = error.message; }
    }, error => { if (state.statusNode) state.statusNode.textContent = error.code === 1 ? 'Allow location access to share GPS.' : 'GPS signal unavailable. Try the movement demo.'; }, { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 }));
  }));

  const resamplePath = (points, count = 12) => {
    if (points.length < 2) return points;
    const lengths = [0];
    for (let i = 1; i < points.length; i++) lengths.push(lengths[i - 1] + haversine(points[i - 1], points[i]));
    const total = lengths[lengths.length - 1];
    if (!total) return Array(count).fill(points[0]);
    return Array.from({ length: count }, (_, index) => {
      const target = total * index / (count - 1);
      let segment = lengths.findIndex(length => length >= target);
      if (segment <= 0) return points[0];
      const span = lengths[segment] - lengths[segment - 1];
      const fraction = span ? (target - lengths[segment - 1]) / span : 0;
      return [points[segment - 1][0] + (points[segment][0] - points[segment - 1][0]) * fraction, points[segment - 1][1] + (points[segment][1] - points[segment - 1][1]) * fraction];
    });
  };
  const animateTo = (state, destination, duration) => new Promise(resolve => {
    const start = state.riderMarker.getLatLng(); const from = [start.lat, start.lng]; const begun = performance.now();
    const step = now => {
      const progress = Math.min(1, (now - begun) / duration); const eased = progress * (2 - progress);
      const point = [from[0] + (destination[0] - from[0]) * eased, from[1] + (destination[1] - from[1]) * eased];
      state.riderPoint = point; state.riderMarker.setLatLng(point); updateEtaFromPosition(state, point);
      if (progress < 1) requestAnimationFrame(step); else resolve();
    };
    requestAnimationFrame(step);
  });
  document.querySelectorAll('.simulate-rider').forEach(button => button.addEventListener('click', async () => {
    const state = mapStates.get(`rider-${button.dataset.orderId}`);
    if (!state || button.dataset.running === '1') return;
    const gpsButton = document.querySelector(`.gps-share[data-order-id="${button.dataset.orderId}"]`);
    if (gpsButton?.dataset.watchId) gpsButton.click();
    button.dataset.running = '1'; button.disabled = true; button.textContent = 'Loading road route…';
    try {
      const current = state.riderMarker.getLatLng();
      const route = await getOsrmRoute([[current.lat, current.lng], state.destination]);
      const steps = resamplePath(route.coordinates, 12);
      state.roadCoordinates = route.coordinates;
      state.routeLine.setLatLngs(route.coordinates);
      state.roadFactor = route.remainingDistance / Math.max(.001, haversine([current.lat, current.lng], state.destination));
      button.textContent = 'Rider is moving…';
      for (let i = 1; i < steps.length; i++) {
        await animateTo(state, steps[i], 1750);
        await sendRiderPosition(button.dataset.orderId, steps[i]);
        if (state.statusNode) state.statusNode.textContent = `Live route · step ${i} of ${steps.length - 1}`;
        if (i < steps.length - 1) await new Promise(resolve => window.setTimeout(resolve, 250));
      }
      if (state.statusNode) state.statusNode.textContent = 'Rider arrived at the delivery pin';
    } catch (error) {
      if (state.statusNode) state.statusNode.textContent = error.message || 'Could not complete the movement demo.';
    } finally {
      button.dataset.running = '0'; button.disabled = false; button.textContent = '↻ Simulate Rider Movement';
    }
  }));
});

const CACHE_NAME = 'hackmate-v1.0.0';
const STATIC_CACHE_NAME = 'hackmate-static-v1.0.0';
const DYNAMIC_CACHE_NAME = 'hackmate-dynamic-v1.0.0';

// Files to cache immediately
const STATIC_FILES = [
  '/',
  '/login.php',
  '/assets/css/style.css',
  '/assets/css/tailwind.css',
  '/assets/js/main.js',
  '/assets/js/pwa.js',
  '/manifest.json',
  '/assets/icons/icon-192x192.png',
  '/assets/icons/icon-512x512.png',
  '/assets/icons/apple-touch-icon.png',
  // Add offline fallback page
  '/offline.html'
];

// Network-first resources (dynamic content)
const NETWORK_FIRST = [
  '/admin/',
  '/mentor/',
  '/participant/',
  '/volunteer/',
  '/ajax/',
  '/api/'
];

// Cache-first resources (static assets)
const CACHE_FIRST = [
  '/assets/',
  '/public/',
  '/images/',
  '.css',
  '.js',
  '.png',
  '.jpg',
  '.jpeg',
  '.svg',
  '.gif',
  '.webp',
  '.ico'
];

// Install event - cache static files
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(STATIC_CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching static files');
        return cache.addAll(STATIC_FILES);
      })
      .then(() => {
        console.log('Service Worker: Static files cached');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Error caching static files:', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== STATIC_CACHE_NAME && cacheName !== DYNAMIC_CACHE_NAME) {
            console.log('Service Worker: Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker: Activated');
      return self.clients.claim();
    })
  );
});

// Fetch event - handle requests with appropriate strategy
self.addEventListener('fetch', event => {
  const requestUrl = new URL(event.request.url);
  
  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Skip chrome-extension and other protocols
  if (!requestUrl.protocol.startsWith('http')) {
    return;
  }

  event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
  const requestUrl = new URL(request.url);
  const pathname = requestUrl.pathname;

  try {
    // Check if it's a network-first resource
    if (NETWORK_FIRST.some(pattern => pathname.startsWith(pattern))) {
      return await networkFirst(request);
    }

    // Check if it's a cache-first resource
    if (CACHE_FIRST.some(pattern => pathname.includes(pattern) || pathname.endsWith(pattern))) {
      return await cacheFirst(request);
    }

    // Default: network-first for HTML pages
    if (request.headers.get('accept')?.includes('text/html')) {
      return await networkFirst(request);
    }

    // For other resources, try cache first
    return await cacheFirst(request);

  } catch (error) {
    console.error('Service Worker: Error handling request:', error);
    
    // Return offline page for HTML requests
    if (request.headers.get('accept')?.includes('text/html')) {
      const offlineResponse = await caches.match('/offline.html');
      return offlineResponse || new Response('Offline', { status: 503 });
    }
    
    // Return cached version or error for other requests
    const cachedResponse = await caches.match(request);
    return cachedResponse || new Response('Resource not available offline', { status: 503 });
  }
}

// Network-first strategy
async function networkFirst(request) {
  try {
    const networkResponse = await fetch(request);
    
    // Cache successful responses
    if (networkResponse.ok) {
      const cache = await caches.open(DYNAMIC_CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // Network failed, try cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    throw error;
  }
}

// Cache-first strategy
async function cacheFirst(request) {
  const cachedResponse = await caches.match(request);
  
  if (cachedResponse) {
    // Update cache in background
    fetch(request).then(networkResponse => {
      if (networkResponse.ok) {
        const cache = caches.open(DYNAMIC_CACHE_NAME);
        cache.then(c => c.put(request, networkResponse));
      }
    }).catch(() => {
      // Ignore network errors in background update
    });
    
    return cachedResponse;
  }

  // Not in cache, fetch from network
  const networkResponse = await fetch(request);
  
  if (networkResponse.ok) {
    const cache = await caches.open(DYNAMIC_CACHE_NAME);
    cache.put(request, networkResponse.clone());
  }
  
  return networkResponse;
}

// Background sync for form submissions
self.addEventListener('sync', event => {
  console.log('Service Worker: Background sync triggered:', event.tag);
  
  if (event.tag === 'form-submission') {
    event.waitUntil(syncFormSubmissions());
  }
});

async function syncFormSubmissions() {
  try {
    // Get pending form submissions from IndexedDB
    const submissions = await getPendingSubmissions();
    
    for (const submission of submissions) {
      try {
        const response = await fetch(submission.url, {
          method: submission.method,
          headers: submission.headers,
          body: submission.body
        });
        
        if (response.ok) {
          await removePendingSubmission(submission.id);
          console.log('Service Worker: Form submission synced successfully');
        }
      } catch (error) {
        console.error('Service Worker: Error syncing form submission:', error);
      }
    }
  } catch (error) {
    console.error('Service Worker: Error in background sync:', error);
  }
}

// Push notification handling
self.addEventListener('push', event => {
  console.log('Service Worker: Push message received');
  
  let notificationData = {
    title: 'HackMate Notification',
    body: 'You have a new update!',
    url: '/dashboard/',
    tag: 'hackmate-general'
  };
  
  // Parse push data if available
  if (event.data) {
    try {
      const payload = event.data.json();
      notificationData = {
        title: payload.title || notificationData.title,
        body: payload.body || payload.message || notificationData.body,
        url: payload.url || payload.action_url || notificationData.url,
        tag: payload.tag || payload.id || notificationData.tag,
        icon: payload.icon,
        image: payload.image,
        badge: payload.badge,
        requireInteraction: payload.requireInteraction || false,
        silent: payload.silent || false,
        timestamp: payload.timestamp || Date.now(),
        data: payload.data || {}
      };
    } catch (error) {
      console.error('Service Worker: Error parsing push data:', error);
      // Fallback to text content
      notificationData.body = event.data.text() || notificationData.body;
    }
  }
  
  const options = {
    body: notificationData.body,
    icon: notificationData.icon || '/assets/img/icons/icon-192x192.png',
    badge: notificationData.badge || '/assets/img/icons/badge-72x72.png',
    tag: notificationData.tag,
    requireInteraction: notificationData.requireInteraction,
    silent: notificationData.silent,
    timestamp: notificationData.timestamp,
    data: {
      url: notificationData.url,
      timestamp: notificationData.timestamp,
      ...notificationData.data
    },
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/assets/img/icons/action-view.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/assets/img/icons/action-dismiss.png'
      }
    ]
  };
  
  // Add image if provided
  if (notificationData.image) {
    options.image = notificationData.image;
  }
  
  // Add vibration pattern
  if (!notificationData.silent) {
    options.vibrate = [200, 100, 200];
  }

  event.waitUntil(
    self.registration.showNotification(notificationData.title, options)
  );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Notification clicked', {
    action: event.action,
    data: event.notification.data
  });
  
  event.notification.close();
  
  // Handle different actions
  if (event.action === 'view') {
    const url = event.notification.data?.url || '/dashboard/';
    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then(clientList => {
          // Check if app is already open
          for (const client of clientList) {
            if (client.url.includes(new URL(url, self.location.origin).pathname) && 'focus' in client) {
              return client.focus();
            }
          }
          // If not open, open new window
          if (clients.openWindow) {
            return clients.openWindow(url);
          }
        })
    );
  } else if (event.action === 'dismiss') {
    // Just close the notification (already done above)
    return;
  } else {
    // Default click (no action) - open the URL or dashboard
    const url = event.notification.data?.url || '/dashboard/';
    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then(clientList => {
          // Check if app is already open
          for (const client of clientList) {
            if (client.url.includes(new URL(url, self.location.origin).pathname) && 'focus' in client) {
              return client.focus();
            }
          }
          // If not open, open new window
          if (clients.openWindow) {
            return clients.openWindow(url);
          }
        })
    );
  }
});

// Helper functions for IndexedDB operations
async function getPendingSubmissions() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('hackmate-sync', 1);
    
    request.onerror = () => reject(request.error);
    request.onsuccess = () => {
      const db = request.result;
      const transaction = db.transaction(['submissions'], 'readonly');
      const store = transaction.objectStore('submissions');
      const getAll = store.getAll();
      
      getAll.onsuccess = () => resolve(getAll.result);
      getAll.onerror = () => reject(getAll.error);
    };
    
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains('submissions')) {
        db.createObjectStore('submissions', { keyPath: 'id', autoIncrement: true });
      }
    };
  });
}

async function removePendingSubmission(id) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('hackmate-sync', 1);
    
    request.onerror = () => reject(request.error);
    request.onsuccess = () => {
      const db = request.result;
      const transaction = db.transaction(['submissions'], 'readwrite');
      const store = transaction.objectStore('submissions');
      const deleteRequest = store.delete(id);
      
      deleteRequest.onsuccess = () => resolve();
      deleteRequest.onerror = () => reject(deleteRequest.error);
    };
  });
}

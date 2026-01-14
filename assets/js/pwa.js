/**
 * PWA Management JavaScript
 * Handles installation prompts, service worker registration, and PWA features
 */

class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.isOnline = navigator.onLine;
        this.installButton = null;
        this.updateAvailable = false;
        this.notificationPermission = 'default';
        this.isSubscribedToPush = false;
        this.lastNotificationCheck = null;
        this.notificationCheckInterval = null;
        this.vapidPublicKey = 'DEMO_KEY_REPLACE_IN_PRODUCTION';
        
        this.init();
    }

    async init() {
        // Register service worker
        await this.registerServiceWorker();
        
        // Setup installation prompt
        this.setupInstallPrompt();
        
        // Setup online/offline detection
        this.setupNetworkDetection();
        
        // Setup update detection
        this.setupUpdateDetection();
        
        // Setup form sync for offline submissions
        this.setupFormSync();
        
        // Check if already installed
        this.checkInstallStatus();
        
        // Initialize notification system
        await this.initNotifications();
        
        console.log('PWA Manager initialized');
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });
                
                console.log('Service Worker registered successfully:', registration);
                
                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                this.showUpdateNotification();
                            }
                        });
                    }
                });
                
                return registration;
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }

    setupInstallPrompt() {
        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWA install prompt available');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
            
            // Show notification about install availability
            setTimeout(() => {
                this.showInstallNotification();
            }, 3000);
        });

        // Listen for app installed event
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            this.isInstalled = true;
            this.hideInstallButton();
            this.showInstalledNotification();
        });

        // Create install button
        this.createInstallButton();
        
        // Check for Android-specific installation
        this.setupAndroidInstallCheck();
    }

    createInstallButton() {
        // Check if button already exists
        if (document.getElementById('pwa-install-btn')) return;

        const installBtn = document.createElement('button');
        installBtn.id = 'pwa-install-btn';
        installBtn.innerHTML = `
            <i class="fas fa-download mr-2"></i>
            Install App
        `;
        installBtn.className = `
            fixed bottom-4 right-4 z-50 bg-indigo-600 hover:bg-indigo-700 
            text-white px-4 py-2 rounded-lg shadow-lg transition-all duration-300
            flex items-center text-sm font-medium hidden
        `;
        
        installBtn.addEventListener('click', () => this.promptInstall());
        
        document.body.appendChild(installBtn);
        this.installButton = installBtn;
    }

    showInstallButton() {
        if (this.installButton && !this.isInstalled) {
            this.installButton.classList.remove('hidden');
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                if (this.installButton && !this.installButton.classList.contains('hidden')) {
                    this.installButton.style.opacity = '0.7';
                }
            }, 10000);
        }
    }

    hideInstallButton() {
        if (this.installButton) {
            this.installButton.classList.add('hidden');
        }
    }

    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('No install prompt available');
            return;
        }

        try {
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log(`User response to install prompt: ${outcome}`);
            
            if (outcome === 'accepted') {
                this.hideInstallButton();
            }
            
            this.deferredPrompt = null;
        } catch (error) {
            console.error('Error showing install prompt:', error);
        }
    }

    setupNetworkDetection() {
        const updateOnlineStatus = () => {
            this.isOnline = navigator.onLine;
            this.showConnectionStatus();
        };

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        
        // Initial status
        updateOnlineStatus();
    }

    showConnectionStatus() {
        const existingToast = document.getElementById('connection-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.id = 'connection-toast';
        toast.className = `
            fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white text-sm
            transition-all duration-300 transform
        `;
        
        if (this.isOnline) {
            toast.className += ' bg-green-600';
            toast.innerHTML = '<i class="fas fa-wifi mr-2"></i>Back online';
        } else {
            toast.className += ' bg-red-600';
            toast.innerHTML = '<i class="fas fa-wifi-slash mr-2"></i>Working offline';
        }

        document.body.appendChild(toast);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    }

    setupUpdateDetection() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'UPDATE_AVAILABLE') {
                    this.showUpdateNotification();
                }
            });
        }
    }

    showUpdateNotification() {
        if (document.getElementById('update-notification')) return;

        const notification = document.createElement('div');
        notification.id = 'update-notification';
        notification.className = `
            fixed top-4 left-1/2 transform -translate-x-1/2 z-50 
            bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-lg
            flex items-center space-x-3 text-sm
        `;
        
        notification.innerHTML = `
            <i class="fas fa-sync-alt animate-spin"></i>
            <span>New version available!</span>
            <button id="update-btn" class="bg-white text-indigo-600 px-3 py-1 rounded text-xs font-medium hover:bg-gray-100">
                Update
            </button>
            <button id="dismiss-update" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        `;

        document.body.appendChild(notification);

        // Handle update button click
        document.getElementById('update-btn').addEventListener('click', () => {
            this.applyUpdate();
        });

        // Handle dismiss button click
        document.getElementById('dismiss-update').addEventListener('click', () => {
            notification.remove();
        });
    }

    async applyUpdate() {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration && registration.waiting) {
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                window.location.reload();
            }
        }
    }

    setupFormSync() {
        // Intercept form submissions for offline sync
        document.addEventListener('submit', (e) => {
            if (!this.isOnline && e.target.tagName === 'FORM') {
                e.preventDefault();
                this.queueFormSubmission(e.target);
            }
        });
    }

    async queueFormSubmission(form) {
        try {
            const formData = new FormData(form);
            const submission = {
                url: form.action || window.location.href,
                method: form.method || 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(formData).toString(),
                timestamp: Date.now()
            };

            await this.saveSubmissionToIndexedDB(submission);
            
            this.showToast('Form saved! Will submit when online.', 'info');
            
            // Register background sync if available
            if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('form-submission');
            }
        } catch (error) {
            console.error('Error queuing form submission:', error);
            this.showToast('Error saving form. Please try again.', 'error');
        }
    }

    async saveSubmissionToIndexedDB(submission) {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('hackmate-sync', 1);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                const db = request.result;
                const transaction = db.transaction(['submissions'], 'readwrite');
                const store = transaction.objectStore('submissions');
                const addRequest = store.add(submission);
                
                addRequest.onsuccess = () => resolve();
                addRequest.onerror = () => reject(addRequest.error);
            };
            
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains('submissions')) {
                    db.createObjectStore('submissions', { keyPath: 'id', autoIncrement: true });
                }
            };
        });
    }

    checkInstallStatus() {
        // Check if running in standalone mode (installed)
        if (window.matchMedia('(display-mode: standalone)').matches || 
            window.navigator.standalone === true) {
            this.isInstalled = true;
            this.hideInstallButton();
        }
    }

    showInstalledNotification() {
        this.showToast('App installed successfully! You can now use it offline.', 'success');
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `
            fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 
            px-6 py-3 rounded-lg shadow-lg text-white text-sm max-w-sm
            transition-all duration-300
        `;
        
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            warning: 'bg-yellow-600',
            info: 'bg-blue-600'
        };
        
        toast.className += ` ${colors[type] || colors.info}`;
        toast.textContent = message;

        document.body.appendChild(toast);

        // Auto-remove after 4 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Public methods for manual control
    async forceUpdate() {
        await this.applyUpdate();
    }

    async clearCache() {
        if ('caches' in window) {
            const cacheNames = await caches.keys();
            await Promise.all(cacheNames.map(name => caches.delete(name)));
            this.showToast('Cache cleared successfully!', 'success');
        }
    }

    setupAndroidInstallCheck() {
        // Check if this is Android Chrome/Samsung Internet
        const isAndroid = /Android/i.test(navigator.userAgent);
        const isChrome = /Chrome/i.test(navigator.userAgent);
        const isSamsung = /SamsungBrowser/i.test(navigator.userAgent);
        
        if (isAndroid && (isChrome || isSamsung)) {
            // Add extra install guidance for Android
            setTimeout(() => {
                if (!this.isInstalled && !this.deferredPrompt && !sessionStorage.getItem('installGuideShown')) {
                    this.showAndroidInstallGuide();
                }
            }, 8000);
        }
    }
    
    showAndroidInstallGuide() {
        const guide = document.createElement('div');
        guide.id = 'android-install-guide';
        guide.className = `
            fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-50
            bg-white rounded-lg shadow-2xl p-6 max-w-sm mx-4
            border border-gray-200
        `;
        
        guide.innerHTML = `
            <div class="text-center">
                <div class="mx-auto h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-mobile-alt text-indigo-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Install HackMate App</h3>
                <p class="text-sm text-gray-600 mb-4">
                    To install this app on your Android device:
                </p>
                <div class="text-left text-sm text-gray-700 space-y-2 mb-4">
                    <div class="flex items-start">
                        <span class="inline-block w-5 h-5 bg-indigo-600 text-white text-xs rounded-full flex items-center justify-center mr-2 mt-0.5">1</span>
                        <span>Tap the menu (⋮) in your browser</span>
                    </div>
                    <div class="flex items-start">
                        <span class="inline-block w-5 h-5 bg-indigo-600 text-white text-xs rounded-full flex items-center justify-center mr-2 mt-0.5">2</span>
                        <span>Look for "Add to Home screen" or "Install app"</span>
                    </div>
                    <div class="flex items-start">
                        <span class="inline-block w-5 h-5 bg-indigo-600 text-white text-xs rounded-full flex items-center justify-center mr-2 mt-0.5">3</span>
                        <span>Tap "Add" or "Install"</span>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button id="close-guide" class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                        Maybe Later
                    </button>
                    <button id="try-install" class="flex-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                        Try Auto Install
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(guide);
        
        // Add event listeners
        document.getElementById('close-guide').addEventListener('click', () => {
            guide.remove();
            // Don't show again for this session
            sessionStorage.setItem('installGuideShown', 'true');
        });
        
        document.getElementById('try-install').addEventListener('click', () => {
            guide.remove();
            this.promptInstall();
        });
        
        // Auto-hide after 15 seconds
        setTimeout(() => {
            if (guide.parentNode) {
                guide.remove();
            }
        }, 15000);
    }
    
    showInstallNotification() {
        if (document.getElementById('install-notification')) return;
        
        const notification = document.createElement('div');
        notification.id = 'install-notification';
        notification.className = `
            fixed top-4 left-1/2 transform -translate-x-1/2 z-50
            bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg
            flex items-center space-x-3 text-sm max-w-sm
        `;
        
        notification.innerHTML = `
            <i class="fas fa-download"></i>
            <span>App can be installed!</span>
            <button id="install-now" class="bg-white text-green-600 px-3 py-1 rounded text-xs font-medium hover:bg-gray-100">
                Install
            </button>
            <button id="dismiss-install" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(notification);
        
        // Handle install button click
        document.getElementById('install-now').addEventListener('click', () => {
            notification.remove();
            this.promptInstall();
        });
        
        // Handle dismiss button click
        document.getElementById('dismiss-install').addEventListener('click', () => {
            notification.remove();
        });
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 8000);
    }

    getInstallStatus() {
        return {
            isInstalled: this.isInstalled,
            isOnline: this.isOnline,
            updateAvailable: this.updateAvailable,
            notificationPermission: this.notificationPermission,
            isSubscribedToPush: this.isSubscribedToPush
        };
    }

    // ============================================
    // NOTIFICATION SYSTEM
    // ============================================

    async initNotifications() {
        try {
            // Check notification permission
            this.notificationPermission = Notification.permission || 'default';
            
            // Check if already subscribed
            await this.checkPushSubscription();
            
            // Setup notification UI if permission is granted
            if (this.notificationPermission === 'granted') {
                this.startNotificationPolling();
            }
            
            console.log('Notification system initialized');
        } catch (error) {
            console.error('Error initializing notifications:', error);
        }
    }

    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            console.warn('This browser does not support notifications');
            return false;
        }

        if (this.notificationPermission === 'granted') {
            return true;
        }

        if (this.notificationPermission === 'denied') {
            this.showToast('Notifications are blocked. Please enable them in browser settings.', 'warning');
            return false;
        }

        try {
            const permission = await Notification.requestPermission();
            this.notificationPermission = permission;
            
            if (permission === 'granted') {
                this.showToast('Notifications enabled! You\'ll receive important updates.', 'success');
                await this.subscribeToPushNotifications();
                this.startNotificationPolling();
                return true;
            } else {
                this.showToast('Notifications disabled. You can enable them later in settings.', 'info');
                return false;
            }
        } catch (error) {
            console.error('Error requesting notification permission:', error);
            return false;
        }
    }

    async subscribeToPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push messaging is not supported');
            return false;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            
            // Check if already subscribed
            let subscription = await registration.pushManager.getSubscription();
            
            if (!subscription) {
                // Subscribe to push notifications
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
                });
            }

            // Send subscription to server
            const success = await this.sendSubscriptionToServer(subscription);
            
            if (success) {
                this.isSubscribedToPush = true;
                console.log('Successfully subscribed to push notifications');
                return true;
            } else {
                console.error('Failed to send subscription to server');
                return false;
            }
        } catch (error) {
            console.error('Error subscribing to push notifications:', error);
            return false;
        }
    }

    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/api/notifications.php?action=subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription,
                    user_agent: navigator.userAgent,
                    timestamp: Date.now()
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            return result.success === true;
        } catch (error) {
            console.error('Error sending subscription to server:', error);
            return false;
        }
    }

    async checkPushSubscription() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return false;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            
            this.isSubscribedToPush = !!subscription;
            return this.isSubscribedToPush;
        } catch (error) {
            console.error('Error checking push subscription:', error);
            return false;
        }
    }

    async unsubscribeFromPush() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                await subscription.unsubscribe();
                
                // Notify server about unsubscription
                await fetch('/api/notifications.php?action=unsubscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint
                    })
                });
                
                this.isSubscribedToPush = false;
                this.stopNotificationPolling();
                console.log('Successfully unsubscribed from push notifications');
                return true;
            }
        } catch (error) {
            console.error('Error unsubscribing from push notifications:', error);
            return false;
        }
    }

    startNotificationPolling() {
        // Poll for new notifications every 30 seconds when online
        if (this.notificationCheckInterval) {
            clearInterval(this.notificationCheckInterval);
        }

        this.notificationCheckInterval = setInterval(async () => {
            if (this.isOnline && this.notificationPermission === 'granted') {
                await this.checkForNewNotifications();
            }
        }, 30000); // Check every 30 seconds

        // Initial check
        setTimeout(() => {
            if (this.isOnline && this.notificationPermission === 'granted') {
                this.checkForNewNotifications();
            }
        }, 2000);
    }

    stopNotificationPolling() {
        if (this.notificationCheckInterval) {
            clearInterval(this.notificationCheckInterval);
            this.notificationCheckInterval = null;
        }
    }

    async checkForNewNotifications() {
        try {
            const lastCheck = this.lastNotificationCheck || (Date.now() - 300000); // 5 minutes ago
            
            const response = await fetch(`/api/notifications.php?action=check&since=${lastCheck}`);
            
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            
            if (data.success && data.notifications && data.notifications.length > 0) {
                for (const notification of data.notifications) {
                    this.showLocalNotification(notification);
                }
            }

            this.lastNotificationCheck = Date.now();
        } catch (error) {
            console.error('Error checking for notifications:', error);
        }
    }

    showLocalNotification(notificationData) {
        if (this.notificationPermission !== 'granted') {
            return;
        }

        const options = {
            body: notificationData.body || notificationData.message,
            icon: '/assets/img/icons/icon-192x192.png',
            badge: '/assets/img/icons/badge-72x72.png',
            tag: notificationData.id || 'hackmate-notification',
            data: {
                url: notificationData.url || '/dashboard/',
                timestamp: Date.now(),
                id: notificationData.id
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
            ],
            requireInteraction: false,
            silent: false
        };

        const notification = new Notification(
            notificationData.title || 'HackMate Notification',
            options
        );

        // Handle notification click
        notification.onclick = () => {
            window.focus();
            if (notificationData.url) {
                window.location.href = notificationData.url;
            }
            notification.close();
        };

        // Auto-close after 10 seconds
        setTimeout(() => {
            notification.close();
        }, 10000);
    }

    // Utility function to convert VAPID key
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Public methods for notification management
    async enableNotifications() {
        const granted = await this.requestNotificationPermission();
        if (granted) {
            await this.subscribeToPushNotifications();
        }
        return granted;
    }

    async disableNotifications() {
        await this.unsubscribeFromPush();
        this.stopNotificationPolling();
        this.showToast('Notifications disabled', 'info');
    }

    getNotificationStatus() {
        return {
            permission: this.notificationPermission,
            isSubscribed: this.isSubscribedToPush,
            isPolling: !!this.notificationCheckInterval,
            supported: 'Notification' in window && 'serviceWorker' in navigator
        };
    }
}

// Initialize PWA Manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PWAManager;
}

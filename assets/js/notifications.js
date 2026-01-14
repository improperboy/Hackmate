// Push Notification Management
class NotificationManager {
    constructor() {
        this.vapidPublicKey = 'BEl62iUYgUivxIkv69yViEuiBIa40HvinOjw0xHj9UgMOHpFLEF7Z-PsiGSRN9QVHSPHrbud-2Y1-LlG_z3I0TQ'; // Replace with your VAPID public key
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.subscription = null;
        this.init();
    }

    async init() {
        if (!this.isSupported) {
            console.log('Push notifications are not supported');
            return;
        }

        try {
            // Register service worker if not already registered
            if (!navigator.serviceWorker.controller) {
                await navigator.serviceWorker.register('/sw.js');
                console.log('Service Worker registered successfully');
            }

            // Check current subscription status
            await this.checkSubscription();
            
            // Add UI elements for notification controls
            this.addNotificationUI();
            
        } catch (error) {
            console.error('Error initializing notifications:', error);
        }
    }

    async checkSubscription() {
        try {
            const registration = await navigator.serviceWorker.ready;
            this.subscription = await registration.pushManager.getSubscription();
            
            if (this.subscription) {
                console.log('User is already subscribed to notifications');
                this.updateUISubscribed();
                // Send subscription to server to ensure it's up to date
                await this.sendSubscriptionToServer(this.subscription);
            } else {
                console.log('User is not subscribed to notifications');
                this.updateUIUnsubscribed();
            }
        } catch (error) {
            console.error('Error checking subscription:', error);
        }
    }

    async requestPermission() {
        if (!this.isSupported) {
            alert('Push notifications are not supported in this browser');
            return false;
        }

        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log('Notification permission granted');
                await this.subscribe();
                return true;
            } else if (permission === 'denied') {
                console.log('Notification permission denied');
                alert('Notifications are blocked. Please enable them in your browser settings to receive updates.');
                return false;
            } else {
                console.log('Notification permission dismissed');
                return false;
            }
        } catch (error) {
            console.error('Error requesting permission:', error);
            return false;
        }
    }

    async subscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });

            console.log('User subscribed to notifications:', subscription);
            this.subscription = subscription;
            
            // Send subscription to server
            const success = await this.sendSubscriptionToServer(subscription);
            
            if (success) {
                this.updateUISubscribed();
                this.showNotification('Notifications Enabled', 'You will now receive updates when new content is posted!');
            }
            
            return subscription;
        } catch (error) {
            console.error('Error subscribing to notifications:', error);
            alert('Failed to enable notifications. Please try again.');
            return null;
        }
    }

    async unsubscribe() {
        try {
            if (this.subscription) {
                await this.subscription.unsubscribe();
                await this.removeSubscriptionFromServer(this.subscription);
                this.subscription = null;
                this.updateUIUnsubscribed();
                console.log('User unsubscribed from notifications');
            }
        } catch (error) {
            console.error('Error unsubscribing from notifications:', error);
        }
    }

    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/api/notifications/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription,
                    user_id: this.getCurrentUserId(),
                    user_type: this.getCurrentUserType()
                })
            });

            if (response.ok) {
                console.log('Subscription sent to server successfully');
                return true;
            } else {
                console.error('Failed to send subscription to server:', response.status);
                return false;
            }
        } catch (error) {
            console.error('Error sending subscription to server:', error);
            return false;
        }
    }

    async removeSubscriptionFromServer(subscription) {
        try {
            const response = await fetch('/api/notifications/unsubscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription,
                    user_id: this.getCurrentUserId()
                })
            });

            if (response.ok) {
                console.log('Subscription removed from server successfully');
            } else {
                console.error('Failed to remove subscription from server:', response.status);
            }
        } catch (error) {
            console.error('Error removing subscription from server:', error);
        }
    }

    addNotificationUI() {
        // Check if notification button already exists
        if (document.getElementById('notification-toggle')) {
            return;
        }

        // Create notification toggle button
        const notificationButton = document.createElement('button');
        notificationButton.id = 'notification-toggle';
        notificationButton.className = 'notification-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors';
        notificationButton.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5-5-5 5h5z M12 3v14"></path>
            </svg>
            <span>Enable Notifications</span>
        `;

        // Add click event
        notificationButton.addEventListener('click', async () => {
            if (this.subscription) {
                await this.unsubscribe();
            } else {
                await this.requestPermission();
            }
        });

        // Add to header or navigation area
        const header = document.querySelector('header') || document.querySelector('nav') || document.querySelector('.header');
        if (header) {
            header.appendChild(notificationButton);
        } else {
            // Add to body if no header found
            document.body.insertBefore(notificationButton, document.body.firstChild);
        }
    }

    updateUISubscribed() {
        const button = document.getElementById('notification-toggle');
        if (button) {
            button.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5-5-5 5h5z M12 3v14"></path>
                </svg>
                <span>Disable Notifications</span>
            `;
            button.className = 'notification-btn bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors';
        }
    }

    updateUIUnsubscribed() {
        const button = document.getElementById('notification-toggle');
        if (button) {
            button.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5-5-5 5h5z M12 3v14"></path>
                </svg>
                <span>Enable Notifications</span>
            `;
            button.className = 'notification-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors';
        }
    }

    showNotification(title, body, options = {}) {
        if (Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body: body,
                icon: '/assets/icons/icon-192x192.png',
                badge: '/assets/icons/icon-72x72.png',
                ...options
            });

            notification.onclick = function() {
                window.focus();
                notification.close();
            };

            // Auto close after 5 seconds
            setTimeout(() => {
                notification.close();
            }, 5000);
        }
    }

    getCurrentUserId() {
        // This should be implemented based on your session management
        // For now, return a placeholder or get from session storage
        return sessionStorage.getItem('user_id') || localStorage.getItem('user_id') || null;
    }

    getCurrentUserType() {
        // This should be implemented based on your session management
        return sessionStorage.getItem('user_type') || localStorage.getItem('user_type') || 'participant';
    }

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

    // Test notification functionality
    async testNotification() {
        if (this.subscription) {
            try {
                const response = await fetch('/api/notifications/test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: this.getCurrentUserId()
                    })
                });

                if (response.ok) {
                    console.log('Test notification sent');
                } else {
                    console.error('Failed to send test notification');
                }
            } catch (error) {
                console.error('Error sending test notification:', error);
            }
        } else {
            alert('Please enable notifications first');
        }
    }
}

// Initialize notification manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if ('serviceWorker' in navigator) {
        window.notificationManager = new NotificationManager();
    }
});

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}

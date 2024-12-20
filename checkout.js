class VotalityPricing {
    constructor() {
        this.init();
    }

    async init() {
        try {
            // Set default state first
            this.setDefaultPricingState();
            
            // Check authentication status
            const authStatus = await this.checkAuthStatus();
            
            if (authStatus.isLoggedIn) {
                await this.updateSubscriptionState();
            }
            
            this.setupEventListeners();
        } catch (error) {
            console.error('Initialization error:', error);
            // Continue with default free plan state
            this.setDefaultPricingState();
        }
    }

    setDefaultPricingState() {
        const buttons = document.querySelectorAll('.cta-button');
        buttons.forEach(button => {
            if (button.getAttribute('data-plan') === 'free') {
                button.textContent = 'Current Plan';
                button.disabled = true;
                button.classList.add('current-plan');
            } else {
                button.textContent = button.getAttribute('data-plan') === 'premium' ? 
                    'Choose Premium' : 'Choose Teams';
                button.disabled = false;
                button.classList.remove('current-plan');
            }
        });
    }

    async checkAuthStatus() {
        try {
            const response = await fetch('/check_login_status.php');
            const data = await response.json();
            return {
                isLoggedIn: data.isLoggedIn || false,
                userId: data.userId
            };
        } catch (error) {
            console.error('Auth check error:', error);
            return { isLoggedIn: false };
        }
    }

    async updateSubscriptionState() {
        try {
            const response = await fetch('/api/get_subscription.php');
            if (!response.ok) throw new Error('Subscription check failed');
            
            const data = await response.json();
            this.updateUIForSubscription(data);
        } catch (error) {
            console.error('Subscription check error:', error);
            // Continue with default state
        }
    }

    updateUIForSubscription(subscription) {
        const buttons = document.querySelectorAll('.cta-button');
        
        buttons.forEach(button => {
            const plan = button.getAttribute('data-plan');
            
            if (subscription.plan === plan) {
                button.textContent = 'Current Plan';
                button.disabled = true;
                button.classList.add('current-plan');
            } else {
                const planText = plan === 'premium' ? 'Choose Premium' : 'Choose Teams';
                button.textContent = planText;
                button.disabled = false;
                button.classList.remove('current-plan');
            }
        });
    }

    setupEventListeners() {
        const buttons = document.querySelectorAll('.cta-button');
        
        buttons.forEach(button => {
            if (button.disabled) return;
            
            button.addEventListener('click', async () => {
                try {
                    button.classList.add('loading');
                    
                    // Check authentication
                    const authStatus = await this.checkAuthStatus();
                    
                    if (!authStatus.isLoggedIn) {
                        sessionStorage.setItem('intended_plan', button.getAttribute('data-plan'));
                        window.location.href = '/signin.html?redirect=' + encodeURIComponent(window.location.pathname);
                        return;
                    }

                    const response = await fetch('/api/create-checkout.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            plan: button.getAttribute('data-plan')
                        })
                    });

                    if (!response.ok) {
                        throw new Error('Failed to create checkout session');
                    }

                    const data = await response.json();
                    
                    if (data.success && data.checkoutUrl) {
                        window.location.href = data.checkoutUrl;
                    } else {
                        throw new Error(data.message || 'Failed to create checkout session');
                    }

                } catch (error) {
                    console.error('Checkout error:', error);
                    alert('There was an error starting the checkout process. Please try again.');
                } finally {
                    button.classList.remove('loading');
                }
            });
        });
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    new VotalityPricing();
});
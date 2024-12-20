<?php
class LemonSqueezyConfig {
    // Your API key - consider moving this to an .env file
    private const API_KEY = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5NGQ1OWNlZi1kYmI4LTRlYTUtYjE3OC1kMjU0MGZjZDY5MTkiLCJqdGkiOiIyYmEyOTYzNTk4NTYzMTQxZDY2NjliYjAxYTNjNGM4ZDNiMmVmNTNjZjJkOGM0NDg1NmRhMWE4ZDRmYjZhZWY1ZjE4OWIyMWZlMWE2ZTA2YiIsImlhdCI6MTczMDEyNTAyNy42ODI0NTksIm5iZiI6MTczMDEyNTAyNy42ODI0NjIsImV4cCI6MjA0NTY1NzgyNy42Mzk5ODMsInN1YiI6IjM2MDY3OTciLCJzY29wZXMiOltdfQ.njOWQ9yeaHmagm1YQ4tDLyrh16SHnSG2qYIUWpYAMZJnHE04CvXnp_nYsOtYTasyIyAiIKiUTKwKykxA8yAMxHva6SjYokhhr_7idosnatpE19I6IEu3JUgpDHu98ZzMZj0OmzAUb4vRwXTHJ0giPqbCrnzfuZc82gbyLoM2PcZbzvXD8_x7iuR8dBQYh-6UwDDpWjMe7xoAvbpxO_kFwuJ2pP5Xp8X76TJliJ0taoBnNsRALCN80D5cvcULOfiVCUIxlK84zRLyh8IWipaL_XfmirzI4OwncPl13DMRMliKWCkuh9ajW9I2UFSp2MYd3iVQ4BHnSMhfUv8p4rSnpC0Syo_NjdR4mV72Y4bAex4y80hN5aMsePKcWKSPoMwGXLhiecUJh4FjYfM69b-AVmV7uq9brPYBw30gixqLNB2xOF41UbzxJ7fav9-8yxT256OWpc2rw6mh_sgMRzPwt0zfScG2mnCT57R3y0obTSR0nsMDPPaCu2_nO5rxxrFh';
    
    // Your store configuration
    private const PRODUCTS = [
        'premium' => [
            'product_id' => '378527',
            'variant_id' => '570057',
            'price' => 20.00
        ],
        'teams' => [
            'product_id' => '', // Add this once you create the Teams product
            'variant_id' => '', // Add this once you create the Teams product
            'price' => 30.00
        ]
    ];
    
    // Webhook configuration
    private const WEBHOOK_URL = 'https://votalityai.com/webhooks/lemon-squeezy.php';
    
    public static function getApiKey() {
        return self::API_KEY;
    }
    
    public static function getProductId($plan) {
        return self::PRODUCTS[$plan]['product_id'] ?? null;
    }
    
    public static function getVariantId($plan) {
        return self::PRODUCTS[$plan]['variant_id'] ?? null;
    }
    
    public static function getPrice($plan) {
        return self::PRODUCTS[$plan]['price'] ?? null;
    }
    
    public static function getCheckoutUrl($plan, $userId = null) {
        $variantId = self::getVariantId($plan);
        if (!$variantId) {
            return null;
        }
        
        $url = "https://votality.lemonsqueezy.com/checkout/buy/{$variantId}";
        
        // Add custom parameters
        if ($userId) {
            $url .= "?checkout[custom][user_id]=" . urlencode($userId);
        }
        
        return $url;
    }
}
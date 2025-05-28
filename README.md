# **WordPress Multisite Setup & Automated Subsite Creation via REST API**  

## **Table of Contents**  
1. [Introduction](#introduction)  
2. [Prerequisites](#prerequisites)  
3. [Enabling WordPress Multisite](#enabling-wordpress-multisite)  
4. [Configuring Network Settings](#configuring-network-settings)  
5. [Updating wp-config.php & .htaccess](#updating-wp-configphp--htaccess)  
6. [Installing WP Multisite Subsite Creator Plugin](#installing-wp-multisite-subsite-creator-plugin)  
7. [Creating Subsites via REST API](#creating-subsites-via-rest-api)  
8. [Troubleshooting](#troubleshooting)  
9. [Conclusion](#conclusion)  

---

## **1. Introduction**  
This guide explains how to set up **WordPress Multisite** and automate subsite creation using the **WP Multisite Subsite Creator** plugin via REST API. This is useful for developers managing multiple WordPress sites from a single installation.  

---

## **2. Prerequisites**  
- A working WordPress installation  
- Access to **wp-config.php** and **.htaccess**  
- Administrator privileges  
- PHP 7.4+ & MySQL 5.6+  
- For subdomains: Wildcard DNS configured (e.g., `*.domain.com`)  

---

## **3. Enabling WordPress Multisite**  
1. Open **wp-config.php** (located in WordPress root).  
2. Add this line **above** `/* That's all, stop editing! */`:  
   ```php
   define('WP_ALLOW_MULTISITE', true);
   ```
3. Save the file.  

---

## **4. Configuring Network Settings**  
1. Go to **WordPress Admin Dashboard → Tools → Network Setup**.  
2. Choose:  
   - **Sub-domains** (requires wildcard DNS)  
   - **Sub-directories** (simpler, no DNS changes needed)  
3. Click **Install**.  

---

## **5. Updating wp-config.php & .htaccess**  
### **A. wp-config.php Updates**  
WordPress will generate code to add to **wp-config.php**. Example for **sub-directories**:  
```php
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DOMAIN_CURRENT_SITE', 'yourdomain.com');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);
```  

### **B. .htaccess Updates**  
Replace existing rules with the generated ones. Example for **sub-directories**:  
```apache
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]

# Add trailing slash to /wp-admin
RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]
RewriteRule . index.php [L]
```  

**Save changes and log in again.**  

---

## **6. Installing WP Multisite Subsite Creator Plugin**  
1. Download the plugin (if custom, ensure it’s installed).  
2. Go to **Network Admin → Plugins → Add New → Upload**.  
3. Activate it **network-wide**.  

---

## **7. Creating Subsites via REST API**  
### **API Endpoint:**  
```
POST http://domain.com/wp-json/wpmss/v1/create-subsite
```  

### **Request Body (JSON):**  
```json
{
  "subdomain": "demo",
  "title": "Multisite API Test",
  "admin_username": "admin",
  "admin_email": "admin@domain.com",
  "admin_password": "admin123"
}
```  

### **Example (cURL):**  
```bash
curl -X POST http://domain.com/wp-json/wpmss/v1/create-subsite \
-H "Content-Type: application/json" \
-d '{
  "subdomain": "demo",
  "title": "API Test Site",
  "admin_username": "admin",
  "admin_email": "admin@domain.com",
  "admin_password": "admin123"
}'
```  

### **Expected Response:**  
```json
{
    "success": true,
    "message": "Subsite created successfully",
    "site_id": 6,
    "subdomain": "demo",
    "site_url": "https://demo.gravityformsplugin.test",
    "admin": {
        "username": "admin",
        "password": "admin123",
        "email": "admin@domain.com"
    }
}
```  

---

## **8. Troubleshooting**  
| Issue | Solution |  
|--------|------------|  
| **403 Forbidden** | Check `.htaccess` rules and file permissions. |  
| **Subdomain Not Working** | Configure wildcard DNS (`*.domain.com`). |  
| **API Not Found** | Ensure the plugin is network-activated. |  
| **Missing Styles** | Add `define('COOKIE_DOMAIN', $_SERVER['HTTP_HOST']);` to `wp-config.php`. |  

---

## **9. Conclusion**  
You’ve successfully:  
✅ Enabled WordPress Multisite  
✅ Configured network settings  
✅ Automated subsite creation via REST API  

This setup is ideal for SaaS platforms, multi-client sites, or large-scale WordPress networks.  

**Next Steps:**  
- Secure the API with authentication (e.g., JWT).  
- Add custom fields for subsite setup.  

🚀 **Happy Multisite Management!**
